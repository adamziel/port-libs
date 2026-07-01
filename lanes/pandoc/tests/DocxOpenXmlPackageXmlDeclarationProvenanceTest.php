<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml declarations without exposing package bytes' => static function (TestRunner $t): void {
        $parts = docx_package_xml_declaration_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $identityEntries = docx_package_xml_declaration_entries_by_part($identity['packageEntries']);

        $expectedPresentParts = [
            '[Content_Types].xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ];
        $expectedMissingParts = [
            '_rels/.rels',
            'customXml/no-declaration.xml',
        ];

        $t->same(6, $summary['partXmlDeclarationReviewedPartCount']);
        $t->same(4, $summary['partXmlDeclarationPartCount']);
        $t->same(2, $summary['partXmlDeclarationMissingPartCount']);
        $t->same($expectedPresentParts, $summary['partXmlDeclarationPartNames']);
        $t->same($expectedMissingParts, $summary['partXmlDeclarationMissingPartNames']);
        $t->same(['1.0' => 4], $summary['partXmlDeclarationVersionCounts']);
        $t->same(['ISO-8859-1' => 1, 'UTF-8' => 3], $summary['partXmlDeclarationEncodingCounts']);
        $t->same(['no' => 1, 'unspecified' => 2, 'yes' => 1], $summary['partXmlDeclarationStandaloneCounts']);
        $t->same(['2' => 2, '3' => 2], $summary['partXmlDeclarationAttributeCountPartCounts']);

        $documentPart = $inventory['word/document.xml'];
        $t->same(true, $documentPart['xmlDeclarationReviewed']);
        $t->same(true, $documentPart['xmlDeclarationPresent']);
        $t->same('1.0', $documentPart['xmlDeclarationVersion']);
        $t->same('UTF-8', $documentPart['xmlDeclarationEncoding']);
        $t->same(false, $documentPart['xmlDeclarationStandalone']);
        $t->same(3, $documentPart['xmlDeclarationAttributeCount']);

        $relationshipsPart = $inventory['_rels/.rels'];
        $t->same(true, $relationshipsPart['xmlDeclarationReviewed']);
        $t->same(false, $relationshipsPart['xmlDeclarationPresent']);
        $t->same(null, $relationshipsPart['xmlDeclarationVersion']);
        $t->same(null, $relationshipsPart['xmlDeclarationEncoding']);
        $t->same(null, $relationshipsPart['xmlDeclarationStandalone']);
        $t->same(0, $relationshipsPart['xmlDeclarationAttributeCount']);

        $corePart = $inventory['docProps/core.xml'];
        $t->same(true, $corePart['xmlDeclarationPresent']);
        $t->same('ISO-8859-1', $corePart['xmlDeclarationEncoding']);
        $t->same(null, $corePart['xmlDeclarationStandalone']);
        $t->same(2, $corePart['xmlDeclarationAttributeCount']);

        $customPart = $inventory['customXml/no-declaration.xml'];
        $t->same(true, $customPart['xmlDeclarationReviewed']);
        $t->same(false, $customPart['xmlDeclarationPresent']);

        $binaryPart = $inventory['word/media/review.png'];
        $t->same(false, $binaryPart['xmlDeclarationReviewed']);
        $t->same(false, $binaryPart['xmlDeclarationPresent']);

        $t->same($summary['partXmlDeclarationReviewedPartCount'], $identity['partXmlDeclarationReviewedPartCount']);
        $t->same($summary['partXmlDeclarationPartNames'], $identity['partXmlDeclarationPartNames']);
        $t->same($summary['partXmlDeclarationMissingPartNames'], $identity['partXmlDeclarationMissingPartNames']);
        $t->same($summary['partXmlDeclarationEncodingCounts'], $identity['partXmlDeclarationEncodingCounts']);
        $t->same($summary['partXmlDeclarationStandaloneCounts'], $identity['partXmlDeclarationStandaloneCounts']);
        $t->same($identity, $docx['packageIdentity']);

        $identityDocumentPart = $identityEntries['word/document.xml'];
        $t->same(true, $identityDocumentPart['xmlDeclarationReviewed']);
        $t->same(true, $identityDocumentPart['xmlDeclarationPresent']);
        $t->same('UTF-8', $identityDocumentPart['xmlDeclarationEncoding']);
        $t->same(false, $identityDocumentPart['xmlDeclarationStandalone']);
        $t->same(3, $identityDocumentPart['xmlDeclarationAttributeCount']);

        $identityBinaryPart = $identityEntries['word/media/review.png'];
        $t->same(false, $identityBinaryPart['xmlDeclarationReviewed']);
        $t->same(false, $identityBinaryPart['xmlDeclarationPresent']);

        $encodedPackage = json_encode($package);
        $t->true(is_string($encodedPackage), 'package XML declaration metadata should encode for review');
        $t->true(!str_contains((string) $encodedPackage, 'Package XML declaration fixture.'), 'package metadata should not expose XML text');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_declaration_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/no-declaration.xml" ContentType="application/xml; profile=declaration-review"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/no-declaration.xml"/>
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML declaration fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="ISO-8859-1"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Declaration provenance</dc:title>
</cp:coreProperties>
XML,
        'customXml/no-declaration.xml' => <<<'XML'
<review:packet xmlns:review="urn:docx-xml-declaration-review">
  <review:value>metadata only</review:value>
</review:packet>
XML,
        'word/media/review.png' => 'not really a png, just package bytes',
    ];
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function docx_package_xml_declaration_entries_by_part(array $entries): array
{
    $byPart = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && is_string($entry['partName'] ?? null)) {
            $byPart[$entry['partName']] = $entry;
        }
    }

    return $byPart;
}
