<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\DB;

class LocationService
{
    public function create(array $data): Location
    {
        return DB::transaction(function () use ($data) {
            // Only one location can be the default "our warehouse"
            if (!empty($data['is_default'])) {
                Location::where('is_default', true)->update(['is_default' => false]);
            }

            return Location::create($data);
        });
    }

    public function update(Location $location, array $data): Location
    {
        return DB::transaction(function () use ($location, $data) {
            if (!empty($data['is_default'])) {
                Location::where('is_default', true)
                    ->where('id', '!=', $location->id)
                    ->update(['is_default' => false]);
            }

            $location->update($data);
            return $location;
        });
    }

    public function delete(Location $location): void
    {
        $hasStock = \App\Models\LocationStockLedger::where('location_id', $location->id)->exists();

        if ($hasStock) {
            throw new \Exception('Cannot delete — this location has stock movement history. Deactivate instead.');
        }

        if ($location->is_default) {
            throw new \Exception('Cannot delete the default warehouse location. Set another location as default first.');
        }

        $location->delete();
    }
}