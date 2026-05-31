<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssModulesTransformer
{
    private string $hash = 'EgL3uq';
    private string $pattern = '[hash]_[local]';
    private bool $dashedIdents = false;

    /**
     * @var array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     */
    private array $exports = [];

    /**
     * @var array<string, array{type:string, name:string, specifier:string}>
     */
    private array $references = [];

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>,
     *   references:array<string, array{type:string, name:string, specifier:string}>
     * }
     *
     * @param array{hash?:string, pattern?:string, minify?:bool, dashedIdents?:bool, dashed_idents?:bool} $options
     */
    public function transform(string $css, array $options = []): array
    {
        $this->hash = $options['hash'] ?? 'EgL3uq';
        $this->pattern = $options['pattern'] ?? '[hash]_[local]';
        $this->dashedIdents = ($options['dashedIdents'] ?? $options['dashed_idents'] ?? false) === true;
        $this->exports = [];
        $this->references = [];

        $code = $this->transformRuleList($this->stripComments($css), 0);
        if (($options['minify'] ?? true) === true) {
            $code = (new NestingTransformer())->lower($code);
        }

        return [
            'code' => $code,
            'exports' => $this->exports,
            'references' => $this->references,
        ];
    }

    private function transformRuleList(string $css, int $styleNestingDepth): string
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
                $output .= $prelude . '{' . $this->transformAtRuleBody($trimmedPrelude, $body, $styleNestingDepth) . '}';
                $cursor = $close + 1;
                continue;
            }

            [$selector, $locals] = $this->rewriteSelectorList($prelude);
            [$rewrittenBody, $composes] = $this->rewriteStyleBody($body, $styleNestingDepth);
            $this->assertValidComposesSelector($prelude, $composes);
            $this->addComposesToLocals($locals, $composes);

            $output .= $selector . '{' . $rewrittenBody . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function transformAtRuleBody(string $prelude, string $body, int $styleNestingDepth): string
    {
        if (preg_match('/^@(?:-[a-z]+-)?keyframes\b/i', $prelude) === 1) {
            return $body;
        }

        if (strcasecmp($prelude, '@view-transition') === 0) {
            return $this->rewriteViewTransitionDeclarationList($body);
        }

        if ($styleNestingDepth > 0) {
            $this->assertNoNestedComposesInAtRuleDeclarations($body);
        }

        return $this->transformRuleList($body, $styleNestingDepth);
    }

    /**
     * @return array{0:string,1:list<array{type:string, name:string, specifier?:string}>}
     */
    private function rewriteStyleBody(string $body, int $styleNestingDepth): array
    {
        $output = '';
        $composes = [];
        $cursor = 0;

        while (true) {
            $nextBlock = $this->findNextTopLevel($body, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($body, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = substr($body, $cursor, $nextStatement - $cursor + 1);
                $output .= $this->rewriteDeclarationStatement($statement, $composes, $styleNestingDepth);
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $output .= $this->rewriteTrailingDeclarations(substr($body, $cursor), $composes, $styleNestingDepth);
                break;
            }

            $prefix = substr($body, $cursor, $nextBlock - $cursor);
            [$declarations, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $output .= $this->rewriteTrailingDeclarations($declarations, $composes, $styleNestingDepth);
            $trimmedNested = trim($nestedPrelude);
            $close = $this->findMatchingBrace($body, $nextBlock);
            $nestedBody = substr($body, $nextBlock + 1, $close - $nextBlock - 1);

            if ($trimmedNested !== '' && $trimmedNested[0] === '@') {
                $output .= $nestedPrelude . '{' . $this->transformAtRuleBody($trimmedNested, $nestedBody, $styleNestingDepth + 1) . '}';
            } else {
                [$selector, $locals] = $this->rewriteSelectorList($nestedPrelude);
                [$rewrittenNestedBody, $nestedComposes] = $this->rewriteStyleBody($nestedBody, $styleNestingDepth + 1);
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
    private function rewriteDeclarationStatement(string $statement, array &$composes, int $styleNestingDepth): string
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
            $rawProperty = trim(substr($withoutSemicolon, 0, $colon));
            $value = trim(substr($withoutSemicolon, $colon + 1));
            $rewrittenProperty = $rawProperty;
            if ($this->dashedIdents && str_starts_with($rawProperty, '--')) {
                $rewrittenProperty = $this->scopeDashedIdent($rawProperty, false);
            }

            $rewrittenValue = $this->rewriteCssModuleDeclarationValue($property, $value);
            if ($this->dashedIdents) {
                $rewrittenValue = $this->rewriteDashedIdentReferences($rewrittenValue ?? $value);
            }

            if ($rewrittenValue === null && $rewrittenProperty === $rawProperty) {
                return $statement;
            }

            $trailingSemicolon = str_ends_with(rtrim($statement), ';') ? ';' : '';

            return $rewrittenProperty . ':' . ($rewrittenValue ?? $value) . $trailingSemicolon;
        }

        if ($styleNestingDepth > 0) {
            throw new \InvalidArgumentException('The `composes` property cannot be used within nested rules');
        }

        $value = trim(substr($withoutSemicolon, $colon + 1));
        array_push($composes, ...$this->parseComposesValue($value));

        return '';
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function rewriteTrailingDeclarations(string $source, array &$composes, int $styleNestingDepth): string
    {
        $output = '';
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($source, ';', $cursor)) !== null) {
            $statement = substr($source, $cursor, $semicolon - $cursor + 1);
            $output .= $this->rewriteDeclarationStatement($statement, $composes, $styleNestingDepth);
            $cursor = $semicolon + 1;
        }

        $tail = substr($source, $cursor);
        if (trim($tail) !== '') {
            $output .= $this->rewriteDeclarationStatement($tail, $composes, $styleNestingDepth);
        } else {
            $output .= $tail;
        }

        return $output;
    }

    private function rewriteCssModuleDeclarationValue(string $property, string $value): ?string
    {
        return match ($property) {
            'view-transition-name' => $this->rewriteViewTransitionNameValue($value),
            'view-transition-class' => $this->rewriteViewTransitionIdentList($value, ['none']),
            'view-transition-group' => $this->rewriteViewTransitionNameValue($value, ['contain']),
            default => null,
        };
    }

    /**
     * @param list<string> $additionalKeywords
     */
    private function rewriteViewTransitionNameValue(string $value, array $additionalKeywords = []): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        $lower = strtolower($trimmed);
        if (in_array($lower, array_merge(['none', 'auto'], $additionalKeywords), true)) {
            return $lower;
        }

        if (!$this->isPlainCssIdent($trimmed)) {
            return $value;
        }

        return $this->scopeCustomIdent($trimmed);
    }

    /**
     * @param list<string> $keywords
     */
    private function rewriteViewTransitionIdentList(string $value, array $keywords = []): string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return $value;
        }

        $rewritten = [];
        foreach ($tokens as $token) {
            $lower = strtolower($token);
            if (in_array($lower, $keywords, true) || !$this->isPlainCssIdent($token)) {
                $rewritten[] = $token;
                continue;
            }

            $rewritten[] = $this->scopeCustomIdent($token);
        }

        return implode(' ', $rewritten);
    }

    private function rewriteViewTransitionDeclarationList(string $body): string
    {
        if (str_contains($body, '{')) {
            return $body;
        }

        $output = '';
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($body, ';', $cursor)) !== null) {
            $statement = substr($body, $cursor, $semicolon - $cursor + 1);
            $output .= $this->rewriteViewTransitionDeclarationStatement($statement);
            $cursor = $semicolon + 1;
        }

        $tail = substr($body, $cursor);
        if (trim($tail) !== '') {
            $output .= $this->rewriteViewTransitionDeclarationStatement($tail);
        } else {
            $output .= $tail;
        }

        return $output;
    }

    private function rewriteViewTransitionDeclarationStatement(string $statement): string
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
        if ($property !== 'types') {
            return $statement;
        }

        $value = trim(substr($withoutSemicolon, $colon + 1));
        $trailingSemicolon = str_ends_with(rtrim($statement), ';') ? ';' : '';

        return 'types:' . $this->rewriteViewTransitionIdentList($value, ['none']) . $trailingSemicolon;
    }

    private function assertNoNestedComposesInAtRuleDeclarations(string $body): void
    {
        $cursor = 0;

        while (($nextBlock = $this->findNextTopLevel($body, '{', $cursor)) !== null) {
            $prefix = substr($body, $cursor, $nextBlock - $cursor);
            [$declarations] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $this->assertNoNestedComposesInDeclarationSource($declarations);

            $cursor = $this->findMatchingBrace($body, $nextBlock) + 1;
        }

        $this->assertNoNestedComposesInDeclarationSource(substr($body, $cursor));
    }

    private function assertNoNestedComposesInDeclarationSource(string $source): void
    {
        $composes = [];
        $this->rewriteTrailingDeclarations($source, $composes, 1);
    }

    /**
     * @return list<array{type:string, name:string, specifier?:string}>
     */
    private function parseComposesValue(string $value): array
    {
        $tokens = $this->tokenizeComposesValue($value);
        $fromIndex = null;
        foreach ($tokens as $index => $token) {
            if (strcasecmp($token, 'from') === 0) {
                $fromIndex = $index;
                break;
            }
        }

        $type = 'local';
        $specifier = null;
        $names = $tokens;

        if ($fromIndex !== null) {
            $names = array_slice($tokens, 0, $fromIndex);
            $from = array_slice($tokens, $fromIndex + 1);
            if (count($from) !== 1) {
                throw new \InvalidArgumentException('Invalid CSS Modules composes declaration');
            }

            if (strcasecmp($from[0], 'global') === 0) {
                $type = 'global';
            } else {
                $specifier = $this->parseQuotedSpecifier($from[0]);
                if ($specifier === null) {
                    throw new \InvalidArgumentException('Invalid CSS Modules composes declaration');
                }

                $type = 'dependency';
            }
        }

        if ($names === []) {
            throw new \InvalidArgumentException('Invalid CSS Modules composes declaration');
        }

        $references = [];
        foreach ($names as $name) {
            if (!$this->isValidComposesIdent($name)) {
                throw new \InvalidArgumentException('Invalid CSS Modules composes declaration');
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
     * @return list<string>
     */
    private function tokenizeComposesValue(string $value): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $value[++$i];
                    continue;
                }

                if ($char === $quote) {
                    $tokens[] = $current;
                    $current = '';
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                $quote = $char;
                $current = $char;
                continue;
            }

            if (ctype_space($char)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('Invalid CSS Modules composes declaration');
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private function parseQuotedSpecifier(string $token): ?string
    {
        $quote = $token[0] ?? '';
        if (($quote !== '"' && $quote !== "'") || substr($token, -1) !== $quote) {
            return null;
        }

        return substr($token, 1, -1);
    }

    private function isValidComposesIdent(string $name): bool
    {
        if (in_array(strtolower($name), ['from', 'initial', 'inherit', 'unset', 'default', 'revert', 'revert-layer'], true)) {
            return false;
        }

        return preg_match('/^-?(?:[A-Za-z_]|-[A-Za-z_])[A-Za-z0-9_-]*$/', $name) === 1;
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
                $this->assertCssModulesFunctionalSelector($inner);
                $output .= $this->rewriteSelectorFragment($inner, 'global', $locals);
                $i = $close;
                continue;
            }

            if ($bracketDepth === 0 && $this->startsWithPseudoFunction($selector, $i, ':local')) {
                $open = $i + strlen(':local');
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $this->assertCssModulesFunctionalSelector($inner);
                $output .= $this->rewriteSelectorFragment($inner, $mode === 'global' ? 'global' : 'local', $locals);
                $i = $close;
                continue;
            }

            $viewTransitionFunction = $bracketDepth === 0 && $mode === 'local'
                ? $this->viewTransitionSelectorFunctionAt($selector, $i)
                : null;
            if ($viewTransitionFunction !== null) {
                $open = $i + strlen($viewTransitionFunction['prefix'] . $viewTransitionFunction['name']);
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $output .= $viewTransitionFunction['prefix']
                    . $viewTransitionFunction['name']
                    . '('
                    . $this->rewriteViewTransitionSelectorFunctionArgs($viewTransitionFunction['name'], $inner)
                    . ')';
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

    private function assertCssModulesFunctionalSelector(string $selector): void
    {
        if (trim($selector) === '') {
            throw new \InvalidArgumentException('CSS Modules :local and :global selectors cannot be empty');
        }

        if ($this->findNextTopLevel($selector, ',', 0) !== null) {
            throw new \InvalidArgumentException('CSS Modules :local and :global selectors must contain a single selector');
        }
    }

    /**
     * @return array{prefix:string,name:string}|null
     */
    private function viewTransitionSelectorFunctionAt(string $selector, int $offset): ?array
    {
        foreach ([
            ':active-view-transition-type',
            '::view-transition-group',
            '::view-transition-image-pair',
            '::view-transition-new',
            '::view-transition-old',
        ] as $function) {
            $length = strlen($function);
            if (strncasecmp(substr($selector, $offset, $length), $function, $length) !== 0) {
                continue;
            }

            if (($selector[$offset + $length] ?? '') !== '(') {
                continue;
            }

            return [
                'prefix' => str_starts_with($function, '::') ? '::' : ':',
                'name' => ltrim($function, ':'),
            ];
        }

        return null;
    }

    private function rewriteViewTransitionSelectorFunctionArgs(string $name, string $args): string
    {
        if (strcasecmp($name, 'active-view-transition-type') === 0) {
            return implode(',', array_map(
                fn (string $part): string => $this->rewriteViewTransitionSelectorIdentSequence($part),
                $this->splitTopLevel($args, ',')
            ));
        }

        return $this->rewriteViewTransitionSelectorIdentSequence($args);
    }

    private function rewriteViewTransitionSelectorIdentSequence(string $value): string
    {
        return preg_replace_callback(
            '/(?<![A-Za-z0-9_-])(\\.?)(-?[A-Za-z_][A-Za-z0-9_-]*)/',
            function (array $matches): string {
                $prefix = $matches[1];
                $name = $matches[2];
                if (in_array(strtolower($name), ['none', 'auto'], true)) {
                    return $prefix . $name;
                }

                return $prefix . $this->scopeCustomIdent($name);
            },
            trim($value)
        ) ?? trim($value);
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

    private function scopeCustomIdent(string $local): string
    {
        $this->ensureExport($local);

        return $this->scopedName($local);
    }

    private function scopeDashedIdent(string $name, bool $isReferenced): string
    {
        $this->ensureDashedExport($name, $isReferenced);

        return $this->scopedDashedName($name);
    }

    private function ensureDashedExport(string $name, bool $isReferenced): void
    {
        if (isset($this->exports[$name])) {
            if ($isReferenced) {
                $this->exports[$name]['isReferenced'] = true;
            }

            return;
        }

        $this->exports[$name] = [
            'name' => $this->scopedDashedName($name),
            'composes' => [],
            'isReferenced' => $isReferenced,
        ];
    }

    private function scopedDashedName(string $name): string
    {
        if (!str_starts_with($name, '--')) {
            return $name;
        }

        return '--' . $this->scopedName(substr($name, 2));
    }

    private function rewriteDashedIdentReferences(string $value): string
    {
        $output = '';
        $cursor = 0;
        $quote = null;
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

            if ($char === '/' && ($value[$i + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $i + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('CSS contains an unbalanced comment');
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            $functionName = null;
            foreach (['var', 'env'] as $candidate) {
                if ($this->startsWithFunctionName($value, $i, $candidate)) {
                    $functionName = substr($value, $i, strlen($candidate));
                    break;
                }
            }

            if ($functionName === null) {
                continue;
            }

            $open = $i + strlen($functionName);
            $close = $this->findMatchingParen($value, $open);
            $inner = substr($value, $open + 1, $close - $open - 1);
            $output .= substr($value, $cursor, $i - $cursor)
                . $functionName
                . '('
                . $this->rewriteDashedFunctionArguments($inner)
                . ')';
            $cursor = $close + 1;
            $i = $close;
        }

        return $output . substr($value, $cursor);
    }

    private function rewriteDashedFunctionArguments(string $inner): string
    {
        $comma = $this->findNextTopLevel($inner, ',', 0);
        $head = $comma === null ? trim($inner) : trim(substr($inner, 0, $comma));
        $tail = $comma === null ? null : substr($inner, $comma + 1);
        $rewrittenHead = $this->rewriteDashedReferenceToken($head);

        if ($tail === null) {
            return $rewrittenHead ?? $inner;
        }

        $rewrittenTail = $this->rewriteDashedIdentReferences(trim($tail));

        return ($rewrittenHead ?? $head) . ',' . $rewrittenTail;
    }

    private function rewriteDashedReferenceToken(string $token): ?string
    {
        $parts = $this->splitWhitespaceTopLevel($token);
        if ($parts === [] || !str_starts_with($parts[0], '--')) {
            return null;
        }

        $name = $parts[0];
        if (count($parts) === 1) {
            return $this->scopeDashedIdent($name, true);
        }

        if (count($parts) !== 3 || strcasecmp($parts[1], 'from') !== 0) {
            return null;
        }

        if (strcasecmp($parts[2], 'global') === 0) {
            return $name;
        }

        $specifier = $this->parseQuotedSpecifier($parts[2]);
        if ($specifier === null) {
            return null;
        }

        $placeholder = $this->dashedDependencyPlaceholder($name, $specifier);
        $this->references[$placeholder] = [
            'type' => 'dependency',
            'name' => $name,
            'specifier' => $specifier,
        ];

        return $placeholder;
    }

    private function dashedDependencyPlaceholder(string $name, string $specifier): string
    {
        $hash = substr(hash('sha1', $this->hash . "\0" . $name . "\0" . $specifier), 0, 12);

        return '--lc-' . $hash;
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

    /**
     * @return list<string>
     */
    private function splitWhitespaceTopLevel(string $value): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $value[++$i];
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
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            }

            if (ctype_space($char) && $parenDepth === 0 && $bracketDepth === 0) {
                if (trim($current) !== '') {
                    $parts[] = trim($current);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
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

    private function startsWithFunctionName(string $value, int $offset, string $name): bool
    {
        $length = strlen($name);
        if (strncasecmp(substr($value, $offset, $length), $name, $length) !== 0) {
            return false;
        }

        $previous = $value[$offset - 1] ?? '';
        if ($previous !== '' && $this->isIdentChar($previous)) {
            return false;
        }

        return ($value[$offset + $length] ?? '') === '(';
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

    private function isPlainCssIdent(string $value): bool
    {
        return preg_match('/^-?(?:[A-Za-z_]|-[A-Za-z_])[A-Za-z0-9_-]*$/', $value) === 1;
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
