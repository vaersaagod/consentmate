<?php

namespace vaersaagod\consentmate\migrations;

use craft\db\Migration;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;

/**
 * Adds the columns that keep services' cookies in step with their presets
 *
 * Services made from a preset before this get the preset's current cookies as
 * the ones last applied. The presets haven't changed since they were
 * released, so that's what these services were created with.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.4
 */
class m261104_120000_add_preset_cookies extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Install::addPresetCookieColumns($this);

        foreach (ConsentMate::getInstance()->getPresets()->getAllPresets() as $handle => $preset) {
            // JSON columns are encoded by the query builder, so pass arrays
            $this->update(Table::SERVICES, ['presetCookies' => $preset->cookies], [
                'preset' => $handle,
                'presetCookies' => null,
            ], [], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261104_120000_add_preset_cookies cannot be reverted.\n";

        return false;
    }
}
