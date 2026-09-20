<?php

namespace Tests\Unit;

use App\Support\LocalDateTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class LocalDateTimeTest extends TestCase
{
    public function test_utc_date_is_presented_in_sao_paulo_timezone(): void
    {
        config(['app.display_timezone' => 'America/Sao_Paulo']);

        $utcDate = CarbonImmutable::parse('2026-09-20 03:30:00', 'UTC');

        $this->assertSame('20/09/2026 00:30', LocalDateTime::format($utcDate));
        $this->assertSame('Horário de Brasília (UTC-03:00)', LocalDateTime::label());
    }

    public function test_invalid_display_timezone_falls_back_to_utc(): void
    {
        config(['app.display_timezone' => 'Fuso/Invalido']);

        $utcDate = CarbonImmutable::parse('2026-09-20 03:30:00', 'UTC');

        $this->assertSame('20/09/2026 03:30', LocalDateTime::format($utcDate));
        $this->assertSame('UTC (UTC+00:00)', LocalDateTime::label());
    }
}
