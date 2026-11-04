<?php

namespace vaersaagod\consentmate\migrations;

use craft\db\Migration;

/**
 * Adds the cookie reports table
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class m261015_200000_add_cookie_reports extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Install::createCookieReportsTable($this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261015_200000_add_cookie_reports cannot be reverted.\n";

        return false;
    }
}
