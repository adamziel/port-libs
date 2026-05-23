<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class SyntaxHighlightClassifier
{
    /**
     * @param array{language?: string} $options
     */
    public function highlightForToken(string $source, Token $token, array $options = []): string
    {
        $semanticHighlight = $this->semanticHighlightFor($source, $token, $options);
        if ($semanticHighlight !== null) {
            return $semanticHighlight;
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
        if ($token->kind !== 'identifier') {
            return null;
        }

        $language = strtolower((string) ($options['language'] ?? ''));
        $lower = strtolower($token->text);

        if ($this->isMarkupLanguage($language) && $this->isMarkupTagName($source, $token->start)) {
            return 'type';
        }

        if ($this->isCssLanguage($language) && $this->isCssKeywordContext($source, $token->start)) {
            return 'keyword';
        }

        if (in_array($lower, $this->languageKeywords($language), true)) {
            return 'keyword';
        }

        if (in_array($lower, $this->languageTypes($language), true)) {
            return 'type';
        }

        return null;
    }

    private function isMarkupLanguage(string $language): bool
    {
        return in_array($language, ['html', 'jsx', 'tsx', 'xml'], true);
    }

    private function isCssLanguage(string $language): bool
    {
        return in_array($language, ['css', 'scss'], true);
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
                'finally', 'for', 'from', 'function', 'if', 'import', 'in',
                'instanceof', 'let', 'new', 'of', 'return', 'static', 'switch',
                'throw', 'try', 'type', 'typeof', 'var', 'while', 'with', 'yield',
            ],
            'php', 'hack', 'hh' => [
                'case', 'catch', 'class', 'declare', 'default', 'else', 'extends',
                'finally', 'for', 'foreach', 'function', 'if', 'implements',
                'interface', 'match', 'namespace', 'new', 'private', 'protected',
                'public', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'use',
                'while', 'yield',
            ],
            'python', 'py' => [
                'and', 'as', 'assert', 'async', 'await', 'break', 'class',
                'continue', 'def', 'del', 'elif', 'else', 'except', 'finally',
                'for', 'from', 'if', 'import', 'in', 'is', 'lambda', 'not', 'or',
                'pass', 'raise', 'return', 'try', 'while', 'with', 'yield',
            ],
            'rust', 'rs' => [
                'async', 'await', 'const', 'crate', 'else', 'enum', 'extern',
                'fn', 'for', 'if', 'impl', 'let', 'loop', 'match', 'mod', 'mut',
                'pub', 'return', 'self', 'static', 'struct', 'super', 'trait',
                'type', 'unsafe', 'use', 'where', 'while',
            ],
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
