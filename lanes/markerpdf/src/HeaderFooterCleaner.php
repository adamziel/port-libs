<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class HeaderFooterCleaner
{
    private BenchmarkScorer $textScorer;

    public function __construct(?BenchmarkScorer $textScorer = null)
    {
        $this->textScorer = $textScorer ?? new BenchmarkScorer();
    }

    /**
     * @param list<list<string>> $pages
     * @return list<string>
     */
    public function findCommonEdgeLines(array $pages, int $maxSelectedLines = 2, float $threshold = 0.6): array
    {
        $pageCount = count($pages);
        if ($pageCount < 3) {
            return [];
        }

        $counts = [];
        foreach ($pages as $page) {
            $edgeLines = array_merge(
                array_slice($page, 0, $maxSelectedLines),
                array_slice($page, -$maxSelectedLines)
            );

            foreach ($edgeLines as $line) {
                if ($this->length($line) <= 4) {
                    continue;
                }
                $counts[$line] = ($counts[$line] ?? 0) + 1;
            }
        }

        $common = [];
        foreach ($counts as $line => $count) {
            if ($count > $pageCount * $threshold) {
                $common[] = $line;
            }
        }

        return $common;
    }

    /**
     * @param list<list<string>> $pages
     * @return list<list<string>>
     */
    public function removeCommonEdgeLines(array $pages, int $maxSelectedLines = 2, float $threshold = 0.6): array
    {
        $common = array_flip($this->findCommonEdgeLines($pages, $maxSelectedLines, $threshold));
        if ($common === []) {
            return $pages;
        }

        return array_map(
            static fn (array $page): array => array_values(array_filter(
                $page,
                static fn (string $line): bool => !isset($common[$line])
            )),
            $pages
        );
    }

    public function replaceLeadingTrailingDigits(string $text, string $replacement): string
    {
        $text = preg_replace('/^\d+/', $replacement, $text) ?? $text;

        return preg_replace('/\d+$/', $replacement, $text) ?? $text;
    }

    /**
     * @param list<array{0: string, 1: int}> $items
     * @return list<int>
     */
    public function findOverlapElements(array $items, float $stringMatchThreshold = 0.9, float $minOverlap = 0.05): array
    {
        $result = [];
        $titles = array_map(static fn (array $item): string => $item[0], $items);
        $minimumOverlap = max(3.0, count($items) * $minOverlap);

        foreach ($items as $index => [$left, $id]) {
            $overlapCount = 0;
            foreach ($titles as $titleIndex => $right) {
                if ($index === $titleIndex) {
                    continue;
                }
                if ($this->textScorer->ratio($left, $right) >= $stringMatchThreshold * 100.0) {
                    $overlapCount++;
                }
            }

            if ($overlapCount >= $minimumOverlap) {
                $result[] = $id;
            }
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public function filterCommonTitles(array $blocks): array
    {
        $titles = [];
        foreach ($blocks as $index => $block) {
            $blockType = (string) ($block['type'] ?? $block['block_type'] ?? '');
            if (!in_array($blockType, ['Title', 'Section-header'], true)) {
                continue;
            }

            $text = trim($block['text']);
            if (str_starts_with($text, '#')) {
                $text = preg_replace('/#+/', '', $text) ?? $text;
            }
            $text = trim($this->replaceLeadingTrailingDigits($text, ''));
            $titles[] = [$text, $index];
        }

        $badBlockIds = array_flip($this->findOverlapElements($titles));

        return array_values(array_filter(
            $blocks,
            static fn (array $block, int $index): bool => !isset($badBlockIds[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
