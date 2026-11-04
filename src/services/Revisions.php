<?php

namespace vaersaagod\consentmate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use vaersaagod\consentmate\ConsentMate;
use vaersaagod\consentmate\db\Table;

/**
 * The consent revision
 *
 * Consent stored in a visitor's browser carries the revision it was given
 * for. When the current revision is newer, the banner is shown again. Each
 * bump is a row, which doubles as an audit trail.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Revisions extends Component
{
    // Private Properties
    // =========================================================================

    /**
     * @var int|null
     */
    private ?int $_current = null;

    // Public Methods
    // =========================================================================

    /**
     * Returns the current revision.
     *
     * @return int
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getCurrentRevision(): int
    {
        if ($this->_current !== null) {
            return $this->_current;
        }

        $current = (new Query())
            ->from(Table::REVISIONS)
            ->max('[[id]]');

        return $this->_current = (int)($current ?: 1);
    }

    /**
     * Bumps the revision, so every visitor is asked for consent again.
     *
     * @param string $reason
     * @return int The new revision
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function bump(string $reason): int
    {
        Db::insert(Table::REVISIONS, [
            'reason' => mb_substr($reason, 0, 255),
            'userId' => Craft::$app->getUser()->getId(),
        ]);

        $this->_current = (int)Craft::$app->getDb()->getLastInsertID();

        ConsentMate::getInstance()->getCaches()->changed();

        return $this->_current;
    }

    /**
     * Returns the most recent revisions, newest first.
     *
     * @param int $limit
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function getRecentRevisions(int $limit = 10): array
    {
        return (new Query())
            ->select(['r.id', 'r.reason', 'r.userId', 'r.dateCreated', 'u.username'])
            ->from(['r' => Table::REVISIONS])
            ->leftJoin(['u' => \craft\db\Table::USERS], '[[u.id]] = [[r.userId]]')
            ->orderBy(['r.id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }
}
