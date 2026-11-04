<?php

/**
 * Fathom Analytics
 *
 * Sources: Fathom's documentation and EU isolation notes (October 2026).
 * Fathom sets no cookies.
 */

return [
    'name' => 'Fathom',
    'category' => 'statistics',
    'provider' => 'Conva Ventures Inc.',
    'privacyPolicyUrl' => 'https://usefathom.com/privacy',
    'dataTransfers' => 'Canada and USA (anonymised data only; IP addresses are removed on EU servers)',
    'description' => 'Counts how many people visit the website and which pages they view, without cookies and without storing personal data.',
    'cookies' => [],
    'fields' => [
        'siteId' => [
            'label' => 'Site ID',
            'placeholder' => 'ABCDEFGH',
            'pattern' => '/^[A-Z0-9]{4,12}$/',
        ],
    ],
    'headCode' => <<<'HTML'
<script src="https://cdn.usefathom.com/script.js" data-site="{siteId}" defer></script>
HTML,
    'notes' => 'Fathom sets no cookies. If your legal assessment allows it, move it to Strictly necessary so it runs without consent.',
];
