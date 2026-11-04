<?php

namespace vaersaagod\consentmate\migrations;

use craft\db\Migration;

/**
 * Adds the preset columns to the services table
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class m261028_120000_add_service_presets extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Install::addServicePresetColumns($this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261028_120000_add_service_presets cannot be reverted.\n";

        return false;
    }
}
