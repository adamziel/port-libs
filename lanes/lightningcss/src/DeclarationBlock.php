<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class DeclarationBlock
{
    private const BOX_SHORTHANDS = [
        'margin' => [
            'top' => 'margin-top',
            'right' => 'margin-right',
            'bottom' => 'margin-bottom',
            'left' => 'margin-left',
        ],
        'padding' => [
            'top' => 'padding-top',
            'right' => 'padding-right',
            'bottom' => 'padding-bottom',
            'left' => 'padding-left',
        ],
        'scroll-margin' => [
            'top' => 'scroll-margin-top',
            'right' => 'scroll-margin-right',
            'bottom' => 'scroll-margin-bottom',
            'left' => 'scroll-margin-left',
        ],
        'scroll-padding' => [
            'top' => 'scroll-padding-top',
            'right' => 'scroll-padding-right',
            'bottom' => 'scroll-padding-bottom',
            'left' => 'scroll-padding-left',
        ],
        'inset' => [
            'top' => 'top',
            'right' => 'right',
            'bottom' => 'bottom',
            'left' => 'left',
        ],
    ];

    private const BACKGROUND_LONGHANDS = [
        'background-color',
        'background-image',
        'background-position',
        'background-position-x',
        'background-position-y',
        'background-size',
        'background-repeat',
        'background-attachment',
        'background-origin',
        'background-clip',
    ];

    private const BORDER_SIDES = ['top', 'right', 'bottom', 'left'];
    private const BORDER_COMPONENTS = ['width', 'style', 'color'];
    private const BORDER_STYLES = ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];
    private const BORDER_WIDTH_KEYWORDS = ['thin', 'medium', 'thick'];
    private const OUTLINE_LONGHANDS = [
        'outline-width',
        'outline-style',
        'outline-color',
    ];
    private const OUTLINE_STYLES = ['auto', 'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];

    private const FLEX_DIRECTIONS = ['row', 'row-reverse', 'column', 'column-reverse'];
    private const FLEX_WRAPS = ['nowrap', 'wrap', 'wrap-reverse'];
    private const FLEX_ITEM_LONGHANDS = [
        'flex-grow',
        'flex-shrink',
        'flex-basis',
    ];
    private const ANIMATION_DIRECTIONS = ['normal', 'reverse', 'alternate', 'alternate-reverse'];
    private const ANIMATION_FILL_MODES = ['none', 'forwards', 'backwards', 'both'];
    private const ANIMATION_PLAY_STATES = ['running', 'paused'];
    private const ANIMATION_TIMING_FUNCTIONS = ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'];
    private const ANIMATION_COMPOSITIONS = ['replace', 'add', 'accumulate'];
    private const ANIMATION_LONGHANDS = [
        'animation-name',
        'animation-duration',
        'animation-timing-function',
        'animation-iteration-count',
        'animation-direction',
        'animation-play-state',
        'animation-delay',
        'animation-fill-mode',
        'animation-timeline',
    ];
    private const TRANSITION_LONGHANDS = [
        'transition-property',
        'transition-duration',
        'transition-delay',
        'transition-timing-function',
    ];
    private const TRANSITION_TIMING_FUNCTIONS = ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'];
    private const GRID_AREA_COMPONENTS = [
        'grid-row-start',
        'grid-column-start',
        'grid-row-end',
        'grid-column-end',
    ];
    private const GRID_TEMPLATE_COMPONENTS = [
        'grid-template-rows',
        'grid-template-columns',
        'grid-template-areas',
    ];
    private const GRID_AUTO_COMPONENTS = [
        'grid-auto-flow',
        'grid-auto-rows',
        'grid-auto-columns',
    ];
    private const PLACE_ALIGNMENT_SHORTHANDS = [
        'place-content' => [
            'align' => 'align-content',
            'justify' => 'justify-content',
        ],
        'place-self' => [
            'align' => 'align-self',
            'justify' => 'justify-self',
        ],
        'place-items' => [
            'align' => 'align-items',
            'justify' => 'justify-items',
        ],
    ];
    private const GAP_LONGHANDS = [
        'row-gap',
        'column-gap',
    ];
    private const OVERFLOW_LONGHANDS = [
        'overflow-x',
        'overflow-y',
    ];
    private const LIST_STYLE_LONGHANDS = [
        'list-style-position',
        'list-style-image',
        'list-style-type',
    ];
    private const TEXT_DECORATION_LONGHANDS = [
        'text-decoration-line',
        'text-decoration-thickness',
        'text-decoration-style',
        'text-decoration-color',
    ];
    private const TEXT_DECORATION_LINES = [
        'underline',
        'overline',
        'line-through',
        'blink',
    ];
    private const TEXT_DECORATION_EXCLUSIVE_LINES = [
        'none',
        'spelling-error',
        'grammar-error',
    ];
    private const TEXT_DECORATION_STYLES = [
        'solid',
        'double',
        'dotted',
        'dashed',
        'wavy',
    ];
    private const TEXT_EMPHASIS_LONGHANDS = [
        'text-emphasis-style',
        'text-emphasis-color',
    ];
    private const TEXT_EMPHASIS_FILLS = ['filled', 'open'];
    private const TEXT_EMPHASIS_SHAPES = ['dot', 'circle', 'double-circle', 'triangle', 'sesame'];
    private const CARET_LONGHANDS = [
        'caret-color',
        'caret-shape',
    ];
    private const CARET_SHAPES = ['auto', 'bar', 'block', 'underscore'];
    private const FONT_LONGHANDS = [
        'font-family',
        'font-size',
        'font-style',
        'font-weight',
        'font-stretch',
        'line-height',
        'font-variant-caps',
    ];
    private const FONT_STYLES = ['normal', 'italic', 'oblique'];
    private const CONTAINER_LONGHANDS = [
        'container-name',
        'container-type',
    ];
    private const CONTAINER_TYPES = [
        'normal',
        'inline-size',
        'size',
        'scroll-state',
    ];
    private const FONT_STRETCH_KEYWORDS = [
        'normal',
        'ultra-condensed',
        'extra-condensed',
        'condensed',
        'semi-condensed',
        'semi-expanded',
        'expanded',
        'extra-expanded',
        'ultra-expanded',
    ];
    private const FONT_SIZE_KEYWORDS = [
        'xx-small',
        'x-small',
        'small',
        'medium',
        'large',
        'x-large',
        'xx-large',
        'xxx-large',
        'smaller',
        'larger',
    ];
    private const BORDER_IMAGE_LONGHANDS = [
        'border-image-source',
        'border-image-slice',
        'border-image-width',
        'border-image-outset',
        'border-image-repeat',
    ];
    private const BORDER_IMAGE_REPEAT_KEYWORDS = ['stretch', 'repeat', 'round', 'space'];
    private const MASK_BORDER_LONGHANDS = [
        'mask-border-source',
        'mask-border-slice',
        'mask-border-width',
        'mask-border-outset',
        'mask-border-repeat',
        'mask-border-mode',
    ];
    private const MASK_BORDER_REPEAT_KEYWORDS = ['stretch', 'repeat', 'round', 'space'];
    private const MASK_BORDER_MODE_KEYWORDS = ['alpha', 'luminance'];
    private const MASK_LONGHANDS = [
        'mask-image',
        'mask-position',
        'mask-size',
        'mask-repeat',
        'mask-origin',
        'mask-clip',
        'mask-composite',
        'mask-mode',
    ];
    private const MASK_GEOMETRY_BOXES = [
        'border-box',
        'padding-box',
        'content-box',
        'margin-box',
        'fill-box',
        'stroke-box',
        'view-box',
    ];
    private const MASK_COMPOSITE_KEYWORDS = ['add', 'subtract', 'intersect', 'exclude'];
    private const MASK_MODE_KEYWORDS = ['alpha', 'luminance', 'match-source'];
    private const BORDER_RADIUS_CORNERS = [
        'top-left' => 'border-top-left-radius',
        'top-right' => 'border-top-right-radius',
        'bottom-right' => 'border-bottom-right-radius',
        'bottom-left' => 'border-bottom-left-radius',
    ];
    private const LOGICAL_BORDER_RADIUS_LONGHANDS = [
        'border-start-start-radius',
        'border-start-end-radius',
        'border-end-end-radius',
        'border-end-start-radius',
    ];

    /**
     * @return array<string, string>
     */
    public function parse(string $block): array
    {
        $declarations = [];
        foreach ($this->parseEntries($block) as $entry) {
            $value = $entry['value'];
            if ($entry['important']) {
                $value .= ' !important';
            }
            $declarations[$entry['property']] = $value;
        }

        return $declarations;
    }

    /**
     * @return list<array{property:string, value:string, important:bool}>
     */
    public function parseEntries(string $block): array
    {
        $entries = [];
        foreach ($this->splitTopLevel($block, ';') as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $colon = $this->findTopLevelColon($part);
            if ($colon === null) {
                throw new \InvalidArgumentException("Invalid CSS declaration: {$part}");
            }
            $property = strtolower(trim(substr($part, 0, $colon)));
            $value = trim(substr($part, $colon + 1));
            if ($property === '' || $value === '') {
                throw new \InvalidArgumentException("Invalid CSS declaration: {$part}");
            }
            [$value, $important] = $this->splitImportantFlag($value);
            if ($value === '') {
                throw new \InvalidArgumentException("Invalid CSS declaration: {$part}");
            }
            $entries[] = [
                'property' => $property,
                'value' => $value,
                'important' => $important,
            ];
        }

        return $entries;
    }

    /**
     * @return array{value:string, important:bool}|null
     */
    public function getProperty(string $block, string $property): ?array
    {
        $property = $this->normalizeProperty($property);
        $entries = $this->cssomOrderedEntries($this->parseEntries($block));
        $boxValue = $this->getBoxProperty($entries, $property);
        if ($boxValue !== null) {
            return $boxValue;
        }
        $logicalBoxValue = $this->getLogicalBoxProperty($entries, $property);
        if ($logicalBoxValue !== null) {
            return $logicalBoxValue;
        }
        if ($this->isLogicalBoxProperty($property)) {
            return null;
        }
        $backgroundValue = $this->getBackgroundProperty($entries, $property);
        if ($backgroundValue !== null) {
            return $backgroundValue;
        }
        if ($property === 'background' || in_array($property, self::BACKGROUND_LONGHANDS, true)) {
            return null;
        }
        $borderValue = $this->getBorderProperty($entries, $property);
        if ($borderValue !== null) {
            return $borderValue;
        }
        if ($this->isBorderProperty($property)) {
            return null;
        }
        $outlineValue = $this->getOutlineProperty($entries, $property);
        if ($outlineValue !== null) {
            return $outlineValue;
        }
        if ($this->isOutlineProperty($property)) {
            return null;
        }
        $borderImageValue = $this->getBorderImageProperty($entries, $property);
        if ($borderImageValue !== null) {
            return $borderImageValue;
        }
        if ($this->isBorderImageProperty($property)) {
            return null;
        }
        $flexValue = $this->getFlexProperty($entries, $property);
        if ($flexValue !== null) {
            return $flexValue;
        }
        $animationValue = $this->getAnimationProperty($entries, $property);
        if ($animationValue !== null) {
            return $animationValue;
        }
        if ($this->isAnimationProperty($property)) {
            return null;
        }
        $transitionValue = $this->getTransitionProperty($entries, $property);
        if ($transitionValue !== null) {
            return $transitionValue;
        }
        if ($this->isTransitionProperty($property)) {
            return null;
        }
        $maskValue = $this->getMaskProperty($entries, $property);
        if ($maskValue !== null) {
            return $maskValue;
        }
        if ($this->isMaskProperty($property)) {
            return null;
        }
        $maskBorderValue = $this->getMaskBorderProperty($entries, $property);
        if ($maskBorderValue !== null) {
            return $maskBorderValue;
        }
        $borderRadiusValue = $this->getBorderRadiusProperty($entries, $property);
        if ($borderRadiusValue !== null) {
            return $borderRadiusValue;
        }
        if ($this->isBorderRadiusProperty($property)) {
            return null;
        }
        $gridValue = $this->getGridProperty($entries, $property);
        if ($gridValue !== null) {
            return $gridValue;
        }
        $placeAlignmentValue = $this->getPlaceAlignmentProperty($entries, $property);
        if ($placeAlignmentValue !== null) {
            return $placeAlignmentValue;
        }
        if ($this->isPlaceAlignmentProperty($property)) {
            return null;
        }
        $gapValue = $this->getGapProperty($entries, $property);
        if ($gapValue !== null) {
            return $gapValue;
        }
        if ($this->isGapProperty($property)) {
            return null;
        }
        $overflowValue = $this->getOverflowProperty($entries, $property);
        if ($overflowValue !== null) {
            return $overflowValue;
        }
        if ($this->isOverflowProperty($property)) {
            return null;
        }
        $listStyleValue = $this->getListStyleProperty($entries, $property);
        if ($listStyleValue !== null) {
            return $listStyleValue;
        }
        if ($this->isListStyleProperty($property)) {
            return null;
        }
        $textDecorationValue = $this->getTextDecorationProperty($entries, $property);
        if ($textDecorationValue !== null) {
            return $textDecorationValue;
        }
        if ($this->isTextDecorationProperty($property)) {
            return null;
        }
        $textEmphasisValue = $this->getTextEmphasisProperty($entries, $property);
        if ($textEmphasisValue !== null) {
            return $textEmphasisValue;
        }
        if ($this->isTextEmphasisProperty($property)) {
            return null;
        }
        $caretValue = $this->getCaretProperty($entries, $property);
        if ($caretValue !== null) {
            return $caretValue;
        }
        if ($this->isCaretProperty($property)) {
            return null;
        }
        $fontValue = $this->getFontProperty($entries, $property);
        if ($fontValue !== null) {
            return $fontValue;
        }
        if ($this->isFontProperty($property)) {
            return null;
        }
        $containerValue = $this->getContainerProperty($entries, $property);
        if ($containerValue !== null) {
            return $containerValue;
        }
        if ($this->isContainerProperty($property)) {
            return null;
        }

        $match = null;
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                $match = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $match;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getFlexProperty(array $entries, string $property): ?array
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);
        if ($prefix === null || $base === null) {
            return null;
        }

        if (in_array($base, ['flex-flow', 'flex-direction', 'flex-wrap'], true)) {
            $components = $this->resolveFlexFlowComponents($entries, $prefix);
            if ($base === 'flex-flow') {
                $direction = $components['direction'];
                $wrap = $components['wrap'];
                if ($direction === null && $wrap === null) {
                    return null;
                }
                if (!$components['flow'] && ($direction === null || $wrap === null)) {
                    return null;
                }

                $important = ($direction ?? $wrap)['important'];
                if ($direction !== null && $direction['important'] !== $important) {
                    return null;
                }
                if ($wrap !== null && $wrap['important'] !== $important) {
                    return null;
                }

                return [
                    'value' => $this->composeFlexFlow($direction['value'] ?? null, $wrap['value'] ?? null),
                    'important' => $important,
                ];
            }

            if ($base === 'flex-direction') {
                return $components['direction'];
            }

            return $components['wrap'];
        }

        if (in_array($base, ['flex', 'flex-grow', 'flex-shrink', 'flex-basis'], true)) {
            $components = $this->resolveFlexItemComponents($entries, $prefix);
            if ($base !== 'flex') {
                return $components[$base];
            }

            $grow = $components['flex-grow'];
            $shrink = $components['flex-shrink'];
            $basis = $components['flex-basis'];
            if ($grow === null || $shrink === null || $basis === null) {
                return null;
            }

            $important = $grow['important'];
            if ($shrink['important'] !== $important || $basis['important'] !== $important) {
                return null;
            }

            return [
                'value' => $this->composeFlexShorthandValue([
                    'flex-grow' => $grow['value'],
                    'flex-shrink' => $shrink['value'],
                    'flex-basis' => $basis['value'],
                ]),
                'important' => $important,
            ];
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getAnimationProperty(array $entries, string $property): ?array
    {
        if (!$this->isAnimationProperty($property)) {
            return null;
        }

        $components = [];
        $sawAnimationProperty = false;
        foreach ($entries as $entry) {
            if ($entry['property'] === 'animation') {
                $components = $this->animationComponentsFromShorthand($entry['value'], $entry['important']);
                $sawAnimationProperty = true;
                continue;
            }

            if (!$this->isAnimationLonghand($entry['property'])) {
                continue;
            }

            $components[$entry['property']] = [
                'value' => $this->normalizeAnimationLonghandList($entry['property'], $entry['value']),
                'important' => $entry['important'],
            ];
            $sawAnimationProperty = true;
        }

        if (!$sawAnimationProperty) {
            return null;
        }

        if ($property !== 'animation') {
            return $components[$property] ?? null;
        }

        return $this->composeAnimationShorthandProperty($components);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getBorderImageProperty(array $entries, string $property): ?array
    {
        if (!$this->isBorderImageProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'border-image') {
                $parsed = $this->parseBorderImageComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::BORDER_IMAGE_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isBorderImageLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeBorderImageLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'border-image') {
            return $components[$property] ?? null;
        }

        foreach (self::BORDER_IMAGE_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->composeBorderImageShorthandValue([
                'border-image-source' => $components['border-image-source']['value'],
                'border-image-slice' => $components['border-image-slice']['value'],
                'border-image-width' => $components['border-image-width']['value'],
                'border-image-outset' => $components['border-image-outset']['value'],
                'border-image-repeat' => $components['border-image-repeat']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setMaskLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isMaskLonghand($property)) {
            return null;
        }

        $value = $this->normalizeMaskLonghandValue($property, $value);
        $valueParts = array_map(
            static fn (string $part): string => trim($part),
            $this->splitTopLevel($value, ',')
        );
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'mask') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $layers = $this->parseMaskLayers($entries[$index]['value']);
            if (count($valueParts) !== count($layers)) {
                return null;
            }

            foreach ($layers as $layerIndex => $_layer) {
                $layers[$layerIndex][$property] = $this->normalizeMaskLonghandValue($property, $valueParts[$layerIndex]);
            }

            $entries[$index] = [
                'property' => 'mask',
                'value' => $this->composeMaskLayers($layers),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setBorderImageLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isBorderImageLonghand($property)) {
            return null;
        }

        $value = $this->normalizeBorderImageLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'border-image') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $components = $this->parseBorderImageComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'border-image',
                'value' => $this->composeBorderImageShorthandValue($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @return array{
     *     border-image-source:string,
     *     border-image-slice:string,
     *     border-image-width:string,
     *     border-image-outset:string,
     *     border-image-repeat:string
     * }|null
     */
    private function parseBorderImageComponents(string $value): ?array
    {
        $groups = array_map('trim', $this->splitTopLevel($value, '/'));
        if (count($groups) > 3) {
            return null;
        }

        $components = [
            'border-image-source' => 'none',
            'border-image-slice' => '100%',
            'border-image-width' => '1',
            'border-image-outset' => '0',
            'border-image-repeat' => 'stretch',
        ];
        $sourceSet = false;
        $sliceTokens = [];
        $repeatTokens = [];

        foreach ($this->splitWhitespaceTopLevel($groups[0] ?? '') as $token) {
            $lower = strtolower(trim($token));
            if (in_array($lower, self::BORDER_IMAGE_REPEAT_KEYWORDS, true)) {
                $repeatTokens[] = $lower;
                continue;
            }

            if (!$sourceSet && $this->isBorderImageSourceToken($token)) {
                $components['border-image-source'] = $this->normalizeBorderImageSourceValue($token);
                $sourceSet = true;
                continue;
            }

            $sliceTokens[] = $token;
        }

        if (count($repeatTokens) > 2) {
            return null;
        }
        if ($repeatTokens !== []) {
            $components['border-image-repeat'] = $this->normalizeBorderImageRepeatValue(implode(' ', $repeatTokens));
        }
        if ($sliceTokens !== []) {
            $components['border-image-slice'] = $this->normalizeBorderImageSliceValue(implode(' ', $sliceTokens));
        }
        if (isset($groups[1]) && $groups[1] !== '') {
            $parsedWidth = $this->parseBorderImageSlashComponent($groups[1]);
            if ($parsedWidth['rect'] !== null) {
                $components['border-image-width'] = $parsedWidth['rect'];
            }
            array_push($repeatTokens, ...$parsedWidth['repeatTokens']);
        }
        if (isset($groups[2]) && $groups[2] !== '') {
            $parsedOutset = $this->parseBorderImageSlashComponent($groups[2]);
            if ($parsedOutset['rect'] !== null) {
                $components['border-image-outset'] = $parsedOutset['rect'];
            }
            array_push($repeatTokens, ...$parsedOutset['repeatTokens']);
        }
        if (count($repeatTokens) > 2) {
            return null;
        }
        if ($repeatTokens !== []) {
            $components['border-image-repeat'] = $this->normalizeBorderImageRepeatValue(implode(' ', $repeatTokens));
        }

        return $components;
    }

    /**
     * @return array{rect:?string, repeatTokens:list<string>}
     */
    private function parseBorderImageSlashComponent(string $value): array
    {
        $rectTokens = [];
        $repeatTokens = [];
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower(trim($token));
            if (in_array($lower, self::BORDER_IMAGE_REPEAT_KEYWORDS, true)) {
                $repeatTokens[] = $lower;
                continue;
            }

            $rectTokens[] = $token;
        }

        return [
            'rect' => $rectTokens === [] ? null : $this->normalizeBorderImageRectValue(implode(' ', $rectTokens)),
            'repeatTokens' => $repeatTokens,
        ];
    }

    private function isBorderImageSourceToken(string $token): bool
    {
        return strtolower($token) === 'none' || $this->isBackgroundImageToken($token);
    }

    private function normalizeBorderImageLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'border-image-source' => $this->normalizeBorderImageSourceValue($value),
            'border-image-slice' => $this->normalizeBorderImageSliceValue($value),
            'border-image-width', 'border-image-outset' => $this->normalizeBorderImageRectValue($value),
            'border-image-repeat' => $this->normalizeBorderImageRepeatValue($value),
            default => trim($value),
        };
    }

    private function normalizeBorderImageSourceValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^url\(/i', $value) === 1) {
            return $this->normalizeCssUrlToken($value);
        }

        return strtolower($value) === 'none' ? 'none' : $value;
    }

    private function normalizeBorderImageSliceValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        $fill = false;
        $offsets = [];
        foreach ($tokens as $token) {
            if (strcasecmp($token, 'fill') === 0) {
                $fill = true;
                continue;
            }

            $offsets[] = $token;
        }

        $slice = $offsets === [] ? '100%' : $this->normalizeBorderImageRectValue(implode(' ', $offsets));

        return $fill ? $slice . ' fill' : $slice;
    }

    private function normalizeBorderImageRectValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) < 1 || count($tokens) > 4) {
            return trim($value);
        }

        return $this->compressBoxShorthand(match (count($tokens)) {
            1 => [
                'top' => $tokens[0],
                'right' => $tokens[0],
                'bottom' => $tokens[0],
                'left' => $tokens[0],
            ],
            2 => [
                'top' => $tokens[0],
                'right' => $tokens[1],
                'bottom' => $tokens[0],
                'left' => $tokens[1],
            ],
            3 => [
                'top' => $tokens[0],
                'right' => $tokens[1],
                'bottom' => $tokens[2],
                'left' => $tokens[1],
            ],
            default => [
                'top' => $tokens[0],
                'right' => $tokens[1],
                'bottom' => $tokens[2],
                'left' => $tokens[3],
            ],
        });
    }

    private function normalizeBorderImageRepeatValue(string $value): string
    {
        $tokens = array_map(
            static fn (string $token): string => strtolower(trim($token)),
            $this->splitWhitespaceTopLevel($value)
        );
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return 'stretch';
        }
        if (count($tokens) === 1 || $tokens[0] === $tokens[1]) {
            return $tokens[0];
        }

        return $tokens[0] . ' ' . $tokens[1];
    }

    /**
     * @param array{
     *     border-image-source:string,
     *     border-image-slice:string,
     *     border-image-width:string,
     *     border-image-outset:string,
     *     border-image-repeat:string
     * } $components
     */
    private function composeBorderImageShorthandValue(array $components): string
    {
        $source = $this->normalizeBorderImageSourceValue($components['border-image-source']);
        $slice = $this->normalizeBorderImageSliceValue($components['border-image-slice']);
        $width = $this->normalizeBorderImageRectValue($components['border-image-width']);
        $outset = $this->normalizeBorderImageRectValue($components['border-image-outset']);
        $repeat = $this->normalizeBorderImageRepeatValue($components['border-image-repeat']);
        $parts = [];

        if (!$this->isDefaultBorderImageSource($source)) {
            $parts[] = $source;
        }
        if (!$this->isDefaultBorderImageSlice($slice) || !$this->isDefaultBorderImageWidth($width) || !$this->isDefaultBorderImageOutset($outset)) {
            $slicePart = $slice;
            if (!$this->isDefaultBorderImageWidth($width) || !$this->isDefaultBorderImageOutset($outset)) {
                $slicePart .= ' / ';
                if (!$this->isDefaultBorderImageWidth($width)) {
                    $slicePart .= $width;
                }
                if (!$this->isDefaultBorderImageOutset($outset)) {
                    $slicePart .= ' / ' . $outset;
                }
            }
            $parts[] = trim($slicePart);
        }
        if (!$this->isDefaultBorderImageRepeat($repeat)) {
            $parts[] = $repeat;
        }

        return $parts === [] ? 'none' : implode(' ', $parts);
    }

    private function isBorderImageProperty(string $property): bool
    {
        return $property === 'border-image' || $this->isBorderImageLonghand($property);
    }

    private function isBorderImageLonghand(string $property): bool
    {
        return in_array($property, self::BORDER_IMAGE_LONGHANDS, true);
    }

    private function isDefaultBorderImageSource(string $value): bool
    {
        return strcasecmp(trim($value), 'none') === 0;
    }

    private function isDefaultBorderImageSlice(string $value): bool
    {
        return strcasecmp(trim($value), '100%') === 0;
    }

    private function isDefaultBorderImageWidth(string $value): bool
    {
        return trim($value) === '1';
    }

    private function isDefaultBorderImageOutset(string $value): bool
    {
        return trim($value) === '0';
    }

    private function isDefaultBorderImageRepeat(string $value): bool
    {
        return strcasecmp(trim($value), 'stretch') === 0;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getMaskProperty(array $entries, string $property): ?array
    {
        if (!$this->isMaskProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'mask') {
                foreach ($this->maskComponentsFromShorthand($entry['value'], $entry['important']) as $longhand => $component) {
                    $components[$longhand] = $component;
                }
                continue;
            }

            if ($this->isMaskLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeMaskLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'mask') {
            return $components[$property] ?? null;
        }

        foreach (self::MASK_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        $value = $this->composeMaskShorthandValue($components);
        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
            'important' => $important,
        ];
    }

    /**
     * @return array<string, array{value:string, important:bool}>
     */
    private function maskComponentsFromShorthand(string $value, bool $important): array
    {
        $layers = $this->parseMaskLayers($value);
        $components = [
            'mask' => ['value' => $this->composeMaskLayers($layers), 'important' => $important],
        ];

        foreach (self::MASK_LONGHANDS as $longhand) {
            $components[$longhand] = [
                'value' => implode(', ', array_map(
                    static fn (array $layer): string => $layer[$longhand],
                    $layers
                )),
                'important' => $important,
            ];
        }

        return $components;
    }

    /**
     * @return list<array{
     *     mask-image:string,
     *     mask-position:string,
     *     mask-size:string,
     *     mask-repeat:string,
     *     mask-origin:string,
     *     mask-clip:string,
     *     mask-composite:string,
     *     mask-mode:string
     * }>
     */
    private function parseMaskLayers(string $value): array
    {
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $layers[] = $this->parseMaskLayer($layer);
        }

        return $layers === [] ? [$this->defaultMaskLayer()] : $layers;
    }

    /**
     * @return array{
     *     mask-image:string,
     *     mask-position:string,
     *     mask-size:string,
     *     mask-repeat:string,
     *     mask-origin:string,
     *     mask-clip:string,
     *     mask-composite:string,
     *     mask-mode:string
     * }
     */
    private function parseMaskLayer(string $layer): array
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        $parsed = $this->defaultMaskLayer();
        $positionTokens = [];
        $origin = null;
        $clip = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $lower = strtolower(trim($token));

            $slash = $this->findTopLevelCharacter($token, '/');
            if ($token === '/' || $slash !== null) {
                $after = '';
                if ($token !== '/') {
                    $before = substr($token, 0, $slash);
                    $after = substr($token, $slash + 1);
                    if ($before !== '') {
                        $positionTokens[] = $before;
                    }
                }

                $sizeTokens = [];
                if ($after !== '') {
                    $sizeTokens[] = $after;
                }
                while (($tokens[$i + 1] ?? null) !== null) {
                    $next = $tokens[$i + 1];
                    if ($this->isMaskLayerComponentBoundary($next)) {
                        break;
                    }
                    $sizeTokens[] = $next;
                    $i++;
                }
                if ($sizeTokens !== []) {
                    $parsed['mask-size'] = $this->normalizeMaskLonghandValue('mask-size', implode(' ', $sizeTokens));
                }
                continue;
            }

            if ($this->isMaskImageToken($token) && $parsed['mask-image'] === 'none') {
                $parsed['mask-image'] = $this->normalizeMaskLonghandValue('mask-image', $token);
                continue;
            }

            if ($this->isBackgroundRepeatToken($lower)) {
                $parsed['mask-repeat'] = $this->normalizeMaskLonghandValue(
                    'mask-repeat',
                    $this->consumeBackgroundRepeat($tokens, $i)
                );
                continue;
            }

            if ($this->isMaskGeometryBox($lower)) {
                if ($origin === null) {
                    $origin = $lower;
                } elseif ($clip === null) {
                    $clip = $lower;
                } else {
                    $positionTokens[] = $token;
                }
                continue;
            }

            if ($this->isMaskClipValue($lower)) {
                $clip = $lower;
                continue;
            }

            if (in_array($lower, self::MASK_COMPOSITE_KEYWORDS, true)) {
                $parsed['mask-composite'] = $lower;
                continue;
            }

            if (in_array($lower, self::MASK_MODE_KEYWORDS, true)) {
                $parsed['mask-mode'] = $lower;
                continue;
            }

            $positionTokens[] = $token;
        }

        if ($positionTokens !== []) {
            $parsed['mask-position'] = $this->normalizeMaskLonghandValue('mask-position', implode(' ', $positionTokens));
        }
        if ($origin !== null) {
            $parsed['mask-origin'] = $origin;
        }
        if ($clip === null && $origin !== null) {
            $clip = $origin;
        }
        if ($clip !== null) {
            $parsed['mask-clip'] = $clip;
        }

        return $parsed;
    }

    /**
     * @return array{
     *     mask-image:string,
     *     mask-position:string,
     *     mask-size:string,
     *     mask-repeat:string,
     *     mask-origin:string,
     *     mask-clip:string,
     *     mask-composite:string,
     *     mask-mode:string
     * }
     */
    private function defaultMaskLayer(): array
    {
        return [
            'mask-image' => 'none',
            'mask-position' => '0 0',
            'mask-size' => 'auto',
            'mask-repeat' => 'repeat',
            'mask-origin' => 'border-box',
            'mask-clip' => 'border-box',
            'mask-composite' => 'add',
            'mask-mode' => 'match-source',
        ];
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     */
    private function composeMaskShorthandValue(array $components): ?string
    {
        $lists = [];
        $layerCount = null;
        foreach (self::MASK_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }

            $values = array_map(
                static fn (string $part): string => trim($part),
                $this->splitTopLevel($components[$longhand]['value'], ',')
            );
            if ($values === []) {
                return null;
            }
            if ($layerCount === null) {
                $layerCount = count($values);
            } elseif (count($values) !== $layerCount) {
                return null;
            }
            $lists[$longhand] = $values;
        }

        $layers = [];
        for ($i = 0; $i < $layerCount; $i++) {
            $layer = [];
            foreach (self::MASK_LONGHANDS as $longhand) {
                $layer[$longhand] = $this->normalizeMaskLonghandValue($longhand, $lists[$longhand][$i]);
            }
            $layers[] = $layer;
        }

        return $this->composeMaskLayers($layers);
    }

    /**
     * @param list<array{
     *     mask-image:string,
     *     mask-position:string,
     *     mask-size:string,
     *     mask-repeat:string,
     *     mask-origin:string,
     *     mask-clip:string,
     *     mask-composite:string,
     *     mask-mode:string
     * }> $layers
     */
    private function composeMaskLayers(array $layers): string
    {
        return implode(', ', array_map(
            fn (array $layer): string => $this->composeMaskLayer($layer),
            $layers
        ));
    }

    /**
     * @param array{
     *     mask-image:string,
     *     mask-position:string,
     *     mask-size:string,
     *     mask-repeat:string,
     *     mask-origin:string,
     *     mask-clip:string,
     *     mask-composite:string,
     *     mask-mode:string
     * } $layer
     */
    private function composeMaskLayer(array $layer): string
    {
        $parts = [$this->normalizeMaskLonghandValue('mask-image', $layer['mask-image'])];
        $position = $this->normalizeMaskLonghandValue('mask-position', $layer['mask-position']);
        $size = $this->normalizeMaskLonghandValue('mask-size', $layer['mask-size']);
        $repeat = $this->normalizeMaskLonghandValue('mask-repeat', $layer['mask-repeat']);
        $origin = $this->normalizeMaskLonghandValue('mask-origin', $layer['mask-origin']);
        $clip = $this->normalizeMaskLonghandValue('mask-clip', $layer['mask-clip']);
        $composite = $this->normalizeMaskLonghandValue('mask-composite', $layer['mask-composite']);
        $mode = $this->normalizeMaskLonghandValue('mask-mode', $layer['mask-mode']);

        if (!$this->isDefaultMaskPosition($position) || !$this->isDefaultMaskSize($size)) {
            $parts[] = $position;
            if (!$this->isDefaultMaskSize($size)) {
                $parts[] = '/';
                $parts[] = $size;
            }
        }
        if (!$this->isDefaultMaskRepeat($repeat)) {
            $parts[] = $repeat;
        }
        if (!$this->isDefaultMaskOrigin($origin) || !$this->isDefaultMaskClip($clip)) {
            $parts[] = $origin;
            if ($clip !== $origin) {
                $parts[] = $clip;
            }
        }
        if (!$this->isDefaultMaskComposite($composite)) {
            $parts[] = $composite;
        }
        if (!$this->isDefaultMaskMode($mode)) {
            $parts[] = $mode;
        }

        return implode(' ', $parts);
    }

    private function normalizeMaskLonghandValue(string $property, string $value): string
    {
        $value = trim($value);

        return match ($property) {
            'mask-image' => $this->normalizeMaskImageValue($value),
            'mask-repeat' => $this->compressBackgroundRepeat(strtolower($value)),
            'mask-origin', 'mask-clip', 'mask-composite', 'mask-mode' => strtolower($value),
            default => $value,
        };
    }

    private function normalizeMaskImageValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^url\(/i', $value) === 1) {
            return $this->normalizeCssUrlToken($value);
        }

        return strtolower($value) === 'none' ? 'none' : $value;
    }

    private function isMaskLayerComponentBoundary(string $token): bool
    {
        $lower = strtolower(trim($token));

        return $this->isBackgroundRepeatToken($lower)
            || $this->isMaskGeometryBox($lower)
            || $this->isMaskClipValue($lower)
            || in_array($lower, self::MASK_COMPOSITE_KEYWORDS, true)
            || in_array($lower, self::MASK_MODE_KEYWORDS, true);
    }

    private function isMaskImageToken(string $token): bool
    {
        return strtolower(trim($token)) === 'none' || $this->isBackgroundImageToken($token);
    }

    private function isMaskGeometryBox(string $value): bool
    {
        return in_array($value, self::MASK_GEOMETRY_BOXES, true);
    }

    private function isMaskClipValue(string $value): bool
    {
        return $value === 'no-clip';
    }

    private function isDefaultMaskPosition(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['0', '0 0', '0% 0%', 'left top'], true);
    }

    private function isDefaultMaskSize(string $value): bool
    {
        return $this->isDefaultBackgroundSize($value);
    }

    private function isDefaultMaskRepeat(string $value): bool
    {
        return strtolower(trim($value)) === 'repeat';
    }

    private function isDefaultMaskOrigin(string $value): bool
    {
        return strtolower(trim($value)) === 'border-box';
    }

    private function isDefaultMaskClip(string $value): bool
    {
        return strtolower(trim($value)) === 'border-box';
    }

    private function isDefaultMaskComposite(string $value): bool
    {
        return strtolower(trim($value)) === 'add';
    }

    private function isDefaultMaskMode(string $value): bool
    {
        return strtolower(trim($value)) === 'match-source';
    }

    private function isMaskProperty(string $property): bool
    {
        return $property === 'mask' || $this->isMaskLonghand($property);
    }

    private function isMaskLonghand(string $property): bool
    {
        return in_array($property, self::MASK_LONGHANDS, true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getMaskBorderProperty(array $entries, string $property): ?array
    {
        if (!$this->isMaskBorderProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'mask-border') {
                $parsed = $this->parseMaskBorderComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::MASK_BORDER_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isMaskBorderLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeMaskBorderLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'mask-border') {
            return $components[$property] ?? null;
        }

        foreach (self::MASK_BORDER_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->composeMaskBorderShorthandValue([
                'mask-border-source' => $components['mask-border-source']['value'],
                'mask-border-slice' => $components['mask-border-slice']['value'],
                'mask-border-width' => $components['mask-border-width']['value'],
                'mask-border-outset' => $components['mask-border-outset']['value'],
                'mask-border-repeat' => $components['mask-border-repeat']['value'],
                'mask-border-mode' => $components['mask-border-mode']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setMaskBorderLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isMaskBorderLonghand($property)) {
            return null;
        }

        $value = $this->normalizeMaskBorderLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'mask-border') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $components = $this->parseMaskBorderComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'mask-border',
                'value' => $this->composeMaskBorderShorthandValue($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @return array{
     *     mask-border-source:string,
     *     mask-border-slice:string,
     *     mask-border-width:string,
     *     mask-border-outset:string,
     *     mask-border-repeat:string,
     *     mask-border-mode:string
     * }|null
     */
    private function parseMaskBorderComponents(string $value): ?array
    {
        $groups = array_map('trim', $this->splitTopLevel($value, '/'));
        if (count($groups) > 3) {
            return null;
        }

        $components = [
            'mask-border-source' => 'none',
            'mask-border-slice' => '100%',
            'mask-border-width' => '1',
            'mask-border-outset' => '0',
            'mask-border-repeat' => 'stretch',
            'mask-border-mode' => 'alpha',
        ];
        $sourceSet = false;
        $sliceTokens = [];
        $repeatTokens = [];

        foreach ($this->splitWhitespaceTopLevel($groups[0] ?? '') as $token) {
            $lower = strtolower(trim($token));
            if (in_array($lower, self::MASK_BORDER_MODE_KEYWORDS, true)) {
                $components['mask-border-mode'] = $lower;
                continue;
            }

            if (in_array($lower, self::MASK_BORDER_REPEAT_KEYWORDS, true)) {
                $repeatTokens[] = $lower;
                continue;
            }

            if (!$sourceSet && $this->isMaskBorderSourceToken($token)) {
                $components['mask-border-source'] = $this->normalizeMaskBorderSourceValue($token);
                $sourceSet = true;
                continue;
            }

            $sliceTokens[] = $token;
        }

        if (count($repeatTokens) > 2) {
            return null;
        }
        if ($repeatTokens !== []) {
            $components['mask-border-repeat'] = $this->normalizeMaskBorderRepeatValue(implode(' ', $repeatTokens));
        }
        if ($sliceTokens !== []) {
            $components['mask-border-slice'] = $this->normalizeMaskBorderSliceValue(implode(' ', $sliceTokens));
        }
        if (isset($groups[1]) && $groups[1] !== '') {
            $parsedWidth = $this->parseMaskBorderSlashComponent($groups[1]);
            if ($parsedWidth['rect'] !== null) {
                $components['mask-border-width'] = $parsedWidth['rect'];
            }
            array_push($repeatTokens, ...$parsedWidth['repeatTokens']);
            if ($parsedWidth['mode'] !== null) {
                $components['mask-border-mode'] = $parsedWidth['mode'];
            }
        }
        if (isset($groups[2]) && $groups[2] !== '') {
            $parsedOutset = $this->parseMaskBorderSlashComponent($groups[2]);
            if ($parsedOutset['rect'] !== null) {
                $components['mask-border-outset'] = $parsedOutset['rect'];
            }
            array_push($repeatTokens, ...$parsedOutset['repeatTokens']);
            if ($parsedOutset['mode'] !== null) {
                $components['mask-border-mode'] = $parsedOutset['mode'];
            }
        }
        if (count($repeatTokens) > 2) {
            return null;
        }
        if ($repeatTokens !== []) {
            $components['mask-border-repeat'] = $this->normalizeMaskBorderRepeatValue(implode(' ', $repeatTokens));
        }

        return $components;
    }

    /**
     * @return array{rect:?string, repeatTokens:list<string>, mode:?string}
     */
    private function parseMaskBorderSlashComponent(string $value): array
    {
        $rectTokens = [];
        $repeatTokens = [];
        $mode = null;
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower(trim($token));
            if (in_array($lower, self::MASK_BORDER_MODE_KEYWORDS, true)) {
                $mode = $lower;
                continue;
            }
            if (in_array($lower, self::MASK_BORDER_REPEAT_KEYWORDS, true)) {
                $repeatTokens[] = $lower;
                continue;
            }

            $rectTokens[] = $token;
        }

        return [
            'rect' => $rectTokens === [] ? null : $this->normalizeMaskBorderRectValue(implode(' ', $rectTokens)),
            'repeatTokens' => $repeatTokens,
            'mode' => $mode,
        ];
    }

    private function isMaskBorderSourceToken(string $token): bool
    {
        return strtolower($token) === 'none' || $this->isBackgroundImageToken($token);
    }

    private function normalizeMaskBorderLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'mask-border-source' => $this->normalizeMaskBorderSourceValue($value),
            'mask-border-slice' => $this->normalizeMaskBorderSliceValue($value),
            'mask-border-width', 'mask-border-outset' => $this->normalizeMaskBorderRectValue($value),
            'mask-border-repeat' => $this->normalizeMaskBorderRepeatValue($value),
            'mask-border-mode' => strtolower(trim($value)),
            default => trim($value),
        };
    }

    private function normalizeMaskBorderSourceValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^url\(/i', $value) === 1) {
            return $this->normalizeCssUrlToken($value);
        }

        return strtolower($value) === 'none' ? 'none' : $value;
    }

    private function normalizeMaskBorderSliceValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        $fill = false;
        $offsets = [];
        foreach ($tokens as $token) {
            if (strcasecmp($token, 'fill') === 0) {
                $fill = true;
                continue;
            }

            $offsets[] = $token;
        }

        $slice = $offsets === [] ? '100%' : $this->normalizeMaskBorderRectValue(implode(' ', $offsets));

        return $fill ? $slice . ' fill' : $slice;
    }

    private function normalizeMaskBorderRectValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) < 1 || count($tokens) > 4) {
            return trim($value);
        }

        return $this->compressBoxShorthand(match (count($tokens)) {
            1 => [
                'top' => $tokens[0],
                'right' => $tokens[0],
                'bottom' => $tokens[0],
                'left' => $tokens[0],
            ],
            2 => [
                'top' => $tokens[0],
                'right' => $tokens[1],
                'bottom' => $tokens[0],
                'left' => $tokens[1],
            ],
            3 => [
                'top' => $tokens[0],
                'right' => $tokens[1],
                'bottom' => $tokens[2],
                'left' => $tokens[1],
            ],
            default => [
                'top' => $tokens[0],
                'right' => $tokens[1],
                'bottom' => $tokens[2],
                'left' => $tokens[3],
            ],
        });
    }

    private function normalizeMaskBorderRepeatValue(string $value): string
    {
        $tokens = array_map(
            static fn (string $token): string => strtolower(trim($token)),
            $this->splitWhitespaceTopLevel($value)
        );
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return 'stretch';
        }
        if (count($tokens) === 1 || $tokens[0] === $tokens[1]) {
            return $tokens[0];
        }

        return $tokens[0] . ' ' . $tokens[1];
    }

    /**
     * @param array{
     *     mask-border-source:string,
     *     mask-border-slice:string,
     *     mask-border-width:string,
     *     mask-border-outset:string,
     *     mask-border-repeat:string,
     *     mask-border-mode:string
     * } $components
     */
    private function composeMaskBorderShorthandValue(array $components): string
    {
        $source = $this->normalizeMaskBorderSourceValue($components['mask-border-source']);
        $slice = $this->normalizeMaskBorderSliceValue($components['mask-border-slice']);
        $width = $this->normalizeMaskBorderRectValue($components['mask-border-width']);
        $outset = $this->normalizeMaskBorderRectValue($components['mask-border-outset']);
        $repeat = $this->normalizeMaskBorderRepeatValue($components['mask-border-repeat']);
        $mode = strtolower(trim($components['mask-border-mode']));
        $parts = [];

        if (!$this->isDefaultMaskBorderSource($source)) {
            $parts[] = $source;
        }
        if (!$this->isDefaultMaskBorderSlice($slice) || !$this->isDefaultMaskBorderWidth($width) || !$this->isDefaultMaskBorderOutset($outset)) {
            $slicePart = $slice;
            if (!$this->isDefaultMaskBorderWidth($width) || !$this->isDefaultMaskBorderOutset($outset)) {
                $slicePart .= ' / ';
                if (!$this->isDefaultMaskBorderWidth($width)) {
                    $slicePart .= $width;
                }
                if (!$this->isDefaultMaskBorderOutset($outset)) {
                    $slicePart .= ' / ' . $outset;
                }
            }
            $parts[] = trim($slicePart);
        }
        if (!$this->isDefaultMaskBorderRepeat($repeat)) {
            $parts[] = $repeat;
        }
        if (!$this->isDefaultMaskBorderMode($mode)) {
            $parts[] = $mode;
        }

        return $parts === [] ? 'none' : implode(' ', $parts);
    }

    private function isMaskBorderProperty(string $property): bool
    {
        return $property === 'mask-border' || $this->isMaskBorderLonghand($property);
    }

    private function isMaskBorderLonghand(string $property): bool
    {
        return in_array($property, self::MASK_BORDER_LONGHANDS, true);
    }

    private function isDefaultMaskBorderSource(string $value): bool
    {
        return strcasecmp(trim($value), 'none') === 0;
    }

    private function isDefaultMaskBorderSlice(string $value): bool
    {
        return strcasecmp(trim($value), '100%') === 0;
    }

    private function isDefaultMaskBorderWidth(string $value): bool
    {
        return trim($value) === '1';
    }

    private function isDefaultMaskBorderOutset(string $value): bool
    {
        return trim($value) === '0';
    }

    private function isDefaultMaskBorderRepeat(string $value): bool
    {
        return strcasecmp(trim($value), 'stretch') === 0;
    }

    private function isDefaultMaskBorderMode(string $value): bool
    {
        return strcasecmp(trim($value), 'alpha') === 0;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getBorderRadiusProperty(array $entries, string $property): ?array
    {
        $prefix = $this->borderRadiusPrefixForProperty($property);
        $base = $this->baseBorderRadiusProperty($property);
        if ($prefix === null || $base === null) {
            return null;
        }

        $longhands = $this->borderRadiusLonghandsForPrefix($prefix);
        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $this->borderRadiusProperty($prefix, 'border-radius')) {
                $parsed = $this->parseBorderRadiusComponents($entry['value'], $prefix);
                if ($parsed === null) {
                    continue;
                }

                foreach ($longhands as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isBorderRadiusLonghandForPrefix($entry['property'], $prefix)) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeBorderRadiusCornerValue($entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($base !== 'border-radius') {
            return $components[$property] ?? null;
        }

        foreach ($longhands as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->composeBorderRadiusShorthandValue([
                'top-left' => $components[$this->borderRadiusProperty($prefix, 'border-top-left-radius')]['value'],
                'top-right' => $components[$this->borderRadiusProperty($prefix, 'border-top-right-radius')]['value'],
                'bottom-right' => $components[$this->borderRadiusProperty($prefix, 'border-bottom-right-radius')]['value'],
                'bottom-left' => $components[$this->borderRadiusProperty($prefix, 'border-bottom-left-radius')]['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setBorderRadiusLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        $prefix = $this->borderRadiusPrefixForProperty($property);
        if ($prefix === null || !$this->isBorderRadiusLonghandForPrefix($property, $prefix)) {
            return null;
        }

        $value = $this->normalizeBorderRadiusCornerValue($value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($this->isLogicalBorderRadiusLonghand($entries[$index]['property'])) {
                break;
            }

            if ($entries[$index]['property'] !== $this->borderRadiusProperty($prefix, 'border-radius')) {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $components = $this->parseBorderRadiusComponents($entries[$index]['value'], $prefix);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => $this->borderRadiusProperty($prefix, 'border-radius'),
                'value' => $this->composeBorderRadiusShorthandValue([
                    'top-left' => $components[$this->borderRadiusProperty($prefix, 'border-top-left-radius')],
                    'top-right' => $components[$this->borderRadiusProperty($prefix, 'border-top-right-radius')],
                    'bottom-right' => $components[$this->borderRadiusProperty($prefix, 'border-bottom-right-radius')],
                    'bottom-left' => $components[$this->borderRadiusProperty($prefix, 'border-bottom-left-radius')],
                ]),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function parseBorderRadiusComponents(string $value, string $prefix): ?array
    {
        $parts = array_map('trim', $this->splitTopLevel($value, '/'));
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

        $components = [];
        foreach (array_keys(self::BORDER_RADIUS_CORNERS) as $index => $corner) {
            $components[$this->borderRadiusProperty($prefix, self::BORDER_RADIUS_CORNERS[$corner])] =
                $this->composeBorderRadiusCornerValue($horizontal[$index], $vertical[$index]);
        }

        return $components;
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

        return match (count($tokens)) {
            1 => [$tokens[0], $tokens[0], $tokens[0], $tokens[0]],
            2 => [$tokens[0], $tokens[1], $tokens[0], $tokens[1]],
            3 => [$tokens[0], $tokens[1], $tokens[2], $tokens[1]],
            4 => $tokens,
        };
    }

    /**
     * @param array{top-left:string,top-right:string,bottom-right:string,bottom-left:string} $components
     */
    private function composeBorderRadiusShorthandValue(array $components): string
    {
        $horizontal = [];
        $vertical = [];
        foreach (array_keys(self::BORDER_RADIUS_CORNERS) as $corner) {
            $parsed = $this->parseBorderRadiusCornerValue($components[$corner]);
            if ($parsed === null) {
                return implode(' ', $components);
            }

            $horizontal[$corner] = $parsed[0];
            $vertical[$corner] = $parsed[1];
        }

        $horizontalValue = $this->compressBoxShorthand([
            'top' => $horizontal['top-left'],
            'right' => $horizontal['top-right'],
            'bottom' => $horizontal['bottom-right'],
            'left' => $horizontal['bottom-left'],
        ]);
        $verticalValue = $this->compressBoxShorthand([
            'top' => $vertical['top-left'],
            'right' => $vertical['top-right'],
            'bottom' => $vertical['bottom-right'],
            'left' => $vertical['bottom-left'],
        ]);

        return $horizontalValue === $verticalValue ? $horizontalValue : $horizontalValue . ' / ' . $verticalValue;
    }

    private function normalizeBorderRadiusCornerValue(string $value): string
    {
        $corner = $this->parseBorderRadiusCornerValue($value);
        if ($corner === null) {
            return trim($value);
        }

        return $this->composeBorderRadiusCornerValue($corner[0], $corner[1]);
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

        return [$tokens[0], $tokens[1] ?? $tokens[0]];
    }

    private function composeBorderRadiusCornerValue(string $horizontal, string $vertical): string
    {
        return $horizontal === $vertical ? $horizontal : $horizontal . ' ' . $vertical;
    }

    private function isBorderRadiusProperty(string $property): bool
    {
        return $this->isBorderRadiusShorthand($property)
            || ($this->borderRadiusPrefixForProperty($property) !== null && $this->baseBorderRadiusProperty($property) !== null);
    }

    private function isBorderRadiusShorthand(string $property): bool
    {
        $prefix = $this->borderRadiusPrefixForProperty($property);

        return $prefix !== null && $property === $this->borderRadiusProperty($prefix, 'border-radius');
    }

    private function isBorderRadiusLonghandForPrefix(string $property, string $prefix): bool
    {
        return in_array($property, $this->borderRadiusLonghandsForPrefix($prefix), true);
    }

    private function isBorderRadiusLonghand(string $property): bool
    {
        $prefix = $this->borderRadiusPrefixForProperty($property);

        return $prefix !== null && $this->isBorderRadiusLonghandForPrefix($property, $prefix);
    }

    private function isLogicalBorderRadiusLonghand(string $property): bool
    {
        return in_array($property, self::LOGICAL_BORDER_RADIUS_LONGHANDS, true);
    }

    /**
     * @return list<string>
     */
    private function borderRadiusLonghandsForPrefix(string $prefix): array
    {
        return array_map(
            fn (string $longhand): string => $this->borderRadiusProperty($prefix, $longhand),
            array_values(self::BORDER_RADIUS_CORNERS)
        );
    }

    private function borderRadiusPrefixForProperty(string $property): ?string
    {
        if (str_starts_with($property, '-webkit-border-')) {
            return '-webkit-';
        }
        if (str_starts_with($property, '-moz-border-')) {
            return '-moz-';
        }
        if (str_starts_with($property, 'border-')) {
            return '';
        }

        return null;
    }

    private function baseBorderRadiusProperty(string $property): ?string
    {
        $prefix = $this->borderRadiusPrefixForProperty($property);
        if ($prefix === null) {
            return null;
        }

        $base = $prefix === '' ? $property : substr($property, strlen($prefix));
        if ($base === 'border-radius' || in_array($base, array_values(self::BORDER_RADIUS_CORNERS), true)) {
            return $base;
        }

        return null;
    }

    private function borderRadiusProperty(string $prefix, string $base): string
    {
        return $prefix . $base;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getGridProperty(array $entries, string $property): ?array
    {
        if (!$this->isGridProperty($property)) {
            return null;
        }

        $components = array_fill_keys(self::GRID_AREA_COMPONENTS, null);
        $template = array_fill_keys(array_merge(self::GRID_TEMPLATE_COMPONENTS, self::GRID_AUTO_COMPONENTS), null);
        foreach ($entries as $entry) {
            if (array_key_exists($entry['property'], $template)) {
                $template[$entry['property']] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }

            if ($entry['property'] === 'grid-area') {
                $area = $this->parseGridArea($entry['value']);
                if ($area === null) {
                    continue;
                }
                foreach ($area as $component => $value) {
                    $components[$component] = [
                        'value' => $value,
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($entry['property'] === 'grid-row' || $entry['property'] === 'grid-column') {
                $placement = $this->parseGridLineShorthand($entry['value']);
                if ($placement === null) {
                    continue;
                }
                $axis = $entry['property'] === 'grid-row' ? 'row' : 'column';
                $components["grid-{$axis}-start"] = [
                    'value' => $placement[0],
                    'important' => $entry['important'],
                ];
                $components["grid-{$axis}-end"] = [
                    'value' => $placement[1],
                    'important' => $entry['important'],
                ];
                continue;
            }

            if (in_array($entry['property'], self::GRID_AREA_COMPONENTS, true)) {
                $components[$entry['property']] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property === 'grid-template') {
            return $this->composeGridTemplateShorthand($template);
        }

        if ($property === 'grid') {
            return $this->composeGridShorthand($template);
        }

        if (in_array($property, self::GRID_AREA_COMPONENTS, true)) {
            return $components[$property];
        }

        if ($property === 'grid-row') {
            return $this->composeGridPlacement($components['grid-row-start'], $components['grid-row-end']);
        }

        if ($property === 'grid-column') {
            return $this->composeGridPlacement($components['grid-column-start'], $components['grid-column-end']);
        }

        if ($property === 'grid-area') {
            $value = [];
            $important = null;
            foreach (self::GRID_AREA_COMPONENTS as $component) {
                if ($components[$component] === null) {
                    return null;
                }
                if ($important === null) {
                    $important = $components[$component]['important'];
                } elseif ($components[$component]['important'] !== $important) {
                    return null;
                }
                $value[] = $components[$component]['value'];
            }

            $area = array_combine(self::GRID_AREA_COMPONENTS, $value);
            if ($area === false) {
                return null;
            }

            return ['value' => $this->serializeGridAreaPlacement($area), 'important' => $important];
        }

        return null;
    }

    /**
     * @param array<string, array{value:string, important:bool}|null> $components
     * @return array{value:string, important:bool}|null
     */
    private function composeGridTemplateShorthand(array $components): ?array
    {
        $rows = $components['grid-template-rows'] ?? null;
        $columns = $components['grid-template-columns'] ?? null;
        $areas = $components['grid-template-areas'] ?? null;
        if ($rows === null || $columns === null || $areas === null) {
            return null;
        }

        $important = $this->sameImportant([$rows, $columns, $areas]);
        if ($important === null) {
            return null;
        }

        if ($this->isGridTemplateAreasNone($areas['value'])) {
            return [
                'value' => $this->normalizeGridTrackValue($rows['value']) . ' / ' . $this->normalizeGridTrackValue($columns['value']),
                'important' => $important,
            ];
        }

        $value = $this->serializeGridTemplateWithAreas($rows['value'], $columns['value'], $areas['value']);
        if ($value === null) {
            return null;
        }

        return ['value' => $value, 'important' => $important];
    }

    /**
     * @param array<string, array{value:string, important:bool}|null> $components
     * @return array{value:string, important:bool}|null
     */
    private function composeGridShorthand(array $components): ?array
    {
        foreach (array_merge(self::GRID_TEMPLATE_COMPONENTS, self::GRID_AUTO_COMPONENTS) as $property) {
            if (($components[$property] ?? null) === null) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null || !$this->isInitialGridAuto($components)) {
            return null;
        }

        return $this->composeGridTemplateShorthand($components);
    }

    /**
     * @param list<array{value:string, important:bool}|null> $components
     */
    private function sameImportant(array $components): ?bool
    {
        $important = null;
        foreach ($components as $component) {
            if ($component === null) {
                return null;
            }
            if ($important === null) {
                $important = $component['important'];
                continue;
            }
            if ($component['important'] !== $important) {
                return null;
            }
        }

        return $important;
    }

    /**
     * @param array<string, array{value:string, important:bool}|null> $components
     */
    private function isInitialGridAuto(array $components): bool
    {
        $flow = $this->normalizeGridAutoFlow($components['grid-auto-flow']['value']);
        $rows = strtolower($this->normalizeGridTrackValue($components['grid-auto-rows']['value']));
        $columns = strtolower($this->normalizeGridTrackValue($components['grid-auto-columns']['value']));

        return $flow === 'row' && $rows === 'auto' && $columns === 'auto';
    }

    private function normalizeGridAutoFlow(string $value): string
    {
        $tokens = array_map('strtolower', $this->splitWhitespaceTopLevel($value));
        sort($tokens);

        return implode(' ', $tokens);
    }

    private function serializeGridTemplateWithAreas(string $rowsValue, string $columnsValue, string $areasValue): ?string
    {
        $areas = $this->parseGridTemplateAreaRows($areasValue);
        $rows = $this->parseGridTrackList($rowsValue);
        $columns = $this->parseGridTrackList($columnsValue);
        if ($areas === null || $areas === [] || $rows === null || $columns === null) {
            return null;
        }
        if ($rows['none'] || $rows['hasRepeat'] || $columns['hasRepeat']) {
            return null;
        }

        $areaColumnCount = $areas[0]['columns'];
        while (count($areas) < count($rows['items'])) {
            $areas[] = [
                'text' => implode(' ', array_fill(0, $areaColumnCount, '.')),
                'columns' => $areaColumnCount,
            ];
        }

        $parts = [];
        $rowCount = count($areas);
        for ($i = 0; $i < $rowCount; $i++) {
            if (($rows['lineNames'][$i] ?? []) !== []) {
                array_push($parts, ...$this->serializeGridLineNameBoundary($rows['lineNames'][$i]));
            }

            $parts[] = '"' . str_replace('"', '\\"', $areas[$i]['text']) . '"';

            $track = $rows['items'][$i] ?? null;
            if ($track !== null && !$this->isDefaultGridTrackSize($track)) {
                $parts[] = $track;
            }
        }

        if (($rows['lineNames'][$rowCount] ?? []) !== []) {
            array_push($parts, ...$this->serializeGridLineNameBoundary($rows['lineNames'][$rowCount]));
        }

        return implode(' ', $parts) . ' / ' . $this->serializeGridTrackList($columns);
    }

    /**
     * @return list<array{text:string, columns:int}>|null
     */
    private function parseGridTemplateAreaRows(string $value): ?array
    {
        if ($this->isGridTemplateAreasNone($value)) {
            return [];
        }

        $rows = [];
        $quote = null;
        $current = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote === null) {
                if (ctype_space($char)) {
                    continue;
                }
                if ($char !== '"' && $char !== "'") {
                    return null;
                }
                $quote = $char;
                $current = '';
                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $char . $value[++$i];
                continue;
            }

            if ($char === $quote) {
                $tokens = preg_split('/\s+/', trim($current)) ?: [];
                $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
                if ($tokens === []) {
                    return null;
                }
                $columns = count($tokens);
                if ($rows !== [] && $rows[0]['columns'] !== $columns) {
                    return null;
                }
                $rows[] = [
                    'text' => implode(' ', $tokens),
                    'columns' => $columns,
                ];
                $quote = null;
                continue;
            }

            $current .= $char;
        }

        return $quote === null ? $rows : null;
    }

    /**
     * @return array{none:bool, items:list<string>, lineNames:list<list<string>>, hasRepeat:bool}|null
     */
    private function parseGridTrackList(string $value): ?array
    {
        $value = trim($value);
        if (strcasecmp($value, 'none') === 0) {
            return [
                'none' => true,
                'items' => [],
                'lineNames' => [[]],
                'hasRepeat' => false,
            ];
        }

        $items = [];
        $lineNames = [[]];
        $hasRepeat = false;
        foreach ($this->splitGridTrackTokens($value) as $token) {
            $names = $this->parseGridLineNameToken($token);
            if ($names !== null) {
                $index = count($items);
                if (!isset($lineNames[$index])) {
                    $lineNames[$index] = [];
                }
                array_push($lineNames[$index], ...$names);
                continue;
            }

            $items[] = $token;
            if (str_starts_with(strtolower($token), 'repeat(')) {
                $hasRepeat = true;
            }
            if (!isset($lineNames[count($items)])) {
                $lineNames[count($items)] = [];
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'none' => false,
            'items' => $items,
            'lineNames' => $lineNames,
            'hasRepeat' => $hasRepeat,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitGridTrackTokens(string $value): array
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
                if (trim($token) !== '') {
                    $tokens[] = trim($token);
                    $token = '';
                }
                continue;
            }

            $token .= $char;
        }

        if (trim($token) !== '') {
            $tokens[] = trim($token);
        }

        return $tokens;
    }

    /**
     * @return list<string>|null
     */
    private function parseGridLineNameToken(string $token): ?array
    {
        if (preg_match('/^\[(.*)\]$/s', trim($token), $matches) !== 1) {
            return null;
        }

        $inner = trim($matches[1]);
        if ($inner === '') {
            return [];
        }

        return preg_split('/\s+/', $inner) ?: [];
    }

    /**
     * @param array{none:bool, items:list<string>, lineNames:list<list<string>>, hasRepeat:bool} $trackList
     */
    private function serializeGridTrackList(array $trackList): string
    {
        if ($trackList['none']) {
            return 'none';
        }

        $parts = [];
        foreach ($trackList['items'] as $index => $item) {
            if (($trackList['lineNames'][$index] ?? []) !== []) {
                $parts[] = $this->serializeGridLineNames($trackList['lineNames'][$index]);
            }
            $parts[] = $item;
        }

        $lastNames = $trackList['lineNames'][count($trackList['items'])] ?? [];
        if ($lastNames !== []) {
            $parts[] = $this->serializeGridLineNames($lastNames);
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<string> $names
     */
    private function serializeGridLineNames(array $names): string
    {
        return '[' . implode(' ', $names) . ']';
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function serializeGridLineNameBoundary(array $names): array
    {
        if (count($names) === 2) {
            return [
                $this->serializeGridLineNames([$names[0]]),
                $this->serializeGridLineNames([$names[1]]),
            ];
        }

        return [$this->serializeGridLineNames($names)];
    }

    private function normalizeGridTrackValue(string $value): string
    {
        $trackList = $this->parseGridTrackList($value);
        if ($trackList === null) {
            return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        }

        return $this->serializeGridTrackList($trackList);
    }

    private function isDefaultGridTrackSize(string $value): bool
    {
        return strcasecmp(trim($value), 'auto') === 0;
    }

    private function isGridTemplateAreasNone(string $value): bool
    {
        return strcasecmp(trim($value), 'none') === 0;
    }

    /**
     * @return array{
     *     grid-row-start:string,
     *     grid-column-start:string,
     *     grid-row-end:string,
     *     grid-column-end:string
     * }|null
     */
    private function parseGridArea(string $value): ?array
    {
        $parts = $this->splitGridPlacement($value);
        if (count($parts) < 1 || count($parts) > 4) {
            return null;
        }

        if (count($parts) === 1) {
            $opposite = $this->defaultGridLineEndValue($parts[0]);
            $parts = [$parts[0], $opposite, $opposite, $opposite];
        } elseif (count($parts) === 2) {
            $parts = [
                $parts[0],
                $parts[1],
                $this->defaultGridLineEndValue($parts[0]),
                $this->defaultGridLineEndValue($parts[1]),
            ];
        } elseif (count($parts) === 3) {
            $parts = [
                $parts[0],
                $parts[1],
                $parts[2],
                $this->defaultGridLineEndValue($parts[1]),
            ];
        }

        return array_combine(self::GRID_AREA_COMPONENTS, $parts) ?: null;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseGridLineShorthand(string $value): ?array
    {
        $parts = $this->splitGridPlacement($value);
        if (count($parts) < 1 || count($parts) > 2) {
            return null;
        }

        return [$parts[0], $parts[1] ?? $this->defaultGridLineEndValue($parts[0])];
    }

    /**
     * @return list<string>
     */
    private function splitGridPlacement(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', $this->splitTopLevel($value, '/')),
            static fn (string $part): bool => $part !== ''
        ));
    }

    /**
     * @param array{value:string, important:bool}|null $start
     * @param array{value:string, important:bool}|null $end
     * @return array{value:string, important:bool}|null
     */
    private function composeGridPlacement(?array $start, ?array $end): ?array
    {
        if ($start === null || $end === null || $start['important'] !== $end['important']) {
            return null;
        }

        return [
            'value' => $this->serializeGridLinePlacement($start['value'], $end['value']),
            'important' => $start['important'],
        ];
    }

    private function defaultGridLineEndValue(string $start): string
    {
        return $this->isGridAreaLineName($start) ? $start : 'auto';
    }

    private function isGridAreaLineName(string $value): bool
    {
        $value = trim($value);

        return preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $value) === 1
            && !in_array(strtolower($value), ['auto', 'span'], true);
    }

    private function canOmitGridLineEnd(string $start, string $end): bool
    {
        return strcasecmp(trim($end), 'auto') === 0
            || ($this->isGridAreaLineName($start) && trim($start) === trim($end));
    }

    private function serializeGridLinePlacement(string $start, string $end): string
    {
        if ($this->canOmitGridLineEnd($start, $end)) {
            return $start;
        }

        return $start . ' / ' . $end;
    }

    /**
     * @param array<string, string> $values
     */
    private function serializeGridAreaPlacement(array $values): string
    {
        $rowStart = $values['grid-row-start'];
        $columnStart = $values['grid-column-start'];
        $rowEnd = $values['grid-row-end'];
        $columnEnd = $values['grid-column-end'];

        $canOmitColumnEnd = $this->canOmitGridLineEnd($columnStart, $columnEnd);
        $canOmitRowEnd = $canOmitColumnEnd && $this->canOmitGridLineEnd($rowStart, $rowEnd);
        $canOmitColumnStart = $canOmitRowEnd && $this->canOmitGridLineEnd($rowStart, $columnStart);

        $parts = [$rowStart];
        if (!$canOmitColumnStart) {
            $parts[] = $columnStart;
        }
        if (!$canOmitRowEnd) {
            $parts[] = $rowEnd;
        }
        if (!$canOmitColumnEnd) {
            $parts[] = $columnEnd;
        }

        return implode(' / ', $parts);
    }

    /**
     * @return array<string, string>|null
     */
    private function gridPlacementLonghandValuesFromShorthand(string $property, string $value): ?array
    {
        if ($property === 'grid-area') {
            return $this->parseGridArea($value);
        }

        if ($property === 'grid-row') {
            $placement = $this->parseGridLineShorthand($value);

            return $placement === null
                ? null
                : [
                    'grid-row-start' => $placement[0],
                    'grid-row-end' => $placement[1],
                ];
        }

        if ($property === 'grid-column') {
            $placement = $this->parseGridLineShorthand($value);

            return $placement === null
                ? null
                : [
                    'grid-column-start' => $placement[0],
                    'grid-column-end' => $placement[1],
                ];
        }

        return null;
    }

    /**
     * @param array<string, string> $values
     */
    private function serializeGridPlacementShorthand(string $property, array $values): ?string
    {
        if ($property === 'grid-area') {
            foreach (self::GRID_AREA_COMPONENTS as $component) {
                if (!array_key_exists($component, $values)) {
                    return null;
                }
            }

            return $this->serializeGridAreaPlacement($values);
        }

        if ($property === 'grid-row' && isset($values['grid-row-start'], $values['grid-row-end'])) {
            return $this->serializeGridLinePlacement($values['grid-row-start'], $values['grid-row-end']);
        }

        if ($property === 'grid-column' && isset($values['grid-column-start'], $values['grid-column-end'])) {
            return $this->serializeGridLinePlacement($values['grid-column-start'], $values['grid-column-end']);
        }

        return null;
    }

    private function isGridProperty(string $property): bool
    {
        return $property === 'grid-area'
            || $property === 'grid-template'
            || $property === 'grid'
            || $property === 'grid-row'
            || $property === 'grid-column'
            || in_array($property, self::GRID_TEMPLATE_COMPONENTS, true)
            || in_array($property, self::GRID_AUTO_COMPONENTS, true)
            || in_array($property, self::GRID_AREA_COMPONENTS, true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getPlaceAlignmentProperty(array $entries, string $property): ?array
    {
        $shorthand = $this->placeAlignmentShorthandForProperty($property);
        if ($shorthand === null) {
            return null;
        }

        $components = [
            'align' => null,
            'justify' => null,
        ];
        foreach ($entries as $entry) {
            if ($entry['property'] === $shorthand) {
                $parsed = $this->parsePlaceAlignmentComponents($shorthand, $entry['value']);
                if ($parsed === null) {
                    continue;
                }

                $components['align'] = [
                    'value' => $parsed['align'],
                    'important' => $entry['important'],
                ];
                $components['justify'] = [
                    'value' => $parsed['justify'],
                    'important' => $entry['important'],
                ];
                continue;
            }

            $slot = $this->placeAlignmentLonghandSlot($shorthand, $entry['property']);
            if ($slot === null) {
                continue;
            }

            $normalized = $this->normalizePlaceAlignmentComponent($shorthand, $slot, $entry['value']);
            if ($normalized === null) {
                continue;
            }

            $components[$slot] = [
                'value' => $normalized,
                'important' => $entry['important'],
            ];
        }

        $longhands = self::PLACE_ALIGNMENT_SHORTHANDS[$shorthand];
        if ($property === $longhands['align']) {
            return $components['align'];
        }
        if ($property === $longhands['justify']) {
            return $components['justify'];
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializePlaceAlignmentComponents(
                $shorthand,
                $components['align']['value'],
                $components['justify']['value']
            ),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setPlaceAlignmentLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        $shorthand = $this->placeAlignmentShorthandForLonghand($property);
        if ($shorthand === null) {
            return null;
        }

        $slot = $this->placeAlignmentLonghandSlot($shorthand, $property);
        if ($slot === null) {
            return null;
        }

        $value = $this->normalizePlaceAlignmentComponent($shorthand, $slot, $value);
        if ($value === null) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== $shorthand) {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $components = $this->parsePlaceAlignmentComponents($shorthand, $entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$slot] = $value;
            $entries[$index] = [
                'property' => $shorthand,
                'value' => $this->serializePlaceAlignmentComponents($shorthand, $components['align'], $components['justify']),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removePlaceAlignmentLonghand(array $entries, string $property): string
    {
        $shorthand = $this->placeAlignmentShorthandForLonghand($property);
        if ($shorthand === null) {
            return $this->serializeEntries($entries);
        }

        $slot = $this->placeAlignmentLonghandSlot($shorthand, $property);
        if ($slot === null) {
            return $this->serializeEntries($entries);
        }

        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== $shorthand) {
                $result[] = $entry;
                continue;
            }

            $components = $this->parsePlaceAlignmentComponents($shorthand, $entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            $remainingSlot = $slot === 'align' ? 'justify' : 'align';
            $result[] = [
                'property' => self::PLACE_ALIGNMENT_SHORTHANDS[$shorthand][$remainingSlot],
                'value' => $components[$remainingSlot],
                'important' => $entry['important'],
            ];
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{align:string, justify:string}|null
     */
    private function parsePlaceAlignmentComponents(string $shorthand, string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(strtolower(trim($value)));
        if ($tokens === [] || count($tokens) > 4) {
            return null;
        }

        $maxAlignLength = min(2, count($tokens));
        for ($alignLength = 1; $alignLength <= $maxAlignLength; $alignLength++) {
            $align = $this->normalizePlaceAlignmentComponent(
                $shorthand,
                'align',
                implode(' ', array_slice($tokens, 0, $alignLength))
            );
            if ($align === null) {
                continue;
            }

            $remaining = array_slice($tokens, $alignLength);
            if ($remaining === []) {
                return [
                    'align' => $align,
                    'justify' => $this->defaultPlaceAlignmentJustify($shorthand, $align),
                ];
            }
            if (count($remaining) > 2) {
                continue;
            }

            $justify = $this->normalizePlaceAlignmentComponent($shorthand, 'justify', implode(' ', $remaining));
            if ($justify !== null) {
                return [
                    'align' => $align,
                    'justify' => $justify,
                ];
            }
        }

        return null;
    }

    private function normalizePlaceAlignmentComponent(string $shorthand, string $slot, string $value): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel(strtolower(trim($value)));
        if ($tokens === [] || count($tokens) > 2) {
            return null;
        }

        $baseline = $this->normalizeBaselinePosition($tokens);
        if ($baseline !== null) {
            if ($shorthand === 'place-content' && $slot === 'justify') {
                return null;
            }

            return $baseline;
        }

        if ($slot === 'justify' && $shorthand === 'place-items') {
            $legacy = $this->normalizeLegacyJustify($tokens);
            if ($legacy !== null) {
                return $legacy;
            }
        }

        if (count($tokens) === 1) {
            $token = $tokens[0];
            if ($this->isPlaceAlignmentSingleKeyword($shorthand, $slot, $token)) {
                return $token;
            }

            return null;
        }

        if (!in_array($tokens[0], ['safe', 'unsafe'], true)) {
            return null;
        }

        $position = $tokens[1];
        if ($this->isPlaceAlignmentPositionKeyword($shorthand, $slot, $position)) {
            return $tokens[0] . ' ' . $position;
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function normalizeBaselinePosition(array $tokens): ?string
    {
        if ($tokens === ['baseline'] || $tokens === ['first', 'baseline']) {
            return 'baseline';
        }
        if ($tokens === ['last', 'baseline']) {
            return 'last baseline';
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function normalizeLegacyJustify(array $tokens): ?string
    {
        if (count($tokens) !== 2) {
            return null;
        }

        if ($tokens[0] === 'legacy' && in_array($tokens[1], ['left', 'right', 'center'], true)) {
            return 'legacy ' . $tokens[1];
        }
        if ($tokens[1] === 'legacy' && in_array($tokens[0], ['left', 'right', 'center'], true)) {
            return 'legacy ' . $tokens[0];
        }

        return null;
    }

    private function isPlaceAlignmentSingleKeyword(string $shorthand, string $slot, string $token): bool
    {
        if ($shorthand === 'place-content') {
            if (in_array($token, ['normal', 'space-between', 'space-around', 'space-evenly', 'stretch'], true)) {
                return true;
            }

            return $this->isPlaceAlignmentPositionKeyword($shorthand, $slot, $token);
        }

        if ($shorthand === 'place-self' && $slot === 'align' && $token === 'auto') {
            return true;
        }
        if ($shorthand === 'place-self' && $slot === 'justify' && $token === 'auto') {
            return true;
        }

        if (in_array($token, ['normal', 'stretch'], true)) {
            return true;
        }

        return $this->isPlaceAlignmentPositionKeyword($shorthand, $slot, $token);
    }

    private function isPlaceAlignmentPositionKeyword(string $shorthand, string $slot, string $token): bool
    {
        $contentPositions = ['center', 'start', 'end', 'flex-start', 'flex-end'];
        $selfPositions = ['center', 'start', 'end', 'self-start', 'self-end', 'flex-start', 'flex-end'];

        if ($shorthand === 'place-content') {
            if ($slot === 'justify' && in_array($token, ['left', 'right'], true)) {
                return true;
            }

            return in_array($token, $contentPositions, true);
        }

        if ($slot === 'justify' && in_array($token, ['left', 'right'], true)) {
            return true;
        }

        return in_array($token, $selfPositions, true);
    }

    private function defaultPlaceAlignmentJustify(string $shorthand, string $align): string
    {
        if ($shorthand === 'place-content' && $this->isBaselineAlignmentValue($align)) {
            return 'start';
        }

        return $align;
    }

    private function serializePlaceAlignmentComponents(string $shorthand, string $align, string $justify): string
    {
        if ($this->placeAlignmentCanOmitJustify($shorthand, $align, $justify)) {
            return $align;
        }

        return $align . ' ' . $justify;
    }

    private function placeAlignmentCanOmitJustify(string $shorthand, string $align, string $justify): bool
    {
        if ($shorthand === 'place-content') {
            if ($this->isBaselineAlignmentValue($align)) {
                return false;
            }

            return $align === $justify;
        }

        if ($justify === 'auto' && $shorthand === 'place-self') {
            return true;
        }
        if ($justify === 'normal' && $align === 'normal') {
            return true;
        }
        if ($justify === 'stretch' && $align === 'normal') {
            return true;
        }
        if ($this->isBaselineAlignmentValue($justify) && $align === $justify) {
            return true;
        }

        return $align === $justify && $this->isSelfPositionAlignmentValue($align);
    }

    private function isBaselineAlignmentValue(string $value): bool
    {
        return $value === 'baseline' || $value === 'last baseline';
    }

    private function isSelfPositionAlignmentValue(string $value): bool
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if (count($tokens) === 2 && in_array($tokens[0], ['safe', 'unsafe'], true)) {
            $value = $tokens[1];
        }

        return in_array($value, ['center', 'start', 'end', 'self-start', 'self-end', 'flex-start', 'flex-end'], true);
    }

    private function isPlaceAlignmentProperty(string $property): bool
    {
        return $this->placeAlignmentShorthandForProperty($property) !== null;
    }

    private function placeAlignmentShorthandForProperty(string $property): ?string
    {
        if (isset(self::PLACE_ALIGNMENT_SHORTHANDS[$property])) {
            return $property;
        }

        return $this->placeAlignmentShorthandForLonghand($property);
    }

    private function placeAlignmentShorthandForLonghand(string $property): ?string
    {
        foreach (self::PLACE_ALIGNMENT_SHORTHANDS as $shorthand => $longhands) {
            if ($property === $longhands['align'] || $property === $longhands['justify']) {
                return $shorthand;
            }
        }

        return null;
    }

    private function placeAlignmentLonghandSlot(string $shorthand, string $property): ?string
    {
        foreach (self::PLACE_ALIGNMENT_SHORTHANDS[$shorthand] ?? [] as $slot => $longhand) {
            if ($property === $longhand) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getGapProperty(array $entries, string $property): ?array
    {
        if (!$this->isGapProperty($property)) {
            return null;
        }

        $components = array_fill_keys(self::GAP_LONGHANDS, null);
        foreach ($entries as $entry) {
            if ($entry['property'] === 'gap') {
                $parsed = $this->parseGapComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::GAP_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isGapLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'gap') {
            return $components[$property];
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeGapComponents(
                $components['row-gap']['value'],
                $components['column-gap']['value']
            ),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setGapLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isGapLonghand($property)) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'gap') {
                continue;
            }

            $components = $this->parseGapComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'gap',
                'value' => $this->serializeGapComponents($components['row-gap'], $components['column-gap']),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeGapLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'gap') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseGapComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::GAP_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{row-gap:string, column-gap:string}|null
     */
    private function parseGapComponents(string $value): ?array
    {
        $parts = $this->splitWhitespaceTopLevel($value);
        if (count($parts) < 1 || count($parts) > 2) {
            return null;
        }

        return [
            'row-gap' => $parts[0],
            'column-gap' => $parts[1] ?? $parts[0],
        ];
    }

    private function serializeGapComponents(string $row, string $column): string
    {
        return $row === $column ? $row : $row . ' ' . $column;
    }

    private function isGapProperty(string $property): bool
    {
        return $property === 'gap' || $this->isGapLonghand($property);
    }

    private function isGapLonghand(string $property): bool
    {
        return in_array($property, self::GAP_LONGHANDS, true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getOverflowProperty(array $entries, string $property): ?array
    {
        if (!$this->isOverflowProperty($property)) {
            return null;
        }

        $components = array_fill_keys(self::OVERFLOW_LONGHANDS, null);
        foreach ($entries as $entry) {
            if ($entry['property'] === 'overflow') {
                $parsed = $this->parseOverflowComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::OVERFLOW_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isOverflowLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeOverflowValue($entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'overflow') {
            return $components[$property];
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeOverflowComponents(
                $components['overflow-x']['value'],
                $components['overflow-y']['value']
            ),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setOverflowLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isOverflowLonghand($property)) {
            return null;
        }

        $value = $this->normalizeOverflowValue($value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'overflow') {
                continue;
            }

            $components = $this->parseOverflowComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'overflow',
                'value' => $this->serializeOverflowComponents($components['overflow-x'], $components['overflow-y']),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeOverflowLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'overflow') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseOverflowComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::OVERFLOW_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{overflow-x:string, overflow-y:string}|null
     */
    private function parseOverflowComponents(string $value): ?array
    {
        $parts = $this->splitWhitespaceTopLevel($value);
        if (count($parts) < 1 || count($parts) > 2) {
            return null;
        }

        return [
            'overflow-x' => $this->normalizeOverflowValue($parts[0]),
            'overflow-y' => $this->normalizeOverflowValue($parts[1] ?? $parts[0]),
        ];
    }

    private function serializeOverflowComponents(string $x, string $y): string
    {
        return $x === $y ? $x : $x . ' ' . $y;
    }

    private function normalizeOverflowValue(string $value): string
    {
        $value = trim($value);
        $lower = strtolower($value);

        return in_array($lower, ['visible', 'hidden', 'clip', 'scroll', 'auto'], true) ? $lower : $value;
    }

    private function isOverflowProperty(string $property): bool
    {
        return $property === 'overflow' || $this->isOverflowLonghand($property);
    }

    private function isOverflowLonghand(string $property): bool
    {
        return in_array($property, self::OVERFLOW_LONGHANDS, true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getBorderProperty(array $entries, string $property): ?array
    {
        if (!$this->isBorderProperty($property)) {
            return null;
        }

        $sides = $this->resolveBorderSides($entries);
        if ($property === 'border') {
            return $this->composeBorderShorthand($sides);
        }

        if (preg_match('/^border-(width|style|color)$/', $property, $matches) === 1) {
            return $this->composeBorderComponentShorthand($sides, $matches[1]);
        }

        if (preg_match('/^border-(top|right|bottom|left)$/', $property, $matches) === 1) {
            return $this->composeBorderSideShorthand($sides[$matches[1]]);
        }

        if (preg_match('/^border-(top|right|bottom|left)-(width|style|color)$/', $property, $matches) === 1) {
            return $sides[$matches[1]][$matches[2]];
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array<string, array<string, array{value:string, important:bool}|null>>
     */
    private function resolveBorderSides(array $entries): array
    {
        $sides = [];
        foreach (self::BORDER_SIDES as $side) {
            $sides[$side] = array_fill_keys(self::BORDER_COMPONENTS, null);
        }

        foreach ($entries as $entry) {
            $property = $entry['property'];
            $important = $entry['important'];

            if ($property === 'border') {
                $components = $this->parseBorderValue($entry['value']);
                foreach (self::BORDER_SIDES as $side) {
                    $this->applyBorderComponents($sides[$side], $components, $important);
                }
                continue;
            }

            if (preg_match('/^border-(width|style|color)$/', $property, $matches) === 1) {
                $component = $matches[1];
                $expanded = $this->expandBoxShorthand($entry['value']);
                if ($expanded === null) {
                    continue;
                }
                foreach (self::BORDER_SIDES as $side) {
                    $sides[$side][$component] = [
                        'value' => $expanded[$side],
                        'important' => $important,
                    ];
                }
                continue;
            }

            if (preg_match('/^border-(top|right|bottom|left)$/', $property, $matches) === 1) {
                $components = $this->parseBorderValue($entry['value']);
                $this->applyBorderComponents($sides[$matches[1]], $components, $important);
                continue;
            }

            if (preg_match('/^border-(top|right|bottom|left)-(width|style|color)$/', $property, $matches) === 1) {
                $sides[$matches[1]][$matches[2]] = [
                    'value' => $entry['value'],
                    'important' => $important,
                ];
            }
        }

        return $sides;
    }

    /**
     * @param array<string, array{value:string, important:bool}|null> $side
     * @param array{width:?string, style:?string, color:?string} $components
     */
    private function applyBorderComponents(array &$side, array $components, bool $important): void
    {
        foreach (self::BORDER_COMPONENTS as $component) {
            if ($components[$component] === null) {
                continue;
            }

            $side[$component] = [
                'value' => $components[$component],
                'important' => $important,
            ];
        }
    }

    /**
     * @return array{width:?string, style:?string, color:?string}
     */
    private function parseBorderValue(string $value): array
    {
        $components = [
            'width' => null,
            'style' => null,
            'color' => null,
        ];

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($components['style'] === null && in_array($lower, self::BORDER_STYLES, true)) {
                $components['style'] = $token;
                continue;
            }

            if ($components['width'] === null && $this->isBorderWidthToken($token)) {
                $components['width'] = $token;
                continue;
            }

            if ($components['color'] === null) {
                $components['color'] = $token;
                continue;
            }

            $components['color'] .= ' ' . $token;
        }

        return $components;
    }

    private function isBorderWidthToken(string $token): bool
    {
        $lower = strtolower($token);
        if (in_array($lower, self::BORDER_WIDTH_KEYWORDS, true)) {
            return true;
        }

        return preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)(?:[a-zA-Z%]+)?|calc\(|var\()/i', $token) === 1;
    }

    /**
     * @param array<string, array<string, array{value:string, important:bool}|null>> $sides
     * @return array{value:string, important:bool}|null
     */
    private function composeBorderShorthand(array $sides): ?array
    {
        $top = $sides['top'];
        foreach (self::BORDER_COMPONENTS as $component) {
            if ($top[$component] === null) {
                return null;
            }
        }

        foreach (self::BORDER_SIDES as $side) {
            foreach (self::BORDER_COMPONENTS as $component) {
                if ($sides[$side][$component] === null || $sides[$side][$component] !== $top[$component]) {
                    return null;
                }
            }
        }

        return [
            'value' => $this->composeBorderValue($top),
            'important' => $top['width']['important'],
        ];
    }

    /**
     * @param array<string, array<string, array{value:string, important:bool}|null>> $sides
     * @return array{value:string, important:bool}|null
     */
    private function composeBorderComponentShorthand(array $sides, string $component): ?array
    {
        $values = [];
        $important = null;
        foreach (self::BORDER_SIDES as $side) {
            $part = $sides[$side][$component];
            if ($part === null) {
                return null;
            }
            if ($important === null) {
                $important = $part['important'];
            } elseif ($part['important'] !== $important) {
                return null;
            }
            $values[$side] = $part['value'];
        }

        return [
            'value' => $this->compressBoxShorthand($values),
            'important' => $important,
        ];
    }

    /**
     * @param array<string, array{value:string, important:bool}|null> $side
     * @return array{value:string, important:bool}|null
     */
    private function composeBorderSideShorthand(array $side): ?array
    {
        foreach (self::BORDER_COMPONENTS as $component) {
            if ($side[$component] === null) {
                return null;
            }
        }

        $important = $side['width']['important'];
        foreach (self::BORDER_COMPONENTS as $component) {
            if ($side[$component]['important'] !== $important) {
                return null;
            }
        }

        return [
            'value' => $this->composeBorderValue($side),
            'important' => $important,
        ];
    }

    /**
     * @param array<string, array{value:string, important:bool}|null> $components
     */
    private function composeBorderValue(array $components): string
    {
        return implode(' ', [
            $components['width']['value'],
            $components['style']['value'],
            $components['color']['value'],
        ]);
    }

    private function isBorderProperty(string $property): bool
    {
        return $property === 'border'
            || preg_match('/^border-(?:width|style|color)$/', $property) === 1
            || preg_match('/^border-(?:top|right|bottom|left)(?:-(?:width|style|color))?$/', $property) === 1;
    }

    private function isBorderComponentLonghand(string $property): bool
    {
        return preg_match('/^border-(?:top|right|bottom|left)-(?:width|style|color)$/', $property) === 1;
    }

    /**
     * @return list<string>|null
     */
    private function borderShorthandLonghands(string $property): ?array
    {
        if ($property === 'border') {
            return array_merge(
                $this->borderComponentLonghands('width'),
                $this->borderComponentLonghands('style'),
                $this->borderComponentLonghands('color')
            );
        }

        if (in_array($property, ['border-width', 'border-style', 'border-color'], true)) {
            return $this->borderComponentLonghands(substr($property, strlen('border-')));
        }

        if (preg_match('/^border-(top|right|bottom|left)$/', $property, $matches) !== 1) {
            return null;
        }

        return [
            "border-{$matches[1]}-width",
            "border-{$matches[1]}-style",
            "border-{$matches[1]}-color",
        ];
    }

    /**
     * @return list<string>
     */
    private function borderComponentLonghands(string $component): array
    {
        return array_map(
            static fn (string $side): string => "border-{$side}-{$component}",
            self::BORDER_SIDES
        );
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getOutlineProperty(array $entries, string $property): ?array
    {
        if (!$this->isOutlineProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'outline') {
                $parsed = $this->completeOutlineComponents($this->parseOutlineValue($entry['value']));
                foreach (self::OUTLINE_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isOutlineLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeOutlineLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'outline') {
            return $components[$property] ?? null;
        }

        foreach (self::OUTLINE_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->composeOutlineShorthandValue([
                'outline-width' => $components['outline-width']['value'],
                'outline-style' => $components['outline-style']['value'],
                'outline-color' => $components['outline-color']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @return array{outline-width:?string, outline-style:?string, outline-color:?string}
     */
    private function parseOutlineValue(string $value): array
    {
        $components = [
            'outline-width' => null,
            'outline-style' => null,
            'outline-color' => null,
        ];

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if ($components['outline-style'] === null && in_array($lower, self::OUTLINE_STYLES, true)) {
                $components['outline-style'] = $lower;
                continue;
            }

            if (
                $components['outline-color'] === null
                && $components['outline-style'] !== null
                && preg_match('/^var\(/i', $token) === 1
            ) {
                $components['outline-color'] = $token;
                continue;
            }

            if ($components['outline-width'] === null && $this->isBorderWidthToken($token)) {
                $components['outline-width'] = $token;
                continue;
            }

            if ($components['outline-color'] === null) {
                $components['outline-color'] = $this->normalizeOutlineColorValue($token);
                continue;
            }

            $components['outline-color'] .= ' ' . $token;
        }

        return $components;
    }

    /**
     * @param array{outline-width:?string, outline-style:?string, outline-color:?string} $components
     * @return array{outline-width:string, outline-style:string, outline-color:string}
     */
    private function completeOutlineComponents(array $components): array
    {
        return [
            'outline-width' => $components['outline-width'] ?? 'medium',
            'outline-style' => $components['outline-style'] ?? 'none',
            'outline-color' => $components['outline-color'] ?? 'currentcolor',
        ];
    }

    /**
     * @param array{outline-width:string, outline-style:string, outline-color:string} $components
     */
    private function composeOutlineShorthandValue(array $components): string
    {
        $width = trim($components['outline-width']);
        $style = strtolower(trim($components['outline-style']));
        $color = $this->normalizeOutlineColorValue($components['outline-color']);
        $parts = [];

        if (strcasecmp($width, 'medium') !== 0) {
            $parts[] = $width;
        }
        if (strcasecmp($style, 'none') !== 0) {
            $parts[] = $style;
        }
        if (strcasecmp($color, 'currentcolor') !== 0) {
            $parts[] = $color;
        }

        return $parts === [] ? 'none' : implode(' ', $parts);
    }

    private function normalizeOutlineLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'outline-style' => strtolower(trim($value)),
            'outline-color' => $this->normalizeOutlineColorValue($value),
            default => trim($value),
        };
    }

    private function normalizeOutlineColorValue(string $value): string
    {
        $value = trim($value);

        return strcasecmp($value, 'currentcolor') === 0 ? 'currentcolor' : $value;
    }

    private function isOutlineProperty(string $property): bool
    {
        return $property === 'outline' || $this->isOutlineLonghand($property);
    }

    private function isOutlineLonghand(string $property): bool
    {
        return in_array($property, self::OUTLINE_LONGHANDS, true);
    }

    /**
     * @return array<string, string>|null
     */
    private function borderLonghandValuesFromShorthand(string $property, string $value): ?array
    {
        if ($property === 'border') {
            $components = $this->completeBorderComponents($this->parseBorderValue($value));
            $values = [];
            foreach (self::BORDER_SIDES as $side) {
                foreach (self::BORDER_COMPONENTS as $component) {
                    $values["border-{$side}-{$component}"] = $components[$component];
                }
            }

            return $values;
        }

        if (preg_match('/^border-(width|style|color)$/', $property, $matches) === 1) {
            $expanded = $this->expandBoxShorthand($value);
            if ($expanded === null) {
                return null;
            }

            $values = [];
            foreach (self::BORDER_SIDES as $side) {
                $values["border-{$side}-{$matches[1]}"] = $expanded[$side];
            }

            return $values;
        }

        if (preg_match('/^border-(top|right|bottom|left)$/', $property, $matches) === 1) {
            $components = $this->completeBorderComponents($this->parseBorderValue($value));

            return [
                "border-{$matches[1]}-width" => $components['width'],
                "border-{$matches[1]}-style" => $components['style'],
                "border-{$matches[1]}-color" => $components['color'],
            ];
        }

        return null;
    }

    /**
     * @param array{width:?string, style:?string, color:?string} $components
     * @return array{width:string, style:string, color:string}
     */
    private function completeBorderComponents(array $components): array
    {
        return [
            'width' => $components['width'] ?? 'medium',
            'style' => $components['style'] ?? 'none',
            'color' => $components['color'] ?? 'currentcolor',
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{
     *     direction:array{value:string, important:bool}|null,
     *     wrap:array{value:string, important:bool}|null,
     *     flow:bool
     * }
     */
    private function resolveFlexFlowComponents(array $entries, string $prefix): array
    {
        $components = [
            'direction' => null,
            'wrap' => null,
            'flow' => false,
        ];

        foreach ($entries as $entry) {
            if ($entry['property'] === $this->flexProperty($prefix, 'flex-flow')) {
                $components['flow'] = true;
                $expanded = $this->expandFlexFlow($entry['value']);
                foreach ($expanded as $component => $value) {
                    if ($value !== null) {
                        $components[$component] = [
                            'value' => $value,
                            'important' => $entry['important'],
                        ];
                    }
                }
                continue;
            }

            if ($entry['property'] === $this->flexProperty($prefix, 'flex-direction')) {
                $components['direction'] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
                continue;
            }

            if ($entry['property'] === $this->flexProperty($prefix, 'flex-wrap')) {
                $components['wrap'] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $components;
    }

    private function flexPrefixForProperty(string $property): ?string
    {
        if ($property === '-webkit-flex' || str_starts_with($property, '-webkit-flex-')) {
            return '-webkit-';
        }

        if ($property === 'flex' || str_starts_with($property, 'flex-')) {
            return '';
        }

        return null;
    }

    private function baseFlexProperty(string $property): ?string
    {
        if (str_starts_with($property, '-webkit-')) {
            $property = substr($property, strlen('-webkit-'));
        }

        return in_array($property, ['flex', 'flex-flow', 'flex-direction', 'flex-wrap', 'flex-grow', 'flex-shrink', 'flex-basis'], true)
            ? $property
            : null;
    }

    private function flexProperty(string $prefix, string $base): string
    {
        return $prefix . $base;
    }

    /**
     * @return array{direction:?string, wrap:?string}
     */
    private function expandFlexFlow(string $value): array
    {
        $components = [
            'direction' => null,
            'wrap' => null,
        ];

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower($token);
            if (in_array($lower, self::FLEX_DIRECTIONS, true)) {
                $components['direction'] = $token;
                continue;
            }

            if (in_array($lower, self::FLEX_WRAPS, true)) {
                $components['wrap'] = $token;
            }
        }

        return $components;
    }

    private function composeFlexFlow(?string $direction, ?string $wrap): string
    {
        return implode(' ', array_values(array_filter(
            [$direction, $wrap],
            static fn (?string $part): bool => $part !== null && $part !== ''
        )));
    }

    /**
     * @param array{flex-grow:string, flex-shrink:string, flex-basis:string} $components
     */
    private function composeFlexShorthandValue(array $components): string
    {
        $grow = $components['flex-grow'];
        $shrink = $components['flex-shrink'];
        $basis = $components['flex-basis'];

        if ($this->flexNumberEquals($grow, 0.0) && $this->flexNumberEquals($shrink, 0.0) && strtolower($basis) === 'auto') {
            return 'none';
        }

        $basisKind = $this->flexBasisZeroKind($basis);
        $parts = [];
        if (!$this->flexNumberEquals($grow, 1.0) || !$this->flexNumberEquals($shrink, 1.0) || $basisKind !== 'nonzero') {
            $parts[] = $grow;
            if (!$this->flexNumberEquals($shrink, 1.0) || $basisKind === 'length') {
                $parts[] = $shrink;
            }
        }

        if ($basisKind !== 'percentage') {
            $parts[] = $basis;
        }

        return implode(' ', $parts);
    }

    private function flexBasisZeroKind(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[+-]?(?:0+(?:\.0+)?|\.0+)(?:e[+-]?\d+)?%$/', $value) === 1) {
            return 'percentage';
        }

        if (preg_match('/^[+-]?(?:0+(?:\.0+)?|\.0+)(?:e[+-]?\d+)?(?:[a-z]+)?$/', $value) === 1) {
            return 'length';
        }

        return 'nonzero';
    }

    private function flexNumberEquals(string $value, float $expected): bool
    {
        if (!$this->isFlexNumberToken($value)) {
            return false;
        }

        return abs(((float) $value) - $expected) < 0.0000001;
    }

    /**
     * @return array{flex-grow:string, flex-shrink:string, flex-basis:string}|null
     */
    private function parseFlexShorthandComponents(string $value): ?array
    {
        $value = trim($value);
        if (strcasecmp($value, 'none') === 0) {
            return [
                'flex-grow' => '0',
                'flex-shrink' => '0',
                'flex-basis' => 'auto',
            ];
        }

        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return null;
        }

        $grow = null;
        $shrink = null;
        $basis = null;
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($grow === null && $this->isFlexNumberToken($token)) {
                $grow = $this->normalizeFlexNumberValue($token);
                if ($index + 1 < $count && $this->isFlexNumberToken($tokens[$index + 1])) {
                    $shrink = $this->normalizeFlexNumberValue($tokens[++$index]);
                }
                continue;
            }

            if ($basis === null && $this->isFlexBasisToken($token)) {
                $basis = $this->normalizeFlexBasisValue($token);
                continue;
            }

            return null;
        }

        return [
            'flex-grow' => $grow ?? '1',
            'flex-shrink' => $shrink ?? '1',
            'flex-basis' => $basis ?? '0%',
        ];
    }

    private function normalizeFlexLonghandValue(string $base, string $value): ?string
    {
        return match ($base) {
            'flex-grow', 'flex-shrink' => $this->isFlexNumberToken($value) ? $this->normalizeFlexNumberValue($value) : null,
            'flex-basis' => $this->isFlexBasisToken($value) ? $this->normalizeFlexBasisValue($value) : null,
            default => null,
        };
    }

    private function isFlexNumberToken(string $value): bool
    {
        return preg_match('/^[+-]?(?:\d*\.\d+|\d+)(?:[eE][+-]?\d+)?$/', trim($value)) === 1;
    }

    private function normalizeFlexNumberValue(string $value): string
    {
        $number = (float) trim($value);
        if (abs($number) < 0.0000001) {
            return '0';
        }

        return rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');
    }

    private function isFlexBasisToken(string $value): bool
    {
        $value = trim($value);
        if (strcasecmp($value, 'auto') === 0) {
            return true;
        }

        if (preg_match('/^[+-]?(?:\d*\.\d+|\d+)(?:[eE][+-]?\d+)?%$/', $value) === 1) {
            return true;
        }

        if (preg_match('/^[+-]?(?:(?:\d*\.\d+|\d+)(?:[eE][+-]?\d+)?)(?:[a-z]+)$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^[+-]?(?:0+(?:\.0+)?|\.0+)(?:[eE][+-]?\d+)?$/', $value) === 1) {
            return true;
        }

        return preg_match('/^(?:calc|min|max|clamp)\(/i', $value) === 1;
    }

    private function normalizeFlexBasisValue(string $value): string
    {
        $value = trim($value);
        if (strcasecmp($value, 'auto') === 0) {
            return 'auto';
        }

        if (preg_match('/^([+-]?(?:\d*\.\d+|\d+)(?:[eE][+-]?\d+)?)(%|[a-z]+)$/i', $value, $matches) === 1) {
            $number = $this->normalizeFlexNumberValue($matches[1]);
            if ($number === '0' && $matches[2] !== '%') {
                return '0';
            }

            return $number . strtolower($matches[2]);
        }

        if ($this->isFlexNumberToken($value) && $this->flexNumberEquals($value, 0.0)) {
            return '0';
        }

        return $value;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{
     *     flex-grow:array{value:string, important:bool}|null,
     *     flex-shrink:array{value:string, important:bool}|null,
     *     flex-basis:array{value:string, important:bool}|null
     * }
     */
    private function resolveFlexItemComponents(array $entries, string $prefix): array
    {
        $components = [
            'flex-grow' => null,
            'flex-shrink' => null,
            'flex-basis' => null,
        ];

        foreach ($entries as $entry) {
            if ($entry['property'] === $this->flexProperty($prefix, 'flex')) {
                $parsed = $this->parseFlexShorthandComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::FLEX_ITEM_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            $entryPrefix = $this->flexPrefixForProperty($entry['property']);
            $entryBase = $this->baseFlexProperty($entry['property']);
            if ($entryPrefix !== $prefix || !in_array($entryBase, self::FLEX_ITEM_LONGHANDS, true)) {
                continue;
            }

            $value = $this->normalizeFlexLonghandValue($entryBase, $entry['value']);
            if ($value === null) {
                continue;
            }

            $components[$entryBase] = [
                'value' => $value,
                'important' => $entry['important'],
            ];
        }

        return $components;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getTransitionProperty(array $entries, string $property): ?array
    {
        $prefix = $this->transitionPrefixForProperty($property);
        $base = $this->baseTransitionProperty($property);
        if ($prefix === null || $base === null) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            $entryBase = $this->baseTransitionProperty($entry['property']);
            if ($entryBase === null || $this->transitionPrefixForProperty($entry['property']) !== $prefix) {
                continue;
            }

            if ($entryBase === 'transition') {
                $components = $this->transitionComponentsFromShorthand($entry['value'], $entry['important']);
                continue;
            }

            if (in_array($entryBase, self::TRANSITION_LONGHANDS, true)) {
                $components[$entryBase] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        if ($base !== 'transition') {
            return $components[$base] ?? null;
        }

        return $this->composeTransitionProperty($components);
    }

    /**
     * @return array<string, array{value:string, important:bool}>
     */
    private function transitionComponentsFromShorthand(string $value, bool $important): array
    {
        $layers = $this->parseTransitionLayers($value);
        $components = [
            'transition' => ['value' => $value, 'important' => $important],
            'transition-property' => ['value' => implode(', ', array_column($layers, 'property')), 'important' => $important],
            'transition-duration' => ['value' => implode(', ', array_column($layers, 'duration')), 'important' => $important],
            'transition-delay' => ['value' => implode(', ', array_column($layers, 'delay')), 'important' => $important],
            'transition-timing-function' => ['value' => implode(', ', array_column($layers, 'timing')), 'important' => $important],
        ];

        return $components;
    }

    /**
     * @return list<array{property:string, duration:string, delay:string, timing:string}>
     */
    private function parseTransitionLayers(string $value): array
    {
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $layers[] = $this->parseTransitionLayer($layer);
        }

        return $layers === [] ? [$this->parseTransitionLayer('all')] : $layers;
    }

    /**
     * @return array{property:string, duration:string, delay:string, timing:string}
     */
    private function parseTransitionLayer(string $layer): array
    {
        $property = 'all';
        $duration = '0s';
        $delay = '0s';
        $timing = 'ease';
        $propertySet = false;
        $durationSet = false;
        $delaySet = false;
        $timingSet = false;

        foreach ($this->splitWhitespaceTopLevel($layer) as $token) {
            if (!$durationSet && $this->isTransitionTimeToken($token)) {
                $duration = $this->canonicalTransitionTime($token);
                $durationSet = true;
                continue;
            }

            if (!$timingSet && $this->isTransitionTimingToken($token)) {
                $timing = $token;
                $timingSet = true;
                continue;
            }

            if (!$delaySet && $this->isTransitionTimeToken($token)) {
                $delay = $this->canonicalTransitionTime($token);
                $delaySet = true;
                continue;
            }

            if (!$propertySet) {
                $property = $token;
                $propertySet = true;
            } else {
                $property .= ' ' . $token;
            }
        }

        return [
            'property' => $property,
            'duration' => $duration,
            'delay' => $delay,
            'timing' => $timing,
        ];
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     * @return array{value:string, important:bool}|null
     */
    private function composeTransitionProperty(array $components): ?array
    {
        $lists = [];
        $important = null;
        $length = null;
        foreach (self::TRANSITION_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
            if ($important === null) {
                $important = $components[$longhand]['important'];
            } elseif ($components[$longhand]['important'] !== $important) {
                return null;
            }

            $parts = $this->transitionComponentList($components[$longhand]['value']);
            if ($parts === []) {
                return null;
            }
            if ($length === null) {
                $length = count($parts);
            } elseif (count($parts) !== $length) {
                return null;
            }
            $lists[$longhand] = $parts;
        }

        $layers = [];
        for ($i = 0; $i < $length; $i++) {
            $layers[] = $this->serializeTransitionLayer(
                $lists['transition-property'][$i],
                $lists['transition-duration'][$i],
                $lists['transition-timing-function'][$i],
                $lists['transition-delay'][$i]
            );
        }

        return [
            'value' => implode(', ', $layers),
            'important' => $important ?? false,
        ];
    }

    /**
     * @return list<string>
     */
    private function transitionComponentList(string $value): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $part): string => trim($part),
                $this->splitTopLevel($value, ',')
            ),
            static fn (string $part): bool => $part !== ''
        ));
    }

    private function serializeTransitionLayer(string $property, string $duration, string $timing, string $delay): string
    {
        $parts = [$property];
        $duration = $this->canonicalTransitionTime($duration);
        $delay = $this->canonicalTransitionTime($delay);
        if (!$this->isZeroTransitionTime($duration) || !$this->isZeroTransitionTime($delay)) {
            $parts[] = $duration;
        }
        if (!$this->isDefaultTransitionTiming($timing)) {
            $parts[] = $timing;
        }
        if (!$this->isZeroTransitionTime($delay)) {
            $parts[] = $delay;
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setTransitionLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        $prefix = $this->transitionPrefixForProperty($property);
        $base = $this->baseTransitionProperty($property);
        if ($prefix === null || !in_array($base, self::TRANSITION_LONGHANDS, true)) {
            return null;
        }

        $valueCount = count($this->transitionComponentList($value));
        if ($valueCount === 0) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            $entryBase = $this->baseTransitionProperty($entries[$index]['property']);
            if ($entryBase === null || $this->transitionPrefixForProperty($entries[$index]['property']) !== $prefix) {
                continue;
            }

            if ($entryBase === $base) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entryBase !== 'transition') {
                continue;
            }

            $layerCount = count($this->parseTransitionLayers($entries[$index]['value']));
            if ($layerCount !== $valueCount) {
                continue;
            }

            $components = $this->transitionComponentsFromShorthand($entries[$index]['value'], $entries[$index]['important']);
            $components[$base] = [
                'value' => $value,
                'important' => $important,
            ];
            $transition = $this->composeTransitionProperty($components);
            if ($transition === null) {
                continue;
            }

            $entries[$index] = [
                'property' => $this->transitionPropertyName($prefix, 'transition'),
                'value' => $transition['value'],
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function removeTransitionShorthandWithinPriority(array $entries, string $property): array
    {
        $prefix = $this->transitionPrefixForProperty($property);
        if ($prefix === null) {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            function (array $entry) use ($prefix): bool {
                $base = $this->baseTransitionProperty($entry['property']);

                return $base === null || $this->transitionPrefixForProperty($entry['property']) !== $prefix;
            }
        ));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeTransitionLonghand(array $entries, string $property): string
    {
        $prefix = $this->transitionPrefixForProperty($property);
        $base = $this->baseTransitionProperty($property);
        if ($prefix === null || !in_array($base, self::TRANSITION_LONGHANDS, true)) {
            return $this->serializeEntries($entries);
        }

        $result = [];
        foreach ($entries as $entry) {
            $entryBase = $this->baseTransitionProperty($entry['property']);
            if ($entryBase === null || $this->transitionPrefixForProperty($entry['property']) !== $prefix) {
                $result[] = $entry;
                continue;
            }

            if ($entryBase === $base) {
                continue;
            }

            if ($entryBase !== 'transition') {
                $result[] = $entry;
                continue;
            }

            $components = $this->transitionComponentsFromShorthand($entry['value'], $entry['important']);
            foreach (self::TRANSITION_LONGHANDS as $longhand) {
                if ($longhand === $base) {
                    continue;
                }

                $result[] = [
                    'property' => $this->transitionPropertyName($prefix, $longhand),
                    'value' => $components[$longhand]['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeAnimationLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'animation') {
                $result[] = $entry;
                continue;
            }

            $components = $this->animationComponentsFromShorthand($entry['value'], $entry['important']);
            foreach (self::ANIMATION_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand]['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    private function isTransitionProperty(string $property): bool
    {
        return $this->baseTransitionProperty($property) !== null;
    }

    private function isTransitionShorthand(string $property): bool
    {
        return $this->baseTransitionProperty($property) === 'transition';
    }

    private function isTransitionLonghand(string $property): bool
    {
        $base = $this->baseTransitionProperty($property);

        return $base !== null && in_array($base, self::TRANSITION_LONGHANDS, true);
    }

    private function transitionPrefixForProperty(string $property): ?string
    {
        foreach (['-webkit-', '-moz-'] as $prefix) {
            if (str_starts_with($property, $prefix . 'transition')) {
                return $this->baseTransitionProperty($property) === null ? null : $prefix;
            }
        }

        if (str_starts_with($property, 'transition')) {
            return $this->baseTransitionProperty($property) === null ? null : '';
        }

        return null;
    }

    private function baseTransitionProperty(string $property): ?string
    {
        foreach (['-webkit-', '-moz-'] as $prefix) {
            if (str_starts_with($property, $prefix)) {
                $property = substr($property, strlen($prefix));
                break;
            }
        }

        return $property === 'transition' || in_array($property, self::TRANSITION_LONGHANDS, true)
            ? $property
            : null;
    }

    private function transitionPropertyName(string $prefix, string $base): string
    {
        return $prefix . $base;
    }

    private function isTransitionTimeToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:ms|s)$/i', trim($token)) === 1;
    }

    private function canonicalTransitionTime(string $token): string
    {
        return $this->isZeroTransitionTime($token) ? '0s' : trim($token);
    }

    private function isZeroTransitionTime(string $token): bool
    {
        return preg_match('/^[+-]?(?:0+|0*\.0+)(?:ms|s)$/i', trim($token)) === 1;
    }

    private function isTransitionTimingToken(string $token): bool
    {
        $lower = strtolower(trim($token));

        return in_array($lower, self::TRANSITION_TIMING_FUNCTIONS, true)
            || preg_match('/^(?:cubic-bezier|steps|linear)\(/', $lower) === 1;
    }

    private function isDefaultTransitionTiming(string $timing): bool
    {
        return strcasecmp(trim($timing), 'ease') === 0;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getBackgroundProperty(array $entries, string $property): ?array
    {
        if ($property !== 'background' && !in_array($property, self::BACKGROUND_LONGHANDS, true)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'background') {
                $components = $this->backgroundComponentsFromShorthand($entry['value'], $entry['important']);
                continue;
            }

            if (in_array($entry['property'], self::BACKGROUND_LONGHANDS, true)) {
                $this->applyBackgroundLonghand($components, $entry['property'], $entry['value'], $entry['important']);
            }
        }

        if ($property !== 'background') {
            if ($property === 'background-position') {
                return $this->getBackgroundPosition($components);
            }

            return $components[$property] ?? null;
        }

        if (!isset($components['background'])) {
            return null;
        }
        $important = $components['background']['important'];
        foreach ($components as $component) {
            if ($component['important'] !== $important) {
                return null;
            }
        }

        $value = $this->composeBackgroundValue($components);
        if ($value === null) {
            return null;
        }

        return ['value' => $value, 'important' => $important];
    }

    /**
     * @return array<string, array{value:string, important:bool}>
     */
    private function backgroundComponentsFromShorthand(string $value, bool $important): array
    {
        $layers = $this->parseBackgroundLayers($value);
        $components = [
            'background' => ['value' => $value, 'important' => $important],
        ];
        foreach (self::BACKGROUND_LONGHANDS as $longhand) {
            $longhandValue = $this->backgroundLonghandFromLayers($layers, $longhand);
            if ($longhandValue !== null) {
                $components[$longhand] = ['value' => $longhandValue, 'important' => $important];
            }
        }

        return $components;
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     */
    private function applyBackgroundLonghand(array &$components, string $property, string $value, bool $important): void
    {
        $components[$property] = [
            'value' => $value,
            'important' => $important,
        ];

        if ($property !== 'background-position') {
            return;
        }

        [$x, $y] = $this->splitBackgroundPositionList($value);
        if ($x !== null) {
            $components['background-position-x'] = ['value' => $x, 'important' => $important];
        }
        if ($y !== null) {
            $components['background-position-y'] = ['value' => $y, 'important' => $important];
        }
    }

    /**
     * @return list<array{
     *     raw:string,
     *     image:?string,
     *     color:?string,
     *     position:?string,
     *     positionX:?string,
     *     positionY:?string,
     *     size:?string,
     *     repeat:?string,
     *     attachment:?string,
     *     origin:?string,
     *     clip:?string
     * }>
     */
    private function parseBackgroundLayers(string $value): array
    {
        return array_map(
            fn (string $layer): array => $this->parseBackgroundLayer($layer),
            $this->splitTopLevel($value, ',')
        );
    }

    /**
     * @return array{
     *     raw:string,
     *     image:?string,
     *     color:?string,
     *     position:?string,
     *     positionX:?string,
     *     positionY:?string,
     *     size:?string,
     *     repeat:?string,
     *     attachment:?string,
     *     origin:?string,
     *     clip:?string
     * }
     */
    private function parseBackgroundLayer(string $layer): array
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        $parsed = [
            'raw' => trim($layer),
            'image' => null,
            'color' => null,
            'position' => null,
            'positionX' => null,
            'positionY' => null,
            'size' => null,
            'repeat' => null,
            'attachment' => null,
            'origin' => null,
            'clip' => null,
        ];
        $positionTokens = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $lower = strtolower($token);
            if ($token === '/') {
                $sizeTokens = [];
                for ($i++; $i < count($tokens); $i++) {
                    $sizeTokens[] = $tokens[$i];
                }
                $parsed['size'] = implode(' ', $sizeTokens);
                break;
            }
            if (str_contains($token, '/') && $token !== '/' && $parsed['size'] === null) {
                [$before, $after] = array_pad(explode('/', $token, 2), 2, '');
                if ($before !== '') {
                    $positionTokens[] = $before;
                }
                if ($after !== '') {
                    $sizeTokens = [$after];
                    for ($i++; $i < count($tokens); $i++) {
                        $sizeTokens[] = $tokens[$i];
                    }
                    $parsed['size'] = implode(' ', $sizeTokens);
                    break;
                }
            } elseif ($this->isBackgroundImageToken($token)) {
                $parsed['image'] = $token;
            } elseif ($this->isBackgroundRepeatToken($lower)) {
                $parsed['repeat'] = $this->consumeBackgroundRepeat($tokens, $i);
            } elseif ($this->isBackgroundAttachmentToken($lower)) {
                $parsed['attachment'] = $lower;
            } elseif ($this->isBackgroundBoxToken($lower)) {
                if ($parsed['origin'] === null) {
                    $parsed['origin'] = $lower;
                } elseif ($parsed['clip'] === null) {
                    $parsed['clip'] = $lower;
                } else {
                    $positionTokens[] = $token;
                }
            } elseif ($this->isBackgroundClipKeyword($lower)) {
                $parsed['clip'] = $lower;
            } elseif ($this->isBackgroundColorToken($token)) {
                $parsed['color'] = $token;
            } else {
                $positionTokens[] = $token;
            }
        }
        if ($parsed['clip'] === null && $parsed['origin'] !== null) {
            $parsed['clip'] = $parsed['origin'];
        }

        if ($positionTokens !== []) {
            $parsed['position'] = implode(' ', $positionTokens);
            $parsed['positionX'] = $positionTokens[0] ?? null;
            $parsed['positionY'] = count($positionTokens) > 1 ? implode(' ', array_slice($positionTokens, 1)) : null;
        }

        return $parsed;
    }

    /**
     * @param list<array{raw:string,image:?string,color:?string,position:?string,positionX:?string,positionY:?string,size:?string,repeat:?string,attachment:?string,origin:?string,clip:?string}> $layers
     */
    private function backgroundLonghandFromLayers(array $layers, string $property): ?string
    {
        if ($property === 'background-color') {
            for ($i = count($layers) - 1; $i >= 0; $i--) {
                if ($layers[$i]['color'] !== null) {
                    return $layers[$i]['color'];
                }
            }

            return null;
        }

        $values = [];
        foreach ($layers as $layer) {
            $value = match ($property) {
                'background-image' => $layer['image'],
                'background-position' => $layer['position'],
                'background-position-x' => $layer['positionX'],
                'background-position-y' => $layer['positionY'],
                'background-size' => $layer['size'],
                'background-repeat' => $layer['repeat'],
                'background-attachment' => $layer['attachment'] ?? 'scroll',
                'background-origin' => $layer['origin'] ?? 'padding-box',
                'background-clip' => $layer['clip'] ?? 'border-box',
                default => null,
            };
            if ($value === null) {
                return null;
            }
            $values[] = $value;
        }

        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     */
    private function getBackgroundPosition(array $components): ?array
    {
        $x = $components['background-position-x'] ?? null;
        $y = $components['background-position-y'] ?? null;
        if ($x === null && $y === null) {
            return $components['background-position'] ?? null;
        }

        $important = ($x ?? $y)['important'];
        if ($x !== null && $x['important'] !== $important) {
            return null;
        }
        if ($y !== null && $y['important'] !== $important) {
            return null;
        }

        return [
            'value' => trim(($x['value'] ?? '0') . ' ' . ($y['value'] ?? '0')),
            'important' => $important,
        ];
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     */
    private function composeBackgroundValue(array $components): ?string
    {
        $layers = $this->parseBackgroundLayers($components['background']['value']);
        $layerCount = max(1, count($layers));
        if (!$this->backgroundComponentLayerCountsFit($components, $layerCount)) {
            return null;
        }

        $images = $this->componentList($components['background-image']['value'] ?? null, $layerCount);
        $positions = $this->componentList($components['background-position']['value'] ?? null, $layerCount);
        $positionX = $this->componentList($components['background-position-x']['value'] ?? null, $layerCount);
        $positionY = $this->componentList($components['background-position-y']['value'] ?? null, $layerCount);
        $sizes = $this->componentList($components['background-size']['value'] ?? null, $layerCount);
        $repeats = $this->componentList($components['background-repeat']['value'] ?? null, $layerCount);
        $attachments = $this->componentList($components['background-attachment']['value'] ?? null, $layerCount);
        $origins = $this->componentList($components['background-origin']['value'] ?? null, $layerCount);
        $clips = $this->componentList($components['background-clip']['value'] ?? null, $layerCount);
        $color = $components['background-color']['value'] ?? null;
        $result = [];

        for ($i = 0; $i < $layerCount; $i++) {
            $layer = [];
            if (($color !== null && $i === $layerCount - 1) && (($images[$i] ?? null) === null)) {
                $layer[] = $color;
            }
            if (($images[$i] ?? null) !== null) {
                $layer[] = $images[$i];
            }
            $position = null;
            if (($positionX[$i] ?? null) !== null || ($positionY[$i] ?? null) !== null) {
                $position = trim(($positionX[$i] ?? '0') . ' ' . ($positionY[$i] ?? '0'));
            } else {
                $position = $positions[$i] ?? null;
            }
            $size = $sizes[$i] ?? null;
            if ($position === null && $size !== null && !$this->isDefaultBackgroundSize($size)) {
                $position = '0 0';
            }
            if ($position !== null) {
                $layer[] = $position;
            }
            if ($size !== null && !$this->isDefaultBackgroundSize($size)) {
                $layer[] = '/';
                $layer[] = $size;
            }
            if (($repeats[$i] ?? null) !== null) {
                $layer[] = $this->compressBackgroundRepeat($repeats[$i]);
            }
            if (($attachments[$i] ?? null) !== null && !$this->isDefaultBackgroundAttachment($attachments[$i])) {
                $layer[] = strtolower($attachments[$i]);
            }
            $origin = $origins[$i] ?? null;
            $clip = $clips[$i] ?? null;
            if ($origin !== null || $clip !== null) {
                $origin = strtolower($origin ?? 'padding-box');
                $clip = strtolower($clip ?? 'border-box');
                $outputOrigin = !$this->isDefaultBackgroundOrigin($origin)
                    || (!$this->isDefaultBackgroundClip($clip) && $this->isBackgroundBoxToken($clip));
                if ($outputOrigin) {
                    $layer[] = $origin;
                }
                if (($outputOrigin && $clip !== $origin) || !$this->isDefaultBackgroundClip($clip)) {
                    $layer[] = $clip;
                }
            }
            if ($color !== null && $i === $layerCount - 1 && ($images[$i] ?? null) !== null) {
                array_unshift($layer, $color);
            }
            $result[] = implode(' ', array_values(array_filter($layer, static fn (string $part): bool => $part !== '')));
        }

        return implode(', ', $result);
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     */
    private function backgroundComponentLayerCountsFit(array $components, int $layerCount): bool
    {
        foreach ([
            'background-image',
            'background-position',
            'background-position-x',
            'background-position-y',
            'background-size',
            'background-repeat',
            'background-attachment',
            'background-origin',
            'background-clip',
        ] as $property) {
            if (!isset($components[$property])) {
                continue;
            }

            if (count($this->splitTopLevel($components[$property]['value'], ',')) > $layerCount) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string|null>
     */
    private function componentList(?string $value, int $count): array
    {
        if ($value === null) {
            return array_fill(0, $count, null);
        }

        $parts = array_map(
            static fn (string $part): string => trim($part),
            $this->splitTopLevel($value, ',')
        );
        if ($parts === []) {
            return array_fill(0, $count, null);
        }
        while (count($parts) < $count) {
            $parts[] = $parts[array_key_last($parts)];
        }

        return array_slice($parts, 0, $count);
    }

    private function isBackgroundImageToken(string $token): bool
    {
        return preg_match('/^(?:url|[-_a-zA-Z][-_a-zA-Z0-9]*-gradient|image|cross-fade|image-set)\(/i', $token) === 1;
    }

    private function isBackgroundColorToken(string $token): bool
    {
        return preg_match('/^(?:#[0-9a-fA-F]{3,8}|(?:rgb|rgba|hsl|hsla|color)\(|[a-zA-Z]+)$/', $token) === 1
            && !$this->isBackgroundRepeatToken(strtolower($token))
            && !in_array(strtolower($token), ['left', 'right', 'top', 'bottom', 'center', 'scroll', 'fixed', 'local', 'border-box', 'padding-box', 'content-box', 'border', 'text', 'cover', 'contain', 'none'], true);
    }

    private function isBackgroundRepeatToken(string $token): bool
    {
        return in_array($token, ['repeat', 'no-repeat', 'space', 'round', 'repeat-x', 'repeat-y'], true);
    }

    private function isBackgroundAttachmentToken(string $token): bool
    {
        return in_array($token, ['scroll', 'fixed', 'local'], true);
    }

    private function isBackgroundBoxToken(string $token): bool
    {
        return in_array($token, ['border-box', 'padding-box', 'content-box'], true);
    }

    private function isBackgroundClipKeyword(string $token): bool
    {
        return in_array($token, ['border', 'text'], true);
    }

    /**
     * @param list<string> $tokens
     */
    private function consumeBackgroundRepeat(array $tokens, int &$index): string
    {
        $first = strtolower($tokens[$index]);
        if ($first === 'repeat-x' || $first === 'repeat-y') {
            return $first;
        }
        $second = strtolower($tokens[$index + 1] ?? '');
        if (in_array($second, ['repeat', 'no-repeat', 'space', 'round'], true)) {
            $index++;

            return $first . ' ' . $second;
        }

        return $first;
    }

    private function compressBackgroundRepeat(string $repeat): string
    {
        return match (strtolower($repeat)) {
            'repeat no-repeat' => 'repeat-x',
            'no-repeat repeat' => 'repeat-y',
            default => $repeat,
        };
    }

    private function isDefaultBackgroundSize(string $size): bool
    {
        return in_array(strtolower(trim($size)), ['auto', 'auto auto'], true);
    }

    private function isDefaultBackgroundAttachment(string $attachment): bool
    {
        return strtolower(trim($attachment)) === 'scroll';
    }

    private function isDefaultBackgroundOrigin(string $origin): bool
    {
        return strtolower(trim($origin)) === 'padding-box';
    }

    private function isDefaultBackgroundClip(string $clip): bool
    {
        return strtolower(trim($clip)) === 'border-box';
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitBackgroundPositionList(string $value): array
    {
        $xs = [];
        $ys = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            [$x, $y] = $this->splitBackgroundPosition($layer);
            if ($x === null) {
                return [null, null];
            }
            $xs[] = $x;
            $ys[] = $y ?? '0';
        }

        return [implode(', ', $xs), implode(', ', $ys)];
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitBackgroundPosition(string $value): array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        $count = count($tokens);
        if ($count === 0) {
            return [null, null];
        }
        if ($count === 1) {
            return [$tokens[0], '0'];
        }
        if ($count === 2) {
            return [$tokens[0], $tokens[1]];
        }

        for ($i = 1; $i < $count; $i++) {
            if (in_array(strtolower($tokens[$i]), ['top', 'bottom'], true)) {
                return [
                    implode(' ', array_slice($tokens, 0, $i)),
                    implode(' ', array_slice($tokens, $i)),
                ];
            }
        }

        return [$tokens[0], implode(' ', array_slice($tokens, 1))];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getListStyleProperty(array $entries, string $property): ?array
    {
        if (!$this->isListStyleProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'list-style') {
                $parsed = $this->parseListStyleComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::LIST_STYLE_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isListStyleLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeListStyleLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'list-style') {
            return $components[$property] ?? null;
        }

        foreach (self::LIST_STYLE_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeListStyleComponents([
                'list-style-type' => $components['list-style-type']['value'],
                'list-style-image' => $components['list-style-image']['value'],
                'list-style-position' => $components['list-style-position']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setListStyleLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isListStyleLonghand($property)) {
            return null;
        }

        $value = $this->normalizeListStyleLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'list-style') {
                continue;
            }

            $components = $this->parseListStyleComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'list-style',
                'value' => $this->serializeListStyleComponents($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @return array{list-style-type:string, list-style-image:string, list-style-position:string}|null
     */
    private function parseListStyleComponents(string $value): ?array
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
                $image = $this->normalizeListStyleImageValue($token);
                continue;
            }
            if ($type !== null) {
                return null;
            }
            $type = $this->normalizeListStyleTypeValue($token);
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
            if (strtolower($type) !== 'none' && $image === null) {
                $image = 'none';
            }
        }

        return [
            'list-style-type' => $type ?? 'disc',
            'list-style-image' => $image ?? 'none',
            'list-style-position' => $position ?? 'outside',
        ];
    }

    /**
     * @param array{list-style-type:string, list-style-image:string, list-style-position:string} $components
     */
    private function serializeListStyleComponents(array $components): string
    {
        $type = strtolower($components['list-style-type']) === 'none' ? 'none' : $components['list-style-type'];
        $image = $components['list-style-image'];
        $position = strtolower($components['list-style-position']);
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

    private function normalizeListStyleLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'list-style-type' => $this->normalizeListStyleTypeValue($value),
            'list-style-image' => $this->normalizeListStyleImageValue($value),
            'list-style-position' => strtolower(trim($value)),
            default => trim($value),
        };
    }

    private function normalizeListStyleTypeValue(string $value): string
    {
        $value = trim($value);
        if ($this->isQuotedStringToken($value)) {
            return $this->normalizeCssStringToken($value);
        }

        return strtolower($value) === 'none' ? 'none' : $value;
    }

    private function normalizeListStyleImageValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^url\(/i', $value) === 1) {
            return $this->normalizeCssUrlToken($value);
        }

        return strtolower($value) === 'none' ? 'none' : $value;
    }

    private function isListStyleProperty(string $property): bool
    {
        return $property === 'list-style' || $this->isListStyleLonghand($property);
    }

    private function isListStyleLonghand(string $property): bool
    {
        return in_array($property, self::LIST_STYLE_LONGHANDS, true);
    }

    private function isListStyleImageToken(string $token): bool
    {
        return preg_match('/^(?:url|(?:-(?:webkit|o)-)?(?:linear|radial|conic)-gradient|image-set|cross-fade|paint)\(/i', trim($token)) === 1;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getTextDecorationProperty(array $entries, string $property): ?array
    {
        if (!$this->isTextDecorationProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'text-decoration') {
                $parsed = $this->parseTextDecorationComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::TEXT_DECORATION_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isTextDecorationLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeTextDecorationLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'text-decoration') {
            return $components[$property] ?? null;
        }

        foreach (self::TEXT_DECORATION_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeTextDecorationComponents([
                'text-decoration-line' => $components['text-decoration-line']['value'],
                'text-decoration-thickness' => $components['text-decoration-thickness']['value'],
                'text-decoration-style' => $components['text-decoration-style']['value'],
                'text-decoration-color' => $components['text-decoration-color']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setTextDecorationLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isTextDecorationLonghand($property)) {
            return null;
        }

        $value = $this->normalizeTextDecorationLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'text-decoration') {
                continue;
            }

            $components = $this->parseTextDecorationComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'text-decoration',
                'value' => $this->serializeTextDecorationComponents($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeTextDecorationLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'text-decoration') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseTextDecorationComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::TEXT_DECORATION_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{
     *     text-decoration-line:string,
     *     text-decoration-thickness:string,
     *     text-decoration-style:string,
     *     text-decoration-color:string
     * }|null
     */
    private function parseTextDecorationComponents(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return null;
        }

        $lineTokens = [];
        $exclusiveLine = null;
        $thickness = null;
        $style = null;
        $color = null;

        foreach ($tokens as $token) {
            $lower = strtolower(trim($token));
            if (in_array($lower, self::TEXT_DECORATION_EXCLUSIVE_LINES, true)) {
                if ($lineTokens !== [] || $exclusiveLine !== null) {
                    return null;
                }
                $exclusiveLine = $lower;
                continue;
            }
            if (in_array($lower, self::TEXT_DECORATION_LINES, true)) {
                if ($exclusiveLine !== null || in_array($lower, $lineTokens, true)) {
                    return null;
                }
                $lineTokens[] = $lower;
                continue;
            }
            if ($thickness === null && $this->isTextDecorationThicknessToken($token)) {
                $thickness = $this->normalizeTextDecorationThicknessValue($token);
                continue;
            }
            if ($style === null && in_array($lower, self::TEXT_DECORATION_STYLES, true)) {
                $style = $lower;
                continue;
            }
            if ($color === null) {
                $color = $this->normalizeTextDecorationColorValue($token);
                continue;
            }

            return null;
        }

        $line = $exclusiveLine ?? $this->normalizeTextDecorationLineValue(implode(' ', $lineTokens));

        return [
            'text-decoration-line' => $line === '' ? 'none' : $line,
            'text-decoration-thickness' => $thickness ?? 'auto',
            'text-decoration-style' => $style ?? 'solid',
            'text-decoration-color' => $color ?? 'currentColor',
        ];
    }

    /**
     * @param array{
     *     text-decoration-line:string,
     *     text-decoration-thickness:string,
     *     text-decoration-style:string,
     *     text-decoration-color:string
     * } $components
     */
    private function serializeTextDecorationComponents(array $components): string
    {
        $line = $this->normalizeTextDecorationLineValue($components['text-decoration-line']);
        if ($line === 'none') {
            return 'none';
        }

        $parts = [$line];
        $thickness = $this->normalizeTextDecorationThicknessValue($components['text-decoration-thickness']);
        $style = strtolower(trim($components['text-decoration-style']));
        $color = $this->normalizeTextDecorationColorValue($components['text-decoration-color']);

        if (strcasecmp($thickness, 'auto') !== 0) {
            $parts[] = $thickness;
        }
        if ($style !== 'solid') {
            $parts[] = $style;
        }
        if (strcasecmp($color, 'currentColor') !== 0) {
            $parts[] = $color;
        }

        return implode(' ', $parts);
    }

    private function normalizeTextDecorationLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'text-decoration-line' => $this->normalizeTextDecorationLineValue($value),
            'text-decoration-thickness' => $this->normalizeTextDecorationThicknessValue($value),
            'text-decoration-style' => strtolower(trim($value)),
            'text-decoration-color' => $this->normalizeTextDecorationColorValue($value),
            default => trim($value),
        };
    }

    private function normalizeTextDecorationLineValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return 'none';
        }

        $lines = [];
        foreach ($tokens as $token) {
            $lower = strtolower(trim($token));
            if (in_array($lower, self::TEXT_DECORATION_EXCLUSIVE_LINES, true)) {
                return $lower;
            }
            if (in_array($lower, self::TEXT_DECORATION_LINES, true) && !in_array($lower, $lines, true)) {
                $lines[] = $lower;
            }
        }

        $ordered = [];
        foreach (self::TEXT_DECORATION_LINES as $line) {
            if (in_array($line, $lines, true)) {
                $ordered[] = $line;
            }
        }

        return $ordered === [] ? 'none' : implode(' ', $ordered);
    }

    private function normalizeTextDecorationThicknessValue(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeTextDecorationColorValue(string $value): string
    {
        $value = trim($value);

        return strcasecmp($value, 'currentcolor') === 0 ? 'currentColor' : $value;
    }

    private function isTextDecorationProperty(string $property): bool
    {
        return $property === 'text-decoration' || $this->isTextDecorationLonghand($property);
    }

    private function isTextDecorationLonghand(string $property): bool
    {
        return in_array($property, self::TEXT_DECORATION_LONGHANDS, true);
    }

    private function isTextDecorationThicknessToken(string $token): bool
    {
        $token = trim($token);
        $lower = strtolower($token);
        if ($lower === 'auto' || $lower === 'from-font') {
            return true;
        }

        return preg_match('/^(?:[+-]?(?:\d+|\d*\.\d+)(?:[a-z%]+)?|(?:calc|clamp|min|max|var)\()/i', $token) === 1;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getTextEmphasisProperty(array $entries, string $property): ?array
    {
        $prefix = $this->textEmphasisPrefixForProperty($property);
        $base = $this->baseTextEmphasisProperty($property);
        if ($prefix === null || $base === null) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            $entryPrefix = $this->textEmphasisPrefixForProperty($entry['property']);
            $entryBase = $this->baseTextEmphasisProperty($entry['property']);
            if ($entryPrefix !== $prefix || $entryBase === null) {
                continue;
            }

            if ($entryBase === 'text-emphasis') {
                $parsed = $this->parseTextEmphasisComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::TEXT_EMPHASIS_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            $components[$entryBase] = [
                'value' => $this->normalizeTextEmphasisLonghandValue($entryBase, $entry['value']),
                'important' => $entry['important'],
            ];
        }

        if ($base !== 'text-emphasis') {
            return $components[$base] ?? null;
        }

        foreach (self::TEXT_EMPHASIS_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeTextEmphasisComponents([
                'text-emphasis-style' => $components['text-emphasis-style']['value'],
                'text-emphasis-color' => $components['text-emphasis-color']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setTextEmphasisLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        $prefix = $this->textEmphasisPrefixForProperty($property);
        $base = $this->baseTextEmphasisProperty($property);
        if ($prefix === null || $base === null || !in_array($base, self::TEXT_EMPHASIS_LONGHANDS, true)) {
            return null;
        }

        $value = $this->normalizeTextEmphasisLonghandValue($base, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            $entryPrefix = $this->textEmphasisPrefixForProperty($entries[$index]['property']);
            $entryBase = $this->baseTextEmphasisProperty($entries[$index]['property']);
            if ($entryPrefix !== $prefix || $entryBase !== 'text-emphasis') {
                continue;
            }

            $components = $this->parseTextEmphasisComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$base] = $value;
            $entries[$index] = [
                'property' => $this->textEmphasisProperty($prefix, 'text-emphasis'),
                'value' => $this->serializeTextEmphasisComponents($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeTextEmphasisLonghand(array $entries, string $property): string
    {
        $prefix = $this->textEmphasisPrefixForProperty($property);
        $base = $this->baseTextEmphasisProperty($property);
        if ($prefix === null || $base === null || !in_array($base, self::TEXT_EMPHASIS_LONGHANDS, true)) {
            return $this->serializeEntries($entries);
        }

        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            $entryPrefix = $this->textEmphasisPrefixForProperty($entry['property']);
            $entryBase = $this->baseTextEmphasisProperty($entry['property']);
            if ($entryPrefix !== $prefix || $entryBase !== 'text-emphasis') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseTextEmphasisComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::TEXT_EMPHASIS_LONGHANDS as $longhand) {
                if ($longhand === $base) {
                    continue;
                }

                $result[] = [
                    'property' => $this->textEmphasisProperty($prefix, $longhand),
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{text-emphasis-style:string, text-emphasis-color:string}|null
     */
    private function parseTextEmphasisComponents(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return null;
        }

        $styleTokens = [];
        $color = null;
        foreach ($tokens as $token) {
            if ($this->isTextEmphasisStyleToken($token)) {
                $styleTokens[] = $token;
                continue;
            }

            if ($color !== null) {
                return null;
            }
            $color = $this->normalizeTextEmphasisColorValue($token);
        }

        $style = $styleTokens === [] ? 'none' : $this->parseTextEmphasisStyleTokens($styleTokens);
        if ($style === null) {
            return null;
        }

        return [
            'text-emphasis-style' => $style,
            'text-emphasis-color' => $color ?? 'currentColor',
        ];
    }

    /**
     * @param list<string> $tokens
     */
    private function parseTextEmphasisStyleTokens(array $tokens): ?string
    {
        if (count($tokens) === 1 && $this->isQuotedStringToken($tokens[0])) {
            return $this->normalizeCssStringToken($tokens[0]);
        }

        $fill = null;
        $shape = null;
        foreach ($tokens as $token) {
            if ($this->isQuotedStringToken($token)) {
                return null;
            }

            $lower = strtolower(trim($token));
            if ($lower === 'none') {
                return count($tokens) === 1 ? 'none' : null;
            }
            if (in_array($lower, self::TEXT_EMPHASIS_FILLS, true)) {
                if ($fill !== null) {
                    return null;
                }
                $fill = $lower;
                continue;
            }
            if (in_array($lower, self::TEXT_EMPHASIS_SHAPES, true)) {
                if ($shape !== null) {
                    return null;
                }
                $shape = $lower;
                continue;
            }

            return null;
        }

        if ($fill === null && $shape === null) {
            return null;
        }
        if ($shape === null) {
            return $fill;
        }

        return $fill === 'open' ? "open {$shape}" : $shape;
    }

    /**
     * @param array{text-emphasis-style:string, text-emphasis-color:string} $components
     */
    private function serializeTextEmphasisComponents(array $components): string
    {
        $style = $this->normalizeTextEmphasisStyleValue($components['text-emphasis-style']);
        $color = $this->normalizeTextEmphasisColorValue($components['text-emphasis-color']);
        if (strcasecmp($style, 'none') === 0) {
            return 'none';
        }

        return strcasecmp($color, 'currentColor') === 0 ? $style : "{$style} {$color}";
    }

    private function normalizeTextEmphasisLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'text-emphasis-style' => $this->normalizeTextEmphasisStyleValue($value),
            'text-emphasis-color' => $this->normalizeTextEmphasisColorValue($value),
            default => trim($value),
        };
    }

    private function normalizeTextEmphasisStyleValue(string $value): string
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return trim($value);
        }

        return $this->parseTextEmphasisStyleTokens($tokens) ?? trim($value);
    }

    private function normalizeTextEmphasisColorValue(string $value): string
    {
        $value = trim($value);

        return strcasecmp($value, 'currentcolor') === 0 ? 'currentColor' : $value;
    }

    private function isTextEmphasisProperty(string $property): bool
    {
        return $this->baseTextEmphasisProperty($property) !== null;
    }

    private function isTextEmphasisLonghand(string $property): bool
    {
        $base = $this->baseTextEmphasisProperty($property);

        return $base !== null && in_array($base, self::TEXT_EMPHASIS_LONGHANDS, true);
    }

    private function isTextEmphasisStyleToken(string $token): bool
    {
        if ($this->isQuotedStringToken($token)) {
            return true;
        }

        $lower = strtolower(trim($token));

        return $lower === 'none'
            || in_array($lower, self::TEXT_EMPHASIS_FILLS, true)
            || in_array($lower, self::TEXT_EMPHASIS_SHAPES, true);
    }

    private function textEmphasisPrefixForProperty(string $property): ?string
    {
        if ($property === '-webkit-text-emphasis' || str_starts_with($property, '-webkit-text-emphasis-')) {
            return $this->baseTextEmphasisProperty($property) === null ? null : '-webkit-';
        }
        if ($property === 'text-emphasis' || str_starts_with($property, 'text-emphasis-')) {
            return $this->baseTextEmphasisProperty($property) === null ? null : '';
        }

        return null;
    }

    private function baseTextEmphasisProperty(string $property): ?string
    {
        if ($property === '-webkit-text-emphasis' || str_starts_with($property, '-webkit-text-emphasis-')) {
            $property = substr($property, strlen('-webkit-'));
        }

        return match ($property) {
            'text-emphasis',
            'text-emphasis-style',
            'text-emphasis-color' => $property,
            default => null,
        };
    }

    private function textEmphasisProperty(string $prefix, string $base): string
    {
        return "{$prefix}{$base}";
    }

    /**
     * @return list<string>|null
     */
    private function textEmphasisShorthandLonghands(string $property): ?array
    {
        $prefix = $this->textEmphasisPrefixForProperty($property);
        $base = $this->baseTextEmphasisProperty($property);
        if ($prefix === null || $base !== 'text-emphasis') {
            return null;
        }

        return array_map(
            fn (string $longhand): string => $this->textEmphasisProperty($prefix, $longhand),
            self::TEXT_EMPHASIS_LONGHANDS
        );
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getCaretProperty(array $entries, string $property): ?array
    {
        if (!$this->isCaretProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'caret') {
                $parsed = $this->parseCaretComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::CARET_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isCaretLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeCaretLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'caret') {
            return $components[$property] ?? null;
        }

        foreach (self::CARET_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeCaretComponents([
                'caret-color' => $components['caret-color']['value'],
                'caret-shape' => $components['caret-shape']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setCaretLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isCaretLonghand($property)) {
            return null;
        }

        $value = $this->normalizeCaretLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'caret') {
                continue;
            }

            $components = $this->parseCaretComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'caret',
                'value' => $this->serializeCaretComponents($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeCaretLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'caret') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseCaretComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::CARET_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{caret-color:string, caret-shape:string}|null
     */
    private function parseCaretComponents(string $value): ?array
    {
        $color = null;
        $shape = null;
        $auto = 0;

        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $lower = strtolower(trim($token));
            if ($lower === 'auto') {
                $auto++;
                continue;
            }

            if ($this->isCaretShapeToken($token)) {
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
                $color = $this->normalizeCaretColorValue($token);
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
            'caret-color' => $color ?? 'auto',
            'caret-shape' => $shape ?? 'auto',
        ];
    }

    /**
     * @param array{caret-color:string, caret-shape:string} $components
     */
    private function serializeCaretComponents(array $components): string
    {
        $color = $this->normalizeCaretColorValue($components['caret-color']);
        $shape = $this->normalizeCaretShapeValue($components['caret-shape']);
        $parts = [];

        if (strcasecmp($color, 'auto') !== 0) {
            $parts[] = $color;
        }
        if (strcasecmp($shape, 'auto') !== 0) {
            $parts[] = $shape;
        }

        return $parts === [] ? 'auto' : implode(' ', $parts);
    }

    private function normalizeCaretLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'caret-color' => $this->normalizeCaretColorValue($value),
            'caret-shape' => $this->normalizeCaretShapeValue($value),
            default => trim($value),
        };
    }

    private function normalizeCaretColorValue(string $value): string
    {
        $value = trim($value);

        return strcasecmp($value, 'auto') === 0 ? 'auto' : $value;
    }

    private function normalizeCaretShapeValue(string $value): string
    {
        $value = trim($value);
        $lower = strtolower($value);

        return in_array($lower, self::CARET_SHAPES, true) ? $lower : $value;
    }

    private function isCaretProperty(string $property): bool
    {
        return $property === 'caret' || $this->isCaretLonghand($property);
    }

    private function isCaretLonghand(string $property): bool
    {
        return in_array($property, self::CARET_LONGHANDS, true);
    }

    private function isCaretShapeToken(string $token): bool
    {
        return in_array(strtolower(trim($token)), ['bar', 'block', 'underscore'], true);
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
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getFontProperty(array $entries, string $property): ?array
    {
        if (!$this->isFontProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'font') {
                $parsed = $this->parseFontComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::FONT_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isFontLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeFontLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'font') {
            return $components[$property] ?? null;
        }

        foreach (self::FONT_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeFontComponents([
                'font-family' => $components['font-family']['value'],
                'font-size' => $components['font-size']['value'],
                'font-style' => $components['font-style']['value'],
                'font-weight' => $components['font-weight']['value'],
                'font-stretch' => $components['font-stretch']['value'],
                'line-height' => $components['line-height']['value'],
                'font-variant-caps' => $components['font-variant-caps']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setFontLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isFontLonghand($property)) {
            return null;
        }

        $value = $this->normalizeFontLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'font') {
                continue;
            }

            $components = $this->parseFontComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'font',
                'value' => $this->serializeFontComponents($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeFontLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'font') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseFontComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::FONT_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{
     *     font-family:string,
     *     font-size:string,
     *     font-style:string,
     *     font-weight:string,
     *     font-stretch:string,
     *     line-height:string,
     *     font-variant-caps:string
     * }|null
     */
    private function parseFontComponents(string $value): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel($value);
        if ($tokens === []) {
            return null;
        }

        $components = [
            'font-family' => null,
            'font-size' => null,
            'font-style' => 'normal',
            'font-weight' => 'normal',
            'font-stretch' => 'normal',
            'line-height' => 'normal',
            'font-variant-caps' => 'normal',
        ];
        $preSizeCount = 0;
        $familyStart = null;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            $slash = $this->findTopLevelCharacter($token, '/');
            $fontSizeToken = $slash === null ? $token : substr($token, 0, $slash);
            if ($fontSizeToken !== '' && $this->isFontSizeToken($fontSizeToken)) {
                $components['font-size'] = $this->normalizeFontLonghandValue('font-size', $fontSizeToken);
                if ($slash !== null) {
                    $lineHeight = substr($token, $slash + 1);
                    if ($lineHeight === '') {
                        $index++;
                        $lineHeight = $tokens[$index] ?? '';
                    }
                    if ($lineHeight === '') {
                        return null;
                    }
                    $components['line-height'] = $this->normalizeFontLonghandValue('line-height', $lineHeight);
                } elseif (($tokens[$index + 1] ?? null) === '/') {
                    $index += 2;
                    if (!isset($tokens[$index])) {
                        return null;
                    }
                    $components['line-height'] = $this->normalizeFontLonghandValue('line-height', $tokens[$index]);
                } elseif (isset($tokens[$index + 1]) && str_starts_with($tokens[$index + 1], '/')) {
                    $lineHeight = substr($tokens[++$index], 1);
                    if ($lineHeight === '') {
                        $index++;
                        $lineHeight = $tokens[$index] ?? '';
                    }
                    if ($lineHeight === '') {
                        return null;
                    }
                    $components['line-height'] = $this->normalizeFontLonghandValue('line-height', $lineHeight);
                }
                $familyStart = $index + 1;
                break;
            }

            if (!$this->applyFontPreSizeToken($components, $tokens, $index, $preSizeCount)) {
                return null;
            }
            if ($preSizeCount > 4) {
                return null;
            }
        }

        if ($components['font-size'] === null || $familyStart === null || $familyStart >= count($tokens)) {
            return null;
        }

        $family = implode(' ', array_slice($tokens, $familyStart));
        $components['font-family'] = $this->normalizeFontFamilyList($family);
        if ($components['font-family'] === '') {
            return null;
        }

        return [
            'font-family' => $components['font-family'],
            'font-size' => $components['font-size'],
            'font-style' => $components['font-style'],
            'font-weight' => $components['font-weight'],
            'font-stretch' => $components['font-stretch'],
            'line-height' => $components['line-height'],
            'font-variant-caps' => $components['font-variant-caps'],
        ];
    }

    /**
     * @param array<string, string|null> $components
     * @param list<string> $tokens
     */
    private function applyFontPreSizeToken(array &$components, array $tokens, int &$index, int &$count): bool
    {
        $token = $tokens[$index];
        $lower = strtolower(trim($token));
        if ($lower === 'normal') {
            $count++;

            return true;
        }

        if ($components['font-style'] === 'normal' && in_array($lower, self::FONT_STYLES, true)) {
            $style = $lower;
            while (isset($tokens[$index + 1]) && $this->isFontStyleAngleToken($tokens[$index + 1])) {
                $style .= ' ' . trim($tokens[++$index]);
            }
            $components['font-style'] = $style;
            $count++;

            return true;
        }

        if ($components['font-weight'] === 'normal' && $this->isFontWeightToken($lower)) {
            $components['font-weight'] = $this->normalizeFontLonghandValue('font-weight', $token);
            $count++;

            return true;
        }

        if ($components['font-variant-caps'] === 'normal' && $lower === 'small-caps') {
            $components['font-variant-caps'] = $lower;
            $count++;

            return true;
        }

        if ($components['font-stretch'] === 'normal' && $this->isFontStretchToken($lower)) {
            $components['font-stretch'] = $this->normalizeFontLonghandValue('font-stretch', $token);
            $count++;

            return true;
        }

        return false;
    }

    /**
     * @param array{
     *     font-family:string,
     *     font-size:string,
     *     font-style:string,
     *     font-weight:string,
     *     font-stretch:string,
     *     line-height:string,
     *     font-variant-caps:string
     * } $components
     */
    private function serializeFontComponents(array $components): string
    {
        $parts = [];
        $style = $this->normalizeFontLonghandValue('font-style', $components['font-style']);
        $variant = $this->normalizeFontLonghandValue('font-variant-caps', $components['font-variant-caps']);
        $weight = $this->normalizeFontLonghandValue('font-weight', $components['font-weight']);
        $stretch = $this->normalizeFontLonghandValue('font-stretch', $components['font-stretch']);
        $size = $this->normalizeFontLonghandValue('font-size', $components['font-size']);
        $lineHeight = $this->normalizeFontLonghandValue('line-height', $components['line-height']);

        if ($style !== 'normal') {
            $parts[] = $style;
        }
        if ($variant !== 'normal') {
            $parts[] = $variant;
        }
        if ($weight !== 'normal') {
            $parts[] = $weight;
        }
        if ($stretch !== 'normal') {
            $parts[] = $stretch;
        }

        $sizePart = $size;
        if ($lineHeight !== 'normal') {
            $sizePart .= '/' . $lineHeight;
        }
        $parts[] = $sizePart;
        $parts[] = $this->normalizeFontFamilyList($components['font-family']);

        return implode(' ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function normalizeFontLonghandValue(string $property, string $value): string
    {
        $value = trim($value);

        return match ($property) {
            'font-family' => $this->normalizeFontFamilyList($value),
            'font-style' => $this->normalizeFontStyleValue($value),
            'font-weight' => $this->normalizeFontWeightValue($value),
            'font-stretch' => $this->normalizeFontStretchValue($value),
            'font-variant-caps' => strtolower($value),
            'font-size', 'line-height' => $this->normalizeFontNumericValue($value),
            default => $value,
        };
    }

    private function normalizeFontStyleValue(string $value): string
    {
        $parts = $this->splitWhitespaceTopLevel($value);
        if ($parts === []) {
            return '';
        }
        $parts[0] = strtolower($parts[0]);

        return implode(' ', $parts);
    }

    private function normalizeFontWeightValue(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))\.0+$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function normalizeFontStretchValue(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))\.0+%$/', $value, $matches) === 1) {
            return $matches[1] . '%';
        }

        return $value;
    }

    private function normalizeFontNumericValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))\.0+([a-z%]+)?$/i', $value, $matches) === 1) {
            return $matches[1] . strtolower($matches[2] ?? '');
        }

        return strtolower($value) === 'normal' ? 'normal' : $value;
    }

    private function normalizeFontFamilyList(string $value): string
    {
        return implode(', ', array_map(
            fn (string $family): string => $this->normalizeFontFamilyName($family),
            array_values(array_filter(
                array_map('trim', $this->splitTopLevel($value, ',')),
                static fn (string $family): bool => $family !== ''
            ))
        ));
    }

    private function normalizeFontFamilyName(string $family): string
    {
        $family = trim($family);
        if ($family === '') {
            return '';
        }

        if ($this->isQuotedStringToken($family)) {
            $family = substr($family, 1, -1);
        }
        $family = trim(preg_replace('/\s+/', ' ', $family) ?? $family);
        $lower = strtolower($family);
        if ($this->isGenericFontFamily($lower)) {
            return $lower;
        }
        if ($this->canSerializeUnquotedFontFamily($family)) {
            return $family;
        }

        return '"' . str_replace('"', '\\"', $family) . '"';
    }

    private function canSerializeUnquotedFontFamily(string $family): bool
    {
        $parts = preg_split('/\s+/', $family) ?: [];
        if ($parts === []) {
            return false;
        }

        foreach ($parts as $part) {
            if (preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $part) !== 1) {
                return false;
            }
        }

        return !in_array(strtolower($parts[0]), $this->reservedFontFamilyNames(), true);
    }

    /**
     * @return list<string>
     */
    private function reservedFontFamilyNames(): array
    {
        return [
            'default',
            'inherit',
            'initial',
            'revert',
            'revert-layer',
            'unset',
        ];
    }

    private function isGenericFontFamily(string $family): bool
    {
        return in_array($family, [
            'serif',
            'sans-serif',
            'monospace',
            'cursive',
            'fantasy',
            'system-ui',
            'emoji',
            'math',
            'fangsong',
        ], true);
    }

    private function isFontStyleAngleToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:deg|grad|rad|turn)$/i', trim($token)) === 1;
    }

    private function isFontWeightToken(string $value): bool
    {
        return in_array($value, ['normal', 'bold', 'bolder', 'lighter'], true)
            || preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1;
    }

    private function isFontStretchToken(string $value): bool
    {
        return in_array($value, self::FONT_STRETCH_KEYWORDS, true)
            || preg_match('/^[+-]?(?:\d+|\d*\.\d+)%$/', $value) === 1;
    }

    private function isFontSizeToken(string $value): bool
    {
        $value = trim($value);
        $lower = strtolower($value);
        if (in_array($lower, self::FONT_SIZE_KEYWORDS, true)) {
            return true;
        }

        return preg_match('/^(?:0|[+-]?(?:\d+|\d*\.\d+)[a-z%]+|(?:calc|clamp|min|max|var)\()/i', $value) === 1;
    }

    private function isFontProperty(string $property): bool
    {
        return $property === 'font' || $this->isFontLonghand($property);
    }

    private function isFontLonghand(string $property): bool
    {
        return in_array($property, self::FONT_LONGHANDS, true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getContainerProperty(array $entries, string $property): ?array
    {
        if (!$this->isContainerProperty($property)) {
            return null;
        }

        $components = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === 'container') {
                $parsed = $this->parseContainerComponents($entry['value']);
                if ($parsed === null) {
                    continue;
                }

                foreach (self::CONTAINER_LONGHANDS as $longhand) {
                    $components[$longhand] = [
                        'value' => $parsed[$longhand],
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            if ($this->isContainerLonghand($entry['property'])) {
                $components[$entry['property']] = [
                    'value' => $this->normalizeContainerLonghandValue($entry['property'], $entry['value']),
                    'important' => $entry['important'],
                ];
            }
        }

        if ($property !== 'container') {
            return $components[$property] ?? null;
        }

        foreach (self::CONTAINER_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
        }

        $important = $this->sameImportant(array_values($components));
        if ($important === null) {
            return null;
        }

        return [
            'value' => $this->serializeContainerComponents([
                'container-name' => $components['container-name']['value'],
                'container-type' => $components['container-type']['value'],
            ]),
            'important' => $important,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setContainerLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isContainerLonghand($property)) {
            return null;
        }

        $value = $this->normalizeContainerLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'container') {
                continue;
            }

            $components = $this->parseContainerComponents($entries[$index]['value']);
            if ($components === null) {
                continue;
            }

            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'container',
                'value' => $this->serializeContainerComponents($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeContainerLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'container') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseContainerComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::CONTAINER_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @return array{container-name:string, container-type:string}|null
     */
    private function parseContainerComponents(string $value): ?array
    {
        $parts = array_map('trim', $this->splitTopLevel($value, '/'));
        if (count($parts) > 2 || $parts === [] || $parts[0] === '') {
            return null;
        }

        $type = $parts[1] ?? 'normal';
        if ($type === '') {
            return null;
        }

        return [
            'container-name' => $this->normalizeContainerLonghandValue('container-name', $parts[0]),
            'container-type' => $this->normalizeContainerLonghandValue('container-type', $type),
        ];
    }

    /**
     * @param array{container-name:string, container-type:string} $components
     */
    private function serializeContainerComponents(array $components): string
    {
        $name = $this->normalizeContainerLonghandValue('container-name', $components['container-name']);
        $type = $this->normalizeContainerLonghandValue('container-type', $components['container-type']);
        if ($type === 'normal') {
            return $name;
        }

        return $name . ' / ' . $type;
    }

    private function normalizeContainerLonghandValue(string $property, string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', trim($value)) ?? $value);
        if ($property === 'container-type') {
            $lower = strtolower($value);

            return in_array($lower, self::CONTAINER_TYPES, true) ? $lower : $value;
        }

        return strcasecmp($value, 'none') === 0 ? 'none' : $value;
    }

    private function isContainerProperty(string $property): bool
    {
        return $property === 'container' || $this->isContainerLonghand($property);
    }

    private function isContainerLonghand(string $property): bool
    {
        return in_array($property, self::CONTAINER_LONGHANDS, true);
    }

    private function findTopLevelCharacter(string $value, string $character): ?int
    {
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
            if ($char === $character && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function isQuotedStringToken(string $token): bool
    {
        return preg_match('/^([\'"]).*\1$/s', trim($token)) === 1;
    }

    private function normalizeCssStringToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^([\'"])(.*)\1$/s', $token, $matches) !== 1) {
            return $token;
        }

        return '"' . str_replace('"', '\\"', $matches[2]) . '"';
    }

    private function normalizeCssUrlToken(string $token): string
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

    public function setProperty(string $block, string $property, string $value, bool $important = false): string
    {
        $property = $this->normalizeProperty($property);
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('CSS declaration value cannot be empty');
        }

        [$normalEntries, $importantEntries] = $this->partitionEntriesByImportance($this->parseEntries($block));
        if ($important) {
            $normalEntries = $this->removeEntriesWithPropertyId($normalEntries, $property);
            $importantEntries = $this->setPropertyWithinPriority($importantEntries, $property, $value, true);
        } else {
            $importantEntries = $this->removeEntriesWithPropertyId($importantEntries, $property);
            $normalEntries = $this->setPropertyWithinPriority($normalEntries, $property, $value, false);
        }

        return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function setPropertyWithinPriority(array $entries, string $property, string $value, bool $important): array
    {
        if ($this->isBoxLonghand($property)) {
            return $this->parseEntries($this->setBoxLonghand($entries, $property, $value, $important));
        }
        $backgroundValue = $this->setBackgroundLonghand($entries, $property, $value, $important);
        if ($backgroundValue !== null) {
            return $this->parseEntries($backgroundValue);
        }
        $outlineValue = $this->setOutlineLonghand($entries, $property, $value, $important);
        if ($outlineValue !== null) {
            return $this->parseEntries($outlineValue);
        }
        $flexValue = $this->setFlexLonghand($entries, $property, $value, $important);
        if ($flexValue !== null) {
            return $this->parseEntries($flexValue);
        }
        $transitionValue = $this->setTransitionLonghand($entries, $property, $value, $important);
        if ($transitionValue !== null) {
            return $this->parseEntries($transitionValue);
        }
        $animationValue = $this->setAnimationLonghand($entries, $property, $value, $important);
        if ($animationValue !== null) {
            return $this->parseEntries($animationValue);
        }
        $maskValue = $this->setMaskLonghand($entries, $property, $value, $important);
        if ($maskValue !== null) {
            return $this->parseEntries($maskValue);
        }
        $borderImageValue = $this->setBorderImageLonghand($entries, $property, $value, $important);
        if ($borderImageValue !== null) {
            return $this->parseEntries($borderImageValue);
        }
        $maskBorderValue = $this->setMaskBorderLonghand($entries, $property, $value, $important);
        if ($maskBorderValue !== null) {
            return $this->parseEntries($maskBorderValue);
        }
        $borderRadiusValue = $this->setBorderRadiusLonghand($entries, $property, $value, $important);
        if ($borderRadiusValue !== null) {
            return $this->parseEntries($borderRadiusValue);
        }
        $gridValue = $this->setGridPlacementLonghand($entries, $property, $value, $important);
        if ($gridValue !== null) {
            return $this->parseEntries($gridValue);
        }
        $placeAlignmentValue = $this->setPlaceAlignmentLonghand($entries, $property, $value, $important);
        if ($placeAlignmentValue !== null) {
            return $this->parseEntries($placeAlignmentValue);
        }
        $gapValue = $this->setGapLonghand($entries, $property, $value, $important);
        if ($gapValue !== null) {
            return $this->parseEntries($gapValue);
        }
        $overflowValue = $this->setOverflowLonghand($entries, $property, $value, $important);
        if ($overflowValue !== null) {
            return $this->parseEntries($overflowValue);
        }
        $logicalBoxValue = $this->setLogicalBoxProperty($entries, $property, $value, $important);
        if ($logicalBoxValue !== null) {
            return $this->parseEntries($logicalBoxValue);
        }
        $listStyleValue = $this->setListStyleLonghand($entries, $property, $value, $important);
        if ($listStyleValue !== null) {
            return $this->parseEntries($listStyleValue);
        }
        $textDecorationValue = $this->setTextDecorationLonghand($entries, $property, $value, $important);
        if ($textDecorationValue !== null) {
            return $this->parseEntries($textDecorationValue);
        }
        $textEmphasisValue = $this->setTextEmphasisLonghand($entries, $property, $value, $important);
        if ($textEmphasisValue !== null) {
            return $this->parseEntries($textEmphasisValue);
        }
        $caretValue = $this->setCaretLonghand($entries, $property, $value, $important);
        if ($caretValue !== null) {
            return $this->parseEntries($caretValue);
        }
        $fontValue = $this->setFontLonghand($entries, $property, $value, $important);
        if ($fontValue !== null) {
            return $this->parseEntries($fontValue);
        }
        $containerValue = $this->setContainerLonghand($entries, $property, $value, $important);
        if ($containerValue !== null) {
            return $this->parseEntries($containerValue);
        }

        $lastMatch = null;
        foreach ($entries as $index => $entry) {
            if ($entry['property'] === $property) {
                $lastMatch = $index;
            }
        }

        $replacement = [
            'property' => $property,
            'value' => $value,
            'important' => $important,
        ];

        if ($lastMatch === null) {
            $entries[] = $replacement;
        } else {
            $entries[$lastMatch] = $replacement;
        }

        return $entries;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setBackgroundLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!in_array($property, self::BACKGROUND_LONGHANDS, true)) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'background') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }
            if (!$this->backgroundLonghandCanApplyToShorthand($entries[$index]['value'], $property, $value)) {
                return null;
            }

            $components = $this->backgroundComponentsFromShorthand($entries[$index]['value'], $entries[$index]['important']);
            $this->applyBackgroundLonghand($components, $property, $value, $important);
            $background = $this->composeBackgroundValue($components);
            if ($background === null) {
                return null;
            }

            $entries[$index] = [
                'property' => 'background',
                'value' => $background,
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    private function backgroundLonghandCanApplyToShorthand(string $background, string $property, string $value): bool
    {
        if ($property === 'background-color') {
            return true;
        }

        return count($this->splitTopLevel($value, ',')) === count($this->parseBackgroundLayers($background));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setOutlineLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isOutlineLonghand($property)) {
            return null;
        }

        $value = $this->normalizeOutlineLonghandValue($property, $value);
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'outline') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $components = $this->completeOutlineComponents($this->parseOutlineValue($entries[$index]['value']));
            $components[$property] = $value;
            $entries[$index] = [
                'property' => 'outline',
                'value' => $this->composeOutlineShorthandValue($components),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setFlexItemLonghand(array $entries, string $property, string $base, string $prefix, string $value, bool $important): ?string
    {
        $value = $this->normalizeFlexLonghandValue($base, $value);
        if ($value === null) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] === $this->flexProperty($prefix, 'flex')) {
                if ($entries[$index]['important'] !== $important) {
                    return null;
                }

                $components = $this->parseFlexShorthandComponents($entries[$index]['value']);
                if ($components === null) {
                    continue;
                }

                $components[$base] = $value;
                $entries[$index] = [
                    'property' => $entries[$index]['property'],
                    'value' => $this->composeFlexShorthandValue($components),
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($this->baseFlexProperty($entries[$index]['property']) === 'flex') {
                $components = $this->parseFlexShorthandComponents($entries[$index]['value']);
                if ($components === null) {
                    continue;
                }

                $entryPrefix = $this->flexPrefixForProperty($entries[$index]['property']);
                if ($entryPrefix === null) {
                    continue;
                }

                array_splice(
                    $entries,
                    $index,
                    1,
                    $this->flexItemLonghandEntries($components, $entryPrefix, $base, $entries[$index]['important'])
                );
                $entries[] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }
        }

        return null;
    }

    /**
     * @param array{flex-grow:string, flex-shrink:string, flex-basis:string} $components
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function flexItemLonghandEntries(array $components, string $prefix, ?string $skip, bool $important): array
    {
        $entries = [];
        foreach (self::FLEX_ITEM_LONGHANDS as $longhand) {
            if ($longhand === $skip) {
                continue;
            }

            $entries[] = [
                'property' => $this->flexProperty($prefix, $longhand),
                'value' => $components[$longhand],
                'important' => $important,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setFlexLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);
        if ($prefix === null || $base === null) {
            return null;
        }

        if (in_array($base, self::FLEX_ITEM_LONGHANDS, true)) {
            return $this->setFlexItemLonghand($entries, $property, $base, $prefix, $value, $important);
        }

        if (!in_array($base, ['flex-direction', 'flex-wrap'], true)) {
            return null;
        }

        $component = $base === 'flex-direction' ? 'direction' : 'wrap';
        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] === $this->flexProperty($prefix, 'flex-flow')) {
                if ($entries[$index]['important'] !== $important) {
                    return null;
                }

                $components = $this->expandFlexFlow($entries[$index]['value']);
                $components[$component] = $value;
                $entries[$index] = [
                    'property' => $entries[$index]['property'],
                    'value' => $this->composeFlexFlow($components['direction'], $components['wrap']),
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($this->baseFlexProperty($entries[$index]['property']) === 'flex-flow') {
                $components = $this->expandFlexFlow($entries[$index]['value']);
                if ($components[$component] === null) {
                    continue;
                }

                $components[$component] = null;
                $entries[$index] = [
                    'property' => $entries[$index]['property'],
                    'value' => $this->composeFlexFlow($components['direction'], $components['wrap']),
                    'important' => $entries[$index]['important'],
                ];
                $entries[] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setGridPlacementLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!in_array($property, self::GRID_AREA_COMPONENTS, true)) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            $values = $this->gridPlacementLonghandValuesFromShorthand(
                $entries[$index]['property'],
                $entries[$index]['value']
            );
            if ($values === null || !array_key_exists($property, $values)) {
                continue;
            }

            $values[$property] = $value;
            $serialized = $this->serializeGridPlacementShorthand($entries[$index]['property'], $values);
            if ($serialized === null) {
                continue;
            }

            $entries[$index] = [
                'property' => $entries[$index]['property'],
                'value' => $serialized,
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setAnimationNameLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if ($property !== 'animation-name') {
            return null;
        }

        $names = array_values(array_filter(
            array_map('trim', $this->splitTopLevel($value, ',')),
            static fn (string $name): bool => $name !== ''
        ));
        if ($names === []) {
            throw new \InvalidArgumentException('animation-name cannot be empty');
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === 'animation-name') {
                $entries[$index] = [
                    'property' => 'animation-name',
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'animation') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $layers = $this->splitTopLevel($entries[$index]['value'], ',');
            if (count($names) === count($layers)) {
                $updated = [];
                foreach ($layers as $layerIndex => $layer) {
                    $updated[] = $this->composeAnimationLayer(
                        $this->parseAnimationLayer($layer)['baseTokens'],
                        $names[$layerIndex]
                    );
                }

                $entries[$index] = [
                    'property' => 'animation',
                    'value' => implode(', ', $updated),
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            $entries[$index] = [
                'property' => 'animation',
                'value' => implode(', ', array_map(
                    function (string $layer): string {
                        $parts = $this->parseAnimationLayer($layer);

                        return $this->composeAnimationLayer($parts['baseTokens'], $parts['name']);
                    },
                    $layers
                )),
                'important' => $important,
            ];
            $entries[] = [
                'property' => 'animation-name',
                'value' => $value,
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setAnimationLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        if (!$this->isAnimationLonghand($property)) {
            return null;
        }

        if ($property === 'animation-name') {
            return $this->setAnimationNameLonghand($entries, $property, $value, $important);
        }

        $value = $this->normalizeAnimationLonghandList($property, $value);
        $values = $this->animationComponentList($value);
        if ($values === []) {
            throw new \InvalidArgumentException("{$property} cannot be empty");
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== 'animation') {
                continue;
            }
            if ($entries[$index]['important'] !== $important) {
                return null;
            }

            $layers = $this->parseAnimationCssomLayers($entries[$index]['value']);
            if (count($values) !== count($layers)) {
                $entries[$index] = [
                    'property' => 'animation',
                    'value' => $this->serializeAnimationCssomLayers($layers),
                    'important' => $important,
                ];
                $entries[] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            foreach ($layers as $layerIndex => $_layer) {
                $layers[$layerIndex][$property] = $values[$layerIndex];
            }

            $entries[$index] = [
                'property' => 'animation',
                'value' => $this->serializeAnimationCssomLayers($layers),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        return null;
    }

    /**
     * @return array<string, array{value:string, important:bool}>
     */
    private function animationComponentsFromShorthand(string $value, bool $important): array
    {
        $layers = $this->parseAnimationCssomLayers($value);
        $components = [
            'animation' => ['value' => $this->serializeAnimationCssomLayers($layers), 'important' => $important],
        ];

        foreach (self::ANIMATION_LONGHANDS as $longhand) {
            $components[$longhand] = [
                'value' => implode(', ', array_column($layers, $longhand)),
                'important' => $important,
            ];
        }

        return $components;
    }

    /**
     * @param array<string, array{value:string, important:bool}> $components
     * @return array{value:string, important:bool}|null
     */
    private function composeAnimationShorthandProperty(array $components): ?array
    {
        $lists = [];
        $important = null;
        $length = null;
        foreach (self::ANIMATION_LONGHANDS as $longhand) {
            if (!isset($components[$longhand])) {
                return null;
            }
            if ($important === null) {
                $important = $components[$longhand]['important'];
            } elseif ($components[$longhand]['important'] !== $important) {
                return null;
            }

            $parts = $this->animationComponentList($components[$longhand]['value']);
            if ($parts === []) {
                return null;
            }
            if ($length === null) {
                $length = count($parts);
            } elseif (count($parts) !== $length) {
                return null;
            }
            $lists[$longhand] = $parts;
        }

        $layers = [];
        for ($index = 0; $index < $length; $index++) {
            $layer = [];
            foreach (self::ANIMATION_LONGHANDS as $longhand) {
                $layer[$longhand] = $lists[$longhand][$index];
            }
            $layers[] = $layer;
        }

        return [
            'value' => $this->serializeAnimationCssomLayers($layers),
            'important' => $important ?? false,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseAnimationCssomLayers(string $value): array
    {
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $layers[] = $this->parseAnimationCssomLayer($layer);
        }

        return $layers === [] ? [$this->animationDefaultLayer()] : $layers;
    }

    /**
     * @return array<string, string>
     */
    private function parseAnimationCssomLayer(string $layer): array
    {
        $components = $this->animationDefaultLayer();
        $durationSet = false;
        $delaySet = false;
        $timingSet = false;
        $iterationSet = false;
        $directionSet = false;
        $fillSet = false;
        $playStateSet = false;
        $timelineSet = false;
        $name = null;

        foreach ($this->splitWhitespaceTopLevel($layer) as $token) {
            $lower = strtolower($token);
            if ($this->isAnimationTimeToken($lower)) {
                if (!$durationSet) {
                    $components['animation-duration'] = $this->canonicalAnimationTime($token);
                    $durationSet = true;
                    continue;
                }
                if (!$delaySet) {
                    $components['animation-delay'] = $this->canonicalAnimationTime($token);
                    $delaySet = true;
                    continue;
                }
            }

            if (!$timingSet && $this->isAnimationTimingToken($lower)) {
                $components['animation-timing-function'] = $token;
                $timingSet = true;
                continue;
            }

            if (!$iterationSet && $this->isAnimationIterationToken($lower)) {
                $components['animation-iteration-count'] = $this->normalizeAnimationIterationCount($token);
                $iterationSet = true;
                continue;
            }

            if (!$directionSet && in_array($lower, self::ANIMATION_DIRECTIONS, true)) {
                $components['animation-direction'] = $lower;
                $directionSet = true;
                continue;
            }

            if (!$fillSet && in_array($lower, self::ANIMATION_FILL_MODES, true)) {
                $components['animation-fill-mode'] = $lower;
                $fillSet = true;
                continue;
            }

            if (!$playStateSet && in_array($lower, self::ANIMATION_PLAY_STATES, true)) {
                $components['animation-play-state'] = $lower;
                $playStateSet = true;
                continue;
            }

            if (!$timelineSet && $this->isAnimationTimelineToken($token)) {
                $components['animation-timeline'] = trim($token);
                $timelineSet = true;
                continue;
            }

            $name = $name === null ? $token : $name . ' ' . $token;
        }

        if ($name !== null && trim($name) !== '') {
            $components['animation-name'] = trim($name);
        }

        return $components;
    }

    /**
     * @return array<string, string>
     */
    private function animationDefaultLayer(): array
    {
        return [
            'animation-name' => 'none',
            'animation-duration' => '0s',
            'animation-timing-function' => 'ease',
            'animation-iteration-count' => '1',
            'animation-direction' => 'normal',
            'animation-play-state' => 'running',
            'animation-delay' => '0s',
            'animation-fill-mode' => 'none',
            'animation-timeline' => 'auto',
        ];
    }

    /**
     * @param list<array<string, string>> $layers
     */
    private function serializeAnimationCssomLayers(array $layers): string
    {
        return implode(', ', array_map(
            fn (array $layer): string => $this->serializeAnimationCssomLayer($layer),
            $layers
        ));
    }

    /**
     * @param array<string, string> $layer
     */
    private function serializeAnimationCssomLayer(array $layer): string
    {
        $name = $layer['animation-name'] ?? 'none';
        if (strcasecmp($name, 'none') === 0) {
            return 'none';
        }

        $duration = $this->canonicalAnimationTime($layer['animation-duration'] ?? '0s');
        $delay = $this->canonicalAnimationTime($layer['animation-delay'] ?? '0s');
        $timing = $layer['animation-timing-function'] ?? 'ease';
        $iteration = $this->normalizeAnimationIterationCount($layer['animation-iteration-count'] ?? '1');
        $direction = strtolower($layer['animation-direction'] ?? 'normal');
        $playState = strtolower($layer['animation-play-state'] ?? 'running');
        $fillMode = strtolower($layer['animation-fill-mode'] ?? 'none');
        $timeline = $layer['animation-timeline'] ?? 'auto';
        $parts = [];

        if (!$this->isZeroAnimationTime($duration) || !$this->isZeroAnimationTime($delay)) {
            $parts[] = $duration;
        }
        if (strcasecmp($timing, 'ease') !== 0 || $this->animationNameConflictsWith($name, $timing)) {
            $parts[] = $timing;
        }
        if (!$this->isZeroAnimationTime($delay)) {
            $parts[] = $delay;
        }
        if ($iteration !== '1' || strcasecmp($name, 'infinite') === 0) {
            $parts[] = $iteration;
        }
        if ($direction !== 'normal' || in_array(strtolower($name), self::ANIMATION_DIRECTIONS, true)) {
            $parts[] = $direction;
        }
        if ($fillMode !== 'none' || (strcasecmp($name, 'none') !== 0 && in_array(strtolower($name), self::ANIMATION_FILL_MODES, true))) {
            $parts[] = $fillMode;
        }
        if ($playState !== 'running' || in_array(strtolower($name), self::ANIMATION_PLAY_STATES, true)) {
            $parts[] = $playState;
        }

        $parts[] = $name;
        if (strcasecmp($timeline, 'auto') !== 0) {
            $parts[] = $timeline;
        }

        return implode(' ', $parts);
    }

    private function normalizeAnimationLonghandList(string $property, string $value): string
    {
        return implode(', ', array_map(
            fn (string $part): string => $this->normalizeAnimationLonghandValue($property, $part),
            $this->animationComponentList($value)
        ));
    }

    private function normalizeAnimationLonghandValue(string $property, string $value): string
    {
        $value = trim($value);

        return match ($property) {
            'animation-duration', 'animation-delay' => $this->canonicalAnimationTime($value),
            'animation-iteration-count' => $this->normalizeAnimationIterationCount($value),
            'animation-direction', 'animation-play-state', 'animation-fill-mode' => strtolower($value),
            default => $value,
        };
    }

    /**
     * @return list<string>
     */
    private function animationComponentList(string $value): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $part): string => trim($part),
                $this->splitTopLevel($value, ',')
            ),
            static fn (string $part): bool => $part !== ''
        ));
    }

    private function canonicalAnimationTime(string $token): string
    {
        return $this->isZeroAnimationTime($token) ? '0s' : trim($token);
    }

    private function isZeroAnimationTime(string $token): bool
    {
        return preg_match('/^[+-]?(?:0+|0*\.0+)(?:ms|s)$/i', trim($token)) === 1;
    }

    private function normalizeAnimationIterationCount(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'infinite') {
            return $value;
        }

        return preg_replace('/(?<=\d)\.0+$/', '', $value) ?? $value;
    }

    private function animationNameConflictsWith(string $name, string $component): bool
    {
        $name = strtolower(trim($name));
        $component = strtolower(trim($component));

        return $name === $component
            || ($this->isAnimationTimingToken($component) && $this->isAnimationTimingToken($name));
    }

    private function isAnimationTimelineToken(string $token): bool
    {
        return preg_match('/^(?:scroll|view)\(/i', trim($token)) === 1;
    }

    private function isAnimationProperty(string $property): bool
    {
        return $property === 'animation' || $this->isAnimationLonghand($property);
    }

    private function isAnimationLonghand(string $property): bool
    {
        return in_array($property, self::ANIMATION_LONGHANDS, true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setLogicalBoxProperty(array $entries, string $property, string $value, bool $important): ?string
    {
        $parts = $this->logicalBoxLonghandParts($property);
        if ($parts === null) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($this->isPhysicalBoxPropertyFor($entries[$index]['property'], $parts['shorthand'])) {
                break;
            }

            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($entries[$index]['property'] !== $parts['axisShorthand']) {
                continue;
            }

            $expanded = $this->expandLogicalBoxAxisShorthand($entries[$index]['value']);
            if ($expanded === null) {
                continue;
            }

            $expanded[$parts['side']] = $value;
            $entries[$index] = [
                'property' => $parts['axisShorthand'],
                'value' => $this->compressLogicalBoxAxisShorthand($expanded['start'], $expanded['end']),
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        $entries[] = [
            'property' => $property,
            'value' => $value,
            'important' => $important,
        ];

        return $this->serializeEntries($entries);
    }

    /**
     * @return array{start:string, end:string}|null
     */
    private function expandLogicalBoxAxisShorthand(string $value): ?array
    {
        $parts = $this->splitWhitespaceTopLevel($value);
        $count = count($parts);
        if ($count < 1 || $count > 2) {
            return null;
        }

        return [
            'start' => $parts[0],
            'end' => $parts[1] ?? $parts[0],
        ];
    }

    private function compressLogicalBoxAxisShorthand(string $start, string $end): string
    {
        return $start === $end ? $start : $start . ' ' . $end;
    }

    /**
     * @return array{baseTokens:list<string>, name:?string}
     */
    private function parseAnimationLayer(string $layer): array
    {
        $base = [];
        $name = null;
        $timeCount = 0;
        $seenTiming = false;
        $seenIteration = false;
        $seenDirection = false;
        $seenFill = false;
        $seenPlayState = false;
        $seenComposition = false;

        foreach ($this->splitWhitespaceTopLevel($layer) as $token) {
            $lower = strtolower($token);
            if ($this->isAnimationTimeToken($lower) && $timeCount < 2) {
                $base[] = $token;
                $timeCount++;
                continue;
            }

            if (!$seenTiming && $this->isAnimationTimingToken($lower)) {
                $base[] = $token;
                $seenTiming = true;
                continue;
            }

            if (!$seenIteration && $this->isAnimationIterationToken($lower)) {
                $base[] = $token;
                $seenIteration = true;
                continue;
            }

            if (!$seenDirection && in_array($lower, self::ANIMATION_DIRECTIONS, true)) {
                $base[] = $token;
                $seenDirection = true;
                continue;
            }

            if (!$seenFill && in_array($lower, self::ANIMATION_FILL_MODES, true) && $lower !== 'none') {
                $base[] = $token;
                $seenFill = true;
                continue;
            }

            if (!$seenPlayState && in_array($lower, self::ANIMATION_PLAY_STATES, true)) {
                $base[] = $token;
                $seenPlayState = true;
                continue;
            }

            if (!$seenComposition && in_array($lower, self::ANIMATION_COMPOSITIONS, true)) {
                $base[] = $token;
                $seenComposition = true;
                continue;
            }

            if ($name === null) {
                $name = $token;
            } else {
                $name .= ' ' . $token;
            }
        }

        return ['baseTokens' => $base, 'name' => $name];
    }

    /**
     * @param list<string> $baseTokens
     */
    private function composeAnimationLayer(array $baseTokens, ?string $name): string
    {
        $parts = $baseTokens;
        if ($name !== null && trim($name) !== '') {
            $parts[] = trim($name);
        }

        if ($parts === []) {
            return 'none';
        }

        return implode(' ', $parts);
    }

    private function isAnimationTimeToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:ms|s)$/', $token) === 1;
    }

    private function isAnimationTimingToken(string $token): bool
    {
        return in_array($token, self::ANIMATION_TIMING_FUNCTIONS, true)
            || preg_match('/^(?:cubic-bezier|steps|linear)\(/', $token) === 1;
    }

    private function isAnimationIterationToken(string $token): bool
    {
        return $token === 'infinite' || preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1;
    }

    public function removeProperty(string $block, string $property): string
    {
        $property = $this->normalizeProperty($property);
        [$normalEntries, $importantEntries] = $this->partitionEntriesByImportance($this->parseEntries($block));

        if ($this->isBoxShorthand($property)) {
            $normalEntries = $this->removeBoxShorthandWithinPriority($normalEntries, $property);
            $importantEntries = $this->removeBoxShorthandWithinPriority($importantEntries, $property);

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($property === 'background') {
            $normalEntries = $this->removeBackgroundShorthandWithinPriority($normalEntries);
            $importantEntries = $this->removeBackgroundShorthandWithinPriority($importantEntries);

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isTransitionShorthand($property)) {
            $normalEntries = $this->removeTransitionShorthandWithinPriority($normalEntries, $property);
            $importantEntries = $this->removeTransitionShorthandWithinPriority($importantEntries, $property);

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($property === 'animation') {
            $normalEntries = $this->removeAnimationShorthandWithinPriority($normalEntries);
            $importantEntries = $this->removeAnimationShorthandWithinPriority($importantEntries);

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        $shorthandLonghands = $this->cssomShorthandLonghands($property);
        if ($shorthandLonghands !== null) {
            $normalEntries = $this->removeShorthandLonghandsWithinPriority($normalEntries, $property, $shorthandLonghands);
            $importantEntries = $this->removeShorthandLonghandsWithinPriority($importantEntries, $property, $shorthandLonghands);

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }

        if ($this->isBoxLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeBoxLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeBoxLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isLogicalBoxLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeLogicalBoxLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeLogicalBoxLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isBorderComponentLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeBorderComponentLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeBorderComponentLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isOutlineLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeOutlineLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeOutlineLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isRemovableFlexLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeFlexLonghand($normalEntries, $property) ?? $this->serializeEntries($normalEntries));
            $importantEntries = $this->parseEntries($this->removeFlexLonghand($importantEntries, $property) ?? $this->serializeEntries($importantEntries));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isTransitionLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeTransitionLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeTransitionLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isAnimationLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeAnimationLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeAnimationLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isMaskLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeMaskLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeMaskLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isBorderImageLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeBorderImageLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeBorderImageLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isMaskBorderLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeMaskBorderLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeMaskBorderLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isBorderRadiusLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeBorderRadiusLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeBorderRadiusLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isGridPlacementProperty($property)) {
            $normalEntries = $this->parseEntries($this->removeGridPlacementProperty($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeGridPlacementProperty($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->placeAlignmentShorthandForLonghand($property) !== null) {
            $normalEntries = $this->parseEntries($this->removePlaceAlignmentLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removePlaceAlignmentLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isGapLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeGapLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeGapLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isOverflowLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeOverflowLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeOverflowLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isListStyleLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeListStyleLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeListStyleLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isTextDecorationLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeTextDecorationLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeTextDecorationLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isTextEmphasisLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeTextEmphasisLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeTextEmphasisLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isCaretLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeCaretLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeCaretLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isFontLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeFontLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeFontLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isContainerLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeContainerLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeContainerLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }

        $normalEntries = $this->removeEntriesWithPropertyId($normalEntries, $property);
        $importantEntries = $this->removeEntriesWithPropertyId($importantEntries, $property);

        return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function removeBoxShorthandWithinPriority(array $entries, string $property): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['property'] !== $property
                && !$this->isBoxLonghandFor($entry['property'], $property)
        ));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function removeBackgroundShorthandWithinPriority(array $entries): array
    {
        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['property'] !== 'background'
                && !in_array($entry['property'], self::BACKGROUND_LONGHANDS, true)
        ));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function removeAnimationShorthandWithinPriority(array $entries): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['property'] !== 'animation'
                && !$this->isAnimationLonghand($entry['property'])
        ));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @param list<string> $longhands
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function removeShorthandLonghandsWithinPriority(array $entries, string $property, array $longhands): array
    {
        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['property'] !== $property
                && !in_array($entry['property'], $longhands, true)
        ));
    }

    /**
     * @return list<string>|null
     */
    private function cssomShorthandLonghands(string $property): ?array
    {
        $logicalBoxLonghands = $this->logicalBoxAxisLonghands($property);
        if ($logicalBoxLonghands !== null) {
            return $logicalBoxLonghands;
        }

        $borderLonghands = $this->borderShorthandLonghands($property);
        if ($borderLonghands !== null) {
            return $borderLonghands;
        }

        $flexLonghands = $this->flexShorthandLonghands($property);
        if ($flexLonghands !== null) {
            return $flexLonghands;
        }

        $gridLonghands = $this->gridShorthandLonghands($property);
        if ($gridLonghands !== null) {
            return $gridLonghands;
        }

        if (isset(self::PLACE_ALIGNMENT_SHORTHANDS[$property])) {
            return array_values(self::PLACE_ALIGNMENT_SHORTHANDS[$property]);
        }

        if ($property === 'border-image') {
            return self::BORDER_IMAGE_LONGHANDS;
        }

        if ($property === 'mask') {
            return self::MASK_LONGHANDS;
        }

        if ($property === 'mask-border') {
            return self::MASK_BORDER_LONGHANDS;
        }

        if ($property === 'outline') {
            return self::OUTLINE_LONGHANDS;
        }

        if ($this->isBorderRadiusShorthand($property)) {
            return $this->borderRadiusLonghandsForPrefix($this->borderRadiusPrefixForProperty($property) ?? '');
        }

        if ($property === 'gap') {
            return self::GAP_LONGHANDS;
        }

        if ($property === 'overflow') {
            return self::OVERFLOW_LONGHANDS;
        }

        if ($property === 'list-style') {
            return self::LIST_STYLE_LONGHANDS;
        }

        if ($property === 'text-decoration') {
            return self::TEXT_DECORATION_LONGHANDS;
        }

        $textEmphasisLonghands = $this->textEmphasisShorthandLonghands($property);
        if ($textEmphasisLonghands !== null) {
            return $textEmphasisLonghands;
        }

        if ($property === 'caret') {
            return self::CARET_LONGHANDS;
        }

        if ($property === 'font') {
            return self::FONT_LONGHANDS;
        }

        return $property === 'container' ? self::CONTAINER_LONGHANDS : null;
    }

    /**
     * @return list<string>|null
     */
    private function flexShorthandLonghands(string $property): ?array
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);
        if ($prefix === null) {
            return null;
        }

        return match ($base) {
            'flex' => array_map(
                fn (string $longhand): string => $this->flexProperty($prefix, $longhand),
                self::FLEX_ITEM_LONGHANDS
            ),
            'flex-flow' => [
                $this->flexProperty($prefix, 'flex-direction'),
                $this->flexProperty($prefix, 'flex-wrap'),
            ],
            default => null,
        };
    }

    /**
     * @return list<string>|null
     */
    private function gridShorthandLonghands(string $property): ?array
    {
        return match ($property) {
            'grid-template' => self::GRID_TEMPLATE_COMPONENTS,
            'grid' => array_merge(self::GRID_TEMPLATE_COMPONENTS, self::GRID_AUTO_COMPONENTS),
            'grid-row' => ['grid-row-start', 'grid-row-end'],
            'grid-column' => ['grid-column-start', 'grid-column-end'],
            'grid-area' => self::GRID_AREA_COMPONENTS,
            default => null,
        };
    }

    private function isRemovableFlexLonghand(string $property): bool
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);

        return $prefix !== null && in_array($base, ['flex-direction', 'flex-wrap', 'flex-grow', 'flex-shrink', 'flex-basis'], true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeFlexLonghand(array $entries, string $property): ?string
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);
        if ($prefix === null || $base === null) {
            return null;
        }

        if (in_array($base, self::FLEX_ITEM_LONGHANDS, true)) {
            $result = [];
            foreach ($entries as $entry) {
                if ($entry['property'] === $property) {
                    continue;
                }

                if ($entry['property'] !== $this->flexProperty($prefix, 'flex')) {
                    $result[] = $entry;
                    continue;
                }

                $components = $this->parseFlexShorthandComponents($entry['value']);
                if ($components === null) {
                    $result[] = $entry;
                    continue;
                }

                array_push(
                    $result,
                    ...$this->flexItemLonghandEntries($components, $prefix, $base, $entry['important'])
                );
            }

            return $this->serializeEntries($result);
        }

        if (!in_array($base, ['flex-direction', 'flex-wrap'], true)) {
            return null;
        }

        $component = $base === 'flex-direction' ? 'direction' : 'wrap';
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== $this->flexProperty($prefix, 'flex-flow')) {
                $result[] = $entry;
                continue;
            }

            $components = $this->expandFlexFlow($entry['value']);
            $components[$component] = null;
            foreach (['direction' => 'flex-direction', 'wrap' => 'flex-wrap'] as $name => $longhand) {
                if ($components[$name] === null) {
                    continue;
                }

                $result[] = [
                    'property' => $this->flexProperty($prefix, $longhand),
                    'value' => $components[$name],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeMaskLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'mask') {
                $result[] = $entry;
                continue;
            }

            $components = $this->maskComponentsFromShorthand($entry['value'], $entry['important']);
            foreach (self::MASK_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand]['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeOutlineLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'outline') {
                $result[] = $entry;
                continue;
            }

            $components = $this->completeOutlineComponents($this->parseOutlineValue($entry['value']));
            foreach (self::OUTLINE_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeBorderComponentLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            $split = $this->splitBorderShorthandForRemovedLonghand($entry, $property);
            if ($split === null) {
                $result[] = $entry;
                continue;
            }

            array_push($result, ...$split);
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeListStyleLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'list-style') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseListStyleComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::LIST_STYLE_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeBorderImageLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'border-image') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseBorderImageComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::BORDER_IMAGE_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeMaskBorderLonghand(array $entries, string $property): string
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== 'mask-border') {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseMaskBorderComponents($entry['value']);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::MASK_BORDER_LONGHANDS as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeBorderRadiusLonghand(array $entries, string $property): string
    {
        $prefix = $this->borderRadiusPrefixForProperty($property);
        if ($prefix === null || !$this->isBorderRadiusLonghandForPrefix($property, $prefix)) {
            return $this->serializeEntries($entries);
        }

        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== $this->borderRadiusProperty($prefix, 'border-radius')) {
                $result[] = $entry;
                continue;
            }

            $components = $this->parseBorderRadiusComponents($entry['value'], $prefix);
            if ($components === null) {
                $result[] = $entry;
                continue;
            }

            foreach ($this->borderRadiusLonghandsForPrefix($prefix) as $longhand) {
                if ($longhand === $property) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $components[$longhand],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param array{property:string, value:string, important:bool} $entry
     * @return list<array{property:string, value:string, important:bool}>|null
     */
    private function splitBorderShorthandForRemovedLonghand(array $entry, string $property): ?array
    {
        $longhands = $this->borderShorthandLonghands($entry['property']);
        if ($longhands === null || !in_array($property, $longhands, true)) {
            return null;
        }

        $values = $this->borderLonghandValuesFromShorthand($entry['property'], $entry['value']);
        if ($values === null) {
            return null;
        }

        $split = [];
        foreach ($longhands as $longhand) {
            if ($longhand === $property || !isset($values[$longhand])) {
                continue;
            }

            $split[] = [
                'property' => $longhand,
                'value' => $values[$longhand],
                'important' => $entry['important'],
            ];
        }

        return $split;
    }

    private function isGridPlacementProperty(string $property): bool
    {
        return in_array($property, ['grid-area', 'grid-row', 'grid-column'], true)
            || in_array($property, self::GRID_AREA_COMPONENTS, true);
    }

    /**
     * @return list<string>|null
     */
    private function gridPlacementShorthandLonghands(string $property): ?array
    {
        return match ($property) {
            'grid-row' => ['grid-row-start', 'grid-row-end'],
            'grid-column' => ['grid-column-start', 'grid-column-end'],
            'grid-area' => self::GRID_AREA_COMPONENTS,
            default => null,
        };
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeGridPlacementProperty(array $entries, string $property): string
    {
        $longhands = $this->gridPlacementShorthandLonghands($property) ?? [];
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property || in_array($entry['property'], $longhands, true)) {
                continue;
            }

            if ($longhands !== []) {
                $result[] = $entry;
                continue;
            }

            $split = $this->splitGridPlacementShorthandForRemovedLonghand($entry, $property);
            if ($split === null) {
                $result[] = $entry;
                continue;
            }

            array_push($result, ...$split);
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param array{property:string, value:string, important:bool} $entry
     * @return list<array{property:string, value:string, important:bool}>|null
     */
    private function splitGridPlacementShorthandForRemovedLonghand(array $entry, string $property): ?array
    {
        $longhands = $this->gridPlacementShorthandLonghands($entry['property']);
        if ($longhands === null || !in_array($property, $longhands, true)) {
            return null;
        }

        $values = $this->gridPlacementLonghandValuesFromShorthand($entry['property'], $entry['value']);
        if ($values === null) {
            return null;
        }

        $split = [];
        foreach ($longhands as $longhand) {
            if ($longhand === $property || !array_key_exists($longhand, $values)) {
                continue;
            }

            $split[] = [
                'property' => $longhand,
                'value' => $values[$longhand],
                'important' => $entry['important'],
            ];
        }

        return $split;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function serializeEntries(array $entries): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            $value = $entry['value'];
            if ($entry['important']) {
                $value .= ' !important';
            }
            $parts[] = $entry['property'] . ': ' . $value;
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function cssomOrderedEntries(array $entries): array
    {
        [$normalEntries, $importantEntries] = $this->partitionEntriesByImportance($entries);

        return array_merge($normalEntries, $importantEntries);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{
     *     0:list<array{property:string, value:string, important:bool}>,
     *     1:list<array{property:string, value:string, important:bool}>
     * }
     */
    private function partitionEntriesByImportance(array $entries): array
    {
        $normalEntries = [];
        $importantEntries = [];
        foreach ($entries as $entry) {
            if ($entry['important']) {
                $importantEntries[] = $entry;
            } else {
                $normalEntries[] = $entry;
            }
        }

        return [$normalEntries, $importantEntries];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function removeEntriesWithPropertyId(array $entries, string $property): array
    {
        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['property'] !== $property
        ));
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getBoxProperty(array $entries, string $property): ?array
    {
        if ($this->isBoxShorthand($property)) {
            $sides = $this->resolveBoxSides($entries, $property);
            foreach ($sides as $side) {
                if ($side === null) {
                    return null;
                }
            }

            $important = $sides['top']['important'];
            foreach ($sides as $side) {
                if ($side['important'] !== $important) {
                    return null;
                }
            }

            return [
                'value' => $this->compressBoxShorthand([
                    'top' => $sides['top']['value'],
                    'right' => $sides['right']['value'],
                    'bottom' => $sides['bottom']['value'],
                    'left' => $sides['left']['value'],
                ]),
                'important' => $important,
            ];
        }

        $shorthand = $this->boxShorthandForLonghand($property);
        if ($shorthand === null) {
            return null;
        }

        $sideName = $this->boxSideForLonghand($property);
        if ($sideName === null) {
            return null;
        }

        $sides = $this->resolveBoxSides($entries, $shorthand);

        return $sides[$sideName];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function setBoxLonghand(array $entries, string $property, string $value, bool $important): string
    {
        $shorthand = $this->boxShorthandForLonghand($property);
        $sideName = $this->boxSideForLonghand($property);
        if ($shorthand === null || $sideName === null) {
            $entries[] = [
                'property' => $property,
                'value' => $value,
                'important' => $important,
            ];

            return $this->serializeEntries($entries);
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($entries[$index]['property'] === $property) {
                $entries[$index] = [
                    'property' => $property,
                    'value' => $value,
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }

            if ($this->isLogicalBoxPropertyFor($entries[$index]['property'], $shorthand)) {
                break;
            }

            if ($entries[$index]['property'] === $shorthand) {
                $sides = $this->expandBoxShorthand($entries[$index]['value']);
                if ($sides === null) {
                    break;
                }

                $sides[$sideName] = $value;
                $entries[$index] = [
                    'property' => $shorthand,
                    'value' => $this->compressBoxShorthand($sides),
                    'important' => $important,
                ];

                return $this->serializeEntries($entries);
            }
        }

        $entries[] = [
            'property' => $property,
            'value' => $value,
            'important' => $important,
        ];

        return $this->serializeEntries($entries);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeBoxLonghand(array $entries, string $property): string
    {
        $shorthand = $this->boxShorthandForLonghand($property);
        $sideName = $this->boxSideForLonghand($property);
        if ($shorthand === null || $sideName === null) {
            return $this->serializeEntries($entries);
        }

        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== $shorthand) {
                $result[] = $entry;
                continue;
            }

            $sides = $this->expandBoxShorthand($entry['value']);
            if ($sides === null) {
                $result[] = $entry;
                continue;
            }

            foreach (self::BOX_SHORTHANDS[$shorthand] as $side => $longhand) {
                if ($side === $sideName) {
                    continue;
                }

                $result[] = [
                    'property' => $longhand,
                    'value' => $sides[$side],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getLogicalBoxProperty(array $entries, string $property): ?array
    {
        $axis = $this->logicalBoxAxisForShorthand($property);
        if ($axis !== null) {
            $sides = $this->resolveLogicalBoxAxis($entries, $axis['shorthand'], $axis['axis']);
            if ($sides['start'] === null || $sides['end'] === null) {
                return null;
            }
            if ($sides['start']['important'] !== $sides['end']['important']) {
                return null;
            }

            return [
                'value' => $this->compressLogicalBoxAxisShorthand($sides['start']['value'], $sides['end']['value']),
                'important' => $sides['start']['important'],
            ];
        }

        $longhand = $this->logicalBoxLonghandParts($property);
        if ($longhand === null) {
            return null;
        }

        $sides = $this->resolveLogicalBoxAxis($entries, $longhand['shorthand'], $longhand['axis']);

        return $sides[$longhand['side']];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{
     *     start:array{value:string, important:bool}|null,
     *     end:array{value:string, important:bool}|null
     * }
     */
    private function resolveLogicalBoxAxis(array $entries, string $shorthand, string $axis): array
    {
        $sides = [
            'start' => null,
            'end' => null,
        ];
        $axisShorthand = $shorthand . '-' . $axis;
        $startProperty = $axisShorthand . '-start';
        $endProperty = $axisShorthand . '-end';

        foreach ($entries as $entry) {
            if ($entry['property'] === $axisShorthand) {
                $expanded = $this->expandLogicalBoxAxisShorthand($entry['value']);
                if ($expanded === null) {
                    continue;
                }

                $sides['start'] = [
                    'value' => $expanded['start'],
                    'important' => $entry['important'],
                ];
                $sides['end'] = [
                    'value' => $expanded['end'],
                    'important' => $entry['important'],
                ];
                continue;
            }

            if ($entry['property'] === $startProperty) {
                $sides['start'] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
                continue;
            }

            if ($entry['property'] === $endProperty) {
                $sides['end'] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $sides;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeLogicalBoxLonghand(array $entries, string $property): string
    {
        $parts = $this->logicalBoxLonghandParts($property);
        if ($parts === null) {
            return $this->serializeEntries($entries);
        }

        $result = [];
        foreach ($entries as $entry) {
            if ($entry['property'] === $property) {
                continue;
            }

            if ($entry['property'] !== $parts['axisShorthand']) {
                $result[] = $entry;
                continue;
            }

            $expanded = $this->expandLogicalBoxAxisShorthand($entry['value']);
            if ($expanded === null) {
                $result[] = $entry;
                continue;
            }

            foreach (['start', 'end'] as $side) {
                if ($side === $parts['side']) {
                    continue;
                }

                $result[] = [
                    'property' => $parts['shorthand'] . '-' . $parts['axis'] . '-' . $side,
                    'value' => $expanded[$side],
                    'important' => $entry['important'],
                ];
            }
        }

        return $this->serializeEntries($result);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{
     *     top:array{value:string, important:bool}|null,
     *     right:array{value:string, important:bool}|null,
     *     bottom:array{value:string, important:bool}|null,
     *     left:array{value:string, important:bool}|null
     * }
     */
    private function resolveBoxSides(array $entries, string $shorthand): array
    {
        $sides = [
            'top' => null,
            'right' => null,
            'bottom' => null,
            'left' => null,
        ];

        foreach ($entries as $entry) {
            if ($entry['property'] === $shorthand) {
                $expanded = $this->expandBoxShorthand($entry['value']);
                if ($expanded === null) {
                    continue;
                }

                foreach ($expanded as $side => $value) {
                    $sides[$side] = [
                        'value' => $value,
                        'important' => $entry['important'],
                    ];
                }
                continue;
            }

            $side = $this->boxSideForLonghand($entry['property']);
            if ($side !== null && $this->isBoxLonghandFor($entry['property'], $shorthand)) {
                $sides[$side] = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
            }
        }

        return $sides;
    }

    /**
     * @return array{top:string, right:string, bottom:string, left:string}|null
     */
    private function expandBoxShorthand(string $value): ?array
    {
        $parts = $this->splitWhitespaceTopLevel($value);
        $count = count($parts);
        if ($count < 1 || $count > 4) {
            return null;
        }

        return match ($count) {
            1 => [
                'top' => $parts[0],
                'right' => $parts[0],
                'bottom' => $parts[0],
                'left' => $parts[0],
            ],
            2 => [
                'top' => $parts[0],
                'right' => $parts[1],
                'bottom' => $parts[0],
                'left' => $parts[1],
            ],
            3 => [
                'top' => $parts[0],
                'right' => $parts[1],
                'bottom' => $parts[2],
                'left' => $parts[1],
            ],
            default => [
                'top' => $parts[0],
                'right' => $parts[1],
                'bottom' => $parts[2],
                'left' => $parts[3],
            ],
        };
    }

    /**
     * @param array{top:string, right:string, bottom:string, left:string} $sides
     */
    private function compressBoxShorthand(array $sides): string
    {
        if ($sides['top'] === $sides['bottom'] && $sides['right'] === $sides['left']) {
            if ($sides['top'] === $sides['right']) {
                return $sides['top'];
            }

            return $sides['top'] . ' ' . $sides['right'];
        }

        if ($sides['right'] === $sides['left']) {
            return $sides['top'] . ' ' . $sides['right'] . ' ' . $sides['bottom'];
        }

        return $sides['top'] . ' ' . $sides['right'] . ' ' . $sides['bottom'] . ' ' . $sides['left'];
    }

    /**
     * @return list<string>
     */
    private function splitWhitespaceTopLevel(string $value): array
    {
        $parts = [];
        $part = '';
        $quote = null;
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $part .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $part .= $value[++$i];
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
                $depth = max(0, $depth - 1);
            } elseif (ctype_space($char) && $depth === 0) {
                if (trim($part) !== '') {
                    $parts[] = trim($part);
                    $part = '';
                }
                continue;
            }

            $part .= $char;
        }

        if (trim($part) !== '') {
            $parts[] = trim($part);
        }

        return $parts;
    }

    private function isBoxShorthand(string $property): bool
    {
        return isset(self::BOX_SHORTHANDS[$property]);
    }

    private function isBoxLonghand(string $property): bool
    {
        return $this->boxShorthandForLonghand($property) !== null;
    }

    private function isBoxLonghandFor(string $property, string $shorthand): bool
    {
        return in_array($property, self::BOX_SHORTHANDS[$shorthand] ?? [], true);
    }

    private function isPhysicalBoxPropertyFor(string $property, string $shorthand): bool
    {
        return $property === $shorthand || $this->isBoxLonghandFor($property, $shorthand);
    }

    private function isLogicalBoxPropertyFor(string $property, string $shorthand): bool
    {
        return in_array($property, [
            "{$shorthand}-block",
            "{$shorthand}-block-start",
            "{$shorthand}-block-end",
            "{$shorthand}-inline",
            "{$shorthand}-inline-start",
            "{$shorthand}-inline-end",
        ], true);
    }

    /**
     * @return array{shorthand:string, axis:string, axisShorthand:string}|null
     */
    private function logicalBoxAxisForShorthand(string $property): ?array
    {
        foreach (array_keys(self::BOX_SHORTHANDS) as $shorthand) {
            foreach (['block', 'inline'] as $axis) {
                $axisShorthand = $shorthand . '-' . $axis;
                if ($property === $axisShorthand) {
                    return [
                        'shorthand' => $shorthand,
                        'axis' => $axis,
                        'axisShorthand' => $axisShorthand,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array{shorthand:string, axis:string, side:string, axisShorthand:string}|null
     */
    private function logicalBoxLonghandParts(string $property): ?array
    {
        foreach (array_keys(self::BOX_SHORTHANDS) as $shorthand) {
            foreach (['block', 'inline'] as $axis) {
                $axisShorthand = $shorthand . '-' . $axis;
                foreach (['start', 'end'] as $side) {
                    if ($property === $axisShorthand . '-' . $side) {
                        return [
                            'shorthand' => $shorthand,
                            'axis' => $axis,
                            'side' => $side,
                            'axisShorthand' => $axisShorthand,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function logicalBoxAxisLonghands(string $property): ?array
    {
        $axis = $this->logicalBoxAxisForShorthand($property);
        if ($axis === null) {
            return null;
        }

        return [
            $axis['axisShorthand'] . '-start',
            $axis['axisShorthand'] . '-end',
        ];
    }

    private function isLogicalBoxProperty(string $property): bool
    {
        return $this->logicalBoxAxisForShorthand($property) !== null
            || $this->logicalBoxLonghandParts($property) !== null;
    }

    private function isLogicalBoxLonghand(string $property): bool
    {
        return $this->logicalBoxLonghandParts($property) !== null;
    }

    private function boxShorthandForLonghand(string $property): ?string
    {
        foreach (self::BOX_SHORTHANDS as $shorthand => $longhands) {
            if (in_array($property, $longhands, true)) {
                return $shorthand;
            }
        }

        return null;
    }

    private function boxSideForLonghand(string $property): ?string
    {
        foreach (self::BOX_SHORTHANDS as $longhands) {
            $side = array_search($property, $longhands, true);
            if ($side !== false) {
                return $side;
            }
        }

        return null;
    }

    private function normalizeProperty(string $property): string
    {
        $property = strtolower(trim($property));
        if ($property === '') {
            throw new \InvalidArgumentException('CSS declaration property cannot be empty');
        }

        return $property;
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function splitImportantFlag(string $value): array
    {
        $value = trim($value);
        if (!str_ends_with(strtolower($value), 'important')) {
            return [$value, false];
        }

        $importantStart = strlen($value) - strlen('important');
        $beforeImportant = rtrim(substr($value, 0, $importantStart));
        if ($beforeImportant === '' || substr($beforeImportant, -1) !== '!') {
            return [$value, false];
        }

        $bang = strlen($beforeImportant) - 1;
        if (!$this->isTopLevelOffset($value, $bang)) {
            return [$value, false];
        }

        return [rtrim(substr($beforeImportant, 0, -1)), true];
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [''];
        $quote = null;
        $depth = 0;
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
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === $delimiter && $depth === 0) {
                $parts[] = '';
                continue;
            }
            $parts[array_key_last($parts)] .= $char;
        }

        return $parts;
    }

    private function findTopLevelColon(string $value): ?int
    {
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
                $depth = max(0, $depth - 1);
            } elseif ($char === ':' && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function isTopLevelOffset(string $value, int $target): bool
    {
        $quote = null;
        $depth = 0;
        for ($i = 0; $i < $target; $i++) {
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
                $depth = max(0, $depth - 1);
            }
        }

        return $quote === null && $depth === 0;
    }
}
