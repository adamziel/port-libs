<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/extra.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record compression method review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="CompressionMethodBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Compression Method Review</dc:title>
  </office:meta>
</office:document-meta>
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

$buildPackage = static function () use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): ZipPackage {
    $parts = [
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/source.png', 'data' => str_repeat('P', 512), 'compressionMethod' => 0],
        ['name' => 'Pictures/extra.bin', 'data' => str_repeat('B', 64), 'compressionMethod' => 8],
        ['name' => 'Payloads/raw.bin', 'data' => str_repeat('O', 96), 'compressionMethod' => 12],
        ['name' => 'Notes/private.txt', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
    ];

    return $buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name'));
};

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$sumByCompressionMethod = static function (array $parts, string $field): array {
    $sums = [];
    foreach ($parts as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        $method = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
        $sums[$method] = ($sums[$method] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }
    ksort($sums, SORT_STRING);

    return $sums;
};

$countByCompressionMethod = static function (array $parts): array {
    $counts = [];
    foreach ($parts as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        $method = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
        $counts[$method] = ($counts[$method] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
};

$ratiosByCompressionMethod = static function (array $parts) use ($sumByCompressionMethod): array {
    $compressed = $sumByCompressionMethod($parts, 'compressedByteLength');
    $uncompressed = $sumByCompressionMethod($parts, 'byteLength');
    $ratios = [];
    foreach ($uncompressed as $method => $uncompressedBytes) {
        $compressedBytes = $compressed[$method] ?? 0;
        $ratios[$method] = $uncompressedBytes === 0
            ? 0.0
            : ($compressedBytes === 0 ? null : (float) ($uncompressedBytes / $compressedBytes));
    }
    ksort($ratios, SORT_STRING);

    return $ratios;
};

return [
    'summarizes ODT ZIP source records by compression method' => static function (TestRunner $t) use ($buildPackage, $indexBy, $sumByCompressionMethod, $countByCompressionMethod, $ratiosByCompressionMethod): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedCounts = $countByCompressionMethod($compactInventory['parts']);
        $expectedSourceRecordBytes = $sumByCompressionMethod($compactInventory['parts'], 'zipSourceRecordBytes');
        $expectedCompressedBytes = $sumByCompressionMethod($compactInventory['parts'], 'compressedByteLength');
        $expectedUncompressedBytes = $sumByCompressionMethod($compactInventory['parts'], 'byteLength');
        $expectedRatios = $ratiosByCompressionMethod($compactInventory['parts']);

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(3, $handoff['packageZipSourceRecordCompressionMethodCount']);
            $t->same($expectedCounts, $handoff['packageZipSourceRecordCompressionMethodCounts']);
            $t->same($expectedSourceRecordBytes, $handoff['packageZipSourceRecordCompressionMethodBytes']);
            $t->same($expectedCompressedBytes, $handoff['packageZipSourceRecordCompressionMethodCompressedByteLengths']);
            $t->same($expectedUncompressedBytes, $handoff['packageZipSourceRecordCompressionMethodUncompressedByteLengths']);
            $t->same($expectedRatios, $handoff['packageZipSourceRecordCompressionMethodExpansionRatios']);
            $t->same(0, $handoff['packageZipSourceRecordCompressionMethodDataDescriptorEntryCount']);
            $t->same(1, $handoff['packageZipSourceRecordCompressionMethodUnsupportedEntryCount']);
        }

        $t->same(
            $compactInventory['packageZipSourceRecordCompressionMethods'],
            $compactIdentity['packageZipSourceRecordCompressionMethods']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordCompressionMethods'],
            $richIdentity['packageZipSourceRecordCompressionMethods']
        );

        $methods = $indexBy($compactInventory['packageZipSourceRecordCompressionMethods'], 'compressionMethodKey');
        $richMethods = $indexBy($richProvenance['packageZipSourceRecordCompressionMethods'], 'compressionMethodKey');
        $stored = $methods['0'];
        $deflated = $methods['8'];
        $unsupported = $methods['12'];

        $t->same('stored', $stored['compressionMethodName']);
        $t->same($expectedCounts['0'], $stored['entryCount']);
        $t->same($expectedSourceRecordBytes['0'], $stored['sourceRecordBytes']);
        $t->same($expectedCompressedBytes['0'], $stored['compressedByteLength']);
        $t->same($expectedUncompressedBytes['0'], $stored['uncompressedByteLength']);
        $t->same($expectedRatios['0'], $stored['expansionRatio']);
        $t->same('META-INF/manifest.xml', $stored['largestSourceRecordEntry']['entryName']);
        $t->same(false, $stored['largestSourceRecordEntry']['canExposeBytes']);

        $t->same('deflated', $deflated['compressionMethodName']);
        $t->same(['Pictures/extra.bin', 'content.xml', 'styles.xml'], $deflated['entryNames']);
        $t->same($expectedSourceRecordBytes['8'], $deflated['sourceRecordBytes']);
        $t->same($expectedCompressedBytes['8'], $deflated['compressedByteLength']);
        $t->same($expectedUncompressedBytes['8'], $deflated['uncompressedByteLength']);
        $t->same($expectedRatios['8'], $deflated['expansionRatio']);
        $t->same(0, $deflated['unsupportedEntryCount']);

        $t->same('unsupported', $unsupported['compressionMethodName']);
        $t->same(['Payloads/raw.bin'], $unsupported['entryNames']);
        $t->same(1, $unsupported['entryCount']);
        $t->same(1, $unsupported['unsupportedEntryCount']);
        $t->same($expectedSourceRecordBytes['12'], $unsupported['sourceRecordBytes']);
        $t->same($expectedCompressedBytes['12'], $unsupported['compressedByteLength']);
        $t->same($expectedUncompressedBytes['12'], $unsupported['uncompressedByteLength']);
        $t->same($expectedRatios['12'], $unsupported['expansionRatio']);
        $t->same('Payloads/raw.bin', $unsupported['largestSourceRecordEntry']['entryName']);
        $t->same(false, $unsupported['largestSourceRecordEntry']['canExposeBytes']);

        foreach (['0', '8', '12'] as $methodKey) {
            $t->same($methods[$methodKey]['entryNames'], $richMethods[$methodKey]['entryNames']);
            $t->same($methods[$methodKey]['sourceRecordBytes'], $richMethods[$methodKey]['sourceRecordBytes']);
            $t->same($methods[$methodKey]['compressedByteLength'], $richMethods[$methodKey]['compressedByteLength']);
            $t->same($methods[$methodKey]['uncompressedByteLength'], $richMethods[$methodKey]['uncompressedByteLength']);
            $t->same($methods[$methodKey]['expansionRatio'], $richMethods[$methodKey]['expansionRatio']);
            $t->same($methods[$methodKey]['unsupportedEntryCount'], $richMethods[$methodKey]['unsupportedEntryCount']);
        }
    },
];
