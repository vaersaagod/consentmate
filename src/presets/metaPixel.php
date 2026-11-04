<?php

/**
 * Meta Pixel (base code)
 *
 * Sources: Meta's Pixel base code and cookie documentation, and fbevents.js
 * (October 2026).
 */

return [
    'name' => 'Meta Pixel',
    'category' => 'marketing',
    'provider' => 'Meta Platforms Ireland Limited',
    'privacyPolicyUrl' => 'https://www.facebook.com/privacy/policy/',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Measures the results of our ads on Facebook and Instagram, and lets us show ads to people who have visited the website.',
    'cookies' => [
        ['name' => '_fbp', 'type' => 'cookie', 'party' => 'first', 'duration' => 3, 'unit' => 'months'],
        ['name' => '_fbc', 'type' => 'cookie', 'party' => 'first', 'duration' => 3, 'unit' => 'months'],
        ['name' => 'fr', 'type' => 'cookie', 'party' => 'third', 'duration' => 3, 'unit' => 'months'],
    ],
    'fields' => [
        'pixelId' => [
            'label' => 'Pixel ID',
            'placeholder' => '123456789012345',
            'pattern' => '/^[0-9]{15,17}$/',
        ],
    ],
    'headCode' => <<<'HTML'
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{pixelId}');
fbq('track', 'PageView');
</script>
HTML,
    'notes' => 'If the pixel is loaded through Google Tag Manager, leave the pixel ID empty. The service then only declares the cookies.',
];
