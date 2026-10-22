<?php

namespace App\Domains\Catalogue\Enums;

enum AttributeType: string
{
    case Number = 'number';
    case Text = 'text';
    case Select = 'select';
    case Boolean = 'boolean';
    case Date = 'date';

    public function label(): string
    {
        return match ($this) {
            self::Number => 'Number',
            self::Text => 'Text',
            self::Select => 'Choice from a list',
            self::Boolean => 'Yes / no',
            self::Date => 'Date',
        };
    }
}
