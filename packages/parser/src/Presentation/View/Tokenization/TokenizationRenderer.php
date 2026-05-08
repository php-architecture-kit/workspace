<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Tokenization;

use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenizationViewData;
use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenStreamItemViewData;
use PhpArchitecture\Parser\Presentation\View\Tokenization\DTO\TokenViewData;

final class TokenizationRenderer
{
    private const ICONS = [
        'bof'          => '🏁',
        'eof'          => '🏁',
        'begin-object' => '{',
        'end-object'   => '}',
        'begin-array'  => '[',
        'end-array'    => ']',
        'double-quote' => '"',
        'unknown'      => '·',
        'space'        => '␣',
        'newline'      => '↵',
        'true'         => '✓',
        'false'        => '✓',
        'null'         => '✓',
        'digit1-9'     => '#',
        'zero'         => '#',
    ];

    public function renderSimple(TokenizationViewData $data): string
    {
        $result = '';
        foreach ($data->items as $item) {
            if ($item->type === TokenStreamItemViewData::TYPE_TOKEN) {
                $result .= $item->token->name . "\n";
            }
        }
        return $result;
    }

    public function renderStats(TokenizationViewData $data): string
    {
        $sep  = str_repeat('=', 120) . "\n";
        $dash = str_repeat('-', 120) . "\n";

        $result  = $sep;
        $result .= "TOKENIZATION STATISTICS\n";
        $result .= $sep . "\n";
        $result .= "Total Tokens: {$data->totalTokens}\n";
        $result .= "Total Regions: {$data->totalRegions}\n\n";

        $result .= $dash;
        $result .= "TOKEN DISTRIBUTION\n";
        $result .= $dash;
        foreach ($data->tokenStats as $name => $count) {
            $pct     = $data->totalTokens > 0 ? ($count / $data->totalTokens) * 100 : 0;
            $result .= sprintf("  %-30s: %5d tokens (%5.2f%%)\n", $name, $count, $pct);
        }

        $result .= "\n" . $dash;
        $result .= "REGION DISTRIBUTION\n";
        $result .= $dash;
        foreach ($data->regionStats as $name => $count) {
            $result .= sprintf("  %-30s: %5d occurrences\n", $name, $count);
        }

        return $result;
    }

    public function renderDetailed(TokenizationViewData $data): string
    {
        $sep  = str_repeat('=', 120) . "\n";
        $dash = str_repeat('-', 120) . "\n";

        $result  = $sep;
        $result .= "TOKENIZATION OUTPUT - " . date('Y-m-d H:i:s') . "\n";
        $result .= $sep . "\n";
        $result .= "Root region: {$data->rootRegionName}\n";
        $result .= "Total tokens: {$data->totalTokens}\n";
        $result .= "Total regions: {$data->totalRegions}\n\n";

        $result .= $dash;
        $result .= "TOKEN STATISTICS\n";
        $result .= $dash;
        foreach ($data->tokenStats as $name => $count) {
            $pct     = $data->totalTokens > 0 ? ($count / $data->totalTokens) * 100 : 0;
            $result .= sprintf("  %-30s: %5d tokens (%5.2f%%)\n", $name, $count, $pct);
        }

        $result .= "\n" . $sep;
        $result .= "FULL TOKEN LIST WITH NESTED REGIONS\n";
        $result .= $sep . "\n";

        $tokenIndex = 0;
        foreach ($data->items as $item) {
            $indent = str_repeat('  ', $item->depth);

            if ($item->type === TokenStreamItemViewData::TYPE_REGION_START) {
                $tags    = $item->regionTags !== '' ? " [{$item->regionTags}]" : '';
                $result .= sprintf("\n%s╔═══ REGION START: %-20s%s ═══╗\n", $indent, $item->regionName, $tags);
            } elseif ($item->type === TokenStreamItemViewData::TYPE_REGION_END) {
                $tags    = $item->regionTags !== '' ? " [{$item->regionTags}]" : '';
                $result .= sprintf("%s╚═══ REGION END: %-20s%s ═══╝\n\n", $indent, $item->regionName, $tags);
            } else {
                $tokenIndex++;
                $result .= $this->renderTokenLine($item->token, $tokenIndex, $indent, $item->regionName);
            }
        }

        $result .= "\n" . $sep;

        return $result;
    }

    public function renderColoredRegions(TokenizationViewData $data): string
    {
        $regionNames = array_keys($data->regionStats);
        $regionNames[] = $data->rootRegionName;
        $regionNames = array_unique($regionNames);

        $palette = $this->buildPalette($regionNames);

        $stack  = [$data->rootRegionName];
        $result = '';

        foreach ($data->items as $item) {
            if ($item->type === TokenStreamItemViewData::TYPE_REGION_START) {
                $stack[] = $item->regionName;
            } elseif ($item->type === TokenStreamItemViewData::TYPE_REGION_END) {
                array_pop($stack);
            } else {
                $raw = $item->token->raw;

                if ($item->token->isUnknown) {
                    $result .= $raw !== '' ? "\e[41m\e[97m{$raw}\e[0m" : '';
                    continue;
                }

                $regionName = end($stack);
                $bg         = $palette[$regionName] ?? null;

                if ($bg === null || $raw === '') {
                    $result .= $raw;
                    continue;
                }

                foreach (explode("\n", $raw) as $i => $line) {
                    if ($i > 0) {
                        $result .= "\n";
                    }
                    if ($line !== '') {
                        $result .= "\e[48;5;{$bg}m\e[30m{$line}\e[0m";
                    }
                }
            }
        }

        $result .= "\n\n";
        $result .= $this->renderColorLegend($palette, $data);

        return $result;
    }

    /**
     * @param string[] $regionNames
     * @return array<string,int>  name → ANSI 256 bg color index
     */
    private function buildPalette(array $regionNames): array
    {
        $bgColors = [
            226, // bright yellow
            214, // orange
            118, // bright green
            159, // light cyan
            219, // pink
            183, // light purple
            123, // aqua
            208, // dark orange
            156, // lime
            195, // pale blue
            222, // light gold
            189, // lavender
        ];

        $palette = [];
        $i = 0;
        foreach ($regionNames as $name) {
            $palette[$name] = $bgColors[$i % count($bgColors)];
            $i++;
        }

        return $palette;
    }

    /**
     * @param array<string,int> $palette
     */
    private function renderColorLegend(array $palette, TokenizationViewData $data): string
    {
        $result  = "\e[1mRegion legend:\e[0m\n";
        foreach ($palette as $name => $bg) {
            $count   = $data->regionStats[$name] ?? ($name === $data->rootRegionName ? 1 : 0);
            $sample  = "\e[48;5;{$bg}m\e[30m  {$name}  \e[0m";
            $result .= "  {$sample}  {$name} ({$count}x)\n";
        }

        if ($data->hasUnknownTokens()) {
            $count   = count($data->unknownTokens);
            $sample  = "\e[41m\e[97m  unknown  \e[0m";
            $result .= "  {$sample}  unknown ({$count}x)\n";
        }

        return $result;
    }

    private function renderTokenLine(TokenViewData $token, int $index, string $indent, string $regionName): string
    {
        $raw     = strlen($token->raw) > 50 ? substr($token->raw, 0, 50) . '...' : $token->raw;
        $icon    = self::ICONS[$token->name] ?? ' ';
        $pos     = $this->formatPosition($token);
        $tags    = !empty($token->tags) ? ' [' . implode(', ', $token->tags) . ']' : '';

        return sprintf(
            "%s%s %5d. %-25s | %-50s | %s | region: %s%s\n",
            $indent,
            $icon,
            $index,
            $token->name,
            json_encode($raw),
            $pos,
            $regionName,
            $tags,
        );
    }

    private function formatPosition(TokenViewData $token): string
    {
        $pos = $token->position;
        $str = sprintf("abs: %5d-%-5d", $pos->startAbs, $pos->endAbs);

        if ($pos->hasRowCol()) {
            $str .= sprintf(" | row/col: %d:%d-%d:%d", $pos->startRow, $pos->startCol, $pos->endRow, $pos->endCol);
        }

        return $str;
    }
}
