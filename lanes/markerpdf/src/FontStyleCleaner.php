<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class FontStyleCleaner
{
    /**
     * @param list<array{type?: string, block_type?: string, lines: list<array{spans: list<array<string, mixed>>}>}> $blocks
     * @return list<array{type?: string, block_type?: string, lines: list<array{spans: list<array<string, mixed>>}>}>
     */
    public function markBoldItalicSpans(array $blocks, int $boldMinWeight = 600): array
    {
        $fontWeights = [];

        foreach ($blocks as $blockIndex => $block) {
            $blockType = $block['type'] ?? $block['block_type'] ?? null;
            if (in_array($blockType, ['Title', 'Section-header'], true)) {
                continue;
            }

            foreach ($block['lines'] as $lineIndex => $line) {
                foreach ($line['spans'] as $spanIndex => $span) {
                    $font = strtolower((string) ($span['font'] ?? ''));
                    if (str_contains($font, 'bold')) {
                        $blocks[$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['bold'] = true;
                    }
                    if (str_contains($font, 'ital')) {
                        $blocks[$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['italic'] = true;
                    }

                    $weight = $this->spanWeight($span);
                    if ($weight !== null) {
                        $fontWeights[] = $weight;
                    }
                }
            }
        }

        if ($fontWeights === []) {
            return $blocks;
        }

        foreach ($blocks as $blockIndex => $block) {
            foreach ($block['lines'] as $lineIndex => $line) {
                foreach ($line['spans'] as $spanIndex => $span) {
                    $weight = $this->spanWeight($span);
                    if ($weight !== null && $weight >= $boldMinWeight) {
                        $blocks[$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['bold'] = true;
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $spans
     */
    public function mergeStyledLine(array $spans): string
    {
        $text = '';
        $count = count($spans);

        foreach ($spans as $index => $span) {
            $spanText = (string) ($span['text'] ?? '');
            if ($this->length($spanText) > 3 && $index > 0 && $index < $count - 1) {
                $nextSpan = $this->nextSubstantialSpan($spans, $index);
                if (($span['italic'] ?? false) && !($nextSpan['italic'] ?? false)) {
                    $spanText = $this->surroundText($spanText, '*');
                } elseif (($span['bold'] ?? false) && !($nextSpan['bold'] ?? false)) {
                    $spanText = $this->surroundText($spanText, '**');
                }
            }

            $text .= $spanText;
        }

        return $text;
    }

    /**
     * @param list<array{spans: list<array<string, mixed>>}> $lines
     * @return list<string>
     */
    public function mergeStyledLines(array $lines): array
    {
        return array_map(fn (array $line): string => $this->mergeStyledLine($line['spans']), $lines);
    }

    private function surroundText(string $text, string $marker): string
    {
        preg_match('/^(\s*)/', $text, $leading);
        preg_match('/(\s*)$/', $text, $trailing);

        return ($leading[1] ?? '') . $marker . trim($text) . $marker . ($trailing[1] ?? '');
    }

    /**
     * @param list<array<string, mixed>> $spans
     * @return array<string, mixed>|null
     */
    private function nextSubstantialSpan(array $spans, int $index): ?array
    {
        $nextSpan = null;
        $nextIndex = 1;
        while (count($spans) > $index + $nextIndex) {
            $nextSpan = $spans[$index + $nextIndex];
            $nextIndex++;
            if ($this->length(trim((string) ($nextSpan['text'] ?? ''))) > 2) {
                break;
            }
        }

        return $nextSpan;
    }

    /**
     * @param array<string, mixed> $span
     */
    private function spanWeight(array $span): ?float
    {
        if (isset($span['font_weight'])) {
            return (float) $span['font_weight'];
        }
        if (isset($span['fontWeight'])) {
            return (float) $span['fontWeight'];
        }

        return null;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
