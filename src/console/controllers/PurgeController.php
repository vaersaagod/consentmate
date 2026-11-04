<?php

namespace vaersaagod\consentmate\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use vaersaagod\consentmate\ConsentMate;
use yii\console\ExitCode;

/**
 * Deletes consent log rows past their retention, and stale cookie reports
 *
 * Craft's garbage collection does the same, but only runs on a small share of
 * web requests. Run this from cron (e.g. daily) so the log retention holds.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class PurgeController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Deletes consent log rows past their retention, and stale cookie reports.
     *
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIndex(): int
    {
        $plugin = ConsentMate::getInstance();

        $consents = $plugin->getConsents()->purgeExpired();
        $this->stdout("Deleted $consents consent log rows past their retention." . PHP_EOL, Console::FG_GREEN);

        $reports = $plugin->getCookieReports()->purgeStale();
        $this->stdout("Deleted $reports stale cookie reports." . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }
}
