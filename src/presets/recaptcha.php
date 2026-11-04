<?php

/**
 * Google reCAPTCHA (v2 and v3)
 *
 * Sources: Google Cloud's reCAPTCHA terms and a live test (October 2026).
 * Since April 2026, Google acts as a processor under the Google Cloud terms.
 */

return [
    'name' => 'Google reCAPTCHA',
    'category' => 'necessary',
    'provider' => 'Google Cloud EMEA Limited',
    'privacyPolicyUrl' => 'https://cloud.google.com/terms/cloud-privacy-notice',
    'dataTransfers' => 'USA (EU-US Data Privacy Framework)',
    'description' => 'Protects our forms against spam and abuse by checking that the visitor is a human.',
    'cookies' => [
        ['name' => '_GRECAPTCHA', 'type' => 'cookie', 'party' => 'third', 'duration' => 6, 'unit' => 'months'],
        ['name' => '_grecaptcha', 'type' => 'localStorage', 'party' => 'first', 'unit' => 'persistent'],
        ['name' => 'rc::a', 'type' => 'localStorage', 'party' => 'third', 'unit' => 'persistent'],
    ],
    'notes' => 'Listed as Strictly necessary because forms don\'t work without it, but this is disputed: some data protection authorities say reCAPTCHA needs consent. Loading it from www.recaptcha.net instead of www.google.com avoids sending Google\'s cookies.',
];
