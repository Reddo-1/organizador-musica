<?php

namespace App\Services;

use App\Models\Song;
use App\Support\LocalPath;
use RuntimeException;

/**
 * Writes playlists for rekordbox (SPEC §5.8): UTF-8 without BOM, CRLF line endings and each
 * song's absolute path exactly as stored, which is how rekordbox on Windows finds the files.
 */
final class M3u8Exporter
{
    private const EOL = "\r\n";

    public function __construct(private readonly Settings $settings) {}

    /** @param  iterable<Song>  $songs  In playlist order. */
    public function render(iterable $songs): string
    {
        $lines = ['#EXTM3U'];

        foreach ($songs as $song) {
            $seconds = $song->duration === null ? -1 : (int) round($song->duration);
            $label = (string) preg_replace('/[\r\n]+/', ' ', $song->displayName());

            $lines[] = "#EXTINF:{$seconds},{$label}";
            $lines[] = $song->path;
        }

        return implode(self::EOL, $lines).self::EOL;
    }

    /** "Set <1>: Neuro/Deep?" → "Set 1 NeuroDeep.m3u8": drops <>:"/\|?* and control characters. */
    public static function fileName(string $playlistName): string
    {
        $name = (string) preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]/u', '', $playlistName);
        // Windows does not allow a name that ends in a dot or a space.
        $name = rtrim(trim($name), '. ');

        return ($name === '' ? 'playlist' : $name).'.m3u8';
    }

    /**
     * Saves the playlist in the playlists folder from Ajustes (or the one given) and returns the
     * final path, written the way the user would type it.
     *
     * @param  iterable<Song>  $songs
     */
    public function save(string $playlistName, iterable $songs, ?string $folder = null): string
    {
        $folder ??= $this->settings->playlistsFolder();
        $directory = rtrim(LocalPath::toLocal($folder), '\\/');

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear la carpeta de playlists {$folder}.");
        }

        $file = $directory.DIRECTORY_SEPARATOR.self::fileName($playlistName);

        if (@file_put_contents($file, $this->render($songs)) === false) {
            throw new RuntimeException('No se pudo guardar la playlist en '.LocalPath::toStored($file).'.');
        }

        return LocalPath::toStored($file);
    }
}
