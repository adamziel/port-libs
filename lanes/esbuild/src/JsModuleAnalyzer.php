<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class JsModuleAnalyzer
{
    /**
     * @var list<Token>
     */
    private array $tokens = [];

    public function analyze(string $source): ModuleAnalysis
    {
        $this->tokens = (new JsLexer())->tokenize($source);
        $imports = [];
        $exports = [];

        $count = count($this->tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $this->tokens[$index];
            if ($token->kind !== 'identifier') {
                continue;
            }

            if ($token->text === 'import') {
                $import = $this->parseImport($index);
                if ($import !== null) {
                    $imports[] = $import;
                }
                continue;
            }

            if ($token->text === 'export') {
                $exports[] = $this->parseExport($index);
            }
        }

        [$importMetaOffsets, $importMetaProperties] = $this->collectImportMetaReferences();

        return new ModuleAnalysis(
            $imports,
            $exports,
            $importMetaOffsets,
            $importMetaProperties,
            $this->collectAssetReferences(),
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
            return $this->parseDynamicImport($index);
        }

        $end = $this->findStatementEnd($index);
        if ($next->kind === 'string') {
            [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($index + 2, $end);

            return new ModuleImport('side-effect', $this->stringValue($next), [], $this->tokens[$index]->offset, $attributesKeyword, $attributes);
        }

        $from = $this->findIdentifierBetween('from', $index + 1, $end);
        if ($from === null || !isset($this->tokens[$from + 1]) || $this->tokens[$from + 1]->kind !== 'string') {
            throw new \InvalidArgumentException('Static import must include a string source');
        }
        [$attributesKeyword, $attributes] = $this->parseImportAttributesClause($from + 2, $end);

        $specifiers = [];
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
            foreach ($this->parseImportSpecifiers($cursor + 1, $braceEnd) as $specifier) {
                $specifiers[] = $specifier;
            }
            $kind = $kind === 'default' ? 'default-named' : 'named';
        }

        return new ModuleImport($kind, $this->stringValue($this->tokens[$from + 1]), $specifiers, $this->tokens[$index]->offset, $attributesKeyword, $attributes);
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

        $end = $this->findStatementEnd($index);
        if ($next->text === '*') {
            $from = $this->findIdentifierBetween('from', $index + 1, $end);
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
            $from = $this->findIdentifierBetween('from', $braceEnd + 1, $end);
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

            return new ModuleExport($source === null ? 'named' : 're-export-named', $source, $this->parseExportSpecifiers($index + 2, $braceEnd), $this->tokens[$index]->offset, $attributesKeyword, $attributes);
        }

        return new ModuleExport('declaration', null, [], $this->tokens[$index]->offset);
    }

    private function parseDynamicImport(int $index): ?ModuleImport
    {
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
        if ($source->kind !== 'string') {
            return null;
        }

        $attributesKeyword = null;
        $attributes = [];
        $comma = $this->findTopLevelPunctuator(',', $index + 3, $end);
        if ($comma !== null) {
            [$attributesKeyword, $attributes] = $this->parseDynamicImportOptions($comma + 1, $end);
        }

        return new ModuleImport('dynamic', $this->stringValue($source), [], $this->tokens[$index]->offset, $attributesKeyword, $attributes);
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
     * @return list<array{imported:string, local:?string}>
     */
    private function parseImportSpecifiers(int $start, int $end): array
    {
        $specifiers = [];
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i];
            if ($token->text === ',') {
                continue;
            }
            if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                continue;
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

            $specifiers[] = ['imported' => $imported, 'local' => $local];
        }

        return $specifiers;
    }

    /**
     * @return list<array{exported:string, local:?string}>
     */
    private function parseExportSpecifiers(int $start, int $end): array
    {
        $specifiers = [];
        for ($i = $start; $i < $end; $i++) {
            $token = $this->tokens[$i];
            if ($token->text === ',') {
                continue;
            }
            if ($token->kind !== 'identifier' && $token->kind !== 'string') {
                continue;
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

            $specifiers[] = ['exported' => $exported, 'local' => $local];
        }

        return $specifiers;
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
}
