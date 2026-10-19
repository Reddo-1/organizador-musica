<?php

namespace App\Services;

use App\Data\TrackInfo;
use App\Exceptions\UnreadableAudioFile;
use App\Support\LocalPath;
use getID3;
use getid3_lib;

/**
 * Reads what an audio file says about itself (SPEC §5.2). Read-only: getID3 opens the file with
 * mode "rb" and nothing here writes tags.
 */
final class TagReader
{
    /** Camelot notation inside a comment, e.g. Mixed In Key's "8A - Energy 6". */
    public const CAMELOT_IN_COMMENT = '/\b(1[0-2]|[1-9])\s?([AB])\b/i';

    /** A whole value that is a Camelot key, as TKEY/initialkey write it ("8A", "08A", "8 A"). */
    private const CAMELOT_VALUE = '/^0?(1[0-2]|[1-9])\s?([AB])$/i';

    /** date / year / TDRC / TYER / originaldate, plus the names other formats use for them. */
    private const YEAR_TAGS = [
        'date', 'year', 'recording_time', 'creation_date', 'creationdate',
        'originaldate', 'original_year', 'originalyear', 'original_release_time',
    ];

    /** TKEY / initialkey / key. */
    private const KEY_TAGS = ['initial_key', 'initialkey', 'key'];

    /** COMM, plus Vorbis and RIFF comments. TXXX:comment is read apart (see commentTexts()). */
    private const COMMENT_TAGS = ['comment', 'comments', 'description'];

    public function read(string $localPath, ?string $scanRoot = null): TrackInfo
    {
        $info = $this->analyze($localPath);
        $tags = $this->tags($info);
        $fromName = self::parseFileName(LocalPath::stem($localPath));

        return new TrackInfo(
            title: $this->first($tags, ['title']) ?? $fromName['title'],
            artist: $this->first($tags, ['artist']) ?? $fromName['artist'],
            songYear: $this->songYear($tags),
            bpm: $this->bpm($tags),
            musicalKey: $this->musicalKey($tags, $info),
            duration: isset($info['playtime_seconds']) ? round((float) $info['playtime_seconds'], 2) : null,
            genre: $this->first($tags, ['genre']),
            discoveredYear: $this->discoveredYear($localPath),
            subfolder: $scanRoot === null ? null : self::firstSubfolder($localPath, $scanRoot),
        );
    }

    /**
     * "01 - Artista - Título" → artist "Artista", title "Título": drop a leading track number
     * and split on the first " - ". Without " - " the whole name is the title.
     *
     * @return array{artist: ?string, title: string}
     */
    public static function parseFileName(string $stem): array
    {
        $stem = trim($stem);
        $name = trim((string) preg_replace('/^\d{1,3}[\s._-]+/u', '', $stem));

        if ($name === '') {
            $name = $stem;
        }

        $parts = explode(' - ', $name, 2);

        if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
            return ['artist' => trim($parts[0]), 'title' => trim($parts[1])];
        }

        return ['artist' => null, 'title' => $name];
    }

    /** DNB\JumpUp\x.mp3 → "JumpUp"; a file directly in the scanned folder has none. */
    public static function firstSubfolder(string $localPath, string $scanRoot): ?string
    {
        $root = rtrim(str_replace('\\', '/', $scanRoot), '/').'/';
        $file = str_replace('\\', '/', $localPath);

        if (str_starts_with($file, $root)) {
            $relative = substr($file, strlen($root));
        } elseif (mb_stripos($file, $root) === 0) {
            $relative = mb_substr($file, mb_strlen($root));
        } else {
            return null;
        }

        $parts = explode('/', $relative);

        return count($parts) > 1 && $parts[0] !== '' ? $parts[0] : null;
    }

    /** @return array<string, mixed> */
    private function analyze(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw UnreadableAudioFile::notFound($path);
        }

        $getId3 = new getID3;
        $getId3->setOption([
            'encoding' => 'UTF-8',
            'option_tags_html' => false,
            'option_save_attachments' => false,
            'option_md5_data' => false,
        ]);

        $info = $getId3->analyze($path);

        // getID3 also reports recoverable problems as errors; only give up when no audio was found.
        if (empty($info['fileformat']) || (! empty($info['error']) && empty($info['audio']))) {
            throw UnreadableAudioFile::because($path, implode('; ', $info['error'] ?? ['formato desconocido']));
        }

        return $info;
    }

    /**
     * Every tag of every format, merged by getID3 and keyed in lower case (M4A writes "BPM").
     *
     * @param  array<string, mixed>  $info
     * @return array<string, list<string>>
     */
    private function tags(array $info): array
    {
        getid3_lib::CopyTagsToComments($info);

        $tags = [];

        foreach ($info['comments'] ?? [] as $name => $values) {
            if ($name === 'picture') {
                continue;
            }

            foreach ((array) $values as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $tags[strtolower((string) $name)][] = trim((string) $value);
                }
            }
        }

        return $tags;
    }

    /**
     * @param  array<string, list<string>>  $tags
     * @param  list<string>  $names
     */
    private function first(array $tags, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($tags[$name][0])) {
                return $tags[$name][0];
            }
        }

        return null;
    }

    /**
     * The first year between 1950 and next year (SPEC §5.2); nothing valid is null.
     *
     * @param  array<string, list<string>>  $tags
     */
    private function songYear(array $tags): ?int
    {
        $latest = (int) now()->year + 1;

        foreach (self::YEAR_TAGS as $name) {
            foreach ($tags[$name] ?? [] as $value) {
                preg_match_all('/\d{4}/', $value, $matches);

                foreach ($matches[0] as $year) {
                    if ((int) $year >= 1950 && (int) $year <= $latest) {
                        return (int) $year;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Only a BPM between 50 and 250 is believable (SPEC §5.2).
     *
     * @param  array<string, list<string>>  $tags
     */
    private function bpm(array $tags): ?float
    {
        foreach ($tags['bpm'] ?? [] as $value) {
            $value = str_replace(',', '.', $value);

            if (is_numeric($value) && (float) $value >= 50 && (float) $value <= 250) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    /**
     * The key tag, or else Camelot notation found in the comments (SPEC §5.2).
     *
     * @param  array<string, list<string>>  $tags
     * @param  array<string, mixed>  $info
     */
    private function musicalKey(array $tags, array $info): ?string
    {
        $value = $this->first($tags, self::KEY_TAGS);

        if ($value !== null) {
            return preg_match(self::CAMELOT_VALUE, $value, $match)
                ? $match[1].strtoupper($match[2])
                : $value;
        }

        foreach ($this->commentTexts($tags, $info) as $comment) {
            if (preg_match(self::CAMELOT_IN_COMMENT, $comment, $match)) {
                return $match[1].strtoupper($match[2]);
            }
        }

        return null;
    }

    /**
     * COMM (and other formats' comments) first, then TXXX frames described as "comment".
     *
     * @param  array<string, list<string>>  $tags
     * @param  array<string, mixed>  $info
     * @return list<string>
     */
    private function commentTexts(array $tags, array $info): array
    {
        $comments = [];

        foreach (self::COMMENT_TAGS as $name) {
            array_push($comments, ...$tags[$name] ?? []);
        }

        foreach ($info['id3v2']['comments']['text'] ?? [] as $description => $value) {
            if (is_string($description) && strcasecmp(trim($description), 'comment') === 0 && is_scalar($value)) {
                $comments[] = (string) $value;
            }
        }

        return $comments;
    }

    /** The year of the older of creation (filectime on Windows) and modification (SPEC §5.2). */
    private function discoveredYear(string $path): ?int
    {
        $times = array_filter([@filectime($path), @filemtime($path)]);

        return $times === [] ? null : (int) date('Y', min($times));
    }
}
