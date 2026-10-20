<?php

namespace App\Filament\Pages;

use App\Enums\SetRole;
use App\Livewire\PlaylistEditor;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Services\FilterPresets;
use App\Services\PlaylistGenerator;
use App\Services\SongFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;

/**
 * The home page (SPEC §5.7): filters with a live count, a set generated from them, the draft to
 * shape and export for rekordbox, and presets of filters.
 */
class GeneratePlaylist extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Generar playlist';

    protected static ?string $title = 'Generar playlist';

    protected static ?string $slug = 'generar';

    protected static ?int $navigationSort = 1;

    /** The filters, the number of songs and the order; kept in the session between visits. */
    #[Session]
    public ?array $data = [];

    /** The playlist "Generar" fills. */
    #[Session]
    public ?int $draftId = null;

    /** True once the draft was exported: the next "Generar" starts a new playlist. */
    #[Session]
    public bool $draftKept = false;

    public ?int $preset = null;

    /** Bumped on every generation so the editor below starts again with the new songs. */
    public int $generation = 0;

    /** The home page of the app (SPEC §6), served at the panel root like Filament's dashboard. */
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function mount(): void
    {
        $this->form->fill($this->withDefaults($this->data ?? []));
    }

    public function form(Schema $schema): Schema
    {
        $bounds = app(SongFilter::class)->bounds();

        return $schema
            ->components([
                View::make('filament.pages.generate.counter')
                    ->viewData(fn (): array => $this->counter()),
                // The main action sits right under the count, above the long list of filters.
                Section::make('Generar')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('count')
                                ->label('Nº de temas')
                                ->helperText('0 = todos los que cumplen los filtros.')
                                ->integer()
                                ->minValue(0)
                                ->maxValue(1000)
                                ->required(),
                            Select::make('order')
                                ->label('Orden')
                                ->options(PlaylistGenerator::ORDERS)
                                ->selectablePlaceholder(false)
                                ->required(),
                        ]),
                        Actions::make([
                            Action::make('generate')
                                ->label('Generar')
                                ->icon(Heroicon::OutlinedSparkles)
                                ->submit('generate')
                                ->keyBindings(['mod+enter']),
                            Action::make('newPlaylist')
                                ->label('Nueva playlist')
                                ->color('gray')
                                ->visible(fn (): bool => $this->draft() !== null && ! $this->draftKept)
                                ->action('startNewPlaylist'),
                        ]),
                        View::make('filament.pages.generate.keys'),
                    ]),
                Section::make('Filtros')
                    ->compact()
                    ->schema([
                        Select::make('genres')
                            ->label('Género')
                            ->multiple()
                            ->options(fn (): array => Genre::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Todos')
                            ->live(),
                        ToggleButtons::make('roles')
                            ->label('Rol en el set')
                            ->multiple()
                            ->options(SetRole::options())
                            ->colors(collect(SetRole::cases())->mapWithKeys(fn (SetRole $role): array => [$role->value => $role->getColor()])->all())
                            ->inline()
                            ->live(),
                        ...$this->tagFields(),
                        Grid::make(1)->schema([
                            $this->range('energy', 'Energía', [1, 5]),
                            $this->range('song_year', 'Año de la canción', $bounds['song_year']),
                            $this->range('discovered_year', 'Año de descubrimiento', $bounds['discovered_year']),
                            $this->range('bpm', 'BPM', $bounds['bpm'] ? [floor($bounds['bpm'][0]), ceil($bounds['bpm'][1])] : null),
                        ]),
                        Toggle::make('include_missing')
                            ->label('Incluir temas cuyo archivo falta')
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        $draft = $this->draft();

        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 12])->schema([
                Group::make([
                    View::make('filament.pages.generate.presets')
                        ->viewData(fn (FilterPresets $presets): array => ['options' => $presets->options()]),
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('generate-form')
                        ->livewireSubmitHandler('generate'),
                ])->columnSpan(['default' => 1, 'xl' => 5]),
                Group::make([
                    $draft
                        ? Livewire::make(PlaylistEditor::class, ['playlistId' => $draft->id, 'draft' => ! $this->draftKept])
                            ->key("editor-{$draft->id}-{$this->generation}")
                        : View::make('filament.pages.generate.empty'),
                ])->columnSpan(['default' => 1, 'xl' => 7]),
            ]),
        ]);
    }

    public function generate(PlaylistGenerator $generator, SongFilter $filter): void
    {
        $state = $this->form->getState();

        if ($filter->apply($state)->doesntExist()) {
            Notification::make()->title('Ningún tema cumple los filtros')->warning()->send();

            return;
        }

        $draft = $this->draftKept ? null : $this->draft();
        $playlist = $generator->generate($state, (int) $state['count'], $state['order'], $draft);

        $this->draftId = $playlist->id;
        $this->draftKept = false;
        $this->generation++;
        unset($this->cachedSchemas['content']);
    }

    /** Keeps the current draft in the history; the next "Generar" starts another playlist. */
    public function startNewPlaylist(): void
    {
        $this->draftKept = true;
        unset($this->cachedSchemas['content']);

        Notification::make()
            ->title('El borrador queda guardado en Playlists')
            ->body('«Generar» empezará una playlist nueva.')
            ->success()
            ->send();
    }

    #[On('playlist-exported')]
    public function keepDraft(int $id): void
    {
        if ($id === $this->draftId) {
            $this->draftKept = true;
            unset($this->cachedSchemas['content']);
        }
    }

    public function updatedPreset(FilterPresets $presets): void
    {
        if ($this->preset === null) {
            return;
        }

        $state = $this->form->getRawState();

        $this->form->fill($this->withDefaults($presets->load($this->preset), [
            'count' => $state['count'] ?? 20,
            'order' => $state['order'] ?? 'random',
        ]));
    }

    /**
     * A filter changed by hand: the filters are no longer the loaded preset, so the selector goes
     * back to empty and choosing that preset loads it again (a select does not fire for the
     * option it already shows). The number of songs and the order are not part of a preset.
     */
    public function updatedData(mixed $value, string $key): void
    {
        if (! in_array($key, ['count', 'order'], true)) {
            $this->preset = null;
        }
    }

    public function savePresetAction(): Action
    {
        return Action::make('savePreset')
            ->label('Guardar filtros')
            ->icon(Heroicon::OutlinedBookmark)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Guardar los filtros como preset')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(fn (FilterPresets $presets): array => ['name' => $this->preset ? ($presets->options()[$this->preset] ?? '') : ''])
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->helperText('Si ya hay un preset con este nombre, se sustituye.')
                    ->required()
                    ->maxLength(100),
            ])
            ->action(function (array $data, FilterPresets $presets): void {
                $filters = SongFilter::normalize($this->form->getRawState());
                $this->preset = $presets->save($data['name'], $filters)->id;

                Notification::make()->title('Preset guardado')->success()->send();
            });
    }

    public function deletePresetAction(): Action
    {
        return Action::make('deletePreset')
            ->label('Borrar preset')
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->tooltip('Borrar este preset')
            ->color('gray')
            ->visible(fn (): bool => $this->preset !== null)
            ->requiresConfirmation()
            ->modalHeading('¿Borrar este preset?')
            ->modalDescription('Solo se borra el preset; los filtros que tienes puestos no cambian.')
            ->modalSubmitActionLabel('Borrar')
            ->action(function (FilterPresets $presets): void {
                $presets->delete($this->preset);
                $this->preset = null;

                Notification::make()->title('Preset borrado')->success()->send();
            });
    }

    /** @return array{matching: int, total: int} */
    private function counter(): array
    {
        return [
            'matching' => app(SongFilter::class)->apply($this->data ?? [])->count(),
            'total' => Song::count(),
        ];
    }

    private function draft(): ?Playlist
    {
        return $this->draftId ? Playlist::query()->find($this->draftId) : null;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        $bounds = app(SongFilter::class)->bounds();
        $tags = collect(config('music.tag_categories'))
            ->map(fn (): array => ['include' => [], 'mode' => SongFilter::ANY, 'exclude' => []])
            ->all();

        return [
            'genres' => [],
            'roles' => [],
            'tags' => $tags,
            'energy' => [1, 5],
            'song_year' => $bounds['song_year'],
            'discovered_year' => $bounds['discovered_year'],
            'bpm' => $bounds['bpm'] ? [floor($bounds['bpm'][0]), ceil($bounds['bpm'][1])] : null,
            'include_missing' => false,
            'count' => 20,
            'order' => 'random',
        ];
    }

    /**
     * The defaults overlaid with stored filters (session or preset). A stored range is fitted to
     * the library as it is now: one saved before there was data, or out of the current bounds,
     * would leave its slider without handles.
     *
     * @return array<string, mixed>
     */
    private function withDefaults(array ...$layers): array
    {
        $defaults = $this->defaults();
        $state = array_replace_recursive($defaults, ...$layers);

        foreach (SongFilter::RANGES as $field) {
            $state[$field] = $this->fitRange($state[$field] ?? null, $defaults[$field]);
        }

        return $state;
    }

    /**
     * @param  array{0: float|int, 1: float|int}|null  $bounds
     * @return array{0: float|int, 1: float|int}|null
     */
    private function fitRange(mixed $range, ?array $bounds): ?array
    {
        $range = array_values((array) $range);

        if ($bounds === null || count($range) !== 2 || ! is_numeric($range[0]) || ! is_numeric($range[1])) {
            return $bounds;
        }

        $low = max(min($range[0], $range[1]), $bounds[0]);
        $high = min(max($range[0], $range[1]), $bounds[1]);

        return $low <= $high ? [$low, $high] : $bounds;
    }

    /** @return list<Fieldset> One include / mode / exclude block per tag category. */
    private function tagFields(): array
    {
        return collect(config('music.tag_categories'))
            ->map(function (string $label, string $category): Fieldset {
                $options = fn (): array => Tag::query()->where('category', $category)->orderBy('name')->pluck('name', 'id')->all();

                return Fieldset::make($label)
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Select::make("tags.{$category}.include")
                            ->label('Incluir')
                            ->multiple()
                            ->searchable()
                            ->options($options)
                            ->placeholder('Cualquiera')
                            ->live(),
                        Select::make("tags.{$category}.exclude")
                            ->label('Excluir')
                            ->multiple()
                            ->searchable()
                            ->options($options)
                            ->placeholder('Ninguna')
                            ->live(),
                        ToggleButtons::make("tags.{$category}.mode")
                            ->label('Si incluyes varias')
                            ->options([SongFilter::ANY => 'Tiene alguna (OR)', SongFilter::ALL => 'Tiene todas (AND)'])
                            ->grouped()
                            ->live()
                            ->columnSpanFull(),
                    ]);
            })
            ->values()
            ->all();
    }

    /** @param  array{0: float|int, 1: float|int}|null  $bounds */
    private function range(string $field, string $label, ?array $bounds): Slider|Text
    {
        if ($bounds === null || $bounds[0] >= $bounds[1]) {
            return Text::make("{$label}: sin datos suficientes en la biblioteca para filtrar.")
                ->color('gray');
        }

        return Slider::make($field)
            ->label($label)
            ->range(minValue: $bounds[0], maxValue: $bounds[1])
            ->step(1)
            ->fillTrack([false, true, false])
            ->tooltips()
            ->markAsRequired(false)
            ->live();
    }
}
