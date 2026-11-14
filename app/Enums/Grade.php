<?php

namespace App\Enums;

enum Grade: string
{
    case Again = 'again';
    case Hard = 'hard';
    case Good = 'good';
    case Easy = 'easy';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public static function fromLegacy(bool $remembered): self
    {
        return $remembered ? self::Good : self::Again;
    }

    public function remembered(): bool
    {
        return $this !== self::Again;
    }
}
