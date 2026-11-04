<?php

/**
 * Google Analytics 4 (gtag.js)
 *
 * Sources: Google's gtag.js setup guide and cookie usage page, checked
 * against the Open Cookie Database (October 2026).
 */

return [
    'name' => 'Google Analytics',
    'category' => 'statistics',
    'provider' => 'Google Ireland Limited',
    'privacyPolicyUrl' => 'https://policies.google.com/privacy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Measures how visitors use the website, such as which pages they visit and how they arrived. The information is used in aggregate to improve the website.',
    'cookies' => [
        ['name' => '_ga', 'type' => 'cookie', 'party' => 'first', 'duration' => 2, 'unit' => 'years'],
        ['name' => '_ga_*', 'type' => 'cookie', 'party' => 'first', 'duration' => 2, 'unit' => 'years'],
        ['name' => '_gcl_au', 'type' => 'cookie', 'party' => 'first', 'duration' => 3, 'unit' => 'months'],
    ],
    'fields' => [
        'measurementId' => [
            'label' => 'Measurement ID',
            'placeholder' => 'G-XXXXXXXXXX',
            'pattern' => '/^G-[A-Z0-9]{4,20}$/',
        ],
    ],
    'headCode' => <<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id={measurementId}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{measurementId}');
</script>
HTML,
    'notes' => 'If Google Analytics is loaded through Google Tag Manager, leave the measurement ID empty. The service then only declares the cookies.',
];
