<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

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
                            $isStartedByBof = $startedBy?->name === 'bof';
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
                                    $event->region->rename('emptyLine');
                                } elseif ($isStartedByBof) {
                                    $event->region->rename('emptyLine');
                                } else {
                                    $event->region->rename('trailingWs');
                                }
                            } else {
                                if ($isStartedByNewLine && !$isTriggerTokenIncluded) {
                                    $event->region->rename('leadingWs');
                                } elseif ($previousEndedWithNewline) {
                                    $event->region->rename('leadingWs');
                                } else {
                                    $event->region->rename('inlineWs');
                                }
                            }
                        },
                    ),
                )
                ->closeWith(Rule::taggedWith("_ws"), true, false)
                ->setNodeType(NodeType::Raw)
                ->addTag('ws', 'whitespace', '-')
                ->withPossibleNames('emptyLine', 'trailingWs', 'leadingWs', 'inlineWs'),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $grammar;
    }
}
