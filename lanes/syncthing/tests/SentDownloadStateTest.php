<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\SentDownloadState;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream sent download append diff and timestamp semantics' => static function (TestRunner $t): void {
        $state = new SentDownloadState();
        $version = VersionVector::fromCounters([101 => 1]);
        $file = sentDownloadFile('wp-content/uploads/2026/hero.jpg', $version, 11);

        $t->same([], $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $file, [], availableUpdated: 1, created: 1),
        ], minBlocks: 10));

        $updates = $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $file, [1], availableUpdated: 1, created: 1),
        ], minBlocks: 10);
        $t->same(1, count($updates));
        $t->same(FileDownloadProgressUpdate::TYPE_APPEND, $updates[0]->updateType);
        $t->same([1], $updates[0]->blockIndexes);
        $t->same(BlockList::MIN_BLOCK_SIZE, $updates[0]->blockSize);

        $t->same([], $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $file, [1], availableUpdated: 1, created: 1),
        ], minBlocks: 10));

        $t->same([], $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $file, [1], availableUpdated: 2, created: 1),
        ], minBlocks: 10));

        $t->same([], $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $file, [1, 2], availableUpdated: 2, created: 1),
        ], minBlocks: 10));

        $updates = $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $file, [1, 2], availableUpdated: 3, created: 1),
        ], minBlocks: 10);
        $t->same(1, count($updates));
        $t->same([2], $updates[0]->blockIndexes);
        $t->same(['wp-content/uploads/2026/hero.jpg' => 2], $state->getBlockCounts('wordpress-media'));
    },
    'maps upstream version changes and puller recreation forget append pairs' => static function (TestRunner $t): void {
        $state = new SentDownloadState();
        $v1 = VersionVector::fromCounters([101 => 1]);
        $v2 = VersionVector::fromCounters([202 => 1]);
        $fileV1 = sentDownloadFile('wp-content/uploads/2026/hero.jpg', $v1, 11);
        $fileV2 = sentDownloadFile('wp-content/uploads/2026/hero.jpg', $v2, 11);

        $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $fileV1, [1, 2], availableUpdated: 1, created: 1),
        ], minBlocks: 10);

        $updates = $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $fileV2, [1, 2], availableUpdated: 1, created: 1),
        ], minBlocks: 10);
        $t->same(2, count($updates));
        $t->same(FileDownloadProgressUpdate::TYPE_FORGET, $updates[0]->updateType);
        $t->same([101 => 1], $updates[0]->version->toArray());
        $t->same(FileDownloadProgressUpdate::TYPE_APPEND, $updates[1]->updateType);
        $t->same([202 => 1], $updates[1]->version->toArray());
        $t->same([1, 2], $updates[1]->blockIndexes);

        $updates = $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $fileV2, [1], availableUpdated: 2, created: 2),
        ], minBlocks: 10);
        $t->same(2, count($updates));
        $t->same(FileDownloadProgressUpdate::TYPE_FORGET, $updates[0]->updateType);
        $t->same(FileDownloadProgressUpdate::TYPE_APPEND, $updates[1]->updateType);
        $t->same([1], $updates[1]->blockIndexes);

        $updates = $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $fileV1, [], availableUpdated: 3, created: 2),
        ], minBlocks: 10);
        $t->same(2, count($updates));
        $t->same([202 => 1], $updates[0]->version->toArray());
        $t->same(FileDownloadProgressUpdate::TYPE_APPEND, $updates[1]->updateType);
        $t->same([101 => 1], $updates[1]->version->toArray());
        $t->same([], $updates[1]->blockIndexes);
    },
    'maps upstream min block filtering inactive files and completed pull cleanup' => static function (TestRunner $t): void {
        $state = new SentDownloadState();
        $version = VersionVector::fromCounters([101 => 1]);
        $eligible = sentDownloadFile('wp-content/uploads/2026/hero.jpg', $version, 11);
        $tooSmall = sentDownloadFile('wp-content/uploads/2026/icon.jpg', $version, 3);
        $directory = sentDownloadFile('wp-content/uploads/2026', $version, 11, FileInfo::TYPE_DIRECTORY);
        $symlink = sentDownloadFile('wp-content/uploads/current', $version, 11, FileInfo::TYPE_SYMLINK);

        $t->same([], $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $tooSmall, [1, 2, 3], availableUpdated: 1, created: 1),
            new ActiveDownload('wordpress-media', $directory, [1, 2, 3], availableUpdated: 1, created: 1),
            new ActiveDownload('wordpress-media', $symlink, [1, 2, 3], availableUpdated: 1, created: 1),
            new ActiveDownload('other-folder', $eligible, [1, 2, 3], availableUpdated: 1, created: 1),
        ], minBlocks: 10));
        $t->same([], $state->getBlockCounts('wordpress-media'));

        $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $eligible, [1, 2, 3], availableUpdated: 2, created: 1),
        ], minBlocks: 10);

        $updates = $state->update('wordpress-media', [], minBlocks: 10);
        $t->same(1, count($updates));
        $t->same(FileDownloadProgressUpdate::TYPE_FORGET, $updates[0]->updateType);
        $t->same('wp-content/uploads/2026/hero.jpg', $updates[0]->name);
        $t->same([101 => 1], $updates[0]->version->toArray());
        $t->same([], $state->update('wordpress-media', [], minBlocks: 10));
    },
    'maps upstream folder cleanup forget messages' => static function (TestRunner $t): void {
        $state = new SentDownloadState();
        $v1 = VersionVector::fromCounters([101 => 1]);
        $v2 = VersionVector::fromCounters([202 => 1]);
        $first = sentDownloadFile('wp-content/uploads/2026/hero.jpg', $v1, 11);
        $second = sentDownloadFile('wp-content/uploads/2026/banner.jpg', $v2, 11);

        $state->update('wordpress-media', [
            new ActiveDownload('wordpress-media', $first, [1], availableUpdated: 1, created: 1),
            new ActiveDownload('wordpress-media', $second, [5], availableUpdated: 1, created: 1),
        ], minBlocks: 10);

        $updates = $state->cleanup('wordpress-media');
        $names = array_map(static fn (FileDownloadProgressUpdate $update): string => $update->name, $updates);
        sort($names, SORT_STRING);

        $t->same(['wp-content/uploads/2026/banner.jpg', 'wp-content/uploads/2026/hero.jpg'], $names);
        $t->same([], $state->folders());
        $t->same([], $state->cleanup('wordpress-media'));
    },
    'rejects malformed sent download state inputs' => static function (TestRunner $t): void {
        $state = new SentDownloadState();
        $version = VersionVector::fromCounters([101 => 1]);
        $file = sentDownloadFile('wp-content/uploads/2026/hero.jpg', $version, 11);

        $t->throws(InvalidArgumentException::class, static fn () => new ActiveDownload('wordpress-media', $file, [-1]));
        $t->throws(InvalidArgumentException::class, static fn () => new ActiveDownload('wordpress-media', $file, [], availableUpdated: -1));
        $t->throws(InvalidArgumentException::class, static fn () => $state->update('wordpress-media', [new stdClass()]));
        $t->throws(InvalidArgumentException::class, static fn () => $state->update('wordpress-media', [], minBlocks: -1));
    },
];

/**
 * @return list<Block>
 */
function sentDownloadBlocks(int $count): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $hash = hash('sha256', 'wordpress-media-block-' . $i);
        $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, $hash);
    }

    return $blocks;
}

function sentDownloadFile(string $name, VersionVector $version, int $blocks, int $type = FileInfo::TYPE_FILE): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        type: $type,
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        blocks: sentDownloadBlocks($blocks),
    );
}
