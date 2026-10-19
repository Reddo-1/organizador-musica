<?php

namespace App\Filament\Resources\Playlists;

use App\Filament\Pages\GeneratePlaylist;
use App\Filament\Resources\Playlists\Pages\ListPlaylists;
use App\Filament\Resources\Playlists\Pages\ViewPlaylist;
use App\Models\Playlist;
use App\Services\M3u8Exporter;
use App\Services\PlaylistEditor;
use App\Support\Duration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The history of generated playlists (SPEC §5.7): reopen, export again or delete. */
class PlaylistResource extends Resource
{
    protected static ?string $model = Playlist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Playlists';

    protected static ?string $modelLabel = 'playlist';

    protected static ?string $pluralModelLabel = 'playlists';

    protected static ?string $slug = 'playlists';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('songs')->withSum('songs', 'duration'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('songs_count')
                    ->label('Temas')
                    ->numeric(locale: 'es')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('songs_sum_duration')
                    ->label('Duración')
                    ->formatStateUsing(fn ($state): string => Duration::human((float) $state))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Playlist $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('save')
                        ->label('Guardar .m3u8')
                        ->icon(Heroicon::OutlinedArrowDownOnSquare)
                        ->action(function (Playlist $record, PlaylistEditor $editor): void {
                            try {
                                $path = $editor->export($record);
                            } catch (\RuntimeException $exception) {
                                Notification::make()->title('No se pudo guardar el .m3u8')->body($exception->getMessage())->danger()->send();

                                return;
                            }

                            Notification::make()->title('Playlist guardada')->body($path)->success()->send();
                        }),
                    Action::make('download')
                        ->label('Descargar .m3u8')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(fn (Playlist $record, PlaylistEditor $editor) => response()->streamDownload(
                            function () use ($editor, $record): void {
                                echo $editor->m3u8($record);
                            },
                            M3u8Exporter::fileName($record->name),
                            ['Content-Type' => 'audio/x-mpegurl; charset=UTF-8'],
                        )),
                    Action::make('delete')
                        ->label('Borrar')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Playlist $record): string => "¿Borrar «{$record->name}»?")
                        ->modalDescription('Se borra la playlist de la app. Los temas y los .m3u8 que hayas guardado no se tocan.')
                        ->modalSubmitActionLabel('Borrar')
                        ->action(function (Playlist $record): void {
                            $record->delete();

                            Notification::make()->title('Playlist borrada')->success()->send();
                        }),
                ])->color('gray')->tooltip('Acciones'),
            ])
            ->emptyStateHeading('Aún no has generado ninguna playlist')
            ->emptyStateDescription('Las playlists que generes aparecen aquí para reabrirlas o volver a exportarlas.')
            ->emptyStateActions([
                Action::make('generate')->label('Generar playlist')->url(GeneratePlaylist::getUrl())->color('gray'),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaylists::route('/'),
            'view' => ViewPlaylist::route('/{record}'),
        ];
    }
}
