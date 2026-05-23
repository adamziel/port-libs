<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class TypeScriptModuleLowerer
{
    /**
     * @var list<Token>
     */
    private array $tokens = [];
    private string $source = '';
    /**
     * @var array<string, array<string, array{value:string, comment:string, numericValue:?float}>>
     */
    private array $enumConstants = [];
    private bool $useDefineForClassFields = true;
    private bool $minifySyntax = false;
    private bool $lowerUsingDeclarations = false;
    private bool $lowerAsyncGenerators = false;
    private bool $lowerDecorators = false;
    private int $targetYear = 2022;
    private bool $hasLoweredUsingDeclarations = false;
    private bool $hasLoweredAwaitUsingDeclarations = false;
    private bool $needsUsingHelperRuntime = false;
    private bool $needsAsyncGeneratorHelperRuntime = false;
    private int $usingScopeCounter = 0;
    private int $asyncGeneratorForAwaitCounter = 0;
    private string $usingHelperKnownSymbolName = '__knownSymbol';
    private string $usingHelperTypeErrorName = '__typeError';
    private string $usingHelperUsingName = '__using';
    private string $usingHelperCallDisposeName = '__callDispose';
    private string $asyncGeneratorAwaitName = '__await';
    private string $asyncGeneratorName = '__asyncGenerator';
    private string $asyncGeneratorYieldStarName = '__yieldStar';
    private string $asyncGeneratorForAwaitName = '__forAwait';
    /**
     * @var array<string, true>
     */
    private array $generatedIdentifiers = [];

    public function lower(
        string $source,
        bool $useDefineForClassFields = true,
        bool $minifySyntax = false,
        bool $lowerUsingDeclarations = false,
        bool $lowerAsyncGenerators = false,
        int $targetYear = 2022,
        bool $lowerDecorators = false
    ): string
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($source);
        $this->enumConstants = $this->collectEnumConstants();
        $this->useDefineForClassFields = $useDefineForClassFields;
        $this->minifySyntax = $minifySyntax;
        $this->lowerUsingDeclarations = $lowerUsingDeclarations;
        $this->lowerAsyncGenerators = $lowerAsyncGenerators;
        $this->lowerDecorators = $lowerDecorators;
        $this->targetYear = $targetYear;
        $this->hasLoweredUsingDeclarations = false;
        $this->hasLoweredAwaitUsingDeclarations = false;
        $this->needsUsingHelperRuntime = false;
        $this->needsAsyncGeneratorHelperRuntime = false;
        $this->usingScopeCounter = 0;
        $this->asyncGeneratorForAwaitCounter = 0;
        $this->generatedIdentifiers = $this->sourceIdentifierMap();
        $this->configureHelperNames();
        $this->validateSwitchCaseUsingDeclarations();
        $this->validateDecoratorBoundaries();

        $lines = [];
        $exportAssignments = [];
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text === ';') {
                continue;
            }

            $first = $this->tokens[$i] ?? null;
            if ($first === null) {
                continue;
            }

            $exportAsNamespaceEnd = $this->exportAsNamespaceStatementEnd($i);
            if ($exportAsNamespaceEnd !== null) {
                $i = $exportAsNamespaceEnd;
                continue;
            }

            $ambientEnd = $this->ambientStatementEnd($i);
            if ($ambientEnd !== null) {
                $i = $ambientEnd;
                continue;
            }

            $enumStatement = $this->enumStatementAt($i);
            if ($enumStatement !== null) {
                [$exported, $declared, $const, $enumIndex] = $enumStatement;
                $end = $this->enumStatementEnd($enumIndex);
                if (!$declared && (!$const || $exported)) {
                    $lines[] = $this->lowerEnumStatement($enumIndex, $end, $exported);
                }
                $i = $end;
                continue;
            }

            $classStatement = $this->lowerClassStatementAt($i);
            if ($classStatement !== null) {
                [$classOutput, $end] = $classStatement;
                if ($classOutput !== '') {
                    $lines[] = $classOutput;
                }
                $i = $end;
                continue;
            }

            $end = $this->findStatementEndForLowering($i);
            $effectiveEnd = $this->withoutTrailingSemicolon($end);
            $this->validateForUsingStatement($i, $effectiveEnd);

            $forUsingStatement = $this->lowerForUsingStatementAt($i, $effectiveEnd);
            if ($forUsingStatement !== null) {
                [$forOutput, $forEnd] = $forUsingStatement;
                $lines[] = $forOutput;
                $i = $forEnd;
                continue;
            }

            $asyncGeneratorFunction = $this->lowerAsyncGeneratorFunctionStatementAt($i, $effectiveEnd);
            if ($asyncGeneratorFunction !== null) {
                [$functionOutput, $functionEnd] = $asyncGeneratorFunction;
                $lines[] = $functionOutput;
                $i = $functionEnd;
                continue;
            }

            $functionUsingStatement = $this->lowerFunctionBodyUsingStatementAt($i, $effectiveEnd);
            if ($functionUsingStatement !== null) {
                [$functionOutput, $functionEnd] = $functionUsingStatement;
                $lines[] = $functionOutput;
                $i = $functionEnd;
                continue;
            }

            $switchCaseUsingStatement = $this->lowerSwitchCaseBlockUsingStatementAt($i, $effectiveEnd);
            if ($switchCaseUsingStatement !== null) {
                [$switchOutput, $switchEnd] = $switchCaseUsingStatement;
                $lines[] = $switchOutput;
                $i = $switchEnd;
                continue;
            }

            $blockUsingStatement = $this->lowerBlockScopedUsingStatementAt($i, $effectiveEnd);
            if ($blockUsingStatement !== null) {
                [$blockOutput, $blockEnd] = $blockUsingStatement;
                $lines[] = $blockOutput;
                $i = $blockEnd;
                continue;
            }

            $objectMethodUsingStatement = $this->lowerObjectLiteralMethodUsingStatementAt($i, $effectiveEnd);
            if ($objectMethodUsingStatement !== null) {
                $lines[] = $objectMethodUsingStatement;
                $i = $end;
                continue;
            }

            $classExpressionStatement = $this->lowerClassExpressionStatementAt($i, $effectiveEnd);
            if ($classExpressionStatement !== null) {
                [$classExpressionOutput, $classExpressionEnd] = $classExpressionStatement;
                $lines[] = $classExpressionOutput;
                $i = $classExpressionEnd;
                continue;
            }

            if ($first->text === 'export' && ($this->tokens[$i + 1] ?? null)?->text === '=') {
                if ($i + 2 > $effectiveEnd) {
                    throw new \InvalidArgumentException('Expected expression after TypeScript export equals');
                }
                $exportAssignments[] = 'module.exports = ' . $this->printTokenRange($i + 2, $effectiveEnd) . ';';
                $i = $end;
                continue;
            }

            if ($first->text === 'export' && ($this->tokens[$i + 1] ?? null)?->text === 'using') {
                throw new \InvalidArgumentException('Unexpected "using"');
            }

            if ($first->text === 'export'
                && ($this->tokens[$i + 1] ?? null)?->text === 'await'
                && ($this->tokens[$i + 2] ?? null)?->text === 'using'
            ) {
                throw new \InvalidArgumentException('Unexpected "await"');
            }

            if ($first->text === 'export' && ($this->tokens[$i + 1] ?? null)?->text === 'import') {
                [$local, $target, $cursor] = $this->parseImportEqualsStatement($i + 1, $effectiveEnd);
                $this->assertStatementConsumed($cursor, $effectiveEnd);
                $lines[] = 'export const ' . $local . ' = ' . $target . ';';
                $i = $end;
                continue;
            }

            if ($first->text === 'import' && ($this->tokens[$i + 1] ?? null)?->text === 'type') {
                $i = $end;
                continue;
            }

            if ($first->text === 'import'
                && ($this->tokens[$i + 1] ?? null)?->kind === 'identifier'
                && ($this->tokens[$i + 2] ?? null)?->text === '='
            ) {
                [$local, $target, $cursor] = $this->parseImportEqualsStatement($i, $effectiveEnd);
                $this->assertStatementConsumed($cursor, $effectiveEnd);
                $lines[] = 'const ' . $local . ' = ' . $target . ';';
                $i = $end;
                continue;
            }

            if ($this->isErasableTypeScriptStatement($i)) {
                $i = $end;
                continue;
            }

            $usingDeclaration = $this->isUsingDeclarationStart($i);
            if ($usingDeclaration) {
                $using = $this->parseUsingDeclaration($i, $effectiveEnd);
                $statement = $this->printUsingDeclaration($using);
                if ($statement !== '') {
                    $lines[] = $statement;
                }
                $i = $end;
                continue;
            }

            $statement = $usingDeclaration || $this->containsErasableTypeScriptSyntax($i, $effectiveEnd) || $this->containsInlineableEnumReference($i, $effectiveEnd)
                ? $this->printRuntimeStatement($i, $effectiveEnd)
                : $this->originalStatementText($i, $end);
            if ($statement !== '') {
                $lines[] = $statement;
            }
            $i = $end;
        }

        foreach ($exportAssignments as $assignment) {
            $lines[] = $assignment;
        }

        if ($this->hasLoweredUsingDeclarations) {
            return $this->wrapUsingHelperStatements($lines);
        }

        $output = $lines === [] ? '' : implode("\n", $lines) . "\n";

        return $this->helperRuntime() . $output;
    }

    /**
     * @return array{0:string, 1:string, 2:int}
     */
    private function parseImportEqualsStatement(int $importIndex, int $effectiveEnd): array
    {
        $local = $this->tokens[$importIndex + 1] ?? null;
        if ($local?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after TypeScript import');
        }
        if (($this->tokens[$importIndex + 2] ?? null)?->text !== '=') {
            throw new \InvalidArgumentException('Expected "=" after TypeScript import name');
        }

        $targetStart = $importIndex + 3;
        if ($targetStart > $effectiveEnd) {
            throw new \InvalidArgumentException('Expected TypeScript import equals target');
        }

        $cursor = $this->importEqualsTargetEnd($targetStart);
        $target = $this->printTokenRange($targetStart, $cursor - 1);

        return [$local->text, $target, $cursor];
    }

    /**
     * @return array{0:bool, 1:bool, 2:bool, 3:int}|null
     */
    private function enumStatementAt(int $start): ?array
    {
        $cursor = $start;
        $exported = false;
        $declared = false;
        $const = false;

        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            $exported = true;
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                return null;
            }
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'declare') {
            $declared = true;
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                return null;
            }
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

        return [$exported, $declared, $const, $cursor];
    }

    private function exportAsNamespaceStatementEnd(int $start): ?int
    {
        if (($this->tokens[$start] ?? null)?->text !== 'export'
            || ($this->tokens[$start + 1] ?? null)?->text !== 'as'
            || ($this->tokens[$start + 2] ?? null)?->text !== 'namespace'
        ) {
            return null;
        }

        $name = $this->tokens[$start + 3] ?? null;
        if ($name?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after TypeScript export as namespace');
        }

        $afterName = $this->tokens[$start + 4] ?? null;
        if ($afterName?->text === '.') {
            throw new \InvalidArgumentException('Expected ";" after TypeScript export as namespace');
        }
        if ($afterName !== null && $afterName->text !== ';' && !$this->hasLineBreakBetween($start + 3, $start + 4)) {
            throw new \InvalidArgumentException('Expected ";" after TypeScript export as namespace');
        }

        return $afterName?->text === ';' ? $start + 4 : $start + 3;
    }

    private function ambientStatementEnd(int $start): ?int
    {
        $cursor = $this->skipLeadingDecorators($start);
        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)
                || ($this->tokens[$cursor + 1] ?? null)?->text !== 'declare'
            ) {
                return null;
            }
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text !== 'declare'
            || $this->hasLineBreakBetween($cursor, $cursor + 1)
        ) {
            return null;
        }

        $cursor++;

        $next = $this->tokens[$cursor] ?? null;
        if ($next === null) {
            throw new \InvalidArgumentException('Unexpected end after TypeScript declare');
        }

        if (in_array($next->text, ['var', 'let', 'const'], true)) {
            if ($next->text === 'const' && ($this->tokens[$cursor + 1] ?? null)?->text === 'enum') {
                return $this->ambientBlockDeclarationEnd($cursor + 1);
            }

            return $this->findStatementEndOrLineBreak($start);
        }

        if ($next->text === 'abstract') {
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)
                || ($this->tokens[$cursor + 1] ?? null)?->text !== 'class'
            ) {
                throw new \InvalidArgumentException('Unexpected TypeScript declare statement');
            }
            $cursor++;
            $next = $this->tokens[$cursor] ?? null;
        }

        if (in_array($next->text, ['function', 'class', 'interface', 'enum'], true)) {
            return $this->ambientDeclarationEnd($cursor);
        }

        if (in_array($next->text, ['namespace', 'module', 'global'], true)) {
            return $this->ambientNamespaceLikeDeclarationEnd($cursor);
        }

        throw new \InvalidArgumentException('Unexpected TypeScript declare statement');
    }

    private function skipLeadingDecorators(int $start): int
    {
        $cursor = $start;
        while (($this->tokens[$cursor] ?? null)?->text === '@') {
            $cursor = $this->decoratorEnd($cursor) + 1;
        }

        return $cursor;
    }

    private function decoratorEnd(int $atIndex): int
    {
        $depth = 0;
        $count = count($this->tokens);
        for ($i = $atIndex + 1; $i < $count; $i++) {
            $text = $this->tokens[$i]->text;
            if ($i === $atIndex + 1) {
                $first = $this->tokens[$i];
                if (($first->kind !== 'identifier' && $first->text !== '(')
                    || in_array($first->text, ['new', 'function', 'class'], true)
                ) {
                    throw new \InvalidArgumentException('Expected identifier after JavaScript decorator');
                }
            }
            if ($depth === 0 && ($text === '?.' || ($text === '?' && ($this->tokens[$i + 1] ?? null)?->text === '.'))) {
                throw new \InvalidArgumentException('JavaScript decorator syntax does not allow "?." here');
            }
            if ($depth === 0 && $text === '[') {
                throw new \InvalidArgumentException('JavaScript decorator syntax does not allow computed property access here');
            }
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            }

            if ($depth === 0) {
                if ($text === '<' && ($this->tokens[$i - 1] ?? null)?->kind === 'identifier') {
                    $typeArgumentsEnd = $this->typeParameterListEnd($i, $count);
                    if ($typeArgumentsEnd > $i) {
                        $i = $typeArgumentsEnd;
                    }
                }

                $next = $this->tokens[$i + 1] ?? null;
                if ($text === ')' && $next?->text === '.') {
                    throw new \InvalidArgumentException('JavaScript decorator syntax does not allow "." after a call expression');
                }
                if ($next !== null && in_array($next->text, ['@', 'declare', 'export', 'abstract', 'class'], true)) {
                    return $i;
                }
                if (!in_array($text, ['.', '?.'], true) && $next !== null && $this->canStartDecoratedClassMember($next)) {
                    return $i;
                }
                if ($this->hasLineBreakBetween($i, $i + 1)) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unterminated TypeScript decorator');
    }

    private function validateDecoratorBoundaries(): void
    {
        $classBodies = $this->classBodyRanges();
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== '@') {
                continue;
            }

            $classBody = $this->enclosingClassBodyRange($i, $classBodies);
            $targetClass = $this->decoratorListClassTargetIndex($i);

            if ($classBody !== null && $targetClass === null) {
                if ($this->isParameterDecorator($i, $classBody)) {
                    throw new \InvalidArgumentException('Parameter decorators are not allowed in JavaScript');
                }

                continue;
            }

            if ($targetClass === null) {
                throw new \InvalidArgumentException('Decorators are not valid here');
            }

            $i = max($i, $targetClass - 1);
        }
    }

    /**
     * @return list<array{open:int, close:int}>
     */
    private function classBodyRanges(): array
    {
        $ranges = [];
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== 'class') {
                continue;
            }

            $bodyOpen = $this->classExpressionBodyOpen($i, $count - 1);
            if ($bodyOpen === null) {
                continue;
            }

            $ranges[] = [
                'open' => $bodyOpen,
                'close' => $this->findMatchingPunctuator($bodyOpen, '{', '}'),
            ];
        }

        return $ranges;
    }

    /**
     * @param list<array{open:int, close:int}> $ranges
     * @return array{open:int, close:int}|null
     */
    private function enclosingClassBodyRange(int $index, array $ranges): ?array
    {
        $best = null;
        $bestSize = PHP_INT_MAX;
        foreach ($ranges as $range) {
            if ($index <= $range['open'] || $index >= $range['close']) {
                continue;
            }

            $size = $range['close'] - $range['open'];
            if ($size < $bestSize) {
                $best = $range;
                $bestSize = $size;
            }
        }

        return $best;
    }

    private function rangeContainsToken(string $text, int $start, int $end): bool
    {
        for ($i = $start; $i <= $end; $i++) {
            if (($this->tokens[$i] ?? null)?->text === $text) {
                return true;
            }
        }

        return false;
    }

    private function decoratorListClassTargetIndex(int $start): ?int
    {
        $cursor = $start;
        while (($this->tokens[$cursor] ?? null)?->text === '@') {
            $cursor = $this->decoratorEnd($cursor) + 1;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === 'default') {
                $cursor++;
            }
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'declare') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text === 'abstract') {
            $cursor++;
        }

        return ($this->tokens[$cursor] ?? null)?->text === 'class' ? $cursor : null;
    }

    /**
     * @param array{open:int, close:int} $classBody
     */
    private function isParameterDecorator(int $atIndex, array $classBody): bool
    {
        $depth = 0;
        $open = null;
        for ($i = $classBody['open'] + 1; $i < $atIndex; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $depth++;
                if ($depth === 1) {
                    $open = $i;
                }
                continue;
            }
            if ($text === ')') {
                $depth--;
                if ($depth === 0) {
                    $open = null;
                }
            }
        }

        if ($open === null || $depth <= 0) {
            return false;
        }

        $close = $this->findMatchingPunctuator($open, '(', ')');
        $next = $this->nextSignificantTokenAfterMethodParameters($close, $classBody['close']);

        return $next !== null && ($this->tokens[$next] ?? null)?->text === '{';
    }

    private function nextSignificantTokenAfterMethodParameters(int $close, int $classBodyClose): ?int
    {
        $depth = 0;
        for ($i = $close + 1; $i < $classBodyClose; $i++) {
            $text = $this->tokens[$i]->text;
            if ($depth === 0 && in_array($text, ['{', ';', '='], true)) {
                return $i;
            }
            if (in_array($text, ['(', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', ']'], true)) {
                $depth--;
            }
        }

        return null;
    }

    private function canStartDecoratedClassMember(Token $token): bool
    {
        if ($token->kind === 'private_identifier') {
            return true;
        }

        if ($token->text === '*' || $token->text === '[') {
            return true;
        }

        return $token->kind === 'identifier';
    }

    private function ambientDeclarationEnd(int $keywordIndex): int
    {
        $block = $this->findTopLevelBlockOpenBeforeStatementEnd($keywordIndex);

        return $block === null
            ? $this->findStatementEndOrLineBreak($keywordIndex)
            : $this->findMatchingPunctuator($block, '{', '}');
    }

    private function ambientBlockDeclarationEnd(int $keywordIndex): int
    {
        $block = $this->findTopLevelBlockOpenBeforeStatementEnd($keywordIndex);
        if ($block === null) {
            throw new \InvalidArgumentException('Expected TypeScript ambient declaration block');
        }

        return $this->findMatchingPunctuator($block, '{', '}');
    }

    private function ambientNamespaceLikeDeclarationEnd(int $keywordIndex): int
    {
        $keyword = $this->tokens[$keywordIndex] ?? null;
        if ($keyword === null) {
            throw new \InvalidArgumentException('Expected TypeScript ambient declaration');
        }

        if ($keyword->text === 'global') {
            if (($this->tokens[$keywordIndex + 1] ?? null)?->text !== '{') {
                throw new \InvalidArgumentException('Expected TypeScript declare global block');
            }
            $end = $this->findMatchingPunctuator($keywordIndex + 1, '{', '}');
            $this->validateExportAsNamespaceInRange($keywordIndex + 2, $end);

            return $end;
        }

        $name = $this->tokens[$keywordIndex + 1] ?? null;
        if ($name === null || ($name->kind !== 'identifier' && $name->kind !== 'string')) {
            throw new \InvalidArgumentException('Expected TypeScript ambient module or namespace name');
        }

        $cursor = $keywordIndex + 2;
        if ($name->kind === 'identifier') {
            while (($this->tokens[$cursor] ?? null)?->text === '.'
                && ($this->tokens[$cursor + 1] ?? null)?->kind === 'identifier'
            ) {
                $cursor += 2;
            }
        }

        if (($this->tokens[$cursor] ?? null)?->text === '{') {
            $end = $this->findMatchingPunctuator($cursor, '{', '}');
            $this->validateExportAsNamespaceInRange($cursor + 1, $end);

            return $end;
        }

        return $this->findStatementEndOrLineBreak($keywordIndex);
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerClassStatementAt(int $start): ?array
    {
        $cursor = $start;
        $hasTypeScriptClassSyntax = false;
        $isExportDefaultClass = false;
        [$decoratorTexts, $decoratorSkipRanges, $cursor] = $this->classStatementDecorators($cursor);
        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                return null;
            }
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === 'default') {
                if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                    return null;
                }
                $isExportDefaultClass = true;
                $cursor++;
            }
            [$afterExportDecorators, $afterExportSkipRanges, $cursor] = $this->classStatementDecorators($cursor);
            if ($decoratorTexts !== [] && $afterExportDecorators !== []) {
                throw new \InvalidArgumentException('Decorators are not valid here');
            }
            array_push($decoratorTexts, ...$afterExportDecorators);
            $decoratorSkipRanges += $afterExportSkipRanges;
        }

        if (($this->tokens[$cursor] ?? null)?->text === 'abstract') {
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                return null;
            }
            $hasTypeScriptClassSyntax = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text !== 'class') {
            return null;
        }

        $bodyOpen = $this->findTopLevelBlockOpenBeforeStatementEnd($cursor);
        if ($bodyOpen === null) {
            return null;
        }
        if ($this->classHeaderContainsTypeScriptSyntax($cursor, $bodyOpen)) {
            $hasTypeScriptClassSyntax = true;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        $hasExtends = $this->classHeaderHasExtends($cursor, $bodyOpen);
        [$members, $hasTypeScriptMemberSyntax, $fieldKeyTemps, $fieldKeyPrelude, $afterClassStaticAssignments, $memberDecorators] = $this->lowerClassMembers(
            $bodyOpen + 1,
            $bodyClose,
            $hasExtends,
        );
        $hasClassDecorators = $decoratorTexts !== [];
        if (!$hasTypeScriptClassSyntax && !$hasTypeScriptMemberSyntax && !($this->lowerDecorators && ($hasClassDecorators || $memberDecorators !== []))) {
            return null;
        }

        $fieldKeyExtendsPrelude = [];
        $extendsTemp = null;
        if ($this->useDefineForClassFields === false && $fieldKeyPrelude !== [] && $hasExtends) {
            $extendsTemp = $this->allocateClassFieldTemp($fieldKeyTemps);
            $fieldKeyExtendsPrelude = $fieldKeyPrelude;
            $fieldKeyPrelude = [];
        }

        $exportDefaultWithAfterClassAssignments = false;
        $defaultClassName = null;
        $afterClassStaticAssignmentClassName = null;
        if ($afterClassStaticAssignments !== []) {
            $className = $this->classDeclarationName($cursor, $bodyOpen);
            if ($isExportDefaultClass) {
                $exportDefaultWithAfterClassAssignments = true;
                if ($className === null) {
                    $className = $this->allocateDefaultExportName();
                    $defaultClassName = $className;
                }
            }
            if ($className === null) {
                throw new \InvalidArgumentException('Cannot lower static class fields for an anonymous class');
            }
            $afterClassStaticAssignmentClassName = $className;
        }

        $memberDecoratorInitName = null;
        $memberDecoratorClassName = null;
        $memberDecoratorBaseName = null;
        $initializeMemberDecoratorArraysBeforeClass = true;
        if ($this->lowerDecorators && $memberDecorators !== []) {
            if ($afterClassStaticAssignments !== []) {
                throw new \InvalidArgumentException('Decorator lowering for members with static field lowering is not supported yet');
            }
            if ($hasExtends && ($fieldKeyExtendsPrelude !== [] || $fieldKeyTemps !== [])) {
                throw new \InvalidArgumentException('Decorator lowering for derived members with computed class field keys is not supported yet');
            }
            $memberDecoratorClassName = $this->classDeclarationName($cursor, $bodyOpen);
            if ($memberDecoratorClassName === null && $isExportDefaultClass) {
                $memberDecoratorClassName = $defaultClassName ?? $this->allocateDefaultExportName();
                $defaultClassName ??= $memberDecoratorClassName;
            }
            if ($memberDecoratorClassName === null) {
                throw new \InvalidArgumentException('Decorator lowering for anonymous class members is not supported yet');
            }
            $needsInstanceInitializer = $this->memberDecoratorsNeedInstanceInitializer($memberDecorators);
            $memberDecoratorInitName = $this->allocateGeneratedIdentifier('_init');
            if ($needsInstanceInitializer) {
                $members = $this->injectMemberDecoratorInitializers(
                    $members,
                    $this->memberDecoratorInstanceInitializers($memberDecorators, $memberDecoratorInitName),
                    $hasExtends,
                );
            }
            if ($hasExtends) {
                $memberDecoratorBaseName = $this->allocateGeneratedIdentifier('_a');
                $extendsTemp = $memberDecoratorBaseName;
                $fieldKeyExtendsPrelude = $this->memberDecoratorArrayAssignmentStatements($memberDecorators);
                $initializeMemberDecoratorArraysBeforeClass = false;
            }
        }

        $decoratorTargetClassName = null;
        $decoratorContextName = null;
        $decoratorVariableBaseName = null;
        $memberDecoratorClassDecorator = null;
        if ($this->lowerDecorators && $hasClassDecorators) {
            $decoratorTargetClassName = $this->classDeclarationName($cursor, $bodyOpen);
            if ($decoratorTargetClassName === null && $isExportDefaultClass) {
                $decoratorTargetClassName = $defaultClassName ?? $this->allocateDefaultExportName();
                $defaultClassName ??= $decoratorTargetClassName;
                $decoratorContextName = '';
                $decoratorVariableBaseName = '_class_decorators';
            }
            if ($decoratorTargetClassName === null) {
                throw new \InvalidArgumentException('Decorator lowering for anonymous classes is not supported yet');
            }
            if ($memberDecorators !== []) {
                $memberDecoratorClassDecorator = [
                    'decoratorName' => $this->allocateGeneratedIdentifier($decoratorVariableBaseName ?? '_' . $decoratorTargetClassName . '_decorators'),
                    'decorators' => array_map(
                        fn (string $decoratorText): string => $this->decoratorExpression($decoratorText),
                        $decoratorTexts,
                    ),
                    'contextName' => $decoratorContextName ?? $decoratorTargetClassName,
                ];
            }
        }

        $header = $this->classHeaderText(
            $exportDefaultWithAfterClassAssignments ? $cursor : $start,
            $bodyOpen,
            $fieldKeyExtendsPrelude,
            $extendsTemp,
            $defaultClassName,
            $decoratorSkipRanges,
        );
        if ($decoratorTexts !== [] && !$this->lowerDecorators) {
            $header = implode(' ', $decoratorTexts) . ' ' . $header;
        }
        $lines = [$header . ' {'];
        foreach ($members as $member) {
            foreach (explode("\n", $member) as $line) {
                $lines[] = '  ' . $line;
            }
        }
        $lines[] = '}';

        $classOutput = implode("\n", $lines);
        if ($this->useDefineForClassFields === false && $fieldKeyTemps !== []) {
            $prefix = 'var ' . implode(', ', $fieldKeyTemps) . ';';
            if ($fieldKeyPrelude !== []) {
                $prefix .= "\n" . implode("\n", $fieldKeyPrelude);
            }
            $classOutput = $prefix . "\n" . $classOutput;
        }

        if ($afterClassStaticAssignments !== []) {
            if ($afterClassStaticAssignmentClassName === null) {
                throw new \LogicException('Expected a class name for static field assignments');
            }
            $classOutput .= "\n" . implode("\n", $this->staticFieldAssignmentStatements(
                $afterClassStaticAssignmentClassName,
                $afterClassStaticAssignments,
            ));
            if ($exportDefaultWithAfterClassAssignments) {
                $classOutput .= "\n" . $this->exportDefaultClauseStatement($afterClassStaticAssignmentClassName);
            }
        }

        if ($memberDecorators !== []) {
            if ($memberDecoratorInitName === null || $memberDecoratorClassName === null) {
                throw new \LogicException('Expected class member decorator lowering metadata');
            }
            $classOutput = $this->lowerClassMemberDecoratorStatements(
                $classOutput,
                $memberDecoratorClassName,
                $memberDecorators,
                $memberDecoratorInitName,
                $memberDecoratorBaseName ?? 'null',
                $initializeMemberDecoratorArraysBeforeClass,
                $memberDecoratorBaseName === null ? [] : [$memberDecoratorBaseName],
                $memberDecoratorClassDecorator,
            );
        }

        if ($this->lowerDecorators && $hasClassDecorators && $memberDecorators === []) {
            if ($decoratorTargetClassName === null) {
                throw new \LogicException('Expected a class name for decorator lowering');
            }
            $classOutput = $this->lowerClassDecoratorStatement(
                $classOutput,
                $decoratorTargetClassName,
                $decoratorTexts,
                $decoratorContextName,
                $decoratorVariableBaseName,
            );
        }

        return [$classOutput, $bodyClose];
    }

    /**
     * @return array{0:list<string>, 1:array<int, int>, 2:int}
     */
    private function classStatementDecorators(int $start): array
    {
        $texts = [];
        $skipRanges = [];
        $cursor = $start;
        while (($this->tokens[$cursor] ?? null)?->text === '@') {
            $end = $this->decoratorEnd($cursor);
            $texts[] = rtrim($this->printClassMemberRuntimeRange($cursor, $end), ';');
            $skipRanges[$cursor] = $end;
            $cursor = $end + 1;
        }

        return [$texts, $skipRanges, $cursor];
    }

    /**
     * @param list<string> $decoratorTexts
     */
    private function lowerClassDecoratorStatement(
        string $classOutput,
        string $className,
        array $decoratorTexts,
        ?string $decoratorContextName = null,
        ?string $decoratorVariableBaseName = null,
    ): string
    {
        $decoratorName = $this->allocateGeneratedIdentifier($decoratorVariableBaseName ?? '_' . $className . '_decorators');
        $initName = $this->allocateGeneratedIdentifier('_init');
        $decoratorContextName ??= $className;
        $decorators = array_map(
            fn (string $decoratorText): string => $this->decoratorExpression($decoratorText),
            $decoratorTexts,
        );

        return 'var ' . $decoratorName . ', ' . $initName . ";\n"
            . $decoratorName . ' = [' . implode(', ', $decorators) . "];\n"
            . $classOutput . "\n"
            . $initName . " = __decoratorStart(null);\n"
            . $className . ' = __decorateElement(' . $initName . ', 0, '
            . $this->quoteJsString($decoratorContextName) . ', ' . $decoratorName . ', ' . $className . ");\n"
            . '__runInitializers(' . $initName . ', 1, ' . $className . ');';
    }

    /**
     * @param list<array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, extraDeclarations?:list<string>, beforeDecorateStatements?:list<string>, afterDecorateStatements?:list<string>, decorateExtraArgument?:string, decorateTarget?:string, memberNameExpression?:string, memberNameTemp?:string, memberNameInitializer?:string, decoratorArrayInitializedInMemberKey?:bool, decorateResultAssignment?:string, decorateResultTemp?:string, decorateResultGet?:string, decorateResultSet?:string, instanceInitializers?:list<string>}> $memberDecorators
     * @param array{decoratorName:string, decorators:list<string>, contextName:string}|null $classDecorator
     */
    private function lowerClassMemberDecoratorStatements(
        string $classOutput,
        string $className,
        array $memberDecorators,
        string $initName,
        string $decoratorStartBase = 'null',
        bool $initializeDecoratorArraysBeforeClass = true,
        array $extraDeclarations = [],
        ?array $classDecorator = null,
    ): string {
        $decoratorExtraDeclarations = [];
        foreach ($memberDecorators as $decorator) {
            foreach (($decorator['extraDeclarations'] ?? []) as $declaration) {
                $decoratorExtraDeclarations[] = $declaration;
            }
        }
        $declarations = array_merge(
            array_map(static fn (array $decorator): string => $decorator['decoratorName'], $memberDecorators),
            $classDecorator === null ? [] : [$classDecorator['decoratorName']],
            array_values(array_filter(array_map(static fn (array $decorator): ?string => $decorator['memberNameTemp'] ?? null, $memberDecorators))),
            $extraDeclarations,
            [$initName],
            $decoratorExtraDeclarations,
        );
        $prefix = ['var ' . implode(', ', $declarations) . ';'];
        if ($classDecorator !== null) {
            $prefix[] = $classDecorator['decoratorName'] . ' = [' . implode(', ', $classDecorator['decorators']) . '];';
        }
        if ($initializeDecoratorArraysBeforeClass) {
            array_push($prefix, ...$this->memberDecoratorArrayAssignmentStatements($memberDecorators));
        }

        $suffix = [$initName . ' = __decoratorStart(' . $decoratorStartBase . ');'];
        foreach ($memberDecorators as $decorator) {
            foreach (($decorator['beforeDecorateStatements'] ?? []) as $statement) {
                $suffix[] = strtr($statement, [
                    '%%CLASS%%' => $className,
                    '_INIT_' => $initName,
                ]);
            }
            $extraArgument = isset($decorator['decorateExtraArgument'])
                ? ', ' . strtr($decorator['decorateExtraArgument'], [
                    '%%CLASS%%' => $className,
                    '_INIT_' => $initName,
                ])
                : '';
            $decorateTarget = strtr($decorator['decorateTarget'] ?? $className, [
                '%%CLASS%%' => $className,
                '_INIT_' => $initName,
            ]);
            $decorateCall = '__decorateElement(' . $initName . ', ' . $decorator['flags'] . ', '
                . $this->memberDecoratorNameExpression($decorator) . ', '
                . $decorator['decoratorName'] . ', ' . $decorateTarget . $extraArgument . ')';
            if (isset($decorator['decorateResultAssignment'])) {
                $suffix[] = $decorator['decorateResultAssignment'] . ' = ' . $decorateCall . ';';
            } elseif (isset($decorator['decorateResultTemp'], $decorator['decorateResultGet'], $decorator['decorateResultSet'])) {
                $suffix[] = $decorator['decorateResultTemp'] . ' = ' . $decorateCall
                    . ', ' . $decorator['decorateResultGet'] . ' = ' . $decorator['decorateResultTemp'] . '.get'
                    . ', ' . $decorator['decorateResultSet'] . ' = ' . $decorator['decorateResultTemp'] . '.set;';
            } else {
                $suffix[] = $decorateCall . ';';
            }
            foreach (($decorator['afterDecorateStatements'] ?? []) as $statement) {
                $suffix[] = strtr($statement, [
                    '%%CLASS%%' => $className,
                    '_INIT_' => $initName,
                ]);
            }
        }
        if ($classDecorator === null) {
            $suffix[] = '__decoratorMetadata(' . $initName . ', ' . $className . ');';
        } else {
            $suffix[] = $className . ' = __decorateElement(' . $initName . ', 0, '
                . $this->quoteJsString($classDecorator['contextName']) . ', '
                . $classDecorator['decoratorName'] . ', ' . $className . ');';
        }
        if ($this->memberDecoratorsNeedStaticInitializer($memberDecorators)) {
            $suffix[] = '__runInitializers(' . $initName . ', 3, ' . $className . ');';
        }
        foreach ($memberDecorators as $decorator) {
            if (($decorator['staticInitializer'] ?? null) !== null) {
                $suffix[] = strtr($decorator['staticInitializer'], [
                    '%%CLASS%%' => $className,
                    '_INIT_' => $initName,
                ]);
            }
        }
        if ($classDecorator !== null) {
            $suffix[] = '__runInitializers(' . $initName . ', 1, ' . $className . ');';
        }

        return implode("\n", $prefix) . "\n" . $classOutput . "\n" . implode("\n", $suffix);
    }

    /**
     * @param list<array{decoratorName:string, decorators:list<string>, memberNameTemp?:string, memberNameInitializer?:string, decoratorArrayInitializedInMemberKey?:bool}> $memberDecorators
     * @return list<string>
     */
    private function memberDecoratorArrayAssignmentStatements(array $memberDecorators): array
    {
        return array_values(array_filter(array_map(
            static function (array $decorator): ?string {
                if (($decorator['decoratorArrayInitializedInMemberKey'] ?? false) === true) {
                    return null;
                }

                $arrayAssignment = $decorator['decoratorName'] . ' = [' . implode(', ', $decorator['decorators']) . ']';
                if (isset($decorator['memberNameTemp'], $decorator['memberNameInitializer'])) {
                    return $decorator['memberNameTemp'] . ' = (' . $arrayAssignment . ', ' . $decorator['memberNameInitializer'] . ');';
                }

                return $arrayAssignment . ';';
            },
            $memberDecorators,
        ), static fn (?string $statement): bool => $statement !== null));
    }

    /**
     * @param array{memberName:string, memberNameExpression?:string} $decorator
     */
    private function memberDecoratorNameExpression(array $decorator): string
    {
        return $decorator['memberNameExpression'] ?? $this->quoteJsString($decorator['memberName']);
    }

    /**
     * @param list<array{needsInstanceInitializer:bool}> $memberDecorators
     */
    private function memberDecoratorsNeedInstanceInitializer(array $memberDecorators): bool
    {
        foreach ($memberDecorators as $decorator) {
            if ($decorator['needsInstanceInitializer']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{needsInstanceInitializer:bool, instanceInitializer:?string, instanceInitializers?:list<string>}> $memberDecorators
     * @return list<string>
     */
    private function memberDecoratorInstanceInitializers(array $memberDecorators, string $initName): array
    {
        $initializers = [];
        $defaultInitializerAdded = false;
        foreach ($memberDecorators as $decorator) {
            if (!$decorator['needsInstanceInitializer']) {
                continue;
            }

            if (isset($decorator['instanceInitializers'])) {
                foreach ($decorator['instanceInitializers'] as $initializer) {
                    $initializers[] = str_replace('_INIT_', $initName, $initializer);
                }
                continue;
            }

            $initializer = $decorator['instanceInitializer'] ?? null;
            if ($initializer === null) {
                if ($defaultInitializerAdded) {
                    continue;
                }

                $initializer = '__runInitializers(' . $initName . ', 5, this);';
                $defaultInitializerAdded = true;
            }
            $initializers[] = str_replace('_INIT_', $initName, $initializer);
        }

        return $initializers;
    }

    /**
     * @param list<string> $members
     * @param list<string> $initializers
     * @return list<string>
     */
    private function injectMemberDecoratorInitializers(array $members, array $initializers, bool $hasExtends): array
    {
        foreach ($members as $index => $member) {
            if (!str_starts_with($member, 'constructor(')) {
                continue;
            }

            [$header, $body] = $this->constructorMemberParts($member);
            if ($hasExtends) {
                $body = $this->injectParameterPropertyAssignmentsIntoBody($body, $initializers, true);
            } else {
                array_splice($body, $this->memberDecoratorInitializerInsertionIndex($body, $members, $index), 0, $initializers);
            }

            $lines = [$header];
            foreach ($body as $line) {
                $lines[] = '  ' . $line;
            }
            $lines[] = '}';
            $members[$index] = implode("\n", $lines);

            if ($index > 0 && $this->allPreviousMembersArePrivateFieldDeclarations($members, $index)) {
                $constructor = array_splice($members, $index, 1);
                array_splice($members, 0, 0, $constructor);
            }

            return $members;
        }

        array_unshift($members, $this->syntheticConstructorForAssignments($initializers, $hasExtends));

        return $members;
    }

    /**
     * @param list<string> $body
     * @param list<string> $members
     */
    private function memberDecoratorInitializerInsertionIndex(array $body, array $members, int $constructorIndex): int
    {
        $propertyNames = $this->constructorParameterPropertyFieldNamesAfter($members, $constructorIndex);
        if ($propertyNames === []) {
            return 0;
        }

        $propertyNameSet = array_fill_keys($propertyNames, true);
        $insertAt = 0;
        foreach ($body as $line) {
            if (preg_match('/^this\.([$_\pL][$_\pL\pN]*) = \1;$/u', $line, $match) !== 1) {
                break;
            }
            if (!isset($propertyNameSet[$match[1]])) {
                break;
            }

            $insertAt++;
        }

        return $insertAt;
    }

    /**
     * @param list<string> $members
     * @return list<string>
     */
    private function constructorParameterPropertyFieldNamesAfter(array $members, int $constructorIndex): array
    {
        $names = [];
        for ($i = $constructorIndex + 1, $count = count($members); $i < $count; $i++) {
            if (preg_match('/^([$_\pL][$_\pL\pN]*);$/u', $members[$i], $match) !== 1) {
                break;
            }

            $names[] = $match[1];
        }

        return $names;
    }

    /**
     * @param list<array{needsStaticInitializer:bool}> $memberDecorators
     */
    private function memberDecoratorsNeedStaticInitializer(array $memberDecorators): bool
    {
        foreach ($memberDecorators as $decorator) {
            if ($decorator['needsStaticInitializer']) {
                return true;
            }
        }

        return false;
    }

    private function decoratorExpression(string $decoratorText): string
    {
        $decoratorText = trim($decoratorText);
        if (!str_starts_with($decoratorText, '@')) {
            throw new \InvalidArgumentException('Expected JavaScript decorator expression');
        }

        return trim(substr($decoratorText, 1));
    }

    private function isExportDefaultClassStatement(int $start): bool
    {
        return ($this->tokens[$start] ?? null)?->text === 'export'
            && ($this->tokens[$start + 1] ?? null)?->text === 'default'
            && !$this->hasLineBreakBetween($start, $start + 1);
    }

    private function classHeaderContainsTypeScriptSyntax(int $classIndex, int $bodyOpen): bool
    {
        for ($i = $classIndex + 1; $i < $bodyOpen; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }
            if ($token->text === '<' || $token->text === 'implements') {
                return true;
            }
        }

        return false;
    }

    private function classHeaderHasExtends(int $classIndex, int $bodyOpen): bool
    {
        for ($i = $classIndex + 1; $i < $bodyOpen; $i++) {
            if (($this->tokens[$i] ?? null)?->text === 'extends') {
                return true;
            }
        }

        return false;
    }

    private function classDeclarationName(int $classIndex, int $bodyOpen): ?string
    {
        $name = $this->tokens[$classIndex + 1] ?? null;
        if ($name === null || $name->kind !== 'identifier' || $name->text === 'extends') {
            return null;
        }

        return $name->text;
    }

    /**
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<string>, 5:list<array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, extraDeclarations?:list<string>, beforeDecorateStatements?:list<string>, decorateExtraArgument?:string}>}
     */
    private function lowerClassMembers(int $start, int $end, bool $hasExtends, ?array &$fieldKeyTemps = null): array
    {
        $fieldKeyTemps ??= [];
        $members = [];
        $instanceAssignments = [];
        $staticAssignments = [];
        $afterClassStaticAssignments = [];
        $fieldKeyPrelude = [];
        $pendingFieldKeyEffects = [];
        $lastComputedMethod = null;
        $hasTypeScriptMemberSyntax = false;
        $memberDecorators = [];
        for ($cursor = $start; $cursor < $end; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            $memberEnd = $this->classMemberEnd($cursor, $end);
            [$loweredMembers, $transformed, $memberInstanceAssignments, $memberStaticAssignments, $fieldKeyEffects, $memberDecorator] = $this->lowerClassMember(
                $cursor,
                $memberEnd,
                $fieldKeyTemps,
                $hasExtends,
            );
            if ($memberDecorator !== null) {
                $memberDecorators[] = $memberDecorator;
            }
            $computedMethod = $this->useDefineForClassFields === false
                ? $this->computedClassMethodKey($cursor, $memberEnd)
                : null;
            if ($transformed) {
                $hasTypeScriptMemberSyntax = true;
            }
            foreach ($memberInstanceAssignments as $assignment) {
                $instanceAssignments[] = $assignment;
            }
            foreach ($memberStaticAssignments as $assignment) {
                $staticAssignments[] = $assignment;
            }

            $hasOutputMember = false;
            foreach ($loweredMembers as $member) {
                if ($member !== '') {
                    $hasOutputMember = true;
                    break;
                }
            }
            if ($hasOutputMember && $staticAssignments !== []) {
                if ($this->shouldLowerStaticFieldAssignmentsOutsideClass()) {
                    array_push($afterClassStaticAssignments, ...$staticAssignments);
                } else {
                    $members[] = $this->staticFieldAssignmentBlock($staticAssignments);
                }
                $staticAssignments = [];
            }

            if ($computedMethod !== null && $loweredMembers !== [] && $loweredMembers[0] !== '') {
                $prefixExpressions = $this->fieldKeyEffectSequenceExpressions($pendingFieldKeyEffects);
                $replacement = $prefixExpressions === []
                    ? $computedMethod['expression']
                    : '(' . implode(', ', array_merge($prefixExpressions, [$computedMethod['expression']])) . ')';
                $memberIndex = count($members);
                $members[] = $this->printClassMemberRuntimeRangeWithComputedKeyReplacement(
                    $cursor,
                    $memberEnd,
                    $computedMethod['open'],
                    $computedMethod['close'],
                    $replacement
                );
                $lastComputedMethod = [
                    'memberIndex' => $memberIndex,
                    'start' => $cursor,
                    'end' => $memberEnd,
                    'open' => $computedMethod['open'],
                    'close' => $computedMethod['close'],
                    'prefixEffects' => $pendingFieldKeyEffects,
                    'expression' => $computedMethod['expression'],
                ];
                $pendingFieldKeyEffects = [];
                $hasTypeScriptMemberSyntax = true;
            } else {
                foreach ($loweredMembers as $member) {
                    if ($member !== '') {
                        $members[] = $member;
                    }
                }
            }

            foreach ($fieldKeyEffects as $effect) {
                $pendingFieldKeyEffects[] = $effect;
            }
            $cursor = $memberEnd;
        }

        if ($this->useDefineForClassFields === false && $pendingFieldKeyEffects !== []) {
            if ($lastComputedMethod !== null) {
                $this->appendFieldKeyEffectsToComputedMethod($members, $lastComputedMethod, $pendingFieldKeyEffects, $fieldKeyTemps);
            } else {
                $this->appendFieldKeyEffectsToPrelude($fieldKeyPrelude, $pendingFieldKeyEffects, $fieldKeyTemps);
            }
            $hasTypeScriptMemberSyntax = true;
        }

        if ($this->useDefineForClassFields === false && $instanceAssignments !== []) {
            $members = $this->injectInstanceFieldAssignments($members, $instanceAssignments, $hasExtends);
        }

        if ($this->useDefineForClassFields === false && $staticAssignments !== []) {
            if ($this->shouldLowerStaticFieldAssignmentsOutsideClass()) {
                array_push($afterClassStaticAssignments, ...$staticAssignments);
            } else {
                $members[] = $this->staticFieldAssignmentBlock($staticAssignments);
            }
        }

        return [$members, $hasTypeScriptMemberSyntax, $fieldKeyTemps, $fieldKeyPrelude, $afterClassStaticAssignments, $memberDecorators];
    }

    /**
     * @param list<string> $fieldKeyTemps
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>, 5:array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, extraDeclarations?:list<string>, beforeDecorateStatements?:list<string>, decorateExtraArgument?:string}|null}
     */
    private function lowerClassMember(int $start, int $end, array &$fieldKeyTemps, bool $hasExtends): array
    {
        $cursor = $start;
        $decorated = false;
        $decoratorTexts = [];
        while (($this->tokens[$cursor] ?? null)?->text === '@') {
            $decorated = true;
            $decoratorEnd = $this->decoratorEnd($cursor);
            $decoratorTexts[] = rtrim($this->printClassMemberRuntimeRange($cursor, $decoratorEnd), ';');
            $cursor = $decoratorEnd + 1;
        }

        $memberStart = $cursor;
        $modifiers = [];
        while ($cursor <= $end
            && ($this->tokens[$cursor] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$cursor]->text, $this->classMemberModifierKeywords(), true)
        ) {
            $modifiers[] = $this->tokens[$cursor]->text;
            $cursor++;
        }
        $accessorModifierIndex = array_search('accessor', $modifiers, true);
        if ($accessorModifierIndex !== false && $accessorModifierIndex !== count($modifiers) - 1) {
            throw new \InvalidArgumentException('Expected ";" but found "' . (($this->tokens[$cursor] ?? null)?->text ?? '') . '"');
        }

        if ($this->lowerDecorators && $decorated) {
            $decoratedAccessor = $this->lowerDecoratedAccessorMember($memberStart, $end, $cursor, $modifiers, $decoratorTexts);
            if ($decoratedAccessor !== null) {
                return $decoratedAccessor;
            }
            $decoratedGetterSetter = $this->lowerDecoratedGetterSetterMember($memberStart, $end, $cursor, $modifiers, $decoratorTexts);
            if ($decoratedGetterSetter !== null) {
                return $decoratedGetterSetter;
            }
            $decoratedMethod = $this->lowerDecoratedMethodMember($memberStart, $end, $cursor, $modifiers, $decoratorTexts);
            if ($decoratedMethod !== null) {
                return $decoratedMethod;
            }
            $decoratedField = $this->lowerDecoratedFieldMember($memberStart, $end, $cursor, $modifiers, $decoratorTexts);
            if ($decoratedField !== null) {
                return $decoratedField;
            }

            throw new \InvalidArgumentException('Decorator lowering for class members is not supported yet');
        }

        if (!in_array('declare', $modifiers, true)) {
            if ($cursor > $end) {
                if ($accessorModifierIndex !== false) {
                    return [[$this->printClassMemberRange($start, $end)], true, [], [], [], null];
                }

                return [[$this->printClassMemberRange($start, $end)], false, [], [], [], null];
            }

            if ($accessorModifierIndex !== false) {
                $autoAccessor = $this->lowerAutoAccessorMember($start, $end, $cursor, $modifiers);
                if ($autoAccessor !== null) {
                    return [...$autoAccessor, null];
                }
            }

            $asyncGeneratorMethod = $this->lowerAsyncGeneratorClassMethodMember($start, $end);
            if ($asyncGeneratorMethod !== null) {
                return [[$asyncGeneratorMethod], true, [], [], [], null];
            }

            $methodUsing = $this->lowerClassMethodUsingMember($start, $end);
            if ($methodUsing !== null) {
                return [[$methodUsing], true, [], [], [], null];
            }

            $constructor = $this->lowerConstructorParameterPropertyMember($start, $end, $cursor, $hasExtends);
            if ($constructor !== null) {
                return [$constructor[0], $constructor[1], [], [], [], null];
            }

            if ($this->useDefineForClassFields === false) {
                $assignSemanticsField = $this->lowerAssignSemanticsClassField($start, $end, $cursor, $modifiers, $fieldKeyTemps);
                if ($assignSemanticsField !== null) {
                    return [...$assignSemanticsField, null];
                }
            }

            if (in_array('abstract', $modifiers, true) && !$this->classMemberHasBody($cursor, $end)) {
                return [[], true, [], [], [], null];
            }

            if ($this->containsClassMemberTypeScriptSyntax($start, $end, $modifiers)) {
                return [[$this->printClassMemberRuntimeRange($start, $end)], true, [], [], [], null];
            }

            return [[$this->printClassMemberRange($start, $end)], false, [], [], [], null];
        }

        if ($decorated) {
            throw new \InvalidArgumentException('Decorators are not valid on TypeScript declare class fields');
        }

        $name = $this->tokens[$cursor] ?? null;
        if ($name === null) {
            throw new \InvalidArgumentException('Expected TypeScript declare class field name');
        }

        if ($name->kind === 'private_identifier') {
            throw new \InvalidArgumentException('"declare" cannot be used with a private identifier');
        }

        if ($name->text === '[') {
            throw new \InvalidArgumentException('"declare" cannot be used with an index signature');
        }

        if (in_array('get', $modifiers, true)) {
            throw new \InvalidArgumentException('"declare" cannot be used with a getter');
        }

        if (in_array('set', $modifiers, true)) {
            throw new \InvalidArgumentException('"declare" cannot be used with a setter');
        }

        if (($this->tokens[$cursor + 1] ?? null)?->text === '(') {
            throw new \InvalidArgumentException('"declare" cannot be used with a method');
        }

        return [[], true, [], [], [], null];
    }

    /**
     * @return array{text:string, end:int, expression:?string, temp:?string, decoratorBase:string}|null
     */
    private function decoratedPublicMemberName(int $memberNameIndex, int $effectiveEnd): ?array
    {
        $name = $this->tokens[$memberNameIndex] ?? null;
        if ($name === null) {
            return null;
        }

        if ($name->kind === 'identifier') {
            return [
                'text' => $name->text,
                'end' => $memberNameIndex,
                'expression' => null,
                'temp' => null,
                'decoratorBase' => '_' . $name->text . '_dec',
            ];
        }

        if ($name->text !== '[') {
            return null;
        }

        $nameEnd = $this->findMatchingPunctuator($memberNameIndex, '[', ']');
        if ($nameEnd > $effectiveEnd) {
            return null;
        }

        $expression = $this->printTokenRange($memberNameIndex + 1, $nameEnd - 1);
        if ($expression === '') {
            throw new \InvalidArgumentException('Expected TypeScript computed class field name');
        }

        $label = $this->computedDecoratorLabel($memberNameIndex + 1, $nameEnd - 1);

        return [
            'text' => $label,
            'end' => $nameEnd,
            'expression' => $expression,
            'temp' => $this->allocateGeneratedIdentifier('_a'),
            'decoratorBase' => '_' . $label . '_dec',
        ];
    }

    private function computedDecoratorLabel(int $start, int $end): string
    {
        $first = $this->tokens[$start] ?? null;
        if ($first?->kind === 'identifier') {
            return $first->text;
        }

        return 'computed';
    }

    /**
     * @return array{text:string, plain:string, end:int, storageName:string, decoratorBase:string}|null
     */
    private function decoratedPrivateMemberName(int $memberNameIndex): ?array
    {
        $name = $this->tokens[$memberNameIndex] ?? null;
        if ($name?->kind !== 'private_identifier') {
            return null;
        }

        $plain = substr($name->text, 1);
        if ($plain === '') {
            return null;
        }

        return [
            'text' => $name->text,
            'plain' => $plain,
            'end' => $memberNameIndex,
            'storageName' => $this->allocateGeneratedIdentifier('_' . $plain),
            'decoratorBase' => '_' . $plain . '_dec',
        ];
    }

    /**
     * @param list<string> $modifiers
     * @param list<string> $decoratorTexts
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>, 5:array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, extraDeclarations?:list<string>, beforeDecorateStatements?:list<string>, decorateExtraArgument?:string, decorateTarget?:string}}|null
     */
    private function lowerDecoratedAccessorMember(
        int $memberStart,
        int $end,
        int $memberNameIndex,
        array $modifiers,
        array $decoratorTexts
    ): ?array {
        if ($decoratorTexts === []
            || !in_array('accessor', $modifiers, true)
            || in_array('get', $modifiers, true)
            || in_array('set', $modifiers, true)
            || in_array('declare', $modifiers, true)
        ) {
            return null;
        }

        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        $name = $this->decoratedPublicMemberName($memberNameIndex, $effectiveEnd);
        $privateName = $name === null ? $this->decoratedPrivateMemberName($memberNameIndex) : null;
        if (($name === null && $privateName === null) || ($name !== null && $name['text'] === 'constructor')) {
            return null;
        }

        $cursor = ($name ?? $privateName)['end'] + 1;
        if (($this->tokens[$cursor] ?? null)?->text === '?' || ($this->tokens[$cursor] ?? null)?->text === '!') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $effectiveEnd, ['=', ';']);
        }

        $next = $cursor <= $effectiveEnd ? ($this->tokens[$cursor] ?? null) : null;
        if ($next?->text === '<' || $next?->text === '(') {
            return null;
        }
        if ($next !== null && $next->text !== '=') {
            throw new \InvalidArgumentException('Expected ";" but found "' . $next->text . '"');
        }

        $initializer = null;
        if ($next?->text === '=') {
            if ($cursor + 1 > $effectiveEnd) {
                throw new \InvalidArgumentException('Expected initializer after decorated auto accessor');
            }
            $initializer = $this->printTokenRange($cursor + 1, $effectiveEnd);
            if ($initializer === '') {
                throw new \InvalidArgumentException('Expected initializer after decorated auto accessor');
            }
        }

        $isStatic = in_array('static', $modifiers, true);
        $storageName = $privateName !== null
            ? $privateName['storageName']
            : $this->allocateGeneratedIdentifier($name['expression'] === null ? '_' . $name['text'] : '__a');
        $decorator = [
            'memberName' => $name['text'] ?? $privateName['text'],
            'decoratorName' => $this->allocateGeneratedIdentifier($name['decoratorBase'] ?? $privateName['decoratorBase']),
            'decorators' => array_map(fn (string $decorator): string => $this->decoratorExpression($decorator), $decoratorTexts),
            'flags' => $privateName === null ? ($isStatic ? 12 : 4) : ($isStatic ? 28 : 20),
            'needsInstanceInitializer' => !$isStatic,
            'needsStaticInitializer' => false,
            'instanceInitializer' => null,
            'staticInitializer' => null,
            'extraDeclarations' => [$storageName],
            'beforeDecorateStatements' => [$storageName . ' = new WeakMap();'],
            'decorateExtraArgument' => $storageName,
        ];
        if ($privateName !== null) {
            $brandName = $this->allocateGeneratedIdentifier($isStatic ? '_' . $privateName['plain'] . '_static' : '_' . $privateName['plain'] . '_instances');
            $resultName = $this->allocateGeneratedIdentifier('_a');
            $getterName = $this->allocateGeneratedIdentifier($privateName['plain'] . '_get');
            $setterName = $this->allocateGeneratedIdentifier($privateName['plain'] . '_set');
            $decorator['extraDeclarations'] = [$storageName, $resultName, $getterName, $setterName, $brandName];
            $decorator['beforeDecorateStatements'] = [
                $storageName . ' = new WeakMap();',
                $brandName . ' = new WeakSet();',
            ];
            $decorator['decorateTarget'] = $brandName;
            $decorator['decorateResultTemp'] = $resultName;
            $decorator['decorateResultGet'] = $getterName;
            $decorator['decorateResultSet'] = $setterName;
            if ($isStatic) {
                $decorator['afterDecorateStatements'] = ['__privateAdd(%%CLASS%%, ' . $brandName . ');'];
            } else {
                $decorator['instanceInitializers'] = [
                    '__privateAdd(this, ' . $brandName . ');',
                ];
            }
        }
        if ($name !== null && $name['expression'] !== null && $name['temp'] !== null) {
            $decorator['memberNameExpression'] = $name['temp'];
            $decorator['memberNameTemp'] = $name['temp'];
            $decorator['memberNameInitializer'] = $name['expression'];
        }

        $target = $isStatic ? '%%CLASS%%' : 'this';
        $initializerCall = '__runInitializers(_INIT_, 8, ' . $target
            . ($initializer === null ? '' : ', ' . $initializer)
            . ')';
        $accessorInitialization = '__privateAdd(' . $target . ', ' . $storageName . ', ' . $initializerCall . '), '
            . '__runInitializers(_INIT_, 11, ' . $target . ');';

        if ($isStatic) {
            $decorator['staticInitializer'] = $accessorInitialization;
        } else {
            if (isset($decorator['instanceInitializers'])) {
                $decorator['instanceInitializers'][] = $accessorInitialization;
            } else {
                $decorator['instanceInitializer'] = $accessorInitialization;
            }
        }

        return [[], true, [], [], [], $decorator];
    }

    /**
     * @param list<string> $modifiers
     * @param list<string> $decoratorTexts
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>, 5:array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, memberNameExpression?:string, memberNameTemp?:string, decoratorArrayInitializedInMemberKey?:bool}}|null
     */
    private function lowerDecoratedGetterSetterMember(
        int $memberStart,
        int $end,
        int $memberNameIndex,
        array $modifiers,
        array $decoratorTexts
    ): ?array {
        $isGetter = in_array('get', $modifiers, true);
        $isSetter = in_array('set', $modifiers, true);
        if ($decoratorTexts === []
            || in_array('accessor', $modifiers, true)
            || in_array('declare', $modifiers, true)
            || $isGetter === $isSetter
        ) {
            return null;
        }

        $isStatic = in_array('static', $modifiers, true);
        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        $name = $this->decoratedPublicMemberName($memberNameIndex, $effectiveEnd);
        $privateName = $name === null ? $this->decoratedPrivateMemberName($memberNameIndex) : null;
        if (($name === null && $privateName === null) || ($name !== null && $name['text'] === 'constructor')) {
            return null;
        }

        $cursor = ($name ?? $privateName)['end'] + 1;
        if (($this->tokens[$cursor] ?? null)?->text === '?' || ($this->tokens[$cursor] ?? null)?->text === '!') {
            throw new \InvalidArgumentException('Expected "(" but found "' . $this->tokens[$cursor]->text . '"');
        }
        if (($this->tokens[$cursor] ?? null)?->text !== '(') {
            return null;
        }

        $paramsClose = $this->findMatchingPunctuator($cursor, '(', ')');
        if ($paramsClose > $effectiveEnd) {
            return null;
        }

        $afterParams = $paramsClose + 1;
        if (($this->tokens[$afterParams] ?? null)?->text === ':') {
            $afterParams = $this->skipTypeExpression($afterParams + 1, $effectiveEnd, ['{']);
        }
        if (($this->tokens[$afterParams] ?? null)?->text !== '{') {
            return null;
        }
        $bodyOpen = $afterParams;
        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd) {
            return null;
        }

        $decorator = [
            'memberName' => $name['text'] ?? $privateName['text'],
            'decoratorName' => $this->allocateGeneratedIdentifier($name['decoratorBase'] ?? $privateName['decoratorBase']),
            'decorators' => array_map(fn (string $decorator): string => $this->decoratorExpression($decorator), $decoratorTexts),
            'flags' => $privateName === null
                ? ($isStatic ? ($isGetter ? 10 : 11) : ($isGetter ? 2 : 3))
                : 16 + ($isStatic ? 8 : 0) + ($isGetter ? 2 : 3),
            'needsInstanceInitializer' => !$isStatic,
            'needsStaticInitializer' => $isStatic,
            'instanceInitializer' => null,
            'staticInitializer' => null,
        ];

        if ($privateName !== null) {
            $brandName = $this->allocateGeneratedIdentifier($isStatic ? '_' . $privateName['plain'] . '_static' : '_' . $privateName['plain'] . '_instances');
            $accessorFunction = $this->allocateGeneratedIdentifier($privateName['plain'] . ($isGetter ? '_get' : '_set'));
            $params = $paramsClose > $cursor + 1
                ? $this->printParameterRuntimeRange($cursor + 1, $paramsClose - 1)
                : '';
            $body = $bodyClose > $bodyOpen + 1
                ? $this->printRuntimeTokenRange($bodyOpen + 1, $bodyClose - 1, $memberStart)
                : '';
            $decorator['extraDeclarations'] = [$brandName, $accessorFunction];
            $decorator['beforeDecorateStatements'] = [
                $brandName . ' = new WeakSet();',
                $accessorFunction . ' = function(' . $params . ') {' . $body . '};',
            ];
            $decorator['decorateTarget'] = $brandName;
            $decorator['decorateExtraArgument'] = $accessorFunction;
            $decorator['decorateResultAssignment'] = $accessorFunction;
            if ($isStatic) {
                $decorator['afterDecorateStatements'] = ['__privateAdd(%%CLASS%%, ' . $brandName . ');'];
            } else {
                $decorator['instanceInitializers'] = [
                    '__runInitializers(_INIT_, 5, this);',
                    '__privateAdd(this, ' . $brandName . ');',
                ];
            }

            return [
                [],
                true,
                [],
                [],
                [],
                $decorator,
            ];
        }

        $method = $this->printClassMemberRuntimeRange($memberStart, $end);
        if ($name !== null && $name['expression'] !== null && $name['temp'] !== null) {
            $decorator['memberNameExpression'] = $name['temp'];
            $decorator['memberNameTemp'] = $name['temp'];
            $decorator['decoratorArrayInitializedInMemberKey'] = true;
            $replacement = $name['temp'] . ' = ('
                . $decorator['decoratorName'] . ' = [' . implode(', ', $decorator['decorators']) . '], '
                . $name['expression'] . ')';
            $method = $this->printClassMemberRuntimeRangeWithComputedKeyReplacement(
                $memberStart,
                $end,
                $memberNameIndex,
                $name['end'],
                $replacement
            );
        }

        return [
            [$method],
            true,
            [],
            [],
            [],
            $decorator,
        ];
    }

    /**
     * @param list<string> $modifiers
     * @param list<string> $decoratorTexts
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>, 5:array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, extraDeclarations?:list<string>, beforeDecorateStatements?:list<string>, decorateExtraArgument?:string, memberNameExpression?:string, memberNameTemp?:string, memberNameInitializer?:string, decoratorArrayInitializedInMemberKey?:bool}}|null
     */
    private function lowerDecoratedMethodMember(
        int $memberStart,
        int $end,
        int $memberNameIndex,
        array $modifiers,
        array $decoratorTexts
    ): ?array {
        if ($decoratorTexts === []
            || in_array('accessor', $modifiers, true)
            || in_array('get', $modifiers, true)
            || in_array('set', $modifiers, true)
        ) {
            return null;
        }

        $isStatic = in_array('static', $modifiers, true);
        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        $nameCandidate = $this->tokens[$memberNameIndex] ?? null;
        if ($nameCandidate?->kind === 'identifier') {
            $nameEnd = $memberNameIndex;
        } elseif ($nameCandidate?->text === '[') {
            $nameEnd = $this->findMatchingPunctuator($memberNameIndex, '[', ']');
            if ($nameEnd > $effectiveEnd) {
                return null;
            }
        } else {
            return null;
        }

        $cursor = $nameEnd + 1;
        if (($this->tokens[$cursor] ?? null)?->text === '?') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text === '<') {
            $typeParametersEnd = $this->typeParameterListEnd($cursor, $effectiveEnd);
            if ($typeParametersEnd <= $cursor) {
                return null;
            }
            $cursor = $typeParametersEnd + 1;
        }
        if (($this->tokens[$cursor] ?? null)?->text !== '(') {
            return null;
        }

        $name = $this->decoratedPublicMemberName($memberNameIndex, $effectiveEnd);
        if ($name === null || $name['text'] === 'constructor') {
            return null;
        }

        $bodyOpen = $this->classMethodBodyOpen($memberStart, $end);
        if ($bodyOpen === null) {
            return null;
        }

        $decorator = [
            'memberName' => $name['text'],
            'decoratorName' => $this->allocateGeneratedIdentifier($name['decoratorBase']),
            'decorators' => array_map(fn (string $decorator): string => $this->decoratorExpression($decorator), $decoratorTexts),
            'flags' => $isStatic ? 9 : 1,
            'needsInstanceInitializer' => !$isStatic,
            'needsStaticInitializer' => $isStatic,
            'instanceInitializer' => null,
            'staticInitializer' => null,
        ];

        $method = $this->printClassMemberRuntimeRange($memberStart, $end);
        if ($name['expression'] !== null && $name['temp'] !== null) {
            $decorator['memberNameExpression'] = $name['temp'];
            $decorator['memberNameTemp'] = $name['temp'];
            $decorator['decoratorArrayInitializedInMemberKey'] = true;
            $replacement = $name['temp'] . ' = ('
                . $decorator['decoratorName'] . ' = [' . implode(', ', $decorator['decorators']) . '], '
                . $name['expression'] . ')';
            $method = $this->printClassMemberRuntimeRangeWithComputedKeyReplacement(
                $memberStart,
                $end,
                $memberNameIndex,
                $name['end'],
                $replacement
            );
        }

        return [
            [$method],
            true,
            [],
            [],
            [],
            $decorator,
        ];
    }

    /**
     * @param list<string> $modifiers
     * @param list<string> $decoratorTexts
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>, 5:array{memberName:string, decoratorName:string, decorators:list<string>, flags:int, needsInstanceInitializer:bool, needsStaticInitializer:bool, instanceInitializer:?string, staticInitializer:?string, extraDeclarations?:list<string>, beforeDecorateStatements?:list<string>, decorateExtraArgument?:string}}|null
     */
    private function lowerDecoratedFieldMember(
        int $memberStart,
        int $end,
        int $memberNameIndex,
        array $modifiers,
        array $decoratorTexts
    ): ?array {
        if ($decoratorTexts === []
            || in_array('accessor', $modifiers, true)
            || in_array('get', $modifiers, true)
            || in_array('set', $modifiers, true)
            || in_array('declare', $modifiers, true)
        ) {
            return null;
        }

        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        $name = $this->decoratedPublicMemberName($memberNameIndex, $effectiveEnd);
        $privateName = $name === null ? $this->decoratedPrivateMemberName($memberNameIndex) : null;
        if (($name === null && $privateName === null) || ($name !== null && $name['text'] === 'constructor')) {
            return null;
        }

        $cursor = ($name ?? $privateName)['end'] + 1;
        if (($this->tokens[$cursor] ?? null)?->text === '(') {
            return null;
        }
        if (($this->tokens[$cursor] ?? null)?->text === '<') {
            return null;
        }
        if (($this->tokens[$cursor] ?? null)?->text === '?' || ($this->tokens[$cursor] ?? null)?->text === '!') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $effectiveEnd, ['=', ';']);
        }

        $initializer = null;
        if ($cursor <= $effectiveEnd) {
            if (($this->tokens[$cursor] ?? null)?->text !== '=') {
                return null;
            }
            if ($cursor + 1 > $effectiveEnd) {
                throw new \InvalidArgumentException('Expected initializer after decorated class field');
            }
            $initializer = $this->printTokenRange($cursor + 1, $effectiveEnd);
            if ($initializer === '') {
                throw new \InvalidArgumentException('Expected initializer after decorated class field');
            }
        }

        $isStatic = in_array('static', $modifiers, true);
        $memberName = $name['text'] ?? $privateName['text'];
        $decorator = [
            'memberName' => $memberName,
            'decoratorName' => $this->allocateGeneratedIdentifier($name['decoratorBase'] ?? $privateName['decoratorBase']),
            'decorators' => array_map(fn (string $decorator): string => $this->decoratorExpression($decorator), $decoratorTexts),
            'flags' => $privateName === null ? ($isStatic ? 13 : 5) : ($isStatic ? 29 : 21),
            'needsInstanceInitializer' => !$isStatic,
            'needsStaticInitializer' => false,
            'instanceInitializer' => null,
            'staticInitializer' => null,
        ];
        if ($privateName !== null) {
            $decorator['extraDeclarations'] = [$privateName['storageName']];
            $decorator['beforeDecorateStatements'] = [$privateName['storageName'] . ' = new WeakMap();'];
            $decorator['decorateTarget'] = $privateName['storageName'];
        }
        if ($name !== null && $name['expression'] !== null && $name['temp'] !== null) {
            $decorator['memberNameExpression'] = $name['temp'];
            $decorator['memberNameTemp'] = $name['temp'];
            $decorator['memberNameInitializer'] = $name['expression'];
        }

        $initializerCall = '__runInitializers(_INIT_, 8, ' . ($isStatic ? '%%CLASS%%' : 'this')
            . ($initializer === null ? '' : ', ' . $initializer)
            . ')';
        if ($privateName !== null) {
            $fieldInitialization = '__privateAdd(' . ($isStatic ? '%%CLASS%%' : 'this') . ', '
                . $privateName['storageName'] . ', ' . $initializerCall . '), '
                . '__runInitializers(_INIT_, 11, ' . ($isStatic ? '%%CLASS%%' : 'this') . ');';
        } else {
            $fieldInitialization = '__publicField(' . ($isStatic ? '%%CLASS%%' : 'this') . ', '
                . ($name['expression'] === null ? $this->quoteJsString($name['text']) : $name['temp']) . ', ' . $initializerCall . '), '
                . '__runInitializers(_INIT_, 11, ' . ($isStatic ? '%%CLASS%%' : 'this') . ');';
        }

        if ($isStatic) {
            $decorator['staticInitializer'] = $fieldInitialization;
        } else {
            $decorator['instanceInitializer'] = $fieldInitialization;
        }

        return [[], true, [], [], [], $decorator];
    }

    /**
     * @param list<string> $modifiers
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>}|null
     */
    private function lowerAutoAccessorMember(int $start, int $end, int $memberNameIndex, array $modifiers): ?array
    {
        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        if ($effectiveEnd < $memberNameIndex) {
            return null;
        }

        $name = $this->tokens[$memberNameIndex] ?? null;
        if ($name === null) {
            return null;
        }

        if ($name->text === '[') {
            $nameEnd = $this->findMatchingPunctuator($memberNameIndex, '[', ']');
            if ($nameEnd > $effectiveEnd) {
                return null;
            }
        } elseif ($name->kind === 'identifier' || $name->kind === 'private_identifier' || $name->kind === 'string') {
            $nameEnd = $memberNameIndex;
        } else {
            return null;
        }

        $cursor = $nameEnd + 1;
        $transformed = true;
        if (($this->tokens[$cursor] ?? null)?->text === '?' || ($this->tokens[$cursor] ?? null)?->text === '!') {
            $transformed = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $transformed = true;
            $cursor = $this->skipTypeExpression($cursor + 1, $effectiveEnd, ['=', ';']);
        }

        $next = $cursor <= $effectiveEnd ? ($this->tokens[$cursor] ?? null) : null;
        if ($next?->text === '<') {
            throw new \InvalidArgumentException('Expected ";" but found "<"');
        }
        if ($next?->text === '(') {
            throw new \InvalidArgumentException('Expected ";" but found "("');
        }
        if ($next !== null && $next->text !== '=') {
            throw new \InvalidArgumentException('Expected ";" but found "' . $next->text . '"');
        }

        return $transformed
            ? [[$this->printClassMemberRuntimeRange($start, $end)], true, [], [], []]
            : null;
    }

    private function lowerClassMethodUsingMember(int $start, int $end): ?string
    {
        $bodyOpen = $this->classMethodBodyOpen($start, $end);
        if ($bodyOpen === null) {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $end) {
            return null;
        }

        [$bodyLines, $changed] = $this->lowerFunctionBodyUsingStatements(
            $bodyOpen + 1,
            $bodyClose,
            $this->isAsyncClassMethodHeader($start, $bodyOpen),
        );
        if (!$changed) {
            return null;
        }

        $header = $this->printClassMethodHeaderRuntimeRange($start, $bodyOpen - 1);
        $lines = [$header . ' {'];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function lowerAsyncGeneratorClassMethodMember(int $start, int $end): ?string
    {
        if (!$this->lowerAsyncGenerators) {
            return null;
        }

        $bodyOpen = $this->classMethodBodyOpen($start, $end);
        if ($bodyOpen === null || !$this->isAsyncGeneratorClassMethodHeader($start, $bodyOpen)) {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $end) {
            return null;
        }

        $header = $this->asyncGeneratorMethodRuntimeHeader($start, $bodyOpen - 1);

        return $this->printAsyncGeneratorFunctionLike($header, $bodyOpen + 1, $bodyClose);
    }

    private function classMethodBodyOpen(int $start, int $end): ?int
    {
        $parenDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                if ($parenDepth === 0
                    && $bracketDepth === 0
                    && $this->findTopLevelTokenInRange('=', $start, $i - 1) === null
                ) {
                    $paramsClose = $this->findMatchingPunctuator($i, '(', ')');
                    if ($paramsClose <= $end) {
                        $cursor = $paramsClose + 1;
                        if (($this->tokens[$cursor] ?? null)?->text === ':') {
                            $cursor = $this->skipTypeExpression($cursor + 1, $end, ['{']);
                        }
                        if (($this->tokens[$cursor] ?? null)?->text === '{') {
                            return $cursor;
                        }
                    }
                }
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
            }
        }

        return null;
    }

    private function isAsyncClassMethodHeader(int $start, int $bodyOpen): bool
    {
        for ($i = $start; $i < $bodyOpen; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null || $token->text !== 'async' || !$this->isTopLevelInRange($start, $i)) {
                continue;
            }

            $next = $this->tokens[$i + 1] ?? null;

            return $next !== null && $next->text !== '(';
        }

        return false;
    }

    private function isAsyncGeneratorClassMethodHeader(int $start, int $bodyOpen): bool
    {
        for ($i = $start; $i < $bodyOpen; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null || $token->text !== 'async' || !$this->isTopLevelInRange($start, $i)) {
                continue;
            }

            $star = $this->tokens[$i + 1] ?? null;

            return $star !== null && $star->text === '*';
        }

        return false;
    }

    private function printClassMethodHeaderRuntimeRange(int $start, int $end): string
    {
        $header = rtrim($this->printClassMemberRuntimeRange($start, $end), ';');

        return preg_replace('/\basync\*/', 'async *', $header) ?? $header;
    }

    private function asyncGeneratorMethodRuntimeHeader(int $start, int $end): string
    {
        $header = $this->printClassMethodHeaderRuntimeRange($start, $end);

        return preg_replace('/\basync\s*\*\s*/', '', $header, 1) ?? $header;
    }

    /**
     * @param list<string> $modifiers
     * @param list<string> $fieldKeyTemps
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>}|null
     */
    private function lowerAssignSemanticsClassField(
        int $start,
        int $end,
        int $memberNameIndex,
        array $modifiers,
        array &$fieldKeyTemps
    ): ?array
    {
        if (in_array('accessor', $modifiers, true)) {
            return null;
        }

        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        if ($effectiveEnd < $memberNameIndex) {
            return null;
        }

        $privateField = $this->lowerAssignSemanticsPrivateClassField($memberNameIndex, $effectiveEnd, $modifiers);
        if ($privateField !== null) {
            return $privateField;
        }

        $field = $this->classFieldTarget($memberNameIndex, $effectiveEnd);
        if ($field === null) {
            return null;
        }

        [$target, $nameEnd, $erasableIndexSignature, $computedExpression] = $field;
        if ($target === null && !$erasableIndexSignature && $computedExpression === null) {
            return null;
        }

        $cursor = $nameEnd + 1;
        if (($this->tokens[$cursor] ?? null)?->text === '?' || ($this->tokens[$cursor] ?? null)?->text === '!') {
            if ($this->tokens[$cursor]->text === '!' && $this->classMemberMarkerStartsMethod($cursor, $memberNameIndex, $effectiveEnd)) {
                throw new \InvalidArgumentException('Expected ";" but found "' . (($this->tokens[$cursor + 1] ?? null)?->text ?? '') . '"');
            }
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === '<') {
            $typeParametersEnd = $this->typeParameterListEnd($cursor, $effectiveEnd);
            if ($typeParametersEnd > $cursor && ($this->tokens[$typeParametersEnd + 1] ?? null)?->text === '(') {
                return null;
            }
            throw new \InvalidArgumentException('Expected "(" after TypeScript class method type parameters');
        }

        if (($this->tokens[$cursor] ?? null)?->text === '(') {
            return null;
        }

        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $effectiveEnd, ['=', ';']);
        }

        if ($erasableIndexSignature) {
            return [[], true, [], [], []];
        }

        if (($this->tokens[$cursor] ?? null)?->text !== '=') {
            $keyEffects = $computedExpression === null
                ? []
                : [['sequence' => $computedExpression, 'preludeExpression' => $computedExpression]];

            return [[], true, [], [], $keyEffects];
        }

        $keyEffects = [];
        if ($target === null && $computedExpression !== null) {
            $temp = $this->allocateClassFieldTemp($fieldKeyTemps);
            $target = 'this[' . $temp . ']';
            $keyEffects[] = ['sequence' => $temp . ' = ' . $computedExpression, 'preludeExpression' => null];
        }

        if ($target === null || $cursor + 1 > $effectiveEnd) {
            throw new \InvalidArgumentException('Expected initializer after TypeScript class field');
        }

        $value = $this->printTokenRange($cursor + 1, $effectiveEnd);
        if ($value === '') {
            throw new \InvalidArgumentException('Expected initializer after TypeScript class field');
        }

        $assignment = $target . ' = ' . $value . ';';

        return in_array('static', $modifiers, true)
            ? [[], true, [], [$assignment], $keyEffects]
            : [[], true, [$assignment], [], $keyEffects];
    }

    /**
     * @param list<string> $modifiers
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>}|null
     */
    private function lowerAssignSemanticsPrivateClassField(int $start, int $effectiveEnd, array $modifiers): ?array
    {
        $name = $this->tokens[$start] ?? null;
        if ($name?->kind !== 'private_identifier') {
            return null;
        }

        $cursor = $start + 1;
        if (($this->tokens[$cursor] ?? null)?->text === '?' || ($this->tokens[$cursor] ?? null)?->text === '!') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text === '(') {
            return null;
        }
        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $effectiveEnd, ['=', ';']);
        }

        if (in_array('static', $modifiers, true)) {
            $declaration = 'static ' . $name->text;
            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                if ($cursor + 1 > $effectiveEnd) {
                    throw new \InvalidArgumentException('Expected initializer after TypeScript private class field');
                }

                $value = $this->printTokenRange($cursor + 1, $effectiveEnd);
                if ($value === '') {
                    throw new \InvalidArgumentException('Expected initializer after TypeScript private class field');
                }

                return [[$declaration . ' = ' . $value . ';'], true, [], [], []];
            }

            return [[$declaration . ';'], $cursor !== $start + 1, [], [], []];
        }

        $declaration = $name->text . ';';

        if (($this->tokens[$cursor] ?? null)?->text !== '=') {
            return [[$declaration], $cursor !== $start + 1, [], [], []];
        }

        if ($cursor + 1 > $effectiveEnd) {
            throw new \InvalidArgumentException('Expected initializer after TypeScript private class field');
        }

        $value = $this->printTokenRange($cursor + 1, $effectiveEnd);
        if ($value === '') {
            throw new \InvalidArgumentException('Expected initializer after TypeScript private class field');
        }

        $assignment = 'this.' . $name->text . ' = ' . $value . ';';

        return in_array('static', $modifiers, true)
            ? [[$declaration], true, [], [$assignment], []]
            : [[$declaration], true, [$assignment], [], []];
    }

    /**
     * @return array{0:?string, 1:int, 2:bool, 3:?string}|null
     */
    private function classFieldTarget(int $start, int $end): ?array
    {
        $name = $this->tokens[$start] ?? null;
        if ($name === null) {
            return null;
        }

        if ($name->kind === 'private_identifier') {
            return [null, $start, false, null];
        }

        if ($name->kind === 'identifier') {
            $next = $this->tokens[$start + 1] ?? null;
            if ($next !== null
                && $next->kind === 'identifier'
                && !in_array($next->text, ['as', 'satisfies'], true)
            ) {
                return null;
            }

            return ['this.' . $name->text, $start, false, null];
        }

        if ($name->kind === 'string') {
            return ['this[' . $this->quoteJsString($this->stringTokenValue($name)) . ']', $start, false, null];
        }

        if ($name->text !== '[') {
            return null;
        }

        $close = $this->findMatchingPunctuator($start, '[', ']');
        if ($close > $end) {
            return null;
        }

        $colon = $this->findTopLevelTokenInRange(':', $start + 1, $close - 1);
        if ($colon !== null) {
            return [null, $close, true, null];
        }

        if ($close === $start + 2 && ($this->tokens[$start + 1] ?? null)?->kind === 'string') {
            return ['this[' . $this->quoteJsString($this->stringTokenValue($this->tokens[$start + 1])) . ']', $close, false, null];
        }

        $computed = $this->printTokenRange($start + 1, $close - 1);
        if ($computed === '') {
            throw new \InvalidArgumentException('Expected TypeScript computed class field name');
        }

        return [null, $close, false, $computed];
    }

    /**
     * @param list<string> $fieldKeyTemps
     */
    private function allocateClassFieldTemp(array &$fieldKeyTemps): string
    {
        $temp = $this->nextClassFieldTempName(count($fieldKeyTemps));
        $fieldKeyTemps[] = $temp;

        return $temp;
    }

    private function nextClassFieldTempName(int $index): string
    {
        $name = '';
        do {
            $name = chr(ord('a') + ($index % 26)) . $name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return '_' . $name;
    }

    /**
     * @param list<array{sequence:string, preludeExpression:?string}> $effects
     * @return list<string>
     */
    private function fieldKeyEffectSequenceExpressions(array $effects): array
    {
        return array_map(static fn (array $effect): string => $effect['sequence'], $effects);
    }

    /**
     * @param list<string> $members
     * @param array{memberIndex:int, start:int, end:int, open:int, close:int, prefixEffects:list<array{sequence:string, preludeExpression:?string}>, expression:string} $method
     * @param list<array{sequence:string, preludeExpression:?string}> $effects
     * @param list<string> $fieldKeyTemps
     */
    private function appendFieldKeyEffectsToComputedMethod(
        array &$members,
        array $method,
        array $effects,
        array &$fieldKeyTemps
    ): void {
        $keyTemp = $this->allocateClassFieldTemp($fieldKeyTemps);
        $expressions = array_merge(
            $this->fieldKeyEffectSequenceExpressions($method['prefixEffects']),
            [$keyTemp . ' = ' . $method['expression']],
            $this->fieldKeyEffectSequenceExpressions($effects),
            [$keyTemp],
        );

        $members[$method['memberIndex']] = $this->printClassMemberRuntimeRangeWithComputedKeyReplacement(
            $method['start'],
            $method['end'],
            $method['open'],
            $method['close'],
            '(' . implode(', ', $expressions) . ')'
        );
    }

    /**
     * @param list<string> $prelude
     * @param list<array{sequence:string, preludeExpression:?string}> $effects
     * @param list<string> $fieldKeyTemps
     */
    private function appendFieldKeyEffectsToPrelude(array &$prelude, array $effects, array &$fieldKeyTemps): void
    {
        foreach ($effects as $effect) {
            if ($effect['preludeExpression'] !== null) {
                $temp = $this->allocateClassFieldTemp($fieldKeyTemps);
                $prelude[] = $temp . ' = ' . $effect['preludeExpression'] . ';';
                continue;
            }

            $prelude[] = $effect['sequence'] . ';';
        }
    }

    /**
     * @return array{open:int, close:int, expression:string}|null
     */
    private function computedClassMethodKey(int $start, int $end): ?array
    {
        $cursor = $start;
        while (($this->tokens[$cursor] ?? null)?->text === '@') {
            $cursor = $this->decoratorEnd($cursor) + 1;
        }

        while ($cursor <= $end
            && ($this->tokens[$cursor] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$cursor]->text, $this->classMemberModifierKeywords(), true)
        ) {
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text === '*') {
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text !== '[') {
            return null;
        }

        $close = $this->findMatchingPunctuator($cursor, '[', ']');
        if ($close > $end) {
            return null;
        }

        $afterName = $close + 1;
        if (($this->tokens[$afterName] ?? null)?->text === '?') {
            $afterName++;
        }
        if (($this->tokens[$afterName] ?? null)?->text === '<') {
            $typeParametersEnd = $this->typeParameterListEnd($afterName, $end);
            if ($typeParametersEnd <= $afterName) {
                return null;
            }
            $afterName = $typeParametersEnd + 1;
        }

        if (($this->tokens[$afterName] ?? null)?->text !== '(') {
            return null;
        }

        return [
            'open' => $cursor,
            'close' => $close,
            'expression' => $this->printTokenRange($cursor + 1, $close - 1),
        ];
    }

    private function printClassMemberRuntimeRangeWithComputedKeyReplacement(
        int $start,
        int $end,
        int $keyOpen,
        int $keyClose,
        string $replacement
    ): string {
        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        if ($effectiveEnd < $start) {
            return '';
        }

        $parts = [];
        $previous = null;
        for ($i = $start; $i <= $effectiveEnd; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            if ($i === $keyOpen) {
                $text = '[' . $replacement . ']';
                if ($previous !== null && (
                    $this->needsSpace($previous, $text)
                    || in_array($previous, ['accessor', 'static'], true)
                    || $this->startsAfterLeadingDecorator($i, $start, $effectiveEnd)
                )) {
                    $parts[] = ' ';
                }
                $parts[] = $text;
                $previous = ']';
                $i = $keyClose;
                continue;
            }

            if ($token->kind === 'identifier'
                && in_array($token->text, ['abstract', 'override', 'private', 'protected', 'public', 'readonly'], true)
                && $this->isTopLevelInRange($start, $i)
            ) {
                continue;
            }

            if ($token->text === '<' && (
                $this->isDecoratorTypeArgumentList($i, $start, $effectiveEnd)
                || $this->isClassMemberTypeParameterList($i, $start, $effectiveEnd)
            )) {
                $i = $this->typeParameterListEnd($i, $effectiveEnd);
                continue;
            }

            if (($token->text === '?' || $token->text === '!')
                && $this->isClassMemberOptionalOrDefiniteMarker($i, $start, $effectiveEnd)
            ) {
                if ($token->text === '!' && $this->classMemberMarkerStartsMethod($i, $start, $effectiveEnd)) {
                    throw new \InvalidArgumentException('Expected ";" but found "' . (($this->tokens[$i + 1] ?? null)?->text ?? '') . '"');
                }
                continue;
            }

            if ($token->text === ':' && $this->isClassMemberTypeColon($i, $start, $effectiveEnd)) {
                $i = $this->skipTypeExpression($i + 1, $effectiveEnd, ['=', '{', ';']) - 1;
                continue;
            }

            if ($token->text === ':' && $this->isClassMethodParameterTypeColon($i, $start, $effectiveEnd)) {
                $i = $this->skipTypeExpression($i + 1, $effectiveEnd, ['=', ',', ')']) - 1;
                continue;
            }

            $text = $token->text;
            if ($token->kind === 'string') {
                $text = $this->quoteJsString($this->stringTokenValue($token));
            }

            if ($previous !== null && (
                $this->needsSpace($previous, $text)
                || ($previous === 'static' && $text === '[')
                || $this->startsAfterLeadingDecorator($i, $start, $effectiveEnd)
            )) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        if ($parts === []) {
            return '';
        }

        $member = implode('', $parts);
        $last = $this->tokens[$effectiveEnd] ?? null;
        $hasTopLevelInitializer = $this->findTopLevelTokenInRange('=', $start, $effectiveEnd) !== null;
        if (($hasTopLevelInitializer || $last?->text !== '}') && !str_ends_with($member, ';')) {
            $member .= ';';
        }

        return $member;
    }

    /**
     * @param list<string> $members
     * @param list<string> $assignments
     * @return list<string>
     */
    private function injectInstanceFieldAssignments(array $members, array $assignments, bool $hasExtends): array
    {
        foreach ($members as $index => $member) {
            if (!str_starts_with($member, 'constructor(')) {
                continue;
            }

            $members[$index] = $this->injectAssignmentsIntoConstructor($member, $assignments, $hasExtends);
            if ($index > 0 && $this->allPreviousMembersArePrivateFieldDeclarations($members, $index)) {
                $constructor = array_splice($members, $index, 1);
                array_splice($members, 0, 0, $constructor);
            }

            return $members;
        }

        array_unshift($members, $this->syntheticConstructorForAssignments($assignments, $hasExtends));

        return $members;
    }

    /**
     * @param list<string> $members
     */
    private function allPreviousMembersArePrivateFieldDeclarations(array $members, int $beforeIndex): bool
    {
        for ($i = 0; $i < $beforeIndex; $i++) {
            if (preg_match('/^#[$_\pL][$_\pL\pN]*;$/u', $members[$i]) !== 1) {
                return false;
            }
        }

        return $beforeIndex > 0;
    }

    /**
     * @param list<string> $assignments
     */
    private function injectAssignmentsIntoConstructor(string $member, array $assignments, bool $hasExtends): string
    {
        [$header, $body] = $this->constructorMemberParts($member);
        $body = $this->injectParameterPropertyAssignmentsIntoBody($body, $assignments, $hasExtends);

        $lines = [$header];
        foreach ($body as $line) {
            $lines[] = '  ' . $line;
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @return array{0:string, 1:list<string>}
     */
    private function constructorMemberParts(string $member): array
    {
        $lines = explode("\n", $member);
        if (count($lines) > 1) {
            $header = array_shift($lines) ?? 'constructor() {';
            array_pop($lines);

            return [$header, array_map(static fn (string $line): string => preg_replace('/^  /', '', $line) ?? $line, $lines)];
        }

        if (preg_match('/^(constructor\s*\([^)]*\)\s*)\{(.*)\}$/s', $member, $match) !== 1) {
            return [$member, []];
        }

        return [trim($match[1]) . ' {', $this->constructorBodyTextLines($match[2])];
    }

    /**
     * @param list<string> $assignments
     */
    private function syntheticConstructorForAssignments(array $assignments, bool $hasExtends): string
    {
        $lines = ['constructor() {'];
        if ($hasExtends) {
            $lines[] = '  super(...arguments);';
        }
        foreach ($assignments as $assignment) {
            $lines[] = '  ' . $assignment;
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $assignments
     */
    private function staticFieldAssignmentBlock(array $assignments): string
    {
        $lines = ['static {'];
        foreach ($assignments as $assignment) {
            $lines[] = '  ' . $assignment;
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function shouldLowerStaticFieldAssignmentsOutsideClass(): bool
    {
        return $this->useDefineForClassFields === false && $this->targetYear < 2022;
    }

    /**
     * @param list<string> $assignments
     * @return list<string>
     */
    private function staticFieldAssignmentStatements(string $className, array $assignments): array
    {
        return array_map(
            static fn (string $assignment): string => preg_replace('/^this(?=\.|\[|#)/', $className, $assignment) ?? $assignment,
            $assignments,
        );
    }

    /**
     * @return array{0:list<string>, 1:bool}|null
     */
    private function lowerConstructorParameterPropertyMember(int $start, int $end, int $memberNameIndex, bool $hasExtends): ?array
    {
        if (($this->tokens[$memberNameIndex] ?? null)?->text !== 'constructor'
            || ($this->tokens[$memberNameIndex + 1] ?? null)?->text !== '('
        ) {
            return null;
        }

        $paramsOpen = $memberNameIndex + 1;
        $paramsClose = $this->findMatchingPunctuator($paramsOpen, '(', ')');
        if ($paramsClose > $end || ($this->tokens[$paramsClose + 1] ?? null)?->text !== '{') {
            return null;
        }

        $bodyOpen = $paramsClose + 1;
        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $end) {
            return null;
        }

        [$parameters, $propertyNames, $hasParameterProperties] = $this->lowerConstructorParameters($paramsOpen + 1, $paramsClose);
        if (!$hasParameterProperties) {
            return null;
        }

        $propertyAssignments = array_map(
            static fn (string $propertyName): string => 'this.' . $propertyName . ' = ' . $propertyName . ';',
            $propertyNames,
        );
        $body = $this->constructorBodyLines($bodyOpen, $bodyClose);
        $body = $this->injectParameterPropertyAssignmentsIntoBody($body, $propertyAssignments, $hasExtends);

        $lines = ['constructor(' . implode(', ', $parameters) . ') {'];
        foreach ($body as $line) {
            $lines[] = '  ' . $line;
        }
        $lines[] = '}';

        $members = [implode("\n", $lines)];
        if ($this->useDefineForClassFields) {
            foreach ($propertyNames as $propertyName) {
                $members[] = $propertyName . ';';
            }
        }

        return [$members, true];
    }

    /**
     * @param list<string> $body
     * @param list<string> $assignments
     * @return list<string>
     */
    private function injectParameterPropertyAssignmentsIntoBody(array $body, array $assignments, bool $hasExtends): array
    {
        if ($assignments === []) {
            return $body;
        }

        if (!$hasExtends) {
            array_splice($body, 0, 0, $assignments);

            return $body;
        }

        if ($this->constructorNeedsSuperHelper($body)) {
            $helper = ['var __super = (...args) => {', '  super(...args);'];
            foreach ($assignments as $assignment) {
                $helper[] = '  ' . $assignment;
            }
            $helper[] = '  return this;';
            $helper[] = '};';

            return array_merge($helper, array_map(
                static fn (string $line): string => preg_replace('/(^|[^\w$])super\s*\(/', '$1__super(', $line) ?? $line,
                $body,
            ));
        }

        $body = $this->rewriteDeadFalseSuperCalls($body);
        foreach ($body as $index => $line) {
            $controlSplit = $this->splitTopLevelCommaSuperControlStatement($line);
            if ($controlSplit !== null) {
                $replacement = [];
                if ($controlSplit['before'] !== null) {
                    $replacement[] = $controlSplit['before'];
                }
                $replacement[] = $controlSplit['super'];
                $indent = $controlSplit['indent'];
                foreach ($assignments as $assignment) {
                    $replacement[] = $indent . $assignment;
                }
                $replacement[] = $controlSplit['after'];
                array_splice($body, $index, 1, $replacement);

                return $body;
            }

            $statementSplit = $this->splitTopLevelCommaSuperConditionStatement($line)
                ?? $this->splitTopLevelCommaSuperForInitializerStatement($line);
            if ($statementSplit !== null) {
                $replacement = [];
                if ($statementSplit['before'] !== null) {
                    $replacement[] = $statementSplit['before'];
                }
                $replacement[] = $statementSplit['super'];
                $indent = $statementSplit['indent'];
                foreach ($assignments as $assignment) {
                    $replacement[] = $indent . $assignment;
                }
                $replacement[] = $statementSplit['after'];
                array_splice($body, $index, 1, $replacement);

                return $body;
            }

            $split = $this->splitTopLevelCommaSuperExpressionStatement($line);
            if ($split !== null) {
                $replacement = [];
                if ($split['before'] !== null) {
                    $replacement[] = $split['before'];
                }
                $replacement[] = $split['super'];
                $indent = $split['indent'];
                foreach ($assignments as $assignment) {
                    $replacement[] = $indent . $assignment;
                }
                if ($split['after'] !== null) {
                    $replacement[] = $split['after'];
                }
                array_splice($body, $index, 1, $replacement);

                return $body;
            }

            if (preg_match('/^(\s*)super\s*\(/', $line, $match) === 1) {
                $indent = $match[1] ?? '';
                $prefixedAssignments = array_map(static fn (string $assignment): string => $indent . $assignment, $assignments);
                array_splice($body, $index + 1, 0, $prefixedAssignments);

                return $body;
            }
        }

        array_splice($body, 0, 0, $assignments);

        return $body;
    }

    /**
     * @param list<string> $body
     */
    private function constructorNeedsSuperHelper(array $body): bool
    {
        $liveSuperCalls = 0;
        foreach ($body as $line) {
            $superCalls = preg_match_all('/(^|[^\w$])super\s*\(/', $line);
            if ($superCalls === 0 || $this->isDeadFalseSuperLine($line)) {
                continue;
            }

            $liveSuperCalls += $superCalls;
            if ($superCalls > 1) {
                return true;
            }

            if (!$this->constructorLineHasDirectSuperCall($line)
                && $this->splitTopLevelCommaSuperExpressionStatement($line) === null
                && $this->splitTopLevelCommaSuperControlStatement($line) === null
                && $this->splitTopLevelCommaSuperConditionStatement($line) === null
                && $this->splitTopLevelCommaSuperForInitializerStatement($line) === null
            ) {
                return true;
            }
        }

        return $liveSuperCalls > 1;
    }

    private function constructorLineHasDirectSuperCall(string $line): bool
    {
        return preg_match('/^\s*super\s*\(/', $line) === 1;
    }

    private function isDeadFalseSuperLine(string $line): bool
    {
        return preg_match('/^\s*if\s*\(\s*false\s*\)\s*super\s*\(/', $line) === 1;
    }

    /**
     * @return array{indent:string, before:?string, super:string, after:string}|null
     */
    private function splitTopLevelCommaSuperControlStatement(string $line): ?array
    {
        if (preg_match('/^(\s*)(return|throw)\s+(.+);$/s', $line, $match) !== 1) {
            return null;
        }

        $indent = $match[1];
        $keyword = $match[2];
        $split = $this->splitTopLevelCommaSuperExpression(trim($match[3]));
        if ($split === null || $split['after'] === []) {
            return null;
        }

        return [
            'indent' => $indent,
            'before' => $split['before'] === [] ? null : $indent . implode(', ', $split['before']) . ';',
            'super' => $indent . $split['super'] . ';',
            'after' => $indent . $keyword . ' ' . implode(', ', $split['after']) . ';',
        ];
    }

    /**
     * @return array{indent:string, before:?string, super:string, after:string}|null
     */
    private function splitTopLevelCommaSuperConditionStatement(string $line): ?array
    {
        if (preg_match('/^(\s*)(if|switch)\s*\(/', $line, $match) !== 1) {
            return null;
        }

        $indent = $match[1];
        $open = strpos($line, '(', strlen($indent . $match[2]));
        if ($open === false) {
            return null;
        }

        $close = $this->matchingParenthesisOffset($line, $open);
        if ($close === null) {
            return null;
        }

        $split = $this->splitTopLevelSuperExpression(trim(substr($line, $open + 1, $close - $open - 1)), true);
        if ($split === null) {
            return null;
        }

        return [
            'indent' => $indent,
            'before' => $split['before'] === [] ? null : $indent . implode(', ', $split['before']) . ';',
            'super' => $indent . $split['super'] . ';',
            'after' => substr($line, 0, $open + 1) . implode(', ', $split['after']) . substr($line, $close),
        ];
    }

    /**
     * @return array{indent:string, before:?string, super:string, after:string}|null
     */
    private function splitTopLevelCommaSuperForInitializerStatement(string $line): ?array
    {
        if (preg_match('/^(\s*)for\s*\(/', $line, $match) !== 1) {
            return null;
        }

        $indent = $match[1];
        $open = strpos($line, '(', strlen($indent . 'for'));
        if ($open === false) {
            return null;
        }

        $close = $this->matchingParenthesisOffset($line, $open);
        if ($close === null) {
            return null;
        }

        $header = substr($line, $open + 1, $close - $open - 1);
        $semicolon = $this->topLevelDelimiterOffset($header, ';');
        if ($semicolon === null) {
            return null;
        }

        $initializer = trim(substr($header, 0, $semicolon));
        if ($initializer === '') {
            return null;
        }

        $split = $this->splitTopLevelSuperExpression($initializer, false);
        if ($split === null) {
            return null;
        }

        $afterInitializer = implode(', ', $split['after']);

        return [
            'indent' => $indent,
            'before' => $split['before'] === [] ? null : $indent . implode(', ', $split['before']) . ';',
            'super' => $indent . $split['super'] . ';',
            'after' => substr($line, 0, $open + 1) . $afterInitializer . substr($header, $semicolon) . substr($line, $close),
        ];
    }

    /**
     * @return array{indent:string, before:?string, super:string, after:?string}|null
     */
    private function splitTopLevelCommaSuperExpressionStatement(string $line): ?array
    {
        if (preg_match('/^(\s*)(.*?);$/s', $line, $match) !== 1) {
            return null;
        }

        $indent = $match[1];
        $expression = trim($match[2]);
        if ($expression === ''
            || str_starts_with($expression, 'return ')
            || str_starts_with($expression, 'throw ')
            || preg_match('/^(if|switch|for|while)\b/', $expression) === 1
        ) {
            return null;
        }

        $split = $this->splitTopLevelCommaSuperExpression($expression);
        if ($split === null) {
            return null;
        }

        return [
            'indent' => $indent,
            'before' => $split['before'] === [] ? null : $indent . implode(', ', $split['before']) . ';',
            'super' => $indent . $split['super'] . ';',
            'after' => $split['after'] === [] ? null : $indent . implode(', ', $split['after']) . ';',
        ];
    }

    /**
     * @return array{before:list<string>, super:string, after:list<string>}|null
     */
    private function splitTopLevelSuperExpression(string $expression, bool $requireAfter): ?array
    {
        $split = $this->splitTopLevelCommaSuperExpression($expression);
        if ($split === null && $this->isDirectSuperCallExpression($expression)) {
            $split = [
                'before' => [],
                'super' => trim($expression),
                'after' => [],
            ];
        }

        if ($split === null || ($requireAfter && $split['after'] === [])) {
            return null;
        }

        return $split;
    }

    /**
     * @return array{before:list<string>, super:string, after:list<string>}|null
     */
    private function splitTopLevelCommaSuperExpression(string $expression): ?array
    {
        if ($expression === '') {
            return null;
        }

        $parts = $this->splitTopLevelCommaExpression($expression);
        if (count($parts) < 2) {
            return null;
        }

        $superIndex = null;
        foreach ($parts as $index => $part) {
            if (preg_match('/(^|[^\w$])super\s*\(/', $part) !== 1) {
                continue;
            }

            if (!$this->isDirectSuperCallExpression($part) || $superIndex !== null) {
                return null;
            }
            $superIndex = $index;
        }

        if ($superIndex === null) {
            return null;
        }

        return [
            'before' => array_slice($parts, 0, $superIndex),
            'super' => $parts[$superIndex],
            'after' => array_slice($parts, $superIndex + 1),
        ];
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelCommaExpression(string $expression): array
    {
        $parts = [];
        $start = 0;
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        $quote = null;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
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

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth--;
                continue;
            }
            if ($char === '{') {
                $braceDepth++;
                continue;
            }
            if ($char === '}') {
                $braceDepth--;
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth--;
                continue;
            }
            if ($char !== ',' || $parenDepth !== 0 || $braceDepth !== 0 || $bracketDepth !== 0) {
                continue;
            }

            $part = trim(substr($expression, $start, $i - $start));
            if ($part === '') {
                return [$expression];
            }
            $parts[] = $part;
            $start = $i + 1;
        }

        $tail = trim(substr($expression, $start));
        if ($tail === '') {
            return [$expression];
        }
        $parts[] = $tail;

        return $parts;
    }

    private function matchingParenthesisOffset(string $text, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($text);
        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];
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

            if ($char === '"' || $char === "'" || $char === '`') {
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

        return null;
    }

    private function topLevelDelimiterOffset(string $text, string $delimiter): ?int
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        $quote = null;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
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

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth--;
                continue;
            }
            if ($char === '{') {
                $braceDepth++;
                continue;
            }
            if ($char === '}') {
                $braceDepth--;
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth--;
                continue;
            }

            if ($char === $delimiter && $parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function isDirectSuperCallExpression(string $expression): bool
    {
        $expression = trim($expression);
        if (!str_starts_with($expression, 'super')) {
            return false;
        }

        $cursor = 5;
        $length = strlen($expression);
        while ($cursor < $length && ctype_space($expression[$cursor])) {
            $cursor++;
        }
        if (($expression[$cursor] ?? null) !== '(') {
            return false;
        }

        $depth = 0;
        $quote = null;
        for ($i = $cursor; $i < $length; $i++) {
            $char = $expression[$i];
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

            if ($char === '"' || $char === "'" || $char === '`') {
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
                    return trim(substr($expression, $i + 1)) === '';
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $body
     * @return list<string>
     */
    private function rewriteDeadFalseSuperCalls(array $body): array
    {
        return array_map(
            static fn (string $line): string => preg_replace('/^(\s*if\s*\(\s*false\s*\)\s*)super\s*\(/', '$1__super(', $line) ?? $line,
            $body,
        );
    }

    /**
     * @return array{0:list<string>, 1:list<string>, 2:bool}
     */
    private function lowerConstructorParameters(int $start, int $close): array
    {
        $parameters = [];
        $properties = [];
        $hasParameterProperties = false;

        for ($cursor = $start; $cursor < $close; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ',') {
                continue;
            }

            $parameterEnd = $this->constructorParameterEnd($cursor, $close);
            [$parameter, $propertyName] = $this->lowerConstructorParameter($cursor, $parameterEnd);
            if ($parameter !== '') {
                $parameters[] = $parameter;
            }
            if ($propertyName !== null) {
                $properties[] = $propertyName;
                $hasParameterProperties = true;
            }
            $cursor = $parameterEnd;
        }

        return [$parameters, $properties, $hasParameterProperties];
    }

    private function constructorParameterEnd(int $start, int $close): int
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i < $close; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '{') {
                $braceDepth++;
            } elseif ($text === '}') {
                $braceDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0 && $text === ',') {
                return $i - 1;
            }
        }

        return $close - 1;
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private function lowerConstructorParameter(int $start, int $end): array
    {
        $cursor = $start;
        $modifiers = [];
        while ($cursor <= $end
            && ($this->tokens[$cursor] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$cursor]->text, ['public', 'protected', 'private', 'readonly', 'override'], true)
        ) {
            $modifiers[] = $this->tokens[$cursor]->text;
            $cursor++;
        }

        if ($modifiers === []) {
            return [$this->printParameterRuntimeRange($start, $end), null];
        }

        $name = $this->tokens[$cursor] ?? null;
        if ($name === null) {
            if (array_intersect($modifiers, ['public', 'protected', 'private']) !== []) {
                throw new \InvalidArgumentException('Expected identifier after TypeScript constructor parameter property modifier');
            }

            return [$this->printParameterRuntimeRange($start, $end), null];
        }

        if ($name->text === '{' || $name->text === '[') {
            throw new \InvalidArgumentException('Expected identifier after TypeScript constructor parameter property modifier');
        }

        if ($name->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier after TypeScript constructor parameter property modifier');
        }

        return [$this->printParameterRuntimeRange($cursor, $end), $name->text];
    }

    /**
     * @return list<string>
     */
    private function constructorBodyLines(int $bodyOpen, int $bodyClose): array
    {
        $lines = [];
        for ($cursor = $bodyOpen + 1; $cursor < $bodyClose; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            if (($this->tokens[$cursor] ?? null)?->text === 'if') {
                [$ifLines, $next] = $this->constructorIfStatementLines($cursor, $bodyClose);
                foreach ($ifLines as $line) {
                    $lines[] = $line;
                }
                $cursor = $next;
                continue;
            }

            $statementEnd = $this->constructorStatementEnd($cursor, $bodyClose);
            $line = $this->printConstructorStatement($cursor, $statementEnd);
            if ($line !== '') {
                $lines[] = $line;
            }
            $cursor = $statementEnd;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function constructorBodyTextLines(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        $lines = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^if\s*\((.*?)\)\s*(.*?)\s*else\s*(.*?)$/', $line, $match) === 1) {
                $lines[] = 'if (' . trim($match[1]) . ') ' . $this->ensureStatementSemicolon(trim($match[2]));
                $lines[] = 'else ' . $this->ensureStatementSemicolon(trim($match[3]));
                continue;
            }

            foreach ($this->splitSimpleSemicolonStatements($line) as $statement) {
                $lines[] = $statement;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function splitSimpleSemicolonStatements(string $line): array
    {
        $statements = [];
        $start = 0;
        $depth = 0;
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                continue;
            }
            if ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
                continue;
            }
            if ($char !== ';' || $depth !== 0) {
                continue;
            }

            $statement = trim(substr($line, $start, $i - $start + 1));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $start = $i + 1;
        }

        $tail = trim(substr($line, $start));
        if ($tail !== '') {
            $statements[] = $this->ensureStatementSemicolon($tail);
        }

        return $statements;
    }

    /**
     * @return array{0:list<string>, 1:int}
     */
    private function constructorIfStatementLines(int $ifIndex, int $bodyClose): array
    {
        if (($this->tokens[$ifIndex + 1] ?? null)?->text !== '(') {
            return [[$this->printConstructorStatement($ifIndex, $this->constructorStatementEnd($ifIndex, $bodyClose))], $ifIndex];
        }

        $conditionOpen = $ifIndex + 1;
        $conditionClose = $this->findMatchingPunctuator($conditionOpen, '(', ')');
        $thenStart = $conditionClose + 1;
        $thenEnd = $this->constructorStatementEnd($thenStart, $bodyClose);
        $lines = ['if (' . $this->printTokenRange($conditionOpen + 1, $conditionClose - 1) . ') ' . $this->printConstructorStatement($thenStart, $thenEnd)];
        $next = $thenEnd;

        if (($this->tokens[$thenEnd + 1] ?? null)?->text === 'else') {
            $elseStart = $thenEnd + 2;
            $elseEnd = $this->constructorStatementEnd($elseStart, $bodyClose);
            $lines[] = 'else ' . $this->printConstructorStatement($elseStart, $elseEnd);
            $next = $elseEnd;
        }

        return [$lines, $next];
    }

    private function constructorStatementEnd(int $start, int $bodyClose): int
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i < $bodyClose; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($text === '{') {
                $braceDepth++;
            } elseif ($text === '}') {
                $braceDepth--;
                if ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
                    return $i;
                }
            } elseif ($parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0 && $text === ';') {
                return $i;
            }
        }

        return $bodyClose - 1;
    }

    private function printConstructorStatement(int $start, int $end): string
    {
        $statement = $this->printTokenRange($start, $end);
        if ($statement === '') {
            return '';
        }

        return $this->ensureStatementSemicolon($statement);
    }

    private function ensureStatementSemicolon(string $statement): string
    {
        return str_ends_with($statement, ';') || str_ends_with($statement, '}')
            ? $statement
            : $statement . ';';
    }

    private function printParameterRuntimeRange(int $start, int $end): string
    {
        $parts = [];
        $previous = null;
        for ($i = $start; $i <= $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            if (($token->text === '?' || $token->text === '!') && ($this->tokens[$i + 1] ?? null)?->text === ':') {
                continue;
            }

            if ($token->text === ':' && $this->isParameterTypeColon($i, $start, $end)) {
                $i = $this->skipTypeExpression($i + 1, $end, ['=']) - 1;
                continue;
            }

            $text = $token->text;
            if ($token->kind === 'string') {
                $text = $this->quoteJsString($this->stringTokenValue($token));
            }

            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
    }

    private function isParameterTypeColon(int $index, int $start, int $end): bool
    {
        if (!$this->isTopLevelInRange($start, $index)) {
            return false;
        }

        $previous = $this->previousSignificantTokenIndex($index - 1);
        if ($previous !== null && in_array(($this->tokens[$previous] ?? null)?->text, ['?', '!'], true)) {
            $previous = $this->previousSignificantTokenIndex($previous - 1);
        }

        $previousToken = $previous === null ? null : $this->tokens[$previous];

        return $previousToken !== null
            && ($previousToken->kind === 'identifier' || in_array($previousToken->text, [')', ']'], true));
    }

    private function classMemberHasBody(int $start, int $end): bool
    {
        for ($i = $start; $i <= $end; $i++) {
            if (($this->tokens[$i] ?? null)?->text === '{' && $this->isTopLevelInRange($start, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $modifiers
     */
    private function containsClassMemberTypeScriptSyntax(int $start, int $end, array $modifiers): bool
    {
        if (array_intersect($modifiers, ['abstract', 'override', 'private', 'protected', 'public', 'readonly']) !== []) {
            return true;
        }

        for ($i = $start; $i <= $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }
            if ($token->text === ':' && (
                $this->isClassMemberTypeColon($i, $start, $end)
                || $this->isClassMethodParameterTypeColon($i, $start, $end)
            )) {
                return true;
            }
            if (($token->text === '?' || $token->text === '!')
                && $this->isClassMemberOptionalOrDefiniteMarker($i, $start, $end)
            ) {
                return true;
            }
            if ($token->text === '<' && (
                $this->isDecoratorTypeArgumentList($i, $start, $end)
                || $this->isClassMemberTypeParameterList($i, $start, $end)
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function classMemberModifierKeywords(): array
    {
        return [
            'abstract',
            'accessor',
            'declare',
            'get',
            'override',
            'private',
            'protected',
            'public',
            'readonly',
            'set',
            'static',
        ];
    }

    private function classMemberEnd(int $start, int $end): int
    {
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        for ($i = $start; $i < $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0 && $text === '@') {
                $i = $this->decoratorEnd($i);
            } elseif ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($text === '{') {
                $braceDepth++;
            } elseif ($text === '}') {
                $braceDepth--;
                if ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
                    return $i;
                }
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0 && $text === ';') {
                return $i;
            }

            if ($parenDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
                && $this->hasLineBreakBetween($i, $i + 1)
            ) {
                if ($this->startsAfterLeadingDecorator($i + 1, $start, $end)) {
                    continue;
                }

                return $i;
            }
        }

        return $end - 1;
    }

    /**
     * @param list<string> $extendsPrelude
     */
    private function classHeaderText(
        int $start,
        int $bodyOpen,
        array $extendsPrelude = [],
        ?string $extendsTemp = null,
        ?string $anonymousClassName = null,
        array $skipRanges = [],
    ): string
    {
        $parts = [];
        $previous = null;
        for ($i = $start; $i < $bodyOpen; $i++) {
            if (isset($skipRanges[$i])) {
                $i = $skipRanges[$i];
                continue;
            }

            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }
            if ($token->text === 'abstract') {
                continue;
            }
            if ($token->text === '<') {
                $i = $this->typeParameterListEnd($i, $bodyOpen);
                continue;
            }
            if ($token->text === 'implements') {
                break;
            }
            if ($token->text === 'extends' && $extendsTemp !== null) {
                $extendsExpressionStart = $i + 1;
                $extendsExpressionEnd = $this->classHeaderExtendsExpressionEnd($extendsExpressionStart, $bodyOpen);
                $extendsExpression = $this->printTokenRange($extendsExpressionStart, $extendsExpressionEnd);
                if ($extendsExpression === '') {
                    throw new \InvalidArgumentException('Expected class extends expression');
                }
                if ($previous !== null && $this->needsSpace($previous, 'extends')) {
                    $parts[] = ' ';
                }
                $parts[] = 'extends';
                $parts[] = ' ';
                $parts[] = '(' . implode(', ', array_merge(
                    [$extendsTemp . ' = ' . $extendsExpression],
                    $this->statementLinesToExpressions($extendsPrelude),
                    [$extendsTemp],
                )) . ')';
                $previous = ')';
                $i = $extendsExpressionEnd;
                continue;
            }

            $text = $token->text;
            if ($text === 'class' && $anonymousClassName !== null) {
                if ($previous !== null && $this->needsSpace($previous, $text)) {
                    $parts[] = ' ';
                }
                $parts[] = 'class ' . $anonymousClassName;
                $previous = $anonymousClassName;
                continue;
            }
            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
    }

    private function classHeaderExtendsExpressionEnd(int $start, int $bodyOpen): int
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        $end = $bodyOpen - 1;
        for ($i = $start; $i < $bodyOpen; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '{') {
                $braceDepth++;
            } elseif ($text === '}') {
                $braceDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0 && $text === 'implements') {
                return $i - 1;
            }
        }

        return $end;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function statementLinesToExpressions(array $lines): array
    {
        return array_map(
            static fn (string $line): string => rtrim(trim($line), ';'),
            $lines,
        );
    }

    private function typeParameterListEnd(int $start, int $limit): int
    {
        $depth = 0;
        for ($i = $start; $i < $limit; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '<') {
                $depth++;
            } elseif ($text === '>') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $start;
    }

    private function printClassMemberRange(int $start, int $end): string
    {
        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        if ($effectiveEnd < $start) {
            return '';
        }

        $member = $this->printTokenRange($start, $effectiveEnd);
        if ($member === '') {
            return '';
        }

        $last = $this->tokens[$effectiveEnd] ?? null;
        if ($last?->text !== '}' && !str_ends_with($member, ';')) {
            $member .= ';';
        }

        return $member;
    }

    private function printClassMemberRuntimeRange(int $start, int $end): string
    {
        $effectiveEnd = ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
        if ($effectiveEnd < $start) {
            return '';
        }

        $parts = [];
        $previous = null;
        for ($i = $start; $i <= $effectiveEnd; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            if ($token->kind === 'identifier'
                && in_array($token->text, ['abstract', 'override', 'private', 'protected', 'public', 'readonly'], true)
                && $this->isTopLevelInRange($start, $i)
            ) {
                continue;
            }

            if ($token->text === '<' && (
                $this->isDecoratorTypeArgumentList($i, $start, $effectiveEnd)
                || $this->isClassMemberTypeParameterList($i, $start, $effectiveEnd)
            )) {
                $i = $this->typeParameterListEnd($i, $effectiveEnd);
                continue;
            }

            if (($token->text === '?' || $token->text === '!')
                && $this->isClassMemberOptionalOrDefiniteMarker($i, $start, $effectiveEnd)
            ) {
                if ($token->text === '!' && $this->classMemberMarkerStartsMethod($i, $start, $effectiveEnd)) {
                    throw new \InvalidArgumentException('Expected ";" but found "' . (($this->tokens[$i + 1] ?? null)?->text ?? '') . '"');
                }
                continue;
            }

            if ($token->text === ':' && $this->isClassMemberTypeColon($i, $start, $effectiveEnd)) {
                $i = $this->skipTypeExpression($i + 1, $effectiveEnd, ['=', '{', ';']) - 1;
                continue;
            }

            if ($token->text === ':' && $this->isClassMethodParameterTypeColon($i, $start, $effectiveEnd)) {
                $i = $this->skipTypeExpression($i + 1, $effectiveEnd, ['=', ',', ')']) - 1;
                continue;
            }

            $text = $token->text;
            if ($token->kind === 'string') {
                $text = $this->quoteJsString($this->stringTokenValue($token));
            }

            if ($previous !== null && (
                $this->needsSpace($previous, $text)
                || $this->startsAfterLeadingDecorator($i, $start, $effectiveEnd)
            )) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        if ($parts === []) {
            return '';
        }

        $member = implode('', $parts);
        $last = $this->tokens[$effectiveEnd] ?? null;
        $hasTopLevelInitializer = $this->findTopLevelTokenInRange('=', $start, $effectiveEnd) !== null;
        if (($hasTopLevelInitializer || $last?->text !== '}') && !str_ends_with($member, ';')) {
            $member .= ';';
        }

        return $member;
    }

    private function isClassMemberTypeColon(int $index, int $start, int $end): bool
    {
        if (!$this->isTopLevelInRange($start, $index)) {
            return false;
        }

        $previous = $this->previousSignificantTokenIndex($index - 1);
        if ($previous !== null && in_array(($this->tokens[$previous] ?? null)?->text, ['?', '!'], true)) {
            $previous = $this->previousSignificantTokenIndex($previous - 1);
        }

        $previousToken = $previous === null ? null : $this->tokens[$previous];
        if ($previousToken === null) {
            return false;
        }

        return $previousToken->kind === 'identifier'
            || $previousToken->kind === 'private_identifier'
            || $previousToken->kind === 'string'
            || in_array($previousToken->text, [')', ']'], true);
    }

    private function isClassMethodParameterTypeColon(int $index, int $start, int $end): bool
    {
        $bodyOpen = $this->classMemberBodyOpen($start, $end);
        if ($bodyOpen !== null && $index > $bodyOpen) {
            return false;
        }

        $open = $this->enclosingOpenPunctuator($index, '(', ')', $start);
        if ($open === null) {
            return false;
        }

        $close = $this->findMatchingPunctuator($open, '(', ')');
        if ($bodyOpen !== null && $close > $bodyOpen) {
            return false;
        }

        $previous = $this->previousSignificantTokenIndex($index - 1);
        if ($previous !== null && in_array(($this->tokens[$previous] ?? null)?->text, ['?', '!'], true)) {
            $previous = $this->previousSignificantTokenIndex($previous - 1);
        }

        $previousToken = $previous === null ? null : $this->tokens[$previous];

        return $previousToken !== null
            && ($previousToken->kind === 'identifier' || in_array($previousToken->text, [')', ']'], true));
    }

    private function classMemberBodyOpen(int $start, int $end): ?int
    {
        $parenDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $text === '{') {
                return $i;
            }
        }

        return null;
    }

    private function isClassMemberOptionalOrDefiniteMarker(int $index, int $start, int $end): bool
    {
        if (!$this->isTopLevelInRange($start, $index)) {
            return false;
        }

        $previous = $this->previousSignificantTokenIndex($index - 1);
        $next = $this->tokens[$index + 1] ?? null;

        return $previous !== null
            && (
                ($this->tokens[$previous]->kind === 'identifier'
                    || $this->tokens[$previous]->kind === 'private_identifier'
                    || $this->tokens[$previous]->kind === 'string'
                    || $this->tokens[$previous]->text === ']')
                && ($index === $end || ($next !== null && in_array($next->text, [':', '=', ';', '(', '<'], true)))
            );
    }

    private function isClassMemberTypeParameterList(int $index, int $start, int $end): bool
    {
        if (!$this->isTopLevelInRange($start, $index)) {
            return false;
        }

        $close = $this->typeParameterListEnd($index, $end);

        return $close > $index && ($this->tokens[$close + 1] ?? null)?->text === '(';
    }

    private function isDecoratorTypeArgumentList(int $index, int $start, int $end): bool
    {
        if (($this->tokens[$index] ?? null)?->text !== '<') {
            return false;
        }

        $cursor = $start;
        while ($cursor <= $end && ($this->tokens[$cursor] ?? null)?->text === '@') {
            $decoratorEnd = $this->decoratorEnd($cursor);
            if ($index > $cursor && $index <= $decoratorEnd) {
                if (!$this->isTopLevelInRange($cursor, $index)) {
                    return false;
                }

                $previous = $this->tokens[$index - 1] ?? null;
                if ($previous === null || !in_array($previous->kind, ['identifier', 'private_identifier'], true)) {
                    return false;
                }

                $close = $this->typeParameterListEnd($index, $decoratorEnd + 1);

                return $close > $index;
            }

            $cursor = $decoratorEnd + 1;
        }

        return false;
    }

    private function startsAfterLeadingDecorator(int $index, int $start, int $end): bool
    {
        $cursor = $start;
        while ($cursor <= $end && ($this->tokens[$cursor] ?? null)?->text === '@') {
            $decoratorEnd = $this->decoratorEnd($cursor);
            $afterDecorator = $decoratorEnd + 1;
            if ($index === $afterDecorator) {
                return true;
            }

            $onlySkippedModifiers = $afterDecorator < $index;
            for ($modifier = $afterDecorator; $modifier < $index; $modifier++) {
                $token = $this->tokens[$modifier] ?? null;
                if ($token?->kind !== 'identifier'
                    || !in_array($token->text, ['abstract', 'override', 'private', 'protected', 'public', 'readonly'], true)
                ) {
                    $onlySkippedModifiers = false;
                    break;
                }
            }

            if ($onlySkippedModifiers) {
                return true;
            }

            $cursor = $decoratorEnd + 1;
        }

        return false;
    }

    private function classMemberMarkerStartsMethod(int $index, int $start, int $end): bool
    {
        $next = $this->tokens[$index + 1] ?? null;
        if ($next?->text === '(') {
            return true;
        }

        if ($next?->text !== '<') {
            return false;
        }

        return $this->isClassMemberTypeParameterList($index + 1, $start, $end);
    }

    private function isTopLevelInRange(int $start, int $index): bool
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i < $index; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '{') {
                $braceDepth++;
            } elseif ($text === '}') {
                $braceDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            }
        }

        return $parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0;
    }

    private function findTopLevelBlockOpenBeforeStatementEnd(int $start): ?int
    {
        $parenDepth = 0;
        $bracketDepth = 0;
        $count = count($this->tokens);

        for ($i = $start; $i < $count; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $text === '{') {
                return $i;
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $text === ';') {
                return null;
            }

            if ($parenDepth === 0 && $bracketDepth === 0 && $this->hasLineBreakBetween($i, $i + 1)) {
                return null;
            }
        }

        return null;
    }

    private function findTopLevelTokenInRange(string $text, int $start, int $end): ?int
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $tokenText = $this->tokens[$i]->text;
            if ($tokenText === '(') {
                $parenDepth++;
            } elseif ($tokenText === ')') {
                $parenDepth--;
            } elseif ($tokenText === '{') {
                $braceDepth++;
            } elseif ($tokenText === '}') {
                $braceDepth--;
            } elseif ($tokenText === '[') {
                $bracketDepth++;
            } elseif ($tokenText === ']') {
                $bracketDepth--;
            } elseif ($parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0 && $tokenText === $text) {
                return $i;
            }
        }

        return null;
    }

    private function validateExportAsNamespaceInRange(int $start, int $end): void
    {
        for ($i = $start; $i < $end; $i++) {
            if (($this->tokens[$i] ?? null)?->text !== 'export'
                || ($this->tokens[$i + 1] ?? null)?->text !== 'as'
                || ($this->tokens[$i + 2] ?? null)?->text !== 'namespace'
            ) {
                continue;
            }

            $name = $this->tokens[$i + 3] ?? null;
            if ($name?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected identifier after TypeScript export as namespace');
            }

            $afterName = $this->tokens[$i + 4] ?? null;
            if ($afterName?->text === '.') {
                throw new \InvalidArgumentException('Expected ";" after TypeScript export as namespace');
            }
            if ($afterName !== null
                && $afterName->text !== ';'
                && $i + 4 < $end
                && !$this->hasLineBreakBetween($i + 3, $i + 4)
            ) {
                throw new \InvalidArgumentException('Expected ";" after TypeScript export as namespace');
            }
        }
    }

    private function enumStatementEnd(int $enumIndex): int
    {
        if (($this->tokens[$enumIndex + 2] ?? null)?->text !== '{') {
            throw new \InvalidArgumentException('Expected TypeScript enum block');
        }

        $close = $this->findMatchingPunctuator($enumIndex + 2, '{', '}');

        return ($this->tokens[$close + 1] ?? null)?->text === ';' ? $close + 1 : $close;
    }

    private function lowerEnumStatement(int $enumIndex, int $effectiveEnd, bool $exported): string
    {
        $name = $this->tokens[$enumIndex + 1] ?? null;
        if ($name?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected TypeScript enum name');
        }
        if (($this->tokens[$enumIndex + 2] ?? null)?->text !== '{') {
            throw new \InvalidArgumentException('Expected TypeScript enum block');
        }

        $open = $enumIndex + 2;
        $close = $this->findMatchingPunctuator($open, '{', '}');
        if ($close > $effectiveEnd) {
            throw new \InvalidArgumentException('TypeScript enum block must end inside its statement');
        }

        $members = $this->parseEnumMembers($open + 1, $close, $name->text, $this->enumConstants[$name->text] ?? [], $this->enumConstants);
        $parameter = $this->enumParameterName($name->text, $members);
        $lines = [
            ($exported ? 'export var ' : 'var ') . $name->text . ' = ' . ($this->enumMembersArePure($members) ? '/* @__PURE__ */ ' : '') . '((' . $parameter . ') => {',
        ];

        foreach ($members as $member) {
            $memberName = $this->quoteJsString($member['name']);
            if ($member['assignmentKind'] === 'string') {
                $lines[] = '  ' . $parameter . '[' . $memberName . '] = ' . $member['assignment'] . ';';
            } else {
                $lines[] = '  ' . $parameter . '[' . $parameter . '[' . $memberName . '] = ' . $member['assignment'] . '] = ' . $memberName . ';';
            }
        }

        $lines[] = '  return ' . $parameter . ';';
        $lines[] = '})(' . $name->text . ' || {});';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, array<string, array{value:string, comment:string, numericValue:?float}>>
     */
    private function collectEnumConstants(): array
    {
        $constants = [];
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            $statement = $this->enumStatementAt($i);
            if ($statement === null) {
                continue;
            }

            [, $declared, , $enumIndex] = $statement;
            $name = $this->tokens[$enumIndex + 1] ?? null;
            if ($declared || $name?->kind !== 'identifier' || ($this->tokens[$enumIndex + 2] ?? null)?->text !== '{') {
                continue;
            }

            $open = $enumIndex + 2;
            $close = $this->findMatchingPunctuator($open, '{', '}');
            foreach ($this->parseEnumMembers($open + 1, $close, $name->text, $constants[$name->text] ?? [], $constants) as $member) {
                if (!in_array($member['assignmentKind'], ['number', 'string'], true)) {
                    continue;
                }

                $constants[$name->text][$member['name']] = [
                    'value' => $member['constantValue'],
                    'comment' => $this->enumInlineComment($member['name']),
                    'numericValue' => $member['numericValue'],
                ];
            }
            $i = $close;
        }

        return $constants;
    }

    /**
     * @param array<string, array{value:string, comment:string, numericValue:?float}> $knownMembers
     * @param array<string, array<string, array{value:string, comment:string, numericValue:?float}>> $knownEnums
     * @return list<array{name:string, assignment:string, constantValue:string, assignmentKind:string, numericValue:?float}>
     */
    private function parseEnumMembers(int $start, int $close, string $enumName, array $knownMembers = [], array $knownEnums = []): array
    {
        $members = [];
        $memberConstants = $knownMembers;
        $enumConstants = $knownEnums;
        $enumConstants[$enumName] = $memberConstants;
        $cursor = $start;
        $previousNumeric = -1.0;

        while ($cursor < $close) {
            $separator = $this->tokens[$cursor] ?? null;
            if ($separator?->text === ',' || $separator?->text === ';') {
                $cursor++;
                continue;
            }

            $nameToken = $this->tokens[$cursor] ?? null;
            if ($nameToken === null || ($nameToken->kind !== 'identifier' && $nameToken->kind !== 'string')) {
                throw new \InvalidArgumentException('Expected TypeScript enum member name');
            }

            $memberName = $this->enumMemberName($nameToken);
            $cursor++;
            $assignmentKind = 'number';
            $numericValue = null;

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $expressionStart = $cursor + 1;
                if ($expressionStart >= $close) {
                    throw new \InvalidArgumentException('Expected TypeScript enum member value');
                }

                $expressionEnd = $this->enumExpressionEnd($expressionStart, $close);
                [$assignment, $assignmentKind, $numericValue] = $this->enumMemberAssignment(
                    $expressionStart,
                    $expressionEnd,
                    $memberConstants,
                    $enumConstants,
                );
                $previousNumeric = $numericValue;
                $cursor = $expressionEnd + 1;
            } elseif ($previousNumeric !== null) {
                $previousNumeric++;
                $assignment = $this->formatEnumNumber($previousNumeric);
                $numericValue = $previousNumeric;
            } else {
                $assignment = 'void 0';
                $assignmentKind = 'void';
            }

            $constantValue = $assignmentKind === 'number' && $numericValue !== null
                ? $this->formatEnumNumber($numericValue)
                : $assignment;
            $members[] = [
                'name' => $memberName,
                'assignment' => $assignment,
                'constantValue' => $constantValue,
                'assignmentKind' => $assignmentKind,
                'numericValue' => $numericValue,
            ];
            if (in_array($assignmentKind, ['number', 'string'], true)) {
                $memberConstants[$memberName] = [
                    'value' => $constantValue,
                    'comment' => $this->enumInlineComment($memberName),
                    'numericValue' => $numericValue,
                ];
                $enumConstants[$enumName] = $memberConstants;
            }

            $after = $this->tokens[$cursor] ?? null;
            if ($cursor < $close && $after?->text !== ',' && $after?->text !== ';') {
                throw new \InvalidArgumentException('Expected "," after TypeScript enum member');
            }
            if ($cursor < $close) {
                $cursor++;
            }
        }

        return $members;
    }

    private function enumExpressionEnd(int $start, int $close): int
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
     * @param array<string, array{value:string, comment:string, numericValue:?float}> $memberConstants
     * @param array<string, array<string, array{value:string, comment:string, numericValue:?float}>> $enumConstants
     * @return array{0:string, 1:string, 2:?float}
     */
    private function enumMemberAssignment(int $start, int $end, array $memberConstants, array $enumConstants): array
    {
        if ($this->hasAdjacentEnumValueTokens($start, $end)) {
            throw new \InvalidArgumentException('Expected "," after TypeScript enum member');
        }

        $constant = $this->evaluateEnumConstantExpression($start, $end, $memberConstants, $enumConstants);
        if ($constant !== null) {
            [$value, $comment] = $constant;
            $assignment = $this->formatEnumNumber($value);
            if ($comment !== null) {
                $assignment .= ' /* ' . $comment . ' */';
            }

            return [$assignment, 'number', $value];
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
            $value = ($this->tokens[$end]->numberValue ?? (float) $this->tokens[$end]->text);
            if ($this->tokens[$start]->text === '-') {
                $value *= -1;
            }

            return [$this->formatEnumNumber($value), 'number', $value];
        }

        return [$this->printTokenRange($start, $end), 'unknown', null];
    }

    /**
     * @param array<string, array{value:string, comment:string, numericValue:?float}> $memberConstants
     * @param array<string, array<string, array{value:string, comment:string, numericValue:?float}>> $enumConstants
     * @return array{0:float, 1:?string}|null
     */
    private function evaluateEnumConstantExpression(int $start, int $end, array $memberConstants, array $enumConstants): ?array
    {
        $single = $this->enumConstantAt($start, $end, $memberConstants, $enumConstants);
        if ($single !== null && $single['lastIndex'] === $end && $single['numericValue'] !== null) {
            return [$single['numericValue'], $single['comment']];
        }

        $cursor = $start;
        $value = 0.0;
        $sign = 1.0;
        $expectTerm = true;
        $hasTerm = false;

        while ($cursor <= $end) {
            $token = $this->tokens[$cursor] ?? null;
            if ($token === null) {
                return null;
            }

            if ($expectTerm) {
                if ($token->text === '+' || $token->text === '-') {
                    $sign = $token->text === '-' ? -1.0 : 1.0;
                    $cursor++;
                    continue;
                }

                if ($token->kind === 'number') {
                    $value += $sign * ($token->numberValue ?? (float) $token->text);
                    $cursor++;
                    $expectTerm = false;
                    $hasTerm = true;
                    continue;
                }

                $constant = $this->enumConstantAt($cursor, $end, $memberConstants, $enumConstants);
                if ($constant !== null && $constant['numericValue'] !== null) {
                    $value += $sign * $constant['numericValue'];
                    $cursor = $constant['lastIndex'] + 1;
                    $expectTerm = false;
                    $hasTerm = true;
                    continue;
                }

                return null;
            }

            if ($token->text !== '+' && $token->text !== '-') {
                return null;
            }
            $sign = $token->text === '-' ? -1.0 : 1.0;
            $cursor++;
            $expectTerm = true;
        }

        return $hasTerm && !$expectTerm ? [$value, null] : null;
    }

    /**
     * @param array<string, array{value:string, comment:string, numericValue:?float}> $memberConstants
     * @param array<string, array<string, array{value:string, comment:string, numericValue:?float}>> $enumConstants
     * @return array{value:string, comment:string, numericValue:?float, lastIndex:int}|null
     */
    private function enumConstantAt(int $index, int $end, array $memberConstants, array $enumConstants): ?array
    {
        $token = $this->tokens[$index] ?? null;
        if ($token?->kind !== 'identifier') {
            return null;
        }

        $member = null;
        $lastIndex = null;
        if ($index + 2 <= $end
            && ($this->tokens[$index + 1] ?? null)?->text === '.'
            && ($this->tokens[$index + 2] ?? null)?->kind === 'identifier'
        ) {
            $member = $this->tokens[$index + 2]->text;
            $lastIndex = $index + 2;
        } elseif ($index + 3 <= $end
            && ($this->tokens[$index + 1] ?? null)?->text === '['
            && ($this->tokens[$index + 2] ?? null)?->kind === 'string'
            && ($this->tokens[$index + 3] ?? null)?->text === ']'
        ) {
            $member = $this->stringTokenValue($this->tokens[$index + 2]);
            $lastIndex = $index + 3;
        }

        if ($member === null || $lastIndex === null || !isset($enumConstants[$token->text][$member])) {
            return isset($memberConstants[$token->text])
                ? $memberConstants[$token->text] + ['lastIndex' => $index]
                : null;
        }

        return $enumConstants[$token->text][$member] + ['lastIndex' => $lastIndex];
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

    /**
     * @param list<array{name:string, assignment:string, constantValue:string, assignmentKind:string, numericValue:?float}> $members
     */
    private function enumParameterName(string $enumName, array $members): string
    {
        foreach ($members as $member) {
            if ($member['name'] === $enumName) {
                return '_' . $enumName;
            }
        }

        return $enumName;
    }

    /**
     * @param list<array{name:string, assignment:string, constantValue:string, assignmentKind:string, numericValue:?float}> $members
     */
    private function enumMembersArePure(array $members): bool
    {
        foreach ($members as $member) {
            if ($member['assignmentKind'] === 'unknown') {
                return false;
            }
        }

        return true;
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

    private function importEqualsTargetEnd(int $start): int
    {
        if (($this->tokens[$start] ?? null)?->kind === 'identifier'
            && $this->tokens[$start]->text === 'require'
            && ($this->tokens[$start + 1] ?? null)?->text === '('
        ) {
            $end = $this->findMatchingPunctuator($start + 1, '(', ')');
            if ($end !== $start + 3 || ($this->tokens[$start + 2] ?? null)?->kind !== 'string') {
                throw new \InvalidArgumentException('TypeScript import equals require target must contain one string argument');
            }

            return $end + 1;
        }

        [, $cursor] = $this->readQualifiedName($start);

        return $cursor;
    }

    private function assertStatementConsumed(int $cursor, int $effectiveEnd): void
    {
        if ($cursor <= $effectiveEnd) {
            throw new \InvalidArgumentException('Expected TypeScript import equals to end after its target');
        }
    }

    private function isErasableTypeScriptStatement(int $start): bool
    {
        $first = $this->tokens[$start] ?? null;
        if ($first?->kind !== 'identifier') {
            return false;
        }

        if (in_array($first->text, ['type', 'interface'], true)) {
            return true;
        }

        return $first->text === 'export'
            && ($this->tokens[$start + 1] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$start + 1]->text, ['type', 'interface'], true);
    }

    private function originalStatementText(int $start, int $end): string
    {
        $first = $this->tokens[$start] ?? null;
        $last = $this->tokens[$end] ?? null;
        if ($first === null || $last === null) {
            return '';
        }

        $statement = trim(substr(
            $this->source,
            $first->offset,
            $last->offset + strlen($last->text) - $first->offset
        ));
        if ($statement === '') {
            return '';
        }

        if (!str_ends_with($statement, ';') && $last->text !== '}') {
            $statement .= ';';
        }

        return $statement;
    }

    private function containsErasableTypeScriptSyntax(int $start, int $end): bool
    {
        for ($i = $start; $i <= $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            if (($token->text === ':' && $this->isTypeAnnotationColon($i, $start))
                || (($token->text === 'as' || $token->text === 'satisfies') && $this->isTypeCastKeyword($i, $start))
                || ($token->text === '!' && $this->isPostfixTypeAssertion($i, $start))
                || ($token->text === '?' && ($this->tokens[$i + 1] ?? null)?->text === ':' && $this->isOptionalTypeMarker($i, $start))
            ) {
                return true;
            }
        }

        return false;
    }

    private function printRuntimeStatement(int $start, int $end): string
    {
        $statement = $this->printRuntimeTokenRange($start, $end, $start);
        if ($statement === '') {
            return '';
        }

        if ($this->runtimeStatementNeedsSemicolon($start, $end, $statement)) {
            $statement .= ';';
        }

        return $statement;
    }

    private function printRuntimeTokenRange(int $start, int $end, ?int $statementStart = null): string
    {
        $statementStart ??= $start;
        $parts = [];
        $previous = null;
        for ($i = $start; $i <= $end; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null) {
                continue;
            }

            $enumReference = $this->enumReferenceAt($i, $end);
            if ($enumReference !== null) {
                [$text, $lastIndex] = $enumReference;
                if ($previous !== null && $this->needsSpace($previous, $text)) {
                    $parts[] = ' ';
                }
                $parts[] = $text;
                $previous = $text;
                $i = $lastIndex;
                continue;
            }

            if ($token->text === '?' && ($this->tokens[$i + 1] ?? null)?->text === ':' && $this->isOptionalTypeMarker($i, $statementStart)) {
                continue;
            }

            if ($token->text === ':' && $this->isTypeAnnotationColon($i, $statementStart)) {
                $i = $this->skipTypeExpression($i + 1, $end, $this->typeAnnotationStopTokens($i, $statementStart)) - 1;
                continue;
            }

            if (($token->text === 'as' || $token->text === 'satisfies') && $this->isTypeCastKeyword($i, $statementStart)) {
                $i = $this->skipTypeExpression($i + 1, $end, [',', ')', ']', '}', ';']) - 1;
                continue;
            }

            if ($token->text === '!' && $this->isPostfixTypeAssertion($i, $statementStart)) {
                continue;
            }

            $text = $token->text;
            if ($token->kind === 'string') {
                $text = '"' . addcslashes(stripcslashes(substr($token->text, 1, -1)), "\\\"") . '"';
            }

            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        if ($parts === []) {
            return '';
        }

        return implode('', $parts);
    }

    private function runtimeStatementNeedsSemicolon(int $start, int $end, string $statement): bool
    {
        $last = $this->tokens[$end] ?? null;
        $firstText = ($this->tokens[$start] ?? null)?->text;
        $secondText = ($this->tokens[$start + 1] ?? null)?->text;
        $isVariableDeclaration = in_array($firstText, ['let', 'const', 'var'], true)
            || $firstText === 'using'
            || ($firstText === 'export' && in_array($secondText, ['let', 'const', 'var'], true));

        return ($isVariableDeclaration || $last?->text !== '}') && !str_ends_with($statement, ';');
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerClassExpressionStatementAt(int $start, int $effectiveEnd): ?array
    {
        $output = '';
        $cursor = $start;
        $matched = false;
        $fieldKeyTemps = [];

        for ($classIndex = $start; $classIndex <= $effectiveEnd; $classIndex++) {
            $classExpression = $this->lowerClassExpressionAt($classIndex, $effectiveEnd, $fieldKeyTemps);
            if ($classExpression === null) {
                continue;
            }

            if ($cursor <= $classIndex - 1) {
                $output .= $this->printRuntimeTokenRange($cursor, $classIndex - 1, $start);
            }
            $output .= $this->classExpressionSeparator(rtrim($output)) . $classExpression['expression'];
            $cursor = $classExpression['bodyClose'] + 1;
            $matched = true;
            $classIndex = $classExpression['bodyClose'];
        }

        if (!$matched) {
            return null;
        }

        if ($cursor <= $effectiveEnd) {
            $output .= $this->printRuntimeTokenRange($cursor, $effectiveEnd, $start);
        }

        if ($output !== '' && !str_ends_with($output, ';')) {
            $output .= ';';
        }

        if ($fieldKeyTemps !== []) {
            $output = 'var ' . implode(', ', $fieldKeyTemps) . ";\n" . $output;
        }

        return [$output, $effectiveEnd];
    }

    /**
     * @param list<string> $fieldKeyTemps
     * @return array{expression:string, bodyClose:int}|null
     */
    private function lowerClassExpressionAt(int $classIndex, int $effectiveEnd, array &$fieldKeyTemps): ?array
    {
        [$decoratorTexts, , $cursor] = ($this->tokens[$classIndex] ?? null)?->text === '@'
            ? $this->classStatementDecorators($classIndex)
            : [[], [], $classIndex];

        if (($this->tokens[$cursor] ?? null)?->text !== 'class') {
            return null;
        }
        $classIndex = $cursor;

        $bodyOpen = $this->classExpressionBodyOpen($classIndex, $effectiveEnd);
        if ($bodyOpen === null) {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd) {
            return null;
        }

        $hasTypeScriptClassSyntax = $this->classHeaderContainsTypeScriptSyntax($classIndex, $bodyOpen);
        [$members, $hasTypeScriptMemberSyntax, , $fieldKeyPrelude, $afterClassStaticAssignments] = $this->lowerClassMembers(
            $bodyOpen + 1,
            $bodyClose,
            $this->classHeaderHasExtends($classIndex, $bodyOpen),
            $fieldKeyTemps,
        );
        if (!$hasTypeScriptClassSyntax && !$hasTypeScriptMemberSyntax && $decoratorTexts === []) {
            return null;
        }

        $fieldKeyExtendsPrelude = [];
        $extendsTemp = null;
        if ($this->useDefineForClassFields === false && $fieldKeyPrelude !== [] && $this->classHeaderHasExtends($classIndex, $bodyOpen)) {
            $extendsTemp = $this->allocateClassFieldTemp($fieldKeyTemps);
            $fieldKeyExtendsPrelude = $fieldKeyPrelude;
            $fieldKeyPrelude = [];
        }

        $header = $this->classHeaderText($classIndex, $bodyOpen, $fieldKeyExtendsPrelude, $extendsTemp);
        if ($decoratorTexts !== []) {
            $header = implode(' ', $decoratorTexts) . ' ' . $header;
        }
        $lines = [$header . ' {'];
        foreach ($members as $member) {
            foreach (explode("\n", $member) as $line) {
                $lines[] = '  ' . $line;
            }
        }
        $lines[] = '}';
        $classExpression = implode("\n", $lines);

        $expressions = $this->statementLinesToExpressions($fieldKeyPrelude);
        if ($afterClassStaticAssignments !== []) {
            $classTemp = $this->allocateClassFieldTemp($fieldKeyTemps);
            $expressions[] = $classTemp . ' = ' . $classExpression;
            array_push($expressions, ...$this->statementLinesToExpressions(
                $this->staticFieldAssignmentStatements($classTemp, $afterClassStaticAssignments),
            ));
            $expressions[] = $classTemp;
        } elseif ($expressions !== []) {
            $expressions[] = $classExpression;
        }

        return [
            'expression' => $expressions === [] ? $classExpression : '(' . implode(', ', $expressions) . ')',
            'bodyClose' => $bodyClose,
        ];
    }

    private function classExpressionBodyOpen(int $classIndex, int $effectiveEnd): ?int
    {
        $parenDepth = 0;
        $bracketDepth = 0;
        for ($i = $classIndex + 1; $i <= $effectiveEnd; $i++) {
            $text = $this->tokens[$i]->text;
            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                $parenDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                $bracketDepth--;
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $text === '{') {
                return $i;
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $text === ';') {
                return null;
            }
        }

        return null;
    }

    private function classExpressionSeparator(string $prefix): string
    {
        return $prefix === '' || preg_match('/[\(\[,:]$/', $prefix) === 1 ? '' : ' ';
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerAsyncGeneratorFunctionStatementAt(int $start, int $effectiveEnd): ?array
    {
        if (!$this->lowerAsyncGenerators) {
            return null;
        }

        $output = '';
        $cursor = $start;
        $matched = false;
        $needsAssignmentSemicolon = false;
        for ($async = $start; $async <= $effectiveEnd; $async++) {
            $function = $this->asyncGeneratorFunctionCandidateAt($start, $async, $effectiveEnd);
            if ($function === null) {
                continue;
            }

            if ($cursor <= $async - 1) {
                $output .= $this->printRuntimeTokenRange($cursor, $async - 1, $start);
            }

            $header = $this->asyncGeneratorFunctionRuntimeHeader($function);
            $replacement = $this->printAsyncGeneratorFunctionLike($header, $function['bodyOpen'] + 1, $function['bodyClose']);
            $output .= $this->asyncGeneratorExpressionSeparator(rtrim($output)) . $replacement;
            $cursor = $function['bodyClose'] + 1;
            $matched = true;

            $previous = $this->previousSignificantTokenIndex($async - 1);
            if ($previous !== null && ($this->tokens[$previous] ?? null)?->text === '=') {
                $needsAssignmentSemicolon = true;
            }

            $async = $function['bodyClose'];
        }

        if (!$matched) {
            return null;
        }

        if ($cursor <= $effectiveEnd) {
            $output .= $this->printRuntimeTokenRange($cursor, $effectiveEnd, $start);
        }

        if (($needsAssignmentSemicolon || $this->runtimeStatementNeedsSemicolon($start, $effectiveEnd, $output))
            && !str_ends_with($output, ';')
        ) {
            $output .= ';';
        }

        return [$output, $effectiveEnd];
    }

    /**
     * @return array{async:int, star:int, paramsOpen:int, paramsClose:int, bodyOpen:int, bodyClose:int}|null
     */
    private function asyncGeneratorFunctionCandidateAt(int $start, int $async, int $effectiveEnd): ?array
    {
        if (($this->tokens[$async] ?? null)?->text !== 'async') {
            return null;
        }

        $isParenthesizedDefaultExport = false;
        $isParenthesizedExpression = false;
        $isNestedExpression = false;
        if (!$this->isTopLevelInRange($start, $async)) {
            $isParenthesizedDefaultExport = $this->isParenthesizedDefaultExportAsyncGeneratorExpression($start, $async);
            $isParenthesizedExpression = $this->isParenthesizedAsyncGeneratorExpression($start, $async);
            $isNestedExpression = $this->isNestedAsyncGeneratorExpression($start, $async);
            if (!$isParenthesizedDefaultExport && !$isParenthesizedExpression && !$isNestedExpression) {
                return null;
            }
        }

        $previous = $this->previousSignificantTokenIndex($async - 1);
        $previousText = $previous === null ? null : ($this->tokens[$previous] ?? null)?->text;
        if ($async !== $start
            && $previousText !== null
            && !in_array($previousText, ['=', 'export', 'default'], true)
            && !($isParenthesizedDefaultExport && $previousText === '(')
            && !($isParenthesizedExpression && $previousText === '(')
            && !$isNestedExpression
        ) {
            return null;
        }

        return $this->asyncGeneratorFunctionAt($async, $effectiveEnd);
    }

    private function isParenthesizedDefaultExportAsyncGeneratorExpression(int $start, int $async): bool
    {
        if (($this->tokens[$start] ?? null)?->text !== 'export'
            || ($this->tokens[$start + 1] ?? null)?->text !== 'default'
            || $start + 2 >= $async
        ) {
            return false;
        }

        for ($cursor = $start + 2; $cursor < $async; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text !== '(') {
                return false;
            }
        }

        return ($this->tokens[$async - 1] ?? null)?->text === '(';
    }

    private function isParenthesizedAsyncGeneratorExpression(int $start, int $async): bool
    {
        $previous = $this->previousSignificantTokenIndex($async - 1);
        if ($previous === null || ($this->tokens[$previous] ?? null)?->text !== '(') {
            return false;
        }

        $close = $this->findMatchingPunctuator($previous, '(', ')');
        if ($close <= $async) {
            return false;
        }

        return $close <= $this->withoutTrailingSemicolon($this->findStatementEndForLowering($start));
    }

    private function isNestedAsyncGeneratorExpression(int $start, int $async): bool
    {
        $previous = $this->previousSignificantTokenIndex($async - 1);
        if ($previous === null) {
            return false;
        }

        $previousText = ($this->tokens[$previous] ?? null)?->text;
        if ($previousText === '[') {
            $close = $this->findMatchingPunctuator($previous, '[', ']');

            return $close <= $this->withoutTrailingSemicolon($this->findStatementEndForLowering($start));
        }

        if ($previousText === ':') {
            $open = $this->enclosingOpenPunctuator($async, '{', '}', $start);
            if ($open === null || !$this->isObjectLiteralExpressionOpen($open)) {
                return false;
            }

            $close = $this->findMatchingPunctuator($open, '{', '}');

            return $close <= $this->withoutTrailingSemicolon($this->findStatementEndForLowering($start));
        }

        if ($previousText !== ',') {
            return false;
        }

        return $this->enclosingOpenPunctuator($async, '(', ')', $start) !== null
            || $this->enclosingOpenPunctuator($async, '[', ']', $start) !== null;
    }

    private function isObjectLiteralExpressionOpen(int $open): bool
    {
        $before = $this->previousSignificantTokenIndex($open - 1);
        if ($before === null) {
            return false;
        }

        return in_array(($this->tokens[$before] ?? null)?->text, ['=', '(', '[', ',', ':', '?', '=>', 'default', 'return'], true);
    }

    private function asyncGeneratorExpressionSeparator(string $prefix): string
    {
        return $prefix === '' || preg_match('/[\(\[:]$/', $prefix) === 1 ? '' : ' ';
    }

    /**
     * @return array{async:int, star:int, paramsOpen:int, paramsClose:int, bodyOpen:int, bodyClose:int}|null
     */
    private function asyncGeneratorFunctionAt(int $async, int $effectiveEnd): ?array
    {
        if (($this->tokens[$async] ?? null)?->text !== 'async'
            || ($this->tokens[$async + 1] ?? null)?->text !== 'function'
            || $this->hasLineBreakBetween($async, $async + 1)
        ) {
            return null;
        }

        $star = $async + 2;
        if (($this->tokens[$star] ?? null)?->text !== '*') {
            return null;
        }

        $cursor = $star + 1;
        if (($this->tokens[$cursor] ?? null)?->kind === 'identifier') {
            $cursor++;
        }
        if (($this->tokens[$cursor] ?? null)?->text !== '(') {
            return null;
        }

        $paramsOpen = $cursor;
        $paramsClose = $this->findMatchingPunctuator($paramsOpen, '(', ')');
        if ($paramsClose > $effectiveEnd) {
            return null;
        }

        $cursor = $paramsClose + 1;
        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $effectiveEnd, ['{']);
        }
        if (($this->tokens[$cursor] ?? null)?->text !== '{') {
            return null;
        }

        $bodyOpen = $cursor;
        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd) {
            return null;
        }

        return [
            'async' => $async,
            'star' => $star,
            'paramsOpen' => $paramsOpen,
            'paramsClose' => $paramsClose,
            'bodyOpen' => $bodyOpen,
            'bodyClose' => $bodyClose,
        ];
    }

    /**
     * @param array{async:int, star:int, paramsOpen:int, paramsClose:int, bodyOpen:int, bodyClose:int} $function
     */
    private function asyncGeneratorFunctionRuntimeHeader(array $function): string
    {
        $name = $function['star'] + 1 <= $function['paramsOpen'] - 1
            ? $this->printRuntimeTokenRange($function['star'] + 1, $function['paramsOpen'] - 1, $function['async'])
            : '';
        $params = $this->printRuntimeTokenRange($function['paramsOpen'], $function['paramsClose'], $function['async']);

        return 'function' . ($name === '' ? '' : ' ' . $name) . $params;
    }

    private function printAsyncGeneratorFunctionLike(string $header, int $bodyStart, int $bodyClose): string
    {
        $this->needsAsyncGeneratorHelperRuntime = true;
        [$bodyLines] = $this->lowerAsyncGeneratorBodyStatements($bodyStart, $bodyClose);

        $lines = [$header . ' {', '  return ' . $this->asyncGeneratorName . '(this, null, function* () {'];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '    ' . $part;
            }
        }
        $lines[] = '  });';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerFunctionBodyUsingStatementAt(int $start, int $effectiveEnd): ?array
    {
        $bodyOpen = $this->findTopLevelBlockOpenBeforeStatementEnd($start);
        if ($bodyOpen === null || $bodyOpen > $effectiveEnd || !$this->isFunctionLikeBodyHeader($start, $bodyOpen)) {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd) {
            return null;
        }

        [$bodyLines, $changed] = $this->lowerFunctionBodyUsingStatements(
            $bodyOpen + 1,
            $bodyClose,
            $this->isAsyncFunctionLikeHeader($start, $bodyOpen),
        );
        if (!$changed) {
            return null;
        }

        $header = rtrim(substr(
            $this->source,
            $this->tokens[$start]->offset,
            $this->tokens[$bodyOpen]->offset - $this->tokens[$start]->offset,
        ));
        $lines = [$header . ' {'];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '}' . ($this->functionLikeStatementNeedsSemicolon($start) ? ';' : '');

        return [implode("\n", $lines), $bodyClose];
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerSwitchCaseBlockUsingStatementAt(int $start, int $effectiveEnd): ?array
    {
        if (!$this->lowerUsingDeclarations
            || ($this->tokens[$start] ?? null)?->text !== 'switch'
            || ($this->tokens[$start + 1] ?? null)?->text !== '('
        ) {
            return null;
        }

        $conditionClose = $this->findMatchingPunctuator($start + 1, '(', ')');
        $bodyOpen = $conditionClose + 1;
        if (($this->tokens[$bodyOpen] ?? null)?->text !== '{') {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd || $bodyClose !== $effectiveEnd) {
            return null;
        }

        $copyOffset = $this->tokens[$start]->offset;
        $output = '';
        $changed = false;
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;

        for ($cursor = $bodyOpen + 1; $cursor < $bodyClose; $cursor++) {
            $text = $this->tokens[$cursor]->text;
            $atCaseClauseLevel = $parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0;

            if ($atCaseClauseLevel && $text === 'for') {
                $statementEnd = $this->switchCaseClauseStatementEnd($cursor, $bodyClose);
                $effectiveStatementEnd = $this->withoutTrailingSemicolon($statementEnd);
                $this->validateForUsingStatement($cursor, $effectiveStatementEnd);
                $forUsing = $this->lowerForUsingStatementAt($cursor, $effectiveStatementEnd);
                if ($forUsing !== null) {
                    [$forOutput, $forEnd] = $forUsing;
                    $output .= substr($this->source, $copyOffset, $this->tokens[$cursor]->offset - $copyOffset);
                    $output .= $forOutput;
                    $copyOffset = $this->tokens[$forEnd]->offset + strlen($this->tokens[$forEnd]->text);
                    $changed = true;
                    $cursor = $forEnd;
                    continue;
                }
            }

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
            if ($text === '}' && $braceDepth > 0) {
                $braceDepth--;
                continue;
            }
            if ($text !== '{') {
                continue;
            }

            if ($parenDepth !== 0 || $bracketDepth !== 0 || $braceDepth !== 0) {
                $braceDepth++;
                continue;
            }

            $blockClose = $this->findMatchingPunctuator($cursor, '{', '}');
            if (!$this->containsUsingDeclarationInRange($cursor + 1, $blockClose - 1)) {
                $cursor = $blockClose;
                continue;
            }

            [$bodyLines, $blockChanged] = $this->lowerBlockScopedUsingStatements($cursor + 1, $blockClose);
            if (!$blockChanged) {
                $cursor = $blockClose;
                continue;
            }

            $output .= substr($this->source, $copyOffset, $this->tokens[$cursor]->offset - $copyOffset);
            $output .= $this->formatBlockScopedUsingLines($bodyLines);
            $copyOffset = $this->tokens[$blockClose]->offset + strlen($this->tokens[$blockClose]->text);
            $changed = true;
            $cursor = $blockClose;
        }

        if (!$changed) {
            return null;
        }

        $output .= substr(
            $this->source,
            $copyOffset,
            $this->tokens[$bodyClose]->offset + strlen($this->tokens[$bodyClose]->text) - $copyOffset
        );

        return [trim($output), $bodyClose];
    }

    private function switchCaseClauseStatementEnd(int $start, int $bodyClose): int
    {
        $depth = 0;
        $canEndAtTopLevelCloseBrace = $this->statementCanEndAtTopLevelCloseBrace($start);
        for ($i = $start; $i < $bodyClose; $i++) {
            $text = $this->tokens[$i]->text;
            if ($i > $start && $depth === 0 && ($text === 'case' || $text === 'default')) {
                return max($start, $this->previousSignificantTokenIndex($i - 1) ?? ($i - 1));
            }
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                if ($canEndAtTopLevelCloseBrace && $depth === 0 && $text === '}') {
                    return $i;
                }
                continue;
            }
            if ($text === ';' && $depth === 0) {
                return $i;
            }
        }

        return max($start, $bodyClose - 1);
    }

    /**
     * @param list<string> $bodyLines
     */
    private function formatBlockScopedUsingLines(array $bodyLines): string
    {
        $lines = ['{'];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function containsUsingDeclarationInRange(int $start, int $end): bool
    {
        for ($cursor = $start; $cursor <= $end; $cursor++) {
            if ($this->isUsingDeclarationStart($cursor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerBlockScopedUsingStatementAt(int $start, int $effectiveEnd): ?array
    {
        if (!$this->lowerUsingDeclarations) {
            return null;
        }

        $first = ($this->tokens[$start] ?? null)?->text;
        if (!in_array($first, ['{', 'if', 'while', 'for'], true)) {
            return null;
        }

        $bodyOpen = $first === '{' ? $start : $this->findTopLevelBlockOpenBeforeStatementEnd($start);
        if ($bodyOpen === null || $bodyOpen > $effectiveEnd) {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd || $bodyClose !== $effectiveEnd) {
            return null;
        }

        [$bodyLines, $changed] = $this->lowerBlockScopedUsingStatements($bodyOpen + 1, $bodyClose);
        if (!$changed) {
            return null;
        }

        $header = trim(substr(
            $this->source,
            $this->tokens[$start]->offset,
            $this->tokens[$bodyOpen]->offset - $this->tokens[$start]->offset,
        ));

        $lines = [$header === '' ? '{' : $header . ' {'];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '}';

        return [implode("\n", $lines), $bodyClose];
    }

    private function lowerObjectLiteralMethodUsingStatementAt(int $start, int $effectiveEnd): ?string
    {
        $methods = [];
        for ($cursor = $start; $cursor <= $effectiveEnd; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text !== '{') {
                continue;
            }

            $method = $this->objectLiteralMethodBodyAt($start, $cursor, $effectiveEnd);
            if ($method === null) {
                continue;
            }

            if ($this->lowerAsyncGenerators && $method['asyncGenerator']) {
                [$bodyLines, $changed] = $this->lowerAsyncGeneratorBodyStatements(
                    $cursor + 1,
                    $method['bodyClose'],
                );
            } else {
                [$bodyLines, $changed] = $this->lowerFunctionBodyUsingStatements(
                    $cursor + 1,
                    $method['bodyClose'],
                    $method['async'],
                );
            }
            if (!$changed) {
                continue;
            }

            $methods[] = [
                'methodStart' => $method['methodStart'],
                'bodyOpen' => $cursor,
                'bodyClose' => $method['bodyClose'],
                'bodyLines' => $bodyLines,
                'asyncGenerator' => $this->lowerAsyncGenerators && $method['asyncGenerator'],
            ];
            $cursor = $method['bodyClose'];
        }

        if ($methods === []) {
            return null;
        }

        $output = '';
        $cursor = $start;
        foreach ($methods as $method) {
            if ($cursor <= $method['methodStart'] - 1) {
                $output .= $this->printRuntimeTokenRange($cursor, $method['methodStart'] - 1, $start);
            }

            $header = $method['asyncGenerator']
                ? $this->asyncGeneratorMethodRuntimeHeader($method['methodStart'], $method['bodyOpen'] - 1)
                : rtrim($this->printClassMethodHeaderRuntimeRange($method['methodStart'], $method['bodyOpen'] - 1));
            if ($method['asyncGenerator']) {
                $this->needsAsyncGeneratorHelperRuntime = true;
                $output .= $header . " {\n";
                $output .= '  return ' . $this->asyncGeneratorName . "(this, null, function* () {\n";
                foreach ($method['bodyLines'] as $line) {
                    foreach (explode("\n", $line) as $part) {
                        if ($part === '') {
                            continue;
                        }
                        $output .= '    ' . $part . "\n";
                    }
                }
                $output .= "  });\n";
                $output .= '}';
            } else {
                $output .= $header . " {\n";
                foreach ($method['bodyLines'] as $line) {
                    foreach (explode("\n", $line) as $part) {
                        if ($part === '') {
                            continue;
                        }
                        $output .= '  ' . $part . "\n";
                    }
                }
                $output .= '}';
            }
            $cursor = $method['bodyClose'] + 1;
        }

        if ($cursor <= $effectiveEnd) {
            $output .= $this->printRuntimeTokenRange($cursor, $effectiveEnd, $start);
        }

        if ($output !== '' && $this->runtimeStatementNeedsSemicolon($start, $effectiveEnd, $output)) {
            $output .= ';';
        }

        return $output;
    }

    /**
     * @return array{methodStart:int, bodyClose:int, async:bool, asyncGenerator:bool}|null
     */
    private function objectLiteralMethodBodyAt(int $statementStart, int $bodyOpen, int $effectiveEnd): ?array
    {
        $paramsOpen = $this->objectMethodParamsOpenBeforeBody($statementStart, $bodyOpen);
        if ($paramsOpen === null) {
            return null;
        }

        $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
        if ($bodyClose > $effectiveEnd) {
            return null;
        }

        $nameEnd = $this->previousSignificantTokenIndex($paramsOpen - 1);
        if ($nameEnd === null) {
            return null;
        }

        $name = $this->tokens[$nameEnd] ?? null;
        if ($name === null) {
            return null;
        }

        if ($name->text === ']') {
            $nameStart = $this->matchingOpenPunctuator($nameEnd, '[', ']', $statementStart);
            if ($nameStart === null) {
                return null;
            }
        } elseif ($name->kind === 'identifier'
            || $name->kind === 'private_identifier'
            || $name->kind === 'string'
            || $name->kind === 'number'
        ) {
            $nameStart = $nameEnd;
        } else {
            return null;
        }

        $methodStart = $nameStart;
        $isGenerator = false;
        $beforeName = $this->previousSignificantTokenIndex($nameStart - 1);
        if ($beforeName !== null && ($this->tokens[$beforeName] ?? null)?->text === '*') {
            $methodStart = $beforeName;
            $isGenerator = true;
            $beforeName = $this->previousSignificantTokenIndex($beforeName - 1);
        }

        $isAsync = false;
        if ($beforeName !== null
            && ($this->tokens[$beforeName] ?? null)?->text === 'async'
            && !$this->hasLineBreakBetween($beforeName, $methodStart)
            && ($this->tokens[$beforeName + 1] ?? null)?->text !== '('
        ) {
            $isAsync = true;
            $methodStart = $beforeName;
        }

        $beforeMethod = $this->previousSignificantTokenIndex($methodStart - 1);
        if ($beforeMethod !== null && !in_array(($this->tokens[$beforeMethod] ?? null)?->text, ['{', ',', ';'], true)) {
            return null;
        }

        return [
            'methodStart' => $methodStart,
            'bodyClose' => $bodyClose,
            'async' => $isAsync,
            'asyncGenerator' => $isAsync && $isGenerator,
        ];
    }

    private function objectMethodParamsOpenBeforeBody(int $statementStart, int $bodyOpen): ?int
    {
        $candidate = null;
        for ($cursor = $statementStart; $cursor < $bodyOpen; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text !== '(') {
                continue;
            }

            $paramsClose = $this->findMatchingPunctuator($cursor, '(', ')');
            if ($paramsClose >= $bodyOpen) {
                continue;
            }

            $afterParams = $paramsClose + 1;
            if (($this->tokens[$afterParams] ?? null)?->text === ':') {
                $afterParams = $this->skipTypeExpression($afterParams + 1, $bodyOpen - 1, ['{']);
            }

            if ($afterParams === $bodyOpen) {
                $candidate = $cursor;
            }
        }

        return $candidate;
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerForUsingStatementAt(int $start, int $effectiveEnd): ?array
    {
        if (!$this->lowerUsingDeclarations) {
            return null;
        }

        $loop = $this->parseForUsingOfLoop($start, $effectiveEnd);
        if ($loop === null) {
            return null;
        }

        $tempName = '_' . $loop['name'];
        [$stackName] = $this->nextUsingScopeNames();
        $iterable = $this->printTokenRange($loop['iterableStart'], $loop['iterableEnd']);
        $bodyLines = [
            'const ' . $loop['name'] . ' = ' . $this->usingHelperUsingName . '(' . $stackName . ', ' . $tempName . ($loop['awaitUsing'] ? ', true' : '') . ');',
        ];
        $hasAwaitUsing = $loop['awaitUsing'];

        if ($loop['bodyIsBlock']) {
            [$nestedLines, $nestedHasAwaitUsing] = $this->lowerUsingLoopBodyLines(
                $loop['bodyStart'] + 1,
                $loop['bodyEnd'],
                $stackName,
            );
            foreach ($nestedLines as $line) {
                $bodyLines[] = $line;
            }
            $hasAwaitUsing = $hasAwaitUsing || $nestedHasAwaitUsing;
        } elseif ($loop['bodyStart'] <= $loop['bodyEnd']) {
            $line = $this->containsErasableTypeScriptSyntax($loop['bodyStart'], $loop['bodyEnd']) || $this->containsInlineableEnumReference($loop['bodyStart'], $loop['bodyEnd'])
                ? $this->printRuntimeStatement($loop['bodyStart'], $loop['bodyEnd'])
                : $this->originalStatementText($loop['bodyStart'], $loop['bodyEnd']);
            if ($line !== '') {
                $bodyLines[] = $line;
            }
        }

        $lines = [
            'for' . ($loop['forAwait'] ? ' await' : '') . ' (var ' . $tempName . ' of ' . $iterable . ') {',
        ];
        foreach ($this->usingHelperScopeLines($bodyLines, $hasAwaitUsing, $stackName) as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '}';

        return [implode("\n", $lines), $loop['bodyEnd']];
    }

    /**
     * @return array{
     *   forAwait:bool,
     *   awaitUsing:bool,
     *   name:string,
     *   iterableStart:int,
     *   iterableEnd:int,
     *   bodyStart:int,
     *   bodyEnd:int,
     *   bodyIsBlock:bool
     * }|null
     */
    private function parseForUsingOfLoop(int $start, int $effectiveEnd): ?array
    {
        if (($this->tokens[$start] ?? null)?->text !== 'for') {
            return null;
        }

        $cursor = $start + 1;
        $forAwait = false;
        if (($this->tokens[$cursor] ?? null)?->text === 'await') {
            $forAwait = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text !== '(') {
            return null;
        }

        $open = $cursor;
        $close = $this->findMatchingPunctuator($open, '(', ')');
        if ($close > $effectiveEnd) {
            return null;
        }

        $cursor = $open + 1;
        $awaitUsing = false;
        if (($this->tokens[$cursor] ?? null)?->text === 'await'
            && ($this->tokens[$cursor + 1] ?? null)?->text === 'using'
            && !$this->hasLineBreakBetween($cursor, $cursor + 1)
        ) {
            $awaitUsing = true;
            $cursor++;
        }

        if (($this->tokens[$cursor] ?? null)?->text !== 'using') {
            return null;
        }

        $name = $this->tokens[$cursor + 1] ?? null;
        if ($name?->kind !== 'identifier' || in_array($name->text, ['of', 'in'], true)) {
            return null;
        }
        if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
            throw new \InvalidArgumentException('Expected loop variable after TypeScript using declaration');
        }

        $cursor += 2;
        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $close - 1, ['of']);
        }

        if (($this->tokens[$cursor] ?? null)?->text !== 'of') {
            return null;
        }

        $iterableStart = $cursor + 1;
        $iterableEnd = $close - 1;
        if ($iterableStart > $iterableEnd) {
            throw new \InvalidArgumentException('Expected iterable after TypeScript using loop declaration');
        }

        $bodyStart = $close + 1;
        $bodyIsBlock = false;
        if (($this->tokens[$bodyStart] ?? null)?->text === '{') {
            $bodyEnd = $this->findMatchingPunctuator($bodyStart, '{', '}');
            if ($bodyEnd > $effectiveEnd) {
                return null;
            }
            $bodyIsBlock = true;
        } else {
            $bodyEnd = $effectiveEnd;
        }

        return [
            'forAwait' => $forAwait,
            'awaitUsing' => $awaitUsing,
            'name' => $name->text,
            'iterableStart' => $iterableStart,
            'iterableEnd' => $iterableEnd,
            'bodyStart' => $bodyStart,
            'bodyEnd' => $bodyEnd,
            'bodyIsBlock' => $bodyIsBlock,
        ];
    }

    /**
     * @return array{0:list<string>, 1:bool}
     */
    private function lowerUsingLoopBodyLines(int $start, int $bodyClose, string $stackName): array
    {
        $lines = [];
        $hasAwaitUsing = false;
        for ($cursor = $start; $cursor < $bodyClose; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            $statementEnd = $this->functionBodyStatementEnd($cursor, $bodyClose);
            $effectiveEnd = $this->withoutTrailingSemicolon($statementEnd);
            $this->validateForUsingStatement($cursor, $effectiveEnd);

            $forUsing = $this->lowerForUsingStatementAt($cursor, $effectiveEnd);
            if ($forUsing !== null) {
                [$forOutput, $forEnd] = $forUsing;
                $lines[] = $forOutput;
                $cursor = $forEnd;
                continue;
            }

            $switchCaseUsing = $this->lowerSwitchCaseBlockUsingStatementAt($cursor, $effectiveEnd);
            if ($switchCaseUsing !== null) {
                [$switchOutput, $switchEnd] = $switchCaseUsing;
                $lines[] = $switchOutput;
                $cursor = $switchEnd;
                continue;
            }

            $nested = $this->lowerBlockScopedUsingStatementAt($cursor, $effectiveEnd);
            if ($nested !== null) {
                [$nestedOutput, $nestedEnd] = $nested;
                $lines[] = $nestedOutput;
                $cursor = $nestedEnd;
                continue;
            }

            if ($this->isUsingDeclarationStart($cursor)) {
                $using = $this->parseUsingDeclaration($cursor, $effectiveEnd);
                $lines[] = 'const ' . $this->printUsingDeclarators($using['declarations'], true, $using['await'], $stackName) . ';';
                $hasAwaitUsing = $hasAwaitUsing || $using['await'];
                $cursor = $statementEnd;
                continue;
            }

            $line = $this->containsErasableTypeScriptSyntax($cursor, $statementEnd) || $this->containsInlineableEnumReference($cursor, $statementEnd)
                ? $this->printRuntimeStatement($cursor, $statementEnd)
                : $this->originalStatementText($cursor, $statementEnd);
            if ($line !== '') {
                $lines[] = $line;
            }
            $cursor = $statementEnd;
        }

        return [$lines, $hasAwaitUsing];
    }

    private function isFunctionLikeBodyHeader(int $start, int $bodyOpen): bool
    {
        for ($i = $start; $i < $bodyOpen; $i++) {
            $token = $this->tokens[$i] ?? null;
            if ($token === null || !$this->isTopLevelInRange($start, $i)) {
                continue;
            }

            if ($token->text === 'function' || $token->text === '=>') {
                return true;
            }
        }

        return false;
    }

    private function isAsyncFunctionLikeHeader(int $start, int $bodyOpen): bool
    {
        $header = substr(
            $this->source,
            $this->tokens[$start]->offset,
            $this->tokens[$bodyOpen]->offset - $this->tokens[$start]->offset,
        );

        return preg_match('/(?:^|[^A-Za-z0-9_$])async\s*(?:function\b|\()/u', $header) === 1;
    }

    private function functionLikeStatementNeedsSemicolon(int $start): bool
    {
        $first = ($this->tokens[$start] ?? null)?->text;
        $second = ($this->tokens[$start + 1] ?? null)?->text;
        $third = ($this->tokens[$start + 2] ?? null)?->text;
        $fourth = ($this->tokens[$start + 3] ?? null)?->text;

        return !($first === 'function'
            || ($first === 'async' && $second === 'function')
            || ($first === 'export' && ($second === 'function' || ($second === 'async' && $third === 'function')))
            || ($first === 'export' && $second === 'default' && ($third === 'function' || ($third === 'async' && $fourth === 'function'))));
    }

    /**
     * @return array{0:list<string>, 1:bool}
     */
    private function lowerFunctionBodyUsingStatements(int $start, int $bodyClose, bool $isAsyncFunction): array
    {
        $lines = [];
        $changed = false;
        $hasFunctionScopeUsing = false;
        $hasAwaitUsing = false;
        $stackName = null;
        for ($cursor = $start; $cursor < $bodyClose; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            $statementEnd = $this->functionBodyStatementEnd($cursor, $bodyClose);
            $effectiveEnd = $this->withoutTrailingSemicolon($statementEnd);
            $this->validateForUsingStatement($cursor, $effectiveEnd);

            $forUsing = $this->lowerForUsingStatementAt($cursor, $effectiveEnd);
            if ($forUsing !== null) {
                [$forOutput, $forEnd] = $forUsing;
                $lines[] = $forOutput;
                $changed = true;
                $cursor = $forEnd;
                continue;
            }

            $switchCaseUsing = $this->lowerUsingDeclarations ? $this->lowerSwitchCaseBlockUsingStatementAt($cursor, $effectiveEnd) : null;
            if ($switchCaseUsing !== null) {
                [$switchOutput, $switchEnd] = $switchCaseUsing;
                $lines[] = $switchOutput;
                $changed = true;
                $cursor = $switchEnd;
                continue;
            }

            $nested = $this->lowerUsingDeclarations ? $this->lowerBlockScopedUsingStatementAt($cursor, $effectiveEnd) : null;
            if ($nested !== null) {
                [$nestedOutput, $nestedEnd] = $nested;
                $lines[] = $nestedOutput;
                $changed = true;
                $cursor = $nestedEnd;
                continue;
            }

            if ($this->isUsingDeclarationStart($cursor)) {
                $using = $this->parseUsingDeclaration($cursor, $effectiveEnd);
                if ($using['await'] && !$isAsyncFunction) {
                    throw new \InvalidArgumentException('Cannot use await using inside a non-async function');
                }

                if ($this->lowerUsingDeclarations && !($this->minifySyntax && $this->usingDeclarationsCanSkipUsingMachinery($using))) {
                    if ($stackName === null) {
                        [$stackName] = $this->nextUsingScopeNames();
                    }
                    $lines[] = 'const ' . $this->printUsingDeclarators($using['declarations'], true, $using['await'], $stackName) . ';';
                    $hasFunctionScopeUsing = true;
                    $hasAwaitUsing = $hasAwaitUsing || $using['await'];
                } else {
                    $lines[] = $this->printUsingDeclaration($using, allowHelperLowering: false);
                }
                $changed = true;
                $cursor = $statementEnd;
                continue;
            }

            $line = $this->containsErasableTypeScriptSyntax($cursor, $statementEnd) || $this->containsInlineableEnumReference($cursor, $statementEnd)
                ? $this->printRuntimeStatement($cursor, $statementEnd)
                : $this->originalStatementText($cursor, $statementEnd);
            if ($line !== '') {
                $lines[] = $line;
            }
            $cursor = $statementEnd;
        }

        if ($hasFunctionScopeUsing) {
            return [$this->usingHelperScopeLines($lines, $hasAwaitUsing, $stackName), true];
        }

        return [$lines, $changed];
    }

    /**
     * @return array{0:list<string>, 1:bool, 2:bool}
     */
    private function lowerBlockScopedUsingStatements(int $start, int $bodyClose): array
    {
        $lines = [];
        $changed = false;
        $hasAwaitUsing = false;
        [$stackName] = $this->nextUsingScopeNames();
        for ($cursor = $start; $cursor < $bodyClose; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            $statementEnd = $this->functionBodyStatementEnd($cursor, $bodyClose);
            $effectiveEnd = $this->withoutTrailingSemicolon($statementEnd);
            $this->validateForUsingStatement($cursor, $effectiveEnd);

            $forUsing = $this->lowerForUsingStatementAt($cursor, $effectiveEnd);
            if ($forUsing !== null) {
                [$forOutput, $forEnd] = $forUsing;
                $lines[] = $forOutput;
                $changed = true;
                $cursor = $forEnd;
                continue;
            }

            $switchCaseUsing = $this->lowerSwitchCaseBlockUsingStatementAt($cursor, $effectiveEnd);
            if ($switchCaseUsing !== null) {
                [$switchOutput, $switchEnd] = $switchCaseUsing;
                $lines[] = $switchOutput;
                $changed = true;
                $cursor = $switchEnd;
                continue;
            }

            $nested = $this->lowerBlockScopedUsingStatementAt($cursor, $effectiveEnd);
            if ($nested !== null) {
                [$nestedOutput, $nestedEnd] = $nested;
                $lines[] = $nestedOutput;
                $changed = true;
                $cursor = $nestedEnd;
                continue;
            }

            if ($this->isUsingDeclarationStart($cursor)) {
                $using = $this->parseUsingDeclaration($cursor, $effectiveEnd);
                $lines[] = 'const ' . $this->printUsingDeclarators($using['declarations'], true, $using['await'], $stackName) . ';';
                $hasAwaitUsing = $hasAwaitUsing || $using['await'];
                $changed = true;
                $cursor = $statementEnd;
                continue;
            }

            $line = $this->containsErasableTypeScriptSyntax($cursor, $statementEnd) || $this->containsInlineableEnumReference($cursor, $statementEnd)
                ? $this->printRuntimeStatement($cursor, $statementEnd)
                : $this->originalStatementText($cursor, $statementEnd);
            if ($line !== '') {
                $lines[] = $line;
            }
            $cursor = $statementEnd;
        }

        if (!$changed) {
            return [$lines, false, false];
        }

        return [$this->usingHelperScopeLines($lines, $hasAwaitUsing, $stackName), true, $hasAwaitUsing];
    }

    /**
     * @return array{0:list<string>, 1:bool}
     */
    private function lowerAsyncGeneratorBodyStatements(int $start, int $bodyClose): array
    {
        $lines = [];
        $changed = false;
        $hasFunctionScopeUsing = false;
        $hasAwaitUsing = false;
        $stackName = null;
        for ($cursor = $start; $cursor < $bodyClose; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            $statementEnd = $this->functionBodyStatementEnd($cursor, $bodyClose);
            $effectiveEnd = $this->withoutTrailingSemicolon($statementEnd);
            $this->validateForUsingStatement($cursor, $effectiveEnd);

            $forAwait = $this->lowerAsyncGeneratorForAwaitStatementAt($cursor, $effectiveEnd);
            if ($forAwait !== null) {
                [$forOutput, $forEnd] = $forAwait;
                $lines[] = $forOutput;
                $changed = true;
                $cursor = $forEnd;
                continue;
            }

            if ($this->isUsingDeclarationStart($cursor)) {
                $using = $this->parseUsingDeclaration($cursor, $effectiveEnd);
                if ($stackName === null) {
                    [$stackName] = $this->nextUsingScopeNames();
                }
                $lines[] = 'const ' . $this->printAsyncGeneratorUsingDeclarators($using['declarations'], $using['await'], $stackName) . ';';
                $hasFunctionScopeUsing = true;
                $hasAwaitUsing = $hasAwaitUsing || $using['await'];
                $changed = true;
                $cursor = $statementEnd;
                continue;
            }

            $line = $this->printAsyncGeneratorStatement($cursor, $statementEnd);
            if ($line !== '') {
                $lines[] = $line;
            }
            $cursor = $statementEnd;
        }

        if ($hasFunctionScopeUsing) {
            return [$this->asyncGeneratorUsingHelperScopeLines($lines, $hasAwaitUsing, $stackName), true];
        }

        return [$lines, $changed || $this->lowerAsyncGenerators];
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function lowerAsyncGeneratorForAwaitStatementAt(int $start, int $effectiveEnd): ?array
    {
        $loop = $this->parseAsyncGeneratorForAwaitLoop($start, $effectiveEnd);
        if ($loop === null) {
            return null;
        }

        [$iterName, $moreName, $tempName, $errorName] = $this->nextAsyncGeneratorForAwaitNames();
        $iterable = $this->printAsyncGeneratorExpression($loop['iterableStart'], $loop['iterableEnd']);
        $bodyLines = [];
        if ($loop['using']) {
            $bodyLines[] = 'var _' . $loop['name'] . ' = ' . $tempName . '.value;';
            [$stackName] = $this->nextUsingScopeNames();
            $nestedLines = [
                'const ' . $loop['name'] . ' = ' . $this->usingHelperUsingName . '(' . $stackName . ', _' . $loop['name'] . ($loop['awaitUsing'] ? ', true' : '') . ');',
            ];
            if ($loop['bodyIsBlock']) {
                [$innerLines] = $this->lowerAsyncGeneratorBodyStatements($loop['bodyStart'] + 1, $loop['bodyEnd']);
                foreach ($innerLines as $line) {
                    $nestedLines[] = $line;
                }
            } elseif ($loop['bodyStart'] <= $loop['bodyEnd']) {
                $nestedLines[] = $this->printAsyncGeneratorStatement($loop['bodyStart'], $loop['bodyEnd']);
            }
            foreach ($this->asyncGeneratorUsingHelperScopeLines($nestedLines, $loop['awaitUsing'], $stackName) as $line) {
                $bodyLines[] = $line;
            }
        } else {
            $bodyLines[] = $loop['kind'] . ' ' . $loop['name'] . ' = ' . $tempName . '.value;';
            if ($loop['bodyIsBlock']) {
                [$innerLines] = $this->lowerAsyncGeneratorBodyStatements($loop['bodyStart'] + 1, $loop['bodyEnd']);
                foreach ($innerLines as $line) {
                    $bodyLines[] = $line;
                }
            } elseif ($loop['bodyStart'] <= $loop['bodyEnd']) {
                $bodyLines[] = $this->printAsyncGeneratorStatement($loop['bodyStart'], $loop['bodyEnd']);
            }
        }

        $lines = [
            'try {',
            '  for (var ' . $iterName . ' = ' . $this->asyncGeneratorForAwaitName . '(' . $iterable . '), ' . $moreName . ', ' . $tempName . ', ' . $errorName . '; ' . $moreName . ' = !(' . $tempName . ' = yield new ' . $this->asyncGeneratorAwaitName . '(' . $iterName . '.next())).done; ' . $moreName . ' = false) {',
        ];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '    ' . $part;
            }
        }
        $lines[] = '  }';
        $lines[] = '} catch (' . $tempName . ') {';
        $lines[] = '  ' . $errorName . ' = [' . $tempName . '];';
        $lines[] = '} finally {';
        $lines[] = '  try {';
        $lines[] = '    ' . $moreName . ' && (' . $tempName . ' = ' . $iterName . '.return) && (yield new ' . $this->asyncGeneratorAwaitName . '(' . $tempName . '.call(' . $iterName . ')));';
        $lines[] = '  } finally {';
        $lines[] = '    if (' . $errorName . ')';
        $lines[] = '      throw ' . $errorName . '[0];';
        $lines[] = '  }';
        $lines[] = '}';
        $this->needsAsyncGeneratorHelperRuntime = true;

        return [implode("\n", $lines), $loop['bodyEnd']];
    }

    /**
     * @return array{
     *   using:bool,
     *   awaitUsing:bool,
     *   kind:string,
     *   name:string,
     *   iterableStart:int,
     *   iterableEnd:int,
     *   bodyStart:int,
     *   bodyEnd:int,
     *   bodyIsBlock:bool
     * }|null
     */
    private function parseAsyncGeneratorForAwaitLoop(int $start, int $effectiveEnd): ?array
    {
        if (($this->tokens[$start] ?? null)?->text !== 'for'
            || ($this->tokens[$start + 1] ?? null)?->text !== 'await'
            || ($this->tokens[$start + 2] ?? null)?->text !== '('
        ) {
            return null;
        }

        $open = $start + 2;
        $close = $this->findMatchingPunctuator($open, '(', ')');
        if ($close > $effectiveEnd) {
            return null;
        }

        $cursor = $open + 1;
        $using = false;
        $awaitUsing = false;
        $kind = 'let';
        if (($this->tokens[$cursor] ?? null)?->text === 'await'
            && ($this->tokens[$cursor + 1] ?? null)?->text === 'using'
            && !$this->hasLineBreakBetween($cursor, $cursor + 1)
        ) {
            $using = true;
            $awaitUsing = true;
            $cursor += 2;
        } elseif (($this->tokens[$cursor] ?? null)?->text === 'using') {
            $using = true;
            $cursor++;
        } elseif (in_array(($this->tokens[$cursor] ?? null)?->text, ['let', 'const', 'var'], true)) {
            $kind = $this->tokens[$cursor]->text;
            $cursor++;
        } else {
            return null;
        }

        $name = $this->tokens[$cursor] ?? null;
        if ($name?->kind !== 'identifier') {
            return null;
        }
        $cursor++;

        if (($this->tokens[$cursor] ?? null)?->text === ':') {
            $cursor = $this->skipTypeExpression($cursor + 1, $close - 1, ['of']);
        }
        if (($this->tokens[$cursor] ?? null)?->text !== 'of') {
            return null;
        }

        $iterableStart = $cursor + 1;
        $iterableEnd = $close - 1;
        if ($iterableStart > $iterableEnd) {
            throw new \InvalidArgumentException('Expected iterable after for-await declaration');
        }

        $bodyStart = $close + 1;
        $bodyIsBlock = false;
        if (($this->tokens[$bodyStart] ?? null)?->text === '{') {
            $bodyEnd = $this->findMatchingPunctuator($bodyStart, '{', '}');
            if ($bodyEnd > $effectiveEnd) {
                return null;
            }
            $bodyIsBlock = true;
        } else {
            $bodyEnd = $effectiveEnd;
        }

        return [
            'using' => $using,
            'awaitUsing' => $awaitUsing,
            'kind' => $kind,
            'name' => $name->text,
            'iterableStart' => $iterableStart,
            'iterableEnd' => $iterableEnd,
            'bodyStart' => $bodyStart,
            'bodyEnd' => $bodyEnd,
            'bodyIsBlock' => $bodyIsBlock,
        ];
    }

    /**
     * @return array{0:string, 1:string, 2:string, 3:string}
     */
    private function nextAsyncGeneratorForAwaitNames(): array
    {
        $this->asyncGeneratorForAwaitCounter++;
        $suffix = $this->asyncGeneratorForAwaitCounter === 1 ? '' : (string) $this->asyncGeneratorForAwaitCounter;

        return ['iter' . $suffix, 'more' . $suffix, 'temp' . $suffix, 'error' . $suffix];
    }

    private function functionBodyStatementEnd(int $start, int $bodyClose): int
    {
        if ($this->isUsingDeclarationStart($start)) {
            return min($this->findUsingDeclarationEnd($start), $bodyClose - 1);
        }

        $depth = 0;
        $canEndAtTopLevelCloseBrace = $this->statementCanEndAtTopLevelCloseBrace($start);
        for ($i = $start; $i < $bodyClose; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                if ($canEndAtTopLevelCloseBrace && $depth === 0 && $text === '}') {
                    return $i;
                }
                continue;
            }
            if ($text === ';' && $depth === 0) {
                return $i;
            }
        }

        return max($start, $bodyClose - 1);
    }

    private function statementCanEndAtTopLevelCloseBrace(int $start): bool
    {
        $first = ($this->tokens[$start] ?? null)?->text;
        if (in_array($first, ['{', 'if', 'for', 'while', 'switch', 'try', 'do', 'class', 'function'], true)) {
            return true;
        }

        if ($first !== 'export') {
            return false;
        }

        $second = ($this->tokens[$start + 1] ?? null)?->text;
        $third = ($this->tokens[$start + 2] ?? null)?->text;

        return in_array($second, ['class', 'function'], true)
            || ($second === 'default' && in_array($third, ['class', 'function'], true))
            || ($second === 'async' && $third === 'function');
    }

    private function validateForUsingStatement(int $start, int $end): void
    {
        if (($this->tokens[$start] ?? null)?->text !== 'for') {
            return;
        }

        $open = null;
        if (($this->tokens[$start + 1] ?? null)?->text === '(') {
            $open = $start + 1;
        } elseif (($this->tokens[$start + 1] ?? null)?->text === 'await'
            && ($this->tokens[$start + 2] ?? null)?->text === '('
        ) {
            $open = $start + 2;
        }
        if ($open === null || $open > $end) {
            return;
        }

        $close = $this->findMatchingPunctuator($open, '(', ')');
        if ($close > $end || $open + 1 >= $close) {
            return;
        }

        $isAwaitUsing = false;
        $using = $open + 1;
        if (($this->tokens[$using] ?? null)?->text === 'await'
            && ($this->tokens[$using + 1] ?? null)?->text === 'using'
            && !$this->hasLineBreakBetween($using, $using + 1)
        ) {
            $isAwaitUsing = true;
            $using++;
        }

        if (($this->tokens[$using] ?? null)?->text !== 'using') {
            return;
        }

        $name = $this->tokens[$using + 1] ?? null;
        if ($name?->kind !== 'identifier' || $name->text === 'of' || $name->text === 'in') {
            return;
        }
        if ($this->hasLineBreakBetween($using, $using + 1)) {
            throw new \InvalidArgumentException('Expected loop variable after TypeScript using declaration');
        }

        $depth = 0;
        $of = null;
        $in = null;
        $semicolon = null;
        $initializer = null;
        for ($i = $using + 2; $i < $close; $i++) {
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
            if ($text === 'of') {
                $of = $i;
                break;
            }
            if ($text === 'in') {
                $in = $i;
                break;
            }
            if ($text === ';') {
                $semicolon = $i;
                break;
            }
            if ($text === '=' && $initializer === null) {
                $initializer = $i;
            }
        }

        if ($in !== null) {
            throw new \InvalidArgumentException(($isAwaitUsing ? '"await using"' : '"using"') . ' declarations are not allowed here');
        }

        if ($of !== null) {
            if ($initializer !== null && $initializer < $of) {
                throw new \InvalidArgumentException('for-of loop variables cannot have an initializer');
            }

            return;
        }

        if ($isAwaitUsing) {
            throw new \InvalidArgumentException('"await using" declarations are not allowed here');
        }

        if ($semicolon !== null && $initializer === null) {
            throw new \InvalidArgumentException('The declaration "' . $name->text . '" must be initialized');
        }
    }

    private function validateSwitchCaseUsingDeclarations(): void
    {
        $count = count($this->tokens);
        for ($switch = 0; $switch < $count; $switch++) {
            if (($this->tokens[$switch] ?? null)?->text !== 'switch'
                || ($this->tokens[$switch + 1] ?? null)?->text !== '('
            ) {
                continue;
            }

            $conditionClose = $this->findMatchingPunctuator($switch + 1, '(', ')');
            $bodyOpen = $conditionClose + 1;
            if (($this->tokens[$bodyOpen] ?? null)?->text !== '{') {
                continue;
            }

            $bodyClose = $this->findMatchingPunctuator($bodyOpen, '{', '}');
            $braceDepth = 0;
            $parenDepth = 0;
            $bracketDepth = 0;
            $waitingForCaseColon = false;
            $insideCaseClause = false;

            for ($i = $bodyOpen + 1; $i < $bodyClose; $i++) {
                $text = $this->tokens[$i]->text;
                $atCaseClauseLevel = $braceDepth === 0 && $parenDepth === 0 && $bracketDepth === 0;

                if ($atCaseClauseLevel && ($text === 'case' || $text === 'default')) {
                    $waitingForCaseColon = true;
                    $insideCaseClause = false;
                    continue;
                }

                if ($atCaseClauseLevel && $waitingForCaseColon && $text === ':') {
                    $waitingForCaseColon = false;
                    $insideCaseClause = true;
                    continue;
                }

                if ($atCaseClauseLevel && $insideCaseClause && $this->isUsingDeclarationStart($i)) {
                    throw new \InvalidArgumentException('Cannot use a "using" declaration directly inside a switch case');
                }

                if ($text === '{') {
                    $braceDepth++;
                } elseif ($text === '}') {
                    $braceDepth--;
                } elseif ($text === '(') {
                    $parenDepth++;
                } elseif ($text === ')') {
                    $parenDepth--;
                } elseif ($text === '[') {
                    $bracketDepth++;
                } elseif ($text === ']') {
                    $bracketDepth--;
                }
            }
        }
    }

    private function containsInlineableEnumReference(int $start, int $end): bool
    {
        for ($i = $start; $i <= $end; $i++) {
            if ($this->enumReferenceAt($i, $end) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private function enumReferenceAt(int $index, int $end): ?array
    {
        $enum = $this->tokens[$index] ?? null;
        if ($enum?->kind !== 'identifier'
            || ($this->tokens[$index - 1] ?? null)?->text === '.'
            || !isset($this->enumConstants[$enum->text])
        ) {
            return null;
        }

        $member = null;
        $lastIndex = null;
        if ($index + 2 <= $end
            && ($this->tokens[$index + 1] ?? null)?->text === '.'
            && ($this->tokens[$index + 2] ?? null)?->kind === 'identifier'
        ) {
            $member = $this->tokens[$index + 2]->text;
            $lastIndex = $index + 2;
        } elseif ($index + 3 <= $end
            && ($this->tokens[$index + 1] ?? null)?->text === '['
            && ($this->tokens[$index + 2] ?? null)?->kind === 'string'
            && ($this->tokens[$index + 3] ?? null)?->text === ']'
        ) {
            $member = $this->stringTokenValue($this->tokens[$index + 2]);
            $lastIndex = $index + 3;
        }

        if ($member === null || $lastIndex === null || !isset($this->enumConstants[$enum->text][$member])) {
            return null;
        }

        $constant = $this->enumConstants[$enum->text][$member];

        return [$constant['value'] . ' /* ' . $constant['comment'] . ' */', $lastIndex];
    }

    private function enumInlineComment(string $name): string
    {
        return str_replace(['/*', '*/'], ['/ *', '* /'], $name);
    }

    /**
     * @return list<string>
     */
    private function typeAnnotationStopTokens(int $colonIndex, int $statementStart): array
    {
        $previous = $this->previousSignificantTokenIndex($colonIndex - 1);
        if ($this->isForHeaderTypeAnnotationColon($colonIndex, $statementStart)) {
            return ['=', ',', ')', ';', 'of', 'in'];
        }

        return $previous !== null && ($this->tokens[$previous] ?? null)?->text === ')'
            ? ['=', ',', ')', '{', '=>', ';']
            : ['=', ',', ')', ';'];
    }

    private function isForHeaderTypeAnnotationColon(int $colonIndex, int $statementStart): bool
    {
        $open = $this->enclosingOpenPunctuator($colonIndex, '(', ')', $statementStart);
        if ($open === null) {
            return false;
        }

        $before = $this->previousSignificantTokenIndex($open - 1);
        if ($before === null) {
            return false;
        }
        if (($this->tokens[$before] ?? null)?->text === 'await') {
            $before = $this->previousSignificantTokenIndex($before - 1);
        }
        if ($before === null || ($this->tokens[$before] ?? null)?->text !== 'for') {
            return false;
        }

        for ($i = $open + 1; $i < $colonIndex; $i++) {
            $text = ($this->tokens[$i] ?? null)?->text;
            if ($text === 'using') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $stopTokens
     */
    private function skipTypeExpression(int $start, int $end, array $stopTokens = ['=', ',', ')', '{', '=>', ';']): int
    {
        $parenDepth = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        $angleDepth = 0;

        for ($i = $start; $i <= $end; $i++) {
            $text = $this->tokens[$i]->text;
            if ($parenDepth === 0 && $braceDepth === 0 && $bracketDepth === 0 && $angleDepth === 0 && in_array($text, $stopTokens, true)) {
                return $i;
            }

            if ($text === '(') {
                $parenDepth++;
            } elseif ($text === ')') {
                if ($parenDepth === 0) {
                    return $i;
                }
                $parenDepth--;
            } elseif ($text === '{') {
                $braceDepth++;
            } elseif ($text === '}') {
                if ($braceDepth === 0) {
                    return $i;
                }
                $braceDepth--;
            } elseif ($text === '[') {
                $bracketDepth++;
            } elseif ($text === ']') {
                if ($bracketDepth === 0) {
                    return $i;
                }
                $bracketDepth--;
            } elseif ($text === '<') {
                $angleDepth++;
            } elseif ($text === '>' && $angleDepth > 0) {
                $angleDepth--;
            }
        }

        return $end + 1;
    }

    private function isTypeAnnotationColon(int $index, int $statementStart): bool
    {
        $previous = $this->previousSignificantTokenIndex($index - 1);
        if ($previous === null) {
            return false;
        }

        if (($this->tokens[$previous] ?? null)?->text === '?') {
            $previous = $this->previousSignificantTokenIndex($previous - 1);
        }

        $previousToken = $previous === null ? null : $this->tokens[$previous];
        if ($previousToken === null) {
            return false;
        }

        if ($previousToken->text === ')') {
            $open = $this->matchingOpenPunctuator($previous, '(', ')', $statementStart);

            return $open !== null && $this->isFunctionOrArrowParameterList($open, $previous);
        }

        if ($previousToken->kind !== 'identifier') {
            return false;
        }

        if ($this->enclosingOpenPunctuator($index, '{', '}', $statementStart) !== null) {
            return false;
        }

        if ($this->isVariableDeclaratorName($previous, $statementStart)) {
            return true;
        }

        $open = $this->enclosingOpenPunctuator($previous, '(', ')', $statementStart);

        return $open !== null && $this->isFunctionOrArrowParameterList($open, $this->findMatchingPunctuator($open, '(', ')'));
    }

    private function isOptionalTypeMarker(int $index, int $statementStart): bool
    {
        $previous = $this->previousSignificantTokenIndex($index - 1);

        return $previous !== null
            && ($this->tokens[$previous] ?? null)?->kind === 'identifier'
            && $this->isTypeAnnotationColon($index + 1, $statementStart);
    }

    private function isTypeCastKeyword(int $index, int $statementStart): bool
    {
        $previous = $this->previousSignificantTokenIndex($index - 1);
        $next = $this->tokens[$index + 1] ?? null;
        $first = $this->tokens[$statementStart] ?? null;
        if ($first !== null
            && in_array($first->text, ['import', 'export'], true)
            && !$this->hasTopLevelAssignmentBefore($index, $statementStart)
        ) {
            return false;
        }

        return $previous !== null
            && $next !== null
            && $this->tokens[$index]->kind === 'identifier'
            && (
                in_array($next->text, ['typeof', 'keyof', 'readonly', '{', '[', '('], true)
                || $next->kind === 'identifier'
            );
    }

    private function isPostfixTypeAssertion(int $index, int $statementStart): bool
    {
        $previous = $this->previousSignificantTokenIndex($index - 1);
        $next = $this->tokens[$index + 1] ?? null;
        if ($previous === null || $next === null) {
            return false;
        }

        if (!in_array($next->text, [';', ',', ')', ']', '}', '.', '=', '=>'], true)) {
            return false;
        }

        $previousText = $this->tokens[$previous]->text;
        if (in_array($previousText, [')', ']', '}'], true)) {
            return true;
        }

        return $this->tokens[$previous]->kind === 'identifier'
            && !$this->isVariableDeclaratorName($previous, $statementStart);
    }

    private function hasTopLevelAssignmentBefore(int $index, int $statementStart): bool
    {
        $depth = 0;
        for ($i = $statementStart; $i < $index; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                continue;
            }
            if ($depth === 0 && $text === '=') {
                return true;
            }
        }

        return false;
    }

    private function isVariableDeclaratorName(int $index, int $statementStart): bool
    {
        $depth = 0;
        for ($i = $index - 1; $i >= $statementStart; $i--) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, [')', '}', ']'], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, ['(', '{', '['], true)) {
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if ($text === '=') {
                return false;
            }
            if ($text === ',' || in_array($text, ['let', 'const', 'var', 'using'], true)) {
                return true;
            }
        }

        return false;
    }

    private function isFunctionOrArrowParameterList(int $open, int $close): bool
    {
        $before = $this->previousSignificantTokenIndex($open - 1);
        if ($before !== null) {
            if ($this->tokens[$before]->text === 'function') {
                return true;
            }
            if ($this->tokens[$before]->kind === 'identifier') {
                $beforeName = $this->previousSignificantTokenIndex($before - 1);
                if ($beforeName !== null && $this->tokens[$beforeName]->text === 'function') {
                    return true;
                }
            }
        }

        $after = $this->tokens[$close + 1] ?? null;
        if ($after?->text === '=>') {
            return true;
        }

        if ($after?->text === ':') {
            $cursor = $this->skipTypeExpression($close + 2, count($this->tokens) - 1);

            return ($this->tokens[$cursor] ?? null)?->text === '=>' || ($this->tokens[$cursor] ?? null)?->text === '{';
        }

        return false;
    }

    private function previousSignificantTokenIndex(int $index): ?int
    {
        return $index >= 0 ? $index : null;
    }

    private function matchingOpenPunctuator(int $close, string $open, string $closeText, int $limit): ?int
    {
        $depth = 0;
        for ($i = $close; $i >= $limit; $i--) {
            $text = $this->tokens[$i]->text;
            if ($text === $closeText) {
                $depth++;
            } elseif ($text === $open) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function enclosingOpenPunctuator(int $index, string $open, string $closeText, int $limit): ?int
    {
        $depth = 0;
        for ($i = $index; $i >= $limit; $i--) {
            $text = $this->tokens[$i]->text;
            if ($text === $closeText) {
                $depth++;
            } elseif ($text === $open) {
                if ($depth === 0) {
                    return $i;
                }
                $depth--;
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

        return max($start, $count - 1);
    }

    private function findStatementEndForLowering(int $start): int
    {
        return $this->isUsingDeclarationStart($start)
            ? $this->findUsingDeclarationEnd($start)
            : $this->findStatementEndOrLineBreak($start);
    }

    private function isUsingDeclarationStart(int $start): bool
    {
        $using = $this->usingDeclarationKeywordIndex($start);
        if ($using === null) {
            return false;
        }

        return ($this->tokens[$using + 1] ?? null)?->kind === 'identifier'
            && !$this->hasLineBreakBetween($using, $using + 1);
    }

    private function usingDeclarationKeywordIndex(int $start): ?int
    {
        if (($this->tokens[$start] ?? null)?->text === 'using') {
            return $start;
        }

        if (($this->tokens[$start] ?? null)?->text === 'await'
            && ($this->tokens[$start + 1] ?? null)?->text === 'using'
            && !$this->hasLineBreakBetween($start, $start + 1)
        ) {
            return $start + 1;
        }

        return null;
    }

    private function findUsingDeclarationEnd(int $start): int
    {
        $depth = 0;
        $count = count($this->tokens);
        for ($i = $start; $i < $count; $i++) {
            $text = $this->tokens[$i]->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            } elseif ($text === ';' && $depth === 0) {
                return $i;
            }

            if ($depth === 0 && $this->hasLineBreakBetween($i, $i + 1)) {
                if (in_array($text, [',', '='], true)) {
                    continue;
                }

                return $i;
            }
        }

        return max($start, $count - 1);
    }

    /**
     * @return array{await:bool, declarations:list<array{name:string, valueStart:int, valueEnd:int}>}
     */
    private function parseUsingDeclaration(int $start, int $end): array
    {
        $using = $this->usingDeclarationKeywordIndex($start);
        if ($using === null) {
            throw new \InvalidArgumentException('Expected TypeScript using declaration');
        }

        $isAwaitUsing = $using !== $start;
        $cursor = $using + 1;
        $declarations = [];
        while ($cursor <= $end) {
            $name = $this->tokens[$cursor] ?? null;
            if ($name?->kind !== 'identifier') {
                throw new \InvalidArgumentException('Expected identifier in TypeScript using declaration');
            }

            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === ':') {
                $cursor = $this->skipTypeExpression($cursor + 1, $end, ['=', ',', ';']);
            }

            if (($this->tokens[$cursor] ?? null)?->text !== '=') {
                throw new \InvalidArgumentException('The declaration "' . $name->text . '" must be initialized');
            }

            $cursor++;
            $valueStart = $cursor;
            if ($cursor > $end) {
                throw new \InvalidArgumentException('Expected initializer after TypeScript using declaration');
            }

            $cursor = $this->usingInitializerEnd($cursor, $end);
            $valueEnd = ($this->tokens[$cursor] ?? null)?->text === ',' ? $cursor - 1 : $end;
            if ($valueEnd < $valueStart) {
                throw new \InvalidArgumentException('Expected initializer after TypeScript using declaration');
            }
            $declarations[] = [
                'name' => $name->text,
                'valueStart' => $valueStart,
                'valueEnd' => $valueEnd,
            ];

            if (($this->tokens[$cursor] ?? null)?->text !== ',') {
                return ['await' => $isAwaitUsing, 'declarations' => $declarations];
            }

            $cursor++;
        }

        return ['await' => $isAwaitUsing, 'declarations' => $declarations];
    }

    /**
     * @param array{await:bool, declarations:list<array{name:string, valueStart:int, valueEnd:int}>} $using
     */
    private function printUsingDeclaration(array $using, bool $allowHelperLowering = true): string
    {
        $canSkipUsingMachinery = !$using['await'] && $this->usingDeclarationsCanSkipUsingMachinery($using);
        if ($this->minifySyntax && $canSkipUsingMachinery) {
            $kind = $this->lowerUsingDeclarations && $allowHelperLowering ? 'var' : 'const';

            return $kind . ' ' . $this->printUsingDeclarators($using['declarations'], false) . ';';
        }

        if ($this->lowerUsingDeclarations && $allowHelperLowering) {
            $this->hasLoweredUsingDeclarations = true;
            $this->needsUsingHelperRuntime = true;
            if ($using['await']) {
                $this->hasLoweredAwaitUsingDeclarations = true;
            }

            return 'var ' . $this->printUsingDeclarators($using['declarations'], true, $using['await']) . ';';
        }

        $kind = $using['await'] ? 'await using' : 'using';

        return $kind . ' ' . $this->printUsingDeclarators($using['declarations'], false) . ';';
    }

    /**
     * @param list<array{name:string, valueStart:int, valueEnd:int}> $declarations
     */
    private function printUsingDeclarators(
        array $declarations,
        bool $wrapInHelper,
        bool $isAwaitUsing = false,
        string $stackName = '_stack'
    ): string
    {
        $parts = [];
        foreach ($declarations as $declaration) {
            $value = $this->printUsingInitializer($declaration['valueStart'], $declaration['valueEnd']);
            if ($wrapInHelper) {
                $args = $stackName . ', ' . $value;
                if ($isAwaitUsing) {
                    $args .= ', true';
                }
                $value = $this->usingHelperUsingName . '(' . $args . ')';
            }
            $parts[] = $declaration['name'] . ' = ' . $value;
        }

        return implode(', ', $parts);
    }

    /**
     * @param list<array{name:string, valueStart:int, valueEnd:int}> $declarations
     */
    private function printAsyncGeneratorUsingDeclarators(
        array $declarations,
        bool $isAwaitUsing,
        string $stackName
    ): string
    {
        $this->needsUsingHelperRuntime = true;
        $parts = [];
        foreach ($declarations as $declaration) {
            $value = $this->printAsyncGeneratorExpression($declaration['valueStart'], $declaration['valueEnd']);
            $parts[] = $declaration['name'] . ' = ' . $this->usingHelperUsingName . '(' . $stackName . ', ' . $value . ($isAwaitUsing ? ', true' : '') . ')';
        }

        return implode(', ', $parts);
    }

    /**
     * @param array{await:bool, declarations:list<array{name:string, valueStart:int, valueEnd:int}>} $using
     */
    private function usingDeclarationsCanSkipUsingMachinery(array $using): bool
    {
        if ($using['declarations'] === []) {
            return false;
        }

        foreach ($using['declarations'] as $declaration) {
            if (!$this->usingInitializerCanSkipUsingMachinery($declaration['valueStart'], $declaration['valueEnd'])) {
                return false;
            }
        }

        return true;
    }

    private function usingInitializerCanSkipUsingMachinery(int $start, int $end): bool
    {
        [$start, $end] = $this->stripOuterParentheses($start, $end);
        $comma = $this->lastTopLevelComma($start, $end);
        if ($comma !== null) {
            return $this->usingInitializerCanSkipUsingMachinery($comma + 1, $end);
        }

        return $start === $end
            && in_array(($this->tokens[$start] ?? null)?->text, ['null', 'undefined'], true);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function stripOuterParentheses(int $start, int $end): array
    {
        while (($this->tokens[$start] ?? null)?->text === '('
            && ($this->tokens[$end] ?? null)?->text === ')'
            && $this->findMatchingPunctuator($start, '(', ')') === $end
        ) {
            $start++;
            $end--;
        }

        return [$start, $end];
    }

    private function lastTopLevelComma(int $start, int $end): ?int
    {
        $depth = 0;
        $lastComma = null;
        for ($i = $start; $i <= $end; $i++) {
            $text = ($this->tokens[$i] ?? null)?->text;
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
                continue;
            }
            if (in_array($text, [')', '}', ']'], true)) {
                $depth--;
                continue;
            }
            if ($text === ',' && $depth === 0) {
                $lastComma = $i;
            }
        }

        return $lastComma;
    }

    private function printUsingInitializer(int $start, int $end): string
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
            } elseif ($token->kind === 'identifier' && $token->text === 'undefined') {
                $text = 'void 0';
            }

            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
    }

    private function printAsyncGeneratorStatement(int $start, int $end): string
    {
        $effectiveEnd = $this->withoutTrailingSemicolon($end);
        if (($this->tokens[$start] ?? null)?->text === 'yield') {
            if (($this->tokens[$start + 1] ?? null)?->text === '*') {
                $value = $start + 2 <= $effectiveEnd
                    ? $this->printAsyncGeneratorExpression($start + 2, $effectiveEnd)
                    : '';

                return 'yield* ' . $this->asyncGeneratorYieldStarName . '(' . $value . ');';
            }

            if ($start >= $effectiveEnd) {
                return 'yield;';
            }

            return 'yield ' . $this->printAsyncGeneratorExpression($start + 1, $effectiveEnd) . ';';
        }

        $statement = $this->printAsyncGeneratorExpression($start, $effectiveEnd);
        if ($statement === '') {
            return '';
        }

        if ($this->runtimeStatementNeedsSemicolon($start, $effectiveEnd, $statement)) {
            $statement .= ';';
        }

        return $statement;
    }

    private function printAsyncGeneratorExpression(int $start, int $end): string
    {
        $expression = $this->printRuntimeTokenRange($start, $end, $start);

        return $this->rewriteAwaitForAsyncGenerator($expression);
    }

    private function rewriteAwaitForAsyncGenerator(string $expression): string
    {
        if (!str_contains($expression, 'await')) {
            return $expression;
        }

        $this->needsAsyncGeneratorHelperRuntime = true;

        $output = '';
        $length = strlen($expression);
        for ($i = 0; $i < $length;) {
            $char = $expression[$i];
            if ($char === '"' || $char === "'" || $char === '`') {
                $end = $this->quotedStringEnd($expression, $i);
                $output .= substr($expression, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }

            if (substr($expression, $i, 5) !== 'await'
                || ($i > 0 && ($this->isIdentifierCharacter($expression[$i - 1]) || $expression[$i - 1] === '.'))
                || ($i + 5 < $length && $this->isIdentifierCharacter($expression[$i + 5]))
            ) {
                $output .= $char;
                $i++;
                continue;
            }

            $operandStart = $i + 5;
            while ($operandStart < $length && ctype_space($expression[$operandStart])) {
                $operandStart++;
            }
            if ($operandStart >= $length) {
                $output .= 'await';
                $i += 5;
                continue;
            }

            $operandEnd = $this->awaitOperandEnd($expression, $operandStart);
            if ($operandEnd < $operandStart) {
                $output .= 'await';
                $i += 5;
                continue;
            }

            $operand = trim(substr($expression, $operandStart, $operandEnd - $operandStart + 1));
            $output .= 'yield new ' . $this->asyncGeneratorAwaitName . '(' . $operand . ')';
            $i = $operandEnd + 1;
        }

        return $output;
    }

    private function awaitOperandEnd(string $expression, int $start): int
    {
        $length = strlen($expression);
        $depth = 0;
        $end = $start - 1;
        for ($i = $start; $i < $length; $i++) {
            $char = $expression[$i];
            if ($char === '"' || $char === "'" || $char === '`') {
                $i = $this->quotedStringEnd($expression, $i);
                $end = $i;
                continue;
            }

            if ($depth === 0 && ($char === ',' || $char === ';' || $char === "\n" || $char === ')' || $char === ']' || $char === '}')) {
                break;
            }

            if ($depth === 0 && $end >= $start) {
                if (($char === '+' || $char === '-') && ($expression[$i + 1] ?? '') === $char) {
                    $end = $i + 1;
                    break;
                }

                if ($this->awaitOperandHasTopLevelBinaryOperatorAt($expression, $i)) {
                    break;
                }
            }

            if ($depth === 0 && $i === $start && ($char === '+' || $char === '-') && ($expression[$i + 1] ?? '') === $char) {
                $end = $i + 1;
                $i++;
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            }

            $end = $i;
        }

        return $end;
    }

    private function awaitOperandHasTopLevelBinaryOperatorAt(string $expression, int $offset): bool
    {
        $char = $expression[$offset];
        if (ctype_space($char)) {
            return preg_match('/\G\s+(?:in|instanceof)\b/', $expression, $matches, 0, $offset) === 1;
        }

        if ($char === '?') {
            $next = $expression[$offset + 1] ?? '';

            return $next !== '.';
        }

        return str_contains('?:=<>|&^+-*/%!', $char);
    }

    private function quotedStringEnd(string $expression, int $start): int
    {
        $quote = $expression[$start];
        $length = strlen($expression);
        for ($i = $start + 1; $i < $length; $i++) {
            if ($expression[$i] === '\\') {
                $i++;
                continue;
            }
            if ($expression[$i] === $quote) {
                return $i;
            }
        }

        return $length - 1;
    }

    private function isIdentifierCharacter(string $char): bool
    {
        return preg_match('/[A-Za-z0-9_$]/', $char) === 1;
    }

    /**
     * @return array{0:string, 1:string, 2:string, 3:string, 4:string}
     */
    private function nextUsingScopeNames(): array
    {
        $this->usingScopeCounter++;
        $suffix = (string) ($this->usingScopeCounter + 1);

        return ['_stack' . $suffix, '_' . $suffix, '_error' . $suffix, '_hasError' . $suffix, '_promise' . $suffix];
    }

    /**
     * @param list<string> $bodyLines
     * @return list<string>
     */
    private function usingHelperScopeLines(array $bodyLines, bool $hasAwaitUsing, ?string $stackName = null): array
    {
        $this->needsUsingHelperRuntime = true;
        [$stack, $catch, $error, $hasError, $promise] = $stackName === null
            ? $this->nextUsingScopeNames()
            : [$stackName, substr($stackName, 6) === '' ? '_' : '_' . substr($stackName, 6), '_error' . substr($stackName, 6), '_hasError' . substr($stackName, 6), '_promise' . substr($stackName, 6)];

        $lines = [
            'var ' . $stack . ' = [];',
            'try {',
        ];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '} catch (' . $catch . ') {';
        $lines[] = '  var ' . $error . ' = ' . $catch . ', ' . $hasError . ' = true;';
        $lines[] = '} finally {';
        if ($hasAwaitUsing) {
            $lines[] = '  var ' . $promise . ' = ' . $this->usingHelperCallDisposeName . '(' . $stack . ', ' . $error . ', ' . $hasError . ');';
            $lines[] = '  ' . $promise . ' && await ' . $promise . ';';
        } else {
            $lines[] = '  ' . $this->usingHelperCallDisposeName . '(' . $stack . ', ' . $error . ', ' . $hasError . ');';
        }
        $lines[] = '}';

        return $lines;
    }

    /**
     * @param list<string> $bodyLines
     * @return list<string>
     */
    private function asyncGeneratorUsingHelperScopeLines(array $bodyLines, bool $hasAwaitUsing, ?string $stackName = null): array
    {
        $this->needsUsingHelperRuntime = true;
        $this->needsAsyncGeneratorHelperRuntime = true;
        [$stack, $catch, $error, $hasError, $promise] = $stackName === null
            ? $this->nextUsingScopeNames()
            : [$stackName, substr($stackName, 6) === '' ? '_' : '_' . substr($stackName, 6), '_error' . substr($stackName, 6), '_hasError' . substr($stackName, 6), '_promise' . substr($stackName, 6)];

        $lines = [
            'var ' . $stack . ' = [];',
            'try {',
        ];
        foreach ($bodyLines as $line) {
            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $lines[] = '  ' . $part;
            }
        }
        $lines[] = '} catch (' . $catch . ') {';
        $lines[] = '  var ' . $error . ' = ' . $catch . ', ' . $hasError . ' = true;';
        $lines[] = '} finally {';
        if ($hasAwaitUsing) {
            $lines[] = '  var ' . $promise . ' = ' . $this->usingHelperCallDisposeName . '(' . $stack . ', ' . $error . ', ' . $hasError . ');';
            $lines[] = '  ' . $promise . ' && (yield new ' . $this->asyncGeneratorAwaitName . '(' . $promise . '));';
        } else {
            $lines[] = '  ' . $this->usingHelperCallDisposeName . '(' . $stack . ', ' . $error . ', ' . $hasError . ');';
        }
        $lines[] = '}';

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function wrapUsingHelperStatements(array $lines): string
    {
        $prefix = [];
        $body = [];
        $suffix = [];
        $allowDirectivePrefix = true;
        foreach ($lines as $line) {
            if ($allowDirectivePrefix && $this->isStringDirectiveStatement(ltrim($line))) {
                $prefix[] = $line;
                continue;
            }

            $allowDirectivePrefix = false;

            if ($this->isTopLevelUsingHelperPrefixStatement($line)) {
                $prefix[] = $line;
                continue;
            }
            if ($this->isTopLevelUsingHelperSuffixStatement($line)) {
                $suffix[] = $line;
                continue;
            }

            $localExport = $this->rewriteTopLevelUsingHelperLocalExport($line);
            if ($localExport !== null) {
                foreach ($localExport['body'] as $bodyLine) {
                    foreach (explode("\n", $bodyLine) as $part) {
                        if ($part === '') {
                            continue;
                        }
                        $body[] = '  ' . $part;
                    }
                }
                foreach ($localExport['suffix'] as $suffixLine) {
                    $suffix[] = $suffixLine;
                }
                continue;
            }

            $localClass = $this->rewriteTopLevelUsingHelperLocalClass($line);
            if ($localClass !== null) {
                foreach (explode("\n", $localClass) as $part) {
                    if ($part === '') {
                        continue;
                    }
                    $body[] = '  ' . $part;
                }
                continue;
            }

            foreach (explode("\n", $line) as $part) {
                if ($part === '') {
                    continue;
                }
                $body[] = '  ' . $part;
            }
        }

        $finally = $this->hasLoweredAwaitUsingDeclarations
            ? '  var _promise = ' . $this->usingHelperCallDisposeName . "(_stack, _error, _hasError);\n  _promise && await _promise;"
            : '  ' . $this->usingHelperCallDisposeName . '(_stack, _error, _hasError);';

        $prefixText = $this->joinTopLevelUsingHelperStatements($prefix);
        $suffixText = $this->joinTopLevelUsingHelperStatements($suffix);

        return $prefixText
            . $this->helperRuntime()
            . "var _stack = [];\n"
            . "try {\n"
            . implode("\n", $body) . "\n"
            . "} catch (_) {\n"
            . "  var _error = _, _hasError = true;\n"
            . "} finally {\n"
            . $finally . "\n"
            . "}\n"
            . $suffixText;
    }

    private function isTopLevelUsingHelperPrefixStatement(string $line): bool
    {
        $trimmed = ltrim($line);

        if (preg_match('/^import(?:\s|["\'(])/', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^(?:export\s+(?:default\s+)?)?(?:async\s+)?function\b/', $trimmed) === 1) {
            return true;
        }

        return preg_match('/^export\s+(?:\*|\{[\s\S]*?\}\s+from\b)/', $trimmed) === 1;
    }

    private function isTopLevelUsingHelperSuffixStatement(string $line): bool
    {
        $trimmed = ltrim($line);

        return preg_match('/^export\s*\{/', $trimmed) === 1
            && preg_match('/^export\s*\{[\s\S]*?\}\s+from\b/', $trimmed) !== 1;
    }

    /**
     * @return array{body:list<string>, suffix:list<string>}|null
     */
    private function rewriteTopLevelUsingHelperLocalExport(string $line): ?array
    {
        $trimmed = trim($line);
        if (preg_match('/^export\s+(?:var|let|const)\s*([\s\S]*?);?$/', $trimmed, $match) === 1) {
            $declarations = rtrim($match[1]);
            $names = $this->exportedVariableNames($declarations);
            if ($names === []) {
                return null;
            }

            return [
                'body' => ['var ' . $declarations . ';'],
                'suffix' => [$this->exportClauseStatement($names)],
            ];
        }

        if (preg_match('/^export\s+class\s+([A-Za-z_$][A-Za-z0-9_$]*)([\s\S]*)$/', $trimmed, $match) === 1) {
            $name = $match[1];
            $tail = rtrim($match[2]);
            if ($tail === '' || !str_contains($tail, '{')) {
                return null;
            }

            return [
                'body' => ['var ' . $name . ' = ' . $this->hoistedClassExpression($name, $tail)],
                'suffix' => [$this->exportClauseStatement([$name])],
            ];
        }

        if (preg_match('/^export\s+default\s+class(?:\s+([A-Za-z_$][A-Za-z0-9_$]*))?([\s\S]*)$/', $trimmed, $match) === 1) {
            $name = $match[1] !== '' ? $match[1] : $this->allocateDefaultExportName();
            $tail = rtrim($match[2]);
            if ($tail === '' || !str_contains($tail, '{')) {
                return null;
            }

            $classExpression = $match[1] !== ''
                ? $this->hoistedClassExpression($name, $tail)
                : 'class' . $tail . (str_ends_with($tail, ';') ? '' : ';');

            return [
                'body' => ['var ' . $name . ' = ' . $classExpression],
                'suffix' => [$this->exportDefaultClauseStatement($name)],
            ];
        }

        if (preg_match('/^export\s+default\s+([\s\S]*?);?$/', $trimmed, $match) === 1
            && preg_match('/^export\s+default\s+(?:class|(?:async\s+)?function)\b/', $trimmed) !== 1
        ) {
            $name = $this->allocateDefaultExportName();
            $expression = rtrim($match[1]);
            if ($expression === '') {
                return null;
            }

            return [
                'body' => ['var ' . $name . ' = ' . $expression . ';'],
                'suffix' => [$this->exportDefaultClauseStatement($name)],
            ];
        }

        return null;
    }

    private function rewriteTopLevelUsingHelperLocalClass(string $line): ?string
    {
        $trimmed = trim($line);
        if (preg_match('/^class\s+([A-Za-z_$][A-Za-z0-9_$]*)([\s\S]*)$/', $trimmed, $match) !== 1) {
            return null;
        }

        $name = $match[1];
        $tail = rtrim($match[2]);
        if ($tail === '' || !str_contains($tail, '{')) {
            return null;
        }

        return 'var ' . $name . ' = ' . $this->hoistedClassExpression($name, $tail);
    }

    private function hoistedClassExpression(string $name, string $tail): string
    {
        [$hasSelfReference, $rewrittenTail] = $this->rewriteHoistedClassSelfReferences($name, $tail);
        $className = $hasSelfReference ? ' ' . $this->hoistedClassInternalName($name, $tail) : '';

        if ($hasSelfReference) {
            [, $rewrittenTail] = $this->rewriteHoistedClassSelfReferences($name, $tail, trim($className));
        }

        return 'class' . $className . $rewrittenTail . (str_ends_with($rewrittenTail, ';') ? '' : ';');
    }

    /**
     * @return array{0:bool, 1:string}
     */
    private function rewriteHoistedClassSelfReferences(string $name, string $tail, ?string $replacement = null): array
    {
        $tokens = (new JsLexer())->tokenize($tail);
        $referenceIndexes = [];
        foreach ($tokens as $index => $token) {
            if ($token->kind === 'identifier'
                && $token->text === $name
                && $this->isHoistedClassSelfReference($tokens, $index)
            ) {
                $referenceIndexes[$index] = true;
            }
        }

        if ($referenceIndexes === []) {
            return [false, $tail];
        }

        if ($replacement === null) {
            return [true, $tail];
        }

        $output = '';
        $cursor = 0;
        foreach ($tokens as $index => $token) {
            if (!isset($referenceIndexes[$index])) {
                continue;
            }

            $output .= substr($tail, $cursor, $token->offset - $cursor) . $replacement;
            $cursor = $token->offset + strlen($token->text);
        }

        return [true, $output . substr($tail, $cursor)];
    }

    /**
     * @param list<Token> $tokens
     */
    private function isHoistedClassSelfReference(array $tokens, int $index): bool
    {
        $previous = $tokens[$index - 1] ?? null;
        $next = $tokens[$index + 1] ?? null;
        if ($previous?->text === '.') {
            return false;
        }

        if ($next?->text === ':') {
            return false;
        }

        if ($previous !== null
            && in_array($previous->text, ['{', ';'], true)
            && $next !== null
            && in_array($next->text, ['=', '(', ';'], true)
        ) {
            return false;
        }

        return true;
    }

    private function hoistedClassInternalName(string $name, string $tail): string
    {
        $used = $this->sourceIdentifierMap();
        foreach ((new JsLexer())->tokenize($tail) as $token) {
            if ($token->kind === 'identifier') {
                $used[$token->text] = true;
            }
        }

        return $this->allocateUniqueIdentifier('_' . $name, $used);
    }

    /**
     * @return list<string>
     */
    private function exportedVariableNames(string $declarations): array
    {
        $tokens = (new JsLexer())->tokenize($declarations);
        if ($tokens === []) {
            return [];
        }

        $names = [];
        $cursor = 0;
        $end = count($tokens) - 1;

        while ($cursor <= $end) {
            [$declarationNames, $cursor] = $this->exportedBindingNamesAt($tokens, $cursor, $end);
            if ($declarationNames === []) {
                return [];
            }
            foreach ($declarationNames as $name) {
                $names[] = $name;
            }

            $cursor = $this->skipExportedVariableInitializer($tokens, $cursor, $end);
            if ($cursor > $end) {
                break;
            }
            $cursor++;
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<Token> $tokens
     * @return array{0:list<string>, 1:int}
     */
    private function exportedBindingNamesAt(array $tokens, int $start, int $end): array
    {
        $token = $tokens[$start] ?? null;
        if ($token === null) {
            return [[], $start + 1];
        }

        if ($token->kind === 'identifier') {
            return [[$token->text], $start + 1];
        }

        if ($token->text === '[') {
            return $this->exportedArrayBindingNamesAt($tokens, $start, $end);
        }

        if ($token->text === '{') {
            return $this->exportedObjectBindingNamesAt($tokens, $start, $end);
        }

        return [[], $start + 1];
    }

    /**
     * @param list<Token> $tokens
     * @return array{0:list<string>, 1:int}
     */
    private function exportedArrayBindingNamesAt(array $tokens, int $open, int $end): array
    {
        $close = min($this->matchingExportedBindingPunctuator($tokens, $open, '[', ']'), $end);
        $names = [];
        $cursor = $open + 1;

        while ($cursor < $close) {
            if (($tokens[$cursor] ?? null)?->text === ',') {
                $cursor++;
                continue;
            }

            if (($tokens[$cursor] ?? null)?->text === '.'
                && ($tokens[$cursor + 1] ?? null)?->text === '.'
                && ($tokens[$cursor + 2] ?? null)?->text === '.'
            ) {
                $cursor += 3;
            }

            [$elementNames, $cursor] = $this->exportedBindingNamesAt($tokens, $cursor, $close);
            foreach ($elementNames as $name) {
                $names[] = $name;
            }

            if (($tokens[$cursor] ?? null)?->text === '=') {
                $cursor = $this->skipExportedBindingInitializer($tokens, $cursor + 1, $close);
            }
        }

        return [$names, $close + 1];
    }

    /**
     * @param list<Token> $tokens
     * @return array{0:list<string>, 1:int}
     */
    private function exportedObjectBindingNamesAt(array $tokens, int $open, int $end): array
    {
        $close = min($this->matchingExportedBindingPunctuator($tokens, $open, '{', '}'), $end);
        $names = [];
        $cursor = $open + 1;

        while ($cursor < $close) {
            if (($tokens[$cursor] ?? null)?->text === ',') {
                $cursor++;
                continue;
            }

            if (($tokens[$cursor] ?? null)?->text === '.'
                && ($tokens[$cursor + 1] ?? null)?->text === '.'
                && ($tokens[$cursor + 2] ?? null)?->text === '.'
            ) {
                [$restNames, $cursor] = $this->exportedBindingNamesAt($tokens, $cursor + 3, $close);
                foreach ($restNames as $name) {
                    $names[] = $name;
                }
                continue;
            }

            if (($tokens[$cursor] ?? null)?->text === '[') {
                $cursor = $this->matchingExportedBindingPunctuator($tokens, $cursor, '[', ']') + 1;
                if (($tokens[$cursor] ?? null)?->text === ':') {
                    [$valueNames, $cursor] = $this->exportedBindingNamesAt($tokens, $cursor + 1, $close);
                    foreach ($valueNames as $name) {
                        $names[] = $name;
                    }
                    if (($tokens[$cursor] ?? null)?->text === '=') {
                        $cursor = $this->skipExportedBindingInitializer($tokens, $cursor + 1, $close);
                    }
                }
                continue;
            }

            $property = $tokens[$cursor] ?? null;
            if ($property === null) {
                break;
            }

            if (($tokens[$cursor + 1] ?? null)?->text === ':') {
                [$valueNames, $cursor] = $this->exportedBindingNamesAt($tokens, $cursor + 2, $close);
                foreach ($valueNames as $name) {
                    $names[] = $name;
                }
            } else {
                if ($property->kind === 'identifier') {
                    $names[] = $property->text;
                }
                $cursor++;
            }

            if (($tokens[$cursor] ?? null)?->text === '=') {
                $cursor = $this->skipExportedBindingInitializer($tokens, $cursor + 1, $close);
            }
        }

        return [$names, $close + 1];
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipExportedBindingInitializer(array $tokens, int $start, int $patternClose): int
    {
        $depth = 0;
        for ($i = $start; $i < $patternClose; $i++) {
            $text = $tokens[$i]->text;
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

    /**
     * @param list<Token> $tokens
     */
    private function skipExportedVariableInitializer(array $tokens, int $start, int $end): int
    {
        $depth = 0;
        for ($i = $start; $i <= $end; $i++) {
            $text = $tokens[$i]->text;
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
     * @param list<Token> $tokens
     */
    private function matchingExportedBindingPunctuator(array $tokens, int $openIndex, string $open, string $close): int
    {
        $depth = 0;
        $count = count($tokens);
        for ($i = $openIndex; $i < $count; $i++) {
            $text = $tokens[$i]->text;
            if ($text === $open) {
                $depth++;
            } elseif ($text === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unterminated TypeScript export binding pattern');
    }

    /**
     * @param list<string> $names
     */
    private function exportClauseStatement(array $names): string
    {
        $lines = ['export {'];
        $last = count($names) - 1;
        foreach ($names as $index => $name) {
            $lines[] = '  ' . $name . ($index === $last ? '' : ',');
        }
        $lines[] = '};';

        return implode("\n", $lines);
    }

    private function exportDefaultClauseStatement(string $name): string
    {
        return "export {\n  " . $name . " as default\n};";
    }

    private function allocateDefaultExportName(): string
    {
        $used = $this->sourceIdentifierMap();

        return $this->allocateUniqueIdentifier('defaultExport', $used);
    }

    private function isStringDirectiveStatement(string $line): bool
    {
        return preg_match('/^(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\');?$/', trim($line)) === 1;
    }

    /**
     * @param list<string> $statements
     */
    private function joinTopLevelUsingHelperStatements(array $statements): string
    {
        if ($statements === []) {
            return '';
        }

        return implode('', array_map(
            static fn (string $statement): string => rtrim($statement) . "\n",
            $statements,
        ));
    }

    private function helperRuntime(): string
    {
        if (!$this->needsUsingHelperRuntime && !$this->needsAsyncGeneratorHelperRuntime) {
            return '';
        }

        $runtime = $this->helperRuntimePreamble();
        if ($this->needsUsingHelperRuntime) {
            $runtime .= $this->usingHelperRuntime();
        }
        if ($this->needsAsyncGeneratorHelperRuntime) {
            $runtime .= $this->asyncGeneratorHelperRuntime();
        }

        return $runtime;
    }

    private function helperRuntimePreamble(): string
    {
        return strtr(<<<'JS'
var %%knownSymbol%% = (name, symbol) => (symbol = Symbol[name]) ? symbol : /* @__PURE__ */ Symbol.for("Symbol." + name);
var %%typeError%% = (msg) => {
  throw TypeError(msg);
};
JS, [
            '%%knownSymbol%%' => $this->usingHelperKnownSymbolName,
            '%%typeError%%' => $this->usingHelperTypeErrorName,
        ]) . "\n";
    }

    private function usingHelperRuntime(): string
    {
        return strtr(<<<'JS'
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

    private function asyncGeneratorHelperRuntime(): string
    {
        return strtr(<<<'JS'
var %%await%% = function(promise, isYieldStar) {
  this[0] = promise;
  this[1] = isYieldStar;
};
var %%asyncGenerator%% = (__this, __arguments, generator) => {
  var resume = (k, v, yes, no) => {
    try {
      var x = generator[k](v), isAwait = (v = x.value) instanceof %%await%%, done = x.done;
      Promise.resolve(isAwait ? v[0] : v).then((y) => isAwait ? resume(k === "return" ? k : "next", v[1] ? { done: y.done, value: y.value } : y, yes, no) : yes({ value: y, done })).catch((e) => resume("throw", e, yes, no));
    } catch (e) {
      no(e);
    }
  }, method = (k, call, wait, clear) => it[k] = (x) => (call = new Promise((yes, no, run) => (run = () => resume(k, x, yes, no), q ? q.then(run) : run())), clear = () => q === wait && (q = 0), q = wait = call.then(clear, clear), call), q, it = {};
  return generator = generator.apply(__this, __arguments), it[%%knownSymbol%%("asyncIterator")] = () => it, method("next"), method("throw"), method("return"), it;
};
var %%yieldStar%% = (value) => {
  var obj = value[%%knownSymbol%%("asyncIterator")], isAwait = false, method, it = {};
  if (obj == null) {
    obj = value[%%knownSymbol%%("iterator")]();
    method = (k) => it[k] = (x) => obj[k](x);
  } else {
    obj = obj.call(value);
    method = (k) => it[k] = (v) => {
      if (isAwait) {
        isAwait = false;
        if (k === "throw") throw v;
        return v;
      }
      isAwait = true;
      return { done: false, value: new %%await%%(new Promise((resolve) => {
        var x = obj[k](v);
        if (!(x instanceof Object)) %%typeError%%("Object expected");
        resolve(x);
      }), 1) };
    };
  }
  return it[%%knownSymbol%%("iterator")] = () => it, method("next"), "throw" in obj ? method("throw") : it.throw = (x) => {
    throw x;
  }, "return" in obj && method("return"), it;
};
var %%forAwait%% = (obj, it, method) => (it = obj[%%knownSymbol%%("asyncIterator")]) ? it.call(obj) : (obj = obj[%%knownSymbol%%("iterator")](), it = {}, method = (key, fn) => (fn = obj[key]) && (it[key] = (arg) => new Promise((yes, no, done) => (arg = fn.call(obj, arg), done = arg.done, Promise.resolve(arg.value).then((value) => yes({ value, done }), no)))), method("next"), method("return"), it);
JS, [
            '%%knownSymbol%%' => $this->usingHelperKnownSymbolName,
            '%%typeError%%' => $this->usingHelperTypeErrorName,
            '%%await%%' => $this->asyncGeneratorAwaitName,
            '%%asyncGenerator%%' => $this->asyncGeneratorName,
            '%%yieldStar%%' => $this->asyncGeneratorYieldStarName,
            '%%forAwait%%' => $this->asyncGeneratorForAwaitName,
        ]) . "\n";
    }

    private function configureHelperNames(): void
    {
        $used = $this->sourceIdentifierMap();
        $this->usingHelperKnownSymbolName = $this->allocateUniqueIdentifier('__knownSymbol', $used);
        $this->usingHelperTypeErrorName = $this->allocateUniqueIdentifier('__typeError', $used);
        $this->usingHelperUsingName = $this->allocateUniqueIdentifier('__using', $used);
        $this->usingHelperCallDisposeName = $this->allocateUniqueIdentifier('__callDispose', $used);
        $this->asyncGeneratorAwaitName = $this->allocateUniqueIdentifier('__await', $used);
        $this->asyncGeneratorName = $this->allocateUniqueIdentifier('__asyncGenerator', $used);
        $this->asyncGeneratorYieldStarName = $this->allocateUniqueIdentifier('__yieldStar', $used);
        $this->asyncGeneratorForAwaitName = $this->allocateUniqueIdentifier('__forAwait', $used);
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

    private function allocateGeneratedIdentifier(string $base): string
    {
        return $this->allocateUniqueIdentifier($base, $this->generatedIdentifiers);
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

    private function usingInitializerEnd(int $start, int $end): int
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

    private function withoutTrailingSemicolon(int $end): int
    {
        return ($this->tokens[$end] ?? null)?->text === ';' ? $end - 1 : $end;
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

        throw new \InvalidArgumentException('Unterminated TypeScript import equals target');
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

    private function printTokenRange(int $start, int $end): string
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
            }

            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
    }

    private function needsSpace(string $previous, string $current): bool
    {
        if (in_array($previous, ['=', '=>', '&&=', '||=', '??='], true) || in_array($current, ['=', '=>', '&&=', '||=', '??='], true)) {
            return true;
        }
        if (in_array($previous, ['accessor', 'static', 'get', 'set'], true) && (str_starts_with($current, '[') || str_starts_with($current, '#'))) {
            return true;
        }
        if ($previous === ')' && $current === '{') {
            return true;
        }
        if (in_array($current, [')', ']', '}', ',', ';', '.', ':'], true)) {
            return false;
        }
        if (in_array($previous, ['(', '[', '{', '.', ':', ''], true)) {
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
