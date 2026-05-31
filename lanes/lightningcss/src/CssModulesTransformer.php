<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssModulesTransformer
{
    private string $hash = 'EgL3uq';
    private string $pattern = '[hash]_[local]';

    /**
     * @var array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     */
    private array $exports = [];

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>,
     *   references:array<string, array{type:string, name:string, specifier?:string}>
     * }
     *
     * @param array{hash?:string, pattern?:string, minify?:bool} $options
     */
    public function transform(string $css, array $options = []): array
    {
        $this->hash = $options['hash'] ?? 'EgL3uq';
        $this->pattern = $options['pattern'] ?? '[hash]_[local]';
        $this->exports = [];

        $code = $this->transformRuleList($this->stripComments($css));
        if (($options['minify'] ?? true) === true) {
            $code = (new NestingTransformer())->lower($code);
        }

        return [
            'code' => $code,
            'exports' => $this->exports,
            'references' => [],
        ];
    }

    private function transformRuleList(string $css): string
    {
        $output = '';
        $cursor = 0;

        while (true) {
            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $output .= substr($css, $cursor, $nextStatement - $cursor + 1);
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = substr($css, $cursor, $nextBlock - $cursor);
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            $trimmedPrelude = trim($prelude);

            if ($trimmedPrelude !== '' && $trimmedPrelude[0] === '@') {
                $output .= $prelude . '{' . $this->transformAtRuleBody($trimmedPrelude, $body) . '}';
                $cursor = $close + 1;
                continue;
            }

            [$selector, $locals] = $this->rewriteSelectorList($prelude);
            [$rewrittenBody, $composes] = $this->rewriteStyleBody($body);
            $this->assertValidComposesSelector($prelude, $composes);
            $this->addComposesToLocals($locals, $composes);

            $output .= $selector . '{' . $rewrittenBody . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function transformAtRuleBody(string $prelude, string $body): string
    {
        if (preg_match('/^@(?:-[a-z]+-)?keyframes\b/i', $prelude) === 1) {
            return $body;
        }

        return $this->transformRuleList($body);
    }

    /**
     * @return array{0:string,1:list<array{type:string, name:string, specifier?:string}>}
     */
    private function rewriteStyleBody(string $body): array
    {
        $output = '';
        $composes = [];
        $cursor = 0;

        while (true) {
            $nextBlock = $this->findNextTopLevel($body, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($body, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = substr($body, $cursor, $nextStatement - $cursor + 1);
                $output .= $this->rewriteDeclarationStatement($statement, $composes);
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $output .= $this->rewriteTrailingDeclarations(substr($body, $cursor), $composes);
                break;
            }

            $prefix = substr($body, $cursor, $nextBlock - $cursor);
            [$declarations, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $output .= $this->rewriteTrailingDeclarations($declarations, $composes);
            $trimmedNested = trim($nestedPrelude);
            $close = $this->findMatchingBrace($body, $nextBlock);
            $nestedBody = substr($body, $nextBlock + 1, $close - $nextBlock - 1);

            if ($trimmedNested !== '' && $trimmedNested[0] === '@') {
                $output .= $nestedPrelude . '{' . $this->transformAtRuleBody($trimmedNested, $nestedBody) . '}';
            } else {
                [$selector, $locals] = $this->rewriteSelectorList($nestedPrelude);
                [$rewrittenNestedBody, $nestedComposes] = $this->rewriteStyleBody($nestedBody);
                $this->assertValidComposesSelector($nestedPrelude, $nestedComposes);
                $this->addComposesToLocals($locals, $nestedComposes);
                $output .= $selector . '{' . $rewrittenNestedBody . '}';
            }

            $cursor = $close + 1;
        }

        return [$output, $composes];
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function rewriteDeclarationStatement(string $statement, array &$composes): string
    {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            return $statement;
        }

        $withoutSemicolon = rtrim($trimmed, ';');
        $colon = $this->findNextTopLevel($withoutSemicolon, ':', 0);
        if ($colon === null) {
            return $statement;
        }

        $property = strtolower(trim(substr($withoutSemicolon, 0, $colon)));
        if ($property !== 'composes') {
            return $statement;
        }

        $value = trim(substr($withoutSemicolon, $colon + 1));
        array_push($composes, ...$this->parseComposesValue($value));

        return '';
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function rewriteTrailingDeclarations(string $source, array &$composes): string
    {
        $output = '';
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($source, ';', $cursor)) !== null) {
            $statement = substr($source, $cursor, $semicolon - $cursor + 1);
            $output .= $this->rewriteDeclarationStatement($statement, $composes);
            $cursor = $semicolon + 1;
        }

        $tail = substr($source, $cursor);
        if (trim($tail) !== '') {
            $output .= $this->rewriteDeclarationStatement($tail, $composes);
        } else {
            $output .= $tail;
        }

        return $output;
    }

    /**
     * @return list<array{type:string, name:string, specifier?:string}>
     */
    private function parseComposesValue(string $value): array
    {
        $type = 'local';
        $specifier = null;

        if (preg_match('/^(.*?)\s+from\s+global$/i', $value, $matches) === 1) {
            $value = trim($matches[1]);
            $type = 'global';
        } elseif (preg_match('/^(.*?)\s+from\s+(["\'])(.*?)\2$/i', $value, $matches) === 1) {
            $value = trim($matches[1]);
            $type = 'dependency';
            $specifier = $matches[3];
        }

        $references = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $name) {
            if ($name === '') {
                continue;
            }

            if ($type === 'local') {
                $references[] = [
                    'type' => 'local',
                    'name' => $this->scopedName($name),
                ];
                continue;
            }

            if ($type === 'global') {
                $references[] = [
                    'type' => 'global',
                    'name' => $name,
                ];
                continue;
            }

            $references[] = [
                'type' => 'dependency',
                'name' => $name,
                'specifier' => $specifier ?? '',
            ];
        }

        return $references;
    }

    /**
     * @param list<string> $locals
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function addComposesToLocals(array $locals, array $composes): void
    {
        if ($locals === [] || $composes === []) {
            return;
        }

        foreach ($locals as $local) {
            $this->ensureExport($local);
            foreach ($composes as $compose) {
                if (in_array($compose, $this->exports[$local]['composes'], true)) {
                    continue;
                }
                $this->exports[$local]['composes'][] = $compose;
            }
        }
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function assertValidComposesSelector(string $selectorList, array $composes): void
    {
        if ($composes === []) {
            return;
        }

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            if (!$this->isSimpleLocalClassSelector($selector)) {
                throw new \InvalidArgumentException('CSS Modules composes may only be used in a simple local class selector');
            }
        }
    }

    private function isSimpleLocalClassSelector(string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '') {
            return false;
        }

        if ($this->startsWithPseudoFunction($selector, 0, ':local')) {
            $open = strlen(':local');
            $close = $this->findMatchingParen($selector, $open);
            if (trim(substr($selector, $close + 1)) !== '') {
                return false;
            }

            $selector = trim(substr($selector, $open + 1, $close - $open - 1));
        }

        return preg_match('/^\.[A-Za-z_-][A-Za-z0-9_-]*$/', $selector) === 1;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private function rewriteSelectorList(string $selectorList): array
    {
        $rewritten = [];
        $locals = [];

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $selectorLocals = [];
            $rewritten[] = $this->rewriteSelectorFragment($selector, 'local', $selectorLocals);
            foreach (array_keys($selectorLocals) as $local) {
                if (!in_array($local, $locals, true)) {
                    $locals[] = $local;
                }
            }
        }

        return [implode(', ', $rewritten), $locals];
    }

    /**
     * @param array<string, true> $locals
     */
    private function rewriteSelectorFragment(string $selector, string $mode, array &$locals): string
    {
        $output = '';
        $quote = null;
        $bracketDepth = 0;
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];

            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $selector[++$i];
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

            if ($char === '[') {
                $bracketDepth++;
                $output .= $char;
                continue;
            }

            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $output .= $char;
                continue;
            }

            if ($bracketDepth === 0 && $this->startsWithPseudoFunction($selector, $i, ':global')) {
                $open = $i + strlen(':global');
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $output .= $this->rewriteSelectorFragment($inner, 'global', $locals);
                $i = $close;
                continue;
            }

            if ($bracketDepth === 0 && $this->startsWithPseudoFunction($selector, $i, ':local')) {
                $open = $i + strlen(':local');
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $output .= $this->rewriteSelectorFragment($inner, 'local', $locals);
                $i = $close;
                continue;
            }

            if ($bracketDepth === 0 && (
                $this->startsWithCssModulesPseudoName($selector, $i, ':global')
                || $this->startsWithCssModulesPseudoName($selector, $i, ':local')
            )) {
                throw new \InvalidArgumentException('CSS Modules :local and :global selectors must use functional syntax');
            }

            if ($bracketDepth === 0 && $mode === 'local' && ($char === '.' || $char === '#')) {
                $nameStart = $i + 1;
                if ($nameStart < $length && $this->isIdentStart($selector[$nameStart])) {
                    $nameEnd = $this->readIdentEnd($selector, $nameStart);
                    $local = substr($selector, $nameStart, $nameEnd - $nameStart);
                    $locals[$local] = true;
                    $this->ensureExport($local);
                    $output .= $char . $this->scopedName($local);
                    $i = $nameEnd - 1;
                    continue;
                }
            }

            $output .= $char;
        }

        return trim($output);
    }

    private function ensureExport(string $local): void
    {
        if (isset($this->exports[$local])) {
            return;
        }

        $this->exports[$local] = [
            'name' => $this->scopedName($local),
            'composes' => [],
            'isReferenced' => false,
        ];
    }

    private function scopedName(string $local): string
    {
        return strtr($this->pattern, [
            '[hash]' => $this->hash,
            '[local]' => $local,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDeclarationsAndNestedPrelude(string $prefix): array
    {
        $semicolon = $this->findLastTopLevel($prefix, ';');
        if ($semicolon === null) {
            return ['', $prefix];
        }

        return [substr($prefix, 0, $semicolon + 1), substr($prefix, $semicolon + 1)];
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

    private function findNextTopLevel(string $css, string $needle, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $length = strlen($css);

        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
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
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === '{') {
                if ($needle === '{' && $parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
                    return $i;
                }
                $braceDepth++;
            } elseif ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
            } elseif ($char === $needle && $parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
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
                if ($char === '\\') {
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

    private function findMatchingParen(string $css, int $open): int
    {
        $quote = null;
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
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
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('CSS selector pseudo-class is missing a closing parenthesis');
    }

    private function startsWithPseudoFunction(string $selector, int $offset, string $name): bool
    {
        $length = strlen($name);
        if (strncasecmp(substr($selector, $offset, $length), $name, $length) !== 0) {
            return false;
        }

        return ($selector[$offset + $length] ?? '') === '(';
    }

    private function startsWithCssModulesPseudoName(string $selector, int $offset, string $name): bool
    {
        $length = strlen($name);
        if (strncasecmp(substr($selector, $offset, $length), $name, $length) !== 0) {
            return false;
        }

        $next = $selector[$offset + $length] ?? '';
        if ($next === '(') {
            return false;
        }

        return $next === '' || !$this->isIdentChar($next);
    }

    private function readIdentEnd(string $value, int $start): int
    {
        $length = strlen($value);
        $offset = $start;

        while ($offset < $length && $this->isIdentChar($value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function isIdentStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || $char === '-';
    }

    private function isIdentChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
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
