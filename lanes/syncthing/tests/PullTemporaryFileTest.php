<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\BlockPullResult;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullFinalizationResult;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\VersionVector;

return [
    'assembles copied sparse and pulled blocks into final media file' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $blockSize = BlockList::MIN_BLOCK_SIZE;
            $bytes = str_repeat('A', $blockSize)
                . str_repeat("\0", $blockSize)
                . str_repeat('B', $blockSize);
            $file = syncthing_pull_temp_file('wp-content/uploads/2026/finalized-hero.jpg', $bytes, 0600, 1700000000);
            $assembler = new PullTemporaryFile($file, $root);

            $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize), source: 'copiedFromOrigin');
            $assembler->skipSparseBlock($file->blocks[1]);
            $assembler->applyPullResult(new BlockPullResult(
                block: $file->blocks[2],
                data: substr($bytes, 2 * $blockSize, $blockSize),
            ));

            $result = $assembler->finalize();
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);
            $tempPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, RequestServer::temporaryName($file->name));

            $t->true($result->closed);
            $t->true($result->finalized);
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
            $t->same([0, 1, 2], $result->availableBlockIndexes);
            $t->same([
                0 => 'copiedFromOrigin',
                1 => 'sparseSkipped',
                2 => 'pulled',
            ], $assembler->sourcesByBlockIndex());
            $t->same($bytes, (string) file_get_contents($finalPath));
            $t->true(!file_exists($tempPath));
            $t->same(0600, fileperms($finalPath) & 0777);
            $t->same(1700000000, filemtime($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'finalClose waits for all blocks and is idempotent after rename' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $blockSize = BlockList::MIN_BLOCK_SIZE;
            $bytes = str_repeat('first', intdiv($blockSize, 5))
                . str_repeat('second', intdiv($blockSize, 6));
            $file = syncthing_pull_temp_file('wp-content/uploads/2026/two-block.jpg', $bytes);
            $assembler = new PullTemporaryFile($file, $root);

            $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize), source: 'copiedFromElsewhere');

            $notReady = $assembler->finalize();
            $t->true(!$notReady->closed);
            $t->true(!$notReady->finalized);
            $t->same([0], $notReady->availableBlockIndexes);
            $t->true(file_exists($assembler->tempPath()));

            $assembler->applyPullResult(new BlockPullResult(
                block: $file->blocks[1],
                data: substr($bytes, $blockSize),
            ));
            $done = $assembler->finalize();
            $again = $assembler->finalize();

            $t->true($done->closed);
            $t->true($done->finalized);
            $t->same([0, 1], $done->availableBlockIndexes);
            $t->true(!$again->closed);
            $t->true(!$again->finalized);
            $t->throws(LogicException::class, static fn () => $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize)));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'temporary files keep upstream provisional owner write permissions' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('private-media-draft', 6000);
            $file = syncthing_pull_temp_file('wp-content/uploads/private/draft.jpg', $bytes, 0400, 1700000400);
            $assembler = new PullTemporaryFile($file, $root);

            $assembler->writeBlock($file->blocks[0], $bytes, source: 'pulled');
            $t->same(0600, syncthing_pull_temp_mode($assembler->tempPath()));

            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same(0400, syncthing_pull_temp_mode($assembler->finalPath()));
            $t->same($bytes, (string) file_get_contents($assembler->finalPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'reused read-only temporary files are made writable before block writes' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('correct-private-block', 6000);
            $file = syncthing_pull_temp_file('wp-content/uploads/private/resumed.jpg', $bytes, 0400, 1700000500);
            $tempName = RequestServer::temporaryName($file->name);
            $tempPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tempName);
            if (!mkdir(dirname($tempPath), 0777, true) && !is_dir(dirname($tempPath))) {
                throw new RuntimeException('Failed to create temporary test directory');
            }
            file_put_contents($tempPath, str_repeat('stale', 100));
            chmod($tempPath, 0400);

            $assembler = new PullTemporaryFile($file, $root, $tempName);
            $assembler->writeBlock($file->blocks[0], $bytes, source: 'pulledAfterRestart');

            $t->same(0600, syncthing_pull_temp_mode($tempPath));
            $t->same($bytes, (string) file_get_contents($tempPath));

            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same([0 => 'pulledAfterRestart'], $assembler->sourcesByBlockIndex());
            $t->true(!file_exists($tempPath));
            $t->same($bytes, (string) file_get_contents($assembler->finalPath()));
            $t->same(0400, syncthing_pull_temp_mode($assembler->finalPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish moves conflicting local file aside before promotion' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/concurrent-edit.jpg';
            $localBytes = str_repeat('local editor crop ', 5000);
            $remoteBytes = str_repeat('remote camera edit ', 5000);
            $current = syncthing_pull_temp_file($name, $localBytes, 0644, 1700000600, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700000700,
                VersionVector::fromCounters([202 => 2]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            file_put_contents($finalPath, $localBytes);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, conflictTimestamp: 1700000800);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledConcurrentWinner');
            $result = $assembler->finalize();
            $expectedConflict = 'wp-content/uploads/2026/concurrent-edit.sync-conflict-'
                . date('Ymd-His', 1700000800)
                . '-202.jpg';
            $conflictPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedConflict);

            $t->true($result->finalized);
            $t->same($expectedConflict, $result->conflictName);
            $t->same([$expectedConflict], $result->scanNames);
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
            $t->same($localBytes, (string) file_get_contents($conflictPath));
            $t->same([0 => 'pulledConcurrentWinner'], $assembler->sourcesByBlockIndex());
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish replaces non-conflicting descendant file without conflict copy' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/metadata-only.jpg';
            $localBytes = str_repeat('local published media ', 4000);
            $remoteBytes = str_repeat('remote normalized media ', 4000);
            $current = syncthing_pull_temp_file($name, $localBytes, 0644, 1700000900, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001000,
                VersionVector::fromCounters([101 => 4, 202 => 1]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            file_put_contents($finalPath, $localBytes);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, conflictTimestamp: 1700001100);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledDescendant');
            $result = $assembler->finalize();
            $conflicts = glob($root . DIRECTORY_SEPARATOR . 'wp-content/uploads/2026/*.sync-conflict-*') ?: [];

            $t->true($result->finalized);
            $t->same(null, $result->conflictName);
            $t->same([], $result->scanNames);
            $t->same([], $conflicts);
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'failed block pulls close but leave temporary file reusable' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('missing-media', 12000);
            $file = syncthing_pull_temp_file('wp-content/uploads/2026/retry-later.jpg', $bytes);
            $assembler = new PullTemporaryFile($file, $root);
            $failed = new BlockPullResult($file->blocks[0], error: 'temporary peer disconnected');

            $t->true(!$assembler->applyPullResult($failed));
            $result = $assembler->finalize();

            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);
            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->same('pull: temporary peer disconnected', $result->error);
            $t->same([], $result->availableBlockIndexes);
            $t->true(file_exists($assembler->tempPath()));
            $t->true(!file_exists($finalPath));
            $t->throws(LogicException::class, static fn () => $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $file->blocks[0]->size)));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'receive-encrypted finalization appends FileInfo trailer before promotion' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $plainBytes = str_repeat('private wordpress media export ', 24);
            $plainBlocks = (new BlockList())->fromBytes($plainBytes, strlen($plainBytes));
            $plainFile = new FileInfo(
                name: 'wp-content/uploads/2026/private/finalized-pull.bin',
                modifiedS: 1700002300,
                version: VersionVector::fromCounters([77 => 16]),
                size: strlen($plainBytes),
                blocksHash: (new BlockList())->hashBlocks($plainBlocks),
                rawBlockSize: strlen($plainBytes),
                sequence: 161,
                blocks: $plainBlocks,
                modifiedBy: 77,
            );
            $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'wordpress media sync secret');
            $fileKey = ReceiveEncrypted::fileKey($plainFile->name, $folderKey);
            $encryptedFile = ReceiveEncrypted::encryptFileInfo(
                $plainFile,
                $folderKey,
                str_repeat("\12", ReceiveEncrypted::NONCE_SIZE),
            );
            $encryptedData = ReceiveEncrypted::encryptBytes(
                $plainBytes . str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes)),
                $fileKey,
                str_repeat("\13", ReceiveEncrypted::NONCE_SIZE),
            );

            $assembler = new PullTemporaryFile($encryptedFile, $root);
            $assembler->writeBlock($encryptedFile->blocks[0], $encryptedData, receiveEncrypted: true, source: 'receiveEncryptedPull');

            $result = $assembler->finalize();
            $finalBytes = (string) file_get_contents($assembler->finalPath());
            $verified = ReceiveEncrypted::verifyFinalizedEncryptedFile($finalBytes, $folderKey);

            $t->true($result->closed);
            $t->true($result->finalized);
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
            $t->same($encryptedFile->size, strlen($encryptedData));
            $t->same($encryptedData, $verified['encryptedData']);
            $t->same($plainBytes, $verified['plaintext']);
            $t->same($plainFile->name, $verified['plainFile']->name);
            $t->same($verified['trailerSize'], $result->encryptionTrailerSize);
            $t->same(strlen($finalBytes), $result->finalSize);
            $t->same($encryptedFile->size + $verified['trailerSize'], $result->finalSize);
            $t->same([0], $result->availableBlockIndexes);
            $t->same([0 => 'receiveEncryptedPull'], $assembler->sourcesByBlockIndex());
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
];

function syncthing_pull_temp_file(
    string $name,
    string $bytes,
    int $permissions = 0644,
    int $modifiedS = 0,
    ?VersionVector $version = null,
    int $modifiedBy = 101,
): FileInfo
{
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);

    return new FileInfo(
        name: $name,
        modifiedS: $modifiedS,
        version: $version ?? VersionVector::fromCounters([101 => 1]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: $permissions,
        blocks: $blocks,
        modifiedBy: $modifiedBy,
    );
}

function syncthing_pull_temp_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-pull-temp-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary pull root');
    }

    return $root;
}

function syncthing_pull_temp_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function syncthing_pull_temp_mode(string $path): int
{
    clearstatcache(true, $path);

    return fileperms($path) & 0777;
}
