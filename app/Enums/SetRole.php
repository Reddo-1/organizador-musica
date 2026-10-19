<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SetRole: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Body = 'body';
    case Weight = 'weight';
    case Breather = 'breather';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Abrir',
            self::Body => 'Cuerpo',
            self::Weight => 'Peso',
            self::Breather => 'Respiro',
        };
    }

    /**
     * One fixed colour per role, kept apart from the energy scale (blue → red).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'cyan',
            self::Body => 'gray',
            self::Weight => 'fuchsia',
            self::Breather => 'indigo',
        };
    }

    /**
     * @return array<string, string> value => label, in set order.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->getLabel()])
            ->all();
    }
}
