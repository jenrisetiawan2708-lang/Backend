<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOnly;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Register middleware alias
        Route::aliasMiddleware('admin.only', AdminOnly::class);

        // Set locale Indonesia untuk Carbon
        \Carbon\Carbon::setLocale('id');
    }
}
