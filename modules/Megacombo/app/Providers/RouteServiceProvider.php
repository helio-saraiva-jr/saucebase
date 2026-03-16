<?php

namespace Modules\Megacombo\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path('megacombo', '/routes/web.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(module_path('megacombo', '/routes/api.php'));
    }
}
