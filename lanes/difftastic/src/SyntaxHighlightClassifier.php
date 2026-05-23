<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class SyntaxHighlightClassifier
{
    /**
     * @param array{language?: string, semanticHighlights?: bool} $options
     */
    public function highlightForToken(string $source, Token $token, array $options = []): string
    {
        if (($options['semanticHighlights'] ?? true) !== false) {
            $semanticHighlight = $this->semanticHighlightFor($source, $token, $options);
            if ($semanticHighlight !== null) {
                return $semanticHighlight;
            }
        }

        return match ($token->kind) {
            'comment' => 'comment',
            'delimiter' => 'delimiter',
            'string' => 'string',
            default => 'normal',
        };
    }

    public function ansiStyleForHighlight(string $highlight, string $background): ?string
    {
        return match ($highlight) {
            'comment' => $background === 'dark' ? '3;94' : '3;34',
            'string' => $background === 'dark' ? '95' : '35',
            'keyword', 'type' => '1',
            default => null,
        };
    }

    /**
     * @param array{language?: string} $options
     */
    private function semanticHighlightFor(string $source, Token $token, array $options): ?string
    {
        $language = strtolower((string) ($options['language'] ?? ''));
        if ($token->kind === 'punctuation' && $this->isKeywordishOperator($language, $token->text)) {
            return 'keyword';
        }
        if ($token->kind !== 'identifier') {
            return null;
        }

        $lower = strtolower($token->text);

        if ($this->isMarkupLanguage($language) && $this->isMarkupTagName($source, $token->start)) {
            return 'type';
        }

        if ($this->isCssLanguage($language) && $this->isCssKeywordContext($source, $token->start)) {
            return 'keyword';
        }

        if ($this->isRustLanguage($language) && $this->isRustLifetimeLabel($source, $token->start)) {
            return 'type';
        }

        if (in_array($lower, $this->languageKeywords($language), true)) {
            return 'keyword';
        }

        if (in_array($lower, $this->languageTypes($language), true)) {
            return 'type';
        }

        return null;
    }

    private function isKeywordishOperator(string $language, string $text): bool
    {
        if (!$this->isKeywordishOperatorLanguage($language)) {
            return false;
        }

        return in_array($text, [
            '!', '!=', '!==', '%', '&', '&&', '*', '+', '-', '/', '::', '->',
            '<', '<=', '=', '==', '===', '=>', '>', '>=', '??', '?:', '^', '|',
            '||', '~',
        ], true);
    }

    private function isKeywordishOperatorLanguage(string $language): bool
    {
        return in_array($language, [
            'hack',
            'hh',
            'javascript',
            'js',
            'json',
            'php',
            'python',
            'py',
            'rs',
            'rust',
            'ts',
            'tsx',
            'typescript',
            'yaml',
            'yml',
        ], true);
    }

    private function isMarkupLanguage(string $language): bool
    {
        return in_array($language, ['html', 'jsx', 'tsx', 'xml'], true);
    }

    private function isCssLanguage(string $language): bool
    {
        return in_array($language, ['css', 'scss'], true);
    }

    private function isRustLanguage(string $language): bool
    {
        return in_array($language, ['rs', 'rust'], true);
    }

    private function isMarkupTagName(string $source, int $start): bool
    {
        if ($start <= 0) {
            return false;
        }

        $previous = $source[$start - 1] ?? '';
        if ($previous === '<') {
            return true;
        }

        return $previous === '/'
            && $start >= 2
            && ($source[$start - 2] ?? '') === '<';
    }

    private function isCssKeywordContext(string $source, int $start): bool
    {
        $previous = $this->previousNonWhitespaceCharacter($source, $start);

        return $previous === '@' || $previous === '!';
    }

    private function isRustLifetimeLabel(string $source, int $start): bool
    {
        if ($start <= 0 || ($source[$start - 1] ?? '') !== "'") {
            return false;
        }

        $previous = $start >= 2 ? ($source[$start - 2] ?? '') : '';

        return $previous === ''
            || preg_match('/[\s<&,(=:]/', $previous) === 1;
    }

    private function previousNonWhitespaceCharacter(string $source, int $start): ?string
    {
        for ($index = $start - 1; $index >= 0; $index--) {
            $character = $source[$index];
            if ($character === ' ' || $character === "\t" || $character === "\n" || $character === "\r") {
                continue;
            }

            return $character;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function languageKeywords(string $language): array
    {
        return match ($language) {
            'javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx' => [
                'as', 'assert', 'async', 'await', 'break', 'case', 'catch', 'class',
                'const', 'default', 'delete', 'do', 'else', 'export', 'extends',
                'false', 'finally', 'for', 'from', 'function', 'if', 'import', 'in',
                'instanceof', 'let', 'new', 'null', 'of', 'return', 'static',
                'switch', 'throw', 'true', 'try', 'type', 'typeof', 'undefined',
                'var', 'while', 'with', 'yield',
            ],
            'json' => ['false', 'null', 'true'],
            'php', 'hack', 'hh' => [
                'case', 'catch', 'class', 'declare', 'default', 'else', 'extends',
                'false', 'finally', 'for', 'foreach', 'function', 'if', 'implements',
                'interface', 'match', 'namespace', 'new', 'null', 'private',
                'protected', 'public', 'return', 'static', 'switch', 'throw',
                'trait', 'true', 'try', 'use', 'while', 'yield',
            ],
            'python', 'py' => [
                'and', 'as', 'assert', 'async', 'await', 'break', 'class',
                'continue', 'def', 'del', 'elif', 'else', 'except', 'finally',
                'false', 'for', 'from', 'if', 'import', 'in', 'is', 'lambda',
                'none', 'not', 'or', 'pass', 'raise', 'return', 'true', 'try',
                'while', 'with', 'yield',
            ],
            'rust', 'rs' => [
                'async', 'await', 'const', 'crate', 'else', 'enum', 'extern',
                'false', 'fn', 'for', 'if', 'impl', 'let', 'loop', 'match', 'mod',
                'mut', 'pub', 'return', 'self', 'static', 'struct', 'super',
                'trait', 'true', 'type', 'unsafe', 'use', 'where', 'while',
            ],
            'yaml', 'yml' => ['false', 'null', 'true'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function languageTypes(string $language): array
    {
        return match ($language) {
            'javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx' => [
                'any', 'array', 'bigint', 'boolean', 'never', 'number', 'object',
                'promise', 'record', 'string', 'symbol', 'unknown', 'void',
            ],
            'php', 'hack', 'hh' => [
                'array', 'bool', 'callable', 'float', 'int', 'iterable', 'mixed',
                'never', 'object', 'parent', 'self', 'string', 'void',
            ],
            'python', 'py' => [
                'bool', 'bytes', 'dict', 'float', 'int', 'list', 'set', 'str', 'tuple',
            ],
            'rust', 'rs' => [
                'bool', 'char', 'f32', 'f64', 'i8', 'i16', 'i32', 'i64', 'i128',
                'isize', 'str', 'u8', 'u16', 'u32', 'u64', 'u128', 'usize',
            ],
            default => [],
        };
    }
}
