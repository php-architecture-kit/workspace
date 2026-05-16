<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

use Stringable;

final class PhpClassFileTemplate implements Stringable
{
    public function __construct(
        public NamespaceStmtTemplate $namespaceStmt,
        public ClassStmtTemplate $classStmt,
    ) {}

    /** @return UseStmtTemplate[] */
    public function computeUseStmts(): array
    {
        $imports = [];
        if ($this->classStmt->extendsClass !== null && !$this->namespaceStmt->isFqcnDirectChild($this->classStmt->extendsClass)) {
            $imports[] = $this->classStmt->extendsClass;
        }

        foreach ($this->classStmt->docblock->propertyTemplates as $property) {
            $imports[] = $property->attributeClass;
            foreach ($property->nodeClasses as $nodeClass) {
                if (!$this->namespaceStmt->isFqcnDirectChild($nodeClass)) {
                    $imports[] = $nodeClass;
                }
            }
        }

        sort($imports);
        return array_map(static fn(string $fqcn) => new UseStmtTemplate($fqcn), array_unique($imports));
    }

    public function __toString(): string
    {
        $useStmts = $this->computeUseStmts();

        $content  = '<?php' . PHP_EOL . PHP_EOL;
        $content .= 'declare(strict_types=1);' . PHP_EOL . PHP_EOL;
        $content .= (string) $this->namespaceStmt . PHP_EOL;
        $content .= PHP_EOL;
        $content .= implode(PHP_EOL, array_map(static fn(UseStmtTemplate $useStmt) => (string) $useStmt, $useStmts)) . PHP_EOL;
        $content .= PHP_EOL;
        $content .= (string) $this->classStmt . PHP_EOL;

        return $content;
    }
}
