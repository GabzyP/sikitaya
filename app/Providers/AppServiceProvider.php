<?php

namespace App\Providers;

use App\Contracts\MidtransGateway;
use App\Services\MidtransGatewayService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MidtransGateway::class, MidtransGatewayService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
