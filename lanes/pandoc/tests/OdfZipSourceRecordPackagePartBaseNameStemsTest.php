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
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Extras/report.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record package part base-name stem review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="StemBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Package Part Base-name Stem Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => str_repeat('R', 512), 'compressionMethod' => 0],
    ['name' => 'Thumbnails/review.png', 'data' => str_repeat('T', 64), 'compressionMethod' => 8],
    ['name' => 'Extras/report.bin', 'data' => str_repeat('B', 160), 'compressionMethod' => 0],
    ['name' => 'Notes/report.txt', 'data' => str_repeat('N', 32), 'compressionMethod' => 0],
], 'odt zip source record package part base-name stem provenance');

return [
    'summarizes ODT ZIP source records by package part base-name stem' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedStemCounts = [
            'content' => 1,
            'manifest' => 1,
            'meta' => 1,
            'mimetype' => 1,
            'report' => 2,
            'review' => 2,
            'styles' => 1,
        ];
        $expectedStemBytes = odf_zip_source_record_base_name_stem_sums($compactInventory['parts'], 'zipSourceRecordBytes');

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(7, $handoff['packageZipSourceRecordPackagePartBaseNameStemCount']);
            $t->same($expectedStemCounts, $handoff['packageZipSourceRecordPackagePartBaseNameStemCounts']);
            $t->same($expectedStemBytes, $handoff['packageZipSourceRecordPackagePartBaseNameStemBytes']);
            $t->same(2, $handoff['packageZipSourceRecordDuplicatePackagePartBaseNameStemCount']);
            $t->same(4, $handoff['packageZipSourceRecordDuplicatePackagePartBaseNameStemEntryCount']);
            $t->same(['report', 'review'], $handoff['packageZipSourceRecordDuplicatePackagePartBaseNameStems']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartBaseNameStemDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartBaseNameStemIssueEntryCount']);
        }

        $t->same(
            $compactInventory['packageZipSourceRecordPackagePartBaseNameStemBytes'],
            $richProvenance['packageZipSourceRecordPackagePartBaseNameStemBytes']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordPackagePartBaseNameStemCounts'],
            $documentProvenance['packageZipSourceRecordPackagePartBaseNameStemCounts']
        );

        $compactStems = odf_zip_source_record_base_name_stem_index_by(
            $compactInventory['packageZipSourceRecordPackagePartBaseNameStems'],
            'baseNameStem'
        );
        $richStems = odf_zip_source_record_base_name_stem_index_by(
            $richProvenance['packageZipSourceRecordPackagePartBaseNameStems'],
            'baseNameStem'
        );

        foreach ([$compactStems['review'], $richStems['review']] as $review) {
            $t->same(2, $review['entryCount']);
            $t->same(1, $review['baseNameVariantCount']);
            $t->same(1, $review['extensionVariantCount']);
            $t->same(['review.png' => 2], $review['baseNameCounts']);
            $t->same(['png' => 2], $review['partExtensionCounts']);
            $t->same(['Pictures/' => 1, 'Thumbnails/' => 1], $review['directoryRootCounts']);
            $t->same(['image/png' => 2], $review['manifestMediaTypeBaseCounts']);
            $t->same([0 => 1, 8 => 1], $review['compressionMethodCounts']);
            $t->same([
                'manifest-declared' => 2,
                'media-resource' => 1,
                'package-thumbnail' => 1,
            ], $review['roleCounts']);
            $t->same(['Pictures/review.png', 'Thumbnails/review.png'], $review['entryNames']);
            $t->same(
                odf_zip_source_record_base_name_stem_sum_for_stem($compactInventory['parts'], 'review', 'zipSourceRecordBytes'),
                $review['sourceRecordBytes']
            );
            $t->same(
                odf_zip_source_record_base_name_stem_sum_for_stem($compactInventory['parts'], 'review', 'zipCentralDirectoryRecordBytes'),
                $review['centralDirectoryRecordBytes']
            );
            $t->same('Pictures/review.png', $review['largestSourceRecordEntry']['entryName']);
            $t->same('review', $review['largestSourceRecordEntry']['baseNameStem']);
            $t->same(false, array_key_exists('contents', $review['largestSourceRecordEntry']));
        }

        foreach ([$compactStems['report'], $richStems['report']] as $report) {
            $t->same(2, $report['entryCount']);
            $t->same(2, $report['baseNameVariantCount']);
            $t->same(2, $report['extensionVariantCount']);
            $t->same(['report.bin' => 1, 'report.txt' => 1], $report['baseNameCounts']);
            $t->same(['bin' => 1, 'txt' => 1], $report['partExtensionCounts']);
            $t->same(['Extras/' => 1, 'Notes/' => 1], $report['directoryRootCounts']);
            $t->same([
                '(missing)' => 1,
                'application/octet-stream' => 1,
            ], $report['manifestMediaTypeBaseCounts']);
            $t->same([0 => 2], $report['compressionMethodCounts']);
            $t->same([
                'manifest-declared' => 1,
                'undeclared-package-entry' => 1,
            ], $report['roleCounts']);
            $t->same(['Extras/report.bin', 'Notes/report.txt'], $report['entryNames']);
            $t->same('Extras/report.bin', $report['largestSourceRecordEntry']['entryName']);
            $t->same('report', $report['largestSourceRecordEntry']['baseNameStem']);
            $t->same(true, $report['largestSourceRecordEntry']['canExposeBytes']);
        }
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_base_name_stem_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_base_name_stem_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $stem = is_string($part['zipPackageManifestPackagePartBaseNameStem'] ?? null)
            ? $part['zipPackageManifestPackagePartBaseNameStem']
            : '';
        if ($stem === '') {
            continue;
        }

        $sums[$stem] = ($sums[$stem] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }
    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function odf_zip_source_record_base_name_stem_sum_for_stem(array $inventory, string $stem, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipPackageManifestPackagePartBaseNameStem'] ?? null) !== $stem) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}
