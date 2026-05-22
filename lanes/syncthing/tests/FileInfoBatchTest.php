<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoBatch;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream file info batch flush error lifecycle' => static function (TestRunner $t): void {
        $error = null;
        $called = 0;
        $batch = FileInfoBatch::withFlushFunction(
            static function (array $files) use (&$error, &$called): ?Throwable {
                $called++;

                return $error;
            },
        );

        $batch->append(syncthing_file_info_batch_file('test'));
        $t->same(null, $batch->flush());
        $t->same(1, $called);
        $t->same(0, $batch->count());
        $t->same(0, $batch->size());

        $error = new RuntimeException('problem');
        $batch->append(syncthing_file_info_batch_file('test'));
        $t->same($error, $batch->flush());
        $t->same(2, $called);

        $t->same($error, $batch->flush());
        $t->same($error, $batch->flushIfFull());
        $t->same(2, $called);
        $t->throws(LogicException::class, static fn () => $batch->append(syncthing_file_info_batch_file('after-error')));

        $error = null;
        $batch->reset();
        $batch->append(syncthing_file_info_batch_file('test'));
        $t->same(null, $batch->flush());
        $t->same(3, $called);
    },
    'flushes only when upstream file count or byte limits are reached' => static function (TestRunner $t): void {
        $flushes = [];
        $batch = FileInfoBatch::withFlushFunction(
            static function (array $files) use (&$flushes): ?Throwable {
                $flushes[] = array_map(static fn (FileInfo $file): string => $file->name, $files);

                return null;
            },
        );

        $batch->append(syncthing_file_info_batch_file('wp-content/uploads/2026/hero.jpg'));
        $t->true(!$batch->full());
        $t->same(null, $batch->flushIfFull());
        $t->same([], $flushes);

        for ($i = 1; $i < FileInfoBatch::MAX_BATCH_SIZE_FILES; $i++) {
            $batch->append(syncthing_file_info_batch_file('wp-content/uploads/2026/gallery-' . $i . '.jpg'));
        }

        $t->same(FileInfoBatch::MAX_BATCH_SIZE_FILES, $batch->count());
        $t->true($batch->full());
        $t->same(null, $batch->flushIfFull());
        $t->same(1, count($flushes));
        $t->same(FileInfoBatch::MAX_BATCH_SIZE_FILES, count($flushes[0]));
        $t->same(0, $batch->count());

        $oversizedName = 'wp-content/uploads/2026/' . str_repeat('large-media-', 24_000) . '.bin';
        $batch->append(syncthing_file_info_batch_file($oversizedName));
        $t->true($batch->size() >= FileInfoBatch::MAX_BATCH_SIZE_BYTES);
        $t->true($batch->full());
        $t->same(null, $batch->flushIfFull());
        $t->same(2, count($flushes));
        $t->same([$oversizedName], $flushes[1]);
    },
    'batches wordpress media file infos into index update frames' => static function (TestRunner $t): void {
        $frames = [];
        $lastSequence = 41;
        $batch = FileInfoBatch::withFlushFunction(
            static function (array $files) use (&$frames, &$lastSequence): ?Throwable {
                $sequence = $lastSequence + count($files);
                $frames[] = BepWire::encodeIndexUpdateMessage(new IndexUpdate(
                    'wordpress-media',
                    $files,
                    lastSequence: $sequence,
                    prevSequence: $lastSequence,
                ));
                $lastSequence = $sequence;

                return null;
            },
        );

        $batch->append(syncthing_file_info_batch_file('wp-content/uploads/2026/hero.jpg', sequence: 42));
        $batch->append(syncthing_file_info_batch_file('wp-content/uploads/2026/gallery.jpg', sequence: 43));
        $t->same(null, $batch->flush());

        $decoded = BepWire::decodeIndexUpdateMessage($frames[0]);
        $t->same('wordpress-media', $decoded->folder);
        $t->same(43, $decoded->lastSequence);
        $t->same(41, $decoded->prevSequence);
        $t->same([
            'wp-content/uploads/2026/hero.jpg',
            'wp-content/uploads/2026/gallery.jpg',
        ], array_map(static fn (FileInfo $file): string => $file->name, $decoded->files));
    },
];

function syncthing_file_info_batch_file(string $name, int $sequence = 1): FileInfo
{
    $bytes = 'wordpress media bytes for ' . $name;
    $block = new Block(0, strlen($bytes), hash('sha256', $bytes));

    return new FileInfo(
        name: $name,
        modifiedS: 1_700_001_000,
        version: VersionVector::fromCounters([101 => $sequence]),
        size: strlen($bytes),
        blocksHash: hash('sha256', hex2bin($block->hashHex) ?: ''),
        permissions: 0644,
        rawBlockSize: max(1, strlen($bytes)),
        sequence: $sequence,
        blocks: [$block],
        modifiedBy: 101,
    );
}
