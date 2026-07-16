<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssDependencyAnalyzer
{
    /**
     * @return array{code:string,dependencies:list<array{type:string,url:string,placeholder:string}>}
     */
    public function analyze(string $css, string $filename = 'test.css'): array
    {
        $dependencies = [];
        $rewritten = $this->rewriteDependencies($css, $filename, $dependencies);
        $code = (new CssMinifier())->minify($rewritten);

        return [
            'code' => $this->quoteUrlPlaceholders($code, $dependencies),
            'dependencies' => $dependencies,
        ];
    }

    /**
     * @param list<array{type:string,url:string,placeholder:string}> $dependencies
     */
    private function quoteUrlPlaceholders(string $css, array $dependencies): string
    {
        foreach ($dependencies as $dependency) {
            if ($dependency['type'] !== 'url') {
                continue;
            }

            $placeholder = $dependency['placeholder'];
            $css = str_replace('url(' . $placeholder . ')', 'url("' . $placeholder . '")', $css);
        }

        return $css;
    }

    /**
     * @param list<array{type:string,url:string,placeholder:string}> $dependencies
     */
    private function rewriteDependencies(string $css, string $filename, array &$dependencies): string
    {
        $output = '';
        $length = strlen($css);
        $offset = 0;
        $quote = null;

        while ($offset < $length) {
            $char = $css[$offset];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $offset + 1 < $length) {
                    $output .= $css[++$offset];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                $offset++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                $offset++;
                continue;
            }

            if ($this->startsIdentifier($css, $offset, '@import')) {
                $import = $this->consumeImportSource($css, $offset, $filename, $dependencies);
                if ($import !== null) {
                    $output .= $import['replacement'];
                    $offset = $import['end'];
                    continue;
                }
            }

            $function = $this->consumeFunction($css, $offset, 'image-set')
                ?? $this->consumeFunction($css, $offset, '-webkit-image-set');
            if ($function !== null) {
                $output .= $function['name'] . '('
                    . $this->rewriteImageSetBody($function['body'], $filename, $dependencies)
                    . ')';
                $offset = $function['end'];
                continue;
            }

            $url = $this->consumeUrlFunction($css, $offset);
            if ($url !== null) {
                $output .= 'url("'
                    . $this->recordUrlDependency(
                        $url['url'],
                        $filename,
                        $dependencies,
                        $this->isCustomPropertyUrlContext($css, $offset)
                    )
                    . '")';
                $offset = $url['end'];
                continue;
            }

            $output .= $char;
            $offset++;
        }

        return $output;
    }

    /**
     * @param list<array{type:string,url:string,placeholder:string}> $dependencies
     */
    private function consumeImportSource(string $css, int $offset, string $filename, array &$dependencies): ?array
    {
        $cursor = $offset + strlen('@import');
        $cursor = $this->skipWhitespace($css, $cursor);
        if ($cursor >= strlen($css)) {
            return null;
        }

        $source = null;
        $end = $cursor;
        $quote = $css[$cursor];
        if ($quote === '"' || $quote === "'") {
            $string = $this->consumeQuotedString($css, $cursor);
            if ($string === null) {
                return null;
            }
            $source = $string['value'];
            $end = $string['end'];
        } else {
            $url = $this->consumeUrlFunction($css, $cursor);
            if ($url === null) {
                return null;
            }
            $source = $url['url'];
            $end = $url['end'];
        }

        $placeholder = $this->recordDependency('import', $source, $filename, $dependencies);

        return [
            'replacement' => substr($css, $offset, $cursor - $offset) . '"' . $placeholder . '"',
            'end' => $end,
        ];
    }

    /**
     * @param list<array{type:string,url:string,placeholder:string}> $dependencies
     */
    private function rewriteImageSetBody(string $body, string $filename, array &$dependencies): string
    {
        $items = $this->splitTopLevel($body, ',');
        foreach ($items as &$item) {
            $leading = strlen($item) - strlen(ltrim($item));
            $prefix = substr($item, 0, $leading);
            $candidate = substr($item, $leading);

            $url = $this->consumeUrlFunction($candidate, 0);
            if ($url !== null) {
                $placeholder = $this->recordUrlDependency($url['url'], $filename, $dependencies);
                $item = $prefix . '"' . $placeholder . '"' . substr($candidate, $url['end']);
                continue;
            }

            $quote = $candidate[0] ?? '';
            if ($quote === '"' || $quote === "'") {
                $string = $this->consumeQuotedString($candidate, 0);
                if ($string !== null) {
                    $placeholder = $this->recordUrlDependency($string['value'], $filename, $dependencies);
                    $item = $prefix . '"' . $placeholder . '"' . substr($candidate, $string['end']);
                }
            }
        }
        unset($item);

        return implode(',', $items);
    }

    /**
     * @param list<array{type:string,url:string,placeholder:string}> $dependencies
     */
    private function recordUrlDependency(
        string $url,
        string $filename,
        array &$dependencies,
        bool $customProperty = false
    ): string {
        return $this->recordDependency('url', $url, $filename, $dependencies, $customProperty);
    }

    /**
     * @param list<array{type:string,url:string,placeholder:string}> $dependencies
     */
    private function recordDependency(
        string $type,
        string $url,
        string $filename,
        array &$dependencies,
        bool $customProperty = false
    ): string
    {
        if ($type === 'url' && $customProperty && $this->isAmbiguousCustomPropertyUrl($url)) {
            throw new \InvalidArgumentException(
                "Ambiguous url('{$url}') in custom property. Use an absolute URL instead"
            );
        }

        $placeholder = CssModulesTransformer::hashCssModuleString($filename . '_' . $url);
        $dependencies[] = [
            'type' => $type,
            'url' => $url,
            'placeholder' => $placeholder,
        ];

        return $placeholder;
    }

    private function isAmbiguousCustomPropertyUrl(string $url): bool
    {
        $url = trim($url);

        return $url !== ''
            && !str_starts_with($url, '/')
            && !str_starts_with($url, '#')
            && preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) !== 1;
    }

    private function isCustomPropertyUrlContext(string $css, int $urlOffset): bool
    {
        $prefix = substr($css, 0, $urlOffset);
        $brace = strrpos($prefix, '{');
        $semicolon = strrpos($prefix, ';');
        $start = max($brace === false ? -1 : $brace, $semicolon === false ? -1 : $semicolon) + 1;
        $declaration = substr($css, $start, $urlOffset - $start);
        $colon = strpos($declaration, ':');
        if ($colon === false) {
            return false;
        }

        return str_starts_with(trim(substr($declaration, 0, $colon)), '--');
    }

    private function consumeFunction(string $css, int $offset, string $name): ?array
    {
        if (!$this->startsIdentifier($css, $offset, $name)) {
            return null;
        }

        $open = $offset + strlen($name);
        if (($css[$open] ?? null) !== '(') {
            return null;
        }

        $close = $this->findMatchingParen($css, $open);

        return [
            'name' => substr($css, $offset, strlen($name)),
            'body' => substr($css, $open + 1, $close - $open - 1),
            'end' => $close + 1,
        ];
    }

    private function consumeUrlFunction(string $css, int $offset): ?array
    {
        $function = $this->consumeFunction($css, $offset, 'url');
        if ($function === null) {
            return null;
        }

        $body = trim($function['body']);
        $quote = $body[0] ?? '';
        if ($quote === '"' || $quote === "'") {
            $string = $this->consumeQuotedString($body, 0);
            $url = $string === null ? trim($body, "\"' \t\r\n") : $string['value'];
        } else {
            $url = $body;
        }

        return [
            'url' => $url,
            'end' => $function['end'],
        ];
    }

    private function consumeQuotedString(string $css, int $offset): ?array
    {
        $quote = $css[$offset] ?? '';
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }

        $value = '';
        $length = strlen($css);
        for ($i = $offset + 1; $i < $length; $i++) {
            $char = $css[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $value .= $css[++$i];
                continue;
            }
            if ($char === $quote) {
                return ['value' => $value, 'end' => $i + 1];
            }
            $value .= $char;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
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
            if ($char === $delimiter && $depth === 0) {
                $parts[] = substr($value, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($value, $start);

        return $parts;
    }

    private function findMatchingParen(string $css, int $open): int
    {
        $depth = 1;
        $quote = null;
        $length = strlen($css);
        for ($i = $open + 1; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
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
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unclosed CSS function');
    }

    private function startsIdentifier(string $css, int $offset, string $identifier): bool
    {
        if (strncasecmp(substr($css, $offset, strlen($identifier)), $identifier, strlen($identifier)) !== 0) {
            return false;
        }

        $before = $offset > 0 ? $css[$offset - 1] : '';
        $after = $css[$offset + strlen($identifier)] ?? '';

        return !$this->isIdentifierChar($before) && !$this->isIdentifierChar($after);
    }

    private function isIdentifierChar(string $char): bool
    {
        return $char !== '' && preg_match('/[-_a-z0-9]/i', $char) === 1;
    }

    private function skipWhitespace(string $css, int $offset): int
    {
        while (isset($css[$offset]) && ctype_space($css[$offset])) {
            $offset++;
        }

        return $offset;
    }
}
