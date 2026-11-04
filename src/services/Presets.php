<?php

namespace vaersaagod\consentmate\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\elements\Service;
use vaersaagod\consentmate\events\RegisterPresetsEvent;
use vaersaagod\consentmate\models\Preset;

/**
 * Service presets
 *
 * The built-in presets are one file each in `src/presets`. Projects can add
 * their own, change built-in ones or remove them (`false`) with the `presets`
 * config setting, and plugins with `EVENT_REGISTER_PRESETS`.
 *
 * Services keep the cookies their preset gave them, so they're updated with
 * `syncServices()` when a preset's cookies change. A release that changes the
 * cookies of a built-in preset needs a migration that calls it.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Presets extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * @event RegisterPresetsEvent The event triggered when the presets are
     * collected.
     */
    public const EVENT_REGISTER_PRESETS = 'registerPresets';

    // Private Properties
    // =========================================================================

    /**
     * @var array<string, Preset>|null
     */
    private ?array $_presets = null;

    // Public Methods
    // =========================================================================

    /**
     * Returns all presets, keyed by handle and sorted by name.
     *
     * @return array<string, Preset>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getAllPresets(): array
    {
        if ($this->_presets !== null) {
            return $this->_presets;
        }

        $configs = [];

        foreach (glob(\dirname(__DIR__) . '/presets/*.php') ?: [] as $file) {
            $config = require $file;

            if (\is_array($config)) {
                $configs[basename($file, '.php')] = $config;
            }
        }

        // Projects can add presets, change built-in ones, or remove them
        foreach (ConsentMate::getInstance()->getSettings()->presets as $handle => $config) {
            if ($config === false) {
                unset($configs[$handle]);
                continue;
            }

            if (\is_array($config)) {
                $configs[$handle] = array_merge($configs[$handle] ?? [], $config);
            }
        }

        if ($this->hasEventHandlers(self::EVENT_REGISTER_PRESETS)) {
            $event = new RegisterPresetsEvent(['presets' => $configs]);
            $this->trigger(self::EVENT_REGISTER_PRESETS, $event);
            $configs = $event->presets;
        }

        $categories = ConsentMate::getInstance()->getSettings()->getCategories();
        $fallbackCategory = array_key_first(array_filter($categories, static fn(array $category) => !$category['required']))
            ?? array_key_first($categories);
        $presets = [];

        foreach ($configs as $handle => $config) {
            if (!\is_array($config) || empty($config['name'])) {
                continue;
            }

            $preset = new Preset(array_intersect_key($config, array_flip(array_keys((new Preset())->getAttributes()))));
            $preset->handle = (string)$handle;
            $preset->cookies = Service::normalizeCookies($preset->cookies);
            $preset->fields = $this->_normalizeFields($preset->fields);

            if (!isset($categories[$preset->category])) {
                $preset->category = (string)$fallbackCategory;
            }

            $presets[$preset->handle] = $preset;
        }

        uasort($presets, static fn(Preset $a, Preset $b) => strcasecmp($a->name, $b->name));

        return $this->_presets = $presets;
    }

    /**
     * Returns a preset by its handle.
     *
     * @param string|null $handle
     * @return Preset|null
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getPreset(?string $handle): ?Preset
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        return $this->getAllPresets()[$handle] ?? null;
    }

    /**
     * Returns the presets grouped by their default category, in category
     * order, with the category names for a site.
     *
     * @param int $siteId
     * @return array<string, array{name: string, presets: Preset[]}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getPresetsByCategory(int $siteId): array
    {
        $groups = [];

        foreach (ConsentMate::getInstance()->getTexts()->getCategories($siteId) as $handle => $category) {
            $groups[$handle] = ['name' => $category['name'], 'presets' => []];
        }

        foreach ($this->getAllPresets() as $preset) {
            $groups[$preset->category]['presets'][] = $preset;
        }

        return array_filter($groups, static fn(array $group) => !empty($group['presets']));
    }

    /**
     * Normalizes the ID values posted for, or stored on, a service: only the
     * preset's fields, trimmed.
     *
     * @param Preset|null $preset
     * @param mixed $values
     * @return array<string, string>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function normalizeValues(?Preset $preset, mixed $values): array
    {
        if (\is_string($values)) {
            $values = Json::decodeIfJson($values);
        }

        if ($preset === null || !\is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach (array_keys($preset->fields) as $field) {
            $value = trim((string)($values[$field] ?? ''));

            if ($value !== '') {
                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Generates a preset's code from ID values. Returns an empty string if a
     * value is missing or doesn't match its field's pattern, so a bad ID
     * never ends up in a page.
     *
     * @param Preset $preset
     * @param array $values
     * @param string $position 'head' or 'body'
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function generateCode(Preset $preset, array $values, string $position): string
    {
        $template = $position === 'body' ? $preset->bodyCode : $preset->headCode;

        if (trim($template) === '') {
            return '';
        }

        $replacements = [];

        foreach (array_keys($preset->fields) as $field) {
            $value = (string)($values[$field] ?? '');

            if ($value === '' || !$preset->isValidValue($field, $value)) {
                return '';
            }

            $replacements['{' . $field . '}'] = $value;
        }

        return trim(strtr($template, $replacements));
    }

    /**
     * Returns the preset whose first-party cookies match a cookie name.
     *
     * @param string $name
     * @return Preset|null
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function findByCookieName(string $name): ?Preset
    {
        foreach ($this->getAllPresets() as $preset) {
            foreach ($preset->cookies as $cookie) {
                if ($cookie['party'] !== 'first' || $cookie['type'] !== 'cookie') {
                    continue;
                }

                $pattern = '/^' . str_replace('\*', '.*', preg_quote($cookie['name'], '/')) . '$/';

                if (preg_match($pattern, $name)) {
                    return $preset;
                }
            }
        }

        return null;
    }

    /**
     * Brings a service's cookies in step with its preset, without touching
     * the client's own changes. Compares the service's cookies with the
     * preset's cookies as last applied (`$applied`) and as they are now
     * (`$preset`):
     *
     * - A row the client hasn't changed since it was applied follows the
     *   preset: it's replaced with the new version, or removed if the preset
     *   dropped the cookie.
     * - A row the client changed or deleted is left as it is.
     * - A cookie new to the preset is added, unless the service already has
     *   one by that name and type.
     * - Cookies the client added are never touched.
     *
     * @param array $current The service's cookies, normalized
     * @param array $applied The preset's cookies as last applied, normalized
     * @param array $preset The preset's cookies now, normalized
     * @return array{cookies: array, added: string[], changed: string[], removed: string[]}
     *
     * @author    Værsågod
     * @since     1.0.4
     */
    public function mergeCookies(array $current, array $applied, array $preset): array
    {
        // A row's full value, and the cookie it describes
        $key = static fn(array $row): string => implode("\0", [$row['name'], $row['type'], $row['party'], (string)$row['duration'], $row['unit']]);
        $id = static fn(array $row): string => $row['name'] . "\0" . $row['type'];

        $appliedKeys = array_map($key, $applied);
        $appliedIds = array_map($id, $applied);
        $presetKeys = array_map($key, $preset);
        $presetById = [];

        foreach ($preset as $row) {
            $presetById[$id($row)] ??= $row;
        }

        $cookies = [];
        $added = [];
        $changed = [];
        $removed = [];

        foreach ($current as $row) {
            $rowKey = $key($row);

            // Untouched since it was applied, and the preset has changed or dropped it
            if (\in_array($rowKey, $appliedKeys, true) && !\in_array($rowKey, $presetKeys, true)) {
                $replacement = $presetById[$id($row)] ?? null;

                if ($replacement === null) {
                    $removed[] = $row['name'];
                    continue;
                }

                $cookies[] = $replacement;
                $changed[] = $row['name'];
                continue;
            }

            $cookies[] = $row;
        }

        $currentIds = array_map($id, $cookies);

        foreach ($preset as $row) {
            $rowId = $id($row);

            // Already applied, a change to a cookie the preset already had (only
            // untouched rows follow those, above), or one the service has
            if (\in_array($key($row), $appliedKeys, true) || \in_array($rowId, $appliedIds, true) || \in_array($rowId, $currentIds, true)) {
                continue;
            }

            $cookies[] = $row;
            $currentIds[] = $rowId;
            $added[] = $row['name'];
        }

        // A replacement can match a row the client already added
        $unique = [];

        foreach ($cookies as $row) {
            $unique[$key($row)] ??= $row;
        }

        return [
            'cookies' => array_values($unique),
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
        ];
    }

    /**
     * Brings the cookies of every service made from a preset in step with
     * it. Services without the preset's cookies as last applied (made before
     * they were stored) only get them stored, so the next change applies.
     *
     * @param bool $dryRun Report what would change without saving
     * @return array<int, array{service: Service, added: string[], changed: string[], removed: string[], saved: bool}> The services whose cookies changed
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.4
     */
    public function syncServices(bool $dryRun = false): array
    {
        $handles = array_keys($this->getAllPresets());

        if (empty($handles)) {
            return [];
        }

        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;

        /** @var Service[] $services */
        $services = Service::find()
            ->preset($handles)
            ->siteId($primarySiteId)
            ->status(null)
            ->all();

        $results = [];

        foreach ($services as $service) {
            $changes = $service->syncPresetCookies();

            if ($changes === null) {
                continue;
            }

            $hasChanges = !empty($changes['added']) || !empty($changes['changed']) || !empty($changes['removed']);
            $saved = false;

            if (!$dryRun) {
                // Only the cookies change, so other fields that no longer
                // validate (an old ID, say) mustn't block it
                $saved = Craft::$app->getElements()->saveElement($service, false);
            }

            if ($hasChanges) {
                $results[] = ['service' => $service] + $changes + ['saved' => $saved];
            }
        }

        return $results;
    }

    /**
     * Returns the preset options for selects, by name.
     *
     * @return array<int, array{label: string, value: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getPresetOptions(): array
    {
        return array_values(array_map(
            static fn(Preset $preset) => ['label' => Craft::t('consentmate', $preset->name), 'value' => $preset->handle],
            $this->getAllPresets()
        ));
    }

    // Private Methods
    // =========================================================================

    /**
     * Keeps only fields with a valid pattern, and gives each one a label. A
     * field without a pattern could let anything into the generated code.
     *
     * @param array $fields
     * @return array<string, array{label: string, placeholder?: string, pattern: string, instructions?: string}>
     */
    private function _normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $handle => $field) {
            $pattern = \is_array($field) ? ($field['pattern'] ?? null) : null;

            if (!\is_string($pattern) || @preg_match($pattern, '') === false) {
                Craft::warning("The preset field \"$handle\" has no valid pattern, so it's left out.", __METHOD__);
                continue;
            }

            $normalized[(string)$handle] = ['label' => (string)($field['label'] ?? $handle), 'pattern' => $pattern]
                + array_intersect_key($field, ['placeholder' => true, 'instructions' => true]);
        }

        return $normalized;
    }
}
