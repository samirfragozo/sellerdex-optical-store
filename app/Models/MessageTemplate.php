<?php

namespace App\Models;

use App\Enums\MessageTemplateKey;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A WhatsApp message a company reworded; keys it never edited use the translated default. */
#[Fillable(['company_id', 'key', 'body'])]
class MessageTemplate extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['key' => MessageTemplateKey::class];
    }

    public static function bodyFor(int $companyId, MessageTemplateKey $key): string
    {
        $body = self::withoutGlobalScopes()->where('company_id', $companyId)->where('key', $key->value)->value('body');

        return filled($body) ? $body : $key->defaultBody();
    }

    /** @param  array{cliente?: string, orden?: string, saldo?: int}  $vars */
    public static function render(int $companyId, MessageTemplateKey $key, array $vars): string
    {
        return strtr(self::bodyFor($companyId, $key), [
            '{cliente}' => $vars['cliente'] ?? '',
            '{orden}' => $vars['orden'] ?? '',
            '{saldo}' => isset($vars['saldo']) ? '$'.number_format($vars['saldo'], 0, ',', '.') : '',
            '{optica}' => (string) Company::withoutGlobalScopes()->whereKey($companyId)->value('name'),
        ]);
    }
}
