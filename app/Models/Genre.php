<?php

namespace App\Models;

use App\Support\NameKey;
use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    /** @return HasMany<Song, $this> */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    /** Finds a genre ignoring case: "drum and bass" is "Drum and Bass" (SPEC §4). */
    public static function findNamed(string $name): ?self
    {
        $key = NameKey::of($name);

        return static::query()->get()->first(fn (self $genre): bool => NameKey::of($genre->name) === $key);
    }

    public static function findOrCreateNamed(string $name): self
    {
        return static::findNamed($name) ?? static::create(['name' => NameKey::clean($name)]);
    }
}
