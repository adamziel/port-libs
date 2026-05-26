<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class JsLexer
{
    /**
     * @return list<Token>
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $source, $m, 0, $offset)) {
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G\/\/[^\r\n]*/A', $source, $m, 0, $offset) || preg_match('/\G\/\*.*?\*\//As', $source, $m, 0, $offset)) {
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G[$_\pL][$_\pL\pN]*/Au', $source, $m, 0, $offset)) {
                $tokens[] = new Token('identifier', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G(?:0[xX][0-9a-fA-F]+|\d+(?:\.\d+)?)/A', $source, $m, 0, $offset)) {
                $tokens[] = new Token('number', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/As', $source, $m, 0, $offset)) {
                $tokens[] = new Token('string', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G=>|===|!==|==|!=|<=|>=|\+\+|--|&&|\|\||[{}()[\].,;:?+\-*\/%<>=!]/A', $source, $m, 0, $offset)) {
                $tokens[] = new Token('punctuator', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }

            throw new \InvalidArgumentException('Unexpected JavaScript byte at offset ' . $offset);
        }

        return $tokens;
    }
}

