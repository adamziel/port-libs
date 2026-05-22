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

        return new ModuleAnalysis($imports, $exports);
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
            $end = $this->findMatchingPunctuator($index + 1, '(', ')');
            $source = $this->firstStringValueBetween($index + 2, $end);
            if ($source === null) {
                throw new \InvalidArgumentException('Dynamic import must include a string source in this native slice');
            }

            return new ModuleImport('dynamic', $source, [], $this->tokens[$index]->offset);
        }

        $end = $this->findStatementEnd($index);
        if ($next->kind === 'string') {
            return new ModuleImport('side-effect', $this->stringValue($next), [], $this->tokens[$index]->offset);
        }

        $from = $this->findIdentifierBetween('from', $index + 1, $end);
        if ($from === null || !isset($this->tokens[$from + 1]) || $this->tokens[$from + 1]->kind !== 'string') {
            throw new \InvalidArgumentException('Static import must include a string source');
        }

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

        return new ModuleImport($kind, $this->stringValue($this->tokens[$from + 1]), $specifiers, $this->tokens[$index]->offset);
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

            return new ModuleExport($kind, $this->stringValue($this->tokens[$from + 1]), $specifiers, $this->tokens[$index]->offset);
        }

        if ($next->text === '{') {
            $braceEnd = $this->findMatchingPunctuator($index + 1, '{', '}');
            $from = $this->findIdentifierBetween('from', $braceEnd + 1, $end);
            $source = null;
            if ($from !== null) {
                if (($this->tokens[$from + 1] ?? null)?->kind !== 'string') {
                    throw new \InvalidArgumentException('Re-export must include a string source');
                }
                $source = $this->stringValue($this->tokens[$from + 1]);
            }

            return new ModuleExport($source === null ? 'named' : 're-export-named', $source, $this->parseExportSpecifiers($index + 2, $braceEnd), $this->tokens[$index]->offset);
        }

        return new ModuleExport('declaration', null, [], $this->tokens[$index]->offset);
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

    private function tokenName(Token $token): string
    {
        return $token->kind === 'string' ? $this->stringValue($token) : $token->text;
    }

    private function firstStringValueBetween(int $start, int $end): ?string
    {
        for ($i = $start; $i < $end; $i++) {
            if (($this->tokens[$i] ?? null)?->kind === 'string') {
                return $this->stringValue($this->tokens[$i]);
            }
        }

        return null;
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
