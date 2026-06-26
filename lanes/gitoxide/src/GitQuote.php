<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitQuote
{
    private function __construct()
    {
    }

    public static function single(string $value): string
    {
        $quoted = "'";
        $offset = 0;

        while (($position = self::findFirstSingleSpecial($value, $offset)) !== null) {
            $quoted .= substr($value, $offset, $position - $offset);
            $quoted .= "'\\" . $value[$position] . "'";
            $offset = $position + 1;
        }

        return $quoted . substr($value, $offset) . "'";
    }

    /**
     * @return array{value: string, consumed: int}
     */
    public static function ansiCUndo(string $input): array
    {
        $length = strlen($input);
        if ($length === 0 || $input[0] !== '"') {
            return ['value' => $input, 'consumed' => $length];
        }
        if ($length < 2) {
            throw new \InvalidArgumentException('Input must be surrounded by double quotes');
        }

        $cursor = 1;
        $consumed = 1;
        $out = '';

        while (true) {
            $position = self::findFirstAnsiSpecial($input, $cursor);
            if ($position === null) {
                $out .= substr($input, $cursor);
                $consumed += $length - $cursor;
                break;
            }

            $out .= substr($input, $cursor, $position - $cursor);
            $consumed += ($position - $cursor) + 1;

            if ($input[$position] === '"') {
                break;
            }

            $nextPosition = $position + 1;
            if ($nextPosition >= $length) {
                throw new \InvalidArgumentException('Unexpected end of input');
            }

            $next = $input[$nextPosition];
            $cursor = $nextPosition + 1;
            $consumed++;

            if ($next === 'n') {
                $out .= "\n";
            } elseif ($next === 'r') {
                $out .= "\r";
            } elseif ($next === 't') {
                $out .= "\t";
            } elseif ($next === 'a') {
                $out .= "\x07";
            } elseif ($next === 'b') {
                $out .= "\x08";
            } elseif ($next === 'v') {
                $out .= "\x0b";
            } elseif ($next === 'f') {
                $out .= "\x0c";
            } elseif ($next === '"') {
                $out .= '"';
            } elseif ($next === '\\') {
                $out .= '\\';
            } elseif ($next >= '0' && $next <= '3') {
                if ($cursor + 2 > $length) {
                    throw new \InvalidArgumentException('Unexpected end of input when fetching two more octal bytes');
                }

                $digits = $next . substr($input, $cursor, 2);
                if (!self::isOctalDigits($digits)) {
                    throw new \InvalidArgumentException('Invalid octal escape value');
                }

                $out .= chr(octdec($digits));
                $cursor += 2;
                $consumed += 2;
            } else {
                throw new \InvalidArgumentException('Invalid escaped value ' . ord($next));
            }
        }

        return ['value' => $out, 'consumed' => $consumed];
    }

    private static function findFirstSingleSpecial(string $value, int $offset): ?int
    {
        $quote = strpos($value, "'", $offset);
        $bang = strpos($value, '!', $offset);

        if ($quote === false) {
            return $bang === false ? null : $bang;
        }
        if ($bang === false) {
            return $quote;
        }

        return min($quote, $bang);
    }

    private static function findFirstAnsiSpecial(string $value, int $offset): ?int
    {
        $quote = strpos($value, '"', $offset);
        $backslash = strpos($value, '\\', $offset);

        if ($quote === false) {
            return $backslash === false ? null : $backslash;
        }
        if ($backslash === false) {
            return $quote;
        }

        return min($quote, $backslash);
    }

    private static function isOctalDigits(string $digits): bool
    {
        for ($i = 0; $i < 3; $i++) {
            $byte = ord($digits[$i]);
            if ($byte < 48 || $byte > 55) {
                return false;
            }
        }

        return true;
    }
}
