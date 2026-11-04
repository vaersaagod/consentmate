<?php

namespace vaersaagod\consentmate\controllers;

use Craft;
use craft\web\Controller;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\services\Consents;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The consent log in the CP
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentsController extends Controller
{
    // Const Properties
    // =========================================================================

    /**
     * The permission to view the consent log.
     */
    public const PERMISSION_VIEW_CONSENTS = 'consentmate:view-consents';

    /**
     * Rows per page.
     */
    private const PER_PAGE = 50;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(self::PERMISSION_VIEW_CONSENTS);

        return true;
    }

    /**
     * Renders the log, newest first, with a summary of the last 30 days.
     *
     * @return Response
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIndex(): Response
    {
        $plugin = ConsentMate::getInstance();
        $search = trim((string)$this->request->getQueryParam('q', ''));
        // Not `action`, which Craft treats as a controller route
        $action = (string)$this->request->getQueryParam('type', '');
        $page = max(1, (int)$this->request->getQueryParam('page', 1));

        $log = $plugin->getConsents()->getLogPage([
            'search' => $search,
            'action' => $action,
        ], $page, self::PER_PAGE);

        return $this->renderTemplate('consentmate/_consents/index.twig', [
            'rows' => $log['rows'],
            'total' => $log['total'],
            'page' => $page,
            'totalPages' => max(1, (int)ceil($log['total'] / self::PER_PAGE)),
            'search' => $search,
            'selectedAction' => \in_array($action, Consents::ACTIONS, true) ? $action : '',
            'actions' => Consents::ACTIONS,
            'summary' => $plugin->getConsents()->getSummary(30),
            'logSettings' => $plugin->getSettings()->getLog(),
            'revisions' => $plugin->getRevisions()->getRecentRevisions(10),
        ]);
    }

    /**
     * Renders every logged consent for a consent ID, with the snapshot of
     * what the visitor was shown each time.
     *
     * @param string $consentId
     * @return Response
     * @throws NotFoundHttpException
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionView(string $consentId): Response
    {
        $plugin = ConsentMate::getInstance();
        $history = $plugin->getConsents()->getHistory($consentId);

        if (empty($history)) {
            throw new NotFoundHttpException('Consent not found.');
        }

        $snapshots = [];

        foreach ($history as $row) {
            $snapshotId = (int)($row['snapshotId'] ?? 0);

            if ($snapshotId && !isset($snapshots[$snapshotId])) {
                $snapshots[$snapshotId] = $plugin->getSnapshots()->getDataById($snapshotId);
            }
        }

        return $this->renderTemplate('consentmate/_consents/view.twig', [
            'consentId' => strtolower($consentId),
            'history' => $history,
            'snapshots' => $snapshots,
            'sites' => Craft::$app->getSites(),
        ]);
    }
}
