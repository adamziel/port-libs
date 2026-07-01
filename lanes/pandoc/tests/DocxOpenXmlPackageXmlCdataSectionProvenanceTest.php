<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml cdata parent-depth rollups without exposing text' => static function (TestRunner $t): void {
        $reviewCdata = 'docx-cdata-review:hidden-payload-alpha';
        $nestedCdata = 'docx-cdata-nested:hidden-payload-beta';
        $settingsCdata = 'docx-cdata-settings:hidden-payload-gamma';
        $parts = docx_package_xml_cdata_section_fixture_parts(
            $reviewCdata,
            $nestedCdata,
            $settingsCdata,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/cdata-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $expectedByteLength = strlen($reviewCdata) + strlen($nestedCdata) + strlen($settingsCdata);

        $t->same(2, $summary['partXmlCdataSectionPartCount']);
        $t->same(3, $summary['partXmlCdataSectionCount']);
        $t->same($expectedByteLength, $summary['partXmlCdataSectionByteLength']);
        $t->same([2 => 1, 3 => 2], $summary['partXmlCdataSectionParentDepthCounts']);
        $t->same(['customXml/cdata-review.xml', 'word/settings.xml'], $summary['partXmlCdataSectionPartNames']);
        $t->same(false, $summary['partXmlCdataSectionsTruncated']);

        $t->same(2, $reviewPart['xmlCdataSectionCount']);
        $t->same(strlen($reviewCdata) + strlen($nestedCdata), $reviewPart['xmlCdataSectionByteLength']);
        $t->same([2 => 1, 3 => 1], $reviewPart['xmlCdataSectionParentDepthCounts']);
        $t->same(false, $reviewPart['xmlCdataSectionsTruncated']);
        $t->same('/review:packet/review:value', $reviewPart['xmlCdataSections'][0]['parentPath']);
        $t->same(2, $reviewPart['xmlCdataSections'][0]['parentDepth']);
        $t->same(strlen($reviewCdata), $reviewPart['xmlCdataSections'][0]['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewCdata)), $reviewPart['xmlCdataSections'][0]['crc32']);
        $t->same(hash('sha256', $reviewCdata), $reviewPart['xmlCdataSections'][0]['sha256']);
        $t->same('/review:packet/review:nested/review:value', $reviewPart['xmlCdataSections'][1]['parentPath']);
        $t->same(3, $reviewPart['xmlCdataSections'][1]['parentDepth']);
        $t->same(hash('sha256', $nestedCdata), $reviewPart['xmlCdataSections'][1]['sha256']);

        $t->same(1, $settingsPart['xmlCdataSectionCount']);
        $t->same(strlen($settingsCdata), $settingsPart['xmlCdataSectionByteLength']);
        $t->same([3 => 1], $settingsPart['xmlCdataSectionParentDepthCounts']);
        $t->same('/w:settings/w:docVars/w:docVar', $settingsPart['xmlCdataSections'][0]['parentPath']);
        $t->same(3, $settingsPart['xmlCdataSections'][0]['parentDepth']);
        $t->same(sprintf('%08x', crc32($settingsCdata)), $settingsPart['xmlCdataSections'][0]['crc32']);
        $t->same(hash('sha256', $settingsCdata), $settingsPart['xmlCdataSections'][0]['sha256']);

        $t->same('customXml/cdata-review.xml', $summary['partXmlCdataSections'][0]['partName']);
        $t->same('/review:packet/review:value', $summary['partXmlCdataSections'][0]['parentPath']);
        $t->same('customXml/cdata-review.xml', $summary['partXmlCdataSections'][1]['partName']);
        $t->same('/review:packet/review:nested/review:value', $summary['partXmlCdataSections'][1]['parentPath']);
        $t->same('word/settings.xml', $summary['partXmlCdataSections'][2]['partName']);
        $t->same('/w:settings/w:docVars/w:docVar', $summary['partXmlCdataSections'][2]['parentPath']);

        $t->true(!isset($reviewPart['xmlCdataSections'][0]['text']), 'raw XML CDATA text should not be exposed on part metadata');
        $t->true(!isset($reviewPart['xmlCdataSections'][0]['data']), 'raw XML CDATA data should not be exposed on part metadata');
        $encodedSections = json_encode([
            $reviewPart['xmlCdataSections'],
            $settingsPart['xmlCdataSections'],
            $summary['partXmlCdataSections'],
        ]);
        $t->true(is_string($encodedSections), 'XML CDATA metadata should encode for review');
        $t->true(!str_contains((string) $encodedSections, 'hidden-payload'), 'raw XML CDATA text should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_cdata_section_fixture_parts(
    string $reviewCdata,
    string $nestedCdata,
    string $settingsCdata,
): array {
    $reviewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<review:packet xmlns:review="urn:docx-cdata-review">
  <review:value><![CDATA[{$reviewCdata}]]></review:value>
  <review:nested>
    <review:value><![CDATA[{$nestedCdata}]]></review:value>
  </review:nested>
</review:packet>
XML;
    $settingsXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docVars>
    <w:docVar w:name="Review"><![CDATA[{$settingsCdata}]]></w:docVar>
  </w:docVars>
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
  <Override PartName="/customXml/cdata-review.xml" ContentType="application/xml; profile=cdata-review"/>
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
  <Relationship Id="rCustomCdata" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/cdata-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML CDATA provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML CDATA fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/cdata-review.xml' => $reviewXml,
        'word/settings.xml' => $settingsXml,
    ];
}
