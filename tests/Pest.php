<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => test()->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        test()->seed(RolesAndPermissionsSeeder::class);
        assertFrontendBuildIsFresh();
    })
    ->in('Browser');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Actions');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Support\Facades\File;

function openCashRegisterSession(User $user, int $openingCash = 0): CashRegisterSession
{
    return CashRegisterSession::factory()->for($user)->create([
        'opening_cash' => $openingCash,
        'company_id' => $user->company_id,
    ]);
}

// Browser tests load the compiled frontend in a real browser. A stale build
// (source changed since the last `npm run build`) doesn't produce a clean
// test failure — it hangs the embedded server indefinitely, since the page
// never finishes loading and nothing here has a bounded timeout. Fail fast
// with a clear message instead.
function assertFrontendBuildIsFresh(): void
{
    $manifest = public_path('build/manifest.json');

    if (! file_exists($manifest)) {
        test()->fail('Frontend assets are not built — run `npm run build` before running the Browser suite.');
    }

    $manifestTime = filemtime($manifest);
    $staleFile = collect(File::allFiles(resource_path('js')))
        ->first(fn ($file) => $file->getMTime() > $manifestTime);

    if ($staleFile) {
        test()->fail("Frontend assets are stale ({$staleFile->getRelativePathname()} changed after the last build) — run `npm run build` before running the Browser suite.");
    }
}
