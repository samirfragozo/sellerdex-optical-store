<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'tax_id', 'address', 'phones', 'logo'])]
class BusinessSetting extends Model
{
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
