<?php

namespace App\Models;

use Database\Factories\PlaylistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'filters'])]
class Playlist extends Model
{
    /** @use HasFactory<PlaylistFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    /** @return BelongsToMany<Song, $this> In playlist order. */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)->withPivot('position')->orderByPivot('position');
    }
}
