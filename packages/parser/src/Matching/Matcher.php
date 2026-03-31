<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Matching;

class Matcher
{
    /**
     * @var array<string>
     */
    private array $currentStack = [];

    /**
     * @var array<int,array<string>>
     */
    private array $currentOffsetStack = [];

    /**
     * @var array<int,array<string>>
     */
    private array $currentMatchedSequenceNodeStack = [];

    public function __construct(
        private readonly Grammar $grammar,
        private readonly ParsingMode $mode = ParsingMode::LENIENT,
        private readonly int $recursionLimit = 100,
    ) {}

    public function process(TokenStream $stream): MemberTree
    {
        $members = [];
        $offset = 0;
        $rootMember = $this->grammar->rootMember();

        while ($stream->has($offset)) {
            if ($rootMember !== null) {
                if (($member = $this->matchSequence($rootMember, $stream, $offset)) !== null) {
                    if ($member instanceof Member) {
                        $members[] = $member;
                    } else {
                        $members = array_merge($members, $member);
                    }
                    continue;
                }
            }

            foreach ($this->grammar->members($stream->peek($offset)->name) as $sequenceName => $sequence) {
                if (($member = $this->matchSequence($sequence, $stream, $offset)) !== null) {
                    if ($member instanceof Member) {
                        $members[] = $member;
                    } else {
                        $members = array_merge($members, $member);
                    }
                    break;
                }
            }

            $token = $stream->peek($offset++);
            if ($token instanceof Token) {
                if ($token->name === Token::TOKEN_UNKNOWN) {
                    if ($this->mode === ParsingMode::STRICT) {
                        throw new UnknownTokenException($token, $offset - 1);
                    }

                    if ($this->mode === ParsingMode::RECOVERY) {
                        $offset++;
                        continue;
                    }
                }

                $members[] = $token;
                continue;
            }

            break;
        }

        return new MemberTree($this->grammar, $members);
    }

    private function tokenizeInnerTokens(Grammar $grammar, string $raw): TokenStream
    {
        $stringStream = new StringStream($raw);
        $lexer = new Lexer($grammar);

        return $lexer->process($stringStream);
    }

    private function processInnerTokens(Grammar $grammar, TokenStream $tokenStream): MemberTree
    {
        $processor = new Processor($grammar, $this->mode, $this->recursionLimit);

        return $processor->process($tokenStream);
    }

    /**
     * @return null|array<Token|Member>|Member
     */
    private function matchSequence(Sequence $sequence, TokenStream $stream, int &$offset): null|array|Member
    {
        if (!$this->grammar->isMemberStartsWithToken($sequence->name, $stream->peek($offset)->name)) {
            return null;
        }

        if (in_array($sequence->name, $this->currentStack) && count(array_filter($this->currentStack, static fn(string $name) => $name === $sequence->name)) > $this->recursionLimit) {
            $currentToken = $stream->peek($offset);
            throw new LogicException(
                "Recursive loop detected on token `{$currentToken->name}`, position: `{$currentToken->startPosition}`, row: `{$currentToken->startRow}`, column: `{$currentToken->startColumn}`. Sequence `{$sequence->name}` is called recursively over {$this->recursionLimit} times. Stack:\n`"
                    . implode(' -> ', array_slice($this->currentStack, 0, 10)) . " -> ... -> "
                    . implode(' -> ', array_slice($this->currentStack, -5)) . " -> {$sequence->name}`.\n\n"
                    . "Current offset stacks:\n"
                    . implode("\n", array_map(static fn(array $offsetStack, int $offsetIndex): string => "[o:{$offsetIndex}, r:{$stream->peek($offsetIndex)->startRow}, c:{$stream->peek($offsetIndex)->startColumn}] {$stream->peek($offsetIndex)->name}: "
                        . implode(', ', $offsetStack) . ";", $this->currentOffsetStack, array_keys($this->currentOffsetStack)))
            );
        }
        $this->currentStack[] = $sequence->name;

        $nestedSequence = new NestedSequence(
            $sequence->nodes,
            Cardinality::ExactlyOne,
        );
        $matchingAstNode = $this->grammar->astNodeBySequenceName($sequence->name);
        $anchorSequence = ($matchingAstNode->anchorSequences[$sequence->name] ?? null)
            ? new NestedSequence(
                $matchingAstNode->anchorSequences[$sequence->name]->nodes,
                Cardinality::ExactlyOne,
            )
            : null;

        if (($members = $this->matchNestedSequence($nestedSequence, $anchorSequence, $stream, $offset)) === null) {
            $name = array_pop($this->currentStack);
            if ($name !== $sequence->name) {
                throw new LogicException("The retrieved stack element is different than expected. Expected: `{$sequence->name}`. Actual: `{$name}`. Stack: `" . implode(' -> ', $this->currentStack) . "`.");
            }

            return null;
        }

        $name = array_pop($this->currentStack);
        if ($name !== $sequence->name) {
            throw new LogicException("The retrieved stack element is different than expected. Expected: `{$sequence->name}`. Actual: `{$name}`. Stack: `" . implode(' -> ', $this->currentStack) . "`.");
        }

        $innerMembers = null;
        if ($sequence->innerGrammar instanceof Grammar) {
            $innerTokens = array_merge(...array_map(
                static fn(Member|Token $innerMember): array => match (true) {
                    $innerMember instanceof Token => empty($innerMember->innerTokens) ? [$innerMember] : $innerMember->innerTokens->tokens,
                    $innerMember instanceof Member => $this->tokenizeInnerTokens($sequence->innerGrammar, $innerMember->raw())->tokens,
                },
                $members
            ));

            $innerMembers = $this->processInnerTokens(
                $sequence->innerGrammar,
                new TokenStream($innerTokens)
            );
        }

        return $sequence->member
            ? new Member(
                $sequence->name,
                $members,
                $innerMembers,
            )
            : $members;
    }

    /**
     * @return null|array<Token|Member>
     */
    private function matchNestedSequence(
        NestedSequence $sequence,
        ?NestedSequence $anchorSequence,
        TokenStream $stream,
        int &$offset
    ): null|array {
        $sequenceCount = 0;
        $sequenceMembers = [];
        $start = $offset;

        while ($sequenceCount < $sequence->cardinality->max()) {
            $sequenceValues = [];
            $sequenceStart = $offset;

            foreach ($sequence->nodes as $pos => $node) {
                $nodeStart = $offset;
                $nodeKey = $node->toString();

                if ($node->isLookbehind) {
                    $offset--;
                }

                if ($node instanceof NestedSequence) {
                    $items = $this->matchNestedSequence($node, $anchorSequence?->nodes[$pos] ?? null, $stream, $offset);
                    if ($items !== null) {
                        if ($node->isLookbehind) {
                            continue;
                        }

                        if ($node->isLookahead) {
                            $offset = $nodeStart;
                            break;
                        }

                        $sequenceValues = array_merge($sequenceValues, $items);
                        foreach ($items as $index => $item) {
                            $this->currentMatchedSequenceNodeStack[$offset + $index][] = "{$nodeKey}: {$item->name}";
                        }
                        continue;
                    }

                    $offset = $sequenceStart;
                    break 2;
                }

                $items = $this->matchSequenceNode($node, $stream, $offset);
                if ($items !== null) {
                    if ($node->isLookbehind) {
                        continue;
                    }

                    if ($node->isLookahead) {
                        $offset = $nodeStart;
                        break;
                    }

                    foreach ($items as $index => $item) {
                        $item->grammarNodeName = $nodeKey;
                        $item->grammarNodePos = $pos;
                        $item->sequenceIndex = $sequenceCount;
                        $item->astAnchor = $anchorSequence?->nodes[$pos]?->toString();
                        $this->currentMatchedSequenceNodeStack[$offset + $index][] = "{$nodeKey}: {$item->name}";
                    }

                    $sequenceValues = array_merge($sequenceValues, $items);
                    continue;
                }

                $offset = $sequenceStart;
                break 2;
            }

            $sequenceMembers = array_merge($sequenceMembers, $sequenceValues);
            $sequenceCount++;
        }

        if ($sequenceCount < $sequence->cardinality->min()) {
            for ($i = $start; $i < $offset; $i++) {
                unset($this->currentMatchedSequenceNodeStack[$i]);
            }
            $offset = $start;
            return null;
        }

        return $sequenceMembers;
    }

    /**
     * @return null|array<Token|Member>
     */
    private function matchSequenceNode(SequenceNode $node, TokenStream $stream, int &$offset): null|array
    {
        $nodeKey = $node->toString();

        $count = 0;
        $start = $offset;
        $values = [];

        while ($count < $node->cardinality->max()) {
            $currentOffset = $offset;
            foreach ($node->alternatives as $alternative) {
                if (($altNode = $this->matchNodeName($alternative)) === null && !in_array($alternative, Token::RESERVED_TOKEN_NAMES)) {
                    continue;
                }

                if ($altNode === null) {
                    $token = $stream->matchAny($offset, [$alternative]);
                    if ($token !== null) {
                        $values[] = $token;

                        $count++;
                        unset($this->currentOffsetStack[$currentOffset]);

                        continue 2;
                    }

                    continue;
                };

                if (is_array($altNode) && !empty(array_filter($altNode, static fn(Regex|Sequence $aNode) => $aNode instanceof Sequence))) {
                    $this->currentOffsetStack[$currentOffset][] = "[{$alternative}]";
                }

                if ($altNode instanceof Sequence || $altNode instanceof Regex) {
                    $altNode = [$altNode->name => $altNode];
                }

                foreach ($altNode as $aName => $aNode) {
                    if ($aNode instanceof Regex) {
                        $token = $stream->matchAny($offset, [$aName]);
                        if ($token !== null) {
                            $values[] = $token;

                            $count++;
                            unset($this->currentOffsetStack[$currentOffset]);

                            continue 3;
                        }
                    };

                    if ($aNode instanceof Sequence && !in_array($aNode->name, $this->currentOffsetStack[$offset] ?? [])) {
                        $this->currentOffsetStack[$currentOffset][] = $aNode->name;

                        if (($member = $this->matchSequence($aNode, $stream, $offset)) === null) {
                            continue;
                        }

                        if ($member instanceof Member) {
                            $values[] = $member;
                        } else {
                            $values = array_merge($values, $member);
                        }

                        $count++;
                        unset($this->currentOffsetStack[$currentOffset]);

                        continue 3;
                    }
                }
            }

            break;
        }

        if ($count < $node->cardinality->min()) {
            $offset = $start;

            return null;
        }

        return $values;
    }

    /**
     * @return null|Regex|Sequence|array<string,Regex|Sequence>
     */
    private function matchNodeName(string $nodeName): null|Regex|Sequence|array
    {
        return $this->grammar->token($nodeName) ?? $this->grammar->member($nodeName) ?? $this->grammar->group($nodeName);
    }
}
