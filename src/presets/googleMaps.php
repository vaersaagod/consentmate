<?php

/**
 * Google Maps (embeds and the Maps JavaScript API)
 *
 * Sources: a live test of the iframe embed and the JS API (October 2026).
 * No cookies were set for a visitor who isn't logged in to Google, but
 * Google receives the IP address.
 */

return [
    'name' => 'Google Maps',
    'category' => 'preferences',
    'provider' => 'Google Ireland Limited',
    'privacyPolicyUrl' => 'https://policies.google.com/privacy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Shows interactive maps from Google Maps. Google receives your IP address and information about how you use the map.',
    'cookies' => [],
    'notes' => 'Gate Google Maps embeds in templates with the preset handle "googleMaps". Maps set no cookies for visitors who aren\'t logged in to Google, but Google receives their IP address, so it still needs consent. Some sites use Marketing instead.',
];
