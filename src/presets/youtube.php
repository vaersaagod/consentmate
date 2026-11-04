<?php

/**
 * YouTube embeds (youtube.com and youtube-nocookie.com)
 *
 * Sources: Google's cookie documentation and a live test of both embed
 * domains from a Norwegian IP (October 2026). youtube-nocookie.com sets no
 * cookies, but still stores data in the browser.
 */

return [
    'name' => 'YouTube',
    'category' => 'marketing',
    'provider' => 'Google Ireland Limited',
    'privacyPolicyUrl' => 'https://policies.google.com/privacy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Shows videos hosted on YouTube. YouTube may collect information about how you watch videos and use it for its own purposes, including advertising.',
    'cookies' => [
        ['name' => 'YSC', 'type' => 'cookie', 'party' => 'third', 'unit' => 'session'],
        ['name' => 'VISITOR_INFO1_LIVE', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => '__Secure-YNID', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => 'VISITOR_PRIVACY_METADATA', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => '__Secure-ROLLOUT_TOKEN', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => 'ytidb::LAST_RESULT_ENTRY_KEY', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'yt-icons-last-purged', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'yt-player-caption-persistence', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'YtIdbMeta', 'type' => 'indexedDB', 'party' => 'third', 'unit' => 'persistent'],
    ],
    'notes' => 'Gate YouTube embeds in templates with the preset handle "youtube", so they follow this service\'s category. youtube-nocookie.com sets no cookies, but still stores data in the browser, so it needs consent too.',
];
