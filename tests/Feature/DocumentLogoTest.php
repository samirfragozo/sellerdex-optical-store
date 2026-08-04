<?php

use App\Models\User;
use App\Services\DocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('downscales a large logo before embedding it in documents', function () {
    Storage::fake('public');

    // A wide logo that would otherwise decode to a huge bitmap in dompdf.
    $image = imagecreatetruecolor(2000, 600);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    Storage::disk('public')->put('business/logo.png', $bytes);

    $user = User::factory()->admin()->create();
    $user->company->update(['logo' => 'business/logo.png']);
    $this->actingAs($user);

    $header = app(DocumentRenderer::class)->businessHeader();

    expect($header['logo'])->toStartWith('data:image/png;base64,');

    $decoded = base64_decode(str_replace('data:image/png;base64,', '', $header['logo']));
    $size = getimagesizefromstring($decoded);

    expect($size[0])->toBeLessThanOrEqual(480);
});
