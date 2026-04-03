<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser;

use PhpArchitecture\Parser\Matching\Matcher;
use PhpArchitecture\Parser\Processing\Context\ParsingContext;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Lexer;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;

class Parser
{
    public function parse(
        string|StringStream $stream,
        ParsingContext $context,
    ): NodeInterface {
        if (is_string($stream)) {
            $stream = new StringStream($stream);
        }

        $lexer = new Lexer($context->tokenizationContext());
        $tokenizedRootRegion = $lexer->process($stream);

        $this->parseRegionRecursive($tokenizedRootRegion, $context, null);

        return $context->getOutput();
    }

    private function parseRegionRecursive(TokenRegion $region, ParsingContext $context, ?NodeInterface $parent): void
    {
        $matchingContext = $context->matchingContextForRegion($region);
        if ($matchingContext === null) {
            $node = $context->nodeFactory()->fromTokenRegion($region);
            $context->addNode($parent, $node);

            if ($region->hasTag(NodeType::Node->value)) {
                foreach ($region->stream->tokens as $tokenOrRegion) {
                    if ($tokenOrRegion instanceof TokenRegion) {
                        $this->parseRegionRecursive($tokenOrRegion, $context, $node);
                        continue;
                    }

                    $this->parseToken($tokenOrRegion, $context, $node);
                }
            }

            return;
        }

        $matcher = new Matcher($matchingContext);
        $matchedRegionOrSequence = $matcher->process($region);

        if ($matchedRegionOrSequence instanceof MatchedRegion) {
            $this->parseMatchedRegionRecursive($matchedRegionOrSequence, $context, $parent);
            return;
        }

        $this->parseMatchedSequenceRecursive($matchedRegionOrSequence, $context, $parent);
    }

    private function parseMatchedRegionRecursive(MatchedRegion $region, ParsingContext $context, ?NodeInterface $parent): void
    {
        $node = $context->nodeFactory()->fromMatchedRegion($region);
        $context->addNode($parent, $node);

        if ($region->hasTag(NodeType::Node->value)) {
            foreach ($region->items as $item) {
                if ($item instanceof Token) {
                    $this->parseToken($item, $context, $node);
                    continue;
                }
                if ($item instanceof TokenRegion) {
                    $this->parseRegionRecursive($item, $context, $node);
                    continue;
                }
                assert($item instanceof MatchedSequence);
                $this->parseMatchedSequenceRecursive($item, $context, $node);
            }
        }
    }

    private function parseMatchedSequenceRecursive(MatchedSequence $sequence, ParsingContext $context, ?NodeInterface $parent): void
    {
        $node = $context->nodeFactory()->fromMatchedSequence($sequence);
        $context->addNode($parent, $node);

        if ($sequence->hasTag(NodeType::Node->value)) {
            foreach ($sequence->items as $item) {
                $this->parseMatchedSequenceNodeRecursive($item, $context, $node);
            }
        }
    }

    private function parseMatchedSequenceNodeRecursive(MatchedSequenceNode $sequenceNode, ParsingContext $context, NodeInterface $parent): void
    {
        $node = $context->nodeFactory()->fromMatchedSequenceNode($sequenceNode);
        $context->addNode($parent, $node);

        if ($sequenceNode->hasTag(NodeType::Node->value)) {
            foreach ($sequenceNode->items as $item) {
                if ($item instanceof Token) {
                    $this->parseToken($item, $context, $node);
                    continue;
                }
                if ($item instanceof TokenRegion) {
                    $this->parseRegionRecursive($item, $context, $node);
                    continue;
                }
                assert($item instanceof MatchedSequence);
                $this->parseMatchedSequenceRecursive($item, $context, $node);
            }
        }
    }

    private function parseToken(Token $token, ParsingContext $context, NodeInterface $parent): void
    {
        $node = $context->nodeFactory()->fromToken($token);
        $context->addNode($parent, $node);
    }
}
