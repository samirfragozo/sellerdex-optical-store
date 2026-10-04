<?php

namespace App\Filament\Resources\BusinessSettings\Pages;

use App\Enums\MessageTemplateKey;
use App\Filament\Pages\Onboarding\Steps\InvoicingStep;
use App\Filament\Resources\BusinessSettings\BusinessSettingResource;
use App\Models\Company;
use App\Models\MessageTemplate;
use Filament\Resources\Pages\EditRecord;

class ManageBusinessSetting extends EditRecord
{
    protected static string $resource = BusinessSettingResource::class;

    /** @var array{receipt_prefix: ?string}|null */
    private ?array $receiptPrefixState = null;

    /** @var array<string, ?string>|null */
    private ?array $templatesState = null;

    /** Always edit the current company's row, regardless of route params. */
    public function mount(int|string|null $record = null): void
    {
        parent::mount(Company::current()->getKey());
    }

    /** The receipt prefix lives on the receipt numbering range, not on the company. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['receipt_prefix'] = InvoicingStep::receiptRange($this->getRecord())?->prefix;
        $data['templates'] = collect(MessageTemplateKey::cases())
            ->mapWithKeys(fn (MessageTemplateKey $key) => [$key->value => MessageTemplate::bodyFor($this->getRecord()->id, $key)])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hidden in external-manual mode, so the key may be absent: then the range is left untouched.
        $this->receiptPrefixState = array_key_exists('receipt_prefix', $data) ? ['receipt_prefix' => $data['receipt_prefix']] : null;
        unset($data['receipt_prefix']);
        $this->templatesState = $data['templates'] ?? null;
        unset($data['templates']);

        return $data;
    }

    protected function afterSave(): void
    {
        InvoicingStep::persist($this->getRecord(), $this->receiptPrefixState ?? []);

        // The form didn't send templates: never delete what is stored.
        if ($this->templatesState === null) {
            return;
        }

        // Only real edits are stored, so untouched messages keep following the translated default.
        foreach (MessageTemplateKey::cases() as $key) {
            $body = trim((string) ($this->templatesState[$key->value] ?? ''));
            $row = MessageTemplate::withoutGlobalScopes()->where('company_id', $this->getRecord()->id)->where('key', $key->value);

            if ($body === '' || $body === $key->defaultBody()) {
                $row->delete();
            } else {
                MessageTemplate::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $this->getRecord()->id, 'key' => $key->value],
                    ['body' => $body],
                );
            }
        }
    }

    public function getBreadcrumb(): string
    {
        return __('app.business.title');
    }

    public function getTitle(): string
    {
        return __('app.business.title');
    }

    /** No delete for the singleton. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
