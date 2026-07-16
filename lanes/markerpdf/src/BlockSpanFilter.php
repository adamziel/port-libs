<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class BlockSpanFilter
{
    /**
     * Native boundary for marker.schema.block.Block::filter_spans.
     *
     * @param array<string, mixed> $block
     * @param list<string> $badSpanIds
     * @return array<string, mixed>
     */
    public function filterSpans(array $block, array $badSpanIds): array
    {
        $badIds = array_fill_keys(array_map(static fn (string $id): string => $id, $badSpanIds), true);

        return $this->filterLineSpans(
            $block,
            static fn (array $span): bool => !isset($badIds[(string) ($span['span_id'] ?? '')]),
            false
        );
    }

    /**
     * Native boundary for marker.schema.block.Block::filter_bad_span_types.
     *
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    public function filterBadSpanTypes(array $block, ?MarkerSettings $settings = null): array
    {
        $settings ??= new MarkerSettings();
        $blockType = (string) ($block['block_type'] ?? $block['type'] ?? '');
        if (!in_array($blockType, $settings->badSpanTypes(), true)) {
            return $this->filterLineSpans($block, static fn (array $span): bool => true, false);
        }

        return $this->filterLineSpans($block, static fn (array $span): bool => false, true);
    }

    /**
     * Applies the two span filtering steps used in marker.convert::convert_single_pdf.
     *
     * @param array<string, mixed> $page
     * @param list<string> $badSpanIds
     * @return array<string, mixed>
     */
    public function filterPage(array $page, array $badSpanIds = [], ?MarkerSettings $settings = null): array
    {
        $settings ??= new MarkerSettings();
        $blocks = $page['blocks'] ?? [];
        if (!is_array($blocks)) {
            return $page;
        }

        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }

            $block = $this->filterSpans($block, $badSpanIds);
            $blocks[$index] = $this->filterBadSpanTypes($block, $settings);
        }
        $page['blocks'] = array_values($blocks);

        return $page;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param list<string> $badSpanIds
     * @return list<array<string, mixed>>
     */
    public function filterPages(array $pages, array $badSpanIds = [], ?MarkerSettings $settings = null): array
    {
        $settings ??= new MarkerSettings();
        $out = [];
        foreach ($pages as $page) {
            $out[] = $this->filterPage($page, $badSpanIds, $settings);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $block
     * @param callable(array<string, mixed>): bool $keepSpan
     * @return array<string, mixed>
     */
    private function filterLineSpans(array $block, callable $keepSpan, bool $dropLinesWithoutSpans): array
    {
        $lines = $block['lines'] ?? [];
        if (!is_array($lines)) {
            return $block;
        }

        $newLines = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                if (!$dropLinesWithoutSpans) {
                    $newLines[] = $line;
                }
                continue;
            }

            $spans = $line['spans'] ?? null;
            if (!is_array($spans)) {
                if (!$dropLinesWithoutSpans) {
                    $newLines[] = $line;
                }
                continue;
            }

            $newSpans = [];
            foreach ($spans as $span) {
                if (is_array($span) && $keepSpan($span)) {
                    $newSpans[] = $span;
                }
            }

            if ($newSpans === []) {
                continue;
            }

            $line['spans'] = array_values($newSpans);
            $newLines[] = $line;
        }

        $block['lines'] = array_values($newLines);

        return $block;
    }
}
