<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml processing instruction pseudo attributes without exposing values' => static function (TestRunner $t): void {
        $stylesheetData = 'type="text/xsl" href="../word/theme/review.xsl" alternate=\'yes\' hidden-payload-alpha';
        $reviewAuditData = 'checkpoint="package" href="../audit.xml" href="duplicate-ignored" state="" hidden-payload-beta';
        $settingsData = 'progid="Word.Document" hidden-payload-gamma';
        $parts = docx_package_xml_processing_instruction_pseudo_attribute_fixture_parts(
            $stylesheetData,
            $reviewAuditData,
            $settingsData,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/pi-attributes.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $expectedValueByteLength = strlen('text/xsl')
            + strlen('../word/theme/review.xsl')
            + strlen('yes')
            + strlen('package')
            + strlen('../audit.xml')
            + strlen('')
            + strlen('Word.Document');

        $instructionByTarget = static function (array $instructions): array {
            $byTarget = [];
            foreach ($instructions as $instruction) {
                if (is_array($instruction) && is_string($instruction['target'] ?? null)) {
                    $byTarget[$instruction['target']] = $instruction;
                }
            }

            return $byTarget;
        };
        $attributeByName = static function (array $attributes): array {
            $byName = [];
            foreach ($attributes as $attribute) {
                if (is_array($attribute) && is_string($attribute['name'] ?? null)) {
                    $byName[$attribute['name']] = $attribute;
                }
            }

            return $byName;
        };

        $summaryByTarget = $instructionByTarget($summary['partXmlProcessingInstructions']);
        $reviewByTarget = $instructionByTarget($reviewPart['xmlProcessingInstructions']);
        $settingsByTarget = $instructionByTarget($settingsPart['xmlProcessingInstructions']);
        $stylesheetAttributes = $attributeByName($reviewByTarget['xml-stylesheet']['dataAttributes']);
        $reviewAuditAttributes = $attributeByName($reviewByTarget['review-audit']['dataAttributes']);
        $settingsAttributes = $attributeByName($settingsByTarget['settings-state']['dataAttributes']);

        $t->same(2, $summary['partXmlProcessingInstructionPartCount']);
        $t->same(3, $summary['partXmlProcessingInstructionCount']);
        $t->same(7, $summary['partXmlProcessingInstructionDataAttributeCount']);
        $t->same(6, $summary['partXmlProcessingInstructionDataAttributeNameCount']);
        $t->same(
            ['alternate' => 1, 'checkpoint' => 1, 'href' => 2, 'progid' => 1, 'state' => 1, 'type' => 1],
            $summary['partXmlProcessingInstructionDataAttributeNameCounts'],
        );
        $t->same(['alternate', 'checkpoint', 'href', 'progid', 'state', 'type'], $summary['partXmlProcessingInstructionDataAttributeNames']);
        $t->same($expectedValueByteLength, $summary['partXmlProcessingInstructionDataAttributeValueByteLength']);
        $t->same(['customXml/pi-attributes.xml', 'word/settings.xml'], $summary['partXmlProcessingInstructionPartNames']);

        $t->same(6, $reviewPart['xmlProcessingInstructionDataAttributeCount']);
        $t->same(
            ['alternate' => 1, 'checkpoint' => 1, 'href' => 2, 'state' => 1, 'type' => 1],
            $reviewPart['xmlProcessingInstructionDataAttributeNameCounts'],
        );
        $t->same(['alternate', 'checkpoint', 'href', 'state', 'type'], $reviewPart['xmlProcessingInstructionDataAttributeNames']);
        $t->same($expectedValueByteLength - strlen('Word.Document'), $reviewPart['xmlProcessingInstructionDataAttributeValueByteLength']);

        $t->same(3, $reviewByTarget['xml-stylesheet']['dataAttributeCount']);
        $t->same(['alternate', 'href', 'type'], $reviewByTarget['xml-stylesheet']['dataAttributeNames']);
        $t->same(strlen('yes'), $stylesheetAttributes['alternate']['valueByteLength']);
        $t->same(sprintf('%08x', crc32('yes')), $stylesheetAttributes['alternate']['valueCrc32']);
        $t->same(hash('sha256', '../word/theme/review.xsl'), $stylesheetAttributes['href']['valueSha256']);
        $t->same(strlen('text/xsl'), $stylesheetAttributes['type']['valueByteLength']);

        $t->same(3, $reviewByTarget['review-audit']['dataAttributeCount']);
        $t->same(['checkpoint', 'href', 'state'], $reviewByTarget['review-audit']['dataAttributeNames']);
        $t->same(hash('sha256', 'package'), $reviewAuditAttributes['checkpoint']['valueSha256']);
        $t->same(hash('sha256', '../audit.xml'), $reviewAuditAttributes['href']['valueSha256']);
        $t->same(0, $reviewAuditAttributes['state']['valueByteLength']);
        $t->same(null, $reviewAuditAttributes['state']['valueCrc32']);
        $t->same(null, $reviewAuditAttributes['state']['valueSha256']);

        $t->same(1, $settingsPart['xmlProcessingInstructionDataAttributeCount']);
        $t->same(['progid' => 1], $settingsPart['xmlProcessingInstructionDataAttributeNameCounts']);
        $t->same(['progid'], $settingsPart['xmlProcessingInstructionDataAttributeNames']);
        $t->same(1, $settingsByTarget['settings-state']['dataAttributeCount']);
        $t->same(['progid'], $settingsByTarget['settings-state']['dataAttributeNames']);
        $t->same(hash('sha256', 'Word.Document'), $settingsAttributes['progid']['valueSha256']);

        $t->same(['alternate', 'href', 'type'], $summaryByTarget['xml-stylesheet']['dataAttributeNames']);
        $t->same(['checkpoint', 'href', 'state'], $summaryByTarget['review-audit']['dataAttributeNames']);
        $t->same(['progid'], $summaryByTarget['settings-state']['dataAttributeNames']);
        $t->true(!isset($stylesheetAttributes['href']['value']), 'raw pseudo-attribute values should not be exposed');

        $encodedMetadata = json_encode([
            $summary['partXmlProcessingInstructions'],
            $reviewPart['xmlProcessingInstructions'],
            $settingsPart['xmlProcessingInstructions'],
        ]);
        $t->true(is_string($encodedMetadata), 'PI pseudo-attribute metadata should encode for review');
        $t->true(!str_contains((string) $encodedMetadata, 'hidden-payload'), 'raw PI data should not appear in package metadata');
        $t->true(!str_contains((string) $encodedMetadata, '../word/theme/review.xsl'), 'raw pseudo-attribute values should not appear in package metadata');
        $t->true(!str_contains((string) $encodedMetadata, 'Word.Document'), 'raw pseudo-attribute values should not appear in package metadata');
        $t->true(!str_contains((string) $encodedMetadata, 'duplicate-ignored'), 'duplicate raw pseudo-attribute values should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_processing_instruction_pseudo_attribute_fixture_parts(
    string $stylesheetData,
    string $reviewAuditData,
    string $settingsData,
): array {
    $reviewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet {$stylesheetData}?>
<review:packet xmlns:review="urn:docx-pi-pseudo-attribute-review">
  <?review-audit {$reviewAuditData}?>
  <review:value>safe</review:value>
</review:packet>
XML;
    $settingsXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <?settings-state {$settingsData}?>
  <w:updateFields w:val="true"/>
</w:settings>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/pi-attributes.xml" ContentType="application/xml; profile=pi-pseudo-attributes"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
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
  <Relationship Id="rCustomPiAttributes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/pi-attributes.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML processing instruction pseudo-attribute fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML processing instruction pseudo-attribute fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/pi-attributes.xml' => $reviewXml,
        'word/settings.xml' => $settingsXml,
    ];
}
