<?php

namespace vaersaagod\consentmate\db;

/**
 * ConsentMate database table names
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
abstract class Table
{
    // Const Properties
    // =========================================================================

    /** @var string Service elements (non-localized attributes) */
    public const SERVICES = '{{%consentmate_services}}';

    /** @var string Service elements (localized attributes) */
    public const SERVICES_SITES = '{{%consentmate_services_sites}}';

    /** @var string Banner and category copy, per site */
    public const TEXTS = '{{%consentmate_texts}}';

    /** @var string Consent revisions; the newest id is the current revision */
    public const REVISIONS = '{{%consentmate_revisions}}';

    /** @var string Snapshots of what visitors were shown, per site */
    public const SNAPSHOTS = '{{%consentmate_snapshots}}';

    /** @var string The consent log */
    public const CONSENTS = '{{%consentmate_consents}}';

    /** @var string Cookies seen in visitors' browsers that are undeclared, or set without consent */
    public const COOKIE_REPORTS = '{{%consentmate_cookie_reports}}';
}
