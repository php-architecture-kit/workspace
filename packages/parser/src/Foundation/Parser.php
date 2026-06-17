<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Lexer;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;

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

        $rootNode = $context->nodeFactory()->fromTokenRegion($tokenizedRootRegion, null);

        $this->initializeContext($rootNode, $context);

        return $rootNode;
    }

    private function initializeContext(NodeInterface $rootNode, ParsingContext $context): void
    {
        foreach ($context->grammar()->contextInitializers as $initializer) {
            $initializer($rootNode);
        }
    }
}
