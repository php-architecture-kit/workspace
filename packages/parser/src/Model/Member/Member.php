<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Member;

use PhpArchitecture\Parser\Model\MetaTrait;
use PhpArchitecture\Parser\Model\Token\TokenInterface;
use Stringable;

final class Member implements Stringable
{
    use MetaTrait;

    /**
     * @param array<TokenInterface|Member|MemberTree> $members
     */
    public function __construct(
        public readonly string $name,
        public readonly array $members,
    ) {}

    /**
     * @param callable(TokenInterface|Member|MemberTree):bool $filter
     * 
     * @return array<TokenInterface|Member>
     */
    public function members(?callable $filter = null): array
    {
        return $filter
            ? array_filter($this->members, $filter)
            : $this->members;
    }

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
