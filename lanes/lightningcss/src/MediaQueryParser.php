<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class MediaQueryParser
{
    public function minifyList(string $queryList): string
    {
        $queries = $this->splitTopLevel($queryList, ',');
        if ($queries === []) {
            return '';
        }

        return implode(',', array_map(fn (string $query): string => $this->minifyQuery($query), $queries));
    }

    public function lowerRangeSyntaxList(string $queryList, bool $lowerSimpleRanges = true, bool $lowerIntervalRanges = true): string
    {
        $queries = $this->splitTopLevel($this->minifyList($queryList), ',');
        if ($queries === []) {
            return '';
        }

        return implode(',', array_map(
            fn (string $query): string => $this->lowerRangeSyntaxCondition($query, $lowerSimpleRanges, $lowerIntervalRanges)['css'],
            $queries
        ));
    }

    private function minifyQuery(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            throw new \InvalidArgumentException('Media query must not be empty');
        }
        if ($query[0] === '&') {
            throw new \InvalidArgumentException('Media query cannot start with a nesting selector');
        }

        $query = $this->normalizeWhitespace($query);
        $query = $this->normalizeParentheses($query);
        $query = preg_replace('/\b(and|or)\b/i', ' $1 ', $query) ?? $query;
        $query = $this->normalizeWhitespace($query);
        $this->validateTopLevelConditionFunctions($query);
        $query = $this->normalizeBooleanConditionGroups($query);
        $query = preg_replace_callback('/^(not|only)\s+(screen|print|all)\b/i', static fn (array $m): string => strtolower($m[1]) . ' ' . strtolower($m[2]), $query) ?? $query;
        $query = preg_replace_callback('/^(screen|print|all)\b/i', static fn (array $m): string => strtolower($m[1]), $query) ?? $query;

        return trim($query);
    }

    private function normalizeParentheses(string $source): string
    {
        $output = '';
        $quote = null;
        $length = strlen($source);

        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $source[++$i];
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

            $close = $this->findMatchingDelimiter($source, $i, '(', ')');
            $inner = substr($source, $i + 1, $close - $i - 1);
            $output .= '(' . $this->minifyParenthesized($inner) . ')';
            $i = $close;
        }

        return $output;
    }

    private function minifyParenthesized(string $inner): string
    {
        $inner = trim($inner);
        if ($inner === '') {
            throw new \InvalidArgumentException('Empty brackets are invalid in media query conditions');
        }

        if ($this->containsTopLevelKeyword($inner, 'and') || $this->containsTopLevelKeyword($inner, 'or')) {
            return $this->normalizeParentheses($this->normalizeWhitespace($inner));
        }

        if (preg_match('/^not\s+(.+)$/i', $inner, $matches) === 1) {
            return 'not ' . $this->normalizeParentheses($this->normalizeWhitespace($matches[1]));
        }

        if ($inner[0] === '(') {
            return $this->normalizeParentheses($this->normalizeWhitespace($inner));
        }

        return $this->minifyFeature($inner);
    }

    private function minifyFeature(string $feature): string
    {
        $feature = $this->normalizeWhitespace($feature);
        if ($this->containsTopLevelDelimiter($feature, ',')) {
            throw new \InvalidArgumentException("Invalid media query feature: {$feature}");
        }

        if (preg_match('/^(.+?)\s*(<=|>=|<|>)\s*([_a-zA-Z-][_a-zA-Z0-9-]*)\s*(<=|>=|<|>)\s*(.+)$/', $feature, $matches) === 1) {
            $this->validateRangeFeature(strtolower($matches[3]), $matches[1], $matches[5], $feature);

            return $this->minifyValue($matches[1]) . $matches[2] . strtolower($matches[3]) . $matches[4] . $this->minifyValue($matches[5]);
        }

        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\s*(<=|>=|<|>|=)\s*(.+)$/', $feature, $matches) === 1) {
            $name = strtolower($matches[1]);
            if ($matches[2] === '=' && !$this->isRangeComparableMediaFeature($name)) {
                throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
            }
            $this->validateRangeFeature($name, $matches[3], null, $feature);

            return strtolower($matches[1]) . $matches[2] . $this->minifyValue($matches[3]);
        }

        if (preg_match('/^(.+?)\s*(<=|>=|<|>|=)\s*([_a-zA-Z-][_a-zA-Z0-9-]*)$/', $feature, $matches) === 1) {
            $name = strtolower($matches[3]);
            if ($matches[2] === '=' && !$this->isRangeComparableMediaFeature($name)) {
                throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
            }
            $this->validateRangeFeature($name, $matches[1], null, $feature);

            return strtolower($matches[3]) . $this->oppositeComparison($matches[2]) . $this->minifyValue($matches[1]);
        }

        if (preg_match('/^(min|max)-([_a-zA-Z-][_a-zA-Z0-9-]*)\s*:\s*(.+)$/i', $feature, $matches) === 1) {
            $this->validateRangeFeature(strtolower($matches[2]), $matches[3], null, $feature);
            $operator = strtolower($matches[1]) === 'min' ? '>=' : '<=';

            return strtolower($matches[2]) . $operator . $this->minifyValue($matches[3]);
        }

        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\s*:\s*(.+)$/', $feature, $matches) === 1) {
            $this->validateDiscreteMediaFeature(strtolower($matches[1]), $matches[2], $feature);

            return strtolower($matches[1]) . ':' . $this->minifyValue($matches[2]);
        }

        if (preg_match('/^[_a-zA-Z-][_a-zA-Z0-9-]*\(/', $feature) === 1) {
            throw new \InvalidArgumentException("Unknown media query condition function: {$feature}");
        }

        return strtolower(str_replace(' ', '', $feature));
    }

    private function validateTopLevelConditionFunctions(string $query): void
    {
        $quote = null;
        $depth = 0;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
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
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth !== 0 || preg_match('/[_a-zA-Z-]/', $char) !== 1) {
                continue;
            }

            $start = $i;
            while ($i < $length && preg_match('/[-_a-zA-Z0-9]/', $query[$i]) === 1) {
                $i++;
            }

            $identifier = strtolower(substr($query, $start, $i - $start));
            if (($query[$i] ?? '') === '('
                && $identifier !== 'not'
                && !in_array($identifier, ['calc', 'clamp', 'env', 'max', 'min', 'var'], true)
            ) {
                throw new \InvalidArgumentException('Unknown media query condition function: ' . substr($query, $start));
            }

            $i--;
        }
    }

    private function validateRangeFeature(string $name, string $leftValue, ?string $rightValue, string $feature): void
    {
        if (!$this->isRangeComparableMediaFeature($name)) {
            throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
        }

        foreach ([$leftValue, $rightValue] as $value) {
            if ($value === null) {
                continue;
            }
            if (!$this->isValidRangeValue($name, $value)) {
                throw new \InvalidArgumentException("Invalid media query range value: {$feature}");
            }
        }
    }

    private function validateDiscreteMediaFeature(string $name, string $value, string $feature): void
    {
        if ($name === 'grid' && !in_array(trim($value), ['0', '1'], true)) {
            throw new \InvalidArgumentException("Invalid media query feature value: {$feature}");
        }
    }

    private function isRangeComparableMediaFeature(string $name): bool
    {
        if (str_starts_with($name, 'min-') || str_starts_with($name, 'max-')) {
            return false;
        }

        return in_array($name, ['width', 'height', 'color', 'resolution'], true);
    }

    private function isValidRangeValue(string $feature, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^(?:calc|clamp|env|max|min|var)\(/i', $value) === 1) {
            return true;
        }

        if (str_starts_with($value, '--styled-jsx-placeholder-')) {
            return true;
        }

        return match ($feature) {
            'color' => preg_match('/^\d+$/', $value) === 1,
            'resolution' => preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)(?:dpcm|dpi|dppx|x))$/i', $value) === 1,
            default => preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)(?:[a-zA-Z%]+))$/', $value) === 1,
        };
    }

    private function containsTopLevelDelimiter(string $value, string $delimiter): bool
    {
        $quote = null;
        $parenDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
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
            } elseif ($char === $delimiter && $parenDepth === 0) {
                return true;
            }
        }

        return false;
    }

    private function minifyValue(string $value): string
    {
        $value = trim($value);
        $value = $this->foldSimpleCalc($value);
        $value = preg_replace('/\s*\/\s*/', '/', $value) ?? $value;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\/1$/', $value, $matches) === 1) {
            return $this->trimNumber($matches[1]);
        }

        return $value;
    }

    private function foldSimpleCalc(string $value): string
    {
        if (preg_match('/^calc\(\s*([+-]?[0-9]+(?:\.[0-9]+)?)([a-zA-Z%]+)\s*([+-])\s*([0-9]+(?:\.[0-9]+)?)\2\s*\)$/', $value, $matches) !== 1) {
            return preg_replace_callback('/^calc\(\s*(.+)\s*\)$/', static fn (array $m): string => 'calc(' . trim($m[1]) . ')', $value) ?? $value;
        }

        $left = (float) $matches[1];
        $right = (float) $matches[4];
        $result = $matches[3] === '+' ? $left + $right : $left - $right;

        return $this->trimNumber((string) $result) . strtolower($matches[2]);
    }

    private function oppositeComparison(string $operator): string
    {
        return match ($operator) {
            '<' => '>',
            '<=' => '>=',
            '>' => '<',
            '>=' => '<=',
            default => '=',
        };
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function normalizeBooleanConditionGroups(string $query): string
    {
        $query = preg_replace('/\bnot\s*\(/i', 'not (', $query) ?? $query;
        $query = $this->simplifyNotWrappedConditions($query);
        $query = $this->flattenRedundantBooleanGroups($query, 'and');
        $query = $this->flattenRedundantBooleanGroups($query, 'or');

        return $this->normalizeWhitespace($query);
    }

    private function simplifyNotWrappedConditions(string $query): string
    {
        $output = '';
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            if (!$this->startsKeywordAt($query, $i, 'not')) {
                $output .= $query[$i];
                continue;
            }

            $after = $i + 3;
            $spaceStart = $after;
            while ($after < $length && ctype_space($query[$after])) {
                $after++;
            }

            if ($after === $spaceStart || ($query[$after] ?? '') !== '(') {
                $output .= $query[$i];
                continue;
            }

            $close = $this->findMatchingDelimiter($query, $after, '(', ')');
            $inner = substr($query, $after + 1, $close - $after - 1);
            $stripped = $this->stripOneBalancedParentheses(trim($inner));

            $output .= 'not ';
            if ($stripped !== null) {
                $output .= '(' . $stripped . ')';
            } else {
                $output .= substr($query, $after, $close - $after + 1);
            }

            $i = $close;
        }

        return $output;
    }

    private function flattenRedundantBooleanGroups(string $query, string $operator): string
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
            if ($this->containsTopLevelKeyword($inner, $operator)
                && ($this->endsWithKeyword($output, $operator) || $this->remainingStartsWithKeyword($query, $close + 1, $operator))
            ) {
                $output .= $inner;
                $i = $close;
                continue;
            }

            $output .= substr($query, $i, $close - $i + 1);
            $i = $close;
        }

        return $output;
    }

    private function stripOneBalancedParentheses(string $value): ?string
    {
        if (($value[0] ?? '') !== '(') {
            return null;
        }

        $close = $this->findMatchingDelimiter($value, 0, '(', ')');

        return $close === strlen($value) - 1 ? substr($value, 1, -1) : null;
    }

    private function startsKeywordAt(string $value, int $offset, string $keyword): bool
    {
        if (strncasecmp(substr($value, $offset, strlen($keyword)), $keyword, strlen($keyword)) !== 0) {
            return false;
        }

        $before = $value[$offset - 1] ?? '';
        $after = $value[$offset + strlen($keyword)] ?? '';

        return ($before === '' || preg_match('/[-_a-zA-Z0-9]/', $before) !== 1)
            && ($after === '' || preg_match('/[-_a-zA-Z0-9]/', $after) !== 1);
    }

    private function endsWithKeyword(string $value, string $keyword): bool
    {
        return preg_match('/(?:^|\s)' . preg_quote($keyword, '/') . '\s*$/i', $value) === 1;
    }

    private function remainingStartsWithKeyword(string $value, int $offset, string $keyword): bool
    {
        $remaining = ltrim(substr($value, $offset));

        return preg_match('/^' . preg_quote($keyword, '/') . '(?:\s|$)/i', $remaining) === 1;
    }

    private function trimNumber(string $number): string
    {
        if (!str_contains($number, '.')) {
            return $number;
        }

        return rtrim(rtrim($number, '0'), '.');
    }

    /**
     * @return array{css:string,changed:bool,root:?string,bareNot:bool}
     */
    private function lowerRangeSyntaxCondition(string $condition, bool $lowerSimpleRanges, bool $lowerIntervalRanges): array
    {
        $condition = trim($condition);
        $orParts = $this->splitTopLevelLogical($condition, 'or');
        if ($orParts !== null) {
            $parts = [];
            $changed = false;
            foreach ($orParts as $part) {
                $lowered = $this->lowerRangeSyntaxCondition($part, $lowerSimpleRanges, $lowerIntervalRanges);
                $parts[] = $lowered['root'] === 'and' || $lowered['bareNot']
                    ? '(' . $lowered['css'] . ')'
                    : $lowered['css'];
                $changed = $changed || $lowered['changed'];
            }

            return [
                'css' => implode(' or ', $parts),
                'changed' => $changed,
                'root' => 'or',
                'bareNot' => false,
            ];
        }

        $andParts = $this->splitTopLevelLogical($condition, 'and');
        if ($andParts !== null) {
            $parts = [];
            $changed = false;
            foreach ($andParts as $part) {
                $lowered = $this->lowerRangeSyntaxCondition($part, $lowerSimpleRanges, $lowerIntervalRanges);
                $parts[] = $lowered['root'] === 'or' || $lowered['bareNot']
                    ? '(' . $lowered['css'] . ')'
                    : $lowered['css'];
                $changed = $changed || $lowered['changed'];
            }

            return [
                'css' => implode(' and ', $parts),
                'changed' => $changed,
                'root' => 'and',
                'bareNot' => false,
            ];
        }

        if (preg_match('/^not\s+(.+)$/i', $condition, $matches) === 1) {
            $inner = trim($matches[1]);
            $unwrapped = $this->unwrapSingleParenthesizedValue($inner) ?? $inner;
            $range = $this->lowerRangeFeature($unwrapped, true, $lowerSimpleRanges, $lowerIntervalRanges);
            if ($range !== null) {
                return $range;
            }

            $lowered = $this->lowerRangeSyntaxCondition($inner, $lowerSimpleRanges, $lowerIntervalRanges);
            if (!$lowered['changed']) {
                return [
                    'css' => $condition,
                    'changed' => false,
                    'root' => null,
                    'bareNot' => true,
                ];
            }

            return [
                'css' => 'not (' . $lowered['css'] . ')',
                'changed' => true,
                'root' => null,
                'bareNot' => true,
            ];
        }

        $unwrapped = $this->unwrapSingleParenthesizedValue($condition);
        if ($unwrapped !== null) {
            $range = $this->lowerRangeFeature($unwrapped, false, $lowerSimpleRanges, $lowerIntervalRanges);
            if ($range !== null) {
                return $range;
            }

            $lowered = $this->lowerRangeSyntaxCondition($unwrapped, $lowerSimpleRanges, $lowerIntervalRanges);
            if ($lowered['changed']) {
                return [
                    'css' => '(' . $lowered['css'] . ')',
                    'changed' => true,
                    'root' => null,
                    'bareNot' => false,
                ];
            }
        }

        $range = $this->lowerRangeFeature($condition, false, $lowerSimpleRanges, $lowerIntervalRanges);
        if ($range !== null) {
            return $range;
        }

        return [
            'css' => $condition,
            'changed' => false,
            'root' => null,
            'bareNot' => str_starts_with(strtolower($condition), 'not '),
        ];
    }

    /**
     * @return list<string>|null
     */
    private function splitTopLevelLogical(string $condition, string $operator): ?array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $parenDepth = 0;
        $length = strlen($condition);
        $found = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $condition[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                $current .= $char;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                $current .= $char;
                continue;
            }

            if ($parenDepth === 0 && (ctype_alpha($char) || $char === '_' || $char === '-')) {
                $start = $i;
                while ($i < $length && preg_match('/[-_a-zA-Z0-9]/', $condition[$i]) === 1) {
                    $i++;
                }
                $identifier = substr($condition, $start, $i - $start);
                $previous = $condition[$start - 1] ?? '';
                $next = $condition[$i] ?? '';
                if (strcasecmp($identifier, $operator) === 0
                    && ($previous === '' || preg_match('/[-_a-zA-Z0-9]/', $previous) !== 1)
                    && ($next === '' || preg_match('/[-_a-zA-Z0-9]/', $next) !== 1)
                ) {
                    if (trim($current) === '') {
                        return null;
                    }
                    $parts[] = trim($current);
                    $current = '';
                    $found = true;
                    while (isset($condition[$i]) && ctype_space($condition[$i])) {
                        $i++;
                    }
                    $i--;
                    continue;
                }

                $current .= $identifier;
                $i--;
                continue;
            }

            $current .= $char;
        }

        if (!$found || trim($current) === '') {
            return null;
        }

        $parts[] = trim($current);

        return $parts;
    }

    /**
     * @return array{css:string,changed:bool,root:?string,bareNot:bool}|null
     */
    private function lowerRangeFeature(string $feature, bool $negated, bool $lowerSimpleRanges, bool $lowerIntervalRanges): ?array
    {
        $feature = trim($feature);
        $ident = '[_a-zA-Z-][_a-zA-Z0-9-]*';
        $operator = '<=|>=|<|>';

        if (preg_match('/^(.+?)\s*(' . $operator . ')\s*(' . $ident . ')\s*(' . $operator . ')\s*(.+)$/', $feature, $matches) === 1) {
            if (!$lowerIntervalRanges) {
                return null;
            }

            $name = strtolower($matches[3]);
            if (!$this->isLegacyRangeFeature($name)) {
                return null;
            }

            $left = $this->legacyComparison($name, $this->comparisonFromLeft($matches[2]), $this->minifyValue($matches[1]));
            $right = $this->legacyComparison($name, $matches[4], $this->minifyValue($matches[5]));
            $css = $this->andLegacyComparisons([$left, $right]);
            if ($negated) {
                return [
                    'css' => 'not (' . $css . ')',
                    'changed' => true,
                    'root' => null,
                    'bareNot' => true,
                ];
            }

            return [
                'css' => $css,
                'changed' => true,
                'root' => 'and',
                'bareNot' => false,
            ];
        }

        if (preg_match('/^(' . $ident . ')\s*(' . $operator . ')\s*(.+)$/', $feature, $matches) !== 1) {
            return null;
        }

        if (!$lowerSimpleRanges) {
            return null;
        }

        $name = strtolower($matches[1]);
        if (!$this->isLegacyRangeFeature($name)) {
            return null;
        }

        $comparison = $this->legacyComparison(
            $name,
            $negated ? $this->invertComparison($matches[2]) : $matches[2],
            $this->minifyValue($matches[3])
        );

        return [
            'css' => $comparison['css'],
            'changed' => true,
            'root' => null,
            'bareNot' => $comparison['bareNot'],
        ];
    }

    private function isLegacyRangeFeature(string $feature): bool
    {
        if (str_starts_with($feature, 'min-') || str_starts_with($feature, 'max-')) {
            return false;
        }

        return in_array($feature, ['width', 'height', 'color', 'resolution'], true);
    }

    private function comparisonFromLeft(string $operator): string
    {
        return match ($operator) {
            '<' => '>',
            '<=' => '>=',
            '>' => '<',
            '>=' => '<=',
            default => $operator,
        };
    }

    private function invertComparison(string $operator): string
    {
        return match ($operator) {
            '<' => '>=',
            '<=' => '>',
            '>' => '<=',
            '>=' => '<',
            default => $operator,
        };
    }

    /**
     * @return array{css:string,bareNot:bool}
     */
    private function legacyComparison(string $feature, string $operator, string $value): array
    {
        return match ($operator) {
            '>=' => ['css' => '(min-' . $feature . ':' . $value . ')', 'bareNot' => false],
            '<=' => ['css' => '(max-' . $feature . ':' . $value . ')', 'bareNot' => false],
            '>' => ['css' => 'not (max-' . $feature . ':' . $value . ')', 'bareNot' => true],
            '<' => ['css' => 'not (min-' . $feature . ':' . $value . ')', 'bareNot' => true],
            default => ['css' => '(' . $feature . $operator . $value . ')', 'bareNot' => false],
        };
    }

    /**
     * @param list<array{css:string,bareNot:bool}> $comparisons
     */
    private function andLegacyComparisons(array $comparisons): string
    {
        return implode(' and ', array_map(
            static fn (array $comparison): string => $comparison['bareNot']
                ? '(' . $comparison['css'] . ')'
                : $comparison['css'],
            $comparisons
        ));
    }

    private function unwrapSingleParenthesizedValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value[0] !== '(') {
            return null;
        }

        $close = $this->findMatchingDelimiter($value, 0, '(', ')');
        if ($close !== strlen($value) - 1) {
            return null;
        }

        return substr($value, 1, -1);
    }

    private function containsTopLevelKeyword(string $value, string $keyword): bool
    {
        foreach ($this->splitTopLevelWords($value) as $word) {
            if (strcasecmp($word, $keyword) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelWords(string $value): array
    {
        $words = [];
        $current = '';
        $quote = null;
        $parenDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
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
            }

            if ($parenDepth === 0 && ctype_space($char)) {
                if ($current !== '') {
                    $words[] = $current;
                    $current = '';
                }
                continue;
            }

            if ($parenDepth === 0 && preg_match('/[A-Za-z-]/', $char) === 1) {
                $current .= $char;
                continue;
            }

            if ($current !== '') {
                $words[] = $current;
                $current = '';
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
    private function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [''];
        $quote = null;
        $parenDepth = 0;
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
            } elseif ($char === $delimiter && $parenDepth === 0) {
                $parts[] = '';
                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
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
}
