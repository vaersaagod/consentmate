<?php

/**
 * Instagram embeds
 *
 * Sources: Meta's cookie table (which Instagram's cookie policy points to),
 * instagram.com's response headers and a live test (October 2026, durations
 * rechecked November 2026). A fresh browser got no cookies; Instagram users'
 * own cookies are sent.
 */

return [
    'name' => 'Instagram',
    'category' => 'marketing',
    'provider' => 'Meta Platforms Ireland Limited',
    'privacyPolicyUrl' => 'https://privacycenter.instagram.com/policy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Shows posts from Instagram. Instagram can see that you visited the page and may link this to your Instagram account.',
    'cookies' => [
        ['name' => 'csrftoken', 'type' => 'cookie', 'party' => 'third', 'duration' => 400, 'unit' => 'days'],
        ['name' => 'mid', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => 'ig_did', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => 'datr', 'type' => 'cookie', 'party' => 'third', 'duration' => 400, 'unit' => 'days'],
    ],
    'notes' => 'Gate Instagram embeds in templates with the preset handle "instagram". The cookies are only sent for visitors who use Instagram.',
];
