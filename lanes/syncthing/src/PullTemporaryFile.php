<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullTemporaryFile
{
    private string $rootPath;

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
    ) {
        if ($this->file->type !== FileInfo::TYPE_FILE || $this->file->deleted || $this->file->isInvalid()) {
            throw new \InvalidArgumentException('Pull temporary files can only assemble valid regular files');
        }
        if ($this->maxConflicts < -1) {
            throw new \InvalidArgumentException('Max conflicts must be -1 or greater');
        }

        ProtocolValidation::checkFileInfoConsistency($this->file);

        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('Pull root path must be an existing directory');
        }
        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);

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
            @chmod($this->tempPath(), $this->file->permissions & 0777);
        }

        $finalPath = $this->finalPath();
        $finalDir = dirname($finalPath);
        if (!is_dir($finalDir) && !mkdir($finalDir, 0777, true) && !is_dir($finalDir)) {
            $this->error = 'creating parent directory failed';
            return $this->result(closed: true, finalized: false, error: $this->error);
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
        if (!$this->shouldMoveExistingForConflict()) {
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

    private function shouldMoveExistingForConflict(): bool
    {
        if ($this->currentFile === null || $this->currentFile->isDirectory() || $this->currentFile->isSymlink()) {
            return false;
        }

        return $this->file->inConflictWith($this->currentFile);
    }

    private function modifiedByLabel(): string
    {
        return $this->file->modifiedBy > 0 ? (string) $this->file->modifiedBy : 'unknown';
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
