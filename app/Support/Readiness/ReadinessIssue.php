<?php

namespace App\Support\Readiness;

use App\Enums\ReadinessSeverity;

/** One thing that stops (blocking) or weakens (warning) a company's ability to sell. */
final readonly class ReadinessIssue
{
    /** @param  'lens'|null  $scope  null = affects every sale; 'lens' = only lens armados */
    public function __construct(
        public string $key,
        public ReadinessSeverity $severity,
        public string $message,
        public string $url,
        public ?string $scope = null,
    ) {}

    /** @return array{key: string, severity: string, message: string, url: string, scope: string|null} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'url' => $this->url,
            'scope' => $this->scope,
        ];
    }
}
