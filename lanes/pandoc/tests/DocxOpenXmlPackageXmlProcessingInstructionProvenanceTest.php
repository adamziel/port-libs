<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml processing instructions without exposing data' => static function (TestRunner $t): void {
        $reviewPacketData = 'state="draft" hidden-payload-alpha';
        $reviewInnerData = 'hidden-payload-beta';
        $settingsData = 'hidden-payload-gamma';
        $parts = docx_package_xml_processing_instruction_fixture_parts(
            $reviewPacketData,
            $reviewInnerData,
            $settingsData,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/pi-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $expectedDataByteLength = strlen($reviewPacketData) + strlen($reviewInnerData) + strlen($settingsData);

        $instructionByTarget = static function (array $instructions): array {
            $byTarget = [];
            foreach ($instructions as $instruction) {
                if (is_array($instruction) && is_string($instruction['target'] ?? null)) {
                    $byTarget[$instruction['target']] = $instruction;
                }
            }

            return $byTarget;
        };
        $summaryByTarget = $instructionByTarget($summary['partXmlProcessingInstructions']);

        $t->same(2, $summary['partXmlProcessingInstructionPartCount']);
        $t->same(3, $summary['partXmlProcessingInstructionCount']);
        $t->same($expectedDataByteLength, $summary['partXmlProcessingInstructionDataByteLength']);
        $t->same([0 => 1, 1 => 1, 2 => 1], $summary['partXmlProcessingInstructionParentDepthCounts']);
        $t->same(['review-inner', 'review-packet', 'settings-state'], $summary['partXmlProcessingInstructionTargets']);
        $t->same(['customXml/pi-review.xml', 'word/settings.xml'], $summary['partXmlProcessingInstructionPartNames']);
        $t->same(false, $summary['partXmlProcessingInstructionsTruncated']);

        $t->same(2, $reviewPart['xmlProcessingInstructionCount']);
        $t->same(strlen($reviewPacketData) + strlen($reviewInnerData), $reviewPart['xmlProcessingInstructionDataByteLength']);
        $t->same([0 => 1, 2 => 1], $reviewPart['xmlProcessingInstructionParentDepthCounts']);
        $t->same(['review-inner', 'review-packet'], $reviewPart['xmlProcessingInstructionTargets']);
        $t->same(false, $reviewPart['xmlProcessingInstructionsTruncated']);
        $t->same('review-packet', $reviewPart['xmlProcessingInstructions'][0]['target']);
        $t->same('/', $reviewPart['xmlProcessingInstructions'][0]['parentPath']);
        $t->same(0, $reviewPart['xmlProcessingInstructions'][0]['parentDepth']);
        $t->same(strlen($reviewPacketData), $reviewPart['xmlProcessingInstructions'][0]['dataByteLength']);
        $t->same(sprintf('%08x', crc32($reviewPacketData)), $reviewPart['xmlProcessingInstructions'][0]['dataCrc32']);
        $t->same(hash('sha256', $reviewPacketData), $reviewPart['xmlProcessingInstructions'][0]['dataSha256']);
        $t->same('review-inner', $reviewPart['xmlProcessingInstructions'][1]['target']);
        $t->same('/review:packet/review:value', $reviewPart['xmlProcessingInstructions'][1]['parentPath']);
        $t->same(2, $reviewPart['xmlProcessingInstructions'][1]['parentDepth']);
        $t->same(hash('sha256', $reviewInnerData), $reviewPart['xmlProcessingInstructions'][1]['dataSha256']);

        $t->same(1, $settingsPart['xmlProcessingInstructionCount']);
        $t->same(strlen($settingsData), $settingsPart['xmlProcessingInstructionDataByteLength']);
        $t->same([1 => 1], $settingsPart['xmlProcessingInstructionParentDepthCounts']);
        $t->same(['settings-state'], $settingsPart['xmlProcessingInstructionTargets']);
        $t->same('/w:settings', $settingsPart['xmlProcessingInstructions'][0]['parentPath']);
        $t->same(1, $settingsPart['xmlProcessingInstructions'][0]['parentDepth']);
        $t->same(hash('sha256', $settingsData), $settingsPart['xmlProcessingInstructions'][0]['dataSha256']);

        $t->same('customXml/pi-review.xml', $summaryByTarget['review-packet']['partName']);
        $t->same('/', $summaryByTarget['review-packet']['parentPath']);
        $t->same('customXml/pi-review.xml', $summaryByTarget['review-inner']['partName']);
        $t->same('/review:packet/review:value', $summaryByTarget['review-inner']['parentPath']);
        $t->same('word/settings.xml', $summaryByTarget['settings-state']['partName']);
        $t->same('/w:settings', $summaryByTarget['settings-state']['parentPath']);

        $t->true(!isset($reviewPart['xmlProcessingInstructions'][0]['data']), 'raw PI data should not be exposed on part metadata');
        $encodedInstructions = json_encode([
            $reviewPart['xmlProcessingInstructions'],
            $settingsPart['xmlProcessingInstructions'],
            $summary['partXmlProcessingInstructions'],
        ]);
        $t->true(is_string($encodedInstructions), 'PI metadata should encode for review');
        $t->true(!str_contains((string) $encodedInstructions, 'hidden-payload'), 'raw PI data should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_processing_instruction_fixture_parts(
    string $reviewPacketData,
    string $reviewInnerData,
    string $settingsData,
): array {
    $reviewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<?review-packet {$reviewPacketData}?>
<review:packet xmlns:review="urn:docx-pi-review">
  <review:value><?review-inner {$reviewInnerData}?>safe</review:value>
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
  <Override PartName="/customXml/pi-review.xml" ContentType="application/xml; profile=pi-review"/>
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
  <Relationship Id="rCustomPi" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/pi-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML processing instruction provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML processing instruction fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/pi-review.xml' => $reviewXml,
        'word/settings.xml' => $settingsXml,
    ];
}
