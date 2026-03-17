<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Member;

use PhpArchitecture\Parser\Grammar;
use PhpArchitecture\Parser\Model\MetaTrait;
use PhpArchitecture\Parser\Model\Token\TokenInterface;
use Stringable;

final class MemberTree implements Stringable
{
    use MetaTrait;

    /**
     * @param array<TokenInterface|Member|MemberTree> $members
     */
    public function __construct(
        public readonly Grammar $grammar,
        public readonly array $members,
    ) {}

    public function raw(): string
    {
        return implode(
            '',
            array_map(
                static fn(TokenInterface|Member|MemberTree $member): string => $member->raw(),
                $this->members
            )
        );
    }
    
    public function __toString(): string
    {
        return $this->raw();
    }
}
