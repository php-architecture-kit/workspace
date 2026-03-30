<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Tokenization;

use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use Stringable;

final class TokenRegion implements Stringable
{
    use MetaTrait;

    public const KEY_PARENT = 'parentRegion';

    public function __construct(
        public private(set) string $name,
        public private(set) TokenStream $stream,
    ) {}

    public static function new(string $name): self
    {
        return new self($name, new TokenStream());
    }

    public function firstToken(): null|Token
    {
        $first = $this->stream->first();
        if ($first instanceof TokenRegion) {
            return $first->firstToken();
        }

        return $first;
    }

    public function lastToken(): null|Token
    {
        $last = $this->stream->last();
        if ($last instanceof TokenRegion) {
            return $last->lastToken();
        }

        return $last;
    }

    public function rename(string $name): self
    {
        $this->setMeta("renamedFrom", array_merge($this->getMeta("renamedFrom", []), [$this->name]));

        $this->name = $name;

        return $this;
    }

    public function replaceTokenStream(TokenStream $stream): self
    {
        $this->setMeta("streamReplacedFrom", array_merge($this->getMeta("streamReplacedFrom", []), [$this->stream]));

        $this->stream = $stream;

        return $this;
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
