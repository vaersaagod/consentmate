<?php

namespace vaersaagod\consentmate\web\assets\frontend;

use craft\web\AssetBundle;

/**
 * The front-end runtime and stylesheet
 *
 * The bundle isn't registered on the view: ConsentMate outputs its own tags
 * (the runtime needs `defer` and a nonce). It exists so the files are
 * published the way Craft Cloud expects: Cloud uploads every asset bundle's
 * source path to its CDN at build time, and doesn't support publishing
 * anything else at runtime.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class FrontendAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@vaersaagod/consentmate/web/assets/frontend/dist';

        parent::init();
    }
}
