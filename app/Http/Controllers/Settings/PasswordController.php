<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        assert($user !== null, 'User must be authenticated');

        $user->update([
            'password' => $validated['password'],
        ]);

        // Revoke every other session: a password change usually means the old
        // credential can no longer be trusted. Requires AuthenticateSession on
        // the web group (bootstrap/app.php) to take full effect; the current
        // session survives because its password hash is refreshed here.
        Auth::logoutOtherDevices($validated['password']);

        return back();
    }
}
