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
        $entries = $this->parseEntries($block);
        $boxValue = $this->getBoxProperty($entries, $property);
        if ($boxValue !== null) {
            return $boxValue;
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

    public function setProperty(string $block, string $property, string $value, bool $important = false): string
    {
        $property = $this->normalizeProperty($property);
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('CSS declaration value cannot be empty');
        }

        $entries = $this->parseEntries($block);
        if ($this->isBoxLonghand($property)) {
            return $this->setBoxLonghand($entries, $property, $value, $important);
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

        return $this->serializeEntries($entries);
    }

    public function removeProperty(string $block, string $property): string
    {
        $property = $this->normalizeProperty($property);
        if ($this->isBoxShorthand($property)) {
            return $this->serializeEntries(array_values(array_filter(
                $this->parseEntries($block),
                fn (array $entry): bool => $entry['property'] !== $property
                    && !$this->isBoxLonghandFor($entry['property'], $property)
            )));
        }

        if ($this->isBoxLonghand($property)) {
            return $this->removeBoxLonghand($this->parseEntries($block), $property);
        }

        $entries = array_values(array_filter(
            $this->parseEntries($block),
            static fn (array $entry): bool => $entry['property'] !== $property
        ));

        return $this->serializeEntries($entries);
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
