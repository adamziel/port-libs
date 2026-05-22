<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class TokenDiffer
{
    private const OPEN_DELIMITERS = ['(' => true, '[' => true, '{' => true];
    private const CLOSE_DELIMITERS = [')' => true, ']' => true, '}' => true];

    /**
     * @return list<Token>
     */
    public function tokenize(string $source): array
    {
        preg_match_all('/<!--[\s\S]*?-->|\/\*[\s\S]*?\*\/|\/\/[^\r\n]*|[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|===|!==|==|!=|<=|>=|=>|->|::|&&|\|\||[{}()[\].,;:+*\/<>=!-]|\S/u', $source, $matches);

        $tokens = [];
        $depth = 0;
        foreach ($matches[0] ?? [] as $text) {
            $delimiterRole = null;
            if (isset(self::CLOSE_DELIMITERS[$text])) {
                $depth = max(0, $depth - 1);
                $delimiterRole = 'close';
            } elseif (isset(self::OPEN_DELIMITERS[$text])) {
                $delimiterRole = 'open';
            }

            $tokens[] = new Token($this->classify($text), $text, $delimiterRole, $depth);

            if ($delimiterRole === 'open') {
                $depth++;
            }
        }

        return $tokens;
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool} $options
     * @return list<array{op:string, text:string}>
     */
    public function diff(string $old, string $new, array $options = []): array
    {
        $a = array_map(static fn (Token $token): string => $token->text, $this->tokensForDiff($old, $options));
        $b = array_map(static fn (Token $token): string => $token->text, $this->tokensForDiff($new, $options));
        $table = array_fill(0, count($a) + 1, array_fill(0, count($b) + 1, 0));
        for ($i = count($a) - 1; $i >= 0; $i--) {
            for ($j = count($b) - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < count($a) && $j < count($b)) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['op' => '=', 'text' => $a[$i++]];
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['op' => '-', 'text' => $a[$i++]];
            } else {
                $ops[] = ['op' => '+', 'text' => $b[$j++]];
            }
        }
        while ($i < count($a)) {
            $ops[] = ['op' => '-', 'text' => $a[$i++]];
        }
        while ($j < count($b)) {
            $ops[] = ['op' => '+', 'text' => $b[$j++]];
        }

        return $ops;
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool} $options
     */
    public function hasChanges(string $old, string $new, array $options = []): bool
    {
        foreach ($this->diff($old, $new, $options) as $op) {
            if ($op['op'] !== '=') {
                return true;
            }
        }

        return false;
    }

    private function classify(string $text): string
    {
        return match (true) {
            str_starts_with($text, '/*'),
            str_starts_with($text, '//'),
            str_starts_with($text, '<!--') => 'comment',
            preg_match('/^[A-Za-z_]/', $text) === 1 => 'identifier',
            preg_match('/^\d/', $text) === 1 => 'number',
            str_starts_with($text, '"') || str_starts_with($text, "'") => 'string',
            isset(self::OPEN_DELIMITERS[$text]) || isset(self::CLOSE_DELIMITERS[$text]) => 'delimiter',
            default => 'punctuation',
        };
    }

    /**
     * @param array{ignoreComments?: bool, ignoreTrailingCommas?: bool} $options
     * @return list<Token>
     */
    private function tokensForDiff(string $source, array $options): array
    {
        $tokens = $this->tokenize($source);

        if (($options['ignoreComments'] ?? false) === true) {
            $tokens = array_values(array_filter(
                $tokens,
                static fn (Token $token): bool => $token->kind !== 'comment',
            ));
        }

        if (($options['ignoreTrailingCommas'] ?? true) === true) {
            $tokens = $this->removeIgnoredTrailingCommas($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function removeIgnoredTrailingCommas(array $tokens): array
    {
        $kept = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->text === ',' && $this->nextTokenClosesList($tokens, $i + 1)) {
                continue;
            }

            $kept[] = $token;
        }

        return $kept;
    }

    /**
     * @param list<Token> $tokens
     */
    private function nextTokenClosesList(array $tokens, int $offset): bool
    {
        $count = count($tokens);
        for ($i = $offset; $i < $count; $i++) {
            if ($tokens[$i]->kind === 'comment') {
                continue;
            }

            return $tokens[$i]->delimiterRole === 'close';
        }

        return false;
    }
}
