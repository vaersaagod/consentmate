<?php

namespace vaersaagod\consentmate\twigextensions;

use Twig\Compiler;
use Twig\Node\Node;
use vaersaagod\consentmate\ConsentMate;

/**
 * Compiles the `{% consent %}` tag: captures the body and outputs it gated
 * behind the category
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentNode extends Node
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->write("try {\n")
            ->indent()
            ->subcompile($this->getNode('body'))
            ->outdent()
            ->write("} catch (\\Throwable \$e) {\n")
            ->indent()
            ->write("ob_end_clean();\n")
            ->write("throw \$e;\n")
            ->outdent()
            ->write("}\n")
            ->write('echo ' . ConsentMate::class . '::getInstance()->getFrontend()->gate(ob_get_clean(), ')
            ->subcompile($this->getNode('category'));

        if ($this->hasNode('options')) {
            $compiler
                ->raw(', ')
                ->subcompile($this->getNode('options'));
        }

        $compiler->raw(");\n");
    }
}
