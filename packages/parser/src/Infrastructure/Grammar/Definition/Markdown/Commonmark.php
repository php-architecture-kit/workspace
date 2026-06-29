<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Markdown;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class Commonmark extends Whitespace
{
    public const FORMAT = "Markdown";
    public const VARIANT = "commonmark";

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
