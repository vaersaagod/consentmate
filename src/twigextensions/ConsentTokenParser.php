<?php

namespace vaersaagod\consentmate\twigextensions;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses `{% consent 'category' [with { options }] %}...{% endconsent %}`
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentTokenParser extends AbstractTokenParser
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getTag(): string
    {
        return 'consent';
    }

    /**
     * @inheritdoc
     */
    public function parse(Token $token): ConsentNode
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $nodes = [
            'category' => $this->parser->parseExpression(),
        ];

        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $nodes['options'] = $this->parser->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $nodes['body'] = $this->parser->subparse([$this, 'decideConsentEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new ConsentNode($nodes, [], $lineno);
    }

    /**
     * @param Token $token
     * @return bool
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function decideConsentEnd(Token $token): bool
    {
        return $token->test('endconsent');
    }
}
