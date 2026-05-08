<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Matching;

use LogicException;
use PhpArchitecture\Parser\Foundation\Matching\Contract\MatchingContext;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenStream;

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

        if ($matchedSequence !== null) {
            $this->context->addMatchedSequence($matchedSequence);
            return $matchedSequence;
        }

        // Build detailed error message
        $errorMsg = "Root sequence '{$rootSequence->name}' could not be matched for region '{$region->name}'.\n";
        $errorMsg .= "TokenStream topAskOffset: {$region->stream->topAskOffset}\n\n";

        // Show sequence structure
        $errorMsg .= "Expected sequence structure:\n";
        $nodeDescriptions = [];
        foreach ($rootSequence->nodes as $idx => $node) {
            if ($node instanceof SequenceNode) {
                $alternatives = implode(' | ', $node->alternatives);
                $cardinality = $node->min === $node->max
                    ? "exactly {$node->min}"
                    : "min:{$node->min}, max:{$node->max}";
                $nodeDescriptions[] = "  [{$idx}] ({$alternatives}) - {$cardinality}";
            } elseif ($node instanceof NestedSequence) {
                $nodeDescriptions[] = "  [{$idx}] <nested sequence> - min:{$node->min}, max:{$node->max}";
            }
        }
        $errorMsg .= implode("\n", $nodeDescriptions) . "\n\n";

        // Show available tokens at start
        $errorMsg .= "Available tokens in region (first 10):\n";
        $tokenCount = 0;
        $tokenOffset = 0;
        while ($region->stream->has($tokenOffset) && $tokenCount < 10) {
            $token = $region->stream->peek($tokenOffset);
            if ($token instanceof Token) {
                $displayValue = strlen($token->raw) > 30 ? substr($token->raw, 0, 30) . '...' : $token->raw;
                $tagsStr = count($token->tags) > 0 ? ' [' . implode(', ', $token->tags) . ']' : '';
                $errorMsg .= "  [{$tokenOffset}] {$token->name}: " . json_encode($displayValue) . "{$tagsStr}\n";
            } elseif ($token instanceof TokenRegion) {
                $tagsStr = count($token->tags) > 0 ? ' [' . implode(', ', $token->tags) . ']' : '';
                $errorMsg .= "  [{$tokenOffset}] <region: {$token->name}>{$tagsStr}\n";
            }
            $tokenOffset++;
            $tokenCount++;
        }

        if ($region->stream->has($tokenOffset)) {
            $totalTokens = 0;
            $countOffset = 0;
            while ($region->stream->has($countOffset)) {
                $countOffset++;
                $totalTokens++;
            }
            $errorMsg .= "  ... and " . ($totalTokens - 10) . " more tokens\n";
        }

        throw new LogicException($errorMsg);
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

        // Use precomputed expanded first valid tokens from SequenceLibrary
        $expandedValidTokens = $this->context->getSequenceLibrary()->getExpandedFirstValidTokens($sequence->name);

        if (!empty($expandedValidTokens)) {
            $isValid = in_array($firstTokenName, $expandedValidTokens);

            if (!$isValid) {
                foreach ($firstToken->tags as $tag) {
                    if (in_array($tag, $expandedValidTokens)) {
                        $isValid = true;
                        break;
                    }
                }
            }

            if (!$isValid) {
                return null;
            }
        }

        if (in_array($sequence->name, $this->currentStack)) {
            $count = count(array_filter($this->currentStack, static fn(string $name) => $name === $sequence->name));
            if ($count > self::RECURSION_LIMIT) {
                throw new LogicException(
                    "Recursive loop detected. Sequence '{$sequence->name}' is called recursively over " . self::RECURSION_LIMIT . " times. Stack: " . implode(' -> ', $this->currentStack),
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
                $matchedNode = $this->matchSequenceNode($node, $stream, $offset);
                if ($matchedNode === null) {
                    $offset = $start;
                    array_pop($this->currentStack);
                    return null;
                }
                $items[] = $matchedNode;
            }
        }

        array_pop($this->currentStack);

        $matchedSequence = new MatchedSequence(
            $sequence->name,
            $items,
            $sequence->meta,
            $sequence->tags,
        );

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
                        $matchedNode = $this->matchSequenceNode($node, $stream, $offset);
                        if ($matchedNode !== null) {
                            if ($node->isLookbehind) {
                                $offset = $nodeStart;
                                continue;
                            }
                            if ($node->isLookahead) {
                                $offset = $nodeStart;
                                break;
                            }
                            $alternativeItems[] = $matchedNode;
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
     * @return null|MatchedSequenceNode
     */
    private function matchSequenceNode(SequenceNode $node, TokenStream $stream, int &$offset): null|MatchedSequenceNode
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
                            $items[] = $matchedSequence;

                            $matched = true;
                            unset($this->currentOffsetStack[$currentOffset]);
                            $count++;
                            break;
                        }

                        $token = $stream->matchAny($offset, [$alternative]);
                        if ($token !== null) {
                            $items[] = $token;
                            $matched = true;
                            unset($this->currentOffsetStack[$currentOffset]);
                            $count++;
                            break;
                        }
                    }
                } else {
                    $token = $stream->matchAny($offset, [$alternative]);
                    if ($token !== null) {
                        $items[] = $token;
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

        return new MatchedSequenceNode(
            $node->anchorName ?? implode('|', $node->alternatives),
            $items,
            $node->min,
            $node->max,
            $node->meta,
            $node->tags,
        );
    }
}
