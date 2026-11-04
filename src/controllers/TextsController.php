<?php

namespace vaersaagod\consentmate\controllers;

use Craft;
use craft\helpers\Cp;
use craft\web\Controller;
use vaersaagod\consentmate\ConsentMate;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Editing the banner and category copy, per site
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class TextsController extends Controller
{
    // Const Properties
    // =========================================================================

    /**
     * The permission to edit the banner and category copy.
     */
    public const PERMISSION_MANAGE_TEXTS = 'consentmate:manage-texts';

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

        $this->requirePermission(self::PERMISSION_MANAGE_TEXTS);

        return true;
    }

    /**
     * Renders the texts form for the requested site.
     *
     * @return Response
     * @throws ForbiddenHttpException
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIndex(): Response
    {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
        }

        $texts = ConsentMate::getInstance()->getTexts();

        return $this->renderTemplate('consentmate/_texts/index.twig', [
            'site' => $site,
            'fieldDefinitions' => $texts->getFieldDefinitions(),
            'texts' => $texts->getTexts($site->id),
            'defaults' => $texts->getDefaults($site->language),
            'categories' => $texts->getCategories($site->id),
        ]);
    }

    /**
     * Saves the texts for a site.
     *
     * @return Response|null
     * @throws ForbiddenHttpException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $siteId = (int)$this->request->getRequiredBodyParam('siteId');
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null || !\in_array($siteId, Craft::$app->getSites()->getEditableSiteIds(), true)) {
            throw new ForbiddenHttpException('User not authorized to edit content in this site.');
        }

        $texts = $this->request->getBodyParam('texts');

        ConsentMate::getInstance()->getTexts()->saveTexts($siteId, \is_array($texts) ? $texts : []);

        return $this->asSuccess(Craft::t('consentmate', 'Texts saved.'));
    }
}
