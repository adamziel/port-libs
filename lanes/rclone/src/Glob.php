<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class Glob
{
    public static function pathToRegex(string $glob, bool $ignoreCase = false): string
    {
        $regex = $ignoreCase ? '(?i)' : '';
        if (str_starts_with($glob, '/')) {
            $glob = substr($glob, 1);
            $regex .= '^';
        } else {
            $regex .= '(^|/)';
        }

        $stars = 0;
        $inBraces = false;
        $inBrackets = 0;
        $slashed = false;
        $inRegexp = false;
        $inRegexpEnd = false;
        $last = null;

        $insertStars = static function () use (&$stars, &$regex, $glob): void {
            if ($stars === 0) {
                return;
            }
            if ($stars === 1) {
                $regex .= '[^/]*';
            } elseif ($stars === 2) {
                $regex .= '.*';
            } else {
                throw new \InvalidArgumentException("too many stars in glob {$glob}");
            }
            $stars = 0;
        };

        foreach (str_split($glob) as $char) {
            if ($slashed) {
                $regex .= $char;
                $slashed = false;
                $last = $char;
                continue;
            }

            if ($inRegexpEnd) {
                if ($char === '}') {
                    $regex = substr($regex, 0, -1) . '})';
                    $last = $char;
                    continue;
                }
                $inRegexpEnd = false;
            }

            if ($inRegexp) {
                if ($char === '}' && $last === '}') {
                    $inRegexp = false;
                    $inRegexpEnd = true;
                    $regex = substr($regex, 0, -1) . ')';
                } else {
                    $regex .= $char;
                }
                $last = $char;
                continue;
            }

            if ($char !== '*') {
                $insertStars();
            }

            if ($inBrackets > 0) {
                $regex .= $char;
                if ($char === '[') {
                    $inBrackets++;
                } elseif ($char === ']') {
                    $inBrackets--;
                }
                $last = $char;
                continue;
            }

            switch ($char) {
                case '\\':
                    $regex .= '\\';
                    $slashed = true;
                    break;
                case '*':
                    $stars++;
                    break;
                case '?':
                    $regex .= '[^/]';
                    break;
                case '[':
                    $regex .= '[';
                    $inBrackets++;
                    break;
                case ']':
                    throw new \InvalidArgumentException("mismatched ']' in glob {$glob}");
                case '{':
                    if ($inBraces) {
                        if ($last === '{') {
                            $inRegexp = true;
                            $inBraces = false;
                        } else {
                            throw new \InvalidArgumentException("can't nest braces in glob {$glob}");
                        }
                    } else {
                        $inBraces = true;
                        $regex .= '(';
                    }
                    break;
                case '}':
                    if (!$inBraces) {
                        throw new \InvalidArgumentException("mismatched braces in glob {$glob}");
                    }
                    $regex .= ')';
                    $inBraces = false;
                    break;
                case ',':
                    $regex .= $inBraces ? '|' : ',';
                    break;
                case '.':
                case '+':
                case '(':
                case ')':
                case '|':
                case '^':
                case '$':
                    $regex .= '\\' . $char;
                    break;
                default:
                    $regex .= $char;
                    break;
            }

            $last = $char;
        }

        $insertStars();

        if ($inBrackets > 0) {
            throw new \InvalidArgumentException("mismatched '[' and ']' in glob {$glob}");
        }
        if ($inBraces) {
            throw new \InvalidArgumentException("mismatched braces in glob {$glob}");
        }
        if ($inRegexp) {
            throw new \InvalidArgumentException("mismatched '{{' and '}}' in glob {$glob}");
        }

        $regex .= '$';
        self::assertValidRegex($regex, $glob);

        return $regex;
    }

    public static function pathMatches(string $glob, string $path, bool $ignoreCase = false): bool
    {
        return self::matchesRegex(self::pathToRegex($glob, $ignoreCase), $path);
    }

    public static function matchesRegex(string $regex, string $path): bool
    {
        $result = preg_match(self::delimit($regex), self::normalizePath($path));
        if ($result === false) {
            throw new \InvalidArgumentException("bad regex {$regex}");
        }

        return $result === 1;
    }

    private static function assertValidRegex(string $regex, string $glob): void
    {
        if (@preg_match(self::delimit($regex), '') === false) {
            throw new \InvalidArgumentException("bad glob pattern {$glob}");
        }
    }

    private static function delimit(string $regex): string
    {
        return '~' . str_replace('~', '\\~', $regex) . '~';
    }

    private static function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}
