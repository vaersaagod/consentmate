<?php

/**
 * Vimeo embeds (player.vimeo.com)
 *
 * Sources: Vimeo's cookie policy and a live test with and without dnt=1
 * (October 2026).
 */

return [
    'name' => 'Vimeo',
    'category' => 'statistics',
    'provider' => 'Vimeo.com, Inc.',
    'privacyPolicyUrl' => 'https://vimeo.com/privacy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Shows videos hosted on Vimeo and gives us statistics on how they are viewed.',
    'cookies' => [
        ['name' => 'vuid', 'type' => 'cookie', 'party' => 'third', 'duration' => 2, 'unit' => 'years'],
        ['name' => 'player', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => '__cf_bm', 'type' => 'cookie', 'party' => 'third', 'duration' => 30, 'unit' => 'minutes'],
        ['name' => 'cf_clearance', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => '_cfuvid', 'type' => 'cookie', 'party' => 'third', 'unit' => 'session'],
        ['name' => 'player_clearance', 'type' => 'cookie', 'party' => 'third', 'duration' => 7, 'unit' => 'days'],
        ['name' => 'LOCAL_STORAGE_ID_*', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'PLAYER_PICOX_SAMPLING_SEED', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'sync_active', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'SESSION_STORAGE_ID_*', 'type' => 'sessionStorage', 'party' => 'third', 'unit' => 'session'],
    ],
    'notes' => 'Gate Vimeo embeds in templates with the preset handle "vimeo". With dnt=1 in the embed URL, Vimeo only sets its security cookies, and Preferences can be enough.',
];
