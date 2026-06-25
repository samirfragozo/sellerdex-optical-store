<?php

namespace App\Filament\Concerns;

trait RedirectsToResourceIndex
{
    /**
     * After creating or saving, return to the resource's table.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
