<?php

use App\Http\Controllers\SongAudioController;
use Illuminate\Support\Facades\Route;

// Every page is served by the Filament panel (app/Providers/Filament/AppPanelProvider.php) from the root.

Route::get('/songs/{song}/audio', SongAudioController::class)->name('songs.audio');
