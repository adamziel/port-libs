<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class TransitionPrefixer
{
    private const RTL_LANGS = [
        'ae',
        'ar',
        'arc',
        'bcc',
        'bqi',
        'ckb',
        'dv',
        'fa',
        'glk',
        'he',
        'ku',
        'mzn',
        'nqo',
        'pnb',
        'ps',
        'sd',
        'ug',
        'ur',
        'yi',
    ];

    private const LENGTH_TARGET_FALLBACK_PROPERTIES = [
        'margin-right' => true,
        'margin' => true,
        'padding-right' => true,
        'padding' => true,
        'width' => true,
        'height' => true,
        'min-height' => true,
        'max-height' => true,
        'line-height' => true,
        'border-radius' => true,
    ];

    public function prefixLegacySafari(string $css): string
    {
        return $this->prefixForTargets($css, ['chrome' => 4, 'safari' => 14]);
    }

    /**
     * @param array<string, mixed> $targets
     */
    public function prefixForTargets(string $css, array $targets): string
    {
        return $this->rewriteRuleList((new CssMinifier())->minify($css, preserveFontTargetFallbacks: true), false, $this->targetOptions($targets));
    }

    /**
     * @param array<string, bool>|null $targetOptions
     */
    private function rewriteRuleList(
        string $css,
        bool $insideAdvancedColorSupports = false,
        ?array $targetOptions = null,
        bool $insideLightDarkSupports = false
    ): string
    {
        $targetOptions ??= $this->targetOptions([]);
        $output = '';
        $cursor = 0;
        $length = strlen($css);
        $emittedKeyframes = [];
        $lastMergeableStyleRule = null;

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBrace($css, $open);
            $prelude = trim(substr($css, $cursor, $open - $cursor));
            $body = substr($css, $open + 1, $close - $open - 1);
            if (str_starts_with($prelude, '@')) {
                $lastMergeableStyleRule = null;
                $prelude = $this->rewriteMediaRangePrelude($prelude, $targetOptions);
                $prelude = $this->rewriteSupportsDeclarationPrefixPrelude($prelude, $targetOptions);
                if ($this->isKeyframesPrelude($prelude)) {
                    $output .= $this->rewriteKeyframesRule($prelude, $body, $targetOptions, $emittedKeyframes);
                    $cursor = $close + 1;
                    continue;
                }
                if ($this->isFontPaletteValuesPrelude($prelude)) {
                    $output .= $this->rewriteFontPaletteValuesRule($prelude, $body, $insideAdvancedColorSupports);
                    $cursor = $close + 1;
                    continue;
                }
                $output .= $prelude . '{' . $this->rewriteRuleList(
                    $body,
                    $insideAdvancedColorSupports || $this->isAdvancedColorSupportsPrelude($prelude),
                    $targetOptions,
                    $insideLightDarkSupports || $this->isLightDarkSupportsPrelude($prelude)
                ) . '}';
            } else {
                $rewrittenStyleRule = $this->rewriteStyleRule(
                    $prelude,
                    $body,
                    $insideAdvancedColorSupports,
                    $insideLightDarkSupports,
                    $targetOptions
                );
                $this->appendRewrittenStyleRule(
                    $output,
                    $rewrittenStyleRule,
                    $rewrittenStyleRule !== $prelude . '{' . $body . '}',
                    $lastMergeableStyleRule,
                    $targetOptions
                );
            }
            $cursor = $close + 1;
        }

        return $output;
    }

    /**
     * @param array{selectors:string, body:string, start:int, changed:bool, prefixInfo:array{canonical:string,prefixed:bool,needed:bool}, selectorSet:array<string, bool>}|null $lastMergeableStyleRule
     * @param array<string, bool> $targetOptions
     */
    private function appendRewrittenStyleRule(
        string &$output,
        string $rule,
        bool $changed,
        ?array &$lastMergeableStyleRule,
        array $targetOptions
    ): void
    {
        $rules = $this->splitStyleRuleList($rule);
        if ($rules !== null && count($rules) > 1) {
            foreach ($rules as $singleRule) {
                $this->appendSingleRewrittenStyleRule($output, $singleRule, $changed, $lastMergeableStyleRule, $targetOptions, false);
            }

            return;
        }

        $this->appendSingleRewrittenStyleRule($output, $rule, $changed, $lastMergeableStyleRule, $targetOptions, true);
    }

    /**
     * @param array{selectors:string, body:string, start:int, changed:bool, prefixInfo:array{canonical:string,prefixed:bool,needed:bool}, selectorSet:array<string, bool>}|null $lastMergeableStyleRule
     * @param array<string, bool> $targetOptions
     */
    private function appendSingleRewrittenStyleRule(
        string &$output,
        string $rule,
        bool $changed,
        ?array &$lastMergeableStyleRule,
        array $targetOptions,
        bool $allowSelectorMerge
    ): void
    {
        $parsed = $this->parseSingleStyleRule($rule);
        if ($parsed === null) {
            $output .= $rule;
            $lastMergeableStyleRule = null;
            return;
        }

        $prefixInfo = $this->selectorPrefixCanonicalInfo($parsed['selectors'], $targetOptions);
        $sameBodyAsLast = $lastMergeableStyleRule !== null && $lastMergeableStyleRule['body'] === $parsed['body'];
        $selectorSet = $sameBodyAsLast ? $lastMergeableStyleRule['selectorSet'] : [];

        if ($sameBodyAsLast && isset($selectorSet[$parsed['selectors']])) {
            return;
        }

        if ($sameBodyAsLast
            && $lastMergeableStyleRule['prefixInfo']['canonical'] === $prefixInfo['canonical']
            && $lastMergeableStyleRule['prefixInfo']['prefixed']
            && !$lastMergeableStyleRule['prefixInfo']['needed']
        ) {
            if (!$prefixInfo['prefixed']) {
                $output = substr($output, 0, $lastMergeableStyleRule['start'])
                    . $parsed['selectors'] . '{' . $parsed['body'] . '}';
                unset($selectorSet[$lastMergeableStyleRule['selectors']]);
                $selectorSet[$parsed['selectors']] = true;
                $lastMergeableStyleRule = [
                    'selectors' => $parsed['selectors'],
                    'body' => $parsed['body'],
                    'start' => $lastMergeableStyleRule['start'],
                    'changed' => true,
                    'prefixInfo' => $prefixInfo,
                    'selectorSet' => $selectorSet,
                ];
                return;
            }

            if (!$prefixInfo['needed']) {
                return;
            }
        }

        if ($sameBodyAsLast
            && !$lastMergeableStyleRule['prefixInfo']['prefixed']
            && $prefixInfo['prefixed']
            && !$prefixInfo['needed']
            && $lastMergeableStyleRule['prefixInfo']['canonical'] === $prefixInfo['canonical']
        ) {
            return;
        }

        if ($sameBodyAsLast
            && $allowSelectorMerge
            && ($lastMergeableStyleRule['changed'] || $changed)
        ) {
            $selectors = $lastMergeableStyleRule['selectors'] . ',' . $parsed['selectors'];
            $prefixInfo = $this->selectorPrefixCanonicalInfo($selectors, $targetOptions);
            $output = substr($output, 0, $lastMergeableStyleRule['start']) . $selectors . '{' . $parsed['body'] . '}';
            unset($selectorSet[$lastMergeableStyleRule['selectors']]);
            $selectorSet[$selectors] = true;
            $lastMergeableStyleRule = [
                'selectors' => $selectors,
                'body' => $parsed['body'],
                'start' => $lastMergeableStyleRule['start'],
                'changed' => true,
                'prefixInfo' => $prefixInfo,
                'selectorSet' => $selectorSet,
            ];
            return;
        }

        $start = strlen($output);
        $output .= $rule;
        $selectorSet[$parsed['selectors']] = true;
        $lastMergeableStyleRule = [
            'selectors' => $parsed['selectors'],
            'body' => $parsed['body'],
            'start' => $start,
            'changed' => $changed,
            'prefixInfo' => $prefixInfo,
            'selectorSet' => $selectorSet,
        ];
    }

    /**
     * @return list<string>|null
     */
    private function splitStyleRuleList(string $rules): ?array
    {
        $parts = [];
        $cursor = 0;
        $length = strlen($rules);
        while ($cursor < $length) {
            $open = $this->findNextTopLevel($rules, '{', $cursor);
            if ($open === null) {
                return trim(substr($rules, $cursor)) === '' ? $parts : null;
            }

            $prelude = trim(substr($rules, $cursor, $open - $cursor));
            if ($prelude === '' || str_starts_with($prelude, '@')) {
                return null;
            }

            $close = $this->findMatchingBrace($rules, $open);
            $parts[] = $prelude . '{' . substr($rules, $open + 1, $close - $open - 1) . '}';
            $cursor = $close + 1;
        }

        return $parts === [] ? null : $parts;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return array{canonical:string,prefixed:bool,needed:bool}
     */
    private function selectorPrefixCanonicalInfo(string $selectors, array $targetOptions): array
    {
        $prefixed = false;
        $needed = false;
        $canonical = $selectors;
        foreach ($this->selectorPrefixCanonicalPatterns($targetOptions) as $pattern) {
            $canonical = preg_replace_callback(
                $pattern['pattern'],
                static function () use (&$prefixed, &$needed, $pattern): string {
                    $prefixed = true;
                    $needed = $needed || $pattern['needed'];

                    return $pattern['canonical'];
                },
                $canonical
            ) ?? $canonical;
        }

        return [
            'canonical' => $canonical,
            'prefixed' => $prefixed,
            'needed' => $needed,
        ];
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{pattern:string,canonical:string,needed:bool}>
     */
    private function selectorPrefixCanonicalPatterns(array $targetOptions): array
    {
        return [
            ['pattern' => '/::-moz-selection(?![-_a-z0-9])/i', 'canonical' => '::selection', 'needed' => $targetOptions['selectionNeedsMoz'] ?? false],
            ['pattern' => '/:-moz-placeholder-shown(?![-_a-z0-9])/i', 'canonical' => ':placeholder-shown', 'needed' => $targetOptions['placeholderShownNeedsMoz'] ?? false],
            ['pattern' => '/:-ms-placeholder-shown(?![-_a-z0-9])/i', 'canonical' => ':placeholder-shown', 'needed' => $targetOptions['placeholderShownNeedsMs'] ?? false],
            ['pattern' => '/:-webkit-full-screen(?![-_a-z0-9])/i', 'canonical' => ':fullscreen', 'needed' => $targetOptions['fullscreenNeedsWebkit'] ?? false],
            ['pattern' => '/:-moz-full-screen(?![-_a-z0-9])/i', 'canonical' => ':fullscreen', 'needed' => $targetOptions['fullscreenNeedsMoz'] ?? false],
            ['pattern' => '/:-ms-fullscreen(?![-_a-z0-9])/i', 'canonical' => ':fullscreen', 'needed' => $targetOptions['fullscreenNeedsMs'] ?? false],
            ['pattern' => '/::-webkit-backdrop(?![-_a-z0-9])/i', 'canonical' => '::backdrop', 'needed' => $targetOptions['backdropNeedsWebkit'] ?? false],
            ['pattern' => '/::-ms-backdrop(?![-_a-z0-9])/i', 'canonical' => '::backdrop', 'needed' => $targetOptions['backdropNeedsMs'] ?? false],
            ['pattern' => '/::-webkit-file-upload-button(?![-_a-z0-9])/i', 'canonical' => '::file-selector-button', 'needed' => $targetOptions['fileSelectorButtonNeedsWebkit'] ?? false],
            ['pattern' => '/::-ms-browse(?![-_a-z0-9])/i', 'canonical' => '::file-selector-button', 'needed' => $targetOptions['fileSelectorButtonNeedsMs'] ?? false],
            ['pattern' => '/:-webkit-autofill(?![-_a-z0-9])/i', 'canonical' => ':autofill', 'needed' => $targetOptions['autofillNeedsWebkit'] ?? false],
            ['pattern' => '/:-moz-read-only(?![-_a-z0-9])/i', 'canonical' => ':read-only', 'needed' => $targetOptions['readWriteNeedsMoz'] ?? false],
            ['pattern' => '/:-moz-read-write(?![-_a-z0-9])/i', 'canonical' => ':read-write', 'needed' => $targetOptions['readWriteNeedsMoz'] ?? false],
            ['pattern' => '/:-webkit-any-link(?![-_a-z0-9])/i', 'canonical' => ':any-link', 'needed' => $targetOptions['anyLinkNeedsWebkit'] ?? false],
            ['pattern' => '/:-moz-any-link(?![-_a-z0-9])/i', 'canonical' => ':any-link', 'needed' => $targetOptions['anyLinkNeedsMoz'] ?? false],
            ['pattern' => '/::-webkit-input-placeholder(?![-_a-z0-9])/i', 'canonical' => '::placeholder', 'needed' => $targetOptions['placeholderNeedsWebkit'] ?? false],
            ['pattern' => '/::-moz-placeholder(?![-_a-z0-9])/i', 'canonical' => '::placeholder', 'needed' => $targetOptions['placeholderNeedsMoz'] ?? false],
            ['pattern' => '/::-ms-input-placeholder(?![-_a-z0-9])/i', 'canonical' => '::placeholder', 'needed' => $targetOptions['placeholderNeedsMs'] ?? false],
        ];
    }

    /**
     * @return array{selectors:string, body:string}|null
     */
    private function parseSingleStyleRule(string $rule): ?array
    {
        if ($rule === '' || str_starts_with($rule, '@')) {
            return null;
        }

        $open = $this->findNextTopLevel($rule, '{', 0);
        if ($open === null) {
            return null;
        }

        $close = $this->findMatchingBrace($rule, $open);
        if ($close !== strlen($rule) - 1) {
            return null;
        }

        $selectors = substr($rule, 0, $open);
        if ($selectors === '' || str_starts_with($selectors, '@')) {
            return null;
        }

        return [
            'selectors' => $selectors,
            'body' => substr($rule, $open + 1, $close - $open - 1),
        ];
    }

    private function isKeyframesPrelude(string $prelude): bool
    {
        return preg_match('/^@(?:-(?:webkit|moz|o)-)?keyframes\b/i', $prelude) === 1;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @param array<string, true> $emittedKeyframes
     */
    private function rewriteKeyframesRule(string $prelude, string $body, array $targetOptions, array &$emittedKeyframes): string
    {
        if (preg_match('/^@(?:(-(?:webkit|moz|o)-))?keyframes\s+(.+)$/i', $prelude, $matches) !== 1) {
            return $prelude . '{' . $body . '}';
        }

        $prefix = strtolower($matches[1] ?? '');
        $name = $matches[2];
        $rules = [];
        if ($targetOptions['keyframesNeedsWebkit']) {
            $this->appendKeyframesRule($rules, $emittedKeyframes, '@-webkit-keyframes', $name, $body);
        } elseif ($prefix === '-webkit-') {
            return '';
        }

        if ($targetOptions['keyframesNeedsMoz']) {
            $this->appendKeyframesRule($rules, $emittedKeyframes, '@-moz-keyframes', $name, $body);
        } elseif ($prefix === '-moz-') {
            return '';
        }

        if ($targetOptions['keyframesNeedsO']) {
            $this->appendKeyframesRule($rules, $emittedKeyframes, '@-o-keyframes', $name, $body);
        } elseif ($prefix === '-o-') {
            return '';
        }

        if ($prefix === '') {
            $this->appendKeyframesRule($rules, $emittedKeyframes, '@keyframes', $name, $body);
        }

        return implode('', $rules);
    }

    /**
     * @param list<string> $rules
     * @param array<string, true> $emittedKeyframes
     */
    private function appendKeyframesRule(array &$rules, array &$emittedKeyframes, string $keyword, string $name, string $body): void
    {
        $key = strtolower($keyword . ' ' . $name);
        if (isset($emittedKeyframes[$key])) {
            return;
        }

        $emittedKeyframes[$key] = true;
        $rules[] = $keyword . ' ' . $name . '{' . $body . '}';
    }

    private function isFontPaletteValuesPrelude(string $prelude): bool
    {
        return preg_match('/^@font-palette-values\b/i', $prelude) === 1;
    }

    private function rewriteFontPaletteValuesRule(string $prelude, string $body, bool $insideAdvancedColorSupports): string
    {
        $entries = $this->parseDeclarations($body);
        if ($entries === null || $insideAdvancedColorSupports) {
            return $prelude . '{' . $body . '}';
        }

        $rewritten = [];
        $supportEntries = $entries;
        $changed = false;
        $needsLabSupport = false;

        foreach ($entries as $index => $entry) {
            if ($entry['property'] !== 'override-colors' || $entry['important']) {
                $rewritten[] = $entry;
                continue;
            }

            $fallback = $this->advancedColorFallbackValue($entry['value']);
            if ($fallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            $rewritten[] = $this->entryWithValue($entry, $fallback);
            $changed = true;

            if ($this->containsCustomPropertyReference($entry['value'])) {
                $labFallback = $this->advancedColorLabFallbackValue($entry['value'], true);
                if ($labFallback !== null && $labFallback !== $fallback) {
                    $supportEntries[$index] = $this->entryWithValue($entry, $labFallback);
                    $needsLabSupport = true;
                }
                continue;
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return $prelude . '{' . $body . '}';
        }

        $output = $prelude . '{' . $this->serializeDeclarations($rewritten) . '}';
        if ($needsLabSupport) {
            $output .= '@supports (color:lab(0% 0 0)){' . $prelude . '{' . $this->serializeDeclarations($supportEntries) . '}}';
        }

        return $output;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function rewriteStyleRule(
        string $selectors,
        string $body,
        bool $insideAdvancedColorSupports,
        bool $insideLightDarkSupports,
        array $targetOptions
    ): string
    {
        $entries = $this->parseDeclarations($body);
        if ($entries === null) {
            return $selectors . '{' . $body . '}';
        }

        $ltrEntries = $entries;
        $rtlEntries = $entries;
        $hasLtrInlineTransition = $this->rewriteInlineTransitionEntries($ltrEntries, 'ltr');
        $hasRtlInlineTransition = $this->rewriteInlineTransitionEntries($rtlEntries, 'rtl');
        $hasInlineTransition = $hasLtrInlineTransition || $hasRtlInlineTransition;

        if ($hasInlineTransition) {
            $this->rewritePrefixedTransitionEntries($ltrEntries, $targetOptions);
            $this->rewritePrefixedTransitionEntries($rtlEntries, $targetOptions);
            $this->rewriteMaskPrefixEntries($ltrEntries, $targetOptions);
            $this->rewriteMaskPrefixEntries($rtlEntries, $targetOptions);

            return $this->selectorVariant($selectors, 'ltr-webkit') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selectors, 'ltr-modern') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selectors, 'rtl-webkit') . '{' . $this->serializeDeclarations($rtlEntries) . '}'
                . $this->selectorVariant($selectors, 'rtl-modern') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        if ($targetOptions['borderRadiusNeedsLogicalFallback'] ?? false) {
            $logicalRadiusFallback = $this->rewriteLogicalBorderRadiusEntries($entries);
            if ($logicalRadiusFallback !== null) {
                return $this->selectorVariant($selectors, 'ltr-modern') . '{' . $this->serializeDeclarations($logicalRadiusFallback[0]) . '}'
                    . $this->selectorVariant($selectors, 'rtl-modern') . '{' . $this->serializeDeclarations($logicalRadiusFallback[1]) . '}';
            }
        }

        $transitionChanged = $this->rewritePrefixedTransitionEntries($entries, $targetOptions);
        $supportRules = [];
        $displayFlexChanged = $this->rewriteDisplayFlexPrefixEntries($entries, $targetOptions);
        $flexChanged = $this->rewriteFlexPrefixEntries($entries, $targetOptions);
        $animationTimelineChanged = $this->rewriteAnimationTimelineShorthandEntries($entries, $targetOptions);
        $animationChanged = $this->rewriteAnimationPrefixEntries($entries, $targetOptions) || $animationTimelineChanged;
        $colorSchemeChanged = $this->rewriteColorSchemeFallbackEntries($entries, $selectors, $supportRules, $targetOptions);
        $printColorAdjustChanged = $this->rewritePrintColorAdjustPrefixEntries($entries, $targetOptions);
        $columnsChanged = $this->rewriteColumnsPrefixEntries($entries, $targetOptions);
        $uiPrefixChanged = $this->rewriteUiPrefixEntries($entries, $targetOptions);
        $cursorPrefixChanged = $this->rewriteCursorPrefixEntries($entries, $targetOptions);
        $boxSizingChanged = $this->rewriteBoxSizingPrefixEntries($entries, $targetOptions);
        $objectFitChanged = $this->rewriteObjectFitPrefixEntries($entries, $targetOptions);
        $unicodeBidiChanged = $this->rewriteUnicodeBidiPrefixEntries($entries, $targetOptions);
        $textCompatibilityPrefixChanged = $this->rewriteTextCompatibilityPrefixEntries($entries, $targetOptions);
        $scrollSnapPrefixChanged = $this->rewriteScrollSnapPrefixEntries($entries, $targetOptions);
        $breakPrefixChanged = $this->rewriteBreakPrefixEntries($entries, $targetOptions);
        $overflowShorthandChanged = $this->rewriteOverflowShorthandFallbackEntries($entries, $targetOptions);
        $transformPrefixChanged = $this->rewriteTransformPrefixEntries($entries, $targetOptions);
        $positionStickyChanged = $this->rewritePositionStickyPrefixEntries($entries, $targetOptions);
        $backgroundSizeOriginChanged = $this->rewriteBackgroundSizeOriginPrefixEntries($entries, $targetOptions);
        $backgroundClipChanged = $this->rewriteBackgroundClipPrefixEntries($entries, $targetOptions);
        $clipPathChanged = $this->rewriteClipPathPrefixEntries($entries, $targetOptions);
        $maskChanged = $this->rewriteMaskPrefixEntries($entries, $targetOptions, $selectors, $supportRules);
        $filterChanged = $this->rewriteFilterPrefixEntries($entries, $selectors, $supportRules, $targetOptions);
        $boxShadowChanged = $this->rewriteBoxShadowPrefixEntries($entries, $selectors, $supportRules, $targetOptions);
        $textShadowChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteTextShadowFallbackEntries($entries, $selectors, $supportRules, $targetOptions);
        $textDecorationChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteTextDecorationPrefixEntries($entries, $selectors, $supportRules, $targetOptions);
        $textEmphasisChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteTextEmphasisPrefixEntries($entries, $selectors, $supportRules, $targetOptions);
        $caretChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteCaretFallbackEntries($entries, $selectors, $supportRules, $targetOptions);
        $listStyleChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteListStyleFallbackEntries($entries, $selectors, $supportRules, $targetOptions);
        $borderRadiusChanged = $this->rewriteBorderRadiusPrefixEntries($entries, $targetOptions);
        $borderImageChanged = $this->rewriteBorderImagePrefixEntries($entries, $targetOptions);
        $imageSetChanged = $this->rewriteImageSetPrefixEntries($entries, $targetOptions);
        $gradientPrefixChanged = $this->rewriteGradientPrefixEntries($entries, $targetOptions);
        $sizingKeywordChanged = $this->rewriteSizingKeywordPrefixEntries($entries, $targetOptions);
        $logicalSizeFallbackChanged = $this->rewriteLogicalSizeFallbackEntries($entries, $targetOptions);
        $clampChanged = $this->rewriteClampFallbackEntries($entries, $targetOptions);
        $colorChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteAdvancedColorFallbackEntries($entries, $selectors, $supportRules, $targetOptions);
        $lightDarkChanged = $insideLightDarkSupports
            ? false
            : $this->rewriteLightDarkFallbackEntries($entries, $targetOptions);
        $lightDarkSerializationChanged = $this->rewriteLightDarkAdvancedColorSerializationEntries($entries, $targetOptions);
        $alphaHexChanged = $this->rewriteAlphaHexFallbackEntries($entries, $targetOptions);
        $modernColorChanged = $this->rewriteModernColorFunctionEntries($entries, $targetOptions);
        $fontTargetChanged = $this->rewriteFontTargetFallbackEntries($entries, $targetOptions);
        $fontTypographyPrefixChanged = $this->rewriteFontTypographyPrefixEntries($entries, $targetOptions);
        $lengthTargetChanged = $this->rewriteLengthTargetFallbackEntries($entries, $targetOptions);
        $logicalBorderFallback = $this->rewriteLogicalBorderFallbackRule(
            $selectors,
            $entries,
            $targetOptions,
            $targetOptions['logicalBorderNeedsFallback'] ?? false,
            $targetOptions['logicalBorderShorthandNeedsFallback'] ?? false
        );
        if ($logicalBorderFallback !== null) {
            return $logicalBorderFallback . implode('', $supportRules);
        }
        $logicalSpacingFallback = $this->rewriteLogicalSpacingFallbackRule(
            $selectors,
            $entries,
            $targetOptions,
            $targetOptions['logicalSpacingInlineNeedsFallback'] ?? false,
            $targetOptions['logicalSpacingBlockNeedsFallback'] ?? false,
            $targetOptions['logicalSpacingShorthandNeedsFallback'] ?? false
        );
        if ($logicalSpacingFallback !== null) {
            return $logicalSpacingFallback . implode('', $supportRules);
        }
        $logicalInsetFallback = ($targetOptions['logicalInsetNeedsFallback'] ?? false)
            ? $this->rewriteLogicalInsetFallbackRule($selectors, $entries, $targetOptions)
            : null;
        if ($logicalInsetFallback !== null) {
            return $logicalInsetFallback . implode('', $supportRules);
        }
        $logicalTextAlignFallback = ($targetOptions['logicalTextAlignNeedsFallback'] ?? false)
            ? $this->rewriteLogicalTextAlignFallbackRule($selectors, $entries, $targetOptions)
            : null;
        if ($logicalTextAlignFallback !== null) {
            return $logicalTextAlignFallback . implode('', $supportRules);
        }
        $selectorVariants = $this->selectorPrefixVariants($selectors, $targetOptions);
        if ($transitionChanged || $displayFlexChanged || $flexChanged || $animationChanged || $colorSchemeChanged || $printColorAdjustChanged || $columnsChanged || $uiPrefixChanged || $cursorPrefixChanged || $boxSizingChanged || $objectFitChanged || $unicodeBidiChanged || $textCompatibilityPrefixChanged || $scrollSnapPrefixChanged || $breakPrefixChanged || $overflowShorthandChanged || $transformPrefixChanged || $positionStickyChanged || $backgroundSizeOriginChanged || $backgroundClipChanged || $clipPathChanged || $maskChanged || $filterChanged || $boxShadowChanged || $textShadowChanged || $textDecorationChanged || $textEmphasisChanged || $caretChanged || $listStyleChanged || $borderRadiusChanged || $borderImageChanged || $imageSetChanged || $gradientPrefixChanged || $sizingKeywordChanged || $logicalSizeFallbackChanged || $clampChanged || $colorChanged || $lightDarkChanged || $lightDarkSerializationChanged || $alphaHexChanged || $modernColorChanged || $fontTargetChanged || $fontTypographyPrefixChanged || $lengthTargetChanged || $selectorVariants !== null) {
            return $this->serializeRulesForSelectors($selectorVariants ?? [$selectors], $entries) . implode('', $supportRules);
        }

        return $selectors . '{' . $body . '}';
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function rewriteMediaRangePrelude(string $prelude, array $targetOptions): string
    {
        $lowerSimpleRanges = $targetOptions['mediaRangeSimpleNeedsFallback'] ?? false;
        $lowerIntervalRanges = $targetOptions['mediaRangeIntervalNeedsFallback'] ?? false;
        $usesXResolutionUnit = $targetOptions['mediaResolutionUsesXUnit'] ?? false;
        $usesDppxResolutionUnit = $targetOptions['mediaResolutionUsesDppxUnit'] ?? false;
        $needsResolutionPrefixes = ($targetOptions['mediaResolutionNeedsWebkitPrefix'] ?? false)
            || ($targetOptions['mediaResolutionNeedsMozPrefix'] ?? false);
        if ((!$lowerSimpleRanges && !$lowerIntervalRanges && !$needsResolutionPrefixes && !$usesXResolutionUnit && !$usesDppxResolutionUnit) || preg_match('/^@media\b/i', $prelude) !== 1) {
            return $prelude;
        }

        $condition = trim(substr($prelude, strlen('@media')));
        if ($condition === '') {
            return $prelude;
        }

        $parser = new MediaQueryParser();
        if ($needsResolutionPrefixes) {
            $condition = $this->prefixResolutionEqualityRangeQueries($condition, $targetOptions);
        }
        $condition = $parser->lowerRangeSyntaxList($condition, $lowerSimpleRanges, $lowerIntervalRanges);
        if ($usesDppxResolutionUnit) {
            $condition = $parser->useDppxResolutionUnitList($condition);
        }
        if ($needsResolutionPrefixes) {
            $condition = $this->prefixResolutionMediaQueries($condition, $targetOptions);
        }
        if ($usesXResolutionUnit) {
            $condition = $parser->useXResolutionUnitList($condition);
        }

        return '@media ' . $condition;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function prefixResolutionEqualityRangeQueries(string $queryList, array $targetOptions): string
    {
        $needsWebkit = $targetOptions['mediaResolutionNeedsWebkitPrefix'] ?? false;
        $needsMoz = $targetOptions['mediaResolutionNeedsMozPrefix'] ?? false;
        if (!$needsWebkit && !$needsMoz) {
            return $queryList;
        }

        $queries = [];
        $seen = [];
        foreach ($this->splitTopLevel($queryList, ',') as $query) {
            foreach ($this->resolutionEqualityRangeQueryVariants($query, $needsWebkit, $needsMoz) as $variant) {
                if (isset($seen[$variant])) {
                    continue;
                }

                $seen[$variant] = true;
                $queries[] = $variant;
            }
        }

        return implode(',', $queries);
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function prefixResolutionMediaQueries(string $queryList, array $targetOptions): string
    {
        $needsWebkit = $targetOptions['mediaResolutionNeedsWebkitPrefix'] ?? false;
        $needsMoz = $targetOptions['mediaResolutionNeedsMozPrefix'] ?? false;
        if (!$needsWebkit && !$needsMoz) {
            return $queryList;
        }

        $queries = [];
        $seen = [];
        foreach ($this->splitTopLevel($queryList, ',') as $query) {
            foreach ($this->resolutionMediaQueryVariants($query, $needsWebkit, $needsMoz) as $variant) {
                if (isset($seen[$variant])) {
                    continue;
                }

                $seen[$variant] = true;
                $queries[] = $variant;
            }
        }

        return implode(',', $queries);
    }

    /**
     * @return list<string>
     */
    private function resolutionEqualityRangeQueryVariants(string $query, bool $needsWebkit, bool $needsMoz): array
    {
        $matches = $this->matchResolutionEqualityRangeConditions($query);
        if ($matches === []) {
            return [$query];
        }

        $variants = [];
        if ($needsWebkit) {
            $variant = $this->replaceResolutionMediaQueryConditions($query, $matches, 'webkit');
            if ($variant !== null) {
                $variants[] = $variant;
            }
        }
        if ($needsMoz) {
            $variant = $this->replaceResolutionMediaQueryConditions($query, $matches, 'moz');
            if ($variant !== null) {
                $variants[] = $variant;
            }
        }
        $variants[] = $query;

        return $variants;
    }

    /**
     * @return list<string>
     */
    private function resolutionMediaQueryVariants(string $query, bool $needsWebkit, bool $needsMoz): array
    {
        $matches = $this->matchResolutionConditions($query);
        if ($matches === []) {
            return [$query];
        }

        $variants = [];
        if ($needsWebkit) {
            $variant = $this->replaceResolutionMediaQueryConditions($query, $matches, 'webkit');
            if ($variant !== null) {
                $variants[] = $variant;
            }
        }
        if ($needsMoz) {
            $variant = $this->replaceResolutionMediaQueryConditions($query, $matches, 'moz');
            if ($variant !== null) {
                $variants[] = $variant;
            }
        }
        $variants[] = $query;

        return $variants;
    }

    /**
     * @return list<array{offset:int,length:int,bound:string,value:string,negated:bool,operator?:string}>
     */
    private function matchResolutionEqualityRangeConditions(string $query): array
    {
        $resolution = '[+-]?(?:\d+|\d*\.\d+)(?:dppx|x|dpi|dpcm)';
        if (preg_match_all('/(?:(not)\s+)?\(\s*(?:resolution\s*=\s*(' . $resolution . ')|(' . $resolution . ')\s*=\s*resolution)\s*\)/i', $query, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
            $matches = [];
            foreach ($all as $match) {
                $negated = ($match[1][1] ?? -1) >= 0 && $match[1][0] !== '';
                $matches[] = [
                    'offset' => $match[0][1],
                    'length' => strlen($match[0][0]),
                    'bound' => '',
                    'operator' => '=',
                    'value' => trim($match[2][0] !== '' ? $match[2][0] : $match[3][0]),
                    'negated' => $negated,
                ];
            }

            return $matches;
        }

        return [];
    }

    /**
     * @return list<array{offset:int,length:int,bound:string,value:string,negated:bool,operator?:string}>
     */
    private function matchResolutionConditions(string $query): array
    {
        $resolution = '[+-]?(?:\d+|\d*\.\d+)(?:dppx|x|dpi|dpcm)';
        $matches = [];
        if (preg_match_all('/(?:(not)\s+)?\(\s*(?:resolution\s*(<=|>=|<|>)\s*(' . $resolution . ')|(' . $resolution . ')\s*(<=|>=|<|>)\s*resolution)\s*\)/i', $query, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
            foreach ($all as $match) {
                $negated = ($match[1][1] ?? -1) >= 0 && $match[1][0] !== '';
                $nameFirst = $match[2][0] !== '';
                $matches[] = [
                    'offset' => $match[0][1],
                    'length' => strlen($match[0][0]),
                    'bound' => '',
                    'operator' => $nameFirst ? $match[2][0] : $this->flipRangeOperator($match[5][0]),
                    'value' => trim($nameFirst ? $match[3][0] : $match[4][0]),
                    'negated' => $negated,
                ];
            }
        }

        if (preg_match_all('/(?:(not)\s+)?\((min|max)-resolution:([^)]+)\)/i', $query, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
            foreach ($all as $match) {
                $negated = ($match[1][1] ?? -1) >= 0 && $match[1][0] !== '';
                $matches[] = [
                    'offset' => $match[0][1],
                    'length' => strlen($match[0][0]),
                    'bound' => strtolower($match[2][0]),
                    'value' => trim($match[3][0]),
                    'negated' => $negated,
                ];
            }

            usort($matches, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);
        }

        return $matches;
    }

    /**
     * @param list<array{offset:int,length:int,bound:string,value:string,negated:bool,operator?:string}> $matches
     */
    private function replaceResolutionMediaQueryConditions(string $query, array $matches, string $vendor): ?string
    {
        $rewritten = $query;
        $changed = false;
        for ($i = count($matches) - 1; $i >= 0; $i--) {
            $match = $matches[$i];
            $ratio = $this->resolutionValueToDevicePixelRatio($match['value']);
            if ($ratio === null) {
                continue;
            }

            $rewritten = substr_replace(
                $rewritten,
                isset($match['operator'])
                    ? $this->resolutionPrefixRangeCondition($match['operator'], $ratio, $vendor, $match['negated'])
                    : $this->resolutionPrefixCondition($match['bound'], $ratio, $vendor, $match['negated']),
                $match['offset'],
                $match['length']
            );
            $changed = true;
        }

        return $changed ? $rewritten : null;
    }

    private function resolutionPrefixCondition(string $bound, string $ratio, string $vendor, bool $negated): string
    {
        if ($bound === '') {
            $feature = match ($vendor) {
                'webkit' => '-webkit-device-pixel-ratio',
                'moz' => '-moz-device-pixel-ratio',
                default => 'resolution',
            };
        } else {
            $feature = match ($vendor) {
                'webkit' => $bound === 'min' ? '-webkit-min-device-pixel-ratio' : '-webkit-max-device-pixel-ratio',
                'moz' => $bound === 'min' ? 'min--moz-device-pixel-ratio' : 'max--moz-device-pixel-ratio',
                default => $bound . '-resolution',
            };
        }
        $condition = '(' . $feature . ':' . $ratio . ')';

        return $negated ? 'not ' . $condition : $condition;
    }

    private function resolutionPrefixRangeCondition(string $operator, string $ratio, string $vendor, bool $negated): string
    {
        $feature = match ($vendor) {
            'webkit' => '-webkit-device-pixel-ratio',
            'moz' => '-moz-device-pixel-ratio',
            default => 'resolution',
        };
        $condition = '(' . $feature . $operator . $ratio . ')';

        return $negated ? 'not ' . $condition : $condition;
    }

    private function flipRangeOperator(string $operator): string
    {
        return match ($operator) {
            '<' => '>',
            '<=' => '>=',
            '>' => '<',
            '>=' => '<=',
            default => $operator,
        };
    }

    private function resolutionValueToDevicePixelRatio(string $value): ?string
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(dppx|x|dpi|dpcm)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $ratio = match (strtolower($matches[2])) {
            'dppx', 'x' => $number,
            'dpi' => $number / 96,
            'dpcm' => $number / (96 / 2.54),
            default => null,
        };
        if ($ratio === null) {
            return null;
        }

        return $this->formatDevicePixelRatioNumber($ratio);
    }

    private function formatDevicePixelRatioNumber(float $ratio): string
    {
        $formatted = rtrim(rtrim(sprintf('%.5f', round($ratio, 5)), '0'), '.');
        if ($formatted === '' || $formatted === '-0') {
            return '0';
        }
        if (str_starts_with($formatted, '0.')) {
            return substr($formatted, 1);
        }
        if (str_starts_with($formatted, '-0.')) {
            return '-' . substr($formatted, 2);
        }

        return $formatted;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function rewriteSupportsDeclarationPrefixPrelude(string $prelude, array $targetOptions): string
    {
        if (preg_match('/^@supports\b/i', $prelude) !== 1) {
            return $prelude;
        }

        $condition = trim(substr($prelude, strlen('@supports')));
        if ($condition === '') {
            return $prelude;
        }

        $prefixGroups = $this->supportsDeclarationPrefixGroups($targetOptions);
        if ($prefixGroups === []) {
            return $prelude;
        }

        $rewritten = $this->rewriteSupportsDeclarationPrefixes($condition, $condition, $prefixGroups);

        return '@supports ' . $rewritten;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return array<string, array<string, bool>>
     */
    private function supportsDeclarationPrefixGroups(array $targetOptions): array
    {
        return [
            'backdrop-filter' => [
                '-webkit-' => $targetOptions['backdropFilterNeedsWebkit'] ?? false,
            ],
            'filter' => [
                '-webkit-' => $targetOptions['filterNeedsWebkit'] ?? false,
            ],
            'transform' => [
                '-webkit-' => $targetOptions['transformNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['transformNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['transformNeedsMs'] ?? false,
                '-o-' => $targetOptions['transformNeedsO'] ?? false,
            ],
            'transform-origin' => [
                '-webkit-' => $targetOptions['transformNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['transformNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['transformNeedsMs'] ?? false,
                '-o-' => $targetOptions['transformNeedsO'] ?? false,
            ],
            'perspective' => [
                '-webkit-' => $targetOptions['perspectiveNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['perspectiveNeedsMoz'] ?? false,
            ],
            'perspective-origin' => [
                '-webkit-' => $targetOptions['perspectiveNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['perspectiveNeedsMoz'] ?? false,
            ],
            'transform-style' => [
                '-webkit-' => $targetOptions['transformNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['transformNeedsMoz'] ?? false,
            ],
            'backface-visibility' => [
                '-webkit-' => $targetOptions['backfaceVisibilityNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['backfaceVisibilityNeedsMoz'] ?? false,
            ],
            'box-sizing' => [
                '-webkit-' => $targetOptions['boxSizingNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['boxSizingNeedsMoz'] ?? false,
            ],
            'print-color-adjust' => [
                '-webkit-' => $targetOptions['printColorAdjustNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['printColorAdjustNeedsMoz'] ?? false,
            ],
            'user-select' => [
                '-webkit-' => $targetOptions['userSelectNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['userSelectNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['userSelectNeedsMs'] ?? false,
            ],
            'appearance' => [
                '-webkit-' => $targetOptions['appearanceNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['appearanceNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['appearanceNeedsMs'] ?? false,
            ],
            'clip-path' => [
                '-webkit-' => $targetOptions['clipPathNeedsWebkit'] ?? false,
            ],
            'text-size-adjust' => [
                '-webkit-' => $targetOptions['textSizeAdjustNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['textSizeAdjustNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['textSizeAdjustNeedsMs'] ?? false,
            ],
            'hyphens' => [
                '-webkit-' => $targetOptions['hyphensNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['hyphensNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['hyphensNeedsMs'] ?? false,
            ],
            'tab-size' => [
                '-moz-' => $targetOptions['tabSizeNeedsMoz'] ?? false,
                '-o-' => $targetOptions['tabSizeNeedsO'] ?? false,
            ],
            'text-align-last' => [
                '-moz-' => $targetOptions['textAlignLastNeedsMoz'] ?? false,
            ],
            'text-overflow' => [
                '-o-' => $targetOptions['textOverflowNeedsO'] ?? false,
            ],
            'text-decoration' => [
                '-webkit-' => $targetOptions['textDecorationNeedsWebkit'] ?? false,
            ],
            'text-decoration-line' => [
                '-webkit-' => $targetOptions['textDecorationLonghandNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['textDecorationNeedsMoz'] ?? false,
            ],
            'text-decoration-style' => [
                '-webkit-' => $targetOptions['textDecorationLonghandNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['textDecorationNeedsMoz'] ?? false,
            ],
            'text-decoration-color' => [
                '-webkit-' => $targetOptions['textDecorationLonghandNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['textDecorationNeedsMoz'] ?? false,
            ],
            'text-decoration-skip-ink' => [
                '-webkit-' => $targetOptions['textDecorationSkipInkNeedsWebkit'] ?? false,
            ],
            'box-decoration-break' => [
                '-webkit-' => $targetOptions['boxDecorationBreakNeedsWebkit'] ?? false,
            ],
            'font-feature-settings' => [
                '-webkit-' => $targetOptions['fontFeatureSettingsNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['fontFeatureSettingsNeedsMoz'] ?? false,
            ],
            'font-variant-ligatures' => [
                '-webkit-' => $targetOptions['fontFeatureSettingsNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['fontFeatureSettingsNeedsMoz'] ?? false,
            ],
            'font-language-override' => [
                '-webkit-' => $targetOptions['fontFeatureSettingsNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['fontFeatureSettingsNeedsMoz'] ?? false,
            ],
            'font-kerning' => [
                '-webkit-' => $targetOptions['fontKerningNeedsWebkit'] ?? false,
            ],
        ];
    }

    /**
     * @param array<string, array<string, bool>> $prefixGroups
     */
    private function rewriteSupportsDeclarationPrefixes(string $condition, string $rootCondition, array $prefixGroups): string
    {
        $logical = $this->splitSupportsConditionByLogicalOperator($condition);
        if ($logical !== null) {
            $operators = array_values(array_unique(array_map(
                static fn (array $item): string => $item['type'] === 'operator' ? $item['value'] : '',
                $logical
            )));
            $operators = array_values(array_filter($operators, static fn (string $operator): bool => $operator !== ''));

            if ($operators === ['or']) {
                $conditions = [];
                foreach ($logical as $item) {
                    if ($item['type'] === 'operator') {
                        continue;
                    }

                    if ($this->shouldDropSupportsDeclarationCondition($item['value'], $rootCondition, $prefixGroups)) {
                        continue;
                    }

                    $conditions[] = $this->rewriteSupportsDeclarationPrefixes($item['value'], $rootCondition, $prefixGroups);
                }

                return $conditions === [] ? $condition : implode(' or ', $conditions);
            }

            $parts = [];
            foreach ($logical as $item) {
                $parts[] = $item['type'] === 'operator'
                    ? $item['value']
                    : $this->rewriteSupportsDeclarationPrefixes($item['value'], $rootCondition, $prefixGroups);
            }

            return implode(' ', $parts);
        }

        $declaration = $this->parseSupportsDeclarationCondition($condition);
        if ($declaration === null) {
            return $condition;
        }

        $prefixInfo = $this->supportsDeclarationPrefixInfo($declaration['property'], $prefixGroups);
        if ($prefixInfo === null || $prefixInfo['prefix'] !== '') {
            return $condition;
        }

        $alternatives = [];
        foreach ($prefixGroups[$prefixInfo['base']] as $prefix => $needed) {
            if (!$needed) {
                continue;
            }

            $prefixedProperty = $prefix . $prefixInfo['base'];
            if ($this->supportsConditionHasDeclaration($rootCondition, $prefixedProperty, $declaration['value'])) {
                continue;
            }

            $alternatives[] = $this->supportsDeclarationCondition($prefixedProperty, $declaration['value']);
        }

        if ($alternatives === []) {
            return $this->supportsDeclarationCondition($declaration['property'], $declaration['value']);
        }

        $alternatives[] = $this->supportsDeclarationCondition($declaration['property'], $declaration['value']);

        return '(' . implode(' or ', $alternatives) . ')';
    }

    /**
     * @param array<string, array<string, bool>> $prefixGroups
     */
    private function shouldDropSupportsDeclarationCondition(string $condition, string $rootCondition, array $prefixGroups): bool
    {
        $declaration = $this->parseSupportsDeclarationCondition($condition);
        if ($declaration === null) {
            return false;
        }

        $prefixInfo = $this->supportsDeclarationPrefixInfo($declaration['property'], $prefixGroups);
        if ($prefixInfo === null || $prefixInfo['prefix'] === '') {
            return false;
        }

        return !($prefixGroups[$prefixInfo['base']][$prefixInfo['prefix']] ?? false)
            && $this->supportsConditionHasDeclaration($rootCondition, $prefixInfo['base'], $declaration['value']);
    }

    /**
     * @param array<string, array<string, bool>> $prefixGroups
     * @return array{base:string,prefix:string}|null
     */
    private function supportsDeclarationPrefixInfo(string $property, array $prefixGroups): ?array
    {
        foreach ($prefixGroups as $baseProperty => $prefixes) {
            if ($property === $baseProperty) {
                return ['base' => $baseProperty, 'prefix' => ''];
            }

            foreach ($prefixes as $prefix => $_needed) {
                if ($property === $prefix . $baseProperty) {
                    return ['base' => $baseProperty, 'prefix' => $prefix];
                }
            }
        }

        return null;
    }

    /**
     * @return array{property:string,value:string}|null
     */
    private function parseSupportsDeclarationCondition(string $condition): ?array
    {
        $condition = trim($condition);
        $inner = $this->unwrapSingleSupportsParentheses($condition);
        if ($inner !== null) {
            $condition = trim($inner);
        }

        if ($this->splitSupportsConditionByLogicalOperator($condition) !== null) {
            return null;
        }

        $colon = $this->findTopLevelColon($condition);
        if ($colon === null) {
            return null;
        }

        $property = strtolower(trim(substr($condition, 0, $colon)));
        $value = trim(substr($condition, $colon + 1));
        if ($property === '' || $value === '' || preg_match('/^[_a-zA-Z-][_a-zA-Z0-9-]*$/', $property) !== 1) {
            return null;
        }

        return [
            'property' => $property,
            'value' => $value,
        ];
    }

    private function supportsConditionHasDeclaration(string $condition, string $property, string $value): bool
    {
        foreach ($this->collectSupportsDeclarationConditions($condition) as $declaration) {
            if ($declaration['property'] === $property && $declaration['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{property:string,value:string}>
     */
    private function collectSupportsDeclarationConditions(string $condition): array
    {
        $declaration = $this->parseSupportsDeclarationCondition($condition);
        if ($declaration !== null) {
            return [$declaration];
        }

        $logical = $this->splitSupportsConditionByLogicalOperator($condition);
        if ($logical !== null) {
            $declarations = [];
            foreach ($logical as $item) {
                if ($item['type'] === 'condition') {
                    array_push($declarations, ...$this->collectSupportsDeclarationConditions($item['value']));
                }
            }

            return $declarations;
        }

        $inner = $this->unwrapSingleSupportsParentheses($condition);
        if ($inner !== null) {
            return $this->collectSupportsDeclarationConditions($inner);
        }

        return [];
    }

    private function supportsDeclarationCondition(string $property, string $value): string
    {
        return '(' . $property . ':' . $value . ')';
    }

    /**
     * @return list<array{type:string,value:string}>|null
     */
    private function splitSupportsConditionByLogicalOperator(string $condition): ?array
    {
        $items = [];
        $current = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
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
            if ($char === '[') {
                $bracketDepth++;
                $current .= $char;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $current .= $char;
                continue;
            }

            if ($parenDepth === 0 && $bracketDepth === 0 && (ctype_alpha($char) || $char === '_' || $char === '-')) {
                $start = $i;
                while ($i < $length && $this->isIdentifierChar($condition[$i])) {
                    $i++;
                }
                $identifier = substr($condition, $start, $i - $start);
                $lower = strtolower($identifier);
                $previous = $condition[$start - 1] ?? '';
                $next = $condition[$i] ?? '';
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

        $items[] = ['type' => 'condition', 'value' => trim($current)];

        return $items;
    }

    private function unwrapSingleSupportsParentheses(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value[0] !== '(') {
            return null;
        }

        [, $offset] = $this->readFunctionRaw($value, 0);
        if ($offset !== strlen($value) - 1) {
            return null;
        }

        return substr($value, 1, -1);
    }

    private function isAdvancedColorSupportsPrelude(string $prelude): bool
    {
        return preg_match('/^@supports\b/i', $prelude) === 1
            && preg_match('/:\s*(?:lab|lch|oklab|oklch|color)\(/i', $prelude) === 1
            && $this->supportsConditionAllowsFallbackSuppression(trim(substr($prelude, strlen('@supports'))));
    }

    private function isLightDarkSupportsPrelude(string $prelude): bool
    {
        return preg_match('/^@supports\b/i', $prelude) === 1
            && preg_match('/:\s*light-dark\(/i', $prelude) === 1
            && $this->supportsConditionAllowsFallbackSuppression(trim(substr($prelude, strlen('@supports'))));
    }

    private function supportsConditionAllowsFallbackSuppression(string $condition): bool
    {
        $condition = trim($condition);
        if ($condition === '') {
            return false;
        }

        $inner = $this->unwrapSingleSupportsParentheses($condition);
        if ($inner !== null) {
            return $this->supportsConditionAllowsFallbackSuppression($inner);
        }

        if (preg_match('/^not(?:\s|\()/i', $condition) === 1) {
            return false;
        }

        $logical = $this->splitSupportsConditionByLogicalOperator($condition);
        if ($logical === null) {
            return true;
        }

        foreach ($logical as $item) {
            if ($item['type'] === 'operator' && strtolower($item['value']) === 'or') {
                return false;
            }

            if ($item['type'] === 'condition' && !$this->supportsConditionAllowsFallbackSuppression($item['value'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $targets
     * @return array<string, bool>
     */
    private function targetOptions(array $targets): array
    {
        $browserTargets = isset($targets['browsers']) && is_array($targets['browsers'])
            ? $targets['browsers']
            : $targets;
        $lightDarkExcluded = $this->featureListContains($targets['exclude'] ?? [], 'light-dark');
        $mediaRangeIncluded = $this->featureListContains($targets['include'] ?? [], 'media-range-syntax')
            || $this->featureListContains($targets['include'] ?? [], 'media-queries');
        $mediaRangeExcluded = $this->featureListContains($targets['exclude'] ?? [], 'media-range-syntax')
            || $this->featureListContains($targets['exclude'] ?? [], 'media-queries');
        $mediaIntervalIncluded = $this->featureListContains($targets['include'] ?? [], 'media-interval-syntax')
            || $this->featureListContains($targets['include'] ?? [], 'media-queries');
        $mediaIntervalExcluded = $this->featureListContains($targets['exclude'] ?? [], 'media-interval-syntax')
            || $this->featureListContains($targets['exclude'] ?? [], 'media-queries');
        $logicalPropertiesIncluded = $this->featureListContains($targets['include'] ?? [], 'logical-properties');
        $logicalPropertiesExcluded = $this->featureListContains($targets['exclude'] ?? [], 'logical-properties');
        $normalized = [];
        foreach ($browserTargets as $browser => $version) {
            if (!is_scalar($version)) {
                continue;
            }
            $browserName = $this->normalizeBrowserTargetName((string) $browser);
            if ($browserName === null) {
                continue;
            }

            $normalized[$browserName] = $this->targetVersionCode($version);
        }

        $chrome = $normalized['chrome'] ?? null;
        $safari = $normalized['safari'] ?? null;
        $firefox = $normalized['firefox'] ?? null;
        $needsWebkitBoxShadow = $this->targetInRange($normalized, 'android', [2, 1], [3, 0])
            || $this->targetInRange($normalized, 'chrome', [4], [9])
            || $this->targetInRange($normalized, 'safari', [3, 1], [5])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [4, 2]);
        $needsMozBoxShadow = $this->targetInRange($normalized, 'firefox', [3, 5], [3, 6]);
        $supportsAdvancedColor = $this->targetAtLeast($normalized, 'chrome', [111])
            || $this->targetAtLeast($normalized, 'edge', [111])
            || $this->targetAtLeast($normalized, 'safari', [16]);
        $usesP3Fallback = $this->targetInRange($normalized, 'safari', [10], [14, 255, 255]);
        $needsSrgbFallback = ($chrome !== null && !$this->targetAtLeast($normalized, 'chrome', [111]))
            || ($safari !== null && !$this->targetAtLeast($normalized, 'safari', [10]))
            || ($chrome === null && $safari === null);
        $alphaHexNeedsRgbaFallback = isset($normalized['ie'])
            || $this->targetInRange($normalized, 'chrome', [0], [61])
            || $this->targetInRange($normalized, 'firefox', [0], [48])
            || $this->targetInRange($normalized, 'safari', [0], [9, 255, 255])
            || $this->targetInRange($normalized, 'ios_saf', [0], [9, 255, 255]);
        $modernColorNeedsLegacySyntax = $this->targetInRange($normalized, 'safari', [0], [11, 255, 255])
            || $this->targetInRange($normalized, 'ios_saf', [0], [11, 255, 255]);
        $modernColorNeedsCanonicalization = $modernColorNeedsLegacySyntax
            || $this->targetInRange($normalized, 'safari', [12], [13, 255, 255])
            || $this->targetInRange($normalized, 'ios_saf', [12], [13, 255, 255]);
        $transformNeedsWebkit = $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [4], [35])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
            || $this->targetInRange($normalized, 'opera', [15], [22])
            || $this->targetInRange($normalized, 'safari', [3, 1], [8]);
        $transformNeedsMoz = $this->targetInRange($normalized, 'firefox', [3, 5], [15]);
        $transformNeedsMs = $this->targetInRange($normalized, 'ie', [0], [9]);
        $transformNeedsO = $this->targetInRange($normalized, 'opera', [10, 5], [12]);
        $perspectiveNeedsWebkit = $this->targetInRange($normalized, 'android', [3], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [12], [35])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
            || $this->targetInRange($normalized, 'opera', [15], [22])
            || $this->targetInRange($normalized, 'safari', [4], [8]);
        $perspectiveNeedsMoz = $this->targetInRange($normalized, 'firefox', [10], [15]);
        $backfaceVisibilityNeedsWebkit = $this->targetInRange($normalized, 'android', [3], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [12], [35])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [15, 2])
            || $this->targetInRange($normalized, 'opera', [15], [22])
            || $this->targetInRange($normalized, 'safari', [4], [15, 2]);
        $backfaceVisibilityNeedsMoz = $this->targetInRange($normalized, 'firefox', [10], [15]);
        $sizingMinMaxNeedsWebkit = $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [22], [45])
            || $this->targetInRange($normalized, 'ios_saf', [7], [13, 4])
            || $this->targetInRange($normalized, 'opera', [15], [32])
            || $this->targetInRange($normalized, 'safari', [6], [10, 1])
            || $this->targetInRange($normalized, 'samsung', [0], [4]);
        $sizingMinMaxNeedsMoz = $this->targetInRange($normalized, 'firefox', [3], [65]);
        $sizingFitContentNeedsWebkit = $sizingMinMaxNeedsWebkit;
        $sizingFitContentNeedsMoz = $this->targetInRange($normalized, 'firefox', [3], [93]);
        $sizingStretchNeedsWebkit = $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [22], [137])
            || $this->targetInRange($normalized, 'edge', [79], [137])
            || $this->targetAtLeast($normalized, 'ios_saf', [7])
            || $this->targetAtLeast($normalized, 'opera', [15])
            || $this->targetAtLeast($normalized, 'safari', [6])
            || $this->targetAtLeast($normalized, 'samsung', [4]);
        $sizingStretchNeedsMoz = $this->targetAtLeast($normalized, 'firefox', [3]);
        $animationNeedsWebkit = $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [4], [42])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
            || $this->targetInRange($normalized, 'opera', [15], [29])
            || $this->targetInRange($normalized, 'safari', [4], [8]);
        $animationNeedsMoz = $this->targetInRange($normalized, 'firefox', [5], [15]);
        $animationNeedsO = $this->targetInRange($normalized, 'opera', [12], [12]);
        $anyPseudoNeedsWebkit = $this->targetInRange($normalized, 'chrome', [12], [87])
            || $this->targetInRange($normalized, 'edge', [79], [87])
            || $this->targetInRange($normalized, 'ios_saf', [5], [13])
            || $this->targetInRange($normalized, 'opera', [14], [73])
            || $this->targetInRange($normalized, 'safari', [5], [13])
            || $this->targetInRange($normalized, 'samsung', [1], [14]);
        $anyPseudoNeedsMoz = $this->targetInRange($normalized, 'firefox', [4], [78]);
        $selectorLangListNeedsFallback = isset($normalized['android'])
            || isset($normalized['chrome'])
            || isset($normalized['edge'])
            || isset($normalized['firefox'])
            || isset($normalized['ie'])
            || isset($normalized['opera'])
            || isset($normalized['samsung'])
            || $this->targetInRange($normalized, 'ios_saf', [0], [10, 2, 255])
            || $this->targetInRange($normalized, 'safari', [0], [10, 0, 255]);
        $selectorDirNeedsLangFallback = $this->targetInRange($normalized, 'android', [0], [24, 255, 255])
            || $this->targetInRange($normalized, 'chrome', [0], [119, 255, 255])
            || $this->targetInRange($normalized, 'edge', [0], [119, 255, 255])
            || $this->targetInRange($normalized, 'firefox', [0], [48, 255, 255])
            || $this->targetInRange($normalized, 'ios_saf', [0], [16, 3, 255])
            || $this->targetInRange($normalized, 'opera', [0], [105, 255, 255])
            || $this->targetInRange($normalized, 'safari', [0], [16, 3, 255])
            || isset($normalized['ie']);
        $fullscreenNeedsWebkit = $this->targetInRange($normalized, 'chrome', [15], [70])
            || $this->targetInRange($normalized, 'opera', [15], [63])
            || $this->targetInRange($normalized, 'safari', [5, 1], [16, 3])
            || $this->targetInRange($normalized, 'samsung', [4], [9, 2]);
        $fileSelectorButtonNeedsWebkit = $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [4], [88])
            || $this->targetInRange($normalized, 'edge', [79], [88])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [14])
            || $this->targetInRange($normalized, 'opera', [15], [74])
            || $this->targetInRange($normalized, 'safari', [3, 1], [14])
            || $this->targetInRange($normalized, 'samsung', [4], [14]);
        $autofillNeedsWebkit = $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [4], [109])
            || $this->targetInRange($normalized, 'edge', [79], [109])
            || $this->targetInRange($normalized, 'ios_saf', [3, 2], [14, 5])
            || $this->targetInRange($normalized, 'opera', [15], [95])
            || $this->targetInRange($normalized, 'safari', [3, 1], [14, 1])
            || $this->targetInRange($normalized, 'samsung', [4], [20]);
        $anyLinkNeedsWebkit = $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
            || $this->targetInRange($normalized, 'chrome', [15], [64])
            || $this->targetInRange($normalized, 'ios_saf', [6], [8, 1])
            || $this->targetInRange($normalized, 'opera', [15], [51])
            || $this->targetInRange($normalized, 'safari', [6, 1], [8])
            || $this->targetInRange($normalized, 'samsung', [5], [8, 2]);

        return [
            'boxShadowNeedsWebkit' => $needsWebkitBoxShadow,
            'boxShadowNeedsMoz' => $needsMozBoxShadow,
            'boxShadowDropLegacyPrefixes' => !$needsWebkitBoxShadow && !$needsMozBoxShadow && (
                $this->targetAtLeast($normalized, 'chrome', [95])
                || $this->targetAtLeast($normalized, 'safari', [16])
            ),
            'boxShadowSupportsAdvancedColor' => $supportsAdvancedColor,
            'boxShadowDropOverriddenFallbacks' => $supportsAdvancedColor,
            'advancedColorSupportsNative' => $supportsAdvancedColor,
            'advancedColorNeedsSrgbFallback' => $needsSrgbFallback,
            'advancedColorUsesP3Fallback' => $usesP3Fallback,
            'alphaHexNeedsRgbaFallback' => $alphaHexNeedsRgbaFallback,
            'modernColorNeedsLegacySyntax' => $modernColorNeedsLegacySyntax,
            'modernColorNeedsCanonicalization' => $modernColorNeedsCanonicalization,
            'filterNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [18], [52])
                || $this->targetInRange($normalized, 'ios_saf', [6], [9])
                || $this->targetInRange($normalized, 'opera', [15], [39])
                || $this->targetInRange($normalized, 'safari', [6], [9])
                || $this->targetInRange($normalized, 'samsung', [4], [6, 2]),
            'backdropFilterNeedsWebkit' => $this->targetInRange($normalized, 'edge', [17], [18])
                || $this->targetInRange($normalized, 'safari', [9], [17, 6])
                || $this->targetInRange($normalized, 'ios_saf', [9], [17, 6]),
            'printColorAdjustNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [17], [135])
                || $this->targetInRange($normalized, 'edge', [79], [135])
                || $this->targetInRange($normalized, 'ios_saf', [6], [15, 2])
                || $this->targetAtLeast($normalized, 'opera', [15])
                || $this->targetInRange($normalized, 'safari', [6], [15, 2])
                || $this->targetInRange($normalized, 'samsung', [4], [28]),
            'printColorAdjustNeedsMoz' => $this->targetInRange($normalized, 'firefox', [48], [96]),
            'columnsNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [49])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
                || $this->targetInRange($normalized, 'opera', [15], [36])
                || $this->targetInRange($normalized, 'safari', [3, 1], [8])
                || $this->targetInRange($normalized, 'samsung', [0], [4]),
            'columnsNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [51]),
            'displayFlexNeedsOldWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [20])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [6])
                || $this->targetInRange($normalized, 'safari', [3, 1], [6]),
            'displayFlexNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [28])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
                || $this->targetInRange($normalized, 'opera', [15], [16])
                || $this->targetInRange($normalized, 'safari', [3, 1], [8]),
            'displayFlexNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [21]),
            'displayFlexNeedsMs' => $this->targetInRange($normalized, 'ie', [10], [10]),
            'flexNeedsOldWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [20])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [6])
                || $this->targetInRange($normalized, 'safari', [3, 1], [6]),
            'flexNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [28])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
                || $this->targetInRange($normalized, 'opera', [15], [16])
                || $this->targetInRange($normalized, 'safari', [3, 1], [8]),
            'flexNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [21]),
            'flexNeedsMs' => $this->targetInRange($normalized, 'ie', [10], [10]),
            'animationNeedsWebkit' => $animationNeedsWebkit,
            'animationNeedsMoz' => $animationNeedsMoz,
            'animationNeedsO' => $animationNeedsO,
            'userSelectNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [53])
                || $this->targetAtLeast($normalized, 'ios_saf', [3])
                || $this->targetInRange($normalized, 'opera', [15], [40])
                || $this->targetAtLeast($normalized, 'safari', [3, 1])
                || $this->targetInRange($normalized, 'samsung', [4], [5]),
            'userSelectNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [68]),
            'userSelectNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || $this->targetAtLeast($normalized, 'ie', [10]),
            'appearanceNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [83])
                || $this->targetInRange($normalized, 'edge', [79], [83])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [15, 2])
                || $this->targetInRange($normalized, 'opera', [15], [72])
                || $this->targetInRange($normalized, 'safari', [3, 1], [15, 2])
                || $this->targetInRange($normalized, 'samsung', [4], [13]),
            'appearanceNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [79]),
            'appearanceNeedsMs' => isset($normalized['ie'])
                || $this->targetInRange($normalized, 'edge', [12], [18]),
            'cursorZoomNeedsWebkit' => $this->targetInRange($normalized, 'chrome', [4], [36])
                || $this->targetInRange($normalized, 'opera', [15], [23])
                || $this->targetInRange($normalized, 'safari', [3, 1], [8]),
            'cursorZoomNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [23]),
            'cursorGrabNeedsWebkit' => $this->targetInRange($normalized, 'chrome', [4], [67])
                || $this->targetInRange($normalized, 'opera', [15], [54])
                || $this->targetInRange($normalized, 'safari', [3, 1], [10]),
            'cursorGrabNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [25]),
            'boxSizingNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [3])
                || $this->targetInRange($normalized, 'chrome', [4], [9])
                || $this->targetInRange($normalized, 'ios_saf', [3], [4, 2])
                || $this->targetInRange($normalized, 'safari', [3, 1], [5]),
            'boxSizingNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [28]),
            'objectFitNeedsO' => $this->targetInRange($normalized, 'opera', [10, 6], [12, 1]),
            'writingModeNeedsWebkit' => $this->targetInRange($normalized, 'android', [3], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [8], [47])
                || $this->targetInRange($normalized, 'ios_saf', [5], [10, 3])
                || $this->targetInRange($normalized, 'opera', [15], [34])
                || $this->targetInRange($normalized, 'safari', [5, 1], [10, 1])
                || $this->targetInRange($normalized, 'samsung', [0], [4]),
            'writingModeNeedsMs' => $this->targetAtLeast($normalized, 'ie', [5, 5]),
            'textSizeAdjustNeedsWebkit' => $this->targetAtLeast($normalized, 'ios_saf', [5]),
            'textSizeAdjustNeedsMoz' => isset($normalized['firefox']),
            'textSizeAdjustNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || $this->targetAtLeast($normalized, 'ie', [10]),
            'unicodeBidiIsolateNeedsWebkit' => $this->targetInRange($normalized, 'chrome', [16], [47])
                || $this->targetInRange($normalized, 'ios_saf', [6], [10, 3])
                || $this->targetInRange($normalized, 'opera', [15], [34])
                || $this->targetInRange($normalized, 'safari', [6], [10, 1]),
            'unicodeBidiIsolateNeedsMoz' => $this->targetInRange($normalized, 'firefox', [10], [49]),
            'unicodeBidiPlaintextNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [6], [10, 3])
                || $this->targetInRange($normalized, 'safari', [6], [10, 1]),
            'unicodeBidiPlaintextNeedsMoz' => $this->targetInRange($normalized, 'firefox', [10], [49]),
            'unicodeBidiIsolateOverrideNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [7], [10, 3])
                || $this->targetInRange($normalized, 'safari', [7], [10, 1]),
            'unicodeBidiIsolateOverrideNeedsMoz' => $this->targetInRange($normalized, 'firefox', [17], [49]),
            'hyphensNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [4, 2], [16, 5])
                || $this->targetInRange($normalized, 'safari', [5, 1], [16, 5]),
            'hyphensNeedsMoz' => $this->targetInRange($normalized, 'firefox', [6], [42]),
            'hyphensNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || $this->targetAtLeast($normalized, 'ie', [10]),
            'tabSizeNeedsMoz' => $this->targetInRange($normalized, 'firefox', [4], [90]),
            'tabSizeNeedsO' => $this->targetInRange($normalized, 'opera', [10, 6], [12, 1]),
            'textAlignLastNeedsMoz' => $this->targetInRange($normalized, 'firefox', [12], [48]),
            'textOverflowNeedsO' => $this->targetInRange($normalized, 'opera', [9], [12]),
            'textOrientationNeedsWebkit' => $this->targetInRange($normalized, 'safari', [10, 1], [13, 1]),
            'touchActionNeedsMs' => $this->targetInRange($normalized, 'ie', [10], [10]),
            'textDecorationSkipInkNeedsWebkit' => $this->targetAtLeast($normalized, 'ios_saf', [8])
                || $this->targetInRange($normalized, 'safari', [7, 1], [12]),
            'boxDecorationBreakNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [22], [129])
                || $this->targetInRange($normalized, 'edge', [79], [129])
                || $this->targetAtLeast($normalized, 'ios_saf', [7])
                || $this->targetAtLeast($normalized, 'opera', [15])
                || $this->targetAtLeast($normalized, 'safari', [6, 1])
                || $this->targetAtLeast($normalized, 'samsung', [4]),
            'scrollSnapNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [9], [10, 3])
                || $this->targetInRange($normalized, 'safari', [9], [10, 1]),
            'scrollSnapNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || $this->targetAtLeast($normalized, 'ie', [10]),
            'breakNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [49])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [8, 1])
                || $this->targetInRange($normalized, 'opera', [15], [36])
                || $this->targetInRange($normalized, 'safari', [3, 1], [8])
                || $this->targetInRange($normalized, 'samsung', [0], [4]),
            'overflowShorthandNeedsLonghandFallback' => $this->targetsNeedFeatureFallback($normalized, [
                'android' => [68],
                'chrome' => [68],
                'edge' => [79],
                'firefox' => [61],
                'ios_saf' => [13, 4],
                'opera' => [48],
                'safari' => [13, 1],
                'samsung' => [10],
            ]),
            'transformNeedsWebkit' => $transformNeedsWebkit,
            'transformNeedsMoz' => $transformNeedsMoz,
            'transformNeedsMs' => $transformNeedsMs,
            'transformNeedsO' => $transformNeedsO,
            'perspectiveNeedsWebkit' => $perspectiveNeedsWebkit,
            'perspectiveNeedsMoz' => $perspectiveNeedsMoz,
            'backfaceVisibilityNeedsWebkit' => $backfaceVisibilityNeedsWebkit,
            'backfaceVisibilityNeedsMoz' => $backfaceVisibilityNeedsMoz,
            'sizingMinMaxNeedsWebkit' => $sizingMinMaxNeedsWebkit,
            'sizingMinMaxNeedsMoz' => $sizingMinMaxNeedsMoz,
            'sizingFitContentNeedsWebkit' => $sizingFitContentNeedsWebkit,
            'sizingFitContentNeedsMoz' => $sizingFitContentNeedsMoz,
            'sizingStretchNeedsWebkit' => $sizingStretchNeedsWebkit,
            'sizingStretchNeedsMoz' => $sizingStretchNeedsMoz,
            'maskNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [119])
                || $this->targetInRange($normalized, 'edge', [79], [119])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [15, 2])
                || $this->targetInRange($normalized, 'opera', [15], [105])
                || $this->targetInRange($normalized, 'safari', [4], [15, 2])
                || $this->targetInRange($normalized, 'samsung', [4], [24]),
            'stickyNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [6], [12, 2])
                || $this->targetInRange($normalized, 'safari', [6, 1], [12, 1]),
            'backgroundSizeOriginNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [2, 3]),
            'backgroundSizeOriginNeedsMoz' => $this->targetInRange($normalized, 'firefox', [0], [3, 6]),
            'backgroundSizeOriginNeedsO' => $this->targetInRange($normalized, 'opera', [0], [10]),
            'backgroundClipNeedsWebkit' => $this->targetInRange($normalized, 'android', [4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [119])
                || $this->targetInRange($normalized, 'edge', [79], [119])
                || $this->targetInRange($normalized, 'ios_saf', [4], [13])
                || $this->targetInRange($normalized, 'opera', [15], [105])
                || $this->targetInRange($normalized, 'safari', [3, 2], [13])
                || $this->targetInRange($normalized, 'samsung', [4], [24]),
            'backgroundClipNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [14]),
            'clipPathNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [24], [54])
                || $this->targetInRange($normalized, 'ios_saf', [7], [9])
                || $this->targetInRange($normalized, 'opera', [15], [41])
                || $this->targetInRange($normalized, 'safari', [7], [9])
                || $this->targetInRange($normalized, 'samsung', [4], [5]),
            'textDecorationNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [8], [26])
                || $this->targetInRange($normalized, 'safari', [8], [26]),
            'textDecorationLonghandNeedsWebkit' => $this->targetInRange($normalized, 'ios_saf', [8], [12])
                || $this->targetInRange($normalized, 'safari', [8], [12]),
            'textDecorationNeedsMoz' => $this->targetInRange($normalized, 'firefox', [6], [35]),
            'textDecorationThicknessShorthandNeedsFallback' => $this->targetsNeedTextDecorationThicknessShorthandFallback($normalized),
            'textDecorationThicknessPercentNeedsFallback' => $this->targetsNeedTextDecorationThicknessPercentFallback($normalized),
            'textEmphasisNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [25], [98])
                || $this->targetInRange($normalized, 'edge', [79], [98])
                || $this->targetInRange($normalized, 'opera', [15], [85])
                || $this->targetInRange($normalized, 'safari', [6, 1], [7])
                || $this->targetInRange($normalized, 'samsung', [4], [17]),
            'gradientNeedsOldWebkit' => ($chrome !== null && $chrome <= $this->encodedTargetVersion(8))
                || ($safari !== null && $safari < $this->encodedTargetVersion(5, 1)),
            'gradientNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [25])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [6])
                || $this->targetInRange($normalized, 'safari', [4], [6]),
            'gradientNeedsMoz' => $this->targetInRange($normalized, 'firefox', [3, 6], [15]),
            'gradientNeedsO' => $this->targetInRange($normalized, 'opera', [11], [12]),
            'imageSetNeedsWebkit' => !isset($normalized['ie']) && (
                $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [21], [112])
                || $this->targetInRange($normalized, 'edge', [79], [112])
                || $this->targetInRange($normalized, 'ios_saf', [6], [9, 3])
                || $this->targetInRange($normalized, 'opera', [15], [98])
                || $this->targetInRange($normalized, 'safari', [6], [9, 1])
                || $this->targetInRange($normalized, 'samsung', [4], [22])
            ),
            'imageSetNeedsUrlFallback' => isset($normalized['ie']),
            'borderRadiusNeedsWebkit' => $this->targetInRange($normalized, 'android', [0], [2, 1])
                || $this->targetInRange($normalized, 'chrome', [0], [4])
                || $this->targetInRange($normalized, 'ios_saf', [0], [3])
                || $this->targetInRange($normalized, 'safari', [3, 1], [4]),
            'borderRadiusNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [3, 6]),
            'borderRadiusNeedsLogicalFallback' => $this->targetInRange($normalized, 'safari', [0], [12]),
            'borderRadiusDropLegacyPrefixes' => (
                $this->targetAtLeast($normalized, 'chrome', [5])
                || $this->targetAtLeast($normalized, 'safari', [5])
                || $this->targetAtLeast($normalized, 'firefox', [4])
            ),
            'borderImageNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [14])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [5])
                || $this->targetInRange($normalized, 'safari', [3, 1], [5, 1]),
            'borderImageNeedsMoz' => $this->targetInRange($normalized, 'firefox', [3, 5], [14]),
            'borderImageNeedsO' => $this->targetInRange($normalized, 'opera', [11], [12, 1]),
            'clampNeedsMaxMinFallback' => $this->targetInRange($normalized, 'safari', [0], [12]),
            'logicalBorderNeedsFallback' => $logicalPropertiesIncluded || (!$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [0], [68, 255, 255])
                || $this->targetInRange($normalized, 'chrome', [0], [68, 255, 255])
                || $this->targetInRange($normalized, 'edge', [0], [78, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [0], [40, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [12, 1, 255])
                || $this->targetInRange($normalized, 'opera', [0], [47, 255, 255])
                || $this->targetInRange($normalized, 'safari', [0], [12, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [0], [9, 255, 255])
                || isset($normalized['ie'])
            )),
            'logicalBorderShorthandNeedsFallback' => !$logicalPropertiesIncluded && !$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'chrome', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'edge', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [0], [65, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [14, 4, 255])
                || $this->targetInRange($normalized, 'opera', [0], [61, 255, 255])
                || $this->targetInRange($normalized, 'safari', [0], [14, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [0], [13, 255, 255])
                || isset($normalized['ie'])
            ),
            'logicalSpacingInlineNeedsFallback' => $logicalPropertiesIncluded || (!$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [68, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [3], [40, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [12, 0, 255])
                || $this->targetInRange($normalized, 'opera', [15], [55, 255, 255])
                || $this->targetInRange($normalized, 'safari', [3, 1], [12, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [4], [9, 255, 255])
            )),
            'logicalSpacingBlockNeedsFallback' => $logicalPropertiesIncluded || (!$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [68, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [3, 2], [12, 0, 255])
                || $this->targetInRange($normalized, 'opera', [15], [55, 255, 255])
                || $this->targetInRange($normalized, 'safari', [3, 1], [12, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [4], [9, 255, 255])
            )),
            'logicalSpacingShorthandNeedsFallback' => !$logicalPropertiesIncluded && !$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'chrome', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'edge', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [0], [65, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [14, 4, 255])
                || $this->targetInRange($normalized, 'opera', [0], [61, 255, 255])
                || $this->targetInRange($normalized, 'safari', [0], [14, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [0], [13, 255, 255])
                || isset($normalized['ie'])
            ),
            'logicalInsetNeedsFallback' => $logicalPropertiesIncluded || (!$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'chrome', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'edge', [0], [86, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [0], [62, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [14, 4, 255])
                || $this->targetInRange($normalized, 'opera', [0], [61, 255, 255])
                || $this->targetInRange($normalized, 'safari', [0], [14, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [0], [13, 255, 255])
                || isset($normalized['ie'])
            )),
            'logicalSizeNeedsFallback' => $logicalPropertiesIncluded || (!$logicalPropertiesExcluded && (
                $this->targetInRange($normalized, 'android', [0], [56, 255, 255])
                || $this->targetInRange($normalized, 'chrome', [0], [56, 255, 255])
                || $this->targetInRange($normalized, 'edge', [0], [78, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [0], [40, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [12, 1, 255])
                || $this->targetInRange($normalized, 'opera', [0], [42, 255, 255])
                || $this->targetInRange($normalized, 'safari', [0], [12, 0, 255])
                || $this->targetInRange($normalized, 'samsung', [0], [6, 255, 255])
                || isset($normalized['ie'])
            )),
            'logicalTextAlignNeedsFallback' => $logicalPropertiesIncluded || (!$logicalPropertiesExcluded && $this->targetsNeedFeatureFallback($normalized, [
                'android' => [37],
                'chrome' => [18],
                'edge' => [79],
                'firefox' => [4],
                'ios_saf' => [2],
                'opera' => [14],
                'safari' => [3, 1],
                'samsung' => [1],
            ])),
            'anyPseudoNeedsWebkit' => $anyPseudoNeedsWebkit,
            'anyPseudoNeedsMoz' => $anyPseudoNeedsMoz,
            'selectorListNotNeedsFallback' => $this->targetInRange($normalized, 'android', [0], [144, 255, 255])
                || $this->targetInRange($normalized, 'chrome', [0], [87, 255, 255])
                || $this->targetInRange($normalized, 'edge', [0], [87, 255, 255])
                || $this->targetInRange($normalized, 'firefox', [0], [83, 255, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [8, 255, 255])
                || $this->targetInRange($normalized, 'opera', [0], [74, 255, 255])
                || $this->targetInRange($normalized, 'safari', [0], [8, 255, 255])
                || $this->targetInRange($normalized, 'samsung', [0], [14, 255, 255]),
            'isSelectorSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [145],
                'chrome' => [88],
                'edge' => [88],
                'firefox' => [78],
                'ios_saf' => [14],
                'opera' => [75],
                'safari' => [14],
                'samsung' => [15],
            ]),
            'focusWithinNeedsSelectorListFallback' => $this->targetsNeedFeatureFallback($normalized, [
                'android' => [145],
                'chrome' => [60],
                'edge' => [79],
                'firefox' => [52],
                'ios_saf' => [10, 2],
                'opera' => [47],
                'safari' => [10, 1],
                'samsung' => [8, 2],
            ]),
            'focusVisibleNeedsSelectorListFallback' => $this->targetsNeedFeatureFallback($normalized, [
                'android' => [145],
                'chrome' => [86],
                'edge' => [86],
                'firefox' => [85],
                'ios_saf' => [15, 4],
                'opera' => [72],
                'safari' => [15, 4],
                'samsung' => [14],
            ]),
            'placeholderNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [56])
                || $this->targetInRange($normalized, 'ios_saf', [4, 3], [10])
                || $this->targetInRange($normalized, 'opera', [15], [43])
                || $this->targetInRange($normalized, 'safari', [5], [10])
                || $this->targetInRange($normalized, 'samsung', [4], [6, 2]),
            'placeholderNeedsMoz' => $this->targetInRange($normalized, 'firefox', [18], [50]),
            'placeholderNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || $this->targetAtLeast($normalized, 'ie', [10]),
            'selectionNeedsMoz' => $this->targetInRange($normalized, 'firefox', [2], [61]),
            'placeholderShownNeedsMoz' => $this->targetInRange($normalized, 'firefox', [4], [50]),
            'placeholderShownNeedsMs' => $this->targetAtLeast($normalized, 'ie', [10]),
            'fullscreenNeedsWebkit' => $fullscreenNeedsWebkit,
            'fullscreenNeedsMoz' => $this->targetInRange($normalized, 'firefox', [10], [63]),
            'fullscreenNeedsMs' => $this->targetAtLeast($normalized, 'ie', [11]),
            'backdropNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [32], [36])
                || $this->targetInRange($normalized, 'opera', [19], [23]),
            'backdropNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || isset($normalized['ie']),
            'fileSelectorButtonNeedsWebkit' => $fileSelectorButtonNeedsWebkit,
            'fileSelectorButtonNeedsMs' => $this->targetInRange($normalized, 'edge', [12], [18])
                || $this->targetAtLeast($normalized, 'ie', [10]),
            'autofillNeedsWebkit' => $autofillNeedsWebkit,
            'readWriteNeedsMoz' => $this->targetInRange($normalized, 'firefox', [3], [77]),
            'anyLinkNeedsWebkit' => $anyLinkNeedsWebkit,
            'anyLinkNeedsMoz' => $this->targetInRange($normalized, 'firefox', [3], [49]),
            'selectorLangListNeedsFallback' => $selectorLangListNeedsFallback,
            'selectorDirNeedsLangFallback' => $selectorDirNeedsLangFallback,
            'selectorDirFallbackNeedsIsWrapper' => $selectorLangListNeedsFallback,
            'lightDarkNeedsFallback' => !$lightDarkExcluded && (
                ($chrome !== null && !$this->targetAtLeast($normalized, 'chrome', [123]))
                || (isset($normalized['edge']) && !$this->targetAtLeast($normalized, 'edge', [123]))
                || ($firefox !== null && !$this->targetAtLeast($normalized, 'firefox', [120]))
                || (isset($normalized['opera']) && !$this->targetAtLeast($normalized, 'opera', [82]))
                || ($safari !== null && !$this->targetAtLeast($normalized, 'safari', [17, 5]))
                || (isset($normalized['ios_saf']) && !$this->targetAtLeast($normalized, 'ios_saf', [17, 5]))
                || (isset($normalized['samsung']) && !$this->targetAtLeast($normalized, 'samsung', [27]))
                || (isset($normalized['android']) && !$this->targetAtLeast($normalized, 'android', [123]))
                || isset($normalized['ie'])
            ),
            'lightDarkNormalizeAdvancedColor' => !$lightDarkExcluded && $firefox !== null,
            'mediaRangeSimpleNeedsFallback' => $mediaRangeIncluded || (!$mediaRangeExcluded && (
                $this->targetInRange($normalized, 'chrome', [0], [103])
                || $this->targetInRange($normalized, 'edge', [0], [103])
                || $this->targetInRange($normalized, 'firefox', [0], [62])
                || $this->targetInRange($normalized, 'safari', [0], [16, 3, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [16, 3, 255])
                || $this->targetInRange($normalized, 'android', [0], [103])
                || $this->targetInRange($normalized, 'opera', [0], [70])
                || $this->targetInRange($normalized, 'samsung', [0], [19])
            )),
            'mediaRangeIntervalNeedsFallback' => $mediaIntervalIncluded || (!$mediaIntervalExcluded && (
                $this->targetInRange($normalized, 'chrome', [0], [103])
                || $this->targetInRange($normalized, 'edge', [0], [103])
                || $this->targetInRange($normalized, 'firefox', [0], [101])
                || $this->targetInRange($normalized, 'safari', [0], [16, 3, 255])
                || $this->targetInRange($normalized, 'ios_saf', [0], [16, 3, 255])
                || $this->targetInRange($normalized, 'android', [0], [103])
                || $this->targetInRange($normalized, 'opera', [0], [70])
                || $this->targetInRange($normalized, 'samsung', [0], [19])
            )),
            'mediaResolutionNeedsWebkitPrefix' => $this->targetInRange($normalized, 'android', [2, 3], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [28])
                || $this->targetInRange($normalized, 'safari', [4], [15, 6])
                || $this->targetInRange($normalized, 'ios_saf', [4], [15, 6]),
            'mediaResolutionNeedsMozPrefix' => $this->targetInRange($normalized, 'firefox', [3, 5], [15]),
            'mediaResolutionUsesXUnit' => $this->targetsSupportXResolutionUnit($normalized),
            'mediaResolutionUsesDppxUnit' => $normalized !== [] && !$this->targetsSupportXResolutionUnit($normalized),
            'fontCqwSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [105],
                'chrome' => [105],
                'edge' => [105],
                'firefox' => [110],
                'ios_saf' => [16],
                'opera' => [72],
                'safari' => [16],
                'samsung' => [20],
            ]),
            'fontSystemUiNeedsFallback' => $this->targetInRange($normalized, 'safari', [0], [8])
                || $this->targetInRange($normalized, 'firefox', [0], [91]),
            'fontSystemUiSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [145],
                'chrome' => [56],
                'edge' => [79],
                'firefox' => [92],
                'ios_saf' => [11],
                'opera' => [43],
                'safari' => [11],
                'samsung' => [6],
            ]),
            'fontXxxLargeSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [79],
                'chrome' => [79],
                'edge' => [79],
                'firefox' => [79],
                'ios_saf' => [16, 4],
                'opera' => [57],
                'safari' => [16, 4],
                'samsung' => [12],
            ]),
            'fontVariableWeightSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [62],
                'chrome' => [62],
                'edge' => [17],
                'firefox' => [61],
                'ios_saf' => [11],
                'opera' => [46],
                'safari' => [11],
                'samsung' => [8],
            ]),
            'fontObliqueAngleSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [62],
                'chrome' => [62],
                'edge' => [79],
                'firefox' => [61],
                'ios_saf' => [11, 3],
                'opera' => [46],
                'safari' => [11, 1],
                'samsung' => [8],
            ]),
            'fontFeatureSettingsNeedsWebkit' => $this->targetInRange($normalized, 'android', [4, 4], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [16], [47])
                || $this->targetInRange($normalized, 'opera', [15], [34])
                || $this->targetInRange($normalized, 'samsung', [0], [4]),
            'fontFeatureSettingsNeedsMoz' => $this->targetInRange($normalized, 'firefox', [4], [33]),
            'fontKerningNeedsWebkit' => $this->targetInRange($normalized, 'android', [0], [4, 4])
                || $this->targetInRange($normalized, 'chrome', [29], [32])
                || $this->targetInRange($normalized, 'ios_saf', [8], [11, 3])
                || $this->targetInRange($normalized, 'opera', [16], [19])
                || $this->targetInRange($normalized, 'safari', [7], [9]),
            'animationTimelineShorthandNeedsFallback' => $this->targetsNeedFeatureFallback($normalized, [
                'android' => [115],
                'chrome' => [115],
                'edge' => [115],
                'opera' => [77],
                'samsung' => [23],
            ]),
            'lengthMinMaxFunctionSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [79],
                'chrome' => [79],
                'edge' => [79],
                'firefox' => [79],
                'ios_saf' => [11, 3],
                'opera' => [57],
                'safari' => [11, 1],
                'samsung' => [12],
            ]),
            'lengthContainerQueryUnitsSupported' => $this->targetsAllAtLeast($normalized, [
                'android' => [105],
                'chrome' => [105],
                'edge' => [105],
                'firefox' => [110],
                'ios_saf' => [16],
                'opera' => [72],
                'safari' => [16],
                'samsung' => [20],
            ]),
            'keyframesNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 4, 3])
                || $this->targetInRange($normalized, 'chrome', [4], [42])
                || $this->targetInRange($normalized, 'ios_saf', [3], [8, 0])
                || $this->targetInRange($normalized, 'opera', [15], [29])
                || $this->targetInRange($normalized, 'safari', [4], [8]),
            'keyframesNeedsMoz' => $this->targetInRange($normalized, 'firefox', [5], [15]),
            'keyframesNeedsO' => $this->targetInRange($normalized, 'opera', [12], [12]),
            'transitionNeedsWebkit' => $this->targetInRange($normalized, 'android', [2, 1], [4, 2])
                || $this->targetInRange($normalized, 'chrome', [4], [25])
                || $this->targetInRange($normalized, 'ios_saf', [3], [6])
                || $this->targetInRange($normalized, 'safari', [3, 1], [6]),
            'transitionNeedsMoz' => $this->targetInRange($normalized, 'firefox', [4], [15]),
            'transitionNeedsO' => $this->targetInRange($normalized, 'opera', [10], [12]),
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLengthTargetFallbackEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if (!$entry['important']
                && isset(self::LENGTH_TARGET_FALLBACK_PROPERTIES[$entry['property']])
                && $this->lengthValueSupportedForTargetFallback($entry['value'], $targetOptions)
            ) {
                $previous = $this->lastSamePropertyEntryIndex($rewritten, $entry['property']);
                if ($previous !== null
                    && !$rewritten[$previous]['important']
                    && !$this->containsCustomPropertyReference($rewritten[$previous]['value'])
                ) {
                    array_splice($rewritten, $previous, 1);
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;

        return true;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function lastSamePropertyEntryIndex(array $entries, string $property): ?int
    {
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function lengthValueSupportedForTargetFallback(string $value, array $targetOptions): bool
    {
        if ($this->containsCustomPropertyReference($value)) {
            return false;
        }

        $hasTargetFeature = false;
        if (preg_match('/\b(?:min|max)\(/i', $value) === 1) {
            if (!($targetOptions['lengthMinMaxFunctionSupported'] ?? false)) {
                return false;
            }
            $hasTargetFeature = true;
        }

        if (preg_match('/(?<![a-z_-])cq(?:w|h|i|b|min|max)\b/i', $value) === 1) {
            if (!($targetOptions['lengthContainerQueryUnitsSupported'] ?? false)) {
                return false;
            }
            $hasTargetFeature = true;
        }

        return $hasTargetFeature;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteFontTargetFallbackEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if (!$entry['important'] && ($targetOptions['fontSystemUiNeedsFallback'] ?? false)) {
                $fallback = $this->fontSystemUiFallbackValue($entry['property'], $entry['value']);
                if ($fallback !== null && $fallback !== $entry['value']) {
                    $entry = $this->entryWithValue($entry, $fallback);
                    $changed = true;
                }
            }

            if (!$entry['important'] && $this->fontDeclarationSupportsTargetFallback($entry['property'], $entry['value'], $targetOptions)) {
                $last = array_key_last($rewritten);
                if ($last !== null
                    && $rewritten[$last]['property'] === $entry['property']
                    && !$rewritten[$last]['important']
                    && !$this->containsCustomPropertyReference($rewritten[$last]['value'])
                ) {
                    array_pop($rewritten);
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;

        return true;
    }

    private function fontSystemUiFallbackValue(string $property, string $value): ?string
    {
        if ($property === 'font-family') {
            return $this->expandSystemUiFontFamilyList($value);
        }

        if ($property !== 'font') {
            return null;
        }

        $familyOffset = $this->fontShorthandFamilyOffset($value);
        if ($familyOffset === null) {
            return null;
        }

        $prefix = rtrim(substr($value, 0, $familyOffset));
        $family = substr($value, $familyOffset);
        $expanded = $this->expandSystemUiFontFamilyList($family);
        if ($expanded === trim($family)) {
            return null;
        }

        return $prefix . ' ' . $expanded;
    }

    private function expandSystemUiFontFamilyList(string $value): string
    {
        $families = $this->splitTopLevel($value, ',');
        $fallbacks = [
            '-apple-system',
            'BlinkMacSystemFont',
            'Segoe UI',
            'Roboto',
            'Noto Sans',
            'Ubuntu',
            'Cantarell',
            'Helvetica Neue',
        ];
        $expanded = [];
        $seen = [];
        $changed = false;

        foreach ($families as $family) {
            $family = trim($family);
            if ($family === '') {
                continue;
            }

            $key = strtolower($family);
            if (isset($seen[$key])) {
                $changed = true;
                continue;
            }

            $expanded[] = $family;
            $seen[$key] = true;

            if ($key !== 'system-ui') {
                continue;
            }

            foreach ($fallbacks as $fallback) {
                $fallbackKey = strtolower($fallback);
                if (isset($seen[$fallbackKey])) {
                    continue;
                }

                $expanded[] = $fallback;
                $seen[$fallbackKey] = true;
                $changed = true;
            }
        }

        return $changed ? implode(',', $expanded) : trim($value);
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function fontDeclarationSupportsTargetFallback(string $property, string $value, array $targetOptions): bool
    {
        if ($this->containsCustomPropertyReference($value)) {
            return false;
        }

        return match ($property) {
            'font-size' => $this->fontSizeValueSupportedForTargetFallback($value, $targetOptions),
            'font-weight' => $this->fontWeightValueSupportedForTargetFallback($value, $targetOptions),
            'font-family' => $this->fontFamilyValueSupportedForTargetFallback($value, $targetOptions),
            'font-style' => $this->fontStyleValueSupportedForTargetFallback($value, $targetOptions),
            'font' => $this->fontShorthandValueSupportedForTargetFallback($value, $targetOptions),
            default => false,
        };
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function fontSizeValueSupportedForTargetFallback(string $value, array $targetOptions): bool
    {
        $lower = strtolower(trim($value));
        if ($lower === 'xxx-large') {
            return $targetOptions['fontXxxLargeSupported'] ?? false;
        }

        if (preg_match('/cq(?:w|h|i|b|min|max)\b/i', $value) === 1) {
            return $targetOptions['fontCqwSupported'] ?? false;
        }

        return false;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function fontWeightValueSupportedForTargetFallback(string $value, array $targetOptions): bool
    {
        if (!($targetOptions['fontVariableWeightSupported'] ?? false)) {
            return false;
        }

        $value = trim($value);
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) !== 1) {
            return false;
        }

        $weight = (float) $value;

        return fmod($weight, 100.0) !== 0.0;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function fontFamilyValueSupportedForTargetFallback(string $value, array $targetOptions): bool
    {
        return ($targetOptions['fontSystemUiSupported'] ?? false) && $this->fontFamilyUsesSystemUi($value);
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function fontStyleValueSupportedForTargetFallback(string $value, array $targetOptions): bool
    {
        return ($targetOptions['fontObliqueAngleSupported'] ?? false)
            && preg_match('/^oblique\s+[+-]?(?:\d+|\d*\.\d+)(?:deg|grad|rad|turn)?$/i', trim($value)) === 1;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function fontShorthandValueSupportedForTargetFallback(string $value, array $targetOptions): bool
    {
        $shorthand = $this->fontShorthandTargetInfo($value);
        if ($shorthand === null) {
            return false;
        }

        $prefix = trim(substr($value, 0, $shorthand['familyOffset']));
        $family = substr($value, $shorthand['familyOffset']);
        $tokens = $this->splitWhitespaceTopLevel($prefix);
        $sizeToken = '';
        foreach ($tokens as $token) {
            $parts = $this->splitTopLevel($token, '/');
            $candidate = $parts[0] ?? $token;
            if ($this->isFontTargetSizeToken($candidate)) {
                $sizeToken = $candidate;
            }
        }

        $hasTargetFeature = false;
        if ($shorthand['hasObliqueAngle']) {
            if (!($targetOptions['fontObliqueAngleSupported'] ?? false)) {
                return false;
            }
            $hasTargetFeature = true;
        }

        if ($sizeToken !== '') {
            $lowerSize = strtolower(trim($sizeToken));
            if ($lowerSize === 'xxx-large') {
                if (!($targetOptions['fontXxxLargeSupported'] ?? false)) {
                    return false;
                }
                $hasTargetFeature = true;
            } elseif (preg_match('/cq(?:w|h|i|b|min|max)\b/i', $sizeToken) === 1) {
                if (!($targetOptions['fontCqwSupported'] ?? false)) {
                    return false;
                }
                $hasTargetFeature = true;
            }
        }

        if ($this->fontFamilyUsesSystemUi($family)) {
            if (!($targetOptions['fontSystemUiSupported'] ?? false)) {
                return false;
            }
            $hasTargetFeature = true;
        }

        return $hasTargetFeature;
    }

    private function fontFamilyUsesSystemUi(string $value): bool
    {
        foreach ($this->splitTopLevel($value, ',') as $family) {
            if (strcasecmp(trim($family), 'system-ui') === 0) {
                return true;
            }
        }

        return false;
    }

    private function fontShorthandFamilyOffset(string $value): ?int
    {
        return $this->fontShorthandTargetInfo($value)['familyOffset'] ?? null;
    }

    /**
     * @return array{familyOffset:int,hasObliqueAngle:bool}|null
     */
    private function fontShorthandTargetInfo(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevelWithOffsets($value);
        $hasObliqueAngle = false;
        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            $parts = $this->splitTopLevel($token['token'], '/');
            if (count($parts) <= 2 && $this->isFontTargetSizeToken($parts[0] ?? '')) {
                return [
                    'familyOffset' => $this->skipWhitespace($value, $token['end']),
                    'hasObliqueAngle' => $hasObliqueAngle,
                ];
            }

            if (strcasecmp(trim($token['token']), 'oblique') === 0
                && isset($tokens[$index + 1])
                && $this->isFontTargetObliqueAngleToken($tokens[$index + 1]['token'])
            ) {
                $hasObliqueAngle = true;
                $index++;
                continue;
            }

            if ($this->isFontTargetPreSizeToken($token['token'])) {
                continue;
            }

            return null;
        }

        return null;
    }

    private function isFontTargetObliqueAngleToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:deg|grad|rad|turn)?$/i', trim($token)) === 1;
    }

    /**
     * @return list<array{token:string,start:int,end:int}>
     */
    private function splitWhitespaceTopLevelWithOffsets(string $value): array
    {
        $tokens = [];
        $token = '';
        $tokenStart = null;
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($tokenStart === null) {
                    $tokenStart = $i;
                }
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
                if ($token !== '' && $tokenStart !== null) {
                    $tokens[] = ['token' => $token, 'start' => $tokenStart, 'end' => $i];
                    $token = '';
                    $tokenStart = null;
                }
                continue;
            }

            if ($tokenStart === null) {
                $tokenStart = $i;
            }
            $token .= $char;
        }

        if ($token !== '' && $tokenStart !== null) {
            $tokens[] = ['token' => $token, 'start' => $tokenStart, 'end' => $length];
        }

        return $tokens;
    }

    private function skipWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function isFontTargetPreSizeToken(string $token): bool
    {
        $lower = strtolower(trim($token));
        if ($lower === '' || $lower === 'normal') {
            return true;
        }

        if (in_array($lower, ['italic', 'oblique', 'small-caps', 'bold', 'bolder', 'lighter'], true)) {
            return true;
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $lower) === 1) {
            return true;
        }

        return $this->isFontTargetStretchToken($lower);
    }

    private function isFontTargetSizeToken(string $token): bool
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

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:deg|grad|rad|turn)$/i', $lower) === 1) {
            return false;
        }

        return preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)(?:[a-z]+|%))$/i', $lower) === 1
            || preg_match('/^(?:calc|min|max|clamp)\(/i', $lower) === 1;
    }

    private function isFontTargetStretchToken(string $token): bool
    {
        return in_array(strtolower(trim($token)), [
            'ultra-condensed',
            'extra-condensed',
            'condensed',
            'semi-condensed',
            'semi-expanded',
            'expanded',
            'extra-expanded',
            'ultra-expanded',
        ], true) || preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)%)$/', trim($token)) === 1;
    }

    private function normalizeBrowserTargetName(string $browser): ?string
    {
        return match (strtolower(str_replace('-', '_', $browser))) {
            'android', 'chrome', 'edge', 'firefox', 'ie', 'opera', 'safari', 'samsung' => strtolower(str_replace('-', '_', $browser)),
            'and_chr' => 'chrome',
            'and_ff' => 'firefox',
            'ios', 'ios_saf', 'ios_safari' => 'ios_saf',
            'op_mob' => 'opera',
            default => null,
        };
    }

    private function targetVersionCode(int|float|string $version): int
    {
        if (is_int($version) && $version >= 65536) {
            return $version;
        }
        if (is_float($version) && $version >= 65536 && floor($version) === $version) {
            return (int) $version;
        }

        if (is_string($version) && ctype_digit($version) && (int) $version >= 65536) {
            return (int) $version;
        }

        $text = trim((string) $version);
        if ($text === '') {
            return 0;
        }

        $parts = preg_split('/[._-]/', $text) ?: [];
        $major = isset($parts[0]) ? max(0, (int) $parts[0]) : 0;
        $minor = isset($parts[1]) ? max(0, (int) $parts[1]) : 0;
        $patch = isset($parts[2]) ? max(0, (int) $parts[2]) : 0;

        return $this->encodedTargetVersion($major, $minor, $patch);
    }

    /**
     * @param array<string, int> $targets
     * @param array{0:int,1?:int,2?:int} $min
     * @param array{0:int,1?:int,2?:int} $max
     */
    private function targetInRange(array $targets, string $browser, array $min, array $max): bool
    {
        if (!isset($targets[$browser])) {
            return false;
        }

        $version = $targets[$browser];

        return $version >= $this->encodedTargetVersion($min[0], $min[1] ?? 0, $min[2] ?? 0)
            && $version <= $this->encodedTargetVersion($max[0], $max[1] ?? 0, $max[2] ?? 0);
    }

    /**
     * @param array<string, int> $targets
     * @param array{0:int,1?:int,2?:int} $minimum
     */
    private function targetAtLeast(array $targets, string $browser, array $minimum): bool
    {
        return isset($targets[$browser])
            && $targets[$browser] >= $this->encodedTargetVersion($minimum[0], $minimum[1] ?? 0, $minimum[2] ?? 0);
    }

    /**
     * @param array<string, int> $targets
     * @param array<string, array{0:int,1?:int,2?:int}> $minimums
     */
    private function targetsAllAtLeast(array $targets, array $minimums): bool
    {
        if ($targets === []) {
            return false;
        }

        foreach ($targets as $browser => $version) {
            if (!isset($minimums[$browser])) {
                return false;
            }

            $minimum = $minimums[$browser];
            if ($version < $this->encodedTargetVersion($minimum[0], $minimum[1] ?? 0, $minimum[2] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, int> $targets
     */
    private function targetsNeedTextDecorationThicknessPercentFallback(array $targets): bool
    {
        return $this->targetsNeedFeatureFallback($targets, [
            'android' => [87],
            'chrome' => [87],
            'edge' => [87],
            'firefox' => [79],
            'ios_saf' => [17, 4],
            'opera' => [62],
            'safari' => [17, 4],
            'samsung' => [14],
        ]);
    }

    /**
     * @param array<string, int> $targets
     */
    private function targetsNeedTextDecorationThicknessShorthandFallback(array $targets): bool
    {
        return $this->targetsNeedFeatureFallback($targets, [
            'android' => [87],
            'chrome' => [87],
            'edge' => [87],
            'firefox' => [79],
            'ios_saf' => [26, 2],
            'opera' => [62],
            'safari' => [26, 2],
            'samsung' => [14],
        ]);
    }

    /**
     * @param array<string, int> $targets
     * @param array<string, array{0:int,1?:int,2?:int}> $minimums
     */
    private function targetsNeedFeatureFallback(array $targets, array $minimums): bool
    {
        if ($targets === []) {
            return false;
        }

        foreach ($targets as $browser => $version) {
            if ($browser === 'ie') {
                return true;
            }

            $minimum = $minimums[$browser] ?? null;
            if ($minimum === null || $version < $this->encodedTargetVersion($minimum[0], $minimum[1] ?? 0, $minimum[2] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, int> $targets
     */
    private function targetsSupportXResolutionUnit(array $targets): bool
    {
        if ($targets === []) {
            return false;
        }

        foreach ($targets as $browser => $version) {
            $minimum = match ($browser) {
                'android', 'chrome' => [68],
                'edge' => [79],
                'firefox' => [62],
                'opera' => [48],
                'safari', 'ios_saf' => [16],
                'samsung' => [10],
                default => null,
            };
            if ($minimum === null || $version < $this->encodedTargetVersion($minimum[0], $minimum[1] ?? 0, $minimum[2] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    private function encodedTargetVersion(int $major, int $minor = 0, int $patch = 0): int
    {
        return (($major & 0xff) << 16) | (($minor & 0xff) << 8) | ($patch & 0xff);
    }

    private function featureListContains(mixed $features, string $feature): bool
    {
        $feature = $this->normalizeFeatureName($feature);
        if (is_string($features)) {
            return $this->normalizeFeatureName($features) === $feature;
        }

        if (!is_array($features)) {
            return false;
        }

        foreach ($features as $name => $enabled) {
            if (is_string($name) && $this->normalizeFeatureName($name) === $feature) {
                return (bool) $enabled;
            }

            if (is_string($enabled) && $this->normalizeFeatureName($enabled) === $feature) {
                return true;
            }
        }

        return false;
    }

    private function normalizeFeatureName(string $feature): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($feature)) ?? strtolower($feature);
    }

    /**
     * @return list<array{property:string,name:string,value:string,important:bool}>|null
     */
    private function parseDeclarations(string $body): ?array
    {
        $entries = [];
        foreach ($this->splitTopLevel($body, ';') as $part) {
            if ($part === '') {
                continue;
            }

            $colon = $this->findTopLevelColon($part);
            if ($colon === null) {
                return null;
            }

            $name = substr($part, 0, $colon);
            $value = substr($part, $colon + 1);
            if ($name === '' || $value === '') {
                return null;
            }

            [$value, $important] = $this->splitImportantFlag($value);
            $entries[] = [
                'property' => strtolower($name),
                'name' => $name,
                'value' => $value,
                'important' => $important,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function serializeDeclarations(array $entries): string
    {
        return implode(';', array_map(
            static fn (array $entry): string => $entry['name'] . ':' . $entry['value'] . ($entry['important'] ? '!important' : ''),
            $entries
        ));
    }

    /**
     * @param list<string> $selectors
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function serializeRulesForSelectors(array $selectors, array $entries): string
    {
        $declarations = $this->serializeDeclarations($entries);
        $rules = '';
        foreach ($selectors as $selector) {
            $rules .= $selector . '{' . $declarations . '}';
        }

        return $rules;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function selectorsWithTargetPrefixVariants(string $selectors, array $targetOptions): array
    {
        return $this->selectorPrefixVariants($selectors, $targetOptions) ?? [$selectors];
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>|null
     */
    private function selectorPrefixVariants(string $selectors, array $targetOptions): ?array
    {
        $selectorList = $this->splitTopLevel($selectors, ',');
        if (count($selectorList) !== 1) {
            $grouped = $this->selectorListAutofillGroupVariants($selectorList, $targetOptions);
            if ($grouped !== null) {
                return $grouped;
            }

            $unsupportedPseudoFallback = $this->selectorListUnsupportedPseudoVariants($selectorList, $targetOptions);
            if ($unsupportedPseudoFallback !== null) {
                return $unsupportedPseudoFallback;
            }

            $variants = [];
            foreach ($selectorList as $selector) {
                array_push($variants, ...($this->singleSelectorPrefixVariants($selector, $targetOptions) ?? [$selector]));
            }
            $variants = array_values(array_unique($variants));

            return $variants === $selectorList ? null : $variants;
        }

        return $this->singleSelectorPrefixVariants($selectors, $targetOptions);
    }

    /**
     * @param list<string> $selectorList
     * @param array<string, bool> $targetOptions
     * @return list<string>|null
     */
    private function selectorListAutofillGroupVariants(array $selectorList, array $targetOptions): ?array
    {
        if (
            !($targetOptions['autofillNeedsWebkit'] ?? false)
            || ($targetOptions['selectorListNotNeedsFallback'] ?? false)
        ) {
            return null;
        }

        $prefixed = [];
        $changed = false;
        foreach ($selectorList as $selector) {
            $rewritten = preg_replace($this->pseudoClassPattern('autofill'), ':-webkit-autofill', $selector) ?? $selector;
            $prefixed[] = $rewritten;
            $changed = $changed || $rewritten !== $selector;
        }

        if (!$changed) {
            return null;
        }

        return [
            ':-webkit-any(' . implode(',', $prefixed) . ')',
            ':is(' . implode(',', $selectorList) . ')',
        ];
    }

    /**
     * @param list<string> $selectorList
     * @param array<string, bool> $targetOptions
     * @return list<string>|null
     */
    private function selectorListUnsupportedPseudoVariants(array $selectorList, array $targetOptions): ?array
    {
        $hasUnsupportedPseudo = false;
        foreach ($selectorList as $selector) {
            if ($this->selectorContainsUnsupportedTargetPseudo($selector, $targetOptions)) {
                $hasUnsupportedPseudo = true;
                break;
            }
        }

        if (!$hasUnsupportedPseudo) {
            return null;
        }

        if (($targetOptions['isSelectorSupported'] ?? false) && $this->selectorListCanUseForgivingIsWrapper($selectorList)) {
            return [':is(' . implode(',', $selectorList) . ')'];
        }

        $variants = [];
        foreach ($selectorList as $selector) {
            array_push($variants, ...($this->singleSelectorPrefixVariants($selector, $targetOptions) ?? [$selector]));
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function selectorContainsUnsupportedTargetPseudo(string $selector, array $targetOptions): bool
    {
        return (($targetOptions['focusVisibleNeedsSelectorListFallback'] ?? false)
                && preg_match($this->pseudoClassPattern('focus-visible'), $selector) === 1)
            || (($targetOptions['focusWithinNeedsSelectorListFallback'] ?? false)
                && preg_match($this->pseudoClassPattern('focus-within'), $selector) === 1);
    }

    /**
     * @param list<string> $selectorList
     */
    private function selectorListCanUseForgivingIsWrapper(array $selectorList): bool
    {
        $specificity = null;
        foreach ($selectorList as $selector) {
            if ($this->selectorContainsPseudoElement($selector)) {
                return false;
            }

            $selectorSpecificity = SelectorSpecificity::packed($selector);
            if ($specificity === null) {
                $specificity = $selectorSpecificity;
                continue;
            }

            if ($selectorSpecificity !== $specificity) {
                return false;
            }
        }

        return true;
    }

    private function selectorContainsPseudoElement(string $selector): bool
    {
        return preg_match('/::[-_a-z0-9]+|:(?:before|after|first-line|first-letter)(?![-_a-z0-9])/i', $selector) === 1;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>|null
     */
    private function singleSelectorPrefixVariants(string $selectors, array $targetOptions): ?array
    {
        $variants = [$selectors];
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewriteIsSelectorVariants($selector, $targetOptions));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewriteLangSelectorVariants($selector, $targetOptions));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewriteDirSelectorVariants($selector, $targetOptions));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewriteNotSelectorVariants($selector, $targetOptions));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoElementVariants($selector, 'selection', [
            ...(($targetOptions['selectionNeedsMoz'] ?? false) ? ['::-moz-selection'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoClassVariants($selector, 'placeholder-shown', [
            ...(($targetOptions['placeholderShownNeedsMoz'] ?? false) ? [':-moz-placeholder-shown'] : []),
            ...(($targetOptions['placeholderShownNeedsMs'] ?? false) ? [':-ms-placeholder-shown'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoClassVariants($selector, 'fullscreen', [
            ...(($targetOptions['fullscreenNeedsWebkit'] ?? false) ? [':-webkit-full-screen'] : []),
            ...(($targetOptions['fullscreenNeedsMoz'] ?? false) ? [':-moz-full-screen'] : []),
            ...(($targetOptions['fullscreenNeedsMs'] ?? false) ? [':-ms-fullscreen'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoElementVariants($selector, 'backdrop', [
            ...(($targetOptions['backdropNeedsWebkit'] ?? false) ? ['::-webkit-backdrop'] : []),
            ...(($targetOptions['backdropNeedsMs'] ?? false) ? ['::-ms-backdrop'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoElementVariants($selector, 'file-selector-button', [
            ...(($targetOptions['fileSelectorButtonNeedsWebkit'] ?? false) ? ['::-webkit-file-upload-button'] : []),
            ...(($targetOptions['fileSelectorButtonNeedsMs'] ?? false) ? ['::-ms-browse'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoClassVariants($selector, 'autofill', [
            ...(($targetOptions['autofillNeedsWebkit'] ?? false) ? [':-webkit-autofill'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoClassVariants($selector, 'read-only', [
            ...(($targetOptions['readWriteNeedsMoz'] ?? false) ? [':-moz-read-only'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoClassVariants($selector, 'read-write', [
            ...(($targetOptions['readWriteNeedsMoz'] ?? false) ? [':-moz-read-write'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePseudoClassVariants($selector, 'any-link', [
            ...(($targetOptions['anyLinkNeedsWebkit'] ?? false) ? [':-webkit-any-link'] : []),
            ...(($targetOptions['anyLinkNeedsMoz'] ?? false) ? [':-moz-any-link'] : []),
        ]));
        $variants = $this->expandSelectorVariants($variants, fn (string $selector): array => $this->rewritePlaceholderSelectorVariants($selector, $targetOptions));
        $variants = array_values(array_unique($variants));

        return $variants === [$selectors] ? null : $variants;
    }

    /**
     * @param list<string> $variants
     * @return list<string>
     */
    private function expandSelectorVariants(array $variants, callable $rewriter): array
    {
        $expanded = [];
        foreach ($variants as $variant) {
            foreach ($rewriter($variant) as $rewritten) {
                if (!in_array($rewritten, $expanded, true)) {
                    $expanded[] = $rewritten;
                }
            }
        }

        return $expanded;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function rewriteIsSelectorVariants(string $selector, array $targetOptions): array
    {
        if (!(($targetOptions['anyPseudoNeedsWebkit'] ?? false) || ($targetOptions['anyPseudoNeedsMoz'] ?? false))
            || !$this->selectorContainsSimpleListFunction($selector, 'is', true)
        ) {
            return [$selector];
        }

        $variants = [];
        if ($targetOptions['anyPseudoNeedsWebkit'] ?? false) {
            $variants[] = $this->replaceSimpleListFunction($selector, 'is', '-webkit-any', true);
        }
        if ($targetOptions['anyPseudoNeedsMoz'] ?? false) {
            $variants[] = $this->replaceSimpleListFunction($selector, 'is', '-moz-any', true);
        }
        $variants[] = $selector;

        return array_values(array_unique($variants));
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function rewriteNotSelectorVariants(string $selector, array $targetOptions): array
    {
        if (!($targetOptions['selectorListNotNeedsFallback'] ?? false)
            || !$this->selectorContainsSimpleListFunction($selector, 'not', true)
        ) {
            return [$selector];
        }

        $variants = [];
        if ($targetOptions['anyPseudoNeedsWebkit'] ?? false) {
            $variants[] = $this->replaceSimpleListFunction($selector, 'not', 'not:-webkit-any', true);
        }
        if ($targetOptions['anyPseudoNeedsMoz'] ?? false) {
            $variants[] = $this->replaceSimpleListFunction($selector, 'not', 'not:-moz-any', true);
        }
        $variants[] = $this->replaceSimpleListFunction($selector, 'not', 'not:is', true);

        return array_values(array_unique($variants));
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function rewritePlaceholderSelectorVariants(string $selector, array $targetOptions): array
    {
        if (!$this->selectorHasUnprefixedPlaceholderPseudo($selector)) {
            return [$selector];
        }

        $variants = [];
        if ($targetOptions['placeholderNeedsWebkit'] ?? false) {
            $variants[] = $this->replacePlaceholderPseudo($selector, '::-webkit-input-placeholder');
        }
        if ($targetOptions['placeholderNeedsMoz'] ?? false) {
            $variants[] = $this->replacePlaceholderPseudo($selector, '::-moz-placeholder');
        }
        if ($targetOptions['placeholderNeedsMs'] ?? false) {
            $variants[] = $this->replacePlaceholderPseudo($selector, '::-ms-input-placeholder');
        }
        $variants[] = $selector;

        return array_values(array_unique($variants));
    }

    private function selectorHasUnprefixedPlaceholderPseudo(string $selector): bool
    {
        return preg_match('/::placeholder(?![-_a-z0-9])/i', $selector) === 1;
    }

    private function replacePlaceholderPseudo(string $selector, string $replacement): string
    {
        return preg_replace('/::placeholder(?![-_a-z0-9])/i', $replacement, $selector) ?? $selector;
    }

    /**
     * @param list<string> $replacements
     * @return list<string>
     */
    private function rewritePseudoClassVariants(string $selector, string $name, array $replacements): array
    {
        if ($replacements === [] || preg_match($this->pseudoClassPattern($name), $selector) !== 1) {
            return [$selector];
        }

        $variants = [];
        foreach ($replacements as $replacement) {
            $variants[] = preg_replace($this->pseudoClassPattern($name), $replacement, $selector) ?? $selector;
        }
        $variants[] = $selector;

        return array_values(array_unique($variants));
    }

    /**
     * @param list<string> $replacements
     * @return list<string>
     */
    private function rewritePseudoElementVariants(string $selector, string $name, array $replacements): array
    {
        if ($replacements === [] || preg_match($this->pseudoElementPattern($name), $selector) !== 1) {
            return [$selector];
        }

        $variants = [];
        foreach ($replacements as $replacement) {
            $variants[] = preg_replace($this->pseudoElementPattern($name), $replacement, $selector) ?? $selector;
        }
        $variants[] = $selector;

        return array_values(array_unique($variants));
    }

    private function pseudoClassPattern(string $name): string
    {
        return '/:' . preg_quote($name, '/') . '(?![-_a-z0-9])/i';
    }

    private function pseudoElementPattern(string $name): string
    {
        return '/::' . preg_quote($name, '/') . '(?![-_a-z0-9])/i';
    }

    private function selectorContainsSimpleListFunction(string $selector, string $name, bool $requiresList): bool
    {
        if (preg_match_all('/:' . preg_quote($name, '/') . '\(([^()]*)\)/i', $selector, $matches) !== false) {
            foreach ($matches[1] as $argument) {
                if ((!$requiresList || count($this->splitTopLevel($argument, ',')) > 1)
                    && $this->selectorArgumentCanUseAny($argument)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function replaceSimpleListFunction(string $selector, string $name, string $replacement, bool $requiresList): string
    {
        return preg_replace_callback(
            '/:' . preg_quote($name, '/') . '\(([^()]*)\)/i',
            function (array $matches) use ($replacement, $requiresList): string {
                $argument = $this->normalizeSelectorListArgument($matches[1]);
                if (($requiresList && count($this->splitTopLevel($argument, ',')) < 2)
                    || !$this->selectorArgumentCanUseAny($argument)
                ) {
                    return $matches[0];
                }

                return match ($replacement) {
                    'not:-webkit-any' => ':not(:-webkit-any(' . $argument . '))',
                    'not:-moz-any' => ':not(:-moz-any(' . $argument . '))',
                    'not:is' => ':not(:is(' . $argument . '))',
                    default => ':' . $replacement . '(' . $argument . ')',
                };
            },
            $selector
        ) ?? $selector;
    }

    private function selectorArgumentCanUseAny(string $argument): bool
    {
        foreach ($this->splitTopLevel($argument, ',') as $part) {
            if ($part === '' || $this->selectorHasTopLevelCombinator($part)) {
                return false;
            }
        }

        return true;
    }

    private function selectorHasTopLevelCombinator(string $selector): bool
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
            } elseif (($char === ' ' || $char === '>' || $char === '+' || $char === '~') && $parenDepth === 0 && $bracketDepth === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSelectorListArgument(string $argument): string
    {
        return implode(',', array_map('trim', $this->splitTopLevel($argument, ',')));
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function rewriteLangSelectorVariants(string $selector, array $targetOptions): array
    {
        if (!($targetOptions['selectorLangListNeedsFallback'] ?? false) || !$this->selectorHasMultiLangPseudo($selector)) {
            return [$selector];
        }

        $variants = [];
        if ($targetOptions['anyPseudoNeedsWebkit'] ?? false) {
            $variants[] = $this->replaceLangListPseudo($selector, '-webkit-any');
        }
        if ($targetOptions['anyPseudoNeedsMoz'] ?? false) {
            $variants[] = $this->replaceLangListPseudo($selector, '-moz-any');
        }
        $variants[] = $this->replaceLangListPseudo($selector, 'is');

        return array_values(array_unique($variants));
    }

    private function selectorHasMultiLangPseudo(string $selector): bool
    {
        if (preg_match_all('/:lang\(([^()]*)\)/i', $selector, $matches) === false) {
            return false;
        }

        foreach ($matches[1] as $argument) {
            if (count($this->splitTopLevel($argument, ',')) > 1) {
                return true;
            }
        }

        return false;
    }

    private function replaceLangListPseudo(string $selector, string $function): string
    {
        return preg_replace_callback(
            '/:lang\(([^()]*)\)/i',
            function (array $matches) use ($function): string {
                $pseudoList = $this->langPseudoListFromArgument($matches[1]);
                if ($pseudoList === null) {
                    return $matches[0];
                }

                return ':' . $function . '(' . $pseudoList . ')';
            },
            $selector
        ) ?? $selector;
    }

    private function langPseudoListFromArgument(string $argument): ?string
    {
        $languages = array_values(array_filter(
            array_map('trim', $this->splitTopLevel($argument, ',')),
            static fn (string $language): bool => $language !== ''
        ));
        if (count($languages) < 2) {
            return null;
        }

        return implode(',', array_map(
            static fn (string $language): string => ':lang(' . trim($language, '\'"') . ')',
            $languages
        ));
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function rewriteDirSelectorVariants(string $selector, array $targetOptions): array
    {
        if (!($targetOptions['selectorDirNeedsLangFallback'] ?? false)
            || preg_match('/:dir\(\s*(?:rtl|ltr)\s*\)/i', $selector) !== 1
        ) {
            return [$selector];
        }

        $variants = [];
        if ($targetOptions['anyPseudoNeedsWebkit'] ?? false) {
            $variants[] = $this->replaceDirPseudo($selector, 'webkit', $targetOptions);
        }
        if ($targetOptions['anyPseudoNeedsMoz'] ?? false) {
            $variants[] = $this->replaceDirPseudo($selector, 'moz', $targetOptions);
        }
        $variants[] = $this->replaceDirPseudo(
            $selector,
            (($targetOptions['anyPseudoNeedsWebkit'] ?? false) || ($targetOptions['anyPseudoNeedsMoz'] ?? false)) ? 'modern-any' : 'modern',
            $targetOptions
        );

        return array_values(array_unique($variants));
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function replaceDirPseudo(string $selector, string $mode, array $targetOptions): string
    {
        $rewritten = preg_replace_callback(
            '/:dir\(\s*(rtl|ltr)\s*\)/i',
            function (array $matches) use ($mode, $targetOptions): string {
                return $this->dirPseudoReplacement(strtolower($matches[1]), $mode, $targetOptions);
            },
            $selector
        ) ?? $selector;

        if ($mode === 'modern' && !($targetOptions['selectorDirFallbackNeedsIsWrapper'] ?? false)) {
            return $this->collapseSingleIsLangSelector($rewritten);
        }

        return $rewritten;
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function dirPseudoReplacement(string $direction, string $mode, array $targetOptions): string
    {
        $pseudoList = $this->rtlLangPseudoList();
        $langList = implode(',', self::RTL_LANGS);

        if ($mode === 'webkit') {
            return $direction === 'rtl'
                ? ':-webkit-any(' . $pseudoList . ')'
                : ':not(:-webkit-any(' . $pseudoList . '))';
        }
        if ($mode === 'moz') {
            return $direction === 'rtl'
                ? ':-moz-any(' . $pseudoList . ')'
                : ':not(:-moz-any(' . $pseudoList . '))';
        }
        if ($mode === 'modern-any' || ($targetOptions['selectorDirFallbackNeedsIsWrapper'] ?? false)) {
            return $direction === 'rtl'
                ? ':is(' . $pseudoList . ')'
                : ($mode === 'modern-any' ? ':not(:is(' . $pseudoList . '))' : ':not(' . $pseudoList . ')');
        }

        return $direction === 'rtl'
            ? ':lang(' . $langList . ')'
            : ':not(:lang(' . $langList . '))';
    }

    private function rtlLangPseudoList(): string
    {
        return implode(',', array_map(
            static fn (string $language): string => ':lang(' . $language . ')',
            self::RTL_LANGS
        ));
    }

    private function collapseSingleIsLangSelector(string $selector): string
    {
        return preg_replace('/:is\((:lang\([^)]*\))\)/i', '$1', $selector) ?? $selector;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function rewriteInlineTransitionEntries(array &$entries, string $direction): bool
    {
        $changed = false;
        foreach ($entries as &$entry) {
            if ($entry['important']) {
                continue;
            }
            if ($entry['property'] === 'transition-property') {
                [$value, $entryChanged] = $this->rewriteTransitionPropertyListForDirection($entry['value'], $direction);
                $entry['value'] = $value;
                $changed = $changed || $entryChanged;
                continue;
            }
            if ($entry['property'] === 'transition') {
                [$value, $entryChanged] = $this->rewriteTransitionShorthandForDirection($entry['value'], $direction);
                $entry['value'] = $value;
                $changed = $changed || $entryChanged;
            }
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteBorderRadiusPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $needsWebkit = $targetOptions['borderRadiusNeedsWebkit'] ?? false;
        $needsMoz = $targetOptions['borderRadiusNeedsMoz'] ?? false;
        $dropLegacy = $targetOptions['borderRadiusDropLegacyPrefixes'] ?? false;
        if (!$needsWebkit && !$needsMoz && !$dropLegacy) {
            return false;
        }

        $changed = false;
        $hasUnprefixed = [];
        $hasUnprefixedShorthand = false;
        foreach ($entries as $entry) {
            if ($entry['important'] || !str_starts_with($entry['property'], 'border-') || !str_ends_with($entry['property'], '-radius')) {
                continue;
            }
            if (!str_starts_with($entry['property'], '-webkit-') && !str_starts_with($entry['property'], '-moz-')) {
                $hasUnprefixed[$entry['property'] . "\0" . $entry['value']] = true;
                if ($entry['property'] === 'border-radius') {
                    $hasUnprefixedShorthand = true;
                }
            }
        }

        $rewritten = [];
        foreach ($entries as $entry) {
            if ($entry['important'] || !str_ends_with($entry['property'], '-radius')) {
                $rewritten[] = $entry;
                continue;
            }

            if (str_starts_with($entry['property'], '-webkit-') || str_starts_with($entry['property'], '-moz-')) {
                $unprefixed = preg_replace('/^-(?:webkit|moz)-/', '', $entry['property']) ?? $entry['property'];
                if ($dropLegacy && ($hasUnprefixedShorthand || isset($hasUnprefixed[$unprefixed . "\0" . $entry['value']]))) {
                    $changed = true;
                    continue;
                }
                $rewritten[] = $entry;
                continue;
            }

            if ($needsWebkit && !$this->hasBorderRadiusPrefixedEntry($entries, '-webkit-' . $entry['property'], $entry['value'])) {
                $rewritten[] = $this->declarationEntry('-webkit-' . $entry['property'], $entry['value']);
                $changed = true;
            }
            if ($needsMoz && !$this->hasBorderRadiusPrefixedEntry($entries, '-moz-' . $entry['property'], $entry['value'])) {
                $rewritten[] = $this->declarationEntry('-moz-' . $entry['property'], $entry['value']);
                $changed = true;
            }
            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function hasBorderRadiusPrefixedEntry(array $entries, string $property, string $value): bool
    {
        foreach ($entries as $entry) {
            if (!$entry['important'] && $entry['property'] === $property && $entry['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array{0:list<array{property:string,name:string,value:string,important:bool}>,1:list<array{property:string,name:string,value:string,important:bool}>}|null
     */
    private function rewriteLogicalBorderRadiusEntries(array $entries): ?array
    {
        $ltrEntries = [];
        $rtlEntries = [];
        $changed = false;

        foreach ($entries as $entry) {
            if ($entry['important']) {
                $ltrEntries[] = $entry;
                $rtlEntries[] = $entry;
                continue;
            }

            $ltrProperty = $this->logicalBorderRadiusPhysicalProperty($entry['property'], 'ltr');
            $rtlProperty = $this->logicalBorderRadiusPhysicalProperty($entry['property'], 'rtl');
            if ($ltrProperty === null || $rtlProperty === null) {
                $ltrEntries[] = $entry;
                $rtlEntries[] = $entry;
                continue;
            }

            $ltrEntries[] = $this->declarationEntry($ltrProperty, $entry['value']);
            $rtlEntries[] = $this->declarationEntry($rtlProperty, $entry['value']);
            $changed = true;
        }

        return $changed ? [$ltrEntries, $rtlEntries] : null;
    }

    private function logicalBorderRadiusPhysicalProperty(string $property, string $direction): ?string
    {
        return match ($property) {
            'border-start-start-radius' => $direction === 'rtl' ? 'border-top-right-radius' : 'border-top-left-radius',
            'border-start-end-radius' => $direction === 'rtl' ? 'border-top-left-radius' : 'border-top-right-radius',
            'border-end-end-radius' => $direction === 'rtl' ? 'border-bottom-left-radius' : 'border-bottom-right-radius',
            'border-end-start-radius' => $direction === 'rtl' ? 'border-bottom-right-radius' : 'border-bottom-left-radius',
            default => null,
        };
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLogicalBorderFallbackRule(string $selectors, array $entries, array $targetOptions, bool $needsFullFallback, bool $needsShorthandFallback): ?string
    {
        if (!$needsFullFallback && !$needsShorthandFallback) {
            return null;
        }

        $ltrEntries = [];
        $rtlEntries = [];
        $changed = false;
        $needsDirectionSplit = false;

        foreach ($entries as $entry) {
            $fallback = $this->logicalBorderFallbackEntries($entry, $needsFullFallback, $needsShorthandFallback);
            if ($fallback === null) {
                $ltrEntries[] = $entry;
                $rtlEntries[] = $entry;
                continue;
            }

            array_push($ltrEntries, ...$fallback['ltr']);
            array_push($rtlEntries, ...$fallback['rtl']);
            $changed = true;
            $needsDirectionSplit = $needsDirectionSplit || $fallback['directional'];
        }

        if (!$changed) {
            return null;
        }

        if (!$needsDirectionSplit) {
            return $this->serializeRulesForSelectors($this->selectorsWithTargetPrefixVariants($selectors, $targetOptions), $ltrEntries);
        }

        $rules = '';
        foreach ($this->selectorsWithTargetPrefixVariants($selectors, $targetOptions) as $selector) {
            $rules .= $this->selectorVariant($selector, 'ltr-webkit') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selector, 'ltr-modern') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selector, 'rtl-webkit') . '{' . $this->serializeDeclarations($rtlEntries) . '}'
                . $this->selectorVariant($selector, 'rtl-modern') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        return $rules;
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool} $entry
     * @return array{ltr:list<array{property:string,name:string,value:string,important:bool}>,rtl:list<array{property:string,name:string,value:string,important:bool}>,directional:bool}|null
     */
    private function logicalBorderFallbackEntries(array $entry, bool $needsFullFallback, bool $needsShorthandFallback): ?array
    {
        $property = $entry['property'];
        $value = $entry['value'];
        $important = $entry['important'];

        if ($needsFullFallback) {
            switch ($property) {
                case 'border-block':
                    return $this->logicalBorderFallbackResult([
                        ['border-top', $value],
                        ['border-bottom', $value],
                    ], null, false, $important);

                case 'border-block-start':
                    return $this->logicalBorderFallbackResult([
                        ['border-top', $value],
                    ], null, false, $important);

                case 'border-block-end':
                    return $this->logicalBorderFallbackResult([
                        ['border-bottom', $value],
                    ], null, false, $important);

                case 'border-inline':
                    return $this->logicalBorderFallbackResult([
                        ['border-left', $value],
                        ['border-right', $value],
                    ], null, false, $important);

                case 'border-inline-start':
                    return $this->logicalBorderFallbackResult(
                        [['border-left', $value]],
                        [['border-right', $value]],
                        true,
                        $important
                    );

                case 'border-inline-end':
                    return $this->logicalBorderFallbackResult(
                        [['border-right', $value]],
                        [['border-left', $value]],
                        true,
                        $important
                    );
            }

            if (preg_match('/^border-(block|inline)-(start|end)-(width|style|color)$/', $property, $matches) === 1) {
                $axis = $matches[1];
                $side = $matches[2];
                $suffix = '-' . $matches[3];

                if ($axis === 'block') {
                    $physical = $side === 'start' ? 'border-top' . $suffix : 'border-bottom' . $suffix;
                    return $this->logicalBorderFallbackResult([[$physical, $value]], null, false, $important);
                }

                if ($side === 'start') {
                    return $this->logicalBorderFallbackResult(
                        [['border-left' . $suffix, $value]],
                        [['border-right' . $suffix, $value]],
                        true,
                        $important
                    );
                }

                return $this->logicalBorderFallbackResult(
                    [['border-right' . $suffix, $value]],
                    [['border-left' . $suffix, $value]],
                    true,
                    $important
                );
            }
        }

        if (preg_match('/^border-(block|inline)-(width|style|color)$/', $property, $matches) === 1) {
            $axis = $matches[1];
            $suffix = '-' . $matches[2];
            $sides = $this->axisSides($value);
            if ($sides === null) {
                return null;
            }

            if ($needsFullFallback) {
                if ($axis === 'block') {
                    return $this->logicalBorderFallbackResult([
                        ['border-top' . $suffix, $sides[0]],
                        ['border-bottom' . $suffix, $sides[1]],
                    ], null, false, $important);
                }

                return $this->logicalBorderFallbackResult(
                    [
                        ['border-left' . $suffix, $sides[0]],
                        ['border-right' . $suffix, $sides[1]],
                    ],
                    [
                        ['border-left' . $suffix, $sides[1]],
                        ['border-right' . $suffix, $sides[0]],
                    ],
                    $sides[0] !== $sides[1],
                    $important
                );
            }

            if ($needsShorthandFallback) {
                if ($axis === 'block') {
                    return $this->logicalBorderFallbackResult([
                        ['border-block-start' . $suffix, $sides[0]],
                        ['border-block-end' . $suffix, $sides[1]],
                    ], null, false, $important);
                }

                return $this->logicalBorderFallbackResult([
                    ['border-inline-start' . $suffix, $sides[0]],
                    ['border-inline-end' . $suffix, $sides[1]],
                ], null, false, $important);
            }
        }

        if ($needsShorthandFallback) {
            switch ($property) {
                case 'border-block':
                    return $this->logicalBorderFallbackResult([
                        ['border-block-start', $value],
                        ['border-block-end', $value],
                    ], null, false, $important);

                case 'border-inline':
                    return $this->logicalBorderFallbackResult([
                        ['border-inline-start', $value],
                        ['border-inline-end', $value],
                    ], null, false, $important);
            }
        }

        return null;
    }

    /**
     * @param list<array{0:string,1:string}> $ltr
     * @param list<array{0:string,1:string}>|null $rtl
     * @return array{ltr:list<array{property:string,name:string,value:string,important:bool}>,rtl:list<array{property:string,name:string,value:string,important:bool}>,directional:bool}
     */
    private function logicalBorderFallbackResult(array $ltr, ?array $rtl, bool $directional, bool $important): array
    {
        return [
            'ltr' => $this->declarationEntriesFromPairs($ltr, $important),
            'rtl' => $this->declarationEntriesFromPairs($rtl ?? $ltr, $important),
            'directional' => $directional,
        ];
    }

    /**
     * @param list<array{0:string,1:string}> $pairs
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function declarationEntriesFromPairs(array $pairs, bool $important): array
    {
        $entries = [];
        foreach ($pairs as [$property, $value]) {
            $entries[] = $this->declarationEntry($property, $value, $important);
        }

        return $entries;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLogicalSpacingFallbackRule(string $selectors, array $entries, array $targetOptions, bool $needsInlineFallback, bool $needsBlockFallback, bool $needsShorthandFallback): ?string
    {
        if (!$needsInlineFallback && !$needsBlockFallback && !$needsShorthandFallback) {
            return null;
        }

        $ltrEntries = [];
        $rtlEntries = [];
        $changed = false;
        $needsDirectionSplit = false;

        $count = count($entries);
        for ($index = 0; $index < $count; $index++) {
            $entry = $entries[$index];
            $pairedFallback = $this->logicalSpacingInlinePairFallbackEntries($entry, $entries[$index + 1] ?? null, $needsInlineFallback);
            if ($pairedFallback !== null) {
                array_push($ltrEntries, ...$pairedFallback['ltr']);
                array_push($rtlEntries, ...$pairedFallback['rtl']);
                $changed = true;
                $needsDirectionSplit = $needsDirectionSplit || $pairedFallback['directional'];
                $index++;
                continue;
            }

            $fallback = $this->logicalSpacingFallbackEntries($entry, $needsInlineFallback, $needsBlockFallback, $needsShorthandFallback);
            if ($fallback === null) {
                $ltrEntries[] = $entry;
                $rtlEntries[] = $entry;
                continue;
            }

            array_push($ltrEntries, ...$fallback['ltr']);
            array_push($rtlEntries, ...$fallback['rtl']);
            $changed = true;
            $needsDirectionSplit = $needsDirectionSplit || $fallback['directional'];
        }

        if (!$changed) {
            return null;
        }

        if (!$needsDirectionSplit) {
            return $this->serializeRulesForSelectors($this->selectorsWithTargetPrefixVariants($selectors, $targetOptions), $ltrEntries);
        }

        $rules = '';
        foreach ($this->selectorsWithTargetPrefixVariants($selectors, $targetOptions) as $selector) {
            $rules .= $this->selectorVariant($selector, 'ltr-webkit') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selector, 'ltr-modern') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selector, 'rtl-webkit') . '{' . $this->serializeDeclarations($rtlEntries) . '}'
                . $this->selectorVariant($selector, 'rtl-modern') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        return $rules;
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool} $entry
     * @return array{ltr:list<array{property:string,name:string,value:string,important:bool}>,rtl:list<array{property:string,name:string,value:string,important:bool}>,directional:bool}|null
     */
    private function logicalSpacingFallbackEntries(array $entry, bool $needsInlineFallback, bool $needsBlockFallback, bool $needsShorthandFallback): ?array
    {
        $property = $entry['property'];
        $value = $entry['value'];
        $important = $entry['important'];

        if (preg_match('/^(margin|padding)-(inline|block)-(start|end)$/', $property, $matches) === 1) {
            $base = $matches[1];
            $axis = $matches[2];
            $side = $matches[3];

            if ($axis === 'inline' && $needsInlineFallback) {
                if ($side === 'start') {
                    return $this->logicalSpacingFallbackResult(
                        [[$base . '-left', $value]],
                        [[$base . '-right', $value]],
                        true,
                        $important
                    );
                }

                return $this->logicalSpacingFallbackResult(
                    [[$base . '-right', $value]],
                    [[$base . '-left', $value]],
                    true,
                    $important
                );
            }

            if ($axis === 'block' && $needsBlockFallback) {
                $physical = $side === 'start' ? $base . '-top' : $base . '-bottom';
                return $this->logicalSpacingFallbackResult([[$physical, $value]], null, false, $important);
            }

            return null;
        }

        if (preg_match('/^(margin|padding)-(inline|block)$/', $property, $matches) !== 1) {
            return null;
        }

        $base = $matches[1];
        $axis = $matches[2];
        $sides = $this->axisSides($value);
        if ($sides === null) {
            return null;
        }

        if ($axis === 'inline' && $needsInlineFallback) {
            return $this->logicalSpacingFallbackResult(
                [
                    [$base . '-left', $sides[0]],
                    [$base . '-right', $sides[1]],
                ],
                [
                    [$base . '-left', $sides[1]],
                    [$base . '-right', $sides[0]],
                ],
                $sides[0] !== $sides[1],
                $important
            );
        }

        if ($axis === 'block' && $needsBlockFallback) {
            return $this->logicalSpacingFallbackResult([
                [$base . '-top', $sides[0]],
                [$base . '-bottom', $sides[1]],
            ], null, false, $important);
        }

        if ($needsShorthandFallback) {
            return $this->logicalSpacingFallbackResult([
                [$base . '-' . $axis . '-start', $sides[0]],
                [$base . '-' . $axis . '-end', $sides[1]],
            ], null, false, $important);
        }

        return null;
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool}|null $end
     * @return array{ltr:list<array{property:string,name:string,value:string,important:bool}>,rtl:list<array{property:string,name:string,value:string,important:bool}>,directional:bool}|null
     */
    private function logicalSpacingInlinePairFallbackEntries(array $start, ?array $end, bool $needsInlineFallback): ?array
    {
        if (!$needsInlineFallback || $end === null || $start['important'] !== $end['important']) {
            return null;
        }

        if (preg_match('/^(margin|padding)-inline-start$/', $start['property'], $matches) !== 1) {
            return null;
        }

        $base = $matches[1];
        if ($end['property'] !== $base . '-inline-end') {
            return null;
        }

        return $this->logicalSpacingFallbackResult(
            [
                [$base . '-left', $start['value']],
                [$base . '-right', $end['value']],
            ],
            [
                [$base . '-left', $end['value']],
                [$base . '-right', $start['value']],
            ],
            $start['value'] !== $end['value'],
            $start['important']
        );
    }

    /**
     * @param list<array{0:string,1:string}> $ltr
     * @param list<array{0:string,1:string}>|null $rtl
     * @return array{ltr:list<array{property:string,name:string,value:string,important:bool}>,rtl:list<array{property:string,name:string,value:string,important:bool}>,directional:bool}
     */
    private function logicalSpacingFallbackResult(array $ltr, ?array $rtl, bool $directional, bool $important): array
    {
        return [
            'ltr' => $this->declarationEntriesFromPairs($ltr, $important),
            'rtl' => $this->declarationEntriesFromPairs($rtl ?? $ltr, $important),
            'directional' => $directional,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLogicalInsetFallbackRule(string $selectors, array $entries, array $targetOptions): ?string
    {
        $ltrEntries = [];
        $rtlEntries = [];
        $changed = false;
        $needsDirectionSplit = false;

        foreach ($entries as $entry) {
            $fallback = $this->logicalInsetFallbackEntries($entry);
            if ($fallback === null) {
                $ltrEntries[] = $entry;
                $rtlEntries[] = $entry;
                continue;
            }

            array_push($ltrEntries, ...$fallback['ltr']);
            array_push($rtlEntries, ...$fallback['rtl']);
            $changed = true;
            $needsDirectionSplit = $needsDirectionSplit || $fallback['directional'];
        }

        if (!$changed) {
            return null;
        }

        if (!$needsDirectionSplit) {
            return $this->serializeRulesForSelectors($this->selectorsWithTargetPrefixVariants($selectors, $targetOptions), $ltrEntries);
        }

        $rules = '';
        foreach ($this->selectorsWithTargetPrefixVariants($selectors, $targetOptions) as $selector) {
            $rules .= $this->selectorVariant($selector, 'ltr-webkit') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selector, 'ltr-modern') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selector, 'rtl-webkit') . '{' . $this->serializeDeclarations($rtlEntries) . '}'
                . $this->selectorVariant($selector, 'rtl-modern') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        return $rules;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLogicalTextAlignFallbackRule(string $selectors, array $entries, array $targetOptions): ?string
    {
        $baseEntries = [];
        $ltrEntries = [];
        $rtlEntries = [];
        $changed = false;

        foreach ($entries as $entry) {
            if ($entry['property'] !== 'text-align' || $entry['important']) {
                $baseEntries[] = $entry;
                continue;
            }

            $value = strtolower(trim($entry['value']));
            if ($value === 'start') {
                $ltrEntries[] = $this->declarationEntry('text-align', 'left');
                $rtlEntries[] = $this->declarationEntry('text-align', 'right');
                $changed = true;
                continue;
            }

            if ($value === 'end') {
                $ltrEntries[] = $this->declarationEntry('text-align', 'right');
                $rtlEntries[] = $this->declarationEntry('text-align', 'left');
                $changed = true;
                continue;
            }

            $baseEntries[] = $entry;
        }

        if (!$changed) {
            return null;
        }

        $selectorVariants = $this->selectorsWithTargetPrefixVariants($selectors, $targetOptions);
        $output = $baseEntries === [] ? '' : $this->serializeRulesForSelectors($selectorVariants, $baseEntries);
        foreach ($selectorVariants as $selector) {
            $output .= $this->directionSelectorVariant($selector, 'ltr') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->directionSelectorVariant($selector, 'rtl') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        return $output;
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool} $entry
     * @return array{ltr:list<array{property:string,name:string,value:string,important:bool}>,rtl:list<array{property:string,name:string,value:string,important:bool}>,directional:bool}|null
     */
    private function logicalInsetFallbackEntries(array $entry): ?array
    {
        $important = $entry['important'];

        switch ($entry['property']) {
            case 'inset-inline-start':
                return [
                    'ltr' => [$this->declarationEntry('left', $entry['value'], $important)],
                    'rtl' => [$this->declarationEntry('right', $entry['value'], $important)],
                    'directional' => true,
                ];

            case 'inset-inline-end':
                return [
                    'ltr' => [$this->declarationEntry('right', $entry['value'], $important)],
                    'rtl' => [$this->declarationEntry('left', $entry['value'], $important)],
                    'directional' => true,
                ];

            case 'inset-inline':
                $inline = $this->axisSides($entry['value']);
                if ($inline === null) {
                    return null;
                }

                return [
                    'ltr' => [
                        $this->declarationEntry('left', $inline[0], $important),
                        $this->declarationEntry('right', $inline[1], $important),
                    ],
                    'rtl' => [
                        $this->declarationEntry('left', $inline[1], $important),
                        $this->declarationEntry('right', $inline[0], $important),
                    ],
                    'directional' => $inline[0] !== $inline[1],
                ];

            case 'inset-block-start':
                return [
                    'ltr' => [$this->declarationEntry('top', $entry['value'], $important)],
                    'rtl' => [$this->declarationEntry('top', $entry['value'], $important)],
                    'directional' => false,
                ];

            case 'inset-block-end':
                return [
                    'ltr' => [$this->declarationEntry('bottom', $entry['value'], $important)],
                    'rtl' => [$this->declarationEntry('bottom', $entry['value'], $important)],
                    'directional' => false,
                ];

            case 'inset-block':
                $block = $this->axisSides($entry['value']);
                if ($block === null) {
                    return null;
                }
                $blockEntries = [
                    $this->declarationEntry('top', $block[0], $important),
                    $this->declarationEntry('bottom', $block[1], $important),
                ];

                return [
                    'ltr' => $blockEntries,
                    'rtl' => $blockEntries,
                    'directional' => false,
                ];

            case 'inset':
                $box = $this->boxSides($entry['value']);
                if ($box === null) {
                    return null;
                }
                $boxEntries = [
                    $this->declarationEntry('top', $box['top'], $important),
                    $this->declarationEntry('bottom', $box['bottom'], $important),
                    $this->declarationEntry('left', $box['left'], $important),
                    $this->declarationEntry('right', $box['right'], $important),
                ];

                return [
                    'ltr' => $boxEntries,
                    'rtl' => $boxEntries,
                    'directional' => false,
                ];
        }

        return null;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function axisSides(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === [] || count($tokens) > 2) {
            return null;
        }

        return [$tokens[0], $tokens[1] ?? $tokens[0]];
    }

    /**
     * @return array{top:string,right:string,bottom:string,left:string}|null
     */
    private function boxSides(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === [] || count($tokens) > 4) {
            return null;
        }

        $top = $tokens[0];
        $right = $tokens[1] ?? $top;
        $bottom = $tokens[2] ?? $top;
        $left = $tokens[3] ?? $right;

        return [
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'left' => $left,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewritePrefixedTransitionEntries(array &$entries, array $targetOptions): bool
    {
        $baseProperties = [
            'transition' => true,
            'transition-property' => true,
            'transition-duration' => true,
            'transition-delay' => true,
            'transition-timing-function' => true,
        ];
        $neededPrefixes = [
            '-webkit-' => $targetOptions['transitionNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['transitionNeedsMoz'] ?? false,
            '-o-' => $targetOptions['transitionNeedsO'] ?? false,
        ];
        $vendorProperties = [];
        foreach (array_keys($baseProperties) as $baseProperty) {
            foreach ($neededPrefixes as $prefix => $_needed) {
                $vendorProperties[$prefix . $baseProperty] = [$prefix, $baseProperty];
            }
        }

        $unprefixedValues = [];
        $prefixedValues = [];
        $hasRelevantDeclaration = false;
        foreach ($entries as $entry) {
            if (isset($baseProperties[$entry['property']])) {
                $hasRelevantDeclaration = true;
                if (!$entry['important']) {
                    [$value] = $this->canonicalPrefixedTransitionValue($entry['property'], $entry['value'], $targetOptions);
                    $unprefixedValues[$entry['property']][$value] = true;
                }
                continue;
            }

            if (isset($vendorProperties[$entry['property']])) {
                $hasRelevantDeclaration = true;
                if (!$entry['important']) {
                    [$prefix, $baseProperty] = $vendorProperties[$entry['property']];
                    $prefixedValues[$baseProperty][$prefix][$entry['value']] = true;
                }
            }
        }

        if (!$hasRelevantDeclaration || $unprefixedValues === []) {
            return false;
        }

        $changed = false;
        $rewritten = [];
        foreach ($entries as $entry) {
            if ($entry['important']) {
                $rewritten[] = $entry;
                continue;
            }

            if (isset($vendorProperties[$entry['property']])) {
                [$prefix, $baseProperty] = $vendorProperties[$entry['property']];
                if (
                    !$this->transitionDeclarationNeedsPrefix($baseProperty, $entry['value'], $prefix, $neededPrefixes, $targetOptions)
                    && isset($unprefixedValues[$baseProperty][$entry['value']])
                ) {
                    $changed = true;
                    continue;
                }

                $rewritten[] = $entry;
                continue;
            }

            if (isset($baseProperties[$entry['property']])) {
                [$value, $entryChanged, $needsPrefixedTransition] = $this->canonicalPrefixedTransitionValue($entry['property'], $entry['value'], $targetOptions);
                if ($entryChanged) {
                    $entry['value'] = $value;
                    $changed = true;
                }

                $entryNeededPrefixes = $neededPrefixes;
                if ($needsPrefixedTransition) {
                    $entryNeededPrefixes['-webkit-'] = true;
                }

                foreach ($entryNeededPrefixes as $prefix => $needed) {
                    if (!$needed || isset($prefixedValues[$entry['property']][$prefix][$value])) {
                        continue;
                    }

                    $rewritten[] = $this->declarationEntry($prefix . $entry['property'], $value);
                    $prefixedValues[$entry['property']][$prefix][$value] = true;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @return array{0:string,1:bool,2:bool}
     */
    private function canonicalPrefixedTransitionValue(string $property, string $value, array $targetOptions): array
    {
        if ($property === 'transition') {
            return $this->rewritePrefixedTransitionShorthand($value, $targetOptions);
        }

        if ($property === 'transition-property') {
            return $this->rewritePrefixedTransitionPropertyList($value, $targetOptions);
        }

        return [$value, false, false];
    }

    /**
     * @param array<string, bool> $neededPrefixes
     */
    private function transitionDeclarationNeedsPrefix(
        string $baseProperty,
        string $value,
        string $prefix,
        array $neededPrefixes,
        array $targetOptions
    ): bool {
        if ($neededPrefixes[$prefix] ?? false) {
            return true;
        }

        if ($prefix === '-webkit-' && ($baseProperty === 'transition' || $baseProperty === 'transition-property')) {
            [, , $needsPrefixedTransition] = $this->canonicalPrefixedTransitionValue($baseProperty, $value, $targetOptions);
            return $needsPrefixedTransition;
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteDisplayFlexPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $lastStandardIndex = [];
        $lastKindIndex = [];
        foreach ($entries as $index => $entry) {
            if ($entry['property'] !== 'display' || $entry['important']) {
                continue;
            }

            $kind = $this->displayFlexKind($entry['value']);
            if ($kind === null) {
                continue;
            }

            $lastKindIndex[$kind] = $index;
            if ($this->displayFlexCanonicalValue($entry['value']) === $kind) {
                $lastStandardIndex[$kind] = $index;
            }
        }

        if ($lastStandardIndex === []) {
            return false;
        }

        $changed = false;
        $rewritten = [];
        $seen = [
            'flex' => [],
            'inline-flex' => [],
        ];

        foreach ($entries as $index => $entry) {
            if ($entry['property'] !== 'display' || $entry['important']) {
                $rewritten[] = $entry;
                continue;
            }

            $kind = $this->displayFlexKind($entry['value']);
            if ($kind === null) {
                $rewritten[] = $entry;
                continue;
            }

            $standardIndex = $lastStandardIndex[$kind] ?? null;
            if ($standardIndex === null) {
                $rewritten[] = $entry;
                continue;
            }

            $canonical = $this->displayFlexCanonicalValue($entry['value']);
            if ($index < $standardIndex) {
                $changed = true;
                continue;
            }

            if ($index === $standardIndex && ($lastKindIndex[$kind] ?? $index) > $index) {
                $changed = true;
                continue;
            }

            $needed = $this->neededDisplayFlexValues($kind, $targetOptions);

            if ($canonical === $kind) {
                foreach ($needed as $displayValue) {
                    if ($displayValue === $kind || isset($seen[$kind][$displayValue])) {
                        continue;
                    }

                    $rewritten[] = $this->declarationEntry('display', $displayValue);
                    $seen[$kind][$displayValue] = true;
                    $changed = true;
                }
            }

            if (isset($seen[$kind][$canonical])) {
                $changed = true;
                continue;
            }

            $seen[$kind][$canonical] = true;
            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    private function displayFlexKind(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'flex', '-webkit-box', '-moz-box', '-webkit-flex', '-ms-flexbox' => 'flex',
            'inline-flex', '-webkit-inline-box', '-moz-inline-box', '-webkit-inline-flex', '-ms-inline-flexbox' => 'inline-flex',
            default => null,
        };
    }

    private function displayFlexCanonicalValue(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<string>
     */
    private function neededDisplayFlexValues(string $kind, array $targetOptions): array
    {
        $values = [];
        if ($targetOptions['displayFlexNeedsOldWebkit'] ?? false) {
            $values[] = $kind === 'inline-flex' ? '-webkit-inline-box' : '-webkit-box';
        }
        if ($targetOptions['displayFlexNeedsMoz'] ?? false) {
            $values[] = $kind === 'inline-flex' ? '-moz-inline-box' : '-moz-box';
        }
        if ($targetOptions['displayFlexNeedsWebkit'] ?? false) {
            $values[] = $kind === 'inline-flex' ? '-webkit-inline-flex' : '-webkit-flex';
        }
        if ($targetOptions['displayFlexNeedsMs'] ?? false) {
            $values[] = $kind === 'inline-flex' ? '-ms-inline-flexbox' : '-ms-flexbox';
        }
        $values[] = $kind;

        return $values;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteFlexPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $standards = $this->collectFlexStandardValues($entries);
        if ($standards === []) {
            return false;
        }

        $existing = [];
        foreach ($entries as $entry) {
            if ($entry['important']) {
                continue;
            }

            $existing[$entry['property'] . "\0" . $entry['value']] = true;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if ($entry['important']) {
                $rewritten[] = $entry;
                continue;
            }

            if ($entry['property'] === 'flex-flow') {
                $minified = $this->minifyFlexFlowValue($entry['value']);
                if ($minified !== $entry['value']) {
                    $entry = $this->entryWithValue($entry, $minified);
                    $changed = true;
                }
            }

            if ($this->shouldDropFlexPrefixedEntry($entry, $standards, $targetOptions)) {
                $changed = true;
                continue;
            }

            $prefixEntries = $this->flexPrefixEntriesForStandard($entry['property'], $entry['value'], $targetOptions);
            if ($prefixEntries !== []) {
                foreach ($prefixEntries as $prefixEntry) {
                    $key = $prefixEntry['property'] . "\0" . $prefixEntry['value'];
                    if (isset($existing[$key])) {
                        continue;
                    }

                    $rewritten[] = $prefixEntry;
                    $existing[$key] = true;
                    $changed = true;
                }
            }

            if ($this->flexPlaceShorthandNeedsExpansion($entry['property'], $targetOptions)) {
                $changed = true;
                continue;
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array<string, array<string, true>>
     */
    private function collectFlexStandardValues(array $entries): array
    {
        $standards = [];
        foreach ($entries as $entry) {
            if ($entry['important']) {
                continue;
            }

            $property = $entry['property'];
            $value = $entry['value'];
            if ($this->isFlexStandardProperty($property)) {
                if ($property === 'flex-flow') {
                    $value = $this->minifyFlexFlowValue($value);
                }
                $standards[$property][$value] = true;
                continue;
            }

            if ($property === 'place-content') {
                [$align, $justify] = $this->splitPlacePair($value);
                $standards['place-content'][$value] = true;
                $standards['align-content'][$align] = true;
                $standards['justify-content'][$justify] = true;
                continue;
            }

            if ($property === 'place-self') {
                [$align, $justify] = $this->splitPlacePair($value);
                $standards['place-self'][$value] = true;
                $standards['align-self'][$align] = true;
                $standards['justify-self'][$justify] = true;
                continue;
            }

            if ($property === 'place-items') {
                [$align, $justify] = $this->splitPlacePair($value);
                $standards['place-items'][$value] = true;
                $standards['align-items'][$align] = true;
                $standards['justify-items'][$justify] = true;
            }
        }

        return $standards;
    }

    private function isFlexStandardProperty(string $property): bool
    {
        return in_array($property, [
            'flex-direction',
            'flex-wrap',
            'flex-flow',
            'flex-grow',
            'flex-shrink',
            'flex-basis',
            'flex',
            'order',
            'align-content',
            'justify-content',
            'align-self',
            'align-items',
        ], true);
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexPrefixEntriesForStandard(string $property, string $value, array $targetOptions): array
    {
        return match ($property) {
            'flex-direction' => $this->flexDirectionPrefixEntries($value, $targetOptions),
            'flex-wrap' => $this->flexWrapPrefixEntries($value, $targetOptions),
            'flex-flow' => $this->flexFlowPrefixEntries($value, $targetOptions),
            'flex-grow' => $this->flexGrowPrefixEntries($value, $targetOptions),
            'flex-shrink' => $this->flexSimplePrefixEntries($value, $targetOptions, [
                ['property' => '-ms-flex-negative', 'flag' => 'flexNeedsMs'],
                ['property' => '-webkit-flex-shrink', 'flag' => 'flexNeedsWebkit'],
            ]),
            'flex-basis' => $this->flexSimplePrefixEntries($value, $targetOptions, [
                ['property' => '-ms-flex-preferred-size', 'flag' => 'flexNeedsMs'],
                ['property' => '-webkit-flex-basis', 'flag' => 'flexNeedsWebkit'],
            ]),
            'flex' => $this->flexShorthandPrefixEntries($value, $targetOptions),
            'order' => $this->orderPrefixEntries($value, $targetOptions),
            'align-content' => $this->alignmentPrefixEntries('align-content', $value, $targetOptions),
            'justify-content' => $this->alignmentPrefixEntries('justify-content', $value, $targetOptions),
            'align-self' => $this->alignmentPrefixEntries('align-self', $value, $targetOptions),
            'align-items' => $this->alignmentPrefixEntries('align-items', $value, $targetOptions),
            'place-content' => $this->placeContentPrefixEntries($value, $targetOptions),
            'place-self' => $this->placeSelfPrefixEntries($value, $targetOptions),
            'place-items' => $this->placeItemsPrefixEntries($value, $targetOptions),
            default => [],
        };
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexDirectionPrefixEntries(string $value, array $targetOptions): array
    {
        $entries = [];
        $parts = $this->flexDirectionBoxParts($value);
        if ($parts !== null) {
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-orient', $parts[0]);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-orient', $parts[0]);
            }
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-direction', $parts[1]);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-direction', $parts[1]);
            }
        }

        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-flex-direction', $value);
        }
        if ($targetOptions['flexNeedsMs'] ?? false) {
            $entries[] = $this->declarationEntry('-ms-flex-direction', $value);
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexWrapPrefixEntries(string $value, array $targetOptions): array
    {
        $entries = [];
        $boxLines = $this->flexWrapBoxLinesValue($value);
        if ($boxLines !== null) {
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-lines', $boxLines);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-lines', $boxLines);
            }
        }
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-flex-wrap', $value);
        }
        if ($targetOptions['flexNeedsMs'] ?? false) {
            $entries[] = $this->declarationEntry('-ms-flex-wrap', $value);
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexFlowPrefixEntries(string $value, array $targetOptions): array
    {
        [$direction] = $this->parseFlexFlowValue($value);
        $entries = [];
        $parts = $this->flexDirectionBoxParts($direction);
        if ($parts !== null) {
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-orient', $parts[0]);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-orient', $parts[0]);
            }
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-direction', $parts[1]);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-direction', $parts[1]);
            }
        }
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-flex-flow', $value);
        }
        if ($targetOptions['flexNeedsMs'] ?? false) {
            $entries[] = $this->declarationEntry('-ms-flex-flow', $value);
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexGrowPrefixEntries(string $value, array $targetOptions): array
    {
        $entries = [];
        if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-box-flex', $value);
        }
        if ($targetOptions['flexNeedsMoz'] ?? false) {
            $entries[] = $this->declarationEntry('-moz-box-flex', $value);
        }
        if ($targetOptions['flexNeedsMs'] ?? false) {
            $entries[] = $this->declarationEntry('-ms-flex-positive', $value);
        }
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-flex-grow', $value);
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @param list<array{property:string,flag:string}> $mapping
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexSimplePrefixEntries(string $value, array $targetOptions, array $mapping): array
    {
        $entries = [];
        foreach ($mapping as $item) {
            if ($targetOptions[$item['flag']] ?? false) {
                $entries[] = $this->declarationEntry($item['property'], $value);
            }
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function flexShorthandPrefixEntries(string $value, array $targetOptions): array
    {
        $entries = [];
        $boxFlex = $this->legacyBoxFlexValueForFlex($value);
        if ($boxFlex !== null) {
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-flex', $boxFlex);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-flex', $boxFlex);
            }
        }
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-flex', $value);
        }
        if ($targetOptions['flexNeedsMs'] ?? false) {
            $entries[] = $this->declarationEntry('-ms-flex', $value);
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function orderPrefixEntries(string $value, array $targetOptions): array
    {
        $entries = [];
        if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-box-ordinal-group', $value);
        }
        if ($targetOptions['flexNeedsMoz'] ?? false) {
            $entries[] = $this->declarationEntry('-moz-box-ordinal-group', $value);
        }
        if ($targetOptions['flexNeedsMs'] ?? false) {
            $entries[] = $this->declarationEntry('-ms-flex-order', $value);
        }
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-order', $value);
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function alignmentPrefixEntries(string $property, string $value, array $targetOptions): array
    {
        $entries = [];
        switch ($property) {
            case 'align-content':
                $linePack = $this->legacyContentAlignmentValue($value);
                if (($targetOptions['flexNeedsMs'] ?? false) && $linePack !== null) {
                    $entries[] = $this->declarationEntry('-ms-flex-line-pack', $linePack);
                }
                if ($targetOptions['flexNeedsWebkit'] ?? false) {
                    $entries[] = $this->declarationEntry('-webkit-align-content', $value);
                }
                break;

            case 'justify-content':
                $pack = $this->legacyPackAlignmentValue($value);
                if ($pack !== null) {
                    if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                        $entries[] = $this->declarationEntry('-webkit-box-pack', $pack);
                    }
                    if ($targetOptions['flexNeedsMoz'] ?? false) {
                        $entries[] = $this->declarationEntry('-moz-box-pack', $pack);
                    }
                    if ($targetOptions['flexNeedsMs'] ?? false) {
                        $entries[] = $this->declarationEntry('-ms-flex-pack', $pack);
                    }
                }
                if ($targetOptions['flexNeedsWebkit'] ?? false) {
                    $entries[] = $this->declarationEntry('-webkit-justify-content', $value);
                }
                break;

            case 'align-self':
                $itemAlign = $this->legacySelfAlignmentValue($value);
                if (($targetOptions['flexNeedsMs'] ?? false) && $itemAlign !== null) {
                    $entries[] = $this->declarationEntry('-ms-flex-item-align', $itemAlign);
                }
                if ($targetOptions['flexNeedsWebkit'] ?? false) {
                    $entries[] = $this->declarationEntry('-webkit-align-self', $value);
                }
                break;

            case 'align-items':
                $boxAlign = $this->legacySelfAlignmentValue($value);
                if ($boxAlign !== null) {
                    if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                        $entries[] = $this->declarationEntry('-webkit-box-align', $boxAlign);
                    }
                    if ($targetOptions['flexNeedsMoz'] ?? false) {
                        $entries[] = $this->declarationEntry('-moz-box-align', $boxAlign);
                    }
                    if ($targetOptions['flexNeedsMs'] ?? false) {
                        $entries[] = $this->declarationEntry('-ms-flex-align', $boxAlign);
                    }
                }
                if ($targetOptions['flexNeedsWebkit'] ?? false) {
                    $entries[] = $this->declarationEntry('-webkit-align-items', $value);
                }
                break;
        }

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function placeContentPrefixEntries(string $value, array $targetOptions): array
    {
        if (!$this->flexPlaceShorthandNeedsExpansion('place-content', $targetOptions)) {
            return [];
        }

        [$align, $justify] = $this->splitPlacePair($value);
        $entries = [];
        $linePack = $this->legacyContentAlignmentValue($align);
        if (($targetOptions['flexNeedsMs'] ?? false) && $linePack !== null) {
            $entries[] = $this->declarationEntry('-ms-flex-line-pack', $linePack);
        }
        $pack = $this->legacyPackAlignmentValue($justify);
        if ($pack !== null) {
            if ($targetOptions['flexNeedsOldWebkit'] ?? false) {
                $entries[] = $this->declarationEntry('-webkit-box-pack', $pack);
            }
            if ($targetOptions['flexNeedsMoz'] ?? false) {
                $entries[] = $this->declarationEntry('-moz-box-pack', $pack);
            }
            if ($targetOptions['flexNeedsMs'] ?? false) {
                $entries[] = $this->declarationEntry('-ms-flex-pack', $pack);
            }
        }
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-align-content', $align);
        }
        $entries[] = $this->declarationEntry('align-content', $align);
        if ($targetOptions['flexNeedsWebkit'] ?? false) {
            $entries[] = $this->declarationEntry('-webkit-justify-content', $justify);
        }
        $entries[] = $this->declarationEntry('justify-content', $justify);

        return $entries;
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function placeSelfPrefixEntries(string $value, array $targetOptions): array
    {
        if (!$this->flexPlaceShorthandNeedsExpansion('place-self', $targetOptions)) {
            return [];
        }

        [$align, $justify] = $this->splitPlacePair($value);

        return array_merge(
            $this->alignmentPrefixEntries('align-self', $align, $targetOptions),
            [$this->declarationEntry('align-self', $align)],
            [$this->declarationEntry('justify-self', $justify)]
        );
    }

    /**
     * @param array<string, bool> $targetOptions
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function placeItemsPrefixEntries(string $value, array $targetOptions): array
    {
        if (!$this->flexPlaceShorthandNeedsExpansion('place-items', $targetOptions)) {
            return [];
        }

        [$align, $justify] = $this->splitPlacePair($value);

        return array_merge(
            $this->alignmentPrefixEntries('align-items', $align, $targetOptions),
            [$this->declarationEntry('align-items', $align)],
            [$this->declarationEntry('justify-items', $justify)]
        );
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function flexPlaceShorthandNeedsExpansion(string $property, array $targetOptions): bool
    {
        if (!in_array($property, ['place-content', 'place-self', 'place-items'], true)) {
            return false;
        }

        return ($targetOptions['flexNeedsOldWebkit'] ?? false)
            || ($targetOptions['flexNeedsWebkit'] ?? false)
            || ($targetOptions['flexNeedsMoz'] ?? false)
            || ($targetOptions['flexNeedsMs'] ?? false);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitPlacePair(string $value): array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return ['', ''];
        }

        $align = $tokens[0];
        $justify = count($tokens) > 1 ? implode(' ', array_slice($tokens, 1)) : $align;

        return [$align, $justify];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function flexDirectionBoxParts(string $value): ?array
    {
        return match (strtolower(trim($value))) {
            'row' => ['horizontal', 'normal'],
            'column' => ['vertical', 'normal'],
            'row-reverse' => ['horizontal', 'reverse'],
            'column-reverse' => ['vertical', 'reverse'],
            default => null,
        };
    }

    private function flexWrapBoxLinesValue(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'wrap', 'wrap-reverse' => 'multiple',
            'nowrap' => 'single',
            default => null,
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseFlexFlowValue(string $value): array
    {
        $direction = 'row';
        $wrap = 'nowrap';
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if (in_array($lower, ['row', 'row-reverse', 'column', 'column-reverse'], true)) {
                $direction = $lower;
            } elseif (in_array($lower, ['nowrap', 'wrap', 'wrap-reverse'], true)) {
                $wrap = $lower;
            }
        }

        return [$direction, $wrap];
    }

    private function minifyFlexFlowValue(string $value): string
    {
        [$direction, $wrap] = $this->parseFlexFlowValue($value);
        $parts = [];
        if ($direction !== 'row' || $wrap === 'nowrap') {
            $parts[] = $direction;
        }
        if ($wrap !== 'nowrap') {
            $parts[] = $wrap;
        }

        return implode(' ', $parts);
    }

    private function legacyBoxFlexValueForFlex(string $value): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === [] || preg_match('/^(?:\d+|\d*\.\d+)$/', $tokens[0]) !== 1) {
            return null;
        }

        return $tokens[0];
    }

    private function legacyPackAlignmentValue(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'start', 'flex-start', 'left' => 'start',
            'end', 'flex-end', 'right' => 'end',
            'center' => 'center',
            'space-between' => 'justify',
            default => null,
        };
    }

    private function legacyContentAlignmentValue(string $value): ?string
    {
        return $this->legacyPackAlignmentValue($value);
    }

    private function legacySelfAlignmentValue(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'start', 'flex-start', 'self-start' => 'start',
            'end', 'flex-end', 'self-end' => 'end',
            'center' => 'center',
            'baseline', 'first baseline', 'last baseline' => 'baseline',
            'stretch', 'normal' => 'stretch',
            default => null,
        };
    }

    /**
     * @param array<string, array<string, true>> $standards
     * @param array<string, bool> $targetOptions
     */
    private function shouldDropFlexPrefixedEntry(array $entry, array $standards, array $targetOptions): bool
    {
        $property = $entry['property'];
        $value = $entry['value'];

        return match ($property) {
            '-webkit-box-orient' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && $this->hasFlexDirectionForBoxPart($standards, 'orient', $value),
            '-moz-box-orient' => !($targetOptions['flexNeedsMoz'] ?? false) && $this->hasFlexDirectionForBoxPart($standards, 'orient', $value),
            '-webkit-box-direction' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && $this->hasFlexDirectionForBoxPart($standards, 'direction', $value),
            '-moz-box-direction' => !($targetOptions['flexNeedsMoz'] ?? false) && $this->hasFlexDirectionForBoxPart($standards, 'direction', $value),
            '-webkit-flex-direction' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex-direction'][$value]),
            '-ms-flex-direction' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex-direction'][$value]),
            '-webkit-box-lines' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && $this->hasFlexWrapForBoxLines($standards, $value),
            '-moz-box-lines' => !($targetOptions['flexNeedsMoz'] ?? false) && $this->hasFlexWrapForBoxLines($standards, $value),
            '-webkit-flex-wrap' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex-wrap'][$value]),
            '-ms-flex-wrap' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex-wrap'][$value]),
            '-webkit-flex-flow' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex-flow'][$value]),
            '-ms-flex-flow' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex-flow'][$value]),
            '-webkit-box-flex' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && $this->hasFlexGrowOrFlexForBoxFlex($standards, $value),
            '-moz-box-flex' => !($targetOptions['flexNeedsMoz'] ?? false) && $this->hasFlexGrowOrFlexForBoxFlex($standards, $value),
            '-ms-flex-positive' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex-grow'][$value]),
            '-webkit-flex-grow' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex-grow'][$value]),
            '-ms-flex-negative' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex-shrink'][$value]),
            '-webkit-flex-shrink' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex-shrink'][$value]),
            '-ms-flex-preferred-size' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex-basis'][$value]),
            '-webkit-flex-basis' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex-basis'][$value]),
            '-webkit-flex' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['flex'][$value]),
            '-ms-flex' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['flex'][$value]),
            '-webkit-box-ordinal-group' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && isset($standards['order'][$value]),
            '-moz-box-ordinal-group' => !($targetOptions['flexNeedsMoz'] ?? false) && isset($standards['order'][$value]),
            '-ms-flex-order' => !($targetOptions['flexNeedsMs'] ?? false) && isset($standards['order'][$value]),
            '-webkit-order' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['order'][$value]),
            '-ms-flex-line-pack' => !($targetOptions['flexNeedsMs'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'align-content', $value, 'content'),
            '-webkit-align-content' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['align-content'][$value]),
            '-webkit-box-pack' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'justify-content', $value, 'pack'),
            '-moz-box-pack' => !($targetOptions['flexNeedsMoz'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'justify-content', $value, 'pack'),
            '-ms-flex-pack' => !($targetOptions['flexNeedsMs'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'justify-content', $value, 'pack'),
            '-webkit-justify-content' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['justify-content'][$value]),
            '-ms-flex-item-align' => !($targetOptions['flexNeedsMs'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'align-self', $value, 'self'),
            '-webkit-align-self' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['align-self'][$value]),
            '-webkit-box-align' => !($targetOptions['flexNeedsOldWebkit'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'align-items', $value, 'self'),
            '-moz-box-align' => !($targetOptions['flexNeedsMoz'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'align-items', $value, 'self'),
            '-ms-flex-align' => !($targetOptions['flexNeedsMs'] ?? false) && $this->hasAlignmentForLegacyValue($standards, 'align-items', $value, 'self'),
            '-webkit-align-items' => !($targetOptions['flexNeedsWebkit'] ?? false) && isset($standards['align-items'][$value]),
            default => false,
        };
    }

    /**
     * @param array<string, array<string, true>> $standards
     */
    private function hasFlexDirectionForBoxPart(array $standards, string $part, string $value): bool
    {
        foreach (array_keys($standards['flex-direction'] ?? []) as $direction) {
            $direction = (string) $direction;
            $parts = $this->flexDirectionBoxParts($direction);
            if ($parts !== null && $parts[$part === 'orient' ? 0 : 1] === $value) {
                return true;
            }
        }

        foreach (array_keys($standards['flex-flow'] ?? []) as $flow) {
            $flow = (string) $flow;
            [$direction] = $this->parseFlexFlowValue($flow);
            $parts = $this->flexDirectionBoxParts($direction);
            if ($parts !== null && $parts[$part === 'orient' ? 0 : 1] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, true>> $standards
     */
    private function hasFlexWrapForBoxLines(array $standards, string $value): bool
    {
        foreach (array_keys($standards['flex-wrap'] ?? []) as $wrap) {
            $wrap = (string) $wrap;
            if ($this->flexWrapBoxLinesValue($wrap) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, true>> $standards
     */
    private function hasFlexGrowOrFlexForBoxFlex(array $standards, string $value): bool
    {
        if (isset($standards['flex-grow'][$value])) {
            return true;
        }

        foreach (array_keys($standards['flex'] ?? []) as $flex) {
            $flex = (string) $flex;
            if ($this->legacyBoxFlexValueForFlex($flex) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, true>> $standards
     */
    private function hasAlignmentForLegacyValue(array $standards, string $property, string $value, string $kind): bool
    {
        foreach (array_keys($standards[$property] ?? []) as $standard) {
            $standard = (string) $standard;
            $legacy = match ($kind) {
                'pack' => $this->legacyPackAlignmentValue($standard),
                'content' => $this->legacyContentAlignmentValue($standard),
                'self' => $this->legacySelfAlignmentValue($standard),
                default => null,
            };
            if ($legacy === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array<string, bool> $targetOptions
     */
    private function rewriteColorSchemeFallbackEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        if (!($targetOptions['lightDarkNeedsFallback'] ?? false)) {
            return false;
        }

        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important'] || $entry['property'] !== 'color-scheme') {
                $rewritten[] = $entry;
                continue;
            }

            $tokens = array_map('strtolower', $this->splitWhitespaceTopLevel($entry['value']));
            $unknown = array_diff($tokens, ['light', 'dark', 'only']);
            if ($unknown !== [] || (!in_array('light', $tokens, true) && !in_array('dark', $tokens, true))) {
                $rewritten[] = $entry;
                continue;
            }

            $hasLight = in_array('light', $tokens, true);
            $hasDark = in_array('dark', $tokens, true);
            if ($hasLight && $hasDark) {
                $rewritten[] = $this->declarationEntry('--lightningcss-light', 'initial');
                $rewritten[] = $this->declarationEntry('--lightningcss-dark', '');
                $supportRules[] = '@media (prefers-color-scheme:dark){'
                    . $selectors
                    . '{--lightningcss-light:;--lightningcss-dark:initial}}';
                $changed = true;
            } elseif ($hasLight) {
                $rewritten[] = $this->declarationEntry('--lightningcss-light', 'initial');
                $rewritten[] = $this->declarationEntry('--lightningcss-dark', '');
                $changed = true;
            } else {
                $rewritten[] = $this->declarationEntry('--lightningcss-light', '');
                $rewritten[] = $this->declarationEntry('--lightningcss-dark', 'initial');
                $changed = true;
            }

            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewritePrintColorAdjustPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $needsWebkit = $targetOptions['printColorAdjustNeedsWebkit'] ?? false;
        $needsMoz = $targetOptions['printColorAdjustNeedsMoz'] ?? false;
        if (!$needsWebkit && !$needsMoz) {
            return false;
        }

        $hasWebkit = false;
        $hasMoz = false;
        foreach ($entries as $entry) {
            if ($entry['property'] === '-webkit-print-color-adjust' && !$entry['important']) {
                $hasWebkit = true;
                continue;
            }
            if ($entry['property'] === '-moz-print-color-adjust' && !$entry['important']) {
                $hasMoz = true;
            }
        }

        if (($hasWebkit || !$needsWebkit) && ($hasMoz || !$needsMoz)) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if ($entry['property'] === 'print-color-adjust' && !$entry['important']) {
                if ($needsWebkit && !$hasWebkit) {
                    $rewritten[] = $this->declarationEntry('-webkit-print-color-adjust', $entry['value']);
                    $changed = true;
                }
                if ($needsMoz && !$hasMoz) {
                    $rewritten[] = $this->declarationEntry('-moz-print-color-adjust', $entry['value']);
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteColumnsPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        foreach ([
            'columns',
            'column-width',
            'column-gap',
            'column-rule',
            'column-rule-color',
            'column-rule-width',
            'column-count',
            'column-rule-style',
            'column-span',
            'column-fill',
        ] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['columnsNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['columnsNeedsMoz'] ?? false,
            ]) || $changed;
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteUiPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'user-select', [
            '-webkit-' => $targetOptions['userSelectNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['userSelectNeedsMoz'] ?? false,
            '-ms-' => $targetOptions['userSelectNeedsMs'] ?? false,
        ]);

        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'appearance', [
            '-webkit-' => $targetOptions['appearanceNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['appearanceNeedsMoz'] ?? false,
            '-ms-' => $targetOptions['appearanceNeedsMs'] ?? false,
        ]) || $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteCursorPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $knownPrefixedValues = [];
        $unprefixedBaseValues = [];

        foreach ($entries as $entry) {
            if ($entry['important'] || $entry['property'] !== 'cursor') {
                continue;
            }

            $info = $this->cursorPrefixInfo($entry['value']);
            if ($info === null) {
                continue;
            }

            if ($info['prefix'] === '') {
                $unprefixedBaseValues[$info['baseValue']] = true;
                continue;
            }

            $knownPrefixedValues[$info['prefix'] . $info['baseValue']] = true;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if ($entry['important'] || $entry['property'] !== 'cursor') {
                $rewritten[] = $entry;
                continue;
            }

            $info = $this->cursorPrefixInfo($entry['value']);
            if ($info === null) {
                $rewritten[] = $entry;
                continue;
            }

            if ($info['prefix'] !== '') {
                if (!$this->cursorPrefixNeeded($info['keyword'], $info['prefix'], $targetOptions) && isset($unprefixedBaseValues[$info['baseValue']])) {
                    $changed = true;
                    continue;
                }

                $rewritten[] = $entry;
                continue;
            }

            foreach (['-webkit-', '-moz-'] as $prefix) {
                if (!$this->cursorPrefixNeeded($info['keyword'], $prefix, $targetOptions)) {
                    continue;
                }

                $prefixedValue = $info['prefixedValues'][$prefix];
                if (isset($knownPrefixedValues[$prefix . $info['baseValue']])) {
                    continue;
                }

                $rewritten[] = $this->declarationEntry('cursor', $prefixedValue);
                $knownPrefixedValues[$prefix . $info['baseValue']] = true;
                $changed = true;
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;

        return true;
    }

    /**
     * @return array{keyword:string,prefix:string,baseValue:string,prefixedValues:array<string,string>}|null
     */
    private function cursorPrefixInfo(string $value): ?array
    {
        if ($this->containsCustomPropertyReference($value)) {
            return null;
        }

        $parts = $this->splitTopLevel($value, ',');
        if ($parts === []) {
            return null;
        }

        $lastIndex = array_key_last($parts);
        $lastValue = strtolower(trim($parts[$lastIndex]));
        $prefix = '';
        $keyword = $lastValue;

        foreach (['-webkit-', '-moz-'] as $candidate) {
            if (str_starts_with($lastValue, $candidate)) {
                $prefix = $candidate;
                $keyword = substr($lastValue, strlen($candidate));
                break;
            }
        }

        if (!in_array($keyword, ['zoom-in', 'zoom-out', 'grab', 'grabbing'], true)) {
            return null;
        }

        $baseParts = $parts;
        $baseParts[$lastIndex] = $keyword;
        $baseValue = implode(',', $baseParts);
        $prefixedValues = [];
        foreach (['-webkit-', '-moz-'] as $candidate) {
            $prefixedParts = $parts;
            $prefixedParts[$lastIndex] = $candidate . $keyword;
            $prefixedValues[$candidate] = implode(',', $prefixedParts);
        }

        return [
            'keyword' => $keyword,
            'prefix' => $prefix,
            'baseValue' => $baseValue,
            'prefixedValues' => $prefixedValues,
        ];
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function cursorPrefixNeeded(string $keyword, string $prefix, array $targetOptions): bool
    {
        if ($keyword === 'zoom-in' || $keyword === 'zoom-out') {
            return $prefix === '-webkit-'
                ? ($targetOptions['cursorZoomNeedsWebkit'] ?? false)
                : ($targetOptions['cursorZoomNeedsMoz'] ?? false);
        }

        return $prefix === '-webkit-'
            ? ($targetOptions['cursorGrabNeedsWebkit'] ?? false)
            : ($targetOptions['cursorGrabNeedsMoz'] ?? false);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteAnimationTimelineShorthandEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['animationTimelineShorthandNeedsFallback'] ?? false)) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if ($entry['property'] !== 'animation') {
                $rewritten[] = $entry;
                continue;
            }

            [$animationValue, $timelineValue] = $this->splitAnimationTimelineShorthandValue($entry['value']);
            if ($timelineValue === null) {
                $rewritten[] = $entry;
                continue;
            }

            $rewritten[] = $this->entryWithValue($entry, $animationValue);
            $rewritten[] = $this->declarationEntry('animation-timeline', $timelineValue, $entry['important']);
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;

        return true;
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function splitAnimationTimelineShorthandValue(string $value): array
    {
        $animationLayers = [];
        $timelineLayers = [];
        $changed = false;

        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $tokens = $this->splitWhitespaceTopLevel($layer);
            $animationTokens = [];
            $timelineTokens = [];
            foreach ($tokens as $token) {
                if ($this->isAnimationTimelineShorthandToken($token)) {
                    $timelineTokens[] = $token;
                    $changed = true;
                    continue;
                }

                $animationTokens[] = $token;
            }

            $animationLayers[] = implode(' ', $animationTokens);
            $timelineLayers[] = $timelineTokens === [] ? 'auto' : implode(' ', $timelineTokens);
        }

        if (!$changed) {
            return [$value, null];
        }

        return [implode(',', $animationLayers), implode(',', $timelineLayers)];
    }

    private function isAnimationTimelineShorthandToken(string $token): bool
    {
        return preg_match('/^(?:scroll|view)\(/i', trim($token)) === 1;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteAnimationPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        $properties = [
            'animation',
            'animation-name',
            'animation-duration',
            'animation-delay',
            'animation-direction',
            'animation-fill-mode',
            'animation-iteration-count',
            'animation-play-state',
            'animation-timing-function',
        ];

        foreach ($properties as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['animationNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['animationNeedsMoz'] ?? false,
                '-o-' => $targetOptions['animationNeedsO'] ?? false,
            ]) || $changed;
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteBoxSizingPrefixEntries(array &$entries, array $targetOptions): bool
    {
        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'box-sizing', [
            '-webkit-' => $targetOptions['boxSizingNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['boxSizingNeedsMoz'] ?? false,
        ]);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteObjectFitPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'object-fit', [
            '-o-' => $targetOptions['objectFitNeedsO'] ?? false,
        ]);

        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'object-position', [
            '-o-' => $targetOptions['objectFitNeedsO'] ?? false,
        ]) || $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteWritingModePrefixEntries(array &$entries, array $targetOptions): bool
    {
        $needsWebkit = $targetOptions['writingModeNeedsWebkit'] ?? false;
        $needsMs = $targetOptions['writingModeNeedsMs'] ?? false;
        $hasRelevantDeclaration = false;
        $unprefixedValues = [];
        $equivalentMsValues = [];
        $prefixedValues = [
            '-webkit-' => [],
            '-ms-' => [],
        ];

        foreach ($entries as $entry) {
            if ($entry['property'] === 'writing-mode') {
                $hasRelevantDeclaration = true;
                if (!$entry['important']) {
                    $unprefixedValues[$entry['value']] = true;
                    $equivalentMsValues[$entry['value']] = true;
                    $msValue = $this->legacyMsWritingModeValue($entry['value']);
                    if ($msValue !== null) {
                        $equivalentMsValues[$msValue] = true;
                    }
                }
                continue;
            }

            if ($entry['property'] === '-webkit-writing-mode') {
                $hasRelevantDeclaration = true;
                $prefixedValues['-webkit-'][$entry['value']] = true;
                continue;
            }

            if ($entry['property'] === '-ms-writing-mode') {
                $hasRelevantDeclaration = true;
                $prefixedValues['-ms-'][$entry['value']] = true;
            }
        }

        if (!$hasRelevantDeclaration || $unprefixedValues === []) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if (
                $entry['property'] === '-webkit-writing-mode'
                && !$entry['important']
                && !$needsWebkit
                && isset($unprefixedValues[$entry['value']])
            ) {
                $changed = true;
                continue;
            }

            if (
                $entry['property'] === '-ms-writing-mode'
                && !$entry['important']
                && !$needsMs
                && isset($equivalentMsValues[$entry['value']])
            ) {
                $changed = true;
                continue;
            }

            if ($entry['property'] === 'writing-mode' && !$entry['important']) {
                if ($needsWebkit && !isset($prefixedValues['-webkit-'][$entry['value']])) {
                    $rewritten[] = $this->declarationEntry('-webkit-writing-mode', $entry['value']);
                    $prefixedValues['-webkit-'][$entry['value']] = true;
                    $changed = true;
                }

                $msValue = $this->legacyMsWritingModeValue($entry['value']);
                if ($needsMs && $msValue !== null && !isset($prefixedValues['-ms-'][$msValue])) {
                    $rewritten[] = $this->declarationEntry('-ms-writing-mode', $msValue);
                    $prefixedValues['-ms-'][$msValue] = true;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    private function legacyMsWritingModeValue(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'horizontal-tb' => 'lr-tb',
            'vertical-rl' => 'tb-rl',
            'vertical-lr' => 'tb-lr',
            default => null,
        };
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteUnicodeBidiPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $unprefixedValues = [];
        $prefixedValues = [
            '-webkit-' => [],
            '-moz-' => [],
        ];
        $hasRelevantDeclaration = false;

        foreach ($entries as $entry) {
            if ($entry['property'] !== 'unicode-bidi') {
                continue;
            }

            $info = $this->unicodeBidiPrefixInfo($entry['value']);
            if ($info === null) {
                continue;
            }

            $hasRelevantDeclaration = true;
            if ($info['prefix'] === '' && !$entry['important']) {
                $unprefixedValues[$info['keyword']] = true;
                continue;
            }

            if ($info['prefix'] !== '') {
                $prefixedValues[$info['prefix']][$info['keyword']] = true;
            }
        }

        if (!$hasRelevantDeclaration || $unprefixedValues === []) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if ($entry['property'] !== 'unicode-bidi') {
                $rewritten[] = $entry;
                continue;
            }

            $info = $this->unicodeBidiPrefixInfo($entry['value']);
            if ($info === null) {
                $rewritten[] = $entry;
                continue;
            }

            if (
                $info['prefix'] !== ''
                && !$entry['important']
                && !$this->unicodeBidiPrefixNeeded($info['keyword'], $info['prefix'], $targetOptions)
                && isset($unprefixedValues[$info['keyword']])
            ) {
                $changed = true;
                continue;
            }

            if ($info['prefix'] === '' && !$entry['important']) {
                foreach (['-webkit-', '-moz-'] as $prefix) {
                    if (!$this->unicodeBidiPrefixNeeded($info['keyword'], $prefix, $targetOptions)) {
                        continue;
                    }
                    if (isset($prefixedValues[$prefix][$info['keyword']])) {
                        continue;
                    }

                    $rewritten[] = $this->declarationEntry('unicode-bidi', $prefix . $info['keyword']);
                    $prefixedValues[$prefix][$info['keyword']] = true;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @return array{keyword:string,prefix:string}|null
     */
    private function unicodeBidiPrefixInfo(string $value): ?array
    {
        $value = strtolower(trim($value));
        $prefix = '';
        foreach (['-webkit-', '-moz-'] as $candidate) {
            if (str_starts_with($value, $candidate)) {
                $prefix = $candidate;
                $value = substr($value, strlen($candidate));
                break;
            }
        }

        if (!in_array($value, ['isolate', 'plaintext', 'isolate-override'], true)) {
            return null;
        }

        return [
            'keyword' => $value,
            'prefix' => $prefix,
        ];
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function unicodeBidiPrefixNeeded(string $keyword, string $prefix, array $targetOptions): bool
    {
        return match ($keyword) {
            'isolate' => $prefix === '-webkit-'
                ? ($targetOptions['unicodeBidiIsolateNeedsWebkit'] ?? false)
                : ($targetOptions['unicodeBidiIsolateNeedsMoz'] ?? false),
            'plaintext' => $prefix === '-webkit-'
                ? ($targetOptions['unicodeBidiPlaintextNeedsWebkit'] ?? false)
                : ($targetOptions['unicodeBidiPlaintextNeedsMoz'] ?? false),
            'isolate-override' => $prefix === '-webkit-'
                ? ($targetOptions['unicodeBidiIsolateOverrideNeedsWebkit'] ?? false)
                : ($targetOptions['unicodeBidiIsolateOverrideNeedsMoz'] ?? false),
            default => false,
        };
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteTextCompatibilityPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'text-size-adjust', [
            '-webkit-' => $targetOptions['textSizeAdjustNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['textSizeAdjustNeedsMoz'] ?? false,
            '-ms-' => $targetOptions['textSizeAdjustNeedsMs'] ?? false,
        ]);
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'hyphens', [
            '-webkit-' => $targetOptions['hyphensNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['hyphensNeedsMoz'] ?? false,
            '-ms-' => $targetOptions['hyphensNeedsMs'] ?? false,
        ]) || $changed;
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'tab-size', [
            '-moz-' => $targetOptions['tabSizeNeedsMoz'] ?? false,
            '-o-' => $targetOptions['tabSizeNeedsO'] ?? false,
        ]) || $changed;
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'text-align-last', [
            '-moz-' => $targetOptions['textAlignLastNeedsMoz'] ?? false,
        ]) || $changed;
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'text-overflow', [
            '-o-' => $targetOptions['textOverflowNeedsO'] ?? false,
        ]) || $changed;
        $changed = $this->rewriteWritingModePrefixEntries($entries, $targetOptions) || $changed;
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'text-orientation', [
            '-webkit-' => $targetOptions['textOrientationNeedsWebkit'] ?? false,
        ]) || $changed;
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'touch-action', [
            '-ms-' => $targetOptions['touchActionNeedsMs'] ?? false,
        ]) || $changed;
        $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, 'text-decoration-skip-ink', [
            '-webkit-' => $targetOptions['textDecorationSkipInkNeedsWebkit'] ?? false,
        ]) || $changed;

        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'box-decoration-break', [
            '-webkit-' => $targetOptions['boxDecorationBreakNeedsWebkit'] ?? false,
        ]) || $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteScrollSnapPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        foreach ([
            'scroll-snap-type',
            'scroll-snap-coordinate',
            'scroll-snap-destination',
            'scroll-snap-points-x',
            'scroll-snap-points-y',
        ] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['scrollSnapNeedsWebkit'] ?? false,
                '-ms-' => $targetOptions['scrollSnapNeedsMs'] ?? false,
            ]) || $changed;
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteFontTypographyPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        foreach (['font-feature-settings', 'font-variant-ligatures', 'font-language-override'] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['fontFeatureSettingsNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['fontFeatureSettingsNeedsMoz'] ?? false,
            ]) || $changed;
        }

        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'font-kerning', [
            '-webkit-' => $targetOptions['fontKerningNeedsWebkit'] ?? false,
        ]) || $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteBreakPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        foreach (['break-before', 'break-after', 'break-inside'] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['breakNeedsWebkit'] ?? false,
            ]) || $changed;
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteTransformPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        foreach (['transform', 'transform-origin'] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['transformNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['transformNeedsMoz'] ?? false,
                '-ms-' => $targetOptions['transformNeedsMs'] ?? false,
                '-o-' => $targetOptions['transformNeedsO'] ?? false,
            ]) || $changed;
        }

        foreach (['perspective', 'perspective-origin', 'transform-style'] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['perspectiveNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['perspectiveNeedsMoz'] ?? false,
            ]) || $changed;
        }

        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'backface-visibility', [
            '-webkit-' => $targetOptions['backfaceVisibilityNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['backfaceVisibilityNeedsMoz'] ?? false,
        ]) || $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewritePositionStickyPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $needsWebkit = $targetOptions['stickyNeedsWebkit'] ?? false;
        $hasSticky = false;
        $hasWebkitSticky = false;
        foreach ($entries as $entry) {
            if ($entry['property'] !== 'position' || $entry['important']) {
                continue;
            }
            $value = strtolower($entry['value']);
            if ($value === 'sticky') {
                $hasSticky = true;
            } elseif ($value === '-webkit-sticky') {
                $hasWebkitSticky = true;
            }
        }

        if (!$hasSticky) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            if ($entry['property'] === 'position' && !$entry['important']) {
                $value = strtolower($entry['value']);
                if ($value === '-webkit-sticky' && !$needsWebkit) {
                    $changed = true;
                    continue;
                }
                if ($value === 'sticky' && $needsWebkit && !$hasWebkitSticky) {
                    $rewritten[] = $this->declarationEntry('position', '-webkit-sticky');
                    $hasWebkitSticky = true;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteBackgroundClipPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $neededPrefixes = [
            '-webkit-' => $targetOptions['backgroundClipNeedsWebkit'] ?? false,
            '-ms-' => $targetOptions['backgroundClipNeedsMs'] ?? false,
        ];
        $vendorProperties = [
            '-webkit-' => '-webkit-background-clip',
            '-ms-' => '-ms-background-clip',
        ];
        $prefixedTextValues = [
            '-webkit-' => false,
            '-ms-' => false,
        ];
        $hasTextClip = false;
        $hasStandaloneTextClip = false;

        foreach ($entries as $entry) {
            if ($entry['important']) {
                continue;
            }

            if ($entry['property'] === 'background-clip' && strtolower(trim($entry['value'])) === 'text') {
                $hasTextClip = true;
                $hasStandaloneTextClip = true;
                continue;
            }

            if ($entry['property'] === 'background' && $this->backgroundValueWithoutTextClip($entry['value']) !== null) {
                $hasTextClip = true;
                continue;
            }

            $prefix = $this->uiPrefixForProperty($entry['property'], $vendorProperties);
            if ($prefix !== null && strtolower(trim($entry['value'])) === 'text') {
                $prefixedTextValues[$prefix] = true;
            }
        }

        if (!$hasTextClip) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            $prefix = $this->uiPrefixForProperty($entry['property'], $vendorProperties);
            if (
                $prefix !== null
                && !$entry['important']
                && strtolower(trim($entry['value'])) === 'text'
                && !($neededPrefixes[$prefix] ?? false)
                && $hasTextClip
            ) {
                $changed = true;
                continue;
            }

            if ($entry['property'] === 'background' && !$entry['important']) {
                $background = $this->backgroundValueWithoutTextClip($entry['value']);
                if ($background !== null && in_array(true, $neededPrefixes, true)) {
                    if ($background !== '') {
                        $rewritten[] = $this->entryWithValue($entry, $background);
                    }
                    foreach ($neededPrefixes as $neededPrefix => $needed) {
                        if ($needed && !$prefixedTextValues[$neededPrefix]) {
                            $rewritten[] = $this->declarationEntry($vendorProperties[$neededPrefix], 'text');
                            $prefixedTextValues[$neededPrefix] = true;
                        }
                    }
                    if (!$hasStandaloneTextClip) {
                        $rewritten[] = $this->declarationEntry('background-clip', 'text');
                    }
                    $changed = true;
                    continue;
                }
            }

            if ($entry['property'] === 'background-clip' && !$entry['important'] && strtolower(trim($entry['value'])) === 'text') {
                foreach ($neededPrefixes as $neededPrefix => $needed) {
                    if ($needed && !$prefixedTextValues[$neededPrefix]) {
                        $rewritten[] = $this->declarationEntry($vendorProperties[$neededPrefix], 'text');
                        $prefixedTextValues[$neededPrefix] = true;
                        $changed = true;
                    }
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    private function backgroundValueWithoutTextClip(string $value): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        $filtered = [];
        $removed = false;
        foreach ($tokens as $token) {
            if (strtolower(trim($token)) === 'text') {
                $removed = true;
                continue;
            }

            $filtered[] = $token;
        }

        return $removed ? implode(' ', $filtered) : null;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteClipPathPrefixEntries(array &$entries, array $targetOptions): bool
    {
        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'clip-path', [
            '-webkit-' => $targetOptions['clipPathNeedsWebkit'] ?? false,
        ]);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteBorderImagePrefixEntries(array &$entries, array $targetOptions): bool
    {
        return $this->rewriteVendorPrefixedDeclarationGroup($entries, 'border-image', [
            '-webkit-' => $targetOptions['borderImageNeedsWebkit'] ?? false,
            '-moz-' => $targetOptions['borderImageNeedsMoz'] ?? false,
            '-o-' => $targetOptions['borderImageNeedsO'] ?? false,
        ]);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteSizingKeywordPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $logicalFallback = $targetOptions['logicalSizeNeedsFallback'] ?? false;
        $metadata = [];
        $hasSizingValue = false;
        $prefixedValues = [];
        $standardRemainder = [];

        foreach ($entries as $index => $entry) {
            $outputProperty = $this->sizingKeywordOutputProperty($entry['property'], $logicalFallback);
            $value = $entry['important'] ? null : $this->sizingKeywordValueInfo($entry['value']);
            $metadata[$index] = [$outputProperty, $value];

            if ($outputProperty === null || $value === null) {
                continue;
            }

            $hasSizingValue = true;
            if ($value['kind'] === 'standard') {
                $key = $outputProperty . '|' . $value['keyword'];
                $standardRemainder[$key] = ($standardRemainder[$key] ?? 0) + 1;
            } elseif ($value['kind'] === 'prefixed') {
                $prefixedValues[$outputProperty][$value['value']] = true;
            }
        }

        if (!$hasSizingValue) {
            return false;
        }

        $changed = false;
        $fallbackSeen = [];
        $rewritten = [];

        foreach ($entries as $index => $entry) {
            [$outputProperty, $value] = $metadata[$index];
            if ($outputProperty === null) {
                $rewritten[] = $entry;
                continue;
            }

            if ($value !== null && $outputProperty !== $entry['property']) {
                $entry = $this->declarationEntry($outputProperty, $entry['value'], $entry['important']);
                $changed = true;
            }

            if ($value === null) {
                if (!$entry['important']) {
                    $fallbackSeen[$outputProperty] = true;
                }
                $rewritten[] = $entry;
                continue;
            }

            if ($value['kind'] === 'logical-only') {
                $fallbackSeen[$outputProperty] = true;
                $rewritten[] = $entry;
                continue;
            }

            if ($value['kind'] === 'prefixed') {
                $key = $outputProperty . '|' . $value['keyword'];
                if (
                    $value['keyword'] !== 'stretch'
                    && !$this->sizingKeywordNeedsPrefix($value['keyword'], $value['prefix'], $targetOptions)
                    && ($standardRemainder[$key] ?? 0) > 0
                ) {
                    $changed = true;
                    continue;
                }

                $rewritten[] = $entry;
                continue;
            }

            $key = $outputProperty . '|' . $value['keyword'];
            $standardRemainder[$key] = max(0, ($standardRemainder[$key] ?? 0) - 1);

            if (!($fallbackSeen[$outputProperty] ?? false)) {
                foreach ($this->sizingKeywordPrefixedValues($value['keyword']) as $prefix => $prefixedValue) {
                    if (!$this->sizingKeywordNeedsPrefix($value['keyword'], $prefix, $targetOptions)) {
                        continue;
                    }
                    if (isset($prefixedValues[$outputProperty][$prefixedValue])) {
                        continue;
                    }

                    $rewritten[] = $this->declarationEntry($outputProperty, $prefixedValue);
                    $prefixedValues[$outputProperty][$prefixedValue] = true;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
            $fallbackSeen[$outputProperty] = true;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLogicalSizeFallbackEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['logicalSizeNeedsFallback'] ?? false)) {
            return false;
        }

        $changed = false;
        $rewritten = [];
        $pending = [];
        $pendingIndex = 0;

        $flushPending = function () use (&$pending, &$rewritten): void {
            if ($pending === []) {
                return;
            }

            usort($pending, static function (array $left, array $right): int {
                $rank = $left['rank'] <=> $right['rank'];

                return $rank !== 0 ? $rank : $left['index'] <=> $right['index'];
            });

            foreach ($pending as $pendingEntry) {
                $rewritten[] = $pendingEntry['entry'];
            }

            $pending = [];
        };

        foreach ($entries as $entry) {
            $physicalProperty = $this->logicalSizePhysicalProperty($entry['property']);
            if ($physicalProperty === null) {
                $flushPending();
                $rewritten[] = $entry;
                continue;
            }

            $pending[] = [
                'entry' => $this->declarationEntry($physicalProperty, $entry['value'], $entry['important']),
                'rank' => $this->logicalSizePhysicalOrder($physicalProperty),
                'index' => $pendingIndex++,
            ];
            $changed = true;
        }

        $flushPending();

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    private function logicalSizePhysicalProperty(string $property): ?string
    {
        return match ($property) {
            'block-size' => 'height',
            'min-block-size' => 'min-height',
            'max-block-size' => 'max-height',
            'inline-size' => 'width',
            'min-inline-size' => 'min-width',
            'max-inline-size' => 'max-width',
            default => null,
        };
    }

    private function logicalSizePhysicalOrder(string $property): int
    {
        return match ($property) {
            'height' => 0,
            'min-height' => 1,
            'max-height' => 2,
            'width' => 3,
            'min-width' => 4,
            'max-width' => 5,
            default => 99,
        };
    }

    private function sizingKeywordOutputProperty(string $property, bool $logicalFallback): ?string
    {
        return match ($property) {
            'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height' => $property,
            'inline-size' => $logicalFallback ? 'width' : 'inline-size',
            'block-size' => $logicalFallback ? 'height' : 'block-size',
            'min-inline-size' => $logicalFallback ? 'min-width' : 'min-inline-size',
            'min-block-size' => $logicalFallback ? 'min-height' : 'min-block-size',
            'max-inline-size' => $logicalFallback ? 'max-width' : 'max-inline-size',
            'max-block-size' => $logicalFallback ? 'max-height' : 'max-block-size',
            default => null,
        };
    }

    /**
     * @return array{kind:string,keyword:string,value?:string,prefix?:string}|null
     */
    private function sizingKeywordValueInfo(string $value): ?array
    {
        $lower = strtolower(trim($value));

        return match ($lower) {
            'min-content' => ['kind' => 'standard', 'keyword' => 'min-content'],
            'max-content' => ['kind' => 'standard', 'keyword' => 'max-content'],
            'fit-content' => ['kind' => 'standard', 'keyword' => 'fit-content'],
            'stretch' => ['kind' => 'standard', 'keyword' => 'stretch'],
            '-webkit-min-content' => ['kind' => 'prefixed', 'keyword' => 'min-content', 'value' => '-webkit-min-content', 'prefix' => '-webkit-'],
            '-moz-min-content' => ['kind' => 'prefixed', 'keyword' => 'min-content', 'value' => '-moz-min-content', 'prefix' => '-moz-'],
            '-webkit-max-content' => ['kind' => 'prefixed', 'keyword' => 'max-content', 'value' => '-webkit-max-content', 'prefix' => '-webkit-'],
            '-moz-max-content' => ['kind' => 'prefixed', 'keyword' => 'max-content', 'value' => '-moz-max-content', 'prefix' => '-moz-'],
            '-webkit-fit-content' => ['kind' => 'prefixed', 'keyword' => 'fit-content', 'value' => '-webkit-fit-content', 'prefix' => '-webkit-'],
            '-moz-fit-content' => ['kind' => 'prefixed', 'keyword' => 'fit-content', 'value' => '-moz-fit-content', 'prefix' => '-moz-'],
            '-webkit-fill-available' => ['kind' => 'prefixed', 'keyword' => 'stretch', 'value' => '-webkit-fill-available', 'prefix' => '-webkit-'],
            '-moz-available' => ['kind' => 'prefixed', 'keyword' => 'stretch', 'value' => '-moz-available', 'prefix' => '-moz-'],
            default => str_starts_with($lower, 'fit-content(')
                ? ['kind' => 'logical-only', 'keyword' => 'fit-content-function']
                : null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function sizingKeywordPrefixedValues(string $keyword): array
    {
        return match ($keyword) {
            'min-content' => ['-webkit-' => '-webkit-min-content', '-moz-' => '-moz-min-content'],
            'max-content' => ['-webkit-' => '-webkit-max-content', '-moz-' => '-moz-max-content'],
            'fit-content' => ['-webkit-' => '-webkit-fit-content', '-moz-' => '-moz-fit-content'],
            'stretch' => ['-webkit-' => '-webkit-fill-available', '-moz-' => '-moz-available'],
            default => [],
        };
    }

    private function sizingKeywordNeedsPrefix(string $keyword, string $prefix, array $targetOptions): bool
    {
        if ($prefix === '-webkit-') {
            return match ($keyword) {
                'min-content', 'max-content' => $targetOptions['sizingMinMaxNeedsWebkit'] ?? false,
                'fit-content' => $targetOptions['sizingFitContentNeedsWebkit'] ?? false,
                'stretch' => $targetOptions['sizingStretchNeedsWebkit'] ?? false,
                default => false,
            };
        }

        if ($prefix === '-moz-') {
            return match ($keyword) {
                'min-content', 'max-content' => $targetOptions['sizingMinMaxNeedsMoz'] ?? false,
                'fit-content' => $targetOptions['sizingFitContentNeedsMoz'] ?? false,
                'stretch' => $targetOptions['sizingStretchNeedsMoz'] ?? false,
                default => false,
            };
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $neededPrefixes
     */
    private function rewriteVendorPrefixedDeclarationGroup(array &$entries, string $baseProperty, array $neededPrefixes): bool
    {
        $vendorProperties = [];
        foreach ($neededPrefixes as $prefix => $_needed) {
            $vendorProperties[$prefix] = $prefix . $baseProperty;
        }

        $unprefixedValues = [];
        $prefixedValues = [];
        $hasRelevantDeclaration = false;
        foreach ($entries as $entry) {
            if ($entry['property'] === $baseProperty) {
                $hasRelevantDeclaration = true;
                if (!$entry['important']) {
                    $unprefixedValues[$entry['value']] = true;
                }
                continue;
            }

            $prefix = $this->uiPrefixForProperty($entry['property'], $vendorProperties);
            if ($prefix === null) {
                continue;
            }

            $hasRelevantDeclaration = true;
            $prefixedValues[$prefix][$entry['value']] = true;
        }

        if (!$hasRelevantDeclaration || $unprefixedValues === []) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            $prefix = $this->uiPrefixForProperty($entry['property'], $vendorProperties);
            if (
                $prefix !== null
                && !$entry['important']
                && !($neededPrefixes[$prefix] ?? false)
                && isset($unprefixedValues[$entry['value']])
            ) {
                $changed = true;
                continue;
            }

            if ($entry['property'] === $baseProperty && !$entry['important']) {
                foreach ($neededPrefixes as $neededPrefix => $needed) {
                    if (!$needed || isset($prefixedValues[$neededPrefix][$entry['value']])) {
                        continue;
                    }

                    $rewritten[] = $this->declarationEntry($vendorProperties[$neededPrefix], $entry['value']);
                    $prefixedValues[$neededPrefix][$entry['value']] = true;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @param array<string, string> $vendorProperties
     */
    private function uiPrefixForProperty(string $property, array $vendorProperties): ?string
    {
        foreach ($vendorProperties as $prefix => $vendorProperty) {
            if ($property === $vendorProperty) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLightDarkFallbackEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['lightDarkNeedsFallback'] ?? false)) {
            return false;
        }

        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important'] || stripos($entry['value'], 'light-dark(') === false) {
                $rewritten[] = $entry;
                continue;
            }

            $knownNestedFallbacks = $this->knownNestedLightDarkFallbackValues($entry['value']);
            if ($knownNestedFallbacks !== null) {
                foreach ($knownNestedFallbacks as $fallback) {
                    $rewritten[] = $this->entryWithValue($entry, $fallback);
                }
                $changed = true;
                continue;
            }

            $srgbFallback = $this->rewriteLightDarkFallbackValue(
                $entry['value'],
                fn (string $arm): string => $this->advancedColorFallbackValue($arm) ?? $arm
            );
            if ($srgbFallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            $rewritten[] = $this->entryWithValue($entry, $srgbFallback);
            $labFallback = $this->rewriteLightDarkFallbackValue(
                $entry['value'],
                fn (string $arm): string => $this->advancedColorLabTargetValue($arm)
                    ?? $this->advancedColorLabFallbackValue($arm, true)
                    ?? $arm
            );
            if ($labFallback !== null && $labFallback !== $srgbFallback) {
                $rewritten[] = $this->entryWithValue($entry, $labFallback);
            }

            $changed = true;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteBackgroundSizeOriginPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $changed = false;
        foreach (['background-size', 'background-origin'] as $property) {
            $changed = $this->rewriteVendorPrefixedDeclarationGroup($entries, $property, [
                '-webkit-' => $targetOptions['backgroundSizeOriginNeedsWebkit'] ?? false,
                '-moz-' => $targetOptions['backgroundSizeOriginNeedsMoz'] ?? false,
                '-o-' => $targetOptions['backgroundSizeOriginNeedsO'] ?? false,
            ]) || $changed;
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteOverflowShorthandFallbackEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['overflowShorthandNeedsLonghandFallback'] ?? false)) {
            return false;
        }

        $rewritten = [];
        $changed = false;
        $count = count($entries);
        for ($index = 0; $index < $count; $index++) {
            $entry = $entries[$index];
            if ($entry['property'] !== 'overflow') {
                $rewritten[] = $entry;
                continue;
            }

            $parts = $this->overflowShorthandParts($entry['value']);
            if ($parts === null) {
                $rewritten[] = $entry;
                continue;
            }

            $x = $parts[0];
            $y = $parts[1] ?? $parts[0];
            $next = $entries[$index + 1] ?? null;
            if (
                $next !== null
                && !$entry['important']
                && !$next['important']
                && in_array($next['property'], ['overflow-x', 'overflow-y'], true)
                && $this->isOverflowKeyword($next['value'])
            ) {
                if ($next['property'] === 'overflow-x') {
                    $x = $next['value'];
                } else {
                    $y = $next['value'];
                }

                $index++;
                $changed = true;
            }

            if ($x === $y) {
                $rewritten[] = $this->entryWithValue($entry, $x);
                $changed = $changed || $entry['value'] !== $x;
                continue;
            }

            $rewritten[] = $this->declarationEntry('overflow-x', $x, $entry['important']);
            $rewritten[] = $this->declarationEntry('overflow-y', $y, $entry['important']);
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;
        return true;
    }

    /**
     * @return list<string>|null
     */
    private function overflowShorthandParts(string $value): ?array
    {
        $parts = $this->splitWhitespaceTopLevel($value);
        if (count($parts) < 1 || count($parts) > 2) {
            return null;
        }

        foreach ($parts as $part) {
            if (!$this->isOverflowKeyword($part)) {
                return null;
            }
        }

        return $parts;
    }

    private function isOverflowKeyword(string $value): bool
    {
        return in_array(strtolower($value), ['visible', 'hidden', 'clip', 'scroll', 'auto'], true);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteLightDarkAdvancedColorSerializationEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['lightDarkNormalizeAdvancedColor'] ?? false)) {
            return false;
        }

        $changed = false;
        foreach ($entries as &$entry) {
            if ($entry['important'] || stripos($entry['value'], 'light-dark(') === false) {
                continue;
            }

            $normalized = $this->normalizeAdvancedColorSerialization($entry['value']);
            if ($normalized !== $entry['value']) {
                $entry['value'] = $normalized;
                $changed = true;
            }
        }
        unset($entry);

        return $changed;
    }

    private function normalizeAdvancedColorSerialization(string $value): string
    {
        return preg_replace_callback(
            '/\b(lab|lch|oklab|oklch|color)\(([^()]*)\)/i',
            function (array $matches): string {
                $arguments = preg_replace_callback(
                    '/(?<![-_a-zA-Z])[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:e[-+]?\d+)?%?/i',
                    fn (array $number): string => $this->formatCssColorNumber($number[0]),
                    $matches[2]
                ) ?? $matches[2];

                return strtolower($matches[1]) . '(' . $arguments . ')';
            },
            $value
        ) ?? $value;
    }

    private function formatCssColorNumber(string $token): string
    {
        $hasPercent = str_ends_with($token, '%');
        $number = $hasPercent ? substr($token, 0, -1) : $token;
        $formatted = sprintf('%.6g', (float) $number);
        $formatted = str_replace('E', 'e', $formatted);
        $formatted = preg_replace('/e\+/', 'e', $formatted) ?? $formatted;
        if (str_starts_with($formatted, '0.')) {
            $formatted = substr($formatted, 1);
        } elseif (str_starts_with($formatted, '-0.')) {
            $formatted = '-' . substr($formatted, 2);
        }

        return $formatted . ($hasPercent ? '%' : '');
    }

    /**
     * @return non-empty-list<string>|null
     */
    private function knownNestedLightDarkFallbackValues(string $value): ?array
    {
        $relative = $this->knownRelativeLightDarkFallbackValues($value);
        if ($relative !== null) {
            return $relative;
        }

        $mixed = $this->knownColorMixLightDarkFallbackValue($value);
        if ($mixed !== null) {
            return [$mixed];
        }

        return null;
    }

    /**
     * @return non-empty-list<string>|null
     */
    private function knownRelativeLightDarkFallbackValues(string $value): ?array
    {
        if (preg_match('/^(rgb|color)\(/i', $value, $matches) !== 1) {
            return null;
        }

        [$function, $offset] = $this->readFunctionRaw($value, 0);
        if ($offset !== strlen($value) - 1) {
            return null;
        }

        $functionName = strtolower($matches[1]);
        $arguments = trim(substr($function, strlen($functionName) + 1, -1));
        if (stripos($arguments, 'from ') !== 0) {
            return null;
        }

        $arguments = trim(substr($arguments, 5));
        if (stripos($arguments, 'light-dark(') !== 0) {
            return null;
        }

        [$lightDark, $lightDarkOffset] = $this->readFunctionRaw($arguments, 0);
        $arms = $this->splitTopLevel(substr($lightDark, strlen('light-dark('), -1), ',');
        if (count($arms) !== 2) {
            return null;
        }

        $rest = trim(substr($arguments, $lightDarkOffset + 1));
        $rest = preg_replace('/\s*\/\s*/', '/', preg_replace('/\s+/', ' ', $rest) ?? $rest) ?? $rest;

        if ($functionName === 'rgb') {
            if (preg_match('/^r g b\/(.+)$/', $rest, $matches) !== 1) {
                return null;
            }

            return $this->relativeRgbLightDarkFallback($arms[0], $arms[1], trim($matches[1]), false);
        }

        if ($functionName !== 'color' || preg_match('/^srgb r g b\/(.+)$/', $rest, $matches) !== 1) {
            return null;
        }

        return $this->relativeRgbLightDarkFallback($arms[0], $arms[1], trim($matches[1]), true);
    }

    /**
     * @return non-empty-list<string>|null
     */
    private function relativeRgbLightDarkFallback(string $light, string $dark, string $alpha, bool $includeSrgbColorFunction): ?array
    {
        $lightRgb = $this->knownSrgbColorChannels($light);
        $darkRgb = $this->knownSrgbColorChannels($dark);
        if ($lightRgb === null || $darkRgb === null) {
            return null;
        }

        if ($alpha === '10%') {
            $srgb = 'var(--lightningcss-light,' . $this->hexColorWithAlpha($lightRgb, 0.1) . ') '
                . 'var(--lightningcss-dark,' . $this->hexColorWithAlpha($darkRgb, 0.1) . ')';
            if (!$includeSrgbColorFunction) {
                return [$srgb];
            }

            return [
                $srgb,
                'var(--lightningcss-light,' . $this->srgbColorFunction($lightRgb, 0.1) . ') '
                    . 'var(--lightningcss-dark,' . $this->srgbColorFunction($darkRgb, 0.1) . ')',
            ];
        }

        if (preg_match('/^var\(--[-_a-zA-Z0-9]+\)$/', $alpha) === 1) {
            return [
                'var(--lightningcss-light,' . $this->rgbRelativeResult($lightRgb, $alpha) . ') '
                    . 'var(--lightningcss-dark,' . $this->rgbRelativeResult($darkRgb, $alpha) . ')',
            ];
        }

        return null;
    }

    private function knownColorMixLightDarkFallbackValue(string $value): ?string
    {
        if (stripos($value, 'color-mix(') !== 0) {
            return null;
        }

        [$function, $offset] = $this->readFunctionRaw($value, 0);
        if ($offset !== strlen($value) - 1) {
            return null;
        }

        $arguments = substr($function, strlen('color-mix('), -1);
        $parts = $this->splitTopLevel($arguments, ',');
        if (count($parts) !== 3 || strtolower($parts[0]) !== 'in srgb') {
            return null;
        }

        $first = $this->readLightDarkColorArms($parts[1]);
        $second = $this->readLightDarkColorArms($parts[2]);
        if ($first === null || $second === null) {
            return null;
        }

        $light = $this->mixSrgbColors($first['light'], $second['light']);
        $dark = $this->mixSrgbColors($first['dark'], $second['dark']);
        if ($light === null || $dark === null) {
            return null;
        }

        return 'var(--lightningcss-light,' . $this->hexColor($light) . ') '
            . 'var(--lightningcss-dark,' . $this->hexColor($dark) . ')';
    }

    /**
     * @return array{light:array{0:int,1:int,2:int},dark:array{0:int,1:int,2:int}}|null
     */
    private function readLightDarkColorArms(string $value): ?array
    {
        $value = trim($value);
        if (stripos($value, 'light-dark(') !== 0) {
            return null;
        }

        [$function, $offset] = $this->readFunctionRaw($value, 0);
        if ($offset !== strlen($value) - 1) {
            return null;
        }

        $arms = $this->splitTopLevel(substr($function, strlen('light-dark('), -1), ',');
        if (count($arms) !== 2) {
            return null;
        }

        $light = $this->knownSrgbColorChannels($arms[0]);
        $dark = $this->knownSrgbColorChannels($arms[1]);
        if ($light === null || $dark === null) {
            return null;
        }

        return [
            'light' => $light,
            'dark' => $dark,
        ];
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    private function knownSrgbColorChannels(string $color): ?array
    {
        return match (strtolower(trim($color))) {
            '#ff0',
            '#ffff00',
            'yellow' => [255, 255, 0],
            '#f00',
            '#ff0000',
            'red' => [255, 0, 0],
            '#ffc0cb',
            'pink' => [255, 192, 203],
            default => null,
        };
    }

    /**
     * @param array{0:int,1:int,2:int} $left
     * @param array{0:int,1:int,2:int} $right
     * @return array{0:int,1:int,2:int}
     */
    private function mixSrgbColors(array $left, array $right): array
    {
        return [
            (int) round(($left[0] + $right[0]) / 2),
            (int) round(($left[1] + $right[1]) / 2),
            (int) round(($left[2] + $right[2]) / 2),
        ];
    }

    /**
     * @param array{0:int,1:int,2:int} $channels
     */
    private function hexColor(array $channels): string
    {
        return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
    }

    /**
     * @param array{0:int,1:int,2:int} $channels
     */
    private function hexColorWithAlpha(array $channels, float $alpha): string
    {
        return sprintf('#%02x%02x%02x%02x', $channels[0], $channels[1], $channels[2], (int) round($alpha * 255));
    }

    /**
     * @param array{0:int,1:int,2:int} $channels
     */
    private function rgbRelativeResult(array $channels, string $alpha): string
    {
        return 'rgb(' . $channels[0] . ' ' . $channels[1] . ' ' . $channels[2] . ' / ' . $alpha . ')';
    }

    /**
     * @param array{0:int,1:int,2:int} $channels
     */
    private function srgbColorFunction(array $channels, float $alpha): string
    {
        return 'color(srgb '
            . $this->formatUnitColorChannel($channels[0])
            . ' '
            . $this->formatUnitColorChannel($channels[1])
            . ' '
            . $this->formatUnitColorChannel($channels[2])
            . ' / '
            . $this->formatAlpha($alpha)
            . ')';
    }

    private function formatUnitColorChannel(int $channel): string
    {
        if ($channel === 0 || $channel === 255) {
            return $channel === 0 ? '0' : '1';
        }

        return rtrim(rtrim(sprintf('%.6F', $channel / 255), '0'), '.');
    }

    private function formatAlpha(float $alpha): string
    {
        $value = rtrim(rtrim(sprintf('%.6F', $alpha), '0'), '.');

        return str_starts_with($value, '0.') ? substr($value, 1) : $value;
    }

    /**
     * @param callable(string): string $armMapper
     */
    private function rewriteLightDarkFallbackValue(string $value, callable $armMapper): ?string
    {
        $output = '';
        $quote = null;
        $parenDepth = 0;
        $matched = false;
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

            if ($parenDepth === 0 && $this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $value[$i + strlen($identifier)] ?? '';
                if (strtolower($identifier) === 'light-dark' && $next === '(') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $arguments = substr($function, strlen($identifier) + 1, -1);
                    $parts = $this->splitTopLevel($arguments, ',');
                    if (count($parts) !== 2) {
                        $output .= $function;
                        $i = $offset;
                        continue;
                    }

                    $output .= 'var(--lightningcss-light,' . $armMapper($parts[0]) . ') '
                        . 'var(--lightningcss-dark,' . $armMapper($parts[1]) . ')';
                    $matched = true;
                    $i = $offset;
                    continue;
                }

                $output .= $identifier;
                $i += strlen($identifier) - 1;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            }

            $output .= $char;
        }

        return $matched ? $output : null;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     * @param list<string> $supportRules
     */
    private function rewriteMaskPrefixEntries(array &$entries, array $targetOptions, ?string $supportSelector = null, array &$supportRules = []): bool
    {
        $needsWebkit = $targetOptions['maskNeedsWebkit'] ?? false;
        $changed = !$needsWebkit && $this->dropUnneededMaskPrefixEntries($entries);
        $drop = [];
        $insertions = [];

        $hasWebkitMask = false;
        $hasWebkitMaskImage = false;
        foreach ($entries as $entry) {
            $hasWebkitMask = $hasWebkitMask || $entry['property'] === '-webkit-mask';
            $hasWebkitMaskImage = $hasWebkitMaskImage || $entry['property'] === '-webkit-mask-image';
        }

        foreach ($needsWebkit ? ['modern', 'webkit'] : ['modern'] as $family) {
            $plan = $this->planMaskBorderComposition($entries, $family, $needsWebkit);
            if ($plan === null) {
                continue;
            }

            foreach ($plan['drop'] as $index) {
                $drop[$index] = true;
            }
            $insertions[$plan['replaceAt']] = array_merge($insertions[$plan['replaceAt']] ?? [], $plan['entries']);
            $changed = true;
        }

        $plan = $this->planMaskLayerComposition($entries, $needsWebkit);
        if ($plan !== null) {
            foreach ($plan['drop'] as $index) {
                $drop[$index] = true;
            }
            $insertions[$plan['replaceAt']] = array_merge($insertions[$plan['replaceAt']] ?? [], $plan['entries']);
            $changed = true;
        }

        $rewritten = [];
        foreach ($entries as $index => $entry) {
            foreach ($insertions[$index] ?? [] as $inserted) {
                $rewritten[] = $inserted;
            }

            if (isset($drop[$index])) {
                continue;
            }

            $result = $this->rewriteSingleMaskPrefixEntry($entry, $hasWebkitMask, $hasWebkitMaskImage, $needsWebkit);
            [$mapped, $entryChanged] = $result;
            $supportEntries = $result[2] ?? [];
            if ($supportSelector !== null && $supportEntries !== []) {
                $supportRules[] = $this->supportsLabRule($supportSelector, $supportEntries);
            }
            array_push($rewritten, ...$mapped);
            $changed = $changed || $entryChanged;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function dropUnneededMaskPrefixEntries(array &$entries): bool
    {
        $unprefixed = [];
        foreach ($entries as $entry) {
            if (!$entry['important']) {
                $unprefixed[$entry['property']] = true;
            }
        }

        $rewritten = [];
        $changed = false;
        foreach ($entries as $entry) {
            $baseProperty = $this->unprefixedMaskPropertyForWebkit($entry['property']);
            if ($baseProperty !== null && !$entry['important'] && isset($unprefixed[$baseProperty])) {
                $changed = true;
                continue;
            }

            $rewritten[] = $entry;
        }

        if (!$changed) {
            return false;
        }

        $entries = $rewritten;

        return true;
    }

    private function unprefixedMaskPropertyForWebkit(string $property): ?string
    {
        return match ($property) {
            '-webkit-mask' => 'mask',
            '-webkit-mask-image' => 'mask-image',
            '-webkit-mask-position' => 'mask-position',
            '-webkit-mask-size' => 'mask-size',
            '-webkit-mask-repeat' => 'mask-repeat',
            '-webkit-mask-origin' => 'mask-origin',
            '-webkit-mask-clip' => 'mask-clip',
            '-webkit-mask-composite' => 'mask-composite',
            '-webkit-mask-source-type' => 'mask-mode',
            '-webkit-mask-box-image' => 'mask-border',
            '-webkit-mask-box-image-source' => 'mask-border-source',
            '-webkit-mask-box-image-slice' => 'mask-border-slice',
            '-webkit-mask-box-image-width' => 'mask-border-width',
            '-webkit-mask-box-image-outset' => 'mask-border-outset',
            '-webkit-mask-box-image-repeat' => 'mask-border-repeat',
            default => null,
        };
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array<string, bool> $targetOptions
     */
    private function rewriteFilterPrefixEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];
        $hasWebkitFilter = false;
        $hasWebkitBackdropFilter = false;
        $unprefixedValues = [
            'filter' => [],
            'backdrop-filter' => [],
        ];

        foreach ($entries as $entry) {
            $hasWebkitFilter = $hasWebkitFilter || $entry['property'] === '-webkit-filter';
            $hasWebkitBackdropFilter = $hasWebkitBackdropFilter || $entry['property'] === '-webkit-backdrop-filter';
            if (isset($unprefixedValues[$entry['property']])) {
                $unprefixedValues[$entry['property']][$entry['value']] = true;
            }
        }

        foreach ($entries as $entry) {
            if ($entry['property'] === '-webkit-filter'
                && !($targetOptions['filterNeedsWebkit'] ?? false)
                && isset($unprefixedValues['filter'][$entry['value']])
            ) {
                $changed = true;
                continue;
            }
            if ($entry['property'] === '-webkit-backdrop-filter'
                && !($targetOptions['backdropFilterNeedsWebkit'] ?? false)
                && isset($unprefixedValues['backdrop-filter'][$entry['value']])
            ) {
                $changed = true;
                continue;
            }

            if ($entry['important'] || !in_array($entry['property'], ['filter', 'backdrop-filter'], true)) {
                $rewritten[] = $entry;
                continue;
            }

            $prefixedProperty = $entry['property'] === 'filter' ? '-webkit-filter' : '-webkit-backdrop-filter';
            $hasPrefixed = $entry['property'] === 'filter' ? $hasWebkitFilter : $hasWebkitBackdropFilter;
            $needsPrefixed = $entry['property'] === 'filter'
                ? ($targetOptions['filterNeedsWebkit'] ?? false)
                : ($targetOptions['backdropFilterNeedsWebkit'] ?? false);
            $fallback = $this->advancedColorFallbackValue($entry['value']);
            if ($fallback === null) {
                if ($needsPrefixed && !$hasPrefixed) {
                    $rewritten[] = $this->declarationEntry($prefixedProperty, $entry['value']);
                    $changed = true;
                }
                $rewritten[] = $entry;
                continue;
            }

            if ($needsPrefixed && !$hasPrefixed) {
                $rewritten[] = $this->declarationEntry($prefixedProperty, $fallback);
            }
            $rewritten[] = $this->entryWithValue($entry, $fallback);
            $changed = true;

            $hasCustomPropertyReference = $this->containsCustomPropertyReference($entry['value']);
            $labFallback = $this->advancedColorLabFallbackValue($entry['value'], $hasCustomPropertyReference);
            if ($labFallback !== null && $hasCustomPropertyReference) {
                $supportEntries = [];
                if ($needsPrefixed && !$hasPrefixed) {
                    $supportEntries[] = $this->declarationEntry($prefixedProperty, $labFallback);
                }
                $supportEntries[] = $this->entryWithValue($entry, $labFallback);
                $supportRules[] = $this->supportsLabRule($selectors, $supportEntries);
                continue;
            }

            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array{boxShadowNeedsWebkit:bool,boxShadowNeedsMoz:bool,boxShadowDropLegacyPrefixes:bool,boxShadowSupportsAdvancedColor:bool,boxShadowDropOverriddenFallbacks:bool,advancedColorNeedsSrgbFallback:bool,advancedColorUsesP3Fallback:bool} $targetOptions
     */
    private function rewriteBoxShadowPrefixEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];
        $hasWebkitBoxShadow = false;
        $hasMozBoxShadow = false;

        foreach ($entries as $entry) {
            $hasWebkitBoxShadow = $hasWebkitBoxShadow || $entry['property'] === '-webkit-box-shadow';
            $hasMozBoxShadow = $hasMozBoxShadow || $entry['property'] === '-moz-box-shadow';
        }

        foreach ($entries as $entry) {
            if ($entry['important'] || !$this->isBoxShadowProperty($entry['property'])) {
                $rewritten[] = $entry;
                continue;
            }

            if (($targetOptions['boxShadowDropLegacyPrefixes']
                    || ($entry['property'] === '-webkit-box-shadow' && !$targetOptions['boxShadowNeedsWebkit'])
                    || ($entry['property'] === '-moz-box-shadow' && !$targetOptions['boxShadowNeedsMoz']))
                && $this->isLegacyBoxShadowProperty($entry['property'])
                && $this->hasMatchingUnprefixedBoxShadow($entries, $entry['value'])
            ) {
                $changed = true;
                continue;
            }

            if ($this->isLegacyBoxShadowProperty($entry['property'])) {
                $value = $this->expandLegacyAlphaHexColors($entry['value']);
                $changed = $changed || $value !== $entry['value'];
                $entry['value'] = $value;
                $rewritten[] = $entry;
                continue;
            }

            if ($targetOptions['boxShadowDropOverriddenFallbacks'] && !$this->containsCustomPropertyReference($entry['value'])) {
                [$rewritten, $dropped] = $this->dropPreviousBoxShadowFallbacks($rewritten);
                $changed = $changed || $dropped;
            }

            $fallback = $targetOptions['boxShadowSupportsAdvancedColor']
                ? null
                : $this->advancedColorFallbackValue($entry['value']);
            if ($fallback !== null) {
                $hasPreviousFallback = $this->hasPreviousUnprefixedBoxShadow($rewritten);
                $hasCustomPropertyReference = $this->containsCustomPropertyReference($entry['value']);
                $p3Fallback = $hasCustomPropertyReference || !$targetOptions['advancedColorUsesP3Fallback']
                    ? null
                    : $this->advancedColorP3FallbackValue($entry['value']);
                $finalValue = $this->advancedColorLabTargetValue($entry['value']) ?? $entry['value'];

                if (!$hasPreviousFallback) {
                    if ($targetOptions['advancedColorNeedsSrgbFallback'] || $p3Fallback === null) {
                        if ($targetOptions['boxShadowNeedsWebkit'] && !$hasWebkitBoxShadow) {
                            $rewritten[] = $this->declarationEntry('-webkit-box-shadow', $this->expandLegacyAlphaHexColors($fallback));
                        }
                        if ($targetOptions['boxShadowNeedsMoz'] && !$hasMozBoxShadow) {
                            $rewritten[] = $this->declarationEntry('-moz-box-shadow', $this->expandLegacyAlphaHexColors($fallback));
                        }
                        $rewritten[] = $this->entryWithValue($entry, $fallback);
                    } else {
                        if ($targetOptions['boxShadowNeedsWebkit'] && !$hasWebkitBoxShadow) {
                            $rewritten[] = $this->declarationEntry('-webkit-box-shadow', $this->expandLegacyAlphaHexColors($p3Fallback));
                        }
                        if ($targetOptions['boxShadowNeedsMoz'] && !$hasMozBoxShadow) {
                            $rewritten[] = $this->declarationEntry('-moz-box-shadow', $this->expandLegacyAlphaHexColors($p3Fallback));
                        }
                        $rewritten[] = $this->entryWithValue($entry, $p3Fallback);
                    }
                    if ($targetOptions['advancedColorNeedsSrgbFallback'] && $p3Fallback !== null && $p3Fallback !== $fallback) {
                        if ($targetOptions['boxShadowNeedsWebkit'] && !$hasWebkitBoxShadow) {
                            $rewritten[] = $this->declarationEntry('-webkit-box-shadow', $this->expandLegacyAlphaHexColors($p3Fallback));
                        }
                        if ($targetOptions['boxShadowNeedsMoz'] && !$hasMozBoxShadow) {
                            $rewritten[] = $this->declarationEntry('-moz-box-shadow', $this->expandLegacyAlphaHexColors($p3Fallback));
                        }
                        $rewritten[] = $this->entryWithValue($entry, $p3Fallback);
                    }
                    $changed = true;
                }

                if ($hasCustomPropertyReference) {
                    $labFallback = $this->advancedColorLabFallbackValue($entry['value'], true);
                    if ($labFallback !== null) {
                        $supportRules[] = $this->supportsLabRule($selectors, [$this->entryWithValue($entry, $labFallback)]);
                    }
                    continue;
                }

                $rewritten[] = $this->entryWithValue($entry, $finalValue);
                $changed = true;
                continue;
            }

            if ($targetOptions['boxShadowNeedsWebkit'] && !$hasWebkitBoxShadow) {
                $rewritten[] = $this->declarationEntry('-webkit-box-shadow', $this->expandLegacyAlphaHexColors($entry['value']));
                $changed = true;
            }
            if ($targetOptions['boxShadowNeedsMoz'] && !$hasMozBoxShadow) {
                $rewritten[] = $this->declarationEntry('-moz-box-shadow', $this->expandLegacyAlphaHexColors($entry['value']));
                $changed = true;
            }

            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array{boxShadowNeedsWebkit:bool,boxShadowNeedsMoz:bool,boxShadowDropLegacyPrefixes:bool,boxShadowSupportsAdvancedColor:bool,boxShadowDropOverriddenFallbacks:bool,advancedColorNeedsSrgbFallback:bool,advancedColorUsesP3Fallback:bool} $targetOptions
     */
    private function rewriteTextShadowFallbackEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important'] || $entry['property'] !== 'text-shadow') {
                $rewritten[] = $entry;
                continue;
            }

            $fallback = $targetOptions['boxShadowSupportsAdvancedColor']
                ? null
                : $this->advancedColorFallbackValue($entry['value']);
            if ($fallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            $hasCustomPropertyReference = $this->containsCustomPropertyReference($entry['value']);
            $p3Fallback = $hasCustomPropertyReference || !$targetOptions['advancedColorUsesP3Fallback']
                ? null
                : $this->advancedColorP3FallbackValue($entry['value']);

            if ($targetOptions['advancedColorNeedsSrgbFallback'] || $p3Fallback === null) {
                $rewritten[] = $this->entryWithValue($entry, $fallback);
            }
            if ($targetOptions['advancedColorUsesP3Fallback'] && $p3Fallback !== null && $p3Fallback !== $fallback) {
                $rewritten[] = $this->entryWithValue($entry, $p3Fallback);
            }

            if ($hasCustomPropertyReference) {
                $labFallback = $this->advancedColorLabFallbackValue($entry['value'], true);
                if ($labFallback !== null) {
                    $supportRules[] = $this->supportsLabRule($selectors, [$this->entryWithValue($entry, $labFallback)]);
                }
                $changed = true;
                continue;
            }

            $rewritten[] = $this->entryWithValue($entry, $this->advancedColorLabTargetValue($entry['value']) ?? $entry['value']);
            $changed = true;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array<string, bool> $targetOptions
     */
    private function rewriteTextDecorationPrefixEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        [$entries, $composed] = $this->composeTextDecorationEntries($entries);
        $changed = $changed || $composed;

        $hasWebkit = [];
        $hasMoz = [];
        $hasUnprefixed = [];
        foreach ($entries as $entry) {
            $base = $this->textDecorationBaseProperty($entry['property']);
            if ($base === null) {
                continue;
            }
            $hasWebkit[$base] = ($hasWebkit[$base] ?? false) || str_starts_with($entry['property'], '-webkit-');
            $hasMoz[$base] = ($hasMoz[$base] ?? false) || str_starts_with($entry['property'], '-moz-');
            $hasUnprefixed[$base] = ($hasUnprefixed[$base] ?? false)
                || (
                    !str_starts_with($entry['property'], '-webkit-')
                    && !str_starts_with($entry['property'], '-moz-')
                );
        }

        $rewritten = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'text-decoration-thickness') {
                $value = $this->textDecorationThicknessTargetValue($entry['value'], $targetOptions);
                if ($value !== $entry['value']) {
                    $entry = $this->entryWithValue($entry, $value);
                    $changed = true;
                }
                $rewritten[] = $entry;
                continue;
            }

            if ($entry['important'] || !$this->isTextDecorationProperty($entry['property'])) {
                $rewritten[] = $entry;
                continue;
            }

            $base = $this->textDecorationBaseProperty($entry['property']);
            if ($base === null) {
                $rewritten[] = $entry;
                continue;
            }

            $entry['value'] = $base === 'text-decoration'
                ? $this->normalizeTextDecorationValue($entry['value'])
                : $this->normalizeTextDecorationLonghandValue($base, $entry['value']);
            if (str_starts_with($entry['property'], '-moz-')) {
                if (
                    !($targetOptions['textDecorationNeedsMoz'] ?? false)
                    && $this->textDecorationPropertySupportsMozPrefix($base)
                    && ($hasUnprefixed[$base] ?? false)
                ) {
                    $changed = true;
                    continue;
                }

                $rewritten[] = $entry;
                continue;
            }

            $thicknessEntry = null;
            if ($base === 'text-decoration' && ($targetOptions['textDecorationThicknessShorthandNeedsFallback'] ?? false)) {
                $parts = $this->parseTextDecorationValue($entry['value']);
                if ($parts['thickness'] !== null) {
                    $thicknessEntry = $this->declarationEntry(
                        'text-decoration-thickness',
                        $this->textDecorationThicknessTargetValue($parts['thickness'], $targetOptions),
                        $entry['important']
                    );
                    $parts['thickness'] = null;
                    $entry['value'] = $this->serializeTextDecorationParts($parts);
                    $changed = true;
                }
            }

            if (str_starts_with($entry['property'], '-webkit-')) {
                if (!$this->textDecorationNeedsWebkitPrefix($base, $entry['value'], $targetOptions) && ($hasUnprefixed[$base] ?? false)) {
                    $changed = true;
                    continue;
                }

                $rewritten[] = $entry;
                if ($thicknessEntry !== null) {
                    $rewritten[] = $thicknessEntry;
                }
                $changed = true;
                continue;
            }

            $fallback = $this->advancedColorFallbackValue($entry['value']);
            if ($fallback !== null) {
                $labFallback = $this->advancedColorLabFallbackValue($entry['value'], $this->containsCustomPropertyReference($entry['value']));
                $fallbackValue = $base === 'text-decoration'
                    ? $this->normalizeTextDecorationValue($fallback)
                    : $fallback;

                if ($this->containsCustomPropertyReference($entry['value'])) {
                    $rewritten[] = $this->entryWithValue($entry, $fallbackValue);
                    if ($thicknessEntry !== null) {
                        $rewritten[] = $thicknessEntry;
                    }
                    if ($labFallback !== null) {
                        $supportValue = $base === 'text-decoration'
                            ? $this->normalizeTextDecorationValue($labFallback)
                            : $labFallback;
                        $supportRules[] = $this->supportsLabRule($selectors, [$this->entryWithValue($entry, $supportValue)]);
                    }
                    $changed = true;
                    continue;
                }

                $finalValue = $base === 'text-decoration'
                    ? $this->normalizeTextDecorationValue($this->advancedColorLabTargetValue($entry['value']) ?? $entry['value'])
                    : ($this->advancedColorLabTargetValue($entry['value']) ?? $entry['value']);
                if ($this->textDecorationNeedsWebkitPrefix($base, $fallbackValue, $targetOptions) && !($hasWebkit[$base] ?? false)) {
                    $rewritten[] = $this->declarationEntry('-webkit-' . $base, $fallbackValue);
                }
                if ($targetOptions['textDecorationNeedsMoz'] && $this->textDecorationPropertySupportsMozPrefix($base) && !($hasMoz[$base] ?? false)) {
                    $rewritten[] = $this->declarationEntry('-moz-' . $base, $fallbackValue);
                }
                $rewritten[] = $this->entryWithValue($entry, $fallbackValue);
                if ($this->textDecorationNeedsWebkitPrefix($base, $finalValue, $targetOptions) && !($hasWebkit[$base] ?? false)) {
                    $rewritten[] = $this->declarationEntry('-webkit-' . $base, $finalValue);
                }
                if ($targetOptions['textDecorationNeedsMoz'] && $this->textDecorationPropertySupportsMozPrefix($base) && !($hasMoz[$base] ?? false)) {
                    $rewritten[] = $this->declarationEntry('-moz-' . $base, $finalValue);
                }
                $rewritten[] = $this->entryWithValue($entry, $finalValue);
                if ($thicknessEntry !== null) {
                    $rewritten[] = $thicknessEntry;
                }
                $changed = true;
                continue;
            }

            $needsWebkit = $this->textDecorationNeedsWebkitPrefix($base, $entry['value'], $targetOptions)
                && !($hasWebkit[$base] ?? false);
            $needsMoz = $targetOptions['textDecorationNeedsMoz']
                && !($hasMoz[$base] ?? false)
                && $this->textDecorationPropertySupportsMozPrefix($base);
            if ($needsWebkit) {
                $rewritten[] = $this->declarationEntry('-webkit-' . $base, $entry['value']);
                $changed = true;
            }
            if ($needsMoz) {
                $rewritten[] = $this->declarationEntry('-moz-' . $base, $entry['value']);
                $changed = true;
            }
            $rewritten[] = $entry;
            if ($thicknessEntry !== null) {
                $rewritten[] = $thicknessEntry;
            }
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array{0:list<array{property:string,name:string,value:string,important:bool}>,1:bool}
     */
    private function composeTextDecorationEntries(array $entries): array
    {
        $changed = false;
        $rewritten = [];
        $latestShorthand = null;

        foreach ($entries as $entry) {
            if ($entry['important']) {
                $rewritten[] = $entry;
                $latestShorthand = null;
                continue;
            }

            if ($entry['property'] === 'text-decoration') {
                $entry['value'] = $this->normalizeTextDecorationValue($entry['value']);
                $rewritten[] = $entry;
                $latestShorthand = array_key_last($rewritten);
                continue;
            }

            if (
                $latestShorthand !== null
                && in_array($entry['property'], ['text-decoration-style', 'text-decoration-color'], true)
                && !$this->containsCustomPropertyReference($entry['value'])
            ) {
                $component = $entry['property'] === 'text-decoration-style' ? 'style' : 'color';
                $value = $this->normalizeTextDecorationLonghandValue($entry['property'], $entry['value']);
                if ($this->canComposeTextDecorationComponent($component, $value)) {
                    $rewritten[$latestShorthand]['value'] = $this->composeTextDecorationComponent(
                        $rewritten[$latestShorthand]['value'],
                        $component,
                        $value
                    );
                    $changed = true;
                    continue;
                }
            }

            $rewritten[] = $entry;
        }

        return [$rewritten, $changed];
    }

    private function composeTextDecorationComponent(string $value, string $component, string $componentValue): string
    {
        $parts = $this->parseTextDecorationValue($value);
        $parts[$component] = $componentValue;

        return $this->serializeTextDecorationParts($parts);
    }

    private function canComposeTextDecorationComponent(string $component, string $value): bool
    {
        return $component === 'color'
            ? $this->isTextDecorationColorToken($value)
            : $this->isTextDecorationStyleToken($value);
    }

    private function normalizeTextDecorationLonghandValue(string $property, string $value): string
    {
        $value = trim($value);

        return match ($property) {
            'text-decoration-line' => $this->serializeTextDecorationLineTokens($this->splitWhitespaceTopLevel($value)),
            'text-decoration-style' => strtolower($value),
            default => $value,
        };
    }

    private function normalizeTextDecorationValue(string $value): string
    {
        return $this->serializeTextDecorationParts($this->parseTextDecorationValue($value));
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function textDecorationThicknessTargetValue(string $value, array $targetOptions): string
    {
        $value = trim($value);
        if (!($targetOptions['textDecorationThicknessPercentNeedsFallback'] ?? false)) {
            return $value;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $value, $matches) !== 1) {
            return $value;
        }

        return $this->textDecorationPercentThicknessFallback((float) $matches[1]);
    }

    private function textDecorationPercentThicknessFallback(float $percentage): string
    {
        if (abs($percentage) < 0.0000001) {
            return '0';
        }

        $factor = $percentage / 100;
        $reciprocal = 1 / $factor;
        if ($reciprocal > 0 && abs($reciprocal - round($reciprocal)) < 0.0000001) {
            return 'calc(1em / ' . (int) round($reciprocal) . ')';
        }

        return 'calc(' . $this->formatCssNumericValue($factor) . ' * 1em)';
    }

    private function formatCssNumericValue(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        if (str_starts_with($formatted, '0.')) {
            return substr($formatted, 1);
        }
        if (str_starts_with($formatted, '-0.')) {
            return '-' . substr($formatted, 2);
        }

        return $formatted;
    }

    /**
     * @return array{lines:list<string>,style:?string,color:?string,thickness:?string,other:list<string>}
     */
    private function parseTextDecorationValue(string $value): array
    {
        $parts = [
            'lines' => [],
            'style' => null,
            'color' => null,
            'thickness' => null,
            'other' => [],
        ];

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($this->isTextDecorationLineToken($lower)) {
                if ($lower === 'none') {
                    $parts['lines'] = ['none'];
                    continue;
                }
                if (!in_array($lower, $parts['lines'], true) && !in_array('none', $parts['lines'], true)) {
                    $parts['lines'][] = $lower;
                }
                continue;
            }

            if ($this->isTextDecorationStyleToken($lower)) {
                $parts['style'] = $lower;
                continue;
            }

            if ($this->isTextDecorationThicknessToken($token)) {
                $parts['thickness'] = strtolower($token);
                continue;
            }

            if ($this->isTextDecorationColorToken($token)) {
                $parts['color'] = $token;
                continue;
            }

            $parts['other'][] = $token;
        }

        return $parts;
    }

    /**
     * @param array{lines:list<string>,style:?string,color:?string,thickness:?string,other:list<string>} $parts
     */
    private function serializeTextDecorationParts(array $parts): string
    {
        $output = [];
        array_push($output, ...$this->sortTextDecorationLines($parts['lines']));
        if ($parts['thickness'] !== null) {
            $output[] = $parts['thickness'];
        }
        if ($parts['style'] !== null && $parts['style'] !== 'solid') {
            $output[] = $parts['style'];
        } elseif ($parts['style'] === 'solid' && $output === []) {
            $output[] = 'solid';
        }
        if ($parts['color'] !== null) {
            $output[] = $parts['color'];
        }
        array_push($output, ...$parts['other']);

        return implode(' ', $output);
    }

    /**
     * @param list<string> $tokens
     */
    private function serializeTextDecorationLineTokens(array $tokens): string
    {
        return implode(' ', $this->sortTextDecorationLines(array_map('strtolower', $tokens)));
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function sortTextDecorationLines(array $lines): array
    {
        if (in_array('none', $lines, true)) {
            return ['none'];
        }

        $order = ['underline', 'overline', 'line-through', 'blink', 'spelling-error', 'grammar-error'];
        $rank = array_flip($order);
        usort($lines, static fn (string $a, string $b): int => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));

        return $lines;
    }

    private function isTextDecorationProperty(string $property): bool
    {
        return $this->textDecorationBaseProperty($property) !== null;
    }

    private function textDecorationBaseProperty(string $property): ?string
    {
        $base = preg_replace('/^-(?:webkit|moz)-/', '', $property) ?? $property;

        return in_array($base, ['text-decoration', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color'], true)
            ? $base
            : null;
    }

    private function textDecorationPropertyNeedsWebkitPrefix(string $base, string $value): bool
    {
        if ($base !== 'text-decoration') {
            return true;
        }

        return !$this->isTextDecorationLineOnly($value);
    }

    /**
     * @param array<string, bool> $targetOptions
     */
    private function textDecorationNeedsWebkitPrefix(string $base, string $value, array $targetOptions): bool
    {
        if (!$this->textDecorationPropertyNeedsWebkitPrefix($base, $value)) {
            return false;
        }

        if ($base === 'text-decoration') {
            return $targetOptions['textDecorationNeedsWebkit'] ?? false;
        }

        return $targetOptions['textDecorationLonghandNeedsWebkit'] ?? false;
    }

    private function textDecorationPropertySupportsMozPrefix(string $base): bool
    {
        return in_array($base, ['text-decoration-line', 'text-decoration-style', 'text-decoration-color'], true);
    }

    private function isTextDecorationLineOnly(string $value): bool
    {
        $parts = $this->parseTextDecorationValue($value);

        return $parts['lines'] !== []
            && $parts['style'] === null
            && $parts['color'] === null
            && $parts['thickness'] === null
            && $parts['other'] === [];
    }

    private function isTextDecorationLineToken(string $token): bool
    {
        return in_array($token, ['none', 'underline', 'overline', 'line-through', 'blink', 'spelling-error', 'grammar-error'], true);
    }

    private function isTextDecorationStyleToken(string $token): bool
    {
        return in_array(strtolower($token), ['solid', 'double', 'dotted', 'dashed', 'wavy'], true);
    }

    private function isTextDecorationThicknessToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:px|em|rem|%)$/i', trim($token)) === 1
            || in_array(strtolower(trim($token)), ['auto', 'from-font'], true);
    }

    private function isTextDecorationColorToken(string $token): bool
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

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array<string, bool> $targetOptions
     */
    private function rewriteTextEmphasisPrefixEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        $hasWebkit = [];
        $hasUnprefixed = [];

        foreach ($entries as $entry) {
            $base = $this->textEmphasisBaseProperty($entry['property']);
            if ($base === null) {
                continue;
            }
            if (str_starts_with($entry['property'], '-webkit-')) {
                $hasWebkit[$base] = true;
            } else {
                $hasUnprefixed[$base] = true;
            }
        }

        $rewritten = [];
        foreach ($entries as $entry) {
            $base = $this->textEmphasisBaseProperty($entry['property']);
            if ($entry['important'] || $base === null) {
                $rewritten[] = $entry;
                continue;
            }

            $entry['value'] = $this->normalizeTextEmphasisPropertyValue($base, $entry['value']);
            if (str_starts_with($entry['property'], '-webkit-')) {
                if (!($targetOptions['textEmphasisNeedsWebkit'] ?? false) && ($hasUnprefixed[$base] ?? false)) {
                    $changed = true;
                    continue;
                }

                $rewritten[] = $entry;
                $changed = true;
                continue;
            }

            $fallback = $this->advancedColorFallbackValue($entry['value']);
            if ($fallback !== null) {
                $fallbackValue = $this->normalizeTextEmphasisPropertyValue($base, $fallback);
                $hasCustomPropertyReference = $this->containsCustomPropertyReference($entry['value']);
                if ($hasCustomPropertyReference) {
                    if (($targetOptions['textEmphasisNeedsWebkit'] ?? false) && !($hasWebkit[$base] ?? false)) {
                        $rewritten[] = $this->declarationEntry('-webkit-' . $base, $fallbackValue);
                    }
                    $rewritten[] = $this->entryWithValue($entry, $fallbackValue);

                    $labFallback = $this->advancedColorLabFallbackValue($entry['value'], true);
                    if ($labFallback !== null) {
                        $supportValue = $this->normalizeTextEmphasisPropertyValue($base, $labFallback);
                        $supportEntries = [];
                        if (($targetOptions['textEmphasisNeedsWebkit'] ?? false) && !($hasWebkit[$base] ?? false)) {
                            $supportEntries[] = $this->declarationEntry('-webkit-' . $base, $supportValue);
                        }
                        $supportEntries[] = $this->entryWithValue($entry, $supportValue);
                        $supportRules[] = $this->supportsLabRule($selectors, $supportEntries);
                    }
                    $changed = true;
                    continue;
                }

                $finalValue = $this->normalizeTextEmphasisPropertyValue(
                    $base,
                    $this->advancedColorLabTargetValue($entry['value']) ?? $entry['value']
                );
                if (($targetOptions['textEmphasisNeedsWebkit'] ?? false) && !($hasWebkit[$base] ?? false)) {
                    $rewritten[] = $this->declarationEntry('-webkit-' . $base, $fallbackValue);
                }
                $rewritten[] = $this->entryWithValue($entry, $fallbackValue);
                if (($targetOptions['textEmphasisNeedsWebkit'] ?? false) && !($hasWebkit[$base] ?? false)) {
                    $rewritten[] = $this->declarationEntry('-webkit-' . $base, $finalValue);
                }
                $rewritten[] = $this->entryWithValue($entry, $finalValue);
                $changed = true;
                continue;
            }

            if (($targetOptions['textEmphasisNeedsWebkit'] ?? false)
                && !($hasWebkit[$base] ?? false)
                && $this->textEmphasisPropertyNeedsWebkitPrefix($base, $entry['value'])
            ) {
                $rewritten[] = $this->declarationEntry('-webkit-' . $base, $entry['value']);
                $changed = true;
            }
            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    private function textEmphasisBaseProperty(string $property): ?string
    {
        $base = preg_replace('/^-webkit-/', '', $property) ?? $property;

        return in_array($base, ['text-emphasis', 'text-emphasis-style', 'text-emphasis-color', 'text-emphasis-position'], true)
            ? $base
            : null;
    }

    private function textEmphasisPropertyNeedsWebkitPrefix(string $base, string $value): bool
    {
        if ($base !== 'text-emphasis-position') {
            return true;
        }

        return $this->textEmphasisPositionNeedsWebkitPrefix($value);
    }

    private function textEmphasisPositionNeedsWebkitPrefix(string $value): bool
    {
        return !in_array($this->normalizeTextEmphasisPosition($value), ['over left', 'under left'], true);
    }

    private function normalizeTextEmphasisPropertyValue(string $base, string $value): string
    {
        return match ($base) {
            'text-emphasis' => $this->normalizeTextEmphasisShorthand($value),
            'text-emphasis-style' => $this->normalizeTextEmphasisStyle($value),
            'text-emphasis-position' => $this->normalizeTextEmphasisPosition($value),
            default => trim($value),
        };
    }

    private function normalizeTextEmphasisShorthand(string $value): string
    {
        $components = $this->parseTextEmphasisValue($value);

        return $components === null ? trim($value) : $this->serializeTextEmphasisParts($components);
    }

    private function normalizeTextEmphasisStyle(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([\'"]).*\1$/s', $value) === 1) {
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
            if ($token === 'none' || $this->isTextEmphasisShapeToken($token)) {
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

    private function normalizeTextEmphasisPosition(string $value): string
    {
        if (stripos($value, 'var(') !== false) {
            return trim($value);
        }

        $trimmed = trim($value);
        $tokens = $this->splitWhitespaceTopLevel(strtolower($trimmed));
        if ($tokens === []) {
            return $trimmed;
        }

        if (count($tokens) === 1) {
            return in_array($tokens[0], ['over', 'under', 'left', 'right'], true) ? $tokens[0] : $trimmed;
        }

        if (count($tokens) !== 2) {
            return $trimmed;
        }

        $vertical = null;
        $horizontal = null;
        foreach ($tokens as $token) {
            if (in_array($token, ['over', 'under'], true) && $vertical === null) {
                $vertical = $token;
                continue;
            }
            if (in_array($token, ['left', 'right'], true) && $horizontal === null) {
                $horizontal = $token;
                continue;
            }

            return $trimmed;
        }

        if ($vertical === null || $horizontal === null) {
            return $trimmed;
        }

        return $horizontal === 'right' ? $vertical : $vertical . ' left';
    }

    /**
     * @return array{style:?string,color:?string,other:list<string>}|null
     */
    private function parseTextEmphasisValue(string $value): ?array
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
            if (preg_match('/^([\'"]).*\1$/s', trim($token)) === 1
                || $lower === 'filled'
                || $lower === 'open'
                || $lower === 'none'
                || $this->isTextEmphasisShapeToken($lower)
            ) {
                $styleTokens[] = $token;
                continue;
            }

            $other[] = trim($token);
        }

        $style = $styleTokens === [] ? null : $this->normalizeTextEmphasisStyle(implode(' ', $styleTokens));
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
     * @param array{style:?string,color:?string,other:list<string>} $parts
     */
    private function serializeTextEmphasisParts(array $parts): string
    {
        $output = [];
        if ($parts['style'] !== null) {
            $output[] = $parts['style'];
        }
        if ($parts['color'] !== null) {
            $output[] = $parts['color'];
        }
        array_push($output, ...$parts['other']);

        return implode(' ', $output);
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

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array<string, bool> $targetOptions
     */
    private function rewriteCaretFallbackEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important'] || !in_array($entry['property'], ['caret', 'caret-color', 'caret-shape'], true)) {
                $rewritten[] = $entry;
                continue;
            }

            $entry['value'] = $this->normalizeCaretPropertyValue($entry['property'], $entry['value']);
            if ($entry['property'] === 'caret-shape') {
                $rewritten[] = $entry;
                continue;
            }

            $fallback = ($targetOptions['boxShadowSupportsAdvancedColor'] ?? false)
                ? null
                : $this->advancedColorFallbackValue($entry['value']);
            if ($fallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            $hasCustomPropertyReference = $this->containsCustomPropertyReference($entry['value']);
            $p3Fallback = $hasCustomPropertyReference || !($targetOptions['advancedColorUsesP3Fallback'] ?? false)
                ? null
                : $this->advancedColorP3FallbackValue($entry['value']);

            if (($targetOptions['advancedColorNeedsSrgbFallback'] ?? true) || $p3Fallback === null) {
                $rewritten[] = $this->entryWithValue($entry, $this->normalizeCaretPropertyValue($entry['property'], $fallback));
            }
            if (($targetOptions['advancedColorUsesP3Fallback'] ?? false) && $p3Fallback !== null && $p3Fallback !== $fallback) {
                $rewritten[] = $this->entryWithValue($entry, $this->normalizeCaretPropertyValue($entry['property'], $p3Fallback));
            }

            if ($hasCustomPropertyReference) {
                $labFallback = $this->advancedColorLabFallbackValue($entry['value'], true);
                if ($labFallback !== null) {
                    $supportRules[] = $this->supportsLabRule(
                        $selectors,
                        [$this->entryWithValue($entry, $this->normalizeCaretPropertyValue($entry['property'], $labFallback))]
                    );
                }
                $changed = true;
                continue;
            }

            $finalValue = $this->advancedColorLabTargetValue($entry['value']) ?? $entry['value'];
            $rewritten[] = $this->entryWithValue($entry, $this->normalizeCaretPropertyValue($entry['property'], $finalValue));
            $changed = true;
        }

        $entries = $rewritten;

        return $changed;
    }

    private function normalizeCaretPropertyValue(string $property, string $value): string
    {
        return match ($property) {
            'caret' => $this->normalizeCaretShorthand($value),
            'caret-shape' => strtolower(trim($value)),
            default => trim($value),
        };
    }

    private function normalizeCaretShorthand(string $value): string
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

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     * @param array<string, bool> $targetOptions
     */
    private function rewriteListStyleFallbackEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important'] || !in_array($entry['property'], ['list-style', 'list-style-image'], true)) {
                $rewritten[] = $entry;
                continue;
            }

            $fallback = ($targetOptions['boxShadowSupportsAdvancedColor'] ?? false)
                ? null
                : $this->advancedColorFallbackValue($entry['value']);
            if ($fallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            if (($targetOptions['gradientNeedsOldWebkit'] ?? false) && $entry['property'] === 'list-style-image') {
                foreach ($this->legacyWebkitGradientEntries($entry, $fallback) as $prefixedEntry) {
                    $rewritten[] = $prefixedEntry;
                }
            }
            $rewritten[] = $this->entryWithValue($entry, $fallback);

            if ($this->containsCustomPropertyReference($entry['value'])) {
                $labFallback = $this->advancedColorLabFallbackValue($entry['value'], true);
                if ($labFallback !== null) {
                    $supportRules[] = $this->supportsLabRule($selectors, [$this->entryWithValue($entry, $labFallback)]);
                }
                $changed = true;
                continue;
            }

            $rewritten[] = $this->entryWithValue($entry, $this->advancedColorLabTargetValue($entry['value']) ?? $entry['value']);
            $changed = true;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool} $entry
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function legacyWebkitGradientEntries(array $entry, string $fallback): array
    {
        if (preg_match('/^linear-gradient\((#[0-9a-f]+),\s*(#[0-9a-f]+)\)$/i', trim($fallback), $matches) !== 1) {
            return [];
        }

        $from = strtolower($matches[1]);
        $to = strtolower($matches[2]);

        return [
            $this->entryWithValue($entry, '-webkit-gradient(linear,0 0,0 100%,from(' . $from . '),to(' . $to . '))'),
            $this->entryWithValue($entry, '-webkit-linear-gradient(top,' . $from . ',' . $to . ')'),
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteGradientPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $needsOldWebkit = $targetOptions['gradientNeedsOldWebkit'] ?? false;
        $needsWebkit = $targetOptions['gradientNeedsWebkit'] ?? false;
        $needsMoz = $targetOptions['gradientNeedsMoz'] ?? false;
        $needsO = $targetOptions['gradientNeedsO'] ?? false;
        $needsAnyPrefix = $needsOldWebkit || $needsWebkit || $needsMoz || $needsO;

        $modernGradients = [];
        $existingValues = [];
        foreach ($entries as $entry) {
            if ($entry['important'] || $entry['property'] !== 'background-image') {
                continue;
            }
            if ($this->linearGradientPrefixingHasAdvancedColorStack($entry['value'])) {
                return false;
            }

            $existingValues[$entry['value']] = true;
            if ($this->isUnprefixedLinearGradientValue($entry['value'])) {
                $modernGradients[$this->canonicalGradientValue($entry['value'])] = true;
            }
        }

        if (!$needsAnyPrefix && $modernGradients === []) {
            return false;
        }

        $changed = false;
        $rewritten = [];
        foreach ($entries as $entry) {
            if ($entry['important'] || $entry['property'] !== 'background-image') {
                $rewritten[] = $entry;
                continue;
            }

            $modernEquivalent = $this->linearGradientModernEquivalent($entry['value']);
            if (!$needsAnyPrefix
                && $modernEquivalent !== null
                && !$this->isUnprefixedLinearGradientValue($entry['value'])
                && isset($modernGradients[$this->canonicalGradientValue($modernEquivalent)])
            ) {
                $changed = true;
                continue;
            }

            if (!$this->isUnprefixedLinearGradientValue($entry['value'])) {
                $rewritten[] = $entry;
                continue;
            }

            if ($needsOldWebkit) {
                $legacy = $this->legacyLinearGradientValue($entry['value']);
                if ($legacy !== null && !isset($existingValues[$legacy])) {
                    $rewritten[] = $this->entryWithValue($entry, $legacy);
                    $existingValues[$legacy] = true;
                    $changed = true;
                }
            }

            foreach ([['-webkit', $needsWebkit], ['-moz', $needsMoz], ['-o', $needsO]] as [$prefix, $needed]) {
                if (!$needed) {
                    continue;
                }

                $prefixed = $this->prefixedLinearGradientValue($entry['value'], $prefix);
                if ($prefixed === null || isset($existingValues[$prefixed])) {
                    continue;
                }

                $rewritten[] = $this->entryWithValue($entry, $prefixed);
                $existingValues[$prefixed] = true;
                $changed = true;
            }

            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    private function isUnprefixedLinearGradientValue(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^linear-gradient\(/i', $value) !== 1) {
            return false;
        }

        [, $offset] = $this->readFunctionRaw($value, 0);

        return $offset === strlen($value) - 1;
    }

    private function linearGradientPrefixingHasAdvancedColorStack(string $value): bool
    {
        return preg_match('/\b(?:color|lab|lch|oklab|oklch|var)\(/i', $value) === 1;
    }

    private function canonicalGradientValue(string $value): string
    {
        return strtolower(trim($value));
    }

    private function linearGradientModernEquivalent(string $value): ?string
    {
        $value = trim($value);
        if ($this->isUnprefixedLinearGradientValue($value)) {
            return $value;
        }

        if (preg_match('/^-(webkit|moz|o)-linear-gradient\((.*)\)$/is', $value, $matches) === 1) {
            $args = $this->splitTopLevel($matches[2], ',');
            if ($args === []) {
                return null;
            }

            $direction = $this->modernLinearGradientDirectionFromPrefixed($args[0]);
            if ($direction !== null) {
                array_shift($args);
            } elseif ($this->looksLikePrefixedLinearGradientDirection($args[0])) {
                return null;
            } else {
                $direction = 'to top';
            }

            if (count($args) < 2) {
                return null;
            }

            $parts = $direction === '' ? $args : array_merge([$direction], $args);

            return 'linear-gradient(' . implode(',', $parts) . ')';
        }

        if (preg_match('/^-webkit-gradient\(\s*linear\s*,(.*)\)$/is', $value, $matches) === 1) {
            $args = $this->splitTopLevel($matches[1], ',');
            if (count($args) < 4) {
                return null;
            }

            $direction = $this->modernLinearGradientDirectionFromLegacyPoints($args[0], $args[1]);
            if ($direction === null) {
                return null;
            }

            $from = $this->legacyWebkitGradientStopValue($args[2], 'from');
            $to = $this->legacyWebkitGradientStopValue($args[count($args) - 1], 'to');
            if ($from === null || $to === null) {
                return null;
            }

            $parts = $direction === '' ? [$from, $to] : [$direction, $from, $to];

            return 'linear-gradient(' . implode(',', $parts) . ')';
        }

        return null;
    }

    private function legacyLinearGradientValue(string $value): ?string
    {
        $parts = $this->parseModernLinearGradient($value);
        if ($parts === null || count($parts['stops']) !== 2) {
            return null;
        }

        $points = $this->legacyWebkitGradientPoints($parts['direction']);
        if ($points === null) {
            return null;
        }

        return '-webkit-gradient(linear,'
            . $points[0]
            . ','
            . $points[1]
            . ',from('
            . $parts['stops'][0]
            . '),to('
            . $parts['stops'][1]
            . '))';
    }

    private function prefixedLinearGradientValue(string $value, string $prefix): ?string
    {
        $parts = $this->parseModernLinearGradient($value);
        if ($parts === null) {
            return null;
        }

        $direction = $this->prefixedLinearGradientDirection($parts['direction']);
        if ($direction === null) {
            return null;
        }

        $args = $direction === '' ? $parts['stops'] : array_merge([$direction], $parts['stops']);

        return $prefix . '-linear-gradient(' . implode(',', $args) . ')';
    }

    /**
     * @return array{direction:string|null,stops:list<string>}|null
     */
    private function parseModernLinearGradient(string $value): ?array
    {
        if (preg_match('/^linear-gradient\((.*)\)$/is', trim($value), $matches) !== 1) {
            return null;
        }

        $args = $this->splitTopLevel($matches[1], ',');
        if (count($args) < 2) {
            return null;
        }

        $direction = null;
        $first = strtolower(trim($args[0]));
        if (str_starts_with($first, 'to ')) {
            $direction = $first;
            array_shift($args);
        } elseif (preg_match('/^-?(?:\d+|\d*\.\d+)deg$/', $first) === 1) {
            $direction = $first;
            array_shift($args);
        }

        if (count($args) < 2) {
            return null;
        }

        return [
            'direction' => $direction,
            'stops' => array_values($args),
        ];
    }

    /**
     * @return list<string>|null
     */
    private function legacyWebkitGradientPoints(?string $direction): ?array
    {
        return match ($direction) {
            null, 'to bottom' => ['0 0', '0 100%'],
            'to right' => ['0 0', '100% 0'],
            'to left' => ['100% 0', '0 0'],
            'to top' => ['0 100%', '0 0'],
            'to bottom right', 'to right bottom' => ['0 0', '100% 100%'],
            'to bottom left', 'to left bottom' => ['100% 0', '0 100%'],
            'to top right', 'to right top' => ['0 100%', '100% 0'],
            'to top left', 'to left top' => ['100% 100%', '0 0'],
            default => null,
        };
    }

    private function prefixedLinearGradientDirection(?string $direction): ?string
    {
        if ($direction === null || $direction === 'to bottom') {
            return 'top';
        }

        if (preg_match('/^(-?(?:\d+|\d*\.\d+))deg$/', $direction, $matches) === 1) {
            $angle = 90 - (float) $matches[1];

            return $this->formatGradientAngle($angle);
        }

        if (!str_starts_with($direction, 'to ')) {
            return null;
        }

        $tokens = preg_split('/\s+/', trim(substr($direction, 3))) ?: [];
        $converted = [];
        foreach ($tokens as $token) {
            $converted[] = match ($token) {
                'top' => 'bottom',
                'right' => 'left',
                'bottom' => 'top',
                'left' => 'right',
                default => null,
            };
            if ($converted[array_key_last($converted)] === null) {
                return null;
            }
        }

        return implode(' ', $converted);
    }

    private function modernLinearGradientDirectionFromPrefixed(string $direction): ?string
    {
        $direction = strtolower(trim($direction));

        return match ($direction) {
            'top' => '',
            'left' => 'to right',
            'right' => 'to left',
            'bottom' => 'to top',
            'top left', 'left top' => 'to bottom right',
            'top right', 'right top' => 'to bottom left',
            'bottom left', 'left bottom' => 'to top right',
            'bottom right', 'right bottom' => 'to top left',
            default => $this->modernLinearGradientDirectionFromPrefixedAngle($direction),
        };
    }

    private function modernLinearGradientDirectionFromPrefixedAngle(string $direction): ?string
    {
        if (preg_match('/^(-?(?:\d+|\d*\.\d+))deg$/', $direction, $matches) !== 1) {
            return null;
        }

        $angle = 90 - (float) $matches[1];

        return $this->formatGradientAngle($angle);
    }

    private function looksLikePrefixedLinearGradientDirection(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, [
            'top',
            'left',
            'right',
            'bottom',
            'top left',
            'left top',
            'top right',
            'right top',
            'bottom left',
            'left bottom',
            'bottom right',
            'right bottom',
        ], true) || preg_match('/^-?(?:\d+|\d*\.\d+)deg$/', $value) === 1;
    }

    private function modernLinearGradientDirectionFromLegacyPoints(string $start, string $end): ?string
    {
        $start = trim(strtolower($start));
        $end = trim(strtolower($end));

        return match ($start . '|' . $end) {
            '0 0|0 100%' => '',
            '0 0|100% 0' => 'to right',
            '100% 0|0 0' => 'to left',
            '0 100%|0 0' => 'to top',
            '0 0|100% 100%' => 'to bottom right',
            '100% 0|0 100%' => 'to bottom left',
            '0 100%|100% 0' => 'to top right',
            '100% 100%|0 0' => 'to top left',
            default => null,
        };
    }

    private function legacyWebkitGradientStopValue(string $value, string $name): ?string
    {
        $value = trim($value);
        if (preg_match('/^' . preg_quote($name, '/') . '\((.*)\)$/is', $value, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function formatGradientAngle(float $angle): string
    {
        $angle = fmod($angle, 360.0);
        if ($angle < 0) {
            $angle += 360.0;
        }

        $formatted = rtrim(rtrim(sprintf('%.6F', $angle), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . 'deg';
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteImageSetPrefixEntries(array &$entries, array $targetOptions): bool
    {
        $needsWebkit = $targetOptions['imageSetNeedsWebkit'] ?? false;
        $needsUrlFallback = $targetOptions['imageSetNeedsUrlFallback'] ?? false;
        if (!$needsWebkit && !$needsUrlFallback) {
            return false;
        }

        $properties = [
            'background',
            'background-image',
            'border-image',
            'border-image-source',
            '-webkit-mask',
            '-webkit-mask-image',
            'list-style',
            'list-style-image',
        ];
        $hasPrefixed = [];
        $hasUnprefixed = [];
        foreach ($entries as $entry) {
            if ($entry['important'] || !in_array($entry['property'], $properties, true)) {
                continue;
            }
            if ($this->containsPrefixedImageSet($entry['value'])) {
                $hasPrefixed[$entry['property']] = true;
            }
            if ($this->containsUnprefixedImageSet($entry['value'])) {
                $hasUnprefixed[$entry['property']] = true;
            }
        }

        $changed = false;
        $rewritten = [];
        foreach ($entries as $entry) {
            if ($entry['important'] || !in_array($entry['property'], $properties, true)) {
                $rewritten[] = $entry;
                continue;
            }

            if ($needsUrlFallback
                && ($hasUnprefixed[$entry['property']] ?? false)
                && preg_match('/^url\(/i', $entry['value']) === 1
            ) {
                $normalized = $this->normalizeQuotedUrlToken($entry['value']);
                $changed = $changed || $normalized !== $entry['value'];
                $entry['value'] = $normalized;
                $rewritten[] = $entry;
                continue;
            }

            if (!$this->containsUnprefixedImageSet($entry['value'])) {
                $rewritten[] = $entry;
                continue;
            }

            if ($needsWebkit && !($hasPrefixed[$entry['property']] ?? false)) {
                $rewritten[] = $this->entryWithValue($entry, $this->prefixImageSetFunctions($entry['value']));
                $changed = true;
            }
            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    private function containsUnprefixedImageSet(string $value): bool
    {
        return preg_match('/(?<!-)image-set\(/i', $value) === 1;
    }

    private function containsPrefixedImageSet(string $value): bool
    {
        return stripos($value, '-webkit-image-set(') !== false;
    }

    private function prefixImageSetFunctions(string $value): string
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

            $lower = strtolower(substr($value, $i));
            $previous = $i > 0 ? $value[$i - 1] : '';
            if (str_starts_with($lower, 'image-set(') && ($previous === '' || !$this->isIdentifierChar($previous))) {
                [$function, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $this->prefixImageSetFunction($function);
                $i = $offset;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function prefixImageSetFunction(string $function): string
    {
        if (preg_match('/^image-set\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $candidates = array_map(
            fn (string $candidate): string => $this->prefixImageSetCandidate($candidate),
            $this->splitTopLevel($matches[1], ',')
        );

        return '-webkit-image-set(' . implode(',', $candidates) . ')';
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteClampFallbackEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['clampNeedsMaxMinFallback'] ?? false)) {
            return false;
        }

        $changed = false;
        foreach ($entries as &$entry) {
            if (stripos($entry['value'], 'clamp(') === false) {
                continue;
            }

            $rewritten = $this->lowerClampFunctions($entry['value']);
            if ($rewritten === $entry['value']) {
                continue;
            }

            $entry['value'] = $rewritten;
            $changed = true;
        }
        unset($entry);

        return $changed;
    }

    private function lowerClampFunctions(string $value): string
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

            $lower = strtolower(substr($value, $i));
            $previous = $i > 0 ? $value[$i - 1] : '';
            if (str_starts_with($lower, 'url(') && ($previous === '' || !$this->isIdentifierChar($previous))) {
                [$function, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $function;
                $i = $offset;
                continue;
            }
            if (str_starts_with($lower, 'clamp(') && ($previous === '' || !$this->isIdentifierChar($previous))) {
                [$function, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $this->lowerClampFunction($function);
                $i = $offset;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function lowerClampFunction(string $function): string
    {
        if (preg_match('/^clamp\((.*)\)$/is', trim($function), $matches) !== 1) {
            return $function;
        }

        $args = $this->splitTopLevel($matches[1], ',');
        if (count($args) !== 3) {
            return $function;
        }

        $min = $this->lowerClampFunctions($args[0]);
        $preferred = $this->lowerClampFunctions($args[1]);
        $max = $this->lowerClampFunctions($args[2]);

        return 'max(' . $min . ',min(' . $preferred . ',' . $max . '))';
    }

    private function prefixImageSetCandidate(string $candidate): string
    {
        $tokens = $this->splitWhitespaceTopLevel($candidate);
        if ($tokens === []) {
            return trim($candidate);
        }

        $tokens[0] = $this->imageSetCandidateTokenToWebkitUrl($tokens[0]);

        return implode(' ', $tokens);
    }

    private function imageSetCandidateTokenToWebkitUrl(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^([\'"])(.*)\1$/s', $token, $matches) === 1) {
            return 'url("' . str_replace('"', '\\"', $matches[2]) . '")';
        }
        if (preg_match('/^url\(/i', $token) === 1) {
            return $this->normalizeQuotedUrlToken($token);
        }

        return $token;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param list<string> $supportRules
     */
    private function rewriteAdvancedColorFallbackEntries(array &$entries, string $selectors, array &$supportRules, array $targetOptions): bool
    {
        $needsSrgbFallback = $targetOptions['advancedColorNeedsSrgbFallback'] ?? false;
        $usesP3Fallback = $targetOptions['advancedColorUsesP3Fallback'] ?? false;
        $supportsNative = $targetOptions['advancedColorSupportsNative'] ?? false;
        if (!$needsSrgbFallback && !$usesP3Fallback && !$supportsNative) {
            return false;
        }

        $changed = false;
        $rewritten = [];
        $p3SupportEntries = [];
        $labSupportEntries = [];

        foreach ($entries as $entry) {
            $isCustomProperty = str_starts_with($entry['property'], '--');
            $normalized = $isCustomProperty
                ? $entry['value']
                : $this->normalizeBackgroundFallbackValue($entry['value']);

            if ($entry['important'] || (!$isCustomProperty && !$this->propertySupportsAdvancedColorFallback($entry['property'], $normalized))) {
                $rewritten[] = $entry;
                continue;
            }

            $srgbFallback = $this->advancedColorFallbackValue($normalized);
            if ($srgbFallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            $hasCustomPropertyReference = $this->containsCustomPropertyReference($normalized);
            if ($supportsNative && !$needsSrgbFallback) {
                [$rewritten, $dropped] = $this->dropPreviousSamePropertyFallbacks($rewritten, $entry['property']);
                $rewritten[] = $entry;
                $changed = $changed || $dropped;
                continue;
            }

            $p3Fallback = $usesP3Fallback ? $this->advancedColorP3FallbackValue($normalized, $isCustomProperty) : null;
            $labFallback = $this->advancedColorLabFallbackValue($normalized, $isCustomProperty || $hasCustomPropertyReference);

            if (!$needsSrgbFallback) {
                if ($hasCustomPropertyReference && $p3Fallback !== null) {
                    [$rewritten, $dropped] = $this->dropPreviousSamePropertyFallbacks($rewritten, $entry['property']);
                    $rewritten[] = $this->entryWithValue($entry, $p3Fallback);
                    $changed = true;
                    if ($labFallback !== null && $labFallback !== $p3Fallback) {
                        $labSupportEntries[] = $this->entryWithValue($entry, $labFallback);
                    }
                    $changed = $changed || $dropped;
                    continue;
                }

                $rewritten[] = $entry;
                continue;
            }

            $rewritten[] = $this->entryWithValue($entry, $srgbFallback);
            $changed = true;

            if ($isCustomProperty || $hasCustomPropertyReference) {
                if ($p3Fallback !== null && $p3Fallback !== $srgbFallback) {
                    $p3SupportEntries[] = $this->entryWithValue($entry, $p3Fallback);
                }
                if ($labFallback !== null && $labFallback !== $srgbFallback) {
                    $labSupportEntries[] = $this->entryWithValue($entry, $labFallback);
                }
                continue;
            }

            if ($p3Fallback !== null && $p3Fallback !== $srgbFallback && $p3Fallback !== $normalized) {
                $rewritten[] = $this->entryWithValue($entry, $p3Fallback);
            }

            $entry['value'] = $this->advancedColorLabTargetValue($normalized) ?? $normalized;
            $rewritten[] = $entry;
        }

        if ($p3SupportEntries !== []) {
            $supportRules[] = $this->supportsP3Rule($selectors, $p3SupportEntries);
        }
        if ($labSupportEntries !== []) {
            $supportRules[] = $this->supportsLabRule($selectors, $labSupportEntries);
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array{0:list<array{property:string,name:string,value:string,important:bool}>,1:bool}
     */
    private function dropPreviousSamePropertyFallbacks(array $entries, string $property): array
    {
        $rewritten = [];
        $dropped = false;
        foreach ($entries as $entry) {
            if (!$entry['important'] && $entry['property'] === $property) {
                $dropped = true;
                continue;
            }

            $rewritten[] = $entry;
        }

        return [$rewritten, $dropped];
    }

    private function propertySupportsAdvancedColorFallback(string $property, string $value): bool
    {
        if ($property === 'color') {
            return stripos($value, 'light-dark(') === false;
        }

        return in_array($property, [
            'background',
            'background-color',
            'background-image',
            'fill',
            'outline',
            'outline-color',
            'stroke',
        ], true);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array{replaceAt:int,drop:list<int>,entries:list<array{property:string,name:string,value:string,important:bool}>}|null
     */
    private function planMaskLayerComposition(array $entries, bool $needsWebkit): ?array
    {
        $map = [
            'mask-image' => 'image',
            'mask-position' => 'position',
            'mask-size' => 'size',
            'mask-repeat' => 'repeat',
            'mask-origin' => 'origin',
            'mask-clip' => 'clip',
            'mask-composite' => 'composite',
            'mask-mode' => 'mode',
        ];

        $latest = [];
        $drop = [];
        foreach ($entries as $index => $entry) {
            $component = $map[$entry['property']] ?? null;
            if ($component === null) {
                continue;
            }
            if ($entry['important']) {
                return null;
            }
            $latest[$component] = $index;
            $drop[] = $index;
        }

        if (!isset($latest['image']) || count($latest) === 1) {
            return null;
        }

        $image = $entries[$latest['image']]['value'];
        if (!$this->isPrefixableMaskImageValue($image) || count($this->splitTopLevel($image, ',')) !== 1) {
            return null;
        }

        $components = [];
        foreach ($latest as $component => $index) {
            $components[$component] = $this->normalizeMaskLayerComponent($component, $entries[$index]['value']);
        }

        $componentSets = [$components];
        $fallbackImage = $this->advancedColorFallbackValue($components['image']);
        if ($fallbackImage !== null) {
            $fallbackComponents = $components;
            $fallbackComponents['image'] = $fallbackImage;
            array_unshift($componentSets, $fallbackComponents);
        }

        $planned = [];
        foreach ($componentSets as $componentSet) {
            array_push($planned, ...$this->maskLayerEntries($componentSet, $needsWebkit));
        }

        return [
            'replaceAt' => min($drop),
            'drop' => $drop,
            'entries' => $planned,
        ];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array{replaceAt:int,drop:list<int>,entries:list<array{property:string,name:string,value:string,important:bool}>}|null
     */
    private function planMaskBorderComposition(array $entries, string $family, bool $needsWebkit): ?array
    {
        $map = $family === 'modern'
            ? [
                'mask-border-source' => 'source',
                'mask-border-slice' => 'slice',
                'mask-border-width' => 'width',
                'mask-border-outset' => 'outset',
                'mask-border-repeat' => 'repeat',
                'mask-border-mode' => 'mode',
            ]
            : [
                '-webkit-mask-box-image-source' => 'source',
                '-webkit-mask-box-image-slice' => 'slice',
                '-webkit-mask-box-image-width' => 'width',
                '-webkit-mask-box-image-outset' => 'outset',
                '-webkit-mask-box-image-repeat' => 'repeat',
            ];

        $latest = [];
        $drop = [];
        foreach ($entries as $index => $entry) {
            $component = $map[$entry['property']] ?? null;
            if ($component === null) {
                continue;
            }
            if ($entry['important']) {
                return null;
            }
            $latest[$component] = $index;
            $drop[] = $index;
        }

        if (!isset($latest['source'], $latest['slice'])) {
            return null;
        }

        $components = [];
        foreach ($latest as $component => $index) {
            $components[$component] = $this->normalizeMaskBorderComponent($component, $entries[$index]['value']);
        }

        $componentSets = [$components];
        $fallbackSource = $this->advancedColorFallbackValue($components['source'] ?? '');
        if ($fallbackSource !== null) {
            $fallbackComponents = $components;
            $fallbackComponents['source'] = $fallbackSource;
            array_unshift($componentSets, $fallbackComponents);
        }

        $planned = [];
        foreach ($componentSets as $componentSet) {
            if ($needsWebkit) {
                $planned[] = $this->maskEntry('-webkit-mask-box-image', $this->composeMaskBorderValue($componentSet, false));
            }
            if ($family === 'modern') {
                $planned[] = $this->maskEntry('mask-border', $this->composeMaskBorderValue($componentSet, true));
            }
        }

        return [
            'replaceAt' => min($drop),
            'drop' => $drop,
            'entries' => $planned,
        ];
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool} $entry
     * @return array{0:list<array{property:string,name:string,value:string,important:bool}>,1:bool,2?:list<array{property:string,name:string,value:string,important:bool}>}
     */
    private function rewriteSingleMaskPrefixEntry(array $entry, bool $hasWebkitMask, bool $hasWebkitMaskImage, bool $needsWebkit): array
    {
        if ($entry['important']) {
            return [[$entry], false];
        }

        switch ($entry['property']) {
            case '-webkit-mask':
                $entry['value'] = $this->normalizeMaskShorthand($entry['value'], false);
                $fallback = $this->advancedColorFallbackValue($entry['value']);
                if ($fallback !== null) {
                    return [[$this->maskEntry('-webkit-mask', $fallback), $entry], true];
                }

                return [[$entry], true];

            case 'mask':
                $mode = $this->maskShorthandMode($entry['value']);
                $modern = $this->normalizeMaskShorthand($entry['value'], true);
                $prefixed = $this->normalizeMaskShorthand($entry['value'], false);
                $modernFallback = $this->advancedColorFallbackValue($modern);
                $prefixedFallback = $this->advancedColorFallbackValue($prefixed);
                if ($modernFallback !== null && $prefixedFallback !== null) {
                    $modernLab = $this->advancedColorLabFallbackValue($modern);
                    $prefixedLab = $this->advancedColorLabFallbackValue($prefixed);
                    if (
                        $modernLab !== null
                        && $prefixedLab !== null
                        && $this->containsCustomPropertyReference($modern)
                    ) {
                        $mapped = [];
                        if ($needsWebkit && !$hasWebkitMask) {
                            $mapped[] = $this->maskEntry('-webkit-mask', $prefixedFallback);
                        }
                        $mapped[] = $this->maskEntry('mask', $modernFallback);

                        $supportEntries = [];
                        if ($needsWebkit && !$hasWebkitMask) {
                            $supportEntries[] = $this->maskEntry('-webkit-mask', $prefixedLab);
                        }
                        if ($needsWebkit && $mode !== null && strtolower($mode) !== 'alpha') {
                            $supportEntries[] = $this->maskEntry('-webkit-mask-source-type', strtolower($mode));
                        }
                        $supportEntries[] = $this->maskEntry('mask', $modernLab);

                        return [$mapped, true, $supportEntries];
                    }

                    $entry['value'] = $modern;
                    $mapped = [];
                    if ($needsWebkit && !$hasWebkitMask) {
                        $mapped[] = $this->maskEntry('-webkit-mask', $prefixedFallback);
                    }
                    $mapped[] = $this->maskEntry('mask', $modernFallback);
                    if ($needsWebkit && !$hasWebkitMask) {
                        $mapped[] = $this->maskEntry('-webkit-mask', $prefixed);
                    }
                    if ($needsWebkit && $mode !== null && strtolower($mode) !== 'alpha') {
                        $mapped[] = $this->maskEntry('-webkit-mask-source-type', strtolower($mode));
                    }
                    $mapped[] = $entry;

                    return [$mapped, true];
                }

                if ($mode === null || strtolower($mode) === 'alpha') {
                    return [[$entry], false];
                }

                $entry['value'] = $modern;
                $mapped = [];
                if ($needsWebkit && !$hasWebkitMask) {
                    $mapped[] = $this->maskEntry('-webkit-mask', $prefixed);
                }
                if ($needsWebkit) {
                    $mapped[] = $this->maskEntry('-webkit-mask-source-type', strtolower($mode));
                }
                $mapped[] = $entry;

                return [$mapped, true];

            case '-webkit-mask-image':
                $entry['value'] = $this->normalizeMaskImageValue($entry['value']);
                $fallback = $this->advancedColorFallbackValue($entry['value']);
                if ($fallback !== null) {
                    return [[$this->maskEntry('-webkit-mask-image', $fallback), $entry], true];
                }

                return [[$entry], true];

            case 'mask-image':
                if (!$this->isPrefixableMaskImageValue($entry['value'])) {
                    return [[$entry], false];
                }

                $entry['value'] = $this->normalizeMaskImageValue($entry['value']);
                $fallback = $this->advancedColorFallbackValue($entry['value']);
                if ($fallback !== null) {
                    $mapped = [];
                    if ($needsWebkit && !$hasWebkitMaskImage) {
                        $mapped[] = $this->maskEntry('-webkit-mask-image', $fallback);
                    }
                    $mapped[] = $this->maskEntry('mask-image', $fallback);
                    if ($needsWebkit && !$hasWebkitMaskImage) {
                        $mapped[] = $this->maskEntry('-webkit-mask-image', $entry['value']);
                    }
                    $mapped[] = $entry;

                    return [$mapped, true];
                }

                return [
                    ($needsWebkit && !$hasWebkitMaskImage) ? [$this->maskEntry('-webkit-mask-image', $entry['value']), $entry] : [$entry],
                    true,
                ];

            case 'mask-border':
                $modern = $this->normalizeMaskBorderShorthand($entry['value'], true);
                $prefixed = $this->normalizeMaskBorderShorthand($entry['value'], false);
                $entry['value'] = $modern;
                $modernFallback = $this->advancedColorFallbackValue($modern);
                $prefixedFallback = $this->advancedColorFallbackValue($prefixed);
                if ($modernFallback !== null && $prefixedFallback !== null) {
                    $modernLab = $this->advancedColorLabFallbackValue($modern);
                    $prefixedLab = $this->advancedColorLabFallbackValue($prefixed);
                    if (
                        $modernLab !== null
                        && $prefixedLab !== null
                        && $this->containsCustomPropertyReference($modern)
                    ) {
                        return [
                            array_values(array_filter([
                                $needsWebkit ? $this->maskEntry('-webkit-mask-box-image', $prefixedFallback) : null,
                                $this->maskEntry('mask-border', $modernFallback),
                            ])),
                            true,
                            array_values(array_filter([
                                $needsWebkit ? $this->maskEntry('-webkit-mask-box-image', $prefixedLab) : null,
                                $this->maskEntry('mask-border', $modernLab),
                            ])),
                        ];
                    }

                    return [
                        array_values(array_filter([
                            $needsWebkit ? $this->maskEntry('-webkit-mask-box-image', $prefixedFallback) : null,
                            $this->maskEntry('mask-border', $modernFallback),
                            $needsWebkit ? $this->maskEntry('-webkit-mask-box-image', $prefixed) : null,
                            $entry,
                        ])),
                        true,
                    ];
                }

                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-box-image', $prefixed), $entry] : [$entry], true];

            case 'mask-border-source':
                $value = $this->normalizeMaskBorderComponent('source', $entry['value']);
                $entry['value'] = $value;
                $fallback = $this->advancedColorFallbackValue($value);
                if ($fallback !== null) {
                    return [
                        array_values(array_filter([
                            $needsWebkit ? $this->maskEntry('-webkit-mask-box-image-source', $fallback) : null,
                            $this->maskEntry('mask-border-source', $fallback),
                            $needsWebkit ? $this->maskEntry('-webkit-mask-box-image-source', $value) : null,
                            $entry,
                        ])),
                        true,
                    ];
                }

                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-box-image-source', $value), $entry] : [$entry], true];

            case 'mask-border-slice':
                $value = $this->normalizeMaskBorderComponent('slice', $entry['value']);
                $entry['value'] = $value;

                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-box-image-slice', $value), $entry] : [$entry], true];

            case 'mask-border-width':
                $value = $this->normalizeMaskBorderComponent('width', $entry['value']);
                $entry['value'] = $value;

                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-box-image-width', $value), $entry] : [$entry], true];

            case 'mask-border-outset':
                $value = $this->normalizeMaskBorderComponent('outset', $entry['value']);
                $entry['value'] = $value;

                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-box-image-outset', $value), $entry] : [$entry], true];

            case 'mask-border-repeat':
                $value = $this->normalizeMaskBorderComponent('repeat', $entry['value']);
                $entry['value'] = $value;

                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-box-image-repeat', $value), $entry] : [$entry], true];

            case 'mask-composite':
                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-composite', $this->mapWebkitMaskComposite($entry['value'])), $entry] : [$entry], $needsWebkit];

            case 'mask-mode':
                return [$needsWebkit ? [$this->maskEntry('-webkit-mask-source-type', strtolower(trim($entry['value']))), $entry] : [$entry], $needsWebkit];
        }

        return [[$entry], false];
    }

    /**
     * @param array<string,string> $components
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function maskLayerEntries(array $components, bool $needsWebkit): array
    {
        $entries = [];
        if ($needsWebkit) {
            $entries[] = $this->maskEntry('-webkit-mask', $this->composeMaskLayerValue($components, false));
        }
        if ($needsWebkit && isset($components['composite'])) {
            $entries[] = $this->maskEntry('-webkit-mask-composite', $this->mapWebkitMaskComposite($components['composite']));
        }
        if ($needsWebkit && isset($components['mode']) && strtolower($components['mode']) !== 'alpha') {
            $entries[] = $this->maskEntry('-webkit-mask-source-type', strtolower($components['mode']));
        }
        $entries[] = $this->maskEntry('mask', $this->composeMaskLayerValue($components, true));

        return $entries;
    }

    /**
     * @param array<string,string> $components
     */
    private function composeMaskLayerValue(array $components, bool $includeCompositeAndMode): string
    {
        $value = $components['image'] ?? 'none';
        if (isset($components['position'])) {
            $value .= ' ' . $components['position'];
        }
        if (isset($components['size'])) {
            $value .= '/' . $components['size'];
        }
        if (isset($components['repeat'])) {
            $value .= ' ' . $components['repeat'];
        }
        $origin = $components['origin'] ?? null;
        $clip = $components['clip'] ?? null;
        if ($origin !== null && $clip !== null) {
            $value .= $origin === $clip ? ' ' . $origin : ' ' . $origin . ' ' . $clip;
        } elseif ($origin !== null && strtolower($origin) !== 'border-box') {
            $value .= ' ' . $origin;
        } elseif ($clip !== null && strtolower($clip) !== 'border-box') {
            $value .= ' ' . $clip;
        }

        if ($includeCompositeAndMode && isset($components['composite'])) {
            $value .= ' ' . $components['composite'];
        }
        if ($includeCompositeAndMode && isset($components['mode']) && strtolower($components['mode']) !== 'alpha') {
            $value .= ' ' . $components['mode'];
        }

        return $value;
    }

    private function normalizeMaskLayerComponent(string $component, string $value): string
    {
        $value = trim($value);

        return match ($component) {
            'image' => $this->normalizeMaskImageValue($value),
            'repeat' => $this->compressRepeatValue($value),
            'origin',
            'clip',
            'composite',
            'mode' => strtolower($value),
            default => $this->normalizeMaskShorthandSpacing($value),
        };
    }

    /**
     * @param array<string,string> $components
     */
    private function composeMaskBorderValue(array $components, bool $includeMode): string
    {
        $value = ($components['source'] ?? 'none') . ' ' . ($components['slice'] ?? '100%');
        $width = $components['width'] ?? null;
        $outset = $components['outset'] ?? null;
        if ($width !== null || ($outset !== null && $outset !== '0')) {
            $value .= '/' . ($width ?? '1');
            if ($outset !== null && $outset !== '0') {
                $value .= '/' . $outset;
            }
        }

        $repeat = $components['repeat'] ?? null;
        if ($repeat !== null && strtolower($repeat) !== 'stretch') {
            $value .= ' ' . $repeat;
        }

        $mode = strtolower($components['mode'] ?? 'alpha');
        if ($includeMode && $mode !== 'alpha') {
            $value .= ' ' . $mode;
        }

        return $value;
    }

    private function normalizeMaskBorderComponent(string $component, string $value): string
    {
        $value = trim($value);

        return match ($component) {
            'source' => $this->normalizeUrlToken($value),
            'slice',
            'width',
            'outset' => $this->compressBoxValue($value),
            'repeat' => $this->compressRepeatValue($value),
            'mode' => strtolower($value),
            default => $value,
        };
    }

    private function normalizeMaskBorderShorthand(string $value, bool $includeMode): string
    {
        $mode = null;
        $tokens = [];
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($lower === 'alpha' || $lower === 'luminance') {
                $mode = $lower;
                continue;
            }
            $tokens[] = $this->normalizeUrlToken($token);
        }

        $normalized = preg_replace('/\s*\/\s*/', '/', implode(' ', $tokens)) ?? implode(' ', $tokens);
        if ($includeMode && $mode !== null && $mode !== 'alpha') {
            $normalized .= ' ' . $mode;
        }

        return trim($normalized);
    }

    private function normalizeMaskShorthand(string $value, bool $includeMode): string
    {
        $mode = null;
        $tokens = [];
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($lower === 'alpha' || $lower === 'luminance') {
                $mode = $lower;
                continue;
            }
            $tokens[] = $this->normalizeMaskShorthandToken($token);
        }

        $normalized = $this->normalizeMaskShorthandSpacing(implode(' ', $tokens));
        if ($includeMode && $mode !== null && $mode !== 'alpha') {
            $normalized .= ' ' . $mode;
        }

        return trim($normalized);
    }

    private function maskShorthandMode(string $value): ?string
    {
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($lower === 'alpha' || $lower === 'luminance') {
                return $lower;
            }
        }

        return null;
    }

    private function normalizeMaskImageValue(string $value): string
    {
        return implode(',', array_map(
            fn (string $part): string => $this->normalizeMaskShorthandToken($part),
            $this->splitTopLevel($value, ',')
        ));
    }

    private function normalizeMaskShorthandToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^url\(/i', $token) === 1) {
            return $this->normalizeQuotedUrlToken($token);
        }

        return $this->normalizeMaskShorthandSpacing($token);
    }

    private function normalizeMaskShorthandSpacing(string $value): string
    {
        return preg_replace('/\s*\/\s*/', '/', trim($value)) ?? trim($value);
    }

    private function isPrefixableMaskImageValue(string $value): bool
    {
        return stripos($value, 'var(') === false;
    }

    private function advancedColorFallbackValue(string $value): ?string
    {
        return $this->mapAdvancedColorValue(
            $value,
            fn (string $color): ?string => $this->knownAdvancedColorFallback($color)
        );
    }

    private function advancedColorP3FallbackValue(string $value, bool $allowIdentity = false): ?string
    {
        return $this->mapAdvancedColorValue(
            $value,
            fn (string $color): ?string => $this->knownAdvancedColorP3Fallback($color),
            $allowIdentity
        );
    }

    private function advancedColorLabFallbackValue(string $value, bool $allowIdentity = false): ?string
    {
        return $this->mapAdvancedColorValue(
            $value,
            fn (string $color): ?string => $this->knownAdvancedColorLabFallback($color),
            $allowIdentity
        );
    }

    private function advancedColorLabTargetValue(string $value): ?string
    {
        if (preg_match('/\b(?:oklab|oklch)\(/i', $value) !== 1) {
            return null;
        }

        return $this->advancedColorLabFallbackValue($value);
    }

    private function containsCustomPropertyReference(string $value): bool
    {
        return stripos($value, 'var(') !== false;
    }

    /**
     * @param callable(string): ?string $mapper
     */
    private function mapAdvancedColorValue(string $value, callable $mapper, bool $allowIdentity = false): ?string
    {
        $matched = false;
        $unknown = false;
        $fallback = preg_replace_callback(
            '/\b(lch|lab|oklab|oklch|color)\(([^()]*)\)/i',
            function (array $matches) use (&$matched, &$unknown, $mapper): string {
                $matched = true;
                $normalized = strtolower($matches[1]) . '(' . $this->normalizeColorFunctionArguments($matches[2]) . ')';
                $color = $mapper($normalized);
                if ($color === null) {
                    $unknown = true;

                    return $matches[0];
                }

                return $color;
            },
            $value
        ) ?? $value;

        if (!$matched || $unknown || (!$allowIdentity && $fallback === $value)) {
            return null;
        }

        return $fallback;
    }

    private function normalizeColorFunctionArguments(string $arguments): string
    {
        $arguments = preg_replace('/\s+/', ' ', trim($arguments)) ?? trim($arguments);
        $arguments = preg_replace('/\s*,\s*/', ',', $arguments) ?? $arguments;

        return $arguments;
    }

    private function knownAdvancedColorFallback(string $color): ?string
    {
        return match ($color) {
            'lab(40% 56.6 39)' => '#b32323',
            'lab(51.5117% 43.3777 -29.0443)' => '#af5cae',
            'lab(52.2319% 40.1449 59.9171)',
            'oklab(59.686% .1009 .1192)',
            'oklab(59.686% 0.1009 0.1192)' => '#c65d07',
            'oklch(59.686% .15619 49.7694)',
            'oklch(59.686% 0.15619 49.7694)' => '#c65d06',
            'oklch(40% .12687354 34.568626)',
            'oklch(40% 0.1268735435 34.568626)' => '#7e250f',
            'oklch(100% 0 0/.5)',
            'oklch(100% 0 0deg/50%)' => '#ffffff80',
            'lab(47.7776% -34.2947 -7.65904)',
            'oklab(54% -.1 -.02)',
            'oklab(54.0% -0.10 -0.02)' => '#00807c',
            'lch(56.208% 136.76 46.312)',
            'lab(56.208% 94.4644 98.8928)' => '#ff0f0e',
            'lch(51% 135.366 301.364)',
            'lab(51% 70.4544 -115.586)' => '#7773ff',
            'color(srgb .41587 .50367 .36664)' => '#6a805d',
            'color(display-p3 .43313 .50108 .3795)' => '#6a805d',
            'color(display-p3 .643308 .192455 .167712)' => '#b32323',
            'color(display-p3 0 .5 1)' => '#4263eb',
            'color(display-p3 0 1 0)' => '#00f942',
            'color(a98-rgb .44091 .49971 .37408)' => '#6a805d',
            'color(prophoto-rgb .36589 .41717 .31333)' => '#6a805d',
            'color(rec2020 .4221 .4758 .35605)' => '#728765',
            'color(xyz-d50 .2005 .14089 .4472)' => '#7654cd',
            'color(xyz .0771883 .154377 .0257295/.65)' => '#008000a6',
            'color(xyz .21661 .14602 .59452)' => '#7654cd',
            'lch(50.998% 135.363 338)' => '#ee00be',
            default => null,
        };
    }

    private function knownAdvancedColorP3Fallback(string $color): ?string
    {
        if (preg_match('/^color\(display-p3\b/', $color) === 1) {
            return $color;
        }

        return match ($color) {
            'lab(40% 56.6 39)' => 'color(display-p3 .643308 .192455 .167712)',
            'lab(52.2319% 40.1449 59.9171)',
            'oklab(59.686% .1009 .1192)',
            'oklab(59.686% 0.1009 0.1192)' => 'color(display-p3 .724144 .386777 .148795)',
            'lch(56.208% 136.76 46.312)',
            'lab(56.208% 94.4644 98.8928)' => 'color(display-p3 1 .0000153435 -.00000303562)',
            'lch(51% 135.366 301.364)',
            'lab(51% 70.4544 -115.586)' => 'color(display-p3 .440289 .28452 1.23485)',
            'lch(50.998% 135.363 338)',
            'lab(50.998% 125.506 -50.7078)' => 'color(display-p3 .972962 -.362078 .804206)',
            'oklch(100% 0 0/.5)',
            'oklch(100% 0 0deg/50%)' => 'color(display-p3 1 1 1 / .5)',
            default => null,
        };
    }

    private function knownAdvancedColorLabFallback(string $color): ?string
    {
        return match ($color) {
            'lab(40% 56.6 39)' => 'lab(40% 56.6 39)',
            'lab(51.5117% 43.3777 -29.0443)' => 'lab(51.5117% 43.3777 -29.0443)',
            'lab(52.2319% 40.1449 59.9171)',
            'oklab(59.686% .1009 .1192)',
            'oklab(59.686% 0.1009 0.1192)' => 'lab(52.2319% 40.1449 59.9171)',
            'oklch(59.686% .15619 49.7694)',
            'oklch(59.686% 0.15619 49.7694)' => 'lab(52.2321% 40.1417 59.9527)',
            'oklch(40% .12687354 34.568626)',
            'oklch(40% 0.1268735435 34.568626)' => 'lab(29.2661% 38.2437 35.3889)',
            'oklch(100% 0 0/.5)',
            'oklch(100% 0 0deg/50%)' => 'lab(100% 0 0 / .5)',
            'lab(47.7776% -34.2947 -7.65904)',
            'oklab(54% -.1 -.02)',
            'oklab(54.0% -0.10 -0.02)' => 'lab(47.7776% -34.2947 -7.65904)',
            'lch(56.208% 136.76 46.312)',
            'lab(56.208% 94.4644 98.8928)' => 'lab(56.208% 94.4644 98.8928)',
            'lch(51% 135.366 301.364)',
            'lab(51% 70.4544 -115.586)' => 'lab(51% 70.4544 -115.586)',
            'lch(50.998% 135.363 338)',
            'lab(50.998% 125.506 -50.7078)' => 'lab(50.998% 125.506 -50.7078)',
            default => null,
        };
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function supportsLabRule(string $selectors, array $entries): string
    {
        return '@supports (color:lab(0% 0 0)){' . $selectors . '{' . $this->serializeDeclarations($entries) . '}}';
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function supportsP3Rule(string $selectors, array $entries): string
    {
        return '@supports (color:color(display-p3 0 0 0)){' . $selectors . '{' . $this->serializeDeclarations($entries) . '}}';
    }

    private function normalizeBackgroundFallbackValue(string $value): string
    {
        return preg_replace_callback(
            '/url\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)/i',
            fn (array $matches): string => $this->normalizeQuotedUrlToken($matches[0]),
            $value
        ) ?? $value;
    }

    private function normalizeQuotedUrlToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^url\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)$/i', $token, $matches) !== 1) {
            return $token;
        }

        $url = $matches[2] !== '' ? $matches[2] : trim($matches[3]);

        return 'url("' . str_replace('"', '\\"', $url) . '")';
    }

    /**
     * @param array{property:string,name:string,value:string,important:bool} $entry
     * @return array{property:string,name:string,value:string,important:bool}
     */
    private function entryWithValue(array $entry, string $value): array
    {
        $entry['value'] = $value;

        return $entry;
    }

    private function isBoxShadowProperty(string $property): bool
    {
        return in_array($property, ['box-shadow', '-webkit-box-shadow', '-moz-box-shadow'], true);
    }

    private function isLegacyBoxShadowProperty(string $property): bool
    {
        return in_array($property, ['-webkit-box-shadow', '-moz-box-shadow'], true);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function hasMatchingUnprefixedBoxShadow(array $entries, string $value): bool
    {
        foreach ($entries as $entry) {
            if (!$entry['important'] && $entry['property'] === 'box-shadow' && $entry['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function hasPreviousUnprefixedBoxShadow(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (!$entry['important'] && $entry['property'] === 'box-shadow') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @return array{0:list<array{property:string,name:string,value:string,important:bool}>,1:bool}
     */
    private function dropPreviousBoxShadowFallbacks(array $entries): array
    {
        $dropped = false;
        $kept = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'box-shadow' && !$entry['important'] && !$this->containsCustomPropertyReference($entry['value'])) {
                $dropped = true;
                continue;
            }

            $kept[] = $entry;
        }

        return [$kept, $dropped];
    }

    private function expandLegacyAlphaHexColors(string $value): string
    {
        return $this->expandAlphaHexColors($value, false, false);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteAlphaHexFallbackEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['alphaHexNeedsRgbaFallback'] ?? false)) {
            return false;
        }

        $changed = false;
        foreach ($entries as &$entry) {
            $value = $this->expandAlphaHexColors($entry['value'], true, true);
            if ($value === $entry['value']) {
                continue;
            }

            $entry['value'] = $value;
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     * @param array<string, bool> $targetOptions
     */
    private function rewriteModernColorFunctionEntries(array &$entries, array $targetOptions): bool
    {
        if (!($targetOptions['modernColorNeedsCanonicalization'] ?? false)) {
            return false;
        }

        $legacySyntax = $targetOptions['modernColorNeedsLegacySyntax'] ?? false;
        $changed = false;
        foreach ($entries as &$entry) {
            $rewritten = $this->rewriteModernColorFunctionValue($entry['value'], $legacySyntax);
            if ($rewritten === $entry['value']) {
                continue;
            }

            $entry['value'] = $rewritten;
            $changed = true;
        }
        unset($entry);

        return $changed;
    }

    private function rewriteModernColorFunctionValue(string $value, bool $legacySyntax): string
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
                $lower = strtolower($identifier);
                $next = $value[$i + strlen($identifier)] ?? '';
                if ($next === '(' && $lower === 'url') {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $function;
                    $i = $offset;
                    continue;
                }

                if ($next === '(' && in_array($lower, ['rgb', 'rgba', 'hsl', 'hsla'], true)) {
                    [$function, $offset] = $this->readFunctionRaw($value, $i);
                    $output .= $this->modernColorFunctionReplacement($lower, $function, $legacySyntax) ?? $function;
                    $i = $offset;
                    continue;
                }

                $output .= $identifier;
                $i += strlen($identifier) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function modernColorFunctionReplacement(string $name, string $function, bool $legacySyntax): ?string
    {
        if (preg_match('/^[^(]+\((.*)\)$/s', trim($function), $matches) !== 1) {
            return null;
        }

        $parts = $this->splitTopLevel($matches[1], '/');
        if (count($parts) !== 2 || trim($parts[1]) === '' || !$this->modernColorAlphaRequiresFallback($parts[1])) {
            return null;
        }

        $alpha = trim($parts[1]);
        if ($name === 'rgb' || $name === 'rgba') {
            $rgb = $this->parseModernRgbComponents($parts[0]);
            if ($rgb === null) {
                return null;
            }

            if ($legacySyntax) {
                return 'rgba(' . implode(',', $rgb) . ',' . $alpha . ')';
            }

            return 'rgb(' . implode(' ', $rgb) . '/' . $alpha . ')';
        }

        if (!$legacySyntax) {
            return null;
        }

        $hsl = $this->parseModernHslComponents($parts[0]);
        if ($hsl === null) {
            return null;
        }

        return 'hsla(' . implode(',', $hsl) . ',' . $alpha . ')';
    }

    private function modernColorAlphaRequiresFallback(string $alpha): bool
    {
        return preg_match('/\b(?:var|calc)\(/i', $alpha) === 1;
    }

    /**
     * @return list<int>|null
     */
    private function parseModernRgbComponents(string $components): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($components));
        if (count($tokens) === 5 && strtolower($tokens[0]) === 'from') {
            $origin = strtolower($tokens[1]);
            if ($origin === 'yellow' && array_slice($tokens, 2) === ['r', 'g', 'b']) {
                return [255, 255, 0];
            }

            return null;
        }

        if (count($tokens) !== 3) {
            return null;
        }

        $rgb = [];
        foreach ($tokens as $token) {
            $component = $this->parseModernRgbNumericComponent($token);
            if ($component === null) {
                return null;
            }
            $rgb[] = $component;
        }

        return $rgb;
    }

    private function parseModernRgbNumericComponent(string $token): ?int
    {
        $token = trim($token);
        if (strcasecmp($token, 'none') === 0 || preg_match('/\b(?:var|calc)\(/i', $token) === 1) {
            return null;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) === 1) {
            return min(255, max(0, (int) round(((float) $matches[1] / 100) * 255)));
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1) {
            return min(255, max(0, (int) round((float) $token)));
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function parseModernHslComponents(string $components): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($components));
        if (count($tokens) === 5 && strtolower($tokens[0]) === 'from') {
            $origin = strtolower($tokens[1]);
            if ($origin === 'yellow' && array_slice($tokens, 2) === ['h', 's', 'l']) {
                return ['60', '100%', '50%'];
            }

            return null;
        }

        if (count($tokens) !== 3) {
            return null;
        }

        if (strcasecmp($tokens[0], 'none') === 0 || preg_match('/\b(?:var|calc)\(/i', $tokens[0]) === 1) {
            return null;
        }
        if (strcasecmp($tokens[1], 'none') === 0 || strcasecmp($tokens[2], 'none') === 0) {
            return null;
        }
        if (preg_match('/\b(?:var|calc)\(/i', $tokens[1] . ' ' . $tokens[2]) === 1) {
            return null;
        }

        $hue = $this->normalizeModernColorHueToken($tokens[0]);
        if ($hue === null || preg_match('/^[+-]?(?:\d+|\d*\.\d+)%$/', $tokens[1]) !== 1 || preg_match('/^[+-]?(?:\d+|\d*\.\d+)%$/', $tokens[2]) !== 1) {
            return null;
        }

        return [$hue, $tokens[1], $tokens[2]];
    }

    private function normalizeModernColorHueToken(string $token): ?string
    {
        $token = trim($token);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(deg)?$/i', $token, $matches) !== 1) {
            return null;
        }

        $value = rtrim(rtrim(sprintf('%.6F', (float) $matches[1]), '0'), '.');

        return $value === '-0' ? '0' : $value;
    }

    private function expandAlphaHexColors(string $value, bool $compact, bool $transparentBlack): string
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

            if (strtolower(substr($value, $i, 4)) === 'url(' && ($i === 0 || !$this->isIdentifierChar($value[$i - 1]))) {
                [$function, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $function;
                $i = $offset;
                continue;
            }

            if ($char === '#'
                && preg_match('/^#([0-9a-f]{4}|[0-9a-f]{8})(?![0-9a-f])/i', substr($value, $i), $matches) === 1
            ) {
                $output .= $this->alphaHexReplacement($matches[1], $compact, $transparentBlack);
                $i += strlen($matches[0]) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function alphaHexReplacement(string $hex, bool $compact, bool $transparentBlack): string
    {
        $hex = strtolower($hex);
        if (strlen($hex) === 4) {
            [$red, $green, $blue, $alpha] = str_split($hex);
            $red .= $red;
            $green .= $green;
            $blue .= $blue;
            $alpha .= $alpha;
        } else {
            $red = substr($hex, 0, 2);
            $green = substr($hex, 2, 2);
            $blue = substr($hex, 4, 2);
            $alpha = substr($hex, 6, 2);
        }

        if ($transparentBlack && $red === '00' && $green === '00' && $blue === '00' && $alpha === '00') {
            return 'transparent';
        }

        $separator = $compact ? ',' : ', ';

        return 'rgba('
            . hexdec($red)
            . $separator
            . hexdec($green)
            . $separator
            . hexdec($blue)
            . $separator
            . $this->formatAlphaHex((int) hexdec($alpha))
            . ')';
    }

    private function formatAlphaHex(int $alpha): string
    {
        for ($precision = 0; $precision <= 3; $precision++) {
            $candidate = rtrim(rtrim(sprintf('%.' . $precision . 'F', $alpha / 255), '0'), '.');
            if ($candidate === '') {
                $candidate = '0';
            }
            if ((int) round((float) $candidate * 255) === $alpha) {
                $formatted = $candidate;

                return str_starts_with($formatted, '0.') ? substr($formatted, 1) : $formatted;
            }
        }

        $formatted = rtrim(rtrim(sprintf('%.3F', $alpha / 255), '0'), '.');

        return str_starts_with($formatted, '0.') ? substr($formatted, 1) : $formatted;
    }

    /**
     * @return array{property:string,name:string,value:string,important:bool}
     */
    private function maskEntry(string $property, string $value): array
    {
        return $this->declarationEntry($property, $value);
    }

    /**
     * @return array{property:string,name:string,value:string,important:bool}
     */
    private function declarationEntry(string $property, string $value, bool $important = false): array
    {
        return [
            'property' => $property,
            'name' => $property,
            'value' => $value,
            'important' => $important,
        ];
    }

    private function normalizeUrlToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^url\(\s*([\'"])(.*?)\1\s*\)$/i', $token, $matches) !== 1) {
            return $token;
        }

        $url = $matches[2];
        if (preg_match('/[\s\'"()\\\\]/', $url) === 1) {
            return 'url("' . str_replace('"', '\\"', $url) . '")';
        }

        return 'url(' . $url . ')';
    }

    private function compressBoxValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) === 4 && $tokens[3] === $tokens[1]) {
            array_pop($tokens);
        }
        if (count($tokens) === 3 && $tokens[2] === $tokens[0]) {
            array_pop($tokens);
        }
        if (count($tokens) === 2 && $tokens[1] === $tokens[0]) {
            array_pop($tokens);
        }

        return implode(' ', $tokens);
    }

    private function compressRepeatValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel(strtolower(trim($value)));

        return count($tokens) === 2 && $tokens[0] === $tokens[1] ? $tokens[0] : implode(' ', $tokens);
    }

    private function mapWebkitMaskComposite(string $value): string
    {
        return match (strtolower(trim($value))) {
            'subtract' => 'source-out',
            'intersect' => 'source-in',
            'exclude' => 'xor',
            'add' => 'source-over',
            default => strtolower(trim($value)),
        };
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function rewriteTransitionPropertyListForDirection(string $value, string $direction): array
    {
        $changed = false;
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $mapped = $this->mapInlinePhysicalProperty($part, $direction);
            $changed = $changed || $mapped !== trim($part);
            $parts[] = $mapped;
        }

        return [implode(',', $parts), $changed];
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function rewriteTransitionShorthandForDirection(string $value, string $direction): array
    {
        $changed = false;
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            [$rewrittenLayer, $layerChanged] = $this->rewriteTransitionLayerProperty(
                $layer,
                fn (string $property): string => $this->mapInlinePhysicalProperty($property, $direction)
            );
            $changed = $changed || $layerChanged;
            $layers[] = $rewrittenLayer;
        }

        return [implode(',', $layers), $changed];
    }

    /**
     * @return array{0:string,1:bool,2:bool}
     */
    private function rewritePrefixedTransitionPropertyList(string $value, array $targetOptions): array
    {
        $changed = false;
        $needsPrefixedTransition = false;
        $parts = [];
        $seen = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $part = trim($part);
            $expansion = $this->prefixedTransitionPropertyExpansion($part, $targetOptions);
            foreach ($expansion['properties'] as $property) {
                $key = strtolower($property);
                if (isset($seen[$key])) {
                    $changed = true;
                    continue;
                }

                $seen[$key] = true;
                $parts[] = $property;
            }
            $changed = $changed || $expansion['properties'] !== [$part];
            $needsPrefixedTransition = $needsPrefixedTransition || $expansion['needsPrefixedTransition'];
        }

        return [implode(',', $parts), $changed, $needsPrefixedTransition];
    }

    /**
     * @return array{0:string,1:bool,2:bool}
     */
    private function rewritePrefixedTransitionShorthand(string $value, array $targetOptions): array
    {
        $changed = false;
        $needsPrefixedTransition = false;
        $layers = [];
        $seenLayers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $tokens = $this->splitWhitespaceTopLevel($layer);
            $propertyIndex = $this->transitionPropertyTokenIndex($tokens);
            if ($propertyIndex !== null) {
                $expansion = $this->prefixedTransitionPropertyExpansion($tokens[$propertyIndex], $targetOptions);
                if ($expansion['properties'] !== [$tokens[$propertyIndex]]) {
                    foreach ($expansion['properties'] as $property) {
                        $expanded = $tokens;
                        $expanded[$propertyIndex] = $property;
                        $serialized = implode(' ', $expanded);
                        $key = strtolower($serialized);
                        if (isset($seenLayers[$key])) {
                            continue;
                        }

                        $seenLayers[$key] = true;
                        $layers[] = $serialized;
                    }
                    $changed = true;
                    $needsPrefixedTransition = $needsPrefixedTransition || $expansion['needsPrefixedTransition'];
                    continue;
                }
            }

            $serialized = implode(' ', $tokens);
            $key = strtolower($serialized);
            if (isset($seenLayers[$key])) {
                $changed = true;
                continue;
            }

            $seenLayers[$key] = true;
            $layers[] = $serialized;
        }

        return [implode(',', $layers), $changed, $needsPrefixedTransition];
    }

    /**
     * @return array{properties:non-empty-list<string>,needsPrefixedTransition:bool}
     */
    private function prefixedTransitionPropertyExpansion(string $property, array $targetOptions): array
    {
        $trimmed = trim($property);
        $needsMaskWebkit = $targetOptions['maskNeedsWebkit'] ?? false;
        $needsBackdropFilterWebkit = $targetOptions['backdropFilterNeedsWebkit'] ?? false;

        $maskProperties = [
            'mask' => '-webkit-mask',
            'mask-border' => '-webkit-mask-box-image',
            'mask-composite' => '-webkit-mask-composite',
            'mask-mode' => '-webkit-mask-source-type',
        ];
        $lower = strtolower($trimmed);
        foreach ($maskProperties as $unprefixed => $prefixed) {
            if ($lower === $unprefixed) {
                return [
                    'properties' => $needsMaskWebkit ? [$prefixed, $unprefixed] : [$unprefixed],
                    'needsPrefixedTransition' => false,
                ];
            }
            if ($lower === $prefixed) {
                return [
                    'properties' => $needsMaskWebkit ? [$prefixed] : [$unprefixed],
                    'needsPrefixedTransition' => false,
                ];
            }
        }

        if ($lower === 'backdrop-filter') {
            return [
                'properties' => $needsBackdropFilterWebkit ? ['-webkit-backdrop-filter', 'backdrop-filter'] : ['backdrop-filter'],
                'needsPrefixedTransition' => false,
            ];
        }

        if ($lower === '-webkit-backdrop-filter') {
            return [
                'properties' => $needsBackdropFilterWebkit ? ['-webkit-backdrop-filter'] : ['backdrop-filter'],
                'needsPrefixedTransition' => false,
            ];
        }

        return match ($lower) {
            'transform' => [
                'properties' => ['-webkit-transform', 'transform'],
                'needsPrefixedTransition' => true,
            ],
            default => [
                'properties' => [$trimmed],
                'needsPrefixedTransition' => false,
            ],
        };
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function rewriteTransitionLayerProperty(string $layer, callable $mapper): array
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        $propertyIndex = $this->transitionPropertyTokenIndex($tokens);
        if ($propertyIndex === null) {
            return [$layer, false];
        }

        $property = $tokens[$propertyIndex];
        $mapped = $mapper($property);
        if ($mapped === $property) {
            return [implode(' ', $tokens), false];
        }

        $tokens[$propertyIndex] = $mapped;

        return [implode(' ', $tokens), true];
    }

    /**
     * @param list<string> $tokens
     */
    private function transitionPropertyTokenIndex(array $tokens): ?int
    {
        foreach ($tokens as $index => $token) {
            $lower = strtolower($token);
            if ($this->isTimeToken($lower) || $this->isTimingFunctionToken($lower) || $lower === 'normal' || $lower === 'allow-discrete') {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function mapInlinePhysicalProperty(string $property, string $direction): string
    {
        return match (strtolower(trim($property))) {
            'margin-inline-start' => $direction === 'rtl' ? 'margin-right' : 'margin-left',
            'margin-inline-end' => $direction === 'rtl' ? 'margin-left' : 'margin-right',
            'padding-inline-start' => $direction === 'rtl' ? 'padding-right' : 'padding-left',
            'padding-inline-end' => $direction === 'rtl' ? 'padding-left' : 'padding-right',
            'inset-inline-start' => $direction === 'rtl' ? 'right' : 'left',
            'inset-inline-end' => $direction === 'rtl' ? 'left' : 'right',
            default => trim($property),
        };
    }

    private function selectorVariant(string $selectors, string $variant): string
    {
        $suffix = match ($variant) {
            'ltr-webkit' => ':not(' . $this->rtlPseudo('-webkit-any') . ')',
            'ltr-modern' => ':not(' . $this->rtlPseudo('is') . ')',
            'rtl-webkit' => $this->rtlPseudo('-webkit-any'),
            'rtl-modern' => $this->rtlPseudo('is'),
        };

        return implode(',', array_map(
            static fn (string $selector): string => trim($selector) . $suffix,
            $this->splitTopLevel($selectors, ',')
        ));
    }

    private function directionSelectorVariant(string $selectors, string $direction): string
    {
        $suffix = $direction === 'rtl'
            ? $this->rtlPseudo('is')
            : ':not(' . $this->rtlPseudo('is') . ')';

        return implode(',', array_map(
            fn (string $selector): string => $this->insertSelectorSuffixBeforePseudoElement(trim($selector), $suffix),
            $this->splitTopLevel($selectors, ',')
        ));
    }

    private function insertSelectorSuffixBeforePseudoElement(string $selector, string $suffix): string
    {
        if (preg_match('/(?:::(?:before|after|first-letter|first-line)|:(?:before|after|first-letter|first-line))\b/i', $selector, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $selector . $suffix;
        }

        $offset = $matches[0][1];

        return substr($selector, 0, $offset) . $suffix . substr($selector, $offset);
    }

    private function rtlPseudo(string $function): string
    {
        return ':' . $function . '(' . implode(',', array_map(
            static fn (string $language): string => ':lang(' . $language . ')',
            self::RTL_LANGS
        )) . ')';
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function splitImportantFlag(string $value): array
    {
        if (preg_match('/^(.*?)!\s*important$/i', $value, $matches) === 1) {
            return [$matches[1], true];
        }

        return [$value, false];
    }

    private function isTimeToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:ms|s)$/i', $token) === 1;
    }

    private function isTimingFunctionToken(string $token): bool
    {
        return in_array($token, ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'], true)
            || preg_match('/^(?:cubic-bezier|steps)\(/', $token) === 1;
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

        return $length - 1;
    }

    /**
     * @return array{0:string,1:int}
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

    private function readIdentifier(string $value, int $start): string
    {
        $length = strlen($value);
        $end = $start;
        while ($end < $length && $this->isIdentifierChar($value[$end])) {
            $end++;
        }

        return substr($value, $start, $end - $start);
    }

    private function isIdentifierStart(string $char): bool
    {
        return preg_match('/[-_a-zA-Z]/', $char) === 1;
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/[-_a-zA-Z0-9]/', $char) === 1;
    }
}
