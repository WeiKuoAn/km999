<?php

namespace App\Support;

final class PricingGroup
{
    public const CORE = 'core';

    public const HUMANITIES = 'humanities';

    public const RESEARCH = 'research';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::CORE, self::HUMANITIES, self::RESEARCH, self::OTHER];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CORE => '核心科（英／數／理）',
            self::HUMANITIES => '國社生',
            self::RESEARCH => '理化科研',
            self::OTHER => '其他',
        ];
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return '未設定';
        }

        return self::labels()[$key] ?? $key;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
