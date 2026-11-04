<?php

namespace vaersaagod\consentmate\controllers;

use craft\helpers\UrlHelper;
use craft\web\Controller;
use vaersaagod\consentmate\ConsentMate;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * The ConsentMate section's landing route
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Redirects to the first page in the section the user can access.
     *
     * @return Response
     * @throws ForbiddenHttpException if the user can't access any of them
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIndex(): Response
    {
        $subnav = ConsentMate::getInstance()->getCpNavItem()['subnav'] ?? [];

        if (empty($subnav)) {
            throw new ForbiddenHttpException('User not authorized to access ConsentMate.');
        }

        return $this->redirect(UrlHelper::cpUrl(reset($subnav)['url']));
    }
}
