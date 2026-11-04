<?php

namespace vaersaagod\consentmate\migrations;

use craft\db\Migration;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;
use vaersaagod\consentmate\elements\Service;

/**
 * Adds the columns that keep services' cookies in step with their presets,
 * and applies this release's preset corrections
 *
 * Services made from a preset before this get the cookies the preset had in
 * 1.0.0 to 1.0.3 as the ones last applied. The sync then brings them up to
 * date, keeping whatever the client changed.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.4
 */
class m261104_120000_add_preset_cookies extends Migration
{
    // Const Properties
    // =========================================================================

    /**
     * The cookies of the presets this release corrects, as 1.0.0 to 1.0.3 had
     * them. The other presets haven't changed since 1.0.0.
     */
    private const PREVIOUS_COOKIES = [
        'facebook' => [
            ['name' => 'fr', 'type' => 'cookie', 'party' => 'third', 'duration' => 3, 'unit' => 'months'],
            ['name' => 'datr', 'type' => 'cookie', 'party' => 'third', 'duration' => 2, 'unit' => 'years'],
            ['name' => 'sb', 'type' => 'cookie', 'party' => 'third', 'duration' => 2, 'unit' => 'years'],
            ['name' => 'c_user', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
            ['name' => 'xs', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ],
        'instagram' => [
            ['name' => 'csrftoken', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
            ['name' => 'mid', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
            ['name' => 'ig_did', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
            ['name' => 'datr', 'type' => 'cookie', 'party' => 'third', 'duration' => 2, 'unit' => 'years'],
        ],
        'linkedinInsight' => [
            ['name' => 'li_adsId', 'type' => 'cookie', 'party' => 'first', 'duration' => 180, 'unit' => 'days'],
            ['name' => 'li_adsId', 'type' => 'localStorage', 'party' => 'first', 'unit' => 'persistent'],
            ['name' => 'li_fat_id', 'type' => 'cookie', 'party' => 'first', 'duration' => 30, 'unit' => 'days'],
            ['name' => 'li_giant', 'type' => 'cookie', 'party' => 'first', 'duration' => 7, 'unit' => 'days'],
            ['name' => 'bcookie', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
            ['name' => 'lidc', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'days'],
            ['name' => 'li_sugr', 'type' => 'cookie', 'party' => 'third', 'duration' => 90, 'unit' => 'days'],
            ['name' => 'UserMatchHistory', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'days'],
            ['name' => 'AnalyticsSyncHistory', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'days'],
            ['name' => 'li_gc', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ],
        'typeform' => [
            ['name' => 'tf_respondent_cc', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
            ['name' => 'attribution_user_id', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
            ['name' => 'AWSALBTGCORS', 'type' => 'cookie', 'party' => 'third', 'duration' => 7, 'unit' => 'days'],
            ['name' => 'tf_random_id', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
            ['name' => 'rl_*', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
            ['name' => 'rudder.*', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ],
    ];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Install::addPresetCookieColumns($this);

        $plugin = ConsentMate::getInstance();
        $configPresets = $plugin->getSettings()->presets;

        foreach ($plugin->getPresets()->getAllPresets() as $handle => $preset) {
            // A project that sets the preset's cookies in its config had those
            $previous = isset(self::PREVIOUS_COOKIES[$handle]) && !isset($configPresets[$handle]['cookies'])
                ? Service::normalizeCookies(self::PREVIOUS_COOKIES[$handle])
                : $preset->cookies;

            // JSON columns are encoded by the query builder, so pass arrays
            $this->update(Table::SERVICES, ['presetCookies' => $previous], [
                'preset' => $handle,
                'presetCookies' => null,
            ], [], false);
        }

        $plugin->getPresets()->syncServices();

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
