<?php

namespace App\Models;

use App\Support\NameKey;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A multi-valued label of a category from config('music.tag_categories'): subgenre, percussion...
 */
#[Fillable(['category', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /** The category the scanner fills from the first-level subfolder (SPEC §5.2). */
    public const SUBGENRE = 'subgenre';

    /** @return BelongsToMany<Song, $this> */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class);
    }

    #[Scope]
    protected function inCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    public function categoryLabel(): string
    {
        return config("music.tag_categories.{$this->category}", $this->category);
    }

    /** Finds a tag of a category ignoring case: "halftime" is "Halftime" (SPEC §4). */
    public static function findNamed(string $category, string $name): ?self
    {
        $key = NameKey::of($name);

        return static::query()
            ->where('category', $category)
            ->get()
            ->first(fn (self $tag): bool => NameKey::of($tag->name) === $key);
    }

    public static function findOrCreateNamed(string $category, string $name): self
    {
        return static::findNamed($category, $name)
            ?? static::create(['category' => $category, 'name' => NameKey::clean($name)]);
    }
}
