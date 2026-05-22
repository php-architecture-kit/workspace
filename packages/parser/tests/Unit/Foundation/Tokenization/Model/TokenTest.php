<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Tokenization\Model;

use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TokenTest extends TestCase
{
    #[Test]
    public function shouldHaveSkipTagOnBofToken(): void
    {
        self::assertTrue(Token::bof()->hasTag(NodeType::Skip->value));
    }

    #[Test]
    public function shouldHaveSkipTagOnEofToken(): void
    {
        self::assertTrue(Token::eof(0)->hasTag(NodeType::Skip->value));
    }

    #[Test]
    public function shouldNotHaveSkipTagOnUnknownToken(): void
    {
        self::assertFalse(Token::unknown('x', 0)->hasTag(NodeType::Skip->value));
    }
}
