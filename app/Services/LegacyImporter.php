<?php

namespace App\Services;

use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Support\LocalPath;
use App\Support\NameKey;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Brings in the library of the Python version (SPEC §5.11) from its musica.db, opened read-only
 * through the "legacy" connection. Running it again updates the same songs instead of
 * duplicating them. Presets are not imported.
 */
final class LegacyImporter
{
    private const ROLES = ['abrir' => SetRole::Open, 'cuerpo' => SetRole::Body, 'peso' => SetRole::Weight, 'respiro' => SetRole::Breather];

    private const CATEGORIES = ['subgenero' => 'subgenre', 'percusion' => 'percussion'];

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{created: int, updated: int, genres: int, tags: int, links: int, settings: list<string>, unknownCategories: list<string>}
     */
    public function import(string $file): array
    {
        $legacy = $this->open($file);
        $tables = collect($legacy->select("select name from sqlite_master where type = 'table'"))->pluck('name')->all();

        if (! in_array('canciones', $tables, true)) {
            throw new RuntimeException("{$file} no parece la base de datos de la versión Python: no tiene la tabla «canciones».");
        }

        return DB::transaction(function () use ($legacy, $tables): array {
            $genres = $this->genres($legacy, $tables);
            [$tags, $unknownCategories] = $this->tags($legacy, $tables);
            [$songs, $created, $updated] = $this->songs($legacy, $genres);
            $links = $this->links($legacy, $tables, $songs, $tags);
            $settings = $this->settings($legacy, $tables);

            return [
                'created' => $created,
                'updated' => $updated,
                'genres' => count(array_unique($genres)),
                'tags' => count(array_unique($tags)),
                'links' => $links,
                'settings' => $settings,
                'unknownCategories' => $unknownCategories,
            ];
        });
    }

    private function open(string $file): Connection
    {
        $local = LocalPath::toLocal(trim($file));

        if (! is_file($local)) {
            throw new RuntimeException("No se encuentra {$file}.");
        }

        config(['database.connections.legacy.database' => $local]);
        DB::purge('legacy');

        return DB::connection('legacy');
    }

    /**
     * @param  list<string>  $tables
     * @return array<int, int> legacy genre id => genre id
     */
    private function genres(Connection $legacy, array $tables): array
    {
        if (! in_array('generos', $tables, true)) {
            return [];
        }

        return collect($legacy->table('generos')->get())
            ->filter(fn (object $row): bool => filled($row->nombre ?? null))
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => Genre::findOrCreateNamed($row->nombre)->id])
            ->all();
    }

    /**
     * @param  list<string>  $tables
     * @return array{0: array<int, int>, 1: list<string>} legacy tag id => tag id, and the categories this app does not know
     */
    private function tags(Connection $legacy, array $tables): array
    {
        if (! in_array('etiquetas', $tables, true)) {
            return [[], []];
        }

        $map = [];
        $unknown = [];

        foreach ($legacy->table('etiquetas')->get() as $row) {
            if (blank($row->nombre ?? null) || blank($row->categoria ?? null)) {
                continue;
            }

            $slug = Str::of($row->categoria)->ascii()->lower()->trim()->value();
            $category = self::CATEGORIES[$slug] ?? $slug;

            if (! array_key_exists($category, config('music.tag_categories'))) {
                $unknown[] = $row->categoria;
            }

            $map[(int) $row->id] = Tag::findOrCreateNamed($category, $row->nombre)->id;
        }

        return [$map, array_values(array_unique($unknown))];
    }

    /**
     * @param  array<int, int>  $genres
     * @return array{0: array<int, int>, 1: int, 2: int} legacy song id => song id, created, updated
     */
    private function songs(Connection $legacy, array $genres): array
    {
        $existing = Song::query()->pluck('id', 'path_key')->all();
        $map = [];
        $created = 0;
        $updated = 0;

        foreach ($legacy->table('canciones')->orderBy('id')->cursor() as $row) {
            $path = trim((string) ($row->ruta ?? ''));

            if ($path === '') {
                continue;
            }

            $title = filled($row->titulo ?? null) ? trim($row->titulo) : TagReader::parseFileName(LocalPath::stem($path))['title'];
            $fields = [
                'path' => $path,
                'title' => $title,
                'artist' => filled($row->artista ?? null) ? trim($row->artista) : null,
                'genre_id' => isset($row->genero_id) ? ($genres[(int) $row->genero_id] ?? null) : null,
                'song_year' => self::int($row->anio_cancion ?? null),
                'discovered_year' => self::int($row->anio_descubrimiento ?? null),
                'energy' => self::energy($row->energia ?? null),
                'set_role' => self::role($row->funcion ?? null),
                'bpm' => is_numeric($row->bpm ?? null) ? round((float) $row->bpm, 2) : null,
                'musical_key' => filled($row->tonalidad ?? null) ? trim($row->tonalidad) : null,
                'duration' => is_numeric($row->duracion ?? null) ? round((float) $row->duracion, 2) : null,
                'notes' => filled($row->notas ?? null) ? $row->notas : null,
                'missing' => (bool) ($row->falta ?? false),
            ];

            $key = LocalPath::key($path);

            if (isset($existing[$key])) {
                $song = Song::query()->findOrFail($existing[$key]);
                $song->update($fields);
                $updated++;
            } else {
                $song = Song::create($fields);
                $existing[$key] = $song->id;
                $created++;
            }

            $map[(int) $row->id] = $song->id;
        }

        return [$map, $created, $updated];
    }

    /**
     * @param  list<string>  $tables
     * @param  array<int, int>  $songs
     * @param  array<int, int>  $tags
     */
    private function links(Connection $legacy, array $tables, array $songs, array $tags): int
    {
        if (! in_array('cancion_etiqueta', $tables, true)) {
            return 0;
        }

        $bySong = [];

        foreach ($legacy->table('cancion_etiqueta')->get() as $row) {
            $song = $songs[(int) ($row->cancion_id ?? 0)] ?? null;
            $tag = $tags[(int) ($row->etiqueta_id ?? 0)] ?? null;

            if ($song && $tag) {
                $bySong[$song][] = $tag;
            }
        }

        $links = 0;

        foreach ($bySong as $songId => $tagIds) {
            Song::query()->find($songId)?->tags()->syncWithoutDetaching(array_unique($tagIds));
            $links += count(array_unique($tagIds));
        }

        return $links;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string> The settings brought in.
     */
    private function settings(Connection $legacy, array $tables): array
    {
        if (! in_array('ajustes', $tables, true)) {
            return [];
        }

        $columns = collect($legacy->select('pragma table_info(ajustes)'))->pluck('name')->all();
        [$keyColumn, $valueColumn] = match (true) {
            in_array('clave', $columns, true) && in_array('valor', $columns, true) => ['clave', 'valor'],
            in_array('key', $columns, true) && in_array('value', $columns, true) => ['key', 'value'],
            default => [$columns[0] ?? 'clave', $columns[1] ?? 'valor'],
        };

        $values = collect($legacy->table('ajustes')->get())->mapWithKeys(fn (object $row): array => [
            NameKey::of((string) $row->{$keyColumn}) => $row->{$valueColumn},
        ]);
        $imported = [];

        if (filled($values['carpeta_musica'] ?? null)) {
            $this->settings->setMusicFolder($values['carpeta_musica']);
            $imported[] = 'carpeta de música';
        }

        if (filled($values['carpeta_playlists'] ?? null)) {
            $this->settings->setPlaylistsFolder($values['carpeta_playlists']);
            $imported[] = 'carpeta de playlists';
        }

        if ($values->has('genero_defecto')) {
            $this->settings->setDefaultGenre((string) $values['genero_defecto']);
            $imported[] = 'género por defecto';
        }

        if ($values->has('subcarpeta_subgenero')) {
            $this->settings->setSubfolderAsSubgenre(filter_var($values['subcarpeta_subgenero'], FILTER_VALIDATE_BOOL)
                || in_array(NameKey::of((string) $values['subcarpeta_subgenero']), ['si', 'sí'], true));
            $imported[] = 'subcarpeta como subgénero';
        }

        return $imported;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function energy(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 1 && (int) $value <= 5 ? (int) $value : null;
    }

    private static function role(mixed $value): ?SetRole
    {
        if (blank($value)) {
            return null;
        }

        $slug = Str::of((string) $value)->ascii()->lower()->trim()->value();

        return self::ROLES[$slug] ?? SetRole::tryFrom($slug);
    }
}
