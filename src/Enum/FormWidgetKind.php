<?php

namespace App\Enum;

enum FormWidgetKind: string
{
    case NATIVE = 'native';
    case LIST = 'list';
    case COMPOSED = 'composed';

    public function label(): string
    {
        return match ($this) {
            self::NATIVE => 'Champ de base',
            self::LIST => 'Liste',
            self::COMPOSED => 'Composé',
        };
    }
}
