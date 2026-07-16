<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;

$archive = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => '{"source":"gzip-header-crc-preflight","target":"wordpress"}',
    ],
    [
        'name' => 'packet/content.md',
        'data' => "# GZIP header CRC preflight\n\nReady for bounded WordPress archive review.\n",
    ],
]);
$tarBytes = $archive->bytes();
$firstLength = 512;
$firstMember = GzipStream::build(substr($tarBytes, 0, $firstLength), [
    'filename' => 'wordpress-header-crc-part-1.tar',
    'comment' => 'signed gzip member metadata',
    'headerCrc' => true,
]);
$secondMember = GzipStream::build(substr($tarBytes, $firstLength), [
    'filename' => 'wordpress-header-crc-part-2.tar',
    'comment' => 'unsigned gzip member metadata',
]);

$firstMemberMetadata = GzipStream::members($firstMember)[0];
$headerCrcOffset = $firstMemberMetadata['headerCrcOffset'];
if (!is_int($headerCrcOffset)) {
    throw new RuntimeException('Expected first gzip member to carry FHCRC metadata.');
}

$tamperedStream = substr_replace(
    $firstMember,
    chr(ord($firstMember[$headerCrcOffset]) ^ 0x01),
    $headerCrcOffset,
    1
) . $secondMember;

$inspection = ArchiveCompressionStream::inspectGzipHeaderCrcPolicy(
    $tamperedStream,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($tarBytes)
);

if (in_array('--self-test', $argv, true)) {
    if (($inspection['handoffPolicy'] ?? null) !== 'review-before-conversion') {
        throw new RuntimeException('Expected tampered gzip header CRC to require review.');
    }

    if (($inspection['mismatchedHeaderCrcMemberCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected exactly one gzip member header CRC mismatch.');
    }

    if (($inspection['members'][0]['diagnostics'] ?? []) !== ['gzip-member-header-crc-mismatch']) {
        throw new RuntimeException('Expected first gzip member to carry the header CRC mismatch diagnostic.');
    }

    try {
        ArchiveCompressionStream::openTar(
            $tamperedStream,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );
    } catch (RuntimeException) {
        echo "gzip header crc preflight self-test passed\n";
        exit(0);
    }

    throw new RuntimeException('Expected strict gzip TAR opening to reject the tampered FHCRC.');
}

echo "GZIP header CRC preflight for WordPress archive review:\n";
echo 'handoffPolicy=' . $inspection['handoffPolicy'] . "\n";
echo 'mismatchedHeaderCrcMemberCount=' . $inspection['mismatchedHeaderCrcMemberCount'] . "\n";
foreach ($inspection['members'] as $member) {
    echo '- member=' . $member['memberIndex']
        . ' filename=' . ($member['filenameText'] ?? '(none)')
        . ' headerCrcPresent=' . (($member['headerCrcPresent'] ?? false) ? 'yes' : 'no')
        . ' headerCrcMatches=' . (($member['headerCrcMatches'] ?? null) === null ? 'n/a' : (($member['headerCrcMatches'] ?? false) ? 'yes' : 'no'))
        . ' policy=' . $member['policy']
        . "\n";
}
