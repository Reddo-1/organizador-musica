<?php

namespace App\Http\Controllers;

use App\Exceptions\AudioNotPlayable;
use App\Models\Song;
use App\Services\AudioPreview;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a song for the player (SPEC §5.5). The file always comes from the path stored for the
 * song, never from the request. response()->file() answers Range requests, so the player can seek.
 */
class SongAudioController extends Controller
{
    public function __invoke(Request $request, Song $song, AudioPreview $preview): BinaryFileResponse
    {
        try {
            $file = $preview->playable($song, forceConversion: $request->boolean('preview'));
        } catch (AudioNotPlayable $exception) {
            abort(404, $exception->getMessage());
        }

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
