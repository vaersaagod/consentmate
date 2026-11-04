<?php

namespace vaersaagod\consentmate\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\web\User as WebUser;
use vaersaagod\consentmate\helpers\DurationHelper;

/**
 * ConsentMate settings
 *
 * All settings are developer settings, set in `config/consentmate.php`.
 * Client-facing content (services, texts) lives in the control panel.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Settings extends Model
{
    // Const Properties
    // =========================================================================

    /**
     * The Google Consent Mode consent types.
     */
    public const GOOGLE_CONSENT_TYPES = [
        'ad_storage',
        'ad_user_data',
        'ad_personalization',
        'analytics_storage',
        'functionality_storage',
        'personalization_storage',
        'security_storage',
    ];

    // Public Properties
    // =========================================================================

    /**
     * The plugin's name in the control panel, e.g. "Consent". It's
     * translated with the `consentmate` category, so "Consent" shows as
     * "Samtykke" in a Norwegian control panel. Add your own name to a
     * project's translations/<language>/consentmate.php.
     *
     * @var string
     */
    public string $pluginName = 'ConsentMate';

    /**
     * Whether ConsentMate is enabled. A boolean, or an array keyed by site
     * handle with an optional '*' fallback.
     *
     * @var bool|array
     */
    public bool|array $enabled = true;

    /**
     * The consent categories, in display order, keyed by handle.
     *
     * - `required`: the category can't be refused (only for strictly necessary)
     * - `google`: the Google Consent Mode types granted with the category
     *
     * Names and descriptions are client copy, edited per site in the CP.
     *
     * @var array
     */
    public array $categories = [
        'necessary' => [
            'required' => true,
            'google' => ['security_storage'],
        ],
        'preferences' => [
            'google' => ['functionality_storage', 'personalization_storage'],
        ],
        'statistics' => [
            'google' => ['analytics_storage'],
        ],
        'marketing' => [
            'google' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        ],
    ];

    /**
     * How long a consent that grants at least one optional category is kept.
     * Seconds, or a DateInterval duration string.
     *
     * @var mixed
     */
    public mixed $consentLifetime = 'P12M';

    /**
     * How long a refusal (only required categories) is kept before the
     * visitor is asked again. Seconds, or a DateInterval duration string.
     *
     * @var mixed
     */
    public mixed $refusalLifetime = 'P6M';

    /**
     * The name of the consent cookie.
     *
     * @var string
     */
    public string $cookieName = 'consentmate';

    /**
     * The consent cookie domain. Null for the current host only, or a parent
     * domain (e.g. '.example.com') to share consent across subdomains.
     *
     * @var string|null
     */
    public ?string $cookieDomain = null;

    /**
     * Google Consent Mode v2 settings. See `getGoogleConsentMode()` for the
     * defaults.
     *
     * @var array
     */
    public array $googleConsentMode = [];

    /**
     * A Google Tag Manager container that ConsentMate loads itself, after
     * consent. See `getGoogleTagManager()` for the defaults.
     *
     * @var array
     */
    public array $googleTagManager = [];

    /**
     * A Google tag (gtag.js) that ConsentMate loads itself, after consent.
     * See `getGoogleTag()` for the defaults.
     *
     * @var array
     */
    public array $googleTag = [];

    /**
     * Consent signal adapters for other vendors. Microsoft Clarity reads
     * Google Consent Mode and needs no adapter.
     *
     * - `microsoft`: Microsoft Advertising UET consent mode (`window.uetq`)
     * - `meta`: Meta Pixel consent API (`window.fbq`)
     *
     * @var array
     */
    public array $vendorSignals = [];

    /**
     * Global Privacy Control settings. See `getGpc()` for the defaults.
     *
     * @var array
     */
    public array $gpc = [];

    /**
     * The template for the banner and the settings dialog. Null for the
     * plugin's own template.
     *
     * @var string|null
     */
    public ?string $bannerTemplate = null;

    /**
     * The template for `craft.consentmate.declaration()`. Null for the
     * plugin's own template.
     *
     * @var string|null
     */
    public ?string $declarationTemplate = null;

    /**
     * The template for consent placeholders. Null for the plugin's own
     * template.
     *
     * @var string|null
     */
    public ?string $placeholderTemplate = null;

    /**
     * Whether to output the plugin's default banner CSS.
     *
     * @var bool
     */
    public bool $includeCss = true;

    /**
     * Whether to show a floating button that reopens the consent settings.
     *
     * @var bool
     */
    public bool $floatingButton = false;

    /**
     * Whether to reload the page after a visitor withdraws consent, so that
     * scripts that already ran are gone.
     *
     * @var bool
     */
    public bool $reloadOnWithdraw = true;

    /**
     * Whether enabling a new service, or moving a service to another
     * category, asks every visitor for consent again.
     *
     * @var bool
     */
    public bool $renewOnNewService = true;

    /**
     * Consent log settings. See `getLog()` for the defaults.
     *
     * @var array
     */
    public array $log = [];

    /**
     * The CSP nonce for the scripts ConsentMate outputs. Null to use
     * ToolMate's nonce when ToolMate's CSP is enabled, a string, or a
     * callable that returns a string.
     *
     * @var mixed
     */
    public mixed $cspNonce = null;

    /**
     * Cookies that are always declared under the built-in "This website"
     * service in the first required category. Each item takes the same keys
     * as a service's cookies (name, type, party, duration, unit), plus an
     * optional `purpose`, shown in the cookie table and translated with the
     * `site` category. Null declares ConsentMate's cookie and Craft's CSRF,
     * session and login cookies.
     *
     * @var array|null
     */
    public ?array $necessaryCookies = null;

    /**
     * The maximum number of consent saves per visitor (by IP) per minute.
     *
     * @var int
     */
    public int $rateLimit = 30;

    /**
     * Cookie report settings. See `getCookieReport()` for the defaults.
     *
     * @var array
     */
    public array $cookieReport = [];

    /**
     * Service presets to add, change or remove, keyed by handle. An array
     * adds a preset (or changes keys of a built-in one, e.g. its default
     * category); `false` removes a built-in preset. See the files in
     * `src/presets` for the shape.
     *
     * @var array<string, array|false>
     */
    public array $presets = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns a setting that can be a plain value or a per-site map with an
     * optional '*' fallback.
     *
     * @param string $name
     * @param string|null $siteHandle
     * @return mixed
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getLocalizedConfigSetting(string $name, ?string $siteHandle = null): mixed
    {
        $value = $this->$name;

        if (!\is_array($value)) {
            return $value;
        }

        $siteHandle ??= Craft::$app->getSites()->getCurrentSite()->handle;

        return $value[$siteHandle] ?? $value['*'] ?? null;
    }

    /**
     * Returns the normalized categories, keyed by handle.
     *
     * @return array<string, array{handle: string, required: bool, google: string[]}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->categories as $handle => $config) {
            $handle = (string)$handle;
            $categories[$handle] = [
                'handle' => $handle,
                'required' => (bool)($config['required'] ?? false),
                'google' => array_values(array_intersect($config['google'] ?? [], self::GOOGLE_CONSENT_TYPES)),
            ];
        }

        return $categories;
    }

    /**
     * Returns the handles of the categories that can't be refused.
     *
     * @return string[]
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getRequiredCategoryHandles(): array
    {
        return array_keys(array_filter($this->getCategories(), static fn(array $category) => $category['required']));
    }

    /**
     * Returns the consent lifetime in seconds.
     *
     * @return int
     * @throws \yii\base\InvalidConfigException
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getConsentLifetimeSeconds(): int
    {
        return ConfigHelper::durationInSeconds($this->consentLifetime);
    }

    /**
     * Returns the refusal lifetime in seconds.
     *
     * @return int
     * @throws \yii\base\InvalidConfigException
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getRefusalLifetimeSeconds(): int
    {
        return ConfigHelper::durationInSeconds($this->refusalLifetime);
    }

    /**
     * Returns the Google Consent Mode settings, merged with the defaults.
     *
     * - `enabled`: whether to output Consent Mode defaults and updates
     * - `mode`: 'basic' loads Google tags only after consent, 'advanced'
     *   loads them right away with everything denied (cookieless pings)
     * - `waitForUpdate`: milliseconds tags wait for a consent update
     * - `regions`: limit the denied defaults to these ISO 3166-2 regions
     *   (null for everywhere)
     * - `urlPassthrough`: pass ad click info through URLs when ad_storage
     *   is denied
     * - `adsDataRedaction`: redact ad click identifiers when ad_storage is
     *   denied
     *
     * @return array{enabled: bool, mode: string, waitForUpdate: int, regions: ?array, urlPassthrough: bool, adsDataRedaction: bool}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getGoogleConsentMode(): array
    {
        return array_merge([
            'enabled' => true,
            'mode' => 'basic',
            'waitForUpdate' => 500,
            'regions' => null,
            'urlPassthrough' => false,
            'adsDataRedaction' => true,
        ], $this->googleConsentMode);
    }

    /**
     * Returns the Google Tag Manager settings, merged with the defaults.
     *
     * - `id`: the container ID ('GTM-XXXXXX'), usually from App::env()
     * - `loadOn`: in basic mode, the container loads once any of these
     *   categories is granted
     *
     * @return array{id: ?string, loadOn: string[]}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getGoogleTagManager(): array
    {
        return array_merge([
            'id' => null,
            'loadOn' => ['statistics', 'marketing'],
        ], $this->googleTagManager);
    }

    /**
     * Returns the Google tag (gtag.js) settings, merged with the defaults.
     *
     * - `id`: a tag ID ('G-XXXXXX', 'AW-XXXXXX'), or an array of IDs
     * - `loadOn`: in basic mode, the tag loads once any of these categories
     *   is granted
     *
     * @return array{id: string|string[]|null, loadOn: string[]}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getGoogleTag(): array
    {
        return array_merge([
            'id' => null,
            'loadOn' => ['statistics', 'marketing'],
        ], $this->googleTag);
    }

    /**
     * Returns the vendor signal settings, merged with the defaults.
     *
     * @return array{microsoft: bool, meta: bool}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getVendorSignals(): array
    {
        return array_merge([
            'microsoft' => false,
            'meta' => false,
        ], $this->vendorSignals);
    }

    /**
     * Returns the Global Privacy Control settings, merged with the defaults.
     *
     * - `enabled`: whether to honor the GPC signal
     * - `categories`: categories that "Accept all" doesn't grant while the
     *   signal is on (the visitor can still grant them one by one)
     *
     * @return array{enabled: bool, categories: string[]}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getGpc(): array
    {
        return array_merge([
            'enabled' => true,
            'categories' => ['marketing'],
        ], $this->gpc);
    }

    /**
     * Returns the consent log settings, merged with the defaults.
     *
     * - `enabled`: whether consents are logged
     * - `retention`: how long log rows are kept (seconds or a duration string)
     * - `userAgent`: whether to log the visitor's user agent
     * - `ipHash`: whether to log a keyed hash of the visitor's IP address
     *
     * @return array{enabled: bool, retention: mixed, userAgent: bool, ipHash: bool}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getLog(): array
    {
        return array_merge([
            'enabled' => true,
            'retention' => 'P24M',
            'userAgent' => true,
            'ipHash' => false,
        ], $this->log);
    }

    /**
     * Returns the cookie report settings, merged with the defaults.
     *
     * - `enabled`: whether to report cookies seen when visitors save consent
     * - `retention`: how long a report is kept after it was last seen
     * - `ignore`: cookie names (with * wildcards) never reported, like
     *   cookies set by the hosting
     *
     * @return array{enabled: bool, retention: mixed, ignore: string[]}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCookieReport(): array
    {
        return array_merge([
            'enabled' => true,
            'retention' => 'P90D',
            'ignore' => [],
        ], $this->cookieReport);
    }

    /**
     * Returns the cookies declared under the built-in "This website" service.
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getNecessaryCookies(): array
    {
        if ($this->necessaryCookies !== null) {
            return $this->necessaryCookies;
        }

        $generalConfig = Craft::$app->getConfig()->getGeneral();

        return [
            ['name' => $this->cookieName, 'type' => 'cookie', 'party' => 'first', 'purpose' => 'consent'] + DurationHelper::fromSeconds($this->getConsentLifetimeSeconds()),
            ['name' => $generalConfig->csrfTokenName, 'type' => 'cookie', 'party' => 'first', 'duration' => null, 'unit' => 'session', 'purpose' => 'csrf'],
            ['name' => $generalConfig->phpSessionName, 'type' => 'cookie', 'party' => 'first', 'duration' => null, 'unit' => 'session', 'purpose' => 'session'],
            $this->_identityCookie(),
        ];
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['cookieName'], 'required'];
        $rules[] = [['rateLimit'], 'integer', 'min' => 1];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns Craft's login cookie. It's only set when someone logs in, and
     * its name is derived from the environment ID, so it's read from Craft's
     * own user component config. It lasts as long as the longest login.
     *
     * @return array
     */
    private function _identityCookie(): array
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $name = App::userConfig()['identityCookie']['name'] ?? null;
        $seconds = max((int)$generalConfig->userSessionDuration, (int)$generalConfig->rememberedUserSessionDuration);

        return [
            'name' => $name ?: md5('Craft.' . WebUser::class . '.' . Craft::$app->getEnvId()) . '_identity',
            'type' => 'cookie',
            'party' => 'first',
            'purpose' => 'login',
        ] + ($seconds > 0 ? DurationHelper::fromSeconds($seconds) : ['duration' => null, 'unit' => 'session']);
    }
}
