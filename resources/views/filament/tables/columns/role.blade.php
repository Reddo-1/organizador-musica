@php
    /** @var \App\Models\Song $song */
    use App\Enums\SetRole;

    $song = $getRecord();
    $role = $song->set_role;
    $color = $role?->getColor();
@endphp

<div class="app-cell">
<select
    @class(['app-role-select', 'fi-color', 'fi-color-'.$color => $color && $color !== 'gray', 'is-empty' => ! $role, 'is-neutral' => $color === 'gray'])
    aria-label="Rol en el set de {{ $song->displayName() }}"
    wire:change="setRole({{ $song->id }}, $event.target.value)"
>
    <option value="" @selected(! $role)>—</option>
    @foreach (SetRole::cases() as $case)
        <option value="{{ $case->value }}" @selected($role === $case)>{{ $case->getLabel() }}</option>
    @endforeach
</select>
</div>
