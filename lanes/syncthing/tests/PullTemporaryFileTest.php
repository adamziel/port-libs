<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\BlockPullResult;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullFinalizationResult;
use PortLibs\Syncthing\PullTemporaryFile;
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
];

function syncthing_pull_temp_file(string $name, string $bytes, int $permissions = 0644, int $modifiedS = 0): FileInfo
{
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);

    return new FileInfo(
        name: $name,
        modifiedS: $modifiedS,
        version: VersionVector::fromCounters([101 => 1]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: $permissions,
        blocks: $blocks,
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
