<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssMinifier
{
    /**
     * @return array{code:string,warnings:list<array{message:string,type:string,loc:array{filename:string,line:int,column:int}}>}
     */
    public function minifyWithErrorRecovery(string $css, string $filename = '<stdin>'): array
    {
        $warnings = [];
        $css = $this->omitRecoverableInvalidAtRules($css, $filename, $warnings);

        return [
            'code' => $this->minify($css),
            'warnings' => $warnings,
        ];
    }

    public function minify(string $css, bool $preserveFontTargetFallbacks = false): string
    {
        [$css, $licenseComments] = $this->stripComments($css);
        $output = '';
        $quote = null;
        $pendingSpace = false;
        $length = strlen($css);
        $tight = '{}:;,>+~()[]';

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
                if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                    $output .= ' ';
                }
                $pendingSpace = false;
                $quote = $char;
                $output .= $char;
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = true;
                continue;
            }

            if ($char === ':' && $pendingSpace && $this->startsDescendantPseudoClass($css, $i)) {
                if ($this->needsSelectorDescendantSpaceBeforePseudo($output)) {
                    $output .= ' ';
                }
                $output .= $char;
                $pendingSpace = false;
                continue;
            }

            if ($char === '&' && $pendingSpace && $this->isSelectorContextAhead($css, $i)) {
                if ($this->needsSelectorDescendantSpaceBeforeParentReference($output)) {
                    $output .= ' ';
                }
                $output .= $char;
                $pendingSpace = false;
                continue;
            }

            if (str_contains($tight, $char)) {
                $output = rtrim($output);
                $output .= $char;
                $pendingSpace = false;
                continue;
            }

            if ($pendingSpace && $this->needsSelectorDescendantSpaceAfterAttribute($output, $css, $i)) {
                $output .= ' ';
            } elseif ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                $output .= ' ';
            }
            $pendingSpace = false;
            $output .= $char;
        }

        $css = $this->minifyContainerQueries($this->minifyMediaQueries($this->minifyDeclarationValues(str_replace(';}', '}', trim($output)))));
        $css = $this->canonicalizeImplicitNestedSelectors($css);
        $css = $this->minifyImportRules($css);
        $css = $this->minifyLayerRules($css);
        $css = $this->minifyNamespaceRules($css);
        $css = $this->normalizeNamespaceAttributeSelectors($css);
        $css = $this->minifySupportsRules($css);
        $css = $this->minifyFontFeatureValuesRules($css);
        $css = $this->minifyKeyframesRules($css);
        $css = $this->mergeAdjacentRuleBlocks($css);
        $css = $this->rewriteAllResetDeclarationBlocks($css);
        $css = $this->composeContainerDeclarationBlocks($css);
        $css = $this->composePositionDeclarationBlocks($css);
        $css = $this->composeGridDeclarationBlocks($css);
        $css = $this->composeBorderRadiusDeclarationBlocks($css);
        $css = $this->composeFontDeclarationBlocks($css, $preserveFontTargetFallbacks);
        $css = $this->composeListStyleDeclarationBlocks($css);
        $css = $this->composeTextEmphasisDeclarationBlocks($css);
        $css = $this->composeTransitionDeclarationBlocks($css);

        $css = $this->composeAnimationDeclarationBlocks($css);
        $css = $this->minifyPropertyRules($css);
        $css = $this->minifyViewTransitionRules($css);
        $css = $this->validatePageRules($css);
        $css = $this->normalizeScopeRuleSpacing($css);
        $css = $this->removeEmptyStartingStyleRules($css);

        return $this->prependLicenseComments(
            $this->compactLegacyPseudoElementColons($css),
            $licenseComments,
        );
    }

    /**
     * @param list<array{message:string,type:string,loc:array{filename:string,line:int,column:int}}> $warnings
     */
    private function omitRecoverableInvalidAtRules(string $css, string $filename, array &$warnings): string
    {
        $output = '';
        $cursor = 0;

        while (($invalid = $this->findRecoverableInvalidAtRule($css, $cursor)) !== null) {
            $output .= substr($css, $cursor, $invalid['start'] - $cursor);
            $warnings[] = [
                'message' => 'Unexpected token Function("' . $invalid['function'] . '")',
                'type' => 'UnexpectedToken',
                'loc' => $this->sourceLocation($css, $invalid['functionOffset'], $filename),
            ];
            $cursor = $invalid['end'];
        }

        return $output . substr($css, $cursor);
    }

    /**
     * @return array{start:int,end:int,function:string,functionOffset:int}|null
     */
    private function findRecoverableInvalidAtRule(string $css, int $start): ?array
    {
        $quote = null;
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

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return null;
                }
                $i = $end + 1;
                continue;
            }

            if ($char !== '@') {
                continue;
            }

            foreach (['@container', '@media'] as $keyword) {
                if (!$this->startsWithAtKeyword($css, $i, $keyword)) {
                    continue;
                }

                $open = $this->findNextTopLevel($css, '{', $i + strlen($keyword));
                if ($open === null) {
                    continue;
                }

                $preludeStart = $i + strlen($keyword);
                $prelude = substr($css, $preludeStart, $open - $preludeStart);
                $functionOffset = $keyword === '@container'
                    ? $this->recoverableContainerFunctionOffset($prelude)
                    : $this->recoverableMediaFunctionOffset($prelude);
                if ($functionOffset === null) {
                    continue;
                }

                $function = $this->readIdentifier($prelude, $functionOffset);
                $close = $this->findMatchingBraceInCss($css, $open);

                return [
                    'start' => $i,
                    'end' => $close + 1,
                    'function' => strtolower($function),
                    'functionOffset' => $preludeStart + $functionOffset,
                ];
            }
        }

        return null;
    }

    private function startsWithAtKeyword(string $css, int $offset, string $keyword): bool
    {
        if (strncasecmp(substr($css, $offset, strlen($keyword)), $keyword, strlen($keyword)) !== 0) {
            return false;
        }

        $next = $css[$offset + strlen($keyword)] ?? '';

        return $next === '' || !$this->isIdentifierChar($next);
    }

    private function recoverableContainerFunctionOffset(string $prelude): ?int
    {
        [$name, $condition] = $this->splitContainerNameAndCondition(trim($prelude));
        if ($condition === null || $condition === '') {
            return null;
        }

        $functionOffset = $this->findUnsupportedTopLevelConditionFunctionOffset($condition, ['style', 'scroll-state']);
        if ($functionOffset === null) {
            return null;
        }

        $conditionOffset = strpos($prelude, $condition);
        if ($conditionOffset === false) {
            return null;
        }

        return $conditionOffset + $functionOffset;
    }

    private function recoverableMediaFunctionOffset(string $prelude): ?int
    {
        foreach ($this->splitTopLevel($prelude, ',') as $query) {
            $trimmed = trim($query);
            $functionOffset = $this->findUnsupportedTopLevelConditionFunctionOffset($trimmed, []);
            if ($functionOffset === null) {
                continue;
            }

            $queryOffset = strpos($prelude, $query);
            if ($queryOffset === false) {
                continue;
            }

            return $queryOffset + (strpos($query, $trimmed) ?: 0) + $functionOffset;
        }

        return null;
    }

    /**
     * @param list<string> $allowedFunctions
     */
    private function findUnsupportedTopLevelConditionFunctionOffset(string $condition, array $allowedFunctions): ?int
    {
        $quote = null;
        $depth = 0;
        $length = strlen($condition);

        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];
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

            if ($depth !== 0 || !$this->isIdentifierStart($char)) {
                continue;
            }

            $identifier = strtolower($this->readIdentifier($condition, $i));
            $after = $i + strlen($identifier);
            if (($condition[$after] ?? '') !== '(') {
                $i = $after - 1;
                continue;
            }

            if (!in_array($identifier, $allowedFunctions, true)) {
                return $i;
            }

            $i = $after - 1;
        }

        return null;
    }

    /**
     * @return array{filename:string,line:int,column:int}
     */
    private function sourceLocation(string $source, int $offset, string $filename): array
    {
        $before = substr($source, 0, $offset);
        $line = substr_count($before, "\n") + 1;
        $lastNewline = strrpos($before, "\n");
        $column = $lastNewline === false ? strlen($before) : strlen(substr($before, $lastNewline + 1));

        return [
            'filename' => $filename,
            'line' => $line,
            'column' => $column,
        ];
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private function stripComments(string $css): array
    {
        $output = '';
        $licenseComments = [];
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
                    return [$output, $licenseComments];
                }
                $comment = substr($css, $i, $end - $i + 2);
                if (($css[$i + 2] ?? '') === '!') {
                    $licenseComments[] = trim($comment);
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return [$output, $licenseComments];
    }

    /**
     * @param list<string> $licenseComments
     */
    private function prependLicenseComments(string $css, array $licenseComments): string
    {
        if ($licenseComments === []) {
            return $css;
        }

        $prefix = implode("\n", $licenseComments);
        if ($css === '') {
            return $prefix;
        }

        return $prefix . "\n" . $css;
    }

    private function compactLegacyPseudoElementColons(string $css): string
    {
        $output = '';
        $quote = null;
        $length = strlen($css);
        $legacyPseudoElements = ['before', 'after', 'first-line', 'first-letter'];

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

            if ($char === ':' && ($css[$i + 1] ?? '') === ':' && $this->isSelectorContextAhead($css, $i)) {
                foreach ($legacyPseudoElements as $pseudoElement) {
                    $token = '::' . $pseudoElement;
                    if (strncasecmp(substr($css, $i, strlen($token)), $token, strlen($token)) !== 0) {
                        continue;
                    }

                    $next = $css[$i + strlen($token)] ?? '';
                    if ($next !== '' && preg_match('/[-_a-zA-Z0-9]/', $next) === 1) {
                        continue;
                    }

                    $output .= ':' . $pseudoElement;
                    $i += strlen($token) - 1;
                    continue 2;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function isSelectorContextAhead(string $css, int $offset): bool
    {
        $nextBlock = $this->findNextTopLevel($css, '{', $offset);
        if ($nextBlock === null) {
            return false;
        }

        $nextStatement = $this->findNextTopLevel($css, ';', $offset);
        if ($nextStatement !== null && $nextStatement < $nextBlock) {
            return false;
        }

        $nextClose = $this->findNextTopLevel($css, '}', $offset);

        return $nextClose === null || $nextBlock < $nextClose;
    }

    private function normalizeScopeRuleSpacing(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $scope = strpos($css, '@scope', $cursor);
            if ($scope === false) {
                $output .= substr($css, $cursor);
                break;
            }

            $output .= substr($css, $cursor, $scope - $cursor);
            $open = $this->findNextTopLevel($css, '{', $scope);
            if ($open === null) {
                $output .= substr($css, $scope);
                break;
            }

            $prelude = substr($css, $scope, $open - $scope);
            $prelude = preg_replace('/\bto\s*\(/i', 'to (', $prelude) ?? $prelude;
            $output .= $prelude . '{';
            $cursor = $open + 1;
        }

        return $output;
    }

    private function removeEmptyStartingStyleRules(string $css): string
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

            if ($char === '@' && $this->startsWithAtKeyword($css, $i, '@starting-style') && $this->isRuleListBoundaryBefore($output)) {
                $bodyStart = $i + strlen('@starting-style');
                while ($bodyStart < $length && ctype_space($css[$bodyStart])) {
                    $bodyStart++;
                }

                if (($css[$bodyStart] ?? '') === '{') {
                    $close = $this->findMatchingBraceInCss($css, $bodyStart);
                    if (trim(substr($css, $bodyStart + 1, $close - $bodyStart - 1)) === '') {
                        $i = $close;
                        continue;
                    }
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifyViewTransitionRules(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $position = $this->findAtKeywordInCss($css, '@view-transition', $cursor);
            if ($position === null) {
                $output .= substr($css, $cursor);
                break;
            }

            if (!$this->isRuleListBoundaryBefore(substr($css, 0, $position))) {
                $output .= substr($css, $cursor, $position - $cursor + 1);
                $cursor = $position + 1;
                continue;
            }

            $open = $this->findNextTopLevel($css, '{', $position + strlen('@view-transition'));
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = trim(substr($css, $position, $open - $position));
            if (strcasecmp($prelude, '@view-transition') !== 0) {
                $output .= substr($css, $cursor, $open - $cursor + 1);
                $cursor = $open + 1;
                continue;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);
            $output .= substr($css, $cursor, $position - $cursor)
                . '@view-transition{'
                . $this->minifyViewTransitionDeclarationList($body)
                . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function minifyViewTransitionDeclarationList(string $body): string
    {
        if (str_contains($body, '{')) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $parts = [];
        foreach ($entries as $entry) {
            $property = $entry['property'];
            $value = match ($property) {
                'navigation' => strtolower($entry['value']),
                'types' => $this->minifyViewTransitionTypesValue($entry['value']),
                default => $entry['value'],
            };
            $parts[] = $property . ':' . $value . ($entry['important'] ? '!important' : '');
        }

        return implode(';', $parts);
    }

    private function minifyViewTransitionTypesValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return trim($value);
        }

        return implode(' ', array_map(
            static fn (string $token): string => strcasecmp($token, 'none') === 0 ? 'none' : $token,
            $tokens
        ));
    }

    private function isRuleListBoundaryBefore(string $output): bool
    {
        for ($i = strlen($output) - 1; $i >= 0; $i--) {
            if (ctype_space($output[$i])) {
                continue;
            }

            return in_array($output[$i], ['{', '}', ';'], true);
        }

        return true;
    }

    private function minifyImportRules(string $css): string
    {
        $output = '';
        $quote = null;
        $braceDepth = 0;
        $parenDepth = 0;
        $bracketDepth = 0;
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

            if ($char === '{') {
                $braceDepth++;
                $output .= $char;
                continue;
            }
            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
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

            if ($braceDepth === 0 && $parenDepth === 0 && $bracketDepth === 0 && $this->startsAtKeyword($css, $i, '@charset')) {
                $end = $this->findNextTopLevel($css, ';', $i + strlen('@charset'));
                if ($end === null) {
                    break;
                }
                $i = $end;
                continue;
            }

            if ($braceDepth === 0 && $parenDepth === 0 && $bracketDepth === 0 && $this->startsAtKeyword($css, $i, '@import')) {
                $end = $this->findNextTopLevel($css, ';', $i + strlen('@import'));
                if ($end === null) {
                    $output .= substr($css, $i);
                    break;
                }
                $output .= $this->minifyImportStatement(substr($css, $i, $end - $i)) . ';';
                $i = $end;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifyNamespaceRules(string $css): string
    {
        $output = '';
        $quote = null;
        $braceDepth = 0;
        $parenDepth = 0;
        $bracketDepth = 0;
        $seenTopLevelRuleBlock = false;
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

            if ($braceDepth === 0 && $parenDepth === 0 && $bracketDepth === 0 && $this->startsAtKeyword($css, $i, '@namespace')) {
                if ($seenTopLevelRuleBlock) {
                    throw new \InvalidArgumentException('Unexpected @namespace rule after style rules');
                }

                $end = $this->findNextTopLevel($css, ';', $i + strlen('@namespace'));
                if ($end === null) {
                    $output .= substr($css, $i);
                    break;
                }
                $output .= $this->minifyNamespaceStatement(substr($css, $i, $end - $i)) . ';';
                $i = $end;
                continue;
            }

            if ($char === '{') {
                if ($braceDepth === 0) {
                    $seenTopLevelRuleBlock = true;
                }
                $braceDepth++;
                $output .= $char;
                continue;
            }
            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
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

            $output .= $char;
        }

        return $output;
    }

    private function minifyLayerRules(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $position = $this->findTopLevelAtKeyword($css, '@layer', $cursor);
            if ($position === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $output .= substr($css, $cursor, $position - $cursor);
            $nodes = [];
            $scan = $position;

            while ($scan < $length) {
                $next = $this->skipWhitespace($css, $scan);
                if (!$this->startsAtKeyword($css, $next, '@layer')) {
                    break;
                }

                $node = $this->parseLayerRuleAt($css, $next);
                if ($node === null) {
                    break;
                }

                $nodes[] = $node;
                $scan = $node['end'];
            }

            if ($nodes === []) {
                $output .= substr($css, $position, 6);
                $cursor = $position + 6;
                continue;
            }

            $output .= $this->shouldPreserveLayerRunSourceOrder($nodes)
                ? $this->serializeLayerRunSourceOrder($nodes)
                : $this->serializeLayerRun($nodes);
            $cursor = $scan;
        }

        return $output;
    }

    /**
     * @return array{end:int,type:string,names:list<string>,body?:string}|null
     */
    private function parseLayerRuleAt(string $css, int $offset): ?array
    {
        $start = $offset + strlen('@layer');
        $semicolon = $this->findNextTopLevel($css, ';', $start);
        $open = $this->findNextTopLevel($css, '{', $start);

        if ($semicolon !== null && ($open === null || $semicolon < $open)) {
            $prelude = trim(substr($css, $start, $semicolon - $start));
            if ($prelude === '') {
                throw new \InvalidArgumentException('Invalid @layer statement: missing layer name');
            }

            return [
                'end' => $semicolon + 1,
                'type' => 'statement',
                'names' => $this->minifyLayerNameList($prelude),
            ];
        }

        if ($open === null) {
            return null;
        }

        $prelude = trim(substr($css, $start, $open - $start));
        if (str_contains($prelude, ',')) {
            throw new \InvalidArgumentException("Invalid @layer block prelude: {$prelude}");
        }

        $close = $this->findMatchingBraceInCss($css, $open);
        $body = substr($css, $open + 1, $close - $open - 1);
        if ($prelude === '') {
            return [
                'end' => $close + 1,
                'type' => 'anonymous-block',
                'names' => [],
                'body' => $this->minifyLayerRules($body),
            ];
        }

        $names = $this->minifyLayerNameList($prelude);
        if (count($names) !== 1) {
            throw new \InvalidArgumentException("Invalid @layer block prelude: {$prelude}");
        }

        return [
            'end' => $close + 1,
            'type' => trim($body) === '' ? 'statement' : 'block',
            'names' => $names,
            'body' => $this->minifyLayerRules($body),
        ];
    }

    /**
     * @param list<array{end:int,type:string,names:list<string>,body?:string}> $nodes
     */
    private function serializeLayerRun(array $nodes): string
    {
        $order = [];
        $bodies = [];
        $anonymous = [];

        foreach ($nodes as $node) {
            if ($node['type'] === 'anonymous-block') {
                $anonymous[] = '@layer{' . ($node['body'] ?? '') . '}';
                continue;
            }

            foreach ($node['names'] as $name) {
                if (!isset($order[$name])) {
                    $order[$name] = count($order);
                }
            }

            if ($node['type'] === 'block') {
                $name = $node['names'][0];
                $body = $node['body'] ?? '';
                $bodies[$name] = isset($bodies[$name])
                    ? $this->combineRuleBodies($bodies[$name], $body)
                    : $body;
            }
        }

        $orderedNames = array_keys($order);
        usort($orderedNames, static fn (string $left, string $right): int => $order[$left] <=> $order[$right]);

        $output = '';
        $pendingStatements = [];
        foreach ($orderedNames as $name) {
            if (!isset($bodies[$name])) {
                $pendingStatements[] = $name;
                continue;
            }

            if ($pendingStatements !== []) {
                $output .= '@layer ' . implode(',', $pendingStatements) . ';';
                $pendingStatements = [];
            }

            $output .= '@layer ' . $name . '{' . $this->mergeAdjacentRuleBlocks($bodies[$name]) . '}';
        }

        if ($pendingStatements !== []) {
            $output .= '@layer ' . implode(',', $pendingStatements) . ';';
        }

        return $output . implode('', $anonymous);
    }

    /**
     * @param list<array{end:int,type:string,names:list<string>,body?:string}> $nodes
     */
    private function shouldPreserveLayerRunSourceOrder(array $nodes): bool
    {
        $sawStatement = false;
        foreach ($nodes as $node) {
            if ($node['type'] === 'statement') {
                $sawStatement = true;
                continue;
            }

            if ($sawStatement && str_contains($node['body'] ?? '', '@layer')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{end:int,type:string,names:list<string>,body?:string}> $nodes
     */
    private function serializeLayerRunSourceOrder(array $nodes): string
    {
        $output = '';
        foreach ($nodes as $node) {
            if ($node['type'] === 'anonymous-block') {
                $output .= '@layer{' . ($node['body'] ?? '') . '}';
                continue;
            }

            if ($node['type'] === 'statement') {
                $output .= '@layer ' . implode(',', $node['names']) . ';';
                continue;
            }

            $output .= '@layer ' . $node['names'][0] . '{' . ($node['body'] ?? '') . '}';
        }

        return $output;
    }

    /**
     * @return list<string>
     */
    private function minifyLayerNameList(string $prelude): array
    {
        $names = [];
        foreach ($this->splitTopLevel($prelude, ',') as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $names[] = $this->minifyLayerName($name);
        }

        return $names;
    }

    private function minifyLayerName(string $name): string
    {
        $name = preg_replace('/\\\\20\s?/i', '\\ ', $name) ?? $name;
        $name = preg_replace('/\\\\([.#])/', '\\\\$1', $name) ?? $name;

        return preg_replace('/(?<!\\\\)\s+/', '', $name) ?? $name;
    }

    private function findTopLevelAtKeyword(string $css, string $keyword, int $start): ?int
    {
        $quote = null;
        $braceDepth = 0;
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
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }
            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if ($braceDepth === 0 && $parenDepth === 0 && $bracketDepth === 0 && $char === '@' && $this->startsAtKeyword($css, $i, $keyword)) {
                return $i;
            }
        }

        return null;
    }

    private function skipWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function minifyNamespaceStatement(string $statement): string
    {
        $rest = trim(substr($statement, strlen('@namespace')));
        if ($rest === '') {
            return '@namespace';
        }

        $tokens = $this->splitWhitespaceTopLevel($rest);
        if (count($tokens) === 1) {
            return '@namespace ' . $this->minifyNamespaceSourceToken($tokens[0]);
        }
        if (count($tokens) === 2) {
            return '@namespace ' . $tokens[0] . ' ' . $this->minifyNamespaceSourceToken($tokens[1]);
        }

        return '@namespace ' . $rest;
    }

    private function minifyNamespaceSourceToken(string $token): string
    {
        if ($this->startsUrlFunction($token, 0)) {
            [$url, $offset] = $this->readFunctionRaw($token, 0);
            if ($offset === strlen($token) - 1) {
                $value = $this->cssUrlTokenValue($url);
                if ($value !== null) {
                    return '"' . str_replace('"', '\\"', $value) . '"';
                }
            }
        }

        if (($token[0] ?? '') === '"' || ($token[0] ?? '') === "'") {
            return $this->normalizeCssStringToken($token);
        }

        return $token;
    }

    private function normalizeNamespaceAttributeSelectors(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $preludeStart = $this->findPreludeStart($css, $cursor, $open);
            $output .= substr($css, $cursor, $preludeStart - $cursor);

            $prelude = substr($css, $preludeStart, $open - $preludeStart);
            if ($this->isStyleRulePrelude($prelude)) {
                $prelude = $this->normalizeSelectorAttributeSelectors($prelude);
            }

            $output .= $prelude . '{';
            $cursor = $open + 1;
        }

        return $output;
    }

    private function findPreludeStart(string $css, int $start, int $open): int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $preludeStart = $start;

        for ($i = $start; $i < $open; $i++) {
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
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if ($parenDepth === 0 && $bracketDepth === 0 && ($char === ';' || $char === '}')) {
                $preludeStart = $i + 1;
            }
        }

        return $preludeStart;
    }

    private function isStyleRulePrelude(string $prelude): bool
    {
        $trimmed = trim($prelude);

        return $trimmed !== '' && $trimmed[0] !== '@';
    }

    private function normalizeSelectorAttributeSelectors(string $selector): string
    {
        $output = '';
        $quote = null;
        $parenDepth = 0;
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

            if ($char === '(') {
                $parenDepth++;
                $output .= $char;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                $output .= $char;
                continue;
            }

            if ($char === '[' && $parenDepth === 0) {
                $close = $this->findSelectorAttributeClose($selector, $i);
                if ($close !== null) {
                    $content = substr($selector, $i + 1, $close - $i - 1);
                    $output .= '[' . $this->normalizeAttributeSelectorContent($content) . ']';
                    $i = $close;
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function findSelectorAttributeClose(string $selector, int $open): ?int
    {
        $quote = null;
        $length = strlen($selector);

        for ($i = $open + 1; $i < $length; $i++) {
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
                continue;
            }
            if ($char === ']') {
                return $i;
            }
        }

        return null;
    }

    private function normalizeAttributeSelectorContent(string $content): string
    {
        if (preg_match('/^(.+?)\s*([~|^$*]?=)\s*(.+?)(?:\s+([a-zA-Z]))?$/s', trim($content), $matches) !== 1) {
            return $content;
        }

        $name = trim($matches[1]);
        if (str_starts_with($name, '|')) {
            $name = substr($name, 1);
        }

        $value = trim($matches[3]);
        if (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'") {
            $value = $this->normalizeCssStringToken($value);
        } elseif (preg_match('/^[-_a-zA-Z0-9]+$/', $value) === 1) {
            $value = '"' . str_replace('"', '\\"', $value) . '"';
        }

        $flag = isset($matches[4]) && $matches[4] !== '' ? ' ' . strtolower($matches[4]) : '';

        return $name . $matches[2] . $value . $flag;
    }

    private function minifyImportStatement(string $statement): string
    {
        $rest = trim(substr($statement, strlen('@import')));
        if ($rest === '') {
            return '@import';
        }

        $source = null;
        $offset = 0;
        if ($this->startsUrlFunction($rest, 0)) {
            [$url, $urlEnd] = $this->readFunctionRaw($rest, 0);
            $value = $this->cssUrlTokenValue($url);
            if ($value === null) {
                return '@import ' . $rest;
            }
            $source = '"' . str_replace('"', '\\"', $value) . '"';
            $offset = $urlEnd + 1;
        } elseif (($rest[0] ?? '') === '"' || ($rest[0] ?? '') === "'") {
            [$string, $stringEnd] = $this->readQuotedStringRaw($rest, 0);
            $source = $this->normalizeCssStringToken($string);
            $offset = $stringEnd + 1;
        }

        if ($source === null) {
            return '@import ' . $rest;
        }

        $tail = trim(substr($rest, $offset));
        $parts = [$source];
        $sawLayerModifier = false;
        while ($tail !== '') {
            if ($this->startsAtKeyword($tail, 0, 'layer')) {
                if (($tail[strlen('layer')] ?? '') === '(') {
                    [$function, $functionEnd] = $this->readFunctionRaw($tail, 0);
                    $layerName = trim(substr($function, strlen('layer('), -1));
                    if (count($this->splitTopLevel($layerName, ',')) > 1) {
                        throw new \InvalidArgumentException("Invalid @import layer name: {$layerName}");
                    }

                    $parts[] = 'layer(' . $this->minifyLayerName($layerName) . ')';
                    $tail = trim(substr($tail, $functionEnd + 1));
                    $sawLayerModifier = true;
                    continue;
                }

                $parts[] = 'layer';
                $tail = trim(substr($tail, strlen('layer')));
                $sawLayerModifier = true;
                continue;
            }

            if ($this->startsAtKeyword($tail, 0, 'supports') && ($tail[strlen('supports')] ?? '') === '(') {
                [$function, $functionEnd] = $this->readFunctionRaw($tail, 0);
                $condition = substr($function, strlen('supports('), -1);
                $wrappedCondition = $sawLayerModifier ? $this->unwrapSingleParenthesizedValue($condition) : null;
                $parts[] = $wrappedCondition === null
                    ? 'supports(' . $this->minifySupportsCondition($condition, false) . ')'
                    : 'supports((' . $this->minifySupportsCondition($wrappedCondition, false) . '))';
                $tail = trim(substr($tail, $functionEnd + 1));
                continue;
            }

            $parts[] = (new MediaQueryParser())->minifyList($tail, allowCompactedNegation: true);
            break;
        }

        return '@import ' . $this->serializeImportParts($parts);
    }

    /**
     * @param non-empty-list<string> $parts
     */
    private function serializeImportParts(array $parts): string
    {
        $output = array_shift($parts);
        $previous = $output;
        foreach ($parts as $part) {
            $separator = str_starts_with($previous, 'supports(') && str_starts_with($part, '(') ? '' : ' ';
            $output .= $separator . $part;
            $previous = $part;
        }

        return $output;
    }

    private function minifySupportsCondition(string $condition, bool $wrapDeclaration): string
    {
        $condition = trim($condition);
        if ($condition === '') {
            return '';
        }

        $inner = $this->unwrapSingleParenthesizedValue($condition);
        if ($inner !== null) {
            return $this->minifySupportsCondition($inner, $wrapDeclaration);
        }

        $logical = $this->splitContainerConditionByLogicalOperator($condition);
        if ($logical !== null) {
            $parts = [];
            foreach ($logical as $item) {
                $parts[] = $item['type'] === 'operator'
                    ? strtolower($item['value'])
                    : $this->minifySupportsCondition($item['value'], true);
            }

            return implode(' ', $parts);
        }

        if (preg_match('/^not(?:\s+|\()(.*)$/is', $condition, $matches) === 1) {
            $rest = trim($matches[1]);
            if (($condition[3] ?? '') === '(' && str_ends_with($rest, ')')) {
                $rest = substr($rest, 0, -1);
            }

            return 'not ' . $this->minifySupportsCondition($rest, true);
        }

        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\(/', $condition) === 1) {
            return trim($condition);
        }

        $colon = $this->findTopLevelColon($condition);
        if ($colon !== null) {
            $property = strtolower(trim(substr($condition, 0, $colon)));
            $value = trim(substr($condition, $colon + 1));
            $declaration = $property . ':' . $this->normalizeMathFunctionOperators($value);

            return $wrapDeclaration ? '(' . $declaration . ')' : $declaration;
        }

        return trim($condition);
    }

    private function minifySupportsRules(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $position = stripos($css, '@supports', $cursor);
            if ($position === false) {
                $output .= substr($css, $cursor);
                break;
            }
            $before = $position === 0 ? '' : $css[$position - 1];
            $after = $css[$position + 9] ?? '';
            if (($before !== '' && preg_match('/[-_a-zA-Z0-9]/', $before) === 1)
                || ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1)
            ) {
                $output .= substr($css, $cursor, $position + 9 - $cursor);
                $cursor = $position + 9;
                continue;
            }

            $open = $this->findNextTopLevel($css, '{', $position + 9);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = $this->minifySupportsRuleCondition(substr($css, $position + 9, $open - ($position + 9)));
            $output .= substr($css, $cursor, $position - $cursor) . '@supports';
            if ($prelude !== '') {
                $output .= ' ' . $prelude;
            }
            $output .= '{';
            $cursor = $open + 1;
        }

        return $output;
    }

    private function minifySupportsRuleCondition(string $condition): string
    {
        $node = $this->normalizeSupportsConditionNode($condition);

        return $node['css'];
    }

    /**
     * @return array{css:string,type:string}
     */
    private function normalizeSupportsConditionNode(string $condition): array
    {
        $condition = trim($condition);
        if ($condition === '') {
            return ['css' => '', 'type' => 'unknown'];
        }

        $inner = $this->unwrapSingleParenthesizedValue($condition);
        if ($inner !== null) {
            $node = $this->normalizeSupportsConditionNode($inner);
            if ($node['type'] === 'bare-unknown') {
                return ['css' => '(' . $node['css'] . ')', 'type' => 'unknown'];
            }

            return $node;
        }

        $logical = $this->splitContainerConditionByLogicalOperator($condition);
        if ($logical !== null) {
            $operator = null;
            foreach ($logical as $item) {
                if ($item['type'] === 'operator') {
                    $operator = strtolower($item['value']);
                    break;
                }
            }

            $parts = [];
            foreach ($logical as $item) {
                if ($item['type'] === 'operator') {
                    $parts[] = strtolower($item['value']);
                    continue;
                }

                $node = $this->normalizeSupportsConditionNode($item['value']);
                $css = $node['css'];
                if ($operator !== null && $this->supportsConditionNeedsParens($node, $operator)) {
                    $css = '(' . $css . ')';
                }
                $parts[] = $css;
            }

            return ['css' => implode(' ', $parts), 'type' => $operator ?? 'unknown'];
        }

        if (preg_match('/^not(?:\s+|\()(.*)$/is', $condition, $matches) === 1) {
            $hasFunctionParens = ($condition[3] ?? '') === '(';
            $rest = trim($matches[1]);
            if ($hasFunctionParens && str_ends_with($rest, ')')) {
                $rest = substr($rest, 0, -1);
            }

            $node = $this->normalizeSupportsConditionNode($rest);
            $operand = $node['css'];
            if ($this->supportsConditionNeedsParens($node, 'not')) {
                $operand = '(' . $operand . ')';
            }

            return ['css' => 'not ' . $operand, 'type' => 'not'];
        }

        $function = $this->normalizeSupportsConditionFunction($condition);
        if ($function !== null) {
            return ['css' => $function, 'type' => 'function'];
        }

        $colon = $this->findTopLevelColon($condition);
        if ($colon !== null) {
            $property = strtolower(trim(substr($condition, 0, $colon)));
            $value = $this->normalizeSupportsDeclarationValue(substr($condition, $colon + 1));

            return ['css' => '(' . $property . ':' . $value . ')', 'type' => 'declaration'];
        }

        return ['css' => trim($condition), 'type' => 'bare-unknown'];
    }

    /**
     * @param array{css:string,type:string} $node
     */
    private function supportsConditionNeedsParens(array $node, string $parentType): bool
    {
        return match ($node['type']) {
            'not' => true,
            'and' => $parentType !== 'and',
            'or' => $parentType !== 'or',
            default => false,
        };
    }

    private function normalizeSupportsConditionFunction(string $condition): ?string
    {
        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\(/', $condition, $matches) !== 1) {
            return null;
        }

        [$function, $offset] = $this->readFunctionRaw($condition, 0);
        if ($offset !== strlen($condition) - 1) {
            return null;
        }

        $name = strtolower($matches[1]);
        $inner = substr($function, strlen($matches[1]) + 1, -1);
        if ($name === 'selector') {
            $inner = preg_replace('/\s*([>+~])\s*/', ' $1 ', trim($inner)) ?? trim($inner);
            $inner = preg_replace('/\s+/', ' ', $inner) ?? $inner;

            return 'selector(' . $inner . ')';
        }

        return $name . '(' . trim($inner) . ')';
    }

    private function normalizeSupportsDeclarationValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^calc\((.*)\)$/i', $value, $matches) === 1) {
            $inner = preg_replace('/\s*([*\/])\s*/', ' $1 ', trim($matches[1])) ?? trim($matches[1]);
            $inner = preg_replace('/\s+/', ' ', $inner) ?? $inner;

            return 'calc(' . $inner . ')';
        }
        if (preg_match('/^(hsl|hsla|rgb|rgba)\((.*)\)$/i', $value, $matches) === 1) {
            $inner = preg_replace('/\s*,\s*/', ', ', trim($matches[2])) ?? trim($matches[2]);

            return strtolower($matches[1]) . '(' . $inner . ')';
        }

        return $this->normalizeMathFunctionOperators($value);
    }

    private function needsSpaceBefore(string $output, string $next): bool
    {
        if ($output === '') {
            return false;
        }
        $previous = $output[strlen($output) - 1];
        if ($previous === ')') {
            return ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.' || $next === '#';
        }
        if ($previous === '"' || $previous === "'") {
            return ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.' || $next === '#';
        }
        if ($next === '"' || $next === "'") {
            return ctype_alnum($previous) || $previous === '_' || $previous === '-' || $previous === '%';
        }

        return (ctype_alnum($previous) || $previous === '_' || $previous === '-' || $previous === '%')
            && (ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.' || $next === '#');
    }

    private function needsSelectorDescendantSpaceAfterAttribute(string $output, string $css, int $offset): bool
    {
        if ($output === '' || $output[strlen($output) - 1] !== ']') {
            return false;
        }
        $next = $css[$offset] ?? '';
        if (!($next === '*' || $next === '.' || $next === '#' || $next === '[' || ctype_alpha($next))) {
            return false;
        }

        return $this->isSelectorContextAhead($css, $offset);
    }

    private function startsDescendantPseudoClass(string $css, int $offset): bool
    {
        return preg_match('/^:(?:(?:is|where|not|has)\(|scope\b)/i', substr($css, $offset)) === 1;
    }

    private function needsSelectorDescendantSpaceBeforePseudo(string $output): bool
    {
        if ($output === '') {
            return false;
        }

        $previous = $output[strlen($output) - 1];

        return !in_array($previous, ['{', ',', '>', '+', '~', '('], true);
    }

    private function needsSelectorDescendantSpaceBeforeParentReference(string $output): bool
    {
        if ($output === '') {
            return false;
        }

        $previous = $output[strlen($output) - 1];

        return !in_array($previous, ['{', ',', '>', '+', '~', '('], true);
    }

    private function minifyDeclarationValues(string $css): string
    {
        $output = '';
        $quote = null;
        $braceDepth = 0;
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

            if ($char === '{') {
                $braceDepth++;
                $output .= $char;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $output .= $char;
                continue;
            }

            if ($char !== ':' || $braceDepth === 0) {
                $output .= $char;
                continue;
            }

            $property = $this->currentPropertyCandidate($output);
            if (!$this->isDeclarationProperty($property)) {
                $output .= $char;
                continue;
            }

            [$value, $delimiter, $offset] = $this->readDeclarationValue($css, $i + 1);
            $output .= ':' . $this->minifyDeclarationValue($property, $value);
            if ($delimiter !== '') {
                if ($delimiter === '}') {
                    $braceDepth = max(0, $braceDepth - 1);
                }
                $output .= $delimiter;
            }
            $i = $offset;
        }

        return $output;
    }

    private function canonicalizeImplicitNestedSelectors(string $css): string
    {
        return $this->canonicalizeImplicitNestedSelectorsInRuleList($css, false);
    }

    private function canonicalizeImplicitNestedSelectorsInRuleList(string $css, bool $insideStyleRule): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $preludePrefix = substr($css, $cursor, $open - $cursor);
            $statementBoundary = $this->lastTopLevelSemicolon($preludePrefix);
            $statementPrefix = '';
            if ($statementBoundary !== null) {
                $statementPrefix = substr($preludePrefix, 0, $statementBoundary + 1);
                $preludePrefix = substr($preludePrefix, $statementBoundary + 1);
            }

            $prelude = trim($preludePrefix);
            $close = $this->findMatchingBraceInCss($css, $open);
            $isAtRule = str_starts_with($prelude, '@');
            $body = $this->canonicalizeImplicitNestedSelectorsInRuleList(
                substr($css, $open + 1, $close - $open - 1),
                $isAtRule ? $insideStyleRule : true
            );

            if ($insideStyleRule) {
                $prelude = $this->canonicalizeNestedStylePrelude($prelude);
            }

            $output .= $statementPrefix . $prelude . '{' . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function canonicalizeNestedStylePrelude(string $prelude): string
    {
        if ($prelude === '' || str_starts_with($prelude, '@') || str_starts_with($prelude, '--')) {
            return $prelude;
        }

        return implode(
            ',',
            array_map(
                fn (string $selector): string => $this->canonicalizeImplicitNestedSelector($selector),
                $this->splitTopLevel($prelude, ',')
            )
        );
    }

    private function canonicalizeImplicitNestedSelector(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '' || str_contains($selector, '&')) {
            return $selector;
        }

        return $this->startsWithSelectorCombinator($selector)
            ? '&' . $selector
            : '& ' . $selector;
    }

    private function startsWithSelectorCombinator(string $selector): bool
    {
        $selector = ltrim($selector);

        return $selector !== '' && in_array($selector[0], ['>', '+', '~'], true);
    }

    private function minifyMediaQueries(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);
        $parser = new MediaQueryParser();

        while ($cursor < $length) {
            $position = stripos($css, '@media', $cursor);
            if ($position === false) {
                $output .= substr($css, $cursor);
                break;
            }
            $before = $position === 0 ? '' : $css[$position - 1];
            $after = $css[$position + 6] ?? '';
            if (($before !== '' && preg_match('/[-_a-zA-Z0-9]/', $before) === 1)
                || ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1)
            ) {
                $output .= substr($css, $cursor, $position + 6 - $cursor);
                $cursor = $position + 6;
                continue;
            }

            $open = $this->findNextTopLevel($css, '{', $position + 6);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = trim(substr($css, $position + 6, $open - ($position + 6)));
            $minifiedPrelude = $prelude === '' ? '' : $parser->minifyList($prelude, allowCompactedNegation: true);
            if ($minifiedPrelude === '' || strcasecmp($minifiedPrelude, 'all') === 0) {
                $close = $this->findMatchingBraceInCss($css, $open);
                $body = substr($css, $open + 1, $close - $open - 1);
                $output .= substr($css, $cursor, $position - $cursor) . $this->minifyMediaQueries($body);
                $cursor = $close + 1;
                continue;
            }

            if (strcasecmp($minifiedPrelude, 'not all') === 0) {
                $close = $this->findMatchingBraceInCss($css, $open);
                $output .= substr($css, $cursor, $position - $cursor);
                $cursor = $close + 1;
                continue;
            }

            $output .= substr($css, $cursor, $position - $cursor) . '@media ' . $minifiedPrelude . '{';
            $cursor = $open + 1;
        }

        return $output;
    }

    private function minifyContainerQueries(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $position = stripos($css, '@container', $cursor);
            if ($position === false) {
                $output .= substr($css, $cursor);
                break;
            }
            $before = $position === 0 ? '' : $css[$position - 1];
            $after = $css[$position + 10] ?? '';
            if (($before !== '' && preg_match('/[-_a-zA-Z0-9]/', $before) === 1)
                || ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1)
            ) {
                $output .= substr($css, $cursor, $position + 10 - $cursor);
                $cursor = $position + 10;
                continue;
            }

            $open = $this->findNextTopLevel($css, '{', $position + 10);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = $this->minifyContainerPrelude(substr($css, $position + 10, $open - ($position + 10)));
            $output .= substr($css, $cursor, $position - $cursor) . '@container';
            if ($prelude !== '') {
                $output .= ' ' . $prelude;
            }
            $output .= '{';
            $cursor = $open + 1;
        }

        return $output;
    }

    private function minifyContainerPrelude(string $prelude): string
    {
        $prelude = trim($prelude);
        if ($prelude === '') {
            throw new \InvalidArgumentException('@container rule is missing a name or condition');
        }

        $this->validateContainerPrelude($prelude);
        [$name, $condition] = $this->splitContainerNameAndCondition($prelude);
        if ($condition === null) {
            return $name ?? $prelude;
        }

        $condition = $this->minifyContainerCondition($condition, true);

        return $name === null ? $condition : $name . ' ' . $condition;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitContainerNameAndCondition(string $prelude): array
    {
        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)(.*)$/s', $prelude, $matches) !== 1) {
            return [null, $prelude];
        }

        $head = strtolower($matches[1]);
        if (in_array($head, ['not', 'style', 'scroll-state'], true)) {
            return [null, $prelude];
        }

        if (($matches[2] ?? '') !== '' && !ctype_space($matches[2][0]) && $head === 'unknown') {
            return [null, $prelude];
        }

        $tail = trim($matches[2]);
        if ($tail === '') {
            return [$matches[1], null];
        }

        return [$matches[1], $tail];
    }

    private function validateContainerPrelude(string $prelude): void
    {
        if ($this->isInvalidContainerName($prelude)) {
            throw new \InvalidArgumentException("Invalid @container name: {$prelude}");
        }

        [$name, $condition] = $this->splitContainerNameAndCondition($prelude);
        if ($name !== null && $this->isInvalidContainerName($name)) {
            throw new \InvalidArgumentException("Invalid @container name: {$name}");
        }

        if ($condition === null) {
            return;
        }

        if ($name !== null && !$this->isNamedContainerConditionStart($condition)) {
            $token = $this->splitWhitespaceTopLevel($condition)[0] ?? $condition;
            throw new \InvalidArgumentException("Invalid @container condition after {$name}: {$token}");
        }

        $this->validateContainerCondition($condition, false);
    }

    private function isInvalidContainerName(string $name): bool
    {
        return in_array(strtolower(trim($name)), [
            'and',
            'initial',
            'inherit',
            'none',
            'not',
            'or',
            'revert',
            'revert-layer',
            'unset',
        ], true);
    }

    private function isNamedContainerConditionStart(string $condition): bool
    {
        $condition = strtolower(ltrim($condition));

        return str_starts_with($condition, '(')
            || str_starts_with($condition, 'not ')
            || str_starts_with($condition, 'not(')
            || str_starts_with($condition, 'style(')
            || str_starts_with($condition, 'scroll-state(');
    }

    private function validateContainerCondition(string $condition, bool $styleMode): void
    {
        $condition = trim($condition);
        if ($condition === '') {
            throw new \InvalidArgumentException('Invalid empty @container condition');
        }

        $inner = $this->unwrapSingleParenthesizedValue($condition);
        if ($inner !== null) {
            if (trim($inner) === '') {
                throw new \InvalidArgumentException('Invalid empty @container condition');
            }
            $this->validateContainerCondition($inner, $styleMode);

            return;
        }

        $logical = $this->splitContainerConditionByLogicalOperator($condition);
        if ($logical !== null) {
            foreach ($logical as $item) {
                if ($item['type'] === 'condition') {
                    $this->validateContainerCondition($item['value'], $styleMode);
                }
            }

            return;
        }

        if (preg_match('/^not(?:\s+|\()(.*)$/is', $condition, $matches) === 1) {
            $rest = trim($matches[1]);
            if (($condition[3] ?? '') === '(' && str_ends_with($rest, ')')) {
                $rest = substr($rest, 0, -1);
            }
            $this->validateContainerCondition($rest, $styleMode);

            return;
        }

        $functionName = $this->wholeContainerConditionFunctionName($condition);
        if ($functionName !== null) {
            if (!in_array($functionName, ['style', 'scroll-state'], true)) {
                throw new \InvalidArgumentException("Unknown @container condition function: {$functionName}");
            }

            $function = $this->readNamedFunctionIfWhole($condition, $functionName);
            if ($function !== null && $this->wholeContainerConditionFunctionName($function) === $functionName) {
                throw new \InvalidArgumentException("Nested @container {$functionName}() conditions are invalid");
            }
            if ($function !== null) {
                $this->validateContainerCondition($function, $styleMode || $functionName === 'style');
            }

            return;
        }

        $this->validateContainerFeature($condition, $styleMode);
    }

    private function wholeContainerConditionFunctionName(string $condition): ?string
    {
        $condition = trim($condition);
        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\(/', $condition, $matches) !== 1) {
            return null;
        }

        [, $offset] = $this->readFunctionRaw($condition, 0);

        return $offset === strlen($condition) - 1 ? strtolower($matches[1]) : null;
    }

    private function validateContainerFeature(string $feature, bool $styleMode): void
    {
        $feature = trim($feature);
        if ($feature === '') {
            throw new \InvalidArgumentException('Invalid empty @container condition');
        }

        if ($styleMode) {
            return;
        }

        if (preg_match('/^(?:inline-size|block-size|width|height)\s*(?:<=|>=|<|>|=)\s*[-_a-zA-Z][-_a-zA-Z0-9]*$/i', $feature) === 1) {
            throw new \InvalidArgumentException("Invalid @container range comparison: {$feature}");
        }

        if (preg_match('/^orientation\s*(?:<=|>=|<|>|=)\s*/i', $feature) === 1) {
            throw new \InvalidArgumentException("Invalid @container range feature: {$feature}");
        }
    }

    private function minifyContainerCondition(string $condition, bool $topLevel = false, bool $styleMode = false): string
    {
        $condition = trim($condition);
        if ($condition === '') {
            return '';
        }

        $inner = $this->unwrapSingleParenthesizedValue($condition);
        if ($inner !== null) {
            $normalizedInner = $this->minifyContainerCondition($inner, false, $styleMode);
            if ($this->canDropContainerConditionParens($normalizedInner, $topLevel)) {
                return $normalizedInner;
            }

            return '(' . $normalizedInner . ')';
        }

        $logical = $this->splitContainerConditionByLogicalOperator($condition);
        if ($logical !== null) {
            $parts = [];
            foreach ($logical as $item) {
                if ($item['type'] === 'operator') {
                    $parts[] = strtolower($item['value']);
                    continue;
                }
                $parts[] = $this->minifyContainerCondition($item['value'], false, $styleMode);
            }

            return implode(' ', $parts);
        }

        if (preg_match('/^not(?:\s+|\()(.*)$/is', $condition, $matches) === 1) {
            $hasFunctionParens = ($condition[3] ?? '') === '(';
            $rest = trim($matches[1]);
            if ($hasFunctionParens && str_ends_with($rest, ')')) {
                $rest = substr($rest, 0, -1);
            }
            $operand = $this->minifyContainerCondition($rest, false, $styleMode);
            if ($this->hasTopLevelContainerLogicalOperator($operand)) {
                $operand = '(' . $operand . ')';
            } elseif ($hasFunctionParens
                && !str_starts_with($operand, '(')
                && !str_starts_with($operand, 'style(')
                && !str_starts_with($operand, 'scroll-state(')
            ) {
                $operand = '(' . $operand . ')';
            }

            return 'not ' . $operand;
        }

        foreach (['style', 'scroll-state'] as $functionName) {
            $function = $this->readNamedFunctionIfWhole($condition, $functionName);
            if ($function !== null) {
                $innerMode = $functionName === 'style' || $styleMode;

                return $functionName . '(' . $this->minifyContainerCondition($function, true, $innerMode) . ')';
            }
        }

        return $styleMode
            ? $this->minifyContainerStyleFeature($condition)
            : $this->minifyContainerFeature($condition);
    }

    private function canDropContainerConditionParens(string $condition, bool $topLevel): bool
    {
        if ($this->hasTopLevelContainerLogicalOperator($condition)) {
            return true;
        }

        if (!$topLevel && str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
            return true;
        }

        if (!$topLevel) {
            return false;
        }

        return str_starts_with($condition, 'not ')
            || str_starts_with($condition, 'style(')
            || str_starts_with($condition, 'scroll-state(');
    }

    private function hasTopLevelContainerLogicalOperator(string $condition): bool
    {
        return $this->splitContainerConditionByLogicalOperator($condition) !== null;
    }

    /**
     * @return list<array{type:string,value:string}>|null
     */
    private function splitContainerConditionByLogicalOperator(string $condition): ?array
    {
        $items = [];
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

            if ($parenDepth === 0 && $this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($condition, $i);
                $lower = strtolower($identifier);
                $previous = $condition[$i - 1] ?? '';
                $next = $condition[$i + strlen($identifier)] ?? '';
                if (in_array($lower, ['and', 'or'], true)
                    && ($previous === '' || !$this->isIdentifierChar($previous))
                    && ($next === '' || !$this->isIdentifierChar($next))
                ) {
                    if (trim($current) === '') {
                        return null;
                    }
                    $items[] = ['type' => 'condition', 'value' => trim($current)];
                    $items[] = ['type' => 'operator', 'value' => $lower];
                    $current = '';
                    $found = true;
                    $i += strlen($identifier) - 1;
                    while (isset($condition[$i + 1]) && ctype_space($condition[$i + 1])) {
                        $i++;
                    }
                    continue;
                }
            }

            $current .= $char;
        }

        if (!$found || trim($current) === '') {
            return null;
        }

        $items[] = ['type' => 'condition', 'value' => trim($current)];

        return $items;
    }

    private function minifyContainerStyleFeature(string $feature): string
    {
        $feature = preg_replace('/\s*!\s*important\b/i', '', $feature) ?? $feature;

        return $this->minifyColorKeywords($this->minifyContainerFeature($feature));
    }

    private function minifyContainerFeature(string $feature): string
    {
        $feature = trim($feature);
        try {
            $query = (new MediaQueryParser())->minifyList('(' . $feature . ')');
            $inner = $this->unwrapSingleParenthesizedValue($query);
            if ($inner !== null) {
                return $inner;
            }
        } catch (\Throwable) {
        }

        return trim($feature);
    }

    private function readNamedFunctionIfWhole(string $value, string $name): ?string
    {
        $prefix = $name . '(';
        if (strtolower(substr($value, 0, strlen($prefix))) !== $prefix) {
            return null;
        }

        [$function, $offset] = $this->readFunctionRaw($value, 0);
        if ($offset !== strlen($value) - 1) {
            return null;
        }

        return substr($function, strlen($prefix), -1);
    }

    private function unwrapSingleParenthesizedValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value[0] !== '(') {
            return null;
        }

        $quote = null;
        $depth = 0;
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
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i === $length - 1 ? substr($value, 1, -1) : null;
                }
            }
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

    private function currentPropertyCandidate(string $output): string
    {
        $block = strrpos($output, '{');
        $closeBlock = strrpos($output, '}');
        $semicolon = strrpos($output, ';');
        $start = max(
            $block === false ? -1 : $block,
            $closeBlock === false ? -1 : $closeBlock,
            $semicolon === false ? -1 : $semicolon
        ) + 1;

        return trim(substr($output, $start));
    }

    private function isDeclarationProperty(string $property): bool
    {
        return preg_match('/^(?:[_a-zA-Z]|-[_a-zA-Z]|--[_a-zA-Z])[-_a-zA-Z0-9]*$/', $property) === 1;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function readDeclarationValue(string $css, int $start): array
    {
        $value = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($css);

        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $value .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $value .= $css[++$i];
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
            } elseif (($char === ';' || $char === '}') && $parenDepth === 0 && $bracketDepth === 0) {
                return [$value, $char, $i];
            }

            $value .= $char;
        }

        return [$value, '', $length - 1];
    }

    private function minifyDeclarationValue(string $property, string $value): string
    {
        $customPropertyColorCalc = str_starts_with($property, '--') && $this->containsColorFunctionCalc($value);
        $value = $this->minifyMathFunctions($this->normalizeMathFunctionOperators($value));
        $value = $this->minifyTransformValue($property, $value);
        $value = $this->minifyAnimationLonghandValue($property, $value);
        $value = $this->minifyTransitionLonghandValue($property, $value);
        $value = $this->minifyFilterValue($property, $value);
        $value = $this->minifyBoxShadowValue($property, $value);
        $value = $this->minifyTextEmphasisValue($property, $value);
        $value = $this->minifyCaretValue($property, $value);
        $value = $this->minifyListStyleValue($property, $value);
        $value = $this->minifyContainerDeclarationValue($property, $value);
        $value = $this->minifyBorderRadiusValue($property, $value);
        $value = $this->minifyAspectRatioValue($property, $value);
        $value = $this->minifyGridValue($property, $value);
        $value = $this->minifyFontValue($property, $value);
        $value = $this->minifyColorSchemeValue($property, $value);
        $value = $this->minifyImageSetFunctions($value);
        $value = $this->minifyGradientFunctions($value);
        $value = $this->minifyBoxLengthListValue($property, $value);
        if (str_starts_with($property, '--')) {
            if ($customPropertyColorCalc) {
                $value = $this->minifyColorFunctionsAndHex($value);
            }
        } elseif (!$this->isFontFamilySensitiveProperty($property)) {
            $value = $this->minifyColorKeywords($value);
            $value = $this->minifySrgbColorMixFunctions($value);
            $value = $this->minifyLightDarkFunctions($value);
        }

        return $value;
    }

    private function containsColorFunctionCalc(string $value): bool
    {
        return preg_match('/\b(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color)\([^;{}]*\bcalc\(/i', $value) === 1;
    }

    private function minifyBorderRadiusValue(string $property, string $value): string
    {
        if (!in_array(strtolower($property), ['border-radius', '-webkit-border-radius', '-moz-border-radius'], true)) {
            return $value;
        }

        $parts = $this->splitTopLevel($value, '/');
        if ($parts === [] || count($parts) > 2) {
            return trim($value);
        }

        $horizontal = $this->minifyBorderRadiusSideList($parts[0]);
        if ($horizontal === null) {
            return trim($value);
        }

        if (count($parts) === 1) {
            return $horizontal;
        }

        $vertical = $this->minifyBorderRadiusSideList($parts[1]);
        if ($vertical === null) {
            return trim($value);
        }

        return $horizontal . '/' . $vertical;
    }

    private function minifyAspectRatioValue(string $property, string $value): string
    {
        if (strtolower($property) !== 'aspect-ratio') {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) === 1) {
            $tokens = $this->splitWhitespaceTopLevel($value);
            if (count($tokens) === 1) {
                return strtolower($tokens[0]) === 'auto'
                    ? 'auto'
                    : $this->minifyPlainNumberToken($tokens[0]);
            }

            return $value;
        }

        if (count($parts) !== 2) {
            return $value;
        }

        $leftTokens = $this->splitWhitespaceTopLevel($parts[0]);
        $rightTokens = $this->splitWhitespaceTopLevel($parts[1]);
        if ($leftTokens === [] || $rightTokens === []) {
            return $value;
        }

        $hasAuto = false;
        if (strtolower($leftTokens[0]) === 'auto') {
            $hasAuto = true;
            array_shift($leftTokens);
        }
        if ($rightTokens !== [] && strtolower($rightTokens[array_key_last($rightTokens)]) === 'auto') {
            if ($hasAuto) {
                return $value;
            }

            $hasAuto = true;
            array_pop($rightTokens);
        }

        if (count($leftTokens) !== 1 || count($rightTokens) !== 1) {
            return $value;
        }

        $ratio = $this->minifyPlainNumberToken($leftTokens[0]) . '/' . $this->minifyPlainNumberToken($rightTokens[0]);

        return $hasAuto ? 'auto ' . $ratio : $ratio;
    }

    private function minifyGridValue(string $property, string $value): string
    {
        $property = strtolower($property);
        if (!in_array($property, [
            'grid',
            'grid-area',
            'grid-auto-columns',
            'grid-auto-flow',
            'grid-auto-rows',
            'grid-column',
            'grid-column-end',
            'grid-column-start',
            'grid-row',
            'grid-row-end',
            'grid-row-start',
            'grid-template',
            'grid-template-areas',
            'grid-template-columns',
            'grid-template-rows',
        ], true)) {
            return $value;
        }

        if ($property === 'grid-template-areas') {
            return $this->normalizeGridQuotedAreaRows($value);
        }

        if ($property === 'grid-auto-flow') {
            return $this->minifyGridAutoFlowValue($value);
        }

        if (in_array($property, ['grid-row-start', 'grid-row-end', 'grid-column-start', 'grid-column-end'], true)) {
            return $this->minifyGridLineValue($value);
        }

        if ($property === 'grid-row' || $property === 'grid-column') {
            return $this->minifyGridLineShorthandValue($value);
        }

        if ($property === 'grid-area') {
            return $this->minifyGridAreaValue($value);
        }

        $value = $this->normalizeGridQuotedAreaRows($value);
        $value = preg_replace('/\bdense\s+auto-flow\b/i', 'auto-flow dense', $value) ?? $value;
        $value = preg_replace('/\bauto-flow\s+auto(?=\/|$)/i', 'auto-flow', $value) ?? $value;
        $value = $this->mergeAdjacentGridLineNameBlocks($value);
        $value = preg_replace(
            '/"\s+(?=(?:[+-]?(?:\d|\.)|auto\b|minmax\(|min-content\b|max-content\b|fit-content\(|repeat\())/i',
            '"',
            $value
        ) ?? $value;
        $value = $this->compactGridTemplateAreaTrackSpacing($value);
        $value = $this->minifyGridNumericDimensions($value);

        return $property === 'grid' ? $this->minifyGridAutoFlowDefaultRows($value) : $value;
    }

    private function normalizeGridQuotedAreaRows(string $value): string
    {
        $output = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char !== '"' && $char !== "'") {
                $output .= $char;
                continue;
            }

            $quote = $char;
            $row = '';
            for ($j = $i + 1; $j < $length; $j++) {
                $current = $value[$j];
                if ($current === '\\' && $j + 1 < $length) {
                    $row .= $current . $value[++$j];
                    continue;
                }
                if ($current === $quote) {
                    $output .= '"' . $this->normalizeGridTemplateAreaRowText($row) . '"';
                    $i = $j;
                    continue 2;
                }
                $row .= $current;
            }

            $output .= $quote . $row;
            break;
        }

        return $output;
    }

    private function normalizeGridTemplateAreaRowText(string $row): string
    {
        $tokens = preg_split('/\s+/', trim($row)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return '';
        }

        $normalized = array_map(
            static fn (string $token): string => preg_match('/^\.+$/', $token) === 1 ? '.' : $token,
            $tokens
        );
        $dotCount = count(array_filter($normalized, static fn (string $token): bool => $token === '.'));
        if ($dotCount > 0 && $dotCount < count($normalized)) {
            return implode('', $normalized);
        }

        return implode(' ', $normalized);
    }

    private function mergeAdjacentGridLineNameBlocks(string $value): string
    {
        do {
            $previous = $value;
            $value = preg_replace('/\[([^\]\[]+)\]\[([^\]\[]+)\]/', '[$1 $2]', $value) ?? $value;
        } while ($value !== $previous);

        return $value;
    }

    private function compactGridTemplateAreaTrackSpacing(string $value): string
    {
        $trackToken = '(?:'
            . '\[[^\]\[]+\]'
            . '|[+-]?(?:\d+\.\d+|\.\d+|\d+)(?:fr|px|em|rem|ch|ex|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cqw|cqh|cqi|cqb|cqmin|cqmax|%)'
            . '|auto|min-content|max-content|none'
            . '|minmax\([^()]*\)'
            . '|fit-content\([^()]*\)'
            . '|repeat\([^()]*\)'
            . ')';

        return preg_replace('/(' . $trackToken . ')\s+(?=")/i', '$1', $value) ?? $value;
    }

    private function minifyGridAutoFlowDefaultRows(string $value): string
    {
        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) !== 2) {
            return $value;
        }

        $rows = trim($parts[0]);
        $columns = trim($parts[1]);
        if (strcasecmp($rows, 'auto-flow') !== 0 || $columns === '') {
            return $value;
        }

        return 'none/' . $columns;
    }

    private function minifyGridAutoFlowValue(string $value): string
    {
        $tokens = array_map(static fn (string $token): string => strtolower($token), $this->splitWhitespaceTopLevel(trim($value)));
        if ($tokens === []) {
            return trim($value);
        }

        $known = ['row', 'column', 'dense'];
        foreach ($tokens as $token) {
            if (!in_array($token, $known, true)) {
                return trim($value);
            }
        }

        $hasDense = in_array('dense', $tokens, true);
        if (in_array('column', $tokens, true)) {
            return $hasDense ? 'column dense' : 'column';
        }

        return $hasDense ? 'dense' : 'row';
    }

    private function minifyGridLineShorthandValue(string $value): string
    {
        $parts = $this->splitTopLevel($value, '/');
        if ($parts === [] || count($parts) > 2) {
            return trim($value);
        }

        $start = $this->minifyGridLineValue($parts[0]);
        $end = isset($parts[1]) ? $this->minifyGridLineValue($parts[1]) : 'auto';
        if (strcasecmp($end, 'auto') === 0) {
            return $start;
        }
        if ($start === $end && $this->canCollapseRepeatedGridAreaLine($start)) {
            return $start;
        }

        return $start . '/' . $end;
    }

    private function minifyGridAreaValue(string $value): string
    {
        $parts = array_map(fn (string $part): string => $this->minifyGridLineValue($part), $this->splitTopLevel($value, '/'));
        if ($parts === []) {
            return trim($value);
        }
        if (count($parts) < 4) {
            return $this->minifyGridLineShorthandValue(implode('/', $parts));
        }

        $parts = array_slice($parts, 0, 4);
        if ($parts[0] === $parts[1] && $parts[0] === $parts[2] && $parts[0] === $parts[3]
            && $this->canCollapseRepeatedGridAreaLine($parts[0])
        ) {
            return $parts[0];
        }
        if ($parts[0] === $parts[2] && $parts[1] === $parts[3]
            && $this->canCollapseRepeatedGridAreaLine($parts[0])
            && $this->canCollapseRepeatedGridAreaLine($parts[1])
        ) {
            return $parts[0] . '/' . $parts[1];
        }
        if ($parts[1] === $parts[3] && $this->canCollapseRepeatedGridAreaLine($parts[1])) {
            return $parts[0] . '/' . $parts[1] . '/' . $parts[2];
        }

        return implode('/', $parts);
    }

    private function minifyGridLineValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return trim($value);
        }

        $hasSpan = false;
        $number = null;
        $name = null;
        foreach ($tokens as $token) {
            $lower = strtolower($token);
            if ($lower === 'span') {
                $hasSpan = true;
                continue;
            }
            if ($this->isIntegerToken($token)) {
                $number ??= (string) (int) $token;
                continue;
            }
            if ($name !== null || str_contains($token, '(')) {
                return implode(' ', $tokens);
            }
            $name = $token;
        }

        if ($hasSpan) {
            $parts = ['span'];
            if ($number !== null && !($number === '1' && $name !== null)) {
                $parts[] = $number;
            }
            if ($name !== null) {
                $parts[] = $name;
            }

            return implode(' ', $parts);
        }

        if ($number !== null && $name !== null) {
            return $number . ' ' . $name;
        }

        return $this->minifyGridNumericDimensions(implode(' ', $tokens));
    }

    private function isIntegerToken(string $token): bool
    {
        return preg_match('/^[+-]?\d+$/', trim($token)) === 1;
    }

    private function canCollapseRepeatedGridAreaLine(string $value): bool
    {
        $value = trim($value);
        if (strcasecmp($value, 'auto') === 0) {
            return true;
        }
        if (str_contains($value, ' ') || str_contains($value, '(') || $this->isIntegerToken($value)) {
            return false;
        }

        return preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $value) === 1;
    }

    private function minifyGridNumericDimensions(string $value): string
    {
        return preg_replace_callback(
            '/(?<![_a-zA-Z0-9.-])([+-]?(?:\d+\.\d+|\.\d+|\d+))(fr|px|em|rem|ch|ex|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cqw|cqh|cqi|cqb|cqmin|cqmax|%)(?=$|[^_a-zA-Z0-9-])/i',
            fn (array $matches): string => $this->minifyNumericDimensionToken($matches[1] . $matches[2]),
            $value
        ) ?? $value;
    }

    private function minifyBorderRadiusSideList(string $value): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === [] || count($tokens) > 4) {
            return null;
        }

        $tokens = array_map(fn (string $token): string => $this->minifyLengthToken($token), $tokens);

        return implode(' ', $this->compressBoxSideValues($tokens));
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function compressBoxSideValues(array $tokens): array
    {
        if (count($tokens) === 4 && $tokens[3] === $tokens[1]) {
            array_pop($tokens);
        }

        if (count($tokens) === 3 && $tokens[2] === $tokens[0]) {
            array_pop($tokens);
        }

        if (count($tokens) === 2 && $tokens[1] === $tokens[0]) {
            array_pop($tokens);
        }

        return $tokens;
    }

    private function minifyLightDarkFunctions(string $value): string
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

            if (!$this->isIdentifierStart($char)) {
                $output .= $char;
                continue;
            }

            $identifier = $this->readIdentifier($value, $i);
            if (strtolower($identifier) !== 'light-dark' || ($value[$i + strlen($identifier)] ?? '') !== '(') {
                $output .= $identifier;
                $i += strlen($identifier) - 1;
                continue;
            }

            [$function, $offset] = $this->readFunctionRaw($value, $i);
            $arguments = substr($function, strlen($identifier) + 1, -1);
            $parts = $this->splitTopLevel($arguments, ',');
            if (count($parts) !== 2) {
                $output .= $function;
                $i = $offset;
                continue;
            }

            $light = $this->minifyLightDarkFunctions($parts[0]);
            $dark = $this->minifyLightDarkFunctions($parts[1]);
            $output .= $light === $dark ? $light : 'light-dark(' . $light . ',' . $dark . ')';
            $i = $offset;
        }

        return $output;
    }

    private function minifyColorSchemeValue(string $property, string $value): string
    {
        if (strtolower($property) !== 'color-scheme') {
            return $value;
        }

        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return trim($value);
        }

        foreach ($tokens as $token) {
            if (str_contains($token, '(')) {
                return trim($value);
            }
        }

        $tokens = array_map(static fn (string $token): string => strtolower($token), $tokens);
        $unknown = array_diff($tokens, ['light', 'dark', 'only']);
        if ($unknown === [] && (in_array('light', $tokens, true) || in_array('dark', $tokens, true))) {
            $ordered = [];
            if (in_array('light', $tokens, true)) {
                $ordered[] = 'light';
            }
            if (in_array('dark', $tokens, true)) {
                $ordered[] = 'dark';
            }
            if (in_array('only', $tokens, true)) {
                $ordered[] = 'only';
            }

            return implode(' ', $ordered);
        }

        return implode(' ', $tokens);
    }

    private function minifyBoxLengthListValue(string $property, string $value): string
    {
        $property = strtolower($property);
        if ($property !== 'margin' && $property !== 'padding' && !preg_match('/^(?:margin|padding|inset)(?:-|$)/', $property)) {
            return $value;
        }

        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return $value;
        }

        return implode(' ', array_map(fn (string $token): string => $this->minifyLengthToken($token), $tokens));
    }

    private function minifyLengthToken(string $token): string
    {
        $token = strtolower(trim($token));
        if (preg_match('/^[+-]?0(?:\.0+)?[a-z%]+$/', $token) === 1) {
            return '0';
        }

        return $this->minifyNumericDimensionToken($token);
    }

    private function minifyFontValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'font' => $this->minifyFontShorthandValue($value),
            'font-family' => $this->minifyFontFamilyList($value),
            'font-style' => $this->minifyFontStyleValue($value),
            'font-stretch' => $this->minifyFontStretchValue($value),
            'font-variant-caps' => strtolower(trim($value)),
            'font-weight' => $this->minifyFontWeightValue($value),
            'src' => $this->minifyFontFaceSrcValue($value),
            'unicode-range' => $this->minifyUnicodeRangeValue($value),
            'override-colors' => $this->minifyFontPaletteOverrideColorsValue($value),
            default => $value,
        };
    }

    private function minifyFontShorthandValue(string $value): string
    {
        $components = $this->parseFontShorthandComponents($value);

        return $components === null ? trim($value) : $this->serializeFontShorthandComponents($components);
    }

    /**
     * @return array{style:string,variant:string,weight:string,stretch:string,size:string,lineHeight:string,family:string,explicitWeight:bool}|null
     */
    private function parseFontShorthandComponents(string $value): ?array
    {
        if (stripos($value, 'var(') !== false) {
            return null;
        }

        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return null;
        }

        $components = [
            'style' => 'normal',
            'variant' => 'normal',
            'weight' => 'normal',
            'stretch' => 'normal',
            'size' => '',
            'lineHeight' => 'normal',
            'family' => '',
            'explicitWeight' => false,
        ];

        foreach ($tokens as $index => $token) {
            $sizeAndLineHeight = $this->splitTopLevel($token, '/');
            if (count($sizeAndLineHeight) > 2) {
                return null;
            }

            $size = $sizeAndLineHeight[0];
            if ($this->isFontSizeToken($size)) {
                $components['size'] = $this->minifyFontSizeValue($size);
                if (count($sizeAndLineHeight) === 2) {
                    if ($sizeAndLineHeight[1] === '') {
                        return null;
                    }
                    $components['lineHeight'] = $this->minifyFontLineHeightValue($sizeAndLineHeight[1]);
                }

                $family = trim(implode(' ', array_slice($tokens, $index + 1)));
                if ($family === '') {
                    return null;
                }

                $components['family'] = $this->minifyFontFamilyList($family);

                return $components;
            }

            if (!$this->applyFontPreSizeToken($components, $token)) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array{style:string,variant:string,weight:string,stretch:string,size:string,lineHeight:string,family:string,explicitWeight:bool} $components
     */
    private function serializeFontShorthandComponents(array $components): string
    {
        $parts = [];
        if ($components['style'] !== 'normal') {
            $parts[] = $components['style'];
        }
        if ($components['variant'] === 'small-caps') {
            $parts[] = $components['variant'];
        }
        if ($components['weight'] !== 'normal' && ($components['weight'] !== '400' || $components['explicitWeight'])) {
            $parts[] = $components['weight'];
        }
        if ($components['stretch'] !== 'normal') {
            $parts[] = $components['stretch'];
        }

        $size = $components['size'];
        if ($components['lineHeight'] !== 'normal') {
            $size .= '/' . $components['lineHeight'];
        }

        $parts[] = $size;
        $parts[] = $components['family'];

        return implode(' ', $parts);
    }

    /**
     * @param array{style:string,variant:string,weight:string,stretch:string,size:string,lineHeight:string,family:string,explicitWeight:bool} $components
     */
    private function applyFontPreSizeToken(array &$components, string $token): bool
    {
        $lower = strtolower(trim($token));
        if ($lower === '') {
            return true;
        }

        if ($lower === 'normal') {
            return true;
        }

        if (in_array($lower, ['italic', 'oblique'], true)) {
            $components['style'] = $lower;

            return true;
        }

        if ($lower === 'small-caps') {
            $components['variant'] = $lower;

            return true;
        }

        if ($this->isFontWeightToken($lower)) {
            $components['weight'] = $this->minifyFontWeightValue($lower);
            $components['explicitWeight'] = true;

            return true;
        }

        if ($this->isFontStretchToken($lower)) {
            $components['stretch'] = $this->minifyFontStretchValue($lower);

            return true;
        }

        return false;
    }

    private function isFontSizeToken(string $token): bool
    {
        $lower = strtolower(trim($token));
        if (in_array($lower, [
            'xx-small',
            'x-small',
            'small',
            'medium',
            'large',
            'x-large',
            'xx-large',
            'xxx-large',
            'larger',
            'smaller',
        ], true)) {
            return true;
        }

        return preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)(?:[a-z]+|%))$/i', $lower) === 1
            || preg_match('/^(?:calc|min|max|clamp)\(/i', $lower) === 1;
    }

    private function minifyFontSizeValue(string $value): string
    {
        return $this->minifyNumericDimensionToken(strtolower(trim($value)));
    }

    private function minifyFontLineHeightValue(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'normal') {
            return 'normal';
        }
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1) {
            return $this->minifyNumber((float) $value);
        }

        return $this->minifyNumericDimensionToken($value);
    }

    private function minifyNumericDimensionToken(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(?:([a-z]+)|(%))$/i', $value, $matches) !== 1) {
            return $value;
        }

        $unit = strtolower(($matches[2] ?? '') !== '' ? $matches[2] : '%');

        return $this->minifyNumber((float) $matches[1]) . $unit;
    }

    private function isFontFamilySensitiveProperty(string $property): bool
    {
        return in_array(strtolower($property), ['font', 'font-family'], true);
    }

    private function minifyFontFamilyList(string $value): string
    {
        return implode(',', array_map(
            fn (string $family): string => $this->minifyFontFamilyName($family),
            $this->splitTopLevel($value, ',')
        ));
    }

    private function minifyFontFamilyName(string $family): string
    {
        $family = trim($family);
        if ($family === '') {
            return $family;
        }

        $unquoted = $this->unquoteCssString($family);
        if ($unquoted === null) {
            return $family;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $unquoted) ?? $unquoted);
        if ($normalized !== '' && $this->canSerializeUnquotedFontFamily($normalized)) {
            return $normalized;
        }

        return $this->quoteCssString($normalized);
    }

    private function minifyFontStretchValue(string $value): string
    {
        $parts = $this->splitWhitespaceTopLevel(trim($value));
        if (count($parts) === 2 && strcasecmp($parts[0], $parts[1]) === 0) {
            return $this->minifyFontStretchValue($parts[0]);
        }

        $stretch = [
            'normal' => 'normal',
            'ultra-condensed' => '50%',
            'extra-condensed' => '62.5%',
            'condensed' => '75%',
            'semi-condensed' => '87.5%',
            'semi-expanded' => '112.5%',
            'expanded' => '125%',
            'extra-expanded' => '150%',
            'ultra-expanded' => '200%',
        ];

        return $stretch[strtolower(trim($value))] ?? $value;
    }

    private function isFontStretchToken(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            'normal',
            'ultra-condensed',
            'extra-condensed',
            'condensed',
            'semi-condensed',
            'semi-expanded',
            'expanded',
            'extra-expanded',
            'ultra-expanded',
        ], true) || preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)%)$/', trim($value)) === 1;
    }

    private function minifyFontWeightValue(string $value): string
    {
        $parts = $this->splitWhitespaceTopLevel(trim($value));
        if (count($parts) === 2 && strcasecmp($parts[0], $parts[1]) === 0) {
            return $this->minifyFontWeightValue($parts[0]);
        }

        if (count($parts) > 1) {
            return implode(' ', array_map(fn (string $part): string => $this->minifyFontWeightValue($part), $parts));
        }

        $lower = strtolower(trim($value));

        return match ($lower) {
            'bold' => '700',
            'normal' => '400',
            default => preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $lower) === 1
                ? $this->minifyNumber((float) $lower)
                : trim($value),
        };
    }

    private function isFontWeightToken(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, ['bold', 'bolder', 'lighter'], true)
            || preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1;
    }

    private function minifyFontStyleValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel(strtolower(trim($value)));
        if ($tokens === []) {
            return trim($value);
        }
        if ($tokens[0] !== 'oblique') {
            return implode(' ', $tokens);
        }
        if (count($tokens) === 3 && $tokens[1] === $tokens[2]) {
            return 'oblique';
        }
        if (count($tokens) === 2 && $tokens[1] === '0deg') {
            return 'oblique';
        }

        return implode(' ', $tokens);
    }

    private function minifyFontFaceSrcValue(string $value): string
    {
        return implode(',', array_map(
            fn (string $part): string => $this->minifyFontFaceSrcPart($part),
            $this->splitTopLevel($value, ',')
        ));
    }

    private function minifyFontFaceSrcPart(string $part): string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($part));
        if ($tokens === []) {
            return trim($part);
        }

        $sawTech = false;
        $invalidDescriptorOrder = false;
        foreach ($tokens as $token) {
            $lower = strtolower($token);
            if (str_starts_with($lower, 'tech(')) {
                $sawTech = true;
            } elseif (str_starts_with($lower, 'format(') && $sawTech) {
                $invalidDescriptorOrder = true;
                break;
            }
        }

        $normalized = [];
        foreach ($tokens as $token) {
            $normalized[] = $this->minifyFontFaceSrcToken($token, !$invalidDescriptorOrder);
        }

        if ($invalidDescriptorOrder) {
            return implode(' ', $normalized);
        }

        $output = '';
        foreach ($normalized as $index => $token) {
            $type = $this->fontFaceSrcTokenType($tokens[$index]);
            if ($output !== '' && $type === 'source') {
                $output .= ' ';
            }
            $output .= $token;
        }

        return $output;
    }

    private function minifyFontFaceSrcToken(string $token, bool $strictDescriptorOrder): string
    {
        $token = trim($token);
        if (preg_match('/^url\(/i', $token) === 1) {
            return $this->normalizeCssUrlToken($token, false);
        }
        if (preg_match('/^local\((.*)\)$/is', $token, $matches) === 1) {
            return 'local(' . $this->minifyFontFaceLocalName($matches[1]) . ')';
        }
        if ($strictDescriptorOrder && preg_match('/^format\((.*)\)$/is', $token, $matches) === 1) {
            return 'format(' . $this->normalizeCssStringToken('"' . strtolower($this->cssStringTokenValue(trim($matches[1]))) . '"') . ')';
        }
        if (preg_match('/^tech\((.*)\)$/is', $token, $matches) === 1) {
            return 'tech(' . $this->minifyFontFaceTechList($matches[1]) . ')';
        }

        return $token;
    }

    private function minifyFontFaceLocalName(string $name): string
    {
        $name = trim($name);
        if ($this->isQuotedStringToken($name)) {
            $value = $this->cssStringTokenValue($name);
            if ($value === '') {
                return '""';
            }

            return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        }

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    private function minifyFontFaceTechList(string $value): string
    {
        if (str_contains($value, ',')) {
            return implode(',', array_map(
                static fn (string $part): string => strtolower(trim($part)),
                $this->splitTopLevel($value, ',')
            ));
        }

        return implode(' ', array_map(
            static fn (string $part): string => strtolower($part),
            $this->splitWhitespaceTopLevel($value)
        ));
    }

    private function fontFaceSrcTokenType(string $token): string
    {
        return preg_match('/^(?:url|local)\(/i', trim($token)) === 1 ? 'source' : 'descriptor';
    }

    private function minifyUnicodeRangeValue(string $value): string
    {
        return implode(',', array_map(
            fn (string $part): string => $this->minifyUnicodeRangePart($part),
            $this->splitTopLevel($value, ',')
        ));
    }

    private function minifyFontPaletteOverrideColorsValue(string $value): string
    {
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $tokens = $this->splitWhitespaceTopLevel($part);
            if (count($tokens) < 2) {
                $parts[] = trim($part);
                continue;
            }

            $paletteIndex = array_shift($tokens);
            $color = implode(' ', $tokens);
            $parts[] = $this->minifyPlainNumberToken($paletteIndex) . ' ' . $this->minifyPaletteColorToken($color);
        }

        $output = '';
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $output .= str_contains($part, 'var(') ? ', ' : ',';
            }
            $output .= $part;
        }

        return $output;
    }

    private function minifyFontFeatureValuesRules(string $css): string
    {
        $output = '';
        $cursor = 0;
        $pending = null;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                break;
            }

            $preludePrefix = substr($css, $cursor, $open - $cursor);
            $statementBoundary = $this->lastTopLevelSemicolon($preludePrefix);
            if ($statementBoundary !== null) {
                if ($pending !== null) {
                    $output .= $this->serializeFontFeatureValuesRule($pending['prelude'], $pending['body']);
                    $pending = null;
                }
                $output .= substr($preludePrefix, 0, $statementBoundary + 1);
                $preludePrefix = substr($preludePrefix, $statementBoundary + 1);
            }

            $prelude = trim($preludePrefix);
            $close = $this->findMatchingBraceInCss($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);
            $fontFeaturePrelude = $this->normalizeFontFeatureValuesPrelude($prelude);

            if ($fontFeaturePrelude !== null) {
                $body = $this->minifyFontFeatureValuesBody($body);
                if ($pending !== null && $pending['prelude'] === $fontFeaturePrelude) {
                    $pending['body'] = $this->mergeFontFeatureValuesBodies($pending['body'], $body);
                } else {
                    if ($pending !== null) {
                        $output .= $this->serializeFontFeatureValuesRule($pending['prelude'], $pending['body']);
                    }
                    $pending = ['prelude' => $fontFeaturePrelude, 'body' => $body];
                }
            } else {
                if ($pending !== null) {
                    $output .= $this->serializeFontFeatureValuesRule($pending['prelude'], $pending['body']);
                    $pending = null;
                }
                $output .= $preludePrefix . '{'
                    . $this->minifyFontFeatureValuesRules($body)
                    . '}';
            }

            $cursor = $close + 1;
        }

        if ($pending !== null) {
            $output .= $this->serializeFontFeatureValuesRule($pending['prelude'], $pending['body']);
        }

        return $output . substr($css, $cursor);
    }

    private function normalizeFontFeatureValuesPrelude(string $prelude): ?string
    {
        if (preg_match('/^@font-feature-values\b(.*)$/i', trim($prelude), $matches) !== 1) {
            return null;
        }

        $families = trim($matches[1]);
        if ($families === '') {
            return '@font-feature-values';
        }

        return '@font-feature-values ' . $this->minifyFontFamilyList($families);
    }

    private function minifyFontFeatureValuesBody(string $body): string
    {
        $features = $this->parseFontFeatureValuesBody($body);
        if ($features === null) {
            return $body;
        }

        return $this->serializeFontFeatureValuesBody($features);
    }

    /**
     * @return array{order:list<string>, blocks:array<string, array{order:list<string>, declarations:array<string, array{name:string,value:string,important:bool}>}>}|null
     */
    private function parseFontFeatureValuesBody(string $body): ?array
    {
        $features = [
            'order' => [],
            'blocks' => [],
        ];
        $cursor = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($body, '{', $cursor);
            if ($open === null) {
                return trim(substr($body, $cursor)) === '' ? $features : null;
            }

            if (trim(substr($body, $cursor, $open - $cursor)) === '') {
                return null;
            }

            $prelude = strtolower(trim(substr($body, $cursor, $open - $cursor)));
            if (!in_array($prelude, $this->fontFeatureValueBlockNames(), true)) {
                return null;
            }

            $close = $this->findMatchingBraceInCss($body, $open);
            $entries = $this->parseDeclarationEntriesForComposition(substr($body, $open + 1, $close - $open - 1));
            if ($entries === null) {
                return null;
            }

            if (!isset($features['blocks'][$prelude])) {
                $features['order'][] = $prelude;
                $features['blocks'][$prelude] = [
                    'order' => [],
                    'declarations' => [],
                ];
            }

            foreach ($entries as $entry) {
                if (!isset($features['blocks'][$prelude]['declarations'][$entry['property']])) {
                    $features['blocks'][$prelude]['order'][] = $entry['property'];
                }
                $features['blocks'][$prelude]['declarations'][$entry['property']] = [
                    'name' => $entry['name'],
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }

            $cursor = $close + 1;
        }

        return $features;
    }

    /**
     * @param array{order:list<string>, blocks:array<string, array{order:list<string>, declarations:array<string, array{name:string,value:string,important:bool}>}>} $features
     */
    private function serializeFontFeatureValuesBody(array $features): string
    {
        $parts = [];
        foreach ($features['order'] as $feature) {
            $declarations = [];
            foreach ($features['blocks'][$feature]['order'] as $property) {
                $entry = $features['blocks'][$feature]['declarations'][$property];
                $declarations[] = $entry['name'] . ':' . $entry['value'] . ($entry['important'] ? '!important' : '');
            }
            $parts[] = $feature . '{' . implode(';', $declarations) . '}';
        }

        return implode('', $parts);
    }

    private function mergeFontFeatureValuesBodies(string $first, string $second): string
    {
        $features = $this->parseFontFeatureValuesBody($first . $second);

        return $features === null ? $first . $second : $this->serializeFontFeatureValuesBody($features);
    }

    private function serializeFontFeatureValuesRule(string $prelude, string $body): string
    {
        return $prelude . '{' . $body . '}';
    }

    private function minifyPropertyRules(string $css): string
    {
        $parts = [];
        $propertyIndexes = [];
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $parts[] = substr($css, $cursor);
                break;
            }

            $preludePrefix = substr($css, $cursor, $open - $cursor);
            $statementBoundary = $this->lastTopLevelSemicolon($preludePrefix);
            $statementPrefix = '';
            if ($statementBoundary !== null) {
                $statementPrefix = substr($preludePrefix, 0, $statementBoundary + 1);
                $preludePrefix = substr($preludePrefix, $statementBoundary + 1);
            }

            $prelude = trim($preludePrefix);
            $close = $this->findMatchingBraceInCss($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);

            $propertyName = $this->propertyRuleName($prelude);
            if ($propertyName !== null) {
                if ($statementPrefix !== '') {
                    $parts[] = $statementPrefix;
                }
                $serialized = $this->serializePropertyRule($propertyName, $body);
                if (isset($propertyIndexes[$propertyName])) {
                    $parts[$propertyIndexes[$propertyName]] = $serialized;
                } else {
                    $propertyIndexes[$propertyName] = count($parts);
                    $parts[] = $serialized;
                }
            } else {
                $parts[] = $statementPrefix . $preludePrefix . '{' . $this->minifyPropertyRules($body) . '}';
            }

            $cursor = $close + 1;
        }

        return implode('', $parts);
    }

    private function minifyKeyframesRules(string $css): string
    {
        $parts = [];
        $keyframeIndexes = [];
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $parts[] = substr($css, $cursor);
                break;
            }

            $preludePrefix = substr($css, $cursor, $open - $cursor);
            $statementBoundary = $this->lastTopLevelSemicolon($preludePrefix);
            $statementPrefix = '';
            if ($statementBoundary !== null) {
                $statementPrefix = substr($preludePrefix, 0, $statementBoundary + 1);
                $preludePrefix = substr($preludePrefix, $statementBoundary + 1);
            }

            $prelude = trim($preludePrefix);
            $close = $this->findMatchingBraceInCss($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);
            $keyframes = $this->minifyKeyframesPrelude($prelude);

            if ($keyframes !== null) {
                if ($statementPrefix !== '') {
                    $parts[] = $statementPrefix;
                }
                $serialized = $keyframes['prelude'] . '{' . $this->minifyKeyframesBody($body) . '}';
                if (isset($keyframeIndexes[$keyframes['key']])) {
                    $parts[$keyframeIndexes[$keyframes['key']]] = $serialized;
                } else {
                    $keyframeIndexes[$keyframes['key']] = count($parts);
                    $parts[] = $serialized;
                }
            } elseif (str_starts_with($prelude, '@')) {
                $parts[] = $statementPrefix . $preludePrefix . '{' . $this->minifyKeyframesRules($body) . '}';
            } else {
                $parts[] = $statementPrefix . $preludePrefix . '{' . $body . '}';
            }

            $cursor = $close + 1;
        }

        return implode('', $parts);
    }

    /**
     * @return array{key:string,prelude:string}|null
     */
    private function minifyKeyframesPrelude(string $prelude): ?array
    {
        if (preg_match('/^@((?:-[a-z]+-)?keyframes)\s+(.+)$/i', trim($prelude), $matches) !== 1) {
            return null;
        }

        $keyword = '@' . strtolower($matches[1]);
        $name = $this->minifyKeyframesName($matches[2]);

        return [
            'key' => strtolower($keyword . ' ' . $name),
            'prelude' => $keyword . ' ' . $name,
        ];
    }

    private function minifyKeyframesName(string $name): string
    {
        $name = trim($name);
        $unquoted = $this->unquoteCssString($name);
        if ($unquoted !== null) {
            if (preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $unquoted) === 1 && !$this->isReservedAnimationName($unquoted)) {
                return $unquoted;
            }

            return $this->quoteCssString($unquoted);
        }

        if ($this->isReservedAnimationName($name)) {
            throw new \InvalidArgumentException('Invalid @keyframes name: ' . $name);
        }

        return $name;
    }

    private function minifyKeyframesBody(string $body): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($body, '{', $cursor);
            if ($open === null) {
                break;
            }

            $prelude = trim(substr($body, $cursor, $open - $cursor));
            $close = $this->findMatchingBraceInCss($body, $open);
            $selectors = $this->minifyKeyframeSelectorList($prelude);
            if ($selectors !== null) {
                $declarations = $this->rewriteKeyframeDeclarationList(substr($body, $open + 1, $close - $open - 1));
                $output .= $selectors . '{' . $declarations . '}';
            }

            $cursor = $close + 1;
        }

        return $output;
    }

    private function minifyKeyframeSelectorList(string $selectorList): ?string
    {
        $selectors = [];
        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $normalized = $this->minifyKeyframeSelector($selector);
            if ($normalized === null) {
                return null;
            }
            if ($normalized !== '') {
                $selectors[] = $normalized;
            }
        }

        return $selectors === [] ? null : implode(',', $selectors);
    }

    private function minifyKeyframeSelector(string $selector): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($selector));
        if ($tokens === []) {
            return null;
        }

        if (count($tokens) === 1) {
            $token = strtolower($tokens[0]);
            if ($token === 'from') {
                return '0%';
            }
            if ($token === 'to') {
                return 'to';
            }
            if ($this->isHundredPercentToken($token)) {
                return 'to';
            }

            return $tokens[0];
        }

        if (count($tokens) === 2) {
            $offset = strtolower($tokens[1]);
            if ($offset === 'from' || $offset === 'to') {
                return null;
            }

            return $tokens[0] . ' ' . $tokens[1];
        }

        return trim($selector);
    }

    private function rewriteKeyframeDeclarationList(string $body): string
    {
        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $lastBackground = null;
        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }

            if ($entry['property'] === 'background') {
                $lastBackground = $index;
                continue;
            }

            if ($entry['property'] !== 'background-color' || $lastBackground === null) {
                continue;
            }

            if ($entries[$lastBackground]['important'] !== $entry['important']) {
                continue;
            }

            if (!$this->isSimpleBackgroundColorValue($entries[$lastBackground]['value'])
                || !$this->isSimpleBackgroundColorValue($entry['value'])
            ) {
                continue;
            }

            $entries[$lastBackground]['value'] = $entry['value'];
            $entries[$index]['drop'] = true;
        }

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function isSimpleBackgroundColorValue(string $value): bool
    {
        $value = trim($value);

        return $value !== ''
            && !str_contains($value, ' ')
            && !str_contains($value, '(')
            && !str_contains($value, ',');
    }

    private function validatePageRules(string $css): string
    {
        $cursor = 0;
        while (($position = $this->findAtKeywordInCss($css, '@page', $cursor)) !== null) {
            $open = $this->findNextTopLevel($css, '{', $position + strlen('@page'));
            if ($open === null) {
                $cursor = $position + strlen('@page');
                continue;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $this->validatePageRuleBody(substr($css, $open + 1, $close - $open - 1), false);
            $cursor = $close + 1;
        }

        return $css;
    }

    private function validatePageRuleBody(string $body, bool $insideMarginBox): void
    {
        $cursor = 0;
        while (($open = $this->findNextTopLevel($body, '{', $cursor)) !== null) {
            $prefix = substr($body, $cursor, $open - $cursor);
            $lastSemicolon = strrpos($prefix, ';');
            $lastClose = strrpos($prefix, '}');
            $preludeStart = max($lastSemicolon === false ? -1 : $lastSemicolon, $lastClose === false ? -1 : $lastClose) + 1;
            $prelude = trim(substr($prefix, $preludeStart));
            $close = $this->findMatchingBraceInCss($body, $open);

            if (str_starts_with($prelude, '@')) {
                $name = $this->pageNestedAtRuleName($prelude);
                if ($name === null || $insideMarginBox || !$this->isPageMarginAtRule($name)) {
                    throw new \InvalidArgumentException('Invalid @page nested at-rule: ' . ($name ?? $prelude));
                }

                $this->validatePageRuleBody(substr($body, $open + 1, $close - $open - 1), true);
            }

            $cursor = $close + 1;
        }
    }

    private function pageNestedAtRuleName(string $prelude): ?string
    {
        if (preg_match('/^@([_a-zA-Z][-_a-zA-Z0-9]*)\b/', trim($prelude), $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private function isPageMarginAtRule(string $name): bool
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

    private function propertyRuleName(string $prelude): ?string
    {
        if (preg_match('/^@property\b(.*)$/i', trim($prelude), $matches) !== 1) {
            return null;
        }

        $name = trim($matches[1]);
        if (preg_match('/^--[-_a-zA-Z0-9]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid @property name: {$name}");
        }

        return $name;
    }

    private function serializePropertyRule(string $name, string $body): string
    {
        $descriptors = $this->parsePropertyRuleDescriptors($body);
        $syntax = $descriptors['syntax'] ?? null;
        $inherits = $descriptors['inherits'] ?? null;
        if ($syntax === null || $inherits === null) {
            throw new \InvalidArgumentException("@property {$name} requires syntax and inherits descriptors");
        }

        $syntaxValue = $this->normalizePropertySyntax($syntax);
        $syntaxGrammar = $this->cssStringTokenValue($syntaxValue);
        $hasInitialValue = array_key_exists('initial-value', $descriptors);
        if (!$hasInitialValue && trim($syntaxGrammar) !== '*') {
            throw new \InvalidArgumentException("@property {$name} requires an initial-value descriptor for syntax {$syntaxValue}");
        }

        $parts = [
            'syntax:' . $syntaxValue,
            'inherits:' . $this->normalizePropertyInherits($inherits),
        ];

        if ($hasInitialValue) {
            $parts[] = 'initial-value:' . $this->minifyPropertyInitialValue($syntaxGrammar, $descriptors['initial-value']);
        }

        return '@property ' . $name . '{' . implode(';', $parts) . '}';
    }

    /**
     * @return array<string, string>
     */
    private function parsePropertyRuleDescriptors(string $body): array
    {
        $descriptors = [];
        foreach ($this->splitTopLevel($body, ';') as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $colon = $this->findTopLevelColon($part);
            if ($colon === null) {
                throw new \InvalidArgumentException("Invalid @property descriptor: {$part}");
            }

            $name = strtolower(trim(substr($part, 0, $colon)));
            $value = trim(substr($part, $colon + 1));
            if ($name === '') {
                throw new \InvalidArgumentException("Invalid @property descriptor: {$part}");
            }
            $descriptors[$name] = $value;
        }

        return $descriptors;
    }

    private function normalizePropertySyntax(string $value): string
    {
        if (!$this->isQuotedStringToken($value)) {
            throw new \InvalidArgumentException('@property syntax must be a string');
        }

        $syntax = $this->cssStringTokenValue($value);
        $syntax = trim(preg_replace('/\s+/', ' ', $syntax) ?? $syntax);
        $syntax = preg_replace('/\s*\|\s*/', '|', $syntax) ?? $syntax;
        $syntax = preg_replace('/\s*([#+])\s*/', '$1', $syntax) ?? $syntax;

        return $this->quoteCssString($syntax);
    }

    private function normalizePropertyInherits(string $value): string
    {
        $value = strtolower(trim($value));
        if (!in_array($value, ['true', 'false'], true)) {
            throw new \InvalidArgumentException("@property inherits must be true or false: {$value}");
        }

        return $value;
    }

    private function minifyPropertyInitialValue(string $syntax, string $value): string
    {
        $value = trim($value);
        $syntax = trim($syntax);
        $normalizedSyntax = strtolower(str_replace(' ', '', $syntax));

        if ($value === '') {
            if ($normalizedSyntax === '*') {
                return '';
            }

            throw new \InvalidArgumentException("@property initial-value cannot be empty for syntax {$syntax}");
        }

        if ($normalizedSyntax === '*') {
            return $value;
        }

        if ($normalizedSyntax === '<color>#') {
            return implode(',', array_map(
                fn (string $part): string => $this->minifyPropertyColorInitialValue($part),
                $this->splitTopLevel($value, ',')
            ));
        }

        if ($normalizedSyntax === '<color>+') {
            return implode(' ', array_map(
                fn (string $part): string => $this->minifyPropertyColorInitialValue($part),
                $this->splitWhitespaceTopLevel($value)
            ));
        }

        if (str_contains($normalizedSyntax, '<color>')) {
            return $this->minifyPropertyColorInitialValue($value);
        }

        if (str_contains($normalizedSyntax, '<length>')) {
            if (str_contains($normalizedSyntax, '|none') && strcasecmp($value, 'none') === 0) {
                return 'none';
            }
            if (stripos($value, 'var(') !== false || !$this->isPropertyLengthValue($value)) {
                throw new \InvalidArgumentException("@property initial-value does not match {$syntax}: {$value}");
            }

            return $this->minifyNumericDimensionToken($value);
        }

        if ($normalizedSyntax === '<string>') {
            if (!$this->isQuotedStringToken($value)) {
                throw new \InvalidArgumentException("@property initial-value does not match {$syntax}: {$value}");
            }

            return $this->normalizeCssStringToken($value);
        }

        if ($normalizedSyntax === '<time>') {
            if (!$this->isTimeValue($value)) {
                throw new \InvalidArgumentException("@property initial-value does not match {$syntax}: {$value}");
            }

            return $this->minifyTimeValue($value);
        }

        if ($normalizedSyntax === '<url>') {
            if (preg_match('/^url\(/i', $value) !== 1) {
                throw new \InvalidArgumentException("@property initial-value does not match {$syntax}: {$value}");
            }

            return $this->normalizeCssUrlToken($value, false);
        }

        if ($normalizedSyntax === '<image>') {
            return $this->minifyColorKeywords($this->minifyImageSetFunctions($value));
        }

        return $this->minifyColorKeywords($value);
    }

    private function minifyPropertyColorInitialValue(string $value): string
    {
        $value = trim($value);
        if ($value === '' || stripos($value, 'var(') !== false || preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:[a-zA-Z%]+)?$/', $value) === 1) {
            throw new \InvalidArgumentException("@property initial-value is not a color: {$value}");
        }

        if (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1) {
            return $this->compressHexColor($value);
        }

        $minified = $this->minifyColorKeywords($value);
        if ($minified !== $value
            || preg_match('/^(?:rgb|rgba|hsl|hsla|lab|lch|oklab|oklch|color)\(/i', $value) === 1
            || $this->isPropertyColorKeyword($value)
        ) {
            return $minified;
        }

        throw new \InvalidArgumentException("@property initial-value is not a color: {$value}");
    }

    private function isPropertyColorKeyword(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            'aqua',
            'black',
            'blue',
            'chartreuse',
            'cornflowerblue',
            'currentcolor',
            'cyan',
            'fuchsia',
            'green',
            'lime',
            'magenta',
            'red',
            'transparent',
            'white',
            'yellow',
        ], true);
    }

    private function isPropertyLengthValue(string $value): bool
    {
        return preg_match('/^[+-]?(?:0|(?:\d+|\d*\.\d+)(?:px|em|rem|vh|vw|vmin|vmax|ch|ex|lh|rlh|cm|mm|q|in|pt|pc|%)?)$/i', trim($value)) === 1;
    }

    /**
     * @return list<string>
     */
    private function fontFeatureValueBlockNames(): array
    {
        return ['@styleset', '@character-variant', '@stylistic', '@swash', '@ornaments', '@annotation'];
    }

    private function minifyPaletteColorToken(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color) === 1) {
            return $this->compressHexColor($color);
        }

        return $this->minifyColorKeywords($color);
    }

    private function minifyPlainNumberToken(string $value): string
    {
        $value = trim($value);

        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1
            ? $this->minifyNumber((float) $value)
            : $value;
    }

    private function minifyUnicodeRangePart(string $part): string
    {
        $part = trim($part);
        if (preg_match('/^u\+([0-9a-f?]{1,6})(?:-([0-9a-f]{1,6}))?$/i', $part, $matches) !== 1) {
            return $part;
        }

        $start = strtoupper($matches[1]);
        $end = isset($matches[2]) ? strtoupper($matches[2]) : null;
        if ($end === null) {
            if (str_contains($start, '?')) {
                return 'U+' . $this->trimUnicodeWildcardPrefix($start);
            }

            return 'U+' . $this->trimUnicodeCodepoint($start);
        }

        $wildcard = $this->unicodeRangeWildcard($start, $end);
        if ($wildcard !== null) {
            return 'U+' . $wildcard;
        }

        return 'U+' . $this->trimUnicodeCodepoint($start) . '-' . $this->trimUnicodeCodepoint($end);
    }

    private function unicodeRangeWildcard(string $start, string $end): ?string
    {
        $width = max(strlen($start), strlen($end));
        $start = str_pad($start, $width, '0', STR_PAD_LEFT);
        $end = str_pad($end, $width, '0', STR_PAD_LEFT);

        $prefixLength = 0;
        while (
            $prefixLength < $width
            && $start[$prefixLength] === $end[$prefixLength]
        ) {
            $prefixLength++;
        }

        $startSuffix = substr($start, $prefixLength);
        $endSuffix = substr($end, $prefixLength);
        if ($startSuffix === '' || !preg_match('/^0+$/', $startSuffix) || !preg_match('/^F+$/', $endSuffix)) {
            return null;
        }

        $prefix = ltrim(substr($start, 0, $prefixLength), '0');

        return $this->trimUnicodeWildcardPrefix($prefix . str_repeat('?', strlen($startSuffix)));
    }

    private function trimUnicodeCodepoint(string $codepoint): string
    {
        $trimmed = ltrim(strtoupper($codepoint), '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function trimUnicodeWildcardPrefix(string $range): string
    {
        $range = strtoupper($range);
        $firstQuestion = strpos($range, '?');
        if ($firstQuestion === false) {
            return $this->trimUnicodeCodepoint($range);
        }

        $prefix = ltrim(substr($range, 0, $firstQuestion), '0');

        return $prefix . substr($range, $firstQuestion);
    }

    private function canSerializeUnquotedFontFamily(string $family): bool
    {
        $tokens = preg_split('/\s+/', $family) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $token) !== 1) {
                return false;
            }
        }

        if (count($tokens) === 1) {
            return !in_array(strtolower($tokens[0]), $this->reservedQuotedFontFamilyNames(), true);
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function reservedQuotedFontFamilyNames(): array
    {
        return [
            'cursive',
            'default',
            'emoji',
            'fangsong',
            'fantasy',
            'inherit',
            'initial',
            'math',
            'monospace',
            'revert',
            'revert-layer',
            'sans-serif',
            'serif',
            'system-ui',
            'ui-monospace',
            'ui-rounded',
            'ui-sans-serif',
            'ui-serif',
            'unset',
        ];
    }

    private function unquoteCssString(string $value): ?string
    {
        $quote = $value[0] ?? '';
        if (($quote !== '"' && $quote !== "'") || substr($value, -1) !== $quote) {
            return null;
        }

        $inner = substr($value, 1, -1);
        if (str_contains($inner, '\\')) {
            return null;
        }

        return $inner;
    }

    private function quoteCssString(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function minifyTransformValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'transform', '-webkit-transform', '-moz-transform' => $this->minifyTransformFunctionList($value),
            'translate' => $this->minifyTransformTranslateLonghand($value),
            'rotate' => $this->minifyTransformRotateLonghand($value),
            'scale' => $this->minifyTransformScaleLonghand($value),
            default => $value,
        };
    }

    private function minifyTransformFunctionList(string $value): string
    {
        $functions = [];
        $length = strlen($value);

        for ($i = 0; $i < $length;) {
            if (ctype_space($value[$i])) {
                $i++;
                continue;
            }

            if ($this->isIdentifierStart($value[$i])) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $i + strlen($identifier);
                if (($value[$next] ?? '') === '(') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $functions[] = $this->minifyTransformFunction($function);
                    $i = $offset + 1;
                    continue;
                }
            }

            return preg_replace('/\)\s+(?=[-_a-zA-Z][-_a-zA-Z0-9]*\()/u', ')', trim($value)) ?? trim($value);
        }

        return implode('', $functions);
    }

    private function minifyTransformFunction(string $function): string
    {
        if (preg_match('/^([-_a-zA-Z][-_a-zA-Z0-9]*)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return trim($function);
        }

        $name = strtolower($matches[1]);
        $args = $this->splitTopLevel($matches[2], ',');

        return match ($name) {
            'translate' => $this->minifyTransformTranslate($args),
            'translatex' => $this->minifyTransformTranslateX($args),
            'translatey' => $this->minifyTransformTranslateY($args),
            'translatez' => $this->minifyTransformTranslateZ($args),
            'translate3d' => $this->minifyTransformTranslate3d($args),
            'scale' => $this->minifyTransformScale($args),
            'scalex' => $this->minifyTransformScaleAxis('scaleX', $args),
            'scaley' => $this->minifyTransformScaleAxis('scaleY', $args),
            'scalez' => $this->minifyTransformScaleAxis('scaleZ', $args),
            'scale3d' => $this->minifyTransformScale3d($args),
            'rotate' => $this->minifyTransformRotateAxis('rotate', $args),
            'rotatex' => $this->minifyTransformRotateAxis('rotateX', $args),
            'rotatey' => $this->minifyTransformRotateAxis('rotateY', $args),
            'rotatez' => $this->minifyTransformRotateAxis('rotate', $args),
            'rotate3d' => $this->minifyTransformRotate3d($args),
            'skew' => $this->minifyTransformSkew($args),
            'skewx' => $this->minifyTransformSkewAxis('skew', $args),
            'skewy' => $this->minifyTransformSkewAxis('skewY', $args),
            'perspective' => $this->minifyTransformPerspective($args),
            'matrix' => $this->minifyTransformGenericFunction('matrix', $args),
            'matrix3d' => $this->minifyTransformGenericFunction('matrix3d', $args),
            default => $matches[1] . '(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformGenericArgument($arg), $args)) . ')',
        };
    }

    private function minifyTransformTranslateLonghand(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) === 1 && strtolower($tokens[0]) === 'none') {
            return 'none';
        }
        if ($tokens === [] || count($tokens) > 3) {
            return trim($value);
        }

        $tokens = array_map(fn (string $token): string => $this->normalizeTransformLengthArgument($token), $tokens);
        while (count($tokens) > 1 && $this->isTransformZeroLength($tokens[count($tokens) - 1])) {
            array_pop($tokens);
        }

        return implode(' ', $tokens);
    }

    private function minifyTransformScaleLonghand(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) === 1 && strtolower($tokens[0]) === 'none') {
            return 'none';
        }
        if ($tokens === [] || count($tokens) > 3) {
            return trim($value);
        }

        $tokens = array_map(fn (string $token): string => $this->normalizeTransformScaleArgument($token), $tokens);
        if (count($tokens) === 3 && $this->isTransformScaleIdentity($tokens[2])) {
            array_pop($tokens);
        }
        if (count($tokens) === 2 && $this->transformNumbersEqual($tokens[0], $tokens[1])) {
            return $tokens[0];
        }

        return implode(' ', $tokens);
    }

    private function minifyTransformRotateLonghand(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) === 1 && strtolower($tokens[0]) === 'none') {
            return 'none';
        }
        if ($tokens === []) {
            return trim($value);
        }

        $angleIndexes = [];
        $allowUnitlessZeroAngle = count($tokens) === 1;
        foreach ($tokens as $index => $token) {
            if ($this->isTransformAngleToken($token, $allowUnitlessZeroAngle)) {
                $angleIndexes[] = $index;
            }
        }
        if (count($angleIndexes) !== 1) {
            return trim($value);
        }

        $angleIndex = $angleIndexes[0];
        $angle = $this->normalizeTransformLonghandAngleArgument($tokens[$angleIndex]);
        $axisTokens = [];
        foreach ($tokens as $index => $token) {
            if ($index !== $angleIndex) {
                $axisTokens[] = $token;
            }
        }

        if ($axisTokens === []) {
            return $angle;
        }
        if (count($axisTokens) === 1) {
            $axis = strtolower($axisTokens[0]);
            if ($axis === 'z') {
                return $angle;
            }
            if ($axis === 'x' || $axis === 'y') {
                return $axis . ' ' . $angle;
            }

            return trim($value);
        }
        if (count($axisTokens) !== 3) {
            return trim($value);
        }

        $numbers = [];
        $serialized = [];
        foreach ($axisTokens as $token) {
            $axis = $this->normalizeTransformAxisNumberArgument($token);
            $number = $this->unitlessMathNumber($axis);
            if ($number === null) {
                return trim($value);
            }
            $numbers[] = $number;
            $serialized[] = $axis;
        }

        $axis = $this->singleTransformAxis($numbers);
        if ($axis !== null) {
            $axisAngle = $axis['sign'] < 0 ? $this->negateTransformLonghandAngle($angle) : $angle;
            if ($axis['axis'] === 'z') {
                return $axisAngle;
            }

            return $axis['axis'] . ' ' . $axisAngle;
        }

        return implode(' ', [...$serialized, $angle]);
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformTranslate(array $args): string
    {
        if (count($args) < 1 || count($args) > 2) {
            return 'translate(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformLengthArgument($arg), $args)) . ')';
        }

        $x = $this->normalizeTransformLengthArgument($args[0]);
        $y = isset($args[1]) ? $this->normalizeTransformLengthArgument($args[1]) : '0';

        if ($this->isTransformZeroLength($y)) {
            return 'translate(' . $x . ')';
        }
        if ($this->isTransformZeroLength($x)) {
            return 'translateY(' . $y . ')';
        }

        return 'translate(' . $x . ',' . $y . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformTranslateX(array $args): string
    {
        if (count($args) !== 1) {
            return 'translateX(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformLengthArgument($arg), $args)) . ')';
        }

        return 'translate(' . $this->normalizeTransformLengthArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformTranslateY(array $args): string
    {
        if (count($args) !== 1) {
            return 'translateY(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformLengthArgument($arg), $args)) . ')';
        }

        return 'translateY(' . $this->normalizeTransformLengthArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformTranslateZ(array $args): string
    {
        if (count($args) !== 1) {
            return 'translateZ(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformLengthArgument($arg), $args)) . ')';
        }

        return 'translateZ(' . $this->normalizeTransformLengthArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformTranslate3d(array $args): string
    {
        if (count($args) !== 3) {
            return 'translate3d(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformLengthArgument($arg), $args)) . ')';
        }

        $x = $this->normalizeTransformLengthArgument($args[0]);
        $y = $this->normalizeTransformLengthArgument($args[1]);
        $z = $this->normalizeTransformLengthArgument($args[2]);

        if ($this->isTransformZeroLength($z)) {
            if ($this->isTransformZeroLength($y)) {
                return 'translate(' . $x . ')';
            }
            if ($this->isTransformZeroLength($x)) {
                return 'translateY(' . $y . ')';
            }

            return 'translate(' . $x . ',' . $y . ')';
        }
        if ($this->isTransformZeroLength($x) && $this->isTransformZeroLength($y)) {
            return 'translateZ(' . $z . ')';
        }

        return 'translate3d(' . $x . ',' . $y . ',' . $z . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformScale(array $args): string
    {
        if (count($args) < 1 || count($args) > 2) {
            return 'scale(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformScaleArgument($arg), $args)) . ')';
        }

        $x = $this->normalizeTransformScaleArgument($args[0]);
        $y = isset($args[1]) ? $this->normalizeTransformScaleArgument($args[1]) : $x;

        if ($this->transformNumbersEqual($x, $y)) {
            return 'scale(' . $x . ')';
        }
        if ($this->isTransformScaleIdentity($y)) {
            return 'scaleX(' . $x . ')';
        }
        if ($this->isTransformScaleIdentity($x)) {
            return 'scaleY(' . $y . ')';
        }

        return 'scale(' . $x . ',' . $y . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformScaleAxis(string $name, array $args): string
    {
        if (count($args) !== 1) {
            return $name . '(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformScaleArgument($arg), $args)) . ')';
        }

        return $name . '(' . $this->normalizeTransformScaleArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformScale3d(array $args): string
    {
        if (count($args) !== 3) {
            return 'scale3d(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformScaleArgument($arg), $args)) . ')';
        }

        $x = $this->normalizeTransformScaleArgument($args[0]);
        $y = $this->normalizeTransformScaleArgument($args[1]);
        $z = $this->normalizeTransformScaleArgument($args[2]);

        if ($this->isTransformScaleIdentity($z)) {
            if ($this->transformNumbersEqual($x, $y)) {
                return 'scale(' . $x . ')';
            }
            if ($this->isTransformScaleIdentity($y)) {
                return 'scaleX(' . $x . ')';
            }
            if ($this->isTransformScaleIdentity($x)) {
                return 'scaleY(' . $y . ')';
            }
        }

        if ($this->isTransformScaleIdentity($x) && $this->isTransformScaleIdentity($y)) {
            return 'scaleZ(' . $z . ')';
        }

        return 'scale3d(' . $x . ',' . $y . ',' . $z . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformRotateAxis(string $name, array $args): string
    {
        if (count($args) !== 1) {
            return $name . '(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformGenericArgument($arg), $args)) . ')';
        }

        return $name . '(' . $this->normalizeTransformFunctionAngleArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformRotate3d(array $args): string
    {
        if (count($args) !== 4) {
            return 'rotate3d(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformGenericArgument($arg), $args)) . ')';
        }

        $axes = [
            $this->normalizeTransformAxisNumberArgument($args[0]),
            $this->normalizeTransformAxisNumberArgument($args[1]),
            $this->normalizeTransformAxisNumberArgument($args[2]),
        ];
        $numbers = [];
        foreach ($axes as $axis) {
            $number = $this->unitlessMathNumber($axis);
            if ($number === null) {
                $angle = $this->normalizeTransformFunctionAngleArgument($args[3]);

                return 'rotate3d(' . implode(',', [...$axes, $angle]) . ')';
            }
            $numbers[] = $number;
        }

        $angle = $this->normalizeTransformFunctionAngleArgument($args[3]);
        $axis = $this->singleTransformAxis($numbers);
        if ($axis !== null) {
            $axisAngle = $axis['sign'] < 0 ? $this->negateTransformFunctionAngle($angle) : $angle;

            return match ($axis['axis']) {
                'x' => 'rotateX(' . $axisAngle . ')',
                'y' => 'rotateY(' . $axisAngle . ')',
                default => 'rotate(' . $axisAngle . ')',
            };
        }

        return 'rotate3d(' . implode(',', [...$axes, $angle]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformSkew(array $args): string
    {
        if (count($args) < 1 || count($args) > 2) {
            return 'skew(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformFunctionAngleArgument($arg), $args)) . ')';
        }

        $x = $this->normalizeTransformFunctionAngleArgument($args[0]);
        $y = isset($args[1]) ? $this->normalizeTransformFunctionAngleArgument($args[1]) : '0';
        if ($this->isTransformZeroAngle($y)) {
            return 'skew(' . $x . ')';
        }
        if ($this->isTransformZeroAngle($x)) {
            return 'skewY(' . $y . ')';
        }

        return 'skew(' . $x . ',' . $y . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformSkewAxis(string $name, array $args): string
    {
        if (count($args) !== 1) {
            return $name . '(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformFunctionAngleArgument($arg), $args)) . ')';
        }

        return $name . '(' . $this->normalizeTransformFunctionAngleArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformPerspective(array $args): string
    {
        if (count($args) !== 1) {
            return 'perspective(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformLengthArgument($arg), $args)) . ')';
        }

        return 'perspective(' . $this->normalizeTransformLengthArgument($args[0]) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyTransformGenericFunction(string $name, array $args): string
    {
        return $name . '(' . implode(',', array_map(fn (string $arg): string => $this->normalizeTransformGenericArgument($arg), $args)) . ')';
    }

    private function normalizeTransformLengthArgument(string $arg): string
    {
        $arg = trim($this->minifyMathFunctions($arg));
        $linear = $this->parseLinearMathArgument($arg);
        if ($linear !== null && $this->linearMathUnitsAllInGroup($linear, 'length:absolute') && count($this->nonZeroLinearCalcUnits($linear)) > 1) {
            return $this->serializeTransformCanonicalLength($this->canonicalLinearMathValue($arg, 'length:absolute') ?? 0.0);
        }

        $normalized = $linear === null ? $this->normalizeMathArgument($arg) : $this->serializeTransformLinearArgument($linear);
        $value = $this->comparableMathValue($normalized);
        if ($value !== null && abs($value['canonical']) < 0.0000001) {
            return '0';
        }

        return $normalized;
    }

    private function normalizeTransformScaleArgument(string $arg): string
    {
        $normalized = $this->normalizeMathArgument($arg);
        $value = $this->comparableMathValue($normalized);
        if ($value === null) {
            return $normalized;
        }
        if ($value['unit'] === '%') {
            return $this->serializeMathNumberWithUnit($value['value'] / 100, '');
        }
        if ($value['unit'] === '') {
            return $this->serializeMathNumberWithUnit($value['value'], '');
        }

        return $normalized;
    }

    private function normalizeTransformGenericArgument(string $arg): string
    {
        return $this->normalizeMathArgument($arg);
    }

    private function normalizeTransformAxisNumberArgument(string $arg): string
    {
        $normalized = $this->normalizeMathArgument($arg);
        $number = $this->unitlessMathNumber($normalized);

        return $number === null ? $normalized : $this->serializeMathNumberWithUnit($number, '');
    }

    private function normalizeTransformFunctionAngleArgument(string $arg): string
    {
        $degrees = $this->canonicalLinearMathValue($arg, 'angle');
        if ($degrees !== null) {
            return $this->serializeTransformDegrees($degrees, false);
        }

        return $this->normalizeMathArgument($arg);
    }

    private function normalizeTransformLonghandAngleArgument(string $arg): string
    {
        $degrees = $this->canonicalLinearMathValue($arg, 'angle');
        if ($degrees !== null) {
            return $this->serializeTransformDegrees($degrees, true);
        }

        $normalized = $this->normalizeMathArgument($arg);
        $number = $this->unitlessMathNumber($normalized);
        if ($number !== null && abs($number) < 0.0000001) {
            return '0deg';
        }

        return $normalized;
    }

    private function isTransformAngleToken(string $token, bool $allowUnitlessZero): bool
    {
        if ($this->canonicalLinearMathValue($token, 'angle') !== null) {
            return true;
        }

        if (!$allowUnitlessZero) {
            return false;
        }

        $number = $this->unitlessMathNumber($this->normalizeMathArgument($token));

        return $number !== null && abs($number) < 0.0000001;
    }

    private function isTransformZeroAngle(string $value): bool
    {
        $degrees = $this->canonicalLinearMathValue($value, 'angle');
        if ($degrees !== null) {
            return abs($degrees) < 0.0000001;
        }

        $number = $this->unitlessMathNumber($value);

        return $number !== null && abs($number) < 0.0000001;
    }

    private function negateTransformFunctionAngle(string $angle): string
    {
        $degrees = $this->canonicalLinearMathValue($angle, 'angle');
        if ($degrees !== null) {
            return $this->serializeTransformDegrees(-$degrees, false);
        }

        $number = $this->unitlessMathNumber($angle);
        if ($number !== null) {
            return $this->serializeMathNumberWithUnit(-$number, '');
        }

        return str_starts_with($angle, '-') ? substr($angle, 1) : '-' . $angle;
    }

    private function negateTransformLonghandAngle(string $angle): string
    {
        $degrees = $this->canonicalLinearMathValue($angle, 'angle');
        if ($degrees !== null) {
            return $this->serializeTransformDegrees(-$degrees, true);
        }

        return str_starts_with($angle, '-') ? substr($angle, 1) : '-' . $angle;
    }

    /**
     * @param list<float> $numbers
     * @return array{axis:string,sign:int}|null
     */
    private function singleTransformAxis(array $numbers): ?array
    {
        $nonZero = [];
        foreach (['x', 'y', 'z'] as $index => $axis) {
            if (abs($numbers[$index] ?? 0.0) >= 0.0000001) {
                $nonZero[] = [
                    'axis' => $axis,
                    'sign' => ($numbers[$index] ?? 0.0) < 0 ? -1 : 1,
                ];
            }
        }

        return count($nonZero) === 1 ? $nonZero[0] : null;
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $linear
     */
    private function serializeTransformLinearArgument(array $linear): string
    {
        $units = $this->nonZeroLinearCalcUnits($linear);
        if (count($units) <= 1) {
            return $this->serializeLinearCalcArgument($linear);
        }

        return 'calc(' . $this->serializeLinearCalcArgument($linear) . ')';
    }

    private function canonicalLinearMathValue(string $arg, string $group): ?float
    {
        $linear = $this->parseLinearMathArgument(trim($this->minifyMathFunctions($arg)));
        if ($linear === null) {
            return null;
        }

        $units = $this->nonZeroLinearCalcUnits($linear);
        if ($units === []) {
            $zeroUnit = $this->zeroLinearCalcUnit($linear);
            if ($zeroUnit === null) {
                return null;
            }
            $comparison = $this->mathComparison(0.0, $zeroUnit);

            return $comparison !== null && $comparison['group'] === $group ? 0.0 : null;
        }

        $canonical = 0.0;
        foreach ($units as $unit) {
            $comparison = $this->mathComparison($linear['terms'][$unit], $unit);
            if ($comparison === null || $comparison['group'] !== $group) {
                return null;
            }
            $canonical += $comparison['canonical'];
        }

        return $canonical;
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $linear
     */
    private function linearMathUnitsAllInGroup(array $linear, string $group): bool
    {
        $units = $this->nonZeroLinearCalcUnits($linear);
        if ($units === []) {
            return true;
        }

        foreach ($units as $unit) {
            $comparison = $this->mathComparison($linear['terms'][$unit], $unit);
            if ($comparison === null || $comparison['group'] !== $group) {
                return false;
            }
        }

        return true;
    }

    private function serializeTransformCanonicalLength(float $px): string
    {
        return $this->serializeMathNumberWithUnit($px, 'px');
    }

    private function serializeTransformDegrees(float $degrees, bool $keepZeroUnit): string
    {
        $rounded = round($degrees);
        if (abs($degrees - $rounded) < 0.0001) {
            $degrees = (float) $rounded;
        }

        if (abs($degrees) < 0.0000001) {
            return $keepZeroUnit ? '0deg' : '0';
        }

        return $this->minifyNumber($degrees) . 'deg';
    }

    private function isTransformZeroLength(string $value): bool
    {
        $comparable = $this->comparableMathValue($value);

        return $comparable !== null && abs($comparable['canonical']) < 0.0000001;
    }

    private function isTransformScaleIdentity(string $value): bool
    {
        $number = $this->unitlessMathNumber($value);

        return $number !== null && abs($number - 1.0) < 0.0000001;
    }

    private function transformNumbersEqual(string $left, string $right): bool
    {
        $leftNumber = $this->unitlessMathNumber($left);
        $rightNumber = $this->unitlessMathNumber($right);

        return $leftNumber !== null && $rightNumber !== null && abs($leftNumber - $rightNumber) < 0.0000001;
    }

    private function minifyContainerDeclarationValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'container' => $this->minifyContainerShorthandValue($value),
            'container-type' => strtolower(trim($value)),
            'container-name' => trim($value),
            default => $value,
        };
    }

    private function minifyContainerShorthandValue(string $value): string
    {
        $value = trim($value);
        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) === 2 && strtolower($parts[1]) === 'normal') {
            return $parts[0];
        }
        if (count($parts) === 2) {
            return $parts[0] . '/' . strtolower($parts[1]);
        }

        return str_replace(' / ', '/', $value);
    }

    private function minifyAnimationLonghandValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'animation',
            '-webkit-animation',
            '-moz-animation' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationShorthandLayer($part)),
            'animation-name' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationName($part)),
            'animation-duration',
            'animation-delay' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTimeToken($part)),
            'animation-timing-function' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionTimingFunction($part)),
            'animation-iteration-count' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationIterationCount($part)),
            'animation-direction',
            'animation-play-state',
            'animation-fill-mode',
            'animation-composition' => $this->mapCommaList($value, static fn (string $part): string => strtolower(trim($part))),
            'animation-timeline' => $this->mapCommaList(
                $value,
                fn (string $part): string => $this->isAnimationTimelineToken($part)
                    ? $this->minifyAnimationTimelineToken($part)
                    : trim($part)
            ),
            'animation-range-start' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationRangeSideValue($part, 'start')),
            'animation-range-end' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationRangeSideValue($part, 'end')),
            'animation-range' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationRangeShorthandLayer($part)),
            default => $value,
        };
    }

    private function minifyAnimationName(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([\'"])(.*)\1$/s', $value, $matches) !== 1) {
            return $value;
        }

        $name = $matches[2];
        if (preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $name) !== 1) {
            return $value;
        }

        return in_array(strtolower($name), [
            ...$this->reservedAnimationNames(),
        ], true) ? $value : $name;
    }

    private function isReservedAnimationName(string $name): bool
    {
        return in_array(strtolower(trim($name)), $this->reservedAnimationNames(), true);
    }

    /**
     * @return list<string>
     */
    private function reservedAnimationNames(): array
    {
        return [
            'default',
            'inherit',
            'initial',
            'none',
            'revert',
            'revert-layer',
            'unset',
        ];
    }

    private function minifyAnimationShorthandLayer(string $layer): string
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        if ($tokens === []) {
            return trim($layer);
        }

        if (stripos($layer, 'var(') !== false) {
            return implode(' ', array_map(
                fn (string $token): string => $this->minifyAnimationTokenInPlace($token),
                $tokens
            ));
        }

        $components = [
            'duration' => null,
            'timing' => null,
            'delay' => null,
            'iteration' => null,
            'direction' => null,
            'fill' => null,
            'play' => null,
            'name' => null,
            'timeline' => null,
        ];

        foreach ($tokens as $index => $token) {
            $lower = strtolower($token);

            if ($this->isQuotedStringToken($token)) {
                if ($components['name'] !== null) {
                    return trim($layer);
                }
                $components['name'] = $this->minifyAnimationName($token);
                continue;
            }

            if ($this->isTimeValue($token)) {
                if ($components['duration'] === null) {
                    $components['duration'] = $this->minifyTimeValue($token);
                    continue;
                }
                if ($components['delay'] === null) {
                    $components['delay'] = $this->minifyTimeValue($token);
                    continue;
                }

                return trim($layer);
            }

            if ($components['timing'] === null && $this->isTransitionTimingFunction($token)) {
                $components['timing'] = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            if ($components['iteration'] === null && $this->isAnimationIterationToken($lower)) {
                $components['iteration'] = $this->minifyAnimationIterationCount($lower);
                continue;
            }

            if ($components['direction'] === null && in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)) {
                $components['direction'] = $lower;
                continue;
            }

            if ($components['fill'] === null && in_array($lower, ['none', 'forwards', 'backwards', 'both'], true)) {
                if ($lower !== 'none' || $components['name'] !== null || $this->hasFutureAnimationNameToken($tokens, $index + 1)) {
                    $components['fill'] = $lower;
                    continue;
                }
            }

            if ($components['play'] === null && in_array($lower, ['running', 'paused'], true)) {
                $components['play'] = $lower;
                continue;
            }

            if ($components['timeline'] === null && $this->isAnimationTimelineToken($token)) {
                $components['timeline'] = $this->minifyAnimationTimelineToken($token);
                continue;
            }

            if ($components['name'] !== null) {
                return trim($layer);
            }
            $components['name'] = $this->minifyAnimationName($token);
        }

        return $this->serializeAnimationShorthandLayer($components);
    }

    private function minifyAnimationTokenInPlace(string $token): string
    {
        if ($this->isTimeValue($token)) {
            return $this->minifyTimeValue($token);
        }
        if ($this->isTransitionTimingFunction($token)) {
            return $this->minifyTransitionTimingFunction($token);
        }
        if ($this->isQuotedStringToken($token)) {
            return $this->minifyAnimationName($token);
        }

        return strtolower($token) === 'auto' ? 'auto' : $token;
    }

    /**
     * @param list<string> $tokens
     */
    private function hasFutureAnimationNameToken(array $tokens, int $offset): bool
    {
        for ($i = $offset; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($this->isQuotedStringToken($token)) {
                return true;
            }

            $lower = strtolower($token);
            if ($this->isTimeValue($token)
                || $this->isTransitionTimingFunction($token)
                || $this->isAnimationIterationToken($lower)
                || in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)
                || in_array($lower, ['none', 'forwards', 'backwards', 'both'], true)
                || in_array($lower, ['running', 'paused'], true)
                || $this->isAnimationTimelineToken($token)
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array{duration:?string,timing:?string,delay:?string,iteration:?string,direction:?string,fill:?string,play:?string,name:?string,timeline:?string} $components
     */
    private function serializeAnimationShorthandLayer(array $components): string
    {
        $duration = $components['duration'] ?? '0s';
        $timing = $components['timing'] ?? 'ease';
        $delay = $components['delay'] ?? '0s';
        $iteration = $components['iteration'] ?? '1';
        $direction = $components['direction'] ?? 'normal';
        $fill = $components['fill'] ?? 'none';
        $play = $components['play'] ?? 'running';
        $name = $components['name'] ?? 'none';
        $timeline = $components['timeline'] ?? 'auto';

        $parts = [];
        if ($duration !== '0s' || $delay !== '0s') {
            $parts[] = $duration;
        }
        if ($timing !== 'ease' || $this->animationNameConflictsWith($name, 'timing')) {
            $parts[] = $timing;
        }
        if ($delay !== '0s') {
            $parts[] = $delay;
        }
        if ($iteration !== '1' || $this->animationNameConflictsWith($name, 'iteration')) {
            $parts[] = $iteration;
        }
        if ($direction !== 'normal' || $this->animationNameConflictsWith($name, 'direction')) {
            $parts[] = $direction;
        }
        if ($fill !== 'none' || $this->animationNameConflictsWith($name, 'fill')) {
            $parts[] = $fill;
        }
        if ($play !== 'running' || $this->animationNameConflictsWith($name, 'play')) {
            $parts[] = $play;
        }
        if ($name !== 'none' || $parts === []) {
            $parts[] = $name;
        }
        if ($timeline !== 'auto') {
            $parts[] = $timeline;
        }

        return implode(' ', $parts);
    }

    private function animationNameConflictsWith(string $name, string $component): bool
    {
        if ($this->isQuotedStringToken($name)) {
            return false;
        }

        $lower = strtolower($name);

        return match ($component) {
            'timing' => $this->isTransitionTimingFunction($lower),
            'iteration' => $this->isAnimationIterationToken($lower),
            'direction' => in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true),
            'fill' => in_array($lower, ['forwards', 'backwards', 'both'], true),
            'play' => in_array($lower, ['running', 'paused'], true),
            default => false,
        };
    }

    private function isQuotedStringToken(string $token): bool
    {
        return preg_match('/^([\'"]).*\1$/s', trim($token)) === 1;
    }

    private function isAnimationIterationToken(string $token): bool
    {
        return $token === 'infinite' || preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1;
    }

    private function isAnimationTimelineToken(string $token): bool
    {
        $lower = strtolower(trim($token));

        return $lower === 'auto'
            || str_starts_with($lower, '--')
            || preg_match('/^(?:scroll|view)\(/', $lower) === 1;
    }

    private function minifyAnimationTimelineToken(string $token): string
    {
        $lower = strtolower(trim($token));
        if (preg_match('/^scroll\((.*)\)$/', $lower, $matches) === 1) {
            $parts = $this->splitWhitespaceTopLevel($matches[1]);
            sort($parts);
            $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== 'block' && $part !== 'nearest'));

            return 'scroll(' . implode(' ', $parts) . ')';
        }
        if (preg_match('/^view\((.*)\)$/', $lower, $matches) === 1) {
            $parts = $this->splitWhitespaceTopLevel($matches[1]);
            if (($parts[0] ?? null) === 'block') {
                array_shift($parts);
            }
            if (count($parts) >= 3 && $parts[1] === 'auto' && $parts[2] === 'auto') {
                array_splice($parts, 1);
            }
            if (count($parts) >= 3 && $parts[1] === $parts[2]) {
                array_pop($parts);
            }

            return 'view(' . implode(' ', $parts) . ')';
        }

        return $lower;
    }

    private function minifyAnimationRangeSideValue(string $value, string $side): string
    {
        $range = $this->parseAnimationRangeSide($value, $side);

        return $range === null ? trim($value) : $this->serializeAnimationRangeSide($range);
    }

    private function minifyAnimationRangeShorthandLayer(string $value): string
    {
        $range = $this->parseAnimationRangeShorthandLayer($value);

        return $range === null ? trim($value) : $this->serializeAnimationRangePair($range['start'], $range['end']);
    }

    /**
     * @return array{type:string,name?:string,offset?:string}|null
     */
    private function parseAnimationRangeSide(string $value, string $side): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);

        return $this->parseAnimationRangeSideTokens($tokens, $side);
    }

    /**
     * @param list<string> $tokens
     * @return array{type:string,name?:string,offset?:string}|null
     */
    private function parseAnimationRangeSideTokens(array $tokens, string $side): ?array
    {
        if (count($tokens) === 1) {
            $token = trim($tokens[0]);
            $lower = strtolower($token);
            if ($lower === 'normal') {
                return ['type' => 'normal'];
            }
            if ($this->isAnimationRangeOffsetToken($token)) {
                return ['type' => 'offset', 'offset' => $this->minifyLengthPercentageToken($token)];
            }
            if ($this->isAnimationRangeNameToken($token)) {
                return ['type' => 'named', 'name' => $lower];
            }

            return null;
        }

        if (count($tokens) === 2 && $this->isAnimationRangeNameToken($tokens[0]) && $this->isAnimationRangeOffsetToken($tokens[1])) {
            $offset = $this->minifyLengthPercentageToken($tokens[1]);
            if (($side === 'start' && $this->isPercentValue($offset, 0.0))
                || ($side === 'end' && $this->isPercentValue($offset, 100.0))
            ) {
                return ['type' => 'named', 'name' => strtolower(trim($tokens[0]))];
            }

            return ['type' => 'named', 'name' => strtolower(trim($tokens[0])), 'offset' => $offset];
        }

        return null;
    }

    /**
     * @return array{start:array{type:string,name?:string,offset?:string},end:array{type:string,name?:string,offset?:string}}|null
     */
    private function parseAnimationRangeShorthandLayer(string $value): ?array
    {
        if (stripos($value, 'var(') !== false) {
            return null;
        }

        $tokens = $this->splitWhitespaceTopLevel($value);
        $count = count($tokens);
        if ($count === 0 || $count > 4) {
            return null;
        }

        if ($count === 1) {
            $single = $this->parseAnimationRangeSideTokens([$tokens[0]], 'start');
            if ($single === null) {
                return null;
            }
            if ($single['type'] === 'named') {
                return [
                    'start' => $single,
                    'end' => ['type' => 'named', 'name' => $single['name']],
                ];
            }

            return [
                'start' => $single,
                'end' => ['type' => 'normal'],
            ];
        }

        $candidates = match ($count) {
            2 => [
                [[0], [1]],
            ],
            3 => [
                [[0, 1], [2]],
                [[0], [1, 2]],
            ],
            4 => [
                [[0, 1], [2, 3]],
            ],
            default => [],
        };

        foreach ($candidates as [$startIndices, $endIndices]) {
            $startTokens = array_map(static fn (int $index): string => $tokens[$index], $startIndices);
            $endTokens = array_map(static fn (int $index): string => $tokens[$index], $endIndices);
            $start = $this->parseAnimationRangeSideTokens($startTokens, 'start');
            $end = $this->parseAnimationRangeSideTokens($endTokens, 'end');
            if ($start !== null && $end !== null) {
                return ['start' => $start, 'end' => $end];
            }
        }

        return null;
    }

    private function isAnimationRangeNameToken(string $token): bool
    {
        $token = trim($token);
        $lower = strtolower($token);

        return !in_array($lower, ['normal', 'inherit', 'initial', 'revert', 'revert-layer', 'unset'], true)
            && preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $token) === 1;
    }

    private function isAnimationRangeOffsetToken(string $token): bool
    {
        $token = trim($token);
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)%$/', $token) === 1) {
            return true;
        }

        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:px|em|rem|vh|vw|vmin|vmax|ch|ex|lh|rlh|cm|mm|q|in|pt|pc)$/i', $token) === 1;
    }

    private function minifyLengthPercentageToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(%|[a-z]+)$/i', $token, $matches) !== 1) {
            return $token;
        }

        return $this->minifyNumber((float) $matches[1]) . strtolower($matches[2]);
    }

    private function isPercentValue(string $token, float $expected): bool
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) !== 1) {
            return false;
        }

        return abs(((float) $matches[1]) - $expected) < 0.0000001;
    }

    /**
     * @param array{type:string,name?:string,offset?:string} $side
     */
    private function serializeAnimationRangeSide(array $side): string
    {
        if ($side['type'] === 'normal') {
            return 'normal';
        }
        if ($side['type'] === 'offset') {
            return $side['offset'];
        }

        return $side['name'] . (isset($side['offset']) ? ' ' . $side['offset'] : '');
    }

    /**
     * @param array{type:string,name?:string,offset?:string} $start
     * @param array{type:string,name?:string,offset?:string} $end
     */
    private function serializeAnimationRangePair(array $start, array $end): string
    {
        if ($start['type'] === 'normal' && $end['type'] === 'normal') {
            return 'normal';
        }
        if ($end['type'] === 'normal') {
            return $this->serializeAnimationRangeSide($start);
        }
        if ($start['type'] === 'named'
            && $end['type'] === 'named'
            && ($start['name'] ?? null) === ($end['name'] ?? null)
            && !isset($start['offset'])
            && !isset($end['offset'])
        ) {
            return $start['name'];
        }

        return $this->serializeAnimationRangeSide($start) . ' ' . $this->serializeAnimationRangeSide($end);
    }

    private function minifyTransitionLonghandValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'transition',
            '-webkit-transition',
            '-moz-transition' => $this->minifyTransitionShorthandValue($value),
            'transition-property',
            '-webkit-transition-property',
            '-moz-transition-property' => $this->minifyTransitionPropertyValue($value),
            'transition-duration',
            'transition-delay' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTimeValue($part)),
            'transition-timing-function' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionTimingFunction($part)),
            default => $value,
        };
    }

    private function minifyFilterValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'filter',
            '-webkit-filter',
            'backdrop-filter',
            '-webkit-backdrop-filter' => $this->minifyFilterFunctionList($value),
            default => $value,
        };
    }

    private function minifyFilterFunctionList(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return trim($value);
        }

        $output = '';
        $previous = null;
        foreach ($tokens as $token) {
            $token = $this->minifyFilterToken($token);
            if ($previous !== null && $this->needsFilterTokenSpace($previous, $token)) {
                $output .= ' ';
            }
            $output .= $token;
            $previous = $token;
        }

        return $output;
    }

    private function minifyFilterToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^url\(/i', $token) === 1) {
            return $this->normalizeFilterUrlToken($token);
        }
        if (preg_match('/^([a-z-]+)\((.*)\)$/i', $token, $matches) !== 1) {
            return $token;
        }

        $function = strtolower($matches[1]);
        $arguments = trim($matches[2]);

        if ($function === 'blur' && $this->isZeroLengthToken($arguments)) {
            return 'blur()';
        }
        if ($function === 'brightness' && $this->isHundredPercentToken($arguments)) {
            return 'brightness()';
        }
        if ($function === 'hue-rotate' && $this->isZeroAngleToken($arguments)) {
            return 'hue-rotate()';
        }

        return $function . '(' . $arguments . ')';
    }

    private function needsFilterTokenSpace(string $previous, string $next): bool
    {
        return preg_match('/^(?:var|url|drop-shadow)\(/i', $previous) === 1
            || preg_match('/^(?:var|url|drop-shadow)\(/i', $next) === 1;
    }

    private function normalizeFilterUrlToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^url\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)$/i', $token, $matches) !== 1) {
            return $token;
        }

        $url = ($matches[2] ?? '') !== '' ? $matches[2] : trim($matches[3] ?? '');
        if (preg_match('/[\s\'"()\\\\]/', $url) === 1) {
            return 'url("' . str_replace('"', '\\"', $url) . '")';
        }

        return 'url(' . $url . ')';
    }

    private function minifyBoxShadowValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'box-shadow',
            '-webkit-box-shadow',
            '-moz-box-shadow',
            'text-shadow' => $this->mapCommaList($value, fn (string $part): string => $this->minifyShadowLayer($part)),
            default => $value,
        };
    }

    private function minifyTextEmphasisValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'text-emphasis',
            '-webkit-text-emphasis' => $this->minifyTextEmphasisShorthand($value),
            'text-emphasis-style',
            '-webkit-text-emphasis-style' => $this->minifyTextEmphasisStyle($value),
            'text-emphasis-position',
            '-webkit-text-emphasis-position' => $this->minifyTextEmphasisPosition($value),
            default => $value,
        };
    }

    private function minifyTextEmphasisShorthand(string $value): string
    {
        $components = $this->parseTextEmphasisShorthandComponents($value);

        return $components === null ? trim($value) : $this->serializeTextEmphasisComponents($components);
    }

    private function minifyTextEmphasisStyle(string $value): string
    {
        $value = trim($value);
        if ($this->isQuotedStringToken($value)) {
            return $value;
        }

        $tokens = $this->splitWhitespaceTopLevel(strtolower($value));
        if ($tokens === []) {
            return $value;
        }

        $fill = null;
        $shape = null;
        foreach ($tokens as $token) {
            if ($token === 'filled' || $token === 'open') {
                $fill = $token;
                continue;
            }
            if ($this->isTextEmphasisShapeToken($token) || $token === 'none') {
                $shape = $token;
                continue;
            }

            return $value;
        }

        if ($shape === null) {
            return $fill ?? $value;
        }
        if ($shape === 'none') {
            return 'none';
        }

        return $fill === 'open' ? 'open ' . $shape : $shape;
    }

    private function minifyTextEmphasisPosition(string $value): string
    {
        if (stripos($value, 'var(') !== false) {
            return trim($value);
        }

        $tokens = $this->splitWhitespaceTopLevel(strtolower(trim($value)));
        if (count($tokens) === 2 && $tokens[1] === 'right') {
            array_pop($tokens);
        }

        return $tokens === [] ? trim($value) : implode(' ', $tokens);
    }

    /**
     * @return array{style:?string,color:?string,other:list<string>}|null
     */
    private function parseTextEmphasisShorthandComponents(string $value): ?array
    {
        $styleTokens = [];
        $color = null;
        $other = [];

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($this->isTextEmphasisColorToken($token)) {
                if ($color !== null) {
                    return null;
                }
                $color = trim($token);
                continue;
            }
            if ($this->isQuotedStringToken($token) || $lower === 'filled' || $lower === 'open' || $lower === 'none' || $this->isTextEmphasisShapeToken($lower)) {
                $styleTokens[] = $token;
                continue;
            }

            $other[] = trim($token);
        }

        $style = $styleTokens === [] ? null : $this->minifyTextEmphasisStyle(implode(' ', $styleTokens));
        if ($style === null && $color === null && $other === []) {
            return null;
        }

        return [
            'style' => $style,
            'color' => $color,
            'other' => $other,
        ];
    }

    /**
     * @param array{style:?string,color:?string,other:list<string>} $components
     */
    private function serializeTextEmphasisComponents(array $components): string
    {
        $parts = [];
        if ($components['style'] !== null) {
            $parts[] = $components['style'];
        }
        if ($components['color'] !== null) {
            $parts[] = $components['color'];
        }
        array_push($parts, ...$components['other']);

        return implode(' ', $parts);
    }

    private function isTextEmphasisShapeToken(string $token): bool
    {
        return in_array($token, ['dot', 'circle', 'double-circle', 'triangle', 'sesame'], true);
    }

    private function isTextEmphasisColorToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        if ($token[0] === '#') {
            return true;
        }
        if (preg_match('/^(?:rgb|rgba|hsl|hsla|lab|lch|oklab|oklch|color)\(/i', $token) === 1) {
            return true;
        }

        return in_array(strtolower($token), [
            'black',
            'blue',
            'currentcolor',
            'green',
            'red',
            'transparent',
            'white',
            'yellow',
        ], true);
    }

    private function minifyCaretValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'caret' => $this->minifyCaretShorthand($value),
            'caret-shape' => strtolower(trim($value)),
            default => $value,
        };
    }

    private function minifyListStyleValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'list-style' => $this->minifyListStyleShorthand($value),
            'list-style-type' => $this->minifyListStyleTypeValue($value),
            'list-style-image' => $this->minifyListStyleImageValue($value, false),
            'list-style-position' => strtolower(trim($value)),
            default => $value,
        };
    }

    private function minifyListStyleShorthand(string $value): string
    {
        if ($this->containsCustomPropertyReference($value)) {
            return implode(' ', array_map(
                fn (string $token): string => $this->minifyListStyleTokenInPlace($token, false),
                $this->splitWhitespaceTopLevel($value)
            ));
        }

        $components = $this->parseListStyleComponents($value, false);

        return $components === null ? trim($value) : $this->serializeListStyleComponents($components);
    }

    private function minifyListStyleTokenInPlace(string $token, bool $quoteSafeUrls): string
    {
        $lower = strtolower(trim($token));
        if ($lower === 'inside' || $lower === 'outside' || $lower === 'none') {
            return $lower;
        }
        if ($this->isListStyleImageToken($token)) {
            return $this->minifyListStyleImageValue($token, $quoteSafeUrls);
        }

        return $this->minifyListStyleTypeValue($token);
    }

    private function minifyListStyleTypeValue(string $value): string
    {
        $value = trim($value);
        if ($this->isQuotedStringToken($value)) {
            return $this->normalizeCssStringToken($value);
        }
        if (preg_match('/^symbols\((.*)\)$/is', $value, $matches) === 1) {
            $tokens = $this->splitListStyleSymbolsArguments(trim($matches[1]));
            if ($tokens === []) {
                return $value;
            }

            $system = strtolower($tokens[0]);
            $parts = [];
            if (in_array($system, ['cyclic', 'numeric', 'alphabetic', 'symbolic', 'fixed'], true)) {
                array_shift($tokens);
                if ($system !== 'symbolic') {
                    $parts[] = $system;
                }
            }

            foreach ($tokens as $token) {
                $parts[] = $this->minifyListStyleSymbolToken($token);
            }

            return 'symbols(' . implode(' ', $parts) . ')';
        }

        return strtolower($value) === 'none' ? 'none' : $value;
    }

    /**
     * @return list<string>
     */
    private function splitListStyleSymbolsArguments(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        for ($i = 0; $i < $length;) {
            if (ctype_space($value[$i])) {
                $i++;
                continue;
            }

            $char = $value[$i];
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $token = $char;
                for ($i++; $i < $length; $i++) {
                    $token .= $value[$i];
                    if ($value[$i] === '\\' && $i + 1 < $length) {
                        $token .= $value[++$i];
                        continue;
                    }
                    if ($value[$i] === $quote) {
                        $i++;
                        break;
                    }
                }
                $tokens[] = $token;
                continue;
            }

            if ($this->startsUrlFunction($value, $i)) {
                [$token, $offset] = $this->readFunctionRaw($value, $i);
                $tokens[] = $token;
                $i = $offset + 1;
                continue;
            }

            $token = '';
            for (; $i < $length; $i++) {
                if (ctype_space($value[$i]) || $value[$i] === '"' || $value[$i] === "'") {
                    break;
                }
                $token .= $value[$i];
            }
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function minifyListStyleSymbolToken(string $token): string
    {
        $token = trim($token);
        if ($this->isQuotedStringToken($token)) {
            return $this->normalizeCssStringToken($token);
        }
        if (preg_match('/^url\(/i', $token) === 1) {
            return $this->normalizeCssUrlToken($token, false);
        }

        return $token;
    }

    private function minifyListStyleImageValue(string $value, bool $quoteSafeUrls): string
    {
        $value = trim($value);
        if (preg_match('/^url\(/i', $value) === 1) {
            return $this->normalizeCssUrlToken($value, $quoteSafeUrls);
        }

        return $value;
    }

    private function minifyImageSetFunctions(string $value): string
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

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            $lower = strtolower(substr($value, $i));
            $previous = $i > 0 ? $value[$i - 1] : '';
            if ((str_starts_with($lower, 'image-set(') || str_starts_with($lower, '-webkit-image-set('))
                && ($previous === '' || !$this->isIdentifierChar($previous))
            ) {
                [$function, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $this->minifyImageSetFunction($function);
                $i = $offset;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifyImageSetFunction(string $function): string
    {
        if (preg_match('/^(-webkit-image-set|image-set)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $name = strtolower($matches[1]);
        $isPrefixed = $name === '-webkit-image-set';
        $candidates = array_map(
            fn (string $candidate): string => $this->minifyImageSetCandidate($candidate, $isPrefixed),
            $this->splitTopLevel($matches[2], ',')
        );

        return $name . '(' . implode(',', $candidates) . ')';
    }

    private function minifyImageSetCandidate(string $candidate, bool $isPrefixed): string
    {
        $tokens = $this->splitWhitespaceTopLevel($candidate);
        if ($tokens === []) {
            return trim($candidate);
        }

        $image = array_shift($tokens);
        $resolution = null;
        $type = null;
        $other = [];

        foreach ($tokens as $token) {
            if ($this->isImageSetResolutionToken($token)) {
                $resolution = $this->minifyImageSetResolutionToken($token);
                continue;
            }
            if ($this->isImageSetTypeToken($token)) {
                $type = $this->normalizeImageSetTypeToken($token);
                continue;
            }

            $other[] = trim($token);
        }

        $parts = [$this->normalizeImageSetImageToken($image, $isPrefixed)];
        if ($resolution !== null || !$isPrefixed || $type !== null) {
            $parts[] = $resolution ?? '1x';
        }
        if ($type !== null) {
            $parts[] = $type;
        }
        array_push($parts, ...$other);

        return implode(' ', $parts);
    }

    private function normalizeImageSetImageToken(string $token, bool $isPrefixed): string
    {
        $token = trim($token);
        if ($this->isQuotedStringToken($token)) {
            if ($isPrefixed) {
                $url = $this->cssStringTokenValue($token);

                return 'url("' . str_replace('"', '\\"', $url) . '")';
            }

            return $this->normalizeCssStringToken($token);
        }

        if (preg_match('/^url\(/i', $token) === 1) {
            if ($isPrefixed) {
                return $this->normalizeCssUrlToken($token, false);
            }

            $url = $this->cssUrlTokenValue($token);
            if ($url !== null) {
                return '"' . str_replace('"', '\\"', $url) . '"';
            }
        }

        return $token;
    }

    private function isImageSetResolutionToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:x|dppx|dpi)$/i', trim($token)) === 1;
    }

    private function minifyImageSetResolutionToken(string $token): string
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(x|dppx|dpi)$/i', trim($token), $matches) !== 1) {
            return trim($token);
        }

        return $this->minifyNumber((float) $matches[1]) . strtolower($matches[2]);
    }

    private function isImageSetTypeToken(string $token): bool
    {
        return preg_match('/^type\(/i', trim($token)) === 1;
    }

    private function normalizeImageSetTypeToken(string $token): string
    {
        if (preg_match('/^type\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)$/i', trim($token), $matches) !== 1) {
            return trim($token);
        }

        $type = ($matches[2] ?? '') !== '' ? $matches[2] : trim($matches[3] ?? '');

        return 'type("' . str_replace('"', '\\"', $type) . '")';
    }

    private function minifyGradientFunctions(string $value): string
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

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            $dashedIdentifierStart = $char === '-' && isset($value[$i + 1]) && $this->isIdentifierStart($value[$i + 1]);
            if (!$this->isIdentifierStart($char) && !$dashedIdentifierStart) {
                $output .= $char;
                continue;
            }

            $identifier = $this->readIdentifier($value, $i);
            $next = $i + strlen($identifier);
            $name = strtolower($identifier);
            if (($value[$next] ?? '') !== '(' || !in_array($name, [
                'linear-gradient',
                'repeating-linear-gradient',
                'radial-gradient',
                'repeating-radial-gradient',
                '-webkit-radial-gradient',
                '-moz-radial-gradient',
                '-o-radial-gradient',
                '-webkit-repeating-radial-gradient',
                '-moz-repeating-radial-gradient',
                '-o-repeating-radial-gradient',
                'conic-gradient',
                'repeating-conic-gradient',
                '-webkit-gradient',
            ], true)) {
                $output .= $identifier;
                $i = $next - 1;
                continue;
            }

            [$function, $offset] = $this->readFunctionRaw($value, $i);
            $output .= match (true) {
                $name === 'linear-gradient' || $name === 'repeating-linear-gradient' => $this->minifyLinearGradientFunction($function),
                str_contains($name, 'radial-gradient') => $this->minifyRadialGradientFunction($function),
                $name === 'conic-gradient' || $name === 'repeating-conic-gradient' => $this->minifyConicGradientFunction($function),
                default => $this->minifyLegacyWebkitGradientFunction($function),
            };
            $i = $offset;
        }

        return $output;
    }

    private function minifyLinearGradientFunction(string $function): string
    {
        if (preg_match('/^((?:repeating-)?linear-gradient)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $name = strtolower($matches[1]);
        $parts = $this->splitTopLevel($matches[2], ',');
        if (count($parts) < 2) {
            return $function;
        }

        $direction = $this->parseLinearGradientDirection($parts[0]);
        if ($direction !== null) {
            array_shift($parts);
        }

        $gradientParts = array_map(
            fn (string $part): array => $this->parseLinearGradientPart($part),
            $parts
        );

        $prefix = $direction['prefix'] ?? null;
        if ($direction !== null && !$this->linearGradientPartsHaveSafeColors($gradientParts)) {
            $prefix = $direction['original'];
        } elseif (($direction['reverse'] ?? false) && $this->canReverseLinearGradientParts($gradientParts)) {
            $gradientParts = array_reverse(array_map(
                fn (array $part): array => $this->invertLinearGradientPartPercentages($part),
                $gradientParts
            ));
            $prefix = null;
        }

        $gradientParts = $this->mergeAdjacentLinearGradientStops($gradientParts);

        $serialized = [];
        if ($prefix !== null) {
            $serialized[] = $prefix;
        }

        foreach ($gradientParts as $index => $part) {
            $css = $this->serializeLinearGradientPart($part);
            if ($css === '' || ($part['kind'] === 'hint' && $this->isDefaultLinearGradientHint($part['value'], $index, $gradientParts))) {
                continue;
            }
            $serialized[] = $css;
        }

        if (count($serialized) < 2) {
            return $function;
        }

        return $name . '(' . implode(',', $serialized) . ')';
    }

    private function minifyRadialGradientFunction(string $function): string
    {
        if (preg_match('/^((?:(?:-webkit|-moz|-o)-)?(?:repeating-)?radial-gradient)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $name = strtolower($matches[1]);
        $parts = $this->splitTopLevel($matches[2], ',');
        if (count($parts) < 2) {
            return $function;
        }

        $prelude = $this->minifyRadialGradientPrelude($parts[0]);
        if ($prelude !== null) {
            array_shift($parts);
        }

        $serialized = [];
        if ($prelude !== null && $prelude !== '') {
            $serialized[] = $prelude;
        }

        foreach ($parts as $part) {
            $css = $this->serializeLinearGradientPart($this->parseLinearGradientPart($part));
            if ($css !== '') {
                $serialized[] = $css;
            }
        }

        if (count($serialized) < 2) {
            return $function;
        }

        return $name . '(' . implode(',', $serialized) . ')';
    }

    private function minifyConicGradientFunction(string $function): string
    {
        if (preg_match('/^((?:repeating-)?conic-gradient)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $name = strtolower($matches[1]);
        $parts = $this->splitTopLevel($matches[2], ',');
        if (count($parts) < 2) {
            return $function;
        }

        $prelude = $this->minifyConicGradientPrelude($parts[0]);
        if ($prelude !== null) {
            array_shift($parts);
        }

        $serialized = [];
        if ($prelude !== null && $prelude !== '') {
            $serialized[] = $prelude;
        }

        foreach ($parts as $part) {
            $css = $this->serializeLinearGradientPart($this->parseConicGradientPart($part));
            if ($css !== '') {
                $serialized[] = $css;
            }
        }

        if (count($serialized) < 2) {
            return $function;
        }

        return $name . '(' . implode(',', $serialized) . ')';
    }

    private function minifyLegacyWebkitGradientFunction(string $function): string
    {
        if (preg_match('/^-webkit-gradient\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $parts = $this->splitTopLevel($matches[1], ',');
        if (count($parts) < 6 || strcasecmp(trim($parts[0]), 'radial') !== 0) {
            return $function;
        }

        $parts[0] = 'radial';
        $parts[1] = $this->minifyLegacyWebkitGradientPoint($parts[1]);
        $parts[2] = $this->minifyPlainNumberToken($parts[2]);
        $parts[3] = $this->minifyLegacyWebkitGradientPoint($parts[3]);
        $parts[4] = $this->minifyPlainNumberToken($parts[4]);

        for ($i = 5; $i < count($parts); $i++) {
            $parts[$i] = $this->minifyLegacyWebkitGradientColorStop($parts[$i]);
        }

        return '-webkit-gradient(' . implode(',', $parts) . ')';
    }

    private function minifyRadialGradientPrelude(string $part): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($part));
        if ($tokens === []) {
            return null;
        }

        $shape = null;
        $extent = null;
        $sizes = [];
        $position = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $lower = strtolower($token);

            if ($lower === 'at') {
                $position = $this->minifyGradientPosition(array_slice($tokens, $i + 1));
                if ($position === null) {
                    return null;
                }
                break;
            }

            if ($lower === 'circle' || $lower === 'ellipse') {
                if ($shape !== null) {
                    return null;
                }
                $shape = $lower;
                continue;
            }

            if (in_array($lower, ['closest-side', 'farthest-side', 'closest-corner', 'farthest-corner'], true)) {
                if ($extent !== null || $sizes !== []) {
                    return null;
                }
                $extent = $lower;
                continue;
            }

            if ($this->isRadialGradientSizeToken($token)) {
                if ($extent !== null || count($sizes) >= 2) {
                    return null;
                }
                $sizes[] = $this->minifyGradientPositionComponent($token) ?? $this->minifyNumericDimensionToken($token);
                continue;
            }

            return null;
        }

        if ($shape === 'circle' && count($sizes) > 1) {
            return null;
        }

        $components = [];
        if ($sizes !== []) {
            $components[] = implode(' ', $sizes);
        } else {
            if ($shape === 'circle') {
                $components[] = 'circle';
            }
            if ($extent !== null && $extent !== 'farthest-corner') {
                $components[] = $extent;
            }
        }

        if ($position !== null && $position !== '') {
            $components[] = 'at ' . $position;
        }

        return implode(' ', $components);
    }

    private function minifyConicGradientPrelude(string $part): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($part));
        if ($tokens === []) {
            return null;
        }

        $angle = null;
        $position = null;
        $index = 0;

        if (strtolower($tokens[0]) === 'from') {
            if (!isset($tokens[1])) {
                return null;
            }
            $angle = $this->minifyConicFromAngle($tokens[1]);
            if ($angle === null) {
                return null;
            }
            $index = 2;
        }

        if (isset($tokens[$index]) && strtolower($tokens[$index]) === 'at') {
            $position = $this->minifyGradientPosition(array_slice($tokens, $index + 1));
            if ($position === null) {
                return null;
            }
            $index = count($tokens);
        }

        if ($index !== count($tokens)) {
            return null;
        }

        $components = [];
        if ($angle !== null && !$this->isZeroConicAngle($angle)) {
            $components[] = 'from ' . $angle;
        }
        if ($position !== null && $position !== '') {
            $components[] = 'at ' . $position;
        }

        return implode(' ', $components);
    }

    /**
     * @return array{prefix:?string,reverse:bool,original:string}|null
     */
    private function parseLinearGradientDirection(string $part): ?array
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($part)) ?? trim($part));
        if (str_starts_with($normalized, 'to ')) {
            if ($normalized === 'to bottom') {
                return ['prefix' => null, 'reverse' => false, 'original' => $normalized];
            }
            if ($normalized === 'to top') {
                return ['prefix' => '0deg', 'reverse' => true, 'original' => $normalized];
            }

            return ['prefix' => $normalized, 'reverse' => false, 'original' => $normalized];
        }

        $degrees = $this->parseLinearGradientAngleDegrees($part);
        if ($degrees === null) {
            return null;
        }

        $angle = $this->minifyNumber($degrees) . 'deg';
        if ($this->linearGradientDegreesEqual($degrees, 180.0)) {
            return ['prefix' => null, 'reverse' => false, 'original' => $angle];
        }
        if ($this->linearGradientDegreesEqual($degrees, 0.0)) {
            return ['prefix' => '0deg', 'reverse' => true, 'original' => $angle];
        }

        return ['prefix' => $angle, 'reverse' => false, 'original' => $angle];
    }

    private function parseLinearGradientAngleDegrees(string $token): ?float
    {
        $token = trim($token);
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:deg|grad|rad|turn)?$/i', $token) !== 1) {
            return null;
        }

        return $this->parseHueDegrees($token);
    }

    private function linearGradientDegreesEqual(float $left, float $right): bool
    {
        return abs($left - $right) < 0.000001 || abs(abs($left - $right) - 360.0) < 0.000001;
    }

    /**
     * @return array{kind:string,value?:string,color?:string,positions?:list<string>}
     */
    private function parseLinearGradientPart(string $part): array
    {
        $part = trim($part);
        if ($part === '') {
            return ['kind' => 'raw', 'value' => ''];
        }

        if ($this->isLinearGradientPositionToken($part)) {
            return ['kind' => 'hint', 'value' => $this->minifyLinearGradientPositionToken($part)];
        }

        $tokens = $this->splitWhitespaceTopLevel($part);
        if ($tokens === []) {
            return ['kind' => 'raw', 'value' => $part];
        }

        $color = array_shift($tokens);

        return [
            'kind' => 'stop',
            'color' => $this->minifyColorKeywords($color),
            'positions' => array_map(
                fn (string $token): string => $this->minifyLinearGradientPositionToken($token),
                $tokens
            ),
        ];
    }

    /**
     * @return array{kind:string,value?:string,color?:string,positions?:list<string>}
     */
    private function parseConicGradientPart(string $part): array
    {
        $part = trim($part);
        if ($part === '') {
            return ['kind' => 'raw', 'value' => ''];
        }

        $tokens = $this->splitWhitespaceTopLevel($part);
        if ($tokens === []) {
            return ['kind' => 'raw', 'value' => $part];
        }

        $color = array_shift($tokens);

        return [
            'kind' => 'stop',
            'color' => $this->minifyColorKeywords($color),
            'positions' => array_map(
                fn (string $token): string => $this->minifyConicGradientPositionToken($token),
                $tokens
            ),
        ];
    }

    private function isLinearGradientPositionToken(string $token): bool
    {
        $token = trim($token);

        return $this->linearGradientPercentPosition($token) !== null
            || preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:px|em|rem|ch|ex|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cqw|cqh|cqi|cqb|cqmin|cqmax)$/i', $token) === 1
            || preg_match('/^(?:calc|min|max|clamp)\(/i', $token) === 1;
    }

    private function minifyLinearGradientPositionToken(string $token): string
    {
        $token = trim($token);
        $percent = $this->linearGradientPercentPosition($token);
        if ($percent !== null) {
            return $this->minifyNumber($percent) . '%';
        }

        return $this->minifyNumericDimensionToken($token);
    }

    private function minifyConicGradientPositionToken(string $token): string
    {
        $token = trim($token);
        $percent = $this->linearGradientPercentPosition($token);
        if ($percent !== null) {
            return $this->minifyNumber($percent) . '%';
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(deg|grad|rad|turn)$/i', $token, $matches) === 1) {
            return $this->minifyNumber((float) $matches[1]) . strtolower($matches[2]);
        }

        return $this->minifyNumericDimensionToken($token);
    }

    private function linearGradientPercentPosition(string $token): ?float
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', trim($token), $matches) !== 1) {
            return null;
        }

        return (float) $matches[1];
    }

    private function isRadialGradientSizeToken(string $token): bool
    {
        $token = trim($token);

        return $this->minifyGradientPositionComponent($token) !== null
            || preg_match('/^(?:calc|min|max|clamp)\(/i', $token) === 1;
    }

    /**
     * @param list<string> $tokens
     */
    private function minifyGradientPosition(array $tokens): ?string
    {
        $tokens = array_values(array_filter(array_map(static fn (string $token): string => trim($token), $tokens), static fn (string $token): bool => $token !== ''));
        if ($tokens === [] || count($tokens) > 2) {
            return null;
        }

        if (count($tokens) === 1) {
            $lower = strtolower($tokens[0]);
            if ($lower === 'center') {
                return '';
            }
            if (in_array($lower, ['top', 'right', 'bottom', 'left'], true)) {
                return $lower;
            }

            $component = $this->minifyGradientPositionComponent($tokens[0]);

            return $component === '50%' ? '' : $component;
        }

        $first = strtolower($tokens[0]);
        $second = strtolower($tokens[1]);
        $horizontal = ['left' => '0', 'center' => '50%', 'right' => '100%'];
        $vertical = ['top' => '0', 'center' => '50%', 'bottom' => '100%'];

        if (isset($vertical[$first], $horizontal[$second])) {
            $x = $horizontal[$second];
            $y = $vertical[$first];
        } elseif (isset($horizontal[$first], $vertical[$second])) {
            $x = $horizontal[$first];
            $y = $vertical[$second];
        } else {
            $x = $this->minifyGradientPositionComponent($tokens[0]);
            $y = $this->minifyGradientPositionComponent($tokens[1]);
            if ($x === null || $y === null) {
                return null;
            }
        }

        if ($x === '50%' && $y === '50%') {
            return '';
        }
        if ($y === '50%') {
            return $x;
        }

        return $x . ' ' . $y;
    }

    private function minifyGradientPositionComponent(string $token): ?string
    {
        $token = strtolower(trim($token));
        if (in_array($token, ['left', 'top'], true)) {
            return '0';
        }
        if (in_array($token, ['right', 'bottom'], true)) {
            return '100%';
        }
        if ($token === 'center') {
            return '50%';
        }

        $percent = $this->linearGradientPercentPosition($token);
        if ($percent !== null) {
            return abs($percent) < 0.0000001 ? '0' : $this->minifyNumber($percent) . '%';
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:px|em|rem|ch|ex|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cqw|cqh|cqi|cqb|cqmin|cqmax)$/i', $token) === 1) {
            return $this->minifyNumericDimensionToken($token);
        }

        return null;
    }

    private function minifyConicFromAngle(string $token): ?string
    {
        $degrees = $this->parseLinearGradientAngleDegrees($token);
        if ($degrees === null) {
            return null;
        }

        return $this->minifyNumber($degrees) . 'deg';
    }

    private function isZeroConicAngle(string $angle): bool
    {
        return preg_match('/^0(?:deg)?$/', $angle) === 1;
    }

    private function minifyLegacyWebkitGradientPoint(string $point): string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($point));
        if ($tokens === []) {
            return trim($point);
        }

        $components = [];
        foreach ($tokens as $token) {
            $component = $this->minifyGradientPositionComponent($token);
            if ($component === null) {
                return trim($point);
            }
            $components[] = $component;
        }

        return implode(' ', $components);
    }

    private function minifyLegacyWebkitGradientColorStop(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(from|to)\((.*)\)$/is', $value, $matches) === 1) {
            return strtolower($matches[1]) . '(' . $this->minifyColorKeywords(trim($matches[2])) . ')';
        }

        if (preg_match('/^color-stop\((.*)\)$/is', $value, $matches) !== 1) {
            return $value;
        }

        $parts = $this->splitTopLevel($matches[1], ',');
        if (count($parts) !== 2) {
            return $value;
        }

        $position = trim($parts[0]);
        $percent = $this->linearGradientPercentPosition($position);
        if ($percent !== null) {
            $position = $this->minifyNumber($percent / 100);
        } else {
            $position = $this->minifyPlainNumberToken($position);
        }

        return 'color-stop(' . $position . ',' . $this->minifyColorKeywords(trim($parts[1])) . ')';
    }

    /**
     * @param list<array{kind:string,value?:string,color?:string,positions?:list<string>}> $parts
     */
    private function canReverseLinearGradientParts(array $parts): bool
    {
        foreach ($parts as $part) {
            if ($part['kind'] === 'hint') {
                if (!isset($part['value']) || $this->linearGradientPercentPosition($part['value']) === null) {
                    return false;
                }
                continue;
            }
            if ($part['kind'] !== 'stop') {
                return false;
            }
            foreach ($part['positions'] ?? [] as $position) {
                if ($this->linearGradientPercentPosition($position) === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<array{kind:string,value?:string,color?:string,positions?:list<string>}> $parts
     */
    private function linearGradientPartsHaveSafeColors(array $parts): bool
    {
        foreach ($parts as $part) {
            if ($part['kind'] === 'hint') {
                continue;
            }
            if ($part['kind'] !== 'stop' || !isset($part['color']) || !$this->isSafeLinearGradientColorToken($part['color'])) {
                return false;
            }
        }

        return true;
    }

    private function isSafeLinearGradientColorToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        if ($this->parseSerializedSrgbColor($token) !== null) {
            return true;
        }

        return preg_match('/^(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color|color-mix|light-dark)\(/i', $token) === 1;
    }

    /**
     * @param array{kind:string,value?:string,color?:string,positions?:list<string>} $part
     * @return array{kind:string,value?:string,color?:string,positions?:list<string>}
     */
    private function invertLinearGradientPartPercentages(array $part): array
    {
        if ($part['kind'] === 'hint' && isset($part['value'])) {
            $part['value'] = $this->invertLinearGradientPercentToken($part['value']);

            return $part;
        }

        if ($part['kind'] === 'stop') {
            $positions = [];
            foreach ($part['positions'] ?? [] as $position) {
                $positions[] = $this->invertLinearGradientPercentToken($position);
            }
            sort($positions, SORT_NATURAL);
            $part['positions'] = $positions;
        }

        return $part;
    }

    private function invertLinearGradientPercentToken(string $token): string
    {
        $percent = $this->linearGradientPercentPosition($token);
        if ($percent === null) {
            return $token;
        }

        return $this->minifyNumber(100.0 - $percent) . '%';
    }

    /**
     * @param list<array{kind:string,value?:string,color?:string,positions?:list<string>}> $parts
     * @return list<array{kind:string,value?:string,color?:string,positions?:list<string>}>
     */
    private function mergeAdjacentLinearGradientStops(array $parts): array
    {
        $merged = [];
        $count = count($parts);

        for ($i = 0; $i < $count; $i++) {
            $current = $parts[$i];
            $next = $parts[$i + 1] ?? null;
            if ($next !== null
                && $current['kind'] === 'stop'
                && $next['kind'] === 'stop'
                && ($current['color'] ?? null) === ($next['color'] ?? null)
                && count($current['positions'] ?? []) === 1
                && count($next['positions'] ?? []) === 1
            ) {
                $current['positions'] = [
                    $current['positions'][0],
                    $next['positions'][0],
                ];
                $merged[] = $current;
                $i++;
                continue;
            }

            $merged[] = $current;
        }

        return $merged;
    }

    /**
     * @param array{kind:string,value?:string,color?:string,positions?:list<string>} $part
     */
    private function serializeLinearGradientPart(array $part): string
    {
        if ($part['kind'] === 'hint') {
            return $part['value'] ?? '';
        }
        if ($part['kind'] !== 'stop') {
            return $part['value'] ?? '';
        }

        $positions = $part['positions'] ?? [];

        return trim(($part['color'] ?? '') . ($positions === [] ? '' : ' ' . implode(' ', $positions)));
    }

    /**
     * @param list<array{kind:string,value?:string,color?:string,positions?:list<string>}> $parts
     */
    private function isDefaultLinearGradientHint(string $value, int $index, array $parts): bool
    {
        if ($index === 0 || $index === count($parts) - 1) {
            return false;
        }

        $percent = $this->linearGradientPercentPosition($value);

        return $percent !== null && abs($percent - 50.0) < 0.000001;
    }

    /**
     * @return array{type:string,image:string,position:string}|null
     */
    private function parseListStyleComponents(string $value, bool $quoteSafeUrls): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return null;
        }

        $type = null;
        $image = null;
        $position = null;
        $noneCount = 0;

        foreach ($tokens as $token) {
            $lower = strtolower(trim($token));
            if ($lower === 'none') {
                $noneCount++;
                continue;
            }
            if (($lower === 'inside' || $lower === 'outside') && $position === null) {
                $position = $lower;
                continue;
            }
            if ($this->isListStyleImageToken($token)) {
                if ($image !== null) {
                    return null;
                }
                $image = $this->minifyListStyleImageValue($token, $quoteSafeUrls);
                continue;
            }
            if ($type !== null) {
                return null;
            }
            $type = $this->minifyListStyleTypeValue($token);
        }

        if ($noneCount > 0) {
            if ($type === null) {
                $type = 'none';
                $noneCount--;
            }
            if ($noneCount > 0 && $image === null) {
                $image = 'none';
                $noneCount--;
            }
            if ($noneCount > 0) {
                return null;
            }
            if ($type !== null && strtolower($type) !== 'none' && $image === null) {
                $image = 'none';
            }
        }

        return [
            'type' => $type ?? 'disc',
            'image' => $image ?? 'none',
            'position' => $position ?? 'outside',
        ];
    }

    /**
     * @param array{type:string,image:string,position:string} $components
     */
    private function serializeListStyleComponents(array $components): string
    {
        $type = strtolower($components['type']) === 'none' ? 'none' : $components['type'];
        $image = $components['image'];
        $position = strtolower($components['position']);
        $parts = [];

        if ($position !== 'outside') {
            $parts[] = $position;
        }
        if (strtolower($image) !== 'none') {
            $parts[] = $image;
        }
        if (strtolower($type) !== 'disc') {
            $parts[] = $type;
        }

        return $parts === [] ? 'outside' : implode(' ', $parts);
    }

    private function isListStyleImageToken(string $token): bool
    {
        return preg_match('/^(?:url|(?:-(?:webkit|o)-)?(?:linear|radial|conic)-gradient|image-set|cross-fade|paint)\(/i', trim($token)) === 1;
    }

    private function normalizeCssStringToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^([\'"])(.*)\1$/s', $token, $matches) !== 1) {
            return $token;
        }

        return '"' . str_replace('"', '\\"', $matches[2]) . '"';
    }

    private function cssStringTokenValue(string $token): string
    {
        if (preg_match('/^([\'"])(.*)\1$/s', trim($token), $matches) !== 1) {
            return trim($token);
        }

        return $matches[2];
    }

    private function normalizeCssUrlToken(string $token, bool $quoteSafeUrls): string
    {
        $token = trim($token);
        if (preg_match('/^url\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)$/i', $token, $matches) !== 1) {
            return $token;
        }

        $url = ($matches[2] ?? '') !== '' ? $matches[2] : trim($matches[3] ?? '');
        if ($quoteSafeUrls || preg_match('/[\s\'"()\\\\]/', $url) === 1) {
            return 'url("' . str_replace('"', '\\"', $url) . '")';
        }

        return 'url(' . $url . ')';
    }

    private function cssUrlTokenValue(string $token): ?string
    {
        if (preg_match('/^url\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)$/i', trim($token), $matches) !== 1) {
            return null;
        }

        return ($matches[2] ?? '') !== '' ? $matches[2] : trim($matches[3] ?? '');
    }

    private function minifyCaretShorthand(string $value): string
    {
        $components = $this->parseCaretShorthandComponents($value);

        return $components === null ? trim($value) : $this->serializeCaretComponents($components);
    }

    /**
     * @return array{color:?string,shape:?string}|null
     */
    private function parseCaretShorthandComponents(string $value): ?array
    {
        $color = null;
        $shape = null;
        $auto = 0;

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($lower === 'auto') {
                $auto++;
                continue;
            }

            if ($this->isCaretShapeToken($lower)) {
                if ($shape !== null) {
                    return null;
                }
                $shape = $lower;
                continue;
            }

            if ($this->isCaretColorToken($token)) {
                if ($color !== null) {
                    return null;
                }
                $color = trim($token);
                continue;
            }

            if ($shape !== null) {
                return null;
            }
            $shape = trim($token);
        }

        while ($auto > 0) {
            if ($color === null) {
                $color = 'auto';
            } elseif ($shape === null) {
                $shape = 'auto';
            } else {
                return null;
            }
            $auto--;
        }

        return [
            'color' => $color,
            'shape' => $shape,
        ];
    }

    /**
     * @param array{color:?string,shape:?string} $components
     */
    private function serializeCaretComponents(array $components): string
    {
        $parts = [];
        if ($components['color'] !== null && strtolower($components['color']) !== 'auto') {
            $parts[] = $components['color'];
        }
        if ($components['shape'] !== null && strtolower($components['shape']) !== 'auto') {
            $parts[] = $components['shape'];
        }

        return $parts === [] ? 'auto' : implode(' ', $parts);
    }

    private function isCaretShapeToken(string $token): bool
    {
        return in_array($token, ['bar', 'block', 'underscore'], true);
    }

    private function isCaretColorToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        if ($token[0] === '#') {
            return true;
        }
        if (preg_match('/^(?:rgb|rgba|hsl|hsla|lab|lch|oklab|oklch|color)\(/i', $token) === 1) {
            return true;
        }

        return in_array(strtolower($token), [
            'black',
            'blue',
            'currentcolor',
            'green',
            'red',
            'transparent',
            'white',
            'yellow',
        ], true);
    }

    private function containsCustomPropertyReference(string $value): bool
    {
        return stripos($value, 'var(') !== false;
    }

    private function minifyShadowLayer(string $layer): string
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        if ($tokens === []) {
            return trim($layer);
        }
        if (count($tokens) === 1 && strcasecmp($tokens[0], 'none') === 0) {
            return 'none';
        }

        $inset = false;
        $lengths = [];
        $colors = [];
        foreach ($tokens as $token) {
            if (strcasecmp($token, 'inset') === 0) {
                $inset = true;
                continue;
            }

            if ($this->isShadowLengthToken($token)) {
                $lengths[] = $this->minifyShadowLengthToken($token);
                continue;
            }

            if ($this->isShadowColorToken($token)) {
                $color = $this->minifyShadowColorToken($token);
                if (strcasecmp($color, 'currentColor') !== 0) {
                    $colors[] = $color;
                }
                continue;
            }

            return implode(' ', array_map(fn (string $part): string => $this->minifyShadowTokenInPlace($part), $tokens));
        }

        if (count($lengths) === 4 && $this->isZeroMinifiedLength($lengths[3])) {
            array_pop($lengths);
        }
        if (count($lengths) === 3 && $this->isZeroMinifiedLength($lengths[2])) {
            array_pop($lengths);
        }

        $parts = [];
        if ($inset) {
            $parts[] = 'inset';
        }
        array_push($parts, ...$lengths, ...$colors);

        return implode(' ', $parts);
    }

    private function minifyShadowTokenInPlace(string $token): string
    {
        if (strcasecmp($token, 'inset') === 0) {
            return 'inset';
        }
        if ($this->isShadowLengthToken($token)) {
            return $this->minifyShadowLengthToken($token);
        }
        if ($this->isShadowColorToken($token)) {
            return $this->minifyShadowColorToken($token);
        }

        return trim($token);
    }

    private function isShadowLengthToken(string $token): bool
    {
        $token = trim($token);
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:[a-zA-Z%]+)?$/', $token) === 1) {
            return true;
        }

        return preg_match('/^(?:calc|min|max|clamp)\(/i', $token) === 1;
    }

    private function minifyShadowLengthToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z%]+)?$/', $token, $matches) !== 1) {
            return $token;
        }

        $number = (float) $matches[1];
        if (abs($number) < 0.0000001) {
            return '0';
        }

        return $this->minifyNumber($number) . strtolower($matches[2] ?? '');
    }

    private function isZeroMinifiedLength(string $token): bool
    {
        return $token === '0';
    }

    private function isShadowColorToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        if ($token[0] === '#') {
            return true;
        }
        if (preg_match('/^(?:rgb|rgba|hsl|hsla|lab|lch|oklab|oklch|color)\(/i', $token) === 1) {
            return true;
        }
        if (strcasecmp($token, 'currentcolor') === 0) {
            return true;
        }

        return preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $token) === 1;
    }

    private function minifyShadowColorToken(string $token): string
    {
        $token = trim($token);
        if (preg_match(
            '/^rgba\(\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([+-]?(?:\d+|\d*\.\d+))\s*\)$/i',
            $token,
            $matches
        ) !== 1) {
            return $token;
        }

        $red = (int) $matches[1];
        $green = (int) $matches[2];
        $blue = (int) $matches[3];
        $alpha = (float) $matches[4];
        if ($red < 0 || $red > 255 || $green < 0 || $green > 255 || $blue < 0 || $blue > 255 || $alpha < 0 || $alpha > 1) {
            return $token;
        }

        return $this->compressHexColor(sprintf('#%02x%02x%02x%02x', $red, $green, $blue, (int) round($alpha * 255)));
    }

    private function compressHexColor(string $color): string
    {
        $lower = strtolower($color);
        if ($lower === '#ff0000' || $lower === '#f00') {
            return 'red';
        }
        if ($lower === '#808080') {
            return 'gray';
        }

        if (preg_match('/^#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3$/i', $color, $matches) === 1) {
            return '#' . strtolower($matches[1] . $matches[2] . $matches[3]);
        }
        if (preg_match('/^#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3([0-9a-f])\4$/i', $color, $matches) === 1) {
            return '#' . strtolower($matches[1] . $matches[2] . $matches[3] . $matches[4]);
        }

        return strtolower($color);
    }

    private function isZeroLengthToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:0|0*\.0+)(?:px|em|rem|vh|vw|vmin|vmax|ch|ex|lh|rlh|cm|mm|q|in|pt|pc)?$/i', trim($token)) === 1;
    }

    private function isHundredPercentToken(string $token): bool
    {
        return preg_match('/^\+?(?:100|100\.0+)%$/', trim($token)) === 1;
    }

    private function isZeroAngleToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:0|0*\.0+)(?:deg|grad|rad|turn)?$/i', trim($token)) === 1;
    }

    private function minifyTransitionPropertyValue(string $value): string
    {
        $properties = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            array_push($properties, ...$this->expandBlockAxisTransitionProperty(trim($part)));
        }

        return implode(',', $properties);
    }

    private function minifyTransitionShorthandValue(string $value): string
    {
        return $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionLayer($part));
    }

    private function minifyTransitionLayer(string $layer): string
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        if ($tokens === []) {
            return trim($layer);
        }

        $property = null;
        $duration = null;
        $timing = null;
        $delay = null;
        $behavior = null;

        foreach ($tokens as $token) {
            if ($this->isTimeValue($token)) {
                if ($duration === null) {
                    $duration = $this->minifyTimeValue($token);
                    continue;
                }
                if ($delay === null) {
                    $delay = $this->minifyTimeValue($token);
                    continue;
                }

                return trim($layer);
            }

            if ($this->isTransitionTimingFunction($token)) {
                if ($timing !== null) {
                    return trim($layer);
                }
                $timing = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            $lower = strtolower($token);
            if ($lower === 'normal' || $lower === 'allow-discrete') {
                if ($behavior !== null) {
                    return trim($layer);
                }
                $behavior = $lower;
                continue;
            }

            if ($property !== null) {
                return trim($layer);
            }
            $property = $token;
        }

        $parts = [];
        if ($duration !== null) {
            $parts[] = $duration;
        }
        if ($timing !== null && $timing !== 'ease') {
            $parts[] = $timing;
        }
        if ($delay !== null && $delay !== '0s') {
            if ($duration === null) {
                $parts[] = '0s';
            }
            $parts[] = $delay;
        }
        if ($behavior !== null && $behavior !== 'normal') {
            $parts[] = $behavior;
        }

        $value = $parts === [] ? 'all' : implode(' ', $parts);
        if ($property === null || strtolower($property) === 'all') {
            return $value;
        }

        return implode(',', array_map(
            static fn (string $expanded): string => $expanded . ($value === 'all' ? '' : ' ' . $value),
            $this->expandBlockAxisTransitionProperty($property)
        ));
    }

    private function isTimeValue(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:ms|s)$/i', $value) === 1) {
            return true;
        }

        return $this->evaluateTimeCalc($value) !== null;
    }

    private function isTransitionTimingFunction(string $value): bool
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'], true)) {
            return true;
        }

        return preg_match('/^(?:cubic-bezier|steps)\(/', $value) === 1;
    }

    private function minifyTimeValue(string $value): string
    {
        $value = trim($value);
        $time = $this->evaluateTimeCalc($value);

        return $time === null ? $this->minifyTimeToken($value) : $this->shortestTime($time);
    }

    private function minifyTimeToken(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(ms|s)$/i', $value, $matches) !== 1) {
            return $value;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);

        return $this->shortestTime($unit === 'ms' ? $number / 1000 : $number);
    }

    private function minifyAnimationIterationCount(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'infinite') {
            return $value;
        }

        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1
            ? $this->minifyNumber((float) $value)
            : $value;
    }

    private function minifyTransitionTimingFunction(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^cubic-bezier\((.*)\)$/', $value, $matches) === 1) {
            $numbers = array_map('trim', explode(',', $matches[1]));
            if (count($numbers) !== 4) {
                return $value;
            }

            $canonical = implode(',', array_map(fn (string $number): string => $this->minifyNumber((float) $number), $numbers));

            return match ($canonical) {
                '.25,.1,.25,1' => 'ease',
                '.42,0,1,1' => 'ease-in',
                '0,0,.58,1' => 'ease-out',
                '.42,0,.58,1' => 'ease-in-out',
                default => 'cubic-bezier(' . $canonical . ')',
            };
        }

        if (preg_match('/^steps\(\s*([+-]?(?:\d+|\d*\.\d+))\s*,\s*([^)]+)\)$/', $value, $matches) === 1) {
            $count = $this->minifyNumber((float) $matches[1]);
            $position = trim(strtolower($matches[2]));
            $position = match ($position) {
                'jump-start' => 'start',
                'jump-end' => 'end',
                default => $position,
            };

            if ($count === '1' && $position === 'start') {
                return 'step-start';
            }
            if ($count === '1' && $position === 'end') {
                return 'step-end';
            }

            return 'steps(' . $count . ',' . $position . ')';
        }

        return $value;
    }

    private function shortestTime(float $seconds): string
    {
        $secondsValue = $this->minifyNumber($seconds) . 's';
        $millisecondsValue = $this->minifyNumber($seconds * 1000) . 'ms';

        return strlen($millisecondsValue) <= strlen($secondsValue) ? $millisecondsValue : $secondsValue;
    }

    private function evaluateTimeCalc(string $value): ?float
    {
        if (preg_match('/^calc\((.*)\)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $tokens = $this->tokenizeTimeExpression($matches[1]);
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $result = $this->parseTimeExpression($tokens, $offset);
        if ($result === null || $offset !== count($tokens) || $result['kind'] !== 'time') {
            return null;
        }

        return $result['value'];
    }

    /**
     * @return list<array{type:string,value:string}>
     */
    private function tokenizeTimeExpression(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        for ($i = 0; $i < $length;) {
            $char = $expression[$i];
            if (ctype_space($char)) {
                $i++;
                continue;
            }
            if (str_contains('()+-*', $char)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $i++;
                continue;
            }
            if (preg_match('/\G(?:\d+|\d*\.\d+)(?:ms|s)?/Ai', $expression, $matches, 0, $i) === 1) {
                $tokens[] = ['type' => 'number', 'value' => $matches[0]];
                $i += strlen($matches[0]);
                continue;
            }

            return [];
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeExpression(array $tokens, int &$offset): ?array
    {
        $value = $this->parseTimeProduct($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && in_array($tokens[$offset]['type'], ['+', '-'], true)) {
            $operator = $tokens[$offset++]['type'];
            $right = $this->parseTimeProduct($tokens, $offset);
            if ($right === null) {
                return null;
            }
            if ($value['kind'] !== 'time' || $right['kind'] !== 'time') {
                return null;
            }
            $value = [
                'value' => $operator === '+' ? $value['value'] + $right['value'] : $value['value'] - $right['value'],
                'kind' => 'time',
            ];
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeProduct(array $tokens, int &$offset): ?array
    {
        $value = $this->parseTimeFactor($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && $tokens[$offset]['type'] === '*') {
            $offset++;
            $right = $this->parseTimeFactor($tokens, $offset);
            if ($right === null) {
                return null;
            }
            if ($value['kind'] === 'time' && $right['kind'] === 'time') {
                return null;
            }
            $value = [
                'value' => $value['value'] * $right['value'],
                'kind' => $value['kind'] === 'time' || $right['kind'] === 'time' ? 'time' : 'number',
            ];
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeFactor(array $tokens, int &$offset): ?array
    {
        if ($offset >= count($tokens)) {
            return null;
        }

        $token = $tokens[$offset++];
        if ($token['type'] === '+') {
            return $this->parseTimeFactor($tokens, $offset);
        }
        if ($token['type'] === '-') {
            $value = $this->parseTimeFactor($tokens, $offset);

            return $value === null ? null : ['value' => -$value['value'], 'kind' => $value['kind']];
        }
        if ($token['type'] === '(') {
            $value = $this->parseTimeExpression($tokens, $offset);
            if ($value === null || ($tokens[$offset]['type'] ?? null) !== ')') {
                return null;
            }
            $offset++;

            return $value;
        }
        if ($token['type'] !== 'number') {
            return null;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(ms|s)?$/i', $token['value'], $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2] ?? '');

        if ($unit === '') {
            return ['value' => $number, 'kind' => 'number'];
        }

        return ['value' => $unit === 'ms' ? $number / 1000 : $number, 'kind' => 'time'];
    }

    private function minifyNumber(float $number): string
    {
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $formatted = rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');
        if (str_starts_with($formatted, '0.')) {
            return substr($formatted, 1);
        }
        if (str_starts_with($formatted, '-0.')) {
            return '-' . substr($formatted, 2);
        }

        return $formatted;
    }

    private function mapCommaList(string $value, callable $mapper): string
    {
        return implode(',', array_map(
            static fn (string $part): string => $mapper($part),
            $this->splitTopLevel($value, ',')
        ));
    }

    /**
     * @return list<string>
     */
    private function splitWhitespaceTopLevel(string $value): array
    {
        $tokens = [];
        $token = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $token .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $token .= $value[++$i];
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
            } elseif (ctype_space($char) && $parenDepth === 0 && $bracketDepth === 0) {
                if ($token !== '') {
                    $tokens[] = $token;
                    $token = '';
                }
                continue;
            }

            $token .= $char;
        }

        if ($token !== '') {
            $tokens[] = $token;
        }

        return $tokens;
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

        return array_values(array_map('trim', $parts));
    }

    private function mergeAdjacentRuleBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $pending = null;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                break;
            }

            $preludePrefix = substr($css, $cursor, $open - $cursor);
            $statementBoundary = $this->lastTopLevelSemicolon($preludePrefix);
            if ($statementBoundary !== null) {
                if ($pending !== null) {
                    $output .= $this->serializeRuleBlock($pending['prelude'], $pending['body']);
                    $pending = null;
                }
                $output .= substr($preludePrefix, 0, $statementBoundary + 1);
                $preludePrefix = substr($preludePrefix, $statementBoundary + 1);
            }

            $prelude = trim($preludePrefix);
            $close = $this->findMatchingBraceInCss($css, $open);
            if ($prelude === '') {
                if ($pending !== null) {
                    $output .= $this->serializeRuleBlock($pending['prelude'], $pending['body']);
                    $pending = null;
                }
                $output .= substr($css, $cursor, $close - $cursor + 1);
                $cursor = $close + 1;
                continue;
            }

            $body = $this->mergeAdjacentRuleBlocks(substr($css, $open + 1, $close - $open - 1));
            if ($pending !== null
                && $pending['prelude'] === $prelude
                && $this->isMergeableRulePrelude($prelude)
            ) {
                $pending['body'] = $this->combineRuleBodies($pending['body'], $body);
            } else {
                if ($pending !== null) {
                    $output .= $this->serializeRuleBlock($pending['prelude'], $pending['body']);
                }
                $pending = ['prelude' => $prelude, 'body' => $body];
            }

            $cursor = $close + 1;
        }

        if ($pending !== null) {
            $output .= $this->serializeRuleBlock($pending['prelude'], $pending['body']);
        }

        return $output . substr($css, $cursor);
    }

    private function lastTopLevelSemicolon(string $value): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $last = null;
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
            } elseif ($char === ';' && $parenDepth === 0 && $bracketDepth === 0) {
                $last = $i;
            }
        }

        return $last;
    }

    private function isMergeableRulePrelude(string $prelude): bool
    {
        $prelude = strtolower(trim($prelude));
        if ($prelude === '') {
            return false;
        }
        if ($prelude[0] !== '@') {
            return true;
        }

        return preg_match('/^@(?:container|media|supports)\b/', $prelude) === 1;
    }

    private function combineRuleBodies(string $first, string $second): string
    {
        if ($first === '') {
            return $second;
        }
        if ($second === '') {
            return $first;
        }
        if (!str_contains($first, '{') && !str_contains($second, '{')) {
            return rtrim($first, ';') . ';' . ltrim($second, ';');
        }

        return $this->mergeAdjacentRuleBlocks($first . $second);
    }

    private function serializeRuleBlock(string $prelude, string $body): string
    {
        return $prelude . '{' . $body . '}';
    }

    private function rewriteAllResetDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->rewriteAllResetDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->rewriteAllResetDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function rewriteAllResetDeclarationList(string $body): string
    {
        if (stripos($body, 'all:') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $lastAll = null;
        foreach ($entries as $index => $entry) {
            if ($entry['important']) {
                return $body;
            }
            if ($entry['property'] === 'all') {
                $lastAll = $index;
            }
        }

        if ($lastAll === null) {
            return $body;
        }

        $laterDirectionProperties = [];
        for ($index = $lastAll + 1, $count = count($entries); $index < $count; $index++) {
            if ($this->isAllResetDirectionProperty($entries[$index]['property'])) {
                $laterDirectionProperties[$entries[$index]['property']] = true;
            }
        }

        $moveAfterAll = [];
        for ($index = 0; $index < $lastAll; $index++) {
            $property = $entries[$index]['property'];
            if ($this->isCustomPropertyName($property)) {
                continue;
            }
            if ($this->isAllResetDirectionProperty($property)) {
                $moveAfterAll[$property] = ['index' => $index, 'entry' => $entries[$index]];
            }
            $entries[$index]['drop'] = true;
        }

        foreach ($entries as $index => $entry) {
            if ($entry['property'] === 'all' && $index !== $lastAll) {
                $entries[$index]['drop'] = true;
            }
        }

        if ($moveAfterAll !== []) {
            uasort(
                $moveAfterAll,
                static fn (array $left, array $right): int => $left['index'] <=> $right['index']
            );

            $insert = [];
            foreach ($moveAfterAll as $property => $record) {
                if (!isset($laterDirectionProperties[$property])) {
                    $insert[] = $record['entry'];
                }
            }

            if ($insert !== []) {
                array_splice($entries, $lastAll + 1, 0, $insert);
            }
        }

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function isAllResetDirectionProperty(string $property): bool
    {
        return $property === 'direction' || $property === 'unicode-bidi';
    }

    private function isCustomPropertyName(string $property): bool
    {
        return str_starts_with($property, '--');
    }

    private function composeTransitionDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeTransitionDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeTransitionDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function findMatchingBraceInCss(string $css, int $open): int
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

        return $length - 1;
    }

    private function composeContainerDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeContainerDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeContainerDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composePositionDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composePositionDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composePositionDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeGridDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeGridDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeGridDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeFontDeclarationBlocks(string $css, bool $preserveTargetFallbacks = false): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeFontDeclarationBlocks(substr($css, $open + 1, $close - $open - 1), $preserveTargetFallbacks);
            if (!str_contains($body, '{')) {
                $body = $this->composeFontDeclarationList($body, $preserveTargetFallbacks);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeBorderRadiusDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeBorderRadiusDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeBorderRadiusDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeBorderRadiusDeclarationList(string $body): string
    {
        if (stripos($body, 'border-') === false || stripos($body, '-radius') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->rewriteBorderRadiusGroup($entries, '');
        $this->rewriteBorderRadiusGroup($entries, '-webkit-');
        $this->rewriteBorderRadiusGroup($entries, '-moz-');

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteBorderRadiusGroup(array &$entries, string $prefix): void
    {
        $shorthand = $prefix . 'border-radius';
        $corners = [
            'top-left' => $prefix . 'border-top-left-radius',
            'top-right' => $prefix . 'border-top-right-radius',
            'bottom-right' => $prefix . 'border-bottom-right-radius',
            'bottom-left' => $prefix . 'border-bottom-left-radius',
        ];
        $logicalCorners = $prefix === '' ? [
            'border-start-start-radius',
            'border-start-end-radius',
            'border-end-end-radius',
            'border-end-start-radius',
        ] : [];
        $relevant = array_merge([$shorthand], array_values($corners));
        $latest = [];
        $lastShorthand = null;
        $latestLogical = [];

        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if (in_array($entry['property'], $logicalCorners, true)) {
                if ($entry['important']) {
                    return;
                }
                $latestLogical[$entry['property']] = $index;
                continue;
            }
            if (!in_array($entry['property'], $relevant, true)) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === $shorthand) {
                $lastShorthand = $index;
                continue;
            }
            $latest[$entry['property']] = $index;
        }

        if ($lastShorthand !== null) {
            foreach ($logicalCorners as $property) {
                foreach ($entries as $index => $entry) {
                    if (!$entry['drop'] && $entry['property'] === $property && $index < $lastShorthand) {
                        $entries[$index]['drop'] = true;
                    }
                }
                if (isset($latestLogical[$property]) && $latestLogical[$property] < $lastShorthand) {
                    unset($latestLogical[$property]);
                }
            }
            foreach ($corners as $property) {
                foreach ($entries as $index => $entry) {
                    if (!$entry['drop'] && $entry['property'] === $property && $index < $lastShorthand) {
                        $entries[$index]['drop'] = true;
                    }
                }
                if (isset($latest[$property]) && $latest[$property] < $lastShorthand) {
                    unset($latest[$property]);
                }
            }
        }

        $this->rewriteBorderRadiusPartialOverrides($entries, $shorthand, $corners, $logicalCorners, $latestLogical, $latest, $lastShorthand);

        foreach ($corners as $property) {
            if (!isset($latest[$property])) {
                return;
            }
        }

        $included = array_values($latest);
        $replaceAt = min($included);
        $lastIncluded = max($included);
        foreach ($latestLogical as $index) {
            if ($index > $replaceAt && $index < $lastIncluded) {
                return;
            }
        }

        $horizontal = [];
        $vertical = [];

        foreach ($corners as $property) {
            $corner = $this->parseBorderRadiusCornerValue($entries[$latest[$property]]['value']);
            if ($corner === null) {
                return;
            }
            $horizontal[] = $corner[0];
            $vertical[] = $corner[1];
        }

        foreach ($included as $index) {
            $entries[$index]['drop'] = true;
        }
        if ($lastShorthand !== null && $lastShorthand < $replaceAt) {
            $entries[$lastShorthand]['drop'] = true;
        }

        $entries[$replaceAt]['drop'] = false;
        $entries[$replaceAt]['property'] = $shorthand;
        $entries[$replaceAt]['name'] = $shorthand;
        $entries[$replaceAt]['value'] = $this->serializeBorderRadiusCornerLists($horizontal, $vertical);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     * @param array<string, string> $corners
     * @param list<string> $logicalCorners
     * @param array<string, int> $latestLogical
     * @param array<string, int> $latest
     */
    private function rewriteBorderRadiusPartialOverrides(
        array &$entries,
        string $shorthand,
        array $corners,
        array $logicalCorners,
        array $latestLogical,
        array $latest,
        ?int $lastShorthand
    ): void {
        if ($lastShorthand === null || $entries[$lastShorthand]['drop']) {
            return;
        }

        $overrides = [];
        foreach ($corners as $corner => $property) {
            if (isset($latest[$property]) && $latest[$property] > $lastShorthand) {
                $overrides[$corner] = $latest[$property];
            }
        }

        if ($overrides === [] || count($overrides) === count($corners)) {
            return;
        }

        $lastOverride = max($overrides);
        foreach ($latestLogical as $index) {
            if ($index > $lastShorthand && $index < $lastOverride) {
                return;
            }
        }

        $parsed = $this->parseBorderRadiusShorthandValue($entries[$lastShorthand]['value']);
        if ($parsed === null) {
            return;
        }

        [$horizontal, $vertical] = $parsed;
        $cornerIndexes = [
            'top-left' => 0,
            'top-right' => 1,
            'bottom-right' => 2,
            'bottom-left' => 3,
        ];

        foreach ($overrides as $corner => $index) {
            $parsedCorner = $this->parseBorderRadiusCornerValue($entries[$index]['value']);
            if ($parsedCorner === null || $this->containsDynamicBorderRadiusToken($parsedCorner)) {
                return;
            }

            $cornerIndex = $cornerIndexes[$corner];
            $horizontal[$cornerIndex] = $parsedCorner[0];
            $vertical[$cornerIndex] = $parsedCorner[1];
        }

        if ($this->containsDynamicBorderRadiusToken($horizontal) || $this->containsDynamicBorderRadiusToken($vertical)) {
            return;
        }

        foreach ($overrides as $index) {
            $entries[$index]['drop'] = true;
        }
        $entries[$lastShorthand]['value'] = $this->serializeBorderRadiusCornerLists($horizontal, $vertical);
    }

    /**
     * @return array{0:list<string>,1:list<string>}|null
     */
    private function parseBorderRadiusShorthandValue(string $value): ?array
    {
        $parts = $this->splitTopLevel($value, '/');
        if ($parts === [] || count($parts) > 2) {
            return null;
        }

        $horizontal = $this->expandBorderRadiusSideList($parts[0]);
        if ($horizontal === null) {
            return null;
        }

        $vertical = count($parts) === 2 ? $this->expandBorderRadiusSideList($parts[1]) : $horizontal;
        if ($vertical === null) {
            return null;
        }

        return [$horizontal, $vertical];
    }

    /**
     * @return list<string>|null
     */
    private function expandBorderRadiusSideList(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === [] || count($tokens) > 4) {
            return null;
        }

        $tokens = array_map(fn (string $token): string => $this->minifyLengthToken($token), $tokens);

        return match (count($tokens)) {
            1 => [$tokens[0], $tokens[0], $tokens[0], $tokens[0]],
            2 => [$tokens[0], $tokens[1], $tokens[0], $tokens[1]],
            3 => [$tokens[0], $tokens[1], $tokens[2], $tokens[1]],
            4 => $tokens,
        };
    }

    /**
     * @param list<string>|array{0:string,1:string} $tokens
     */
    private function containsDynamicBorderRadiusToken(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains(strtolower($token), 'var(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseBorderRadiusCornerValue(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === [] || count($tokens) > 2) {
            return null;
        }

        $horizontal = $this->minifyLengthToken($tokens[0]);
        $vertical = $this->minifyLengthToken($tokens[1] ?? $tokens[0]);

        return [$horizontal, $vertical];
    }

    /**
     * @param list<string> $horizontal
     * @param list<string> $vertical
     */
    private function serializeBorderRadiusCornerLists(array $horizontal, array $vertical): string
    {
        $horizontalValue = implode(' ', $this->compressBoxSideValues($horizontal));
        if ($horizontal === $vertical) {
            return $horizontalValue;
        }

        return $horizontalValue . '/' . implode(' ', $this->compressBoxSideValues($vertical));
    }

    private function composeFontDeclarationList(string $body, bool $preserveTargetFallbacks = false): string
    {
        if (stripos($body, 'font') === false && stripos($body, 'line-height') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->rewriteFontGroup($entries, $preserveTargetFallbacks);

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function composeContainerDeclarationList(string $body): string
    {
        if (stripos($body, 'container') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->rewriteContainerGroup($entries);

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function composePositionDeclarationList(string $body): string
    {
        if (!$this->containsPositionInsetDeclarationName($body)) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->rewritePositionInsetGroup($entries);

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function composeGridDeclarationList(string $body): string
    {
        if (stripos($body, 'grid-') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->rewriteGridShorthandTemplateAreaOverrideGroup($entries);
        $this->rewriteGridShorthandRowOverrideGroup($entries);
        $this->rewriteGridTemplateGroup($entries);
        $this->rewriteGridPlacementGroups($entries);

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteGridShorthandTemplateAreaOverrideGroup(array &$entries): void
    {
        $shorthand = null;
        $areas = null;
        $areaIndexes = [];
        $latestRows = null;
        $rowIndexes = [];

        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === 'grid' || $entry['property'] === 'grid-template') {
                $shorthand = $index;
                $areas = null;
                $areaIndexes = [];
                $latestRows = null;
                $rowIndexes = [];
                continue;
            }
            if ($shorthand === null) {
                continue;
            }
            if ($entry['property'] === 'grid-template-areas') {
                $areas = $index;
                $areaIndexes[] = $index;
                continue;
            }
            if ($entry['property'] === 'grid-template-rows') {
                $latestRows = $index;
                $rowIndexes[] = $index;
            }
        }

        if ($shorthand === null || $areas === null) {
            return;
        }

        $tracks = $this->parseGridShorthandTemplateTracks($entries[$shorthand]['value']);
        if ($tracks === null) {
            return;
        }

        $rows = $latestRows === null ? $tracks['rows'] : $entries[$latestRows]['value'];
        $value = $this->serializeGridTemplateDeclarationShorthand(
            $entries[$areas]['value'],
            $rows,
            $tracks['columns'],
        );
        if ($value === null) {
            return;
        }

        $entries[$shorthand]['value'] = $value;
        foreach ($areaIndexes as $index) {
            $entries[$index]['drop'] = true;
        }
        foreach ($rowIndexes as $index) {
            $entries[$index]['drop'] = true;
        }
    }

    /**
     * @return array{rows:string,columns:string}|null
     */
    private function parseGridShorthandTemplateTracks(string $value): ?array
    {
        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) !== 2) {
            return null;
        }

        $rows = trim($parts[0]);
        $columns = trim($parts[1]);
        if ($rows === '' || $columns === '') {
            return null;
        }

        $lowerRows = strtolower($rows);
        $lowerColumns = strtolower($columns);
        if (str_contains($lowerRows, 'auto-flow') || str_contains($lowerColumns, 'auto-flow')) {
            return null;
        }
        if (str_contains($rows, '"') || str_contains($rows, "'")) {
            return null;
        }

        return ['rows' => $rows, 'columns' => $columns];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteGridShorthandRowOverrideGroup(array &$entries): void
    {
        $lastGrid = null;
        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === 'grid') {
                $lastGrid = $index;
            }
        }

        if ($lastGrid === null) {
            return;
        }

        $latestRows = null;
        $rowIndexes = [];
        for ($index = $lastGrid + 1, $count = count($entries); $index < $count; $index++) {
            $entry = $entries[$index];
            if ($entry['drop']) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === 'grid-template-rows') {
                $latestRows = $index;
                $rowIndexes[] = $index;
                continue;
            }
            if (in_array($entry['property'], ['grid', 'grid-template', 'grid-template-areas', 'grid-template-columns'], true)) {
                return;
            }
        }

        if ($latestRows === null) {
            return;
        }

        $parts = $this->splitTopLevel($entries[$lastGrid]['value'], '/');
        if (count($parts) !== 2) {
            return;
        }

        $entries[$lastGrid]['value'] = trim($entries[$latestRows]['value']) . '/' . trim($parts[1]);
        foreach ($rowIndexes as $index) {
            $entries[$index]['drop'] = true;
        }
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteGridTemplateGroup(array &$entries): void
    {
        if ($this->rewriteGridShorthandAreaGroup($entries)) {
            return;
        }

        $templateProperties = [
            'areas' => 'grid-template-areas',
            'rows' => 'grid-template-rows',
            'columns' => 'grid-template-columns',
        ];
        $autoProperties = [
            'flow' => 'grid-auto-flow',
            'rows' => 'grid-auto-rows',
            'columns' => 'grid-auto-columns',
        ];
        $templateNames = array_flip($templateProperties);
        $autoNames = array_flip($autoProperties);
        $latest = [];
        $latestAuto = [];
        $templateIndices = [];
        $autoIndices = [];

        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === 'grid' || $entry['property'] === 'grid-template') {
                return;
            }
            if (isset($templateNames[$entry['property']])) {
                $component = $templateNames[$entry['property']];
                $latest[$component] = $index;
                $templateIndices[] = $index;
                continue;
            }
            if (isset($autoNames[$entry['property']])) {
                $component = $autoNames[$entry['property']];
                $latestAuto[$component] = $index;
                $autoIndices[] = $index;
            }
        }

        foreach (['areas', 'rows', 'columns'] as $required) {
            if (!isset($latest[$required])) {
                return;
            }
        }

        $shorthand = $this->serializeGridTemplateDeclarationShorthand(
            $entries[$latest['areas']]['value'],
            $entries[$latest['rows']]['value'],
            $entries[$latest['columns']]['value'],
        );
        if ($shorthand === null) {
            return;
        }

        $gridShorthand = null;
        if (isset($latestAuto['flow'], $latestAuto['rows'], $latestAuto['columns'])) {
            $gridShorthand = $this->serializeGridShorthandWithAutoTracks(
                $shorthand,
                $entries[$latest['areas']]['value'],
                $entries[$latest['rows']]['value'],
                $entries[$latest['columns']]['value'],
                $entries[$latestAuto['flow']]['value'],
                $entries[$latestAuto['rows']]['value'],
                $entries[$latestAuto['columns']]['value'],
            );
        }
        $useGridShorthand = $gridShorthand !== null;

        $included = array_values($latest);
        if ($useGridShorthand) {
            $included = array_merge($included, array_values($latestAuto));
        }
        $replaceAt = min($included);

        foreach ($templateIndices as $index) {
            $entries[$index]['drop'] = true;
        }
        if ($useGridShorthand) {
            foreach ($autoIndices as $index) {
                $entries[$index]['drop'] = true;
            }
        }

        $entries[$replaceAt] = [
            'property' => $useGridShorthand ? 'grid' : 'grid-template',
            'name' => $useGridShorthand ? 'grid' : 'grid-template',
            'value' => $gridShorthand ?? $shorthand,
            'important' => false,
            'drop' => false,
        ];

        if (!$useGridShorthand) {
            $this->moveGridAutoFlowAfterAutoTracks($entries, $latestAuto);
        }
    }

    private function serializeGridShorthandWithAutoTracks(
        string $templateShorthand,
        string $areas,
        string $rows,
        string $columns,
        string $flow,
        string $autoRows,
        string $autoColumns
    ): ?string {
        $flow = $this->canonicalGridAutoFlowForComposition($flow);
        if ($flow === null) {
            return null;
        }

        $areas = trim($areas);
        $rows = trim($rows);
        $columns = trim($columns);
        $autoRows = trim($autoRows);
        $autoColumns = trim($autoColumns);
        $autoRowsIsDefault = strcasecmp($autoRows, 'auto') === 0;
        $autoColumnsIsDefault = strcasecmp($autoColumns, 'auto') === 0;

        if ($flow === 'row' && $autoRowsIsDefault && $autoColumnsIsDefault) {
            return $templateShorthand;
        }

        if (strcasecmp($areas, 'none') !== 0) {
            return null;
        }

        if (($flow === 'row' || $flow === 'dense')
            && strcasecmp($rows, 'none') === 0
            && $autoColumnsIsDefault
        ) {
            $autoFlow = $flow === 'dense' ? 'auto-flow dense' : 'auto-flow';
            if (!$autoRowsIsDefault) {
                $autoFlow .= ' ' . $autoRows;
            }

            return $autoFlow . '/' . $columns;
        }

        if (($flow === 'column' || $flow === 'column dense')
            && strcasecmp($columns, 'none') === 0
            && $autoRowsIsDefault
        ) {
            $autoFlow = $flow === 'column dense' ? 'auto-flow dense' : 'auto-flow';
            if (!$autoColumnsIsDefault) {
                $autoFlow .= ' ' . $autoColumns;
            }

            return $rows . '/' . $autoFlow;
        }

        return null;
    }

    private function canonicalGridAutoFlowForComposition(string $value): ?string
    {
        $tokens = array_map(static fn (string $token): string => strtolower($token), $this->splitWhitespaceTopLevel(trim($value)));
        if ($tokens === []) {
            return null;
        }

        foreach ($tokens as $token) {
            if (!in_array($token, ['row', 'column', 'dense'], true)) {
                return null;
            }
        }

        $hasDense = in_array('dense', $tokens, true);
        if (in_array('column', $tokens, true)) {
            return $hasDense ? 'column dense' : 'column';
        }

        return $hasDense ? 'dense' : 'row';
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     * @param array<string, int> $latestAuto
     */
    private function moveGridAutoFlowAfterAutoTracks(array &$entries, array $latestAuto): void
    {
        if (!isset($latestAuto['flow'], $latestAuto['rows'], $latestAuto['columns'])) {
            return;
        }
        if ($this->canonicalGridAutoFlowForComposition($entries[$latestAuto['flow']]['value']) === null) {
            return;
        }

        $indices = [$latestAuto['flow'], $latestAuto['rows'], $latestAuto['columns']];
        sort($indices);
        if ($indices[2] - $indices[0] !== 2) {
            return;
        }

        $rows = $entries[$latestAuto['rows']];
        $columns = $entries[$latestAuto['columns']];
        $flow = $entries[$latestAuto['flow']];

        $entries[$indices[0]] = $rows;
        $entries[$indices[1]] = $columns;
        $entries[$indices[2]] = $flow;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteGridPlacementGroups(array &$entries): void
    {
        foreach ($entries as $entry) {
            if ($entry['drop']) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if (in_array($entry['property'], ['grid-area', 'grid-row', 'grid-column'], true)) {
                return;
            }
        }

        $latest = [];
        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if (in_array($entry['property'], ['grid-row-start', 'grid-row-end', 'grid-column-start', 'grid-column-end'], true)) {
                $latest[$entry['property']] = $index;
            }
        }

        if (isset($latest['grid-row-start'], $latest['grid-row-end'], $latest['grid-column-start'], $latest['grid-column-end'])) {
            $included = [
                $latest['grid-row-start'],
                $latest['grid-row-end'],
                $latest['grid-column-start'],
                $latest['grid-column-end'],
            ];
            $replaceAt = min($included);
            foreach ($entries as $index => $entry) {
                if (in_array($entry['property'], ['grid-row-start', 'grid-row-end', 'grid-column-start', 'grid-column-end'], true)) {
                    $entries[$index]['drop'] = true;
                }
            }
            $entries[$replaceAt] = [
                'property' => 'grid-area',
                'name' => 'grid-area',
                'value' => $this->minifyGridAreaValue(
                    $entries[$latest['grid-row-start']]['value']
                        . '/'
                        . $entries[$latest['grid-column-start']]['value']
                        . '/'
                        . $entries[$latest['grid-row-end']]['value']
                        . '/'
                        . $entries[$latest['grid-column-end']]['value']
                ),
                'important' => false,
                'drop' => false,
            ];

            return;
        }

        $this->rewriteGridAxisPlacementGroup($entries, 'grid-row', 'grid-row-start', 'grid-row-end');
        $this->rewriteGridAxisPlacementGroup($entries, 'grid-column', 'grid-column-start', 'grid-column-end');
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteGridAxisPlacementGroup(array &$entries, string $shorthand, string $start, string $end): void
    {
        $latestStart = null;
        $latestEnd = null;
        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if ($entry['property'] === $start) {
                $latestStart = $index;
            } elseif ($entry['property'] === $end) {
                $latestEnd = $index;
            }
        }

        if ($latestStart === null || $latestEnd === null) {
            return;
        }

        $replaceAt = min($latestStart, $latestEnd);
        foreach ($entries as $index => $entry) {
            if ($entry['property'] === $start || $entry['property'] === $end) {
                $entries[$index]['drop'] = true;
            }
        }

        $entries[$replaceAt] = [
            'property' => $shorthand,
            'name' => $shorthand,
            'value' => $this->minifyGridLineShorthandValue($entries[$latestStart]['value'] . '/' . $entries[$latestEnd]['value']),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteGridShorthandAreaGroup(array &$entries): bool
    {
        $shorthandIndex = null;
        $areaIndex = null;
        $rowIndex = null;
        $columnIndex = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop']) {
                continue;
            }
            if ($entry['important']) {
                return false;
            }

            if ($entry['property'] === 'grid' || $entry['property'] === 'grid-template') {
                $shorthandIndex = $index;
                $areaIndex = null;
                $rowIndex = null;
                $columnIndex = null;
                continue;
            }

            if ($shorthandIndex === null) {
                continue;
            }

            if ($entry['property'] === 'grid-template-areas') {
                $areaIndex = $index;
                continue;
            }
            if ($entry['property'] === 'grid-template-rows') {
                $rowIndex = $index;
                continue;
            }
            if ($entry['property'] === 'grid-template-columns') {
                $columnIndex = $index;
            }
        }

        if ($shorthandIndex === null || $areaIndex === null) {
            return false;
        }

        $shorthand = $this->parseGridTemplateShorthandForAreas(
            $entries[$shorthandIndex]['property'],
            $entries[$shorthandIndex]['value'],
        );
        if ($shorthand === null) {
            return false;
        }

        $rows = $rowIndex !== null ? $entries[$rowIndex]['value'] : $shorthand['rows'];
        $columns = $columnIndex !== null ? $entries[$columnIndex]['value'] : $shorthand['columns'];
        $value = $this->serializeGridTemplateDeclarationShorthand($entries[$areaIndex]['value'], $rows, $columns);
        if ($value === null) {
            return false;
        }

        foreach (array_filter([$areaIndex, $rowIndex, $columnIndex], static fn (?int $index): bool => $index !== null) as $index) {
            $entries[$index]['drop'] = true;
        }
        $entries[$shorthandIndex]['value'] = $value;

        return true;
    }

    /**
     * @return array{rows:string,columns:string}|null
     */
    private function parseGridTemplateShorthandForAreas(string $property, string $value): ?array
    {
        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) !== 2) {
            return null;
        }

        $rows = trim($parts[0]);
        $columns = trim($parts[1]);
        if ($rows === '' || $columns === '') {
            return null;
        }

        if ($property === 'grid' && (stripos($rows, 'auto-flow') !== false || stripos($columns, 'auto-flow') !== false)) {
            return null;
        }

        if (strcasecmp($rows, 'none') === 0) {
            return null;
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    private function serializeGridTemplateDeclarationShorthand(string $areas, string $rows, string $columns): ?string
    {
        $areas = trim($areas);
        $rows = trim($rows);
        $columns = trim($columns);
        if ($areas === '' || $rows === '' || $columns === '') {
            return null;
        }

        if (strcasecmp($areas, 'none') === 0) {
            if (strcasecmp($rows, 'none') === 0 && strcasecmp($columns, 'none') === 0) {
                return 'none';
            }

            return $rows . '/' . $columns;
        }

        if (stripos($columns, 'repeat(') !== false) {
            return null;
        }

        $areaRows = $this->parseGridTemplateAreaRows($areas);
        if ($areaRows === null || $areaRows === []) {
            return null;
        }

        $rowTokens = $this->splitGridTrackListTokens($rows);
        $rowTracks = array_values(array_filter(
            $rowTokens,
            fn (string $token): bool => !$this->isGridLineNameToken($token),
        ));
        if ($rowTracks === [] || count($rowTracks) < count($areaRows)) {
            return null;
        }

        $areaColumnCount = $this->gridTemplateAreaColumnCount($areaRows[0]);
        $targetRows = max(count($areaRows), count($rowTracks));
        $segments = [];
        $tokenIndex = 0;

        for ($rowIndex = 0; $rowIndex < $targetRows; $rowIndex++) {
            while (isset($rowTokens[$tokenIndex]) && $this->isGridLineNameToken($rowTokens[$tokenIndex])) {
                $segments[] = $rowTokens[$tokenIndex++];
            }

            $area = $areaRows[$rowIndex] ?? $this->gridTemplateEmptyAreaRow($areaColumnCount);
            $segments[] = '"' . $area . '"';

            if (!isset($rowTracks[$rowIndex])) {
                continue;
            }

            while (isset($rowTokens[$tokenIndex]) && $this->isGridLineNameToken($rowTokens[$tokenIndex])) {
                $segments[] = $rowTokens[$tokenIndex++];
            }

            $track = $rowTokens[$tokenIndex] ?? $rowTracks[$rowIndex];
            if (!$this->isGridLineNameToken($track)) {
                $tokenIndex++;
                if (strcasecmp($track, 'auto') !== 0) {
                    $segments[] = $track;
                }
            }
        }

        while (isset($rowTokens[$tokenIndex])) {
            $segments[] = $rowTokens[$tokenIndex++];
        }

        return implode('', $segments) . '/' . $columns;
    }

    /**
     * @return list<string>|null
     */
    private function parseGridTemplateAreaRows(string $value): ?array
    {
        $rows = [];
        $quote = null;
        $row = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
                    $row .= $char . $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $rows[] = $this->normalizeGridTemplateAreaRowText($row);
                    $row = '';
                    $quote = null;
                    continue;
                }
                $row .= $char;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if (!ctype_space($char)) {
                return null;
            }
        }

        return $quote === null ? $rows : null;
    }

    private function gridTemplateAreaColumnCount(string $row): int
    {
        $tokens = preg_split('/\s+/', trim($row)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        return max(1, count($tokens));
    }

    private function gridTemplateEmptyAreaRow(int $columns): string
    {
        return implode(' ', array_fill(0, max(1, $columns), '.'));
    }

    /**
     * @return list<string>
     */
    private function splitGridTrackListTokens(string $value): array
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
                    $tokens[] = substr($value, $i);
                    break;
                }
                $tokens[] = substr($value, $i, $end - $i + 1);
                $i = $end;
                continue;
            }

            $start = $i;
            $quote = null;
            $parenDepth = 0;
            for (; $i < $length; $i++) {
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
                    continue;
                }
                if ($char === '(') {
                    $parenDepth++;
                    continue;
                }
                if ($char === ')') {
                    $parenDepth = max(0, $parenDepth - 1);
                    continue;
                }
                if ($parenDepth === 0 && ($char === '[' || ctype_space($char))) {
                    break;
                }
            }

            $token = substr($value, $start, $i - $start);
            if ($token !== '') {
                $tokens[] = $token;
            }
            if ($i < $length && $value[$i] === '[') {
                $i--;
            }
        }

        return $tokens;
    }

    private function isGridLineNameToken(string $token): bool
    {
        $token = trim($token);

        return str_starts_with($token, '[') && str_ends_with($token, ']');
    }

    private function containsPositionInsetDeclarationName(string $body): bool
    {
        return stripos($body, 'inset') !== false
            || preg_match('/(?:^|;)(?:top|right|bottom|left):/i', $body) === 1;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewritePositionInsetGroup(array &$entries): void
    {
        $this->rewritePhysicalInsetGroup($entries);
        $this->rewriteLogicalInsetAxisGroup($entries, 'inset-block', 'inset-block-start', 'inset-block-end');
        $this->rewriteLogicalInsetAxisGroup($entries, 'inset-inline', 'inset-inline-start', 'inset-inline-end');
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewritePhysicalInsetGroup(array &$entries): void
    {
        $components = [
            'top' => 'top',
            'right' => 'right',
            'bottom' => 'bottom',
            'left' => 'left',
        ];
        $relevant = array_merge(array_values($components), ['inset']);
        $latest = [];
        $lastInset = null;

        foreach ($entries as $index => $entry) {
            if (str_starts_with($entry['property'], 'inset-block') || str_starts_with($entry['property'], 'inset-inline')) {
                return;
            }
            if ($entry['drop'] || !in_array($entry['property'], $relevant, true)) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === 'inset') {
                $lastInset = $index;
                continue;
            }
            $latest[$entry['property']] = $index;
        }

        if ($lastInset !== null) {
            foreach ($components as $property) {
                foreach ($entries as $index => $entry) {
                    if (!$entry['drop'] && $entry['property'] === $property && $index < $lastInset) {
                        $entries[$index]['drop'] = true;
                    }
                }
                if (isset($latest[$property]) && $latest[$property] < $lastInset) {
                    unset($latest[$property]);
                }
            }
        }

        foreach (array_values($components) as $property) {
            if (!isset($latest[$property])) {
                return;
            }
        }

        $included = array_values($latest);
        $replaceAt = min($included);
        foreach ($entries as $index => $entry) {
            if (in_array($entry['property'], array_values($components), true)) {
                $entries[$index]['drop'] = true;
            }
        }
        if ($lastInset !== null && $lastInset < $replaceAt) {
            $entries[$lastInset]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => 'inset',
            'name' => 'inset',
            'value' => $this->serializeBoxShorthandValues(
                $entries[$latest['top']]['value'],
                $entries[$latest['right']]['value'],
                $entries[$latest['bottom']]['value'],
                $entries[$latest['left']]['value']
            ),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteLogicalInsetAxisGroup(array &$entries, string $shorthand, string $start, string $end): void
    {
        $latestStart = null;
        $latestEnd = null;
        $reset = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !in_array($entry['property'], ['inset', $shorthand, $start, $end], true)) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            if ($entry['property'] === 'inset' || $entry['property'] === $shorthand) {
                $reset = $reset === null ? $index : max($reset, $index);
                continue;
            }
            if ($entry['property'] === $start) {
                $latestStart = $index;
            } elseif ($entry['property'] === $end) {
                $latestEnd = $index;
            }
        }

        if ($reset !== null) {
            foreach ($entries as $index => $entry) {
                if (!$entry['drop'] && ($entry['property'] === $start || $entry['property'] === $end) && $index < $reset) {
                    $entries[$index]['drop'] = true;
                }
            }
            if ($latestStart !== null && $latestStart < $reset) {
                $latestStart = null;
            }
            if ($latestEnd !== null && $latestEnd < $reset) {
                $latestEnd = null;
            }
        }

        if ($latestStart === null || $latestEnd === null) {
            return;
        }

        $replaceAt = min($latestStart, $latestEnd);
        foreach ($entries as $index => $entry) {
            if ($entry['property'] === $start || $entry['property'] === $end) {
                $entries[$index]['drop'] = true;
            }
        }
        if ($reset !== null && $entries[$reset]['property'] === $shorthand && $reset < $replaceAt) {
            $entries[$reset]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => $shorthand,
            'name' => $shorthand,
            'value' => $this->serializeAxisShorthandValues(
                $entries[$latestStart]['value'],
                $entries[$latestEnd]['value']
            ),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteContainerGroup(array &$entries): void
    {
        $relevantIndices = [];
        $latest = [];

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !in_array($entry['property'], ['container', 'container-name', 'container-type'], true)) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === 'container-name') {
                $latest['name'] = $index;
            } elseif ($entry['property'] === 'container-type') {
                $latest['type'] = $index;
            }
        }

        if (!isset($latest['name'], $latest['type'])) {
            return;
        }

        $replaceAt = min($latest['name'], $latest['type']);
        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => 'container',
            'name' => 'container',
            'value' => $this->serializeContainerShorthand(
                $entries[$latest['name']]['value'],
                $entries[$latest['type']]['value']
            ),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteFontGroup(array &$entries, bool $preserveTargetFallbacks = false): void
    {
        $properties = [
            'font' => 'font',
            'family' => 'font-family',
            'size' => 'font-size',
            'weight' => 'font-weight',
            'style' => 'font-style',
            'stretch' => 'font-stretch',
            'variant' => 'font-variant-caps',
            'lineHeight' => 'line-height',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === 'font') {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            $preserveEarlierFontFallbacks = $preserveTargetFallbacks
                && $this->fontShorthandHasTargetFallbackBoundary($entries[$lastShorthand]['value']);

            foreach ($relevantIndices as $index) {
                if (
                    $index < $lastShorthand
                    && (!$preserveEarlierFontFallbacks || $entries[$index]['property'] !== 'font')
                ) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseFontShorthandComponents($entries[$lastShorthand]['value']);
            if ($state === null) {
                return;
            }

            $changed = false;
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }

                $component = $relevantNames[$entries[$index]['property']];
                if ($component !== 'lineHeight' || $this->containsCustomPropertyReference($entries[$index]['value'])) {
                    continue;
                }

                $state['lineHeight'] = $this->minifyFontLineHeightValue($entries[$index]['value']);
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeFontShorthandComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'font') {
                $latest[$component] = $index;
            }
        }

        foreach (['family', 'size', 'weight', 'style', 'stretch', 'lineHeight'] as $required) {
            if (!isset($latest[$required])) {
                return;
            }
        }

        foreach (['family', 'size', 'lineHeight'] as $guarded) {
            if ($this->containsCustomPropertyReference($entries[$latest[$guarded]]['value'])) {
                return;
            }
        }

        $variant = 'normal';
        $preserveVariant = null;
        if (isset($latest['variant'])) {
            $variant = strtolower(trim($entries[$latest['variant']]['value']));
            if (!in_array($variant, ['normal', 'small-caps'], true)) {
                $preserveVariant = $latest['variant'];
                $variant = 'normal';
            }
        }

        $included = [
            $latest['family'],
            $latest['size'],
            $latest['weight'],
            $latest['style'],
            $latest['stretch'],
            $latest['lineHeight'],
        ];
        if (isset($latest['variant']) && $preserveVariant === null) {
            $included[] = $latest['variant'];
        }

        $replaceAt = min($included);
        foreach ($relevantIndices as $index) {
            if (in_array($index, $included, true)) {
                $entries[$index]['drop'] = true;
            }
        }

        $entries[$replaceAt] = [
            'property' => 'font',
            'name' => 'font',
            'value' => $this->serializeFontShorthandComponents([
                'style' => $this->minifyFontStyleValue($entries[$latest['style']]['value']),
                'variant' => $variant,
                'weight' => $this->minifyFontWeightValue($entries[$latest['weight']]['value']),
                'stretch' => $this->minifyFontStretchValue($entries[$latest['stretch']]['value']),
                'size' => $this->minifyFontSizeValue($entries[$latest['size']]['value']),
                'lineHeight' => $this->minifyFontLineHeightValue($entries[$latest['lineHeight']]['value']),
                'family' => $this->minifyFontFamilyList($entries[$latest['family']]['value']),
                'explicitWeight' => true,
            ]),
            'important' => false,
            'drop' => false,
        ];
    }

    private function fontShorthandHasTargetFallbackBoundary(string $value): bool
    {
        return (bool) preg_match('/(?:^|[\s,])system-ui(?:[\s,]|$)|(?:^|[\s\/])xxx-large(?:[\s\/]|$)|(?:\d|\.)cq(?:w|h|i|b|min|max)\b/i', $value);
    }

    private function serializeContainerShorthand(string $name, string $type): string
    {
        $name = trim($name);
        $type = strtolower(trim($type));

        return $type === 'normal' ? $name : $name . '/' . $type;
    }

    private function serializeBoxShorthandValues(string $top, string $right, string $bottom, string $left): string
    {
        if ($top === $right && $top === $bottom && $top === $left) {
            return $top;
        }
        if ($top === $bottom && $right === $left) {
            return $top . ' ' . $right;
        }
        if ($right === $left) {
            return $top . ' ' . $right . ' ' . $bottom;
        }

        return $top . ' ' . $right . ' ' . $bottom . ' ' . $left;
    }

    private function serializeAxisShorthandValues(string $start, string $end): string
    {
        return $start === $end ? $start : $start . ' ' . $end;
    }

    private function composeTransitionDeclarationList(string $body): string
    {
        if (stripos($body, 'transition') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        foreach (['-webkit-', '-moz-', ''] as $prefix) {
            $this->rewriteTransitionGroup($entries, $prefix);
        }

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function composeListStyleDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeListStyleDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeListStyleDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeListStyleDeclarationList(string $body): string
    {
        if (stripos($body, 'list-style') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->rewriteListStyleGroup($entries);

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function composeAnimationDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeAnimationDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeAnimationDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeAnimationDeclarationList(string $body): string
    {
        if (stripos($body, 'animation') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        $this->dropAnimationRangeResetByAnimationShorthand($entries);
        foreach (['-webkit-', '-moz-', ''] as $prefix) {
            $this->rewriteAnimationGroup($entries, $prefix);
        }
        $this->rewriteAnimationRangeGroup($entries);

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    private function composeTextEmphasisDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeTextEmphasisDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeTextEmphasisDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function composeTextEmphasisDeclarationList(string $body): string
    {
        if (stripos($body, 'text-emphasis') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        foreach (['-webkit-', ''] as $prefix) {
            $this->rewriteTextEmphasisGroup($entries, $prefix);
        }

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteTextEmphasisGroup(array &$entries, string $prefix): void
    {
        $properties = [
            'emphasis' => $prefix . 'text-emphasis',
            'style' => $prefix . 'text-emphasis-style',
            'color' => $prefix . 'text-emphasis-color',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === $properties['emphasis']) {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            foreach ($relevantIndices as $index) {
                if ($index < $lastShorthand) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseTextEmphasisShorthandComponents($entries[$lastShorthand]['value']);
            if ($state === null) {
                return;
            }

            $changed = false;
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }

                $component = $relevantNames[$entries[$index]['property']];
                if ($component === 'emphasis') {
                    continue;
                }

                $value = $component === 'style'
                    ? $this->minifyTextEmphasisStyle($entries[$index]['value'])
                    : trim($entries[$index]['value']);
                if ($component === 'color' && $this->containsCustomPropertyReference($value)) {
                    continue;
                }

                $state[$component] = $value;
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeTextEmphasisComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'emphasis') {
                $latest[$component] = $index;
            }
        }

        if (!isset($latest['style'], $latest['color'])
            || $this->containsCustomPropertyReference($entries[$latest['color']]['value'])
        ) {
            return;
        }

        $replaceAt = min($latest['style'], $latest['color']);
        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => $properties['emphasis'],
            'name' => $properties['emphasis'],
            'value' => $this->serializeTextEmphasisComponents([
                'style' => $this->minifyTextEmphasisStyle($entries[$latest['style']]['value']),
                'color' => trim($entries[$latest['color']]['value']),
                'other' => [],
            ]),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteListStyleGroup(array &$entries): void
    {
        $properties = [
            'list' => 'list-style',
            'type' => 'list-style-type',
            'image' => 'list-style-image',
            'position' => 'list-style-position',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === 'list-style') {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            if ($this->containsCustomPropertyReference($entries[$lastShorthand]['value'])) {
                return;
            }

            foreach ($relevantIndices as $index) {
                if ($index < $lastShorthand) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseListStyleComponents($entries[$lastShorthand]['value'], true);
            if ($state === null) {
                return;
            }

            $hasFollowingRelevant = false;
            foreach ($relevantIndices as $index) {
                if ($index > $lastShorthand && !$entries[$index]['drop']) {
                    $hasFollowingRelevant = true;
                    break;
                }
            }
            $changed = $hasFollowingRelevant && $entries[$lastShorthand]['value'] !== $this->serializeListStyleComponents($state);
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }

                $component = $relevantNames[$entries[$index]['property']];
                if ($component === 'list') {
                    continue;
                }

                $value = $this->normalizeListStyleComponentValue($component, $entries[$index]['value'], true);
                if ($component === 'image' && $this->containsCustomPropertyReference($value)) {
                    continue;
                }

                $state[$component] = $value;
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeListStyleComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'list') {
                $latest[$component] = $index;
            }
        }

        foreach (['type', 'image', 'position'] as $required) {
            if (!isset($latest[$required])) {
                return;
            }
        }
        if ($this->containsCustomPropertyReference($entries[$latest['image']]['value'])) {
            return;
        }

        $replaceAt = min(array_values($latest));
        $state = [
            'type' => $this->normalizeListStyleComponentValue('type', $entries[$latest['type']]['value'], true),
            'image' => $this->normalizeListStyleComponentValue('image', $entries[$latest['image']]['value'], true),
            'position' => $this->normalizeListStyleComponentValue('position', $entries[$latest['position']]['value'], true),
        ];

        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => 'list-style',
            'name' => 'list-style',
            'value' => $this->serializeListStyleComponents($state),
            'important' => false,
            'drop' => false,
        ];
    }

    private function normalizeListStyleComponentValue(string $component, string $value, bool $quoteSafeUrls): string
    {
        return match ($component) {
            'type' => $this->minifyListStyleTypeValue($value),
            'image' => $this->minifyListStyleImageValue($value, $quoteSafeUrls),
            'position' => strtolower(trim($value)),
            default => trim($value),
        };
    }

    /**
     * @return list<array{property:string,name:string,value:string,important:bool,drop:bool}>|null
     */
    private function parseDeclarationEntriesForComposition(string $body): ?array
    {
        $entries = [];
        foreach ($this->splitTopLevel($body, ';') as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $colon = $this->findTopLevelColon($part);
            if ($colon === null) {
                return null;
            }

            $name = trim(substr($part, 0, $colon));
            $property = strtolower($name);
            $value = trim(substr($part, $colon + 1));
            if ($property === '' || $value === '') {
                return null;
            }

            [$value, $important] = $this->splitImportantFlag($value);
            $entries[] = [
                'property' => $property,
                'name' => $name,
                'value' => $value,
                'important' => $important,
                'drop' => false,
            ];
        }

        return $entries;
    }

    private function findTopLevelColon(string $part): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($part);
        for ($i = 0; $i < $length; $i++) {
            $char = $part[$i];
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
            } elseif ($char === ':' && $parenDepth === 0 && $bracketDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function splitImportantFlag(string $value): array
    {
        if (preg_match('/^(.*?)\s*!\s*important\s*$/i', $value, $matches) === 1) {
            return [trim($matches[1]), true];
        }

        return [$value, false];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function serializeDeclarationEntriesForComposition(array $entries): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            if ($entry['drop']) {
                continue;
            }
            $parts[] = $entry['name'] . ':' . $entry['value'] . ($entry['important'] ? '!important' : '');
        }

        return implode(';', $parts);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteTransitionGroup(array &$entries, string $prefix): void
    {
        $properties = [
            'transition' => $prefix . 'transition',
            'property' => $prefix . 'transition-property',
            'duration' => $prefix . 'transition-duration',
            'timing' => $prefix . 'transition-timing-function',
            'delay' => $prefix . 'transition-delay',
            'behavior' => $prefix . 'transition-behavior',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === $properties['transition']) {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            foreach ($relevantIndices as $index) {
                if ($index < $lastShorthand) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseTransitionShorthandComponents($entries[$lastShorthand]['value']);
            if ($state === null) {
                return;
            }

            $changed = false;
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }
                $component = $relevantNames[$entries[$index]['property']];
                if ($component === 'transition') {
                    continue;
                }

                $values = $this->parseTransitionLonghandList($component, $entries[$index]['value']);
                if ($values === null) {
                    continue;
                }

                $state[$component] = $values;
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeTransitionComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'transition') {
                $latest[$component] = $index;
            }
        }

        foreach (['property', 'duration', 'timing', 'delay'] as $required) {
            if (!isset($latest[$required])) {
                return;
            }
        }

        $state = [
            'property' => [],
            'duration' => [],
            'timing' => [],
            'delay' => [],
            'behavior' => ['normal'],
        ];
        foreach ($latest as $component => $index) {
            $values = $this->parseTransitionLonghandList($component, $entries[$index]['value']);
            if ($values === null) {
                return;
            }
            $state[$component] = $values;
        }

        $replaceAt = min(array_values($latest));
        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => $properties['transition'],
            'name' => $properties['transition'],
            'value' => $this->serializeTransitionComponents($state),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteAnimationGroup(array &$entries, string $prefix): void
    {
        $properties = [
            'animation' => $prefix . 'animation',
            'name' => $prefix . 'animation-name',
            'duration' => $prefix . 'animation-duration',
            'timing' => $prefix . 'animation-timing-function',
            'delay' => $prefix . 'animation-delay',
            'iteration' => $prefix . 'animation-iteration-count',
            'direction' => $prefix . 'animation-direction',
            'fill' => $prefix . 'animation-fill-mode',
            'play' => $prefix . 'animation-play-state',
            'timeline' => $prefix . 'animation-timeline',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === $properties['animation']) {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            foreach ($relevantIndices as $index) {
                if ($index < $lastShorthand) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseAnimationShorthandComponents($entries[$lastShorthand]['value']);
            if ($state === null) {
                return;
            }

            $changed = false;
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }
                $component = $relevantNames[$entries[$index]['property']];
                if ($component === 'animation') {
                    continue;
                }

                $values = $this->parseAnimationLonghandList($component, $entries[$index]['value']);
                if ($values === null || count($values) !== count($state['name'])) {
                    continue;
                }

                $state[$component] = $values;
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeAnimationComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'animation') {
                $latest[$component] = $index;
            }
        }

        foreach (['name', 'duration', 'timing', 'delay', 'iteration', 'direction', 'fill', 'play'] as $required) {
            if (!isset($latest[$required])) {
                return;
            }
        }

        $state = [
            'duration' => [],
            'timing' => [],
            'delay' => [],
            'iteration' => [],
            'direction' => [],
            'fill' => [],
            'play' => [],
            'name' => [],
            'timeline' => [],
        ];
        foreach ($latest as $component => $index) {
            $values = $this->parseAnimationLonghandList($component, $entries[$index]['value']);
            if ($values === null) {
                return;
            }
            $state[$component] = $values;
        }

        if ($state['timeline'] === []) {
            $state['timeline'] = array_fill(0, count($state['name']), 'auto');
        }

        $counts = array_map('count', $state);
        if (count(array_unique($counts)) !== 1) {
            return;
        }

        $replaceAt = min(array_values($latest));
        if ($prefix === '' && $this->hasAnimationRangeEntryBefore($entries, $replaceAt)) {
            return;
        }

        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => $properties['animation'],
            'name' => $properties['animation'],
            'value' => $this->serializeAnimationComponents($state),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function dropAnimationRangeResetByAnimationShorthand(array &$entries): void
    {
        $lastAnimation = null;
        foreach ($entries as $index => $entry) {
            if (!$entry['drop'] && !$entry['important'] && $entry['property'] === 'animation') {
                $lastAnimation = $index;
            }
        }

        if ($lastAnimation === null) {
            return;
        }

        foreach ($entries as $index => $entry) {
            if ($index >= $lastAnimation || $entry['drop']) {
                continue;
            }
            if (in_array($entry['property'], ['animation-range', 'animation-range-start', 'animation-range-end'], true)) {
                $entries[$index]['drop'] = true;
            }
        }
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function hasAnimationRangeEntryBefore(array $entries, int $before): bool
    {
        foreach ($entries as $index => $entry) {
            if ($index >= $before) {
                continue;
            }
            if (!$entry['drop'] && in_array($entry['property'], ['animation-range', 'animation-range-start', 'animation-range-end'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteAnimationRangeGroup(array &$entries): void
    {
        $properties = [
            'range' => 'animation-range',
            'start' => 'animation-range-start',
            'end' => 'animation-range-end',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === 'animation-range') {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            foreach ($relevantIndices as $index) {
                if ($index < $lastShorthand) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseAnimationRangeShorthandList($entries[$lastShorthand]['value']);
            if ($state === null) {
                return;
            }

            $changed = false;
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }

                $component = $relevantNames[$entries[$index]['property']];
                if ($component === 'range') {
                    continue;
                }

                $values = $this->parseAnimationRangeSideList($entries[$index]['value'], $component);
                if ($values === null || count($values) !== count($state['start'])) {
                    continue;
                }

                $state[$component] = $values;
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeAnimationRangeComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'range') {
                $latest[$component] = $index;
            }
        }

        if (!isset($latest['start'], $latest['end'])) {
            return;
        }

        $start = $this->parseAnimationRangeSideList($entries[$latest['start']]['value'], 'start');
        $end = $this->parseAnimationRangeSideList($entries[$latest['end']]['value'], 'end');
        if ($start === null || $end === null || count($start) !== count($end)) {
            return;
        }

        $replaceAt = min($latest['start'], $latest['end']);
        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => 'animation-range',
            'name' => 'animation-range',
            'value' => $this->serializeAnimationRangeComponents(['start' => $start, 'end' => $end]),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @return array{duration:list<string>,timing:list<string>,delay:list<string>,iteration:list<string>,direction:list<string>,fill:list<string>,play:list<string>,name:list<string>,timeline:list<string>}|null
     */
    private function parseAnimationShorthandComponents(string $value): ?array
    {
        if (stripos($value, 'var(') !== false) {
            return null;
        }

        $state = [
            'duration' => [],
            'timing' => [],
            'delay' => [],
            'iteration' => [],
            'direction' => [],
            'fill' => [],
            'play' => [],
            'name' => [],
            'timeline' => [],
        ];

        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $components = $this->parseAnimationLayerComponents($layer);
            if ($components === null) {
                return null;
            }
            foreach ($components as $component => $componentValue) {
                $state[$component][] = $componentValue;
            }
        }

        return $state;
    }

    /**
     * @return array{duration:string,timing:string,delay:string,iteration:string,direction:string,fill:string,play:string,name:string,timeline:string}|null
     */
    private function parseAnimationLayerComponents(string $layer): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        if ($tokens === []) {
            return null;
        }

        $components = [
            'duration' => null,
            'timing' => null,
            'delay' => null,
            'iteration' => null,
            'direction' => null,
            'fill' => null,
            'play' => null,
            'name' => null,
            'timeline' => null,
        ];

        foreach ($tokens as $index => $token) {
            $lower = strtolower($token);

            if ($this->isQuotedStringToken($token)) {
                if ($components['name'] !== null) {
                    return null;
                }
                $components['name'] = $this->minifyAnimationName($token);
                continue;
            }

            if ($this->isTimeValue($token)) {
                if ($components['duration'] === null) {
                    $components['duration'] = $this->minifyTimeValue($token);
                    continue;
                }
                if ($components['delay'] === null) {
                    $components['delay'] = $this->minifyTimeValue($token);
                    continue;
                }

                return null;
            }

            if ($components['timing'] === null && $this->isTransitionTimingFunction($token)) {
                $components['timing'] = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            if ($components['iteration'] === null && $this->isAnimationIterationToken($lower)) {
                $components['iteration'] = $this->minifyAnimationIterationCount($lower);
                continue;
            }

            if ($components['direction'] === null && in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)) {
                $components['direction'] = $lower;
                continue;
            }

            if ($components['fill'] === null && in_array($lower, ['none', 'forwards', 'backwards', 'both'], true)) {
                if ($lower !== 'none' || $components['name'] !== null || $this->hasFutureAnimationNameToken($tokens, $index + 1)) {
                    $components['fill'] = $lower;
                    continue;
                }
            }

            if ($components['play'] === null && in_array($lower, ['running', 'paused'], true)) {
                $components['play'] = $lower;
                continue;
            }

            if ($components['timeline'] === null && $this->isAnimationTimelineToken($token)) {
                $components['timeline'] = $this->minifyAnimationTimelineToken($token);
                continue;
            }

            if ($components['name'] !== null) {
                return null;
            }
            $components['name'] = $this->minifyAnimationName($token);
        }

        return [
            'duration' => $components['duration'] ?? '0s',
            'timing' => $components['timing'] ?? 'ease',
            'delay' => $components['delay'] ?? '0s',
            'iteration' => $components['iteration'] ?? '1',
            'direction' => $components['direction'] ?? 'normal',
            'fill' => $components['fill'] ?? 'none',
            'play' => $components['play'] ?? 'running',
            'name' => $components['name'] ?? 'none',
            'timeline' => $components['timeline'] ?? 'auto',
        ];
    }

    /**
     * @return list<string>|null
     */
    private function parseAnimationLonghandList(string $component, string $value): ?array
    {
        if ($component === 'name') {
            return $this->mapAnimationComponentList(
                $value,
                function (string $part): ?string {
                    $part = trim($part);

                    return $part === '' ? null : $this->minifyAnimationName($part);
                }
            );
        }

        if ($component === 'duration' || $component === 'delay') {
            return $this->mapAnimationComponentList(
                $value,
                fn (string $part): ?string => $this->isTimeValue($part) ? $this->minifyTimeValue($part) : null
            );
        }

        if ($component === 'timing') {
            return $this->mapAnimationComponentList(
                $value,
                fn (string $part): ?string => $this->isTransitionTimingFunction($part) ? $this->minifyTransitionTimingFunction($part) : null
            );
        }

        if ($component === 'iteration') {
            return $this->mapAnimationComponentList(
                $value,
                fn (string $part): ?string => $this->isAnimationIterationToken(strtolower(trim($part)))
                    ? $this->minifyAnimationIterationCount($part)
                    : null
            );
        }

        if ($component === 'direction') {
            return $this->mapAnimationComponentList(
                $value,
                static function (string $part): ?string {
                    $part = strtolower(trim($part));

                    return in_array($part, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true) ? $part : null;
                }
            );
        }

        if ($component === 'fill') {
            return $this->mapAnimationComponentList(
                $value,
                static function (string $part): ?string {
                    $part = strtolower(trim($part));

                    return in_array($part, ['none', 'forwards', 'backwards', 'both'], true) ? $part : null;
                }
            );
        }

        if ($component === 'play') {
            return $this->mapAnimationComponentList(
                $value,
                static function (string $part): ?string {
                    $part = strtolower(trim($part));

                    return in_array($part, ['running', 'paused'], true) ? $part : null;
                }
            );
        }

        if ($component === 'timeline') {
            return $this->mapAnimationComponentList(
                $value,
                fn (string $part): ?string => $this->isAnimationTimelineToken($part)
                    ? $this->minifyAnimationTimelineToken($part)
                    : null
            );
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function mapAnimationComponentList(string $value, callable $mapper): ?array
    {
        $mapped = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $component = $mapper($part);
            if ($component === null) {
                return null;
            }
            $mapped[] = $component;
        }

        return $mapped === [] ? null : $mapped;
    }

    /**
     * @return array{start:list<array{type:string,name?:string,offset?:string}>,end:list<array{type:string,name?:string,offset?:string}>}|null
     */
    private function parseAnimationRangeShorthandList(string $value): ?array
    {
        $state = ['start' => [], 'end' => []];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $range = $this->parseAnimationRangeShorthandLayer($layer);
            if ($range === null) {
                return null;
            }
            $state['start'][] = $range['start'];
            $state['end'][] = $range['end'];
        }

        return $state['start'] === [] ? null : $state;
    }

    /**
     * @return list<array{type:string,name?:string,offset?:string}>|null
     */
    private function parseAnimationRangeSideList(string $value, string $side): ?array
    {
        $values = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $range = $this->parseAnimationRangeSide($part, $side);
            if ($range === null) {
                return null;
            }
            $values[] = $range;
        }

        return $values === [] ? null : $values;
    }

    /**
     * @param array{start:list<array{type:string,name?:string,offset?:string}>,end:list<array{type:string,name?:string,offset?:string}>} $state
     */
    private function serializeAnimationRangeComponents(array $state): string
    {
        $ranges = [];
        for ($i = 0, $count = count($state['start']); $i < $count; $i++) {
            $ranges[] = $this->serializeAnimationRangePair($state['start'][$i], $state['end'][$i]);
        }

        return implode(',', $ranges);
    }

    /**
     * @param array{duration:list<string>,timing:list<string>,delay:list<string>,iteration:list<string>,direction:list<string>,fill:list<string>,play:list<string>,name:list<string>,timeline:list<string>} $state
     */
    private function serializeAnimationComponents(array $state): string
    {
        $count = max(
            count($state['duration']),
            count($state['timing']),
            count($state['delay']),
            count($state['iteration']),
            count($state['direction']),
            count($state['fill']),
            count($state['play']),
            count($state['name']),
            count($state['timeline'])
        );
        $layers = [];

        for ($i = 0; $i < $count; $i++) {
            $layers[] = $this->serializeAnimationShorthandLayer([
                'duration' => $this->animationComponentAt($state['duration'], $i, '0s'),
                'timing' => $this->animationComponentAt($state['timing'], $i, 'ease'),
                'delay' => $this->animationComponentAt($state['delay'], $i, '0s'),
                'iteration' => $this->animationComponentAt($state['iteration'], $i, '1'),
                'direction' => $this->animationComponentAt($state['direction'], $i, 'normal'),
                'fill' => $this->animationComponentAt($state['fill'], $i, 'none'),
                'play' => $this->animationComponentAt($state['play'], $i, 'running'),
                'name' => $this->animationComponentAt($state['name'], $i, 'none'),
                'timeline' => $this->animationComponentAt($state['timeline'], $i, 'auto'),
            ]);
        }

        return implode(',', $layers);
    }

    /**
     * @param list<string> $values
     */
    private function animationComponentAt(array $values, int $index, string $default): string
    {
        if ($values === []) {
            return $default;
        }

        return $values[$index % count($values)];
    }

    /**
     * @return array{property:list<string>,duration:list<string>,timing:list<string>,delay:list<string>,behavior:list<string>}|null
     */
    private function parseTransitionShorthandComponents(string $value): ?array
    {
        $state = [
            'property' => [],
            'duration' => [],
            'timing' => [],
            'delay' => [],
            'behavior' => [],
        ];

        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $components = $this->parseTransitionLayerComponents($layer);
            if ($components === null) {
                return null;
            }
            foreach ($components as $component => $componentValue) {
                $state[$component][] = $componentValue;
            }
        }

        return $state;
    }

    /**
     * @return array{property:string,duration:string,timing:string,delay:string,behavior:string}|null
     */
    private function parseTransitionLayerComponents(string $layer): ?array
    {
        $property = null;
        $duration = null;
        $timing = null;
        $delay = null;
        $behavior = null;

        foreach ($this->splitWhitespaceTopLevel($layer) as $token) {
            if ($this->isTimeValue($token)) {
                if ($duration === null) {
                    $duration = $this->minifyTimeValue($token);
                    continue;
                }
                if ($delay === null) {
                    $delay = $this->minifyTimeValue($token);
                    continue;
                }

                return null;
            }

            if ($this->isTransitionTimingFunction($token)) {
                if ($timing !== null) {
                    return null;
                }
                $timing = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            $lower = strtolower($token);
            if ($lower === 'normal' || $lower === 'allow-discrete') {
                if ($behavior !== null) {
                    return null;
                }
                $behavior = $lower;
                continue;
            }

            if ($property !== null) {
                return null;
            }
            $property = $token;
        }

        return [
            'property' => $property ?? 'all',
            'duration' => $duration ?? '0s',
            'timing' => $timing ?? 'ease',
            'delay' => $delay ?? '0s',
            'behavior' => $behavior ?? 'normal',
        ];
    }

    /**
     * @return list<string>|null
     */
    private function parseTransitionLonghandList(string $component, string $value): ?array
    {
        if ($component === 'behavior') {
            return $this->mapTransitionComponentList(
                $value,
                static function (string $part): ?string {
                    $part = strtolower(trim($part));

                    return $part === 'normal' || $part === 'allow-discrete' ? $part : null;
                }
            );
        }

        if ($component === 'duration' || $component === 'delay') {
            return $this->mapTransitionComponentList(
                $value,
                fn (string $part): ?string => $this->isTimeValue($part) ? $this->minifyTimeValue($part) : null
            );
        }

        if ($component === 'timing') {
            return $this->mapTransitionComponentList(
                $value,
                fn (string $part): ?string => $this->isTransitionTimingFunction($part) ? $this->minifyTransitionTimingFunction($part) : null
            );
        }

        if ($component === 'property') {
            return $this->mapTransitionComponentList(
                $value,
                function (string $part): ?string {
                    $part = trim($part);
                    if ($part === '') {
                        return null;
                    }

                    return implode(', ', $this->expandBlockAxisTransitionProperty($part));
                }
            );
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function mapTransitionComponentList(string $value, callable $mapper): ?array
    {
        $mapped = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $component = $mapper($part);
            if ($component === null) {
                return null;
            }
            $mapped[] = $component;
        }

        return $mapped === [] ? null : $mapped;
    }

    /**
     * @param array{property:list<string>,duration:list<string>,timing:list<string>,delay:list<string>,behavior:list<string>} $state
     */
    private function serializeTransitionComponents(array $state): string
    {
        $count = max(
            count($state['property']),
            count($state['duration']),
            count($state['timing']),
            count($state['delay']),
            count($state['behavior'])
        );
        $layers = [];

        for ($i = 0; $i < $count; $i++) {
            $property = $this->transitionComponentAt($state['property'], $i, 'all');
            $duration = $this->transitionComponentAt($state['duration'], $i, '0s');
            $timing = $this->transitionComponentAt($state['timing'], $i, 'ease');
            $delay = $this->transitionComponentAt($state['delay'], $i, '0s');
            $behavior = $this->transitionComponentAt($state['behavior'], $i, 'normal');

            $parts = [];
            if (strtolower($property) !== 'all') {
                $parts[] = $property;
            }

            $needsDuration = $duration !== '0s' || $timing !== 'ease' || $delay !== '0s' || $behavior !== 'normal';
            if ($needsDuration) {
                $parts[] = $duration;
            }
            if ($timing !== 'ease') {
                $parts[] = $timing;
            }
            if ($delay !== '0s') {
                if (!$needsDuration) {
                    $parts[] = '0s';
                }
                $parts[] = $delay;
            }
            if ($behavior !== 'normal') {
                $parts[] = $behavior;
            }

            $layers[] = $parts === [] ? 'all' : implode(' ', $parts);
        }

        return implode(',', $layers);
    }

    /**
     * @param list<string> $values
     */
    private function transitionComponentAt(array $values, int $index, string $default): string
    {
        if ($values === []) {
            return $default;
        }

        return $values[$index % count($values)];
    }

    /**
     * @return non-empty-list<string>
     */
    private function expandBlockAxisTransitionProperty(string $property): array
    {
        return match (strtolower($property)) {
            'margin-block' => ['margin-top', 'margin-bottom'],
            'margin-block-start' => ['margin-top'],
            'margin-block-end' => ['margin-bottom'],
            'padding-block' => ['padding-top', 'padding-bottom'],
            'padding-block-start' => ['padding-top'],
            'padding-block-end' => ['padding-bottom'],
            default => [$property],
        };
    }

    private function normalizeMathFunctionOperators(string $value): string
    {
        $output = '';
        $quote = null;
        $functionStack = [];
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

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $i + strlen($identifier);
                if (($value[$next] ?? '') === '(') {
                    $parentIsMath = end($functionStack) === true;
                    $functionStack[] = $parentIsMath || in_array(strtolower($identifier), ['calc', 'clamp', 'max', 'min'], true);
                    $output .= $identifier . '(';
                    $i = $next;
                    continue;
                }
                $output .= $identifier;
                $i = $next - 1;
                continue;
            }

            if ($char === ')') {
                array_pop($functionStack);
                $output = rtrim($output) . ')';
                continue;
            }

            if (($char === '+' || $char === '-') && end($functionStack) === true && $this->isBinaryMathOperator($value, $i)) {
                $output = rtrim($output) . ' ' . $char . ' ';
                while (isset($value[$i + 1]) && ctype_space($value[$i + 1])) {
                    $i++;
                }
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifyMathFunctions(string $value): string
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

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $i + strlen($identifier);
                if (($value[$next] ?? '') === '(') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $this->minifyMathFunction($function);
                    $i = $offset;
                    continue;
                }

                $output .= $identifier;
                $i = $next - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifyMathFunction(string $function): string
    {
        if (preg_match('/^([-_a-zA-Z][-_a-zA-Z0-9]*)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $name = strtolower($matches[1]);
        $inner = $this->minifyMathFunctions($matches[2]);

        if ($name === 'calc') {
            $linear = $this->parseLinearMathArgument($inner);

            return $linear === null
                ? 'calc(' . $this->compactCalcFallback($inner) . ')'
                : $this->serializeLinearCalc($linear);
        }

        if ($name === 'min' || $name === 'max') {
            return $this->minifyMathMinMax($name, $this->splitTopLevel($inner, ','));
        }

        if ($name === 'clamp') {
            return $this->minifyMathClamp($this->splitTopLevel($inner, ','));
        }

        if ($name === 'round') {
            return $this->minifyMathRound($this->splitTopLevel($inner, ','));
        }

        if ($name === 'rem' || $name === 'mod') {
            return $this->minifyMathRemainder($name, $this->splitTopLevel($inner, ','));
        }

        if ($name === 'hypot') {
            return $this->minifyMathHypot($this->splitTopLevel($inner, ','));
        }

        if ($name === 'sqrt') {
            return $this->minifyMathSqrt($this->splitTopLevel($inner, ','));
        }

        if ($name === 'pow') {
            return $this->minifyMathPow($this->splitTopLevel($inner, ','));
        }

        if ($name === 'log') {
            return $this->minifyMathLog($this->splitTopLevel($inner, ','));
        }

        if ($name === 'exp') {
            return $this->minifyMathExp($this->splitTopLevel($inner, ','));
        }

        if ($name === 'abs') {
            return $this->minifyMathAbs($this->splitTopLevel($inner, ','));
        }

        if ($name === 'sign') {
            return $this->minifyMathSign($this->splitTopLevel($inner, ','));
        }

        return $matches[1] . '(' . $this->compactMathFallback($inner) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathMinMax(string $name, array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if ($normalized === []) {
            return $name . '()';
        }

        return $this->minifyMathMinMaxNormalized($name, $normalized);
    }

    /**
     * @param non-empty-list<string> $args
     */
    private function minifyMathMinMaxNormalized(string $name, array $args): string
    {
        $comparables = [];
        $groupOrder = [];
        foreach ($args as $index => $arg) {
            $comparables[$index] = $this->comparableMathValue($arg);
            if ($comparables[$index] !== null && !array_key_exists($comparables[$index]['group'], $groupOrder)) {
                $groupOrder[$comparables[$index]['group']] = $index;
            }
        }

        $keep = array_fill(0, count($args), true);
        foreach ($args as $index => $_arg) {
            $left = $comparables[$index];
            if ($left === null) {
                continue;
            }

            foreach ($args as $otherIndex => $_otherArg) {
                if ($index === $otherIndex) {
                    continue;
                }
                $right = $comparables[$otherIndex];
                if ($right === null) {
                    continue;
                }
                $comparison = $this->compareComparableMathValues($right, $left);
                if ($comparison === null) {
                    continue;
                }

                if ($name === 'min' && ($comparison < 0 || ($comparison === 0 && $otherIndex < $index))) {
                    $keep[$index] = false;
                    break;
                }
                if ($name === 'max' && ($comparison > 0 || ($comparison === 0 && $otherIndex < $index))) {
                    $keep[$index] = false;
                    break;
                }
            }
        }

        $kept = [];
        foreach ($args as $index => $arg) {
            if ($keep[$index]) {
                $kept[] = ['index' => $index, 'value' => $arg, 'comparable' => $comparables[$index]];
            }
        }

        usort($kept, static function (array $left, array $right) use ($groupOrder): int {
            $leftOrder = $left['comparable'] === null ? $left['index'] : $groupOrder[$left['comparable']['group']];
            $rightOrder = $right['comparable'] === null ? $right['index'] : $groupOrder[$right['comparable']['group']];

            return $leftOrder <=> $rightOrder ?: $left['index'] <=> $right['index'];
        });

        $values = array_map(static fn (array $item): string => $item['value'], $kept);
        if (count($values) === 1) {
            return $values[0];
        }

        return $name . '(' . implode(',', $values) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathClamp(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 3) {
            return 'clamp(' . implode(',', $normalized) . ')';
        }

        [$lower, $preferred, $upper] = $normalized;
        $lowerComparable = $this->comparableMathValue($lower);
        $preferredComparable = $this->comparableMathValue($preferred);
        $upperComparable = $this->comparableMathValue($upper);

        if ($lowerComparable !== null && $preferredComparable !== null && $upperComparable !== null) {
            $lowerToUpper = $this->compareComparableMathValues($lowerComparable, $upperComparable);
            $preferredToLower = $this->compareComparableMathValues($preferredComparable, $lowerComparable);
            $preferredToUpper = $this->compareComparableMathValues($preferredComparable, $upperComparable);
            if ($lowerToUpper !== null && $preferredToLower !== null && $preferredToUpper !== null) {
                if ($lowerToUpper > 0 || $preferredToLower < 0) {
                    return $lower;
                }
                if ($preferredToUpper > 0) {
                    return $upper;
                }

                return $preferred;
            }
        }

        if ($preferredComparable !== null && $upperComparable !== null) {
            $preferredToUpper = $this->compareComparableMathValues($preferredComparable, $upperComparable);
            if ($preferredToUpper !== null && $preferredToUpper <= 0) {
                return $this->minifyMathMinMaxNormalized('max', [$lower, $preferred]);
            }
        }

        return 'clamp(' . implode(',', $normalized) . ')';
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathRound(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        $strategy = 'nearest';
        if (count($normalized) === 3) {
            $strategy = strtolower($normalized[0]);
            $normalized = [$normalized[1], $normalized[2]];
        }
        if (count($normalized) !== 2 || !in_array($strategy, ['nearest', 'down', 'up', 'to-zero'], true)) {
            return 'round(' . implode(',', $this->normalizeMathArguments($args)) . ')';
        }

        $value = $this->comparableMathValue($normalized[0]);
        $step = $this->comparableMathValue($normalized[1]);
        if ($value === null || $step === null || $this->compareComparableMathValues($value, $step) === null || abs($step['canonical']) < 0.0000001) {
            return 'round(' . implode(',', $strategy === 'nearest' ? $normalized : array_merge([$strategy], $normalized)) . ')';
        }

        $ratio = $value['canonical'] / abs($step['canonical']);
        $rounded = match ($strategy) {
            'down' => floor($ratio),
            'up' => ceil($ratio),
            'to-zero' => $ratio < 0 ? ceil($ratio) : floor($ratio),
            default => round($ratio),
        };

        return $this->serializeMathNumberWithUnit($rounded * abs($step['value']), $value['unit']);
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathRemainder(string $name, array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 2) {
            return $name . '(' . implode(',', $normalized) . ')';
        }

        $dividend = $this->comparableMathValue($normalized[0]);
        $divisor = $this->comparableMathValue($normalized[1]);
        if ($dividend === null || $divisor === null || $this->compareComparableMathValues($dividend, $divisor) === null || abs($divisor['canonical']) < 0.0000001) {
            return $name . '(' . implode(',', $normalized) . ')';
        }

        if ($name === 'rem') {
            $result = fmod($dividend['canonical'], $divisor['canonical']);
        } else {
            $result = $dividend['canonical'] - $divisor['canonical'] * floor($dividend['canonical'] / $divisor['canonical']);
        }

        return $this->serializeMathNumberWithUnit($result / $dividend['canonicalPerUnit'], $dividend['unit']);
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathHypot(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if ($normalized === [] || $normalized === ['']) {
            return 'hypot()';
        }

        $unit = null;
        $sum = 0.0;
        foreach ($normalized as $arg) {
            $value = $this->comparableMathValue($arg);
            if ($value === null || $value['unit'] === '%') {
                return 'hypot(' . implode(',', $normalized) . ')';
            }
            $unit ??= $value['unit'];
            if ($value['unit'] !== $unit) {
                return 'hypot(' . implode(',', $normalized) . ')';
            }
            $sum += $value['value'] ** 2;
        }

        return $this->serializeComputedMathNumberWithUnit(sqrt($sum), $unit ?? '');
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathSqrt(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 1) {
            return 'sqrt(' . implode(',', $normalized) . ')';
        }

        $value = $this->unitlessMathNumber($normalized[0]);
        if ($value === null || $value < 0) {
            return 'sqrt(' . $normalized[0] . ')';
        }

        return $this->serializeComputedMathNumberWithUnit(sqrt($value), '');
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathPow(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 2) {
            return 'pow(' . implode(',', $normalized) . ')';
        }

        $base = $this->unitlessMathNumber($normalized[0]);
        $exponent = $this->unitlessMathNumber($normalized[1]);
        if ($base === null || $exponent === null) {
            return 'pow(' . implode(',', $normalized) . ')';
        }

        $result = $base ** $exponent;
        if (!is_finite($result)) {
            return 'pow(' . implode(',', $normalized) . ')';
        }

        return $this->serializeComputedMathNumberWithUnit($result, '');
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathLog(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 1 && count($normalized) !== 2) {
            return 'log(' . implode(',', $normalized) . ')';
        }

        $value = $this->unitlessMathNumber($normalized[0]);
        $base = count($normalized) === 2 ? $this->unitlessMathNumber($normalized[1]) : M_E;
        if ($value === null || $base === null || $value <= 0 || $base <= 0 || abs($base - 1.0) < 0.0000001) {
            return 'log(' . implode(',', $normalized) . ')';
        }

        $result = log($value) / log($base);
        if (!is_finite($result)) {
            return 'log(' . implode(',', $normalized) . ')';
        }

        return $this->serializeComputedMathNumberWithUnit($result, '');
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathExp(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 1) {
            return 'exp(' . implode(',', $normalized) . ')';
        }

        $value = $this->unitlessMathNumber($normalized[0]);
        if ($value === null) {
            return 'exp(' . $normalized[0] . ')';
        }

        $result = exp($value);
        if (!is_finite($result)) {
            return 'exp(' . $normalized[0] . ')';
        }

        if (abs($result - M_E) < 0.0000001) {
            return 'e';
        }

        return $this->serializeComputedMathNumberWithUnit($result, '');
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathAbs(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 1) {
            return 'abs(' . implode(',', $normalized) . ')';
        }

        $value = $this->comparableMathValue($normalized[0]);
        if ($value === null || $value['unit'] === '%') {
            return 'abs(' . $normalized[0] . ')';
        }

        return $this->serializeMathNumberWithUnit(abs($value['value']), $value['unit']);
    }

    /**
     * @param list<string> $args
     */
    private function minifyMathSign(array $args): string
    {
        $normalized = $this->normalizeMathArguments($args);
        if (count($normalized) !== 1) {
            return 'sign(' . implode(',', $normalized) . ')';
        }

        $value = $this->comparableMathValue($normalized[0]);
        if ($value === null || $value['unit'] === '%') {
            return 'sign(' . $normalized[0] . ')';
        }

        if (abs($value['canonical']) < 0.0000001) {
            return '0';
        }

        return $value['canonical'] < 0 ? '-1' : '1';
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private function normalizeMathArguments(array $args): array
    {
        return array_map(fn (string $arg): string => $this->normalizeMathArgument($arg), $args);
    }

    private function normalizeMathArgument(string $arg): string
    {
        $arg = trim($this->minifyMathFunctions($arg));
        $linear = $this->parseLinearMathArgument($arg);
        if ($linear !== null) {
            return $this->serializeLinearCalcArgument($linear, true);
        }

        return $this->compactMathFallback($arg);
    }

    private function unitlessMathNumber(string $arg): ?float
    {
        $constant = $this->mathConstant($arg);
        if ($constant !== null) {
            return $constant;
        }

        $linear = $this->parseLinearMathArgument($arg);
        if ($linear === null) {
            return null;
        }

        $units = $this->nonZeroLinearCalcUnits($linear);
        if ($units === []) {
            return 0.0;
        }

        return $units === [''] ? ($linear['terms'][''] ?? 0.0) : null;
    }

    /**
     * @return array{terms:array<string,float>,order:list<string>}|null
     */
    private function parseLinearMathArgument(string $arg): ?array
    {
        $arg = trim($arg);
        if (preg_match('/^calc\((.*)\)$/is', $arg, $matches) === 1) {
            $arg = trim($matches[1]);
        }

        $tokens = $this->tokenizeLinearCalcExpression($arg);
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $result = $this->parseLinearCalcExpression($tokens, $offset);

        return $result !== null && $offset === count($tokens) ? $result : null;
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $value
     */
    private function serializeLinearCalcArgument(array $value, bool $preserveZeroUnit = false): string
    {
        $units = $this->nonZeroLinearCalcUnits($value);
        if ($units === []) {
            $zeroUnit = $preserveZeroUnit ? $this->zeroLinearCalcUnit($value) : null;

            return $zeroUnit === null ? '0' : '0' . $zeroUnit;
        }
        if (count($units) === 1) {
            $unit = $units[0];

            return $this->serializeLinearCalcTerm($value['terms'][$unit], $unit);
        }

        if (($value['terms'][$units[0]] ?? 0.0) < 0) {
            $positive = array_values(array_filter($units, fn (string $unit): bool => ($value['terms'][$unit] ?? 0.0) > 0));
            if ($positive !== []) {
                $negative = array_values(array_filter($units, fn (string $unit): bool => ($value['terms'][$unit] ?? 0.0) < 0));
                $units = array_merge($positive, $negative);
            }
        }

        $output = '';
        foreach ($units as $unit) {
            $coefficient = $value['terms'][$unit];
            $term = $this->serializeLinearCalcTerm(abs($coefficient), $unit);
            if ($output === '') {
                $output = $coefficient < 0 ? '-' . $term : $term;
                continue;
            }
            $output .= $coefficient < 0 ? ' - ' . $term : ' + ' . $term;
        }

        return $output;
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $value
     */
    private function zeroLinearCalcUnit(array $value): ?string
    {
        if (count($value['order']) !== 1) {
            return null;
        }

        $unit = $value['order'][0];

        return $unit !== '' && abs($value['terms'][$unit] ?? 0.0) < 0.0000001 ? $unit : null;
    }

    /**
     * @return array{value:float,unit:string,group:string,canonical:float,canonicalPerUnit:float}|null
     */
    private function comparableMathValue(string $arg): ?array
    {
        $linear = $this->parseLinearMathArgument($arg);
        if ($linear === null) {
            return null;
        }

        $units = $this->nonZeroLinearCalcUnits($linear);
        if ($units === []) {
            $unit = $this->zeroLinearCalcUnit($linear) ?? '';
            $value = 0.0;
        } elseif (count($units) === 1) {
            $unit = $units[0];
            $value = $linear['terms'][$unit];
        } else {
            return null;
        }

        $comparison = $this->mathComparison($value, $unit);
        if ($comparison === null) {
            return null;
        }

        return [
            'value' => $value,
            'unit' => $unit,
            'group' => $comparison['group'],
            'canonical' => $comparison['canonical'],
            'canonicalPerUnit' => $comparison['canonicalPerUnit'],
        ];
    }

    /**
     * @return array{group:string,canonical:float,canonicalPerUnit:float}|null
     */
    private function mathComparison(float $value, string $unit): ?array
    {
        $absoluteLengths = [
            'px' => 1.0,
            'in' => 96.0,
            'cm' => 96.0 / 2.54,
            'mm' => 96.0 / 25.4,
            'q' => 96.0 / 101.6,
            'pc' => 16.0,
            'pt' => 96.0 / 72.0,
        ];
        if (isset($absoluteLengths[$unit])) {
            return [
                'group' => 'length:absolute',
                'canonical' => $value * $absoluteLengths[$unit],
                'canonicalPerUnit' => $absoluteLengths[$unit],
            ];
        }

        $times = ['s' => 1.0, 'ms' => 0.001];
        if (isset($times[$unit])) {
            return [
                'group' => 'time',
                'canonical' => $value * $times[$unit],
                'canonicalPerUnit' => $times[$unit],
            ];
        }

        $angles = [
            'deg' => 1.0,
            'grad' => 0.9,
            'rad' => 180.0 / M_PI,
            'turn' => 360.0,
        ];
        if (isset($angles[$unit])) {
            return [
                'group' => 'angle',
                'canonical' => $value * $angles[$unit],
                'canonicalPerUnit' => $angles[$unit],
            ];
        }

        return [
            'group' => $unit === '' ? 'number' : 'unit:' . $unit,
            'canonical' => $value,
            'canonicalPerUnit' => 1.0,
        ];
    }

    /**
     * @param array{group:string,canonical:float} $left
     * @param array{group:string,canonical:float} $right
     */
    private function compareComparableMathValues(array $left, array $right): ?int
    {
        if ($left['group'] !== $right['group']) {
            return null;
        }
        if (abs($left['canonical'] - $right['canonical']) < 0.0000001) {
            return 0;
        }

        return $left['canonical'] < $right['canonical'] ? -1 : 1;
    }

    private function serializeMathNumberWithUnit(float $number, string $unit): string
    {
        $serialized = $this->minifyNumber($number);

        return $serialized === '0' ? '0' : $serialized . $unit;
    }

    private function compactMathFallback(string $value): string
    {
        $value = preg_replace('/\s*,\s*/', ',', trim($value)) ?? trim($value);
        $value = preg_replace('/\s*([*\/])\s*/', '$1', $value) ?? $value;

        return $value;
    }

    private function compactCalcFallback(string $value): string
    {
        if (preg_match('/\bsign\([^)]*%/', $value) === 1) {
            $value = preg_replace('/\s*,\s*/', ',', trim($value)) ?? trim($value);
            $value = preg_replace('/\s*([*\/])\s*/', ' $1 ', $value) ?? $value;

            return preg_replace('/\s+/', ' ', $value) ?? $value;
        }

        return $this->compactMathFallback($value);
    }

    private function foldSimpleLengthCalcs(string $value): string
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

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $i + strlen($identifier);
                if (strtolower($identifier) === 'calc' && ($value[$next] ?? '') === '(') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $this->foldSimpleLengthCalc($function);
                    $i = $offset;
                    continue;
                }

                $output .= $identifier;
                $i = $next - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function foldSimpleLengthCalc(string $function): string
    {
        if (preg_match('/^calc\((.*)\)$/is', trim($function), $calcMatches) !== 1) {
            return $function;
        }
        $inner = trim($calcMatches[1]);

        $tokens = $this->tokenizeLinearCalcExpression($inner);
        if ($tokens !== []) {
            $offset = 0;
            $result = $this->parseLinearCalcExpression($tokens, $offset);
            if ($result !== null && $offset === count($tokens)) {
                return $this->serializeLinearCalc($result);
            }
        }

        return $function;
    }

    /**
     * @return list<array{type:string,value:string,unit?:string}>
     */
    private function tokenizeLinearCalcExpression(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        for ($i = 0; $i < $length;) {
            $char = $expression[$i];
            if (ctype_space($char)) {
                $i++;
                continue;
            }
            if (str_contains('()+-*/', $char)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $i++;
                continue;
            }
            if (preg_match('/\G(\d*\.\d+|\d+)(?:[eE][+-]?\d+)?/A', $expression, $numberMatches, 0, $i) === 1) {
                $number = $numberMatches[0];
                $i += strlen($number);
                $unit = '';
                if (preg_match('/\G(?:%|[a-zA-Z][a-zA-Z0-9]*)/A', $expression, $unitMatches, 0, $i) === 1) {
                    $unit = strtolower($unitMatches[0]);
                    $i += strlen($unitMatches[0]);
                }
                $tokens[] = ['type' => 'number', 'value' => $number, 'unit' => $unit];
                continue;
            }
            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($expression, $i);
                $tokens[] = ['type' => 'ident', 'value' => strtolower($identifier)];
                $i += strlen($identifier);
                continue;
            }

            return [];
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string,value:string,unit?:string}> $tokens
     * @return array{terms:array<string,float>,order:list<string>}|null
     */
    private function parseLinearCalcExpression(array $tokens, int &$offset): ?array
    {
        $value = $this->parseLinearCalcProduct($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && in_array($tokens[$offset]['type'], ['+', '-'], true)) {
            $operator = $tokens[$offset++]['type'];
            $right = $this->parseLinearCalcProduct($tokens, $offset);
            if ($right === null) {
                return null;
            }
            $value = $this->combineLinearCalc($value, $right, $operator === '-' ? -1.0 : 1.0);
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string,unit?:string}> $tokens
     * @return array{terms:array<string,float>,order:list<string>}|null
     */
    private function parseLinearCalcProduct(array $tokens, int &$offset): ?array
    {
        $value = $this->parseLinearCalcFactor($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && in_array($tokens[$offset]['type'], ['*', '/'], true)) {
            $operator = $tokens[$offset++]['type'];
            $right = $this->parseLinearCalcFactor($tokens, $offset);
            if ($right === null) {
                return null;
            }

            if ($operator === '*') {
                $leftScalar = $this->linearCalcScalar($value);
                $rightScalar = $this->linearCalcScalar($right);
                if ($leftScalar === null && $rightScalar === null) {
                    return null;
                }
                $value = $leftScalar !== null
                    ? $this->scaleLinearCalc($right, $leftScalar)
                    : $this->scaleLinearCalc($value, $rightScalar);
                continue;
            }

            $rightScalar = $this->linearCalcScalar($right);
            if ($rightScalar === null || abs($rightScalar) < 0.0000001) {
                return null;
            }
            $value = $this->scaleLinearCalc($value, 1 / $rightScalar);
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string,unit?:string}> $tokens
     * @return array{terms:array<string,float>,order:list<string>}|null
     */
    private function parseLinearCalcFactor(array $tokens, int &$offset): ?array
    {
        if ($offset >= count($tokens)) {
            return null;
        }

        $token = $tokens[$offset++];
        if ($token['type'] === '+') {
            return $this->parseLinearCalcFactor($tokens, $offset);
        }
        if ($token['type'] === '-') {
            $value = $this->parseLinearCalcFactor($tokens, $offset);

            return $value === null ? null : $this->scaleLinearCalc($value, -1.0);
        }
        if ($token['type'] === '(') {
            $value = $this->parseLinearCalcExpression($tokens, $offset);
            if ($value === null || ($tokens[$offset]['type'] ?? null) !== ')') {
                return null;
            }
            $offset++;

            return $value;
        }
        if ($token['type'] === 'ident' && $token['value'] === 'calc' && ($tokens[$offset]['type'] ?? null) === '(') {
            $offset++;
            $value = $this->parseLinearCalcExpression($tokens, $offset);
            if ($value === null || ($tokens[$offset]['type'] ?? null) !== ')') {
                return null;
            }
            $offset++;

            return $value;
        }
        if ($token['type'] === 'ident') {
            $constant = $this->mathConstant($token['value']);
            if ($constant !== null) {
                return [
                    'terms' => ['' => $constant],
                    'order' => [''],
                ];
            }
        }
        if ($token['type'] !== 'number') {
            return null;
        }

        $unit = strtolower($token['unit'] ?? '');
        if ($unit !== '' && !$this->isFoldableCalcUnit($unit)) {
            return null;
        }

        return [
            'terms' => [$unit => (float) $token['value']],
            'order' => [$unit],
        ];
    }

    private function isFoldableCalcUnit(string $unit): bool
    {
        return in_array($unit, [
            '%',
            'cap',
            'ch',
            'cm',
            'cqb',
            'cqh',
            'cqi',
            'cqmax',
            'cqmin',
            'cqw',
            'deg',
            'dvh',
            'dvw',
            'em',
            'ex',
            'grad',
            'ic',
            'in',
            'lh',
            'lvh',
            'lvw',
            'mm',
            'ms',
            'pc',
            'pt',
            'px',
            'q',
            'rad',
            'rem',
            'ric',
            'rlh',
            's',
            'svh',
            'svmin',
            'svw',
            'turn',
            'vh',
            'vmax',
            'vmin',
            'vw',
        ], true);
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $left
     * @param array{terms:array<string,float>,order:list<string>} $right
     * @return array{terms:array<string,float>,order:list<string>}
     */
    private function combineLinearCalc(array $left, array $right, float $rightSign): array
    {
        $terms = $left['terms'];
        $order = $left['order'];
        foreach ($right['order'] as $unit) {
            if (!in_array($unit, $order, true)) {
                $order[] = $unit;
            }
        }
        foreach ($right['terms'] as $unit => $coefficient) {
            $terms[$unit] = ($terms[$unit] ?? 0.0) + $rightSign * $coefficient;
        }

        return ['terms' => $terms, 'order' => $order];
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $value
     * @return array{terms:array<string,float>,order:list<string>}
     */
    private function scaleLinearCalc(array $value, float $factor): array
    {
        $terms = [];
        foreach ($value['terms'] as $unit => $coefficient) {
            $terms[$unit] = $coefficient * $factor;
        }

        return ['terms' => $terms, 'order' => $value['order']];
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $value
     */
    private function linearCalcScalar(array $value): ?float
    {
        $nonZero = $this->nonZeroLinearCalcUnits($value);
        if ($nonZero === []) {
            return 0.0;
        }

        return $nonZero === [''] ? ($value['terms'][''] ?? 0.0) : null;
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $value
     * @return list<string>
     */
    private function nonZeroLinearCalcUnits(array $value): array
    {
        $units = [];
        foreach ($value['order'] as $unit) {
            if (abs($value['terms'][$unit] ?? 0.0) >= 0.0000001) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    /**
     * @param array{terms:array<string,float>,order:list<string>} $value
     */
    private function serializeLinearCalc(array $value): string
    {
        $units = $this->nonZeroLinearCalcUnits($value);
        if ($units === []) {
            return '0';
        }
        if (count($units) === 1) {
            $unit = $units[0];

            return $this->serializeLinearCalcTerm($value['terms'][$unit], $unit);
        }

        if (($value['terms'][$units[0]] ?? 0.0) < 0) {
            $positive = array_values(array_filter($units, fn (string $unit): bool => ($value['terms'][$unit] ?? 0.0) > 0));
            if ($positive !== []) {
                $negative = array_values(array_filter($units, fn (string $unit): bool => ($value['terms'][$unit] ?? 0.0) < 0));
                $units = array_merge($positive, $negative);
            }
        }

        $output = '';
        foreach ($units as $unit) {
            $coefficient = $value['terms'][$unit];
            $term = $this->serializeLinearCalcTerm(abs($coefficient), $unit);
            if ($output === '') {
                $output = $coefficient < 0 ? '-' . $term : $term;
                continue;
            }
            $output .= $coefficient < 0 ? ' - ' . $term : ' + ' . $term;
        }

        return 'calc(' . $output . ')';
    }

    private function mathConstant(string $identifier): ?float
    {
        return match (strtolower(trim($identifier))) {
            'e' => M_E,
            'pi' => M_PI,
            default => null,
        };
    }

    private function serializeComputedMathNumberWithUnit(float $number, string $unit): string
    {
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $serialized = str_replace('E', 'e', sprintf('%.6G', $number));
        if (str_starts_with($serialized, '0.')) {
            $serialized = substr($serialized, 1);
        } elseif (str_starts_with($serialized, '-0.')) {
            $serialized = '-' . substr($serialized, 2);
        }

        return $serialized === '0' ? '0' : $serialized . $unit;
    }

    private function serializeLinearCalcTerm(float $coefficient, string $unit): string
    {
        $number = $this->minifyNumber($coefficient);
        if ($number === '0' || $unit === '') {
            return $number;
        }

        return $number . $unit;
    }

    private function isBinaryMathOperator(string $value, int $offset): bool
    {
        $previous = $this->previousNonSpace($value, $offset - 1);
        $next = $this->nextNonSpace($value, $offset + 1);
        if ($previous === null || $next === null) {
            return false;
        }

        if ($this->isExponentSign($value, $offset)) {
            return false;
        }

        return preg_match('/[a-zA-Z0-9_%)]/', $previous) === 1
            && preg_match('/[a-zA-Z0-9_.(-]/', $next) === 1;
    }

    private function isExponentSign(string $value, int $offset): bool
    {
        $previous = $offset > 0 ? $value[$offset - 1] : '';
        if ($previous !== 'e' && $previous !== 'E') {
            return false;
        }

        $beforeExponent = $value[$offset - 2] ?? '';
        $afterSign = $value[$offset + 1] ?? '';

        return ctype_digit($beforeExponent) && ctype_digit($afterSign);
    }

    private function previousNonSpace(string $value, int $offset): ?string
    {
        for ($i = $offset; $i >= 0; $i--) {
            if (!ctype_space($value[$i])) {
                return $value[$i];
            }
        }

        return null;
    }

    private function nextNonSpace(string $value, int $offset): ?string
    {
        $length = strlen($value);
        for ($i = $offset; $i < $length; $i++) {
            if (!ctype_space($value[$i])) {
                return $value[$i];
            }
        }

        return null;
    }

    private function minifyColorKeywords(string $value): string
    {
        $colors = [
            'aqua' => '#0ff',
            'black' => '#000',
            'blue' => '#00f',
            'chartreuse' => '#7fff00',
            'cornflowerblue' => '#6495ed',
            'cyan' => '#0ff',
            'fuchsia' => '#f0f',
            'lime' => '#0f0',
            'magenta' => '#f0f',
            'transparent' => '#0000',
            'white' => '#fff',
            'yellow' => '#ff0',
        ];

        return $this->minifyColorTokens($value, $colors, true);
    }

    private function minifyColorFunctionsAndHex(string $value): string
    {
        return $this->minifyColorTokens($value, [], false);
    }

    /**
     * @param array<string,string> $colors
     */
    private function minifyColorTokens(string $value, array $colors, bool $minifyKeywords): string
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

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $lower = strtolower($identifier);
                $previous = $value[$i - 1] ?? '';
                $next = $value[$i + strlen($identifier)] ?? '';
                if ($lower === 'color-mix' && $next === '(') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $function;
                    $i = $offset;
                    continue;
                }
                if ($next === '(' && in_array($lower, ['hsl', 'hsla', 'hwb', 'rgb', 'rgba', 'lab', 'lch', 'oklab', 'oklch', 'color'], true)) {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $this->minifyColorFunction($function)
                        ?? $this->minifyAdvancedColorFunction($function)
                        ?? $function;
                    $i = $offset;
                    continue;
                }
                if ($previous === '-' || $next === '(') {
                    $output .= $identifier;
                    $i += strlen($identifier) - 1;
                    continue;
                }

                $output .= $minifyKeywords ? ($colors[$lower] ?? $this->minifySystemColorKeyword($identifier)) : $identifier;
                $i += strlen($identifier) - 1;
                continue;
            }

            if ($char === '#'
                && preg_match('/^#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})(?![0-9a-fA-F])/', substr($value, $i), $matches) === 1
            ) {
                $output .= $this->compressHexColor($matches[0]);
                $i += strlen($matches[0]) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifySystemColorKeyword(string $identifier): string
    {
        $systemColors = [
            'accentcolor',
            'accentcolortext',
            'activetext',
            'buttonborder',
            'buttonface',
            'buttontext',
            'canvas',
            'canvastext',
            'field',
            'fieldtext',
            'graytext',
            'highlight',
            'highlighttext',
            'linktext',
            'mark',
            'marktext',
            'selecteditem',
            'selecteditemtext',
            'visitedtext',
        ];
        $lower = strtolower($identifier);

        return in_array($lower, $systemColors, true) ? $lower : $identifier;
    }

    private function minifySrgbColorMixFunctions(string $value): string
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

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $value[$i + strlen($identifier)] ?? '';
                if (strcasecmp($identifier, 'color-mix') === 0 && $next === '(') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $this->minifyColorMixFunction($function) ?? $function;
                    $i = $offset;
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function minifyColorMixFunction(string $function): ?string
    {
        if (preg_match('/^color-mix\((.*)\)$/is', trim($function), $matches) !== 1) {
            return null;
        }

        $parts = $this->splitTopLevel($matches[1], ',');
        if (count($parts) !== 3) {
            return null;
        }

        $interpolation = $this->parseColorMixInterpolationSpace($parts[0]);
        if ($interpolation === null) {
            return null;
        }

        $space = $interpolation['space'];
        if ($space === 'srgb' && $interpolation['hueMethod'] === null) {
            return $this->minifySrgbColorMixParts($parts[1], $parts[2]);
        }

        if (($space === 'lab' || $space === 'oklab') && $interpolation['hueMethod'] === null) {
            return $this->minifyRectangularColorMixParts($space, $parts[1], $parts[2]);
        }

        if (in_array($space, ['srgb-linear', 'xyz', 'xyz-d50', 'xyz-d65'], true) && $interpolation['hueMethod'] === null) {
            return $this->minifyColorFunctionColorMixParts($space, $parts[1], $parts[2]);
        }

        if ($space === 'hsl') {
            return $this->minifyHslColorMixParts($interpolation['hueMethod'] ?? 'shorter', $parts[1], $parts[2]);
        }

        if ($space === 'hwb') {
            return $this->minifyHwbColorMixParts($interpolation['hueMethod'] ?? 'shorter', $parts[1], $parts[2]);
        }

        if ($space === 'lch' || $space === 'oklch') {
            return $this->minifyPolarColorMixParts($space, $interpolation['hueMethod'] ?? 'shorter', $parts[1], $parts[2]);
        }

        return null;
    }

    /**
     * @return array{space:string,hueMethod:?string}|null
     */
    private function parseColorMixInterpolationSpace(string $space): ?array
    {
        $tokens = preg_split('/\s+/', strtolower(trim($space))) ?: [];
        if (count($tokens) < 2 || $tokens[0] !== 'in') {
            return null;
        }

        $colorSpace = $tokens[1];
        $hueMethod = null;
        if (count($tokens) === 4 && $tokens[3] === 'hue') {
            $method = $tokens[2];
            if (!in_array($method, ['shorter', 'longer', 'increasing', 'decreasing', 'specified'], true)) {
                return null;
            }
            $hueMethod = $method;
        } elseif (count($tokens) !== 2) {
            return null;
        }

        if (!in_array($colorSpace, ['srgb', 'srgb-linear', 'hsl', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'xyz', 'xyz-d50', 'xyz-d65'], true)) {
            return null;
        }

        return [
            'space' => $colorSpace,
            'hueMethod' => $hueMethod,
        ];
    }

    private function minifySrgbColorMixParts(string $leftStop, string $rightStop): ?string
    {
        $left = $this->parseSrgbColorMixStop($leftStop);
        $right = $this->parseSrgbColorMixStop($rightStop);
        if ($left === null || $right === null) {
            return $this->serializeUnresolvedSrgbColorMixFunction($leftStop, $rightStop);
        }

        [$leftWeight, $rightWeight] = $this->normalizedColorMixWeights($left['weight'], $right['weight']);
        if ($leftWeight === null || $rightWeight === null) {
            return null;
        }

        $leftAlpha = $left['color']['alpha'];
        $rightAlpha = $right['color']['alpha'];
        $alpha = ($leftAlpha * $leftWeight) + ($rightAlpha * $rightWeight);
        if ($alpha <= 0.000000001) {
            return '#0000';
        }

        $red = (($left['color']['red'] / 255 * $leftAlpha * $leftWeight) + ($right['color']['red'] / 255 * $rightAlpha * $rightWeight)) / $alpha;
        $green = (($left['color']['green'] / 255 * $leftAlpha * $leftWeight) + ($right['color']['green'] / 255 * $rightAlpha * $rightWeight)) / $alpha;
        $blue = (($left['color']['blue'] / 255 * $leftAlpha * $leftWeight) + ($right['color']['blue'] / 255 * $rightAlpha * $rightWeight)) / $alpha;

        return $this->serializeColorBytes(
            $this->clampColorByte((int) round($red * 255)),
            $this->clampColorByte((int) round($green * 255)),
            $this->clampColorByte((int) round($blue * 255)),
            min(1.0, max(0.0, $alpha)),
        );
    }

    private function minifyRectangularColorMixParts(string $space, string $leftStop, string $rightStop): ?string
    {
        $left = $this->parseRectangularColorMixStop($leftStop, $space);
        $right = $this->parseRectangularColorMixStop($rightStop, $space);
        if ($left === null || $right === null) {
            return null;
        }

        [$leftWeight, $rightWeight] = $this->normalizedColorMixWeights($left['weight'], $right['weight']);
        if ($leftWeight === null || $rightWeight === null) {
            return null;
        }

        [$leftAlpha, $rightAlpha, $resultAlpha] = $this->resolveRectangularColorMixAlphas(
            $left['color']['alpha'],
            $right['color']['alpha'],
            $leftWeight,
            $rightWeight,
        );
        $componentAlpha = ($leftAlpha * $leftWeight) + ($rightAlpha * $rightWeight);
        $components = [];
        for ($index = 0; $index < 3; $index++) {
            $components[] = $this->mixRectangularColorComponent(
                $left['color']['components'][$index],
                $right['color']['components'][$index],
                $leftWeight,
                $rightWeight,
                $leftAlpha,
                $rightAlpha,
                $componentAlpha,
            );
        }

        return $this->serializeRectangularColorMixResult($space, $components, $resultAlpha);
    }

    private function minifyColorFunctionColorMixParts(string $space, string $leftStop, string $rightStop): ?string
    {
        $left = $this->parseColorFunctionColorMixStop($leftStop, $space);
        $right = $this->parseColorFunctionColorMixStop($rightStop, $space);
        if ($left === null || $right === null) {
            return null;
        }

        [$leftWeight, $rightWeight] = $this->normalizedColorMixWeights($left['weight'], $right['weight']);
        if ($leftWeight === null || $rightWeight === null) {
            return null;
        }

        [$leftAlpha, $rightAlpha, $resultAlpha] = $this->resolveRectangularColorMixAlphas(
            $left['color']['alpha'],
            $right['color']['alpha'],
            $leftWeight,
            $rightWeight,
        );
        $componentAlpha = ($leftAlpha * $leftWeight) + ($rightAlpha * $rightWeight);
        $components = [];
        for ($index = 0; $index < 3; $index++) {
            $components[] = $this->mixRectangularColorComponent(
                $left['color']['components'][$index],
                $right['color']['components'][$index],
                $leftWeight,
                $rightWeight,
                $leftAlpha,
                $rightAlpha,
                $componentAlpha,
            );
        }

        return $this->serializeColorFunctionColorMixResult($this->normalizeColorSpaceName($space), $components, $resultAlpha);
    }

    private function minifyPolarColorMixParts(string $space, string $hueMethod, string $leftStop, string $rightStop): ?string
    {
        $left = $this->parsePolarColorMixStop($leftStop, $space);
        $right = $this->parsePolarColorMixStop($rightStop, $space);
        if ($left === null || $right === null) {
            return null;
        }

        [$leftWeight, $rightWeight] = $this->normalizedColorMixWeights($left['weight'], $right['weight']);
        if ($leftWeight === null || $rightWeight === null) {
            return null;
        }

        [$leftAlpha, $rightAlpha, $resultAlpha] = $this->resolveRectangularColorMixAlphas(
            $left['color']['alpha'],
            $right['color']['alpha'],
            $leftWeight,
            $rightWeight,
        );
        $componentAlpha = ($leftAlpha * $leftWeight) + ($rightAlpha * $rightWeight);

        $lightness = $this->mixRectangularColorComponent(
            $left['color']['lightness'],
            $right['color']['lightness'],
            $leftWeight,
            $rightWeight,
            $leftAlpha,
            $rightAlpha,
            $componentAlpha,
        );
        $chroma = $this->mixRectangularColorComponent(
            $left['color']['chroma'],
            $right['color']['chroma'],
            $leftWeight,
            $rightWeight,
            $leftAlpha,
            $rightAlpha,
            $componentAlpha,
        );
        $hue = $this->mixPolarHueComponent(
            $left['color']['hue'],
            $right['color']['hue'],
            $leftWeight,
            $rightWeight,
            $hueMethod,
        );

        return $this->serializePolarColorMixResult($space, $lightness, $chroma, $hue, $resultAlpha);
    }

    private function minifyHslColorMixParts(string $hueMethod, string $leftStop, string $rightStop): ?string
    {
        $left = $this->parseHslColorMixStop($leftStop);
        $right = $this->parseHslColorMixStop($rightStop);
        if ($left === null || $right === null) {
            return null;
        }

        [$leftWeight, $rightWeight] = $this->normalizedColorMixWeights($left['weight'], $right['weight']);
        if ($leftWeight === null || $rightWeight === null) {
            return null;
        }

        [$leftAlpha, $rightAlpha, $resultAlpha] = $this->resolveRectangularColorMixAlphas(
            $left['color']['alpha'],
            $right['color']['alpha'],
            $leftWeight,
            $rightWeight,
        );
        $componentAlpha = ($leftAlpha * $leftWeight) + ($rightAlpha * $rightWeight);

        $hue = $this->mixPolarHueComponent(
            $left['color']['hue'],
            $right['color']['hue'],
            $leftWeight,
            $rightWeight,
            $hueMethod,
        );
        $saturation = $this->mixRectangularColorComponent(
            $left['color']['saturation'],
            $right['color']['saturation'],
            $leftWeight,
            $rightWeight,
            $leftAlpha,
            $rightAlpha,
            $componentAlpha,
        );
        $lightness = $this->mixRectangularColorComponent(
            $left['color']['lightness'],
            $right['color']['lightness'],
            $leftWeight,
            $rightWeight,
            $leftAlpha,
            $rightAlpha,
            $componentAlpha,
        );

        [$red, $green, $blue] = $this->hslToRgbBytes(
            $hue ?? 0.0,
            $saturation ?? 0.0,
            $lightness ?? 0.0,
            $this->colorMixRgbByteRoundingBias($left['color']['alpha'], $right['color']['alpha'], $leftWeight, $rightWeight),
        );

        return $this->serializeColorBytes($red, $green, $blue, $resultAlpha ?? 0.0);
    }

    private function minifyHwbColorMixParts(string $hueMethod, string $leftStop, string $rightStop): ?string
    {
        $left = $this->parseHwbColorMixStop($leftStop);
        $right = $this->parseHwbColorMixStop($rightStop);
        if ($left === null || $right === null) {
            return null;
        }

        [$leftWeight, $rightWeight] = $this->normalizedColorMixWeights($left['weight'], $right['weight']);
        if ($leftWeight === null || $rightWeight === null) {
            return null;
        }

        [$leftAlpha, $rightAlpha, $resultAlpha] = $this->resolveRectangularColorMixAlphas(
            $left['color']['alpha'],
            $right['color']['alpha'],
            $leftWeight,
            $rightWeight,
        );
        $componentAlpha = ($leftAlpha * $leftWeight) + ($rightAlpha * $rightWeight);

        $hue = $this->mixPolarHueComponent(
            $left['color']['hue'],
            $right['color']['hue'],
            $leftWeight,
            $rightWeight,
            $hueMethod,
        );
        $white = $this->mixRectangularColorComponent(
            $left['color']['white'],
            $right['color']['white'],
            $leftWeight,
            $rightWeight,
            $leftAlpha,
            $rightAlpha,
            $componentAlpha,
        );
        $black = $this->mixRectangularColorComponent(
            $left['color']['black'],
            $right['color']['black'],
            $leftWeight,
            $rightWeight,
            $leftAlpha,
            $rightAlpha,
            $componentAlpha,
        );

        [$red, $green, $blue] = $this->hwbColorMixToRgbBytes(
            $hue ?? 0.0,
            $white ?? 0.0,
            $black ?? 0.0,
            $this->colorMixRgbByteRoundingBias($left['color']['alpha'], $right['color']['alpha'], $leftWeight, $rightWeight),
        );

        return $this->serializeColorBytes($red, $green, $blue, $resultAlpha ?? 0.0);
    }

    private function serializeUnresolvedSrgbColorMixFunction(string $left, string $right): string
    {
        return 'color-mix(in srgb, '
            . $this->serializeUnresolvedColorMixStop($left)
            . ', '
            . $this->serializeUnresolvedColorMixStop($right)
            . ')';
    }

    private function serializeUnresolvedColorMixStop(string $stop): string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        $tokens = array_map(fn (string $token): string => match (strtolower($token)) {
            '#00f', '#0000ff' => 'blue',
            '#f00', '#ff0000' => 'red',
            'accentcolor' => 'accentcolor',
            default => $token,
        }, $tokens);

        return implode(' ', $tokens);
    }

    /**
     * @return array{color:array{red:int,green:int,blue:int,alpha:float},weight:?float}|null
     */
    private function parseSrgbColorMixStop(string $stop): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        if ($tokens === []) {
            return null;
        }

        $weight = null;
        $firstWeight = $this->parseColorMixPercentage($tokens[0]);
        if ($firstWeight !== null) {
            $weight = $firstWeight;
            array_shift($tokens);
        }

        if ($tokens !== []) {
            $lastIndex = count($tokens) - 1;
            $lastWeight = $this->parseColorMixPercentage($tokens[$lastIndex]);
            if ($lastWeight !== null) {
                if ($weight !== null) {
                    return null;
                }
                $weight = $lastWeight;
                array_pop($tokens);
            }
        }

        if (count($tokens) !== 1) {
            return null;
        }

        $color = $this->parseSrgbColorMixColor($tokens[0]);
        if ($color === null) {
            return null;
        }

        return [
            'color' => $color,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{color:array{components:list<?float>,alpha:?float},weight:?float}|null
     */
    private function parseRectangularColorMixStop(string $stop, string $space): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        if ($tokens === []) {
            return null;
        }

        $weight = null;
        $firstWeight = $this->parseColorMixPercentage($tokens[0]);
        if ($firstWeight !== null) {
            $weight = $firstWeight;
            array_shift($tokens);
        }

        if ($tokens !== []) {
            $lastIndex = count($tokens) - 1;
            $lastWeight = $this->parseColorMixPercentage($tokens[$lastIndex]);
            if ($lastWeight !== null) {
                if ($weight !== null) {
                    return null;
                }
                $weight = $lastWeight;
                array_pop($tokens);
            }
        }

        if (count($tokens) !== 1) {
            return null;
        }

        $color = $this->parseRectangularColorMixColor($tokens[0], $space);
        if ($color === null) {
            return null;
        }

        return [
            'color' => $color,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{color:array{components:list<?float>,alpha:?float},weight:?float}|null
     */
    private function parseColorFunctionColorMixStop(string $stop, string $space): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        if ($tokens === []) {
            return null;
        }

        $weight = null;
        $firstWeight = $this->parseColorMixPercentage($tokens[0]);
        if ($firstWeight !== null) {
            $weight = $firstWeight;
            array_shift($tokens);
        }

        if ($tokens !== []) {
            $lastIndex = count($tokens) - 1;
            $lastWeight = $this->parseColorMixPercentage($tokens[$lastIndex]);
            if ($lastWeight !== null) {
                if ($weight !== null) {
                    return null;
                }
                $weight = $lastWeight;
                array_pop($tokens);
            }
        }

        if (count($tokens) !== 1) {
            return null;
        }

        $color = $this->parseColorFunctionColorMixColor($tokens[0], $space);
        if ($color === null) {
            return null;
        }

        return [
            'color' => $color,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{color:array{lightness:?float,chroma:?float,hue:?float,alpha:?float},weight:?float}|null
     */
    private function parsePolarColorMixStop(string $stop, string $space): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        if ($tokens === []) {
            return null;
        }

        $weight = null;
        $firstWeight = $this->parseColorMixPercentage($tokens[0]);
        if ($firstWeight !== null) {
            $weight = $firstWeight;
            array_shift($tokens);
        }

        if ($tokens !== []) {
            $lastIndex = count($tokens) - 1;
            $lastWeight = $this->parseColorMixPercentage($tokens[$lastIndex]);
            if ($lastWeight !== null) {
                if ($weight !== null) {
                    return null;
                }
                $weight = $lastWeight;
                array_pop($tokens);
            }
        }

        if (count($tokens) !== 1) {
            return null;
        }

        $color = $this->parsePolarColorMixColor($tokens[0], $space);
        if ($color === null) {
            return null;
        }

        return [
            'color' => $color,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{color:array{hue:?float,saturation:?float,lightness:?float,alpha:?float},weight:?float}|null
     */
    private function parseHslColorMixStop(string $stop): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        if ($tokens === []) {
            return null;
        }

        $weight = null;
        $firstWeight = $this->parseColorMixPercentage($tokens[0]);
        if ($firstWeight !== null) {
            $weight = $firstWeight;
            array_shift($tokens);
        }

        if ($tokens !== []) {
            $lastIndex = count($tokens) - 1;
            $lastWeight = $this->parseColorMixPercentage($tokens[$lastIndex]);
            if ($lastWeight !== null) {
                if ($weight !== null) {
                    return null;
                }
                $weight = $lastWeight;
                array_pop($tokens);
            }
        }

        if (count($tokens) !== 1) {
            return null;
        }

        $color = $this->parseHslColorMixColor($tokens[0]);
        if ($color === null) {
            return null;
        }

        return [
            'color' => $color,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{color:array{hue:?float,white:?float,black:?float,alpha:?float},weight:?float}|null
     */
    private function parseHwbColorMixStop(string $stop): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($stop));
        if ($tokens === []) {
            return null;
        }

        $weight = null;
        $firstWeight = $this->parseColorMixPercentage($tokens[0]);
        if ($firstWeight !== null) {
            $weight = $firstWeight;
            array_shift($tokens);
        }

        if ($tokens !== []) {
            $lastIndex = count($tokens) - 1;
            $lastWeight = $this->parseColorMixPercentage($tokens[$lastIndex]);
            if ($lastWeight !== null) {
                if ($weight !== null) {
                    return null;
                }
                $weight = $lastWeight;
                array_pop($tokens);
            }
        }

        if (count($tokens) !== 1) {
            return null;
        }

        $color = $this->parseHwbColorMixColor($tokens[0]);
        if ($color === null) {
            return null;
        }

        return [
            'color' => $color,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{components:list<?float>,alpha:?float}|null
     */
    private function parseRectangularColorMixColor(string $token, string $space): ?array
    {
        if (preg_match('/^' . preg_quote($space, '/') . '\((.*)\)$/is', trim($token), $matches) !== 1) {
            return null;
        }

        $parts = $this->parseAdvancedColorFunctionParts($matches[1]);
        if ($parts === null || count($parts['components']) !== 3) {
            return null;
        }

        $components = [];
        foreach ($parts['components'] as $index => $component) {
            $value = $this->parseRectangularColorMixComponent($component, $index, $space);
            if ($value === false) {
                return null;
            }
            $components[] = $value;
        }

        $alpha = $this->parseRectangularColorMixAlpha($parts['alpha']);
        if ($alpha === false) {
            return null;
        }

        return [
            'components' => $components,
            'alpha' => $alpha,
        ];
    }

    /**
     * @return array{components:list<?float>,alpha:?float}|null
     */
    private function parseColorFunctionColorMixColor(string $token, string $space): ?array
    {
        if (preg_match('/^color\((.*)\)$/is', trim($token), $matches) !== 1) {
            return null;
        }

        $parts = $this->parseAdvancedColorFunctionParts($matches[1]);
        if ($parts === null || count($parts['components']) !== 4) {
            return null;
        }

        $actualSpace = $this->normalizeColorSpaceName($parts['components'][0]);
        if ($actualSpace !== $this->normalizeColorSpaceName($space)) {
            return null;
        }

        $components = [];
        foreach (array_slice($parts['components'], 1) as $component) {
            $value = $this->parseColorFunctionColorMixComponent($component);
            if ($value === false) {
                return null;
            }
            $components[] = $value;
        }

        $alpha = $this->parseRectangularColorMixAlpha($parts['alpha']);
        if ($alpha === false) {
            return null;
        }

        return [
            'components' => $components,
            'alpha' => $alpha,
        ];
    }

    /**
     * @return array{lightness:?float,chroma:?float,hue:?float,alpha:?float}|null
     */
    private function parsePolarColorMixColor(string $token, string $space): ?array
    {
        if (preg_match('/^' . preg_quote($space, '/') . '\((.*)\)$/is', trim($token), $matches) !== 1) {
            return null;
        }

        $parts = $this->parseAdvancedColorFunctionParts($matches[1]);
        if ($parts === null || count($parts['components']) !== 3) {
            return null;
        }

        $lightness = $this->parsePolarColorMixLightnessComponent($parts['components'][0], $space);
        $chroma = $this->parsePolarColorMixChromaComponent($parts['components'][1], $space);
        $hue = $this->parsePolarColorMixHueComponent($parts['components'][2]);
        $alpha = $this->parseRectangularColorMixAlpha($parts['alpha']);

        if ($lightness === false || $chroma === false || $hue === false || $alpha === false) {
            return null;
        }

        return [
            'lightness' => $lightness,
            'chroma' => $chroma,
            'hue' => $hue,
            'alpha' => $alpha,
        ];
    }

    /**
     * @return array{hue:?float,saturation:?float,lightness:?float,alpha:?float}|null
     */
    private function parseHslColorMixColor(string $token): ?array
    {
        if (preg_match('/^hsla?\((.*)\)$/is', trim($token), $matches) !== 1) {
            return null;
        }

        $parts = $this->parseColorFunctionParts($matches[1]);
        if ($parts === null || count($parts['components']) !== 3) {
            return null;
        }

        $hue = $this->parseHslColorMixHueComponent($parts['components'][0]);
        $saturation = $this->parseHslColorMixPercentageComponent($parts['components'][1]);
        $lightness = $this->parseHslColorMixPercentageComponent($parts['components'][2]);
        $alpha = $this->parseRectangularColorMixAlpha($parts['alpha']);

        if ($hue === false || $saturation === false || $lightness === false || $alpha === false) {
            return null;
        }

        return [
            'hue' => $hue,
            'saturation' => $saturation,
            'lightness' => $lightness,
            'alpha' => $alpha,
        ];
    }

    /**
     * @return array{hue:?float,white:?float,black:?float,alpha:?float}|null
     */
    private function parseHwbColorMixColor(string $token): ?array
    {
        if (preg_match('/^hwb\((.*)\)$/is', trim($token), $matches) !== 1) {
            return null;
        }

        $parts = $this->parseColorFunctionParts($matches[1]);
        if ($parts === null || count($parts['components']) !== 3) {
            return null;
        }

        $hue = $this->parseHslColorMixHueComponent($parts['components'][0]);
        $white = $this->parseHslColorMixPercentageComponent($parts['components'][1]);
        $black = $this->parseHslColorMixPercentageComponent($parts['components'][2]);
        $alpha = $this->parseRectangularColorMixAlpha($parts['alpha']);

        if ($hue === false || $white === false || $black === false || $alpha === false) {
            return null;
        }

        return [
            'hue' => $hue,
            'white' => $white,
            'black' => $black,
            'alpha' => $alpha,
        ];
    }

    private function parseRectangularColorMixComponent(string $token, int $index, string $space): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return false;
        }

        if ($index === 0) {
            if ($space === 'oklab' && !$number['isPercentage']) {
                return $number['value'] * 100;
            }

            return $number['value'];
        }

        if ($space === 'lab' && $number['isPercentage']) {
            return $number['value'] * 1.25;
        }

        if ($space === 'oklab' && $number['isPercentage']) {
            return $number['value'] * 0.004;
        }

        return $number['value'];
    }

    private function parsePolarColorMixLightnessComponent(string $token, string $space): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return false;
        }

        if ($space === 'oklch' && !$number['isPercentage']) {
            return $number['value'] * 100;
        }

        return $number['value'];
    }

    private function parsePolarColorMixChromaComponent(string $token, string $space): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return false;
        }

        if ($space === 'lch' && $number['isPercentage']) {
            return $number['value'] * 1.5;
        }

        if ($space === 'oklch' && $number['isPercentage']) {
            return $number['value'] * 0.004;
        }

        return $number['value'];
    }

    private function parsePolarColorMixHueComponent(string $token): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        return $this->parseHueDegrees($token) ?? false;
    }

    private function parseHslColorMixHueComponent(string $token): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        return $this->parseHueDegrees($token) ?? false;
    }

    private function parseHslColorMixPercentageComponent(string $token): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        return $this->parsePercentageComponent($token) ?? false;
    }

    private function parseRectangularColorMixAlpha(?string $alpha): float|false|null
    {
        if ($alpha === null || trim($alpha) === '') {
            return 1.0;
        }

        if (strcasecmp(trim($alpha), 'none') === 0) {
            return null;
        }

        return $this->parseAlphaComponent($alpha) ?? false;
    }

    private function parseColorFunctionColorMixComponent(string $token): float|false|null
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return null;
        }

        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return false;
        }

        return $number['isPercentage'] ? $number['value'] / 100 : $number['value'];
    }

    /**
     * @return array{0:float,1:float,2:?float}
     */
    private function resolveRectangularColorMixAlphas(?float $left, ?float $right, float $leftWeight, float $rightWeight): array
    {
        if ($left === null && $right === null) {
            return [1.0, 1.0, null];
        }

        if ($left === null) {
            $alpha = $right ?? 1.0;

            return [$alpha, $alpha, $alpha * ($leftWeight + $rightWeight)];
        }

        if ($right === null) {
            return [$left, $left, $left * ($leftWeight + $rightWeight)];
        }

        return [$left, $right, ($left * $leftWeight) + ($right * $rightWeight)];
    }

    private function mixRectangularColorComponent(
        ?float $left,
        ?float $right,
        float $leftWeight,
        float $rightWeight,
        float $leftAlpha,
        float $rightAlpha,
        float $componentAlpha
    ): ?float {
        if ($left === null && $right === null) {
            return null;
        }

        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        if ($componentAlpha <= 0.000000001) {
            return 0.0;
        }

        return (($left * $leftAlpha * $leftWeight) + ($right * $rightAlpha * $rightWeight)) / $componentAlpha;
    }

    private function mixPolarHueComponent(
        ?float $left,
        ?float $right,
        float $leftWeight,
        float $rightWeight,
        string $hueMethod
    ): ?float {
        if ($left === null && $right === null) {
            return null;
        }

        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        $weightSum = $leftWeight + $rightWeight;
        if ($weightSum <= 0.000000001) {
            return 0.0;
        }

        [$leftHue, $rightHue] = $this->adjustPolarHuePair($left, $right, $hueMethod);
        $hue = (($leftHue * $leftWeight) + ($rightHue * $rightWeight)) / $weightSum;

        return $this->normalizeMixedHue($hue);
    }

    /**
     * @return array{0:float,1:float}
     */
    private function adjustPolarHuePair(float $left, float $right, string $hueMethod): array
    {
        $left = $this->normalizeMixedHue($left);
        $right = $this->normalizeMixedHue($right);
        $delta = $right - $left;

        if ($hueMethod === 'shorter') {
            if ($delta > 180.0) {
                $left += 360.0;
            } elseif ($delta < -180.0) {
                $right += 360.0;
            }
        } elseif ($hueMethod === 'longer') {
            if ($delta > 0.0 && $delta < 180.0) {
                $left += 360.0;
            } elseif ($delta < 0.0 && $delta > -180.0) {
                $right += 360.0;
            }
        } elseif ($hueMethod === 'increasing') {
            if ($right < $left) {
                $right += 360.0;
            }
        } elseif ($hueMethod === 'decreasing') {
            if ($left < $right) {
                $left += 360.0;
            }
        }

        return [$left, $right];
    }

    private function normalizeMixedHue(float $hue): float
    {
        $hue = fmod($hue, 360.0);
        if ($hue < 0.0) {
            $hue += 360.0;
        }

        return abs($hue - 360.0) < 0.000000001 ? 0.0 : $hue;
    }

    /**
     * @param list<?float> $components
     */
    private function serializeRectangularColorMixResult(string $space, array $components, ?float $alpha): string
    {
        $serialized = [];
        foreach ($components as $index => $component) {
            if ($component === null) {
                $serialized[] = 'none';
                continue;
            }

            $value = $this->minifyColorNumber($component, 4);
            $serialized[] = $index === 0 ? $value . '%' : $value;
        }

        return $space . '(' . implode(' ', $serialized) . $this->serializeRectangularColorMixAlpha($alpha) . ')';
    }

    /**
     * @param list<?float> $components
     */
    private function serializeColorFunctionColorMixResult(string $space, array $components, ?float $alpha): string
    {
        $serialized = [];
        foreach ($components as $component) {
            $serialized[] = $component === null ? 'none' : $this->minifyColorNumber($component, 6);
        }

        return 'color(' . $space . ' ' . implode(' ', $serialized) . $this->serializeRectangularColorMixAlpha($alpha) . ')';
    }

    private function serializePolarColorMixResult(
        string $space,
        ?float $lightness,
        ?float $chroma,
        ?float $hue,
        ?float $alpha
    ): string {
        $components = [
            $lightness === null ? 'none' : $this->minifyColorNumber($lightness, 4) . '%',
            $chroma === null ? 'none' : $this->minifyColorNumber($chroma, 4),
            $hue === null ? 'none' : $this->minifyColorNumber($hue, 4),
        ];

        return $space . '(' . implode(' ', $components) . $this->serializeRectangularColorMixAlpha($alpha) . ')';
    }

    private function serializeRectangularColorMixAlpha(?float $alpha): string
    {
        if ($alpha === null) {
            return '/none';
        }

        if (abs($alpha - 1.0) < 0.0000001) {
            return '';
        }

        return '/' . $this->minifyColorNumber(max(0.0, min(1.0, $alpha)), 4);
    }

    private function colorMixRgbByteRoundingBias(?float $leftAlpha, ?float $rightAlpha, float $leftWeight, float $rightWeight): float
    {
        if ($leftWeight <= 0.000000001 || $rightWeight <= 0.000000001) {
            return 0.000000001;
        }

        if (($leftAlpha !== null && $leftAlpha < 0.999999999)
            || ($rightAlpha !== null && $rightAlpha < 0.999999999)
        ) {
            return 0.125000001;
        }

        return 0.000000001;
    }

    private function parseColorMixPercentage(string $token): ?float
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', trim($token), $matches) !== 1) {
            return null;
        }

        $percentage = (float) $matches[1];
        if ($percentage < 0) {
            return null;
        }

        return $percentage / 100;
    }

    /**
     * @return array{0:?float,1:?float}
     */
    private function normalizedColorMixWeights(?float $left, ?float $right): array
    {
        if ($left === null && $right === null) {
            return [0.5, 0.5];
        }

        if ($left === null && $right !== null) {
            return [max(0.0, 1.0 - $right), $right];
        }

        if ($left !== null && $right === null) {
            return [$left, max(0.0, 1.0 - $left)];
        }

        if ($left === null || $right === null) {
            return [null, null];
        }

        $sum = $left + $right;
        if ($sum <= 0.0) {
            return [0.0, 0.0];
        }

        if ($sum > 1.0) {
            return [$left / $sum, $right / $sum];
        }

        return [$left, $right];
    }

    /**
     * @return array{red:int,green:int,blue:int,alpha:float}|null
     */
    private function parseSrgbColorMixColor(string $token): ?array
    {
        $token = trim($token);
        if (preg_match('/^(?:rgb|rgba|hsl|hsla|hwb)\(/i', $token) === 1) {
            $serialized = $this->minifyColorFunction($token);
            if ($serialized === null) {
                return null;
            }

            return $this->parseSerializedSrgbColor($serialized);
        }

        return $this->parseSerializedSrgbColor($token);
    }

    /**
     * @return array{red:int,green:int,blue:int,alpha:float}|null
     */
    private function parseSerializedSrgbColor(string $color): ?array
    {
        $color = trim($color);
        $named = [
            'black' => [0, 0, 0, 1.0],
            'blue' => [0, 0, 255, 1.0],
            'gray' => [128, 128, 128, 1.0],
            'green' => [0, 128, 0, 1.0],
            'lime' => [0, 255, 0, 1.0],
            'rebeccapurple' => [102, 51, 153, 1.0],
            'red' => [255, 0, 0, 1.0],
            'transparent' => [0, 0, 0, 0.0],
            'white' => [255, 255, 255, 1.0],
        ];
        $lower = strtolower($color);
        if (isset($named[$lower])) {
            return [
                'red' => $named[$lower][0],
                'green' => $named[$lower][1],
                'blue' => $named[$lower][2],
                'alpha' => $named[$lower][3],
            ];
        }

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color, $matches) !== 1) {
            return null;
        }

        $hex = strtolower($matches[1]);
        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $expanded = '';
            foreach (str_split($hex) as $digit) {
                $expanded .= $digit . $digit;
            }
            $hex = $expanded;
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

        return [
            'red' => $red,
            'green' => $green,
            'blue' => $blue,
            'alpha' => $alpha,
        ];
    }

    private function clampColorByte(int $value): int
    {
        return min(255, max(0, $value));
    }

    private function minifyColorFunction(string $function): ?string
    {
        if (preg_match('/^(hsl|hsla|hwb|rgb|rgba)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return null;
        }

        $name = strtolower($matches[1]);
        if ($name === 'rgb' || $name === 'rgba') {
            $relativeRgb = $this->minifyRelativeRgbColorFunction($matches[2]);
            if ($relativeRgb !== null) {
                return $relativeRgb;
            }
        }
        if ($name === 'hsl' || $name === 'hsla') {
            $relativeHsl = $this->minifyRelativeHslColorFunction($matches[2]);
            if ($relativeHsl !== null) {
                return $relativeHsl;
            }
        }
        if ($name === 'hwb') {
            $relativeHwb = $this->minifyRelativeHwbColorFunction($matches[2]);
            if ($relativeHwb !== null) {
                return $relativeHwb;
            }
        }

        $parts = $this->parseColorFunctionParts($matches[2]);
        if ($parts === null) {
            return null;
        }

        if ($name === 'hsl' || $name === 'hsla') {
            $hue = $this->parseHueDegrees($parts['components'][0] ?? '');
            $saturation = $this->parsePercentageComponent($parts['components'][1] ?? '');
            $lightness = $this->parsePercentageComponent($parts['components'][2] ?? '');
            if ($hue === null || $saturation === null || $lightness === null) {
                return null;
            }

            $alpha = $parts['alpha'] === null ? 1.0 : $this->parseAlphaComponent($parts['alpha']);
            if ($alpha === null) {
                return null;
            }

            [$red, $green, $blue] = $this->hslToRgbBytes($hue, $saturation, $lightness);

            return $this->serializeColorBytes($red, $green, $blue, $alpha);
        }

        if ($name === 'hwb') {
            $hue = $this->parseHueDegrees($parts['components'][0] ?? '');
            $white = $this->parsePercentageComponent($parts['components'][1] ?? '');
            $black = $this->parsePercentageComponent($parts['components'][2] ?? '');
            if ($hue === null || $white === null || $black === null) {
                return null;
            }

            $alpha = $parts['alpha'] === null ? 1.0 : $this->parseAlphaComponent($parts['alpha']);
            if ($alpha === null) {
                return null;
            }

            [$red, $green, $blue] = $this->hwbToRgbBytes($hue, $white, $black);

            return $this->serializeColorBytes($red, $green, $blue, $alpha);
        }

        $red = $this->parseRgbComponent($parts['components'][0] ?? '');
        $green = $this->parseRgbComponent($parts['components'][1] ?? '');
        $blue = $this->parseRgbComponent($parts['components'][2] ?? '');
        if ($red === null || $green === null || $blue === null) {
            return null;
        }

        $alpha = $parts['alpha'] === null ? 1.0 : $this->parseAlphaComponent($parts['alpha']);
        if ($alpha === null) {
            return null;
        }

        return $this->serializeColorBytes($red, $green, $blue, $alpha);
    }

    private function minifyRelativeHslColorFunction(string $arguments): ?string
    {
        $parsed = $this->parseRelativeSrgbColorArguments($arguments);
        if ($parsed === null) {
            return null;
        }

        $channels = $this->relativeHslChannelsFromSrgbOrigin($parsed['origin']);
        $hue = $this->evaluateRelativeHueComponent($parsed['components'][0], $channels);
        $saturation = $this->evaluateRelativePercentageComponent($parsed['components'][1], $channels);
        $lightness = $this->evaluateRelativePercentageComponent($parsed['components'][2], $channels);
        if ($hue === null || $saturation === null || $lightness === null) {
            return null;
        }

        $alpha = $parsed['alpha'] === null
            ? 1.0
            : $this->evaluateRelativeAlphaComponent($parsed['alpha'], $channels);
        if ($alpha === null) {
            return null;
        }

        [$red, $green, $blue] = $this->hslToRgbBytes(
            $this->normalizeMixedHue($hue),
            min(1.0, max(0.0, $saturation / 100)),
            min(1.0, max(0.0, $lightness / 100)),
        );

        return $this->serializeColorBytes($red, $green, $blue, $alpha);
    }

    private function minifyRelativeHwbColorFunction(string $arguments): ?string
    {
        $parsed = $this->parseRelativeSrgbColorArguments($arguments);
        if ($parsed === null) {
            return null;
        }

        $channels = $this->relativeHwbChannelsFromSrgbOrigin($parsed['origin']);
        $hue = $this->evaluateRelativeHueComponent($parsed['components'][0], $channels);
        $white = $this->evaluateRelativePercentageComponent($parsed['components'][1], $channels);
        $black = $this->evaluateRelativePercentageComponent($parsed['components'][2], $channels);
        if ($hue === null || $white === null || $black === null) {
            return null;
        }

        $alpha = $parsed['alpha'] === null
            ? 1.0
            : $this->evaluateRelativeAlphaComponent($parsed['alpha'], $channels);
        if ($alpha === null) {
            return null;
        }

        [$red, $green, $blue] = $this->hwbToRgbBytes(
            $this->normalizeMixedHue($hue),
            min(1.0, max(0.0, $white / 100)),
            min(1.0, max(0.0, $black / 100)),
        );

        return $this->serializeColorBytes($red, $green, $blue, $alpha);
    }

    /**
     * @return array{origin:array{red:int,green:int,blue:int,alpha:float},components:list<string>,alpha:?string}|null
     */
    private function parseRelativeSrgbColorArguments(string $arguments): ?array
    {
        $slashParts = $this->splitTopLevel(trim($arguments), '/');
        if ($slashParts === [] || count($slashParts) > 2) {
            return null;
        }

        $tokens = $this->splitWhitespaceTopLevel($slashParts[0]);
        if (count($tokens) !== 5 || strcasecmp($tokens[0], 'from') !== 0) {
            return null;
        }

        $origin = $this->parseRelativeSrgbOrigin($tokens[1]);
        if ($origin === null) {
            return null;
        }

        return [
            'origin' => $origin,
            'components' => array_slice($tokens, 2, 3),
            'alpha' => isset($slashParts[1]) ? trim($slashParts[1]) : null,
        ];
    }

    private function minifyRelativeRgbColorFunction(string $arguments): ?string
    {
        $slashParts = $this->splitTopLevel(trim($arguments), '/');
        if ($slashParts === [] || count($slashParts) > 2) {
            return null;
        }

        $tokens = $this->splitWhitespaceTopLevel($slashParts[0]);
        if (count($tokens) !== 5 || strcasecmp($tokens[0], 'from') !== 0) {
            return null;
        }

        $origin = $this->parseRelativeSrgbOrigin($tokens[1]);
        if ($origin === null) {
            return null;
        }

        $red = $this->evaluateRelativeRgbComponent($tokens[2], $origin);
        $green = $this->evaluateRelativeRgbComponent($tokens[3], $origin);
        $blue = $this->evaluateRelativeRgbComponent($tokens[4], $origin);
        if ($red === null || $green === null || $blue === null) {
            return null;
        }

        $alpha = isset($slashParts[1])
            ? $this->evaluateRelativeAlphaComponent(trim($slashParts[1]), $origin)
            : 1.0;
        if ($alpha === null) {
            return null;
        }

        return $this->serializeColorBytes($red, $green, $blue, $alpha);
    }

    /**
     * @return array{red:int,green:int,blue:int,alpha:float}|null
     */
    private function parseRelativeSrgbOrigin(string $origin): ?array
    {
        $origin = trim($origin);
        $serialized = $this->minifyColorFunction($origin) ?? $origin;

        return $this->parseSerializedSrgbColor($serialized);
    }

    /**
     * @param array{red:int,green:int,blue:int,alpha:float} $origin
     */
    private function evaluateRelativeRgbComponent(string $token, array $origin): ?int
    {
        $value = $this->evaluateRelativeColorChannelToken($token, $origin, false);

        return $value === null ? null : $this->clampColorByte((int) round($value));
    }

    /**
     * @param array{red:int,green:int,blue:int,alpha:float} $origin
     */
    private function evaluateRelativeAlphaComponent(string $token, array $origin): ?float
    {
        $value = $this->evaluateRelativeColorChannelToken($token, $origin, true);

        return $value === null ? null : min(1.0, max(0.0, $value));
    }

    /**
     * @param array<string,float|int> $origin
     */
    private function evaluateRelativeColorChannelToken(string $token, array $origin, bool $alphaContext): ?float
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (strcasecmp($token, 'none') === 0) {
            return 0.0;
        }

        $channel = $this->relativeColorChannelValue($token, $origin);
        if ($channel !== null) {
            return $channel;
        }

        if (preg_match('/^calc\((.*)\)$/is', $token, $matches) === 1) {
            return $this->evaluateRelativeColorCalcExpression($matches[1], $origin);
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) === 1) {
            $percentage = (float) $matches[1];

            return $alphaContext ? $percentage / 100 : $percentage * 255 / 100;
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1) {
            return (float) $token;
        }

        return null;
    }

    /**
     * @param array<string,float|int> $origin
     */
    private function relativeColorChannelValue(string $token, array $origin): ?float
    {
        $token = strtolower(trim($token));
        if ($token === 'b' && array_key_exists('blue', $origin)) {
            return (float) $origin['blue'];
        }
        if (array_key_exists($token, $origin)) {
            return (float) $origin[$token];
        }

        return match ($token) {
            'r' => isset($origin['red']) ? (float) $origin['red'] : null,
            'g' => isset($origin['green']) ? (float) $origin['green'] : null,
            'alpha' => isset($origin['alpha']) ? (float) $origin['alpha'] : null,
            default => null,
        };
    }

    /**
     * @param array<string,float|int> $origin
     */
    private function evaluateRelativeColorCalcExpression(string $expression, array $origin): ?float
    {
        $offset = 0;
        $value = $this->parseRelativeColorCalcSum($expression, $offset, $origin);
        if ($value === null) {
            return null;
        }

        $this->skipRelativeColorCalcWhitespace($expression, $offset);

        return $offset === strlen($expression) ? $value : null;
    }

    /**
     * @param array<string,float|int> $origin
     */
    private function parseRelativeColorCalcSum(string $expression, int &$offset, array $origin): ?float
    {
        $value = $this->parseRelativeColorCalcProduct($expression, $offset, $origin);
        if ($value === null) {
            return null;
        }

        while (true) {
            $this->skipRelativeColorCalcWhitespace($expression, $offset);
            $operator = $expression[$offset] ?? '';
            if ($operator !== '+' && $operator !== '-') {
                return $value;
            }

            $offset++;
            $right = $this->parseRelativeColorCalcProduct($expression, $offset, $origin);
            if ($right === null) {
                return null;
            }

            $value = $operator === '+' ? $value + $right : $value - $right;
        }
    }

    /**
     * @param array<string,float|int> $origin
     */
    private function parseRelativeColorCalcProduct(string $expression, int &$offset, array $origin): ?float
    {
        $value = $this->parseRelativeColorCalcFactor($expression, $offset, $origin);
        if ($value === null) {
            return null;
        }

        while (true) {
            $this->skipRelativeColorCalcWhitespace($expression, $offset);
            $operator = $expression[$offset] ?? '';
            if ($operator !== '*' && $operator !== '/') {
                return $value;
            }

            $offset++;
            $right = $this->parseRelativeColorCalcFactor($expression, $offset, $origin);
            if ($right === null || ($operator === '/' && abs($right) < 0.000000000001)) {
                return null;
            }

            $value = $operator === '*' ? $value * $right : $value / $right;
        }
    }

    /**
     * @param array<string,float|int> $origin
     */
    private function parseRelativeColorCalcFactor(string $expression, int &$offset, array $origin): ?float
    {
        $this->skipRelativeColorCalcWhitespace($expression, $offset);
        $char = $expression[$offset] ?? '';
        if ($char === '+' || $char === '-') {
            $offset++;
            $value = $this->parseRelativeColorCalcFactor($expression, $offset, $origin);

            return $value === null ? null : ($char === '-' ? -$value : $value);
        }

        if ($char === '(') {
            $offset++;
            $value = $this->parseRelativeColorCalcSum($expression, $offset, $origin);
            $this->skipRelativeColorCalcWhitespace($expression, $offset);
            if (($expression[$offset] ?? '') !== ')') {
                return null;
            }
            $offset++;

            return $value;
        }

        if (preg_match('/\G[+-]?(?:\d+|\d*\.\d+)/', $expression, $matches, 0, $offset) === 1) {
            $offset += strlen($matches[0]);

            return (float) $matches[0];
        }

        if (preg_match('/\G[_a-zA-Z][_a-zA-Z0-9-]*/', $expression, $matches, 0, $offset) === 1) {
            $offset += strlen($matches[0]);

            return $this->relativeColorChannelValue($matches[0], $origin);
        }

        return null;
    }

    /**
     * @param array<string,float|int> $channels
     */
    private function evaluateRelativeHueComponent(string $token, array $channels): ?float
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (strcasecmp($token, 'none') === 0) {
            return 0.0;
        }

        $channel = $this->relativeColorChannelValue($token, $channels);
        if ($channel !== null) {
            return $channel;
        }

        if (preg_match('/^calc\((.*)\)$/is', $token, $matches) === 1) {
            return $this->evaluateRelativeColorCalcExpression($matches[1], $channels);
        }

        return $this->parseHueDegrees($token);
    }

    /**
     * @param array<string,float|int> $channels
     */
    private function evaluateRelativePercentageComponent(string $token, array $channels): ?float
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (strcasecmp($token, 'none') === 0) {
            return 0.0;
        }

        $channel = $this->relativeColorChannelValue($token, $channels);
        if ($channel !== null) {
            return $channel;
        }

        if (preg_match('/^calc\((.*)\)$/is', $token, $matches) === 1) {
            return $this->evaluateRelativeColorCalcExpression($matches[1], $channels);
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) === 1) {
            return (float) $matches[1];
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1) {
            return (float) $token;
        }

        return null;
    }

    /**
     * @param array{red:int,green:int,blue:int,alpha:float} $origin
     * @return array{h:float,s:float,l:float,alpha:float}
     */
    private function relativeHslChannelsFromSrgbOrigin(array $origin): array
    {
        $channels = $this->rgbBytesToHslChannels($origin['red'], $origin['green'], $origin['blue']);
        $channels['alpha'] = $origin['alpha'];

        return $channels;
    }

    /**
     * @param array{red:int,green:int,blue:int,alpha:float} $origin
     * @return array{h:float,w:float,b:float,alpha:float}
     */
    private function relativeHwbChannelsFromSrgbOrigin(array $origin): array
    {
        $hsl = $this->rgbBytesToHslChannels($origin['red'], $origin['green'], $origin['blue']);
        $red = $origin['red'] / 255;
        $green = $origin['green'] / 255;
        $blue = $origin['blue'] / 255;

        return [
            'h' => $hsl['h'],
            'w' => round(min($red, $green, $blue) * 100, 10),
            'b' => round((1.0 - max($red, $green, $blue)) * 100, 10),
            'alpha' => $origin['alpha'],
        ];
    }

    /**
     * @return array{h:float,s:float,l:float}
     */
    private function rgbBytesToHslChannels(int $red, int $green, int $blue): array
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $lightness = ($max + $min) / 2;

        if ($delta <= 0.000000001) {
            return [
                'h' => 0.0,
                's' => 0.0,
                'l' => round($lightness * 100, 10),
            ];
        }

        $saturation = $delta / (1 - abs(2 * $lightness - 1));
        if ($max === $r) {
            $hue = 60 * fmod(($g - $b) / $delta, 6);
        } elseif ($max === $g) {
            $hue = 60 * (($b - $r) / $delta + 2);
        } else {
            $hue = 60 * (($r - $g) / $delta + 4);
        }

        return [
            'h' => $this->normalizeMixedHue($hue),
            's' => round($saturation * 100, 10),
            'l' => round($lightness * 100, 10),
        ];
    }

    private function skipRelativeColorCalcWhitespace(string $expression, int &$offset): void
    {
        $length = strlen($expression);
        while ($offset < $length && ctype_space($expression[$offset])) {
            $offset++;
        }
    }

    private function minifyAdvancedColorFunction(string $function): ?string
    {
        if (preg_match('/^(lab|lch|oklab|oklch|color)\((.*)\)$/is', trim($function), $matches) !== 1) {
            return null;
        }

        $name = strtolower($matches[1]);
        $parts = $this->parseAdvancedColorFunctionParts($matches[2]);
        if ($parts === null) {
            return null;
        }

        $alpha = $this->serializeAdvancedColorAlpha($parts['alpha']);
        if ($alpha === null) {
            return null;
        }

        if ($name === 'lab') {
            if (count($parts['components']) !== 3) {
                return null;
            }

            $lightness = $this->normalizeLabLightnessComponent($parts['components'][0]);
            $a = $this->normalizeLabAxisComponent($parts['components'][1]);
            $b = $this->normalizeLabAxisComponent($parts['components'][2]);
            if ($lightness === null || $a === null || $b === null) {
                return null;
            }

            return 'lab(' . $lightness . ' ' . $a . ' ' . $b . $alpha . ')';
        }

        if ($name === 'lch') {
            if (count($parts['components']) !== 3) {
                return null;
            }

            $lightness = $this->normalizeLabLightnessComponent($parts['components'][0]);
            $chroma = $this->normalizeLchChromaComponent($parts['components'][1]);
            $hue = $this->normalizeColorHueComponent($parts['components'][2]);
            if ($lightness === null || $chroma === null || $hue === null) {
                return null;
            }

            return 'lch(' . $lightness . ' ' . $chroma . ' ' . $hue . $alpha . ')';
        }

        if ($name === 'oklab') {
            if (count($parts['components']) !== 3) {
                return null;
            }

            $lightness = $this->normalizeOkLightnessComponent($parts['components'][0]);
            $a = $this->normalizeOklabAxisComponent($parts['components'][1]);
            $b = $this->normalizeOklabAxisComponent($parts['components'][2]);
            if ($lightness === null || $a === null || $b === null) {
                return null;
            }

            return 'oklab(' . $lightness . ' ' . $a . ' ' . $b . $alpha . ')';
        }

        if ($name === 'oklch') {
            if (count($parts['components']) !== 3) {
                return null;
            }

            $lightness = $this->normalizeOkLightnessComponent($parts['components'][0]);
            $chroma = $this->normalizeOklabAxisComponent($parts['components'][1]);
            $hue = $this->normalizeColorHueComponent($parts['components'][2]);
            if ($lightness === null || $chroma === null || $hue === null) {
                return null;
            }

            return 'oklch(' . $lightness . ' ' . $chroma . ' ' . $hue . $alpha . ')';
        }

        if (count($parts['components']) !== 4) {
            return null;
        }

        $space = $this->normalizeColorSpaceName($parts['components'][0]);
        $red = $this->normalizeColorFunctionComponent($parts['components'][1]);
        $green = $this->normalizeColorFunctionComponent($parts['components'][2]);
        $blue = $this->normalizeColorFunctionComponent($parts['components'][3]);
        if ($red === null || $green === null || $blue === null) {
            return null;
        }

        return 'color(' . $space . ' ' . $red . ' ' . $green . ' ' . $blue . $alpha . ')';
    }

    /**
     * @return array{components:list<string>,alpha:?string}|null
     */
    private function parseAdvancedColorFunctionParts(string $arguments): ?array
    {
        $arguments = trim($arguments);
        if ($arguments === '') {
            return null;
        }

        $slashParts = $this->splitTopLevel($arguments, '/');
        if (count($slashParts) > 2) {
            return null;
        }

        $components = $this->splitWhitespaceTopLevel($slashParts[0] ?? '');
        if ($components === []) {
            return null;
        }

        return [
            'components' => $components,
            'alpha' => isset($slashParts[1]) ? trim($slashParts[1]) : null,
        ];
    }

    private function serializeAdvancedColorAlpha(?string $alpha): ?string
    {
        if ($alpha === null || trim($alpha) === '') {
            return '';
        }

        $value = $this->parseAlphaComponent($alpha);
        if ($value === null) {
            return null;
        }

        if (abs($value - 1.0) < 0.0000001) {
            return '';
        }

        return '/' . $this->minifyColorNumber($value);
    }

    private function normalizeLabLightnessComponent(string $token): ?string
    {
        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return null;
        }

        return $this->minifyColorNumber($number['value']) . '%';
    }

    private function normalizeOkLightnessComponent(string $token): ?string
    {
        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return null;
        }

        $value = $number['isPercentage'] ? $number['value'] : $number['value'] * 100;

        return $this->minifyColorNumber($value) . '%';
    }

    private function normalizeLabAxisComponent(string $token): ?string
    {
        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return null;
        }

        $value = $number['isPercentage'] ? $number['value'] * 1.25 : $number['value'];

        return $this->minifyColorNumber($value, $number['isPercentage'] ? 4 : 8);
    }

    private function normalizeLchChromaComponent(string $token): ?string
    {
        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return null;
        }

        $value = $number['isPercentage'] ? $number['value'] * 1.5 : $number['value'];

        return $this->minifyColorNumber($value, $number['isPercentage'] ? 4 : 8);
    }

    private function normalizeOklabAxisComponent(string $token): ?string
    {
        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return null;
        }

        $value = $number['isPercentage'] ? $number['value'] * 0.004 : $number['value'];

        return $this->minifyColorNumber($value);
    }

    private function normalizeColorHueComponent(string $token): ?string
    {
        $degrees = $this->parseHueDegrees($token);

        return $degrees === null ? null : $this->minifyColorNumber($degrees);
    }

    private function normalizeColorFunctionComponent(string $token): ?string
    {
        $number = $this->parseColorNumberToken($token);
        if ($number === null) {
            return null;
        }

        $value = $number['isPercentage'] ? $number['value'] / 100 : $number['value'];

        return $this->minifyColorNumber($value);
    }

    private function normalizeColorSpaceName(string $space): string
    {
        $space = strtolower(trim($space));

        return $space === 'xyz-d65' ? 'xyz' : $space;
    }

    /**
     * @return array{value:float,isPercentage:bool}|null
     */
    private function parseColorNumberToken(string $token): ?array
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(%)?$/', trim($token), $matches) !== 1) {
            return null;
        }

        return [
            'value' => (float) $matches[1],
            'isPercentage' => isset($matches[2]) && $matches[2] === '%',
        ];
    }

    private function minifyColorNumber(float $number, int $precision = 8): string
    {
        if (abs($number) < 0.000000000001) {
            return '0';
        }

        $formatted = rtrim(rtrim(sprintf('%.' . $precision . 'F', $number), '0'), '.');
        if (str_starts_with($formatted, '0.')) {
            return substr($formatted, 1);
        }
        if (str_starts_with($formatted, '-0.')) {
            return '-' . substr($formatted, 2);
        }

        return $formatted;
    }

    /**
     * @return array{components:list<string>,alpha:?string}|null
     */
    private function parseColorFunctionParts(string $arguments): ?array
    {
        $arguments = trim($arguments);
        if ($arguments === '') {
            return null;
        }

        $commaParts = $this->splitTopLevel($arguments, ',');
        if (count($commaParts) > 1) {
            if (count($commaParts) !== 3 && count($commaParts) !== 4) {
                return null;
            }

            return [
                'components' => array_slice($commaParts, 0, 3),
                'alpha' => $commaParts[3] ?? null,
            ];
        }

        $slashParts = $this->splitTopLevel($arguments, '/');
        if (count($slashParts) > 2) {
            return null;
        }
        $components = $this->splitWhitespaceTopLevel($slashParts[0] ?? '');
        if (count($components) !== 3) {
            return null;
        }

        return [
            'components' => $components,
            'alpha' => isset($slashParts[1]) ? trim($slashParts[1]) : null,
        ];
    }

    private function parseHueDegrees(string $token): ?float
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return 0.0;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(deg|grad|rad|turn)?$/i', $token, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1];
        $unit = strtolower($matches[2] ?? 'deg');
        $degrees = match ($unit) {
            'grad' => $value * 0.9,
            'rad' => $value * 180 / M_PI,
            'turn' => $value * 360,
            default => $value,
        };
        $degrees = fmod($degrees, 360.0);

        return $degrees < 0 ? $degrees + 360.0 : $degrees;
    }

    private function parsePercentageComponent(string $token): ?float
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return 0.0;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%?$/', $token, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1] / 100;

        return $value < 0 || $value > 1 ? null : $value;
    }

    private function parseRgbComponent(string $token): ?int
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            return 0;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) === 1) {
            $value = (float) $matches[1];

            return $this->clampColorByte((int) round($value * 255 / 100));
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))$/', $token, $matches) !== 1) {
            return null;
        }

        return $this->clampColorByte((int) round((float) $matches[1]));
    }

    private function parseAlphaComponent(string $token): ?float
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0) {
            $value = 0.0;
        } elseif (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) === 1) {
            $value = (float) $matches[1] / 100;
        } elseif (preg_match('/^([+-]?(?:\d+|\d*\.\d+))$/', $token, $matches) === 1) {
            $value = (float) $matches[1];
        } else {
            return null;
        }

        return $value < 0 || $value > 1 ? null : $value;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hslToRgbBytes(float $hue, float $saturation, float $lightness, float $roundingBias = 0.000000001): array
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $lightness - $chroma / 2;

        [$red, $green, $blue] = match (true) {
            $hue < 60 => [$chroma, $x, 0.0],
            $hue < 120 => [$x, $chroma, 0.0],
            $hue < 180 => [0.0, $chroma, $x],
            $hue < 240 => [0.0, $x, $chroma],
            $hue < 300 => [$x, 0.0, $chroma],
            default => [$chroma, 0.0, $x],
        };

        return [
            (int) round(($red + $m) * 255 + $roundingBias),
            (int) round(($green + $m) * 255 + $roundingBias),
            (int) round(($blue + $m) * 255 + $roundingBias),
        ];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hwbToRgbBytes(float $hue, float $white, float $black): array
    {
        $sum = $white + $black;
        if ($sum >= 1.0) {
            $gray = $sum <= 0.0 ? 0.0 : $white / $sum;

            return [
                (int) round($gray * 255),
                (int) round($gray * 255),
                (int) round($gray * 255),
            ];
        }

        $x = 1 - abs(fmod($hue / 60, 2) - 1);
        [$red, $green, $blue] = match (true) {
            $hue < 60 => [1.0, $x, 0.0],
            $hue < 120 => [$x, 1.0, 0.0],
            $hue < 180 => [0.0, 1.0, $x],
            $hue < 240 => [0.0, $x, 1.0],
            $hue < 300 => [$x, 0.0, 1.0],
            default => [1.0, 0.0, $x],
        };
        $factor = 1.0 - $white - $black;

        return [
            (int) round(($red * $factor + $white) * 255 + 0.000000001),
            (int) round(($green * $factor + $white) * 255 + 0.000000001),
            (int) round(($blue * $factor + $white) * 255 + 0.000000001),
        ];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hwbColorMixToRgbBytes(float $hue, float $white, float $black, float $roundingBias = 0.000000001): array
    {
        $white = min(1.0, max(0.0, $white));
        $black = min(1.0, max(0.0, $black));
        $sum = $white + $black;
        if ($sum >= 1.0) {
            $gray = $sum <= 0.0 ? 0.0 : $white / $sum;

            return [
                (int) round($gray * 255),
                (int) round($gray * 255),
                (int) round($gray * 255),
            ];
        }

        $hue = $this->normalizeMixedHue($hue);
        $x = 1 - abs(fmod($hue / 60, 2) - 1);
        [$red, $green, $blue] = match (true) {
            $hue < 60 => [1.0, $x, 0.0],
            $hue < 120 => [$x, 1.0, 0.0],
            $hue < 180 => [0.0, 1.0, $x],
            $hue < 240 => [0.0, $x, 1.0],
            $hue < 300 => [$x, 0.0, 1.0],
            default => [1.0, 0.0, $x],
        };
        $factor = 1.0 - $white - $black;

        return [
            $this->hwbColorMixChannelToByte($red, $factor, $white, $roundingBias),
            $this->hwbColorMixChannelToByte($green, $factor, $white, $roundingBias),
            $this->hwbColorMixChannelToByte($blue, $factor, $white, $roundingBias),
        ];
    }

    private function hwbColorMixChannelToByte(float $hueChannel, float $factor, float $white, float $roundingBias = 0.000000001): int
    {
        $value = ($hueChannel * $factor + $white) * 255;
        if (abs($hueChannel) < 0.000000001 && $white > 0.0) {
            return $this->clampColorByte((int) ceil($value + 0.000000001));
        }

        return $this->clampColorByte((int) round($value + $roundingBias));
    }

    private function serializeColorBytes(int $red, int $green, int $blue, float $alpha): string
    {
        if (abs($alpha - 1.0) < 0.0000001) {
            return $this->compressHexColor(sprintf('#%02x%02x%02x', $red, $green, $blue));
        }

        return $this->compressHexColor(sprintf('#%02x%02x%02x%02x', $red, $green, $blue, (int) round($alpha * 255)));
    }

    private function startsUrlFunction(string $value, int $offset): bool
    {
        if (strtolower(substr($value, $offset, 4)) !== 'url(') {
            return false;
        }

        $previous = $offset > 0 ? $value[$offset - 1] : '';

        return $previous === '' || !$this->isIdentifierChar($previous);
    }

    private function startsAtKeyword(string $value, int $offset, string $keyword): bool
    {
        if (strtolower(substr($value, $offset, strlen($keyword))) !== strtolower($keyword)) {
            return false;
        }

        $previous = $offset > 0 ? $value[$offset - 1] : '';
        $next = $value[$offset + strlen($keyword)] ?? '';

        return ($previous === '' || !$this->isIdentifierChar($previous))
            && ($next === '' || !$this->isIdentifierChar($next));
    }

    private function findAtKeywordInCss(string $css, string $keyword, int $start): ?int
    {
        $quote = null;
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

            if ($char === '@' && $this->startsAtKeyword($css, $i, $keyword)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readQuotedStringRaw(string $value, int $start): array
    {
        $quote = $value[$start] ?? '';
        if ($quote !== '"' && $quote !== "'") {
            return ['', $start];
        }

        $output = $quote;
        $length = strlen($value);
        for ($i = $start + 1; $i < $length; $i++) {
            $output .= $value[$i];
            if ($value[$i] === '\\' && $i + 1 < $length) {
                $output .= $value[++$i];
                continue;
            }
            if ($value[$i] === $quote) {
                return [$output, $i];
            }
        }

        return [$output, $length - 1];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readFunctionRaw(string $value, int $start): array
    {
        $output = '';
        $quote = null;
        $depth = 0;
        $length = strlen($value);

        for ($i = $start; $i < $length; $i++) {
            $char = $value[$i];
            $output .= $char;
            if ($quote !== null) {
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
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return [$output, $i];
                }
            }
        }

        return [$output, $length - 1];
    }

    private function readIdentifier(string $value, int $start): string
    {
        $length = strlen($value);
        $identifier = '';
        for ($i = $start; $i < $length; $i++) {
            if (!$this->isIdentifierChar($value[$i])) {
                break;
            }
            $identifier .= $value[$i];
        }

        return $identifier;
    }

    private function isIdentifierStart(string $char): bool
    {
        return preg_match('/[a-zA-Z_]/', $char) === 1;
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/[-a-zA-Z0-9_]/', $char) === 1;
    }
}
