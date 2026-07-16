<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Creation\DefaultsDefinition;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\ParsedTree\Context\ContextStack;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

class Whitespace implements GrammarDefinitionInterface
{
    public const FORMAT = "technical";
    public const VARIANT = "whitespace";

    public const CONTEXT_INDENT_UNIT = 'indentUnit';
    protected Grammar $grammar;

    public function grammar(): Grammar
    {
        $this->grammar = new Grammar(static::FORMAT, static::VARIANT);

        $this->grammar->global->add(
            Rule::technical("bof", ['_ws']),
            Rule::technical("eof", ['_ws']),
            Rule::token("space", " ", ['_ws']),
            Rule::token("tab", "\t", ['_ws']),
            Rule::token("cr", "\r", ['_ws']),
            Rule::token("newline", "\n", ['_ws']),
            Rule::taggedWith('_ws')
                ->startRegion('whitespace', true)
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

                            $isLastTokenEOL = $lastToken?->name === 'newline' || $lastToken?->name === 'eof';
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

                            if ($isLastTokenEOL) {
                                if ($isStartedByNewLine && !$isTriggerTokenIncluded) {
                                    $event->region->rename('emptyLine')->removeTag('-t');
                                } elseif ($isStartedByBof || $previousEndedWithNewline) {
                                    $event->region->rename('emptyLine')->removeTag('-t');
                                } else {
                                    $event->region->rename('trailingWs')->removeTag('-l');
                                }
                            } else {
                                if ($isStartedByNewLine && !$isTriggerTokenIncluded) {
                                    $event->region->rename('leadingWs')->removeTag('-t');
                                } elseif ($previousEndedWithNewline) {
                                    $event->region->rename('leadingWs')->removeTag('-t');
                                } else {
                                    $event->region->rename('inlineWs');
                                }
                            }
                        },
                    ),
                )
                ->closeWith(Rule::taggedWith("_ws"), true, false)
                ->setNodeType(NodeType::Raw)
                ->addTag('ws', 'whitespace', '-', '-l', '-t')
                ->withPossibleNames('emptyLine', 'trailingWs', 'leadingWs', 'inlineWs')
                // '-l'/'-t' pick out only the possible names that keep that tag after
                // the rename listener above runs removeTag('-t')/removeTag('-l') per
                // instance — see the listener: emptyLine and leadingWs keep '-l' (lose
                // '-t'), trailingWs keeps '-t' (loses '-l'), inlineWs keeps both. Without
                // this, both tags fall back to resolving as "the whitespace region, in
                // any possible form" — identical for '-l' and '-t' — so e.g. "-t* -l*"
                // (two adjacent slots meant to divide trailing-then-leading whitespace)
                // couldn't actually tell them apart, and the first slot greedily
                // consumed the whole run regardless of which one it was written as.
                ->withPossibleNamesForTag('-l', 'emptyLine', 'leadingWs', 'inlineWs')
                ->withPossibleNamesForTag('-t', 'trailingWs', 'inlineWs'),
        );

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        $this->grammar->nodeClassMap = array_merge($this->grammar->nodeClassMap, [
            'emptyLine' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode::class,
            'trailingWs' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode::class,
            'inlineWs' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode::class,
            'leadingWs' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode::class,
        ]);

        return $this->grammar;
    }

    /**
     * @param string[] $stylesWithIndentation
     * @param callable(NodeInterface $rootNode):string $indentUnitResolver
     */
    public function withIndentationSupport(
        array $stylesWithIndentation,
        callable $indentUnitResolver,
    ): self {
        $this->grammar->contextDefinition->addContextInitializer(
            function (NodeInterface $rootNode) use ($stylesWithIndentation, $indentUnitResolver): void {
                $style = $rootNode->getContextStack()->treeContext[ContextStack::STYLE] ?? DefaultsDefinition::DEFAULT_STYLE;
                if (!in_array($style, $stylesWithIndentation, true)) {
                    return;
                }

                $rootNode->getContextStack()->treeContext[self::CONTEXT_INDENT_UNIT] = $indentUnitResolver($rootNode);
            },
        );

        return $this;
    }
}
