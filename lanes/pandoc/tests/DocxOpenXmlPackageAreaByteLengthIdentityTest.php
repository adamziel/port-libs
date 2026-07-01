<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records docx package area byte identity mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries DOCX package area byte buckets through provenance and identities' => static function (TestRunner $t): void {
        $parts = docx_package_area_byte_identity_fixture_parts();
        $directDocument = (new DocxOpenXmlReader())->readPackage($parts);
        $directDocx = $directDocument->attr('docx');
        $directPackage = $directDocx['packageProvenance'];
        $directSummary = $directPackage['summary'];
        $directIdentity = $directPackage['packageIdentity'];
        $directDocumentIdentity = $directPackage['documentPackageIdentity'];

        $zipDocument = (new DocxOpenXmlReader())->readZipPackage(
            ZipPackage::fromParts(docx_package_area_byte_identity_zip_parts(), 'docx package area byte identity')
        );
        $zipPackage = $zipDocument->attr('docx')['packageProvenance'];
        $zipSummary = $zipPackage['summary'];
        $zipIdentity = $zipPackage['packageIdentity'];

        $expectedAreaCounts = [
            '/' => 2,
            '_rels/' => 1,
            'customXml/' => 1,
            'docProps/' => 1,
            'word/' => 5,
        ];
        $expectedAreaBytes = [
            '/' => strlen($parts['[Content_Types].xml']) + strlen($parts['root-note.xml']),
            '_rels/' => strlen($parts['_rels/.rels']),
            'customXml/' => strlen($parts['customXml/review/data.bin']),
            'docProps/' => strlen($parts['docProps/core.xml']),
            'word/' => strlen($parts['word/_rels/document.xml.rels'])
                + strlen($parts['word/document.xml'])
                + strlen($parts['word/media/review.png'])
                + strlen($parts['word/media/deep/scan.png'])
                + strlen($parts['word/embeddings/review.xlsx']),
        ];
        ksort($expectedAreaCounts, SORT_STRING);
        ksort($expectedAreaBytes, SORT_STRING);
        $expectedAreaRatios = docx_package_area_byte_identity_expansion_ratio_map($expectedAreaBytes, $expectedAreaBytes);
        $zipCompressedAreaBytes = docx_package_area_byte_identity_sum_inventory_by_area(
            $zipPackage['parts'],
            'compressedByteLength'
        );
        $zipAreaRatios = docx_package_area_byte_identity_expansion_ratio_map(
            $expectedAreaBytes,
            $zipCompressedAreaBytes
        );

        $t->same('DOCX package area byte identity.', $directDocument->children[0]->attr('text'));
        $t->same($directIdentity, $directDocx['packageIdentity']);
        $t->same($expectedAreaCounts, $directSummary['packageAreaCounts']);
        $t->same($expectedAreaBytes, $directSummary['packageAreaByteLengths']);
        $t->same($expectedAreaBytes, $directSummary['packageAreaCompressedByteLengths']);
        $t->same($expectedAreaRatios, $directSummary['packageAreaExpansionRatios']);
        $t->same($expectedAreaCounts, $directIdentity['packageAreaCounts']);
        $t->same($expectedAreaBytes, $directIdentity['packageAreaByteLengths']);
        $t->same($expectedAreaBytes, $directIdentity['packageAreaCompressedByteLengths']);
        $t->same($expectedAreaRatios, $directIdentity['packageAreaExpansionRatios']);
        $t->same($directIdentity['packageAreaCount'], $directSummary['packageIdentityPackageAreaCount']);
        $t->same($directIdentity['packageAreaCounts'], $directSummary['packageIdentityPackageAreaCounts']);
        $t->same($directIdentity['packageAreaByteLengths'], $directSummary['packageIdentityPackageAreaByteLengths']);
        $t->same(
            $directIdentity['packageAreaCompressedByteLengths'],
            $directSummary['packageIdentityPackageAreaCompressedByteLengths']
        );
        $t->same(
            $directIdentity['packageAreaExpansionRatios'],
            $directSummary['packageIdentityPackageAreaExpansionRatios']
        );

        $t->same($expectedAreaCounts, $directDocumentIdentity['packageAreaCounts']);
        $t->same($expectedAreaBytes, $directDocumentIdentity['packageAreaByteLengths']);
        $t->same($expectedAreaBytes, $directDocumentIdentity['packageAreaCompressedByteLengths']);
        $t->same($expectedAreaRatios, $directDocumentIdentity['packageAreaExpansionRatios']);
        $t->same($directDocumentIdentity['packageAreaCount'], $directSummary['documentPackageIdentityPackageAreaCount']);
        $t->same($directDocumentIdentity['packageAreaCounts'], $directSummary['documentPackageIdentityPackageAreaCounts']);
        $t->same(
            $directDocumentIdentity['packageAreaByteLengths'],
            $directSummary['documentPackageIdentityPackageAreaByteLengths']
        );
        $t->same(
            $directDocumentIdentity['packageAreaCompressedByteLengths'],
            $directSummary['documentPackageIdentityPackageAreaCompressedByteLengths']
        );
        $t->same(
            $directDocumentIdentity['packageAreaExpansionRatios'],
            $directSummary['documentPackageIdentityPackageAreaExpansionRatios']
        );

        $t->same($directSummary['packageAreaSummaries'], $directIdentity['packageAreaSummaries']);
        $t->same($directSummary['partNamesByPackageArea'], $directIdentity['entryNamesByPackageArea']);
        $t->same($directSummary['partNamesByPackageArea'], $directDocumentIdentity['entryNamesByPackageArea']);
        $t->same([
            '[Content_Types].xml',
            'root-note.xml',
        ], $directIdentity['entryNamesByPackageArea']['/']);
        $t->same([
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ], $directIdentity['entryNamesByPackageArea']['word/']);

        $directAreas = docx_package_area_byte_identity_index_by_area($directSummary['packageAreaSummaries']);
        $t->same(5, $directAreas['word/']['partCount']);
        $t->same($expectedAreaBytes['word/'], $directAreas['word/']['byteLength']);
        $t->same($expectedAreaBytes['word/'], $directAreas['word/']['compressedByteLength']);
        $t->same(1.0, $directAreas['word/']['expansionRatio']);
        $t->same(1, $directAreas['word/']['relationshipPartCount']);
        $t->same(0, $directAreas['word/']['missingContentTypePartCount']);
        $t->same(['docx-package-part-bytes-blocked' => 5], $directAreas['word/']['byteExposurePolicyCounts']);
        $t->same(1, $directAreas['customXml/']['missingContentTypePartCount']);
        $t->same(['docx-package-part-bytes-blocked' => 1], $directAreas['customXml/']['byteExposurePolicyCounts']);
        $t->same('word/', $directPackage['parts']['word/document.xml']['packageArea']);
        $t->same('/', $directPackage['parts']['[Content_Types].xml']['packageArea']);

        $directEntries = docx_package_area_byte_identity_index_by_part($directIdentity['packageEntries']);
        $t->same('word/', $directEntries['word/embeddings/review.xlsx']['packageArea']);
        $t->same('/', $directEntries['root-note.xml']['packageArea']);
        $t->same(false, array_key_exists('contents', $directAreas['word/']['largestPart']));
        $t->same(false, array_key_exists('contents', $directIdentity['packageAreaSummaries'][0]['largestPart']));

        $zipAreas = docx_package_area_byte_identity_index_by_area($zipSummary['packageAreaSummaries']);
        $t->same($expectedAreaCounts, $zipSummary['packageAreaCounts']);
        $t->same($expectedAreaBytes, $zipSummary['packageAreaByteLengths']);
        $t->same($zipCompressedAreaBytes, $zipSummary['packageAreaCompressedByteLengths']);
        $t->same($zipAreaRatios, $zipSummary['packageAreaExpansionRatios']);
        $t->same($expectedAreaCounts, $zipIdentity['packageAreaCounts']);
        $t->same($expectedAreaBytes, $zipIdentity['packageAreaByteLengths']);
        $t->same($zipCompressedAreaBytes, $zipIdentity['packageAreaCompressedByteLengths']);
        $t->same($zipAreaRatios, $zipIdentity['packageAreaExpansionRatios']);
        $t->same($zipSummary['packageAreaSummaries'], $zipIdentity['packageAreaSummaries']);
        $t->same($zipSummary['partNamesByPackageArea'], $zipIdentity['entryNamesByPackageArea']);
        $t->same($zipCompressedAreaBytes['word/'], $zipAreas['word/']['compressedByteLength']);
        $t->same($zipAreaRatios['word/'], $zipAreas['word/']['expansionRatio']);
        $t->same(['docx-zip-entry-metadata-only' => 5], $zipAreas['word/']['byteExposurePolicyCounts']);
        $t->same('docx-zip-entry-metadata-only', $zipIdentity['packageEntries'][0]['byteExposurePolicy']);
        $t->same(false, $zipIdentity['packageEntries'][0]['canExposeBytes']);
        $t->same(false, array_key_exists('contents', $zipAreas['word/']['largestPart']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_area_byte_identity_fixture_parts(): array
{
    $embeddedContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $embeddedPackageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';

    return [
        '[Content_Types].xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="{$embeddedContentType}"/>
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
        'word/_rels/document.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rDeepImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/deep/scan.png"/>
  <Relationship Id="rEmbeddedWorkbook" Type="{$embeddedPackageRel}" Target="embeddings/review.xlsx"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>DOCX package area byte identity.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package area byte identity</dc:title>
</cp:coreProperties>
XML,
        'root-note.xml' => '<root-note>package root sidecar</root-note>',
        'word/media/review.png' => 'review png bytes',
        'word/media/deep/scan.png' => 'deep scan png bytes',
        'word/embeddings/review.xlsx' => 'embedded package bytes',
        'customXml/review/data.bin' => 'untyped custom xml data',
    ];
}

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_area_byte_identity_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_package_area_byte_identity_fixture_parts() as $name => $data) {
        $zipParts[] = [
            'name' => $name,
            'data' => $data,
            'compressionMethod' => str_starts_with($name, 'word/') ? 8 : 0,
        ];
    }

    return $zipParts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_package_area_byte_identity_sum_inventory_by_area(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (!is_array($part)) {
            continue;
        }

        $area = is_string($part['packageArea'] ?? null) ? $part['packageArea'] : '/';
        $value = is_int($part[$field] ?? null) ? (int) $part[$field] : (int) ($part['bytes'] ?? 0);
        $sums[$area] = ($sums[$area] ?? 0) + $value;
    }
    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, int> $uncompressedBytes
 * @param array<string, int> $compressedBytes
 * @return array<string, ?float>
 */
function docx_package_area_byte_identity_expansion_ratio_map(
    array $uncompressedBytes,
    array $compressedBytes
): array {
    $ratios = [];
    foreach ($uncompressedBytes as $area => $uncompressedByteLength) {
        $compressedByteLength = (int) ($compressedBytes[$area] ?? 0);
        if ((int) $uncompressedByteLength === 0) {
            $ratios[$area] = 0.0;
            continue;
        }
        if ($compressedByteLength === 0) {
            $ratios[$area] = null;
            continue;
        }

        $ratios[$area] = (float) $uncompressedByteLength / $compressedByteLength;
    }
    ksort($ratios, SORT_STRING);

    return $ratios;
}

/**
 * @param list<array<string, mixed>> $summaries
 * @return array<string, array<string, mixed>>
 */
function docx_package_area_byte_identity_index_by_area(array $summaries): array
{
    $indexed = [];
    foreach ($summaries as $summary) {
        if (is_array($summary) && is_string($summary['packageArea'] ?? null)) {
            $indexed[$summary['packageArea']] = $summary;
        }
    }

    return $indexed;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function docx_package_area_byte_identity_index_by_part(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && is_string($entry['partName'] ?? null)) {
            $indexed[$entry['partName']] = $entry;
        }
    }

    return $indexed;
}
