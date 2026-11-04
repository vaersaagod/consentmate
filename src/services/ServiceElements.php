<?php

namespace vaersaagod\consentmate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\ElementHelper;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;
use vaersaagod\consentmate\elements\Service;
use yii\caching\TagDependency;

/**
 * Reads services for the front end, and reacts to service changes
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ServiceElements extends Component
{
    // Private Properties
    // =========================================================================

    /**
     * @var array<int, array{sites: int[], category: ?string}> The state of
     * services before they're saved, keyed by element ID
     */
    private array $_before = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns the services that are live on a site, as plain arrays, ordered
     * by category order and then title. Cached until anything changes.
     *
     * @param int $siteId
     * @return array<int, array{id: int, uid: string, title: string, preset: ?string, category: string, provider: string, description: string, privacyPolicyUrl: string, dataTransfers: string, cookies: array, headCode: string, bodyCode: string}>
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getLiveServices(int $siteId): array
    {
        $plugin = ConsentMate::getInstance();
        $cacheKey = "consentmate:services:{$plugin->getVersion()}:$siteId";

        return Craft::$app->getCache()->getOrSet($cacheKey, static function() use ($siteId, $plugin): array {
            $categoryOrder = array_flip(array_keys($plugin->getSettings()->getCategories()));

            /** @var Service[] $elements */
            $elements = Service::find()
                ->siteId($siteId)
                ->category(array_keys($categoryOrder))
                ->orderBy(['elements_sites.title' => SORT_ASC])
                ->all();

            $services = [];

            foreach ($elements as $element) {
                // Pasted code, or the code the preset generates from its IDs
                $code = $element->getOutputCode();

                $services[] = [
                    'id' => (int)$element->id,
                    'uid' => (string)$element->uid,
                    'title' => (string)$element->title,
                    'preset' => $element->getPreset()?->handle,
                    'category' => (string)$element->category,
                    'provider' => (string)$element->provider,
                    'description' => (string)$element->description,
                    'privacyPolicyUrl' => (string)$element->privacyPolicyUrl,
                    'dataTransfers' => (string)$element->dataTransfers,
                    'cookies' => $element->getCookies(),
                    'headCode' => $code['head'],
                    'bodyCode' => $code['body'],
                ];
            }

            usort($services, static fn(array $a, array $b) => $categoryOrder[$a['category']] <=> $categoryOrder[$b['category']]);

            return $services;
        }, null, $plugin->getCaches()->getDependency());
    }

    /**
     * Records a service's state before it's saved.
     *
     * @param Service $service
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function beforeSave(Service $service): void
    {
        if ($service->propagating || ElementHelper::isDraftOrRevision($service) || !$service->id) {
            return;
        }

        $this->_before[$service->id] = [
            'sites' => $this->_getLiveSiteIds($service->id),
            'category' => (new Query())
                ->select(['category'])
                ->from(Table::SERVICES)
                ->where(['id' => $service->id])
                ->scalar() ?: null,
        ];
    }

    /**
     * Reacts to a saved service, after it's propagated to all sites. Enabling
     * a service on a site, or moving it to another category, bumps the
     * consent revision.
     *
     * @param Service $service
     * @return void
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function afterPropagate(Service $service): void
    {
        if (ElementHelper::isDraftOrRevision($service)) {
            return;
        }

        $plugin = ConsentMate::getInstance();
        $before = $this->_before[$service->id] ?? ['sites' => [], 'category' => null];
        unset($this->_before[$service->id]);

        $liveSiteIds = $this->_getLiveSiteIds($service->id);
        $newSiteIds = array_diff($liveSiteIds, $before['sites']);
        $movedCategory = $before['category'] !== null && $before['category'] !== $service->category && !empty($liveSiteIds);

        if ($plugin->getSettings()->renewOnNewService && (!empty($newSiteIds) || $movedCategory)) {
            $reason = $movedCategory ? 'category-changed' : 'enabled';
            $plugin->getRevisions()->bump("$reason: $service->title");

            return;
        }

        $plugin->getCaches()->changed();
    }

    /**
     * Reacts to a restored service. Restoring a live service counts as
     * enabling it.
     *
     * @param Service $service
     * @return void
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function afterRestore(Service $service): void
    {
        $plugin = ConsentMate::getInstance();

        if ($plugin->getSettings()->renewOnNewService && !empty($this->_getLiveSiteIds($service->id))) {
            $plugin->getRevisions()->bump("restored: $service->title");

            return;
        }

        $plugin->getCaches()->changed();
    }

    /**
     * Reacts to a deleted service.
     *
     * @param Service $service
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function afterDelete(Service $service): void
    {
        if (ElementHelper::isDraftOrRevision($service)) {
            return;
        }

        ConsentMate::getInstance()->getCaches()->changed();
    }

    /**
     * Invalidates cached service data. Used when something other than a
     * service save changes what's live (e.g. a site is added).
     *
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function invalidate(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), Caches::CACHE_TAG);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the IDs of the sites a service is live on.
     *
     * @param int $id
     * @return int[]
     */
    private function _getLiveSiteIds(int $id): array
    {
        return array_map('intval', (new Query())
            ->select(['es.siteId'])
            ->from(['es' => CraftTable::ELEMENTS_SITES])
            ->innerJoin(['e' => CraftTable::ELEMENTS], '[[e.id]] = [[es.elementId]]')
            ->where([
                'es.elementId' => $id,
                'es.enabled' => true,
                'e.enabled' => true,
                'e.archived' => false,
                'e.dateDeleted' => null,
                'e.draftId' => null,
                'e.revisionId' => null,
            ])
            ->column());
    }
}
