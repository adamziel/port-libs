<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceName
{
    public const CATEGORY_TAG = 'Tag';
    public const CATEGORY_LOCAL_BRANCH = 'LocalBranch';
    public const CATEGORY_REMOTE_BRANCH = 'RemoteBranch';
    public const CATEGORY_NOTE = 'Note';
    public const CATEGORY_PSEUDO_REF = 'PseudoRef';
    public const CATEGORY_MAIN_PSEUDO_REF = 'MainPseudoRef';
    public const CATEGORY_MAIN_REF = 'MainRef';
    public const CATEGORY_LINKED_PSEUDO_REF = 'LinkedPseudoRef';
    public const CATEGORY_LINKED_REF = 'LinkedRef';
    public const CATEGORY_BISECT = 'Bisect';
    public const CATEGORY_REWRITTEN = 'Rewritten';
    public const CATEGORY_WORKTREE_PRIVATE = 'WorktreePrivate';

    public static function assertValid(string $name): void
    {
        self::assertCommonShape($name);

        if (!str_contains($name, '/') && !self::isPseudoRef($name)) {
            throw new \InvalidArgumentException('Standalone reference names must be all uppercase or underscores');
        }
    }

    public static function isValid(string $name): bool
    {
        try {
            self::assertValid($name);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function isPseudoRef(string $name): bool
    {
        return preg_match('/^[A-Z_]+$/', $name) === 1;
    }

    public static function assertValidPartial(string $name): void
    {
        self::assertCommonShape($name);
    }

    public static function isValidPartial(string $name): bool
    {
        try {
            self::assertValidPartial($name);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function assertValidBranchName(string $name): void
    {
        self::assertValid($name);

        if ($name === 'refs/heads/HEAD') {
            throw new \InvalidArgumentException('Reference branch name refs/heads/HEAD is reserved');
        }
    }

    public static function isValidBranchName(string $name): bool
    {
        try {
            self::assertValidBranchName($name);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function sanitizePartial(string $name): string
    {
        if ($name === '') {
            return '-';
        }

        $out = '';
        $previous = "\0";
        $componentStart = 0;
        $componentEnd = 0;
        $length = strlen($name);
        $last = $length - 1;

        for ($index = 0; $index < $length; $index++) {
            $byte = $name[$index];
            $ord = ord($byte);

            if (
                $byte === '\\'
                || $byte === '^'
                || $byte === ':'
                || $byte === '['
                || $byte === '?'
                || $byte === ' '
                || $byte === '~'
                || $byte === '*'
                || $ord <= 0x1f
                || $ord === 0x7f
            ) {
                $out .= '-';
            } elseif ($byte === '.' && $previous === '.') {
                // Consecutive dots collapse during Gitoxide sanitization.
            } elseif ($byte === '.' && $previous === '/') {
                $out .= '-';
            } elseif ($byte === '{' && $previous === '@') {
                $out .= '-';
            } elseif ($byte === '/' && $previous === '/') {
                // Repeated slashes collapse during Gitoxide sanitization.
            } else {
                if ($byte === '/') {
                    $componentStart = $componentEnd;
                    $componentEnd = $index;
                    $component = substr($name, $componentStart, $componentEnd - $componentStart);
                    if (str_ends_with($component, '.lock')) {
                        $out = self::trimRepeatedSuffix($out, '.lock');
                    }
                }

                $out .= $byte;

                if ($index === $last) {
                    $component = substr($name, $componentEnd + 1);
                    if (str_ends_with($component, '.lock')) {
                        $out = self::trimRepeatedSuffix($out, '.lock');
                    }
                }
            }

            $previous = $byte;
        }

        $out = ltrim(rtrim($out, '/'), '/');
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

    public static function joinPartial(string $name, string $component): string
    {
        self::assertValidPartial($name);

        $joined = $name . '/' . $component;
        self::assertValidPartial($joined);

        return $joined;
    }

    public static function fileName(string $name): string
    {
        self::assertValidPartial($name);

        $slash = strrpos($name, '/');
        return $slash === false ? $name : substr($name, $slash + 1);
    }

    public static function shorten(string $name): string
    {
        $category = self::categoryAndShortName($name);
        return $category['shortName'] ?? $name;
    }

    public static function category(string $name): ?string
    {
        $category = self::categoryAndShortName($name);
        return $category['category'] ?? null;
    }

    /**
     * @return array{category: string, shortName: string, worktreeName?: string}|null
     */
    public static function categoryAndShortName(string $name): ?array
    {
        self::assertValidPartial($name);

        foreach ([
            self::CATEGORY_TAG => 'refs/tags/',
            self::CATEGORY_LOCAL_BRANCH => 'refs/heads/',
            self::CATEGORY_REMOTE_BRANCH => 'refs/remotes/',
        ] as $category => $prefix) {
            if (str_starts_with($name, $prefix)) {
                return ['category' => $category, 'shortName' => substr($name, strlen($prefix))];
            }
        }

        foreach ([
            self::CATEGORY_NOTE => 'refs/notes/',
            self::CATEGORY_BISECT => 'refs/bisect/',
            self::CATEGORY_WORKTREE_PRIVATE => 'refs/worktree/',
            self::CATEGORY_REWRITTEN => 'refs/rewritten/',
        ] as $category => $prefix) {
            if (str_starts_with($name, $prefix)) {
                return ['category' => $category, 'shortName' => substr($name, strlen('refs/'))];
            }
        }

        if (self::isPseudoRef($name)) {
            return ['category' => self::CATEGORY_PSEUDO_REF, 'shortName' => $name];
        }

        if (str_starts_with($name, 'main-worktree/')) {
            $shortened = substr($name, strlen('main-worktree/'));
            if (str_starts_with($shortened, 'refs/')) {
                return ['category' => self::CATEGORY_MAIN_REF, 'shortName' => $shortened];
            }
            if (self::isPseudoRef($shortened)) {
                return ['category' => self::CATEGORY_MAIN_PSEUDO_REF, 'shortName' => $shortened];
            }

            return null;
        }

        if (str_starts_with($name, 'worktrees/')) {
            $shortenedWithWorktree = substr($name, strlen('worktrees/'));
            $slash = strpos($shortenedWithWorktree, '/');
            if ($slash === false) {
                return null;
            }

            $worktreeName = substr($shortenedWithWorktree, 0, $slash);
            $shortened = substr($shortenedWithWorktree, $slash + 1);
            if (str_starts_with($shortened, 'refs/')) {
                return [
                    'category' => self::CATEGORY_LINKED_REF,
                    'shortName' => $shortened,
                    'worktreeName' => $worktreeName,
                ];
            }
            if (self::isPseudoRef($shortened)) {
                return [
                    'category' => self::CATEGORY_LINKED_PSEUDO_REF,
                    'shortName' => $shortened,
                    'worktreeName' => $worktreeName,
                ];
            }
        }

        return null;
    }

    public static function isWorktreePrivate(string $name): bool
    {
        $category = self::category($name);

        return in_array($category, [
            self::CATEGORY_MAIN_PSEUDO_REF,
            self::CATEGORY_PSEUDO_REF,
            self::CATEGORY_LINKED_PSEUDO_REF,
            self::CATEGORY_WORKTREE_PRIVATE,
            self::CATEGORY_REWRITTEN,
            self::CATEGORY_BISECT,
        ], true);
    }

    public static function isRemoteTrackingBranch(string $name): bool
    {
        return self::category($name) === self::CATEGORY_REMOTE_BRANCH;
    }

    public static function toFullName(string $category, string $shortName, ?string $worktreeName = null): string
    {
        self::assertValidPartial($shortName);

        $prefix = self::categoryPrefix($category);
        $partial = match ($category) {
            self::CATEGORY_NOTE => self::stripPrefix($shortName, 'notes/'),
            self::CATEGORY_MAIN_REF => self::stripPrefix($shortName, 'refs/'),
            self::CATEGORY_LINKED_PSEUDO_REF, self::CATEGORY_LINKED_REF => self::linkedPartialName($shortName, $worktreeName),
            self::CATEGORY_BISECT => self::stripPrefix($shortName, 'bisect/'),
            self::CATEGORY_REWRITTEN => self::stripPrefix($shortName, 'rewritten/'),
            self::CATEGORY_WORKTREE_PRIVATE => self::stripPrefix($shortName, 'worktree/'),
            self::CATEGORY_TAG,
            self::CATEGORY_LOCAL_BRANCH,
            self::CATEGORY_REMOTE_BRANCH,
            self::CATEGORY_PSEUDO_REF,
            self::CATEGORY_MAIN_PSEUDO_REF => $shortName,
            default => throw new \InvalidArgumentException('Unknown reference category: ' . $category),
        };

        $out = $prefix;
        if ($category === self::CATEGORY_LINKED_PSEUDO_REF || $category === self::CATEGORY_LINKED_REF) {
            $out .= $worktreeName . '/';
        }

        if ($out === '' || !str_starts_with($partial, $out)) {
            $out .= $partial;
        } else {
            $out = $partial;
        }

        self::assertValidPartial($out);
        return $out;
    }

    public static function expandNamespace(string $namespace): string
    {
        self::assertValidPartial($namespace);

        $out = '';
        foreach (explode('/', $namespace) as $component) {
            $out .= 'refs/namespaces/' . $component . '/';
        }

        return $out;
    }

    public static function prefixNamespace(string $name, string $namespace): string
    {
        self::assertValidPartial($name);
        $expanded = self::expandNamespace($namespace);

        return str_starts_with($name, $expanded) ? $name : $expanded . $name;
    }

    public static function stripNamespace(string $name, string $namespace): string
    {
        self::assertValidPartial($name);
        $expanded = self::expandNamespace($namespace);

        return str_starts_with($name, $expanded) ? substr($name, strlen($expanded)) : $name;
    }

    public static function intoNamespacedPrefix(string $namespace, string $prefix): string
    {
        self::assertValidPartial($namespace);
        self::assertRelativePathPrefix($prefix);

        return self::expandNamespace($namespace) . $prefix;
    }

    private static function assertCommonShape(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Reference name cannot be empty');
        }
        if (preg_match('/[\x00-\x20\x7f~^:?*\[\\\\]/', $name) === 1) {
            throw new \InvalidArgumentException('Reference name contains an invalid byte');
        }
        if ($name[0] === '/' || str_ends_with($name, '/')) {
            throw new \InvalidArgumentException('Reference name cannot start or end with slash');
        }
        if (str_ends_with($name, '.')) {
            throw new \InvalidArgumentException('Reference name cannot end with dot');
        }
        if (str_contains($name, '//')) {
            throw new \InvalidArgumentException('Reference name cannot contain repeated slashes');
        }
        if (str_contains($name, '..')) {
            throw new \InvalidArgumentException('Reference name cannot contain two consecutive dots');
        }
        if (str_contains($name, '@{')) {
            throw new \InvalidArgumentException('Reference name cannot contain @{');
        }

        foreach (explode('/', $name) as $component) {
            if ($component === '') {
                throw new \InvalidArgumentException('Reference name cannot contain empty path components');
            }
            if ($component[0] === '.') {
                throw new \InvalidArgumentException('Reference name component cannot start with dot');
            }
            if (str_ends_with($component, '.lock')) {
                throw new \InvalidArgumentException("Reference name cannot end with '.lock'");
            }
        }
    }

    private static function categoryPrefix(string $category): string
    {
        return match ($category) {
            self::CATEGORY_TAG => 'refs/tags/',
            self::CATEGORY_LOCAL_BRANCH => 'refs/heads/',
            self::CATEGORY_REMOTE_BRANCH => 'refs/remotes/',
            self::CATEGORY_NOTE => 'refs/notes/',
            self::CATEGORY_MAIN_PSEUDO_REF => 'main-worktree/',
            self::CATEGORY_MAIN_REF => 'main-worktree/refs/',
            self::CATEGORY_PSEUDO_REF => '',
            self::CATEGORY_LINKED_PSEUDO_REF, self::CATEGORY_LINKED_REF => 'worktrees/',
            self::CATEGORY_BISECT => 'refs/bisect/',
            self::CATEGORY_REWRITTEN => 'refs/rewritten/',
            self::CATEGORY_WORKTREE_PRIVATE => 'refs/worktree/',
            default => throw new \InvalidArgumentException('Unknown reference category: ' . $category),
        };
    }

    private static function stripPrefix(string $value, string $prefix): string
    {
        return str_starts_with($value, $prefix) ? substr($value, strlen($prefix)) : $value;
    }

    private static function linkedPartialName(string $shortName, ?string $worktreeName): string
    {
        if ($worktreeName === null || $worktreeName === '') {
            throw new \InvalidArgumentException('Linked reference categories require a worktree name');
        }
        self::assertValidPartial($worktreeName);

        return $shortName;
    }

    private static function assertRelativePathPrefix(string $prefix): void
    {
        if ($prefix === '') {
            return;
        }
        if ($prefix[0] === '/') {
            throw new \InvalidArgumentException('Namespaced prefix must be relative');
        }
        if (str_contains($prefix, '\\')) {
            throw new \InvalidArgumentException('Namespaced prefix must use slash separators');
        }

        $parts = explode('/', $prefix);
        foreach ($parts as $index => $component) {
            if ($component === '' && $index === count($parts) - 1) {
                continue;
            }
            if ($component === '' || $component === '.' || $component === '..') {
                throw new \InvalidArgumentException('Namespaced prefix contains an invalid path component');
            }
            if (preg_match('/[\x00-\x1f:<>\"|?*]/', $component) === 1) {
                throw new \InvalidArgumentException('Namespaced prefix contains an invalid byte');
            }
            if (str_ends_with($component, '.') || str_ends_with($component, ' ')) {
                throw new \InvalidArgumentException('Namespaced prefix contains a Windows-incompatible component');
            }
            if (strcasecmp($component, '.git') === 0) {
                throw new \InvalidArgumentException('Namespaced prefix must not contain .git components');
            }
        }
    }

    private static function trimRepeatedSuffix(string $input, string $suffix): string
    {
        while (str_ends_with($input, $suffix)) {
            $input = substr($input, 0, -strlen($suffix));
        }

        return $input;
    }
}
