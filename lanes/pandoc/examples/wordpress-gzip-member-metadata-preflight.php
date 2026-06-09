<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"gzip-member-metadata","target":"wordpress"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# GZIP member metadata\n\nReady for WordPress archive review.\n",
    ],
]);

$tarBytes = $archive->bytes();
$splitOffset = 512;
$gzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
    'filename' => '../wp-content\\uploads.tar',
    'comment' => "review\x7fsource",
]) . GzipStream::build(substr($tarBytes, $splitOffset), [
    'filename' => 'wordpress-gzip-member-metadata-part-2.tar',
    'comment' => 'safe decoded package segment',
]);

$inspection = ArchiveCompressionStream::inspectGzipMemberMetadataPolicy(
    $gzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tarBytes)
);

if (in_array('--self-test', $argv, true)) {
    if ($inspection['handoffPolicy'] !== 'review-before-conversion') {
        throw new RuntimeException('Expected unsafe gzip member metadata to require review.');
    }

    if (($inspection['unsafeFilenameMemberCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected one gzip member filename to be marked unsafe.');
    }

    if (($inspection['unsafeCommentMemberCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected one gzip member comment to be marked unsafe.');
    }

    if (($inspection['diagnostics'] ?? []) !== [
        'gzip-member-filename-backslash-path',
        'gzip-member-filename-parent-segment',
        'gzip-member-comment-control-bytes',
    ]) {
        throw new RuntimeException('Expected gzip member metadata diagnostics to be preserved.');
    }

    if (isset($inspection['members'][0]['data']) || isset($inspection['archive']) || isset($inspection['tarBytes'])) {
        throw new RuntimeException('Expected gzip member metadata preflight to avoid exposing decoded package bytes.');
    }

    echo "gzip member metadata preflight self-test passed\n";
    exit(0);
}

echo "GZIP member metadata preflight for WordPress archive review:\n";
echo 'policy=' . $inspection['handoffPolicy'] . "\n";
echo 'diagnostics=' . implode(',', $inspection['diagnostics']) . "\n";
echo 'unsafeFilenameMembers=' . $inspection['unsafeFilenameMemberCount'] . "\n";
echo 'unsafeCommentMembers=' . $inspection['unsafeCommentMemberCount'] . "\n";
