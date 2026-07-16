<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

use InvalidArgumentException;

final class SideBySideDiffRenderer
{
    private const DEFAULT_CONTEXT_LINES = 3;
    private const HUNK_MERGE_MAX_DISTANCE = 4;

    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
        private readonly AnsiSyntaxHighlighter $syntaxHighlighter = new AnsiSyntaxHighlighter(),
    ) {
    }

    /**
     * @param array{tabWidth?: int, columnWidth?: int, contextLines?: int, showBoth?: bool, stripCr?: bool, useColor?: bool, backgroundColor?: string, syntaxHighlight?: bool, language?: string} $options
     */
    public function renderTextDiff(string $old, string $new, array $options = []): string
    {
        $old = $this->differ->normalizeTextForDiff($old, $options);
        $new = $this->differ->normalizeTextForDiff($new, $options);
        if ($old === $new) {
            return "No syntactic changes\n";
        }

        $tabWidth = max(1, (int) ($options['tabWidth'] ?? 8));
        $columnWidth = max($tabWidth + 1, (int) ($options['columnWidth'] ?? 80));
        $contextLines = max(0, (int) ($options['contextLines'] ?? self::DEFAULT_CONTEXT_LINES));
        $showBoth = (bool) ($options['showBoth'] ?? false);
        $useColor = (bool) ($options['useColor'] ?? false);
        $backgroundColor = $this->backgroundColor($options['backgroundColor'] ?? 'dark');
        $syntaxOptions = [
            'language' => (string) ($options['language'] ?? ''),
            'backgroundColor' => $backgroundColor,
            'syntaxHighlight' => (bool) ($options['syntaxHighlight'] ?? true),
        ];
        $oldErrorSpansByLine = $this->syntaxHighlighter->treeSitterErrorSpansByLine($old, $syntaxOptions);
        $newErrorSpansByLine = $this->syntaxHighlighter->treeSitterErrorSpansByLine($new, $syntaxOptions);
        if (!$showBoth && $old === '' && $new !== '') {
            return $this->renderSingleColumnTextDiff($new, $tabWidth, $useColor, 'right', $backgroundColor);
        }
        if (!$showBoth && $new === '' && $old !== '') {
            return $this->renderSingleColumnTextDiff($old, $tabWidth, $useColor, 'left', $backgroundColor);
        }

        $oldLines = $old === '' ? [] : $this->displayLines($old);
        $newLines = $new === '' ? [] : $this->displayLines($new);
        $lineNumberWidth = max(1, strlen((string) max(count($oldLines), count($newLines))));
        $rows = [];

        foreach ($this->alignedLinePairs($oldLines, $newLines, $contextLines) as $pair) {
            [$oldLineNumber, $newLineNumber] = $pair;
            if ($oldLineNumber === null && $newLineNumber === null) {
                $rows[] = $this->formatHunkSeparator($lineNumberWidth, $columnWidth);
                continue;
            }

            $lhsHasNovel = $oldLineNumber !== null && (
                $newLineNumber === null || $oldLines[$oldLineNumber] !== $newLines[$newLineNumber]
            );
            $rhsHasNovel = $newLineNumber !== null && (
                $oldLineNumber === null || $oldLines[$oldLineNumber] !== $newLines[$newLineNumber]
            );
            $lhsSyntaxOptions = $oldLineNumber === null
                ? $syntaxOptions
                : $this->syntaxOptionsForLine($syntaxOptions, $oldErrorSpansByLine, $oldLineNumber);
            $rhsSyntaxOptions = $newLineNumber === null
                ? $syntaxOptions
                : $this->syntaxOptionsForLine($syntaxOptions, $newErrorSpansByLine, $newLineNumber);
            $lhsParts = $oldLineNumber === null
                ? [str_repeat(' ', $columnWidth)]
                : $this->splitLineForDisplayWithNovel(
                    $oldLines[$oldLineNumber],
                    $newLineNumber === null ? null : $newLines[$newLineNumber],
                    $columnWidth,
                    $tabWidth,
                    'left',
                    $useColor,
                    $backgroundColor,
                    $lhsSyntaxOptions,
                );
            $rhsParts = $newLineNumber === null
                ? ['']
                : $this->splitLineForDisplayWithNovel(
                    $newLines[$newLineNumber],
                    $oldLineNumber === null ? null : $oldLines[$oldLineNumber],
                    $columnWidth,
                    $tabWidth,
                    'right',
                    $useColor,
                    $backgroundColor,
                    $rhsSyntaxOptions,
                );
            $partCount = max(count($lhsParts), count($rhsParts));

            for ($part = 0; $part < $partCount; $part++) {
                $lhsNumber = $part === 0
                    ? $this->formatLineNumber($oldLineNumber, $lineNumberWidth)
                    : $this->formatContinuationLineNumber($lineNumberWidth);
                $rhsNumber = $part === 0
                    ? $this->formatLineNumber($newLineNumber, $lineNumberWidth)
                    : $this->formatContinuationLineNumber($lineNumberWidth);
                if ($useColor && $lhsHasNovel) {
                    $lhsNumber = $this->ansiNovel($lhsNumber, 'left', true, $backgroundColor);
                }
                if ($useColor && $rhsHasNovel) {
                    $rhsNumber = $this->ansiNovel($rhsNumber, 'right', true, $backgroundColor);
                }
                $lhsText = $lhsParts[$part] ?? str_repeat(' ', $columnWidth);
                $rhsText = $rhsParts[$part] ?? '';

                $rows[] = $lhsNumber . $lhsText . '  ' . $rhsNumber . $rhsText;
            }
        }

        return implode("\n", $rows) . "\n";
    }

    private function renderSingleColumnTextDiff(string $source, int $tabWidth, bool $useColor, string $side, string $backgroundColor): string
    {
        $lines = explode("\n", $source);
        $lineNumberWidth = max(1, strlen((string) count($lines)));
        $rows = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $this->formatLineNumber($index, $lineNumberWidth);
            $displayLine = str_replace("\t", str_repeat(' ', $tabWidth), $line);
            if ($useColor) {
                $lineNumber = $this->ansiNovel($lineNumber, $side, true, $backgroundColor);
                if ($displayLine !== '') {
                    $displayLine = $this->ansiNovel($displayLine, $side, false, $backgroundColor);
                }
            }

            $rows[] = $lineNumber . $displayLine;
        }

        return implode("\n", $rows) . "\n";
    }

    public function displayWidth(string $text, int $tabWidth = 8): int
    {
        $width = 0;
        foreach ($this->characters($text) as $character) {
            if ($character === "\t") {
                $width += $tabWidth;
                continue;
            }

            $width += $this->characterDisplayWidth($character);
        }

        return $width;
    }

    /**
     * @return list<string>
     */
    public function splitLineForDisplay(string $line, int $maxWidth, int $tabWidth = 8, string $side = 'right'): array
    {
        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Display column width must be positive.');
        }
        if ($maxWidth <= $tabWidth) {
            throw new InvalidArgumentException('Display column width must be larger than tab width.');
        }

        $parts = [];
        $offset = 0;
        $lineLength = strlen($line);
        while ($offset < $lineLength) {
            $nextOffset = $this->byteOffsetForWidth($line, $offset, $maxWidth, $tabWidth);
            if ($nextOffset <= $offset) {
                $nextOffset = $this->nextCharacterOffset($line, $offset);
            }

            $parts[] = $this->displayPart(
                substr($line, $offset, $nextOffset - $offset),
                $maxWidth,
                $tabWidth,
                $side,
            );
            $offset = $nextOffset;
        }

        if ($parts === []) {
            $parts[] = $this->displayPart('', $maxWidth, $tabWidth, $side);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function splitLineForDisplayWithNovel(
        string $line,
        ?string $oppositeLine,
        int $maxWidth,
        int $tabWidth,
        string $side,
        bool $useColor,
        string $backgroundColor,
        array $syntaxOptions,
    ): array {
        if (!$useColor) {
            return $this->splitLineForDisplay($line, $maxWidth, $tabWidth, $side);
        }

        $spans = $this->lineNovelSpans($line, $oppositeLine, $side);
        $styledSpans = $this->styledSpansForLine($line, $spans, $side, $backgroundColor, $syntaxOptions);

        $parts = [];
        $offset = 0;
        $lineLength = strlen($line);
        while ($offset < $lineLength) {
            $nextOffset = $this->byteOffsetForWidth($line, $offset, $maxWidth, $tabWidth);
            if ($nextOffset <= $offset) {
                $nextOffset = $this->nextCharacterOffset($line, $offset);
            }

            $rawPart = substr($line, $offset, $nextOffset - $offset);
            $displayPart = $this->displayPartWithStyledSpans($line, $offset, $nextOffset, $styledSpans, $tabWidth);
            $padding = max(0, $maxWidth - $this->displayWidth($rawPart, $tabWidth));
            if ($side === 'left') {
                $displayPart .= str_repeat(' ', $padding);
            }
            $parts[] = $displayPart;
            $offset = $nextOffset;
        }

        if ($parts === []) {
            $parts[] = $this->displayPart('', $maxWidth, $tabWidth, $side);
        }

        return $parts;
    }

    /**
     * @return list<array{start:int, end:int}>
     */
    private function lineNovelSpans(string $line, ?string $oppositeLine, string $side): array
    {
        if ($oppositeLine === null) {
            return $line === '' ? [] : [['start' => 0, 'end' => strlen($line)]];
        }

        $ops = $side === 'left'
            ? $this->differ->diffWords($line, $oppositeLine, ['splitNumbers' => true])
            : $this->differ->diffWords($oppositeLine, $line, ['splitNumbers' => true]);
        $targetOp = $side === 'left' ? '-' : '+';
        $targetCursor = 0;
        $spans = [];

        foreach ($ops as $op) {
            $length = strlen($op['text']);
            if ($op['op'] === '=') {
                $targetCursor += $length;
                continue;
            }

            if ($op['op'] === $targetOp) {
                if ($length > 0) {
                    $spans[] = ['start' => $targetCursor, 'end' => $targetCursor + $length];
                }
                $targetCursor += $length;
                continue;
            }

        }

        return $this->mergeNovelSpans($spans);
    }

    /**
     * @param list<array{start:int, end:int}> $spans
     * @return list<array{start:int, end:int}>
     */
    private function mergeNovelSpans(array $spans): array
    {
        usort($spans, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $merged = [];

        foreach ($spans as $span) {
            if ($span['end'] <= $span['start']) {
                continue;
            }

            $last = array_key_last($merged);
            if ($last !== null && $span['start'] <= $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $span['end']);
                continue;
            }

            $merged[] = $span;
        }

        return $merged;
    }

    /**
     * @param list<array{start:int, end:int}> $novelSpans
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style:string}>} $syntaxOptions
     * @return list<array{start:int, end:int, style:string}>
     */
    private function styledSpansForLine(string $line, array $novelSpans, string $side, string $backgroundColor, array $syntaxOptions): array
    {
        $spans = [];
        if (($syntaxOptions['syntaxHighlight'] ?? true) === true) {
            foreach ($this->syntaxHighlighter->spansForLine($line, $syntaxOptions) as $syntaxSpan) {
                foreach ($this->subtractSpans($syntaxSpan, $novelSpans) as $remainingSpan) {
                    $spans[] = $remainingSpan;
                }
            }
        }

        $novelStyle = $this->ansiNovelStyle($side, true, $backgroundColor);
        foreach ($novelSpans as $novelSpan) {
            $spans[] = [
                'start' => $novelSpan['start'],
                'end' => $novelSpan['end'],
                'style' => $novelStyle,
            ];
        }

        return $this->mergeStyledSpans($spans);
    }

    /**
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool} $syntaxOptions
     * @param array<int, list<array{start:int, end:int, style:string}>> $spansByLine
     * @return array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style:string}>}
     */
    private function syntaxOptionsForLine(array $syntaxOptions, array $spansByLine, int $lineNumber): array
    {
        if (!isset($spansByLine[$lineNumber])) {
            return $syntaxOptions;
        }

        return $syntaxOptions + ['treeSitterErrorSpans' => $spansByLine[$lineNumber]];
    }

    /**
     * @param array{start:int, end:int, style:string} $span
     * @param list<array{start:int, end:int}> $blockers
     * @return list<array{start:int, end:int, style:string}>
     */
    private function subtractSpans(array $span, array $blockers): array
    {
        $segments = [$span];
        foreach ($blockers as $blocker) {
            $next = [];
            foreach ($segments as $segment) {
                if ($blocker['end'] <= $segment['start'] || $blocker['start'] >= $segment['end']) {
                    $next[] = $segment;
                    continue;
                }

                if ($segment['start'] < $blocker['start']) {
                    $next[] = [
                        'start' => $segment['start'],
                        'end' => min($segment['end'], $blocker['start']),
                        'style' => $segment['style'],
                    ];
                }
                if ($blocker['end'] < $segment['end']) {
                    $next[] = [
                        'start' => max($segment['start'], $blocker['end']),
                        'end' => $segment['end'],
                        'style' => $segment['style'],
                    ];
                }
            }

            $segments = $next;
            if ($segments === []) {
                break;
            }
        }

        return array_values(array_filter($segments, static fn (array $segment): bool => $segment['end'] > $segment['start']));
    }

    /**
     * @param list<array{start:int, end:int, style:string}> $spans
     * @return list<array{start:int, end:int, style:string}>
     */
    private function mergeStyledSpans(array $spans): array
    {
        usort($spans, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $merged = [];
        foreach ($spans as $span) {
            $last = array_key_last($merged);
            if ($last !== null && $span['style'] === $merged[$last]['style'] && $span['start'] <= $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $span['end']);
                continue;
            }

            $merged[] = $span;
        }

        return $merged;
    }

    /**
     * @param list<array{start:int, end:int, style:string}> $spans
     */
    private function displayPartWithStyledSpans(string $line, int $start, int $end, array $spans, int $tabWidth): string
    {
        $cursor = $start;
        $display = '';

        foreach ($spans as $span) {
            $spanStart = max($start, $span['start']);
            $spanEnd = min($end, $span['end']);
            if ($spanEnd <= $spanStart) {
                continue;
            }

            if ($cursor < $spanStart) {
                $display .= $this->expandTabs(substr($line, $cursor, $spanStart - $cursor), $tabWidth);
            }
            $display .= $this->ansi(
                $this->expandTabs(substr($line, $spanStart, $spanEnd - $spanStart), $tabWidth),
                $span['style'],
            );
            $cursor = $spanEnd;
        }

        if ($cursor < $end) {
            $display .= $this->expandTabs(substr($line, $cursor, $end - $cursor), $tabWidth);
        }

        return $display;
    }

    /**
     * @return list<string>
     */
    private function displayLines(string $source): array
    {
        $lines = explode("\n", $source);
        if (count($lines) > 1 && $lines[array_key_last($lines)] === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @return list<array{0:?int, 1:?int}>
     */
    private function alignedLinePairs(array $oldLines, array $newLines, int $contextLines): array
    {
        $pairs = [];
        $pendingOld = [];
        $pendingNew = [];

        foreach ((new LineDiffer())->diff($oldLines, $newLines) as $op) {
            if ($op['op'] === '=') {
                $this->flushPendingLinePairs($pairs, $pendingOld, $pendingNew);
                $pairs[] = [$op['old'], $op['new']];
                continue;
            }

            if ($op['op'] === '-') {
                $pendingOld[] = $op['old'];
            } else {
                $pendingNew[] = $op['new'];
            }
        }

        $this->flushPendingLinePairs($pairs, $pendingOld, $pendingNew);

        return $this->linePairsWithContext($pairs, $oldLines, $newLines, $contextLines);
    }

    /**
     * @param list<array{0:?int, 1:?int}> $pairs
     * @param list<int> $pendingOld
     * @param list<int> $pendingNew
     */
    private function flushPendingLinePairs(array &$pairs, array &$pendingOld, array &$pendingNew): void
    {
        $count = max(count($pendingOld), count($pendingNew));
        for ($index = 0; $index < $count; $index++) {
            $pairs[] = [$pendingOld[$index] ?? null, $pendingNew[$index] ?? null];
        }

        $pendingOld = [];
        $pendingNew = [];
    }

    /**
     * @param list<array{0:?int, 1:?int}> $pairs
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @return list<array{0:?int, 1:?int}>
     */
    private function linePairsWithContext(array $pairs, array $oldLines, array $newLines, int $contextLines): array
    {
        $groups = [];
        $currentStart = null;
        $currentEnd = null;
        $lastChangedIndex = null;

        foreach ($pairs as $index => $pair) {
            if (!$this->linePairHasChange($pair, $oldLines, $newLines)) {
                continue;
            }

            if ($currentStart === null) {
                $currentStart = $index;
                $currentEnd = $index;
            } elseif ($lastChangedIndex !== null && $index - $lastChangedIndex <= self::HUNK_MERGE_MAX_DISTANCE) {
                $currentEnd = $index;
            } else {
                $groups[] = [$currentStart, $currentEnd];
                $currentStart = $index;
                $currentEnd = $index;
            }

            $lastChangedIndex = $index;
        }

        if ($currentStart === null || $currentEnd === null) {
            return $pairs;
        }

        $groups[] = [$currentStart, $currentEnd];

        $ranges = [];
        $lastPairIndex = count($pairs) - 1;
        foreach ($groups as [$start, $end]) {
            $rangeStart = max(0, $start - $contextLines);
            $rangeEnd = min($lastPairIndex, $end + $contextLines);
            $previous = array_key_last($ranges);
            if ($previous !== null && $rangeStart <= $ranges[$previous][1] + 1) {
                $ranges[$previous][1] = max($ranges[$previous][1], $rangeEnd);
                continue;
            }

            $ranges[] = [$rangeStart, $rangeEnd];
        }

        $visible = [];
        foreach ($ranges as $rangeIndex => [$start, $end]) {
            if ($rangeIndex > 0) {
                $visible[] = [null, null];
            }
            for ($index = $start; $index <= $end; $index++) {
                $visible[] = $pairs[$index];
            }
        }

        return $visible;
    }

    /**
     * @param array{0:?int, 1:?int} $pair
     * @param list<string> $oldLines
     * @param list<string> $newLines
     */
    private function linePairHasChange(array $pair, array $oldLines, array $newLines): bool
    {
        [$oldLineNumber, $newLineNumber] = $pair;
        if ($oldLineNumber === null || $newLineNumber === null) {
            return true;
        }

        return $oldLines[$oldLineNumber] !== $newLines[$newLineNumber];
    }

    private function formatHunkSeparator(int $lineNumberWidth, int $columnWidth): string
    {
        return str_repeat('.', $lineNumberWidth)
            . ' '
            . str_repeat(' ', $columnWidth)
            . '  '
            . str_repeat('.', $lineNumberWidth)
            . ' ...';
    }

    private function displayPart(string $part, int $maxWidth, int $tabWidth, string $side): string
    {
        $expanded = $this->expandTabs($part, $tabWidth);
        $padding = max(0, $maxWidth - $this->displayWidth($part, $tabWidth));

        return $side === 'left' ? $expanded . str_repeat(' ', $padding) : $expanded;
    }

    private function expandTabs(string $text, int $tabWidth): string
    {
        return str_replace("\t", str_repeat(' ', $tabWidth), $text);
    }

    private function ansiNovel(string $text, string $side, bool $bold = false, string $backgroundColor = 'dark'): string
    {
        if ($text === '') {
            return '';
        }

        return $this->ansi($text, $this->ansiNovelStyle($side, $bold, $backgroundColor));
    }

    private function ansiNovelStyle(string $side, bool $bold = false, string $backgroundColor = 'dark'): string
    {
        $color = match ($side) {
            'left' => $backgroundColor === 'dark' ? '91' : '31',
            default => $backgroundColor === 'dark' ? '92' : '32',
        };
        $prefix = $bold ? '1;' : '';

        return $prefix . $color;
    }

    private function ansi(string $text, string $style): string
    {
        return $text === '' ? '' : "\033[" . $style . 'm' . $text . "\033[0m";
    }

    private function backgroundColor(mixed $value): string
    {
        return $value === 'light' ? 'light' : 'dark';
    }

    private function byteOffsetForWidth(string $text, int $startOffset, int $maxWidth, int $tabWidth): int
    {
        $offset = $startOffset;
        $currentWidth = 0;
        $textLength = strlen($text);
        while ($offset < $textLength) {
            $character = $this->nextCharacterAt($text, $offset);
            $characterWidth = $character === "\t"
                ? $tabWidth
                : $this->characterDisplayWidth($character);
            if ($currentWidth + $characterWidth > $maxWidth) {
                return $offset;
            }

            $currentWidth += $characterWidth;
            $offset += strlen($character);
        }

        return $textLength;
    }

    private function formatLineNumber(?int $lineNumber, int $width): string
    {
        if ($lineNumber === null) {
            return str_repeat('.', $width) . ' ';
        }

        return str_pad((string) ($lineNumber + 1), $width, ' ', STR_PAD_LEFT) . ' ';
    }

    private function formatContinuationLineNumber(int $width): string
    {
        return str_repeat('.', $width) . ' ';
    }

    /**
     * @return list<string>
     */
    private function characters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match_all('/./us', $text, $matches) !== false && ($matches[0] ?? []) !== []) {
            return $matches[0];
        }

        return str_split($text);
    }

    private function nextCharacterAt(string $text, int $offset): string
    {
        $byte = ord($text[$offset]);
        if ($byte < 0x80) {
            return $text[$offset];
        }

        $length = match (true) {
            $byte >= 0xc2 && $byte <= 0xdf => 2,
            $byte >= 0xe0 && $byte <= 0xef => 3,
            $byte >= 0xf0 && $byte <= 0xf4 => 4,
            default => 1,
        };
        $character = substr($text, $offset, $length);

        return preg_match('/^./us', $character) === 1 ? $character : $text[$offset];
    }

    private function nextCharacterOffset(string $text, int $offset): int
    {
        return $offset + strlen($this->nextCharacterAt($text, $offset));
    }

    private function characterDisplayWidth(string $character): int
    {
        if ($character === '') {
            return 0;
        }

        $codePoint = $this->codePoint($character);
        if ($codePoint === null) {
            return 1;
        }
        if ($codePoint < 0x20 || ($codePoint >= 0x7f && $codePoint < 0xa0)) {
            return 0;
        }
        if (
            ($codePoint >= 0x300 && $codePoint <= 0x36f)
            || ($codePoint >= 0x1ab0 && $codePoint <= 0x1aff)
            || ($codePoint >= 0x1dc0 && $codePoint <= 0x1dff)
            || $codePoint === 0x200d
            || ($codePoint >= 0x20d0 && $codePoint <= 0x20ff)
            || ($codePoint >= 0xfe00 && $codePoint <= 0xfe0f)
            || ($codePoint >= 0xfe20 && $codePoint <= 0xfe2f)
        ) {
            return 0;
        }
        if (
            ($codePoint >= 0x1100 && $codePoint <= 0x115f)
            || ($codePoint >= 0x2e80 && $codePoint <= 0xa4cf)
            || ($codePoint >= 0xac00 && $codePoint <= 0xd7a3)
            || ($codePoint >= 0xf900 && $codePoint <= 0xfaff)
            || ($codePoint >= 0xfe10 && $codePoint <= 0xfe19)
            || ($codePoint >= 0xfe30 && $codePoint <= 0xfe6f)
            || ($codePoint >= 0xff00 && $codePoint <= 0xff60)
            || ($codePoint >= 0xffe0 && $codePoint <= 0xffe6)
            || ($codePoint >= 0x1f300 && $codePoint <= 0x1faff)
        ) {
            return 2;
        }

        return 1;
    }

    private function codePoint(string $character): ?int
    {
        $bytes = array_values(unpack('C*', $character) ?: []);
        $first = $bytes[0] ?? null;
        if ($first === null) {
            return null;
        }
        if ($first < 0x80) {
            return $first;
        }
        if ($first >= 0xc2 && $first <= 0xdf && isset($bytes[1])) {
            return (($first & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($first >= 0xe0 && $first <= 0xef && isset($bytes[2])) {
            return (($first & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }
        if ($first >= 0xf0 && $first <= 0xf4 && isset($bytes[3])) {
            return (($first & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        }

        return null;
    }

}
