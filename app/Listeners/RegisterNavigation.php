<?php

namespace App\Listeners;

use App\Events\NavigationRegistering;
use Illuminate\Support\Facades\Auth;
use Spatie\Navigation\Facades\Navigation;
use Spatie\Navigation\Section;

class RegisterNavigation
{
    /**
     * Handle the event.
     */
    public function handle(NavigationRegistering $event): void
    {
        Navigation::addWhen(
            fn () => Auth::check() && Auth::user()->isAdmin(),
            'Dashboard',
            route('dashboard'),
            function (Section $section) {
                $section->attributes([
                    'group' => 'main',
                    'slug' => 'dashboard',
                    'order' => 0,
                ]);
            }
        );

        Navigation::addWhen(
            fn () => Auth::check() && Auth::user()->isAdmin(),
            'Ferramentas',
            '#',
            function (Section $section) {
                $section->attributes([
                    'group' => 'main',
                    'slug' => 'megacombo',
                    'order' => 10,
                ]);

                $section->add(
                    'Calculadora financeira',
                    route('megacombo.financial-calculator'),
                    function (Section $childSection) {
                        $childSection->attributes([
                            'slug' => 'calculator',
                            'order' => 0,
                        ]);
                    }
                );

                $section->add(
                    'Motor de probabilidades',
                    route('megacombo.probability-engine'),
                    function (Section $childSection) {
                        $childSection->attributes([
                            'slug' => 'probability',
                            'order' => 1,
                        ]);
                    }
                );

                $section->add(
                    'IA especialista',
                    route('megacombo.ai-specialist'),
                    function (Section $childSection) {
                        $childSection->attributes([
                            'slug' => 'ai-specialist',
                            'order' => 2,
                        ]);
                    }
                );
            }
        );

        Navigation::addWhen(
            fn () => Auth::check() && Auth::user()->isUser(),
            'Dashboard',
            route('megacombo.client-portal'),
            function (Section $section) {
                $section->attributes([
                    'group' => 'main',
                    'slug' => 'portal',
                    'order' => 0,
                ]);
            }
        );

        Navigation::addWhen(
            fn () => Auth::check() && Auth::user()->isUser(),
            'Ferramentas',
            '#',
            function (Section $section) {
                $section->attributes([
                    'group' => 'main',
                    'slug' => 'megacombo',
                    'order' => 10,
                ]);

                $section->add(
                    'Calculadora financeira',
                    route('megacombo.client-financial-calculator'),
                    function (Section $childSection) {
                        $childSection->attributes([
                            'slug' => 'calculator',
                            'order' => 0,
                        ]);
                    }
                );

                $section->add(
                    'Motor de probabilidades',
                    route('megacombo.client-probability-engine'),
                    function (Section $childSection) {
                        $childSection->attributes([
                            'slug' => 'probability',
                            'order' => 1,
                        ]);
                    }
                );

                $section->add(
                    'IA especialista',
                    route('megacombo.ai-specialist'),
                    function (Section $childSection) {
                        $childSection->attributes([
                            'slug' => 'ai-specialist',
                            'order' => 2,
                        ]);
                    }
                );
            }
        );

        Navigation::add(
            'Star us on Github',
            'https://github.com/sauce-base/saucebase',
            function (Section $section) {
                $section->attributes([
                    'group' => 'secondary',
                    'slug' => 'github',
                    'external' => true,
                    'newPage' => true,
                    'order' => 0,
                ]);
            }
        );

        Navigation::add(
            'Documentation',
            'https://sauce-base.github.io/docs/getting-started/introduction',
            function (Section $section) {
                $section->attributes([
                    'group' => 'secondary',
                    'slug' => 'documentation',
                    'external' => true,
                    'newPage' => true,
                    'order' => 0,
                ]);
            }
        );

        Navigation::addWhen(
            fn () => Auth::check() && Auth::user()->isAdmin(),
            'Admin',
            route('filament.admin.pages.dashboard'),
            function (Section $section) {
                $section->attributes([
                    'group' => 'secondary',
                    'slug' => 'admin',
                    'order' => 10,
                    'external' => true,
                    'newPage' => true,
                    'class' => 'bg-yellow-500/10 text-yellow-600 hover:bg-yellow-500/20 hover:text-yellow-400',
                ]);
            }
        );
    }
}
