<?php

use App\Models\Song;
use App\Services\AudioPreview;

beforeEach(function () {
    $this->folder = temporaryFolder();
    config(['music.ffmpeg' => is_file((string) env('FFMPEG_BINARY')) ? env('FFMPEG_BINARY') : null]);
});

it('streams an MP3 from the stored path and answers Range requests', function () {
    $song = Song::factory()->create(['path' => putTrack($this->folder, 'Kanine - Sombra.mp3')]);
    $size = filesize($song->path);

    $this->get("/songs/{$song->id}/audio")
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('Accept-Ranges', 'bytes');

    $this->get("/songs/{$song->id}/audio", ['Range' => 'bytes=100-199'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', "bytes 100-199/{$size}");
});

it('only knows songs by id, never by a path in the request', function () {
    $song = Song::factory()->create(['path' => putTrack($this->folder, 'a.mp3')]);

    $this->get("/songs/{$song->id}/audio?path=/etc/passwd")->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    $this->get('/songs/999/audio')->assertNotFound();
});

it('answers 404 when the file is missing', function () {
    $song = Song::factory()->create(['path' => $this->folder.'/no-esta.mp3']);

    expect(app(AudioPreview::class)->status($song))->toBe(AudioPreview::MISSING);
    $this->get("/songs/{$song->id}/audio")->assertNotFound();
});

it('asks for ffmpeg to play an AIFF when it is not configured', function () {
    config(['music.ffmpeg' => null]);
    $song = Song::factory()->create(['path' => putTrack($this->folder, 'Kanine - Sombra.aiff', 'tagged.aiff')]);

    expect(app(AudioPreview::class)->status($song))->toBe(AudioPreview::NEEDS_FFMPEG);
    $this->get("/songs/{$song->id}/audio")->assertNotFound();
    $this->get("/biblioteca/{$song->id}/edit")->assertSee('Configura ffmpeg para escucharlo aquí.');
});

it('converts an AIFF to a 128 kbps MP3 once and keeps it', function () {
    if (! app(AudioPreview::class)->hasFfmpeg()) {
        $this->markTestSkipped('FFMPEG_BINARY no está configurado en este equipo.');
    }

    $source = putTrack($this->folder, 'Kanine - Sombra.aiff', 'tagged.aiff');
    $before = md5_file($source);
    $song = Song::factory()->create(['path' => $source]);

    $this->get("/songs/{$song->id}/audio")->assertOk()->assertHeader('Content-Type', 'audio/mpeg');

    $preview = storage_path("app/previews/{$song->id}-".filemtime($source).'.mp3');

    expect(file_exists($preview))->toBeTrue()
        ->and(app(AudioPreview::class)->status($song))->toBe(AudioPreview::CONVERTED)
        ->and(md5_file($source))->toBe($before);

    $this->get("/songs/{$song->id}/audio", ['Range' => 'bytes=0-99'])->assertStatus(206);

    @unlink($preview);
});
