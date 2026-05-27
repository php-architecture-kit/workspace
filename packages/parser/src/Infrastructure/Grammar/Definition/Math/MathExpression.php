<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Math;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class MathExpression extends Whitespace
{
    public const FORMAT = 'math';
    public const VARIANT = 'expression';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $parenGroup = Rule::token('parenOpen', '(', type: NodeType::Structure)
            ->startRegion('parenGroup')
            ->setInheritanceFromAncestor()
            ->reParsedByPratt('binaryExpression')
            ->prattAtom()
            ->closeWith(Rule::token('parenClose', ')', type: NodeType::Structure));

        $expression = (new Region('expression'))
            ->setInheritanceFromGlobal()
            ->reParsedByPratt('binaryExpression')
            ->add(
                Rule::expr('number', '[0-9]+(?:\.[0-9]+)?')
                    ->prattAtom(),
                $parenGroup,
                Rule::token('caret', '**', type: NodeType::Structure)
                    ->prattInfix(bindingPower: 30, rightAssociative: true),
                Rule::token('asterisk', '*', type: NodeType::Structure)
                    ->prattInfix(bindingPower: 20),
                Rule::token('slash', '/', type: NodeType::Structure)
                    ->prattInfix(bindingPower: 20),
                Rule::token('plus', '+', type: NodeType::Structure)
                    ->prattInfix(bindingPower: 10),
                Rule::token('minus', '-', type: NodeType::Structure)
                    ->prattInfix(bindingPower: 10),
            );

        $grammar->global->add($expression);
        $grammar->setRootRegion($expression);

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $grammar;
    }
}
