<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssFormatter
{
    private const TRANSFORM_FUNCTIONS = [
        'matrix',
        'matrix3d',
        'translate',
        'translateX',
        'translateY',
        'translateZ',
        'translate3d',
        'scale',
        'scaleX',
        'scaleY',
        'scaleZ',
        'scale3d',
        'rotate',
        'rotateX',
        'rotateY',
        'rotateZ',
        'rotate3d',
        'skew',
        'skewX',
        'skewY',
        'perspective',
    ];

    private const FONT_LONGHANDS = [
        'font-family',
        'font-size',
        'font-style',
        'font-weight',
        'font-stretch',
        'font-variant-caps',
        'line-height',
    ];

    private const BORDER_IMAGE_LONGHANDS = [
        'border-image-source',
        'border-image-slice',
        'border-image-width',
        'border-image-outset',
        'border-image-repeat',
    ];

    private const BORDER_PHYSICAL_SIDES = ['top', 'right', 'bottom', 'left'];

    private const BORDER_LOGICAL_SIDES = ['block-start', 'block-end', 'inline-start', 'inline-end'];

    private const BORDER_LOGICAL_AXIS_SIDES = [
        'block' => ['start' => 'block-start', 'end' => 'block-end'],
        'inline' => ['start' => 'inline-start', 'end' => 'inline-end'],
    ];

    private const BORDER_COMPONENTS = ['width', 'style', 'color'];

    private const BORDER_STYLES = ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];

    private const OUTLINE_COMPONENTS = ['width', 'style', 'color'];

    private const OUTLINE_STYLES = ['auto', 'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];

    private const BORDER_IMAGE_REPEAT_KEYWORDS = ['stretch', 'repeat', 'round', 'space'];

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

    public function format(string $css): string
    {
        $css = trim($this->stripComments($css));
        if ($css === '') {
            return '';
        }

        $rules = [];
        $cursor = 0;
        $length = strlen($css);
        $seenNonNamespaceRule = false;

        while (true) {
            $cursor = $this->skipWhitespace($css, $cursor);
            if ($cursor >= $length) {
                break;
            }

            $open = $this->findNextTopLevel($css, '{', $cursor);
            $semicolon = $this->findNextTopLevel($css, ';', $cursor);
            if ($semicolon !== null && ($open === null || $semicolon < $open)) {
                $statement = trim(substr($css, $cursor, $semicolon - $cursor));
                if (preg_match('/^@charset\b/i', $statement) === 1) {
                    $cursor = $semicolon + 1;
                    continue;
                }

                if (preg_match('/^@namespace\b/i', $statement) === 1) {
                    if ($seenNonNamespaceRule) {
                        throw new \InvalidArgumentException('Unexpected @namespace rule');
                    }

                    $rules[] = $this->formatNamespaceRule($statement);
                    $cursor = $semicolon + 1;
                    continue;
                }

                throw new \InvalidArgumentException('Unsupported CSS statement: ' . $statement);
            }

            if ($open === null) {
                throw new \InvalidArgumentException('Expected a @page block');
            }

            $prelude = trim(substr($css, $cursor, $open - $cursor));
            $close = $this->findMatchingBrace($css, $open);
            if (preg_match('/^@counter-style\s+([_a-zA-Z-][_a-zA-Z0-9-]*)$/', $prelude) === 1) {
                $rules[] = $this->formatCounterStyleRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $seenNonNamespaceRule = true;
                $cursor = $close + 1;
                continue;
            }

            if ($this->isPropertyRulePrelude($prelude)) {
                $rules[] = $this->formatPropertyRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $seenNonNamespaceRule = true;
                $cursor = $close + 1;
                continue;
            }

            if ($this->isPositionTryRulePrelude($prelude)) {
                $rules[] = $this->formatPositionTryRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $seenNonNamespaceRule = true;
                $cursor = $close + 1;
                continue;
            }

            if ($this->isConditionalGroupPrelude($prelude)) {
                $rules[] = $this->formatConditionalGroupRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $seenNonNamespaceRule = true;
                $cursor = $close + 1;
                continue;
            }

            if (!preg_match('/^@page(?:\s|:|$)/i', $prelude)) {
                if (str_starts_with($prelude, '@')) {
                    throw new \InvalidArgumentException('CssFormatter currently supports style rules, @page, @counter-style, @property, @position-try, @media, and @layer rules only');
                }

                $rules[] = $this->formatStyleRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
                $seenNonNamespaceRule = true;
                $cursor = $close + 1;
                continue;
            }

            $rules[] = $this->formatPageRule($prelude, substr($css, $open + 1, $close - $open - 1), 0);
            $seenNonNamespaceRule = true;
            $cursor = $close + 1;
        }

        return implode("\n\n", $rules) . "\n";
    }

    private function formatNamespaceRule(string $statement): string
    {
        if (preg_match('/^@namespace(?:\s+([_a-zA-Z-][_a-zA-Z0-9-]*))?\s+(.+)$/i', $statement, $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid @namespace rule');
        }

        $prefix = isset($matches[1]) && $matches[1] !== '' ? $matches[1] . ' ' : '';

        return '@namespace ' . $prefix . $this->formatNamespaceSource(trim($matches[2])) . ';';
    }

    private function formatNamespaceSource(string $source): string
    {
        if (preg_match('/^url\(\s*(.*?)\s*\)$/i', $source, $matches) === 1) {
            return $this->quoteCssString(trim($matches[1], " \t\n\r\0\x0B\"'"));
        }

        if ($this->isCssStringToken($source)) {
            return $this->quoteCssString(substr($source, 1, -1));
        }

        return $source;
    }

    private function formatPageRule(string $prelude, string $body, int $indentLevel): string
    {
        $items = $this->parsePageRuleItems($body);
        $indent = $this->indent($indentLevel);
        if ($items === []) {
            return $indent . $this->normalizePagePrelude($prelude) . ' {}';
        }

        $blocks = [];
        foreach ($items as $item) {
            if ($item['type'] === 'declarations') {
                $blocks[] = $this->formatDeclarations($item['body'], $indentLevel + 1);
                continue;
            }

            $blocks[] = $this->formatPageMarginRule($item['prelude'], $item['body'], $indentLevel + 1);
        }

        return $indent . $this->normalizePagePrelude($prelude) . " {\n"
            . implode("\n\n", $blocks) . "\n"
            . $indent . '}';
    }

    /**
     * @return list<array{type:'declarations', body:string}|array{type:'margin-rule', prelude:string, body:string}>
     */
    private function parsePageRuleItems(string $body): array
    {
        $items = [];
        $cursor = 0;
        $length = strlen($body);

        while (true) {
            $cursor = $this->skipWhitespace($body, $cursor);
            if ($cursor >= $length) {
                break;
            }

            if ($body[$cursor] === '@') {
                $open = $this->findNextTopLevel($body, '{', $cursor);
                if ($open === null) {
                    throw new \InvalidArgumentException('Invalid @page nested at-rule');
                }

                $prelude = trim(substr($body, $cursor, $open - $cursor));
                $name = $this->pageMarginAtRuleName($prelude);
                if ($name === null || !$this->isPageMarginAtRuleName($name)) {
                    throw new \InvalidArgumentException('Invalid @page nested at-rule: ' . ($name ?? $prelude));
                }

                $close = $this->findMatchingBrace($body, $open);
                $nestedBody = substr($body, $open + 1, $close - $open - 1);
                $nestedAt = $this->findNextTopLevelAtKeyword($nestedBody, 0);
                if ($nestedAt !== null) {
                    $nestedOpen = $this->findNextTopLevel($nestedBody, '{', $nestedAt);
                    $nestedPrelude = $nestedOpen === null
                        ? trim(substr($nestedBody, $nestedAt))
                        : trim(substr($nestedBody, $nestedAt, $nestedOpen - $nestedAt));
                    $nestedName = $this->pageMarginAtRuleName($nestedPrelude);
                    throw new \InvalidArgumentException('Invalid @page nested at-rule: ' . ($nestedName ?? $nestedPrelude));
                }

                $items[] = [
                    'type' => 'margin-rule',
                    'prelude' => '@' . $name,
                    'body' => $nestedBody,
                ];
                $cursor = $close + 1;
                continue;
            }

            $nextAt = $this->findNextTopLevelAtKeyword($body, $cursor);
            $end = $nextAt ?? $length;
            $declarations = trim(substr($body, $cursor, $end - $cursor));
            if ($declarations !== '') {
                $items[] = [
                    'type' => 'declarations',
                    'body' => $declarations,
                ];
            }
            $cursor = $end;
        }

        return $items;
    }

    private function formatPageMarginRule(string $prelude, string $body, int $indentLevel): string
    {
        return $this->indent($indentLevel) . $prelude . " {\n"
            . $this->formatDeclarations($body, $indentLevel + 1) . "\n"
            . $this->indent($indentLevel) . '}';
    }

    private function formatCounterStyleRule(string $prelude, string $body, int $indentLevel): string
    {
        $nestedAt = $this->findNextTopLevelAtKeyword($body, 0);
        if ($nestedAt !== null) {
            throw new \InvalidArgumentException('@counter-style rules only allow declarations');
        }

        $indent = $this->indent($indentLevel);
        $body = trim($body);
        if ($body === '') {
            return $indent . $this->normalizeCounterStylePrelude($prelude) . ' {}';
        }

        return $indent . $this->normalizeCounterStylePrelude($prelude) . " {\n"
            . $this->formatDeclarations($body, $indentLevel + 1) . "\n"
            . $indent . '}';
    }

    private function formatPropertyRule(string $prelude, string $body, int $indentLevel): string
    {
        $name = $this->propertyRuleName($prelude);
        $indent = $this->indent($indentLevel);
        $body = trim($body);
        if ($body === '') {
            return $indent . '@property ' . $name . ' {}';
        }

        return $indent . '@property ' . $name . " {\n"
            . $this->formatPropertyDeclarations($body, $indentLevel + 1) . "\n"
            . $indent . '}';
    }

    private function formatPositionTryRule(string $prelude, string $body, int $indentLevel): string
    {
        $name = $this->positionTryRuleName($prelude);
        $indent = $this->indent($indentLevel);
        $body = trim($body);
        if ($body === '') {
            return $indent . '@position-try ' . $name . ' {}';
        }

        return $indent . '@position-try ' . $name . " {\n"
            . $this->formatDeclarations($body, $indentLevel + 1) . "\n"
            . $indent . '}';
    }

    private function formatConditionalGroupRule(string $prelude, string $body, int $indentLevel): string
    {
        $items = $this->parseConditionalGroupItems($body, $indentLevel + 1);
        $indent = $this->indent($indentLevel);
        if ($items === []) {
            return $indent . $this->normalizeConditionalGroupPrelude($prelude) . ' {}';
        }

        return $indent . $this->normalizeConditionalGroupPrelude($prelude) . " {\n"
            . implode("\n\n", $items) . "\n"
            . $indent . '}';
    }

    /**
     * @return list<string>
     */
    private function parseConditionalGroupItems(string $body, int $indentLevel): array
    {
        $items = [];
        $cursor = 0;
        $length = strlen($body);

        while (true) {
            $cursor = $this->skipWhitespace($body, $cursor);
            if ($cursor >= $length) {
                break;
            }

            $open = $this->findNextTopLevel($body, '{', $cursor);
            if ($open === null) {
                throw new \InvalidArgumentException('Invalid nested rule in formatter group');
            }

            $prelude = trim(substr($body, $cursor, $open - $cursor));
            $close = $this->findMatchingBrace($body, $open);
            $nestedBody = substr($body, $open + 1, $close - $open - 1);
            if ($prelude !== '' && $prelude[0] !== '@') {
                $items[] = $this->formatStyleRule($prelude, $nestedBody, $indentLevel);
            } elseif ($this->isPropertyRulePrelude($prelude)) {
                $items[] = $this->formatPropertyRule($prelude, $nestedBody, $indentLevel);
            } elseif ($this->isPositionTryRulePrelude($prelude)) {
                $items[] = $this->formatPositionTryRule($prelude, $nestedBody, $indentLevel);
            } elseif ($this->isConditionalGroupPrelude($prelude)) {
                $items[] = $this->formatConditionalGroupRule($prelude, $nestedBody, $indentLevel);
            } else {
                throw new \InvalidArgumentException('Unsupported nested rule in formatter group: ' . $prelude);
            }

            $cursor = $close + 1;
        }

        return $items;
    }

    private function formatPropertyDeclarations(string $body, int $indentLevel): string
    {
        $declarations = $this->parseDeclarations($body);
        $hasInitialValue = false;
        foreach ($declarations as [$property]) {
            if ($property === 'initial-value') {
                $hasInitialValue = true;
                break;
            }
        }

        $lines = [];
        $last = count($declarations) - 1;
        foreach ($declarations as $index => [$property, $value]) {
            $suffix = (!$hasInitialValue && $index === $last) ? '' : ';';
            $lines[] = $this->indent($indentLevel)
                . $property . ': ' . $this->formatPropertyDeclarationValue($property, $value) . $suffix;
        }

        return implode("\n", $lines);
    }

    private function formatStyleRule(string $prelude, string $body, int $indentLevel): string
    {
        $selector = $this->formatStyleSelector($prelude);
        if ($selector === '') {
            throw new \InvalidArgumentException('Invalid empty style rule selector');
        }

        $indent = $this->indent($indentLevel);
        $declarations = $this->orderImportantStyleDeclarations(
            $this->composeGridStyleDeclarations(
                $this->composeOutlineStyleDeclarations(
                    $this->composeBorderImageStyleDeclarations(
                        $this->composeBorderStyleDeclarations(
                            $this->composeBoxModelStyleDeclarations(
                                $this->composeBackgroundStyleDeclarations(
                                    $this->composeFontStyleDeclarations($this->parseDeclarations($body))
                                )
                            )
                        )
                    )
                )
            )
        );
        if ($declarations === []) {
            return $indent . $selector . ' {}';
        }

        $lines = [];
        foreach ($declarations as [$property, $value]) {
            $lines[] = $this->formatStyleDeclaration($property, $value, $indentLevel + 1);
        }

        return $indent . $selector . " {\n"
            . implode("\n", $lines) . "\n"
            . $indent . '}';
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function orderImportantStyleDeclarations(array $declarations): array
    {
        $normal = [];
        $important = [];
        foreach ($declarations as $declaration) {
            if ($this->isImportantDeclarationValue($declaration[1])) {
                $important[] = $declaration;
                continue;
            }

            $normal[] = $declaration;
        }

        if ($normal === [] || $important === []) {
            return $declarations;
        }

        return array_merge($normal, $important);
    }

    private function isImportantDeclarationValue(string $value): bool
    {
        return preg_match('/!\s*important\s*$/i', trim($value)) === 1;
    }

    private function formatStyleDeclaration(string $property, string $value, int $indentLevel): string
    {
        $indent = $this->indent($indentLevel);
        $prefix = $indent . $property . ': ';
        $formatted = match ($property) {
            'color' => $this->formatColorDeclarationValue($value),
            'font' => $this->formatFontShorthandValue($value),
            'background' => $this->formatBackgroundShorthandValue($value),
            'background-color' => $this->formatColorDeclarationValue($value),
            'background-image' => $this->formatBackgroundImageDeclarationValue($value),
            'grid', 'grid-template' => $this->formatGridTemplateDeclarationValue($property, $value, strlen($prefix)),
            'outline' => $this->formatOutlineShorthandValue($value),
            'outline-color' => $this->formatColorDeclarationValue($value),
            'border' => $this->formatBorderShorthandValue($value),
            'transform', '-webkit-transform', '-moz-transform', '-ms-transform', '-o-transform' => $this->formatTransformDeclarationValue($value),
            default => $this->formatDeclarationValue($value),
        };

        return $prefix . $formatted . ';';
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeFontStyleDeclarations(array $declarations): array
    {
        $output = [];
        $skip = [];
        $count = count($declarations);

        for ($i = 0; $i < $count; $i++) {
            if (isset($skip[$i])) {
                continue;
            }

            [$property, $value] = $declarations[$i];
            if ($property === 'font') {
                $lineHeightIndex = $this->nextLineHeightIndex($declarations, $i + 1, $skip);
                if ($lineHeightIndex !== null && $this->canFoldFontLineHeight($declarations[$lineHeightIndex][1])) {
                    $output[] = ['font', $this->formatFontShorthandValue($value, $declarations[$lineHeightIndex][1])];
                    $skip[$lineHeightIndex] = true;
                    continue;
                }

                $output[] = ['font', $this->formatFontShorthandValue($value)];
                continue;
            }

            if ($this->isFontLonghand($property)) {
                $collected = $this->collectContiguousFontLonghands($declarations, $i, $skip);
                $font = $this->composeFontLonghands($collected['values']);
                if ($font !== null) {
                    $output[] = ['font', $font['value']];
                    foreach ($font['consumed'] as $consumedProperty) {
                        foreach ($collected['indexes'][$consumedProperty] ?? [] as $index) {
                            $skip[$index] = true;
                        }
                    }
                    continue;
                }
            }

            $output[] = [$property, $value];
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @param array<int, bool> $skip
     * @return array{values:array<string, string>, indexes:array<string, list<int>>}
     */
    private function collectContiguousFontLonghands(array $declarations, int $start, array $skip): array
    {
        $values = [];
        $indexes = [];
        $count = count($declarations);
        for ($i = $start; $i < $count; $i++) {
            if (isset($skip[$i])) {
                continue;
            }

            [$property, $value] = $declarations[$i];
            if (!$this->isFontLonghand($property)) {
                break;
            }

            $values[$property] = $value;
            $indexes[$property][] = $i;
        }

        return ['values' => $values, 'indexes' => $indexes];
    }

    private function isFontLonghand(string $property): bool
    {
        return in_array($property, self::FONT_LONGHANDS, true);
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBackgroundStyleDeclarations(array $declarations): array
    {
        $output = [];
        $count = count($declarations);

        for ($index = 0; $index < $count;) {
            if (!$this->isBackgroundDeclaration($declarations[$index][0])) {
                $output[] = $declarations[$index];
                $index++;
                continue;
            }

            $run = [];
            while ($index < $count && $this->isBackgroundDeclaration($declarations[$index][0])) {
                $run[] = $declarations[$index];
                $index++;
            }

            $background = $this->composeBackgroundDeclarationRun($run);
            if ($background !== null) {
                $output[] = ['background', $background];
                continue;
            }

            foreach ($run as $declaration) {
                $output[] = $declaration;
            }
        }

        return $output;
    }

    private function isBackgroundDeclaration(string $property): bool
    {
        return $property === 'background' || in_array($property, self::BACKGROUND_LONGHANDS, true);
    }

    /**
     * @param non-empty-list<array{string, string}> $run
     */
    private function composeBackgroundDeclarationRun(array $run): ?string
    {
        if (count($run) < 2) {
            return null;
        }

        $components = [];
        $hasShorthand = false;
        foreach ($run as [$property, $value]) {
            if ($this->isImportantDeclarationValue($value) || preg_match('/\bvar\s*\(/i', $value) === 1) {
                return null;
            }

            if ($property === 'background') {
                $parsed = $this->backgroundComponentsFromShorthand($value, true);
                if ($parsed === null) {
                    return null;
                }

                $components = $parsed;
                $hasShorthand = true;
                continue;
            }

            $components[$property] = $this->normalizeBackgroundComponentValue($property, $value);
            if ($property === 'background-position') {
                [$x, $y] = $this->splitSingleBackgroundPosition($components[$property]);
                if ($x !== null) {
                    $components['background-position-x'] = $x;
                }
                if ($y !== null) {
                    $components['background-position-y'] = $y;
                }
            }
        }

        $layerCount = $this->backgroundLayerCountFromComponents($components);
        if ($layerCount < 1) {
            return null;
        }

        if ($hasShorthand) {
            if (!$this->backgroundComponentLayerCountsFit($components, $layerCount)) {
                return null;
            }
        } elseif (!$this->backgroundLonghandsAreComplete($components)) {
            return null;
        }

        return $this->composeBackgroundValue($components, $layerCount);
    }

    /**
     * @return array<string, string>|null
     */
    private function backgroundComponentsFromShorthand(string $value, bool $includeInitialValues): ?array
    {
        $layers = $this->parseBackgroundLayers($value);
        if ($layers === null) {
            return null;
        }

        $components = ['background' => $this->formatDeclarationValue($value)];
        foreach (self::BACKGROUND_LONGHANDS as $property) {
            $longhand = $this->backgroundLonghandFromLayers($layers, $property, $includeInitialValues);
            if ($longhand !== null) {
                $components[$property] = $longhand;
            }
        }

        return $components;
    }

    private function normalizeBackgroundComponentValue(string $property, string $value): string
    {
        return match ($property) {
            'background-color' => $this->formatColorDeclarationValue($value),
            'background-image' => $this->formatBackgroundImageDeclarationValue($value),
            'background-size' => $this->normalizeBackgroundSizeDeclarationValue($value),
            'background-repeat' => $this->normalizeBackgroundRepeatDeclarationValue($value),
            'background-attachment' => $this->normalizeBackgroundKeywordList($value, ['scroll', 'fixed', 'local']),
            'background-origin' => $this->normalizeBackgroundKeywordList($value, ['border-box', 'padding-box', 'content-box']),
            'background-clip' => $this->normalizeBackgroundKeywordList($value, ['border-box', 'padding-box', 'content-box', 'border', 'text']),
            default => $this->formatDeclarationValue($value),
        };
    }

    /**
     * @param non-empty-list<array{
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
     * }> $layers
     */
    private function backgroundLonghandFromLayers(array $layers, string $property, bool $includeInitialValues): ?string
    {
        if ($property === 'background-color') {
            return $layers[array_key_last($layers)]['color'] ?? ($includeInitialValues ? '#0000' : null);
        }

        $values = [];
        foreach ($layers as $layer) {
            $value = match ($property) {
                'background-image' => $layer['image'] ?? ($includeInitialValues ? 'none' : null),
                'background-position' => $layer['position'] ?? ($includeInitialValues ? '0 0' : null),
                'background-position-x' => $layer['positionX'] ?? ($includeInitialValues ? '0' : null),
                'background-position-y' => $layer['positionY'] ?? ($includeInitialValues ? '0' : null),
                'background-size' => $layer['size'] ?? ($includeInitialValues ? 'auto' : null),
                'background-repeat' => $layer['repeat'] ?? ($includeInitialValues ? 'repeat' : null),
                'background-attachment' => $layer['attachment'] ?? ($includeInitialValues ? 'scroll' : null),
                'background-origin' => $layer['origin'] ?? ($includeInitialValues ? 'padding-box' : null),
                'background-clip' => $layer['clip'] ?? ($includeInitialValues ? 'border-box' : null),
                default => null,
            };
            if ($value === null) {
                return null;
            }

            $values[] = $value;
        }

        return implode(', ', $values);
    }

    /**
     * @param array<string, string> $components
     */
    private function backgroundLonghandsAreComplete(array $components): bool
    {
        foreach ([
            'background-color',
            'background-image',
            'background-size',
            'background-repeat',
            'background-attachment',
            'background-origin',
            'background-clip',
        ] as $property) {
            if (!isset($components[$property])) {
                return false;
            }
        }

        return isset($components['background-position'])
            || (isset($components['background-position-x']) && isset($components['background-position-y']));
    }

    /**
     * @param array<string, string> $components
     */
    private function backgroundLayerCountFromComponents(array $components): int
    {
        $count = 1;
        foreach (self::BACKGROUND_LONGHANDS as $property) {
            if (!isset($components[$property]) || $property === 'background-color') {
                continue;
            }

            $count = max($count, count($this->splitTopLevel($components[$property], ',')));
        }

        return $count;
    }

    /**
     * @param array<string, string> $components
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
            if (isset($components[$property]) && count($this->splitTopLevel($components[$property], ',')) > $layerCount) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $components
     */
    private function composeBackgroundValue(array $components, int $layerCount): ?string
    {
        $images = $this->componentList($components['background-image'] ?? null, $layerCount);
        $positions = $this->componentList($components['background-position'] ?? null, $layerCount);
        $positionX = $this->componentList($components['background-position-x'] ?? null, $layerCount);
        $positionY = $this->componentList($components['background-position-y'] ?? null, $layerCount);
        $sizes = $this->componentList($components['background-size'] ?? null, $layerCount);
        $repeats = $this->componentList($components['background-repeat'] ?? null, $layerCount);
        $attachments = $this->componentList($components['background-attachment'] ?? null, $layerCount);
        $origins = $this->componentList($components['background-origin'] ?? null, $layerCount);
        $clips = $this->componentList($components['background-clip'] ?? null, $layerCount);
        $color = $components['background-color'] ?? null;
        $result = [];

        for ($i = 0; $i < $layerCount; $i++) {
            $layer = [];
            $image = $images[$i] ?? null;
            if ($color !== null && $i === $layerCount - 1 && !$this->isTransparentBackgroundColor($color)) {
                $layer[] = $color;
            }
            if (!$this->isDefaultBackgroundImage($image)) {
                $layer[] = $image;
            }

            $position = null;
            if (($positionX[$i] ?? null) !== null || ($positionY[$i] ?? null) !== null) {
                $position = trim(($positionX[$i] ?? '0') . ' ' . ($positionY[$i] ?? '0'));
            } else {
                $position = $positions[$i] ?? null;
            }
            $position = $position === null ? null : $this->serializeBackgroundPosition($position);

            $size = isset($sizes[$i]) ? $this->serializeBackgroundSize($sizes[$i]) : null;
            if ($position === null && $size !== null && !$this->isDefaultBackgroundSize($size)) {
                $position = '0 0';
            }
            if ($position !== null && (!$this->isDefaultBackgroundPosition($position) || ($size !== null && !$this->isDefaultBackgroundSize($size)))) {
                $layer[] = $position;
            }
            if ($size !== null && !$this->isDefaultBackgroundSize($size)) {
                $layer[] = '/';
                $layer[] = $size;
            }
            if (($repeats[$i] ?? null) !== null && !$this->isDefaultBackgroundRepeat($repeats[$i])) {
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
                if ($origin === $clip && !$this->isDefaultBackgroundOrigin($origin)) {
                    $layer[] = $origin;
                } elseif (!$this->isDefaultBackgroundOrigin($origin) || !$this->isDefaultBackgroundClip($clip)) {
                    $layer[] = $origin;
                    $layer[] = $clip;
                }
            }

            $serialized = implode(' ', array_values(array_filter($layer, static fn (string $part): bool => $part !== '')));
            $result[] = $serialized === '' ? '0 0' : $serialized;
        }

        return $result === [] ? null : implode(', ', $result);
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

    /**
     * @return non-empty-list<array{
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
     * }>|null
     */
    private function parseBackgroundLayers(string $value): ?array
    {
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $parsed = $this->parseBackgroundLayer($layer);
            if ($parsed === null) {
                return null;
            }

            $layers[] = $parsed;
        }

        return $layers === [] ? null : $layers;
    }

    /**
     * @return array{
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
     * }|null
     */
    private function parseBackgroundLayer(string $layer): ?array
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($layer));
        $parsed = [
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
                [$size, $nextIndex] = $this->consumeBackgroundSizeAfterSlash($tokens, $i + 1);
                if ($size === null) {
                    return null;
                }

                $parsed['size'] = $size;
                $i = $nextIndex - 1;
                continue;
            }

            if ($this->isBackgroundImageToken($token)) {
                $parsed['image'] = $this->normalizeCssUrlToken($token);
                continue;
            }

            if ($this->isBackgroundRepeatToken($lower)) {
                $parsed['repeat'] = $this->consumeBackgroundRepeat($tokens, $i);
                continue;
            }

            if ($this->isBackgroundAttachmentToken($lower)) {
                $parsed['attachment'] = $lower;
                continue;
            }

            if ($this->isBackgroundBoxToken($lower)) {
                if ($parsed['origin'] === null) {
                    $parsed['origin'] = $lower;
                } elseif ($parsed['clip'] === null) {
                    $parsed['clip'] = $lower;
                } else {
                    return null;
                }
                continue;
            }

            if ($this->isBackgroundColorToken($token)) {
                $parsed['color'] = $this->formatColorDeclarationValue($token);
                continue;
            }

            $positionTokens[] = $this->formatDeclarationValue($token);
        }

        if ($parsed['clip'] === null && $parsed['origin'] !== null) {
            $parsed['clip'] = $parsed['origin'];
        }
        if ($positionTokens !== []) {
            $parsed['position'] = implode(' ', $positionTokens);
            [$parsed['positionX'], $parsed['positionY']] = $this->splitSingleBackgroundPosition($parsed['position']);
        }

        return $parsed;
    }

    /**
     * @param list<string> $tokens
     * @return array{0:?string,1:int}
     */
    private function consumeBackgroundSizeAfterSlash(array $tokens, int $startIndex): array
    {
        if (!isset($tokens[$startIndex])) {
            return [null, $startIndex];
        }

        $sizeTokens = [$tokens[$startIndex]];
        $index = $startIndex + 1;
        if (
            isset($tokens[$index])
            && count($sizeTokens) < 2
            && $this->isBackgroundSizeComponentToken($tokens[$index])
        ) {
            $sizeTokens[] = $tokens[$index];
            $index++;
        }

        return [$this->normalizeBackgroundSizeLayer(implode(' ', $sizeTokens)), $index];
    }

    private function isBackgroundSizeComponentToken(string $token): bool
    {
        $lower = strtolower($token);
        if (in_array($lower, ['cover', 'contain', 'auto'], true)) {
            return true;
        }
        if ($this->isBackgroundRepeatToken($lower)
            || $this->isBackgroundAttachmentToken($lower)
            || $this->isBackgroundBoxToken($lower)
            || $this->isBackgroundColorToken($token)
            || $this->isBackgroundImageToken($token)
        ) {
            return false;
        }

        return true;
    }

    private function isBackgroundImageToken(string $token): bool
    {
        return strcasecmp($token, 'none') === 0
            || preg_match('/^(?:url|[-_a-zA-Z][-_a-zA-Z0-9]*-gradient|image|cross-fade|image-set)\(/i', $token) === 1;
    }

    private function isBackgroundColorToken(string $token): bool
    {
        $lower = strtolower($token);
        if (in_array($lower, [
            'left',
            'right',
            'top',
            'bottom',
            'center',
            'scroll',
            'fixed',
            'local',
            'border-box',
            'padding-box',
            'content-box',
            'cover',
            'contain',
            'none',
            'repeat',
            'no-repeat',
            'round',
            'space',
        ], true)) {
            return false;
        }

        return preg_match('/^(?:#[0-9a-fA-F]{3,8}|(?:rgb|rgba|hsl|hsla|color)\(|[a-zA-Z]+)$/', $token) === 1;
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

    private function formatBackgroundShorthandValue(string $value): string
    {
        if ($this->isImportantDeclarationValue($value) || preg_match('/\bvar\s*\(/i', $value) === 1) {
            return $this->formatDeclarationValue($value);
        }

        $components = $this->backgroundComponentsFromShorthand($value, true);
        if ($components === null) {
            return $this->formatDeclarationValue($value);
        }

        return $this->composeBackgroundValue(
            $components,
            $this->backgroundLayerCountFromComponents($components)
        ) ?? $this->formatDeclarationValue($value);
    }

    private function formatBackgroundImageDeclarationValue(string $value): string
    {
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $part = trim($part);
            if ($part === '') {
                return $this->formatDeclarationValue($value);
            }

            $parts[] = strcasecmp($part, 'none') === 0 ? 'none' : $this->normalizeCssUrlToken($part);
        }

        return implode(', ', $parts);
    }

    private function normalizeBackgroundSizeDeclarationValue(string $value): string
    {
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $normalized = $this->normalizeBackgroundSizeLayer($part);
            if ($normalized === null) {
                return $this->formatDeclarationValue($value);
            }

            $parts[] = $normalized;
        }

        return implode(', ', $parts);
    }

    private function normalizeBackgroundSizeLayer(string $layer): ?string
    {
        $tokens = $this->splitWhitespaceTopLevel(trim($layer));
        if ($tokens === [] || count($tokens) > 2) {
            return null;
        }

        $tokens = array_map(fn (string $token): string => $this->formatDeclarationValue($token), $tokens);
        if (count($tokens) === 1) {
            return strtolower($tokens[0]) === 'auto' ? 'auto' : $tokens[0];
        }

        return strtolower($tokens[1]) === 'auto' ? $tokens[0] : $tokens[0] . ' ' . $tokens[1];
    }

    private function normalizeBackgroundRepeatDeclarationValue(string $value): string
    {
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $tokens = $this->splitWhitespaceTopLevel(trim($part));
            if ($tokens === [] || count($tokens) > 2) {
                return $this->formatDeclarationValue($value);
            }

            $tokens = array_map(static fn (string $token): string => strtolower($token), $tokens);
            foreach ($tokens as $token) {
                if (!$this->isBackgroundRepeatToken($token)) {
                    return $this->formatDeclarationValue($value);
                }
            }

            $parts[] = $this->compressBackgroundRepeat(implode(' ', $tokens));
        }

        return implode(', ', $parts);
    }

    /**
     * @param list<string> $allowed
     */
    private function normalizeBackgroundKeywordList(string $value, array $allowed): string
    {
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $part = strtolower(trim($part));
            if (!in_array($part, $allowed, true)) {
                return $this->formatDeclarationValue($value);
            }

            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    private function compressBackgroundRepeat(string $repeat): string
    {
        return match (strtolower(trim($repeat))) {
            'repeat no-repeat' => 'repeat-x',
            'no-repeat repeat' => 'repeat-y',
            'repeat repeat' => 'repeat',
            default => strtolower(trim($repeat)),
        };
    }

    private function serializeBackgroundPosition(string $position): string
    {
        $position = $this->formatDeclarationValue($position);
        $tokens = $this->splitWhitespaceTopLevel($position);
        if (count($tokens) === 2 && in_array(strtolower($tokens[1]), ['50%', 'center'], true)) {
            return $tokens[0];
        }

        return $position;
    }

    private function serializeBackgroundSize(string $size): string
    {
        return $this->normalizeBackgroundSizeLayer($size) ?? $this->formatDeclarationValue($size);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitSingleBackgroundPosition(string $value): array
    {
        if (count($this->splitTopLevel($value, ',')) !== 1) {
            return [null, null];
        }

        $tokens = $this->splitWhitespaceTopLevel(trim($value));
        if ($tokens === []) {
            return [null, null];
        }

        if (count($tokens) === 1) {
            return [$tokens[0], 'center'];
        }

        return [$tokens[0], implode(' ', array_slice($tokens, 1))];
    }

    private function isDefaultBackgroundImage(?string $image): bool
    {
        return $image === null || strcasecmp(trim($image), 'none') === 0;
    }

    private function isDefaultBackgroundPosition(string $position): bool
    {
        return in_array(strtolower(trim($position)), ['0', '0 0', '0% 0%', 'left top'], true);
    }

    private function isDefaultBackgroundSize(string $size): bool
    {
        return in_array(strtolower(trim($size)), ['auto', 'auto auto'], true);
    }

    private function isDefaultBackgroundRepeat(string $repeat): bool
    {
        return in_array(strtolower(trim($repeat)), ['repeat', 'repeat repeat'], true);
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

    private function isTransparentBackgroundColor(string $color): bool
    {
        return in_array(strtolower(trim($color)), ['transparent', '#0000', 'rgba(0,0,0,0)', 'rgb(0 0 0 / 0)'], true);
    }

    private function normalizeCssUrlToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^url\(\s*(.*?)\s*\)$/is', $token, $matches) !== 1) {
            return $this->formatDeclarationValue($token);
        }

        $content = trim($matches[1]);
        if ($this->isCssStringToken($content)) {
            $content = substr($content, 1, -1);
        }

        if ($content === '' || preg_match('/[\s()\\\\]/', $content) === 1) {
            return 'url(' . $content . ')';
        }

        return 'url(' . $this->quoteCssString($content) . ')';
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBoxModelStyleDeclarations(array $declarations): array
    {
        foreach (['margin', 'padding'] as $base) {
            $declarations = $this->composeBoxModelShorthandOverrides($declarations, $base);
            $declarations = $this->composeBoxModelLogicalPairs($declarations, $base);
            $declarations = $this->composeBoxModelPhysicalLonghands($declarations, $base);
        }

        return $declarations;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeOutlineStyleDeclarations(array $declarations): array
    {
        $declarations = $this->composeOutlineLonghands($declarations);
        $declarations = $this->composeOutlineColorOverrides($declarations);

        return $declarations;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeOutlineLonghands(array $declarations): array
    {
        if ($this->containsDeclaration($declarations, 'outline')) {
            return $declarations;
        }

        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            foreach (self::OUTLINE_COMPONENTS as $component) {
                if ($property === 'outline-' . $component) {
                    $indexes[$component] = $index;
                }
            }
        }

        foreach (self::OUTLINE_COMPONENTS as $component) {
            if (!isset($indexes[$component])) {
                return $declarations;
            }
        }

        $components = [];
        foreach (self::OUTLINE_COMPONENTS as $component) {
            $value = $this->formatOutlineComponentValue($component, $declarations[$indexes[$component]][1]);
            if (!$this->canComposeOutlineValue($value)) {
                return $declarations;
            }

            $components[$component] = $value;
        }

        $replaceAt = min($indexes);

        return $this->replaceDeclarations($declarations, [
            $replaceAt => ['outline', $this->serializeOutlineComponents($components)],
        ], array_flip(array_values($indexes)));
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeOutlineColorOverrides(array $declarations): array
    {
        $replacements = [];
        $skip = [];
        $count = count($declarations);

        for ($i = 0; $i < $count; $i++) {
            if ($declarations[$i][0] !== 'outline') {
                continue;
            }

            $components = $this->parseOutlineShorthandComponents($declarations[$i][1]);
            if ($components === null) {
                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                [$property, $value] = $declarations[$j];
                if ($property === 'outline') {
                    break;
                }

                if (!str_starts_with($property, 'outline-')) {
                    continue;
                }

                if ($property !== 'outline-color') {
                    break;
                }

                $formattedColor = $this->formatColorDeclarationValue($value);
                if (!$this->canComposeOutlineValue($formattedColor)) {
                    continue;
                }

                $components['color'] = $formattedColor;
                $replacements[$i] = ['outline', $this->serializeOutlineComponents($components)];
                $skip[$j] = true;
                break;
            }
        }

        return $this->replaceDeclarations($declarations, $replacements, $skip);
    }

    private function formatOutlineComponentValue(string $component, string $value): string
    {
        if ($component === 'color') {
            return $this->formatColorDeclarationValue($value);
        }

        return $this->formatDeclarationValue($value);
    }

    private function formatOutlineShorthandValue(string $value): string
    {
        $components = $this->parseOutlineShorthandComponents($value);
        if ($components === null) {
            return $this->formatDeclarationValue($value);
        }

        return $this->serializeOutlineComponents($components);
    }

    /**
     * @return array{width:string, style:string, color:string}|null
     */
    private function parseOutlineShorthandComponents(string $value): ?array
    {
        if (!$this->canComposeOutlineValue($value)) {
            return null;
        }

        $components = [];
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $formatted = $this->formatDeclarationValue($token);
            $lower = strtolower($formatted);
            if (!isset($components['style']) && in_array($lower, self::OUTLINE_STYLES, true)) {
                $components['style'] = $lower;
                continue;
            }

            if (!isset($components['width']) && $this->isBorderWidthToken($formatted)) {
                $components['width'] = $formatted;
                continue;
            }

            if (!isset($components['color'])) {
                $components['color'] = $this->formatColorDeclarationValue($token);
                continue;
            }

            return null;
        }

        foreach (self::OUTLINE_COMPONENTS as $component) {
            if (!isset($components[$component])) {
                return null;
            }
        }

        return [
            'width' => $components['width'],
            'style' => $components['style'],
            'color' => $components['color'],
        ];
    }

    /**
     * @param array{width:string, style:string, color:string} $components
     */
    private function serializeOutlineComponents(array $components): string
    {
        return $components['width'] . ' ' . $components['style'] . ' ' . $components['color'];
    }

    private function canComposeOutlineValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/!\s*important\b|\bvar\s*\(/i', $value) !== 1;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBorderStyleDeclarations(array $declarations): array
    {
        $declarations = $this->dropBorderDeclarationsResetByLaterShorthand($declarations);
        $declarations = $this->composeEqualPhysicalBorderGroup($declarations, 'border', '');
        $declarations = $this->composePhysicalBorderSideShorthandGroup($declarations);
        foreach (self::BORDER_COMPONENTS as $component) {
            $declarations = $this->composeEqualPhysicalBorderGroup(
                $declarations,
                'border-' . $component,
                '-' . $component,
                $component
            );
        }

        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            $declarations = $this->composePhysicalBorderSideComponents($declarations, $side);
        }

        $declarations = $this->composeLogicalBorderSideComponents($declarations);
        $declarations = $this->composeLogicalBorderSideShorthandGroup($declarations);
        $declarations = $this->composeEqualLogicalBorderAxisSides($declarations);
        $declarations = $this->composeLogicalBorderWidthPairs($declarations);
        $declarations = $this->composeEqualLogicalBorderWidthAxes($declarations);
        $declarations = $this->composeBorderSideOverridesAgainstShorthand($declarations);

        return $declarations;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function dropBorderDeclarationsResetByLaterShorthand(array $declarations): array
    {
        $skip = [];
        foreach ($declarations as $index => [$property, $value]) {
            if ($property !== 'border' && !$this->isPhysicalBorderSideShorthand($property)) {
                continue;
            }

            $side = $this->physicalBorderSideFromShorthand($property);
            $important = $this->isImportantDeclarationValue($value);
            for ($previous = 0; $previous < $index; $previous++) {
                if (isset($skip[$previous])) {
                    continue;
                }

                if ($this->isImportantDeclarationValue($declarations[$previous][1]) && !$important) {
                    continue;
                }

                if ($side === null && $this->isPhysicalBorderProperty($declarations[$previous][0])) {
                    $skip[$previous] = true;
                    continue;
                }

                if ($side !== null && $this->isPhysicalBorderSideProperty($declarations[$previous][0], $side)) {
                    $skip[$previous] = true;
                }
            }
        }

        return $this->replaceDeclarations($declarations, [], $skip);
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeEqualPhysicalBorderGroup(
        array $declarations,
        string $replacementProperty,
        string $propertySuffix,
        ?string $component = null
    ): array {
        if ($this->containsLogicalBorderDeclaration($declarations)
            || $this->containsDeclaration($declarations, $replacementProperty)
        ) {
            return $declarations;
        }

        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            foreach (self::BORDER_PHYSICAL_SIDES as $side) {
                if ($property === 'border-' . $side . $propertySuffix) {
                    $indexes[$side] = $index;
                }
            }
        }

        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            if (!isset($indexes[$side])) {
                return $declarations;
            }
        }

        $values = [];
        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            $value = $this->formatBorderComponentValue($component, $declarations[$indexes[$side]][1]);
            if (!$this->canComposeBorderValue($value)) {
                return $declarations;
            }

            $values[$side] = $value;
        }

        if (!$this->allValuesEqual($values)) {
            return $declarations;
        }

        $replaceAt = min($indexes);

        return $this->replaceDeclarations($declarations, [
            $replaceAt => [$replacementProperty, $values['top']],
        ], array_flip(array_values($indexes)));
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composePhysicalBorderSideComponents(array $declarations, string $side): array
    {
        $shorthand = 'border-' . $side;
        if ($this->containsDeclaration($declarations, $shorthand)) {
            return $declarations;
        }

        $skip = [];
        $replacements = [];
        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            foreach (self::BORDER_COMPONENTS as $component) {
                if ($property === $shorthand . '-' . $component) {
                    $indexes[$component] = $index;
                }
            }
        }

        foreach (self::BORDER_COMPONENTS as $component) {
            if (!isset($indexes[$component])) {
                return $declarations;
            }
        }

        $values = [];
        foreach (self::BORDER_COMPONENTS as $component) {
            $value = $this->formatBorderComponentValue($component, $declarations[$indexes[$component]][1]);
            if (!$this->canComposeBorderValue($value)) {
                return $declarations;
            }

            $values[] = $value;
        }

        $replaceAt = min($indexes);
        $replacements[$replaceAt] = [$shorthand, implode(' ', $values)];
        foreach ($indexes as $index) {
            $skip[$index] = true;
        }

        return $this->replaceDeclarations($declarations, $replacements, $skip);
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composePhysicalBorderSideShorthandGroup(array $declarations): array
    {
        if ($this->containsLogicalBorderDeclaration($declarations)
            || $this->containsDeclaration($declarations, 'border')
        ) {
            return $declarations;
        }

        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            $side = $this->physicalBorderSideFromShorthand($property);
            if ($side !== null) {
                $indexes[$side] = $index;
            }
        }

        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            if (!isset($indexes[$side])) {
                return $declarations;
            }
        }

        $sideComponents = [];
        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            $components = $this->parseBorderShorthandComponents($declarations[$indexes[$side]][1]);
            if ($components === null) {
                return $declarations;
            }

            $sideComponents[$side] = $components;
        }

        $base = $sideComponents['top'];
        $diffComponents = [];
        foreach (self::BORDER_COMPONENTS as $component) {
            foreach (self::BORDER_PHYSICAL_SIDES as $side) {
                if (strcasecmp($base[$component], $sideComponents[$side][$component]) !== 0) {
                    $diffComponents[$component] = true;
                    break;
                }
            }
        }

        if (count($diffComponents) !== 1) {
            return $declarations;
        }

        $component = array_key_first($diffComponents);
        $componentSides = [];
        $diffSides = [];
        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            $value = $sideComponents[$side][$component];
            $componentSides[$side] = $value;
            if (strcasecmp($base[$component], $value) !== 0) {
                $diffSides[$side] = $value;
            }
        }

        $override = count($diffSides) === 1
            ? ['border-' . array_key_first($diffSides) . '-' . $component, reset($diffSides)]
            : ['border-' . $component, $this->compressBoxModelSides($componentSides)];
        $replaceAt = min($indexes);
        $skip = array_flip(array_values($indexes));
        $output = [];

        foreach ($declarations as $index => $declaration) {
            if ($index === $replaceAt) {
                $output[] = ['border', $this->serializeBorderComponents($base)];
                $output[] = $override;
                continue;
            }

            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $declaration;
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBorderSideOverridesAgainstShorthand(array $declarations): array
    {
        $replacements = [];
        $skip = [];
        $count = count($declarations);

        for ($i = 0; $i < $count; $i++) {
            if ($declarations[$i][0] !== 'border') {
                continue;
            }

            $base = $this->parseBorderShorthandComponents($declarations[$i][1]);
            if ($base === null) {
                continue;
            }

            $pending = [];
            for ($j = $i + 1; $j < $count; $j++) {
                [$property, $value] = $declarations[$j];
                if ($property === 'border' || $this->isLogicalBorderDeclaration($property)) {
                    break;
                }

                if ($this->isPhysicalBorderSideShorthand($property)) {
                    $side = $this->physicalBorderSideFromShorthand($property);
                    $components = $side === null ? null : $this->parseBorderShorthandComponents($value);
                    if ($side === null || $components === null) {
                        continue;
                    }

                    $replacement = $this->borderSideOverrideReplacement($side, $base, $components);
                    if ($replacement !== null) {
                        $replacements[$j] = $replacement;
                    }
                    continue;
                }

                $longhand = $this->physicalBorderSideLonghand($property);
                if ($longhand === null || !$this->canComposeBorderValue($value)) {
                    continue;
                }

                [$side, $component] = $longhand;
                $pending[$side]['indexes'][$component] = $j;
                $pending[$side]['components'][$component] = $this->formatBorderComponentValue($component, $value);
            }

            foreach ($pending as $side => $data) {
                if (count($data['components']) < 2) {
                    continue;
                }

                $components = array_merge($base, $data['components']);
                $replacement = $this->borderSideOverrideReplacement($side, $base, $components);
                if ($replacement === null) {
                    continue;
                }

                $replaceAt = min($data['indexes']);
                $replacements[$replaceAt] = $replacement;
                foreach ($data['indexes'] as $index) {
                    $skip[$index] = true;
                }
            }
        }

        return $this->replaceDeclarations($declarations, $replacements, $skip);
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeLogicalBorderSideComponents(array $declarations): array
    {
        foreach (self::BORDER_LOGICAL_SIDES as $side) {
            $shorthand = 'border-' . $side;
            if ($this->containsDeclaration($declarations, $shorthand)) {
                continue;
            }

            $indexes = [];
            foreach ($declarations as $index => [$property]) {
                foreach (self::BORDER_COMPONENTS as $component) {
                    if ($property === $shorthand . '-' . $component) {
                        $indexes[$component] = $index;
                    }
                }
            }

            foreach (self::BORDER_COMPONENTS as $component) {
                if (!isset($indexes[$component])) {
                    continue 2;
                }
            }

            $values = [];
            foreach (self::BORDER_COMPONENTS as $component) {
                $value = $this->formatBorderComponentValue($component, $declarations[$indexes[$component]][1]);
                if (!$this->canComposeBorderValue($value)) {
                    continue 2;
                }

                $values[$component] = $value;
            }

            $declarations = $this->replaceDeclarations(
                $declarations,
                [
                    min($indexes) => ['border-' . $side, $this->serializeBorderComponents($values)],
                ],
                array_flip(array_values($indexes))
            );
        }

        return $declarations;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeLogicalBorderSideShorthandGroup(array $declarations): array
    {
        if ($this->containsDeclaration($declarations, 'border')) {
            return $declarations;
        }

        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            foreach (self::BORDER_LOGICAL_SIDES as $side) {
                if ($property === 'border-' . $side) {
                    $indexes[$side] = $index;
                }
            }
        }

        foreach (self::BORDER_LOGICAL_SIDES as $side) {
            if (!isset($indexes[$side])) {
                return $declarations;
            }
        }

        $sideComponents = [];
        foreach (self::BORDER_LOGICAL_SIDES as $side) {
            $components = $this->parseBorderShorthandComponents($declarations[$indexes[$side]][1]);
            if ($components === null) {
                return $declarations;
            }

            $sideComponents[$side] = $components;
        }

        $bestBase = null;
        $bestOverrides = [];
        $bestSameSides = -1;
        $bestOverrideCount = PHP_INT_MAX;
        foreach (self::BORDER_LOGICAL_SIDES as $side) {
            $base = $sideComponents[$side];
            $sameSides = 0;
            foreach (self::BORDER_LOGICAL_SIDES as $candidateSide) {
                if ($this->borderComponentsEqual($base, $sideComponents[$candidateSide])) {
                    $sameSides++;
                }
            }

            $overrides = $this->logicalBorderOverridesForBase($sideComponents, $base);
            $overrideCount = count($overrides);
            if ($sameSides > $bestSameSides || ($sameSides === $bestSameSides && $overrideCount < $bestOverrideCount)) {
                $bestBase = $base;
                $bestOverrides = $overrides;
                $bestSameSides = $sameSides;
                $bestOverrideCount = $overrideCount;
            }
        }

        if ($bestBase === null || $bestOverrides === []) {
            return $declarations;
        }

        $replaceAt = min($indexes);
        $skip = array_flip(array_values($indexes));
        $output = [];
        foreach ($declarations as $index => $declaration) {
            if ($index === $replaceAt) {
                $output[] = ['border', $this->serializeBorderComponents($bestBase)];
                foreach ($bestOverrides as $override) {
                    $output[] = $override;
                }
                continue;
            }

            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $declaration;
        }

        return $output;
    }

    /**
     * @param array<string, array{width:string, style:string, color:string}> $sideComponents
     * @param array{width:string, style:string, color:string} $base
     * @return list<array{string, string}>
     */
    private function logicalBorderOverridesForBase(array $sideComponents, array $base): array
    {
        $overrides = [];
        foreach (self::BORDER_LOGICAL_AXIS_SIDES as $axis => $sides) {
            $startSide = $sides['start'];
            $endSide = $sides['end'];
            $start = $sideComponents[$startSide];
            $end = $sideComponents[$endSide];
            $startMatchesBase = $this->borderComponentsEqual($base, $start);
            $endMatchesBase = $this->borderComponentsEqual($base, $end);
            if ($startMatchesBase && $endMatchesBase) {
                continue;
            }

            if ($this->borderComponentsEqual($start, $end)) {
                $overrides[] = $this->logicalBorderOverrideReplacement('border-' . $axis, $base, $start);
                continue;
            }

            if (!$startMatchesBase) {
                $overrides[] = $this->logicalBorderOverrideReplacement('border-' . $startSide, $base, $start);
            }
            if (!$endMatchesBase) {
                $overrides[] = $this->logicalBorderOverrideReplacement('border-' . $endSide, $base, $end);
            }
        }

        return $overrides;
    }

    /**
     * @param array{width:string, style:string, color:string} $base
     * @param array{width:string, style:string, color:string} $components
     * @return array{string, string}
     */
    private function logicalBorderOverrideReplacement(string $property, array $base, array $components): array
    {
        $diff = [];
        foreach (self::BORDER_COMPONENTS as $component) {
            if (strcasecmp($base[$component], $components[$component]) !== 0) {
                $diff[] = $component;
            }
        }

        if (count($diff) === 1) {
            $component = $diff[0];
            return [$property . '-' . $component, $components[$component]];
        }

        return [$property, $this->serializeBorderComponents($components)];
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeEqualLogicalBorderAxisSides(array $declarations): array
    {
        foreach (self::BORDER_LOGICAL_AXIS_SIDES as $axis => $sides) {
            $shorthand = 'border-' . $axis;
            if ($this->containsDeclaration($declarations, $shorthand)) {
                continue;
            }

            $indexes = [];
            foreach ($declarations as $index => [$property]) {
                if ($property === 'border-' . $sides['start']) {
                    $indexes['start'] = $index;
                }
                if ($property === 'border-' . $sides['end']) {
                    $indexes['end'] = $index;
                }
            }

            if (!isset($indexes['start'], $indexes['end'])) {
                continue;
            }

            $start = $this->parseBorderShorthandComponents($declarations[$indexes['start']][1]);
            $end = $this->parseBorderShorthandComponents($declarations[$indexes['end']][1]);
            if ($start === null || $end === null || !$this->borderComponentsEqual($start, $end)) {
                continue;
            }

            $declarations = $this->replaceDeclarations(
                $declarations,
                [
                    min($indexes) => [$shorthand, $this->serializeBorderComponents($start)],
                ],
                array_flip(array_values($indexes))
            );
        }

        return $declarations;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeLogicalBorderWidthPairs(array $declarations): array
    {
        foreach (self::BORDER_LOGICAL_AXIS_SIDES as $axis => $sides) {
            $shorthand = 'border-' . $axis . '-width';
            if ($this->containsDeclaration($declarations, $shorthand)) {
                continue;
            }

            $indexes = [];
            foreach ($declarations as $index => [$property]) {
                if ($property === 'border-' . $sides['start'] . '-width') {
                    $indexes['start'] = $index;
                }
                if ($property === 'border-' . $sides['end'] . '-width') {
                    $indexes['end'] = $index;
                }
            }

            if (!isset($indexes['start'], $indexes['end'])) {
                continue;
            }

            $start = $this->formatBorderComponentValue('width', $declarations[$indexes['start']][1]);
            $end = $this->formatBorderComponentValue('width', $declarations[$indexes['end']][1]);
            if (!$this->canComposeBorderValue($start) || !$this->canComposeBorderValue($end)) {
                continue;
            }

            $declarations = $this->replaceDeclarations(
                $declarations,
                [
                    min($indexes) => [
                        $shorthand,
                        strcasecmp($start, $end) === 0 ? $start : $start . ' ' . $end,
                    ],
                ],
                array_flip(array_values($indexes))
            );
        }

        return $declarations;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeEqualLogicalBorderWidthAxes(array $declarations): array
    {
        if ($this->containsDeclaration($declarations, 'border-width')) {
            return $declarations;
        }

        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            if ($property === 'border-block-width') {
                $indexes['block'] = $index;
            }
            if ($property === 'border-inline-width') {
                $indexes['inline'] = $index;
            }
        }

        if (!isset($indexes['block'], $indexes['inline'])) {
            return $declarations;
        }

        $block = $this->expandLogicalBorderAxisWidth($declarations[$indexes['block']][1]);
        $inline = $this->expandLogicalBorderAxisWidth($declarations[$indexes['inline']][1]);
        if ($block === null || $inline === null) {
            return $declarations;
        }

        $values = [$block['start'], $block['end'], $inline['start'], $inline['end']];
        if (!$this->allValuesEqual($values)) {
            return $declarations;
        }

        return $this->replaceDeclarations(
            $declarations,
            [
                min($indexes) => ['border-width', $values[0]],
            ],
            array_flip(array_values($indexes))
        );
    }

    /**
     * @param list<array{string, string}> $declarations
     */
    private function containsDeclaration(array $declarations, string $property): bool
    {
        foreach ($declarations as [$candidate]) {
            if ($candidate === $property) {
                return true;
            }
        }

        return false;
    }

    private function isPhysicalBorderProperty(string $property): bool
    {
        if ($property === 'border' || in_array($property, ['border-width', 'border-style', 'border-color'], true)) {
            return true;
        }

        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            if ($this->isPhysicalBorderSideProperty($property, $side)) {
                return true;
            }
        }

        return false;
    }

    private function isPhysicalBorderSideProperty(string $property, string $side): bool
    {
        return $property === 'border-' . $side
            || in_array($property, [
                'border-' . $side . '-width',
                'border-' . $side . '-style',
                'border-' . $side . '-color',
            ], true);
    }

    private function isPhysicalBorderSideShorthand(string $property): bool
    {
        return $this->physicalBorderSideFromShorthand($property) !== null;
    }

    private function physicalBorderSideFromShorthand(string $property): ?string
    {
        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            if ($property === 'border-' . $side) {
                return $side;
            }
        }

        return null;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function physicalBorderSideLonghand(string $property): ?array
    {
        foreach (self::BORDER_PHYSICAL_SIDES as $side) {
            foreach (self::BORDER_COMPONENTS as $component) {
                if ($property === 'border-' . $side . '-' . $component) {
                    return [$side, $component];
                }
            }
        }

        return null;
    }

    private function isLogicalBorderDeclaration(string $property): bool
    {
        return preg_match('/^border-(?:block|inline)(?:-|$)/', $property) === 1;
    }

    /**
     * @param list<array{string, string}> $declarations
     */
    private function containsLogicalBorderDeclaration(array $declarations): bool
    {
        foreach ($declarations as [$property]) {
            if ($this->isLogicalBorderDeclaration($property)) {
                return true;
            }
        }

        return false;
    }

    private function formatBorderComponentValue(?string $component, string $value): string
    {
        if ($component === 'color') {
            return $this->formatColorDeclarationValue($value);
        }

        return $this->formatDeclarationValue($value);
    }

    private function formatBorderShorthandValue(string $value): string
    {
        $components = $this->parseBorderShorthandComponents($value);
        if ($components === null) {
            return $this->formatDeclarationValue($value);
        }

        return $this->serializeBorderComponents($components, true);
    }

    private function canComposeBorderValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/!\s*important\b|\bvar\s*\(/i', $value) !== 1;
    }

    /**
     * @return array{width:string, style:string, color:string}|null
     */
    private function parseBorderShorthandComponents(string $value): ?array
    {
        if (!$this->canComposeBorderValue($value)) {
            return null;
        }

        $components = [];
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            $formatted = $this->formatDeclarationValue($token);
            $lower = strtolower($formatted);
            if (!isset($components['style']) && in_array($lower, self::BORDER_STYLES, true)) {
                $components['style'] = $lower;
                continue;
            }

            if (!isset($components['width']) && $this->isBorderWidthToken($formatted)) {
                $components['width'] = $formatted;
                continue;
            }

            if (!isset($components['color'])) {
                $components['color'] = $this->formatColorDeclarationValue($token);
                continue;
            }

            return null;
        }

        foreach (self::BORDER_COMPONENTS as $component) {
            if (!isset($components[$component])) {
                return null;
            }
        }

        return [
            'width' => $components['width'],
            'style' => $components['style'],
            'color' => $components['color'],
        ];
    }

    /**
     * @param array{width:string, style:string, color:string} $components
     */
    private function serializeBorderComponents(array $components, bool $omitCurrentColor = false): string
    {
        $parts = [$components['width'], $components['style']];
        if (!$omitCurrentColor || strcasecmp($components['color'], 'currentColor') !== 0) {
            $parts[] = $components['color'];
        }

        return implode(' ', $parts);
    }

    /**
     * @param array{width:string, style:string, color:string} $left
     * @param array{width:string, style:string, color:string} $right
     */
    private function borderComponentsEqual(array $left, array $right): bool
    {
        foreach (self::BORDER_COMPONENTS as $component) {
            if (strcasecmp($left[$component], $right[$component]) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{start:string, end:string}|null
     */
    private function expandLogicalBorderAxisWidth(string $value): ?array
    {
        if (!$this->canComposeBorderValue($value)) {
            return null;
        }

        $tokens = array_map(
            fn (string $token): string => $this->formatBorderComponentValue('width', $token),
            $this->splitWhitespaceTopLevel($value)
        );
        if ($tokens === [] || count($tokens) > 2) {
            return null;
        }

        return [
            'start' => $tokens[0],
            'end' => $tokens[1] ?? $tokens[0],
        ];
    }

    private function isBorderWidthToken(string $token): bool
    {
        $lower = strtolower($token);
        if (in_array($lower, ['thin', 'medium', 'thick', '0'], true)) {
            return true;
        }

        return preg_match('/^-?(?:\d+|\d*\.\d+)(?:px|em|rem|ch|ex|lh|rlh|vw|vh|vmin|vmax|cm|mm|q|in|pt|pc|%)$/i', $token) === 1;
    }

    /**
     * @param array{width:string, style:string, color:string} $base
     * @param array{width:string, style:string, color:string} $components
     * @return array{string, string}|null
     */
    private function borderSideOverrideReplacement(string $side, array $base, array $components): ?array
    {
        $diff = [];
        foreach (self::BORDER_COMPONENTS as $component) {
            if (strcasecmp($base[$component], $components[$component]) !== 0) {
                $diff[] = $component;
            }
        }

        if ($diff === []) {
            return null;
        }

        if (count($diff) === 1) {
            $component = $diff[0];
            return ['border-' . $side . '-' . $component, $components[$component]];
        }

        return [
            'border-' . $side,
            $components['width'] . ' ' . $components['style'] . ' ' . $components['color'],
        ];
    }

    /**
     * @param array<string, string> $values
     */
    private function allValuesEqual(array $values): bool
    {
        $first = null;
        foreach ($values as $value) {
            if ($first === null) {
                $first = $value;
                continue;
            }

            if (strcasecmp($first, $value) !== 0) {
                return false;
            }
        }

        return $first !== null;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @param array<int, array{string, string}> $replacements
     * @param array<int, bool> $skip
     * @return list<array{string, string}>
     */
    private function replaceDeclarations(array $declarations, array $replacements, array $skip): array
    {
        if ($replacements === [] && $skip === []) {
            return $declarations;
        }

        $output = [];
        foreach ($declarations as $index => $declaration) {
            if (isset($skip[$index]) && !isset($replacements[$index])) {
                continue;
            }

            $output[] = $replacements[$index] ?? $declaration;
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBorderImageStyleDeclarations(array $declarations): array
    {
        $declarations = $this->dropBorderImageDeclarationsResetByBorder($declarations);
        $declarations = $this->composeBorderImageSourceOverrides($declarations);

        return $this->composeBorderImageLonghands($declarations);
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function dropBorderImageDeclarationsResetByBorder(array $declarations): array
    {
        $skip = [];
        foreach ($declarations as $index => [$property, $value]) {
            if ($property !== 'border') {
                continue;
            }

            $borderImportant = $this->isImportantDeclarationValue($value);
            for ($previous = 0; $previous < $index; $previous++) {
                if (!$this->isUnprefixedBorderImageProperty($declarations[$previous][0])) {
                    continue;
                }

                if ($this->isImportantDeclarationValue($declarations[$previous][1]) && !$borderImportant) {
                    continue;
                }

                $skip[$previous] = true;
            }
        }

        if ($skip === []) {
            return $declarations;
        }

        $output = [];
        foreach ($declarations as $index => $declaration) {
            if (!isset($skip[$index])) {
                $output[] = $declaration;
            }
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBorderImageSourceOverrides(array $declarations): array
    {
        $skip = [];
        $replacements = [];
        foreach ($declarations as $index => [$property, $value]) {
            if ($property !== 'border-image-source'
                || $this->isImportantDeclarationValue($value)
                || !$this->canComposeBorderImageLonghandValue($property, $value)
            ) {
                continue;
            }

            $shorthandIndex = $this->latestPreviousBorderImageShorthandIndex($declarations, $index, $skip);
            if ($shorthandIndex === null || $this->isImportantDeclarationValue($declarations[$shorthandIndex][1])) {
                continue;
            }

            $components = $this->parseBorderImageComponents($declarations[$shorthandIndex][1]);
            if ($components === null) {
                continue;
            }

            $components['border-image-source'] = $this->normalizeBorderImageSourceValue($value);
            $replacements[$shorthandIndex] = ['border-image', $this->composeBorderImageShorthandValue($components)];
            $skip[$index] = true;
        }

        if ($replacements === [] && $skip === []) {
            return $declarations;
        }

        $output = [];
        foreach ($declarations as $index => $declaration) {
            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $replacements[$index] ?? $declaration;
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @param array<int, bool> $skip
     */
    private function latestPreviousBorderImageShorthandIndex(array $declarations, int $before, array $skip): ?int
    {
        for ($index = $before - 1; $index >= 0; $index--) {
            if (isset($skip[$index])) {
                continue;
            }

            if ($declarations[$index][0] === 'border-image') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBorderImageLonghands(array $declarations): array
    {
        foreach ($declarations as [$property]) {
            if ($property === 'border-image') {
                return $declarations;
            }
        }

        $latest = [];
        foreach ($declarations as $index => [$property, $value]) {
            if (!$this->isBorderImageLonghand($property)) {
                continue;
            }

            if (!$this->canComposeBorderImageLonghandValue($property, $value)) {
                return $declarations;
            }

            $latest[$property] = $index;
        }

        foreach (self::BORDER_IMAGE_LONGHANDS as $property) {
            if (!isset($latest[$property])) {
                return $declarations;
            }
        }

        $components = [];
        foreach (self::BORDER_IMAGE_LONGHANDS as $property) {
            $components[$property] = $this->normalizeBorderImageLonghandValue(
                $property,
                $declarations[$latest[$property]][1]
            );
        }

        $replaceAt = min($latest);
        $skip = array_flip(array_values($latest));
        $output = [];
        foreach ($declarations as $index => $declaration) {
            if ($index === $replaceAt) {
                $output[] = ['border-image', $this->composeBorderImageShorthandValue($components)];
                continue;
            }

            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $declaration;
        }

        return $output;
    }

    private function canComposeBorderImageLonghandValue(string $property, string $value): bool
    {
        $value = trim($value);
        if ($value === ''
            || $this->isImportantDeclarationValue($value)
            || preg_match('/\bvar\s*\(/i', $value) === 1
        ) {
            return false;
        }

        if ($property === 'border-image-source') {
            return $this->isBorderImageSourceToken($value);
        }

        if ($property === 'border-image-repeat') {
            $tokens = $this->splitWhitespaceTopLevel($value);
            if ($tokens === [] || count($tokens) > 2) {
                return false;
            }

            foreach ($tokens as $token) {
                if (!in_array(strtolower($token), self::BORDER_IMAGE_REPEAT_KEYWORDS, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isUnprefixedBorderImageProperty(string $property): bool
    {
        return $property === 'border-image' || $this->isBorderImageLonghand($property);
    }

    private function isBorderImageLonghand(string $property): bool
    {
        return in_array($property, self::BORDER_IMAGE_LONGHANDS, true);
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
        if ($this->isImportantDeclarationValue($value) || preg_match('/\bvar\s*\(/i', $value) === 1) {
            return null;
        }

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
        $token = trim($token);

        return strcasecmp($token, 'none') === 0
            || preg_match('/^(?:url|(?:repeating-)?(?:linear|radial|conic)-gradient|image|image-set|cross-fade|element|paint)\(/i', $token) === 1;
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

        return strcasecmp($value, 'none') === 0 ? 'none' : $this->formatDeclarationValue($value);
    }

    private function normalizeBorderImageSliceValue(string $value): string
    {
        $fill = false;
        $offsets = [];
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
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
        $tokens = array_map(
            fn (string $token): string => $this->formatDeclarationValue($token),
            $this->splitWhitespaceTopLevel($value)
        );
        if (count($tokens) < 1 || count($tokens) > 4) {
            return trim($value);
        }

        return $this->compressBoxModelSides(match (count($tokens)) {
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

        if (strcasecmp($source, 'none') !== 0) {
            $parts[] = $source;
        }
        if (strcasecmp($slice, '100%') !== 0 || $width !== '1' || $outset !== '0') {
            $slicePart = $slice;
            if ($width !== '1' || $outset !== '0') {
                $slicePart .= ' / ';
                if ($width !== '1') {
                    $slicePart .= $width;
                }
                if ($outset !== '0') {
                    $slicePart .= ' / ' . $outset;
                }
            }
            $parts[] = trim($slicePart);
        }
        if (strcasecmp($repeat, 'stretch') !== 0) {
            $parts[] = $repeat;
        }

        return $parts === [] ? 'none' : implode(' ', $parts);
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBoxModelShorthandOverrides(array $declarations, string $base): array
    {
        $count = count($declarations);
        if ($count < 2) {
            return $declarations;
        }

        $skip = [];
        $replacements = [];
        for ($i = 0; $i < $count; $i++) {
            if ($declarations[$i][0] !== $base) {
                continue;
            }

            $sides = $this->expandBoxModelShorthand($declarations[$i][1]);
            if ($sides === null) {
                continue;
            }

            $consumed = [];
            $aborted = false;
            for ($j = $i + 1; $j < $count; $j++) {
                $property = $declarations[$j][0];
                if ($property === $base || $this->isLogicalBoxModelProperty($property, $base)) {
                    break;
                }

                $side = $this->physicalBoxModelSide($property, $base);
                if ($side === null) {
                    continue;
                }

                if (!$this->canComposeBoxModelValue($declarations[$j][1])) {
                    $aborted = true;
                    break;
                }

                $sides[$side] = $this->formatDeclarationValue($declarations[$j][1]);
                $consumed[] = $j;
            }

            if ($aborted || $consumed === []) {
                continue;
            }

            $replacements[$i] = [$base, $this->compressBoxModelSides($sides)];
            foreach ($consumed as $index) {
                $skip[$index] = true;
            }
        }

        if ($replacements === []) {
            return $declarations;
        }

        $output = [];
        foreach ($declarations as $index => $declaration) {
            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $replacements[$index] ?? $declaration;
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBoxModelLogicalPairs(array $declarations, string $base): array
    {
        $replacements = [];
        $skip = [];
        foreach ([
            $base . '-block' => ['start' => $base . '-block-start', 'end' => $base . '-block-end'],
            $base . '-inline' => ['start' => $base . '-inline-start', 'end' => $base . '-inline-end'],
        ] as $shorthand => $properties) {
            $indexes = [];
            foreach ($declarations as $index => [$property]) {
                if ($property === $properties['start']) {
                    $indexes['start'] = $index;
                } elseif ($property === $properties['end']) {
                    $indexes['end'] = $index;
                }
            }

            if (!isset($indexes['start'], $indexes['end'])) {
                continue;
            }

            $start = $this->formatDeclarationValue($declarations[$indexes['start']][1]);
            $end = $this->formatDeclarationValue($declarations[$indexes['end']][1]);
            if (!$this->canComposeBoxModelValue($start) || !$this->canComposeBoxModelValue($end)) {
                continue;
            }

            $replaceAt = min($indexes);
            $skip[max($indexes)] = true;
            $replacements[$replaceAt] = [
                $shorthand,
                strcasecmp($start, $end) === 0 ? $start : $start . ' ' . $end,
            ];
        }

        if ($replacements === []) {
            return $declarations;
        }

        $output = [];
        foreach ($declarations as $index => $declaration) {
            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $replacements[$index] ?? $declaration;
        }

        return $output;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeBoxModelPhysicalLonghands(array $declarations, string $base): array
    {
        foreach ($declarations as [$property]) {
            if ($this->isLogicalBoxModelProperty($property, $base)) {
                return $declarations;
            }
        }

        $indexes = [];
        foreach ($declarations as $index => [$property]) {
            $side = $this->physicalBoxModelSide($property, $base);
            if ($side !== null) {
                $indexes[$side] = $index;
            }
        }

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (!isset($indexes[$side])) {
                return $declarations;
            }
        }

        $sides = [];
        foreach ($indexes as $side => $index) {
            $value = $this->formatDeclarationValue($declarations[$index][1]);
            if (!$this->canComposeBoxModelValue($value)) {
                return $declarations;
            }

            $sides[$side] = $value;
        }

        $replaceAt = min($indexes);
        $skip = array_flip(array_values($indexes));
        $output = [];
        foreach ($declarations as $index => $declaration) {
            if ($index === $replaceAt) {
                $output[] = [$base, $this->compressBoxModelSides($sides)];
                continue;
            }

            if (isset($skip[$index])) {
                continue;
            }

            $output[] = $declaration;
        }

        return $output;
    }

    /**
     * @return array{top:string,right:string,bottom:string,left:string}|null
     */
    private function expandBoxModelShorthand(string $value): ?array
    {
        if (!$this->canComposeBoxModelValue($value)) {
            return null;
        }

        $tokens = array_map(
            fn (array $token): string => $this->formatDeclarationValue($token['value']),
            $this->splitWhitespaceTokensWithOffsets($value),
        );
        if ($tokens === [] || count($tokens) > 4) {
            return null;
        }

        return match (count($tokens)) {
            1 => ['top' => $tokens[0], 'right' => $tokens[0], 'bottom' => $tokens[0], 'left' => $tokens[0]],
            2 => ['top' => $tokens[0], 'right' => $tokens[1], 'bottom' => $tokens[0], 'left' => $tokens[1]],
            3 => ['top' => $tokens[0], 'right' => $tokens[1], 'bottom' => $tokens[2], 'left' => $tokens[1]],
            4 => ['top' => $tokens[0], 'right' => $tokens[1], 'bottom' => $tokens[2], 'left' => $tokens[3]],
        };
    }

    /**
     * @param array{top:string,right:string,bottom:string,left:string} $sides
     */
    private function compressBoxModelSides(array $sides): string
    {
        if (strcasecmp($sides['left'], $sides['right']) !== 0) {
            return implode(' ', [$sides['top'], $sides['right'], $sides['bottom'], $sides['left']]);
        }

        if (strcasecmp($sides['bottom'], $sides['top']) !== 0) {
            return implode(' ', [$sides['top'], $sides['right'], $sides['bottom']]);
        }

        if (strcasecmp($sides['right'], $sides['top']) !== 0) {
            return implode(' ', [$sides['top'], $sides['right']]);
        }

        return $sides['top'];
    }

    private function physicalBoxModelSide(string $property, string $base): ?string
    {
        return match ($property) {
            $base . '-top' => 'top',
            $base . '-right' => 'right',
            $base . '-bottom' => 'bottom',
            $base . '-left' => 'left',
            default => null,
        };
    }

    private function isLogicalBoxModelProperty(string $property, string $base): bool
    {
        return in_array($property, [
            $base . '-block',
            $base . '-block-start',
            $base . '-block-end',
            $base . '-inline',
            $base . '-inline-start',
            $base . '-inline-end',
        ], true);
    }

    private function canComposeBoxModelValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/!\s*important\b|\bvar\s*\(/i', $value) !== 1;
    }

    /**
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function composeGridStyleDeclarations(array $declarations): array
    {
        $templateComponents = [
            'areas' => 'grid-template-areas',
            'rows' => 'grid-template-rows',
            'columns' => 'grid-template-columns',
        ];
        $autoComponents = [
            'flow' => 'grid-auto-flow',
            'rows' => 'grid-auto-rows',
            'columns' => 'grid-auto-columns',
        ];
        $templateByProperty = array_flip($templateComponents);
        $autoByProperty = array_flip($autoComponents);
        $latest = [];
        $latestAuto = [];
        $indexes = [];
        $autoIndexes = [];

        foreach ($declarations as $index => [$property, $value]) {
            if ($property === 'grid' || $property === 'grid-template') {
                return $declarations;
            }

            if (!isset($templateByProperty[$property]) && !isset($autoByProperty[$property])) {
                continue;
            }

            if (stripos($value, '!important') !== false) {
                return $declarations;
            }

            if (isset($templateByProperty[$property])) {
                $component = $templateByProperty[$property];
                $latest[$component] = $index;
                $indexes[] = $index;
                continue;
            }

            $component = $autoByProperty[$property];
            $latestAuto[$component] = $index;
            $autoIndexes[] = $index;
        }

        foreach (array_keys($templateComponents) as $component) {
            if (!isset($latest[$component])) {
                return $declarations;
            }
        }

        $shorthand = $this->composeGridTemplateLonghands(
            $declarations[$latest['areas']][1],
            $declarations[$latest['rows']][1],
            $declarations[$latest['columns']][1]
        );
        if ($shorthand === null) {
            return $declarations;
        }

        $gridShorthand = null;
        if (isset($latestAuto['flow'], $latestAuto['rows'], $latestAuto['columns'])) {
            $gridShorthand = $this->composeGridShorthandWithAutoTracks(
                $shorthand,
                $declarations[$latest['areas']][1],
                $declarations[$latest['rows']][1],
                $declarations[$latest['columns']][1],
                $declarations[$latestAuto['flow']][1],
                $declarations[$latestAuto['rows']][1],
                $declarations[$latestAuto['columns']][1]
            );
        }

        $useGridShorthand = $gridShorthand !== null;
        if ($useGridShorthand) {
            $indexes = array_merge($indexes, $autoIndexes);
        }

        $replaceAt = min($indexes);
        $gridIndexes = array_flip($indexes);
        $output = [];
        foreach ($declarations as $index => $declaration) {
            if ($index === $replaceAt) {
                $output[] = [$useGridShorthand ? 'grid' : 'grid-template', $gridShorthand ?? $shorthand];
                continue;
            }

            if (isset($gridIndexes[$index])) {
                continue;
            }

            $output[] = $declaration;
        }

        if (!$useGridShorthand && isset($latestAuto['flow'], $latestAuto['rows'], $latestAuto['columns'])) {
            $output = $this->moveGridAutoFlowAfterAutoTracks($output);
        }

        return $output;
    }

    private function composeGridShorthandWithAutoTracks(
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
                $autoFlow .= ' ' . $this->formatDeclarationValue($autoRows);
            }

            return $autoFlow . ' / ' . $this->formatDeclarationValue($columns);
        }

        if (($flow === 'column' || $flow === 'column dense')
            && strcasecmp($columns, 'none') === 0
            && $autoRowsIsDefault
        ) {
            $autoFlow = $flow === 'column dense' ? 'auto-flow dense' : 'auto-flow';
            if (!$autoColumnsIsDefault) {
                $autoFlow .= ' ' . $this->formatDeclarationValue($autoColumns);
            }

            return $this->formatDeclarationValue($rows) . ' / ' . $autoFlow;
        }

        return null;
    }

    private function canonicalGridAutoFlowForComposition(string $value): ?string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($this->formatDeclarationValue($value)))) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
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
     * @param list<array{string, string}> $declarations
     * @return list<array{string, string}>
     */
    private function moveGridAutoFlowAfterAutoTracks(array $declarations): array
    {
        $latest = [];
        foreach ($declarations as $index => [$property]) {
            if ($property === 'grid-auto-flow') {
                $latest['flow'] = $index;
            } elseif ($property === 'grid-auto-rows') {
                $latest['rows'] = $index;
            } elseif ($property === 'grid-auto-columns') {
                $latest['columns'] = $index;
            }
        }

        if (!isset($latest['flow'], $latest['rows'], $latest['columns'])) {
            return $declarations;
        }

        if ($this->canonicalGridAutoFlowForComposition($declarations[$latest['flow']][1]) === null) {
            return $declarations;
        }

        $positions = [$latest['flow'], $latest['rows'], $latest['columns']];
        sort($positions);
        if ($positions[2] - $positions[0] !== 2) {
            return $declarations;
        }

        $rows = $declarations[$latest['rows']];
        $columns = $declarations[$latest['columns']];
        $flow = $declarations[$latest['flow']];
        $declarations[$positions[0]] = $rows;
        $declarations[$positions[1]] = $columns;
        $declarations[$positions[2]] = $flow;

        return $declarations;
    }

    private function composeGridTemplateLonghands(string $areas, string $rows, string $columns): ?string
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

            return $this->formatDeclarationValue($rows) . ' / ' . $this->formatDeclarationValue($columns);
        }

        if (stripos($columns, 'repeat(') !== false) {
            return null;
        }

        $areaRows = $this->gridTemplateAreaRowsForComposition($areas);
        if ($areaRows === null || $areaRows === []) {
            return null;
        }

        $rowTokens = $this->splitGridTemplateTokens($rows);
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
                    $segments[] = $this->formatDeclarationValue($track);
                }
            }
        }

        while (isset($rowTokens[$tokenIndex])) {
            $segments[] = $rowTokens[$tokenIndex++];
        }

        return implode(' ', $segments) . ' / ' . $this->formatDeclarationValue($columns);
    }

    /**
     * @return list<string>|null
     */
    private function gridTemplateAreaRowsForComposition(string $areas): ?array
    {
        $tokens = $this->splitGridTemplateTokens($areas);
        if ($tokens === []) {
            return null;
        }

        $rows = [];
        foreach ($tokens as $token) {
            if (!$this->isCssStringToken($token)) {
                return null;
            }

            $formatted = $this->formatGridTemplateAreaString($token);
            $rows[] = substr($formatted, 1, -1);
        }

        return $rows;
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
     * @param list<array{string, string}> $declarations
     * @param array<int, bool> $skip
     */
    private function nextLineHeightIndex(array $declarations, int $start, array $skip): ?int
    {
        $count = count($declarations);
        for ($i = $start; $i < $count; $i++) {
            if (isset($skip[$i])) {
                continue;
            }

            [$property] = $declarations[$i];
            if ($property === 'line-height') {
                return $i;
            }

            if (!$this->isFontLonghand($property)) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $values
     * @return array{value:string, consumed:list<string>}|null
     */
    private function composeFontLonghands(array $values): ?array
    {
        if (!isset($values['font-family'], $values['font-size'])) {
            return null;
        }

        $parts = [];
        $consumed = ['font-family', 'font-size'];

        $style = $this->formatDeclarationValue($values['font-style'] ?? 'normal');
        if (strcasecmp($style, 'normal') !== 0) {
            $parts[] = $style;
        }
        if (array_key_exists('font-style', $values)) {
            $consumed[] = 'font-style';
        }

        $variant = $this->formatDeclarationValue($values['font-variant-caps'] ?? 'normal');
        if (strcasecmp($variant, 'small-caps') === 0) {
            $parts[] = $variant;
            $consumed[] = 'font-variant-caps';
        } elseif (strcasecmp($variant, 'normal') === 0 && array_key_exists('font-variant-caps', $values)) {
            $consumed[] = 'font-variant-caps';
        }

        $weight = $this->formatDeclarationValue($values['font-weight'] ?? 'normal');
        if (strcasecmp($weight, 'normal') !== 0) {
            $parts[] = $weight;
        }
        if (array_key_exists('font-weight', $values)) {
            $consumed[] = 'font-weight';
        }

        $stretch = $this->formatDeclarationValue($values['font-stretch'] ?? 'normal');
        if (strcasecmp($stretch, 'normal') !== 0) {
            $parts[] = $stretch;
        }
        if (array_key_exists('font-stretch', $values)) {
            $consumed[] = 'font-stretch';
        }

        $size = $this->formatDeclarationValue($values['font-size']);
        if (isset($values['line-height']) && $this->canFoldFontLineHeight($values['line-height'])) {
            $size .= ' / ' . $this->formatDeclarationValue($values['line-height']);
            $consumed[] = 'line-height';
        }
        $parts[] = $size;
        $parts[] = $this->formatFontFamilyList($values['font-family']);

        return [
            'value' => implode(' ', $parts),
            'consumed' => array_values(array_unique($consumed)),
        ];
    }

    private function canFoldFontLineHeight(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/\b(var|env|calc|min|max|clamp)\s*\(/i', $value) !== 1;
    }

    private function formatFontShorthandValue(string $value, ?string $lineHeight = null): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        $tokens = $this->splitWhitespaceTokensWithOffsets($value);
        if ($tokens === []) {
            return $this->formatDeclarationValue($value);
        }

        foreach ($tokens as $index => $token) {
            $tokenValue = $token['value'];
            [$sizeToken, $embeddedLineHeight] = $this->splitFontSizeAndLineHeight($tokenValue);
            if (!$this->isFontSizeToken($sizeToken)) {
                continue;
            }

            $familyStart = $token['end'];
            if ($embeddedLineHeight === null && $index + 1 < count($tokens) && $tokens[$index + 1]['value'] === '/') {
                $next = $tokens[$index + 2] ?? null;
                if ($next !== null) {
                    $embeddedLineHeight = $next['value'];
                    $familyStart = $next['end'];
                }
            }

            $prefix = trim(substr($value, 0, $token['start']));
            $family = trim(substr($value, $familyStart));
            if ($family === '') {
                return $this->formatDeclarationValue($value);
            }

            $size = $this->formatDeclarationValue($sizeToken);
            $foldedLineHeight = $lineHeight !== null ? $this->formatDeclarationValue($lineHeight) : $embeddedLineHeight;
            if ($foldedLineHeight !== null && $foldedLineHeight !== '') {
                $size .= ' / ' . $this->formatDeclarationValue($foldedLineHeight);
            }

            $parts = [];
            if ($prefix !== '') {
                $parts[] = $this->formatDeclarationValue($prefix);
            }
            $parts[] = $size;
            $parts[] = $this->formatFontFamilyList($family);

            return implode(' ', $parts);
        }

        return $this->formatDeclarationValue($value);
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function splitFontSizeAndLineHeight(string $token): array
    {
        $slash = $this->findNextTopLevel($token, '/', 0);
        if ($slash === null) {
            return [$token, null];
        }

        return [
            trim(substr($token, 0, $slash)),
            trim(substr($token, $slash + 1)),
        ];
    }

    private function isFontSizeToken(string $token): bool
    {
        $token = strtolower(trim($token));
        if (preg_match('/^(?:xx-small|x-small|small|medium|large|x-large|xx-large|xxx-large|larger|smaller)$/', $token) === 1) {
            return true;
        }

        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:%|[a-z][a-z0-9-]*)$/', $token) === 1
            || preg_match('/^(?:var|calc|min|max|clamp)\(/', $token) === 1;
    }

    private function formatFontFamilyList(string $value): string
    {
        $families = [];
        foreach ($this->splitTopLevel($value, ',') as $family) {
            $family = trim($family);
            if ($family === '') {
                continue;
            }

            $families[] = $this->formatFontFamilyName($family);
        }

        return implode(', ', $families);
    }

    private function formatFontFamilyName(string $family): string
    {
        $family = trim(preg_replace('/\s+/', ' ', $family) ?? $family);
        if ($this->isCssStringToken($family)) {
            $quote = $family[0];
            $inner = substr($family, 1, -1);
            if ($this->canUnquoteFontFamily($inner)) {
                return stripcslashes($inner);
            }

            return $quote . str_replace($quote, '\\' . $quote, $inner) . $quote;
        }

        return $family;
    }

    private function canUnquoteFontFamily(string $family): bool
    {
        $family = trim(preg_replace('/\s+/', ' ', stripcslashes($family)) ?? $family);
        if ($family === '') {
            return false;
        }

        $reserved = [
            'serif',
            'sans-serif',
            'monospace',
            'cursive',
            'fantasy',
            'system-ui',
            'ui-serif',
            'ui-sans-serif',
            'ui-monospace',
            'ui-rounded',
            'emoji',
            'math',
            'fangsong',
            'caption',
            'icon',
            'menu',
            'message-box',
            'small-caption',
            'status-bar',
            'default',
            'inherit',
            'initial',
            'unset',
            'revert',
            'revert-layer',
        ];
        if (in_array(strtolower($family), $reserved, true)) {
            return false;
        }

        foreach (preg_split('/\s+/', $family) ?: [] as $word) {
            if (preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $word) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function formatDeclarations(string $body, int $indentLevel): string
    {
        $lines = [];
        foreach ($this->parseDeclarations($body) as [$property, $value]) {
            $lines[] = $this->indent($indentLevel) . $property . ': ' . $this->formatDeclarationValue($value) . ';';
        }

        return implode("\n", $lines);
    }

    private function formatGridTemplateDeclarationValue(string $property, string $value, int $continuationIndent): string
    {
        $parts = $this->splitTopLevel($value, '/');
        if (count($parts) > 2) {
            return $this->formatDeclarationValue($value);
        }

        $rows = $this->formatGridTemplateAreaRows(trim($parts[0]));
        if ($rows === null) {
            if (count($parts) === 2) {
                $left = $this->formatDeclarationValue(trim($parts[0]));
                $right = $this->formatDeclarationValue(trim($parts[1]));
                if ($left !== '' && $right !== '') {
                    return $left . ' / ' . $right;
                }
            }

            return $this->formatDeclarationValue($value);
        }

        $continuation = "\n" . str_repeat(' ', $continuationIndent);
        if (count($parts) === 1) {
            return implode($continuation, $rows);
        }

        $columns = $this->formatDeclarationValue(trim($parts[1]));
        if ($columns === '') {
            return $this->formatDeclarationValue($value);
        }

        return implode($continuation, $rows) . $continuation . '/ ' . $columns;
    }

    /**
     * @return list<string>|null
     */
    private function formatGridTemplateAreaRows(string $rows): ?array
    {
        $tokens = $this->splitGridTemplateTokens($rows);
        if ($tokens === [] || !$this->gridTemplateTokensContainAreaString($tokens)) {
            return null;
        }

        $lines = [];
        $pendingLineNames = [];
        $index = 0;
        $count = count($tokens);

        while ($index < $count) {
            $token = $tokens[$index];
            if ($this->isGridLineNameToken($token)) {
                $pendingLineNames[] = $token;
                $index++;
                continue;
            }

            if (!$this->isCssStringToken($token)) {
                return null;
            }

            $segments = array_merge($pendingLineNames, [$this->formatGridTemplateAreaString($token)]);
            $pendingLineNames = [];
            $index++;

            $between = [];
            while ($index < $count && !$this->isCssStringToken($tokens[$index])) {
                $between[] = $tokens[$index++];
            }

            $hasNextArea = $index < $count;
            [$suffix, $nextPrefix] = $this->formatGridTemplateRowTail($between, $hasNextArea);
            $segments = array_merge($segments, $suffix);
            $pendingLineNames = $nextPrefix;
            $lines[] = implode(' ', $segments);
        }

        if ($pendingLineNames !== []) {
            $last = array_key_last($lines);
            if ($last === null) {
                return null;
            }
            $lines[$last] .= ' ' . implode(' ', $pendingLineNames);
        }

        return $lines;
    }

    /**
     * @param list<string> $tokens
     * @return array{0:list<string>,1:list<string>}
     */
    private function formatGridTemplateRowTail(array $tokens, bool $hasNextArea): array
    {
        if ($tokens === []) {
            return [[], []];
        }

        $trackIndex = null;
        foreach ($tokens as $index => $token) {
            if (!$this->isGridLineNameToken($token)) {
                $trackIndex = $index;
                break;
            }
        }

        if ($trackIndex === null) {
            if (!$hasNextArea) {
                return [$tokens, []];
            }

            return $this->splitGridTemplateBoundaryLineNames($tokens);
        }

        $suffix = array_slice($tokens, 0, $trackIndex);
        $track = $tokens[$trackIndex];
        if (strcasecmp($track, 'auto') !== 0) {
            $suffix[] = $this->formatDeclarationValue($track);
        }

        foreach (array_slice($tokens, $trackIndex + 1) as $token) {
            $suffix[] = $token;
        }

        return [$suffix, []];
    }

    /**
     * @param list<string> $tokens
     * @return array{0:list<string>,1:list<string>}
     */
    private function splitGridTemplateBoundaryLineNames(array $tokens): array
    {
        if ($tokens === []) {
            return [[], []];
        }

        $firstNames = $this->gridLineNameTokenNames($tokens[0]);
        if (count($firstNames) > 1) {
            return [
                [$this->gridLineNameToken([$firstNames[0]])],
                [$this->gridLineNameToken(array_slice($firstNames, 1))],
            ];
        }

        if (count($tokens) === 1) {
            return [[], $tokens];
        }

        return [[$tokens[0]], array_slice($tokens, 1)];
    }

    /**
     * @return list<string>
     */
    private function splitGridTemplateTokens(string $value): array
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
                    return [];
                }
                $tokens[] = trim(substr($value, $i, $end - $i + 1));
                $i = $end;
                continue;
            }

            if ($value[$i] === '"' || $value[$i] === "'") {
                $quote = $value[$i];
                $start = $i;
                for ($i++; $i < $length; $i++) {
                    if ($value[$i] === '\\') {
                        $i++;
                        continue;
                    }
                    if ($value[$i] === $quote) {
                        $tokens[] = substr($value, $start, $i - $start + 1);
                        break;
                    }
                }
                if ($i >= $length) {
                    return [];
                }
                continue;
            }

            $start = $i;
            $parenDepth = 0;
            for (; $i < $length; $i++) {
                $char = $value[$i];
                if ($char === '(') {
                    $parenDepth++;
                    continue;
                }
                if ($char === ')' && $parenDepth > 0) {
                    $parenDepth--;
                    continue;
                }
                if ($parenDepth === 0 && (ctype_space($char) || $char === '[' || $char === '"' || $char === "'")) {
                    break;
                }
            }

            $tokens[] = trim(substr($value, $start, $i - $start));
            $i--;
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $tokens
     */
    private function gridTemplateTokensContainAreaString(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($this->isCssStringToken($token)) {
                return true;
            }
        }

        return false;
    }

    private function formatGridTemplateAreaString(string $token): string
    {
        $quote = $token[0];
        $content = substr($token, 1, -1);
        $cells = preg_split('/\s+/', trim($content)) ?: [];
        $cells = array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));
        $cells = array_map(static fn (string $cell): string => preg_match('/^\.+$/', $cell) === 1 ? '.' : $cell, $cells);

        return $quote . implode(' ', $cells) . $quote;
    }

    private function isCssStringToken(string $token): bool
    {
        $token = trim($token);

        return strlen($token) >= 2
            && (($token[0] === '"' && substr($token, -1) === '"')
                || ($token[0] === "'" && substr($token, -1) === "'"));
    }

    private function isGridLineNameToken(string $token): bool
    {
        $token = trim($token);

        return str_starts_with($token, '[') && str_ends_with($token, ']');
    }

    /**
     * @return list<string>
     */
    private function gridLineNameTokenNames(string $token): array
    {
        $names = preg_split('/\s+/', trim(substr($token, 1, -1))) ?: [];

        return array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
    }

    /**
     * @param list<string> $names
     */
    private function gridLineNameToken(array $names): string
    {
        return '[' . implode(' ', $names) . ']';
    }

    /**
     * @return list<array{string, string}>
     */
    private function parseDeclarations(string $body): array
    {
        $declarations = [];
        foreach ($this->splitTopLevel($body, ';') as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $colon = $this->findNextTopLevel($part, ':', 0);
            if ($colon === null) {
                throw new \InvalidArgumentException('Invalid declaration in @page rule: ' . $part);
            }

            $property = strtolower(trim(substr($part, 0, $colon)));
            $value = trim(substr($part, $colon + 1));
            if ($property === '') {
                throw new \InvalidArgumentException('Invalid declaration in @page rule: ' . $part);
            }

            $declarations[] = [$property, $value];
        }

        return $declarations;
    }

    private function normalizePagePrelude(string $prelude): string
    {
        $prelude = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);
        $prelude = preg_replace('/^@page\s+:/i', '@page :', $prelude) ?? $prelude;

        return $prelude;
    }

    private function normalizeCounterStylePrelude(string $prelude): string
    {
        $prelude = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);

        return preg_replace('/^@counter-style\s+/i', '@counter-style ', $prelude) ?? $prelude;
    }

    private function normalizeConditionalGroupPrelude(string $prelude): string
    {
        $prelude = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);
        if (preg_match('/^@supports\s+(.+)$/i', $prelude, $matches) === 1) {
            return '@supports ' . $this->normalizeSupportsCondition($matches[1]);
        }

        return $prelude;
    }

    private function normalizeSupportsCondition(string $condition): string
    {
        $condition = trim($condition);
        if (preg_match('/^not\s+(.+)$/i', $condition, $matches) === 1) {
            return 'not ' . $this->stripOneRedundantSupportsWrapper($matches[1]);
        }

        while (($unwrapped = $this->stripOneRedundantSupportsWrapper($condition)) !== $condition) {
            $condition = $unwrapped;
        }

        return $condition;
    }

    private function stripOneRedundantSupportsWrapper(string $condition): string
    {
        $condition = trim($condition);
        if (!str_starts_with($condition, '(') || !str_ends_with($condition, ')')) {
            return $condition;
        }

        $depth = 0;
        $quote = null;
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

            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0 && $i < $length - 1) {
                return $condition;
            }
        }

        $candidate = trim(substr($condition, 1, -1));
        if ($this->supportsConditionIsFullyWrapped($candidate) || $this->hasTopLevelSupportsLogicalOperator($candidate)) {
            return $candidate;
        }

        return $condition;
    }

    private function supportsConditionIsFullyWrapped(string $condition): bool
    {
        $condition = trim($condition);
        if (!str_starts_with($condition, '(') || !str_ends_with($condition, ')')) {
            return false;
        }

        $depth = 0;
        $quote = null;
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

            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0 && $i < $length - 1) {
                return false;
            }
        }

        return $depth === 0;
    }

    private function hasTopLevelSupportsLogicalOperator(string $condition): bool
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

            if ($depth === 0 && preg_match('/\G\s+(?:and|or)\s+/Ai', $condition, $matches, 0, $i) === 1) {
                return true;
            }
        }

        return false;
    }

    private function formatDeclarationValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        $value = $this->quoteSimpleUrlFunctions($value);
        $value = $this->normalizeCalcNumberLiterals($value);

        return preg_replace('/\bcounter\(\s*([_a-zA-Z-][_a-zA-Z0-9-]*)\s*\)/', 'counter($1)', $value) ?? $value;
    }

    private function formatTransformDeclarationValue(string $value): string
    {
        $value = $this->formatDeclarationValue($value);
        $length = strlen($value);
        $output = '';

        for ($i = 0; $i < $length;) {
            $functionName = $this->transformFunctionNameAt($value, $i);
            if ($functionName === null) {
                $output .= $value[$i];
                $i++;
                continue;
            }

            $open = $i + strlen($functionName);
            $close = $this->findMatchingParenthesis($value, $open);
            if ($close === null) {
                $output .= $value[$i];
                $i++;
                continue;
            }

            if ($output !== '' && substr($output, -1) === ')') {
                $output .= ' ';
            }

            $args = substr($value, $open + 1, $close - $open - 1);
            $output .= substr($value, $i, strlen($functionName)) . '(' . $this->formatTransformFunctionArguments($args) . ')';
            $i = $close + 1;
        }

        return $output;
    }

    private function transformFunctionNameAt(string $value, int $offset): ?string
    {
        foreach (self::TRANSFORM_FUNCTIONS as $name) {
            if ($this->startsFunctionAt($value, $offset, $name)) {
                return substr($value, $offset, strlen($name));
            }
        }

        return null;
    }

    private function formatTransformFunctionArguments(string $args): string
    {
        return implode(
            ', ',
            array_map(
                fn (string $arg): string => $this->formatDeclarationValue($arg),
                $this->splitTopLevel($args, ',')
            )
        );
    }

    private function normalizeCalcNumberLiterals(string $value): string
    {
        $length = strlen($value);
        $output = '';

        for ($i = 0; $i < $length;) {
            if (!$this->startsFunctionAt($value, $i, 'calc')) {
                $output .= $value[$i];
                $i++;
                continue;
            }

            $open = $i + 4;
            $close = $this->findMatchingParenthesis($value, $open);
            if ($close === null) {
                $output .= $value[$i];
                $i++;
                continue;
            }

            $content = substr($value, $open + 1, $close - $open - 1);
            $output .= 'calc(' . $this->normalizeLeadingZeroDecimals($content) . ')';
            $i = $close + 1;
        }

        return $output;
    }

    private function normalizeLeadingZeroDecimals(string $value): string
    {
        return preg_replace_callback(
            '/(?<![a-zA-Z0-9_.-])([+-]?)0+\.(\d+)([a-zA-Z%]+)?(?![a-zA-Z0-9_-])/',
            static function (array $matches): string {
                $fraction = rtrim($matches[2], '0');
                if ($fraction === '') {
                    return $matches[1] . '0' . ($matches[3] ?? '');
                }

                return $matches[1] . '.' . $fraction . ($matches[3] ?? '');
            },
            $value
        ) ?? $value;
    }

    private function quoteSimpleUrlFunctions(string $value): string
    {
        $length = strlen($value);
        $output = '';

        for ($i = 0; $i < $length;) {
            if (!$this->startsUrlFunctionAt($value, $i)) {
                $output .= $value[$i];
                $i++;
                continue;
            }

            $open = $i + 3;
            $close = $this->findMatchingParenthesis($value, $open);
            if ($close === null) {
                $output .= $value[$i];
                $i++;
                continue;
            }

            $raw = substr($value, $i, $close - $i + 1);
            $content = trim(substr($value, $open + 1, $close - $open - 1));
            if (!$this->isSimpleUnquotedUrlContent($content)) {
                $output .= $raw;
                $i = $close + 1;
                continue;
            }

            $output .= 'url(' . $this->quoteCssString($content) . ')';
            $i = $close + 1;
        }

        return $output;
    }

    private function startsUrlFunctionAt(string $value, int $offset): bool
    {
        return $this->startsFunctionAt($value, $offset, 'url');
    }

    private function startsFunctionAt(string $value, int $offset, string $name): bool
    {
        $length = strlen($name);
        if (strncasecmp(substr($value, $offset, $length + 1), $name . '(', $length + 1) !== 0) {
            return false;
        }

        return $offset === 0 || preg_match('/[a-zA-Z0-9_-]/', $value[$offset - 1]) !== 1;
    }

    private function findMatchingParenthesis(string $value, int $open): ?int
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
                continue;
            }

            if ($char === '\\') {
                $i++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function isSimpleUnquotedUrlContent(string $content): bool
    {
        if ($content === '' || $content[0] === '"' || $content[0] === "'") {
            return false;
        }

        return preg_match('/[\s()\\\\]/', $content) !== 1;
    }

    private function formatColorDeclarationValue(string $value): string
    {
        $formatted = $this->formatDeclarationValue($value);

        return match (strtolower($formatted)) {
            'blue' => '#00f',
            'yellow' => '#ff0',
            'black' => '#000',
            default => $formatted,
        };
    }

    private function formatPropertyDeclarationValue(string $property, string $value): string
    {
        $property = strtolower($property);
        if ($property === 'syntax') {
            return $this->formatPropertySyntaxValue($value);
        }

        if ($property === 'inherits') {
            return strtolower(trim($value));
        }

        return $this->formatDeclarationValue($value);
    }

    private function formatPropertySyntaxValue(string $value): string
    {
        $syntax = trim($value);
        if (strlen($syntax) >= 2 && (($syntax[0] === '"' && substr($syntax, -1) === '"') || ($syntax[0] === "'" && substr($syntax, -1) === "'"))) {
            $syntax = substr($syntax, 1, -1);
        }

        $syntax = trim(preg_replace('/\s+/', ' ', $syntax) ?? $syntax);
        $syntax = preg_replace('/\s*\|\s*/', ' | ', $syntax) ?? $syntax;
        $syntax = preg_replace('/\s*([#+])\s*/', '$1', $syntax) ?? $syntax;

        return '"' . str_replace('"', '\\"', $syntax) . '"';
    }

    private function formatStyleSelector(string $prelude): string
    {
        $selector = trim(preg_replace('/\s+/', ' ', $prelude) ?? $prelude);
        $output = '';
        $quote = null;
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
                $close = $this->findSelectorAttributeClose($selector, $i);
                if ($close !== null) {
                    $content = substr($selector, $i + 1, $close - $i - 1);
                    $output .= '[' . $this->formatSelectorAttributeContent($content) . ']';
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

    private function formatSelectorAttributeContent(string $content): string
    {
        if (preg_match('/^(.+?)\s*([~|^$*]?=)\s*(.+?)(?:\s+([a-zA-Z]))?$/s', trim($content), $matches) !== 1) {
            return $content;
        }

        $name = trim($matches[1]);
        if (str_starts_with($name, '|')) {
            $name = substr($name, 1);
        }

        $value = trim($matches[3]);
        if ($this->isCssStringToken($value)) {
            $value = substr($value, 1, -1);
        }

        $flag = isset($matches[4]) && $matches[4] !== '' ? ' ' . strtolower($matches[4]) : '';

        return $name . $matches[2] . $this->quoteCssString($value) . $flag;
    }

    private function quoteCssString(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function isPropertyRulePrelude(string $prelude): bool
    {
        return preg_match('/^@property\b/i', trim($prelude)) === 1;
    }

    private function isPositionTryRulePrelude(string $prelude): bool
    {
        return preg_match('/^@position-try\b/i', trim($prelude)) === 1;
    }

    private function isConditionalGroupPrelude(string $prelude): bool
    {
        return preg_match('/^@(media|layer|supports)\b/i', trim($prelude)) === 1;
    }

    private function propertyRuleName(string $prelude): string
    {
        if (preg_match('/^@property\b(.*)$/i', trim($prelude), $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid @property rule prelude: ' . $prelude);
        }

        $name = trim($matches[1]);
        if (preg_match('/^--[-_a-zA-Z0-9]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid @property name: {$name}");
        }

        return $name;
    }

    private function positionTryRuleName(string $prelude): string
    {
        if (preg_match('/^@position-try\b(.*)$/i', trim($prelude), $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid @position-try rule prelude: ' . $prelude);
        }

        $name = trim($matches[1]);
        if (preg_match('/^--[-_a-zA-Z0-9]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid @position-try name: {$name}");
        }

        return $name;
    }

    private function pageMarginAtRuleName(string $prelude): ?string
    {
        return preg_match('/^@([a-z-]+)\s*$/i', trim($prelude), $matches) === 1 ? strtolower($matches[1]) : null;
    }

    private function isPageMarginAtRuleName(string $name): bool
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
                    break;
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    /**
     * @return list<array{value:string, start:int, end:int}>
     */
    private function splitWhitespaceTokensWithOffsets(string $value): array
    {
        $tokens = [];
        $quote = null;
        $parenDepth = 0;
        $start = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($start === null && !ctype_space($char)) {
                $start = $i;
            }

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

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if (ctype_space($char) && $parenDepth === 0 && $start !== null) {
                $tokens[] = [
                    'value' => substr($value, $start, $i - $start),
                    'start' => $start,
                    'end' => $i,
                ];
                $start = null;
            }
        }

        if ($start !== null) {
            $tokens[] = [
                'value' => substr($value, $start),
                'start' => $start,
                'end' => $length,
            ];
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function splitWhitespaceTopLevel(string $value): array
    {
        return array_map(
            static fn (array $token): string => $token['value'],
            $this->splitWhitespaceTokensWithOffsets($value)
        );
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $css, string $separator): array
    {
        $parts = [];
        $start = 0;
        $quote = null;
        $parenDepth = 0;
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
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

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === $separator && $parenDepth === 0) {
                $parts[] = substr($css, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($css, $start);

        return $parts;
    }

    private function findNextTopLevel(string $css, string $needle, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
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
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === '{') {
                if ($braceDepth === 0 && $needle === '{') {
                    return $i;
                }
                $braceDepth++;
                continue;
            }

            if ($char === '}') {
                if ($braceDepth > 0) {
                    $braceDepth--;
                }
                continue;
            }

            if ($char === $needle && $parenDepth === 0 && $braceDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findNextTopLevelAtKeyword(string $css, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
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
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }

            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }

            if ($char === '}') {
                if ($braceDepth > 0) {
                    $braceDepth--;
                }
                continue;
            }

            if ($char === '@' && $parenDepth === 0 && $braceDepth === 0) {
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
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unclosed CSS block');
    }

    private function skipWhitespace(string $css, int $offset): int
    {
        $length = strlen($css);
        while ($offset < $length && ctype_space($css[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function indent(int $level): string
    {
        return str_repeat('  ', $level);
    }
}
