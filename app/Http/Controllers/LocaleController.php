<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Kept in sync with the locales configured for the Filament language
     * switch in AppServiceProvider so both switchers share one preference.
     *
     * @var list<string>
     */
    public const SUPPORTED = ['es', 'en'];

    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);

        cookie()->queue(cookie()->forever('filament_language_switch_locale', $locale));

        return back();
    }
}
