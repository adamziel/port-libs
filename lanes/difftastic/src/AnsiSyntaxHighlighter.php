<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class AnsiSyntaxHighlighter
{
    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
        private readonly SyntaxHighlightClassifier $highlightClassifier = new SyntaxHighlightClassifier(),
    ) {
    }

    /**
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style?:string}>, source?: string, lineStartOffset?: int} $options
     * @return list<array{start:int, end:int, style:string}>
     */
    public function spansForLine(string $line, array $options = []): array
    {
        if (($options['syntaxHighlight'] ?? true) === false || trim($line) === '') {
            return [];
        }

        $background = ($options['backgroundColor'] ?? 'dark') === 'light' ? 'light' : 'dark';
        $errorSpans = $this->treeSitterErrorSpansForLine($options);
        $spans = [];

        foreach ($this->differ->tokenize($line, $options) as $token) {
            if ($this->overlapsStyledSpan($token->start, $token->end, $errorSpans)) {
                continue;
            }

            $style = $this->styleForToken($line, $token, $background, $options);
            if ($style === null || $token->end <= $token->start) {
                continue;
            }

            $spans[] = [
                'start' => $token->start,
                'end' => $token->end,
                'style' => $style,
            ];
        }
        foreach ($errorSpans as $span) {
            $spans[] = $span;
        }

        return $this->mergeAdjacentSpans($spans);
    }

    /**
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style?:string}>, source?: string, lineStartOffset?: int} $options
     */
    public function highlightLine(string $line, int $tabWidth, array $options = []): string
    {
        return $this->applySpansToRange($line, 0, strlen($line), $this->spansForLine($line, $options), $tabWidth);
    }

    /**
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool} $options
     * @return array<int, list<array{start:int, end:int, style:string}>>
     */
    public function treeSitterErrorSpansByLine(string $source, array $options = []): array
    {
        if (($options['syntaxHighlight'] ?? true) === false || trim($source) === '') {
            return [];
        }

        $lineStarts = $this->lineStartOffsets($source);
        $spans = [];
        foreach ($this->differ->syntaxErrorSpans($source, $options) as $span) {
            $lineNumber = $this->lineNumberForOffset($lineStarts, $span['start']);
            $lineStart = $lineStarts[$lineNumber];
            $spans[$lineNumber][] = [
                'start' => $span['start'] - $lineStart,
                'end' => $span['end'] - $lineStart,
                'style' => '35',
            ];
        }

        foreach ($spans as &$lineSpans) {
            $lineSpans = $this->mergeAdjacentSpans($lineSpans);
        }
        unset($lineSpans);

        return $spans;
    }

    /**
     * @param list<array{start:int, end:int, style:string}> $spans
     */
    public function applySpansToRange(string $line, int $start, int $end, array $spans, int $tabWidth): string
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

            $display .= $this->ansi($this->expandTabs(substr($line, $spanStart, $spanEnd - $spanStart), $tabWidth), $span['style']);
            $cursor = $spanEnd;
        }

        if ($cursor < $end) {
            $display .= $this->expandTabs(substr($line, $cursor, $end - $cursor), $tabWidth);
        }

        return $display;
    }

    /**
     * @param list<array{start:int, end:int, style:string}> $spans
     * @return list<array{start:int, end:int, style:string}>
     */
    private function mergeAdjacentSpans(array $spans): array
    {
        usort($spans, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $merged = [];

        foreach ($spans as $span) {
            $last = array_key_last($merged);
            if ($last !== null && $span['style'] === $merged[$last]['style'] && $span['start'] === $merged[$last]['end']) {
                $merged[$last]['end'] = $span['end'];
                continue;
            }

            $merged[] = $span;
        }

        return $merged;
    }

    /**
     * @param array{treeSitterErrorSpans?: list<array{start:int, end:int, style?:string}>} $options
     * @return list<array{start:int, end:int, style:string}>
     */
    private function treeSitterErrorSpansForLine(array $options): array
    {
        $spans = [];
        foreach ($options['treeSitterErrorSpans'] ?? [] as $span) {
            if ($span['end'] <= $span['start']) {
                continue;
            }

            $spans[] = [
                'start' => $span['start'],
                'end' => $span['end'],
                'style' => $span['style'] ?? '35',
            ];
        }

        return $this->mergeAdjacentSpans($spans);
    }

    /**
     * @param list<array{start:int, end:int, style:string}> $spans
     */
    private function overlapsStyledSpan(int $start, int $end, array $spans): bool
    {
        foreach ($spans as $span) {
            if ($start < $span['end'] && $end > $span['start']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function lineStartOffsets(string $source): array
    {
        $starts = [0];
        $offset = 0;
        while (($newline = strpos($source, "\n", $offset)) !== false) {
            $starts[] = $newline + 1;
            $offset = $newline + 1;
        }

        return $starts;
    }

    /**
     * @param list<int> $lineStarts
     */
    private function lineNumberForOffset(array $lineStarts, int $offset): int
    {
        $lineNumber = 0;
        foreach ($lineStarts as $index => $lineStart) {
            if ($lineStart > $offset) {
                break;
            }

            $lineNumber = $index;
        }

        return $lineNumber;
    }

    /**
     * @param array{language?: string, source?: string, lineStartOffset?: int} $options
     */
    private function styleForToken(string $line, Token $token, string $background, array $options): ?string
    {
        $source = (string) ($options['source'] ?? $line);
        $lineStartOffset = (int) ($options['lineStartOffset'] ?? 0);
        $sourceToken = new Token(
            $token->kind,
            $token->text,
            $token->delimiterRole,
            $token->depth,
            $lineStartOffset + $token->start,
            $lineStartOffset + $token->end,
        );

        return $this->highlightClassifier->ansiStyleForHighlight(
            $this->highlightClassifier->highlightForToken($source, $sourceToken, $options),
            $background,
        );
    }

    private function ansi(string $text, string $style): string
    {
        return $text === '' ? '' : "\033[" . $style . 'm' . $text . "\033[0m";
    }

    private function expandTabs(string $text, int $tabWidth): string
    {
        return str_replace("\t", str_repeat(' ', $tabWidth), $text);
    }
}
