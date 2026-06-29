<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Markdown;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;

class Gfm extends Commonmark
{
    public const FORMAT = "Markdown";
    public const VARIANT = "gfm";

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
