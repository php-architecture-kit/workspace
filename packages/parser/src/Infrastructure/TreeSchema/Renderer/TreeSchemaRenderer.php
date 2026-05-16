<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer;

use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\PhpClassFileTemplate;

final class TreeSchemaRenderer
{
    public function render(PhpClassFileTemplate $template): string
    {
        return (string) $template;
    }
}
