<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Defaults;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Context\ContextStack;
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
                ->setNodeType(NodeType::Node)
                ->addTag('ws', 'whitespace', '-', '-l', '-t')
                ->withPossibleNames('emptyLine', 'trailingWs', 'leadingWs', 'inlineWs'),
        );

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

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
                $style = $rootNode->getContextStack()->treeContext[ContextStack::STYLE] ?? Defaults::DEFAULT_STYLE;
                if (!in_array($style, $stylesWithIndentation, true)) {
                    return;
                }

                $rootNode->getContextStack()->treeContext[self::CONTEXT_INDENT_UNIT] = $indentUnitResolver($rootNode);
            },
        );

        return $this;
    }

    // /**
    //  * @return callable(ContextStack $parentContext, string $style):string
    //  */
    // public function indentationResolver(bool $leadingNewline): callable
    // {
    //     return static function (ContextStack $parentContext, string $style): string {
    //         $indentUnit = $parentContext->treeContext[self::CONTEXT_INDENT_UNIT] ?? null;
    //         if ($indentUnit === null) {
    //             throw new \RuntimeException("Indentation unit not set in " . static::FORMAT . " " . static::VARIANT . " context for style '{$style}'");
    //         }

    //         // TODO
    //         return '';
    //     };
    // }

    // public static function emptyLine(string $content = "\n\n"): NodeInterface
    // {
    //     return new Node(
    //         name: 'emptyLine',
    //         attributes: [new RawContentAttribute($content)],
    //     );
    // }

    // public static function trailingWs(string $content = "\n"): NodeInterface
    // {
    //     return new Node(
    //         name: 'trailingWs',
    //         attributes: [new RawContentAttribute($content)],
    //     );
    // }

    // public static function leadingWs(string $content = ""): NodeInterface
    // {
    //     return new Node(
    //         name: 'leadingWs',
    //         attributes: [new RawContentAttribute($content)],
    //     );
    // }

    // public static function inlineWs(string $content = ""): NodeInterface
    // {
    //     return new Node(
    //         name: 'inlineWs',
    //         attributes: [new RawContentAttribute($content)],
    //     );
    // }
}
