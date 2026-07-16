<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class SelectorSpecificity
{
    /**
     * @return array{ids:int,classes:int,elements:int,packed:int}
     */
    public static function calculate(string $selector): array
    {
        $selector = trim($selector);
        if ($selector === '') {
            throw new \InvalidArgumentException('Selector must not be empty');
        }

        $best = ['ids' => 0, 'classes' => 0, 'elements' => 0];
        foreach (self::splitSelectorList($selector) as $part) {
            $specificity = self::calculateSingleSelector($part);
            if (self::compareParts($specificity, $best) > 0) {
                $best = $specificity;
            }
        }

        return $best + ['packed' => self::packParts($best)];
    }

    public static function packed(string $selector): int
    {
        return self::calculate($selector)['packed'];
    }

    public static function compare(string $left, string $right): int
    {
        $leftParts = self::calculate($left);
        $rightParts = self::calculate($right);

        return self::compareParts($leftParts, $rightParts);
    }

    /**
     * @return array{ids:int,classes:int,elements:int}
     */
    private static function calculateSingleSelector(string $selector): array
    {
        $specificity = ['ids' => 0, 'classes' => 0, 'elements' => 0];
        $length = strlen($selector);

        for ($i = 0; $i < $length;) {
            $char = $selector[$i];

            if ($char === '"' || $char === "'") {
                $i = self::skipString($selector, $i);
                continue;
            }

            if ($char === '[') {
                $specificity['classes']++;
                $i = self::findMatchingDelimiter($selector, $i, '[', ']') + 1;
                continue;
            }

            if ($char === '#') {
                $cursor = $i + 1;
                if (self::readIdentifier($selector, $cursor) !== '') {
                    $specificity['ids']++;
                    $i = $cursor;
                    continue;
                }
            }

            if ($char === '.') {
                $cursor = $i + 1;
                if (self::readIdentifier($selector, $cursor) !== '') {
                    $specificity['classes']++;
                    $i = $cursor;
                    continue;
                }
            }

            if ($char === ':') {
                $i = self::consumePseudoSelector($selector, $i, $specificity);
                continue;
            }

            if ($char === '*') {
                $i = self::consumeUniversalOrNamespacedType($selector, $i, $specificity);
                continue;
            }

            if ($char === '|') {
                $i = self::consumeExplicitNamespaceType($selector, $i, $specificity);
                continue;
            }

            if (self::isIdentifierStart($char)) {
                $i = self::consumeTypeSelector($selector, $i, $specificity);
                continue;
            }

            $i++;
        }

        return $specificity;
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $specificity
     */
    private static function consumePseudoSelector(string $selector, int $colon, array &$specificity): int
    {
        $length = strlen($selector);
        $doubleColon = ($colon + 1 < $length && $selector[$colon + 1] === ':');
        $cursor = $colon + ($doubleColon ? 2 : 1);
        $name = self::readIdentifier($selector, $cursor);
        if ($name === '') {
            return $colon + 1;
        }

        $lowerName = strtolower($name);
        if ($doubleColon || in_array($lowerName, ['before', 'after', 'first-line', 'first-letter'], true)) {
            $specificity['elements']++;

            return self::skipOptionalFunction($selector, $cursor);
        }

        if ($cursor < $length && $selector[$cursor] === '(') {
            $close = self::findMatchingDelimiter($selector, $cursor, '(', ')');
            $argument = substr($selector, $cursor + 1, $close - $cursor - 1);

            if ($lowerName === 'where') {
                return $close + 1;
            }

            if (in_array($lowerName, ['is', 'matches', 'not', 'has'], true)) {
                self::addParts($specificity, self::maxSpecificityForList($argument));

                return $close + 1;
            }

            if ($lowerName === 'nth-child' || $lowerName === 'nth-last-child') {
                $specificity['classes']++;
                $ofSelectorList = self::extractNthOfSelectorList($argument);
                if ($ofSelectorList !== null) {
                    self::addParts($specificity, self::maxSpecificityForList($ofSelectorList));
                }

                return $close + 1;
            }

            if ($lowerName === 'lang') {
                self::validateLangSelectorArgument($argument);
                $specificity['classes']++;

                return $close + 1;
            }

            $specificity['classes']++;

            return $close + 1;
        }

        $specificity['classes']++;

        return $cursor;
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $specificity
     */
    private static function consumeUniversalOrNamespacedType(string $selector, int $cursor, array &$specificity): int
    {
        $length = strlen($selector);
        if ($cursor + 1 >= $length || $selector[$cursor + 1] !== '|') {
            return $cursor + 1;
        }

        $cursor += 2;
        if ($cursor < $length && $selector[$cursor] === '*') {
            return $cursor + 1;
        }

        if (self::readIdentifier($selector, $cursor) !== '') {
            $specificity['elements']++;
        }

        return $cursor;
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $specificity
     */
    private static function consumeExplicitNamespaceType(string $selector, int $cursor, array &$specificity): int
    {
        $cursor++;
        if ($cursor < strlen($selector) && $selector[$cursor] === '*') {
            return $cursor + 1;
        }

        if (self::readIdentifier($selector, $cursor) !== '') {
            $specificity['elements']++;
        }

        return $cursor;
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $specificity
     */
    private static function consumeTypeSelector(string $selector, int $cursor, array &$specificity): int
    {
        self::readIdentifier($selector, $cursor);
        if ($cursor < strlen($selector) && $selector[$cursor] === '|') {
            $cursor++;
            if ($cursor < strlen($selector) && $selector[$cursor] === '*') {
                return $cursor + 1;
            }

            if (self::readIdentifier($selector, $cursor) !== '') {
                $specificity['elements']++;
            }

            return $cursor;
        }

        $specificity['elements']++;

        return $cursor;
    }

    private static function skipOptionalFunction(string $selector, int $cursor): int
    {
        if ($cursor < strlen($selector) && $selector[$cursor] === '(') {
            return self::findMatchingDelimiter($selector, $cursor, '(', ')') + 1;
        }

        return $cursor;
    }

    /**
     * @return array{ids:int,classes:int,elements:int}
     */
    private static function maxSpecificityForList(string $selectorList): array
    {
        $best = ['ids' => 0, 'classes' => 0, 'elements' => 0];
        foreach (self::splitSelectorList($selectorList) as $selector) {
            $specificity = self::calculateSingleSelector($selector);
            if (self::compareParts($specificity, $best) > 0) {
                $best = $specificity;
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private static function splitSelectorList(string $selectorList): array
    {
        $parts = [''];
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($selectorList);

        for ($i = 0; $i < $length; $i++) {
            $char = $selectorList[$i];
            if ($quote !== null) {
                $parts[array_key_last($parts)] .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $parts[array_key_last($parts)] .= $selectorList[++$i];
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
            } elseif ($char === ',' && $parenDepth === 0 && $bracketDepth === 0) {
                $parts[] = '';
                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private static function extractNthOfSelectorList(string $argument): ?string
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($argument);

        for ($i = 0; $i < $length; $i++) {
            $char = $argument[$i];
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
            } elseif (
                $parenDepth === 0
                && $bracketDepth === 0
                && strncasecmp(substr($argument, $i, 2), 'of', 2) === 0
                && !self::isIdentifierChar($argument[$i - 1] ?? '')
                && !self::isIdentifierChar($argument[$i + 2] ?? '')
            ) {
                $selectorList = trim(substr($argument, $i + 2));

                return $selectorList === '' ? null : $selectorList;
            }
        }

        return null;
    }

    private static function validateLangSelectorArgument(string $argument): void
    {
        $ranges = self::splitSelectorList($argument);
        if ($ranges === []) {
            throw new \InvalidArgumentException('Invalid :lang() selector argument');
        }

        foreach ($ranges as $range) {
            if (!self::isValidLangRange($range)) {
                throw new \InvalidArgumentException('Invalid :lang() selector argument');
            }
        }
    }

    private static function isValidLangRange(string $range): bool
    {
        $range = trim($range);
        if ($range === '') {
            return false;
        }

        if ($range[0] === '"' || $range[0] === "'") {
            return self::isCompleteQuotedString($range);
        }

        $length = strlen($range);
        $sawLanguageCharacter = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $range[$i];
            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    return false;
                }
                $i++;
                $sawLanguageCharacter = true;
                continue;
            }

            if ($char === '*') {
                $sawLanguageCharacter = true;
                continue;
            }

            if (!$sawLanguageCharacter && preg_match('/[0-9]/', $char) === 1) {
                return false;
            }

            if (!self::isIdentifierChar($char)) {
                return false;
            }

            $sawLanguageCharacter = true;
        }

        return $sawLanguageCharacter;
    }

    private static function isCompleteQuotedString(string $value): bool
    {
        $quote = $value[0];
        $length = strlen($value);
        for ($i = 1; $i < $length; $i++) {
            if ($value[$i] === '\\') {
                $i++;
                continue;
            }

            if ($value[$i] === $quote) {
                return $i === $length - 1;
            }
        }

        return false;
    }

    private static function findMatchingDelimiter(string $source, int $open, string $left, string $right): int
    {
        $depth = 1;
        $quote = null;
        $length = strlen($source);

        for ($i = $open + 1; $i < $length; $i++) {
            $char = $source[$i];
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
            } elseif ($char === $left) {
                $depth++;
            } elseif ($char === $right) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $length - 1;
    }

    private static function skipString(string $source, int $start): int
    {
        $quote = $source[$start];
        $length = strlen($source);
        for ($i = $start + 1; $i < $length; $i++) {
            if ($source[$i] === '\\') {
                $i++;
                continue;
            }
            if ($source[$i] === $quote) {
                return $i + 1;
            }
        }

        return $length;
    }

    private static function readIdentifier(string $source, int &$cursor): string
    {
        $length = strlen($source);
        $start = $cursor;
        if ($cursor >= $length || (!self::isIdentifierStart($source[$cursor]) && $source[$cursor] !== '\\')) {
            return '';
        }

        while ($cursor < $length) {
            if ($source[$cursor] === '\\' && $cursor + 1 < $length) {
                $cursor += 2;
                continue;
            }
            if (!self::isIdentifierChar($source[$cursor])) {
                break;
            }
            $cursor++;
        }

        return substr($source, $start, $cursor - $start);
    }

    private static function isIdentifierStart(string $char): bool
    {
        return $char !== '' && preg_match('/[A-Za-z_\x80-\xff-]/', $char) === 1;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return $char !== '' && preg_match('/[A-Za-z0-9_\x80-\xff-]/', $char) === 1;
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $target
     * @param array{ids:int,classes:int,elements:int} $addition
     */
    private static function addParts(array &$target, array $addition): void
    {
        $target['ids'] += $addition['ids'];
        $target['classes'] += $addition['classes'];
        $target['elements'] += $addition['elements'];
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $left
     * @param array{ids:int,classes:int,elements:int} $right
     */
    private static function compareParts(array $left, array $right): int
    {
        return [$left['ids'], $left['classes'], $left['elements']]
            <=> [$right['ids'], $right['classes'], $right['elements']];
    }

    /**
     * @param array{ids:int,classes:int,elements:int} $specificity
     */
    private static function packParts(array $specificity): int
    {
        return ($specificity['ids'] << 20) | ($specificity['classes'] << 10) | $specificity['elements'];
    }
}
