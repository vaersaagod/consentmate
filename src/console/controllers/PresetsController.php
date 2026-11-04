<?php

namespace vaersaagod\consentmate\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use vaersaagod\consentmate\ConsentMate;
use yii\console\ExitCode;

/**
 * Brings services' cookies in step with their presets
 *
 * Built-in presets are applied by the plugin's migrations when it's updated.
 * Run this after changing a project's own presets in the `presets` setting.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.4
 */
class PresetsController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool Whether to only report what would change
     */
    public bool $dryRun = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'dryRun';

        return $options;
    }

    /**
     * Updates the cookies of services made from a preset. Cookies the client
     * added, changed or deleted are kept as they are.
     *
     * @return int
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.4
     */
    public function actionSync(): int
    {
        $results = ConsentMate::getInstance()->getPresets()->syncServices($this->dryRun);

        if (empty($results)) {
            $this->stdout('All services are up to date with their presets.' . PHP_EOL, Console::FG_GREEN);

            return ExitCode::OK;
        }

        $failed = false;

        foreach ($results as $result) {
            $lines = [];

            foreach (['added' => 'Added', 'changed' => 'Changed', 'removed' => 'Removed'] as $key => $label) {
                if (!empty($result[$key])) {
                    $lines[] = "  $label: " . implode(', ', $result[$key]);
                }
            }

            $status = match (true) {
                $this->dryRun => 'would update',
                $result['saved'] => 'updated',
                default => 'COULD NOT BE SAVED',
            };

            $failed = $failed || (!$this->dryRun && !$result['saved']);

            $this->stdout("{$result['service']->title} (#{$result['service']->id}) $status" . PHP_EOL, $result['saved'] || $this->dryRun ? Console::FG_GREEN : Console::FG_RED);
            $this->stdout(implode(PHP_EOL, $lines) . PHP_EOL);
        }

        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
