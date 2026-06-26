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
    public const ERROR_PATH_SEPARATOR = 'PathSeparator';
    public const ERROR_WINDOWS_PATH_PREFIX = 'WindowsPathPrefix';
    public const ERROR_WINDOWS_RESERVED_NAME = 'WindowsReservedName';
    public const ERROR_WINDOWS_ILLEGAL_CHARACTER = 'WindowsIllegalCharacter';
    public const ERROR_DOT_GIT_DIR = 'DotGitDir';
    public const ERROR_SYMLINKED_GIT_MODULES = 'SymlinkedGitModules';
    public const ERROR_RELATIVE = 'Relative';
    public const ERROR_SOME_LOWERCASE = 'SomeLowercase';
    public const ERROR_RESERVED = 'Reserved';

    public const PATH_MODE_SYMLINK = 'Symlink';

    private const HFS_IGNORABLE_CODEPOINTS = [
        "\u{200c}",
        "\u{200d}",
        "\u{200e}",
        "\u{200f}",
        "\u{202a}",
        "\u{202b}",
        "\u{202c}",
        "\u{202d}",
        "\u{202e}",
        "\u{206a}",
        "\u{206b}",
        "\u{206c}",
        "\u{206d}",
        "\u{206e}",
        "\u{206f}",
        "\u{feff}",
    ];

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

    /**
     * @return array{protect_windows: bool, protect_hfs: bool, protect_ntfs: bool}
     */
    public static function pathComponentOptions(
        bool $protectWindows = true,
        bool $protectHfs = true,
        bool $protectNtfs = true
    ): array {
        return [
            'protect_windows' => $protectWindows,
            'protect_hfs' => $protectHfs,
            'protect_ntfs' => $protectNtfs,
        ];
    }

    /**
     * @param array{protect_windows?: bool, protect_hfs?: bool, protect_ntfs?: bool} $options
     */
    public static function validatePathComponent(string $component, ?string $mode = null, array $options = []): ?string
    {
        $protectWindows = $options['protect_windows'] ?? true;
        $protectHfs = $options['protect_hfs'] ?? true;
        $protectNtfs = $options['protect_ntfs'] ?? true;

        if ($component === '') {
            return self::ERROR_EMPTY;
        }

        if ($component === '..' || $component === '.') {
            return self::ERROR_RELATIVE;
        }

        if ($protectWindows) {
            if (str_contains($component, '/') || str_contains($component, '\\')) {
                return self::ERROR_PATH_SEPARATOR;
            }

            if (self::secondUtf8CharacterIsColon($component)) {
                return self::ERROR_WINDOWS_PATH_PREFIX;
            }
        } elseif (str_contains($component, '/')) {
            return self::ERROR_PATH_SEPARATOR;
        }

        $isSymlink = $mode === self::PATH_MODE_SYMLINK;
        if ($protectHfs) {
            if (self::isDotHfs($component, 'git')) {
                return self::ERROR_DOT_GIT_DIR;
            }

            if ($isSymlink && self::isDotHfs($component, 'gitmodules')) {
                return self::ERROR_SYMLINKED_GIT_MODULES;
            }
        }

        if ($protectNtfs) {
            if (self::isDotGitNtfs($component)) {
                return self::ERROR_DOT_GIT_DIR;
            }

            if ($isSymlink && self::isDotNtfs($component, 'gitmodules', 'gi7eba')) {
                return self::ERROR_SYMLINKED_GIT_MODULES;
            }

            if ($protectWindows) {
                $windowsError = self::windowsPathComponentError($component);
                if ($windowsError !== null) {
                    return $windowsError;
                }
            }
        }

        if (!$protectHfs && !$protectNtfs) {
            if (self::asciiEqualsIgnoreCase($component, '.git')) {
                return self::ERROR_DOT_GIT_DIR;
            }

            if ($isSymlink && self::asciiEqualsIgnoreCase($component, '.gitmodules')) {
                return self::ERROR_SYMLINKED_GIT_MODULES;
            }
        }

        return null;
    }

    /**
     * @param array{protect_windows?: bool, protect_hfs?: bool, protect_ntfs?: bool} $options
     */
    public static function isValidPathComponent(string $component, ?string $mode = null, array $options = []): bool
    {
        return self::validatePathComponent($component, $mode, $options) === null;
    }

    public static function isWindowsDevicePathComponent(string $component): bool
    {
        return self::isWinDevice($component);
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

    public static function validateReferenceName(string $name): ?string
    {
        $error = self::validateReferenceNamePartial($name);
        if ($error !== null) {
            return $error;
        }

        if (!str_contains($name, '/') && !self::isAllAsciiUppercaseOrUnderscore($name)) {
            return self::ERROR_SOME_LOWERCASE;
        }

        return null;
    }

    public static function isValidReferenceName(string $name): bool
    {
        return self::validateReferenceName($name) === null;
    }

    public static function assertValidReferenceName(string $name): string
    {
        $error = self::validateReferenceName($name);
        if ($error !== null) {
            throw new \InvalidArgumentException("Invalid reference name: {$error}");
        }

        return $name;
    }

    public static function validateBranchName(string $name): ?string
    {
        $error = self::validateReferenceName($name);
        if ($error !== null) {
            return $error;
        }

        if ($name === 'refs/heads/HEAD') {
            return self::ERROR_RESERVED;
        }

        return null;
    }

    public static function isValidBranchName(string $name): bool
    {
        return self::validateBranchName($name) === null;
    }

    public static function assertValidBranchName(string $name): string
    {
        $error = self::validateBranchName($name);
        if ($error !== null) {
            throw new \InvalidArgumentException("Invalid branch name: {$error}");
        }

        return $name;
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

    private static function secondUtf8CharacterIsColon(string $input): bool
    {
        if (preg_match('/^.:/us', $input) === 1) {
            return true;
        }

        return strlen($input) > 1 && $input[1] === ':';
    }

    private static function isDotHfs(string $input, string $searchCaseInsensitive): bool
    {
        $filtered = str_replace(self::HFS_IGNORABLE_CODEPOINTS, '', $input);
        $expectedLength = strlen($searchCaseInsensitive) + 1;

        return strlen($filtered) === $expectedLength
            && $filtered[0] === '.'
            && self::asciiEqualsIgnoreCase(substr($filtered, 1), $searchCaseInsensitive);
    }

    private static function isDotGitNtfs(string $input): bool
    {
        if (
            strlen($input) >= 4
            && self::asciiEqualsIgnoreCase(substr($input, 0, 4), '.git')
        ) {
            return self::isDoneNtfs(substr($input, 4));
        }

        if (
            strlen($input) >= 5
            && self::asciiEqualsIgnoreCase(substr($input, 0, 5), 'git~1')
        ) {
            return self::isDoneNtfs(substr($input, 5));
        }

        return false;
    }

    private static function isDotNtfs(string $input, string $searchCaseInsensitive, string $ntfsShortnamePrefix): bool
    {
        if (($input[0] ?? '') === '.') {
            $endPosition = 1 + strlen($searchCaseInsensitive);
            if (
                strlen($input) >= $endPosition
                && self::asciiEqualsIgnoreCase(substr($input, 1, strlen($searchCaseInsensitive)), $searchCaseInsensitive)
            ) {
                return self::isDoneNtfs(substr($input, $endPosition));
            }

            return false;
        }

        if (
            strlen($input) >= 8
            && self::asciiEqualsIgnoreCase(substr($input, 0, 6), substr($searchCaseInsensitive, 0, 6))
            && $input[6] === '~'
            && self::byteInRange($input[7], '1', '4')
        ) {
            return self::isDoneNtfs(substr($input, 8));
        }

        $sawTilde = false;
        $position = 0;
        while ($position < 8) {
            if ($position >= strlen($input)) {
                return false;
            }

            $byte = $input[$position];
            $ord = ord($byte);
            if ($sawTilde) {
                if (!self::isAsciiDigit($byte)) {
                    return false;
                }
            } elseif ($byte === '~') {
                $sawTilde = true;
                $position++;
                if ($position >= strlen($input)) {
                    return false;
                }

                $byte = $input[$position];
                if (!self::byteInRange($byte, '1', '9')) {
                    return false;
                }
            } elseif (
                $position >= 6
                || ($ord & 0x80) === 0x80
                || !isset($ntfsShortnamePrefix[$position])
                || !self::asciiBytesEqualIgnoreCase($byte, $ntfsShortnamePrefix[$position])
            ) {
                return false;
            }

            $position++;
        }

        return self::isDoneNtfs(substr($input, $position));
    }

    private static function isDoneNtfs(string $input): bool
    {
        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $byte = $input[$index];
            if ($byte === ':') {
                return true;
            }
            if ($byte !== ' ' && $byte !== '.') {
                return false;
            }
        }

        return true;
    }

    private static function windowsPathComponentError(string $input): ?string
    {
        if (self::isWinDevice($input)) {
            return self::ERROR_WINDOWS_RESERVED_NAME;
        }

        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $byte = $input[$index];
            if (ord($byte) < 0x20 || str_contains(':<>"|?*', $byte)) {
                return self::ERROR_WINDOWS_ILLEGAL_CHARACTER;
            }
        }

        if (str_ends_with($input, '.') || str_ends_with($input, ' ')) {
            return self::ERROR_WINDOWS_ILLEGAL_CHARACTER;
        }

        return null;
    }

    private static function isWinDevice(string $input): bool
    {
        if (strlen($input) < 3) {
            return false;
        }

        $firstThree = substr($input, 0, 3);
        if (self::asciiEqualsIgnoreCase($firstThree, 'AUX') && self::isDoneWindows(substr($input, 3))) {
            return true;
        }
        if (self::asciiEqualsIgnoreCase($firstThree, 'NUL') && self::isDoneWindows(substr($input, 3))) {
            return true;
        }
        if (self::asciiEqualsIgnoreCase($firstThree, 'PRN') && self::isDoneWindows(substr($input, 3))) {
            return true;
        }
        if (
            self::asciiEqualsIgnoreCase($firstThree, 'COM')
            && isset($input[3])
            && self::byteInRange($input[3], '1', '9')
            && self::isDoneWindows(substr($input, 4))
        ) {
            return true;
        }
        if (
            self::asciiEqualsIgnoreCase($firstThree, 'LPT')
            && isset($input[3])
            && self::isAsciiDigit($input[3])
            && self::isDoneWindows(substr($input, 4))
        ) {
            return true;
        }
        if (self::asciiEqualsIgnoreCase($firstThree, 'CON')) {
            return self::isDoneWindows(substr($input, 3))
                || (
                    strlen($input) >= 6
                    && self::asciiEqualsIgnoreCase(substr($input, 3, 3), 'IN$')
                    && self::isDoneWindows(substr($input, 6))
                )
                || (
                    strlen($input) >= 7
                    && self::asciiEqualsIgnoreCase(substr($input, 3, 4), 'OUT$')
                    && self::isDoneWindows(substr($input, 7))
                );
        }

        return false;
    }

    private static function isDoneWindows(string $input): bool
    {
        $length = strlen($input);
        $index = 0;
        while ($index < $length && $input[$index] === ' ') {
            $index++;
        }

        return $index === $length || $input[$index] === '.' || $input[$index] === ':';
    }

    private static function isAllAsciiUppercaseOrUnderscore(string $input): bool
    {
        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $byte = $input[$index];
            if ($byte !== '_' && !self::byteInRange($byte, 'A', 'Z')) {
                return false;
            }
        }

        return true;
    }

    private static function isAsciiDigit(string $byte): bool
    {
        return self::byteInRange($byte, '0', '9');
    }

    private static function byteInRange(string $byte, string $start, string $end): bool
    {
        $ord = ord($byte);

        return $ord >= ord($start) && $ord <= ord($end);
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

    private static function asciiEqualsIgnoreCase(string $left, string $right): bool
    {
        if (strlen($left) !== strlen($right)) {
            return false;
        }

        $length = strlen($left);
        for ($index = 0; $index < $length; $index++) {
            if (!self::asciiBytesEqualIgnoreCase($left[$index], $right[$index])) {
                return false;
            }
        }

        return true;
    }

    private static function asciiBytesEqualIgnoreCase(string $left, string $right): bool
    {
        return self::asciiLowerOrd(ord($left)) === self::asciiLowerOrd(ord($right));
    }

    private static function asciiLowerOrd(int $ord): int
    {
        return $ord >= 0x41 && $ord <= 0x5a ? $ord + 0x20 : $ord;
    }
}
