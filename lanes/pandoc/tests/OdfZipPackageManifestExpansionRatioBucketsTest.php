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
      <text:p>ZIP package manifest expansion ratio buckets.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="RatioBucketBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Package Manifest Expansion Ratio Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$highRatioBytes = str_repeat('H', 70000);
$unknownName = 'Payloads/zero-compressed.bin';
$unknownUncompressedSize = 37;
$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/high.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="Pictures/empty.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$buildZipPackage = static function (array $parts): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($parts as $part) {
        $name = $part['name'];
        $rawName = $part['rawName'] ?? $name;
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $part['generalPurposeFlags'] ?? 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate ZIP entry {$name}");
        }

        $localData = $part['localData'] ?? $compressed;
        $localCompressedSize = $part['localCompressedSize'] ?? strlen($localData);
        $localUncompressedSize = $part['localUncompressedSize'] ?? strlen($data);
        $centralCompressedSize = $part['centralCompressedSize'] ?? strlen($compressed);
        $centralUncompressedSize = $part['centralUncompressedSize'] ?? strlen($data);
        $crc = $part['crc32'] ?? $crc32($data);
        $offset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($rawName),
            0
        );
        $body .= $rawName . $localData;

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
            $centralCompressedSize,
            $centralUncompressedSize,
            strlen($rawName),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $rawName;
    }

    $centralOffset = strlen($body);
    $entryCount = count($parts);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), $centralOffset, 0)
    );
};

$buildPackage = static fn (): ZipPackage => $buildZipPackage([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/high.bin', 'data' => $highRatioBytes, 'compressionMethod' => 8],
    ['name' => 'Pictures/empty.bin', 'data' => '', 'compressionMethod' => 0],
    [
        'name' => $unknownName,
        'data' => '',
        'compressionMethod' => 12,
        'centralCompressedSize' => 0,
        'centralUncompressedSize' => $unknownUncompressedSize,
        'localData' => '',
        'localCompressedSize' => 0,
        'localUncompressedSize' => $unknownUncompressedSize,
        'crc32' => 0,
    ],
]);

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT ZIP package manifest expansion ratio buckets across package handoff' => static function (TestRunner $t) use ($buildPackage, $indexBy, $unknownName): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedBuckets = ['zero-byte', 'up-to-1x', 'over-100x', 'unknown'];
        $expectedEntryNames = [
            'zero-byte' => ['Pictures/empty.bin'],
            'up-to-1x' => ['mimetype', 'META-INF/manifest.xml', 'content.xml', 'styles.xml', 'meta.xml'],
            'over-100x' => ['Pictures/high.bin'],
            'unknown' => [$unknownName],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance, $documentIdentity] as $handoff) {
            $t->same(4, $handoff['zipPackageManifestExpansionRatioBucketSummaryCount']);
            $t->same($expectedBuckets, $handoff['zipPackageManifestExpansionRatioBuckets']);

            $summaries = $indexBy($handoff['zipPackageManifestExpansionRatioBucketSummaries'], 'expansionRatioBucket');
            foreach ($expectedEntryNames as $bucket => $entryNames) {
                $t->same($entryNames, $summaries[$bucket]['entryNames']);
                $t->same(count($entryNames), $summaries[$bucket]['entryCount']);
            }
            $t->same(1, $summaries['unknown']['unknownExpansionRatioEntryCount']);
            $t->same(null, $summaries['unknown']['largestExpansionRatio']);
            $t->true(($summaries['over-100x']['largestExpansionRatio'] ?? 0.0) > 100.0);
        }

        $compactParts = $indexBy($compactInventory['parts'], 'path');
        $compactIdentityEntries = $indexBy($compactIdentity['packageEntries'], 'path');
        $richParts = $indexBy($richProvenance['parts'], 'part');
        $richIdentityEntries = $indexBy($richIdentity['packageEntries'], 'part');
        $documentIdentityEntries = $indexBy($documentIdentity['packageEntries'], 'part');

        foreach ([$compactParts, $compactIdentityEntries] as $entries) {
            $t->same('zero-byte', $entries['Pictures/empty.bin']['zipPackageManifestExpansionRatioBucket']);
            $t->same('up-to-1x', $entries['mimetype']['zipPackageManifestExpansionRatioBucket']);
            $t->same('over-100x', $entries['Pictures/high.bin']['zipPackageManifestExpansionRatioBucket']);
            $t->same('unknown', $entries[$unknownName]['zipPackageManifestExpansionRatioBucket']);
        }

        foreach ([$richParts, $richIdentityEntries, $documentIdentityEntries] as $entries) {
            $t->same('zero-byte', $entries['Pictures/empty.bin']['zipPackageManifestExpansionRatioBucket']);
            $t->same('up-to-1x', $entries['mimetype']['zipPackageManifestExpansionRatioBucket']);
            $t->same('over-100x', $entries['Pictures/high.bin']['zipPackageManifestExpansionRatioBucket']);
            $t->same('unknown', $entries[$unknownName]['zipPackageManifestExpansionRatioBucket']);
        }

        $t->same($compactInventory['zipPackageManifestExpansionRatioBucketSummaries'], $richProvenance['zipPackageManifestExpansionRatioBucketSummaries']);
        $t->same($compactIdentity['zipPackageManifestExpansionRatioBuckets'], $richIdentity['zipPackageManifestExpansionRatioBuckets']);
        $t->same($richProvenance['zipPackageManifestExpansionRatioBucketSummaries'], $documentProvenance['zipPackageManifestExpansionRatioBucketSummaries']);
    },
];
