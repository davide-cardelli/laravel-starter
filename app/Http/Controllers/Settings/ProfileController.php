<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\User\Concerns\GuardsLastSuperAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    // Reuse the same last-super-admin invariant as the admin deletion paths:
    // self-service account deletion must not be the one hole in it.
    use GuardsLastSuperAdmin;

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null, 'User must be authenticated');

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        assert($user !== null, 'User must be authenticated');

        DB::transaction(function () use ($user): void {
            // Same guard as every other deletion path: a sole super-admin
            // deleting themselves would leave nobody able to manage the system.
            // Runs first, so a blocked attempt (403) leaves session and account
            // untouched. The row lock is held across the logout+delete below.
            $this->abortIfLastSuperAdmin($user);

            // Log out BEFORE deleting: Auth::logout() refreshes the remember
            // token via save(), which on an already-deleted model would perform
            // an INSERT and resurrect the account. Logging out while the row
            // still exists keeps it a plain UPDATE.
            Auth::logout();

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
