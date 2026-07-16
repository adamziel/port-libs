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
        if ($this->isBashLanguage($language) && $this->isBashFlagArgument($token->text)) {
            return 'keyword';
        }
        if (
            $this->isPythonLanguage($language)
            && $token->kind === 'string'
            && $this->isPythonStringizedAnnotationContext($source, $token)
        ) {
            return 'type';
        }
        if ($token->kind !== 'identifier') {
            return null;
        }

        $lower = strtolower($token->text);

        if ($this->isMarkupLanguage($language) && $this->isMarkupTagName($source, $token->start)) {
            return 'type';
        }

        if ($this->isMarkupLanguage($language) && $this->isMarkupDoctypeKeyword($source, $token)) {
            return 'keyword';
        }

        if ($this->isCssLanguage($language) && $this->isCssKeywordContext($source, $token->start)) {
            return 'keyword';
        }

        if ($this->isElispLanguage($language) && $this->isElispKeywordOrConstant($token->text)) {
            return 'keyword';
        }

        if ($this->isYamlLanguage($language) && $this->isYamlBuiltinScalar($token->text)) {
            return 'keyword';
        }

        if ($this->isCLikeLanguage($language) && $this->isCPreprocessorDirective($source, $token->start)) {
            return 'keyword';
        }

        if ($this->isRustLanguage($language) && $this->isRustLifetimeLabel($source, $token->start)) {
            return 'type';
        }

        if ($this->isRustLanguage($language) && $this->isRustMacroCall($source, $token)) {
            return 'keyword';
        }

        if ($this->isJavaScriptLikeLanguage($language)) {
            if ($this->isJavaScriptBuiltinVariable($token->text)) {
                return 'keyword';
            }
            if ($this->isJavaScriptAllCapsConstantIdentifier($token->text)) {
                return 'keyword';
            }
            if ($this->isUppercaseIdentifier($token->text)) {
                return 'type';
            }
        }

        if ($this->isJavaLanguage($language) && $this->isUppercaseIdentifier($token->text)) {
            return 'type';
        }

        if ($this->isLuaLanguage($language) && $this->isLuaBuiltinConstant($token->text)) {
            return 'keyword';
        }

        if ($this->isGoLanguage($language) && $this->isGoBuiltinConstant($token->text)) {
            return 'keyword';
        }

        if ($this->isPhpLikeLanguage($language)
            && ($this->isPhpBuiltinVariable($source, $token) || $this->isPhpMagicConstant($token->text))
        ) {
            return 'keyword';
        }

        if ($this->isRubyLanguage($language) && $this->isRubyAllCapsConstantIdentifier($token->text)) {
            return 'keyword';
        }

        if ($this->isPythonLanguage($language)) {
            if ($this->isPythonBuiltinVariable($token->text)) {
                return 'keyword';
            }
            if ($this->isPythonBuiltinFunctionCall($source, $token)) {
                return 'normal';
            }
            if ($this->isPythonBuiltinTypeName($token->text)) {
                return $this->isPythonTypeAnnotationContext($source, $token)
                    || $this->isPythonQualifiedTypeAnnotationContext($source, $token)
                    ? 'type'
                    : 'normal';
            }
        }

        if (in_array($lower, $this->languageKeywords($language), true)) {
            return 'keyword';
        }

        if (in_array($lower, $this->languageTypes($language), true)) {
            return 'type';
        }

        if ($this->isPythonLanguage($language) && $this->isUppercaseIdentifier($token->text)) {
            return 'type';
        }

        if ($this->isRubyLanguage($language) && $this->isUppercaseIdentifier($token->text)) {
            return 'type';
        }

        return null;
    }

    private function isKeywordishOperator(string $language, string $text): bool
    {
        if (!$this->isKeywordishOperatorLanguage($language)) {
            return false;
        }

        if (
            ($this->isCSharpLanguage($language) || $this->isGoLanguage($language) || $this->isJavaLanguage($language) || $language === 'swift')
            && $text === ':'
        ) {
            return true;
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
            'bash',
            'c#',
            'csharp',
            'go',
            'golang',
            'java',
            'javascript',
            'js',
            'json',
            'php',
            'python',
            'py',
            'rb',
            'rs',
            'ruby',
            'rust',
            'sql',
            'swift',
            'ts',
            'tsx',
            'typescript',
            'yaml',
            'yml',
            'sh',
            'shell',
        ], true);
    }

    private function isBashLanguage(string $language): bool
    {
        return in_array($language, ['bash', 'sh', 'shell'], true);
    }

    private function isMarkupLanguage(string $language): bool
    {
        return in_array($language, ['html', 'jsx', 'tsx', 'xml'], true);
    }

    private function isCssLanguage(string $language): bool
    {
        return in_array($language, ['css', 'scss'], true);
    }

    private function isElispLanguage(string $language): bool
    {
        return in_array($language, ['el', 'elisp', 'emacs-lisp'], true);
    }

    private function isYamlLanguage(string $language): bool
    {
        return in_array($language, ['yaml', 'yml'], true);
    }

    private function isCLikeLanguage(string $language): bool
    {
        return in_array($language, ['c', 'cc', 'cpp', 'c++', 'h', 'hpp', 'objc', 'objective-c'], true);
    }

    private function isRustLanguage(string $language): bool
    {
        return in_array($language, ['rs', 'rust'], true);
    }

    private function isPythonLanguage(string $language): bool
    {
        return in_array($language, ['python', 'py'], true);
    }

    private function isRubyLanguage(string $language): bool
    {
        return in_array($language, ['rb', 'ruby'], true);
    }

    private function isJavaScriptLikeLanguage(string $language): bool
    {
        return in_array($language, ['javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx'], true);
    }

    private function isGoLanguage(string $language): bool
    {
        return in_array($language, ['go', 'golang'], true);
    }

    private function isLuaLanguage(string $language): bool
    {
        return $language === 'lua';
    }

    private function isJavaLanguage(string $language): bool
    {
        return $language === 'java';
    }

    private function isCSharpLanguage(string $language): bool
    {
        return in_array($language, ['c#', 'csharp'], true);
    }

    private function isPhpLikeLanguage(string $language): bool
    {
        return in_array($language, ['hack', 'hh', 'php'], true);
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

    private function isMarkupDoctypeKeyword(string $source, Token $token): bool
    {
        if (strtolower($token->text) !== 'doctype' || $token->start < 2) {
            return false;
        }

        $previous = $this->previousNonWhitespaceCharacterPosition($source, $token->start);
        if ($previous === null || ($source[$previous] ?? '') !== '!') {
            return false;
        }

        $beforeBang = $this->previousNonWhitespaceCharacterPosition($source, $previous);

        return $beforeBang !== null && ($source[$beforeBang] ?? '') === '<';
    }

    private function isCssKeywordContext(string $source, int $start): bool
    {
        $previous = $this->previousNonWhitespaceCharacter($source, $start);

        return $previous === '@' || $previous === '!';
    }

    private function isYamlBuiltinScalar(string $text): bool
    {
        return in_array(strtolower($text), ['false', 'null', 'true'], true);
    }

    private function isBashFlagArgument(string $text): bool
    {
        return preg_match('/^-[A-Za-z0-9][A-Za-z0-9_-]*$/', $text) === 1
            || preg_match('/^--[A-Za-z0-9][A-Za-z0-9_-]*(?:=.*)?$/', $text) === 1;
    }

    private function isCPreprocessorDirective(string $source, int $start): bool
    {
        $lineStart = max(strrpos(substr($source, 0, $start), "\n") ?: 0, 0);
        if ($lineStart > 0) {
            $lineStart++;
        }

        $prefix = substr($source, $lineStart, $start - $lineStart);

        return preg_match('/^\s*#\s*$/', $prefix) === 1;
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

    private function isRustMacroCall(string $source, Token $token): bool
    {
        return $this->nextNonWhitespaceCharacter($source, $token->end) === '!';
    }

    private function isJavaScriptAllCapsConstantIdentifier(string $text): bool
    {
        return preg_match('/^[A-Z_][A-Z0-9_]+$/', $text) === 1;
    }

    private function isRubyAllCapsConstantIdentifier(string $text): bool
    {
        return preg_match('/^[A-Z_][A-Z0-9_]+$/', $text) === 1;
    }

    private function isJavaScriptBuiltinVariable(string $text): bool
    {
        return in_array($text, ['arguments', 'module', 'console', 'window', 'document', 'this', 'super'], true);
    }

    private function isGoBuiltinConstant(string $text): bool
    {
        return in_array($text, ['false', 'iota', 'nil', 'true'], true);
    }

    private function isLuaBuiltinConstant(string $text): bool
    {
        return in_array($text, ['false', 'nil', 'true'], true);
    }

    private function isPhpBuiltinVariable(string $source, Token $token): bool
    {
        if (!in_array($token->text, [
            'GLOBALS',
            '_COOKIE',
            '_ENV',
            '_FILES',
            '_GET',
            '_POST',
            '_REQUEST',
            '_SERVER',
            '_SESSION',
            'this',
        ], true)) {
            return false;
        }

        return $token->start > 0 && ($source[$token->start - 1] ?? '') === '$';
    }

    private function isPhpMagicConstant(string $text): bool
    {
        return in_array($text, [
            '__CLASS__', '__DIR__', '__FILE__', '__FUNCTION__', '__LINE__',
            '__METHOD__', '__NAMESPACE__', '__TRAIT__',
        ], true);
    }

    private function isElispKeywordOrConstant(string $text): bool
    {
        return in_array($text, [
            'and', 'catch', 'cond', 'defconst', 'defmacro', 'defsubst', 'defun',
            'defvar', 'function', 'if', 'interactive', 'lambda', 'let', 'nil',
            'or', 'prog1', 'prog2', 'progn', 'quote', 'setq', 't', 'while',
        ], true);
    }

    private function isPythonBuiltinFunctionCall(string $source, Token $token): bool
    {
        if (!in_array($token->text, $this->pythonBuiltinFunctionNames(), true)) {
            return false;
        }

        return $this->nextNonWhitespaceCharacter($source, $token->end) === '(';
    }

    private function isPythonBuiltinTypeName(string $text): bool
    {
        return in_array($text, [
            'Any', 'Callable', 'Dict', 'Iterable', 'Iterator', 'List', 'Literal',
            'Mapping', 'MutableMapping', 'Optional', 'Sequence', 'Set', 'Tuple',
            'TypeAlias', 'Union', 'bool', 'bytes', 'dict', 'float', 'int',
            'list', 'set', 'str', 'tuple',
        ], true);
    }

    private function isPythonBuiltinVariable(string $text): bool
    {
        return in_array($text, ['self', 'cls'], true);
    }

    private function isPythonTypeAnnotationContext(string $source, Token $token): bool
    {
        $previous = $this->previousNonWhitespaceCharacter($source, $token->start);
        if ($previous === ':') {
            $colon = $this->previousNonWhitespaceCharacterPosition($source, $token->start);
            $beforeColon = $colon === null ? null : $this->previousNonWhitespaceCharacter($source, $colon);

            return $beforeColon !== ')' && $beforeColon !== ']';
        }

        if ($previous === '[' || $previous === ',' || $previous === '|') {
            return $this->pythonLinePrefixHasAnnotationMarker($source, $token->start)
                || $this->pythonPreviousContinuationHasAnnotationMarker($source, $token->start);
        }

        if ($previous !== '>') {
            return false;
        }

        return $this->previousNonWhitespaceCharacters($source, $token->start, 2) === '->';
    }

    private function isPythonQualifiedTypeAnnotationContext(string $source, Token $token): bool
    {
        $dot = $this->previousNonWhitespaceCharacterPosition($source, $token->start);
        if ($dot === null || ($source[$dot] ?? '') !== '.') {
            return false;
        }

        $moduleEnd = $dot;
        $moduleStart = $moduleEnd;
        while ($moduleStart > 0 && preg_match('/[A-Za-z0-9_]/', $source[$moduleStart - 1]) === 1) {
            $moduleStart--;
        }

        $module = substr($source, $moduleStart, $moduleEnd - $moduleStart);
        if (!in_array($module, ['typing', 'typing_extensions'], true)) {
            return false;
        }

        $moduleToken = new Token('identifier', $module, start: $moduleStart, end: $moduleEnd);

        return $this->isPythonTypeAnnotationContext($source, $moduleToken)
            || $this->pythonLinePrefixHasAnnotationMarker($source, $moduleStart)
            || $this->pythonPreviousContinuationHasAnnotationMarker($source, $moduleStart);
    }

    private function isPythonStringizedAnnotationContext(string $source, Token $token): bool
    {
        if (!$this->pythonStringLooksLikeTypeExpression($token->text)) {
            return false;
        }

        if ($this->isPythonTypeAnnotationContext($source, $token)) {
            return true;
        }

        if (!$this->pythonHasFutureAnnotationsImport($source)) {
            return false;
        }

        return $this->isPythonLikelyTypeAliasAssignment($source, $token);
    }

    private function pythonStringLooksLikeTypeExpression(string $text): bool
    {
        $inner = trim($text, "\"'");
        if ($inner === '') {
            return false;
        }

        if (
            preg_match('/\b(?:bool|bytes|dict|float|int|list|set|str|tuple|Any|Callable|Dict|Iterable|Iterator|List|Literal|Mapping|MutableMapping|Optional|Sequence|Set|Tuple|TypeAlias|Union)\b/', $inner) !== 1
            && preg_match('/(?:^|[.\[\|,\s])(?:[A-Z][A-Za-z0-9_]*|typing(?:_extensions)?\.[A-Za-z_][A-Za-z0-9_]*)(?:$|[\]\|,\s])/', $inner) !== 1
        ) {
            return false;
        }

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*(?:\s*(?:\[|\]|\||,|\.|\(|\)|[A-Za-z0-9_]))*$/', $inner) === 1;
    }

    private function pythonHasFutureAnnotationsImport(string $source): bool
    {
        return preg_match('/^\s*from\s+__future__\s+import\s+(?:[A-Za-z_][A-Za-z0-9_]*\s*,\s*)*annotations(?:\s*,|\s|$)/m', $source) === 1;
    }

    private function isPythonLikelyTypeAliasAssignment(string $source, Token $token): bool
    {
        $lineStart = max(strrpos(substr($source, 0, $token->start), "\n") ?: 0, 0);
        if ($lineStart > 0) {
            $lineStart++;
        }
        $prefix = substr($source, $lineStart, $token->start - $lineStart);

        return preg_match('/^\s*[A-Z][A-Za-z0-9_]*\s*=\s*$/', $prefix) === 1
            || preg_match('/^\s*[A-Z][A-Za-z0-9_]*\s*:\s*(?:[A-Za-z_][A-Za-z0-9_]*\.)?TypeAlias\s*=\s*$/', $prefix) === 1;
    }

    /**
     * @return list<string>
     */
    private function pythonBuiltinFunctionNames(): array
    {
        return [
            '__import__', 'abs', 'all', 'any', 'ascii', 'bin', 'bool',
            'breakpoint', 'bytearray', 'bytes', 'callable', 'chr',
            'classmethod', 'compile', 'complex', 'delattr', 'dict', 'dir',
            'divmod', 'enumerate', 'eval', 'exec', 'filter', 'float',
            'format', 'frozenset', 'getattr', 'globals', 'hasattr', 'hash',
            'help', 'hex', 'id', 'input', 'int', 'isinstance', 'issubclass',
            'iter', 'len', 'list', 'locals', 'map', 'max', 'memoryview',
            'min', 'next', 'object', 'oct', 'open', 'ord', 'pow', 'print',
            'property', 'range', 'repr', 'reversed', 'round', 'set',
            'setattr', 'slice', 'sorted', 'staticmethod', 'str', 'sum',
            'super', 'tuple', 'type', 'vars', 'zip',
        ];
    }

    private function isUppercaseIdentifier(string $text): bool
    {
        return preg_match('/^[A-Z][A-Za-z0-9_$]*$/', $text) === 1;
    }

    private function pythonLinePrefixHasAnnotationMarker(string $source, int $start): bool
    {
        $lineStart = max(strrpos(substr($source, 0, $start), "\n") ?: 0, 0);
        if ($lineStart > 0) {
            $lineStart++;
        }
        $prefix = substr($source, $lineStart, $start - $lineStart);

        return str_contains($prefix, ':') || str_contains($prefix, '->');
    }

    private function pythonPreviousContinuationHasAnnotationMarker(string $source, int $start): bool
    {
        $prefix = substr($source, max(0, $start - 500), $start - max(0, $start - 500));
        $openSquare = strrpos($prefix, '[');
        if ($openSquare === false) {
            return false;
        }

        $continuation = substr($prefix, $openSquare);
        if ($this->pythonSquareBracketBalance($continuation) <= 0) {
            return false;
        }
        if (str_contains($continuation, "]:\n")) {
            return false;
        }

        $beforeOpen = substr($prefix, 0, $openSquare);
        $lastStatementBreak = max(
            strrpos($beforeOpen, "\n\n") ?: -1,
            strrpos($beforeOpen, ';') ?: -1,
        );
        $region = substr($beforeOpen, $lastStatementBreak + 1);

        return str_contains($region, ':') || str_contains($region, '->');
    }

    private function pythonSquareBracketBalance(string $text): int
    {
        $balance = 0;
        $quote = null;
        $escaped = false;
        $length = strlen($text);
        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }
            if ($character === '[') {
                $balance++;
            } elseif ($character === ']') {
                $balance--;
            }
        }

        return $balance;
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

    private function previousNonWhitespaceCharacterPosition(string $source, int $start): ?int
    {
        for ($index = $start - 1; $index >= 0; $index--) {
            $character = $source[$index];
            if ($character === ' ' || $character === "\t" || $character === "\n" || $character === "\r") {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function previousNonWhitespaceCharacters(string $source, int $start, int $count): string
    {
        $characters = '';
        for ($index = $start - 1; $index >= 0 && strlen($characters) < $count; $index--) {
            $character = $source[$index];
            if ($character === ' ' || $character === "\t" || $character === "\n" || $character === "\r") {
                continue;
            }
            $characters = $character . $characters;
        }

        return $characters;
    }

    private function nextNonWhitespaceCharacter(string $source, int $start): ?string
    {
        $length = strlen($source);
        for ($index = $start; $index < $length; $index++) {
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
            'bash', 'sh', 'shell' => [
                'case', 'do', 'done', 'elif', 'else', 'esac', 'export', 'fi',
                'for', 'function', 'if', 'in', 'select', 'then', 'unset',
                'until', 'while',
            ],
            'c', 'cc', 'cpp', 'c++', 'h', 'hpp', 'objc', 'objective-c' => [
                'auto', 'break', 'case', 'catch', 'class', 'const', 'constexpr',
                'continue', 'default', 'delete', 'do', 'else', 'enum', 'extern',
                'for', 'goto', 'if', 'namespace', 'new', 'private', 'protected',
                'public', 'return', 'sizeof', 'static', 'struct', 'switch',
                'template', 'throw', 'try', 'typedef', 'typename', 'union',
                'using', 'while',
            ],
            'javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx' => [
                'as', 'assert', 'async', 'await', 'break', 'case', 'catch', 'class',
                'const', 'default', 'delete', 'do', 'else', 'export', 'extends',
                'false', 'finally', 'for', 'from', 'function', 'if', 'import', 'in',
                'instanceof', 'let', 'new', 'null', 'of', 'return', 'static',
                'switch', 'throw', 'true', 'try', 'type', 'typeof', 'undefined',
                'var', 'while', 'with', 'yield',
            ],
            'json' => ['false', 'null', 'true'],
            'lua' => [
                'and', 'break', 'do', 'else', 'elseif', 'end', 'for',
                'function', 'goto', 'if', 'in', 'local', 'not', 'or',
                'repeat', 'return', 'then', 'until', 'while',
            ],
            'go', 'golang' => [
                'break', 'case', 'chan', 'const', 'continue', 'defer', 'default',
                'else', 'fallthrough', 'for', 'func', 'go', 'goto', 'if',
                'import', 'interface', 'map', 'package', 'range', 'return',
                'select', 'struct', 'switch', 'type', 'var',
            ],
            'java' => [
                'abstract', 'assert', 'break', 'case', 'catch', 'class',
                'continue', 'default', 'do', 'else', 'enum', 'extends',
                'false', 'final', 'finally', 'for', 'if', 'implements',
                'import', 'instanceof', 'interface', 'new', 'null', 'package',
                'private', 'protected', 'public', 'return', 'static', 'super',
                'switch', 'this', 'throw', 'throws', 'true', 'try', 'while',
            ],
            'c#', 'csharp' => [
                'abstract', 'as', 'base', 'break', 'case', 'catch', 'checked',
                'class', 'const', 'continue', 'default', 'delegate', 'do',
                'else', 'enum', 'event', 'explicit', 'extern', 'false',
                'finally', 'fixed', 'for', 'foreach', 'if', 'implicit', 'in',
                'interface', 'internal', 'is', 'lock', 'namespace', 'new',
                'null', 'operator', 'out', 'override', 'params', 'private',
                'protected', 'public', 'readonly', 'ref', 'return', 'sealed',
                'sizeof', 'stackalloc', 'static', 'struct', 'switch', 'this',
                'throw', 'true', 'try', 'typeof', 'unchecked', 'unsafe',
                'using', 'virtual', 'void', 'volatile', 'while',
            ],
            'php', 'hack', 'hh' => [
                'case', 'catch', 'class', 'declare', 'default', 'else', 'extends',
                'false', 'finally', 'for', 'foreach', 'function', 'if', 'implements',
                'include', 'include_once', 'interface', 'match', 'namespace', 'new',
                'null', 'private', 'protected', 'public', 'require', 'require_once',
                'return', 'static', 'switch', 'throw', 'trait', 'true', 'try', 'use',
                'while', 'yield',
            ],
            'python', 'py' => [
                'and', 'as', 'assert', 'async', 'await', 'break', 'class',
                'continue', 'case', 'def', 'del', 'elif', 'else', 'except',
                'exec', 'finally', 'false', 'for', 'from', 'global', 'if',
                'import', 'in', 'is', 'lambda', 'match', 'none', 'nonlocal',
                'not', 'or', 'pass', 'print', 'raise', 'return', 'true', 'try',
                'while', 'with', 'yield',
            ],
            'rust', 'rs' => [
                'async', 'await', 'const', 'crate', 'else', 'enum', 'extern',
                'false', 'fn', 'for', 'if', 'impl', 'let', 'loop', 'match', 'mod',
                'mut', 'pub', 'return', 'self', 'static', 'struct', 'super',
                'trait', 'true', 'type', 'unsafe', 'use', 'where', 'while',
            ],
            'ruby', 'rb' => [
                'alias', 'and', 'begin', 'break', 'case', 'class', 'def', 'do',
                'else', 'elsif', 'end', 'ensure', 'false', 'for', 'if', 'in',
                'module', 'next', 'nil', 'or', 'private', 'protected', 'public',
                'rescue', 'retry', 'return', 'then', 'true', 'unless', 'until',
                'when', 'while', 'yield',
            ],
            'sql' => [
                'add', 'after', 'alter', 'and', 'as', 'begin', 'between', 'by',
                'case', 'column', 'commit', 'constraint', 'create', 'cross',
                'database', 'default', 'delete', 'distinct', 'do', 'drop',
                'else', 'end', 'exists', 'foreign', 'from', 'full', 'group',
                'having', 'if', 'in', 'index', 'inner', 'insert', 'into', 'is',
                'join', 'key', 'lateral', 'left', 'like', 'limit', 'natural',
                'not', 'offset', 'on', 'or', 'order', 'outer', 'primary',
                'references', 'rename', 'replace', 'returning', 'right',
                'rollback', 'select', 'set', 'table', 'then', 'to',
                'transaction', 'union', 'unique', 'update', 'using', 'values',
                'view', 'when', 'where', 'with',
            ],
            'swift' => [
                'as', 'associatedtype', 'break', 'case', 'catch', 'class',
                'continue', 'default', 'defer', 'deinit', 'do', 'else', 'enum',
                'extension', 'fallthrough', 'false', 'for', 'func', 'guard',
                'if', 'import', 'in', 'init', 'inout', 'is', 'let', 'nil',
                'operator', 'private', 'protocol', 'public', 'return',
                'self', 'static', 'struct', 'subscript', 'super', 'switch',
                'throw', 'throws', 'true', 'try', 'typealias', 'var', 'where',
                'while',
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
            'c', 'cc', 'cpp', 'c++', 'h', 'hpp', 'objc', 'objective-c' => [
                'bool', 'char', 'double', 'float', 'int', 'int16_t', 'int32_t',
                'int64_t', 'int8_t', 'long', 'short', 'size_t', 'uint16_t',
                'uint32_t', 'uint64_t', 'uint8_t', 'void',
            ],
            'javascript', 'js', 'jsx', 'typescript', 'ts', 'tsx' => [
                'any', 'array', 'bigint', 'boolean', 'never', 'number', 'object',
                'promise', 'record', 'string', 'symbol', 'unknown', 'void',
            ],
            'go', 'golang' => [
                'any', 'bool', 'byte', 'comparable', 'complex64', 'complex128',
                'error', 'float32', 'float64', 'int', 'int8', 'int16', 'int32',
                'int64', 'rune', 'string', 'uint', 'uint8', 'uint16', 'uint32',
                'uint64', 'uintptr',
            ],
            'java' => [
                'boolean', 'byte', 'char', 'double', 'float', 'int', 'long',
                'short', 'void',
            ],
            'c#', 'csharp' => [
                'bool', 'byte', 'char', 'decimal', 'double', 'dynamic', 'float',
                'int', 'long', 'nint', 'nuint', 'object', 'sbyte', 'short',
                'string', 'uint', 'ulong', 'ushort', 'var', 'void',
            ],
            'lua' => ['boolean', 'function', 'nil', 'number', 'string', 'table', 'thread', 'userdata'],
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
            'sql' => [
                'bigint', 'binary', 'bit', 'boolean', 'char', 'character',
                'date', 'datetime', 'decimal', 'double', 'enum', 'float',
                'inet', 'int', 'integer', 'json', 'jsonb', 'mediumint',
                'null', 'numeric', 'real', 'serial', 'smallint', 'string', 'text',
                'time', 'timestamp', 'tinyint', 'uuid', 'varchar', 'varying',
                'xml',
            ],
            'swift' => [
                'any', 'bool', 'character', 'double', 'float', 'int', 'int8',
                'int16', 'int32', 'int64', 'never', 'string', 'uint', 'uint8',
                'uint16', 'uint32', 'uint64', 'void',
            ],
            default => [],
        };
    }
}
