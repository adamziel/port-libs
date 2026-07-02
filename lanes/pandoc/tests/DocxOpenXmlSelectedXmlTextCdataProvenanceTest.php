<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx selected xml text and cdata metadata for package review' => static function (TestRunner $t): void {
        $settingsText = "selected-settings-text:hidden-alpha\nline-two";
        $settingsCdata = 'selected-settings-cdata:hidden-beta';
        $themeCdata = 'selected-theme-cdata:hidden-gamma';
        $parts = docx_selected_xml_text_cdata_fixture_parts($settingsText, $settingsCdata, $themeCdata);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $selected = $package['selectedXmlParts'];
        $summary = $package['summary'];
        $settings = $selected['byKind']['settings'];
        $theme = $selected['byKind']['theme'];
        $settingsTextRows = array_values(array_filter(
            $settings['xmlTextNodes'],
            static fn (array $row): bool => $row['parentPath'] === '/w:settings/w:docVars/w:docVar',
        ));

        $t->same(18, $selected['count']);
        $t->same(6, $selected['existingCount']);
        $t->same(2, $selected['xmlCdataSectionPartCount']);
        $t->same(2, $selected['xmlCdataSectionCount']);
        $t->same(strlen($settingsCdata) + strlen($themeCdata), $selected['xmlCdataSectionByteLength']);
        $t->same(['word/settings.xml', 'word/theme/text-theme.xml'], $selected['xmlCdataSectionPartNames']);
        $t->same([
            '/a:theme/a:themeElements' => 1,
            '/w:settings/w:compat' => 1,
        ], $selected['xmlCdataSectionParentPathCounts']);
        $t->same([
            'http://schemas.openxmlformats.org/drawingml/2006/main' => 1,
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main' => 1,
        ], $selected['xmlCdataSectionParentNamespaceCounts']);
        $t->same($selected['xmlCdataSectionCount'], $summary['selectedXmlPartXmlCdataSectionCount']);
        $t->same($selected['xmlCdataSections'], $summary['selectedXmlPartXmlCdataSections']);

        $t->same(1, $settings['xmlCdataSectionCount']);
        $t->same(strlen($settingsCdata), $settings['xmlCdataSectionByteLength']);
        $t->same('/w:settings/w:compat', $settings['xmlCdataSections'][0]['parentPath']);
        $t->same(2, $settings['xmlCdataSections'][0]['parentDepth']);
        $t->same('w:compat', $settings['xmlCdataSections'][0]['parentQualifiedName']);
        $t->same(sprintf('%08x', crc32($settingsCdata)), $settings['xmlCdataSections'][0]['crc32']);
        $t->same(hash('sha256', $settingsCdata), $settings['xmlCdataSections'][0]['sha256']);

        $t->same(1, $theme['xmlCdataSectionCount']);
        $t->same(strlen($themeCdata), $theme['xmlCdataSectionByteLength']);
        $t->same('/a:theme/a:themeElements', $theme['xmlCdataSections'][0]['parentPath']);
        $t->same(2, $theme['xmlCdataSections'][0]['parentDepth']);
        $t->same('a:themeElements', $theme['xmlCdataSections'][0]['parentQualifiedName']);
        $t->same(sprintf('%08x', crc32($themeCdata)), $theme['xmlCdataSections'][0]['crc32']);
        $t->same(hash('sha256', $themeCdata), $theme['xmlCdataSections'][0]['sha256']);

        $t->true($selected['xmlTextNodeCount'] >= $settings['xmlTextNodeCount'], 'selected text rollup should include settings text nodes');
        $t->true(in_array('word/settings.xml', $selected['xmlTextNodePartNames'], true), 'settings text part should be summarized');
        $t->same($selected['xmlTextNodeCount'], $summary['selectedXmlPartXmlTextNodeCount']);
        $t->same($selected['xmlTextNodeByteLength'], $summary['selectedXmlPartXmlTextNodeByteLength']);
        $t->same($selected['xmlTextNodeWhitespaceCount'], $summary['selectedXmlPartXmlTextNodeWhitespaceCount']);
        $t->same($selected['xmlTextNodeNonWhitespaceCount'], $summary['selectedXmlPartXmlTextNodeNonWhitespaceCount']);
        $t->same($selected['xmlTextNodeParentPathCounts'], $summary['selectedXmlPartXmlTextNodeParentPathCounts']);
        $t->same($selected['xmlTextNodes'], $summary['selectedXmlPartXmlTextNodes']);

        $t->same(1, count($settingsTextRows));
        $t->same(strlen($settingsText), $settingsTextRows[0]['byteLength']);
        $t->same(false, $settingsTextRows[0]['isWhitespaceOnly']);
        $t->same(1, $settingsTextRows[0]['lineBreakCount']);
        $t->same(true, $settingsTextRows[0]['hasLineBreak']);
        $t->same('docVar', $settingsTextRows[0]['parentLocalName']);
        $t->same('w:docVar', $settingsTextRows[0]['parentQualifiedName']);
        $t->same(sprintf('%08x', crc32($settingsText)), $settingsTextRows[0]['crc32']);
        $t->same(hash('sha256', $settingsText), $settingsTextRows[0]['sha256']);

        $encodedReview = json_encode([
            $selected['xmlCdataSections'],
            $selected['xmlTextNodes'],
            $summary['selectedXmlPartXmlCdataSections'],
            $summary['selectedXmlPartXmlTextNodes'],
        ]);
        $t->true(is_string($encodedReview), 'selected XML text metadata should encode for review');
        $t->true(!array_key_exists('data', $settingsTextRows[0]), 'selected XML text metadata must not expose raw text');
        $t->true(!array_key_exists('data', $settings['xmlCdataSections'][0]), 'selected XML CDATA metadata must not expose raw CDATA text');
        $t->true(!str_contains((string) $encodedReview, 'hidden-alpha'), 'selected XML text rollups should not expose text content');
        $t->true(!str_contains((string) $encodedReview, 'hidden-beta'), 'selected XML CDATA rollups should not expose CDATA content');
        $t->true(!str_contains((string) $encodedReview, 'hidden-gamma'), 'selected XML theme CDATA rollups should not expose CDATA content');
        $t->true(!str_contains((string) $encodedReview, 'Text Theme'), 'selected XML text rollups should not expose selected part attribute values');
    },
];

/**
 * @return array<string, string>
 */
function docx_selected_xml_text_cdata_fixture_parts(string $settingsText, string $settingsCdata, string $themeCdata): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/theme/text-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
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
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml?review=text#settings"/>
  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/text-theme.xml?review=cdata#theme"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Selected XML text fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Selected XML Text Review</dc:title>
</cp:coreProperties>
XML,
        'word/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Normal"/>
</w:styles>
XML,
        'word/numbering.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="0"/>
</w:numbering>
XML,
        'word/settings.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docVars><w:docVar>{$settingsText}</w:docVar></w:docVars>
  <w:compat><![CDATA[{$settingsCdata}]]></w:compat>
</w:settings>
XML,
        'word/theme/text-theme.xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Text Theme">
  <a:themeElements><![CDATA[{$themeCdata}]]></a:themeElements>
</a:theme>
XML,
    ];
}
