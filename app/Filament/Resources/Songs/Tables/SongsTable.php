<?php

namespace App\Filament\Resources\Songs\Tables;

use App\Enums\SetRole;
use App\Filament\Pages\Scan;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\SongEditor;
use App\Services\TagManager;
use App\Support\Palette;
use App\Support\PathHtml;
use App\Support\Plural;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * Biblioteca (SPEC §5.6): every song with search, filters and sorting. Energy and role are
 * edited right in the row; the bulk actions only change the database, never a file.
 */
final class SongsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['genre', 'tags']))
            ->columns([
                ViewColumn::make('listen')
                    ->label('')
                    ->view('filament.tables.columns.listen')
                    ->width('2.5rem')
                    ->disabledClick(),
                TextColumn::make('artist')
                    ->label('Artista')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->width('10rem')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('title')
                    ->label('Título')
                    // The format tells apart the MP3 and the AIFF of the same song.
                    ->formatStateUsing(fn (Song $record): HtmlString => new HtmlString(e($record->title).' <span class="app-format">'.e(strtoupper($record->extension())).'</span>'))
                    ->searchable()
                    ->sortable()
                    ->width('14rem')
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('genre.name')
                    ->label('Género')
                    ->sortable()
                    ->placeholder('—')
                    // A genre is a few words: on one line it keeps the rows short.
                    ->width('9rem')
                    ->visibleFrom('md')
                    ->toggleable(),
                ViewColumn::make('tags')
                    ->label('Etiquetas')
                    ->view('filament.tables.columns.tags')
                    ->width('11rem')
                    ->visibleFrom('md')
                    ->toggleable(),
                ViewColumn::make('energy')
                    ->label('Energía')
                    ->view('filament.tables.columns.energy')
                    ->sortable()
                    ->disabledClick(),
                ViewColumn::make('set_role')
                    ->label('Rol')
                    ->view('filament.tables.columns.role')
                    ->sortable()
                    ->disabledClick(),
                TextColumn::make('song_year')
                    ->label('Año')
                    ->sortable()
                    ->placeholder('—')
                    ->alignEnd()
                    ->visibleFrom('md'),
                TextColumn::make('discovered_year')
                    ->label('Descubierta')
                    ->sortable()
                    ->placeholder('—')
                    ->alignEnd()
                    ->visibleFrom('md')
                    ->toggleable(),
                TextColumn::make('bpm')
                    ->label('BPM')
                    ->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?float $state): string => self::bpm($state))
                    ->alignEnd(),
                TextColumn::make('musical_key')
                    ->label('Key')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'app-key'])
                    ->visibleFrom('md'),
                TextColumn::make('missing')
                    ->label('Falta')
                    ->state(fn (Song $record): ?string => $record->missing ? 'Falta' : null)
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('path')
                    ->label('Ruta')
                    ->formatStateUsing(fn (string $state): HtmlString => PathHtml::render($state, 'app-path-cell'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('artist')->orderBy('title'))
            ->searchPlaceholder('Artista, título, key o ruta')
            ->filters(self::filters())
            ->filtersFormColumns(2)
            ->toolbarActions([
                BulkActionGroup::make(self::bulkActions())->label('Editar los seleccionados'),
            ])
            // The same empty table means "scan first" or "nothing matches", depending on the library.
            ->emptyStateIcon(Heroicon::OutlinedMusicalNote)
            ->emptyStateHeading(fn (): string => Song::query()->exists() ? 'Ningún tema coincide' : 'Aún no hay temas')
            ->emptyStateDescription(fn (): string => Song::query()->exists()
                ? 'Cambia la búsqueda o quita algún filtro.'
                : 'Escanea tu carpeta de música para darlos de alta.')
            ->emptyStateActions([
                Action::make('scan')
                    ->label('Escanear carpeta')
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->url(fn (): string => Scan::getUrl())
                    ->visible(fn (): bool => Song::query()->doesntExist()),
            ])
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50);
    }

    public static function bpm(?float $bpm): string
    {
        if ($bpm === null) {
            return '—';
        }

        return fmod($bpm, 1.0) === 0.0 ? (string) (int) $bpm : number_format($bpm, 2, ',', '');
    }

    /** @return array<int, mixed> */
    private static function filters(): array
    {
        $tagFilters = collect(config('music.tag_categories'))
            ->map(fn (string $label, string $category): SelectFilter => SelectFilter::make("tags_{$category}")
                ->label($label)
                ->multiple()
                ->options(fn (): array => ['none' => 'Sin '.mb_strtolower($label)] + Tag::query()->where('category', $category)->orderBy('name')->pluck('name', 'id')->all())
                ->query(function (Builder $query, array $data) use ($category): void {
                    $values = $data['values'] ?? [];

                    if ($values === []) {
                        return;
                    }

                    $ids = array_values(array_filter($values, fn ($value): bool => $value !== 'none'));

                    $query->where(function (Builder $query) use ($values, $ids, $category): void {
                        if (in_array('none', $values, true)) {
                            $query->orWhereDoesntHave('tags', fn (Builder $tags) => $tags->where('category', $category));
                        }

                        if ($ids !== []) {
                            $query->orWhereHas('tags', fn (Builder $tags) => $tags->whereKey($ids));
                        }
                    });
                }))
            ->values()
            ->all();

        return [
            SelectFilter::make('energy')
                ->label('Energía')
                ->multiple()
                ->options(['none' => 'Sin energía', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                ->query(fn (Builder $query, array $data) => self::nullableIn($query, 'energy', $data['values'] ?? [])),
            SelectFilter::make('set_role')
                ->label('Rol')
                ->multiple()
                ->options(['none' => 'Sin rol', ...SetRole::options()])
                ->query(fn (Builder $query, array $data) => self::nullableIn($query, 'set_role', $data['values'] ?? [])),
            SelectFilter::make('genre_id')
                ->label('Género')
                ->multiple()
                ->options(fn (): array => Genre::query()->orderBy('name')->pluck('name', 'id')->all()),
            ...$tagFilters,
            TernaryFilter::make('missing')
                ->label('Archivo')
                ->placeholder('Todos')
                ->trueLabel('Falta el archivo')
                ->falseLabel('Archivo presente'),
            self::rangeFilter('song_year', 'Año de la canción'),
            self::rangeFilter('discovered_year', 'Año de descubrimiento'),
            self::rangeFilter('bpm', 'BPM'),
        ];
    }

    /** @param  list<int|string>  $values  'none' stands for "no value". */
    private static function nullableIn(Builder $query, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }

        $present = array_values(array_filter($values, fn ($value): bool => $value !== 'none'));

        $query->where(function (Builder $query) use ($column, $values, $present): void {
            if (in_array('none', $values, true)) {
                $query->orWhereNull($column);
            }

            if ($present !== []) {
                $query->orWhereIn($column, $present);
            }
        });
    }

    private static function rangeFilter(string $column, string $label): Filter
    {
        return Filter::make($column)
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('from')->label("{$label}: desde")->numeric(),
                    TextInput::make('to')->label('hasta')->numeric(),
                ]),
            ])
            ->query(function (Builder $query, array $data) use ($column): void {
                $query
                    ->when(filled($data['from'] ?? null), fn (Builder $query) => $query->where($column, '>=', $data['from']))
                    ->when(filled($data['to'] ?? null), fn (Builder $query) => $query->where($column, '<=', $data['to']));
            })
            ->indicateUsing(function (array $data) use ($label): ?string {
                $from = $data['from'] ?? null;
                $to = $data['to'] ?? null;

                return match (true) {
                    filled($from) && filled($to) => "{$label}: {$from}–{$to}",
                    filled($from) => "{$label}: desde {$from}",
                    filled($to) => "{$label}: hasta {$to}",
                    default => null,
                };
            })
            ->columnSpanFull();
    }

    /** @return list<BulkAction> */
    private static function bulkActions(): array
    {
        $done = fn (int $count, string $what) => Notification::make()
            ->title(Plural::of($count, 'tema', 'temas').' '.$what)
            ->success()
            ->send();
        // Says how many songs a bulk edit will change before it is applied.
        $selected = fn (Collection $records): string => 'Se aplica a '.Plural::of($records->count(), 'tema seleccionado', 'temas seleccionados').'.';

        return [
            BulkAction::make('setEnergy')
                ->modalDescription($selected)
                ->modalSubmitActionLabel('Aplicar')
                ->label('Poner energía')
                ->icon(Heroicon::OutlinedBolt)
                ->modalWidth('md')
                ->schema([
                    ToggleButtons::make('energy')
                        ->label('Energía')
                        ->options([0 => '–', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                        ->colors([0 => 'gray'] + Palette::ENERGY)
                        ->inline()
                        ->grouped()
                        ->required(),
                ])
                ->action(fn (Collection $records, array $data, SongEditor $editor) => $done($editor->setEnergy($records->modelKeys(), $data['energy']), 'con la energía cambiada'))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('setRole')
                ->modalDescription($selected)
                ->modalSubmitActionLabel('Aplicar')
                ->label('Poner rol')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->modalWidth('md')
                ->schema([
                    ToggleButtons::make('set_role')
                        ->label('Rol en el set')
                        ->options(['' => '–', ...SetRole::options()])
                        ->inline()
                        ->grouped(),
                ])
                ->action(fn (Collection $records, array $data, SongEditor $editor) => $done($editor->setRole($records->modelKeys(), $data['set_role'] ?? null), 'con el rol cambiado'))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('setGenre')
                ->modalDescription($selected)
                ->modalSubmitActionLabel('Aplicar')
                ->label('Poner género')
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->modalWidth('md')
                ->schema([
                    Select::make('genre_id')
                        ->label('Género')
                        ->options(fn (): array => Genre::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('Sin género')
                        ->createOptionForm([TextInput::make('name')->label('Nombre')->required()->maxLength(100)])
                        ->createOptionUsing(fn (array $data): int => app(TagManager::class)->createGenre($data['name'])->id),
                ])
                ->action(fn (Collection $records, array $data, SongEditor $editor) => $done($editor->setGenre($records->modelKeys(), filled($data['genre_id'] ?? null) ? (int) $data['genre_id'] : null), 'con el género cambiado'))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('addTags')
                ->modalDescription($selected)
                ->modalSubmitActionLabel('Aplicar')
                ->label('Añadir etiquetas')
                ->icon(Heroicon::OutlinedTag)
                ->modalWidth('lg')
                ->schema([
                    Select::make('category')
                        ->label('Categoría')
                        ->options(config('music.tag_categories'))
                        ->default(array_key_first(config('music.tag_categories')))
                        ->required()
                        ->live(),
                    TagsInput::make('names')
                        ->label('Etiquetas')
                        ->placeholder('Escribe y pulsa Intro')
                        ->suggestions(fn (Get $get): array => Tag::query()->where('category', $get('category'))->orderBy('name')->pluck('name')->all())
                        ->splitKeys(['Tab', ','])
                        ->required(),
                ])
                ->action(fn (Collection $records, array $data, SongEditor $editor) => $done($editor->addTags($records->modelKeys(), $data['category'], $data['names']), 'con etiquetas añadidas'))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('removeTags')
                ->modalDescription($selected)
                ->modalSubmitActionLabel('Aplicar')
                ->label('Quitar etiquetas')
                ->icon(Heroicon::OutlinedMinusCircle)
                ->modalWidth('lg')
                ->schema(fn (Collection $records): array => [
                    Select::make('tags')
                        ->label('Etiquetas que tienen los seleccionados')
                        ->multiple()
                        ->options(Tag::query()
                            ->whereHas('songs', fn (Builder $songs) => $songs->whereKey($records->modelKeys()))
                            ->orderBy('category')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Tag $tag): array => [$tag->id => "{$tag->name} · {$tag->categoryLabel()}"])
                            ->all())
                        ->required(),
                ])
                ->action(function (Collection $records, array $data, SongEditor $editor): void {
                    $editor->removeTags($records->modelKeys(), array_map('intval', $data['tags']));

                    Notification::make()->title('Etiquetas quitadas')->success()->send();
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('remove')
                ->label('Quitar de la app')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(fn (Collection $records): string => '¿Quitar '.Plural::of($records->count(), 'tema', 'temas').' de la app?')
                ->modalDescription('Se borran los temas y su clasificación de la base de datos. Los archivos no se tocan.')
                ->modalSubmitActionLabel('Quitar')
                ->action(fn (Collection $records, SongEditor $editor) => $done($editor->remove($records->modelKeys()), 'quitados de la app'))
                ->deselectRecordsAfterCompletion(),
        ];
    }
}
