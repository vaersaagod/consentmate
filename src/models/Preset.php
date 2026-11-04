<?php

namespace vaersaagod\consentmate\models;

use Craft;
use craft\base\Model;

/**
 * A service preset: a known service (Google Analytics, YouTube) with its
 * declaration, and optionally the code to load it from an ID
 *
 * Texts are written in English and translated with the `consentmate`
 * category, so projects can override them in their own translation files.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Preset extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The handle, used in `{% consent 'handle' %}` and stored on services
     */
    public string $handle = '';

    /**
     * @var string The service name, like "Google Analytics"
     */
    public string $name = '';

    /**
     * @var string The default category handle
     */
    public string $category = 'marketing';

    /**
     * @var string The company behind the service
     */
    public string $provider = '';

    /**
     * @var string The provider's privacy policy
     */
    public string $privacyPolicyUrl = '';

    /**
     * @var string Where data is transferred outside the EU/EEA, and on what basis
     */
    public string $dataTransfers = '';

    /**
     * @var string What the service is used for (English, translated)
     */
    public string $description = '';

    /**
     * @var array The cookies and storage the service sets, in the same shape
     * as a service's cookies
     */
    public array $cookies = [];

    /**
     * @var array<string, array{label: string, placeholder?: string, pattern: string, instructions?: string}>
     * The IDs the code is generated from, keyed by the token used in the code
     */
    public array $fields = [];

    /**
     * @var string Code for `<head>`, with `{field}` tokens
     */
    public string $headCode = '';

    /**
     * @var string Code for the end of `<body>`, with `{field}` tokens
     */
    public string $bodyCode = '';

    /**
     * @var string Guidance shown in the CP (English, translated)
     */
    public string $notes = '';

    // Public Methods
    // =========================================================================

    /**
     * Returns the description in a language.
     *
     * @param string|null $language
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getDescription(?string $language = null): string
    {
        return $this->description !== '' ? Craft::t('consentmate', $this->description, [], $language) : '';
    }

    /**
     * Returns the CP notes, translated.
     *
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getNotes(): string
    {
        return $this->notes !== '' ? Craft::t('consentmate', $this->notes) : '';
    }

    /**
     * Returns whether the preset generates code.
     *
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function hasCode(): bool
    {
        return trim($this->headCode) !== '' || trim($this->bodyCode) !== '';
    }

    /**
     * Returns whether the preset asks for IDs.
     *
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function hasFields(): bool
    {
        return !empty($this->fields);
    }

    /**
     * Returns whether a value is valid for a field.
     *
     * @param string $field
     * @param string $value
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function isValidValue(string $field, string $value): bool
    {
        $pattern = $this->fields[$field]['pattern'] ?? null;

        return \is_string($pattern) && preg_match($pattern, $value) === 1;
    }
}
