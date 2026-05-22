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
        if (!str_starts_with($property, '--')) {
            $value = $this->minifyColorKeywords($value);
        }

        return $value;
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
