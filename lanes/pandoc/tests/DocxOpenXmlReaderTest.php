<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

return [
    'maps docx core properties styles hyperlinks numbering and media from package parts' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_openxml_reader_fixture_parts());

        $meta = $document->attr('meta');
        $docx = $document->attr('docx');
        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $ordered = $document->children[2];
        $bullet = $document->children[3];
        $imageParagraph = $document->children[4];
        $table = $document->children[5];

        $t->same('Imported DOCX Batch', $meta['title']);
        $t->same(['Migration Editor'], $meta['authors']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $docx['contentTypes']['overrides']['word/document.xml']);
        $t->same('docProps/core.xml', $docx['corePropertiesPart']);
        $t->same('word/styles.xml', $docx['stylesPart']);
        $t->same('rCore', $docx['corePropertiesRelationship']['id']);
        $t->same('docProps/core.xml', $docx['corePropertiesRelationship']['targetPart']);
        $t->true(!isset($docx['stylesRelationship']), 'conventional DOCX styles fallback should not invent relationship metadata');
        $t->same('word/numbering.xml', $docx['numberingPart']);
        $t->true(!isset($docx['numberingRelationship']), 'conventional DOCX numbering fallback should not invent relationship metadata');
        $t->same('image/png', $docx['media']['word/media/review.png']['contentType']);
        $t->same(strlen('fake png bytes'), $docx['media']['word/media/review.png']['size']);

        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('imported-docx-heading', $heading->attr('id'));
        $t->same('Heading 1', $heading->attr('docxStyleName'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[3]->type);
        $t->same('underline', $paragraph->children[5]->type);
        $t->same('strikeout', $paragraph->children[7]->type);
        $t->same('superscript', $paragraph->children[9]->type);
        $t->same('link', $paragraph->children[11]->type);
        $t->same('https://example.test/source?post=42', $paragraph->children[11]->attr('url'));
        $t->same('rLink', $paragraph->children[11]->attr('relationshipId'));

        $t->same('ordered_list', $ordered->type);
        $t->same(3, $ordered->attr('start'));
        $t->same('upper_roman', $ordered->attr('style'));
        $t->same('one_paren', $ordered->attr('delimiter'));
        $t->same('First review step', $ordered->children[0]->children[0]->attr('text'));
        $t->same('Second review step', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->same('•', $bullet->attr('bulletChar'));
        $t->same('Bullet media note', $bullet->children[0]->children[0]->attr('text'));

        $image = $imageParagraph->children[1];
        $t->same('image', $image->type);
        $t->same('word/media/review.png', $image->attr('url'));
        $t->same('Review screenshot', $image->attr('alt'));
        $t->same('Review image', $image->attr('title'));
        $t->same('image/png', $image->attr('contentType'));
        $t->same('table', $table->type);
        $t->same('Reviewer', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same(2, $table->children[0]->children[0]->children[1]->attr('colspan'));
    },
    'resolves docx numbering from the document relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/review-numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="review-numbering.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/review-numbering.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="30">
    <w:lvl w:ilvl="0"><w:start w:val="4"/><w:numFmt w:val="upperLetter"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="40">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="30"/></w:num>
  <w:num w:numId="8"><w:abstractNumId w:val="40"/></w:num>
</w:numbering>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $ordered = $document->children[2];
        $bullet = $document->children[3];

        $t->same('word/review-numbering.xml', $docx['numberingPart']);
        $t->same('rNumbering', $docx['numberingRelationship']['id']);
        $t->same('word/document.xml', $docx['numberingRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['numberingRelationship']['relationshipsPart']);
        $t->same('review-numbering.xml', $docx['numberingRelationship']['target']);
        $t->same('word/review-numbering.xml', $docx['numberingRelationship']['targetPart']);
        $t->same(true, $docx['numberingRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', $docx['numberingRelationship']['contentType']);
        $t->same('ordered_list', $ordered->type);
        $t->same(4, $ordered->attr('start'));
        $t->same('upper_alpha', $ordered->attr('style'));
        $t->same('period', $ordered->attr('delimiter'));
        $t->same('bullet_list', $bullet->type);
        $t->same('-', $bullet->attr('bulletChar'));
    },
    'resolves docx styles and core properties from relationship targets' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/review-core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/theme/review-styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            'Target="docProps/core.xml"',
            'Target="customXml/review-core.xml"',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="theme/review-styles.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['customXml/review-core.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:title>Relationship DOCX Batch</dc:title>
  <dc:creator>Relationship Editor</dc:creator>
</cp:coreProperties>
XML;
        $parts['word/theme/review-styles.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="Relationship Heading"/>
    <w:pPr><w:outlineLvl w:val="1"/></w:pPr>
  </w:style>
</w:styles>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $meta = $document->attr('meta');
        $docx = $document->attr('docx');
        $heading = $document->children[0];

        $t->same('Relationship DOCX Batch', $meta['title']);
        $t->same(['Relationship Editor'], $meta['authors']);
        $t->same('customXml/review-core.xml', $docx['corePropertiesPart']);
        $t->same('rCore', $docx['corePropertiesRelationship']['id']);
        $t->same('/', $docx['corePropertiesRelationship']['sourcePart']);
        $t->same('_rels/.rels', $docx['corePropertiesRelationship']['relationshipsPart']);
        $t->same('customXml/review-core.xml', $docx['corePropertiesRelationship']['target']);
        $t->same('customXml/review-core.xml', $docx['corePropertiesRelationship']['targetPart']);
        $t->same(true, $docx['corePropertiesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $docx['corePropertiesRelationship']['contentType']);
        $t->same('word/theme/review-styles.xml', $docx['stylesPart']);
        $t->same('rStyles', $docx['stylesRelationship']['id']);
        $t->same('word/document.xml', $docx['stylesRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['stylesRelationship']['relationshipsPart']);
        $t->same('theme/review-styles.xml', $docx['stylesRelationship']['target']);
        $t->same('word/theme/review-styles.xml', $docx['stylesRelationship']['targetPart']);
        $t->same(true, $docx['stylesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $docx['stylesRelationship']['contentType']);
        $t->same(2, $heading->attr('level'));
        $t->same('Relationship Heading', $heading->attr('docxStyleName'));
    },
    'resolves docx extended package properties from relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/review-app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rApp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="customXml/review-app.xml?profile=review#app"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['customXml/review-app.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Template>Review.dotm</Template>
  <Manager>Migration Lead</Manager>
  <Company>WordPress Migration Desk</Company>
  <Pages>12</Pages>
  <Words>3456</Words>
  <Characters>12000</Characters>
  <CharactersWithSpaces>13025</CharactersWithSpaces>
  <Lines>123</Lines>
  <Paragraphs>48</Paragraphs>
  <DocSecurity>4</DocSecurity>
  <Application>Microsoft Word</Application>
  <AppVersion>16.0000</AppVersion>
  <HyperlinkBase>https://example.test/review/</HyperlinkBase>
  <ScaleCrop>false</ScaleCrop>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>0</SharedDoc>
  <HyperlinksChanged>true</HyperlinksChanged>
  <HeadingPairs>
    <vt:vector size="4" baseType="variant">
      <vt:variant><vt:lpstr>Title</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>2</vt:i4></vt:variant>
      <vt:variant><vt:lpstr>Heading 1</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>4</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="2" baseType="lpstr">
      <vt:lpstr>DOCX source packet</vt:lpstr>
      <vt:lpstr>Reviewer checklist</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
</Properties>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $meta = $document->attr('meta');
        $docx = $document->attr('docx');
        $extended = $docx['extendedProperties'];

        $t->same('customXml/review-app.xml', $docx['extendedPropertiesPart']);
        $t->same('rApp', $docx['extendedPropertiesRelationship']['id']);
        $t->same('/', $docx['extendedPropertiesRelationship']['sourcePart']);
        $t->same('_rels/.rels', $docx['extendedPropertiesRelationship']['relationshipsPart']);
        $t->same('customXml/review-app.xml?profile=review#app', $docx['extendedPropertiesRelationship']['target']);
        $t->same('customXml/review-app.xml?profile=review#app', $docx['extendedPropertiesRelationship']['resolvedTarget']);
        $t->same('customXml/review-app.xml', $docx['extendedPropertiesRelationship']['targetPart']);
        $t->same(true, $docx['extendedPropertiesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.extended-properties+xml', $docx['extendedPropertiesRelationship']['contentType']);
        $t->same('Review.dotm', $extended['template']);
        $t->same('Migration Lead', $extended['manager']);
        $t->same('WordPress Migration Desk', $extended['company']);
        $t->same(12, $extended['pages']);
        $t->same(3456, $extended['words']);
        $t->same(12000, $extended['characters']);
        $t->same(13025, $extended['charactersWithSpaces']);
        $t->same(123, $extended['lines']);
        $t->same(48, $extended['paragraphs']);
        $t->same(4, $extended['docSecurity']);
        $t->same('Microsoft Word', $extended['application']);
        $t->same('16.0000', $extended['appVersion']);
        $t->same('https://example.test/review/', $extended['hyperlinkBase']);
        $t->same(false, $extended['scaleCrop']);
        $t->same(false, $extended['linksUpToDate']);
        $t->same(false, $extended['sharedDoc']);
        $t->same(true, $extended['hyperlinksChanged']);
        $t->same('Title', $extended['headingPairs'][0]['name']);
        $t->same(2, $extended['headingPairs'][0]['count']);
        $t->same('Heading 1', $extended['headingPairs'][1]['name']);
        $t->same(4, $extended['headingPairs'][1]['count']);
        $t->same(['DOCX source packet', 'Reviewer checklist'], $extended['titlesOfParts']);
        $t->same('Microsoft Word', $meta['docxExtendedProperties']['application']);
    },
    'resolves docx settings and font table from relationship targets' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' . "\n" .
            '  <Override PartName="/word/fonts/review-fonts.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../docSettings/review-settings.xml?profile=team#settings"/>' . "\n" .
            '  <Relationship Id="rFontTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fonts/review-fonts.xml#fontTable"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['docSettings/review-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:trackRevisions/>
  <w:doNotTrackMoves w:val="true"/>
  <w:doNotTrackFormatting w:val="0"/>
  <w:evenAndOddHeaders/>
  <w:updateFields w:val="1"/>
  <w:defaultTabStop w:val="720"/>
  <w:decimalSymbol w:val=","/>
  <w:listSeparator w:val=";"/>
  <w:zoom w:percent="125" w:val="bestFit"/>
  <w:documentProtection w:edit="readOnly" w:enforcement="1" w:cryptProviderType="rsaFull" w:cryptAlgorithmClass="hash" w:cryptAlgorithmType="typeAny" w:cryptAlgorithmSid="14" w:cryptSpinCount="100000"/>
  <w:compat>
    <w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/>
  </w:compat>
  <w:docVars>
    <w:docVar w:name="ReviewBatch" w:val="wp-import"/>
  </w:docVars>
</w:settings>
XML;
        $parts['word/fonts/review-fonts.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:font w:name="Aptos">
    <w:altName w:val="Body Font"/>
    <w:charset w:val="00"/>
    <w:family w:val="swiss"/>
    <w:pitch w:val="variable"/>
    <w:panose1 w:val="020F0502020204030204"/>
  </w:font>
  <w:font w:name="Courier New">
    <w:charset w:val="00"/>
    <w:family w:val="modern"/>
    <w:pitch w:val="fixed"/>
  </w:font>
</w:fonts>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $settings = $docx['settings'];
        $fontTable = $docx['fontTable'];

        $t->same('docSettings/review-settings.xml', $docx['settingsPart']);
        $t->same('rSettings', $docx['settingsRelationship']['id']);
        $t->same('word/document.xml', $docx['settingsRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['settingsRelationship']['relationshipsPart']);
        $t->same('../docSettings/review-settings.xml?profile=team#settings', $docx['settingsRelationship']['target']);
        $t->same('docSettings/review-settings.xml?profile=team#settings', $docx['settingsRelationship']['resolvedTarget']);
        $t->same('docSettings/review-settings.xml', $docx['settingsRelationship']['targetPart']);
        $t->same(true, $docx['settingsRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $docx['settingsRelationship']['contentType']);
        $t->same(true, $settings['trackRevisions']);
        $t->same(true, $settings['doNotTrackMoves']);
        $t->same(false, $settings['doNotTrackFormatting']);
        $t->same(true, $settings['evenAndOddHeaders']);
        $t->same(true, $settings['updateFields']);
        $t->same(720, $settings['defaultTabStopTwips']);
        $t->same(',', $settings['decimalSymbol']);
        $t->same(';', $settings['listSeparator']);
        $t->same(125, $settings['zoom']['percent']);
        $t->same('bestFit', $settings['zoom']['value']);
        $t->same('readOnly', $settings['documentProtection']['edit']);
        $t->same(true, $settings['documentProtection']['enforcement']);
        $t->same('rsaFull', $settings['documentProtection']['cryptProviderType']);
        $t->same(14, $settings['documentProtection']['cryptAlgorithmSid']);
        $t->same(100000, $settings['documentProtection']['cryptSpinCount']);
        $t->same('compatibilityMode', $settings['compatibility'][0]['name']);
        $t->same('15', $settings['compatibility'][0]['value']);
        $t->same(['ReviewBatch' => 'wp-import'], $settings['documentVariables']);

        $t->same('word/fonts/review-fonts.xml', $docx['fontTablePart']);
        $t->same('rFontTable', $docx['fontTableRelationship']['id']);
        $t->same('fonts/review-fonts.xml#fontTable', $docx['fontTableRelationship']['target']);
        $t->same('word/fonts/review-fonts.xml#fontTable', $docx['fontTableRelationship']['resolvedTarget']);
        $t->same('word/fonts/review-fonts.xml', $docx['fontTableRelationship']['targetPart']);
        $t->same(true, $docx['fontTableRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml', $docx['fontTableRelationship']['contentType']);
        $t->same(2, $fontTable['fontCount']);
        $t->same(['Aptos', 'Courier New'], $fontTable['declaredNames']);
        $t->same('Body Font', $fontTable['byName']['Aptos']['alternateName']);
        $t->same('swiss', $fontTable['byName']['Aptos']['family']);
        $t->same('variable', $fontTable['byName']['Aptos']['pitch']);
        $t->same('020F0502020204030204', $fontTable['byName']['Aptos']['panose1']);
        $t->same('modern', $fontTable['byName']['Courier New']['family']);
        $t->same('fixed', $fontTable['byName']['Courier New']['pitch']);
    },
    'resolves docx web settings from relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/web/review-web-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rWebSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings" Target="web/review-web-settings.xml?profile=browser#web"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/web/review-web-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:webSettings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:optimizeForBrowser/>
  <w:allowPNG w:val="0"/>
  <w:relyOnVML w:val="off"/>
  <w:doNotRelyOnCSS w:val="1"/>
  <w:doNotSaveAsSingleFile/>
  <w:doNotOrganizeInFolder w:val="true"/>
  <w:doNotUseLongFileNames w:val="false"/>
  <w:encoding w:val="UTF-8"/>
  <w:targetScreenSz w:val="1024x768"/>
  <w:pixelsPerInch w:val="144"/>
</w:webSettings>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $webSettings = $docx['webSettings'];

        $t->same('word/web/review-web-settings.xml', $docx['webSettingsPart']);
        $t->same('rWebSettings', $docx['webSettingsRelationship']['id']);
        $t->same('word/document.xml', $docx['webSettingsRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['webSettingsRelationship']['relationshipsPart']);
        $t->same('web/review-web-settings.xml?profile=browser#web', $docx['webSettingsRelationship']['target']);
        $t->same('word/web/review-web-settings.xml?profile=browser#web', $docx['webSettingsRelationship']['resolvedTarget']);
        $t->same('word/web/review-web-settings.xml', $docx['webSettingsRelationship']['targetPart']);
        $t->same(true, $docx['webSettingsRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml', $docx['webSettingsRelationship']['contentType']);
        $t->same(true, $webSettings['optimizeForBrowser']);
        $t->same(false, $webSettings['allowPng']);
        $t->same(false, $webSettings['relyOnVml']);
        $t->same(true, $webSettings['doNotRelyOnCss']);
        $t->same(true, $webSettings['doNotSaveAsSingleFile']);
        $t->same(true, $webSettings['doNotOrganizeInFolder']);
        $t->same(false, $webSettings['doNotUseLongFileNames']);
        $t->same('UTF-8', $webSettings['encoding']);
        $t->same('1024x768', $webSettings['targetScreenSize']);
        $t->same(144, $webSettings['pixelsPerInch']);
    },
    'resolves docx theme font and color scheme from relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/theme/review-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/review-theme.xml?variant=wp#theme"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/theme/review-theme.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="WordPress Review Theme">
  <a:themeElements>
    <a:clrScheme name="Review Colors">
      <a:dk1><a:sysClr val="windowText" lastClr="111111"/></a:dk1>
      <a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>
      <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
      <a:accent2><a:prstClr val="orange"/></a:accent2>
      <a:hlink><a:srgbClr val="0563C1"/></a:hlink>
      <a:folHlink><a:schemeClr val="accent1"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="Review Fonts">
      <a:majorFont>
        <a:latin typeface="Aptos Display"/>
        <a:ea typeface="Yu Gothic"/>
        <a:cs typeface="Arial"/>
      </a:majorFont>
      <a:minorFont>
        <a:latin typeface="Aptos"/>
        <a:ea typeface="Meiryo"/>
        <a:cs typeface="Times New Roman"/>
      </a:minorFont>
    </a:fontScheme>
  </a:themeElements>
</a:theme>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $theme = $docx['theme'];

        $t->same('word/theme/review-theme.xml', $docx['themePart']);
        $t->same('rTheme', $docx['themeRelationship']['id']);
        $t->same('word/document.xml', $docx['themeRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['themeRelationship']['relationshipsPart']);
        $t->same('theme/review-theme.xml?variant=wp#theme', $docx['themeRelationship']['target']);
        $t->same('word/theme/review-theme.xml?variant=wp#theme', $docx['themeRelationship']['resolvedTarget']);
        $t->same('word/theme/review-theme.xml', $docx['themeRelationship']['targetPart']);
        $t->same(true, $docx['themeRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.theme+xml', $docx['themeRelationship']['contentType']);
        $t->same('WordPress Review Theme', $theme['name']);
        $t->same('Review Fonts', $theme['fonts']['schemeName']);
        $t->same('Aptos Display', $theme['fonts']['majorLatin']);
        $t->same('Yu Gothic', $theme['fonts']['majorEastAsia']);
        $t->same('Arial', $theme['fonts']['majorComplexScript']);
        $t->same('Aptos', $theme['fonts']['minorLatin']);
        $t->same('Meiryo', $theme['fonts']['minorEastAsia']);
        $t->same('Times New Roman', $theme['fonts']['minorComplexScript']);
        $t->same('Review Colors', $theme['colors']['schemeName']);
        $t->same(6, $theme['colors']['count']);
        $t->same('system', $theme['colors']['items'][0]['kind']);
        $t->same('windowText', $theme['colors']['items'][0]['value']);
        $t->same('111111', $theme['colors']['byName']['text1']);
        $t->same('4472C4', $theme['colors']['byName']['accent1']);
        $t->same('0563C1', $theme['colors']['byName']['hyperlink']);
        $t->same('preset', $theme['colors']['items'][3]['kind']);
        $t->same('orange', $theme['colors']['items'][3]['value']);
        $t->same('scheme', $theme['colors']['items'][5]['kind']);
        $t->same('accent1', $theme['colors']['items'][5]['value']);
    },
    'reads a native zip docx package without shelling out' => static function (TestRunner $t): void {
        $path = docx_openxml_reader_temp_docx(docx_openxml_reader_fixture_parts());
        try {
            $document = (new DocxOpenXmlReader())->readFile($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $t->same('document', $document->type);
        $t->same('Imported DOCX Heading', $document->children[0]->attr('text'));
        $t->same('ordered_list', $document->children[2]->type);
        $t->same('word/media/review.png', $document->children[4]->children[1]->attr('mediaPath'));
    },
    'reads a bounded ZipPackage docx package without shelling out' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readZipPackage(
            docx_openxml_reader_zip_package(docx_openxml_reader_fixture_parts())
        );
        $docx = $document->attr('docx');
        $image = $document->children[4]->children[1];

        $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same('word/media/review.png', $image->attr('mediaPath'));
        $t->same('image/png', $image->attr('contentType'));
        $t->same(strlen('fake png bytes'), $docx['media']['word/media/review.png']['size']);
    },
    'renders docx reader ast through markdown and wordpress writers' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_openxml_reader_fixture_parts());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# Imported DOCX Heading', $markdown);
        $t->contains('[source link](https://example.test/source?post=42)', $markdown);
        $t->contains('III) First review step', $markdown);
        $t->contains('![Review screenshot](word/media/review.png "Review image")', $markdown);
        $t->contains('<h1 id="imported-docx-heading">Imported DOCX Heading</h1>', $blocks);
        $t->contains('<a href="https://example.test/source?post=42">source link</a>', $blocks);
        $t->contains('<ol start="3" type="I">', $blocks);
        $t->contains('<img src="word/media/review.png" alt="Review screenshot" title="Review image"/>', $blocks);
        $t->contains('<td colspan="2"><p>Approved</p></td>', $blocks);
    },
    'rejects malformed or incomplete docx packages with bounded diagnostics' => static function (TestRunner $t): void {
        $reader = new DocxOpenXmlReader();

        $t->throws(RuntimeException::class, static fn (): AstNode => $reader->readPackage([]));
        $t->throws(RuntimeException::class, static fn (): AstNode => $reader->readPackage([
            'word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>',
        ]));
    },
];

/**
 * @return array<string, string>
 */
function docx_openxml_reader_fixture_parts(): array
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
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:title>Imported DOCX Batch</dc:title>
  <dc:creator>Migration Editor</dc:creator>
  <dc:description>WordPress DOCX import fixture</dc:description>
  <cp:keywords>docx,wordpress,review</cp:keywords>
  <dcterms:created>2026-06-09T12:00:00Z</dcterms:created>
  <dcterms:modified>2026-06-09T12:30:00Z</dcterms:modified>
</cp:coreProperties>
XML,
        'word/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="Heading 1"/>
    <w:pPr><w:outlineLvl w:val="0"/></w:pPr>
  </w:style>
</w:styles>
XML,
        'word/numbering.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="upperRoman"/><w:lvlText w:val="%1)"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="20">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7">
    <w:abstractNumId w:val="10"/>
    <w:lvlOverride w:ilvl="0"><w:startOverride w:val="3"/></w:lvlOverride>
  </w:num>
  <w:num w:numId="8"><w:abstractNumId w:val="20"/></w:num>
</w:numbering>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source?post=42" TargetMode="External"/>
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Imported DOCX Heading</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Plain </w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>underlined</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:strike/></w:rPr><w:t>removed</w:t></w:r>
      <w:r><w:t xml:space="preserve"> draft</w:t></w:r>
      <w:r><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:t>2</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>
    </w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>First review step</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Second review step</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="8"/></w:numPr></w:pPr><w:r><w:t>Bullet media note</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Inline media </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="1" name="Review image" title="Review image" descr="Review screenshot"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:tbl>
      <w:tr><w:tc><w:p><w:r><w:t>Reviewer</w:t></w:r></w:p></w:tc><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>Approved</w:t></w:r></w:p></w:tc></w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'fake png bytes',
    ];
}

/**
 * @param array<string, string> $parts
 * @return list<array{name:string, data:string}>
 */
function docx_openxml_reader_zip_parts(array $parts): array
{
    $zipParts = [];
    foreach ($parts as $name => $contents) {
        $zipParts[] = [
            'name' => $name,
            'data' => $contents,
        ];
    }

    return $zipParts;
}

/**
 * @param array<string, string> $parts
 */
function docx_openxml_reader_zip_package(array $parts): ZipPackage
{
    return ZipPackage::fromParts(docx_openxml_reader_zip_parts($parts));
}

/**
 * @param array<string, string> $parts
 */
function docx_openxml_reader_temp_docx(array $parts): string
{
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to allocate temporary DOCX path');
    }

    $written = file_put_contents($path, ZipPackage::build(docx_openxml_reader_zip_parts($parts)));
    if ($written === false) {
        throw new RuntimeException('Unable to write temporary DOCX package');
    }

    return $path;
}
