<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullItemUpdater
{
    private const ERR_INCOMPATIBLE_SYMLINK = 'incompatible symlink entry; rescan with newer Syncthing on source';
    private const ERR_MODIFIED = 'checking existing item: file modified but not rescanned; will try again later';
    private const ERR_DIR_HAS_TO_BE_SCANNED = 'directory has been deleted on a remote device but contains changed files, scheduling scan';
    private const ERR_DIR_HAS_IGNORED = 'directory has been deleted on a remote device but contains ignored files (see ignore documentation for (?d) prefix)';
    private const ERR_DIR_NOT_EMPTY = 'directory has been deleted on a remote device but is not empty; the contents are probably ignored on that remote device, but not locally';
    private const ERR_UNEXPECTED_DIR_ON_FILE_DELETE = 'encountered directory when trying to remove file/symlink';
    private const DELETE_DB_ONLY = "\0db-only";
    private const ALL_LOCAL_FLAGS = FileInfo::FLAG_LOCAL_UNSUPPORTED
        | FileInfo::FLAG_LOCAL_IGNORED
        | FileInfo::FLAG_LOCAL_MUST_RESCAN
        | FileInfo::FLAG_LOCAL_RECEIVE_ONLY
        | FileInfo::FLAG_LOCAL_GLOBAL
        | FileInfo::FLAG_LOCAL_NEEDED
        | FileInfo::FLAG_LOCAL_REMOTE_INVALID;

    private string $rootPath;

    /**
     * @var list<array{folder:string, item:string, type:string, action:string}>
     */
    private array $itemStartedEvents = [];

    /**
     * @var list<array{folder:string, item:string, error:?string, type:string, action:string}>
     */
    private array $itemFinishedEvents = [];

    /**
     * @var list<array{path:string, error:string}>
     */
    private array $pullErrors = [];

    /**
     * @var list<array{file:FileInfo, type:string}>
     */
    private array $dbUpdates = [];

    /**
     * @var list<string>
     */
    private array $scanNames = [];

    public function __construct(
        string $rootPath,
        private readonly string $folderId = '',
        private readonly bool $ignorePerms = false,
        private readonly int $maxConflicts = -1,
        private readonly ?int $conflictTimestamp = null,
        private readonly ?IgnoreMatcher $ignoreMatcher = null,
        private readonly bool $receiveOnlyFolder = false,
    ) {
        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('Pull item root path must be an existing directory');
        }
        if ($this->maxConflicts < -1) {
            throw new \InvalidArgumentException('Max conflicts must be -1 or greater');
        }

        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);
    }

    public function handleDirectory(FileInfo $file, ?FileInfo $current = null): void
    {
        if (!$file->isDirectory() || $file->isDeleted() || $file->isInvalid()) {
            throw new \InvalidArgumentException('Directory updates require a valid directory FileInfo');
        }

        $this->withLifecycle($file, 'dir', 'update', function () use ($file, $current): ?string {
            $path = $this->absolutePath($file->name);
            $exists = $this->pathExists($path);

            if ($exists && !$this->isDirectoryPath($path)) {
                if (!$this->existingPathMatchesCurrentFile($path, $file->name, $current)) {
                    return $this->fail($file->name, self::ERR_MODIFIED);
                }

                $error = $this->replaceExistingNonDirectory($path, $file, $current);
                if ($error !== null) {
                    return $this->fail($file->name, $error);
                }
                $exists = false;
            }

            if (!$exists) {
                $error = $this->ensureParentDirectory($file->name);
                if ($error !== null) {
                    return $this->fail($file->name, $error);
                }

                if (!mkdir($path, $this->directoryMode($file))) {
                    return $this->fail($file->name, 'creating directory failed');
                }
            }

            if (!$this->ignorePerms && !$file->noPermissions) {
                @chmod($path, ($file->permissions & 0777) | (fileperms($path) & 07000));
            }

            $this->scheduleDbUpdate($file, PullDbUpdater::DB_UPDATE_HANDLE_DIR);

            return null;
        });
    }

    public function handleSymlink(FileInfo $file, ?FileInfo $current = null): void
    {
        if (!$file->isSymlink() || $file->isDeleted() || $file->isInvalid()) {
            throw new \InvalidArgumentException('Symlink updates require a valid symlink FileInfo');
        }

        $this->withLifecycle($file, 'symlink', 'update', function () use ($file, $current): ?string {
            if ($file->symlinkTarget === '') {
                $this->newPullError($file->name, self::ERR_INCOMPATIBLE_SYMLINK);
                return null;
            }

            $path = $this->absolutePath($file->name);
            if ($this->pathExists($path)) {
                if (!$this->existingPathMatchesCurrentFile($path, $file->name, $current)) {
                    return $this->fail($file->name, self::ERR_MODIFIED);
                }

                $error = $this->replaceExistingForSymlink($path, $file, $current);
                if ($error !== null) {
                    return $this->fail($file->name, $error);
                }
            }

            $error = $this->ensureParentDirectory($file->name);
            if ($error !== null) {
                return $this->fail($file->name, $error);
            }

            if (!symlink($file->symlinkTarget, $path)) {
                return $this->fail($file->name, 'symlink create failed');
            }

            $this->scheduleDbUpdate($file, PullDbUpdater::DB_UPDATE_HANDLE_SYMLINK);

            return null;
        });
    }

    public function deleteFile(FileInfo $file, ?FileInfo $current = null): void
    {
        if ($file->isDirectory() || !$file->isDeleted() || $file->isInvalid()) {
            throw new \InvalidArgumentException('File deletion requires a deleted non-directory FileInfo');
        }

        $this->withLifecycle($file, 'file', 'delete', function () use ($file, $current): ?string {
            $check = $this->checkToBeDeleted($file, $current);
            if ($check === self::DELETE_DB_ONLY) {
                $this->scheduleDbUpdate($file, PullDbUpdater::DB_UPDATE_DELETE_FILE);
                return null;
            }
            if ($check !== null) {
                return $this->fail($file->name, 'delete file: ' . $check);
            }
            if ($current !== null && $current->isDirectory()) {
                return $this->fail($file->name, 'delete file: ' . self::ERR_UNEXPECTED_DIR_ON_FILE_DELETE);
            }

            $path = $this->absolutePath($file->name);
            $error = null;
            if ($current !== null && !$current->isSymlink() && $file->inConflictWith($current)) {
                $error = $this->moveForConflict($path, $file);
            } elseif (is_link($path) || is_file($path)) {
                $error = unlink($path) ? null : 'removing deleted file failed';
            }

            if ($error !== null) {
                return $this->fail($file->name, 'delete file: ' . $error);
            }

            $this->scheduleDbUpdate($file, PullDbUpdater::DB_UPDATE_DELETE_FILE);

            return null;
        });
    }

    /**
     * @param list<FileInfo> $knownDirectoryChildren
     */
    public function deleteDirectory(FileInfo $file, ?FileInfo $current = null, array $knownDirectoryChildren = []): void
    {
        if (!$file->isDirectory() || !$file->isDeleted() || $file->isInvalid()) {
            throw new \InvalidArgumentException('Directory deletion requires a deleted directory FileInfo');
        }
        foreach ($knownDirectoryChildren as $child) {
            if (!$child instanceof FileInfo) {
                throw new \InvalidArgumentException('Known directory children must be FileInfo instances');
            }
        }

        $this->withLifecycle($file, 'dir', 'delete', function () use ($file, $current, $knownDirectoryChildren): ?string {
            $check = $this->checkToBeDeleted($file, $current);
            if ($check === self::DELETE_DB_ONLY) {
                $this->scheduleDbUpdate($file, PullDbUpdater::DB_UPDATE_DELETE_DIR);
                return null;
            }
            if ($check !== null) {
                return $this->fail($file->name, 'delete dir: ' . $check);
            }

            $error = $this->deleteDirectoryOnDisk($file->name, $knownDirectoryChildren);
            if ($error !== null) {
                return $this->fail($file->name, 'delete dir: ' . $error);
            }

            $this->scheduleDbUpdate($file, PullDbUpdater::DB_UPDATE_DELETE_DIR);

            return null;
        });
    }

    /**
     * @return list<array{folder:string, item:string, type:string, action:string}>
     */
    public function itemStartedEvents(): array
    {
        return $this->itemStartedEvents;
    }

    /**
     * @return list<array{folder:string, item:string, error:?string, type:string, action:string}>
     */
    public function itemFinishedEvents(): array
    {
        return $this->itemFinishedEvents;
    }

    /**
     * @return list<array{path:string, error:string}>
     */
    public function pullErrors(): array
    {
        return $this->pullErrors;
    }

    /**
     * @return list<array{file:FileInfo, type:string}>
     */
    public function dbUpdates(): array
    {
        return $this->dbUpdates;
    }

    /**
     * @return list<string>
     */
    public function scanNames(): array
    {
        return $this->scanNames;
    }

    /**
     * @param callable(): ?string $work
     */
    private function withLifecycle(FileInfo $file, string $type, string $action, callable $work): void
    {
        $this->itemStartedEvents[] = [
            'folder' => $this->folderId,
            'item' => $file->name,
            'type' => $type,
            'action' => $action,
        ];

        $error = null;
        try {
            $error = $work();
        } catch (\Throwable $throwable) {
            $error = $throwable->getMessage();
            $this->newPullError($file->name, $error);
        }

        $this->itemFinishedEvents[] = [
            'folder' => $this->folderId,
            'item' => $file->name,
            'error' => $error,
            'type' => $type,
            'action' => $action,
        ];
    }

    private function checkToBeDeleted(FileInfo $file, ?FileInfo $current): ?string
    {
        if ($this->parentTraversesSymlink($file->name)) {
            return self::DELETE_DB_ONLY;
        }

        $path = $this->absolutePath($file->name);
        if (!$this->pathExists($path)) {
            if ($this->caseConflictName($file->name) !== null) {
                return self::DELETE_DB_ONLY;
            }
            if ($current !== null && !$current->isDeleted() && !$current->isUnsupported()) {
                $this->addScanName($file->name);
                return self::ERR_MODIFIED;
            }

            return self::DELETE_DB_ONLY;
        }

        if ($current === null || $current->isDeleted()) {
            $this->addScanName($file->name);
            return self::ERR_MODIFIED;
        }
        if (!$this->diskEntryMatchesKnownFileInfo($path, $file->name, $current)) {
            $this->addScanName($file->name);
            return self::ERR_MODIFIED;
        }

        return null;
    }

    /**
     * @param list<FileInfo> $knownDirectoryChildren
     */
    private function deleteDirectoryOnDisk(string $name, array $knownDirectoryChildren): ?string
    {
        $path = $this->absolutePath($name);
        if (!$this->isDirectoryPath($path)) {
            return $this->pathExists($path) ? 'directory delete target is not a directory' : null;
        }

        $knownChildren = $this->knownDirectoryChildrenByName($name, $knownDirectoryChildren);
        $dirsToDelete = [];
        $hasIgnored = false;
        $hasKnown = false;
        $hasToBeScanned = false;
        $hasReceiveOnlyChanged = false;
        $deleteError = null;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            $entryPath = $entry->getPathname();
            $entryName = $this->relativeNameFromPath($entryPath);
            $match = $this->ignoreMatcher?->match($entryName);

            if (($match !== null && $match->isDeletable()) || RequestServer::isTemporaryName($entryName)) {
                if ($entry->isDir() && !$entry->isLink()) {
                    $dirsToDelete[] = $entryPath;
                    continue;
                }
                if (!@unlink($entryPath) && $deleteError === null) {
                    $deleteError = 'removing deleted directory child failed';
                }
                continue;
            }

            if ($match !== null && $match->isIgnored()) {
                $hasIgnored = true;
                continue;
            }

            $known = $knownChildren[$entryName] ?? null;
            if ($known === null || $known->isDeleted()) {
                $this->addScanName($entryName);
                $hasToBeScanned = true;
                continue;
            }

            if ($this->receiveOnlyFolder && $known->isReceiveOnlyChanged()) {
                $hasReceiveOnlyChanged = true;
                continue;
            }

            if (!$this->diskEntryMatchesKnownFileInfo($entryPath, $entryName, $known)) {
                $this->addScanName($entryName);
                $hasToBeScanned = true;
                continue;
            }

            $hasKnown = true;
        }

        usort($dirsToDelete, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($dirsToDelete as $dir) {
            if (!@rmdir($dir) && $deleteError === null) {
                $deleteError = 'removing deleted directory child failed';
            }
        }

        if ($hasToBeScanned) {
            return self::ERR_DIR_HAS_TO_BE_SCANNED;
        }
        if ($hasIgnored) {
            return self::ERR_DIR_HAS_IGNORED;
        }
        if ($hasReceiveOnlyChanged) {
            $this->addScanName($name);
            return $this->removeDirectoryTree($path);
        }
        if ($hasKnown) {
            return self::ERR_DIR_NOT_EMPTY;
        }
        if ($deleteError !== null) {
            return $deleteError;
        }

        return rmdir($path) ? null : 'removing deleted directory failed';
    }

    private function removeDirectoryTree(string $path): ?string
    {
        if (!$this->isDirectoryPath($path)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                if (!rmdir($entry->getPathname())) {
                    return 'removing deleted directory child failed';
                }
                continue;
            }

            if (!unlink($entry->getPathname())) {
                return 'removing deleted directory child failed';
            }
        }

        return rmdir($path) ? null : 'removing deleted directory failed';
    }

    /**
     * @param list<FileInfo> $knownDirectoryChildren
     *
     * @return array<string, FileInfo>
     */
    private function knownDirectoryChildrenByName(string $directoryName, array $knownDirectoryChildren): array
    {
        $prefix = rtrim($directoryName, '/') . '/';
        $children = [];
        foreach ($knownDirectoryChildren as $child) {
            if ($child->name !== $directoryName && str_starts_with($child->name, $prefix)) {
                $children[$child->name] = $child;
            }
        }

        return $children;
    }

    private function diskEntryMatchesKnownFileInfo(string $path, string $name, FileInfo $known): bool
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            $target = readlink($path);
            return $known->isSymlink() && is_string($target) && $known->symlinkTarget === $target;
        }

        if ($this->isDirectoryPath($path)) {
            return $known->isDirectory();
        }

        if (!is_file($path) || $known->type !== FileInfo::TYPE_FILE) {
            return false;
        }

        $size = filesize($path);
        $mtime = filemtime($path);
        $mode = fileperms($path);
        if (!is_int($size) || !is_int($mtime) || !is_int($mode)) {
            return false;
        }

        $disk = new FileInfo(
            name: $name,
            modifiedS: $mtime,
            version: $known->version,
            size: $size,
            type: FileInfo::TYPE_FILE,
            permissions: $mode & 0777,
            noPermissions: $known->noPermissions,
            rawBlockSize: $known->rawBlockSize,
        );

        return $known->isEquivalent($disk, new FileInfoComparison(
            ignorePerms: $this->ignorePerms || $known->noPermissions,
            ignoreBlocks: true,
            ignoreFlags: self::ALL_LOCAL_FLAGS,
            ignoreOwnership: true,
        ));
    }

    private function replaceExistingNonDirectory(string $path, FileInfo $file, ?FileInfo $current): ?string
    {
        if ($current !== null && !$current->isSymlink() && $file->inConflictWith($current)) {
            return $this->moveForConflict($path, $file);
        }

        return $this->deleteItemOnDisk($path);
    }

    private function replaceExistingForSymlink(string $path, FileInfo $file, ?FileInfo $current): ?string
    {
        if ($current !== null && !$current->isDirectory() && !$current->isSymlink() && $file->inConflictWith($current)) {
            return $this->moveForConflict($path, $file);
        }

        return $this->deleteItemOnDisk($path);
    }

    private function existingPathMatchesCurrentFile(string $path, string $name, ?FileInfo $current): bool
    {
        if ($current === null || $current->isDeleted()) {
            $this->addScanName($name);
            return false;
        }

        clearstatcache(true, $path);
        if (is_link($path)) {
            $target = readlink($path);
            return $current->isSymlink() && is_string($target) && $target === $current->symlinkTarget;
        }

        if ($this->isDirectoryPath($path)) {
            return $current->isDirectory();
        }

        if (!is_file($path) || $current->type !== FileInfo::TYPE_FILE) {
            return false;
        }

        $size = filesize($path);
        $mtime = filemtime($path);
        if (!is_int($size) || !is_int($mtime) || $size !== $current->size) {
            return false;
        }

        return $current->modifiedS === 0 || $current->modifiedS === $mtime;
    }

    private function deleteItemOnDisk(string $path): ?string
    {
        if (is_link($path) || is_file($path)) {
            return unlink($path) ? null : 'removing item to be replaced failed';
        }

        if (!$this->isDirectoryPath($path)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                if (!rmdir($entry->getPathname())) {
                    return 'removing old directory child failed';
                }
                continue;
            }

            if (!unlink($entry->getPathname())) {
                return 'removing old directory child failed';
            }
        }

        return rmdir($path) ? null : 'removing old directory failed';
    }

    private function moveForConflict(string $path, FileInfo $file): ?string
    {
        $conflictName = self::conflictName(
            $file->name,
            $file->modifiedBy > 0 ? (string) $file->modifiedBy : 'unknown',
            $this->conflictTimestamp ?? time(),
        );
        $conflictPath = $this->absolutePath($conflictName);
        $dir = dirname($conflictPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            return 'creating conflict parent directory failed';
        }

        if (!rename($path, $conflictPath)) {
            return 'moving old item for conflict failed';
        }

        $this->addScanName($conflictName);
        $this->pruneConflicts($file);

        return null;
    }

    private function ensureParentDirectory(string $name): ?string
    {
        $slash = strrpos($name, '/');
        if ($slash === false) {
            return null;
        }

        $parent = substr($name, 0, $slash);
        if ($parent === '') {
            return null;
        }

        $parentPath = $this->absolutePath($parent);
        if ($this->isDirectoryPath($parentPath)) {
            return null;
        }
        if ($this->pathExists($parentPath)) {
            return 'checking parent dirs: parent is not a directory';
        }
        if (!mkdir($parentPath, 0755, true) && !$this->isDirectoryPath($parentPath)) {
            return 'creating parent dir failed';
        }

        $this->addScanName($parent);

        return null;
    }

    private function scheduleDbUpdate(FileInfo $file, string $type): void
    {
        $this->dbUpdates[] = [
            'file' => $file,
            'type' => $type,
        ];
    }

    private function fail(string $path, string $error): string
    {
        $this->newPullError($path, $error);

        return $error;
    }

    private function newPullError(string $path, string $error): void
    {
        $this->pullErrors[] = [
            'path' => $path,
            'error' => $error,
        ];
    }

    private function addScanName(string $name): void
    {
        if (!in_array($name, $this->scanNames, true)) {
            $this->scanNames[] = $name;
        }
    }

    private function absolutePath(string $name): string
    {
        ProtocolValidation::checkFilename($name);

        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    private function relativeNameFromPath(string $path): string
    {
        $prefix = $this->rootPath . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $prefix)) {
            throw new \RuntimeException('Directory entry is outside the pull root');
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
    }

    private function parentTraversesSymlink(string $name): bool
    {
        ProtocolValidation::checkFilename($name);

        $parts = explode('/', $name);
        array_pop($parts);
        if ($parts === []) {
            return false;
        }

        $path = $this->rootPath;
        foreach ($parts as $part) {
            $path .= DIRECTORY_SEPARATOR . $part;
            if (is_link($path)) {
                return true;
            }
        }

        return false;
    }

    private function caseConflictName(string $name): ?string
    {
        $slash = strrpos($name, '/');
        $directory = $slash === false ? '' : substr($name, 0, $slash);
        $base = $slash === false ? $name : substr($name, $slash + 1);
        $parentPath = $directory === '' ? $this->rootPath : $this->absolutePath($directory);
        if (!$this->isDirectoryPath($parentPath)) {
            return null;
        }

        $entries = scandir($parentPath);
        if ($entries === false) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === $base) {
                continue;
            }
            if (strcasecmp($entry, $base) === 0) {
                return $directory === '' ? $entry : $directory . '/' . $entry;
            }
        }

        return null;
    }

    private function pathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    private function isDirectoryPath(string $path): bool
    {
        return is_dir($path) && !is_link($path);
    }

    private function directoryMode(FileInfo $file): int
    {
        if ($this->ignorePerms || $file->noPermissions) {
            return 0777;
        }

        return $file->permissions & 0777;
    }

    private function pruneConflicts(FileInfo $file): void
    {
        if ($this->maxConflicts < 0) {
            return;
        }

        $matches = $this->existingConflictNames($file->name);
        if (count($matches) <= $this->maxConflicts) {
            return;
        }

        rsort($matches, SORT_STRING);
        foreach (array_slice($matches, $this->maxConflicts) as $name) {
            $path = $this->absolutePath($name);
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function existingConflictNames(string $name): array
    {
        $slash = strrpos($name, '/');
        $directory = $slash === false ? '' : substr($name, 0, $slash + 1);
        $base = $slash === false ? $name : substr($name, $slash + 1);
        $dot = strrpos($base, '.');
        $stem = $dot === false ? $base : substr($base, 0, $dot);
        $extension = $dot === false ? '' : substr($base, $dot);
        $pattern = $this->absolutePath($directory . $stem . '.sync-conflict-????????-??????*' . $extension);
        $paths = glob($pattern) ?: [];
        $names = [];
        $prefixLength = strlen($this->rootPath . DIRECTORY_SEPARATOR);
        foreach ($paths as $path) {
            $names[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, $prefixLength));
        }

        return $names;
    }

    private static function conflictName(string $name, string $lastModifiedBy, int $timestamp): string
    {
        $slash = strrpos($name, '/');
        $directory = $slash === false ? '' : substr($name, 0, $slash + 1);
        $base = $slash === false ? $name : substr($name, $slash + 1);
        $dot = strrpos($base, '.');
        $stem = $dot === false ? $base : substr($base, 0, $dot);
        $extension = $dot === false ? '' : substr($base, $dot);

        return $directory . $stem . '.sync-conflict-' . date('Ymd-His', $timestamp) . '-' . $lastModifiedBy . $extension;
    }
}
