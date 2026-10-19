<?php

return [
    'default_folder' => env('MUSIC_FOLDER') ?: 'C:\Users\Ricardo\Music\Musikote\DNB',
    'playlists_folder' => env('MUSIC_PLAYLISTS_FOLDER') ?: storage_path('app/playlists'),
    'default_genre' => 'Drum and Bass',
    'subfolder_as_subgenre' => true,
    'extensions' => ['mp3', 'aiff', 'aif', 'wav', 'flac', 'm4a', 'ogg'],
    'browser_playable' => ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
        'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg'],
    'ffmpeg' => env('FFMPEG_BINARY') ?: null, // ruta completa a ffmpeg.exe
    'tag_categories' => ['subgenre' => 'Subgénero', 'percussion' => 'Percusión'],
    'backups_to_keep' => 10,
];
