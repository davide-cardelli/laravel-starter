<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => $this->inspiringQuote(),
            'auth' => [
                'user' => $request->user()?->only([
                    'id',
                    'first_name',
                    'last_name',
                    'phone',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ]),
                // Permission names let the frontend hide actions the user
                // cannot perform; server-side Policies remain the authority.
                'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Session flash messages surfaced by the Toast component.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * A random inspiring quote split into message and author.
     *
     * Quotes are formatted "message - author"; guard both halves so the
     * frontend always receives two strings, even for an unexpected format.
     *
     * @return array{message: string, author: string}
     */
    protected function inspiringQuote(): array
    {
        $raw = Inspiring::quotes()->random();
        $raw = is_string($raw) ? $raw : '';

        $parts = explode('-', $raw, 2);

        return [
            'message' => trim($parts[0]),
            'author' => trim($parts[1] ?? ''),
        ];
    }
}
