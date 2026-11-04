<?php

namespace vaersaagod\consentmate\services;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use vaersaagod\consentmate\db\Table;

/**
 * Snapshots of what visitors were shown
 *
 * GDPR asks that a controller can show what information a visitor got when
 * they consented. Each distinct combination of texts, categories, services
 * and revision is stored once per site, and consent log rows point to it.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Snapshots extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Stores a snapshot if it doesn't exist yet, and returns its hash.
     *
     * @param int $siteId
     * @param array $data
     * @return string
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function ensure(int $siteId, array $data): string
    {
        $hash = sha1($siteId . ':' . Json::encode($data));

        $exists = (new Query())
            ->from(Table::SNAPSHOTS)
            ->where(['hash' => $hash])
            ->exists();

        if (!$exists) {
            try {
                Db::insert(Table::SNAPSHOTS, [
                    'siteId' => $siteId,
                    'hash' => $hash,
                    // JSON columns are encoded by the query builder
                    'data' => $data,
                ]);
            } catch (\yii\db\IntegrityException) {
                // Inserted by a concurrent request
            }
        }

        return $hash;
    }

    /**
     * Returns a snapshot's ID by its hash.
     *
     * @param string $hash
     * @return int|null
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getIdByHash(string $hash): ?int
    {
        $id = (new Query())
            ->select(['id'])
            ->from(Table::SNAPSHOTS)
            ->where(['hash' => $hash])
            ->scalar();

        return $id ? (int)$id : null;
    }

    /**
     * Returns a snapshot's data by its ID.
     *
     * @param int $id
     * @return array|null
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getDataById(int $id): ?array
    {
        $data = (new Query())
            ->select(['data'])
            ->from(Table::SNAPSHOTS)
            ->where(['id' => $id])
            ->scalar();

        if ($data === false || $data === null) {
            return null;
        }

        $data = Json::decodeIfJson($data);

        return \is_array($data) ? $data : null;
    }
}
