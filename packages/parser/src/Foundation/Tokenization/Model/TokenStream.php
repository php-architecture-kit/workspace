<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Model;

class TokenStream
{
    /** @var array<Token|TokenRegion> */
    public private(set) array $tokens = [];
    public private(set) int $topAskOffset = 0;

    public function add(Token|TokenRegion $token): void
    {
        $this->tokens[] = $token;
    }

    public function first(): null|Token|TokenRegion
    {
        return $this->tokens[0] ?? null;
    }

    public function get(int $offset): null|Token|TokenRegion
    {
        return $this->tokens[$offset] ?? null;
    }

    public function has(int $offset): bool
    {
        return isset($this->tokens[$offset]);
    }

    public function remove(int $offset): void
    {
        unset($this->tokens[$offset]);
        $this->tokens = array_values($this->tokens);
    }

    public function is(int $offset, string $token): bool
    {
        $peekedToken = $this->peek($offset);

        return $peekedToken !== null
            && ($peekedToken->name === $token || in_array($token, $peekedToken->tags));
    }

    public function last(): null|Token|TokenRegion
    {
        $last = $this->lastOffset();

        return $last === null ? null : ($this->tokens[$last] ?? null);
    }

    public function lastOffset(): ?int
    {
        $res = array_key_last($this->tokens);

        return $res === null ? null : (int) $res;
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
