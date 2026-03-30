<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Model\Sequence;

use InvalidArgumentException;
use PhpArchitecture\Parser\Grammar\Definition\Model\Cardinality;

final class SequenceNode
{
    /**
     * @param string[] $alternatives
     * @param string[] $tags
     */
    public function __construct(
        public array $alternatives,
        public Cardinality $cardinality,
        public bool $isLookahead = false,
        public bool $isLookbehind = false,
        public ?string $anchorName = null,
        public array $tags = [],
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
     * - token+[anchorName]
     * - token+[anchorName]/t
     * - token+/s
     */
    public static function fromString(string $sequenceNode): self
    {
        if (!preg_match(
            '/^(?<lookahead>>)?(?<lookbehind><)?(?<optional>\?)?(?<name>[a-zA-Z\s\-_|][a-zA-Z0-9\s\-_|]*)(?<quantifier>[+*])?(?:\[(?<anchor>[a-zA-Z0-9\s\-_]+)\])?(?:\/(?<tags>[a-zA-Z]+))?$/',
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

        $anchorName = !empty($m['anchor']) ? $m['anchor'] : null;
        $tags = !empty($m['tags']) ? str_split($m['tags']) : [];

        return new self($alternatives, $cardinality, $isLookahead, $isLookbehind, $anchorName, $tags);
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

        $anchor = $this->anchorName !== null ? '[' . $this->anchorName . ']' : '';
        $tagsStr = !empty($this->tags) ? '/' . implode('', $this->tags) : '';

        return sprintf(
            '%s%s%s%s%s%s',
            $prefix,
            $this->cardinality === Cardinality::ZeroOrOne ? '?' : '',
            $name,
            match ($this->cardinality) {
                Cardinality::OneOrMore => '+',
                Cardinality::ZeroOrMore => '*',
                default => '',
            },
            $anchor,
            $tagsStr,
        );
    }
}
