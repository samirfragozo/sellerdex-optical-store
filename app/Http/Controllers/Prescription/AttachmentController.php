<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /** Streams the private scan of the original prescription (health data, never public). */
    public function __invoke(Prescription $prescription): StreamedResponse
    {
        Gate::authorize('view', $prescription);

        abort_unless($prescription->attachment && Storage::disk('local')->exists($prescription->attachment), 404);

        return Storage::disk('local')->response($prescription->attachment, headers: [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
