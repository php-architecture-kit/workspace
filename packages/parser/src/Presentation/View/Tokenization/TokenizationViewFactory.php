<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Tokenization;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\Position;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenizationViewData;
use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenPositionViewData;
use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenStreamItemViewData;
use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenViewData;

final class TokenizationViewFactory
{
    public function fromTokenRegion(TokenRegion $root): TokenizationViewData
    {
        $items       = [];
        $tokenStats  = [];
        $regionStats = [];
        $unknowns    = [];

        $this->collectItems($root, 0, $items, $tokenStats, $regionStats, $unknowns);

        arsort($tokenStats);
        arsort($regionStats);

        $totalTokens  = array_sum($tokenStats);
        $totalRegions = array_sum($regionStats);

        return new TokenizationViewData(
            rootRegionName: $root->name,
            totalTokens: $totalTokens,
            totalRegions: $totalRegions,
            items: $items,
            tokenStats: $tokenStats,
            regionStats: $regionStats,
            unknownTokens: $unknowns,
        );
    }

    private function collectItems(
        TokenRegion $region,
        int $depth,
        array &$items,
        array &$tokenStats,
        array &$regionStats,
        array &$unknowns,
    ): void {
        foreach ($region->stream->tokens as $item) {
            if ($item instanceof Token) {
                $tokenView = $this->fromToken($item);
                $items[]   = TokenStreamItemViewData::token($tokenView, $depth, $region->name);

                $tokenStats[$item->name] = ($tokenStats[$item->name] ?? 0) + 1;

                if ($item->name === Token::TOKEN_UNKNOWN) {
                    $unknowns[] = $tokenView;
                }
            } elseif ($item instanceof TokenRegion) {
                $tags   = !empty($item->getAllTags()) ? implode(', ', $this->domainTags($item->getAllTags())) : '';
                $items[] = TokenStreamItemViewData::regionStart($item->name, $tags, $depth);

                $regionStats[$item->name] = ($regionStats[$item->name] ?? 0) + 1;

                $this->collectItems($item, $depth + 1, $items, $tokenStats, $regionStats, $unknowns);

                $items[] = TokenStreamItemViewData::regionEnd($item->name, $tags, $depth);
            }
        }
    }

    /**
     * @param string[] $tags
     * @return string[]
     */
    private function domainTags(array $tags): array
    {
        return array_values(array_filter(
            $tags,
            static fn(string $t) => !str_starts_with($t, 'NodeType.'),
        ));
    }

    private function fromToken(Token $token): TokenViewData
    {
        $startRow = null;
        $startCol = null;
        $endRow   = null;
        $endCol   = null;

        if ($token->hasMeta(Position::KEY_START)) {
            /** @var Position $startPos */
            $startPos = $token->getMeta(Position::KEY_START);
            /** @var Position $endPos */
            $endPos   = $token->getMeta(Position::KEY_END);
            $startRow = $startPos->row;
            $startCol = $startPos->column;
            $endRow   = $endPos->row;
            $endCol   = $endPos->column;
        }

        return new TokenViewData(
            name: $token->name,
            raw: $token->raw,
            isUnknown: $token->name === Token::TOKEN_UNKNOWN,
            tags: $this->domainTags($token->getAllTags()),
            position: new TokenPositionViewData(
                startAbs: $token->startPosition,
                endAbs: $token->endPosition,
                startRow: $startRow,
                startCol: $startCol,
                endRow: $endRow,
                endCol: $endCol,
            ),
        );
    }
}
