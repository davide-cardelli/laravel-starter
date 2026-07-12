<?php

declare(strict_types=1);

it('renders the custom 404 page in a real browser', function () {
    visit('/a-page-that-does-not-exist')
        ->assertSee('404')
        ->assertSee('Page not found')
        ->assertNoJavaScriptErrors();
});
