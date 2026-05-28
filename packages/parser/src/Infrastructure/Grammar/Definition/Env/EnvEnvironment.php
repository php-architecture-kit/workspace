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

        $grammar->global->add(
            Rule::token("hash", "#", type: NodeType::Structure)
                ->startRegion('lineComment', true)
                ->add(
                    Rule::token("space", " "),
                    Rule::token("tab", "\t"),
                    Rule::token("cr", "\r"),
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
            Rule::expr("identifier", "[a-zA-Z_][a-zA-Z0-9_]*")
                ->startRegion('assignment', true)
                ->add(
                    Rule::token("space", " "),
                    Rule::token("tab", "\t"),
                    Rule::token("equals", "=", type: NodeType::Structure),
                    Rule::expr("simpleExpansion", "\\$[a-zA-Z_][a-zA-Z0-9_]*"),
                    Rule::expr("bracedExpansion", "\\$\\{[a-zA-Z_][a-zA-Z0-9_]*\\}"),
                    Rule::expr("unquotedText", "[^\n$\t =]+"),
                    Rule::token("newline", "\n")->closeRegion(true, true, false),
                    Rule::technical("eof")->closeRegion(true, true, false),
                )
                ->withRootSequence(
                    "identifier (space|tab)* equals (space|tab)* "
                    . "(simpleExpansion|bracedExpansion|unquotedText|equals|space|tab)* "
                    . "newline|eof",
                )
                ->setNodeType(NodeType::Node),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT), false);

        return $grammar;
    }
}
