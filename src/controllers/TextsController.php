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

        $service = ConsentMate::getInstance()->getTexts();
        $values = $service->getTexts($site->id);
        $categories = $service->getCategories($site->id);

        // What was posted when a save failed. Read from the route params set
        // by actionSave(), not from action params, which the query string
        // could fill with someone else's copy.
        $routeParams = Craft::$app->getUrlManager()->getRouteParams();
        $texts = $routeParams['texts'] ?? null;
        $errors = $routeParams['errors'] ?? [];

        if (\is_array($texts)) {
            foreach ($values as $key => $value) {
                if ($key !== 'categories' && isset($texts[$key]) && \is_string($texts[$key])) {
                    $values[$key] = $texts[$key];
                }
            }

            foreach ($categories as $handle => $category) {
                foreach (['name', 'description'] as $key) {
                    $posted = $texts['categories'][$handle][$key] ?? null;

                    if (\is_string($posted)) {
                        $categories[$handle][$key] = $posted;
                    }
                }
            }
        }

        return $this->renderTemplate('consentmate/_texts/index.twig', [
            'site' => $site,
            'fieldDefinitions' => $service->getFieldDefinitions(),
            'texts' => $values,
            'errors' => \is_array($errors) ? $errors : [],
            'defaults' => $service->getDefaults($site->language),
            'categories' => $categories,
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
        $texts = \is_array($texts) ? $texts : [];
        $service = ConsentMate::getInstance()->getTexts();
        $errors = $service->validateTexts($texts);

        if (!empty($errors)) {
            return $this->asFailure(Craft::t('consentmate', 'Couldn\'t save the texts.'), routeParams: [
                'texts' => $texts,
                'errors' => $errors,
            ]);
        }

        $service->saveTexts($siteId, $texts);

        return $this->asSuccess(Craft::t('consentmate', 'Texts saved.'));
    }
}
