<?php

namespace vaersaagod\consentmate\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\fieldlayoutelements\TextareaField;
use craft\fieldlayoutelements\TextField;
use craft\fieldlayoutelements\TitleField;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\validators\UrlValidator;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\controllers\ServicesController;
use vaersaagod\consentmate\db\Table;
use vaersaagod\consentmate\elements\db\ServiceQuery;
use vaersaagod\consentmate\fieldlayoutelements\CategoryField;
use vaersaagod\consentmate\fieldlayoutelements\CookiesField;
use vaersaagod\consentmate\fieldlayoutelements\PresetField;
use vaersaagod\consentmate\helpers\DurationHelper;
use vaersaagod\consentmate\models\Preset;

/**
 * A service: a tool that needs consent (Google Analytics, Meta Pixel, a
 * YouTube embed), with the code it runs and the cookies it sets.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Service extends Element
{
    // Const Properties
    // =========================================================================

    /**
     * The field layout is defined in code. Its UIDs must be stable across
     * requests, since the element editor refers to layout elements by UID.
     */
    private const LAYOUT_UIDS = [
        'layout' => 'b4fa9f94-7a2f-4d4f-8e6c-bb287e1d891e',
        'tabContent' => '820a7204-e42e-4c51-b257-94476c779119',
        'tabCode' => 'c64d5832-918f-4e0a-bc68-1b492b07a27e',
        'title' => '30f232ba-085b-4275-9d90-ca9fd07a1586',
        'category' => 'f0239fc6-cfd9-46cf-b39a-5e9cd5ac4df5',
        'provider' => 'e2423f4c-d095-4aed-ae2f-f764979fca04',
        'presetValues' => '5741551e-e110-4427-ac11-195bc8eb67d5',
        'description' => '5c83c6f8-38ce-4367-addd-e710a42b79c9',
        'privacyPolicyUrl' => '66bf1103-82c6-418d-8b85-9eb345cf2ba1',
        'dataTransfers' => '7a2024bb-7fb7-41c7-a3a8-fec090bd2791',
        'cookies' => '853341bb-035a-44c4-98ab-8bf07de63d7b',
        'headCode' => 'f83921bc-5851-4495-bce3-604445914125',
        'bodyCode' => '59e05d21-8e59-4841-ad4b-36e96c9f8fbc',
    ];

    // Static Properties
    // =========================================================================

    /**
     * @var FieldLayout|null
     */
    private static ?FieldLayout $_fieldLayout = null;

    // Public Properties
    // =========================================================================

    /**
     * @var string|null The category handle
     */
    public ?string $category = null;

    /**
     * @var string|null The company behind the service
     */
    public ?string $provider = null;

    /**
     * @var string|null The preset the service was created from, if any
     */
    public ?string $preset = null;

    /**
     * @var array|string|null The IDs the preset's code is generated from,
     * keyed by field. Normalized to an array on init and before validation.
     */
    public array|string|null $presetValues = [];

    /**
     * @var array|string|null The cookies the service sets. Each row has name,
     * type, party, duration and unit. Normalized to an array on init and
     * before validation.
     */
    public array|string|null $cookies = [];

    /**
     * @var string|null Code output in `<head>` after consent
     */
    public ?string $headCode = null;

    /**
     * @var string|null Code output at the end of `<body>` after consent
     */
    public ?string $bodyCode = null;

    /**
     * @var string|null What the service is used for (localized)
     */
    public ?string $description = null;

    /**
     * @var string|null The provider's privacy policy (localized)
     */
    public ?string $privacyPolicyUrl = null;

    /**
     * @var string|null Where data is transferred, e.g. "USA (EU-US Data
     * Privacy Framework)" (localized)
     */
    public ?string $dataTransfers = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('consentmate', 'Service');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('consentmate', 'service');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('consentmate', 'Services');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('consentmate', 'services');
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return ServiceQuery
     */
    public static function find(): ServiceQuery
    {
        return new ServiceQuery(static::class);
    }

    /**
     * Returns the field layout, built in code.
     *
     * @return FieldLayout
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function fieldLayout(): FieldLayout
    {
        if (self::$_fieldLayout !== null) {
            return self::$_fieldLayout;
        }

        $uids = self::LAYOUT_UIDS;

        $layout = new FieldLayout([
            'type' => self::class,
            'uid' => $uids['layout'],
        ]);

        $contentTab = new FieldLayoutTab([
            'name' => Craft::t('consentmate', 'Service'),
            'uid' => $uids['tabContent'],
            'layout' => $layout,
        ]);
        $contentTab->setElements([
            new TitleField([
                'uid' => $uids['title'],
                'label' => Craft::t('consentmate', 'Name'),
                'instructions' => Craft::t('consentmate', 'The name visitors see, like "Google Analytics".'),
            ]),
            new CategoryField([
                'uid' => $uids['category'],
                'width' => 50,
            ]),
            new TextField([
                'uid' => $uids['provider'],
                'attribute' => 'provider',
                'label' => Craft::t('consentmate', 'Provider'),
                'instructions' => Craft::t('consentmate', 'The company behind the service, like "Google Ireland Limited".'),
                'width' => 50,
            ]),
            new PresetField([
                'uid' => $uids['presetValues'],
            ]),
            new TextareaField([
                'uid' => $uids['description'],
                'attribute' => 'description',
                'label' => Craft::t('consentmate', 'Purpose'),
                'instructions' => Craft::t('consentmate', 'What the service is used for, in the language of the site.'),
                'translatable' => true,
                'rows' => 3,
            ]),
            new TextField([
                'uid' => $uids['privacyPolicyUrl'],
                'attribute' => 'privacyPolicyUrl',
                'label' => Craft::t('consentmate', 'Privacy policy URL'),
                'instructions' => Craft::t('consentmate', "The provider's privacy policy."),
                'translatable' => true,
                'type' => 'url',
                'width' => 50,
            ]),
            new TextField([
                'uid' => $uids['dataTransfers'],
                'attribute' => 'dataTransfers',
                'label' => Craft::t('consentmate', 'Data transfers'),
                'instructions' => Craft::t('consentmate', 'Countries outside the EU/EEA that data is transferred to, and on what basis. For example "USA (EU-US Data Privacy Framework)". Leave empty if none.'),
                'translatable' => true,
                'width' => 50,
            ]),
            new CookiesField([
                'uid' => $uids['cookies'],
            ]),
        ]);

        $codeTab = new FieldLayoutTab([
            'name' => Craft::t('consentmate', 'Code'),
            'uid' => $uids['tabCode'],
            'layout' => $layout,
        ]);
        $codeTab->setElements([
            new TextareaField([
                'uid' => $uids['headCode'],
                'attribute' => 'headCode',
                'label' => Craft::t('consentmate', 'Head code'),
                'instructions' => Craft::t('consentmate', 'Scripts and markup to add to `<head>` once a visitor consents to the category. Paste the snippet exactly as the provider gives it. On a service made from a preset, code pasted here replaces the code written from the ID. Leave both code fields empty if the service is loaded some other way, for example through Google Tag Manager.'),
                'class' => ['code'],
                'rows' => 10,
            ]),
            new TextareaField([
                'uid' => $uids['bodyCode'],
                'attribute' => 'bodyCode',
                'label' => Craft::t('consentmate', 'Body code'),
                'instructions' => Craft::t('consentmate', 'Scripts and markup to add at the end of `<body>` once a visitor consents to the category.'),
                'class' => ['code'],
                'rows' => 10,
            ]),
        ]);

        $layout->setTabs([$contentTab, $codeTab]);

        return self::$_fieldLayout = $layout;
    }

    /**
     * Returns the cookie type options.
     *
     * @return array<int, array{label: string, value: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function cookieTypeOptions(): array
    {
        return [
            ['label' => Craft::t('consentmate', 'Cookie'), 'value' => 'cookie'],
            ['label' => Craft::t('consentmate', 'Local storage'), 'value' => 'localStorage'],
            ['label' => Craft::t('consentmate', 'Session storage'), 'value' => 'sessionStorage'],
            ['label' => Craft::t('consentmate', 'IndexedDB'), 'value' => 'indexedDB'],
            ['label' => Craft::t('consentmate', 'Pixel'), 'value' => 'pixel'],
        ];
    }

    /**
     * Normalizes cookie rows from the database or a CP form.
     *
     * @param mixed $value
     * @return array<int, array{name: string, type: string, party: string, duration: ?int, unit: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function normalizeCookies(mixed $value): array
    {
        if (\is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!\is_array($value)) {
            return [];
        }

        $types = array_column(self::cookieTypeOptions(), 'value');
        $rows = [];

        foreach ($value as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $name = trim((string)($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $unit = (string)($row['unit'] ?? 'session');
            $unit = \array_key_exists($unit, DurationHelper::UNITS) ? $unit : 'session';
            $duration = $row['duration'] ?? null;
            $hasLength = DurationHelper::UNITS[$unit] !== null;

            $rows[] = [
                'name' => $name,
                'type' => \in_array($row['type'] ?? null, $types, true) ? $row['type'] : 'cookie',
                'party' => ($row['party'] ?? 'first') === 'third' ? 'third' : 'first',
                'duration' => $hasLength && is_numeric($duration) ? max(1, (int)$duration) : null,
                'unit' => $unit,
            ];
        }

        return $rows;
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->cookies = self::normalizeCookies($this->cookies);
        $this->presetValues = $this->getPresetValues();
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        $sites = [];

        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $sites[] = [
                'siteId' => $siteId,
                'propagate' => true,
                'enabledByDefault' => true,
            ];
        }

        return $sites;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return static::fieldLayout();
    }

    /**
     * Returns the cookie rows.
     *
     * @return array<int, array{name: string, type: string, party: string, duration: ?int, unit: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCookies(): array
    {
        return self::normalizeCookies($this->cookies);
    }

    /**
     * Returns the preset the service was created from, if it still exists.
     *
     * @return Preset|null
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getPreset(): ?Preset
    {
        return ConsentMate::getInstance()->getPresets()->getPreset($this->preset);
    }

    /**
     * Returns the preset ID values, normalized.
     *
     * @return array<string, string>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getPresetValues(): array
    {
        return ConsentMate::getInstance()->getPresets()->normalizeValues($this->getPreset(), $this->presetValues);
    }

    /**
     * Returns whether the service has code pasted in.
     *
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function hasCode(): bool
    {
        return trim((string)$this->headCode) !== '' || trim((string)$this->bodyCode) !== '';
    }

    /**
     * Returns the code to output: pasted code if there is any, otherwise the
     * code the preset generates from the ID values.
     *
     * @return array{head: string, body: string, generated: bool}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getOutputCode(): array
    {
        if ($this->hasCode()) {
            return ['head' => trim((string)$this->headCode), 'body' => trim((string)$this->bodyCode), 'generated' => false];
        }

        $preset = $this->getPreset();

        if ($preset === null || !$preset->hasCode()) {
            return ['head' => '', 'body' => '', 'generated' => false];
        }

        $presets = ConsentMate::getInstance()->getPresets();
        $values = $this->getPresetValues();
        $head = $presets->generateCode($preset, $values, 'head');
        $body = $presets->generateCode($preset, $values, 'body');

        return ['head' => $head, 'body' => $body, 'generated' => $head !== '' || $body !== ''];
    }

    /**
     * Validates the preset ID values against their fields' patterns.
     *
     * @param string $attribute
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function validatePresetValues(string $attribute): void
    {
        $preset = $this->getPreset();

        if ($preset === null) {
            return;
        }

        foreach ($this->getPresetValues() as $field => $value) {
            if (!$preset->isValidValue($field, $value)) {
                $label = Craft::t('consentmate', $preset->fields[$field]['label'] ?? $field);
                $this->addError($attribute, Craft::t('consentmate', '{label} doesn\'t look right. Check it against the value in {service}.', [
                    'label' => $label,
                    'service' => Craft::t('consentmate', $preset->name),
                ]));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        return $user->can(ServicesController::PERMISSION_MANAGE_SERVICES);
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        return $user->can(ServicesController::PERMISSION_MANAGE_SERVICES);
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }

        return $user->can(ServicesController::PERMISSION_MANAGE_SERVICES);
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        return $user->can(ServicesController::PERMISSION_MANAGE_SERVICES);
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return $user->can(ServicesController::PERMISSION_MANAGE_SERVICES);
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('consentmate/services');
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        $this->cookies = self::normalizeCookies($this->cookies);
        $this->presetValues = $this->getPresetValues();

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        $preset = $this->getPreset();

        if ($preset !== null) {
            if ($isNew && !$this->propagating) {
                $this->_applyPreset($preset);
            } elseif ($this->propagating) {
                $this->_localizePresetDescription($preset);
            }
        }

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        Db::upsert(Table::SERVICES, [
            'id' => $this->id,
            'category' => (string)$this->category,
            'provider' => $this->provider,
            'preset' => $this->getPreset() !== null ? $this->preset : null,
            // JSON columns are encoded by the query builder, so pass arrays
            'presetValues' => $this->getPresetValues() ?: null,
            'cookies' => self::normalizeCookies($this->cookies),
            'headCode' => $this->headCode,
            'bodyCode' => $this->bodyCode,
        ]);

        Db::upsert(Table::SERVICES_SITES, [
            'id' => $this->id,
            'siteId' => $this->siteId,
            'description' => $this->description,
            'privacyPolicyUrl' => $this->privacyPolicyUrl,
            'dataTransfers' => $this->dataTransfers,
        ]);

        parent::afterSave($isNew);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('consentmate', 'All services'),
                'criteria' => [],
                'defaultSort' => ['title', 'asc'],
            ],
            [
                'heading' => Craft::t('consentmate', 'Categories'),
            ],
        ];

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        foreach (ConsentMate::getInstance()->getTexts()->getCategories($siteId) as $category) {
            $sources[] = [
                'key' => "category:{$category['handle']}",
                'label' => $category['name'],
                'criteria' => ['category' => $category['handle']],
                'data' => ['category' => $category['handle']],
                'defaultSort' => ['title', 'asc'],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineFieldLayouts(?string $source): array
    {
        return [static::fieldLayout()];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'category' => ['label' => Craft::t('consentmate', 'Category')],
            'provider' => ['label' => Craft::t('consentmate', 'Provider')],
            'preset' => ['label' => Craft::t('consentmate', 'Preset')],
            'cookies' => ['label' => Craft::t('consentmate', 'Cookies')],
            'code' => ['label' => Craft::t('consentmate', 'Code')],
            'id' => ['label' => Craft::t('app', 'ID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['category', 'provider', 'cookies', 'code'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            [
                'label' => Craft::t('consentmate', 'Provider'),
                'orderBy' => 'consentmate_services.provider',
                'attribute' => 'provider',
            ],
            [
                'label' => Craft::t('consentmate', 'Category'),
                'orderBy' => 'consentmate_services.category',
                'attribute' => 'category',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['provider', 'description'];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $categoryHandles = array_keys(ConsentMate::getInstance()->getSettings()->getCategories());

        // Unpublished drafts (from elements/create) are saved in the essentials
        // scenario, before the category is chosen
        $rules[] = [['category'], 'required', 'on' => [self::SCENARIO_DEFAULT, self::SCENARIO_LIVE]];
        $rules[] = [['category'], 'in', 'range' => $categoryHandles];
        $rules[] = [['provider', 'privacyPolicyUrl'], 'string', 'max' => 255];
        $rules[] = [['privacyPolicyUrl'], UrlValidator::class, 'defaultScheme' => 'https'];
        $rules[] = [['cookies', 'headCode', 'bodyCode', 'description', 'dataTransfers', 'preset', 'presetValues'], 'safe'];
        // The preset can be posted on any save, so only accept known ones
        $rules[] = [['preset'], 'in', 'range' => array_keys(ConsentMate::getInstance()->getPresets()->getAllPresets())];
        $rules[] = [['presetValues'], 'validatePresetValues'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("consentmate/services/{$this->getCanonicalId()}");
    }

    /**
     * @inheritdoc
     */
    protected function crumbs(): array
    {
        return [
            [
                'label' => Craft::t('consentmate', 'Services'),
                'url' => UrlHelper::cpUrl('consentmate/services'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'category' => Html::encode(ConsentMate::getInstance()->getTexts()->getCategoryName((string)$this->category, $this->siteId)),
            'provider' => Html::encode((string)$this->provider),
            'cookies' => Html::encode(implode(', ', array_column($this->getCookies(), 'name'))),
            'preset' => Html::encode($this->getPreset() !== null ? Craft::t('consentmate', $this->getPreset()->name) : ''),
            'code' => $this->_codeLabelHtml(),
            default => parent::attributeHtml($attribute),
        };
    }

    // Private Methods
    // =========================================================================

    /**
     * Fills in what the client hasn't from a preset, when a service is created
     * from it. The preset's category wins, since the index posts the category
     * of the selected source.
     *
     * @param Preset $preset
     * @return void
     */
    private function _applyPreset(Preset $preset): void
    {
        $language = $this->getSite()->language;

        $this->category = $preset->category;
        $this->title = $this->title ?: Craft::t('consentmate', $preset->name, [], $language);
        $this->provider = $this->provider ?: $preset->provider;
        $this->privacyPolicyUrl = $this->privacyPolicyUrl ?: $preset->privacyPolicyUrl;
        $this->dataTransfers = $this->dataTransfers ?: $preset->dataTransfers;
        $this->description = $this->description ?: $preset->getDescription($language);

        if (empty($this->getCookies())) {
            $this->cookies = $preset->cookies;
        }
    }

    /**
     * Propagating to another site copies the description in the source site's
     * language. While it's still the preset's text, swap in this site's
     * translation; a description the client wrote is kept.
     *
     * @param Preset $preset
     * @return void
     */
    private function _localizePresetDescription(Preset $preset): void
    {
        $sourceLanguage = $this->propagatingFrom?->getSite()->language;

        if ($sourceLanguage === null || $this->description !== $preset->getDescription($sourceLanguage)) {
            return;
        }

        $this->description = $preset->getDescription($this->getSite()->language);
    }

    /**
     * Returns the Code column for the index.
     *
     * @return string
     */
    private function _codeLabelHtml(): string
    {
        if ($this->hasCode()) {
            return Html::tag('span', Craft::t('consentmate', 'Custom'), ['class' => 'status-label green']);
        }

        if ($this->getOutputCode()['generated']) {
            return Html::tag('span', Craft::t('consentmate', 'Generated'), ['class' => 'status-label green']);
        }

        return Html::tag('span', Craft::t('consentmate', 'None'), ['class' => 'light']);
    }
}
