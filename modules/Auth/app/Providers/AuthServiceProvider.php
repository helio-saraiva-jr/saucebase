<?php

namespace Modules\Auth\Providers;

use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Auth';

    protected string $nameLower = 'auth';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    /**
     * Share Inertia data globally.
     */
    protected function shareInertiaData(): void
    {
        Inertia::share('auth.user', fn () => Auth::user());
        Inertia::share('auth.profile', function () {
            $user = Auth::user();

            if (! $user) {
                return null;
            }

            $isRepresentative = $user->hasRole('admin');

            return [
                'key' => $isRepresentative ? 'representative' : 'client',
                'label' => $isRepresentative ? 'Representante' : 'Cliente',
            ];
        });
        Inertia::share('canLogin', true);
        Inertia::share('canRegister', true);
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        parent::registerConfig();

        $this->mergeConfigFrom(module_path($this->name, 'config/services.php'), 'services');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
