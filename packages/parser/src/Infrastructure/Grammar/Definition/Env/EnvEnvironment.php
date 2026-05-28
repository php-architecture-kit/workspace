<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Env;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class EnvEnvironment extends Whitespace
{
    public const FORMAT = 'env';
    public const VARIANT = 'environment';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $simpleExpansion = Rule::token("dollar", "$")
            ->startRegion("simpleExpansion", true)
            ->add(
                Rule::expr("string", "[a-zA-Z_][a-zA-Z0-9_]*")->closeRegion(true, true, false),
            )
            ->withRootSequence("dollar string[varRef]")
            ->addTag("value")
            ->setNodeType(NodeType::Node)
            ->prattAtom();

        $bracedExpansion = Rule::token("dollarBrace", "\${", type: NodeType::Structure)
            ->priority(1)
            ->startRegion("bracedExpansion", true)
            ->reParsedByPratt("envExpression")
            ->prattAtom()
            ->add(
                Rule::expr("string", "[a-zA-Z_][a-zA-Z0-9_]*")->prattAtom(),
                Rule::expr("unquotedText", "[^\n\$\t }:#=]+")->prattAtom()->addTag("value"),
                Rule::token("space", " "),
                Rule::token("tab", "\t"),
                Rule::token("colonMinus", ":-", type: NodeType::Structure)->prattInfix(10),
                Rule::token("colonPlus", ":+", type: NodeType::Structure)->prattInfix(10),
                Rule::token("colonQuestion", ":?", type: NodeType::Structure)->prattInfix(10),
                Rule::token("colonAssign", ":=", type: NodeType::Structure)->prattInfix(10),
                Rule::token("closeBrace", "}", type: NodeType::Structure)->closeRegion(true, true, false),
            )
            ->addTag("value")
            ->setNodeType(NodeType::Node);

        $grammar->global->add(
            Rule::token("hash", "#", type: NodeType::Structure)
                ->startRegion('lineComment', true)
                ->add(
                    Rule::expr("commentContent", "[^\r\n]+"),
                    Rule::token("newline", "\n")->closeRegion(true, true, false),
                    Rule::technical("eof")->closeRegion(true, true, false),
                    EventSubscriber::on(
                        TokenRegionEndedEvent::class,
                        static function (TokenRegionEndedEvent $event, TokenizationContext $context): void {
                            $placement = $context->getCurrentRegion()
                                ->getMeta(TokenRegion::KEY_PARENT)
                                ?->stream->lastOffset();
                            if ($placement === null || $placement < 1) {
                                return;
                            }
                            $previous = $context->getCurrentRegion()
                                ->getMeta(TokenRegion::KEY_PARENT)
                                ?->stream->get($placement - 1);
                            if (
                                $previous instanceof TokenRegion &&
                                in_array($previous->name, ['lineComment', 'blockComment'], true)
                            ) {
                                $event->region->rename('blockComment');
                                if ($previous->name === 'lineComment') {
                                    $previous->rename('blockComment');
                                }
                            }
                        },
                    ),
                )
                ->withRootSequence("hash (space|tab)* ?commentContent ?cr newline|eof")
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-")
                ->withPossibleNames('lineComment', 'blockComment'),
            Rule::expr("string", "[a-zA-Z_][a-zA-Z0-9_]*")
                ->startRegion('assignment', true)
                ->add(
                    $simpleExpansion,
                    $bracedExpansion,
                    Rule::token("space", " "),
                    Rule::token("tab", "\t"),
                    Rule::token("assign", "=", type: NodeType::Structure),
                    Rule::expr("unquotedText", "[^\n\$\t =#]+")->addTag("value"),
                    Rule::token("newline", "\n")->closeRegion(true, true, false),
                    Rule::technical("eof")->closeRegion(true, true, false),
                )
                ->withRootSequence("string[identifier] (space|tab)* assign (space|tab)* (simpleExpansion|bracedExpansion|unquotedText|string|space|tab)* newline|eof")
                ->setNodeType(NodeType::Node),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT), false);

        return $grammar;
    }
}
