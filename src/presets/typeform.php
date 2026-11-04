<?php

/**
 * Typeform embeds
 *
 * Sources: Typeform's cookie policy and a live test of the data-tf-widget
 * embed (October 2026).
 */

return [
    'name' => 'Typeform',
    'category' => 'preferences',
    'provider' => 'TYPEFORM, S.L.',
    'privacyPolicyUrl' => 'https://www.typeform.com/legal/privacy-policy',
    'dataTransfers' => 'USA (Standard Contractual Clauses)',
    'description' => 'Shows forms and surveys from Typeform. Typeform stores the answers and collects information about how the form is used.',
    'cookies' => [
        ['name' => 'tf_respondent_cc', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => 'attribution_user_id', 'type' => 'cookie', 'party' => 'third', 'duration' => 1, 'unit' => 'years'],
        ['name' => 'AWSALBTGCORS', 'type' => 'cookie', 'party' => 'third', 'duration' => 7, 'unit' => 'days'],
        ['name' => 'tf_random_id', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => '*-visitorId', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'rl_*', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
        ['name' => 'rudder.*', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
    ],
    'notes' => 'Gate Typeform embeds in templates with the preset handle "typeform". Enterprise accounts can keep their data in Typeform\'s EU data centre. Then nothing is transferred to the USA, so clear Data transfers.',
];
