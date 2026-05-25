<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class NestingTransformer
{
    public function lower(string $css): string
    {
        $flat = $this->lowerRuleList($this->stripComments($css), null);

        return (new CssMinifier())->minify($flat);
    }

    /**
     * @param array{browsers?:array<string, int|float|string>,include?:mixed,exclude?:mixed} $targets
     */
    public function transformForTargets(string $css, array $targets = []): string
    {
        if ($this->featureListContains($targets['exclude'] ?? [], 'nesting')) {
            return (new CssMinifier())->minify($css);
        }

        if (
            $this->featureListContains($targets['include'] ?? [], 'nesting')
            || $this->targetsRequireNestingLowering($targets['browsers'] ?? [])
        ) {
            return $this->lower($css);
        }

        return (new CssMinifier())->minify($css);
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function lowerRuleList(string $css, ?array $parentSelectors): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

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
                    $output .= $statement . ';';
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            if ($prelude === '') {
                throw new \InvalidArgumentException('CSS rule is missing a prelude');
            }

            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);

            if (str_starts_with($prelude, '@scope')) {
                $scopePrelude = $parentSelectors === null
                    ? $this->resolveScopePrelude($prelude, [':scope'])
                    : $this->resolveScopePrelude($prelude, $parentSelectors);
                $output .= $scopePrelude . '{' . $this->lowerScopeBody($body) . '}';
            } elseif (str_starts_with($prelude, '@')) {
                $inner = $parentSelectors === null
                    ? $this->lowerRuleList($body, null)
                    : $this->lowerStyleBody($parentSelectors, $body);
                $output .= $prelude . '{' . $inner . '}';
            } else {
                $selectors = $parentSelectors === null
                    ? $this->normalizeTopLevelSelectors($this->splitTopLevel($prelude, ','))
                    : $this->resolveNestedSelectors($parentSelectors, $prelude);
                $output .= $this->lowerStyleBody($selectors, $body);
            }

            $cursor = $close + 1;
        }

        return $output;
    }

    /**
     * @param list<string> $selectors
     */
    private function lowerStyleBody(array $selectors, string $body): string
    {
        $output = '';
        $declarations = '';
        $cursor = 0;

        while (($open = $this->findNextTopLevel($body, '{', $cursor)) !== null) {
            $prefix = substr($body, $cursor, $open - $cursor);
            [$declarationPart, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $declarations .= $declarationPart;
            $output .= $this->emitDeclarationRule($selectors, $declarations);
            $declarations = '';

            $nestedPrelude = trim($nestedPrelude);
            if ($nestedPrelude === '') {
                throw new \InvalidArgumentException('Nested CSS rule is missing a prelude');
            }

            $close = $this->findMatchingBrace($body, $open);
            $nestedBody = substr($body, $open + 1, $close - $open - 1);

            if (str_starts_with($nestedPrelude, '@nest ')) {
                $nestedSelectors = $this->resolveNestedSelectors($selectors, substr($nestedPrelude, 6));
                $output .= $this->lowerStyleBody($nestedSelectors, $nestedBody);
            } elseif (str_starts_with($nestedPrelude, '@scope')) {
                $output .= $this->resolveScopePrelude($nestedPrelude, $selectors) . '{' . $this->lowerScopeBody($nestedBody) . '}';
            } elseif (str_starts_with($nestedPrelude, '@')) {
                $output .= $nestedPrelude . '{' . $this->lowerStyleBody($selectors, $nestedBody) . '}';
            } else {
                $nestedSelectors = $this->resolveNestedSelectors($selectors, $nestedPrelude);
                $output .= $this->lowerStyleBody($nestedSelectors, $nestedBody);
            }

            $cursor = $close + 1;
        }

        $declarations .= substr($body, $cursor);

        return $output . $this->emitDeclarationRule($selectors, $declarations);
    }

    private function lowerScopeBody(string $body): string
    {
        $output = '';
        $declarations = '';
        $cursor = 0;

        while (($open = $this->findNextTopLevel($body, '{', $cursor)) !== null) {
            $prefix = substr($body, $cursor, $open - $cursor);
            [$declarationPart, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $declarations .= $declarationPart;
            $output .= $declarations;
            $declarations = '';

            $nestedPrelude = trim($nestedPrelude);
            if ($nestedPrelude === '') {
                throw new \InvalidArgumentException('Nested CSS rule is missing a prelude');
            }

            $close = $this->findMatchingBrace($body, $open);
            $nestedBody = substr($body, $open + 1, $close - $open - 1);

            if (str_starts_with($nestedPrelude, '@nest ')) {
                $nestedSelectors = $this->resolveNestedSelectors([':scope'], substr($nestedPrelude, 6));
                $output .= $this->lowerStyleBody($nestedSelectors, $nestedBody);
            } elseif (str_starts_with($nestedPrelude, '@scope')) {
                $output .= $this->resolveScopePrelude($nestedPrelude, [':scope']) . '{' . $this->lowerScopeBody($nestedBody) . '}';
            } elseif (str_starts_with($nestedPrelude, '@')) {
                $output .= $nestedPrelude . '{' . $this->lowerScopeBody($nestedBody) . '}';
            } else {
                $nestedSelectors = $this->resolveNestedSelectors([':scope'], $nestedPrelude);
                $output .= $this->lowerStyleBody($nestedSelectors, $nestedBody);
            }

            $cursor = $close + 1;
        }

        return $output . substr($body, $cursor);
    }

    /**
     * @param list<string> $selectors
     */
    private function emitDeclarationRule(array $selectors, string $declarations): string
    {
        $declarations = trim($declarations);
        if ($declarations === '') {
            return '';
        }

        return implode(', ', $selectors) . '{' . $declarations . '}';
    }

    /**
     * @param list<string> $selectors
     * @return list<string>
     */
    private function normalizeTopLevelSelectors(array $selectors): array
    {
        return array_map(
            fn (string $selector): string => $this->resolveParentReferences(trim($selector), [':scope']),
            $selectors
        );
    }

    /**
     * @param list<string> $parentSelectors
     * @return list<string>
     */
    private function resolveNestedSelectors(array $parentSelectors, string $nestedPrelude): array
    {
        $nestedSelectors = $this->splitTopLevel($nestedPrelude, ',');
        $resolved = [];
        foreach ($nestedSelectors as $nested) {
            $nested = trim($nested);
            if ($nested === '') {
                continue;
            }

            if (str_contains($nested, '&')) {
                if ($this->startsWithCombinator($nested)) {
                    $nested = '& ' . $nested;
                }

                $distributed = $this->distributeAttachedParentReference($parentSelectors, $nested);
                if ($distributed !== null) {
                    array_push($resolved, ...$distributed);
                    continue;
                }

                $resolved[] = $this->resolveParentReferences($nested, $parentSelectors);
                continue;
            }

            $resolved[] = $this->parentReference($parentSelectors) . ' ' . $nested;
        }

        if ($resolved === []) {
            throw new \InvalidArgumentException('Nested CSS selector list is empty');
        }

        return $resolved;
    }

    /**
     * @param list<string> $parentSelectors
     * @return list<string>|null
     */
    private function distributeAttachedParentReference(array $parentSelectors, string $selector): ?array
    {
        if (count($parentSelectors) < 2 || substr_count($selector, '&') !== 1) {
            return null;
        }

        $selector = trim($selector);
        if (preg_match('/^&(?=[.#:\[])(.+)$/s', $selector, $matches) !== 1) {
            return null;
        }

        $suffix = $matches[1];
        if (
            $suffix === ''
            || str_contains($suffix, '&')
            || count($this->splitTopLevel($suffix, ',')) !== 1
            || $this->hasTopLevelCombinator($suffix)
        ) {
            return null;
        }

        $sharedKey = null;
        foreach ($parentSelectors as $parentSelector) {
            $parentSelector = trim($parentSelector);
            if (
                $parentSelector === ''
                || str_contains($parentSelector, ':is(')
                || count($this->splitTopLevel($parentSelector, ',')) !== 1
                || $this->hasTopLevelCombinator($parentSelector)
            ) {
                return null;
            }

            $key = $this->leadingSimpleSelectorKey($parentSelector);
            if ($key === null) {
                return null;
            }

            $sharedKey ??= $key;
            if ($key !== $sharedKey) {
                return null;
            }
        }

        return array_map(
            static fn (string $parentSelector): string => trim($parentSelector) . $suffix,
            $parentSelectors
        );
    }

    private function leadingSimpleSelectorKey(string $selector): ?string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return null;
        }

        $pattern = '/^(?:[#.][-_a-zA-Z0-9]+|[A-Za-z_][-_a-zA-Z0-9]*|\|[A-Za-z_][-_a-zA-Z0-9]*'
            . '|\*\|[A-Za-z_][-_a-zA-Z0-9]*|[A-Za-z_][-_a-zA-Z0-9]*\|[A-Za-z_][-_a-zA-Z0-9]*)/';
        if (preg_match($pattern, $selector, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[0]);
    }

    /**
     * @param list<string> $parentSelectors
     */
    private function resolveParentReferences(string $selector, array $parentSelectors): string
    {
        if (!str_contains($selector, '&')) {
            return $selector;
        }

        $parentReference = $this->parentReference($parentSelectors);
        $attachedSuffix = $this->attachedParentSuffix($parentReference);
        $output = '';
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];
            if ($char !== '&') {
                $output .= $char;
                continue;
            }

            $attachedType = $this->readAttachedTypeSelector($selector, $i + 1);
            if ($attachedType !== '') {
                $output .= $attachedType . $attachedSuffix;
                $i += strlen($attachedType);
                continue;
            }

            $output .= $this->isAttachedToPreviousSelector($selector, $i)
                ? $attachedSuffix
                : ($this->isFirstNonWhitespaceAt($selector, $i) ? $parentReference : $attachedSuffix);
        }

        return $output;
    }

    /**
     * @param list<string> $parentSelectors
     */
    private function resolveScopePrelude(string $prelude, array $parentSelectors): string
    {
        $rest = trim(substr($prelude, 6));
        if ($rest === '') {
            return '@scope';
        }

        if (str_starts_with($rest, 'to ')) {
            $boundary = $this->readParenthesized(trim(substr($rest, 3)));
            if ($boundary === null) {
                return $prelude;
            }

            return '@scope to (' . $this->resolveScopeSelectorList($boundary[0], $parentSelectors) . ')';
        }

        if (!str_starts_with($rest, '(')) {
            return $prelude;
        }

        $start = $this->readParenthesized($rest);
        if ($start === null) {
            return $prelude;
        }

        $scopeRoot = $this->resolveScopeSelectorList($start[0], $parentSelectors);
        $output = '@scope (' . $scopeRoot . ')';
        $afterStart = trim($start[1]);

        if ($afterStart === '') {
            return $output;
        }

        if (!str_starts_with($afterStart, 'to ')) {
            return $output . ' ' . $afterStart;
        }

        $end = $this->readParenthesized(trim(substr($afterStart, 3)));
        if ($end === null) {
            return $output . ' ' . $afterStart;
        }

        return $output . ' to (' . $this->resolveScopeSelectorList($end[0], [$scopeRoot]) . ')';
    }

    /**
     * @param list<string> $parentSelectors
     */
    private function resolveScopeSelectorList(string $selectorList, array $parentSelectors): string
    {
        $selectors = [];
        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $selectors[] = $this->resolveParentReferences(trim($selector), $parentSelectors);
        }

        return implode(', ', $selectors);
    }

    /**
     * @param list<string> $parentSelectors
     */
    private function parentReference(array $parentSelectors): string
    {
        if (count($parentSelectors) === 1) {
            return trim($parentSelectors[0]);
        }

        return ':is(' . implode(', ', array_map('trim', $parentSelectors)) . ')';
    }

    private function attachedParentSuffix(string $parentReference): string
    {
        $parentReference = trim($parentReference);
        if (
            $parentReference === ':scope'
            || (
                preg_match('/^[.#[:]/', $parentReference) === 1
                && !$this->hasTopLevelCombinator($parentReference)
            )
        ) {
            return $parentReference;
        }

        return ':is(' . $parentReference . ')';
    }

    private function readAttachedTypeSelector(string $selector, int $offset): string
    {
        $rest = substr($selector, $offset);
        if (preg_match('/^(?:\*\|[A-Za-z_][A-Za-z0-9_-]*|\|[A-Za-z_][A-Za-z0-9_-]*|[A-Za-z_][A-Za-z0-9_-]*\|[A-Za-z_][A-Za-z0-9_-]*|[A-Za-z_][A-Za-z0-9_-]*|\*)/', $rest, $matches) !== 1) {
            return '';
        }

        return $matches[0];
    }

    private function isAttachedToPreviousSelector(string $selector, int $ampersand): bool
    {
        if ($ampersand === 0) {
            return false;
        }

        $previous = $selector[$ampersand - 1];

        return !ctype_space($previous)
            && !in_array($previous, ['>', '+', '~', '(', ','], true)
            && $previous !== '&';
    }

    private function isFirstNonWhitespaceAt(string $selector, int $offset): bool
    {
        for ($i = 0; $i < $offset; $i++) {
            if (!ctype_space($selector[$i])) {
                return false;
            }
        }

        return true;
    }

    private function startsWithCombinator(string $selector): bool
    {
        $selector = ltrim($selector);

        return $selector !== '' && in_array($selector[0], ['>', '+', '~'], true);
    }

    private function featureListContains(mixed $features, string $feature): bool
    {
        $feature = strtolower($feature);
        if (is_string($features)) {
            return strtolower($features) === $feature;
        }

        if (!is_array($features)) {
            return false;
        }

        foreach ($features as $name => $enabled) {
            if (is_string($name) && strtolower($name) === $feature) {
                return (bool) $enabled;
            }

            if (is_string($enabled) && strtolower($enabled) === $feature) {
                return true;
            }
        }

        return false;
    }

    private function targetsRequireNestingLowering(mixed $browsers): bool
    {
        if (!is_array($browsers)) {
            return false;
        }

        $support = [
            'chrome' => 112.0,
            'edge' => 112.0,
            'firefox' => 117.0,
            'ios' => 16.5,
            'ios-safari' => 16.5,
            'ios_saf' => 16.5,
            'safari' => 16.5,
        ];

        foreach ($browsers as $browser => $version) {
            $name = strtolower(str_replace(' ', '-', (string) $browser));
            if (in_array($name, ['ie', 'internet-explorer'], true)) {
                return true;
            }

            if (!isset($support[$name])) {
                continue;
            }

            if ($this->targetMajorVersion($version) < $support[$name]) {
                return true;
            }
        }

        return false;
    }

    private function targetMajorVersion(int|float|string $version): float
    {
        $numeric = (float) $version;

        return $numeric >= 65536 ? (float) (((int) $numeric) >> 16) : $numeric;
    }

    private function hasTopLevelCombinator(string $selector): bool
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];
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
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && (ctype_space($char) || $char === '>' || $char === '+' || $char === '~')) {
                return true;
            }
        }

        return false;
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
        $parts = [];
        $start = 0;
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
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
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $delimiter && $parenDepth === 0 && $bracketDepth === 0) {
                $part = trim(substr($value, $start, $i - $start));
                if ($part !== '') {
                    $parts[] = $part;
                }
                $start = $i + 1;
            }
        }

        $part = trim(substr($value, $start));
        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts;
    }

    private function findNextTopLevel(string $value, string $needle, int $offset): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $length = strlen($value);

        for ($i = $offset; $i < $length; $i++) {
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
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === '{') {
                if ($char === $needle && $parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
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

    private function findLastTopLevel(string $value, string $needle): ?int
    {
        $last = null;
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
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
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $needle && $parenDepth === 0 && $bracketDepth === 0) {
                $last = $i;
            }
        }

        return $last;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function readParenthesized(string $value): ?array
    {
        if (($value[0] ?? '') !== '(') {
            return null;
        }

        $close = $this->findMatchingParen($value, 0);

        return [substr($value, 1, $close - 1), substr($value, $close + 1)];
    }

    private function findMatchingParen(string $value, int $open): int
    {
        $quote = null;
        $depth = 0;
        $length = strlen($value);

        for ($i = $open; $i < $length; $i++) {
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
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unclosed CSS parentheses');
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

        throw new \InvalidArgumentException('Unclosed CSS block');
    }

    private function skipWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
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
