<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitGlob
{
    public const NO_SUB_DIR = 1 << 0;
    public const ENDS_WITH = 1 << 1;
    public const MUST_BE_DIR = 1 << 2;
    public const NEGATIVE = 1 << 3;
    public const ABSOLUTE = 1 << 4;

    public const CASE_SENSITIVE = 'sensitive';
    public const CASE_FOLD = 'fold';

    public const WILDMATCH_NO_MATCH_SLASH_LITERAL = 1 << 0;
    public const WILDMATCH_IGNORE_CASE = 1 << 1;

    private const GLOB_CHARACTERS = '*?[\\';

    public function __construct(
        public readonly string $text,
        public readonly int $mode,
        public readonly ?int $firstWildcardPosition,
    ) {
    }

    public static function parse(string $pattern): ?self
    {
        return self::parsePattern($pattern, true);
    }

    public static function fromBytesWithoutNegation(string $pattern): ?self
    {
        return self::parsePattern($pattern, false);
    }

    public function __toString(): string
    {
        $display = '';
        if ($this->hasMode(self::NEGATIVE)) {
            $display .= '!';
        }
        if ($this->hasMode(self::ABSOLUTE)) {
            $display .= '/';
        }

        $display .= $this->text;

        if ($this->hasMode(self::MUST_BE_DIR)) {
            $display .= '/';
        }

        return $display;
    }

    public function hasMode(int $mode): bool
    {
        return ($this->mode & $mode) === $mode;
    }

    public function isNegative(): bool
    {
        return $this->hasMode(self::NEGATIVE);
    }

    public function matchesRepoRelativePath(
        string $path,
        ?int $basenameStartPosition = null,
        ?bool $isDirectory = null,
        string $case = self::CASE_SENSITIVE,
        int $wildmatchMode = self::WILDMATCH_NO_MATCH_SLASH_LITERAL,
    ): bool {
        if ($isDirectory !== true && $this->hasMode(self::MUST_BE_DIR)) {
            return false;
        }

        $wildmatchMode = self::modeWithCase($wildmatchMode, $case);
        if ($this->hasMode(self::NO_SUB_DIR) && !$this->hasMode(self::ABSOLUTE)) {
            return $this->matches(substr($path, $basenameStartPosition ?? 0), $wildmatchMode);
        }

        return $this->matches($path, $wildmatchMode);
    }

    public function matches(string $value, int $wildmatchMode = 0): bool
    {
        $ignoreCase = ($wildmatchMode & self::WILDMATCH_IGNORE_CASE) !== 0;
        $noMatchSlash = ($wildmatchMode & self::WILDMATCH_NO_MATCH_SLASH_LITERAL) !== 0;

        if ($this->firstWildcardPosition === null) {
            return self::asciiEquals($this->text, $value, $ignoreCase);
        }

        if (
            $this->hasMode(self::ENDS_WITH)
            && (!$noMatchSlash || !str_contains($value, '/'))
        ) {
            $suffix = substr($this->text, $this->firstWildcardPosition + 1);

            return self::asciiEndsWith($value, $suffix, $ignoreCase);
        }

        $prefix = substr($this->text, 0, $this->firstWildcardPosition);
        if (!self::asciiEquals($prefix, substr($value, 0, strlen($prefix)), $ignoreCase)) {
            return false;
        }

        return self::wildmatch($this->text, $value, $wildmatchMode);
    }

    public static function wildmatch(string $pattern, string $value, int $mode = 0): bool
    {
        return GitAttributes::globMatches(
            $pattern,
            $value,
            ($mode & self::WILDMATCH_NO_MATCH_SLASH_LITERAL) !== 0,
            ($mode & self::WILDMATCH_IGNORE_CASE) !== 0,
        );
    }

    public static function basenameStartPosition(string $value): ?int
    {
        $pos = strrpos($value, '/');

        return $pos === false ? null : $pos + 1;
    }

    /**
     * @return array{patterns:list<array>,source:string,base:?string}
     */
    public static function searchListFromBytes(string $bytes, string $sourceFile, ?string $root): array
    {
        return [
            'patterns' => [],
            'source' => $sourceFile,
            'base' => self::searchListBase($sourceFile, $root),
        ];
    }

    /**
     * @return array{patterns:list<array>,source:string,base:?string}|null
     */
    public static function searchListFromFile(string $sourceFile, ?string $root = null): ?array
    {
        if (!is_file($sourceFile) || !is_readable($sourceFile)) {
            return null;
        }

        $bytes = file_get_contents($sourceFile);
        if ($bytes === false) {
            return null;
        }

        return self::searchListFromBytes($bytes, $sourceFile, $root);
    }

    public static function searchListBase(string $sourceFile, ?string $root): ?string
    {
        if ($root === null) {
            return null;
        }

        $parent = self::parentPath(self::normalizeSearchPath($sourceFile));
        if ($parent === '') {
            return null;
        }

        $root = self::normalizeSearchPath($root);
        if ($root === '') {
            $base = $parent;
        } elseif ($parent === $root) {
            $base = '';
        } elseif (str_starts_with($parent, $root . '/')) {
            $base = substr($parent, strlen($root) + 1);
        } else {
            return null;
        }

        return $base === '' ? null : $base . '/';
    }

    /**
     * @return array{string, ?int}|null
     */
    public static function stripBaseHandleRecomputeBasenamePosition(
        string $base,
        string $relativePath,
        ?int $basenameStartPosition,
        string $case = self::CASE_SENSITIVE,
    ): ?array {
        $baseLength = strlen($base);
        $pathPrefix = substr($relativePath, 0, $baseLength);
        $matches = match ($case) {
            self::CASE_SENSITIVE => $pathPrefix === $base,
            self::CASE_FOLD => self::asciiEquals($pathPrefix, $base, true),
            default => throw new \InvalidArgumentException("Unsupported glob case mode: {$case}"),
        };
        if (!$matches) {
            return null;
        }

        $basenameStartPosition = $basenameStartPosition === null
            ? null
            : $basenameStartPosition - $baseLength;

        return [
            substr($relativePath, $baseLength),
            $basenameStartPosition === null || $basenameStartPosition <= 0 ? null : $basenameStartPosition,
        ];
    }

    private static function parsePattern(string $pattern, bool $mayAlter): ?self
    {
        if ($pattern === '') {
            return null;
        }

        $mode = 0;
        if ($mayAlter) {
            if ($pattern[0] === '!') {
                $mode |= self::NEGATIVE;
                $pattern = substr($pattern, 1);
            } elseif ($pattern[0] === '\\') {
                $second = $pattern[1] ?? null;
                if ($second === '!' || $second === '#') {
                    $pattern = substr($pattern, 1);
                }
            }
        }

        if (self::isAsciiWhitespaceOnly($pattern)) {
            return null;
        }

        if (($pattern[0] ?? null) === '/') {
            $mode |= self::ABSOLUTE;
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '/')) {
            $mode |= self::MUST_BE_DIR;
            $pattern = substr($pattern, 0, -1);
        }

        if (!str_contains($pattern, '/')) {
            $mode |= self::NO_SUB_DIR;
        }

        if (($pattern[0] ?? null) === '*' && self::firstWildcardPosition(substr($pattern, 1)) === null) {
            $mode |= self::ENDS_WITH;
        }

        return new self($pattern, $mode, self::firstWildcardPosition($pattern));
    }

    private static function firstWildcardPosition(string $pattern): ?int
    {
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            if (str_contains(self::GLOB_CHARACTERS, $pattern[$i])) {
                return $i;
            }
        }

        return null;
    }

    private static function isAsciiWhitespaceOnly(string $pattern): bool
    {
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($pattern[$i]);
            if ($byte !== 32 && ($byte < 9 || $byte > 13)) {
                return false;
            }
        }

        return true;
    }

    private static function modeWithCase(int $mode, string $case): int
    {
        return match ($case) {
            self::CASE_SENSITIVE => $mode,
            self::CASE_FOLD => $mode | self::WILDMATCH_IGNORE_CASE,
            default => throw new \InvalidArgumentException("Unsupported glob case mode: {$case}"),
        };
    }

    private static function asciiEquals(string $left, string $right, bool $ignoreCase): bool
    {
        $length = strlen($left);
        if ($length !== strlen($right)) {
            return false;
        }
        if (!$ignoreCase) {
            return $left === $right;
        }

        for ($i = 0; $i < $length; $i++) {
            if (self::asciiFoldByte($left[$i]) !== self::asciiFoldByte($right[$i])) {
                return false;
            }
        }

        return true;
    }

    private static function asciiEndsWith(string $value, string $suffix, bool $ignoreCase): bool
    {
        $suffixLength = strlen($suffix);
        if ($suffixLength === 0) {
            return true;
        }
        if ($suffixLength > strlen($value)) {
            return false;
        }

        return self::asciiEquals(substr($value, -$suffixLength), $suffix, $ignoreCase);
    }

    private static function asciiFoldByte(string $byte): string
    {
        $ord = ord($byte);
        if ($ord >= 65 && $ord <= 90) {
            return chr($ord + 32);
        }

        return $byte;
    }

    private static function normalizeSearchPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        if ($path === '.') {
            return '';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    private static function parentPath(string $path): string
    {
        $pos = strrpos($path, '/');
        if ($pos === false) {
            return '';
        }
        if ($pos === 0) {
            return '/';
        }

        return substr($path, 0, $pos);
    }
}
