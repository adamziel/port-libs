<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml declarations without exposing raw declaration text' => static function (TestRunner $t): void {
        $declarations = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8"?>',
            'customXml/declaration-review.xml' => '<?xml version="1.1" encoding="windows-1252" standalone="no"?>',
            'word/settings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        ];
        $parts = docx_package_xml_declaration_fixture_parts($declarations);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $declarationRows = [];
        foreach ($summary['partXmlDeclarations'] as $row) {
            $declarationRows[$row['partName']] = $row;
        }
        $expectedDeclarationBytes = array_sum(array_map('strlen', $declarations));

        $t->same(7, $summary['partXmlDeclarationPartCount']);
        $t->same(7, $summary['partXmlDeclarationCount']);
        $t->same($expectedDeclarationBytes, $summary['partXmlDeclarationByteLength']);
        $t->same(['1.0' => 6, '1.1' => 1], $summary['partXmlDeclarationVersionCounts']);
        $t->same(['UTF-8' => 6, 'windows-1252' => 1], $summary['partXmlDeclarationEncodingCounts']);
        $t->same(2, $summary['partXmlDeclarationStandaloneDeclarationCount']);
        $t->same(1, $summary['partXmlDeclarationStandaloneYesCount']);
        $t->same(1, $summary['partXmlDeclarationStandaloneNoCount']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/declaration-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/settings.xml',
        ], $summary['partXmlDeclarationPartNames']);
        $t->same(false, $summary['partXmlDeclarationsTruncated']);

        $reviewPart = $package['parts']['customXml/declaration-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $themePart = $package['parts']['word/theme/no-declaration.xml'];

        $t->same(true, $reviewPart['xmlDeclarationPresent']);
        $t->same('1.1', $reviewPart['xmlDeclarationVersion']);
        $t->same('windows-1252', $reviewPart['xmlDeclarationEncoding']);
        $t->same(false, $reviewPart['xmlDeclarationStandalone']);
        $t->same(3, $reviewPart['xmlDeclarationAttributeCount']);
        $t->same(strlen($declarations['customXml/declaration-review.xml']), $reviewPart['xmlDeclarationByteLength']);
        $t->same(hash('sha256', $declarations['customXml/declaration-review.xml']), $reviewPart['xmlDeclarationSha256']);

        $t->same(true, $settingsPart['xmlDeclarationPresent']);
        $t->same(true, $settingsPart['xmlDeclarationStandalone']);
        $t->same(hash('sha256', $declarations['word/settings.xml']), $settingsPart['xmlDeclarationSha256']);
        $t->same(false, $themePart['xmlDeclarationPresent']);
        $t->same(null, $themePart['xmlDeclarationEncoding']);
        $t->true(!isset($declarationRows['word/theme/no-declaration.xml']), 'parts without XML declarations should not appear in declaration rollups');

        $t->same('windows-1252', $declarationRows['customXml/declaration-review.xml']['encoding']);
        $t->same(false, $declarationRows['customXml/declaration-review.xml']['standalone']);
        $t->same(3, $declarationRows['customXml/declaration-review.xml']['attributeCount']);
        $t->same(hash('sha256', $declarations['customXml/declaration-review.xml']), $declarationRows['customXml/declaration-review.xml']['sha256']);
        $t->same(true, $declarationRows['word/settings.xml']['standalone']);
        $t->true(in_array('settings', $declarationRows['word/settings.xml']['roles'], true), 'settings role should be carried into declaration review rows');

        $encodedDeclarations = json_encode([
            $reviewPart,
            $settingsPart,
            $summary['partXmlDeclarations'],
        ]);
        $t->true(is_string($encodedDeclarations), 'XML declaration metadata should encode for review');
        $t->true(!str_contains((string) $encodedDeclarations, '<?xml'), 'raw XML declaration text should not appear in package metadata');
    },
];

/**
 * @param array<string, string> $declarations
 * @return array<string, string>
 */
function docx_package_xml_declaration_fixture_parts(array $declarations): array
{
    return [
        '[Content_Types].xml' => $declarations['[Content_Types].xml'] . "\n" . <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/declaration-review.xml" ContentType="application/xml; profile=declaration-review"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/theme/no-declaration.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
</Types>
XML,
        '_rels/.rels' => $declarations['_rels/.rels'] . "\n" . <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => $declarations['word/_rels/document.xml.rels'] . "\n" . <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomDeclaration" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/declaration-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/no-declaration.xml"/>
</Relationships>
XML,
        'word/document.xml' => $declarations['word/document.xml'] . "\n" . <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML declaration provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => $declarations['docProps/core.xml'] . "\n" . <<<'XML'
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML declaration fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/declaration-review.xml' => $declarations['customXml/declaration-review.xml'] . "\n" . <<<'XML'
<review:packet xmlns:review="urn:docx-declaration-review">
  <review:value>safe</review:value>
</review:packet>
XML,
        'word/settings.xml' => $declarations['word/settings.xml'] . "\n" . <<<'XML'
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/theme/no-declaration.xml' => <<<'XML'
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="No Declaration Theme"/>
XML,
    ];
}
