<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class InlineDiffRenderer
{
    private const DEFAULT_CONTEXT_LINES = 3;
    private const HUNK_MERGE_MAX_DISTANCE = 4;

    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
        private readonly LineDiffer $lineDiffer = new LineDiffer(),
        private readonly AnsiSyntaxHighlighter $syntaxHighlighter = new AnsiSyntaxHighlighter(),
    ) {
    }

    /**
     * @param array{path?: string, language?: string, displayLanguage?: string, extraInfo?: string, tabWidth?: int, contextLines?: int, stripCr?: bool, useColor?: bool, backgroundColor?: string, syntaxHighlight?: bool} $options
     */
    public function renderTextDiff(string $old, string $new, array $options = []): string
    {
        $old = $this->differ->normalizeTextForDiff($old, $options);
        $new = $this->differ->normalizeTextForDiff($new, $options);
        if ($old === $new) {
            return "No syntactic changes\n";
        }

        $oldLines = $this->splitOnNewlines($old);
        $newLines = $this->splitOnNewlines($new);
        $pairs = $this->alignedLinePairs($oldLines, $newLines);
        $hunks = $this->hunkRanges($pairs, $oldLines, $newLines);
        if ($hunks === []) {
            return "No syntactic changes\n";
        }

        $tabWidth = max(1, (int) ($options['tabWidth'] ?? 8));
        $contextLines = max(0, (int) ($options['contextLines'] ?? self::DEFAULT_CONTEXT_LINES));
        $path = (string) ($options['path'] ?? '(stdin)');
        $language = (string) ($options['displayLanguage'] ?? $this->displayLanguageName($options['language'] ?? 'text'));
        $extraInfo = isset($options['extraInfo']) ? (string) $options['extraInfo'] : null;
        $useColor = (bool) ($options['useColor'] ?? false);
        $backgroundColor = $this->backgroundColor($options['backgroundColor'] ?? 'dark');
        $syntaxOptions = [
            'language' => (string) ($options['language'] ?? ''),
            'backgroundColor' => $backgroundColor,
            'syntaxHighlight' => (bool) ($options['syntaxHighlight'] ?? true),
        ];
        $oldErrorSpansByLine = $this->syntaxHighlighter->treeSitterErrorSpansByLine($old, $syntaxOptions);
        $newErrorSpansByLine = $this->syntaxHighlighter->treeSitterErrorSpansByLine($new, $syntaxOptions);
        $output = '';
        $hunkTotal = count($hunks);

        foreach ($hunks as $index => [$start, $end]) {
            $hunkNumber = $index + 1;
            $output .= $this->formatHeader($path, $language, $hunkNumber, $hunkTotal, $extraInfo, $useColor, $backgroundColor) . "\n";

            $beforeStart = max(0, $start - $contextLines);
            for ($pairIndex = $beforeStart; $pairIndex < $start; $pairIndex++) {
                $oldLineNumber = $pairs[$pairIndex][0];
                if ($oldLineNumber !== null) {
                    $output .= $this->formatInlineLine('left', $oldLineNumber, $oldLines[$oldLineNumber], false, $tabWidth, $useColor, $backgroundColor, $this->syntaxOptionsForLine($syntaxOptions, $oldErrorSpansByLine, $oldLineNumber));
                }
            }

            for ($pairIndex = $start; $pairIndex <= $end; $pairIndex++) {
                $oldLineNumber = $pairs[$pairIndex][0];
                if ($oldLineNumber !== null) {
                    $output .= $this->formatInlineLine('left', $oldLineNumber, $oldLines[$oldLineNumber], true, $tabWidth, $useColor, $backgroundColor, $this->syntaxOptionsForLine($syntaxOptions, $oldErrorSpansByLine, $oldLineNumber));
                }
            }

            for ($pairIndex = $start; $pairIndex <= $end; $pairIndex++) {
                $newLineNumber = $pairs[$pairIndex][1];
                if ($newLineNumber !== null) {
                    $output .= $this->formatInlineLine('right', $newLineNumber, $newLines[$newLineNumber], true, $tabWidth, $useColor, $backgroundColor, $this->syntaxOptionsForLine($syntaxOptions, $newErrorSpansByLine, $newLineNumber));
                }
            }

            $afterEnd = min(count($pairs) - 1, $end + $contextLines);
            for ($pairIndex = $end + 1; $pairIndex <= $afterEnd; $pairIndex++) {
                $newLineNumber = $pairs[$pairIndex][1];
                if ($newLineNumber !== null) {
                    $output .= $this->formatInlineLine('right', $newLineNumber, $newLines[$newLineNumber], false, $tabWidth, $useColor, $backgroundColor, $this->syntaxOptionsForLine($syntaxOptions, $newErrorSpansByLine, $newLineNumber));
                }
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * @param list<string> $gitArguments
     * @param array{path?: string, language?: string, displayLanguage?: string, extraInfo?: string, tabWidth?: int, contextLines?: int, stripCr?: bool, useColor?: bool, backgroundColor?: string, syntaxHighlight?: bool} $options
     */
    public function renderGitExternalTextDiff(string $old, string $new, array $gitArguments, array $options = []): string
    {
        $metadata = GitExternalDiffMetadata::fromArguments($gitArguments);

        return $this->renderTextDiff($old, $new, $metadata->applyToOptions($options));
    }

    /**
     * @param list<string> $pathArguments
     * @param array{path?: string, language?: string, displayLanguage?: string, extraInfo?: string, tabWidth?: int, contextLines?: int, stripCr?: bool, useColor?: bool, backgroundColor?: string, syntaxHighlight?: bool} $options
     */
    public function renderPathArgumentsTextDiff(
        string $old,
        string $new,
        array $pathArguments,
        array $options = [],
        ?string $temporaryDirectory = null,
    ): string {
        $metadata = GitExternalDiffMetadata::fromPathArguments($pathArguments, $temporaryDirectory);

        return $this->renderTextDiff($old, $new, $metadata->applyToOptions($options));
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string|null>|null $environment
     */
    public function renderUnmergedPathStatus(array $arguments, ?array $environment = null): ?string
    {
        return GitExternalDiffMetadata::unmergedPathMessage($arguments, $environment);
    }

    /**
     * @param array{path?: string, extraInfo?: string, useColor?: bool, backgroundColor?: string} $options
     */
    public function renderBinaryDiff(string $oldBytes, string $newBytes, array $options = []): string
    {
        $path = (string) ($options['path'] ?? '(stdin)');
        $extraInfo = isset($options['extraInfo']) ? (string) $options['extraInfo'] : null;
        $useColor = (bool) ($options['useColor'] ?? false);
        $output = $this->formatHeader($path, 'Binary', 1, 1, $extraInfo, $useColor, $this->backgroundColor($options['backgroundColor'] ?? 'dark')) . "\n";

        if ($oldBytes === $newBytes) {
            return $output . "No changes.\n\n";
        }

        $oldSize = strlen($oldBytes);
        $newSize = strlen($newBytes);
        if ($oldSize === 0) {
            return $output . 'Binary file added (' . $this->formatBinarySize($newSize) . ").\n\n";
        }
        if ($newSize === 0) {
            return $output . 'Binary file removed (' . $this->formatBinarySize($oldSize) . ").\n\n";
        }

        return $output
            . 'Binary file modified (old: '
            . $this->formatBinarySize($oldSize)
            . ', new: '
            . $this->formatBinarySize($newSize)
            . ").\n\n";
    }

    /**
     * @param list<string> $gitArguments
     * @param array{path?: string, extraInfo?: string, useColor?: bool, backgroundColor?: string} $options
     */
    public function renderGitExternalBinaryDiff(string $oldBytes, string $newBytes, array $gitArguments, array $options = []): string
    {
        $metadata = GitExternalDiffMetadata::fromArguments($gitArguments);

        return $this->renderBinaryDiff($oldBytes, $newBytes, $metadata->applyToOptions($options));
    }

    public function formatHeader(
        string $path,
        string $language,
        int $hunkNumber = 1,
        int $hunkTotal = 1,
        ?string $extraInfo = null,
        bool $useColor = false,
        string $backgroundColor = 'dark',
    ): string {
        $divider = $hunkTotal === 1 ? '' : $hunkNumber . '/' . $hunkTotal . ' --- ';
        $displayPath = $useColor
            ? $this->ansiHeaderPath($path, $hunkNumber, $backgroundColor)
            : $path;
        $trailer = ' --- ' . $divider . $language;
        if ($useColor) {
            $trailer = $this->ansiDim($trailer);
        }

        $header = $displayPath . $trailer;
        if ($extraInfo !== null && $extraInfo !== '' && $hunkNumber === 1) {
            $header .= "\n" . ($useColor ? $this->ansiDim($extraInfo) : $extraInfo);
        }

        return $header;
    }

    private function formatBinarySize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $value = (float) $bytes;
        foreach (['KiB', 'MiB', 'GiB', 'TiB'] as $unit) {
            $value /= 1024;
            if ($value < 1024 || $unit === 'TiB') {
                return number_format($value, 1, '.', '') . ' ' . $unit;
            }
        }

        return $bytes . ' B';
    }

    /**
     * @return list<string>
     */
    private function splitOnNewlines(string $source): array
    {
        return array_map(
            static fn (string $line): string => str_ends_with($line, "\r") ? substr($line, 0, -1) : $line,
            explode("\n", $source),
        );
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

        foreach ($this->lineDiffer->diff($oldLines, $newLines) as $op) {
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

    /**
     * @param list<array{0:?int, 1:?int}> $pairs
     * @param list<string> $oldLines
     * @param list<string> $newLines
     * @return list<array{0:int, 1:int}>
     */
    private function hunkRanges(array $pairs, array $oldLines, array $newLines): array
    {
        $ranges = [];
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
                $ranges[] = [$currentStart, $currentEnd ?? $currentStart];
                $currentStart = $index;
                $currentEnd = $index;
            }

            $lastChangedIndex = $index;
        }

        if ($currentStart !== null) {
            $ranges[] = [$currentStart, $currentEnd ?? $currentStart];
        }

        return $this->mergeOverlappingContextRanges($ranges, count($pairs));
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

    /**
     * @param list<array{0:int, 1:int}> $ranges
     * @return list<array{0:int, 1:int}>
     */
    private function mergeOverlappingContextRanges(array $ranges, int $pairCount): array
    {
        if ($ranges === []) {
            return [];
        }

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $previous = array_key_last($merged);
            if ($previous !== null && $start <= $merged[$previous][1] + 1) {
                $merged[$previous][1] = min($pairCount - 1, max($merged[$previous][1], $end));
                continue;
            }

            $merged[] = [$start, min($pairCount - 1, $end)];
        }

        return $merged;
    }

    /**
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style:string}>} $syntaxOptions
     */
    private function formatInlineLine(string $side, int $lineNumber, string $line, bool $isNovel, int $tabWidth, bool $useColor, string $backgroundColor, array $syntaxOptions): string
    {
        $lineNumberText = (string) ($lineNumber + 1) . ' ';
        if ($useColor) {
            $lineNumberText = $isNovel
                ? $this->ansiNovelLineNumber($lineNumberText, $side, $backgroundColor)
                : $this->ansiDim($lineNumberText);
        }

        $line = $useColor && ($syntaxOptions['syntaxHighlight'] ?? true) === true
            ? $this->syntaxHighlighter->highlightLine($line, $tabWidth, $syntaxOptions)
            : str_replace("\t", str_repeat(' ', $tabWidth), $line);

        if ($side === 'left') {
            return $lineNumberText . '   ' . $line . "\n";
        }

        return '   ' . $lineNumberText . $line . "\n";
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

    private function displayLanguageName(string $language): string
    {
        return match (strtolower($language)) {
            'c++', 'cpp' => 'C++',
            'c#', 'csharp' => 'C#',
            'css' => 'CSS',
            'html' => 'HTML',
            'javascript', 'js' => 'JavaScript',
            'json' => 'JSON',
            'php', 'hack' => 'PHP',
            'python', 'py' => 'Python',
            'rust', 'rs' => 'Rust',
            'scss' => 'SCSS',
            'text', 'plain', 'plain-text', 'plaintext' => 'Text',
            'typescript', 'ts' => 'TypeScript',
            'tsx' => 'TSX',
            'xml' => 'XML',
            'yaml', 'yml' => 'YAML',
            default => $language === '' ? 'Text' : ucfirst($language),
        };
    }

    private function ansiHeaderPath(string $text, int $hunkNumber, string $backgroundColor): string
    {
        $style = $hunkNumber === 1
            ? ($backgroundColor === 'dark' ? '1;93' : '1;33')
            : '1';

        return "\033[" . $style . 'm' . $text . "\033[0m";
    }

    private function ansiNovelLineNumber(string $text, string $side, string $backgroundColor): string
    {
        $color = match ($side) {
            'left' => $backgroundColor === 'dark' ? '91' : '31',
            default => $backgroundColor === 'dark' ? '92' : '32',
        };

        return "\033[1;" . $color . 'm' . $text . "\033[0m";
    }

    private function backgroundColor(mixed $value): string
    {
        return $value === 'light' ? 'light' : 'dark';
    }

    private function ansiDim(string $text): string
    {
        return $text === '' ? '' : "\033[2m" . $text . "\033[0m";
    }
}
