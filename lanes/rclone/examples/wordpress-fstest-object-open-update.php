<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$remote = new MemoryProvider();
$remote->put('exports/site.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-21T00:00:00Z',
    'mimeType' => 'application/rss+xml',
    'metadata' => ['wp-artifact' => 'wxr'],
]);

$rangePreview = $remote->readObject('exports/site.wxr', [
    'rangeStart' => 0,
    'rangeEnd' => 4,
]);
$rangeTail = $remote->readObject('exports/site.wxr', [
    'rangeStart' => -1,
    'rangeEnd' => 6,
]);

$freshExport = '<rss version="2.0"><channel><item>post</item></channel></rss>';
$updated = $remote->updateObject('exports/site.wxr', $freshExport, [
    'sourcePath' => 'tmp/provider-stream-name.bin',
    'modTime' => '2026-05-22T00:00:00Z',
    'mimeType' => 'application/xml',
    'metadata' => ['wp-artifact' => 'wxr', 'wp-export' => 'full'],
]);

$streamed = $remote->putStream('exports/piped-import.wxr', $tree['exports/site.wxr'], [
    'unknownSize' => true,
    'modTime' => '2026-05-22T01:00:00Z',
]);

return [
    'rangePreview' => $rangePreview,
    'rangeTail' => $rangeTail,
    'updatedPath' => $updated->path,
    'updatedSize' => $updated->size,
    'updatedModTime' => $updated->modTime,
    'ignoredSourceVisible' => $remote->pathExists('tmp/provider-stream-name.bin'),
    'putStreamPath' => $streamed->path,
    'putStreamSize' => $streamed->size,
    'objects' => array_map(static fn ($info) => $info->path, $remote->list('exports')),
];
