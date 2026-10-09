<?php

namespace App\Http\Controllers;

use App\Models\{Location, Product, ProductCategory, Vendor};
use App\Services\Reports\{GreigeReports, PackagingReports, YarnReports};
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const FAMILIES = [
        'yarn'      => YarnReports::class,
        'greige'    => GreigeReports::class,
        'packaging' => PackagingReports::class,
    ];

    public function index()
    {
        $families = collect(self::FAMILIES)
            ->filter(fn($c, $g) => auth()->user()->can("reports.{$g}"))
            ->map(fn($c, $g) => ['title' => $c::title(), 'reports' => $c::catalog()]);

        abort_if($families->isEmpty(), 403, 'You do not have access to any reports.');
        return view('reports.index', compact('families'));
    }

    public function show(Request $request, string $group, string $key)
    {
        $class = self::FAMILIES[$group] ?? abort(404);
        abort_unless(auth()->user()->can("reports.{$group}"), 403, 'You do not have access to these reports.');
        $catalog = $class::catalog();
        $meta = $catalog[$key] ?? abort(404);

        $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date', 'asof' => 'nullable|date',
            'vendor_id' => 'nullable|integer', 'product_id' => 'nullable|integer', 'location_id' => 'nullable|integer',
            'category_id' => 'nullable|integer', 'days' => 'nullable|integer|min:1|max:3650',
            'location_scope' => 'nullable|in:own,mills,all',
        ]);

        $today = now()->toDateString();
        $f = [
            'from'           => $request->input('from', $today),
            'to'             => $request->input('to', $request->input('from', $today)),
            'asof'           => $request->input('asof', $today),
            'vendor_id'      => $request->integer('vendor_id') ?: null,
            'product_id'     => $request->integer('product_id') ?: null,
            'location_id'    => $request->integer('location_id') ?: null,
            'category_id'    => $request->integer('category_id') ?: null,
            'days'           => $request->integer('days') ?: 90,
            'location_scope' => $request->input('location_scope', 'own'),
        ];
        if ($f['from'] > $f['to']) [$f['from'], $f['to']] = [$f['to'], $f['from']];

        $result = (new $class)->run($key, $f);

        if ($request->input('export') === 'csv') {
            return $this->csv($meta['title'], $result, $f, $meta['filter']);
        }

        $options = $this->filterOptions($group, $f);
        return view('reports.show', compact('group', 'key', 'meta', 'catalog', 'result', 'f', 'options') + ['familyTitle' => $class::title()]);
    }

    private function filterOptions(string $group, array $f): array
    {
        $catCode = match ($group) { 'yarn' => 'yarn', 'greige' => 'greige', default => null };
        $catIds = $catCode
            ? ProductCategory::where('code', $catCode)->pluck('id')
            : collect([$f['category_id'] ?: ProductCategory::where('code', 'packaging')->value('id')]);

        return [
            'vendors'    => $group === 'packaging' ? collect() : Vendor::orderBy('name')->get(['id', 'name']),
            'products'   => Product::whereIn('category_id', $catIds)->orderBy('name')->get(['id', 'name']),
            'locations'  => Location::whereNull('vendor_id')->orderBy('name')->get(['id', 'name']),
            'mills'      => $group === 'greige' ? Location::whereNotNull('vendor_id')->orderBy('name')->get(['id', 'name']) : collect(),
            'categories' => $group === 'packaging' ? ProductCategory::orderBy('name')->get(['id', 'name', 'code']) : collect(),
        ];
    }

    // Excel-friendly CSV (UTF-8 BOM so Excel shows symbols correctly)
    private function csv(string $title, array $result, array $f, string $filter): StreamedResponse
    {
        $period = match ($filter) {
            'range' => date('d-M-Y', strtotime($f['from'])) . ' to ' . date('d-M-Y', strtotime($f['to'])),
            'asof'  => 'As on ' . date('d-M-Y', strtotime($f['asof'])),
            'days'  => "Idle over {$f['days']} days",
            default => 'As on ' . now()->format('d-M-Y'),
        };
        $filename = str_replace([' ', '/'], ['_', '-'], $title) . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($title, $result, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$title]);
            fputcsv($out, [$period]);
            fputcsv($out, []);
            fputcsv($out, array_column($result['columns'], 'label'));
            $totals = [];
            foreach ($result['rows'] as $row) {
                $line = [];
                foreach ($result['columns'] as $c) {
                    $v = $row[$c['key']] ?? '';
                    if ($c['type'] === 'date' && $v) $v = date('d-M-Y', strtotime($v));
                    $line[] = $v;
                    if ($c['total'] && is_numeric($row[$c['key']] ?? null)) $totals[$c['key']] = ($totals[$c['key']] ?? 0) + $row[$c['key']];
                }
                fputcsv($out, $line);
            }
            if ($result['rows'] && $totals) {
                fputcsv($out, array_map(fn($c, $i) => $i === 0 ? 'Total' : ($c['total'] ? round($totals[$c['key']] ?? 0, 3) : ''),
                    $result['columns'], array_keys($result['columns'])));
            }
            if (!empty($result['pending'])) fputcsv($out, [$result['pending']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
