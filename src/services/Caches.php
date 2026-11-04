<?php

namespace vaersaagod\consentmate\services;

use Craft;
use craft\base\Component;
use vaersaagod\consentmate\events\ChangeEvent;
use yii\caching\TagDependency;

/**
 * Invalidates ConsentMate's own caches, and purges static page caches when
 * services, texts or the consent revision change
 *
 * Every page carries the services, texts and revision in its markup, so a
 * statically cached page goes stale on any change.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Caches extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * The cache tag for everything ConsentMate caches.
     */
    public const CACHE_TAG = 'consentmate';

    /**
     * @event ChangeEvent The event triggered after services, texts or the
     * consent revision changed, once per request. Use it to purge caches
     * ConsentMate doesn't know about.
     */
    public const EVENT_AFTER_CHANGE = 'afterChange';

    // Private Properties
    // =========================================================================

    /**
     * @var bool Whether a purge is already scheduled for the end of the request
     */
    private bool $_purgeScheduled = false;

    // Public Methods
    // =========================================================================

    /**
     * Returns a cache dependency that's invalidated on any change.
     *
     * @return TagDependency
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getDependency(): TagDependency
    {
        return new TagDependency(['tags' => [self::CACHE_TAG]]);
    }

    /**
     * Marks ConsentMate's data as changed: invalidates its own caches now,
     * and purges static caches once at the end of the request.
     *
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function changed(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);

        if ($this->_purgeScheduled) {
            return;
        }

        $this->_purgeScheduled = true;

        Craft::$app->onAfterRequest(function(): void {
            $this->_purgeScheduled = false;
            $this->purgeStaticCaches();
        });
    }

    /**
     * Purges static page caches: CacheMate and Blitz when installed, and
     * anything listening to EVENT_AFTER_CHANGE.
     *
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function purgeStaticCaches(): void
    {
        $plugins = Craft::$app->getPlugins();

        try {
            // Neither plugin is a dependency, so they're untyped here
            if ($plugins->isPluginEnabled('cachemate')) {
                /** @var mixed $cacheMate */
                $cacheMate = $plugins->getPlugin('cachemate');
                $cacheMate->getCachePurge()->purgeAll();
            }

            if ($plugins->isPluginEnabled('blitz')) {
                /** @var mixed $blitz */
                $blitz = $plugins->getPlugin('blitz');
                $blitz->refreshCache->refreshAll();
            }
        } catch (\Throwable $e) {
            Craft::error('Couldn\'t purge static caches: ' . $e->getMessage(), __METHOD__);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_CHANGE)) {
            $this->trigger(self::EVENT_AFTER_CHANGE, new ChangeEvent());
        }
    }
}
