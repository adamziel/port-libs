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

    public function lower(string $source, bool $useDefineForClassFields = true): string
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($source);
        $this->enumConstants = $this->collectEnumConstants();
        $this->useDefineForClassFields = $useDefineForClassFields;

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

            $end = $this->findStatementEndOrLineBreak($i);
            $effectiveEnd = $this->withoutTrailingSemicolon($end);

            if ($first->text === 'export' && ($this->tokens[$i + 1] ?? null)?->text === '=') {
                if ($i + 2 > $effectiveEnd) {
                    throw new \InvalidArgumentException('Expected expression after TypeScript export equals');
                }
                $exportAssignments[] = 'module.exports = ' . $this->printTokenRange($i + 2, $effectiveEnd) . ';';
                $i = $end;
                continue;
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

            if ($first->text === 'import' && ($this->tokens[$i + 1] ?? null)?->kind === 'identifier') {
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

            $statement = $this->containsErasableTypeScriptSyntax($i, $effectiveEnd) || $this->containsInlineableEnumReference($i, $effectiveEnd)
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

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
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
            if (in_array($text, ['(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', '}', ']'], true)) {
                $depth--;
            }

            if ($depth === 0) {
                $next = $this->tokens[$i + 1] ?? null;
                if ($next !== null && in_array($next->text, ['@', 'declare', 'export', 'abstract', 'class'], true)) {
                    return $i;
                }
                if ($this->hasLineBreakBetween($i, $i + 1)) {
                    return $i;
                }
            }
        }

        throw new \InvalidArgumentException('Unterminated TypeScript decorator');
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
        if (($this->tokens[$cursor] ?? null)?->text === 'export') {
            if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                return null;
            }
            $cursor++;
            if (($this->tokens[$cursor] ?? null)?->text === 'default') {
                if ($this->hasLineBreakBetween($cursor, $cursor + 1)) {
                    return null;
                }
                $cursor++;
            }
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
        [$members, $hasTypeScriptMemberSyntax, $fieldKeyTemps, $fieldKeyPrelude] = $this->lowerClassMembers(
            $bodyOpen + 1,
            $bodyClose,
            $this->classHeaderHasExtends($cursor, $bodyOpen),
        );
        if (!$hasTypeScriptClassSyntax && !$hasTypeScriptMemberSyntax) {
            return null;
        }

        $header = $this->classHeaderText($start, $bodyOpen);
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

        return [$classOutput, $bodyClose];
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

    /**
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>}
     */
    private function lowerClassMembers(int $start, int $end, bool $hasExtends): array
    {
        $members = [];
        $instanceAssignments = [];
        $staticAssignments = [];
        $fieldKeyTemps = [];
        $fieldKeyPrelude = [];
        $pendingFieldKeyEffects = [];
        $lastComputedMethod = null;
        $hasTypeScriptMemberSyntax = false;
        for ($cursor = $start; $cursor < $end; $cursor++) {
            if (($this->tokens[$cursor] ?? null)?->text === ';') {
                continue;
            }

            $memberEnd = $this->classMemberEnd($cursor, $end);
            [$loweredMembers, $transformed, $memberInstanceAssignments, $memberStaticAssignments, $fieldKeyEffects] = $this->lowerClassMember(
                $cursor,
                $memberEnd,
                $fieldKeyTemps,
                $hasExtends,
            );
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
                $members[] = $this->staticFieldAssignmentBlock($staticAssignments);
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
            $members[] = $this->staticFieldAssignmentBlock($staticAssignments);
        }

        return [$members, $hasTypeScriptMemberSyntax, $fieldKeyTemps, $fieldKeyPrelude];
    }

    /**
     * @param list<string> $fieldKeyTemps
     * @return array{0:list<string>, 1:bool, 2:list<string>, 3:list<string>, 4:list<array{sequence:string, preludeExpression:?string}>}
     */
    private function lowerClassMember(int $start, int $end, array &$fieldKeyTemps, bool $hasExtends): array
    {
        $cursor = $start;
        $decorated = false;
        while (($this->tokens[$cursor] ?? null)?->text === '@') {
            $decorated = true;
            $cursor = $this->decoratorEnd($cursor) + 1;
        }

        $modifiers = [];
        while ($cursor <= $end
            && ($this->tokens[$cursor] ?? null)?->kind === 'identifier'
            && in_array($this->tokens[$cursor]->text, $this->classMemberModifierKeywords(), true)
        ) {
            $modifiers[] = $this->tokens[$cursor]->text;
            $cursor++;
        }

        if (!in_array('declare', $modifiers, true)) {
            if ($cursor > $end) {
                return [[$this->printClassMemberRange($start, $end)], false, [], [], []];
            }

            $constructor = $this->lowerConstructorParameterPropertyMember($start, $end, $cursor, $hasExtends);
            if ($constructor !== null) {
                return [$constructor[0], $constructor[1], [], [], []];
            }

            if ($this->useDefineForClassFields === false) {
                $assignSemanticsField = $this->lowerAssignSemanticsClassField($start, $end, $cursor, $modifiers, $fieldKeyTemps);
                if ($assignSemanticsField !== null) {
                    return $assignSemanticsField;
                }
            }

            if (in_array('abstract', $modifiers, true) && !$this->classMemberHasBody($cursor, $end)) {
                return [[], true, [], [], []];
            }

            if ($this->containsClassMemberTypeScriptSyntax($start, $end, $modifiers)) {
                return [[$this->printClassMemberRuntimeRange($start, $end)], true, [], [], []];
            }

            return [[$this->printClassMemberRange($start, $end)], false, [], [], []];
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

        return [[], true, [], [], []];
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

        $declaration = in_array('static', $modifiers, true)
            ? 'static ' . $name->text . ';'
            : $name->text . ';';

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
                if ($previous !== null && ($this->needsSpace($previous, $text) || $previous === 'static')) {
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

            if ($token->text === '<' && $this->isClassMemberTypeParameterList($i, $start, $effectiveEnd)) {
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

            if ($previous !== null && ($this->needsSpace($previous, $text) || ($previous === 'static' && $text === '['))) {
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
        if ($last?->text !== '}' && !str_ends_with($member, ';')) {
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
            if ($superCalls > 1 || (!$this->constructorLineHasDirectSuperCall($line) && $this->splitTopLevelCommaSuperExpressionStatement($line) === null)) {
                if ($this->splitTopLevelCommaSuperControlStatement($line) !== null) {
                    continue;
                }

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
            if ($token->text === ':' && $this->isClassMemberTypeColon($i, $start, $end)) {
                return true;
            }
            if (($token->text === '?' || $token->text === '!')
                && $this->isClassMemberOptionalOrDefiniteMarker($i, $start, $end)
            ) {
                return true;
            }
            if ($token->text === '<' && $this->isClassMemberTypeParameterList($i, $start, $end)) {
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
            } elseif ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0 && $text === ';') {
                return $i;
            }

            if ($parenDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
                && $this->hasLineBreakBetween($i, $i + 1)
            ) {
                return $i;
            }
        }

        return $end - 1;
    }

    private function classHeaderText(int $start, int $bodyOpen): string
    {
        $parts = [];
        $previous = null;
        for ($i = $start; $i < $bodyOpen; $i++) {
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

            $text = $token->text;
            if ($previous !== null && $this->needsSpace($previous, $text)) {
                $parts[] = ' ';
            }
            $parts[] = $text;
            $previous = $text;
        }

        return implode('', $parts);
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

            if ($token->text === '<' && $this->isClassMemberTypeParameterList($i, $start, $effectiveEnd)) {
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

            if ($previous !== null && $this->needsSpace($previous, $text)) {
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
        if ($last?->text !== '}' && !str_ends_with($member, ';')) {
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
            && $next !== null
            && (
                ($this->tokens[$previous]->kind === 'identifier'
                    || $this->tokens[$previous]->kind === 'string'
                    || $this->tokens[$previous]->text === ']')
                && in_array($next->text, [':', '=', ';', '(', '<'], true)
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

            if ($token->text === '?' && ($this->tokens[$i + 1] ?? null)?->text === ':' && $this->isOptionalTypeMarker($i, $start)) {
                continue;
            }

            if ($token->text === ':' && $this->isTypeAnnotationColon($i, $start)) {
                $i = $this->skipTypeExpression($i + 1, $end, $this->typeAnnotationStopTokens($i, $start)) - 1;
                continue;
            }

            if (($token->text === 'as' || $token->text === 'satisfies') && $this->isTypeCastKeyword($i, $start)) {
                $i = $this->skipTypeExpression($i + 1, $end, [',', ')', ']', '}', ';']) - 1;
                continue;
            }

            if ($token->text === '!' && $this->isPostfixTypeAssertion($i, $start)) {
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

        $statement = implode('', $parts);
        $last = $this->tokens[$end] ?? null;
        if ($last?->text !== '}' && !str_ends_with($statement, ';')) {
            $statement .= ';';
        }

        return $statement;
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

        return $previous !== null && ($this->tokens[$previous] ?? null)?->text === ')'
            ? ['=', ',', ')', '{', '=>', ';']
            : ['=', ',', ')', ';'];
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
            if ($text === ',' || in_array($text, ['let', 'const', 'var'], true)) {
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
