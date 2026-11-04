<?php

namespace vaersaagod\consentmate\services;

use Carbon\Carbon;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\ConfigHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\models\Site;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;
use yii\base\InvalidArgumentException;

/**
 * Saves consents (the consent cookie and the consent log), and reads the log
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Consents extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * The actions a visitor can take.
     */
    public const ACTIONS = ['accept-all', 'reject-all', 'custom', 'placeholder', 'withdraw'];

    /**
     * How many rows to delete per query when purging.
     */
    private const PURGE_BATCH_SIZE = 5000;

    // Public Methods
    // =========================================================================

    /**
     * Validates a consent posted by the runtime, logs it, and returns the
     * normalized consent.
     *
     * @param array $payload
     * @param Site $site
     * @return array{consentId: string, categories: string[], action: string, revision: int, timestamp: int}
     * @throws InvalidArgumentException if the payload is invalid
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function save(array $payload, Site $site): array
    {
        $plugin = ConsentMate::getInstance();
        $settings = $plugin->getSettings();

        $consentId = strtolower((string)($payload['consentId'] ?? ''));

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $consentId)) {
            throw new InvalidArgumentException('Invalid consent ID.');
        }

        $action = (string)($payload['action'] ?? '');

        if (!\in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid action.');
        }

        $categories = $payload['categories'] ?? [];

        if (!\is_array($categories)) {
            throw new InvalidArgumentException('Invalid categories.');
        }

        // Only known categories, always including the required ones, in config order
        $known = array_keys($settings->getCategories());
        $posted = array_filter($categories, 'is_string');
        $granted = array_values(array_filter($known, static fn(string $handle) => \in_array($handle, $posted, true)));
        $granted = array_values(array_unique(array_merge($settings->getRequiredCategoryHandles(), $granted)));
        usort($granted, static fn(string $a, string $b) => array_search($a, $known, true) <=> array_search($b, $known, true));

        // A page can be older than the current revision (a cached page), but
        // never newer
        $currentRevision = $plugin->getRevisions()->getCurrentRevision();
        $revision = max(1, min((int)($payload['revision'] ?? $currentRevision), $currentRevision));

        $consent = [
            'consentId' => $consentId,
            'categories' => $granted,
            'action' => $action,
            'revision' => $revision,
            'timestamp' => time(),
        ];

        $log = $settings->getLog();

        if (!$log['enabled']) {
            return $consent;
        }

        $snapshotHash = (string)($payload['snapshot'] ?? '');
        $snapshotId = preg_match('/^[0-9a-f]{40}$/', $snapshotHash)
            ? $plugin->getSnapshots()->getIdByHash($snapshotHash)
            : null;

        $path = (string)($payload['path'] ?? '');
        $path = mb_substr((string)parse_url($path, PHP_URL_PATH), 0, 255);

        $language = (string)($payload['language'] ?? '');
        $language = preg_match('/^[a-zA-Z]{2,3}([-_][a-zA-Z0-9]{2,8})?$/', $language) ? $language : $site->language;

        $request = Craft::$app->getRequest();
        $userAgent = $log['userAgent'] ? mb_substr((string)$request->getUserAgent(), 0, 512) : null;
        $ipHash = $log['ipHash'] ? $this->hashIp((string)$request->getUserIP()) : null;

        Db::insert(Table::CONSENTS, [
            'consentId' => $consentId,
            'siteId' => $site->id,
            'snapshotId' => $snapshotId,
            'revision' => $revision,
            'action' => $action,
            // JSON columns are encoded by the query builder
            'categories' => $granted,
            'gpc' => (bool)($payload['gpc'] ?? false),
            'path' => $path !== '' ? $path : null,
            'language' => mb_substr($language, 0, 12),
            'userAgent' => $userAgent !== '' ? $userAgent : null,
            'ipHash' => $ipHash,
        ]);

        return $consent;
    }

    /**
     * Returns the `Set-Cookie` header value for a consent. The cookie is set
     * from the server so browsers that cap cookies set from JavaScript
     * (Safari's ITP: 7 days) keep it for its full lifetime. It's not
     * HttpOnly, since the runtime reads it.
     *
     * @param array $consent As returned by save()
     * @return string
     * @throws \yii\base\InvalidConfigException
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCookieHeader(array $consent): string
    {
        $settings = ConsentMate::getInstance()->getSettings();
        $required = $settings->getRequiredCategoryHandles();
        $hasOptional = !empty(array_diff($consent['categories'], $required));
        $maxAge = $hasOptional ? $settings->getConsentLifetimeSeconds() : $settings->getRefusalLifetimeSeconds();

        // The same format the runtime writes; it reads it with
        // JSON.parse(decodeURIComponent(value))
        $value = rawurlencode(Json::encode([
            'id' => $consent['consentId'],
            'r' => $consent['revision'],
            'c' => $consent['categories'],
            't' => $consent['timestamp'],
        ]));

        $parts = [
            "$settings->cookieName=$value",
            'Path=/',
            "Max-Age=$maxAge",
            'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge),
            'SameSite=Lax',
        ];

        if ($settings->cookieDomain) {
            $parts[] = "Domain=$settings->cookieDomain";
        }

        if (Craft::$app->getRequest()->getIsSecureConnection()) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    /**
     * Returns a keyed hash of an IP address. The address itself is never
     * stored.
     *
     * @param string $ip
     * @return string
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, Craft::$app->getConfig()->getGeneral()->securityKey);
    }

    /**
     * Returns a page of log rows, newest first.
     *
     * @param array $criteria `search` (a consent ID, or part of one), `siteId`, `action`
     * @param int $page
     * @param int $perPage
     * @return array{rows: array, total: int}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getLogPage(array $criteria = [], int $page = 1, int $perPage = 50): array
    {
        $query = $this->_logQuery($criteria);
        $total = (int)$query->count();

        $rows = $query
            ->select(['c.id', 'c.consentId', 'c.siteId', 'c.revision', 'c.action', 'c.categories', 'c.gpc', 'c.path', 'c.dateCreated'])
            ->orderBy(['c.id' => SORT_DESC])
            ->offset(max(0, $page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        return [
            'rows' => array_map([$this, '_normalizeRow'], $rows),
            'total' => $total,
        ];
    }

    /**
     * Returns every log row for a consent ID, oldest first.
     *
     * @param string $consentId
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getHistory(string $consentId): array
    {
        $rows = (new Query())
            ->from(['c' => Table::CONSENTS])
            ->where(['c.consentId' => strtolower($consentId)])
            ->orderBy(['c.id' => SORT_ASC])
            ->all();

        return array_map([$this, '_normalizeRow'], $rows);
    }

    /**
     * Returns totals for the last days: all consents, and how many were each
     * action.
     *
     * @param int $days
     * @param int|null $siteId
     * @return array{total: int, actions: array<string, int>}
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getSummary(int $days = 30, ?int $siteId = null): array
    {
        $since = Carbon::now('UTC')->subDays($days)->format('Y-m-d H:i:s');

        $counts = (new Query())
            ->select(['action', 'count' => 'COUNT(*)'])
            ->from(Table::CONSENTS)
            ->where(['>=', 'dateCreated', $since])
            ->andFilterWhere(['siteId' => $siteId])
            ->groupBy(['action'])
            ->pairs();

        $actions = [];

        foreach (self::ACTIONS as $action) {
            $actions[$action] = (int)($counts[$action] ?? 0);
        }

        return [
            'total' => array_sum($actions),
            'actions' => $actions,
        ];
    }

    /**
     * Deletes log rows older than the retention period, and snapshots no log
     * row refers to any more (keeping each site's newest).
     *
     * @return int The number of log rows deleted
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function purgeExpired(): int
    {
        $retention = ConfigHelper::durationInSeconds(ConsentMate::getInstance()->getSettings()->getLog()['retention']);

        if ($retention <= 0) {
            return 0;
        }

        $cutoff = Carbon::now('UTC')->subSeconds($retention)->format('Y-m-d H:i:s');
        $deleted = 0;

        // Delete in batches, so a large backlog doesn't lock the table
        do {
            $ids = (new Query())
                ->select(['id'])
                ->from(Table::CONSENTS)
                ->where(['<', 'dateCreated', $cutoff])
                ->limit(self::PURGE_BATCH_SIZE)
                ->column();

            if (!empty($ids)) {
                $deleted += Db::delete(Table::CONSENTS, ['id' => $ids]);
            }
        } while (\count($ids) === self::PURGE_BATCH_SIZE);

        $newestPerSite = (new Query())
            ->select(['MAX([[id]])'])
            ->from(Table::SNAPSHOTS)
            ->groupBy(['siteId'])
            ->column();

        $referenced = (new Query())
            ->select(['snapshotId'])
            ->distinct()
            ->from(Table::CONSENTS)
            ->where(['not', ['snapshotId' => null]]);

        Db::delete(Table::SNAPSHOTS, [
            'and',
            ['<', 'dateCreated', $cutoff],
            ['not', ['id' => $newestPerSite]],
            ['not', ['id' => $referenced]],
        ]);

        return $deleted;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $criteria
     * @return Query
     */
    private function _logQuery(array $criteria): Query
    {
        $query = (new Query())->from(['c' => Table::CONSENTS]);

        $search = strtolower(trim((string)($criteria['search'] ?? '')));

        if ($search !== '') {
            $query->andWhere(['like', 'c.consentId', $search]);
        }

        if (!empty($criteria['siteId'])) {
            $query->andWhere(['c.siteId' => (int)$criteria['siteId']]);
        }

        if (!empty($criteria['action']) && \in_array($criteria['action'], self::ACTIONS, true)) {
            $query->andWhere(['c.action' => $criteria['action']]);
        }

        return $query;
    }

    /**
     * @param array $row
     * @return array
     */
    private function _normalizeRow(array $row): array
    {
        $categories = Json::decodeIfJson($row['categories'] ?? '[]');
        $row['categories'] = \is_array($categories) ? $categories : [];
        $row['gpc'] = (bool)($row['gpc'] ?? false);

        return $row;
    }
}
