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

    public function lower(string $source): string
    {
        $this->source = $source;
        $this->tokens = (new JsLexer())->tokenize($source);

        $output = '';
        $depth = 0;
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $this->tokens[$i];
            if ($depth === 0 && $this->isNamespaceKeywordAt($i)) {
                [$namespaceOutput, $blockEnd] = $this->lowerNamespaceAt($i);
                $output .= $namespaceOutput;
                $i = $blockEnd;
                continue;
            }

            $depth = $this->adjustDepth($depth, $token);
        }

        return $output;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function lowerNamespaceAt(int $index): array
    {
        $name = $this->tokens[$index + 1] ?? null;
        if ($name?->kind !== 'identifier') {
            throw new \InvalidArgumentException('Expected TypeScript namespace name');
        }
        if (($this->tokens[$index + 2] ?? null)?->text !== '{') {
            throw new \InvalidArgumentException('Expected TypeScript namespace block');
        }

        $blockStart = $index + 2;
        $blockEnd = $this->findMatchingPunctuator($blockStart, '{', '}');
        $statements = $this->namespaceStatements($blockStart + 1, $blockEnd);
        $imports = [];
        $ordered = [];

        foreach ($statements as [$start, $end]) {
            $first = $this->tokens[$start] ?? null;
            if ($first === null) {
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

            if ($this->isTypeOnlyNamespaceStatement($start)) {
                continue;
            }

            $this->rejectNestedNamespaceImportEquals($start, $end);
            $ordered[] = ['kind' => 'body', 'start' => $start, 'end' => $end];
        }

        $body = [];
        foreach ($ordered as $item) {
            if ($item['kind'] === 'body') {
                $body[] = $this->printBodyStatement((int) $item['start'], (int) $item['end'], $name->text, $imports);
            }
        }

        $used = [];
        foreach ($imports as $local => $import) {
            if ($import['exported']) {
                $used[$local] = true;
            }
        }
        foreach ($body as $statement) {
            foreach ($statement['uses'] as $local) {
                $used[$local] = true;
            }
        }

        $lines = [];
        foreach ($ordered as $item) {
            if ($item['kind'] === 'import') {
                $local = (string) $item['local'];
                if (!isset($used[$local])) {
                    continue;
                }
                $import = $imports[$local];
                $lines[] = $import['exported']
                    ? $name->text . '.' . $local . ' = ' . $import['source'] . ';'
                    : 'const ' . $local . ' = ' . $import['source'] . ';';
                continue;
            }

            $statement = array_shift($body);
            if ($statement !== null && $statement['text'] !== '') {
                $lines[] = $statement['text'];
            }
        }

        if ($lines === []) {
            return ['', $blockEnd];
        }

        return [
            'var ' . $name->text . ";\n"
            . '((' . $name->text . ") => {\n"
            . implode('', array_map(static fn (string $line): string => '  ' . $line . "\n", $lines))
            . '})(' . $name->text . ' || (' . $name->text . " = {}));\n",
            $blockEnd,
        ];
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

            $statementEnd = $this->findStatementEndOrLineBreak($i, $end);
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

    private function isTypeOnlyNamespaceStatement(int $start): bool
    {
        $token = $this->tokens[$start] ?? null;
        if ($token?->kind !== 'identifier') {
            return false;
        }

        if (in_array($token->text, ['type', 'interface'], true)) {
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
    private function printBodyStatement(int $start, int $end, string $namespace, array $imports): array
    {
        $uses = [];
        $text = $this->printTokenRange($start, $end, $namespace, $imports, $uses);
        if ($text !== '' && !str_ends_with($text, ';')) {
            $text .= ';';
        }

        return ['text' => $text, 'uses' => array_values(array_unique($uses))];
    }

    /**
     * @param array<string, array{local:string, source:string, exported:bool}> $imports
     * @param list<string> $uses
     */
    private function printTokenRange(int $start, int $end, string $namespace, array $imports, array &$uses = []): string
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
                && ($this->tokens[$i - 1] ?? null)?->text !== '.'
            ) {
                $uses[] = $token->text;
                if ($imports[$token->text]['exported']) {
                    $text = $namespace . '.' . $token->text;
                }
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
