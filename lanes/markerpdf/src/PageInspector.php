<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PageInspector
{
    /**
     * Native boundary for marker.schema.page.Page::get_all_lines.
     *
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    public function getAllLines(array $page): array
    {
        $lines = [];
        foreach (($page['blocks'] ?? []) as $block) {
            if (!is_array($block) || !isset($block['lines']) || !is_array($block['lines'])) {
                continue;
            }

            foreach ($block['lines'] as $line) {
                if (is_array($line)) {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * Native boundary for marker.schema.page.Page::get_nonblank_lines.
     *
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    public function getNonblankLines(array $page): array
    {
        return array_values(array_filter(
            $this->getAllLines($page),
            fn (array $line): bool => trim($this->linePrelimText($line)) !== ''
        ));
    }

    /**
     * Native boundary for marker.schema.page.Page::get_nonblank_spans.
     *
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    public function getNonblankSpans(array $page): array
    {
        $spans = [];
        foreach ($this->getAllLines($page) as $line) {
            if (!isset($line['spans']) || !is_array($line['spans'])) {
                continue;
            }

            foreach ($line['spans'] as $span) {
                if (is_array($span) && trim((string) ($span['text'] ?? '')) !== '') {
                    $spans[] = $span;
                }
            }
        }

        return $spans;
    }

    /**
     * Native boundary for marker.schema.page.Page::get_font_sizes.
     *
     * @param array<string, mixed> $page
     * @return list<float>
     */
    public function getFontSizes(array $page): array
    {
        $sizes = [];
        foreach ($this->getNonblankSpans($page) as $span) {
            $size = $span['font_size'] ?? $span['fontSize'] ?? null;
            if (is_int($size) || is_float($size)) {
                $sizes[] = (float) $size;
            }
        }

        return $sizes;
    }

    /**
     * Native boundary for marker.schema.page.Page::get_line_heights.
     *
     * @param array<string, mixed> $page
     * @return list<float>
     */
    public function getLineHeights(array $page): array
    {
        $heights = [];
        foreach ($this->getNonblankLines($page) as $line) {
            $bbox = $this->bbox($line['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $heights[] = $bbox[3] - $bbox[1];
        }

        return $heights;
    }

    /**
     * Native boundary for marker.schema.page.Page::prelim_text.
     *
     * @param array<string, mixed> $page
     */
    public function prelimText(array $page): string
    {
        $parts = [];
        foreach (($page['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                $parts[] = $this->blockPrelimText($block);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockPrelimText(array $block): string
    {
        if (!isset($block['lines']) || !is_array($block['lines'])) {
            return (string) ($block['prelim_text'] ?? $block['prelimText'] ?? '');
        }

        $parts = [];
        foreach ($block['lines'] as $line) {
            if (is_array($line)) {
                $parts[] = $this->linePrelimText($line);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $line
     */
    private function linePrelimText(array $line): string
    {
        if (isset($line['prelim_text']) || isset($line['prelimText'])) {
            return (string) ($line['prelim_text'] ?? $line['prelimText']);
        }

        $text = '';
        foreach (($line['spans'] ?? []) as $span) {
            if (is_array($span)) {
                $text .= (string) ($span['text'] ?? '');
            }
        }

        return $text;
    }

    /**
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                return null;
            }
            $bbox[] = (float) $part;
        }

        return $bbox;
    }
}
