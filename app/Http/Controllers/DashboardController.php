<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return view('home', ['d' => $dashboard->build(auth()->user())]);
    }
}
