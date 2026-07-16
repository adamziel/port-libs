<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class JsModuleAnalyzer
{
    /**
     * @var list<Token>
     */
    private array $tokens = [];
    private string $source = '';

    public function analyze(string $source): ModuleAnalysis
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($this->sourceWithNoSubstitutionTemplateStrings($source));
        $imports = [];
        $exports = [];
        $deadControlFlowRanges = $this->deadControlFlowRanges();

        $count = count($this->tokens);
        $depth = 0;
        for ($index = 0; $index < $count; $index++) {
            $token = $this->tokens[$index];
            if ($token->kind !== 'identifier') {
                $depth = $this->adjustDepth($depth, $token);
                continue;
            }

            if ($token->text === 'require') {
                foreach ($this->parseCommonJsRequire($index, $deadControlFlowRanges) as $commonJsImport) {
                    $imports[] = $commonJsImport;
                }
                $depth = $this->adjustDepth($depth, $token);
                continue;
            }

            if ($token->text === 'import') {
                if (($this->tokens[$index + 1] ?? null)?->text === '(') {
                    foreach ($this->parseDynamicImports($index, $deadControlFlowRanges) as $dynamicImport) {
                        $imports[] = $dynamicImport;
                    }
                } else {
                    if ($depth !== 0) {
                        $depth = $this->adjustDepth($depth, $token);
                        continue;
                    }

                    $import = $this->parseImport($index);
                    if ($import !== null) {
                        $imports[] = $import;
                    }
                }
                $depth = $this->adjustDepth($depth, $token);
                continue;
            }

            if ($token->text === 'export' && $depth === 0) {
                $exports[] = $this->parseExport($index);
            }

            $depth = $this->adjustDepth($depth, $token);
        }

        [$importMetaOffsets, $importMetaProperties] = $this->collectImportMetaReferences();

        return new ModuleAnalysis(
            $imports,
            $exports,
            $importMetaOffsets,
            $importMetaProperties,
            $this->collectAssetReferences(),
            $this->collectTypeScriptNamespaces(),
            $this->pruneTypeScriptRuntimeImports($imports),
        );
    }

    private function parseImport(int $index): ?ModuleImport
    {
        $next = $this->tokens[$index + 1] ?? null;
        if ($next === null) {
            return null;
        }

        if ($next->text === '.') {
            return null;
        }

        if ($next->text === '(') {
            $imports = $this->parseDynamicImports($index, []);

            return $imports[0] ?? null;
        }

        if ($next->kind === 'identifier' && ($this->tokens[$index + 2] ?? null)?->text === '=') {
            return $this->parseImportEquals($index, false, $index + 1);
        }

        if ($next->text === 'type'
            && ($this->tokens[$index + 2] ?? null)?->kind === 'identifier'
            && ($this->tokens[$index + 3] ?? null)?->text === '='
        ) {
            return $this->parseImportEquals($index, true, $index + 2);
        }

        if ($next->text === 'type' && !$this->isRuntimeDefaultImportNamedType($index)) {
            return $this->parseTypeOnlyImport($index);
        }

        $end = $this->findStatementEnd($index);
        if ($next->kind === 'string') {
            [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($index + 2, $end);

            return new ModuleImport('side-effect', $this->stringValue($next), [], $this->tokens[$index]->offset, $attributesKeyword, $attributes);
        }

        $from = $this->findSourceStringAfterFrom($index + 1, $end);
        if ($from === null || !isset($this->tokens[$from + 1]) || $this->tokens[$from + 1]->kind !== 'string') {
            throw new \InvalidArgumentException('Static import must include a string source');
        }
        [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);

        $specifiers = [];
        $typeSpecifiers = [];
        $kind = 'named';
        $cursor = $index + 1;

        if (($this->tokens[$cursor] ?? null)?->kind === 'identifier' && !in_array($this->tokens[$cursor]->text, ['from', 'as'], true)) {
            $specifiers[] = ['imported' => 'default', 'local' => $this->tokens[$cursor]->text];
            $kind = 'default';
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === ',') {
                $cursor++;
            }
        }

        if (($this->tokens[$cursor] ?? null)?->text === '*') {
            if (($this->tokens[$cursor + 1] ?? null)?->text !== 'as' || ($this->tokens[$cursor + 2] ?? null)?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected "as" in namespace import');
            }
            $specifiers[] = ['imported' => '*', 'local' => $this->tokens[$cursor + 2]->text];
            $kind = $kind === 'default' ? 'default-namespace' : 'namespace';
        } elseif (($this->tokens[$cursor] ?? null)?->text === '{') {
            $braceEnd = $this->findMatchingPunctuator($cursor, '{', '}');
            [$namedSpecifiers, $typeSpecifiers] = $this->parseImportSpecifiers($cursor + 1, $braceEnd);
            foreach ($namedSpecifiers as $specifier) {
                $specifiers[] = $specifier;
            }
            $kind = $kind === 'default' ? 'default-named' : 'named';
        }

        return new ModuleImport($kind, $this->stringValue($this->tokens[$from + 1]), $specifiers, $this->tokens[$index]->offset, $attributesKeyword, $attributes, false, $typeSpecifiers);
    }

    private function parseExport(int $index): ModuleExport
    {
        $next = $this->tokens[$index + 1] ?? null;
        if ($next === null) {
            throw new \InvalidArgumentException('Unexpected end after export');
        }

        if ($next->text === 'default') {
            return new ModuleExport('default', null, [], $this->tokens[$index]->offset);
        }

        if ($next->text === '=') {
            return new ModuleExport('ts-export-equals', null, [], $this->tokens[$index]->offset);
        }

        if ($next->text === 'as' && ($this->tokens[$index + 2] ?? null)?->text === 'namespace') {
            return $this->parseExportAsNamespace($index);
        }

        if ($next->text === 'import') {
            return $this->parseExportImportEquals($index);
        }

        if ($next->text === 'type') {
            return $this->parseTypeOnlyExport($index);
        }

        $end = $this->findStatementEnd($index);
        if ($next->text === '*') {
            $from = $this->findSourceStringAfterFrom($index + 1, $end);
            if ($from === null || ($this->tokens[$from + 1] ?? null)?->kind !== 'string') {
                throw new \InvalidArgumentException('Export star must include a string source');
            }
            [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);

            $specifiers = [];
            $kind = 'star';
            if (($this->tokens[$index + 2] ?? null)?->text === 'as') {
                $alias = $this->tokens[$index + 3] ?? null;
                if ($alias === null || ($alias->kind !== 'identifier' && $alias->kind !== 'string')) {
                    throw new \InvalidArgumentException('Expected namespace alias after export star as');
                }
                $specifiers[] = ['exported' => $this->tokenName($alias), 'local' => '*'];
                $kind = 'star-as';
            }

            return new ModuleExport($kind, $this->stringValue($this->tokens[$from + 1]), $specifiers, $this->tokens[$index]->offset, $attributesKeyword, $attributes);
        }

        if ($next->text === '{') {
            $braceEnd = $this->findMatchingPunctuator($index + 1, '{', '}');
            $from = $this->findSourceStringAfterFrom($braceEnd + 1, $end);
            $source = null;
            $attributesKeyword = null;
            $attributes = [];
            if ($from !== null) {
                if (($this->tokens[$from + 1] ?? null)?->kind !== 'string') {
                    throw new \InvalidArgumentException('Re-export must include a string source');
                }
                $source = $this->stringValue($this->tokens[$from + 1]);
                [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);
            }

            [$specifiers, $typeSpecifiers] = $this->parseExportSpecifiers($index + 2, $braceEnd);

            return new ModuleExport($source === null ? 'named' : 're-export-named', $source, $specifiers, $this->tokens[$index]->offset, $attributesKeyword, $attributes, false, $typeSpecifiers);
        }

        return new ModuleExport('declaration', null, [], $this->tokens[$index]->offset);
    }

    private function parseExportAsNamespace(int $index): ModuleExport
    {
        $name = $this->tokens[$index + 3] ?? null;
        if ($name?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after TypeScript export as namespace');
        }

        $afterName = $this->tokens[$index + 4] ?? null;
        if ($afterName?->text === '.') {
            throw new \InvalidArgumentException('Expected ";" after TypeScript export as namespace');
        }
        if ($afterName !== null && $afterName->text !== ';' && !$this->hasLineBreakBetween($index + 3, $index + 4)) {
            throw new \InvalidArgumentException('Expected ";" after TypeScript export as namespace');
        }

        return new ModuleExport(
            'type-only-export-as-namespace',
            null,
            [],
            $this->tokens[$index]->offset,
            null,
            [],
            true,
            [['exported' => $name->text, 'local' => $name->text]],
        );
    }

    /**
     * @param list<array{0:int, 1:int}> $deadControlFlowRanges
     * @return list<ModuleImport>
     */
    private function parseDynamicImports(int $index, array $deadControlFlowRanges): array
    {
        if ($this->isIndexInRanges($index, $deadControlFlowRanges)
            || $this->isIndexInProvablyDeadExpressionBranch($index)
        ) {
            return [];
        }

        $end = $this->findMatchingPunctuator($index + 1, '(', ')');
        $source = $this->tokens[$index + 2] ?? null;
        if ($source === null || $source->text === ')') {
            throw new \InvalidArgumentException('Dynamic import must include an argument');
        }
        if ($source->text === '.'
            && ($this->tokens[$index + 3] ?? null)?->text === '.'
            && ($this->tokens[$index + 4] ?? null)?->text === '.'
        ) {
            throw new \InvalidArgumentException('Dynamic import cannot use a spread argument');
        }

        $attributesKeyword = null;
        $attributes = [];
        $comma = $this->findTopLevelPunctuator(',', $index + 3, $end);
        $argumentEnd = $comma ?? $end;
        if ($comma !== null) {
            [$attributesKeyword, $attributes] = $this->parseDynamicImportOptions($comma + 1, $end);
        }

        $imports = [];
        foreach ($this->conditionalStringArgumentSources($index + 2, $argumentEnd) as $source) {
            $imports[] = new ModuleImport('dynamic', $source, [], $this->tokens[$index]->offset, $attributesKeyword, $attributes);
        }
        foreach ($this->conditionalGlobArgumentSources($index + 2, $argumentEnd) as $source) {
            $imports[] = new ModuleImport('dynamic-glob', $source, [], $this->tokens[$index]->offset, $attributesKeyword, $attributes);
        }

        return $imports;
    }

    /**
     * @param list<array{0:int, 1:int}> $deadControlFlowRanges
     * @return list<ModuleImport>
     */
    private function parseCommonJsRequire(int $index, array $deadControlFlowRanges): array
    {
        if ($this->isTypeScriptImportEqualsRequireTargetAt($index)
            || ($this->tokens[$index - 1] ?? null)?->text === '.'
            || $this->isIndexInRanges($index, $deadControlFlowRanges)
            || $this->isIndexInProvablyDeadExpressionBranch($index)
        ) {
            return [];
        }

        $kind = null;
        $open = null;
        $sourceIndex = null;
        if (($this->tokens[$index + 1] ?? null)?->text === '(') {
            $kind = 'commonjs-require';
            $open = $index + 1;
            $sourceIndex = $index + 2;
        } elseif (($this->tokens[$index + 1] ?? null)?->text === '.'
            && ($this->tokens[$index + 2] ?? null)?->kind === 'identifier'
            && $this->tokens[$index + 2]->text === 'resolve'
            && ($this->tokens[$index + 3] ?? null)?->text === '('
        ) {
            $kind = 'commonjs-require-resolve';
            $open = $index + 3;
            $sourceIndex = $index + 4;
        }

        if ($kind === null || $open === null || $sourceIndex === null) {
            return [];
        }

        $end = $this->findMatchingPunctuator($open, '(', ')');
        $comma = $this->findTopLevelPunctuator(',', $sourceIndex + 1, $end);
        $argumentEnd = $end;
        if ($comma !== null) {
            if ($comma + 1 !== $end) {
                return [];
            }
            $argumentEnd = $comma;
        }

        $sources = $kind === 'commonjs-require-resolve'
            ? $this->commonJsDirectStringArgumentSource($sourceIndex, $argumentEnd)
            : $this->conditionalStringArgumentSources($sourceIndex, $argumentEnd);
        $globSources = [];
        if ($sources === [] && $kind === 'commonjs-require') {
            $globSources = $this->conditionalGlobArgumentSources($sourceIndex, $argumentEnd);
        }
        if ($sources === [] && $globSources === []) {
            return [];
        }

        $imports = [];
        foreach ($sources as $source) {
            $imports[] = new ModuleImport($kind, $source, [], $this->tokens[$index]->offset);
        }
        foreach ($globSources as $source) {
            $imports[] = new ModuleImport('commonjs-require-glob', $source, [], $this->tokens[$index]->offset);
        }

        return $imports;
    }

    /**
     * @return list<string>
     */
    private function commonJsDirectStringArgumentSource(int $start, int $end): array
    {
        [$start, $end] = $this->trimOuterParentheses($start, $end);
        $source = $this->tokens[$start] ?? null;
        if ($source?->kind !== 'string' || $start + 1 !== $end) {
            return [];
        }

        return [$this->stringValue($source)];
    }

    /**
     * @return list<string>
     */
    private function conditionalStringArgumentSources(int $start, int $end): array
    {
        [$start, $end] = $this->trimOuterParentheses($start, $end);
        if ($start >= $end) {
            return [];
        }

        $source = $this->tokens[$start] ?? null;
        if ($source?->kind === 'string' && $start + 1 === $end) {
            return [$this->stringValue($source)];
        }

        $question = $this->findTopLevelConditionalQuestion($start, $end);
        if ($question === null) {
            return [];
        }

        $colon = $this->findMatchingConditionalColon($question + 1, $end);
        if ($colon === null) {
            return [];
        }

        return [
            ...$this->conditionalStringArgumentSources($question + 1, $colon),
            ...$this->conditionalStringArgumentSources($colon + 1, $end),
        ];
    }

    /**
     * @return list<string>
     */
    private function conditionalGlobArgumentSources(int $start, int $end): array
    {
        [$start, $end] = $this->trimOuterParentheses($start, $end);
        if ($start >= $end) {
            return [];
        }

        $source = $this->globPatternArgumentSource($start, $end);
        if ($source !== null) {
            return [$source];
        }

        $question = $this->findTopLevelConditionalQuestion($start, $end);
        if ($question === null) {
            return [];
        }

        $colon = $this->findMatchingConditionalColon($question + 1, $end);
        if ($colon === null) {
            return [];
        }

        return [
            ...$this->conditionalGlobArgumentSources($question + 1, $colon),
            ...$this->conditionalGlobArgumentSources($colon + 1, $end),
        ];
    }

    private function globPatternArgumentSource(int $start, int $end): ?string
    {
        [$start, $end] = $this->trimOuterParentheses($start, $end);
        $rawParts = $this->globRawPartsFromExpression($start, $end);
        if ($rawParts === null) {
            return null;
        }

        $pattern = $this->globPatternStringFromRawParts($rawParts);
        if ($pattern === null) {
            return null;
        }

        return str_starts_with($pattern, './') || str_starts_with($pattern, '../') ? $pattern : null;
    }

    /**
     * @return list<array{text?:string, wildcard?:true}>|null
     */
    private function globRawPartsFromExpression(int $start, int $end): ?array
    {
        [$start, $end] = $this->trimOuterParentheses($start, $end);
        if ($start >= $end) {
            return null;
        }

        $plus = $this->findLastTopLevelPunctuator('+', $start, $end);
        if ($plus !== null) {
            $left = $this->globRawPartsFromExpression($start, $plus);
            if ($left === null) {
                return null;
            }

            $right = $this->globRawPartsFromExpression($plus + 1, $end);

            return [
                ...$left,
                ...($right ?? [['wildcard' => true]]),
            ];
        }

        $token = $this->tokens[$start] ?? null;
        if ($token?->kind === 'string' && $start + 1 === $end) {
            return [['text' => $this->stringValue($token)]];
        }

        if ($token?->kind === 'identifier'
            && $start + 1 === $end
            && ($this->source[$token->offset] ?? null) === '`'
        ) {
            return $this->templateGlobRawPartsAt($token->offset);
        }

        return null;
    }

    /**
     * @param list<array{text?:string, wildcard?:true}> $rawParts
     */
    private function globPatternStringFromRawParts(array $rawParts): ?string
    {
        $parts = [];
        $last = ['prefix' => '', 'wildcard' => 'none'];

        foreach ($rawParts as $part) {
            if (($part['wildcard'] ?? false) === true) {
                if ($last['wildcard'] === 'none') {
                    if (!str_ends_with($last['prefix'], '/')) {
                        $last['wildcard'] = 'except-slash';
                    } else {
                        $last['wildcard'] = 'including-slash';
                        $parts[] = $last;
                        $last = ['prefix' => '/', 'wildcard' => 'except-slash'];
                    }
                }
                continue;
            }

            $text = $part['text'] ?? '';
            if ($text === '') {
                continue;
            }
            if ($last['wildcard'] !== 'none') {
                $parts[] = $last;
                $last = ['prefix' => '', 'wildcard' => 'none'];
            }
            $last['prefix'] .= $text;
        }

        $parts[] = $last;

        if (count($parts) === 1 && $parts[0]['wildcard'] === 'none') {
            return null;
        }

        $pattern = '';
        foreach ($parts as $part) {
            $pattern .= $part['prefix'];
            if ($part['wildcard'] === 'except-slash') {
                $pattern .= '*';
            } elseif ($part['wildcard'] === 'including-slash') {
                $pattern .= '**';
            }
        }

        return $pattern;
    }

    /**
     * @return list<array{text?:string, wildcard?:true}>|null
     */
    private function templateGlobRawPartsAt(int $offset): ?array
    {
        if (($this->source[$offset] ?? null) !== '`') {
            return null;
        }

        $parts = [];
        $length = strlen($this->source);
        $chunkStart = $offset + 1;
        $hasWildcard = false;

        for ($cursor = $chunkStart; $cursor < $length; $cursor++) {
            $char = $this->source[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === '$' && ($this->source[$cursor + 1] ?? null) === '{') {
                $text = substr($this->source, $chunkStart, $cursor - $chunkStart);
                if ($text !== '') {
                    $parts[] = ['text' => stripcslashes($text)];
                }
                $parts[] = ['wildcard' => true];
                $hasWildcard = true;

                $end = $this->templateExpressionEnd($cursor + 1);
                if ($end === null) {
                    return null;
                }
                $cursor = $end;
                $chunkStart = $cursor + 1;
                continue;
            }
            if ($char === '`') {
                $text = substr($this->source, $chunkStart, $cursor - $chunkStart);
                if ($text !== '') {
                    $parts[] = ['text' => stripcslashes($text)];
                }

                return $hasWildcard ? $parts : null;
            }
        }

        return null;
    }

    private function templateExpressionEnd(int $openBraceOffset): ?int
    {
        $length = strlen($this->source);
        $depth = 1;

        for ($cursor = $openBraceOffset + 1; $cursor < $length; $cursor++) {
            $char = $this->source[$cursor];
            if ($char === '"' || $char === "'") {
                $cursor = $this->quotedLiteralEnd($this->source, $cursor, $char) - 1;
                continue;
            }
            if ($char === '`') {
                $end = $this->templateLiteralEnd($this->source, $cursor);
                if ($end === null) {
                    return null;
                }
                $cursor = $end - 1;
                continue;
            }
            if ($char === '/' && ($this->source[$cursor + 1] ?? null) === '/') {
                $cursor += strcspn($this->source, "\r\n", $cursor) - 1;
                continue;
            }
            if ($char === '/' && ($this->source[$cursor + 1] ?? null) === '*') {
                $close = strpos($this->source, '*/', $cursor + 2);
                if ($close === false) {
                    return null;
                }
                $cursor = $close + 1;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $cursor;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function trimOuterParentheses(int $start, int $end): array
    {
        while (($this->tokens[$start] ?? null)?->text === '('
            && $this->findMatchingPunctuator($start, '(', ')') === $end - 1
        ) {
            $start++;
            $end--;
        }

        return [$start, $end];
    }

    private function findTopLevelConditionalQuestion(int $start, int $end): ?int
    {
        $depth = 0;
        for ($i = $start; $i < $end; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                continue;
            }
            if ($depth === 0 && $text === '?') {
                return $i;
            }
        }

        return null;
    }

    private function findMatchingConditionalColon(int $start, int $end): ?int
    {
        $depth = 0;
        $conditionalDepth = 0;
        for ($i = $start; $i < $end; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if ($text === '?') {
                $conditionalDepth++;
                continue;
            }
            if ($text === ':') {
                if ($conditionalDepth === 0) {
                    return $i;
                }
                $conditionalDepth--;
            }
        }

        return null;
    }

    private function parseImportEquals(int $index, bool $typeOnly, int $localIndex): ModuleImport
    {
        $local = $this->tokens[$localIndex] ?? null;
        if ($local === null || $local->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier in TypeScript import equals');
        }
        if (($this->tokens[$localIndex + 1] ?? null)?->text !== '=') {
            throw new \InvalidArgumentException('Expected "=" in TypeScript import equals');
        }

        [$kind, $source, $cursor] = $this->parseImportEqualsTarget($localIndex + 2);
        $end = $this->findStatementEnd($index);
        if ($cursor < $end && !$this->hasLineBreakBetween($cursor - 1, $cursor)) {
            throw new \InvalidArgumentException('Expected TypeScript import equals to end after its target');
        }

        $specifier = ['imported' => $source, 'local' => $local->text];

        return new ModuleImport(
            $kind,
            $source,
            $typeOnly ? [] : [$specifier],
            $this->tokens[$index]->offset,
            null,
            [],
            $typeOnly,
            $typeOnly ? [$specifier] : [],
        );
    }

    private function parseTypeOnlyImport(int $index): ModuleImport
    {
        $end = $this->findStatementEnd($index);
        $from = $this->findSourceStringAfterFrom($index + 2, $end);
        if ($from === null || ($this->tokens[$from + 1] ?? null)?->kind !== 'string') {
            throw new \InvalidArgumentException('Type-only import must include a string source');
        }

        [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);
        $cursor = $index + 2;
        $typeSpecifiers = [];
        $kind = 'type-only-named';

        if (($this->tokens[$cursor] ?? null)?->kind === 'identifier' && $cursor < $from) {
            $typeSpecifiers[] = ['imported' => 'default', 'local' => $this->tokens[$cursor]->text];
            $kind = 'type-only-default';
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === ',') {
                throw new \InvalidArgumentException('Type-only default imports cannot be combined with named or namespace imports');
            }
        }

        if (($this->tokens[$cursor] ?? null)?->text === '*') {
            if (($this->tokens[$cursor + 1] ?? null)?->text !== 'as' || ($this->tokens[$cursor + 2] ?? null)?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected "as" in type-only namespace import');
            }
            $typeSpecifiers[] = ['imported' => '*', 'local' => $this->tokens[$cursor + 2]->text];
            $kind = 'type-only-namespace';
        } elseif (($this->tokens[$cursor] ?? null)?->text === '{') {
            $braceEnd = $this->findMatchingPunctuator($cursor, '{', '}');
            if ($braceEnd > $from) {
                throw new \InvalidArgumentException('Type-only import specifier list must end before "from"');
            }
            [, $typeSpecifiers] = $this->parseImportSpecifiers($cursor + 1, $braceEnd, true);
            $kind = 'type-only-named';
        }

        return new ModuleImport($kind, $this->stringValue($this->tokens[$from + 1]), [], $this->tokens[$index]->offset, $attributesKeyword, $attributes, true, $typeSpecifiers);
    }

    private function parseExportImportEquals(int $index): ModuleExport
    {
        $local = $this->tokens[$index + 2] ?? null;
        if ($local === null || $local->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after export import');
        }
        if (($this->tokens[$index + 3] ?? null)?->text !== '=') {
            throw new \InvalidArgumentException('Expected "=" after export import name');
        }

        [, $source] = $this->parseImportEqualsTarget($index + 4);

        return new ModuleExport(
            'ts-export-import-equals',
            $source,
            [['exported' => $local->text, 'local' => $local->text]],
            $this->tokens[$index]->offset,
        );
    }

    private function parseTypeOnlyExport(int $index): ModuleExport
    {
        $end = $this->findStatementEnd($index);
        $next = $this->tokens[$index + 2] ?? null;
        if ($next === null) {
            throw new \InvalidArgumentException('Unexpected end after export type');
        }

        if ($next->text === '*') {
            $from = $this->findSourceStringAfterFrom($index + 2, $end);
            if ($from === null || ($this->tokens[$from + 1] ?? null)?->kind !== 'string') {
                throw new \InvalidArgumentException('Type-only export star must include a string source');
            }
            [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);

            $typeSpecifiers = [];
            $kind = 'type-only-star';
            if (($this->tokens[$index + 3] ?? null)?->text === 'as') {
                $alias = $this->tokens[$index + 4] ?? null;
                if ($alias === null || ($alias->kind !== 'identifier' && $alias->kind !== 'string')) {
                    throw new \InvalidArgumentException('Expected namespace alias after export type star as');
                }
                $typeSpecifiers[] = ['exported' => $this->tokenName($alias), 'local' => '*'];
                $kind = 'type-only-star-as';
            }

            return new ModuleExport($kind, $this->stringValue($this->tokens[$from + 1]), [], $this->tokens[$index]->offset, $attributesKeyword, $attributes, true, $typeSpecifiers);
        }

        if ($next->text === '{') {
            $braceEnd = $this->findMatchingPunctuator($index + 2, '{', '}');
            $from = $this->findSourceStringAfterFrom($braceEnd + 1, $end);
            $source = null;
            $attributesKeyword = null;
            $attributes = [];
            if ($from !== null) {
                if (($this->tokens[$from + 1] ?? null)?->kind !== 'string') {
                    throw new \InvalidArgumentException('Type-only re-export must include a string source');
                }
                $source = $this->stringValue($this->tokens[$from + 1]);
                [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);
            }

            [, $typeSpecifiers] = $this->parseExportSpecifiers($index + 3, $braceEnd, true);
            if ($source === null) {
                foreach ($typeSpecifiers as $specifier) {
                    if ($specifier['local'] === 'default') {
                        throw new \InvalidArgumentException('Type-only local export cannot reference default');
                    }
                }
            }

            return new ModuleExport($source === null ? 'type-only-named' : 'type-only-re-export-named', $source, [], $this->tokens[$index]->offset, $attributesKeyword, $attributes, true, $typeSpecifiers);
        }

        return new ModuleExport('type-declaration', null, [], $this->tokens[$index]->offset, null, [], true);
    }

    /**
     * @return array{0:list<int>, 1:list<array{property:string, offset:int}>}
     */
    private function collectImportMetaReferences(): array
    {
        $offsets = [];
        $properties = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!$this->isImportMetaAt($i)) {
                continue;
            }

            $offsets[] = $this->tokens[$i]->offset;
            $property = $this->tokens[$i + 4] ?? null;
            if (($this->tokens[$i + 3] ?? null)?->text === '.' && $property?->kind === 'identifier') {
                $properties[] = ['property' => $property->text, 'offset' => $property->offset];
            }
        }

        return [$offsets, $properties];
    }

    /**
     * @return list<AssetReference>
     */
    private function collectAssetReferences(): array
    {
        $references = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== 'new'
                || ($this->tokens[$i + 1] ?? null)?->kind !== 'identifier'
                || $this->tokens[$i + 1]->text !== 'URL'
                || ($this->tokens[$i + 2] ?? null)?->text !== '('
            ) {
                continue;
            }

            $end = $this->findMatchingPunctuator($i + 2, '(', ')');
            $source = $this->tokens[$i + 3] ?? null;
            if ($source === null || $source->kind !== 'string') {
                continue;
            }

            $comma = $this->findTopLevelPunctuator(',', $i + 4, $end);
            if ($comma === null || !$this->isImportMetaUrlAt($comma + 1)) {
                continue;
            }

            $references[] = new AssetReference(
                $this->stringValue($source),
                $this->tokens[$i]->offset,
                'import.meta.url',
                $this->assetReferenceContext($i),
            );
        }

        return $references;
    }

    private function isImportMetaAt(int $index): bool
    {
        return ($this->tokens[$index] ?? null)?->kind === 'identifier'
            && $this->tokens[$index]->text === 'import'
            && ($this->tokens[$index + 1] ?? null)?->text === '.'
            && ($this->tokens[$index + 2] ?? null)?->kind === 'identifier'
            && $this->tokens[$index + 2]->text === 'meta';
    }

    private function isImportMetaUrlAt(int $index): bool
    {
        return $this->isImportMetaAt($index)
            && ($this->tokens[$index + 3] ?? null)?->text === '.'
            && ($this->tokens[$index + 4] ?? null)?->kind === 'identifier'
            && $this->tokens[$index + 4]->text === 'url';
    }

    private function assetReferenceContext(int $index): string
    {
        if (($this->tokens[$index - 1] ?? null)?->text !== '(') {
            return 'new-url';
        }

        $callee = $this->tokens[$index - 2] ?? null;
        if ($callee?->kind === 'identifier' && $callee->text === 'import') {
            return 'dynamic-import';
        }

        if ($callee?->kind === 'identifier'
            && ($this->tokens[$index - 3] ?? null)?->kind === 'identifier'
            && $this->tokens[$index - 3]->text === 'new'
        ) {
            return $callee->text === 'Worker' ? 'worker-constructor' : 'constructor-argument';
        }

        return 'call-argument';
    }

    /**
     * @return list<TypeScriptNamespace>
     */
    private function collectTypeScriptNamespaces(): array
    {
        return $this->collectTypeScriptNamespacesInRange(0, count($this->tokens), null);
    }

    /**
     * @return list<TypeScriptNamespace>
     */
    private function collectTypeScriptNamespacesInRange(int $start, int $end, ?string $parent): array
    {
        $namespaces = [];
        for ($i = $start; $i < $end; $i++) {
            $parsed = $this->parseTypeScriptNamespaceAt($i, $parent);
            if ($parsed === null) {
                continue;
            }

            [$namespaceChain, $blockStart, $blockEnd, $childParent] = $parsed;
            foreach ($namespaceChain as $namespace) {
                $namespaces[] = $namespace;
            }
            foreach ($this->collectTypeScriptNamespacesInRange($blockStart + 1, $blockEnd, $childParent) as $child) {
                $namespaces[] = $child;
            }
            $i = $blockEnd;
        }

        return $namespaces;
    }

    /**
     * @return array{0:list<TypeScriptNamespace>, 1:int, 2:int, 3:string}|null
     */
    private function parseTypeScriptNamespaceAt(int $index, ?string $parent): ?array
    {
        if (!$this->isTypeScriptNamespaceKeywordAt($index)
            || ($this->tokens[$index - 1] ?? null)?->text === '.'
            || ($this->tokens[$index + 1] ?? null)?->kind !== 'identifier'
            || $this->hasLineBreakBetween($index, $index + 1)
        ) {
            return null;
        }

        [$nameParts, $cursor] = $this->readNamespaceNameParts($index + 1);
        if (($this->tokens[$cursor] ?? null)?->text !== '{') {
            return null;
        }

        [$exported, $declared] = $this->namespaceModifiers($index);
        $blockEnd = $this->findMatchingPunctuator($cursor, '{', '}');
        $namespaces = [];
        $currentParent = $parent;
        $lastQualifiedName = null;
        $partCount = count($nameParts);

        foreach ($nameParts as $partIndex => $part) {
            $isLast = $partIndex === $partCount - 1;
            $qualifiedName = $currentParent === null ? $part['name'] : $currentParent . '.' . $part['name'];
            $members = $isLast
                ? $this->collectTypeScriptNamespaceMembers($cursor + 1, $blockEnd)
                : [new TypeScriptNamespaceMember($nameParts[$partIndex + 1]['name'], 'namespace', true, $declared, false, $nameParts[$partIndex + 1]['offset'])];

            $namespaces[] = new TypeScriptNamespace(
                $part['name'],
                $qualifiedName,
                $currentParent,
                $partIndex === 0 ? $exported : true,
                $declared,
                $part['offset'],
                $members,
            );

            $currentParent = $qualifiedName;
            $lastQualifiedName = $qualifiedName;
        }

        return [$namespaces, $cursor, $blockEnd, $lastQualifiedName ?? ''];
    }

    /**
     * @return list<TypeScriptNamespaceMember>
     */
    private function collectTypeScriptNamespaceMembers(int $start, int $end): array
    {
        $members = [];
        $depth = 0;
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i];
            if ($depth !== 0 || $token->kind !== 'identifier') {
                $depth = $this->adjustDepth($depth, $token);
                continue;
            }

            $member = null;
            $skipTo = null;
            if ($token->text === 'export') {
                $cursor = $i + 1;
                $declared = false;
                if (($this->tokens[$cursor] ?? null)?->kind === 'identifier'
                    && $this->tokens[$cursor]->text === 'declare'
                ) {
                    $declared = true;
                    $cursor++;
                }

                $variableMembers = $this->typeScriptNamespaceVariableMembersFromDeclaration($cursor, true, $declared, $i);
                if ($variableMembers !== null) {
                    foreach ($variableMembers as $variableMember) {
                        $members[] = $variableMember;
                    }
                    $skipTo = $this->findStatementEndOrLineBreak($i);
                } else {
                    $member = $this->typeScriptNamespaceMemberFromDeclaration($cursor, true, $declared);
                    if ($member !== null && $member->kind === 'namespace') {
                        $skipTo = $this->namespaceBlockEnd($cursor);
                    } elseif ($member !== null && $member->kind === 'import-equals') {
                        $skipTo = $this->findStatementEnd($i);
                    }
                }
            } elseif ($token->text === 'declare'
                && $this->isTypeScriptNamespaceKeywordAt($i + 1)
            ) {
                $member = $this->typeScriptNamespaceMemberFromDeclaration($i + 1, false, true);
                if ($member !== null) {
                    $skipTo = $this->namespaceBlockEnd($i + 1);
                }
            } elseif ($this->isTypeScriptNamespaceKeywordAt($i)) {
                $member = $this->typeScriptNamespaceMemberFromDeclaration($i, false, false);
                if ($member !== null) {
                    $skipTo = $this->namespaceBlockEnd($i);
                }
            } elseif ($token->text === 'import') {
                $member = $this->typeScriptNamespaceMemberFromDeclaration($i, false, false);
                if ($member !== null) {
                    $skipTo = $this->findStatementEnd($i);
                }
            }

            if ($member !== null) {
                $members[] = $member;
            }
            if ($skipTo !== null) {
                $i = $skipTo;
                $depth = 0;
                continue;
            }

            $depth = $this->adjustDepth($depth, $token);
        }

        return $members;
    }

    private function typeScriptNamespaceMemberFromDeclaration(int $start, bool $exported, bool $declared): ?TypeScriptNamespaceMember
    {
        $token = $this->tokens[$start] ?? null;
        if ($token === null || $token->kind !== 'identifier') {
            return null;
        }

        if ($this->isTypeScriptNamespaceKeywordAt($start)) {
            [$nameParts] = $this->readNamespaceNameParts($start + 1);
            $name = $nameParts[0]['name'] ?? null;
            if ($name === null) {
                return null;
            }

            return new TypeScriptNamespaceMember($name, 'namespace', $exported, $declared, false, $token->offset);
        }

        if (in_array($token->text, ['type', 'interface'], true)) {
            $name = $this->tokens[$start + 1] ?? null;
            if ($name?->kind !== 'identifier') {
                return null;
            }

            return new TypeScriptNamespaceMember($name->text, $token->text, $exported, $declared, true, $token->offset);
        }

        if (in_array($token->text, ['var', 'let', 'const', 'function', 'class', 'enum'], true)) {
            $nameIndex = $start + 1;
            if ($token->text === 'function' && ($this->tokens[$nameIndex] ?? null)?->text === '*') {
                $nameIndex++;
            }

            $name = $this->tokens[$nameIndex] ?? null;
            if ($name?->kind !== 'identifier') {
                return null;
            }

            return new TypeScriptNamespaceMember($name->text, $token->text, $exported, $declared, false, $token->offset);
        }

        if ($token->text === 'import') {
            $name = $this->tokens[$start + 1] ?? null;
            if ($name?->kind !== 'identifier' || ($this->tokens[$start + 2] ?? null)?->text !== '=') {
                return null;
            }

            [, $source] = $this->parseImportEqualsTarget($start + 3);

            return new TypeScriptNamespaceMember($name->text, 'import-equals', $exported, $declared, false, $token->offset, $source);
        }

        return null;
    }

    /**
     * @return list<TypeScriptNamespaceMember>|null
     */
    private function typeScriptNamespaceVariableMembersFromDeclaration(int $start, bool $exported, bool $declared, int $statementStart): ?array
    {
        $token = $this->tokens[$start] ?? null;
        if ($token === null || !in_array($token->text, ['var', 'let', 'const'], true)) {
            return null;
        }

        $end = $this->findStatementEndOrLineBreak($statementStart);
        if (($this->tokens[$end] ?? null)?->text === ';') {
            $end--;
        }

        $members = [];
        foreach ($this->bindingNamesInVariableDeclarations($start + 1, $end) as $name) {
            $members[] = new TypeScriptNamespaceMember($name, $token->text, $exported, $declared, false, $token->offset);
        }

        return $members;
    }

    private function namespaceBlockEnd(int $index): ?int
    {
        if (!$this->isTypeScriptNamespaceKeywordAt($index)
            || ($this->tokens[$index + 1] ?? null)?->kind !== 'identifier'
        ) {
            return null;
        }

        [, $cursor] = $this->readNamespaceName($index + 1);
        if (($this->tokens[$cursor] ?? null)?->text !== '{') {
            return null;
        }

        return $this->findMatchingPunctuator($cursor, '{', '}');
    }

    /**
     * @return list<string>
     */
    private function bindingNamesInVariableDeclarations(int $start, int $end): array
    {
        $names = [];
        $cursor = $start;

        while ($cursor <= $end) {
            [$declarationNames, $cursor] = $this->bindingNamesAt($cursor, $end);
            foreach ($declarationNames as $name) {
                $names[] = $name;
            }

            $cursor = $this->skipToNextVariableDeclaration($cursor, $end);
            if ($cursor > $end) {
                break;
            }
            $cursor++;
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array{0:list<string>, 1:int}
     */
    private function bindingNamesAt(int $start, int $end): array
    {
        $token = $this->tokens[$start] ?? null;
        if ($token === null) {
            return [[], $start + 1];
        }

        if ($token->kind === 'identifier') {
            return [[$token->text], $start + 1];
        }

        if ($token->text === '[') {
            return $this->arrayBindingNamesAt($start, $end);
        }

        if ($token->text === '{') {
            return $this->objectBindingNamesAt($start, $end);
        }

        return [[], $start + 1];
    }

    /**
     * @return array{0:list<string>, 1:int}
     */
    private function arrayBindingNamesAt(int $open, int $end): array
    {
        $close = min($this->findMatchingPunctuator($open, '[', ']'), $end);
        $names = [];
        $cursor = $open + 1;

        while ($cursor < $close) {
            if (($this->tokens[$cursor] ?? null)?->text === ',') {
                $cursor++;
                continue;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '.'
                && ($this->tokens[$cursor + 1] ?? null)?->text === '.'
                && ($this->tokens[$cursor + 2] ?? null)?->text === '.'
            ) {
                $cursor += 3;
            }

            [$elementNames, $cursor] = $this->bindingNamesAt($cursor, $close);
            foreach ($elementNames as $name) {
                $names[] = $name;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $cursor = $this->skipBindingInitializer($cursor + 1, $close);
            }
        }

        return [$names, $close + 1];
    }

    /**
     * @return array{0:list<string>, 1:int}
     */
    private function objectBindingNamesAt(int $open, int $end): array
    {
        $close = min($this->findMatchingPunctuator($open, '{', '}'), $end);
        $names = [];
        $cursor = $open + 1;

        while ($cursor < $close) {
            if (($this->tokens[$cursor] ?? null)?->text === ',') {
                $cursor++;
                continue;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '.'
                && ($this->tokens[$cursor + 1] ?? null)?->text === '.'
                && ($this->tokens[$cursor + 2] ?? null)?->text === '.'
            ) {
                [$restNames, $cursor] = $this->bindingNamesAt($cursor + 3, $close);
                foreach ($restNames as $name) {
                    $names[] = $name;
                }
                continue;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '[') {
                $cursor = $this->findMatchingPunctuator($cursor, '[', ']') + 1;
                if (($this->tokens[$cursor] ?? null)?->text === ':') {
                    [$valueNames, $cursor] = $this->bindingNamesAt($cursor + 1, $close);
                    foreach ($valueNames as $name) {
                        $names[] = $name;
                    }
                    if (($this->tokens[$cursor] ?? null)?->text === '=') {
                        $cursor = $this->skipBindingInitializer($cursor + 1, $close);
                    }
                }
                continue;
            }

            $property = $this->tokens[$cursor] ?? null;
            if ($property === null) {
                break;
            }

            if (($this->tokens[$cursor + 1] ?? null)?->text === ':') {
                [$valueNames, $cursor] = $this->bindingNamesAt($cursor + 2, $close);
                foreach ($valueNames as $name) {
                    $names[] = $name;
                }
            } else {
                if ($property->kind === 'identifier') {
                    $names[] = $property->text;
                }
                $cursor++;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $cursor = $this->skipBindingInitializer($cursor + 1, $close);
            }
        }

        return [$names, $close + 1];
    }

    private function skipBindingInitializer(int $start, int $patternClose): int
    {
        $depth = 0;
        for ($i = $start; $i < $patternClose; $i++) {
            $text = $this->tokens[$i]->text;
            if ($depth === 0 && $text === ',') {
                return $i;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            }
        }

        return $patternClose;
    }

    private function skipToNextVariableDeclaration(int $start, int $end): int
    {
        $depth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($depth === 0 && $text === ',') {
                return $i;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            }
        }

        return $end + 1;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function readNamespaceName(int $start): array
    {
        [$parts, $cursor] = $this->readNamespaceNameParts($start);

        return [implode('.', array_map(static fn (array $part): string => $part['name'], $parts)), $cursor];
    }

    /**
     * @return array{0:list<array{name:string, offset:int}>, 1:int}
     */
    private function readNamespaceNameParts(int $start): array
    {
        $parts = [];
        $cursor = $start;
        while (($this->tokens[$cursor] ?? null)?->kind === 'identifier') {
            $parts[] = [
                'name' => $this->tokens[$cursor]->text,
                'offset' => $this->tokens[$cursor]->offset,
            ];
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text !== '.') {
                break;
            }
            if (($this->tokens[$cursor + 1] ?? null)?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected identifier after "." in TypeScript namespace name');
            }
            $cursor++;
        }

        return [$parts, $cursor];
    }

    /**
     * @return array{0:bool, 1:bool}
     */
    private function namespaceModifiers(int $keywordIndex): array
    {
        $exported = false;
        $declared = false;
        $cursor = $keywordIndex - 1;

        if (($this->tokens[$cursor] ?? null)?->kind === 'identifier'
            && $this->tokens[$cursor]->text === 'declare'
            && !$this->hasLineBreakBetween($cursor, $keywordIndex)
        ) {
            $declared = true;
            $cursor--;
        }

        if (($this->tokens[$cursor] ?? null)?->kind === 'identifier'
            && $this->tokens[$cursor]->text === 'export'
            && !$this->hasLineBreakBetween($cursor, $cursor + 1)
        ) {
            $exported = true;
        }

        return [$exported, $declared];
    }

    private function isTypeScriptNamespaceKeywordAt(int $index): bool
    {
        return ($this->tokens[$index] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$index]->text, ['namespace', 'module'], true);
    }

    /**
     * @param list<ModuleImport> $imports
     * @return list<ModuleImport>
     */
    private function pruneTypeScriptRuntimeImports(array $imports): array
    {
        [$anyUses, $liveUses] = $this->collectTopLevelImportUses($imports);
        $keptImportEquals = $this->keptTypeScriptImportEqualsLocals($imports, $liveUses);
        $runtimeImports = [];

        foreach ($imports as $import) {
            if ($import->typeOnly) {
                continue;
            }

            if ($import->kind === 'dynamic'
                || $import->kind === 'dynamic-glob'
                || $import->kind === 'side-effect'
                || str_starts_with($import->kind, 'commonjs-')
            ) {
                $runtimeImports[] = $import;
                continue;
            }

            if ($import->kind === 'ts-import-equals-require') {
                $runtimeImports[] = $import;
                continue;
            }

            if ($import->kind === 'ts-import-equals-reference') {
                $local = $import->specifiers[0]['local'] ?? null;
                if ($local !== null && isset($keptImportEquals[$local])) {
                    $runtimeImports[] = $import;
                }
                continue;
            }

            $retained = [];
            $hasAnyTypeScriptUse = false;
            foreach ($import->specifiers as $specifier) {
                $local = $specifier['local'];
                if ($local === null) {
                    continue;
                }
                if (isset($anyUses[$local])) {
                    $hasAnyTypeScriptUse = true;
                }
                if (isset($liveUses[$local])) {
                    $retained[] = $specifier;
                }
            }

            if ($retained !== []) {
                $runtimeImports[] = new ModuleImport(
                    $this->kindForRetainedImportSpecifiers($retained),
                    $import->source,
                    $retained,
                    $import->offset,
                    $import->attributesKeyword,
                    $import->attributes,
                );
            } elseif ($hasAnyTypeScriptUse) {
                $runtimeImports[] = new ModuleImport(
                    'side-effect',
                    $import->source,
                    [],
                    $import->offset,
                    $import->attributesKeyword,
                    $import->attributes,
                );
            }
        }

        return $runtimeImports;
    }

    /**
     * @param list<ModuleImport> $imports
     * @param array<string, true> $liveUses
     * @return array<string, true>
     */
    private function keptTypeScriptImportEqualsLocals(array $imports, array $liveUses): array
    {
        $byLocal = [];
        foreach ($imports as $import) {
            if ($import->typeOnly || $import->kind !== 'ts-import-equals-reference') {
                continue;
            }
            $local = $import->specifiers[0]['local'] ?? null;
            if ($local !== null) {
                $byLocal[$local] = $import;
            }
        }

        $needed = $liveUses;
        $kept = [];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($byLocal as $local => $import) {
                if (!isset($needed[$local]) || isset($kept[$local])) {
                    continue;
                }

                $kept[$local] = true;
                $sourceRoot = $this->firstQualifiedNamePart($import->source);
                if (isset($byLocal[$sourceRoot]) && !isset($needed[$sourceRoot])) {
                    $needed[$sourceRoot] = true;
                    $changed = true;
                }
            }
        }

        return $kept;
    }

    /**
     * @param list<array{imported:string, local:?string}> $specifiers
     */
    private function kindForRetainedImportSpecifiers(array $specifiers): string
    {
        $hasDefault = false;
        $hasNamespace = false;
        $namedCount = 0;

        foreach ($specifiers as $specifier) {
            if ($specifier['imported'] === 'default') {
                $hasDefault = true;
            } elseif ($specifier['imported'] === '*') {
                $hasNamespace = true;
            } else {
                $namedCount++;
            }
        }

        if ($hasDefault && $hasNamespace) {
            return 'default-namespace';
        }
        if ($hasDefault && $namedCount > 0) {
            return 'default-named';
        }
        if ($hasDefault) {
            return 'default';
        }
        if ($hasNamespace) {
            return 'namespace';
        }

        return 'named';
    }

    private function firstQualifiedNamePart(string $qualifiedName): string
    {
        $dot = strpos($qualifiedName, '.');

        return $dot === false ? $qualifiedName : substr($qualifiedName, 0, $dot);
    }

    /**
     * @param list<ModuleImport> $imports
     * @return array{0:array<string, true>, 1:array<string, true>}
     */
    private function collectTopLevelImportUses(array $imports): array
    {
        $locals = [];
        foreach ($imports as $import) {
            if ($import->typeOnly) {
                continue;
            }
            foreach ($import->specifiers as $specifier) {
                $local = $specifier['local'];
                if ($local !== null) {
                    $locals[$local] = true;
                }
            }
        }

        if ($locals === []) {
            return [[], []];
        }

        $ignoredRanges = $this->importDeclarationRanges($imports);
        $ignoredRanges = array_merge($ignoredRanges, $this->typeOnlyExportRanges());
        $deadRanges = $this->deadControlFlowRanges();
        $anyUses = [];
        $liveUses = [];

        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $this->tokens[$i];
            if ($token->kind !== 'identifier' || !isset($locals[$token->text])) {
                continue;
            }
            if ($this->isIndexInRanges($i, $ignoredRanges)) {
                continue;
            }

            $anyUses[$token->text] = true;
            if (!$this->isIndexInRanges($i, $deadRanges)) {
                $liveUses[$token->text] = true;
            }
        }

        return [$anyUses, $liveUses];
    }

    /**
     * @param list<ModuleImport> $imports
     * @return list<array{0:int, 1:int}>
     */
    private function importDeclarationRanges(array $imports): array
    {
        $ranges = [];
        foreach ($imports as $import) {
            if ($import->kind === 'dynamic') {
                continue;
            }

            $index = $this->tokenIndexAtOffset($import->offset);
            if ($index === null) {
                continue;
            }

            $ranges[] = [$index, $this->findStatementEndOrLineBreak($index)];
        }

        return $ranges;
    }

    /**
     * @return list<array{0:int, 1:int}>
     */
    private function typeOnlyExportRanges(): array
    {
        $ranges = [];
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== 'export'
                || ($this->tokens[$i + 1] ?? null)?->text !== 'type'
            ) {
                continue;
            }

            $ranges[] = [$i, $this->findStatementEndOrLineBreak($i)];
        }

        return $ranges;
    }

    /**
     * @return list<array{0:int, 1:int}>
     */
    private function deadControlFlowRanges(): array
    {
        $ranges = [];
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== 'if'
                || ($this->tokens[$i + 1] ?? null)?->text !== '('
            ) {
                continue;
            }

            $close = $this->findMatchingPunctuator($i + 1, '(', ')');
            if ($close !== $i + 3
                || ($this->tokens[$i + 2] ?? null)?->kind !== 'identifier'
                || $this->tokens[$i + 2]->text !== 'false'
            ) {
                continue;
            }

            $bodyStart = $close + 1;
            if ($bodyStart >= $count) {
                continue;
            }

            if (($this->tokens[$bodyStart] ?? null)?->text === '{') {
                $ranges[] = [$bodyStart, $this->findMatchingPunctuator($bodyStart, '{', '}')];
            } else {
                $ranges[] = [$bodyStart, $this->findStatementEndOrLineBreak($bodyStart)];
            }
        }

        return $ranges;
    }

    /**
     * @param list<array{0:int, 1:int}> $ranges
     */
    private function isIndexInRanges(int $index, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($index >= $start && $index <= $end) {
                return true;
            }
        }

        return false;
    }

    private function isIndexInProvablyDeadExpressionBranch(int $index): bool
    {
        return $this->isIndexInDeadLogicalRightHandSide($index)
            || $this->isIndexInDeadConditionalBranch($index);
    }

    private function isIndexInDeadLogicalRightHandSide(int $index): bool
    {
        $depth = 0;
        for ($i = $index - 1; $i >= 0; $i--) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, [')', '}', ']'], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                if ($depth === 0) {
                    return false;
                }
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if (in_array($text, [',', ';', ':'], true)) {
                return false;
            }

            $operator = null;
            $operatorStart = $i;
            if ($text === '&&' || $text === '||') {
                $operator = $text;
            } elseif ($this->isNullishCoalescingOperatorEndingAt($i)) {
                $operator = '??';
                $operatorStart = $i - 1;
            }

            if ($operator === null) {
                continue;
            }

            $left = $this->previousTopLevelExpressionToken($operatorStart - 1);
            if ($left?->kind !== 'identifier') {
                return false;
            }

            return ($operator === '&&' && $left->text === 'false')
                || ($operator === '||' && $left->text === 'true')
                || ($operator === '??' && in_array($left->text, ['true', 'false'], true));
        }

        return false;
    }

    private function isIndexInDeadConditionalBranch(int $index): bool
    {
        $depth = 0;
        $statementEnd = $this->findStatementEndOrLineBreak($index);
        for ($i = $index - 1; $i >= 0; $i--) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, [')', '}', ']'], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                if ($depth === 0) {
                    return false;
                }
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if (in_array($text, [',', ';'], true)) {
                return false;
            }

            if ($text === ':') {
                $question = $this->findMatchingConditionalQuestionBefore($i);
                if ($question === null) {
                    return false;
                }

                $condition = $this->previousTopLevelExpressionToken($question - 1);
                return $condition?->kind === 'identifier' && $condition->text === 'true';
            }

            if ($this->isConditionalQuestionTokenAt($i)) {
                $colon = $this->findMatchingConditionalColon($i + 1, $statementEnd);
                if ($colon === null || $colon <= $index) {
                    return false;
                }

                $condition = $this->previousTopLevelExpressionToken($i - 1);
                return $condition?->kind === 'identifier' && $condition->text === 'false';
            }
        }

        return false;
    }

    private function findMatchingConditionalQuestionBefore(int $colon): ?int
    {
        $depth = 0;
        $conditionalDepth = 0;
        for ($i = $colon - 1; $i >= 0; $i--) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, [')', '}', ']'], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                if ($depth === 0) {
                    return null;
                }
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if ($text === ':') {
                $conditionalDepth++;
                continue;
            }
            if ($this->isConditionalQuestionTokenAt($i)) {
                if ($conditionalDepth === 0) {
                    return $i;
                }
                $conditionalDepth--;
            }
        }

        return null;
    }

    private function previousTopLevelExpressionToken(int $start): ?Token
    {
        $depth = 0;
        for ($i = $start; $i >= 0; $i--) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                return null;
            }

            $text = $token->text;
            if (in_array($text, [')', '}', ']'], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                if ($depth === 0) {
                    return null;
                }
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if (in_array($text, [',', ';', '?', ':', '&&', '||'], true) || $this->isNullishCoalescingOperatorEndingAt($i)) {
                return null;
            }

            return $token;
        }

        return null;
    }

    private function isConditionalQuestionTokenAt(int $index): bool
    {
        return ($this->tokens[$index] ?? null)?->text === '?'
            && !$this->isNullishCoalescingOperatorStartingAt($index)
            && !$this->isNullishCoalescingOperatorEndingAt($index)
            && ($this->tokens[$index + 1] ?? null)?->text !== '.';
    }

    private function isNullishCoalescingOperatorStartingAt(int $index): bool
    {
        return ($this->tokens[$index] ?? null)?->text === '?'
            && ($this->tokens[$index + 1] ?? null)?->text === '?'
            && $this->tokens[$index + 1]->offset === $this->tokens[$index]->offset + 1;
    }

    private function isNullishCoalescingOperatorEndingAt(int $index): bool
    {
        return ($this->tokens[$index] ?? null)?->text === '?'
            && ($this->tokens[$index - 1] ?? null)?->text === '?'
            && $this->tokens[$index]->offset === $this->tokens[$index - 1]->offset + 1;
    }

    private function tokenIndexAtOffset(int $offset): ?int
    {
        foreach ($this->tokens as $index => $token) {
            if ($token->offset === $offset) {
                return $index;
            }
        }

        return null;
    }

    private function findStatementEndOrLineBreak(int $start): int
    {
        $depth = 0;
        $count = count($this->tokens);
        for ($i = $start; $i < $count; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(' || $text === '{' || $text === '[') {
                $depth++;
            } elseif ($text === ')' || $text === '}' || $text === ']') {
                $depth--;
            } elseif ($text === ';' && $depth === 0) {
                return $i;
            }

            if ($depth === 0 && $this->hasLineBreakBetween($i, $i + 1)) {
                return $i;
            }
        }

        return max($start, $count - 1);
    }

    private function adjustDepth(int $depth, Token $token): int
    {
        if (in_array($token->text, ['(', '{', '['], true)) {
            return $depth + 1;
        }
        if (in_array($token->text, [')', '}', ']'], true)) {
            return max(0, $depth - 1);
        }

        return $depth;
    }

    private function isRuntimeDefaultImportNamedType(int $index): bool
    {
        return ($this->tokens[$index + 1] ?? null)?->text === 'type'
            && ($this->tokens[$index + 2] ?? null)?->text === 'from'
            && ($this->tokens[$index + 3] ?? null)?->kind === 'string';
    }

    /**
     * @return array{0:string, 1:string, 2:int}
     */
    private function parseImportEqualsTarget(int $start): array
    {
        if (($this->tokens[$start] ?? null)?->kind === 'identifier'
            && $this->tokens[$start]->text === 'require'
            && ($this->tokens[$start + 1] ?? null)?->text === '('
            && ($this->tokens[$start + 2] ?? null)?->kind === 'string'
        ) {
            $end = $this->findMatchingPunctuator($start + 1, '(', ')');
            if ($end !== $start + 3) {
                throw new \InvalidArgumentException('TypeScript import equals require target must contain one string argument');
            }

            return ['ts-import-equals-require', $this->stringValue($this->tokens[$start + 2]), $end + 1];
        }

        [$qualifiedName, $cursor] = $this->readQualifiedName($start);

        return ['ts-import-equals-reference', $qualifiedName, $cursor];
    }

    private function isTypeScriptImportEqualsRequireTargetAt(int $index): bool
    {
        if (($this->tokens[$index - 1] ?? null)?->text !== '='
            || ($this->tokens[$index - 2] ?? null)?->kind !== 'identifier'
        ) {
            return false;
        }

        return ($this->tokens[$index - 3] ?? null)?->text === 'import'
            || (
                ($this->tokens[$index - 3] ?? null)?->text === 'type'
                && ($this->tokens[$index - 4] ?? null)?->text === 'import'
            );
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function readQualifiedName(int $start): array
    {
        $parts = [];
        $cursor = $start;
        while (($this->tokens[$cursor] ?? null)?->kind === 'identifier') {
            $parts[] = $this->tokens[$cursor]->text;
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text !== '.') {
                break;
            }
            if (($this->tokens[$cursor + 1] ?? null)?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected identifier after "." in TypeScript import equals target');
            }
            $cursor++;
        }

        if ($parts === []) {
            throw new \InvalidArgumentException('Expected TypeScript import equals target');
        }

        return [implode('.', $parts), $cursor];
    }

    private function hasLineBreakBetween(int $leftIndex, int $rightIndex): bool
    {
        $left = $this->tokens[$leftIndex] ?? null;
        $right = $this->tokens[$rightIndex] ?? null;
        if ($left === null || $right === null) {
            return false;
        }

        $start = $left->offset + strlen($left->text);
        $gap = substr($this->source, $start, $right->offset - $start);

        return str_contains($gap, "\n") || str_contains($gap, "\r");
    }

    /**
     * @return array{0:list<array{imported:string, local:?string}>, 1:list<array{imported:string, local:?string}>}
     */
    private function parseImportSpecifiers(int $start, int $end, bool $forceTypeOnly = false): array
    {
        $specifiers = [];
        $typeSpecifiers = [];
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i];
            if ($token->text === ',') {
                continue;
            }
            if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                continue;
            }

            $isTypeSpecifier = $forceTypeOnly;
            if (!$forceTypeOnly
                && $token->kind === 'identifier'
                && $token->text === 'type'
                && ($this->tokens[$i + 1] ?? null) !== null
                && $this->tokens[$i + 1]->text !== ','
                && $this->tokens[$i + 1]->text !== '}'
            ) {
                $isTypeSpecifier = true;
                $i++;
                $token = $this->tokens[$i];
                if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                    throw new \InvalidArgumentException('Expected imported identifier after type-only import marker');
                }
            }

            $imported = $this->tokenName($token);
            $local = $imported;
            if (($this->tokens[$i + 1] ?? null)?->text === 'as') {
                $alias = $this->tokens[$i + 2] ?? null;
                if ($alias === null || $alias->kind !== 'identifier') {
                    throw new \InvalidArgumentException('Expected local identifier after import alias');
                }
                $local = $alias->text;
                $i += 2;
            }

            if ($isTypeSpecifier) {
                $typeSpecifiers[] = ['imported' => $imported, 'local' => $local];
            } else {
                $specifiers[] = ['imported' => $imported, 'local' => $local];
            }
        }

        return [$specifiers, $typeSpecifiers];
    }

    /**
     * @return array{0:list<array{exported:string, local:?string}>, 1:list<array{exported:string, local:?string}>}
     */
    private function parseExportSpecifiers(int $start, int $end, bool $forceTypeOnly = false): array
    {
        $specifiers = [];
        $typeSpecifiers = [];
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i];
            if ($token->text === ',') {
                continue;
            }
            if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                continue;
            }

            $isTypeSpecifier = $forceTypeOnly;
            if (!$forceTypeOnly
                && $token->kind === 'identifier'
                && $token->text === 'type'
                && ($this->tokens[$i + 1] ?? null) !== null
                && $this->tokens[$i + 1]->text !== ','
                && $this->tokens[$i + 1]->text !== '}'
            ) {
                $isTypeSpecifier = true;
                $i++;
                $token = $this->tokens[$i];
                if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                    throw new \InvalidArgumentException('Expected exported identifier after type-only export marker');
                }
            }

            $local = $this->tokenName($token);
            $exported = $local;
            if (($this->tokens[$i + 1] ?? null)?->text === 'as') {
                $alias = $this->tokens[$i + 2] ?? null;
                if ($alias === null || ($alias->kind !== 'identifier' && $alias->kind !== 'string')) {
                    throw new \InvalidArgumentException('Expected exported identifier after export alias');
                }
                $exported = $this->tokenName($alias);
                $i += 2;
            }

            if ($isTypeSpecifier) {
                $typeSpecifiers[] = ['exported' => $exported, 'local' => $local];
            } else {
                $specifiers[] = ['exported' => $exported, 'local' => $local];
            }
        }

        return [$specifiers, $typeSpecifiers];
    }

    /**
     * @return array{0:?string, 1:array<string, string>}
     */
    private function parseImportAttributesClause(int $start, int $end): array
    {
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null || $token->kind !== 'identifier' || !in_array($token->text, ['assert', 'with'], true)) {
                continue;
            }

            if (($this->tokens[$i + 1] ?? null)?->text !== '{') {
                throw new \InvalidArgumentException('Expected import attribute object after "' . $token->text . '"');
            }

            $braceEnd = $this->findMatchingPunctuator($i + 1, '{', '}');
            if ($braceEnd > $end) {
                throw new \InvalidArgumentException('Import attribute object crosses the current statement boundary');
            }

            return [$token->text, $this->parseImportAttributesObject($i + 2, $braceEnd, $token->text)];
        }

        return [null, []];
    }

    /**
     * @return array{0:?string, 1:array<string, string>}
     */
    private function parseDynamicImportOptions(int $start, int $end): array
    {
        for ($i = $start; $i < $end; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== '{') {
                continue;
            }

            $objectEnd = $this->findMatchingPunctuator($i, '{', '}');
            for ($property = $i + 1; $property < $objectEnd; $property++) {
                $token = $this->tokens[$property] ?? null;
                if ($token === null || ($token->kind !== 'identifier' && $token->kind !== 'string')) {
                    continue;
                }

                $name = $this->tokenName($token);
                if (!in_array($name, ['assert', 'with'], true)
                    || ($this->tokens[$property + 1] ?? null)?->text !== ':'
                    || ($this->tokens[$property + 2] ?? null)?->text !== '{'
                ) {
                    continue;
                }

                $attributesEnd = $this->findMatchingPunctuator($property + 2, '{', '}');

                return [$name, $this->parseImportAttributesObject($property + 3, $attributesEnd, $name)];
            }
        }

        return [null, []];
    }

    /**
     * @return array<string, string>
     */
    private function parseImportAttributesObject(int $start, int $end, string $keyword): array
    {
        $attributes = [];
        $expectKey = true;
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                break;
            }

            if ($token->text === ',') {
                if ($expectKey) {
                    throw new \InvalidArgumentException('Expected import ' . $this->attributeLabel($keyword) . ' key before comma');
                }
                $expectKey = true;
                continue;
            }

            if (!$expectKey) {
                throw new \InvalidArgumentException('Expected comma between import ' . $this->attributeLabel($keyword) . ' entries');
            }

            if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                throw new \InvalidArgumentException('Expected import ' . $this->attributeLabel($keyword) . ' key');
            }

            $key = $this->tokenName($token);
            if (($this->tokens[$i + 1] ?? null)?->text !== ':') {
                throw new \InvalidArgumentException('Expected ":" after import ' . $this->attributeLabel($keyword) . ' key "' . $key . '"');
            }

            $value = $this->tokens[$i + 2] ?? null;
            if ($value === null || $value->kind !== 'string') {
                throw new \InvalidArgumentException('Expected string value for import ' . $this->attributeLabel($keyword) . ' "' . $key . '"');
            }

            if (array_key_exists($key, $attributes)) {
                throw new \InvalidArgumentException('Duplicate import ' . $this->attributeLabel($keyword) . ' "' . $key . '"');
            }

            $attributes[$key] = $this->stringValue($value);
            $i += 2;
            $expectKey = false;
        }

        return $attributes;
    }

    private function attributeLabel(string $keyword): string
    {
        return $keyword === 'assert' ? 'assertion' : 'attribute';
    }

    private function tokenName(Token $token): string
    {
        return $token->kind === 'string' ? $this->stringValue($token) : $token->text;
    }

    private function findSourceStringAfterFrom(int $start, int $end): ?int
    {
        $depth = 0;
        $from = null;
        for ($i = $start; $i < $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(' || $text === '{' || $text === '[') {
                $depth++;
                continue;
            }
            if ($text === ')' || $text === '}' || $text === ']') {
                $depth--;
                continue;
            }
            if ($depth === 0
                && $this->tokens[$i]->kind === 'identifier'
                && $text === 'from'
                && ($this->tokens[$i + 1] ?? null)?->kind === 'string'
            ) {
                $from = $i;
            }
        }

        return $from;
    }

    private function findIdentifierBetween(string $text, int $start, int $end): ?int
    {
        for ($i = $start; $i < $end; $i++) {
            if (($this->tokens[$i] ?? null)?->kind === 'identifier' && $this->tokens[$i]->text === $text) {
                return $i;
            }
        }

        return null;
    }

    private function findStatementEnd(int $start): int
    {
        $depth = 0;
        $count = count($this->tokens);
        for ($i = $start; $i < $count; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(' || $text === '{' || $text === '[') {
                $depth++;
            } elseif ($text === ')' || $text === '}' || $text === ']') {
                $depth--;
            } elseif ($text === ';' && $depth === 0) {
                return $i;
            }
        }

        return $count;
    }

    private function findTopLevelPunctuator(string $punctuator, int $start, int $end): ?int
    {
        $depth = 0;
        for ($i = $start; $i < $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(' || $text === '{' || $text === '[') {
                $depth++;
            } elseif ($text === ')' || $text === '}' || $text === ']') {
                $depth--;
            } elseif ($text === $punctuator && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findLastTopLevelPunctuator(string $punctuator, int $start, int $end): ?int
    {
        $depth = 0;
        for ($i = $end - 1; $i >= $start; $i--) {
            $text = $this->tokens[$i]->text;
            if ($text === ')' || $text === '}' || $text === ']') {
                $depth++;
            } elseif ($text === '(' || $text === '{' || $text === '[') {
                $depth--;
            } elseif ($text === $punctuator && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findMatchingPunctuator(int $start, string $open, string $close): int
    {
        $depth = 0;
        $count = count($this->tokens);
        for ($i = $start; $i < $count; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === $open) {
                $depth++;
            } elseif ($text === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unterminated ' . $open . ' group');
    }

    private function stringValue(Token $token): string
    {
        return stripcslashes(substr($token->text, 1, -1));
    }

    private function sourceWithNoSubstitutionTemplateStrings(string $source): string
    {
        $result = '';
        $length = strlen($source);

        for ($offset = 0; $offset < $length;) {
            $char = $source[$offset];

            if ($char === '"' || $char === "'") {
                $end = $this->quotedLiteralEnd($source, $offset, $char);
                $result .= substr($source, $offset, $end - $offset);
                $offset = $end;
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? null) === '/') {
                $end = $offset + strcspn($source, "\r\n", $offset);
                $result .= substr($source, $offset, $end - $offset);
                $offset = $end;
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? null) === '*') {
                $close = strpos($source, '*/', $offset + 2);
                $end = $close === false ? $length : $close + 2;
                $result .= substr($source, $offset, $end - $offset);
                $offset = $end;
                continue;
            }

            if ($char === '`') {
                $template = $this->noSubstitutionTemplateAsStringLiteral($source, $offset);
                if ($template !== null) {
                    [$text, $end] = $template;
                    $result .= $text;
                    $offset = $end;
                    continue;
                }

                $end = $this->templateLiteralEnd($source, $offset);
                if ($end !== null) {
                    $result .= 'x' . str_repeat(' ', $end - $offset - 1);
                    $offset = $end;
                    continue;
                }
            }

            $result .= $char;
            $offset++;
        }

        return $result;
    }

    private function quotedLiteralEnd(string $source, int $offset, string $quote): int
    {
        $length = strlen($source);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === $quote) {
                return $cursor + 1;
            }
        }

        return $length;
    }

    private function templateLiteralEnd(string $source, int $offset): ?int
    {
        $length = strlen($source);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === '`') {
                return $cursor + 1;
            }
        }

        return null;
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function noSubstitutionTemplateAsStringLiteral(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $bodyStart = $offset + 1;

        for ($cursor = $bodyStart; $cursor < $length; $cursor++) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === '$' && ($source[$cursor + 1] ?? null) === '{') {
                return null;
            }
            if ($char !== '`') {
                continue;
            }

            $body = substr($source, $bodyStart, $cursor - $bodyStart);
            if (!str_contains($body, '"')) {
                return ['"' . $body . '"', $cursor + 1];
            }
            if (!str_contains($body, "'")) {
                return ["'" . $body . "'", $cursor + 1];
            }

            return null;
        }

        return null;
    }
}
