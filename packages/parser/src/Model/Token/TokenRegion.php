<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Token;

use PhpArchitecture\Parser\Model\MetaTrait;

final class TokenRegion implements TokenInterface
{
    use MetaTrait;

    /**
     * @param TokenInterface[] $tokens
     */
    public function __construct(
        public readonly string $name,
        public readonly array $tokens,
    ) {}

    public function raw(): string
    {
        return implode(
            '',
            array_map(
                static fn(TokenInterface $token): string => $token->raw(),
                $this->tokens
            )
        );
    }

    public function __toString(): string
    {
        return $this->raw();
    }
}
