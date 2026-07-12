<?php

declare(strict_types=1);

test('registration is rate limited', function () {
    // The throttle runs before validation, so even empty attempts count. Five
    // are allowed per minute; the sixth is blocked.
    foreach (range(1, 5) as $ignored) {
        $this->post(route('register.store'), []);
    }

    $this->post(route('register.store'), [])->assertTooManyRequests();
});

test('password reset link requests are rate limited', function () {
    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.email'), ['email' => 'victim@example.com']);
    }

    $this->post(route('password.email'), ['email' => 'victim@example.com'])
        ->assertTooManyRequests();
});
