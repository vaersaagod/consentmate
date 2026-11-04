<?php

namespace vaersaagod\consentmate\variables;

use Craft;
use craft\helpers\Template;
use Twig\Markup;
use vaersaagod\consentmate\ConsentMate;

/**
 * `craft.consentmate`
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentMateVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Outputs the `<head>` markup. The same as `{% hook 'consentmateHead' %}`.
     *
     * @return Markup
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function head(): Markup
    {
        return Template::raw(ConsentMate::getInstance()->getFrontend()->renderHead());
    }

    /**
     * Renders the cookie declaration for the current site.
     *
     * @param array $options `headingLevel` (default 2), `template`
     * @return Markup
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function declaration(array $options = []): Markup
    {
        return ConsentMate::getInstance()->getFrontend()->renderDeclaration($options);
    }

    /**
     * Returns the categories for the current site, with names, descriptions
     * and their live services.
     *
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function categories(): array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        return ConsentMate::getInstance()->getFrontend()->getSiteData($siteId)['categories'];
    }

    /**
     * Returns the texts for the current site.
     *
     * @return array
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function texts(): array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        return ConsentMate::getInstance()->getTexts()->getTexts($siteId);
    }

    /**
     * Gates markup behind a consent category. See the `|consent` filter.
     *
     * @param string|null $html
     * @param string $category
     * @param array $options
     * @return Markup
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function gate(?string $html, string $category, array $options = []): Markup
    {
        return ConsentMate::getInstance()->getFrontend()->gate($html, $category, $options);
    }
}
