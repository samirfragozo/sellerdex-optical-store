<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

test('always forces https urls', function () {
    URL::forceRootUrl('http://example.com');

    (new AppServiceProvider(app()))->boot();

    expect(url('/'))->toBe('https://example.com');
});
