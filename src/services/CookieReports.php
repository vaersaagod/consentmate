<?php

namespace vaersaagod\consentmate\services;

use Carbon\Carbon;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\helpers\Db;
use craft\models\Site;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;
use vaersaagod\consentmate\elements\Service;
use yii\base\InvalidArgumentException;
use yii\db\Expression;

/**
 * Reports cookies seen in visitors' browsers that no service declares, or
 * that were set without consent
 *
 * Every consent save sends the site's own cookies to the server. Their names
 * are compared with the first-party cookies the services declare:
 *
 * - a cookie no service declares is reported as undeclared
 * - a declared cookie whose category the visitor hadn't granted when it was
 *   set is reported as a leak: something set it before consent (usually a
 *   script hardcoded in a template, outside `{% consent %}`)
 *
 * Third-party cookies never reach the server, so they can't be checked.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class CookieReports extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * A declared cookie that was present while its category wasn't granted.
     */
    public const STATUS_LEAK = 'leak';

    /**
     * A cookie no service declares.
     */
    public const STATUS_UNDECLARED = 'undeclared';

    /**
     * The most cookies checked per request.
     */
    private const MAX_COOKIES = 100;

    /**
     * The most reports kept per site. The save endpoint is public, so this
     * keeps made-up cookie names from filling the table.
     */
    private const MAX_REPORTS_PER_SITE = 200;

    // Public Methods
    // =========================================================================

    /**
     * Checks the cookies sent with a consent save.
     *
     * @param Site $site
     * @param string[] $cookieNames The names of the cookies the browser sent
     * @param mixed $previous The categories granted before this save, as posted by the runtime
     * @param string|null $path The page the visitor was on
     * @return array{undeclared: string[], leaks: string[]} The names reported
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function record(Site $site, array $cookieNames, mixed $previous, ?string $path): array
    {
        $reported = ['undeclared' => [], 'leaks' => []];
        $settings = ConsentMate::getInstance()->getSettings();

        if (!$settings->getCookieReport()['enabled']) {
            return $reported;
        }

        $required = $settings->getRequiredCategoryHandles();
        $granted = array_merge($required, $this->_normalizeCategories($previous));
        $declared = $this->_declaredCookies($site->id);
        $ignore = $this->_ignorePatterns();
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s');

        // The reports the site already has, which are counted again even when
        // the site is at the limit
        $existing = array_flip(array_map(
            static fn(array $row) => $row['status'] . ':' . $row['name'],
            (new Query())
                ->select(['name', 'status'])
                ->from(Table::COOKIE_REPORTS)
                ->where(['siteId' => $site->id])
                ->all()
        ));
        $room = self::MAX_REPORTS_PER_SITE - \count($existing);

        foreach (\array_slice($cookieNames, 0, self::MAX_COOKIES) as $name) {
            $name = (string)$name;

            if ($name === '' || mb_strlen($name) > 255 || $this->_matchesAny($name, $ignore)) {
                continue;
            }

            $match = null;

            foreach ($declared as $cookie) {
                if (preg_match($cookie['pattern'], $name)) {
                    $match = $cookie;
                    break;
                }
            }

            $status = match (true) {
                $match === null => self::STATUS_UNDECLARED,
                !\in_array($match['category'], $granted, true) => self::STATUS_LEAK,
                default => null,
            };

            if ($status === null) {
                continue;
            }

            if (!isset($existing["$status:$name"])) {
                if ($room <= 0) {
                    continue;
                }

                $existing["$status:$name"] = true;
                $room--;
            }

            $this->_upsert($site->id, $name, $status, $match['category'] ?? null, $match['serviceId'] ?? null, $path, $now);
            $reported[$status === self::STATUS_LEAK ? 'leaks' : 'undeclared'][] = $name;
        }

        return $reported;
    }

    /**
     * Returns the reports, leaks first, then by when they were last seen.
     *
     * @param bool $ignored Whether to return the ignored reports instead
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getReports(bool $ignored = false): array
    {
        $rows = (new Query())
            ->select([
                'r.id', 'r.siteId', 'r.name', 'r.status', 'r.category', 'r.serviceId', 'r.hits',
                'r.lastPath', 'r.ignored', 'r.firstSeen', 'r.lastSeen',
                'serviceTitle' => 'es.title',
            ])
            ->from(['r' => Table::COOKIE_REPORTS])
            ->leftJoin(['es' => CraftTable::ELEMENTS_SITES], '[[es.elementId]] = [[r.serviceId]] AND [[es.siteId]] = [[r.siteId]]')
            ->where(['r.ignored' => $ignored])
            ->orderBy([
                new Expression("CASE WHEN [[r.status]] = 'leak' THEN 0 ELSE 1 END"),
                'r.lastSeen' => SORT_DESC,
            ])
            ->all();

        foreach ($rows as &$row) {
            $row['ignored'] = (bool)$row['ignored'];
            $row['hits'] = (int)$row['hits'];
        }

        return $rows;
    }

    /**
     * Returns the number of reports that aren't ignored.
     *
     * @return int
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getIssueCount(): int
    {
        return (int)(new Query())
            ->from(Table::COOKIE_REPORTS)
            ->where(['ignored' => false])
            ->count();
    }

    /**
     * Ignores a report, or stops ignoring it. Ignored reports keep counting,
     * but aren't shown as issues.
     *
     * @param int $id
     * @param bool $ignored
     * @return void
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function setIgnored(int $id, bool $ignored): void
    {
        Db::update(Table::COOKIE_REPORTS, ['ignored' => $ignored], ['id' => $id]);
    }

    /**
     * Deletes a report, e.g. once a leak is fixed. It comes back if the
     * cookie is seen again.
     *
     * @param int $id
     * @return void
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function delete(int $id): void
    {
        Db::delete(Table::COOKIE_REPORTS, ['id' => $id]);
    }

    /**
     * Declares an undeclared cookie on a service, and removes the report.
     *
     * @param int $id
     * @param int $serviceId
     * @return Service The service the cookie was added to
     * @throws InvalidArgumentException if the report or service doesn't exist
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function addToService(int $id, int $serviceId): Service
    {
        $report = (new Query())
            ->from(Table::COOKIE_REPORTS)
            ->where(['id' => $id, 'status' => self::STATUS_UNDECLARED])
            ->one();

        if (!$report) {
            throw new InvalidArgumentException('Report not found.');
        }

        /** @var Service|null $service */
        $service = Service::find()
            ->id($serviceId)
            ->siteId($report['siteId'])
            ->status(null)
            ->one();

        if (!$service) {
            throw new InvalidArgumentException('Service not found.');
        }

        $cookies = $service->getCookies();
        $cookies[] = [
            'name' => $report['name'],
            'type' => 'cookie',
            'party' => 'first',
            'duration' => null,
            'unit' => 'session',
        ];
        $service->cookies = $cookies;

        if (!Craft::$app->getElements()->saveElement($service)) {
            throw new InvalidArgumentException(implode(' ', $service->getFirstErrors()));
        }

        $this->delete($id);

        return $service;
    }

    /**
     * Deletes reports that haven't been seen within the retention period.
     * Ignored reports are kept, so they stay ignored.
     *
     * @return int The number of reports deleted
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function purgeStale(): int
    {
        $retention = ConfigHelper::durationInSeconds(ConsentMate::getInstance()->getSettings()->getCookieReport()['retention']);

        if ($retention <= 0) {
            return 0;
        }

        return Db::delete(Table::COOKIE_REPORTS, [
            'and',
            ['ignored' => false],
            ['<', 'lastSeen', Carbon::now('UTC')->subSeconds($retention)->format('Y-m-d H:i:s')],
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the first-party cookies declared on a site, as patterns with
     * their category and service. Third-party cookies never reach the
     * server, so they're left out; a first-party cookie with the same name
     * as a declared third-party one is undeclared.
     *
     * @param int $siteId
     * @return array<int, array{pattern: string, category: string, serviceId: ?int}>
     */
    private function _declaredCookies(int $siteId): array
    {
        $declared = [];
        $categories = ConsentMate::getInstance()->getFrontend()->getSiteData($siteId)['categories'];

        foreach ($categories as $handle => $category) {
            foreach ($category['services'] as $service) {
                foreach ($service['cookies'] as $cookie) {
                    if ($cookie['party'] !== 'first' || $cookie['type'] !== 'cookie') {
                        continue;
                    }

                    $declared[] = [
                        'pattern' => $this->_toRegex($cookie['name']),
                        'category' => (string)$handle,
                        'serviceId' => $service['id'] ?? null,
                    ];
                }
            }
        }

        return $declared;
    }

    /**
     * Returns the patterns of cookies that are never reported: the config's
     * ignore list, and Craft's control panel cookies.
     *
     * @return string[]
     */
    private function _ignorePatterns(): array
    {
        $names = ConsentMate::getInstance()->getSettings()->getCookieReport()['ignore'];

        // Set on the CP login page ("Keep me signed in" remembers the username)
        $names[] = App::userConfig()['usernameCookie']['name'] ?? null;

        // The CP's own UI state (Craft.setCookie(), e.g. the sidebar), sent
        // along when a logged-in user visits the front end
        $names[] = 'Craft-*';

        return array_map([$this, '_toRegex'], array_filter(array_map('strval', $names)));
    }

    /**
     * Returns a regex for a cookie name with * wildcards.
     *
     * @param string $name
     * @return string
     */
    private function _toRegex(string $name): string
    {
        return '/^' . str_replace('\*', '.*', preg_quote($name, '/')) . '$/';
    }

    /**
     * @param string $name
     * @param string[] $patterns
     * @return bool
     */
    private function _matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the known category handles in a posted list.
     *
     * @param mixed $categories
     * @return string[]
     */
    private function _normalizeCategories(mixed $categories): array
    {
        if (!\is_array($categories)) {
            return [];
        }

        $known = array_keys(ConsentMate::getInstance()->getSettings()->getCategories());

        return array_values(array_intersect($known, array_filter($categories, 'is_string')));
    }

    /**
     * Adds a report, or counts another sighting of an existing one.
     *
     * @param int $siteId
     * @param string $name
     * @param string $status
     * @param string|null $category
     * @param int|null $serviceId
     * @param string|null $path
     * @param string $now
     * @return void
     * @throws \yii\db\Exception
     */
    private function _upsert(int $siteId, string $name, string $status, ?string $category, ?int $serviceId, ?string $path, string $now): void
    {
        $path = $path !== null ? mb_substr($path, 0, 255) : null;

        Db::upsert(Table::COOKIE_REPORTS, [
            'siteId' => $siteId,
            'name' => $name,
            'status' => $status,
            'category' => $category,
            'serviceId' => $serviceId,
            'hits' => 1,
            'lastPath' => $path,
            'firstSeen' => $now,
            'lastSeen' => $now,
        ], [
            'category' => $category,
            'serviceId' => $serviceId,
            'hits' => new Expression('[[hits]] + 1'),
            'lastPath' => $path,
            'lastSeen' => $now,
        ]);
    }
}
