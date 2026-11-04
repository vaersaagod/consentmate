<?php

/**
 * Spotify embeds (open.spotify.com/embed)
 *
 * Sources: Spotify's cookie policy and a live test (October 2026).
 */

return [
    'name' => 'Spotify',
    'category' => 'marketing',
    'provider' => 'Spotify AB',
    'privacyPolicyUrl' => 'https://www.spotify.com/legal/privacy-policy/',
    'dataTransfers' => 'Countries outside the EU/EEA, including the USA (Standard Contractual Clauses)',
    'description' => 'Plays music and podcasts from Spotify on the website. Spotify may recognise your browser and collect information about what you play.',
    'cookies' => [
        ['name' => 'sp_t', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => 'sp_landing', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'days'],
        ['name' => 'sentryReplaySession', 'type' => 'sessionStorage', 'party' => 'third', 'unit' => 'session'],
    ],
    'notes' => 'Gate Spotify embeds in templates with the preset handle "spotify".',
];
