<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Lexer;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('func')]
abstract class GrammarTestCase extends TestCase
{
    final protected function assertGrammarParsing(
        string $string,
        Grammar $grammar,
        ?callable $assertDefinedGrammarValid = null,
        ?callable $assertCompiledGrammarValid = null,
        ?callable $assertInitialParsingContextValid = null,
        ?callable $assertTokenizationResultValid = null,
        ?callable $assertParsingContextAfterTokenizationValid = null,
        ?callable $assertParsingResultValid = null,
        ?callable $assertParsingContextAfterParsingValid = null,
    ): void {
        $this->assertGrammarDefinitionStage($grammar, $assertDefinedGrammarValid);

        try {
            $compiledGrammar = (new GrammarCompiler())->compile($grammar);
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                '[Grammar compilation] Failed to compile grammar "%s": %s',
                $grammar->name,
                $e->getMessage(),
            ));
        }

        $this->assertCompiledGrammarStage($compiledGrammar, $assertCompiledGrammarValid);

        try {
            $context = new DefaultParsingContext($compiledGrammar);
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                '[Parsing context] Failed to create DefaultParsingContext for grammar "%s": %s',
                $compiledGrammar->name,
                $e->getMessage(),
            ));
        }

        $this->assertInitialParsingContextStage($context, $assertInitialParsingContextValid);

        $stream = new StringStream($string);

        try {
            $tokenRegion = (new Lexer($context->tokenizationContext()))->process($stream);
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                '[Tokenization] Lexer threw an exception for grammar "%s" with input "%s": %s',
                $grammar->name,
                $string,
                $e->getMessage(),
            ));
        }

        $this->assertTokenizationResultStage($tokenRegion, $assertTokenizationResultValid);
        $this->assertParsingContextAfterTokenizationStage($context, $assertParsingContextAfterTokenizationValid);

        try {
            $result = $context->nodeFactory()->fromTokenRegion($tokenRegion, null);
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                '[Node factory] Failed to parse token region for grammar "%s" with input "%s": %s',
                $grammar->name,
                $string,
                $e->getMessage(),
            ));
        }

        $this->assertParsingResultStage($result, $assertParsingResultValid);
        $this->assertParsingContextAfterParsingStage($context, $assertParsingContextAfterParsingValid);
    }

    protected function assertGrammarDefinitionStage(Grammar $grammar, ?callable $assert): void
    {
        $this->assertNotEmpty(
            $grammar->name,
            '[Grammar definition] Grammar must have a non-empty name.',
        );
        $this->assertNotEmpty(
            $grammar->getAllRegions(),
            '[Grammar definition] Grammar must contain at least one region.',
        );

        if ($assert !== null) {
            $assert($grammar, $this);
        }
    }

    protected function assertCompiledGrammarStage(CompiledGrammar $compiledGrammar, ?callable $assert): void
    {
        $this->assertNotEmpty(
            $compiledGrammar->regions,
            sprintf('[Compiled grammar] Grammar "%s" must have at least one compiled region.', $compiledGrammar->name),
        );
        $this->assertArrayHasKey(
            $compiledGrammar->rootRegionName,
            $compiledGrammar->regions,
            sprintf(
                '[Compiled grammar] Root region "%s" must exist among compiled regions of grammar "%s".',
                $compiledGrammar->rootRegionName,
                $compiledGrammar->name,
            ),
        );

        if ($assert !== null) {
            $assert($compiledGrammar, $this);
        }
    }

    protected function assertInitialParsingContextStage(DefaultParsingContext $context, ?callable $assert): void
    {
        $this->assertNotNull(
            $context->tokenizationContext(),
            '[Parsing context] DefaultParsingContext must provide a tokenization context.',
        );
        $this->assertNotNull(
            $context->nodeFactory(),
            '[Parsing context] DefaultParsingContext must provide a node factory.',
        );

        if ($assert !== null) {
            $assert($context, $this);
        }
    }

    protected function assertTokenizationResultStage(TokenRegion $tokenRegion, ?callable $assert): void
    {
        if ($assert !== null) {
            $assert($tokenRegion, $this);
        }
    }

    protected function assertParsingContextAfterTokenizationStage(DefaultParsingContext $context, ?callable $assert): void
    {
        if ($assert !== null) {
            $assert($context, $this);
        }
    }

    protected function assertParsingResultStage(NodeInterface $result, ?callable $assert): void
    {
        if ($assert !== null) {
            $assert($result, $this);
        }
    }

    protected function assertParsingContextAfterParsingStage(DefaultParsingContext $context, ?callable $assert): void
    {
        if ($assert !== null) {
            $assert($context, $this);
        }
    }
}
