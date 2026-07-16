<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PlatformMetadataApplier
{
    private ?\Closure $xattrSetter;
    private ?\Closure $xattrLister;
    private ?\Closure $xattrGetter;
    private ?\Closure $xattrRemover;
    private ?\Closure $xattrFilter;

    /**
     * @param null|callable(string, string, string): (bool|null) $xattrSetter
     * @param null|callable(string): list<string> $xattrLister
     * @param null|callable(string, string): (?string) $xattrGetter
     * @param null|callable(string, string): (bool|null) $xattrRemover
     * @param null|callable(string): bool $xattrFilter
     */
    public function __construct(
        private readonly bool $syncOwnership = false,
        private readonly bool $copyOwnershipFromParent = false,
        private readonly bool $syncXattrs = false,
        ?callable $xattrSetter = null,
        ?callable $xattrLister = null,
        ?callable $xattrGetter = null,
        ?callable $xattrRemover = null,
        ?callable $xattrFilter = null,
    ) {
        $this->xattrSetter = $xattrSetter === null ? null : \Closure::fromCallable($xattrSetter);
        $this->xattrLister = $xattrLister === null ? null : \Closure::fromCallable($xattrLister);
        $this->xattrGetter = $xattrGetter === null ? null : \Closure::fromCallable($xattrGetter);
        $this->xattrRemover = $xattrRemover === null ? null : \Closure::fromCallable($xattrRemover);
        $this->xattrFilter = $xattrFilter === null ? null : \Closure::fromCallable($xattrFilter);
    }

    public function apply(FileInfo $file, string $path): ?string
    {
        $xattrError = $this->setXattrs($file, $path);
        if ($xattrError !== null) {
            return $xattrError;
        }

        if ($this->syncOwnership) {
            return $this->applyFileOwnership($file, $path);
        }
        if ($this->copyOwnershipFromParent) {
            return $this->copyOwnershipFromParent($path);
        }

        return null;
    }

    private function setXattrs(FileInfo $file, string $path): ?string
    {
        if (!$this->syncXattrs) {
            return null;
        }

        try {
            $current = $this->currentXattrs($path);
        } catch (XattrsNotSupportedException) {
            return null;
        } catch (\Throwable $throwable) {
            return 'setting xattrs: GetXattr: ' . $throwable->getMessage();
        }

        foreach ($current as $name => $_value) {
            if (array_key_exists($name, $file->xattrs)) {
                continue;
            }

            try {
                if (!$this->removeXattr($path, $name)) {
                    return 'setting xattrs: remove ' . $name . ' failed';
                }
            } catch (XattrsNotSupportedException) {
                return null;
            } catch (\Throwable $throwable) {
                return 'setting xattrs: remove ' . $name . ': ' . $throwable->getMessage();
            }
        }

        foreach ($file->xattrs as $name => $value) {
            if (array_key_exists($name, $current) && hash_equals($current[$name], $value)) {
                continue;
            }

            try {
                if (!$this->setXattr($path, $name, $value)) {
                    return 'setting xattrs: ' . $name . ' failed';
                }
            } catch (XattrsNotSupportedException) {
                return null;
            } catch (\Throwable $throwable) {
                return 'setting xattrs: ' . $name . ': ' . $throwable->getMessage();
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function currentXattrs(string $path): array
    {
        $current = [];
        foreach ($this->listXattrNames($path) as $name) {
            if (!is_string($name) || $name === '' || !$this->xattrPermitted($name)) {
                continue;
            }

            $value = $this->getXattr($path, $name);
            if ($value === null) {
                continue;
            }

            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * @return list<string>
     */
    private function listXattrNames(string $path): array
    {
        if ($this->xattrLister !== null) {
            $names = ($this->xattrLister)($path);
            if (!is_array($names)) {
                throw new \UnexpectedValueException('xattr lister must return an array');
            }

            sort($names, SORT_STRING);
            return array_values($names);
        }

        if (!function_exists('xattr_list')) {
            return [];
        }

        $names = @xattr_list($path);
        if (!is_array($names)) {
            return [];
        }

        sort($names, SORT_STRING);
        return array_values($names);
    }

    private function getXattr(string $path, string $name): ?string
    {
        if ($this->xattrGetter !== null) {
            $value = ($this->xattrGetter)($path, $name);
            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException('xattr getter must return a string or null');
            }

            return $value;
        }

        if (!function_exists('xattr_get')) {
            return null;
        }

        $value = @xattr_get($path, $name);
        return is_string($value) ? $value : null;
    }

    private function setXattr(string $path, string $name, string $value): bool
    {
        if ($this->xattrSetter !== null) {
            $result = ($this->xattrSetter)($path, $name, $value);
            return $result !== false;
        }

        if (!function_exists('xattr_set')) {
            return true;
        }

        return @xattr_set($path, $name, $value) !== false;
    }

    private function removeXattr(string $path, string $name): bool
    {
        if ($this->xattrRemover !== null) {
            $result = ($this->xattrRemover)($path, $name);
            return $result !== false;
        }

        if (!function_exists('xattr_remove')) {
            return true;
        }

        return @xattr_remove($path, $name) !== false;
    }

    private function xattrPermitted(string $name): bool
    {
        return $this->xattrFilter === null || (bool) ($this->xattrFilter)($name);
    }

    private function applyFileOwnership(FileInfo $file, string $path): ?string
    {
        if ($file->unixUid === null || $file->unixGid === null) {
            return null;
        }

        $uid = $file->unixUid;
        if ($file->unixOwnerName !== null && function_exists('posix_getpwnam')) {
            $user = @posix_getpwnam($file->unixOwnerName);
            if (is_array($user) && isset($user['uid'])) {
                $uid = (int) $user['uid'];
            }
        }

        $gid = $file->unixGid;
        if ($file->unixGroupName !== null && function_exists('posix_getgrnam')) {
            $group = @posix_getgrnam($file->unixGroupName);
            if (is_array($group) && isset($group['gid'])) {
                $gid = (int) $group['gid'];
            }
        }

        return $this->applyNumericOwnership($path, $uid, $gid, 'setting ownership');
    }

    private function copyOwnershipFromParent(string $path): ?string
    {
        $parent = dirname($path);
        $stat = @lstat($parent);
        if (!is_array($stat) || !isset($stat['uid'], $stat['gid'])) {
            return 'copy owner from parent: stat failed';
        }

        return $this->applyNumericOwnership($path, (int) $stat['uid'], (int) $stat['gid'], 'copy owner from parent');
    }

    private function applyNumericOwnership(string $path, int $uid, int $gid, string $context): ?string
    {
        $stat = @lstat($path);
        if (is_array($stat) && isset($stat['uid'], $stat['gid']) && (int) $stat['uid'] === $uid && (int) $stat['gid'] === $gid) {
            return null;
        }

        if (!function_exists('lchown') || !@lchown($path, $uid)) {
            return $context . ': chown failed';
        }
        if (!function_exists('lchgrp') || !@lchgrp($path, $gid)) {
            return $context . ': chgrp failed';
        }

        return null;
    }
}
