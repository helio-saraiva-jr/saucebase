<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevProfileController extends Controller
{
    public function __invoke(Request $request, string $profile): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $currentProfile = $user->hasRole(Role::ADMIN->value) ? 'representative' : 'client';

        if ($profile === 'back') {
            $profile = (string) $request->session()->get('dev.previous_profile', $currentProfile);
        }

        $role = match ($profile) {
            'representative' => Role::ADMIN,
            'client' => Role::USER,
            default => null,
        };

        abort_unless($role !== null, 404);

        if ($role->value !== ($currentProfile === 'representative' ? Role::ADMIN->value : Role::USER->value)) {
            $request->session()->put('dev.previous_profile', $currentProfile);
        }

        $user->syncRoles([$role->value]);

        return redirect()->intended(route('dashboard'));
    }
}
