<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Source-record provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Source Record Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$scriptXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Review
End Sub</script:module>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0, 'comment' => 'manifest record'],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0, 'comment' => 'script record'],
];

$packageManifestEntriesByName = static function (ZipPackage $package): array {
    $entriesByName = [];
    foreach ($package->packageManifestPreflight()['entries'] as $entry) {
        $entriesByName[$entry['name']] = $entry;
    }

    return $entriesByName;
};

$expectedSourceRecord = static function (array $entry): array {
    return [
        'hasZipSourceRecordProvenance' => true,
        'zipLocalHeaderLength' => $entry['localHeaderLength'],
        'zipLocalHeaderSha256' => $entry['localHeaderSha256'],
        'zipCentralDirectoryRecordOffset' => $entry['centralDirectoryRecordOffset'],
        'zipCentralDirectoryRecordEnd' => $entry['centralDirectoryRecordEnd'],
        'zipCentralDirectoryRecordBytes' => $entry['centralDirectoryRecordEnd'] - $entry['centralDirectoryRecordOffset'],
        'zipCentralDirectoryRecordSha256' => $entry['centralDirectoryRecordSha256'],
        'zipSourceRecordReviewBytes' => $entry['localHeaderLength']
            + ($entry['centralDirectoryRecordEnd'] - $entry['centralDirectoryRecordOffset']),
    ];
};

$assertSourceRecord = static function (TestRunner $t, array $actual, array $expected, string $context): void {
    foreach ($expected as $key => $value) {
        $t->same($value, $actual[$key] ?? null, "{$context} {$key}");
    }
};

$summaryItemsByName = static function (array $items, string $nameKey): array {
    $itemsByName = [];
    foreach ($items as $item) {
        $name = $item[$nameKey] ?? null;
        if (is_string($name) && $name !== '') {
            $itemsByName[$name] = $item;
        }
    }

    return $itemsByName;
};

$sourceRecordTotals = static function (array $entries): array {
    $localHeaderBytes = 0;
    $centralDirectoryRecordBytes = 0;

    foreach ($entries as $entry) {
        $localHeaderBytes += $entry['localHeaderLength'];
        $centralDirectoryRecordBytes += $entry['centralDirectoryRecordEnd'] - $entry['centralDirectoryRecordOffset'];
    }

    return [
        'entryCount' => count($entries),
        'localHeaderBytes' => $localHeaderBytes,
        'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
        'sourceRecordReviewBytes' => $localHeaderBytes + $centralDirectoryRecordBytes,
    ];
};

return [
    'carries ODF ZIP source-record provenance without exposing package bytes' => static function (TestRunner $t) use (
        $parts,
        $packageManifestEntriesByName,
        $expectedSourceRecord,
        $assertSourceRecord,
        $summaryItemsByName,
        $sourceRecordTotals
    ): void {
        $package = ZipPackage::fromParts($parts, 'odf source record');
        $manifestEntriesByName = $packageManifestEntriesByName($package);
        $readerResult = (new OdfReader())->readPackage($package);
        $readerProvenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $compactInventory = OpenDocumentPackage::fromPackage($package)->summarize()['packageInventory'];
        $expectedTotals = $sourceRecordTotals($manifestEntriesByName);

        foreach (['content.xml', 'Basic/Standard/Review.xml'] as $name) {
            $expected = $expectedSourceRecord($manifestEntriesByName[$name]);

            $assertSourceRecord($t, $readerProvenance['parts'][$name], $expected, "reader {$name}");
            $assertSourceRecord($t, $compactInventory['parts'][$name], $expected, "compact {$name}");
            $t->same(false, array_key_exists('compressedDataSha256', $readerProvenance['parts'][$name]), "reader {$name} compressed hash omitted");
            $t->same(false, array_key_exists('compressedDataSha256', $compactInventory['parts'][$name]), "compact {$name} compressed hash omitted");
        }

        $readerScript = $readerProvenance['parts']['Basic/Standard/Review.xml'];
        $compactScript = $compactInventory['parts']['Basic/Standard/Review.xml'];
        $t->same('script-package-bytes-blocked', $readerScript['byteExposurePolicy']);
        $t->same('script-package-bytes-blocked', $compactScript['byteExposurePolicy']);
        $t->same(false, $readerScript['canExposeBytes']);
        $t->same(false, $compactScript['canExposeBytes']);
        $t->same(true, $readerScript['hasZipSourceRecordProvenance']);
        $t->same(true, $compactScript['hasZipSourceRecordProvenance']);

        foreach (['reader' => $readerProvenance['zipSourceRecords'], 'compact' => $compactInventory['zipSourceRecords']] as $context => $summary) {
            $t->same($expectedTotals['entryCount'], $summary['entryCount'], "{$context} summary entry count");
            $t->same($expectedTotals['localHeaderBytes'], $summary['localHeaderBytes'], "{$context} summary local headers");
            $t->same($expectedTotals['centralDirectoryRecordBytes'], $summary['centralDirectoryRecordBytes'], "{$context} summary central records");
            $t->same($expectedTotals['sourceRecordReviewBytes'], $summary['sourceRecordReviewBytes'], "{$context} summary review bytes");
            $t->same(false, $summary['canExposeBytes'], "{$context} summary byte exposure");
            $t->same('zip-source-record-provenance-metadata-only', $summary['byteExposurePolicy'], "{$context} summary policy");
        }

        $readerItems = $summaryItemsByName($readerProvenance['zipSourceRecords']['items'], 'part');
        $compactItems = $summaryItemsByName($compactInventory['zipSourceRecords']['items'], 'path');
        foreach (['content.xml', 'Basic/Standard/Review.xml'] as $name) {
            $expected = $expectedSourceRecord($manifestEntriesByName[$name]);

            $assertSourceRecord($t, $readerItems[$name], $expected, "reader summary {$name}");
            $assertSourceRecord($t, $compactItems[$name], $expected, "compact summary {$name}");
            $t->same(false, array_key_exists('compressedDataSha256', $readerItems[$name]), "reader summary {$name} compressed hash omitted");
            $t->same(false, array_key_exists('compressedDataSha256', $compactItems[$name]), "compact summary {$name} compressed hash omitted");
        }

        $t->same($readerProvenance['zipSourceRecords'], $readerResult['document']->attr('manifest')['packageProvenance']['zipSourceRecords']);
    },
];
