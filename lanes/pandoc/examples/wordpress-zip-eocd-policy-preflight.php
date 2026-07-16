<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\DeflateStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\ZipPackage;

$zipFixtureBytes = static function (array $entries, string $packageComment = ''): string {
    $body = '';
    $centralDirectory = '';
    foreach ($entries as $entry) {
        $name = (string) $entry['name'];
        $data = (string) ($entry['data'] ?? '');
        $method = (int) ($entry['compressionMethod'] ?? 0);
        $payload = $method === 8 ? gzdeflate($data) : $data;
        $crc32 = (int) sprintf('%u', crc32($data));
        $localHeaderOffset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            $method,
            0,
            0,
            $crc32,
            strlen($payload),
            strlen($data),
            strlen($name),
            0
        ) . $name . $payload;

        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0,
            $method,
            0,
            0,
            $crc32,
            strlen($payload),
            strlen($data),
            strlen($name),
            0,
            0,
            0,
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
            0,
            0,
            count($entries),
            count($entries),
            strlen($centralDirectory),
            strlen($body),
            strlen($packageComment)
        )
        . $packageComment;
};

$rewriteZipEndOfCentralDirectoryOffset = static function (string $zip, int $centralDirectoryOffset): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if (!is_int($eocdOffset)) {
        throw new RuntimeException('ZIP fixture is missing an end of central directory record.');
    }

    return substr_replace($zip, pack('V', $centralDirectoryOffset), $eocdOffset + 16, 4);
};

$baseZipBytes = $zipFixtureBytes([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'compressionMethod' => 0,
    ],
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>EOCD review packet</w:p></w:body></w:document>',
        'compressionMethod' => 8,
    ],
], 'wordpress eocd review');

$trailingZipBytes = $baseZipBytes . "detached reviewer bytes\n";
$badOffsetZipBytes = $rewriteZipEndOfCentralDirectoryOffset($baseZipBytes, 0);

$trailingInspection = ArchiveCompressionStream::inspectZipEndOfCentralDirectoryPolicy(
    GzipStream::build($trailingZipBytes, [
        'filename' => 'wordpress-eocd-layout-package.zip',
        'comment' => 'EOCD layout review',
        'headerCrc' => true,
    ]),
    ArchiveCompressionStream::FORMAT_GZIP_ZIP,
    strlen($trailingZipBytes)
);

$badOffsetInspection = ArchiveCompressionStream::inspectZipEndOfCentralDirectoryPolicy(
    DeflateStream::build($badOffsetZipBytes, [
        'format' => DeflateStream::FORMAT_RAW,
        'compressionLevel' => 9,
    ]),
    ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP,
    strlen($badOffsetZipBytes)
);

$trailingExtractionBlocked = false;
try {
    ZipPackage::fromString($trailingZipBytes);
} catch (RuntimeException) {
    $trailingExtractionBlocked = true;
}

$badOffsetExtractionBlocked = false;
try {
    ZipPackage::fromString($badOffsetZipBytes);
} catch (RuntimeException) {
    $badOffsetExtractionBlocked = true;
}

if (($argv[1] ?? null) === '--self-test') {
    if (
        $trailingInspection['type'] !== 'zip-end-of-central-directory-policy'
        || $trailingInspection['handoffPolicy'] !== 'review-before-conversion'
        || $trailingInspection['extractionPolicy'] !== 'zip-eocd-review'
        || $trailingInspection['issues'] !== ['eocd-trailing-bytes']
        || $trailingInspection['hasTrailingBytes'] !== true
        || $trailingInspection['trailingByteCount'] !== strlen("detached reviewer bytes\n")
        || ($trailingInspection['stream']['members'][0]['filename'] ?? null) !== 'wordpress-eocd-layout-package.zip'
        || $badOffsetInspection['issues'] !== ['central-directory-offset-not-central-header']
        || $badOffsetInspection['centralDirectoryOffsetLocation'] !== 'local-file-header'
        || $badOffsetInspection['extractionPolicy'] !== 'zip-eocd-review'
        || !$trailingExtractionBlocked
        || !$badOffsetExtractionBlocked
        || isset($trailingInspection['package'])
        || isset($badOffsetInspection['package'])
    ) {
        throw new RuntimeException('wordpress-zip-eocd-policy-preflight self-test failed');
    }

    echo "wordpress-zip-eocd-policy-preflight self-test passed\n";
    return;
}

echo json_encode([
    'trailing' => [
        'policy' => $trailingInspection['handoffPolicy'],
        'issues' => $trailingInspection['issues'],
        'trailingByteCount' => $trailingInspection['trailingByteCount'],
        'sourceName' => $trailingInspection['stream']['members'][0]['filename'] ?? null,
    ],
    'badOffset' => [
        'policy' => $badOffsetInspection['handoffPolicy'],
        'issues' => $badOffsetInspection['issues'],
        'centralDirectoryOffsetLocation' => $badOffsetInspection['centralDirectoryOffsetLocation'],
    ],
    'extractionBlocked' => [
        'trailing' => $trailingExtractionBlocked,
        'badOffset' => $badOffsetExtractionBlocked,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
