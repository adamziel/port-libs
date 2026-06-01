<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class StylesheetParser
{
    /**
     * @return list<CssRule>
     */
    public function parse(string $css): array
    {
        return $this->parseRuleList($this->stripComments($css));
    }

    /**
     * Returns declaration key/value source ranges for the rule addressed by a zero-based rule path.
     *
     * @param list<int> $rulePath
     * @return array{
     *     key: array{start: array{line:int,column:int}, end: array{line:int,column:int}},
     *     value: array{start: array{line:int,column:int}, end: array{line:int,column:int}}
     * }|null
     */
    public function propertyLocation(string $css, array $rulePath, int $declarationIndex): ?array
    {
        if ($rulePath === []) {
            throw new \InvalidArgumentException('CSS rule path cannot be empty');
        }
        if ($declarationIndex < 0) {
            throw new \InvalidArgumentException('CSS declaration index cannot be negative');
        }
        foreach ($rulePath as $index) {
            if ($index < 0) {
                throw new \InvalidArgumentException('CSS rule path indexes cannot be negative');
            }
        }

        $block = $this->locateRuleBlock($css, $rulePath, 0, strlen($css), true);
        if ($block === null) {
            return null;
        }

        $body = substr($css, $block['bodyStart'], $block['bodyEnd'] - $block['bodyStart']);
        $origin = $this->sourceLocationForOffset($css, $block['bodyStart']);

        return (new DeclarationBlock())->propertyLocation($body, $declarationIndex, $origin['line'], $origin['column']);
    }

    /**
     * @return list<CssRule>
     */
    private function parseRuleList(string $css): array
    {
        $rules = [];
        $length = strlen($css);
        $cursor = 0;

        while (true) {
            $cursor = $this->skipWhitespace($css, $cursor);
            if ($cursor >= $length) {
                break;
            }

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    if (!str_starts_with($statement, '@')) {
                        throw new \InvalidArgumentException("Top-level CSS statement is not an at-rule: {$statement}");
                    }
                    [$name, $prelude] = $this->parseAtPrelude($statement);
                    $rules[] = new CssRule(CssRule::TYPE_AT_RULE, $name, $prelude, [], [], []);
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $trailing = trim(substr($css, $cursor));
                if ($trailing !== '') {
                    throw new \InvalidArgumentException("Unexpected trailing CSS without a block: {$trailing}");
                }
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            if ($prelude === '') {
                throw new \InvalidArgumentException('CSS rule is missing a prelude');
            }
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            $rules[] = $this->parseBlockRule($prelude, $body);
            $cursor = $close + 1;
        }

        return $rules;
    }

    private function parseBlockRule(string $prelude, string $body): CssRule
    {
        if (str_starts_with($prelude, '@')) {
            [$name, $atPrelude] = $this->parseAtPrelude($prelude);
            $atPrelude = $this->normalizeAtRulePrelude($name, $atPrelude);
            $bodyParts = $this->parseBody($body);

            return new CssRule(CssRule::TYPE_AT_RULE, $name, $atPrelude, [], $bodyParts['declarations'], $bodyParts['rules']);
        }

        $bodyParts = $this->parseBody($body);

        return new CssRule(CssRule::TYPE_STYLE, null, $prelude, $this->splitTopLevel($prelude, ','), $bodyParts['declarations'], $bodyParts['rules']);
    }

    /**
     * @return array{declarations: array<string, string>, rules: list<CssRule>}
     */
    private function parseBody(string $body): array
    {
        $declarationSource = '';
        $rules = [];
        $cursor = 0;

        while (($open = $this->findNextTopLevel($body, '{', $cursor)) !== null) {
            $prefix = substr($body, $cursor, $open - $cursor);
            [$declarations, $prelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            [$declarations, $statementRules] = $this->extractAtRuleStatements($declarations);
            $declarationSource .= $declarations;
            foreach ($statementRules as $statementRule) {
                $rules[] = $statementRule;
            }
            $prelude = trim($prelude);
            if ($prelude === '') {
                throw new \InvalidArgumentException('Nested CSS rule is missing a prelude');
            }

            $close = $this->findMatchingBrace($body, $open);
            $rules[] = $this->parseBlockRule($prelude, substr($body, $open + 1, $close - $open - 1));
            $cursor = $close + 1;
        }

        [$trailingDeclarations, $trailingStatementRules] = $this->extractAtRuleStatements(substr($body, $cursor));
        $declarationSource .= $trailingDeclarations;
        foreach ($trailingStatementRules as $statementRule) {
            $rules[] = $statementRule;
        }
        $declarationSource = trim($declarationSource);
        $declarations = $declarationSource === '' ? [] : (new DeclarationBlock())->parse($declarationSource);

        return ['declarations' => $declarations, 'rules' => $rules];
    }

    /**
     * @return array{0:string,1:list<CssRule>}
     */
    private function extractAtRuleStatements(string $source): array
    {
        $declarations = '';
        $rules = [];
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($source, ';', $cursor)) !== null) {
            $statement = substr($source, $cursor, $semicolon - $cursor);
            $trimmed = trim($statement);
            if ($trimmed !== '' && str_starts_with($trimmed, '@')) {
                [$name, $prelude] = $this->parseAtPrelude($trimmed);
                $rules[] = new CssRule(CssRule::TYPE_AT_RULE, $name, $prelude, [], [], []);
            } else {
                $declarations .= substr($source, $cursor, $semicolon - $cursor + 1);
            }

            $cursor = $semicolon + 1;
        }

        $tail = substr($source, $cursor);
        if (trim($tail) !== '') {
            $declarations .= $tail;
        }

        return [$declarations, $rules];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDeclarationsAndNestedPrelude(string $prefix): array
    {
        $semicolon = $this->findLastTopLevel($prefix, ';');
        if ($semicolon !== null) {
            return [substr($prefix, 0, $semicolon + 1), substr($prefix, $semicolon + 1)];
        }

        $trimmed = trim($prefix);
        if ($trimmed === '' || str_starts_with($trimmed, '@') || str_starts_with($trimmed, '&')) {
            return ['', $prefix];
        }

        return ['', $prefix];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseAtPrelude(string $prelude): array
    {
        if (preg_match('/^@([_a-zA-Z][-_a-zA-Z0-9]*)(?:\s+(.*))?$/s', trim($prelude), $matches) !== 1) {
            throw new \InvalidArgumentException("Invalid CSS at-rule prelude: {$prelude}");
        }

        return [strtolower($matches[1]), trim($matches[2] ?? '')];
    }

    private function normalizeAtRulePrelude(string $name, string $prelude): string
    {
        if ($name !== 'media' || $prelude === '') {
            return $prelude;
        }

        return (new MediaQueryParser())->minifyList($prelude, true);
    }

    /**
     * @param list<int> $rulePath
     * @return array{bodyStart:int,bodyEnd:int}|null
     */
    private function locateRuleBlock(
        string $css,
        array $rulePath,
        int $start,
        int $end,
        bool $countAtRuleStatements
    ): ?array {
        $target = $rulePath[0];
        $index = 0;
        $cursor = $start;

        while (true) {
            $cursor = $this->skipWhitespaceAndComments($css, $cursor, $end);
            if ($cursor >= $end) {
                return null;
            }

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor, $end);
            $nextStatement = $countAtRuleStatements ? $this->findNextTopLevel($css, ';', $cursor, $end) : null;
            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '' && str_starts_with($statement, '@')) {
                    if ($index === $target) {
                        return null;
                    }
                    $index++;
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                return null;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            if ($prelude === '') {
                return null;
            }

            $close = $this->findMatchingBrace($css, $nextBlock);
            if ($close > $end) {
                return null;
            }
            if ($index === $target) {
                $bodyStart = $nextBlock + 1;
                if (count($rulePath) === 1) {
                    return ['bodyStart' => $bodyStart, 'bodyEnd' => $close];
                }

                return $this->locateRuleBlock($css, array_slice($rulePath, 1), $bodyStart, $close, true);
            }

            $index++;
            $cursor = $close + 1;
        }
    }

    /**
     * @return array{line:int,column:int}
     */
    private function sourceLocationForOffset(string $source, int $offset): array
    {
        $line = 1;
        $column = 1;
        $length = min($offset, strlen($source));
        for ($i = 0; $i < $length; $i++) {
            if ($source[$i] === "\n") {
                $line++;
                $column = 1;
                continue;
            }
            $column++;
        }

        return ['line' => $line, 'column' => $column];
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [''];
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $parts[array_key_last($parts)] .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $parts[array_key_last($parts)] .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $delimiter && $parenDepth === 0 && $bracketDepth === 0) {
                $parts[] = '';
                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private function findNextTopLevel(string $css, string $needle, int $start, ?int $end = null): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = min($end ?? strlen($css), strlen($css));
        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $commentEnd = strpos($css, '*/', $i + 2);
                if ($commentEnd === false || $commentEnd + 2 > $length) {
                    return null;
                }
                $i = $commentEnd + 1;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $needle && $parenDepth === 0 && $bracketDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findLastTopLevel(string $css, string $needle): ?int
    {
        $last = null;
        $offset = 0;
        while (($next = $this->findNextTopLevel($css, $needle, $offset)) !== null) {
            $last = $next;
            $offset = $next + 1;
        }

        return $last;
    }

    private function findMatchingBrace(string $css, int $open): int
    {
        $quote = null;
        $depth = 0;
        $length = strlen($css);
        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $commentEnd = strpos($css, '*/', $i + 2);
                if ($commentEnd === false) {
                    throw new \InvalidArgumentException('CSS comment is missing a closing marker');
                }
                $i = $commentEnd + 1;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('CSS block is missing a closing brace');
    }

    private function skipWhitespace(string $css, int $offset): int
    {
        $length = strlen($css);
        while ($offset < $length && ctype_space($css[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function skipWhitespaceAndComments(string $css, int $offset, int $end): int
    {
        while ($offset < $end) {
            if (ctype_space($css[$offset])) {
                $offset++;
                continue;
            }
            if ($css[$offset] === '/' && ($css[$offset + 1] ?? '') === '*') {
                $commentEnd = strpos($css, '*/', $offset + 2);
                if ($commentEnd === false || $commentEnd + 2 > $end) {
                    return $end;
                }
                $offset = $commentEnd + 2;
                continue;
            }

            break;
        }

        return $offset;
    }

    private function stripComments(string $css): string
    {
        $output = '';
        $quote = null;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }
            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return $output;
                }
                $i = $end + 1;
                continue;
            }
            $output .= $char;
        }

        return $output;
    }
}
