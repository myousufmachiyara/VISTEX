<?php
namespace App\Services;

use App\Models\{Challan, ChallanDirectItem, ChallanItem, PurchaseOrder, Product, ProductCategory, LocationStockLedger};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChallanService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private NotificationService $notificationService
    ) {}

    // ── PO-based challan (gatekeeper) ──
    public function create(array $data, array $items, ?int $userId = null): Challan
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);
            $this->assertPoIsReceivable($po);

            $images = $data['challan_images'] ?? [];
            if (empty($images)) throw new \Exception('At least one photo of the challan is required.');

            $challan = Challan::create([
                'challan_no'           => $this->numberService->next('challan', 'challans', 'challan_no', 'CHL'),
                'entry_type'           => 'po',
                'category_id'          => $po->product_category_id,
                'purchase_order_id'    => $po->id,
                'vendor_challan_no'    => $data['vendor_challan_no'] ?? null,
                ...$this->transport($data),
                'received_date'        => $data['received_date'],
                'challan_images'       => $images,
                'status'               => 'AwaitingInspection',
                'has_objection'        => (bool) ($data['has_objection'] ?? false),
                'objection_remarks'    => $data['objection_remarks'] ?? null,
                'objection_voice_note' => $data['objection_voice_note'] ?? null,
                'remarks'              => $data['remarks'] ?? null,
                'received_by' => $userId, 'created_by' => $userId, 'updated_by' => $userId,
            ]);

            foreach ($items as $item) {
                ChallanItem::create([
                    'challan_id' => $challan->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'] ?? null,
                    'expected_qty' => $item['expected_qty'] ?? 0,
                    'received_qty' => $item['received_qty'] ?? 0,
                ]);
            }

            if ($challan->has_objection) {
                $objection = \App\Models\PurchaseOrderObjection::create([
                    'purchase_order_id' => $po->id, 'source' => 'gate', 'challan_id' => $challan->id,
                    'remarks' => $challan->objection_remarks ?: 'Objection raised at gate (see voice note).',
                    'status' => 'Open', 'raised_by' => $userId,
                ]);
                $challan->update(['objection_id' => $objection->id]);
            }

            $this->notificationService->notifyCategoryIncharges(
                $po->product_category_id, 'challan_received', 'New Challan Received',
                "Challan {$challan->challan_no} logged against PO {$po->order_no}" . ($challan->has_objection ? ' — with objection.' : '.'),
                'challan', $challan->id
            );

            return $challan->load('items');
        });
    }

    // ── No-PO challan (gatekeeper): vendor typed, items typed, nothing posted yet ──
    public function createDirect(array $data, array $items, ?int $userId = null): Challan
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $items = $this->cleanDirectItems($items);

            $images = $data['challan_images'] ?? [];
            if (empty($images)) throw new \Exception('At least one photo of the challan is required.');

            $challan = Challan::create([
                'challan_no'         => $this->numberService->next('challan', 'challans', 'challan_no', 'CHL'),
                'entry_type'         => 'direct',
                'category_id'        => $data['category_id'],
                'direct_vendor_name' => trim($data['vendor_name']),
                ...$this->transport($data),
                'received_date'      => $data['received_date'],
                'challan_images'     => $images,
                'status'             => 'AwaitingInspection',
                'remarks'            => $data['remarks'] ?? null,
                'received_by' => $userId, 'created_by' => $userId, 'updated_by' => $userId,
            ]);

            $this->writeDirectItems($challan, $items);

            $this->notificationService->notifyCategoryIncharges(
                $challan->category_id, 'challan_received', 'Purchase Without PO — Review Needed',
                "Challan {$challan->challan_no} logged without PO from '{$challan->direct_vendor_name}'.",
                'challan', $challan->id
            );

            return $challan->load('directItems');
        });
    }

    // ── Edit a challan before the incharge decides ──
    // $data: received_date, remarks, transport fields, challan_images (the final list: kept + new uploads),
    //        PO: vendor_challan_no, has_objection, objection_remarks, objection_voice_note (final value or null)
    //        direct: category_id, vendor_name
    // $items: PO → [['id' => challan_item_id, 'received_qty' => x]], direct → full replacement list
    public function update(Challan $challan, array $data, array $items, ?int $userId = null): Challan
    {
        $oldImages = $challan->challan_images ?? [];
        $oldVoice  = $challan->objection_voice_note;

        $challan = DB::transaction(function () use ($challan, $data, $items, $userId) {
            $challan = Challan::lockForUpdate()->findOrFail($challan->id);
            if ($challan->status !== Challan::AWAITING) {
                throw new \Exception("This challan is {$challan->status_label} and can no longer be edited.");
            }

            $images = array_values(array_filter($data['challan_images'] ?? []));
            if (empty($images)) throw new \Exception('At least one photo of the challan is required.');

            // Optional fields change only when sent, so a partial update never wipes them.
            $header = [
                'received_date'  => $data['received_date'] ?? $challan->received_date,
                'challan_images' => $images,
                'updated_by'     => $userId,
                ...array_intersect_key($this->transport($data), $data),
            ];
            if (array_key_exists('remarks', $data)) $header['remarks'] = $data['remarks'];

            if ($challan->entry_type === 'direct') {
                $vendor = trim($data['vendor_name'] ?? '');
                if ($vendor === '') throw new \Exception('Enter the vendor name.');
                $items = $this->cleanDirectItems($items);

                $challan->update($header + [
                    'category_id'        => $data['category_id'] ?? $challan->category_id,
                    'direct_vendor_name' => $vendor,
                ]);
                $challan->directItems()->delete();
                $this->writeDirectItems($challan, $items);
            } else {
                $hasObjection = (bool) ($data['has_objection'] ?? false);
                $voice = $hasObjection ? ($data['objection_voice_note'] ?? null) : null;
                $remarks = $hasObjection ? trim((string) ($data['objection_remarks'] ?? '')) : '';
                if ($hasObjection && $remarks === '' && !$voice) {
                    throw new \Exception('Add an objection note or a voice note.');
                }

                if (array_key_exists('vendor_challan_no', $data)) $header['vendor_challan_no'] = $data['vendor_challan_no'];
                $challan->update($header + [
                    'has_objection'        => $hasObjection,
                    'objection_remarks'    => $remarks ?: null,
                    'objection_voice_note' => $voice,
                ]);

                $rows = $challan->items()->get()->keyBy('id');
                foreach ($items as $row) {
                    $item = $rows->get((int) ($row['id'] ?? 0));
                    if (!$item) continue;
                    $qty = (float) ($row['received_qty'] ?? 0);
                    if ($qty < 0) throw new \Exception('Received quantity cannot be negative.');
                    $item->update(['received_qty' => $qty]);
                }

                $this->syncGateObjection($challan, $userId);
            }

            // Tell the category incharges (not the person who made the edit)
            $categoryId = $challan->category_id ?? $challan->purchaseOrder?->product_category_id;
            $incharges = $categoryId ? (ProductCategory::find($categoryId)?->incharges()->pluck('user_id')->all() ?? []) : [];
            $this->notificationService->notifyUsers(
                array_diff($incharges, [$userId]), 'challan_updated', 'Challan Updated',
                "Challan {$challan->challan_no} was edited before inspection.", 'challan', $challan->id
            );

            return $challan;
        });

        // Files are removed only after the update is committed.
        $disk = Storage::disk('public');
        foreach (array_diff($oldImages, $challan->challan_images ?? []) as $gone) {
            if ($gone && $disk->exists($gone)) $disk->delete($gone);
        }
        if ($oldVoice && $oldVoice !== $challan->objection_voice_note && $disk->exists($oldVoice)) $disk->delete($oldVoice);

        return $challan->fresh(['items', 'directItems']);
    }

    // Keep the PO's gate objection in step with the challan.
    private function syncGateObjection(Challan $challan, ?int $userId): void
    {
        $objection = $challan->objection_id ? \App\Models\PurchaseOrderObjection::find($challan->objection_id) : null;

        if ($challan->has_objection) {
            $text = $challan->objection_remarks ?: 'Objection raised at gate (see voice note).';
            if ($objection) {
                $objection->update(['remarks' => $text]);
            } else {
                $objection = \App\Models\PurchaseOrderObjection::create([
                    'purchase_order_id' => $challan->purchase_order_id, 'source' => 'gate', 'challan_id' => $challan->id,
                    'remarks' => $text, 'status' => 'Open', 'raised_by' => $userId,
                ]);
                $challan->update(['objection_id' => $objection->id]);
            }
        } elseif ($objection) {
            // Withdrawn at the gate before anyone acted on it
            if ($objection->source === 'gate' && $objection->status === 'Open') $objection->delete();
            $challan->update(['objection_id' => null]);
        }
    }

    private function transport(array $data): array
    {
        $clean = fn($v) => ($v = trim((string) $v)) === '' ? null : $v;
        $vehicle = $clean($data['vehicle_no'] ?? null);
        return [
            'vehicle_no'     => $vehicle ? strtoupper($vehicle) : null,
            'driver_name'    => $clean($data['driver_name'] ?? null),
            'driver_contact' => $clean($data['driver_contact'] ?? null),
        ];
    }

    private function cleanDirectItems(array $items): array
    {
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0 && trim($i['description'] ?? '') !== ''));
        if (empty($items)) throw new \Exception('Enter at least one item.');
        return $items;
    }

    private function writeDirectItems(Challan $challan, array $items): void
    {
        foreach ($items as $item) {
            $qty = (float) $item['quantity']; $price = (float) ($item['unit_price'] ?? 0);
            ChallanDirectItem::create([
                'challan_id' => $challan->id, 'description' => trim($item['description']),
                'quantity' => $qty, 'unit' => $item['unit'] ?? null,
                'unit_price' => $price, 'amount' => round($qty * $price, 2),
            ]);
        }
    }

    // ── Incharge review of a no-PO challan: links accounts, stock and expense, posts ledger ──
    public function reviewDirect(Challan $challan, array $header, array $items, int $userId): Challan
    {
        return DB::transaction(function () use ($challan, $header, $items, $userId) {
            if ($challan->entry_type !== 'direct') throw new \Exception('This is not a no-PO entry.');
            if ($challan->status !== 'AwaitingInspection') throw new \Exception('This challan has already been reviewed.');

            $mapping = app(AccountMappingService::class);
            $hasVendor = !empty($header['payable_vendor_id']);
            if (!$hasVendor && empty($header['payable_account_id'])) {
                throw new \Exception('Select the vendor or payable account this purchase is owed to.');
            }
            $payableAccountId = $hasVendor ? $mapping->accountId('accounts_payable') : (int) $header['payable_account_id'];
            if (!$payableAccountId) throw new \Exception('Accounts Payable mapping is not configured.');

            $rows = $challan->directItems->keyBy('id');
            $debits = []; // account_id => amount

            foreach ($items as $row) {
                $item = $rows->get((int) $row['id']);
                if (!$item) throw new \Exception('Invalid item in submission.');
                $amount = round((float) $item->amount, 2);

                if ($row['treatment'] === 'stock') {
                    $category = ProductCategory::findOrFail($row['product_category_id']);
                    $pid = $row['product_id'] ?? 'new';
                    $product = ($pid === 'new' || $pid === '' || $pid === null)
                        ? $this->makeProductFromItem($item, $category->id, $row['measurement_unit_id'] ?? null)
                        : Product::findOrFail((int) $pid);

                    $stockAccount = $category->stock_account_id ?: $mapping->accountId('stock_in_hand');
                    if (!$stockAccount) throw new \Exception("No stock account set for category {$category->name}.");
                    $debits[$stockAccount] = ($debits[$stockAccount] ?? 0) + $amount;

                    LocationStockLedger::create([
                        'doc_no' => $challan->challan_no, 'location_id' => $header['location_id'], 'product_id' => $product->id,
                        'status' => 'fresh', 'quantity' => $item->quantity, 'amount' => $amount,
                        'reference_type' => 'Challan', 'reference_id' => $challan->id, 'entry_date' => $challan->received_date,
                    ]);
                    $item->update(['treatment' => 'stock', 'product_category_id' => $category->id, 'product_id' => $product->id, 'expense_account_id' => null]);
                } else {
                    $accId = (int) $row['expense_account_id'];
                    $debits[$accId] = ($debits[$accId] ?? 0) + $amount;
                    $item->update(['treatment' => 'expense', 'expense_account_id' => $accId, 'product_id' => null, 'product_category_id' => null]);
                }
            }

            $total = round(array_sum($debits), 2);
            $party = $hasVendor ? ['party_type' => 'vendor', 'party_id' => (int) $header['payable_vendor_id']] : [];

            $lines = [];
            foreach ($debits as $accId => $amt) $lines[] = ['account_id' => $accId, 'debit' => round($amt, 2), 'credit' => 0];
            $lines[] = array_merge(['account_id' => $payableAccountId, 'debit' => 0, 'credit' => $total], $party);

            // Optional: settled straight away from petty cash / bank / staff account
            if (!empty($header['paid_from_account_id'])) {
                $lines[] = array_merge(['account_id' => $payableAccountId, 'debit' => $total, 'credit' => 0], $party);
                $lines[] = ['account_id' => (int) $header['paid_from_account_id'], 'debit' => 0, 'credit' => $total];
            }

            app(VoucherService::class)->post(
                'system', $challan->received_date->format('Y-m-d'), $lines,
                "Purchase without PO {$challan->challan_no} — {$challan->direct_vendor_name}",
                'Challan', $challan->id, $userId
            );

            $challan->update([
                'status' => 'Accepted', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'updated_by' => $userId,
                'payable_vendor_id' => $header['payable_vendor_id'] ?? null,
                'payable_account_id' => $payableAccountId,
                'paid_from_account_id' => $header['paid_from_account_id'] ?? null,
            ]);

            return $challan->fresh('directItems');
        });
    }

    private function makeProductFromItem(ChallanDirectItem $item, int $categoryId, $unitId): Product
    {
        if (!$unitId) throw new \Exception("Select a unit to create the new item '{$item->description}'.");

        $base = Str::slug($item->description) ?: 'item';
        $sku = $base; $n = 2;
        while (DB::table('products')->where('sku', $sku)->exists()) $sku = $base . '-' . $n++;

        return Product::create([
            'name' => $item->description, 'sku' => $sku, 'category_id' => $categoryId,
            'measurement_unit' => $unitId, 'is_active' => true,
        ]);
    }

    public function rejectDirect(Challan $challan, int $userId, string $reason): Challan
    {
        if ($challan->entry_type !== 'direct') throw new \Exception('This is not a no-PO entry.');
        if ($challan->status !== 'AwaitingInspection') throw new \Exception('This entry has already been processed.');

        $challan->update([
            'status' => 'Rejected', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'updated_by' => $userId,
            'remarks' => trim(($challan->remarks ?? '') . ' [Rejected: ' . $reason . ']'),
        ]);
        return $challan->fresh();
    }

    private function assertPoIsReceivable(PurchaseOrder $po): void
    {
        if ($po->type === 'purchase' && !in_array($po->status, ['Approved', 'Issued', 'PartiallyReceived'])) {
            throw new \Exception('This Purchase Order is not yet Approved, or is already fully received.');
        }
        if (in_array($po->type, ['weaving', 'processing']) && !in_array($po->status, ['Issued', 'PartiallyReceived'])) {
            throw new \Exception('This Purchase Order has not been Issued yet, or is already fully received.');
        }
    }

    // Legacy GRN path: only touch a challan nobody has reviewed yet.
    // The incharge review flow sets its own final status.
    public function markProcessed(?Challan $challan): void
    {
        if ($challan && $challan->status === Challan::AWAITING) {
            $challan->update(['status' => Challan::PROCESSED]);
        }
    }
}