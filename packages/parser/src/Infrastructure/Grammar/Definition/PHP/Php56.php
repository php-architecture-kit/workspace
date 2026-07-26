<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\PHP;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionReturnEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

class Php56 extends Whitespace
{
    public const FORMAT = "php";
    public const VARIANT = "5.6";

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->global->add(
            Rule::token('openTag', '<?php', ['_opener'], NodeType::Structure),
            Rule::token('shortOpenTag', '<?', ['_opener'], NodeType::Structure),
            Rule::token('aspOpenTag', '<%', ['_opener'], NodeType::Structure),
            Rule::token('shortOpenTagWithEcho', '<?=', ['_opener'], NodeType::Structure),
            Rule::token('aspOpenTagWithEcho', '<%=', ['_opener'], NodeType::Structure),
            Rule::expr('scriptOpenTag', "<script[ \\t\\r\\n]+language[ \\t\\r\\n]*=[ \\t\\r\\n]*(\"php\"|'php'|php)[ \\t\\r\\n]*>", true, ['_opener']),

            Rule::taggedWith("_opener")
                ->startRegion("code", true)
                ->setInheritanceFromGlobal()
                ->add(
                    # closers
                    Rule::token('closeTag', '?>', [], NodeType::Structure)->closeRegion(true),
                    Rule::token('aspCloseTag', '%>', [], NodeType::Structure)->closeRegion(true),
                    Rule::keyword('</script>', true, 'scriptCloseTag')->closeRegion(true),

                    Rule::token('semicolon', ';', type: NodeType::Structure),
                    Rule::token('colon', ':', type: NodeType::Structure),

                    # heredoc
                    Rule::expr("heredocBegin", "<<<(\"[A-Za-z_][A-Za-z0-9_]*\"|[A-Za-z_][A-Za-z0-9_]*)\n")
                        ->startRegion("heredoc", true)
                        ->setInheritanceFromGlobal()
                        ->add(
                            Rule::dynamic("heredocEnd", static fn(Rule $rule, Token $token): RegexRule => Rule::keyword(trim(substr(rtrim($token->raw, "\n"), 3), "\""))->definition, "heredocBegin", [CallbackRule::SAME_REGION])->closeRegion(true),
                        ),

                    # nowdoc
                    Rule::expr("nowdocBegin", "<<<'[A-Za-z_][A-Za-z0-9_]*'\n")
                        ->startRegion("nowdoc", true)
                        ->setInheritanceFromGlobal()
                        ->add(
                            Rule::dynamic("nowdocEnd", static fn(Rule $rule, Token $token): RegexRule => Rule::keyword(substr(rtrim($token->raw, "\n"), 4))->definition, "nowdocBegin", [CallbackRule::SAME_REGION])->closeRegion(true),
                        ),

                    # brackets
                    Rule::token('openBracket', '(', type: NodeType::Structure)
                        ->startRegion("brackets")
                        ->setInheritanceFromAncestor()
                        ->add(
                            Rule::token('closeBracket', ')', type: NodeType::Structure)->closeRegion(true),
                        ),
                    Rule::token('openCurlBracket', '{', type: NodeType::Structure)
                        ->startRegion("curlBrackets")
                        ->setInheritanceFromAncestor()
                        ->add(
                            Rule::token('closeCurlBracket', '}', type: NodeType::Structure)->closeRegion(true),
                        ),
                    Rule::token('openSquareBracket', '[', type: NodeType::Structure)
                        ->startRegion("squareBrackets")
                        ->setInheritanceFromAncestor()
                        ->add(
                            Rule::token('closeSquareBracket', ']', type: NodeType::Structure)->closeRegion(true),
                        ),

                    # if
                    Rule::keyword('if', true, 'ifKeyword', type: NodeType::Structure)
                        ->startRegion("ifStatement")
                        ->setInheritanceFromAncestor()
                        ->add(
                            Rule::keyword('endif', true, 'endifKeyword', type: NodeType::Structure)->closeRegion(true),
                            Rule::keyword('else', true, 'elseKeyword', type: NodeType::Structure)
                                ->startRegion("elseStatement")
                                ->setInheritanceFromAncestor()
                                ->add(
                                    EventSubscriber::on(
                                        TokenRegionReturnEvent::class,
                                        static function (TokenRegionReturnEvent $event, TokenizationContext $context): void {
                                            $currentRegion = $context->getCurrentRegion();
                                            $lastRegion = $currentRegion->stream->last();
                                            if ($lastRegion?->getName() === 'ifStatement' || $lastRegion?->getName() === 'curlBrackets') {
                                                $parentRegion = $currentRegion->getMeta(TokenRegion::KEY_PARENT);
                                                $context->escapeToRegion($parentRegion);

                                                return;
                                            }
                                        }
                                    ),
                                )
                                ->closeWith(Rule::ref('semicolon'), false, false, false),

                            EventSubscriber::on(TokenAddedEvent::class, static function (TokenAddedEvent $event, TokenizationContext $context): void {
                                if ($event->token->name !== 'semicolon' || $context->getCurrentRegion()->getName() !== 'ifStatement') {
                                    return;
                                }

                                $currentRegion = $context->getCurrentRegion();
                                $currentRegion->setMeta('closeIfNotFollowedBy', true);
                            }),

                            EventSubscriber::on(TokenMatchedEvent::class, static function (TokenMatchedEvent $event, TokenizationContext $context): void {
                                $currentRegion = $context->getCurrentRegion();
                                if ($currentRegion->getName() !== 'ifStatement' || $currentRegion->getMeta('closeIfNotFollowedBy') !== true) {
                                    return;
                                }

                                if (in_array('_ws', $event->token->tags) || in_array('_trivial', $event->token->tags)) {
                                    return;
                                }

                                if ($event->token->name !== 'elseKeyword' && $event->token->name !== 'endifKeyword') {
                                    $parentRegion = $currentRegion->getMeta(TokenRegion::KEY_PARENT);
                                    $tokensToMove = [];
                                    while ($currentRegion->stream->last()?->getName() !== 'curlBrackets' && $currentRegion->stream->last()?->getName() !== 'semicolon') {
                                        $tokensToMove[] = $currentRegion->stream->last();
                                        $currentRegion->stream->remove($currentRegion->stream->lastOffset());
                                    }

                                    $parentRegion->stream->add(...array_reverse($tokensToMove));
                                    $currentRegion->removeMeta('closeIfNotFollowedBy');

                                    $context->escapeToRegion($parentRegion);
                                }
                            })->priority(100),

                            EventSubscriber::on(
                                TokenRegionReturnEvent::class,
                                static function (TokenRegionReturnEvent $event, TokenizationContext $context): void {
                                    $currentRegion = $context->getCurrentRegion();
                                    $lastRegion = $currentRegion->stream->last();
                                    if ($currentRegion->getName() === 'ifStatement' && $lastRegion?->getName() === 'curlBrackets') {
                                        $currentRegion->setMeta('closeIfNotFollowedBy', true);

                                        return;
                                    }

                                    if ($lastRegion?->getName() === 'elseStatement') {
                                        $parentRegion = $currentRegion->getMeta(TokenRegion::KEY_PARENT);
                                        $context->escapeToRegion($parentRegion);

                                        return;
                                    }
                                }
                            ),
                        ),
                ),
        );

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
