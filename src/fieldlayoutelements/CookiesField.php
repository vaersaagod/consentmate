<?php

namespace vaersaagod\consentmate\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use vaersaagod\consentmate\elements\Service;
use vaersaagod\consentmate\helpers\DurationHelper;

/**
 * The editable table of cookies (and other storage) a service sets
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class CookiesField extends BaseNativeField
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public string $attribute = 'cookies';

    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        unset($config['attribute'], $config['mandatory']);
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['mandatory']);

        return $fields;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('consentmate', 'Cookies and storage');
    }

    /**
     * @inheritdoc
     */
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('consentmate', 'The cookies and other browser storage the service sets. Use * as a wildcard, like `_ga_*`. First-party cookies are deleted when a visitor withdraws consent.');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Service|null $element */
        return Craft::$app->getView()->renderTemplate('_includes/forms/editableTable.twig', [
            'id' => $this->id(),
            'name' => $this->attribute,
            'cols' => [
                'name' => [
                    'heading' => Craft::t('consentmate', 'Name'),
                    'type' => 'singleline',
                    'code' => true,
                ],
                'type' => [
                    'heading' => Craft::t('consentmate', 'Type'),
                    'type' => 'select',
                    'options' => Service::cookieTypeOptions(),
                    'thin' => true,
                ],
                'party' => [
                    'heading' => Craft::t('consentmate', 'Set by'),
                    'type' => 'select',
                    'options' => [
                        ['label' => Craft::t('consentmate', 'This website'), 'value' => 'first'],
                        ['label' => Craft::t('consentmate', 'Third party'), 'value' => 'third'],
                    ],
                    'thin' => true,
                ],
                'duration' => [
                    'heading' => Craft::t('consentmate', 'Duration'),
                    'type' => 'number',
                    'thin' => true,
                ],
                'unit' => [
                    'heading' => Craft::t('consentmate', 'Unit'),
                    'type' => 'select',
                    'options' => DurationHelper::unitOptions(),
                    'thin' => true,
                ],
            ],
            'rows' => $element?->cookies ?? [],
            'defaultValues' => [
                'type' => 'cookie',
                'party' => 'first',
                'unit' => 'years',
            ],
            'allowAdd' => true,
            'allowDelete' => true,
            'allowReorder' => true,
            'addRowLabel' => Craft::t('consentmate', 'Add a cookie'),
            'static' => $static,
            'describedBy' => $this->describedBy($element, $static),
        ]);
    }
}
