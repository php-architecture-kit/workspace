<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Registry\Definition\Technical;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

class Whitespace implements GrammarDefinitionInterface
{
    public const FORMAT = "technical";
    public const VARIANT = "whitespace";

    public function grammar(): Grammar
    {
        $grammar = new Grammar(static::FORMAT, static::VARIANT);

        $grammar->global->add(
            Rule::technical("bof", ['_ws']),
            Rule::technical("eof", ['_ws']),
            Rule::token("space", " ", ['_ws']),
            Rule::token("tab", "\t", ['_ws']),
            Rule::token("cr", "\r", ['_ws']),
            Rule::token("newline", "\n", ['_ws']),

            Rule::taggedWith('_ws')
                ->startRegion('whitespace_region', true)
                ->add(
                    Rule::technical("bof", ['_ws']),
                    Rule::technical("eof", ['_ws'])
                        ->closeRegion(true, true, false),
                    Rule::token("space", " ", ['_ws']),
                    Rule::token("tab", "\t", ['_ws']),
                    Rule::token("cr", "\r", ['_ws']),
                    Rule::token("newline", "\n", ['_ws'])
                        ->closeRegion(true, true, false),

                    EventSubscriber::on(
                        TokenRegionEndedEvent::class,
                        static function (TokenRegionEndedEvent $event, TokenizationContext $context): void {
                            /** @var ?Token $startedBy */
                            $startedBy = $event->region->getMeta(StartRegionEventListener::KEY_STARTED_BY, null);
                            $firstToken = $event->region->firstToken();
                            $lastToken = $event->region->lastToken();

                            $isLastTokenNewLine = $lastToken?->name === 'newline' || $lastToken?->name === 'eof';
                            $isStartedByNewLine = $startedBy?->name === 'newline';
                            $isTriggerTokenIncluded = $startedBy === $firstToken;

                            $currentRegionPlacementInParent = $context->getCurrentRegion()->getMeta("parentRegion")?->stream->lastOffset();
                            $previousEndedWithNewline = $startedBy?->name === 'bof';
                            if ($currentRegionPlacementInParent !== null && $currentRegionPlacementInParent > 0) {
                                $previousTokenOrRegion = $context->getCurrentRegion()->getMeta("parentRegion")?->stream->get($currentRegionPlacementInParent - 1);
                                if ($previousTokenOrRegion instanceof Token && $previousTokenOrRegion->name === 'newline') {
                                    $previousEndedWithNewline = true;
                                }

                                if ($previousTokenOrRegion instanceof TokenRegion && $previousTokenOrRegion->lastToken()?->name === 'newline') {
                                    $previousEndedWithNewline = true;
                                }
                            }

                            if ($isLastTokenNewLine) {
                                if ($isStartedByNewLine && !$isTriggerTokenIncluded) {
                                    $event->region->rename('empty-line');
                                } else {
                                    $event->region->rename('trailing-ws');
                                }
                            } else {
                                if ($isStartedByNewLine && !$isTriggerTokenIncluded) {
                                    $event->region->rename('leading-ws');
                                } elseif ($previousEndedWithNewline) {
                                    $event->region->rename('leading-ws');
                                } else {
                                    $event->region->rename('inline-ws');
                                }
                            }
                        }
                    )
                )
                ->closeWith(Rule::taggedWith("_ws"), true, false)
                ->setNodeType(NodeType::Raw)
                ->addTag('ws', 'whitespace'),
        );

        return $grammar;
    }
}
