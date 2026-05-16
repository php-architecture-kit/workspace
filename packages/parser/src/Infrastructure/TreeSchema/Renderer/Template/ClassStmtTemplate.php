<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use Stringable;

readonly class ClassStmtTemplate implements Stringable
{
    /**
     * @param class-string<NodeInterface> $extendsClass
     */
    public function __construct(
        public string $className,
        public ?string $extendsClass,
        public DocblockTemplate $docblock,
    ) {}

    public function __toString(): string
    {
        $extendsClause = '';
        if ($this->extendsClass !== null) {
            $pos = strrpos($this->extendsClass, '\\');
            $shortName = $pos !== false ? substr($this->extendsClass, $pos + 1) : $this->extendsClass;
            $extendsClause = ' extends ' . $shortName;
        }

        return (string) $this->docblock . "\n" . "class " . $this->className . $extendsClause . " {}";
    }
}
