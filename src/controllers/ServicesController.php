<?php

namespace vaersaagod\consentmate\controllers;

use Craft;
use craft\web\Controller;
use vaersaagod\consentmate\ConsentMate;
use yii\web\Response;

/**
 * The services index, and asking everyone for consent again
 *
 * Editing services goes through Craft's own `elements/edit` and
 * `elements/create` actions.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ServicesController extends Controller
{
    // Const Properties
    // =========================================================================

    /**
     * The permission to manage services and ask everyone again.
     */
    public const PERMISSION_MANAGE_SERVICES = 'consentmate:manage-services';

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

        $this->requirePermission(self::PERMISSION_MANAGE_SERVICES);

        return true;
    }

    /**
     * Renders the services index.
     *
     * @return Response
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIndex(): Response
    {
        $plugin = ConsentMate::getInstance();

        return $this->renderTemplate('consentmate/_services/index.twig', [
            'currentRevision' => $plugin->getRevisions()->getCurrentRevision(),
            'presetGroups' => $plugin->getPresets()->getPresetsByCategory(Craft::$app->getSites()->getCurrentSite()->id),
        ]);
    }

    /**
     * Bumps the consent revision, so every visitor is asked again.
     *
     * @return Response|null
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionRenew(): ?Response
    {
        $this->requirePostRequest();

        $revision = ConsentMate::getInstance()->getRevisions()->bump('manual');

        return $this->asSuccess(Craft::t('consentmate', 'Everyone will be asked for consent again (revision {revision}).', [
            'revision' => $revision,
        ]));
    }
}
