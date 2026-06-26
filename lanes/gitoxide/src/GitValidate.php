<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitValidate
{
    public const ERROR_EMPTY = 'Empty';
    public const ERROR_PARENT_COMPONENT = 'ParentComponent';
    public const ERROR_INVALID_BYTE = 'InvalidByte';
    public const ERROR_STARTS_WITH_SLASH = 'StartsWithSlash';
    public const ERROR_REPEATED_SLASH = 'RepeatedSlash';
    public const ERROR_REPEATED_DOT = 'RepeatedDot';
    public const ERROR_LOCK_FILE_SUFFIX = 'LockFileSuffix';
    public const ERROR_REFLOG_PORTION = 'ReflogPortion';
    public const ERROR_ASTERISK = 'Asterisk';
    public const ERROR_STARTS_WITH_DOT = 'StartsWithDot';
    public const ERROR_ENDS_WITH_DOT = 'EndsWithDot';
    public const ERROR_ENDS_WITH_SLASH = 'EndsWithSlash';

    public static function validateSubmoduleName(string $name): ?string
    {
        if ($name === '') {
            return self::ERROR_EMPTY;
        }

        foreach (preg_split('/[\/\\\\]/', $name) ?: [] as $component) {
            if ($component === '..') {
                return self::ERROR_PARENT_COMPONENT;
            }
        }

        return null;
    }

    public static function isValidSubmoduleName(string $name): bool
    {
        return self::validateSubmoduleName($name) === null;
    }

    public static function assertValidSubmoduleName(string $name): string
    {
        $error = self::validateSubmoduleName($name);
        if ($error !== null) {
            throw new \InvalidArgumentException("Invalid submodule name: {$error}");
        }

        return $name;
    }

    public static function validateTagName(string $name): ?string
    {
        return self::validateTagOrPartialReferenceName($name);
    }

    public static function isValidTagName(string $name): bool
    {
        return self::validateTagName($name) === null;
    }

    public static function assertValidTagName(string $name): string
    {
        $error = self::validateTagName($name);
        if ($error !== null) {
            throw new \InvalidArgumentException("Invalid tag name: {$error}");
        }

        return $name;
    }

    public static function validateReferenceNamePartial(string $name): ?string
    {
        return self::validateTagOrPartialReferenceName($name);
    }

    public static function isValidReferenceNamePartial(string $name): bool
    {
        return self::validateReferenceNamePartial($name) === null;
    }

    public static function sanitizeReferenceNamePartial(string $name): string
    {
        if ($name === '') {
            return '-';
        }

        $out = '';
        $previous = "\0";
        $componentEnd = 0;
        $length = strlen($name);
        $last = $length - 1;

        for ($index = 0; $index < $length; $index++) {
            $byte = $name[$index];
            $ord = ord($byte);

            if (self::isInvalidTagByte($byte, $ord)) {
                $out .= '-';
            } elseif ($byte === '*') {
                $out .= '-';
            } elseif ($byte === '.' && $previous === '.') {
                // Skip consecutive dots, matching gix_validate::tag::name_inner().
            } elseif ($byte === '.' && $previous === '/') {
                $out .= '-';
            } elseif ($byte === '{' && $previous === '@') {
                $out .= '-';
            } elseif ($byte === '/' && $previous === '/') {
                // Skip repeated slashes while preserving the previous-byte state.
            } else {
                if ($byte === '/') {
                    $componentStart = $componentEnd;
                    $componentEnd = $index;
                    if (str_ends_with(substr($name, $componentStart, $componentEnd - $componentStart), '.lock')) {
                        $out = self::trimRepeatedSuffix($out, '.lock');
                    }
                }

                $out .= $byte;

                if ($index === $last && str_ends_with(substr($name, $componentEnd + 1), '.lock')) {
                    $out = self::trimRepeatedSuffix($out, '.lock');
                }
            }

            $previous = $byte;
        }

        $out = rtrim($out, '/');
        $out = ltrim($out, '/');
        if ($out === '') {
            return '-';
        }

        if ($out[0] === '.') {
            $out = '-' . substr($out, 1);
        }

        $lastIndex = strlen($out) - 1;
        if ($out[$lastIndex] === '.') {
            $out = substr($out, 0, -1) . '-';
        }

        return $out === '' ? '-' : $out;
    }

    private static function validateTagOrPartialReferenceName(string $name): ?string
    {
        if ($name === '') {
            return self::ERROR_EMPTY;
        }

        if ($name[strlen($name) - 1] === '/') {
            return self::ERROR_ENDS_WITH_SLASH;
        }

        if ($name[0] === '/') {
            return self::ERROR_STARTS_WITH_SLASH;
        }

        $previous = "\0";
        $componentEnd = 0;
        $length = strlen($name);
        $last = $length - 1;

        for ($index = 0; $index < $length; $index++) {
            $byte = $name[$index];
            $ord = ord($byte);

            if (self::isInvalidTagByte($byte, $ord)) {
                return self::ERROR_INVALID_BYTE;
            }

            if ($byte === '*') {
                return self::ERROR_ASTERISK;
            }

            if ($byte === '.' && $previous === '.') {
                return self::ERROR_REPEATED_DOT;
            }

            if ($byte === '.' && $previous === '/') {
                return self::ERROR_STARTS_WITH_DOT;
            }

            if ($byte === '{' && $previous === '@') {
                return self::ERROR_REFLOG_PORTION;
            }

            if ($byte === '/' && $previous === '/') {
                return self::ERROR_REPEATED_SLASH;
            }

            if ($byte === '/') {
                $componentStart = $componentEnd;
                $componentEnd = $index;
                if (str_ends_with(substr($name, $componentStart, $componentEnd - $componentStart), '.lock')) {
                    return self::ERROR_LOCK_FILE_SUFFIX;
                }
            }

            if ($index === $last && str_ends_with(substr($name, $componentEnd + 1), '.lock')) {
                return self::ERROR_LOCK_FILE_SUFFIX;
            }

            $previous = $byte;
        }

        if ($name[0] === '.') {
            return self::ERROR_STARTS_WITH_DOT;
        }

        if ($name[$last] === '.') {
            return self::ERROR_ENDS_WITH_DOT;
        }

        return null;
    }

    private static function isInvalidTagByte(string $byte, int $ord): bool
    {
        return $byte === '\\'
            || $byte === '^'
            || $byte === ':'
            || $byte === '['
            || $byte === '?'
            || $byte === ' '
            || $byte === '~'
            || $ord <= 0x1f
            || $ord === 0x7f;
    }

    private static function trimRepeatedSuffix(string $input, string $suffix): string
    {
        while (str_ends_with($input, $suffix)) {
            $input = substr($input, 0, -strlen($suffix));
        }

        return $input;
    }
}
