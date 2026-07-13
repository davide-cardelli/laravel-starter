<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Browser tests drive a real headless browser (Playwright) against an in-process
// Laravel server, so they share the same RefreshDatabase transaction as the test.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

// Custom expectations (expect()->extend(...)) and shared test helpers go here
// when the suite actually needs them.
