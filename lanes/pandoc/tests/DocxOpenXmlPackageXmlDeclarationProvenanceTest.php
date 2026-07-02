<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml declarations without exposing raw declarations' => static function (TestRunner $t): void {
        $standardDeclaration = '<?xml version="1.0" encoding="UTF-8"?>';
        $relationshipDeclaration = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $reviewDeclaration = '<?xml version="1.1" encoding="windows-1252" standalone="no"?>';
        $parts = docx_package_xml_declaration_fixture_parts(
            $standardDeclaration,
            $relationshipDeclaration,
            $reviewDeclaration,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $contentTypesPart = $package['parts']['[Content_Types].xml'];
        $relationshipsPart = $package['parts']['word/_rels/document.xml.rels'];
        $reviewPart = $package['parts']['customXml/declaration-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $expectedByteLength = strlen($standardDeclaration) * 3
            + strlen($relationshipDeclaration)
            + strlen($reviewDeclaration);

        $declarationsByPart = [];
        foreach ($summary['partXmlDeclarations'] as $declaration) {
            $declarationsByPart[$declaration['partName']] = $declaration;
        }

        $t->same(5, $summary['partXmlDeclarationPartCount']);
        $t->same(5, $summary['partXmlDeclarationCount']);
        $t->same($expectedByteLength, $summary['partXmlDeclarationByteLength']);
        $t->same(12, $summary['partXmlDeclarationAttributeCount']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/declaration-review.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $summary['partXmlDeclarationPartNames']);
        $t->same(['1.0' => 4, '1.1' => 1], $summary['partXmlDeclarationVersionCounts']);
        $t->same(['UTF-8' => 4, 'windows-1252' => 1], $summary['partXmlDeclarationEncodingCounts']);
        $t->same(2, $summary['partXmlStandaloneDeclarationCount']);
        $t->same(1, $summary['partXmlStandaloneYesCount']);
        $t->same(1, $summary['partXmlStandaloneNoCount']);
        $t->same(false, $summary['partXmlDeclarationsTruncated']);

        $t->same(true, $contentTypesPart['xmlDeclarationPresent']);
        $t->same('1.0', $contentTypesPart['xmlDeclarationVersion']);
        $t->same('UTF-8', $contentTypesPart['xmlDeclarationEncoding']);
        $t->same(null, $contentTypesPart['xmlDeclarationStandalone']);
        $t->same(2, $contentTypesPart['xmlDeclarationAttributeCount']);
        $t->same(strlen($standardDeclaration), $contentTypesPart['xmlDeclarationByteLength']);
        $t->same(hash('sha256', $standardDeclaration), $contentTypesPart['xmlDeclarationSha256']);

        $t->same(true, $relationshipsPart['xmlDeclarationPresent']);
        $t->same(true, $relationshipsPart['xmlDeclarationStandalone']);
        $t->same(3, $relationshipsPart['xmlDeclarationAttributeCount']);
        $t->same(hash('sha256', $relationshipDeclaration), $relationshipsPart['xmlDeclarationSha256']);

        $t->same(true, $reviewPart['xmlDeclarationPresent']);
        $t->same('1.1', $reviewPart['xmlDeclarationVersion']);
        $t->same('windows-1252', $reviewPart['xmlDeclarationEncoding']);
        $t->same(false, $reviewPart['xmlDeclarationStandalone']);
        $t->same(strlen($reviewDeclaration), $reviewPart['xmlDeclarationByteLength']);
        $t->same(hash('sha256', $reviewDeclaration), $reviewPart['xmlDeclarationSha256']);

        $t->same(false, $settingsPart['xmlDeclarationPresent']);
        $t->same(0, $settingsPart['xmlDeclarationByteLength']);
        $t->same(null, $settingsPart['xmlDeclarationSha256']);

        $t->same($reviewPart['xmlDeclarationSha256'], $declarationsByPart['customXml/declaration-review.xml']['sha256']);
        $t->same('application/xml', $declarationsByPart['customXml/declaration-review.xml']['contentTypeBase']);
        $t->same(['document-relationship-target', 'custom-xml-part'], $declarationsByPart['customXml/declaration-review.xml']['roles']);
        $t->same(true, $declarationsByPart['word/_rels/document.xml.rels']['standalone']);

        $encodedDeclarations = json_encode([
            $summary['partXmlDeclarations'],
            $contentTypesPart,
            $relationshipsPart,
            $reviewPart,
        ]);
        $t->true(is_string($encodedDeclarations), 'XML declaration metadata should encode for review');
        $t->true(!str_contains((string) $encodedDeclarations, '<?xml'), 'raw XML declarations should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_declaration_fixture_parts(
    string $standardDeclaration,
    string $relationshipDeclaration,
    string $reviewDeclaration,
): array {
    return [
        '[Content_Types].xml' => $standardDeclaration . "\n" . <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/declaration-review.xml" ContentType="application/xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
</Types>
XML,
        '_rels/.rels' => $standardDeclaration . "\n" . <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => $relationshipDeclaration . "\n" . <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomDeclaration" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/declaration-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => $standardDeclaration . "\n" . <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML declaration provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'customXml/declaration-review.xml' => $reviewDeclaration . "\n" . <<<'XML'
<review:packet xmlns:review="urn:docx-declaration-review">
  <review:value>safe</review:value>
</review:packet>
XML,
        'word/settings.xml' => <<<'XML'
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
    ];
}
