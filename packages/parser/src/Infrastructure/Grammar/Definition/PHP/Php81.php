<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\PHP;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class Php81 extends Php80
{
    public const FORMAT = "php";
    public const VARIANT = "8.1";

    public function grammar(): Grammar
    {
        parent::grammar();

        $origin = new GrammarOrigin(self::FORMAT, self::VARIANT);

        $this->grammar->stampOrigin($origin);

        return $this->grammar;
    }
}
