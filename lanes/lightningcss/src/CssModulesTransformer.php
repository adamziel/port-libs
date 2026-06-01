<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssModulesTransformer
{
    private const PURE_NO_CHECK_MARKER = '/*__lightningcss-cssmodules-pure-no-check__*/';
    private const PRESERVE_EMPTY_COMPOSES_DECLARATION = '--__lightningcss-cssmodules-preserve-empty-composes:0';
    private const RAW_AT_RULE_BODY_DECLARATION_PREFIX = '--__lightningcss-cssmodules-raw-at-rule-body-';
    private const COMMENT_IDENTIFIER_BOUNDARY = "\x1f";
    private const HASH_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-';
    private const U32_MASK = 0xffffffff;
    private const U32_BASE = 4294967296;

    private string $hash = 'EgL3uq';
    private string $contentHash = '';
    private string $filename = 'test.css';
    private string $pattern = '[hash]_[local]';
    private bool $dashedIdents = false;
    private bool $animation = true;
    private bool $grid = true;
    private bool $container = true;
    private bool $customIdents = true;
    private bool $pure = false;
    private bool $minify = true;
    private bool $preserveEmptyComposesRules = true;
    private bool $preserveDependencyComposesDuplicates = false;

    /** @var array<string, string> */
    private array $pseudoClasses = [];

    /** @var list<string> */
    private array $unusedSymbols = [];

    /** @var list<string> */
    private array $rawAtRuleBodies = [];

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
     * @param array{hash?:string, contentHash?:string, filename?:string, projectRoot?:string, project_root?:string, pattern?:string, minify?:bool, dashedIdents?:bool, dashed_idents?:bool, animation?:bool, grid?:bool, container?:bool, customIdents?:bool, custom_idents?:bool, pure?:bool, unusedSymbols?:list<string>, unused_symbols?:list<string>, pseudoClasses?:array<string, string>, pseudo_classes?:array<string, string>, preserveDependencyComposesDuplicates?:bool} $options
     */
    public function transform(string $css, array $options = []): array
    {
        $this->pattern = $options['pattern'] ?? '[hash]_[local]';
        $this->assertValidCssModulesPattern($this->pattern);
        $this->filename = $options['filename'] ?? 'test.css';
        $projectRoot = $options['projectRoot'] ?? $options['project_root'] ?? null;
        $this->hash = $options['hash'] ?? self::hashCssModuleString(
            self::relativeFilenameForHash($this->filename, is_string($projectRoot) ? $projectRoot : null),
            $this->patternStartsWithSegment('[hash]')
        );
        $this->contentHash = $options['contentHash']
            ?? (str_contains($this->pattern, '[content-hash]')
                ? self::hashCssModuleString($css, $this->patternStartsWithSegment('[content-hash]'))
                : '');
        $this->dashedIdents = ($options['dashedIdents'] ?? $options['dashed_idents'] ?? false) === true;
        $this->animation = ($options['animation'] ?? true) !== false;
        $this->grid = ($options['grid'] ?? true) !== false;
        $this->container = ($options['container'] ?? true) !== false;
        $this->customIdents = ($options['customIdents'] ?? $options['custom_idents'] ?? true) !== false;
        $this->pure = ($options['pure'] ?? false) === true;
        $minify = ($options['minify'] ?? true) === true;
        $this->preserveEmptyComposesRules = $minify;
        $this->preserveDependencyComposesDuplicates = ($options['preserveDependencyComposesDuplicates'] ?? false) === true;
        $this->pseudoClasses = $this->normalizePseudoClasses($options['pseudoClasses'] ?? $options['pseudo_classes'] ?? []);
        $this->unusedSymbols = $this->normalizeUnusedSymbols($options['unusedSymbols'] ?? $options['unused_symbols'] ?? []);
        $this->minify = $minify;
        $this->exports = [];
        $this->references = [];
        $this->rawAtRuleBodies = [];

        [$css, $licenseComments] = $this->stripComments($css);
        $code = $this->transformRuleList($css, 0, false, true);
        if ($minify) {
            $code = (new NestingTransformer())->lower($code);
            $code = $this->rewriteAttributeSelectorsInCss($code);
            $code = $this->restorePreservedEmptyComposesRules($code);
            $code = $this->restoreEmptyNthChildOfSelectorLists($code);
        }
        $code = $this->restoreRawAtRuleBodies($code);

        if ($minify && $this->unusedSymbols !== []) {
            $code = $this->pruneUnusedSymbolsFromCss(
                $code,
                $this->scopedUnusedSymbols(),
                $this->scopedUnusedSelectorSymbols()
            );
            $this->pruneUnusedExports($code);
            $this->pruneUnusedReferences($code);
        }

        $code = $this->prependLicenseComments(str_replace(self::COMMENT_IDENTIFIER_BOUNDARY, '', $code), $licenseComments);

        return [
            'code' => $code,
            'exports' => $this->exports,
            'references' => $this->references,
        ];
    }

    private function transformRuleList(
        string $css,
        int $styleNestingDepth,
        bool $composesNestedContext,
        bool $recordDependencyReferences
    ): string
    {
        $output = '';
        $cursor = 0;

        while (true) {
            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = substr($css, $cursor, $nextStatement - $cursor + 1);
                $this->assertNoDeprecatedValueRule($statement);
                $output .= str_replace(self::PURE_NO_CHECK_MARKER, '', $statement);
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $output .= str_replace(self::PURE_NO_CHECK_MARKER, '', substr($css, $cursor));
                break;
            }

            $prelude = substr($css, $cursor, $nextBlock - $cursor);
            [$prelude, $skipPureCheck] = $this->consumePureNoCheckMarker($prelude);
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            $trimmedPrelude = trim($prelude);

            if ($trimmedPrelude !== '' && $trimmedPrelude[0] === '@') {
                $this->assertNoDeprecatedValueRule($trimmedPrelude);
                $output .= $this->rewriteAtRulePrelude($prelude, $trimmedPrelude) . '{' . $this->transformAtRuleBody($trimmedPrelude, $body, $styleNestingDepth) . '}';
                $cursor = $close + 1;
                continue;
            }

            [$selector, $locals] = $this->rewriteSelectorList($prelude);
            if ($this->pure && !$skipPureCheck && $styleNestingDepth === 0) {
                $this->assertPureSelectorList($prelude);
            }
            [$rewrittenBody, $composes] = $this->rewriteStyleBody(
                $body,
                $styleNestingDepth,
                $composesNestedContext,
                $recordDependencyReferences
            );
            $this->assertValidComposesSelector($prelude, $composes);
            $this->addComposesToLocals($locals, $composes);
            $rewrittenBody = $this->preserveEmptyComposesRuleBody($rewrittenBody, $composes);

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

        if (preg_match('/^@position-try\b/i', $prelude) === 1) {
            return $this->rewritePositionTryDeclarationList($body);
        }

        if (preg_match('/^@counter-style\b/i', $prelude) === 1) {
            return $this->rewriteDescriptorDeclarationList($body, false, false);
        }

        if (preg_match('/^@font-palette-values\b/i', $prelude) === 1) {
            return $this->rewriteDescriptorDeclarationList($body, true, false);
        }

        if (!$this->cssModulesAtRuleBodyIsParsed($prelude)) {
            return $this->preserveRawAtRuleBody($body);
        }

        if ($styleNestingDepth > 0) {
            $this->assertNoNestedComposesInAtRuleDeclarations($body);
        }

        return $this->transformRuleList($body, $styleNestingDepth, true, false);
    }

    private function cssModulesAtRuleBodyIsParsed(string $prelude): bool
    {
        $name = $this->atRuleName($prelude);
        if ($name === null) {
            return false;
        }

        if (str_ends_with($name, 'keyframes')) {
            return true;
        }

        return in_array($name, [
            'container',
            'counter-style',
            'font-face',
            'font-feature-values',
            'font-palette-values',
            'layer',
            'media',
            'nest',
            'page',
            'property',
            'scope',
            'starting-style',
            'supports',
            'view-transition',
        ], true);
    }

    private function atRuleName(string $prelude): ?string
    {
        $trimmed = ltrim($prelude);
        if (($trimmed[0] ?? '') !== '@') {
            return null;
        }

        $token = $this->readCssIdentifierToken($trimmed, 1);

        return $token === null ? null : strtolower($token['decoded']);
    }

    private function preserveRawAtRuleBody(string $body): string
    {
        $index = count($this->rawAtRuleBodies);
        $this->rawAtRuleBodies[] = $this->serializeRawAtRuleBody($body);

        return self::RAW_AT_RULE_BODY_DECLARATION_PREFIX . $index . ':0;';
    }

    private function serializeRawAtRuleBody(string $body): string
    {
        $output = '';
        $quote = null;
        $pendingSpace = false;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($quote !== null) {
                if ($pendingSpace) {
                    $output .= ' ';
                    $pendingSpace = false;
                }

                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $body[++$i];
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = $output !== '';
                continue;
            }

            if ($pendingSpace) {
                $output .= ' ';
                $pendingSpace = false;
            }

            $output .= $char;
            if ($char === '"' || $char === "'") {
                $quote = $char;
            }
        }

        $serialized = rtrim($output);

        return str_ends_with($serialized, ';') ? rtrim(substr($serialized, 0, -1)) : $serialized;
    }

    private function restoreRawAtRuleBodies(string $code): string
    {
        foreach ($this->rawAtRuleBodies as $index => $body) {
            $marker = self::RAW_AT_RULE_BODY_DECLARATION_PREFIX . $index . ':0';
            $code = str_replace($marker . ';', $body, $code);
            $code = str_replace($marker, $body, $code);
        }

        return $code;
    }

    private function rewriteAtRulePrelude(string $prelude, string $trimmedPrelude): string
    {
        if ($this->animation && preg_match('/^@(?:-[a-z]+-)?keyframes\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteKeyframesPrelude($prelude);
        }

        if ($this->customIdents && preg_match('/^@counter-style\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteCounterStylePrelude($prelude);
        }

        if ($this->dashedIdents && preg_match('/^@property\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteDashedIdentAtRulePrelude($prelude, '@property');
        }

        if ($this->dashedIdents && preg_match('/^@font-palette-values\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteDashedIdentAtRulePrelude($prelude, '@font-palette-values');
        }

        if ($this->dashedIdents && preg_match('/^@position-try\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteDashedIdentAtRulePrelude($prelude, '@position-try');
        }

        if ($this->container && $this->customIdents && preg_match('/^@container\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteContainerPrelude($prelude);
        }

        if (preg_match('/^@scope\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteScopePrelude($prelude);
        }

        if (preg_match('/^@nest\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteNestPrelude($prelude);
        }

        if ($this->dashedIdents && preg_match('/^\s*@media\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteDashedIdentReferences($prelude, false);
        }

        return $prelude;
    }

    private function rewriteKeyframesPrelude(string $prelude): string
    {
        if (preg_match('/^(\s*@(?:-[a-z]+-)?keyframes\b)(\s*)(.*)$/is', $prelude, $matches) !== 1) {
            return $prelude;
        }

        $nameSource = $matches[3];
        $leading = strspn($nameSource, " \t\r\n\f");
        $token = $this->readCssIdentifierToken($nameSource, $leading);
        if ($token === null) {
            return $prelude;
        }

        $name = $token['decoded'];
        $this->ensureExport($name);

        return $matches[1]
            . $matches[2]
            . substr($nameSource, 0, $leading)
            . $this->escapeCssIdentifier($this->scopedName($name))
            . substr($nameSource, $token['end']);
    }

    private function rewriteCounterStylePrelude(string $prelude): string
    {
        if (preg_match('/^(\s*@counter-style\b)(\s*)(.*)$/is', $prelude, $matches) !== 1) {
            return $prelude;
        }

        $nameSource = $matches[3];
        $leading = strspn($nameSource, " \t\r\n\f");
        $token = $this->readCssIdentifierToken($nameSource, $leading);
        if ($token === null) {
            return $prelude;
        }

        $name = $token['decoded'];
        $this->ensureExport($name);

        return $matches[1]
            . $matches[2]
            . substr($nameSource, 0, $leading)
            . $this->escapeCssIdentifier($this->scopedName($name))
            . substr($nameSource, $token['end']);
    }

    private function rewriteDashedIdentAtRulePrelude(string $prelude, string $keyword): string
    {
        if (preg_match('/^(\s*' . preg_quote($keyword, '/') . '\b)(\s*)(.*)$/is', $prelude, $matches) !== 1) {
            return $prelude;
        }

        $nameSource = $matches[3];
        $leading = strspn($nameSource, " \t\r\n\f");
        $token = $this->readCssIdentifierToken($nameSource, $leading);
        if ($token === null || !str_starts_with($token['decoded'], '--')) {
            return $prelude;
        }

        $name = $token['decoded'];
        $this->ensureDashedExport($name, false);

        return $matches[1]
            . $matches[2]
            . substr($nameSource, 0, $leading)
            . $this->escapeCssIdentifier($this->scopedDashedName($name))
            . substr($nameSource, $token['end']);
    }

    private function rewriteContainerPrelude(string $prelude): string
    {
        if (preg_match('/^(\s*@container\b)(\s*)(.*)$/is', $prelude, $matches) !== 1) {
            return $prelude;
        }

        $conditionSource = $matches[3];
        $leading = strspn($conditionSource, " \t\r\n\f");
        $token = $this->readCssIdentifierToken($conditionSource, $leading);
        if ($token === null) {
            return $prelude;
        }

        $name = $token['decoded'];
        if ($this->isReservedContainerName($name)) {
            return $prelude;
        }

        $afterToken = substr($conditionSource, $token['end']);
        $lowerName = strtolower($name);
        if (($lowerName === 'style' || $lowerName === 'scroll-state') && str_starts_with($afterToken, '(')) {
            return $prelude;
        }

        $this->ensureExport($name);

        return $matches[1]
            . $matches[2]
            . substr($conditionSource, 0, $leading)
            . $this->escapeCssIdentifier($this->scopedName($name))
            . substr($conditionSource, $token['end']);
    }

    private function isReservedContainerName(string $name): bool
    {
        return in_array(strtolower($name), [
            'none',
            'and',
            'not',
            'or',
            'initial',
            'inherit',
            'unset',
            'default',
            'revert',
            'revert-layer',
        ], true);
    }

    private function rewriteScopePrelude(string $prelude): string
    {
        if (preg_match('/^(\s*@scope\b)(.*)$/is', $prelude, $matches) !== 1) {
            return $prelude;
        }

        $tail = $matches[2];
        $cursor = $this->skipCssWhitespace($tail, 0);
        $start = null;
        $end = null;

        if (($tail[$cursor] ?? '') === '(') {
            $close = $this->findMatchingParen($tail, $cursor);
            $start = substr($tail, $cursor + 1, $close - $cursor - 1);
            $cursor = $this->skipCssWhitespace($tail, $close + 1);
        }

        if ($this->startsWithScopeToKeyword($tail, $cursor)) {
            $cursor = $this->skipCssWhitespace($tail, $cursor + 2);
            if (($tail[$cursor] ?? '') !== '(') {
                throw new \InvalidArgumentException('CSS @scope rule is missing a scope limit selector');
            }

            $close = $this->findMatchingParen($tail, $cursor);
            $end = substr($tail, $cursor + 1, $close - $cursor - 1);
            $cursor = $this->skipCssWhitespace($tail, $close + 1);
        }

        $output = $matches[1];
        if ($start !== null) {
            $output .= ' (' . $this->rewriteScopeSelectorList($start) . ')';
        }

        if ($end !== null) {
            $output .= ' to (' . $this->rewriteScopeSelectorList($end) . ')';
        }

        return $output . substr($tail, $cursor);
    }

    private function rewriteScopeSelectorList(string $selectorList): string
    {
        if ($this->pure) {
            $this->assertPureSelectorList($selectorList);
        }

        return $this->rewriteSelectorList($selectorList)[0];
    }

    private function rewriteNestPrelude(string $prelude): string
    {
        if (preg_match('/^(\s*@nest\b)(\s*)(.*)$/is', $prelude, $matches) !== 1) {
            return $prelude;
        }

        $selectorList = trim($matches[3]);
        if ($selectorList === '') {
            return $prelude;
        }

        return $matches[1] . $matches[2] . $this->rewriteSelectorList($selectorList)[0];
    }

    private function startsWithScopeToKeyword(string $value, int $offset): bool
    {
        if (strncasecmp(substr($value, $offset, 2), 'to', 2) !== 0) {
            return false;
        }

        $next = $value[$offset + 2] ?? '';

        return $next === '' || !$this->isIdentChar($next);
    }

    private function skipCssWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{0:string,1:list<array{type:string, name:string, specifier?:string}>}
     */
    private function rewriteStyleBody(
        string $body,
        int $styleNestingDepth,
        bool $composesNestedContext,
        bool $recordDependencyReferences
    ): array
    {
        $output = '';
        $composes = [];
        $cursor = 0;

        while (true) {
            $nextBlock = $this->findNextTopLevel($body, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($body, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = substr($body, $cursor, $nextStatement - $cursor + 1);
                $output .= $this->rewriteDeclarationStatement(
                    $statement,
                    $composes,
                    $styleNestingDepth,
                    $composesNestedContext,
                    $recordDependencyReferences
                );
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $output .= $this->rewriteTrailingDeclarations(
                    substr($body, $cursor),
                    $composes,
                    $styleNestingDepth,
                    $composesNestedContext,
                    $recordDependencyReferences
                );
                break;
            }

            $prefix = substr($body, $cursor, $nextBlock - $cursor);
            [$declarations, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $output .= $this->rewriteTrailingDeclarations(
                $declarations,
                $composes,
                $styleNestingDepth,
                $composesNestedContext,
                $recordDependencyReferences
            );
            $output = $this->preserveEmptyComposesRuleBody($output, $composes);
            $trimmedNested = trim($nestedPrelude);
            $close = $this->findMatchingBrace($body, $nextBlock);
            $nestedBody = substr($body, $nextBlock + 1, $close - $nextBlock - 1);

            if ($trimmedNested !== '' && $trimmedNested[0] === '@') {
                $output .= $this->rewriteAtRulePrelude($nestedPrelude, $trimmedNested) . '{' . $this->transformAtRuleBody($trimmedNested, $nestedBody, $styleNestingDepth + 1) . '}';
            } else {
                [$selector, $locals] = $this->rewriteSelectorList($nestedPrelude);
                [$rewrittenNestedBody, $nestedComposes] = $this->rewriteStyleBody($nestedBody, $styleNestingDepth + 1, true, false);
                $this->assertValidComposesSelector($nestedPrelude, $nestedComposes);
                $this->addComposesToLocals($locals, $nestedComposes);
                $rewrittenNestedBody = $this->preserveEmptyComposesRuleBody($rewrittenNestedBody, $nestedComposes);
                $output .= $selector . '{' . $rewrittenNestedBody . '}';
            }

            $cursor = $close + 1;
        }

        return [$output, $composes];
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function preserveEmptyComposesRuleBody(string $body, array $composes): string
    {
        if (!$this->preserveEmptyComposesRules || $composes === [] || trim($body) !== '') {
            return $body;
        }

        return self::PRESERVE_EMPTY_COMPOSES_DECLARATION . ';';
    }

    private function restorePreservedEmptyComposesRules(string $code): string
    {
        return str_replace([
            '{' . self::PRESERVE_EMPTY_COMPOSES_DECLARATION . '}',
            '{' . self::PRESERVE_EMPTY_COMPOSES_DECLARATION . ';}',
        ], '{}', $code);
    }

    private function restoreEmptyNthChildOfSelectorLists(string $code): string
    {
        return preg_replace(
            '/:(nth(?:-last)?-child)\(([^()]*)\bof\)/i',
            ':$1($2of )',
            $code
        ) ?? $code;
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function rewriteDeclarationStatement(
        string $statement,
        array &$composes,
        int $styleNestingDepth,
        bool $composesNestedContext,
        bool $recordDependencyReferences
    ): string
    {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            return '';
        }

        $this->assertNoDeprecatedValueRule($trimmed);

        $withoutSemicolon = rtrim($trimmed, ';');
        if (trim($withoutSemicolon) === '') {
            return '';
        }

        $colon = $this->findNextTopLevel($withoutSemicolon, ':', 0);
        if ($colon === null) {
            return $statement;
        }

        $rawProperty = trim(substr($withoutSemicolon, 0, $colon));
        if (!$this->isValidDeclarationPropertyName($rawProperty)) {
            return '';
        }

        $property = $this->normalizedDeclarationPropertyName($rawProperty);
        if ($property !== 'composes') {
            $value = trim(substr($withoutSemicolon, $colon + 1));
            $rewrittenProperty = $rawProperty;
            if ($this->dashedIdents && str_starts_with($rawProperty, '--')) {
                $rewrittenProperty = $this->scopeDashedIdent($rawProperty, false);
            }

            $rewrittenValue = $this->rewriteCssModuleDeclarationValue($property, $value);
            if ($this->dashedIdents) {
                $rewrittenValue = $this->rewriteDashedIdentReferences($rewrittenValue ?? $value, $recordDependencyReferences);
            }

            if ($rewrittenValue === null && $rewrittenProperty === $rawProperty) {
                return $statement;
            }

            $trailingSemicolon = str_ends_with(rtrim($statement), ';') ? ';' : '';

            return $rewrittenProperty . ':' . ($rewrittenValue ?? $value) . $trailingSemicolon;
        }

        [$value, $priority] = $this->splitDeclarationPriority(trim(substr($withoutSemicolon, $colon + 1)));
        try {
            $parsedComposes = $this->parseComposesValue($value);
        } catch (\InvalidArgumentException) {
            $trailingSemicolon = str_ends_with(rtrim($statement), ';') ? ';' : '';

            return 'composes:' . $this->serializeInvalidComposesValue($value) . $priority . $trailingSemicolon;
        }

        if ($styleNestingDepth > 0 || $composesNestedContext) {
            throw new \InvalidArgumentException('The `composes` property cannot be used within nested rules');
        }

        array_push($composes, ...$parsedComposes);

        return '';
    }

    private function normalizedDeclarationPropertyName(string $rawProperty): string
    {
        $decoded = $this->decodeCssIdentifierToken($rawProperty);

        return strtolower($decoded ?? $rawProperty);
    }

    private function isValidDeclarationPropertyName(string $rawProperty): bool
    {
        $token = $this->readCssIdentifierToken($rawProperty, 0);

        return $token !== null && $token['end'] === strlen($rawProperty);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDeclarationPriority(string $value): array
    {
        $bang = null;
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

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
                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $i++;
                continue;
            }

            if ($char === '!') {
                $bang = $i;
            }
        }

        if ($bang === null || preg_match('/^\s*important\s*$/i', substr($value, $bang + 1)) !== 1) {
            return [$value, ''];
        }

        return [rtrim(substr($value, 0, $bang)), '!important'];
    }

    private function serializeInvalidComposesValue(string $value): string
    {
        $output = '';
        $pendingSpace = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if (ctype_space($char)) {
                $pendingSpace = $output !== '';
                continue;
            }

            if ($char === '"' || $char === "'") {
                $end = $this->findStringTokenEnd($value, $i);
                $output .= ($pendingSpace ? ' ' : '') . substr($value, $i, $end - $i + 1);
                $pendingSpace = false;
                $i = $end;
                continue;
            }

            $token = ($char === '\\' || $char === '-' || $this->isCssIdentifierStartChar($char))
                ? $this->readCssIdentifierToken($value, $i)
                : null;
            if ($token !== null) {
                $output .= ($pendingSpace ? ' ' : '') . $this->escapeCssIdentifier($token['decoded']);
                $pendingSpace = false;
                $i = $token['end'] - 1;
                continue;
            }

            if ($pendingSpace && !in_array($char, [')', ';'], true)) {
                $output .= ' ';
            }

            $output .= $char;
            $pendingSpace = false;
        }

        return $output;
    }

    private function findStringTokenEnd(string $value, int $start): int
    {
        $quote = $value[$start];
        $length = strlen($value);

        for ($i = $start + 1; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $i++;
                continue;
            }

            if ($char === $quote) {
                return $i;
            }
        }

        return $length - 1;
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $composes
     */
    private function rewriteTrailingDeclarations(
        string $source,
        array &$composes,
        int $styleNestingDepth,
        bool $composesNestedContext,
        bool $recordDependencyReferences
    ): string
    {
        $output = '';
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($source, ';', $cursor)) !== null) {
            $statement = substr($source, $cursor, $semicolon - $cursor + 1);
            $output .= $this->rewriteDeclarationStatement(
                $statement,
                $composes,
                $styleNestingDepth,
                $composesNestedContext,
                $recordDependencyReferences
            );
            $cursor = $semicolon + 1;
        }

        $tail = substr($source, $cursor);
        if (trim($tail) !== '') {
            $output .= $this->rewriteDeclarationStatement(
                $tail,
                $composes,
                $styleNestingDepth,
                $composesNestedContext,
                $recordDependencyReferences
            );
        } else {
            $output .= $tail;
        }

        return $output;
    }

    private function rewriteCssModuleDeclarationValue(string $property, string $value): ?string
    {
        return match ($property) {
            'animation' => $this->animation ? $this->rewriteAnimationShorthandValue($value) : null,
            'animation-name' => $this->animation ? $this->rewriteAnimationNameValue($value) : null,
            'list-style' => $this->rewriteListStyleValue($value),
            'list-style-type' => $this->rewriteListStyleTypeValue($value),
            'transition-property' => $this->rewriteTransitionPropertyValue($value),
            'grid', 'grid-template', 'grid-template-rows', 'grid-template-columns',
            'grid-template-areas', 'grid-area', 'grid-row', 'grid-row-start',
            'grid-row-end', 'grid-column', 'grid-column-start', 'grid-column-end' => $this->rewriteGridValue($property, $value),
            'view-transition-name' => $this->rewriteViewTransitionNameValue($value),
            'view-transition-class' => $this->rewriteViewTransitionIdentList($value, ['none']),
            'view-transition-group' => $this->rewriteViewTransitionNameValue($value, ['contain', 'nearest', 'normal']),
            'position-try-fallbacks' => $this->dashedIdents ? $this->rewritePositionTryFallbacksValue($value) : null,
            'font-palette' => $this->dashedIdents ? $this->rewriteFontPaletteValue($value) : null,
            default => null,
        };
    }

    private function rewriteTransitionPropertyValue(string $value): ?string
    {
        if (preg_match('/(^|,)\s*(,|$)/', $value) === 1) {
            return null;
        }

        $parts = $this->splitTopLevel($value, ',');
        if ($parts === []) {
            return null;
        }

        $rewritten = [];
        $changed = false;

        foreach ($parts as $part) {
            $token = $this->readCssIdentifierToken($part, 0);
            if ($token === null || $token['end'] !== strlen($part)) {
                return null;
            }

            $decoded = $token['decoded'];
            $serialized = str_starts_with($decoded, '--')
                ? $this->escapeCssIdentifier($decoded)
                : strtolower($this->escapeCssIdentifier($decoded));

            $rewritten[] = $serialized;
            $changed = $changed || $serialized !== $part;
        }

        if (!$changed && strpbrk($value, " \t\n\r\f") === false) {
            return null;
        }

        return implode(',', $rewritten);
    }

    private function rewritePositionTryFallbacksValue(string $value): string
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

            if ($char === '"' || $char === "'") {
                $quote = $char;
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

            if ($this->startsWithFunctionName($value, $i, 'var') || $this->startsWithFunctionName($value, $i, 'env')) {
                $open = $i + 3;
                $i = $this->findMatchingParen($value, $open);
                continue;
            }

            if ($char !== '-' || ($value[$i + 1] ?? '') !== '-') {
                continue;
            }

            $token = $this->readCssIdentifierToken($value, $i);
            if ($token === null || !str_starts_with($token['decoded'], '--')) {
                continue;
            }

            $output .= substr($value, $cursor, $i - $cursor)
                . $this->escapeCssIdentifier($this->scopeDashedIdent($token['decoded'], false));
            $cursor = $token['end'];
            $i = $token['end'] - 1;
        }

        return $output . substr($value, $cursor);
    }

    private function rewriteAnimationNameValue(string $value): string
    {
        return implode(',', array_map(
            fn (string $part): string => $this->rewriteAnimationNameToken(trim($part)) ?? trim($part),
            $this->splitTopLevel($value, ',')
        ));
    }

    private function rewriteAnimationShorthandValue(string $value): string
    {
        $animations = [];
        foreach ($this->splitTopLevel($value, ',') as $animation) {
            $tokens = $this->splitWhitespaceTopLevel($animation);
            $rewritten = [];
            $scopedName = false;

            foreach ($tokens as $token) {
                if (!$scopedName) {
                    $replacement = $this->rewriteAnimationNameToken($token);
                    if ($replacement !== null) {
                        $rewritten[] = $replacement;
                        $scopedName = true;
                        continue;
                    }
                }

                $rewritten[] = $token;
            }

            $animations[] = implode(' ', $rewritten);
        }

        return implode(',', $animations);
    }

    private function rewriteAnimationNameToken(string $token): ?string
    {
        if ($token === '' || $this->isAnimationShorthandKeyword($token) || $this->isAnimationShorthandNonNameToken($token)) {
            return null;
        }

        if ($this->isQuotedToken($token)) {
            $name = $this->decodeCssStringToken(substr($token, 1, -1));
            if ($name === '' || $this->isAnimationShorthandKeyword($name)) {
                return null;
            }

            return $this->escapeCssIdentifier($this->scopeCustomIdentReference($name));
        }

        $decoded = $this->decodeCssIdentifierToken($token);
        if ($decoded === null || $decoded === '' || $this->isAnimationShorthandKeyword($decoded)) {
            return null;
        }

        return $this->escapeCssIdentifier($this->scopeCustomIdentReference($decoded));
    }

    private function isAnimationShorthandKeyword(string $token): bool
    {
        return in_array(strtolower($token), [
            'none',
            'initial',
            'inherit',
            'unset',
            'default',
            'revert',
            'revert-layer',
            'linear',
            'ease',
            'ease-in',
            'ease-out',
            'ease-in-out',
            'step-start',
            'step-end',
            'infinite',
            'normal',
            'reverse',
            'alternate',
            'alternate-reverse',
            'forwards',
            'backwards',
            'both',
            'running',
            'paused',
            'auto',
        ], true);
    }

    private function isAnimationShorthandNonNameToken(string $token): bool
    {
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:ms|s)$/i', $token) === 1) {
            return true;
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1) {
            return true;
        }

        return str_contains($token, '(');
    }

    private function rewriteListStyleTypeValue(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $this->isCssWideKeyword($trimmed) || $this->isBuiltInListStyleType($trimmed)) {
            return null;
        }

        return $this->rewriteCounterStyleReference($trimmed);
    }

    private function rewriteListStyleValue(string $value): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return null;
        }

        $rewritten = [];
        $changed = false;
        $typeSeen = false;

        foreach ($tokens as $token) {
            $lower = strtolower($token);
            if ($this->isCssWideKeyword($token) || $lower === 'inside' || $lower === 'outside' || $this->isListStyleImageToken($token)) {
                $rewritten[] = $token;
                continue;
            }

            if (!$typeSeen) {
                if ($this->isBuiltInListStyleType($token)) {
                    $typeSeen = true;
                    $rewritten[] = $token;
                    continue;
                }

                $reference = $this->rewriteCounterStyleReference($token);
                if ($reference !== null) {
                    $typeSeen = true;
                    $rewritten[] = $reference;
                    $changed = $changed || $reference !== $token;
                    continue;
                }
            }

            $rewritten[] = $token;
        }

        return $changed ? implode(' ', $rewritten) : null;
    }

    private function rewriteCounterStyleReference(string $token): ?string
    {
        $decoded = $this->decodeCssIdentifierToken($token);
        if ($decoded === null || $decoded === '' || $this->isCssWideKeyword($decoded) || $this->isBuiltInListStyleType($decoded)) {
            return null;
        }

        $this->ensureExport($decoded);
        $this->exports[$decoded]['isReferenced'] = true;

        return $this->customIdents ? $this->escapeCssIdentifier($this->scopedName($decoded)) : $token;
    }

    private function rewriteFontPaletteValue(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $this->isCssWideKeyword($trimmed) || in_array(strtolower($trimmed), ['normal', 'light', 'dark'], true)) {
            return null;
        }

        $token = $this->readCssIdentifierToken($trimmed, 0);
        if ($token === null || $token['end'] !== strlen($trimmed) || !str_starts_with($token['decoded'], '--')) {
            return null;
        }

        return $this->escapeCssIdentifier($this->scopeDashedIdent($token['decoded'], true));
    }

    private function rewriteGridValue(string $property, string $value): ?string
    {
        if (!$this->grid) {
            return null;
        }

        $rewritten = match ($property) {
            'grid', 'grid-template' => $this->rewriteGridTemplateValue($value),
            'grid-template-rows', 'grid-template-columns' => $this->rewriteGridLineNameLists($value),
            'grid-template-areas' => $this->rewriteGridTemplateAreaStrings($value),
            'grid-area', 'grid-row', 'grid-row-start', 'grid-row-end',
            'grid-column', 'grid-column-start', 'grid-column-end' => $this->rewriteGridLineValue($value),
            default => $value,
        };

        return $rewritten === $value ? null : $rewritten;
    }

    private function rewriteGridTemplateValue(string $value): string
    {
        $output = '';
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                $start = $i;
                while ($i < $length) {
                    $current = $value[$i];
                    if ($current === '\\' && $i + 1 < $length) {
                        $i += 2;
                        continue;
                    }

                    if ($current === $quote) {
                        $content = substr($value, $start, $i - $start);
                        $output .= $this->rewriteGridTemplateAreaStringContent($content) . $quote;
                        $quote = null;
                        break;
                    }

                    $i++;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($char === '[') {
                $close = $this->findGridLineNameListEnd($value, $i);
                if ($close !== null) {
                    $inner = substr($value, $i + 1, $close - $i - 1);
                    $output .= '[' . $this->rewriteGridLineNameListContent($inner) . ']';
                    $i = $close;
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function rewriteGridLineNameLists(string $value): string
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

            if ($char === '[') {
                $close = $this->findGridLineNameListEnd($value, $i);
                if ($close !== null) {
                    $inner = substr($value, $i + 1, $close - $i - 1);
                    $output .= '[' . $this->rewriteGridLineNameListContent($inner) . ']';
                    $i = $close;
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function findGridLineNameListEnd(string $value, int $open): ?int
    {
        $quote = null;
        $length = strlen($value);

        for ($i = $open + 1; $i < $length; $i++) {
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

            if ($char === ']') {
                return $i;
            }
        }

        return null;
    }

    private function rewriteGridTemplateAreaStrings(string $value): string
    {
        return preg_replace_callback(
            '/(["\'])(?:\\\\.|(?!\\1).)*\\1/s',
            function (array $matches): string {
                $quote = $matches[1];
                $content = substr($matches[0], 1, -1);

                return $quote . $this->rewriteGridTemplateAreaStringContent($content) . $quote;
            },
            $value
        ) ?? $value;
    }

    private function rewriteGridTemplateAreaStringContent(string $content): string
    {
        $parts = preg_split('/(\s+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $content;
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || ctype_space($part) || preg_match('/^\.+$/', $part) === 1) {
                continue;
            }

            $parts[$index] = $this->scopeGridName($part);
        }

        return implode('', $parts);
    }

    private function rewriteGridLineNameListContent(string $content): string
    {
        $parts = preg_split('/(\s+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $content;
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || ctype_space($part)) {
                continue;
            }

            $token = $this->readCssIdentifierToken($part, 0);
            if ($token === null || $token['end'] !== strlen($part)) {
                continue;
            }

            $parts[$index] = $this->scopeGridNameToken($token);
        }

        return implode('', $parts);
    }

    private function rewriteGridLineValue(string $value): string
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

            $token = ($char === '\\' || $char === '-' || $this->isCssIdentifierStartChar($char))
                ? $this->readCssIdentifierToken($value, $i)
                : null;
            if ($token === null) {
                $output .= $char;
                continue;
            }

            if (($value[$token['end']] ?? '') === '(') {
                $close = $this->findMatchingParen($value, $token['end']);
                $output .= substr($value, $i, $close - $i + 1);
                $i = $close;
                continue;
            }

            $output .= $this->scopeGridNameToken($token);
            $i = $token['end'] - 1;
        }

        return $output;
    }

    /**
     * @param array{raw:string, decoded:string, end:int} $token
     */
    private function scopeGridNameToken(array $token): string
    {
        $decoded = $token['decoded'];
        if ($this->isGridLineKeyword($decoded)) {
            return $token['raw'];
        }

        return $this->escapeCssIdentifier($this->scopeGridName($token['raw']));
    }

    private function scopeGridName(string $raw): string
    {
        $decoded = $this->decodeCssIdentifierToken($raw);
        if ($decoded === null || $decoded === '' || $this->isGridLineKeyword($decoded)) {
            return $raw;
        }

        $this->assertGridPatternSupportsLineNames();
        $this->ensureExport($decoded);

        return $this->scopedName($decoded);
    }

    private function assertGridPatternSupportsLineNames(): void
    {
        if (!str_ends_with($this->pattern, '[local]')) {
            throw new \InvalidArgumentException('The CSS modules `pattern` config must end with `[local]` for use in CSS grid line names.');
        }
    }

    private function isGridLineKeyword(string $token): bool
    {
        return $this->isCssWideKeyword($token)
            || in_array(strtolower($token), ['auto', 'span', 'none', 'subgrid', 'masonry'], true);
    }

    private function isCssWideKeyword(string $token): bool
    {
        return in_array(strtolower($token), ['initial', 'inherit', 'unset', 'default', 'revert', 'revert-layer'], true);
    }

    private function isListStyleImageToken(string $token): bool
    {
        return preg_match('/^(?:url|image|image-set|cross-fade|linear-gradient|radial-gradient|conic-gradient|var)\(/i', $token) === 1;
    }

    private function isBuiltInListStyleType(string $token): bool
    {
        return in_array(strtolower($token), [
            'none',
            'disc',
            'circle',
            'square',
            'decimal',
            'decimal-leading-zero',
            'disclosure-open',
            'disclosure-closed',
            'lower-alpha',
            'lower-latin',
            'lower-roman',
            'upper-alpha',
            'upper-latin',
            'upper-roman',
            'arabic-indic',
            'armenian',
            'bengali',
            'cambodian',
            'cjk-decimal',
            'cjk-earthly-branch',
            'cjk-heavenly-stem',
            'cjk-ideographic',
            'devanagari',
            'ethiopic-numeric',
            'georgian',
            'gujarati',
            'gurmukhi',
            'hebrew',
            'hiragana',
            'hiragana-iroha',
            'japanese-formal',
            'japanese-informal',
            'kannada',
            'katakana',
            'katakana-iroha',
            'khmer',
            'korean-hangul-formal',
            'korean-hanja-formal',
            'korean-hanja-informal',
            'lao',
            'lower-armenian',
            'malayalam',
            'mongolian',
            'myanmar',
            'oriya',
            'persian',
            'simp-chinese-formal',
            'simp-chinese-informal',
            'tamil',
            'telugu',
            'thai',
            'tibetan',
            'trad-chinese-formal',
            'trad-chinese-informal',
            'upper-armenian',
        ], true);
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

        $token = $this->readCssIdentifierToken($trimmed, 0);
        if ($token === null || $token['end'] !== strlen($trimmed)) {
            return $value;
        }

        $lower = strtolower($token['decoded']);
        if (in_array($lower, array_merge(['none', 'auto'], $additionalKeywords), true)) {
            return $lower;
        }

        return $this->escapeCssIdentifier($this->scopeCustomIdent($token['decoded']));
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
            $parsed = $this->readCssIdentifierToken($token, 0);
            if ($parsed === null || $parsed['end'] !== strlen($token)) {
                $rewritten[] = $token;
                continue;
            }

            $lower = strtolower($parsed['decoded']);
            if (in_array($lower, $keywords, true)) {
                $rewritten[] = $lower;
                continue;
            }

            $rewritten[] = $this->escapeCssIdentifier($this->scopeCustomIdent($parsed['decoded']));
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
            $rewrittenTail = $this->rewriteViewTransitionDeclarationStatement($tail);
            if (trim($rewrittenTail) !== '' && !str_ends_with(rtrim($rewrittenTail), ';')) {
                $rewrittenTail .= ';';
            }

            $output .= $rewrittenTail;
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

        $rawProperty = trim(substr($withoutSemicolon, 0, $colon));
        $property = $this->normalizedDeclarationPropertyName($rawProperty);
        if ($property === 'composes') {
            return '';
        }

        if ($property !== 'types') {
            return $statement;
        }

        $value = trim(substr($withoutSemicolon, $colon + 1));
        $trailingSemicolon = str_ends_with(rtrim($statement), ';') ? ';' : '';

        return 'types:' . $this->rewriteViewTransitionIdentList($value, ['none']) . $trailingSemicolon;
    }

    private function rewritePositionTryDeclarationList(string $body): string
    {
        if (str_contains($body, '{')) {
            return $body;
        }

        return $this->rewriteDescriptorDeclarationList($body, false, true);
    }

    private function rewriteDescriptorDeclarationList(
        string $body,
        bool $dropInvalidComposes,
        bool $rewriteNonComposes
    ): string {
        $output = '';
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($body, ';', $cursor)) !== null) {
            $statement = substr($body, $cursor, $semicolon - $cursor + 1);
            $output .= $this->rewriteDescriptorDeclarationStatement(
                $statement,
                $dropInvalidComposes,
                $rewriteNonComposes
            );
            $cursor = $semicolon + 1;
        }

        $tail = substr($body, $cursor);
        if (trim($tail) === '') {
            return $output . $tail;
        }

        $rewrittenTail = $this->rewriteDescriptorDeclarationStatement(
            $tail,
            $dropInvalidComposes,
            $rewriteNonComposes
        );

        if (trim($rewrittenTail) !== '' && !str_ends_with(rtrim($rewrittenTail), ';')) {
            $rewrittenTail .= ';';
        }

        return $output . $rewrittenTail;
    }

    private function rewriteDescriptorDeclarationStatement(
        string $statement,
        bool $dropInvalidComposes,
        bool $rewriteNonComposes
    ): string {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            return '';
        }

        $withoutSemicolon = rtrim($trimmed, ';');
        $colon = $this->findNextTopLevel($withoutSemicolon, ':', 0);
        if ($colon === null) {
            return $statement;
        }

        $rawProperty = trim(substr($withoutSemicolon, 0, $colon));
        if (!$this->isValidDeclarationPropertyName($rawProperty)) {
            return $rewriteNonComposes ? '' : $statement;
        }

        $property = $this->normalizedDeclarationPropertyName($rawProperty);
        if ($property !== 'composes') {
            if (!$rewriteNonComposes) {
                return $statement;
            }

            $composes = [];

            return $this->rewriteDeclarationStatement($statement, $composes, 1, true, false);
        }

        if ($dropInvalidComposes) {
            return '';
        }

        [$value, $priority] = $this->splitDeclarationPriority(trim(substr($withoutSemicolon, $colon + 1)));
        try {
            $this->parseComposesValue($value);

            return '';
        } catch (\InvalidArgumentException) {
            $trailingSemicolon = str_ends_with(rtrim($statement), ';') ? ';' : '';

            return 'composes:' . $this->serializeInvalidComposesValue($value) . $priority . $trailingSemicolon;
        }
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
        $this->rewriteTrailingDeclarations($source, $composes, 1, true, false);
    }

    /**
     * @return list<array{type:string, name:string, specifier?:string}>
     */
    private function parseComposesValue(string $value): array
    {
        $tokens = $this->tokenizeComposesValue($value);
        $fromIndex = null;
        foreach ($tokens as $index => $token) {
            $decoded = $this->isQuotedToken($token) ? null : $this->decodeCssIdentifierToken($token);
            if ($decoded !== null && strcasecmp($decoded, 'from') === 0) {
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

            $decodedFrom = $this->isQuotedToken($from[0]) ? null : $this->decodeCssIdentifierToken($from[0]);
            if ($decodedFrom !== null && strcasecmp($decodedFrom, 'global') === 0) {
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
            $decodedName = $this->parseComposesIdent($name);
            if ($decodedName === null) {
                throw new \InvalidArgumentException('Invalid CSS Modules composes declaration');
            }

            if ($type === 'local') {
                $references[] = [
                    'type' => 'local',
                    'name' => $this->scopedName($decodedName),
                ];
                continue;
            }

            if ($type === 'global') {
                $references[] = [
                    'type' => 'global',
                    'name' => $decodedName,
                ];
                continue;
            }

            $references[] = [
                'type' => 'dependency',
                'name' => $decodedName,
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

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $char;
                $next = $value[$i + 1];
                if (ctype_xdigit($next)) {
                    $hexLength = 0;
                    while ($i + 1 < $length && $hexLength < 6 && ctype_xdigit($value[$i + 1])) {
                        $current .= $value[++$i];
                        $hexLength++;
                    }
                    if ($i + 1 < $length && ctype_space($value[$i + 1])) {
                        $current .= $value[++$i];
                    }
                    continue;
                }

                $current .= $value[++$i];
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

        return $this->decodeCssStringToken(substr($token, 1, -1));
    }

    private function decodeCssStringToken(string $token): string
    {
        $output = '';
        $length = strlen($token);

        for ($i = 0; $i < $length; $i++) {
            $char = $token[$i];
            if ($char !== '\\') {
                $output .= $char;
                continue;
            }

            if ($i + 1 >= $length) {
                $output .= '\\';
                continue;
            }

            $next = $token[$i + 1];
            if ($next === "\r") {
                $i++;
                if (($token[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                continue;
            }

            if ($next === "\n" || $next === "\f") {
                $i++;
                continue;
            }

            if (!ctype_xdigit($next)) {
                $output .= $next;
                $i++;
                continue;
            }

            $hex = '';
            $cursor = $i + 1;
            while ($cursor < $length && strlen($hex) < 6 && ctype_xdigit($token[$cursor])) {
                $hex .= $token[$cursor];
                $cursor++;
            }

            if ($cursor < $length && ctype_space($token[$cursor])) {
                $cursor++;
            }

            $output .= $this->codepointToUtf8((int) hexdec($hex));
            $i = $cursor - 1;
        }

        return $output;
    }

    private function parseComposesIdent(string $token): ?string
    {
        if ($this->isQuotedToken($token)) {
            return null;
        }

        $decoded = $this->decodeCssIdentifierToken($token);
        if ($decoded === null || $decoded === '') {
            return null;
        }

        if (in_array(strtolower($decoded), ['from', 'initial', 'inherit', 'unset', 'default', 'revert', 'revert-layer'], true)) {
            return null;
        }

        return $decoded;
    }

    private function decodeCssIdentifierToken(string $token): ?string
    {
        $parsed = $this->readCssIdentifierToken($token, 0);
        if ($parsed === null || $parsed['end'] !== strlen($token)) {
            return null;
        }

        return $parsed['decoded'];
    }

    private function isQuotedToken(string $token): bool
    {
        $quote = $token[0] ?? '';

        return ($quote === '"' || $quote === "'") && substr($token, -1) === $quote;
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

        foreach ($locals as $localName) {
            $local = (string) $localName;
            $this->ensureExport($local);
            foreach ($composes as $compose) {
                if (
                    !($this->preserveDependencyComposesDuplicates && ($compose['type'] ?? '') === 'dependency')
                    && in_array($compose, $this->exports[$local]['composes'], true)
                ) {
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
                throw new \InvalidArgumentException('The `composes` property cannot be used with a simple class selector');
            }
        }
    }

    private function isSimpleLocalClassSelector(string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '' || $selector[0] !== '.') {
            return false;
        }

        $token = $this->readCssIdentifierToken($selector, 1);

        return $token !== null && $token['end'] === strlen($selector);
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function consumePureNoCheckMarker(string $prelude): array
    {
        if (!str_contains($prelude, self::PURE_NO_CHECK_MARKER)) {
            return [$prelude, false];
        }

        return [str_replace(self::PURE_NO_CHECK_MARKER, '', $prelude), true];
    }

    private function assertPureSelectorList(string $selectorList): void
    {
        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            if (!$this->selectorHasLocalReference($selector, 'local')) {
                throw new \InvalidArgumentException('Impure CSS module selector');
            }
        }
    }

    private function selectorHasLocalReference(string $selector, string $mode): bool
    {
        $quote = null;
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

            $globalPseudo = $bracketDepth === 0 ? $this->cssModulesPseudoFunctionAt($selector, $i, 'global') : null;
            if ($globalPseudo !== null) {
                $open = $globalPseudo['open'];
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                if ($this->selectorHasLocalReference($inner, 'global')) {
                    return true;
                }
                $i = $close;
                continue;
            }

            $localPseudo = $bracketDepth === 0 ? $this->cssModulesPseudoFunctionAt($selector, $i, 'local') : null;
            if ($localPseudo !== null) {
                $open = $localPseudo['open'];
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                if ($this->selectorHasLocalReference($inner, $mode === 'global' ? 'global' : 'local')) {
                    return true;
                }
                $i = $close;
                continue;
            }

            $rawPseudoFunction = $bracketDepth === 0 ? $this->rawSelectorPseudoFunctionAt($selector, $i) : null;
            if ($rawPseudoFunction !== null) {
                $i = $rawPseudoFunction['close'];
                continue;
            }

            if ($bracketDepth === 0 && $mode === 'local' && ($char === '.' || $char === '#')) {
                $token = $this->readCssIdentifierToken($selector, $i + 1);
                if ($token !== null) {
                    return true;
                }
            }

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private function rewriteSelectorList(string $selectorList): array
    {
        $this->assertNoCommentIdentifierBoundariesInSelectorList($selectorList);
        $this->assertNoInvalidEscapesInSelectorList($selectorList);
        $this->assertNoUnexpectedSelectorCloseParentheses($selectorList);
        $this->assertSelectorPseudoElementBoundaries($selectorList);

        $rewritten = [];
        $locals = [];

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $selectorLocals = [];
            $rewritten[] = $this->rewriteSelectorFragment($selector, 'local', $selectorLocals);
            foreach (array_keys($selectorLocals) as $localKey) {
                $local = (string) $localKey;
                if (!in_array($local, $locals, true)) {
                    $locals[] = $local;
                }
            }
        }

        return [implode(', ', $rewritten), $locals];
    }

    private function assertNoCommentIdentifierBoundariesInSelectorList(string $selectorList): void
    {
        if (str_contains($selectorList, self::COMMENT_IDENTIFIER_BOUNDARY)) {
            throw new \InvalidArgumentException('CSS comments cannot split selector identifiers');
        }
    }

    private function assertNoInvalidEscapesInSelectorList(string $selectorList): void
    {
        $quote = null;
        $length = strlen($selectorList);

        for ($i = 0; $i < $length; $i++) {
            $char = $selectorList[$i];

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
                continue;
            }

            if ($char !== '\\') {
                continue;
            }

            $next = $selectorList[$i + 1] ?? '';
            if ($next === "\n" || $next === "\r" || $next === "\f") {
                throw new \InvalidArgumentException('Invalid CSS escape in selector');
            }

            if ($next !== '') {
                $i++;
            }
        }
    }

    private function assertNoUnexpectedSelectorCloseParentheses(string $selectorList): void
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($selectorList);

        for ($i = 0; $i < $length; $i++) {
            $char = $selectorList[$i];

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
                continue;
            }

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selectorList, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
            }

            if ($char === '[') {
                $bracketDepth++;
                continue;
            }

            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if ($bracketDepth > 0) {
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char !== ')') {
                continue;
            }

            if ($parenDepth === 0) {
                throw new \InvalidArgumentException('Unexpected token CloseParenthesis');
            }

            $parenDepth--;
        }
    }

    private function assertSelectorPseudoElementBoundaries(string $selectorList): void
    {
        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $this->assertSelectorPseudoElementBoundary($selector);
        }
    }

    private function assertSelectorPseudoElementBoundary(string $selector): void
    {
        $quote = null;
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

            $globalPseudo = $bracketDepth === 0 ? $this->cssModulesPseudoFunctionAt($selector, $i, 'global') : null;
            if ($globalPseudo !== null) {
                $open = $globalPseudo['open'];
                $close = $this->findMatchingParen($selector, $open);
                $this->assertSelectorPseudoElementBoundaries(substr($selector, $open + 1, $close - $open - 1));
                $i = $close;
                continue;
            }

            $localPseudo = $bracketDepth === 0 ? $this->cssModulesPseudoFunctionAt($selector, $i, 'local') : null;
            if ($localPseudo !== null) {
                $open = $localPseudo['open'];
                $close = $this->findMatchingParen($selector, $open);
                $this->assertSelectorPseudoElementBoundaries(substr($selector, $open + 1, $close - $open - 1));
                $i = $close;
                continue;
            }

            $pseudoElement = $bracketDepth === 0 ? $this->cssModulesPseudoElementAt($selector, $i) : null;
            if ($pseudoElement === null) {
                if ($char === '\\') {
                    $escapeEnd = $this->cssEscapeEnd($selector, $i);
                    if ($escapeEnd !== null) {
                        $i = $escapeEnd;
                    }
                }
                continue;
            }

            if (isset($pseudoElement['inner'])) {
                if (($pseudoElement['singleSelector'] ?? false) === true) {
                    $this->assertCssModulesFunctionalSelector($pseudoElement['inner']);
                }

                $this->assertSelectorPseudoElementBoundaries($pseudoElement['inner']);
            }

            $this->assertPseudoElementTail($selector, $pseudoElement['end'], $pseudoElement['allowPseudoClasses']);
            $i = $pseudoElement['end'] - 1;
        }
    }

    /**
     * @return array{end:int,allowPseudoClasses:bool,inner?:string}|null
     */
    private function cssModulesPseudoElementAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':') {
            return null;
        }

        $colonLength = ($selector[$offset + 1] ?? '') === ':' ? 2 : 1;
        $token = $this->readCssIdentifierToken($selector, $offset + $colonLength);
        if ($token === null) {
            return null;
        }

        $name = strtolower($token['decoded']);
        if ($name === 'highlight' && $colonLength === 2 && ($selector[$token['end']] ?? '') === '(') {
            $close = $this->findMatchingParen($selector, $token['end']);

            return [
                'end' => $close + 1,
                'allowPseudoClasses' => true,
            ];
        }

        if ($colonLength === 2 && in_array($name, ['part', 'picker'], true) && ($selector[$token['end']] ?? '') === '(') {
            $close = $this->findMatchingParen($selector, $token['end']);

            return [
                'end' => $close + 1,
                'allowPseudoClasses' => true,
            ];
        }

        if ($name === 'slotted' && ($selector[$token['end']] ?? '') === '(') {
            $close = $this->findMatchingParen($selector, $token['end']);

            return [
                'end' => $close + 1,
                'allowPseudoClasses' => false,
                'inner' => substr($selector, $token['end'] + 1, $close - $token['end'] - 1),
            ];
        }

        if ($colonLength === 2 && in_array($name, ['cue', 'cue-region'], true)) {
            if (($selector[$token['end']] ?? '') === '(') {
                $close = $this->findMatchingParen($selector, $token['end']);

                return [
                    'end' => $close + 1,
                    'allowPseudoClasses' => true,
                    'inner' => substr($selector, $token['end'] + 1, $close - $token['end'] - 1),
                    'singleSelector' => true,
                ];
            }

            return [
                'end' => $token['end'],
                'allowPseudoClasses' => true,
            ];
        }

        if ($colonLength === 2 && in_array($name, [
            'details-content',
            'target-text',
            'search-text',
            'selection',
            '-moz-selection',
            'placeholder',
            '-webkit-input-placeholder',
            '-moz-placeholder',
            '-ms-input-placeholder',
            'marker',
            'backdrop',
            '-webkit-backdrop',
            'file-selector-button',
            '-webkit-file-upload-button',
            '-ms-browse',
            '-webkit-scrollbar',
            '-webkit-scrollbar-button',
            '-webkit-scrollbar-track',
            '-webkit-scrollbar-track-piece',
            '-webkit-scrollbar-thumb',
            '-webkit-scrollbar-corner',
            '-webkit-resizer',
            'picker-icon',
            'checkmark',
            'view-transition',
            'grammar-error',
            'spelling-error',
        ], true)) {
            return [
                'end' => $token['end'],
                'allowPseudoClasses' => true,
            ];
        }

        if (!in_array($name, ['before', 'after', 'first-letter', 'first-line'], true)) {
            return null;
        }

        return [
            'end' => $token['end'],
            'allowPseudoClasses' => true,
        ];
    }

    private function assertPseudoElementTail(string $selector, int $offset, bool $allowPseudoClasses): void
    {
        $length = strlen($selector);
        $cursor = $offset;

        while ($cursor < $length) {
            $char = $selector[$cursor];
            if (ctype_space($char)) {
                if (trim(substr($selector, $cursor)) === '') {
                    return;
                }

                throw new \InvalidArgumentException('CSS pseudo-elements cannot be followed by selectors');
            }

            if (!$allowPseudoClasses || $char !== ':' || ($selector[$cursor + 1] ?? '') === ':') {
                throw new \InvalidArgumentException('CSS pseudo-elements cannot be followed by selectors');
            }

            $token = $this->readCssIdentifierToken($selector, $cursor + 1);
            if ($token === null) {
                throw new \InvalidArgumentException('CSS pseudo-elements cannot be followed by selectors');
            }

            $cursor = $token['end'];
            if (($selector[$cursor] ?? '') === '(') {
                $cursor = $this->findMatchingParen($selector, $cursor) + 1;
            }

            if ($cursor >= $length) {
                return;
            }

            $next = $selector[$cursor] ?? '';
            if ($next !== ':') {
                $rest = substr($selector, $cursor);
                if (trim($rest) === '') {
                    return;
                }

                throw new \InvalidArgumentException('CSS pseudo-elements cannot be followed by selectors');
            }
        }
    }

    /**
     * @param array<string, true> $locals
     */
    private function rewriteSelectorFragment(string $selector, string $mode, array &$locals): string
    {
        $this->assertNoDanglingCombinatorInSelector($selector);

        $output = '';
        $quote = null;
        $bracketDepth = 0;
        $afterPseudoElement = false;
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
                if (!$this->minify) {
                    $bracketDepth++;
                    $output .= $char;
                    continue;
                }

                $close = $this->findAttributeSelectorEnd($selector, $i);
                $inner = substr($selector, $i + 1, $close - $i - 1);
                $output .= '[' . $this->rewriteAttributeSelectorContent($inner) . ']';
                $i = $close;
                continue;
            }

            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $output .= $char;
                continue;
            }

            if ($bracketDepth === 0 && !$afterPseudoElement && $this->cssModulesPseudoElementAt($selector, $i) !== null) {
                $afterPseudoElement = true;
            }

            $forgivingSelectorFunction = $bracketDepth === 0 ? $this->forgivingSelectorFunctionAt($selector, $i) : null;
            if ($forgivingSelectorFunction !== null) {
                $inner = substr(
                    $selector,
                    $forgivingSelectorFunction['open'] + 1,
                    $forgivingSelectorFunction['close'] - $forgivingSelectorFunction['open'] - 1
                );
                $rewrittenSelectors = $afterPseudoElement
                    ? $this->rewritePseudoElementForgivingSelectorListParts($inner)
                    : $this->rewriteForgivingSelectorListParts($inner, $mode, $locals);
                if (
                    $forgivingSelectorFunction['decodedName'] === 'is'
                    && count($rewrittenSelectors) === 1
                    && (
                        $this->shouldUnwrapIsSelector($rewrittenSelectors[0])
                        || $this->isTransparentCssModulesModeSelectorFunction($inner)
                    )
                ) {
                    $output .= $rewrittenSelectors[0];
                    $i = $forgivingSelectorFunction['close'];
                    continue;
                }

                $output .= ':'
                    . $forgivingSelectorFunction['canonicalName']
                    . '('
                    . implode(',', $rewrittenSelectors)
                    . ')';
                $i = $forgivingSelectorFunction['close'];
                continue;
            }

            $nthChildSelectorFunction = $bracketDepth === 0 ? $this->nthChildSelectorFunctionAt($selector, $i) : null;
            if ($nthChildSelectorFunction !== null) {
                $inner = substr(
                    $selector,
                    $nthChildSelectorFunction['open'] + 1,
                    $nthChildSelectorFunction['close'] - $nthChildSelectorFunction['open'] - 1
                );
                $ofKeyword = $this->findNthChildOfKeyword($inner);
                if ($ofKeyword === null) {
                    $this->assertNoCssModulesModePseudoInNthChildFormula($inner);
                    $output .= ':' . $nthChildSelectorFunction['rawName'] . '(' . $this->minifyNthChildFormula($inner) . ')';
                    $i = $nthChildSelectorFunction['close'];
                    continue;
                }

                $formulaSource = substr($inner, 0, $ofKeyword['start']);
                $this->assertNoCssModulesModePseudoInNthChildFormula($formulaSource);
                $formula = $this->minifyNthChildFormula($formulaSource);
                $selectorList = substr($inner, $ofKeyword['end']);
                $output .= ':'
                    . $nthChildSelectorFunction['rawName']
                    . '('
                    . $formula
                    . ' of '
                    . $this->rewriteForgivingSelectorList($selectorList, $mode, $locals)
                    . ')';
                $i = $nthChildSelectorFunction['close'];
                continue;
            }

            $negationSelectorFunction = $bracketDepth === 0 ? $this->negationSelectorFunctionAt($selector, $i) : null;
            if ($negationSelectorFunction !== null) {
                $inner = substr(
                    $selector,
                    $negationSelectorFunction['open'] + 1,
                    $negationSelectorFunction['close'] - $negationSelectorFunction['open'] - 1
                );

                if ($afterPseudoElement) {
                    $output .= ':'
                        . $negationSelectorFunction['canonicalName']
                        . '('
                        . $this->rewritePseudoElementStrictSelectorList($inner)
                        . ')';
                    $i = $negationSelectorFunction['close'];
                    continue;
                }

                $output .= ':'
                    . $negationSelectorFunction['canonicalName']
                    . '('
                    . $this->rewriteStrictSelectorList($inner, $mode, $locals)
                    . ')';
                $i = $negationSelectorFunction['close'];
                continue;
            }

            $languageSelectorFunction = $bracketDepth === 0 ? $this->languageSelectorFunctionAt($selector, $i) : null;
            if ($languageSelectorFunction !== null) {
                $inner = substr(
                    $selector,
                    $languageSelectorFunction['open'] + 1,
                    $languageSelectorFunction['close'] - $languageSelectorFunction['open'] - 1
                );
                $this->assertNoCssModulesModePseudoInSelectorFunctionArgs($inner, 'Unexpected token Colon');

                $output .= ':'
                    . $languageSelectorFunction['canonicalName']
                    . '('
                    . $this->serializeLanguageSelectorFunctionArgs($languageSelectorFunction['canonicalName'], $inner)
                    . ')';
                $i = $languageSelectorFunction['close'];
                continue;
            }

            $compoundSelectorFunction = $bracketDepth === 0 ? $this->compoundSelectorArgumentFunctionAt($selector, $i) : null;
            if ($compoundSelectorFunction !== null) {
                $inner = substr(
                    $selector,
                    $compoundSelectorFunction['open'] + 1,
                    $compoundSelectorFunction['close'] - $compoundSelectorFunction['open'] - 1
                );
                $this->assertCssModulesCompoundSelectorArgument($inner);

                $output .= $compoundSelectorFunction['prefix']
                    . $compoundSelectorFunction['name']
                    . '('
                    . $this->rewriteSelectorFragment($inner, $mode, $locals)
                    . ')';
                $i = $compoundSelectorFunction['close'];
                continue;
            }

            $globalPseudo = $bracketDepth === 0 ? $this->cssModulesPseudoFunctionAt($selector, $i, 'global') : null;
            if ($globalPseudo !== null) {
                $open = $globalPseudo['open'];
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $this->assertCssModulesFunctionalSelector($inner);
                $output .= $this->rewriteSelectorFragment($inner, 'global', $locals);
                $i = $close;
                continue;
            }

            $localPseudo = $bracketDepth === 0 ? $this->cssModulesPseudoFunctionAt($selector, $i, 'local') : null;
            if ($localPseudo !== null) {
                $open = $localPseudo['open'];
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $this->assertCssModulesFunctionalSelector($inner);
                $output .= $this->rewriteSelectorFragment($inner, $mode === 'global' ? 'global' : 'local', $locals);
                $i = $close;
                continue;
            }

            $cueSelectorFunction = $bracketDepth === 0 ? $this->cueSelectorFunctionAt($selector, $i) : null;
            if ($cueSelectorFunction !== null) {
                $inner = substr(
                    $selector,
                    $cueSelectorFunction['open'] + 1,
                    $cueSelectorFunction['close'] - $cueSelectorFunction['open'] - 1
                );
                $this->assertCssModulesFunctionalSelector($inner);

                $output .= '::'
                    . $cueSelectorFunction['name']
                    . '('
                    . $this->rewriteSelectorFragment($inner, $mode, $locals)
                    . ')';
                $i = $cueSelectorFunction['close'];
                continue;
            }

            $customIdentFunction = $bracketDepth === 0 ? $this->cssModulesSelectorCustomIdentFunctionAt($selector, $i) : null;
            if ($customIdentFunction !== null) {
                $name = $customIdentFunction['identifier'];
                $serializedName = $mode === 'local'
                    ? $this->escapeCssIdentifier($this->scopeCustomIdent($name))
                    : $this->escapeCssIdentifier($name);
                $output .= $customIdentFunction['prefix']
                    . $customIdentFunction['name']
                    . '('
                    . $serializedName
                    . ')';
                $i = $customIdentFunction['close'];
                continue;
            }

            $rawPseudoFunction = $bracketDepth === 0 ? $this->rawSelectorPseudoFunctionAt($selector, $i) : null;
            if ($rawPseudoFunction !== null) {
                $output .= substr($selector, $i, $rawPseudoFunction['close'] - $i + 1);
                $i = $rawPseudoFunction['close'];
                continue;
            }

            $pseudoClassReplacement = $bracketDepth === 0
                ? $this->pseudoClassReplacementAt($selector, $i)
                : null;
            if ($pseudoClassReplacement !== null) {
                $class = $pseudoClassReplacement['class'];
                $output .= '.';
                if ($mode === 'local') {
                    $this->ensureExport($class);
                    $output .= $this->escapeCssIdentifier($this->scopedName($class));
                } else {
                    $output .= $this->escapeCssIdentifier($class);
                }

                $i += $pseudoClassReplacement['length'] - 1;
                continue;
            }

            $standardPseudoClass = $bracketDepth === 0 ? $this->standardPseudoClassAt($selector, $i) : null;
            if ($standardPseudoClass !== null) {
                $output .= ':' . $standardPseudoClass['name'];
                $i += $standardPseudoClass['length'] - 1;
                continue;
            }

            $viewTransitionFunction = $bracketDepth === 0
                ? $this->viewTransitionSelectorFunctionAt($selector, $i)
                : null;
            if ($viewTransitionFunction !== null) {
                $open = $viewTransitionFunction['open'];
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                $this->assertNoCssModulesModePseudoInViewTransitionSelectorArgs($inner);

                $args = $mode === 'local'
                    ? $this->rewriteViewTransitionSelectorFunctionArgs($viewTransitionFunction['name'], $inner)
                    : $inner;
                $output .= $viewTransitionFunction['prefix']
                    . $viewTransitionFunction['name']
                    . '('
                    . $args
                    . ')';
                $i = $close;
                continue;
            }

            if ($bracketDepth === 0 && (
                $this->cssModulesBarePseudoNameAt($selector, $i, 'global')
                || $this->cssModulesBarePseudoNameAt($selector, $i, 'local')
            )) {
                throw new \InvalidArgumentException('Ambiguous CSS module class not supported');
            }

            if ($bracketDepth === 0 && $char === '|') {
                $this->assertSelectorNamespaceDelimiter($selector, $i);
            }

            if ($bracketDepth === 0 && $mode === 'local' && ($char === '.' || $char === '#')) {
                $token = $this->readCssIdentifierToken($selector, $i + 1);
                if ($token !== null) {
                    $this->assertNoInvalidNamespaceDelimiterAfterScopedIdent($selector, $token['end']);
                    $local = $token['decoded'];
                    $locals[$local] = true;
                    $this->ensureExport($local);
                    $output .= $char . $this->escapeCssIdentifier($this->scopedName($local));
                    $i = $token['end'] - 1;
                    continue;
                }
            }

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    $output .= substr($selector, $i, $escapeEnd - $i + 1);
                    $i = $escapeEnd;
                    continue;
                }
            }

            $output .= $char;
        }

        return trim($output);
    }

    /**
     * @return list<string>
     */
    private function rewritePseudoElementForgivingSelectorListParts(string $selectorList): array
    {
        $rewritten = [];

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            if (!$this->selectorCanFollowPseudoElement($selector)) {
                continue;
            }

            $candidateLocals = [];
            $rewrittenSelector = $this->rewriteSelectorFragment($selector, 'global', $candidateLocals);
            if ($rewrittenSelector === '' || $candidateLocals !== []) {
                continue;
            }

            $rewritten[] = $rewrittenSelector;
        }

        return $rewritten;
    }

    private function rewritePseudoElementStrictSelectorList(string $selectorList): string
    {
        $rewritten = [];

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            if (!$this->selectorCanFollowPseudoElement($selector)) {
                throw new \InvalidArgumentException('CSS pseudo-elements cannot be followed by selectors');
            }

            $candidateLocals = [];
            $rewrittenSelector = $this->rewriteSelectorFragment($selector, 'global', $candidateLocals);
            if ($rewrittenSelector === '' || $candidateLocals !== []) {
                throw new \InvalidArgumentException('CSS pseudo-elements cannot be followed by selectors');
            }

            $rewritten[] = $rewrittenSelector;
        }

        return implode(',', $rewritten);
    }

    private function selectorCanFollowPseudoElement(string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '') {
            return false;
        }

        $length = strlen($selector);
        $cursor = 0;

        while ($cursor < $length) {
            if (ctype_space($selector[$cursor])) {
                return trim(substr($selector, $cursor)) === '';
            }

            if ($selector[$cursor] !== ':' || ($selector[$cursor + 1] ?? '') === ':') {
                return false;
            }

            $token = $this->readCssIdentifierToken($selector, $cursor + 1);
            if ($token === null) {
                return false;
            }

            $name = strtolower($token['decoded']);
            if ($name === 'local' || $name === 'global') {
                return false;
            }

            $cursor = $token['end'];
            if (($selector[$cursor] ?? '') !== '(') {
                continue;
            }

            $close = $this->findMatchingParen($selector, $cursor);
            $inner = substr($selector, $cursor + 1, $close - $cursor - 1);
            if ($this->selectorFunctionArgumentsMustFollowPseudoElement($name)) {
                foreach ($this->splitTopLevel($inner, ',') as $innerSelector) {
                    if (!$this->selectorCanFollowPseudoElement($innerSelector)) {
                        return false;
                    }
                }
            } elseif ($this->selectorFunctionArgumentsContainCssModulesModePseudo($inner)) {
                return false;
            }

            $cursor = $close + 1;
        }

        return true;
    }

    private function selectorFunctionArgumentsMustFollowPseudoElement(string $name): bool
    {
        return in_array($name, ['-moz-any', '-webkit-any', 'has', 'is', 'not', 'where'], true);
    }

    private function selectorFunctionArgumentsContainCssModulesModePseudo(string $arguments): bool
    {
        $length = strlen($arguments);
        for ($i = 0; $i < $length; $i++) {
            if (
                $this->cssModulesPseudoFunctionAt($arguments, $i, 'global') !== null
                || $this->cssModulesPseudoFunctionAt($arguments, $i, 'local') !== null
            ) {
                return true;
            }
        }

        return false;
    }

    private function assertSelectorNamespaceDelimiter(string $selector, int $offset): void
    {
        if (!$this->selectorNamespaceDelimiterAllowed($selector, $offset)) {
            throw new \InvalidArgumentException("Unexpected token Delim('|')");
        }
    }

    private function assertNoInvalidNamespaceDelimiterAfterScopedIdent(string $selector, int $offset): void
    {
        $length = strlen($selector);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if (ctype_space($selector[$cursor])) {
                continue;
            }

            if ($selector[$cursor] === '|') {
                $this->assertSelectorNamespaceDelimiter($selector, $cursor);
            }

            return;
        }
    }

    private function selectorNamespaceDelimiterAllowed(string $selector, int $offset): bool
    {
        if (($selector[$offset + 1] ?? '') === '|' || ($selector[$offset - 1] ?? '') === '|') {
            return false;
        }

        if (!$this->selectorNamespaceNameFollowsPipe($selector, $offset + 1)) {
            return false;
        }

        $prefixStart = $this->selectorNamespacePrefixStart($selector, $offset);
        if ($prefixStart === null) {
            return false;
        }

        if ($prefixStart === $offset || $prefixStart === 0) {
            return true;
        }

        $previous = $selector[$prefixStart - 1] ?? '';

        return ctype_space($previous) || in_array($previous, ['>', '+', '~', ','], true);
    }

    private function selectorNamespaceNameFollowsPipe(string $selector, int $offset): bool
    {
        $next = $selector[$offset] ?? '';
        if ($next === '' || ctype_space($next)) {
            return false;
        }

        return $next === '*' || $this->readCssIdentifierToken($selector, $offset) !== null;
    }

    private function selectorNamespacePrefixStart(string $selector, int $offset): ?int
    {
        if ($offset === 0) {
            return 0;
        }

        $previous = $selector[$offset - 1] ?? '';
        if ($previous === '' || ctype_space($previous) || in_array($previous, ['>', '+', '~', ','], true)) {
            return $offset;
        }

        if ($previous === '*') {
            return $offset - 1;
        }

        for ($start = 0; $start < $offset; $start++) {
            $token = $this->readCssIdentifierToken($selector, $start);
            if ($token !== null && $token['end'] === $offset) {
                return $start;
            }
        }

        return null;
    }

    /**
     * @param array<string, true> $locals
     */
    private function rewriteForgivingSelectorList(string $selectorList, string $mode, array &$locals): string
    {
        return implode(',', $this->rewriteForgivingSelectorListParts($selectorList, $mode, $locals));
    }

    /**
     * @param array<string, true> $locals
     */
    private function rewriteStrictSelectorList(string $selectorList, string $mode, array &$locals): string
    {
        $rewritten = [];

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            if (trim($selector) === '') {
                throw new \InvalidArgumentException('Invalid CSS selector list');
            }

            $candidateLocals = [];
            $rewrittenSelector = $this->rewriteSelectorFragment($selector, $mode, $candidateLocals);
            if ($rewrittenSelector === '') {
                throw new \InvalidArgumentException('Invalid CSS selector list');
            }

            foreach ($candidateLocals as $local => $enabled) {
                if ($enabled) {
                    $locals[(string) $local] = true;
                }
            }

            $rewritten[] = $rewrittenSelector;
        }

        return implode(',', $rewritten);
    }

    /**
     * @param array<string, true> $locals
     * @return list<string>
     */
    private function rewriteForgivingSelectorListParts(string $selectorList, string $mode, array &$locals): array
    {
        $rewritten = [];

        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $candidateLocals = [];
            try {
                $rewrittenSelector = $this->rewriteSelectorFragment($selector, $mode, $candidateLocals);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($rewrittenSelector === '') {
                continue;
            }

            foreach ($candidateLocals as $local => $enabled) {
                if ($enabled) {
                    $locals[(string) $local] = true;
                }
            }

            $rewritten[] = $rewrittenSelector;
        }

        return $rewritten;
    }

    private function shouldUnwrapIsSelector(string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '') {
            return false;
        }

        return !$this->selectorHasTopLevelCombinator($selector)
            && !$this->selectorStartsWithTypeSelector($selector);
    }

    private function isTransparentCssModulesModeSelectorFunction(string $selectorList): bool
    {
        $parts = $this->splitTopLevel($selectorList, ',');
        if (count($parts) !== 1) {
            return false;
        }

        $selector = trim($parts[0]);
        if ($selector === '') {
            return false;
        }

        $modePseudo = $this->cssModulesPseudoFunctionAt($selector, 0, 'global')
            ?? $this->cssModulesPseudoFunctionAt($selector, 0, 'local');
        if ($modePseudo === null) {
            return false;
        }

        $close = $this->findMatchingParen($selector, $modePseudo['open']);

        return $this->skipCssWhitespace($selector, $close + 1) === strlen($selector);
    }

    private function selectorHasTopLevelCombinator(string $selector): bool
    {
        $quote = null;
        $bracketDepth = 0;
        $parenDepth = 0;
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
                continue;
            }

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
            }

            if ($char === '[') {
                $bracketDepth++;
                continue;
            }

            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
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

            if ($bracketDepth !== 0 || $parenDepth !== 0) {
                continue;
            }

            if (ctype_space($char) || $char === '>' || $char === '+' || $char === '~') {
                return true;
            }

            if ($char === '|' && ($selector[$i + 1] ?? '') === '|') {
                return true;
            }
        }

        return false;
    }

    private function selectorStartsWithTypeSelector(string $selector): bool
    {
        $selector = ltrim($selector);
        $first = $selector[0] ?? '';
        if ($first === '' || $first === '.' || $first === '#' || $first === '[' || $first === ':' || $first === '&') {
            return false;
        }

        if ($first === '*' || $first === '|') {
            return true;
        }

        return $this->readCssIdentifierToken($selector, 0) !== null;
    }

    /**
     * @return array{rawName:string,decodedName:string,canonicalName:string,open:int,close:int}|null
     */
    private function forgivingSelectorFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        $decodedName = strtolower($token['decoded']);
        if (!in_array($decodedName, ['is', 'where', 'has', '-webkit-any', '-moz-any'], true)) {
            return null;
        }

        return [
            'rawName' => $token['raw'],
            'decodedName' => $decodedName,
            'canonicalName' => $this->canonicalSelectorFunctionName($decodedName),
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    /**
     * @return array{canonicalName:string,open:int,close:int}|null
     */
    private function negationSelectorFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        $decodedName = strtolower($token['decoded']);
        if ($decodedName !== 'not') {
            return null;
        }

        return [
            'canonicalName' => $this->canonicalSelectorFunctionName($decodedName),
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    private function canonicalSelectorFunctionName(string $decodedName): string
    {
        return match ($decodedName) {
            '-moz-any' => '-moz-any',
            '-webkit-any' => '-webkit-any',
            default => $decodedName,
        };
    }

    /**
     * @return array{prefix:string,name:string,open:int,close:int}|null
     */
    private function compoundSelectorArgumentFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':') {
            return null;
        }

        $prefixLength = ($selector[$offset + 1] ?? '') === ':' ? 2 : 1;
        $token = $this->readCssIdentifierToken($selector, $offset + $prefixLength);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        $name = strtolower($token['decoded']);
        if (!(($prefixLength === 1 && $name === 'host') || ($prefixLength === 2 && $name === 'slotted'))) {
            return null;
        }

        return [
            'prefix' => $prefixLength === 2 ? '::' : ':',
            'name' => $name,
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    private function assertCssModulesCompoundSelectorArgument(string $selector): void
    {
        $selector = trim($selector);
        if ($selector === '') {
            throw new \InvalidArgumentException('Invalid empty selector');
        }

        if ($this->findNextTopLevel($selector, ',', 0) !== null) {
            throw new \InvalidArgumentException('Unexpected token Comma');
        }

        $invalidCombinator = $this->invalidTopLevelCompoundSelectorCombinator($selector);
        if ($invalidCombinator === '|') {
            throw new \InvalidArgumentException("Unexpected token Delim('|')");
        }

        if ($invalidCombinator !== null) {
            throw new \InvalidArgumentException('Invalid state');
        }
    }

    private function invalidTopLevelCompoundSelectorCombinator(string $selector): ?string
    {
        $quote = null;
        $bracketDepth = 0;
        $parenDepth = 0;
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
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

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }

            if ($bracketDepth !== 0 || $parenDepth !== 0) {
                continue;
            }

            if (ctype_space($char) || $char === '>' || $char === '+' || $char === '~') {
                return 'combinator';
            }

            if ($char === '|' && ($selector[$i + 1] ?? '') === '|') {
                return '|';
            }
        }

        return null;
    }

    /**
     * @return array{canonicalName:string,open:int,close:int}|null
     */
    private function languageSelectorFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        $decodedName = strtolower($token['decoded']);
        if (!in_array($decodedName, ['dir', 'lang'], true)) {
            return null;
        }

        return [
            'canonicalName' => $decodedName,
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    private function serializeLanguageSelectorFunctionArgs(string $functionName, string $inner): string
    {
        if (!$this->minify) {
            return $inner;
        }

        $parts = $this->splitTopLevel($inner, ',');
        if ($parts === []) {
            throw new \InvalidArgumentException('Invalid CSS language selector argument');
        }

        if ($functionName === 'dir') {
            if (count($parts) !== 1) {
                throw new \InvalidArgumentException('Invalid CSS direction selector argument');
            }

            $direction = $this->parseLanguageSelectorIdentifier($parts[0]);
            $direction = $direction === null ? null : strtolower($direction);
            if ($direction !== 'ltr' && $direction !== 'rtl') {
                throw new \InvalidArgumentException('Invalid CSS direction selector argument');
            }

            return $direction;
        }

        $serialized = [];
        foreach ($parts as $part) {
            $language = $this->parseLanguageSelectorIdentifierOrString($part);
            if ($language === null) {
                throw new \InvalidArgumentException('Invalid CSS language selector argument');
            }

            $serialized[] = $this->escapeCssIdentifier($language);
        }

        return implode(',', $serialized);
    }

    private function parseLanguageSelectorIdentifierOrString(string $part): ?string
    {
        $part = trim($part);
        if ($part === '') {
            return null;
        }

        $quote = $part[0];
        if ($quote === '"' || $quote === "'") {
            $end = $this->findStringTokenEnd($part, 0);
            if ($end !== strlen($part) - 1) {
                return null;
            }

            return $this->decodeCssStringToken(substr($part, 1, -1));
        }

        return $this->parseLanguageSelectorIdentifier($part);
    }

    private function parseLanguageSelectorIdentifier(string $part): ?string
    {
        $part = trim($part);
        $token = $this->readCssIdentifierToken($part, 0);

        return $token !== null && $token['end'] === strlen($part) ? $token['decoded'] : null;
    }

    /**
     * @return array{rawName:string,open:int,close:int}|null
     */
    private function nthChildSelectorFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        if (!in_array(strtolower($token['decoded']), ['nth-child', 'nth-last-child'], true)) {
            return null;
        }

        return [
            'rawName' => $token['raw'],
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    private function minifyNthChildFormula(string $formula): string
    {
        $formula = strtolower(trim(preg_replace('/\s+/', '', $formula) ?? $formula));
        if ($formula === '') {
            return '';
        }

        if ($formula === 'odd') {
            return 'odd';
        }

        if ($formula === 'even') {
            return '2n';
        }

        if (preg_match('/^([+-]?)(\d*)n(?:([+-])(\d+))?$/', $formula, $matches) === 1) {
            $coefficient = $matches[2] === '' ? 1 : (int) $matches[2];
            if (($matches[1] ?? '') === '-') {
                $coefficient *= -1;
            }

            $offset = 0;
            if (($matches[3] ?? '') !== '') {
                $offset = (int) $matches[4];
                if ($matches[3] === '-') {
                    $offset *= -1;
                }
            }

            if ($coefficient === 0) {
                return (string) $offset;
            }

            if ($coefficient === 2 && $offset === 1) {
                return 'odd';
            }

            $output = match ($coefficient) {
                1 => 'n',
                -1 => '-n',
                default => $coefficient . 'n',
            };

            if ($offset === 0) {
                return $output;
            }

            return $output . ($offset > 0 ? '+' : '') . $offset;
        }

        if (preg_match('/^[+-]?\d+$/', $formula) === 1) {
            return (string) (int) $formula;
        }

        return $formula;
    }

    private function assertNoCssModulesModePseudoInNthChildFormula(string $formula): void
    {
        $this->assertNoCssModulesModePseudoInSelectorFunctionArgs($formula, 'Unexpected token Colon');
    }

    /**
     * @return array{start:int,end:int}|null
     */
    private function findNthChildOfKeyword(string $inner): ?array
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

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

            if ($parenDepth !== 0 || $bracketDepth !== 0) {
                continue;
            }

            if (!$this->isCssIdentifierStartChar($char) && $char !== '-' && $char !== '\\') {
                continue;
            }

            $token = $this->readCssIdentifierToken($inner, $i);
            if ($token === null) {
                continue;
            }

            $previous = $inner[$i - 1] ?? '';
            $next = $inner[$token['end']] ?? '';
            if (
                strcasecmp($token['decoded'], 'of') === 0
                && ($previous === '' || !$this->isCssIdentifierChar($previous))
                && ($next === '' || !$this->isCssIdentifierChar($next))
            ) {
                return [
                    'start' => $i,
                    'end' => $token['end'],
                ];
            }

            $i = $token['end'] - 1;
        }

        return null;
    }

    private function assertNoDeprecatedValueRule(string $source): void
    {
        $source = ltrim($source);
        while (str_starts_with($source, '/*')) {
            $end = strpos($source, '*/');
            if ($end === false) {
                break;
            }

            $source = ltrim(substr($source, $end + 2));
        }

        if (preg_match('/^@value\b/i', $source) !== 1) {
            return;
        }

        throw new \InvalidArgumentException('The @value rule is deprecated');
    }

    /**
     * @param mixed $value
     *
     * @return array<string, string>
     */
    private function normalizePseudoClasses(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $aliases = [
            'hover' => ['hover'],
            'active' => ['active'],
            'focus' => ['focus'],
            'focus-visible' => ['focus-visible', 'focusVisible', 'focus_visible'],
            'focus-within' => ['focus-within', 'focusWithin', 'focus_within'],
        ];

        $normalized = [];
        foreach ($aliases as $canonical => $keys) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $value)) {
                    continue;
                }

                $class = trim((string) $value[$key]);
                if ($class !== '') {
                    $normalized[$canonical] = $class;
                }
                break;
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function normalizeUnusedSymbols(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $symbols = [];
        foreach ($value as $symbol) {
            $symbol = trim((string) $symbol);
            if ($symbol !== '' && !in_array($symbol, $symbols, true)) {
                $symbols[] = $symbol;
            }
        }

        return $symbols;
    }

    /**
     * @return array<string, true>
     */
    private function scopedUnusedSymbols(): array
    {
        $symbols = [];
        foreach ($this->unusedSymbols as $symbol) {
            $symbols[$symbol] = true;
            if (str_starts_with($symbol, '--')) {
                $symbols[$this->scopedDashedName($symbol)] = true;
                continue;
            }

            $symbols[$this->scopedName($symbol)] = true;
        }

        return $symbols;
    }

    /**
     * @param array<string, true> $unusedSymbols
     * @param array<string, true> $unusedSelectorSymbols
     */
    private function pruneUnusedSymbolsFromCss(string $css, array $unusedSymbols, array $unusedSelectorSymbols): string
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
                if ($this->unusedAtRuleName($trimmedPrelude, $unusedSymbols) !== null) {
                    $cursor = $close + 1;
                    continue;
                }

                if ($this->atRuleContainsNestedRules($trimmedPrelude)) {
                    $body = $this->pruneUnusedSymbolsFromCss($body, $unusedSymbols, $unusedSelectorSymbols);
                    if (trim($body) === '') {
                        $cursor = $close + 1;
                        continue;
                    }
                }

                $output .= $prelude . '{' . $body . '}';
                $cursor = $close + 1;
                continue;
            }

            if ($this->selectorListIsUnused($prelude, $unusedSelectorSymbols)) {
                $cursor = $close + 1;
                continue;
            }

            $output .= $prelude . '{' . $this->pruneUnusedDeclarations($body, $unusedSymbols) . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    /**
     * @return array<string, true>
     */
    private function scopedUnusedSelectorSymbols(): array
    {
        $symbols = [];
        $exportNames = [];
        foreach ($this->exports as $export) {
            $exportNames[(string) $export['name']] = true;
        }

        foreach ($this->unusedSymbols as $symbol) {
            if (str_starts_with($symbol, '--')) {
                continue;
            }

            $symbols[$this->scopedName($symbol)] = true;
            if (isset($exportNames[$symbol])) {
                $symbols[$symbol] = true;
            }
        }

        return $symbols;
    }

    /**
     * @param array<string, true> $unusedSymbols
     */
    private function unusedAtRuleName(string $prelude, array $unusedSymbols): ?string
    {
        foreach ([
            '@keyframes',
            '@-webkit-keyframes',
            '@-moz-keyframes',
            '@counter-style',
            '@font-palette-values',
            '@property',
            '@position-try',
        ] as $keyword) {
            if (strncasecmp($prelude, $keyword, strlen($keyword)) !== 0) {
                continue;
            }

            $offset = $this->skipCssWhitespace($prelude, strlen($keyword));
            $token = $this->readAtRuleNameToken($prelude, $offset);
            if ($token !== null && isset($unusedSymbols[$token])) {
                return $token;
            }

            return null;
        }

        return null;
    }

    private function readAtRuleNameToken(string $prelude, int $offset): ?string
    {
        $token = $this->readCssIdentifierToken($prelude, $offset);
        if ($token !== null) {
            return $token['decoded'];
        }

        $quote = $prelude[$offset] ?? '';
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }

        $cursor = $offset + 1;
        $value = '';
        $length = strlen($prelude);
        while ($cursor < $length) {
            $char = $prelude[$cursor];
            if ($char === '\\' && $cursor + 1 < $length) {
                $value .= $prelude[$cursor + 1];
                $cursor += 2;
                continue;
            }

            if ($char === $quote) {
                return $value;
            }

            $value .= $char;
            $cursor++;
        }

        return null;
    }

    private function atRuleContainsNestedRules(string $prelude): bool
    {
        return preg_match('/^@(?:media|supports|container|layer|scope|starting-style)\b/i', $prelude) === 1;
    }

    /**
     * @param array<string, true> $unusedSymbols
     */
    private function pruneUnusedDeclarations(string $body, array $unusedSymbols): string
    {
        $output = '';
        $cursor = 0;

        while (($semicolon = $this->findNextTopLevel($body, ';', $cursor)) !== null) {
            $statement = substr($body, $cursor, $semicolon - $cursor + 1);
            if (!$this->declarationIsUnused($statement, $unusedSymbols)) {
                $output .= $statement;
            }
            $cursor = $semicolon + 1;
        }

        $tail = substr($body, $cursor);
        if (trim($tail) === '' || !$this->declarationIsUnused($tail, $unusedSymbols)) {
            $output .= $tail;
        }

        return $output;
    }

    /**
     * @param array<string, true> $unusedSymbols
     */
    private function declarationIsUnused(string $statement, array $unusedSymbols): bool
    {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            return false;
        }

        $withoutSemicolon = rtrim($trimmed, ';');
        $colon = $this->findNextTopLevel($withoutSemicolon, ':', 0);
        if ($colon === null) {
            return false;
        }

        $property = trim(substr($withoutSemicolon, 0, $colon));

        return str_starts_with($property, '--') && isset($unusedSymbols[$property]);
    }

    /**
     * @param array<string, true> $unusedSymbols
     */
    private function selectorListIsUnused(string $selectorList, array $unusedSymbols): bool
    {
        $selectors = $this->splitTopLevel($selectorList, ',');
        if ($selectors === []) {
            return false;
        }

        foreach ($selectors as $selector) {
            if (!$this->selectorContainsUnusedSymbol($selector, $unusedSymbols)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, true> $unusedSymbols
     */
    private function selectorContainsUnusedSymbol(string $selector, array $unusedSymbols): bool
    {
        foreach ($this->selectorSymbolNames($selector) as $symbol) {
            if (isset($unusedSymbols[$symbol])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function selectorSymbolNames(string $selector): array
    {
        $symbols = [];
        $quote = null;
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

            if ($bracketDepth === 0 && ($char === '.' || $char === '#')) {
                $token = $this->readCssIdentifierToken($selector, $i + 1);
                if ($token !== null) {
                    $symbols[] = $token['decoded'];
                    $i = $token['end'] - 1;
                    continue;
                }
            }

            $pseudoFunction = $bracketDepth === 0 && $char === ':'
                ? $this->selectorPseudoFunctionNameAt($selector, $i)
                : null;
            if ($pseudoFunction !== null) {
                $inner = substr($selector, $pseudoFunction['open'] + 1, $pseudoFunction['close'] - $pseudoFunction['open'] - 1);
                if (in_array($pseudoFunction['name'], ['is', 'where', '-webkit-any', '-moz-any'], true)) {
                    foreach ($this->selectorSymbolNames($inner) as $symbol) {
                        $symbols[] = $symbol;
                    }
                }

                $i = $pseudoFunction['close'];
                continue;
            }

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
            }
        }

        return $symbols;
    }

    /**
     * @return array{name:string,open:int,close:int}|null
     */
    private function selectorPseudoFunctionNameAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':') {
            return null;
        }

        $nameStart = ($selector[$offset + 1] ?? '') === ':' ? $offset + 2 : $offset + 1;
        $token = $this->readCssIdentifierToken($selector, $nameStart);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        return [
            'name' => strtolower($token['decoded']),
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    private function pruneUnusedExports(string $code): void
    {
        $unusedSymbols = $this->scopedUnusedSymbols();
        foreach ($this->exports as $key => $export) {
            $name = $export['name'];
            if (
                (in_array($key, $this->unusedSymbols, true) || isset($unusedSymbols[$name]))
                && !$this->cssContainsIdentifier($code, $name)
            ) {
                unset($this->exports[$key]);
                continue;
            }

            if (
                !$export['isReferenced']
                && !$this->cssContainsIdentifier($code, (string) $name)
            ) {
                unset($this->exports[$key]);
            }
        }
    }

    private function pruneUnusedReferences(string $code): void
    {
        foreach (array_keys($this->references) as $placeholder) {
            if (!$this->cssContainsIdentifier($code, (string) $placeholder)) {
                unset($this->references[$placeholder]);
            }
        }
    }

    private function cssContainsIdentifier(string $css, string $identifier): bool
    {
        return str_contains($css, $identifier) || str_contains($css, $this->escapeCssIdentifier($identifier));
    }

    /**
     * @return array{class:string,length:int}|null
     */
    private function pseudoClassReplacementAt(string $selector, int $offset): ?array
    {
        if ($this->pseudoClasses === [] || ($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || ($selector[$token['end']] ?? '') === '(') {
            return null;
        }

        $pseudo = strtolower($token['decoded']);
        $class = $this->pseudoClasses[$pseudo] ?? null;
        if ($class === null) {
            return null;
        }

        return [
            'class' => $class,
            'length' => $token['end'] - $offset,
        ];
    }

    /**
     * @return array{name:string,length:int}|null
     */
    private function standardPseudoClassAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || ($selector[$token['end']] ?? '') === '(') {
            return null;
        }

        $canonical = $this->canonicalStandardPseudoClassName($token['decoded']);
        if ($canonical === null) {
            return null;
        }

        return [
            'name' => $canonical,
            'length' => $token['end'] - $offset,
        ];
    }

    private function canonicalStandardPseudoClassName(string $name): ?string
    {
        $name = strtolower($name);

        return match ($name) {
            '-moz-placeholder' => '-moz-placeholder-shown',
            '-ms-input-placeholder' => '-ms-placeholder-shown',
            default => in_array($name, [
                '-moz-any-link',
                '-moz-full-screen',
                '-moz-read-only',
                '-moz-read-write',
                '-ms-fullscreen',
                '-o-autofill',
                '-webkit-any-link',
                '-webkit-autofill',
                '-webkit-full-screen',
                'active',
                'active-view-transition',
                'any-link',
                'autofill',
                'blank',
                'buffering',
                'checked',
                'closed',
                'corner-present',
                'current',
                'decrement',
                'default',
                'defined',
                'disabled',
                'double-button',
                'enabled',
                'end',
                'focus',
                'focus-visible',
                'focus-within',
                'fullscreen',
                'future',
                'horizontal',
                'host',
                'hover',
                'in-range',
                'increment',
                'indeterminate',
                'invalid',
                'link',
                'local-link',
                'modal',
                'muted',
                'no-button',
                'open',
                'optional',
                'out-of-range',
                'past',
                'paused',
                'picture-in-picture',
                'placeholder-shown',
                'playing',
                'popover-open',
                'read-only',
                'read-write',
                'required',
                'scope',
                'seeking',
                'single-button',
                'stalled',
                'start',
                'target',
                'target-within',
                'user-invalid',
                'user-valid',
                'valid',
                'vertical',
                'visited',
                'volume-locked',
                'window-inactive',
            ], true) ? $name : null,
        };
    }

    /**
     * @return array{name:string,open:int,close:int}|null
     */
    private function cueSelectorFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') !== ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 2);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        $name = strtolower($token['decoded']);
        if (!in_array($name, ['cue', 'cue-region'], true)) {
            return null;
        }

        return [
            'name' => $name,
            'open' => $token['end'],
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    /**
     * @return array{close:int}|null
     */
    private function rawSelectorPseudoFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':') {
            return null;
        }

        $prefixLength = ($selector[$offset + 1] ?? '') === ':' ? 2 : 1;
        $token = $this->readCssIdentifierToken($selector, $offset + $prefixLength);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        if (
            $prefixLength === 2
            && in_array(strtolower($token['decoded']), ['global', 'local'], true)
        ) {
            return [
                'close' => $this->findMatchingParen($selector, $token['end']),
            ];
        }

        if ($this->selectorFunctionAllowsCssModuleRewrites($token['decoded'])) {
            return null;
        }

        return [
            'close' => $this->findMatchingParen($selector, $token['end']),
        ];
    }

    private function selectorFunctionAllowsCssModuleRewrites(string $name): bool
    {
        return in_array(strtolower($name), [
            '-moz-any',
            '-webkit-any',
            'active-view-transition-type',
            'global',
            'has',
            'host',
            'is',
            'local',
            'not',
            'nth-child',
            'nth-last-child',
            'slotted',
            'view-transition-group',
            'view-transition-image-pair',
            'view-transition-new',
            'view-transition-old',
            'where',
        ], true);
    }

    /**
     * @return array{prefix:string,name:string,identifier:string,close:int}|null
     */
    private function cssModulesSelectorCustomIdentFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':') {
            return null;
        }

        $prefixLength = ($selector[$offset + 1] ?? '') === ':' ? 2 : 1;
        $token = $this->readCssIdentifierToken($selector, $offset + $prefixLength);
        if ($token === null || ($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        $name = strtolower($token['decoded']);
        if (!(($prefixLength === 1 && $name === 'state') || ($prefixLength === 2 && $name === 'highlight'))) {
            return null;
        }

        $close = $this->findMatchingParen($selector, $token['end']);
        $identifier = $this->parseSelectorCustomIdentArgument(
            substr($selector, $token['end'] + 1, $close - $token['end'] - 1)
        );

        return [
            'prefix' => $prefixLength === 2 ? '::' : ':',
            'name' => $name,
            'identifier' => $identifier,
            'close' => $close,
        ];
    }

    private function parseSelectorCustomIdentArgument(string $argument): string
    {
        $argument = trim($argument);
        $token = $this->readCssIdentifierToken($argument, 0);
        if ($token === null || $token['end'] !== strlen($argument) || $this->isCssWideKeyword($token['decoded'])) {
            throw new \InvalidArgumentException('Invalid CSS Modules selector custom identifier');
        }

        return $token['decoded'];
    }

    private function findAttributeSelectorEnd(string $selector, int $open): int
    {
        $quote = null;
        $length = strlen($selector);

        for ($i = $open + 1; $i < $length; $i++) {
            $char = $selector[$i];
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
                continue;
            }

            if ($char === ']') {
                return $i;
            }
        }

        throw new \InvalidArgumentException('CSS attribute selector is missing a closing bracket');
    }

    private function rewriteAttributeSelectorContent(string $content): string
    {
        $equals = $this->findNextTopLevel($content, '=', 0);
        if ($equals === null) {
            return trim($content);
        }

        $operatorStart = $equals;
        $previous = $content[$equals - 1] ?? '';
        if (in_array($previous, ['~', '|', '^', '$', '*'], true)) {
            $operatorStart--;
        }

        $name = trim(substr($content, 0, $operatorStart));
        $operator = substr($content, $operatorStart, $equals - $operatorStart + 1);
        $tail = trim(substr($content, $equals + 1));
        if ($name === '' || $tail === '') {
            throw new \InvalidArgumentException('Invalid value in attribute selector');
        }

        [$valueToken, $flags] = $this->splitAttributeSelectorValueAndFlags($tail);
        $serializedValue = $this->serializeAttributeSelectorValue($valueToken);

        return $name . $operator . $serializedValue . ($flags === '' ? '' : ' ' . $flags);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitAttributeSelectorValueAndFlags(string $tail): array
    {
        $quote = $tail[0] ?? '';
        if ($quote === '"' || $quote === "'") {
            $length = strlen($tail);
            for ($i = 1; $i < $length; $i++) {
                $char = $tail[$i];
                if ($char === '\\' && $i + 1 < $length) {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    return [substr($tail, 0, $i + 1), trim(substr($tail, $i + 1))];
                }
            }

            throw new \InvalidArgumentException('Invalid value in attribute selector');
        }

        $token = $this->readCssIdentifierToken($tail, 0);
        if ($token === null) {
            throw new \InvalidArgumentException('Invalid value in attribute selector');
        }

        return [substr($tail, 0, $token['end']), trim(substr($tail, $token['end']))];
    }

    private function serializeAttributeSelectorValue(string $token): string
    {
        if ($this->isQuotedToken($token)) {
            $decoded = $this->decodeCssStringToken(substr($token, 1, -1));
            if ($this->canUnquoteAttributeSelectorValue($decoded)) {
                return $this->escapeCssIdentifier($decoded);
            }

            return '"' . addcslashes($decoded, "\\\"\n\r\f") . '"';
        }

        $parsed = $this->readCssIdentifierToken($token, 0);
        if ($parsed === null || $parsed['end'] !== strlen($token)) {
            throw new \InvalidArgumentException('Invalid value in attribute selector');
        }

        return $this->escapeCssIdentifier($parsed['decoded']);
    }

    private function canUnquoteAttributeSelectorValue(string $value): bool
    {
        if ($value === '' || preg_match('/^[0-9]/', $value) === 1) {
            return false;
        }

        return !str_contains($value, ',') && !str_contains($value, '!');
    }

    private function rewriteAttributeSelectorsInCss(string $css): string
    {
        $output = '';
        $cursor = 0;

        while (($open = $this->findNextTopLevel($css, '{', $cursor)) !== null) {
            $preludeStart = $this->findPreludeStart($css, $cursor, $open);
            $output .= substr($css, $cursor, $preludeStart - $cursor);

            $prelude = substr($css, $preludeStart, $open - $preludeStart);
            $trimmed = trim($prelude);
            if ($trimmed !== '' && $trimmed[0] !== '@') {
                $prelude = $this->rewriteAttributeSelectorsInSelector($prelude);
            }

            $output .= $prelude . '{';
            $cursor = $open + 1;
        }

        return $output . substr($css, $cursor);
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($css, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
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

    private function rewriteAttributeSelectorsInSelector(string $selector): string
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    $output .= substr($selector, $i, $escapeEnd - $i + 1);
                    $i = $escapeEnd;
                    continue;
                }
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
                $close = $this->findAttributeSelectorEnd($selector, $i);
                $inner = substr($selector, $i + 1, $close - $i - 1);
                $output .= '[' . $this->rewriteAttributeSelectorContent($inner) . ']';
                $i = $close;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function assertCssModulesFunctionalSelector(string $selector): void
    {
        $trimmed = trim($selector);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Invalid empty selector');
        }

        // CSS Modules mode pseudos take a selector, not a relative selector.
        if ($trimmed[0] === ',' || str_contains('>+~', $trimmed[0])) {
            throw new \InvalidArgumentException('Invalid empty selector');
        }

        if ($this->findNextTopLevel($selector, ',', 0) !== null) {
            throw new \InvalidArgumentException('Unexpected token Comma');
        }

        $this->assertNoDanglingCombinatorInSelector($selector);
    }

    private function assertNoDanglingCombinatorInSelector(string $selector): void
    {
        $quote = null;
        $bracketDepth = 0;
        $parenDepth = 0;
        $lastTopLevel = '';
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];

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
                if ($bracketDepth === 0 && $parenDepth === 0) {
                    $lastTopLevel = $char;
                }
                continue;
            }

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($selector, $i);
                if ($escapeEnd !== null) {
                    if ($bracketDepth === 0 && $parenDepth === 0) {
                        $lastTopLevel = '\\';
                    }
                    $i = $escapeEnd;
                    continue;
                }
            }

            if ($char === '[') {
                if ($bracketDepth === 0 && $parenDepth === 0) {
                    $lastTopLevel = $char;
                }
                $bracketDepth++;
                continue;
            }

            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                if ($bracketDepth === 0 && $parenDepth === 0) {
                    $lastTopLevel = $char;
                }
                continue;
            }

            if ($char === '(') {
                if ($bracketDepth === 0 && $parenDepth === 0) {
                    $lastTopLevel = $char;
                }
                $parenDepth++;
                continue;
            }

            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                if ($bracketDepth === 0 && $parenDepth === 0) {
                    $lastTopLevel = $char;
                }
                continue;
            }

            if ($bracketDepth !== 0 || $parenDepth !== 0 || ctype_space($char)) {
                continue;
            }

            $lastTopLevel = $char;
        }

        if ($lastTopLevel !== '' && str_contains('>+~', $lastTopLevel)) {
            throw new \InvalidArgumentException('Invalid dangling combinator in selector');
        }
    }

    /**
     * @return array{prefix:string,name:string,open:int}|null
     */
    private function viewTransitionSelectorFunctionAt(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? '') !== ':') {
            return null;
        }

        $prefixLength = ($selector[$offset + 1] ?? '') === ':' ? 2 : 1;
        $identifier = $this->readCssIdentifierToken($selector, $offset + $prefixLength);
        if ($identifier === null || ($selector[$identifier['end']] ?? '') !== '(') {
            return null;
        }

        $name = strtolower($identifier['decoded']);
        if ($prefixLength === 1 && $name === 'active-view-transition-type') {
            return [
                'prefix' => ':',
                'name' => 'active-view-transition-type',
                'open' => $identifier['end'],
            ];
        }

        if ($prefixLength === 2 && in_array($name, [
            'view-transition-group',
            'view-transition-image-pair',
            'view-transition-new',
            'view-transition-old',
        ], true)) {
            return [
                'prefix' => '::',
                'name' => $name,
                'open' => $identifier['end'],
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

    private function assertNoCssModulesModePseudoInViewTransitionSelectorArgs(string $args): void
    {
        $this->assertNoCssModulesModePseudoInSelectorFunctionArgs(
            $args,
            'CSS Modules :local and :global selectors are not valid inside view-transition selector functions'
        );
    }

    private function assertNoCssModulesModePseudoInSelectorFunctionArgs(string $args, string $message): void
    {
        $quote = null;
        $length = strlen($args);

        for ($i = 0; $i < $length; $i++) {
            $char = $args[$i];

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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($args, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
            }

            if (
                $this->cssModulesPseudoFunctionAt($args, $i, 'global') !== null
                || $this->cssModulesPseudoFunctionAt($args, $i, 'local') !== null
                || $this->cssModulesBarePseudoNameAt($args, $i, 'global')
                || $this->cssModulesBarePseudoNameAt($args, $i, 'local')
            ) {
                throw new \InvalidArgumentException($message);
            }
        }
    }

    private function rewriteViewTransitionSelectorIdentSequence(string $value): string
    {
        $value = trim($value);
        $length = strlen($value);
        if ($value === '') {
            return $value;
        }

        $output = '';
        $cursor = 0;

        if ($value[$cursor] === '*') {
            $output .= '*';
            $cursor++;
        } elseif ($value[$cursor] !== '.') {
            $token = $this->readCssIdentifierToken($value, $cursor);
            if ($token === null) {
                return $value;
            }

            $output .= $this->escapeCssIdentifier($this->scopeCustomIdent($token['decoded']));
            $cursor = $token['end'];
        }

        while ($cursor < $length) {
            if (ctype_space($value[$cursor])) {
                if (trim(substr($value, $cursor)) === '') {
                    break;
                }

                return $value;
            }

            if ($value[$cursor] !== '.') {
                return $value;
            }

            $token = $this->readCssIdentifierToken($value, $cursor + 1);
            if ($token === null) {
                return $value;
            }

            $output .= '.' . $this->escapeCssIdentifier($this->scopeCustomIdent($token['decoded']));
            $cursor = $token['end'];
        }

        return $output;
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
            '[name]' => $this->patternFileName(),
            '[hash]' => $this->hash,
            '[content-hash]' => $this->contentHash,
            '[local]' => $local,
        ]);
    }

    private function assertValidCssModulesPattern(string $pattern): void
    {
        $offset = 0;
        $length = strlen($pattern);
        $validPlaceholders = [
            '[name]' => true,
            '[hash]' => true,
            '[content-hash]' => true,
            '[local]' => true,
        ];

        while ($offset < $length) {
            $open = strpos($pattern, '[', $offset);
            if ($open === false) {
                return;
            }

            $close = strpos($pattern, ']', $open + 1);
            if ($close === false) {
                throw new \InvalidArgumentException(
                    'Error parsing CSS modules pattern: unclosed brackets at index ' . $open
                );
            }

            $placeholder = substr($pattern, $open, $close - $open + 1);
            if (!isset($validPlaceholders[$placeholder])) {
                throw new \InvalidArgumentException(
                    'Error parsing CSS modules pattern: unknown placeholder "' . $placeholder . '" at index ' . $open
                );
            }

            $offset = $close + 1;
        }
    }

    public static function filenameHashForPattern(string $filename, ?string $projectRoot = null, string $pattern = '[hash]_[local]'): string
    {
        return self::hashCssModuleString(
            self::relativeFilenameForHash($filename, $projectRoot),
            str_starts_with($pattern, '[hash]')
        );
    }

    /**
     * @param array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}> $exports
     * @param (callable(string, string): (string|list<string>|null))|null $resolveDependency
     * @return array<string, string>
     */
    public static function exportClassLists(array $exports, ?callable $resolveDependency = null): array
    {
        $classLists = [];
        foreach (array_keys($exports) as $local) {
            $classLists[(string) $local] = self::exportClassList($exports, (string) $local, $resolveDependency) ?? '';
        }

        return $classLists;
    }

    /**
     * @param array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}> $exports
     * @param (callable(string, string): (string|list<string>|null))|null $resolveDependency
     */
    public static function exportClassList(array $exports, string $local, ?callable $resolveDependency = null): ?string
    {
        $export = $exports[$local] ?? null;
        if ($export === null) {
            return null;
        }

        $classes = [(string) $export['name']];
        $exportLocalsByName = self::cssModuleExportLocalsByScopedName($exports);
        self::appendCssModuleComposeClasses(
            $classes,
            $exports,
            $exportLocalsByName,
            $export['composes'] ?? [],
            $resolveDependency,
            [$local => true]
        );

        return implode(' ', array_values(array_filter($classes, static fn (string $className): bool => $className !== '')));
    }

    /**
     * @param array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}> $exports
     * @return array<string, string>
     */
    private static function cssModuleExportLocalsByScopedName(array $exports): array
    {
        $localsByName = [];
        foreach ($exports as $local => $export) {
            $name = (string) ($export['name'] ?? '');
            if ($name !== '' && !isset($localsByName[$name])) {
                $localsByName[$name] = (string) $local;
            }
        }

        return $localsByName;
    }

    /**
     * @param list<string> $classes
     * @param array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}> $exports
     * @param array<string, string> $exportLocalsByName
     * @param list<array{type:string, name:string, specifier?:string}> $references
     * @param (callable(string, string): (string|list<string>|null))|null $resolveDependency
     * @param array<string, true> $stack
     */
    private static function appendCssModuleComposeClasses(
        array &$classes,
        array $exports,
        array $exportLocalsByName,
        array $references,
        ?callable $resolveDependency,
        array $stack
    ): void {
        foreach ($references as $reference) {
            $type = (string) ($reference['type'] ?? '');
            if ($type === 'local') {
                $name = (string) ($reference['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $local = $exportLocalsByName[$name] ?? null;
                if ($local !== null && isset($stack[$local])) {
                    continue;
                }

                $classes[] = $name;
                if ($local !== null) {
                    $nextStack = $stack;
                    $nextStack[$local] = true;
                    self::appendCssModuleComposeClasses(
                        $classes,
                        $exports,
                        $exportLocalsByName,
                        $exports[$local]['composes'] ?? [],
                        $resolveDependency,
                        $nextStack
                    );
                }
                continue;
            }

            if ($type === 'global') {
                $classes[] = (string) ($reference['name'] ?? '');
                continue;
            }

            if ($type === 'dependency') {
                if ($resolveDependency === null) {
                    throw new \InvalidArgumentException('Cannot flatten unresolved CSS Modules dependency reference');
                }

                $resolved = $resolveDependency((string) ($reference['name'] ?? ''), (string) ($reference['specifier'] ?? ''));
                if ($resolved === null) {
                    continue;
                }

                foreach ((array) $resolved as $className) {
                    $classes[] = (string) $className;
                }
                continue;
            }

            throw new \InvalidArgumentException('Invalid CSS Modules export reference');
        }
    }

    private function patternStartsWithSegment(string $segment): bool
    {
        return str_starts_with($this->pattern, $segment);
    }

    private function patternFileName(): string
    {
        $path = str_replace('\\', '/', $this->filename);
        $base = basename($path);
        $dot = strrpos($base, '.');
        $stem = $dot === false ? $base : substr($base, 0, $dot);

        return str_replace('.', '-', $stem);
    }

    private static function hashCssModuleString(string $value, bool $atStart = false): string
    {
        [$low] = self::sipHashString13($value);
        $hash = self::base64CssModuleHash($low);

        if ($atStart && preg_match('/^[0-9]/', $hash) === 1) {
            return '_' . $hash;
        }

        return $hash;
    }

    private static function relativeFilenameForHash(string $filename, ?string $projectRoot): string
    {
        $filename = str_replace('\\', '/', $filename);
        if ($projectRoot === null || $projectRoot === '') {
            return $filename;
        }

        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        if ($projectRoot === '') {
            return $filename;
        }

        if ($filename === $projectRoot) {
            return basename($filename);
        }

        $prefix = $projectRoot . '/';
        if (str_starts_with($filename, $prefix)) {
            return substr($filename, strlen($prefix));
        }

        return $filename;
    }

    /**
     * Rust's Hash implementation for strings appends a 0xff delimiter before
     * feeding SipHasher13. LightningCSS truncates that 64-bit result to u32.
     *
     * @return array{0:int,1:int}
     */
    private static function sipHashString13(string $value): array
    {
        $value .= "\xff";

        $v0 = [0x70736575, 0x736f6d65];
        $v1 = [0x6e646f6d, 0x646f7261];
        $v2 = [0x6e657261, 0x6c796765];
        $v3 = [0x79746573, 0x74656462];

        $length = strlen($value);
        $offset = 0;
        while ($offset + 8 <= $length) {
            $message = [
                ord($value[$offset])
                    | (ord($value[$offset + 1]) << 8)
                    | (ord($value[$offset + 2]) << 16)
                    | (ord($value[$offset + 3]) << 24),
                ord($value[$offset + 4])
                    | (ord($value[$offset + 5]) << 8)
                    | (ord($value[$offset + 6]) << 16)
                    | (ord($value[$offset + 7]) << 24),
            ];

            $v3 = self::u64Xor($v3, $message);
            self::sipRound($v0, $v1, $v2, $v3);
            $v0 = self::u64Xor($v0, $message);
            $offset += 8;
        }

        $last = [0, ($length & 0xff) << 24];
        $shift = 0;
        for ($i = $offset; $i < $length; $i++, $shift += 8) {
            if ($shift < 32) {
                $last[0] = ($last[0] | (ord($value[$i]) << $shift)) & self::U32_MASK;
            } else {
                $last[1] = ($last[1] | (ord($value[$i]) << ($shift - 32))) & self::U32_MASK;
            }
        }

        $v3 = self::u64Xor($v3, $last);
        self::sipRound($v0, $v1, $v2, $v3);
        $v0 = self::u64Xor($v0, $last);
        $v2 = self::u64Xor($v2, [0xff, 0]);

        self::sipRound($v0, $v1, $v2, $v3);
        self::sipRound($v0, $v1, $v2, $v3);
        self::sipRound($v0, $v1, $v2, $v3);

        return self::u64Xor(self::u64Xor($v0, $v1), self::u64Xor($v2, $v3));
    }

    private static function base64CssModuleHash(int $value): string
    {
        $bytes = [
            $value & 0xff,
            ($value >> 8) & 0xff,
            ($value >> 16) & 0xff,
            ($value >> 24) & 0xff,
        ];
        $output = '';

        for ($i = 0; $i < 4; $i += 3) {
            $remaining = min(3, 4 - $i);
            $chunk = 0;
            for ($j = 0; $j < $remaining; $j++) {
                $chunk = ($chunk << 8) | $bytes[$i + $j];
            }
            $chunk <<= (3 - $remaining) * 8;

            for ($j = 0; $j < $remaining + 1; $j++) {
                $output .= self::HASH_ALPHABET[($chunk >> (18 - (6 * $j))) & 0x3f];
            }
        }

        return $output;
    }

    /**
     * @param array{0:int,1:int} $v0
     * @param array{0:int,1:int} $v1
     * @param array{0:int,1:int} $v2
     * @param array{0:int,1:int} $v3
     */
    private static function sipRound(array &$v0, array &$v1, array &$v2, array &$v3): void
    {
        $v0 = self::u64Add($v0, $v1);
        $v1 = self::u64RotateLeft($v1, 13);
        $v1 = self::u64Xor($v1, $v0);
        $v0 = self::u64RotateLeft($v0, 32);
        $v2 = self::u64Add($v2, $v3);
        $v3 = self::u64RotateLeft($v3, 16);
        $v3 = self::u64Xor($v3, $v2);
        $v0 = self::u64Add($v0, $v3);
        $v3 = self::u64RotateLeft($v3, 21);
        $v3 = self::u64Xor($v3, $v0);
        $v2 = self::u64Add($v2, $v1);
        $v1 = self::u64RotateLeft($v1, 17);
        $v1 = self::u64Xor($v1, $v2);
        $v2 = self::u64RotateLeft($v2, 32);
    }

    /**
     * @param array{0:int,1:int} $left
     * @param array{0:int,1:int} $right
     *
     * @return array{0:int,1:int}
     */
    private static function u64Add(array $left, array $right): array
    {
        $lowSum = $left[0] + $right[0];
        $low = $lowSum & self::U32_MASK;
        $carry = intdiv($lowSum, self::U32_BASE);

        return [
            $low,
            ($left[1] + $right[1] + $carry) & self::U32_MASK,
        ];
    }

    /**
     * @param array{0:int,1:int} $left
     * @param array{0:int,1:int} $right
     *
     * @return array{0:int,1:int}
     */
    private static function u64Xor(array $left, array $right): array
    {
        return [
            ($left[0] ^ $right[0]) & self::U32_MASK,
            ($left[1] ^ $right[1]) & self::U32_MASK,
        ];
    }

    /**
     * @param array{0:int,1:int} $value
     *
     * @return array{0:int,1:int}
     */
    private static function u64RotateLeft(array $value, int $shift): array
    {
        $shift %= 64;
        if ($shift === 0) {
            return $value;
        }

        if ($shift === 32) {
            return [$value[1], $value[0]];
        }

        if ($shift < 32) {
            return [
                (($value[0] << $shift) | ($value[1] >> (32 - $shift))) & self::U32_MASK,
                (($value[1] << $shift) | ($value[0] >> (32 - $shift))) & self::U32_MASK,
            ];
        }

        $shift -= 32;

        return [
            (($value[1] << $shift) | ($value[0] >> (32 - $shift))) & self::U32_MASK,
            (($value[0] << $shift) | ($value[1] >> (32 - $shift))) & self::U32_MASK,
        ];
    }

    private function scopeCustomIdent(string $local): string
    {
        $this->ensureExport($local);

        return $this->scopedName($local);
    }

    private function scopeCustomIdentReference(string $local): string
    {
        $this->ensureExport($local);
        $this->exports[$local]['isReferenced'] = true;

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

    private function rewriteDashedIdentReferences(string $value, bool $recordDependencyReferences = true): string
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
                . $this->rewriteDashedFunctionArguments($inner, $functionName, $recordDependencyReferences)
                . ')';
            $cursor = $close + 1;
            $i = $close;
        }

        return $output . substr($value, $cursor);
    }

    private function rewriteDashedFunctionArguments(string $inner, string $functionName, bool $recordDependencyReferences): string
    {
        $comma = $this->findNextTopLevel($inner, ',', 0);
        $head = $comma === null ? trim($inner) : trim(substr($inner, 0, $comma));
        $tail = $comma === null ? null : substr($inner, $comma + 1);
        $rewrittenHead = $this->rewriteDashedReferenceToken(
            $head,
            strcasecmp($functionName, 'env') !== 0,
            $recordDependencyReferences
        );

        if ($tail === null) {
            return $rewrittenHead ?? $inner;
        }

        $rewrittenTail = $this->rewriteDashedIdentReferences(trim($tail), $recordDependencyReferences);

        return ($rewrittenHead ?? $head) . ',' . $rewrittenTail;
    }

    private function rewriteDashedReferenceToken(
        string $token,
        bool $allowDependencyFrom = true,
        bool $recordDependencyReferences = true
    ): ?string
    {
        $parts = $this->splitWhitespaceTopLevel($token);
        if ($parts === [] || !str_starts_with($parts[0], '--')) {
            return null;
        }

        $name = $parts[0];
        if (count($parts) === 1) {
            return $this->scopeDashedIdent($name, true);
        }

        if (count($parts) !== 3) {
            return null;
        }

        $fromKeyword = $this->decodeCssIdentifierToken($parts[1]);
        if ($fromKeyword === null || strcasecmp($fromKeyword, 'from') !== 0) {
            return null;
        }

        if (!$allowDependencyFrom) {
            throw new \InvalidArgumentException('Unexpected token Ident("from")');
        }

        $globalKeyword = $this->decodeCssIdentifierToken($parts[2]);
        if ($globalKeyword !== null && strcasecmp($globalKeyword, 'global') === 0) {
            return $name;
        }

        $specifier = $this->parseQuotedSpecifier($parts[2]);
        if ($specifier === null) {
            return null;
        }

        if (!$recordDependencyReferences) {
            return $this->scopeDashedIdent($name, true);
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($value, $i);
                if ($escapeEnd !== null) {
                    $parts[array_key_last($parts)] .= substr($value, $i, $escapeEnd - $i + 1);
                    $i = $escapeEnd;
                    continue;
                }
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($value, $i);
                if ($escapeEnd !== null) {
                    $current .= substr($value, $i, $escapeEnd - $i + 1);
                    $i = $escapeEnd;
                    continue;
                }
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($css, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($css, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
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

            if ($char === '\\') {
                $escapeEnd = $this->cssEscapeEnd($css, $i);
                if ($escapeEnd !== null) {
                    $i = $escapeEnd;
                    continue;
                }
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

    /**
     * @return array{open:int}|null
     */
    private function cssModulesPseudoFunctionAt(string $selector, int $offset, string $name): ?array
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return null;
        }

        if (($selector[$offset - 1] ?? '') === ':') {
            return null;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || strcasecmp($token['decoded'], $name) !== 0) {
            return null;
        }

        if (($selector[$token['end']] ?? '') !== '(') {
            return null;
        }

        return ['open' => $token['end']];
    }

    private function cssModulesBarePseudoNameAt(string $selector, int $offset, string $name): bool
    {
        if (($selector[$offset] ?? '') !== ':' || ($selector[$offset + 1] ?? '') === ':') {
            return false;
        }

        if (($selector[$offset - 1] ?? '') === ':') {
            return false;
        }

        $token = $this->readCssIdentifierToken($selector, $offset + 1);
        if ($token === null || strcasecmp($token['decoded'], $name) !== 0) {
            return false;
        }

        return ($selector[$token['end']] ?? '') !== '(';
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

    /**
     * @return array{raw:string, decoded:string, end:int}|null
     */
    private function readCssIdentifierToken(string $value, int $start): ?array
    {
        $length = strlen($value);
        $offset = $start;
        $decoded = '';

        if ($offset >= $length) {
            return null;
        }

        if ($value[$offset] === '-') {
            $decoded .= '-';
            $offset++;
        }

        if ($offset >= $length) {
            return null;
        }

        $first = $value[$offset];
        if ($first === '\\') {
            $escape = $this->readCssEscape($value, $offset);
            if ($escape === null) {
                return null;
            }
            $decoded .= $escape['decoded'];
            $offset = $escape['end'];
        } elseif ($first === '-' || $this->isCssIdentifierStartChar($first)) {
            $decoded .= $first;
            $offset++;
        } else {
            return null;
        }

        while ($offset < $length) {
            $char = $value[$offset];
            if ($char === '\\') {
                $escape = $this->readCssEscape($value, $offset);
                if ($escape === null) {
                    break;
                }
                $decoded .= $escape['decoded'];
                $offset = $escape['end'];
                continue;
            }

            if (!$this->isCssIdentifierChar($char)) {
                break;
            }

            $decoded .= $char;
            $offset++;
        }

        if ($decoded === '-' || $decoded === '--') {
            return null;
        }

        return [
            'raw' => substr($value, $start, $offset - $start),
            'decoded' => $decoded,
            'end' => $offset,
        ];
    }

    /**
     * @return array{decoded:string, end:int}|null
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

    private function cssEscapeEnd(string $value, int $offset): ?int
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
            return $offset + 1;
        }

        $cursor = $offset + 1;
        $hexLength = 0;
        while ($cursor < $length && $hexLength < 6 && ctype_xdigit($value[$cursor])) {
            $cursor++;
            $hexLength++;
        }

        if ($cursor < $length && ctype_space($value[$cursor])) {
            $cursor++;
        }

        return $cursor - 1;
    }

    private function escapeCssIdentifier(string $identifier): string
    {
        $output = '';
        $length = strlen($identifier);

        for ($i = 0; $i < $length; $i++) {
            $char = $identifier[$i];
            $code = ord($char);

            if ($i === 0 && ctype_digit($char)) {
                $output .= '\\' . dechex($code) . ' ';
                continue;
            }

            if ($i === 1 && $identifier[0] === '-' && ctype_digit($char)) {
                $output .= '\\' . dechex($code) . ' ';
                continue;
            }

            if ($this->isCssIdentifierChar($char) || $code >= 0x80) {
                $output .= $char;
                continue;
            }

            if ($char === "\0" || $char === "\n" || $char === "\r" || $char === "\f") {
                $output .= '\\' . dechex($code) . ' ';
                continue;
            }

            $output .= '\\' . $char;
        }

        return $output;
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0 || $codepoint > 0x10ffff) {
            $codepoint = 0xfffd;
        }

        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_NOQUOTES, 'UTF-8');
    }

    private function isCssIdentifierStartChar(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || ord($char) >= 0x80;
    }

    private function isCssIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-' || ord($char) >= 0x80;
    }

    private function isIdentChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }

    private function isPlainCssIdent(string $value): bool
    {
        return preg_match('/^-?(?:[A-Za-z_]|-[A-Za-z_])[A-Za-z0-9_-]*$/', $value) === 1;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private function stripComments(string $css): array
    {
        $output = '';
        $licenseComments = [];
        $quote = null;
        $braceDepth = 0;
        $parenDepth = 0;
        $bracketDepth = 0;
        $declarationHead = '';
        $inDeclarationValue = false;
        $inComposesDeclarationValue = false;
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
                } elseif (str_contains(substr($css, $i + 2, $end - $i - 2), 'cssmodules-pure-no-check')) {
                    $output .= self::PURE_NO_CHECK_MARKER;
                } elseif ($inComposesDeclarationValue) {
                    $output .= ' ';
                } elseif (
                    $this->commentBridgesCssIdentifierToken($css, $i, $end)
                    && ($braceDepth === 0 || $this->commentIsInSelectorLikePrelude($declarationHead))
                ) {
                    $output .= self::COMMENT_IDENTIFIER_BOUNDARY;
                } elseif ($braceDepth > 0 && !$inDeclarationValue && $parenDepth === 0 && $bracketDepth === 0) {
                    // Two spaces prevent a comment after a hex escape from becoming the escape terminator.
                    $output .= '  ';
                    $declarationHead .= '  ';
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                $declarationHead = '';
                $inDeclarationValue = false;
                $inComposesDeclarationValue = false;
                $output .= $char;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $declarationHead = '';
                $inDeclarationValue = false;
                $inComposesDeclarationValue = false;
                $output .= $char;
                continue;
            }

            if ($braceDepth > 0 && $parenDepth === 0 && $bracketDepth === 0) {
                if ($char === ';') {
                    $declarationHead = '';
                    $inDeclarationValue = false;
                    $inComposesDeclarationValue = false;
                } elseif ($char === ':' && !$inDeclarationValue) {
                    $property = trim($declarationHead);
                    $inComposesDeclarationValue = $property !== ''
                        && $this->normalizedDeclarationPropertyName($property) === 'composes';
                    $declarationHead = '';
                    $inDeclarationValue = true;
                } elseif (!$inDeclarationValue) {
                    $declarationHead .= $char;
                }
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

            $output .= $char;
        }

        return [$output, $licenseComments];
    }

    private function commentBridgesCssIdentifierToken(string $css, int $start, int $end): bool
    {
        $previous = $css[$start - 1] ?? '';
        $next = $css[$end + 2] ?? '';

        return $previous !== ''
            && $next !== ''
            && $this->isCssIdentifierCommentBoundaryChar($previous)
            && $this->isCssIdentifierCommentBoundaryChar($next);
    }

    private function isCssIdentifierCommentBoundaryChar(string $char): bool
    {
        return $char === '\\' || $char === '-' || $this->isCssIdentifierChar($char);
    }

    private function commentIsInSelectorLikePrelude(string $prelude): bool
    {
        return str_contains($prelude, '.')
            || str_contains($prelude, '#')
            || str_contains($prelude, ':')
            || str_contains($prelude, '[')
            || str_contains($prelude, '&');
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
}
