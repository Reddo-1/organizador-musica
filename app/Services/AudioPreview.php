<?php

namespace App\Services;

use App\Exceptions\AudioNotPlayable;
use App\Models\Song;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * What the browser can play for a song (SPEC §5.5). MP3, WAV, FLAC, M4A and OGG go as they are;
 * anything else (AIFF) is converted once with ffmpeg to a 128 kbps MP3 kept in
 * storage/app/previews/{id}-{mtime}.mp3. The original file is only read.
 */
final class AudioPreview
{
    public const NATIVE = 'native';

    public const CONVERTED = 'converted';

    public const NEEDS_FFMPEG = 'needs-ffmpeg';

    public const MISSING = 'missing';

    public function status(Song $song): string
    {
        if (! is_file($song->localPath())) {
            return self::MISSING;
        }

        if ($this->nativeMime($song) !== null) {
            return self::NATIVE;
        }

        return $this->hasFfmpeg() ? self::CONVERTED : self::NEEDS_FFMPEG;
    }

    public function hasFfmpeg(): bool
    {
        $ffmpeg = config('music.ffmpeg');

        return filled($ffmpeg) && is_file($ffmpeg);
    }

    /**
     * The file to send and its type. $forceConversion is for files whose container the browser
     * knows but whose codec it does not (an ALAC .m4a in Chrome).
     *
     * @return array{path: string, mime: string}
     */
    public function playable(Song $song, bool $forceConversion = false): array
    {
        $source = $song->localPath();

        if (! is_file($source)) {
            throw AudioNotPlayable::missing($song);
        }

        $mime = $this->nativeMime($song);

        if ($mime !== null && ! $forceConversion) {
            return ['path' => $source, 'mime' => $mime];
        }

        if (! $this->hasFfmpeg()) {
            throw AudioNotPlayable::needsFfmpeg($song);
        }

        return ['path' => $this->preview($song, $source), 'mime' => 'audio/mpeg'];
    }

    private function preview(Song $song, string $source): string
    {
        $directory = storage_path('app/previews');
        $target = $directory.DIRECTORY_SEPARATOR."{$song->id}-".filemtime($source).'.mp3';

        if (is_file($target)) {
            return $target;
        }

        File::ensureDirectoryExists($directory);

        // A preview of an older version of the file is no longer needed.
        foreach (File::glob($directory.DIRECTORY_SEPARATOR."{$song->id}-*.mp3") as $old) {
            File::delete($old);
        }

        $temporary = $target.'.'.getmypid().'.part';

        @set_time_limit(0);

        $result = Process::timeout(600)->run([
            config('music.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $source, '-vn', '-map_metadata', '-1', '-codec:a', 'libmp3lame', '-b:a', '128k',
            '-f', 'mp3', $temporary,
        ]);

        if (! $result->successful() || ! is_file($temporary)) {
            File::delete($temporary);

            throw AudioNotPlayable::conversionFailed($song, trim($result->errorOutput()));
        }

        File::move($temporary, $target);

        return $target;
    }

    private function nativeMime(Song $song): ?string
    {
        return config('music.browser_playable')[$song->extension()] ?? null;
    }
}
