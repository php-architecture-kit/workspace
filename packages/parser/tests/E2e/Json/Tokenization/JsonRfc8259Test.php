<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\E2e\Json\Tokenization;

use PhpArchitecture\Parser\GrammarRegistry\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Tokenization\Lexer;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Tokenization\TokenizationContextCompiler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('e2e')]
class JsonRfc8259Test extends TestCase
{
    #[Test]
    public function shouldTokenizeJsonRfc8259(): void
    {
        $grammar = (new JsonRfc8259())->grammar();
        $tokenizationCompiler = new TokenizationContextCompiler();
        $context = $tokenizationCompiler->compile($grammar);
        $lexer = new Lexer($context);
        $output = $lexer->process(
            new StringStream(file_get_contents(__DIR__ . '/../../../Data/Json/rfc8259/testfile_1.json')),
            'json',
            'rfc8259'
        );

        $this->assertCount(322, $output->stream->tokens);
    }
}
