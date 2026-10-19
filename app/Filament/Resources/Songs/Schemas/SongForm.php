<?php

namespace App\Filament\Resources\Songs\Schemas;

use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\TagManager;
use App\Support\Palette;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The song card (SPEC §5.4), shared by Biblioteca's edit page and Clasificar. Its state is the
 * one App\Services\SongEditor::formData() gives and SongEditor::save() takes.
 */
final class SongForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components())->columns(1);
    }

    /** @return array<int, mixed> */
    public static function components(): array
    {
        $year = (int) now()->year + 1;

        return [
            View::make('filament.songs.player')
                ->viewData(fn (?Song $record): array => ['song' => $record]),
            Section::make()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('title')->label('Título')->required()->maxLength(255),
                        TextInput::make('artist')->label('Artista')->maxLength(255),
                    ]),
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        ToggleButtons::make('energy')
                            ->label('Energía')
                            ->options([0 => '–', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                            ->colors([0 => 'gray'] + Palette::ENERGY)
                            ->inline()
                            ->grouped()
                            ->extraAttributes(['class' => 'app-energy-buttons']),
                        ToggleButtons::make('set_role')
                            ->label('Rol en el set')
                            ->options(['' => '–', ...SetRole::options()])
                            ->colors(['' => 'gray', ...collect(SetRole::cases())->mapWithKeys(fn (SetRole $role): array => [$role->value => $role->getColor()])->all()])
                            ->inline()
                            ->grouped(),
                    ]),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        Select::make('genre_id')
                            ->label('Género')
                            ->options(fn (): array => Genre::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->createOptionForm([TextInput::make('name')->label('Nombre')->required()->maxLength(100)])
                            ->createOptionUsing(fn (array $data): int => app(TagManager::class)->createGenre($data['name'])->id)
                            ->createOptionModalHeading('Nuevo género'),
                        ...collect(config('music.tag_categories'))
                            ->map(fn (string $label, string $category): TagsInput => TagsInput::make("tags.{$category}")
                                ->label($label)
                                ->placeholder('Escribe y pulsa Intro')
                                ->suggestions(fn (): array => Tag::query()->where('category', $category)->orderBy('name')->pluck('name')->all())
                                ->splitKeys(['Tab', ','])
                                ->color(Palette::tagCategory($category)))
                            ->values()
                            ->all(),
                    ]),
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextInput::make('song_year')->label('Año de la canción')->integer()->minValue(1950)->maxValue($year),
                        TextInput::make('discovered_year')->label('Año de descubrimiento')->integer()->minValue(1950)->maxValue($year),
                        TextInput::make('bpm')->label('BPM')->numeric()->minValue(50)->maxValue(250)->step(0.01),
                        TextInput::make('musical_key')
                            ->label('Key')
                            ->maxLength(20)
                            ->datalist(collect(range(1, 12))->flatMap(fn (int $n): array => ["{$n}A", "{$n}B"])->all()),
                    ]),
                    Textarea::make('notes')->label('Notas')->rows(3)->maxLength(5000),
                ]),
        ];
    }
}
