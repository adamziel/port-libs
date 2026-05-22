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
        $oldRoot = $this->parseTokenTree($this->tokensForDiff($old, $options), $delimiterPairs);
        $newRoot = $this->parseTokenTree($this->tokensForDiff($new, $options), $delimiterPairs);
        $changes = [];

        $oldLists = $this->directLists($oldRoot['children']);
        $newLists = $this->directLists($newRoot['children']);
        if ($this->usesAngleDelimiters($options)) {
            $this->diffRootListNodes($oldLists, $newLists, $options, $changes);

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
