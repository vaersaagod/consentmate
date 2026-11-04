<?php

/**
 * Facebook embeds (page plugin, video plugin, posts)
 *
 * Sources: Meta's cookie policy and its cookie table, and a live test
 * (October 2026, durations rechecked November 2026). A fresh
 * browser got no cookies; the cookies below are those of Facebook users,
 * which are sent and may be refreshed.
 */

return [
    'name' => 'Facebook',
    'category' => 'marketing',
    'provider' => 'Meta Platforms Ireland Limited',
    'privacyPolicyUrl' => 'https://www.facebook.com/privacy/policy/',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Shows posts, pages and videos from Facebook. Facebook can see that you visited the page and may link this to your Facebook account.',
    'cookies' => [
        ['name' => 'fr', 'type' => 'cookie', 'party' => 'third', 'duration' => 3, 'unit' => 'months'],
        ['name' => 'datr', 'type' => 'cookie', 'party' => 'third', 'duration' => 400, 'unit' => 'days'],
        ['name' => 'sb', 'type' => 'cookie', 'party' => 'third', 'duration' => 400, 'unit' => 'days'],
        ['name' => 'c_user', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => 'xs', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
    ],
    'notes' => 'Gate Facebook embeds in templates with the preset handle "facebook". For the Meta Pixel, use the Meta Pixel preset instead.',
];
