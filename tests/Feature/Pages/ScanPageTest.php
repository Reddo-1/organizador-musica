<?php

use App\Filament\Pages\Scan;
use App\Models\Song;
use App\Models\Tag;
use App\Services\Settings;
use App\Support\LocalPath;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->folder = temporaryFolder();
    putTrack($this->folder, 'Neurofunk/Kanine - Sombra.mp3');
    putTrack($this->folder, 'Techstep/Phibes - Ruido.mp3', 'tagged-v23.mp3');
});

it('scans from the page and keeps the summary', function () {
    Livewire::test(Scan::class)
        ->fillForm([
            'music_folder' => $this->folder,
            'default_genre' => 'Drum and Bass',
            'subfolder_as_subgenre' => true,
        ])
        ->call('scan')
        ->assertHasNoFormErrors()
        ->assertNotified('Escaneo terminado');

    $lastScan = app(Settings::class)->lastScan();

    expect(Song::count())->toBe(2)
        ->and(Tag::pluck('name')->sort()->values()->all())->toBe(['Neurofunk', 'Techstep'])
        ->and(app(Settings::class)->musicFolder())->toBe(Settings::folder($this->folder))
        ->and($lastScan['result']->filesInFolder)->toBe(2)
        ->and($lastScan['result']->new)->toHaveCount(2);

    $this->get('/escanear')->assertSee('Último escaneo')->assertSee('2 temas nuevos');
});

it('saves the scan options it was given', function () {
    Livewire::test(Scan::class)
        ->fillForm(['music_folder' => $this->folder, 'default_genre' => '', 'subfolder_as_subgenre' => false])
        ->call('scan');

    expect(app(Settings::class)->defaultGenre())->toBeNull()
        ->and(app(Settings::class)->subfolderAsSubgenre())->toBeFalse()
        ->and(Tag::count())->toBe(0)
        ->and(Song::query()->where('title', 'Ruido de Fondo')->sole()->genre->name)->toBe('Techstep');
});

it('tells when the folder does not exist', function () {
    Livewire::test(Scan::class)
        ->fillForm(['music_folder' => $this->folder.'/no-existe'])
        ->call('scan')
        ->assertNotified('No se encuentra la carpeta');

    expect(Song::count())->toBe(0);
});

it('requires a folder', function () {
    Livewire::test(Scan::class)
        ->fillForm(['music_folder' => ''])
        ->call('scan')
        ->assertHasFormErrors(['music_folder' => 'required']);
});

it('lists missing songs and relocates one onto the renamed file', function () {
    $component = Livewire::test(Scan::class)->fillForm(['music_folder' => $this->folder])->call('scan');
    $song = Song::query()->where('title', 'Sombra Eléctrica')->sole();
    $song->update(['energy' => 5]);

    $old = $this->folder.DIRECTORY_SEPARATOR.'Neurofunk'.DIRECTORY_SEPARATOR.'Kanine - Sombra.mp3';
    $new = $this->folder.DIRECTORY_SEPARATOR.'Neurofunk'.DIRECTORY_SEPARATOR.'Kanine - Sombra Eléctrica (VIP).mp3';
    rename($old, $new);

    $component->call('scan');
    $duplicate = Song::query()->where('path', LocalPath::toStored($new))->sole();

    $component
        ->assertCanSeeTableRecords([$song->fresh()])
        ->callAction(TestAction::make('relocate')->table($song), data: ['target' => $duplicate->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Tema reubicado');

    $song->refresh();

    expect($song->path)->toBe(LocalPath::toStored($new))
        ->and($song->missing)->toBeFalse()
        ->and($song->energy)->toBe(5)
        ->and(Song::count())->toBe(2)
        ->and(Song::find($duplicate->id))->toBeNull();
});

it('removes a missing song from the app only', function () {
    $missing = Song::factory()->missing()->create();

    Livewire::test(Scan::class)
        ->callAction(TestAction::make('remove')->table($missing))
        ->assertNotified('Tema quitado de la app');

    expect(Song::find($missing->id))->toBeNull();
});
