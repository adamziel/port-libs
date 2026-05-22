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
            $declarations[$property] = $value;
        }

        return $declarations;
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
}

