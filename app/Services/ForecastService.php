<?php

namespace App\Services;

use App\Models\Forecast;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    public function __construct(private DocumentNumberService $numberService) {}

    public function create(array $data, ?int $userId = null): Forecast
    {
        return DB::transaction(function () use ($data, $userId) {

            $required = (float) $data['required_qty'];
            $onHand   = (float) ($data['stock_on_hand'] ?? 0);
            $onOrder  = (float) ($data['on_order_qty'] ?? 0);

            $shortfall = max(0, round($required - $onHand - $onOrder, 3));

            $forecast = Forecast::create([
                'forecast_no'       => $this->numberService->next('forecast', 'forecasts', 'forecast_no', 'FC'),
                'customer_id'        => $data['customer_id'] ?? null,
                'product_id'         => $data['product_id'],
                'required_qty'       => $required,
                'stock_on_hand'      => $onHand,
                'on_order_qty'       => $onOrder,
                'shortfall_qty'      => $shortfall,
                'required_by_date'   => $data['required_by_date'] ?? null,
                'remarks'            => $data['remarks'] ?? null,
                'status'             => 'Pending',
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            return $forecast->load('customer', 'product');
        });
    }

    public function approve(Forecast $forecast, int $approverId): Forecast
    {
        if ($forecast->status !== 'Pending') {
            throw new \Exception('This forecast has already been ' . strtolower($forecast->status) . '.');
        }

        $forecast->update([
            'status'      => 'Approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'updated_by'  => $approverId,
        ]);

        return $forecast;
    }

    public function reject(Forecast $forecast, int $approverId, string $reason): Forecast
    {
        if ($forecast->status !== 'Pending') {
            throw new \Exception('This forecast has already been ' . strtolower($forecast->status) . '.');
        }

        $forecast->update([
            'status'            => 'Rejected',
            'rejection_reason'  => $reason,
            'approved_by'       => $approverId,
            'approved_at'       => now(),
            'updated_by'        => $approverId,
        ]);

        return $forecast;
    }

    public function delete(Forecast $forecast): void
    {
        if ($forecast->status === 'Approved') {
            throw new \Exception('Cannot delete an approved forecast — reject it first if no longer needed, or leave it as a record.');
        }

        $forecast->delete();
    }
}