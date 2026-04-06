<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser;

use PhpArchitecture\Parser\Processing\Context\ParsingContext;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
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

        return $context->nodeFactory()->fromTokenRegion($tokenizedRootRegion, null);
    }
}
