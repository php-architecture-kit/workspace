<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;

class Structure implements NodeInterface, MetaInterface
{
    use MetaTrait;
    use TagsTrait;

    public function __construct(
        public string $name,
        public bool $present,
    ) {}
}
