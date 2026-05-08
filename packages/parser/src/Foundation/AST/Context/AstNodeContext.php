<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Context;

use PhpArchitecture\Parser\Foundation\AST\Model\Identity\NodeId;

class AstNodeContext
{
    public function __construct(
        public readonly NodeId $nodeId,
        public readonly ?string $name = null,
        public private(set) array $data = [],
    ) {}

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }
}
