<?php

namespace vaersaagod\consentmate\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use vaersaagod\consentmate\db\Table;
use vaersaagod\consentmate\elements\Service;

/**
 * Service query
 *
 * @template TKey of array-key
 * @template TElement of Service
 * @extends ElementQuery<TKey,TElement>
 *
 * @method Service[]|array all($db = null)
 * @method Service|array|null one($db = null)
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ServiceQuery extends ElementQuery
{
    // Public Properties
    // =========================================================================

    /**
     * @var string|string[]|null The category handle(s) the services must be in
     */
    public mixed $category = null;

    /**
     * @var string|string[]|null The provider(s) the services must have
     */
    public mixed $provider = null;

    /**
     * @var string|string[]|null The preset handle(s) the services must be made from
     */
    public mixed $preset = null;

    // Public Methods
    // =========================================================================

    /**
     * Narrows the query results based on the services' categories.
     *
     * @param string|string[]|null $value
     * @return static
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function category(mixed $value): static
    {
        $this->category = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the services' providers.
     *
     * @param string|string[]|null $value
     * @return static
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function provider(mixed $value): static
    {
        $this->provider = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the presets the services were made
     * from.
     *
     * @param string|string[]|null $value
     * @return static
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function preset(mixed $value): static
    {
        $this->preset = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable(Table::SERVICES);

        $this->query->leftJoin(
            ['consentmate_services_sites' => Table::SERVICES_SITES],
            '[[consentmate_services_sites.id]] = [[elements.id]] AND [[consentmate_services_sites.siteId]] = [[elements_sites.siteId]]'
        );

        $this->query->addSelect([
            'consentmate_services.category',
            'consentmate_services.provider',
            'consentmate_services.preset',
            'consentmate_services.presetValues',
            'consentmate_services.cookies',
            'consentmate_services.headCode',
            'consentmate_services.bodyCode',
            'consentmate_services_sites.description',
            'consentmate_services_sites.privacyPolicyUrl',
            'consentmate_services_sites.dataTransfers',
        ]);

        if ($this->category !== null) {
            $this->subQuery->andWhere(['consentmate_services.category' => $this->category]);
        }

        if ($this->preset !== null) {
            $this->subQuery->andWhere(['consentmate_services.preset' => $this->preset]);
        }

        if ($this->provider !== null) {
            $this->subQuery->andWhere(Db::parseParam('consentmate_services.provider', $this->provider));
        }

        return parent::beforePrepare();
    }
}
