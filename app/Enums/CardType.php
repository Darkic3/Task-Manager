<?php

namespace App\Enums;

enum CardType: string
{
    case Definition = 'definition';
    case Comparison = 'comparison';
    case Process = 'process';
    case Port = 'port';
    case EventId = 'event_id';
    case Attack = 'attack';
    case Scenario = 'scenario';
    case Ioc = 'ioc';
    case Principle = 'principle';
    case Cloze = 'cloze';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Definition => 'Definition',
            self::Comparison => 'Comparison',
            self::Process => 'Process',
            self::Port => 'Port',
            self::EventId => 'Event ID',
            self::Attack => 'Attack',
            self::Scenario => 'Scenario',
            self::Ioc => 'IOC',
            self::Principle => 'Principle',
            self::Cloze => 'Cloze',
        };
    }
}
