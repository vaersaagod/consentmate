<?php

namespace vaersaagod\consentmate;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use vaersaagod\consentmate\controllers\ConsentsController;
use vaersaagod\consentmate\controllers\ServicesController;
use vaersaagod\consentmate\controllers\TextsController;
use vaersaagod\consentmate\elements\Service;
use vaersaagod\consentmate\models\Settings;
use vaersaagod\consentmate\services\Caches;
use vaersaagod\consentmate\services\Consents;
use vaersaagod\consentmate\services\CookieReports;
use vaersaagod\consentmate\services\Frontend;
use vaersaagod\consentmate\services\Presets;
use vaersaagod\consentmate\services\Revisions;
use vaersaagod\consentmate\services\ServiceElements;
use vaersaagod\consentmate\services\Snapshots;
use vaersaagod\consentmate\services\Texts;
use vaersaagod\consentmate\twigextensions\ConsentMateTwigExtension;
use vaersaagod\consentmate\variables\ConsentMateVariable;
use yii\base\Event;

/**
 * ConsentMate plugin
 *
 * @method static ConsentMate getInstance()
 * @method Settings getSettings()
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentMate extends Plugin
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.0.3';

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'caches' => Caches::class,
                'consents' => Consents::class,
                'cookieReports' => CookieReports::class,
                'frontend' => Frontend::class,
                'presets' => Presets::class,
                'revisions' => Revisions::class,
                'serviceElements' => ServiceElements::class,
                'snapshots' => Snapshots::class,
                'texts' => Texts::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // What editors see in the CP nav, the permissions and the plugin list.
        // Translated once the app is initialized, so it's in the user's language.
        $this->name = $this->getSettings()->pluginName;

        // Component types must be registered in every request context
        $this->_registerElementTypes();
        $this->_registerServiceEvents();
        $this->_registerGarbageCollection();

        Craft::$app->getView()->registerTwigExtension(new ConsentMateTwigExtension());

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('consentmate', ConsentMateVariable::class);
            }
        );

        Craft::$app->onInit(function(): void {
            $this->name = Craft::t('consentmate', $this->getSettings()->pluginName);

            $request = Craft::$app->getRequest();

            if ($request->getIsConsoleRequest()) {
                return;
            }

            if ($request->getIsCpRequest()) {
                $this->_registerCpUrlRules();
                $this->_registerPermissions();

                return;
            }

            $this->_registerSiteTemplateRoot();
            $this->_registerSiteHooks();
        });
    }

    /**
     * @return Consents
     * @throws \yii\base\InvalidConfigException
     */
    public function getConsents(): Consents
    {
        /** @var Consents */
        return $this->get('consents');
    }

    /**
     * @return CookieReports
     * @throws \yii\base\InvalidConfigException
     */
    public function getCookieReports(): CookieReports
    {
        /** @var CookieReports */
        return $this->get('cookieReports');
    }

    /**
     * @return Frontend
     * @throws \yii\base\InvalidConfigException
     */
    public function getFrontend(): Frontend
    {
        /** @var Frontend */
        return $this->get('frontend');
    }

    /**
     * @return Snapshots
     * @throws \yii\base\InvalidConfigException
     */
    public function getSnapshots(): Snapshots
    {
        /** @var Snapshots */
        return $this->get('snapshots');
    }

    /**
     * @return Caches
     * @throws \yii\base\InvalidConfigException
     */
    public function getCaches(): Caches
    {
        /** @var Caches */
        return $this->get('caches');
    }

    /**
     * @return Presets
     * @throws \yii\base\InvalidConfigException
     */
    public function getPresets(): Presets
    {
        /** @var Presets */
        return $this->get('presets');
    }

    /**
     * @return Revisions
     * @throws \yii\base\InvalidConfigException
     */
    public function getRevisions(): Revisions
    {
        /** @var Revisions */
        return $this->get('revisions');
    }

    /**
     * @return ServiceElements
     * @throws \yii\base\InvalidConfigException
     */
    public function getServiceElements(): ServiceElements
    {
        /** @var ServiceElements */
        return $this->get('serviceElements');
    }

    /**
     * @return Texts
     * @throws \yii\base\InvalidConfigException
     */
    public function getTexts(): Texts
    {
        /** @var Texts */
        return $this->get('texts');
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $user = Craft::$app->getUser();
        $subnav = [];

        $issues = 0;

        if ($user->checkPermission(ServicesController::PERMISSION_MANAGE_SERVICES)) {
            $subnav['services'] = ['label' => Craft::t('consentmate', 'Services'), 'url' => 'consentmate/services'];

            if ($this->getSettings()->getCookieReport()['enabled']) {
                $issues = $this->getCookieReports()->getIssueCount();
                $subnav['report'] = ['label' => Craft::t('consentmate', 'Cookie report'), 'url' => 'consentmate/report'];

                if ($issues > 0) {
                    $subnav['report']['badgeCount'] = $issues;
                }
            }
        }

        if ($user->checkPermission(TextsController::PERMISSION_MANAGE_TEXTS)) {
            $subnav['texts'] = ['label' => Craft::t('consentmate', 'Texts'), 'url' => 'consentmate/texts'];
        }

        if ($user->checkPermission(ConsentsController::PERMISSION_VIEW_CONSENTS)) {
            $subnav['consents'] = ['label' => Craft::t('consentmate', 'Consent log'), 'url' => 'consentmate/consents'];
        }

        if (empty($subnav)) {
            return null;
        }

        // The item's URL stays `consentmate` (the default), so Craft keeps the
        // section selected, and the subnav open, on every page under it. The
        // `consentmate` route sends users to the first page they can access.
        $item = parent::getCpNavItem();
        $item['subnav'] = $subnav;

        if ($issues > 0) {
            $item['badgeCount'] = $issues;
        }

        return $item;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers the Service element type.
     *
     * @return void
     */
    private function _registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = Service::class;
            }
        );
    }

    /**
     * Reacts to service and site changes.
     *
     * @return void
     */
    private function _registerServiceEvents(): void
    {
        Event::on(
            Service::class,
            Element::EVENT_BEFORE_SAVE,
            function(ModelEvent $event): void {
                /** @var Service $service */
                $service = $event->sender;
                $this->getServiceElements()->beforeSave($service);
            }
        );

        Event::on(
            Service::class,
            Element::EVENT_AFTER_PROPAGATE,
            function(ModelEvent $event): void {
                /** @var Service $service */
                $service = $event->sender;
                $this->getServiceElements()->afterPropagate($service);
            }
        );

        Event::on(
            Service::class,
            Element::EVENT_AFTER_RESTORE,
            function(Event $event): void {
                /** @var Service $service */
                $service = $event->sender;
                $this->getServiceElements()->afterRestore($service);
            }
        );

        Event::on(
            Service::class,
            Element::EVENT_AFTER_DELETE,
            function(Event $event): void {
                /** @var Service $service */
                $service = $event->sender;
                $this->getServiceElements()->afterDelete($service);
            }
        );

        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_SAVE_SITE,
            function(): void {
                $this->getCaches()->changed();
            }
        );
    }

    /**
     * Deletes expired consent log rows and stale cookie reports when Craft
     * collects garbage.
     *
     * @return void
     */
    private function _registerGarbageCollection(): void
    {
        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            function(): void {
                $this->getConsents()->purgeExpired();
                $this->getCookieReports()->purgeStale();
            }
        );
    }

    /**
     * Makes the front-end templates available in site mode, as
     * `consentmate/_frontend/*`.
     *
     * @return void
     */
    private function _registerSiteTemplateRoot(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event): void {
                $event->roots['consentmate'] = $this->getBasePath() . '/templates';
            }
        );
    }

    /**
     * Registers the `consentmateHead` template hook, and warns in dev mode
     * when a page is rendered without it.
     *
     * @return void
     */
    private function _registerSiteHooks(): void
    {
        Craft::$app->getView()->hook('consentmateHead', function(): string {
            return $this->getFrontend()->renderHead();
        });

        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event): void {
                $frontend = $this->getFrontend();

                if ($frontend->getHeadRendered() || !$frontend->isEnabled() || !str_contains($event->output, '</head>')) {
                    return;
                }

                Craft::warning("The page template \"$event->template\" doesn't output ConsentMate's head. Add {% hook 'consentmateHead' %} first in <head>.", __METHOD__);
            }
        );
    }

    /**
     * Registers the CP routes.
     *
     * @return void
     */
    private function _registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function(RegisterUrlRulesEvent $event): void {
                $event->rules['consentmate'] = 'consentmate/default/index';
                $event->rules['consentmate/services'] = 'consentmate/services/index';
                $event->rules['consentmate/services/<elementId:\d+>'] = 'elements/edit';
                $event->rules['consentmate/report'] = 'consentmate/cookie-report/index';
                $event->rules['consentmate/texts'] = 'consentmate/texts/index';
                $event->rules['consentmate/consents'] = 'consentmate/consents/index';
                $event->rules['consentmate/consents/<consentId:[0-9a-f\-]{36}>'] = 'consentmate/consents/view';
            }
        );
    }

    /**
     * Registers the user permissions.
     *
     * @return void
     */
    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => $this->name,
                    'permissions' => [
                        ServicesController::PERMISSION_MANAGE_SERVICES => [
                            'label' => Craft::t('consentmate', 'Manage services'),
                        ],
                        TextsController::PERMISSION_MANAGE_TEXTS => [
                            'label' => Craft::t('consentmate', 'Edit texts'),
                        ],
                        ConsentsController::PERMISSION_VIEW_CONSENTS => [
                            'label' => Craft::t('consentmate', 'View the consent log'),
                        ],
                    ],
                ];
            }
        );
    }
}
