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
  <manifest:file-entry manifest:full-path="pictures/thumb.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Objects/Pictures/readme.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Objects/pictures/icon.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Scripts/no-extension" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record directory base-name review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="DirectoryBaseNameBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Directory Base-name Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/source.png', 'data' => str_repeat('P', 512), 'compressionMethod' => 0],
    ['name' => 'pictures/thumb.png', 'data' => str_repeat('T', 64), 'compressionMethod' => 8],
    ['name' => 'Objects/Pictures/readme.txt', 'data' => str_repeat('R', 31), 'compressionMethod' => 8],
    ['name' => 'Objects/pictures/icon.png', 'data' => str_repeat('I', 128), 'compressionMethod' => 0],
    ['name' => 'Scripts/no-extension', 'data' => 'macro source bytes', 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
], 'odt zip source record directory base-name provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT ZIP source records by package directory base name' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedExactCounts = [
            'META-INF' => 1,
            'Notes' => 1,
            'Pictures' => 2,
            'Scripts' => 1,
            'pictures' => 2,
        ];
        $expectedCaseFoldCounts = [
            'meta-inf' => 1,
            'notes' => 1,
            'pictures' => 4,
            'scripts' => 1,
        ];
        $expectedExactBytes = odf_zip_source_record_directory_base_name_sums($compactInventory['parts'], false);
        $expectedCaseFoldBytes = odf_zip_source_record_directory_base_name_sums($compactInventory['parts'], true);

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packageZipSourceRecordDirectoryBaseNameCount']);
            $t->same($expectedExactCounts, $handoff['packageZipSourceRecordDirectoryBaseNameCounts']);
            $t->same($expectedExactBytes, $handoff['packageZipSourceRecordDirectoryBaseNameBytes']);
            $t->same(2, $handoff['packageZipSourceRecordDuplicateDirectoryBaseNameCount']);
            $t->same(4, $handoff['packageZipSourceRecordDuplicateDirectoryBaseNameEntryCount']);
            $t->same(['Pictures', 'pictures'], $handoff['packageZipSourceRecordDuplicateDirectoryBaseNames']);
            $t->same(0, $handoff['packageZipSourceRecordDirectoryBaseNameDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordDirectoryBaseNameIssueEntryCount']);

            $t->same(4, $handoff['packageZipSourceRecordCaseFoldDirectoryBaseNameCount']);
            $t->same($expectedCaseFoldCounts, $handoff['packageZipSourceRecordCaseFoldDirectoryBaseNameCounts']);
            $t->same($expectedCaseFoldBytes, $handoff['packageZipSourceRecordCaseFoldDirectoryBaseNameBytes']);
            $t->same(1, $handoff['packageZipSourceRecordDuplicateCaseFoldDirectoryBaseNameCount']);
            $t->same(4, $handoff['packageZipSourceRecordDuplicateCaseFoldDirectoryBaseNameEntryCount']);
            $t->same(['pictures'], $handoff['packageZipSourceRecordDuplicateCaseFoldDirectoryBaseNames']);
            $t->same(0, $handoff['packageZipSourceRecordCaseFoldDirectoryBaseNameDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordCaseFoldDirectoryBaseNameIssueEntryCount']);
        }

        $t->same(
            $compactInventory['packageZipSourceRecordDirectoryBaseNameCounts'],
            $richProvenance['packageZipSourceRecordDirectoryBaseNameCounts']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordCaseFoldDirectoryBaseNameCounts'],
            $documentProvenance['packageZipSourceRecordCaseFoldDirectoryBaseNameCounts']
        );

        $exactGroups = $indexBy($richProvenance['packageZipSourceRecordDirectoryBaseNames'], 'directoryBaseName');
        $caseFoldGroups = $indexBy($richProvenance['packageZipSourceRecordCaseFoldDirectoryBaseNames'], 'caseFoldDirectoryBaseName');

        $pictures = $exactGroups['Pictures'];
        $t->same(2, $pictures['entryCount']);
        $t->same(2, $pictures['fileEntryCount']);
        $t->same(0, $pictures['directoryEntryCount']);
        $t->same(['Objects/Pictures/readme.txt', 'Pictures/source.png'], $pictures['entryNames']);
        $t->same(['Objects/' => 1, 'Pictures/' => 1], $pictures['directoryRootCounts']);
        $t->same(['Objects/Pictures/' => 1, 'Pictures/' => 1], $pictures['packageDirectoryCounts']);
        $t->same([0 => 1, 8 => 1], $pictures['compressionMethodCounts']);
        $t->same(['image/png' => 1, 'text/plain' => 1], $pictures['manifestMediaTypeBaseCounts']);
        $t->same(2, $pictures['roleCounts']['manifest-declared'] ?? 0);
        $t->same(1, $pictures['roleCounts']['media-resource'] ?? 0);
        $t->same(
            odf_zip_source_record_directory_base_name_sum_for_key($compactInventory['parts'], 'Pictures', false, 'zipSourceRecordBytes'),
            $pictures['sourceRecordBytes']
        );
        $t->same('Pictures/source.png', $pictures['largestSourceRecordEntry']['entryName']);
        $t->same('Pictures', $pictures['largestSourceRecordEntry']['directoryBaseName']);
        $t->same('pictures', $pictures['largestSourceRecordEntry']['caseFoldDirectoryBaseName']);
        $t->same(true, $pictures['largestSourceRecordEntry']['canExposeBytes']);

        $caseFoldPictures = $caseFoldGroups['pictures'];
        $t->same(4, $caseFoldPictures['entryCount']);
        $t->same(2, $caseFoldPictures['directoryBaseNameVariantCount']);
        $t->same(['Pictures' => 2, 'pictures' => 2], $caseFoldPictures['directoryBaseNameCounts']);
        $t->same([
            'Objects/Pictures/' => 1,
            'Objects/pictures/' => 1,
            'Pictures/' => 1,
            'pictures/' => 1,
        ], $caseFoldPictures['packageDirectoryCounts']);
        $t->same(['Objects/' => 2, 'Pictures/' => 1, 'pictures/' => 1], $caseFoldPictures['directoryRootCounts']);
        $t->same([0 => 2, 8 => 2], $caseFoldPictures['compressionMethodCounts']);
        $t->same(['image/png' => 3, 'text/plain' => 1], $caseFoldPictures['manifestMediaTypeBaseCounts']);
        $t->same([
            'Objects/Pictures/readme.txt',
            'Objects/pictures/icon.png',
            'Pictures/source.png',
            'pictures/thumb.png',
        ], $caseFoldPictures['entryNames']);
        $t->same(
            odf_zip_source_record_directory_base_name_sum_for_key($compactInventory['parts'], 'pictures', true, 'zipSourceRecordBytes'),
            $caseFoldPictures['sourceRecordBytes']
        );
        $t->same('Pictures/source.png', $caseFoldPictures['largestSourceRecordEntry']['entryName']);
        $t->same('Pictures', $caseFoldPictures['largestSourceRecordEntry']['directoryBaseName']);
        $t->same('pictures', $caseFoldPictures['largestSourceRecordEntry']['caseFoldDirectoryBaseName']);

        $notes = $exactGroups['Notes'];
        $t->same(1, $notes['entryCount']);
        $t->same(['Notes/private.txt'], $notes['entryNames']);
        $t->same(['undeclared-package-entry-no-bytes' => 1], $notes['byteExposurePolicyCounts']);
        $t->same(0, $notes['exposableEntryCount']);
        $t->same(1, $notes['blockedEntryCount']);
        $t->same('Notes/private.txt', $notes['largestSourceRecordEntry']['entryName']);
    },
];

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_directory_base_name_sums(array $inventory, bool $caseFold): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        $directoryBaseName = is_string($part['packageDirectoryBaseName'] ?? null)
            ? $part['packageDirectoryBaseName']
            : null;
        if ($directoryBaseName === null || $directoryBaseName === '') {
            continue;
        }

        $key = $caseFold ? strtolower($directoryBaseName) : $directoryBaseName;
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part['zipSourceRecordBytes'] ?? null) ? $part['zipSourceRecordBytes'] : 0);
    }
    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function odf_zip_source_record_directory_base_name_sum_for_key(array $inventory, string $key, bool $caseFold, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        $directoryBaseName = is_string($part['packageDirectoryBaseName'] ?? null)
            ? $part['packageDirectoryBaseName']
            : null;
        if ($directoryBaseName === null || $directoryBaseName === '') {
            continue;
        }

        $partKey = $caseFold ? strtolower($directoryBaseName) : $directoryBaseName;
        if ($partKey !== $key) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}
