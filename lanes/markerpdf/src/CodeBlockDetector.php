<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class CodeBlockDetector
{
    private const COMMENT_PATTERN = "~^\\s*(//|#|'|--|/\\*|'''|\"\"\"|--\\[\\[|<!--|%|%\\{|\\(\\*)~";

    /**
     * @param list<array<string, mixed>|string> $lines
     */
    public function isCodeLineLength(array $lines, int $threshold = 80): bool
    {
        $totalAlnumChars = 0;
        foreach ($lines as $line) {
            $totalAlnumChars += preg_match_all('/[\p{L}\p{N}_]/u', $this->lineText($line)) ?: 0;
        }

        if ($totalAlnumChars === 0) {
            return false;
        }

        $totalNewlines = max(count($lines) - 1, 1);

        return ($totalAlnumChars / $totalNewlines) < $threshold;
    }

    /**
     * @param list<array<string, mixed>|string> $lines
     */
    public function commentCount(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            if (preg_match(self::COMMENT_PATTERN, $this->lineText($line)) === 1) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>|string> $lines
     */
    public function isCodeBlock(
        array $lines,
        int $lineLengthThreshold = 80,
        ?float $averageFontSize = null,
        ?float $averageLineHeight = null
    ): bool {
        if (count($lines) <= 3 || !$this->isCodeLineLength($lines, $lineLengthThreshold)) {
            return false;
        }

        $lefts = array_map(fn (array|string $line): float => $this->lineLeft($line), $lines);
        $minStart = min($lefts);
        $indentedLines = 0;
        foreach ($lefts as $left) {
            if ($left > $minStart) {
                $indentedLines++;
            }
        }

        if ($indentedLines + $this->commentCount($lines) <= count($lines) * 0.7) {
            return false;
        }

        if ($averageFontSize !== null) {
            $fontSizes = array_values(array_filter(
                array_map(fn (array|string $line): ?float => $this->lineFontSize($line), $lines),
                static fn (?float $value): bool => $value !== null
            ));
            if ($fontSizes === [] || $this->mean($fontSizes) > $averageFontSize * 0.8) {
                return false;
            }
        }

        if ($averageLineHeight !== null) {
            $lineHeights = array_values(array_filter(
                array_map(fn (array|string $line): ?float => $this->lineHeight($line), $lines),
                static fn (?float $value): bool => $value !== null
            ));
            if ($lineHeights === [] || $this->mean($lineHeights) >= $averageLineHeight * 0.8) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{type?: string, lines: list<array<string, mixed>|string>}> $blocks
     * @return list<array{type: string, lines: list<array<string, mixed>|string>}>
     */
    public function identifyCodeBlocks(array $blocks, ?float $averageFontSize = null, ?float $averageLineHeight = null): array
    {
        $classified = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'Text';
            if ($type === 'Text' && $this->isCodeBlock($block['lines'], 80, $averageFontSize, $averageLineHeight)) {
                $type = 'Code';
            }

            $classified[] = [
                'type' => $type,
                'lines' => $block['lines'],
            ];
        }

        return $classified;
    }

    /**
     * @param list<array<string, mixed>|string> $lines
     */
    public function indentBlock(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        $minLeft = min(array_map(fn (array|string $line): float => $this->lineLeft($line), $lines));
        $columnWidth = $this->columnWidth($lines);
        $text = '';
        $blankLine = false;

        foreach ($lines as $line) {
            $lineText = $this->lineText($line);
            $prefix = '';
            if ($columnWidth > 0.0) {
                $prefix = str_repeat(' ', (int) (($this->lineLeft($line) - $minLeft) / $columnWidth));
            }

            $currentLineBlank = trim($lineText) === '';
            if ($blankLine && $currentLineBlank) {
                continue;
            }

            $text .= $prefix . $lineText . "\n";
            $blankLine = $currentLineBlank;
        }

        return $text;
    }

    /**
     * @param list<array<string, mixed>|string> $lines
     */
    private function columnWidth(array $lines): float
    {
        foreach ($lines as $line) {
            $text = $this->lineText($line);
            $length = $this->length($text);
            $left = $this->lineLeft($line);
            $right = $this->lineRight($line);

            if ($length > 0 && $right !== null && $right > $left) {
                return ($right - $left) / $length;
            }
        }

        return 0.0;
    }

    /**
     * @param list<float> $values
     */
    private function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineText(array|string $line): string
    {
        if (is_string($line)) {
            return $line;
        }

        return (string) ($line['text'] ?? $line['prelim_text'] ?? '');
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineLeft(array|string $line): float
    {
        if (!is_array($line)) {
            return 0.0;
        }
        if (isset($line['left'])) {
            return (float) $line['left'];
        }
        if (isset($line['bbox']) && is_array($line['bbox']) && isset($line['bbox'][0])) {
            return (float) $line['bbox'][0];
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineRight(array|string $line): ?float
    {
        if (!is_array($line)) {
            return null;
        }
        if (isset($line['right'])) {
            return (float) $line['right'];
        }
        if (isset($line['bbox']) && is_array($line['bbox']) && isset($line['bbox'][2])) {
            return (float) $line['bbox'][2];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineFontSize(array|string $line): ?float
    {
        if (!is_array($line)) {
            return null;
        }
        if (isset($line['fontSize'])) {
            return (float) $line['fontSize'];
        }
        if (isset($line['font_size'])) {
            return (float) $line['font_size'];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineHeight(array|string $line): ?float
    {
        if (!is_array($line)) {
            return null;
        }
        if (isset($line['height'])) {
            return (float) $line['height'];
        }
        if (isset($line['bbox']) && is_array($line['bbox']) && isset($line['bbox'][1], $line['bbox'][3])) {
            return (float) $line['bbox'][3] - (float) $line['bbox'][1];
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
