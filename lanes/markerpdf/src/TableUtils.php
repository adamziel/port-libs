<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class TableUtils
{
    /**
     * Port of marker.tables.utils::sort_table_blocks.
     *
     * @param list<array<string, mixed>|object> $blocks
     * @return list<array<string, mixed>|object>
     */
    public function sortTableBlocks(array $blocks, float $tolerance = 5.0): array
    {
        if ($tolerance <= 0.0) {
            throw new InvalidArgumentException('Table block sort tolerance must be greater than zero.');
        }

        $groups = [];
        foreach ($blocks as $block) {
            $bbox = $this->bbox($block);
            $groupKey = (string) round((($bbox[1] + $bbox[3]) / 2.0) / $tolerance, 0, PHP_ROUND_HALF_EVEN);
            $groups[$groupKey][] = $block;
        }

        ksort($groups, SORT_NUMERIC);

        $sortedBlocks = [];
        foreach ($groups as $group) {
            usort($group, function (array|object $left, array|object $right): int {
                return $this->bbox($left)[0] <=> $this->bbox($right)[0];
            });

            foreach ($group as $block) {
                $sortedBlocks[] = $block;
            }
        }

        return $sortedBlocks;
    }

    public function replaceDots(string $text): string
    {
        if (preg_match('/.*(?:\s*\.\s*){4,}.*/s', $text) !== 1) {
            return $text;
        }

        return preg_replace('/(?:\s*\.\s*){4,}/', ' ', $text) ?? $text;
    }

    public function replaceNewlines(string $text): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $text) ?? $text);
    }

    /**
     * @param array<string, mixed>|object $block
     * @return list<float>
     */
    private function bbox(array|object $block): array
    {
        $bbox = is_array($block) ? ($block['bbox'] ?? null) : ($block->bbox ?? null);
        if (!is_array($bbox) || count($bbox) !== 4) {
            throw new InvalidArgumentException('Table block must include a four-value bbox.');
        }

        return array_map(static fn (float|int $coordinate): float => (float) $coordinate, array_values($bbox));
    }
}
