<?php

/**
 * Adobe Fonts (Typekit)
 *
 * Sources: Adobe's Adobe Fonts privacy page and a live test (October 2026).
 * No cookies; the p.typekit.net request reports font usage to Adobe.
 */

return [
    'name' => 'Adobe Fonts',
    'category' => 'necessary',
    'provider' => 'Adobe Systems Software Ireland Limited',
    'privacyPolicyUrl' => 'https://www.adobe.com/privacy/policies/adobe-fonts.html',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Loads the fonts used on the website from Adobe. Adobe receives your IP address, but does not set cookies or store it.',
    'cookies' => [
        ['name' => 'p.typekit.net', 'type' => 'pixel', 'party' => 'third', 'unit' => 'session'],
    ],
    'notes' => 'Listed as Strictly necessary since the site needs its fonts, but Adobe receives visitors\' IP addresses. Adobe Fonts\' licence doesn\'t allow self-hosting; to self-host, buy a web licence from the foundry.',
];
