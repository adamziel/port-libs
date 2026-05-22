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

    public function lower(string $source): string
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($source);

        $lines = [];
        $exportAssignments = [];
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if (($this->tokens[$i] ?? null)?->text === ';') {
                continue;
            }

            $end = $this->findStatementEndOrLineBreak($i);
            $effectiveEnd = $this->withoutTrailingSemicolon($end);
            $first = $this->tokens[$i] ?? null;
            if ($first === null) {
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

            $statement = $this->containsErasableTypeScriptSyntax($i, $effectiveEnd)
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
