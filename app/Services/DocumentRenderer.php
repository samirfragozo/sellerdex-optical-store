<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Prescription;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DocumentRenderer
{
    /** Logos are downscaled to this width before embedding so dompdf never decodes a huge bitmap. */
    private const LOGO_MAX_WIDTH = 480;

    /**
     * Business header data shared by every document, with the logo inlined as a
     * base64 data URI so it renders identically in the browser and in dompdf.
     *
     * @return array{settings: BusinessSetting, logo: string|null}
     */
    public function businessHeader(): array
    {
        $settings = BusinessSetting::current();

        return ['settings' => $settings, 'logo' => $this->logoDataUri($settings)];
    }

    /**
     * Build a small base64 data URI for the logo. A full-resolution logo would
     * make dompdf decode the entire bitmap into memory (e.g. a 7407×2020 PNG is
     * ~57 MB), so we downscale it first and cache the result.
     */
    private function logoDataUri(BusinessSetting $settings): ?string
    {
        if (! $settings->logo || ! Storage::disk('public')->exists($settings->logo)) {
            return null;
        }

        $key = 'document.logo.'.md5($settings->logo).'.'.Storage::disk('public')->lastModified($settings->logo);

        return Cache::rememberForever($key, function () use ($settings): string {
            $contents = Storage::disk('public')->get($settings->logo);
            $resized = $this->downscale($contents, self::LOGO_MAX_WIDTH);

            $mime = $resized !== null ? 'image/png' : (Storage::disk('public')->mimeType($settings->logo) ?: 'image/png');

            return 'data:'.$mime.';base64,'.base64_encode($resized ?? $contents);
        });
    }

    /**
     * Downscale raw image bytes to a maximum width, returning PNG bytes, or null
     * when GD is unavailable or the image is already small enough.
     */
    private function downscale(string $contents, int $maxWidth): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        if (imagesx($image) <= $maxWidth) {
            return null;
        }

        $height = (int) round(imagesy($image) * $maxWidth / imagesx($image));
        $resized = imagescale($image, $maxWidth, max(1, $height));

        if ($resized === false) {
            return null;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        ob_start();
        imagepng($resized);
        $output = ob_get_clean();

        return $output !== '' && $output !== false ? $output : null;
    }

    public function invoice(Sale $sale): View
    {
        return view('documents.invoice', $this->invoiceData($sale));
    }

    public function invoicePdf(Sale $sale): Response
    {
        return Pdf::loadView('documents.invoice', $this->invoiceData($sale))
            ->setPaper('a5')
            ->download('factura-'.$sale->number.'.pdf');
    }

    /** @return array<string, mixed> */
    private function invoiceData(Sale $sale): array
    {
        $sale->loadMissing(['customer', 'seller', 'items.product', 'payments']);

        return [
            ...$this->businessHeader(),
            'sale' => $sale,
        ];
    }

    public function formula(Prescription $prescription): View
    {
        return view('documents.formula', $this->formulaData($prescription));
    }

    public function formulaPdf(Prescription $prescription): Response
    {
        return Pdf::loadView('documents.formula', $this->formulaData($prescription))
            ->setPaper('a5')
            ->download('formula-'.$prescription->id.'.pdf');
    }

    /** @return array<string, mixed> */
    private function formulaData(Prescription $prescription): array
    {
        $prescription->loadMissing('customer');

        return [
            ...$this->businessHeader(),
            'rx' => $prescription,
        ];
    }
}
