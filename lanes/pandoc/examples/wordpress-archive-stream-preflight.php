<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;
use PortLibs\Pandoc\TarArchiveEntry;

$rawTarHeader = static function (string $name, string $typeFlag, string $data = '', int $modifiedAt = 0, bool $withEndMarker = true): string {
    $octal = static function (int $value, int $length): string {
        return str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";
    };
    $field = static fn (string $value, int $length): string => str_pad($value, $length, "\0");

    $header = $field($name, 100)
        . $octal(0644, 8)
        . $octal(0, 8)
        . $octal(0, 8)
        . $octal(strlen($data), 12)
        . $octal($modifiedAt, 12)
        . str_repeat(' ', 8)
        . $typeFlag
        . $field('', 100)
        . "ustar\0"
        . '00'
        . $field('', 32)
        . $field('', 32)
        . $octal(0, 8)
        . $octal(0, 8)
        . $field('', 155)
        . str_repeat("\0", 12);

    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $checksum += ord($header[$index]);
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
    $padding = strlen($data) % 512 === 0 ? '' : str_repeat("\0", 512 - (strlen($data) % 512));

    return $header . $data . $padding . ($withEndMarker ? str_repeat("\0", 1024) : '');
};

$paxPayload = static function (array $headers): string {
    $payload = '';
    foreach ($headers as $key => $value) {
        $body = " {$key}={$value}\n";
        $recordLength = strlen($body) + 1;
        do {
            $nextLength = strlen((string) $recordLength) + strlen($body);
            if ($nextLength === $recordLength) {
                $payload .= $recordLength . $body;
                break;
            }
            $recordLength = $nextLength;
        } while (true);
    }

    return $payload;
};

$manifestBytes = '{"source":"wordpress-archive-stream","target":"review"}';
$contentBytes = "# Archived source packet\n\nReady for WordPress import review.\n";
$legacyContentBytes = "# Legacy contiguous source packet\n\nReady for WordPress archive review.\n";
$legacyDirectoryBytes = '';
$paxDeleteContentBytes = "# PAX deletion packet\n\nReady for WordPress archive provenance review.\n";
$paxInheritedContentBytes = "# PAX inherited packet\n\nReady for WordPress archive provenance review.\n";

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

$legacyContiguousArchiveBytes = $rawTarHeader(
    'packet/legacy-contiguous.md',
    '7',
    $legacyContentBytes,
    1780479069
);
$legacyContiguousGzip = GzipStream::build($legacyContiguousArchiveBytes, [
    'filename' => 'wordpress-legacy-contiguous.tar',
    'comment' => 'legacy contiguous TAR preflight',
]);
$legacyContiguousInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $legacyContiguousGzip,
    strlen($legacyContiguousArchiveBytes),
    strlen($legacyContentBytes)
);
$legacyDirectoryArchiveBytes = $rawTarHeader(
    'packet/legacy-directory/',
    '0',
    $legacyDirectoryBytes,
    1780479070
);
$legacyDirectoryGzip = GzipStream::build($legacyDirectoryArchiveBytes, [
    'filename' => 'wordpress-legacy-directory.tar',
    'comment' => 'legacy trailing-slash TAR directory preflight',
]);
$legacyDirectoryInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $legacyDirectoryGzip,
    strlen($legacyDirectoryArchiveBytes),
    strlen($legacyDirectoryBytes)
);
$paxDeleteArchiveBytes = $rawTarHeader('GlobalHead/review', 'g', $paxPayload([
    'comment' => 'global WordPress archive review',
    'mtime' => '1780479074',
    'uname' => 'global-reviewer',
]), 0, false)
    . $rawTarHeader('PaxHeaders/local-delete', 'x', $paxPayload([
        'comment' => '',
        'mtime' => '',
        'uname' => '',
        'org.wordpress.import.review' => 'local-clean',
    ]), 0, false)
    . $rawTarHeader('packet/pax-delete.md', '0', $paxDeleteContentBytes, 1780479073, false)
    . $rawTarHeader('packet/pax-inherited.md', '0', $paxInheritedContentBytes, 0, false)
    . str_repeat("\0", 1024);
$paxDeleteGzip = GzipStream::build($paxDeleteArchiveBytes, [
    'filename' => 'wordpress-pax-delete.tar',
    'comment' => 'PAX deletion metadata preflight',
]);
$paxDeleteInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $paxDeleteGzip,
    strlen($paxDeleteArchiveBytes),
    strlen($paxDeleteContentBytes) + strlen($paxInheritedContentBytes)
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
        'gzipMemberOffset' => 0,
        'content' => $contentBytes,
        'legacyFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'legacyEntryType' => TarArchiveEntry::TYPE_FILE,
        'legacyContent' => $legacyContentBytes,
        'legacyDirectoryType' => TarArchiveEntry::TYPE_DIRECTORY,
        'legacyDirectoryCount' => 1,
        'paxDeleteFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'paxDeleteLocalModifiedAt' => 1780479073,
        'paxDeleteInheritedModifiedAt' => 1780479074,
    ];

    if ($inspection['kind'] !== $expected['kind']
        || $inspection['format'] !== $expected['format']
        || $inspection['entryNames'] !== $expected['entries']
        || $inspection['regularFileCount'] !== $expected['regularFileCount']
        || $inspection['directoryCount'] !== $expected['directoryCount']
        || $inspection['trailingZeroBytes'] !== $expected['trailingZeroBytes']
        || ($inspection['stream']['members'][0]['filename'] ?? null) !== $expected['gzipFilename']
        || ($inspection['stream']['members'][0]['memberOffset'] ?? null) !== $expected['gzipMemberOffset']
        || ($inspection['stream']['members'][0]['compressedDataOffset'] ?? 0) <= ($inspection['stream']['members'][0]['memberOffset'] ?? 0)
        || ($inspection['stream']['members'][0]['trailerOffset'] ?? 0) <= ($inspection['stream']['members'][0]['compressedDataOffset'] ?? 0)
        || ($inspection['stream']['members'][0]['nextMemberOffset'] ?? null) !== ($inspection['stream']['members'][0]['memberSize'] ?? null)
        || $inspection['archive']->read('/packet/content.md') !== $expected['content']
        || ($inspection['entryLayouts'][2]['paxHeaderKeys'] ?? []) !== ['atime', 'ctime']
        || $legacyContiguousInspection['format'] !== $expected['legacyFormat']
        || ($legacyContiguousInspection['entryLayouts'][0]['type'] ?? null) !== $expected['legacyEntryType']
        || $legacyContiguousInspection['archive']->read('/packet/legacy-contiguous.md') !== $expected['legacyContent']
        || ($legacyDirectoryInspection['entryLayouts'][0]['type'] ?? null) !== $expected['legacyDirectoryType']
        || $legacyDirectoryInspection['directoryCount'] !== $expected['legacyDirectoryCount']
        || $legacyDirectoryInspection['archive']->read('/packet/legacy-directory/') !== $legacyDirectoryBytes
        || $paxDeleteInspection['format'] !== $expected['paxDeleteFormat']
        || ($paxDeleteInspection['archive']->entry('/packet/pax-delete.md')->paxHeaders['comment'] ?? null) !== null
        || ($paxDeleteInspection['archive']->entry('/packet/pax-delete.md')->paxHeaders['mtime'] ?? null) !== null
        || ($paxDeleteInspection['archive']->entry('/packet/pax-delete.md')->paxHeaders['org.wordpress.import.review'] ?? null) !== 'local-clean'
        || $paxDeleteInspection['archive']->entry('/packet/pax-delete.md')->modifiedAt !== $expected['paxDeleteLocalModifiedAt']
        || $paxDeleteInspection['archive']->entry('/packet/pax-inherited.md')->modifiedAt !== $expected['paxDeleteInheritedModifiedAt']
        || ($paxDeleteInspection['archive']->entry('/packet/pax-inherited.md')->paxHeaders['comment'] ?? null) !== 'global WordPress archive review'
        || $paxDeleteInspection['archive']->read('/packet/pax-delete.md') !== $paxDeleteContentBytes
        || ($paxDeleteInspection['entryLayouts'][0]['paxGlobalHeaderKeys'] ?? []) !== ['comment', 'mtime', 'uname']
        || ($paxDeleteInspection['entryLayouts'][0]['paxLocalHeaderKeys'] ?? []) !== ['comment', 'mtime', 'org.wordpress.import.review', 'uname']
        || ($paxDeleteInspection['entryLayouts'][0]['paxDeletedHeaderKeys'] ?? []) !== ['comment', 'mtime', 'uname']
        || ($paxDeleteInspection['entryLayouts'][0]['nameSource'] ?? null) !== 'header'
        || ($paxDeleteInspection['entryLayouts'][1]['paxGlobalHeaderKeys'] ?? []) !== ['comment', 'mtime', 'uname']
        || ($paxDeleteInspection['entryLayouts'][1]['paxLocalHeaderKeys'] ?? []) !== []
        || ($paxDeleteInspection['entryLayouts'][1]['paxDeletedHeaderKeys'] ?? []) !== []
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
echo 'gzip.memberOffset=' . $inspection['stream']['members'][0]['memberOffset'] . "\n";
echo 'gzip.compressedDataOffset=' . $inspection['stream']['members'][0]['compressedDataOffset'] . "\n";
echo 'gzip.trailerOffset=' . $inspection['stream']['members'][0]['trailerOffset'] . "\n";
echo 'tar.layout=' . implode(',', $layoutSummary) . "\n";
echo 'content.md=' . $inspection['archive']->read('/packet/content.md') . "\n";
echo 'legacyContiguous.format=' . $legacyContiguousInspection['format'] . "\n";
echo 'legacyContiguous.entryType=' . $legacyContiguousInspection['entryLayouts'][0]['type'] . "\n";
echo 'legacyContiguous.content.md=' . $legacyContiguousInspection['archive']->read('/packet/legacy-contiguous.md') . "\n";
echo 'legacyDirectory.format=' . $legacyDirectoryInspection['format'] . "\n";
echo 'legacyDirectory.entryType=' . $legacyDirectoryInspection['entryLayouts'][0]['type'] . "\n";
echo 'legacyDirectory.directoryCount=' . $legacyDirectoryInspection['directoryCount'] . "\n";
echo 'paxDelete.format=' . $paxDeleteInspection['format'] . "\n";
echo 'paxDelete.localModifiedAt=' . $paxDeleteInspection['archive']->entry('/packet/pax-delete.md')->modifiedAt . "\n";
echo 'paxDelete.localReview=' . $paxDeleteInspection['archive']->entry('/packet/pax-delete.md')->paxHeaders['org.wordpress.import.review'] . "\n";
echo 'paxDelete.inheritedComment=' . $paxDeleteInspection['archive']->entry('/packet/pax-inherited.md')->paxHeaders['comment'] . "\n";
echo 'paxDelete.localGlobalKeys=' . implode(',', $paxDeleteInspection['entryLayouts'][0]['paxGlobalHeaderKeys']) . "\n";
echo 'paxDelete.localPaxKeys=' . implode(',', $paxDeleteInspection['entryLayouts'][0]['paxLocalHeaderKeys']) . "\n";
echo 'paxDelete.localDeletedKeys=' . implode(',', $paxDeleteInspection['entryLayouts'][0]['paxDeletedHeaderKeys']) . "\n";
