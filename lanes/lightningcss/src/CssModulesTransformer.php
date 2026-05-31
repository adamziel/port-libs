<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssModulesTransformer
{
    private const PURE_NO_CHECK_MARKER = '/*__lightningcss-cssmodules-pure-no-check__*/';
    private const HASH_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-';
    private const U32_MASK = 0xffffffff;
    private const U32_BASE = 4294967296;

    private string $hash = 'EgL3uq';
    private string $contentHash = '';
    private string $filename = 'test.css';
    private string $pattern = '[hash]_[local]';
    private bool $dashedIdents = false;
    private bool $animation = true;
    private bool $container = true;
    private bool $customIdents = true;
    private bool $pure = false;

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
     * @param array{hash?:string, contentHash?:string, filename?:string, projectRoot?:string, pattern?:string, minify?:bool, dashedIdents?:bool, dashed_idents?:bool, animation?:bool, container?:bool, customIdents?:bool, custom_idents?:bool, pure?:bool} $options
     */
    public function transform(string $css, array $options = []): array
    {
        $this->pattern = $options['pattern'] ?? '[hash]_[local]';
        $this->filename = $options['filename'] ?? 'test.css';
        $this->hash = $options['hash'] ?? self::hashCssModuleString(
            self::relativeFilenameForHash($this->filename, $options['projectRoot'] ?? null),
            $this->patternStartsWithSegment('[hash]')
        );
        $this->contentHash = $options['contentHash']
            ?? (str_contains($this->pattern, '[content-hash]')
                ? self::hashCssModuleString($css, $this->patternStartsWithSegment('[content-hash]'))
                : '');
        $this->dashedIdents = ($options['dashedIdents'] ?? $options['dashed_idents'] ?? false) === true;
        $this->animation = ($options['animation'] ?? true) !== false;
        $this->container = ($options['container'] ?? true) !== false;
        $this->customIdents = ($options['customIdents'] ?? $options['custom_idents'] ?? true) !== false;
        $this->pure = ($options['pure'] ?? false) === true;
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
                $output .= str_replace(self::PURE_NO_CHECK_MARKER, '', substr($css, $cursor, $nextStatement - $cursor + 1));
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
                $output .= $this->rewriteAtRulePrelude($prelude, $trimmedPrelude) . '{' . $this->transformAtRuleBody($trimmedPrelude, $body, $styleNestingDepth) . '}';
                $cursor = $close + 1;
                continue;
            }

            [$selector, $locals] = $this->rewriteSelectorList($prelude);
            if ($this->pure && !$skipPureCheck && $styleNestingDepth === 0) {
                $this->assertPureSelectorList($prelude);
            }
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

    private function rewriteAtRulePrelude(string $prelude, string $trimmedPrelude): string
    {
        if ($this->animation && preg_match('/^@(?:-[a-z]+-)?keyframes\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteKeyframesPrelude($prelude);
        }

        if ($this->customIdents && preg_match('/^@counter-style\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteCounterStylePrelude($prelude);
        }

        if ($this->container && $this->customIdents && preg_match('/^@container\b/i', $trimmedPrelude) === 1) {
            return $this->rewriteContainerPrelude($prelude);
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
                $output .= $this->rewriteAtRulePrelude($nestedPrelude, $trimmedNested) . '{' . $this->transformAtRuleBody($trimmedNested, $nestedBody, $styleNestingDepth + 1) . '}';
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

        $value = $this->stripDeclarationPriority(trim(substr($withoutSemicolon, $colon + 1)));
        array_push($composes, ...$this->parseComposesValue($value));

        return '';
    }

    private function stripDeclarationPriority(string $value): string
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
            return $value;
        }

        return rtrim(substr($value, 0, $bang));
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
            'animation' => $this->animation ? $this->rewriteAnimationShorthandValue($value) : null,
            'animation-name' => $this->animation ? $this->rewriteAnimationNameValue($value) : null,
            'list-style' => $this->rewriteListStyleValue($value),
            'list-style-type' => $this->rewriteListStyleTypeValue($value),
            'view-transition-name' => $this->rewriteViewTransitionNameValue($value),
            'view-transition-class' => $this->rewriteViewTransitionIdentList($value, ['none']),
            'view-transition-group' => $this->rewriteViewTransitionNameValue($value, ['contain']),
            default => null,
        };
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

            if ($bracketDepth === 0 && $this->startsWithPseudoFunction($selector, $i, ':global')) {
                $open = $i + strlen(':global');
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                if ($this->selectorHasLocalReference($inner, 'global')) {
                    return true;
                }
                $i = $close;
                continue;
            }

            if ($bracketDepth === 0 && $this->startsWithPseudoFunction($selector, $i, ':local')) {
                $open = $i + strlen(':local');
                $close = $this->findMatchingParen($selector, $open);
                $inner = substr($selector, $open + 1, $close - $open - 1);
                if ($this->selectorHasLocalReference($inner, $mode === 'global' ? 'global' : 'local')) {
                    return true;
                }
                $i = $close;
                continue;
            }

            if ($bracketDepth === 0 && $mode === 'local' && ($char === '.' || $char === '#')) {
                $token = $this->readCssIdentifierToken($selector, $i + 1);
                if ($token !== null) {
                    return true;
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
                $token = $this->readCssIdentifierToken($selector, $i + 1);
                if ($token !== null) {
                    $local = $token['decoded'];
                    $locals[$local] = true;
                    $this->ensureExport($local);
                    $output .= $char . $this->escapeCssIdentifier($this->scopedName($local));
                    $i = $token['end'] - 1;
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
            '[name]' => $this->patternFileName(),
            '[hash]' => $this->hash,
            '[content-hash]' => $this->contentHash,
            '[local]' => $local,
        ]);
    }

    public static function filenameHashForPattern(string $filename, ?string $projectRoot = null, string $pattern = '[hash]_[local]'): string
    {
        return self::hashCssModuleString(
            self::relativeFilenameForHash($filename, $projectRoot),
            str_starts_with($pattern, '[hash]')
        );
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
                if (str_contains(substr($css, $i + 2, $end - $i - 2), 'cssmodules-pure-no-check')) {
                    $output .= self::PURE_NO_CHECK_MARKER;
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }
}
