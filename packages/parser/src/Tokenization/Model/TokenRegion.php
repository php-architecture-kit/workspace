<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Model;

use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use Stringable;

final class TokenRegion implements Stringable
{
    use MetaTrait;

    public const KEY_PARENT = 'parentRegion';

    public function __construct(
        public readonly string $name,
        public readonly TokenStream $stream,
    ) {}

    public static function new(string $name): self
    {
        return new self($name, new TokenStream());
    }

    public function __toString(): string
    {
        return implode(
            '',
            array_map(
                static fn(Token|TokenRegion $token): string => $token->__toString(),
                $this->stream->tokens
            )
        );
    }
}
