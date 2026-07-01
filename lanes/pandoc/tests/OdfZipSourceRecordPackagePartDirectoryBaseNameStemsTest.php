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
  <manifest:file-entry manifest:full-path="Pictures.assets/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="pictures.raw/thumb.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Objects/Pictures.assets/readme.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Objects/pictures/icon.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record package part directory base-name stem review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="SourceRecordStemBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Package Part Directory Base-name Stem Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures.assets/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'pictures.raw/thumb.png', 'data' => 'thumbdata', 'compressionMethod' => 8],
    ['name' => 'Objects/Pictures.assets/readme.txt', 'data' => str_repeat('R', 256), 'compressionMethod' => 8],
    ['name' => 'Objects/pictures/icon.png', 'data' => str_repeat('I', 17), 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMB', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt zip source record package part directory base-name stem provenance');

return [
    'records mapped ODF ZIP source-record directory base-name stem case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json') ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['mappedOdfZipSourceRecordPackagePartDirectoryBaseNameStemCases']);
        $t->same(166, $manifest['odfZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedOdfZipSourceRecordPackagePartDirectoryBaseNameStemCases']
        );
        $t->same(
            166,
            $manifest['benchmarkDenominator']['breakdown']['odfZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedOdfZipSourceRecordPackagePartDirectoryBaseNameStemCases']
        );
        $t->same(
            166,
            $manifest['benchmarkDenominator']['inventory']['odfZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedOdfZipSourceRecordPackagePartDirectoryBaseNameStemCases']);
        $t->same(166, $manifest['inventory']['odfZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']);
    },

    'summarizes ODT ZIP source records by package part directory base-name stems' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedStemCounts = [
            'META-INF' => 1,
            'Notes' => 1,
            'Pictures' => 2,
            'Thumbnails' => 1,
            'pictures' => 2,
        ];
        $expectedCaseFoldStemCounts = [
            'meta-inf' => 1,
            'notes' => 1,
            'pictures' => 4,
            'thumbnails' => 1,
        ];
        $expectedStemBytes = odf_zip_source_record_directory_base_name_stem_sums(
            $compactInventory['parts'],
            'packageDirectoryBaseNameStem',
            'zipSourceRecordBytes'
        );
        $expectedCaseFoldStemBytes = odf_zip_source_record_directory_base_name_stem_sums(
            $compactInventory['parts'],
            'packageCaseFoldDirectoryBaseNameStem',
            'zipSourceRecordBytes'
        );

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packageZipSourceRecordPackagePartDirectoryBaseNameStemCount']);
            $t->same($expectedStemCounts, $handoff['packageZipSourceRecordPackagePartDirectoryBaseNameStemCounts']);
            $t->same($expectedStemBytes, $handoff['packageZipSourceRecordPackagePartDirectoryBaseNameStemBytes']);
            $t->same(2, $handoff['packageZipSourceRecordDuplicatePackagePartDirectoryBaseNameStemCount']);
            $t->same(4, $handoff['packageZipSourceRecordDuplicatePackagePartDirectoryBaseNameStemEntryCount']);
            $t->same(['Pictures', 'pictures'], $handoff['packageZipSourceRecordDuplicatePackagePartDirectoryBaseNameStems']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartDirectoryBaseNameStemDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartDirectoryBaseNameStemIssueEntryCount']);

            $t->same(4, $handoff['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCount']);
            $t->same($expectedCaseFoldStemCounts, $handoff['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCounts']);
            $t->same($expectedCaseFoldStemBytes, $handoff['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemBytes']);
            $t->same(1, $handoff['packageZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStemCount']);
            $t->same(4, $handoff['packageZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStemEntryCount']);
            $t->same(['pictures'], $handoff['packageZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStems']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemIssueEntryCount']);
        }

        $compactStems = odf_zip_source_record_directory_base_name_stem_index_by(
            $compactInventory['packageZipSourceRecordPackagePartDirectoryBaseNameStems'],
            'directoryBaseNameStem'
        );
        $richStems = odf_zip_source_record_directory_base_name_stem_index_by(
            $richProvenance['packageZipSourceRecordPackagePartDirectoryBaseNameStems'],
            'directoryBaseNameStem'
        );
        $compactCaseFoldStems = odf_zip_source_record_directory_base_name_stem_index_by(
            $compactInventory['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStems'],
            'caseFoldDirectoryBaseNameStem'
        );
        $richCaseFoldStems = odf_zip_source_record_directory_base_name_stem_index_by(
            $richProvenance['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStems'],
            'caseFoldDirectoryBaseNameStem'
        );

        foreach ([$compactStems['Pictures'], $richStems['Pictures']] as $picturesStem) {
            $t->same(2, $picturesStem['entryCount']);
            $t->same(2, $picturesStem['directoryCount']);
            $t->same(1, $picturesStem['directoryBaseNameVariantCount']);
            $t->same(2, $picturesStem['baseNameVariantCount']);
            $t->same(2, $picturesStem['extensionVariantCount']);
            $t->same(['Pictures.assets' => 2], $picturesStem['directoryBaseNameCounts']);
            $t->same(['Objects/Pictures.assets/' => 1, 'Pictures.assets/' => 1], $picturesStem['directoryCounts']);
            $t->same(['Objects/' => 1, '/' => 1], $picturesStem['directoryRootCounts']);
            $t->same(['HERO.PNG' => 1, 'readme.txt' => 1], $picturesStem['baseNameCounts']);
            $t->same(['png' => 1, 'txt' => 1], $picturesStem['partExtensionCounts']);
            $t->same(['image/png' => 1, 'text/plain' => 1], $picturesStem['manifestMediaTypeBaseCounts']);
            $t->same([0 => 1, 8 => 1], $picturesStem['compressionMethodCounts']);
            $t->same(['manifest-declared' => 2, 'media-resource' => 1], $picturesStem['roleCounts']);
            $t->same(['Objects/Pictures.assets/readme.txt', 'Pictures.assets/HERO.PNG'], $picturesStem['entryNames']);
            $t->same(
                odf_zip_source_record_directory_base_name_stem_sum_for_stem(
                    $compactInventory['parts'],
                    'packageDirectoryBaseNameStem',
                    'Pictures',
                    'zipSourceRecordBytes'
                ),
                $picturesStem['sourceRecordBytes']
            );

            $largest = $picturesStem['largestSourceRecordEntry'];
            $t->same('Objects/Pictures.assets/readme.txt', $largest['entryName']);
            $t->same('Objects/Pictures.assets/', $largest['packageDirectory']);
            $t->same('Pictures.assets', $largest['directoryBaseName']);
            $t->same('Pictures', $largest['directoryBaseNameStem']);
            $t->same('pictures', $largest['caseFoldDirectoryBaseNameStem']);
            $t->same('readme.txt', $largest['packageBasename']);
            $t->same('txt', $largest['packagePartExtension']);
            $t->same(false, array_key_exists('contents', $largest));
        }

        foreach ([$compactCaseFoldStems['pictures'], $richCaseFoldStems['pictures']] as $picturesStem) {
            $t->same(4, $picturesStem['entryCount']);
            $t->same(4, $picturesStem['directoryCount']);
            $t->same(2, $picturesStem['directoryBaseNameStemVariantCount']);
            $t->same(3, $picturesStem['directoryBaseNameVariantCount']);
            $t->same(4, $picturesStem['baseNameVariantCount']);
            $t->same(2, $picturesStem['extensionVariantCount']);
            $t->same(['Pictures' => 2, 'pictures' => 2], $picturesStem['directoryBaseNameStemCounts']);
            $t->same(['Pictures.assets' => 2, 'pictures' => 1, 'pictures.raw' => 1], $picturesStem['directoryBaseNameCounts']);
            $t->same([
                'Objects/Pictures.assets/' => 1,
                'Objects/pictures/' => 1,
                'Pictures.assets/' => 1,
                'pictures.raw/' => 1,
            ], $picturesStem['directoryCounts']);
            $t->same(['image/png' => 3, 'text/plain' => 1], $picturesStem['manifestMediaTypeBaseCounts']);
            $t->same(['manifest-declared' => 4, 'media-resource' => 3], $picturesStem['roleCounts']);
            $t->same([
                'Objects/Pictures.assets/readme.txt',
                'Objects/pictures/icon.png',
                'Pictures.assets/HERO.PNG',
                'pictures.raw/thumb.png',
            ], $picturesStem['entryNames']);
            $t->same('Objects/Pictures.assets/readme.txt', $picturesStem['largestSourceRecordEntry']['entryName']);
            $t->same('pictures', $picturesStem['largestSourceRecordEntry']['caseFoldDirectoryBaseNameStem']);
        }

        $t->same(
            $compactInventory['packageZipSourceRecordPackagePartDirectoryBaseNameStemCounts'],
            $richProvenance['packageZipSourceRecordPackagePartDirectoryBaseNameStemCounts']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCounts'],
            $documentProvenance['packageZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCounts']
        );
        $t->same(
            'Pictures',
            $compactInventory['parts']['Objects/Pictures.assets/readme.txt']['packageDirectoryBaseNameStem']
        );
        $t->same(
            'pictures',
            $richProvenance['parts']['Objects/Pictures.assets/readme.txt']['packageCaseFoldDirectoryBaseNameStem']
        );
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_directory_base_name_stem_index_by(array $items, string $key): array
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
function odf_zip_source_record_directory_base_name_stem_sums(array $inventory, string $stemField, string $sumField): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $stem = is_string($part[$stemField] ?? null) ? $part[$stemField] : '';
        if ($stem === '') {
            continue;
        }

        $sums[$stem] = ($sums[$stem] ?? 0) + (is_int($part[$sumField] ?? null) ? $part[$sumField] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function odf_zip_source_record_directory_base_name_stem_sum_for_stem(
    array $inventory,
    string $stemField,
    string $stem,
    string $sumField
): int {
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part[$stemField] ?? null) !== $stem) {
            continue;
        }

        $sum += is_int($part[$sumField] ?? null) ? $part[$sumField] : 0;
    }

    return $sum;
}
