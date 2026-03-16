<?php

namespace Modules\Megacombo\Providers;

use App\Providers\ModuleServiceProvider;

class MegacomboServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Megacombo';

    protected string $nameLower = 'megacombo';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
