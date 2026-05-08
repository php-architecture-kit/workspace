<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

use Closure;
use PhpArchitecture\Parser\Foundation\AST\Definition\AstDefinitionInterface;
use PhpArchitecture\Parser\Foundation\AST\Definition\AttributeDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\ChildDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\ContextDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\EdgeDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\EdgeType;
use PhpArchitecture\Parser\Foundation\AST\Definition\FormatDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\ReferenceDefinition;

class Definition
{
    /** @var AstDefinitionInterface[] */
    public private(set) array $definitions = [];

    public function __construct(
        public private(set) string $name,
    ) {
    }

    public function add(AstDefinitionInterface ...$definitions): self
    {
        foreach ($definitions as $definition) {
            $this->definitions[] = $definition;
        }
        return $this;
    }

    public static function attribute(
        string $name,
        string $type,
        bool $optional = false,
        ?string $defaultValue = null,
        ?Closure $toGraphMapper = null,
        ?Closure $toTreeMapper = null,
    ): AttributeDefinition {
        return new AttributeDefinition(
            name: $name,
            type: $type,
            optional: $optional,
            defaultValue: $defaultValue,
            toGraphMapper: $toGraphMapper ?? static fn($node) => $node->attributes[$name] ?? null,
            toTreeMapper: $toTreeMapper ?? static fn($node, $value) => null,
        );
    }

    public static function child(
        string $name,
        string $edgeName,
        EdgeType $edgeType = EdgeType::Structural,
        bool $optional = false,
        ?Closure $toGraphMapper = null,
        ?Closure $toTreeMapper = null,
    ): ChildDefinition {
        return new ChildDefinition(
            name: $name,
            edge: new EdgeDefinition($edgeName, $edgeType),
            toGraphMapper: $toGraphMapper ?? static fn($node) => $node->attributes[$name] ?? null,
            toTreeMapper: $toTreeMapper ?? static fn($node, $child) => null,
            optional: $optional,
        );
    }

    public static function context(
        string $name,
        ?Closure $toGraphMapper = null,
    ): ContextDefinition {
        return new ContextDefinition(
            name: $name,
            toGraphMapper: $toGraphMapper ?? static fn($node) => $node->meta[$name] ?? null,
        );
    }

    public static function format(string $name): FormatDefinition
    {
        return new FormatDefinition(name: $name);
    }

    public static function reference(
        string $name,
        string $edgeName,
        EdgeType $edgeType = EdgeType::Semantic,
        ?Closure $toGraphMapper = null,
    ): ReferenceDefinition {
        return new ReferenceDefinition(
            name: $name,
            edge: new EdgeDefinition($edgeName, $edgeType),
            toGraphMapper: $toGraphMapper ?? static fn($node) => $node->meta[$name] ?? null,
        );
    }
}
