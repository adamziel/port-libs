<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceName
{
    public static function assertValid(string $name): void
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
}
