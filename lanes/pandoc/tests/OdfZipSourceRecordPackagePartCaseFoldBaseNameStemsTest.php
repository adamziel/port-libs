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
  <manifest:file-entry manifest:full-path="Pictures/Review.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Extras/Report.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record package part case-fold base-name stem review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="CaseFoldStemBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Package Part Case-fold Base-name Stem Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/Review.PNG', 'data' => str_repeat('R', 512), 'compressionMethod' => 0],
    ['name' => 'Thumbnails/review.png', 'data' => str_repeat('T', 64), 'compressionMethod' => 8],
    ['name' => 'Extras/Report.bin', 'data' => str_repeat('B', 160), 'compressionMethod' => 0],
    ['name' => 'Notes/report.txt', 'data' => str_repeat('N', 32), 'compressionMethod' => 0],
], 'odt zip source record package part case-fold base-name stem provenance');

return [
    'records mapped ODF ZIP source-record package part case-fold base-name stem case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json') ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['mappedOdfZipSourceRecordPackagePartCaseFoldBaseNameStemCases']);
        $t->same(116, $manifest['odfZipSourceRecordPackagePartCaseFoldBaseNameStemAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedOdfZipSourceRecordPackagePartCaseFoldBaseNameStemCases']
        );
        $t->same(
            116,
            $manifest['benchmarkDenominator']['breakdown']['odfZipSourceRecordPackagePartCaseFoldBaseNameStemAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedOdfZipSourceRecordPackagePartCaseFoldBaseNameStemCases']
        );
        $t->same(
            116,
            $manifest['benchmarkDenominator']['inventory']['odfZipSourceRecordPackagePartCaseFoldBaseNameStemAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedOdfZipSourceRecordPackagePartCaseFoldBaseNameStemCases']);
        $t->same(116, $manifest['inventory']['odfZipSourceRecordPackagePartCaseFoldBaseNameStemAssertions']);
    },

    'summarizes ODT ZIP source records by package part case-fold base-name stems' => static function (TestRunner $t) use ($buildPackage): void {
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
        $expectedStemBytes = odf_zip_source_record_case_fold_base_name_stem_sums(
            $compactInventory['parts'],
            'zipSourceRecordBytes'
        );

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(7, $handoff['packageZipSourceRecordPackagePartCaseFoldBaseNameStemCount']);
            $t->same($expectedStemCounts, $handoff['packageZipSourceRecordPackagePartCaseFoldBaseNameStemCounts']);
            $t->same($expectedStemBytes, $handoff['packageZipSourceRecordPackagePartCaseFoldBaseNameStemBytes']);
            $t->same(2, $handoff['packageZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStemCount']);
            $t->same(4, $handoff['packageZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStemEntryCount']);
            $t->same(['report', 'review'], $handoff['packageZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStems']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartCaseFoldBaseNameStemDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartCaseFoldBaseNameStemIssueEntryCount']);
        }

        $t->same(
            $compactInventory['packageZipSourceRecordPackagePartCaseFoldBaseNameStemBytes'],
            $richProvenance['packageZipSourceRecordPackagePartCaseFoldBaseNameStemBytes']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordPackagePartCaseFoldBaseNameStemCounts'],
            $documentProvenance['packageZipSourceRecordPackagePartCaseFoldBaseNameStemCounts']
        );

        $compactStems = odf_zip_source_record_case_fold_base_name_stem_index_by(
            $compactInventory['packageZipSourceRecordPackagePartCaseFoldBaseNameStems'],
            'caseFoldBaseNameStem'
        );
        $richStems = odf_zip_source_record_case_fold_base_name_stem_index_by(
            $richProvenance['packageZipSourceRecordPackagePartCaseFoldBaseNameStems'],
            'caseFoldBaseNameStem'
        );

        foreach ([$compactStems['review'], $richStems['review']] as $review) {
            $t->same(2, $review['entryCount']);
            $t->same(2, $review['baseNameStemVariantCount']);
            $t->same(2, $review['baseNameVariantCount']);
            $t->same(1, $review['extensionVariantCount']);
            $t->same(['Review' => 1, 'review' => 1], $review['baseNameStemCounts']);
            $t->same(['Review.PNG' => 1, 'review.png' => 1], $review['baseNameCounts']);
            $t->same(['png' => 2], $review['partExtensionCounts']);
            $t->same(['Pictures/' => 1, 'Thumbnails/' => 1], $review['directoryRootCounts']);
            $t->same(['image/png' => 2], $review['manifestMediaTypeBaseCounts']);
            $t->same([0 => 1, 8 => 1], $review['compressionMethodCounts']);
            $t->same([
                'manifest-declared' => 2,
                'media-resource' => 1,
                'package-thumbnail' => 1,
            ], $review['roleCounts']);
            $t->same(['Pictures/Review.PNG', 'Thumbnails/review.png'], $review['entryNames']);
            $t->same(
                odf_zip_source_record_case_fold_base_name_stem_sum_for_stem(
                    $compactInventory['parts'],
                    'review',
                    'zipSourceRecordBytes'
                ),
                $review['sourceRecordBytes']
            );
            $t->same('Pictures/Review.PNG', $review['largestSourceRecordEntry']['entryName']);
            $t->same('review', $review['largestSourceRecordEntry']['caseFoldBaseNameStem']);
            $t->same('Review', $review['largestSourceRecordEntry']['baseNameStem']);
            $t->same(false, array_key_exists('contents', $review['largestSourceRecordEntry']));
        }

        foreach ([$compactStems['report'], $richStems['report']] as $report) {
            $t->same(2, $report['entryCount']);
            $t->same(2, $report['baseNameStemVariantCount']);
            $t->same(2, $report['baseNameVariantCount']);
            $t->same(2, $report['extensionVariantCount']);
            $t->same(['Report' => 1, 'report' => 1], $report['baseNameStemCounts']);
            $t->same(['Report.bin' => 1, 'report.txt' => 1], $report['baseNameCounts']);
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
            $t->same(['Extras/Report.bin', 'Notes/report.txt'], $report['entryNames']);
            $t->same('Extras/Report.bin', $report['largestSourceRecordEntry']['entryName']);
            $t->same('report', $report['largestSourceRecordEntry']['caseFoldBaseNameStem']);
            $t->same('Report', $report['largestSourceRecordEntry']['baseNameStem']);
            $t->same(true, $report['largestSourceRecordEntry']['canExposeBytes']);
        }
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_case_fold_base_name_stem_index_by(array $items, string $key): array
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
function odf_zip_source_record_case_fold_base_name_stem_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $stem = is_string($part['zipPackageManifestPackagePartCaseFoldBaseNameStem'] ?? null)
            ? $part['zipPackageManifestPackagePartCaseFoldBaseNameStem']
            : strtolower((string) ($part['zipPackageManifestPackagePartBaseNameStem'] ?? ''));
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
function odf_zip_source_record_case_fold_base_name_stem_sum_for_stem(
    array $inventory,
    string $stem,
    string $field
): int {
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipPackageManifestPackagePartCaseFoldBaseNameStem'] ?? null) !== $stem) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}
