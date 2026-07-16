<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class TypeScriptNamespaceLowerer
{
    /**
     * @var list<Token>
     */
    private array $tokens = [];
    private string $source = '';
    private bool $needsUsingHelperRuntime = false;
    private string $usingHelperKnownSymbolName = '__knownSymbol';
    private string $usingHelperTypeErrorName = '__typeError';
    private string $usingHelperUsingName = '__using';
    private string $usingHelperCallDisposeName = '__callDispose';

    public function lower(string $source): string
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($source);
        $this->needsUsingHelperRuntime = false;
        $this->configureHelperNames();

        $output = '';
        $declaredValues = [];
        $statements = $this->topLevelStatements();
        $this->assertTopLevelNamespaceMergeRules($statements);

        foreach ($statements as $statementIndex => $statement) {
            $namespace = $statement['namespace'];
            if ($namespace !== null) {
                if (!$namespace['declared'] && $statement['namespaceOutput'] !== '') {
                    $name = $this->rootNamespaceName($namespace['namespaceIndex']);
                    $namespaceOutput = isset($declaredValues[$name]) || $this->hasLaterTopLevelEnumDeclaration($statements, $statementIndex, $name)
                        ? $this->stripTopLevelNamespaceDeclaration($statement['namespaceOutput'], $name)
                        : $statement['namespaceOutput'];

                    $output .= $namespaceOutput;
                    $declaredValues[$name] = true;
                }

                continue;
            }

            $topLevel = $this->printTopLevelStatement($statement['start'], $statement['end']);
            if ($topLevel !== '') {
                $output .= $topLevel . "\n";
            }

            $declaration = $this->topLevelValueDeclaration($statement['start'], $statement['end']);
            if ($declaration !== null) {
                $declaredValues[$declaration['name']] = true;
            }
        }

        return $this->needsUsingHelperRuntime ? $this->usingHelperRuntime() . $output : $output;
    }

    /**
     * @return list<array{start:int, end:int, namespace:array{namespaceIndex:int, exported:bool, declared:bool}|null, namespaceOutput:string}>
     */
    private function topLevelStatements(): array
    {
        $statements = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text === ';') {
                continue;
            }

            $namespace = $this->namespaceStatementAt($i);
            if ($namespace !== null) {
                if ($namespace['declared']) {
                    $blockEnd = $this->namespaceBlockEndFromIndex($namespace['namespaceIndex']);
                    $namespaceOutput = '';
                } else {
                    [$namespaceOutput, $blockEnd] = $this->lowerNamespaceAt($namespace['namespaceIndex'], $namespace['exported']);
                }

                $statements[] = [
                    'start' => $i,
                    'end' => $blockEnd,
                    'namespace' => $namespace,
                    'namespaceOutput' => $namespaceOutput,
                ];
                $i = $blockEnd;
                continue;
            }

            $end = $this->declarationStatementEnd($i, $count)
                ?? $this->findStatementEndOrLineBreak($i, $count);
            $statements[] = [
                'start' => $i,
                'end' => $end,
                'namespace' => null,
                'namespaceOutput' => '',
            ];
            $i = $end;
        }

        return $statements;
    }

    /**
     * @param list<array{start:int, end:int, namespace:array{namespaceIndex:int, exported:bool, declared:bool}|null, namespaceOutput:string}> $statements
     */
    private function assertTopLevelNamespaceMergeRules(array $statements): void
    {
        $symbols = [];

        foreach ($statements as $statement) {
            $namespace = $statement['namespace'];
            if ($namespace !== null) {
                if ($namespace['declared'] || $statement['namespaceOutput'] === '') {
                    continue;
                }

                $name = $this->rootNamespaceName($namespace['namespaceIndex']);
                $state = $symbols[$name] ?? [
                    'blockedValueKinds' => [],
                    'functionBeforeNamespace' => false,
                    'runtimeNamespaceSeen' => false,
                ];

                foreach ($state['blockedValueKinds'] as $kind) {
                    if (in_array($kind, ['var', 'let', 'const'], true)) {
                        throw new \InvalidArgumentException('The symbol "' . $name . '" has already been declared');
                    }
                }

                $state['runtimeNamespaceSeen'] = true;
                $symbols[$name] = $state;
                continue;
            }

            $declaration = $this->topLevelValueDeclaration($statement['start'], $statement['end']);
            if ($declaration === null) {
                continue;
            }

            $name = $declaration['name'];
            $kind = $declaration['kind'];
            $state = $symbols[$name] ?? [
                'blockedValueKinds' => [],
                'functionBeforeNamespace' => false,
                'runtimeNamespaceSeen' => false,
            ];

            if ($state['runtimeNamespaceSeen']) {
                if (in_array($kind, ['var', 'let', 'const', 'class'], true)
                    || ($kind === 'function' && !$state['functionBeforeNamespace'])
                ) {
                    throw new \InvalidArgumentException('The symbol "' . $name . '" has already been declared');
                }
            } else {
                if ($kind === 'function') {
                    $state['functionBeforeNamespace'] = true;
                }
                $state['blockedValueKinds'][] = $kind;
            }

            $symbols[$name] = $state;
        }
    }

    /**
     * @param list<array{start:int, end:int, namespace:array{namespaceIndex:int, exported:bool, declared:bool}|null, namespaceOutput:string}> $statements
     */
    private function hasLaterTopLevelEnumDeclaration(array $statements, int $statementIndex, string $name): bool
    {
        $count = count($statements);
        for ($i = $statementIndex + 1; $i < $count; $i++) {
            if ($statements[$i]['namespace'] !== null) {
                continue;
            }

            $declaration = $this->topLevelValueDeclaration($statements[$i]['start'], $statements[$i]['end']);
            if ($declaration !== null && $declaration['name'] === $name && $declaration['kind'] === 'enum') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function lowerNamespaceAt(int $index, bool $exported = false, ?string $parentNamespace = null): array
    {
        [$qualifiedName, $blockStart] = $this->readNamespaceName($index + 1);
        if ($qualifiedName === '') {
            throw new \InvalidArgumentException('Expected TypeScript namespace name');
        }
        if (($this->tokens[$blockStart] ?? null)?->text !== '{') {
            throw new \InvalidArgumentException('Expected TypeScript namespace block');
        }

        $blockEnd = $this->findMatchingPunctuator($blockStart, '{', '}');
        $parts = explode('.', $qualifiedName);

        return [
            $this->lowerNamespaceParts($parts, $blockStart, $blockEnd, $exported, $parentNamespace),
            $blockEnd,
        ];
    }

    /**
     * @param non-empty-list<string> $parts
     */
    private function lowerNamespaceParts(array $parts, int $blockStart, int $blockEnd, bool $exported, ?string $parentNamespace): string
    {
        $name = array_shift($parts);
        if ($parts === []) {
            return $this->lowerNamespaceBlock($name, $blockStart, $blockEnd, $exported, $parentNamespace);
        }

        $childName = $parts[0];
        $parameter = $this->namespaceParameterName($name, [], [], [$childName => true]);
        $childOutput = $this->lowerNamespaceParts($parts, $blockStart, $blockEnd, true, $parameter);
        if ($childOutput === '') {
            return '';
        }

        return $this->renderNamespaceIife(
            $name,
            $parameter,
            $exported,
            $parentNamespace,
            explode("\n", rtrim($childOutput, "\n")),
        );
    }

    private function lowerNamespaceBlock(string $name, int $blockStart, int $blockEnd, bool $exported, ?string $parentNamespace): string
    {
        $statements = $this->namespaceStatements($blockStart + 1, $blockEnd);
        $imports = [];
        $exportedValues = [];
        $exportedLocalBindings = [];
        $ordered = [];
        $hasRuntimeNamespaceStatement = false;
        $hasUsingHelperScope = false;

        foreach ($statements as [$start, $end]) {
            $first = $this->tokens[$start] ?? null;
            if ($first === null) {
                continue;
            }

            if ($first->text === 'export' && ($this->tokens[$start + 1] ?? null)?->text === 'using') {
                throw new \InvalidArgumentException('Unexpected "using"');
            }
            if ($first->text === 'export'
                && ($this->tokens[$start + 1] ?? null)?->text === 'await'
                && ($this->tokens[$start + 2] ?? null)?->text === 'using'
            ) {
                throw new \InvalidArgumentException('Unexpected "await"');
            }

            if ($this->isNamespaceUsingStatement($start)) {
                $ordered[] = ['kind' => 'using', 'start' => $start, 'end' => $end];
                $hasRuntimeNamespaceStatement = true;
                continue;
            }

            $nestedNamespace = $this->namespaceStatementAt($start);
            if ($nestedNamespace !== null) {
                if (!$nestedNamespace['declared']) {
                    $ordered[] = [
                        'kind' => 'namespace',
                        'namespaceIndex' => $nestedNamespace['namespaceIndex'],
                        'exported' => $nestedNamespace['exported'],
                    ];
                }
                continue;
            }

            if ($first->text === 'export' && ($this->tokens[$start + 1] ?? null)?->text === 'import') {
                $import = $this->parseNamespaceImportEquals($start + 1, $end, true);
                $imports[$import['local']] = $import;
                $ordered[] = ['kind' => 'import', 'local' => $import['local']];
                continue;
            }

            if ($first->text === 'import') {
                $import = $this->parseNamespaceImportEquals($start, $end, false);
                $imports[$import['local']] = $import;
                $ordered[] = ['kind' => 'import', 'local' => $import['local']];
                continue;
            }

            if ($this->isExportedDeclareVariableStatement($start)) {
                foreach ($this->parseExportedDeclareVariableNames($start, $end) as $declaredName) {
                    $exportedValues[$declaredName] = true;
                }
                continue;
            }

            $enum = $this->parseNamespaceEnumStatement($start, $end);
            if ($enum !== null) {
                if (!$enum['declared'] && (!$enum['const'] || $enum['exported'])) {
                    if ($enum['exported']) {
                        $exportedLocalBindings[$enum['name']] = true;
                    }
                    $ordered[] = ['kind' => 'enum', 'enum' => $enum];
                    $hasRuntimeNamespaceStatement = true;
                }
                continue;
            }

            if ($this->isExportedFunctionOrClassStatement($start)) {
                $declaration = $this->parseExportedFunctionOrClassDeclaration($start, $end);
                $exportedLocalBindings[$declaration['name']] = true;
                $ordered[] = ['kind' => 'exported-local-declaration', 'declaration' => $declaration];
                $hasRuntimeNamespaceStatement = true;
                continue;
            }

            if ($this->isExportedVariableStatement($start)) {
                $declarations = $this->parseExportedVariableDeclarations($start, $end);
                foreach ($declarations as $declaration) {
                    foreach ($declaration['names'] as $exportedName) {
                        $exportedValues[$exportedName] = true;
                    }
                }
                $ordered[] = ['kind' => 'exported-vars', 'declarations' => $declarations];
                $hasRuntimeNamespaceStatement = true;
                continue;
            }

            if ($this->isTypeOnlyNamespaceStatement($start)) {
                continue;
            }

            $this->rejectNestedNamespaceImportEquals($start, $end);
            $ordered[] = ['kind' => 'body', 'start' => $start, 'end' => $end];
            $hasRuntimeNamespaceStatement = true;
        }

        $parameter = $this->namespaceParameterName($name, $imports, $exportedValues, $exportedLocalBindings);
        $rendered = [];
        foreach ($ordered as $index => $item) {
            if ($item['kind'] === 'body') {
                $statement = $this->printBodyStatement((int) $item['start'], (int) $item['end'], $parameter, $imports, $exportedValues);
                $rendered[$index] = [
                    'lines' => $statement['text'] === '' ? [] : [$statement['text']],
                    'uses' => $statement['uses'],
                ];
            } elseif ($item['kind'] === 'exported-vars') {
                $rendered[$index] = $this->printExportedVariableAssignments($item['declarations'], $parameter, $imports, $exportedValues);
            } elseif ($item['kind'] === 'exported-local-declaration') {
                $rendered[$index] = $this->printExportedLocalDeclaration($item['declaration'], $parameter, $imports, $exportedValues);
            } elseif ($item['kind'] === 'namespace') {
                [$namespaceOutput] = $this->lowerNamespaceAt((int) $item['namespaceIndex'], (bool) $item['exported'], $parameter);
                $rendered[$index] = [
                    'lines' => $namespaceOutput === '' ? [] : explode("\n", rtrim($namespaceOutput, "\n")),
                    'uses' => [],
                ];
            } elseif ($item['kind'] === 'enum') {
                $rendered[$index] = $this->printNamespaceEnum($item['enum'], $parameter);
            } elseif ($item['kind'] === 'using') {
                $rendered[$index] = $this->printNamespaceUsingDeclaration(
                    (int) $item['start'],
                    (int) $item['end'],
                    $parameter,
                    $imports,
                    $exportedValues,
                );
                $hasUsingHelperScope = true;
            }
        }

        $used = [];
        foreach ($imports as $local => $import) {
            if ($import['exported']) {
                $used[$local] = true;
            }
        }
        foreach ($rendered as $statement) {
            foreach ($statement['uses'] as $local) {
                $used[$local] = true;
            }
        }

        $lines = [];
        foreach ($ordered as $index => $item) {
            if ($item['kind'] === 'import') {
                $local = (string) $item['local'];
                if (!isset($used[$local])) {
                    continue;
                }
                $import = $imports[$local];
                $lines[] = $import['exported']
                    ? $parameter . '.' . $local . ' = ' . $import['source'] . ';'
                    : 'const ' . $local . ' = ' . $import['source'] . ';';
                continue;
            }

            foreach ($rendered[$index]['lines'] ?? [] as $line) {
                $lines[] = $line;
            }
        }

        if ($lines === [] && !$hasRuntimeNamespaceStatement) {
            return '';
        }

        if ($hasUsingHelperScope) {
            $lines = $this->namespaceUsingHelperScopeLines($lines);
        }

        return $this->renderNamespaceIife($name, $parameter, $exported, $parentNamespace, $lines);
    }

    /**
     * @param list<string> $lines
     */
    private function renderNamespaceIife(string $name, string $parameter, bool $exported, ?string $parentNamespace, array $lines): string
    {
        $declaration = $parentNamespace === null
            ? ($exported ? 'export var ' : 'var ') . $name . ';'
            : 'let ' . $name . ';';
        $initializer = $parentNamespace === null
            ? $name . ' || (' . $name . ' = {})'
            : ($exported
                ? $name . ' = ' . $parentNamespace . '.' . $name . ' || (' . $parentNamespace . '.' . $name . ' = {})'
                : $name . ' || (' . $name . ' = {})');

        return $declaration . "\n"
            . '((' . $parameter . ") => {\n"
            . $this->indentNamespaceLines($lines)
            . '})(' . $initializer . ");\n";
    }

    /**
     * @param list<string> $lines
     */
    private function indentNamespaceLines(array $lines): string
    {
        $output = '';
        foreach ($lines as $line) {
            foreach (explode("\n", $line) as $part) {
                $output .= '  ' . $part . "\n";
            }
        }

        return $output;
    }

    /**
     * @return list<array{0:int, 1:int}>
     */
    private function namespaceStatements(int $start, int $end): array
    {
        $statements = [];
        for ($i = $start; $i < $end; $i++) {
            if (($this->tokens[$i] ?? null)?->text === ';') {
                continue;
            }

            $statementEnd = $this->declarationStatementEnd($i, $end)
                ?? $this->findStatementEndOrLineBreak($i, $end);
            $effectiveEnd = $statementEnd;
            if (($this->tokens[$effectiveEnd] ?? null)?->text === ';') {
                $effectiveEnd--;
            }
            if ($effectiveEnd >= $i) {
                $statements[] = [$i, $effectiveEnd];
            }

            $i = $statementEnd;
        }

        return $statements;
    }

    private function declarationStatementEnd(int $start, int $limit): ?int
    {
        $cursor = $start;
        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text === 'declare') {
            $cursor++;
        }
        if ($this->isNamespaceKeywordAt($cursor)) {
            for ($i = $cursor + 1; $i < $limit; $i++) {
                if (($this->tokens[$i] ?? null)?->text === '{') {
                    return $this->findMatchingPunctuator($i, '{', '}');
                }
            }
        }
        $enumCursor = $cursor;
        if (($this->tokens[$enumCursor] ?? null)?->text === 'const'
            && ($this->tokens[$enumCursor + 1] ?? null)?->text === 'enum'
        ) {
            $enumCursor++;
        }
        if (($this->tokens[$enumCursor] ?? null)?->text === 'enum') {
            for ($i = $enumCursor + 1; $i < $limit; $i++) {
                if (($this->tokens[$i] ?? null)?->text === '{') {
                    return $this->findMatchingPunctuator($i, '{', '}');
                }
            }
        }
        if (($this->tokens[$cursor] ?? null)?->text === 'async'
            && ($this->tokens[$cursor + 1] ?? null)?->text === 'function'
        ) {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text !== 'function'
            && ($this->tokens[$cursor] ?? null)?->text !== 'class'
        ) {
            return null;
        }

        for ($i = $cursor + 1; $i < $limit; $i++) {
            if (($this->tokens[$i] ?? null)?->text === '{') {
                return $this->findMatchingPunctuator($i, '{', '}');
            }
        }

        return null;
    }

    /**
     * @return array{namespaceIndex:int, exported:bool, declared:bool}|null
     */
    private function namespaceStatementAt(int $start): ?array
    {
        $cursor = $start;
        $exported = false;
        $declared = false;

        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            $exported = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'declare') {
            $declared = true;
            $cursor++;
        }

        if (!$this->isNamespaceKeywordAt($cursor)) {
            return null;
        }

        return [
            'namespaceIndex' => $cursor,
            'exported' => $exported,
            'declared' => $declared,
        ];
    }

    private function namespaceBlockEndFromIndex(int $namespaceIndex): int
    {
        [, $blockStart] = $this->readNamespaceName($namespaceIndex + 1);
        if (($this->tokens[$blockStart] ?? null)?->text !== '{') {
            throw new \InvalidArgumentException('Expected TypeScript namespace block');
        }

        return $this->findMatchingPunctuator($blockStart, '{', '}');
    }

    private function rootNamespaceName(int $namespaceIndex): string
    {
        [$qualifiedName] = $this->readNamespaceName($namespaceIndex + 1);
        if ($qualifiedName === '') {
            throw new \InvalidArgumentException('Expected TypeScript namespace name');
        }

        return explode('.', $qualifiedName)[0];
    }

    private function stripTopLevelNamespaceDeclaration(string $output, string $name): string
    {
        foreach (['var ' . $name . ";\n", 'export var ' . $name . ";\n"] as $prefix) {
            if (str_starts_with($output, $prefix)) {
                return substr($output, strlen($prefix));
            }
        }

        return $output;
    }

    private function printTopLevelStatement(int $start, int $end): string
    {
        if ($this->isTypeOnlyNamespaceStatement($start)) {
            return '';
        }

        return rtrim((new TypeScriptModuleLowerer())->lower($this->originalStatementSource($start, $end)), "\n");
    }

    private function originalStatementSource(int $start, int $end): string
    {
        $first = $this->tokens[$start] ?? null;
        $last = $this->tokens[$end] ?? null;
        if ($first === null || $last === null) {
            return '';
        }

        return substr($this->source, $first->offset, $last->offset + strlen($last->text) - $first->offset);
    }

    /**
     * @return array{name:string, kind:string}|null
     */
    private function topLevelValueDeclaration(int $start, int $end): ?array
    {
        $cursor = $start;
        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'declare') {
            return null;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'async'
            && ($this->tokens[$cursor + 1] ?? null)?->text === 'function'
        ) {
            $cursor++;
        }

        $kind = $this->tokens[$cursor] ?? null;
        if ($kind === null) {
            return null;
        }

        if (in_array($kind->text, ['var', 'let'], true)) {
            $name = $this->tokens[$cursor + 1] ?? null;

            return $name?->kind === 'identifier' ? ['name' => $name->text, 'kind' => $kind->text] : null;
        }

        if ($kind->text === 'const') {
            if (($this->tokens[$cursor + 1] ?? null)?->text === 'enum') {
                $name = $this->tokens[$cursor + 2] ?? null;

                return $name?->kind === 'identifier' ? ['name' => $name->text, 'kind' => 'enum'] : null;
            }

            $name = $this->tokens[$cursor + 1] ?? null;

            return $name?->kind === 'identifier' ? ['name' => $name->text, 'kind' => 'const'] : null;
        }

        if ($kind->text === 'function') {
            $nameIndex = $cursor + 1;
            if (($this->tokens[$nameIndex] ?? null)?->text === '*') {
                $nameIndex++;
            }
            $name = $this->tokens[$nameIndex] ?? null;

            return $name?->kind === 'identifier' ? ['name' => $name->text, 'kind' => 'function'] : null;
        }

        if ($kind->text === 'class') {
            $name = $this->tokens[$cursor + 1] ?? null;

            return $name?->kind === 'identifier' ? ['name' => $name->text, 'kind' => 'class'] : null;
        }

        if ($kind->text === 'enum') {
            $name = $this->tokens[$cursor + 1] ?? null;

            return $name?->kind === 'identifier' ? ['name' => $name->text, 'kind' => 'enum'] : null;
        }

        return null;
    }

    /**
     * @return array{local:string, source:string, exported:bool}
     */
    private function parseNamespaceImportEquals(int $importIndex, int $end, bool $exported): array
    {
        $local = $this->tokens[$importIndex + 1] ?? null;
        if ($local?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after namespace import');
        }
        if (($this->tokens[$importIndex + 2] ?? null)?->text !== '=') {
            throw new \InvalidArgumentException('Expected "=" after namespace import name');
        }

        $targetStart = $importIndex + 3;
        if ($targetStart > $end) {
            throw new \InvalidArgumentException('Expected TypeScript import equals target');
        }

        $source = $this->printTokenRange($targetStart, $end, '', []);
        if ($source === '') {
            throw new \InvalidArgumentException('Expected TypeScript import equals target');
        }

        return [
            'local' => $local->text,
            'source' => $source,
            'exported' => $exported,
        ];
    }

    private function isExportedVariableStatement(int $start): bool
    {
        return ($this->tokens[$start] ?? null)?->text === 'export'
            && in_array(($this->tokens[$start + 1] ?? null)?->text, ['var', 'let', 'const'], true);
    }

    private function isExportedFunctionOrClassStatement(int $start): bool
    {
        $cursor = $start;
        if (($this->tokens[$cursor] ?? null)?->text !== 'export') {
            return false;
        }
        $cursor++;
        if (($this->tokens[$cursor] ?? null)?->text === 'async'
            && ($this->tokens[$cursor + 1] ?? null)?->text === 'function'
        ) {
            return true;
        }

        return in_array(($this->tokens[$cursor] ?? null)?->text, ['function', 'class'], true);
    }

    /**
     * @return array{name:string, declarationStart:int, declarationEnd:int}
     */
    private function parseExportedFunctionOrClassDeclaration(int $start, int $end): array
    {
        $declarationStart = $start + 1;
        $keywordIndex = $declarationStart;
        if (($this->tokens[$keywordIndex] ?? null)?->text === 'async'
            && ($this->tokens[$keywordIndex + 1] ?? null)?->text === 'function'
        ) {
            $keywordIndex++;
        }

        $keyword = $this->tokens[$keywordIndex] ?? null;
        if ($keyword === null || !in_array($keyword->text, ['function', 'class'], true)) {
            throw new \InvalidArgumentException('Expected TypeScript namespace function or class export');
        }

        $nameIndex = $keywordIndex + 1;
        if ($keyword->text === 'function' && ($this->tokens[$nameIndex] ?? null)?->text === '*') {
            $nameIndex++;
        }
        $name = $this->tokens[$nameIndex] ?? null;
        if ($name?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after TypeScript namespace function or class export');
        }

        return [
            'name' => $name->text,
            'declarationStart' => $declarationStart,
            'declarationEnd' => $end,
        ];
    }

    /**
     * @return list<array{names:list<string>, targetStart:int, targetEnd:int, valueStart:?int, valueEnd:?int}>
     */
    private function parseExportedVariableDeclarations(int $start, int $end): array
    {
        $declarations = [];
        $cursor = $start + 2;

        while ($cursor <= $end) {
            $targetStart = $cursor;
            [$names, $cursor] = $this->bindingNamesAt($cursor, $end);
            if ($names === []) {
                throw new \InvalidArgumentException('Expected binding after namespace export variable declaration');
            }
            $targetEnd = $cursor - 1;

            $valueStart = null;
            $valueEnd = null;
            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $valueStart = $cursor + 1;
                if ($valueStart > $end) {
                    throw new \InvalidArgumentException('Expected initializer after namespace export variable declaration');
                }
                $valueEnd = $this->variableInitializerEnd($valueStart, $end);
                $cursor = $valueEnd + 1;
            }

            $declarations[] = [
                'names' => $names,
                'targetStart' => $targetStart,
                'targetEnd' => $targetEnd,
                'valueStart' => $valueStart,
                'valueEnd' => $valueEnd,
            ];

            if ($cursor > $end) {
                break;
            }
            if (($this->tokens[$cursor] ?? null)?->text !== ',') {
                throw new \InvalidArgumentException('Expected "," after namespace export variable declaration');
            }
            $cursor++;
        }

        return $declarations;
    }

    private function isExportedDeclareVariableStatement(int $start): bool
    {
        return ($this->tokens[$start] ?? null)?->text === 'export'
            && ($this->tokens[$start + 1] ?? null)?->text === 'declare'
            && in_array(($this->tokens[$start + 2] ?? null)?->text, ['var', 'let', 'const'], true);
    }

    /**
     * @return list<string>
     */
    private function parseExportedDeclareVariableNames(int $start, int $end): array
    {
        return $this->bindingNamesInVariableDeclarations($start + 3, $end);
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
     * @return array{name:string, exported:bool, declared:bool, const:bool, members:list<array{name:string, assignment:string, assignmentKind:string}>}|null
     */
    private function parseNamespaceEnumStatement(int $start, int $end): ?array
    {
        $cursor = $start;
        $exported = false;
        $declared = false;
        $const = false;

        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            $exported = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'declare') {
            $declared = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'const'
            && ($this->tokens[$cursor + 1] ?? null)?->text === 'enum'
        ) {
            $const = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text !== 'enum') {
            return null;
        }

        $name = $this->tokens[$cursor + 1] ?? null;
        if ($name?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected TypeScript namespace enum name');
        }
        if (($this->tokens[$cursor + 2] ?? null)?->text !== '{') {
            throw new \InvalidArgumentException('Expected TypeScript namespace enum block');
        }

        $open = $cursor + 2;
        $close = $this->findMatchingPunctuator($open, '{', '}');
        if ($close > $end) {
            throw new \InvalidArgumentException('TypeScript namespace enum block must end inside its statement');
        }

        return [
            'name' => $name->text,
            'exported' => $exported,
            'declared' => $declared,
            'const' => $const,
            'members' => $this->parseNamespaceEnumMembers($open + 1, $close, $name->text),
        ];
    }

    /**
     * @return list<array{name:string, assignment:string, assignmentKind:string}>
     */
    private function parseNamespaceEnumMembers(int $start, int $close, string $enumName): array
    {
        $members = [];
        $cursor = $start;
        $previousNumeric = -1.0;

        while ($cursor < $close) {
            if (in_array(($this->tokens[$cursor] ?? null)?->text, [',', ';'], true)) {
                $cursor++;
                continue;
            }

            $nameToken = $this->tokens[$cursor] ?? null;
            if ($nameToken === null || ($nameToken->kind !== 'identifier' && $nameToken->kind !== 'string')) {
                throw new \InvalidArgumentException('Expected TypeScript namespace enum member name');
            }

            $memberName = $this->enumMemberName($nameToken);
            $cursor++;
            $assignmentKind = 'number';

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $expressionStart = $cursor + 1;
                if ($expressionStart >= $close) {
                    throw new \InvalidArgumentException('Expected TypeScript namespace enum member value');
                }

                $expressionEnd = $this->namespaceEnumExpressionEnd($expressionStart, $close);
                [$assignment, $assignmentKind, $numericValue] = $this->namespaceEnumMemberAssignment($expressionStart, $expressionEnd, $enumName);
                $previousNumeric = $numericValue;
                $cursor = $expressionEnd + 1;
            } elseif ($previousNumeric !== null) {
                $previousNumeric++;
                $assignment = $this->formatEnumNumber($previousNumeric);
            } else {
                $assignment = 'void 0';
                $assignmentKind = 'void';
            }

            $members[] = [
                'name' => $memberName,
                'assignment' => $assignment,
                'assignmentKind' => $assignmentKind,
            ];

            $after = $this->tokens[$cursor] ?? null;
            if ($cursor < $close && $after?->text !== ',' && $after?->text !== ';') {
                throw new \InvalidArgumentException('Expected "," after TypeScript namespace enum member');
            }
            if ($cursor < $close) {
                $cursor++;
            }
        }

        return $members;
    }

    private function namespaceEnumExpressionEnd(int $start, int $close): int
    {
        $depth = 0;
        for ($i = $start; $i < $close; $i++) {
            $text = $this->tokens[$i]->text;
            if ($depth === 0 && ($text === ',' || $text === ';')) {
                return $i - 1;
            }

            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            }
        }

        return $close - 1;
    }

    /**
     * @return array{0:string, 1:string, 2:?float}
     */
    private function namespaceEnumMemberAssignment(int $start, int $end, string $enumName): array
    {
        if ($this->hasAdjacentEnumValueTokens($start, $end)) {
            throw new \InvalidArgumentException('Expected "," after TypeScript namespace enum member');
        }

        if ($start === $end) {
            $token = $this->tokens[$start];
            if ($token->kind === 'number') {
                return [$this->formatEnumNumber($token->numberValue ?? (float) $token->text), 'number', $token->numberValue];
            }
            if ($token->kind === 'string') {
                return [$this->quoteJsString($this->stringTokenValue($token)), 'string', null];
            }
            if ($token->kind === 'identifier' && $token->text !== $enumName) {
                return [$token->text, 'unknown', null];
            }
        }

        if ($end === $start + 1
            && in_array(($this->tokens[$start] ?? null)?->text, ['+', '-'], true)
            && ($this->tokens[$end] ?? null)?->kind === 'number'
        ) {
            $value = $this->tokens[$end]->numberValue ?? (float) $this->tokens[$end]->text;
            if ($this->tokens[$start]->text === '-') {
                $value *= -1;
            }

            return [$this->formatEnumNumber($value), 'number', $value];
        }

        $uses = [];

        return [$this->printTokenRange($start, $end, '', [], [], $uses), 'unknown', null];
    }

    private function hasAdjacentEnumValueTokens(int $start, int $end): bool
    {
        for ($i = $start; $i < $end; $i++) {
            $left = $this->tokens[$i] ?? null;
            $right = $this->tokens[$i + 1] ?? null;
            if ($left === null || $right === null) {
                continue;
            }

            if (in_array($left->kind, ['identifier', 'number', 'string'], true)
                && in_array($right->kind, ['identifier', 'number', 'string'], true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function variableInitializerEnd(int $start, int $end): int
    {
        $depth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($depth === 0 && $text === ',') {
                return $i - 1;
            }

            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            }
        }

        return $end;
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     */
    private function namespaceParameterName(string $namespace, array $imports, array $exportedValues, array $exportedLocalBindings = []): string
    {
        return isset($imports[$namespace]) || isset($exportedValues[$namespace]) || isset($exportedLocalBindings[$namespace]) ? '_' . $namespace : $namespace;
    }

    /**
     * @param list<array{names:list<string>, targetStart:int, targetEnd:int, valueStart:?int, valueEnd:?int}> $declarations
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @return array{lines:list<string>, uses:list<string>}
     */
    private function printExportedVariableAssignments(array $declarations, string $namespace, array $imports, array $exportedValues): array
    {
        $lines = [];
        $uses = [];

        foreach ($declarations as $declaration) {
            if ($declaration['valueStart'] === null || $declaration['valueEnd'] === null) {
                continue;
            }

            $target = $this->printBindingAssignmentTarget(
                $declaration['targetStart'],
                $declaration['targetEnd'],
                $namespace,
                $imports,
                $exportedValues,
                $uses
            );
            $value = $this->printTokenRange(
                $declaration['valueStart'],
                $declaration['valueEnd'],
                $namespace,
                $imports,
                $exportedValues,
                $uses
            );
            $lines[] = $target . ' = ' . $value . ';';
        }

        return ['lines' => $lines, 'uses' => array_values(array_unique($uses))];
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @param list<string> $uses
     */
    private function printBindingAssignmentTarget(
        int $start,
        int $end,
        string $namespace,
        array $imports,
        array $exportedValues,
        array &$uses
    ): string {
        $bindingIndexes = array_fill_keys($this->bindingIdentifierIndexes($start, $end), true);
        $parts = [];
        $previous = null;

        for ($i = $start; $i <= $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            $text = $token->text;
            if ($token->kind === 'string') {
                $text = $this->quoteJsString($this->stringTokenValue($token));
            } elseif (isset($bindingIndexes[$i]) && $token->kind === 'identifier') {
                $text = $namespace . '.' . $token->text;
            } elseif ($token->kind === 'identifier'
                && isset($imports[$token->text])
                && !$this->isPropertyAccessIdentifier($i)
            ) {
                $uses[] = $token->text;
                if ($imports[$token->text]['exported']) {
                    $text = $namespace . '.' . $token->text;
                }
            } elseif ($token->kind === 'identifier'
                && isset($exportedValues[$token->text])
                && !$this->isPropertyAccessIdentifier($i)
                && ($this->tokens[$i + 1] ?? null)?->text !== ':'
            ) {
                $text = $namespace . '.' . $token->text;
            }

            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
    }

    /**
     * @return list<int>
     */
    private function bindingIdentifierIndexes(int $start, int $end): array
    {
        [$indexes] = $this->bindingIdentifierIndexesAt($start, $end);

        return array_values(array_unique($indexes));
    }

    /**
     * @return array{0:list<int>, 1:int}
     */
    private function bindingIdentifierIndexesAt(int $start, int $end): array
    {
        $token = $this->tokens[$start] ?? null;
        if ($token === null) {
            return [[], $start + 1];
        }

        if ($token->kind === 'identifier') {
            return [[$start], $start + 1];
        }

        if ($token->text === '[') {
            return $this->arrayBindingIdentifierIndexesAt($start, $end);
        }

        if ($token->text === '{') {
            return $this->objectBindingIdentifierIndexesAt($start, $end);
        }

        return [[], $start + 1];
    }

    /**
     * @return array{0:list<int>, 1:int}
     */
    private function arrayBindingIdentifierIndexesAt(int $open, int $end): array
    {
        $close = min($this->findMatchingPunctuator($open, '[', ']'), $end);
        $indexes = [];
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

            [$elementIndexes, $cursor] = $this->bindingIdentifierIndexesAt($cursor, $close);
            foreach ($elementIndexes as $index) {
                $indexes[] = $index;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $cursor = $this->skipBindingInitializer($cursor + 1, $close);
            }
        }

        return [$indexes, $close + 1];
    }

    /**
     * @return array{0:list<int>, 1:int}
     */
    private function objectBindingIdentifierIndexesAt(int $open, int $end): array
    {
        $close = min($this->findMatchingPunctuator($open, '{', '}'), $end);
        $indexes = [];
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
                [$restIndexes, $cursor] = $this->bindingIdentifierIndexesAt($cursor + 3, $close);
                foreach ($restIndexes as $index) {
                    $indexes[] = $index;
                }
                continue;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '[') {
                $cursor = $this->findMatchingPunctuator($cursor, '[', ']') + 1;
                if (($this->tokens[$cursor] ?? null)?->text === ':') {
                    [$valueIndexes, $cursor] = $this->bindingIdentifierIndexesAt($cursor + 1, $close);
                    foreach ($valueIndexes as $index) {
                        $indexes[] = $index;
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
                [$valueIndexes, $cursor] = $this->bindingIdentifierIndexesAt($cursor + 2, $close);
                foreach ($valueIndexes as $index) {
                    $indexes[] = $index;
                }
            } else {
                if ($property->kind === 'identifier') {
                    $indexes[] = $cursor;
                }
                $cursor++;
            }

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $cursor = $this->skipBindingInitializer($cursor + 1, $close);
            }
        }

        return [$indexes, $close + 1];
    }

    /**
     * @param array{name:string, declarationStart:int, declarationEnd:int} $declaration
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @return array{lines:list<string>, uses:list<string>}
     */
    private function printExportedLocalDeclaration(array $declaration, string $namespace, array $imports, array $exportedValues): array
    {
        $statement = $this->printNamespaceFunctionStatement(
            $declaration['declarationStart'],
            $declaration['declarationEnd'],
            $namespace,
            $imports,
            $exportedValues
        );
        if ($statement === null) {
            $uses = [];
            $source = $this->printTokenRange(
                $declaration['declarationStart'],
                $declaration['declarationEnd'],
                $namespace,
                $imports,
                $exportedValues,
                $uses
            );
            $statement = [
                'text' => $source,
                'uses' => array_values(array_unique($uses)),
            ];
        }

        return [
            'lines' => [
                $statement['text'],
                $namespace . '.' . $declaration['name'] . ' = ' . $declaration['name'] . ';',
            ],
            'uses' => $statement['uses'],
        ];
    }

    /**
     * @param array{name:string, exported:bool, declared:bool, const:bool, members:list<array{name:string, assignment:string, assignmentKind:string}>} $enum
     * @return array{lines:list<string>, uses:list<string>}
     */
    private function printNamespaceEnum(array $enum, string $namespace): array
    {
        $name = $enum['name'];
        $lines = [
            'let ' . $name . ';',
            '((' . $name . ') => {',
        ];

        foreach ($enum['members'] as $member) {
            $memberName = $this->quoteJsString($member['name']);
            if ($member['assignmentKind'] === 'string') {
                $lines[] = '  ' . $name . '[' . $memberName . '] = ' . $member['assignment'] . ';';
            } else {
                $lines[] = '  ' . $name . '[' . $name . '[' . $memberName . '] = ' . $member['assignment'] . '] = ' . $memberName . ';';
            }
        }

        $initializer = $enum['exported']
            ? $name . ' = ' . $namespace . '.' . $name . ' || (' . $namespace . '.' . $name . ' = {})'
            : $name . ' || (' . $name . ' = {})';
        $lines[] = '})(' . $initializer . ');';

        return ['lines' => $lines, 'uses' => []];
    }

    private function isNamespaceUsingStatement(int $start): bool
    {
        if (($this->tokens[$start] ?? null)?->text === 'using') {
            return true;
        }

        return ($this->tokens[$start] ?? null)?->text === 'await'
            && ($this->tokens[$start + 1] ?? null)?->text === 'using'
            && !$this->hasLineBreakBetween($start, $start + 1);
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @return array{lines:list<string>, uses:list<string>}
     */
    private function printNamespaceUsingDeclaration(int $start, int $end, string $namespace, array $imports, array $exportedValues): array
    {
        $cursor = $start;
        if (($this->tokens[$cursor] ?? null)?->text === 'await') {
            throw new \InvalidArgumentException('TypeScript namespace await using lowering is not supported');
        }
        if (($this->tokens[$cursor] ?? null)?->text !== 'using') {
            throw new \InvalidArgumentException('Expected TypeScript namespace using declaration');
        }

        $cursor++;
        $declarators = [];
        $uses = [];
        while ($cursor <= $end) {
            $name = $this->tokens[$cursor] ?? null;
            if ($name?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected identifier in TypeScript namespace using declaration');
            }
            if ($this->hasLineBreakBetween($cursor - 1, $cursor)) {
                throw new \InvalidArgumentException('Expected identifier in TypeScript namespace using declaration');
            }

            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === ':') {
                $cursor = $this->skipNamespaceUsingTypeExpression($cursor + 1, $end);
            }
            if (($this->tokens[$cursor] ?? null)?->text !== '=') {
                throw new \InvalidArgumentException('The declaration "' . $name->text . '" must be initialized');
            }

            $valueStart = $cursor + 1;
            if ($valueStart > $end) {
                throw new \InvalidArgumentException('Expected initializer after TypeScript namespace using declaration');
            }

            $cursor = $this->namespaceUsingInitializerEnd($valueStart, $end);
            $valueEnd = ($this->tokens[$cursor] ?? null)?->text === ',' ? $cursor - 1 : $end;
            if ($valueEnd < $valueStart) {
                throw new \InvalidArgumentException('Expected initializer after TypeScript namespace using declaration');
            }

            $value = $this->printTokenRange($valueStart, $valueEnd, $namespace, $imports, $exportedValues, $uses);
            $declarators[] = $name->text . ' = ' . $this->usingHelperUsingName . '(_stack, ' . $value . ')';

            if (($this->tokens[$cursor] ?? null)?->text !== ',') {
                break;
            }
            $cursor++;
        }

        return [
            'lines' => ['const ' . implode(', ', $declarators) . ';'],
            'uses' => array_values(array_unique($uses)),
        ];
    }

    private function skipNamespaceUsingTypeExpression(int $start, int $end): int
    {
        $depth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '[', '<'], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']', '>'], true)) {
                $depth--;
                continue;
            }
            if ($depth === 0 && ($text === '=' || $text === ',')) {
                return $i;
            }
        }

        return $end + 1;
    }

    private function namespaceUsingInitializerEnd(int $start, int $end): int
    {
        $depth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                continue;
            }
            if ($text === ',' && $depth === 0) {
                return $i;
            }
        }

        return $end + 1;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function namespaceUsingHelperScopeLines(array $lines): array
    {
        $this->needsUsingHelperRuntime = true;
        $wrapped = [
            'var _stack = [];',
            'try {',
        ];
        foreach ($lines as $line) {
            $wrapped[] = '  ' . $line;
        }
        $wrapped[] = '} catch (_) {';
        $wrapped[] = '  var _error = _, _hasError = true;';
        $wrapped[] = '} finally {';
        $wrapped[] = '  ' . $this->usingHelperCallDisposeName . '(_stack, _error, _hasError);';
        $wrapped[] = '}';

        return $wrapped;
    }

    private function usingHelperRuntime(): string
    {
        return strtr(<<<'JS'
var %%knownSymbol%% = (name, symbol) => (symbol = Symbol[name]) ? symbol : /* @__PURE__ */ Symbol.for("Symbol." + name);
var %%typeError%% = (msg) => {
  throw TypeError(msg);
};
var %%using%% = (stack, value, async) => {
  if (value != null) {
    if (typeof value !== "object" && typeof value !== "function") %%typeError%%("Object expected");
    var dispose, inner;
    if (async) dispose = value[%%knownSymbol%%("asyncDispose")];
    if (dispose === void 0) {
      dispose = value[%%knownSymbol%%("dispose")];
      if (async) inner = dispose;
    }
    if (typeof dispose !== "function") %%typeError%%("Object not disposable");
    if (inner) dispose = function() {
      try {
        inner.call(this);
      } catch (e) {
        return Promise.reject(e);
      }
    };
    stack.push([async, dispose, value]);
  } else if (async) {
    stack.push([async]);
  }
  return value;
};
var %%callDispose%% = (stack, error, hasError) => {
  var E = typeof SuppressedError === "function" ? SuppressedError : function(e, s, m, _2) {
    return _2 = Error(m), _2.name = "SuppressedError", _2.error = e, _2.suppressed = s, _2;
  };
  var fail = (e) => error = hasError ? new E(e, error, "An error was suppressed during disposal") : (hasError = true, e);
  var next = (it) => {
    while (it = stack.pop()) {
      try {
        var result = it[1] && it[1].call(it[2]);
        if (it[0]) return Promise.resolve(result).then(next, (e) => (fail(e), next()));
      } catch (e) {
        fail(e);
      }
    }
    if (hasError) throw error;
  };
  return next();
};
JS, [
            '%%knownSymbol%%' => $this->usingHelperKnownSymbolName,
            '%%typeError%%' => $this->usingHelperTypeErrorName,
            '%%using%%' => $this->usingHelperUsingName,
            '%%callDispose%%' => $this->usingHelperCallDisposeName,
        ]) . "\n";
    }

    private function configureHelperNames(): void
    {
        $used = $this->sourceIdentifierMap();
        $this->usingHelperKnownSymbolName = $this->allocateUniqueIdentifier('__knownSymbol', $used);
        $this->usingHelperTypeErrorName = $this->allocateUniqueIdentifier('__typeError', $used);
        $this->usingHelperUsingName = $this->allocateUniqueIdentifier('__using', $used);
        $this->usingHelperCallDisposeName = $this->allocateUniqueIdentifier('__callDispose', $used);
    }

    /**
     * @return array<string, true>
     */
    private function sourceIdentifierMap(): array
    {
        $used = [];
        foreach ($this->tokens as $token) {
            if ($token->kind === 'identifier') {
                $used[$token->text] = true;
            }
        }

        return $used;
    }

    /**
     * @param array<string, true> $used
     */
    private function allocateUniqueIdentifier(string $base, array &$used): string
    {
        if (!isset($used[$base])) {
            $used[$base] = true;

            return $base;
        }

        for ($suffix = 2; ; $suffix++) {
            $candidate = $base . $suffix;
            if (!isset($used[$candidate])) {
                $used[$candidate] = true;

                return $candidate;
            }
        }
    }

    private function isTypeOnlyNamespaceStatement(int $start): bool
    {
        $token = $this->tokens[$start] ?? null;
        if ($token?->kind !== 'identifier') {
            return false;
        }

        if ($token->text === 'declare') {
            return true;
        }

        if (in_array($token->text, ['type', 'interface'], true)) {
            return true;
        }

        if ($token->text === 'export'
            && ($this->tokens[$start + 1] ?? null)?->text === 'declare'
        ) {
            return true;
        }

        return $token->text === 'export'
            && ($this->tokens[$start + 1] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$start + 1]->text, ['type', 'interface'], true);
    }

    private function rejectNestedNamespaceImportEquals(int $start, int $end): void
    {
        for ($i = $start; $i <= $end; $i++) {
            if (($this->tokens[$i] ?? null)?->text === 'import'
                && ($this->tokens[$i + 1] ?? null)?->kind === 'identifier'
                && ($this->tokens[$i + 2] ?? null)?->text === '='
            ) {
                throw new \InvalidArgumentException('TypeScript namespace import equals is only allowed at namespace scope');
            }
            if (($this->tokens[$i] ?? null)?->text === 'export'
                && ($this->tokens[$i + 1] ?? null)?->text === 'import'
                && ($this->tokens[$i + 2] ?? null)?->kind === 'identifier'
                && ($this->tokens[$i + 3] ?? null)?->text === '='
            ) {
                throw new \InvalidArgumentException('TypeScript namespace export import equals is only allowed at namespace scope');
            }
        }
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @return array{text:string, uses:list<string>}
     */
    private function printBodyStatement(int $start, int $end, string $namespace, array $imports, array $exportedValues): array
    {
        $function = $this->printNamespaceFunctionStatement($start, $end, $namespace, $imports, $exportedValues);
        if ($function !== null) {
            return $function;
        }

        $uses = [];
        $text = $this->printTokenRange($start, $end, $namespace, $imports, $exportedValues, $uses);
        if ($text !== '' && !str_ends_with($text, ';')) {
            $text .= ';';
        }

        return ['text' => $text, 'uses' => array_values(array_unique($uses))];
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @return array{text:string, uses:list<string>}|null
     */
    private function printNamespaceFunctionStatement(int $start, int $end, string $namespace, array $imports, array $exportedValues): ?array
    {
        $functionIndex = $start;
        $isAsync = false;
        if (($this->tokens[$functionIndex] ?? null)?->text === 'async'
            && ($this->tokens[$functionIndex + 1] ?? null)?->text === 'function'
        ) {
            $isAsync = true;
            $functionIndex++;
        }

        if (($this->tokens[$functionIndex] ?? null)?->text !== 'function') {
            return null;
        }

        $bodyOpen = $this->functionBodyOpen($functionIndex + 1, $end);
        if ($bodyOpen === null) {
            return null;
        }
        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $end) {
            return null;
        }

        $uses = [];
        $header = $this->printTokenRange($start, $bodyOpen - 1, $namespace, $imports, $exportedValues, $uses);
        [$body, $bodyUses] = $this->printNamespaceFunctionBody(
            $bodyOpen + 1,
            $bodyClose,
            $isAsync,
            $namespace,
            $imports,
            $exportedValues
        );

        return [
            'text' => $header . '{' . $body . '}',
            'uses' => array_values(array_unique([...$uses, ...$bodyUses])),
        ];
    }

    private function functionBodyOpen(int $start, int $end): ?int
    {
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
                continue;
            }
            if ($text === ')') {
                $parenDepth--;
                continue;
            }
            if ($text === '[') {
                $bracketDepth++;
                continue;
            }
            if ($text === ']') {
                $bracketDepth--;
                continue;
            }
            if ($text === '{') {
                if ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
                    return $i;
                }
                $braceDepth++;
                continue;
            }
            if ($text === '}') {
                $braceDepth--;
            }
        }

        return null;
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @return array{0:string, 1:list<string>}
     */
    private function printNamespaceFunctionBody(
        int $start,
        int $bodyClose,
        bool $isAsync,
        string $namespace,
        array $imports,
        array $exportedValues
    ): array {
        $parts = [];
        $uses = [];
        $hasUsingHelperScope = false;
        $hasAwaitUsing = false;
        for ($cursor = $start; $cursor < $bodyClose;) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                $cursor++;
                continue;
            }

            $statementEnd = $this->declarationStatementEnd($cursor, $bodyClose)
                ?? $this->findStatementEndOrLineBreak($cursor, $bodyClose);
            $effectiveEnd = $statementEnd;
            if (($this->tokens[$effectiveEnd] ?? null)?->text === ';') {
                $effectiveEnd--;
            }
            if ($effectiveEnd < $cursor) {
                $cursor = $statementEnd + 1;
                continue;
            }

            if ($this->isNamespaceUsingStatement($cursor)) {
                [$statement, $statementUses, $isAwaitUsing] = $this->printNamespaceFunctionUsingDeclaration(
                    $cursor,
                    $effectiveEnd,
                    $isAsync,
                    $namespace,
                    $imports,
                    $exportedValues
                );
                $hasUsingHelperScope = true;
                $hasAwaitUsing = $hasAwaitUsing || $isAwaitUsing;
            } else {
                $statementUses = [];
                $statement = $this->printTokenRange($cursor, $effectiveEnd, $namespace, $imports, $exportedValues, $statementUses);
                if ($statement !== '' && !str_ends_with($statement, ';') && !str_ends_with($statement, '}')) {
                    $statement .= ';';
                }
            }

            if ($statement !== '') {
                $parts[] = $statement;
                array_push($uses, ...$statementUses);
            }
            $cursor = $statementEnd + 1;
        }

        if ($hasUsingHelperScope) {
            return [$this->namespaceFunctionUsingHelperBody($parts, $hasAwaitUsing), array_values(array_unique($uses))];
        }

        return [implode('', $parts), array_values(array_unique($uses))];
    }

    /**
     * @param list<string> $statements
     */
    private function namespaceFunctionUsingHelperBody(array $statements, bool $hasAwaitUsing): string
    {
        $this->needsUsingHelperRuntime = true;

        $lines = [
            'var _stack = [];',
            'try {',
        ];
        foreach ($statements as $statement) {
            foreach (explode("\n", $statement) as $line) {
                if ($line === '') {
                    continue;
                }
                $lines[] = '  ' . $line;
            }
        }
        $lines[] = '} catch (_) {';
        $lines[] = '  var _error = _, _hasError = true;';
        $lines[] = '} finally {';
        if ($hasAwaitUsing) {
            $lines[] = '  var _promise = ' . $this->usingHelperCallDisposeName . '(_stack, _error, _hasError);';
            $lines[] = '  _promise && await _promise;';
        } else {
            $lines[] = '  ' . $this->usingHelperCallDisposeName . '(_stack, _error, _hasError);';
        }
        $lines[] = '}';

        return "\n  " . implode("\n  ", $lines) . "\n";
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @return array{0:string, 1:list<string>, 2:bool}
     */
    private function printNamespaceFunctionUsingDeclaration(
        int $start,
        int $end,
        bool $isAsyncFunction,
        string $namespace,
        array $imports,
        array $exportedValues
    ): array {
        $cursor = $start;
        $isAwaitUsing = false;
        if (($this->tokens[$cursor] ?? null)?->text === 'await') {
            if (!$isAsyncFunction) {
                throw new \InvalidArgumentException('Cannot use await using inside a non-async namespace function');
            }
            $isAwaitUsing = true;
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text !== 'using') {
            throw new \InvalidArgumentException('Expected TypeScript namespace function using declaration');
        }

        $cursor++;
        $declarators = [];
        $uses = [];
        while ($cursor <= $end) {
            $name = $this->tokens[$cursor] ?? null;
            if ($name?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected identifier in TypeScript namespace function using declaration');
            }
            if ($this->hasLineBreakBetween($cursor - 1, $cursor)) {
                throw new \InvalidArgumentException('Expected identifier in TypeScript namespace function using declaration');
            }

            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === ':') {
                $cursor = $this->skipNamespaceUsingTypeExpression($cursor + 1, $end);
            }
            if (($this->tokens[$cursor] ?? null)?->text !== '=') {
                throw new \InvalidArgumentException('The declaration "' . $name->text . '" must be initialized');
            }

            $valueStart = $cursor + 1;
            if ($valueStart > $end) {
                throw new \InvalidArgumentException('Expected initializer after TypeScript namespace function using declaration');
            }

            $cursor = $this->namespaceUsingInitializerEnd($valueStart, $end);
            $valueEnd = ($this->tokens[$cursor] ?? null)?->text === ',' ? $cursor - 1 : $end;
            if ($valueEnd < $valueStart) {
                throw new \InvalidArgumentException('Expected initializer after TypeScript namespace function using declaration');
            }

            $valueUses = [];
            $value = $this->printTokenRange($valueStart, $valueEnd, $namespace, $imports, $exportedValues, $valueUses);
            $declarators[] = $name->text . ' = ' . $this->usingHelperUsingName . '(_stack, ' . $value . ($isAwaitUsing ? ', true' : '') . ')';
            array_push($uses, ...$valueUses);

            if (($this->tokens[$cursor] ?? null)?->text !== ',') {
                break;
            }
            $cursor++;
        }

        return [
            'const ' . implode(', ', $declarators) . ';',
            array_values(array_unique($uses)),
            $isAwaitUsing,
        ];
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param array<string, true> $exportedValues
     * @param list<string> $uses
     */
    private function printTokenRange(int $start, int $end, string $namespace, array $imports, array $exportedValues = [], array &$uses = []): string
    {
        $parts = [];
        $previous = null;
        for ($i = $start; $i <= $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            $text = $token->text;
            if ($token->kind === 'string') {
                $text = '"' . addcslashes(stripcslashes(substr($token->text, 1, -1)), "\\\"") . '"';
            } elseif ($token->kind === 'identifier'
                && isset($imports[$token->text])
                && !$this->isPropertyAccessIdentifier($i)
            ) {
                $uses[] = $token->text;
                if ($imports[$token->text]['exported']) {
                    $text = $namespace . '.' . $token->text;
                }
            } elseif ($token->kind === 'identifier'
                && isset($exportedValues[$token->text])
                && !$this->isPropertyAccessIdentifier($i)
                && ($this->tokens[$i + 1] ?? null)?->text !== ':'
            ) {
                $text = $namespace . '.' . $token->text;
            }

            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
    }

    private function isPropertyAccessIdentifier(int $index): bool
    {
        if (($this->tokens[$index - 1] ?? null)?->text !== '.') {
            return false;
        }

        return !(($this->tokens[$index - 2] ?? null)?->text === '.'
            && ($this->tokens[$index - 3] ?? null)?->text === '.');
    }

    private function needsSpace(string $previous, string $current): bool
    {
        $assignmentOperators = ['=', '+=', '-=', '*=', '/=', '%='];
        if (in_array($previous, $assignmentOperators, true) || in_array($current, $assignmentOperators, true)) {
            return true;
        }
        if (in_array($current, [')', ']', '}', ',', ';', '.'], true)) {
            return false;
        }
        if (in_array($previous, ['(', '[', '{', '.', ''], true)) {
            return false;
        }
        if ($current === '(') {
            return false;
        }
        if ($previous === ',') {
            return true;
        }

        return $this->isWordLike($previous) && $this->isWordLike($current);
    }

    private function isWordLike(string $text): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_$"\']/', $text);
    }

    private function enumMemberName(Token $token): string
    {
        return $token->kind === 'string' ? $this->stringTokenValue($token) : $token->text;
    }

    private function stringTokenValue(Token $token): string
    {
        return stripcslashes(substr($token->text, 1, -1));
    }

    private function quoteJsString(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function formatEnumNumber(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
    }

    private function findStatementEndOrLineBreak(int $start, int $limit): int
    {
        $depth = 0;
        for ($i = $start; $i < $limit; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            } elseif ($text === ';' && $depth === 0) {
                return $i;
            }

            if ($depth === 0 && $this->hasLineBreakBetween($i, $i + 1)) {
                return $i;
            }
        }

        return $limit - 1;
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

        throw new \InvalidArgumentException('Unterminated TypeScript namespace block');
    }

    private function isNamespaceKeywordAt(int $index): bool
    {
        return ($this->tokens[$index] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$index]->text, ['namespace', 'module'], true)
            && ($this->tokens[$index + 1] ?? null)?->kind === 'identifier';
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function readNamespaceName(int $start): array
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
                throw new \InvalidArgumentException('Expected identifier after "." in TypeScript namespace name');
            }
            $cursor++;
        }

        return [implode('.', $parts), $cursor];
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
}
