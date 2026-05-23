<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfoScanner
{
    private string $rootPath;
    private ?\Closure $xattrFilter;
    private ?\Closure $xattrLister;
    private ?\Closure $xattrGetter;
    private BlockList $blockList;

    /**
     * @param null|callable(string): bool $xattrFilter
     * @param null|callable(string): list<string> $xattrLister
     * @param null|callable(string, string): (?string) $xattrGetter
     */
    public function __construct(
        string $rootPath,
        private readonly bool $scanOwnership = false,
        private readonly bool $scanXattrs = false,
        ?callable $xattrFilter = null,
        private readonly int $maxSingleXattrSize = 0,
        private readonly int $maxTotalXattrSize = 0,
        ?callable $xattrLister = null,
        ?callable $xattrGetter = null,
        ?BlockList $blockList = null,
    ) {
        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('FileInfo scanner root path must be an existing directory');
        }
        if ($this->maxSingleXattrSize < 0 || $this->maxTotalXattrSize < 0) {
            throw new \InvalidArgumentException('Xattr size limits must not be negative');
        }

        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $this->xattrFilter = $xattrFilter === null ? null : \Closure::fromCallable($xattrFilter);
        $this->xattrLister = $xattrLister === null ? null : \Closure::fromCallable($xattrLister);
        $this->xattrGetter = $xattrGetter === null ? null : \Closure::fromCallable($xattrGetter);
        $this->blockList = $blockList ?? new BlockList();
    }

    public function scan(string $name, bool $hashBlocks = false, ?int $blockSize = null): FileInfo
    {
        ProtocolValidation::checkFilename($name);

        $path = $this->absolutePath($name);
        $stat = @lstat($path);
        if (!is_array($stat)) {
            throw new \RuntimeException('stat failed for ' . $name);
        }

        $platform = $this->platformData($name, $path, $stat);

        if (is_link($path)) {
            $target = readlink($path);
            if (!is_string($target)) {
                throw new \RuntimeException('readlink failed for ' . $name);
            }

            return new FileInfo(
                name: $name,
                type: FileInfo::TYPE_SYMLINK,
                noPermissions: true,
                symlinkTarget: $target,
                unixOwnerName: $platform['unixOwnerName'],
                unixGroupName: $platform['unixGroupName'],
                unixUid: $platform['unixUid'],
                unixGid: $platform['unixGid'],
                xattrs: $platform['xattrs'],
            );
        }

        $permissions = (int) $stat['mode'] & 0777;
        $modifiedS = (int) $stat['mtime'];

        if (is_dir($path)) {
            return new FileInfo(
                name: $name,
                modifiedS: $modifiedS,
                type: FileInfo::TYPE_DIRECTORY,
                permissions: $permissions,
                unixOwnerName: $platform['unixOwnerName'],
                unixGroupName: $platform['unixGroupName'],
                unixUid: $platform['unixUid'],
                unixGid: $platform['unixGid'],
                xattrs: $platform['xattrs'],
            );
        }

        if (!is_file($path)) {
            throw new \UnexpectedValueException('unsupported filesystem entry type for ' . $name);
        }

        $size = (int) $stat['size'];
        $blocks = [];
        $blocksHash = '';
        $rawBlockSize = $blockSize ?? BlockList::blockSizeForFileSize($size);
        if ($hashBlocks) {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException('read failed for ' . $name);
            }
            $blocks = $this->blockList->fromBytes($bytes, $rawBlockSize);
            $blocksHash = $this->blockList->hashBlocks($blocks);
        }

        return new FileInfo(
            name: $name,
            modifiedS: $modifiedS,
            size: $size,
            blocksHash: $blocksHash,
            type: FileInfo::TYPE_FILE,
            permissions: $permissions,
            rawBlockSize: $rawBlockSize,
            blocks: $blocks,
            unixOwnerName: $platform['unixOwnerName'],
            unixGroupName: $platform['unixGroupName'],
            unixUid: $platform['unixUid'],
            unixGid: $platform['unixGid'],
            xattrs: $platform['xattrs'],
        );
    }

    /**
     * @param array<string, mixed> $stat
     * @return array{unixOwnerName:?string, unixGroupName:?string, unixUid:?int, unixGid:?int, xattrs:array<string, string>}
     */
    private function platformData(string $name, string $path, array $stat): array
    {
        $owner = [
            'unixOwnerName' => null,
            'unixGroupName' => null,
            'unixUid' => null,
            'unixGid' => null,
        ];
        if ($this->scanOwnership) {
            $uid = (int) ($stat['uid'] ?? 0);
            $gid = (int) ($stat['gid'] ?? 0);
            $owner = [
                'unixOwnerName' => $this->ownerName($uid),
                'unixGroupName' => $this->groupName($gid),
                'unixUid' => $uid,
                'unixGid' => $gid,
            ];
        }

        return $owner + [
            'xattrs' => $this->scanXattrs ? $this->xattrs($name, $path) : [],
        ];
    }

    private function ownerName(int $uid): ?string
    {
        if (function_exists('posix_getpwuid')) {
            $user = @posix_getpwuid($uid);
            if (is_array($user) && isset($user['name'])) {
                return (string) $user['name'];
            }
        }

        return $uid === 0 ? 'root' : null;
    }

    private function groupName(int $gid): ?string
    {
        if (function_exists('posix_getgrgid')) {
            $group = @posix_getgrgid($gid);
            if (is_array($group) && isset($group['name'])) {
                return (string) $group['name'];
            }
        }

        return $gid === 0 ? 'root' : null;
    }

    /**
     * @return array<string, string>
     */
    private function xattrs(string $name, string $path): array
    {
        try {
            $names = $this->listXattrs($path);
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('reading platform data: get xattr ' . $name . ': ' . $throwable->getMessage(), 0, $throwable);
        }

        $xattrs = [];
        $totalSize = 0;
        foreach ($names as $xattrName) {
            if (!is_string($xattrName) || $xattrName === '' || !$this->xattrPermitted($xattrName)) {
                continue;
            }

            try {
                $value = $this->getXattr($path, $xattrName);
            } catch (\Throwable $throwable) {
                throw new \RuntimeException('reading platform data: get xattr ' . $name . ': ' . $throwable->getMessage(), 0, $throwable);
            }
            if ($value === null) {
                continue;
            }

            $entrySize = strlen($xattrName) + strlen($value);
            if ($this->maxSingleXattrSize > 0 && $entrySize > $this->maxSingleXattrSize) {
                continue;
            }

            $totalSize += $entrySize;
            if ($this->maxTotalXattrSize > 0 && $totalSize > $this->maxTotalXattrSize) {
                continue;
            }

            $xattrs[$xattrName] = $value;
        }

        ksort($xattrs);
        return $xattrs;
    }

    /**
     * @return list<string>
     */
    private function listXattrs(string $path): array
    {
        if ($this->xattrLister !== null) {
            $names = ($this->xattrLister)($path);
            if (!is_array($names)) {
                throw new \UnexpectedValueException('xattr lister must return an array');
            }

            return array_values($names);
        }

        if (!function_exists('xattr_list')) {
            return [];
        }

        $names = @xattr_list($path);
        return is_array($names) ? array_values($names) : [];
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

    private function xattrPermitted(string $name): bool
    {
        return $this->xattrFilter === null || (bool) ($this->xattrFilter)($name);
    }

    private function absolutePath(string $name): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    }
}
