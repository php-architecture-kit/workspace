<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\PHP;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class Php70 extends Php56
{
    public const FORMAT = "php";
    public const VARIANT = "7.0";

    public function grammar(): Grammar
    {
        parent::grammar();

        // $this->grammar->global->add(
        // );

        $origin = new GrammarOrigin(self::FORMAT, self::VARIANT);

        $this->grammar->global->removeRule("aspOpenTag", $origin);
        $this->grammar->global->removeRule("aspOpenTagWithEcho", $origin);
        $this->grammar->global->removeRule("scriptOpenTag", $origin);
        $this->grammar->global->regions['code']->removeRule("aspCloseTag", $origin);
        $this->grammar->global->regions['code']->removeRule("scriptCloseTag", $origin);

        $this->grammar->stampOrigin($origin);

        return $this->grammar;
    }
}
