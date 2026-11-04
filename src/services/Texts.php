<?php

namespace vaersaagod\consentmate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;

/**
 * Banner and category copy, per site
 *
 * Defaults come from the plugin's translation files, in the language of
 * each site. What the client saves in the CP overrides them.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Texts extends Component
{
    // Private Properties
    // =========================================================================

    /**
     * @var array<int, array> Merged texts, keyed by site ID
     */
    private array $_texts = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns the text fields shown in the CP, grouped.
     *
     * @return array<string, array{heading: string, fields: array<string, array{label: string, type: string, instructions?: string}>}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getFieldDefinitions(): array
    {
        return [
            'company' => [
                'heading' => Craft::t('consentmate', 'Company'),
                'fields' => [
                    'companyName' => [
                        'label' => Craft::t('consentmate', 'Company name'),
                        'type' => 'text',
                        'instructions' => Craft::t('consentmate', 'The company responsible for the website (the data controller), like "Fjord & Form AS". Shown as the provider of the cookies the website sets itself.'),
                    ],
                ],
            ],
            'banner' => [
                'heading' => Craft::t('consentmate', 'Banner'),
                'fields' => [
                    'bannerHeading' => ['label' => Craft::t('consentmate', 'Heading'), 'type' => 'text'],
                    'bannerBody' => ['label' => Craft::t('consentmate', 'Text'), 'type' => 'textarea'],
                    'googleNotice' => [
                        'label' => Craft::t('consentmate', 'Google notice'),
                        'type' => 'textarea',
                        'instructions' => Craft::t('consentmate', 'Shown in the banner when Google Consent Mode is on. Google requires sites that use Google Ads to say that data is shared with Google for ads personalization. A link to how Google uses data is added after the text.'),
                    ],
                    'policyLinkLabel' => ['label' => Craft::t('consentmate', 'Policy link text'), 'type' => 'text'],
                    'policyUrl' => [
                        'label' => Craft::t('consentmate', 'Policy link URL'),
                        'type' => 'text',
                        'instructions' => Craft::t('consentmate', 'The page with your privacy and cookie policy. The link is left out when this is empty.'),
                    ],
                    'acceptAll' => ['label' => Craft::t('consentmate', 'Accept all button'), 'type' => 'text'],
                    'rejectAll' => ['label' => Craft::t('consentmate', 'Reject all button'), 'type' => 'text'],
                    'openSettings' => ['label' => Craft::t('consentmate', 'Settings button'), 'type' => 'text'],
                ],
            ],
            'settings' => [
                'heading' => Craft::t('consentmate', 'Settings'),
                'fields' => [
                    'settingsHeading' => ['label' => Craft::t('consentmate', 'Heading'), 'type' => 'text'],
                    'settingsBody' => ['label' => Craft::t('consentmate', 'Text'), 'type' => 'textarea'],
                    'saveSettings' => ['label' => Craft::t('consentmate', 'Save button'), 'type' => 'text'],
                    'gpcNotice' => [
                        'label' => Craft::t('consentmate', 'Global Privacy Control notice'),
                        'type' => 'textarea',
                        'instructions' => Craft::t('consentmate', 'Shown in the settings when the visitor\'s browser sends a Global Privacy Control signal.'),
                    ],
                    'floatingButtonLabel' => [
                        'label' => Craft::t('consentmate', 'Cookie settings link'),
                        'type' => 'text',
                        'instructions' => Craft::t('consentmate', 'The label for links and buttons that reopen the settings.'),
                    ],
                ],
            ],
            'placeholder' => [
                'heading' => Craft::t('consentmate', 'Placeholders'),
                'fields' => [
                    'placeholderText' => [
                        'label' => Craft::t('consentmate', 'Text'),
                        'type' => 'textarea',
                        'instructions' => Craft::t('consentmate', 'Shown instead of embedded content (like videos) until the visitor consents. {provider} and {category} are replaced with the provider and the category name.'),
                    ],
                    'placeholderAccept' => ['label' => Craft::t('consentmate', 'Button'), 'type' => 'text'],
                ],
            ],
        ];
    }

    /**
     * Returns the default texts in a language.
     *
     * @param string|null $language
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getDefaults(?string $language = null): array
    {
        $t = static fn(string $message) => Craft::t('consentmate', $message, [], $language);

        $categories = [];

        foreach (array_keys(ConsentMate::getInstance()->getSettings()->getCategories()) as $handle) {
            $categories[$handle] = match ($handle) {
                'necessary' => [
                    'name' => $t('Strictly necessary'),
                    'description' => $t('Needed for the website to work, for example to keep forms secure and to remember your cookie choices. These can\'t be turned off.'),
                ],
                'preferences' => [
                    'name' => $t('Preferences'),
                    'description' => $t('Remember choices you make, like language or region, to give you a more personal experience.'),
                ],
                'statistics' => [
                    'name' => $t('Statistics'),
                    'description' => $t('Help us understand how the website is used, so we can improve it.'),
                ],
                'marketing' => [
                    'name' => $t('Marketing'),
                    'description' => $t('Used to measure the effect of our campaigns and to show you relevant ads on other websites. This involves sharing data with advertising partners.'),
                ],
                default => [
                    'name' => StringHelper::upperCaseFirst(StringHelper::toWords($handle, true)),
                    'description' => '',
                ],
            };
        }

        return [
            'companyName' => '',
            'bannerHeading' => $t('We use cookies'),
            'bannerBody' => $t('We use cookies and similar technologies to make this website work. With your consent, we also use them to understand how the website is used and to market our services. You can accept all, reject all, or choose in the settings. You can change your mind at any time.'),
            'googleNotice' => $t('With your consent, we share data with Google for measurement and ads personalization.'),
            'policyLinkLabel' => $t('Privacy and cookie policy'),
            'policyUrl' => '',
            'acceptAll' => $t('Accept all'),
            'rejectAll' => $t('Reject all'),
            'openSettings' => $t('Settings'),
            'settingsHeading' => $t('Cookie settings'),
            'settingsBody' => $t('Choose which kinds of cookies and similar technologies you allow. Strictly necessary cookies are always on, since the website doesn\'t work without them. You can change your choices at any time.'),
            'saveSettings' => $t('Save choices'),
            'gpcNotice' => $t('Your browser sends a Global Privacy Control signal, so marketing stays off unless you turn it on here.'),
            'floatingButtonLabel' => $t('Cookie settings'),
            'placeholderText' => $t('This content is provided by {provider}, which may set cookies. To show it, allow {category}.'),
            'placeholderAccept' => $t('Allow and show'),
            'categories' => $categories,
        ];
    }

    /**
     * Returns the texts the client has saved for a site, without defaults.
     *
     * @param int $siteId
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getSavedTexts(int $siteId): array
    {
        $json = (new Query())
            ->select(['texts'])
            ->from(Table::TEXTS)
            ->where(['siteId' => $siteId])
            ->scalar();

        $texts = $json ? Json::decodeIfJson($json) : [];

        return \is_array($texts) ? $texts : [];
    }

    /**
     * Returns the texts for a site, with defaults for anything not saved.
     *
     * @param int $siteId
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getTexts(int $siteId): array
    {
        if (isset($this->_texts[$siteId])) {
            return $this->_texts[$siteId];
        }

        $site = Craft::$app->getSites()->getSiteById($siteId, true);
        $defaults = $this->getDefaults($site?->language);
        $saved = $this->getSavedTexts($siteId);
        $texts = $defaults;

        foreach ($defaults as $key => $default) {
            if ($key === 'categories') {
                continue;
            }

            if (isset($saved[$key]) && \is_string($saved[$key]) && trim($saved[$key]) !== '') {
                $texts[$key] = $saved[$key];
            }
        }

        if (!self::isSafeUrl($texts['policyUrl'])) {
            $texts['policyUrl'] = '';
        }

        foreach ($defaults['categories'] as $handle => $category) {
            foreach (['name', 'description'] as $key) {
                $value = $saved['categories'][$handle][$key] ?? null;

                if (\is_string($value) && trim($value) !== '') {
                    $texts['categories'][$handle][$key] = $value;
                }
            }
        }

        return $this->_texts[$siteId] = $texts;
    }

    /**
     * Validates texts posted from the CP.
     *
     * @param array $texts
     * @return array<string, string[]> Errors, keyed by text key
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function validateTexts(array $texts): array
    {
        $errors = [];
        $policyUrl = trim((string)(\is_scalar($texts['policyUrl'] ?? null) ? $texts['policyUrl'] : ''));

        if ($policyUrl !== '' && !self::isSafeUrl($policyUrl)) {
            $errors['policyUrl'][] = Craft::t('consentmate', 'Enter a URL starting with https://, or a path on this website starting with /.');
        }

        return $errors;
    }

    /**
     * Returns whether a URL is safe to link to from the banner: an http(s)
     * URL, or a path on the website. Anything else, like a `javascript:`
     * URL, could run code when the link is clicked.
     *
     * @param string $url
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        // Browsers ignore whitespace and control characters in a URL, so
        // they're refused rather than let them hide a scheme
        return (bool)preg_match('/^(https?:\/\/|\/)[^\x00-\x20\x7F]*$/i', $url);
    }

    /**
     * Saves the texts for a site. Values equal to the default aren't stored,
     * so they follow later changes to the defaults.
     *
     * @param int $siteId
     * @param array $texts
     * @return void
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function saveTexts(int $siteId, array $texts): void
    {
        $site = Craft::$app->getSites()->getSiteById($siteId, true);
        $defaults = $this->getDefaults($site?->language);
        $save = [];

        foreach ($defaults as $key => $default) {
            if ($key === 'categories') {
                continue;
            }

            $value = trim(\is_scalar($texts[$key] ?? null) ? (string)$texts[$key] : '');

            if ($key === 'policyUrl' && !self::isSafeUrl($value)) {
                continue;
            }

            if ($value !== '' && $value !== $default) {
                $save[$key] = $value;
            }
        }

        foreach ($defaults['categories'] as $handle => $category) {
            foreach (['name', 'description'] as $key) {
                $value = $texts['categories'][$handle][$key] ?? null;
                $value = trim(\is_scalar($value) ? (string)$value : '');

                if ($value !== '' && $value !== $category[$key]) {
                    $save['categories'][$handle][$key] = $value;
                }
            }
        }

        Db::upsert(Table::TEXTS, [
            'siteId' => $siteId,
            // JSON columns are encoded by the query builder, so pass the array
            'texts' => $save,
        ]);

        unset($this->_texts[$siteId]);

        ConsentMate::getInstance()->getCaches()->changed();
    }

    /**
     * Returns the categories with their names and descriptions for a site.
     *
     * @param int $siteId
     * @return array<string, array{handle: string, required: bool, google: string[], name: string, description: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCategories(int $siteId): array
    {
        $texts = $this->getTexts($siteId);
        $categories = [];

        foreach (ConsentMate::getInstance()->getSettings()->getCategories() as $handle => $category) {
            $categories[$handle] = $category + [
                'name' => $texts['categories'][$handle]['name'] ?? $handle,
                'description' => $texts['categories'][$handle]['description'] ?? '',
            ];
        }

        return $categories;
    }

    /**
     * Returns the name of a category for a site.
     *
     * @param string $handle
     * @param int|null $siteId
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCategoryName(string $handle, ?int $siteId = null): string
    {
        $siteId ??= Craft::$app->getSites()->getCurrentSite()->id;

        return $this->getCategories($siteId)[$handle]['name'] ?? $handle;
    }
}
