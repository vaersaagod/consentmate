<?php

/**
 * LinkedIn Insight Tag
 *
 * Sources: LinkedIn's cookie table (updated August 2026) and insight.min.js.
 */

return [
    'name' => 'LinkedIn Insight Tag',
    'category' => 'marketing',
    'provider' => 'LinkedIn Ireland Unlimited Company',
    'privacyPolicyUrl' => 'https://www.linkedin.com/legal/privacy-policy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Measures the results of our LinkedIn ads and lets us show ads to people who have visited the website.',
    'cookies' => [
        ['name' => 'li_adsId', 'type' => 'cookie', 'party' => 'first', 'duration' => 180, 'unit' => 'days'],
        ['name' => 'li_adsId', 'type' => 'localStorage', 'party' => 'first', 'unit' => 'persistent'],
        ['name' => 'li_fat_id', 'type' => 'cookie', 'party' => 'first', 'duration' => 30, 'unit' => 'days'],
        ['name' => 'li_giant', 'type' => 'cookie', 'party' => 'first', 'duration' => 7, 'unit' => 'days'],
        ['name' => 'bcookie', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => 'lidc', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'days'],
        ['name' => 'li_sugr', 'type' => 'cookie', 'party' => 'third', 'duration' => 90, 'unit' => 'days'],
        ['name' => 'UserMatchHistory', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'days'],
        ['name' => 'AnalyticsSyncHistory', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'days'],
        ['name' => 'li_gc', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => 'li_mc', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => 'lms_ads', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'days'],
        ['name' => 'lms_analytics', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'days'],
        ['name' => '_guid', 'type' => 'cookie', 'party' => 'third', 'duration' => 90, 'unit' => 'days'],
        ['name' => 'ar_debug', 'type' => 'cookie', 'party' => 'third', 'unit' => 'session'],
        ['name' => '__cf_bm', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'minutes'],
    ],
    'fields' => [
        'partnerId' => [
            'label' => 'Partner ID',
            'placeholder' => '1234567',
            'pattern' => '/^[1-9][0-9]{0,9}$/',
        ],
    ],
    'notes' => 'If the Insight Tag is loaded through Google Tag Manager, leave the partner ID empty. The service then only declares the cookies.',
    'bodyCode' => <<<'HTML'
<script type="text/javascript">
_linkedin_partner_id = "{partnerId}";
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
window._linkedin_data_partner_ids.push(_linkedin_partner_id);
</script><script type="text/javascript">
(function(l) {
if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};
window.lintrk.q=[]}
var s = document.getElementsByTagName("script")[0];
var b = document.createElement("script");
b.type = "text/javascript";b.async = true;
b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
s.parentNode.insertBefore(b, s);})(window.lintrk);
</script>
HTML,
];
