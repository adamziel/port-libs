<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;
use PortLibs\Pandoc\TarArchiveEntry;

$manifestBytes = '{"source":"wordpress-archive-stream","target":"review"}';
$contentBytes = "# Archived source packet\n\nReady for WordPress import review.\n";

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/',
        'type' => TarArchiveEntry::TYPE_DIRECTORY,
        'modifiedAt' => 1780479063,
    ],
    [
        'name' => 'packet/manifest.json',
        'data' => $manifestBytes,
        'modifiedAt' => 1780479064,
        'mode' => 0640,
        'userName' => 'wp-reviewer',
        'groupName' => 'import-team',
    ],
    [
        'name' => 'packet/content.md',
        'data' => $contentBytes,
        'modifiedAt' => 1780479065,
        'accessedAt' => 1780479066,
        'changedAt' => 1780479067,
    ],
]);

$gzip = GzipStream::build($archive->bytes(), [
    'filename' => 'wordpress-archive-stream.tar',
    'comment' => 'WordPress archive stream preflight',
    'modifiedAt' => 1780479068,
    'headerCrc' => true,
]);

$inspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $gzip,
    strlen($archive->bytes()),
    strlen($manifestBytes) + strlen($contentBytes)
);

$layoutSummary = array_map(
    static fn (array $layout): string => implode(':', [
        $layout['name'],
        $layout['type'],
        (string) $layout['headerOffset'],
        (string) $layout['dataOffset'],
        (string) $layout['size'],
    ]),
    $inspection['entryLayouts']
);

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'kind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'format' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'entries' => ['packet/', 'packet/manifest.json', 'packet/content.md'],
        'regularFileCount' => 2,
        'directoryCount' => 1,
        'trailingZeroBytes' => 1024,
        'gzipFilename' => 'wordpress-archive-stream.tar',
        'content' => $contentBytes,
    ];

    if ($inspection['kind'] !== $expected['kind']
        || $inspection['format'] !== $expected['format']
        || $inspection['entryNames'] !== $expected['entries']
        || $inspection['regularFileCount'] !== $expected['regularFileCount']
        || $inspection['directoryCount'] !== $expected['directoryCount']
        || $inspection['trailingZeroBytes'] !== $expected['trailingZeroBytes']
        || ($inspection['stream']['members'][0]['filename'] ?? null) !== $expected['gzipFilename']
        || $inspection['archive']->read('/packet/content.md') !== $expected['content']
        || ($inspection['entryLayouts'][2]['paxHeaderKeys'] ?? []) !== ['atime', 'ctime']
    ) {
        throw new RuntimeException('archive stream preflight self-test failed');
    }

    echo "wordpress-archive-stream-preflight self-test passed\n";
    return;
}

echo 'kind=' . $inspection['kind'] . "\n";
echo 'format=' . $inspection['format'] . "\n";
echo 'entries=' . implode(',', $inspection['entryNames']) . "\n";
echo 'regularFileCount=' . $inspection['regularFileCount'] . "\n";
echo 'directoryCount=' . $inspection['directoryCount'] . "\n";
echo 'unpackedSize=' . $inspection['unpackedSize'] . "\n";
echo 'trailingZeroBytes=' . $inspection['trailingZeroBytes'] . "\n";
echo 'gzip.filename=' . $inspection['stream']['members'][0]['filename'] . "\n";
echo 'gzip.comment=' . $inspection['stream']['members'][0]['comment'] . "\n";
echo 'tar.layout=' . implode(',', $layoutSummary) . "\n";
echo 'content.md=' . $inspection['archive']->read('/packet/content.md') . "\n";
