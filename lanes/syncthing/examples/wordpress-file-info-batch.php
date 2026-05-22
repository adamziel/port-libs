<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoBatch;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
$sequence = 80;
$frames = [];
$lastSequence = 79;

$batch = FileInfoBatch::withFlushFunction(
    static function (array $files) use (&$frames, &$lastSequence): ?Throwable {
        $sequence = max(array_map(static fn (FileInfo $file): int => $file->sequence, $files));
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

foreach ([
    'wp-content/uploads/2026/batch-hero.jpg' => substr($fixture, 0, 64),
    'wp-content/uploads/2026/batch-gallery.jpg' => substr($fixture, 64, 64),
    'wp-content/uploads/2026/batch-original.bin' => $fixture,
] as $name => $bytes) {
    $sequence++;
    $batch->append(wordpressFileInfoBatchFile($name, $bytes, $sequence));
}

$pendingBytes = $batch->size();
$batch->flush();
$decoded = BepWire::decodeIndexUpdateMessage($frames[0]);

echo json_encode([
    'folder' => $decoded->folder,
    'fileCount' => count($decoded->files),
    'paths' => array_map(static fn (FileInfo $file): string => $file->name, $decoded->files),
    'prevSequence' => $decoded->prevSequence,
    'lastSequence' => $decoded->lastSequence,
    'pendingProtobufBytesBeforeFlush' => $pendingBytes,
    'frameBytes' => strlen($frames[0]),
    'batchEmptyAfterFlush' => $batch->count() === 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function wordpressFileInfoBatchFile(string $name, string $bytes, int $sequence): FileInfo
{
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, 32);

    return new FileInfo(
        name: $name,
        modifiedS: 1_700_003_000 + $sequence,
        version: VersionVector::fromCounters([101 => $sequence]),
        size: strlen($bytes),
        blocksHash: $blockList->hashBlocks($blocks),
        permissions: 0644,
        rawBlockSize: 32,
        sequence: $sequence,
        blocks: $blocks,
        modifiedBy: 101,
    );
}
