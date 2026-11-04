<?php

/**
 * Plausible Analytics (the per-site script introduced in October 2025)
 *
 * Sources: Plausible's documentation and its tracker source (October 2026).
 * Plausible sets no cookies and no browser storage.
 */

return [
    'name' => 'Plausible',
    'category' => 'statistics',
    'provider' => 'Plausible Insights OÜ',
    'privacyPolicyUrl' => 'https://plausible.io/data-policy',
    'dataTransfers' => 'None (EU only)',
    'description' => 'Counts how many people visit the website and which pages they view, without cookies and without collecting personal data.',
    'cookies' => [],
    'fields' => [
        'scriptId' => [
            'label' => 'Script ID',
            'placeholder' => 'pa-XXXXXXXXXXXXXXXXXXXXX',
            'pattern' => '/^pa-[A-Za-z0-9_-]{21}$/',
        ],
    ],
    'headCode' => <<<'HTML'
<script async src="https://plausible.io/js/{scriptId}.js"></script>
<script>
window.plausible=window.plausible||function(){(plausible.q=plausible.q||[]).push(arguments)},plausible.init=plausible.init||function(i){plausible.o=i||{}};
plausible.init()
</script>
HTML,
    'notes' => 'Plausible sets no cookies. If your legal assessment allows it, move it to Strictly necessary so it runs without consent. The script ID starts with "pa-" and is in the snippet Plausible gives you. Sites on the older data-domain script, or a proxied script, can paste their own code on the Code tab.',
];
