<?php

namespace vaersaagod\consentmate\twigextensions;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use vaersaagod\consentmate\ConsentMate;

/**
 * The `{% consent %}` tag and `|consent` filter
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentMateTwigExtension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getTokenParsers(): array
    {
        return [
            new ConsentTokenParser(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('consent', [$this, 'consentFilter'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Gates an HTML string behind a consent category, e.g.
     * `{{ embedHtml|consent('marketing', { placeholder: true, provider: 'YouTube' }) }}`
     *
     * @param mixed $html
     * @param string $category
     * @param array $options
     * @return Markup
     * @throws \Throwable
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function consentFilter(mixed $html, string $category, array $options = []): Markup
    {
        return ConsentMate::getInstance()->getFrontend()->gate((string)$html, $category, $options);
    }
}
