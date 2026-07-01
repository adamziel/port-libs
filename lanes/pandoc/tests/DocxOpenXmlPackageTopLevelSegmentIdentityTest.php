<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records docx package top-level segment identity mapped case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedDocxPackageTopLevelSegmentIdentityCases'] ?? null);
        $t->same(79, $manifest['docxPackageTopLevelSegmentIdentityAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxPackageTopLevelSegmentIdentityCases'] ?? null);
        $t->same(79, $manifest['benchmarkDenominator']['breakdown']['docxPackageTopLevelSegmentIdentityAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedDocxPackageTopLevelSegmentIdentityCases'] ?? null);
        $t->same(79, $manifest['benchmarkDenominator']['inventory']['docxPackageTopLevelSegmentIdentityAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedDocxPackageTopLevelSegmentIdentityCases'] ?? null);
        $t->same(79, $manifest['inventory']['docxPackageTopLevelSegmentIdentityAssertions'] ?? null);
    },

    'carries docx package top-level segment summaries through identities' => static function (TestRunner $t): void {
        $parts = docx_package_top_level_segment_identity_fixture_parts();
        $directDocument = (new DocxOpenXmlReader())->readPackage($parts);
        $directDocx = $directDocument->attr('docx');
        $directPackage = $directDocx['packageProvenance'];
        $directSummary = $directPackage['summary'];
        $directIdentity = $directPackage['packageIdentity'];
        $directDocumentIdentity = $directPackage['documentPackageIdentity'];

        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(ZipPackage::fromParts(
            docx_package_top_level_segment_identity_zip_parts(),
            'docx package top-level segment identity'
        ));
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedTopLevelCounts = [
            'WORD' => 1,
            'Word' => 2,
            '[Content_Types].xml' => 1,
            '_rels' => 1,
            'customXml' => 1,
            'docProps' => 1,
            'root-note.xml' => 1,
            'word' => 3,
        ];
        $expectedCaseFoldTopLevelCounts = [
            '[content_types].xml' => 1,
            '_rels' => 1,
            'customxml' => 1,
            'docprops' => 1,
            'root-note.xml' => 1,
            'word' => 6,
        ];

        $directSegments = docx_package_top_level_segment_identity_index_by(
            $directIdentity['packageTopLevelSegments'],
            'topLevelSegment'
        );
        $directCaseFoldSegments = docx_package_top_level_segment_identity_index_by(
            $directIdentity['packageCaseFoldTopLevelSegments'],
            'caseFoldTopLevelSegment'
        );
        $directEntries = docx_package_top_level_segment_identity_entries_by_part(
            $directIdentity['packageEntries']
        );

        $t->same('DOCX package top-level segment identity.', $directDocument->children[0]->attr('text'));
        $t->same($directIdentity, $directDocx['packageIdentity']);
        $t->same(8, $directSummary['partTopLevelSegmentCount']);
        $t->same($expectedTopLevelCounts, $directSummary['partTopLevelSegmentCounts']);
        $t->same($directSummary['partTopLevelSegmentCount'], $directIdentity['packageTopLevelSegmentCount']);
        $t->same($directSummary['partTopLevelSegmentCounts'], $directIdentity['packageTopLevelSegmentCounts']);
        $t->same($directSummary['partTopLevelSegments'], $directIdentity['packageTopLevelSegments']);
        $t->same(2, $directSummary['duplicatePartTopLevelSegmentCount']);
        $t->same(5, $directSummary['duplicatePartTopLevelSegmentPartCount']);
        $t->same(['Word', 'word'], $directSummary['duplicatePartTopLevelSegments']);
        $t->same($directSummary['duplicatePartTopLevelSegmentCount'], $directIdentity['duplicatePackageTopLevelSegmentCount']);
        $t->same($directSummary['duplicatePartTopLevelSegments'], $directIdentity['duplicatePackageTopLevelSegments']);
        $t->same($expectedTopLevelCounts, $directDocumentIdentity['packageTopLevelSegmentCounts']);
        $t->same($directIdentity['packageTopLevelSegmentCount'], $directSummary['packageIdentityPackageTopLevelSegmentCount']);
        $t->same($directIdentity['packageTopLevelSegmentCounts'], $directSummary['packageIdentityPackageTopLevelSegmentCounts']);
        $t->same(
            $directDocumentIdentity['packageTopLevelSegmentCount'],
            $directSummary['documentPackageIdentityPackageTopLevelSegmentCount']
        );
        $t->same(
            $directDocumentIdentity['packageTopLevelSegmentCounts'],
            $directSummary['documentPackageIdentityPackageTopLevelSegmentCounts']
        );

        $t->same(6, $directSummary['partCaseFoldTopLevelSegmentCount']);
        $t->same($expectedCaseFoldTopLevelCounts, $directSummary['partCaseFoldTopLevelSegmentCounts']);
        $t->same(
            $directSummary['partCaseFoldTopLevelSegmentCount'],
            $directIdentity['packageCaseFoldTopLevelSegmentCount']
        );
        $t->same(
            $directSummary['partCaseFoldTopLevelSegmentCounts'],
            $directIdentity['packageCaseFoldTopLevelSegmentCounts']
        );
        $t->same($directSummary['partCaseFoldTopLevelSegments'], $directIdentity['packageCaseFoldTopLevelSegments']);
        $t->same(1, $directSummary['duplicatePartCaseFoldTopLevelSegmentCount']);
        $t->same(6, $directSummary['duplicatePartCaseFoldTopLevelSegmentPartCount']);
        $t->same(['word'], $directSummary['duplicatePartCaseFoldTopLevelSegments']);
        $t->same(
            $directSummary['duplicatePartCaseFoldTopLevelSegmentCount'],
            $directIdentity['duplicatePackageCaseFoldTopLevelSegmentCount']
        );
        $t->same(
            $directSummary['duplicatePartCaseFoldTopLevelSegments'],
            $directIdentity['duplicatePackageCaseFoldTopLevelSegments']
        );
        $t->same($expectedCaseFoldTopLevelCounts, $directDocumentIdentity['packageCaseFoldTopLevelSegmentCounts']);
        $t->same(
            $directIdentity['packageCaseFoldTopLevelSegmentCount'],
            $directSummary['packageIdentityPackageCaseFoldTopLevelSegmentCount']
        );
        $t->same(
            $directIdentity['packageCaseFoldTopLevelSegmentCounts'],
            $directSummary['packageIdentityPackageCaseFoldTopLevelSegmentCounts']
        );
        $t->same(
            $directDocumentIdentity['packageCaseFoldTopLevelSegmentCount'],
            $directSummary['documentPackageIdentityPackageCaseFoldTopLevelSegmentCount']
        );
        $t->same(
            $directDocumentIdentity['packageCaseFoldTopLevelSegmentCounts'],
            $directSummary['documentPackageIdentityPackageCaseFoldTopLevelSegmentCounts']
        );
        $t->same([
            'WORD',
            'Word',
            '[Content_Types].xml',
            '_rels',
            'customXml',
            'docProps',
            'root-note.xml',
            'word',
        ], array_column($directIdentity['packageTopLevelSegments'], 'topLevelSegment'));
        $t->same([
            '[content_types].xml',
            '_rels',
            'customxml',
            'docprops',
            'root-note.xml',
            'word',
        ], array_column($directIdentity['packageCaseFoldTopLevelSegments'], 'caseFoldTopLevelSegment'));

        $wordSegment = $directSegments['Word'];
        $t->same(2, $wordSegment['partCount']);
        $t->same(strlen($parts['Word/media/upper.png']) + strlen($parts['Word/media/raw.bin']), $wordSegment['byteLength']);
        $t->same(['Word/media'], $wordSegment['directories']);
        $t->same(['default' => 1, 'missing' => 1], $wordSegment['contentTypeSourceCounts']);
        $t->same(['document-relationship-target' => 1, 'package-part' => 1], $wordSegment['roleCounts']);
        $t->same('Word/media/raw.bin', $wordSegment['largestPart']['partName']);
        $t->same('Word', $wordSegment['largestPart']['topLevelSegment']);
        $t->same(800, $wordSegment['largestPart']['bytes']);
        $t->same(false, array_key_exists('contents', $wordSegment['largestPart']));

        $caseFoldWord = $directCaseFoldSegments['word'];
        $t->same(6, $caseFoldWord['partCount']);
        $t->same(
            strlen($parts['WORD/media/caps.png'])
                + strlen($parts['Word/media/raw.bin'])
                + strlen($parts['Word/media/upper.png'])
                + strlen($parts['word/_rels/document.xml.rels'])
                + strlen($parts['word/document.xml'])
                + strlen($parts['word/media/lower.png']),
            $caseFoldWord['byteLength']
        );
        $t->same(['WORD' => 1, 'Word' => 2, 'word' => 3], $caseFoldWord['topLevelSegmentCounts']);
        $t->same(1, $caseFoldWord['missingContentTypePartCount']);
        $t->same(['default' => 4, 'missing' => 1, 'override' => 1], $caseFoldWord['contentTypeSourceCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'office-document' => 1,
            'office-document-relationships' => 1,
            'package-part' => 1,
            'relationship-part' => 1,
            'root-relationship-target' => 1,
        ], $caseFoldWord['roleCounts']);
        $t->same([
            'WORD/media/caps.png',
            'Word/media/raw.bin',
            'Word/media/upper.png',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/lower.png',
        ], $caseFoldWord['partNames']);
        $t->same('Word/media/raw.bin', $caseFoldWord['largestPart']['partName']);
        $t->same('Word', $caseFoldWord['largestPart']['topLevelSegment']);
        $t->same('word', $caseFoldWord['largestPart']['caseFoldTopLevelSegment']);
        $t->same(800, $caseFoldWord['largestPart']['bytes']);
        $t->same(false, array_key_exists('contents', $caseFoldWord['largestPart']));

        $t->same('Word', $directEntries['Word/media/raw.bin']['topLevelSegment']);
        $t->same('word', $directEntries['Word/media/raw.bin']['caseFoldTopLevelSegment']);
        $t->same('word', $directEntries['word/document.xml']['topLevelSegment']);
        $t->same('docx-package-part-bytes-blocked', $directEntries['Word/media/raw.bin']['byteExposurePolicy']);
        $t->same(false, array_key_exists('contents', $directEntries['Word/media/raw.bin']));

        $zipCaseFoldSegments = docx_package_top_level_segment_identity_index_by(
            $zipIdentity['packageCaseFoldTopLevelSegments'],
            'caseFoldTopLevelSegment'
        );
        $zipEntries = docx_package_top_level_segment_identity_entries_by_part($zipIdentity['packageEntries']);
        $t->same($expectedTopLevelCounts, $zipIdentity['packageTopLevelSegmentCounts']);
        $t->same($zipSummary['partTopLevelSegments'], $zipIdentity['packageTopLevelSegments']);
        $t->same($expectedCaseFoldTopLevelCounts, $zipIdentity['packageCaseFoldTopLevelSegmentCounts']);
        $t->same($zipSummary['partCaseFoldTopLevelSegments'], $zipIdentity['packageCaseFoldTopLevelSegments']);
        $t->same('docx-zip-entry-metadata-only', $zipEntries['Word/media/raw.bin']['byteExposurePolicy']);
        $t->same('Word', $zipEntries['Word/media/raw.bin']['topLevelSegment']);
        $t->same('word', $zipEntries['Word/media/raw.bin']['caseFoldTopLevelSegment']);
        $t->same(false, array_key_exists('contents', $zipCaseFoldSegments['word']['largestPart']));

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_package_top_level_segment_identity_fixture_parts())
            ->attr('docx')['packageIdentity'];
        $changedParts = $parts;
        $changedParts['Word/media/raw.bin'] = str_repeat('S', 800);
        $changedIdentity = (new DocxOpenXmlReader())
            ->readPackage($changedParts)
            ->attr('docx')['packageIdentity'];
        $t->same($directIdentity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($directIdentity['identitySha256'] !== $changedIdentity['identitySha256']);
        $t->same(64, strlen($directIdentity['identitySha256']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_top_level_segment_identity_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rRootNote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="root-note.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
  <Relationship Id="rUpperWordMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../Word/media/upper.png"/>
  <Relationship Id="rAllCapsWordMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../WORD/media/caps.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>DOCX package top-level segment identity.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Top-level segment identity</dc:title>
</cp:coreProperties>
XML,
        'word/media/lower.png' => 'lower word media bytes',
        'Word/media/upper.png' => 'upper word media bytes',
        'WORD/media/caps.png' => str_repeat('C', 33),
        'Word/media/raw.bin' => str_repeat('R', 800),
        'customXml/data.xml' => '<data/>',
        'root-note.xml' => '<root-note/>',
    ];
}

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_top_level_segment_identity_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_package_top_level_segment_identity_fixture_parts() as $name => $data) {
        $zipParts[] = [
            'name' => $name,
            'data' => $data,
            'compressionMethod' => str_starts_with(strtolower($name), 'word/') ? 8 : 0,
        ];
    }

    return $zipParts;
}

/**
 * @param list<array<string, mixed>> $summaries
 * @return array<string, array<string, mixed>>
 */
function docx_package_top_level_segment_identity_index_by(array $summaries, string $field): array
{
    $indexed = [];
    foreach ($summaries as $summary) {
        if (is_array($summary) && is_string($summary[$field] ?? null)) {
            $indexed[$summary[$field]] = $summary;
        }
    }

    return $indexed;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function docx_package_top_level_segment_identity_entries_by_part(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && is_string($entry['partName'] ?? null)) {
            $indexed[$entry['partName']] = $entry;
        }
    }

    return $indexed;
}
