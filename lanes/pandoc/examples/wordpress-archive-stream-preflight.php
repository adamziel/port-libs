<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\Lz4Frame;
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

$zlibPresetDictionaryStream = static function (string $dictionary, string $payload): string {
    $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['dictionary' => $dictionary]);
    if ($context === false) {
        throw new RuntimeException('Unable to initialize zlib preset-dictionary preflight fixture');
    }

    $encoded = deflate_add($context, $payload, ZLIB_FINISH);
    if ($encoded === false) {
        throw new RuntimeException('Unable to build zlib preset-dictionary preflight fixture');
    }

    return $encoded;
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

$tarOctalField = static fn (int $value): string => str_pad(decoct($value), 7, '0', STR_PAD_LEFT) . "\0";
$lz4HeaderChecksum = static fn (string $descriptor): string => chr((intval(hash('xxh32', $descriptor), 16) >> 8) & 0xff);
$lz4DictionaryMatchBlock = static function (string $dictionary, string $tail): string {
    $matchLength = strlen($dictionary);
    if ($matchLength < 19 || strlen($tail) > 14) {
        throw new RuntimeException('invalid LZ4 dictionary preflight fixture');
    }

    return chr(0x0f)
        . pack('v', $matchLength)
        . chr($matchLength - 19)
        . chr(strlen($tail) << 4)
        . $tail;
};
$lz4DictionaryCompressedFrame = static function (
    int $dictionaryId,
    string $decodedPayload,
    array $rawBlocks,
    bool $blockIndependent = true
) use ($lz4HeaderChecksum): string {
    $descriptor = chr(0x40 | ($blockIndependent ? 0x20 : 0x00) | 0x10 | 0x08 | 0x04 | 0x01)
        . chr(0x40)
        . pack('V2', strlen($decodedPayload), 0)
        . pack('V', $dictionaryId);
    $frame = pack('V', 0x184d2204)
        . $descriptor
        . $lz4HeaderChecksum($descriptor);

    foreach ($rawBlocks as $rawBlock) {
        if (!is_string($rawBlock)) {
            throw new RuntimeException('invalid LZ4 dictionary preflight block');
        }
        $frame .= pack('V', strlen($rawBlock))
            . $rawBlock
            . pack('V', intval(hash('xxh32', $rawBlock), 16));
    }

    return $frame
        . pack('V', 0)
        . pack('V', intval(hash('xxh32', $decodedPayload), 16));
};
$lz4DictionaryUncompressedFrame = static function (
    int $dictionaryId,
    string $decodedPayload,
    bool $blockIndependent = true
) use ($lz4HeaderChecksum): string {
    $descriptor = chr(0x40 | ($blockIndependent ? 0x20 : 0x00) | 0x08 | 0x04 | 0x01)
        . chr(0x40)
        . pack('V2', strlen($decodedPayload), 0)
        . pack('V', $dictionaryId);

    return pack('V', 0x184d2204)
        . $descriptor
        . $lz4HeaderChecksum($descriptor)
        . pack('V', 0x80000000 | strlen($decodedPayload))
        . $decodedPayload
        . pack('V', 0)
        . pack('V', intval(hash('xxh32', $decodedPayload), 16));
};
$zipDescriptorFixtureBytes = static function (array $entries, string $packageComment = '', array $eocd = []): string {
    $body = '';
    $centralDirectory = '';
    foreach ($entries as $entry) {
        $name = (string) $entry['name'];
        $data = (string) ($entry['data'] ?? '');
        $method = (int) ($entry['compressionMethod'] ?? 0);
        $flags = (int) ($entry['flags'] ?? 0);
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        if ($descriptor) {
            $flags |= 0x0008;
        }
        $diskStart = (int) ($entry['diskStart'] ?? 0);

        $payload = $method === 8 ? gzdeflate($data) : $data;
        $crc32 = (int) sprintf('%u', crc32($data));
        $localHeaderOffset = strlen($body);
        $localCrc32 = $descriptor ? 0 : $crc32;
        $localCompressedSize = $descriptor ? 0 : strlen($payload);
        $localUncompressedSize = $descriptor ? 0 : strlen($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $localCrc32,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            0
        ) . $name . $payload;

        if ($descriptor) {
            if ((bool) ($entry['descriptorSignature'] ?? true)) {
                $body .= "PK\x07\x08";
            }
            $body .= pack('VVV', $crc32, strlen($payload), strlen($data));
        }

        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc32,
            strlen($payload),
            strlen($data),
            strlen($name),
            0,
            0,
            $diskStart,
            0,
            0,
            $localHeaderOffset
        ) . $name;
    }

    return $body
        . $centralDirectory
        . pack(
            'VvvvvVVv',
            0x06054b50,
            (int) ($eocd['diskNumber'] ?? 0),
            (int) ($eocd['centralDirectoryDisk'] ?? 0),
            (int) ($eocd['diskEntryCount'] ?? count($entries)),
            (int) ($eocd['totalEntryCount'] ?? count($entries)),
            strlen($centralDirectory),
            strlen($body),
            strlen($packageComment)
        )
        . $packageComment;
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
$multiVolumePolicyTypePayload = 'gnu multi-volume payload fragment';
$multiVolumePolicyPaxPayload = 'pax multi-volume payload fragment';
$signedChecksumContentBytes = "# Signed checksum source packet\n\nReady for WordPress archive review.\n";
$charsetContentBytes = "# PAX charset source packet\n\nReady for WordPress archive charset review.\n";
$duplicatePaxContentBytes = "# Duplicate PAX source packet\n\nReady for WordPress archive duplicate-key review.\n";
$lz4DictionaryPayload = 'packet/word/document.xml needs an external LZ4 dictionary';
$zlibDictionaryManifestBytes = '{"source":"zlib-dictionary-inspection","target":"wordpress"}';
$zlibDictionaryContentBytes = "# ZLIB dictionary inspection\n\nReady for WordPress archive review.\n";
$lz4PackageManifestBytes = '{"source":"lz4-dictionary-inspection","target":"wordpress"}';
$lz4PackageContentBytes = "# LZ4 dictionary inspection\n\nReady for WordPress archive review.\n";
$lz4SplitPackageManifestBytes = '{"source":"lz4-dictionary-split","target":"wordpress"}';
$lz4SplitPackageContentBytes = "# Split LZ4 dictionary package\n\nReady for WordPress archive review.\n";
$nestedSourceBytes = "# Nested archive source\n\nReady for WordPress nested archive review.\n";
$nestedWordXml = '<w:document><w:body><w:p>Nested DOCX review packet</w:p></w:body></w:document>';
$descriptorDocumentXml = '<w:document><w:body><w:p>Descriptor-backed DOCX source</w:p></w:body></w:document>';
$descriptorFootnotesXml = '<w:footnotes><w:footnote w:id="1">Descriptor-backed note</w:footnote></w:footnotes>';
$splitZipMediaBytes = "split archive media placeholder\n";
$archiveBombContentBytes = str_repeat('A', 4096);

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
        'createdAt' => 1780479062,
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
$textHintPolicyGzip = GzipStream::build($archive->bytes(), [
    'filename' => 'wordpress-text-hint-review.tar',
    'comment' => 'claimed text but contains tar bytes',
    'textHint' => true,
]);
$textHintPolicyInspection = ArchiveCompressionStream::inspectGzipTextHintPolicy(
    $textHintPolicyGzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($archive->bytes())
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
$specialPolicyArchiveBytes = $rewriteTarHeaderFields(
    $rawTarHeader('packet/dev/console', '3', '', 1780479078, false),
    [
        329 => $tarOctalField(5),
        337 => $tarOctalField(1),
    ]
) . $rawTarHeader('PaxHeaders/block-device', 'x', $paxPayload([
    'path' => 'packet/dev/disk0',
    'devmajor' => '8',
    'devminor' => '16',
]), 0, false)
    . $rewriteTarHeaderFields(
        $rawTarHeader('placeholder-device', '4', '', 1780479079, false),
        [
            329 => $tarOctalField(0),
            337 => $tarOctalField(0),
        ]
    )
    . $rawTarHeader('packet/dev/import.fifo', '6', '', 1780479080, false)
    . str_repeat("\0", 1024);
$specialPolicyGzip = GzipStream::build($specialPolicyArchiveBytes, [
    'filename' => 'wordpress-special-file-policy.tar',
    'comment' => 'TAR special file extraction policy preflight',
]);
$specialPolicyInspection = ArchiveCompressionStream::inspectTarSpecialFilePolicy(
    $specialPolicyGzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($specialPolicyArchiveBytes)
);
$specialPolicyExtractionBlocked = false;
try {
    TarArchive::fromString($specialPolicyArchiveBytes);
} catch (RuntimeException) {
    $specialPolicyExtractionBlocked = true;
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
$multiVolumePolicyArchiveBytes = $rawTarHeader('PaxHeaders/volume-type', 'x', $paxPayload([
    'path' => 'packet/volume-fragment.md',
    'GNU.volume.filename' => 'packet/full-document.md',
    'GNU.volume.size' => '8192',
]), 0, false)
    . $rewriteTarHeaderFields(
        $rawTarHeader('placeholder-volume.md', 'M', $multiVolumePolicyTypePayload, 1780479082, false),
        [369 => str_pad(decoct(4096), 11, '0', STR_PAD_LEFT) . "\0"]
    )
    . $rawTarHeader('PaxHeaders/volume-pax', 'x', $paxPayload([
        'path' => 'packet/pax-volume-fragment.md',
        'GNU.volume.offset' => '2048',
        'GNU.volume.filename' => 'packet/pax-full-document.md',
        'GNU.volume.size' => '4096',
    ]), 0, false)
    . $rawTarHeader('placeholder-pax-volume.md', '0', $multiVolumePolicyPaxPayload, 1780479083, false)
    . str_repeat("\0", 1024);
$multiVolumePolicyGzip = GzipStream::build($multiVolumePolicyArchiveBytes, [
    'filename' => 'wordpress-multivolume-policy.tar',
    'comment' => 'TAR multi-volume extraction policy preflight',
]);
$multiVolumePolicyInspection = ArchiveCompressionStream::inspectTarMultiVolumePolicy(
    $multiVolumePolicyGzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($multiVolumePolicyArchiveBytes)
);
$multiVolumePolicyExtractionBlocked = false;
try {
    TarArchive::fromString($multiVolumePolicyArchiveBytes);
} catch (RuntimeException) {
    $multiVolumePolicyExtractionBlocked = true;
}
$multiVolumeMalformedOffsetBlocked = false;
try {
    TarArchive::multiVolumePolicyPreflight(
        $rawTarHeader('PaxHeaders/malformed-volume', 'x', $paxPayload([
            'path' => 'packet/malformed-volume.md',
            'GNU.volume.offset' => 'not-a-number',
        ]), 0, false)
        . $rawTarHeader('placeholder-volume.md', '0', 'volume payload fragment', 0, false)
        . str_repeat("\0", 1024)
    );
} catch (RuntimeException) {
    $multiVolumeMalformedOffsetBlocked = true;
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
$duplicatePaxArchiveBytes = $rawTarHeader('PaxHeaders/duplicate-review', 'x', $paxPayload([
    'path' => 'packet/duplicate-pax.md',
    'org.wordpress.import.review' => 'first review state',
]) . $paxPayload([
    'org.wordpress.import.review' => 'second review state',
    'comment' => 'duplicate review metadata',
]), 0, false)
    . $rawTarHeader('packet/duplicate-pax.md', '0', $duplicatePaxContentBytes, 1780479085, false)
    . str_repeat("\0", 1024);
$duplicatePaxGzip = GzipStream::build($duplicatePaxArchiveBytes, [
    'filename' => 'wordpress-duplicate-pax.tar',
    'comment' => 'TAR duplicate PAX keyword preflight',
]);
$duplicatePaxInspection = ArchiveCompressionStream::inspectTarPaxDuplicateKeywordPolicy(
    $duplicatePaxGzip,
    ArchiveCompressionStream::FORMAT_GZIP_TAR,
    strlen($duplicatePaxArchiveBytes)
);
$duplicatePaxExtractionBlocked = false;
try {
    TarArchive::fromString($duplicatePaxArchiveBytes);
} catch (RuntimeException) {
    $duplicatePaxExtractionBlocked = true;
}
$descriptorZipBytes = $zipDescriptorFixtureBytes([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/document.xml',
        'data' => $descriptorDocumentXml,
        'compressionMethod' => 8,
        'descriptor' => true,
    ],
    [
        'name' => 'word/footnotes.xml',
        'data' => $descriptorFootnotesXml,
        'compressionMethod' => 0,
        'descriptor' => true,
        'descriptorSignature' => false,
    ],
], 'zip descriptor review fixture');
$descriptorZipGzip = GzipStream::build($descriptorZipBytes, [
    'filename' => 'wordpress-descriptor-package.zip',
    'comment' => 'ZIP data descriptor preflight fixture',
    'headerCrc' => true,
]);
$descriptorZipInspection = ArchiveCompressionStream::inspectZipDataDescriptorPolicy(
    $descriptorZipGzip,
    ArchiveCompressionStream::FORMAT_GZIP_ZIP,
    strlen($descriptorZipBytes)
);
$splitZipBytes = $zipDescriptorFixtureBytes([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>Split ZIP source</w:p></w:body></w:document>',
        'compressionMethod' => 8,
    ],
    [
        'name' => 'word/media/split.png',
        'data' => $splitZipMediaBytes,
        'compressionMethod' => 0,
        'diskStart' => 2,
    ],
], 'split zip review fixture', [
    'diskNumber' => 1,
    'centralDirectoryDisk' => 1,
    'diskEntryCount' => 2,
    'totalEntryCount' => 3,
]);
$splitZipGzip = GzipStream::build($splitZipBytes, [
    'filename' => 'wordpress-split-package.zip',
    'comment' => 'ZIP split archive policy fixture',
    'headerCrc' => true,
]);
$splitZipInspection = ArchiveCompressionStream::inspectZipSplitArchivePolicy(
    $splitZipGzip,
    ArchiveCompressionStream::FORMAT_GZIP_ZIP,
    strlen($splitZipBytes)
);
$splitZipExtractionBlocked = false;
try {
    ZipPackage::fromString($splitZipBytes);
} catch (RuntimeException) {
    $splitZipExtractionBlocked = true;
}
$lz4DictionaryId = 0x1a2b3c4d;
$lz4DictionaryDescriptor = chr(0x40 | 0x20 | 0x08 | 0x04 | 0x01)
    . chr(0x40)
    . pack('V2', strlen($lz4DictionaryPayload), 0)
    . pack('V', $lz4DictionaryId);
$lz4DictionaryFrame = pack('V', 0x184d2204)
    . $lz4DictionaryDescriptor
    . $lz4HeaderChecksum($lz4DictionaryDescriptor)
    . pack('V', 0x80000000 | strlen($lz4DictionaryPayload))
    . $lz4DictionaryPayload
    . pack('V', 0)
    . pack('V', intval(hash('xxh32', $lz4DictionaryPayload), 16));
$lz4DictionaryStream = Lz4Frame::skippableFrame('dictionary-id:0x1a2b3c4d', 11) . $lz4DictionaryFrame;
$lz4DictionaryInspection = ArchiveCompressionStream::inspectLz4DictionaryPolicy($lz4DictionaryStream);
$lz4DictionaryExtractionBlocked = false;
try {
    Lz4Frame::decode($lz4DictionaryStream);
} catch (RuntimeException) {
    $lz4DictionaryExtractionBlocked = true;
}
$lz4SuppliedDictionary = 'packet/word/document.xml:';
$lz4SuppliedDecodedPayload = $lz4SuppliedDictionary . 'wp' . $lz4SuppliedDictionary . 'ok';
$lz4SuppliedDictionaryStream = Lz4Frame::skippableFrame('dictionary-id:0x1a2b3c4d', 12)
    . $lz4DictionaryCompressedFrame($lz4DictionaryId, $lz4SuppliedDecodedPayload, [
        $lz4DictionaryMatchBlock($lz4SuppliedDictionary, 'wp'),
        $lz4DictionaryMatchBlock($lz4SuppliedDictionary, 'ok'),
    ]);
$lz4SuppliedDecodedPayloadActual = Lz4Frame::decodeWithDictionaries($lz4SuppliedDictionaryStream, [
    $lz4DictionaryId => $lz4SuppliedDictionary,
]);
$lz4SuppliedFrames = Lz4Frame::framesWithDictionaries($lz4SuppliedDictionaryStream, [
    $lz4DictionaryId => $lz4SuppliedDictionary,
]);
$lz4SuppliedMissingDictionaryBlocked = false;
try {
    Lz4Frame::decodeWithDictionaries($lz4SuppliedDictionaryStream, []);
} catch (RuntimeException) {
    $lz4SuppliedMissingDictionaryBlocked = true;
}
$zlibDictionaryArchiveBytes = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => $zlibDictionaryManifestBytes,
    ],
    [
        'name' => 'packet/content.md',
        'data' => $zlibDictionaryContentBytes,
        'modifiedAt' => 1780479092,
    ],
])->bytes();
$zlibDictionary = 'packet/content.md:wordpress-zlib-dictionary';
$zlibDictionaryId = intval(hash('adler32', $zlibDictionary), 16);
$zlibDictionaryStream = $zlibPresetDictionaryStream($zlibDictionary, $zlibDictionaryArchiveBytes);
$zlibDictionaryInspection = ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
    $zlibDictionaryStream,
    ArchiveCompressionStream::FORMAT_ZLIB_TAR,
    [$zlibDictionaryId => $zlibDictionary],
    strlen($zlibDictionaryArchiveBytes),
    strlen($zlibDictionaryManifestBytes) + strlen($zlibDictionaryContentBytes)
);
$zlibDictionaryMissingBlocked = false;
try {
    ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
        $zlibDictionaryStream,
        ArchiveCompressionStream::FORMAT_ZLIB_TAR,
        [],
        strlen($zlibDictionaryArchiveBytes)
    );
} catch (RuntimeException) {
    $zlibDictionaryMissingBlocked = true;
}
$lz4PackageArchiveBytes = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => $lz4PackageManifestBytes,
    ],
    [
        'name' => 'packet/content.md',
        'data' => $lz4PackageContentBytes,
        'modifiedAt' => 1780479093,
    ],
])->bytes();
$lz4PackageDictionaryId = 0x0a0b0c0d;
$lz4PackageDictionary = 'packet/content.md:wordpress-lz4-package-dictionary';
$lz4PackageStream = Lz4Frame::skippableFrame('dictionary-id:0x0a0b0c0d', 13)
    . $lz4DictionaryUncompressedFrame($lz4PackageDictionaryId, $lz4PackageArchiveBytes);
$lz4PackageInspection = ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
    $lz4PackageStream,
    ArchiveCompressionStream::FORMAT_LZ4_TAR,
    [$lz4PackageDictionaryId => $lz4PackageDictionary],
    strlen($lz4PackageArchiveBytes),
    strlen($lz4PackageManifestBytes) + strlen($lz4PackageContentBytes)
);
$lz4PackageMissingDictionaryBlocked = false;
try {
    ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
        $lz4PackageStream,
        ArchiveCompressionStream::FORMAT_LZ4_TAR,
        [],
        strlen($lz4PackageArchiveBytes)
    );
} catch (RuntimeException) {
    $lz4PackageMissingDictionaryBlocked = true;
}
$lz4SplitPackageArchiveBytes = TarArchive::fromEntries([
    [
        'name' => 'packet/manifest.json',
        'data' => $lz4SplitPackageManifestBytes,
    ],
    [
        'name' => 'packet/content.md',
        'data' => $lz4SplitPackageContentBytes,
        'modifiedAt' => 1780479094,
    ],
])->bytes();
$lz4SplitOffset = 1536;
$lz4SplitFirstPayload = substr($lz4SplitPackageArchiveBytes, 0, $lz4SplitOffset);
$lz4SplitSecondPayload = substr($lz4SplitPackageArchiveBytes, $lz4SplitOffset);
$lz4SplitFirstDictionaryId = 0x10111213;
$lz4SplitSecondDictionaryId = 0x20212223;
$lz4SplitFirstDictionary = 'packet/content.md:first-split-lz4-dictionary';
$lz4SplitSecondDictionary = 'packet/content.md:second-split-lz4-dictionary';
$lz4SplitSkippable = Lz4Frame::skippableFrame('split-lz4-dictionary-tar:2', 14);
$lz4SplitFirstFrame = $lz4DictionaryUncompressedFrame($lz4SplitFirstDictionaryId, $lz4SplitFirstPayload);
$lz4SplitSecondFrame = $lz4DictionaryUncompressedFrame($lz4SplitSecondDictionaryId, $lz4SplitSecondPayload);
$lz4SplitPackageStream = $lz4SplitSkippable . $lz4SplitFirstFrame . $lz4SplitSecondFrame;
$lz4SplitPackageInspection = ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
    $lz4SplitPackageStream,
    ArchiveCompressionStream::FORMAT_LZ4_TAR,
    [
        $lz4SplitFirstDictionaryId => $lz4SplitFirstDictionary,
        $lz4SplitSecondDictionaryId => $lz4SplitSecondDictionary,
    ],
    strlen($lz4SplitPackageArchiveBytes),
    strlen($lz4SplitPackageManifestBytes) + strlen($lz4SplitPackageContentBytes)
);
$lz4SplitPackageMissingDictionaryBlocked = false;
try {
    ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
        $lz4SplitPackageStream,
        ArchiveCompressionStream::FORMAT_LZ4_TAR,
        [$lz4SplitFirstDictionaryId => $lz4SplitFirstDictionary],
        strlen($lz4SplitPackageArchiveBytes)
    );
} catch (RuntimeException) {
    $lz4SplitPackageMissingDictionaryBlocked = true;
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
$archiveBombBytes = TarArchive::fromEntries([
    [
        'name' => 'packet/content.md',
        'data' => $archiveBombContentBytes,
    ],
])->bytes();
$archiveBombGzip = GzipStream::build($archiveBombBytes, [
    'filename' => 'wordpress-compressed-review.tar',
    'comment' => 'bounded expansion-ratio preflight',
    'compressionLevel' => 9,
]);
$archiveBombInspection = ArchiveCompressionStream::inspectArchiveBombPolicyAuto(
    $archiveBombGzip,
    strlen($archiveBombBytes),
    strlen($archiveBombContentBytes),
    4.0,
    4.0,
    4.0
);
$unsupportedBzip2Upload = 'BZh9' . 'compressed tar payload bytes stay opaque to WordPress preflight';
$unsupportedXzUpload = "\xfd" . '7zXZ' . "\0" . "\0\x04" . "\0\0\0\0"
    . 'compressed zip payload bytes stay opaque to WordPress preflight';
$unsupportedBzip2Inspection = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
    $unsupportedBzip2Upload,
    'wordpress-review-packet.tar.bz2'
);
$unsupportedXzInspection = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
    $unsupportedXzUpload,
    'wordpress-documents.zip.xz'
);
$sourceNamePolicyInspection = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
    $gzip,
    'wordpress-review-packet.docx',
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
        'gzipMemberOffset' => 0,
        'gzipTextHintFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'gzipTextHintPolicy' => 'review-before-conversion',
        'gzipTextHintExtractionPolicy' => 'metadata-only-no-extraction',
        'gzipTextHintBinaryCount' => 1,
        'gzipTextHintFilename' => 'wordpress-text-hint-review.tar',
        'gzipTextHintDiagnostics' => ['gzip-text-hint-binary-payload'],
        'content' => $contentBytes,
        'contentCreatedAt' => 1780479062,
        'contentSourceType' => 'gzip-member',
        'contentSourceLabel' => 'wordpress-archive-stream.tar',
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
        'specialPolicyFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'specialPolicyEntryCount' => 3,
        'specialPolicySpecialCount' => 3,
        'specialPolicyExtractionPolicy' => 'special-file-entries-blocked',
        'specialPolicyCharacterMajor' => 5,
        'specialPolicyBlockMinor' => 16,
        'specialPolicyFifoSource' => 'none',
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
        'multiVolumePolicyFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'multiVolumePolicyEntryCount' => 2,
        'multiVolumePolicyCount' => 2,
        'multiVolumePolicyTypeCount' => 1,
        'multiVolumePolicyPaxCount' => 2,
        'multiVolumePolicyExtractionPolicy' => 'multi-volume-entries-blocked',
        'multiVolumePolicyTypeName' => 'packet/volume-fragment.md',
        'multiVolumePolicyPaxName' => 'packet/pax-volume-fragment.md',
        'multiVolumePolicyTypeOffset' => 4096,
        'multiVolumePolicyPaxOffset' => 2048,
        'multiVolumePolicyTypeOffsetSource' => 'oldgnu-offset-field',
        'multiVolumePolicyPaxOffsetSource' => 'pax-gnu-volume-offset',
        'multiVolumePolicyOriginalName' => 'packet/full-document.md',
        'multiVolumePolicyDeclaredSize' => 8192,
        'signedChecksumFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'signedChecksumName' => "packet/signed-\u{2603}-checksum.md",
        'signedChecksumModifiedAt' => 1780479083,
        'charsetFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'charsetName' => "packet/charset-\u{2603}.md",
        'charsetGlobalHdrcharset' => 'ISO-IR 10646 2000 UTF-8',
        'charsetLocalHdrcharset' => 'BINARY',
        'duplicatePaxFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'duplicatePaxExtractionPolicy' => 'duplicate-pax-keywords-blocked',
        'duplicatePaxEntryCount' => 1,
        'duplicatePaxKeyword' => 'org.wordpress.import.review',
        'duplicatePaxValues' => ['first review state', 'second review state'],
        'zipDescriptorFormat' => ArchiveCompressionStream::FORMAT_GZIP_ZIP,
        'zipDescriptorEntryCount' => 3,
        'zipDescriptorDescriptorCount' => 2,
        'zipDescriptorSignedCount' => 1,
        'zipDescriptorUnsignedCount' => 1,
        'zipDescriptorNames' => ['word/document.xml', 'word/footnotes.xml'],
        'zipDescriptorSignatures' => [true, false],
        'zipDescriptorLengths' => [16, 12],
        'zipDescriptorGzipFilename' => 'wordpress-descriptor-package.zip',
        'zipSplitFormat' => ArchiveCompressionStream::FORMAT_GZIP_ZIP,
        'zipSplitEntryCount' => 3,
        'zipSplitDiskNumber' => 1,
        'zipSplitCentralDirectoryDisk' => 1,
        'zipSplitDiskEntryCount' => 2,
        'zipSplitTotalEntryCount' => 3,
        'zipSplitIssues' => ['split-archive-eocd', 'split-entry-disk-start'],
        'zipSplitEntryNames' => ['[Content_Types].xml', 'word/document.xml', 'word/media/split.png'],
        'zipSplitEntryDisks' => [0, 0, 2],
        'zipSplitGzipFilename' => 'wordpress-split-package.zip',
        'lz4DictionaryFormat' => 'lz4',
        'lz4DictionaryPolicyType' => 'lz4-dictionary-policy',
        'lz4DictionaryExtractionPolicy' => 'dictionary-frames-blocked',
        'lz4DictionaryFrameCount' => 2,
        'lz4DictionaryId' => $lz4DictionaryId,
        'lz4DictionaryBlockCount' => 1,
        'lz4DictionaryPayloadSize' => strlen($lz4DictionaryPayload),
        'lz4SuppliedDecodedPayload' => $lz4SuppliedDecodedPayload,
        'lz4SuppliedFrameCount' => 2,
        'lz4SuppliedBlockCount' => 2,
        'zlibDictionaryKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'zlibDictionaryFormat' => ArchiveCompressionStream::FORMAT_ZLIB_TAR,
        'zlibDictionaryEntryCount' => 2,
        'zlibDictionaryId' => $zlibDictionaryId,
        'zlibDictionarySize' => strlen($zlibDictionary),
        'zlibDictionaryContent' => $zlibDictionaryContentBytes,
        'lz4PackageKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'lz4PackageFormat' => ArchiveCompressionStream::FORMAT_LZ4_TAR,
        'lz4PackageEntryCount' => 2,
        'lz4PackageFrameCount' => 2,
        'lz4PackageDictionaryId' => $lz4PackageDictionaryId,
        'lz4PackageDictionarySize' => strlen($lz4PackageDictionary),
        'lz4PackageContent' => $lz4PackageContentBytes,
        'lz4SplitPackageKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'lz4SplitPackageFormat' => ArchiveCompressionStream::FORMAT_LZ4_TAR,
        'lz4SplitPackageEntryCount' => 2,
        'lz4SplitPackageFrameCount' => 3,
        'lz4SplitPackageDataFrameCount' => 2,
        'lz4SplitPackageDictionaryFrameCount' => 2,
        'lz4SplitPackageFirstDictionaryId' => $lz4SplitFirstDictionaryId,
        'lz4SplitPackageSecondDictionaryId' => $lz4SplitSecondDictionaryId,
        'lz4SplitPackageSplitOffset' => $lz4SplitOffset,
        'lz4SplitPackageEntrySourceTypes' => ['lz4-frame', 'lz4-frame'],
        'lz4SplitPackageEntrySourceOffsets' => [1024, $lz4SplitOffset],
        'lz4SplitPackageEntrySourceEndOffsets' => [$lz4SplitOffset, 2048],
        'lz4SplitPackageContent' => $lz4SplitPackageContentBytes,
        'nestedRootKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'nestedRootFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'nestedCandidateCount' => 4,
        'nestedPackageCount' => 3,
        'nestedDiagnosticCount' => 1,
        'nestedFirstPath' => 'packet/nested/review.tar.gz',
        'nestedDeeperPath' => 'packet/nested/review.tar.gz!packet/deeper/document.docx',
        'nestedBrokenPath' => 'packet/nested/broken.zip',
        'archiveBombKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'archiveBombFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'archiveBombPolicy' => 'review-before-conversion',
        'archiveBombDiagnostics' => [
            'archive-stream-compression-ratio-exceeds-threshold',
            'archive-total-expansion-ratio-exceeds-threshold',
        ],
        'archiveBombFilename' => 'wordpress-compressed-review.tar',
        'archiveBombContentSize' => strlen($archiveBombContentBytes),
        'unsupportedBzip2Format' => 'bzip2',
        'unsupportedBzip2Kind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'unsupportedBzip2CandidateFormat' => 'bzip2-tar',
        'unsupportedBzip2BlockSize' => 9,
        'unsupportedXzFormat' => 'xz',
        'unsupportedXzKind' => ArchiveCompressionStream::PACKAGE_KIND_ZIP,
        'unsupportedXzCandidateFormat' => 'xz-zip',
        'unsupportedXzFlags' => '0004',
        'unsupportedPolicy' => 'unsupported-compression-stream-blocked',
        'sourceNamePolicyExpectedKind' => ArchiveCompressionStream::PACKAGE_KIND_ZIP,
        'sourceNamePolicyExpectedFormat' => ArchiveCompressionStream::FORMAT_ZIP,
        'sourceNamePolicyDetectedKind' => ArchiveCompressionStream::PACKAGE_KIND_TAR,
        'sourceNamePolicyDetectedFormat' => ArchiveCompressionStream::FORMAT_GZIP_TAR,
        'sourceNamePolicyReason' => 'extension:zip-package',
        'sourceNamePolicyDiagnostics' => [
            'archive-source-name-package-kind-mismatch',
            'archive-source-name-compression-format-mismatch',
        ],
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
        || $textHintPolicyInspection['format'] !== $expected['gzipTextHintFormat']
        || $textHintPolicyInspection['handoffPolicy'] !== $expected['gzipTextHintPolicy']
        || $textHintPolicyInspection['extractionPolicy'] !== $expected['gzipTextHintExtractionPolicy']
        || $textHintPolicyInspection['binaryTextHintMemberCount'] !== $expected['gzipTextHintBinaryCount']
        || $textHintPolicyInspection['diagnostics'] !== $expected['gzipTextHintDiagnostics']
        || ($textHintPolicyInspection['members'][0]['filename'] ?? null) !== $expected['gzipTextHintFilename']
        || ($textHintPolicyInspection['members'][0]['payloadLooksBinary'] ?? false) !== true
        || ($textHintPolicyInspection['members'][0]['policy'] ?? null) !== 'review'
        || ($textHintPolicyInspection['members'][0]['diagnostics'][0] ?? null) !== 'gzip-text-hint-binary-payload'
        || isset($textHintPolicyInspection['members'][0]['data'])
        || $inspection['archive']->read('/packet/content.md') !== $expected['content']
        || ($inspection['entryLayouts'][2]['paxHeaderKeys'] ?? []) !== ['LIBARCHIVE.creationtime', 'atime', 'ctime']
        || ($inspection['entryLayouts'][2]['createdAt'] ?? null) !== $expected['contentCreatedAt']
        || ($inspection['entryLayouts'][2]['decodedSourceSegmentCount'] ?? null) !== 1
        || ($inspection['entryLayouts'][2]['decodedSourceSegments'][0]['sourceType'] ?? null) !== $expected['contentSourceType']
        || ($inspection['entryLayouts'][2]['decodedSourceSegments'][0]['sourceLabel'] ?? null) !== $expected['contentSourceLabel']
        || ($inspection['archive']->entry('/packet/content.md')->paxHeaders['LIBARCHIVE.creationtime'] ?? null) !== (string) $expected['contentCreatedAt']
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
        || $specialPolicyInspection['format'] !== $expected['specialPolicyFormat']
        || $specialPolicyInspection['entryCount'] !== $expected['specialPolicyEntryCount']
        || $specialPolicyInspection['specialFileEntryCount'] !== $expected['specialPolicySpecialCount']
        || $specialPolicyInspection['extractionPolicy'] !== $expected['specialPolicyExtractionPolicy']
        || !$specialPolicyExtractionBlocked
        || ($specialPolicyInspection['entries'][0]['specialType'] ?? null) !== 'character-device'
        || ($specialPolicyInspection['entries'][0]['deviceMajor'] ?? null) !== $expected['specialPolicyCharacterMajor']
        || ($specialPolicyInspection['entries'][1]['name'] ?? null) !== 'packet/dev/disk0'
        || ($specialPolicyInspection['entries'][1]['deviceMinor'] ?? null) !== $expected['specialPolicyBlockMinor']
        || ($specialPolicyInspection['entries'][2]['specialType'] ?? null) !== 'fifo'
        || ($specialPolicyInspection['entries'][2]['deviceNumberSource'] ?? null) !== $expected['specialPolicyFifoSource']
        || ($specialPolicyInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-special-file-policy.tar'
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
        || $multiVolumePolicyInspection['format'] !== $expected['multiVolumePolicyFormat']
        || $multiVolumePolicyInspection['entryCount'] !== $expected['multiVolumePolicyEntryCount']
        || $multiVolumePolicyInspection['multiVolumeEntryCount'] !== $expected['multiVolumePolicyCount']
        || $multiVolumePolicyInspection['typeflagEntryCount'] !== $expected['multiVolumePolicyTypeCount']
        || $multiVolumePolicyInspection['paxMetadataEntryCount'] !== $expected['multiVolumePolicyPaxCount']
        || $multiVolumePolicyInspection['extractionPolicy'] !== $expected['multiVolumePolicyExtractionPolicy']
        || !$multiVolumePolicyExtractionBlocked
        || ($multiVolumePolicyInspection['entries'][0]['name'] ?? null) !== $expected['multiVolumePolicyTypeName']
        || ($multiVolumePolicyInspection['entries'][0]['volumeHeaderFamilies'] ?? []) !== ['gnu-typeflag', 'gnu-pax']
        || ($multiVolumePolicyInspection['entries'][0]['continuationOffset'] ?? null) !== $expected['multiVolumePolicyTypeOffset']
        || ($multiVolumePolicyInspection['entries'][0]['continuationOffsetSource'] ?? null) !== $expected['multiVolumePolicyTypeOffsetSource']
        || ($multiVolumePolicyInspection['entries'][0]['originalName'] ?? null) !== $expected['multiVolumePolicyOriginalName']
        || ($multiVolumePolicyInspection['entries'][0]['declaredVolumeSize'] ?? null) !== $expected['multiVolumePolicyDeclaredSize']
        || ($multiVolumePolicyInspection['entries'][1]['name'] ?? null) !== $expected['multiVolumePolicyPaxName']
        || ($multiVolumePolicyInspection['entries'][1]['continuationOffset'] ?? null) !== $expected['multiVolumePolicyPaxOffset']
        || ($multiVolumePolicyInspection['entries'][1]['continuationOffsetSource'] ?? null) !== $expected['multiVolumePolicyPaxOffsetSource']
        || ($multiVolumePolicyInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-multivolume-policy.tar'
        || !$multiVolumeMalformedOffsetBlocked
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
        || $duplicatePaxInspection['format'] !== $expected['duplicatePaxFormat']
        || $duplicatePaxInspection['extractionPolicy'] !== $expected['duplicatePaxExtractionPolicy']
        || $duplicatePaxInspection['duplicatePaxEntryCount'] !== $expected['duplicatePaxEntryCount']
        || !$duplicatePaxExtractionBlocked
        || ($duplicatePaxInspection['entries'][0]['duplicateKeywords'][0] ?? null) !== $expected['duplicatePaxKeyword']
        || ($duplicatePaxInspection['entries'][0]['duplicateRecords'][0]['values'] ?? []) !== $expected['duplicatePaxValues']
        || ($duplicatePaxInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-duplicate-pax.tar'
        || $descriptorZipInspection['format'] !== $expected['zipDescriptorFormat']
        || $descriptorZipInspection['entryCount'] !== $expected['zipDescriptorEntryCount']
        || $descriptorZipInspection['descriptorEntryCount'] !== $expected['zipDescriptorDescriptorCount']
        || $descriptorZipInspection['signedDescriptorEntryCount'] !== $expected['zipDescriptorSignedCount']
        || $descriptorZipInspection['unsignedDescriptorEntryCount'] !== $expected['zipDescriptorUnsignedCount']
        || array_column($descriptorZipInspection['descriptorEntries'], 'name') !== $expected['zipDescriptorNames']
        || array_column($descriptorZipInspection['descriptorEntries'], 'hasSignature') !== $expected['zipDescriptorSignatures']
        || array_column($descriptorZipInspection['descriptorEntries'], 'descriptorLength') !== $expected['zipDescriptorLengths']
        || array_column($descriptorZipInspection['descriptorEntries'], 'hasZeroLocalHeaderPlaceholders') !== [true, true]
        || ($descriptorZipInspection['descriptorEntries'][0]['valueOffset'] ?? null) !== (($descriptorZipInspection['descriptorEntries'][0]['descriptorOffset'] ?? 0) + 4)
        || ($descriptorZipInspection['descriptorEntries'][1]['valueOffset'] ?? null) !== ($descriptorZipInspection['descriptorEntries'][1]['descriptorOffset'] ?? null)
        || ($descriptorZipInspection['stream']['members'][0]['filename'] ?? null) !== $expected['zipDescriptorGzipFilename']
        || $descriptorZipInspection['zipBytes'] !== $descriptorZipBytes
        || $splitZipInspection['format'] !== $expected['zipSplitFormat']
        || $splitZipInspection['zipBytes'] !== $splitZipBytes
        || $splitZipInspection['packageByteSize'] !== strlen($splitZipBytes)
        || $splitZipInspection['entryCount'] !== $expected['zipSplitEntryCount']
        || $splitZipInspection['diskNumber'] !== $expected['zipSplitDiskNumber']
        || $splitZipInspection['centralDirectoryDisk'] !== $expected['zipSplitCentralDirectoryDisk']
        || $splitZipInspection['diskEntryCount'] !== $expected['zipSplitDiskEntryCount']
        || $splitZipInspection['totalEntryCount'] !== $expected['zipSplitTotalEntryCount']
        || $splitZipInspection['isSingleDisk'] !== false
        || $splitZipInspection['hasSplitArchiveMarkers'] !== true
        || $splitZipInspection['isSupportedByBoundedReader'] !== false
        || $splitZipInspection['issues'] !== $expected['zipSplitIssues']
        || $splitZipInspection['splitArchiveEntryCount'] !== 1
        || ($splitZipInspection['splitArchiveEntries'][0]['name'] ?? null) !== 'word/media/split.png'
        || ($splitZipInspection['splitArchiveEntries'][0]['diskStart'] ?? null) !== 2
        || array_column($splitZipInspection['entries'], 'name') !== $expected['zipSplitEntryNames']
        || array_column($splitZipInspection['entries'], 'diskStart') !== $expected['zipSplitEntryDisks']
        || ($splitZipInspection['stream']['members'][0]['filename'] ?? null) !== $expected['zipSplitGzipFilename']
        || !$splitZipExtractionBlocked
        || $lz4DictionaryInspection['format'] !== $expected['lz4DictionaryFormat']
        || $lz4DictionaryInspection['type'] !== $expected['lz4DictionaryPolicyType']
        || $lz4DictionaryInspection['extractionPolicy'] !== $expected['lz4DictionaryExtractionPolicy']
        || $lz4DictionaryInspection['frameCount'] !== $expected['lz4DictionaryFrameCount']
        || $lz4DictionaryInspection['dictionaryFrameCount'] !== 1
        || !$lz4DictionaryExtractionBlocked
        || ($lz4DictionaryInspection['stream']['frames'][0]['data'] ?? null) !== 'dictionary-id:0x1a2b3c4d'
        || ($lz4DictionaryInspection['stream']['frames'][1]['dictionaryId'] ?? null) !== $expected['lz4DictionaryId']
        || ($lz4DictionaryInspection['stream']['frames'][1]['blockCount'] ?? null) !== $expected['lz4DictionaryBlockCount']
        || ($lz4DictionaryInspection['stream']['frames'][1]['contentSize'] ?? null) !== $expected['lz4DictionaryPayloadSize']
        || ($lz4DictionaryInspection['stream']['frames'][1]['policy'] ?? null) !== 'blocked'
        || ($lz4DictionaryInspection['stream']['frames'][1]['diagnostics'] ?? []) !== ['lz4-dictionary-frame-not-decoded', 'lz4-external-dictionary-required']
        || $lz4SuppliedDecodedPayloadActual !== $expected['lz4SuppliedDecodedPayload']
        || count($lz4SuppliedFrames) !== $expected['lz4SuppliedFrameCount']
        || !$lz4SuppliedMissingDictionaryBlocked
        || ($lz4SuppliedFrames[1]['dictionaryId'] ?? null) !== $expected['lz4DictionaryId']
        || ($lz4SuppliedFrames[1]['blockCount'] ?? null) !== $expected['lz4SuppliedBlockCount']
        || ($lz4SuppliedFrames[1]['blockTypes'] ?? []) !== ['compressed', 'compressed']
        || $zlibDictionaryInspection['kind'] !== $expected['zlibDictionaryKind']
        || $zlibDictionaryInspection['format'] !== $expected['zlibDictionaryFormat']
        || $zlibDictionaryInspection['entryCount'] !== $expected['zlibDictionaryEntryCount']
        || ($zlibDictionaryInspection['stream']['type'] ?? null) !== 'zlib-deflate'
        || ($zlibDictionaryInspection['stream']['hasPresetDictionary'] ?? null) !== true
        || ($zlibDictionaryInspection['stream']['dictionarySupplied'] ?? null) !== true
        || ($zlibDictionaryInspection['stream']['presetDictionaryId'] ?? null) !== $expected['zlibDictionaryId']
        || ($zlibDictionaryInspection['stream']['dictionarySize'] ?? null) !== $expected['zlibDictionarySize']
        || $zlibDictionaryInspection['archive']->read('/packet/content.md') !== $expected['zlibDictionaryContent']
        || ($zlibDictionaryInspection['entryLayouts'][1]['modifiedAt'] ?? null) !== 1780479092
        || !$zlibDictionaryMissingBlocked
        || $lz4PackageInspection['kind'] !== $expected['lz4PackageKind']
        || $lz4PackageInspection['format'] !== $expected['lz4PackageFormat']
        || $lz4PackageInspection['entryCount'] !== $expected['lz4PackageEntryCount']
        || ($lz4PackageInspection['stream']['type'] ?? null) !== 'lz4'
        || ($lz4PackageInspection['stream']['frameCount'] ?? null) !== $expected['lz4PackageFrameCount']
        || ($lz4PackageInspection['stream']['dictionaryFrameCount'] ?? null) !== 1
        || ($lz4PackageInspection['stream']['frames'][1]['dictionaryId'] ?? null) !== $expected['lz4PackageDictionaryId']
        || ($lz4PackageInspection['stream']['frames'][1]['dictionarySupplied'] ?? null) !== true
        || ($lz4PackageInspection['stream']['frames'][1]['dictionarySize'] ?? null) !== $expected['lz4PackageDictionarySize']
        || $lz4PackageInspection['archive']->read('/packet/content.md') !== $expected['lz4PackageContent']
        || ($lz4PackageInspection['entryLayouts'][1]['modifiedAt'] ?? null) !== 1780479093
        || !$lz4PackageMissingDictionaryBlocked
        || $lz4SplitPackageInspection['kind'] !== $expected['lz4SplitPackageKind']
        || $lz4SplitPackageInspection['format'] !== $expected['lz4SplitPackageFormat']
        || $lz4SplitPackageInspection['entryCount'] !== $expected['lz4SplitPackageEntryCount']
        || ($lz4SplitPackageInspection['stream']['frameCount'] ?? null) !== $expected['lz4SplitPackageFrameCount']
        || ($lz4SplitPackageInspection['stream']['dataFrameCount'] ?? null) !== $expected['lz4SplitPackageDataFrameCount']
        || ($lz4SplitPackageInspection['stream']['dictionaryFrameCount'] ?? null) !== $expected['lz4SplitPackageDictionaryFrameCount']
        || ($lz4SplitPackageInspection['stream']['frames'][0]['frameOffset'] ?? null) !== 0
        || ($lz4SplitPackageInspection['stream']['frames'][0]['nextFrameOffset'] ?? null) !== strlen($lz4SplitSkippable)
        || ($lz4SplitPackageInspection['stream']['frames'][1]['dictionaryId'] ?? null) !== $expected['lz4SplitPackageFirstDictionaryId']
        || ($lz4SplitPackageInspection['stream']['frames'][1]['decodedDataOffset'] ?? null) !== 0
        || ($lz4SplitPackageInspection['stream']['frames'][1]['decodedDataEndOffset'] ?? null) !== $expected['lz4SplitPackageSplitOffset']
        || ($lz4SplitPackageInspection['stream']['frames'][1]['frameOffset'] ?? null) !== strlen($lz4SplitSkippable)
        || ($lz4SplitPackageInspection['stream']['frames'][1]['nextFrameOffset'] ?? null) !== strlen($lz4SplitSkippable) + strlen($lz4SplitFirstFrame)
        || ($lz4SplitPackageInspection['stream']['frames'][2]['dictionaryId'] ?? null) !== $expected['lz4SplitPackageSecondDictionaryId']
        || ($lz4SplitPackageInspection['stream']['frames'][2]['decodedDataOffset'] ?? null) !== $expected['lz4SplitPackageSplitOffset']
        || ($lz4SplitPackageInspection['stream']['frames'][2]['decodedDataEndOffset'] ?? null) !== strlen($lz4SplitPackageArchiveBytes)
        || ($lz4SplitPackageInspection['stream']['frames'][2]['nextFrameOffset'] ?? null) !== strlen($lz4SplitPackageStream)
        || array_column($lz4SplitPackageInspection['entryLayouts'][1]['decodedSourceSegments'] ?? [], 'sourceType') !== $expected['lz4SplitPackageEntrySourceTypes']
        || array_column($lz4SplitPackageInspection['entryLayouts'][1]['decodedSourceSegments'] ?? [], 'sourceDecodedOffset') !== $expected['lz4SplitPackageEntrySourceOffsets']
        || array_column($lz4SplitPackageInspection['entryLayouts'][1]['decodedSourceSegments'] ?? [], 'sourceDecodedEndOffset') !== $expected['lz4SplitPackageEntrySourceEndOffsets']
        || $lz4SplitPackageInspection['archive']->read('/packet/content.md') !== $expected['lz4SplitPackageContent']
        || ($lz4SplitPackageInspection['entryLayouts'][1]['modifiedAt'] ?? null) !== 1780479094
        || !$lz4SplitPackageMissingDictionaryBlocked
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
        || $archiveBombInspection['kind'] !== $expected['archiveBombKind']
        || $archiveBombInspection['format'] !== $expected['archiveBombFormat']
        || $archiveBombInspection['handoffPolicy'] !== $expected['archiveBombPolicy']
        || $archiveBombInspection['diagnostics'] !== $expected['archiveBombDiagnostics']
        || ($archiveBombInspection['stream']['members'][0]['filename'] ?? null) !== $expected['archiveBombFilename']
        || $archiveBombInspection['entryUncompressedSize'] !== $expected['archiveBombContentSize']
        || $archiveBombInspection['streamCompressionRatio'] <= 4.0
        || $archiveBombInspection['totalExpansionRatio'] <= 4.0
        || $unsupportedBzip2Inspection['format'] !== $expected['unsupportedBzip2Format']
        || $unsupportedBzip2Inspection['candidateKind'] !== $expected['unsupportedBzip2Kind']
        || $unsupportedBzip2Inspection['candidateFormat'] !== $expected['unsupportedBzip2CandidateFormat']
        || $unsupportedBzip2Inspection['blockSize100k'] !== $expected['unsupportedBzip2BlockSize']
        || $unsupportedBzip2Inspection['extractionPolicy'] !== $expected['unsupportedPolicy']
        || ($unsupportedBzip2Inspection['diagnostics'][1] ?? null) !== 'archive-compression-format-bzip2-not-decoded'
        || $unsupportedXzInspection['format'] !== $expected['unsupportedXzFormat']
        || $unsupportedXzInspection['candidateKind'] !== $expected['unsupportedXzKind']
        || $unsupportedXzInspection['candidateFormat'] !== $expected['unsupportedXzCandidateFormat']
        || $unsupportedXzInspection['streamFlagsHex'] !== $expected['unsupportedXzFlags']
        || $unsupportedXzInspection['extractionPolicy'] !== $expected['unsupportedPolicy']
        || ($unsupportedXzInspection['diagnostics'][1] ?? null) !== 'archive-compression-format-xz-not-decoded'
        || $sourceNamePolicyInspection['sourceName'] !== 'wordpress-review-packet.docx'
        || $sourceNamePolicyInspection['sourceNameReason'] !== $expected['sourceNamePolicyReason']
        || $sourceNamePolicyInspection['expectedKind'] !== $expected['sourceNamePolicyExpectedKind']
        || $sourceNamePolicyInspection['expectedFormat'] !== $expected['sourceNamePolicyExpectedFormat']
        || $sourceNamePolicyInspection['detectedKind'] !== $expected['sourceNamePolicyDetectedKind']
        || $sourceNamePolicyInspection['detectedFormat'] !== $expected['sourceNamePolicyDetectedFormat']
        || $sourceNamePolicyInspection['handoffPolicy'] !== 'review-before-conversion'
        || $sourceNamePolicyInspection['diagnostics'] !== $expected['sourceNamePolicyDiagnostics']
        || isset($sourceNamePolicyInspection['archive'])
        || isset($sourceNamePolicyInspection['tarBytes'])
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
echo 'gzipTextHint.handoffPolicy=' . $textHintPolicyInspection['handoffPolicy'] . "\n";
echo 'gzipTextHint.binaryTextHintMemberCount=' . $textHintPolicyInspection['binaryTextHintMemberCount'] . "\n";
echo 'gzipTextHint.filename=' . $textHintPolicyInspection['members'][0]['filename'] . "\n";
echo 'gzipTextHint.diagnostics=' . implode(',', $textHintPolicyInspection['diagnostics']) . "\n";
echo 'tar.layout=' . implode(',', $layoutSummary) . "\n";
echo 'tar.contentSource=' . $inspection['entryLayouts'][2]['decodedSourceSegments'][0]['sourceType'] . ':' . $inspection['entryLayouts'][2]['decodedSourceSegments'][0]['sourceLabel'] . "\n";
echo 'content.md=' . $inspection['archive']->read('/packet/content.md') . "\n";
echo 'content.createdAt=' . $inspection['entryLayouts'][2]['createdAt'] . "\n";
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
echo 'specialPolicy.format=' . $specialPolicyInspection['format'] . "\n";
echo 'specialPolicy.extractionPolicy=' . $specialPolicyInspection['extractionPolicy'] . "\n";
echo 'specialPolicy.specialFileEntryCount=' . $specialPolicyInspection['specialFileEntryCount'] . "\n";
echo 'specialPolicy.characterDevice=' . $specialPolicyInspection['entries'][0]['name'] . ':' . $specialPolicyInspection['entries'][0]['deviceMajor'] . ':' . $specialPolicyInspection['entries'][0]['deviceMinor'] . "\n";
echo 'specialPolicy.blockDevice=' . $specialPolicyInspection['entries'][1]['name'] . ':' . $specialPolicyInspection['entries'][1]['deviceMajor'] . ':' . $specialPolicyInspection['entries'][1]['deviceMinor'] . "\n";
echo 'specialPolicy.fifoSource=' . $specialPolicyInspection['entries'][2]['deviceNumberSource'] . "\n";
echo 'specialPolicy.extractionBlocked=' . ($specialPolicyExtractionBlocked ? 'yes' : 'no') . "\n";
echo 'sparsePolicy.format=' . $sparsePolicyInspection['format'] . "\n";
echo 'sparsePolicy.extractionPolicy=' . $sparsePolicyInspection['extractionPolicy'] . "\n";
echo 'sparsePolicy.sparseEntryCount=' . $sparsePolicyInspection['sparseEntryCount'] . "\n";
echo 'sparsePolicy.gnuTypeName=' . $sparsePolicyInspection['entries'][0]['name'] . "\n";
echo 'sparsePolicy.schilyName=' . $sparsePolicyInspection['entries'][1]['name'] . "\n";
echo 'sparsePolicy.mapSource=' . $sparsePolicyInspection['entries'][1]['sparseMapSource'] . "\n";
echo 'sparsePolicy.mapSegmentCount=' . $sparsePolicyInspection['entries'][1]['sparseMapSegmentCount'] . "\n";
echo 'sparsePolicy.mapPayloadBytes=' . $sparsePolicyInspection['entries'][1]['sparseMapPayloadBytes'] . "\n";
echo 'sparsePolicy.malformedMapBlocked=' . ($sparseMalformedMapBlocked ? 'yes' : 'no') . "\n";
echo 'multiVolumePolicy.format=' . $multiVolumePolicyInspection['format'] . "\n";
echo 'multiVolumePolicy.extractionPolicy=' . $multiVolumePolicyInspection['extractionPolicy'] . "\n";
echo 'multiVolumePolicy.multiVolumeEntryCount=' . $multiVolumePolicyInspection['multiVolumeEntryCount'] . "\n";
echo 'multiVolumePolicy.typeName=' . $multiVolumePolicyInspection['entries'][0]['name'] . "\n";
echo 'multiVolumePolicy.paxName=' . $multiVolumePolicyInspection['entries'][1]['name'] . "\n";
echo 'multiVolumePolicy.typeOffset=' . $multiVolumePolicyInspection['entries'][0]['continuationOffset'] . "\n";
echo 'multiVolumePolicy.paxOffset=' . $multiVolumePolicyInspection['entries'][1]['continuationOffset'] . "\n";
echo 'multiVolumePolicy.originalName=' . $multiVolumePolicyInspection['entries'][0]['originalName'] . "\n";
echo 'multiVolumePolicy.extractionBlocked=' . ($multiVolumePolicyExtractionBlocked ? 'yes' : 'no') . "\n";
echo 'multiVolumePolicy.malformedOffsetBlocked=' . ($multiVolumeMalformedOffsetBlocked ? 'yes' : 'no') . "\n";
echo 'signedChecksum.format=' . $signedChecksumInspection['format'] . "\n";
echo 'signedChecksum.entry=' . $signedChecksumInspection['entryNames'][0] . "\n";
echo 'signedChecksum.modifiedAt=' . $signedChecksumInspection['entryLayouts'][0]['modifiedAt'] . "\n";
echo 'charset.format=' . $charsetInspection['format'] . "\n";
echo 'charset.entry=' . $charsetInspection['entryNames'][0] . "\n";
echo 'charset.globalHdrcharset=' . $charsetInspection['archive']->entry('/' . $charsetInspection['entryNames'][0])->globalPaxHeaders['hdrcharset'] . "\n";
echo 'charset.localHdrcharset=' . $charsetInspection['archive']->entry('/' . $charsetInspection['entryNames'][0])->localPaxHeaders['hdrcharset'] . "\n";
echo 'charset.invalidBlocked=' . ($invalidCharsetBlocked ? 'yes' : 'no') . "\n";
echo 'duplicatePax.format=' . $duplicatePaxInspection['format'] . "\n";
echo 'duplicatePax.extractionPolicy=' . $duplicatePaxInspection['extractionPolicy'] . "\n";
echo 'duplicatePax.duplicateEntryCount=' . $duplicatePaxInspection['duplicatePaxEntryCount'] . "\n";
echo 'duplicatePax.keyword=' . $duplicatePaxInspection['entries'][0]['duplicateKeywords'][0] . "\n";
echo 'duplicatePax.values=' . implode('|', $duplicatePaxInspection['entries'][0]['duplicateRecords'][0]['values']) . "\n";
echo 'duplicatePax.extractionBlocked=' . ($duplicatePaxExtractionBlocked ? 'yes' : 'no') . "\n";
echo 'zipDescriptor.format=' . $descriptorZipInspection['format'] . "\n";
echo 'zipDescriptor.entryCount=' . $descriptorZipInspection['entryCount'] . "\n";
echo 'zipDescriptor.descriptorEntryCount=' . $descriptorZipInspection['descriptorEntryCount'] . "\n";
echo 'zipDescriptor.signedCount=' . $descriptorZipInspection['signedDescriptorEntryCount'] . "\n";
echo 'zipDescriptor.unsignedCount=' . $descriptorZipInspection['unsignedDescriptorEntryCount'] . "\n";
echo 'zipDescriptor.names=' . implode(',', array_column($descriptorZipInspection['descriptorEntries'], 'name')) . "\n";
echo 'zipDescriptor.gzipFilename=' . $descriptorZipInspection['stream']['members'][0]['filename'] . "\n";
echo 'zipSplit.format=' . $splitZipInspection['format'] . "\n";
echo 'zipSplit.entryCount=' . $splitZipInspection['entryCount'] . "\n";
echo 'zipSplit.issues=' . implode(',', $splitZipInspection['issues']) . "\n";
echo 'zipSplit.entryDisks=' . implode(',', array_column($splitZipInspection['entries'], 'diskStart')) . "\n";
echo 'zipSplit.gzipFilename=' . $splitZipInspection['stream']['members'][0]['filename'] . "\n";
echo 'zipSplit.extractionBlocked=' . ($splitZipExtractionBlocked ? 'yes' : 'no') . "\n";
echo 'lz4Dictionary.format=' . $lz4DictionaryInspection['format'] . "\n";
echo 'lz4Dictionary.extractionPolicy=' . $lz4DictionaryInspection['extractionPolicy'] . "\n";
echo 'lz4Dictionary.dictionaryFrameCount=' . $lz4DictionaryInspection['dictionaryFrameCount'] . "\n";
echo 'lz4Dictionary.dictionaryId=' . $lz4DictionaryInspection['stream']['frames'][1]['dictionaryId'] . "\n";
echo 'lz4Dictionary.payloadSize=' . $lz4DictionaryInspection['stream']['frames'][1]['contentSize'] . "\n";
echo 'lz4Dictionary.extractionBlocked=' . ($lz4DictionaryExtractionBlocked ? 'yes' : 'no') . "\n";
echo 'zlibDictionary.kind=' . $zlibDictionaryInspection['kind'] . "\n";
echo 'zlibDictionary.format=' . $zlibDictionaryInspection['format'] . "\n";
echo 'zlibDictionary.dictionaryId=' . $zlibDictionaryInspection['stream']['presetDictionaryId'] . "\n";
echo 'zlibDictionary.dictionarySize=' . $zlibDictionaryInspection['stream']['dictionarySize'] . "\n";
echo 'zlibDictionary.content.md=' . $zlibDictionaryInspection['archive']->read('/packet/content.md') . "\n";
echo 'zlibDictionary.missingBlocked=' . ($zlibDictionaryMissingBlocked ? 'yes' : 'no') . "\n";
echo 'lz4Package.kind=' . $lz4PackageInspection['kind'] . "\n";
echo 'lz4Package.format=' . $lz4PackageInspection['format'] . "\n";
echo 'lz4Package.dictionaryId=' . $lz4PackageInspection['stream']['frames'][1]['dictionaryId'] . "\n";
echo 'lz4Package.dictionarySize=' . $lz4PackageInspection['stream']['frames'][1]['dictionarySize'] . "\n";
echo 'lz4Package.content.md=' . $lz4PackageInspection['archive']->read('/packet/content.md') . "\n";
echo 'lz4Package.missingBlocked=' . ($lz4PackageMissingDictionaryBlocked ? 'yes' : 'no') . "\n";
echo 'lz4SplitPackage.kind=' . $lz4SplitPackageInspection['kind'] . "\n";
echo 'lz4SplitPackage.format=' . $lz4SplitPackageInspection['format'] . "\n";
echo 'lz4SplitPackage.frameCount=' . $lz4SplitPackageInspection['stream']['frameCount'] . "\n";
echo 'lz4SplitPackage.firstRange=' . $lz4SplitPackageInspection['stream']['frames'][1]['decodedDataOffset'] . ':' . $lz4SplitPackageInspection['stream']['frames'][1]['decodedDataEndOffset'] . "\n";
echo 'lz4SplitPackage.secondRange=' . $lz4SplitPackageInspection['stream']['frames'][2]['decodedDataOffset'] . ':' . $lz4SplitPackageInspection['stream']['frames'][2]['decodedDataEndOffset'] . "\n";
echo 'lz4SplitPackage.entrySources=' . implode(',', array_map(
    static fn (array $segment): string => $segment['sourceType'] . ':' . $segment['sourceDecodedOffset'] . '-' . $segment['sourceDecodedEndOffset'],
    $lz4SplitPackageInspection['entryLayouts'][1]['decodedSourceSegments']
)) . "\n";
echo 'lz4SplitPackage.content.md=' . $lz4SplitPackageInspection['archive']->read('/packet/content.md') . "\n";
echo 'lz4SplitPackage.missingBlocked=' . ($lz4SplitPackageMissingDictionaryBlocked ? 'yes' : 'no') . "\n";
echo 'nested.rootKind=' . $nestedInspection['rootKind'] . "\n";
echo 'nested.rootFormat=' . $nestedInspection['rootFormat'] . "\n";
echo 'nested.candidateCount=' . $nestedInspection['candidateCount'] . "\n";
echo 'nested.packageCount=' . $nestedInspection['packageCount'] . "\n";
echo 'nested.diagnosticCount=' . $nestedInspection['diagnosticCount'] . "\n";
echo 'nested.paths=' . implode(',', array_map(static fn (array $entry): string => $entry['path'], $nestedInspection['entries'])) . "\n";
echo 'archiveBomb.kind=' . $archiveBombInspection['kind'] . "\n";
echo 'archiveBomb.format=' . $archiveBombInspection['format'] . "\n";
echo 'archiveBomb.handoffPolicy=' . $archiveBombInspection['handoffPolicy'] . "\n";
echo 'archiveBomb.diagnostics=' . implode(',', $archiveBombInspection['diagnostics']) . "\n";
echo 'archiveBomb.streamRatio=' . number_format($archiveBombInspection['streamCompressionRatio'], 2, '.', '') . "\n";
echo 'archiveBomb.totalRatio=' . number_format($archiveBombInspection['totalExpansionRatio'], 2, '.', '') . "\n";
echo 'unsupportedBzip2.format=' . $unsupportedBzip2Inspection['format'] . "\n";
echo 'unsupportedBzip2.candidateFormat=' . $unsupportedBzip2Inspection['candidateFormat'] . "\n";
echo 'unsupportedBzip2.extractionPolicy=' . $unsupportedBzip2Inspection['extractionPolicy'] . "\n";
echo 'unsupportedBzip2.diagnostics=' . implode(',', $unsupportedBzip2Inspection['diagnostics']) . "\n";
echo 'unsupportedXz.format=' . $unsupportedXzInspection['format'] . "\n";
echo 'unsupportedXz.candidateFormat=' . $unsupportedXzInspection['candidateFormat'] . "\n";
echo 'unsupportedXz.extractionPolicy=' . $unsupportedXzInspection['extractionPolicy'] . "\n";
echo 'unsupportedXz.diagnostics=' . implode(',', $unsupportedXzInspection['diagnostics']) . "\n";
echo 'sourceNamePolicy.sourceName=' . $sourceNamePolicyInspection['sourceName'] . "\n";
echo 'sourceNamePolicy.expected=' . $sourceNamePolicyInspection['expectedKind'] . '/' . $sourceNamePolicyInspection['expectedFormat'] . "\n";
echo 'sourceNamePolicy.detected=' . $sourceNamePolicyInspection['detectedKind'] . '/' . $sourceNamePolicyInspection['detectedFormat'] . "\n";
echo 'sourceNamePolicy.handoffPolicy=' . $sourceNamePolicyInspection['handoffPolicy'] . "\n";
echo 'sourceNamePolicy.diagnostics=' . implode(',', $sourceNamePolicyInspection['diagnostics']) . "\n";
