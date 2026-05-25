<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\ParseTree;

use PhpArchitecture\Parser\Presentation\View\ParseTree\DTO\ParseNodeViewData;
use PhpArchitecture\Parser\Presentation\View\ParseTree\DTO\ParseTreeViewData;

final class ParseTreeRenderer
{
    public function renderTree(ParseTreeViewData $data, ?int $maxDepth = null): string
    {
        return $this->renderNode($data->root, $maxDepth, 0, true);
    }

    public function renderJson(ParseTreeViewData $data): string
    {
        $arr = $this->nodeToArray($data->root);
        return json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function renderSimple(ParseTreeViewData $data): string
    {
        return $data->rawContent;
    }

    /**
     * Returns unique Node names present in the tree, with occurrence count.
     * @return array<string,int>  name => count
     */
    public function buildUniqueNodeNames(ParseTreeViewData $data): array
    {
        $counts = [];
        $this->countNodeNames($data->root, $counts);
        arsort($counts);
        return $counts;
    }

    /**
     * Render source with colored attributes for every occurrence of selected node names.
     * Each individual attribute instance gets its own color from a 12-color palette
     * (color index = attribute position within the node, cycling if >12).
     *
     * @param string[] $selectedNames  Node names chosen by user
     */
    public function renderColoredSelected(
        ParseTreeViewData $data,
        array $selectedNames,
    ): string {
        $colorMap = [];
        $bgColors = [226, 214, 118, 159, 219, 183, 123, 208, 156, 195, 222, 189];

        $this->buildColorMapByName($data->root, $selectedNames, $colorMap, $bgColors);

        $result = $this->colorizeWithMap($data->root, $colorMap, null);
        $result .= "\n\n";
        $result .= $this->buildLegend($data->root, $selectedNames, $bgColors);

        return $result;
    }

    private function renderNode(ParseNodeViewData $node, ?int $maxDepth, int $depth, bool $isLast): string
    {
        if ($maxDepth !== null && $depth > $maxDepth) {
            return '';
        }

        $indent = str_repeat('  ', $depth);
        $prefix = $depth === 0 ? '' : ($isLast ? '└─ ' : '├─ ');
        $tags   = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
        $meta   = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';

        $line = match ($node->type) {
            ParseNodeViewData::TYPE_NODE => sprintf(
                "%s%sNode: %s%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_NODE_ATTR => sprintf(
                "%s%sNodeAttribute: %s%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_CHOICE_ATTR => sprintf(
                "%s%sChoiceAttribute: %s (choices: %s)%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $node->content ?? '',
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_GROUP_ATTR => sprintf(
                "%s%sGroupAttribute: %s (count: %d)%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $node->childCount ?? 0,
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_GROUPED_ATTR => sprintf(
                "%s%sGroupedAttribute: %s (count: %d)%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $node->childCount ?? 0,
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_OPTIONAL_ATTR => sprintf(
                "%s%sOptionalAttribute: %s (%s)%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $node->present ? 'present' : 'absent',
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_RAW_REGION_ATTR,
            ParseNodeViewData::TYPE_RAW_CONTENT_ATTR => sprintf(
                "%s%s%s: %s = %s%s%s\n",
                $indent,
                $prefix,
                $node->type,
                $node->name,
                json_encode(strlen($node->content ?? '') > 50 ? substr($node->content, 0, 50) . '...' : ($node->content ?? '')),
                $meta,
                $tags,
            ),
            ParseNodeViewData::TYPE_STRUCTURE_ATTR => sprintf(
                "%s%sStructureAttribute: %s (%s) = %s%s%s\n",
                $indent,
                $prefix,
                $node->name,
                $node->present ? 'present' : 'absent',
                json_encode(strlen($node->content ?? '') > 50 ? substr($node->content, 0, 50) . '...' : ($node->content ?? '')),
                $meta,
                $tags,
            ),
            default => sprintf("%s%s%s: %s%s%s\n", $indent, $prefix, $node->type, $node->name, $meta, $tags),
        };

        $result = $line;
        $count  = count($node->children);
        foreach ($node->children as $i => $child) {
            $result .= $this->renderNode($child, $maxDepth, $depth + 1, $i === $count - 1);
        }

        return $result;
    }

    private function nodeToArray(ParseNodeViewData $node): array
    {
        $data = ['type' => $node->type, 'name' => $node->name];

        if (!empty($node->meta)) {
            $data['meta'] = $node->meta;
        }
        if (!empty($node->tags)) {
            $data['tags'] = $node->tags;
        }
        if ($node->content !== null) {
            $data['content'] = $node->content;
        }
        if ($node->present !== null) {
            $data['present'] = $node->present;
        }
        if (!empty($node->children)) {
            $key = match ($node->type) {
                ParseNodeViewData::TYPE_NODE        => 'attributes',
                ParseNodeViewData::TYPE_GROUP_ATTR    => 'nodes',
                ParseNodeViewData::TYPE_GROUPED_ATTR  => 'attributes',
                default                             => 'children',
            };
            $data[$key] = array_map($this->nodeToArray(...), $node->children);
        }

        return $data;
    }

    /**
     * @param string[] $names
     * @return array<string,int>
     */
    private function buildPalette(array $names): array
    {
        $bgColors = [
            226, 214, 118, 159, 219, 183, 123, 208, 156, 195, 222, 189,
        ];

        $palette = [];
        $i = 0;
        foreach ($names as $name) {
            $palette[$name] = $bgColors[$i % count($bgColors)];
            $i++;
        }
        return $palette;
    }

    /**
     * @param string[] $selectedNames
     * @param int[]    $bgColors
     */
    private function buildLegend(ParseNodeViewData $root, array $selectedNames, array $bgColors): string
    {
        $result = '';

        foreach ($selectedNames as $name) {
            $positionNames = [];
            $this->collectAttributeNamesByPosition($root, $name, $positionNames);

            if (empty($positionNames)) {
                continue;
            }

            $result .= "\e[1mAttribute legend for [{$name}]:\e[0m\n";
            foreach ($positionNames as $pos => $attrName) {
                $bg     = $bgColors[$pos % count($bgColors)];
                $sample = "\e[48;5;{$bg}m\e[30m  {$attrName}  \e[0m";
                $result .= "  {$sample}  position {$pos}: {$attrName}\n";
            }
            $result .= "\n";
        }

        return $result;
    }

    /**
     * Collect attribute name per position index from every Node matching $nodeName.
     * Later positions overwrite earlier ones only if they differ (last-write wins, good enough).
     *
     * @param array<int,string> $positionNames
     */
    private function collectAttributeNamesByPosition(
        ParseNodeViewData $node,
        string $nodeName,
        array &$positionNames,
    ): void {
        if ($node->type === ParseNodeViewData::TYPE_NODE && $node->name === $nodeName) {
            foreach ($node->children as $i => $child) {
                $positionNames[$i] = $child->name;
            }
        }
        foreach ($node->children as $child) {
            $this->collectAttributeNamesByPosition($child, $nodeName, $positionNames);
        }
    }

    private function countNodeNames(ParseNodeViewData $node, array &$counts): void
    {
        if ($node->type === ParseNodeViewData::TYPE_NODE) {
            $counts[$node->name] = ($counts[$node->name] ?? 0) + 1;
        }
        foreach ($node->children as $child) {
            $this->countNodeNames($child, $counts);
        }
    }

    /**
     * For every Node whose name is in $selectedNames, assign a color to each
     * of its direct children (attributes) by their position index.
     *
     * @param string[]             $selectedNames
     * @param array<int,int>       $colorMap   spl_object_id => bg
     * @param int[]                $bgColors
     */
    private function buildColorMapByName(
        ParseNodeViewData $node,
        array $selectedNames,
        array &$colorMap,
        array $bgColors,
    ): void {
        if ($node->type === ParseNodeViewData::TYPE_NODE
            && in_array($node->name, $selectedNames, true)
        ) {
            foreach ($node->children as $i => $child) {
                $colorMap[spl_object_id($child)] = $bgColors[$i % count($bgColors)];
            }
        }
        foreach ($node->children as $child) {
            $this->buildColorMapByName($child, $selectedNames, $colorMap, $bgColors);
        }
    }

    /**
     * Walk tree rendering raw content, applying $activeBg when set.
     * When a node has a color in $colorMap, that color is inherited by the whole subtree.
     *
     * @param array<int,int> $colorMap  spl_object_id => bg
     * @param int|null       $activeBg  inherited background from ancestor
     */
    private function colorizeWithMap(
        ParseNodeViewData $node,
        array $colorMap,
        ?int $activeBg,
    ): string {
        $bg = $colorMap[spl_object_id($node)] ?? $activeBg;

        if (empty($node->children)) {
            $raw = $node->content ?? '';
            if ($raw === '' || $bg === null) {
                return $raw;
            }
            $result = '';
            foreach (explode("\n", $raw) as $i => $line) {
                if ($i > 0) {
                    $result .= "\n";
                }
                if ($line !== '') {
                    $result .= "\e[48;5;{$bg}m\e[30m{$line}\e[0m";
                }
            }
            return $result;
        }

        $result = '';
        foreach ($node->children as $child) {
            $result .= $this->colorizeWithMap($child, $colorMap, $bg);
        }
        return $result;
    }
}
