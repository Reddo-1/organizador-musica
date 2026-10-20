<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PicksFolders;
use App\Models\Genre;
use App\Services\BackupManager;
use App\Services\LibraryRelocator;
use App\Services\Settings as AppSettings;
use App\Support\LocalPath;
use App\Support\Plural;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The settings editable from the UI (SPEC §3). Everything specific to this computer lives here
 * or in .env, so the app moves to another PC without code changes.
 */
class Settings extends Page
{
    use PicksFolders;

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
                            ->suffixAction(self::pickFolderAction('data.music_folder'))
                            ->helperText('Escríbela con el formato de Windows (C:\Users\Ricardo\Music\DNB) o elígela con el botón de la carpeta. Los archivos solo se leen.'),
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
                            ->suffixAction(self::pickFolderAction('data.playlists_folder'))
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
                Section::make('Copias de seguridad')
                    ->description('Una al día, en la primera visita del día. Se guardan las '.config('music.backups_to_keep').' últimas. Para llevar tu biblioteca a otro PC, copia la más reciente como database/database.sqlite.')
                    ->aside()
                    ->schema([
                        View::make('filament.pages.settings.backups')
                            ->viewData(fn (BackupManager $backups): array => [
                                'available' => $backups->isAvailable(),
                                'directory' => $backups->directory(),
                                'backups' => array_slice($backups->backups(), 0, 5),
                            ]),
                        Actions::make([
                            Action::make('backupNow')
                                ->label('Copia ahora')
                                ->color('gray')
                                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                                ->visible(fn (BackupManager $backups): bool => $backups->isAvailable())
                                ->action(function (BackupManager $backups): void {
                                    $file = $backups->backupNow();

                                    Notification::make()->title('Copia hecha')->body(basename($file))->success()->send();
                                }),
                        ])->key('backups-actions'),
                    ]),
                Section::make('Mover la biblioteca')
                    ->description('Si la música está ahora en otra carpeta, otro disco u otro PC, cambia de una vez el principio de las rutas de todos los temas. Los archivos no se tocan.')
                    ->aside()
                    ->schema([
                        Actions::make([
                            Action::make('relocateLibrary')
                                ->label('Cambiar la carpeta de los temas')
                                ->color('gray')
                                ->icon(Heroicon::OutlinedArrowsRightLeft)
                                ->modalHeading('Cambiar la carpeta de los temas')
                                ->modalDescription('Las rutas que empiecen por la carpeta de antes pasarán a empezar por la nueva. Después, escanea para comprobar que están todos.')
                                ->modalSubmitActionLabel('Cambiar')
                                ->schema([
                                    TextInput::make('from')
                                        ->label('Carpeta de antes')
                                        ->placeholder('D:\Musica\DNB')
                                        ->required()
                                        ->extraInputAttributes(['class' => 'app-mono-input']),
                                    TextInput::make('to')
                                        ->label('Carpeta de ahora')
                                        ->default(fn (AppSettings $settings): string => $settings->musicFolder())
                                        ->required()
                                        ->extraInputAttributes(['class' => 'app-mono-input']),
                                ])
                                ->action(function (array $data, LibraryRelocator $relocator): void {
                                    $changed = $relocator->relocate($data['from'], $data['to']);

                                    Notification::make()
                                        ->title(Plural::of($changed, 'tema', 'temas').' con la ruta cambiada')
                                        ->success()
                                        ->send();
                                }),
                        ])->key('relocate-actions'),
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

    /** @return list<string> */
    protected function pickableFolders(): array
    {
        return ['data.music_folder', 'data.playlists_folder'];
    }
}
