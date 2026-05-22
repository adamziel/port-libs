<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CustomMediaTransformer
{
    /** @var array<string, string> */
    private array $definitions = [];

    public function transform(string $css, bool $preserveDeclarations = false): string
    {
        [$definitions, $ranges] = $this->collectDefinitions($css);
        $this->definitions = $definitions;

        if (!$preserveDeclarations) {
            $css = $this->removeRanges($css, $ranges);
        }

        return $this->replaceMediaRules($css);
    }

    /**
     * @return array{0: array<string, string>, 1: list<array{start:int,end:int}>}
     */
    private function collectDefinitions(string $css): array
    {
        $definitions = [];
        $ranges = [];
        $offset = 0;

        while (($position = $this->findAtKeyword($css, '@custom-media', $offset)) !== null) {
            $start = $position + strlen('@custom-media');
            $end = $this->findNextTopLevel($css, ';', $start);
            if ($end === null) {
                throw new \InvalidArgumentException('@custom-media rule is missing a terminating semicolon');
            }

            $prelude = trim(substr($css, $start, $end - $start));
            if (preg_match('/^(--[-_a-zA-Z0-9]+)\s+(.+)$/s', $prelude, $matches) !== 1) {
                throw new \InvalidArgumentException("Invalid @custom-media rule: {$prelude}");
            }

            $definitions[$matches[1]] = trim($matches[2]);
            $ranges[] = ['start' => $position, 'end' => $end + 1];
            $offset = $end + 1;
        }

        return [$definitions, $ranges];
    }

    /**
     * @param list<array{start:int,end:int}> $ranges
     */
    private function removeRanges(string $css, array $ranges): string
    {
        for ($i = count($ranges) - 1; $i >= 0; $i--) {
            $range = $ranges[$i];
            $css = substr($css, 0, $range['start']) . substr($css, $range['end']);
        }

        return $css;
    }

    private function replaceMediaRules(string $css): string
    {
        $output = '';
        $cursor = 0;

        while (($position = $this->findAtKeyword($css, '@media', $cursor)) !== null) {
            $open = $this->findNextTopLevel($css, '{', $position + strlen('@media'));
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = trim(substr($css, $position + strlen('@media'), $open - ($position + strlen('@media'))));
            $output .= substr($css, $cursor, $position - $cursor)
                . '@media '
                . $this->resolveMediaQueryList($prelude, [])
                . '{';
            $cursor = $open + 1;
        }

        return $output . substr($css, $cursor);
    }

    /**
     * @param list<string> $stack
     */
    private function resolveMediaQueryList(string $queryList, array $stack): string
    {
        $parts = $this->splitTopLevel($queryList, ',');
        if ($parts === []) {
            return '';
        }

        return implode(', ', array_map(
            fn (string $query): string => $this->resolveSingleQuery($query, $stack),
            $parts
        ));
    }

    /**
     * @param list<string> $stack
     */
    private function resolveSingleQuery(string $query, array $stack): string
    {
        $query = $this->normalizeWhitespace($this->resolveReferences(trim($query), $stack));
        $query = $this->simplifyNegatedFeatureRanges($query);
        $query = $this->simplifyDoubleNegation($query);
        $query = $this->simplifyDuplicateMediaTypes($query);

        return $this->normalizeWhitespace($query);
    }

    /**
     * @param list<string> $stack
     */
    private function resolveReferences(string $query, array $stack): string
    {
        $output = '';
        $quote = null;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $query[++$i];
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

            if ($char !== '(') {
                $output .= $char;
                continue;
            }

            $close = $this->findMatchingDelimiter($query, $i, '(', ')');
            $inner = substr($query, $i + 1, $close - $i - 1);
            $trimmed = trim($inner);
            if (preg_match('/^--[-_a-zA-Z0-9]+$/', $trimmed) === 1) {
                $output .= $this->resolveCustomMedia($trimmed, $stack);
                $i = $close;
                continue;
            }

            $resolvedInner = $this->resolveSingleQuery($inner, $stack);
            $output .= $this->isBareMediaTypeExpression($resolvedInner) ? $resolvedInner : '(' . $resolvedInner . ')';
            $i = $close;
        }

        return $output;
    }

    /**
     * @param list<string> $stack
     */
    private function resolveCustomMedia(string $name, array $stack): string
    {
        if (!array_key_exists($name, $this->definitions)) {
            throw new \InvalidArgumentException("Custom media {$name} is not defined");
        }
        if (in_array($name, $stack, true)) {
            throw new \InvalidArgumentException("Circular custom media reference involving {$name}");
        }

        $stack[] = $name;
        $parts = $this->splitTopLevel($this->definitions[$name], ',');
        $resolved = array_map(
            fn (string $query): string => $this->resolveSingleQuery($query, $stack),
            $parts
        );

        if (count($resolved) === 1) {
            return $resolved[0];
        }

        $factored = $this->factorCommonMediaType($resolved);
        if ($factored !== null) {
            return $factored;
        }

        return '(' . implode(' or ', array_map(
            fn (string $query): string => $this->wrapForBoolean($query),
            $resolved
        )) . ')';
    }

    /**
     * @param list<string> $queries
     */
    private function factorCommonMediaType(array $queries): ?string
    {
        $medium = null;
        $features = [];

        foreach ($queries as $query) {
            if (preg_match('/^((?:not\s+)?(?:screen|print|all))\s+and\s+(.+)$/i', $query, $matches) !== 1) {
                return null;
            }

            $currentMedium = strtolower($this->normalizeWhitespace($matches[1]));
            if ($medium === null) {
                $medium = $currentMedium;
            } elseif ($medium !== $currentMedium) {
                return null;
            }

            $features[] = $this->wrapForBoolean($matches[2]);
        }

        return $medium . ' and (' . implode(' or ', $features) . ')';
    }

    private function wrapForBoolean(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '()';
        }
        if ($query[0] === '(' && $this->matchingOuterParentheses($query)) {
            return $query;
        }

        return '(' . $query . ')';
    }

    private function simplifyNegatedFeatureRanges(string $query): string
    {
        return preg_replace_callback(
            '/\bnot\s*\(\s*(min|max)-([_a-zA-Z-][_a-zA-Z0-9-]*)\s*:\s*([^)]+?)\s*\)/i',
            static function (array $matches): string {
                $operator = strtolower($matches[1]) === 'min' ? '<' : '>';

                return '((' . strtolower($matches[2]) . ' ' . $operator . ' ' . trim($matches[3]) . '))';
            },
            $query
        ) ?? $query;
    }

    private function simplifyDoubleNegation(string $query): string
    {
        do {
            $before = $query;
            $query = preg_replace('/\bnot\s+not\s+(screen|print|all)\b/i', '$1', $query) ?? $query;
            $query = preg_replace('/\bnot\s+not\s+(\([^()]+\))/i', '$1', $query) ?? $query;
        } while ($query !== $before);

        return $query;
    }

    private function simplifyDuplicateMediaTypes(string $query): string
    {
        do {
            $before = $query;
            $query = preg_replace('/\b(screen|print|all)\s+and\s+\1\s+and\b/i', '$1 and', $query) ?? $query;
            $query = preg_replace('/\bnot\s+(screen|print|all)\s+and\s+not\s+\1\s+and\b/i', 'not $1 and', $query) ?? $query;
        } while ($query !== $before);

        return $query;
    }

    private function isBareMediaTypeExpression(string $query): bool
    {
        return preg_match('/^(?:not\s+)?(?:screen|print|all)$/i', trim($query)) === 1;
    }

    private function matchingOuterParentheses(string $query): bool
    {
        try {
            return $this->findMatchingDelimiter($query, 0, '(', ')') === strlen($query) - 1;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function findAtKeyword(string $css, string $keyword, int $start): ?int
    {
        $quote = null;
        $length = strlen($css);
        $keywordLength = strlen($keyword);

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
            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return null;
                }
                $i = $end + 1;
                continue;
            }

            if (strncasecmp(substr($css, $i, $keywordLength), $keyword, $keywordLength) !== 0) {
                continue;
            }

            $after = $css[$i + $keywordLength] ?? '';
            if ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1) {
                continue;
            }

            return $i;
        }

        return null;
    }

    private function findNextTopLevel(string $css, string $needle, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
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
            } elseif ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return null;
                }
                $i = $end + 1;
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

    private function findMatchingDelimiter(string $source, int $open, string $left, string $right): int
    {
        $depth = 1;
        $quote = null;
        $length = strlen($source);

        for ($i = $open + 1; $i < $length; $i++) {
            $char = $source[$i];
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
            } elseif ($char === $left) {
                $depth++;
            } elseif ($char === $right) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Media query contains unbalanced parentheses');
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
}
