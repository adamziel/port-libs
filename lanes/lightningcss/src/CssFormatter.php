<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssFormatter
{
    public function format(string $css): string
    {
        $css = trim($this->stripComments($css));
        if ($css === '') {
            return '';
        }

        $rules = [];
        $cursor = 0;
        $length = strlen($css);

        while (true) {
            $cursor = $this->skipWhitespace($css, $cursor);
            if ($cursor >= $length) {
                break;
            }

            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                throw new \InvalidArgumentException('Expected a @page block');
            }

            $prelude = trim(substr($css, $cursor, $open - $cursor));
            $close = $this->findMatchingBrace($css, $open);
            if (preg_match('/^@counter-style\s+([_a-zA-Z-][_a-zA-Z0-9-]*)$/', $prelude) === 1) {
                $rules[] = $this->formatCounterStyleRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $cursor = $close + 1;
                continue;
            }

            if ($this->isPropertyRulePrelude($prelude)) {
                $rules[] = $this->formatPropertyRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $cursor = $close + 1;
                continue;
            }

            if ($this->isConditionalGroupPrelude($prelude)) {
                $rules[] = $this->formatConditionalGroupRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $cursor = $close + 1;
                continue;
            }

            if (!preg_match('/^@page(?:\s|:|$)/i', $prelude)) {
                if (str_starts_with($prelude, '@')) {
                    throw new \InvalidArgumentException('CssFormatter currently supports style rules, @page, @counter-style, @property, @media, and @layer rules only');
                }

                $rules[] = $this->formatStyleRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $cursor = $close + 1;
                continue;
            }

            $rules[] = $this->formatPageRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
            $cursor = $close + 1;
        }

        return implode("\n\n", $rules) . "\n";
    }

    private function formatPageRule(string $prelude, string $body, int $indentLevel): string
    {
        $items = $this->parsePageRuleItems($body);
        $indent = $this->indent($indentLevel);
        if ($items === []) {
            return $indent . $this->normalizePagePrelude($prelude) . ' {}';
        }

        $blocks = [];
        foreach ($items as $item) {
            if ($item['type'] === 'declarations') {
                $blocks[] = $this->formatDeclarations($item['body'], $indentLevel + 1);
                continue;
            }

            $blocks[] = $this->formatPageMarginRule($item['prelude'], $item['body'], $indentLevel + 1);
        }

        return $indent . $this->normalizePagePrelude($prelude) . " {\n"
            . implode("\n\n", $blocks) . "\n"
            . $indent . '}';
    }

    /**
     * @return list<array{type:'declarations', body:string}|array{type:'margin-rule', prelude:string, body:string}>
     */
    private function parsePageRuleItems(string $body): array
    {
        $items = [];
        $cursor = 0;
        $length = strlen($body);

        while (true) {
            $cursor = $this->skipWhitespace($body, $cursor);
            if ($cursor >= $length) {
                break;
            }

            if ($body[$cursor] === '@') {
                $open = $this->findNextTopLevel($body, '{', $cursor);
                if ($open === null) {
                    throw new \InvalidArgumentException('Invalid @page nested at-rule');
                }

                $prelude = trim(substr($body, $cursor, $open - $cursor));
                $name = $this->pageMarginAtRuleName($prelude);
                if ($name === null || !$this->isPageMarginAtRuleName($name)) {
                    throw new \InvalidArgumentException('Invalid @page nested at-rule: ' . ($name ?? $prelude));
                }

                $close = $this->findMatchingBrace($body, $open);
                $nestedBody = substr($body, $open + 1, $close - $open - 1);
                $nestedAt = $this->findNextTopLevelAtKeyword($nestedBody, 0);
                if ($nestedAt !== null) {
                    $nestedOpen = $this->findNextTopLevel($nestedBody, '{', $nestedAt);
                    $nestedPrelude = $nestedOpen === null
                        ? trim(substr($nestedBody, $nestedAt))
                        : trim(substr($nestedBody, $nestedAt, $nestedOpen - $nestedAt));
                    $nestedName = $this->pageMarginAtRuleName($nestedPrelude);
                    throw new \InvalidArgumentException('Invalid @page nested at-rule: ' . ($nestedName ?? $nestedPrelude));
                }

                $items[] = [
                    'type' => 'margin-rule',
                    'prelude' => '@' . $name,
                    'body' => $nestedBody,
                ];
                $cursor = $close + 1;
                continue;
            }

            $nextAt = $this->findNextTopLevelAtKeyword($body, $cursor);
            $end = $nextAt ?? $length;
            $declarations = trim(substr($body, $cursor, $end - $cursor));
            if ($declarations !== '') {
                $items[] = [
                    'type' => 'declarations',
                    'body' => $declarations,
                ];
            }
            $cursor = $end;
        }

        return $items;
    }

    private function formatPageMarginRule(string $prelude, string $body, int $indentLevel): string
    {
        return $this->indent($indentLevel) . $prelude . " {\n"
            . $this->formatDeclarations($body, $indentLevel + 1) . "\n"
            . $this->indent($indentLevel) . '}';
    }

    private function formatCounterStyleRule(string $prelude, string $body, int $indentLevel): string
    {
        $nestedAt = $this->findNextTopLevelAtKeyword($body, 0);
        if ($nestedAt !== null) {
            throw new \InvalidArgumentException('@counter-style rules only allow declarations');
        }

        $indent = $this->indent($indentLevel);
        $body = trim($body);
        if ($body === '') {
            return $indent . $this->normalizeCounterStylePrelude($prelude) . ' {}';
        }

        return $indent . $this->normalizeCounterStylePrelude($prelude) . " {\n"
            . $this->formatDeclarations($body, $indentLevel + 1) . "\n"
            . $indent . '}';
    }

    private function formatPropertyRule(string $prelude, string $body, int $indentLevel): string
    {
        $name = $this->propertyRuleName($prelude);
        $indent = $this->indent($indentLevel);
        $body = trim($body);
        if ($body === '') {
            return $indent . '@property ' . $name . ' {}';
        }

        return $indent . '@property ' . $name . " {\n"
            . $this->formatPropertyDeclarations($body, $indentLevel + 1) . "\n"
            . $indent . '}';
    }

    private function formatConditionalGroupRule(string $prelude, string $body, int $indentLevel): string
    {
        $items = $this->parseConditionalGroupItems($body, $indentLevel + 1);
        $indent = $this->indent($indentLevel);
        if ($items === []) {
            return $indent . $this->normalizeConditionalGroupPrelude($prelude) . ' {}';
        }

        return $indent . $this->normalizeConditionalGroupPrelude($prelude) . " {\n"
            . implode("\n\n", $items) . "\n"
            . $indent . '}';
    }

    /**
     * @return list<string>
     */
    private function parseConditionalGroupItems(string $body, int $indentLevel): array
    {
        $items = [];
        $cursor = 0;
        $length = strlen($body);

        while (true) {
            $cursor = $this->skipWhitespace($body, $cursor);
            if ($cursor >= $length) {
                break;
            }

            if ($body[$cursor] !== '@') {
                throw new \InvalidArgumentException('@media and @layer formatter groups only support nested at-rules');
            }

            $open = $this->findNextTopLevel($body, '{', $cursor);
            if ($open === null) {
                throw new \InvalidArgumentException('Invalid nested at-rule in formatter group');
            }

            $prelude = trim(substr($body, $cursor, $open - $cursor));
            $close = $this->findMatchingBrace($body, $open);
            $nestedBody = substr($body, $open + 1, $close - $open - 1);
            if ($this->isPropertyRulePrelude($prelude)) {
                $items[] = $this->formatPropertyRule($prelude, $nestedBody, $indentLevel);
            } elseif ($this->isConditionalGroupPrelude($prelude)) {
                $items[] = $this->formatConditionalGroupRule($prelude, $nestedBody, $indentLevel);
            } else {
                throw new \InvalidArgumentException('Unsupported nested at-rule in formatter group: ' . $prelude);
            }

            $cursor = $close + 1;
        }

        return $items;
    }

    private function formatPropertyDeclarations(string $body, int $indentLevel): string
    {
        $declarations = $this->parseDeclarations($body);
        $hasInitialValue = false;
        foreach ($declarations as [$property]) {
            if ($property === 'initial-value') {
                $hasInitialValue = true;
                break;
            }
        }

        $lines = [];
        $last = count($declarations) - 1;
        foreach ($declarations as $index => [$property, $value]) {
            $suffix = (!$hasInitialValue && $index === $last) ? '' : ';';
            $lines[] = $this->indent($indentLevel)
                . $property . ': ' . $this->formatPropertyDeclarationValue($property, $value) . $suffix;
        }

        return implode("\n", $lines);
    }

    private function formatStyleRule(string $prelude, string $body, int $indentLevel): string
    {
        $selector = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);
        if ($selector === '') {
            throw new \InvalidArgumentException('Invalid empty style rule selector');
        }

        $indent = $this->indent($indentLevel);
        $declarations = $this->parseDeclarations($body);
        if ($declarations === []) {
            return $indent . $selector . ' {}';
        }

        $lines = [];
        foreach ($declarations as [$property, $value]) {
            $lines[] = $this->formatStyleDeclaration($property, $value, $indentLevel + 1);
        }

        return $indent . $selector . " {\n"
            . implode("\n", $lines) . "\n"
            . $indent . '}';
    }

    private function formatStyleDeclaration(string $property, string $value, int $indentLevel): string
    {
        $indent = $this->indent($indentLevel);
        $prefix = $indent . $property . ': ';
        $formatted = match ($property) {
            'grid', 'grid-template' => $this->formatGridTemplateDeclarationValue($property, $value, strlen($prefix)),
            default => $this->formatDeclarationValue($value),
        };

        return $prefix . $formatted . ';';
    }

    private function formatDeclarations(string $body, int $indentLevel): string
    {
        $lines = [];
        foreach ($this->parseDeclarations($body) as [$property, $value]) {
            $lines[] = $this->indent($indentLevel) . $property . ': ' . $this->formatDeclarationValue($value) . ';';
        }

        return implode("\n", $lines);
    }

    private function formatGridTemplateDeclarationValue(string $property, string $value, int $continuationIndent): string
    {
        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) > 2) {
            return $this->formatDeclarationValue($value);
        }

        $rows = $this->formatGridTemplateAreaRows(trim($parts[0]));
        if ($rows === null) {
            return $this->formatDeclarationValue($value);
        }

        $continuation = "\n" . str_repeat(' ', $continuationIndent);
        if (count($parts) === 1) {
            return implode($continuation, $rows);
        }

        $columns = $this->formatDeclarationValue(trim($parts[1]));
        if ($columns === '') {
            return $this->formatDeclarationValue($value);
        }

        return implode($continuation, $rows) . $continuation . '/ ' . $columns;
    }

    /**
     * @return list<string>|null
     */
    private function formatGridTemplateAreaRows(string $rows): ?array
    {
        $tokens = $this->splitGridTemplateTokens($rows);
        if ($tokens === [] || !$this->gridTemplateTokensContainAreaString($tokens)) {
            return null;
        }

        $lines = [];
        $pendingLineNames = [];
        $index = 0;
        $count = count($tokens);

        while ($index < $count) {
            $token = $tokens[$index];
            if ($this->isGridLineNameToken($token)) {
                $pendingLineNames[] = $token;
                $index++;
                continue;
            }

            if (!$this->isCssStringToken($token)) {
                return null;
            }

            $segments = array_merge($pendingLineNames, [$this->formatGridTemplateAreaString($token)]);
            $pendingLineNames = [];
            $index++;

            $between = [];
            while ($index < $count && !$this->isCssStringToken($tokens[$index])) {
                $between[] = $tokens[$index++];
            }

            $hasNextArea = $index < $count;
            [$suffix, $nextPrefix] = $this->formatGridTemplateRowTail($between, $hasNextArea);
            $segments = array_merge($segments, $suffix);
            $pendingLineNames = $nextPrefix;
            $lines[] = implode(' ', $segments);
        }

        if ($pendingLineNames !== []) {
            $last = array_key_last($lines);
            if ($last === null) {
                return null;
            }
            $lines[$last] .= ' ' . implode(' ', $pendingLineNames);
        }

        return $lines;
    }

    /**
     * @param list<string> $tokens
     * @return array{0:list<string>,1:list<string>}
     */
    private function formatGridTemplateRowTail(array $tokens, bool $hasNextArea): array
    {
        if ($tokens === []) {
            return [[], []];
        }

        $trackIndex = null;
        foreach ($tokens as $index => $token) {
            if (!$this->isGridLineNameToken($token)) {
                $trackIndex = $index;
                break;
            }
        }

        if ($trackIndex === null) {
            if (!$hasNextArea) {
                return [$tokens, []];
            }

            return $this->splitGridTemplateBoundaryLineNames($tokens);
        }

        $suffix = array_slice($tokens, 0, $trackIndex);
        $track = $tokens[$trackIndex];
        if (strcasecmp($track, 'auto') !== 0) {
            $suffix[] = $this->formatDeclarationValue($track);
        }

        foreach (array_slice($tokens, $trackIndex + 1) as $token) {
            $suffix[] = $token;
        }

        return [$suffix, []];
    }

    /**
     * @param list<string> $tokens
     * @return array{0:list<string>,1:list<string>}
     */
    private function splitGridTemplateBoundaryLineNames(array $tokens): array
    {
        if ($tokens === []) {
            return [[], []];
        }

        $firstNames = $this->gridLineNameTokenNames($tokens[0]);
        if (count($firstNames) > 1) {
            return [
                [$this->gridLineNameToken([$firstNames[0]])],
                [$this->gridLineNameToken(array_slice($firstNames, 1))],
            ];
        }

        if (count($tokens) === 1) {
            return [[], $tokens];
        }

        return [[$tokens[0]], array_slice($tokens, 1)];
    }

    /**
     * @return list<string>
     */
    private function splitGridTemplateTokens(string $value): array
    {
        $tokens = [];
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            if (ctype_space($value[$i])) {
                continue;
            }

            if ($value[$i] === '[') {
                $end = strpos($value, ']', $i + 1);
                if ($end === false) {
                    return [];
                }
                $tokens[] = trim(substr($value, $i, $end - $i + 1));
                $i = $end;
                continue;
            }

            if ($value[$i] === '"' || $value[$i] === "'") {
                $quote = $value[$i];
                $start = $i;
                for ($i++; $i < $length; $i++) {
                    if ($value[$i] === '\\') {
                        $i++;
                        continue;
                    }
                    if ($value[$i] === $quote) {
                        $tokens[] = substr($value, $start, $i - $start + 1);
                        break;
                    }
                }
                if ($i >= $length) {
                    return [];
                }
                continue;
            }

            $start = $i;
            $parenDepth = 0;
            for (; $i < $length; $i++) {
                $char = $value[$i];
                if ($char === '(') {
                    $parenDepth++;
                    continue;
                }
                if ($char === ')' && $parenDepth > 0) {
                    $parenDepth--;
                    continue;
                }
                if ($parenDepth === 0 && (ctype_space($char) || $char === '[' || $char === '"' || $char === "'")) {
                    break;
                }
            }

            $tokens[] = trim(substr($value, $start, $i - $start));
            $i--;
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $tokens
     */
    private function gridTemplateTokensContainAreaString(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($this->isCssStringToken($token)) {
                return true;
            }
        }

        return false;
    }

    private function formatGridTemplateAreaString(string $token): string
    {
        $quote = $token[0];
        $content = substr($token, 1, -1);
        $cells = preg_split('/\s+/', trim($content)) ?: [];
        $cells = array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));
        $cells = array_map(static fn (string $cell): string => preg_match('/^\.+$/', $cell) === 1 ? '.' : $cell, $cells);

        return $quote . implode(' ', $cells) . $quote;
    }

    private function isCssStringToken(string $token): bool
    {
        $token = trim($token);

        return strlen($token) >= 2
            && (($token[0] === '"' && substr($token, -1) === '"')
                || ($token[0] === "'" && substr($token, -1) === "'"));
    }

    private function isGridLineNameToken(string $token): bool
    {
        $token = trim($token);

        return str_starts_with($token, '[') && str_ends_with($token, ']');
    }

    /**
     * @return list<string>
     */
    private function gridLineNameTokenNames(string $token): array
    {
        $names = preg_split('/\s+/', trim(substr($token, 1, -1))) ?: [];

        return array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
    }

    /**
     * @param list<string> $names
     */
    private function gridLineNameToken(array $names): string
    {
        return '[' . implode(' ', $names) . ']';
    }

    /**
     * @return list<array{string, string}>
     */
    private function parseDeclarations(string $body): array
    {
        $declarations = [];
        foreach ($this->splitTopLevel($body, ';') as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $colon = $this->findNextTopLevel($part, ':', 0);
            if ($colon === null) {
                throw new \InvalidArgumentException('Invalid declaration in @page rule: ' . $part);
            }

            $property = strtolower(trim(substr($part, 0, $colon)));
            $value = trim(substr($part, $colon + 1));
            if ($property === '') {
                throw new \InvalidArgumentException('Invalid declaration in @page rule: ' . $part);
            }

            $declarations[] = [$property, $value];
        }

        return $declarations;
    }

    private function normalizePagePrelude(string $prelude): string
    {
        $prelude = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);
        $prelude = preg_replace('/^@page\s+:/i', '@page :', $prelude) ?? $prelude;

        return $prelude;
    }

    private function normalizeCounterStylePrelude(string $prelude): string
    {
        $prelude = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);

        return preg_replace('/^@counter-style\s+/i', '@counter-style ', $prelude) ?? $prelude;
    }

    private function normalizeConditionalGroupPrelude(string $prelude): string
    {
        return trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);
    }

    private function formatDeclarationValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return preg_replace('/\bcounter\(\s*([_a-zA-Z-][_a-zA-Z0-9-]*)\s*\)/', 'counter($1)', $value) ?? $value;
    }

    private function formatPropertyDeclarationValue(string $property, string $value): string
    {
        $property = strtolower($property);
        if ($property === 'syntax') {
            return $this->formatPropertySyntaxValue($value);
        }

        if ($property === 'inherits') {
            return strtolower(trim($value));
        }

        return $this->formatDeclarationValue($value);
    }

    private function formatPropertySyntaxValue(string $value): string
    {
        $syntax = trim($value);
        if (strlen($syntax) >= 2 && (($syntax[0] === '"' && substr($syntax, -1) === '"') || ($syntax[0] === "'" && substr($syntax, -1) === "'"))) {
            $syntax = substr($syntax, 1, -1);
        }

        $syntax = trim(preg_replace('/\s+/', ' ', $syntax) ?? $syntax);
        $syntax = preg_replace('/\s*\|\s*/', ' | ', $syntax) ?? $syntax;
        $syntax = preg_replace('/\s*([#+])\s*/', '$1', $syntax) ?? $syntax;

        return '"' . str_replace('"', '\\"', $syntax) . '"';
    }

    private function isPropertyRulePrelude(string $prelude): bool
    {
        return preg_match('/^@property\b/i', trim($prelude)) === 1;
    }

    private function isConditionalGroupPrelude(string $prelude): bool
    {
        return preg_match('/^@(media|layer)\b/i', trim($prelude)) === 1;
    }

    private function propertyRuleName(string $prelude): string
    {
        if (preg_match('/^@property\b(.*)$/i', trim($prelude), $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid @property rule prelude: ' . $prelude);
        }

        $name = trim($matches[1]);
        if (preg_match('/^--[-_a-zA-Z0-9]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid @property name: {$name}");
        }

        return $name;
    }

    private function pageMarginAtRuleName(string $prelude): ?string
    {
        return preg_match('/^@([a-z-]+)\s*$/i', trim($prelude), $matches) === 1 ? strtolower($matches[1]) : null;
    }

    private function isPageMarginAtRuleName(string $name): bool
    {
        return in_array($name, [
            'top-left-corner',
            'top-left',
            'top-center',
            'top-right',
            'top-right-corner',
            'bottom-left-corner',
            'bottom-left',
            'bottom-center',
            'bottom-right',
            'bottom-right-corner',
            'left-top',
            'left-middle',
            'left-bottom',
            'right-top',
            'right-middle',
            'right-bottom',
        ], true);
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
                    break;
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $css, string $separator): array
    {
        $parts = [];
        $start = 0;
        $quote = null;
        $parenDepth = 0;
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
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
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === $separator && $parenDepth === 0) {
                $parts[] = substr($css, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($css, $start);

        return $parts;
    }

    private function findNextTopLevel(string $css, string $needle, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
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
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === '{') {
                if ($braceDepth === 0 && $needle === '{') {
                    return $i;
                }
                $braceDepth++;
                continue;
            }

            if ($char === '}') {
                if ($braceDepth > 0) {
                    $braceDepth--;
                }
                continue;
            }

            if ($char === $needle && $parenDepth === 0 && $braceDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findNextTopLevelAtKeyword(string $css, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
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
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }

            if ($char === '}') {
                if ($braceDepth > 0) {
                    $braceDepth--;
                }
                continue;
            }

            if ($char === '@' && $parenDepth === 0 && $braceDepth === 0) {
                return $i;
            }
        }

        return null;
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
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unclosed CSS block');
    }

    private function skipWhitespace(string $css, int $offset): int
    {
        $length = strlen($css);
        while ($offset < $length && ctype_space($css[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function indent(int $level): string
    {
        return str_repeat('  ', $level);
    }
}
