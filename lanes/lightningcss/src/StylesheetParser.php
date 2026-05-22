<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class StylesheetParser
{
    /**
     * @return list<CssRule>
     */
    public function parse(string $css): array
    {
        return $this->parseRuleList($this->stripComments($css));
    }

    /**
     * @return list<CssRule>
     */
    private function parseRuleList(string $css): array
    {
        $rules = [];
        $length = strlen($css);
        $cursor = 0;

        while (true) {
            $cursor = $this->skipWhitespace($css, $cursor);
            if ($cursor >= $length) {
                break;
            }

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    if (!str_starts_with($statement, '@')) {
                        throw new \InvalidArgumentException("Top-level CSS statement is not an at-rule: {$statement}");
                    }
                    [$name, $prelude] = $this->parseAtPrelude($statement);
                    $rules[] = new CssRule(CssRule::TYPE_AT_RULE, $name, $prelude, [], [], []);
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $trailing = trim(substr($css, $cursor));
                if ($trailing !== '') {
                    throw new \InvalidArgumentException("Unexpected trailing CSS without a block: {$trailing}");
                }
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            if ($prelude === '') {
                throw new \InvalidArgumentException('CSS rule is missing a prelude');
            }
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            $rules[] = $this->parseBlockRule($prelude, $body);
            $cursor = $close + 1;
        }

        return $rules;
    }

    private function parseBlockRule(string $prelude, string $body): CssRule
    {
        if (str_starts_with($prelude, '@')) {
            [$name, $atPrelude] = $this->parseAtPrelude($prelude);
            $bodyParts = $this->parseBody($body);

            return new CssRule(CssRule::TYPE_AT_RULE, $name, $atPrelude, [], $bodyParts['declarations'], $bodyParts['rules']);
        }

        $bodyParts = $this->parseBody($body);

        return new CssRule(CssRule::TYPE_STYLE, null, $prelude, $this->splitTopLevel($prelude, ','), $bodyParts['declarations'], $bodyParts['rules']);
    }

    /**
     * @return array{declarations: array<string, string>, rules: list<CssRule>}
     */
    private function parseBody(string $body): array
    {
        $declarationSource = '';
        $rules = [];
        $cursor = 0;

        while (($open = $this->findNextTopLevel($body, '{', $cursor)) !== null) {
            $prefix = substr($body, $cursor, $open - $cursor);
            [$declarations, $prelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $declarationSource .= $declarations;
            $prelude = trim($prelude);
            if ($prelude === '') {
                throw new \InvalidArgumentException('Nested CSS rule is missing a prelude');
            }

            $close = $this->findMatchingBrace($body, $open);
            $rules[] = $this->parseBlockRule($prelude, substr($body, $open + 1, $close - $open - 1));
            $cursor = $close + 1;
        }

        $declarationSource .= substr($body, $cursor);
        $declarationSource = trim($declarationSource);
        $declarations = $declarationSource === '' ? [] : (new DeclarationBlock())->parse($declarationSource);

        return ['declarations' => $declarations, 'rules' => $rules];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDeclarationsAndNestedPrelude(string $prefix): array
    {
        $semicolon = $this->findLastTopLevel($prefix, ';');
        if ($semicolon !== null) {
            return [substr($prefix, 0, $semicolon + 1), substr($prefix, $semicolon + 1)];
        }

        $trimmed = trim($prefix);
        if ($trimmed === '' || str_starts_with($trimmed, '@') || str_starts_with($trimmed, '&')) {
            return ['', $prefix];
        }

        return ['', $prefix];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseAtPrelude(string $prelude): array
    {
        if (preg_match('/^@([_a-zA-Z][-_a-zA-Z0-9]*)(?:\s+(.*))?$/s', trim($prelude), $matches) !== 1) {
            throw new \InvalidArgumentException("Invalid CSS at-rule prelude: {$prelude}");
        }

        return [strtolower($matches[1]), trim($matches[2] ?? '')];
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

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
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

    private function findLastTopLevel(string $css, string $needle): ?int
    {
        $last = null;
        $offset = 0;
        while (($next = $this->findNextTopLevel($css, $needle, $offset)) !== null) {
            $last = $next;
            $offset = $next + 1;
        }

        return $last;
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

        throw new \InvalidArgumentException('CSS block is missing a closing brace');
    }

    private function skipWhitespace(string $css, int $offset): int
    {
        $length = strlen($css);
        while ($offset < $length && ctype_space($css[$offset])) {
            $offset++;
        }

        return $offset;
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
}
