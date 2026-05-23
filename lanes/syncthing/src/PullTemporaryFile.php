<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullTemporaryFile
{
    private const ERR_DIR_HAS_TO_BE_SCANNED = 'directory has been deleted on a remote device but contains changed files, scheduling scan';
    private const ERR_DIR_HAS_IGNORED = 'directory has been deleted on a remote device but contains ignored files (see ignore documentation for (?d) prefix)';
    private const ERR_DIR_NOT_EMPTY = 'directory has been deleted on a remote device but is not empty; the contents are probably ignored on that remote device, but not locally';
    private const ERR_MODIFIED = 'checking existing file: file modified but not rescanned; will try again later';
    private const ALL_LOCAL_FLAGS = FileInfo::FLAG_LOCAL_UNSUPPORTED
        | FileInfo::FLAG_LOCAL_IGNORED
        | FileInfo::FLAG_LOCAL_MUST_RESCAN
        | FileInfo::FLAG_LOCAL_RECEIVE_ONLY
        | FileInfo::FLAG_LOCAL_GLOBAL
        | FileInfo::FLAG_LOCAL_NEEDED
        | FileInfo::FLAG_LOCAL_REMOTE_INVALID;

    private string $rootPath;

    private PlatformMetadataApplier $platformMetadata;

    private string $tempName;

    private ?string $error = null;

    private bool $closed = false;

    private int $finalSize = 0;

    private int $encryptionTrailerSize = 0;

    /**
     * @var array<int, true>
     */
    private array $available = [];

    /**
     * @var array<int, string>
     */
    private array $sources = [];

    private ?string $conflictName = null;

    private ?string $archivedName = null;

    /**
     * @var list<string>
     */
    private array $scanNames = [];

    public function __construct(
        public readonly FileInfo $file,
        string $rootPath,
        ?string $tempName = null,
        private readonly bool $ignorePerms = false,
        private readonly bool $sparse = true,
        private readonly ?FileInfo $currentFile = null,
        private readonly int $maxConflicts = -1,
        private readonly ?int $conflictTimestamp = null,
        private readonly ?string $archiveRootPath = null,
        private readonly ?int $archiveTimestamp = null,
        /** @var list<FileInfo>|null */
        private readonly ?array $knownDirectoryChildren = null,
        private readonly ?IgnoreMatcher $ignoreMatcher = null,
        private readonly bool $receiveOnlyFolder = false,
        private readonly bool $detectCaseConflicts = false,
        ?PlatformMetadataApplier $platformMetadata = null,
    ) {
        if ($this->file->type !== FileInfo::TYPE_FILE || $this->file->deleted || $this->file->isInvalid()) {
            throw new \InvalidArgumentException('Pull temporary files can only assemble valid regular files');
        }
        if ($this->maxConflicts < -1) {
            throw new \InvalidArgumentException('Max conflicts must be -1 or greater');
        }
        if ($this->archiveRootPath !== null && ($this->archiveRootPath === '' || str_contains($this->archiveRootPath, "\0"))) {
            throw new \InvalidArgumentException('Archive root path must be null or a valid path');
        }
        if ($this->archiveTimestamp !== null && $this->archiveTimestamp < 0) {
            throw new \InvalidArgumentException('Archive timestamp must be non-negative');
        }
        if ($this->knownDirectoryChildren !== null) {
            foreach ($this->knownDirectoryChildren as $child) {
                if (!$child instanceof FileInfo) {
                    throw new \InvalidArgumentException('Known directory children must be FileInfo instances');
                }
            }
        }

        ProtocolValidation::checkFileInfoConsistency($this->file);

        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('Pull root path must be an existing directory');
        }
        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $this->platformMetadata = $platformMetadata ?? new PlatformMetadataApplier();

        $this->tempName = $tempName ?? RequestServer::temporaryName($this->file->name);
        ProtocolValidation::checkFilename($this->tempName);
    }

    public function tempName(): string
    {
        return $this->tempName;
    }

    public function tempPath(): string
    {
        return $this->absolutePath($this->tempName);
    }

    public function finalPath(): string
    {
        return $this->absolutePath($this->file->name);
    }

    public function writeBlock(Block $block, string $bytes, bool $receiveEncrypted = false, string $source = 'pulled'): void
    {
        $this->assertCanWrite();
        $index = $this->blockIndex($block);
        if ($source === '') {
            throw new \InvalidArgumentException('Block source must not be empty');
        }

        $length = strlen($bytes);
        if ($length !== $block->size) {
            $this->fail('length mismatch ' . $length . ' != ' . $block->size);
            throw new \LengthException('Pulled block length does not match FileInfo block size');
        }
        if (!$receiveEncrypted && !hash_equals(strtolower($block->hashHex), hash('sha256', $bytes))) {
            $this->fail('hash mismatch');
            throw new \UnexpectedValueException('Pulled block hash does not match FileInfo block hash');
        }

        $this->writeBytesAt($bytes, $block->offset);
        $this->markAvailable($index, $source);
    }

    public function skipSparseBlock(Block $block): void
    {
        $this->assertCanWrite();
        if (!$block->isAllZeroes()) {
            throw new \InvalidArgumentException('Only all-zero blocks can be skipped as sparse blocks');
        }

        if (!$this->sparse) {
            $this->writeBlock($block, str_repeat("\0", $block->size), source: 'zeroWritten');
            return;
        }

        $this->ensureTempFile();
        $this->markAvailable($this->blockIndex($block), 'sparseSkipped');
    }

    public function applyPullResult(BlockPullResult $result, bool $receiveEncrypted = false): bool
    {
        if (!$result->successful()) {
            $this->fail('pull: ' . ($result->error ?? 'request failed'));
            return false;
        }

        if ($result->zeroBlock && $this->sparse) {
            $this->skipSparseBlock($result->block);
            return true;
        }

        $this->writeBlock($result->block, $result->data, $receiveEncrypted, 'pulled');
        return true;
    }

    public function fail(string $error): void
    {
        if ($error === '') {
            throw new \InvalidArgumentException('Pull failure must not be empty');
        }

        $this->error ??= $error;
    }

    public function finalize(): PullFinalizationResult
    {
        if ($this->closed) {
            return $this->result(closed: false, finalized: false, error: null);
        }

        if ($this->error === null && !$this->hasAllBlocks()) {
            return $this->result(closed: false, finalized: false, error: null);
        }

        $this->ensureTempFile();
        $this->closed = true;

        if ($this->error !== null) {
            return $this->result(closed: true, finalized: false, error: $this->error);
        }

        try {
            $this->appendEncryptionTrailerIfNeeded();
        } catch (\Throwable $e) {
            $this->error = 'finalizing encrypted file: ' . $e->getMessage();
            return $this->result(closed: true, finalized: false, error: $this->error);
        }

        if (!$this->ignorePerms && !$this->file->noPermissions) {
            if (!@chmod($this->tempPath(), $this->file->permissions & 0777)) {
                $this->error = 'setting permissions: chmod failed';
                return $this->result(closed: true, finalized: false, error: $this->error);
            }
        }

        $metadataError = $this->platformMetadata->apply($this->file, $this->tempPath());
        if ($metadataError !== null) {
            $this->error = 'setting metadata: ' . $metadataError;
            return $this->result(closed: true, finalized: false, error: $this->error);
        }

        $finalPath = $this->finalPath();
        $finalDir = dirname($finalPath);
        if (!is_dir($finalDir) && !mkdir($finalDir, 0777, true) && !is_dir($finalDir)) {
            $this->error = 'creating parent directory failed';
            return $this->result(closed: true, finalized: false, error: $this->error);
        }

        if (!$this->checkCaseOnlyFinalPath()) {
            return $this->result(closed: true, finalized: false, error: $this->error);
        }

        if ((is_link($finalPath) || is_dir($finalPath)) && $this->shouldDeleteExistingNonRegular()) {
            if (!$this->existingPathMatchesCurrentFile($finalPath)) {
                return $this->result(closed: true, finalized: false, error: $this->error);
            }
            if (!$this->deleteExistingNonRegular($finalPath)) {
                return $this->result(closed: true, finalized: false, error: $this->error);
            }
        }

        if (is_link($finalPath) || is_dir($finalPath)) {
            $this->error = 'existing final path is not a regular file';
            return $this->result(closed: true, finalized: false, error: $this->error);
        }
        if (is_file($finalPath) && !$this->replaceExistingFinalFile($finalPath)) {
            return $this->result(closed: true, finalized: false, error: $this->error);
        }

        if (!@rename($this->tempPath(), $finalPath)) {
            if (!$this->copyThenRemove($this->tempPath(), $finalPath)) {
                $this->error = 'replacing final file failed';
                return $this->result(closed: true, finalized: false, error: $this->error);
            }
        }

        if ($this->file->modifiedS > 0) {
            @touch($finalPath, $this->file->modifiedS);
        }

        clearstatcache(true, $finalPath);
        $this->finalSize = max(0, (int) filesize($finalPath));

        return $this->result(
            closed: true,
            finalized: true,
            error: null,
            dbUpdateType: PullFinalizationResult::DB_UPDATE_HANDLE_FILE,
        );
    }

    /**
     * @return list<int>
     */
    public function availableBlockIndexes(): array
    {
        $indexes = array_keys($this->available);
        sort($indexes, SORT_NUMERIC);

        return array_values($indexes);
    }

    /**
     * @return array<int, string>
     */
    public function sourcesByBlockIndex(): array
    {
        ksort($this->sources, SORT_NUMERIC);

        return $this->sources;
    }

    private function assertCanWrite(): void
    {
        if ($this->closed) {
            throw new \LogicException('Cannot write to a closed pull temporary file');
        }
        if ($this->error !== null) {
            throw new \LogicException('Cannot write to a failed pull temporary file');
        }
    }

    private function ensureTempFile(): void
    {
        $path = $this->tempPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create pull temporary directory');
        }

        if (!$this->ignorePerms && is_file($path)) {
            @chmod($path, $this->temporaryMode());
        }

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open pull temporary file');
        }

        try {
            if (!$this->ignorePerms) {
                @chmod($path, $this->temporaryMode());
            }
            if ($this->sparse && !ftruncate($handle, $this->file->size)) {
                throw new \RuntimeException('Failed to size pull temporary file');
            }
        } finally {
            fclose($handle);
        }
    }

    private function writeBytesAt(string $bytes, int $offset): void
    {
        $this->ensureTempFile();

        $handle = @fopen($this->tempPath(), 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open pull temporary file for writing');
        }

        try {
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                throw new \RuntimeException('Failed to seek pull temporary file');
            }

            $remaining = $bytes;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Failed to write pull temporary block');
                }
                $remaining = substr($remaining, $written);
            }
        } finally {
            fclose($handle);
        }
    }

    private function markAvailable(int $index, string $source): void
    {
        $this->available[$index] = true;
        $this->sources[$index] = $source;
    }

    private function hasAllBlocks(): bool
    {
        if ($this->file->blocks === []) {
            return true;
        }

        foreach (array_keys($this->file->blocks) as $index) {
            if (!isset($this->available[$index])) {
                return false;
            }
        }

        return true;
    }

    private function blockIndex(Block $block): int
    {
        foreach ($this->file->blocks as $index => $candidate) {
            if ($candidate->offset === $block->offset && $candidate->size === $block->size) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('Block is not part of the target FileInfo');
    }

    private function absolutePath(string $name): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    private function appendEncryptionTrailerIfNeeded(): void
    {
        if ($this->file->encryptedPayload === '') {
            return;
        }

        $path = $this->tempPath();
        clearstatcache(true, $path);
        $size = filesize($path);
        if ($size === false) {
            throw new \RuntimeException('could not stat temporary file');
        }
        if ($size !== $this->file->size) {
            throw new \LengthException('encrypted temporary file size does not match FileInfo size');
        }

        $trailer = ReceiveEncrypted::encryptionTrailer($this->file);
        $handle = @fopen($path, 'ab');
        if ($handle === false) {
            throw new \RuntimeException('could not reopen temporary file for trailer append');
        }

        try {
            $remaining = $trailer;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('could not append encrypted FileInfo trailer');
                }
                $remaining = substr($remaining, $written);
            }
        } finally {
            fclose($handle);
        }

        $this->encryptionTrailerSize = strlen($trailer);
        $this->finalSize = $this->file->size + $this->encryptionTrailerSize;
    }

    private function temporaryMode(): int
    {
        return ($this->file->permissions & 0777) | 0600;
    }

    private function result(bool $closed, bool $finalized, ?string $error, string $dbUpdateType = ''): PullFinalizationResult
    {
        return new PullFinalizationResult(
            closed: $closed,
            finalized: $finalized,
            error: $error,
            tempName: $this->tempName,
            finalName: $this->file->name,
            availableBlockIndexes: $this->availableBlockIndexes(),
            dbUpdateType: $dbUpdateType,
            finalSize: $this->finalSize,
            encryptionTrailerSize: $this->encryptionTrailerSize,
            conflictName: $this->conflictName,
            scanNames: $this->scanNames,
            archivedName: $this->archivedName,
        );
    }

    private function copyThenRemove(string $source, string $destination): bool
    {
        $sourceHandle = @fopen($source, 'rb');
        if ($sourceHandle === false) {
            return false;
        }

        try {
            $destinationHandle = @fopen($destination, 'wb');
            if ($destinationHandle === false) {
                return false;
            }

            try {
                if (stream_copy_to_stream($sourceHandle, $destinationHandle) === false) {
                    return false;
                }
            } finally {
                fclose($destinationHandle);
            }
        } finally {
            fclose($sourceHandle);
        }

        return unlink($source);
    }

    private function replaceExistingFinalFile(string $finalPath): bool
    {
        if (!$this->existingPathMatchesCurrentFile($finalPath)) {
            return false;
        }

        if (!$this->shouldMoveExistingForConflict()) {
            if ($this->shouldArchiveExistingFinalFile()) {
                return $this->archiveExistingFinalFile($finalPath);
            }

            if (!unlink($finalPath)) {
                $this->error = 'removing old final file failed';
                return false;
            }

            return true;
        }

        if (self::isConflictName($this->file->name) || $this->maxConflicts === 0) {
            if (!unlink($finalPath)) {
                $this->error = 'removing old conflict file failed';
                return false;
            }

            return true;
        }

        $conflictName = self::conflictName(
            $this->file->name,
            $this->modifiedByLabel(),
            $this->conflictTimestamp ?? time(),
        );
        $conflictPath = $this->absolutePath($conflictName);
        $conflictDir = dirname($conflictPath);
        if (!is_dir($conflictDir) && !mkdir($conflictDir, 0777, true) && !is_dir($conflictDir)) {
            $this->error = 'creating conflict parent directory failed';
            return false;
        }

        if (!@rename($finalPath, $conflictPath)) {
            $this->error = 'moving old final file for conflict failed';
            return false;
        }

        $this->conflictName = $conflictName;
        $this->scanNames[] = $conflictName;
        $this->pruneConflicts();

        return true;
    }

    private function existingPathMatchesCurrentFile(string $path): bool
    {
        if ($this->currentFile === null || $this->currentFile->deleted) {
            $this->addScanName($this->file->name);
            $this->error = self::ERR_MODIFIED;
            return false;
        }

        if (!$this->diskEntryMatchesKnownFileInfo($path, $this->currentFile->name, $this->currentFile)) {
            $this->addScanName($this->file->name);
            $this->error = self::ERR_MODIFIED;
            return false;
        }

        return true;
    }

    private function shouldMoveExistingForConflict(): bool
    {
        if ($this->currentFile === null || $this->currentFile->isDirectory() || $this->currentFile->isSymlink()) {
            return false;
        }

        return $this->file->inConflictWith($this->currentFile);
    }

    private function checkCaseOnlyFinalPath(): bool
    {
        if (
            !$this->detectCaseConflicts
            || $this->currentFile === null
            || $this->currentFile->deleted
            || $this->currentFile->name === $this->file->name
            || strcasecmp($this->currentFile->name, $this->file->name) !== 0
        ) {
            return true;
        }

        $finalPath = $this->finalPath();
        if (file_exists($finalPath) || is_link($finalPath)) {
            return true;
        }

        $realPath = $this->absolutePath($this->currentFile->name);
        if (!file_exists($realPath) && !is_link($realPath)) {
            return true;
        }

        $this->error = 'checking existing file: ' . self::caseConflictMessage($this->file->name, $this->currentFile->name);
        return false;
    }

    private function shouldArchiveExistingFinalFile(): bool
    {
        return $this->archiveRootPath !== null
            && $this->currentFile !== null
            && !$this->currentFile->isDirectory()
            && !$this->currentFile->isSymlink();
    }

    private function archiveExistingFinalFile(string $finalPath): bool
    {
        clearstatcache(true, $finalPath);
        $archiveName = self::archiveName($this->file->name, $this->archiveTimestamp ?? time());
        $archivePath = $this->archiveAbsolutePath($archiveName);
        $archiveDir = dirname($archivePath);
        if (!is_dir($archiveDir) && !mkdir($archiveDir, 0777, true) && !is_dir($archiveDir)) {
            $this->error = 'creating version archive parent directory failed';
            return false;
        }

        $mtime = filemtime($finalPath);
        $mode = fileperms($finalPath);
        if (!@rename($finalPath, $archivePath)) {
            if (!$this->copyThenRemove($finalPath, $archivePath)) {
                $this->error = 'archiving old final file failed';
                return false;
            }
        }
        if (is_int($mtime)) {
            @touch($archivePath, $mtime);
        }
        if (is_int($mode)) {
            @chmod($archivePath, $mode & 0777);
        }

        $this->archivedName = $archiveName;
        return true;
    }

    private function shouldDeleteExistingNonRegular(): bool
    {
        return $this->currentFile !== null
            && ($this->currentFile->isDirectory() || $this->currentFile->isSymlink());
    }

    private function deleteExistingNonRegular(string $path): bool
    {
        if (is_link($path)) {
            if (!unlink($path)) {
                $this->error = 'removing old symlink failed';
                return false;
            }

            return true;
        }

        if (!is_dir($path)) {
            return true;
        }

        if (!$this->prepareDirectoryChildrenForDeletion($path)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entryPath = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                if (!rmdir($entryPath)) {
                    $this->error = 'removing old directory child failed';
                    return false;
                }
            } elseif (!unlink($entryPath)) {
                $this->error = 'removing old directory child failed';
                return false;
            }
        }

        if (!rmdir($path)) {
            $this->error = 'removing old directory failed';
            return false;
        }

        return true;
    }

    private function prepareDirectoryChildrenForDeletion(string $directoryPath): bool
    {
        if ($this->knownDirectoryChildren === null) {
            return true;
        }

        $directoryName = $this->relativeNameFromPath($directoryPath);
        $knownChildren = $this->knownDirectoryChildrenByName($directoryName);
        $dirsToDelete = [];
        $hasIgnored = false;
        $hasKnown = false;
        $hasToBeScanned = false;
        $hasReceiveOnlyChanged = false;
        $deleteError = null;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS),
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
                    $deleteError = 'removing old directory child failed';
                }
                continue;
            }

            if ($match !== null && $match->isIgnored()) {
                $hasIgnored = true;
                continue;
            }

            $known = $knownChildren[$entryName] ?? null;
            if ($known === null || $known->deleted) {
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
                $deleteError = 'removing old directory child failed';
            }
        }

        if ($hasToBeScanned) {
            $this->error = self::ERR_DIR_HAS_TO_BE_SCANNED;
            return false;
        }
        if ($hasIgnored) {
            $this->error = self::ERR_DIR_HAS_IGNORED;
            return false;
        }
        if ($hasReceiveOnlyChanged) {
            $this->addScanName($directoryName);
            return true;
        }
        if ($hasKnown) {
            $this->error = self::ERR_DIR_NOT_EMPTY;
            return false;
        }
        if ($deleteError !== null) {
            $this->error = $deleteError;
            return false;
        }

        return true;
    }

    /**
     * @return array<string, FileInfo>
     */
    private function knownDirectoryChildrenByName(string $directoryName): array
    {
        $prefix = rtrim($directoryName, '/') . '/';
        $children = [];
        foreach ($this->knownDirectoryChildren ?? [] as $child) {
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

        if (is_dir($path)) {
            return $known->isDirectory();
        }

        if (!is_file($path)) {
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

    private function relativeNameFromPath(string $path): string
    {
        $prefix = $this->rootPath . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $prefix)) {
            throw new \RuntimeException('Directory entry is outside the pull root');
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
    }

    private function addScanName(string $name): void
    {
        if (!in_array($name, $this->scanNames, true)) {
            $this->scanNames[] = $name;
        }
    }

    private function modifiedByLabel(): string
    {
        return $this->file->modifiedBy > 0 ? (string) $this->file->modifiedBy : 'unknown';
    }

    private function archiveAbsolutePath(string $name): string
    {
        if ($this->archiveRootPath === null) {
            throw new \LogicException('Archive root path is not configured');
        }

        $root = $this->archiveRootPath;
        if (!self::isAbsolutePath($root)) {
            $root = $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $root);
        }

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private static function archiveName(string $name, int $timestamp): string
    {
        $slash = strrpos($name, '/');
        $directory = $slash === false ? '' : substr($name, 0, $slash + 1);
        $base = $slash === false ? $name : substr($name, $slash + 1);
        $dot = strrpos($base, '.');
        $stem = $dot === false ? $base : substr($base, 0, $dot);
        $extension = $dot === false ? '' : substr($base, $dot);

        return $directory . $stem . '~' . date('Ymd-His', $timestamp) . $extension;
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

    private static function isConflictName(string $name): bool
    {
        $slash = strrpos($name, '/');
        $base = $slash === false ? $name : substr($name, $slash + 1);

        return str_contains($base, '.sync-conflict-');
    }

    private static function caseConflictMessage(string $given, string $real): string
    {
        return 'remote "' . $given . '" uses different upper or lowercase characters than local "' . $real . '"; change the casing on either side to match the other';
    }

    private function pruneConflicts(): void
    {
        if ($this->maxConflicts < 0) {
            return;
        }

        $matches = $this->existingConflictNames();
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
    private function existingConflictNames(): array
    {
        $slash = strrpos($this->file->name, '/');
        $directory = $slash === false ? '' : substr($this->file->name, 0, $slash + 1);
        $base = $slash === false ? $this->file->name : substr($this->file->name, $slash + 1);
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
}
