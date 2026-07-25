<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Git;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class GitIgnore extends Whitespace
{
    public const FORMAT = 'git';
    public const VARIANT = 'gitignore';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $grammar->global->add(
            Rule::expr("lineComment", "#[^\n]*")
                ->priority(-1)
                ->startRegion('lineComment', true)
                ->add(
                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, false),
                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, false),
                )
                ->retokenizedByInnerGrammar((new GitComment())->grammar())
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-"),
            Rule::token("exclamation", "!", tags: ["_pattern_part"]),
            Rule::token("doubleAsterisk", "**", tags: ["_pattern_part"])->priority(1),
            Rule::token("asterisk", "*", tags: ["_pattern_part"]),
            Rule::token("question", "?", tags: ["_pattern_part"]),
            Rule::token("slash", "/", tags: ["_pattern_part"]),
            Rule::token("tilde", "~", tags: ["_pattern_part"]),
            Rule::expr("escapeSequence", "\\\\.", tags: ["_pattern_part"]),
            Rule::expr("characterClass", "\\[(?:\\\\.|[^\\]])*\\]", tags: ["_pattern_part"]),
            Rule::expr("literal", "[\p{L}\p{N}_.-]+", tags: ["_pattern_part"]),
            Rule::taggedWith("_pattern_part")
                ->startRegion("pattern", true)
                ->add(
                    Rule::token("exclamation", "!", tags: ["_pattern_part"]),
                    Rule::token("doubleAsterisk", "**", tags: ["_pattern_part"])->priority(1),
                    Rule::token("asterisk", "*", tags: ["_pattern_part"]),
                    Rule::token("question", "?", tags: ["_pattern_part"]),
                    Rule::token("slash", "/", tags: ["_pattern_part"]),
                    Rule::token("tilde", "~", tags: ["_pattern_part"]),
                    Rule::expr("escapeSequence", "\\\\.", tags: ["_pattern_part"]),
                    Rule::expr("characterClass", "\\[(?:\\\\.|[^\\]])*\\]", tags: ["_pattern_part"]),
                    Rule::expr("literal", "[\p{L}\p{N}_.-]+", tags: ["_pattern_part"]),
                    Rule::token("space", " "),
                    Rule::token("tab", "\t"),
                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, false),
                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, false),
                    EventSubscriber::on(
                        TokenRegionEndedEvent::class,
                        static function (TokenRegionEndedEvent $event, TokenizationContext $context): void {
                            $stream = $event->region->stream;

                            $isNegated = $stream->first()?->name === 'exclamation';

                            $lastOffset = $stream->lastOffset();
                            $lastContent = $lastOffset === null ? null : $stream->get($lastOffset - 1);
                            $isDirectoryOnly = $lastContent?->name === 'slash';

                            $event->region->rename(match (true) {
                                $isNegated && $isDirectoryOnly => 'negatedDirectoryPattern',
                                $isNegated => 'negatedPattern',
                                $isDirectoryOnly => 'directoryPattern',
                                default => 'pattern',
                            });
                        },
                    ),
                )
                ->setNodeType(NodeType::Node)
                ->withPossibleNames('pattern', 'negatedPattern', 'directoryPattern', 'negatedDirectoryPattern'),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        $grammar->nodeClassMap = array_merge($grammar->nodeClassMap, [
            'lineComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitignore\LineCommentNode::class,
            'pattern' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitignore\PatternNode::class,
            'directoryPattern' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitignore\DirectoryPatternNode::class,
            'negatedPattern' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitignore\NegatedPatternNode::class,
            'negatedDirectoryPattern' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitignore\NegatedDirectoryPatternNode::class,
        ]);

        return $grammar;
    }
}
