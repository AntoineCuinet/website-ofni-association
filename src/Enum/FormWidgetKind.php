<?php

namespace App\Enum;

enum FormWidgetKind: string
{
    case NATIVE = 'native';
    case LIST = 'list';
    case COMPOSED = 'composed';
}
