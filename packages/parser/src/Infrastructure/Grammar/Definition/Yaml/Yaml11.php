<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Yaml;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;

class Yaml11 extends Yaml10
{
    public const FORMAT = "yaml";
    public const VARIANT = "1.1";

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
