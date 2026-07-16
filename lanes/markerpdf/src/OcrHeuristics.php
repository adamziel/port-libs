<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class OcrHeuristics
{
    private const INVALID_CHARS = ["\u{FFFD}"];

    private LayoutOrderer $layout;

    public function __construct(?LayoutOrderer $layout = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
    }

    /**
     * @param array<string, mixed> $page
     */
    public function shouldOcrPage(array $page, bool $noText, bool $ocrAllPages = false): bool
    {
        [$detectedLinesFound, $totalLines] = $this->detectedLineCoverage($page);

        if ($totalLines === 0) {
            return false;
        }

        $prelimText = $this->pagePrelimText($page);

        return $noText
            || ($prelimText !== '' && $this->detectBadOcr($prelimText))
            || !$detectedLinesFound
            || $ocrAllPages;
    }

    public function detectBadOcr(
        string $text,
        float $spaceThreshold = 0.7,
        float $newlineThreshold = 0.6,
        float $alphanumThreshold = 0.3
    ): bool {
        if ($this->length($text) === 0) {
            return true;
        }

        $spaces = preg_match_all('/\s+/u', $text) ?: 0;
        $alphaChars = $this->length(preg_replace('/\s+/u', '', $text) ?? $text);
        if ($spaces / ($alphaChars + $spaces) > $spaceThreshold) {
            return true;
        }

        $newlines = preg_match_all('/\n+/u', $text) ?: 0;
        $nonNewlines = $this->length(preg_replace('/\n+/u', '', $text) ?? $text);
        if ($newlines / ($newlines + $nonNewlines) > $newlineThreshold) {
            return true;
        }

        if ($this->alphanumRatio($text) < $alphanumThreshold) {
            return true;
        }

        $invalidChars = 0;
        foreach ($this->characters($text) as $char) {
            if (in_array($char, self::INVALID_CHARS, true)) {
                $invalidChars++;
            }
        }

        return $invalidChars > max(6.0, $this->length($text) * 0.03);
    }

    public function alphanumRatio(string $text): float
    {
        $text = str_replace([" ", "\n"], '', $text);
        if ($this->length($text) === 0) {
            return 1.0;
        }

        $alphanumeric = preg_match_all('/[\p{L}\p{N}]/u', $text) ?: 0;

        return $alphanumeric / $this->length($text);
    }

    /**
     * @param list<array<string, mixed>> $pages
     */
    public function noTextFound(array $pages): bool
    {
        $fullText = '';
        foreach ($pages as $page) {
            $fullText .= $this->pagePrelimText($page);
        }

        return trim($fullText) === '';
    }

    /**
     * @param array<string, mixed> $page
     * @return array{0: bool, 1: int}
     */
    public function detectedLineCoverage(
        array $page,
        float $intersectThreshold = 0.5,
        float $detectionThreshold = 0.4
    ): array {
        $detectedLines = $this->detectedLineBoxes($page);
        $totalLines = count($detectedLines);
        if ($totalLines === 0) {
            return [true, 0];
        }

        $imageBbox = $this->bbox($page['text_lines']['image_bbox'] ?? $page['textLines']['image_bbox'] ?? $page['bbox'] ?? null);
        $pageBbox = $this->bbox($page['bbox'] ?? null);
        $lineBoxes = $this->pageLineBoxes($page);
        $foundLines = 0;

        foreach ($detectedLines as $detectedLine) {
            $detectedBbox = $imageBbox !== null && $pageBbox !== null
                ? $this->layout->rescaleBbox($imageBbox, $pageBbox, $detectedLine)
                : $detectedLine;

            $totalIntersection = 0.0;
            foreach ($lineBoxes as $lineBox) {
                $totalIntersection += $this->layout->intersectionPct($detectedBbox, $lineBox);
            }

            if ($totalIntersection > $intersectThreshold) {
                $foundLines++;
            }
        }

        return [($foundLines / $totalLines) > $detectionThreshold, $totalLines];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function detectedLineBoxes(array $page): array
    {
        $textLines = $page['text_lines'] ?? $page['textLines'] ?? [];
        if (!is_array($textLines) || !isset($textLines['bboxes']) || !is_array($textLines['bboxes'])) {
            return [];
        }

        $boxes = [];
        foreach ($textLines['bboxes'] as $line) {
            $bbox = $this->bbox($line);
            if ($bbox !== null) {
                $boxes[] = $bbox;
            }
        }

        return $boxes;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function pageLineBoxes(array $page): array
    {
        $lineBoxes = [];
        $blocks = $page['blocks'] ?? [];
        if (!is_array($blocks)) {
            return [];
        }

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $lines = $block['lines'] ?? [];
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $bbox = $this->bbox($line);
                if ($bbox !== null) {
                    $lineBoxes[] = $bbox;
                }
            }
        }

        return $lineBoxes;
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['bbox'])) {
            return $this->bbox($value['bbox']);
        }

        $values = array_values($value);
        if (count($values) !== 4) {
            return null;
        }
        foreach ($values as $item) {
            if (!is_int($item) && !is_float($item)) {
                return null;
            }
        }

        return array_map(static fn (float|int $item): float => (float) $item, $values);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pagePrelimText(array $page): string
    {
        if (isset($page['prelim_text'])) {
            return (string) $page['prelim_text'];
        }
        if (isset($page['prelimText'])) {
            return (string) $page['prelimText'];
        }

        $parts = [];
        $blocks = $page['blocks'] ?? [];
        if (!is_array($blocks)) {
            return '';
        }

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (isset($block['prelim_text'])) {
                $parts[] = (string) $block['prelim_text'];
                continue;
            }
            if (isset($block['text'])) {
                $parts[] = (string) $block['text'];
                continue;
            }

            $lines = $block['lines'] ?? [];
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                if (!is_array($line)) {
                    $parts[] = (string) $line;
                    continue;
                }
                if (isset($line['prelim_text'])) {
                    $parts[] = (string) $line['prelim_text'];
                } elseif (isset($line['text'])) {
                    $parts[] = (string) $line['text'];
                } elseif (isset($line['spans']) && is_array($line['spans'])) {
                    $spanText = '';
                    foreach ($line['spans'] as $span) {
                        if (is_array($span)) {
                            $spanText .= (string) ($span['text'] ?? '');
                        }
                    }
                    $parts[] = $spanText;
                }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function characters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (function_exists('mb_str_split')) {
            return mb_str_split($text, 1, 'UTF-8');
        }

        return str_split($text);
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
