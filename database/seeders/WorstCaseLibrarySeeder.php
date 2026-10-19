<?php

namespace Database\Seeders;

use App\Data\ScanResult;
use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * Dev only (break-ui): the worst realistic library, to look at the screens with data that is not
 * kind to them. Long Windows paths, long collaborative artist names, accents and other scripts,
 * one-letter names, missing fields, a count of exactly one, and 500 songs with 120 missing.
 *
 *   DB_DATABASE=.playwright-mcp/worst.sqlite php artisan migrate:fresh --seeder=WorstCaseLibrarySeeder
 */
class WorstCaseLibrarySeeder extends Seeder
{
    private const ROOT = 'C:\Users\Aleksandra Wiśniewska-Kowalczyk\Music\DJ Sets\Drum and Bass (Neuro, Deep & Techstep)\2026';

    public function run(): void
    {
        $settings = app(Settings::class);
        $settings->setMusicFolder(self::ROOT);
        $settings->setPlaylistsFolder('C:\Users\Aleksandra Wiśniewska-Kowalczyk\Music\rekordbox\Playlists exportadas para el club (temporada 2026-2027)');

        $dnb = Genre::findOrCreateNamed('Drum and Bass');
        $long = Genre::findOrCreateNamed('Drum & Bass / Jungle (UK Hardcore Continuum, Amen Breaks y Reese)');

        $subgenres = collect(['Neurofunk', 'Deep', 'Techstep', 'Halftime', 'Minimal Deep / Halftime Footwork-influenced (140 → 174)', 'Ñu-Jungle'])
            ->map(fn (string $name): Tag => Tag::findOrCreateNamed('subgenre', $name));
        $percussion = collect(['Rolling', 'Amen-chopped Broken Halftime Shuffle with Ghost Notes', 'Stepper', 'Jo'])
            ->map(fn (string $name): Tag => Tag::findOrCreateNamed('percussion', $name));

        $named = [
            ['Ekko & Sidetrack, TWERL, Indy Skies, Mefjus, Kanine & Emperor', 'Under Bright Lights (feat. Indy Skies) [Mefjus & Kanine Remix] - Extended VIP Mix'],
            ['Jo', 'X'],
            [null, 'Untitled ID (dubplate, sin etiquetas)'],
            ['Łukasz Dvořák', 'Příliš žluťoučký kůň úpěl ďábelské ódy'],
            ['ミラーボール', '東京 Neuro 夜行'],
            ['Phibes', 'Ruido de Fondo'],
            ['Gladde Paling', 'griezelig'],
            ['Noisia & The Upbeats & Teddy Killerz & Black Sun Empire & State of Mind', 'Dead Limit (Teddy Killerz Remix) (Black Sun Empire Reconstruction) [Remastered 2026]'],
        ];

        for ($i = 0; $i < 500; $i++) {
            [$artist, $title] = $named[$i] ?? ['Kanine', "Sundown {$i}"];
            $folder = ['Neurofunk', 'Deep', 'Techstep', 'Halftime\Minimal Deep (140-174) Halftime Footwork Experiments'][$i % 4];
            $file = ($artist ? "{$artist} - " : '').$title;

            $song = Song::create([
                'path' => self::ROOT."\\{$folder}\\".sprintf('%03d. ', $i + 1).str_replace(['/', ':', '?'], '-', $file).'.aiff',
                'title' => $title,
                'artist' => $artist,
                'genre_id' => $i === 0 ? $long->id : ($i === 2 ? null : $dnb->id),
                'song_year' => $i % 7 === 0 ? null : 2000 + ($i % 27),
                'discovered_year' => 2018 + ($i % 9),
                'energy' => $i % 6 === 0 ? null : ($i % 5) + 1,
                'set_role' => $i % 5 === 0 ? null : SetRole::cases()[$i % 4],
                'bpm' => $i % 9 === 0 ? null : 170 + ($i % 7) + ($i % 3) * 0.33,
                'musical_key' => $i % 4 === 0 ? null : (($i % 12) + 1).(['A', 'B'][$i % 2]),
                'duration' => $i % 11 === 0 ? null : 180 + ($i * 37) % 600,
                'notes' => $i === 0 ? str_repeat('Entra en el minuto 1:04 con el Reese; mezclar con algo en 8A o 9A. ', 6) : null,
                'missing' => $i >= 380,
            ]);

            $song->tags()->attach($subgenres[$i % $subgenres->count()]);

            if ($i % 3 === 0) {
                $song->tags()->attach($subgenres[($i + 2) % $subgenres->count()]);
            }

            if ($i % 2 === 0) {
                $song->tags()->attach($percussion[$i % 3]);
            }
        }

        Song::query()->first()->tags()->attach($percussion[3]);

        $paths = Song::query()->orderBy('id')->pluck('path');

        $settings->setLastScan(new ScanResult(
            folder: self::ROOT,
            filesInFolder: 1284,
            new: $paths->take(500)->all(),
            relocated: $paths->take(50)->map(fn (string $path): array => [
                'from' => str_replace('.aiff', '.wav', str_replace('\Neurofunk\\', '\Sin clasificar\Descargas de Bandcamp 2025\\', $path)),
                'to' => $path,
            ])->all(),
            missing: $paths->slice(380)->values()->all(),
            recovered: $paths->take(1)->all(),
            errors: $paths->take(30)->map(fn (string $path): array => [
                'path' => str_replace('.aiff', '.mp3', $path),
                'message' => 'No se pudo leer: Invalid or unsupported tag version; error parsing MPEG header at offset 4096 (file may be truncated or not an MPEG audio file).',
            ])->all(),
        ));
    }
}
