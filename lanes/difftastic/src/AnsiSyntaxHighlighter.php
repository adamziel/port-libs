<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class AnsiSyntaxHighlighter
{
    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
    ) {
    }

    /**
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style?:string}>} $options
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

            $style = $this->styleForToken($token, $background, $options);
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
     * @param array{language?: string, backgroundColor?: string, syntaxHighlight?: bool, treeSitterErrorSpans?: list<array{start:int, end:int, style?:string}>} $options
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
     * @param array{language?: string} $options
     */
    private function styleForToken(Token $token, string $background, array $options): ?string
    {
        if ($token->kind === 'comment') {
            return $background === 'dark' ? '3;94' : '3;34';
        }

        if ($token->kind === 'string') {
            return $background === 'dark' ? '95' : '35';
        }

        if ($token->kind === 'identifier' && $this->isKeywordOrType($token->text, $options)) {
            return '1';
        }

        return null;
    }

    /**
     * @param array{language?: string} $options
     */
    private function isKeywordOrType(string $text, array $options): bool
    {
        $language = strtolower((string) ($options['language'] ?? ''));
        $lower = strtolower($text);

        return in_array($lower, $this->languageKeywords($language), true)
            || in_array($lower, $this->languageTypes($language), true);
    }

    /**
     * @return list<string>
     */
    private function languageKeywords(string $language): array
    {
        return match ($language) {
            'javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx' => [
                'as', 'assert', 'async', 'await', 'break', 'case', 'catch', 'class',
                'const', 'default', 'delete', 'do', 'else', 'export', 'extends',
                'finally', 'for', 'from', 'function', 'if', 'import', 'in',
                'instanceof', 'let', 'new', 'of', 'return', 'static', 'switch',
                'throw', 'try', 'type', 'typeof', 'var', 'while', 'with', 'yield',
            ],
            'php', 'hack', 'hh' => [
                'case', 'catch', 'class', 'declare', 'default', 'else', 'extends',
                'finally', 'for', 'foreach', 'function', 'if', 'implements',
                'interface', 'match', 'namespace', 'new', 'private', 'protected',
                'public', 'return', 'static', 'switch', 'throw', 'trait', 'try',
                'use', 'while', 'yield',
            ],
            'python', 'py' => [
                'and', 'as', 'assert', 'async', 'await', 'break', 'class',
                'continue', 'def', 'del', 'elif', 'else', 'except', 'finally',
                'for', 'from', 'if', 'import', 'in', 'is', 'lambda', 'not', 'or',
                'pass', 'raise', 'return', 'try', 'while', 'with', 'yield',
            ],
            'rust', 'rs' => [
                'async', 'await', 'const', 'crate', 'else', 'enum', 'extern',
                'fn', 'for', 'if', 'impl', 'let', 'loop', 'match', 'mod', 'mut',
                'pub', 'return', 'self', 'static', 'struct', 'super', 'trait',
                'type', 'unsafe', 'use', 'where', 'while',
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function languageTypes(string $language): array
    {
        return match ($language) {
            'javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx' => [
                'any', 'array', 'bigint', 'boolean', 'never', 'number', 'object',
                'promise', 'record', 'string', 'symbol', 'unknown', 'void',
            ],
            'php', 'hack', 'hh' => [
                'array', 'bool', 'callable', 'float', 'int', 'iterable', 'mixed',
                'never', 'object', 'parent', 'self', 'string', 'void',
            ],
            'python', 'py' => [
                'bool', 'bytes', 'dict', 'float', 'int', 'list', 'none', 'set',
                'str', 'tuple',
            ],
            'rust', 'rs' => [
                'bool', 'char', 'f32', 'f64', 'i8', 'i16', 'i32', 'i64', 'i128',
                'isize', 'str', 'u8', 'u16', 'u32', 'u64', 'u128', 'usize',
            ],
            default => [],
        };
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
