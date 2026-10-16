<?php

namespace App\Services;

use App\Models\Challan;
use App\Models\PurchaseReceiving;
use Carbon\Carbon;

/**
 * Goods Receipt Note PDF — same TCPDF look as the Purchase Order print.
 * Built from the challan (gate count + inspection) and, once posted, its GRN.
 * Deliberately shows NO rates or amounts: it is a quantity document for the
 * gate, store and inspection staff.
 */
class GrnPdf
{
    public function render(Challan $challan, ?PurchaseReceiving $receiving = null): string
    {
        $challan->loadMissing([
            'purchaseOrder.vendor', 'purchaseOrder.category', 'vendor', 'category',
            'items.product.measurementUnit', 'items.purchaseOrderItem', 'directItems',
            'receivedBy', 'reviewedBy',
        ]);
        $receiving?->loadMissing(['items', 'approver']);

        $po     = $challan->purchaseOrder;
        $vendor = $challan->vendor ?? $po?->vendor;
        $docNo  = $receiving->receiving_no ?? $challan->challan_no;
        $isProcessing = ($po?->type === 'processing');

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('VISTEX (Private) Limited');
        $pdf->SetTitle($docNo);
        $pdf->SetSubject('Goods Receipt Note');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logo = public_path('assets/img/vistex-logo.png');
        if (file_exists($logo)) $pdf->Image($logo, 6, 8, 60);

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(110, 10);
        $pdf->Cell(90, 8, 'GOODS RECEIPT NOTE', 0, 1, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(110, 20);
        $pdf->Cell(90, 6, $docNo, 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(110, 26);
        $status = $receiving
            ? ($receiving->status === 'PendingApproval' ? 'Pending Approval' : $receiving->status)
            : (Challan::STATUS_LABELS[$challan->status] ?? $challan->status);
        $pdf->Cell(90, 5, 'Status: ' . $status, 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(8);

        $e = fn($v) => e($v ?? '-');
        $d = fn($v) => $v ? Carbon::parse($v)->format('d-M-Y') : '-';

        $pdf->writeHTML('
        <table cellpadding="6" cellspacing="0" width="100%">
        <tr>
            <td width="50%" style="border:1px solid #333;">
            <b style="font-size:11px;">SUPPLIER</b><br><br>
            <b>' . $e($vendor->name ?? $challan->direct_vendor_name) . '</b><br>
            NTN: ' . $e($vendor->ntn_number ?? null) . '<br>
            ' . nl2br($e($vendor->address ?? null)) . '
            </td>
            <td width="4%"></td>
            <td width="46%" style="border:1px solid #333;">
            <b style="font-size:11px;">RECEIVED AT</b><br><br>
            <b>VISTEX (Private) Limited</b><br>
            F-128, Hub River Road, SITE Area, Karachi 75600, Pakistan
            </td>
        </tr>
        </table>', true, false, false, false, '');
        $pdf->Ln(3);

        $pdf->writeHTML('
        <table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #333; font-size:9px;">
        <tr>
            <td width="20%"><b>Gate Challan #</b><br>' . $e($challan->challan_no) . '</td>
            <td width="20%"><b>Supplier Challan #</b><br>' . $e($challan->vendor_challan_no) . '</td>
            <td width="20%"><b>PO #</b><br>' . $e($po->order_no ?? 'Direct') . '</td>
            <td width="20%"><b>Received Date</b><br>' . $d($challan->received_date) . '</td>
            <td width="20%"><b>GRN Date</b><br>' . $d($receiving->receiving_date ?? null) . '</td>
        </tr>
        <tr>
            <td><b>Category</b><br>' . $e($challan->category->name ?? $po?->category?->name) . '</td>
            <td><b>Vehicle #</b><br>' . $e($challan->vehicle_no) . '</td>
            <td><b>Driver</b><br>' . $e($challan->driver_name) . '</td>
            <td><b>Driver Contact</b><br>' . $e($challan->driver_contact) . '</td>
            <td><b>Decision</b><br>' . $e(Challan::DECISIONS[$challan->decision] ?? $challan->decision) . '</td>
        </tr>
        </table>', true, false, false, false, '');
        $pdf->Ln(3);

        $pdf->writeHTML($this->itemsTable($challan, $receiving, $isProcessing), true, false, false, false, '');

        $notes = trim(implode("\n", array_filter([
            $challan->decision_remarks ? 'Inspection: ' . $challan->decision_remarks : null,
            $challan->objection_remarks ? 'Objection: ' . $challan->objection_remarks : null,
            $challan->remarks ? 'Remarks: ' . $challan->remarks : null,
            $receiving?->remarks ? 'GRN: ' . $receiving->remarks : null,
        ])));
        if ($notes !== '') {
            $pdf->Ln(2);
            $pdf->writeHTML('<table cellpadding="4" width="100%" style="border:1px solid #333; font-size:9px;"><tr><td>'
                . nl2br(e($notes)) . '</td></tr></table>', true, false, false, false, '');
        }

        // Signatures — names printed above the line when known
        $pdf->Ln(18);
        $sig = fn($title, $name) => '<td width="33%" style="text-align:center;">'
            . ($name ? e($name) : '&nbsp;') . '<br>______________________<br><b>' . $title . '</b></td>';
        $pdf->writeHTML('<table width="100%" style="font-size:9px;"><tr>'
            . $sig('Received By (Gate)', $challan->receivedBy->name ?? null)
            . $sig('Inspected By', $challan->reviewedBy->name ?? null)
            . $sig('Approved By', $receiving?->approver?->name)
            . '</tr></table>', true, false, false, false, '');

        return $pdf->Output($docNo . '.pdf', 'S');
    }

    private function itemsTable(Challan $challan, ?PurchaseReceiving $receiving, bool $grades): string
    {
        $th = fn($t, $w, $al = 'center') => '<th width="' . $w . '%" style="border:1px solid #333; text-align:' . $al . ';">' . $t . '</th>';
        $td = fn($v, $al = 'center') => '<td style="border:1px solid #333; text-align:' . $al . ';">' . $v . '</td>';
        $n  = fn($v) => $v === null || $v === '' ? '-' : rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        $html = '<table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #333; font-size:9px;">'
              . '<tr style="font-weight:bold; background-color:#f0f0f0;">';

        // Direct (no-PO) challans: plain description list
        if ($challan->items->isEmpty() && $challan->directItems->isNotEmpty()) {
            $html .= $th('#', 6) . $th('Description', 64, 'left') . $th('Quantity', 18) . $th('Unit', 12) . '</tr>';
            foreach ($challan->directItems as $i => $it) {
                $html .= '<tr>' . $td($i + 1) . $td(e($it->description), 'left') . $td($n($it->quantity)) . $td(e($it->unit ?? '')) . '</tr>';
            }
            return $html . '</table>';
        }

        $recvByPoItem = $receiving ? $receiving->items->keyBy('purchase_order_item_id') : collect();

        if ($grades) {
            $html .= $th('#', 4) . $th('Item', 24, 'left') . $th('Bags', 7) . $th('Expected', 10) . $th('Gate Count', 10)
                   . $th('A', 8) . $th('B', 8) . $th('C', 8) . $th('Accepted', 10) . $th('Rejected', 11) . '</tr>';
        } else {
            $html .= $th('#', 5) . $th('Item', 31, 'left') . $th('Bags / Packs', 11) . $th('Ordered', 12) . $th('Gate Count', 12)
                   . $th('Accepted', 11) . $th('Rejected', 10) . $th('Unit', 8) . '</tr>';
        }

        $tot = ['packs' => 0, 'gate' => 0, 'acc' => 0, 'rej' => 0, 'a' => 0, 'b' => 0, 'c' => 0];
        foreach ($challan->items as $i => $it) {
            $ri   = $recvByPoItem->get($it->purchase_order_item_id);
            $acc  = $it->accepted_qty ?? ($ri ? (float) $ri->quantity_received - (float) $ri->quantity_rejected : null);
            $rej  = $it->rejected_qty ?? ($ri ? $ri->quantity_rejected : null);
            $unit = $it->product->measurementUnit->shortcode ?? '';
            $name = e($it->display_name ?? ($it->product->name ?? $it->description ?? ''));
            $tot['packs'] += (float) $it->packs; $tot['gate'] += (float) $it->received_qty;
            $tot['acc'] += (float) $acc; $tot['rej'] += (float) $rej;

            if ($grades) {
                $a = $ri->qty_a ?? $it->qty_a; $b = $ri->qty_b ?? $it->qty_b; $c = $ri->qty_c ?? $it->qty_c;
                $tot['a'] += (float) $a; $tot['b'] += (float) $b; $tot['c'] += (float) $c;
                $html .= '<tr>' . $td($i + 1) . $td($name . ($unit ? ' (' . e($unit) . ')' : ''), 'left') . $td($n($it->packs))
                       . $td($n($it->expected_qty)) . $td($n($it->received_qty)) . $td($n($a)) . $td($n($b)) . $td($n($c))
                       . $td($n($acc)) . $td($n($rej)) . '</tr>';
            } else {
                $html .= '<tr>' . $td($i + 1) . $td($name, 'left') . $td($n($it->packs)) . $td($n($it->expected_qty))
                       . $td($n($it->received_qty)) . $td($n($acc)) . $td($n($rej)) . $td(e($unit)) . '</tr>';
            }
        }

        $b = fn($v) => '<td style="border:1px solid #333; text-align:center;"><b>' . $n($v) . '</b></td>';
        if ($grades) {
            $html .= '<tr><td colspan="2" style="border:1px solid #333; text-align:right;"><b>Total</b></td>' . $b($tot['packs'] ?: null)
                   . '<td style="border:1px solid #333;"></td>' . $b($tot['gate']) . $b($tot['a']) . $b($tot['b']) . $b($tot['c'])
                   . $b($tot['acc']) . $b($tot['rej']) . '</tr>';
        } else {
            $html .= '<tr><td colspan="2" style="border:1px solid #333; text-align:right;"><b>Total</b></td>' . $b($tot['packs'] ?: null)
                   . '<td style="border:1px solid #333;"></td>' . $b($tot['gate']) . $b($tot['acc']) . $b($tot['rej'])
                   . '<td style="border:1px solid #333;"></td></tr>';
        }
        return $html . '</table>';
    }
}
