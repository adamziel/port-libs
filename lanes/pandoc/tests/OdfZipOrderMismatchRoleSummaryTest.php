<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP order role summary.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$buildZipPackageWithCentralDirectoryOrder = static function (array $parts, array $centralOrder): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $centralRecords = [];

    foreach ($parts as $part) {
        $name = $part['name'];
        $rawName = $part['rawName'] ?? $name;
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $part['generalPurposeFlags'] ?? 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0
        );
        $body .= $rawName . $compressed;

        $centralRecords[$name] = pack(
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
            strlen($rawName),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $rawName;
    }

    $central = '';
    foreach ($centralOrder as $name) {
        if (!isset($centralRecords[$name])) {
            throw new RuntimeException("Missing central directory record for {$name}");
        }

        $central .= $centralRecords[$name];
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
};

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
};

return [
    'summarizes ODT ZIP order mismatches by package role' => static function (TestRunner $t) use (
        $buildZipPackageWithCentralDirectoryOrder,
        $indexBy,
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml
    ): void {
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMBNAIL', 'compressionMethod' => 0],
        ];
        $centralOrder = [
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Thumbnails/thumbnail.png',
            'Pictures/',
            'mimetype',
        ];
        $expectedRoleCounts = [
            'manifest-declared' => 4,
            'media-resource' => 1,
            'odf-content' => 1,
            'odf-manifest' => 1,
            'odf-meta' => 1,
            'odf-mimetype' => 1,
            'odf-styles' => 1,
            'package-thumbnail' => 1,
            'undeclared-package-entry' => 1,
            'zip-directory' => 1,
        ];

        $package = $buildZipPackageWithCentralDirectoryOrder($parts, $centralOrder);
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];

        $fields = [
            'centralDirectoryOrderMismatchRoleCount',
            'centralDirectoryOrderMismatchRoleCounts',
            'centralDirectoryOrderMismatchRoleByteLengths',
            'centralDirectoryOrderMismatchRoleCompressedByteLengths',
            'centralDirectoryOrderMismatchRoleSummaries',
        ];
        foreach ($fields as $field) {
            $t->same($compactInventory[$field], $compactIdentity[$field], "{$field} compact identity");
            $t->same($compactInventory[$field], $richProvenance[$field], "{$field} rich provenance");
            $t->same($compactInventory[$field], $richIdentity[$field], "{$field} rich identity");
        }

        $t->same(false, $compactInventory['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same(false, $richProvenance['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same(10, $compactInventory['centralDirectoryOrderMismatchRoleCount']);
        $t->same($expectedRoleCounts, $compactInventory['centralDirectoryOrderMismatchRoleCounts']);
        $t->same($compactInventory['roleByteLengths'], $compactInventory['centralDirectoryOrderMismatchRoleByteLengths']);
        $t->same($richProvenance['roleCompressedByteLengths'], $richProvenance['centralDirectoryOrderMismatchRoleCompressedByteLengths']);

        $summariesByRole = $indexBy($compactInventory['centralDirectoryOrderMismatchRoleSummaries'], 'role');
        $t->same([
            'role' => 'manifest-declared',
            'mismatchedEntryCount' => 4,
            'byteLength' => $compactInventory['roleByteLengths']['manifest-declared'],
            'compressedByteLength' => $compactInventory['roleCompressedByteLengths']['manifest-declared'],
            'mismatchedEntryNames' => ['content.xml', 'styles.xml', 'meta.xml', 'Pictures/hero.png'],
            'centralDirectoryIndexes' => [1, 2, 3, 4],
            'localHeaderOrders' => [2, 3, 4, 6],
        ], $summariesByRole['manifest-declared']);
        $t->same([
            'role' => 'odf-mimetype',
            'mismatchedEntryCount' => 1,
            'byteLength' => strlen(OdfReader::MIMETYPE),
            'compressedByteLength' => strlen(OdfReader::MIMETYPE),
            'mismatchedEntryNames' => ['mimetype'],
            'centralDirectoryIndexes' => [7],
            'localHeaderOrders' => [0],
        ], $summariesByRole['odf-mimetype']);
        $t->same([
            'role' => 'zip-directory',
            'mismatchedEntryCount' => 1,
            'byteLength' => 0,
            'compressedByteLength' => 0,
            'mismatchedEntryNames' => ['Pictures/'],
            'centralDirectoryIndexes' => [6],
            'localHeaderOrders' => [5],
        ], $summariesByRole['zip-directory']);

        foreach ($compactInventory['centralDirectoryOrderMismatchRoleSummaries'] as $summary) {
            $t->same(false, array_key_exists('contents', $summary));
            $t->same(false, array_key_exists('data', $summary));
            $t->same(false, array_key_exists('bytes', $summary));
        }
    },
];
