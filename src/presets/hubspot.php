<?php

/**
 * HubSpot tracking code
 *
 * Sources: HubSpot's tracking code and cookie documentation (October 2026).
 * js.hs-scripts.com redirects EU portals to js-eu1.hs-scripts.com.
 */

return [
    'name' => 'HubSpot',
    'category' => 'marketing',
    'provider' => 'HubSpot Ireland Limited',
    'privacyPolicyUrl' => 'https://legal.hubspot.com/privacy-policy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Tracks visits to the website and connects them to enquiries, for example when someone fills in a form, so we can follow up and measure our marketing.',
    'cookies' => [
        ['name' => '__hstc', 'type' => 'cookie', 'party' => 'first', 'duration' => 6, 'unit' => 'months'],
        ['name' => 'hubspotutk', 'type' => 'cookie', 'party' => 'first', 'duration' => 6, 'unit' => 'months'],
        ['name' => '__hssc', 'type' => 'cookie', 'party' => 'first', 'duration' => 30, 'unit' => 'minutes'],
        ['name' => '__hssrc', 'type' => 'cookie', 'party' => 'first', 'unit' => 'session'],
        ['name' => 'messagesUtk', 'type' => 'cookie', 'party' => 'first', 'duration' => 6, 'unit' => 'months'],
        ['name' => '__cf_bm', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'minutes'],
    ],
    'fields' => [
        'hubId' => [
            'label' => 'Hub ID',
            'placeholder' => '12345678',
            'pattern' => '/^[1-9][0-9]{0,9}$/',
        ],
    ],
    'bodyCode' => <<<'HTML'
<script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/{hubId}.js"></script>
HTML,
    'notes' => 'Turn off HubSpot\'s own cookie banner in HubSpot, or visitors get two banners. messagesUtk is only set when HubSpot chat is used. If ad pixels are set up in HubSpot\'s ads tool, HubSpot also loads them (Meta, LinkedIn, Google), and they set their own cookies: add them as services from their presets.',
];
