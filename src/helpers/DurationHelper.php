<?php

namespace vaersaagod\consentmate\helpers;

use Craft;

/**
 * Converts and labels cookie durations
 *
 * Cookie durations are stored as a value and a unit, so declarations can be
 * rendered in the language of each site without translating them by hand.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class DurationHelper
{
    // Const Properties
    // =========================================================================

    /**
     * The duration units, with their (approximate) length in seconds.
     * 'session' and 'persistent' have no length.
     */
    public const UNITS = [
        'session' => null,
        'minutes' => 60,
        'hours' => 3600,
        'days' => 86400,
        'months' => 2629800,
        'years' => 31557600,
        'persistent' => null,
    ];

    // Public Methods
    // =========================================================================

    /**
     * Returns the largest whole unit for a number of seconds.
     *
     * @param int $seconds
     * @return array{duration: int, unit: string}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function fromSeconds(int $seconds): array
    {
        foreach (['years', 'months', 'days', 'hours'] as $unit) {
            $length = self::UNITS[$unit];
            $value = $seconds / $length;

            // Durations given as P12M or P13M come out a few days off, so round
            // within a small tolerance
            if ($value >= 0.95 && abs($value - round($value)) < 0.05) {
                return ['duration' => (int)round($value), 'unit' => $unit];
            }
        }

        return ['duration' => max(1, (int)round($seconds / 60)), 'unit' => 'minutes'];
    }

    /**
     * Returns the translated label for a duration, e.g. "13 months".
     *
     * @param int|string|null $duration
     * @param string|null $unit
     * @param string|null $language
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function label(int|string|null $duration, ?string $unit, ?string $language = null): string
    {
        $params = ['num' => (int)$duration];

        return match ($unit) {
            'session' => Craft::t('consentmate', 'Session', [], $language),
            'persistent' => Craft::t('consentmate', 'Persistent', [], $language),
            'minutes' => Craft::t('consentmate', '{num, plural, =1{1 minute} other{# minutes}}', $params, $language),
            'hours' => Craft::t('consentmate', '{num, plural, =1{1 hour} other{# hours}}', $params, $language),
            'days' => Craft::t('consentmate', '{num, plural, =1{1 day} other{# days}}', $params, $language),
            'months' => Craft::t('consentmate', '{num, plural, =1{1 month} other{# months}}', $params, $language),
            'years' => Craft::t('consentmate', '{num, plural, =1{1 year} other{# years}}', $params, $language),
            default => '',
        };
    }

    /**
     * Returns the unit options for select inputs.
     *
     * @return array<int, array{label: string, value: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function unitOptions(): array
    {
        return [
            ['label' => Craft::t('consentmate', 'Session'), 'value' => 'session'],
            ['label' => Craft::t('consentmate', 'Minutes'), 'value' => 'minutes'],
            ['label' => Craft::t('consentmate', 'Hours'), 'value' => 'hours'],
            ['label' => Craft::t('consentmate', 'Days'), 'value' => 'days'],
            ['label' => Craft::t('consentmate', 'Months'), 'value' => 'months'],
            ['label' => Craft::t('consentmate', 'Years'), 'value' => 'years'],
            ['label' => Craft::t('consentmate', 'Persistent'), 'value' => 'persistent'],
        ];
    }
}
