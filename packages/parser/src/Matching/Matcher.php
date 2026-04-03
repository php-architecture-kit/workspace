<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Matching;

use LogicException;
use PhpArchitecture\Parser\Processing\Context\MatchingContext;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Matching\NestedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\Sequence;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceNode;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenStream;

class Matcher
{
    private const RECURSION_LIMIT = 100;

    /** @var array<string> */
    private array $currentStack = [];

    /** @var array<int,array<string>> */
    private array $currentOffsetStack = [];

    public function __construct(
        private readonly MatchingContext $context
    ) {}

    public function process(TokenRegion $region): MatchedRegion|MatchedSequence
    {
        $this->context->markMatchingStarted();

        $rootSequence = $this->context->getSequenceLibrary()->rootSequence;

        if ($rootSequence !== null) {
            $result = $this->processWithRoot($region, $rootSequence);
        } else {
            $result = $this->processWithoutRoot($region);
        }

        $this->context->markMatchingFinished();

        return $result;
    }

    private function processWithRoot(TokenRegion $region, Sequence $rootSequence): MatchedSequence
    {
        $offset = 0;
        $matchedSequence = $this->matchSequence($rootSequence, $region->stream, $offset);

        if ($matchedSequence === null) {
            throw new LogicException("Root sequence '{$rootSequence->name}' could not be matched for region '{$region->name}'.");
        }

        return $matchedSequence;
    }

    private function processWithoutRoot(TokenRegion $region): MatchedRegion
    {
        $stream = $region->stream;
        $offset = 0;

        while ($stream->has($offset)) {
            $matched = false;

            foreach ($this->context->getSequenceLibrary()->sequences as $sequence) {
                $startOffset = $offset;
                $matchedSequence = $this->matchSequence($sequence, $stream, $offset);

                if ($matchedSequence !== null) {
                    $matched = true;
                    break;
                }

                $offset = $startOffset;
            }

            if (!$matched) {
                $element = $stream->peek($offset++);

                if ($element instanceof Token) {
                    $this->context->addUnmatchedToken($element);
                } elseif ($element instanceof TokenRegion) {
                    $this->context->addUnmatchedTokenRegion($element);
                }
            }
        }

        return $this->context->getOutput();
    }

    private function matchSequence(Sequence $sequence, TokenStream $stream, int &$offset): ?MatchedSequence
    {
        if (!$stream->has($offset)) {
            return null;
        }

        $firstToken = $stream->peek($offset);
        $firstTokenName = $firstToken instanceof Token ? $firstToken->name : $firstToken->name;
        $validFirstNodes = $sequence->getFirstValidNodeNodeNames();

        if (!empty($validFirstNodes) && !in_array($firstTokenName, $validFirstNodes)) {
            return null;
        }

        if (in_array($sequence->name, $this->currentStack)) {
            $count = count(array_filter($this->currentStack, static fn(string $name) => $name === $sequence->name));
            if ($count > self::RECURSION_LIMIT) {
                throw new LogicException(
                    "Recursive loop detected. Sequence '{$sequence->name}' is called recursively over " . self::RECURSION_LIMIT . " times. Stack: " . implode(' -> ', $this->currentStack)
                );
            }
        }

        $this->currentStack[] = $sequence->name;
        $start = $offset;

        $items = [];
        foreach ($sequence->nodes as $node) {
            if ($node instanceof NestedSequence) {
                $nestedItems = $this->matchNestedSequence($node, $stream, $offset);
                if ($nestedItems === null) {
                    $offset = $start;
                    array_pop($this->currentStack);
                    return null;
                }
                $items = array_merge($items, $nestedItems);
            } elseif ($node instanceof SequenceNode) {
                $nodeItems = $this->matchSequenceNode($node, $stream, $offset);
                if ($nodeItems === null) {
                    $offset = $start;
                    array_pop($this->currentStack);
                    return null;
                }
                $items = array_merge($items, $nodeItems);
            }
        }

        array_pop($this->currentStack);

        $matchedSequence = new MatchedSequence($sequence->name, $items);

        return $matchedSequence;
    }

    /**
     * @return null|array<MatchedSequenceNode>
     */
    private function matchNestedSequence(NestedSequence $nestedSequence, TokenStream $stream, int &$offset): ?array
    {
        $count = 0;
        $allItems = [];
        $start = $offset;

        while ($count < $nestedSequence->max) {
            $matchedAlternative = false;

            foreach ($nestedSequence->alternativeSequences as $alternativeNodes) {
                $alternativeStart = $offset;
                $alternativeItems = [];
                $allMatched = true;

                foreach ($alternativeNodes as $node) {
                    $nodeStart = $offset;

                    if ($node->isLookbehind) {
                        $offset--;
                    }

                    if ($node instanceof NestedSequence) {
                        $items = $this->matchNestedSequence($node, $stream, $offset);
                        if ($items !== null) {
                            if ($node->isLookbehind) {
                                $offset = $nodeStart;
                                continue;
                            }
                            if ($node->isLookahead) {
                                $offset = $nodeStart;
                                break;
                            }
                            $alternativeItems = array_merge($alternativeItems, $items);
                        } else {
                            $allMatched = false;
                            break;
                        }
                    } elseif ($node instanceof SequenceNode) {
                        $items = $this->matchSequenceNode($node, $stream, $offset);
                        if ($items !== null) {
                            if ($node->isLookbehind) {
                                $offset = $nodeStart;
                                continue;
                            }
                            if ($node->isLookahead) {
                                $offset = $nodeStart;
                                break;
                            }
                            $alternativeItems = array_merge($alternativeItems, $items);
                        } else {
                            $allMatched = false;
                            break;
                        }
                    }
                }

                if ($allMatched) {
                    $allItems = array_merge($allItems, $alternativeItems);
                    $matchedAlternative = true;
                    $count++;
                    break;
                }

                $offset = $alternativeStart;
            }

            if (!$matchedAlternative) {
                break;
            }
        }

        if ($count < $nestedSequence->min) {
            $offset = $start;
            return null;
        }

        return $allItems;
    }

    /**
     * @return null|array<MatchedSequenceNode>
     */
    private function matchSequenceNode(SequenceNode $node, TokenStream $stream, int &$offset): ?array
    {
        $count = 0;
        $start = $offset;
        $items = [];

        while ($count < $node->max) {
            $currentOffset = $offset;
            $matched = false;

            foreach ($node->alternatives as $alternative) {
                $namedSequence = $this->context->getSequenceLibrary()->sequences[$alternative] ?? null;

                if ($namedSequence !== null) {
                    if (!in_array($alternative, $this->currentOffsetStack[$currentOffset] ?? [])) {
                        $this->currentOffsetStack[$currentOffset][] = $alternative;

                        $matchedSequence = $this->matchSequence($namedSequence, $stream, $offset);
                        if ($matchedSequence !== null) {
                            $items[] = new MatchedSequenceNode(
                                $matchedSequence->name,
                                $node->anchorName ?? '',
                                [$matchedSequence]
                            );
                            $matched = true;
                            unset($this->currentOffsetStack[$currentOffset]);
                            $count++;
                            break;
                        }
                    }
                } else {
                    $token = $stream->matchAny($offset, [$alternative]);
                    if ($token !== null) {
                        $items[] = new MatchedSequenceNode(
                            $token->name,
                            $node->anchorName ?? '',
                            [$token]
                        );
                        $matched = true;
                        $count++;
                        break;
                    }
                }
            }

            if (!$matched) {
                break;
            }
        }

        if ($count < $node->min) {
            $offset = $start;
            return null;
        }

        return $items;
    }
}
