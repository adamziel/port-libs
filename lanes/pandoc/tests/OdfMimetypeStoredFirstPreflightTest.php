<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Mimetype preflight packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$baseParts = static function (array $mimetypeOverrides = []) use ($manifestXml, $contentXml): array {
    $mimetypePart = array_merge([
        'name' => 'mimetype',
        'data' => OpenDocumentPackage::TEXT_MIMETYPE,
        'compressionMethod' => 0,
    ], $mimetypeOverrides);

    return [
        $mimetypePart,
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ];
};

$buildZipPackage = static function (array $parts): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $dataDescriptor = ($part['dataDescriptor'] ?? false) === true;
        $flags = ($part['generalPurposeFlags'] ?? 0x0800) | ($dataDescriptor ? 0x0008 : 0);
        $localExtra = $part['localExtra'] ?? '';
        $centralExtra = $part['centralExtra'] ?? $localExtra;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new RuntimeException("Unable to deflate {$name}");
        }

        $offset = strlen($body);
        $crc = $crc32($data);
        $localCrc = $dataDescriptor ? 0 : $crc;
        $localCompressedSize = $dataDescriptor ? 0 : strlen($compressed);
        $localUncompressedSize = $dataDescriptor ? 0 : strlen($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $localCrc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            strlen($localExtra)
        );
        $body .= $name . $localExtra . $compressed;
        if ($dataDescriptor) {
            $body .= "PK\x07\x08" . pack('VVV', $crc, strlen($compressed), strlen($data));
        }

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            strlen($centralExtra),
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        );
        $central .= $name . $centralExtra;
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
};

$runtimeExceptionMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    throw new RuntimeException('Expected RuntimeException was not thrown');
};

return [
    'exposes compact ODT mimetype stored-first preflight metadata' => static function (TestRunner $t) use ($baseParts, $buildZipPackage): void {
        $package = $buildZipPackage($baseParts());
        $summary = OpenDocumentPackage::fromPackage($package)->summarize();
        $mimetype = $summary['mimetypeEntry'];
        $preflight = $package->storedFirstEntryPreflight('mimetype', OpenDocumentPackage::TEXT_MIMETYPE);

        $t->same('mimetype', $mimetype['entryName']);
        $t->same($preflight['entryName'], $mimetype['entryName']);
        $t->same($preflight['exists'], $mimetype['exists']);
        $t->same($preflight['firstLocalEntryName'], $mimetype['firstLocalEntryName']);
        $t->same($preflight['isFirstLocalEntry'], $mimetype['isFirstLocalEntry']);
        $t->same($preflight['generalPurposeFlags'], $mimetype['generalPurposeFlags']);
        $t->same($preflight['usesDataDescriptor'], $mimetype['usesDataDescriptor']);
        $t->same($preflight['isStored'], $mimetype['isStored']);
        $t->same($preflight['expectedBytes'], $mimetype['expectedBytes']);
        $t->same($preflight['contentBytes'], $mimetype['contentBytes']);
        $t->same($preflight['contentsMatch'], $mimetype['contentsMatch']);
        $t->same($preflight['isValid'], $mimetype['isValid']);
        $t->same($preflight['diagnostics'], $mimetype['diagnostics']);
        $t->same(true, $mimetype['firstLocalEntry']);
        $t->same(true, $mimetype['firstCentralDirectoryEntry']);
        $t->same(false, $mimetype['hasLocalExtraFields']);
        $t->same(false, $mimetype['hasCentralExtraFields']);
        $t->same(false, $mimetype['canExposeBytes']);
        $t->same('odf-mimetype-validation-only', $mimetype['byteExposurePolicy']);
    },
    'rejects compact ODT mimetype central extra fields before package exposure' => static function (TestRunner $t) use ($baseParts, $buildZipPackage, $runtimeExceptionMessage): void {
        $extraField = pack('vva*', 0xcafe, strlen('review'), 'review');
        $package = $buildZipPackage($baseParts(['centralExtra' => $extraField]));
        $preflight = $package->storedFirstEntryPreflight('mimetype', OpenDocumentPackage::TEXT_MIMETYPE);

        $t->same([0xcafe], $preflight['centralExtraFieldIds']);
        $t->same([], $preflight['localExtraFieldIds']);
        $t->same(false, $preflight['isValid']);

        $message = $runtimeExceptionMessage(
            static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($package)
        );
        $t->contains('must not carry ZIP extra fields', $message);
    },
    'rejects compact ODT mimetype data descriptors before package exposure' => static function (TestRunner $t) use ($baseParts, $buildZipPackage, $runtimeExceptionMessage): void {
        $package = $buildZipPackage($baseParts(['dataDescriptor' => true]));
        $preflight = $package->storedFirstEntryPreflight('mimetype', OpenDocumentPackage::TEXT_MIMETYPE);

        $t->same(true, $preflight['usesDataDescriptor']);
        $t->same(true, $preflight['contentsMatch']);
        $t->same(false, $preflight['isValid']);

        $message = $runtimeExceptionMessage(
            static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($package)
        );
        $t->contains('must not use a ZIP data descriptor', $message);
    },
];
