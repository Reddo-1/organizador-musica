<?php

namespace App\Livewire;

use App\Models\Genre;
use App\Models\Tag;
use App\Services\TagManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The tags of one category, or the genres, with how many songs carry each (SPEC §5.9).
 */
class TagsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public const GENRES = 'genres';

    /** A key of config('music.tag_categories'), or self::GENRES. */
    #[Locked]
    public string $category;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->isGenres()
                ? Genre::query()->withCount('songs')
                : Tag::query()->where('category', $this->category)->withCount('songs'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('songs_count')
                    ->label('Temas')
                    ->numeric(locale: 'es')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('create')
                    ->label($this->isGenres() ? 'Nuevo género' : 'Nueva etiqueta')
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('gray')
                    ->modalHeading($this->isGenres() ? 'Nuevo género' : 'Nueva etiqueta de '.mb_strtolower($this->label()))
                    ->modalSubmitActionLabel('Crear')
                    ->modalWidth('md')
                    ->schema([$this->nameField()])
                    ->action(fn (array $data, TagManager $manager) => $this->run(function () use ($data, $manager): void {
                        $this->isGenres()
                            ? $manager->createGenre($data['name'])
                            : $manager->createTag($this->category, $data['name']);

                        Notification::make()->title('Creado')->success()->send();
                        $this->dispatch('tags-changed');
                    })),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('rename')
                        ->label('Renombrar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->modalHeading(fn (Tag|Genre $record): string => "Renombrar «{$record->name}»")
                        ->modalDescription('Si ya existe otro con ese nombre en esta categoría, se fusionan: sus temas pasan al existente y este se borra. Puedes cambiar solo las mayúsculas.')
                        ->modalSubmitActionLabel('Renombrar')
                        ->modalWidth('md')
                        ->fillForm(fn (Tag|Genre $record): array => ['name' => $record->name])
                        ->schema([$this->nameField()])
                        ->action(fn (Tag|Genre $record, array $data, TagManager $manager) => $this->run(function () use ($record, $data, $manager): void {
                            $songs = $record->songs()->count();
                            $kept = $record instanceof Genre
                                ? $manager->renameGenre($record, $data['name'])
                                : $manager->renameTag($record, $data['name']);

                            $this->dispatch('tags-changed');

                            $kept->is($record)
                                ? Notification::make()->title("Renombrado a «{$kept->name}»")->success()->send()
                                : Notification::make()
                                    ->title("Fusionado con «{$kept->name}»")
                                    ->body(trans_choice('{1} :count tema pasa a esta etiqueta.|[2,*] :count temas pasan a esta etiqueta.', $songs, ['count' => $songs]))
                                    ->success()
                                    ->send();
                        })),
                    Action::make('delete')
                        ->label('Borrar')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Tag|Genre $record): string => "¿Borrar «{$record->name}»?")
                        ->modalDescription(fn (Tag|Genre $record): string => $record->songs_count > 0
                            ? trans_choice('{1} Se quitará de :count tema. Los temas y sus archivos se quedan como están.|[2,*] Se quitará de :count temas. Los temas y sus archivos se quedan como están.', $record->songs_count, ['count' => $record->songs_count])
                            : 'Ningún tema la usa.')
                        ->modalSubmitActionLabel('Borrar')
                        ->action(function (Tag|Genre $record, TagManager $manager): void {
                            $record instanceof Genre ? $manager->deleteGenre($record) : $manager->deleteTag($record);

                            Notification::make()->title('Borrado')->success()->send();
                            $this->dispatch('tags-changed');
                        }),
                ])->tooltip('Acciones')->color('gray'),
            ])
            ->emptyStateHeading($this->isGenres() ? 'Aún no hay géneros' : 'Aún no hay etiquetas de '.mb_strtolower($this->label()))
            ->emptyStateDescription($this->isGenres()
                ? 'Los géneros se crean al escanear o al clasificar.'
                : 'Se crean al clasificar temas'.($this->category === Tag::SUBGENRE ? ' o al escanear subcarpetas.' : '.'))
            ->paginated([25, 50, 100, 'all']);
    }

    public function render(): View
    {
        return view('livewire.tags-table');
    }

    private function nameField(): TextInput
    {
        return TextInput::make('name')
            ->label('Nombre')
            ->required()
            ->maxLength(100)
            ->autofocus();
    }

    private function isGenres(): bool
    {
        return $this->category === self::GENRES;
    }

    private function label(): string
    {
        return $this->isGenres() ? 'Géneros' : config("music.tag_categories.{$this->category}", $this->category);
    }

    private function run(callable $change): void
    {
        try {
            $change();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
