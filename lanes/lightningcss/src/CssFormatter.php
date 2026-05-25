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
            if (preg_match('/^@counter-style\s+([_a-zA-Z-][_a-zA-Z0-9-]*)$/', $prelude) === 1) {
                $close = $this->findMatchingBrace($css, $open);
                $rules[] = $this->formatCounterStyleRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $cursor = $close + 1;
                continue;
            }

            if (!preg_match('/^@page(?:\s|:|$)/i', $prelude)) {
                throw new \InvalidArgumentException('CssFormatter currently supports @page and @counter-style rules only');
            }

            $close = $this->findMatchingBrace($css, $open);
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

    private function formatDeclarations(string $body, int $indentLevel): string
    {
        $lines = [];
        foreach ($this->parseDeclarations($body) as [$property, $value]) {
            $lines[] = $this->indent($indentLevel) . $property . ': ' . $this->formatDeclarationValue($value) . ';';
        }

        return implode("\n", $lines);
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

    private function formatDeclarationValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return preg_replace('/\bcounter\(\s*([_a-zA-Z-][_a-zA-Z0-9-]*)\s*\)/', 'counter($1)', $value) ?? $value;
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
