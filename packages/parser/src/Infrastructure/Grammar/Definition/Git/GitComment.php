<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Git;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class GitComment extends Whitespace
{
    public const FORMAT = 'git';
    public const VARIANT = 'comment';

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->requireBofEof = false;

        $region = (new Region("lineComment"))
            ->setInheritanceFromGlobal()
            ->add(
                Rule::token("hash", "#", type: NodeType::Structure),
                Rule::expr("word", "\S+")
                    ->priority(-1),
            )
            ->withRootSequence("hash ?inlineWs[leadingWs]/r ?(word (inlineWs/r word)*)[content]/r -t*/r");

        $this->grammar->global->add($region);
        $this->grammar->setRootRegion($region);

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
