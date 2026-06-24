<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Prescription;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentRenderer
{
    /**
     * Business header data shared by every document, with the logo inlined as a
     * base64 data URI so it renders identically in the browser and in dompdf.
     *
     * @return array{settings: BusinessSetting, logo: string|null}
     */
    public function businessHeader(): array
    {
        $settings = BusinessSetting::current();
        $logo = null;

        if ($settings->logo && Storage::disk('public')->exists($settings->logo)) {
            $contents = Storage::disk('public')->get($settings->logo);
            $mime = Storage::disk('public')->mimeType($settings->logo) ?: 'image/png';
            $logo = 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return ['settings' => $settings, 'logo' => $logo];
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
