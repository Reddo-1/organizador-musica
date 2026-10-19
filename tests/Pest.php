<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->afterEach(fn () => removeTemporaryFolders())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->afterEach(fn () => removeTemporaryFolders())
    ->in('Unit');

/** A 1-second audio file generated with ffmpeg (see tests/Fixtures/audio/README.md). */
function audio(string $name): string
{
    return fixture("audio/{$name}");
}

/** An empty folder that plays the music folder; it is deleted after the test. */
function temporaryFolder(): string
{
    $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'organizador-tests'.DIRECTORY_SEPARATOR.Str::random(10);
    File::ensureDirectoryExists($folder);
    $GLOBALS['temporaryFolders'][] = $folder;

    return $folder;
}

/** Copies a fixture into the folder as $relative ("JumpUp/Kanine - Sundown.mp3") and returns its path. */
function putTrack(string $folder, string $relative, string $fixture = 'tagged-v24.mp3'): string
{
    $path = $folder.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    File::ensureDirectoryExists(dirname($path));
    File::copy(audio($fixture), $path);

    return $path;
}

function removeTemporaryFolders(): void
{
    foreach ($GLOBALS['temporaryFolders'] ?? [] as $folder) {
        File::deleteDirectory($folder);
    }

    $GLOBALS['temporaryFolders'] = [];
}
