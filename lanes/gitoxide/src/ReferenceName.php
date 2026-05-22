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

        if (
            !str_starts_with($name, 'refs/')
            && !str_starts_with($name, 'worktrees/')
            && !str_starts_with($name, 'main-worktree/')
            && !self::isPseudoRef($name)
        ) {
            throw new \InvalidArgumentException('Reference name must be a full ref name or pseudo ref');
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

    private static function assertCommonShape(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Reference name cannot be empty');
        }
        if ($name === '@') {
            throw new \InvalidArgumentException('Reference name cannot be @');
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
}
