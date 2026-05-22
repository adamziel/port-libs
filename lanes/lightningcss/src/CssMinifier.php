<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssMinifier
{
    public function minify(string $css): string
    {
        $css = $this->stripComments($css);
        $output = '';
        $quote = null;
        $pendingSpace = false;
        $length = strlen($css);
        $tight = '{}:;,>+~()[]';

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
                if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                    $output .= ' ';
                }
                $pendingSpace = false;
                $quote = $char;
                $output .= $char;
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = true;
                continue;
            }

            if (str_contains($tight, $char)) {
                $output = rtrim($output);
                $output .= $char;
                $pendingSpace = false;
                continue;
            }

            if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                $output .= ' ';
            }
            $pendingSpace = false;
            $output .= $char;
        }

        return $this->minifyMediaQueries($this->minifyDeclarationValues(str_replace(';}', '}', trim($output))));
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
                    return $output;
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function needsSpaceBefore(string $output, string $next): bool
    {
        if ($output === '') {
            return false;
        }
        $previous = $output[strlen($output) - 1];
        return (ctype_alnum($previous) || $previous === '_' || $previous === '-')
            && (ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.');
    }

    private function minifyDeclarationValues(string $css): string
    {
        $output = '';
        $quote = null;
        $braceDepth = 0;
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

            if ($char === '{') {
                $braceDepth++;
                $output .= $char;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $output .= $char;
                continue;
            }

            if ($char !== ':' || $braceDepth === 0) {
                $output .= $char;
                continue;
            }

            $property = $this->currentPropertyCandidate($output);
            if (!$this->isDeclarationProperty($property)) {
                $output .= $char;
                continue;
            }

            [$value, $delimiter, $offset] = $this->readDeclarationValue($css, $i + 1);
            $output .= ':' . $this->minifyDeclarationValue($property, $value);
            if ($delimiter !== '') {
                if ($delimiter === '}') {
                    $braceDepth = max(0, $braceDepth - 1);
                }
                $output .= $delimiter;
            }
            $i = $offset;
        }

        return $output;
    }

    private function minifyMediaQueries(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);
        $parser = new MediaQueryParser();

        while ($cursor < $length) {
            $position = stripos($css, '@media', $cursor);
            if ($position === false) {
                $output .= substr($css, $cursor);
                break;
            }
            $before = $position === 0 ? '' : $css[$position - 1];
            $after = $css[$position + 6] ?? '';
            if (($before !== '' && preg_match('/[-_a-zA-Z0-9]/', $before) === 1)
                || ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1)
            ) {
                $output .= substr($css, $cursor, $position + 6 - $cursor);
                $cursor = $position + 6;
                continue;
            }

            $open = $this->findNextTopLevel($css, '{', $position + 6);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = trim(substr($css, $position + 6, $open - ($position + 6)));
            $output .= substr($css, $cursor, $position - $cursor) . '@media';
            if ($prelude !== '') {
                $output .= ' ' . $parser->minifyList($prelude);
            }
            $output .= '{';
            $cursor = $open + 1;
        }

        return $output;
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

    private function currentPropertyCandidate(string $output): string
    {
        $block = strrpos($output, '{');
        $semicolon = strrpos($output, ';');
        $start = max($block === false ? -1 : $block, $semicolon === false ? -1 : $semicolon) + 1;

        return trim(substr($output, $start));
    }

    private function isDeclarationProperty(string $property): bool
    {
        return preg_match('/^(?:[_a-zA-Z]|-[_a-zA-Z]|--[_a-zA-Z])[-_a-zA-Z0-9]*$/', $property) === 1;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function readDeclarationValue(string $css, int $start): array
    {
        $value = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($css);

        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $value .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $value .= $css[++$i];
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
            } elseif (($char === ';' || $char === '}') && $parenDepth === 0 && $bracketDepth === 0) {
                return [$value, $char, $i];
            }

            $value .= $char;
        }

        return [$value, '', $length - 1];
    }

    private function minifyDeclarationValue(string $property, string $value): string
    {
        $value = $this->normalizeMathFunctionOperators($value);
        $value = $this->minifyAnimationLonghandValue($property, $value);
        $value = $this->minifyTransitionLonghandValue($property, $value);
        if (!str_starts_with($property, '--')) {
            $value = $this->minifyColorKeywords($value);
        }

        return $value;
    }

    private function minifyAnimationLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'animation-duration',
            'animation-delay' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTimeToken($part)),
            'animation-iteration-count' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationIterationCount($part)),
            'animation-direction',
            'animation-play-state',
            'animation-fill-mode',
            'animation-composition' => $this->mapCommaList($value, static fn (string $part): string => strtolower(trim($part))),
            default => $value,
        };
    }

    private function minifyTransitionLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'transition-duration',
            'transition-delay' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTimeValue($part)),
            'transition-timing-function' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionTimingFunction($part)),
            default => $value,
        };
    }

    private function minifyTimeValue(string $value): string
    {
        $value = trim($value);
        $time = $this->evaluateTimeCalc($value);

        return $time === null ? $this->minifyTimeToken($value) : $this->shortestTime($time);
    }

    private function minifyTimeToken(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(ms|s)$/i', $value, $matches) !== 1) {
            return $value;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);

        return $this->shortestTime($unit === 'ms' ? $number / 1000 : $number);
    }

    private function minifyAnimationIterationCount(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'infinite') {
            return $value;
        }

        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1
            ? $this->minifyNumber((float) $value)
            : $value;
    }

    private function minifyTransitionTimingFunction(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^cubic-bezier\((.*)\)$/', $value, $matches) === 1) {
            $numbers = array_map('trim', explode(',', $matches[1]));
            if (count($numbers) !== 4) {
                return $value;
            }

            $canonical = implode(',', array_map(fn (string $number): string => $this->minifyNumber((float) $number), $numbers));

            return match ($canonical) {
                '.25,.1,.25,1' => 'ease',
                '.42,0,1,1' => 'ease-in',
                '0,0,.58,1' => 'ease-out',
                '.42,0,.58,1' => 'ease-in-out',
                default => 'cubic-bezier(' . $canonical . ')',
            };
        }

        if (preg_match('/^steps\(\s*([+-]?(?:\d+|\d*\.\d+))\s*,\s*([^)]+)\)$/', $value, $matches) === 1) {
            $count = $this->minifyNumber((float) $matches[1]);
            $position = trim(strtolower($matches[2]));
            $position = match ($position) {
                'jump-start' => 'start',
                'jump-end' => 'end',
                default => $position,
            };

            if ($count === '1' && $position === 'start') {
                return 'step-start';
            }
            if ($count === '1' && $position === 'end') {
                return 'step-end';
            }

            return 'steps(' . $count . ',' . $position . ')';
        }

        return $value;
    }

    private function shortestTime(float $seconds): string
    {
        $secondsValue = $this->minifyNumber($seconds) . 's';
        $millisecondsValue = $this->minifyNumber($seconds * 1000) . 'ms';

        return strlen($millisecondsValue) <= strlen($secondsValue) ? $millisecondsValue : $secondsValue;
    }

    private function evaluateTimeCalc(string $value): ?float
    {
        if (preg_match('/^calc\((.*)\)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $tokens = $this->tokenizeTimeExpression($matches[1]);
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $result = $this->parseTimeExpression($tokens, $offset);
        if ($result === null || $offset !== count($tokens) || $result['kind'] !== 'time') {
            return null;
        }

        return $result['value'];
    }

    /**
     * @return list<array{type:string,value:string}>
     */
    private function tokenizeTimeExpression(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        for ($i = 0; $i < $length;) {
            $char = $expression[$i];
            if (ctype_space($char)) {
                $i++;
                continue;
            }
            if (str_contains('()+-*', $char)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $i++;
                continue;
            }
            if (preg_match('/\G(?:\d+|\d*\.\d+)(?:ms|s)?/Ai', $expression, $matches, 0, $i) === 1) {
                $tokens[] = ['type' => 'number', 'value' => $matches[0]];
                $i += strlen($matches[0]);
                continue;
            }

            return [];
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeExpression(array $tokens, int &$offset): ?array
    {
        $value = $this->parseTimeProduct($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && in_array($tokens[$offset]['type'], ['+', '-'], true)) {
            $operator = $tokens[$offset++]['type'];
            $right = $this->parseTimeProduct($tokens, $offset);
            if ($right === null) {
                return null;
            }
            if ($value['kind'] !== 'time' || $right['kind'] !== 'time') {
                return null;
            }
            $value = [
                'value' => $operator === '+' ? $value['value'] + $right['value'] : $value['value'] - $right['value'],
                'kind' => 'time',
            ];
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeProduct(array $tokens, int &$offset): ?array
    {
        $value = $this->parseTimeFactor($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && $tokens[$offset]['type'] === '*') {
            $offset++;
            $right = $this->parseTimeFactor($tokens, $offset);
            if ($right === null) {
                return null;
            }
            if ($value['kind'] === 'time' && $right['kind'] === 'time') {
                return null;
            }
            $value = [
                'value' => $value['value'] * $right['value'],
                'kind' => $value['kind'] === 'time' || $right['kind'] === 'time' ? 'time' : 'number',
            ];
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeFactor(array $tokens, int &$offset): ?array
    {
        if ($offset >= count($tokens)) {
            return null;
        }

        $token = $tokens[$offset++];
        if ($token['type'] === '+') {
            return $this->parseTimeFactor($tokens, $offset);
        }
        if ($token['type'] === '-') {
            $value = $this->parseTimeFactor($tokens, $offset);

            return $value === null ? null : ['value' => -$value['value'], 'kind' => $value['kind']];
        }
        if ($token['type'] === '(') {
            $value = $this->parseTimeExpression($tokens, $offset);
            if ($value === null || ($tokens[$offset]['type'] ?? null) !== ')') {
                return null;
            }
            $offset++;

            return $value;
        }
        if ($token['type'] !== 'number') {
            return null;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(ms|s)?$/i', $token['value'], $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2] ?? '');

        if ($unit === '') {
            return ['value' => $number, 'kind' => 'number'];
        }

        return ['value' => $unit === 'ms' ? $number / 1000 : $number, 'kind' => 'time'];
    }

    private function minifyNumber(float $number): string
    {
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $formatted = rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');
        if (str_starts_with($formatted, '0.')) {
            return substr($formatted, 1);
        }
        if (str_starts_with($formatted, '-0.')) {
            return '-' . substr($formatted, 2);
        }

        return $formatted;
    }

    private function mapCommaList(string $value, callable $mapper): string
    {
        return implode(',', array_map(
            static fn (string $part): string => $mapper($part),
            $this->splitTopLevel($value, ',')
        ));
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

    private function normalizeMathFunctionOperators(string $value): string
    {
        $output = '';
        $quote = null;
        $functionStack = [];
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
                $next = $i + strlen($identifier);
                if (($value[$next] ?? '') === '(') {
                    $parentIsMath = end($functionStack) === true;
                    $functionStack[] = $parentIsMath || in_array(strtolower($identifier), ['calc', 'clamp', 'max', 'min'], true);
                    $output .= $identifier . '(';
                    $i = $next;
                    continue;
                }
                $output .= $identifier;
                $i = $next - 1;
                continue;
            }

            if ($char === ')') {
                array_pop($functionStack);
                $output = rtrim($output) . ')';
                continue;
            }

            if (($char === '+' || $char === '-') && end($functionStack) === true && $this->isBinaryMathOperator($value, $i)) {
                $output = rtrim($output) . ' ' . $char . ' ';
                while (isset($value[$i + 1]) && ctype_space($value[$i + 1])) {
                    $i++;
                }
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function isBinaryMathOperator(string $value, int $offset): bool
    {
        $previous = $this->previousNonSpace($value, $offset - 1);
        $next = $this->nextNonSpace($value, $offset + 1);
        if ($previous === null || $next === null) {
            return false;
        }

        if ($this->isExponentSign($value, $offset)) {
            return false;
        }

        return preg_match('/[a-zA-Z0-9_%)]/', $previous) === 1
            && preg_match('/[a-zA-Z0-9_.(-]/', $next) === 1;
    }

    private function isExponentSign(string $value, int $offset): bool
    {
        $previous = $value[$offset - 1] ?? '';
        if ($previous !== 'e' && $previous !== 'E') {
            return false;
        }

        $beforeExponent = $value[$offset - 2] ?? '';
        $afterSign = $value[$offset + 1] ?? '';

        return ctype_digit($beforeExponent) && ctype_digit($afterSign);
    }

    private function previousNonSpace(string $value, int $offset): ?string
    {
        for ($i = $offset; $i >= 0; $i--) {
            if (!ctype_space($value[$i])) {
                return $value[$i];
            }
        }

        return null;
    }

    private function nextNonSpace(string $value, int $offset): ?string
    {
        $length = strlen($value);
        for ($i = $offset; $i < $length; $i++) {
            if (!ctype_space($value[$i])) {
                return $value[$i];
            }
        }

        return null;
    }

    private function minifyColorKeywords(string $value): string
    {
        $colors = [
            'aqua' => '#0ff',
            'black' => '#000',
            'blue' => '#00f',
            'chartreuse' => '#7fff00',
            'cyan' => '#0ff',
            'fuchsia' => '#f0f',
            'magenta' => '#f0f',
            'transparent' => '#0000',
            'white' => '#fff',
            'yellow' => '#ff0',
        ];
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

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $lower = strtolower($identifier);
                $previous = $value[$i - 1] ?? '';
                $next = $value[$i + strlen($identifier)] ?? '';
                if ($previous === '-' || $next === '(') {
                    $output .= $identifier;
                    $i += strlen($identifier) - 1;
                    continue;
                }

                $output .= $colors[$lower] ?? $identifier;
                $i += strlen($identifier) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function startsUrlFunction(string $value, int $offset): bool
    {
        if (strtolower(substr($value, $offset, 4)) !== 'url(') {
            return false;
        }

        $previous = $value[$offset - 1] ?? '';

        return $previous === '' || !$this->isIdentifierChar($previous);
    }

    /**
     * @return array{0: string, 1: int}
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

    private function readIdentifier(string $value, int $start): string
    {
        $length = strlen($value);
        $identifier = '';
        for ($i = $start; $i < $length; $i++) {
            if (!$this->isIdentifierChar($value[$i])) {
                break;
            }
            $identifier .= $value[$i];
        }

        return $identifier;
    }

    private function isIdentifierStart(string $char): bool
    {
        return preg_match('/[a-zA-Z_]/', $char) === 1;
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/[-a-zA-Z0-9_]/', $char) === 1;
    }
}
