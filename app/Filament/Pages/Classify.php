<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Songs\Schemas\SongForm;
use App\Models\Song;
use App\Services\ClassificationQueue;
use App\Services\SongEditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * Work through the library one song at a time (SPEC §5.4): a queue on the left, the song card on
 * the right, 1-5 for energy and Ctrl+Enter to save and move on.
 */
class Classify extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Clasificar';

    protected static ?string $title = 'Clasificar';

    protected static ?string $slug = 'clasificar';

    protected static ?int $navigationSort = 2;

    #[Url(as: 'vista')]
    public string $queueView = ClassificationQueue::NO_ENERGY;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'tema')]
    public ?int $songId = null;

    /**
     * The view as it was when it was opened, so saving a song does not reshuffle the queue.
     *
     * @var list<int>
     */
    #[Locked]
    public array $queue = [];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        if (! array_key_exists($this->queueView, ClassificationQueue::VIEWS)) {
            $this->queueView = ClassificationQueue::NO_ENERGY;
        }

        $this->openQueue(keepCurrent: true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(SongForm::components())
            ->statePath('data')
            ->model($this->currentSong() ?? Song::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.classify.toolbar')
                ->viewData(fn (ClassificationQueue $queue): array => [
                    'progress' => $queue->progress(),
                    'counts' => $queue->counts($this->search),
                ]),
            Grid::make(['default' => 1, 'lg' => 7])->schema([
                View::make('filament.pages.classify.queue')
                    ->viewData(fn (ClassificationQueue $queue): array => [
                        'songs' => $this->queueSongs(),
                        'pending' => count(array_intersect($this->queue, $queue->ids($this->queueView, $this->search))),
                    ])
                    ->columnSpan(['default' => 1, 'lg' => 2]),
                Group::make([
                    $this->currentSong()
                        ? Form::make([EmbeddedSchema::make('form')])
                            ->id('classify-form')
                            ->livewireSubmitHandler('saveAndNext')
                            ->footer([
                                // Aligned to the end, Filament lays actions out right to left: the first one is the rightmost.
                                Actions::make([
                                    Action::make('saveAndNext')
                                        ->label('Guardar y siguiente')
                                        ->submit('saveAndNext')
                                        ->keyBindings(['mod+enter']),
                                    Action::make('save')
                                        ->label('Guardar')
                                        ->color('gray')
                                        ->action('save')
                                        ->keyBindings(['mod+s']),
                                    Action::make('next')
                                        ->label('Siguiente')
                                        ->icon(Heroicon::OutlinedChevronRight)
                                        ->iconPosition('after')
                                        ->color('gray')
                                        ->disabled(fn (): bool => ClassificationQueue::after($this->queue, $this->songId) === null)
                                        ->action('next'),
                                    Action::make('previous')
                                        ->label('Anterior')
                                        ->icon(Heroicon::OutlinedChevronLeft)
                                        ->color('gray')
                                        ->disabled(fn (): bool => ClassificationQueue::before($this->queue, $this->songId) === null)
                                        ->action('previous'),
                                ])
                                    ->alignEnd()
                                    // Keep the buttons on screen while the card scrolls.
                                    ->sticky(),
                            ])
                        : View::make('filament.pages.classify.done')
                            ->viewData(fn (): array => ['view' => ClassificationQueue::VIEWS[$this->queueView], 'search' => $this->search]),
                    View::make('filament.pages.classify.keys'),
                ])->columnSpan(['default' => 1, 'lg' => 5]),
            ]),
        ]);
    }

    public function updatedQueueView(): void
    {
        if (! array_key_exists($this->queueView, ClassificationQueue::VIEWS)) {
            $this->queueView = ClassificationQueue::NO_ENERGY;
        }

        $this->openQueue(keepCurrent: false);
    }

    public function updatedSearch(): void
    {
        $this->openQueue(keepCurrent: false);
    }

    public function selectSong(int $id): void
    {
        if (in_array($id, $this->queue, true)) {
            $this->show($id);
        }
    }

    public function previous(): void
    {
        $this->show(ClassificationQueue::before($this->queue, $this->songId) ?? $this->songId);
    }

    public function next(): void
    {
        $this->show(ClassificationQueue::after($this->queue, $this->songId) ?? $this->songId);
    }

    /** Called from the 1-5 keys. */
    public function setEnergy(int $energy): void
    {
        if ($energy >= 1 && $energy <= 5 && $this->currentSong() !== null) {
            $this->data['energy'] = $energy;
        }
    }

    public function save(): void
    {
        if ($this->store()) {
            Notification::make()->title('Guardado')->success()->send();
        }
    }

    public function saveAndNext(): void
    {
        if (! $this->store()) {
            return;
        }

        $next = ClassificationQueue::after($this->queue, $this->songId);

        if ($next === null) {
            // End of the queue: start again with what is still left in the view.
            $this->queue = app(ClassificationQueue::class)->ids($this->queueView, $this->search);
            $next = $this->queue[0] ?? null;

            if ($next === $this->songId) {
                $next = null;
            }
        }

        $this->show($next);
    }

    public function currentSong(): ?Song
    {
        return $this->songId === null ? null : Song::query()->with('tags')->find($this->songId);
    }

    /** @return Collection<int, Song> */
    public function queueSongs(): Collection
    {
        $songs = Song::query()->whereKey($this->queue)->get(['id', 'artist', 'title', 'energy', 'set_role', 'missing'])->keyBy('id');

        return collect($this->queue)->map(fn (int $id): ?Song => $songs->get($id))->filter()->values();
    }

    private function store(): bool
    {
        $song = $this->currentSong();

        if ($song === null) {
            return false;
        }

        app(SongEditor::class)->save($song, $this->form->getState());

        return true;
    }

    private function openQueue(bool $keepCurrent): void
    {
        $this->queue = app(ClassificationQueue::class)->ids($this->queueView, $this->search);

        $keep = $keepCurrent && $this->songId !== null && Song::query()->whereKey($this->songId)->exists();

        if ($keep && ! in_array($this->songId, $this->queue, true)) {
            // Opened on a song from elsewhere (a link): keep it reachable at the head of the queue.
            array_unshift($this->queue, $this->songId);
        }

        $this->show($keep ? $this->songId : ($this->queue[0] ?? null));
    }

    private function show(?int $songId): void
    {
        $this->songId = $songId;

        // The form and the page are built around the current song; rebuild them for the new one.
        unset($this->cachedSchemas['form'], $this->cachedSchemas['content']);

        $song = $this->currentSong();
        $this->form->fill($song ? app(SongEditor::class)->formData($song) : []);
    }
}
