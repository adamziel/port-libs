<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CustomMediaTransformer
{
    /** @var array<string, string> */
    private array $definitions = [];

    /** @var array<string, array{line:int,column:int}> */
    private array $definitionLocations = [];

    private string $source = '';

    /** @var array{line:int,column:int}|null */
    private ?array $currentMediaLocation = null;

    /** @var list<array{start:int,end:int,sourceStart:int}> */
    private array $offsetMap = [];

    public function transform(string $css, bool $preserveDeclarations = false): string
    {
        $this->source = $css;
        [$definitions, $definitionLocations, $ranges] = $this->collectDefinitions($css);
        $this->definitions = $definitions;
        $this->definitionLocations = $definitionLocations;
        $this->currentMediaLocation = null;

        if (!$preserveDeclarations) {
            $css = $this->removeRanges($css, $ranges);
        } else {
            $this->offsetMap = [['start' => 0, 'end' => strlen($css), 'sourceStart' => 0]];
        }

        return $this->replaceMediaRules($this->replaceImportStatements($css));
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, array{line:int,column:int}>, 2: list<array{start:int,end:int}>}
     */
    private function collectDefinitions(string $css): array
    {
        $definitions = [];
        $definitionLocations = [];
        $ranges = [];
        $offset = 0;

        while (($position = $this->findAtKeyword($css, '@custom-media', $offset)) !== null) {
            $start = $position + strlen('@custom-media');
            $end = $this->findNextTopLevel($css, ';', $start);
            if ($end === null) {
                throw new \InvalidArgumentException('@custom-media rule is missing a terminating semicolon');
            }

            $prelude = trim(substr($css, $start, $end - $start));
            if (preg_match('/^(--[-_a-zA-Z0-9]+)\s+(.+)$/s', $prelude, $matches) !== 1) {
                throw new \InvalidArgumentException("Invalid @custom-media rule: {$prelude}");
            }

            $definitions[$matches[1]] = trim($matches[2]);
            $definitionLocations[$matches[1]] = $this->sourceLocation($position);
            $ranges[] = ['start' => $position, 'end' => $end + 1];
            $offset = $end + 1;
        }

        return [$definitions, $definitionLocations, $ranges];
    }

    /**
     * @param list<array{start:int,end:int}> $ranges
     */
    private function removeRanges(string $css, array $ranges): string
    {
        $output = '';
        $this->offsetMap = [];
        $cursor = 0;
        foreach ($ranges as $range) {
            if ($cursor < $range['start']) {
                $segment = substr($css, $cursor, $range['start'] - $cursor);
                $start = strlen($output);
                $output .= $segment;
                $this->offsetMap[] = [
                    'start' => $start,
                    'end' => strlen($output),
                    'sourceStart' => $cursor,
                ];
            }
            $cursor = $range['end'];
        }

        if ($cursor < strlen($css)) {
            $segment = substr($css, $cursor);
            $start = strlen($output);
            $output .= $segment;
            $this->offsetMap[] = [
                'start' => $start,
                'end' => strlen($output),
                'sourceStart' => $cursor,
            ];
        }

        return $output;
    }

    private function replaceMediaRules(string $css): string
    {
        $output = '';
        $cursor = 0;

        while (($position = $this->findAtKeyword($css, '@media', $cursor)) !== null) {
            $open = $this->findNextTopLevel($css, '{', $position + strlen('@media'));
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = trim(substr($css, $position + strlen('@media'), $open - ($position + strlen('@media'))));
            $previousMediaLocation = $this->currentMediaLocation;
            $this->currentMediaLocation = $this->sourceLocation($this->sourceOffsetForCurrentCss($position));
            $output .= substr($css, $cursor, $position - $cursor)
                . '@media '
                . $this->resolveMediaQueryList($prelude, [])
                . '{';
            $this->currentMediaLocation = $previousMediaLocation;
            $cursor = $open + 1;
        }

        return $output . substr($css, $cursor);
    }

    private function replaceImportStatements(string $css): string
    {
        $output = '';
        $cursor = 0;

        while (($position = $this->findAtKeyword($css, '@import', $cursor)) !== null) {
            $end = $this->findNextTopLevel($css, ';', $position + strlen('@import'));
            if ($end === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $statement = substr($css, $position, $end - $position);
            $replacement = $this->replaceImportStatementMediaTail($statement, $position);
            $output .= substr($css, $cursor, $position - $cursor) . $replacement;
            $cursor = $end;
        }

        return $output . substr($css, $cursor);
    }

    private function replaceImportStatementMediaTail(string $statement, int $position): string
    {
        $rest = substr($statement, strlen('@import'));
        $mediaOffset = $this->importMediaTailOffset($rest);
        if ($mediaOffset === null) {
            return $statement;
        }

        $prefix = substr($rest, 0, $mediaOffset);
        $media = trim(substr($rest, $mediaOffset));
        if ($media === '' || $this->collectCustomMediaReferences($media) === []) {
            return $statement;
        }

        $previousMediaLocation = $this->currentMediaLocation;
        $this->currentMediaLocation = $this->sourceLocation($this->sourceOffsetForCurrentCss($position));
        $resolved = $this->resolveMediaQueryList($media, []);
        $this->currentMediaLocation = $previousMediaLocation;

        return '@import' . $this->normalizeImportPrefixSource($prefix) . $resolved;
    }

    private function normalizeImportPrefixSource(string $prefix): string
    {
        $offset = $this->skipWhitespaceAndComments($prefix, 0);
        $open = $this->cssFunctionOpenOffset($prefix, $offset, 'url');
        if ($open === null) {
            return $prefix;
        }

        $close = $this->findMatchingDelimiter($prefix, $open, '(', ')');
        $rawSource = trim(substr($prefix, $open + 1, $close - $open - 1));
        $source = (($rawSource[0] ?? '') === '"' || ($rawSource[0] ?? '') === "'")
            ? $this->cssStringTokenValue($rawSource)
            : $this->decodeCssEscapes($rawSource);

        return substr($prefix, 0, $offset)
            . '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $source) . '"'
            . substr($prefix, $close + 1);
    }

    private function importMediaTailOffset(string $rest): ?int
    {
        $offset = $this->skipWhitespaceAndComments($rest, 0);
        $urlOpen = $this->cssFunctionOpenOffset($rest, $offset, 'url');
        if ($urlOpen !== null) {
            $offset = $this->findMatchingDelimiter($rest, $urlOpen, '(', ')') + 1;
        } elseif (($rest[$offset] ?? '') === '"' || ($rest[$offset] ?? '') === "'") {
            $offset = $this->readQuotedTokenEnd($rest, $offset);
        } else {
            return null;
        }

        while (true) {
            $offset = $this->skipWhitespaceAndComments($rest, $offset);
            $function = $this->readCssIdentifierToken($rest, $offset);
            if (
                $function !== null
                && (($rest[$function['end']] ?? '') === '(')
                && (strcasecmp($function['name'], 'supports') === 0 || strcasecmp($function['name'], 'layer') === 0)
            ) {
                $open = $function['end'];
                $offset = $this->findMatchingDelimiter($rest, $open, '(', ')') + 1;
                continue;
            }

            $identifier = $this->readCssIdentifierToken($rest, $offset);
            if ($identifier !== null && strcasecmp($identifier['name'], 'layer') === 0) {
                $next = $rest[$identifier['end']] ?? '';
                if ($next === '' || !$this->isIdentifierChar($next)) {
                    $offset = $identifier['end'];
                    continue;
                }
            }

            break;
        }

        return $offset;
    }

    private function skipWhitespaceAndComments(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length) {
            if (ctype_space($value[$offset])) {
                $offset++;
                continue;
            }

            if ($value[$offset] === '/' && ($value[$offset + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $offset + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Import rule contains an unbalanced comment');
                }
                $offset = $end + 2;
                continue;
            }

            break;
        }

        return $offset;
    }

    private function startsFunction(string $value, int $offset, string $name): bool
    {
        return $this->cssFunctionOpenOffset($value, $offset, $name) !== null;
    }

    private function cssFunctionOpenOffset(string $value, int $offset, string $name): ?int
    {
        $previous = $value[$offset - 1] ?? '';
        if ($previous !== '' && $this->isIdentifierChar($previous)) {
            return null;
        }

        $identifier = $this->readCssIdentifierToken($value, $offset);
        if ($identifier === null || strcasecmp($identifier['name'], $name) !== 0) {
            return null;
        }

        return ($value[$identifier['end']] ?? '') === '(' ? $identifier['end'] : null;
    }

    private function readQuotedTokenEnd(string $value, int $offset): int
    {
        $quote = $value[$offset];
        $length = strlen($value);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($value[$i] === '\\') {
                $i++;
                continue;
            }
            if ($value[$i] === $quote) {
                return $i + 1;
            }
        }

        throw new \InvalidArgumentException('Import rule contains an unbalanced string');
    }

    private function cssStringTokenValue(string $token): string
    {
        $token = trim($token);
        $quote = $token[0] ?? '';
        if (($quote !== '"' && $quote !== "'") || substr($token, -1) !== $quote) {
            return $token;
        }

        return $this->decodeCssEscapes(substr($token, 1, -1));
    }

    private function decodeCssEscapes(string $token): string
    {
        $output = '';
        $length = strlen($token);

        for ($i = 0; $i < $length; $i++) {
            $char = $token[$i];
            if ($char !== '\\') {
                $output .= $char;
                continue;
            }

            if ($i + 1 >= $length) {
                $output .= '\\';
                continue;
            }

            $next = $token[$i + 1];
            if ($next === "\r") {
                $i++;
                if (($token[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                continue;
            }

            if ($next === "\n" || $next === "\f") {
                $i++;
                continue;
            }

            if (!ctype_xdigit($next)) {
                $output .= $next;
                $i++;
                continue;
            }

            $hex = '';
            $cursor = $i + 1;
            while ($cursor < $length && strlen($hex) < 6 && ctype_xdigit($token[$cursor])) {
                $hex .= $token[$cursor];
                $cursor++;
            }

            if ($cursor < $length && ctype_space($token[$cursor])) {
                if ($token[$cursor] === "\r" && ($token[$cursor + 1] ?? '') === "\n") {
                    $cursor += 2;
                } else {
                    $cursor++;
                }
            }

            $output .= $this->codepointToUtf8((int) hexdec($hex));
            $i = $cursor - 1;
        }

        return $output;
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0 || $codepoint > 0x10ffff) {
            $codepoint = 0xfffd;
        }

        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_NOQUOTES, 'UTF-8');
    }

    private function readIdentifier(string $value, int $offset): string
    {
        $identifier = '';
        $length = strlen($value);
        for ($i = $offset; $i < $length; $i++) {
            if (!$this->isIdentifierChar($value[$i])) {
                break;
            }
            $identifier .= $value[$i];
        }

        return $identifier;
    }

    /**
     * @return array{name:string,end:int}|null
     */
    private function readCssIdentifierToken(string $value, int $offset): ?array
    {
        $length = strlen($value);
        if ($offset >= $length) {
            return null;
        }

        $cursor = $offset;
        $raw = '';
        while ($cursor < $length) {
            $char = $value[$cursor];
            if ($char === '\\') {
                if ($cursor + 1 >= $length) {
                    break;
                }

                $next = $value[$cursor + 1];
                if ($next === "\n" || $next === "\r" || $next === "\f") {
                    break;
                }

                $end = $this->cssEscapeEndOffset($value, $cursor);
                $raw .= substr($value, $cursor, $end - $cursor + 1);
                $cursor = $end + 1;
                continue;
            }

            if (!$this->isIdentifierChar($char)) {
                break;
            }

            $raw .= $char;
            $cursor++;
        }

        if ($raw === '') {
            return null;
        }

        return [
            'name' => $this->decodeCssEscapes($raw),
            'end' => $cursor,
        ];
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/[-_a-zA-Z0-9]/', $char) === 1;
    }

    /**
     * @param list<string> $stack
     */
    private function resolveMediaQueryList(string $queryList, array $stack): string
    {
        $parts = $this->splitTopLevel($queryList, ',');
        if ($parts === []) {
            return '';
        }

        return implode(', ', array_map(
            fn (string $query): string => $this->resolveSingleQuery($query, $stack),
            $parts
        ));
    }

    /**
     * @param list<string> $stack
     */
    private function resolveSingleQuery(string $query, array $stack): string
    {
        $this->validateSupportedCustomMediaBooleanLogic($query);

        $query = $this->normalizeWhitespace($this->resolveReferences(trim($query), $stack));
        $query = $this->simplifyNegatedFeatureRanges($query);
        $query = $this->simplifyDoubleNegation($query);
        $query = $this->simplifyDuplicateMediaTypes($query);

        return $this->normalizeWhitespace($query);
    }

    private function validateSupportedCustomMediaBooleanLogic(string $query): void
    {
        $references = $this->collectCustomMediaReferences($query);
        if ($references === []) {
            return;
        }

        $explicitSignature = $this->mediaTypeSignature($this->stripCustomMediaReferences($query));
        $referenceSignatures = [];
        foreach ($references as $reference) {
            $signatures = $this->customMediaTypeSignatures($reference, []);
            if (count($signatures) > 1) {
                throw CustomMediaException::unsupportedBooleanLogic($reference, $this->currentMediaLocation, $this->customMediaLocation($reference));
            }

            foreach ($signatures as $signature) {
                if ($referenceSignatures !== [] && !array_key_exists($signature, $referenceSignatures)) {
                    throw CustomMediaException::unsupportedBooleanLogic($reference, $this->currentMediaLocation, $this->customMediaLocation($reference));
                }
                $referenceSignatures[$signature] = $reference;
            }
        }

        $referenceSignature = array_key_first($referenceSignatures);
        if ($explicitSignature !== null && $referenceSignature !== null && $explicitSignature !== $referenceSignature) {
            $reference = $referenceSignatures[$referenceSignature] ?? null;
            throw CustomMediaException::unsupportedBooleanLogic($reference, $this->currentMediaLocation, $reference === null ? null : $this->customMediaLocation($reference));
        }
    }

    /**
     * @return list<string>
     */
    private function collectCustomMediaReferences(string $query): array
    {
        $references = [];
        $quote = null;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
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
                continue;
            }

            if ($char === '/' && ($query[$i + 1] ?? '') === '*') {
                $end = strpos($query, '*/', $i + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Media query contains an unbalanced comment');
                }
                $i = $end + 1;
                continue;
            }

            if ($char !== '(') {
                continue;
            }

            $close = $this->findMatchingDelimiter($query, $i, '(', ')');
            $inner = substr($query, $i + 1, $close - $i - 1);
            $trimmed = trim($inner);
            if (preg_match('/^--[-_a-zA-Z0-9]+$/', $trimmed) === 1) {
                $references[$trimmed] = true;
            } else {
                foreach ($this->collectCustomMediaReferences($inner) as $reference) {
                    $references[$reference] = true;
                }
            }

            $i = $close;
        }

        return array_keys($references);
    }

    private function stripCustomMediaReferences(string $query): string
    {
        $output = '';
        $quote = null;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $query[++$i];
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

            if ($char === '/' && ($query[$i + 1] ?? '') === '*') {
                $end = strpos($query, '*/', $i + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Media query contains an unbalanced comment');
                }
                $output .= substr($query, $i, $end - $i + 2);
                $i = $end + 1;
                continue;
            }

            if ($char !== '(') {
                $output .= $char;
                continue;
            }

            $close = $this->findMatchingDelimiter($query, $i, '(', ')');
            $inner = substr($query, $i + 1, $close - $i - 1);
            if (preg_match('/^\s*--[-_a-zA-Z0-9]+\s*$/', $inner) === 1) {
                $output .= '(custom-media)';
            } else {
                $output .= '(' . $this->stripCustomMediaReferences($inner) . ')';
            }
            $i = $close;
        }

        return $output;
    }

    /**
     * @param list<string> $stack
     * @return list<string>
     */
    private function customMediaTypeSignatures(string $name, array $stack): array
    {
        if (!array_key_exists($name, $this->definitions)) {
            throw CustomMediaException::notDefined($name, $this->currentMediaLocation);
        }
        if (in_array($name, $stack, true)) {
            throw CustomMediaException::circular($name, $this->currentMediaLocation);
        }

        $stack[] = $name;
        $signatures = [];
        foreach ($this->splitTopLevel($this->definitions[$name], ',') as $query) {
            $signature = $this->mediaTypeSignature($this->stripCustomMediaReferences($query));
            if ($signature !== null) {
                $signatures[$signature] = true;
            }

            foreach ($this->collectCustomMediaReferences($query) as $reference) {
                foreach ($this->customMediaTypeSignatures($reference, $stack) as $nestedSignature) {
                    $signatures[$nestedSignature] = true;
                }
            }
        }

        return array_keys($signatures);
    }

    private function mediaTypeSignature(string $query): ?string
    {
        $query = $this->normalizeWhitespace(trim($query));
        if (preg_match('/^(?:(not|only)\s+)?(screen|print|all)\b/i', $query, $matches) !== 1) {
            return null;
        }

        $medium = strtolower($matches[2]);
        $qualifier = strtolower($matches[1] ?? '');

        return $qualifier === 'not' ? 'not ' . $medium : $medium;
    }

    /**
     * @param list<string> $stack
     */
    private function resolveReferences(string $query, array $stack): string
    {
        $output = '';
        $quote = null;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $query[++$i];
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

            if ($char === '/' && ($query[$i + 1] ?? '') === '*') {
                $end = strpos($query, '*/', $i + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Media query contains an unbalanced comment');
                }
                $output .= substr($query, $i, $end - $i + 2);
                $i = $end + 1;
                continue;
            }

            if ($char !== '(') {
                $output .= $char;
                continue;
            }

            $close = $this->findMatchingDelimiter($query, $i, '(', ')');
            $inner = substr($query, $i + 1, $close - $i - 1);
            $trimmed = trim($inner);
            if (preg_match('/^--[-_a-zA-Z0-9]+$/', $trimmed) === 1) {
                $output .= $this->resolveCustomMedia($trimmed, $stack);
                $i = $close;
                continue;
            }

            $resolvedInner = $this->resolveSingleQuery($inner, $stack);
            $output .= $this->isBareMediaTypeExpression($resolvedInner) ? $resolvedInner : '(' . $resolvedInner . ')';
            $i = $close;
        }

        return $output;
    }

    /**
     * @param list<string> $stack
     */
    private function resolveCustomMedia(string $name, array $stack): string
    {
        if (!array_key_exists($name, $this->definitions)) {
            throw CustomMediaException::notDefined($name, $this->currentMediaLocation);
        }
        if (in_array($name, $stack, true)) {
            throw CustomMediaException::circular($name, $this->currentMediaLocation);
        }

        $stack[] = $name;
        $parts = $this->splitTopLevel($this->definitions[$name], ',');
        $resolved = array_map(
            fn (string $query): string => $this->resolveSingleQuery($query, $stack),
            $parts
        );

        if (count($resolved) === 1) {
            return $resolved[0];
        }

        $factored = $this->factorCommonMediaType($resolved);
        if ($factored !== null) {
            return $factored;
        }

        return '(' . implode(' or ', array_map(
            fn (string $query): string => $this->wrapForBoolean($query),
            $resolved
        )) . ')';
    }

    /**
     * @param list<string> $queries
     */
    private function factorCommonMediaType(array $queries): ?string
    {
        $medium = null;
        $features = [];

        foreach ($queries as $query) {
            if (preg_match('/^((?:not\s+)?(?:screen|print|all))\s+and\s+(.+)$/i', $query, $matches) !== 1) {
                return null;
            }

            $currentMedium = strtolower($this->normalizeWhitespace($matches[1]));
            if ($medium === null) {
                $medium = $currentMedium;
            } elseif ($medium !== $currentMedium) {
                return null;
            }

            $features[] = $this->wrapForBoolean($matches[2]);
        }

        return $medium . ' and (' . implode(' or ', $features) . ')';
    }

    private function wrapForBoolean(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '()';
        }
        if ($query[0] === '(' && $this->matchingOuterParentheses($query)) {
            return $query;
        }

        return '(' . $query . ')';
    }

    private function simplifyNegatedFeatureRanges(string $query): string
    {
        return preg_replace_callback(
            '/\bnot\s*\(\s*(min|max)-([_a-zA-Z-][_a-zA-Z0-9-]*)\s*:\s*([^)]+?)\s*\)/i',
            fn (array $matches): string => $this->simplifyNegatedLegacyRangeAlias($matches[0]),
            $query
        ) ?? $query;
    }

    private function simplifyNegatedLegacyRangeAlias(string $condition): string
    {
        $minified = (new MediaQueryParser())->minifyList($condition);
        if ($minified === '' || str_starts_with(strtolower($minified), 'not ')) {
            return $minified === '' ? $condition : $minified;
        }

        return '(' . $minified . ')';
    }

    private function simplifyDoubleNegation(string $query): string
    {
        do {
            $before = $query;
            $query = preg_replace('/\bnot\s+not\s+(screen|print|all)\b/i', '$1', $query) ?? $query;
            $query = preg_replace('/\bnot\s+not\s+(\([^()]+\))/i', '$1', $query) ?? $query;
        } while ($query !== $before);

        return $query;
    }

    private function simplifyDuplicateMediaTypes(string $query): string
    {
        do {
            $before = $query;
            $query = preg_replace('/\b(screen|print|all)\s+and\s+\1\s+and\b/i', '$1 and', $query) ?? $query;
            $query = preg_replace('/\bnot\s+(screen|print|all)\s+and\s+not\s+\1\s+and\b/i', 'not $1 and', $query) ?? $query;
        } while ($query !== $before);

        return $query;
    }

    private function isBareMediaTypeExpression(string $query): bool
    {
        return preg_match('/^(?:not\s+)?(?:screen|print|all)$/i', trim($query)) === 1;
    }

    private function matchingOuterParentheses(string $query): bool
    {
        try {
            return $this->findMatchingDelimiter($query, 0, '(', ')') === strlen($query) - 1;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * @return array{line:int,column:int}
     */
    private function sourceLocation(int $offset): array
    {
        $before = substr($this->source, 0, $offset);
        $line = substr_count($before, "\n");
        $lastNewline = strrpos($before, "\n");
        $column = $lastNewline === false ? $offset + 1 : $offset - $lastNewline;

        return ['line' => $line, 'column' => $column];
    }

    private function sourceOffsetForCurrentCss(int $offset): int
    {
        foreach ($this->offsetMap as $segment) {
            if ($offset >= $segment['start'] && $offset <= $segment['end']) {
                return $segment['sourceStart'] + ($offset - $segment['start']);
            }
        }

        return $offset;
    }

    /**
     * @return array{line:int,column:int}|null
     */
    private function customMediaLocation(string $name): ?array
    {
        return $this->definitionLocations[$name] ?? null;
    }

    private function findAtKeyword(string $css, string $keyword, int $start): ?int
    {
        $quote = null;
        $length = strlen($css);
        $keywordLength = strlen($keyword);

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
                continue;
            }
            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return null;
                }
                $i = $end + 1;
                continue;
            }
            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($css, $i);
                continue;
            }

            if (strncasecmp(substr($css, $i, $keywordLength), $keyword, $keywordLength) !== 0) {
                continue;
            }

            $after = $css[$i + $keywordLength] ?? '';
            if ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1) {
                continue;
            }

            return $i;
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
            } elseif ($char === '\\') {
                $i = $this->cssEscapeEndOffset($css, $i);
            } elseif ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return null;
                }
                $i = $end + 1;
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

    private function findMatchingDelimiter(string $source, int $open, string $left, string $right): int
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
            } elseif ($char === '\\') {
                $i = $this->cssEscapeEndOffset($source, $i);
            } elseif ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $end = strpos($source, '*/', $i + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Media query contains an unbalanced comment');
                }
                $i = $end + 1;
            } elseif ($char === $left) {
                $depth++;
            } elseif ($char === $right) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Media query contains unbalanced parentheses');
    }

    private function cssEscapeEndOffset(string $value, int $offset): int
    {
        $length = strlen($value);
        if ($offset + 1 >= $length) {
            return $offset;
        }

        $cursor = $offset + 1;
        $next = $value[$cursor];
        if (!ctype_xdigit($next)) {
            return $next === "\r" && ($value[$cursor + 1] ?? '') === "\n" ? $cursor + 1 : $cursor;
        }

        $digits = 0;
        while ($cursor < $length && $digits < 6 && ctype_xdigit($value[$cursor])) {
            $cursor++;
            $digits++;
        }

        if ($cursor < $length && ctype_space($value[$cursor])) {
            return $value[$cursor] === "\r" && ($value[$cursor + 1] ?? '') === "\n" ? $cursor + 1 : $cursor;
        }

        return $cursor - 1;
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
            } elseif ($char === '\\') {
                $end = $this->cssEscapeEndOffset($value, $i);
                $parts[array_key_last($parts)] .= substr($value, $i, $end - $i + 1);
                $i = $end;
                continue;
            } elseif ($char === '/' && ($value[$i + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $i + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Media query contains an unbalanced comment');
                }
                $parts[array_key_last($parts)] .= substr($value, $i, $end - $i + 2);
                $i = $end + 1;
                continue;
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
}
