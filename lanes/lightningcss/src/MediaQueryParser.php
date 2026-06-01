<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class MediaQueryParser
{
    public function minifyList(string $queryList, bool $allowCompactedNegation = false, bool $recoverInvalidFeatureValues = false): string
    {
        $queryList = $this->stripCommentsAsWhitespace($queryList);
        $queries = $this->splitMediaQueryList($queryList);
        if ($queries === []) {
            return '';
        }

        return implode(',', array_map(
            fn (string $query): string => $this->minifyQuery($query, $allowCompactedNegation, $recoverInvalidFeatureValues),
            $queries
        ));
    }

    public function lowerRangeSyntaxList(string $queryList, bool $lowerSimpleRanges = true, bool $lowerIntervalRanges = true): string
    {
        $queries = $this->splitTopLevel($this->minifyList($queryList), ',');
        if ($queries === []) {
            return '';
        }

        return implode(',', array_map(
            fn (string $query): string => $this->lowerRangeSyntaxQuery($query, $lowerSimpleRanges, $lowerIntervalRanges),
            $queries
        ));
    }

    public function useXResolutionUnitList(string $queryList): string
    {
        return $this->convertDppxResolutionUnits($queryList);
    }

    public function useDppxResolutionUnitList(string $queryList): string
    {
        return $this->convertXResolutionUnits($queryList);
    }

    public function alwaysMatchesList(string $queryList): bool
    {
        $queryList = trim($queryList);
        if ($queryList === '') {
            return true;
        }

        $queries = $this->splitTopLevel($this->minifyList($queryList, allowCompactedNegation: true), ',');
        if ($queries === []) {
            return true;
        }

        foreach ($queries as $query) {
            if (strcasecmp($query, 'all') !== 0) {
                return false;
            }
        }

        return true;
    }

    public function neverMatchesList(string $queryList): bool
    {
        $queryList = trim($queryList);
        if ($queryList === '') {
            return false;
        }

        $queries = $this->splitTopLevel($this->minifyList($queryList, allowCompactedNegation: true), ',');
        if ($queries === []) {
            return false;
        }

        foreach ($queries as $query) {
            if (strcasecmp($query, 'not all') !== 0) {
                return false;
            }
        }

        return true;
    }

    public function andQuery(string $left, string $right): string
    {
        $left = $this->parseSingleQueryForConjunction($left);
        $right = $this->parseSingleQueryForConjunction($right);
        [$qualifier, $type] = $this->combineMediaTypeForAnd($left, $right);

        $condition = $left['condition'];
        if ($right['condition'] !== null) {
            $condition = $condition === null || $condition === $right['condition']
                ? $right['condition']
                : $this->combineMediaConditionsForAnd($condition, $right['condition']);
        }

        return $this->serializeMediaQueryForCombination($qualifier, $type, $condition);
    }

    /**
     * @return list<string>
     */
    private function splitMediaQueryList(string $queryList): array
    {
        if (trim($queryList) === '') {
            return [];
        }

        $rawQueries = $this->splitTopLevelPreservingEmpty($queryList, ',');
        $last = count($rawQueries) - 1;
        $queries = [];

        foreach ($rawQueries as $index => $query) {
            $query = trim($query);
            if ($query === '') {
                if ($index === $last && $index > 0) {
                    continue;
                }

                throw new \InvalidArgumentException('Media query list contains an empty query');
            }

            $queries[] = $query;
        }

        return $queries;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelPreservingEmpty(string $value, string $delimiter): array
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

        return $parts;
    }

    private function minifyQuery(string $query, bool $allowCompactedNegation, bool $recoverInvalidFeatureValues): string
    {
        $query = trim($query);
        if ($query === '') {
            throw new \InvalidArgumentException('Media query must not be empty');
        }
        if ($query[0] === '&') {
            throw new \InvalidArgumentException('Media query cannot start with a nesting selector');
        }

        $query = $this->normalizeEscapedMediaKeywords($this->normalizeWhitespace($query));
        $query = $this->normalizeParentheses($query, $allowCompactedNegation, $recoverInvalidFeatureValues);
        $query = preg_replace_callback(
            '/\b(and|or)\b/i',
            static fn (array $matches): string => ' ' . strtolower($matches[1]) . ' ',
            $query
        ) ?? $query;
        $query = $this->normalizeWhitespace($query);
        $this->validateTopLevelLogicalOperators($query);
        $this->validateTopLevelConditionFunctions($query);
        $this->validateTopLevelConditionOperationOperands($query);
        $query = $this->normalizeBooleanConditionGroups($query);
        $this->validateExplicitMediaTypeConditionSeparator($query);
        $this->validateExplicitMediaTypeCondition($query);
        $query = $this->invertNegatedSimpleRangeConditions($query);
        $query = $this->normalizeRedundantTopLevelConditionWrappers($query);
        $query = preg_replace_callback('/^(not|only)\s+(screen|print|all)\b/i', static fn (array $m): string => strtolower($m[1]) . ' ' . strtolower($m[2]), $query) ?? $query;
        $query = preg_replace_callback('/^(screen|print|all)\b/i', static fn (array $m): string => strtolower($m[1]), $query) ?? $query;

        return trim($query);
    }

    private function validateExplicitMediaTypeCondition(string $query): void
    {
        $ident = $this->cssIdentifierPattern();
        if (preg_match('/^(?:(not|only)\s+)?' . $ident . '\s+or(?:\s|$)/i', $query) === 1) {
            throw new \InvalidArgumentException('Media query conditions after an explicit media type cannot use top-level or');
        }

        $mediaPrefix = $this->extractExplicitMediaTypePrefix($query);
        if ($mediaPrefix === null) {
            return;
        }

        if ($this->splitTopLevelLogical($mediaPrefix['condition'], 'or') !== null) {
            throw new \InvalidArgumentException('Media query conditions after an explicit media type cannot contain top-level or');
        }

        $andParts = $this->splitTopLevelLogical($mediaPrefix['condition'], 'and');
        if ($andParts !== null) {
            foreach ($andParts as $part) {
                if (!$this->isSingleParenthesizedCondition($part)) {
                    throw new \InvalidArgumentException("Invalid media query condition operand: {$part}");
                }
            }

            return;
        }

        $condition = trim($mediaPrefix['condition']);
        if ($this->isSingleParenthesizedCondition($condition)) {
            return;
        }

        if ($this->startsKeywordAt($condition, 0, 'not')) {
            $tail = trim(substr($condition, 3));
            if ($this->isSingleParenthesizedCondition($tail)) {
                return;
            }
        }

        throw new \InvalidArgumentException("Invalid media query condition operand: {$condition}");
    }

    private function validateExplicitMediaTypeConditionSeparator(string $query): void
    {
        $ident = $this->cssIdentifierPattern();
        if (preg_match('/^(?:(not|only)\s+)?(' . $ident . ')\s+(.+)$/i', $query, $matches) !== 1) {
            return;
        }

        $type = $this->canonicalMediaTypeIdentifier($matches[2]);
        $tail = ltrim($matches[3]);
        if ($type === 'not' && str_starts_with($tail, '(')) {
            return;
        }
        if (($type === 'not' || $type === 'only') && preg_match('/^' . $ident . '$/', $tail) === 1) {
            return;
        }

        if (preg_match('/^and(?:\s|$)/i', $tail) !== 1) {
            throw new \InvalidArgumentException('Media query condition after an explicit media type must start with and');
        }
    }

    private function validateTopLevelLogicalOperators(string $query): void
    {
        if (preg_match('/^(?:not|only)$/i', $query) === 1) {
            throw new \InvalidArgumentException('Media query qualifier must be followed by a media type or condition');
        }

        $quote = null;
        $hasOperand = false;
        $waitingForOperand = false;
        $logicalOperator = null;
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

            if (ctype_space($char)) {
                continue;
            }

            if ($char === '(') {
                $i = $this->findMatchingDelimiter($query, $i, '(', ')');
                $hasOperand = true;
                $waitingForOperand = false;
                continue;
            }

            if (preg_match('/[-_a-zA-Z]/', $char) !== 1) {
                continue;
            }

            $start = $i;
            while ($i < $length && preg_match('/[-_a-zA-Z0-9]/', $query[$i]) === 1) {
                $i++;
            }

            $identifier = strtolower(substr($query, $start, $i - $start));
            if ($identifier === 'and' || $identifier === 'or') {
                if (!$hasOperand || $waitingForOperand) {
                    throw new \InvalidArgumentException("Invalid media query boolean operator: {$identifier}");
                }
                if ($logicalOperator !== null && $logicalOperator !== $identifier) {
                    throw new \InvalidArgumentException('Media query boolean operators must be grouped when mixing and/or');
                }

                $logicalOperator = $identifier;
                $hasOperand = false;
                $waitingForOperand = true;
                $i--;
                continue;
            }

            $hasOperand = true;
            $waitingForOperand = false;
            $i--;
        }

        if ($waitingForOperand) {
            throw new \InvalidArgumentException('Media query boolean operator must be followed by a condition');
        }
    }

    private function validateConditionOperationOperands(string $condition): void
    {
        $parts = $this->splitTopLevelLogical($condition, 'or')
            ?? $this->splitTopLevelLogical($condition, 'and')
            ?? [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $this->isSingleParenthesizedCondition($part)) {
                continue;
            }

            throw new \InvalidArgumentException("Invalid media query condition operand: {$part}");
        }
    }

    private function validateTopLevelConditionOperationOperands(string $query): void
    {
        $mediaPrefix = $this->extractExplicitMediaTypePrefix($query);
        if ($mediaPrefix !== null) {
            $this->validateConditionOperationOperands($mediaPrefix['condition']);
            return;
        }

        $this->validateConditionOperationOperands($query);
    }

    private function isSingleParenthesizedCondition(string $condition): bool
    {
        $condition = trim($condition);
        if (($condition[0] ?? '') !== '(') {
            return false;
        }

        return $this->findMatchingDelimiter($condition, 0, '(', ')') === strlen($condition) - 1;
    }

    private function normalizeParentheses(string $source, bool $allowCompactedNegation, bool $recoverInvalidFeatureValues = false): string
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
            $output .= '(' . $this->minifyParenthesized($inner, $allowCompactedNegation, $recoverInvalidFeatureValues) . ')';
            $i = $close;
        }

        return $output;
    }

    private function minifyParenthesized(string $inner, bool $allowCompactedNegation, bool $recoverInvalidFeatureValues): string
    {
        $inner = trim($inner);
        if ($inner === '') {
            throw new \InvalidArgumentException('Empty brackets are invalid in media query conditions');
        }

        if ($allowCompactedNegation && strncasecmp($inner, 'not(', 4) === 0) {
            $close = $this->findMatchingDelimiter($inner, 3, '(', ')');
            if ($close === strlen($inner) - 1) {
                $inner = 'not ' . substr($inner, 3);
            }
        }

        if ($this->containsTopLevelKeyword($inner, 'and') || $this->containsTopLevelKeyword($inner, 'or')) {
            $inner = $this->normalizeWhitespace($inner);
            $this->validateTopLevelLogicalOperators($inner);
            $this->validateConditionOperationOperands($inner);

            return $this->normalizeParentheses($inner, $allowCompactedNegation, $recoverInvalidFeatureValues);
        }

        if (preg_match('/^not\s+(.+)$/i', $inner, $matches) === 1) {
            $condition = $this->normalizeWhitespace($matches[1]);
            if (!$this->isSingleParenthesizedCondition($condition)) {
                throw new \InvalidArgumentException('Media query negation must be followed by a parenthesized condition');
            }

            return 'not ' . $this->normalizeParentheses($condition, $allowCompactedNegation, $recoverInvalidFeatureValues);
        }

        if ($inner[0] === '(') {
            return $this->normalizeParentheses($this->normalizeWhitespace($inner), $allowCompactedNegation, $recoverInvalidFeatureValues);
        }

        return $this->minifyFeature($inner, $recoverInvalidFeatureValues);
    }

    private function minifyFeature(string $feature, bool $recoverInvalidFeatureValues = false): string
    {
        $feature = $this->normalizeWhitespace($feature);
        if ($this->containsTopLevelDelimiter($feature, ',')) {
            throw new \InvalidArgumentException("Invalid media query feature: {$feature}");
        }

        $ident = $this->cssIdentifierPattern();
        if (preg_match('/^(.+?)\s*(<=|>=|<|>|=)\s*(' . $ident . ')\s*(<=|>=|<|>|=)\s*(.+)$/', $feature, $matches) === 1) {
            if (!$this->isValidIntervalComparisonPair($matches[2], $matches[4])) {
                throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
            }
            $name = $this->canonicalMediaFeatureIdentifier($matches[3]);
            $type = $this->rangeComparableMediaFeatureType($name);
            $this->validateRangeFeature($name, $matches[1], $matches[5], $feature);

            return $this->minifyValue($matches[1], $type) . $matches[2] . $name . $matches[4] . $this->minifyValue($matches[5], $type);
        }

        if (preg_match('/^(' . $ident . ')\s*(<=|>=|<|>|=)\s*(.+)$/', $feature, $matches) === 1) {
            $name = $this->canonicalMediaFeatureIdentifier($matches[1]);
            if ($matches[2] === '=' && !$this->isRangeComparableMediaFeature($name)) {
                throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
            }
            $type = $this->rangeComparableMediaFeatureType($name);
            $this->validateRangeFeature($name, $matches[3], null, $feature, $recoverInvalidFeatureValues);

            return $name . $matches[2] . $this->minifyValue($matches[3], $type);
        }

        if (preg_match('/^(.+?)\s*(<=|>=|<|>|=)\s*(' . $ident . ')$/', $feature, $matches) === 1) {
            $name = $this->canonicalMediaFeatureIdentifier($matches[3]);
            if ($matches[2] === '=' && !$this->isRangeComparableMediaFeature($name)) {
                throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
            }
            $type = $this->rangeComparableMediaFeatureType($name);
            $this->validateRangeFeature($name, $matches[1], null, $feature);

            return $name . $this->oppositeComparison($matches[2]) . $this->minifyValue($matches[1], $type);
        }

        if (preg_match('/^(' . $ident . ')\s*:\s*(.+)$/', $feature, $matches) === 1) {
            $name = $this->canonicalMediaFeatureIdentifier($matches[1]);
            if (preg_match('/^-webkit-(min|max)-(.+)$/', $name, $legacyMatches) === 1) {
                $canonical = '-webkit-' . $legacyMatches[2];
                $type = $this->knownMediaFeatureType($canonical);
                if ($type !== null) {
                    if (!$this->mediaFeatureTypeAllowsRanges($type)) {
                        throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
                    }

                    $this->validateRangeFeature($canonical, $matches[2], null, $feature, $recoverInvalidFeatureValues);
                    $operator = $legacyMatches[1] === 'min' ? '>=' : '<=';

                    return $canonical . $operator . $this->minifyValue($matches[2], $type);
                }
            }

            if (preg_match('/^(min|max)-(.+)$/', $name, $legacyMatches) === 1) {
                $canonical = $legacyMatches[2];
                $type = $this->knownMediaFeatureType($canonical);
                if ($type !== null && !$this->mediaFeatureTypeAllowsRanges($type)) {
                    throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
                }
                if ($type !== null) {
                    $this->validateRangeFeature($canonical, $matches[2], null, $feature, $recoverInvalidFeatureValues);
                    $operator = $legacyMatches[1] === 'min' ? '>=' : '<=';

                    return $canonical . $operator . $this->minifyValue($matches[2], $type);
                }
            }

            $this->validateDiscreteMediaFeature($name, $matches[2], $feature, $recoverInvalidFeatureValues);

            return $name . ':' . $this->minifyValue($matches[2], $this->knownMediaFeatureType($name));
        }

        if (preg_match('/^[_a-zA-Z-][_a-zA-Z0-9-]*\(/', $feature) === 1) {
            throw new \InvalidArgumentException("Unknown media query condition function: {$feature}");
        }

        $canonicalLegacyName = $this->canonicalLegacyMediaFeatureName($feature);
        if ($canonicalLegacyName !== null) {
            return $canonicalLegacyName;
        }

        return $this->canonicalMediaFeatureIdentifier(str_replace(' ', '', $feature));
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
            if (($query[$i] ?? '') === '(' && $identifier !== 'not') {
                throw new \InvalidArgumentException('Unknown media query condition function: ' . substr($query, $start));
            }

            $i--;
        }
    }

    private function validateRangeFeature(string $name, string $leftValue, ?string $rightValue, string $feature, bool $recoverInvalidFeatureValues = false): void
    {
        $type = $this->rangeComparableMediaFeatureType($name);
        if ($type === null) {
            throw new \InvalidArgumentException("Invalid media query range feature: {$feature}");
        }

        foreach ([$leftValue, $rightValue] as $value) {
            if ($value === null) {
                continue;
            }
            if (!$this->isValidRangeValue($type, $value)
                && (!$recoverInvalidFeatureValues || !$this->isRecoverableInvalidFeatureValue($value))
            ) {
                throw new \InvalidArgumentException("Invalid media query range value: {$feature}");
            }
        }
    }

    private function validateDiscreteMediaFeature(string $name, string $value, string $feature, bool $recoverInvalidFeatureValues = false): void
    {
        $type = $this->knownMediaFeatureType($name) ?? 'unknown';
        if (!$this->isValidDiscreteMediaFeatureValue($type, $value)
            && (!$recoverInvalidFeatureValues || !$this->isRecoverableInvalidFeatureValue($value))
        ) {
            throw new \InvalidArgumentException("Invalid media query feature value: {$feature}");
        }
    }

    private function isValidDiscreteMediaFeatureValue(string $type, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^env\(/i', $value) === 1) {
            return true;
        }

        return match ($type) {
            'boolean' => preg_match('/^[+-]?\d+$/', $value) === 1 && in_array((int) $value, [0, 1], true),
            'ident' => preg_match('/^' . $this->cssIdentifierPattern() . '$/', $value) === 1,
            'integer',
            'number',
            'length',
            'resolution',
            'ratio',
            'unknown' => $this->isValidRangeValue($type, $value),
            default => false,
        };
    }

    private function isRangeComparableMediaFeature(string $name): bool
    {
        return $this->rangeComparableMediaFeatureType($name) !== null;
    }

    private function rangeComparableMediaFeatureType(string $name): ?string
    {
        $type = $this->knownMediaFeatureType($name);
        if ($type !== null) {
            return $this->mediaFeatureTypeAllowsRanges($type) ? $type : null;
        }

        if ($this->isLegacyKnownMediaFeatureName($name)) {
            return null;
        }

        return 'unknown';
    }

    private function knownMediaFeatureType(string $name): ?string
    {
        return match ($name) {
            'width', 'height', 'device-width', 'device-height' => 'length',
            'aspect-ratio', 'device-aspect-ratio' => 'ratio',
            'color', 'color-index', 'monochrome', 'horizontal-viewport-segments', 'vertical-viewport-segments' => 'integer',
            'resolution' => 'resolution',
            '-webkit-device-pixel-ratio', '-moz-device-pixel-ratio' => 'number',
            'grid' => 'boolean',
            'orientation',
            'overflow-block',
            'overflow-inline',
            'display-mode',
            'scan',
            'update',
            'environment-blending',
            'color-gamut',
            'dynamic-range',
            'inverted-colors',
            'pointer',
            'hover',
            'any-pointer',
            'any-hover',
            'nav-controls',
            'video-color-gamut',
            'video-dynamic-range',
            'scripting',
            'prefers-reduced-motion',
            'prefers-reduced-transparency',
            'prefers-contrast',
            'forced-colors',
            'prefers-color-scheme',
            'prefers-reduced-data' => 'ident',
            default => null,
        };
    }

    private function canonicalLegacyMediaFeatureName(string $name): ?string
    {
        $name = $this->canonicalMediaFeatureIdentifier($name);
        if (preg_match('/^-webkit-(?:min|max)-(.+)$/', $name, $matches) === 1) {
            $canonical = '-webkit-' . $matches[1];

            return $this->knownMediaFeatureType($canonical) !== null ? $canonical : null;
        }

        if (preg_match('/^(?:min|max)-(.+)$/', $name, $matches) === 1) {
            $canonical = $matches[1];

            return $this->knownMediaFeatureType($canonical) !== null ? $canonical : null;
        }

        return null;
    }

    private function mediaFeatureTypeAllowsRanges(string $type): bool
    {
        return in_array($type, ['length', 'number', 'integer', 'resolution', 'ratio', 'unknown'], true);
    }

    private function isLegacyKnownMediaFeatureName(string $name): bool
    {
        if (preg_match('/^(min|max)-(.+)$/i', $name, $matches) === 1) {
            return $this->knownMediaFeatureType(strtolower($matches[2])) !== null;
        }

        if (preg_match('/^-webkit-(min|max)-(.+)$/i', $name, $matches) === 1) {
            return $this->knownMediaFeatureType('-webkit-' . strtolower($matches[2])) !== null;
        }

        return false;
    }

    private function isValidRangeValue(string $type, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^env\(/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^(?:calc|clamp|max|min)\(/i', $value) === 1) {
            return match ($type) {
                'integer', 'resolution' => false,
                'number' => $this->foldSimpleUnitlessCalc($value) !== null
                    || preg_match('/^(?:clamp|max|min)\(/i', $value) === 1,
                default => true,
            };
        }

        if (str_starts_with($value, '--styled-jsx-placeholder-')) {
            return true;
        }

        $number = $this->cssNumberPattern();

        return match ($type) {
            'integer' => preg_match('/^[+-]?\d+$/', $value) === 1,
            'number' => preg_match('/^' . $number . '$/', $value) === 1,
            'resolution' => preg_match('/^' . $number . '(?:dpcm|dpi|dppx|x)$/i', $value) === 1,
            'ratio' => preg_match('/^' . $number . '(?:\s*\/\s*' . $number . ')?$/', $value) === 1,
            'unknown' => $this->isValidUnknownRangeValue($value),
            default => preg_match('/^' . $number . '$/', $value) === 1
                || preg_match('/^' . $number . '(?:[a-zA-Z%]+)$/', $value) === 1,
        };
    }

    private function isValidUnknownRangeValue(string $value): bool
    {
        $number = $this->cssNumberPattern();

        return preg_match('/^' . $number . '$/', $value) === 1
            || preg_match('/^' . $number . '(?:\s*\/\s*' . $number . ')$/', $value) === 1
            || preg_match('/^' . $number . '(?:[a-zA-Z%]+)$/', $value) === 1
            || preg_match('/^' . $number . '(?:dpcm|dpi|dppx|x)$/i', $value) === 1
            || preg_match('/^' . $this->cssIdentifierPattern() . '$/', $value) === 1
            || preg_match('/^[-_a-zA-Z][-_a-zA-Z0-9]*$/', $value) === 1;
    }

    private function isRecoverableInvalidFeatureValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^(?:calc|clamp|max|min)\(/i', $value) === 1) {
            return true;
        }

        return $this->isValidUnknownRangeValue($value);
    }

    private function isValidIntervalComparisonPair(string $startOperator, string $endOperator): bool
    {
        $less = ['<', '<='];
        $greater = ['>', '>='];

        return (in_array($startOperator, $less, true) && in_array($endOperator, $less, true))
            || (in_array($startOperator, $greater, true) && in_array($endOperator, $greater, true));
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

    private function minifyValue(string $value, ?string $type = null): string
    {
        $value = trim($value);
        $value = $this->foldSimpleCalc($value);
        $value = $this->foldSimpleMathFunction($value, $type);
        $value = $this->minifyFunctionCommas($value);
        $value = preg_replace('/\s*\/\s*/', '/', $value) ?? $value;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\/1$/', $value, $matches) === 1) {
            return $this->trimNumber($matches[1]);
        }
        if (preg_match('/^' . $this->cssIdentifierPattern() . '$/', $value) === 1) {
            return $this->canonicalMediaIdentifierValue($value);
        }
        if ($type === 'length' && preg_match('/^' . $this->cssNumberPattern() . '$/', $value) === 1 && (float) $value !== 0.0) {
            return $this->trimNumber($value) . 'px';
        }

        return $this->minifyNumericValue($value);
    }

    private function foldSimpleMathFunction(string $value, ?string $type): string
    {
        if (!in_array($type, ['length', 'number', 'unknown'], true)) {
            return $value;
        }

        if (preg_match('/^(min|max|clamp)\(/i', $value, $matches) !== 1) {
            return $value;
        }

        $function = strtolower($matches[1]);
        $open = strlen($matches[1]);
        try {
            if ($this->findMatchingDelimiter($value, $open, '(', ')') !== strlen($value) - 1) {
                return $value;
            }
        } catch (\InvalidArgumentException) {
            return $value;
        }

        $args = $this->splitTopLevel(substr($value, $open + 1, -1), ',');
        if (($function === 'clamp' && count($args) !== 3) || ($function !== 'clamp' && count($args) < 2)) {
            return $value;
        }

        $values = [];
        foreach ($args as $arg) {
            $comparable = $this->comparableMathValue($arg, $type);
            if ($comparable === null) {
                return $value;
            }

            $values[] = $comparable;
        }

        if (!$this->mathValuesShareComparableUnit($values)) {
            return $value;
        }

        if ($function === 'min' || $function === 'max') {
            $selected = $values[0];
            foreach (array_slice($values, 1) as $candidate) {
                if (($function === 'min' && $candidate['number'] < $selected['number'])
                    || ($function === 'max' && $candidate['number'] > $selected['number'])
                ) {
                    $selected = $candidate;
                }
            }

            return $this->formatComparableMathValue($selected, $type);
        }

        [$minimum, $center, $maximum] = $values;
        if ($center['number'] > $maximum['number']) {
            $center = $maximum;
        }
        if ($center['number'] < $minimum['number']) {
            $center = $minimum;
        }

        return $this->formatComparableMathValue($center, $type);
    }

    /**
     * @return array{number:float,unit:string}|null
     */
    private function comparableMathValue(string $value, ?string $type): ?array
    {
        $value = trim($value);
        $value = $this->foldSimpleCalc($value);
        $folded = $this->foldSimpleMathFunction($value, $type);
        $value = $this->minifyNumericValue($folded);
        $number = $this->cssNumberPattern();

        if (preg_match('/^(' . $number . ')([a-zA-Z%]+)?$/', $value, $matches) !== 1) {
            return null;
        }

        $unit = strtolower($matches[2] ?? '');
        if ($type === 'number' && $unit !== '') {
            return null;
        }

        if ($type === 'length' && $unit === '' && (float) $matches[1] !== 0.0) {
            $unit = 'px';
        }

        return [
            'number' => (float) $matches[1],
            'unit' => $unit,
        ];
    }

    /**
     * @param list<array{number:float,unit:string}> $values
     */
    private function mathValuesShareComparableUnit(array $values): bool
    {
        if ($values === []) {
            return false;
        }

        $unit = $values[0]['unit'];
        foreach ($values as $value) {
            if ($value['unit'] !== $unit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{number:float,unit:string} $value
     */
    private function formatComparableMathValue(array $value, ?string $type): string
    {
        $number = $this->trimNumber(rtrim(rtrim(sprintf('%.12F', $value['number']), '0'), '.'));
        if ($number === '-0') {
            $number = '0';
        }

        if ($type === 'length' && $number === '0') {
            return '0';
        }

        return $number . $value['unit'];
    }

    private function minifyNumericValue(string $value): string
    {
        $number = $this->cssNumberPattern();
        if (preg_match('/^(' . $number . ')([a-zA-Z%]+)$/', $value, $matches) === 1) {
            $numeric = $this->trimNumber($matches[1]);
            $unit = strtolower($matches[2]);
            if ($numeric === '0' && !in_array($unit, ['dpcm', 'dpi', 'dppx', 'x'], true)) {
                return '0';
            }

            return $numeric . $unit;
        }

        if (preg_match('/^(' . $number . ')\/(' . $number . ')$/', $value, $matches) === 1) {
            $left = $this->trimNumber($matches[1]);
            $right = $this->trimNumber($matches[2]);

            return $right === '1' ? $left : $left . '/' . $right;
        }

        if (preg_match('/^(' . $number . ')$/', $value, $matches) === 1) {
            return $this->trimNumber($matches[1]);
        }

        return $value;
    }

    private function minifyFunctionCommas(string $value): string
    {
        $output = '';
        $quote = null;
        $parenDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $value[++$i];
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

            if ($char === '(') {
                $parenDepth++;
                $output .= $char;
                while (isset($value[$i + 1]) && ctype_space($value[$i + 1])) {
                    $i++;
                }
                continue;
            }

            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                $output = rtrim($output);
                $output .= $char;
                continue;
            }

            if ($char === ',' && $parenDepth > 0) {
                $output = rtrim($output) . ',';
                while (isset($value[$i + 1]) && ctype_space($value[$i + 1])) {
                    $i++;
                }
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function convertDppxResolutionUnits(string $queryList): string
    {
        $number = $this->cssNumberPattern();
        $queryList = preg_replace_callback(
            '/(\b(?:min-|max-)?resolution\s*(?::|[<>]=?|=)\s*)(' . $number . ')dppx\b/i',
            fn (array $matches): string => $matches[1] . $this->trimNumber($matches[2]) . 'x',
            $queryList
        ) ?? $queryList;

        return preg_replace_callback(
            '/(' . $number . ')dppx(\s*(?:[<>]=?|=)\s*resolution\b)/i',
            fn (array $matches): string => $this->trimNumber($matches[1]) . 'x' . $matches[2],
            $queryList
        ) ?? $queryList;
    }

    private function convertXResolutionUnits(string $queryList): string
    {
        $number = $this->cssNumberPattern();
        $queryList = preg_replace_callback(
            '/(\b(?:min-|max-)?resolution\s*(?::|[<>]=?|=)\s*)(' . $number . ')x\b/i',
            fn (array $matches): string => $matches[1] . $this->trimNumber($matches[2]) . 'dppx',
            $queryList
        ) ?? $queryList;

        return preg_replace_callback(
            '/(' . $number . ')x(\s*(?:[<>]=?|=)\s*resolution\b)/i',
            fn (array $matches): string => $this->trimNumber($matches[1]) . 'dppx' . $matches[2],
            $queryList
        ) ?? $queryList;
    }

    private function foldSimpleCalc(string $value): string
    {
        $unitless = $this->foldSimpleUnitlessCalc($value);
        if ($unitless !== null) {
            return $unitless;
        }

        $number = $this->cssNumberPattern();
        if (preg_match('/^calc\(\s*(' . $number . ')([a-zA-Z%]+)\s*([+-])\s*(' . $number . ')\2\s*\)$/', $value, $matches) !== 1) {
            return preg_replace_callback(
                '/^calc\(\s*(.+)\s*\)$/',
                fn (array $m): string => 'calc(' . $this->normalizeCalcOperatorSpacing(trim($m[1])) . ')',
                $value
            ) ?? $value;
        }

        $left = (float) $matches[1];
        $right = (float) $matches[4];
        $result = $matches[3] === '+' ? $left + $right : $left - $right;

        return $this->trimNumber((string) $result) . strtolower($matches[2]);
    }

    private function foldSimpleUnitlessCalc(string $value): ?string
    {
        $number = $this->cssNumberPattern();
        if (preg_match('/^calc\(\s*(' . $number . ')\s*([+\-*\/])\s*(' . $number . ')\s*\)$/', $value, $matches) !== 1) {
            return null;
        }

        $left = (float) $matches[1];
        $right = (float) $matches[3];
        if ($matches[2] === '/' && abs($right) < PHP_FLOAT_EPSILON) {
            return null;
        }

        $result = match ($matches[2]) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $left / $right,
            default => null,
        };
        if ($result === null || !is_finite($result)) {
            return null;
        }

        return $this->trimNumber(rtrim(rtrim(sprintf('%.8F', $result), '0'), '.'));
    }

    private function normalizeCalcOperatorSpacing(string $value): string
    {
        $output = '';
        $quote = null;
        $parenDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $value[++$i];
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

            if ($char === '(') {
                $parenDepth++;
                $output .= $char;
                continue;
            }

            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                $output = rtrim($output);
                $output .= $char;
                continue;
            }

            if ($parenDepth === 0 && ($char === '+' || $char === '-') && $this->isBinaryCalcAdditiveOperator($value, $i)) {
                $output = rtrim($output) . ' ' . $char . ' ';
                while (isset($value[$i + 1]) && ctype_space($value[$i + 1])) {
                    $i++;
                }
                continue;
            }

            $output .= $char;
        }

        return $this->normalizeWhitespace($output);
    }

    private function isBinaryCalcAdditiveOperator(string $value, int $offset): bool
    {
        $previous = null;
        for ($i = $offset - 1; $i >= 0; $i--) {
            if (!ctype_space($value[$i])) {
                $previous = $value[$i];
                break;
            }
        }

        $next = null;
        for ($i = $offset + 1, $length = strlen($value); $i < $length; $i++) {
            if (!ctype_space($value[$i])) {
                $next = $value[$i];
                break;
            }
        }

        if ($previous === null || $next === null || str_contains('(,*/+-', $previous)) {
            return false;
        }

        if (($previous === 'e' || $previous === 'E') && preg_match('/[0-9.]/', $value[$offset - 2] ?? '') === 1 && ctype_digit($next)) {
            return false;
        }

        return true;
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

    private function stripCommentsAsWhitespace(string $value): string
    {
        $output = '';
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $value[++$i];
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

            if ($char === '/' && ($value[$i + 1] ?? '') === '*') {
                $output .= ' ';
                $end = strpos($value, '*/', $i + 2);
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

    private function normalizeEscapedMediaKeywords(string $query): string
    {
        $output = '';
        $quote = null;
        $length = strlen($query);
        $identifier = '/^' . $this->cssIdentifierPattern() . '/';
        $keywords = ['and', 'or', 'not', 'only', 'screen', 'print', 'all'];

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

            if (preg_match($identifier, substr($query, $i), $matches) === 1) {
                $raw = $matches[0];
                $decoded = strtolower($this->decodeCssIdentifier($raw));
                $output .= in_array($decoded, $keywords, true) ? $decoded : $raw;
                $i += strlen($raw) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function cssIdentifierPattern(): string
    {
        $escape = '\\\\(?:[0-9a-fA-F]{1,6}\s?|[^\r\n\f])';
        $start = '(?:[_a-zA-Z]|' . $escape . ')';
        $continue = '(?:[-_a-zA-Z0-9]|' . $escape . ')';

        return '(?:(?:--)|-?' . $start . ')' . $continue . '*';
    }

    private function cssNumberPattern(): string
    {
        return '[+-]?(?:(?:\d+\.\d*|\d*\.\d+|\d+)(?:[eE][+-]?\d+)?)';
    }

    private function isZeroNumber(string $value): bool
    {
        return preg_match('/^' . $this->cssNumberPattern() . '$/', $value) === 1 && (float) $value === 0.0;
    }

    private function canonicalMediaTypeIdentifier(string $identifier): string
    {
        $decoded = $this->decodeCssIdentifier($identifier);
        if (!$this->isSafeCssIdentifier($decoded)) {
            return strtolower(trim($identifier));
        }

        $lower = strtolower($decoded);

        return in_array($lower, ['all', 'print', 'screen'], true) ? $lower : $decoded;
    }

    private function canonicalMediaFeatureIdentifier(string $identifier): string
    {
        $decoded = $this->decodeCssIdentifier($identifier);
        if (!$this->isSafeCssIdentifier($decoded)) {
            return strtolower(trim($identifier));
        }

        $lower = strtolower($decoded);
        if ($this->knownMediaFeatureType($lower) !== null) {
            return $lower;
        }

        if (preg_match('/^-webkit-(min|max)-(.+)$/i', $decoded, $matches) === 1) {
            $canonical = '-webkit-' . strtolower($matches[2]);
            if ($this->knownMediaFeatureType($canonical) !== null) {
                return '-webkit-' . strtolower($matches[1]) . '-' . strtolower($matches[2]);
            }
        }

        if (preg_match('/^(min|max)-(.+)$/i', $decoded, $matches) === 1
            && $this->knownMediaFeatureType(strtolower($matches[2])) !== null
        ) {
            return strtolower($matches[1]) . '-' . strtolower($matches[2]);
        }

        return $decoded;
    }

    private function canonicalMediaIdentifierValue(string $identifier): string
    {
        $decoded = $this->decodeCssIdentifier($identifier);

        return $this->isSafeCssIdentifier($decoded)
            ? $decoded
            : trim($identifier);
    }

    private function decodeCssIdentifier(string $identifier): string
    {
        $decoded = '';
        $length = strlen($identifier);
        for ($i = 0; $i < $length; $i++) {
            if ($identifier[$i] !== '\\') {
                $decoded .= $identifier[$i];
                continue;
            }

            $escape = $this->readCssEscape($identifier, $i);
            if ($escape === null) {
                $decoded .= $identifier[$i];
                continue;
            }

            $decoded .= $escape['decoded'];
            $i = $escape['end'] - 1;
        }

        return $decoded;
    }

    /**
     * @return array{decoded:string,end:int}|null
     */
    private function readCssEscape(string $value, int $offset): ?array
    {
        $length = strlen($value);
        if (($value[$offset] ?? '') !== '\\' || $offset + 1 >= $length) {
            return null;
        }

        $next = $value[$offset + 1];
        if ($next === "\n" || $next === "\r" || $next === "\f") {
            return null;
        }

        if (!ctype_xdigit($next)) {
            return [
                'decoded' => $next,
                'end' => $offset + 2,
            ];
        }

        $hex = '';
        $cursor = $offset + 1;
        while ($cursor < $length && strlen($hex) < 6 && ctype_xdigit($value[$cursor])) {
            $hex .= $value[$cursor];
            $cursor++;
        }

        if ($cursor < $length && ctype_space($value[$cursor])) {
            $cursor++;
        }

        return [
            'decoded' => $this->codepointToUtf8((int) hexdec($hex)),
            'end' => $cursor,
        ];
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0 || ($codepoint >= 0xd800 && $codepoint <= 0xdfff) || $codepoint > 0x10ffff) {
            $codepoint = 0xfffd;
        }

        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_NOQUOTES, 'UTF-8');
    }

    private function isSafeCssIdentifier(string $identifier): bool
    {
        return $identifier !== ''
            && $identifier !== '-'
            && $identifier !== '--'
            && preg_match('/^(?:--(?:[-_a-zA-Z0-9]|[^\x00-\x7f])*|-?(?:[_a-zA-Z]|[^\x00-\x7f])(?:[-_a-zA-Z0-9]|[^\x00-\x7f])*)$/', $identifier) === 1;
    }

    private function normalizeBooleanConditionGroups(string $query): string
    {
        $query = preg_replace('/\bnot\s*\(/i', 'not (', $query) ?? $query;
        $query = $this->simplifyNotWrappedConditions($query);
        do {
            $previous = $query;
            $query = $this->flattenRedundantBooleanGroups($query, 'and');
            $query = $this->flattenRedundantBooleanGroups($query, 'or');
        } while ($query !== $previous);

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
            $stripped = $this->stripRedundantNegationParentheses($inner);

            $output .= 'not ';
            $output .= '(' . $stripped . ')';

            $i = $close;
        }

        return $output;
    }

    private function invertNegatedSimpleRangeConditions(string $query): string
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
            $doubleNegated = $this->normalizeDoubleNegatedCondition(trim($inner));
            if ($doubleNegated !== null) {
                $skipOuterWrapper = str_ends_with($output, '(') && ($query[$close + 1] ?? '') === ')';
                if ($skipOuterWrapper) {
                    $output = substr($output, 0, -1);
                    $close++;
                }

                $output .= $doubleNegated;
                $i = $close;
                continue;
            }

            $inverted = $this->invertSimpleRangeFeature(trim($inner));
            if ($inverted === null) {
                $output .= substr($query, $i, $close - $i + 1);
                $i = $close;
                continue;
            }

            $skipOuterWrapper = str_ends_with($output, '(') && ($query[$close + 1] ?? '') === ')';
            if ($skipOuterWrapper) {
                $output = substr($output, 0, -1);
                $close++;
            }

            $output .= '(' . $inverted . ')';
            $i = $close;
        }

        return $this->normalizeWhitespace($output);
    }

    private function normalizeDoubleNegatedCondition(string $inner): ?string
    {
        if (preg_match('/^not\s+(.+)$/i', $inner, $matches) !== 1) {
            return null;
        }

        $condition = $this->unwrapSingleParenthesizedValue(trim($matches[1]));
        if ($condition === null) {
            return null;
        }

        $condition = $this->normalizeBooleanConditionGroups($this->normalizeParentheses($condition, false));
        if ($this->containsTopLevelKeyword($condition, 'and') || $this->containsTopLevelKeyword($condition, 'or')) {
            return $condition;
        }

        while (($unwrapped = $this->unwrapSingleParenthesizedValue($condition)) !== null
            && !$this->containsTopLevelKeyword($unwrapped, 'and')
            && !$this->containsTopLevelKeyword($unwrapped, 'or')
        ) {
            $condition = $unwrapped;
        }

        return '(' . $this->minifyFeature($condition) . ')';
    }

    private function normalizeRedundantTopLevelConditionWrappers(string $query): string
    {
        $mediaPrefix = $this->extractExplicitMediaTypePrefix($query);
        if ($mediaPrefix !== null) {
            $condition = $this->collapseRedundantConditionWrappers(
                $mediaPrefix['condition'],
                $mediaPrefix['qualifier'] !== null || $mediaPrefix['type'] !== 'all'
            );
            if ($mediaPrefix['qualifier'] === null && $mediaPrefix['type'] === 'all') {
                return $this->collapseAllMediaConditionWrapper($condition);
            }

            $prefix = $mediaPrefix['qualifier'] === null
                ? $mediaPrefix['type']
                : $mediaPrefix['qualifier'] . ' ' . $mediaPrefix['type'];

            return $prefix . ' and ' . $condition;
        }

        return $this->collapseRedundantConditionWrappers($query, false);
    }

    /**
     * @return array{qualifier:?string,type:string,condition:?string}
     */
    private function parseSingleQueryForConjunction(string $query): array
    {
        $queries = $this->splitTopLevel($this->minifyList($query, true), ',');
        if (count($queries) !== 1) {
            throw new \InvalidArgumentException('Media query conjunction only supports single queries');
        }

        $query = $queries[0];
        if (preg_match('/^(?:(not|only)\s+)?(' . $this->cssIdentifierPattern() . ')(?:\s+and\s+(.+))?$/i', $query, $matches) === 1) {
            return [
                'qualifier' => isset($matches[1]) && $matches[1] !== '' ? strtolower($matches[1]) : null,
                'type' => $this->canonicalMediaTypeIdentifier($matches[2]),
                'condition' => isset($matches[3]) && trim($matches[3]) !== '' ? trim($matches[3]) : null,
            ];
        }

        return [
            'qualifier' => null,
            'type' => 'all',
            'condition' => $query,
        ];
    }

    /**
     * @param array{qualifier:?string,type:string,condition:?string} $left
     * @param array{qualifier:?string,type:string,condition:?string} $right
     * @return array{?string,string}
     */
    private function combineMediaTypeForAnd(array $left, array $right): array
    {
        if (($left['qualifier'] === 'not' && $left['type'] === 'all') || ($right['qualifier'] === 'not' && $right['type'] === 'all')) {
            return ['not', 'all'];
        }

        if ($left['qualifier'] === 'not' && $right['qualifier'] === 'not') {
            if ($left['type'] === $right['type']) {
                return ['not', $left['type']];
            }

            throw new \InvalidArgumentException('Unsupported media query boolean logic');
        }

        if ($left['type'] === 'all') {
            return [$right['qualifier'], $right['type']];
        }
        if ($right['type'] === 'all') {
            return [$left['qualifier'], $left['type']];
        }
        if ($left['qualifier'] === 'not') {
            return [$right['qualifier'], $right['type']];
        }
        if ($right['qualifier'] === 'not') {
            return [$left['qualifier'], $left['type']];
        }
        if ($left['type'] !== $right['type']) {
            return ['not', 'all'];
        }

        return [null, $left['type']];
    }

    private function combineMediaConditionsForAnd(string $left, string $right): string
    {
        return $this->wrapMediaConditionForAnd($left) . ' and ' . $this->wrapMediaConditionForAnd($right);
    }

    private function wrapMediaConditionForAnd(string $condition): string
    {
        return $this->containsTopLevelKeyword($condition, 'or') ? '(' . $condition . ')' : $condition;
    }

    private function serializeMediaQueryForCombination(?string $qualifier, string $type, ?string $condition): string
    {
        $prefix = '';
        if ($qualifier !== null) {
            $prefix .= $qualifier . ' ';
        }

        if ($type !== 'all' || $qualifier !== null || $condition === null) {
            $prefix .= $type;
        }

        if ($condition === null) {
            return trim($prefix);
        }

        if ($prefix === '') {
            return $condition;
        }

        if ($this->containsTopLevelKeyword($condition, 'or')) {
            $condition = '(' . $condition . ')';
        }

        return trim($prefix) . ' and ' . $condition;
    }

    private function collapseAllMediaConditionWrapper(string $condition): string
    {
        while (($inner = $this->unwrapSingleParenthesizedValue($condition)) !== null
            && ($this->containsTopLevelKeyword($inner, 'and') || $this->containsTopLevelKeyword($inner, 'or'))
        ) {
            $condition = $inner;
        }

        return $condition;
    }

    private function collapseSingleFeatureWrapper(string $condition): string
    {
        $condition = trim($condition);
        while (str_starts_with($condition, '((') && str_ends_with($condition, '))')) {
            $innerClose = $this->findMatchingDelimiter($condition, 1, '(', ')');
            if ($innerClose !== strlen($condition) - 2) {
                break;
            }

            $inner = substr($condition, 2, $innerClose - 2);
            if ($this->containsTopLevelKeyword($inner, 'and') || $this->containsTopLevelKeyword($inner, 'or')) {
                break;
            }

            $condition = '(' . $inner . ')';
        }

        return $condition;
    }

    private function collapseRedundantConditionWrappers(string $condition, bool $preserveTopLevelOr): string
    {
        $condition = trim($condition);
        do {
            $previous = $condition;
            $condition = $this->collapseSingleFeatureWrapper($condition);
            $unwrapped = $this->unwrapSingleParenthesizedValue($condition);
            if ($unwrapped === null) {
                continue;
            }

            $inner = $this->normalizeBooleanConditionGroups(trim($unwrapped));
            $rootOperator = $this->topLevelLogicalRoot($inner);
            if ($rootOperator !== null) {
                if ($preserveTopLevelOr && $rootOperator === 'or') {
                    return '(' . $inner . ')';
                }

                $condition = $inner;
                continue;
            }

            if (preg_match('/^not\s+(.+)$/i', $inner, $matches) !== 1) {
                continue;
            }

            $tail = trim($matches[1]);
            if ($this->isSingleParenthesizedCondition($tail)) {
                $condition = 'not ' . $this->collapseSingleFeatureWrapper($tail);
            }
        } while ($condition !== $previous);

        return $condition;
    }

    private function topLevelLogicalRoot(string $condition): ?string
    {
        if ($this->splitTopLevelLogical($condition, 'or') !== null) {
            return 'or';
        }
        if ($this->splitTopLevelLogical($condition, 'and') !== null) {
            return 'and';
        }

        return null;
    }

    private function invertSimpleRangeFeature(string $feature): ?string
    {
        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\s*(<=|>=|<|>|=)\s*(.+)$/', $feature, $matches) !== 1) {
            return null;
        }

        $name = $this->canonicalMediaFeatureIdentifier($matches[1]);
        if (!$this->isRangeComparableMediaFeature($name)) {
            return null;
        }

        return $name . $this->invertComparison($matches[2]) . $this->minifyValue($matches[3], $this->rangeComparableMediaFeatureType($name));
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

    private function stripRedundantNegationParentheses(string $value): string
    {
        $value = trim($value);
        while (($inner = $this->stripOneBalancedParentheses($value)) !== null) {
            $value = trim($inner);
            if ($this->containsTopLevelKeyword($value, 'and') || $this->containsTopLevelKeyword($value, 'or')) {
                break;
            }
        }

        return $value;
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
        $number = trim($number);
        if ($number === '') {
            return $number;
        }

        if (stripos($number, 'e') !== false) {
            $float = (float) $number;
            if ($float !== 0.0 && abs($float) < 0.000001) {
                return $this->normalizeScientificNumber($number);
            }

            $number = $this->formatFloatNumber($float);
        }

        $sign = '';
        if ($number[0] === '+' || $number[0] === '-') {
            $sign = $number[0];
            $number = substr($number, 1);
        }

        if (!str_contains($number, '.')) {
            $number = ltrim($number, '0');

            return $number === '' ? '0' : ($sign === '-' ? '-' : '') . $number;
        }

        [$integer, $fraction] = explode('.', $number, 2);
        $integer = ltrim($integer, '0');
        $fraction = rtrim($fraction, '0');

        if ($fraction === '') {
            $number = $integer === '' ? '0' : $integer;

            return $number === '0' ? '0' : ($sign === '-' ? '-' : '') . $number;
        }

        $number = ($integer === '' ? '' : $integer) . '.' . $fraction;

        return ($sign === '-' ? '-' : '') . $number;
    }

    private function formatFloatNumber(float $number): string
    {
        if (!is_finite($number)) {
            return (string) $number;
        }

        $formatted = rtrim(rtrim(sprintf('%.12F', $number), '0'), '.');
        if ($formatted === '' || $formatted === '-0') {
            return '0';
        }

        return $formatted;
    }

    private function normalizeScientificNumber(string $number): string
    {
        if (preg_match('/^([+-]?)(\d+\.\d*|\d*\.\d+|\d+)[eE]([+-]?\d+)$/', $number, $matches) !== 1) {
            return strtolower($number);
        }

        $mantissa = $this->trimNumber($matches[2]);
        if ($mantissa === '0') {
            return '0';
        }

        $exponent = (int) $matches[3];
        if ($exponent === 0) {
            return ($matches[1] === '-' ? '-' : '') . $mantissa;
        }

        return ($matches[1] === '-' ? '-' : '') . $mantissa . 'e' . $exponent;
    }

    private function lowerRangeSyntaxQuery(string $query, bool $lowerSimpleRanges, bool $lowerIntervalRanges): string
    {
        $mediaPrefix = $this->extractExplicitMediaTypePrefix($query);
        if ($mediaPrefix === null) {
            return $this->lowerRangeSyntaxCondition($query, $lowerSimpleRanges, $lowerIntervalRanges)['css'];
        }

        $lowered = $this->lowerRangeSyntaxCondition($mediaPrefix['condition'], $lowerSimpleRanges, $lowerIntervalRanges);
        $condition = $lowered['root'] === 'or' ? '(' . $lowered['css'] . ')' : $lowered['css'];

        if ($mediaPrefix['qualifier'] === null && $mediaPrefix['type'] === 'all') {
            return $condition;
        }

        $prefix = $mediaPrefix['qualifier'] === null
            ? $mediaPrefix['type']
            : $mediaPrefix['qualifier'] . ' ' . $mediaPrefix['type'];

        return $prefix . ' and ' . $condition;
    }

    /**
     * @return array{qualifier:?string,type:string,condition:string}|null
     */
    private function extractExplicitMediaTypePrefix(string $query): ?array
    {
        $pattern = '/^(?:(not|only)\s+)?(' . $this->cssIdentifierPattern() . ')\s+and\s+(.+)$/i';
        if (preg_match($pattern, $query, $matches) !== 1) {
            return null;
        }

        $condition = trim($matches[3]);
        if ($condition === '') {
            return null;
        }

        return [
            'qualifier' => isset($matches[1]) && $matches[1] !== '' ? strtolower($matches[1]) : null,
            'type' => $this->canonicalMediaTypeIdentifier($matches[2]),
            'condition' => $condition,
        ];
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

            $lowered = $this->lowerRangeSyntaxCondition($unwrapped, $lowerSimpleRanges, $lowerIntervalRanges);
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
                return $lowered;
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
        if ($this->startsKeywordAt($feature, 0, 'not')) {
            return null;
        }

        if ($this->splitTopLevelLogical($feature, 'and') !== null || $this->splitTopLevelLogical($feature, 'or') !== null) {
            return null;
        }

        $ident = $this->cssIdentifierPattern();
        $intervalOperator = '<=|>=|<|>';
        $simpleOperator = '<=|>=|<|>|=';

        if (preg_match('/^(.+?)\s*(' . $intervalOperator . ')\s*(' . $ident . ')\s*(' . $intervalOperator . ')\s*(.+)$/', $feature, $matches) === 1) {
            if (!$lowerIntervalRanges) {
                return null;
            }

            $name = $this->canonicalMediaFeatureIdentifier($matches[3]);
            if (!$this->isLegacyRangeFeature($name)) {
                return null;
            }

            $type = $this->rangeComparableMediaFeatureType($name);
            $left = $this->legacyComparison($name, $this->comparisonFromLeft($matches[2]), $this->minifyValue($matches[1], $type));
            $right = $this->legacyComparison($name, $matches[4], $this->minifyValue($matches[5], $type));
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

        if (preg_match('/^(' . $ident . ')\s*(' . $simpleOperator . ')\s*(.+)$/', $feature, $matches) !== 1) {
            return null;
        }

        if (!$lowerSimpleRanges) {
            return null;
        }

        $name = $this->canonicalMediaFeatureIdentifier($matches[1]);
        if (!$this->isLegacyRangeFeature($name)) {
            return null;
        }

        $comparison = $this->legacyComparison(
            $name,
            $negated ? $this->invertComparison($matches[2]) : $matches[2],
            $this->minifyValue($matches[3], $this->rangeComparableMediaFeatureType($name))
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
        return $this->rangeComparableMediaFeatureType($feature) !== null;
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
            '>=' => ['css' => '(' . $this->legacyBoundFeatureName($feature, 'min') . ':' . $value . ')', 'bareNot' => false],
            '<=' => ['css' => '(' . $this->legacyBoundFeatureName($feature, 'max') . ':' . $value . ')', 'bareNot' => false],
            '>' => ['css' => 'not (' . $this->legacyBoundFeatureName($feature, 'max') . ':' . $value . ')', 'bareNot' => true],
            '<' => ['css' => 'not (' . $this->legacyBoundFeatureName($feature, 'min') . ':' . $value . ')', 'bareNot' => true],
            '=' => ['css' => '(' . $feature . ':' . $value . ')', 'bareNot' => false],
            default => ['css' => '(' . $feature . $operator . $value . ')', 'bareNot' => false],
        };
    }

    private function legacyBoundFeatureName(string $feature, string $bound): string
    {
        if ($feature === '-webkit-device-pixel-ratio') {
            return '-webkit-' . $bound . '-device-pixel-ratio';
        }

        return $bound . '-' . $feature;
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
