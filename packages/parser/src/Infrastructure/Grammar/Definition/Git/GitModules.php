<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Git;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class GitModules extends Whitespace
{
    public const FORMAT = 'git';
    public const VARIANT = 'gitmodules';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $grammar->global->add(
            // Full-line comments — both # and ; markers (priority -1 so they don't
            // compete with variableName/bracketOpen rules on first-char basis)
            Rule::expr("lineComment", "[#;][^\n]*")
                ->priority(-1)
                ->startRegion('lineComment', true)
                ->add(
                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, false),
                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, false),
                )
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-"),

            // Section header — opened by `[`, renamed on close to distinguish the three forms
            Rule::token("bracketOpen", "[", type: NodeType::Structure)
                ->startRegion("sectionHeader", true)
                ->add(
                    Rule::expr("sectionName", "[A-Za-z0-9.-]+"),
                    // Space between section name and quoted subsection
                    Rule::token("space", " ", ["inlineWs"]),
                    Rule::token("tab", "\t", ["inlineWs"]),
                    // Quoted subsection — dquote opens subsectionName sub-region
                    Rule::token("dquote", '"', type: NodeType::Structure)
                        ->startRegion("subsectionName", true)
                        ->add(
                            // Escape: backslash + any char except literal LF → backslash dropped, char kept.
                            // Priority 1 so it takes precedence over the general char rule.
                            Rule::expr("subsectionEscape", "\\\\[^\n]")->priority(1),
                            // Any char except `"`, `\`, and newline
                            Rule::expr("subsectionChar", "[^\"\\\\\n]+"),
                            Rule::token("dquote", '"', type: NodeType::Structure)
                                ->priority(1)
                                ->closeRegion(true, false, false),
                        )
                        ->setNodeType(NodeType::Node),
                    // `]` closes the section header
                    Rule::token("bracketClose", "]", type: NodeType::Structure)
                        ->closeRegion(true, false, false),
                    // Rename the region to one of three forms after it ends
                    EventSubscriber::on(
                        TokenRegionEndedEvent::class,
                        static function (TokenRegionEndedEvent $event, TokenizationContext $context): void {
                            $stream = $event->region->stream;
                            // The `subsectionName` child region is only present for the quoted form
                            // ([section "subsection"]). Use instanceof TokenRegion check since
                            // subsectionName is the only possible nested region here.
                            $hasSubsectionName = false;
                            $sectionNameRaw = null;

                            $lastOffset = $stream->lastOffset() ?? -1;
                            for ($i = 0; $i <= $lastOffset; $i++) {
                                $item = $stream->get($i);
                                if ($item instanceof TokenRegion) {
                                    $hasSubsectionName = true;
                                }
                                if ($item instanceof Token && $item->name === 'sectionName') {
                                    $sectionNameRaw = $item->raw;
                                }
                            }

                            $event->region->rename(match (true) {
                                $hasSubsectionName => 'quotedSectionHeader',
                                $sectionNameRaw !== null && str_contains($sectionNameRaw, '.') => 'dottedSectionHeader',
                                default => 'bareSectionHeader',
                            });
                        },
                    ),
                )
                ->setNodeType(NodeType::Node)
                ->withPossibleNames('quotedSectionHeader', 'dottedSectionHeader', 'bareSectionHeader'),

            // Variable assignment — opened by a variableName matching [A-Za-z][A-Za-z0-9-]*
            // (alpha-first confirmed enforced by Git's own dispatcher; no underscore allowed)
            Rule::expr("variableName", "[A-Za-z][A-Za-z0-9-]*")
                ->startRegion("assignment", true)
                ->add(
                    Rule::token("equals", "=", type: NodeType::Structure),
                    // Value region — opened when `equals` matches (false = equals not included in value)
                    (new Region("value"))
                        ->openWith(Rule::ref("equals"), false)
                        ->add(
                            // Inline trailing comment — terminates the value when `#` or `;` appears
                            // outside a quoted segment. Named "inlineComment" (not "lineComment") to
                            // avoid a duplicate-region-name collision with the global-level lineComment.
                            // Priority 2 so it fires before valueChar (priority -1) and escape rules (2/3).
                            Rule::expr("inlineComment", "[#;][^\n]*")
                                ->priority(2)
                                ->startRegion("inlineComment", true)
                                ->add(
                                    // repeat=true cascades: closes inlineComment → closes value → closes assignment
                                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, true),
                                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, true),
                                )
                                ->setNodeType(NodeType::Node)
                                ->addTag("comment", "-"),
                            // Quoted segment — dquote opens it; same 5 escape rules apply inside.
                            // Escape rules are added in a separate ->add() call because PHP requires
                            // the variadic spread to be the last argument in any call.
                            Rule::token("dquote", '"', type: NodeType::Structure)
                                ->priority(1)
                                ->startRegion("quotedSegment", true)
                                ->add(
                                    Rule::expr("quotedChar", "[^\"\\\\\n]+"),
                                    Rule::token("dquote", '"', type: NodeType::Structure)
                                        ->priority(1)
                                        ->closeRegion(true, false, false),
                                )
                                ->add(...$this->valueEscapeRules())
                                ->setNodeType(NodeType::Node),
                            // Unquoted literal chars — excludes `"`, `\`, newline, comment markers,
                            // and space/tab (those are captured by the dedicated inlineWs token rules
                            // below so they stay individually tokenizable for trivia capture)
                            Rule::expr("valueChar", "[^\"\\\\\n#; \t]+")->priority(-1),
                            Rule::token("space", " ", ["inlineWs"]),
                            Rule::token("tab", "\t", ["inlineWs"]),
                            // Value closes on newline or EOF; repeat=true cascades to assignment
                            Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, true),
                            Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, true),
                        )
                        // The 5 escape sequences and line-continuation apply OUTSIDE quotes too
                        // (confirmed: Git's parse_value() applies backslash-handling unconditionally
                        // on quote state). Separate call to avoid spread-before-positional PHP error.
                        ->add(...$this->valueEscapeRules())
                        ->setNodeType(NodeType::Node),
                    Rule::token("space", " ", ["inlineWs"]),
                    Rule::token("tab", "\t", ["inlineWs"]),
                    // Assignment closes on newline or EOF
                    Rule::token("newline", "\n", ["trailingWs"])->closeRegion(true, true, false),
                    Rule::technical("eof", ["trailingWs"])->closeRegion(true, true, false),
                    // Rename after close: distinguish key-value vs boolean shorthand
                    EventSubscriber::on(
                        TokenRegionEndedEvent::class,
                        static function (TokenRegionEndedEvent $event, TokenizationContext $context): void {
                            $stream = $event->region->stream;
                            $hasEquals = false;

                            $lastOffset = $stream->lastOffset() ?? -1;
                            for ($i = 0; $i <= $lastOffset; $i++) {
                                $item = $stream->get($i);
                                if ($item instanceof Token && $item->name === 'equals') {
                                    $hasEquals = true;
                                    break;
                                }
                            }

                            $event->region->rename($hasEquals ? 'keyValueAssignment' : 'booleanShorthandAssignment');
                        },
                    ),
                )
                ->setNodeType(NodeType::Node)
                ->withPossibleNames('keyValueAssignment', 'booleanShorthandAssignment'),

            Rule::seq("section",
                "quotedSectionHeader|dottedSectionHeader|bareSectionHeader[header]/n " .
                "-* " .
                "(-l* (keyValueAssignment|booleanShorthandAssignment|lineComment)/c)*[entries]/g " .
                "-*"
            )->setNodeType(NodeType::Node),
        );

        $gitmodulesDoc = (new Region("gitmodules"))
            ->setInheritanceFromGlobal()
            ->withRootSequence("-* section*[sections] -*");

        $grammar->global->add($gitmodulesDoc);
        $grammar->setRootRegion($gitmodulesDoc);

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        // $grammar->nodeClassMap = array_merge($grammar->nodeClassMap, [
        //     'lineComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\LineCommentNode::class,
        //     'bareSectionHeader' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\BareSectionHeaderNode::class,
        //     'subsectionName' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\SubsectionNameNode::class,
        //     'keyValueAssignment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\KeyValueAssignmentNode::class,
        //     'value' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\ValueNode::class,
        //     'assignment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\AssignmentNode::class,
        //     'inlineComment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\InlineCommentNode::class,
        //     'quotedSegment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\QuotedSegmentNode::class,
        //     'quotedSectionHeader' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\QuotedSectionHeaderNode::class,
        //     'dottedSectionHeader' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\DottedSectionHeaderNode::class,
        //     'booleanShorthandAssignment' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\BooleanShorthandAssignmentNode::class,
        //     'section' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\SectionNode::class,
        //     'gitmodules' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules\GitmodulesNode::class,
        // ]);

        return $grammar;
    }

    /**
     * The five escape sequences recognized in .gitmodules values, valid both
     * inside and outside a quoted segment (confirmed from Git's parse_value() in config.c:
     * the backslash-handling branch is unconditional on the current quote state).
     *
     * Also includes backslash-newline line continuation, which has the highest priority
     * so it is identified before the general five-escape rule.
     *
     * @return Rule[]
     */
    private function valueEscapeRules(): array
    {
        return [
            // Line continuation: backslash immediately before a physical newline → both discarded
            Rule::token("lineContinuation", "\\\n")->priority(3),
            // The five valid value escapes: \" \\ \n \t \b (any other \X is a parse error)
            Rule::expr("valueEscape", "\\\\[\"\\\\nbt]")->priority(2),
        ];
    }
}
