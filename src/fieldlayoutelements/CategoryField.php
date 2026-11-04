<?php

namespace vaersaagod\consentmate\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\elements\Service;

/**
 * The service category select
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class CategoryField extends BaseNativeField
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public string $attribute = 'category';

    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public bool $required = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        unset($config['attribute'], $config['mandatory'], $config['required']);
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['mandatory'], $fields['required']);

        return $fields;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('consentmate', 'Category');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        /** @var Service|null $element */
        $siteId = $element?->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $options = [];

        foreach (ConsentMate::getInstance()->getTexts()->getCategories($siteId) as $category) {
            $options[] = ['label' => $category['name'], 'value' => $category['handle']];
        }

        return Craft::$app->getView()->renderTemplate('_includes/forms/select.twig', [
            'id' => $this->id(),
            'describedBy' => $this->describedBy($element, $static),
            'name' => $this->attribute,
            'value' => $element?->category,
            'options' => $options,
            'disabled' => $static,
        ]);
    }
}
