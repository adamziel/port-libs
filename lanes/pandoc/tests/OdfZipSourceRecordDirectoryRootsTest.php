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
      <text:p>ZIP source record directory root review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="SourceRootBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Directory Root Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/source.png', 'data' => str_repeat('P', 512), 'compressionMethod' => 0, 'comment' => 'source-root-image'],
    ['name' => 'Pictures/extra.bin', 'data' => str_repeat('B', 64), 'compressionMethod' => 8],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
], 'odt zip source record directory root provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$sumByRoot = static function (array $parts, string $field): array {
    $sums = [];
    foreach ($parts as $part) {
        $root = is_string($part['zipPackageManifestDirectoryRoot'] ?? null) ? $part['zipPackageManifestDirectoryRoot'] : '/';
        $sums[$root] = ($sums[$root] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }
    ksort($sums, SORT_STRING);

    return $sums;
};

$sumForRoot = static function (array $parts, string $root, string $field): int {
    $sum = 0;
    foreach ($parts as $part) {
        if (($part['zipPackageManifestDirectoryRoot'] ?? null) !== $root) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
};

return [
    'summarizes ODT ZIP source records by package directory root' => static function (TestRunner $t) use ($buildPackage, $indexBy, $sumByRoot, $sumForRoot): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedRootCounts = [
            '/' => 4,
            'META-INF/' => 1,
            'Notes/' => 1,
            'Pictures/' => 2,
        ];
        $expectedRootBytes = $sumByRoot($compactInventory['parts'], 'zipSourceRecordBytes');
        $expectedLocalRecordBytes = array_sum($sumByRoot($compactInventory['parts'], 'zipLocalRecordBytes'));
        $expectedCentralRecordBytes = array_sum($sumByRoot($compactInventory['parts'], 'zipCentralDirectoryRecordBytes'));

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same(4, $handoff['packageZipSourceRecordDirectoryRootCount']);
            $t->same($expectedRootCounts, $handoff['packageZipSourceRecordDirectoryRootCounts']);
            $t->same($expectedRootBytes, $handoff['packageZipSourceRecordDirectoryRootBytes']);
            $t->same(8, $handoff['packageZipSourceRecordEntryCount']);
            $t->same(array_sum($expectedRootBytes), $handoff['packageZipSourceRecordByteLength']);
            $t->same($expectedLocalRecordBytes, $handoff['packageZipSourceRecordLocalRecordByteLength']);
            $t->same($expectedCentralRecordBytes, $handoff['packageZipSourceRecordCentralDirectoryRecordByteLength']);
            $t->same(0, $handoff['packageZipSourceRecordDataDescriptorEntryCount']);
        }

        $t->same($richProvenance['packageZipSourceRecordDirectoryRootCounts'], $documentProvenance['packageZipSourceRecordDirectoryRootCounts']);
        $t->same($compactInventory['packageZipSourceRecordDirectoryRootBytes'], $richProvenance['packageZipSourceRecordDirectoryRootBytes']);

        $compactRoots = $indexBy($compactInventory['packageZipSourceRecordDirectoryRoots'], 'directoryRoot');
        $richRoots = $indexBy($richProvenance['packageZipSourceRecordDirectoryRoots'], 'directoryRoot');
        foreach ([$compactRoots['Pictures/'], $richRoots['Pictures/']] as $pictures) {
            $t->same(2, $pictures['entryCount']);
            $t->same(['Pictures/extra.bin', 'Pictures/source.png'], $pictures['entryNames']);
            $t->same($sumForRoot($compactInventory['parts'], 'Pictures/', 'zipSourceRecordBytes'), $pictures['sourceRecordBytes']);
            $t->same($sumForRoot($compactInventory['parts'], 'Pictures/', 'zipLocalHeaderBytes'), $pictures['localHeaderBytes']);
            $t->same($sumForRoot($compactInventory['parts'], 'Pictures/', 'zipCompressedDataBytes'), $pictures['compressedDataBytes']);
            $t->same($sumForRoot($compactInventory['parts'], 'Pictures/', 'zipCentralDirectoryRecordBytes'), $pictures['centralDirectoryRecordBytes']);
            $t->same($sumForRoot($compactInventory['parts'], 'Pictures/', 'zipCentralDirectoryRawCommentBytes'), $pictures['centralDirectoryRawCommentBytes']);
            $t->same([0 => 1, 8 => 1], $pictures['compressionMethodCounts']);
            $t->same(['application/octet-stream' => 1, 'image/png' => 1], $pictures['manifestMediaTypeBaseCounts']);
            $t->same(['manifest-declared' => 2, 'media-resource' => 2], $pictures['roleCounts']);
            $t->same(2, $pictures['exposableEntryCount']);
            $t->same(0, $pictures['blockedEntryCount']);

            $largest = $pictures['largestSourceRecordEntry'];
            $t->same('Pictures/source.png', $largest['entryName']);
            $t->same('Pictures/', $largest['directoryRoot']);
            $t->same('Pictures/', $largest['packageDirectory']);
            $t->same('source.png', $largest['packageBasename']);
            $t->same(2, $largest['packagePathDepth']);
            $t->same(512, $largest['byteLength']);
            $t->same($compactInventory['parts']['Pictures/source.png']['zipSourceRecordBytes'], $largest['sourceRecordBytes']);
            $t->same(true, $largest['canExposeBytes']);
        }

        foreach ([$compactRoots['Notes/'], $richRoots['Notes/']] as $notes) {
            $t->same(1, $notes['entryCount']);
            $t->same(['Notes/private.txt'], $notes['entryNames']);
            $t->same(['undeclared-package-entry-no-bytes' => 1], $notes['byteExposurePolicyCounts']);
            $t->same(['undeclared-package-entry' => 1], $notes['roleCounts']);
            $t->same(0, $notes['exposableEntryCount']);
            $t->same(1, $notes['blockedEntryCount']);
            $t->same('Notes/private.txt', $notes['largestSourceRecordEntry']['entryName']);
        }
    },
];
