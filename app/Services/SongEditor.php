<?php

namespace App\Services;

use App\Enums\SetRole;
use App\Models\Song;
use App\Models\Tag;
use App\Support\NameKey;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes to songs from the song form (Biblioteca and Clasificar share it) and the bulk actions
 * of Biblioteca (SPEC §5.4, §5.6). Only the database changes: never a file or its tags.
 */
final class SongEditor
{
    /** The fields the song form edits. */
    public const FIELDS = [
        'title', 'artist', 'genre_id', 'song_year', 'discovered_year', 'energy', 'set_role',
        'bpm', 'musical_key', 'notes',
    ];

    /**
     * The form state of a song: its fields, and per tag category the names of its tags.
     *
     * @return array<string, mixed>
     */
    public function formData(Song $song): array
    {
        $data = $song->only(self::FIELDS);
        $data['set_role'] = $song->set_role?->value;
        $data['tags'] = [];

        foreach (array_keys(config('music.tag_categories')) as $category) {
            $data['tags'][$category] = $song->tagsIn($category)->pluck('name')->all();
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data  Form state, as formData() gives it. */
    public function save(Song $song, array $data): Song
    {
        return DB::transaction(function () use ($song, $data): Song {
            $fields = Arr::only($data, self::FIELDS);

            if (array_key_exists('energy', $fields)) {
                $fields['energy'] = self::energy($fields['energy']);
            }

            if (array_key_exists('set_role', $fields)) {
                $fields['set_role'] = self::role($fields['set_role']);
            }

            foreach (['artist', 'musical_key', 'notes'] as $text) {
                if (array_key_exists($text, $fields)) {
                    $fields[$text] = filled($fields[$text]) ? trim((string) $fields[$text]) : null;
                }
            }

            $song->fill($fields)->save();

            if (isset($data['tags']) && is_array($data['tags'])) {
                $this->syncTags($song, $data['tags']);
            }

            return $song->refresh();
        });
    }

    /** @param  iterable<int>|int  $songIds */
    public function setEnergy(iterable|int $songIds, int|string|null $energy): int
    {
        return Song::query()->whereKey(self::ids($songIds))->update(['energy' => self::energy($energy)]);
    }

    /** @param  iterable<int>|int  $songIds */
    public function setRole(iterable|int $songIds, SetRole|string|null $role): int
    {
        return Song::query()->whereKey(self::ids($songIds))->update(['set_role' => self::role($role)?->value]);
    }

    /** @param  iterable<int>|int  $songIds */
    public function setGenre(iterable|int $songIds, ?int $genreId): int
    {
        return Song::query()->whereKey(self::ids($songIds))->update(['genre_id' => $genreId]);
    }

    /**
     * Adds tags of one category, by name; names that do not exist yet are created.
     *
     * @param  iterable<int>|int  $songIds
     * @param  list<string>  $names
     */
    public function addTags(iterable|int $songIds, string $category, array $names): int
    {
        if (! array_key_exists($category, config('music.tag_categories'))) {
            throw new InvalidArgumentException("La categoría {$category} no existe.");
        }

        $tagIds = collect($names)
            ->map(fn (string $name): string => NameKey::clean($name))
            ->filter()
            ->map(fn (string $name): int => Tag::findOrCreateNamed($category, $name)->id)
            ->unique()
            ->values();

        $songs = Song::query()->whereKey(self::ids($songIds))->get();

        DB::transaction(fn () => $songs->each(fn (Song $song) => $song->tags()->syncWithoutDetaching($tagIds)));

        return $songs->count();
    }

    /**
     * @param  iterable<int>|int  $songIds
     * @param  list<int>  $tagIds
     */
    public function removeTags(iterable|int $songIds, array $tagIds): int
    {
        return DB::table('song_tag')
            ->whereIn('song_id', self::ids($songIds))
            ->whereIn('tag_id', $tagIds)
            ->delete();
    }

    /**
     * Takes songs out of the app. Their files are not touched.
     *
     * @param  iterable<int>|int  $songIds
     */
    public function remove(iterable|int $songIds): int
    {
        return Song::query()->whereKey(self::ids($songIds))->delete();
    }

    /** @param  array<string, list<string>|null>  $byCategory */
    private function syncTags(Song $song, array $byCategory): void
    {
        $categories = array_keys(config('music.tag_categories'));
        $ids = [];

        foreach ($categories as $category) {
            $names = $byCategory[$category] ?? null;

            if ($names === null && ! array_key_exists($category, $byCategory)) {
                array_push($ids, ...$song->tagsIn($category)->pluck('id'));

                continue;
            }

            foreach ((array) $names as $name) {
                if (NameKey::clean((string) $name) !== '') {
                    $ids[] = Tag::findOrCreateNamed($category, (string) $name)->id;
                }
            }
        }

        // Tags of a category that is no longer in the config are kept as they are.
        array_push($ids, ...$song->tags()->whereNotIn('category', $categories)->pluck('tags.id'));

        $song->tags()->sync(array_values(array_unique($ids)));
    }

    private static function energy(int|string|null $energy): ?int
    {
        if ($energy === null || $energy === '' || (int) $energy === 0) {
            return null;
        }

        if ((int) $energy < 1 || (int) $energy > 5) {
            throw new InvalidArgumentException('La energía va de 1 a 5.');
        }

        return (int) $energy;
    }

    private static function role(SetRole|string|null $role): ?SetRole
    {
        return $role instanceof SetRole ? $role : (blank($role) ? null : SetRole::from($role));
    }

    /**
     * @param  iterable<int>|int  $ids
     * @return list<int>
     */
    private static function ids(iterable|int $ids): array
    {
        return is_int($ids) ? [$ids] : collect($ids)->map(fn ($id): int => (int) $id)->values()->all();
    }
}
