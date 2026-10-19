<?php

namespace App\Services;

use App\Data\ScanResult;
use App\Exceptions\MusicFolderNotFound;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Support\LocalPath;
use App\Support\NameKey;
use FilesystemIterator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Brings the library in line with the music folder (SPEC §5.1). Only reads the folder: it never
 * renames, moves or deletes a file.
 */
final class LibraryScanner
{
    public function __construct(
        private readonly TagReader $reader,
        private readonly Settings $settings,
    ) {}

    /** @param  string|null  $folder  The folder as the user writes it; the one in Ajustes when null. */
    public function scan(?string $folder = null): ScanResult
    {
        $folder = Settings::folder($folder ?? $this->settings->musicFolder());
        $root = LocalPath::toLocal($folder);

        if (! is_dir($root)) {
            throw MusicFolderNotFound::at($folder);
        }

        $files = $this->audioFiles($root);

        return DB::transaction(fn (): ScanResult => $this->reconcile($folder, $root, $files));
    }

    /** @param  array<string, array{key: string, stored: string, local: string}>  $files */
    private function reconcile(string $folder, string $root, array $files): ScanResult
    {
        /** @var Collection<string, Song> $songs */
        $songs = Song::query()->get(['id', 'path', 'path_key', 'missing'])->keyBy('path_key');

        $newFiles = array_diff_key($files, $songs->all());
        $missing = new Collection;
        $recovered = new Collection;

        foreach ($songs as $key => $song) {
            // A song outside the scanned folder still counts as present while its file exists.
            $exists = isset($files[$key]) || file_exists($song->localPath());

            if (! $exists) {
                $missing->push($song);
            } elseif ($song->missing) {
                $recovered->push($song);
            }
        }

        $relocated = $this->relocate($missing, $newFiles);

        Song::query()->whereKey($missing->pluck('id'))->where('missing', false)->update(['missing' => true]);
        Song::query()->whereKey($recovered->pluck('id'))->update(['missing' => false]);

        [$created, $errors] = $this->create($newFiles, $root);

        return new ScanResult(
            folder: $folder,
            filesInFolder: count($files),
            new: $created,
            relocated: $relocated,
            missing: $missing->pluck('path')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            recovered: $recovered->pluck('path')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            errors: $errors,
        );
    }

    /**
     * Step 3: a missing song and exactly one new file with the same name without extension, when
     * no other missing song shares that name (a WAV converted to AIFF, a file moved to another
     * subfolder). The song keeps all its classification. Takes the pair out of both lists.
     *
     * @param  Collection<int, Song>  $missing
     * @param  array<string, array{key: string, stored: string, local: string}>  $newFiles
     * @return list<array{from: string, to: string}>
     */
    private function relocate(Collection &$missing, array &$newFiles): array
    {
        $stemKey = fn (string $path): string => NameKey::of(LocalPath::stem($path));
        $filesByStem = collect($newFiles)->groupBy(fn (array $file): string => $stemKey($file['stored']));
        $relocated = [];

        foreach ($missing->groupBy(fn (Song $song): string => $stemKey($song->path)) as $stem => $songs) {
            $candidates = $filesByStem->get($stem);

            if ($songs->count() !== 1 || $candidates === null || $candidates->count() !== 1) {
                continue;
            }

            $song = $songs->first();
            $file = $candidates->first();

            $relocated[] = ['from' => $song->path, 'to' => $file['stored']];
            $song->update(['path' => $file['stored'], 'missing' => false]);

            unset($newFiles[$file['key']]);
            $missing = $missing->reject(fn (Song $other): bool => $other->is($song))->values();
        }

        return $relocated;
    }

    /**
     * Step 5: new files are added with what TagReader finds. A file that cannot be read is noted
     * and the scan carries on.
     *
     * @param  array<string, array{key: string, stored: string, local: string}>  $newFiles
     * @return array{0: list<string>, 1: list<array{path: string, message: string}>}
     */
    private function create(array $newFiles, string $root): array
    {
        $defaultGenre = $this->settings->defaultGenre();
        $subfolderAsSubgenre = $this->settings->subfolderAsSubgenre();
        $created = [];
        $errors = [];

        foreach ($newFiles as $file) {
            try {
                $track = $this->reader->read($file['local'], $root);
                $genre = $defaultGenre ?? $track->genre;

                $song = Song::create([
                    'path' => $file['stored'],
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'genre_id' => filled($genre) ? Genre::findOrCreateNamed($genre)->id : null,
                    'song_year' => $track->songYear,
                    'discovered_year' => $track->discoveredYear,
                    'bpm' => $track->bpm,
                    'musical_key' => $track->musicalKey,
                    'duration' => $track->duration,
                ]);

                if ($subfolderAsSubgenre && filled($track->subfolder)) {
                    $song->tags()->attach(Tag::findOrCreateNamed(Tag::SUBGENRE, $track->subfolder));
                }

                $created[] = $file['stored'];
            } catch (Throwable $exception) {
                $errors[] = ['path' => $file['stored'], 'message' => $exception->getMessage()];
            }
        }

        return [$created, $errors];
    }

    /**
     * Step 1: every file under the folder with one of the configured extensions, skipping macOS
     * "._" companions.
     *
     * @return array<string, array{key: string, stored: string, local: string}> by path_key
     */
    private function audioFiles(string $root): array
    {
        $extensions = array_map('strtolower', config('music.extensions'));
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()
                || str_starts_with($file->getFilename(), '._')
                || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $stored = LocalPath::toStored($file->getPathname());
            $key = LocalPath::key($stored);
            $files[$key] = ['key' => $key, 'stored' => $stored, 'local' => $file->getPathname()];
        }

        ksort($files, SORT_NATURAL);

        return $files;
    }
}
