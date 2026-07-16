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

    private PlatformMetadataApplier $platformMetadata;

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
        private readonly bool $receiveEncryptedFolder = false,
        private readonly string $directorySeparator = DIRECTORY_SEPARATOR,
        ?PlatformMetadataApplier $platformMetadata = null,
    ) {
        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('Pull item root path must be an existing directory');
        }
        if ($this->maxConflicts < -1) {
            throw new \InvalidArgumentException('Max conflicts must be -1 or greater');
        }

        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $this->platformMetadata = $platformMetadata ?? new PlatformMetadataApplier();
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
                if (!@chmod($path, ($file->permissions & 0777) | (fileperms($path) & 07000))) {
                    return $this->fail($file->name, 'handling dir (setting permissions): chmod failed');
                }
            }
            $metadataError = $this->platformMetadata->apply($file, $path);
            if ($metadataError !== null) {
                return $this->fail($file->name, 'handling dir (setting metadata): ' . $metadataError);
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
            $metadataError = $this->platformMetadata->apply($file, $path);
            if ($metadataError !== null) {
                return $this->fail($file->name, 'symlink create: setting metadata: ' . $metadataError);
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
     * @param iterable<FileInfo> $fileDeletions
     * @param iterable<FileInfo> $directoryDeletions
     * @param iterable<FileInfo> $currentFiles
     * @param iterable<FileInfo> $knownDirectoryChildren
     */
    public function processDeletions(
        iterable $fileDeletions,
        iterable $directoryDeletions,
        iterable $currentFiles = [],
        iterable $knownDirectoryChildren = [],
    ): void {
        $currentByName = $this->fileInfoByName($currentFiles, 'Current files');
        $directoryChildren = $this->fileInfoList($knownDirectoryChildren, 'Known directory children');
        $filesByName = [];

        foreach ($fileDeletions as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('File deletions must be FileInfo instances');
            }
            if ($file->isDirectory() || !$file->isDeleted() || $file->isInvalid()) {
                throw new \InvalidArgumentException('File deletions must be deleted non-directory FileInfos');
            }

            $filesByName[$file->name] = $file;
        }

        foreach ($filesByName as $file) {
            $this->deleteFile($file, $currentByName[$file->name] ?? null);
        }

        $dirs = $this->fileInfoList($directoryDeletions, 'Directory deletions');
        for ($i = count($dirs) - 1; $i >= 0; --$i) {
            $dir = $dirs[$i];
            if (!$dir->isDirectory() || !$dir->isDeleted() || $dir->isInvalid()) {
                throw new \InvalidArgumentException('Directory deletions must be deleted directory FileInfos');
            }

            $this->deleteDirectory($dir, $currentByName[$dir->name] ?? null, $directoryChildren);
        }
    }

    /**
     * @param iterable<FileInfo> $targetFiles
     * @param iterable<FileInfo> $fileDeletions
     * @param iterable<FileInfo> $currentFiles
     *
     * @return list<FileInfo> file tombstones that were not consumed by a rename shortcut
     */
    public function processRenameShortcuts(
        iterable $targetFiles,
        iterable $fileDeletions,
        iterable $currentFiles,
    ): array {
        $currentByName = $this->fileInfoByName($currentFiles, 'Current files');
        $remainingDeletes = [];
        $buckets = [];

        foreach ($fileDeletions as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('File deletions must be FileInfo instances');
            }
            if ($file->isDirectory() || $file->isSymlink() || !$file->isDeleted() || $file->isInvalid()) {
                throw new \InvalidArgumentException('Rename shortcut deletions must be deleted regular-file FileInfos');
            }

            $remainingDeletes[$file->name] = $file;
            $current = $currentByName[$file->name] ?? null;
            if ($current === null || !$this->isRegularAvailableFile($current)) {
                continue;
            }

            $key = $this->blockIdentityKey($current);
            if ($key !== null) {
                $buckets[$key][] = $current;
            }
        }

        foreach ($targetFiles as $target) {
            if (!$target instanceof FileInfo) {
                throw new \InvalidArgumentException('Target files must be FileInfo instances');
            }
            if (!$this->isRegularAvailableFile($target)) {
                throw new \InvalidArgumentException('Rename shortcut targets must be available regular-file FileInfos');
            }

            $key = $this->blockIdentityKey($target);
            if ($key === null || !isset($buckets[$key])) {
                continue;
            }

            while ($buckets[$key] !== []) {
                $candidate = array_shift($buckets[$key]);
                $sourceDeletion = $remainingDeletes[$candidate->name] ?? null;
                if ($sourceDeletion === null) {
                    continue;
                }

                if ($this->renameFileShortcut($candidate, $sourceDeletion, $target, $currentByName[$target->name] ?? null)) {
                    unset($remainingDeletes[$candidate->name]);
                    break;
                }
            }
        }

        return array_values($remainingDeletes);
    }

    /**
     * @param iterable<FileInfo> $neededFiles
     * @param iterable<FileInfo> $currentFiles
     *
     * @return list<FileInfo> files that still need full handle-file work
     */
    public function processMetadataShortcuts(
        iterable $neededFiles,
        iterable $currentFiles,
    ): array {
        $currentByName = $this->fileInfoByName($currentFiles, 'Current files');
        $remainingFiles = [];

        foreach ($neededFiles as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Needed files must be FileInfo instances');
            }
            if (!$this->isRegularAvailableFile($file)) {
                throw new \InvalidArgumentException('Metadata shortcut candidates must be available regular-file FileInfos');
            }

            $current = $currentByName[$file->name] ?? null;
            if ($current !== null && $this->isRegularAvailableFile($current) && $file->blocksEqual($current)) {
                $this->shortcutFile($file, $current);
                continue;
            }

            $remainingFiles[] = $file;
        }

        return $remainingFiles;
    }

    public function shortcutFile(FileInfo $file, ?FileInfo $current = null): bool
    {
        $this->itemStartedEvents[] = [
            'folder' => $this->folderId,
            'item' => $file->name,
            'type' => 'file',
            'action' => 'metadata',
        ];

        try {
            $error = $this->shortcutFileError($file, $current);
        } catch (\Throwable $throwable) {
            $error = $throwable->getMessage();
            $this->newPullError($file->name, $error);
        }

        $this->itemFinishedEvents[] = [
            'folder' => $this->folderId,
            'item' => $file->name,
            'error' => $error,
            'type' => 'file',
            'action' => 'metadata',
        ];

        return $error === null;
    }

    public function renameFileShortcut(
        FileInfo $currentSource,
        FileInfo $sourceDeletion,
        FileInfo $target,
        ?FileInfo $currentTarget = null,
    ): bool {
        $this->itemStartedEvents[] = [
            'folder' => $this->folderId,
            'item' => $sourceDeletion->name,
            'type' => 'file',
            'action' => 'delete',
        ];
        $this->itemStartedEvents[] = [
            'folder' => $this->folderId,
            'item' => $target->name,
            'type' => 'file',
            'action' => 'update',
        ];

        try {
            $error = $this->renameFileShortcutError($currentSource, $sourceDeletion, $target, $currentTarget);
        } catch (\Throwable $throwable) {
            $error = $throwable->getMessage();
        }

        $this->itemFinishedEvents[] = [
            'folder' => $this->folderId,
            'item' => $sourceDeletion->name,
            'error' => $error,
            'type' => 'file',
            'action' => 'delete',
        ];
        $this->itemFinishedEvents[] = [
            'folder' => $this->folderId,
            'item' => $target->name,
            'error' => $error,
            'type' => 'file',
            'action' => 'update',
        ];

        return $error === null;
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

    /**
     * @param iterable<FileInfo> $files
     *
     * @return array<string, FileInfo>
     */
    private function fileInfoByName(iterable $files, string $label): array
    {
        $byName = [];
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException($label . ' must be FileInfo instances');
            }
            $byName[$file->name] = $file;
        }

        return $byName;
    }

    /**
     * @param iterable<FileInfo> $files
     *
     * @return list<FileInfo>
     */
    private function fileInfoList(iterable $files, string $label): array
    {
        $list = [];
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException($label . ' must be FileInfo instances');
            }
            $list[] = $file;
        }

        return $list;
    }

    private function shortcutFileError(FileInfo $file, ?FileInfo $current): ?string
    {
        if (!$this->isRegularAvailableFile($file)) {
            return $this->fail($file->name, 'metadata shortcut target must be an available regular file');
        }
        if ($current !== null) {
            if (!$this->isRegularAvailableFile($current)) {
                return $this->fail($file->name, 'metadata shortcut current file must be an available regular file');
            }
            if (!$file->blocksEqual($current)) {
                return $this->fail($file->name, 'metadata shortcut requires matching block identity');
            }
        }

        $path = $this->absolutePath($file->name);
        if (!is_file($path) || is_link($path)) {
            return $this->fail($file->name, 'shortcut file (setting metadata): file is not a regular file');
        }

        if (!$this->ignorePerms && !$file->noPermissions && !@chmod($path, $file->permissions & 0777)) {
            return $this->fail($file->name, 'shortcut file (setting permissions): chmod failed');
        }
        $metadataError = $this->platformMetadata->apply($file, $path);
        if ($metadataError !== null) {
            return $this->fail($file->name, 'shortcut file (setting metadata): ' . $metadataError);
        }
        $dbFile = $file;
        if ($this->receiveEncryptedFolder) {
            $trailerSize = $this->rewriteEncryptionTrailer($path, $file);
            $dbFile = $file->withSize($file->size + $trailerSize);
        }

        if ($file->modifiedS > 0 && !@touch($path, $file->modifiedS)) {
            return $this->fail($file->name, 'shortcut file (setting metadata): chtimes failed');
        }

        $this->scheduleDbUpdate($dbFile, PullDbUpdater::DB_UPDATE_SHORTCUT_FILE);

        return null;
    }

    private function rewriteEncryptionTrailer(string $path, FileInfo $file): int
    {
        $trailer = ReceiveEncrypted::encryptionTrailer($file, $this->directorySeparator);
        $handle = @fopen($path, 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('writing encrypted file trailer: open failed');
        }

        try {
            if (fseek($handle, $file->size) !== 0) {
                throw new \RuntimeException('writing encrypted file trailer: seek failed');
            }

            $remaining = $trailer;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('writing encrypted file trailer: write failed');
                }
                $remaining = substr($remaining, $written);
            }

            $finalSize = $file->size + strlen($trailer);
            if (!ftruncate($handle, $finalSize)) {
                throw new \RuntimeException('writing encrypted file trailer: truncate failed');
            }
        } finally {
            fclose($handle);
        }

        return strlen($trailer);
    }

    private function renameFileShortcutError(
        FileInfo $currentSource,
        FileInfo $sourceDeletion,
        FileInfo $target,
        ?FileInfo $currentTarget,
    ): ?string {
        if (!$this->isRegularAvailableFile($currentSource)) {
            return 'rename source must be an available regular file';
        }
        if ($sourceDeletion->name !== $currentSource->name || $sourceDeletion->isDirectory() || $sourceDeletion->isSymlink() || !$sourceDeletion->isDeleted() || $sourceDeletion->isInvalid()) {
            return 'rename source deletion must be a deleted regular-file tombstone for the current source';
        }
        if (!$this->isRegularAvailableFile($target)) {
            return 'rename target must be an available regular file';
        }
        if (!$this->sameBlockIdentity($currentSource, $target)) {
            return 'rename shortcut requires matching block identity';
        }

        $check = $this->checkToBeDeleted($sourceDeletion, $currentSource);
        if ($check === self::DELETE_DB_ONLY) {
            return 'rename source is not present on disk';
        }
        if ($check !== null) {
            return 'rename source: ' . $check;
        }

        $targetCheck = $this->checkRenameTarget($target, $currentTarget, $currentSource->name);
        if ($targetCheck !== null) {
            return 'rename target: ' . $targetCheck;
        }

        $parentError = $this->ensureParentDirectory($target->name);
        if ($parentError !== null) {
            return 'rename target: ' . $parentError;
        }

        $sourcePath = $this->absolutePath($currentSource->name);
        $targetPath = $this->absolutePath($target->name);
        $tempName = RequestServer::temporaryName($target->name);
        $tempPath = $this->absolutePath($tempName);

        if ($sourcePath === $targetPath) {
            return 'rename source and target are identical';
        }
        if ($this->pathExists($tempPath) && $tempPath !== $sourcePath) {
            $tempDelete = $this->deleteItemOnDisk($tempPath);
            if ($tempDelete !== null) {
                return 'rename temp cleanup: ' . $tempDelete;
            }
        }

        if (!@rename($sourcePath, $tempPath)) {
            return 'rename source to temporary target failed';
        }

        $replaceError = null;
        if ($this->pathExists($targetPath)) {
            $replaceError = $this->replaceExistingNonDirectory($targetPath, $target, $currentTarget);
        }
        if ($replaceError !== null) {
            $this->restoreRenameSource($tempPath, $sourcePath);
            return 'rename target: ' . $replaceError;
        }

        if (!@rename($tempPath, $targetPath)) {
            $this->restoreRenameSource($tempPath, $sourcePath);
            return 'rename temporary target into place failed';
        }

        $metadataError = $this->applyFileMetadata($targetPath, $target);
        if ($metadataError !== null) {
            return 'rename target: ' . $metadataError;
        }
        $this->scheduleDbUpdate($target, PullDbUpdater::DB_UPDATE_HANDLE_FILE);
        $this->scheduleDbUpdate($sourceDeletion, PullDbUpdater::DB_UPDATE_DELETE_FILE);

        return null;
    }

    private function checkRenameTarget(FileInfo $target, ?FileInfo $currentTarget, string $sourceName): ?string
    {
        if ($this->parentTraversesSymlink($target->name)) {
            $this->addScanName($target->name);
            return self::ERR_MODIFIED;
        }

        $path = $this->absolutePath($target->name);
        if (!$this->pathExists($path)) {
            $caseConflict = $this->caseConflictName($target->name);
            if ($caseConflict !== null && $caseConflict !== $sourceName) {
                $this->addScanName($target->name);
                return self::ERR_MODIFIED;
            }
            if ($currentTarget === null || $currentTarget->isDeleted()) {
                return null;
            }

            $this->addScanName($target->name);
            return self::ERR_MODIFIED;
        }

        if ($currentTarget === null || $currentTarget->isDeleted()) {
            $this->addScanName($target->name);
            return self::ERR_MODIFIED;
        }
        if (!$this->diskEntryMatchesKnownFileInfo($path, $target->name, $currentTarget)) {
            $this->addScanName($target->name);
            return self::ERR_MODIFIED;
        }

        return null;
    }

    private function isRegularAvailableFile(FileInfo $file): bool
    {
        return $file->type === FileInfo::TYPE_FILE && !$file->isDeleted() && !$file->isInvalid();
    }

    private function blockIdentityKey(FileInfo $file): ?string
    {
        if ($file->blocksHash !== '') {
            return 'hash:' . $file->blocksHash;
        }
        if ($file->blocks === []) {
            return null;
        }

        $parts = [];
        foreach ($file->blocks as $block) {
            $parts[] = $block->offset . ':' . $block->size . ':' . strtolower($block->hashHex);
        }

        return 'blocks:' . implode('|', $parts);
    }

    private function sameBlockIdentity(FileInfo $left, FileInfo $right): bool
    {
        $leftKey = $this->blockIdentityKey($left);
        $rightKey = $this->blockIdentityKey($right);

        return $leftKey !== null && $leftKey === $rightKey;
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
            ignoreXattrs: true,
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

    private function restoreRenameSource(string $tempPath, string $sourcePath): void
    {
        if (!$this->pathExists($tempPath) || $this->pathExists($sourcePath)) {
            return;
        }

        @rename($tempPath, $sourcePath);
    }

    private function applyFileMetadata(string $path, FileInfo $file): ?string
    {
        if (!$this->ignorePerms && !$file->noPermissions) {
            if (!@chmod($path, $file->permissions & 0777)) {
                return 'setting permissions: chmod failed';
            }
        }
        $metadataError = $this->platformMetadata->apply($file, $path);
        if ($metadataError !== null) {
            return 'setting metadata: ' . $metadataError;
        }
        if ($file->modifiedS > 0) {
            if (!@touch($path, $file->modifiedS)) {
                return 'setting metadata: chtimes failed';
            }
        }

        return null;
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
