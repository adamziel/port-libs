<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;
use PortLibs\Pandoc\TarArchiveEntry;
use PortLibs\Pandoc\ZipPackage;

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

$rewriteTarHeaderFields = static function (string $archive, array $fields): string {
    $header = substr($archive, 0, 512);
    foreach ($fields as $offset => $field) {
        $header = substr_replace($header, $field, (int) $offset, strlen($field));
    }

    $header = substr_replace($header, str_repeat(' ', 8), 148, 8);
    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $checksum += ord($header[$index]);
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

    return $header . substr($archive, 512);
};

$rewriteTarHeaderWithSignedChecksum = static function (string $archive): string {
    $header = substr_replace(substr($archive, 0, 512), str_repeat(' ', 8), 148, 8);
    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $byte = ord($header[$index]);
        $checksum += $byte < 128 ? $byte : $byte - 256;
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

    return $header . substr($archive, 512);
};

$manifestBytes = '{"source":"wordpress-archive-stream","target":"review"}';
$contentBytes = "# Archived source packet\n\nReady for WordPress import review.\n";
$legacyContentBytes = "# Legacy contiguous source packet\n\nReady for WordPress archive review.\n";
$legacyDirectoryBytes = '';
$paxDeleteContentBytes = "# PAX deletion packet\n\nReady for WordPress archive provenance review.\n";
$paxInheritedContentBytes = "# PAX inherited packet\n\nReady for WordPress archive provenance review.\n";
$linkPolicySourceBytes = "# Link target source\n\nReady for WordPress archive link policy review.\n";
$sparsePolicyTypePayload = 'gnu sparse payload fragment';
$sparsePolicyPaxPayload = 'schily sparse payload fragment';
$signedChecksumContentBytes = "# Signed checksum source packet\n\nReady for WordPress archive review.\n";
$charsetContentBytes = "# PAX charset source packet\n\nReady for WordPress archive charset review.\n";
$nestedSourceBytes = "# Nested archive source\n\nReady for WordPress nested archive review.\n";
$nestedWordXml = '<w:document><w:body><w:p>Nested DOCX review packet</w:p></w:body></w:document>';

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
$linkPolicyArchiveBytes = $rawTarHeader('packet/link-source.md', '0', $linkPolicySourceBytes, 1780479075, false)
    . $rewriteTarHeaderFields(
        $rawTarHeader('packet/link-hard-copy.md', '1', '', 1780479076, false),
        [157 => str_pad('packet/link-source.md', 100, "\0")]
    )
    . $rawTarHeader('PaxHeaders/link-symlink', 'x', $paxPayload([
        'linkpath' => 'packet/media/review.png',
    ]), 0, false)
    . $rewriteTarHeaderFields(
        $rawTarHeader('packet/media/latest.png', '2', '', 1780479077, false),
        [157 => str_pad('ignored-header-target.png', 100, "\0")]
    )
    . str_repeat("\0", 1024);
$linkPolicyGzip = GzipStream::build($linkPolicyArchiveBytes, [
    'filename' => 'wordpress-link-policy.tar',
    'comment' => 'TAR link extraction policy preflight',
]);
$linkPolicyInspection = ArchiveCompressionStream::inspectTarLinkPolicy(
    $linkPolicyGzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($linkPolicyArchiveBytes)
);
$linkPolicyExtractionBlocked = false;
try {
    TarArchive::fromString($linkPolicyArchiveBytes);
} catch (RuntimeException) {
    $linkPolicyExtractionBlocked = true;
}
$sparsePolicyArchiveBytes = $rawTarHeader('packet/gnu-type-sparse.bin', 'S', $sparsePolicyTypePayload, 1780479080, false)
    . $rawTarHeader('PaxHeaders/sparse-policy', 'x', $paxPayload([
        'path' => 'packet/schily-pax-sparse.bin',
        'SCHILY.filetype' => 'sparse',
        'SCHILY.realsize' => '8192',
        'SCHILY.sparse.map' => '0,16,8176,16',
    ]), 0, false)
    . $rawTarHeader('placeholder-schily.bin', '0', $sparsePolicyPaxPayload, 1780479081, false)
    . str_repeat("\0", 1024);
$sparsePolicyGzip = GzipStream::build($sparsePolicyArchiveBytes, [
    'filename' => 'wordpress-sparse-policy.tar',
    'comment' => 'TAR sparse extraction policy preflight',
]);
$sparsePolicyInspection = ArchiveCompressionStream::inspectTarSparsePolicy(
    $sparsePolicyGzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($sparsePolicyArchiveBytes)
);
$sparsePolicyExtractionBlocked = false;
try {
    TarArchive::fromString($sparsePolicyArchiveBytes);
} catch (RuntimeException) {
    $sparsePolicyExtractionBlocked = true;
}
$sparseMalformedMapBlocked = false;
try {
    TarArchive::sparsePolicyPreflight(
        $rawTarHeader('PaxHeaders/malformed-sparse-map', 'x', $paxPayload([
            'path' => 'packet/malformed-sparse-map.bin',
            'SCHILY.filetype' => 'sparse',
            'SCHILY.realsize' => '8192',
            'SCHILY.sparse.map' => '0,16,8176',
        ]), 0, false)
        . $rawTarHeader('placeholder-malformed.bin', '0', 'sparse payload fragment', 0, false)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException) {
    $sparseMalformedMapBlocked = true;
}
$signedChecksumArchiveBytes = $rewriteTarHeaderWithSignedChecksum($rawTarHeader(
    "packet/signed-\u{2603}-checksum.md",
    '0',
    $signedChecksumContentBytes,
    1780479083
));
$signedChecksumGzip = GzipStream::build($signedChecksumArchiveBytes, [
    'filename' => 'wordpress-signed-checksum.tar',
    'comment' => 'historic signed TAR checksum preflight',
]);
$signedChecksumInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $signedChecksumGzip,
    strlen($signedChecksumArchiveBytes),
    strlen($signedChecksumContentBytes)
);
$charsetArchiveBytes = $rawTarHeader('GlobalHead/charset', 'g', $paxPayload([
    'hdrcharset' => 'ISO-IR 10646 2000 UTF-8',
    'comment' => 'UTF-8 PAX metadata',
]), 0, false)
    . $rawTarHeader('PaxHeaders/local-charset', 'x', $paxPayload([
        'path' => "packet/charset-\u{2603}.md",
        'hdrcharset' => 'BINARY',
        'size' => (string) strlen($charsetContentBytes),
        'uname' => 'wp-reviewer',
    ]), 0, false)
    . $rawTarHeader('placeholder.md', '0', $charsetContentBytes, 1780479084, false)
    . str_repeat("\0", 1024);
$charsetGzip = GzipStream::build($charsetArchiveBytes, [
    'filename' => 'wordpress-pax-hdrcharset.tar',
    'comment' => 'PAX hdrcharset preflight',
]);
$charsetInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
    $charsetGzip,
    strlen($charsetArchiveBytes),
    strlen($charsetContentBytes)
);
$invalidCharsetBlocked = false;
try {
    TarArchive::fromString(
        $rawTarHeader('GlobalHead/invalid-charset', 'g', $paxPayload([
            'hdrcharset' => 'UTF-16LE',
        ]), 0, false)
        . $rawTarHeader('packet/invalid-charset.md', '0', $charsetContentBytes, 0, false)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException) {
    $invalidCharsetBlocked = true;
}
$nestedZipPackage = ZipPackage::fromParts([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/document.xml',
        'data' => $nestedWordXml,
    ],
]);
$nestedInnerTar = TarArchive::fromEntries([
    [
        'name' => 'packet/nested-source.md',
        'data' => $nestedSourceBytes,
    ],
    [
        'name' => 'packet/deeper/document.docx',
        'data' => $nestedZipPackage->bytes(),
    ],
]);
$nestedArchiveBytes = TarArchive::fromEntries([
    [
        'name' => 'packet/content.md',
        'data' => "# Nested outer packet\n\nReady for WordPress review.\n",
    ],
    [
        'name' => 'packet/nested/review.tar.gz',
        'data' => GzipStream::build($nestedInnerTar->bytes(), [
            'filename' => 'nested-review.tar',
            'comment' => 'nested tar review packet',
        ]),
    ],
    [
        'name' => 'packet/nested/document.docx',
        'data' => $nestedZipPackage->bytes(),
    ],
    [
        'name' => 'packet/nested/broken.zip',
        'data' => "PK\x03\x04truncated-nested-review",
    ],
])->bytes();
$nestedGzip = GzipStream::build($nestedArchiveBytes, [
    'filename' => 'wordpress-nested-archive-review.tar',
    'comment' => 'nested archive discovery preflight',
]);
$nestedInspection = ArchiveCompressionStream::inspectNestedPackageStreamsAuto(
    $nestedGzip,
    strlen($nestedArchiveBytes),
    strlen($nestedArchiveBytes),
    2
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
        'linkPolicyFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'linkPolicyEntryCount' => 3,
        'linkPolicyLinkCount' => 2,
        'linkPolicyExtractionPolicy' => 'link-entries-blocked',
        'linkPolicyHardTarget' => 'packet/link-source.md',
        'linkPolicySymlinkTarget' => 'packet/media/review.png',
        'sparsePolicyFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'sparsePolicyEntryCount' => 2,
        'sparsePolicySparseCount' => 2,
        'sparsePolicyExtractionPolicy' => 'sparse-entries-blocked',
        'sparsePolicyGnuTypeName' => 'packet/gnu-type-sparse.bin',
        'sparsePolicySchilyName' => 'packet/schily-pax-sparse.bin',
        'sparsePolicyRealSize' => 8192,
        'sparsePolicyMapSource' => 'SCHILY.sparse.map',
        'sparsePolicyMapSegmentCount' => 2,
        'sparsePolicyMapPayloadBytes' => 32,
        'signedChecksumFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'signedChecksumName' => "packet/signed-\u{2603}-checksum.md",
        'signedChecksumModifiedAt' => 1780479083,
        'charsetFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'charsetName' => "packet/charset-\u{2603}.md",
        'charsetGlobalHdrcharset' => 'ISO-IR 10646 2000 UTF-8',
        'charsetLocalHdrcharset' => 'BINARY',
        'nestedRootKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'nestedRootFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'nestedCandidateCount' => 4,
        'nestedPackageCount' => 3,
        'nestedDiagnosticCount' => 1,
        'nestedFirstPath' => 'packet/nested/review.tar.gz',
        'nestedDeeperPath' => 'packet/nested/review.tar.gz!packet/deeper/document.docx',
        'nestedBrokenPath' => 'packet/nested/broken.zip',
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
        || $linkPolicyInspection['format'] !== $expected['linkPolicyFormat']
        || $linkPolicyInspection['entryCount'] !== $expected['linkPolicyEntryCount']
        || $linkPolicyInspection['linkEntryCount'] !== $expected['linkPolicyLinkCount']
        || $linkPolicyInspection['extractionPolicy'] !== $expected['linkPolicyExtractionPolicy']
        || !$linkPolicyExtractionBlocked
        || ($linkPolicyInspection['entries'][0]['linkType'] ?? null) !== 'hard-link'
        || ($linkPolicyInspection['entries'][0]['linkTarget'] ?? null) !== $expected['linkPolicyHardTarget']
        || ($linkPolicyInspection['entries'][0]['targetEntryExists'] ?? null) !== true
        || ($linkPolicyInspection['entries'][1]['linkType'] ?? null) !== 'symbolic-link'
        || ($linkPolicyInspection['entries'][1]['linkTargetSource'] ?? null) !== 'pax-linkpath'
        || ($linkPolicyInspection['entries'][1]['linkTarget'] ?? null) !== $expected['linkPolicySymlinkTarget']
        || ($linkPolicyInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-link-policy.tar'
        || $sparsePolicyInspection['format'] !== $expected['sparsePolicyFormat']
        || $sparsePolicyInspection['entryCount'] !== $expected['sparsePolicyEntryCount']
        || $sparsePolicyInspection['sparseEntryCount'] !== $expected['sparsePolicySparseCount']
        || $sparsePolicyInspection['extractionPolicy'] !== $expected['sparsePolicyExtractionPolicy']
        || !$sparsePolicyExtractionBlocked
        || ($sparsePolicyInspection['entries'][0]['name'] ?? null) !== $expected['sparsePolicyGnuTypeName']
        || ($sparsePolicyInspection['entries'][0]['sparseHeaderFamilies'] ?? []) !== ['gnu-typeflag']
        || ($sparsePolicyInspection['entries'][1]['name'] ?? null) !== $expected['sparsePolicySchilyName']
        || ($sparsePolicyInspection['entries'][1]['sparseHeaderFamilies'] ?? []) !== ['schily-pax']
        || ($sparsePolicyInspection['entries'][1]['realSize'] ?? null) !== $expected['sparsePolicyRealSize']
        || ($sparsePolicyInspection['entries'][1]['sparseMapSource'] ?? null) !== $expected['sparsePolicyMapSource']
        || ($sparsePolicyInspection['entries'][1]['sparseMapSegmentCount'] ?? null) !== $expected['sparsePolicyMapSegmentCount']
        || ($sparsePolicyInspection['entries'][1]['sparseMapPayloadBytes'] ?? null) !== $expected['sparsePolicyMapPayloadBytes']
        || ($sparsePolicyInspection['entries'][1]['sparseMapSegments'][1]['endOffset'] ?? null) !== $expected['sparsePolicyRealSize']
        || ($sparsePolicyInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-sparse-policy.tar'
        || !$sparseMalformedMapBlocked
        || $signedChecksumInspection['format'] !== $expected['signedChecksumFormat']
        || ($signedChecksumInspection['entryNames'][0] ?? null) !== $expected['signedChecksumName']
        || ($signedChecksumInspection['entryLayouts'][0]['modifiedAt'] ?? null) !== $expected['signedChecksumModifiedAt']
        || ($signedChecksumInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-signed-checksum.tar'
        || $signedChecksumInspection['archive']->read('/' . $expected['signedChecksumName']) !== $signedChecksumContentBytes
        || $charsetInspection['format'] !== $expected['charsetFormat']
        || ($charsetInspection['entryNames'][0] ?? null) !== $expected['charsetName']
        || ($charsetInspection['archive']->entry('/' . $expected['charsetName'])->globalPaxHeaders['hdrcharset'] ?? null) !== $expected['charsetGlobalHdrcharset']
        || ($charsetInspection['archive']->entry('/' . $expected['charsetName'])->localPaxHeaders['hdrcharset'] ?? null) !== $expected['charsetLocalHdrcharset']
        || ($charsetInspection['archive']->entry('/' . $expected['charsetName'])->paxHeaders['hdrcharset'] ?? null) !== $expected['charsetLocalHdrcharset']
        || ($charsetInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-pax-hdrcharset.tar'
        || $charsetInspection['archive']->read('/' . $expected['charsetName']) !== $charsetContentBytes
        || !$invalidCharsetBlocked
        || $nestedInspection['rootKind'] !== $expected['nestedRootKind']
        || $nestedInspection['rootFormat'] !== $expected['nestedRootFormat']
        || $nestedInspection['candidateCount'] !== $expected['nestedCandidateCount']
        || $nestedInspection['packageCount'] !== $expected['nestedPackageCount']
        || $nestedInspection['diagnosticCount'] !== $expected['nestedDiagnosticCount']
        || ($nestedInspection['entries'][0]['path'] ?? null) !== $expected['nestedFirstPath']
        || ($nestedInspection['entries'][0]['kind'] ?? null) !== ArchiveCompressionStream::PACKAGE_KIND_TAR
        || ($nestedInspection['entries'][0]['format'] ?? null) !== ArchiveCompressionStream::FORMAT_GZIP_TAR
        || ($nestedInspection['entries'][1]['path'] ?? null) !== $expected['nestedDeeperPath']
        || ($nestedInspection['entries'][1]['kind'] ?? null) !== ArchiveCompressionStream::PACKAGE_KIND_ZIP
        || ($nestedInspection['entries'][1]['format'] ?? null) !== ArchiveCompressionStream::FORMAT_ZIP
        || ($nestedInspection['entries'][2]['path'] ?? null) !== 'packet/nested/document.docx'
        || ($nestedInspection['entries'][2]['candidateReasons'] ?? []) !== ['extension:zip-package', 'signature:zip']
        || ($nestedInspection['entries'][3]['path'] ?? null) !== $expected['nestedBrokenPath']
        || ($nestedInspection['entries'][3]['status'] ?? null) !== 'unreadable'
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
echo 'linkPolicy.format=' . $linkPolicyInspection['format'] . "\n";
echo 'linkPolicy.extractionPolicy=' . $linkPolicyInspection['extractionPolicy'] . "\n";
echo 'linkPolicy.linkEntryCount=' . $linkPolicyInspection['linkEntryCount'] . "\n";
echo 'linkPolicy.hardTarget=' . $linkPolicyInspection['entries'][0]['linkTarget'] . "\n";
echo 'linkPolicy.symlinkTarget=' . $linkPolicyInspection['entries'][1]['linkTarget'] . "\n";
echo 'sparsePolicy.format=' . $sparsePolicyInspection['format'] . "\n";
echo 'sparsePolicy.extractionPolicy=' . $sparsePolicyInspection['extractionPolicy'] . "\n";
echo 'sparsePolicy.sparseEntryCount=' . $sparsePolicyInspection['sparseEntryCount'] . "\n";
echo 'sparsePolicy.gnuTypeName=' . $sparsePolicyInspection['entries'][0]['name'] . "\n";
echo 'sparsePolicy.schilyName=' . $sparsePolicyInspection['entries'][1]['name'] . "\n";
echo 'sparsePolicy.mapSource=' . $sparsePolicyInspection['entries'][1]['sparseMapSource'] . "\n";
echo 'sparsePolicy.mapSegmentCount=' . $sparsePolicyInspection['entries'][1]['sparseMapSegmentCount'] . "\n";
echo 'sparsePolicy.mapPayloadBytes=' . $sparsePolicyInspection['entries'][1]['sparseMapPayloadBytes'] . "\n";
echo 'sparsePolicy.malformedMapBlocked=' . ($sparseMalformedMapBlocked ? 'yes' : 'no') . "\n";
echo 'signedChecksum.format=' . $signedChecksumInspection['format'] . "\n";
echo 'signedChecksum.entry=' . $signedChecksumInspection['entryNames'][0] . "\n";
echo 'signedChecksum.modifiedAt=' . $signedChecksumInspection['entryLayouts'][0]['modifiedAt'] . "\n";
echo 'charset.format=' . $charsetInspection['format'] . "\n";
echo 'charset.entry=' . $charsetInspection['entryNames'][0] . "\n";
echo 'charset.globalHdrcharset=' . $charsetInspection['archive']->entry('/' . $charsetInspection['entryNames'][0])->globalPaxHeaders['hdrcharset'] . "\n";
echo 'charset.localHdrcharset=' . $charsetInspection['archive']->entry('/' . $charsetInspection['entryNames'][0])->localPaxHeaders['hdrcharset'] . "\n";
echo 'charset.invalidBlocked=' . ($invalidCharsetBlocked ? 'yes' : 'no') . "\n";
echo 'nested.rootKind=' . $nestedInspection['rootKind'] . "\n";
echo 'nested.rootFormat=' . $nestedInspection['rootFormat'] . "\n";
echo 'nested.candidateCount=' . $nestedInspection['candidateCount'] . "\n";
echo 'nested.packageCount=' . $nestedInspection['packageCount'] . "\n";
echo 'nested.diagnosticCount=' . $nestedInspection['diagnosticCount'] . "\n";
echo 'nested.paths=' . implode(',', array_map(static fn (array $entry): string => $entry['path'], $nestedInspection['entries'])) . "\n";
