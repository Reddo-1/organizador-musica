<?php

namespace App\Models;

use Database\Factories\FilterPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A named state of the Generar playlist filters (SPEC §5.7). */
#[Fillable(['name', 'filters'])]
class FilterPreset extends Model
{
    /** @use HasFactory<FilterPresetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }
}
