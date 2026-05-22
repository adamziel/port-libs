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

    public function prefixLegacySafari(string $css): string
    {
        return $this->rewriteRuleList((new CssMinifier())->minify($css));
    }

    private function rewriteRuleList(string $css, bool $insideAdvancedColorSupports = false): string
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

            $close = $this->findMatchingBrace($css, $open);
            $prelude = trim(substr($css, $cursor, $open - $cursor));
            $body = substr($css, $open + 1, $close - $open - 1);
            if (str_starts_with($prelude, '@')) {
                $output .= $prelude . '{' . $this->rewriteRuleList(
                    $body,
                    $insideAdvancedColorSupports || $this->isAdvancedColorSupportsPrelude($prelude)
                ) . '}';
            } else {
                $output .= $this->rewriteStyleRule($prelude, $body, $insideAdvancedColorSupports);
            }
            $cursor = $close + 1;
        }

        return $output;
    }

    private function rewriteStyleRule(string $selectors, string $body, bool $insideAdvancedColorSupports): string
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
            $this->rewritePrefixedTransitionEntries($ltrEntries);
            $this->rewritePrefixedTransitionEntries($rtlEntries);
            $this->rewriteMaskPrefixEntries($ltrEntries);
            $this->rewriteMaskPrefixEntries($rtlEntries);

            return $this->selectorVariant($selectors, 'ltr-webkit') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selectors, 'ltr-modern') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selectors, 'rtl-webkit') . '{' . $this->serializeDeclarations($rtlEntries) . '}'
                . $this->selectorVariant($selectors, 'rtl-modern') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        $transitionChanged = $this->rewritePrefixedTransitionEntries($entries);
        $supportRules = [];
        $maskChanged = $this->rewriteMaskPrefixEntries($entries, $selectors, $supportRules);
        $colorChanged = $insideAdvancedColorSupports
            ? false
            : $this->rewriteAdvancedColorFallbackEntries($entries, $selectors, $supportRules);
        if ($transitionChanged || $maskChanged || $colorChanged) {
            return $selectors . '{' . $this->serializeDeclarations($entries) . '}' . implode('', $supportRules);
        }

        return $selectors . '{' . $body . '}';
    }

    private function isAdvancedColorSupportsPrelude(string $prelude): bool
    {
        return preg_match('/^@supports\b/i', $prelude) === 1
            && preg_match('/:\s*(?:lab|lch|oklab|oklch|color)\(/i', $prelude) === 1;
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
     */
    private function rewritePrefixedTransitionEntries(array &$entries): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important']) {
                $rewritten[] = $entry;
                continue;
            }

            if ($entry['property'] === 'transition') {
                [$value, $entryChanged, $needsPrefixedTransition] = $this->rewritePrefixedTransitionShorthand($entry['value']);
                if ($entryChanged) {
                    if ($needsPrefixedTransition) {
                        $rewritten[] = [
                            'property' => '-webkit-transition',
                            'name' => '-webkit-transition',
                            'value' => $value,
                            'important' => false,
                        ];
                    }
                    $entry['value'] = $value;
                    $changed = true;
                }
                $rewritten[] = $entry;
                continue;
            }

            if ($entry['property'] === 'transition-property') {
                [$value, $entryChanged, $needsPrefixedTransition] = $this->rewritePrefixedTransitionPropertyList($entry['value']);
                if ($entryChanged) {
                    if ($needsPrefixedTransition) {
                        $rewritten[] = [
                            'property' => '-webkit-transition-property',
                            'name' => '-webkit-transition-property',
                            'value' => $value,
                            'important' => false,
                        ];
                    }
                    $entry['value'] = $value;
                    $changed = true;
                }
            }

            $rewritten[] = $entry;
        }

        $entries = $rewritten;

        return $changed;
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool}> $entries
     */
    private function rewriteMaskPrefixEntries(array &$entries, ?string $supportSelector = null, array &$supportRules = []): bool
    {
        $changed = false;
        $drop = [];
        $insertions = [];

        $hasWebkitMask = false;
        $hasWebkitMaskImage = false;
        foreach ($entries as $entry) {
            $hasWebkitMask = $hasWebkitMask || $entry['property'] === '-webkit-mask';
            $hasWebkitMaskImage = $hasWebkitMaskImage || $entry['property'] === '-webkit-mask-image';
        }

        foreach (['modern', 'webkit'] as $family) {
            $plan = $this->planMaskBorderComposition($entries, $family);
            if ($plan === null) {
                continue;
            }

            foreach ($plan['drop'] as $index) {
                $drop[$index] = true;
            }
            $insertions[$plan['replaceAt']] = array_merge($insertions[$plan['replaceAt']] ?? [], $plan['entries']);
            $changed = true;
        }

        $plan = $this->planMaskLayerComposition($entries);
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

            $result = $this->rewriteSingleMaskPrefixEntry($entry, $hasWebkitMask, $hasWebkitMaskImage);
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
     * @param list<string> $supportRules
     */
    private function rewriteAdvancedColorFallbackEntries(array &$entries, string $selectors, array &$supportRules): bool
    {
        $changed = false;
        $rewritten = [];
        $p3SupportEntries = [];
        $labSupportEntries = [];

        foreach ($entries as $entry) {
            if (!in_array($entry['property'], ['background', 'background-color', 'background-image'], true)) {
                $rewritten[] = $entry;
                continue;
            }

            $normalized = $this->normalizeBackgroundFallbackValue($entry['value']);
            $srgbFallback = $this->advancedColorFallbackValue($normalized);
            if ($srgbFallback === null) {
                $rewritten[] = $entry;
                continue;
            }

            $p3Fallback = $this->advancedColorP3FallbackValue($normalized);
            $labFallback = $this->advancedColorLabFallbackValue($normalized);
            $property = $entry['property'];
            $important = $entry['important'];
            $rewritten[] = $this->declarationEntry($property, $srgbFallback, $important);
            $changed = true;

            if ($this->containsCustomPropertyReference($normalized)) {
                if ($p3Fallback !== null && $p3Fallback !== $srgbFallback) {
                    $p3SupportEntries[] = $this->declarationEntry($property, $p3Fallback, $important);
                }
                if ($labFallback !== null && $labFallback !== $srgbFallback) {
                    $labSupportEntries[] = $this->declarationEntry($property, $labFallback, $important);
                }
                continue;
            }

            if ($p3Fallback !== null && $p3Fallback !== $srgbFallback && $p3Fallback !== $normalized) {
                $rewritten[] = $this->declarationEntry($property, $p3Fallback, $important);
            }

            $entry['value'] = $normalized;
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
     * @return array{replaceAt:int,drop:list<int>,entries:list<array{property:string,name:string,value:string,important:bool}>}|null
     */
    private function planMaskLayerComposition(array $entries): ?array
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
            array_push($planned, ...$this->maskLayerEntries($componentSet));
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
    private function planMaskBorderComposition(array $entries, string $family): ?array
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
            $planned[] = $this->maskEntry('-webkit-mask-box-image', $this->composeMaskBorderValue($componentSet, false));
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
    private function rewriteSingleMaskPrefixEntry(array $entry, bool $hasWebkitMask, bool $hasWebkitMaskImage): array
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
                        if (!$hasWebkitMask) {
                            $mapped[] = $this->maskEntry('-webkit-mask', $prefixedFallback);
                        }
                        $mapped[] = $this->maskEntry('mask', $modernFallback);

                        $supportEntries = [];
                        if (!$hasWebkitMask) {
                            $supportEntries[] = $this->maskEntry('-webkit-mask', $prefixedLab);
                        }
                        if ($mode !== null && strtolower($mode) !== 'alpha') {
                            $supportEntries[] = $this->maskEntry('-webkit-mask-source-type', strtolower($mode));
                        }
                        $supportEntries[] = $this->maskEntry('mask', $modernLab);

                        return [$mapped, true, $supportEntries];
                    }

                    $entry['value'] = $modern;
                    $mapped = [];
                    if (!$hasWebkitMask) {
                        $mapped[] = $this->maskEntry('-webkit-mask', $prefixedFallback);
                    }
                    $mapped[] = $this->maskEntry('mask', $modernFallback);
                    if (!$hasWebkitMask) {
                        $mapped[] = $this->maskEntry('-webkit-mask', $prefixed);
                    }
                    if ($mode !== null && strtolower($mode) !== 'alpha') {
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
                if (!$hasWebkitMask) {
                    $mapped[] = $this->maskEntry('-webkit-mask', $prefixed);
                }
                $mapped[] = $this->maskEntry('-webkit-mask-source-type', strtolower($mode));
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
                    if (!$hasWebkitMaskImage) {
                        $mapped[] = $this->maskEntry('-webkit-mask-image', $fallback);
                    }
                    $mapped[] = $this->maskEntry('mask-image', $fallback);
                    if (!$hasWebkitMaskImage) {
                        $mapped[] = $this->maskEntry('-webkit-mask-image', $entry['value']);
                    }
                    $mapped[] = $entry;

                    return [$mapped, true];
                }

                return [
                    $hasWebkitMaskImage ? [$entry] : [$this->maskEntry('-webkit-mask-image', $entry['value']), $entry],
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
                            [
                                $this->maskEntry('-webkit-mask-box-image', $prefixedFallback),
                                $this->maskEntry('mask-border', $modernFallback),
                            ],
                            true,
                            [
                                $this->maskEntry('-webkit-mask-box-image', $prefixedLab),
                                $this->maskEntry('mask-border', $modernLab),
                            ],
                        ];
                    }

                    return [
                        [
                            $this->maskEntry('-webkit-mask-box-image', $prefixedFallback),
                            $this->maskEntry('mask-border', $modernFallback),
                            $this->maskEntry('-webkit-mask-box-image', $prefixed),
                            $entry,
                        ],
                        true,
                    ];
                }

                return [[$this->maskEntry('-webkit-mask-box-image', $prefixed), $entry], true];

            case 'mask-border-source':
                $value = $this->normalizeMaskBorderComponent('source', $entry['value']);
                $entry['value'] = $value;
                $fallback = $this->advancedColorFallbackValue($value);
                if ($fallback !== null) {
                    return [
                        [
                            $this->maskEntry('-webkit-mask-box-image-source', $fallback),
                            $this->maskEntry('mask-border-source', $fallback),
                            $this->maskEntry('-webkit-mask-box-image-source', $value),
                            $entry,
                        ],
                        true,
                    ];
                }

                return [[$this->maskEntry('-webkit-mask-box-image-source', $value), $entry], true];

            case 'mask-border-slice':
                $value = $this->normalizeMaskBorderComponent('slice', $entry['value']);
                $entry['value'] = $value;

                return [[$this->maskEntry('-webkit-mask-box-image-slice', $value), $entry], true];

            case 'mask-border-width':
                $value = $this->normalizeMaskBorderComponent('width', $entry['value']);
                $entry['value'] = $value;

                return [[$this->maskEntry('-webkit-mask-box-image-width', $value), $entry], true];

            case 'mask-border-outset':
                $value = $this->normalizeMaskBorderComponent('outset', $entry['value']);
                $entry['value'] = $value;

                return [[$this->maskEntry('-webkit-mask-box-image-outset', $value), $entry], true];

            case 'mask-border-repeat':
                $value = $this->normalizeMaskBorderComponent('repeat', $entry['value']);
                $entry['value'] = $value;

                return [[$this->maskEntry('-webkit-mask-box-image-repeat', $value), $entry], true];

            case 'mask-composite':
                return [[$this->maskEntry('-webkit-mask-composite', $this->mapWebkitMaskComposite($entry['value'])), $entry], true];

            case 'mask-mode':
                return [[$this->maskEntry('-webkit-mask-source-type', strtolower(trim($entry['value']))), $entry], true];
        }

        return [[$entry], false];
    }

    /**
     * @param array<string,string> $components
     * @return list<array{property:string,name:string,value:string,important:bool}>
     */
    private function maskLayerEntries(array $components): array
    {
        $entries = [$this->maskEntry('-webkit-mask', $this->composeMaskLayerValue($components, false))];
        if (isset($components['composite'])) {
            $entries[] = $this->maskEntry('-webkit-mask-composite', $this->mapWebkitMaskComposite($components['composite']));
        }
        if (isset($components['mode']) && strtolower($components['mode']) !== 'alpha') {
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

    private function advancedColorP3FallbackValue(string $value): ?string
    {
        return $this->mapAdvancedColorValue(
            $value,
            fn (string $color): ?string => $this->knownAdvancedColorP3Fallback($color)
        );
    }

    private function advancedColorLabFallbackValue(string $value): ?string
    {
        return $this->mapAdvancedColorValue(
            $value,
            fn (string $color): ?string => $this->knownAdvancedColorLabFallback($color)
        );
    }

    private function containsCustomPropertyReference(string $value): bool
    {
        return stripos($value, 'var(') !== false;
    }

    /**
     * @param callable(string): ?string $mapper
     */
    private function mapAdvancedColorValue(string $value, callable $mapper): ?string
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

        if (!$matched || $unknown || $fallback === $value) {
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
            'oklab(59.686% 0.1009 0.1192)' => '#c65d07',
            'lab(47.7776% -34.2947 -7.65904)',
            'oklab(54.0% -0.10 -0.02)' => '#00807c',
            'lch(56.208% 136.76 46.312)',
            'lab(56.208% 94.4644 98.8928)' => '#ff0f0e',
            'lch(51% 135.366 301.364)',
            'lab(51% 70.4544 -115.586)' => '#7773ff',
            'color(display-p3 0 .5 1)' => '#4263eb',
            'color(display-p3 0 1 0)' => '#00f942',
            default => null,
        };
    }

    private function knownAdvancedColorP3Fallback(string $color): ?string
    {
        return match ($color) {
            'lab(40% 56.6 39)' => 'color(display-p3 .643308 .192455 .167712)',
            'lab(52.2319% 40.1449 59.9171)',
            'oklab(59.686% 0.1009 0.1192)' => 'color(display-p3 .724144 .386777 .148795)',
            'lch(56.208% 136.76 46.312)',
            'lab(56.208% 94.4644 98.8928)' => 'color(display-p3 1 .0000153435 -.00000303562)',
            'lch(51% 135.366 301.364)',
            'lab(51% 70.4544 -115.586)' => 'color(display-p3 .440289 .28452 1.23485)',
            'lch(50.998% 135.363 338)',
            'lab(50.998% 125.506 -50.7078)' => 'color(display-p3 .972962 -.362078 .804206)',
            default => null,
        };
    }

    private function knownAdvancedColorLabFallback(string $color): ?string
    {
        return match ($color) {
            'lab(40% 56.6 39)' => 'lab(40% 56.6 39)',
            'lab(51.5117% 43.3777 -29.0443)' => 'lab(51.5117% 43.3777 -29.0443)',
            'lab(52.2319% 40.1449 59.9171)',
            'oklab(59.686% 0.1009 0.1192)' => 'lab(52.2319% 40.1449 59.9171)',
            'lab(47.7776% -34.2947 -7.65904)',
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
    private function rewritePrefixedTransitionPropertyList(string $value): array
    {
        $changed = false;
        $needsPrefixedTransition = false;
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $part = trim($part);
            $expansion = $this->prefixedTransitionPropertyExpansion($part);
            foreach ($expansion['properties'] as $property) {
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
    private function rewritePrefixedTransitionShorthand(string $value): array
    {
        $changed = false;
        $needsPrefixedTransition = false;
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $tokens = $this->splitWhitespaceTopLevel($layer);
            $propertyIndex = $this->transitionPropertyTokenIndex($tokens);
            if ($propertyIndex !== null) {
                $expansion = $this->prefixedTransitionPropertyExpansion($tokens[$propertyIndex]);
                if ($expansion['properties'] !== [$tokens[$propertyIndex]]) {
                    foreach ($expansion['properties'] as $property) {
                        $expanded = $tokens;
                        $expanded[$propertyIndex] = $property;
                        $layers[] = implode(' ', $expanded);
                    }
                    $changed = true;
                    $needsPrefixedTransition = $needsPrefixedTransition || $expansion['needsPrefixedTransition'];
                    continue;
                }
            }
            $layers[] = implode(' ', $tokens);
        }

        return [implode(',', $layers), $changed, $needsPrefixedTransition];
    }

    /**
     * @return array{properties:non-empty-list<string>,needsPrefixedTransition:bool}
     */
    private function prefixedTransitionPropertyExpansion(string $property): array
    {
        $trimmed = trim($property);

        return match (strtolower($trimmed)) {
            'transform' => [
                'properties' => ['-webkit-transform', 'transform'],
                'needsPrefixedTransition' => true,
            ],
            'mask' => [
                'properties' => ['-webkit-mask', 'mask'],
                'needsPrefixedTransition' => false,
            ],
            'mask-border' => [
                'properties' => ['-webkit-mask-box-image', 'mask-border'],
                'needsPrefixedTransition' => false,
            ],
            'mask-composite' => [
                'properties' => ['-webkit-mask-composite', 'mask-composite'],
                'needsPrefixedTransition' => false,
            ],
            'mask-mode' => [
                'properties' => ['-webkit-mask-source-type', 'mask-mode'],
                'needsPrefixedTransition' => false,
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
}
