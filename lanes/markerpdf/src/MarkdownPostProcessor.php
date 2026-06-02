<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class MarkdownPostProcessor
{
    /**
     * @param list<string> $lines
     */
    public function mergeLines(array $lines, string $blockType = 'Text'): string
    {
        $text = '';
        foreach ($lines as $line) {
            if ($text === '') {
                $text = $line;
                continue;
            }

            $text = $this->lineSeparator($text, $line, $blockType);
        }

        return $text;
    }

    public function surroundBlock(string $text, string $blockType, ?int $headingLevel = null): string
    {
        if ($blockType === 'Section-header') {
            if (!str_starts_with($text, '#')) {
                $prefix = $headingLevel !== null ? str_repeat('#', $headingLevel) : '##';
                $text = "\n" . $prefix . ' ' . $this->titleCase(trim($text)) . "\n";
            }
        } elseif ($blockType === 'Title') {
            if (!str_starts_with($text, '#')) {
                $text = '# ' . $this->titleCase(trim($text)) . "\n";
            }
        } elseif ($blockType === 'Table') {
            $text = "\n" . $text . "\n";
        } elseif ($blockType === 'List-item') {
            $text = $this->escapeMarkdown(rtrim($text)) . "\n";
        } elseif ($blockType === 'Code') {
            $text = "\n```\n" . $text . "\n```\n";
        } elseif ($blockType === 'Text') {
            $text = $this->escapeMarkdown($text);
        } elseif ($blockType === 'Formula') {
            $trimmed = trim($text);
            if (str_starts_with($trimmed, '$$') && str_ends_with($trimmed, '$$')) {
                $text = "\n" . $trimmed . "\n";
            }
        } elseif ($blockType === 'Caption') {
            $text = "\n" . $this->escapeMarkdown($text) . "\n";
        }

        return $text;
    }

    /**
     * Native boundary for marker.postprocessors.markdown::merge_spans.
     *
     * @param list<array<string, mixed>> $pages
     * @return list<list<array{lines: list<array{text: string, fonts: list<string>, bbox: list<float>}>, pnum: int, bbox: list<float>, block_type: string, heading_level: int|null}>>
     */
    public function mergeSpans(array $pages): array
    {
        $mergedPages = [];

        foreach ($pages as $page) {
            $pageBlocks = [];
            $pnum = isset($page['pnum']) ? (int) $page['pnum'] : 0;

            foreach (($page['blocks'] ?? []) as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $blockLines = [];
                foreach (($block['lines'] ?? []) as $line) {
                    if (!is_array($line) || !isset($line['spans']) || !is_array($line['spans']) || $line['spans'] === []) {
                        continue;
                    }

                    $spans = array_values(array_filter($line['spans'], static fn (mixed $span): bool => is_array($span)));
                    if ($spans === []) {
                        continue;
                    }

                    $lineText = '';
                    $fonts = [];
                    $spanCount = count($spans);
                    foreach ($spans as $spanIndex => $span) {
                        $fonts[] = strtolower((string) ($span['font'] ?? ''));
                        $spanText = (string) ($span['text'] ?? '');

                        if ($this->length($spanText) > 3 && $spanIndex > 0 && $spanIndex < $spanCount - 1) {
                            $nextSpan = $this->nextSubstantialSpan($spans, $spanIndex);
                            if (($span['italic'] ?? false) && !($nextSpan['italic'] ?? false)) {
                                $spanText = $this->surroundText($spanText, '*');
                            } elseif (($span['bold'] ?? false) && !($nextSpan['bold'] ?? false)) {
                                $spanText = $this->surroundText($spanText, '**');
                            }
                        }

                        $linkUri = $this->spanLinkUri($span);
                        if ($linkUri !== null && trim($spanText) !== '') {
                            $spanText = $this->markdownLink($spanText, $linkUri);
                        }

                        $lineText .= $spanText;
                    }

                    $blockLines[] = [
                        'text' => $lineText,
                        'fonts' => $fonts,
                        'bbox' => $this->lineBbox($line),
                    ];
                }

                if ($blockLines === []) {
                    continue;
                }

                $pageBlocks[] = [
                    'lines' => $blockLines,
                    'pnum' => $pnum,
                    'bbox' => $this->bbox($block['bbox'] ?? null) ?? $this->bboxFromMergedLines($blockLines),
                    'block_type' => $this->blockType($block, 'Text'),
                    'heading_level' => $this->headingLevel($block),
                ];
            }

            if ($pageBlocks === []) {
                $pageBlocks[] = [
                    'lines' => [],
                    'pnum' => $pnum,
                    'bbox' => $this->bbox($page['bbox'] ?? null) ?? [0.0, 0.0, 0.0, 0.0],
                    'block_type' => 'Text',
                    'heading_level' => null,
                ];
            }

            $mergedPages[] = $pageBlocks;
        }

        return $mergedPages;
    }

    /**
     * @param list<array<string, mixed>|list<array<string, mixed>>> $pages
     * @return list<array{text: string, block_type: string, page_start: bool, pnum: int|null}>
     */
    public function mergeBlocks(
        array $pages,
        int $maxBlockGap = 15,
        bool $paginateOutput = false,
        string $defaultBlockType = 'Text'
    ): array {
        $textBlocks = [];
        $previousType = null;
        $previousLine = null;
        $previousHeadingLevel = null;
        $blockText = '';
        $blockType = '';
        $pnum = null;

        foreach ($pages as $page) {
            $pageBlocks = $this->pageBlocks($page);
            if ($paginateOutput) {
                if ($blockText !== '') {
                    $textBlocks[] = $this->fullBlock(
                        $this->surroundBlock($blockText, $previousType ?? $defaultBlockType, $previousHeadingLevel),
                        $previousType ?? $defaultBlockType,
                        false,
                        $pnum
                    );
                    $blockText = '';
                }
                $textBlocks[] = $this->fullBlock('', 'Text', true, $this->pageNumber($page, $pageBlocks));
            }

            foreach ($pageBlocks as $block) {
                $blockType = $this->blockType($block, $defaultBlockType);
                $headingLevel = $this->headingLevel($block);
                if (
                    $blockText !== ''
                    && (
                        ($previousType !== null && $blockType !== $previousType)
                        || ($previousHeadingLevel !== null && $headingLevel !== $previousHeadingLevel)
                    )
                ) {
                    $textBlocks[] = $this->fullBlock(
                        $this->surroundBlock($blockText, $previousType ?? $defaultBlockType, $previousHeadingLevel),
                        $previousType ?? $defaultBlockType,
                        false,
                        $pnum
                    );
                    $blockText = '';
                }

                $previousType = $blockType;
                $previousHeadingLevel = $headingLevel;
                $pnum = $this->blockPageNumber($block) ?? $this->pageNumber($page, $pageBlocks);

                foreach (($block['lines'] ?? []) as $line) {
                    $lineText = $this->lineText($line);
                    $isContinuation = false;
                    if ($previousLine !== null) {
                        $lineBox = $this->lineBbox($line);
                        $previousLineBox = $this->lineBbox($previousLine);
                        $verticalDistance = min(
                            abs($lineBox[1] - $previousLineBox[3]),
                            abs($lineBox[3] - $previousLineBox[1])
                        );
                        $isContinuation = $this->lineHeight($line) === $this->lineHeight($previousLine)
                            && $lineBox[0] === $previousLineBox[0]
                            && $verticalDistance < $maxBlockGap;
                    }

                    $previousLine = $line;
                    $blockText = $blockText === ''
                        ? $lineText
                        : $this->lineSeparator($blockText, $lineText, $blockType, $isContinuation);
                }
            }
        }

        if ($blockText !== '') {
            $textBlocks[] = $this->fullBlock(
                $this->surroundBlock($blockText, $blockType !== '' ? $blockType : $defaultBlockType, $previousHeadingLevel),
                $blockType !== '' ? $blockType : $defaultBlockType,
                false,
                $pnum
            );
        }

        return array_values(array_filter(
            $textBlocks,
            static fn (array $block): bool => trim($block['text']) !== '' || $block['page_start']
        ));
    }

    /**
     * @param array{text?: string, block_type?: string} $previousBlock
     * @param array{text?: string} $block
     */
    public function blockSeparator(array $previousBlock, array $block): string
    {
        $separator = "\n";
        if (($previousBlock['block_type'] ?? null) === 'Text') {
            $separator = "\n\n";
        }

        return $separator . (string) ($block['text'] ?? '');
    }

    /**
     * @param list<array{text?: string, block_type?: string, page_start?: bool, pnum?: int|null}> $textBlocks
     */
    public function getFullText(array $textBlocks, string $pageSeparator = "\n\n"): string
    {
        $fullText = '';
        $previousBlock = null;
        foreach ($textBlocks as $block) {
            if (($block['page_start'] ?? false) === true) {
                $fullText .= "\n\n{" . (string) ($block['pnum'] ?? '') . '}' . $pageSeparator;
            } elseif ($previousBlock !== null) {
                $fullText .= $this->blockSeparator($previousBlock, $block);
            } else {
                $fullText .= (string) ($block['text'] ?? '');
            }

            $previousBlock = $block;
        }

        return $fullText;
    }

    public function escapeMarkdown(string $text): string
    {
        return preg_replace('/[#]/', '\\\\$0', $text) ?? $text;
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $page
     * @return list<array<string, mixed>>
     */
    private function pageBlocks(array $page): array
    {
        if (isset($page['blocks']) && is_array($page['blocks'])) {
            return array_values($page['blocks']);
        }

        return array_values(array_filter($page, static fn (mixed $block): bool => is_array($block)));
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array<string, mixed>> $pageBlocks
     */
    private function pageNumber(array $page, array $pageBlocks): ?int
    {
        if (isset($page['pnum'])) {
            return (int) $page['pnum'];
        }
        if (isset($pageBlocks[0])) {
            return $this->blockPageNumber($pageBlocks[0]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockPageNumber(array $block): ?int
    {
        if (isset($block['pnum'])) {
            return (int) $block['pnum'];
        }

        return null;
    }

    /**
     * @return array{text: string, block_type: string, page_start: bool, pnum: int|null}
     */
    private function fullBlock(string $text, string $blockType, bool $pageStart, ?int $pnum): array
    {
        return [
            'text' => $text,
            'block_type' => $blockType,
            'page_start' => $pageStart,
            'pnum' => $pnum,
        ];
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockType(array $block, string $defaultBlockType): string
    {
        return (string) ($block['type'] ?? $block['block_type'] ?? $defaultBlockType);
    }

    /**
     * @param array<string, mixed> $block
     */
    private function headingLevel(array $block): ?int
    {
        if (isset($block['heading_level'])) {
            return (int) $block['heading_level'];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineText(array|string $line): string
    {
        if (is_string($line)) {
            return $line;
        }
        if (isset($line['text'])) {
            return (string) $line['text'];
        }
        if (isset($line['prelim_text'])) {
            return (string) $line['prelim_text'];
        }
        if (isset($line['spans']) && is_array($line['spans'])) {
            return implode('', array_map(static fn (array $span): string => (string) ($span['text'] ?? ''), $line['spans']));
        }

        return '';
    }

    /**
     * @param array<string, mixed>|string $line
     * @return list<float>
     */
    private function lineBbox(array|string $line): array
    {
        if (is_array($line) && isset($line['bbox']) && is_array($line['bbox']) && count($line['bbox']) === 4) {
            return array_map(static fn (float|int $value): float => (float) $value, array_values($line['bbox']));
        }

        return [0.0, 0.0, 0.0, 0.0];
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineHeight(array|string $line): float
    {
        if (is_array($line) && isset($line['height'])) {
            return (float) $line['height'];
        }

        $bbox = $this->lineBbox($line);
        return $bbox[3] - $bbox[1];
    }

    private function lineSeparator(string $line1, string $line2, string $blockType, bool $isContinuation = false): string
    {
        $hyphens = '\-\x{2014}\x{00AC}';
        if (
            preg_match('~.*[\p{Lo}\p{Ll}\d][' . $hyphens . ']\s?$~us', $line1) === 1
            && preg_match('~^\s?[\p{Lo}\p{Ll}\d]~u', $line2) === 1
        ) {
            $line1 = preg_replace('~[' . $hyphens . ']\s?$~u', '', $line1) ?? $line1;
            return rtrim($line1) . ltrim($line2);
        }

        $sentenceContinuations = ',;(' . '\x{2014}' . '"' . "'" . '*';
        $lineEndPattern = '~.*[\p{Lo}\p{Ll}\d][' . $sentenceContinuations . ']?\s?$~us';
        $lineStartPattern = '~^\s?[\p{L}\d]~u';
        $sentenceEndPattern = '~.*[\x{3002}\x{0E46}\.\?!]\s?$~us';

        $textBlocks = ['Text', 'List-item', 'Footnote', 'Caption', 'Figure'];
        if (in_array($blockType, ['Title', 'Section-header'], true)) {
            return rtrim($line1) . ' ' . ltrim($line2);
        }
        if ($blockType === 'Formula') {
            return $line1 . "\n" . $line2;
        }
        if (
            preg_match($lineEndPattern, $line1) === 1
            && preg_match($lineStartPattern, $line2) === 1
            && in_array($blockType, $textBlocks, true)
        ) {
            return rtrim($line1) . ' ' . ltrim($line2);
        }
        if ($isContinuation) {
            return rtrim($line1) . ' ' . ltrim($line2);
        }
        if ($blockType !== 'Table' && in_array($blockType, $textBlocks, true) && preg_match($sentenceEndPattern, $line1) === 1) {
            return $line1 . "\n\n" . $line2;
        }
        if ($blockType === 'Table') {
            return $line1 . "\n\n" . $line2;
        }

        return $line1 . "\n" . $line2;
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

    private function surroundText(string $text, string $marker): string
    {
        preg_match('/^(\s*)/', $text, $leading);
        preg_match('/(\s*)$/', $text, $trailing);

        return ($leading[1] ?? '') . $marker . trim($text) . $marker . ($trailing[1] ?? '');
    }

    /**
     * @param array<string, mixed> $span
     */
    private function spanLinkUri(array $span): ?string
    {
        $uri = $span['link_uri'] ?? $span['url'] ?? $span['href'] ?? null;
        if (!is_string($uri) || trim($uri) === '') {
            return null;
        }

        return $uri;
    }

    private function markdownLink(string $text, string $uri): string
    {
        preg_match('/^(\s*)/', $text, $leading);
        preg_match('/(\s*)$/', $text, $trailing);
        $label = trim($text);
        $label = str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $label);
        $target = str_replace(['\\', ')'], ['\\\\', '\\)'], trim($uri));

        return ($leading[1] ?? '') . '[' . $label . '](' . $target . ')' . ($trailing[1] ?? '');
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

        $values = array_values($value);
        if (count($values) !== 4) {
            return null;
        }

        foreach ($values as $item) {
            if (!is_float($item) && !is_int($item)) {
                return null;
            }
        }

        return array_map(static fn (float|int $value): float => (float) $value, $values);
    }

    /**
     * @param list<array{bbox: list<float>}> $lines
     * @return list<float>
     */
    private function bboxFromMergedLines(array $lines): array
    {
        if ($lines === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return [
            min(array_map(static fn (array $line): float => $line['bbox'][0], $lines)),
            min(array_map(static fn (array $line): float => $line['bbox'][1], $lines)),
            max(array_map(static fn (array $line): float => $line['bbox'][2], $lines)),
            max(array_map(static fn (array $line): float => $line['bbox'][3], $lines)),
        ];
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    private function titleCase(string $text): string
    {
        return ucwords(strtolower($text), " \t\r\n\f\v-");
    }
}
