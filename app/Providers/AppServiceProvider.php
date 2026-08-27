<?php

namespace App\Providers;
 
use App\Models\Vendor;
use App\Models\Customer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;   // add this

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
 
    public function boot(): void
    {
        Schema::defaultStringLength(191);   // add this line
    }
}