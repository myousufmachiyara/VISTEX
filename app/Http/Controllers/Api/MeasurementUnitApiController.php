<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeasurementUnit;

class MeasurementUnitApiController extends Controller
{
    // GET /api/units
    public function index()
    {
        $units = MeasurementUnit::orderBy('name')->get(['id', 'name', 'shortcode']);
        return response()->json(['data' => $units]);
    }
}