<?php

namespace App\Data;

/** What TagReader found in one audio file (SPEC §5.2). */
final readonly class TrackInfo
{
    public function __construct(
        public string $title,
        public ?string $artist,
        public ?int $songYear,
        public ?float $bpm,
        public ?string $musicalKey,
        public ?float $duration,
        public ?string $genre,
        public ?int $discoveredYear,
        public ?string $subfolder,
    ) {}
}
