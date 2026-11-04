<?php

namespace vaersaagod\consentmate\events;

use yii\base\Event;

/**
 * Triggered when the service presets are collected. Add, change or remove
 * presets in `$presets` (arrays keyed by handle, in the same shape as the
 * files in `src/presets`).
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class RegisterPresetsEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var array<string, array> The presets, keyed by handle
     */
    public array $presets = [];
}
