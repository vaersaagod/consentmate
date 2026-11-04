<?php

/**
 * Mapbox GL JS
 *
 * Sources: Mapbox's privacy policy and the mapbox-gl v3 source (October
 * 2026). Mapbox sends telemetry ("map.load") with an identifier that's
 * regenerated every 24 hours.
 */

return [
    'name' => 'Mapbox',
    'category' => 'preferences',
    'provider' => 'Mapbox, Inc.',
    'privacyPolicyUrl' => 'https://www.mapbox.com/legal/privacy',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Shows interactive maps from Mapbox. Mapbox receives your IP address and an anonymous identifier that changes every 24 hours.',
    'cookies' => [
        ['name' => 'mapbox.eventData.uuid:*', 'type' => 'localStorage', 'party' => 'first', 'unit' => 'persistent'],
        ['name' => 'mapbox.eventData.uuidTimestamp:*', 'type' => 'localStorage', 'party' => 'first', 'unit' => 'persistent'],
        ['name' => 'mapbox.eventData:*', 'type' => 'localStorage', 'party' => 'first', 'unit' => 'persistent'],
    ],
    'notes' => 'Mapbox is usually bundled in the site\'s own JavaScript, so the developer has to start the map only once this category is allowed, using the consent JavaScript API. Use Strictly necessary instead only if the map is the core of the page.',
];
