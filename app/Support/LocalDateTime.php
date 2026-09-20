<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class LocalDateTime
{
    public static function format(DateTimeInterface|string|null $value, string $format = 'd/m/Y H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return CarbonImmutable::parse($value)
            ->setTimezone(self::timezone())
            ->format($format);
    }

    public static function label(): string
    {
        $timezone = self::timezone();
        $offset = CarbonImmutable::now($timezone)->format('P');

        if ($timezone->getName() === 'America/Sao_Paulo') {
            return "Horário de Brasília (UTC{$offset})";
        }

        return "{$timezone->getName()} (UTC{$offset})";
    }

    private static function timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone((string) config('app.display_timezone', 'America/Sao_Paulo'));
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
