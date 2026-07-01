<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml declarations without exposing xml bytes' => static function (TestRunner $t): void {
        $parts = docx_package_xml_declaration_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $contentTypesPart = $package['parts']['[Content_Types].xml'];
        $rootRelationshipsPart = $package['parts']['_rels/.rels'];
        $documentRelationshipsPart = $package['parts']['word/_rels/document.xml.rels'];
        $documentPart = $package['parts']['word/document.xml'];
        $corePart = $package['parts']['docProps/core.xml'];
        $reviewPart = $package['parts']['customXml/declaration-review.xml'];
        $missingDeclarationPart = $package['parts']['customXml/no-declaration.xml'];
        $invalidPart = $package['parts']['customXml/invalid-declaration.xml'];
        $binaryPart = $package['parts']['word/media/review.bin'];

        $declarationsByPartName = static function (array $declarations): array {
            $byPartName = [];
            foreach ($declarations as $declaration) {
                if (is_array($declaration) && is_string($declaration['partName'] ?? null)) {
                    $byPartName[$declaration['partName']] = $declaration;
                }
            }

            return $byPartName;
        };
        $summaryDeclarations = $declarationsByPartName($summary['partXmlDeclarations']);

        $t->same(8, $summary['partXmlDeclarationCheckedPartCount']);
        $t->same(6, $summary['partXmlDeclarationPartCount']);
        $t->same(2, $summary['partXmlDeclarationMissingPartCount']);
        $t->same(15, $summary['partXmlDeclarationAttributeCount']);
        $t->same(['1.0' => 5, '1.1' => 1], $summary['partXmlDeclarationVersionCounts']);
        $t->same([
            'ISO-8859-1' => 1,
            'UTF-16' => 1,
            'UTF-8' => 3,
            'windows-1252' => 1,
        ], $summary['partXmlDeclarationEncodingCounts']);
        $t->same(['no' => 1, 'omitted' => 3, 'yes' => 2], $summary['partXmlDeclarationStandaloneCounts']);
        $t->same([
            '[Content_Types].xml',
            'customXml/declaration-review.xml',
            'customXml/invalid-declaration.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $summary['partXmlDeclarationPartNames']);
        $t->same(['_rels/.rels', 'customXml/no-declaration.xml'], $summary['partXmlDeclarationMissingPartNames']);
        $t->same(false, $summary['partXmlDeclarationsTruncated']);

        $t->same(true, $contentTypesPart['xmlDeclarationChecked']);
        $t->same(true, $contentTypesPart['xmlDeclarationPresent']);
        $t->same('1.0', $contentTypesPart['xmlDeclarationVersion']);
        $t->same('UTF-8', $contentTypesPart['xmlDeclarationEncoding']);
        $t->same(null, $contentTypesPart['xmlDeclarationStandalone']);
        $t->same(2, $contentTypesPart['xmlDeclarationAttributeCount']);

        $t->same(true, $rootRelationshipsPart['xmlDeclarationChecked']);
        $t->same(false, $rootRelationshipsPart['xmlDeclarationPresent']);
        $t->same(null, $rootRelationshipsPart['xmlDeclarationEncoding']);
        $t->same(0, $rootRelationshipsPart['xmlDeclarationAttributeCount']);

        $t->same(true, $documentRelationshipsPart['xmlDeclarationPresent']);
        $t->same('UTF-8', $documentRelationshipsPart['xmlDeclarationEncoding']);
        $t->same(true, $documentRelationshipsPart['xmlDeclarationStandalone']);
        $t->same(3, $documentRelationshipsPart['xmlDeclarationAttributeCount']);

        $t->same('UTF-8', $documentPart['xmlDeclarationEncoding']);
        $t->same(null, $documentPart['xmlDeclarationStandalone']);
        $t->same('windows-1252', $corePart['xmlDeclarationEncoding']);
        $t->same(false, $corePart['xmlDeclarationStandalone']);
        $t->same('1.1', $reviewPart['xmlDeclarationVersion']);
        $t->same('ISO-8859-1', $reviewPart['xmlDeclarationEncoding']);
        $t->same(true, $reviewPart['xmlDeclarationStandalone']);

        $t->same(true, $missingDeclarationPart['xmlDeclarationChecked']);
        $t->same(false, $missingDeclarationPart['xmlDeclarationPresent']);
        $t->same(null, $missingDeclarationPart['xmlDeclarationVersion']);
        $t->same(null, $missingDeclarationPart['xmlDeclarationEncoding']);
        $t->same(null, $missingDeclarationPart['xmlDeclarationStandalone']);
        $t->same(0, $missingDeclarationPart['xmlDeclarationAttributeCount']);

        $t->same(true, $invalidPart['xmlDeclarationChecked']);
        $t->same(true, $invalidPart['xmlDeclarationPresent']);
        $t->same('UTF-16', $invalidPart['xmlDeclarationEncoding']);
        $t->same(null, $invalidPart['xmlDeclarationStandalone']);
        $t->same(2, $invalidPart['xmlDeclarationAttributeCount']);

        $t->same(false, $binaryPart['xmlDeclarationChecked']);
        $t->same(false, $binaryPart['xmlDeclarationPresent']);
        $t->same(null, $binaryPart['xmlDeclarationEncoding']);

        $t->same('ISO-8859-1', $summaryDeclarations['customXml/declaration-review.xml']['encoding']);
        $t->same(true, $summaryDeclarations['customXml/declaration-review.xml']['standalone']);
        $t->same('UTF-16', $summaryDeclarations['customXml/invalid-declaration.xml']['encoding']);
        $t->same('override', $summaryDeclarations['customXml/invalid-declaration.xml']['contentTypeSource']);
        $t->true(!isset($summaryDeclarations['_rels/.rels']), 'parts without XML declarations should stay out of declaration records');

        $encodedDeclarations = json_encode([
            $summary['partXmlDeclarations'],
            $contentTypesPart['xmlDeclarationEncoding'],
            $reviewPart['xmlDeclarationEncoding'],
            $invalidPart['xmlDeclarationEncoding'],
        ]);
        $t->true(is_string($encodedDeclarations), 'XML declaration metadata should encode for review');
        $t->true(!str_contains((string) $encodedDeclarations, 'hidden-payload'), 'raw XML part bytes should not appear in declaration metadata');
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
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/declaration-review.xml" ContentType="application/xml; profile=declaration-review"/>
  <Override PartName="/customXml/no-declaration.xml" ContentType="application/xml; profile=declaration-missing"/>
  <Override PartName="/customXml/invalid-declaration.xml" ContentType="application/xml; profile=declaration-invalid"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomDeclaration" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/declaration-review.xml"/>
  <Relationship Id="rMissingDeclaration" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/no-declaration.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML declaration hidden-payload-alpha fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="windows-1252" standalone="no"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML declaration fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/declaration-review.xml' => <<<'XML'
<?xml version="1.1" encoding="ISO-8859-1" standalone="yes"?>
<review:packet xmlns:review="urn:docx-declaration-review">
  <review:value>hidden-payload-beta</review:value>
</review:packet>
XML,
        'customXml/no-declaration.xml' => <<<'XML'
<review:packet xmlns:review="urn:docx-declaration-missing">
  <review:value>hidden-payload-gamma</review:value>
</review:packet>
XML,
        'customXml/invalid-declaration.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<broken><payload>hidden-payload-delta</broken>
XML,
        'word/media/review.bin' => "hidden-payload-epsilon\x00not xml",
    ];
}
