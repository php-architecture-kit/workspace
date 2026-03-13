<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Token;

final class TokenStream
{
    public int $topAskOffset = 0;

    /**
     * @param TokenInterface[] $tokens
     */
    public function __construct(
        public readonly array $tokens,
    ) {}

    public function has(int $offset): bool
    {
        return isset($this->tokens[$offset]);
    }

    public function is(int $offset, string $token): bool
    {
        $peekedToken = $this->peek($offset);
        return $peekedToken !== null && $peekedToken->name() === $token;
    }

    /**
     * @param string[] $tokens
     */
    public function matchAny(int &$offset, array $tokens): ?TokenInterface
    {
        foreach ($tokens as $token) {
            if ($this->is($offset, $token)) {
                return $this->peek($offset++);
            }
        }

        return null;
    }

    public function peek(int $offset = 0): ?TokenInterface
    {
        if ($offset > $this->topAskOffset) {
            $this->topAskOffset = $offset;
        }

        return $this->tokens[$offset] ?? null;
    }
}
