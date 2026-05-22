<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class TokenDiffer
{
    private const BASE_DELIMITER_PAIRS = ['(' => ')', '[' => ']', '{' => '}'];

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
        $lineCommentPattern = $this->isLispLanguage($options) ? ';[^\r\n]*|\/\/[^\r\n]*' : '\/\/[^\r\n]*';
        $stringPattern = $this->isLispLanguage($options)
            ? '"(?:\\\\.|[^"\\\\])*"'
            : '"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'';
        preg_match_all(
            '/<!--[\s\S]*?-->|\/\*[\s\S]*?\*\/|' . $lineCommentPattern . '|[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|' . $stringPattern . '|===|!==|==|!=|<=|>=|=>|->|::|&&|\|\||[{}()[\].,;:+*\/<>=!-]|\S/u',
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
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return list<array{op:string, text:string}>
     */
    public function diff(string $old, string $new, array $options = []): array
    {
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
     * @param array{splitNumbers?: bool} $options
     * @return list<array{op:string, text:string}>
     */
    public function diffWords(string $old, string $new, array $options = []): array
    {
        $splitNumbers = ($options['splitNumbers'] ?? false) === true;
        $a = $splitNumbers ? $this->splitWordsAndNumbers($old) : $this->splitWords($old);
        $b = $splitNumbers ? $this->splitWordsAndNumbers($new) : $this->splitWords($new);

        return $this->diffSequences($a, $b);
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
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
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string} $options
     * @return list<array{op:string, path:string, text?:string, old?:string, new?:string}>
     */
    public function diffSyntaxLists(string $old, string $new, array $options = []): array
    {
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
     * @param array{language?: string} $options
     * @return array<string, string>
     */
    private function delimiterPairs(array $options): array
    {
        $pairs = self::BASE_DELIMITER_PAIRS;
        if ($this->usesAngleDelimiters($options)) {
            $pairs['<'] = '>';
        }

        return $pairs;
    }

    /**
     * @param array{language?: string} $options
     */
    private function usesAngleDelimiters(array $options): bool
    {
        return in_array(strtolower((string) ($options['language'] ?? '')), ['html', 'xml'], true);
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
            str_starts_with($text, ';') && $this->isLispLanguage($options) => 'comment',
            preg_match('/^[A-Za-z_]/', $text) === 1 => 'identifier',
            preg_match('/^\d/', $text) === 1 => 'number',
            str_starts_with($text, '"') || (str_starts_with($text, "'") && !$this->isLispReaderQuote($text, $options)) => 'string',
            isset($delimiterPairs[$text]) || isset($closeDelimiters[$text]) => 'delimiter',
            default => 'punctuation',
        };
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
            $tokens = $this->removeIgnoredTrailingCommas($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function removeIgnoredTrailingCommas(array $tokens): array
    {
        $kept = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->text === ',' && $this->nextTokenClosesList($tokens, $i + 1)) {
                continue;
            }

            $kept[] = $token;
        }

        return $kept;
    }

    /**
     * @param list<Token> $tokens
     */
    private function nextTokenClosesList(array $tokens, int $offset): bool
    {
        $count = count($tokens);
        for ($i = $offset; $i < $count; $i++) {
            if ($tokens[$i]->kind === 'comment') {
                continue;
            }

            return $tokens[$i]->delimiterRole === 'close';
        }

        return false;
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

            $path = $this->javaScriptCallPath($oldCall['callee'], $basePath);
            if ($matchIndex === null) {
                $changes[] = ['op' => '-', 'path' => $path, 'text' => $oldCall['text']];
                continue;
            }

            $matchedNewIndexes[$matchIndex] = true;
            $newCall = $newCalls[$matchIndex];
            if ($this->nodeText($oldCall['list']) !== $this->nodeText($newCall['list'])) {
                $this->diffJavaScriptCallArguments($oldCall['list'], $newCall['list'], $path, $options, $changes);
            }
        }

        foreach ($newCalls as $index => $call) {
            if (($matchedNewIndexes[$index] ?? false) === true) {
                continue;
            }

            $changes[] = [
                'op' => '+',
                'path' => $this->javaScriptCallPath($call['callee'], $basePath),
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
    private function diffJavaScriptCallArguments(array $oldList, array $newList, string $path, array $options, array &$changes): void
    {
        $oldItems = $this->listItems($oldList, $options);
        $newItems = $this->listItems($newList, $options);
        $pairs = min(count($oldItems), count($newItems));

        for ($index = 0; $index < $pairs; $index++) {
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
            $changes[] = ['op' => '-', 'path' => $path . '[' . $index . ']', 'text' => $this->itemText($oldItems[$index])];
        }
        for ($index = $pairs; $index < count($newItems); $index++) {
            $changes[] = ['op' => '+', 'path' => $path . '[' . $index . ']', 'text' => $this->itemText($newItems[$index])];
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{callee:string, signature:string, list:array<string, mixed>, text:string}>
     */
    private function javaScriptCalls(array $nodes): array
    {
        $calls = [];
        $this->collectJavaScriptCalls($nodes, $calls);

        return $calls;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array{callee:string, signature:string, list:array<string, mixed>, text:string}> $calls
     * @param list<string> $contextLabels
     */
    private function collectJavaScriptCalls(array $nodes, array &$calls, array $contextLabels = []): void
    {
        $calleeParts = [];
        foreach ($nodes as $node) {
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
        return $token->kind === 'identifier' || in_array($token->text, ['.', '$'], true);
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

    private function javaScriptCallPath(string $callee, string $basePath): string
    {
        return $basePath . '.call[' . json_encode($callee, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ']';
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
        foreach ($nodes as $node) {
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
