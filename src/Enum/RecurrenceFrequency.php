<?php

namespace App\Enum;

enum RecurrenceFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        };
    }

    public function pluralLabel(): string
    {
        return match($this) {
            self::DAILY => 'days',
            self::WEEKLY => 'weeks',
            self::MONTHLY => 'months',
            self::QUARTERLY => 'quarters',
            self::YEARLY => 'years',
        };
    }
}
