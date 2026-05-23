<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

use InvalidArgumentException;

final class SideBySideDiffRenderer
{
    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
    ) {
    }

    /**
     * @param array{tabWidth?: int, columnWidth?: int, stripCr?: bool} $options
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
        $oldLines = $this->displayLines($old);
        $newLines = $this->displayLines($new);
        $lineNumberWidth = max(1, strlen((string) max(count($oldLines), count($newLines))));
        $rows = [];

        foreach ($this->alignedLinePairs($oldLines, $newLines) as $pair) {
            [$oldLineNumber, $newLineNumber] = $pair;
            $lhsParts = $oldLineNumber === null
                ? ['']
                : $this->splitLineForDisplay($oldLines[$oldLineNumber], $columnWidth, $tabWidth, 'left');
            $rhsParts = $newLineNumber === null
                ? ['']
                : $this->splitLineForDisplay($newLines[$newLineNumber], $columnWidth, $tabWidth, 'right');
            $partCount = max(count($lhsParts), count($rhsParts));

            for ($part = 0; $part < $partCount; $part++) {
                $lhsNumber = $part === 0
                    ? $this->formatLineNumber($oldLineNumber, $lineNumberWidth)
                    : $this->formatContinuationLineNumber($lineNumberWidth);
                $rhsNumber = $part === 0
                    ? $this->formatLineNumber($newLineNumber, $lineNumberWidth)
                    : $this->formatContinuationLineNumber($lineNumberWidth);
                $lhsText = $lhsParts[$part] ?? str_repeat(' ', $columnWidth);
                $rhsText = $rhsParts[$part] ?? '';

                $rows[] = $lhsNumber . $lhsText . '  ' . $rhsNumber . $rhsText;
            }
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
    private function alignedLinePairs(array $oldLines, array $newLines): array
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

        return $pairs;
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

    private function displayPart(string $part, int $maxWidth, int $tabWidth, string $side): string
    {
        $expanded = str_replace("\t", str_repeat(' ', $tabWidth), $part);
        $padding = max(0, $maxWidth - $this->displayWidth($part, $tabWidth));

        return $side === 'left' ? $expanded . str_repeat(' ', $padding) : $expanded;
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
