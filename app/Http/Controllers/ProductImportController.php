<?php

namespace App\Http\Controllers;

use App\Services\ImportProductsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductImportController extends Controller
{
    public function __construct(private ImportProductsService $service) {}

    public function form()
    {
        return view('products.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $result = $this->service->import($request->file('csv_file')->getRealPath(), auth()->id());

            Log::info('[ProductImport] Completed', $result + ['by' => auth()->id()]);

            $message = "Imported {$result['imported']} products. Skipped {$result['skipped']} rows.";
            if (!empty($result['errors'])) {
                $message .= ' ' . count($result['errors']) . ' errors occurred.';
            }

            return redirect()->route('products.index')
                ->with('success', $message)
                ->with('import_errors', $result['errors']);

        } catch (\Exception $e) {
            Log::error('[ProductImport] Failed', ['message' => $e->getMessage()]);
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}