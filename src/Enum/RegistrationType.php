<?php

namespace App\Enum;

enum RegistrationType: string
{
    case GOOGLE = 'google';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::GOOGLE => 'Google',
            self::MANUAL => 'Manual',
        };
    }
}
