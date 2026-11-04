<?php

namespace vaersaagod\consentmate\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;
use vaersaagod\consentmate\db\Table;
use vaersaagod\consentmate\elements\Service;

/**
 * Creates the ConsentMate tables
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::SERVICES)) {
            $this->createTable(Table::SERVICES, [
                'id' => $this->integer()->notNull(),
                'category' => $this->string(64)->notNull(),
                'provider' => $this->string(),
                'cookies' => $this->json(),
                'headCode' => $this->mediumText(),
                'bodyCode' => $this->mediumText(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]])',
            ]);

            $this->createIndex(null, Table::SERVICES, ['category']);
            $this->addForeignKey(null, Table::SERVICES, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
        }

        if (!$this->db->tableExists(Table::SERVICES_SITES)) {
            $this->createTable(Table::SERVICES_SITES, [
                'id' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'description' => $this->text(),
                'privacyPolicyUrl' => $this->string(),
                'dataTransfers' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]], [[siteId]])',
            ]);

            $this->addForeignKey(null, Table::SERVICES_SITES, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
            $this->addForeignKey(null, Table::SERVICES_SITES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(Table::TEXTS)) {
            $this->createTable(Table::TEXTS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'texts' => $this->json(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Table::TEXTS, ['siteId'], true);
            $this->addForeignKey(null, Table::TEXTS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(Table::REVISIONS)) {
            $this->createTable(Table::REVISIONS, [
                'id' => $this->primaryKey(),
                'reason' => $this->string(),
                'userId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, Table::REVISIONS, ['userId'], CraftTable::USERS, ['id'], 'SET NULL');
        }

        if (!$this->db->tableExists(Table::SNAPSHOTS)) {
            $this->createTable(Table::SNAPSHOTS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'hash' => $this->char(40)->notNull(),
                'data' => $this->json(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Table::SNAPSHOTS, ['hash'], true);
            $this->createIndex(null, Table::SNAPSHOTS, ['siteId', 'dateCreated']);
            $this->addForeignKey(null, Table::SNAPSHOTS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(Table::CONSENTS)) {
            $this->createTable(Table::CONSENTS, [
                'id' => $this->bigPrimaryKey(),
                'consentId' => $this->char(36)->notNull(),
                'siteId' => $this->integer()->notNull(),
                'snapshotId' => $this->integer(),
                'revision' => $this->integer()->notNull(),
                'action' => $this->string(16)->notNull(),
                'categories' => $this->json(),
                'gpc' => $this->boolean()->notNull()->defaultValue(false),
                'path' => $this->string(),
                'language' => $this->string(12),
                'userAgent' => $this->string(512),
                'ipHash' => $this->char(64),
                'dateCreated' => $this->dateTime()->notNull(),
            ]);

            $this->createIndex(null, Table::CONSENTS, ['consentId']);
            $this->createIndex(null, Table::CONSENTS, ['dateCreated']);
            $this->createIndex(null, Table::CONSENTS, ['siteId', 'dateCreated']);
            $this->addForeignKey(null, Table::CONSENTS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, Table::CONSENTS, ['snapshotId'], Table::SNAPSHOTS, ['id'], 'SET NULL');
        }

        self::createCookieReportsTable($this);
        self::addServicePresetColumns($this);
        self::addPresetCookieColumns($this);

        // Start at revision 1
        if (!(new Query())->from(Table::REVISIONS)->exists()) {
            $this->insert(Table::REVISIONS, [
                'reason' => 'install',
            ]);
        }

        return true;
    }

    /**
     * Adds the preset columns to the services table. Shared with the migration
     * that added them to existing installs.
     *
     * @param Migration $migration
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function addServicePresetColumns(Migration $migration): void
    {
        if (!$migration->db->columnExists(Table::SERVICES, 'preset')) {
            $migration->addColumn(Table::SERVICES, 'preset', $migration->string(64)->after('provider'));
            $migration->createIndex(null, Table::SERVICES, ['preset']);
        }

        if (!$migration->db->columnExists(Table::SERVICES, 'presetValues')) {
            $migration->addColumn(Table::SERVICES, 'presetValues', $migration->json()->after('preset'));
        }
    }

    /**
     * Adds the columns that keep a service's cookies in step with its preset:
     * the preset's cookies as last applied, and what the last update changed.
     * Shared with the migration that added them to existing installs.
     *
     * @param Migration $migration
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.4
     */
    public static function addPresetCookieColumns(Migration $migration): void
    {
        if (!$migration->db->columnExists(Table::SERVICES, 'presetCookies')) {
            $migration->addColumn(Table::SERVICES, 'presetCookies', $migration->json()->after('presetValues'));
        }

        if (!$migration->db->columnExists(Table::SERVICES, 'presetUpdate')) {
            $migration->addColumn(Table::SERVICES, 'presetUpdate', $migration->json()->after('presetCookies'));
        }
    }

    /**
     * Creates the cookie reports table. Shared with the migration that added
     * it to existing installs.
     *
     * @param Migration $migration
     * @return void
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public static function createCookieReportsTable(Migration $migration): void
    {
        if ($migration->db->tableExists(Table::COOKIE_REPORTS)) {
            return;
        }

        $migration->createTable(Table::COOKIE_REPORTS, [
            'id' => $migration->primaryKey(),
            'siteId' => $migration->integer()->notNull(),
            'name' => $migration->string()->notNull(),
            'status' => $migration->string(16)->notNull(),
            'category' => $migration->string(64),
            'serviceId' => $migration->integer(),
            'hits' => $migration->integer()->notNull()->defaultValue(1),
            'lastPath' => $migration->string(),
            'ignored' => $migration->boolean()->notNull()->defaultValue(false),
            'firstSeen' => $migration->dateTime()->notNull(),
            'lastSeen' => $migration->dateTime()->notNull(),
            'dateCreated' => $migration->dateTime()->notNull(),
            'dateUpdated' => $migration->dateTime()->notNull(),
            'uid' => $migration->uid(),
        ]);

        $migration->createIndex(null, Table::COOKIE_REPORTS, ['siteId', 'name', 'status'], true);
        $migration->createIndex(null, Table::COOKIE_REPORTS, ['ignored', 'status']);
        $migration->addForeignKey(null, Table::COOKIE_REPORTS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', 'CASCADE');
        $migration->addForeignKey(null, Table::COOKIE_REPORTS, ['serviceId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Delete the service elements, so they don't linger in the elements table
        $this->delete(CraftTable::ELEMENTS, ['type' => Service::class]);

        $this->dropTableIfExists(Table::COOKIE_REPORTS);
        $this->dropTableIfExists(Table::CONSENTS);
        $this->dropTableIfExists(Table::SNAPSHOTS);
        $this->dropTableIfExists(Table::REVISIONS);
        $this->dropTableIfExists(Table::TEXTS);
        $this->dropTableIfExists(Table::SERVICES_SITES);
        $this->dropTableIfExists(Table::SERVICES);

        Craft::$app->getElements()->invalidateCachesForElementType(Service::class);

        return true;
    }
}
