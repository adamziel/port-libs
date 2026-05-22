<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class DeclarationBlock
{
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
        $match = null;
        foreach ($this->parseEntries($block) as $entry) {
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
