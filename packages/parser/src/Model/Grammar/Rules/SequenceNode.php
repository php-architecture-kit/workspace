<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar\Rules;

use InvalidArgumentException;

final class SequenceNode
{
    /**
     * @param string[] $alternatives
     */
    public function __construct(
        public readonly array $alternatives,
        public readonly Cardinality $cardinality,
        public readonly bool $isLookahead = false,
        public readonly bool $isLookbehind = false,
    ) {}

    /**
     * @param string $sequenceNode ex.:
     * - token
     * - member
     * - union|of|members|or|tokens
     * - ?zeroOrOne
     * - zeroOrMore*
     * - oneOrMore+
     * - exactlyOne
     * - >lookahead
     * - <lookbehind
     */
    public static function fromString(string $sequenceNode): self
    {
        if (!preg_match(
            '/^(?<lookahead>>)?(?<lookbehind><)?(?<optional>\?)?(?<name>[a-zA-Z\s\-_|][a-zA-Z0-9\s\-_|]*)(?<quantifier>[+*])?$/',
            $sequenceNode,
            $m,
        )) {
            throw new InvalidArgumentException("Invalid sequence node: `{$sequenceNode}`");
        }

        $isLookahead = !empty($m['lookahead']);
        $isLookbehind = !empty($m['lookbehind']);
        if ($isLookahead && $isLookbehind) {
            throw new InvalidArgumentException("Invalid sequence node: `{$sequenceNode}`. Lookahead and lookbehind are not allowed to be used at the same time.");
        }

        $name = $m['name'] ?? '';
        if (empty($name)) {
            throw new InvalidArgumentException("Invalid sequence node: `{$sequenceNode}`");
        }

        $alternatives = explode('|', $name);
        $quantifier = $m['quantifier'] ?? '';
        $isAsterisk = $quantifier === '*';
        $optional   = $m['optional'] === '?' || $isAsterisk;
        $oneOrMore  = $quantifier === '+' || $isAsterisk;

        $cardinality = match (true) {
            $optional && $oneOrMore,
            $isAsterisk => Cardinality::ZeroOrMore,
            $optional => Cardinality::ZeroOrOne,
            $oneOrMore => Cardinality::OneOrMore,
            default => Cardinality::ExactlyOne,
        };

        if (
            ($isLookahead || $isLookbehind)
            && $cardinality->max() !== 1
        ) {
            throw new InvalidArgumentException("Lookahead and lookbehind are not allowed to be repeated. It must be presented max 1 time. Sequence node: `{$sequenceNode}`.");
        }

        return new self($alternatives, $cardinality, $isLookahead, $isLookbehind);
    }

    public function assertSequenceMatchAnchorRequirements(SequenceNode $originalSequenceNode): void
    {
        if ($this->isLookahead || $this->isLookbehind) {
            throw new InvalidArgumentException("Anchor sequence node `{$this->toString()}` validation failed. Lookahead and lookbehind are not allowed to be used in anchor sequence. Use `-` instead.");
        }

        if ($this->cardinality !== Cardinality::ExactlyOne) {
            throw new InvalidArgumentException("Anchor sequence node `{$this->toString()}` validation failed. Only Cardinality::ExactlyOne is allowed for the anchor sequence node.");
        }

        if (count($this->alternatives) !== 1) {
            throw new InvalidArgumentException("Anchor sequence node `{$this->toString()}` validation failed. Anchor sequence node can't be a union of alternative anchor names. Remove `|` and replace it with `or` if You really need it.");
        }

        if (($originalSequenceNode->isLookahead || $originalSequenceNode->isLookbehind) && $this->toString() !== '-') {
            throw new InvalidArgumentException("Anchor sequence node `{$this->toString()}` validation failed. Anchored sequence node is marked as lookahead or lookbehind. The only allowed name for anchor is `-`.");
        }
    }

    /**
     * @return string[]
     */
    public function getAllNodeNames(): array
    {
        return $this->alternatives;
    }

    public function toString(): string
    {
        $name = implode('|', $this->alternatives);

        $prefix = '';
        if ($this->isLookahead) {
            $prefix = '>';
        } elseif ($this->isLookbehind) {
            $prefix = '<';
        }

        return sprintf(
            '%s%s%s%s',
            $prefix,
            $this->cardinality === Cardinality::ZeroOrOne ? '?' : '',
            $name,
            match ($this->cardinality) {
                Cardinality::OneOrMore => '+',
                Cardinality::ZeroOrMore => '*',
                default => '',
            },
        );
    }
}
