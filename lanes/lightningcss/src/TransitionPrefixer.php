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

    private function rewriteRuleList(string $css): string
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
                $output .= $prelude . '{' . $this->rewriteRuleList($body) . '}';
            } else {
                $output .= $this->rewriteStyleRule($prelude, $body);
            }
            $cursor = $close + 1;
        }

        return $output;
    }

    private function rewriteStyleRule(string $selectors, string $body): string
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
            $this->rewriteTransformTransitionEntries($ltrEntries);
            $this->rewriteTransformTransitionEntries($rtlEntries);

            return $this->selectorVariant($selectors, 'ltr-webkit') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selectors, 'ltr-modern') . '{' . $this->serializeDeclarations($ltrEntries) . '}'
                . $this->selectorVariant($selectors, 'rtl-webkit') . '{' . $this->serializeDeclarations($rtlEntries) . '}'
                . $this->selectorVariant($selectors, 'rtl-modern') . '{' . $this->serializeDeclarations($rtlEntries) . '}';
        }

        if ($this->rewriteTransformTransitionEntries($entries)) {
            return $selectors . '{' . $this->serializeDeclarations($entries) . '}';
        }

        return $selectors . '{' . $body . '}';
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
    private function rewriteTransformTransitionEntries(array &$entries): bool
    {
        $changed = false;
        $rewritten = [];

        foreach ($entries as $entry) {
            if ($entry['important']) {
                $rewritten[] = $entry;
                continue;
            }

            if ($entry['property'] === 'transition') {
                [$value, $entryChanged] = $this->rewriteTransformTransitionShorthand($entry['value']);
                if ($entryChanged) {
                    $rewritten[] = [
                        'property' => '-webkit-transition',
                        'name' => '-webkit-transition',
                        'value' => $value,
                        'important' => false,
                    ];
                    $entry['value'] = $value;
                    $changed = true;
                }
                $rewritten[] = $entry;
                continue;
            }

            if ($entry['property'] === 'transition-property') {
                [$value, $entryChanged] = $this->rewriteTransformTransitionPropertyList($entry['value']);
                if ($entryChanged) {
                    $rewritten[] = [
                        'property' => '-webkit-transition-property',
                        'name' => '-webkit-transition-property',
                        'value' => $value,
                        'important' => false,
                    ];
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
     * @return array{0:string,1:bool}
     */
    private function rewriteTransformTransitionPropertyList(string $value): array
    {
        $changed = false;
        $parts = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            if (strtolower($part) === 'transform') {
                $parts[] = '-webkit-transform';
                $parts[] = 'transform';
                $changed = true;
            } else {
                $parts[] = trim($part);
            }
        }

        return [implode(',', $parts), $changed];
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function rewriteTransformTransitionShorthand(string $value): array
    {
        $changed = false;
        $layers = [];
        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $tokens = $this->splitWhitespaceTopLevel($layer);
            $propertyIndex = $this->transitionPropertyTokenIndex($tokens);
            if ($propertyIndex !== null && strtolower($tokens[$propertyIndex]) === 'transform') {
                $prefixed = $tokens;
                $prefixed[$propertyIndex] = '-webkit-transform';
                $layers[] = implode(' ', $prefixed);
                $tokens[$propertyIndex] = 'transform';
                $changed = true;
            }
            $layers[] = implode(' ', $tokens);
        }

        return [implode(',', $layers), $changed];
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
