<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Model;

class TokenStream
{
    /** @var array<Token|TokenRegion> */
    public private(set) array $tokens = [];
    public private(set) int $topAskOffset = 0;

    public function add(Token|TokenRegion $token): void
    {
        $this->tokens[] = $token;
    }

    public function has(int $offset): bool
    {
        return isset($this->tokens[$offset]);
    }

    public function is(int $offset, string $token): bool
    {
        $peekedToken = $this->peek($offset);
        return $peekedToken !== null && $peekedToken->name === $token;
    }

    /**
     * @param string[] $tokens
     */
    public function matchAny(int &$offset, array $tokens): null|Token|TokenRegion
    {
        foreach ($tokens as $token) {
            if ($this->is($offset, $token)) {
                return $this->peek($offset++);
            }
        }

        return null;
    }

    public function peek(int $offset = 0): null|Token|TokenRegion
    {
        if ($offset > $this->topAskOffset) {
            $this->topAskOffset = $offset;
        }

        return $this->tokens[$offset] ?? null;
    }
}
