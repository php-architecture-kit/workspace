<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Pratt;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenStream;
use RuntimeException;

final class PrattParser
{
    private TokenStream $stream;
    private PrattGrammarDefinition $def;
    private string $groupedRegionName;
    private int $offset;

    public function parse(TokenStream $stream, PrattGrammarDefinition $def, string $groupedRegionName): TokenStream
    {
        $this->stream = $stream;
        $this->def = $def;
        $this->groupedRegionName = $groupedRegionName;
        $this->offset = 0;

        $result = new TokenStream();

        foreach ($this->consumeTrivia() as $t) {
            $result->add($t);
        }

        if (!$this->hasMore() || !$this->def->isAtom($this->stream->get($this->offset)->name)) {
            while ($this->hasMore()) {
                $result->add($this->stream->get($this->offset++));
            }
            return $result;
        }

        $result->add($this->parseExpression(0));

        while ($this->hasMore()) {
            $result->add($this->stream->get($this->offset++));
        }

        return $result;
    }

    private function parseExpression(int $minBP): Token|TokenRegion
    {
        $left = $this->consumeAtom();

        while (true) {
            $triviaBeforeOp = $this->peekTrivia();
            $opOffset = $this->offset + count($triviaBeforeOp);

            if (!$this->stream->has($opOffset)) {
                break;
            }

            $opCandidate = $this->stream->get($opOffset);
            if (!($opCandidate instanceof Token) || !$this->def->isInfix($opCandidate->name)) {
                break;
            }

            $role = $this->def->getRole($opCandidate->name);
            if ($role->bindingPower <= $minBP) {
                break;
            }

            $this->offset = $opOffset + 1;
            $triviaAfterOp = $this->consumeTrivia();

            $rightBP = $role->rightAssociative ? $role->bindingPower - 1 : $role->bindingPower;
            $right = $this->parseExpression($rightBP);

            $region = TokenRegion::new($this->groupedRegionName);
            $region->stream->add($left);
            foreach ($triviaBeforeOp as $t) {
                $region->stream->add($t);
            }
            $region->stream->add($opCandidate);
            foreach ($triviaAfterOp as $t) {
                $region->stream->add($t);
            }
            $region->stream->add($right);

            $left = $region;
        }

        return $left;
    }

    /** @return array<Token|TokenRegion> */
    private function consumeTrivia(): array
    {
        $trivia = [];
        while ($this->hasMore()) {
            $item = $this->stream->get($this->offset);
            if ($this->isAtomOrInfix($item)) {
                break;
            }
            $trivia[] = $item;
            $this->offset++;
        }
        return $trivia;
    }

    /** @return array<Token|TokenRegion> */
    private function peekTrivia(): array
    {
        $trivia = [];
        $offset = $this->offset;
        while ($this->stream->has($offset)) {
            $item = $this->stream->get($offset);
            if ($this->isAtomOrInfix($item)) {
                break;
            }
            $trivia[] = $item;
            $offset++;
        }
        return $trivia;
    }

    private function consumeAtom(): Token|TokenRegion
    {
        if (!$this->hasMore()) {
            throw new RuntimeException('Pratt parser expected an atom but the token stream is exhausted.');
        }

        $item = $this->stream->get($this->offset);

        if (!$this->def->isAtom($item->name)) {
            throw new RuntimeException(sprintf(
                'Pratt parser expected an atom at offset %d but found "%s".',
                $this->offset,
                $item->name,
            ));
        }

        $this->offset++;
        return $item;
    }

    private function hasMore(): bool
    {
        return $this->stream->has($this->offset);
    }

    private function isAtomOrInfix(Token|TokenRegion $item): bool
    {
        return $this->def->isAtom($item->name)
            || ($item instanceof Token && $this->def->isInfix($item->name));
    }
}
