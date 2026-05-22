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
     * @var array<string, array<string, array{value:string, comment:string}>>
     */
    private array $enumConstants = [];

    public function lower(string $source): string
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($source);
        $this->enumConstants = $this->collectEnumConstants();

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

        return [$exported, $declared, $const, $cursor];
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

        $members = $this->parseEnumMembers($open + 1, $close, $name->text);
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
     * @return array<string, array<string, array{value:string, comment:string}>>
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
            foreach ($this->parseEnumMembers($open + 1, $close, $name->text) as $member) {
                if (!in_array($member['assignmentKind'], ['number', 'string'], true)) {
                    continue;
                }

                $constants[$name->text][$member['name']] = [
                    'value' => $member['assignment'],
                    'comment' => $this->enumInlineComment($member['name']),
                ];
            }
            $i = $close;
        }

        return $constants;
    }

    /**
     * @return list<array{name:string, assignment:string, assignmentKind:string}>
     */
    private function parseEnumMembers(int $start, int $close, string $enumName): array
    {
        $members = [];
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

            if (($this->tokens[$cursor] ?? null)?->text === '=') {
                $expressionStart = $cursor + 1;
                if ($expressionStart >= $close) {
                    throw new \InvalidArgumentException('Expected TypeScript enum member value');
                }

                $expressionEnd = $this->enumExpressionEnd($expressionStart, $close);
                [$assignment, $assignmentKind, $numericValue] = $this->enumMemberAssignment($expressionStart, $expressionEnd, $enumName);
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
     * @return array{0:string, 1:string, 2:?float}
     */
    private function enumMemberAssignment(int $start, int $end, string $enumName): array
    {
        if ($this->hasAdjacentEnumValueTokens($start, $end)) {
            throw new \InvalidArgumentException('Expected "," after TypeScript enum member');
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
     * @param list<array{name:string, assignment:string, assignmentKind:string}> $members
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
     * @param list<array{name:string, assignment:string, assignmentKind:string}> $members
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
        if (in_array($previous, ['=', '=>'], true) || in_array($current, ['=', '=>'], true)) {
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
