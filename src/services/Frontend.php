<?php

namespace vaersaagod\consentmate\services;

use Craft;
use craft\base\Component;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\View;
use Twig\Markup;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\elements\Service;
use vaersaagod\consentmate\helpers\DurationHelper;
use vaersaagod\consentmate\models\Settings;
use vaersaagod\consentmate\web\assets\frontend\FrontendAsset;

/**
 * Renders everything ConsentMate outputs on the front end
 *
 * The output is the same for every visitor: consent is read and applied in
 * the browser, so pages can be statically cached. Nothing here sets a cookie
 * or starts a session.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Frontend extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * The URL of Google's page on how it uses data from sites, which Google
     * asks sites using its ad products to link to.
     */
    public const GOOGLE_PRIVACY_URL = 'https://business.safety.google/privacy/';

    // Private Properties
    // =========================================================================

    /**
     * @var bool Whether the head has been rendered in this request
     */
    private bool $_headRendered = false;

    /**
     * @var string|false|null The CSP nonce, memoized per request
     */
    private string|false|null $_nonce = null;

    /**
     * @var string|null A hash of the config the cached site data depends on
     */
    private ?string $_configFingerprint = null;

    // Public Methods
    // =========================================================================

    /**
     * Returns whether ConsentMate is enabled for a site, on a site request.
     *
     * @param Site|null $site
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function isEnabled(?Site $site = null): bool
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || !$request->getIsSiteRequest()) {
            return false;
        }

        $site ??= Craft::$app->getSites()->getCurrentSite();

        return (bool)$this->_settings()->getLocalizedConfigSetting('enabled', $site->handle);
    }

    /**
     * Returns whether the head was rendered in this request.
     *
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getHeadRendered(): bool
    {
        return $this->_headRendered;
    }

    /**
     * Renders the `<head>` output: the inline bootstrap, the head code of
     * services, and the runtime and stylesheet. Registers the banner at the
     * start of `<body>` and the body code of services at the end.
     *
     * @return string
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function renderHead(): string
    {
        if ($this->_headRendered || !$this->isEnabled()) {
            return '';
        }

        $this->_headRendered = true;

        $site = Craft::$app->getSites()->getCurrentSite();
        $data = $this->getSiteData($site->id);
        $nonce = $this->getNonce();
        $nonceAttribute = $nonce ? ['nonce' => $nonce] : [];
        $view = Craft::$app->getView();

        $bootstrap = file_get_contents($this->_distPath('bootstrap.js'));
        $config = Json::htmlEncode($this->_clientConfig($site, $data, $nonce));

        $html = Html::tag('script', "window.ConsentMateConfig=$config;$bootstrap", $nonceAttribute) . "\n";
        $html .= $data['headHtml'];

        if ($this->_settings()->includeCss) {
            $html .= Html::cssFile($this->_distUrl('consentmate.css')) . "\n";
        }

        $html .= Html::jsFile($this->_distUrl('consentmate.js'), ['defer' => true] + $nonceAttribute) . "\n";

        $view->registerHtml($this->renderBanner($site), View::POS_BEGIN);

        if ($data['bodyHtml'] !== '') {
            $view->registerHtml($data['bodyHtml'], View::POS_END);
        }

        return $html;
    }

    /**
     * Renders the banner, the settings dialog and the floating button.
     *
     * @param Site $site
     * @return string
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function renderBanner(Site $site): string
    {
        $settings = $this->_settings();
        $data = $this->getSiteData($site->id);
        $gcm = $settings->getGoogleConsentMode();

        return $this->_renderSiteTemplate(
            $settings->bannerTemplate ?? 'consentmate/_frontend/banner.twig',
            [
                'texts' => $data['texts'],
                'categories' => $data['categories'],
                'showGoogleNotice' => $gcm['enabled'],
                'googlePrivacyUrl' => self::GOOGLE_PRIVACY_URL,
                'floatingButton' => $settings->floatingButton,
            ]
        );
    }

    /**
     * Gates markup behind a consent category. The markup is output inside an
     * inert `<template>`, and activated in the browser once the category is
     * granted.
     *
     * `$category` is a category handle, or the handle of a preset (like
     * 'youtube'): then the markup follows the category of the service made
     * from that preset, so the client can move it without template changes.
     *
     * Options:
     * - `placeholder`: true for the default placeholder, or a template path
     * - `provider`: the name used in the placeholder text (defaults to the
     *   service's name when gating by preset)
     *
     * @param string|null $html
     * @param string $category
     * @param array $options
     * @return Markup
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function gate(?string $html, string $category, array $options = []): Markup
    {
        $html = (string)$html;

        if (trim($html) === '' || !$this->isEnabled()) {
            return Template::raw($html);
        }

        // A preset handle gates on the category of the service made from it
        [$category, $serviceName] = $this->_resolveGateCategory($category);
        $options['provider'] ??= $serviceName;

        $categories = $this->_settings()->getCategories();

        if (isset($categories[$category]) && $categories[$category]['required']) {
            return Template::raw($html);
        }

        if (!isset($categories[$category])) {
            Craft::warning("Gated markup uses an unknown consent category \"$category\". It will never be shown.", __METHOD__);
        }

        $template = Html::tag('template', $html, ['data-consentmate' => $category]);
        $placeholder = $options['placeholder'] ?? false;

        if (!$placeholder) {
            return Template::raw($template);
        }

        $placeholderHtml = $this->renderPlaceholder($category, \is_string($placeholder) ? $placeholder : null, $options);

        return Template::raw(Html::tag('div', $template . $placeholderHtml, [
            'data-consentmate-gate' => true,
        ]));
    }

    /**
     * Renders a placeholder for gated content.
     *
     * @param string $category
     * @param string|null $template
     * @param array $options
     * @return string
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function renderPlaceholder(string $category, ?string $template = null, array $options = []): string
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        $data = $this->getSiteData($site->id);
        $categoryName = $data['categories'][$category]['name'] ?? $category;
        $provider = (string)($options['provider'] ?? '');

        $text = strtr($data['texts']['placeholderText'], [
            '{provider}' => $provider !== '' ? $provider : Craft::t('consentmate', 'a third party', [], $site->language),
            '{category}' => $categoryName,
        ]);

        return $this->_renderSiteTemplate(
            $template ?? $this->_settings()->placeholderTemplate ?? 'consentmate/_frontend/placeholder.twig',
            [
                'category' => $category,
                'categoryName' => $categoryName,
                'provider' => $provider,
                'text' => $text,
                'texts' => $data['texts'],
                'options' => $options,
            ]
        );
    }

    /**
     * Renders the cookie declaration for the current site.
     *
     * @param array $options Passed to the template as `options`
     * @return Markup
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function renderDeclaration(array $options = []): Markup
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        $data = $this->getSiteData($site->id);

        return Template::raw($this->_renderSiteTemplate(
            $options['template'] ?? $this->_settings()->declarationTemplate ?? 'consentmate/_frontend/declaration.twig',
            [
                'texts' => $data['texts'],
                'categories' => $data['categories'],
                'options' => $options,
            ]
        ));
    }

    /**
     * Returns everything the front end needs for a site, cached until
     * services, texts or the revision change. The cache key includes the
     * plugin version and a fingerprint of the config, so a deploy that only
     * changes config (like a renamed session cookie) isn't served stale data.
     *
     * @param int $siteId
     * @return array{revision: int, snapshot: string, texts: array, categories: array, headHtml: string, bodyHtml: string, cookies: array}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getSiteData(int $siteId): array
    {
        $plugin = ConsentMate::getInstance();

        return Craft::$app->getCache()->getOrSet(
            "consentmate:site-data:{$plugin->getVersion()}:{$this->getConfigFingerprint()}:$siteId",
            fn() => $this->_buildSiteData($siteId),
            null,
            $plugin->getCaches()->getDependency()
        );
    }

    /**
     * Returns the CSP nonce for ConsentMate's scripts, if any.
     *
     * @return string|null
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getNonce(): ?string
    {
        if ($this->_nonce !== null) {
            return $this->_nonce ?: null;
        }

        $setting = $this->_settings()->cspNonce;
        $nonce = null;

        if (\is_callable($setting)) {
            $nonce = $setting();
        } elseif (\is_string($setting) && $setting !== '') {
            $nonce = $setting;
        } elseif ($setting === null) {
            $nonce = $this->_toolMateNonce();
        }

        $this->_nonce = \is_string($nonce) && $nonce !== '' ? $nonce : false;

        return $this->_nonce ?: null;
    }

    /**
     * Returns a hash of the config that cached data is built from: the
     * plugin settings, and the Craft settings that name the built-in cookies.
     * Part of the cache keys, so config changes show right away.
     *
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getConfigFingerprint(): string
    {
        if ($this->_configFingerprint !== null) {
            return $this->_configFingerprint;
        }

        $generalConfig = Craft::$app->getConfig()->getGeneral();

        // cspNonce can be a closure, and is per request anyway
        $settings = array_diff_key($this->_settings()->toArray(), ['cspNonce' => true]);

        return $this->_configFingerprint = md5(Json::encode([
            $settings,
            $generalConfig->phpSessionName,
            $generalConfig->csrfTokenName,
            $generalConfig->userSessionDuration,
            $generalConfig->rememberedUserSessionDuration,
            Craft::$app->getEnvId(),
        ]));
    }

    // Private Methods
    // =========================================================================

    /**
     * Resolves what `gate()` was given to a category: a category handle as
     * is, or a preset handle to the category of the first live service made
     * from it. Without such a service, the preset's default category is used.
     *
     * @param string $handle
     * @return array{0: string, 1: ?string} The category, and the service name
     */
    private function _resolveGateCategory(string $handle): array
    {
        $plugin = ConsentMate::getInstance();

        if (isset($this->_settings()->getCategories()[$handle])) {
            return [$handle, null];
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        foreach ($plugin->getServiceElements()->getLiveServices($siteId) as $service) {
            if ($service['preset'] === $handle) {
                return [$service['category'], $service['title']];
            }
        }

        $preset = $plugin->getPresets()->getPreset($handle);

        if ($preset !== null) {
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                Craft::warning("Gated markup uses the \"$handle\" preset, but no live service on this site is made from it. Using the preset's default category, \"$preset->category\".", __METHOD__);
            }

            return [$preset->category, Craft::t('consentmate', $preset->name)];
        }

        return [$handle, null];
    }


    /**
     * @return Settings
     */
    private function _settings(): Settings
    {
        return ConsentMate::getInstance()->getSettings();
    }

    /**
     * Builds the front-end data for a site.
     *
     * @param int $siteId
     * @return array
     * @throws \yii\db\Exception
     * @throws \yii\base\InvalidConfigException
     */
    private function _buildSiteData(int $siteId): array
    {
        $plugin = ConsentMate::getInstance();
        $settings = $this->_settings();
        $site = Craft::$app->getSites()->getSiteById($siteId, true);
        $language = $site?->language;
        $texts = $plugin->getTexts()->getTexts($siteId);
        $categories = $plugin->getTexts()->getCategories($siteId);
        $services = $plugin->getServiceElements()->getLiveServices($siteId);
        $requiredHandles = $settings->getRequiredCategoryHandles();

        foreach ($categories as $handle => $category) {
            $categories[$handle]['services'] = [];
        }

        // The built-in "This website" service, in the first required category
        $firstRequired = $requiredHandles[0] ?? null;

        if ($firstRequired !== null) {
            $categories[$firstRequired]['services'][] = [
                'id' => null,
                'uid' => 'consentmate-website',
                'title' => Craft::t('consentmate', 'This website', [], $language),
                // The site name is rarely the company name, so this is left
                // out until the client fills it in
                'provider' => $texts['companyName'],
                'description' => Craft::t('consentmate', 'Cookies the website sets itself, which it needs to work: to remember your cookie choices, to protect forms against misuse, and to keep you logged in.', [], $language),
                'privacyPolicyUrl' => $texts['policyUrl'],
                'dataTransfers' => '',
                'cookies' => $this->_cookieRows($settings->getNecessaryCookies(), $language),
                'builtIn' => true,
            ];
        }

        $headHtml = '';
        $bodyHtml = '';
        $cookies = [];

        foreach ($services as $service) {
            $handle = $service['category'];

            if (!isset($categories[$handle])) {
                continue;
            }

            $categories[$handle]['services'][] = [
                'id' => $service['id'],
                'uid' => $service['uid'],
                'title' => $service['title'],
                'provider' => $service['provider'],
                'description' => $service['description'],
                'privacyPolicyUrl' => $service['privacyPolicyUrl'],
                'dataTransfers' => $service['dataTransfers'],
                'cookies' => $this->_cookieRows($service['cookies'], $language),
                'builtIn' => false,
            ];

            $isRequired = \in_array($handle, $requiredHandles, true);

            if ($service['headCode'] !== '') {
                $headHtml .= $this->_serviceCode($service['headCode'], $handle, $service['uid'], $isRequired);
            }

            if ($service['bodyCode'] !== '') {
                $bodyHtml .= $this->_serviceCode($service['bodyCode'], $handle, $service['uid'], $isRequired);
            }

            // First-party names are deleted when the category is withdrawn
            foreach ($service['cookies'] as $cookie) {
                if ($cookie['party'] === 'first' && $cookie['type'] !== 'pixel') {
                    $cookies[$handle][] = $cookie['name'];
                }
            }
        }

        $revision = $plugin->getRevisions()->getCurrentRevision();

        $snapshot = $plugin->getSnapshots()->ensure($siteId, [
            'revision' => $revision,
            'texts' => array_diff_key($texts, ['categories' => true]),
            'categories' => array_map(static fn(array $category) => [
                'handle' => $category['handle'],
                'required' => $category['required'],
                'name' => $category['name'],
                'description' => $category['description'],
                'services' => array_map(static fn(array $service) => [
                    'title' => $service['title'],
                    'provider' => $service['provider'],
                    'description' => $service['description'],
                    'privacyPolicyUrl' => $service['privacyPolicyUrl'],
                    'dataTransfers' => $service['dataTransfers'],
                    'cookies' => array_map(static fn(array $cookie) => [
                        'name' => $cookie['name'],
                        'type' => $cookie['type'],
                        'party' => $cookie['party'],
                        'duration' => $cookie['durationLabel'],
                        'purpose' => $cookie['purposeLabel'],
                    ], $service['cookies']),
                ], $category['services']),
            ], array_values($categories)),
        ]);

        return [
            'revision' => $revision,
            'snapshot' => $snapshot,
            'texts' => $texts,
            'categories' => $categories,
            'headHtml' => $headHtml,
            'bodyHtml' => $bodyHtml,
            'cookies' => array_map(static fn(array $names) => array_values(array_unique($names)), $cookies),
        ];
    }

    /**
     * Returns the config passed to the browser.
     *
     * @param Site $site
     * @param array $data
     * @param string|null $nonce
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    private function _clientConfig(Site $site, array $data, ?string $nonce): array
    {
        $settings = $this->_settings();
        $gcm = $settings->getGoogleConsentMode();
        $gpc = $settings->getGpc();
        $googleTags = [];

        foreach (['gtm' => $settings->getGoogleTagManager(), 'gtag' => $settings->getGoogleTag()] as $type => $tag) {
            $ids = array_values(array_filter(array_map('trim', (array)($tag['id'] ?? []))));

            if (!empty($ids)) {
                $googleTags[] = [
                    'type' => $type,
                    'ids' => $type === 'gtm' ? [$ids[0]] : $ids,
                    'loadOn' => array_values((array)$tag['loadOn']),
                ];
            }
        }

        $categories = [];

        foreach ($data['categories'] as $category) {
            $categories[] = [
                'handle' => $category['handle'],
                'required' => $category['required'],
                'google' => $category['google'],
                'name' => $category['name'],
            ];
        }

        return [
            'categories' => $categories,
            'googleTypes' => Settings::GOOGLE_CONSENT_TYPES,
            'cookie' => [
                'name' => $settings->cookieName,
                'domain' => $settings->cookieDomain,
                'lifetime' => $settings->getConsentLifetimeSeconds(),
                'refusalLifetime' => $settings->getRefusalLifetimeSeconds(),
            ],
            'revision' => $data['revision'],
            'snapshot' => $data['snapshot'],
            'gcm' => $gcm['enabled'] ? [
                'mode' => $gcm['mode'] === 'advanced' ? 'advanced' : 'basic',
                'waitForUpdate' => (int)$gcm['waitForUpdate'],
                'regions' => !empty($gcm['regions']) ? array_values((array)$gcm['regions']) : null,
                'urlPassthrough' => (bool)$gcm['urlPassthrough'],
                'adsDataRedaction' => (bool)$gcm['adsDataRedaction'],
            ] : null,
            'googleTags' => $googleTags,
            'signals' => $settings->getVendorSignals(),
            'gpc' => $gpc['enabled'] ? ['categories' => array_values((array)$gpc['categories'])] : null,
            'cookies' => $data['cookies'],
            'endpoint' => UrlHelper::rootRelativeUrl(UrlHelper::siteUrl(
                Craft::$app->getConfig()->getGeneral()->actionTrigger . '/consentmate/consent/save',
                null,
                null,
                $site->id
            )),
            'reload' => $settings->reloadOnWithdraw,
            'nonce' => $nonce,
        ];
    }

    /**
     * Returns a service's code, gated unless the category is required.
     *
     * @param string $code
     * @param string $category
     * @param string $uid
     * @param bool $isRequired
     * @return string
     */
    private function _serviceCode(string $code, string $category, string $uid, bool $isRequired): string
    {
        if ($isRequired) {
            return $code . "\n";
        }

        return Html::tag('template', $code, [
            'data-consentmate' => $category,
            'data-consentmate-service' => $uid,
        ]) . "\n";
    }

    /**
     * Adds translated labels to cookie rows.
     *
     * @param array $cookies
     * @param string|null $language
     * @return array
     */
    private function _cookieRows(array $cookies, ?string $language): array
    {
        $types = array_column(Service::cookieTypeOptions(), 'label', 'value');

        return array_map(static fn(array $cookie) => $cookie + [
            'typeLabel' => Craft::t('consentmate', $types[$cookie['type']] ?? 'Cookie', [], $language),
            'partyLabel' => $cookie['party'] === 'third'
                ? Craft::t('consentmate', 'Third party', [], $language)
                : Craft::t('consentmate', 'This website', [], $language),
            'durationLabel' => DurationHelper::label($cookie['duration'] ?? null, $cookie['unit'] ?? 'session', $language),
            'purposeLabel' => self::_purposeLabel($cookie['purpose'] ?? null, $language),
        ], $cookies);
    }

    /**
     * Returns the label for a cookie's purpose. The built-in cookies use
     * keys; anything else is translated with the `site` category.
     *
     * @param string|null $purpose
     * @param string|null $language
     * @return string
     */
    private static function _purposeLabel(?string $purpose, ?string $language): string
    {
        return match ($purpose) {
            null, '' => '',
            'consent' => Craft::t('consentmate', 'Remembers your cookie choices.', [], $language),
            'csrf' => Craft::t('consentmate', 'Protects forms against misuse. Set when a page has a form.', [], $language),
            'session' => Craft::t('consentmate', 'Keeps track of your visit, for example when you send a form or log in.', [], $language),
            'login' => Craft::t('consentmate', 'Keeps you logged in. Only set when you log in.', [], $language),
            default => Craft::t('site', $purpose, [], $language),
        };
    }

    /**
     * Renders a template in site mode.
     *
     * @param string $template
     * @param array $variables
     * @return string
     * @throws \Throwable
     */
    private function _renderSiteTemplate(string $template, array $variables): string
    {
        return Craft::$app->getView()->renderTemplate($template, $variables, View::TEMPLATE_MODE_SITE);
    }

    /**
     * Returns ToolMate's script-src nonce, when ToolMate is installed with
     * its CSP header enabled.
     *
     * @return string|null
     */
    private function _toolMateNonce(): ?string
    {
        $plugins = Craft::$app->getPlugins();

        if (!$plugins->isPluginEnabled('toolmate')) {
            return null;
        }

        try {
            // ToolMate isn't a dependency, so it's untyped here
            /** @var mixed $toolMate */
            $toolMate = $plugins->getPlugin('toolmate');

            if (!$toolMate->getSettings()->csp->enabled) {
                return null;
            }

            return $toolMate->csp->createNonce('script-src');
        } catch (\Throwable $e) {
            Craft::warning('Couldn\'t get a CSP nonce from ToolMate: ' . $e->getMessage(), __METHOD__);

            return null;
        }
    }

    /**
     * Returns the path to a built asset.
     *
     * @param string $file
     * @return string
     */
    private function _distPath(string $file): string
    {
        return \dirname(__DIR__) . '/web/assets/frontend/dist/' . $file;
    }

    /**
     * Returns the published URL of a built asset, through the asset bundle
     * so it works on Craft Cloud (where it's a CDN URL).
     *
     * @param string $file
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    private function _distUrl(string $file): string
    {
        $assetManager = Craft::$app->getAssetManager();
        $bundle = $assetManager->getBundle(FrontendAsset::class);

        // The asset manager appends a ?v= timestamp
        return $assetManager->getAssetUrl($bundle, $file);
    }
}
