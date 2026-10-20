<?php

namespace App\Filament\Pages;

use App\Exceptions\MusicFolderNotFound;
use App\Filament\Concerns\PicksFolders;
use App\Models\Genre;
use App\Models\Song;
use App\Services\LibraryScanner;
use App\Services\Settings;
use App\Services\SongRelocator;
use App\Support\PathHtml;
use App\Support\Plural;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * Scan the music folder (SPEC §5.1) and deal with the songs whose file is missing (SPEC §5.3).
 */
class Scan extends Page implements HasTable
{
    use InteractsWithTable;
    use PicksFolders;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $navigationLabel = 'Escanear';

    protected static ?string $title = 'Escanear';

    protected static ?string $slug = 'escanear';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var Collection<int, Song>|null */
    private ?Collection $presentSongs = null;

    public function mount(Settings $settings): void
    {
        $this->form->fill([
            'music_folder' => $settings->musicFolder(),
            'default_genre' => $settings->defaultGenre(),
            'subfolder_as_subgenre' => $settings->subfolderAsSubgenre(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('music_folder')
                    ->label('Carpeta de música')
                    ->required()
                    ->maxLength(1000)
                    ->extraInputAttributes(['class' => 'app-mono-input', 'spellcheck' => 'false'])
                    ->suffixAction(self::pickFolderAction('data.music_folder'))
                    ->helperText('Escríbela con el formato de Windows (C:\Users\Ricardo\Music\DNB) o elígela con el botón de la carpeta.'),
                TextInput::make('default_genre')
                    ->label('Género por defecto')
                    ->maxLength(100)
                    ->datalist(fn (): array => Genre::query()->orderBy('name')->pluck('name')->all())
                    ->helperText('Se pone a los temas nuevos. Si lo dejas vacío, se usa el género de sus etiquetas.'),
                Toggle::make('subfolder_as_subgenre')
                    ->label('Usar la primera subcarpeta como subgénero')
                    ->helperText('DNB\Neurofunk\tema.mp3 queda con el subgénero «Neurofunk».'),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Carpeta')
                    ->description('Da de alta los temas nuevos y detecta los que faltan. Los archivos solo se leen: nunca se modifican, mueven ni borran.')
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('scan-form')
                            ->livewireSubmitHandler('scan')
                            ->footer([
                                Actions::make([
                                    Action::make('scan')
                                        ->label('Escanear carpeta')
                                        ->submit('scan')
                                        ->keyBindings(['mod+enter']),
                                ]),
                            ]),
                    ]),
                // A closure, so the summary is read when the page renders, after a scan in the same request.
                View::make('filament.pages.scan.summary')
                    ->viewData(fn (): array => ['lastScan' => app(Settings::class)->lastScan()]),
                EmbeddedTable::make(),
            ]);
    }

    public function scan(LibraryScanner $scanner, Settings $settings): void
    {
        $data = $this->form->getState();

        $settings->setMusicFolder($data['music_folder']);
        $settings->setDefaultGenre($data['default_genre'] ?? '');
        $settings->setSubfolderAsSubgenre((bool) ($data['subfolder_as_subgenre'] ?? false));

        // The first scan of a library reads every file's tags; over WSL that can take minutes.
        @set_time_limit(0);

        try {
            $result = $scanner->scan();
        } catch (MusicFolderNotFound $exception) {
            Notification::make()
                ->title('No se encuentra la carpeta')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $settings->setLastScan($result);
        $this->data['music_folder'] = $result->folder;
        $this->resetTable();

        $summary = [
            Plural::of(count($result->new), 'nuevo', 'nuevos'),
            Plural::of(count($result->relocated), 'reubicado', 'reubicados'),
            Plural::of(count($result->missing), 'no encontrado', 'no encontrados'),
        ];

        if ($result->errors !== []) {
            $summary[] = Plural::of(count($result->errors), 'error', 'errores');
        }

        $classifyUrl = self::classifyNewUrl($result->new);

        Notification::make()
            ->title('Escaneo terminado')
            ->body(implode(' · ', $summary))
            ->success()
            ->actions($classifyUrl ? [
                Action::make('classifyNew')->label('Clasificar los nuevos')->button()->url($classifyUrl),
            ] : [])
            ->send();
    }

    /**
     * Clasificar opened on the first of the songs a scan added, or null when it added none (or they
     * are gone since). The rest of them follow in the «Sin energía» queue.
     *
     * @param  list<string>  $paths
     */
    public static function classifyNewUrl(array $paths): ?string
    {
        if ($paths === []) {
            return null;
        }

        $first = Song::query()
            ->whereIn('path', $paths)
            ->whereNull('energy')
            ->orderBy('artist')
            ->orderBy('title')
            ->orderBy('id')
            ->value('id');

        return $first ? Classify::getUrl(['tema' => $first]) : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Song::query()->missing())
            ->heading('Temas que faltan')
            ->description('Su archivo ya no está en la ruta guardada. Reubícalos en el archivo nuevo o quítalos de la app; el archivo no se toca.')
            ->columns([
                TextColumn::make('title')
                    ->label('Tema')
                    ->formatStateUsing(fn (Song $record): string => $record->displayName())
                    ->description(fn (Song $record): HtmlString => PathHtml::render($record->path, 'app-path-cell', app(Settings::class)->musicFolder()))
                    ->searchable(['title', 'artist', 'path'])
                    ->sortable(['artist', 'title'])
                    ->wrap(),
                TextColumn::make('best_match')
                    ->label('Archivo más parecido')
                    ->state(fn (Song $record): ?string => $this->bestMatch($record)['label'] ?? null)
                    ->description(fn (Song $record): ?HtmlString => isset($this->bestMatch($record)['song'])
                        ? PathHtml::render($this->bestMatch($record)['song']->path, 'app-path-cell', app(Settings::class)->musicFolder())
                        : null)
                    ->placeholder('Ningún tema presente')
                    ->wrap()
                    ->visibleFrom('md'),
            ])
            ->defaultSort('path')
            ->recordActions([
                Action::make('relocate')
                    ->label('Reubicar')
                    ->visible(fn (): bool => $this->presentSongs()->isNotEmpty())
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->modalHeading(fn (Song $record): string => "Reubicar «{$record->displayName()}»")
                    ->modalDescription('Elige el archivo nuevo de este tema. El tema conserva su clasificación, toma las etiquetas del elegido y ese duplicado desaparece de la biblioteca.')
                    ->modalSubmitActionLabel('Reubicar')
                    ->modalWidth('3xl')
                    ->fillForm(fn (Song $record): array => ['target' => array_key_first($this->suggestionOptions($record))])
                    ->schema(fn (Song $record): array => [
                        Radio::make('target')
                            ->label('Temas presentes con el nombre de archivo más parecido')
                            ->options(fn (): array => $this->suggestionOptions($record))
                            ->descriptions(fn (): array => $this->suggestionDescriptions($record))
                            ->required(),
                    ])
                    ->action(function (Song $record, array $data, SongRelocator $relocator): void {
                        $duplicate = Song::query()->present()->findOrFail($data['target']);
                        $song = $relocator->relocate($record, $duplicate);

                        Notification::make()
                            ->title('Tema reubicado')
                            ->body($song->path)
                            ->success()
                            ->send();
                    }),
                Action::make('remove')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->modalIconColor('danger')
                    ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Song $record): string => "¿Quitar «{$record->displayName()}» de la app?")
                    ->modalDescription('Se borran el tema y su clasificación de la base de datos. El archivo no se toca.')
                    ->modalSubmitActionLabel('Quitar')
                    ->action(function (Song $record, SongRelocator $relocator): void {
                        $relocator->remove($record);

                        Notification::make()->title('Tema quitado de la app')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('remove')
                    ->label('Quitar de la app')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Quitar los temas seleccionados de la app?')
                    ->modalDescription('Se borran los temas y su clasificación de la base de datos. Los archivos no se tocan.')
                    ->modalSubmitActionLabel('Quitar')
                    ->action(function (Collection $records, SongRelocator $relocator): void {
                        $records->each(fn (Song $song) => $relocator->remove($song));

                        Notification::make()->title("{$records->count()} temas quitados de la app")->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyState(view('filament.tables.empty-line', ['message' => 'No falta ningún archivo: todos los temas están en su ruta.']))
            ->paginated([10, 25, 50]);
    }

    /** @return array{song?: Song, label?: string} */
    private function bestMatch(Song $missing): array
    {
        $best = app(SongRelocator::class)->suggestions($missing, 1, $this->presentSongs())->first();

        return $best === null ? [] : [
            'song' => $best['song'],
            'label' => "{$best['similarity']} % · {$best['song']->displayName()}",
        ];
    }

    /** @return array<int, string> */
    private function suggestionOptions(Song $missing): array
    {
        return app(SongRelocator::class)->suggestions($missing, 8, $this->presentSongs())
            ->mapWithKeys(fn (array $item): array => [
                $item['song']->id => "{$item['similarity']} % · {$item['song']->displayName()}",
            ])
            ->all();
    }

    /** @return array<int, HtmlString> */
    private function suggestionDescriptions(Song $missing): array
    {
        return app(SongRelocator::class)->suggestions($missing, 8, $this->presentSongs())
            ->mapWithKeys(fn (array $item): array => [$item['song']->id => PathHtml::render($item['song']->path, 'app-path-block', app(Settings::class)->musicFolder())])
            ->all();
    }

    /** @return Collection<int, Song> */
    private function presentSongs(): Collection
    {
        return $this->presentSongs ??= Song::query()->present()->get(['id', 'path', 'title', 'artist', 'missing']);
    }

    /** @return list<string> */
    protected function pickableFolders(): array
    {
        return ['data.music_folder'];
    }
}
