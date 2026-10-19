<?php

namespace App\Filament\Pages;

use App\Models\Genre;
use App\Services\Settings as AppSettings;
use App\Support\LocalPath;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The settings editable from the UI (SPEC §3). Everything specific to this computer lives here
 * or in .env, so the app moves to another PC without code changes.
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Ajustes';

    protected static ?string $title = 'Ajustes';

    protected static ?string $slug = 'ajustes';

    protected static ?int $navigationSort = 7;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(AppSettings $settings): void
    {
        $this->form->fill([
            'music_folder' => $settings->musicFolder(),
            'default_genre' => $settings->defaultGenre(),
            'subfolder_as_subgenre' => $settings->subfolderAsSubgenre(),
            'playlists_folder' => $settings->playlistsFolder(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Biblioteca')
                    ->description('Lo que usa Escanear para dar de alta los temas.')
                    ->aside()
                    ->schema([
                        TextInput::make('music_folder')
                            ->label('Carpeta de música')
                            ->required()
                            ->maxLength(1000)
                            ->extraInputAttributes(['class' => 'app-mono-input', 'spellcheck' => 'false'])
                            ->helperText('Con el formato de Windows, por ejemplo C:\Users\Ricardo\Music\DNB. Los archivos solo se leen.'),
                        TextInput::make('default_genre')
                            ->label('Género por defecto')
                            ->maxLength(100)
                            ->datalist(fn (): array => Genre::query()->orderBy('name')->pluck('name')->all())
                            ->helperText('Se pone a los temas nuevos. Si lo dejas vacío, se usa el género de sus etiquetas.'),
                        Toggle::make('subfolder_as_subgenre')
                            ->label('Usar la primera subcarpeta como subgénero')
                            ->helperText('DNB\Neurofunk\tema.mp3 queda con el subgénero «Neurofunk».'),
                    ]),
                Section::make('Playlists')
                    ->description('Dónde se guardan los .m3u8 que luego arrastras a rekordbox.')
                    ->aside()
                    ->schema([
                        TextInput::make('playlists_folder')
                            ->label('Carpeta de playlists')
                            ->maxLength(1000)
                            ->extraInputAttributes(['class' => 'app-mono-input', 'spellcheck' => 'false'])
                            ->placeholder(config('music.playlists_folder'))
                            ->helperText('Vacía, se usa la carpeta storage\app\playlists de la app. Se crea si no existe.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Guardar')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(AppSettings $settings): void
    {
        $data = $this->form->getState();

        $settings->setMusicFolder($data['music_folder']);
        $settings->setDefaultGenre($data['default_genre'] ?? '');
        $settings->setSubfolderAsSubgenre((bool) ($data['subfolder_as_subgenre'] ?? false));
        $settings->setPlaylistsFolder($data['playlists_folder'] ?? null);

        $this->form->fill([
            'music_folder' => $settings->musicFolder(),
            'default_genre' => $settings->defaultGenre(),
            'subfolder_as_subgenre' => $settings->subfolderAsSubgenre(),
            'playlists_folder' => $settings->playlistsFolder(),
        ]);

        Notification::make()->title('Ajustes guardados')->success()->send();

        if (! is_dir(LocalPath::toLocal($settings->musicFolder()))) {
            Notification::make()
                ->title('La carpeta de música no existe en este equipo')
                ->body("{$settings->musicFolder()} queda guardada; Escanear avisará hasta que exista.")
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
