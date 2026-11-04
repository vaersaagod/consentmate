<?php

namespace vaersaagod\consentmate\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Html;
use vaersaagod\consentmate\elements\Service;

/**
 * The IDs a preset's code is generated from, like a Google Analytics
 * measurement ID. Only shown on services made from a preset that has them.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class PresetField extends BaseNativeField
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public string $attribute = 'presetValues';

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

    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        // BaseField::label() doesn't pass the element on to defaultLabel(), so
        // set the element's label for the duration of the render
        $label = $this->label;
        $this->label = $this->defaultLabel($element, $static);

        try {
            return parent::formHtml($element, $static);
        } finally {
            $this->label = $label;
        }
    }

    /**
     * @inheritdoc
     */
    public function showInForm(?ElementInterface $element = null): bool
    {
        return $element instanceof Service
            && ($element->getPreset()?->hasFields() ?? false)
            && parent::showInForm($element);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        if ($element instanceof Service && ($preset = $element->getPreset()) !== null) {
            // A single ID is labelled by itself ("Measurement ID")
            if (\count($preset->fields) === 1) {
                $field = reset($preset->fields);

                return Craft::t('consentmate', $field['label']);
            }

            return Craft::t('consentmate', $preset->name);
        }

        return Craft::t('consentmate', 'Preset');
    }

    /**
     * @inheritdoc
     */
    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        $name = $element instanceof Service ? Craft::t('consentmate', $element->getPreset()->name ?? '') : '';

        return Craft::t('consentmate', 'The {name} tracking code is written from this. To use your own code instead, paste the full tracking code on the Code tab.', [
            'name' => $name,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function tip(?ElementInterface $element = null, bool $static = false): ?string
    {
        if ($element instanceof Service && ($notes = $element->getPreset()?->getNotes())) {
            return $notes;
        }

        return parent::tip($element, $static);
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Service || ($preset = $element->getPreset()) === null) {
            return null;
        }

        $view = Craft::$app->getView();
        $values = $element->getPresetValues();
        $html = '';

        $single = \count($preset->fields) === 1;

        foreach ($preset->fields as $handle => $field) {
            // The field's own label points at id()
            $id = $single ? $this->id() : $this->id() . '-' . $handle;
            $label = Craft::t('consentmate', $field['label']);

            $input = $view->renderTemplate('_includes/forms/text.twig', [
                'id' => $id,
                'name' => "{$this->attribute}[{$handle}]",
                'value' => $values[$handle] ?? '',
                'placeholder' => $field['placeholder'] ?? null,
                'disabled' => $static,
                'autocomplete' => false,
                'class' => 'code',
            ]);

            if ($single) {
                $html .= $input;
                continue;
            }

            $html .= Html::tag('div', Html::label($label, $id) . $input, ['class' => 'field']);
        }

        return $html;
    }
}
