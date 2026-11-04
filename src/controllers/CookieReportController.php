<?php

namespace vaersaagod\consentmate\controllers;

use Craft;
use craft\web\Controller;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\elements\Service;
use vaersaagod\consentmate\services\CookieReports;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * The cookie report: undeclared cookies and cookies set without consent
 *
 * Fixing what the report finds means editing services, so it shares the
 * services permission.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class CookieReportController extends Controller
{
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

        $this->requirePermission(ServicesController::PERMISSION_MANAGE_SERVICES);

        return true;
    }

    /**
     * Renders the report.
     *
     * @return Response
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIndex(): Response
    {
        $plugin = ConsentMate::getInstance();
        $showIgnored = (bool)$this->request->getQueryParam('ignored', false);
        $reports = $plugin->getCookieReports()->getReports($showIgnored);

        $services = [];
        $servicesByPreset = [];

        /** @var Service $service */
        foreach (Service::find()->status(null)->orderBy(['elements_sites.title' => SORT_ASC])->all() as $service) {
            $services[] = ['label' => (string)$service->title, 'value' => $service->id];

            if ($service->getPreset() !== null) {
                $servicesByPreset[$service->preset] ??= $service->id;
            }
        }

        // Suggest the service that probably sets an undeclared cookie: an
        // existing one made from a matching preset, or the preset itself
        foreach ($reports as &$report) {
            $preset = $report['status'] === 'undeclared'
                ? $plugin->getPresets()->findByCookieName($report['name'])
                : null;

            $report['preset'] = $preset;
            $report['suggestedServiceId'] = $preset !== null ? ($servicesByPreset[$preset->handle] ?? null) : null;
        }
        unset($report);

        return $this->renderTemplate('consentmate/_report/index.twig', [
            'leaks' => array_values(array_filter($reports, static fn(array $report) => $report['status'] === 'leak')),
            'undeclared' => array_values(array_filter($reports, static fn(array $report) => $report['status'] === 'undeclared')),
            'serviceElementType' => Service::class,
            'showIgnored' => $showIgnored,
            'services' => $services,
            'enabled' => $plugin->getSettings()->getCookieReport()['enabled'],
            'sitesAtLimit' => array_values(array_filter(array_map(
                static fn(int $siteId) => Craft::$app->getSites()->getSiteById($siteId, true)?->getName(),
                $plugin->getCookieReports()->getSiteIdsAtLimit()
            ))),
            'limit' => CookieReports::MAX_REPORTS_PER_SITE,
        ]);
    }

    /**
     * Ignores a report, or stops ignoring it.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionIgnore(): ?Response
    {
        $this->requirePostRequest();

        $id = (int)$this->request->getRequiredBodyParam('id');
        $ignored = (bool)$this->request->getBodyParam('ignored', true);

        ConsentMate::getInstance()->getCookieReports()->setIgnored($id, $ignored);

        return $this->asSuccess($ignored
            ? Craft::t('consentmate', 'The cookie is ignored.')
            : Craft::t('consentmate', 'The cookie is no longer ignored.'));
    }

    /**
     * Deletes a report. It comes back if the cookie is seen again.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        ConsentMate::getInstance()->getCookieReports()->delete((int)$this->request->getRequiredBodyParam('id'));

        return $this->asSuccess(Craft::t('consentmate', 'Report cleared.'));
    }

    /**
     * Declares an undeclared cookie on a service, then goes to the service so
     * its duration can be filled in.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionAddToService(): ?Response
    {
        $this->requirePostRequest();

        $id = (int)$this->request->getRequiredBodyParam('id');
        $serviceId = (int)$this->request->getRequiredBodyParam('serviceId');

        try {
            $service = ConsentMate::getInstance()->getCookieReports()->addToService($id, $serviceId);
        } catch (InvalidArgumentException $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess(
            Craft::t('consentmate', 'The cookie was added to {service}. Fill in how long it lasts.', [
                'service' => $service->title,
            ]),
            redirect: $service->getCpEditUrl(),
        );
    }
}
