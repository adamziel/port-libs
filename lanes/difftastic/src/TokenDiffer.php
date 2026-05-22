<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class TokenDiffer
{
    /**
     * @return list<Token>
     */
    public function tokenize(string $source): array
    {
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[{}()[\].,;:+*\/<>=!-]+|\S/u', $source, $matches);
        return array_map(static function (string $text): Token {
            $kind = match (true) {
                preg_match('/^[A-Za-z_]/', $text) === 1 => 'identifier',
                preg_match('/^\d/', $text) === 1 => 'number',
                str_starts_with($text, '"') || str_starts_with($text, "'") => 'string',
                default => 'punctuation',
            };
            return new Token($kind, $text);
        }, $matches[0] ?? []);
    }

    /**
     * @return list<array{op:string, text:string}>
     */
    public function diff(string $old, string $new): array
    {
        $a = array_map(static fn (Token $token): string => $token->text, $this->tokenize($old));
        $b = array_map(static fn (Token $token): string => $token->text, $this->tokenize($new));
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
}

