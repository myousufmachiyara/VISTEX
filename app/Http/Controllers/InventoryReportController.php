<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Vendor;
use App\Models\WarehouseStockMovement;
use App\Models\VendorStockLedger;

class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $tab       = $request->tab ?? 'IL';
        $productId = $request->item_id ?: null;
        $from      = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to        = $request->to_date   ?? now()->toDateString();

        $allProducts   = Product::with('category', 'measurementUnit')->orderBy('name')->get();
        $allVendors    = Vendor::active()->orderBy('name')->get();
        $allCategories = ProductCategory::orderBy('name')->get();

        $itemLedger      = collect();
        $openingBalance  = 0;
        $stockInHand     = collect();
        $vendorStock     = collect();
        $stockMovements  = collect(); // STR tab — gate pass / job issue / job receive
        $nonMovingItems  = collect();
        $reorderLevel    = collect();

        $product = $productId ? Product::find($productId) : null;

        // ────────────────────────────────────────────────────────────
        // 1. ITEM LEDGER — full movement history for one product
        // ────────────────────────────────────────────────────────────
        if ($tab === 'IL' && $product) {
            $priorNet = WarehouseStockMovement::where('product_id', $product->id)
                ->where('movement_date', '<', $from)
                ->sum('quantity');
            $openingBalance = (float) $product->opening_stock + (float) $priorNet;

            $rows = WarehouseStockMovement::where('product_id', $product->id)
                ->whereBetween('movement_date', [$from, $to])
                ->orderBy('movement_date')
                ->orderBy('id')
                ->get();

            $running = $openingBalance;
            $itemLedger = $rows->map(function ($m) use (&$running) {
                $running += (float) $m->quantity;
                return [
                    'date'        => $m->movement_date,
                    'type'        => $m->movement_type,
                    'description' => $m->reference_type
                        . ($m->doc_no ? " — {$m->doc_no}" : ($m->reference_id ? " #{$m->reference_id}" : '')),
                    'qty_in'      => $m->quantity > 0 ? $m->quantity : 0,
                    'qty_out'     => $m->quantity < 0 ? abs($m->quantity) : 0,
                    'value'       => abs($m->amount),
                    'balance'     => $running,
                ];
            });
        }

        // ────────────────────────────────────────────────────────────
        // 2. STOCK IN HAND — current qty + value, own warehouse
        // ────────────────────────────────────────────────────────────
        if ($tab === 'SR') {
            $categoryId = $request->category_id;

            $stockInHand = $allProducts
                ->when($categoryId, fn($c) => $c->where('category_id', $categoryId))
                ->when($productId, fn($c) => $c->where('id', $productId))
                ->map(function ($p) {
                    $qty = $p->current_stock;
                    $wac = $p->weighted_average_cost;
                    return [
                        'product'   => $p->name,
                        'sku'       => $p->sku,
                        'category'  => $p->category->name ?? '—',
                        'unit'      => $p->measurementUnit->shortcode ?? '',
                        'quantity'  => round($qty, 3),
                        'rate'      => round($wac, 2),
                        'total'     => round($qty * $wac, 2),
                    ];
                })
                ->filter(fn($s) => $productId || $s['quantity'] != 0)
                ->values();
        }

        // ────────────────────────────────────────────────────────────
        // 3. VENDOR STOCK (Fresh / Issued / Leftover) — replaces LOC tab
        // ────────────────────────────────────────────────────────────
        if ($tab === 'LOC') {
            $vendorId = $request->vendor_id;

            $rows = VendorStockLedger::query()
                ->selectRaw('vendor_id, product_id, status, SUM(quantity) as qty')
                ->groupBy('vendor_id', 'product_id', 'status')
                ->havingRaw('SUM(quantity) > 0.001')
                ->when($vendorId, fn($q) => $q->where('vendor_id', $vendorId))
                ->get();

            $vendorIds  = $rows->pluck('vendor_id')->unique();
            $productIds = $rows->pluck('product_id')->unique();
            $vendorsById  = Vendor::whereIn('id', $vendorIds)->get()->keyBy('id');
            $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row->vendor_id][$row->product_id][$row->status] = (float) $row->qty;
            }

            foreach ($grouped as $vId => $products) {
                foreach ($products as $pId => $statuses) {
                    $fresh    = $statuses['fresh']    ?? 0;
                    $issued   = $statuses['issued']   ?? 0;
                    $leftover = $statuses['leftover'] ?? 0;
                    $total    = $fresh + $issued + $leftover;
                    if ($total <= 0.001) continue;

                    $vendorStock->push([
                        'vendor'   => $vendorsById[$vId]->name ?? 'Unknown',
                        'product'  => $productsById[$pId]->name ?? 'Unknown',
                        'sku'      => $productsById[$pId]->sku ?? '',
                        'fresh'    => round($fresh, 3),
                        'issued'   => round($issued, 3),
                        'leftover' => round($leftover, 3),
                        'total'    => round($total, 3),
                    ]);
                }
            }
            $vendorStock = $vendorStock->sortBy('vendor')->values();
        }

        // ────────────────────────────────────────────────────────────
        // 4. STOCK MOVEMENTS (Gate Pass / Job Issue / Job Receive) — STR tab
        // ────────────────────────────────────────────────────────────
        if ($tab === 'STR') {
            $vendorId = $request->vendor_id;

            // Vendor-side ledger movements (gate pass out, job issue, job receive)
            $vendorMoves = VendorStockLedger::with('vendor', 'product')
                ->whereIn('status', ['fresh']) // fresh = the actual gate-pass "in" event at vendor
                ->whereBetween('entry_date', [$from, $to])
                ->when($vendorId, fn($q) => $q->where('vendor_id', $vendorId))
                ->get()
                ->map(fn($m) => [
                    'date'      => $m->entry_date,
                    'reference' => $m->doc_no ?? $m->reference_type,
                    'type'      => 'Gate Pass Out',
                    'product'   => $m->product->name ?? '',
                    'from'      => 'Our Warehouse',
                    'to'        => $m->vendor->name ?? '',
                    'quantity'  => $m->quantity,
                ]);

            // Warehouse-side (output arriving back from job receive)
            $warehouseMoves = WarehouseStockMovement::with('product')
                ->where('movement_type', 'JobReceiveOutput')
                ->whereBetween('movement_date', [$from, $to])
                ->get()
                ->map(fn($m) => [
                    'date'      => $m->movement_date,
                    'reference' => $m->doc_no ?? "#{$m->reference_id}",
                    'type'      => 'Job Receive Output',
                    'product'   => $m->product->name ?? '',
                    'from'      => 'Vendor',
                    'to'        => 'Our Warehouse',
                    'quantity'  => $m->quantity,
                ]);

            $stockMovements = $vendorMoves->concat($warehouseMoves)->sortBy('date')->values();
        }

        // ────────────────────────────────────────────────────────────
        // 5. NON-MOVING ITEMS
        // ────────────────────────────────────────────────────────────
        if ($tab === 'NMI') {
            $months    = (int) ($request->months ?? 3);
            $threshold = now()->subMonths($months)->toDateString();

            foreach ($allProducts as $p) {
                $stockQty = $p->current_stock;
                if ($stockQty <= 0) continue;

                $lastMovementDate = WarehouseStockMovement::where('product_id', $p->id)
                    ->max('movement_date');

                if (!$lastMovementDate || $lastMovementDate <= $threshold) {
                    $nonMovingItems->push([
                        'product'       => $p->name,
                        'sku'           => $p->sku,
                        'stock_qty'     => round($stockQty, 2),
                        'last_date'     => $lastMovementDate ?? 'Never',
                        'days_inactive' => $lastMovementDate ? Carbon::parse($lastMovementDate)->diffInDays(now()) : null,
                    ]);
                }
            }
            $nonMovingItems = $nonMovingItems->sortByDesc('days_inactive')->values();
        }

        // ────────────────────────────────────────────────────────────
        // 6. REORDER LEVEL
        // ────────────────────────────────────────────────────────────
        if ($tab === 'ROL') {
            foreach ($allProducts as $p) {
                $level = (float) ($p->reorder_level ?? 0);
                if ($level <= 0) continue;

                $stockQty = $p->current_stock;
                if ($stockQty <= $level) {
                    $reorderLevel->push([
                        'product'       => $p->name,
                        'sku'           => $p->sku,
                        'stock_inhand'  => round($stockQty, 2),
                        'reorder_level' => $level,
                        'shortage'      => round(max(0, $level - $stockQty), 2),
                    ]);
                }
            }
            $reorderLevel = $reorderLevel->sortByDesc('shortage')->values();
        }

        return view('reports.inventory_reports', [
            'products'        => $allProducts,
            'vendors'         => $allVendors,
            'categories'      => $allCategories,
            'tab'             => $tab,
            'itemLedger'      => $itemLedger,
            'openingBalance'  => $openingBalance,
            'stockInHand'     => $stockInHand,
            'vendorStock'     => $vendorStock,
            'stockMovements'  => $stockMovements,
            'nonMovingItems'  => $nonMovingItems,
            'reorderLevel'    => $reorderLevel,
            'from'            => $from,
            'to'              => $to,
            'productId'       => $productId,
        ]);
    }

    public function yarnAtMills()
    {
        $balances = \App\Models\YarnInProcessLedger::balancesByVendor();
        return view('reports.yarn_at_mills', compact('balances'));
    }
}