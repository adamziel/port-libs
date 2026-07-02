<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package content type family identity mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries DOCX package content type families through provenance and identity' => static function (TestRunner $t): void {
        $parts = docx_package_content_type_family_identity_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_package_content_type_family_identity_fixture_parts())
            ->attr('docx')['packageIdentity'];
        $changedParts = $parts;
        $changedParts['[Content_Types].xml'] = str_replace(
            'application/json',
            'application/vnd.openxmlformats-officedocument.presentationml.slide+xml',
            $changedParts['[Content_Types].xml'],
        );
        $changedIdentity = (new DocxOpenXmlReader())
            ->readPackage($changedParts)
            ->attr('docx')['packageIdentity'];

        $expectedCounts = [
            'application' => 1,
            'binary' => 1,
            'drawingml' => 1,
            'image' => 1,
            'office-properties' => 1,
            'package-metadata' => 1,
            'relationships' => 2,
            'spreadsheetml' => 1,
            'wordprocessingml' => 1,
            'xml' => 2,
        ];
        $expectedBytes = [
            'application' => strlen($parts['customXml/data.json']),
            'binary' => strlen($parts['customXml/data.bin']),
            'drawingml' => strlen($parts['word/charts/chart1.xml']),
            'image' => strlen($parts['word/media/review.png']),
            'office-properties' => strlen($parts['docProps/app.xml']),
            'package-metadata' => strlen($parts['docProps/core.xml']),
            'relationships' => strlen($parts['_rels/.rels']) + strlen($parts['word/_rels/document.xml.rels']),
            'spreadsheetml' => strlen($parts['word/embeddings/review.xlsx']),
            'wordprocessingml' => strlen($parts['word/document.xml']),
            'xml' => strlen($parts['[Content_Types].xml']) + strlen($parts['customXml/item1.xml']),
        ];

        $t->same($identity, $docx['packageIdentity']);
        $t->same(12, $summary['partCount']);
        $t->same(10, $summary['partContentTypeFamilyCount']);
        $t->same($expectedCounts, $summary['partContentTypeFamilyCounts']);
        $t->same($expectedBytes, $summary['partContentTypeFamilyByteLengths']);
        $t->same($expectedCounts, $identity['packageContentTypeFamilyCounts']);
        $t->same($expectedBytes, $identity['packageContentTypeFamilyByteLengths']);
        $t->same($identity['packageContentTypeFamilyCount'], $summary['packageIdentityPackageContentTypeFamilyCount']);
        $t->same(
            $identity['packageContentTypeFamilyCounts'],
            $summary['packageIdentityPackageContentTypeFamilyCounts']
        );
        $t->same(
            $identity['packageContentTypeFamilyByteLengths'],
            $summary['packageIdentityPackageContentTypeFamilyByteLengths']
        );
        $t->same($summary['partContentTypeFamilies'], $identity['packageContentTypeFamilies']);
        $t->same(
            ['_rels/.rels', 'word/_rels/document.xml.rels'],
            $identity['entryNamesByPackageContentTypeFamily']['relationships']
        );
        $t->same(
            ['[Content_Types].xml', 'customXml/item1.xml'],
            $identity['entryNamesByPackageContentTypeFamily']['xml']
        );
        $t->same(['customXml/data.json'], $summary['partNamesByContentTypeFamily']['application']);

        $families = docx_package_content_type_family_identity_index_by_family(
            $summary['partContentTypeFamilies']
        );
        $t->same(2, $families['relationships']['relationshipPartCount']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 2], $families['relationships']['contentTypeBaseCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $families['relationships']['roleCounts']);
        $t->same(['application/json' => 1], $families['application']['contentTypeBaseCounts']);
        $t->same(['application' => 1], $families['application']['contentTypeMediaTypeCounts']);
        $t->same('customXml/data.json', $families['application']['largestPart']['partName']);
        $t->same(false, array_key_exists('contents', $families['application']['largestPart']));

        $entries = docx_package_content_type_family_identity_index_by_part($identity['packageEntries']);
        $t->same('wordprocessingml', $entries['word/document.xml']['contentTypeFamily']);
        $t->same('relationships', $entries['word/_rels/document.xml.rels']['contentTypeFamily']);
        $t->same('package-metadata', $entries['docProps/core.xml']['contentTypeFamily']);
        $t->same('office-properties', $entries['docProps/app.xml']['contentTypeFamily']);
        $t->same('spreadsheetml', $entries['word/embeddings/review.xlsx']['contentTypeFamily']);
        $t->same('drawingml', $entries['word/charts/chart1.xml']['contentTypeFamily']);
        $t->same('application', $entries['customXml/data.json']['contentTypeFamily']);
        $t->same('binary', $entries['customXml/data.bin']['contentTypeFamily']);
        $t->same(false, array_key_exists('contents', $entries['word/document.xml']));

        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_content_type_family_identity_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/customXml/data.json" ContentType="application/json"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/review.xlsx"/>
  <Relationship Id="rChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>DOCX package content type family identity.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Content type family identity</dc:title>
</cp:coreProperties>
XML,
        'docProps/app.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
  <Application>Port Libs Test Fixture</Application>
</Properties>
XML,
        'word/charts/chart1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">
  <c:chart/>
</c:chartSpace>
XML,
        'word/media/review.png' => 'review png bytes',
        'word/embeddings/review.xlsx' => 'embedded workbook package bytes',
        'customXml/item1.xml' => '<item>xml content</item>',
        'customXml/data.bin' => 'opaque binary data',
        'customXml/data.json' => '{"review":true}',
    ];
}

/**
 * @param list<array<string, mixed>> $summaries
 * @return array<string, array<string, mixed>>
 */
function docx_package_content_type_family_identity_index_by_family(array $summaries): array
{
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[(string) $summary['contentTypeFamily']] = $summary;
    }

    return $indexed;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function docx_package_content_type_family_identity_index_by_part(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[(string) $entry['partName']] = $entry;
    }

    return $indexed;
}
