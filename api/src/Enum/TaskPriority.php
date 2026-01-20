<?php

namespace App\Enum;

enum TaskPriority: string
{
    case NONE = 'none';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function label(): string
    {
        return match($this) {
            self::NONE => 'None',
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
        };
    }

    public function order(): int
    {
        return match($this) {
            self::NONE => 0,
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
        };
    }
}
