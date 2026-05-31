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
    ];

    private const BACKGROUND_LONGHANDS = [
        'background-color',
        'background-image',
        'background-position',
        'background-position-x',
        'background-position-y',
        'background-size',
        'background-repeat',
    ];

    private const BORDER_SIDES = ['top', 'right', 'bottom', 'left'];
    private const BORDER_COMPONENTS = ['width', 'style', 'color'];
    private const BORDER_STYLES = ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];
    private const BORDER_WIDTH_KEYWORDS = ['thin', 'medium', 'thick'];

    private const FLEX_DIRECTIONS = ['row', 'row-reverse', 'column', 'column-reverse'];
    private const FLEX_WRAPS = ['nowrap', 'wrap', 'wrap-reverse'];
    private const ANIMATION_DIRECTIONS = ['normal', 'reverse', 'alternate', 'alternate-reverse'];
    private const ANIMATION_FILL_MODES = ['none', 'forwards', 'backwards', 'both'];
    private const ANIMATION_PLAY_STATES = ['running', 'paused'];
    private const ANIMATION_TIMING_FUNCTIONS = ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'];
    private const ANIMATION_COMPOSITIONS = ['replace', 'add', 'accumulate'];
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
        $flexValue = $this->getFlexProperty($entries, $property);
        if ($flexValue !== null) {
            return $flexValue;
        }
        $animationValue = $this->getAnimationProperty($entries, $property);
        if ($animationValue !== null) {
            return $animationValue;
        }
        $transitionValue = $this->getTransitionProperty($entries, $property);
        if ($transitionValue !== null) {
            return $transitionValue;
        }
        if ($this->isTransitionProperty($property)) {
            return null;
        }
        $maskBorderValue = $this->getMaskBorderProperty($entries, $property);
        if ($maskBorderValue !== null) {
            return $maskBorderValue;
        }
        $gridValue = $this->getGridProperty($entries, $property);
        if ($gridValue !== null) {
            return $gridValue;
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

        $components = $this->resolveFlexComponents($entries, $prefix);
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

        if ($base === 'flex-wrap') {
            return $components['wrap'];
        }

        return null;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getAnimationProperty(array $entries, string $property): ?array
    {
        if ($property !== 'animation-name') {
            return null;
        }

        $match = null;
        foreach ($entries as $entry) {
            if ($entry['property'] === 'animation-name') {
                $match = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
                continue;
            }

            if ($entry['property'] !== 'animation') {
                continue;
            }

            $names = [];
            foreach ($this->splitTopLevel($entry['value'], ',') as $layer) {
                $parts = $this->parseAnimationLayer($layer);
                $names[] = $parts['name'] ?? 'none';
            }

            $match = [
                'value' => implode(', ', $names),
                'important' => $entry['important'],
            ];
        }

        return $match;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{value:string, important:bool}|null
     */
    private function getMaskBorderProperty(array $entries, string $property): ?array
    {
        if ($property !== 'mask-border-source') {
            return null;
        }

        $match = null;
        foreach ($entries as $entry) {
            if ($entry['property'] === 'mask-border-source') {
                $match = [
                    'value' => $entry['value'],
                    'important' => $entry['important'],
                ];
                continue;
            }

            if ($entry['property'] !== 'mask-border') {
                continue;
            }

            $source = $this->parseMaskBorderSource($entry['value']);
            if ($source === null) {
                continue;
            }

            $match = [
                'value' => $source,
                'important' => $entry['important'],
            ];
        }

        return $match;
    }

    private function parseMaskBorderSource(string $value): ?string
    {
        foreach ($this->splitWhitespaceTopLevel($value) as $token) {
            if ($this->isMaskBorderSourceToken($token)) {
                return $token;
            }
        }

        return null;
    }

    private function isMaskBorderSourceToken(string $token): bool
    {
        return strtolower($token) === 'none' || $this->isBackgroundImageToken($token);
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

            return ['value' => implode(' / ', $value), 'important' => $important];
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
        if (count($parts) !== 4) {
            return null;
        }

        return array_combine(self::GRID_AREA_COMPONENTS, $parts) ?: null;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseGridLineShorthand(string $value): ?array
    {
        $parts = $this->splitGridPlacement($value);
        if (count($parts) !== 2) {
            return null;
        }

        return [$parts[0], $parts[1]];
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
            'value' => $start['value'] . ' / ' . $end['value'],
            'important' => $start['important'],
        ];
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
    private function resolveFlexComponents(array $entries, string $prefix): array
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
        if (str_starts_with($property, '-webkit-flex-')) {
            return '-webkit-';
        }

        if (str_starts_with($property, 'flex-')) {
            return '';
        }

        return null;
    }

    private function baseFlexProperty(string $property): ?string
    {
        if (str_starts_with($property, '-webkit-')) {
            $property = substr($property, strlen('-webkit-'));
        }

        return in_array($property, ['flex-flow', 'flex-direction', 'flex-wrap'], true)
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
     *     repeat:?string
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
     *     repeat:?string
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
            } elseif ($this->isBackgroundColorToken($token)) {
                $parsed['color'] = $token;
            } else {
                $positionTokens[] = $token;
            }
        }

        if ($positionTokens !== []) {
            $parsed['position'] = implode(' ', $positionTokens);
            $parsed['positionX'] = $positionTokens[0] ?? null;
            $parsed['positionY'] = count($positionTokens) > 1 ? implode(' ', array_slice($positionTokens, 1)) : null;
        }

        return $parsed;
    }

    /**
     * @param list<array{raw:string,image:?string,color:?string,position:?string,positionX:?string,positionY:?string,size:?string,repeat:?string}> $layers
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
            && !in_array(strtolower($token), ['left', 'right', 'top', 'bottom', 'center', 'scroll', 'fixed', 'local', 'border-box', 'padding-box', 'content-box', 'cover', 'contain', 'none'], true);
    }

    private function isBackgroundRepeatToken(string $token): bool
    {
        return in_array($token, ['repeat', 'no-repeat', 'space', 'round', 'repeat-x', 'repeat-y'], true);
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
        $flexValue = $this->setFlexLonghand($entries, $property, $value, $important);
        if ($flexValue !== null) {
            return $this->parseEntries($flexValue);
        }
        $transitionValue = $this->setTransitionLonghand($entries, $property, $value, $important);
        if ($transitionValue !== null) {
            return $this->parseEntries($transitionValue);
        }
        $animationValue = $this->setAnimationNameLonghand($entries, $property, $value, $important);
        if ($animationValue !== null) {
            return $this->parseEntries($animationValue);
        }
        $logicalBoxValue = $this->setLogicalBoxProperty($entries, $property, $value, $important);
        if ($logicalBoxValue !== null) {
            return $this->parseEntries($logicalBoxValue);
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
    private function setFlexLonghand(array $entries, string $property, string $value, bool $important): ?string
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);
        if ($prefix === null || !in_array($base, ['flex-direction', 'flex-wrap'], true)) {
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
    private function setLogicalBoxProperty(array $entries, string $property, string $value, bool $important): ?string
    {
        $shorthand = $this->boxShorthandForLogicalProperty($property);
        if ($shorthand === null) {
            return null;
        }

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            if ($this->isPhysicalBoxPropertyFor($entries[$index]['property'], $shorthand)) {
                break;
            }

            if ($entries[$index]['property'] !== $property) {
                continue;
            }

            $entries[$index] = [
                'property' => $property,
                'value' => $value,
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

        if ($this->isBoxLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeBoxLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeBoxLonghand($importantEntries, $property));

            return $this->serializeEntries(array_merge($normalEntries, $importantEntries));
        }
        if ($this->isBorderComponentLonghand($property)) {
            $normalEntries = $this->parseEntries($this->removeBorderComponentLonghand($normalEntries, $property));
            $importantEntries = $this->parseEntries($this->removeBorderComponentLonghand($importantEntries, $property));

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

    private function isRemovableFlexLonghand(string $property): bool
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);

        return $prefix !== null && in_array($base, ['flex-direction', 'flex-wrap'], true);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function removeFlexLonghand(array $entries, string $property): ?string
    {
        $prefix = $this->flexPrefixForProperty($property);
        $base = $this->baseFlexProperty($property);
        if ($prefix === null || !in_array($base, ['flex-direction', 'flex-wrap'], true)) {
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

    private function boxShorthandForLogicalProperty(string $property): ?string
    {
        foreach (array_keys(self::BOX_SHORTHANDS) as $shorthand) {
            if ($this->isLogicalBoxPropertyFor($property, $shorthand)) {
                return $shorthand;
            }
        }

        return null;
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
