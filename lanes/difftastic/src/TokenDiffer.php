<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class TokenDiffer
{
    private const BASE_DELIMITER_PAIRS = ['(' => ')', '[' => ']', '{' => '}'];
    private const DEFAULT_BYTE_LIMIT = 1_000_000;
    private const DEFAULT_GRAPH_LIMIT = 3_000_000;

    /**
     * @param array{language?: string} $options
     * @return list<Token>
     */
    public function tokenize(string $source, array $options = []): array
    {
        if ($this->isYamlLanguage($options)) {
            return $this->tokenizeYamlBlockScalars($source, $options);
        }

        $depth = 0;

        return $this->tokenizeGeneric($source, $options, 0, $depth);
    }

    /**
     * @param array{language?: string} $options
     * @return list<Token>
     */
    private function tokenizeGeneric(string $source, array $options, int $baseOffset, int &$depth): array
    {
        $lineCommentPattern = match (true) {
            $this->isLispLanguage($options) => ';[^\r\n]*|\/\/[^\r\n]*',
            $this->isPythonLanguage($options),
            $this->isBashLanguage($options) => '\#[^\r\n]*',
            $this->isRubyLanguage($options) => '\#[^\r\n]*',
            default => '\/\/[^\r\n]*',
        };
        $stringPattern = $this->stringPattern($options);
        $bashFlagPattern = $this->isBashLanguage($options) ? '--?[A-Za-z0-9][A-Za-z0-9_-]*(?:=[^\s;]+)?|' : '';
        preg_match_all(
            '/<!--[\s\S]*?-->|\/\*[\s\S]*?\*\/|' . $lineCommentPattern . '|' . $bashFlagPattern . '[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|' . $stringPattern . '|===|!==|==|!=|<=|>=|=>|->|::|&&|\|\||>>|[{}()[\].,;:+*\/<>=!|$-]|\S/u',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $delimiterPairs = $this->delimiterPairs($options);
        $closeDelimiters = array_flip(array_values($delimiterPairs));
        $tokens = [];
        foreach ($matches[0] ?? [] as $match) {
            [$text, $start] = $match;
            $absoluteStart = $baseOffset + $start;
            $delimiterRole = null;
            if (isset($closeDelimiters[$text])) {
                $depth = max(0, $depth - 1);
                $delimiterRole = 'close';
            } elseif (isset($delimiterPairs[$text])) {
                $delimiterRole = 'open';
            }

            $tokens[] = new Token(
                $this->classify($text, $delimiterPairs, $options),
                $text,
                $delimiterRole,
                $depth,
                $absoluteStart,
                $absoluteStart + strlen($text),
            );

            if ($delimiterRole === 'open') {
                $depth++;
            }
        }

        return $tokens;
    }

    /**
     * @param array{language?: string} $options
     * @return list<Token>
     */
    private function tokenizeYamlBlockScalars(string $source, array $options): array
    {
        $depth = 0;
        $tokens = [];
        $offset = 0;
        $length = strlen($source);
        $matched = preg_match_all(
            '/^(?<indent>[ \t]*)(?<key>[^#:\r\n][^:\r\n]*):[ \t]*(?<style>[|>])(?<mods>[0-9+-]*)[^\r\n]*(?:\r\n|\n|\r)/m',
            $source,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        if ($matched === false || $matched === 0) {
            return $this->tokenizeGeneric($source, $options, 0, $depth);
        }

        foreach ($matches as $match) {
            $headerStart = $match[0][1];
            if ($headerStart < $offset) {
                continue;
            }

            $headerEnd = $headerStart + strlen($match[0][0]);
            if ($headerStart > $offset) {
                $tokens = array_merge(
                    $tokens,
                    $this->tokenizeGeneric(substr($source, $offset, $headerStart - $offset), $options, $offset, $depth),
                );
            }

            $tokens = array_merge(
                $tokens,
                $this->tokenizeGeneric(substr($source, $headerStart, $headerEnd - $headerStart), $options, $headerStart, $depth),
            );

            $contentEnd = $this->yamlBlockScalarEnd($source, $headerEnd, strlen($match['indent'][0]));
            if ($contentEnd > $headerEnd) {
                $text = $this->trimFinalLineEnding(substr($source, $headerEnd, $contentEnd - $headerEnd));
                if ($text !== '') {
                    $tokens[] = new Token('string', $text, 'block-scalar', $depth, $headerEnd, $headerEnd + strlen($text));
                }
            }

            $offset = max($contentEnd, $headerEnd);
        }

        if ($offset < $length) {
            $tokens = array_merge(
                $tokens,
                $this->tokenizeGeneric(substr($source, $offset), $options, $offset, $depth),
            );
        }

        return $tokens;
    }

    private function yamlBlockScalarEnd(string $source, int $start, int $baseIndent): int
    {
        $length = strlen($source);
        $offset = $start;
        $end = $start;

        while ($offset < $length) {
            $newline = strpos($source, "\n", $offset);
            $lineEnd = $newline === false ? $length : $newline;
            $lineContentEnd = $lineEnd > $offset && $source[$lineEnd - 1] === "\r" ? $lineEnd - 1 : $lineEnd;
            $line = substr($source, $offset, $lineContentEnd - $offset);
            $nextOffset = $newline === false ? $length : $newline + 1;

            if (trim($line) !== '' && strspn($line, " \t") <= $baseIndent) {
                break;
            }

            $end = $nextOffset;
            $offset = $nextOffset;
        }

        return $end;
    }

    private function trimFinalLineEnding(string $text): string
    {
        if (str_ends_with($text, "\r\n")) {
            return substr($text, 0, -2);
        }
        if (str_ends_with($text, "\n") || str_ends_with($text, "\r")) {
            return substr($text, 0, -1);
        }

        return $text;
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, stripCr?: bool} $options
     * @return list<array{op:string, text:string}>
     */
    public function diff(string $old, string $new, array $options = []): array
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);
        $a = array_map(static fn (Token $token): string => $token->text, $this->tokensForDiff($old, $options));
        $b = array_map(static fn (Token $token): string => $token->text, $this->tokensForDiff($new, $options));

        return $this->diffSequences($a, $b);
    }

    /**
     * @return list<string>
     */
    public function splitWords(string $source): array
    {
        $words = [];
        $current = '';
        foreach ($this->characters($source) as $character) {
            if ($current !== '') {
                if ($this->isWordCharacter($character)) {
                    $current .= $character;
                    continue;
                }

                $words[] = $current;
                $words[] = $character;
                $current = '';
                continue;
            }

            if ($this->isWordCharacter($character)) {
                $current = $character;
            } else {
                $words[] = $character;
            }
        }

        if ($current !== '') {
            $words[] = $current;
        }

        return $words;
    }

    /**
     * @return list<string>
     */
    public function splitWordsAndNumbers(string $source): array
    {
        $words = [];
        $current = '';
        $currentIsAsciiDigit = false;
        foreach ($this->characters($source) as $character) {
            if ($current !== '') {
                if ($this->isWordCharacter($character)) {
                    $characterIsAsciiDigit = $this->isAsciiDigit($character);
                    if ($characterIsAsciiDigit === $currentIsAsciiDigit) {
                        $current .= $character;
                    } else {
                        $words[] = $current;
                        $current = $character;
                        $currentIsAsciiDigit = $characterIsAsciiDigit;
                    }
                    continue;
                }

                $words[] = $current;
                $words[] = $character;
                $current = '';
                continue;
            }

            if ($this->isWordCharacter($character)) {
                $current = $character;
                $currentIsAsciiDigit = $this->isAsciiDigit($character);
            } else {
                $words[] = $character;
            }
        }

        if ($current !== '') {
            $words[] = $current;
        }

        return $words;
    }

    /**
     * @param array{splitNumbers?: bool, stripCr?: bool} $options
     * @return list<array{op:string, text:string}>
     */
    public function diffWords(string $old, string $new, array $options = []): array
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);
        $splitNumbers = ($options['splitNumbers'] ?? false) === true;
        $a = $splitNumbers ? $this->splitWordsAndNumbers($old) : $this->splitWords($old);
        $b = $splitNumbers ? $this->splitWordsAndNumbers($new) : $this->splitWords($new);
        if (count($a) * count($b) > 1_000_000) {
            return $this->boundedSequenceDiff($a, $b);
        }

        return $this->diffSequences($a, $b);
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, stripCr?: bool} $options
     */
    public function hasChanges(string $old, string $new, array $options = []): bool
    {
        foreach ($this->diff($old, $new, $options) as $op) {
            if ($op['op'] !== '=') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool} $options
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    public function diffSyntaxLists(string $old, string $new, array $options = []): array
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);
        if ($old !== $new) {
            if ($this->isPlainTextLanguage($options)) {
                return $this->diffPlainTextLines($old, $new);
            }

            $fallbackReason = $this->textFallbackReason($old, $new, $options);
            if ($fallbackReason !== null) {
                return $this->diffTextFallback($old, $new, $fallbackReason);
            }

            if ($this->isMakefileLanguage($options)) {
                return $this->diffMakefileTextAtoms($old, $new);
            }

            if ($this->isTomlLanguage($options)) {
                return $this->diffTomlEntries($old, $new);
            }
        }

        $delimiterPairs = $this->delimiterPairs($options);
        $oldForRoot = $this->isHtmlLanguage($options) ? $this->stripHtmlRawBlockBodies($old) : $old;
        $newForRoot = $this->isHtmlLanguage($options) ? $this->stripHtmlRawBlockBodies($new) : $new;
        $oldRoot = $this->parseTokenTree($this->tokensForDiff($oldForRoot, $options), $delimiterPairs);
        $newRoot = $this->parseTokenTree($this->tokensForDiff($newForRoot, $options), $delimiterPairs);
        $changes = [];

        $oldLists = $this->directLists($oldRoot['children']);
        $newLists = $this->directLists($newRoot['children']);
        if ($this->usesAngleDelimiters($options)) {
            $this->diffRootListNodes($oldLists, $newLists, $options, $changes);
            if ($this->isHtmlLanguage($options)) {
                $this->diffHtmlStyleSubLanguage($old, $new, $changes);
                $this->diffHtmlScriptSubLanguage($old, $new, $changes);
            }

            return $changes;
        }
        if ($this->isCssLanguage($options)) {
            $this->diffCssRules($oldRoot['children'], $newRoot['children'], $options, $changes);

            return $changes;
        }
        if ($this->isJavaScriptLanguage($options)) {
            $this->diffJavaScriptStatementSyntax($oldRoot['children'], $newRoot['children'], $options, $changes, '$js');

            return $changes;
        }
        if ($this->isPythonLanguage($options)) {
            $this->diffPythonBlocks($old, $new, $changes);

            return $changes;
        }

        $pairs = min(count($oldLists), count($newLists));
        for ($i = 0; $i < $pairs; $i++) {
            $this->diffListNode($oldLists[$i], $newLists[$i], '$[' . $i . ']', $options, $changes);
        }
        for ($i = $pairs; $i < count($oldLists); $i++) {
            $changes[] = ['op' => '-', 'path' => '$[' . $i . ']', 'text' => $this->nodeText($oldLists[$i])];
        }
        for ($i = $pairs; $i < count($newLists); $i++) {
            $changes[] = ['op' => '+', 'path' => '$[' . $i . ']', 'text' => $this->nodeText($newLists[$i])];
        }

        if ($this->isYamlLanguage($options)) {
            $this->diffYamlBlockSequences($old, $new, $changes);
            $this->diffYamlBlockScalars($old, $new, $changes);
        }
        if ($this->isPhpLikeLanguage($options)) {
            $this->diffPhpFunctionReturnTypes($old, $new, $changes);
        }

        return $changes;
    }

    /**
     * @param array{language?: string, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, stripCr?: bool} $options
     */
    public function textFallbackReason(string $old, string $new, array $options = [], ?string $languageName = null): ?string
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);

        return $this->byteLimitFallbackReason($old, $new, $options)
            ?? $this->syntaxErrorFallbackReason($old, $new, $options, $languageName)
            ?? $this->graphLimitFallbackReason($old, $new, $options);
    }

    /**
     * @param array{language?: string, byteLimit?: int, stripCr?: bool} $options
     */
    public function byteLimitFallbackReason(string $old, string $new, array $options = []): ?string
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);

        if (!$this->usesTextFallback($options)) {
            return null;
        }

        $limit = max(0, (int) ($options['byteLimit'] ?? self::DEFAULT_BYTE_LIMIT));
        $oldBytes = strlen($old);
        $newBytes = strlen($new);
        if ($oldBytes <= $limit && $newBytes <= $limit) {
            return null;
        }

        return $this->formatBinarySize(max($oldBytes, $newBytes)) . ' exceeded DFT_BYTE_LIMIT';
    }

    /**
     * @param array{language?: string, parseErrorLimit?: int, stripCr?: bool} $options
     */
    public function syntaxErrorFallbackReason(string $old, string $new, array $options = [], ?string $languageName = null): ?string
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);

        if (!$this->usesParseErrorFallback($options)) {
            return null;
        }

        $limit = max(0, (int) ($options['parseErrorLimit'] ?? 0));
        $errorCount = $this->syntaxErrorCount($old, $options) + $this->syntaxErrorCount($new, $options);
        if ($errorCount <= $limit) {
            return null;
        }

        $languageName ??= $this->displayLanguageName($options);

        return $errorCount . ' ' . $languageName . ' parse error' . ($errorCount === 1 ? '' : 's') . ', exceeded DFT_PARSE_ERROR_LIMIT';
    }

    /**
     * @param array{language?: string, ignoreComments?: bool, ignoreTrailingCommas?: bool, graphLimit?: int, stripCr?: bool} $options
     */
    public function graphLimitFallbackReason(string $old, string $new, array $options = []): ?string
    {
        $old = $this->normalizeTextForDiff($old, $options);
        $new = $this->normalizeTextForDiff($new, $options);

        if (!$this->usesTextFallback($options)) {
            return null;
        }

        $limit = max(0, (int) ($options['graphLimit'] ?? self::DEFAULT_GRAPH_LIMIT));
        if ($this->estimatedGraphVertexBound($old, $new, $options) <= $limit) {
            return null;
        }

        return 'exceeded DFT_GRAPH_LIMIT';
    }

    /**
     * Difftastic aborts Dijkstra when the visited graph exceeds DFT_GRAPH_LIMIT.
     * The PHP port does not build the same arena graph, so this uses the syntax
     * node cross-product as a conservative preflight bound for the same fallback.
     *
     * @param array{language?: string, ignoreComments?: bool, ignoreTrailingCommas?: bool} $options
     */
    private function estimatedGraphVertexBound(string $old, string $new, array $options): int
    {
        $delimiterPairs = $this->delimiterPairs($options);
        $oldTree = $this->parseTokenTree($this->tokensForDiff($old, $options), $delimiterPairs);
        $newTree = $this->parseTokenTree($this->tokensForDiff($new, $options), $delimiterPairs);

        return $this->syntaxNodeCount($oldTree['children']) * $this->syntaxNodeCount($newTree['children']);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function syntaxNodeCount(array $nodes): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            $count++;
            if (($node['type'] ?? '') === 'list') {
                $count += $this->syntaxNodeCount($node['children'] ?? []);
            }
        }

        return $count;
    }

    /**
     * @param array{language?: string, stripCr?: bool} $options
     */
    public function syntaxErrorCount(string $source, array $options = []): int
    {
        return count($this->syntaxErrorSpans($source, $options));
    }

    /**
     * @param array{language?: string, stripCr?: bool} $options
     * @return list<array{start:int, end:int, text:string}>
     */
    public function syntaxErrorSpans(string $source, array $options = []): array
    {
        $source = $this->normalizeTextForDiff($source, $options);

        if (!$this->usesParseErrorFallback($options)) {
            return [];
        }

        $delimiterPairs = $this->delimiterPairs($options);
        $closeToOpen = array_flip($delimiterPairs);
        $stack = [];
        $errors = [];

        foreach ($this->tokenize($source, $options) as $token) {
            if ($token->kind === 'comment' || $token->kind === 'string') {
                continue;
            }

            if (isset($delimiterPairs[$token->text])) {
                $stack[] = $token;
                continue;
            }

            if (isset($closeToOpen[$token->text])) {
                $expectedOpen = $closeToOpen[$token->text];
                $lastIndex = array_key_last($stack);
                $lastOpen = $lastIndex === null ? null : $stack[$lastIndex];
                if ($lastOpen instanceof Token && $lastOpen->text === $expectedOpen) {
                    array_pop($stack);
                    continue;
                }

                $errors[] = [
                    'start' => $token->start,
                    'end' => $token->end,
                    'text' => $token->text,
                ];
            }
        }

        foreach ($stack as $token) {
            if (!$token instanceof Token) {
                continue;
            }

            $errors[] = [
                'start' => $token->start,
                'end' => $token->end,
                'text' => $token->text,
            ];
        }

        usort($errors, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        return $errors;
    }

    /**
     * Difftastic's CLI defaults to `--strip-cr=on`, removing carriage
     * returns before parsing so CRLF-only edits do not become structural
     * string/comment churn.
     *
     * @param array{stripCr?: bool} $options
     */
    public function normalizeTextForDiff(string $source, array $options = []): string
    {
        if (($options['stripCr'] ?? true) === false) {
            return $source;
        }

        return str_replace("\r", '', $source);
    }

    /**
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    private function diffTextFallback(string $old, string $new, string $reason): array
    {
        return $this->diffLineChanges($old, $new, [[
            'op' => '~',
            'path' => '$text.fallback',
            'old' => 'Text (' . $reason . ')',
            'new' => 'line-oriented diff',
        ]]);
    }

    /**
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    private function diffPlainTextLines(string $old, string $new): array
    {
        return $this->diffLineChanges($old, $new);
    }

    /**
     * Upstream tree-sitter-make treats `text` and `shell_text` nodes as
     * difftastic atoms. This gives Makefile assignment lines a visible
     * syntax-list change instead of returning an empty delimiter diff.
     *
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    private function diffMakefileTextAtoms(string $old, string $new): array
    {
        return $this->diffLineChanges($old, $new, [], '$make.text');
    }

    /**
     * Tree-sitter TOML treats quoted strings and table/array delimiters as
     * structural nodes. The lightweight PHP parser maps the same sample-file
     * boundary by aligning table-qualified key/value entries before falling
     * back to top-level array item diffs.
     *
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    private function diffTomlEntries(string $old, string $new): array
    {
        $oldEntries = $this->tomlEntries($old);
        $newEntries = $this->tomlEntries($new);
        $newByPath = [];
        foreach ($newEntries as $index => $entry) {
            $newByPath[$entry['path']][] = $index;
        }

        $changes = [];
        $matchedNewIndexes = [];
        foreach ($oldEntries as $oldEntry) {
            $matchIndex = null;
            foreach ($newByPath[$oldEntry['path']] ?? [] as $candidateIndex) {
                if (($matchedNewIndexes[$candidateIndex] ?? false) === false) {
                    $matchIndex = $candidateIndex;
                    break;
                }
            }

            if ($matchIndex === null) {
                $changes[] = [
                    'op' => '-',
                    'path' => $oldEntry['path'],
                    'text' => $this->tomlEntryText($oldEntry),
                ];
                continue;
            }

            $matchedNewIndexes[$matchIndex] = true;
            $newEntry = $newEntries[$matchIndex];
            if ($oldEntry['value'] === $newEntry['value']) {
                continue;
            }

            if ($this->isTomlArrayValue($oldEntry['value']) && $this->isTomlArrayValue($newEntry['value'])) {
                $this->diffTomlArrayItems($oldEntry, $newEntry, $changes);
                continue;
            }

            $changes[] = [
                'op' => '~',
                'path' => $oldEntry['path'],
                'old' => $oldEntry['value'],
                'new' => $newEntry['value'],
            ];
        }

        foreach ($newEntries as $index => $entry) {
            if (($matchedNewIndexes[$index] ?? false) === true) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $entry['path'],
                'text' => $this->tomlEntryText($entry),
            ];
        }

        return $changes;
    }

    /**
     * @param array{path:string, value:string} $oldEntry
     * @param array{path:string, value:string} $newEntry
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffTomlArrayItems(array $oldEntry, array $newEntry, array &$changes): void
    {
        $oldItems = $this->tomlTopLevelArrayItems($oldEntry['value']);
        $newItems = $this->tomlTopLevelArrayItems($newEntry['value']);
        if ($oldItems === [] && $newItems === []) {
            $changes[] = [
                'op' => '~',
                'path' => $oldEntry['path'],
                'old' => $oldEntry['value'],
                'new' => $newEntry['value'],
            ];

            return;
        }

        $table = $this->lcsTable($oldItems, $newItems);
        $oldIndex = 0;
        $newIndex = 0;
        while ($oldIndex < count($oldItems) && $newIndex < count($newItems)) {
            if ($oldItems[$oldIndex] === $newItems[$newIndex]) {
                $oldIndex++;
                $newIndex++;
                continue;
            }

            if ($table[$oldIndex + 1][$newIndex] >= $table[$oldIndex][$newIndex + 1]) {
                $changes[] = [
                    'op' => '-',
                    'path' => $oldEntry['path'] . '[' . $oldIndex . ']',
                    'text' => $oldItems[$oldIndex],
                ];
                $oldIndex++;
            } else {
                $changes[] = [
                    'op' => '+',
                    'path' => $newEntry['path'] . '[' . $newIndex . ']',
                    'text' => $newItems[$newIndex],
                ];
                $newIndex++;
            }
        }

        while ($oldIndex < count($oldItems)) {
            $changes[] = [
                'op' => '-',
                'path' => $oldEntry['path'] . '[' . $oldIndex . ']',
                'text' => $oldItems[$oldIndex],
            ];
            $oldIndex++;
        }
        while ($newIndex < count($newItems)) {
            $changes[] = [
                'op' => '+',
                'path' => $newEntry['path'] . '[' . $newIndex . ']',
                'text' => $newItems[$newIndex],
            ];
            $newIndex++;
        }
    }

    /**
     * @return list<array{path:string, key:string, value:string, text:string}>
     */
    private function tomlEntries(string $source): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $source);
        if ($lines === false) {
            $lines = [$source];
        }

        $section = [];
        $arrayTableIndexes = [];
        $entries = [];
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^\[\[?\s*(?<name>[^\]]+?)\s*\]\]?$/', $trimmed, $match) === 1) {
                $section = $this->tomlDottedPath($match['name']);
                if (str_starts_with($trimmed, '[[') && str_ends_with($trimmed, ']]') && $section !== []) {
                    $arrayKey = implode("\0", $section);
                    $arrayIndex = $arrayTableIndexes[$arrayKey] ?? 0;
                    $arrayTableIndexes[$arrayKey] = $arrayIndex + 1;
                    $lastIndex = array_key_last($section);
                    $section[$lastIndex] = $this->tomlIndexedPathPart($section[$lastIndex], $arrayIndex);
                }
                continue;
            }

            $equalsOffset = $this->tomlAssignmentEqualsOffset($line);
            if ($equalsOffset === null) {
                continue;
            }

            $key = trim(substr($line, 0, $equalsOffset));
            $value = trim(substr($line, $equalsOffset + 1));
            $entryLines = [$line];

            $delimiter = $this->tomlMultilineStringDelimiter($value);
            while ($delimiter !== null && !$this->tomlMultilineStringClosed($value, $delimiter) && $index + 1 < $count) {
                $index++;
                $entryLines[] = $lines[$index];
                $value .= "\n" . $lines[$index];
            }

            $keyParts = $this->tomlDottedPath($key);
            $pathParts = array_merge($section, $keyParts);
            if ($this->isTomlInlineTableValue($value)) {
                $inlineEntries = $this->tomlInlineTableEntries($pathParts, $value);
                if ($inlineEntries !== []) {
                    foreach ($inlineEntries as $inlineEntry) {
                        $entries[] = $inlineEntry;
                    }
                    continue;
                }
            }

            $entries[] = [
                'path' => $this->tomlPath($pathParts),
                'key' => implode('.', $keyParts),
                'value' => $value,
                'text' => implode("\n", $entryLines),
            ];
        }

        return $entries;
    }

    private function tomlAssignmentEqualsOffset(string $line): ?int
    {
        $quote = null;
        $length = strlen($line);
        for ($index = 0; $index < $length; $index++) {
            $character = $line[$index];
            if ($quote !== null) {
                if ($character === '\\' && $quote === '"' && $index + 1 < $length) {
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }

            if ($character === '=') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tomlDottedPath(string $path): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $length = strlen($path);
        for ($index = 0; $index < $length; $index++) {
            $character = $path[$index];
            if ($quote !== null) {
                if ($character === '\\' && $quote === '"' && $index + 1 < $length) {
                    $current .= $character . $path[++$index];
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                    continue;
                }
                $current .= $character;
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }

            if ($character === '.') {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $character;
        }

        $parts[] = trim($current);

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param list<string> $parts
     */
    private function tomlPath(array $parts): string
    {
        $path = '$toml';
        foreach ($parts as $part) {
            $path .= $this->tomlPathPart($part);
        }

        return $path;
    }

    private function tomlPathPart(string $part): string
    {
        $indexSuffix = '';
        if (preg_match('/^(?<name>.+)\[(?<index>\d+)\]$/', $part, $match) === 1) {
            $part = $match['name'];
            $indexSuffix = '[' . $match['index'] . ']';
        }

        $prefix = preg_match('/^[A-Za-z0-9_-]+$/', $part) === 1
            ? '.' . $part
            : '["' . addcslashes($part, "\\\"") . '"]';

        return $prefix . $indexSuffix;
    }

    private function tomlIndexedPathPart(string $part, int $index): string
    {
        return $part . '[' . $index . ']';
    }

    private function tomlMultilineStringDelimiter(string $value): ?string
    {
        if (str_starts_with($value, '"""')) {
            return '"""';
        }
        if (str_starts_with($value, "'''")) {
            return "'''";
        }

        return null;
    }

    private function tomlMultilineStringClosed(string $value, string $delimiter): bool
    {
        $first = strpos($value, $delimiter);
        if ($first === false) {
            return false;
        }

        $last = strrpos($value, $delimiter);

        return $last !== false && $last > $first;
    }

    /**
     * @param array{text:string} $entry
     */
    private function tomlEntryText(array $entry): string
    {
        return trim($entry['text']);
    }

    private function isTomlArrayValue(string $value): bool
    {
        $trimmed = trim($value);

        return str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']');
    }

    private function isTomlInlineTableValue(string $value): bool
    {
        $trimmed = trim($value);

        return str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}');
    }

    /**
     * @param list<string> $basePathParts
     * @return list<array{path:string, key:string, value:string, text:string}>
     */
    private function tomlInlineTableEntries(array $basePathParts, string $value): array
    {
        $trimmed = trim($value);
        if (!str_starts_with($trimmed, '{') || !str_ends_with($trimmed, '}')) {
            return [];
        }

        $entries = [];
        foreach ($this->tomlCommaSeparatedTopLevelItems(trim(substr($trimmed, 1, -1))) as $item) {
            $equalsOffset = $this->tomlAssignmentEqualsOffset($item);
            if ($equalsOffset === null) {
                continue;
            }

            $key = trim(substr($item, 0, $equalsOffset));
            $fieldValue = trim(substr($item, $equalsOffset + 1));
            $keyParts = $this->tomlDottedPath($key);
            $pathParts = array_merge($basePathParts, $keyParts);
            if ($this->isTomlInlineTableValue($fieldValue)) {
                $inlineEntries = $this->tomlInlineTableEntries($pathParts, $fieldValue);
                if ($inlineEntries !== []) {
                    foreach ($inlineEntries as $inlineEntry) {
                        $entries[] = $inlineEntry;
                    }
                    continue;
                }
            }

            $entries[] = [
                'path' => $this->tomlPath($pathParts),
                'key' => implode('.', $keyParts),
                'value' => $fieldValue,
                'text' => $key . ' = ' . $fieldValue,
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function tomlTopLevelArrayItems(string $value): array
    {
        $trimmed = trim($value);
        if (!str_starts_with($trimmed, '[') || !str_ends_with($trimmed, ']')) {
            return [];
        }

        $inner = trim(substr($trimmed, 1, -1));
        if ($inner === '') {
            return [];
        }

        return $this->tomlCommaSeparatedTopLevelItems($inner);
    }

    /**
     * @return list<string>
     */
    private function tomlCommaSeparatedTopLevelItems(string $inner): array
    {
        if ($inner === '') {
            return [];
        }

        $items = [];
        $current = '';
        $quote = null;
        $depth = 0;
        $length = strlen($inner);
        for ($index = 0; $index < $length; $index++) {
            $character = $inner[$index];
            if ($quote !== null) {
                $current .= $character;
                if ($character === '\\' && $quote === '"' && $index + 1 < $length) {
                    $current .= $inner[++$index];
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $current .= $character;
                continue;
            }

            if ($character === '[' || $character === '{') {
                $depth++;
                $current .= $character;
                continue;
            }

            if ($character === ']' || $character === '}') {
                $depth = max(0, $depth - 1);
                $current .= $character;
                continue;
            }

            if ($character === ',' && $depth === 0) {
                $items[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $character;
        }

        if (trim($current) !== '') {
            $items[] = trim($current);
        }

        return $items;
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    private function diffLineChanges(string $old, string $new, array $changes = [], string $linePathPrefix = '$text.line'): array
    {
        if ($old === '' && $new !== '') {
            foreach ($this->fallbackLines($new) as $index => $text) {
                $changes[] = ['op' => '+', 'path' => $linePathPrefix . '[' . $index . ']', 'text' => $text];
            }

            return $changes;
        }

        if ($old !== '' && $new === '') {
            foreach ($this->fallbackLines($old) as $index => $text) {
                $changes[] = ['op' => '-', 'path' => $linePathPrefix . '[' . $index . ']', 'text' => $text];
            }

            return $changes;
        }

        $oldLines = $this->fallbackLines($old);
        $newLines = $this->fallbackLines($new);
        $deleted = [];
        $inserted = [];

        foreach ((new LineDiffer())->diff($oldLines, $newLines) as $op) {
            if ($op['op'] === '=') {
                $this->flushFallbackLineChanges($changes, $deleted, $inserted, $linePathPrefix);
                continue;
            }

            if ($op['op'] === '-') {
                $oldIndex = $op['old'];
                $deleted[] = ['index' => $oldIndex, 'text' => $oldLines[$oldIndex]];
            } else {
                $newIndex = $op['new'];
                $inserted[] = ['index' => $newIndex, 'text' => $newLines[$newIndex]];
            }
        }

        $this->flushFallbackLineChanges($changes, $deleted, $inserted, $linePathPrefix);

        return $changes;
    }

    /**
     * @return list<string>
     */
    private function fallbackLines(string $source): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $source);
        if ($lines === false) {
            return [$source];
        }

        return $lines;
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     * @param list<array{index:int, text:string}> $deleted
     * @param list<array{index:int, text:string}> $inserted
     */
    private function flushFallbackLineChanges(array &$changes, array &$deleted, array &$inserted, string $linePathPrefix): void
    {
        $pairs = min(count($deleted), count($inserted));
        for ($index = 0; $index < $pairs; $index++) {
            $changes[] = [
                'op' => '~',
                'path' => $linePathPrefix . '[' . $deleted[$index]['index'] . ']',
                'old' => $deleted[$index]['text'],
                'new' => $inserted[$index]['text'],
            ];
        }

        for ($index = $pairs; $index < count($deleted); $index++) {
            $changes[] = [
                'op' => '-',
                'path' => $linePathPrefix . '[' . $deleted[$index]['index'] . ']',
                'text' => $deleted[$index]['text'],
            ];
        }
        for ($index = $pairs; $index < count($inserted); $index++) {
            $changes[] = [
                'op' => '+',
                'path' => $linePathPrefix . '[' . $inserted[$index]['index'] . ']',
                'text' => $inserted[$index]['text'],
            ];
        }

        $deleted = [];
        $inserted = [];
    }

    /**
     * @param array{language?: string} $options
     */
    private function usesParseErrorFallback(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), [
            'css',
            'hack',
            'javascript',
            'js',
            'json',
            'php',
            'rust',
            'scss',
            'ts',
            'typescript',
            'yaml',
            'yml',
        ], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function usesTextFallback(array $options): bool
    {
        $language = strtolower((string) ($options['language'] ?? ''));
        if ($language === '') {
            return false;
        }

        return !$this->isPlainTextLanguage($options);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isPlainTextLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['plain', 'plain-text', 'plaintext', 'text'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isMakefileLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['make', 'makefile', 'mk'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isTomlLanguage(array $options): bool
    {
        return strtolower((string) ($options['language'] ?? '')) === 'toml';
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
     * @param array{language?: string} $options
     */
    private function displayLanguageName(array $options): string
    {
        return match (strtolower((string) ($options['language'] ?? ''))) {
            'css' => 'CSS',
            'hack' => 'Hack',
            'javascript', 'js' => 'JavaScript',
            'json' => 'JSON',
            'jsx' => 'JavaScript JSX',
            'php' => 'PHP',
            'rust' => 'Rust',
            'scss' => 'SCSS',
            'ts', 'typescript' => 'TypeScript',
            'tsx' => 'TypeScript TSX',
            'yaml', 'yml' => 'YAML',
            default => 'Text',
        };
    }

    /**
     * @param array{language?: string} $options
     * @return array<string, string>
     */
    private function delimiterPairs(array $options): array
    {
        $pairs = self::BASE_DELIMITER_PAIRS;
        if ($this->usesAngleDelimiters($options)) {
            $pairs['<'] = '>';
        }
        if ($this->isRubyLanguage($options)) {
            $pairs += [
                'module' => 'end',
                'class' => 'end',
                'def' => 'end',
                'do' => 'end',
            ];
        }

        return $pairs;
    }

    /**
     * @param array{language?: string} $options
     */
    private function usesAngleDelimiters(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['html', 'jsx', 'tsx', 'xml'], true);
    }

    /**
     * @param array<string, string> $delimiterPairs
     */
    private function classify(string $text, array $delimiterPairs, array $options): string
    {
        $closeDelimiters = array_flip(array_values($delimiterPairs));

        return match (true) {
            str_starts_with($text, '/*'),
            str_starts_with($text, '//'),
            str_starts_with($text, '<!--'),
            str_starts_with($text, '#') && ($this->isPythonLanguage($options) || $this->isRubyLanguage($options) || $this->isBashLanguage($options)),
            str_starts_with($text, ';') && $this->isLispLanguage($options) => 'comment',
            preg_match('/^[A-Za-z_]/', $text) === 1 => 'identifier',
            preg_match('/^\d/', $text) === 1 => 'number',
            $this->isStringToken($text, $options) => 'string',
            isset($delimiterPairs[$text]) || isset($closeDelimiters[$text]) => 'delimiter',
            default => 'punctuation',
        };
    }

    /**
     * @param array{language?: string} $options
     */
    private function stringPattern(array $options): string
    {
        if ($this->isLispLanguage($options)) {
            return '"(?:\\\\.|[^"\\\\])*"';
        }

        if ($this->isRustLanguage($options)) {
            return '"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\\r\n])\'';
        }

        return '"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'';
    }

    /**
     * @param array{language?: string} $options
     */
    private function isStringToken(string $text, array $options): bool
    {
        if (str_starts_with($text, '"')) {
            return true;
        }

        if (!str_starts_with($text, "'") || $this->isLispReaderQuote($text, $options)) {
            return false;
        }

        return !$this->isRustLanguage($options) || strlen($text) > 1;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{op:string, text:string}>
     */
    private function diffSequences(array $a, array $b): array
    {
        $table = $this->lcsTable($a, $b);
        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < count($a) && $j < count($b)) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['op' => '=', 'text' => $a[$i++]];
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['op' => '-', 'text' => $a[$i++]];
            } else {
                $ops[] = ['op' => '+', 'text' => $b[$j++]];
            }
        }
        while ($i < count($a)) {
            $ops[] = ['op' => '-', 'text' => $a[$i++]];
        }
        while ($j < count($b)) {
            $ops[] = ['op' => '+', 'text' => $b[$j++]];
        }

        return $ops;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{op:string, text:string}>
     */
    private function boundedSequenceDiff(array $a, array $b): array
    {
        $aCount = count($a);
        $bCount = count($b);
        $prefix = 0;
        while ($prefix < $aCount && $prefix < $bCount && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while (
            $suffix + $prefix < $aCount
            && $suffix + $prefix < $bCount
            && $a[$aCount - 1 - $suffix] === $b[$bCount - 1 - $suffix]
        ) {
            $suffix++;
        }

        $ops = [];
        for ($i = 0; $i < $prefix; $i++) {
            $ops[] = ['op' => '=', 'text' => $a[$i]];
        }
        for ($i = $prefix; $i < $aCount - $suffix; $i++) {
            $ops[] = ['op' => '-', 'text' => $a[$i]];
        }
        for ($i = $prefix; $i < $bCount - $suffix; $i++) {
            $ops[] = ['op' => '+', 'text' => $b[$i]];
        }
        for ($i = $aCount - $suffix; $i < $aCount; $i++) {
            $ops[] = ['op' => '=', 'text' => $a[$i]];
        }

        return $ops;
    }

    /**
     * @return list<string>
     */
    private function characters(string $source): array
    {
        if ($source === '') {
            return [];
        }

        if (preg_match_all('/./us', $source, $matches) === false || ($matches[0] ?? []) === []) {
            return str_split($source);
        }

        return $matches[0];
    }

    private function isWordCharacter(string $character): bool
    {
        return preg_match('/^[\p{L}\p{N}_]$/u', $character) === 1;
    }

    private function isAsciiDigit(string $character): bool
    {
        return $character >= '0' && $character <= '9';
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return list<Token>
     */
    private function tokensForDiff(string $source, array $options): array
    {
        $tokens = $this->tokenize($source, $options);

        if (($options['ignoreComments'] ?? false) === true) {
            $tokens = array_values(array_filter(
                $tokens,
                static fn (Token $token): bool => $token->kind !== 'comment',
            ));
        }

        if (($options['ignoreTrailingCommas'] ?? true) === true) {
            $tokens = $this->removeIgnoredTrailingCommas($tokens, $options);
        }

        if ($this->isJsxLanguage($options)) {
            $tokens = $this->removeJsxWhitespaceStringExpressions($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function removeIgnoredTrailingCommas(array $tokens, array $options): array
    {
        $kept = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->text === ',' && $this->shouldIgnoreTrailingComma($tokens, $i, $options)) {
                continue;
            }

            $kept[] = $token;
        }

        return $kept;
    }

    /**
     * @param list<Token> $tokens
     */
    private function shouldIgnoreTrailingComma(array $tokens, int $commaIndex, array $options): bool
    {
        $closeIndex = $this->nextSignificantTokenIndex($tokens, $commaIndex + 1);
        if ($closeIndex === null || $tokens[$closeIndex]->delimiterRole !== 'close') {
            return false;
        }

        if (!$this->isPythonLanguage($options)) {
            return true;
        }

        $close = $tokens[$closeIndex]->text;
        if ($close === ']' || $close === '}') {
            return true;
        }
        if ($close !== ')') {
            return false;
        }

        $openIndex = $this->matchingOpenTokenIndex($tokens, $closeIndex, $options);
        if ($openIndex === null) {
            return false;
        }

        $previous = $this->previousSignificantToken($tokens, $openIndex - 1);
        if ($previous === null) {
            return false;
        }

        if (in_array($previous->text, [')', ']', '}'], true)) {
            return true;
        }

        return $previous->kind === 'identifier' && !$this->isPythonControlKeyword($previous->text);
    }

    /**
     * @param list<Token> $tokens
     */
    private function nextSignificantTokenIndex(array $tokens, int $offset): ?int
    {
        $count = count($tokens);
        for ($i = $offset; $i < $count; $i++) {
            if ($tokens[$i]->kind === 'comment') {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private function previousSignificantToken(array $tokens, int $offset): ?Token
    {
        for ($index = $offset; $index >= 0; $index--) {
            if ($tokens[$index]->kind === 'comment') {
                continue;
            }

            return $tokens[$index];
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private function matchingOpenTokenIndex(array $tokens, int $closeIndex, array $options): ?int
    {
        $delimiterPairs = $this->delimiterPairs($options);
        $expectedCloses = [$tokens[$closeIndex]->text];

        for ($index = $closeIndex - 1; $index >= 0; $index--) {
            $token = $tokens[$index];
            if ($token->kind === 'comment') {
                continue;
            }
            if ($token->delimiterRole === 'close') {
                $expectedCloses[] = $token->text;
                continue;
            }
            if ($token->delimiterRole !== 'open') {
                continue;
            }

            $expectedClose = $delimiterPairs[$token->text] ?? null;
            if ($expectedClose === null || $expectedCloses === []) {
                continue;
            }
            if ($expectedCloses[array_key_last($expectedCloses)] !== $expectedClose) {
                continue;
            }

            array_pop($expectedCloses);
            if ($expectedCloses === []) {
                return $index;
            }
        }

        return null;
    }

    private function isPythonControlKeyword(string $text): bool
    {
        return in_array($text, [
            'and',
            'assert',
            'await',
            'class',
            'def',
            'del',
            'elif',
            'else',
            'except',
            'finally',
            'for',
            'from',
            'if',
            'import',
            'in',
            'is',
            'lambda',
            'not',
            'or',
            'raise',
            'return',
            'try',
            'while',
            'with',
            'yield',
        ], true);
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function removeJsxWhitespaceStringExpressions(array $tokens): array
    {
        $kept = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (
                $i + 2 < $count
                && $tokens[$i]->text === '{'
                && $tokens[$i + 1]->kind === 'string'
                && $this->isWhitespaceOnlyStringLiteral($tokens[$i + 1]->text)
                && $tokens[$i + 2]->text === '}'
            ) {
                $i += 2;
                continue;
            }

            $kept[] = $tokens[$i];
        }

        return $kept;
    }

    private function isWhitespaceOnlyStringLiteral(string $text): bool
    {
        if (strlen($text) < 2) {
            return false;
        }

        $quote = $text[0];
        if (($quote !== '"' && $quote !== "'") || !str_ends_with($text, $quote)) {
            return false;
        }

        $inner = substr($text, 1, -1);
        if ($inner === '') {
            return false;
        }

        return trim(stripcslashes($inner)) === '';
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, string> $delimiterPairs
     * @return array{type:string, children:list<array<string, mixed>>}
     */
    private function parseTokenTree(array $tokens, array $delimiterPairs): array
    {
        $offset = 0;

        return ['type' => 'root', 'children' => $this->parseTokenNodes($tokens, $offset, null, $delimiterPairs)];
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, string> $delimiterPairs
     * @return list<array<string, mixed>>
     */
    private function parseTokenNodes(array $tokens, int &$offset, ?string $expectedClose, array $delimiterPairs): array
    {
        $nodes = [];
        $count = count($tokens);
        while ($offset < $count) {
            $token = $tokens[$offset];
            if ($expectedClose !== null && $token->text === $expectedClose) {
                $offset++;

                return $nodes;
            }

            if ($token->delimiterRole === 'open') {
                $open = $token->text;
                $offset++;
                $nodes[] = [
                    'type' => 'list',
                    'open' => $open,
                    'close' => $this->matchingCloseDelimiter($open, $delimiterPairs),
                    'children' => $this->parseTokenNodes($tokens, $offset, $this->matchingCloseDelimiter($open, $delimiterPairs), $delimiterPairs),
                ];
                continue;
            }

            $nodes[] = ['type' => 'atom', 'token' => $token];
            $offset++;
        }

        return $nodes;
    }

    /**
     * @param array<string, string> $delimiterPairs
     */
    private function matchingCloseDelimiter(string $open, array $delimiterPairs): string
    {
        return $delimiterPairs[$open] ?? '';
    }

    /**
     * @param list<array<string, mixed>> $oldLists
     * @param list<array<string, mixed>> $newLists
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffRootListNodes(array $oldLists, array $newLists, array $options, array &$changes): void
    {
        $oldSignatures = array_map(fn (array $list): string => $this->rootListSignature($list), $oldLists);
        $newSignatures = array_map(fn (array $list): string => $this->rootListSignature($list), $newLists);
        $table = $this->lcsTable($oldSignatures, $newSignatures);

        $i = 0;
        $j = 0;
        while ($i < count($oldLists) && $j < count($newLists)) {
            if ($oldSignatures[$i] === $newSignatures[$j]) {
                $this->diffListNode($oldLists[$i], $newLists[$j], '$[' . $j . ']', $options, $changes);
                $i++;
                $j++;
                continue;
            }

            if ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $changes[] = ['op' => '-', 'path' => '$[' . $i . ']', 'text' => $this->nodeText($oldLists[$i])];
                $i++;
            } else {
                $changes[] = ['op' => '+', 'path' => '$[' . $j . ']', 'text' => $this->nodeText($newLists[$j])];
                $j++;
            }
        }

        while ($i < count($oldLists)) {
            $changes[] = ['op' => '-', 'path' => '$[' . $i . ']', 'text' => $this->nodeText($oldLists[$i])];
            $i++;
        }
        while ($j < count($newLists)) {
            $changes[] = ['op' => '+', 'path' => '$[' . $j . ']', 'text' => $this->nodeText($newLists[$j])];
            $j++;
        }
    }

    /**
     * @param array<string, mixed> $list
     */
    private function rootListSignature(array $list): string
    {
        $label = '';
        foreach ($list['children'] ?? [] as $node) {
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                continue;
            }

            $token = $node['token'];
            if ($label === '' && $token->text === '/') {
                $label = '/';
                continue;
            }

            if ($token->kind === 'identifier' || $token->kind === 'number' || $token->kind === 'string') {
                return (string) ($list['open'] ?? '') . $label . $token->text . (string) ($list['close'] ?? '');
            }
        }

        return $this->nodeText($list);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffListNode(array $old, array $new, string $path, array $options, array &$changes): void
    {
        if (($old['open'] ?? '') !== ($new['open'] ?? '') || ($old['close'] ?? '') !== ($new['close'] ?? '')) {
            if ($this->diffChangedOuterDelimiter($old, $new, $path, $changes)) {
                return;
            }

            $changes[] = ['op' => '~', 'path' => $path, 'old' => $this->nodeText($old), 'new' => $this->nodeText($new)];

            return;
        }

        $oldItems = $this->listItems($old, $options);
        $newItems = $this->listItems($new, $options);
        $oldSignatures = array_map(fn (array $item): string => $this->itemSignature($item, $options), $oldItems);
        $newSignatures = array_map(fn (array $item): string => $this->itemSignature($item, $options), $newItems);
        $table = $this->lcsTable($oldSignatures, $newSignatures);

        $i = 0;
        $j = 0;
        while ($i < count($oldItems) && $j < count($newItems)) {
            if ($oldSignatures[$i] === $newSignatures[$j]) {
                $oldText = $this->itemText($oldItems[$i]);
                $newText = $this->itemText($newItems[$j]);
                if ($oldText !== $newText) {
                    $nestedOld = $this->directLists($oldItems[$i]);
                    $nestedNew = $this->directLists($newItems[$j]);
                    if ($nestedOld !== [] || $nestedNew !== []) {
                        $this->diffNestedLists($nestedOld, $nestedNew, $path . '[' . $i . ']', $options, $changes);
                    } else {
                        $changes[] = ['op' => '~', 'path' => $path . '[' . $i . ']', 'old' => $oldText, 'new' => $newText];
                    }
                }
                $i++;
                $j++;
                continue;
            }

            $nestedSliderChanges = $this->nestedSliderChanges(
                $oldItems[$i],
                $newItems[$j],
                $path . '[' . $i . ']',
                $path . '[' . $j . ']',
                $options,
            );
            if ($nestedSliderChanges !== null) {
                foreach ($nestedSliderChanges as $change) {
                    $changes[] = $change;
                }
                $i++;
                $j++;
                continue;
            }

            if ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $changes[] = ['op' => '-', 'path' => $path . '[' . $i . ']', 'text' => $this->itemText($oldItems[$i])];
                $i++;
            } else {
                $changes[] = ['op' => '+', 'path' => $path . '[' . $j . ']', 'text' => $this->itemText($newItems[$j])];
                $j++;
            }
        }

        while ($i < count($oldItems)) {
            $changes[] = ['op' => '-', 'path' => $path . '[' . $i . ']', 'text' => $this->itemText($oldItems[$i])];
            $i++;
        }
        while ($j < count($newItems)) {
            $changes[] = ['op' => '+', 'path' => $path . '[' . $j . ']', 'text' => $this->itemText($newItems[$j])];
            $j++;
        }
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffChangedOuterDelimiter(array $old, array $new, string $path, array &$changes): bool
    {
        $oldAtoms = $this->flattenAtomTexts($old['children'] ?? []);
        $newAtoms = $this->flattenAtomTexts($new['children'] ?? []);
        if ($oldAtoms === [] || $oldAtoms !== $newAtoms) {
            return false;
        }

        $changes[] = [
            'op' => '~',
            'path' => $path . '/delimiters',
            'old' => (string) ($old['open'] ?? '') . (string) ($old['close'] ?? ''),
            'new' => (string) ($new['open'] ?? '') . (string) ($new['close'] ?? ''),
        ];

        foreach ($this->changedDirectWrappers($old['children'] ?? [], $new['children'] ?? []) as $index => $wrapper) {
            $changes[] = [
                'op' => '+',
                'path' => $path . '/wrap' . $index,
                'text' => $wrapper,
            ];
        }
        foreach ($this->changedDirectWrappers($new['children'] ?? [], $old['children'] ?? []) as $index => $wrapper) {
            $changes[] = [
                'op' => '-',
                'path' => $path . '/wrap' . $index,
                'text' => $wrapper,
            ];
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $sourceNodes
     * @param list<array<string, mixed>> $targetNodes
     * @return list<string>
     */
    private function changedDirectWrappers(array $sourceNodes, array $targetNodes): array
    {
        $sourceGroups = $this->directAtomGroups($sourceNodes);
        $targetGroups = $this->directAtomGroups($targetNodes);
        $wrappers = [];
        $sourceIndex = 0;

        foreach ($targetGroups as $targetGroup) {
            $covered = [];
            while ($sourceIndex < count($sourceGroups) && $this->groupAtomCount($covered) < count($targetGroup['atoms'])) {
                $covered[] = $sourceGroups[$sourceIndex];
                $sourceIndex++;
            }

            if (
                $targetGroup['type'] !== 'list'
                || $this->groupAtomTexts($covered) !== $targetGroup['atoms']
                || (count($covered) === 1 && ($covered[0]['type'] ?? '') === 'list' && ($covered[0]['wrapper'] ?? '') === $targetGroup['wrapper'])
            ) {
                continue;
            }

            $wrappers[] = $targetGroup['wrapper'];
        }

        return $wrappers;
    }

    /**
     * @param list<array{type:string, atoms:list<string>, wrapper:string}> $groups
     * @return list<string>
     */
    private function groupAtomTexts(array $groups): array
    {
        $atoms = [];
        foreach ($groups as $group) {
            foreach ($group['atoms'] as $atom) {
                $atoms[] = $atom;
            }
        }

        return $atoms;
    }

    /**
     * @param list<array{type:string, atoms:list<string>, wrapper:string}> $groups
     */
    private function groupAtomCount(array $groups): int
    {
        return count($this->groupAtomTexts($groups));
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{type:string, atoms:list<string>, wrapper:string}>
     */
    private function directAtomGroups(array $nodes): array
    {
        $groups = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'list') {
                $groups[] = [
                    'type' => 'list',
                    'atoms' => $this->flattenAtomTexts($node['children'] ?? []),
                    'wrapper' => (string) ($node['open'] ?? '') . '...' . (string) ($node['close'] ?? ''),
                ];
                continue;
            }

            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                $groups[] = [
                    'type' => 'atom',
                    'atoms' => [$node['token']->text],
                    'wrapper' => '',
                ];
            }
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<string>
     */
    private function flattenAtomTexts(array $nodes): array
    {
        $atoms = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                $atoms[] = $node['token']->text;
                continue;
            }

            if (($node['type'] ?? '') === 'list') {
                foreach ($this->flattenAtomTexts($node['children'] ?? []) as $atom) {
                    $atoms[] = $atom;
                }
            }
        }

        return $atoms;
    }

    /**
     * @param list<array<string, mixed>> $oldItem
     * @param list<array<string, mixed>> $newItem
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return ?list<array{op:string, path:string, text:string}>
     */
    private function nestedSliderChanges(array $oldItem, array $newItem, string $oldPath, string $newPath, array $options): ?array
    {
        if ($this->preferOuterDelimiter($options)) {
            $deletion = $this->outerWrapperChanges($oldItem, $newItem, '-', $oldPath, $options);
            if ($deletion !== null) {
                return $deletion;
            }

            $addition = $this->outerWrapperChanges($newItem, $oldItem, '+', $newPath, $options);
            if ($addition !== null) {
                return $addition;
            }
        }

        $addition = $this->wrapperChange($oldItem, $newItem, '+', $newPath, $options);
        if ($addition !== null) {
            return [$addition];
        }

        $deletion = $this->wrapperChange($newItem, $oldItem, '-', $oldPath, $options);
        if ($deletion !== null) {
            return [$deletion];
        }

        return null;
    }

    /**
     * @param array{language?: string} $options
     */
    private function preferOuterDelimiter(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), [
            'clojure',
            'common-lisp',
            'commonlisp',
            'elisp',
            'emacs-lisp',
            'hcl',
            'janet',
            'json',
            'newick',
            'racket',
            'scheme',
            'sql',
            'toml',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $wrapperItem
     * @param list<array<string, mixed>> $innerItem
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return ?list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    private function outerWrapperChanges(array $wrapperItem, array $innerItem, string $operation, string $path, array $options): ?array
    {
        $innerLead = $this->itemLeadSignature($innerItem);
        if ($innerLead === '') {
            return null;
        }

        $candidateIndex = 0;
        foreach ($wrapperItem as $nodeIndex => $node) {
            if (($node['type'] ?? '') !== 'list') {
                continue;
            }

            $items = $this->listItems($node, $options);
            if (count($items) !== 1 || $this->itemLeadSignature($items[0]) !== $innerLead) {
                $candidateIndex++;
                continue;
            }

            $wrapperPath = $path . '/wrap' . $candidateIndex;
            $changes = [[
                'op' => $operation,
                'path' => $wrapperPath,
                'text' => $this->wrapperText($wrapperItem, $nodeIndex),
            ]];
            $innerList = [
                'type' => 'list',
                'open' => $node['open'] ?? '',
                'close' => $node['close'] ?? '',
                'children' => $innerItem,
            ];
            $nestedChanges = [];

            if ($operation === '-') {
                $this->diffListNode($node, $innerList, $wrapperPath, $options, $nestedChanges);
            } else {
                $this->diffListNode($innerList, $node, $wrapperPath, $options, $nestedChanges);
            }

            foreach ($nestedChanges as $change) {
                $changes[] = $change;
            }

            return $changes;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function itemLeadSignature(array $item): string
    {
        $signature = '';
        foreach ($item as $node) {
            if (($node['type'] ?? '') === 'list') {
                break;
            }
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                return '';
            }

            $signature .= $node['token']->text;
        }

        return $signature;
    }

    /**
     * @param list<array<string, mixed>> $innerItem
     * @param list<array<string, mixed>> $wrapperItem
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return ?array{op:string, path:string, text:string}
     */
    private function wrapperChange(array $innerItem, array $wrapperItem, string $operation, string $path, array $options): ?array
    {
        $innerText = $this->itemText($innerItem);
        $innerSignature = $this->itemSignature($innerItem, $options);
        $candidateIndex = 0;

        foreach ($wrapperItem as $nodeIndex => $node) {
            if (($node['type'] ?? '') !== 'list') {
                continue;
            }

            $items = $this->listItems($node, $options);
            if (count($items) === 1 && $this->itemText($items[0]) === $innerText && $this->itemSignature($items[0], $options) === $innerSignature) {
                return [
                    'op' => $operation,
                    'path' => $path . '/wrap' . $candidateIndex,
                    'text' => $this->wrapperText($wrapperItem, $nodeIndex),
                ];
            }

            $candidateIndex++;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $wrapperItem
     */
    private function wrapperText(array $wrapperItem, int $innerListIndex): string
    {
        $parts = [];
        foreach ($wrapperItem as $index => $node) {
            if ($index === $innerListIndex) {
                $parts[] = (string) ($node['open'] ?? '') . '...' . (string) ($node['close'] ?? '');
                continue;
            }

            $parts[] = $this->nodeText($node);
        }

        return implode('', $parts);
    }

    /**
     * @param list<array<string, mixed>> $oldLists
     * @param list<array<string, mixed>> $newLists
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffNestedLists(array $oldLists, array $newLists, string $path, array $options, array &$changes): void
    {
        $pairs = min(count($oldLists), count($newLists));
        for ($index = 0; $index < $pairs; $index++) {
            $this->diffListNode($oldLists[$index], $newLists[$index], $path . '/' . $oldLists[$index]['open'] . $index . $oldLists[$index]['close'], $options, $changes);
        }
        for ($index = $pairs; $index < count($oldLists); $index++) {
            $changes[] = ['op' => '-', 'path' => $path . '/' . $oldLists[$index]['open'] . $index . $oldLists[$index]['close'], 'text' => $this->nodeText($oldLists[$index])];
        }
        for ($index = $pairs; $index < count($newLists); $index++) {
            $changes[] = ['op' => '+', 'path' => $path . '/' . $newLists[$index]['open'] . $index . $newLists[$index]['close'], 'text' => $this->nodeText($newLists[$index])];
        }
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffCssRules(array $oldNodes, array $newNodes, array $options, array &$changes, string $basePath = '$css'): void
    {
        $newRules = $this->cssRules($newNodes);
        $newBySignature = [];
        foreach ($newRules as $index => $rule) {
            $newBySignature[$rule['signature']][] = $index;
        }

        $matchedNewIndexes = [];
        foreach ($this->cssRules($oldNodes) as $oldRule) {
            $signature = $oldRule['signature'];
            $matchIndex = null;
            foreach ($newBySignature[$signature] ?? [] as $candidateIndex) {
                if (($matchedNewIndexes[$candidateIndex] ?? false) === false) {
                    $matchIndex = $candidateIndex;
                    break;
                }
            }

            if ($matchIndex === null) {
                $changes[] = [
                    'op' => '-',
                    'path' => $this->cssRulePath($signature, $basePath),
                    'text' => $this->cssRuleText($oldRule),
                ];
                continue;
            }

            $matchedNewIndexes[$matchIndex] = true;
            $newRule = $newRules[$matchIndex];
            $rulePath = $this->cssRulePath($signature, $basePath);
            if ($oldRule['selector'] !== $newRule['selector']) {
                $changes[] = [
                    'op' => '~',
                    'path' => $rulePath . '/selector',
                    'old' => $oldRule['selector'],
                    'new' => $newRule['selector'],
                ];
            }
            if ($this->nodeText($oldRule['list']) !== $this->nodeText($newRule['list'])) {
                if (
                    ($this->isNestedCssAtRuleContainer($oldRule) || $this->isNestedCssAtRuleContainer($newRule))
                    && ($this->hasDirectCssRules($oldRule['list']) || $this->hasDirectCssRules($newRule['list']))
                ) {
                    $this->diffCssRules($oldRule['list']['children'] ?? [], $newRule['list']['children'] ?? [], $options, $changes, $rulePath);
                } else {
                    $this->diffListNode(
                        $oldRule['list'],
                        $newRule['list'],
                        $rulePath,
                        $options,
                        $changes,
                    );
                }
            }
        }

        foreach ($newRules as $index => $rule) {
            if (($matchedNewIndexes[$index] ?? false) === true) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $this->cssRulePath($rule['signature'], $basePath),
                'text' => $this->cssRuleText($rule),
            ];
        }
    }

    /**
     * @param array<string, mixed> $list
     */
    private function hasDirectCssRules(array $list): bool
    {
        return $this->cssRules($list['children'] ?? []) !== [];
    }

    /**
     * @param array{selector:string, signature:string, list:array<string, mixed>} $rule
     */
    private function isNestedCssAtRuleContainer(array $rule): bool
    {
        return in_array($rule['signature'], ['@media', '@supports'], true);
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffHtmlStyleSubLanguage(string $old, string $new, array &$changes): void
    {
        $oldBlocks = $this->htmlRawBlocks($old, 'style');
        $newBlocks = $this->htmlRawBlocks($new, 'style');
        if ($oldBlocks === [] && $newBlocks === []) {
            return;
        }

        $cssOptions = ['language' => 'css'];
        $multiBlock = count($oldBlocks) > 1 || count($newBlocks) > 1;
        foreach ($this->pairedHtmlRawBlocks($oldBlocks, $newBlocks, $cssOptions) as $pair) {
            $oldBlock = $pair['old'] ?? [];
            $newBlock = $pair['new'] ?? [];
            $blockIndex = $newBlock['index'] ?? $oldBlock['index'] ?? 0;
            $basePath = $multiBlock ? '$html.style[' . $blockIndex . '].css' : '$html.style.css';
            $delimiterPairs = $this->delimiterPairs($cssOptions);
            $oldRoot = $this->parseTokenTree($this->tokensForDiff($oldBlock['body'] ?? '', $cssOptions), $delimiterPairs);
            $newRoot = $this->parseTokenTree($this->tokensForDiff($newBlock['body'] ?? '', $cssOptions), $delimiterPairs);

            $this->diffCssRules($oldRoot['children'], $newRoot['children'], $cssOptions, $changes, $basePath);
        }
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffHtmlScriptSubLanguage(string $old, string $new, array &$changes): void
    {
        $oldBlocks = $this->htmlRawBlocks($old, 'script');
        $newBlocks = $this->htmlRawBlocks($new, 'script');
        if ($oldBlocks === [] && $newBlocks === []) {
            return;
        }

        $jsOptions = ['language' => 'javascript'];
        $multiBlock = count($oldBlocks) > 1 || count($newBlocks) > 1;
        foreach ($this->pairedHtmlRawBlocks($oldBlocks, $newBlocks, $jsOptions) as $pair) {
            $oldBlock = $pair['old'] ?? [];
            $newBlock = $pair['new'] ?? [];
            $blockIndex = $newBlock['index'] ?? $oldBlock['index'] ?? 0;
            $basePath = $multiBlock ? '$html.script[' . $blockIndex . '].js' : '$html.script.js';
            $delimiterPairs = $this->delimiterPairs($jsOptions);
            $oldRoot = $this->parseTokenTree($this->tokensForDiff($oldBlock['body'] ?? '', $jsOptions), $delimiterPairs);
            $newRoot = $this->parseTokenTree($this->tokensForDiff($newBlock['body'] ?? '', $jsOptions), $delimiterPairs);

            $this->diffJavaScriptStatementSyntax($oldRoot['children'], $newRoot['children'], $jsOptions, $changes, $basePath);
        }
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffJavaScriptStatementSyntax(array $oldNodes, array $newNodes, array $options, array &$changes, string $basePath): void
    {
        $this->diffJavaScriptBlockWrappers($oldNodes, $newNodes, $basePath, $changes);
        $this->diffJavaScriptCalls($oldNodes, $newNodes, $options, $changes, $basePath);
        $this->diffJavaScriptArrays($oldNodes, $newNodes, $options, $changes, $basePath);
        if ($this->isTypeScriptLanguage($options)) {
            $this->diffTypeScriptDeclarations($oldNodes, $newNodes, $options, $changes);
            $this->diffTypeScriptModuleDeclarations($oldNodes, $newNodes, $options, $changes);
        }
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffTypeScriptDeclarations(array $oldNodes, array $newNodes, array $options, array &$changes): void
    {
        $oldDeclarations = $this->typeScriptObjectDeclarations($oldNodes);
        $newDeclarations = $this->typeScriptObjectDeclarations($newNodes);
        $matchedNewKeys = [];

        foreach ($oldDeclarations as $key => $oldDeclaration) {
            $path = $this->typeScriptDeclarationPath($oldDeclaration);
            if (!isset($newDeclarations[$key])) {
                $changes[] = ['op' => '-', 'path' => $path, 'text' => $oldDeclaration['text']];
                continue;
            }

            $matchedNewKeys[$key] = true;
            $newDeclaration = $newDeclarations[$key];
            if ($this->nodeText($oldDeclaration['list']) !== $this->nodeText($newDeclaration['list'])) {
                $this->diffListNode($oldDeclaration['list'], $newDeclaration['list'], $path, $options, $changes);
            }
        }

        foreach ($newDeclarations as $key => $newDeclaration) {
            if (($matchedNewKeys[$key] ?? false) === true) {
                continue;
            }

            $changes[] = ['op' => '+', 'path' => $this->typeScriptDeclarationPath($newDeclaration), 'text' => $newDeclaration['text']];
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return array<string, array{kind:string, name:string, list:array<string, mixed>, text:string}>
     */
    private function typeScriptObjectDeclarations(array $nodes): array
    {
        $declarations = [];
        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? '') !== 'list' || ($node['open'] ?? '') !== '{') {
                continue;
            }

            $context = $this->typeScriptDeclarationContext($nodes, $index);
            if ($context === null) {
                continue;
            }

            $key = $context['kind'] . ':' . $context['name'];
            $declarations[$key] = [
                'kind' => $context['kind'],
                'name' => $context['name'],
                'list' => $node,
                'text' => $context['prefix'] . $this->nodeText($node),
            ];
        }

        return $declarations;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return ?array{kind:string, name:string, prefix:string}
     */
    private function typeScriptDeclarationContext(array $nodes, int $listIndex): ?array
    {
        $atoms = [];
        for ($index = 0; $index < $listIndex; $index++) {
            $node = $nodes[$index];
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                $atoms = [];
                continue;
            }

            $token = $node['token'];
            if ($token->kind === 'comment') {
                continue;
            }
            if ($token->text === ';') {
                $atoms = [];
                continue;
            }

            $atoms[] = $token->text;
        }

        $interfaceIndex = array_search('interface', $atoms, true);
        if ($interfaceIndex !== false) {
            $name = $this->nextIdentifier($atoms, $interfaceIndex + 1);
            if ($name !== null) {
                return [
                    'kind' => 'interface',
                    'name' => $name,
                    'prefix' => 'interface' . $name,
                ];
            }
        }

        $equalsIndex = array_search('=', $atoms, true);
        $typeIndex = array_search('type', $atoms, true);
        if ($equalsIndex === false || $typeIndex === false || $typeIndex > $equalsIndex) {
            return null;
        }

        $name = $this->nextIdentifier(array_slice($atoms, 0, $equalsIndex), $typeIndex + 1);
        if ($name === null) {
            return null;
        }

        return [
            'kind' => 'type',
            'name' => $name,
            'prefix' => 'type' . $name . '=',
        ];
    }

    /**
     * @param list<string> $atoms
     */
    private function nextIdentifier(array $atoms, int $start): ?string
    {
        for ($index = $start; $index < count($atoms); $index++) {
            $atom = $atoms[$index];
            if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $atom) === 1) {
                return $atom;
            }
        }

        return null;
    }

    /**
     * @param array{kind:string, name:string, list:array<string, mixed>, text:string} $declaration
     */
    private function typeScriptDeclarationPath(array $declaration): string
    {
        return '$ts.' . $declaration['kind'] . '[' . json_encode($declaration['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffTypeScriptModuleDeclarations(array $oldNodes, array $newNodes, array $options, array &$changes): void
    {
        $oldDeclarations = $this->typeScriptModuleDeclarations($oldNodes);
        $newDeclarations = $this->typeScriptModuleDeclarations($newNodes);
        $matchedNewKeys = [];

        foreach ($oldDeclarations as $key => $oldDeclaration) {
            $matchKey = isset($newDeclarations[$key]) && (($matchedNewKeys[$key] ?? false) === false)
                ? $key
                : $this->typeScriptModuleFallbackMatch($oldDeclaration, $newDeclarations, $matchedNewKeys);

            if ($matchKey === null) {
                if (!$this->isEmptySyntheticTypeScriptModuleDeclaration($oldDeclaration)) {
                    $changes[] = [
                        'op' => '-',
                        'path' => $this->typeScriptModuleDeclarationPath($oldDeclaration),
                        'text' => $oldDeclaration['text'],
                    ];
                }
                continue;
            }

            $matchedNewKeys[$matchKey] = true;
            $this->diffTypeScriptModuleDeclarationPair(
                $oldDeclaration,
                $newDeclarations[$matchKey],
                $options,
                $changes,
            );
        }

        foreach ($newDeclarations as $key => $newDeclaration) {
            if (($matchedNewKeys[$key] ?? false) === true) {
                continue;
            }
            if ($this->isEmptySyntheticTypeScriptModuleDeclaration($newDeclaration)) {
                continue;
            }

            $changes[] = ['op' => '+', 'path' => $this->typeScriptModuleDeclarationPath($newDeclaration), 'text' => $newDeclaration['text']];
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return array<string, array<string, mixed>>
     */
    private function typeScriptModuleDeclarations(array $nodes): array
    {
        $declarations = [];
        foreach ($this->topLevelStatements($nodes) as $statement) {
            foreach ($this->typeScriptModuleDeclarationsFromStatement($statement) as $declaration) {
                $keyBase = $this->typeScriptModuleDeclarationKey($declaration);
                $key = $keyBase;
                $index = 1;
                while (isset($declarations[$key])) {
                    $key = $keyBase . "\0" . $index;
                    $index++;
                }

                $declarations[$key] = $declaration;
            }
        }
        foreach ($this->typeScriptDynamicImportDeclarations($nodes) as $declaration) {
            $keyBase = $this->typeScriptModuleDeclarationKey($declaration);
            $key = $keyBase;
            $index = 1;
            while (isset($declarations[$key])) {
                $key = $keyBase . "\0" . $index;
                $index++;
            }

            $declarations[$key] = $declaration;
        }

        return $declarations;
    }

    /**
     * @param list<array<string, mixed>> $statement
     * @return list<array<string, mixed>>
     */
    private function typeScriptModuleDeclarationsFromStatement(array $statement): array
    {
        $atoms = $this->statementAtomTexts($statement);
        $first = $this->firstNonCommentAtom($statement);
        if ($first === null || !in_array($first, ['import', 'export'], true)) {
            return [];
        }

        $modifier = null;
        $startIndex = array_search($first, $atoms, true);
        if ($startIndex !== false && ($atoms[$startIndex + 1] ?? null) === 'type') {
            $modifier = 'type';
        }

        $source = $this->typeScriptModuleSource($statement, $atoms, $first);
        $list = $this->firstNamedModuleList($statement);
        $attributes = $source === null ? null : $this->typeScriptModuleAttributeList($statement);
        $text = $this->statementText($statement);
        $declarations = [];

        if ($first === 'import') {
            if ($source === null) {
                return [];
            }

            $defaultName = $this->typeScriptDefaultImportName($statement, $modifier !== null);
            if ($defaultName !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord(
                    'import',
                    $modifier,
                    $source,
                    'default',
                    null,
                    $defaultName,
                    $defaultName,
                );
            }

            $namespaceName = $this->typeScriptNamespaceImportName($atoms);
            if ($namespaceName !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord(
                    'import',
                    $modifier,
                    $source,
                    'namespace',
                    null,
                    $namespaceName,
                    $namespaceName,
                );
            }

            if ($list !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord('import', $modifier, $source, 'named', $list, null, $text);
            } elseif ($defaultName !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord(
                    'import',
                    $modifier,
                    $source,
                    'named',
                    $this->emptyTypeScriptNamedModuleList(),
                    null,
                    '',
                    true,
                );
            }

            if ($declarations === []) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord('import', $modifier, $source, 'side-effect', null, null, $text);
            }
            if ($attributes !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord(
                    'import',
                    $modifier,
                    $source,
                    'attributes',
                    $attributes['list'],
                    $attributes['keyword'],
                    $attributes['keyword'] . $this->nodeText($attributes['list']),
                );
            }

            return $declarations;
        }

        if ($list !== null) {
            if ($source === null && !$this->startsWithNamedExportList($statement)) {
                return [];
            }

            $declarations[] = $this->typeScriptModuleDeclarationRecord('export', $modifier, $source, 'named', $list, null, $text);
            if ($attributes !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord(
                    'export',
                    $modifier,
                    $source,
                    'attributes',
                    $attributes['list'],
                    $attributes['keyword'],
                    $attributes['keyword'] . $this->nodeText($attributes['list']),
                );
            }

            return $declarations;
        }

        if ($source !== null && in_array('*', $atoms, true)) {
            $namespaceName = $this->typeScriptNamespaceImportName($atoms);
            $declarations[] = $this->typeScriptModuleDeclarationRecord(
                'export',
                $modifier,
                $source,
                $namespaceName === null ? 'star' : 'namespace',
                null,
                $namespaceName ?? '*',
                $namespaceName ?? '*',
            );
            if ($attributes !== null) {
                $declarations[] = $this->typeScriptModuleDeclarationRecord(
                    'export',
                    $modifier,
                    $source,
                    'attributes',
                    $attributes['list'],
                    $attributes['keyword'],
                    $attributes['keyword'] . $this->nodeText($attributes['list']),
                );
            }

            return $declarations;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeScriptModuleDeclarationRecord(
        string $kind,
        ?string $modifier,
        ?string $source,
        string $variant,
        ?array $list,
        ?string $name,
        string $text,
        bool $synthetic = false,
    ): array {
        $specifierTexts = $list === null ? [] : array_map(
            fn (array $item): string => $this->itemText($item),
            $this->listItems($list, ['language' => 'typescript']),
        );

        return [
            'kind' => $kind,
            'modifier' => $modifier,
            'source' => $source,
            'variant' => $variant,
            'list' => $list,
            'name' => $name,
            'text' => $text,
            'synthetic' => $synthetic,
            'specifierSignature' => implode("\0", $specifierTexts),
            'specifierLabel' => implode(',', $specifierTexts),
        ];
    }

    /**
     * @return array{type:string, open:string, close:string, children:list<array<string, mixed>>}
     */
    private function emptyTypeScriptNamedModuleList(): array
    {
        return [
            'type' => 'list',
            'open' => '{',
            'close' => '}',
            'children' => [],
        ];
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private function typeScriptModuleDeclarationKey(array $declaration): string
    {
        return implode("\0", [
            $declaration['kind'],
            $declaration['modifier'] ?? '',
            $declaration['variant'],
            $declaration['source'] ?? 'local',
        ]);
    }

    /**
     * @param array<string, mixed> $oldDeclaration
     * @param array<string, array<string, mixed>> $newDeclarations
     * @param array<string, bool> $matchedNewKeys
     */
    private function typeScriptModuleFallbackMatch(array $oldDeclaration, array $newDeclarations, array $matchedNewKeys): ?string
    {
        $fallbackKey = $this->typeScriptModuleFallbackKey($oldDeclaration);
        if ($fallbackKey === null) {
            return null;
        }

        foreach ($newDeclarations as $key => $newDeclaration) {
            if (($matchedNewKeys[$key] ?? false) === true) {
                continue;
            }
            if ($this->typeScriptModuleFallbackKey($newDeclaration) === $fallbackKey) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private function typeScriptModuleFallbackKey(array $declaration): ?string
    {
        if (
            $declaration['kind'] !== 'export'
            || $declaration['source'] === null
        ) {
            return null;
        }

        if ($declaration['variant'] === 'named' && ($declaration['specifierSignature'] ?? '') !== '') {
            return implode("\0", [
                $declaration['kind'],
                $declaration['modifier'] ?? '',
                $declaration['variant'],
                $declaration['specifierSignature'],
            ]);
        }

        if ($declaration['variant'] === 'namespace' && ($declaration['name'] ?? '') !== '') {
            return implode("\0", [
                $declaration['kind'],
                $declaration['modifier'] ?? '',
                $declaration['variant'],
                $declaration['name'],
            ]);
        }

        if ($declaration['variant'] === 'star') {
            return implode("\0", [
                $declaration['kind'],
                $declaration['modifier'] ?? '',
                $declaration['variant'],
            ]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $oldDeclaration
     * @param array<string, mixed> $newDeclaration
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffTypeScriptModuleDeclarationPair(array $oldDeclaration, array $newDeclaration, array $options, array &$changes): void
    {
        $path = $this->typeScriptModuleDeclarationPath($newDeclaration);
        if ($oldDeclaration['source'] !== $newDeclaration['source']) {
            $changes[] = [
                'op' => '~',
                'path' => $this->typeScriptModuleSourceChangePath($newDeclaration),
                'old' => $this->quotedTypeScriptModuleSource($oldDeclaration['source']),
                'new' => $this->quotedTypeScriptModuleSource($newDeclaration['source']),
            ];
        }

        if (in_array($newDeclaration['variant'], ['default', 'namespace'], true)) {
            if ($oldDeclaration['name'] !== $newDeclaration['name']) {
                $changes[] = [
                    'op' => '~',
                    'path' => $path,
                    'old' => (string) $oldDeclaration['name'],
                    'new' => (string) $newDeclaration['name'],
                ];
            }

            return;
        }

        if (in_array($newDeclaration['variant'], ['attributes', 'dynamic-attributes'], true)) {
            if ($oldDeclaration['name'] !== $newDeclaration['name']) {
                $changes[] = [
                    'op' => '~',
                    'path' => $path . '/keyword',
                    'old' => (string) $oldDeclaration['name'],
                    'new' => (string) $newDeclaration['name'],
                ];
            }
            if (($oldDeclaration['list'] ?? null) !== null && ($newDeclaration['list'] ?? null) !== null) {
                if ($this->nodeText($oldDeclaration['list']) !== $this->nodeText($newDeclaration['list'])) {
                    $this->diffListNode($oldDeclaration['list'], $newDeclaration['list'], $path, $options, $changes);
                }
            }

            return;
        }

        if (($oldDeclaration['list'] ?? null) !== null && ($newDeclaration['list'] ?? null) !== null) {
            if ($this->nodeText($oldDeclaration['list']) !== $this->nodeText($newDeclaration['list'])) {
                $this->diffListNode($oldDeclaration['list'], $newDeclaration['list'], $path, $options, $changes);
            }

            return;
        }

        if ($oldDeclaration['text'] !== $newDeclaration['text'] && $oldDeclaration['source'] === $newDeclaration['source']) {
            $changes[] = [
                'op' => '~',
                'path' => $path,
                'old' => $oldDeclaration['text'],
                'new' => $newDeclaration['text'],
            ];
        }
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private function isEmptySyntheticTypeScriptModuleDeclaration(array $declaration): bool
    {
        return ($declaration['synthetic'] ?? false) === true
            && ($declaration['specifierSignature'] ?? '') === '';
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function typeScriptDynamicImportDeclarations(array $nodes): array
    {
        $declarations = [];
        $this->collectTypeScriptDynamicImportDeclarations($nodes, $declarations);

        return $declarations;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $declarations
     */
    private function collectTypeScriptDynamicImportDeclarations(array $nodes, array &$declarations): void
    {
        $count = count($nodes);
        for ($index = 0; $index < $count; $index++) {
            $node = $nodes[$index];
            if (
                ($node['type'] ?? '') === 'atom'
                && $node['token'] instanceof Token
                && $node['token']->text === 'import'
            ) {
                $callList = $this->nextNonCommentList($nodes, $index + 1);
                if ($callList !== null && ($callList['open'] ?? '') === '(') {
                    $declaration = $this->typeScriptDynamicImportDeclaration($callList);
                    if ($declaration !== null) {
                        $declarations[] = $declaration;
                    }
                }
            }

            if (($node['type'] ?? '') === 'list') {
                $this->collectTypeScriptDynamicImportDeclarations($node['children'] ?? [], $declarations);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return ?array<string, mixed>
     */
    private function nextNonCommentList(array $nodes, int $offset): ?array
    {
        for ($index = $offset; $index < count($nodes); $index++) {
            $node = $nodes[$index];
            if (
                ($node['type'] ?? '') === 'atom'
                && $node['token'] instanceof Token
                && $node['token']->kind === 'comment'
            ) {
                continue;
            }

            return ($node['type'] ?? '') === 'list' ? $node : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $callList
     * @return ?array<string, mixed>
     */
    private function typeScriptDynamicImportDeclaration(array $callList): ?array
    {
        $items = $this->listItems($callList, ['language' => 'typescript']);
        if (count($items) < 2) {
            return null;
        }

        $sourceText = $this->itemText($items[0]);
        if (!$this->isQuotedAtom($sourceText)) {
            return null;
        }

        $attributes = $this->typeScriptDynamicImportAttributeList($items[1]);
        if ($attributes === null) {
            return null;
        }

        return $this->typeScriptModuleDeclarationRecord(
            'import',
            null,
            $this->unquoteAtom($sourceText),
            'dynamic-attributes',
            $attributes['list'],
            $attributes['keyword'],
            $attributes['keyword'] . $this->nodeText($attributes['list']),
        );
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return ?array{keyword:string, list:array<string, mixed>}
     */
    private function typeScriptDynamicImportAttributeList(array $nodes): ?array
    {
        foreach ($this->directLists($nodes) as $optionsList) {
            if (($optionsList['open'] ?? '') !== '{') {
                continue;
            }

            $keyword = null;
            foreach ($optionsList['children'] ?? [] as $child) {
                if (($child['type'] ?? '') === 'atom' && $child['token'] instanceof Token) {
                    if ($child['token']->kind === 'comment') {
                        continue;
                    }

                    $text = $child['token']->text;
                    if (in_array($text, ['assert', 'with'], true)) {
                        $keyword = $text;
                        continue;
                    }
                    if ($text === ':' && $keyword !== null) {
                        continue;
                    }
                    if ($text === ',') {
                        $keyword = null;
                    }
                    continue;
                }

                if (($child['type'] ?? '') === 'list' && ($child['open'] ?? '') === '{' && $keyword !== null) {
                    return [
                        'keyword' => $keyword,
                        'list' => $child,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $statement
     */
    private function typeScriptDefaultImportName(array $statement, bool $hasImportTypeModifier): ?string
    {
        $seenImport = false;
        $skippedTypeModifier = false;
        foreach ($statement as $node) {
            if (($node['type'] ?? '') === 'list') {
                return null;
            }
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                continue;
            }

            $token = $node['token'];
            if ($token->kind === 'comment') {
                continue;
            }
            if (!$seenImport) {
                if ($token->text !== 'import') {
                    continue;
                }
                $seenImport = true;
                continue;
            }
            if ($hasImportTypeModifier && !$skippedTypeModifier && $token->text === 'type') {
                $skippedTypeModifier = true;
                continue;
            }
            if (in_array($token->text, ['from', '*', ','], true)) {
                return null;
            }

            return $this->isTypeScriptIdentifierAtom($token->text) ? $token->text : null;
        }

        return null;
    }

    /**
     * @param list<string> $atoms
     */
    private function typeScriptNamespaceImportName(array $atoms): ?string
    {
        $firstIndex = null;
        foreach ($atoms as $index => $atom) {
            if ($atom === 'import' || $atom === 'export') {
                $firstIndex = $index;
                break;
            }
        }
        if ($firstIndex === null) {
            return null;
        }

        $index = $firstIndex + 1;
        if (($atoms[$index] ?? null) === 'type') {
            $index++;
        }
        if (($atoms[$index] ?? null) !== '*' || ($atoms[$index + 1] ?? null) !== 'as') {
            return null;
        }

        $name = $atoms[$index + 2] ?? null;
        return is_string($name) && $this->isTypeScriptIdentifierAtom($name) ? $name : null;
    }

    private function isTypeScriptIdentifierAtom(string $text): bool
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $text) === 1
            && !in_array($text, ['as', 'from', 'import', 'export', 'type'], true);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<list<array<string, mixed>>>
     */
    private function topLevelStatements(array $nodes): array
    {
        $statements = [];
        $current = [];
        foreach ($nodes as $node) {
            $current[] = $node;
            if (
                ($node['type'] ?? '') === 'atom'
                && $node['token'] instanceof Token
                && $node['token']->text === ';'
            ) {
                $statements[] = $current;
                $current = [];
            }
        }

        if ($current !== []) {
            $statements[] = $current;
        }

        return $statements;
    }

    /**
     * @param list<array<string, mixed>> $statement
     * @return list<string>
     */
    private function statementAtomTexts(array $statement): array
    {
        $atoms = [];
        foreach ($statement as $node) {
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                continue;
            }
            if ($node['token']->kind === 'comment') {
                continue;
            }

            $atoms[] = $node['token']->text;
        }

        return $atoms;
    }

    /**
     * @param list<array<string, mixed>> $statement
     */
    private function firstNonCommentAtom(array $statement): ?string
    {
        foreach ($statement as $node) {
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                continue;
            }
            if ($node['token']->kind === 'comment') {
                continue;
            }

            return $node['token']->text;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $statement
     * @param list<string> $atoms
     */
    private function typeScriptModuleSource(array $statement, array $atoms, string $kind): ?string
    {
        foreach ($atoms as $index => $atom) {
            if ($atom === 'from' && isset($atoms[$index + 1]) && $this->isQuotedAtom($atoms[$index + 1])) {
                return $this->unquoteAtom($atoms[$index + 1]);
            }
        }

        if ($kind === 'import') {
            foreach ($statement as $node) {
                if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token && $node['token']->kind === 'string') {
                    return $this->unquoteAtom($node['token']->text);
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $statement
     */
    private function startsWithNamedExportList(array $statement): bool
    {
        $seenExport = false;
        foreach ($statement as $node) {
            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                if ($node['token']->kind === 'comment') {
                    continue;
                }
                if (!$seenExport) {
                    if ($node['token']->text !== 'export') {
                        return false;
                    }
                    $seenExport = true;
                    continue;
                }
                if ($node['token']->text === 'type') {
                    continue;
                }

                return false;
            }

            if (($node['type'] ?? '') === 'list') {
                return $seenExport && ($node['open'] ?? '') === '{';
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $statement
     * @return ?array<string, mixed>
     */
    private function firstNamedModuleList(array $statement): ?array
    {
        $kind = $this->firstNonCommentAtom($statement);
        if ($kind === null || !in_array($kind, ['import', 'export'], true)) {
            return null;
        }

        $seenFrom = false;
        $previousAtom = null;
        foreach ($statement as $node) {
            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                if ($node['token']->kind === 'comment') {
                    continue;
                }

                $previousAtom = $node['token']->text;
                if ($previousAtom === 'from') {
                    $seenFrom = true;
                }
                continue;
            }

            if (($node['type'] ?? '') === 'list' && ($node['open'] ?? '') === '{') {
                if (in_array($previousAtom, ['assert', 'with'], true)) {
                    continue;
                }
                if ($kind === 'import' && $seenFrom) {
                    continue;
                }

                return $node;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $statement
     * @return ?array{keyword:string, list:array<string, mixed>}
     */
    private function typeScriptModuleAttributeList(array $statement): ?array
    {
        $keyword = null;
        foreach ($statement as $node) {
            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                if ($node['token']->kind === 'comment') {
                    continue;
                }

                $text = $node['token']->text;
                if (in_array($text, ['assert', 'with'], true)) {
                    $keyword = $text;
                    continue;
                }
                if ($keyword !== null) {
                    $keyword = null;
                }
                continue;
            }

            if (($node['type'] ?? '') === 'list' && ($node['open'] ?? '') === '{' && $keyword !== null) {
                return [
                    'keyword' => $keyword,
                    'list' => $node,
                ];
            }
        }

        return null;
    }

    private function isQuotedAtom(string $text): bool
    {
        if (strlen($text) < 2) {
            return false;
        }

        return ($text[0] === '"' || $text[0] === "'") && str_ends_with($text, $text[0]);
    }

    private function unquoteAtom(string $text): string
    {
        if (!$this->isQuotedAtom($text)) {
            return $text;
        }

        return stripcslashes(substr($text, 1, -1));
    }

    /**
     * @param list<array<string, mixed>> $statement
     */
    private function statementText(array $statement): string
    {
        return implode('', array_map(fn (array $node): string => $this->nodeText($node), $statement));
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private function typeScriptModuleDeclarationPath(array $declaration): string
    {
        $path = '$ts.' . $declaration['kind'];
        if ($declaration['modifier'] !== null) {
            $path .= '.' . $declaration['modifier'];
        }
        if ($declaration['variant'] === 'default') {
            $path .= '.default';
        } elseif ($declaration['variant'] === 'namespace') {
            $path .= '.namespace';
        } elseif ($declaration['variant'] === 'side-effect') {
            $path .= '.side_effect';
        } elseif ($declaration['variant'] === 'star') {
            $path .= '.star';
        } elseif ($declaration['variant'] === 'attributes') {
            $path .= '.attributes';
        } elseif ($declaration['variant'] === 'dynamic-attributes') {
            $path .= '.dynamic.attributes';
        }
        if ($declaration['source'] === null) {
            return $path . '.local';
        }

        return $path . '[' . json_encode($declaration['source'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private function typeScriptModuleSourceChangePath(array $declaration): string
    {
        $path = '$ts.' . $declaration['kind'];
        if ($declaration['modifier'] !== null) {
            $path .= '.' . $declaration['modifier'];
        }

        $label = (string) (($declaration['specifierLabel'] ?? '') !== ''
            ? $declaration['specifierLabel']
            : ($declaration['name'] ?? $declaration['text']));

        return $path . '.source[' . json_encode($label, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
    }

    private function quotedTypeScriptModuleSource(?string $source): string
    {
        return json_encode((string) $source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffJavaScriptBlockWrappers(array $oldNodes, array $newNodes, string $basePath, array &$changes): void
    {
        $oldBlocks = $this->javaScriptControlBlocks($oldNodes);
        $newBlocks = $this->javaScriptControlBlocks($newNodes);
        $oldBlockSignatures = $this->countJavaScriptBlockSignatures($oldBlocks);
        $newBlockSignatures = $this->countJavaScriptBlockSignatures($newBlocks);
        $oldTopLevelCalls = $this->javaScriptDirectCallNames($oldNodes);
        $newTopLevelCalls = $this->javaScriptDirectCallNames($newNodes);

        foreach ($newBlocks as $block) {
            $signature = $block['signature'];
            if (($oldBlockSignatures[$signature] ?? 0) > 0) {
                $oldBlockSignatures[$signature]--;
                continue;
            }

            if ($block['callNames'] === [] || !$this->containsContiguousSubsequence($oldTopLevelCalls, $block['callNames'])) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $this->javaScriptBlockPath($block['keyword'], $basePath, $block['index']),
                'text' => $block['text'],
            ];
        }

        foreach ($oldBlocks as $block) {
            $signature = $block['signature'];
            if (($newBlockSignatures[$signature] ?? 0) > 0) {
                $newBlockSignatures[$signature]--;
                continue;
            }

            if ($block['callNames'] === [] || !$this->containsContiguousSubsequence($newTopLevelCalls, $block['callNames'])) {
                continue;
            }

            $changes[] = [
                'op' => '-',
                'path' => $this->javaScriptBlockPath($block['keyword'], $basePath, $block['index']),
                'text' => $block['text'],
            ];
        }
    }

    /**
     * @param list<array{signature:string}> $blocks
     * @return array<string, int>
     */
    private function countJavaScriptBlockSignatures(array $blocks): array
    {
        $counts = [];
        foreach ($blocks as $block) {
            $signature = $block['signature'];
            $counts[$signature] = ($counts[$signature] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $needle
     */
    private function containsContiguousSubsequence(array $haystack, array $needle): bool
    {
        $needleCount = count($needle);
        if ($needleCount === 0 || $needleCount > count($haystack)) {
            return false;
        }

        for ($offset = 0; $offset <= count($haystack) - $needleCount; $offset++) {
            if (array_slice($haystack, $offset, $needleCount) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{keyword:string, index:int, signature:string, callNames:list<string>, text:string}>
     */
    private function javaScriptControlBlocks(array $nodes): array
    {
        $blocks = [];
        $keywordIndexes = [];
        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? '') === 'list') {
                if (($node['open'] ?? '') === '{') {
                    $context = $this->javaScriptControlBlockContext($nodes, $index);
                    if ($context !== null) {
                        $keyword = $context['keyword'];
                        $blockIndex = $keywordIndexes[$keyword] ?? 0;
                        $keywordIndexes[$keyword] = $blockIndex + 1;
                        $callNames = $this->javaScriptDirectCallNames($node['children'] ?? []);
                        $blocks[] = [
                            'keyword' => $keyword,
                            'index' => $blockIndex,
                            'signature' => 'js-block:' . $keyword . ':' . implode("\0", $callNames),
                            'callNames' => $callNames,
                            'text' => $context['prefix'] . $this->nodeText($node),
                        ];
                    }
                }

                foreach ($this->javaScriptControlBlocks($node['children'] ?? []) as $nestedBlock) {
                    $keyword = $nestedBlock['keyword'];
                    $nestedBlock['index'] = $keywordIndexes[$keyword] ?? 0;
                    $keywordIndexes[$keyword] = $nestedBlock['index'] + 1;
                    $blocks[] = $nestedBlock;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return ?array{keyword:string, prefix:string}
     */
    private function javaScriptControlBlockContext(array $nodes, int $blockIndex): ?array
    {
        $condition = '';
        for ($index = $blockIndex - 1; $index >= 0; $index--) {
            $node = $nodes[$index];
            if (($node['type'] ?? '') === 'list') {
                if (($node['open'] ?? '') === '(' && $condition === '') {
                    $condition = $this->nodeText($node);
                    continue;
                }

                continue;
            }

            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                continue;
            }

            $token = $node['token'];
            if ($token->kind === 'comment') {
                continue;
            }
            if (in_array($token->text, ['if', 'for', 'while', 'switch', 'catch', 'else', 'finally', 'try'], true)) {
                return [
                    'keyword' => $token->text,
                    'prefix' => $token->text . $condition,
                ];
            }
            if (in_array($token->text, [';', '='], true)) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffJavaScriptCalls(array $oldNodes, array $newNodes, array $options, array &$changes, string $basePath): void
    {
        $newCalls = $this->javaScriptCalls($newNodes);
        $newBySignature = [];
        foreach ($newCalls as $index => $call) {
            $newBySignature[$call['signature']][] = $index;
        }

        $matchedNewIndexes = [];
        foreach ($this->javaScriptCalls($oldNodes) as $oldCall) {
            $matchIndex = null;
            foreach ($newBySignature[$oldCall['signature']] ?? [] as $candidateIndex) {
                if (($matchedNewIndexes[$candidateIndex] ?? false) === false) {
                    $matchIndex = $candidateIndex;
                    break;
                }
            }

            $path = $this->javaScriptCallPath($oldCall['callee'], $basePath, $oldCall['pathContext'] ?? '');
            if ($matchIndex === null) {
                $changes[] = ['op' => '-', 'path' => $path, 'text' => $oldCall['text']];
                continue;
            }

            $matchedNewIndexes[$matchIndex] = true;
            $newCall = $newCalls[$matchIndex];
            if ($this->nodeText($oldCall['list']) !== $this->nodeText($newCall['list'])) {
                $this->diffJavaScriptCallArguments($oldCall['list'], $newCall['list'], $path, $options, $changes, $oldCall['callee']);
            }
        }

        foreach ($newCalls as $index => $call) {
            if (($matchedNewIndexes[$index] ?? false) === true) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $this->javaScriptCallPath($call['callee'], $basePath, $call['pathContext'] ?? ''),
                'text' => $call['text'],
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $oldNodes
     * @param list<array<string, mixed>> $newNodes
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffJavaScriptArrays(array $oldNodes, array $newNodes, array $options, array &$changes, string $basePath): void
    {
        $newArrays = $this->javaScriptArrays($newNodes);
        $newBySignature = [];
        foreach ($newArrays as $index => $array) {
            $newBySignature[$array['signature']][] = $index;
        }

        $matchedNewIndexes = [];
        foreach ($this->javaScriptArrays($oldNodes) as $oldArray) {
            $matchIndex = null;
            foreach ($newBySignature[$oldArray['signature']] ?? [] as $candidateIndex) {
                if (($matchedNewIndexes[$candidateIndex] ?? false) === false) {
                    $matchIndex = $candidateIndex;
                    break;
                }
            }

            $path = $this->javaScriptArrayPath($oldArray['name'], $basePath);
            if ($matchIndex === null) {
                $changes[] = ['op' => '-', 'path' => $path, 'text' => $oldArray['text']];
                continue;
            }

            $matchedNewIndexes[$matchIndex] = true;
            $newArray = $newArrays[$matchIndex];
            if ($this->nodeText($oldArray['list']) !== $this->nodeText($newArray['list'])) {
                $this->diffListNode($oldArray['list'], $newArray['list'], $path, $options, $changes);
            }
        }

        foreach ($newArrays as $index => $array) {
            if (($matchedNewIndexes[$index] ?? false) === true) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $this->javaScriptArrayPath($array['name'], $basePath),
                'text' => $array['text'],
            ];
        }
    }

    /**
     * @param array<string, mixed> $oldList
     * @param array<string, mixed> $newList
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffJavaScriptCallArguments(array $oldList, array $newList, string $path, array $options, array &$changes, ?string $callee = null): void
    {
        $oldItems = $this->listItems($oldList, $options);
        $newItems = $this->listItems($newList, $options);
        $pairs = min(count($oldItems), count($newItems));

        for ($index = 0; $index < $pairs; $index++) {
            if ($this->shouldSkipJavaScriptCallArgumentForTypeScript($callee, $index, $oldItems[$index], $newItems[$index], $options)) {
                continue;
            }

            $oldText = $this->itemText($oldItems[$index]);
            $newText = $this->itemText($newItems[$index]);
            if ($oldText === $newText) {
                continue;
            }

            $nestedOld = $this->directLists($oldItems[$index]);
            $nestedNew = $this->directLists($newItems[$index]);
            if ($nestedOld !== [] || $nestedNew !== []) {
                $this->diffNestedLists($nestedOld, $nestedNew, $path . '[' . $index . ']', $options, $changes);
                continue;
            }

            $changes[] = [
                'op' => '~',
                'path' => $path . '[' . $index . ']',
                'old' => $oldText,
                'new' => $newText,
            ];
        }

        for ($index = $pairs; $index < count($oldItems); $index++) {
            if ($this->shouldSkipJavaScriptCallArgumentForTypeScript($callee, $index, $oldItems[$index], null, $options)) {
                continue;
            }

            $changes[] = ['op' => '-', 'path' => $path . '[' . $index . ']', 'text' => $this->itemText($oldItems[$index])];
        }
        for ($index = $pairs; $index < count($newItems); $index++) {
            if ($this->shouldSkipJavaScriptCallArgumentForTypeScript($callee, $index, null, $newItems[$index], $options)) {
                continue;
            }

            $changes[] = ['op' => '+', 'path' => $path . '[' . $index . ']', 'text' => $this->itemText($newItems[$index])];
        }
    }

    /**
     * @param ?list<array<string, mixed>> $oldItem
     * @param ?list<array<string, mixed>> $newItem
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function shouldSkipJavaScriptCallArgumentForTypeScript(?string $callee, int $index, ?array $oldItem, ?array $newItem, array $options): bool
    {
        if ($callee !== 'import' || $index !== 1 || !$this->isTypeScriptLanguage($options)) {
            return false;
        }

        return ($oldItem !== null && $this->typeScriptDynamicImportAttributeList($oldItem) !== null)
            || ($newItem !== null && $this->typeScriptDynamicImportAttributeList($newItem) !== null);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{callee:string, signature:string, list:array<string, mixed>, text:string, pathContext:string}>
     */
    private function javaScriptCalls(array $nodes): array
    {
        $calls = [];
        $this->collectJavaScriptCalls($nodes, $calls);

        return $calls;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array{callee:string, signature:string, list:array<string, mixed>, text:string, pathContext:string}> $calls
     * @param list<string> $contextLabels
     */
    private function collectJavaScriptCalls(array $nodes, array &$calls, array $contextLabels = []): void
    {
        $calleeParts = [];
        $count = count($nodes);
        for ($index = 0; $index < $count; $index++) {
            $node = $nodes[$index];
            $function = $this->javaScriptFunctionDeclarationAt($nodes, $index);
            if ($function !== null) {
                $functionContextLabels = $contextLabels;
                $functionContextLabels[] = 'function:' . $function['name'];
                $this->collectJavaScriptCalls($function['body']['children'] ?? [], $calls, $functionContextLabels);
                $index = $function['bodyIndex'];
                $calleeParts = [];
                continue;
            }

            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                if ($this->isJavaScriptCalleeToken($node['token'])) {
                    $calleeParts[] = $node['token']->text;
                    continue;
                }

                $calleeParts = [];
                continue;
            }

            if (($node['type'] ?? '') !== 'list') {
                $calleeParts = [];
                continue;
            }

            $nextContextLabels = $contextLabels;
            if (($node['open'] ?? '') === '(') {
                $callee = $this->normalizeJavaScriptCallee($calleeParts);
                if ($callee !== null) {
                    $label = $this->firstJavaScriptStringArgument($node);
                    $calls[] = [
                        'callee' => $callee,
                        'signature' => $this->javaScriptCallSignature($callee, $node, $contextLabels),
                        'list' => $node,
                        'text' => $callee . $this->nodeText($node),
                        'pathContext' => $this->javaScriptCallPathContext($contextLabels),
                    ];
                    if ($label !== null && $this->isJavaScriptNamedCallbackCallee($callee)) {
                        $nextContextLabels[] = $callee . ':' . $label;
                    }
                }
            }

            $this->collectJavaScriptCalls($node['children'] ?? [], $calls, $nextContextLabels);
            $calleeParts = [];
        }
    }

    private function isJavaScriptCalleeToken(Token $token): bool
    {
        return ($token->kind === 'identifier' && !$this->isJavaScriptCalleeBoundaryKeyword($token->text))
            || in_array($token->text, ['.', '$'], true);
    }

    private function isJavaScriptCalleeBoundaryKeyword(string $text): bool
    {
        return in_array($text, [
            'async',
            'await',
            'break',
            'case',
            'catch',
            'class',
            'const',
            'continue',
            'default',
            'delete',
            'do',
            'else',
            'export',
            'finally',
            'for',
            'function',
            'if',
            'import',
            'let',
            'new',
            'return',
            'switch',
            'throw',
            'try',
            'var',
            'while',
            'yield',
        ], true);
    }

    /**
     * @param list<string> $parts
     */
    private function normalizeJavaScriptCallee(array $parts): ?string
    {
        if ($parts === []) {
            return null;
        }

        $callee = trim(implode('', $parts), '.');
        if ($callee === '' || preg_match('/[A-Za-z_$]/', $callee) !== 1) {
            return null;
        }

        if (in_array($callee, ['catch', 'for', 'function', 'if', 'return', 'switch', 'while'], true)) {
            return null;
        }

        return $callee;
    }

    /**
     * @param array<string, mixed> $list
     * @param list<string> $contextLabels
     */
    private function javaScriptCallSignature(string $callee, array $list, array $contextLabels): string
    {
        $signature = 'js-call:' . $callee;
        if ($contextLabels !== []) {
            $signature .= ':context:' . implode("\0", $contextLabels);
        }

        if (!$this->isJavaScriptNamedCallbackCallee($callee)) {
            return $signature;
        }

        $label = $this->firstJavaScriptStringArgument($list);
        if ($label === null) {
            return $signature;
        }

        return $signature . ':label:' . $label;
    }

    /**
     * @param list<string> $contextLabels
     */
    private function javaScriptCallPathContext(array $contextLabels): string
    {
        $path = '';
        foreach ($contextLabels as $label) {
            if (!str_starts_with($label, 'function:')) {
                continue;
            }

            $path .= '.function[' . json_encode(substr($label, strlen('function:')), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
        }

        return $path;
    }

    private function isJavaScriptNamedCallbackCallee(string $callee): bool
    {
        return in_array($callee, [
            'context',
            'describe',
            'it',
            'suite',
            'test',
            'wp.hooks.addAction',
            'wp.hooks.addFilter',
        ], true);
    }

    /**
     * @param array<string, mixed> $list
     */
    private function firstJavaScriptStringArgument(array $list): ?string
    {
        $items = $this->listItems($list, ['language' => 'javascript']);
        if ($items === []) {
            return null;
        }

        $text = $this->itemText($items[0]);
        if (strlen($text) < 2) {
            return null;
        }

        $quote = $text[0];
        if (($quote === '"' || $quote === "'") && str_ends_with($text, $quote)) {
            return $text;
        }

        return null;
    }

    private function javaScriptCallPath(string $callee, string $basePath, string $pathContext = ''): string
    {
        return $basePath . $pathContext . '.call[' . json_encode($callee, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
    }

    private function javaScriptBlockPath(string $keyword, string $basePath, int $index): string
    {
        return $basePath . '.block[' . json_encode($keyword, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . '][' . $index . ']';
    }

    private function javaScriptArrayPath(string $name, string $basePath): string
    {
        return $basePath . '.array[' . json_encode($name, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<string>
     */
    private function javaScriptDirectCallNames(array $nodes): array
    {
        $names = [];
        $calleeParts = [];
        $count = count($nodes);
        for ($index = 0; $index < $count; $index++) {
            $node = $nodes[$index];
            $function = $this->javaScriptFunctionDeclarationAt($nodes, $index);
            if ($function !== null) {
                $index = $function['bodyIndex'];
                $calleeParts = [];
                continue;
            }

            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                if ($this->isJavaScriptCalleeToken($node['token'])) {
                    $calleeParts[] = $node['token']->text;
                    continue;
                }

                $calleeParts = [];
                continue;
            }

            if (($node['type'] ?? '') === 'list' && ($node['open'] ?? '') === '(') {
                $callee = $this->normalizeJavaScriptCallee($calleeParts);
                if ($callee !== null) {
                    $names[] = $callee;
                }
            }

            $calleeParts = [];
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return ?array{name:string, body:array<string, mixed>, bodyIndex:int}
     */
    private function javaScriptFunctionDeclarationAt(array $nodes, int $functionIndex): ?array
    {
        $node = $nodes[$functionIndex] ?? null;
        if (
            !is_array($node)
            || ($node['type'] ?? '') !== 'atom'
            || !$node['token'] instanceof Token
            || $node['token']->text !== 'function'
        ) {
            return null;
        }

        $nameIndex = $this->nextNonCommentNodeIndex($nodes, $functionIndex + 1);
        $nameNode = $nameIndex === null ? null : $nodes[$nameIndex];
        if (
            !is_array($nameNode)
            || ($nameNode['type'] ?? '') !== 'atom'
            || !$nameNode['token'] instanceof Token
            || !$this->isJavaScriptIdentifierAtom($nameNode['token']->text)
        ) {
            return null;
        }

        $parametersIndex = $this->nextNonCommentNodeIndex($nodes, $nameIndex + 1);
        $parametersNode = $parametersIndex === null ? null : $nodes[$parametersIndex];
        if (
            !is_array($parametersNode)
            || ($parametersNode['type'] ?? '') !== 'list'
            || ($parametersNode['open'] ?? '') !== '('
        ) {
            return null;
        }

        $bodyIndex = $this->nextNonCommentNodeIndex($nodes, $parametersIndex + 1);
        $bodyNode = $bodyIndex === null ? null : $nodes[$bodyIndex];
        if (
            !is_array($bodyNode)
            || ($bodyNode['type'] ?? '') !== 'list'
            || ($bodyNode['open'] ?? '') !== '{'
        ) {
            return null;
        }

        return [
            'name' => $nameNode['token']->text,
            'body' => $bodyNode,
            'bodyIndex' => $bodyIndex,
        ];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function nextNonCommentNodeIndex(array $nodes, int $offset): ?int
    {
        for ($index = $offset; $index < count($nodes); $index++) {
            $node = $nodes[$index];
            if (
                ($node['type'] ?? '') === 'atom'
                && $node['token'] instanceof Token
                && $node['token']->kind === 'comment'
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function isJavaScriptIdentifierAtom(string $text): bool
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $text) === 1
            && !in_array($text, ['as', 'async', 'await', 'class', 'export', 'function', 'import'], true);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{name:string, signature:string, list:array<string, mixed>, text:string}>
     */
    private function javaScriptArrays(array $nodes): array
    {
        $arrays = [];
        $this->collectJavaScriptArrays($nodes, $arrays);

        return $arrays;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array{name:string, signature:string, list:array<string, mixed>, text:string}> $arrays
     */
    private function collectJavaScriptArrays(array $nodes, array &$arrays): void
    {
        $contextAtoms = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                $token = $node['token'];
                if ($token->kind === 'comment') {
                    continue;
                }

                if ($token->text === ';') {
                    $contextAtoms = [];
                    continue;
                }

                $contextAtoms[] = $token->text;
                continue;
            }

            if (($node['type'] ?? '') !== 'list') {
                $contextAtoms = [];
                continue;
            }

            if (($node['open'] ?? '') === '[') {
                $name = $this->javaScriptArrayContextName($contextAtoms);
                if ($name !== null) {
                    $arrays[] = [
                        'name' => $name,
                        'signature' => 'js-array:' . $name,
                        'list' => $node,
                        'text' => $name . $this->nodeText($node),
                    ];
                }
            }

            $this->collectJavaScriptArrays($node['children'] ?? [], $arrays);
            $contextAtoms = [];
        }
    }

    /**
     * @param list<string> $contextAtoms
     */
    private function javaScriptArrayContextName(array $contextAtoms): ?string
    {
        for ($index = count($contextAtoms) - 1; $index >= 0; $index--) {
            if (!in_array($contextAtoms[$index], ['=', ':'], true)) {
                continue;
            }

            for ($nameIndex = $index - 1; $nameIndex >= 0; $nameIndex--) {
                $candidate = $contextAtoms[$nameIndex];
                if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $candidate) === 1 && !in_array($candidate, ['const', 'let', 'var', 'return'], true)) {
                    return $candidate;
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffPythonBlocks(string $old, string $new, array &$changes): void
    {
        $oldStructure = $this->pythonBlockStructure($old);
        $newStructure = $this->pythonBlockStructure($new);
        $matchedNewKeys = [];

        foreach ($oldStructure['blocks'] as $key => $oldBlock) {
            if (!isset($newStructure['blocks'][$key])) {
                $changes[] = [
                    'op' => '-',
                    'path' => $this->pythonBlockPath($oldBlock),
                    'text' => $this->pythonBlockText($oldBlock),
                ];
                continue;
            }

            $matchedNewKeys[$key] = true;
            if ($oldBlock['header'] !== $newStructure['blocks'][$key]['header']) {
                $changes[] = [
                    'op' => '~',
                    'path' => $this->pythonBlockPath($oldBlock) . '/header',
                    'old' => $oldBlock['header'],
                    'new' => $newStructure['blocks'][$key]['header'],
                ];
            }
            $this->diffStringItems(
                $oldBlock['items'],
                $newStructure['blocks'][$key]['items'],
                $this->pythonBlockPath($oldBlock),
                $changes,
            );
        }

        foreach ($newStructure['blocks'] as $key => $newBlock) {
            if (($matchedNewKeys[$key] ?? false) === true) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $this->pythonBlockPath($newBlock),
                'text' => $this->pythonBlockText($newBlock),
            ];
        }

        $this->diffStringItems($oldStructure['root'], $newStructure['root'], '$py.root', $changes);
    }

    /**
     * @return array{root:list<string>, blocks:array<string, array{keyword:string, label:string, header:string, items:list<string>, index:int, parentPath:string}>}
     */
    private function pythonBlockStructure(string $source): array
    {
        $entries = $this->pythonSignificantLines($source);
        $position = 0;
        $blocks = [];
        $seen = [];
        $root = $this->parsePythonSuite($entries, $position, 0, '', $blocks, $seen);

        return ['root' => $root, 'blocks' => $blocks];
    }

    /**
     * @return list<array{indent:int, text:string}>
     */
    private function pythonSignificantLines(string $source): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $source);
        if ($lines === false) {
            $lines = [$source];
        }

        $entries = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $entries[] = [
                'indent' => $this->pythonIndentWidth($line),
                'text' => $trimmed,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{indent:int, text:string}> $entries
     * @param array<string, array{keyword:string, label:string, header:string, items:list<string>, index:int, parentPath:string}> $blocks
     * @param array<string, int> $seen
     * @return list<string>
     */
    private function parsePythonSuite(array $entries, int &$position, int $suiteIndent, string $parentPath, array &$blocks, array &$seen): array
    {
        $items = [];
        $count = count($entries);
        $compoundPath = null;
        $compoundKind = null;

        while ($position < $count) {
            $entry = $entries[$position];
            if ($entry['indent'] < $suiteIndent) {
                break;
            }
            if ($entry['indent'] > $suiteIndent) {
                break;
            }

            $header = $this->pythonBlockHeader($entry['text']);
            if ($header === null) {
                $items[] = $entry['text'];
                $position++;
                $compoundPath = null;
                $compoundKind = null;
                continue;
            }

            $keyword = $header['keyword'];
            $continuesCompound = $compoundPath !== null && $this->pythonContinuationMatches($keyword, $compoundKind);
            $blockParentPath = $continuesCompound ? $compoundPath : $parentPath;
            $label = $header['condition'];
            $seenKey = $blockParentPath . "\0" . $keyword . "\0" . $label;
            $blockIndex = $seen[$seenKey] ?? 0;
            $seen[$seenKey] = $blockIndex + 1;

            $block = [
                'keyword' => $keyword,
                'label' => $label,
                'header' => $header['header'],
                'items' => [],
                'index' => $blockIndex,
                'parentPath' => $blockParentPath,
            ];
            $blockPath = $this->pythonBlockPath($block);
            $position++;

            if ($position < $count && $entries[$position]['indent'] > $entry['indent']) {
                $block['items'] = $this->parsePythonSuite(
                    $entries,
                    $position,
                    $entries[$position]['indent'],
                    $blockPath,
                    $blocks,
                    $seen,
                );
            }

            $key = $seenKey . "\0" . $blockIndex;
            $blocks[$key] = $block;

            if ($this->pythonStartsCompoundChain($keyword)) {
                $compoundPath = $blockPath;
                $compoundKind = $keyword;
                continue;
            }

            if (
                $continuesCompound
                && (
                    ($compoundKind === 'if' && $keyword === 'elif')
                    || ($compoundKind === 'try' && in_array($keyword, ['except', 'else'], true))
                )
            ) {
                continue;
            }

            $compoundPath = null;
            $compoundKind = null;
        }

        return $items;
    }

    /**
     * @return ?array{keyword:string, condition:string, header:string}
     */
    private function pythonBlockHeader(string $trimmedLine): ?array
    {
        if (preg_match('/^def\s+(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*(?<suffix>\([^)]*\)(?:\s*->\s*[^:]+)?):(?:\s*#.*)?$/', $trimmedLine, $match) === 1) {
            return [
                'keyword' => 'def',
                'condition' => (string) $match['name'],
                'header' => 'def ' . (string) $match['name'] . $this->normalizePythonHeaderSuffix((string) $match['suffix']),
            ];
        }

        if (preg_match('/^for\s+(?<condition>.+):(?:\s*#.*)?$/', $trimmedLine, $match) === 1) {
            $condition = trim((string) $match['condition']);

            return [
                'keyword' => 'for',
                'condition' => $condition,
                'header' => 'for ' . $condition,
            ];
        }

        if (preg_match('/^elif\s+(?<condition>.+):(?:\s*#.*)?$/', $trimmedLine, $match) === 1) {
            $condition = trim((string) $match['condition']);

            return [
                'keyword' => 'elif',
                'condition' => $condition,
                'header' => 'elif ' . $condition,
            ];
        }

        if (preg_match('/^(?<keyword>if|while|with)\s+(?<condition>.+):(?:\s*#.*)?$/', $trimmedLine, $match) === 1) {
            $keyword = (string) $match['keyword'];
            $condition = trim((string) $match['condition']);

            return [
                'keyword' => $keyword,
                'condition' => $condition,
                'header' => $keyword . ' ' . $condition,
            ];
        }

        if (preg_match('/^(?<keyword>else|try|finally):(?:\s*#.*)?$/', $trimmedLine, $match) === 1) {
            $keyword = (string) $match['keyword'];

            return [
                'keyword' => $keyword,
                'condition' => $keyword,
                'header' => $keyword,
            ];
        }

        if (preg_match('/^except(?<star>\*)?(?:\s+(?<condition>.+))?:(?:\s*#.*)?$/', $trimmedLine, $match) === 1) {
            $keyword = 'except';
            $condition = trim((string) ($match['condition'] ?? ''));
            $header = $keyword . ((string) ($match['star'] ?? '') === '*' ? '*' : '');
            if ($condition !== '') {
                $header .= ' ' . $condition;
            }

            return [
                'keyword' => $keyword,
                'condition' => $condition === '' ? $header : $condition,
                'header' => $header,
            ];
        }

        return null;
    }

    private function pythonStartsCompoundChain(string $keyword): bool
    {
        return in_array($keyword, ['if', 'for', 'while', 'try'], true);
    }

    private function pythonContinuationMatches(string $keyword, ?string $compoundKind): bool
    {
        return match ($keyword) {
            'elif' => $compoundKind === 'if',
            'else' => in_array($compoundKind, ['if', 'for', 'while', 'try'], true),
            'except', 'finally' => $compoundKind === 'try',
            default => false,
        };
    }

    private function normalizePythonHeaderSuffix(string $suffix): string
    {
        return (string) preg_replace('/\s+/', '', trim($suffix));
    }

    private function pythonIndentWidth(string $line): int
    {
        $indent = strspn($line, " \t");
        $width = 0;
        for ($index = 0; $index < $indent; $index++) {
            $width += $line[$index] === "\t" ? 4 : 1;
        }

        return $width;
    }

    /**
     * @param array{keyword:string, label:string, header:string, items:list<string>, index:int, parentPath?:string} $block
     */
    private function pythonBlockPath(array $block): string
    {
        $parentPath = (string) ($block['parentPath'] ?? '');
        $path = ($parentPath === '' ? '$py' : $parentPath) . '.' . $block['keyword'] . '[' . json_encode($block['label'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
        if ($block['index'] > 0) {
            $path .= '[' . $block['index'] . ']';
        }

        return $path;
    }

    /**
     * @param array{keyword:string, label:string, header:string, items:list<string>, index:int, parentPath?:string} $block
     */
    private function pythonBlockText(array $block): string
    {
        $body = $block['items'] === [] ? '' : "\n    " . implode("\n    ", $block['items']);

        return $block['header'] . ':' . $body;
    }

    /**
     * @param list<string> $oldItems
     * @param list<string> $newItems
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffStringItems(array $oldItems, array $newItems, string $path, array &$changes): void
    {
        $table = $this->lcsTable($oldItems, $newItems);
        $i = 0;
        $j = 0;

        while ($i < count($oldItems) && $j < count($newItems)) {
            if ($oldItems[$i] === $newItems[$j]) {
                $i++;
                $j++;
                continue;
            }

            if ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $changes[] = ['op' => '-', 'path' => $path . '[' . $i . ']', 'text' => $oldItems[$i]];
                $i++;
            } else {
                $changes[] = ['op' => '+', 'path' => $path . '[' . $j . ']', 'text' => $newItems[$j]];
                $j++;
            }
        }

        while ($i < count($oldItems)) {
            $changes[] = ['op' => '-', 'path' => $path . '[' . $i . ']', 'text' => $oldItems[$i]];
            $i++;
        }
        while ($j < count($newItems)) {
            $changes[] = ['op' => '+', 'path' => $path . '[' . $j . ']', 'text' => $newItems[$j]];
            $j++;
        }
    }

    /**
     * @return list<array{index:int, attributes:string, body:string}>
     */
    private function htmlRawBlocks(string $source, string $tagName): array
    {
        $quotedTag = preg_quote($tagName, '/');
        $matched = preg_match_all(
            '/<' . $quotedTag . '\b(?<attributes>[^>]*)>(?<body>[\s\S]*?)<\/' . $quotedTag . '>/i',
            $source,
            $matches,
            PREG_SET_ORDER,
        );
        if ($matched === false || $matched === 0) {
            return [];
        }

        $blocks = [];
        foreach ($matches as $index => $match) {
            $body = trim((string) $match['body']);
            $attributes = $this->normalizeHtmlRawBlockAttributes((string) $match['attributes']);
            $blocks[] = [
                'index' => $index,
                'attributes' => $attributes,
                'body' => $body,
            ];
        }

        return $blocks;
    }

    private function normalizeHtmlRawBlockAttributes(string $attributes): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', trim($attributes)));
    }

    /**
     * @param list<array{index:int, attributes:string, body:string}> $oldBlocks
     * @param list<array{index:int, attributes:string, body:string}> $newBlocks
     * @param array{language?: string} $options
     * @return list<array{old?:array{index:int, attributes:string, body:string}, new?:array{index:int, attributes:string, body:string}}>
     */
    private function pairedHtmlRawBlocks(array $oldBlocks, array $newBlocks, array $options): array
    {
        $pairs = [];
        $matchedNew = [];

        foreach ($oldBlocks as $oldBlock) {
            $matchIndex = $this->bestHtmlRawBlockMatch($oldBlock, $newBlocks, $matchedNew, $options);
            if ($matchIndex === null) {
                $pairs[] = ['old' => $oldBlock];
                continue;
            }

            $matchedNew[$matchIndex] = true;
            $pairs[] = ['old' => $oldBlock, 'new' => $newBlocks[$matchIndex]];
        }

        foreach ($newBlocks as $newIndex => $newBlock) {
            if (($matchedNew[$newIndex] ?? false) === true) {
                continue;
            }

            $pairs[] = ['new' => $newBlock];
        }

        usort(
            $pairs,
            static fn (array $a, array $b): int => (($a['new']['index'] ?? $a['old']['index'] ?? 0) <=> ($b['new']['index'] ?? $b['old']['index'] ?? 0)),
        );

        return $pairs;
    }

    /**
     * @param array{index:int, attributes:string, body:string} $oldBlock
     * @param list<array{index:int, attributes:string, body:string}> $newBlocks
     * @param array<int, bool> $matchedNew
     * @param array{language?: string} $options
     */
    private function bestHtmlRawBlockMatch(array $oldBlock, array $newBlocks, array $matchedNew, array $options): ?int
    {
        $bestIndex = null;
        $bestScore = 0;
        foreach ($newBlocks as $newIndex => $newBlock) {
            if (($matchedNew[$newIndex] ?? false) === true) {
                continue;
            }

            $score = $this->htmlRawBlockSimilarity($oldBlock['body'], $newBlock['body'], $options);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $newIndex;
            }
        }

        if ($bestIndex !== null && $bestScore > 0) {
            return $bestIndex;
        }

        $oldIndex = $oldBlock['index'];
        if (
            isset($newBlocks[$oldIndex])
            && ($matchedNew[$oldIndex] ?? false) === false
            && $oldBlock['attributes'] === $newBlocks[$oldIndex]['attributes']
        ) {
            return $oldIndex;
        }

        return null;
    }

    /**
     * @param array{language?: string} $options
     */
    private function htmlRawBlockSimilarity(string $oldBody, string $newBody, array $options): int
    {
        $oldTokens = $this->htmlRawBlockComparableTokens($oldBody, $options);
        $newTokens = $this->htmlRawBlockComparableTokens($newBody, $options);
        if ($oldTokens === [] || $newTokens === []) {
            return trim($oldBody) === trim($newBody) ? 1 : 0;
        }

        return count(array_intersect(array_unique($oldTokens), array_unique($newTokens)));
    }

    /**
     * @param array{language?: string} $options
     * @return list<string>
     */
    private function htmlRawBlockComparableTokens(string $body, array $options): array
    {
        $tokens = [];
        foreach ($this->tokenize($body, $options) as $token) {
            if ($token->kind === 'comment') {
                continue;
            }
            if ($token->kind === 'punctuation' && !in_array($token->text, ['@', '#', '.'], true)) {
                continue;
            }

            $text = strtolower($token->text);
            if ($text === '') {
                continue;
            }

            $tokens[] = $text;
        }

        return $tokens;
    }

    private function stripHtmlRawBlockBodies(string $source): string
    {
        return (string) preg_replace_callback(
            '/(?<open><(?<tag>style|script)\b[^>]*>)(?<body>[\s\S]*?)(?<close><\/\k<tag>\s*>)/i',
            static fn (array $match): string => $match['open'] . $match['close'],
            $source,
        );
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{selector:string, signature:string, list:array<string, mixed>}>
     */
    private function cssRules(array $nodes): array
    {
        $rules = [];
        $selectorParts = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
                if ($node['token']->kind !== 'comment') {
                    $selectorParts[] = $node['token']->text;
                }
                continue;
            }

            if (($node['type'] ?? '') !== 'list') {
                continue;
            }

            if (($node['open'] ?? '') !== '{') {
                $selectorParts[] = $this->nodeText($node);
                continue;
            }

            $selector = implode('', $selectorParts);
            if ($selector !== '') {
                $rules[] = [
                    'selector' => $selector,
                    'signature' => $this->cssRuleSignature($selector),
                    'list' => $node,
                ];
            }
            $selectorParts = [];
        }

        return $rules;
    }

    /**
     * @param array{selector:string, signature:string, list:array<string, mixed>} $rule
     */
    private function cssRuleText(array $rule): string
    {
        return $rule['selector'] . $this->nodeText($rule['list']);
    }

    private function cssRuleSignature(string $selector): string
    {
        if (preg_match('/^@(?<keyword>[A-Za-z_-]+)(?<name>[A-Za-z_][A-Za-z0-9_-]*)\(/', $selector, $match) === 1) {
            return '@' . $match['keyword'] . $match['name'];
        }

        if (preg_match('/^@(?<keyword>keyframes)(?<name>[A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match) === 1) {
            return '@' . $match['keyword'] . $match['name'];
        }

        return $selector;
    }

    private function cssRulePath(string $selector, string $basePath = '$css'): string
    {
        return $basePath . '[' . json_encode($selector, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<list<int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $table = array_fill(0, count($a) + 1, array_fill(0, count($b) + 1, 0));
        for ($i = count($a) - 1; $i >= 0; $i--) {
            for ($j = count($b) - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffYamlBlockSequences(string $old, string $new, array &$changes): void
    {
        $oldSequences = $this->yamlBlockSequences($old);
        $newSequences = $this->yamlBlockSequences($new);
        $paths = array_values(array_unique(array_merge(array_keys($oldSequences), array_keys($newSequences))));
        sort($paths);

        foreach ($paths as $path) {
            $oldItems = $oldSequences[$path] ?? [];
            $newItems = $newSequences[$path] ?? [];
            $table = $this->lcsTable($oldItems, $newItems);
            $i = 0;
            $j = 0;

            while ($i < count($oldItems) && $j < count($newItems)) {
                if ($oldItems[$i] === $newItems[$j]) {
                    $i++;
                    $j++;
                    continue;
                }

                if ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                    $changes[] = ['op' => '-', 'path' => $path . '[' . $i . ']', 'text' => $oldItems[$i]];
                    $i++;
                } else {
                    $changes[] = ['op' => '+', 'path' => $path . '[' . $j . ']', 'text' => $newItems[$j]];
                    $j++;
                }
            }

            while ($i < count($oldItems)) {
                $changes[] = ['op' => '-', 'path' => $path . '[' . $i . ']', 'text' => $oldItems[$i]];
                $i++;
            }
            while ($j < count($newItems)) {
                $changes[] = ['op' => '+', 'path' => $path . '[' . $j . ']', 'text' => $newItems[$j]];
                $j++;
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function yamlBlockSequences(string $source): array
    {
        $sequences = [];
        $stack = [];
        $blockScalarIndent = null;

        foreach (preg_split('/\r\n|\n|\r/', $source) ?: [] as $line) {
            if (trim($line) === '' || trim($line) === '---') {
                continue;
            }

            $indent = strspn($line, " \t");
            if ($blockScalarIndent !== null) {
                if ($indent > $blockScalarIndent || trim($line) === '') {
                    continue;
                }

                $blockScalarIndent = null;
            }

            while ($stack !== [] && $stack[array_key_last($stack)]['indent'] >= $indent) {
                array_pop($stack);
            }

            $trimmed = ltrim($line, " \t");
            if (str_starts_with($trimmed, '- ')) {
                if ($stack === []) {
                    continue;
                }

                $path = $this->yamlPath($stack);
                $sequences[$path] ??= [];
                $sequences[$path][] = trim(substr($trimmed, 2));
                continue;
            }

            if (preg_match('/^(?<key>[^#:\r\n][^:\r\n]*):(?<value>[^\r\n]*)$/', $trimmed, $match) !== 1) {
                continue;
            }

            $stack[] = [
                'indent' => $indent,
                'key' => $this->yamlKey((string) $match['key']),
            ];

            if (preg_match('/^\s*[|>]/', (string) $match['value']) === 1) {
                $blockScalarIndent = $indent;
            }
        }

        return $sequences;
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffYamlBlockScalars(string $old, string $new, array &$changes): void
    {
        $oldScalars = $this->yamlBlockScalars($old);
        $newScalars = $this->yamlBlockScalars($new);
        $paths = array_values(array_unique(array_merge(array_keys($oldScalars), array_keys($newScalars))));
        sort($paths);

        foreach ($paths as $path) {
            if (!array_key_exists($path, $newScalars)) {
                $changes[] = ['op' => '-', 'path' => $path, 'text' => $oldScalars[$path]];
                continue;
            }
            if (!array_key_exists($path, $oldScalars)) {
                $changes[] = ['op' => '+', 'path' => $path, 'text' => $newScalars[$path]];
                continue;
            }
            if ($oldScalars[$path] !== $newScalars[$path]) {
                $changes[] = ['op' => '~', 'path' => $path, 'old' => $oldScalars[$path], 'new' => $newScalars[$path]];
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function yamlBlockScalars(string $source): array
    {
        $scalars = [];
        $lines = preg_split('/\r\n|\n|\r/', $source) ?: [];
        $stack = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];
            if (trim($line) === '' || trim($line) === '---') {
                continue;
            }

            $indent = strspn($line, " \t");
            while ($stack !== [] && $stack[array_key_last($stack)]['indent'] >= $indent) {
                array_pop($stack);
            }

            $trimmed = ltrim($line, " \t");
            if (preg_match('/^(?<key>[^#:\r\n][^:\r\n]*):(?<value>[^\r\n]*)$/', $trimmed, $match) !== 1) {
                continue;
            }

            $entry = [
                'indent' => $indent,
                'key' => $this->yamlKey((string) $match['key']),
            ];
            $stack[] = $entry;

            if (preg_match('/^\s*[|>]/', (string) $match['value']) !== 1) {
                continue;
            }

            $body = [];
            for ($bodyIndex = $index + 1; $bodyIndex < $count; $bodyIndex++) {
                $bodyLine = $lines[$bodyIndex];
                if (trim($bodyLine) !== '' && strspn($bodyLine, " \t") <= $indent) {
                    break;
                }

                $body[] = $bodyLine;
                $index = $bodyIndex;
            }

            $scalars[$this->yamlPath($stack)] = rtrim(implode("\n", $body), "\r\n");
        }

        return $scalars;
    }

    private function yamlKey(string $key): string
    {
        return trim(trim($key), "\"'");
    }

    /**
     * @param list<array{indent:int, key:string}> $stack
     */
    private function yamlPath(array $stack): string
    {
        $path = '$yaml';
        foreach ($stack as $entry) {
            $key = (string) $entry['key'];
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $key) === 1) {
                $path .= '.' . $key;
                continue;
            }

            $path .= '[' . json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
        }

        return $path;
    }

    /**
     * @param list<array{op:string, path:string, text?:string, old?:string, new?:string}> $changes
     */
    private function diffPhpFunctionReturnTypes(string $old, string $new, array &$changes): void
    {
        $oldFunctions = $this->phpFunctionReturnTypes($old);
        $newFunctions = $this->phpFunctionReturnTypes($new);
        $names = array_values(array_intersect(array_keys($oldFunctions), array_keys($newFunctions)));
        sort($names);

        foreach ($names as $name) {
            $oldType = $oldFunctions[$name];
            $newType = $newFunctions[$name];
            if ($oldType === $newType) {
                continue;
            }

            $path = '$php.function.' . $name . '.return_type';
            if ($oldType === null) {
                $changes[] = ['op' => '+', 'path' => $path, 'text' => (string) $newType];
                continue;
            }
            if ($newType === null) {
                $changes[] = ['op' => '-', 'path' => $path, 'text' => $oldType];
                continue;
            }

            $changes[] = ['op' => '~', 'path' => $path, 'old' => $oldType, 'new' => $newType];
        }
    }

    /**
     * @return array<string, ?string>
     */
    private function phpFunctionReturnTypes(string $source): array
    {
        $functions = [];
        preg_match_all(
            '/function\s+(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*(?::\s*(?<type>[^{;\r\n]+))?/m',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $type = isset($match['type']) && trim($match['type']) !== ''
                ? $this->normalizePhpType((string) $match['type'])
                : null;
            $functions[(string) $match['name']] = $type;
        }

        return $functions;
    }

    private function normalizePhpType(string $type): string
    {
        return (string) preg_replace('/\s+/', '', trim($type));
    }

    /**
     * @param array<string, mixed> $list
     * @return list<list<array<string, mixed>>>
     */
    private function listItems(array $list, array $options = []): array
    {
        if ($this->isLispLanguage($options) && $this->isLispLiteralAtomList($list)) {
            return $this->lispListItems($list['children'] ?? []);
        }

        $items = [];
        $current = [];
        foreach ($list['children'] ?? [] as $child) {
            if ($this->isRustBlockCommentItem($list, $child, $options)) {
                if ($current !== []) {
                    $items[] = $current;
                    $current = [];
                }
                $items[] = [$child];
                continue;
            }

            if ($this->shouldStartRustItem($list, $current, $child, $options)) {
                $items[] = $current;
                $current = [];
            }

            if (($child['type'] ?? '') === 'atom' && $child['token'] instanceof Token && $child['token']->text === ',') {
                if ($current !== []) {
                    $items[] = $current;
                    $current = [];
                }
                continue;
            }

            $current[] = $child;

            if ($this->isRustStatementSeparator($list, $child, $options)) {
                $items[] = $current;
                $current = [];
            }

            if ($this->isCssDeclarationSeparator($list, $child, $options)) {
                $items[] = $current;
                $current = [];
            }

            if ($this->isTypeScriptMemberSeparator($list, $child, $options)) {
                $items[] = $current;
                $current = [];
            }
        }
        if ($current !== []) {
            $items[] = $current;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<list<array<string, mixed>>>
     */
    private function lispListItems(array $children): array
    {
        $items = [];
        $count = count($children);
        for ($index = 0; $index < $count; $index++) {
            $child = $children[$index];
            if ($this->isReaderQuoteNode($child) && $index + 1 < $count) {
                $items[] = [$child, $children[$index + 1]];
                $index++;
                continue;
            }

            $items[] = [$child];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $list
     */
    private function isLispLiteralAtomList(array $list): bool
    {
        $children = $list['children'] ?? [];
        if ($children === []) {
            return false;
        }

        foreach ($children as $child) {
            if (($child['type'] ?? '') !== 'atom' || !$child['token'] instanceof Token) {
                return false;
            }

            if (!in_array($child['token']->kind, ['comment', 'string'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isReaderQuoteNode(array $node): bool
    {
        return ($node['type'] ?? '') === 'atom'
            && $node['token'] instanceof Token
            && in_array($node['token']->text, ["'", '`'], true);
    }

    /**
     * @param array<string, mixed> $list
     * @param array<string, mixed> $child
     * @param array{language?: string} $options
     */
    private function isRustBlockCommentItem(array $list, array $child, array $options): bool
    {
        return $this->isRustLanguage($options)
            && ($list['open'] ?? '') === '{'
            && ($child['type'] ?? '') === 'atom'
            && $child['token'] instanceof Token
            && $child['token']->kind === 'comment';
    }

    /**
     * @param array<string, mixed> $list
     * @param list<array<string, mixed>> $current
     * @param array<string, mixed> $child
     * @param array{language?: string} $options
     */
    private function shouldStartRustItem(array $list, array $current, array $child, array $options): bool
    {
        if (!$this->isRustLanguage($options) || ($list['open'] ?? '') !== '{' || $current === []) {
            return false;
        }

        $previous = $current[array_key_last($current)];
        if (($previous['type'] ?? '') !== 'list' || ($previous['open'] ?? '') !== '{') {
            return false;
        }

        $text = $this->firstAtomText($child);

        return in_array($text, ['#', 'async', 'const', 'extern', 'fn', 'impl', 'mod', 'pub', 'static', 'struct', 'type', 'unsafe', 'use'], true);
    }

    /**
     * @param array<string, mixed> $list
     * @param array<string, mixed> $child
     * @param array{language?: string} $options
     */
    private function isRustStatementSeparator(array $list, array $child, array $options): bool
    {
        return $this->isRustLanguage($options)
            && ($list['open'] ?? '') === '{'
            && ($child['type'] ?? '') === 'atom'
            && $child['token'] instanceof Token
            && $child['token']->text === ';';
    }

    /**
     * @param array<string, mixed> $list
     * @param array<string, mixed> $child
     * @param array{language?: string} $options
     */
    private function isCssDeclarationSeparator(array $list, array $child, array $options): bool
    {
        return $this->isCssLanguage($options)
            && ($list['open'] ?? '') === '{'
            && ($child['type'] ?? '') === 'atom'
            && $child['token'] instanceof Token
            && $child['token']->text === ';';
    }

    /**
     * @param array<string, mixed> $list
     * @param array<string, mixed> $child
     * @param array{language?: string} $options
     */
    private function isTypeScriptMemberSeparator(array $list, array $child, array $options): bool
    {
        return $this->isTypeScriptLanguage($options)
            && ($list['open'] ?? '') === '{'
            && ($child['type'] ?? '') === 'atom'
            && $child['token'] instanceof Token
            && $child['token']->text === ';';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function firstAtomText(array $node): string
    {
        if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
            return $node['token']->text;
        }

        return '';
    }

    /**
     * @param array{language?: string} $options
     */
    private function isRustLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['rs', 'rust'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isBashLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['bash', 'sh', 'shell'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isYamlLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['yaml', 'yml'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isCssLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['css', 'scss'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isJavaScriptLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isTypeScriptLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['typescript', 'ts', 'tsx'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isJsxLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['jsx', 'tsx'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isHtmlLanguage(array $options): bool
    {
        return strtolower((string) ($options['language'] ?? '')) === 'html';
    }

    /**
     * @param array{language?: string} $options
     */
    private function isPhpLikeLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['hack', 'hh', 'php'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isPythonLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['py', 'python'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isRubyLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['rb', 'ruby'], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isLispLanguage(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), [
            'clojure',
            'common-lisp',
            'commonlisp',
            'elisp',
            'emacs-lisp',
            'janet',
            'lisp',
            'racket',
            'scheme',
        ], true);
    }

    /**
     * @param array{language?: string} $options
     */
    private function isLispReaderQuote(string $text, array $options): bool
    {
        return $this->isLispLanguage($options) && in_array($text, ["'", '`'], true);
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function itemText(array $item): string
    {
        return implode('', array_map(fn (array $node): string => $this->nodeText($node), $item));
    }

    /**
     * @param list<array<string, mixed>> $item
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     */
    private function itemSignature(array $item, array $options): string
    {
        if ($this->isLispLanguage($options)) {
            $lispSignature = $this->lispItemSignature($item);
            if ($lispSignature !== null) {
                return $lispSignature;
            }
        }

        if (($options['language'] ?? '') === 'json') {
            $jsonPropertyKey = $this->jsonPropertyKeySignature($item);
            if ($jsonPropertyKey !== null) {
                return $jsonPropertyKey;
            }
        }
        if ($this->isJavaScriptLanguage($options)) {
            $javaScriptProperty = $this->javaScriptPropertySignature($item);
            if ($javaScriptProperty !== null) {
                return $javaScriptProperty;
            }
        }
        if ($this->isCssLanguage($options)) {
            $cssAtRule = $this->cssAtRuleSignature($item);
            if ($cssAtRule !== null) {
                return $cssAtRule;
            }

            $cssDeclaration = $this->cssDeclarationSignature($item);
            if ($cssDeclaration !== null) {
                return $cssDeclaration;
            }
        }

        $prefix = '';
        foreach ($item as $node) {
            if (($node['type'] ?? '') === 'list') {
                return $prefix !== '' ? $prefix : 'list:' . ($node['open'] ?? '') . ($node['close'] ?? '');
            }

            $prefix .= $this->nodeText($node);
        }

        return $prefix;
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function lispItemSignature(array $item): ?string
    {
        $prefix = '';
        foreach ($item as $node) {
            if (($node['type'] ?? '') === 'list') {
                break;
            }
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                return null;
            }

            $token = $node['token'];
            if ($token->kind === 'comment' || in_array($token->text, ["'", '`'], true)) {
                continue;
            }

            $prefix .= $token->text;
        }

        return $prefix === '' ? null : 'lisp:' . $prefix;
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function jsonPropertyKeySignature(array $item): ?string
    {
        $key = null;
        foreach ($item as $node) {
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                return null;
            }

            $token = $node['token'];
            if ($key === null) {
                if ($token->kind !== 'string') {
                    return null;
                }

                $key = $token->text;
                continue;
            }

            if ($token->text === ':') {
                return 'json-key:' . $key;
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function javaScriptPropertySignature(array $item): ?string
    {
        $property = '';
        foreach ($item as $node) {
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                return null;
            }

            $text = $node['token']->text;
            if ($text === ':') {
                return $property === '' ? null : 'js-prop:' . $property;
            }
            if ($text === ';' || $text === '=') {
                return null;
            }

            $property .= $text;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function cssDeclarationSignature(array $item): ?string
    {
        $property = '';
        foreach ($item as $node) {
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                return null;
            }

            $text = $node['token']->text;
            if ($text === ':') {
                return $property === '' ? null : 'css-prop:' . $property;
            }
            if ($text === ';') {
                return null;
            }

            $property .= $text;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $item
     */
    private function cssAtRuleSignature(array $item): ?string
    {
        $atoms = [];
        foreach ($item as $node) {
            if (($node['type'] ?? '') === 'list') {
                break;
            }
            if (($node['type'] ?? '') !== 'atom' || !$node['token'] instanceof Token) {
                return null;
            }
            if ($node['token']->kind === 'comment') {
                continue;
            }
            if ($node['token']->text === ';') {
                break;
            }

            $atoms[] = $node['token']->text;
        }

        if (count($atoms) < 2 || $atoms[0] !== '@') {
            return null;
        }

        $keyword = $atoms[1];
        if ($keyword === 'include' && count($atoms) > 2) {
            return 'css-at:@include:' . implode('', array_slice($atoms, 2));
        }

        return 'css-at:@' . $keyword;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function directLists(array $nodes): array
    {
        return array_values(array_filter($nodes, static fn (array $node): bool => ($node['type'] ?? '') === 'list'));
    }

    /**
     * @param array<string, mixed> $node
     */
    private function nodeText(array $node): string
    {
        if (($node['type'] ?? '') === 'atom' && $node['token'] instanceof Token) {
            return $node['token']->text;
        }
        if (($node['type'] ?? '') === 'list') {
            $children = implode('', array_map(fn (array $child): string => $this->nodeText($child), $node['children'] ?? []));

            return (string) ($node['open'] ?? '') . $children . (string) ($node['close'] ?? '');
        }

        return implode('', array_map(fn (array $child): string => $this->nodeText($child), $node['children'] ?? []));
    }
}
