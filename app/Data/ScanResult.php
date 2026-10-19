<?php

namespace App\Data;

/** The summary LibraryScanner reports after a scan (SPEC §5.1, step 6). Paths are as stored. */
final readonly class ScanResult
{
    /**
     * @param  list<string>  $new
     * @param  list<array{from: string, to: string}>  $relocated
     * @param  list<string>  $missing
     * @param  list<string>  $recovered
     * @param  list<array{path: string, message: string}>  $errors
     */
    public function __construct(
        public string $folder,
        public int $filesInFolder,
        public array $new = [],
        public array $relocated = [],
        public array $missing = [],
        public array $recovered = [],
        public array $errors = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
