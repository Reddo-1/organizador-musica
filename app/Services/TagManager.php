<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Support\NameKey;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tags and genres (SPEC §5.9). Renaming to a name that already exists in the same category
 * merges the two: the songs move to the existing one and the old one is deleted. Changing only
 * the case ("halftime" → "Halftime") is a rename.
 */
final class TagManager
{
    public function __construct(private readonly FilterReferences $references) {}

    public function createTag(string $category, string $name): Tag
    {
        if (! array_key_exists($category, config('music.tag_categories'))) {
            throw new InvalidArgumentException("La categoría {$category} no existe.");
        }

        return Tag::findOrCreateNamed($category, self::name($name));
    }

    /** Returns the tag that carries the name afterwards: the same one, or the one it merged into. */
    public function renameTag(Tag $tag, string $name): Tag
    {
        $name = self::name($name);
        $existing = Tag::findNamed($tag->category, $name);

        if ($existing === null || $existing->is($tag)) {
            $tag->update(['name' => $name]);

            return $tag;
        }

        DB::transaction(function () use ($tag, $existing): void {
            $existing->songs()->syncWithoutDetaching($tag->songs()->pluck('songs.id'));
            $this->references->replaceTag($tag->id, $existing->id);
            $tag->delete();
        });

        return $existing;
    }

    public function deleteTag(Tag $tag): void
    {
        DB::transaction(function () use ($tag): void {
            $this->references->forgetTag($tag->id);
            $tag->delete();
        });
    }

    public function createGenre(string $name): Genre
    {
        return Genre::findOrCreateNamed(self::name($name));
    }

    public function renameGenre(Genre $genre, string $name): Genre
    {
        $name = self::name($name);
        $existing = Genre::findNamed($name);

        if ($existing === null || $existing->is($genre)) {
            $genre->update(['name' => $name]);

            return $genre;
        }

        DB::transaction(function () use ($genre, $existing): void {
            Song::query()->where('genre_id', $genre->id)->update(['genre_id' => $existing->id]);
            $this->references->replaceGenre($genre->id, $existing->id);
            $genre->delete();
        });

        return $existing;
    }

    /** Its songs keep everything else and are left without a genre. */
    public function deleteGenre(Genre $genre): void
    {
        DB::transaction(function () use ($genre): void {
            $this->references->forgetGenre($genre->id);
            $genre->delete();
        });
    }

    private static function name(string $name): string
    {
        $name = NameKey::clean($name);

        if ($name === '') {
            throw new InvalidArgumentException('El nombre no puede estar vacío.');
        }

        return $name;
    }
}
