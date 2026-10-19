@php
    /** @var \App\Models\Song $song */
    use App\Support\Palette;

    $song = $getRecord();
    $categories = array_keys(config('music.tag_categories'));
    $tags = $song->tags
        ->sortBy(fn ($tag) => [array_search($tag->category, $categories, true), mb_strtolower($tag->name)])
        ->values();
    $visible = $tags->take(4);
@endphp

<div class="app-cell app-tags">
    @forelse ($visible as $tag)
        <x-filament::badge :color="Palette::tagCategory($tag->category)" size="sm" title="{{ $tag->name }} · {{ $tag->categoryLabel() }}">
            {{ $tag->name }}
        </x-filament::badge>
    @empty
        <span class="app-dash">—</span>
    @endforelse

    @if ($tags->count() > $visible->count())
        <span class="app-tags-more" title="{{ $tags->slice(4)->pluck('name')->join(', ') }}">+{{ $tags->count() - $visible->count() }}</span>
    @endif
</div>
