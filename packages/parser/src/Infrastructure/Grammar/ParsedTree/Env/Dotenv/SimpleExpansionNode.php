<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Env\Dotenv;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

class SimpleExpansionNode extends SequenceNode
{
    public RawContentAttribute $dollar { get => $this->attributes[0]; }

    public RawContentAttribute $varRef { get => $this->attributes[1]; }

    public static function create(string $dollar, string $varRef): self
    {
        return new self(
            name: 'simpleExpansion',
            origin: NodeOrigin::Sequence,
            attributes: [
                new RawContentAttribute($dollar),
                new RawContentAttribute($varRef),
            ],
            parent: null,
        );
    }

    public function getRawDollar(): string
    {
        return $this->dollar->content;
    }

    public function setRawDollar(string $value): self
    {
        $this->dollar->content = $value;
        return $this;
    }

    public function getRawVarRef(): string
    {
        return $this->varRef->content;
    }

    public function setRawVarRef(string $value): self
    {
        $this->varRef->content = $value;
        return $this;
    }
}
