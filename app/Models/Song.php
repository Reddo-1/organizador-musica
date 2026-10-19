<?php

namespace App\Models;

use App\Enums\SetRole;
use App\Support\LocalPath;
use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A track in the library. `path` is the file as Windows names it; the file itself is only ever
 * read (SPEC §1).
 */
#[Fillable([
    'path', 'title', 'artist', 'genre_id', 'song_year', 'discovered_year', 'energy', 'set_role',
    'bpm', 'musical_key', 'duration', 'notes', 'missing',
])]
class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'song_year' => 'integer',
            'discovered_year' => 'integer',
            'energy' => 'integer',
            'set_role' => SetRole::class,
            'bpm' => 'float',
            'duration' => 'float',
            'missing' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // path_key always follows path (SPEC §4).
        static::saving(function (Song $song): void {
            $song->path_key = LocalPath::key($song->path);
        });
    }

    /** @return BelongsTo<Genre, $this> */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return BelongsToMany<Playlist, $this> */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)->withPivot('position');
    }

    /** @return Collection<int, Tag> */
    public function tagsIn(string $category): Collection
    {
        return $this->tags
            ->where('category', $category)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function fileName(): string
    {
        return LocalPath::fileName($this->path);
    }

    public function extension(): string
    {
        return LocalPath::extension($this->path);
    }

    /** Where PHP can open the file on this machine. */
    public function localPath(): string
    {
        return LocalPath::toLocal($this->path);
    }

    /** "Artist - Title", as the .m3u8 and rekordbox show it. */
    public function displayName(): string
    {
        return filled($this->artist) ? "{$this->artist} - {$this->title}" : (string) $this->title;
    }

    #[Scope]
    protected function present(Builder $query): void
    {
        $query->where('missing', false);
    }

    #[Scope]
    protected function missing(Builder $query): void
    {
        $query->where('missing', true);
    }
}
