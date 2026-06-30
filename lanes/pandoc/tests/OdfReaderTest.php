<?php

declare(strict_types=1);

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="Heading_20_1" style:family="paragraph" style:display-name="Heading 1" style:default-outline-level="1"/>
    <style:style style:name="BodyText" style:family="paragraph" style:display-name="Body Text"/>
    <style:style style:name="BaseStrong" style:family="text">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="StrongEmphasis" style:family="text" style:parent-style-name="BaseStrong">
      <style:text-properties fo:font-style="italic" style:text-underline-style="solid"/>
    </style:style>
    <style:style style:name="NarrowColumn" style:family="table-column">
      <style:table-column-properties style:column-width="2cm"/>
    </style:style>
    <style:style style:name="WideColumn" style:family="table-column">
      <style:table-column-properties style:column-width="4cm"/>
    </style:style>
    <text:list-style style:name="NumberedReview">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:automatic-styles>
    <style:style style:name="AutoHeading" style:family="paragraph" style:parent-style-name="Heading_20_1" style:display-name="Auto Review Heading"/>
    <text:list-style style:name="BulletReview">
      <text:list-level-style-bullet text:level="1" text:bullet-char="*"/>
    </text:list-style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:h text:outline-level="1" text:style-name="AutoHeading">Imported ODT Packet</text:h>
      <text:p text:style-name="BodyText">Reviewer <text:span text:style-name="StrongEmphasis">summary</text:span><text:s text:c="2"/>keeps <text:a xlink:href="https://example.test/source.odt">source link</text:a><text:line-break/>next line<office:annotation><dc:creator>Migration Desk</dc:creator><dc:date>2026-06-04T22:10:00Z</dc:date><text:p>Annotation for reviewers.</text:p></office:annotation></text:p>
      <text:list text:style-name="NumberedReview">
        <text:list-item><text:p>Legal review</text:p></text:list-item>
        <text:list-item><text:p>Publish packet</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="BulletReview">
        <text:list-item><text:p>Confirm media map</text:p></text:list-item>
      </text:list>
      <text:section text:name="Sidebar">
        <text:p>Section packet note.</text:p>
      </text:section>
      <draw:frame draw:name="Hero">
        <draw:image xlink:href="Pictures/hero.png">
          <svg:title>Hero title</svg:title>
          <svg:desc>Hero alt text</svg:desc>
        </draw:image>
      </draw:frame>
      <draw:frame draw:name="Pull quote">
        <draw:text-box>
          <text:p>Text box source note.</text:p>
        </draw:text-box>
      </draw:frame>
      <table:table table:name="Audit">
        <table:table-column table:style-name="NarrowColumn"/>
        <table:table-column table:style-name="WideColumn"/>
        <table:table-header-rows>
          <table:table-row>
            <table:table-cell><text:p>Status</text:p></table:table-cell>
            <table:table-cell><text:p>Owner</text:p></table:table-cell>
          </table:table-row>
        </table:table-header-rows>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2"><text:p>Ready for review</text:p></table:table-cell>
          <table:covered-table-cell/>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>ODT Import Packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:description>Source ODT for WordPress import review</dc:description>
    <dc:language>en</dc:language>
    <dc:date>2026-06-04T22:00:00Z</dc:date>
    <meta:keyword>migration</meta:keyword>
    <meta:keyword>odt</meta:keyword>
    <meta:initial-creator>Data Liberation</meta:initial-creator>
    <meta:creation-date>2026-06-04T21:30:00Z</meta:creation-date>
    <meta:editing-cycles>7</meta:editing-cycles>
    <meta:document-statistic meta:page-count="2" meta:word-count="128" meta:paragraph-count="9" meta:image-count="1"/>
    <meta:user-defined meta:name="wp-source-id">packet-42</meta:user-defined>
  </office:meta>
</office:document-meta>
XML;

$buildOdtPackage = static function (
    ?string $overrideContentXml = null,
    ?string $overrideManifestXml = null,
    ?string $overrideStylesXml = null,
    ?string $overrideMetaXml = null,
    array $extraParts = []
) use ($contentXml, $manifestXml, $stylesXml, $metaXml): ZipPackage {
    return ZipPackage::fromParts(array_merge([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $overrideManifestXml ?? $manifestXml],
        ['name' => 'content.xml', 'data' => $overrideContentXml ?? $contentXml],
        ['name' => 'styles.xml', 'data' => $overrideStylesXml ?? $stylesXml],
        ['name' => 'meta.xml', 'data' => $overrideMetaXml ?? $metaXml],
        ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ], $extraParts));
};

$buildZipPackageWithCentralDirectoryOrder = static function (array $parts, array $centralOrder): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $centralRecords = [];

    foreach ($parts as $part) {
        $name = $part['name'];
        $rawName = $part['rawName'] ?? $name;
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $part['generalPurposeFlags'] ?? 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0
        );
        $body .= $rawName . $compressed;

        $centralRecords[$name] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $rawName;
    }

    $central = '';
    foreach ($centralOrder as $name) {
        if (!isset($centralRecords[$name])) {
            throw new RuntimeException("Missing central directory record for {$name}");
        }

        $central .= $centralRecords[$name];
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
};

return [
    'reads ODT manifest metadata styles and package media' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $result = (new OdfReader())->readPackage($buildOdtPackage());
        $document = $result['document'];

        $t->same('odt', $document->attr('source'));
        $t->same('ODT Import Packet', $document->attr('title'));
        $t->same('ODT Import Packet', $result['metadata']['title']);
        $t->same('Migration Desk', $result['metadata']['creator']);
        $t->same('Source ODT for WordPress import review', $result['metadata']['description']);
        $t->same('en', $result['metadata']['language']);
        $t->same('2026-06-04T22:00:00Z', $result['metadata']['date']);
        $t->same(['migration', 'odt'], $result['metadata']['keywords']);
        $t->same('Data Liberation', $result['metadata']['initialCreator']);
        $t->same('2026-06-04T21:30:00Z', $result['metadata']['created']);
        $t->same('7', $result['metadata']['editingCycles']);
        $t->same(2, $result['metadata']['statistics']['pageCount']);
        $t->same(128, $result['metadata']['statistics']['wordCount']);
        $t->same('packet-42', $result['metadata']['userDefined']['wp-source-id']);

        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $t->same(OdfReader::MIMETYPE, $manifestByPath['/']['mediaType']);
        $t->same('1.3', $manifestByPath['/']['version']);
        $t->same('edit', $manifestByPath['/']['preferredViewMode']);
        $t->same('1.3', $document->attr('manifest')['version']);
        $t->same('1.3', $result['importReport']['manifest']['version']);
        $t->same(true, $manifestByPath['content.xml']['exists']);
        $t->same('text/xml', $manifestByPath['styles.xml']['mediaType']);
        $t->same(true, $manifestByPath['Pictures/hero.png']['exists']);
        $t->same('image/png', $manifestByPath['Pictures/hero.png']['mediaType']);
        $t->same(7, $manifestByPath['Pictures/hero.png']['byteLength']);

        $t->same(1, count($result['media']));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $t->same('image/png', $result['media'][0]['mediaType']);
        $t->same(true, $result['media'][0]['exists']);
        $t->same(7, $result['media'][0]['byteLength']);

        $t->same('BaseStrong', $result['styles']['StrongEmphasis']['parentName']);
        $t->same(true, $document->children[1]->children[1]->children[0]->children[0]->children[0]->attr('styleName') === 'StrongEmphasis');
        $t->same(2, count($result['listStyles']));
        $t->same(5, $result['importReport']['manifest']['count']);
        $t->same(0, count($result['importReport']['manifest']['missingItems']));
    },
    'reports ODT style reference diagnostics for reviewer handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPlainParagraph = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Style diagnostics packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $stylesWithBrokenReferences = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0">
  <office:font-face-decls>
    <style:font-face style:name="DeclaredFont"/>
  </office:font-face-decls>
  <office:automatic-styles>
    <number:number-style style:name="ExistingNumber">
      <number:number number:decimal-places="2"/>
    </number:number-style>
    <style:page-layout style:name="ExistingLayout"/>
    <style:page-layout style:name="BrokenRegisterTruthLayout">
      <style:page-layout-properties style:register-truth-ref-style-name="MissingRegisterStyle"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="BrokenParagraph" style:family="paragraph" style:parent-style-name="MissingParent" style:next-style-name="MissingNextParagraph" style:list-style-name="MissingList" style:master-page-name="MissingMaster" style:data-style-name="MissingNumber">
      <style:text-properties style:font-name="MissingFont"/>
    </style:style>
    <style:style style:name="CycleA" style:family="paragraph" style:parent-style-name="CycleB"/>
    <style:style style:name="CycleB" style:family="paragraph" style:parent-style-name="CycleA"/>
    <style:style style:name="MappedCell" style:family="table-cell">
      <style:map style:condition="value() &gt; 0" style:apply-style-name="MissingCell"/>
    </style:style>
    <text:list-style style:name="BrokenList">
      <text:list-level-style-bullet text:level="1" text:bullet-char="*">
        <style:text-properties style:font-name="MissingBulletFont"/>
      </text:list-level-style-bullet>
    </text:list-style>
    <table:table-template table:name="AuditTemplate" table:first-row="MissingHeaderStyle" table:body="MappedCell"/>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="ReviewMaster" style:page-layout-name="MissingLayout" style:next-style-name="MissingNextMaster" draw:style-name="MissingDraw"/>
  </office:master-styles>
</office:document-styles>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPlainParagraph, null, $stylesWithBrokenReferences));
        $styleReport = $result['importReport']['styles'];
        $documentStyles = $result['document']->attr('styles');
        $diagnostics = $styleReport['diagnostics'];
        $diagnosticsByCode = [];
        foreach ($diagnostics as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']][] = $diagnostic;
        }

        $t->same(4, $styleReport['count']);
        $t->same(1, $styleReport['styleMapCount']);
        $t->same(14, $styleReport['diagnosticCount']);
        $t->same($styleReport['diagnosticCount'], $documentStyles['diagnosticCount']);
        $t->same($styleReport['diagnosticCodeCounts'], $documentStyles['diagnosticCodeCounts']);
        $t->same($diagnostics, $documentStyles['diagnostics']);
        $t->same([
            'odf-list-style-missing-font-face' => 1,
            'odf-master-page-missing-draw-style' => 1,
            'odf-master-page-missing-next-master-page' => 1,
            'odf-master-page-missing-page-layout' => 1,
            'odf-page-layout-missing-register-truth-style' => 1,
            'odf-style-map-missing-target' => 1,
            'odf-style-missing-data-style' => 1,
            'odf-style-missing-font-face' => 1,
            'odf-style-missing-list-style' => 1,
            'odf-style-missing-master-page' => 1,
            'odf-style-missing-next-style' => 1,
            'odf-style-missing-parent' => 1,
            'odf-style-parent-cycle' => 1,
            'odf-table-template-missing-style' => 1,
        ], $styleReport['diagnosticCodeCounts']);
        $t->same('BrokenParagraph', $diagnosticsByCode['odf-style-missing-parent'][0]['styleName']);
        $t->same('MissingParent', $diagnosticsByCode['odf-style-missing-parent'][0]['parentName']);
        $t->same('MissingNextParagraph', $diagnosticsByCode['odf-style-missing-next-style'][0]['nextStyleName']);
        $t->same('MissingCell', $diagnosticsByCode['odf-style-map-missing-target'][0]['applyStyleName']);
        $t->same(['CycleA', 'CycleB', 'CycleA'], $diagnosticsByCode['odf-style-parent-cycle'][0]['cyclePath']);
        $t->same('MissingHeaderStyle', $diagnosticsByCode['odf-table-template-missing-style'][0]['styleName']);
        $t->same('MissingLayout', $diagnosticsByCode['odf-master-page-missing-page-layout'][0]['pageLayoutName']);
        $t->same('BrokenRegisterTruthLayout', $diagnosticsByCode['odf-page-layout-missing-register-truth-style'][0]['pageLayoutName']);
        $t->same('MissingRegisterStyle', $diagnosticsByCode['odf-page-layout-missing-register-truth-style'][0]['registerTruthRefStyleName']);
        $t->same('MissingBulletFont', $diagnosticsByCode['odf-list-style-missing-font-face'][0]['fontName']);
    },
    'reports ODT page layout register truth style diagnostics' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPlainParagraph = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Page layout diagnostics packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $stylesWithRegisterTruthReferences = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:automatic-styles>
    <style:page-layout style:name="ValidRegisterTruthLayout">
      <style:page-layout-properties style:register-truth-ref-style-name="ExistingParagraph"/>
    </style:page-layout>
    <style:page-layout style:name="BrokenRegisterTruthLayout">
      <style:page-layout-properties style:register-truth-ref-style-name="MissingRegisterStyle"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="ExistingParagraph" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPlainParagraph, null, $stylesWithRegisterTruthReferences));
        $styleReport = $result['importReport']['styles'];

        $t->same(1, $styleReport['diagnosticCount']);
        $t->same(['odf-page-layout-missing-register-truth-style' => 1], $styleReport['diagnosticCodeCounts']);
        $t->same(2, $styleReport['pageLayoutCount']);
        $t->same('ExistingParagraph', $result['pageLayouts']['ValidRegisterTruthLayout']['properties']['registerTruthRefStyleName']);
        $t->same('MissingRegisterStyle', $result['pageLayouts']['BrokenRegisterTruthLayout']['properties']['registerTruthRefStyleName']);
        $t->same('BrokenRegisterTruthLayout', $styleReport['diagnostics'][0]['pageLayoutName']);
        $t->same('MissingRegisterStyle', $styleReport['diagnostics'][0]['registerTruthRefStyleName']);
    },
    'reports ODT style family mismatch diagnostics for reviewer handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPlainParagraph = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Style family diagnostics packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $stylesWithFamilyMismatches = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0">
  <office:styles>
    <style:style style:name="ParagraphParent" style:family="paragraph"/>
    <style:style style:name="TextParent" style:family="text"/>
    <style:style style:name="ParagraphWithTextParent" style:family="paragraph" style:parent-style-name="TextParent"/>
    <style:style style:name="CellMappedToParagraph" style:family="table-cell">
      <style:map style:condition="value() &gt; 0" style:apply-style-name="ParagraphParent"/>
    </style:style>
    <style:style style:name="WrongTemplateParagraph" style:family="paragraph"/>
    <style:style style:name="WrongDrawCell" style:family="table-cell"/>
    <table:table-template table:name="FamilyTemplate" table:first-row="WrongTemplateParagraph" table:body="CellMappedToParagraph"/>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="ReviewMaster" draw:style-name="WrongDrawCell"/>
  </office:master-styles>
</office:document-styles>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPlainParagraph, null, $stylesWithFamilyMismatches));
        $styleReport = $result['importReport']['styles'];
        $diagnosticsByCode = [];
        foreach ($styleReport['diagnostics'] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']][] = $diagnostic;
        }

        $t->same(4, $styleReport['diagnosticCount']);
        $t->same([
            'odf-master-page-style-family-mismatch' => 1,
            'odf-style-map-target-family-mismatch' => 1,
            'odf-style-parent-family-mismatch' => 1,
            'odf-table-template-style-family-mismatch' => 1,
        ], $styleReport['diagnosticCodeCounts']);
        $t->same('ParagraphWithTextParent', $diagnosticsByCode['odf-style-parent-family-mismatch'][0]['styleName']);
        $t->same('TextParent', $diagnosticsByCode['odf-style-parent-family-mismatch'][0]['parentName']);
        $t->same('paragraph', $diagnosticsByCode['odf-style-parent-family-mismatch'][0]['expectedFamily']);
        $t->same('text', $diagnosticsByCode['odf-style-parent-family-mismatch'][0]['actualFamily']);
        $t->same('CellMappedToParagraph', $diagnosticsByCode['odf-style-map-target-family-mismatch'][0]['styleName']);
        $t->same('ParagraphParent', $diagnosticsByCode['odf-style-map-target-family-mismatch'][0]['applyStyleName']);
        $t->same('table-cell', $diagnosticsByCode['odf-style-map-target-family-mismatch'][0]['expectedFamily']);
        $t->same('FamilyTemplate', $diagnosticsByCode['odf-table-template-style-family-mismatch'][0]['tableTemplateName']);
        $t->same('WrongTemplateParagraph', $diagnosticsByCode['odf-table-template-style-family-mismatch'][0]['styleName']);
        $t->same('ReviewMaster', $diagnosticsByCode['odf-master-page-style-family-mismatch'][0]['masterPageName']);
        $t->same('WrongDrawCell', $diagnosticsByCode['odf-master-page-style-family-mismatch'][0]['drawStyleName']);
        $t->same('drawing-page', $diagnosticsByCode['odf-master-page-style-family-mismatch'][0]['expectedFamily']);
    },
    'reports duplicate ODT style catalog names for reviewer handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPlainParagraph = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Duplicate style diagnostics packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $stylesWithDuplicateCatalogNames = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:font-face-decls>
    <style:font-face style:name="ReviewFont" svg:font-family="'Review One'"/>
    <style:font-face style:name="ReviewFont" svg:font-family="'Review Two'"/>
  </office:font-face-decls>
  <office:automatic-styles>
    <style:page-layout style:name="DuplicateLayout"/>
    <style:page-layout style:name="DuplicateLayout"/>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="DuplicateStyle" style:family="paragraph"/>
    <style:style style:name="DuplicateStyle" style:family="text"/>
    <text:list-style style:name="DuplicateList">
      <text:list-level-style-bullet text:level="1" text:bullet-char="*"/>
    </text:list-style>
    <text:list-style style:name="DuplicateList">
      <text:list-level-style-number text:level="1" style:num-format="1"/>
    </text:list-style>
    <number:number-style style:name="DuplicateNumber">
      <number:number number:decimal-places="0"/>
    </number:number-style>
    <number:date-style style:name="DuplicateNumber">
      <number:year/>
    </number:date-style>
    <table:table-template table:name="DuplicateTemplate"/>
    <table:table-template table:name="DuplicateTemplate"/>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="DuplicateMaster" style:page-layout-name="DuplicateLayout"/>
    <style:master-page style:name="DuplicateMaster" style:page-layout-name="DuplicateLayout"/>
  </office:master-styles>
</office:document-styles>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPlainParagraph, null, $stylesWithDuplicateCatalogNames));
        $styleReport = $result['importReport']['styles'];
        $diagnosticsByCode = [];
        foreach ($styleReport['diagnostics'] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']][] = $diagnostic;
        }

        $t->same(1, $styleReport['count']);
        $t->same(1, $styleReport['fontFaceCount']);
        $t->same(1, $styleReport['dataStyleCount']);
        $t->same(7, $styleReport['diagnosticCount']);
        $t->same([
            'odf-data-style-duplicate-name' => 1,
            'odf-font-face-duplicate-name' => 1,
            'odf-list-style-duplicate-name' => 1,
            'odf-master-page-duplicate-name' => 1,
            'odf-page-layout-duplicate-name' => 1,
            'odf-style-duplicate-name' => 1,
            'odf-table-template-duplicate-name' => 1,
        ], $styleReport['diagnosticCodeCounts']);
        $t->same('DuplicateStyle', $diagnosticsByCode['odf-style-duplicate-name'][0]['styleName']);
        $t->same('paragraph', $diagnosticsByCode['odf-style-duplicate-name'][0]['previousFamily']);
        $t->same('text', $diagnosticsByCode['odf-style-duplicate-name'][0]['replacementFamily']);
        $t->same('ReviewFont', $diagnosticsByCode['odf-font-face-duplicate-name'][0]['fontFaceName']);
        $t->same('DuplicateNumber', $diagnosticsByCode['odf-data-style-duplicate-name'][0]['dataStyleName']);
        $t->same('number-style', $diagnosticsByCode['odf-data-style-duplicate-name'][0]['previousElement']);
        $t->same('date-style', $diagnosticsByCode['odf-data-style-duplicate-name'][0]['replacementElement']);
        $t->same('DuplicateTemplate', $diagnosticsByCode['odf-table-template-duplicate-name'][0]['tableTemplateName']);
        $t->same('DuplicateMaster', $diagnosticsByCode['odf-master-page-duplicate-name'][0]['masterPageName']);
    },
    'reports ODT content style-use diagnostics for reviewer handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithMissingStyleUses = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:automatic-styles>
    <style:style style:name="ExistingParagraph" style:family="paragraph"/>
    <text:list-style style:name="ExistingList">
      <text:list-level-style-bullet text:level="1" text:bullet-char="*"/>
    </text:list-style>
    <table:table-template table:name="ExistingTemplate"/>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:p text:style-name="MissingParagraph">Broken paragraph <text:span text:style-name="MissingText">span</text:span> and <text:a xlink:href="https://example.test/source" text:style-name="MissingLink" text:visited-style-name="MissingVisited">link</text:a>.</text:p>
      <text:list text:style-name="MissingList">
        <text:list-item><text:p>Missing list style item.</text:p></text:list-item>
      </text:list>
      <text:section text:style-name="MissingSection">
        <text:p>Section remains visible.</text:p>
      </text:section>
      <draw:frame draw:style-name="MissingFrame">
        <draw:text-box><text:p>Frame text.</text:p></draw:text-box>
      </draw:frame>
      <table:table table:style-name="MissingTable" table:template-name="MissingTemplate">
        <table:table-column table:style-name="MissingColumn" table:default-cell-style-name="MissingColumnDefault"/>
        <table:table-row table:style-name="MissingRow" table:default-cell-style-name="MissingRowDefault">
          <table:table-cell table:style-name="MissingCell"><text:p>Cell text.</text:p></table:table-cell>
        </table:table-row>
      </table:table>
      <text:p text:style-name="ExistingParagraph">Known styles stay quiet.</text:p>
      <text:list text:style-name="ExistingList">
        <text:list-item><text:p>Known list style stays quiet.</text:p></text:list-item>
      </text:list>
      <table:table table:template-name="ExistingTemplate">
        <table:table-row><table:table-cell><text:p>Known template stays quiet.</text:p></table:table-cell></table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithMissingStyleUses));
        $styleReport = $result['importReport']['styles'];
        $diagnostics = $styleReport['diagnostics'];
        $diagnosticsByReference = [];
        foreach ($diagnostics as $diagnostic) {
            foreach (['styleName', 'visitedStyleName', 'listStyleName', 'tableTemplateName', 'defaultCellStyleName'] as $referenceKey) {
                if (isset($diagnostic[$referenceKey])) {
                    $diagnosticsByReference[$diagnostic[$referenceKey]] = $diagnostic;
                }
            }
        }

        $t->same(14, $styleReport['diagnosticCount']);
        $t->same([
            'odf-content-missing-list-style' => 1,
            'odf-content-missing-style' => 12,
            'odf-content-missing-table-template' => 1,
        ], $styleReport['diagnosticCodeCounts']);
        $t->same('content.xml', $diagnosticsByReference['MissingParagraph']['sourcePart']);
        $t->same('text:p', $diagnosticsByReference['MissingParagraph']['element']);
        $t->same('text:style-name', $diagnosticsByReference['MissingParagraph']['attribute']);
        $t->same('text:a', $diagnosticsByReference['MissingLink']['element']);
        $t->same('text:style-name', $diagnosticsByReference['MissingLink']['attribute']);
        $t->same('text:a', $diagnosticsByReference['MissingVisited']['element']);
        $t->same('text:visited-style-name', $diagnosticsByReference['MissingVisited']['attribute']);
        $t->same('text:list', $diagnosticsByReference['MissingList']['element']);
        $t->same('table:default-cell-style-name', $diagnosticsByReference['MissingRowDefault']['attribute']);
        $t->same('table:table', $diagnosticsByReference['MissingTemplate']['element']);
        $t->same('table:template-name', $diagnosticsByReference['MissingTemplate']['attribute']);
    },
    'preserves ODT manifest version and preferred view mode provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithPreferredViewModes = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:preferred-view-mode="presentation-slide-show"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithPreferredViewModes));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $t->same('1.3', $result['document']->attr('manifest')['version']);
        $t->same('1.3', $result['importReport']['manifest']['version']);
        $t->same('edit', $manifestByPath['/']['preferredViewMode']);
        $t->same('presentation-slide-show', $manifestByPath['Pictures/hero.png']['preferredViewMode']);
        $t->same('presentation-slide-show', $result['media'][0]['preferredViewMode']);
        $t->same('presentation-slide-show', $result['importReport']['media']['items'][0]['preferredViewMode']);
    },
    'preserves ODT manifest version and preferred view mode package provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithPackageModes = str_replace(
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            ],
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:version="1.2" manifest:preferred-view-mode="page-preview"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:version="1.1" manifest:preferred-view-mode="presentation-slide-show"/>',
            ],
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithPackageModes));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $orderByPath = [];
        foreach ($provenance['manifestFileEntryOrder'] as $item) {
            $orderByPath[$item['fullPath']] = $item;
        }
        $inventory = $provenance['parts'];

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(5, $provenance['manifestFileEntryCount']);
        $t->same(3, $provenance['manifestVersionEntryCount']);
        $t->same(['1.1' => 1, '1.2' => 1, '1.3' => 1], $provenance['manifestVersionCounts']);
        $t->same(3, $provenance['manifestPreferredViewModeEntryCount']);
        $t->same([
            'edit' => 1,
            'page-preview' => 1,
            'presentation-slide-show' => 1,
        ], $provenance['manifestPreferredViewModeCounts']);
        $t->same('1.3', $orderByPath['/']['version']);
        $t->same('edit', $orderByPath['/']['preferredViewMode']);
        $t->same('1.2', $orderByPath['content.xml']['version']);
        $t->same('page-preview', $orderByPath['content.xml']['preferredViewMode']);
        $t->same('1.1', $orderByPath['Pictures/hero.png']['version']);
        $t->same('presentation-slide-show', $orderByPath['Pictures/hero.png']['preferredViewMode']);
        $t->same(null, $orderByPath['styles.xml']['version']);
        $t->same(null, $orderByPath['styles.xml']['preferredViewMode']);
        $t->same('1.2', $inventory['content.xml']['manifestVersion']);
        $t->same('page-preview', $inventory['content.xml']['manifestPreferredViewMode']);
        $t->same('1.1', $inventory['Pictures/hero.png']['manifestVersion']);
        $t->same('presentation-slide-show', $inventory['Pictures/hero.png']['manifestPreferredViewMode']);
    },
    'preserves typed ODT meta user-defined fields for package review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $metaWithTypedUserDefined = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>Typed ODT Metadata Packet</dc:title>
    <meta:user-defined meta:name="wp-source-id" meta:value-type="string" office:string-value="packet-42">packet-42</meta:user-defined>
    <meta:user-defined meta:name="requires-legal-review" meta:value-type="boolean" office:boolean-value="true"/>
    <meta:user-defined meta:name="source-score" meta:value-type="float" office:value="97.5"/>
    <meta:user-defined meta:name="publish-date" meta:value-type="date" office:date-value="2026-06-10"/>
    <meta:user-defined meta:name="review-duration" meta:value-type="time" office:time-value="PT1H30M"/>
  </office:meta>
</office:document-meta>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, null, null, $metaWithTypedUserDefined));
        $metadata = $result['metadata'];
        $details = $metadata['userDefinedDetails'];

        $t->same('Typed ODT Metadata Packet', $metadata['title']);
        $t->same('packet-42', $metadata['userDefined']['wp-source-id']);
        $t->same('true', $metadata['userDefined']['requires-legal-review']);
        $t->same('97.5', $metadata['userDefined']['source-score']);
        $t->same('2026-06-10', $metadata['userDefined']['publish-date']);
        $t->same('PT1H30M', $metadata['userDefined']['review-duration']);

        $t->same('string', $details['wp-source-id']['valueType']);
        $t->same('packet-42', $details['wp-source-id']['stringValue']);
        $t->same('packet-42', $details['wp-source-id']['displayValue']);
        $t->same('boolean', $details['requires-legal-review']['valueType']);
        $t->same(true, $details['requires-legal-review']['booleanValue']);
        $t->same('true', $details['requires-legal-review']['displayValue']);
        $t->same('float', $details['source-score']['valueType']);
        $t->same('97.5', $details['source-score']['value']);
        $t->same('97.5', $details['source-score']['displayValue']);
        $t->same('date', $details['publish-date']['valueType']);
        $t->same('2026-06-10', $details['publish-date']['dateValue']);
        $t->same('time', $details['review-duration']['valueType']);
        $t->same('PT1H30M', $details['review-duration']['timeValue']);
        $t->same($details, $result['document']->attr('metadata')['userDefinedDetails']);
        $t->same(true, $result['importReport']['metadata']['userDefinedDetails']['requires-legal-review']['booleanValue']);
        $t->same('PT1H30M', $result['importReport']['metadata']['userDefinedDetails']['review-duration']['timeValue']);
    },
    'maps ODT package policy metadata from meta XML' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $metaWithPackagePolicy = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:meta>
    <dc:title>ODT Import Packet</dc:title>
    <meta:generator>LibreOffice/7.6.4$Linux_X86_64 LibreOffice_project/7.6.4</meta:generator>
    <meta:editing-duration>PT1H2M3S</meta:editing-duration>
    <meta:modification-date>2026-06-08T19:55:00Z</meta:modification-date>
    <meta:modification-time>PT19H55M00S</meta:modification-time>
    <meta:printed-by>Migration Printer</meta:printed-by>
    <meta:print-date>2026-06-08</meta:print-date>
    <meta:print-time>PT12H34M56S</meta:print-time>
    <meta:template xlink:href="Templates/import-review.ott" xlink:type="simple" xlink:title="Import Review Template" xlink:show="replace" xlink:actuate="onRequest" meta:date="2026-06-01T10:00:00Z"/>
    <meta:auto-reload xlink:href="https://example.test/source.odt" xlink:type="simple" xlink:show="replace" xlink:actuate="onLoad" meta:delay="PT15M"/>
    <meta:hyperlink-behaviour xlink:show="new" office:target-frame-name="_blank"/>
  </office:meta>
</office:document-meta>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, null, null, $metaWithPackagePolicy));
        $metadata = $result['metadata'];

        $t->same('LibreOffice/7.6.4$Linux_X86_64 LibreOffice_project/7.6.4', $metadata['generator']);
        $t->same('PT1H2M3S', $metadata['editingDuration']);
        $t->same('2026-06-08T19:55:00Z', $metadata['modificationDate']);
        $t->same('PT19H55M00S', $metadata['modificationTime']);
        $t->same('Migration Printer', $metadata['printedBy']);
        $t->same('2026-06-08', $metadata['printDate']);
        $t->same('PT12H34M56S', $metadata['printTime']);
        $t->same('Templates/import-review.ott', $metadata['template']['href']);
        $t->same('simple', $metadata['template']['type']);
        $t->same('replace', $metadata['template']['show']);
        $t->same('onRequest', $metadata['template']['actuate']);
        $t->same('Import Review Template', $metadata['template']['title']);
        $t->same('2026-06-01T10:00:00Z', $metadata['template']['date']);
        $t->same('https://example.test/source.odt', $metadata['autoReload']['href']);
        $t->same('simple', $metadata['autoReload']['type']);
        $t->same('replace', $metadata['autoReload']['show']);
        $t->same('onLoad', $metadata['autoReload']['actuate']);
        $t->same('PT15M', $metadata['autoReload']['delay']);
        $t->same('new', $metadata['hyperlinkBehaviour']['show']);
        $t->same('_blank', $metadata['hyperlinkBehaviour']['targetFrameName']);
        $t->same('Import Review Template', $result['document']->attr('metadata')['template']['title']);
        $t->same('2026-06-08T19:55:00Z', $result['document']->attr('metadata')['modificationDate']);
        $t->same('PT15M', $result['importReport']['metadata']['autoReload']['delay']);
        $t->same('PT19H55M00S', $result['importReport']['metadata']['modificationTime']);
        $t->same('_blank', $result['importReport']['metadata']['hyperlinkBehaviour']['targetFrameName']);
    },
    'maps ODT settings XML config items into package review metadata' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="ooo:view-settings">
      <config:config-item config:name="ViewAreaTop" config:type="int">1440</config:config-item>
      <config:config-item config:name="ShowRedlineChanges" config:type="boolean">true</config:config-item>
      <config:config-item-map-indexed config:name="Views">
        <config:config-item-map-entry>
          <config:config-item config:name="ViewId" config:type="string">view-1</config:config-item>
          <config:config-item config:name="ViewLeft" config:type="int">120</config:config-item>
        </config:config-item-map-entry>
        <config:config-item-map-entry>
          <config:config-item config:name="ViewId" config:type="string">view-2</config:config-item>
          <config:config-item config:name="ViewLeft" config:type="int">240</config:config-item>
        </config:config-item-map-entry>
      </config:config-item-map-indexed>
    </config:config-item-set>
    <config:config-item-set config:name="ooo:configuration-settings">
      <config:config-item config:name="LoadReadonly" config:type="boolean">false</config:config-item>
      <config:config-item-map-named config:name="ForbiddenCharacters">
        <config:config-item-map-entry config:name="en-US">
          <config:config-item config:name="Language" config:type="string">en</config:config-item>
        </config:config-item-map-entry>
      </config:config-item-map-named>
    </config:config-item-set>
  </office:settings>
</office:document-settings>
XML;
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSettings, null, null, [
            ['name' => 'settings.xml', 'data' => $settingsXml],
        ]));
        $settings = $result['settings'];
        $view = $settings['setsByName']['ooo:view-settings'];
        $configuration = $settings['setsByName']['ooo:configuration-settings'];
        $views = $view['mapsByName']['Views'];
        $forbiddenCharacters = $configuration['mapsByName']['ForbiddenCharacters'];

        $t->same(2, $settings['count']);
        $t->same(8, $settings['itemCount']);
        $t->same(3, $settings['mapEntryCount']);
        $t->same(['ooo:view-settings', 'ooo:configuration-settings'], array_column($settings['sets'], 'name'));
        $t->same(6, $view['itemCount']);
        $t->same(2, $view['mapEntryCount']);
        $t->same(1440, $view['itemsByName']['ViewAreaTop']['typedValue']);
        $t->same('1440', $view['itemsByName']['ViewAreaTop']['value']);
        $t->same(true, $view['itemsByName']['ShowRedlineChanges']['typedValue']);
        $t->same('indexed', $views['type']);
        $t->same(2, $views['entryCount']);
        $t->same('view-1', $views['entries'][0]['itemsByName']['ViewId']['typedValue']);
        $t->same(240, $views['entries'][1]['itemsByName']['ViewLeft']['typedValue']);
        $t->same(2, $configuration['itemCount']);
        $t->same(false, $configuration['itemsByName']['LoadReadonly']['typedValue']);
        $t->same('named', $forbiddenCharacters['type']);
        $t->same(1, $forbiddenCharacters['entryCount']);
        $t->same('en-US', $forbiddenCharacters['entries'][0]['name']);
        $t->same('en', $forbiddenCharacters['entriesByName']['en-US']['itemsByName']['Language']['typedValue']);
        $t->same($settings, $result['document']->attr('settings'));
        $t->same(2, $result['importReport']['settings']['count']);
        $t->same(8, $result['importReport']['settings']['itemCount']);
        $t->same(3, $result['importReport']['settings']['mapEntryCount']);
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $t->same('settings.xml', $manifestByPath['settings.xml']['part']);
        $t->same(true, $manifestByPath['settings.xml']['exists']);
        $t->same(1, count($result['media']), 'settings.xml must stay out of media byte handoff');

        $badSettingsXml = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>';
        $t->throws(\InvalidArgumentException::class, static fn (): array => (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSettings, null, null, [
            ['name' => 'settings.xml', 'data' => $badSettingsXml],
        ])));
    },
    'maps ODT page layouts and master pages into import report metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithPageLayout = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:automatic-styles>
    <style:page-layout style:name="pmReview" style:page-usage="all">
      <style:page-layout-properties
        fo:page-width="8.5in"
        fo:page-height="11in"
        fo:margin-top="1in"
        fo:margin-right="0.75in"
        fo:margin-bottom="1in"
        fo:margin-left="0.75in"
        style:print-orientation="portrait"
        style:writing-mode="lr-tb"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="AppendixBreak" style:family="paragraph" style:master-page-name="AppendixPage">
      <style:paragraph-properties fo:break-before="page"/>
    </style:style>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="ReviewPage" style:display-name="Review Page" style:page-layout-name="pmReview" style:next-style-name="AppendixPage">
      <style:header><text:p>Confidential import packet</text:p></style:header>
      <style:footer><text:p>Page <text:page-number>1</text:page-number></text:p></style:footer>
    </style:master-page>
    <style:master-page style:name="AppendixPage" style:page-layout-name="pmReview"/>
  </office:master-styles>
</office:document-styles>
XML;
        $contentWithMasterPageStyle = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="AppendixBreak">Appendix starts here.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithMasterPageStyle, null, $stylesWithPageLayout));
        $document = $result['document'];
        $pageLayout = $result['pageLayouts']['pmReview'];
        $reviewPage = $result['masterPages']['ReviewPage'];
        $appendixStyle = $result['styles']['AppendixBreak'];
        $paragraph = $document->children[0];

        $t->same(1, $document->attr('pageLayouts')['count']);
        $t->same(2, $document->attr('masterPages')['count']);
        $t->same('pmReview', $pageLayout['name']);
        $t->same('all', $pageLayout['pageUsage']);
        $t->same('8.5in', $pageLayout['properties']['pageWidth']);
        $t->same('11in', $pageLayout['properties']['pageHeight']);
        $t->same('portrait', $pageLayout['properties']['printOrientation']);
        $t->same('lr-tb', $pageLayout['properties']['writingMode']);
        $t->same('1in', $pageLayout['properties']['marginTop']);
        $t->same('0.75in', $pageLayout['properties']['marginRight']);
        $t->true(abs($pageLayout['properties']['pageWidthPoints'] - 612.0) < 0.000001);
        $t->true(abs($pageLayout['properties']['marginLeftPoints'] - 54.0) < 0.000001);

        $t->same('ReviewPage', $reviewPage['name']);
        $t->same('Review Page', $reviewPage['displayName']);
        $t->same('pmReview', $reviewPage['pageLayoutName']);
        $t->same('AppendixPage', $reviewPage['nextStyleName']);
        $t->same(['Confidential import packet'], $reviewPage['headerText']);
        $t->same(['Page 1'], $reviewPage['footerText']);
        $t->same('AppendixPage', $result['masterPages']['AppendixPage']['name']);

        $t->same('AppendixPage', $appendixStyle['masterPageName']);
        $t->same('page', $appendixStyle['paragraphProperties']['breakBefore']);
        $t->same('paragraph', $paragraph->type);
        $t->same('AppendixBreak', $paragraph->attr('styleName'));
        $t->same('AppendixPage', $paragraph->attr('style')['masterPageName']);
        $t->same('page', $paragraph->attr('style')['paragraphProperties']['breakBefore']);
        $t->same('Appendix starts here.', $paragraph->attr('text'));

        $t->same(1, $result['importReport']['pageLayouts']['count']);
        $t->same(2, $result['importReport']['masterPages']['count']);
        $t->same('Confidential import packet', $result['importReport']['masterPages']['items'][0]['headerText'][0]);
        $t->same(1, $result['importReport']['styles']['pageLayoutCount']);
        $t->same(2, $result['importReport']['styles']['masterPageCount']);
    },
    'maps ODT indented paragraph styles into Pandoc block quotes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithQuoteIndent = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ReviewQuote" style:family="paragraph" style:display-name="Review Quote">
      <style:paragraph-properties fo:margin-left="6mm"/>
    </style:style>
    <style:style style:name="InheritedQuote" style:family="paragraph" style:parent-style-name="ReviewQuote" style:display-name="Inherited Review Quote">
      <style:paragraph-properties fo:text-indent="1mm"/>
    </style:style>
    <style:style style:name="SmallIndent" style:family="paragraph" style:display-name="Small Indent">
      <style:paragraph-properties fo:margin-left="3mm"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithQuoteIndent = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="ReviewQuote">Quoted migration decision.</text:p>
      <text:p text:style-name="InheritedQuote">Inherited quoted detail.</text:p>
      <text:p text:style-name="SmallIndent">Indented but not quoted.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithQuoteIndent, null, $stylesWithQuoteIndent));
        $blocks = $result['document']->children;
        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same(3, count($blocks));
        $t->same('blockquote', $blocks[0]->type);
        $t->same(['odf-blockquote'], $blocks[0]->attr('classes'));
        $t->same('ReviewQuote', $blocks[0]->attr('styleName'));
        $t->same('6mm', $blocks[0]->attr('style')['paragraphProperties']['marginLeft']);
        $t->true(abs(($blocks[0]->attr('style')['paragraphProperties']['marginLeftPoints'] ?? 0.0) - 17.00787402) < 0.000001);
        $t->same('paragraph', $blocks[0]->children[0]->type);
        $t->same('Quoted migration decision.', $blocks[0]->children[0]->attr('text'));
        $t->same('blockquote', $blocks[1]->type);
        $t->same('InheritedQuote', $blocks[1]->attr('styleName'));
        $t->same('ReviewQuote', $blocks[1]->attr('style')['parentName']);
        $t->same('1mm', $blocks[1]->attr('style')['paragraphProperties']['textIndent']);
        $t->same('paragraph', $blocks[1]->children[0]->type);
        $t->same('Inherited quoted detail.', $blocks[1]->children[0]->attr('text'));
        $t->same('paragraph', $blocks[2]->type);
        $t->same('Indented but not quoted.', $blocks[2]->attr('text'));
        $t->same(2, $result['importReport']['content']['blockquoteCount']);

        $t->contains('> Quoted migration decision.', $markdown);
        $t->contains('> Inherited quoted detail.', $markdown);
        $t->contains('Indented but not quoted.', $markdown);
        $t->contains('<blockquote class="wp-block-quote odf-blockquote"><p>Quoted migration decision.</p></blockquote>', $blocksHtml);
        $t->contains('<blockquote class="wp-block-quote odf-blockquote"><p>Inherited quoted detail.</p></blockquote>', $blocksHtml);
        $t->contains('<p>Indented but not quoted.</p>', $blocksHtml);
    },
    'does not turn indented ODT list paragraphs into block quotes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithIndentedListParagraphs = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ReviewQuote" style:family="paragraph" style:display-name="Review Quote">
      <style:paragraph-properties fo:margin-left="8mm"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithIndentedListParagraphs = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="ReviewQuote">Top-level quote remains a quote.</text:p>
      <text:list text:style-name="ReviewSteps">
        <text:list-item><text:p text:style-name="ReviewQuote">Indented checklist paragraph stays in the list item.</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithIndentedListParagraphs, null, $stylesWithIndentedListParagraphs));
        $blocks = $result['document']->children;
        $topLevelQuote = $blocks[0];
        $list = $blocks[1];
        $listItemParagraph = $list->children[0]->children[0];

        $t->same('blockquote', $topLevelQuote->type);
        $t->same(['odf-blockquote'], $topLevelQuote->attr('classes'));
        $t->same('Top-level quote remains a quote.', $topLevelQuote->children[0]->attr('text'));
        $t->same('ordered_list', $list->type);
        $t->same('paragraph', $listItemParagraph->type);
        $t->same('ReviewQuote', $listItemParagraph->attr('styleName'));
        $t->same('Indented checklist paragraph stays in the list item.', $listItemParagraph->attr('text'));
        $t->same(1, $result['importReport']['content']['blockquoteCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('> Top-level quote remains a quote.', $markdown);
        $t->contains('1.  Indented checklist paragraph stays in the list item.', $markdown);
        $t->true(!str_contains($markdown, "1.  >"), 'ODT list paragraph indentation must not become a nested blockquote');
        $t->contains('<blockquote class="wp-block-quote odf-blockquote"><p>Top-level quote remains a quote.</p></blockquote>', $blocksHtml);
        $t->contains('<ol><li>Indented checklist paragraph stays in the list item.</li></ol>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, '<li><blockquote'), 'WordPress list output must not wrap the indented list paragraph in a quote');
    },
    'maps ODT preformatted paragraph styles into code blocks' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithPreformattedParagraph = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="Preformatted_20_Text" style:family="paragraph" style:display-name="Preformatted Text"/>
    <style:style style:name="InheritedSourceCode" style:family="paragraph" style:parent-style-name="Preformatted_20_Text" style:display-name="Inherited Source Code"/>
    <style:style style:name="BodyText" style:family="paragraph" style:display-name="Body Text"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithPreformattedParagraph = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="InheritedSourceCode">define('WP_DEBUG', true);<text:line-break/>echo <text:span>sanitize_text_field</text:span>($title);<text:tab/>// review</text:p>
      <text:p text:style-name="BodyText">Following review prose stays a paragraph.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPreformattedParagraph, null, $stylesWithPreformattedParagraph));
        $blocks = $result['document']->children;
        $code = $blocks[0];
        $paragraph = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('code_block', $code->type);
        $t->same("define('WP_DEBUG', true);\necho sanitize_text_field(\$title); // review", $code->attr('text'));
        $t->same('odt', $code->attr('sourceFormat'));
        $t->same(true, $code->attr('odfPreformatted'));
        $t->same('InheritedSourceCode', $code->attr('styleName'));
        $t->same('Preformatted_20_Text', $code->attr('style')['parentName']);
        $t->same('true', $code->attr('attributes')['data-odf-preformatted']);
        $t->same('InheritedSourceCode', $code->attr('attributes')['data-odf-style-name']);
        $t->same('paragraph', $paragraph->type);
        $t->same('Following review prose stays a paragraph.', $paragraph->attr('text'));
        $t->same(1, $result['importReport']['content']['preformattedCodeBlockCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains("``` {data-odf-preformatted=\"true\" data-odf-style-name=\"InheritedSourceCode\"}\ndefine('WP_DEBUG', true);\necho sanitize_text_field(\$title); // review\n```", $markdown);
        $t->contains('<pre class="wp-block-code" data-odf-preformatted="true" data-odf-style-name="InheritedSourceCode"><code>define(&#039;WP_DEBUG&#039;, true);', $blocksHtml);
        $t->contains("echo sanitize_text_field(\$title); // review</code></pre>", $blocksHtml);
        $t->contains('<p>Following review prose stays a paragraph.</p>', $blocksHtml);
    },
    'maps ODT paragraph text properties into styled inline content' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithParagraphTextProperties = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="StrongParagraph" style:family="paragraph" style:display-name="Strong Paragraph">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="InheritedEmphasisParagraph" style:family="paragraph" style:parent-style-name="StrongParagraph" style:display-name="Inherited Emphasis Paragraph">
      <style:text-properties fo:font-style="italic" fo:font-variant="small-caps"/>
    </style:style>
    <style:style style:name="PlainParagraph" style:family="paragraph" style:display-name="Plain Paragraph"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithParagraphTextProperties = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p text:style-name="StrongParagraph">Important <text:a xlink:href="https://example.test/source">source</text:a> packet.</text:p>
      <text:p text:style-name="InheritedEmphasisParagraph">Inherited emphasis packet.</text:p>
      <text:p text:style-name="PlainParagraph">Plain styled paragraph.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithParagraphTextProperties, null, $stylesWithParagraphTextProperties));
        $blocks = $result['document']->children;

        $t->same(3, count($blocks));
        $t->same('Important source packet.', $blocks[0]->attr('text'));
        $t->same('strong', $blocks[0]->children[0]->type);
        $strongSpan = $blocks[0]->children[0]->children[0];
        $t->same('span', $strongSpan->type);
        $t->same('StrongParagraph', $strongSpan->attr('styleName'));
        $t->same('StrongParagraph', $strongSpan->attr('attributes')['data-odf-style-name']);
        $t->same('link', $strongSpan->children[1]->type);
        $t->same('https://example.test/source', $strongSpan->children[1]->attr('url'));

        $t->same('small_caps', $blocks[1]->children[0]->type);
        $t->same('emph', $blocks[1]->children[0]->children[0]->type);
        $t->same('strong', $blocks[1]->children[0]->children[0]->children[0]->type);
        $emphasisSpan = $blocks[1]->children[0]->children[0]->children[0]->children[0];
        $t->same('span', $emphasisSpan->type);
        $t->same('InheritedEmphasisParagraph', $emphasisSpan->attr('styleName'));
        $t->same('StrongParagraph', $blocks[1]->attr('style')['parentName']);
        $t->same(true, $blocks[1]->attr('style')['textProperties']['bold']);
        $t->same(true, $blocks[1]->attr('style')['textProperties']['italic']);
        $t->same(true, $blocks[1]->attr('style')['textProperties']['smallCaps']);

        $t->same('text', $blocks[2]->children[0]->type);
        $t->same('Plain styled paragraph.', $blocks[2]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('**[Important [source](https://example.test/source) packet.]{data-odf-style-name="StrongParagraph"}**', $markdown);
        $t->contains('[***[Inherited emphasis packet.]{data-odf-style-name="InheritedEmphasisParagraph"}***]{.smallcaps}', $markdown);
        $t->contains('<p><strong><span data-odf-style-name="StrongParagraph">Important <a href="https://example.test/source">source</a> packet.</span></strong></p>', $blocksHtml);
        $t->contains('<p><span style="font-variant:small-caps"><em><strong><span data-odf-style-name="InheritedEmphasisParagraph">Inherited emphasis packet.</span></strong></em></span></p>', $blocksHtml);
        $t->contains('<p>Plain styled paragraph.</p>', $blocksHtml);
    },
    'maps ODT source text styles into inline code spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithSourceText = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="Source_Text" style:family="text" style:display-name="Source Text"/>
    <style:style style:name="Source_20_Text" style:family="text" style:display-name="Source Text Escaped"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithSourceText = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Inline <text:span text:style-name="Source_Text">wp_insert_post</text:span> keeps <text:span text:style-name="Source_20_Text">esc_html(<text:s/>$title)</text:span>.</text:p>
      <text:h text:outline-level="2">Use <text:span text:style-name="Source_Text">do_shortcode</text:span></text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSourceText, null, $stylesWithSourceText));
        $paragraph = $result['document']->children[0];
        $heading = $result['document']->children[1];
        $firstCode = $paragraph->children[1];
        $secondCode = $paragraph->children[3];
        $headingCode = $heading->children[1];

        $t->same('Inline wp_insert_post keeps esc_html( $title).', $paragraph->attr('text'));
        $t->same('code', $firstCode->type);
        $t->same('wp_insert_post', $firstCode->attr('text'));
        $t->same('Source_Text', $firstCode->attr('styleName'));
        $t->same('Source_Text', $firstCode->attr('attributes')['data-odf-style-name']);
        $t->same('code', $secondCode->type);
        $t->same('esc_html( $title)', $secondCode->attr('text'));
        $t->same('Source_20_Text', $secondCode->attr('styleName'));
        $t->same('code', $headingCode->type);
        $t->same('do_shortcode', $headingCode->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Inline `wp_insert_post`{data-odf-style-name="Source_Text"} keeps `esc_html( $title)`{data-odf-style-name="Source_20_Text"}.', $markdown);
        $t->contains('## Use `do_shortcode`{data-odf-style-name="Source_Text"}', $markdown);
        $t->contains('<p>Inline <code data-odf-style-name="Source_Text">wp_insert_post</code> keeps <code data-odf-style-name="Source_20_Text">esc_html( $title)</code>.</p>', $blocksHtml);
        $t->contains('<h2 id="use-do-shortcode">Use <code data-odf-style-name="Source_Text">do_shortcode</code></h2>', $blocksHtml);
    },
    'maps ODT content XML blocks to the shared Pandoc-like AST' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $result = (new OdfReader())->readPackage($buildOdtPackage());
        $document = $result['document'];
        $blocks = $document->children;

        $t->same(8, count($blocks));
        $t->same('heading', $blocks[0]->type);
        $t->same(1, $blocks[0]->attr('level'));
        $t->same('AutoHeading', $blocks[0]->attr('styleName'));
        $t->same('imported-odt-packet', $blocks[0]->attr('id'));
        $t->same('Imported ODT Packet', $blocks[0]->attr('text'));
        $t->same('Imported ODT Packet', $blocks[0]->children[0]->attr('text'));

        $paragraph = $blocks[1];
        $t->same('paragraph', $paragraph->type);
        $t->same('BodyText', $paragraph->attr('styleName'));
        $t->same('underline', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[1]->children[0]->type);
        $t->same('strong', $paragraph->children[1]->children[0]->children[0]->type);
        $t->same('summary', $paragraph->children[1]->children[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Reviewer summary  keeps source link' . "\n" . 'next line', $paragraph->attr('text'));
        $t->same('link', $paragraph->children[3]->type);
        $t->same('https://example.test/source.odt', $paragraph->children[3]->attr('url'));
        $t->same('linebreak', $paragraph->children[4]->type);
        $t->same('note', $paragraph->children[6]->type);
        $t->same('Migration Desk', $paragraph->children[6]->attr('author'));
        $t->same('2026-06-04T22:10:00Z', $paragraph->children[6]->attr('date'));
        $t->same('Annotation for reviewers.', $paragraph->children[6]->children[0]->attr('text'));

        $ordered = $blocks[2];
        $t->same('ordered_list', $ordered->type);
        $t->same('lower_alpha', $ordered->attr('style'));
        $t->same(3, $ordered->attr('start'));
        $t->same('Legal review', $ordered->children[0]->children[0]->attr('text'));

        $bullet = $blocks[3];
        $t->same('bullet_list', $bullet->type);
        $t->same('*', $bullet->attr('format'));
        $t->same('Confirm media map', $bullet->children[0]->children[0]->attr('text'));

        $section = $blocks[4];
        $t->same('div', $section->type);
        $t->same('sidebar', $section->attr('id'));
        $t->same('Sidebar', $section->attr('attributes')['data-odf-section-name']);
        $t->same('Section packet note.', $section->children[0]->attr('text'));

        $figure = $blocks[5];
        $t->same('figure', $figure->type);
        $t->same('image', $figure->children[0]->type);
        $t->same('Pictures/hero.png', $figure->children[0]->attr('url'));
        $t->same('Hero alt text', $figure->children[0]->attr('alt'));
        $t->same(7, $figure->children[0]->attr('bytes'));

        $textBox = $blocks[6];
        $t->same('div', $textBox->type);
        $t->same('Pull quote', $textBox->attr('attributes')['data-odf-frame-name']);
        $t->same('Text box source note.', $textBox->children[0]->attr('text'));

        $table = $blocks[7];
        $t->same('table', $table->type);
        $widths = $table->attr('widths');
        $t->true(is_array($widths));
        $t->true(abs($widths[0] - (1 / 3)) < 0.000001);
        $t->true(abs($widths[1] - (2 / 3)) < 0.000001);
        $t->same('table_head', $table->children[0]->type);
        $t->same('Status', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('table_body', $table->children[1]->type);
        $t->same(2, $table->children[1]->children[0]->children[0]->attr('colspan'));
        $t->same('Ready for review', $table->children[1]->children[0]->children[0]->attr('text'));
    },
    'assigns Pandoc-style auto identifiers to ODT headings' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithParagraphHeading = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="StyledHeading" style:family="paragraph" style:display-name="Styled Heading" style:default-outline-level="2"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithHeadings = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:h text:outline-level="1">ODT Source Packet</text:h>
      <text:h text:outline-level="2">ODT Source Packet</text:h>
      <text:p text:style-name="StyledHeading">Styled packet title</text:p>
      <text:h text:outline-level="3">!!!</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithHeadings, null, $stylesWithParagraphHeading));
        $headings = $result['document']->children;

        $t->same(4, count($headings));
        $t->same('heading', $headings[0]->type);
        $t->same('odt-source-packet', $headings[0]->attr('id'));
        $t->same('ODT Source Packet', $headings[0]->attr('text'));
        $t->same('odt-source-packet-1', $headings[1]->attr('id'));
        $t->same(2, $headings[1]->attr('level'));
        $t->same('styled-packet-title', $headings[2]->attr('id'));
        $t->same(2, $headings[2]->attr('level'));
        $t->same('StyledHeading', $headings[2]->attr('styleName'));
        $t->same('section', $headings[3]->attr('id'));
        $t->same(3, $headings[3]->attr('level'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('# ODT Source Packet', $markdown);
        $t->contains('## ODT Source Packet', $markdown);
        $t->contains('## Styled packet title', $markdown);
        $t->contains('### !!!', $markdown);
        $t->contains('<h1 id="odt-source-packet">ODT Source Packet</h1>', $blocksHtml);
        $t->contains('<h2 id="odt-source-packet-1">ODT Source Packet</h2>', $blocksHtml);
        $t->contains('<h2 id="styled-packet-title">Styled packet title</h2>', $blocksHtml);
        $t->contains('<h3 id="section">!!!</h3>', $blocksHtml);
    },
    'uses ODT heading bookmarks as explicit source anchors' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithParagraphHeading = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="StyledHeading" style:family="paragraph" style:display-name="Styled Heading" style:default-outline-level="2"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithHeadingBookmarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:h text:outline-level="1"><text:bookmark-start text:name="Source Review Anchor"/>Heading from source bookmark<text:bookmark-end text:name="Source Review Anchor"/></text:h>
      <text:p text:style-name="StyledHeading"><text:bookmark text:name="Styled Source Anchor"/>Styled heading from bookmark</text:p>
      <text:h text:outline-level="2">Source Review Anchor</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithHeadingBookmarks, null, $stylesWithParagraphHeading));
        $headings = $result['document']->children;

        $t->same(3, count($headings));
        $t->same('heading', $headings[0]->type);
        $t->same('source-review-anchor', $headings[0]->attr('id'));
        $t->same('bookmark', $headings[0]->attr('odfHeadingAnchor')['source']);
        $t->same('Source Review Anchor', $headings[0]->attr('odfHeadingAnchor')['bookmarkName']);
        $t->same('source-review-anchor', $headings[0]->attr('attributes')['data-odf-heading-anchor-id']);
        $t->same('Source Review Anchor', $headings[0]->attr('attributes')['data-odf-heading-bookmark-name']);
        $t->same('Heading from source bookmark', $headings[0]->attr('text'));
        $t->same(1, count($headings[0]->children));
        $t->same('text', $headings[0]->children[0]->type);
        $t->same('Heading from source bookmark', $headings[0]->children[0]->attr('text'));

        $t->same('heading', $headings[1]->type);
        $t->same(2, $headings[1]->attr('level'));
        $t->same('styled-source-anchor', $headings[1]->attr('id'));
        $t->same('Styled Source Anchor', $headings[1]->attr('odfHeadingAnchor')['bookmarkName']);
        $t->same('StyledHeading', $headings[1]->attr('styleName'));
        $t->same('Styled heading from bookmark', $headings[1]->attr('text'));
        $t->same(1, count($headings[1]->children));

        $t->same('source-review-anchor-1', $headings[2]->attr('id'));
        $t->same('Source Review Anchor', $headings[2]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('# Heading from source bookmark {#source-review-anchor data-odf-heading-anchor-source="bookmark" data-odf-heading-bookmark-name="Source Review Anchor" data-odf-heading-anchor-id="source-review-anchor"}', $markdown);
        $t->contains('## Styled heading from bookmark {#styled-source-anchor data-odf-heading-anchor-source="bookmark" data-odf-heading-bookmark-name="Styled Source Anchor" data-odf-heading-anchor-id="styled-source-anchor"}', $markdown);
        $t->contains('## Source Review Anchor {#source-review-anchor-1}', $markdown);
        $t->contains('<h1 id="source-review-anchor" data-odf-heading-anchor-source="bookmark" data-odf-heading-bookmark-name="Source Review Anchor" data-odf-heading-anchor-id="source-review-anchor">Heading from source bookmark</h1>', $blocksHtml);
        $t->contains('<h2 id="styled-source-anchor" data-odf-heading-anchor-source="bookmark" data-odf-heading-bookmark-name="Styled Source Anchor" data-odf-heading-anchor-id="styled-source-anchor">Styled heading from bookmark</h2>', $blocksHtml);
        $t->contains('<h2 id="source-review-anchor-1">Source Review Anchor</h2>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'class="anchor odf-bookmark"'), 'Heading bookmarks should become heading ids, not nested empty anchors');
    },
    'uses ODT text and XML heading ids as explicit source anchors' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithParagraphHeading = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="StyledHeading" style:family="paragraph" style:display-name="Styled Heading" style:default-outline-level="2"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithHeadingSourceIds = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <office:body>
    <office:text>
      <text:h text:outline-level="1" text:id="source-review-id">Heading with text id</text:h>
      <text:p text:style-name="StyledHeading" xml:id="styled-source-id">Styled heading with XML id</text:p>
      <text:h text:outline-level="2" text:id="source-review-id">Duplicate source id</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithHeadingSourceIds, null, $stylesWithParagraphHeading));
        $headings = $result['document']->children;

        $t->same(3, count($headings));
        $t->same('heading', $headings[0]->type);
        $t->same('source-review-id', $headings[0]->attr('id'));
        $t->same('attribute', $headings[0]->attr('odfHeadingAnchor')['source']);
        $t->same('text:id', $headings[0]->attr('odfHeadingAnchor')['attributeName']);
        $t->same('source-review-id', $headings[0]->attr('odfHeadingAnchor')['sourceId']);
        $t->same('source-review-id', $headings[0]->attr('attributes')['data-odf-heading-source-id']);
        $t->same('text:id', $headings[0]->attr('attributes')['data-odf-heading-source-attribute']);
        $t->same('Heading with text id', $headings[0]->attr('text'));

        $t->same('heading', $headings[1]->type);
        $t->same(2, $headings[1]->attr('level'));
        $t->same('styled-source-id', $headings[1]->attr('id'));
        $t->same('xml:id', $headings[1]->attr('odfHeadingAnchor')['attributeName']);
        $t->same('StyledHeading', $headings[1]->attr('styleName'));
        $t->same('Styled heading with XML id', $headings[1]->attr('text'));

        $t->same('source-review-id-1', $headings[2]->attr('id'));
        $t->same('source-review-id', $headings[2]->attr('odfHeadingAnchor')['sourceId']);
        $t->same('Duplicate source id', $headings[2]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('# Heading with text id {#source-review-id data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="text:id" data-odf-heading-source-id="source-review-id" data-odf-heading-anchor-id="source-review-id"}', $markdown);
        $t->contains('## Styled heading with XML id {#styled-source-id data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="xml:id" data-odf-heading-source-id="styled-source-id" data-odf-heading-anchor-id="styled-source-id"}', $markdown);
        $t->contains('## Duplicate source id {#source-review-id-1 data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="text:id" data-odf-heading-source-id="source-review-id" data-odf-heading-anchor-id="source-review-id-1"}', $markdown);
        $t->contains('<h1 id="source-review-id" data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="text:id" data-odf-heading-source-id="source-review-id" data-odf-heading-anchor-id="source-review-id">Heading with text id</h1>', $blocksHtml);
        $t->contains('<h2 id="styled-source-id" data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="xml:id" data-odf-heading-source-id="styled-source-id" data-odf-heading-anchor-id="styled-source-id">Styled heading with XML id</h2>', $blocksHtml);
        $t->contains('<h2 id="source-review-id-1" data-odf-heading-anchor-source="attribute" data-odf-heading-source-attribute="text:id" data-odf-heading-source-id="source-review-id" data-odf-heading-anchor-id="source-review-id-1">Duplicate source id</h2>', $blocksHtml);
    },
    'maps ODT table names and protection metadata into review table handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNamedProtectedTable = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table
        table:name="Protected Review Matrix"
        table:style-name="ReviewTable"
        table:protected="true"
        table:protection-key="opaque-source-key"
        table:protection-key-digest-algorithm="urn:odf:sha1">
        <table:table-row>
          <table:table-cell><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNamedProtectedTable));
        $table = $result['document']->children[0];
        $geometry = $table->attr('tableGeometry');

        $t->same('table', $table->type);
        $t->same('Protected Review Matrix', $table->attr('caption'));
        $t->same('Protected Review Matrix', $table->attr('tableName'));
        $t->same('ReviewTable', $table->attr('styleName'));
        $t->same(true, $table->attr('protected'));
        $t->same(true, $table->attr('protectionKeyPresent'));
        $t->same('urn:odf:sha1', $table->attr('protectionKeyDigestAlgorithm'));
        $t->same('Protected Review Matrix', $table->attr('htmlAttributes')['data-odf-table-name']);
        $t->same('ReviewTable', $table->attr('htmlAttributes')['data-odf-table-style-name']);
        $t->same('true', $table->attr('htmlAttributes')['data-odf-table-protected']);
        $t->same('true', $table->attr('htmlAttributes')['data-odf-table-protection-key-present']);
        $t->same('urn:odf:sha1', $table->attr('htmlAttributes')['data-odf-table-protection-key-digest-algorithm']);
        $t->true(is_array($geometry));
        $geometry = is_array($geometry) ? $geometry : [];
        $t->same('Protected Review Matrix', $geometry['caption'] ?? null);
        $t->same(2, $geometry['columnCount'] ?? null);
        $t->same(2, $geometry['summary']['rowCount'] ?? null);
        $t->same('Migration desk', $geometry['coverage'][2]['text'] ?? null);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': Protected Review Matrix', $markdown);
        $t->contains('<table data-odf-table-name="Protected Review Matrix" data-odf-table-style-name="ReviewTable" data-odf-table-protected="true" data-odf-table-protection-key-present="true" data-odf-table-protection-key-digest-algorithm="urn:odf:sha1">', $blocksHtml);
        $t->contains('<figcaption class="wp-element-caption">Protected Review Matrix</figcaption>', $blocksHtml);
    },
    'maps ODT grouped body and footer table rows into table sections' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithGroupedRows = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Grouped Review">
        <table:table-header-rows>
          <table:table-row>
            <table:table-cell><text:p>Item</text:p></table:table-cell>
            <table:table-cell><text:p>Status</text:p></table:table-cell>
          </table:table-row>
        </table:table-header-rows>
        <table:table-rows>
          <table:table-row>
            <table:table-cell><text:p>Draft import</text:p></table:table-cell>
            <table:table-cell><text:p>Needs review</text:p></table:table-cell>
          </table:table-row>
          <table:table-row>
            <table:table-cell><text:p>Ready import</text:p></table:table-cell>
            <table:table-cell><text:p>Ready</text:p></table:table-cell>
          </table:table-row>
        </table:table-rows>
        <table:table-row>
          <table:table-cell><text:p>Escalated import</text:p></table:table-cell>
          <table:table-cell><text:p>Legal</text:p></table:table-cell>
        </table:table-row>
        <table:table-footer-rows>
          <table:table-row>
            <table:table-cell><text:p>Total</text:p></table:table-cell>
            <table:table-cell><text:p>3 rows</text:p></table:table-cell>
          </table:table-row>
        </table:table-footer-rows>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithGroupedRows));
        $table = $result['document']->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];
        $geometry = $table->attr('tableGeometry');
        $rowGroups = is_array($geometry) ? ($geometry['rowGroups'] ?? []) : [];

        $t->same('table', $table->type);
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn ($child): string => $child->type, $table->children));
        $t->same(1, count($head->children));
        $t->same(3, count($body->children));
        $t->same(1, count($foot->children));
        $t->same('Draft import', $body->children[0]->children[0]->attr('text'));
        $t->same('Ready import', $body->children[1]->children[0]->attr('text'));
        $t->same('Escalated import', $body->children[2]->children[0]->attr('text'));
        $t->same('3 rows', $foot->children[0]->children[1]->attr('text'));
        $t->same('table-head', $rowGroups[0]['kind'] ?? null);
        $t->same('table-body', $rowGroups[1]['kind'] ?? null);
        $t->same('table-foot', $rowGroups[2]['kind'] ?? null);
        $t->same(3, $rowGroups[1]['bodyRowCount'] ?? null);
        $t->same(1, $rowGroups[2]['footRowCount'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<tbody><tr><td><p>Draft import</p></td><td><p>Needs review</p></td></tr><tr><td><p>Ready import</p></td><td><p>Ready</p></td></tr><tr><td><p>Escalated import</p></td><td><p>Legal</p></td></tr></tbody>', $blocksHtml);
        $t->contains('<tfoot><tr><td><p>Total</p></td><td><p>3 rows</p></td></tr></tfoot>', $blocksHtml);
    },
    'maps ODT table print ranges into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPrintRanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="PrintableReview" table:print-ranges="PrintableReview.A1:PrintableReview.B2 PrintableReview.D1:PrintableReview.D4">
        <table:table-row>
          <table:table-cell><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPrintRanges));
        $table = $result['document']->children[0];
        $ranges = $table->attr('odfPrintRanges');

        $t->same('table', $table->type);
        $t->same('PrintableReview', $table->attr('tableName'));
        $t->same([
            'PrintableReview.A1:PrintableReview.B2',
            'PrintableReview.D1:PrintableReview.D4',
        ], $ranges);
        $t->same(2, $table->attr('printRangeCount'));
        $t->same('2', $table->attr('htmlAttributes')['data-odf-table-print-range-count']);
        $t->same('PrintableReview.A1:PrintableReview.B2;PrintableReview.D1:PrintableReview.D4', $table->attr('htmlAttributes')['data-odf-table-print-ranges']);
        $t->same(2, $result['importReport']['content']['tablePrintRangeCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': PrintableReview', $markdown);
        $t->contains('<table data-odf-table-name="PrintableReview" data-odf-table-print-range-count="2" data-odf-table-print-ranges="PrintableReview.A1:PrintableReview.B2;PrintableReview.D1:PrintableReview.D4">', $blocksHtml);
    },
    'maps ODT table scenarios into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTableScenarios = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="ScenarioReview">
        <table:scenario
          table:name="ReadyImport"
          table:display-border="true"
          table:border-color="#00843d"
          table:copy-back="false"
          table:copy-styles="true"
          table:copy-formulas="false"
          table:is-active="true"
          table:scenario-ranges="ScenarioReview.A2:ScenarioReview.B3 ScenarioReview.D2:ScenarioReview.D4"
          table:comment="Approved rows for WordPress import"/>
        <table:scenario
          table:name="DraftFallback"
          table:display-border="false"
          table:is-active="false"
          table:scenario-ranges="ScenarioReview.A5:ScenarioReview.B6"/>
        <table:table-row>
          <table:table-cell><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTableScenarios));
        $table = $result['document']->children[0];
        $scenarios = $table->attr('odfTableScenarios');
        $first = is_array($scenarios) ? ($scenarios[0] ?? []) : [];
        $second = is_array($scenarios) ? ($scenarios[1] ?? []) : [];

        $t->same('table', $table->type);
        $t->same('ScenarioReview', $table->attr('tableName'));
        $t->same(2, $table->attr('scenarioCount'));
        $t->same(1, $table->attr('activeScenarioCount'));
        $t->same(2, count($scenarios));
        $t->same('ReadyImport', $first['name'] ?? null);
        $t->same(true, $first['displayBorder'] ?? null);
        $t->same('#00843d', $first['borderColor'] ?? null);
        $t->same(false, $first['copyBack'] ?? null);
        $t->same(true, $first['copyStyles'] ?? null);
        $t->same(false, $first['copyFormulas'] ?? null);
        $t->same(true, $first['isActive'] ?? null);
        $t->same([
            'ScenarioReview.A2:ScenarioReview.B3',
            'ScenarioReview.D2:ScenarioReview.D4',
        ], $first['scenarioRanges'] ?? null);
        $t->same('Approved rows for WordPress import', $first['comment'] ?? null);
        $t->same('DraftFallback', $second['name'] ?? null);
        $t->same(false, $second['displayBorder'] ?? null);
        $t->same(false, $second['isActive'] ?? null);
        $t->same(['ScenarioReview.A5:ScenarioReview.B6'], $second['scenarioRanges'] ?? null);
        $t->same('2', $table->attr('htmlAttributes')['data-odf-table-scenario-count']);
        $t->same('1', $table->attr('htmlAttributes')['data-odf-table-active-scenario-count']);
        $t->same('ReadyImport,DraftFallback', $table->attr('htmlAttributes')['data-odf-table-scenario-names']);
        $t->same('ScenarioReview.A2:ScenarioReview.B3;ScenarioReview.D2:ScenarioReview.D4;ScenarioReview.A5:ScenarioReview.B6', $table->attr('htmlAttributes')['data-odf-table-scenario-ranges']);
        $t->same(2, $result['importReport']['content']['tableScenarioCount']);
        $t->same(1, $result['importReport']['content']['activeTableScenarioCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': ScenarioReview', $markdown);
        $t->contains('<table class="odf-table-scenario" data-odf-table-name="ScenarioReview" data-odf-table-scenario-count="2" data-odf-table-active-scenario-count="1" data-odf-table-scenario-names="ReadyImport,DraftFallback" data-odf-table-scenario-ranges="ScenarioReview.A2:ScenarioReview.B3;ScenarioReview.D2:ScenarioReview.D4;ScenarioReview.A5:ScenarioReview.B6">', $blocksHtml);
    },
    'preserves quoted ODT table range tokens in print scenarios and consolidation metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithQuotedRanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:consolidation
        table:function="sum"
        table:source-cell-range-addresses="'Source Team''s Sheet'.A1:'Source Team''s Sheet'.B5 'Escalated Review'.C1:'Escalated Review'.C4"
        table:target-cell-address="'Summary Sheet'.A1"
        table:use-labels="row"
        table:link-to-source-data="true"/>
      <table:table
        table:name="Quoted Range Review"
        table:print-ranges="'Review Sheet'.A1:'Review Sheet'.B2 QuotedRange.D1:QuotedRange.D4">
        <table:scenario
          table:name="QuotedReady"
          table:is-active="true"
          table:scenario-ranges="'Review Sheet'.A2:'Review Sheet'.B3 QuotedRange.C1:QuotedRange.C4"/>
        <table:table-row>
          <table:table-cell><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithQuotedRanges));
        $table = $result['document']->children[0];
        $scenarios = $table->attr('odfTableScenarios');
        $consolidations = $result['contentDeclarations']['consolidations'];
        $consolidation = $consolidations[0] ?? [];

        $t->same('table', $table->type);
        $t->same('Quoted Range Review', $table->attr('tableName'));
        $t->same([
            "'Review Sheet'.A1:'Review Sheet'.B2",
            'QuotedRange.D1:QuotedRange.D4',
        ], $table->attr('odfPrintRanges'));
        $t->same(2, $table->attr('printRangeCount'));
        $t->same('2', $table->attr('htmlAttributes')['data-odf-table-print-range-count']);
        $t->same("'Review Sheet'.A1:'Review Sheet'.B2;QuotedRange.D1:QuotedRange.D4", $table->attr('htmlAttributes')['data-odf-table-print-ranges']);

        $t->same(1, $table->attr('scenarioCount'));
        $t->same(1, $table->attr('activeScenarioCount'));
        $t->same([
            "'Review Sheet'.A2:'Review Sheet'.B3",
            'QuotedRange.C1:QuotedRange.C4',
        ], $scenarios[0]['scenarioRanges']);
        $t->same("'Review Sheet'.A2:'Review Sheet'.B3;QuotedRange.C1:QuotedRange.C4", $table->attr('htmlAttributes')['data-odf-table-scenario-ranges']);

        $t->same(1, $result['contentDeclarations']['consolidationCount']);
        $t->same(2, $result['contentDeclarations']['consolidationSourceRangeCount']);
        $t->same('sum', $consolidation['function'] ?? null);
        $t->same([
            "'Source Team''s Sheet'.A1:'Source Team''s Sheet'.B5",
            "'Escalated Review'.C1:'Escalated Review'.C4",
        ], $consolidation['sourceCellRangeAddresses'] ?? null);
        $t->same(2, $consolidation['sourceRangeCount'] ?? null);
        $t->same("'Summary Sheet'.A1", $consolidation['targetCellAddress'] ?? null);
        $t->same(true, $consolidation['linkToSourceData'] ?? null);
        $t->same(2, $result['importReport']['content']['tablePrintRangeCount']);
        $t->same(1, $result['importReport']['content']['tableScenarioCount']);
        $t->same(2, $result['importReport']['content']['consolidationSourceRangeCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('data-odf-table-print-ranges="&#039;Review Sheet&#039;.A1:&#039;Review Sheet&#039;.B2;QuotedRange.D1:QuotedRange.D4"', $blocksHtml);
        $t->contains('data-odf-table-scenario-ranges="&#039;Review Sheet&#039;.A2:&#039;Review Sheet&#039;.B3;QuotedRange.C1:QuotedRange.C4"', $blocksHtml);
    },
    'maps ODT table cell formulas and typed values into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTypedTableCells = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Calculated Review">
        <table:table-row>
          <table:table-cell><text:p>Metric</text:p></table:table-cell>
          <table:table-cell><text:p>Value</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell office:value-type="string" office:string-value="Source total"><text:p>Total</text:p></table:table-cell>
          <table:table-cell table:formula="of:=SUM([.B2:.B3])" office:value-type="currency" office:value="42.5" office:currency="USD"><text:p>$42.50</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell office:value-type="date" office:date-value="2026-06-05"><text:p>Review date</text:p></table:table-cell>
          <table:table-cell office:value-type="boolean" office:boolean-value="true"><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTypedTableCells));
        $table = $result['document']->children[0];
        $rows = $table->children[0]->children;
        $stringCell = $rows[1]->children[0];
        $formulaCell = $rows[1]->children[1];
        $dateCell = $rows[2]->children[0];
        $booleanCell = $rows[2]->children[1];
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];

        $t->same('table', $table->type);
        $t->same('Calculated Review', $table->attr('caption'));
        $t->same('Total', $stringCell->attr('text'));
        $t->same(['odf-table-cell-value'], $stringCell->attr('classes'));
        $t->same('string', $stringCell->attr('odfCellMetadata')['valueType']);
        $t->same('Source total', $stringCell->attr('odfCellMetadata')['stringValue']);
        $t->same('string', $stringCell->attr('htmlAttributes')['data-odf-cell-value-type']);
        $t->same('Source total', $stringCell->attr('htmlAttributes')['data-odf-cell-string-value']);

        $t->same('$42.50', $formulaCell->attr('text'));
        $t->same(['odf-table-cell-value', 'odf-table-cell-formula'], $formulaCell->attr('classes'));
        $t->same('of:=SUM([.B2:.B3])', $formulaCell->attr('odfCellMetadata')['formula']);
        $t->same('currency', $formulaCell->attr('odfCellMetadata')['valueType']);
        $t->same('42.5', $formulaCell->attr('odfCellMetadata')['value']);
        $t->same('USD', $formulaCell->attr('odfCellMetadata')['currency']);
        $t->same('of:=SUM([.B2:.B3])', $formulaCell->attr('htmlAttributes')['data-odf-cell-formula']);
        $t->same('currency', $formulaCell->attr('htmlAttributes')['data-odf-cell-value-type']);
        $t->same('42.5', $formulaCell->attr('htmlAttributes')['data-odf-cell-value']);
        $t->same('USD', $formulaCell->attr('htmlAttributes')['data-odf-cell-currency']);

        $t->same('date', $dateCell->attr('odfCellMetadata')['valueType']);
        $t->same('2026-06-05', $dateCell->attr('odfCellMetadata')['dateValue']);
        $t->same('boolean', $booleanCell->attr('odfCellMetadata')['valueType']);
        $t->same(true, $booleanCell->attr('odfCellMetadata')['booleanValue']);
        $t->same('true', $booleanCell->attr('htmlAttributes')['data-odf-cell-boolean-value']);
        $t->same(4, count(array_filter(
            $coverage,
            static fn (array $record): bool => isset($record['sourceAttributes']['htmlAttributes']['data-odf-cell-value-type'])
        )));
        $t->same('of:=SUM([.B2:.B3])', $coverage[3]['sourceAttributes']['htmlAttributes']['data-odf-cell-formula'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<td class="odf-table-cell-value" data-odf-cell-value-type="string" data-odf-cell-string-value="Source total"><p>Total</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-value odf-table-cell-formula" data-odf-cell-formula="of:=SUM([.B2:.B3])" data-odf-cell-value-type="currency" data-odf-cell-value="42.5" data-odf-cell-currency="USD"><p>$42.50</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-value" data-odf-cell-value-type="date" data-odf-cell-date-value="2026-06-05"><p>Review date</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-value" data-odf-cell-value-type="boolean" data-odf-cell-boolean-value="true"><p>Ready</p></td>', $blocksHtml);
    },
    'maps ODT table cell detective metadata into review handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $content = <<<'XML'
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="DependencyReview">
        <table:table-row>
          <table:table-cell><text:p>Metric</text:p></table:table-cell>
          <table:table-cell table:formula="of:=SUM([.B2:.B4])" office:value-type="float" office:value="42">
            <table:detective>
              <table:highlighted-range table:cell-range-address="DependencyReview.B2:DependencyReview.B4" table:direction="from-dependents" table:contains-error="true" />
              <table:highlighted-range table:cell-range-address="DependencyReview.C2:DependencyReview.C4" table:direction="to-precedents" />
              <table:operation table:name="trace-dependents" table:index="1" />
            </table:detective>
            <text:p>42</text:p>
          </table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $reader = new OdfReader();
        $result = $reader->readPackage($buildOdtPackage($content));
        $table = $result['document']->children[0];
        $rows = $table->children[0]->children;
        $cell = $rows[0]->children[1];
        $detective = $cell->attr('odfCellDetective');
        $htmlAttributes = $cell->attr('htmlAttributes', []);

        $t->same('table_cell', $cell->type);
        $t->same('42', $cell->attr('text'));
        $t->same(['odf-table-cell-value', 'odf-table-cell-formula', 'odf-table-cell-detective'], $cell->attr('classes'));
        $t->same(2, $detective['highlightedRangeCount']);
        $t->same(1, $detective['operationCount']);
        $t->same('DependencyReview.B2:DependencyReview.B4', $detective['highlightedRanges'][0]['cellRangeAddress']);
        $t->same('from-dependents', $detective['highlightedRanges'][0]['direction']);
        $t->same(true, $detective['highlightedRanges'][0]['containsError']);
        $t->same('DependencyReview.C2:DependencyReview.C4', $detective['highlightedRanges'][1]['cellRangeAddress']);
        $t->same('to-precedents', $detective['highlightedRanges'][1]['direction']);
        $t->same('trace-dependents', $detective['operations'][0]['name']);
        $t->same(1, $detective['operations'][0]['index']);
        $t->same('2', $htmlAttributes['data-odf-cell-detective-highlight-count']);
        $t->same('DependencyReview.B2:DependencyReview.B4;DependencyReview.C2:DependencyReview.C4', $htmlAttributes['data-odf-cell-detective-ranges']);
        $t->same('from-dependents,to-precedents', $htmlAttributes['data-odf-cell-detective-directions']);
        $t->same('1', $htmlAttributes['data-odf-cell-detective-error-count']);
        $t->same('1', $htmlAttributes['data-odf-cell-detective-operation-count']);
        $t->same('trace-dependents', $htmlAttributes['data-odf-cell-detective-operation-names']);
        $t->same(1, $result['importReport']['content']['tableCellDetectiveCount']);
        $t->same(2, $result['importReport']['content']['tableCellDetectiveHighlightCount']);
        $t->same(1, $result['importReport']['content']['tableCellDetectiveOperationCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('class="odf-table-cell-value odf-table-cell-formula odf-table-cell-detective"', $blocks);
        $t->contains('data-odf-cell-detective-highlight-count="2"', $blocks);
        $t->contains('data-odf-cell-detective-ranges="DependencyReview.B2:DependencyReview.B4;DependencyReview.C2:DependencyReview.C4"', $blocks);
        $t->contains('data-odf-cell-detective-error-count="1"', $blocks);
        $t->contains('data-odf-cell-detective-operation-names="trace-dependents"', $blocks);
    },
    'maps ODT table cell annotations into review metadata without polluting cell text' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithAnnotatedCell = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <table:table table:name="Review annotations">
        <table:table-row>
          <table:table-cell>
            <text:p>Ready</text:p>
            <office:annotation office:name="cell-review-note">
              <dc:creator>Sheet Reviewer</dc:creator>
              <dc:date>2026-06-09T01:11:00Z</dc:date>
              <text:p>Confirm imported source status.</text:p>
            </office:annotation>
          </table:table-cell>
          <table:table-cell><text:p>Visible neighbor</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithAnnotatedCell));
        $table = $result['document']->children[0];
        $cell = $table->children[0]->children[0]->children[0];
        $annotations = $cell->attr('odfCellAnnotations');

        $t->same('Ready', $cell->attr('text'));
        $t->same(1, count($cell->children));
        $t->same(true, is_array($annotations));
        $t->same(1, count($annotations));
        $t->same('cell-review-note', $annotations[0]['name']);
        $t->same('Sheet Reviewer', $annotations[0]['author']);
        $t->same('2026-06-09T01:11:00Z', $annotations[0]['date']);
        $t->same('Confirm imported source status.', $annotations[0]['text']);
        $t->same(1, $cell->attr('annotationCount'));
        $t->same(['odf-table-cell-annotation'], $cell->attr('classes'));
        $t->same('1', $cell->attr('htmlAttributes')['data-odf-cell-annotation-count']);
        $t->same('Sheet Reviewer', $cell->attr('htmlAttributes')['data-odf-cell-annotation-authors']);
        $t->same('2026-06-09T01:11:00Z', $cell->attr('htmlAttributes')['data-odf-cell-annotation-dates']);
        $t->same(1, $result['importReport']['content']['tableCellAnnotationCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Ready', $markdown);
        $t->contains('<td class="odf-table-cell-annotation" data-odf-cell-annotation-count="1" data-odf-cell-annotation-authors="Sheet Reviewer" data-odf-cell-annotation-dates="2026-06-09T01:11:00Z" data-odf-cell-annotation-text-count="1"><p>Ready</p></td>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'Confirm imported source status.'), 'ODT cell annotation comments must remain review metadata, not visible table-cell content');
    },
    'maps ODT content validations into declarations and table cell metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithContentValidations = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:content-validations>
        <table:content-validation
          table:name="ReviewStatusValidation"
          table:condition="cell-content-is-in-list(&quot;draft&quot;;&quot;ready&quot;;&quot;legal&quot;)"
          table:base-cell-address="Review.B2"
          table:allow-empty-cell="false"
          table:display-list="sort-ascending">
          <table:help-message table:title="Review status" table:display="true">
            <text:p>Choose a migration review status.</text:p>
          </table:help-message>
          <table:error-message table:title="Invalid status" table:display="true" table:message-type="warning">
            <text:p>Use draft, ready, or legal.</text:p>
          </table:error-message>
          <table:error-macro table:name="ReviewStatusMacro" table:execute="false"/>
        </table:content-validation>
      </table:content-validations>
      <table:table table:name="Validation Review">
        <table:table-row>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
          <table:table-cell
            table:content-validation-name="ReviewStatusValidation"
            office:value-type="string"
            office:string-value="ready"><text:p>ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithContentValidations));
        $declarations = $result['contentDeclarations'];
        $validations = is_array($declarations['contentValidations'] ?? null) ? $declarations['contentValidations'] : [];
        $validationsByName = is_array($declarations['contentValidationsByName'] ?? null) ? $declarations['contentValidationsByName'] : [];
        $validation = is_array($validationsByName['ReviewStatusValidation'] ?? null) ? $validationsByName['ReviewStatusValidation'] : [];
        $table = $result['document']->children[0];
        $rows = $table->children[0]->children;
        $statusCell = $rows[0]->children[1];
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];
        $readyCoverage = is_array($coverage[1] ?? null) ? $coverage[1] : [];
        $condition = 'cell-content-is-in-list("draft";"ready";"legal")';

        $t->same(1, $declarations['contentValidationCount'] ?? null);
        $t->same(1, $declarations['contentValidationConditionCount'] ?? null);
        $t->same(2, $declarations['contentValidationMessageCount'] ?? null);
        $t->same(1, count($validations));
        $t->same('ReviewStatusValidation', $validation['name'] ?? null);
        $t->same($condition, $validation['condition'] ?? null);
        $t->same('Review.B2', $validation['baseCellAddress'] ?? null);
        $t->same(false, $validation['allowEmptyCell'] ?? null);
        $t->same('sort-ascending', $validation['displayList'] ?? null);
        $t->same('Review status', $validation['helpMessage']['title'] ?? null);
        $t->same(true, $validation['helpMessage']['display'] ?? null);
        $t->same('Choose a migration review status.', $validation['helpMessage']['text'] ?? null);
        $t->same('Invalid status', $validation['errorMessage']['title'] ?? null);
        $t->same('warning', $validation['errorMessage']['messageType'] ?? null);
        $t->same('Use draft, ready, or legal.', $validation['errorMessage']['text'] ?? null);
        $t->same('ReviewStatusMacro', $validation['errorMacro']['name'] ?? null);
        $t->same(false, $validation['errorMacro']['execute'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(1, $result['importReport']['contentDeclarations']['contentValidationCount'] ?? null);
        $t->same(1, $result['importReport']['contentDeclarations']['contentValidationConditionCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['contentValidationMessageCount'] ?? null);
        $t->same(1, $result['importReport']['content']['contentValidationCount'] ?? null);
        $t->same(1, $result['importReport']['content']['contentValidationConditionCount'] ?? null);
        $t->same(2, $result['importReport']['content']['contentValidationMessageCount'] ?? null);

        $t->same('ready', $statusCell->attr('text'));
        $t->same(['odf-table-cell-value', 'odf-table-cell-validation'], $statusCell->attr('classes'));
        $t->same('ReviewStatusValidation', $statusCell->attr('odfCellMetadata')['contentValidationName'] ?? null);
        $t->same('ReviewStatusValidation', $statusCell->attr('htmlAttributes')['data-odf-cell-content-validation-name'] ?? null);
        $t->same('true', $statusCell->attr('htmlAttributes')['data-odf-cell-content-validation-exists'] ?? null);
        $t->same($condition, $statusCell->attr('htmlAttributes')['data-odf-cell-content-validation-condition'] ?? null);
        $t->same('false', $statusCell->attr('htmlAttributes')['data-odf-cell-content-validation-allow-empty-cell'] ?? null);
        $t->same($validation, $statusCell->attr('odfContentValidation'));
        $t->same('ready', $readyCoverage['text'] ?? null);
        $t->same('ReviewStatusValidation', $readyCoverage['sourceAttributes']['htmlAttributes']['data-odf-cell-content-validation-name'] ?? null);
        $t->same($condition, $readyCoverage['sourceAttributes']['htmlAttributes']['data-odf-cell-content-validation-condition'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<td class="odf-table-cell-value odf-table-cell-validation" data-odf-cell-value-type="string" data-odf-cell-string-value="ready" data-odf-cell-content-validation-name="ReviewStatusValidation" data-odf-cell-content-validation-exists="true" data-odf-cell-content-validation-condition="cell-content-is-in-list(&quot;draft&quot;;&quot;ready&quot;;&quot;legal&quot;)" data-odf-cell-content-validation-allow-empty-cell="false"><p>ready</p></td>', $blocksHtml);
    },
    'maps ODT table cell style properties into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithCellProperties = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="BaseProtectedCell" style:family="table-cell">
      <style:table-cell-properties style:cell-protect="protected" style:print-content="false"/>
    </style:style>
    <style:style style:name="ReviewStatusCell" style:family="table-cell" style:parent-style-name="BaseProtectedCell" style:data-style-name="ReviewCurrencyFormat">
      <style:table-cell-properties
        fo:background-color="#fff4cc"
        fo:border="0.5pt solid #999999"
        fo:padding-left="3pt"
        style:vertical-align="middle"
        style:writing-mode="tb-rl"
        style:repeat-content="false"
        style:shrink-to-fit="true"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithStyledCells = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Cell Style Review">
        <table:table-row>
          <table:table-cell table:style-name="ReviewStatusCell"><text:p>Source note</text:p></table:table-cell>
          <table:table-cell><text:p>Plain cell</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithStyledCells, null, $stylesWithCellProperties));
        $table = $result['document']->children[0];
        $cell = $table->children[0]->children[0]->children[0];
        $plainCell = $table->children[0]->children[0]->children[1];
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];

        $t->same('ReviewStatusCell', $cell->attr('styleName'));
        $t->same('BaseProtectedCell', $cell->attr('style')['parentName']);
        $t->same('ReviewCurrencyFormat', $cell->attr('style')['dataStyleName']);
        $t->same('ReviewCurrencyFormat', $cell->attr('odfCellDataStyleName'));
        $t->same('protected', $cell->attr('style')['tableCellProperties']['cellProtect']);
        $t->same('#fff4cc', $cell->attr('odfCellStyleProperties')['backgroundColor']);
        $t->same('middle', $cell->attr('odfCellStyleProperties')['verticalAlign']);
        $t->same('tb-rl', $cell->attr('odfCellStyleProperties')['writingMode']);
        $t->same(false, $cell->attr('odfCellStyleProperties')['printContent']);
        $t->same(false, $cell->attr('odfCellStyleProperties')['repeatContent']);
        $t->same(true, $cell->attr('odfCellStyleProperties')['shrinkToFit']);
        $t->same(
            ['odf-table-cell-style', 'odf-table-cell-background', 'odf-table-cell-protected', 'odf-table-cell-print-hidden', 'odf-table-cell-vertical-align-middle', 'odf-table-cell-data-style'],
            $cell->attr('classes')
        );
        $t->same('ReviewStatusCell', $cell->attr('htmlAttributes')['data-odf-cell-style-name']);
        $t->same('ReviewCurrencyFormat', $cell->attr('htmlAttributes')['data-odf-cell-data-style-name']);
        $t->same('#fff4cc', $cell->attr('htmlAttributes')['data-odf-cell-background-color']);
        $t->same('middle', $cell->attr('htmlAttributes')['data-odf-cell-vertical-align']);
        $t->same('tb-rl', $cell->attr('htmlAttributes')['data-odf-cell-writing-mode']);
        $t->same('protected', $cell->attr('htmlAttributes')['data-odf-cell-protect']);
        $t->same('false', $cell->attr('htmlAttributes')['data-odf-cell-print-content']);
        $t->same('background-color:#fff4cc; vertical-align:middle; border:0.5pt solid #999999; padding-left:3pt', $cell->attr('htmlAttributes')['style']);
        $t->same(null, $plainCell->attr('odfCellStyleProperties'));
        $t->same(1, $result['importReport']['content']['tableStyledCellCount']);
        $t->same(1, $result['importReport']['content']['tableDataStyledCellCount']);
        $t->same(1, $result['importReport']['content']['tableProtectedCellCount']);
        $t->same(1, $result['importReport']['content']['tablePrintHiddenCellCount']);
        $t->same('ReviewStatusCell', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-cell-style-name'] ?? null);
        $t->same('ReviewCurrencyFormat', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-cell-data-style-name'] ?? null);
        $t->same('background-color:#fff4cc; vertical-align:middle; border:0.5pt solid #999999; padding-left:3pt', $coverage[0]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<td class="odf-table-cell-style odf-table-cell-background odf-table-cell-protected odf-table-cell-print-hidden odf-table-cell-vertical-align-middle odf-table-cell-data-style" data-odf-cell-style-name="ReviewStatusCell" data-odf-cell-background-color="#fff4cc" data-odf-cell-vertical-align="middle" data-odf-cell-writing-mode="tb-rl" data-odf-cell-protect="protected" data-odf-cell-print-content="false" data-odf-cell-repeat-content="false" data-odf-cell-shrink-to-fit="true" data-odf-cell-data-style-name="ReviewCurrencyFormat" style="background-color:#fff4cc; vertical-align:middle; border:0.5pt solid #999999; padding-left:3pt"><p>Source note</p></td>', $blocksHtml);
    },
    'maps ODT number date and currency data styles into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithDataStyles = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0">
  <office:styles>
    <number:currency-style style:name="ReviewCurrencyFormat" style:display-name="Review Currency" number:language="en" number:country="US">
      <number:currency-symbol number:language="en" number:country="US">$</number:currency-symbol>
      <number:number number:decimal-places="2" number:min-integer-digits="1" number:grouping="true"/>
      <number:text> reviewed</number:text>
    </number:currency-style>
    <number:date-style style:name="ReviewDateFormat" number:format-source="fixed" number:language="en" number:country="US">
      <number:day number:style="long"/>
      <number:text>/</number:text>
      <number:month number:style="long"/>
      <number:text>/</number:text>
      <number:year number:style="long"/>
    </number:date-style>
    <style:style style:name="CurrencyCell" style:family="table-cell" style:data-style-name="ReviewCurrencyFormat"/>
    <style:style style:name="DateCell" style:family="table-cell" style:data-style-name="ReviewDateFormat"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithDataStyledCells = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Data Style Review">
        <table:table-row>
          <table:table-cell table:style-name="CurrencyCell" office:value-type="currency" office:value="42.5" office:currency="USD"><text:p>$42.50 reviewed</text:p></table:table-cell>
          <table:table-cell table:style-name="DateCell" office:value-type="date" office:date-value="2026-06-09"><text:p>09/06/2026</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDataStyledCells, null, $stylesWithDataStyles));
        $table = $result['document']->children[0];
        $currencyCell = $table->children[0]->children[0]->children[0];
        $dateCell = $table->children[0]->children[0]->children[1];
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];

        $t->same(2, count($result['dataStyles']));
        $t->same(2, $result['importReport']['styles']['dataStyleCount']);
        $t->same('currency', $result['dataStyles']['ReviewCurrencyFormat']['type']);
        $t->same('Review Currency', $result['dataStyles']['ReviewCurrencyFormat']['displayName']);
        $t->same('en', $result['dataStyles']['ReviewCurrencyFormat']['language']);
        $t->same('US', $result['dataStyles']['ReviewCurrencyFormat']['country']);
        $t->same(3, $result['dataStyles']['ReviewCurrencyFormat']['componentCount']);
        $t->same('$', $result['dataStyles']['ReviewCurrencyFormat']['components'][0]['text']);
        $t->same(true, $result['dataStyles']['ReviewCurrencyFormat']['components'][1]['grouping']);
        $t->same(2, $result['dataStyles']['ReviewCurrencyFormat']['components'][1]['decimalPlaces']);
        $t->same('currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed', $result['dataStyles']['ReviewCurrencyFormat']['formatSignature']);

        $t->same('date', $result['dataStyles']['ReviewDateFormat']['type']);
        $t->same('fixed', $result['dataStyles']['ReviewDateFormat']['formatSource']);
        $t->same(5, $result['dataStyles']['ReviewDateFormat']['componentCount']);
        $t->same('day[style=long]|text:/|month[style=long]|text:/|year[style=long]', $result['dataStyles']['ReviewDateFormat']['formatSignature']);

        $t->same('ReviewCurrencyFormat', $currencyCell->attr('odfCellDataStyleName'));
        $t->same('currency', $currencyCell->attr('odfCellDataStyleType'));
        $t->same('currency', $currencyCell->attr('odfCellDataStyle')['type']);
        $t->same(3, $currencyCell->attr('odfCellDataStyleComponentCount'));
        $t->same('currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed', $currencyCell->attr('odfCellDataStyleSignature'));
        $t->same(['odf-table-cell-value', 'odf-table-cell-data-style'], $currencyCell->attr('classes'));
        $t->same('currency', $currencyCell->attr('htmlAttributes')['data-odf-cell-data-style-type']);
        $t->same('3', $currencyCell->attr('htmlAttributes')['data-odf-cell-data-style-component-count']);
        $t->same('currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed', $currencyCell->attr('htmlAttributes')['data-odf-cell-data-style-signature']);

        $t->same('ReviewDateFormat', $dateCell->attr('odfCellDataStyleName'));
        $t->same('date', $dateCell->attr('odfCellDataStyleType'));
        $t->same(2, $result['importReport']['content']['tableDataStyledCellCount']);
        $t->same(2, $result['importReport']['content']['tableDataStyleDefinitionCellCount']);
        $t->same('date', $coverage[1]['sourceAttributes']['htmlAttributes']['data-odf-cell-data-style-type'] ?? null);
        $t->same('day[style=long]|text:/|month[style=long]|text:/|year[style=long]', $coverage[1]['sourceAttributes']['htmlAttributes']['data-odf-cell-data-style-signature'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('data-odf-cell-data-style-type="currency"', $blocksHtml);
        $t->contains('data-odf-cell-data-style-component-count="3"', $blocksHtml);
        $t->contains('data-odf-cell-data-style-signature="currency-symbol:$|number[decimalPlaces=2,grouping=true,minIntegerDigits=1]|text: reviewed"', $blocksHtml);
        $t->contains('data-odf-cell-data-style-type="date"', $blocksHtml);
        $t->contains('data-odf-cell-data-style-signature="day[style=long]|text:/|month[style=long]|text:/|year[style=long]"', $blocksHtml);
    },
    'applies ODT row and column default cell styles before table review handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithDefaultCellStyles = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="BaseDefaultCell" style:family="table-cell">
      <style:table-cell-properties style:cell-protect="protected" style:print-content="false"/>
    </style:style>
    <style:style style:name="RowDefaultCell" style:family="table-cell" style:parent-style-name="BaseDefaultCell">
      <style:table-cell-properties fo:background-color="#fff4cc" style:vertical-align="middle"/>
    </style:style>
    <style:style style:name="ColumnDefaultCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#e6ffed" style:vertical-align="top"/>
    </style:style>
    <style:style style:name="ExplicitCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#dbeafe" fo:border="0.5pt solid #1d4ed8"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithDefaultCellStyles = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Default Cell Style Review">
        <table:table-column table:default-cell-style-name="ColumnDefaultCell"/>
        <table:table-column table:default-cell-style-name="ColumnDefaultCell"/>
        <table:table-row table:default-cell-style-name="RowDefaultCell">
          <table:table-cell><text:p>Row default wins</text:p></table:table-cell>
          <table:table-cell table:style-name="ExplicitCell"><text:p>Explicit cell wins</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Column default applies</text:p></table:table-cell>
          <table:table-cell><text:p>Second column default applies</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDefaultCellStyles, null, $stylesWithDefaultCellStyles));
        $table = $result['document']->children[0];
        $rows = $table->children[0]->children;
        $rowDefaultCell = $rows[0]->children[0];
        $explicitCell = $rows[0]->children[1];
        $columnDefaultCell = $rows[1]->children[0];
        $secondColumnDefaultCell = $rows[1]->children[1];
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];

        $t->same('RowDefaultCell', $rowDefaultCell->attr('styleName'));
        $t->same('RowDefaultCell', $rowDefaultCell->attr('defaultCellStyleName'));
        $t->same('row', $rowDefaultCell->attr('defaultCellStyleSource'));
        $t->same('BaseDefaultCell', $rowDefaultCell->attr('style')['parentName']);
        $t->same('#fff4cc', $rowDefaultCell->attr('odfCellStyleProperties')['backgroundColor']);
        $t->same('protected', $rowDefaultCell->attr('odfCellStyleProperties')['cellProtect']);
        $t->same(false, $rowDefaultCell->attr('odfCellStyleProperties')['printContent']);
        $t->same('RowDefaultCell', $rowDefaultCell->attr('htmlAttributes')['data-odf-cell-style-name']);
        $t->same('RowDefaultCell', $rowDefaultCell->attr('htmlAttributes')['data-odf-cell-default-style-name']);
        $t->same('row', $rowDefaultCell->attr('htmlAttributes')['data-odf-cell-default-style-source']);

        $t->same('ExplicitCell', $explicitCell->attr('styleName'));
        $t->same(null, $explicitCell->attr('defaultCellStyleName'));
        $t->same('#dbeafe', $explicitCell->attr('odfCellStyleProperties')['backgroundColor']);
        $t->same('0.5pt solid #1d4ed8', $explicitCell->attr('odfCellStyleProperties')['border']);

        $t->same('ColumnDefaultCell', $columnDefaultCell->attr('styleName'));
        $t->same('ColumnDefaultCell', $columnDefaultCell->attr('defaultCellStyleName'));
        $t->same('column', $columnDefaultCell->attr('defaultCellStyleSource'));
        $t->same('#e6ffed', $columnDefaultCell->attr('odfCellStyleProperties')['backgroundColor']);
        $t->same('top', $columnDefaultCell->attr('odfCellStyleProperties')['verticalAlign']);
        $t->same('ColumnDefaultCell', $secondColumnDefaultCell->attr('defaultCellStyleName'));
        $t->same('column', $secondColumnDefaultCell->attr('defaultCellStyleSource'));

        $t->same(4, $result['importReport']['content']['tableStyledCellCount']);
        $t->same(1, $result['importReport']['content']['tableProtectedCellCount']);
        $t->same(1, $result['importReport']['content']['tablePrintHiddenCellCount']);
        $t->same('RowDefaultCell', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-cell-default-style-name'] ?? null);
        $t->same('row', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-cell-default-style-source'] ?? null);
        $t->same('ColumnDefaultCell', $coverage[2]['sourceAttributes']['htmlAttributes']['data-odf-cell-default-style-name'] ?? null);
        $t->same('column', $coverage[2]['sourceAttributes']['htmlAttributes']['data-odf-cell-default-style-source'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('data-odf-cell-style-name="RowDefaultCell"', $blocksHtml);
        $t->contains('data-odf-cell-default-style-name="RowDefaultCell"', $blocksHtml);
        $t->contains('data-odf-cell-default-style-source="row"', $blocksHtml);
        $t->contains('data-odf-cell-style-name="ColumnDefaultCell"', $blocksHtml);
        $t->contains('data-odf-cell-default-style-source="column"', $blocksHtml);
        $t->contains('data-odf-cell-style-name="ExplicitCell"', $blocksHtml);
        $t->contains('Explicit cell wins', $blocksHtml);
    },
    'preserves ODT covered table cell provenance without rendering extra cells' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithCoveredCells = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="CoveredAuditCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#fff4cc" style:cell-protect="protected"/>
    </style:style>
    <style:style style:name="CoveredMutedCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#f3f4f6"/>
    </style:style>
    <style:style style:name="TrailingDefaultCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#e0f2fe"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithCoveredCells = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Covered Cell Review">
        <table:table-column/>
        <table:table-column/>
        <table:table-column/>
        <table:table-column/>
        <table:table-column table:default-cell-style-name="TrailingDefaultCell"/>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="4"><text:p>Published source</text:p></table:table-cell>
          <table:covered-table-cell table:style-name="CoveredAuditCell" office:value-type="string" office:string-value="hidden draft"><text:p>Draft hidden by merge</text:p></table:covered-table-cell>
          <table:covered-table-cell table:style-name="CoveredMutedCell" table:number-columns-repeated="2"/>
          <table:table-cell><text:p>Visible trailing</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:covered-table-cell table:style-name="CoveredAuditCell" office:value-type="string" office:string-value="rowspan source"/>
          <table:table-cell><text:p>Trailing note</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithCoveredCells, null, $stylesWithCoveredCells));
        $table = $result['document']->children[0];
        $rows = $table->children[0]->children;
        $anchorCell = $rows[0]->children[0];
        $trailingCell = $rows[0]->children[1];
        $leadingCoveredRow = $rows[1];
        $leadingRenderedCell = $rows[1]->children[0];
        $coveredCells = $anchorCell->attr('odfCoveredCells');
        $leadingCoveredCells = $leadingCoveredRow->attr('odfCoveredCells');
        $anchorHtml = $anchorCell->attr('htmlAttributes');
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];
        $summary = is_array($geometry) ? ($geometry['summary'] ?? []) : [];

        $t->same('Published source', $anchorCell->attr('text'));
        $t->same(4, $anchorCell->attr('colspan'));
        $t->same(3, $anchorCell->attr('coveredCellCount'));
        $t->same(3, count($coveredCells));
        $t->same(1, $coveredCells[0]['sourceColumn']);
        $t->same(2, $coveredCells[1]['sourceColumn']);
        $t->same(3, $coveredCells[2]['sourceColumn']);
        $t->same('CoveredAuditCell', $coveredCells[0]['styleName']);
        $t->same('protected', $coveredCells[0]['styleProperties']['cellProtect']);
        $t->same('string', $coveredCells[0]['cellMetadata']['valueType']);
        $t->same('hidden draft', $coveredCells[0]['cellMetadata']['stringValue']);
        $t->same('Draft hidden by merge', $coveredCells[0]['text']);
        $t->same('CoveredMutedCell', $coveredCells[1]['styleName']);
        $t->same(1, $coveredCells[1]['repeatIndex']);
        $t->same(2, $coveredCells[2]['repeatIndex']);
        $t->same(2, $coveredCells[2]['sourceRepeat']);
        $t->same(['odf-covered-cell-source'], $anchorCell->attr('classes'));
        $t->same('3', $anchorHtml['data-odf-covered-cell-count']);
        $t->same('1,2,3', $anchorHtml['data-odf-covered-cell-source-columns']);
        $t->same('CoveredAuditCell,CoveredMutedCell', $anchorHtml['data-odf-covered-cell-style-names']);
        $t->same('1', $anchorHtml['data-odf-covered-cell-text-count']);
        $t->same('1', $anchorHtml['data-odf-covered-cell-value-count']);
        $t->same('2', $anchorHtml['data-odf-covered-cell-repeated-count']);

        $t->same('TrailingDefaultCell', $trailingCell->attr('styleName'));
        $t->same('column', $trailingCell->attr('defaultCellStyleSource'));
        $t->same('#e0f2fe', $trailingCell->attr('odfCellStyleProperties')['backgroundColor']);
        $t->same('Visible trailing', $trailingCell->attr('text'));
        $t->same(1, $leadingCoveredRow->attr('coveredCellCount'));
        $t->same(1, count($leadingCoveredCells));
        $t->same(0, $leadingCoveredCells[0]['sourceColumn']);
        $t->same('rowspan source', $leadingCoveredCells[0]['cellMetadata']['stringValue']);
        $t->same(['odf-covered-table-row'], $leadingCoveredRow->attr('classes'));
        $t->same('1', $leadingCoveredRow->attr('htmlAttributes')['data-odf-covered-cell-count']);
        $t->same('Trailing note', $leadingRenderedCell->attr('text'));

        $t->same(5, $geometry['columnCount']);
        $t->same(3, $summary['coveredSlotCount']);
        $t->same(3, $summary['cellCount']);
        $t->same('3', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-covered-cell-count'] ?? null);
        $t->same('TrailingDefaultCell', $coverage[1]['sourceAttributes']['htmlAttributes']['data-odf-cell-default-style-name'] ?? null);
        $t->same(4, $result['importReport']['content']['tableCoveredCellCount']);
        $t->same(4, $result['importReport']['content']['tableCoveredCellMetadataCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<td class="odf-covered-cell-source" data-odf-covered-cell-count="3" data-odf-covered-cell-source-columns="1,2,3" data-odf-covered-cell-style-names="CoveredAuditCell,CoveredMutedCell" data-odf-covered-cell-text-count="1" data-odf-covered-cell-value-count="1" data-odf-covered-cell-repeated-count="2" colspan="4"><p>Published source</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-style odf-table-cell-background" data-odf-cell-style-name="TrailingDefaultCell" data-odf-cell-background-color="#e0f2fe" data-odf-cell-default-style-name="TrailingDefaultCell" data-odf-cell-default-style-source="column" style="background-color:#e0f2fe"><p>Visible trailing</p></td>', $blocksHtml);
        $t->contains('<tr class="odf-covered-table-row" data-odf-covered-cell-count="1" data-odf-covered-cell-source-columns="0" data-odf-covered-cell-style-names="CoveredAuditCell" data-odf-covered-cell-value-count="1"><td><p>Trailing note</p></td></tr>', $blocksHtml);
    },
    'preserves ODT style map rules on table cell review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithStyleMaps = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ReadyCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#e6ffed"/>
    </style:style>
    <style:style style:name="EscalatedCell" style:family="table-cell">
      <style:table-cell-properties fo:background-color="#fff4cc"/>
    </style:style>
    <style:style style:name="ReviewDecisionCellBase" style:family="table-cell">
      <style:table-cell-properties style:cell-protect="protected"/>
      <style:map style:condition="cell-content()=&quot;Ready&quot;" style:apply-style-name="ReadyCell" style:base-cell-address="Review.B2"/>
    </style:style>
    <style:style style:name="ReviewDecisionCell" style:family="table-cell" style:parent-style-name="ReviewDecisionCellBase">
      <style:table-cell-properties fo:border="0.5pt solid #999999"/>
      <style:map style:condition="cell-content()=&quot;Escalated&quot;" style:apply-style-name="EscalatedCell" style:base-cell-address="Review.B3"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithStyleMapCells = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Conditional Styles">
        <table:table-row>
          <table:table-cell table:style-name="ReviewDecisionCell"><text:p>Ready</text:p></table:table-cell>
          <table:table-cell><text:p>Plain</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithStyleMapCells, null, $stylesWithStyleMaps));
        $table = $result['document']->children[0];
        $cell = $table->children[0]->children[0]->children[0];
        $plainCell = $table->children[0]->children[0]->children[1];
        $styleMaps = $cell->attr('odfCellStyleMaps');
        $htmlAttributes = $cell->attr('htmlAttributes');
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];

        $t->same('ReviewDecisionCell', $cell->attr('styleName'));
        $t->same('ReviewDecisionCellBase', $cell->attr('style')['parentName']);
        $t->same('protected', $cell->attr('style')['tableCellProperties']['cellProtect']);
        $t->same('0.5pt solid #999999', $cell->attr('style')['tableCellProperties']['border']);
        $t->same(2, count($styleMaps));
        $t->same('cell-content()="Ready"', $styleMaps[0]['condition']);
        $t->same('ReadyCell', $styleMaps[0]['applyStyleName']);
        $t->same('Review.B2', $styleMaps[0]['baseCellAddress']);
        $t->same('cell-content()="Escalated"', $styleMaps[1]['condition']);
        $t->same('EscalatedCell', $styleMaps[1]['applyStyleName']);
        $t->same('Review.B3', $styleMaps[1]['baseCellAddress']);
        $t->same(null, $plainCell->attr('odfCellStyleMaps'));
        $t->same(['odf-table-cell-style', 'odf-table-cell-protected', 'odf-table-cell-style-map'], $cell->attr('classes'));
        $t->same('2', $htmlAttributes['data-odf-cell-style-map-count']);
        $t->same('cell-content()="Ready"', $htmlAttributes['data-odf-cell-style-map-1-condition']);
        $t->same('ReadyCell', $htmlAttributes['data-odf-cell-style-map-1-apply-style-name']);
        $t->same('Review.B2', $htmlAttributes['data-odf-cell-style-map-1-base-cell-address']);
        $t->same('cell-content()="Escalated"', $htmlAttributes['data-odf-cell-style-map-2-condition']);
        $t->same('EscalatedCell', $htmlAttributes['data-odf-cell-style-map-2-apply-style-name']);
        $t->same('Review.B3', $htmlAttributes['data-odf-cell-style-map-2-base-cell-address']);
        $t->same('2', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-cell-style-map-count'] ?? null);
        $t->same('EscalatedCell', $coverage[0]['sourceAttributes']['htmlAttributes']['data-odf-cell-style-map-2-apply-style-name'] ?? null);
        $t->same(2, $result['importReport']['styles']['styleMapCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<td class="odf-table-cell-style odf-table-cell-protected odf-table-cell-style-map" data-odf-cell-style-name="ReviewDecisionCell" data-odf-cell-protect="protected" data-odf-cell-style-map-count="2" data-odf-cell-style-map-1-condition="cell-content()=&quot;Ready&quot;" data-odf-cell-style-map-1-apply-style-name="ReadyCell" data-odf-cell-style-map-1-base-cell-address="Review.B2" data-odf-cell-style-map-2-condition="cell-content()=&quot;Escalated&quot;" data-odf-cell-style-map-2-apply-style-name="EscalatedCell" data-odf-cell-style-map-2-base-cell-address="Review.B3" style="border:0.5pt solid #999999"><p>Ready</p></td>', $blocksHtml);
    },
    'maps ODT table templates into table review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithTableTemplate = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:styles>
    <table:table-template
      table:name="ReviewTemplate"
      table:first-row-start-column="HeaderStart"
      table:first-row-end-column="HeaderEnd"
      table:first-column="FirstColumn"
      table:last-column="LastColumn"
      table:first-row="HeaderRow"
      table:last-row="SummaryRow"
      table:body="BodyCell"
      table:odd-rows="OddRow"
      table:even-rows="EvenRow"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithTemplatedTable = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Templated Review" table:template-name="ReviewTemplate">
        <table:table-row>
          <table:table-cell><text:p>Area</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Media</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTemplatedTable, null, $stylesWithTableTemplate));
        $table = $result['document']->children[0];
        $template = $result['tableTemplates']['ReviewTemplate'];

        $t->same(1, $result['document']->attr('tableTemplates')['count']);
        $t->same(1, $result['importReport']['tableTemplates']['count']);
        $t->same(1, $result['importReport']['styles']['tableTemplateCount']);
        $t->same('ReviewTemplate', $template['name']);
        $t->same('HeaderStart', $template['styles']['firstRowStartColumn']);
        $t->same('HeaderEnd', $template['styles']['firstRowEndColumn']);
        $t->same('FirstColumn', $template['styles']['firstColumn']);
        $t->same('LastColumn', $template['styles']['lastColumn']);
        $t->same('HeaderRow', $template['styles']['firstRow']);
        $t->same('SummaryRow', $template['styles']['lastRow']);
        $t->same('BodyCell', $template['styles']['body']);
        $t->same('OddRow', $template['styles']['oddRows']);
        $t->same('EvenRow', $template['styles']['evenRows']);

        $t->same('table', $table->type);
        $t->same('ReviewTemplate', $table->attr('templateName'));
        $t->same(['odf-table-template'], $table->attr('classes'));
        $t->same('ReviewTemplate', $table->attr('tableTemplate')['name']);
        $t->same('BodyCell', $table->attr('tableTemplate')['styles']['body']);
        $t->same('ReviewTemplate', $table->attr('htmlAttributes')['data-odf-table-template-name']);
        $t->same('true', $table->attr('htmlAttributes')['data-odf-table-template-exists']);
        $t->same('9', $table->attr('htmlAttributes')['data-odf-table-template-style-count']);
        $t->same(1, $result['importReport']['content']['tableTemplateReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': Templated Review', $markdown);
        $t->contains('<table class="odf-table-template" data-odf-table-name="Templated Review" data-odf-table-template-name="ReviewTemplate" data-odf-table-template-exists="true" data-odf-table-template-style-count="9">', $blocksHtml);
    },
    'maps ODT table column repeats visibility and widths into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithColumnMetadata = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="ReviewNarrowColumn" style:family="table-column">
      <style:table-column-properties style:column-width="2cm" style:rel-column-width="1*" style:use-optimal-column-width="false"/>
    </style:style>
    <style:style style:name="ReviewWideColumn" style:family="table-column">
      <style:table-column-properties style:column-width="4cm" style:rel-column-width="2*" style:use-optimal-column-width="true"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithColumnMetadata = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Column Review">
        <table:table-column table:style-name="ReviewNarrowColumn" table:number-columns-repeated="2" table:default-cell-style-name="ReviewInputCell"/>
        <table:table-column table:style-name="ReviewWideColumn" table:visibility="collapse"/>
        <table:table-row>
          <table:table-cell><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
          <table:table-cell><text:p>Reviewer notes</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
          <table:table-cell><text:p>Hidden source column remains auditable.</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithColumnMetadata, null, $stylesWithColumnMetadata));
        $table = $result['document']->children[0];
        $columns = $table->attr('odfTableColumns');
        $summary = $table->attr('odfTableColumnSummary');
        $widths = $table->attr('widths');

        $t->same('table', $table->type);
        $t->true(is_array($columns));
        $columns = is_array($columns) ? $columns : [];
        $t->same(3, count($columns));
        $t->same(1, $columns[0]['index']);
        $t->same(1, $columns[0]['sourceIndex']);
        $t->same(1, $columns[0]['repeatIndex']);
        $t->same(2, $columns[0]['sourceRepeat']);
        $t->same('ReviewNarrowColumn', $columns[0]['styleName']);
        $t->same('ReviewInputCell', $columns[0]['defaultCellStyleName']);
        $t->same(false, $columns[0]['hidden']);
        $t->same('2cm', $columns[0]['width']);
        $t->same('1*', $columns[0]['relativeWidth']);
        $t->same(false, $columns[0]['useOptimalWidth']);
        $t->true(abs($columns[0]['widthPoints'] - 56.69291338582677) < 0.000001);
        $t->same(2, $columns[1]['repeatIndex']);
        $t->same(2, $columns[1]['sourceRepeat']);
        $t->same(3, $columns[2]['index']);
        $t->same(2, $columns[2]['sourceIndex']);
        $t->same('ReviewWideColumn', $columns[2]['styleName']);
        $t->same('collapse', $columns[2]['visibility']);
        $t->same(true, $columns[2]['hidden']);
        $t->same('4cm', $columns[2]['width']);
        $t->same('2*', $columns[2]['relativeWidth']);
        $t->same(true, $columns[2]['useOptimalWidth']);

        $t->same([
            'count' => 3,
            'sourceCount' => 2,
            'hiddenCount' => 1,
            'repeatedColumnCount' => 2,
            'truncatedRepeatCount' => 0,
        ], $summary);
        $t->same('3', $table->attr('htmlAttributes')['data-odf-table-column-count']);
        $t->same('1', $table->attr('htmlAttributes')['data-odf-table-hidden-column-count']);
        $t->same('2', $table->attr('htmlAttributes')['data-odf-table-repeated-column-count']);
        $t->true(is_array($widths));
        $widths = is_array($widths) ? $widths : [];
        $t->true(abs($widths[0] - 0.25) < 0.000001);
        $t->true(abs($widths[1] - 0.25) < 0.000001);
        $t->true(abs($widths[2] - 0.5) < 0.000001);
        $t->same(3, $result['importReport']['content']['tableColumnDefinitionCount']);
        $t->same(1, $result['importReport']['content']['hiddenTableColumnCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<table data-odf-table-name="Column Review" data-odf-table-column-count="3" data-odf-table-hidden-column-count="1" data-odf-table-repeated-column-count="2">', $blocksHtml);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:50%"/></colgroup>', $blocksHtml);
        $t->contains('<td><p>Hidden source column remains auditable.</p></td>', $blocksHtml);
    },
    'maps ODT table row repeats visibility and styles into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithRowMetadata = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Row Review">
        <table:table-row table:style-name="ReviewHeaderRow">
          <table:table-cell><text:p>Item</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row table:style-name="HiddenReviewRow" table:default-cell-style-name="HiddenReviewCell" table:visibility="collapse" table:number-rows-repeated="2">
          <table:table-cell><text:p>Archived source row</text:p></table:table-cell>
          <table:table-cell><text:p>Keep for audit</text:p></table:table-cell>
        </table:table-row>
        <table:table-row table:style-name="ReviewSummaryRow">
          <table:table-cell><text:p>Summary</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithRowMetadata));
        $table = $result['document']->children[0];
        $body = $table->children[0];
        $rows = $body->children;

        $t->same('table', $table->type);
        $t->same(4, count($rows));
        $t->same('ReviewHeaderRow', $rows[0]->attr('styleName'));
        $t->same('HiddenReviewRow', $rows[1]->attr('styleName'));
        $t->same('HiddenReviewCell', $rows[1]->attr('defaultCellStyleName'));
        $t->same('collapse', $rows[1]->attr('visibility'));
        $t->same(true, $rows[1]->attr('hidden'));
        $t->same(1, $rows[1]->attr('repeatIndex'));
        $t->same(2, $rows[1]->attr('sourceRepeat'));
        $t->same(2, $rows[1]->attr('declaredRepeat'));
        $t->same(['odf-hidden-table-row', 'odf-repeated-table-row'], $rows[1]->attr('classes'));
        $t->same('2', $rows[1]->attr('htmlAttributes')['data-odf-row-source-repeat']);
        $t->same('1', $rows[1]->attr('htmlAttributes')['data-odf-row-repeat-index']);
        $t->same('true', $rows[1]->attr('htmlAttributes')['data-odf-row-hidden']);
        $t->same(2, $rows[2]->attr('repeatIndex'));
        $t->same(2, $rows[2]->attr('sourceRepeat'));
        $t->same('ReviewSummaryRow', $rows[3]->attr('styleName'));
        $t->same(4, $result['importReport']['content']['tableRowDefinitionCount']);
        $t->same(2, $result['importReport']['content']['hiddenTableRowCount']);
        $t->same(2, $result['importReport']['content']['repeatedTableRowCount']);
        $t->same(0, $result['importReport']['content']['truncatedTableRowRepeatCount']);

        $geometryRows = $table->attr('tableGeometry')['sections'][0]['rows'];
        $t->same('ReviewHeaderRow', $geometryRows[0]['sourceAttributes']['htmlAttributes']['data-odf-row-style-name']);
        $t->same('HiddenReviewRow', $geometryRows[1]['sourceAttributes']['htmlAttributes']['data-odf-row-style-name']);
        $t->same('true', $geometryRows[1]['sourceAttributes']['htmlAttributes']['data-odf-row-hidden']);
        $t->same('2', $geometryRows[2]['sourceAttributes']['htmlAttributes']['data-odf-row-repeat-index']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<tr class="odf-hidden-table-row odf-repeated-table-row" data-odf-row-style-name="HiddenReviewRow" data-odf-row-default-cell-style-name="HiddenReviewCell" data-odf-row-visibility="collapse" data-odf-row-hidden="true" data-odf-row-repeat-index="1" data-odf-row-source-repeat="2" data-odf-row-declared-repeat="2">', $blocksHtml);
        $t->contains('<tr class="odf-hidden-table-row odf-repeated-table-row" data-odf-row-style-name="HiddenReviewRow" data-odf-row-default-cell-style-name="HiddenReviewCell" data-odf-row-visibility="collapse" data-odf-row-hidden="true" data-odf-row-repeat-index="2" data-odf-row-source-repeat="2" data-odf-row-declared-repeat="2">', $blocksHtml);
    },
    'maps ODT text-position styles into superscript and subscript spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithVerticalText = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <style:style style:name="SourceSuperscript" style:family="text">
      <style:text-properties style:text-position="super 58%"/>
    </style:style>
    <style:style style:name="InheritedSuperscript" style:family="text" style:parent-style-name="SourceSuperscript"/>
    <style:style style:name="SourceSubscript" style:family="text">
      <style:text-properties style:text-position="sub 58%"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithVerticalText = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Reviewed mark<text:span text:style-name="InheritedSuperscript">TM</text:span> and H<text:span text:style-name="SourceSubscript">2</text:span>O survive.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithVerticalText, null, $stylesWithVerticalText));
        $paragraph = $result['document']->children[0];
        $superscript = $paragraph->children[1];
        $subscript = $paragraph->children[3];

        $t->same('Reviewed markTM and H2O survive.', $paragraph->attr('text'));
        $t->same('SourceSuperscript', $result['styles']['InheritedSuperscript']['parentName']);
        $t->same(true, $result['styles']['SourceSuperscript']['textProperties']['superscript']);
        $t->same(true, $result['styles']['SourceSubscript']['textProperties']['subscript']);
        $t->same('superscript', $superscript->type);
        $t->same('span', $superscript->children[0]->type);
        $t->same('InheritedSuperscript', $superscript->children[0]->attr('styleName'));
        $t->same('TM', $superscript->children[0]->children[0]->attr('text'));
        $t->same('subscript', $subscript->type);
        $t->same('span', $subscript->children[0]->type);
        $t->same('SourceSubscript', $subscript->children[0]->attr('styleName'));
        $t->same('2', $subscript->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Reviewed mark^[TM]{data-odf-style-name="InheritedSuperscript"}^ and H~[2]{data-odf-style-name="SourceSubscript"}~O survive.', $markdown);
        $t->contains('<sup><span data-odf-style-name="InheritedSuperscript">TM</span></sup>', $blocksHtml);
        $t->contains('<sub><span data-odf-style-name="SourceSubscript">2</span></sub>', $blocksHtml);
    },
    'maps ODT numeric bold small-caps and strikeout text styles like upstream style diffs' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithReviewMarks = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="NumericBoldSmallCaps" style:family="text">
      <style:text-properties fo:font-weight="500" fo:font-variant="small-caps"/>
    </style:style>
    <style:style style:name="DraftStrike" style:family="text">
      <style:text-properties style:text-line-through-style="solid"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithReviewMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Review <text:span text:style-name="NumericBoldSmallCaps">Source Title</text:span> and <text:span text:style-name="DraftStrike">draft copy</text:span>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithReviewMarks, null, $stylesWithReviewMarks));
        $paragraph = $result['document']->children[0];
        $smallCaps = $paragraph->children[1];
        $strikeout = $paragraph->children[3];

        $t->same('Review Source Title and draft copy.', $paragraph->attr('text'));
        $t->same(true, $result['styles']['NumericBoldSmallCaps']['textProperties']['bold']);
        $t->same(true, $result['styles']['NumericBoldSmallCaps']['textProperties']['smallCaps']);
        $t->same(true, $result['styles']['DraftStrike']['textProperties']['strikeout']);
        $t->same('small_caps', $smallCaps->type);
        $t->same('strong', $smallCaps->children[0]->type);
        $t->same('span', $smallCaps->children[0]->children[0]->type);
        $t->same('NumericBoldSmallCaps', $smallCaps->children[0]->children[0]->attr('styleName'));
        $t->same('Source Title', $smallCaps->children[0]->children[0]->children[0]->attr('text'));
        $t->same('strikeout', $strikeout->type);
        $t->same('span', $strikeout->children[0]->type);
        $t->same('DraftStrike', $strikeout->children[0]->attr('styleName'));
        $t->same('draft copy', $strikeout->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[**[Source Title]{data-odf-style-name="NumericBoldSmallCaps"}**]{.smallcaps}', $markdown);
        $t->contains('~~[draft copy]{data-odf-style-name="DraftStrike"}~~', $markdown);
        $t->contains('<span style="font-variant:small-caps"><strong><span data-odf-style-name="NumericBoldSmallCaps">Source Title</span></strong></span>', $blocksHtml);
        $t->contains('<del><span data-odf-style-name="DraftStrike">draft copy</span></del>', $blocksHtml);
    },
    'maps ODT font face pitch declarations into style review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithFontFaces = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:font-face-decls>
    <style:font-face style:name="LiberationMono" svg:font-family="'Liberation Mono'" style:font-family-generic="modern" style:font-pitch="fixed"/>
    <style:font-face style:name="SourceSerif" svg:font-family="'Source Serif 4'" style:font-family-generic="roman" style:font-pitch="variable"/>
  </office:font-face-decls>
  <office:styles>
    <style:style style:name="FixedPitchSource" style:family="text">
      <style:text-properties style:font-name="LiberationMono"/>
    </style:style>
    <style:style style:name="DirectPitchSource" style:family="text">
      <style:text-properties style:font-name="SourceSerif" style:font-pitch="fixed"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithAutomaticFontPitch = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:automatic-styles>
    <style:style style:name="AutomaticFixedPitchSource" style:family="text">
      <style:text-properties style:font-name="LiberationMono"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:p>Review <text:span text:style-name="FixedPitchSource">wp_cli</text:span>, <text:span text:style-name="DirectPitchSource">shortcode</text:span>, and <text:span text:style-name="AutomaticFixedPitchSource">auto style</text:span>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithAutomaticFontPitch, null, $stylesWithFontFaces));
        $paragraph = $result['document']->children[0];

        $t->same('Review wp_cli, shortcode, and auto style.', $paragraph->attr('text'));
        $t->same(2, $result['document']->attr('fontFaces')['count']);
        $t->same(2, $result['importReport']['styles']['fontFaceCount']);
        $t->same('fixed', $result['fontFaces']['LiberationMono']['fontPitch']);
        $t->same('modern', $result['fontFaces']['LiberationMono']['fontFamilyGeneric']);
        $t->same("'Liberation Mono'", $result['fontFaces']['LiberationMono']['fontFamily']);
        $t->same('variable', $result['fontFaces']['SourceSerif']['fontPitch']);

        $fixedProperties = $result['styles']['FixedPitchSource']['textProperties'];
        $directProperties = $result['styles']['DirectPitchSource']['textProperties'];
        $automaticProperties = $result['styles']['AutomaticFixedPitchSource']['textProperties'];
        $t->same('LiberationMono', $fixedProperties['fontName']);
        $t->same('fixed', $fixedProperties['fontPitch']);
        $t->same(true, $fixedProperties['fixedPitch']);
        $t->same("'Liberation Mono'", $fixedProperties['fontFace']['fontFamily']);
        $t->same('SourceSerif', $directProperties['fontName']);
        $t->same('fixed', $directProperties['fontPitch']);
        $t->same('variable', $directProperties['fontFace']['fontPitch']);
        $t->same('fixed', $automaticProperties['fontPitch']);

        $fixedSpan = $paragraph->children[1];
        $directSpan = $paragraph->children[3];
        $automaticSpan = $paragraph->children[5];
        $t->same('span', $fixedSpan->type);
        $t->same('FixedPitchSource', $fixedSpan->attr('styleName'));
        $t->same('wp_cli', $fixedSpan->children[0]->attr('text'));
        $t->same('DirectPitchSource', $directSpan->attr('styleName'));
        $t->same('AutomaticFixedPitchSource', $automaticSpan->attr('styleName'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[wp_cli]{data-odf-style-name="FixedPitchSource"}', $markdown);
        $t->contains('[auto style]{data-odf-style-name="AutomaticFixedPitchSource"}', $markdown);
        $t->contains('<span data-odf-style-name="FixedPitchSource">wp_cli</span>', $blocksHtml);
        $t->contains('<span data-odf-style-name="AutomaticFixedPitchSource">auto style</span>', $blocksHtml);
    },
    'maps ODT ruby annotations into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithRuby = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Localized <text:ruby text:style-name="SourceRuby"><text:ruby-base>漢字</text:ruby-base><text:ruby-text text:style-name="RubyText">kanji</text:ruby-text></text:ruby> label and <text:ruby><text:ruby-base><text:span>東京</text:span></text:ruby-base><text:ruby-text>Tokyo</text:ruby-text></text:ruby> note.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithRuby));
        $paragraph = $result['document']->children[0];
        $firstRuby = $paragraph->children[1];
        $secondRuby = $paragraph->children[3];

        $t->same('Localized 漢字 label and 東京 note.', $paragraph->attr('text'));
        $t->same('span', $firstRuby->type);
        $t->same(['odf-ruby'], $firstRuby->attr('classes'));
        $t->same('kanji', $firstRuby->attr('rubyText'));
        $t->same('SourceRuby', $firstRuby->attr('rubyStyleName'));
        $t->same('RubyText', $firstRuby->attr('rubyTextStyleName'));
        $t->same('kanji', $firstRuby->attr('attributes')['data-odf-ruby-text']);
        $t->same('SourceRuby', $firstRuby->attr('attributes')['data-odf-ruby-style-name']);
        $t->same('RubyText', $firstRuby->attr('attributes')['data-odf-ruby-text-style-name']);
        $t->same('漢字', $firstRuby->children[0]->attr('text'));

        $t->same('span', $secondRuby->type);
        $t->same('Tokyo', $secondRuby->attr('rubyText'));
        $t->same('東京', $secondRuby->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['rubyCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[漢字]{.odf-ruby data-odf-ruby-text="kanji" data-odf-ruby-style-name="SourceRuby" data-odf-ruby-text-style-name="RubyText"}', $markdown);
        $t->contains('[東京]{.odf-ruby data-odf-ruby-text="Tokyo"}', $markdown);
        $t->contains('<span class="odf-ruby" data-odf-ruby-text="kanji" data-odf-ruby-style-name="SourceRuby" data-odf-ruby-text-style-name="RubyText">漢字</span>', $blocksHtml);
        $t->contains('<span class="odf-ruby" data-odf-ruby-text="Tokyo">東京</span>', $blocksHtml);
    },
    'continues ODT ordered list numbering across sibling lists by level' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithContinuationLists = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="ContinuationSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="2"/>
      <text:list-level-style-number text:level="2" style:num-format="a" text:start-value="4"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithContinuationLists = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="ContinuationSteps">
        <text:list-item>
          <text:p>First review item</text:p>
          <text:list text:style-name="ContinuationSteps">
            <text:list-item><text:p>Nested legal note</text:p></text:list-item>
          </text:list>
        </text:list-item>
        <text:list-item><text:p>Second review item</text:p></text:list-item>
      </text:list>
      <text:p>Interruption paragraph.</text:p>
      <text:list text:style-name="ContinuationSteps" text:continue-numbering="true">
        <text:list-item><text:p>Third review item</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ContinuationSteps">
        <text:list-item><text:p>Reset review item</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithContinuationLists, null, $stylesWithContinuationLists));
        $blocks = $result['document']->children;

        $t->same(4, count($blocks));
        $firstList = $blocks[0];
        $paragraph = $blocks[1];
        $continuedList = $blocks[2];
        $resetList = $blocks[3];
        $nestedList = $firstList->children[0]->children[1];

        $t->same('ordered_list', $firstList->type);
        $t->same(2, $firstList->attr('start'));
        $t->same('decimal', $firstList->attr('style'));
        $t->same(1, $firstList->attr('listLevel'));
        $t->same('First review item', $firstList->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $nestedList->type);
        $t->same(4, $nestedList->attr('start'));
        $t->same('lower_alpha', $nestedList->attr('style'));
        $t->same(2, $nestedList->attr('listLevel'));
        $t->same('Nested legal note', $nestedList->children[0]->children[0]->attr('text'));
        $t->same('Interruption paragraph.', $paragraph->attr('text'));
        $t->same('ordered_list', $continuedList->type);
        $t->same(true, $continuedList->attr('continued'));
        $t->same(4, $continuedList->attr('start'));
        $t->same('Third review item', $continuedList->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $resetList->type);
        $t->same(2, $resetList->attr('start'));
        $t->same('Reset review item', $resetList->children[0]->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['continuedListCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('2.  First review item', $markdown);
        $t->contains('  d.  Nested legal note', $markdown);
        $t->contains('4.  Third review item', $markdown);
        $t->contains('2.  Reset review item', $markdown);
        $t->contains('<ol start="2">', $blocksHtml);
        $t->contains('<ol start="4" type="a">', $blocksHtml);
        $t->contains('<ol start="4">', $blocksHtml);
    },
    'continues ODT ordered lists from named source list ids' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithNamedContinuationLists = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="NamedReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithNamedContinuationLists = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:id="review-list-a" text:style-name="NamedReviewSteps">
        <text:list-item><text:p>First source step</text:p></text:list-item>
        <text:list-item><text:p>Second source step</text:p></text:list-item>
      </text:list>
      <text:list text:id="unrelated-list" text:style-name="NamedReviewSteps">
        <text:list-item><text:p>Unrelated inserted checklist</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="NamedReviewSteps" text:continue-list="review-list-a">
        <text:list-item><text:p>Third source step</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNamedContinuationLists, null, $stylesWithNamedContinuationLists));
        $blocks = $result['document']->children;
        $sourceList = $blocks[0];
        $unrelatedList = $blocks[1];
        $continuedList = $blocks[2];

        $t->same('ordered_list', $sourceList->type);
        $t->same('review-list-a', $sourceList->attr('listId'));
        $t->same('text:id', $sourceList->attr('listIdAttribute'));
        $t->same('review-list-a', $sourceList->attr('htmlAttributes')['data-odf-list-id']);
        $t->same(1, $sourceList->attr('start'));
        $t->same('First source step', $sourceList->children[0]->children[0]->attr('text'));
        $t->same('Second source step', $sourceList->children[1]->children[0]->attr('text'));

        $t->same('ordered_list', $unrelatedList->type);
        $t->same('unrelated-list', $unrelatedList->attr('listId'));
        $t->same(1, $unrelatedList->attr('start'));
        $t->same('Unrelated inserted checklist', $unrelatedList->children[0]->children[0]->attr('text'));

        $t->same('ordered_list', $continuedList->type);
        $t->same(true, $continuedList->attr('continued'));
        $t->same('review-list-a', $continuedList->attr('continueList'));
        $t->same(3, $continuedList->attr('start'));
        $t->same('continue-list', $continuedList->attr('startSource'));
        $t->same('review-list-a', $continuedList->attr('htmlAttributes')['data-odf-list-continue-list']);
        $t->same('true', $continuedList->attr('htmlAttributes')['data-odf-list-continued']);
        $t->same('Third source step', $continuedList->children[0]->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['continuedListCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($result['document']);
        $t->contains('3.  Third source step', $markdown);
        $t->contains('<ol data-odf-list-id="review-list-a" data-odf-list-id-attribute="text:id"><li>First source step</li><li>Second source step</li></ol>', $blocksHtml);
        $t->contains('<ol data-odf-list-id="unrelated-list" data-odf-list-id-attribute="text:id"><li>Unrelated inserted checklist</li></ol>', $blocksHtml);
        $t->contains('<ol start="3" data-odf-list-continue-list="review-list-a" data-odf-list-continued="true"><li>Third source step</li></ol>', $blocksHtml);
    },
    'preserves ODT image list style metadata for WordPress review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $stylesWithImageList = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <text:list-style style:name="GraphicReviewBullets">
      <text:list-level-style-image text:level="1" xlink:href="Pictures/review-bullet.svg" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" xlink:title="Review badge" svg:width="0.18in" svg:height="0.18in">
        <style:list-level-properties text:min-label-width="0.28in" text:space-before="0.05in" text:list-level-position-and-space-mode="label-alignment">
          <style:list-level-label-alignment text:label-followed-by="listtab" text:list-tab-stop-position="0.35in" fo:text-indent="-0.2in" fo:margin-left="0.45in"/>
        </style:list-level-properties>
      </text:list-level-style-image>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithImageList = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="GraphicReviewBullets">
        <text:list-item><text:p>Review packet with graphic bullet</text:p></text:list-item>
        <text:list-item><text:p>Second graphic bullet item</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $manifestWithImageList = str_replace(
            '</manifest:manifest>',
            '<manifest:file-entry manifest:full-path="Pictures/review-bullet.svg" manifest:media-type="image/svg+xml"/></manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithImageList, $manifestWithImageList, $stylesWithImageList, null, [
            ['name' => 'Pictures/review-bullet.svg', 'data' => '<svg/>', 'compressionMethod' => 0],
        ]));
        $levels = $result['listStyles']['GraphicReviewBullets']['levels'];
        $level = $levels[1];
        $list = $result['document']->children[0];
        $imageMetadata = $list->attr('listImageMetadata');
        $levelProperties = $list->attr('listLevelProperties');
        $htmlAttributes = $list->attr('htmlAttributes');

        $t->same('image', $level['type']);
        $t->same('Pictures/review-bullet.svg', $level['image']['href']);
        $t->same('simple', $level['image']['type']);
        $t->same('embed', $level['image']['show']);
        $t->same('onLoad', $level['image']['actuate']);
        $t->same('Review badge', $level['image']['title']);
        $t->same('0.18in', $level['image']['width']);
        $t->same('0.18in', $level['image']['height']);
        $t->same('0.28in', $level['levelProperties']['minLabelWidth']);
        $t->same('0.05in', $level['levelProperties']['spaceBefore']);
        $t->same('label-alignment', $level['levelProperties']['positionAndSpaceMode']);
        $t->same('listtab', $level['levelProperties']['labelAlignment']['labelFollowedBy']);
        $t->same('0.35in', $level['levelProperties']['labelAlignment']['listTabStopPosition']);
        $t->same('-0.2in', $level['levelProperties']['labelAlignment']['textIndent']);
        $t->same('0.45in', $level['levelProperties']['labelAlignment']['marginLeft']);

        $t->same('bullet_list', $list->type);
        $t->same('image', $list->attr('format'));
        $t->same(true, $list->attr('listImageStyle'));
        $t->same('Pictures/review-bullet.svg', $imageMetadata['href']);
        $t->same('Review badge', $imageMetadata['title']);
        $t->same('0.28in', $levelProperties['minLabelWidth']);
        $t->same('listtab', $levelProperties['labelAlignment']['labelFollowedBy']);
        $t->same('Pictures/review-bullet.svg', $htmlAttributes['data-odf-list-image-href']);
        $t->same('Review badge', $htmlAttributes['data-odf-list-image-title']);
        $t->same('0.18in', $htmlAttributes['data-odf-list-image-width']);
        $t->same('0.18in', $htmlAttributes['data-odf-list-image-height']);
        $t->same('0.28in', $htmlAttributes['data-odf-list-level-min-label-width']);
        $t->same('listtab', $htmlAttributes['data-odf-list-label-label-followed-by']);
        $t->same('0.35in', $htmlAttributes['data-odf-list-label-list-tab-stop-position']);
        $t->same(1, $result['importReport']['content']['imageListStyleCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($result['document']);
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $t->contains('- Review packet with graphic bullet', $markdown);
        $t->contains('<ul data-odf-list-image-style="true" data-odf-list-image-href="Pictures/review-bullet.svg" data-odf-list-image-type="simple" data-odf-list-image-show="embed" data-odf-list-image-actuate="onLoad" data-odf-list-image-title="Review badge" data-odf-list-image-width="0.18in" data-odf-list-image-height="0.18in"', $blocksHtml);
        $t->contains('data-odf-list-level-min-label-width="0.28in"', $blocksHtml);
        $t->contains('data-odf-list-label-label-followed-by="listtab"', $blocksHtml);
        $t->contains('<li>Second graphic bullet item</li>', $blocksHtml);
        $t->same('image/svg+xml', $manifestByPath['Pictures/review-bullet.svg']['mediaType']);
        $t->same(true, $manifestByPath['Pictures/review-bullet.svg']['exists']);
    },
    'preserves ODT list level text properties for WordPress marker review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithListTextProperties = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:font-face-decls>
    <style:font-face style:name="ListMono" svg:font-family="'List Mono'" style:font-family-generic="modern" style:font-pitch="fixed"/>
  </office:font-face-decls>
  <office:styles>
    <text:list-style style:name="StyledReviewMarkers">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1">
        <style:text-properties fo:font-weight="bold" fo:font-style="italic" fo:font-variant="small-caps" style:font-name="ListMono"/>
      </text:list-level-style-number>
      <text:list-level-style-bullet text:level="2" text:bullet-char="-">
        <style:text-properties style:text-underline-style="solid" style:text-line-through-style="solid"/>
      </text:list-level-style-bullet>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithListTextProperties = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="StyledReviewMarkers">
        <text:list-item>
          <text:p>Styled marker source item</text:p>
          <text:list>
            <text:list-item><text:p>Nested marker style metadata</text:p></text:list-item>
          </text:list>
        </text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithListTextProperties, null, $stylesWithListTextProperties));
        $levels = $result['listStyles']['StyledReviewMarkers']['levels'];
        $outerList = $result['document']->children[0];
        $innerList = $outerList->children[0]->children[1];
        $outerProperties = $outerList->attr('listTextProperties');
        $innerProperties = $innerList->attr('listTextProperties');
        $outerAttributes = $outerList->attr('htmlAttributes');
        $innerAttributes = $innerList->attr('htmlAttributes');

        $t->same('number', $levels[1]['type']);
        $t->same('ListMono', $levels[1]['textProperties']['fontName']);
        $t->same("'List Mono'", $levels[1]['textProperties']['fontFace']['fontFamily']);
        $t->same('modern', $levels[1]['textProperties']['fontFace']['fontFamilyGeneric']);
        $t->same('fixed', $levels[1]['textProperties']['fontPitch']);
        $t->same(true, $levels[1]['textProperties']['fixedPitch']);
        $t->same(true, $levels[1]['textProperties']['bold']);
        $t->same(true, $levels[1]['textProperties']['italic']);
        $t->same(true, $levels[1]['textProperties']['smallCaps']);
        $t->same('bullet', $levels[2]['type']);
        $t->same(true, $levels[2]['textProperties']['underline']);
        $t->same(true, $levels[2]['textProperties']['strikeout']);

        $t->same('ordered_list', $outerList->type);
        $t->same('ListMono', $outerProperties['fontName']);
        $t->same(true, $outerProperties['fixedPitch']);
        $t->same(true, $outerProperties['bold']);
        $t->same(true, $outerProperties['italic']);
        $t->same(true, $outerProperties['smallCaps']);
        $t->same('7', $outerAttributes['data-odf-list-text-property-count']);
        $t->same('ListMono', $outerAttributes['data-odf-list-text-font-name']);
        $t->same("'List Mono'", $outerAttributes['data-odf-list-text-font-face-font-family']);
        $t->same('modern', $outerAttributes['data-odf-list-text-font-face-font-family-generic']);
        $t->same('fixed', $outerAttributes['data-odf-list-text-font-pitch']);
        $t->same('true', $outerAttributes['data-odf-list-text-fixed-pitch']);
        $t->same('true', $outerAttributes['data-odf-list-text-bold']);
        $t->same('true', $outerAttributes['data-odf-list-text-italic']);
        $t->same('true', $outerAttributes['data-odf-list-text-small-caps']);

        $t->same('bullet_list', $innerList->type);
        $t->same(true, $innerProperties['underline']);
        $t->same(true, $innerProperties['strikeout']);
        $t->same('2', $innerAttributes['data-odf-list-text-property-count']);
        $t->same('true', $innerAttributes['data-odf-list-text-underline']);
        $t->same('true', $innerAttributes['data-odf-list-text-strikeout']);
        $t->same(2, $result['importReport']['content']['listTextPropertyCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($result['document']);
        $t->contains('1.  Styled marker source item', $markdown);
        $t->contains('- Nested marker style metadata', $markdown);
        $t->contains('<ol data-odf-list-text-property-count="7" data-odf-list-text-font-name="ListMono" data-odf-list-text-font-face-name="ListMono"', $blocksHtml);
        $t->contains('data-odf-list-text-font-face-font-family="&#039;List Mono&#039;"', $blocksHtml);
        $t->contains('data-odf-list-text-bold="true" data-odf-list-text-italic="true" data-odf-list-text-small-caps="true"', $blocksHtml);
        $t->contains('<ul data-odf-list-text-property-count="2" data-odf-list-text-underline="true" data-odf-list-text-strikeout="true"><li>Nested marker style metadata</li></ul>', $blocksHtml);
    },
    'honors explicit ODT list start values before continued numbering' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithExplicitStartLists = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="ExplicitStartSteps">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="2"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithExplicitStartLists = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="ExplicitStartSteps" text:start-value="7">
        <text:list-item><text:p>Explicit source item</text:p></text:list-item>
        <text:list-item><text:p>Second explicit source item</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ExplicitStartSteps" text:continue-numbering="true">
        <text:list-item><text:p>Continued after explicit source start</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ExplicitStartSteps">
        <text:list-item><text:p>Reset to style start</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithExplicitStartLists, null, $stylesWithExplicitStartLists));
        $blocks = $result['document']->children;
        $explicitList = $blocks[0];
        $continuedList = $blocks[1];
        $resetList = $blocks[2];

        $t->same('ordered_list', $explicitList->type);
        $t->same(7, $explicitList->attr('start'));
        $t->same(7, $explicitList->attr('explicitStartValue'));
        $t->same('list-start-value', $explicitList->attr('startSource'));
        $t->same(2, $explicitList->attr('styleStart'));
        $t->same('lower_alpha', $explicitList->attr('style'));
        $t->same('Explicit source item', $explicitList->children[0]->children[0]->attr('text'));
        $t->same('Second explicit source item', $explicitList->children[1]->children[0]->attr('text'));
        $t->same('ordered_list', $continuedList->type);
        $t->same(true, $continuedList->attr('continued'));
        $t->same(9, $continuedList->attr('start'));
        $t->same('Continued after explicit source start', $continuedList->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $resetList->type);
        $t->same(2, $resetList->attr('start'));
        $t->same('style-start-value', $resetList->attr('startSource'));
        $t->same('Reset to style start', $resetList->children[0]->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['continuedListCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('g.  Explicit source item', $markdown);
        $t->contains('h.  Second explicit source item', $markdown);
        $t->contains('i.  Continued after explicit source start', $markdown);
        $t->contains('b.  Reset to style start', $markdown);
        $t->contains('<ol start="7" type="a"><li>Explicit source item</li><li>Second explicit source item</li></ol>', $blocksHtml);
        $t->contains('<ol start="9" type="a"><li>Continued after explicit source start</li></ol>', $blocksHtml);
        $t->contains('<ol start="2" type="a"><li>Reset to style start</li></ol>', $blocksHtml);
    },
    'inherits parent ODT list style for styleless nested lists' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithInheritedList = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="2"/>
      <text:list-level-style-number text:level="2" style:num-format="a" text:start-value="4"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithStylelessNestedList = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="ReviewSteps">
        <text:list-item>
          <text:p>Top-level review item</text:p>
          <text:list>
            <text:list-item><text:p>Inherited nested review item</text:p></text:list-item>
          </text:list>
        </text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithStylelessNestedList, null, $stylesWithInheritedList));
        $outer = $result['document']->children[0];
        $inner = $outer->children[0]->children[1];

        $t->same('ordered_list', $outer->type);
        $t->same('ReviewSteps', $outer->attr('styleName'));
        $t->same(2, $outer->attr('start'));
        $t->same('ordered_list', $inner->type);
        $t->same('ReviewSteps', $inner->attr('inheritedStyleName'));
        $t->same(4, $inner->attr('start'));
        $t->same('lower_alpha', $inner->attr('style'));
        $t->same(2, $inner->attr('listLevel'));
        $t->same('Inherited nested review item', $inner->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('2.  Top-level review item', $markdown);
        $t->contains('  d.  Inherited nested review item', $markdown);
        $t->contains('<ol start="2">', $blocksHtml);
        $t->contains('<ol start="4" type="a">', $blocksHtml);
    },
    'falls back to nearest lower ODT list level style when exact level is missing' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithSparseListLevels = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="SparseReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1"/>
      <text:list-level-style-number text:level="3" style:num-format="i" style:num-suffix=")" text:start-value="7"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithSparseNestedLists = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="SparseReviewSteps">
        <text:list-item>
          <text:p>Top sparse review item</text:p>
          <text:list>
            <text:list-item>
              <text:p>Missing level two fallback item</text:p>
              <text:list>
                <text:list-item>
                  <text:p>Exact sparse level item</text:p>
                  <text:list>
                    <text:list-item><text:p>Deep sparse fallback item</text:p></text:list-item>
                  </text:list>
                </text:list-item>
              </text:list>
            </text:list-item>
          </text:list>
        </text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSparseNestedLists, null, $stylesWithSparseListLevels));
        $outer = $result['document']->children[0];
        $level2 = $outer->children[0]->children[1];
        $level3 = $level2->children[0]->children[1];
        $level4 = $level3->children[0]->children[1];

        $t->same('ordered_list', $outer->type);
        $t->same('SparseReviewSteps', $outer->attr('styleName'));
        $t->same(1, $outer->attr('start'));
        $t->same('decimal', $outer->attr('style'));
        $t->same(1, $outer->attr('listLevel'));
        $t->same('Top sparse review item', $outer->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $level2->type);
        $t->same('SparseReviewSteps', $level2->attr('inheritedStyleName'));
        $t->same(1, $level2->attr('start'));
        $t->same('decimal', $level2->attr('style'));
        $t->same(2, $level2->attr('listLevel'));
        $t->same('Missing level two fallback item', $level2->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $level3->type);
        $t->same(7, $level3->attr('start'));
        $t->same('lower_roman', $level3->attr('style'));
        $t->same('one_paren', $level3->attr('delimiter'));
        $t->same(')', $level3->attr('numberSuffix'));
        $t->same(3, $level3->attr('listLevel'));
        $t->same('Exact sparse level item', $level3->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $level4->type);
        $t->same(7, $level4->attr('start'));
        $t->same('lower_roman', $level4->attr('style'));
        $t->same('one_paren', $level4->attr('delimiter'));
        $t->same(')', $level4->attr('numberSuffix'));
        $t->same(4, $level4->attr('listLevel'));
        $t->same('Deep sparse fallback item', $level4->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('1.  Top sparse review item', $markdown);
        $t->contains('1.  Missing level two fallback item', $markdown);
        $t->contains('vii) Exact sparse level item', $markdown);
        $t->contains('vii) Deep sparse fallback item', $markdown);
        $t->contains('<ol start="7" type="i">', $blocksHtml);
        $t->contains('<li>Deep sparse fallback item</li>', $blocksHtml);
    },
    'maps ODT list number prefix and suffix delimiters like upstream list styles' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithDelimitedList = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="DelimitedReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" style:num-prefix="(" style:num-suffix=")" text:start-value="2"/>
      <text:list-level-style-number text:level="2" style:num-format="A" style:num-suffix=")" text:start-value="3"/>
      <text:list-level-style-number text:level="3" style:num-format="i" style:num-suffix="." text:start-value="4"/>
      <text:list-level-style-number text:level="4" style:num-format="1" style:num-prefix="[" style:num-suffix="]" text:start-value="5"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithDelimitedList = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="DelimitedReviewSteps">
        <text:list-item>
          <text:p>Top-level review item</text:p>
          <text:list>
            <text:list-item>
              <text:p>Upper alpha nested item</text:p>
              <text:list>
                <text:list-item>
                  <text:p>Roman period nested item</text:p>
                  <text:list>
                    <text:list-item><text:p>Default delimiter nested item</text:p></text:list-item>
                  </text:list>
                </text:list-item>
              </text:list>
            </text:list-item>
          </text:list>
        </text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDelimitedList, null, $stylesWithDelimitedList));
        $levels = $result['listStyles']['DelimitedReviewSteps']['levels'];
        $outer = $result['document']->children[0];
        $level2 = $outer->children[0]->children[1];
        $level3 = $level2->children[0]->children[1];
        $level4 = $level3->children[0]->children[1];

        $t->same('(', $levels[1]['numPrefix']);
        $t->same(')', $levels[1]['numSuffix']);
        $t->same('', $levels[2]['numPrefix']);
        $t->same(')', $levels[2]['numSuffix']);
        $t->same('', $levels[3]['numPrefix']);
        $t->same('.', $levels[3]['numSuffix']);
        $t->same('[', $levels[4]['numPrefix']);
        $t->same(']', $levels[4]['numSuffix']);

        $t->same('ordered_list', $outer->type);
        $t->same(2, $outer->attr('start'));
        $t->same('decimal', $outer->attr('style'));
        $t->same('two_parens', $outer->attr('delimiter'));
        $t->same('(', $outer->attr('numberPrefix'));
        $t->same(')', $outer->attr('numberSuffix'));
        $t->same('ordered_list', $level2->type);
        $t->same(3, $level2->attr('start'));
        $t->same('upper_alpha', $level2->attr('style'));
        $t->same('one_paren', $level2->attr('delimiter'));
        $t->same(')', $level2->attr('numberSuffix'));
        $t->same('ordered_list', $level3->type);
        $t->same(4, $level3->attr('start'));
        $t->same('lower_roman', $level3->attr('style'));
        $t->same('period', $level3->attr('delimiter'));
        $t->same('.', $level3->attr('numberSuffix'));
        $t->same('ordered_list', $level4->type);
        $t->same(5, $level4->attr('start'));
        $t->same('default', $level4->attr('delimiter'));
        $t->same('[', $level4->attr('numberPrefix'));
        $t->same(']', $level4->attr('numberSuffix'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('(2) Top-level review item', $markdown);
        $t->contains('C)  Upper alpha nested item', $markdown);
        $t->contains('iv. Roman period nested item', $markdown);
        $t->contains('5.  Default delimiter nested item', $markdown);
        $t->contains('<ol start="2">', $blocksHtml);
        $t->contains('<ol start="3" type="A">', $blocksHtml);
        $t->contains('<ol start="4" type="i">', $blocksHtml);
        $t->contains('<ol start="5">', $blocksHtml);
    },
    'maps ODT list headers as unnumbered review content' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithListHeader = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="HeaderReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithListHeader = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="HeaderReviewSteps">
        <text:list-header><text:p>Review scope introduction</text:p></text:list-header>
        <text:list-item><text:p>First numbered item</text:p></text:list-item>
        <text:list-item><text:p>Second numbered item</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithListHeader, null, $stylesWithListHeader));
        $list = $result['document']->children[0];
        $header = $list->children[0];
        $firstItem = $list->children[1];
        $secondItem = $list->children[2];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('lower_alpha', $list->attr('style'));
        $t->same(true, $header->attr('listHeader'));
        $t->same(['odf-list-header'], $header->attr('classes'));
        $t->same('true', $header->attr('attributes')['data-odf-list-header']);
        $t->same('1', $header->attr('attributes')['data-odf-list-level']);
        $t->same('Review scope introduction', $header->children[0]->attr('text'));
        $t->same('First numbered item', $firstItem->children[0]->attr('text'));
        $t->same('Second numbered item', $secondItem->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['listHeaderCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {.odf-list-header data-odf-list-header="true" data-odf-list-level="1"}', $markdown);
        $t->contains('Review scope introduction', $markdown);
        $t->contains('c.  First numbered item', $markdown);
        $t->contains('d.  Second numbered item', $markdown);
        $t->true(!str_contains($markdown, 'd.  First numbered item'), 'List header must not advance ordered Markdown numbering');
        $t->contains('<div class="odf-list-header" data-odf-list-header="true" data-odf-list-level="1"><p>Review scope introduction</p></div>', $blocksHtml);
        $t->contains('<ol start="3" type="a"><li>First numbered item</li><li>Second numbered item</li></ol>', $blocksHtml);
    },
    'maps ODT footnotes endnotes and bookmark references into reviewable AST nodes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNotesAndBookmarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Bookmark <text:bookmark-start text:name="Review Anchor"/>target<text:bookmark-end text:name="Review Anchor"/> and <text:bookmark-ref text:ref-name="Review Anchor" text:reference-format="text">jump back</text:bookmark-ref>.</text:p>
      <text:p>Footnote<text:note text:id="ftn1" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODF footnote body.</text:p></text:note-body></text:note> Endnote<text:note text:id="edn1" text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>ODF endnote body with <text:a xlink:href="https://example.test/review">review link</text:a>.</text:p></text:note-body></text:note></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNotesAndBookmarks));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $bookmarkParagraph = $blocks[0];
        $bookmark = $bookmarkParagraph->children[1];
        $reference = $bookmarkParagraph->children[3];
        $t->same('span', $bookmark->type);
        $t->same('review-anchor', $bookmark->attr('id'));
        $t->same(['anchor', 'odf-bookmark'], $bookmark->attr('classes'));
        $t->same('Review Anchor', $bookmark->attr('attributes')['data-odf-bookmark-name']);
        $t->same('link', $reference->type);
        $t->same('#review-anchor', $reference->attr('url'));
        $t->same(['odf-bookmark-ref'], $reference->attr('classes'));
        $t->same('Review Anchor', $reference->attr('attributes')['data-odf-ref-name']);
        $t->same('text', $reference->attr('attributes')['data-odf-reference-format']);
        $t->same('jump back', $reference->children[0]->attr('text'));

        $noteParagraph = $blocks[1];
        $footnote = $noteParagraph->children[1];
        $endnote = $noteParagraph->children[3];
        $t->same('note', $footnote->type);
        $t->same('footnote', $footnote->attr('noteClass'));
        $t->same('ftn1', $footnote->attr('id'));
        $t->same('1', $footnote->attr('citation'));
        $t->same('ODF footnote body.', $footnote->children[0]->attr('text'));
        $t->same('note', $endnote->type);
        $t->same('endnote', $endnote->attr('noteClass'));
        $t->same('edn1', $endnote->attr('id'));
        $t->same('i', $endnote->attr('citation'));
        $t->same('ODF endnote body with review link.', $endnote->children[0]->attr('text'));
        $t->same('link', $endnote->children[0]->children[1]->type);
        $t->same('https://example.test/review', $endnote->children[0]->children[1]->attr('url'));

        $t->same(2, $result['importReport']['content']['noteCount']);
        $t->same(1, $result['importReport']['content']['bookmarkCount']);
        $t->same(1, $result['importReport']['content']['bookmarkReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[]{#review-anchor .anchor .odf-bookmark data-odf-bookmark-name="Review Anchor"}', $markdown);
        $t->contains('[jump back](#review-anchor){.odf-bookmark-ref data-odf-ref-name="Review Anchor" data-odf-reference-format="text"}', $markdown);
        $t->contains('[^1]: ODF footnote body.', $markdown);
        $t->contains('[^2]: ODF endnote body with [review link](https://example.test/review).', $markdown);
        $t->contains('<span id="review-anchor" class="anchor odf-bookmark" data-odf-bookmark-name="Review Anchor" data-pandoc-anchor="empty-target"></span>', $blocksHtml);
        $t->contains('<a href="#review-anchor" class="odf-bookmark-ref" data-odf-ref-name="Review Anchor" data-odf-reference-format="text">jump back</a>', $blocksHtml);
        $t->contains('<li id="fn-1"><p>ODF footnote body.</p>', $blocksHtml);
        $t->contains('<li id="fn-2"><p>ODF endnote body with <a href="https://example.test/review">review link</a>.</p>', $blocksHtml);
    },
    'normalizes ODT note citations through inline text markers' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithInlineCitationMarkers = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Reviewer note<text:note text:id="fn-source" text:note-class="footnote"><text:note-citation>F<text:s text:c="2"/>7<text:tab/>b<text:line-break/>continued</text:note-citation><text:note-body><text:p>ODF source citation marker body.</text:p></text:note-body></text:note> keeps source numbering metadata.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithInlineCitationMarkers));
        $paragraph = $result['document']->children[0];
        $note = $paragraph->children[1];

        $t->same('note', $note->type);
        $t->same('fn-source', $note->attr('id'));
        $t->same("F  7 b\ncontinued", $note->attr('citation'));
        $t->same('ODF source citation marker body.', $note->children[0]->attr('text'));
        $t->same('Reviewer note keeps source numbering metadata.', $paragraph->attr('text'));
        $t->same(1, $result['importReport']['content']['noteCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[^1]: ODF source citation marker body.', $markdown);
        $t->contains('<li id="fn-1"><p>ODF source citation marker body.</p>', $blocksHtml);
    },
    'preserves ODT notes configuration metadata for footnote and endnote review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNoteConfigurations = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:notes-configuration
        text:note-class="footnote"
        text:citation-style-name="Footnote_20_Symbol"
        text:citation-body-style-name="Footnote_20_anchor"
        text:default-style-name="Footnote"
        text:start-value="4"
        style:num-format="a"
        style:num-prefix="["
        style:num-suffix="]"
        style:num-letter-sync="true"
        text:footnotes-position="page"
        text:start-numbering-at="chapter"
        text:note-continuation-notice-forward="continued on next page"
        text:note-continuation-notice-backward="continued from previous page"/>
      <text:notes-configuration
        text:note-class="endnote"
        text:citation-style-name="Endnote_20_Symbol"
        text:citation-body-style-name="Endnote_20_anchor"
        text:default-style-name="Endnote"
        text:master-page-name="Endnotes"
        text:start-value="2"
        style:num-format="i"
        style:num-suffix="."/>
      <text:p>Footnote<text:note text:id="ftn-config" text:note-class="footnote"><text:note-citation>d</text:note-citation><text:note-body><text:p>Configured footnote body.</text:p></text:note-body></text:note> Endnote<text:note text:id="edn-config" text:note-class="endnote"><text:note-citation>ii</text:note-citation><text:note-body><text:p>Configured endnote body.</text:p></text:note-body></text:note></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNoteConfigurations));
        $document = $result['document'];
        $declarations = $result['contentDeclarations'];
        $documentDeclarations = $document->attr('contentDeclarations');
        $paragraph = $document->children[0];
        $footnote = $paragraph->children[1];
        $endnote = $paragraph->children[3];
        $footnoteConfiguration = $footnote->attr('noteConfiguration');
        $endnoteConfiguration = $endnote->attr('noteConfiguration');

        $t->same(2, $declarations['noteConfigurationCount']);
        $t->same(2, count($declarations['noteConfigurations']));
        $t->same('footnote', $declarations['noteConfigurations'][0]['noteClass']);
        $t->same('endnote', $declarations['noteConfigurations'][1]['noteClass']);
        $t->same('Footnote_20_Symbol', $declarations['noteConfigurationsByClass']['footnote']['citationStyleName']);
        $t->same('Footnote_20_anchor', $declarations['noteConfigurationsByClass']['footnote']['citationBodyStyleName']);
        $t->same('Footnote', $declarations['noteConfigurationsByClass']['footnote']['defaultStyleName']);
        $t->same(4, $declarations['noteConfigurationsByClass']['footnote']['startValue']);
        $t->same('a', $declarations['noteConfigurationsByClass']['footnote']['numFormat']);
        $t->same('[', $declarations['noteConfigurationsByClass']['footnote']['numPrefix']);
        $t->same(']', $declarations['noteConfigurationsByClass']['footnote']['numSuffix']);
        $t->same(true, $declarations['noteConfigurationsByClass']['footnote']['numLetterSync']);
        $t->same('page', $declarations['noteConfigurationsByClass']['footnote']['footnotesPosition']);
        $t->same('chapter', $declarations['noteConfigurationsByClass']['footnote']['startNumberingAt']);
        $t->same('continued on next page', $declarations['noteConfigurationsByClass']['footnote']['noteContinuationNoticeForward']);
        $t->same('continued from previous page', $declarations['noteConfigurationsByClass']['footnote']['noteContinuationNoticeBackward']);
        $t->same('Endnote_20_Symbol', $declarations['noteConfigurationsByClass']['endnote']['citationStyleName']);
        $t->same('Endnotes', $declarations['noteConfigurationsByClass']['endnote']['masterPageName']);
        $t->same(2, $declarations['noteConfigurationsByClass']['endnote']['startValue']);
        $t->same('i', $declarations['noteConfigurationsByClass']['endnote']['numFormat']);
        $t->same('.', $declarations['noteConfigurationsByClass']['endnote']['numSuffix']);

        $t->same(2, $documentDeclarations['noteConfigurationCount']);
        $t->same(2, $result['importReport']['content']['noteConfigurationCount']);
        $t->same('chapter', $result['importReport']['contentDeclarations']['noteConfigurationsByClass']['footnote']['startNumberingAt']);
        $t->same('Endnotes', $result['importReport']['contentDeclarations']['noteConfigurationsByClass']['endnote']['masterPageName']);

        $t->same('note', $footnote->type);
        $t->same('footnote', $footnote->attr('noteClass'));
        $t->same('ftn-config', $footnote->attr('id'));
        $t->same('d', $footnote->attr('citation'));
        $t->same('Footnote_20_Symbol', $footnoteConfiguration['citationStyleName']);
        $t->same('page', $footnoteConfiguration['footnotesPosition']);
        $t->same('chapter', $footnoteConfiguration['startNumberingAt']);
        $t->same('note', $endnote->type);
        $t->same('endnote', $endnote->attr('noteClass'));
        $t->same('edn-config', $endnote->attr('id'));
        $t->same('ii', $endnote->attr('citation'));
        $t->same('Endnotes', $endnoteConfiguration['masterPageName']);
        $t->same('i', $endnoteConfiguration['numFormat']);

        $markdown = (new MarkdownWriter())->write($document);
        $blocksHtml = (new WordPressBlockWriter())->write($document);
        $t->contains('[^1]: Configured footnote body.', $markdown);
        $t->contains('[^2]: Configured endnote body.', $markdown);
        $t->contains('<li id="fn-1"><p>Configured footnote body.</p>', $blocksHtml);
        $t->contains('<li id="fn-2"><p>Configured endnote body.</p>', $blocksHtml);
    },
    'maps ODT footnote separator metadata into note configuration review data' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFootnoteSeparator = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:notes-configuration
        text:note-class="footnote"
        text:citation-style-name="Footnote_20_Symbol"
        text:default-style-name="Footnote"
        text:start-value="3"
        style:num-format="1"
        text:footnotes-position="page"
        text:start-numbering-at="document">
        <style:footnote-sep
          style:width="0.018cm"
          style:distance-before-sep="0.10cm"
          style:distance-after-sep="0.12cm"
          style:line-style="solid"
          style:adjustment="left"
          style:rel-width="25%"
          style:color="#808080"/>
      </text:notes-configuration>
      <text:p>Separated note<text:note text:id="ftn-separator" text:note-class="footnote"><text:note-citation>3</text:note-citation><text:note-body><text:p>Separator footnote body.</text:p></text:note-body></text:note></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFootnoteSeparator));
        $document = $result['document'];
        $declarations = $result['contentDeclarations'];
        $documentDeclarations = $document->attr('contentDeclarations');
        $paragraph = $document->children[0];
        $footnote = $paragraph->children[1];
        $footnoteConfiguration = $footnote->attr('noteConfiguration');
        $separator = $declarations['noteConfigurationsByClass']['footnote']['footnoteSeparator'];

        $t->same(1, $declarations['noteConfigurationCount']);
        $t->same(1, $declarations['noteConfigurationSeparatorCount']);
        $t->same('0.018cm', $separator['width']);
        $t->same('0.10cm', $separator['distanceBeforeSep']);
        $t->same('0.12cm', $separator['distanceAfterSep']);
        $t->same('solid', $separator['lineStyle']);
        $t->same('left', $separator['adjustment']);
        $t->same('25%', $separator['relWidth']);
        $t->same('#808080', $separator['color']);
        $t->same(1, $documentDeclarations['noteConfigurationSeparatorCount']);
        $t->same(1, $result['importReport']['content']['noteConfigurationSeparatorCount']);
        $t->same(1, $result['importReport']['contentDeclarations']['noteConfigurationSeparatorCount']);
        $t->same('25%', $result['importReport']['contentDeclarations']['noteConfigurationsByClass']['footnote']['footnoteSeparator']['relWidth']);
        $t->same('0.018cm', $footnoteConfiguration['footnoteSeparator']['width']);
        $t->same('25%', $footnoteConfiguration['footnoteSeparator']['relWidth']);

        $markdown = (new MarkdownWriter())->write($document);
        $blocksHtml = (new WordPressBlockWriter())->write($document);
        $t->contains('[^1]: Separator footnote body.', $markdown);
        $t->contains('<li id="fn-1"><p>Separator footnote body.</p>', $blocksHtml);
    },
    'maps ODT line numbering configuration into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLineNumbering = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:linenumbering-configuration
        text:number-lines="true"
        text:style-name="ReviewLineNumber"
        text:offset="0.5cm"
        text:number-position="left"
        text:increment="5"
        text:count-empty-lines="true"
        text:count-in-text-boxes="false"
        text:restart-on-page="true"
        style:num-format="1"
        style:num-letter-sync="true">
        <text:linenumbering-separator text:increment="3">|</text:linenumbering-separator>
      </text:linenumbering-configuration>
      <text:p>Line-numbered legal review source.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLineNumbering));
        $document = $result['document'];
        $declarations = $result['contentDeclarations'];
        $documentDeclarations = $document->attr('contentDeclarations');
        $configuration = $declarations['lineNumberingConfiguration'];
        $separator = $configuration['separator'];

        $t->same(1, $declarations['lineNumberingConfigurationCount']);
        $t->same(1, $declarations['lineNumberingSeparatorCount']);
        $t->same(true, $configuration['numberLines']);
        $t->same('ReviewLineNumber', $configuration['styleName']);
        $t->same('0.5cm', $configuration['offset']);
        $t->same('left', $configuration['numberPosition']);
        $t->same(5, $configuration['increment']);
        $t->same(true, $configuration['countEmptyLines']);
        $t->same(false, $configuration['countInTextBoxes']);
        $t->same(true, $configuration['restartOnPage']);
        $t->same('1', $configuration['numFormat']);
        $t->same(true, $configuration['numLetterSync']);
        $t->same(3, $separator['increment']);
        $t->same('|', $separator['text']);
        $t->same($declarations, $documentDeclarations);
        $t->same(1, $result['importReport']['contentDeclarations']['lineNumberingConfigurationCount']);
        $t->same(1, $result['importReport']['contentDeclarations']['lineNumberingSeparatorCount']);
        $t->same('ReviewLineNumber', $result['importReport']['contentDeclarations']['lineNumberingConfiguration']['styleName']);
        $t->same(1, $result['importReport']['content']['lineNumberingConfigurationCount']);
        $t->same(1, $result['importReport']['content']['lineNumberingSeparatorCount']);
        $t->same('Line-numbered legal review source.', $document->children[0]->attr('text'));

        $blocksHtml = (new WordPressBlockWriter())->write($document);
        $t->contains('<p>Line-numbered legal review source.</p>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'ReviewLineNumber'), 'Line numbering configuration must stay review metadata, not rendered prose');
    },
    'preserves ODT link metadata for Markdown and WordPress review output' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLinkMetadata = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Source <text:a xlink:href="https://example.test/source.odt#review" xlink:type="simple" xlink:show="new" xlink:actuate="onRequest" office:name="Source Link" office:title="Source ODT review" office:target-frame-name="_blank" text:style-name="SourceLink" text:visited-style-name="VisitedSourceLink">review link</text:a> remains auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLinkMetadata));
        $paragraph = $result['document']->children[0];
        $link = $paragraph->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Source review link remains auditable.', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same('https://example.test/source.odt#review', $link->attr('url'));
        $t->same('Source ODT review', $link->attr('title'));
        $t->same('review link', $link->children[0]->attr('text'));
        $t->same('odt', $link->attr('sourceFormat'));
        $t->same(['odf-link'], $link->attr('classes'));
        $t->same('Source Link', $link->attr('odfLinkMetadata')['name']);
        $t->same('SourceLink', $link->attr('odfLinkMetadata')['styleName']);
        $t->same('VisitedSourceLink', $link->attr('odfLinkMetadata')['visitedStyleName']);
        $t->same('_blank', $link->attr('odfLinkMetadata')['targetFrameName']);
        $t->same('simple', $link->attr('odfLinkMetadata')['type']);
        $t->same('new', $link->attr('odfLinkMetadata')['show']);
        $t->same('onRequest', $link->attr('odfLinkMetadata')['actuate']);
        $t->same('Source Link', $link->attr('attributes')['data-odf-link-name']);
        $t->same('SourceLink', $link->attr('attributes')['data-odf-link-style-name']);
        $t->same('VisitedSourceLink', $link->attr('attributes')['data-odf-link-visited-style-name']);
        $t->same('_blank', $link->attr('attributes')['data-odf-link-target-frame-name']);
        $t->same('simple', $link->attr('attributes')['data-odf-link-type']);
        $t->same('new', $link->attr('attributes')['data-odf-link-show']);
        $t->same('onRequest', $link->attr('attributes')['data-odf-link-actuate']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[review link](https://example.test/source.odt#review "Source ODT review"){.odf-link data-odf-link-name="Source Link" data-odf-link-style-name="SourceLink" data-odf-link-visited-style-name="VisitedSourceLink" data-odf-link-target-frame-name="_blank" data-odf-link-type="simple" data-odf-link-show="new" data-odf-link-actuate="onRequest"}', $markdown);
        $t->contains('<a href="https://example.test/source.odt#review" title="Source ODT review" class="odf-link" data-odf-link-name="Source Link" data-odf-link-style-name="SourceLink" data-odf-link-visited-style-name="VisitedSourceLink" data-odf-link-target-frame-name="_blank" data-odf-link-type="simple" data-odf-link-show="new" data-odf-link-actuate="onRequest">review link</a>', $blocksHtml);
    },
    'maps ODT link event listeners into inert review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLinkEvents = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Evented <text:a xlink:href="https://example.test/source.odt" xlink:type="simple" office:title="Evented source"><office:event-listeners><script:event-listener script:event-name="dom:mouseover" script:language="ooo:Basic" xlink:href="vnd.sun.star.script:Standard.Module.Hover?language=Basic&amp;location=document" xlink:type="simple" xlink:actuate="onRequest"/><script:event-listener script:event-name="dom:click" script:language="JavaScript" script:macro-name="ReviewLinkClick" xlink:href="Scripts/review-link.js" xlink:type="simple" xlink:show="replace"/></office:event-listeners>review link</text:a>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLinkEvents));
        $paragraph = $result['document']->children[0];
        $link = $paragraph->children[1];
        $metadata = $link->attr('odfLinkMetadata');
        $listeners = $metadata['eventListeners'] ?? [];

        $t->same('Evented review link.', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same(['odf-link'], $link->attr('classes'));
        $t->same(2, $metadata['eventListenerCount']);
        $t->same('dom:mouseover', $listeners[0]['eventName']);
        $t->same('ooo:Basic', $listeners[0]['language']);
        $t->same('vnd.sun.star.script:Standard.Module.Hover?language=Basic&location=document', $listeners[0]['href']);
        $t->same('simple', $listeners[0]['type']);
        $t->same('onRequest', $listeners[0]['actuate']);
        $t->same('dom:click', $listeners[1]['eventName']);
        $t->same('JavaScript', $listeners[1]['language']);
        $t->same('ReviewLinkClick', $listeners[1]['macroName']);
        $t->same('Scripts/review-link.js', $listeners[1]['href']);
        $t->same('replace', $listeners[1]['show']);
        $t->same('2', $link->attr('attributes')['data-odf-link-event-listener-count']);
        $t->same('dom:mouseover', $link->attr('attributes')['data-odf-link-event-1-name']);
        $t->same('ooo:Basic', $link->attr('attributes')['data-odf-link-event-1-language']);
        $t->same('vnd.sun.star.script:Standard.Module.Hover?language=Basic&location=document', $link->attr('attributes')['data-odf-link-event-1-href']);
        $t->same('onRequest', $link->attr('attributes')['data-odf-link-event-1-actuate']);
        $t->same('dom:click', $link->attr('attributes')['data-odf-link-event-2-name']);
        $t->same('ReviewLinkClick', $link->attr('attributes')['data-odf-link-event-2-macro-name']);
        $t->same(2, $result['importReport']['content']['eventListenerCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[review link](https://example.test/source.odt "Evented source"){.odf-link data-odf-link-type="simple" data-odf-link-event-listener-count="2" data-odf-link-event-1-name="dom:mouseover"', $markdown);
        $t->contains('<a href="https://example.test/source.odt" title="Evented source" class="odf-link" data-odf-link-type="simple" data-odf-link-event-listener-count="2" data-odf-link-event-1-name="dom:mouseover"', $blocksHtml);
        $t->true(!str_contains($blocksHtml, '<script:event-listener'), 'Expected raw ODT script event XML to stay out of WordPress output');
    },
    'reports ODT script package inventory without exposing macro bytes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $basicLibraryXml = '<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard"/>';
        $basicModuleXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Approve
End Sub</script:module>
XML;
        $javaScript = 'function ReviewLinkClick() { return false; }';
        $pythonScript = "def audit():\n    return 'ok'\n";
        $encryptedScript = 'encrypted macro payload';
        $orphanScript = 'function orphan() { return true; }';
        $scriptManifestEntries =
            '  <manifest:file-entry manifest:full-path="Basic/" manifest:media-type=""/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Basic/Standard/script-lb.xml" manifest:media-type="text/xml"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="' . strlen($basicModuleXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/" manifest:media-type=""/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/review-link.js" manifest:media-type="application/javascript" manifest:size="' . strlen($javaScript) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/python/audit.py" manifest:media-type="text/x-python" manifest:size="' . (strlen($pythonScript) + 7) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/encrypted.js" manifest:media-type="application/javascript" manifest:size="2048"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="macro-checksum"><manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="macro-iv"/></manifest:encryption-data></manifest:file-entry>' . "\n";
        $manifestWithScripts = str_replace('</manifest:manifest>', $scriptManifestEntries . '</manifest:manifest>', $manifestXml);
        $contentWithScriptRefs = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Scripted <text:a xlink:href="https://example.test/source.odt" office:title="Scripted source"><office:event-listeners><script:event-listener script:event-name="dom:activate" script:language="ooo:Basic" xlink:href="vnd.sun.star.script:Standard.Review.Approve?language=Basic&amp;location=document" xlink:type="simple"/><script:event-listener script:event-name="dom:click" script:language="JavaScript" script:macro-name="ReviewLinkClick" xlink:href="Scripts/review-link.js" xlink:type="simple"/><script:event-listener script:event-name="dom:load" script:language="Python" xlink:href="Scripts/python/audit.py?entry=run#main" xlink:type="simple"/><script:event-listener script:event-name="dom:error" script:language="JavaScript" xlink:href="Scripts/missing.js" xlink:type="simple"/></office:event-listeners>macro link</text:a> stays inert.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithScriptRefs, $manifestWithScripts, null, null, [
            ['name' => 'Basic/Standard/script-lb.xml', 'data' => $basicLibraryXml],
            ['name' => 'Basic/Standard/Review.xml', 'data' => $basicModuleXml],
            ['name' => 'Scripts/review-link.js', 'data' => $javaScript],
            ['name' => 'Scripts/python/audit.py', 'data' => $pythonScript],
            ['name' => 'Scripts/encrypted.js', 'data' => $encryptedScript],
            ['name' => 'Scripts/orphan.js', 'data' => $orphanScript],
        ]));
        $scripts = $result['scriptMetadata'];
        $partsByPart = [];
        foreach ($scripts['parts'] as $part) {
            $partsByPart[$part['part']] = $part;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }

        $t->same($scripts, $result['document']->attr('scriptMetadata'));
        $t->same($scripts, $result['importReport']['scriptMetadata']);
        $t->same(7, $scripts['partCount']);
        $t->same(2, $scripts['directoryCount']);
        $t->same(6, $scripts['declaredPartCount']);
        $t->same(1, $scripts['undeclaredPartCount']);
        $t->same(1, $scripts['missingPartCount']);
        $t->same(1, $scripts['encryptedPartCount']);
        $t->same(4, $scripts['referenceCount']);
        $t->same(4, $scripts['referencedPartCount']);
        $t->same(3, $scripts['unreferencedPartCount']);
        $t->same(['Basic/' => 'Basic/', 'Scripts/' => 'Scripts/'], array_column($scripts['directories'], 'part', 'part'));
        $t->same(1, $scripts['kindCounts']['basic-library-index']);
        $t->same(1, $scripts['kindCounts']['basic-module']);
        $t->same(4, $scripts['kindCounts']['javascript-script']);
        $t->same(1, $scripts['kindCounts']['python-script']);
        $t->same(2, $scripts['languageCounts']['Basic']);
        $t->same(4, $scripts['languageCounts']['JavaScript']);
        $t->same(1, $scripts['languageCounts']['Python']);

        $basicModule = $partsByPart['Basic/Standard/Review.xml'];
        $t->same('basic-module', $basicModule['kind']);
        $t->same('Basic', $basicModule['language']);
        $t->same('Standard', $basicModule['libraryName']);
        $t->same('Review', $basicModule['moduleName']);
        $t->same(true, $basicModule['exists']);
        $t->same(true, $basicModule['declared']);
        $t->same(true, $basicModule['referenced']);
        $t->same(false, $basicModule['canExposeBytes']);
        $t->same(null, $basicModule['byteLength']);
        $t->same(strlen($basicModuleXml), $basicModule['storedByteLength']);
        $t->same([], $basicModule['diagnostics']);
        $t->same(['vnd.sun.star.script:Standard.Review.Approve?language=Basic&location=document'], $basicModule['hrefs']);
        $t->same('basic-macro', $basicModule['eventReferences'][0]['kind']);
        $t->same('Approve', $basicModule['eventReferences'][0]['macroName']);

        $javascript = $partsByPart['Scripts/review-link.js'];
        $t->same('javascript-script', $javascript['kind']);
        $t->same('JavaScript', $javascript['language']);
        $t->same('review-link', $javascript['moduleName']);
        $t->same(true, $javascript['referenced']);
        $t->same(false, $javascript['canExposeBytes']);
        $t->same(null, $javascript['byteLength']);
        $t->same(strlen($javaScript), $javascript['storedByteLength']);
        $t->same(['Scripts/review-link.js'], $javascript['hrefs']);

        $python = $partsByPart['Scripts/python/audit.py'];
        $t->same('python-script', $python['kind']);
        $t->same('Python', $python['language']);
        $t->same('python', $python['libraryName']);
        $t->same('audit', $python['moduleName']);
        $t->same(true, $python['declaredSizeMismatch']);
        $t->same(['Scripts/python/audit.py?entry=run#main'], $python['hrefs']);

        $missing = $partsByPart['Scripts/missing.js'];
        $t->same(false, $missing['exists']);
        $t->same(true, $missing['referenced']);
        $t->same(['odf-script-package-missing-part'], $missing['diagnostics']);

        $encrypted = $partsByPart['Scripts/encrypted.js'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['canExposeBytes']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedScript), $encrypted['storedByteLength']);
        $t->same('Blowfish CFB', $encrypted['encryption']['algorithm']['name']);
        $t->same(['odf-script-package-encrypted-part'], $encrypted['diagnostics']);

        $orphan = $partsByPart['Scripts/orphan.js'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(false, $orphan['referenced']);
        $t->same(['odf-script-package-undeclared-part'], $orphan['diagnostics']);

        $t->same(false, $manifestByPart['Scripts/review-link.js']['canExposeBytes']);
        $t->same(false, $manifestByPart['Scripts/python/audit.py']['canExposeBytes']);
        $t->same(1, count($result['media']), 'script package payloads must stay out of media byte handoff');
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $t->same(13, $result['importReport']['manifest']['count']);
        $t->same(1, $result['importReport']['manifest']['undeclaredEntryCount']);
        $t->same('Scripts/orphan.js', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('macro link', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'function ReviewLinkClick'), 'Script source bytes must not be rendered into WordPress output');
        $t->true(!str_contains($blocksHtml, 'Sub Approve'), 'Basic macro source bytes must not be rendered into WordPress output');
    },
    'normalizes ODT parent relative text links like upstream fixRelativeLink' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithParentRelativeLinks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Parent <text:a xlink:href="../media/source.odt?download=1#review" office:title="Parent source">review packet</text:a> and local <text:a xlink:href="./local.odt">local packet</text:a>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithParentRelativeLinks));
        $paragraph = $result['document']->children[0];
        $parentLink = $paragraph->children[1];
        $localLink = $paragraph->children[3];

        $t->same('Parent review packet and local local packet.', $paragraph->attr('text'));
        $t->same('link', $parentLink->type);
        $t->same('media/source.odt?download=1#review', $parentLink->attr('url'));
        $t->same('Parent source', $parentLink->attr('title'));
        $t->same('review packet', $parentLink->children[0]->attr('text'));
        $t->same('link', $localLink->type);
        $t->same('./local.odt', $localLink->attr('url'));
        $t->same('local packet', $localLink->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->contains('[review packet](media/source.odt?download=1#review "Parent source")', $markdown);
        $t->contains('[local packet](./local.odt)', $markdown);
        $t->contains('<a href="media/source.odt?download=1#review" title="Parent source">review packet</a>', $blocksHtml);
        $t->contains('<a href="./local.odt">local packet</a>', $blocksHtml);
    },
    'normalizes ODT parent relative frame image links like upstream fixRelativeLink' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithParentRelativeImage = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Parent image <draw:frame draw:name="Parent hero"><draw:image xlink:href="../Pictures/hero.png?download=1#hero"><svg:title>Parent hero title</svg:title><svg:desc>Parent hero alt</svg:desc></draw:image></draw:frame> survives.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithParentRelativeImage));
        $paragraph = $result['document']->children[0];
        $image = $paragraph->children[1];

        $t->same('Parent image Parent hero alt survives.', $paragraph->attr('text'));
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png?download=1#hero', $image->attr('url'));
        $t->same('Pictures/hero.png', $image->attr('sourcePart'));
        $t->same(7, $image->attr('bytes'));
        $t->same('Parent hero alt', $image->attr('alt'));
        $t->same('Parent hero title', $image->attr('title'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('![Parent hero alt](Pictures/hero.png?download=1#hero "Parent hero title")', $markdown);
        $t->contains('<img src="Pictures/hero.png?download=1#hero" alt="Parent hero alt" title="Parent hero title"/>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, '../Pictures/hero.png'), 'Parent-relative image href should not leak into WordPress output');
    },
    'maps ODT annotation ranges into review spans and note handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithAnnotationRange = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <text:p>Review <office:annotation office:name="ann-review-1"><dc:creator>Migration Reviewer</dc:creator><dc:date>2026-06-05T05:58:00Z</dc:date><text:p>Range comment for the annotated source claim.</text:p></office:annotation>annotated <text:span>claim</text:span><office:annotation-end office:name="ann-review-1"/> after.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithAnnotationRange));
        $paragraph = $result['document']->children[0];
        $range = $paragraph->children[1];
        $note = $range->children[1];

        $t->same('Review annotated claim after.', $paragraph->attr('text'));
        $t->same('span', $range->type);
        $t->same(['odf-annotation-range'], $range->attr('classes'));
        $t->same('ann-review-1', $range->attr('annotationName'));
        $t->same('ann-review-1', $range->attr('attributes')['data-odf-annotation-name']);
        $t->same('Migration Reviewer', $range->attr('annotationMetadata')['author']);
        $t->same('2026-06-05T05:58:00Z', $range->attr('annotationMetadata')['date']);
        $t->same('Migration Reviewer', $range->attr('attributes')['data-odf-annotation-author']);
        $t->same('2026-06-05T05:58:00Z', $range->attr('attributes')['data-odf-annotation-date']);
        $t->same('annotated claim', $range->children[0]->attr('text'));
        $t->same('note', $note->type);
        $t->same('Migration Reviewer', $note->attr('author'));
        $t->same('2026-06-05T05:58:00Z', $note->attr('date'));
        $t->same('Range comment for the annotated source claim.', $note->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['noteCount']);
        $t->same(1, $result['importReport']['content']['annotationRangeCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[annotated claim[^1]]{.odf-annotation-range data-odf-annotation-name="ann-review-1" data-odf-annotation-author="Migration Reviewer" data-odf-annotation-date="2026-06-05T05:58:00Z"}', $markdown);
        $t->contains('[^1]: Range comment for the annotated source claim.', $markdown);
        $t->contains('<span class="odf-annotation-range" data-odf-annotation-name="ann-review-1" data-odf-annotation-author="Migration Reviewer" data-odf-annotation-date="2026-06-05T05:58:00Z">annotated claim<sup id="fnref-1">', $blocksHtml);
        $t->contains('<li id="fn-1"><p>Range comment for the annotated source claim.</p>', $blocksHtml);
    },
    'maps ODT reference marks and references into internal review links' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithReferenceMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Range <text:reference-mark-start text:name="Source Claim"/>source claim<text:reference-mark-end text:name="Source Claim"/> and point <text:reference-mark text:name="Point Review"/>marker.</text:p>
      <text:p>See <text:reference-ref text:ref-name="Source Claim" text:reference-format="text">source claim</text:reference-ref> and <text:reference-ref text:ref-name="Point Review" text:reference-format="page">point marker</text:reference-ref>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithReferenceMarks));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $sourceAnchor = $blocks[0]->children[1];
        $pointAnchor = $blocks[0]->children[3];
        $sourceReference = $blocks[1]->children[1];
        $pointReference = $blocks[1]->children[3];

        $t->same('span', $sourceAnchor->type);
        $t->same('source-claim', $sourceAnchor->attr('id'));
        $t->same(['odf-reference-mark', 'odf-reference-mark-range'], $sourceAnchor->attr('classes'));
        $t->same('Source Claim', $sourceAnchor->attr('attributes')['data-odf-reference-name']);
        $t->same('true', $sourceAnchor->attr('attributes')['data-odf-reference-range']);
        $t->same('source claim', $sourceAnchor->children[0]->attr('text'));
        $t->same(' and point ', $blocks[0]->children[2]->attr('text'));
        $t->same('span', $pointAnchor->type);
        $t->same('point-review', $pointAnchor->attr('id'));
        $t->same(['anchor', 'odf-reference-mark'], $pointAnchor->attr('classes'));
        $t->same('Point Review', $pointAnchor->attr('attributes')['data-odf-reference-name']);
        $t->same('Range source claim and point marker.', $blocks[0]->attr('text'));

        $t->same('link', $sourceReference->type);
        $t->same('#source-claim', $sourceReference->attr('url'));
        $t->same(['odf-reference-ref'], $sourceReference->attr('classes'));
        $t->same('Source Claim', $sourceReference->attr('attributes')['data-odf-ref-name']);
        $t->same('text', $sourceReference->attr('attributes')['data-odf-reference-format']);
        $t->same('source claim', $sourceReference->children[0]->attr('text'));
        $t->same('link', $pointReference->type);
        $t->same('#point-review', $pointReference->attr('url'));
        $t->same('page', $pointReference->attr('attributes')['data-odf-reference-format']);
        $t->same('point marker', $pointReference->children[0]->attr('text'));

        $t->same(2, $result['importReport']['content']['referenceMarkCount']);
        $t->same(2, $result['importReport']['content']['referenceReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[source claim]{#source-claim .odf-reference-mark .odf-reference-mark-range data-odf-reference-name="Source Claim" data-odf-reference-range="true"}', $markdown);
        $t->contains('[source claim](#source-claim){.odf-reference-ref data-odf-ref-name="Source Claim" data-odf-reference-format="text"}', $markdown);
        $t->contains('<span id="source-claim" class="odf-reference-mark odf-reference-mark-range" data-odf-reference-name="Source Claim" data-odf-reference-range="true">source claim</span>', $blocksHtml);
        $t->contains('<a href="#source-claim" class="odf-reference-ref" data-odf-ref-name="Source Claim" data-odf-reference-format="text">source claim</a>', $blocksHtml);
        $t->contains('<span id="point-review" class="anchor odf-reference-mark" data-odf-reference-name="Point Review" data-pandoc-anchor="empty-target"></span>marker.', $blocksHtml);
        $t->contains('<a href="#point-review" class="odf-reference-ref" data-odf-ref-name="Point Review" data-odf-reference-format="page">point marker</a>', $blocksHtml);
    },
    'wraps ODT reference mark ranges around nested inline content' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNestedReferenceMark = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Claim <text:reference-mark-start text:name="Styled Source Claim"/>styled <text:a xlink:href="https://example.test/source-claim">source link</text:a><text:reference-mark-end text:name="Styled Source Claim"/> survives.</text:p>
      <text:p>Jump to <text:reference-ref text:ref-name="Styled Source Claim" text:reference-format="text">styled claim</text:reference-ref>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNestedReferenceMark));
        $paragraph = $result['document']->children[0];
        $referenceMark = $paragraph->children[1];
        $reference = $result['document']->children[1]->children[1];

        $t->same('Claim styled source link survives.', $paragraph->attr('text'));
        $t->same('span', $referenceMark->type);
        $t->same('styled-source-claim', $referenceMark->attr('id'));
        $t->same(['odf-reference-mark', 'odf-reference-mark-range'], $referenceMark->attr('classes'));
        $t->same('Styled Source Claim', $referenceMark->attr('referenceName'));
        $t->same(true, $referenceMark->attr('referenceRange'));
        $t->same('true', $referenceMark->attr('attributes')['data-odf-reference-range']);
        $t->same('styled ', $referenceMark->children[0]->attr('text'));
        $t->same('link', $referenceMark->children[1]->type);
        $t->same('https://example.test/source-claim', $referenceMark->children[1]->attr('url'));
        $t->same('source link', $referenceMark->children[1]->children[0]->attr('text'));
        $t->same('#styled-source-claim', $reference->attr('url'));
        $t->same(1, $result['importReport']['content']['referenceMarkCount']);
        $t->same(1, $result['importReport']['content']['referenceReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[styled [source link](https://example.test/source-claim)]{#styled-source-claim .odf-reference-mark .odf-reference-mark-range data-odf-reference-name="Styled Source Claim" data-odf-reference-range="true"}', $markdown);
        $t->contains('[styled claim](#styled-source-claim){.odf-reference-ref data-odf-ref-name="Styled Source Claim" data-odf-reference-format="text"}', $markdown);
        $t->contains('<span id="styled-source-claim" class="odf-reference-mark odf-reference-mark-range" data-odf-reference-name="Styled Source Claim" data-odf-reference-range="true">styled <a href="https://example.test/source-claim">source link</a></span>', $blocksHtml);
        $t->contains('<a href="#styled-source-claim" class="odf-reference-ref" data-odf-ref-name="Styled Source Claim" data-odf-reference-format="text">styled claim</a>', $blocksHtml);
    },
    'maps ODT sequence fields into review spans and import report metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSequences = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Caption <text:sequence text:name="Illustration" text:formula="ooow:Illustration+1" text:ref-name="seq-hero">Figure 1</text:sequence>: Hero image.</text:p>
      <text:h text:outline-level="2">Appendix <text:sequence text:name="Chapter" text:formula="ooow:Chapter+1">A</text:sequence></text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSequences));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $figureSequence = $paragraph->children[1];
        $chapterSequence = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Caption Figure 1: Hero image.', $paragraph->attr('text'));
        $t->same('span', $figureSequence->type);
        $t->same(['odf-sequence'], $figureSequence->attr('classes'));
        $t->same('Figure 1', $figureSequence->children[0]->attr('text'));
        $t->same('Illustration', $figureSequence->attr('attributes')['data-odf-sequence-name']);
        $t->same('ooow:Illustration+1', $figureSequence->attr('attributes')['data-odf-sequence-formula']);
        $t->same('seq-hero', $figureSequence->attr('attributes')['data-odf-sequence-ref-name']);
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('span', $chapterSequence->type);
        $t->same('Chapter', $chapterSequence->attr('attributes')['data-odf-sequence-name']);
        $t->same('A', $chapterSequence->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['sequenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Caption [Figure 1]{.odf-sequence data-odf-sequence-name="Illustration" data-odf-sequence-formula="ooow:Illustration+1" data-odf-sequence-ref-name="seq-hero"}: Hero image.', $markdown);
        $t->contains('## Appendix [A]{.odf-sequence data-odf-sequence-name="Chapter" data-odf-sequence-formula="ooow:Chapter+1"}', $markdown);
        $t->contains('<span class="odf-sequence" data-odf-sequence-name="Illustration" data-odf-sequence-formula="ooow:Illustration+1" data-odf-sequence-ref-name="seq-hero">Figure 1</span>', $blocksHtml);
        $t->contains('<h2 id="appendix-a">Appendix <span class="odf-sequence" data-odf-sequence-name="Chapter" data-odf-sequence-formula="ooow:Chapter+1">A</span></h2>', $blocksHtml);
    },
    'maps ODT sequence and note reference fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithReferenceFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>References <text:sequence-ref text:ref-name="seq-hero" text:reference-format="category-and-value">Figure 1</text:sequence-ref> and footnote <text:note-ref text:ref-name="ftn-review" text:note-class="footnote" text:reference-format="text">1</text:note-ref> stay reviewable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithReferenceFields));
        $paragraph = $result['document']->children[0];
        $sequenceRef = $paragraph->children[1];
        $noteRef = $paragraph->children[3];

        $t->same('References Figure 1 and footnote 1 stay reviewable.', $paragraph->attr('text'));
        $t->same('span', $sequenceRef->type);
        $t->same(['odf-field', 'odf-field-sequence-ref'], $sequenceRef->attr('classes'));
        $t->same('sequence-ref', $sequenceRef->attr('fieldType'));
        $t->same('seq-hero', $sequenceRef->attr('fieldMetadata')['refName']);
        $t->same('category-and-value', $sequenceRef->attr('fieldMetadata')['referenceFormat']);
        $t->same('seq-hero', $sequenceRef->attr('attributes')['data-odf-field-ref-name']);
        $t->same('category-and-value', $sequenceRef->attr('attributes')['data-odf-field-reference-format']);
        $t->same('Figure 1', $sequenceRef->children[0]->attr('text'));

        $t->same('span', $noteRef->type);
        $t->same(['odf-field', 'odf-field-note-ref'], $noteRef->attr('classes'));
        $t->same('note-ref', $noteRef->attr('fieldType'));
        $t->same('ftn-review', $noteRef->attr('fieldMetadata')['refName']);
        $t->same('footnote', $noteRef->attr('fieldMetadata')['noteClass']);
        $t->same('text', $noteRef->attr('fieldMetadata')['referenceFormat']);
        $t->same('ftn-review', $noteRef->attr('attributes')['data-odf-field-ref-name']);
        $t->same('footnote', $noteRef->attr('attributes')['data-odf-field-note-class']);
        $t->same('text', $noteRef->attr('attributes')['data-odf-field-reference-format']);
        $t->same('1', $noteRef->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Figure 1]{.odf-field .odf-field-sequence-ref data-odf-field-type="sequence-ref" data-odf-field-ref-name="seq-hero" data-odf-field-reference-format="category-and-value"}', $markdown);
        $t->contains('[1]{.odf-field .odf-field-note-ref data-odf-field-type="note-ref" data-odf-field-ref-name="ftn-review" data-odf-field-reference-format="text" data-odf-field-note-class="footnote"}', $markdown);
        $t->contains('<span class="odf-field odf-field-sequence-ref" data-odf-field-type="sequence-ref" data-odf-field-ref-name="seq-hero" data-odf-field-reference-format="category-and-value">Figure 1</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-note-ref" data-odf-field-type="note-ref" data-odf-field-ref-name="ftn-review" data-odf-field-reference-format="text" data-odf-field-note-class="footnote">1</span>', $blocksHtml);
    },
    'preserves ODT soft page breaks as review markers' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSoftPageBreak = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Before source page boundary <text:soft-page-break/>after source page boundary.</text:p>
      <text:h text:outline-level="2">Appendix marker<text:soft-page-break/>continued heading</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSoftPageBreak));
        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $paragraphBreak = $paragraph->children[1];
        $headingBreak = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Before source page boundary after source page boundary.', $paragraph->attr('text'));
        $t->same('span', $paragraphBreak->type);
        $t->same(true, $paragraphBreak->attr('softPageBreak'));
        $t->same(['odf-soft-page-break'], $paragraphBreak->attr('classes'));
        $t->same('true', $paragraphBreak->attr('attributes')['data-odf-soft-page-break']);
        $t->same('heading', $heading->type);
        $t->same('span', $headingBreak->type);
        $t->same(true, $headingBreak->attr('softPageBreak'));
        $t->same(2, $result['importReport']['content']['softPageBreakCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Before source page boundary []{.odf-soft-page-break data-odf-soft-page-break="true"}after source page boundary.', $markdown);
        $t->contains('## Appendix marker[]{.odf-soft-page-break data-odf-soft-page-break="true"}continued heading', $markdown);
        $t->contains('<span class="odf-soft-page-break" data-odf-soft-page-break="true"></span>after source page boundary.', $blocksHtml);
        $t->contains('<h2 id="appendix-markercontinued-heading">Appendix marker<span class="odf-soft-page-break" data-odf-soft-page-break="true"></span>continued heading</h2>', $blocksHtml);
    },
    'maps ODT tab stops to Pandoc spaces in inline content' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTabs = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Before<text:tab/>after and <text:span>inner<text:tab/>tab</text:span>.</text:p>
      <text:h text:outline-level="2">Heading<text:tab/>tab</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTabs));
        $paragraph = $result['document']->children[0];
        $heading = $result['document']->children[1];

        $t->same('Before after and inner tab.', $paragraph->attr('text'));
        $t->same('Before after and inner tab.', $paragraph->children[0]->attr('text'));
        $t->true(!str_contains($paragraph->attr('text'), "\t"), 'ODF tabs should normalize to Pandoc spaces in plain text');
        $t->same('Heading tab', $heading->children[0]->attr('text'));
        $t->true(!str_contains($heading->children[0]->attr('text'), "\t"), 'ODF tabs should normalize to Pandoc spaces in headings');

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Before after and inner tab.', $markdown);
        $t->contains('## Heading tab', $markdown);
        $t->contains('<p>Before after and inner tab.</p>', $blocksHtml);
        $t->contains('<h2 id="heading-tab">Heading tab</h2>', $blocksHtml);
    },
    'maps ODT form controls into review placeholders' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithForms = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <office:forms>
        <form:form form:name="Review Form">
          <form:text form:id="ctrl-title" form:name="SourceTitle" form:label="Source title" form:current-value="Migrated title" form:control-implementation="ooo:com.sun.star.form.component.TextField"/>
          <form:checkbox form:id="ctrl-publish" form:name="PublishReady" form:label="Ready to publish" form:current-state="checked"/>
        </form:form>
      </office:forms>
      <text:p>Title field <draw:control draw:control="ctrl-title"/> and missing <draw:control draw:control="ctrl-missing"/> remain visible.</text:p>
      <draw:frame draw:name="Publish checkbox" svg:width="3cm" svg:height="1cm">
        <draw:control draw:control="ctrl-publish"/>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithForms));
        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $titleControl = $paragraph->children[1];
        $missingControl = $paragraph->children[3];
        $blockControl = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('paragraph', $paragraph->type);
        $t->same('Title field Source title and missing ctrl-missing remain visible.', $paragraph->attr('text'));
        $t->same('span', $titleControl->type);
        $t->same(['odf-form-control', 'odf-control-text'], $titleControl->attr('classes'));
        $t->same('ctrl-title', $titleControl->attr('controlId'));
        $t->same('text', $titleControl->attr('controlType'));
        $t->same(true, $titleControl->attr('exists'));
        $t->same('Review Form', $titleControl->attr('formControl')['formName']);
        $t->same('SourceTitle', $titleControl->attr('formControl')['name']);
        $t->same('Source title', $titleControl->attr('formControl')['label']);
        $t->same('Migrated title', $titleControl->attr('formControl')['currentValue']);
        $t->same('ooo:com.sun.star.form.component.TextField', $titleControl->attr('formControl')['implementation']);
        $t->same('ctrl-title', $titleControl->attr('attributes')['data-odf-control-id']);
        $t->same('text', $titleControl->attr('attributes')['data-odf-control-type']);
        $t->same('true', $titleControl->attr('attributes')['data-odf-control-exists']);
        $t->same('Source title', $titleControl->children[0]->attr('text'));

        $t->same('span', $missingControl->type);
        $t->same(['odf-form-control', 'odf-missing-form-control'], $missingControl->attr('classes'));
        $t->same(false, $missingControl->attr('exists'));
        $t->same('ctrl-missing', $missingControl->children[0]->attr('text'));
        $t->same('false', $missingControl->attr('attributes')['data-odf-control-exists']);

        $t->same('div', $blockControl->type);
        $t->same(['odf-form-control', 'odf-control-checkbox'], $blockControl->attr('classes'));
        $t->same('ctrl-publish', $blockControl->attr('controlId'));
        $t->same('checkbox', $blockControl->attr('controlType'));
        $t->same('checked', $blockControl->attr('formControl')['currentState']);
        $t->same('Publish checkbox', $blockControl->attr('attributes')['data-odf-control-frame-name']);
        $t->same('3cm', $blockControl->attr('attributes')['data-odf-control-width']);
        $t->same('1cm', $blockControl->attr('attributes')['data-odf-control-height']);
        $t->same('Ready to publish', $blockControl->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['formControlCount']);
        $t->same(1, $result['importReport']['content']['missingFormControlCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Source title]{.odf-form-control .odf-control-text data-odf-control-id="ctrl-title" data-odf-control-type="text" data-odf-control-exists="true"', $markdown);
        $t->contains('[ctrl-missing]{.odf-form-control .odf-missing-form-control data-odf-control-id="ctrl-missing" data-odf-control-exists="false"}', $markdown);
        $t->contains('::: {.odf-form-control .odf-control-checkbox data-odf-control-id="ctrl-publish" data-odf-control-type="checkbox" data-odf-control-exists="true"', $markdown);
        $t->contains('<span class="odf-form-control odf-control-text" data-odf-control-id="ctrl-title" data-odf-control-type="text" data-odf-control-exists="true"', $blocksHtml);
        $t->contains('<span class="odf-form-control odf-missing-form-control" data-odf-control-id="ctrl-missing" data-odf-control-exists="false">ctrl-missing</span>', $blocksHtml);
        $t->contains('<div class="odf-form-control odf-control-checkbox" data-odf-control-id="ctrl-publish" data-odf-control-type="checkbox" data-odf-control-exists="true"', $blocksHtml);
        $t->contains('Ready to publish', $blocksHtml);
    },
    'maps ODT form submission metadata onto review controls' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFormSubmission = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <office:forms>
        <form:form
          form:name="Submission Form"
          xlink:href="https://example.test/import-review"
          xlink:type="simple"
          form:method="post"
          form:enctype="application/x-www-form-urlencoded"
          form:target-frame="_blank"
          form:command-type="table"
          form:command="import_review_packets"
          form:datasource="wp_import_queue"
          form:apply-filter="true"
          form:filter="status = 'ready'"
          form:order="created DESC"
          form:navigation-mode="current"
          form:tab-cycle="records"
          form:ignore-result="false"
          form:escape-processing="true"
          form:master-fields="source_id"
          form:detail-fields="source_id">
          <form:text form:id="ctrl-submit-title" form:name="SourceTitle" form:label="Source title" form:current-value="Ready packet"/>
        </form:form>
      </office:forms>
      <text:p>Submission <draw:control draw:control="ctrl-submit-title"/> stays auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFormSubmission));
        $control = $result['document']->children[0]->children[1];
        $formMetadata = $control->attr('formControl')['formMetadata'] ?? [];

        $t->same('Source title', $control->children[0]->attr('text'));
        $t->same('https://example.test/import-review', $formMetadata['action'] ?? null);
        $t->same('post', $formMetadata['method'] ?? null);
        $t->same('application/x-www-form-urlencoded', $formMetadata['enctype'] ?? null);
        $t->same('_blank', $formMetadata['targetFrame'] ?? null);
        $t->same('table', $formMetadata['commandType'] ?? null);
        $t->same('import_review_packets', $formMetadata['command'] ?? null);
        $t->same('wp_import_queue', $formMetadata['datasource'] ?? null);
        $t->same(true, $formMetadata['applyFilter'] ?? null);
        $t->same(false, $formMetadata['ignoreResult'] ?? null);
        $t->same(true, $formMetadata['escapeProcessing'] ?? null);
        $t->same('source_id', $formMetadata['masterFields'] ?? null);
        $t->same('source_id', $formMetadata['detailFields'] ?? null);
        $t->same('https://example.test/import-review', $control->attr('formControl')['formAction']);
        $t->same('post', $control->attr('attributes')['data-odf-control-form-method']);
        $t->same('table', $control->attr('attributes')['data-odf-control-form-command-type']);
        $t->same('true', $control->attr('attributes')['data-odf-control-form-apply-filter']);
        $t->same('false', $control->attr('attributes')['data-odf-control-form-ignore-result']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->contains('data-odf-control-form-action="https://example.test/import-review"', $markdown);
        $t->contains('data-odf-control-form-command="import_review_packets"', $markdown);
        $t->contains('data-odf-control-form-datasource="wp_import_queue"', $markdown);
        $t->contains('<span class="odf-form-control odf-control-text" data-odf-control-id="ctrl-submit-title"', $blocksHtml);
        $t->contains('data-odf-control-form-target-frame="_blank"', $blocksHtml);
        $t->contains('data-odf-control-form-master-fields="source_id"', $blocksHtml);
    },
    'maps ODT option-bearing form controls into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFormOptions = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0">
  <office:body>
    <office:text>
      <office:forms>
        <form:form form:name="Review Form">
          <form:combobox form:id="ctrl-disposition" form:name="Disposition" form:label="Review disposition" form:current-value="Ready to publish" form:dropdown="true" form:automatic-completion="true">
            <form:option form:label="Draft" form:value="draft"/>
            <form:option form:label="Ready to publish" form:value="ready" form:current-selected="true"/>
          </form:combobox>
          <form:listbox form:id="ctrl-categories" form:name="Categories" form:list-source-type="cell-range" form:list-source="Source.A2:Source.A4" form:bound-column="2" form:multiple="true">
            <form:item form:label="Posts" form:value="post" form:selected="true"/>
            <form:item form:label="Pages" form:value="page"/>
            <form:item form:label="Media" form:value="attachment" form:current-selected="true"/>
          </form:listbox>
        </form:form>
      </office:forms>
      <text:p>Disposition <draw:control draw:control="ctrl-disposition"/> and categories <draw:control draw:control="ctrl-categories"/> remain auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFormOptions));
        $paragraph = $result['document']->children[0];
        $combobox = $paragraph->children[1];
        $listbox = $paragraph->children[3];

        $t->same('Disposition Review disposition and categories Posts, Media remain auditable.', $paragraph->attr('text'));
        $t->same('span', $combobox->type);
        $t->same(['odf-form-control', 'odf-control-combobox'], $combobox->attr('classes'));
        $t->same('combobox', $combobox->attr('controlType'));
        $t->same('Review disposition', $combobox->children[0]->attr('text'));
        $t->same(2, $combobox->attr('formControl')['optionCount']);
        $t->same(1, $combobox->attr('formControl')['selectedOptionCount']);
        $t->same('Ready to publish', $combobox->attr('formControl')['selectedOptionLabels']);
        $t->same('ready', $combobox->attr('formControl')['selectedOptionValues']);
        $t->same(true, $combobox->attr('formControl')['dropdown']);
        $t->same(true, $combobox->attr('formControl')['automaticCompletion']);
        $t->same('option', $combobox->attr('formControl')['options'][0]['element']);
        $t->same('Draft', $combobox->attr('formControl')['options'][0]['label']);
        $t->same('draft', $combobox->attr('formControl')['options'][0]['value']);
        $t->same(true, $combobox->attr('formControl')['options'][1]['selected']);
        $t->same('2', $combobox->attr('attributes')['data-odf-control-option-count']);
        $t->same('1', $combobox->attr('attributes')['data-odf-control-selected-option-count']);
        $t->same('Ready to publish', $combobox->attr('attributes')['data-odf-control-selected-option-labels']);
        $t->same('ready', $combobox->attr('attributes')['data-odf-control-selected-option-values']);
        $t->same('true', $combobox->attr('attributes')['data-odf-control-dropdown']);
        $t->same('true', $combobox->attr('attributes')['data-odf-control-automatic-completion']);

        $t->same('span', $listbox->type);
        $t->same(['odf-form-control', 'odf-control-listbox'], $listbox->attr('classes'));
        $t->same('listbox', $listbox->attr('controlType'));
        $t->same('Posts, Media', $listbox->children[0]->attr('text'));
        $t->same(3, $listbox->attr('formControl')['optionCount']);
        $t->same(2, $listbox->attr('formControl')['selectedOptionCount']);
        $t->same('Posts, Media', $listbox->attr('formControl')['selectedOptionLabels']);
        $t->same('post, attachment', $listbox->attr('formControl')['selectedOptionValues']);
        $t->same('cell-range', $listbox->attr('formControl')['listSourceType']);
        $t->same('Source.A2:Source.A4', $listbox->attr('formControl')['listSource']);
        $t->same(2, $listbox->attr('formControl')['boundColumn']);
        $t->same(true, $listbox->attr('formControl')['multiple']);
        $t->same('item', $listbox->attr('formControl')['options'][0]['element']);
        $t->same('Pages', $listbox->attr('formControl')['options'][1]['label']);
        $t->same('attachment', $listbox->attr('formControl')['options'][2]['value']);
        $t->same(true, $listbox->attr('formControl')['options'][2]['selected']);
        $t->same('3', $listbox->attr('attributes')['data-odf-control-option-count']);
        $t->same('2', $listbox->attr('attributes')['data-odf-control-selected-option-count']);
        $t->same('Posts, Media', $listbox->attr('attributes')['data-odf-control-selected-option-labels']);
        $t->same('post, attachment', $listbox->attr('attributes')['data-odf-control-selected-option-values']);
        $t->same('cell-range', $listbox->attr('attributes')['data-odf-control-list-source-type']);
        $t->same('Source.A2:Source.A4', $listbox->attr('attributes')['data-odf-control-list-source']);
        $t->same('2', $listbox->attr('attributes')['data-odf-control-bound-column']);
        $t->same('true', $listbox->attr('attributes')['data-odf-control-multiple']);
        $t->same(2, $result['importReport']['content']['formControlCount']);
        $t->same(5, $result['importReport']['content']['formControlOptionCount']);
        $t->same(3, $result['importReport']['content']['selectedFormControlOptionCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Review disposition]{.odf-form-control .odf-control-combobox data-odf-control-id="ctrl-disposition" data-odf-control-type="combobox" data-odf-control-exists="true"', $markdown);
        $t->contains('data-odf-control-selected-option-labels="Ready to publish"', $markdown);
        $t->contains('[Posts, Media]{.odf-form-control .odf-control-listbox data-odf-control-id="ctrl-categories" data-odf-control-type="listbox" data-odf-control-exists="true"', $markdown);
        $t->contains('data-odf-control-list-source="Source.A2:Source.A4"', $markdown);
        $t->contains('<span class="odf-form-control odf-control-combobox" data-odf-control-id="ctrl-disposition" data-odf-control-type="combobox" data-odf-control-exists="true"', $blocksHtml);
        $t->contains('data-odf-control-option-count="2" data-odf-control-selected-option-count="1" data-odf-control-selected-option-labels="Ready to publish"', $blocksHtml);
        $t->contains('<span class="odf-form-control odf-control-listbox" data-odf-control-id="ctrl-categories" data-odf-control-type="listbox" data-odf-control-exists="true"', $blocksHtml);
        $t->contains('data-odf-control-list-source-type="cell-range"', $blocksHtml);
        $t->contains('data-odf-control-list-source="Source.A2:Source.A4"', $blocksHtml);
    },
    'maps ODT field declarations and user-field fallback values into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFieldDeclarations = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:sequence-decls>
        <text:sequence-decl text:name="Illustration" text:display-outline-level="0" text:separation-character="."/>
        <text:sequence-decl text:name="Table" text:display-outline-level="1" text:separation-character=":"/>
      </text:sequence-decls>
      <text:variable-decls>
        <text:variable-decl text:name="ReviewStatus" office:value-type="string"/>
      </text:variable-decls>
      <text:user-field-decls>
        <text:user-field-decl text:name="Reviewer" office:value-type="string" office:string-value="Migration Desk"/>
        <text:user-field-decl text:name="SourcePage" office:value-type="float" office:value="12"/>
      </text:user-field-decls>
      <text:p>Declared reviewer <text:user-field-get text:name="Reviewer"/> saw source page <text:user-field-get text:name="SourcePage"/> before <text:sequence text:name="Illustration" text:formula="ooow:Illustration+1">Figure 1</text:sequence>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFieldDeclarations));
        $document = $result['document'];
        $declarations = $result['contentDeclarations'];
        $paragraph = $document->children[0];
        $reviewerField = $paragraph->children[1];
        $pageField = $paragraph->children[3];
        $sequence = $paragraph->children[5];

        $t->same('Declared reviewer Migration Desk saw source page 12 before Figure 1.', $paragraph->attr('text'));
        $t->same(2, $declarations['sequenceDeclarationCount']);
        $t->same(1, $declarations['variableDeclarationCount']);
        $t->same(2, $declarations['userFieldDeclarationCount']);
        $t->same('Illustration', $declarations['sequenceDeclarations']['Illustration']['name']);
        $t->same(0, $declarations['sequenceDeclarations']['Illustration']['displayOutlineLevel']);
        $t->same('.', $declarations['sequenceDeclarations']['Illustration']['separationCharacter']);
        $t->same('Table', $declarations['sequenceDeclarations']['Table']['name']);
        $t->same(1, $declarations['sequenceDeclarations']['Table']['displayOutlineLevel']);
        $t->same(':', $declarations['sequenceDeclarations']['Table']['separationCharacter']);
        $t->same('ReviewStatus', $declarations['variableDeclarations']['ReviewStatus']['name']);
        $t->same('string', $declarations['variableDeclarations']['ReviewStatus']['valueType']);
        $t->same('Migration Desk', $declarations['userFieldDeclarations']['Reviewer']['stringValue']);
        $t->same('float', $declarations['userFieldDeclarations']['SourcePage']['valueType']);
        $t->same('12', $declarations['userFieldDeclarations']['SourcePage']['value']);
        $t->same($declarations, $document->attr('contentDeclarations'));

        $t->same('span', $reviewerField->type);
        $t->same('Reviewer', $reviewerField->attr('fieldName'));
        $t->same(true, $reviewerField->attr('fieldMetadata')['declared']);
        $t->same('Migration Desk', $reviewerField->attr('fieldMetadata')['stringValue']);
        $t->same('Migration Desk', $reviewerField->children[0]->attr('text'));
        $t->same('true', $reviewerField->attr('attributes')['data-odf-field-declared']);
        $t->same('Migration Desk', $reviewerField->attr('attributes')['data-odf-field-string-value']);

        $t->same('span', $pageField->type);
        $t->same('SourcePage', $pageField->attr('fieldName'));
        $t->same(true, $pageField->attr('fieldMetadata')['declared']);
        $t->same('float', $pageField->attr('fieldMetadata')['valueType']);
        $t->same('12', $pageField->attr('fieldMetadata')['value']);
        $t->same('12', $pageField->children[0]->attr('text'));
        $t->same('true', $pageField->attr('attributes')['data-odf-field-declared']);
        $t->same('12', $pageField->attr('attributes')['data-odf-field-value']);

        $t->same('span', $sequence->type);
        $t->same('Illustration', $sequence->attr('attributes')['data-odf-sequence-name']);
        $t->same(2, $result['importReport']['content']['fieldCount']);
        $t->same(1, $result['importReport']['content']['sequenceCount']);
        $t->same(2, $result['importReport']['contentDeclarations']['sequenceDeclarationCount']);
        $t->same(1, $result['importReport']['contentDeclarations']['variableDeclarationCount']);
        $t->same(2, $result['importReport']['contentDeclarations']['userFieldDeclarationCount']);
        $t->same('Migration Desk', $result['importReport']['contentDeclarations']['userFieldDeclarations']['Reviewer']['stringValue']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Migration Desk]{.odf-field .odf-field-user-field-get data-odf-field-type="user-field-get" data-odf-field-name="Reviewer" data-odf-field-value-type="string" data-odf-field-string-value="Migration Desk" data-odf-field-declared="true"}', $markdown);
        $t->contains('[12]{.odf-field .odf-field-user-field-get data-odf-field-type="user-field-get" data-odf-field-name="SourcePage" data-odf-field-value-type="float" data-odf-field-value="12" data-odf-field-declared="true"}', $markdown);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="Reviewer" data-odf-field-value-type="string" data-odf-field-string-value="Migration Desk" data-odf-field-declared="true">Migration Desk</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="SourcePage" data-odf-field-value-type="float" data-odf-field-value="12" data-odf-field-declared="true">12</span>', $blocksHtml);
    },
    'enriches typed ODT sequence references from sequence declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTypedSequenceReferences = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:sequence-decls>
        <text:sequence-decl text:name="Illustration" text:display-outline-level="0" text:separation-character="."/>
        <text:sequence-decl text:name="Table" text:display-outline-level="1" text:separation-character=":"/>
      </text:sequence-decls>
      <text:p>Caption reference <text:sequence-ref text:name="Illustration" text:ref-name="fig-hero" text:reference-format="category-and-value">Figure 1</text:sequence-ref> and unresolved <text:sequence-ref text:name="Unknown" text:ref-name="unknown-1" text:reference-format="text"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTypedSequenceReferences));
        $paragraph = $result['document']->children[0];
        $typedReference = $paragraph->children[1];
        $unresolvedReference = $paragraph->children[3];

        $t->same('Caption reference Figure 1 and unresolved unknown-1.', $paragraph->attr('text'));
        $t->same(['odf-field', 'odf-field-sequence-ref'], $typedReference->attr('classes'));
        $t->same('sequence-ref', $typedReference->attr('fieldType'));
        $t->same('Illustration', $typedReference->attr('fieldName'));
        $t->same('Illustration', $typedReference->attr('fieldMetadata')['name']);
        $t->same('fig-hero', $typedReference->attr('fieldMetadata')['refName']);
        $t->same('category-and-value', $typedReference->attr('fieldMetadata')['referenceFormat']);
        $t->same(0, $typedReference->attr('fieldMetadata')['sequenceDisplayOutlineLevel']);
        $t->same('.', $typedReference->attr('fieldMetadata')['sequenceSeparationCharacter']);
        $t->same(true, $typedReference->attr('fieldMetadata')['declared']);
        $t->same('Illustration', $typedReference->attr('attributes')['data-odf-field-name']);
        $t->same('fig-hero', $typedReference->attr('attributes')['data-odf-field-ref-name']);
        $t->same('category-and-value', $typedReference->attr('attributes')['data-odf-field-reference-format']);
        $t->same('0', $typedReference->attr('attributes')['data-odf-field-sequence-display-outline-level']);
        $t->same('.', $typedReference->attr('attributes')['data-odf-field-sequence-separation-character']);
        $t->same('true', $typedReference->attr('attributes')['data-odf-field-declared']);

        $t->same(['odf-field', 'odf-field-sequence-ref'], $unresolvedReference->attr('classes'));
        $t->same('sequence-ref', $unresolvedReference->attr('fieldType'));
        $t->same('Unknown', $unresolvedReference->attr('fieldName'));
        $t->same('unknown-1', $unresolvedReference->children[0]->attr('text'));
        $t->same('text', $unresolvedReference->attr('fieldMetadata')['referenceFormat']);
        $t->same(false, isset($unresolvedReference->attr('fieldMetadata')['declared']));
        $t->same(false, isset($unresolvedReference->attr('attributes')['data-odf-field-declared']));
        $t->same(2, $result['contentDeclarations']['sequenceDeclarationCount']);
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Figure 1]{.odf-field .odf-field-sequence-ref data-odf-field-type="sequence-ref" data-odf-field-name="Illustration" data-odf-field-ref-name="fig-hero" data-odf-field-reference-format="category-and-value" data-odf-field-sequence-display-outline-level="0" data-odf-field-sequence-separation-character="." data-odf-field-declared="true"}', $markdown);
        $t->contains('[unknown-1]{.odf-field .odf-field-sequence-ref data-odf-field-type="sequence-ref" data-odf-field-name="Unknown" data-odf-field-ref-name="unknown-1" data-odf-field-reference-format="text"}', $markdown);
        $t->contains('<span class="odf-field odf-field-sequence-ref" data-odf-field-type="sequence-ref" data-odf-field-name="Illustration" data-odf-field-ref-name="fig-hero" data-odf-field-reference-format="category-and-value" data-odf-field-sequence-display-outline-level="0" data-odf-field-sequence-separation-character="." data-odf-field-declared="true">Figure 1</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-sequence-ref" data-odf-field-type="sequence-ref" data-odf-field-name="Unknown" data-odf-field-ref-name="unknown-1" data-odf-field-reference-format="text">unknown-1</span>', $blocksHtml);
    },
    'resolves ODT variable-get fallbacks from current variable state' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithVariableState = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:variable-decls>
        <text:variable-decl text:name="ReviewStatus" office:value-type="string"/>
        <text:variable-decl text:name="ApprovedCount" office:value-type="float"/>
      </text:variable-decls>
      <text:p>Status <text:variable-set text:name="ReviewStatus" office:value-type="string" office:string-value="Ready"/> then <text:variable-get text:name="ReviewStatus"/>.</text:p>
      <text:p>Approved <text:variable-input text:name="ApprovedCount" office:value-type="float" office:value="12"/> then <text:variable-get text:name="ApprovedCount"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithVariableState));
        $declarations = $result['contentDeclarations'];
        $statusParagraph = $result['document']->children[0];
        $countParagraph = $result['document']->children[1];
        $statusSet = $statusParagraph->children[1];
        $statusGet = $statusParagraph->children[3];
        $countInput = $countParagraph->children[1];
        $countGet = $countParagraph->children[3];

        $t->same(2, $declarations['variableDeclarationCount']);
        $t->same('string', $declarations['variableDeclarations']['ReviewStatus']['valueType']);
        $t->same('float', $declarations['variableDeclarations']['ApprovedCount']['valueType']);
        $t->same('Status Ready then Ready.', $statusParagraph->attr('text'));
        $t->same('Approved 12 then 12.', $countParagraph->attr('text'));

        $t->same('variable-set', $statusSet->attr('fieldType'));
        $t->same('Ready', $statusSet->attr('fieldMetadata')['stringValue']);
        $t->same('Ready', $statusSet->children[0]->attr('text'));
        $t->same('variable-get', $statusGet->attr('fieldType'));
        $t->same('ReviewStatus', $statusGet->attr('fieldName'));
        $t->same('string', $statusGet->attr('fieldMetadata')['valueType']);
        $t->same('Ready', $statusGet->attr('fieldMetadata')['stringValue']);
        $t->same(true, $statusGet->attr('fieldMetadata')['declared']);
        $t->same('Ready', $statusGet->children[0]->attr('text'));
        $t->same('true', $statusGet->attr('attributes')['data-odf-field-declared']);
        $t->same('Ready', $statusGet->attr('attributes')['data-odf-field-string-value']);

        $t->same('variable-input', $countInput->attr('fieldType'));
        $t->same('ApprovedCount', $countInput->attr('fieldName'));
        $t->same('float', $countInput->attr('fieldMetadata')['valueType']);
        $t->same('12', $countInput->attr('fieldMetadata')['value']);
        $t->same(true, $countInput->attr('fieldMetadata')['declared']);
        $t->same('12', $countInput->children[0]->attr('text'));
        $t->same('variable-get', $countGet->attr('fieldType'));
        $t->same('ApprovedCount', $countGet->attr('fieldName'));
        $t->same('float', $countGet->attr('fieldMetadata')['valueType']);
        $t->same('12', $countGet->attr('fieldMetadata')['value']);
        $t->same(true, $countGet->attr('fieldMetadata')['declared']);
        $t->same('12', $countGet->children[0]->attr('text'));
        $t->same(4, $result['importReport']['content']['fieldCount']);
        $t->same(2, $result['importReport']['contentDeclarations']['variableDeclarationCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Ready]{.odf-field .odf-field-variable-get data-odf-field-type="variable-get" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready" data-odf-field-declared="true"}', $markdown);
        $t->contains('[12]{.odf-field .odf-field-variable-get data-odf-field-type="variable-get" data-odf-field-name="ApprovedCount" data-odf-field-value-type="float" data-odf-field-value="12" data-odf-field-declared="true"}', $markdown);
        $t->contains('<span class="odf-field odf-field-variable-get" data-odf-field-type="variable-get" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready" data-odf-field-declared="true">Ready</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-variable-get" data-odf-field-type="variable-get" data-odf-field-name="ApprovedCount" data-odf-field-value-type="float" data-odf-field-value="12" data-odf-field-declared="true">12</span>', $blocksHtml);
    },
    'maps ODT variable user page and date fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:user-field-decls>
        <text:user-field-decl text:name="Reviewer" office:value-type="string" office:string-value="Migration Desk"/>
      </text:user-field-decls>
      <text:p>Fields <text:variable-set text:name="ReviewStatus" office:value-type="string" office:string-value="Ready">Ready</text:variable-set> by <text:user-field-get text:name="Reviewer">Migration Desk</text:user-field-get> page <text:page-number text:select-page="current" text:page-adjust="1">2</text:page-number> exported <text:date text:fixed="true" text:date-value="2026-06-05">June 5, 2026</text:date>.</text:p>
      <text:h text:outline-level="2">Status <text:variable-get text:name="ReviewStatus">Ready</text:variable-get></text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFields));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $reviewStatus = $paragraph->children[1];
        $reviewer = $paragraph->children[3];
        $pageNumber = $paragraph->children[5];
        $date = $paragraph->children[7];
        $statusGet = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Fields Ready by Migration Desk page 2 exported June 5, 2026.', $paragraph->attr('text'));
        $t->same('span', $reviewStatus->type);
        $t->same(['odf-field', 'odf-field-variable-set'], $reviewStatus->attr('classes'));
        $t->same('variable-set', $reviewStatus->attr('fieldType'));
        $t->same('ReviewStatus', $reviewStatus->attr('fieldName'));
        $t->same('string', $reviewStatus->attr('fieldMetadata')['valueType']);
        $t->same('Ready', $reviewStatus->attr('fieldMetadata')['stringValue']);
        $t->same('Ready', $reviewStatus->children[0]->attr('text'));

        $t->same(['odf-field', 'odf-field-user-field-get'], $reviewer->attr('classes'));
        $t->same('Reviewer', $reviewer->attr('fieldName'));
        $t->same('Migration Desk', $reviewer->children[0]->attr('text'));
        $t->same('page-number', $pageNumber->attr('fieldType'));
        $t->same('current', $pageNumber->attr('fieldMetadata')['selectPage']);
        $t->same('1', $pageNumber->attr('fieldMetadata')['pageAdjust']);
        $t->same('2', $pageNumber->children[0]->attr('text'));
        $t->same('date', $date->attr('fieldType'));
        $t->same(true, $date->attr('fieldMetadata')['fixed']);
        $t->same('2026-06-05', $date->attr('fieldMetadata')['dateValue']);
        $t->same('June 5, 2026', $date->children[0]->attr('text'));

        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('variable-get', $statusGet->attr('fieldType'));
        $t->same('ReviewStatus', $statusGet->attr('attributes')['data-odf-field-name']);
        $t->same(5, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Ready]{.odf-field .odf-field-variable-set data-odf-field-type="variable-set" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready"}', $markdown);
        $t->contains('[2]{.odf-field .odf-field-page-number data-odf-field-type="page-number" data-odf-field-select-page="current" data-odf-field-page-adjust="1"}', $markdown);
        $t->contains('<span class="odf-field odf-field-variable-set" data-odf-field-type="variable-set" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready">Ready</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="Reviewer">Migration Desk</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-date" data-odf-field-type="date" data-odf-field-date-value="2026-06-05" data-odf-field-fixed="true">June 5, 2026</span>', $blocksHtml);
    },
    'maps ODT typed boolean and currency field values into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTypedFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:user-field-decls>
        <text:user-field-decl text:name="NeedsLegalReview" office:value-type="boolean" office:boolean-value="false"/>
      </text:user-field-decls>
      <text:p>Typed fields <text:variable-set text:name="Approved" office:value-type="boolean" office:boolean-value="true"/> and budget <text:expression text:name="ApprovedBudget" text:formula="ooow:approved-budget" office:value-type="currency" office:value="42.50" office:currency="USD"/> plus declared <text:user-field-get text:name="NeedsLegalReview"/> remain visible.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTypedFields));
        $paragraph = $result['document']->children[0];
        $approved = $paragraph->children[1];
        $budget = $paragraph->children[3];
        $needsReview = $paragraph->children[5];

        $t->same('Typed fields true and budget 42.50 plus declared false remain visible.', $paragraph->attr('text'));
        $t->same(['odf-field', 'odf-field-variable-set'], $approved->attr('classes'));
        $t->same('variable-set', $approved->attr('fieldType'));
        $t->same('Approved', $approved->attr('fieldName'));
        $t->same('boolean', $approved->attr('fieldMetadata')['valueType']);
        $t->same(true, $approved->attr('fieldMetadata')['booleanValue']);
        $t->same('true', $approved->attr('attributes')['data-odf-field-boolean-value']);
        $t->same('true', $approved->children[0]->attr('text'));

        $t->same(['odf-field', 'odf-field-expression'], $budget->attr('classes'));
        $t->same('expression', $budget->attr('fieldType'));
        $t->same('ApprovedBudget', $budget->attr('fieldName'));
        $t->same('currency', $budget->attr('fieldMetadata')['valueType']);
        $t->same('42.50', $budget->attr('fieldMetadata')['value']);
        $t->same('USD', $budget->attr('fieldMetadata')['currency']);
        $t->same('ooow:approved-budget', $budget->attr('fieldMetadata')['formula']);
        $t->same('USD', $budget->attr('attributes')['data-odf-field-currency']);
        $t->same('42.50', $budget->children[0]->attr('text'));

        $t->same(['odf-field', 'odf-field-user-field-get'], $needsReview->attr('classes'));
        $t->same('NeedsLegalReview', $needsReview->attr('fieldName'));
        $t->same(true, $needsReview->attr('fieldMetadata')['declared']);
        $t->same('boolean', $needsReview->attr('fieldMetadata')['valueType']);
        $t->same(false, $needsReview->attr('fieldMetadata')['booleanValue']);
        $t->same('false', $needsReview->attr('attributes')['data-odf-field-boolean-value']);
        $t->same('false', $needsReview->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[true]{.odf-field .odf-field-variable-set data-odf-field-type="variable-set" data-odf-field-name="Approved" data-odf-field-value-type="boolean" data-odf-field-boolean-value="true"}', $markdown);
        $t->contains('[42.50]{.odf-field .odf-field-expression data-odf-field-type="expression" data-odf-field-name="ApprovedBudget" data-odf-field-formula="ooow:approved-budget" data-odf-field-value-type="currency" data-odf-field-value="42.50" data-odf-field-currency="USD"}', $markdown);
        $t->contains('[false]{.odf-field .odf-field-user-field-get data-odf-field-type="user-field-get" data-odf-field-name="NeedsLegalReview" data-odf-field-value-type="boolean" data-odf-field-boolean-value="false" data-odf-field-declared="true"}', $markdown);
        $t->contains('<span class="odf-field odf-field-variable-set" data-odf-field-type="variable-set" data-odf-field-name="Approved" data-odf-field-value-type="boolean" data-odf-field-boolean-value="true">true</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-expression" data-odf-field-type="expression" data-odf-field-name="ApprovedBudget" data-odf-field-formula="ooow:approved-budget" data-odf-field-value-type="currency" data-odf-field-value="42.50" data-odf-field-currency="USD">42.50</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="NeedsLegalReview" data-odf-field-value-type="boolean" data-odf-field-boolean-value="false" data-odf-field-declared="true">false</span>', $blocksHtml);
    },
    'maps ODT measure fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithMeasureFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:p>Measures <text:measure text:name="ApprovedImports" text:kind="value" text:formula="ooow:COUNT([Review.B2:Review.B12])" office:value-type="float" office:value="11" style:data-style-name="ReviewInteger">11</text:measure> and fallback <text:measure text:name="SourceBudget" text:kind="unit" office:value-type="currency" office:value="42.50" office:currency="USD"/> stay reviewable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithMeasureFields));
        $paragraph = $result['document']->children[0];
        $approvedImports = $paragraph->children[1];
        $sourceBudget = $paragraph->children[3];

        $t->same('Measures 11 and fallback 42.50 stay reviewable.', $paragraph->attr('text'));
        $t->same('span', $approvedImports->type);
        $t->same(['odf-field', 'odf-field-measure'], $approvedImports->attr('classes'));
        $t->same('measure', $approvedImports->attr('fieldType'));
        $t->same('ApprovedImports', $approvedImports->attr('fieldName'));
        $t->same('value', $approvedImports->attr('fieldMetadata')['kind']);
        $t->same('ooow:COUNT([Review.B2:Review.B12])', $approvedImports->attr('fieldMetadata')['formula']);
        $t->same('float', $approvedImports->attr('fieldMetadata')['valueType']);
        $t->same('11', $approvedImports->attr('fieldMetadata')['value']);
        $t->same('ReviewInteger', $approvedImports->attr('fieldMetadata')['styleName']);
        $t->same('value', $approvedImports->attr('attributes')['data-odf-field-kind']);
        $t->same('ReviewInteger', $approvedImports->attr('attributes')['data-odf-field-style-name']);
        $t->same('11', $approvedImports->children[0]->attr('text'));

        $t->same('span', $sourceBudget->type);
        $t->same(['odf-field', 'odf-field-measure'], $sourceBudget->attr('classes'));
        $t->same('SourceBudget', $sourceBudget->attr('fieldName'));
        $t->same('unit', $sourceBudget->attr('fieldMetadata')['kind']);
        $t->same('currency', $sourceBudget->attr('fieldMetadata')['valueType']);
        $t->same('42.50', $sourceBudget->attr('fieldMetadata')['value']);
        $t->same('USD', $sourceBudget->attr('fieldMetadata')['currency']);
        $t->same('42.50', $sourceBudget->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[11]{.odf-field .odf-field-measure data-odf-field-type="measure" data-odf-field-name="ApprovedImports" data-odf-field-kind="value" data-odf-field-formula="ooow:COUNT([Review.B2:Review.B12])" data-odf-field-value-type="float" data-odf-field-value="11" data-odf-field-style-name="ReviewInteger"}', $markdown);
        $t->contains('[42.50]{.odf-field .odf-field-measure data-odf-field-type="measure" data-odf-field-name="SourceBudget" data-odf-field-kind="unit" data-odf-field-value-type="currency" data-odf-field-value="42.50" data-odf-field-currency="USD"}', $markdown);
        $t->contains('<span class="odf-field odf-field-measure" data-odf-field-type="measure" data-odf-field-name="ApprovedImports" data-odf-field-kind="value" data-odf-field-formula="ooow:COUNT([Review.B2:Review.B12])" data-odf-field-value-type="float" data-odf-field-value="11" data-odf-field-style-name="ReviewInteger">11</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-measure" data-odf-field-type="measure" data-odf-field-name="SourceBudget" data-odf-field-kind="unit" data-odf-field-value-type="currency" data-odf-field-value="42.50" data-odf-field-currency="USD">42.50</span>', $blocksHtml);
    },
    'maps ODT text and variable input fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithInputFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:user-field-decls>
        <text:user-field-decl text:name="Reviewer" office:value-type="string" office:string-value="Migration Desk"/>
      </text:user-field-decls>
      <text:p>Inputs <text:text-input text:description="Confirm source title">Imported packet title</text:text-input>, <text:variable-input text:name="ReviewStatus" office:value-type="string" office:string-value="Ready">Ready</text:variable-input>, and <text:user-field-input text:name="Reviewer">Migration Desk</text:user-field-input> remain visible.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithInputFields));
        $paragraph = $result['document']->children[0];
        $textInput = $paragraph->children[1];
        $variableInput = $paragraph->children[3];
        $userFieldInput = $paragraph->children[5];

        $t->same('Inputs Imported packet title, Ready, and Migration Desk remain visible.', $paragraph->attr('text'));
        $t->same('span', $textInput->type);
        $t->same(['odf-field', 'odf-field-text-input'], $textInput->attr('classes'));
        $t->same('text-input', $textInput->attr('fieldType'));
        $t->same('Confirm source title', $textInput->attr('fieldMetadata')['description']);
        $t->same('Imported packet title', $textInput->children[0]->attr('text'));
        $t->same('Confirm source title', $textInput->attr('attributes')['data-odf-field-description']);

        $t->same('span', $variableInput->type);
        $t->same('variable-input', $variableInput->attr('fieldType'));
        $t->same('ReviewStatus', $variableInput->attr('fieldName'));
        $t->same('string', $variableInput->attr('fieldMetadata')['valueType']);
        $t->same('Ready', $variableInput->attr('fieldMetadata')['stringValue']);
        $t->same('Ready', $variableInput->children[0]->attr('text'));

        $t->same('span', $userFieldInput->type);
        $t->same('user-field-input', $userFieldInput->attr('fieldType'));
        $t->same('Reviewer', $userFieldInput->attr('fieldName'));
        $t->same('Migration Desk', $userFieldInput->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Imported packet title]{.odf-field .odf-field-text-input data-odf-field-type="text-input" data-odf-field-description="Confirm source title"}', $markdown);
        $t->contains('[Ready]{.odf-field .odf-field-variable-input data-odf-field-type="variable-input" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready"}', $markdown);
        $t->contains('[Migration Desk]{.odf-field .odf-field-user-field-input data-odf-field-type="user-field-input" data-odf-field-name="Reviewer"}', $markdown);
        $t->contains('<span class="odf-field odf-field-text-input" data-odf-field-type="text-input" data-odf-field-description="Confirm source title">Imported packet title</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-variable-input" data-odf-field-type="variable-input" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready">Ready</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-field-input" data-odf-field-type="user-field-input" data-odf-field-name="Reviewer">Migration Desk</span>', $blocksHtml);
    },
    'maps ODT dropdown fields into selected review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDropdownFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Disposition <text:drop-down text:name="ReviewDisposition"><text:label text:value="Draft"/><text:label text:value="Ready to publish" text:current-selected="true"/><text:label text:value="Needs legal review"/></text:drop-down> with fallback <text:drop-down text:name="FallbackDisposition"><text:label text:value="Escalate"/><text:label text:value="Archive"/></text:drop-down> remains auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDropdownFields));
        $paragraph = $result['document']->children[0];
        $selected = $paragraph->children[1];
        $fallback = $paragraph->children[3];

        $t->same('Disposition Ready to publish with fallback Escalate remains auditable.', $paragraph->attr('text'));
        $t->same('span', $selected->type);
        $t->same(['odf-field', 'odf-field-drop-down'], $selected->attr('classes'));
        $t->same('drop-down', $selected->attr('fieldType'));
        $t->same('ReviewDisposition', $selected->attr('fieldName'));
        $t->same(3, $selected->attr('fieldMetadata')['labelCount']);
        $t->same('Ready to publish', $selected->attr('fieldMetadata')['selectedValue']);
        $t->same('Draft', $selected->attr('fieldMetadata')['labels'][0]['value']);
        $t->same(false, $selected->attr('fieldMetadata')['labels'][0]['selected']);
        $t->same('Ready to publish', $selected->attr('fieldMetadata')['labels'][1]['value']);
        $t->same(true, $selected->attr('fieldMetadata')['labels'][1]['selected']);
        $t->same('3', $selected->attr('attributes')['data-odf-field-label-count']);
        $t->same('Ready to publish', $selected->attr('attributes')['data-odf-field-selected-value']);
        $t->same('Ready to publish', $selected->children[0]->attr('text'));

        $t->same('drop-down', $fallback->attr('fieldType'));
        $t->same('FallbackDisposition', $fallback->attr('fieldName'));
        $t->same(2, $fallback->attr('fieldMetadata')['labelCount']);
        $t->same('Escalate', $fallback->attr('fieldMetadata')['selectedValue']);
        $t->same(false, $fallback->attr('fieldMetadata')['labels'][0]['selected']);
        $t->same('Escalate', $fallback->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Ready to publish]{.odf-field .odf-field-drop-down data-odf-field-type="drop-down" data-odf-field-name="ReviewDisposition" data-odf-field-label-count="3" data-odf-field-selected-value="Ready to publish"}', $markdown);
        $t->contains('[Escalate]{.odf-field .odf-field-drop-down data-odf-field-type="drop-down" data-odf-field-name="FallbackDisposition" data-odf-field-label-count="2" data-odf-field-selected-value="Escalate"}', $markdown);
        $t->contains('<span class="odf-field odf-field-drop-down" data-odf-field-type="drop-down" data-odf-field-name="ReviewDisposition" data-odf-field-label-count="3" data-odf-field-selected-value="Ready to publish">Ready to publish</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-drop-down" data-odf-field-type="drop-down" data-odf-field-name="FallbackDisposition" data-odf-field-label-count="2" data-odf-field-selected-value="Escalate">Escalate</span>', $blocksHtml);
    },
    'maps ODT database fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDatabaseFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Database field <text:database-display text:database-name="ImportDS" text:table-name="wp_posts" text:table-type="table" text:column-name="post_title">Imported post title</text:database-display> advanced <text:database-next text:database-name="ImportDS" text:table-name="wp_posts" text:condition="Status == &quot;ready&quot;">next record</text:database-next> row <text:database-row-number text:database-name="ImportDS" text:table-name="wp_posts" text:row-number="12"/> and source <text:database-name text:database-name="ImportDS"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDatabaseFields));
        $paragraph = $result['document']->children[0];
        $display = $paragraph->children[1];
        $next = $paragraph->children[3];
        $rowNumber = $paragraph->children[5];
        $databaseName = $paragraph->children[7];

        $t->same('Database field Imported post title advanced next record row 12 and source ImportDS.', $paragraph->attr('text'));
        $t->same('database-display', $display->attr('fieldType'));
        $t->same('ImportDS', $display->attr('fieldMetadata')['databaseName']);
        $t->same('wp_posts', $display->attr('fieldMetadata')['tableName']);
        $t->same('table', $display->attr('fieldMetadata')['tableType']);
        $t->same('post_title', $display->attr('fieldMetadata')['columnName']);
        $t->same('post_title', $display->attr('attributes')['data-odf-field-column-name']);
        $t->same('Imported post title', $display->children[0]->attr('text'));

        $t->same('database-next', $next->attr('fieldType'));
        $t->same('Status == "ready"', $next->attr('fieldMetadata')['condition']);
        $t->same('Status == "ready"', $next->attr('attributes')['data-odf-field-condition']);
        $t->same('next record', $next->children[0]->attr('text'));

        $t->same('database-row-number', $rowNumber->attr('fieldType'));
        $t->same('12', $rowNumber->attr('fieldMetadata')['rowNumber']);
        $t->same('12', $rowNumber->attr('attributes')['data-odf-field-row-number']);
        $t->same('12', $rowNumber->children[0]->attr('text'));

        $t->same('database-name', $databaseName->attr('fieldType'));
        $t->same('ImportDS', $databaseName->attr('fieldMetadata')['databaseName']);
        $t->same('ImportDS', $databaseName->children[0]->attr('text'));
        $t->same(4, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Imported post title]{.odf-field .odf-field-database-display data-odf-field-type="database-display" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-table-type="table" data-odf-field-column-name="post_title"}', $markdown);
        $t->contains('[12]{.odf-field .odf-field-database-row-number data-odf-field-type="database-row-number" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-row-number="12"}', $markdown);
        $t->contains('<span class="odf-field odf-field-database-display" data-odf-field-type="database-display" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-table-type="table" data-odf-field-column-name="post_title">Imported post title</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-database-next" data-odf-field-type="database-next" data-odf-field-condition="Status == &quot;ready&quot;" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts">next record</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-database-name" data-odf-field-type="database-name" data-odf-field-database-name="ImportDS">ImportDS</span>', $blocksHtml);
    },
    'maps ODT database range policy metadata into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDatabaseRanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:database-ranges>
        <table:database-range
          table:name="ReadyPosts"
          table:target-range-address="Review.A1:Review.D12"
          table:contains-header="true"
          table:display-filter-buttons="true"
          table:on-update-keep-styles="true"
          table:on-update-keep-size="false"
          table:has-persistent-data="false"
          table:orientation="row">
          <table:database-source-sql table:database-name="ImportDS" table:sql-statement="SELECT post_title,status,imported,total FROM wp_posts" table:parse-sql-statement="true"/>
          <table:filter table:target-range-address="Review.A1:Review.D12" table:condition-source-range-address="Criteria.A1:Criteria.B2" table:display-duplicates="false">
            <table:filter-and>
              <table:filter-condition table:field-number="2" table:data-type="text" table:value="ready" table:operator="="/>
              <table:filter-condition table:field-number="3" table:data-type="number" table:value="1" table:operator="&gt;="/>
            </table:filter-and>
          </table:filter>
          <table:sort table:case-sensitive="false" table:language="en" table:country="US" table:algorithm="alphanumeric">
            <table:sort-by table:field-number="1" table:data-type="text" table:order="ascending"/>
            <table:sort-by table:field-number="2" table:data-type="text" table:order="descending"/>
          </table:sort>
          <table:subtotal-rules table:bind-styles-to-content="true" table:case-sensitive="false" table:page-breaks-on-group-change="true">
            <table:sort-groups table:case-sensitive="true">
              <table:sort-by table:field-number="2" table:data-type="text" table:order="ascending"/>
            </table:sort-groups>
            <table:subtotal-rule table:group-by-field-number="2">
              <table:subtotal-field table:field-number="3" table:function="count"/>
              <table:subtotal-field table:field-number="4" table:function="sum"/>
            </table:subtotal-rule>
          </table:subtotal-rules>
        </table:database-range>
        <table:database-range table:name="ArchiveLookup" table:target-range-address="Archive.A1:Archive.B8" table:is-selection="true">
          <table:database-source-table table:database-name="ArchiveDS" table:table-name="wp_postmeta"/>
        </table:database-range>
      </table:database-ranges>
      <text:p>Database ranges stay metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDatabaseRanges));
        $declarations = $result['contentDeclarations'];
        $ranges = is_array($declarations['databaseRanges'] ?? null) ? $declarations['databaseRanges'] : [];
        $rangesByName = is_array($declarations['databaseRangesByName'] ?? null) ? $declarations['databaseRangesByName'] : [];
        $ready = is_array($rangesByName['ReadyPosts'] ?? null) ? $rangesByName['ReadyPosts'] : [];
        $archive = is_array($rangesByName['ArchiveLookup'] ?? null) ? $rangesByName['ArchiveLookup'] : [];
        $filterGroup = is_array($ready['filter']['conditions'][0] ?? null) ? $ready['filter']['conditions'][0] : [];
        $filterConditions = is_array($filterGroup['conditions'] ?? null) ? $filterGroup['conditions'] : [];
        $subtotal = is_array($ready['subtotalRules'] ?? null) ? $ready['subtotalRules'] : [];
        $subtotalSortGroups = is_array($subtotal['sortGroups'] ?? null) ? $subtotal['sortGroups'] : [];
        $subtotalRules = is_array($subtotal['rules'] ?? null) ? $subtotal['rules'] : [];
        $subtotalFields = is_array($subtotalRules[0]['fields'] ?? null) ? $subtotalRules[0]['fields'] : [];

        $t->same(2, $declarations['databaseRangeCount'] ?? null);
        $t->same(2, count($ranges));
        $t->same('ReadyPosts', $ready['name'] ?? null);
        $t->same('Review.A1:Review.D12', $ready['targetRangeAddress'] ?? null);
        $t->same(true, $ready['containsHeader'] ?? null);
        $t->same(true, $ready['displayFilterButtons'] ?? null);
        $t->same(true, $ready['onUpdateKeepStyles'] ?? null);
        $t->same(false, $ready['onUpdateKeepSize'] ?? null);
        $t->same(false, $ready['hasPersistentData'] ?? null);
        $t->same('row', $ready['orientation'] ?? null);
        $t->same('sql', $ready['source']['type'] ?? null);
        $t->same('ImportDS', $ready['source']['databaseName'] ?? null);
        $t->same('SELECT post_title,status,imported,total FROM wp_posts', $ready['source']['sqlStatement'] ?? null);
        $t->same(true, $ready['source']['parseSqlStatement'] ?? null);
        $t->same('Review.A1:Review.D12', $ready['filter']['targetRangeAddress'] ?? null);
        $t->same('Criteria.A1:Criteria.B2', $ready['filter']['conditionSourceRangeAddress'] ?? null);
        $t->same(false, $ready['filter']['displayDuplicates'] ?? null);
        $t->same('and', $filterGroup['type'] ?? null);
        $t->same(2, count($filterConditions));
        $t->same(2, $filterConditions[0]['fieldNumber'] ?? null);
        $t->same('ready', $filterConditions[0]['value'] ?? null);
        $t->same('>=', $filterConditions[1]['operator'] ?? null);
        $t->same(false, $ready['sort']['caseSensitive'] ?? null);
        $t->same('alphanumeric', $ready['sort']['algorithm'] ?? null);
        $t->same(2, count($ready['sort']['sortBy'] ?? []));
        $t->same('descending', $ready['sort']['sortBy'][1]['order'] ?? null);
        $t->same(true, $subtotal['bindStylesToContent'] ?? null);
        $t->same(false, $subtotal['caseSensitive'] ?? null);
        $t->same(true, $subtotal['pageBreaksOnGroupChange'] ?? null);
        $t->same(1, $subtotal['ruleCount'] ?? null);
        $t->same(2, $subtotal['fieldCount'] ?? null);
        $t->same(true, $subtotalSortGroups['caseSensitive'] ?? null);
        $t->same(1, $subtotalSortGroups['sortFieldCount'] ?? null);
        $t->same(2, $subtotalSortGroups['sortBy'][0]['fieldNumber'] ?? null);
        $t->same('ascending', $subtotalSortGroups['sortBy'][0]['order'] ?? null);
        $t->same(2, $subtotalRules[0]['groupByFieldNumber'] ?? null);
        $t->same(2, $subtotalRules[0]['fieldCount'] ?? null);
        $t->same(3, $subtotalFields[0]['fieldNumber'] ?? null);
        $t->same('count', $subtotalFields[0]['function'] ?? null);
        $t->same(4, $subtotalFields[1]['fieldNumber'] ?? null);
        $t->same('sum', $subtotalFields[1]['function'] ?? null);
        $t->same(true, $archive['isSelection'] ?? null);
        $t->same('table', $archive['source']['type'] ?? null);
        $t->same('ArchiveDS', $archive['source']['databaseName'] ?? null);
        $t->same('wp_postmeta', $archive['source']['tableName'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(2, $result['importReport']['contentDeclarations']['databaseRangeCount'] ?? null);
        $t->same(1, $result['importReport']['contentDeclarations']['databaseSubtotalRuleCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['databaseSubtotalFieldCount'] ?? null);
        $t->same(2, $result['importReport']['content']['databaseRangeCount'] ?? null);
        $t->same(1, $result['importReport']['content']['databaseSubtotalRuleCount'] ?? null);
        $t->same(2, $result['importReport']['content']['databaseSubtotalFieldCount'] ?? null);
        $t->same('Database ranges stay metadata-only.', $result['document']->children[0]->attr('text'));
    },
    'maps ODT data pilot tables into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDataPilotTables = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:data-pilot-tables>
        <table:data-pilot-table
          table:name="ReadyPostPivot"
          table:application-data="wp-import-review"
          table:target-range-address="Pivot.A1:Pivot.D8"
          table:buttons="true"
          table:show-filter-button="true"
          table:grand-total="both"
          table:ignore-empty-rows="true"
          table:identify-categories="false">
          <table:source-cell-range table:cell-range-address="Review.A1:Review.D12"/>
          <table:data-pilot-field table:source-field-name="status" table:orientation="row" table:used-hierarchy="1">
            <table:data-pilot-level table:show-empty="false" table:repeat-item-labels="true">
              <table:data-pilot-subtotals>
                <table:data-pilot-subtotal table:function="count"/>
                <table:data-pilot-subtotal table:function="sum"/>
              </table:data-pilot-subtotals>
              <table:data-pilot-members>
                <table:data-pilot-member table:name="ready" table:display="true" table:show-details="true"/>
                <table:data-pilot-member table:name="draft" table:display="false"/>
              </table:data-pilot-members>
            </table:data-pilot-level>
          </table:data-pilot-field>
          <table:data-pilot-field table:source-field-name="total" table:orientation="data" table:function="sum"/>
        </table:data-pilot-table>
        <table:data-pilot-table table:name="ServicePivot" table:target-range-address="Pivot.F1:Pivot.H4">
          <table:source-service table:name="ExternalImport" table:source-name="reports" table:object-name="ApprovedPosts" table:user-name="importer" table:password="do-not-expose"/>
        </table:data-pilot-table>
        <table:data-pilot-table table:target-range-address="Ignored.A1:A2">
          <table:source-cell-range table:cell-range-address="Ignored.A1:A2"/>
        </table:data-pilot-table>
      </table:data-pilot-tables>
      <text:p>Data pilot tables stay metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDataPilotTables));
        $declarations = $result['contentDeclarations'];
        $tables = is_array($declarations['dataPilotTables'] ?? null) ? $declarations['dataPilotTables'] : [];
        $tablesByName = is_array($declarations['dataPilotTablesByName'] ?? null) ? $declarations['dataPilotTablesByName'] : [];
        $ready = is_array($tablesByName['ReadyPostPivot'] ?? null) ? $tablesByName['ReadyPostPivot'] : [];
        $service = is_array($tablesByName['ServicePivot'] ?? null) ? $tablesByName['ServicePivot'] : [];
        $readyFields = is_array($ready['fields'] ?? null) ? $ready['fields'] : [];
        $statusField = is_array($readyFields[0] ?? null) ? $readyFields[0] : [];
        $statusLevel = is_array($statusField['levels'][0] ?? null) ? $statusField['levels'][0] : [];
        $subtotals = is_array($statusLevel['subtotals'] ?? null) ? $statusLevel['subtotals'] : [];
        $members = is_array($statusLevel['members'] ?? null) ? $statusLevel['members'] : [];
        $totalField = is_array($readyFields[1] ?? null) ? $readyFields[1] : [];
        $serviceSource = is_array($service['source'] ?? null) ? $service['source'] : [];

        $t->same(2, $declarations['dataPilotTableCount']);
        $t->same(2, count($tables));
        $t->same(['ReadyPostPivot', 'ServicePivot'], array_column($tables, 'name'));
        $t->same(2, $declarations['dataPilotFieldCount']);
        $t->same(2, $declarations['dataPilotSubtotalCount']);
        $t->same(2, $declarations['dataPilotMemberCount']);
        $t->same('wp-import-review', $ready['applicationData'] ?? null);
        $t->same('Pivot.A1:Pivot.D8', $ready['targetRangeAddress'] ?? null);
        $t->same(true, $ready['buttons'] ?? null);
        $t->same(true, $ready['showFilterButton'] ?? null);
        $t->same('both', $ready['grandTotal'] ?? null);
        $t->same(true, $ready['ignoreEmptyRows'] ?? null);
        $t->same(false, $ready['identifyCategories'] ?? null);
        $t->same('cell-range', $ready['source']['type'] ?? null);
        $t->same('Review.A1:Review.D12', $ready['source']['cellRangeAddress'] ?? null);
        $t->same(2, $ready['fieldCount'] ?? null);
        $t->same('status', $statusField['sourceFieldName'] ?? null);
        $t->same('row', $statusField['orientation'] ?? null);
        $t->same(1, $statusField['usedHierarchy'] ?? null);
        $t->same(1, $statusField['levelCount'] ?? null);
        $t->same(false, $statusLevel['showEmpty'] ?? null);
        $t->same(true, $statusLevel['repeatItemLabels'] ?? null);
        $t->same(2, $statusLevel['subtotalCount'] ?? null);
        $t->same('count', $subtotals[0]['function'] ?? null);
        $t->same('sum', $subtotals[1]['function'] ?? null);
        $t->same(2, $statusLevel['memberCount'] ?? null);
        $t->same('ready', $members[0]['name'] ?? null);
        $t->same(true, $members[0]['display'] ?? null);
        $t->same(true, $members[0]['showDetails'] ?? null);
        $t->same('draft', $members[1]['name'] ?? null);
        $t->same(false, $members[1]['display'] ?? null);
        $t->same('total', $totalField['sourceFieldName'] ?? null);
        $t->same('data', $totalField['orientation'] ?? null);
        $t->same('sum', $totalField['function'] ?? null);
        $t->same('service', $serviceSource['type'] ?? null);
        $t->same('ExternalImport', $serviceSource['name'] ?? null);
        $t->same('reports', $serviceSource['sourceName'] ?? null);
        $t->same('ApprovedPosts', $serviceSource['objectName'] ?? null);
        $t->same('importer', $serviceSource['userName'] ?? null);
        $t->same(true, $serviceSource['passwordPresent'] ?? null);
        $t->true(!isset($serviceSource['password']), 'ODF data-pilot source-service passwords must not be exposed');
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotTableCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotFieldCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotSubtotalCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotMemberCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotTableCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotFieldCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotSubtotalCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotMemberCount'] ?? null);
        $t->same('Data pilot tables stay metadata-only.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Data pilot tables stay metadata-only.', $markdown);
        $t->contains('<p>Data pilot tables stay metadata-only.</p>', $blocksHtml);
    },
    'maps ODT data pilot field display sort layout and reference metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDataPilotFieldPolicy = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:data-pilot-tables>
        <table:data-pilot-table table:name="ReviewPivot" table:target-range-address="Pivot.A1:Pivot.E12" table:grand-total="both">
          <table:source-cell-range table:cell-range-address="Review.A1:Review.E42"/>
          <table:data-pilot-field table:source-field-name="status" table:orientation="row" table:used-hierarchy="1" table:selected-page="ready">
            <table:data-pilot-display-info table:enabled="true" table:display-member-mode="from-top" table:member-count="5" table:data-field="total"/>
            <table:data-pilot-sort-info table:order="descending" table:sort-mode="data" table:data-field="total"/>
            <table:data-pilot-layout-info table:layout-mode="outline-subtotals-top" table:add-empty-lines="true"/>
            <table:data-pilot-field-reference table:type="item-difference" table:field-name="previous_status" table:member-type="named" table:member-name="draft"/>
          </table:data-pilot-field>
          <table:data-pilot-field table:source-field-name="month" table:orientation="column">
            <table:data-pilot-display-info table:enabled="false" table:display-member-mode="from-bottom" table:member-count="3"/>
            <table:data-pilot-sort-info table:order="ascending" table:sort-mode="name"/>
            <table:data-pilot-layout-info table:layout-mode="tabular-layout" table:add-empty-lines="false"/>
          </table:data-pilot-field>
        </table:data-pilot-table>
      </table:data-pilot-tables>
      <text:p>Pivot field policy stays metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDataPilotFieldPolicy));
        $declarations = $result['contentDeclarations'];
        $tablesByName = is_array($declarations['dataPilotTablesByName'] ?? null) ? $declarations['dataPilotTablesByName'] : [];
        $reviewPivot = is_array($tablesByName['ReviewPivot'] ?? null) ? $tablesByName['ReviewPivot'] : [];
        $fields = is_array($reviewPivot['fields'] ?? null) ? $reviewPivot['fields'] : [];
        $statusField = is_array($fields[0] ?? null) ? $fields[0] : [];
        $monthField = is_array($fields[1] ?? null) ? $fields[1] : [];

        $t->same(1, $declarations['dataPilotTableCount']);
        $t->same(2, $declarations['dataPilotFieldCount']);
        $t->same(2, $declarations['dataPilotDisplayInfoCount']);
        $t->same(2, $declarations['dataPilotSortInfoCount']);
        $t->same(2, $declarations['dataPilotLayoutInfoCount']);
        $t->same(1, $declarations['dataPilotFieldReferenceCount']);
        $t->same('both', $reviewPivot['grandTotal'] ?? null);
        $t->same('ready', $statusField['selectedPage'] ?? null);
        $t->same(true, $statusField['displayInfo']['enabled'] ?? null);
        $t->same('from-top', $statusField['displayInfo']['displayMemberMode'] ?? null);
        $t->same(5, $statusField['displayInfo']['memberCount'] ?? null);
        $t->same('total', $statusField['displayInfo']['dataField'] ?? null);
        $t->same('descending', $statusField['sortInfo']['order'] ?? null);
        $t->same('data', $statusField['sortInfo']['sortMode'] ?? null);
        $t->same('total', $statusField['sortInfo']['dataField'] ?? null);
        $t->same('outline-subtotals-top', $statusField['layoutInfo']['layoutMode'] ?? null);
        $t->same(true, $statusField['layoutInfo']['addEmptyLines'] ?? null);
        $t->same('item-difference', $statusField['fieldReference']['type'] ?? null);
        $t->same('previous_status', $statusField['fieldReference']['fieldName'] ?? null);
        $t->same('named', $statusField['fieldReference']['memberType'] ?? null);
        $t->same('draft', $statusField['fieldReference']['memberName'] ?? null);
        $t->same(false, $monthField['displayInfo']['enabled'] ?? null);
        $t->same('from-bottom', $monthField['displayInfo']['displayMemberMode'] ?? null);
        $t->same(3, $monthField['displayInfo']['memberCount'] ?? null);
        $t->same('ascending', $monthField['sortInfo']['order'] ?? null);
        $t->same('name', $monthField['sortInfo']['sortMode'] ?? null);
        $t->same('tabular-layout', $monthField['layoutInfo']['layoutMode'] ?? null);
        $t->same(false, $monthField['layoutInfo']['addEmptyLines'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotDisplayInfoCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotSortInfoCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotLayoutInfoCount'] ?? null);
        $t->same(1, $result['importReport']['contentDeclarations']['dataPilotFieldReferenceCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotDisplayInfoCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotSortInfoCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotLayoutInfoCount'] ?? null);
        $t->same(1, $result['importReport']['content']['dataPilotFieldReferenceCount'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same('Pivot field policy stays metadata-only.', $result['document']->children[0]->attr('text'));
    },
    'maps ODT data pilot grouping metadata into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDataPilotGroups = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:data-pilot-tables>
        <table:data-pilot-table table:name="GroupedPivot" table:target-range-address="Pivot.A1:Pivot.E12">
          <table:source-cell-range table:cell-range-address="Review.A1:Review.E42"/>
          <table:data-pilot-field table:source-field-name="status" table:orientation="row"/>
          <table:data-pilot-groups table:source-field-name="created_at" table:grouped-by="month" table:date-start="2026-01-01" table:date-end="2026-03-31" table:step="3">
            <table:data-pilot-group table:name="Quarter 1">
              <table:data-pilot-group-member table:name="January" table:display="true"/>
              <table:data-pilot-group-member table:name="February" table:display="false"/>
            </table:data-pilot-group>
          </table:data-pilot-groups>
          <table:data-pilot-groups table:source-field-name="status">
            <table:data-pilot-group table:name="Ready states">
              <table:data-pilot-group-member table:name="ready"/>
            </table:data-pilot-group>
          </table:data-pilot-groups>
        </table:data-pilot-table>
      </table:data-pilot-tables>
      <text:p>Data pilot grouping policy stays metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDataPilotGroups));
        $declarations = $result['contentDeclarations'];
        $tablesByName = is_array($declarations['dataPilotTablesByName'] ?? null) ? $declarations['dataPilotTablesByName'] : [];
        $pivot = is_array($tablesByName['GroupedPivot'] ?? null) ? $tablesByName['GroupedPivot'] : [];
        $groups = is_array($pivot['groups'] ?? null) ? $pivot['groups'] : [];
        $quarter = is_array($groups[0] ?? null) ? $groups[0] : [];
        $ready = is_array($groups[1] ?? null) ? $groups[1] : [];
        $quarterMembers = is_array($quarter['members'] ?? null) ? $quarter['members'] : [];

        $t->same(1, $declarations['dataPilotTableCount'] ?? null);
        $t->same(2, $declarations['dataPilotGroupCount'] ?? null);
        $t->same(3, $declarations['dataPilotGroupMemberCount'] ?? null);
        $t->same(2, $pivot['groupCount'] ?? null);
        $t->same('data-pilot-group', $quarter['element'] ?? null);
        $t->same('Quarter 1', $quarter['name'] ?? null);
        $t->same('created_at', $quarter['sourceFieldName'] ?? null);
        $t->same('month', $quarter['groupedBy'] ?? null);
        $t->same('2026-01-01', $quarter['dateStart'] ?? null);
        $t->same('2026-03-31', $quarter['dateEnd'] ?? null);
        $t->same(3, $quarter['step'] ?? null);
        $t->same(2, $quarter['memberCount'] ?? null);
        $t->same('data-pilot-group-member', $quarterMembers[0]['element'] ?? null);
        $t->same('January', $quarterMembers[0]['name'] ?? null);
        $t->same(true, $quarterMembers[0]['display'] ?? null);
        $t->same('February', $quarterMembers[1]['name'] ?? null);
        $t->same(false, $quarterMembers[1]['display'] ?? null);
        $t->same('Ready states', $ready['name'] ?? null);
        $t->same(1, $ready['memberCount'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(2, $result['importReport']['contentDeclarations']['dataPilotGroupCount'] ?? null);
        $t->same(3, $result['importReport']['contentDeclarations']['dataPilotGroupMemberCount'] ?? null);
        $t->same(2, $result['importReport']['content']['dataPilotGroupCount'] ?? null);
        $t->same(3, $result['importReport']['content']['dataPilotGroupMemberCount'] ?? null);
        $t->same('Data pilot grouping policy stays metadata-only.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Data pilot grouping policy stays metadata-only.', $markdown);
        $t->contains('<p>Data pilot grouping policy stays metadata-only.</p>', $blocksHtml);
    },
    'maps ODT named ranges and expressions into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNamedExpressions = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:named-expressions>
        <table:named-range table:name="ImportRows" table:cell-range-address="Review.A2:Review.D42" table:base-cell-address="Review.A1" table:range-usable-as="print-range filter"/>
        <table:named-expression table:name="ReadyPostCount" table:expression="of:=COUNTIF([.B2:.B42];&quot;ready&quot;)" table:base-cell-address="Review.A1"/>
        <table:named-range table:name="SourceTitles" table:cell-range-address="Review.A2:Review.A42"/>
        <table:named-expression table:expression="of:=SUM([.D2:.D42])"/>
      </table:named-expressions>
      <text:p>Named expressions stay metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNamedExpressions));
        $declarations = $result['contentDeclarations'];
        $byName = is_array($declarations['namedExpressionsByName'] ?? null) ? $declarations['namedExpressionsByName'] : [];
        $rows = $byName['ImportRows'] ?? [];
        $ready = $byName['ReadyPostCount'] ?? [];
        $titles = $byName['SourceTitles'] ?? [];

        $t->same(3, $declarations['namedExpressionCount']);
        $t->same(2, $declarations['namedRangeCount']);
        $t->same(1, $declarations['namedFormulaExpressionCount']);
        $t->same(['ImportRows', 'ReadyPostCount', 'SourceTitles'], array_column($declarations['namedExpressions'], 'name'));
        $t->same('range', $rows['type'] ?? null);
        $t->same('named-range', $rows['element'] ?? null);
        $t->same('Review.A2:Review.D42', $rows['cellRangeAddress'] ?? null);
        $t->same('Review.A1', $rows['baseCellAddress'] ?? null);
        $t->same('print-range filter', $rows['rangeUsableAs'] ?? null);
        $t->same('expression', $ready['type'] ?? null);
        $t->same('named-expression', $ready['element'] ?? null);
        $t->same('of:=COUNTIF([.B2:.B42];"ready")', $ready['expression'] ?? null);
        $t->same('Review.A1', $ready['baseCellAddress'] ?? null);
        $t->same('Review.A2:Review.A42', $titles['cellRangeAddress'] ?? null);
        $t->true(!isset($byName['']), 'Unnamed ODT named-expression entries should be skipped');
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(3, $result['importReport']['contentDeclarations']['namedExpressionCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['namedRangeCount'] ?? null);
        $t->same(1, $result['importReport']['contentDeclarations']['namedFormulaExpressionCount'] ?? null);
        $t->same(3, $result['importReport']['content']['namedExpressionCount'] ?? null);
        $t->same(2, $result['importReport']['content']['namedRangeCount'] ?? null);
        $t->same(1, $result['importReport']['content']['namedFormulaExpressionCount'] ?? null);
        $t->same('Named expressions stay metadata-only.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Named expressions stay metadata-only.', $markdown);
        $t->contains('<p>Named expressions stay metadata-only.</p>', $blocksHtml);
    },
    'reports duplicate ODT named range names without hiding source declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDuplicateNamedExpressions = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:named-expressions>
        <table:named-range table:name="ReviewRows" table:cell-range-address="Review.A2:Review.D42" table:base-cell-address="Review.A1" table:range-usable-as="filter"/>
        <table:named-expression table:name="ReviewRows" table:expression="of:=COUNTIF([.B2:.B42];&quot;ready&quot;)" table:base-cell-address="Review.A1"/>
        <table:named-expression table:name="ReviewTotal" table:expression="of:=SUM([.D2:.D42])" table:base-cell-address="Review.A1"/>
        <table:named-range table:name="ReviewRows" table:cell-range-address="Archive.A2:Archive.D8" table:base-cell-address="Archive.A1"/>
      </table:named-expressions>
      <text:p>Duplicate named expressions stay reviewable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDuplicateNamedExpressions));
        $declarations = $result['contentDeclarations'];
        $byName = is_array($declarations['namedExpressionsByName'] ?? null) ? $declarations['namedExpressionsByName'] : [];

        $t->same(4, $declarations['namedExpressionCount'] ?? null);
        $t->same(2, $declarations['namedRangeCount'] ?? null);
        $t->same(2, $declarations['namedFormulaExpressionCount'] ?? null);
        $t->same(['ReviewRows', 'ReviewRows', 'ReviewTotal', 'ReviewRows'], array_column($declarations['namedExpressions'], 'name'));
        $t->same('Archive.A2:Archive.D8', $byName['ReviewRows']['cellRangeAddress'] ?? null);
        $t->same(['ReviewRows' => 3, 'ReviewTotal' => 1], $declarations['namedExpressionNameOccurrences'] ?? null);
        $t->same(1, $declarations['namedExpressionDuplicateNameCount'] ?? null);
        $t->same(2, $declarations['namedExpressionDuplicateEntryCount'] ?? null);
        $t->same(['ReviewRows'], $declarations['namedExpressionDuplicateNames'] ?? null);
        $t->same(1, $result['importReport']['contentDeclarations']['namedExpressionDuplicateNameCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['namedExpressionDuplicateEntryCount'] ?? null);
        $t->same(['ReviewRows'], $result['importReport']['contentDeclarations']['namedExpressionDuplicateNames'] ?? null);
        $t->same(1, $result['importReport']['content']['namedExpressionDuplicateNameCount'] ?? null);
        $t->same(2, $result['importReport']['content']['namedExpressionDuplicateEntryCount'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same('Duplicate named expressions stay reviewable.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Duplicate named expressions stay reviewable.', $markdown);
        $t->contains('<p>Duplicate named expressions stay reviewable.</p>', $blocksHtml);
    },
    'maps ODT label ranges into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLabelRanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:label-ranges>
        <table:label-range table:label-cell-range-address="Review.A1:Review.D1" table:data-cell-range-address="Review.A2:Review.D42" table:orientation="column"/>
        <table:label-range table:label-cell-range-address="Review.A2:Review.A42" table:data-cell-range-address="Review.B2:Review.D42" table:orientation="row"/>
        <table:label-range/>
      </table:label-ranges>
      <text:p>Label ranges stay metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLabelRanges));
        $declarations = $result['contentDeclarations'];
        $labelRanges = is_array($declarations['labelRanges'] ?? null) ? $declarations['labelRanges'] : [];
        $orientationCounts = is_array($declarations['labelRangeOrientationCounts'] ?? null) ? $declarations['labelRangeOrientationCounts'] : [];

        $t->same(2, $declarations['labelRangeCount'] ?? null);
        $t->same(2, count($labelRanges));
        $t->same('Review.A1:Review.D1', $labelRanges[0]['labelCellRangeAddress'] ?? null);
        $t->same('Review.A2:Review.D42', $labelRanges[0]['dataCellRangeAddress'] ?? null);
        $t->same('column', $labelRanges[0]['orientation'] ?? null);
        $t->same('Review.A2:Review.A42', $labelRanges[1]['labelCellRangeAddress'] ?? null);
        $t->same('Review.B2:Review.D42', $labelRanges[1]['dataCellRangeAddress'] ?? null);
        $t->same('row', $labelRanges[1]['orientation'] ?? null);
        $t->same(['column' => 1, 'row' => 1], $orientationCounts);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(2, $result['importReport']['contentDeclarations']['labelRangeCount'] ?? null);
        $t->same(['column' => 1, 'row' => 1], $result['importReport']['contentDeclarations']['labelRangeOrientationCounts'] ?? null);
        $t->same(2, $result['importReport']['content']['labelRangeCount'] ?? null);
        $t->same('Label ranges stay metadata-only.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Label ranges stay metadata-only.', $markdown);
        $t->contains('<p>Label ranges stay metadata-only.</p>', $blocksHtml);
    },
    'maps ODT calculation settings into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithCalculationSettings = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:calculation-settings
        table:case-sensitive="true"
        table:precision-as-shown="false"
        table:search-criteria-must-apply-to-whole-cell="true"
        table:automatic-find-labels="true"
        table:use-regular-expressions="false"
        table:use-wildcards="true"
        table:null-year="1930"
        table:iteration="true"
        table:iteration-count="75"
        table:iteration-tolerance="0.0001"/>
      <text:p>Calculation settings stay metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithCalculationSettings));
        $declarations = $result['contentDeclarations'];
        $settings = is_array($declarations['calculationSettings'] ?? null) ? $declarations['calculationSettings'] : [];

        $t->same(1, $declarations['calculationSettingCount'] ?? null);
        $t->same(true, $settings['caseSensitive'] ?? null);
        $t->same(false, $settings['precisionAsShown'] ?? null);
        $t->same(true, $settings['searchCriteriaMustApplyToWholeCell'] ?? null);
        $t->same(true, $settings['automaticFindLabels'] ?? null);
        $t->same(false, $settings['useRegularExpressions'] ?? null);
        $t->same(true, $settings['useWildcards'] ?? null);
        $t->same(1930, $settings['nullYear'] ?? null);
        $t->same(true, $settings['iteration'] ?? null);
        $t->same(75, $settings['iterationCount'] ?? null);
        $t->same('0.0001', $settings['iterationTolerance'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(1, $result['importReport']['contentDeclarations']['calculationSettingCount'] ?? null);
        $t->same('0.0001', $result['importReport']['contentDeclarations']['calculationSettings']['iterationTolerance'] ?? null);
        $t->same(1, $result['importReport']['content']['calculationSettingCount'] ?? null);
        $t->same('Calculation settings stay metadata-only.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Calculation settings stay metadata-only.', $markdown);
        $t->contains('<p>Calculation settings stay metadata-only.</p>', $blocksHtml);
    },
    'maps ODT consolidation declarations into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithConsolidations = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:consolidation
        table:function="sum"
        table:source-cell-range-addresses="Review.A2:Review.D12 Archive.A2:Archive.D12"
        table:target-cell-address="Summary.A2"
        table:use-labels="column row"
        table:link-to-source-data="true"/>
      <table:consolidation
        table:function="count"
        table:target-cell-address="Summary.F2"
        table:link-to-source-data="false"/>
      <text:p>Consolidations stay metadata-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithConsolidations));
        $declarations = $result['contentDeclarations'];
        $consolidations = is_array($declarations['consolidations'] ?? null) ? $declarations['consolidations'] : [];
        $sum = is_array($consolidations[0] ?? null) ? $consolidations[0] : [];
        $count = is_array($consolidations[1] ?? null) ? $consolidations[1] : [];

        $t->same(2, $declarations['consolidationCount'] ?? null);
        $t->same(2, $declarations['consolidationSourceRangeCount'] ?? null);
        $t->same(2, count($consolidations));
        $t->same('sum', $sum['function'] ?? null);
        $t->same('Review.A2:Review.D12 Archive.A2:Archive.D12', $sum['sourceCellRangeAddressesRaw'] ?? null);
        $t->same(['Review.A2:Review.D12', 'Archive.A2:Archive.D12'], $sum['sourceCellRangeAddresses'] ?? null);
        $t->same(2, $sum['sourceRangeCount'] ?? null);
        $t->same('Summary.A2', $sum['targetCellAddress'] ?? null);
        $t->same('column row', $sum['useLabels'] ?? null);
        $t->same(true, $sum['linkToSourceData'] ?? null);
        $t->same('count', $count['function'] ?? null);
        $t->same('Summary.F2', $count['targetCellAddress'] ?? null);
        $t->same(false, $count['linkToSourceData'] ?? null);
        $t->same($declarations, $result['document']->attr('contentDeclarations'));
        $t->same(2, $result['importReport']['contentDeclarations']['consolidationCount'] ?? null);
        $t->same(2, $result['importReport']['contentDeclarations']['consolidationSourceRangeCount'] ?? null);
        $t->same(2, $result['importReport']['content']['consolidationCount'] ?? null);
        $t->same(2, $result['importReport']['content']['consolidationSourceRangeCount'] ?? null);
        $t->same('Consolidations stay metadata-only.', $result['document']->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Consolidations stay metadata-only.', $markdown);
        $t->contains('<p>Consolidations stay metadata-only.</p>', $blocksHtml);
    },
    'maps ODT source metadata fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSourceMetadataFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:p>Metadata <text:title>Source Packet</text:title> by <text:author-name text:fixed="true">Migration Desk</text:author-name> created <text:creation-date text:date-value="2026-06-05">June 5, 2026</text:creation-date> at <text:creation-time text:time-value="PT09H30M00S">09:30</text:creation-time> revised <text:modification-date text:date-value="2026-06-06">June 6, 2026</text:modification-date> keywords <text:keywords>odt, review</text:keywords>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSourceMetadataFields));
        $paragraph = $result['document']->children[0];
        $title = $paragraph->children[1];
        $author = $paragraph->children[3];
        $creationDate = $paragraph->children[5];
        $creationTime = $paragraph->children[7];
        $modificationDate = $paragraph->children[9];
        $keywords = $paragraph->children[11];

        $t->same('Metadata Source Packet by Migration Desk created June 5, 2026 at 09:30 revised June 6, 2026 keywords odt, review.', $paragraph->attr('text'));
        $t->same('span', $title->type);
        $t->same(['odf-field', 'odf-field-title'], $title->attr('classes'));
        $t->same('title', $title->attr('fieldType'));
        $t->same('title', $title->attr('attributes')['data-odf-field-type']);
        $t->same('Source Packet', $title->children[0]->attr('text'));

        $t->same('author-name', $author->attr('fieldType'));
        $t->same(true, $author->attr('fieldMetadata')['fixed']);
        $t->same('true', $author->attr('attributes')['data-odf-field-fixed']);
        $t->same('Migration Desk', $author->children[0]->attr('text'));

        $t->same('creation-date', $creationDate->attr('fieldType'));
        $t->same('2026-06-05', $creationDate->attr('fieldMetadata')['dateValue']);
        $t->same('2026-06-05', $creationDate->attr('attributes')['data-odf-field-date-value']);
        $t->same('June 5, 2026', $creationDate->children[0]->attr('text'));

        $t->same('creation-time', $creationTime->attr('fieldType'));
        $t->same('PT09H30M00S', $creationTime->attr('fieldMetadata')['timeValue']);
        $t->same('PT09H30M00S', $creationTime->attr('attributes')['data-odf-field-time-value']);
        $t->same('09:30', $creationTime->children[0]->attr('text'));

        $t->same('modification-date', $modificationDate->attr('fieldType'));
        $t->same('2026-06-06', $modificationDate->attr('fieldMetadata')['dateValue']);
        $t->same('keywords', $keywords->attr('fieldType'));
        $t->same('odt, review', $keywords->children[0]->attr('text'));
        $t->same(6, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Source Packet]{.odf-field .odf-field-title data-odf-field-type="title"}', $markdown);
        $t->contains('[June 5, 2026]{.odf-field .odf-field-creation-date data-odf-field-type="creation-date" data-odf-field-date-value="2026-06-05"}', $markdown);
        $t->contains('<span class="odf-field odf-field-title" data-odf-field-type="title">Source Packet</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-author-name" data-odf-field-type="author-name" data-odf-field-fixed="true">Migration Desk</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-creation-time" data-odf-field-type="creation-time" data-odf-field-time-value="PT09H30M00S">09:30</span>', $blocksHtml);
    },
    'maps empty ODT source metadata fields from meta xml into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithEmptyMetadataFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Metadata <text:title/> by <text:author-name/> subject <text:subject/> keywords <text:keywords/> created <text:creation-date/> modified <text:modification-date/> printed <text:printed-by/> custom <text:user-defined text:name="wp-source-id"/> approved <text:user-defined text:name="approved"/> template <text:template-name/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $metaWithFallbackMetadata = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:meta>
    <dc:title>Source Packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:subject>Review packet</dc:subject>
    <meta:keyword>odt</meta:keyword>
    <meta:keyword>review</meta:keyword>
    <meta:creation-date>2026-06-05</meta:creation-date>
    <meta:modification-date>2026-06-06</meta:modification-date>
    <meta:printed-by>Migration Printer</meta:printed-by>
    <meta:template xlink:href="Templates/import-review.ott" xlink:title="Import Review Template"/>
    <meta:user-defined meta:name="wp-source-id" meta:value-type="string">packet-42</meta:user-defined>
    <meta:user-defined meta:name="approved" meta:value-type="boolean" meta:boolean-value="true"/>
  </office:meta>
</office:document-meta>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithEmptyMetadataFields, null, null, $metaWithFallbackMetadata));
        $paragraph = $result['document']->children[0];
        $title = $paragraph->children[1];
        $author = $paragraph->children[3];
        $subject = $paragraph->children[5];
        $keywords = $paragraph->children[7];
        $created = $paragraph->children[9];
        $modified = $paragraph->children[11];
        $printedBy = $paragraph->children[13];
        $sourceId = $paragraph->children[15];
        $approved = $paragraph->children[17];
        $template = $paragraph->children[19];

        $t->same('Metadata Source Packet by Migration Desk subject Review packet keywords odt, review created 2026-06-05 modified 2026-06-06 printed Migration Printer custom packet-42 approved true template Templates/import-review.ott.', $paragraph->attr('text'));
        $t->same('title', $title->attr('fieldType'));
        $t->same('Source Packet', $title->attr('fieldMetadata')['stringValue']);
        $t->same('meta.xml', $title->attr('fieldMetadata')['metadataSource']);
        $t->same('Source Packet', $title->children[0]->attr('text'));
        $t->same('Source Packet', $title->attr('attributes')['data-odf-field-string-value']);
        $t->same('meta.xml', $title->attr('attributes')['data-odf-field-metadata-source']);

        $t->same('author-name', $author->attr('fieldType'));
        $t->same('Migration Desk', $author->attr('fieldMetadata')['stringValue']);
        $t->same('subject', $subject->attr('fieldType'));
        $t->same('Review packet', $subject->attr('fieldMetadata')['stringValue']);
        $t->same('keywords', $keywords->attr('fieldType'));
        $t->same('odt, review', $keywords->attr('fieldMetadata')['stringValue']);
        $t->same('creation-date', $created->attr('fieldType'));
        $t->same('2026-06-05', $created->attr('fieldMetadata')['dateValue']);
        $t->same('modification-date', $modified->attr('fieldType'));
        $t->same('2026-06-06', $modified->attr('fieldMetadata')['dateValue']);
        $t->same('printed-by', $printedBy->attr('fieldType'));
        $t->same('Migration Printer', $printedBy->attr('fieldMetadata')['stringValue']);

        $t->same('user-defined', $sourceId->attr('fieldType'));
        $t->same('wp-source-id', $sourceId->attr('fieldName'));
        $t->same('string', $sourceId->attr('fieldMetadata')['valueType']);
        $t->same('packet-42', $sourceId->attr('fieldMetadata')['stringValue']);
        $t->same('packet-42', $sourceId->children[0]->attr('text'));
        $t->same('true', $approved->attr('attributes')['data-odf-field-boolean-value']);
        $t->same(true, $approved->attr('fieldMetadata')['booleanValue']);
        $t->same('true', $approved->children[0]->attr('text'));
        $t->same('template-name', $template->attr('fieldType'));
        $t->same('Templates/import-review.ott', $template->attr('fieldMetadata')['stringValue']);
        $t->same(10, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Source Packet]{.odf-field .odf-field-title data-odf-field-type="title" data-odf-field-string-value="Source Packet" data-odf-field-metadata-source="meta.xml"}', $markdown);
        $t->contains('[packet-42]{.odf-field .odf-field-user-defined data-odf-field-type="user-defined" data-odf-field-name="wp-source-id" data-odf-field-value-type="string" data-odf-field-string-value="packet-42" data-odf-field-metadata-source="meta.xml"}', $markdown);
        $t->contains('[true]{.odf-field .odf-field-user-defined data-odf-field-type="user-defined" data-odf-field-name="approved" data-odf-field-value-type="boolean" data-odf-field-boolean-value="true" data-odf-field-metadata-source="meta.xml"}', $markdown);
        $t->contains('<span class="odf-field odf-field-title" data-odf-field-type="title" data-odf-field-string-value="Source Packet" data-odf-field-metadata-source="meta.xml">Source Packet</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-creation-date" data-odf-field-type="creation-date" data-odf-field-date-value="2026-06-05" data-odf-field-metadata-source="meta.xml">2026-06-05</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-template-name" data-odf-field-type="template-name" data-odf-field-string-value="Templates/import-review.ott" data-odf-field-metadata-source="meta.xml">Templates/import-review.ott</span>', $blocksHtml);
    },
    'maps empty ODT creation time fields from meta creation timestamps' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithEmptyCreationTimeField = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Created at <text:creation-time/> from package metadata.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $metaWithCreationTimestamp = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <meta:creation-date>2026-06-05T09:30:15Z</meta:creation-date>
  </office:meta>
</office:document-meta>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithEmptyCreationTimeField, null, null, $metaWithCreationTimestamp));
        $paragraph = $result['document']->children[0];
        $creationTime = $paragraph->children[1];

        $t->same('Created at PT09H30M15S from package metadata.', $paragraph->attr('text'));
        $t->same('creation-time', $creationTime->attr('fieldType'));
        $t->same('PT09H30M15S', $creationTime->attr('fieldMetadata')['timeValue']);
        $t->same('meta.xml', $creationTime->attr('fieldMetadata')['metadataSource']);
        $t->same('PT09H30M15S', $creationTime->attr('attributes')['data-odf-field-time-value']);
        $t->same('meta.xml', $creationTime->attr('attributes')['data-odf-field-metadata-source']);
        $t->same('PT09H30M15S', $creationTime->children[0]->attr('text'));
        $t->same('2026-06-05T09:30:15Z', $result['metadata']['created']);
        $t->same('PT09H30M15S', $result['metadata']['creationTime']);
        $t->same('PT09H30M15S', $result['importReport']['metadata']['creationTime']);
        $t->same(1, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[PT09H30M15S]{.odf-field .odf-field-creation-time data-odf-field-type="creation-time" data-odf-field-time-value="PT09H30M15S" data-odf-field-metadata-source="meta.xml"}', $markdown);
        $t->contains('<span class="odf-field odf-field-creation-time" data-odf-field-type="creation-time" data-odf-field-time-value="PT09H30M15S" data-odf-field-metadata-source="meta.xml">PT09H30M15S</span>', $blocksHtml);
    },
    'maps ODT template and line number fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTemplateAndLineFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:p>Template <text:template-name text:display="full">Templates/import-review.ott</text:template-name> source line <text:line-number style:num-format="1">37</text:line-number>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTemplateAndLineFields));
        $paragraph = $result['document']->children[0];
        $template = $paragraph->children[1];
        $lineNumber = $paragraph->children[3];

        $t->same('Template Templates/import-review.ott source line 37.', $paragraph->attr('text'));
        $t->same('span', $template->type);
        $t->same(['odf-field', 'odf-field-template-name'], $template->attr('classes'));
        $t->same('template-name', $template->attr('fieldType'));
        $t->same('full', $template->attr('fieldMetadata')['display']);
        $t->same('template-name', $template->attr('attributes')['data-odf-field-type']);
        $t->same('full', $template->attr('attributes')['data-odf-field-display']);
        $t->same('Templates/import-review.ott', $template->children[0]->attr('text'));

        $t->same('span', $lineNumber->type);
        $t->same(['odf-field', 'odf-field-line-number'], $lineNumber->attr('classes'));
        $t->same('line-number', $lineNumber->attr('fieldType'));
        $t->same('1', $lineNumber->attr('fieldMetadata')['numFormat']);
        $t->same('1', $lineNumber->attr('attributes')['data-odf-field-num-format']);
        $t->same('37', $lineNumber->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Templates/import-review.ott]{.odf-field .odf-field-template-name data-odf-field-type="template-name" data-odf-field-display="full"}', $markdown);
        $t->contains('[37]{.odf-field .odf-field-line-number data-odf-field-type="line-number" data-odf-field-num-format="1"}', $markdown);
        $t->contains('<span class="odf-field odf-field-template-name" data-odf-field-type="template-name" data-odf-field-display="full">Templates/import-review.ott</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-line-number" data-odf-field-type="line-number" data-odf-field-num-format="1">37</span>', $blocksHtml);
    },
    'maps ODT inline meta spans into review metadata spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithInlineMeta = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <office:body>
    <office:text>
      <text:p>Reviewed <text:meta xml:id="source-claim-meta" text:name="review:claim" text:description="Curated import source" text:style-name="MetaSource">source claim</text:meta> with <text:meta-field text:name="review-score" office:value-type="float" office:value="0.98">98%</text:meta-field> confidence and <text:meta-field text:name="review-date" office:value-type="date" office:date-value="2026-06-08"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithInlineMeta));
        $paragraph = $result['document']->children[0];
        $sourceMeta = $paragraph->children[1];
        $scoreMeta = $paragraph->children[3];
        $dateMeta = $paragraph->children[5];

        $t->same('Reviewed source claim with 98% confidence and 2026-06-08.', $paragraph->attr('text'));
        $t->same('span', $sourceMeta->type);
        $t->same(['odf-meta'], $sourceMeta->attr('classes'));
        $t->same('meta', $sourceMeta->attr('metaType'));
        $t->same('source-claim-meta', $sourceMeta->attr('metaMetadata')['sourceId']);
        $t->same('review:claim', $sourceMeta->attr('metaMetadata')['name']);
        $t->same('Curated import source', $sourceMeta->attr('metaMetadata')['description']);
        $t->same('MetaSource', $sourceMeta->attr('metaMetadata')['styleName']);
        $t->same('source claim', $sourceMeta->children[0]->attr('text'));
        $t->same('meta', $sourceMeta->attr('attributes')['data-odf-meta-type']);
        $t->same('source-claim-meta', $sourceMeta->attr('attributes')['data-odf-meta-source-id']);
        $t->same('review:claim', $sourceMeta->attr('attributes')['data-odf-meta-name']);

        $t->same('span', $scoreMeta->type);
        $t->same(['odf-meta', 'odf-meta-field'], $scoreMeta->attr('classes'));
        $t->same('meta-field', $scoreMeta->attr('metaType'));
        $t->same('review-score', $scoreMeta->attr('metaMetadata')['name']);
        $t->same('float', $scoreMeta->attr('metaMetadata')['valueType']);
        $t->same('0.98', $scoreMeta->attr('metaMetadata')['value']);
        $t->same('98%', $scoreMeta->children[0]->attr('text'));
        $t->same('meta-field', $scoreMeta->attr('attributes')['data-odf-meta-type']);
        $t->same('float', $scoreMeta->attr('attributes')['data-odf-meta-value-type']);
        $t->same('0.98', $scoreMeta->attr('attributes')['data-odf-meta-value']);

        $t->same('meta-field', $dateMeta->attr('metaType'));
        $t->same('review-date', $dateMeta->attr('metaMetadata')['name']);
        $t->same('date', $dateMeta->attr('metaMetadata')['valueType']);
        $t->same('2026-06-08', $dateMeta->attr('metaMetadata')['dateValue']);
        $t->same('2026-06-08', $dateMeta->children[0]->attr('text'));
        $t->same('2026-06-08', $dateMeta->attr('attributes')['data-odf-meta-date-value']);
        $t->same(3, $result['importReport']['content']['metaSpanCount']);
        $t->same(0, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[source claim]{.odf-meta data-odf-meta-type="meta" data-odf-meta-source-id="source-claim-meta" data-odf-meta-name="review:claim" data-odf-meta-description="Curated import source" data-odf-meta-style-name="MetaSource"}', $markdown);
        $t->contains('[98%]{.odf-meta .odf-meta-field data-odf-meta-type="meta-field" data-odf-meta-name="review-score" data-odf-meta-value-type="float" data-odf-meta-value="0.98"}', $markdown);
        $t->contains('[2026-06-08]{.odf-meta .odf-meta-field data-odf-meta-type="meta-field" data-odf-meta-name="review-date" data-odf-meta-value-type="date" data-odf-meta-date-value="2026-06-08"}', $markdown);
        $t->contains('<span class="odf-meta" data-odf-meta-type="meta" data-odf-meta-source-id="source-claim-meta" data-odf-meta-name="review:claim" data-odf-meta-description="Curated import source" data-odf-meta-style-name="MetaSource">source claim</span>', $blocksHtml);
        $t->contains('<span class="odf-meta odf-meta-field" data-odf-meta-type="meta-field" data-odf-meta-name="review-score" data-odf-meta-value-type="float" data-odf-meta-value="0.98">98%</span>', $blocksHtml);
        $t->contains('<span class="odf-meta odf-meta-field" data-odf-meta-type="meta-field" data-odf-meta-name="review-date" data-odf-meta-value-type="date" data-odf-meta-date-value="2026-06-08">2026-06-08</span>', $blocksHtml);
    },
    'links ODT inline meta spans to RDF sidecar subjects' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithRdf = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>',
            $manifestXml
        );
        $contentWithRdfLinkedMeta = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xhtml="http://www.w3.org/1999/xhtml"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <office:body>
    <office:text>
      <text:p>Reviewed <text:meta xml:id="source-claim-meta" text:name="review:claim" xhtml:about="content.xml#source-claim-meta" xhtml:property="wp:review-status" xhtml:content="ready" xhtml:datatype="xsd:string">source claim</text:meta> with RDF provenance.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $rdfXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="https://example.test/ns/wp#"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <rdf:Description rdf:about="content.xml#source-claim-meta">
    <dc:creator xml:lang="en">Migration Reviewer</dc:creator>
    <wp:review-status>ready</wp:review-status>
  </rdf:Description>
</rdf:RDF>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithRdfLinkedMeta, $manifestWithRdf, null, null, [
            ['name' => 'manifest.rdf', 'data' => $rdfXml],
        ]));
        $paragraph = $result['document']->children[0];
        $sourceMeta = $paragraph->children[1];

        $t->same('Reviewed source claim with RDF provenance.', $paragraph->attr('text'));
        $t->same('span', $sourceMeta->type);
        $t->same(['odf-meta'], $sourceMeta->attr('classes'));
        $t->same('content.xml#source-claim-meta', $sourceMeta->attr('metaMetadata')['rdfAbout']);
        $t->same('wp:review-status', $sourceMeta->attr('metaMetadata')['rdfProperty']);
        $t->same('ready', $sourceMeta->attr('metaMetadata')['rdfContent']);
        $t->same('xsd:string', $sourceMeta->attr('metaMetadata')['rdfDatatype']);
        $t->same('content.xml#source-claim-meta', $sourceMeta->attr('metaMetadata')['rdfSubject']);
        $t->same(2, $sourceMeta->attr('metaMetadata')['rdfSubjectTripleCount']);
        $t->same('dc:creator,wp:review-status', $sourceMeta->attr('metaMetadata')['rdfSubjectPredicates']);
        $t->same(['dc:creator', 'wp:review-status'], $sourceMeta->attr('metaMetadata')['rdfSubjectMetadata']['predicates']);
        $t->same('manifest.rdf', $sourceMeta->attr('metaMetadata')['rdfSubjectParts']);
        $t->same('content.xml#source-claim-meta', $sourceMeta->attr('attributes')['data-odf-meta-rdf-subject']);
        $t->same('2', $sourceMeta->attr('attributes')['data-odf-meta-rdf-subject-triple-count']);
        $t->same('dc:creator,wp:review-status', $sourceMeta->attr('attributes')['data-odf-meta-rdf-subject-predicates']);
        $t->same(1, $result['importReport']['content']['metaSpanCount']);
        $t->same(1, $result['rdfMetadata']['subjectCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[source claim]{.odf-meta data-odf-meta-type="meta" data-odf-meta-source-id="source-claim-meta" data-odf-meta-name="review:claim" data-odf-meta-rdf-about="content.xml#source-claim-meta" data-odf-meta-rdf-property="wp:review-status" data-odf-meta-rdf-content="ready" data-odf-meta-rdf-datatype="xsd:string" data-odf-meta-rdf-subject="content.xml#source-claim-meta" data-odf-meta-rdf-subject-part-count="1" data-odf-meta-rdf-subject-triple-count="2" data-odf-meta-rdf-subject-literal-count="2" data-odf-meta-rdf-subject-resource-count="0" data-odf-meta-rdf-subject-parts="manifest.rdf" data-odf-meta-rdf-subject-predicates="dc:creator,wp:review-status"}', $markdown);
        $t->contains('<span class="odf-meta" data-odf-meta-type="meta" data-odf-meta-source-id="source-claim-meta" data-odf-meta-name="review:claim" data-odf-meta-rdf-about="content.xml#source-claim-meta" data-odf-meta-rdf-property="wp:review-status" data-odf-meta-rdf-content="ready" data-odf-meta-rdf-datatype="xsd:string" data-odf-meta-rdf-subject="content.xml#source-claim-meta" data-odf-meta-rdf-subject-part-count="1" data-odf-meta-rdf-subject-triple-count="2" data-odf-meta-rdf-subject-literal-count="2" data-odf-meta-rdf-subject-resource-count="0" data-odf-meta-rdf-subject-parts="manifest.rdf" data-odf-meta-rdf-subject-predicates="dc:creator,wp:review-status">source claim</span>', $blocksHtml);
    },
    'maps ODT user-defined content fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithUserDefinedFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Custom source id <text:user-defined text:name="wp-source-id" office:value-type="string" office:string-value="packet-42" text:fixed="true">packet-42</text:user-defined>, review state <text:user-defined text:name="review-state" office:value-type="boolean" office:boolean-value="true"/> on <text:user-defined text:name="review-date" office:value-type="date" office:date-value="2026-06-08">June 8, 2026</text:user-defined>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithUserDefinedFields));
        $paragraph = $result['document']->children[0];
        $sourceId = $paragraph->children[1];
        $reviewState = $paragraph->children[3];
        $reviewDate = $paragraph->children[5];

        $t->same('Custom source id packet-42, review state true on June 8, 2026.', $paragraph->attr('text'));
        $t->same('span', $sourceId->type);
        $t->same(['odf-field', 'odf-field-user-defined'], $sourceId->attr('classes'));
        $t->same('user-defined', $sourceId->attr('fieldType'));
        $t->same('wp-source-id', $sourceId->attr('fieldName'));
        $t->same('string', $sourceId->attr('fieldMetadata')['valueType']);
        $t->same('packet-42', $sourceId->attr('fieldMetadata')['stringValue']);
        $t->same(true, $sourceId->attr('fieldMetadata')['fixed']);
        $t->same('packet-42', $sourceId->children[0]->attr('text'));
        $t->same('wp-source-id', $sourceId->attr('attributes')['data-odf-field-name']);
        $t->same('packet-42', $sourceId->attr('attributes')['data-odf-field-string-value']);
        $t->same('true', $sourceId->attr('attributes')['data-odf-field-fixed']);

        $t->same('span', $reviewState->type);
        $t->same('user-defined', $reviewState->attr('fieldType'));
        $t->same('review-state', $reviewState->attr('fieldName'));
        $t->same('boolean', $reviewState->attr('fieldMetadata')['valueType']);
        $t->same(true, $reviewState->attr('fieldMetadata')['booleanValue']);
        $t->same('true', $reviewState->children[0]->attr('text'));
        $t->same('true', $reviewState->attr('attributes')['data-odf-field-boolean-value']);

        $t->same('user-defined', $reviewDate->attr('fieldType'));
        $t->same('review-date', $reviewDate->attr('fieldName'));
        $t->same('date', $reviewDate->attr('fieldMetadata')['valueType']);
        $t->same('2026-06-08', $reviewDate->attr('fieldMetadata')['dateValue']);
        $t->same('June 8, 2026', $reviewDate->children[0]->attr('text'));
        $t->same('2026-06-08', $reviewDate->attr('attributes')['data-odf-field-date-value']);
        $t->same(3, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[packet-42]{.odf-field .odf-field-user-defined data-odf-field-type="user-defined" data-odf-field-name="wp-source-id" data-odf-field-value-type="string" data-odf-field-string-value="packet-42" data-odf-field-fixed="true"}', $markdown);
        $t->contains('[true]{.odf-field .odf-field-user-defined data-odf-field-type="user-defined" data-odf-field-name="review-state" data-odf-field-value-type="boolean" data-odf-field-boolean-value="true"}', $markdown);
        $t->contains('[June 8, 2026]{.odf-field .odf-field-user-defined data-odf-field-type="user-defined" data-odf-field-name="review-date" data-odf-field-value-type="date" data-odf-field-date-value="2026-06-08"}', $markdown);
        $t->contains('<span class="odf-field odf-field-user-defined" data-odf-field-type="user-defined" data-odf-field-name="wp-source-id" data-odf-field-value-type="string" data-odf-field-string-value="packet-42" data-odf-field-fixed="true">packet-42</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-defined" data-odf-field-type="user-defined" data-odf-field-name="review-state" data-odf-field-value-type="boolean" data-odf-field-boolean-value="true">true</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-defined" data-odf-field-type="user-defined" data-odf-field-name="review-date" data-odf-field-value-type="date" data-odf-field-date-value="2026-06-08">June 8, 2026</span>', $blocksHtml);
    },
    'maps ODT field style names into review span metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithStyledFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:p>Styled fields <text:author-name text:style-name="ReviewerField" text:fixed="true">Migration Desk</text:author-name>, page <text:page-number style:data-style-name="PageDigits">7</text:page-number>, and email <text:sender-email text:style-name="SenderEmail">desk@example.test</text:sender-email>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithStyledFields));
        $paragraph = $result['document']->children[0];
        $author = $paragraph->children[1];
        $page = $paragraph->children[3];
        $email = $paragraph->children[5];

        $t->same('Styled fields Migration Desk, page 7, and email desk@example.test.', $paragraph->attr('text'));
        $t->same('author-name', $author->attr('fieldType'));
        $t->same('ReviewerField', $author->attr('fieldMetadata')['styleName']);
        $t->same(true, $author->attr('fieldMetadata')['fixed']);
        $t->same('ReviewerField', $author->attr('attributes')['data-odf-field-style-name']);
        $t->same('true', $author->attr('attributes')['data-odf-field-fixed']);
        $t->same('Migration Desk', $author->children[0]->attr('text'));

        $t->same('page-number', $page->attr('fieldType'));
        $t->same('PageDigits', $page->attr('fieldMetadata')['styleName']);
        $t->same('PageDigits', $page->attr('attributes')['data-odf-field-style-name']);
        $t->same('7', $page->children[0]->attr('text'));

        $t->same('sender-email', $email->attr('fieldType'));
        $t->same('SenderEmail', $email->attr('fieldMetadata')['styleName']);
        $t->same('SenderEmail', $email->attr('attributes')['data-odf-field-style-name']);
        $t->same('desk@example.test', $email->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Migration Desk]{.odf-field .odf-field-author-name data-odf-field-type="author-name" data-odf-field-style-name="ReviewerField" data-odf-field-fixed="true"}', $markdown);
        $t->contains('[7]{.odf-field .odf-field-page-number data-odf-field-type="page-number" data-odf-field-style-name="PageDigits"}', $markdown);
        $t->contains('[desk@example.test]{.odf-field .odf-field-sender-email data-odf-field-type="sender-email" data-odf-field-style-name="SenderEmail"}', $markdown);
        $t->contains('<span class="odf-field odf-field-author-name" data-odf-field-type="author-name" data-odf-field-style-name="ReviewerField" data-odf-field-fixed="true">Migration Desk</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-page-number" data-odf-field-type="page-number" data-odf-field-style-name="PageDigits">7</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-sender-email" data-odf-field-type="sender-email" data-odf-field-style-name="SenderEmail">desk@example.test</span>', $blocksHtml);
    },
    'maps empty ODT sender fields from settings XML into review spans' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $contentWithEmptySenderFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Sender <text:sender-firstname/> <text:sender-lastname/> &lt;<text:sender-email/>&gt; at <text:sender-company/> remains auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="ooo:user-settings">
      <config:config-item config:name="FirstName" config:type="string">Maya</config:config-item>
      <config:config-item config:name="LastName" config:type="string">Editor</config:config-item>
      <config:config-item config:name="EMail" config:type="string">desk@example.test</config:config-item>
      <config:config-item config:name="Company" config:type="string">WordPress Migration Desk</config:config-item>
    </config:config-item-set>
  </office:settings>
</office:document-settings>
XML;
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithEmptySenderFields, $manifestWithSettings, null, null, [
            ['name' => 'settings.xml', 'data' => $settingsXml],
        ]));
        $paragraph = $result['document']->children[0];
        $firstName = $paragraph->children[1];
        $lastName = $paragraph->children[3];
        $email = $paragraph->children[5];
        $company = $paragraph->children[7];

        $t->same('Sender Maya Editor <desk@example.test> at WordPress Migration Desk remains auditable.', $paragraph->attr('text'));
        $t->same('sender-firstname', $firstName->attr('fieldType'));
        $t->same('Maya', $firstName->attr('fieldMetadata')['stringValue']);
        $t->same('settings.xml', $firstName->attr('fieldMetadata')['settingsSource']);
        $t->same('ooo:user-settings', $firstName->attr('fieldMetadata')['settingsSet']);
        $t->same('FirstName', $firstName->attr('fieldMetadata')['settingsName']);
        $t->same('Maya', $firstName->children[0]->attr('text'));
        $t->same('settings.xml', $firstName->attr('attributes')['data-odf-field-settings-source']);
        $t->same('ooo:user-settings', $firstName->attr('attributes')['data-odf-field-settings-set']);
        $t->same('FirstName', $firstName->attr('attributes')['data-odf-field-settings-name']);

        $t->same('sender-lastname', $lastName->attr('fieldType'));
        $t->same('Editor', $lastName->attr('fieldMetadata')['stringValue']);
        $t->same('LastName', $lastName->attr('fieldMetadata')['settingsName']);
        $t->same('sender-email', $email->attr('fieldType'));
        $t->same('desk@example.test', $email->attr('fieldMetadata')['stringValue']);
        $t->same('EMail', $email->attr('fieldMetadata')['settingsName']);
        $t->same('sender-company', $company->attr('fieldType'));
        $t->same('WordPress Migration Desk', $company->attr('fieldMetadata')['stringValue']);
        $t->same('Company', $company->attr('fieldMetadata')['settingsName']);
        $t->same(4, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Maya]{.odf-field .odf-field-sender-firstname data-odf-field-type="sender-firstname" data-odf-field-string-value="Maya" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="FirstName"}', $markdown);
        $t->contains('[desk@example.test]{.odf-field .odf-field-sender-email data-odf-field-type="sender-email" data-odf-field-string-value="desk@example.test" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="EMail"}', $markdown);
        $t->contains('<span class="odf-field odf-field-sender-firstname" data-odf-field-type="sender-firstname" data-odf-field-string-value="Maya" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="FirstName">Maya</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-sender-email" data-odf-field-type="sender-email" data-odf-field-string-value="desk@example.test" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="EMail">desk@example.test</span>', $blocksHtml);
    },
    'maps empty ODT author initials from settings XML into review spans' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $contentWithEmptyAuthorInitials = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Reviewed by <text:author-initials/> from package user settings.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="ooo:user-settings">
      <config:config-item config:name="Initials" config:type="string">ME</config:config-item>
    </config:config-item-set>
  </office:settings>
</office:document-settings>
XML;
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithEmptyAuthorInitials, $manifestWithSettings, null, null, [
            ['name' => 'settings.xml', 'data' => $settingsXml],
        ]));
        $paragraph = $result['document']->children[0];
        $initials = $paragraph->children[1];

        $t->same('Reviewed by ME from package user settings.', $paragraph->attr('text'));
        $t->same('author-initials', $initials->attr('fieldType'));
        $t->same('ME', $initials->attr('fieldMetadata')['stringValue']);
        $t->same('settings.xml', $initials->attr('fieldMetadata')['settingsSource']);
        $t->same('ooo:user-settings', $initials->attr('fieldMetadata')['settingsSet']);
        $t->same('Initials', $initials->attr('fieldMetadata')['settingsName']);
        $t->same('ME', $initials->children[0]->attr('text'));
        $t->same('ME', $initials->attr('attributes')['data-odf-field-string-value']);
        $t->same('settings.xml', $initials->attr('attributes')['data-odf-field-settings-source']);
        $t->same('ooo:user-settings', $initials->attr('attributes')['data-odf-field-settings-set']);
        $t->same('Initials', $initials->attr('attributes')['data-odf-field-settings-name']);
        $t->same(1, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[ME]{.odf-field .odf-field-author-initials data-odf-field-type="author-initials" data-odf-field-string-value="ME" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="Initials"}', $markdown);
        $t->contains('<span class="odf-field odf-field-author-initials" data-odf-field-type="author-initials" data-odf-field-string-value="ME" data-odf-field-settings-source="settings.xml" data-odf-field-settings-set="ooo:user-settings" data-odf-field-settings-name="Initials">ME</span>', $blocksHtml);
    },
    'maps ODT field number date and time format metadata into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFormattedFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:p>Formatted page <text:page-number style:num-format="I" style:num-prefix="p. " style:num-suffix=" / source" style:num-letter-sync="true">IV</text:page-number>, adjusted date <text:date text:fixed="true" text:date-value="2026-06-08" text:date-adjust="P1D" style:data-style-name="ReviewDateFormat">June 9, 2026</text:date>, and adjusted time <text:time text:time-value="PT13H45M00S" text:time-adjust="PT30M" style:data-style-name="ReviewTimeFormat">14:15</text:time>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFormattedFields));
        $paragraph = $result['document']->children[0];
        $page = $paragraph->children[1];
        $date = $paragraph->children[3];
        $time = $paragraph->children[5];

        $t->same('Formatted page IV, adjusted date June 9, 2026, and adjusted time 14:15.', $paragraph->attr('text'));
        $t->same('page-number', $page->attr('fieldType'));
        $t->same('I', $page->attr('fieldMetadata')['numFormat']);
        $t->same('p. ', $page->attr('fieldMetadata')['numPrefix']);
        $t->same(' / source', $page->attr('fieldMetadata')['numSuffix']);
        $t->same(true, $page->attr('fieldMetadata')['numLetterSync']);
        $t->same('I', $page->attr('attributes')['data-odf-field-num-format']);
        $t->same('p. ', $page->attr('attributes')['data-odf-field-num-prefix']);
        $t->same(' / source', $page->attr('attributes')['data-odf-field-num-suffix']);
        $t->same('true', $page->attr('attributes')['data-odf-field-num-letter-sync']);
        $t->same('IV', $page->children[0]->attr('text'));

        $t->same('date', $date->attr('fieldType'));
        $t->same('2026-06-08', $date->attr('fieldMetadata')['dateValue']);
        $t->same('P1D', $date->attr('fieldMetadata')['dateAdjust']);
        $t->same('ReviewDateFormat', $date->attr('fieldMetadata')['styleName']);
        $t->same(true, $date->attr('fieldMetadata')['fixed']);
        $t->same('P1D', $date->attr('attributes')['data-odf-field-date-adjust']);
        $t->same('ReviewDateFormat', $date->attr('attributes')['data-odf-field-style-name']);
        $t->same('June 9, 2026', $date->children[0]->attr('text'));

        $t->same('time', $time->attr('fieldType'));
        $t->same('PT13H45M00S', $time->attr('fieldMetadata')['timeValue']);
        $t->same('PT30M', $time->attr('fieldMetadata')['timeAdjust']);
        $t->same('ReviewTimeFormat', $time->attr('fieldMetadata')['styleName']);
        $t->same('PT30M', $time->attr('attributes')['data-odf-field-time-adjust']);
        $t->same('14:15', $time->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[IV]{.odf-field .odf-field-page-number data-odf-field-type="page-number" data-odf-field-num-format="I" data-odf-field-num-prefix="p. " data-odf-field-num-suffix=" / source" data-odf-field-num-letter-sync="true"}', $markdown);
        $t->contains('[June 9, 2026]{.odf-field .odf-field-date data-odf-field-type="date" data-odf-field-date-value="2026-06-08" data-odf-field-date-adjust="P1D" data-odf-field-style-name="ReviewDateFormat" data-odf-field-fixed="true"}', $markdown);
        $t->contains('[14:15]{.odf-field .odf-field-time data-odf-field-type="time" data-odf-field-time-value="PT13H45M00S" data-odf-field-time-adjust="PT30M" data-odf-field-style-name="ReviewTimeFormat"}', $markdown);
        $t->contains('<span class="odf-field odf-field-page-number" data-odf-field-type="page-number" data-odf-field-num-format="I" data-odf-field-num-prefix="p. " data-odf-field-num-suffix=" / source" data-odf-field-num-letter-sync="true">IV</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-date" data-odf-field-type="date" data-odf-field-date-value="2026-06-08" data-odf-field-date-adjust="P1D" data-odf-field-style-name="ReviewDateFormat" data-odf-field-fixed="true">June 9, 2026</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-time" data-odf-field-type="time" data-odf-field-time-value="PT13H45M00S" data-odf-field-time-adjust="PT30M" data-odf-field-style-name="ReviewTimeFormat">14:15</span>', $blocksHtml);
    },
    'maps ODT page variable chapter filename and statistic fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPageAndStatisticFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:p>Review page <text:page-variable-set text:name="SourcePage" text:current-value="4" style:num-format="1">4</text:page-variable-set> of <text:page-variable-get text:name="SourcePage" text:current-value="4"/>, chapter <text:chapter text:outline-level="2" text:display="name-and-number">2 Source review</text:chapter>, file <text:file-name text:display="full">source/review.odt</text:file-name>, counts <text:word-count>128</text:word-count>/<text:sentence-count>6</text:sentence-count>/<text:paragraph-count>7</text:paragraph-count>/<text:character-count>640</text:character-count>/<text:table-count>2</text:table-count>/<text:image-count>1</text:image-count>/<text:object-count>3</text:object-count>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPageAndStatisticFields));
        $paragraph = $result['document']->children[0];
        $pageSet = $paragraph->children[1];
        $pageGet = $paragraph->children[3];
        $chapter = $paragraph->children[5];
        $file = $paragraph->children[7];
        $wordCount = $paragraph->children[9];
        $sentenceCount = $paragraph->children[11];
        $paragraphCount = $paragraph->children[13];
        $characterCount = $paragraph->children[15];
        $tableCount = $paragraph->children[17];
        $imageCount = $paragraph->children[19];
        $objectCount = $paragraph->children[21];

        $t->same('Review page 4 of 4, chapter 2 Source review, file source/review.odt, counts 128/6/7/640/2/1/3.', $paragraph->attr('text'));
        $t->same('span', $pageSet->type);
        $t->same(['odf-field', 'odf-field-page-variable-set'], $pageSet->attr('classes'));
        $t->same('page-variable-set', $pageSet->attr('fieldType'));
        $t->same('SourcePage', $pageSet->attr('fieldName'));
        $t->same('4', $pageSet->attr('fieldMetadata')['currentValue']);
        $t->same('1', $pageSet->attr('fieldMetadata')['numFormat']);
        $t->same('4', $pageSet->attr('attributes')['data-odf-field-current-value']);
        $t->same('1', $pageSet->attr('attributes')['data-odf-field-num-format']);
        $t->same('page-variable-get', $pageGet->attr('fieldType'));
        $t->same('SourcePage', $pageGet->attr('fieldName'));
        $t->same('4', $pageGet->attr('fieldMetadata')['currentValue']);
        $t->same('chapter', $chapter->attr('fieldType'));
        $t->same(2, $chapter->attr('fieldMetadata')['outlineLevel']);
        $t->same('name-and-number', $chapter->attr('fieldMetadata')['display']);
        $t->same('2', $chapter->attr('attributes')['data-odf-field-outline-level']);
        $t->same('name-and-number', $chapter->attr('attributes')['data-odf-field-display']);
        $t->same('file-name', $file->attr('fieldType'));
        $t->same('full', $file->attr('fieldMetadata')['display']);
        $t->same('source/review.odt', $file->children[0]->attr('text'));

        $t->same('word-count', $wordCount->attr('fieldType'));
        $t->same('sentence-count', $sentenceCount->attr('fieldType'));
        $t->same('paragraph-count', $paragraphCount->attr('fieldType'));
        $t->same('character-count', $characterCount->attr('fieldType'));
        $t->same('table-count', $tableCount->attr('fieldType'));
        $t->same('image-count', $imageCount->attr('fieldType'));
        $t->same('object-count', $objectCount->attr('fieldType'));
        $t->same(11, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[4]{.odf-field .odf-field-page-variable-set data-odf-field-type="page-variable-set" data-odf-field-name="SourcePage" data-odf-field-current-value="4" data-odf-field-num-format="1"}', $markdown);
        $t->contains('[2 Source review]{.odf-field .odf-field-chapter data-odf-field-type="chapter" data-odf-field-display="name-and-number" data-odf-field-outline-level="2"}', $markdown);
        $t->contains('<span class="odf-field odf-field-page-variable-get" data-odf-field-type="page-variable-get" data-odf-field-name="SourcePage" data-odf-field-current-value="4">4</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-chapter" data-odf-field-type="chapter" data-odf-field-display="name-and-number" data-odf-field-outline-level="2">2 Source review</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-file-name" data-odf-field-type="file-name" data-odf-field-display="full">source/review.odt</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-word-count" data-odf-field-type="word-count">128</span>', $blocksHtml);
    },
    'fills empty ODT statistic fields from meta xml document statistics' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithEmptyStatisticFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package counts <text:word-count/>/<text:sentence-count/>/<text:paragraph-count/>/<text:page-count/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $metaWithExtendedStatistics = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <meta:document-statistic
      meta:page-count="12"
      meta:word-count="128"
      meta:sentence-count="9"
      meta:paragraph-count="7"
      meta:character-count="640"
      meta:non-whitespace-character-count="600"
      meta:syllable-count="210"
      meta:table-count="2"
      meta:image-count="1"
      meta:object-count="3"/>
  </office:meta>
</office:document-meta>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithEmptyStatisticFields, null, null, $metaWithExtendedStatistics));
        $paragraph = $result['document']->children[0];
        $wordCount = $paragraph->children[1];
        $sentenceCount = $paragraph->children[3];
        $paragraphCount = $paragraph->children[5];
        $pageCount = $paragraph->children[7];

        $t->same(9, $result['metadata']['statistics']['sentenceCount']);
        $t->same(600, $result['metadata']['statistics']['nonWhitespaceCharacterCount']);
        $t->same(210, $result['metadata']['statistics']['syllableCount']);
        $t->same('Package counts 128/9/7/12.', $paragraph->attr('text'));
        $t->same('word-count', $wordCount->attr('fieldType'));
        $t->same('128', $wordCount->attr('fieldMetadata')['currentValue']);
        $t->same('meta.xml', $wordCount->attr('fieldMetadata')['metadataSource']);
        $t->same('sentence-count', $sentenceCount->attr('fieldType'));
        $t->same('9', $sentenceCount->attr('fieldMetadata')['currentValue']);
        $t->same('9', $sentenceCount->children[0]->attr('text'));
        $t->same('paragraph-count', $paragraphCount->attr('fieldType'));
        $t->same('7', $paragraphCount->attr('attributes')['data-odf-field-current-value']);
        $t->same('page-count', $pageCount->attr('fieldType'));
        $t->same('12', $pageCount->attr('fieldMetadata')['currentValue']);
        $t->same(4, $result['importReport']['content']['fieldCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<span class="odf-field odf-field-sentence-count" data-odf-field-type="sentence-count" data-odf-field-current-value="9" data-odf-field-metadata-source="meta.xml">9</span>', $blocksHtml);
    },
    'fills empty ODT extended statistic fields from meta xml document statistics' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithExtendedStatisticFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Readable totals <text:non-whitespace-character-count/> non-space characters and <text:syllable-count/> syllables.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $metaWithExtendedStatistics = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <meta:document-statistic
      meta:non-whitespace-character-count="600"
      meta:syllable-count="210"/>
  </office:meta>
</office:document-meta>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithExtendedStatisticFields, null, null, $metaWithExtendedStatistics));
        $paragraph = $result['document']->children[0];
        $nonWhitespaceCount = $paragraph->children[1];
        $syllableCount = $paragraph->children[3];

        $t->same(600, $result['metadata']['statistics']['nonWhitespaceCharacterCount']);
        $t->same(210, $result['metadata']['statistics']['syllableCount']);
        $t->same('Readable totals 600 non-space characters and 210 syllables.', $paragraph->attr('text'));

        $t->same('span', $nonWhitespaceCount->type);
        $t->same(['odf-field', 'odf-field-non-whitespace-character-count'], $nonWhitespaceCount->attr('classes'));
        $t->same('non-whitespace-character-count', $nonWhitespaceCount->attr('fieldType'));
        $t->same('600', $nonWhitespaceCount->attr('fieldMetadata')['currentValue']);
        $t->same('meta.xml', $nonWhitespaceCount->attr('fieldMetadata')['metadataSource']);
        $t->same('600', $nonWhitespaceCount->attr('attributes')['data-odf-field-current-value']);
        $t->same('meta.xml', $nonWhitespaceCount->attr('attributes')['data-odf-field-metadata-source']);
        $t->same('600', $nonWhitespaceCount->children[0]->attr('text'));

        $t->same('span', $syllableCount->type);
        $t->same(['odf-field', 'odf-field-syllable-count'], $syllableCount->attr('classes'));
        $t->same('syllable-count', $syllableCount->attr('fieldType'));
        $t->same('210', $syllableCount->attr('fieldMetadata')['currentValue']);
        $t->same('meta.xml', $syllableCount->attr('fieldMetadata')['metadataSource']);
        $t->same('210', $syllableCount->attr('attributes')['data-odf-field-current-value']);
        $t->same('meta.xml', $syllableCount->attr('attributes')['data-odf-field-metadata-source']);
        $t->same('210', $syllableCount->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[600]{.odf-field .odf-field-non-whitespace-character-count data-odf-field-type="non-whitespace-character-count" data-odf-field-current-value="600" data-odf-field-metadata-source="meta.xml"}', $markdown);
        $t->contains('[210]{.odf-field .odf-field-syllable-count data-odf-field-type="syllable-count" data-odf-field-current-value="210" data-odf-field-metadata-source="meta.xml"}', $markdown);
        $t->contains('<span class="odf-field odf-field-non-whitespace-character-count" data-odf-field-type="non-whitespace-character-count" data-odf-field-current-value="600" data-odf-field-metadata-source="meta.xml">600</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-syllable-count" data-odf-field-type="syllable-count" data-odf-field-current-value="210" data-odf-field-metadata-source="meta.xml">210</span>', $blocksHtml);
    },
    'maps ODT page continuation fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPageContinuationFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Continuation <text:page-continuation text:select-page="next" text:string-value="continued on next page">continued on next page</text:page-continuation> and fallback <text:page-continuation text:select-page="previous" text:string-value="continued from previous page"/> stay reviewable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPageContinuationFields));
        $paragraph = $result['document']->children[0];
        $next = $paragraph->children[1];
        $previous = $paragraph->children[3];

        $t->same('Continuation continued on next page and fallback continued from previous page stay reviewable.', $paragraph->attr('text'));
        $t->same('span', $next->type);
        $t->same(['odf-field', 'odf-field-page-continuation'], $next->attr('classes'));
        $t->same('page-continuation', $next->attr('fieldType'));
        $t->same('next', $next->attr('fieldMetadata')['selectPage']);
        $t->same('continued on next page', $next->attr('fieldMetadata')['stringValue']);
        $t->same('next', $next->attr('attributes')['data-odf-field-select-page']);
        $t->same('continued on next page', $next->attr('attributes')['data-odf-field-string-value']);
        $t->same('continued on next page', $next->children[0]->attr('text'));

        $t->same('page-continuation', $previous->attr('fieldType'));
        $t->same('previous', $previous->attr('fieldMetadata')['selectPage']);
        $t->same('continued from previous page', $previous->attr('fieldMetadata')['stringValue']);
        $t->same('previous', $previous->attr('attributes')['data-odf-field-select-page']);
        $t->same('continued from previous page', $previous->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[continued on next page]{.odf-field .odf-field-page-continuation data-odf-field-type="page-continuation" data-odf-field-string-value="continued on next page" data-odf-field-select-page="next"}', $markdown);
        $t->contains('[continued from previous page]{.odf-field .odf-field-page-continuation data-odf-field-type="page-continuation" data-odf-field-string-value="continued from previous page" data-odf-field-select-page="previous"}', $markdown);
        $t->contains('<span class="odf-field odf-field-page-continuation" data-odf-field-type="page-continuation" data-odf-field-string-value="continued on next page" data-odf-field-select-page="next">continued on next page</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-page-continuation" data-odf-field-type="page-continuation" data-odf-field-string-value="continued from previous page" data-odf-field-select-page="previous">continued from previous page</span>', $blocksHtml);
    },
    'maps ODT sheet name and table formula fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSpreadsheetFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Sheet <text:sheet-name table:table-name="Review Sheet">Review Sheet</text:sheet-name> formula <text:table-formula text:formula="of:=SUM([.B2:.B4])" table:cell-range-address="Review Sheet.B2:Review Sheet.B4" office:value-type="float" office:value="42" style:data-style-name="ReviewFloat">42</text:table-formula> and fallback <text:sheet-name table:table-name="Archive Sheet"/> stay reviewable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSpreadsheetFields));
        $paragraph = $result['document']->children[0];
        $sheet = $paragraph->children[1];
        $formula = $paragraph->children[3];
        $fallback = $paragraph->children[5];

        $t->same('Sheet Review Sheet formula 42 and fallback Archive Sheet stay reviewable.', $paragraph->attr('text'));
        $t->same('span', $sheet->type);
        $t->same(['odf-field', 'odf-field-sheet-name'], $sheet->attr('classes'));
        $t->same('sheet-name', $sheet->attr('fieldType'));
        $t->same('Review Sheet', $sheet->attr('fieldMetadata')['tableName']);
        $t->same('Review Sheet', $sheet->attr('attributes')['data-odf-field-table-name']);
        $t->same('Review Sheet', $sheet->children[0]->attr('text'));

        $t->same('span', $formula->type);
        $t->same(['odf-field', 'odf-field-table-formula'], $formula->attr('classes'));
        $t->same('table-formula', $formula->attr('fieldType'));
        $t->same('of:=SUM([.B2:.B4])', $formula->attr('fieldMetadata')['formula']);
        $t->same('Review Sheet.B2:Review Sheet.B4', $formula->attr('fieldMetadata')['cellRangeAddress']);
        $t->same('float', $formula->attr('fieldMetadata')['valueType']);
        $t->same('42', $formula->attr('fieldMetadata')['value']);
        $t->same('ReviewFloat', $formula->attr('fieldMetadata')['styleName']);
        $t->same('of:=SUM([.B2:.B4])', $formula->attr('attributes')['data-odf-field-formula']);
        $t->same('Review Sheet.B2:Review Sheet.B4', $formula->attr('attributes')['data-odf-field-cell-range-address']);
        $t->same('float', $formula->attr('attributes')['data-odf-field-value-type']);
        $t->same('42', $formula->attr('attributes')['data-odf-field-value']);
        $t->same('ReviewFloat', $formula->attr('attributes')['data-odf-field-style-name']);
        $t->same('42', $formula->children[0]->attr('text'));

        $t->same('sheet-name', $fallback->attr('fieldType'));
        $t->same('Archive Sheet', $fallback->attr('fieldMetadata')['tableName']);
        $t->same('Archive Sheet', $fallback->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Review Sheet]{.odf-field .odf-field-sheet-name data-odf-field-type="sheet-name" data-odf-field-table-name="Review Sheet"}', $markdown);
        $t->contains('[42]{.odf-field .odf-field-table-formula data-odf-field-type="table-formula" data-odf-field-formula="of:=SUM([.B2:.B4])" data-odf-field-cell-range-address="Review Sheet.B2:Review Sheet.B4" data-odf-field-value-type="float" data-odf-field-value="42" data-odf-field-style-name="ReviewFloat"}', $markdown);
        $t->contains('[Archive Sheet]{.odf-field .odf-field-sheet-name data-odf-field-type="sheet-name" data-odf-field-table-name="Archive Sheet"}', $markdown);
        $t->contains('<span class="odf-field odf-field-sheet-name" data-odf-field-type="sheet-name" data-odf-field-table-name="Review Sheet">Review Sheet</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-table-formula" data-odf-field-type="table-formula" data-odf-field-formula="of:=SUM([.B2:.B4])" data-odf-field-cell-range-address="Review Sheet.B2:Review Sheet.B4" data-odf-field-value-type="float" data-odf-field-value="42" data-odf-field-style-name="ReviewFloat">42</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-sheet-name" data-odf-field-type="sheet-name" data-odf-field-table-name="Archive Sheet">Archive Sheet</span>', $blocksHtml);
    },
    'maps ODT conditional and hidden text fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithConditionalFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Conditional <text:conditional-text text:condition="ReviewStatus == &quot;ready&quot;" text:string-value-if-true="Ready to publish" text:string-value-if-false="Hold for review">Ready to publish</text:conditional-text> and hidden <text:hidden-text text:condition="NeedsReview == true" text:string-value="reviewer note">reviewer note</text:hidden-text> plus fallback <text:hidden-text text:condition="AuditOnly" text:string-value="fallback audit note"/> and paragraph marker <text:hidden-paragraph text:condition="ArchiveOnly" text:string-value="archive paragraph marker"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithConditionalFields));
        $paragraph = $result['document']->children[0];
        $conditional = $paragraph->children[1];
        $hidden = $paragraph->children[3];
        $fallback = $paragraph->children[5];
        $hiddenParagraph = $paragraph->children[7];

        $t->same('Conditional Ready to publish and hidden reviewer note plus fallback fallback audit note and paragraph marker archive paragraph marker.', $paragraph->attr('text'));
        $t->same('span', $conditional->type);
        $t->same(['odf-field', 'odf-field-conditional-text'], $conditional->attr('classes'));
        $t->same('conditional-text', $conditional->attr('fieldType'));
        $t->same('ReviewStatus == "ready"', $conditional->attr('fieldMetadata')['condition']);
        $t->same('Ready to publish', $conditional->attr('fieldMetadata')['stringValueIfTrue']);
        $t->same('Hold for review', $conditional->attr('fieldMetadata')['stringValueIfFalse']);
        $t->same('ReviewStatus == "ready"', $conditional->attr('attributes')['data-odf-field-condition']);
        $t->same('Ready to publish', $conditional->attr('attributes')['data-odf-field-string-value-if-true']);
        $t->same('Hold for review', $conditional->attr('attributes')['data-odf-field-string-value-if-false']);
        $t->same('Ready to publish', $conditional->children[0]->attr('text'));

        $t->same('hidden-text', $hidden->attr('fieldType'));
        $t->same('NeedsReview == true', $hidden->attr('fieldMetadata')['condition']);
        $t->same('reviewer note', $hidden->attr('fieldMetadata')['stringValue']);
        $t->same('reviewer note', $hidden->children[0]->attr('text'));
        $t->same('hidden-text', $fallback->attr('fieldType'));
        $t->same('AuditOnly', $fallback->attr('fieldMetadata')['condition']);
        $t->same('fallback audit note', $fallback->attr('fieldMetadata')['stringValue']);
        $t->same('fallback audit note', $fallback->children[0]->attr('text'));
        $t->same('hidden-paragraph', $hiddenParagraph->attr('fieldType'));
        $t->same('ArchiveOnly', $hiddenParagraph->attr('fieldMetadata')['condition']);
        $t->same('archive paragraph marker', $hiddenParagraph->attr('fieldMetadata')['stringValue']);
        $t->same('archive paragraph marker', $hiddenParagraph->children[0]->attr('text'));
        $t->same(4, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Ready to publish]{.odf-field .odf-field-conditional-text data-odf-field-type="conditional-text" data-odf-field-condition="ReviewStatus == \"ready\"" data-odf-field-string-value-if-true="Ready to publish" data-odf-field-string-value-if-false="Hold for review"}', $markdown);
        $t->contains('[fallback audit note]{.odf-field .odf-field-hidden-text data-odf-field-type="hidden-text" data-odf-field-condition="AuditOnly" data-odf-field-string-value="fallback audit note"}', $markdown);
        $t->contains('[archive paragraph marker]{.odf-field .odf-field-hidden-paragraph data-odf-field-type="hidden-paragraph" data-odf-field-condition="ArchiveOnly" data-odf-field-string-value="archive paragraph marker"}', $markdown);
        $t->contains('<span class="odf-field odf-field-conditional-text" data-odf-field-type="conditional-text" data-odf-field-condition="ReviewStatus == &quot;ready&quot;" data-odf-field-string-value-if-true="Ready to publish" data-odf-field-string-value-if-false="Hold for review">Ready to publish</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-hidden-text" data-odf-field-type="hidden-text" data-odf-field-condition="AuditOnly" data-odf-field-string-value="fallback audit note">fallback audit note</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-hidden-paragraph" data-odf-field-type="hidden-paragraph" data-odf-field-condition="ArchiveOnly" data-odf-field-string-value="archive paragraph marker">archive paragraph marker</span>', $blocksHtml);
    },
    'maps ODT script macro and DDE fields into inert review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDynamicFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:dde-connection-decls>
        <text:dde-connection-decl
          office:name="ReviewSheet"
          office:dde-application="soffice"
          office:dde-topic="Documents/review.ods"
          office:dde-item="Approved.A2"
          office:automatic-update="false"
          office:conversion-mode="keep-text"/>
      </text:dde-connection-decls>
      <text:p>Audit <text:script script:language="ooo:Basic" xlink:href="vnd.sun.star.script:Standard.Module.Main?language=Basic&amp;location=document" xlink:type="simple">Run import macro</text:script>, macro <text:execute-macro text:name="Standard.Module.PublishReview">Publish review</text:execute-macro>, and DDE <text:dde-connection text:connection-name="ReviewSheet">Last approved row</text:dde-connection>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDynamicFields));
        $paragraph = $result['document']->children[0];
        $script = $paragraph->children[1];
        $macro = $paragraph->children[3];
        $dde = $paragraph->children[5];
        $declarations = $result['contentDeclarations'];
        $ddeDeclaration = $declarations['ddeConnectionDeclarationsByName']['ReviewSheet'] ?? [];

        $t->same('Audit Run import macro, macro Publish review, and DDE Last approved row.', $paragraph->attr('text'));
        $t->same('span', $script->type);
        $t->same(['odf-field', 'odf-field-script'], $script->attr('classes'));
        $t->same('script', $script->attr('fieldType'));
        $t->same('ooo:Basic', $script->attr('fieldMetadata')['scriptLanguage']);
        $t->same('vnd.sun.star.script:Standard.Module.Main?language=Basic&location=document', $script->attr('fieldMetadata')['href']);
        $t->same('simple', $script->attr('fieldMetadata')['xlinkType']);
        $t->same('ooo:Basic', $script->attr('attributes')['data-odf-field-script-language']);
        $t->same('Run import macro', $script->children[0]->attr('text'));

        $t->same('execute-macro', $macro->attr('fieldType'));
        $t->same('Standard.Module.PublishReview', $macro->attr('fieldName'));
        $t->same('Standard.Module.PublishReview', $macro->attr('fieldMetadata')['name']);
        $t->same('Publish review', $macro->children[0]->attr('text'));

        $t->same('dde-connection', $dde->attr('fieldType'));
        $t->same('ReviewSheet', $dde->attr('fieldMetadata')['connectionName']);
        $t->same('soffice', $dde->attr('fieldMetadata')['ddeApplication']);
        $t->same('Documents/review.ods', $dde->attr('fieldMetadata')['ddeTopic']);
        $t->same('Approved.A2', $dde->attr('fieldMetadata')['ddeItem']);
        $t->same(false, $dde->attr('fieldMetadata')['automaticUpdate']);
        $t->same('keep-text', $dde->attr('fieldMetadata')['conversionMode']);
        $t->same(true, $dde->attr('fieldMetadata')['declared']);
        $t->same('ReviewSheet', $dde->attr('attributes')['data-odf-field-connection-name']);
        $t->same('soffice', $dde->attr('attributes')['data-odf-field-dde-application']);
        $t->same('false', $dde->attr('attributes')['data-odf-field-automatic-update']);
        $t->same('Last approved row', $dde->children[0]->attr('text'));

        $t->same(1, $declarations['ddeConnectionDeclarationCount']);
        $t->same('ReviewSheet', $ddeDeclaration['name'] ?? null);
        $t->same('soffice', $ddeDeclaration['ddeApplication'] ?? null);
        $t->same('Documents/review.ods', $ddeDeclaration['ddeTopic'] ?? null);
        $t->same('Approved.A2', $ddeDeclaration['ddeItem'] ?? null);
        $t->same(false, $ddeDeclaration['automaticUpdate'] ?? null);
        $t->same('keep-text', $ddeDeclaration['conversionMode'] ?? null);
        $t->same(3, $result['importReport']['content']['fieldCount']);
        $t->same(1, $result['importReport']['content']['ddeConnectionDeclarationCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Run import macro]{.odf-field .odf-field-script data-odf-field-type="script" data-odf-field-href="vnd.sun.star.script:Standard.Module.Main?language=Basic&location=document" data-odf-field-xlink-type="simple" data-odf-field-script-language="ooo:Basic"}', $markdown);
        $t->contains('[Publish review]{.odf-field .odf-field-execute-macro data-odf-field-type="execute-macro" data-odf-field-name="Standard.Module.PublishReview"}', $markdown);
        $t->contains('[Last approved row]{.odf-field .odf-field-dde-connection data-odf-field-type="dde-connection" data-odf-field-connection-name="ReviewSheet" data-odf-field-dde-application="soffice" data-odf-field-dde-topic="Documents/review.ods" data-odf-field-dde-item="Approved.A2" data-odf-field-automatic-update="false" data-odf-field-conversion-mode="keep-text" data-odf-field-declared="true"}', $markdown);
        $t->contains('<span class="odf-field odf-field-script" data-odf-field-type="script" data-odf-field-href="vnd.sun.star.script:Standard.Module.Main?language=Basic&amp;location=document" data-odf-field-xlink-type="simple" data-odf-field-script-language="ooo:Basic">Run import macro</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-execute-macro" data-odf-field-type="execute-macro" data-odf-field-name="Standard.Module.PublishReview">Publish review</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-dde-connection" data-odf-field-type="dde-connection" data-odf-field-connection-name="ReviewSheet" data-odf-field-dde-application="soffice" data-odf-field-dde-topic="Documents/review.ods" data-odf-field-dde-item="Approved.A2" data-odf-field-automatic-update="false" data-odf-field-conversion-mode="keep-text" data-odf-field-declared="true">Last approved row</span>', $blocksHtml);
    },
    'maps ODT placeholders into review spans without dropping source text' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithPlaceholders = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Required <text:placeholder text:placeholder-type="text" text:description="Enter migration summary" text:style-name="PlaceholderStyle">migration summary</text:placeholder> and empty <text:placeholder text:placeholder-type="date" text:description="Pick import date">review date</text:placeholder>.</text:p>
      <text:h text:outline-level="2">Heading <text:placeholder text:placeholder-type="text">placeholder</text:placeholder></text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithPlaceholders));
        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $summary = $paragraph->children[1];
        $date = $paragraph->children[3];
        $headingPlaceholder = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Required migration summary and empty review date.', $paragraph->attr('text'));
        $t->same('span', $summary->type);
        $t->same(['odf-placeholder', 'odf-placeholder-text'], $summary->attr('classes'));
        $t->same('text', $summary->attr('placeholderType'));
        $t->same('migration summary', $summary->children[0]->attr('text'));
        $t->same([
            'type' => 'text',
            'description' => 'Enter migration summary',
            'styleName' => 'PlaceholderStyle',
        ], $summary->attr('placeholderMetadata'));
        $t->same([
            'data-odf-placeholder-type' => 'text',
            'data-odf-placeholder-description' => 'Enter migration summary',
            'data-odf-placeholder-style-name' => 'PlaceholderStyle',
        ], $summary->attr('attributes'));

        $t->same(['odf-placeholder', 'odf-placeholder-date'], $date->attr('classes'));
        $t->same('date', $date->attr('placeholderType'));
        $t->same('Pick import date', $date->attr('placeholderMetadata')['description']);
        $t->same('review date', $date->children[0]->attr('text'));

        $t->same('heading', $heading->type);
        $t->same('Heading ', $heading->children[0]->attr('text'));
        $t->same('placeholder', $headingPlaceholder->children[0]->attr('text'));
        $t->same(['odf-placeholder', 'odf-placeholder-text'], $headingPlaceholder->attr('classes'));
        $t->same(3, $result['importReport']['content']['placeholderCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[migration summary]{.odf-placeholder .odf-placeholder-text data-odf-placeholder-type="text" data-odf-placeholder-description="Enter migration summary" data-odf-placeholder-style-name="PlaceholderStyle"}', $markdown);
        $t->contains('<span class="odf-placeholder odf-placeholder-text" data-odf-placeholder-type="text" data-odf-placeholder-description="Enter migration summary" data-odf-placeholder-style-name="PlaceholderStyle">migration summary</span>', $blocksHtml);
    },
    'maps ODT bibliography marks into citation handoff nodes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithBibliographyMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Source cites <text:bibliography-mark text:identifier="smith1899" text:number="4">Smith source packet</text:bibliography-mark> and <text:bibliography-mark text:identifier="missing-source" text:number="5">missing source packet</text:bibliography-mark>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithBibliographyMarks));
        $paragraph = $result['document']->children[0];
        $knownCitation = $paragraph->children[1];
        $missingCitation = $paragraph->children[3];

        $t->same('paragraph', $paragraph->type);
        $t->same('Source cites Smith source packet and missing source packet.', $paragraph->attr('text'));
        $t->same('citation', $knownCitation->type);
        $t->same('smith1899', $knownCitation->attr('id'));
        $t->same('[@smith1899]', $knownCitation->attr('text'));
        $t->same('normal', $knownCitation->attr('mode'));
        $t->same('odt', $knownCitation->attr('sourceFormat'));
        $t->same('Smith source packet', $knownCitation->attr('displayText'));
        $t->same(4, $knownCitation->attr('citationNumber'));
        $t->same('Smith source packet', $knownCitation->children[0]->attr('text'));
        $t->same('citation', $missingCitation->type);
        $t->same('missing-source', $missingCitation->attr('id'));
        $t->same('missing source packet', $missingCitation->attr('displayText'));
        $t->same(5, $missingCitation->attr('citationNumber'));
        $t->same(2, $result['importReport']['content']['citationCount']);

        $processor = CitationCslProcessor::fromItems([[
            'id' => 'smith1899',
            'title' => 'Source Packet',
            'author' => [['family' => 'Smith', 'given' => 'Ada']],
            'issued' => ['date-parts' => [[1899]]],
        ]]);
        $processed = $processor->apply($result['document']);
        $t->same('(Smith 1899)', $processed->children[0]->children[1]->attr('rendered'));
        $t->same('[@missing-source]', $processed->children[0]->children[3]->attr('rendered'));
        $t->same(['smith1899', 'missing-source'], $processor->citationIds($result['document']));
        $t->same(['missing-source'], $processor->missingCitationIds($result['document']));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $processedBlocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('Source cites [@smith1899] and [@missing-source].', $markdown);
        $t->contains('<span class="pandoc-citation" data-pandoc-citation-id="smith1899"', $blocksHtml);
        $t->contains('<span class="pandoc-citation" data-pandoc-citation-id="missing-source"', $blocksHtml);
        $t->contains('<p>Source cites (Smith 1899) and [@missing-source].</p>', $processedBlocks);
    },
    'maps ODT table of contents into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTableOfContents = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:table-of-content text:name="Source Navigation" text:style-name="Contents_20_1" text:protected="true" text:protection-key="toc-key" text:protection-key-digest-algorithm="urn:odf:sha1">
        <text:table-of-content-source text:outline-level="3" text:relative-tab-stop-position="true" text:use-index-marks="false" text:use-index-source-styles="true">
          <text:index-title-template text:style-name="ContentsTitle">Contents</text:index-title-template>
          <text:table-of-content-entry-template text:outline-level="1" text:style-name="ContentsEntry">
            <text:index-entry-link-start/>
            <text:index-entry-text/>
            <text:index-entry-tab-stop style:type="right" style:position="17cm" style:leader-char="."/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
          </text:table-of-content-entry-template>
          <text:index-source-styles text:outline-level="1">
            <text:index-source-style text:style-name="ImportHeading"/>
          </text:index-source-styles>
        </text:table-of-content-source>
        <text:index-title text:name="Table of Contents">
          <text:p>Contents</text:p>
        </text:index-title>
        <text:index-body>
          <text:p><text:a xlink:href="#odt-source-packet">ODT source packet</text:a><text:tab/>1</text:p>
          <text:p><text:a xlink:href="#review">Review table</text:a><text:tab/>2</text:p>
        </text:index-body>
      </text:table-of-content>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTableOfContents));
        $blocks = $result['document']->children;

        $t->same(1, count($blocks));
        $toc = $blocks[0];
        $t->same('div', $toc->type);
        $t->same('source-navigation', $toc->attr('id'));
        $t->same(['odf-table-of-contents', 'odf-protected-table-of-contents'], $toc->attr('classes'));
        $t->same('Source Navigation', $toc->attr('tableOfContentsName'));
        $t->same('Contents_20_1', $toc->attr('styleName'));
        $t->same(true, $toc->attr('protected'));
        $t->same(true, $toc->attr('protectionKeyPresent'));
        $t->same('urn:odf:sha1', $toc->attr('protectionKeyDigestAlgorithm'));

        $source = $toc->attr('tableOfContentsSource');
        $t->same(3, $source['outlineLevel']);
        $t->same(true, $source['relativeTabStopPosition']);
        $t->same(false, $source['useIndexMarks']);
        $t->same(true, $source['useIndexSourceStyles']);
        $t->same([['outlineLevel' => 1, 'styleNames' => ['ImportHeading']]], $source['sourceStyles']);
        $t->same('title', $source['templates'][0]['type']);
        $t->same('ContentsTitle', $source['templates'][0]['styleName']);
        $t->same('entry', $source['templates'][1]['type']);
        $t->same(1, $source['templates'][1]['outlineLevel']);
        $t->same('ContentsEntry', $source['templates'][1]['styleName']);
        $t->same(['index-entry-link-start', 'index-entry-text', 'index-entry-tab-stop', 'index-entry-page-number', 'index-entry-link-end'], array_column($source['templates'][1]['components'], 'type'));
        $t->same('right', $source['templates'][1]['components'][2]['tabStopType']);
        $t->same('17cm', $source['templates'][1]['components'][2]['tabStopPosition']);
        $t->same('.', $source['templates'][1]['components'][2]['leaderChar']);

        $attributes = $toc->attr('attributes');
        $t->same('Source Navigation', $attributes['data-odf-toc-name']);
        $t->same('Contents_20_1', $attributes['data-odf-toc-style-name']);
        $t->same('true', $attributes['data-odf-toc-protected']);
        $t->same('true', $attributes['data-odf-toc-protection-key-present']);
        $t->same('urn:odf:sha1', $attributes['data-odf-toc-protection-key-digest-algorithm']);
        $t->same('3', $attributes['data-odf-toc-source-outline-level']);
        $t->same('true', $attributes['data-odf-toc-source-relative-tab-stop-position']);
        $t->same('false', $attributes['data-odf-toc-source-use-index-marks']);
        $t->same('true', $attributes['data-odf-toc-source-use-index-source-styles']);
        $t->same('1', $attributes['data-odf-toc-source-style-count']);
        $t->same('2', $attributes['data-odf-toc-template-count']);

        $title = $toc->children[0];
        $body = $toc->children[1];
        $t->same('div', $title->type);
        $t->same(['odf-index-title'], $title->attr('classes'));
        $t->same('true', $title->attr('attributes')['data-odf-index-title']);
        $t->same('Contents', $title->children[0]->attr('text'));
        $t->same('div', $body->type);
        $t->same(['odf-index-body'], $body->attr('classes'));
        $t->same('true', $body->attr('attributes')['data-odf-index-body']);
        $t->same('#odt-source-packet', $body->children[0]->children[0]->attr('url'));
        $t->same('ODT source packet', $body->children[0]->children[0]->children[0]->attr('text'));
        $t->same('#review', $body->children[1]->children[0]->attr('url'));
        $t->same(1, $result['importReport']['content']['tableOfContentsCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#source-navigation .odf-table-of-contents .odf-protected-table-of-contents data-odf-toc-name="Source Navigation"', $markdown);
        $t->contains('data-odf-toc-source-use-index-marks="false"', $markdown);
        $t->contains('[ODT source packet](#odt-source-packet)', $markdown);
        $t->contains('<div id="source-navigation" class="odf-table-of-contents odf-protected-table-of-contents" data-odf-toc-name="Source Navigation"', $blocksHtml);
        $t->contains('data-odf-toc-source-style-count="1"', $blocksHtml);
        $t->contains('<div class="odf-index-title" data-odf-index-title="true"><p>Contents</p></div>', $blocksHtml);
        $t->contains('<a href="#odt-source-packet">ODT source packet</a>', $blocksHtml);
    },
    'maps ODT generated indexes beyond table of contents into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithGeneratedIndexes = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:illustration-index text:name="Figure Review" text:style-name="IllustrationIndex" text:protected="true" text:protection-key="figure-key" text:protection-key-digest-algorithm="urn:odf:sha256">
        <text:illustration-index-source text:caption-sequence-name="Illustration" text:use-caption="true">
          <text:index-title-template text:style-name="FigureTitle">Figures</text:index-title-template>
          <text:illustration-index-entry-template text:style-name="FigureEntry">
            <text:index-entry-link-start/>
            <text:index-entry-chapter/>
            <text:index-entry-text/>
            <text:index-entry-tab-stop style:type="right" style:position="16cm" style:leader-char="."/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
          </text:illustration-index-entry-template>
        </text:illustration-index-source>
        <text:index-title text:name="Illustrations">
          <text:p>Figures</text:p>
        </text:index-title>
        <text:index-body>
          <text:p><text:a xlink:href="#source-hero-seq">Figure 1</text:a><text:tab/>2</text:p>
        </text:index-body>
      </text:illustration-index>
      <text:alphabetical-index text:name="Glossary Terms" text:style-name="AlphabeticalIndex">
        <text:alphabetical-index-source text:main-entry-style-name="MainTerm" text:ignore-case="true" text:alphabetical-separators="true" text:combine-entries="true" text:combine-entries-with-dash="false" text:sort-algorithm="alphanumeric">
          <text:alphabetical-index-entry-template text:outline-level="1" text:style-name="GlossaryEntry">
            <text:index-entry-text/>
            <text:index-entry-page-number/>
          </text:alphabetical-index-entry-template>
        </text:alphabetical-index-source>
        <text:index-title text:name="Glossary">
          <text:p>Glossary</text:p>
        </text:index-title>
        <text:index-body>
          <text:p>Migration 5</text:p>
        </text:index-body>
      </text:alphabetical-index>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithGeneratedIndexes));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));

        $illustration = $blocks[0];
        $t->same('div', $illustration->type);
        $t->same('figure-review', $illustration->attr('id'));
        $t->same(['odf-generated-index', 'odf-illustration-index', 'odf-protected-generated-index'], $illustration->attr('classes'));
        $t->same('illustration', $illustration->attr('generatedIndexType'));
        $t->same('illustration-index', $illustration->attr('generatedIndexElement'));
        $t->same('Figure Review', $illustration->attr('generatedIndexName'));
        $t->same('IllustrationIndex', $illustration->attr('styleName'));
        $t->same(true, $illustration->attr('protected'));
        $t->same(true, $illustration->attr('protectionKeyPresent'));
        $t->same('urn:odf:sha256', $illustration->attr('protectionKeyDigestAlgorithm'));

        $source = $illustration->attr('generatedIndexSource');
        $t->same('illustration-index-source', $source['element']);
        $t->same('Illustration', $source['captionSequenceName']);
        $t->same(true, $source['useCaption']);
        $t->same('title', $source['templates'][0]['type']);
        $t->same('index-title-template', $source['templates'][0]['element']);
        $t->same('FigureTitle', $source['templates'][0]['styleName']);
        $t->same('entry', $source['templates'][1]['type']);
        $t->same('illustration-index-entry-template', $source['templates'][1]['element']);
        $t->same('FigureEntry', $source['templates'][1]['styleName']);
        $t->same(['index-entry-link-start', 'index-entry-chapter', 'index-entry-text', 'index-entry-tab-stop', 'index-entry-page-number', 'index-entry-link-end'], array_column($source['templates'][1]['components'], 'type'));
        $t->same('right', $source['templates'][1]['components'][3]['tabStopType']);
        $t->same('16cm', $source['templates'][1]['components'][3]['tabStopPosition']);
        $t->same('.', $source['templates'][1]['components'][3]['leaderChar']);

        $attributes = $illustration->attr('attributes');
        $t->same('illustration', $attributes['data-odf-index-type']);
        $t->same('illustration-index', $attributes['data-odf-index-element']);
        $t->same('Figure Review', $attributes['data-odf-index-name']);
        $t->same('true', $attributes['data-odf-index-protected']);
        $t->same('true', $attributes['data-odf-index-source-use-caption']);
        $t->same('Illustration', $attributes['data-odf-index-source-caption-sequence-name']);
        $t->same('2', $attributes['data-odf-index-template-count']);

        $title = $illustration->children[0];
        $body = $illustration->children[1];
        $t->same(['odf-index-title'], $title->attr('classes'));
        $t->same('Figures', $title->children[0]->attr('text'));
        $t->same(['odf-index-body'], $body->attr('classes'));
        $t->same('#source-hero-seq', $body->children[0]->children[0]->attr('url'));
        $t->same('Figure 1', $body->children[0]->children[0]->children[0]->attr('text'));

        $alphabetical = $blocks[1];
        $t->same(['odf-generated-index', 'odf-alphabetical-index'], $alphabetical->attr('classes'));
        $t->same('alphabetical', $alphabetical->attr('generatedIndexType'));
        $alphabeticalSource = $alphabetical->attr('generatedIndexSource');
        $t->same('alphabetical-index-source', $alphabeticalSource['element']);
        $t->same('MainTerm', $alphabeticalSource['mainEntryStyleName']);
        $t->same(true, $alphabeticalSource['ignoreCase']);
        $t->same(true, $alphabeticalSource['alphabeticalSeparators']);
        $t->same(true, $alphabeticalSource['combineEntries']);
        $t->same(false, $alphabeticalSource['combineEntriesWithDash']);
        $t->same('alphanumeric', $alphabeticalSource['sortAlgorithm']);
        $t->same(1, $alphabeticalSource['templates'][0]['outlineLevel']);
        $t->same('GlossaryEntry', $alphabeticalSource['templates'][0]['styleName']);
        $t->same('Glossary', $alphabetical->children[0]->children[0]->attr('text'));
        $t->same('Migration 5', $alphabetical->children[1]->children[0]->attr('text'));

        $t->same(2, $result['importReport']['content']['generatedIndexCount']);
        $t->same(0, $result['importReport']['content']['tableOfContentsCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#figure-review .odf-generated-index .odf-illustration-index .odf-protected-generated-index data-odf-index-type="illustration"', $markdown);
        $t->contains('data-odf-index-source-caption-sequence-name="Illustration"', $markdown);
        $t->contains('[Figure 1](#source-hero-seq)', $markdown);
        $t->contains('<div id="figure-review" class="odf-generated-index odf-illustration-index odf-protected-generated-index" data-odf-index-type="illustration"', $blocksHtml);
        $t->contains('data-odf-index-source-use-caption="true"', $blocksHtml);
        $t->contains('<a href="#source-hero-seq">Figure 1</a>', $blocksHtml);
        $t->contains('<div id="glossary-terms" class="odf-generated-index odf-alphabetical-index" data-odf-index-type="alphabetical"', $blocksHtml);
    },
    'preserves ODT index entry template component metadata for review packets' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithIndexTemplateComponents = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:table-of-content text:name="Detailed Navigation">
        <text:table-of-content-source text:outline-level="2">
          <text:table-of-content-entry-template text:outline-level="1" text:style-name="DetailedContentsEntry">
            <text:index-entry-link-start xlink:href="#review-heading" xlink:type="simple" xlink:show="replace" xlink:actuate="onRequest"/>
            <text:index-entry-chapter text:style-name="ChapterRef" text:outline-level="2" text:display="number-and-name" text:chapter-format="number"/>
            <text:index-entry-text text:style-name="EntryText"/>
            <text:index-entry-tab-stop style:type="right" style:position="15cm" style:leader-char="." style:leader-text="·"/>
            <text:index-entry-page-number text:style-name="PageRef"/>
            <text:index-entry-link-end/>
          </text:table-of-content-entry-template>
        </text:table-of-content-source>
        <text:index-title><text:p>Navigation</text:p></text:index-title>
        <text:index-body><text:p><text:a xlink:href="#review-heading">Review heading</text:a> 3</text:p></text:index-body>
      </text:table-of-content>
      <text:bibliography text:name="Bibliography Review">
        <text:bibliography-source>
          <text:bibliography-entry-template text:style-name="BibliographyEntry">
            <text:index-entry-bibliography text:bibliography-data-field="author" text:style-name="BibAuthor"/>
            <text:index-entry-text text:style-name="BibText"/>
            <text:index-entry-tab-stop style:type="right" style:position="13cm" style:leader-char="_" style:leader-text=""/>
            <text:index-entry-page-number text:style-name="BibPage"/>
          </text:bibliography-entry-template>
        </text:bibliography-source>
        <text:index-title><text:p>Bibliography</text:p></text:index-title>
        <text:index-body><text:p>Migration Desk 5</text:p></text:index-body>
      </text:bibliography>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithIndexTemplateComponents));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $toc = $blocks[0];
        $tocSource = $toc->attr('tableOfContentsSource');
        $tocComponents = $tocSource['templates'][0]['components'];
        $t->same('Detailed Navigation', $toc->attr('tableOfContentsName'));
        $t->same('entry', $tocSource['templates'][0]['type']);
        $t->same('DetailedContentsEntry', $tocSource['templates'][0]['styleName']);
        $t->same(['index-entry-link-start', 'index-entry-chapter', 'index-entry-text', 'index-entry-tab-stop', 'index-entry-page-number', 'index-entry-link-end'], array_column($tocComponents, 'type'));
        $t->same('#review-heading', $tocComponents[0]['href']);
        $t->same('simple', $tocComponents[0]['xlinkType']);
        $t->same('replace', $tocComponents[0]['xlinkShow']);
        $t->same('onRequest', $tocComponents[0]['xlinkActuate']);
        $t->same('ChapterRef', $tocComponents[1]['styleName']);
        $t->same(2, $tocComponents[1]['outlineLevel']);
        $t->same('number-and-name', $tocComponents[1]['display']);
        $t->same('number', $tocComponents[1]['chapterFormat']);
        $t->same('EntryText', $tocComponents[2]['styleName']);
        $t->same('right', $tocComponents[3]['tabStopType']);
        $t->same('15cm', $tocComponents[3]['tabStopPosition']);
        $t->same('.', $tocComponents[3]['leaderChar']);
        $t->same('·', $tocComponents[3]['leaderText']);
        $t->same('PageRef', $tocComponents[4]['styleName']);

        $bibliography = $blocks[1];
        $bibliographySource = $bibliography->attr('generatedIndexSource');
        $bibliographyComponents = $bibliographySource['templates'][0]['components'];
        $t->same('bibliography', $bibliography->attr('generatedIndexType'));
        $t->same('bibliography-source', $bibliographySource['element']);
        $t->same('BibliographyEntry', $bibliographySource['templates'][0]['styleName']);
        $t->same(['index-entry-bibliography', 'index-entry-text', 'index-entry-tab-stop', 'index-entry-page-number'], array_column($bibliographyComponents, 'type'));
        $t->same('author', $bibliographyComponents[0]['bibliographyDataField']);
        $t->same('BibAuthor', $bibliographyComponents[0]['styleName']);
        $t->same('BibText', $bibliographyComponents[1]['styleName']);
        $t->same('13cm', $bibliographyComponents[2]['tabStopPosition']);
        $t->same('_', $bibliographyComponents[2]['leaderChar']);
        $t->true(!isset($bibliographyComponents[2]['leaderText']), 'Empty leader text should not produce a metadata key');
        $t->same('BibPage', $bibliographyComponents[3]['styleName']);
        $t->same(1, $result['importReport']['content']['tableOfContentsCount']);
        $t->same(1, $result['importReport']['content']['generatedIndexCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#detailed-navigation .odf-table-of-contents data-odf-toc-name="Detailed Navigation"', $markdown);
        $t->contains('::: {#bibliography-review .odf-generated-index .odf-bibliography data-odf-index-type="bibliography"', $markdown);
        $t->contains('<div id="detailed-navigation" class="odf-table-of-contents" data-odf-toc-name="Detailed Navigation"', $blocksHtml);
        $t->contains('<div id="bibliography-review" class="odf-generated-index odf-bibliography" data-odf-index-type="bibliography"', $blocksHtml);
    },
    'maps ODT inline index marks into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithInlineIndexMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Navigation <text:toc-mark text:string-value="ODT source packet" text:outline-level="1"/> term <text:alphabetical-index-mark-start text:id="idx-claim" text:string-value="source claim" text:key1="Migration" text:key2="ODT" text:main-entry="true"/>source claim<text:alphabetical-index-mark-end text:id="idx-claim"/> and <text:user-index-mark text:index-name="Reviewer Terms" text:string-value="Data Liberation"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithInlineIndexMarks));
        $paragraph = $result['document']->children[0];
        $tocMark = $paragraph->children[1];
        $alphabeticalMark = $paragraph->children[3];
        $userMark = $paragraph->children[5];

        $t->same('Navigation ODT source packet term source claim and Data Liberation.', $paragraph->attr('text'));
        $t->same('span', $tocMark->type);
        $t->same(['odf-index-mark', 'odf-index-mark-toc'], $tocMark->attr('classes'));
        $t->same('toc', $tocMark->attr('indexMarkType'));
        $t->same('toc-mark', $tocMark->attr('indexMarkElement'));
        $t->same('ODT source packet', $tocMark->children[0]->attr('text'));
        $t->same('ODT source packet', $tocMark->attr('indexMarkMetadata')['stringValue']);
        $t->same(1, $tocMark->attr('indexMarkMetadata')['outlineLevel']);

        $t->same('span', $alphabeticalMark->type);
        $t->same(['odf-index-mark', 'odf-index-mark-alphabetical'], $alphabeticalMark->attr('classes'));
        $t->same('alphabetical', $alphabeticalMark->attr('indexMarkType'));
        $t->same('alphabetical-index-mark-start', $alphabeticalMark->attr('indexMarkElement'));
        $t->same('source claim', $alphabeticalMark->children[0]->attr('text'));
        $t->same('idx-claim', $alphabeticalMark->attr('indexMarkMetadata')['id']);
        $t->same('Migration', $alphabeticalMark->attr('indexMarkMetadata')['key1']);
        $t->same('ODT', $alphabeticalMark->attr('indexMarkMetadata')['key2']);
        $t->same(true, $alphabeticalMark->attr('indexMarkMetadata')['mainEntry']);

        $t->same('span', $userMark->type);
        $t->same(['odf-index-mark', 'odf-index-mark-user'], $userMark->attr('classes'));
        $t->same('user', $userMark->attr('indexMarkType'));
        $t->same('Reviewer Terms', $userMark->attr('indexMarkMetadata')['indexName']);
        $t->same('Data Liberation', $userMark->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['indexMarkCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[ODT source packet]{.odf-index-mark .odf-index-mark-toc data-odf-index-mark-type="toc"', $markdown);
        $t->contains('[source claim]{.odf-index-mark .odf-index-mark-alphabetical data-odf-index-mark-type="alphabetical"', $markdown);
        $t->contains('data-odf-index-mark-main-entry="true"', $markdown);
        $t->contains('<span class="odf-index-mark odf-index-mark-toc" data-odf-index-mark-type="toc" data-odf-index-mark-element="toc-mark" data-odf-index-mark-string-value="ODT source packet" data-odf-index-mark-outline-level="1">ODT source packet</span>', $blocksHtml);
        $t->contains('<span class="odf-index-mark odf-index-mark-alphabetical" data-odf-index-mark-type="alphabetical" data-odf-index-mark-element="alphabetical-index-mark-start" data-odf-index-mark-id="idx-claim" data-odf-index-mark-string-value="source claim" data-odf-index-mark-key1="Migration" data-odf-index-mark-key2="ODT" data-odf-index-mark-main-entry="true">source claim</span>', $blocksHtml);
        $t->contains('<span class="odf-index-mark odf-index-mark-user" data-odf-index-mark-type="user" data-odf-index-mark-element="user-index-mark" data-odf-index-mark-index-name="Reviewer Terms" data-odf-index-mark-string-value="Data Liberation">Data Liberation</span>', $blocksHtml);
    },
    'maps ODT table caption paragraph styles into caption divs' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTableCaptionStyle = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:automatic-styles>
    <style:style style:name="CaptionStrong" style:family="text">
      <style:text-properties fo:font-weight="bold" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:p text:style-name="Table">Table <text:span text:style-name="CaptionStrong">1</text:span>: Source media audit</text:p>
      <text:p text:style-name="BodyText">Following paragraph stays ordinary.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTableCaptionStyle));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $caption = $blocks[0];
        $t->same('div', $caption->type);
        $t->same(['caption', 'odf-table-caption'], $caption->attr('classes'));
        $t->same('Table', $caption->attr('styleName'));
        $t->same(true, $caption->attr('tableCaption'));
        $t->same('Table 1: Source media audit', $caption->attr('text'));
        $t->same('Table', $caption->attr('attributes')['data-odf-table-caption-style-name']);
        $t->same('paragraph', $caption->children[0]->type);
        $t->same('Table 1: Source media audit', $caption->children[0]->attr('text'));
        $t->same('strong', $caption->children[0]->children[1]->type);
        $t->same('paragraph', $blocks[1]->type);
        $t->same('Following paragraph stays ordinary.', $blocks[1]->attr('text'));
        $t->same(1, $result['importReport']['content']['tableCaptionCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {.caption .odf-table-caption data-odf-table-caption-style-name="Table"}', $markdown);
        $t->contains('Table **[1]{data-odf-style-name="CaptionStrong"}**: Source media audit', $markdown);
        $t->contains('<div class="caption odf-table-caption" data-odf-table-caption-style-name="Table"><p>Table <strong><span data-odf-style-name="CaptionStrong">1</span></strong>: Source media audit</p></div>', $blocksHtml);
    },
    'attaches following ODT table caption paragraphs to table nodes like upstream post process' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFollowingTableCaption = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:automatic-styles>
    <style:style style:name="CaptionStrong" style:family="text">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <table:table table:name="Source review">
        <table:table-row>
          <table:table-cell><text:p>Asset</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Hero image</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
      <text:p text:style-name="Table">Table <text:span text:style-name="CaptionStrong">1</text:span>: Source media audit</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFollowingTableCaption));
        $blocks = $result['document']->children;

        $t->same(1, count($blocks));
        $table = $blocks[0];
        $t->same('table', $table->type);
        $t->same('Source review', $table->attr('tableName'));
        $t->same('Table 1: Source media audit', $table->attr('caption'));
        $t->same(true, $table->attr('odfCaptionParagraph'));
        $t->same('odf-table-caption-paragraph', $table->attr('captionSource')['source']);
        $t->same('following-table', $table->attr('captionSource')['sourcePosition']);
        $t->same('Table', $table->attr('captionSource')['styleName']);
        $t->same('Table', $table->attr('captionSource')['sourceAttributes']['attributes']['data-odf-table-caption-style-name']);
        $t->same('following-paragraph', $table->attr('captionSource')['sourceAttributes']['attributes']['data-odf-table-caption-source']);
        $t->same('Table 1: Source media audit', $table->attr('captionBlocks')[0]->attr('text'));
        $t->same('strong', $table->attr('captionInlines')[1]->type);
        $t->same('Table 1: Source media audit', $table->attr('tableGeometry')['caption']);
        $t->same('captionBlocks', $table->attr('tableGeometry')['captions']['long']['source']);
        $t->same('text:p', $table->attr('tableGeometry')['captions']['long']['sourceElement']);
        $t->same('following-table', $table->attr('tableGeometry')['captions']['long']['sourcePosition']);
        $t->same(1, $result['importReport']['content']['tableCaptionCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': Table **[1]{data-odf-style-name="CaptionStrong"}**: Source media audit', $markdown);
        $t->contains('<figcaption data-odf-table-caption-source="following-paragraph" data-odf-table-caption-style-name="Table" class="odf-table-caption wp-element-caption">Table <strong><span data-odf-style-name="CaptionStrong">1</span></strong>: Source media audit</figcaption>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, '<div class="caption odf-table-caption"'), 'Following ODT table captions should not remain standalone divs after a table');
    },
    'maps ODT linked and protected sections into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLinkedSections = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:section text:name="Imported Appendix" text:style-name="LinkedSection" text:protected="true" text:protection-key="sha1-key" text:protection-key-digest-algorithm="http://www.w3.org/2000/09/xmldsig#sha1">
        <text:section-source xlink:href="Sections/appendix.odt" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" text:section-name="Appendix Source" text:filter-name="writer8"/>
        <text:p>Linked appendix fallback.</text:p>
      </text:section>
      <text:section text:name="Local Sidebar">
        <text:p>Local sidebar text.</text:p>
      </text:section>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLinkedSections));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $linked = $blocks[0];
        $local = $blocks[1];
        $t->same('div', $linked->type);
        $t->same('imported-appendix', $linked->attr('id'));
        $t->same(['odf-section', 'odf-linked-section', 'odf-protected-section'], $linked->attr('classes'));
        $t->same('LinkedSection', $linked->attr('styleName'));
        $t->same(true, $linked->attr('protected'));
        $t->same(true, $linked->attr('protectionKeyPresent'));
        $t->same('Sections/appendix.odt', $linked->attr('sectionSource')['href']);
        $t->same('Appendix Source', $linked->attr('sectionSource')['sectionName']);
        $t->same('writer8', $linked->attr('sectionSource')['filterName']);
        $t->same('simple', $linked->attr('sectionSource')['type']);
        $t->same('embed', $linked->attr('sectionSource')['show']);
        $t->same('onLoad', $linked->attr('sectionSource')['actuate']);
        $t->same('Linked appendix fallback.', $linked->children[0]->attr('text'));
        $t->same('Imported Appendix', $linked->attr('attributes')['data-odf-section-name']);
        $t->same('LinkedSection', $linked->attr('attributes')['data-odf-section-style-name']);
        $t->same('true', $linked->attr('attributes')['data-odf-section-protected']);
        $t->same('true', $linked->attr('attributes')['data-odf-section-protection-key-present']);
        $t->same('http://www.w3.org/2000/09/xmldsig#sha1', $linked->attr('attributes')['data-odf-section-protection-key-digest-algorithm']);
        $t->same('Sections/appendix.odt', $linked->attr('attributes')['data-odf-section-source-href']);
        $t->same('Appendix Source', $linked->attr('attributes')['data-odf-section-source-name']);
        $t->same('writer8', $linked->attr('attributes')['data-odf-section-source-filter-name']);
        $t->same('simple', $linked->attr('attributes')['data-odf-section-source-type']);
        $t->same('embed', $linked->attr('attributes')['data-odf-section-source-show']);
        $t->same('onLoad', $linked->attr('attributes')['data-odf-section-source-actuate']);
        $t->same('div', $local->type);
        $t->same(['odf-section'], $local->attr('classes'));
        $t->same('Local sidebar text.', $local->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['sectionCount']);
        $t->same(1, $result['importReport']['content']['linkedSectionCount']);
        $t->same(1, $result['importReport']['content']['protectedSectionCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#imported-appendix .odf-section .odf-linked-section .odf-protected-section data-odf-section-name="Imported Appendix"', $markdown);
        $t->contains('data-odf-section-source-href="Sections/appendix.odt"', $markdown);
        $t->contains('data-odf-section-protection-key-present="true"', $markdown);
        $t->contains('<div id="imported-appendix" class="odf-section odf-linked-section odf-protected-section" data-odf-section-name="Imported Appendix"', $blocksHtml);
        $t->contains('data-odf-section-source-href="Sections/appendix.odt"', $blocksHtml);
        $t->contains('data-odf-section-protection-key-present="true"', $blocksHtml);
        $t->contains('<p>Linked appendix fallback.</p>', $blocksHtml);
    },
    'maps ODT conditional and hidden sections into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithConditionalSections = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:section text:name="Conditional Appendix" text:style-name="ReviewCondition" text:condition="ReviewStatus == &quot;ready&quot;" text:is-hidden="false" text:display="condition">
        <text:p>Conditional appendix remains reviewable.</text:p>
      </text:section>
      <text:section text:name="Draft Only" text:condition="DraftOnly" text:is-hidden="true">
        <text:p>Hidden draft context remains available for audit.</text:p>
      </text:section>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithConditionalSections));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $conditional = $blocks[0];
        $hidden = $blocks[1];
        $t->same('div', $conditional->type);
        $t->same('conditional-appendix', $conditional->attr('id'));
        $t->same(['odf-section', 'odf-conditional-section'], $conditional->attr('classes'));
        $t->same('ReviewStatus == "ready"', $conditional->attr('sectionCondition'));
        $t->same(false, $conditional->attr('sectionHidden'));
        $t->same('condition', $conditional->attr('sectionDisplay'));
        $t->same('ReviewStatus == "ready"', $conditional->attr('attributes')['data-odf-section-condition']);
        $t->same('false', $conditional->attr('attributes')['data-odf-section-hidden']);
        $t->same('condition', $conditional->attr('attributes')['data-odf-section-display']);
        $t->same('Conditional appendix remains reviewable.', $conditional->children[0]->attr('text'));
        $t->same('div', $hidden->type);
        $t->same('draft-only', $hidden->attr('id'));
        $t->same(['odf-section', 'odf-conditional-section', 'odf-hidden-section'], $hidden->attr('classes'));
        $t->same('DraftOnly', $hidden->attr('sectionCondition'));
        $t->same(true, $hidden->attr('sectionHidden'));
        $t->same('true', $hidden->attr('attributes')['data-odf-section-hidden']);
        $t->same('Hidden draft context remains available for audit.', $hidden->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['sectionCount']);
        $t->same(2, $result['importReport']['content']['conditionalSectionCount']);
        $t->same(1, $result['importReport']['content']['hiddenSectionCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#conditional-appendix .odf-section .odf-conditional-section data-odf-section-name="Conditional Appendix"', $markdown);
        $t->contains('data-odf-section-condition="ReviewStatus == \\"ready\\""', $markdown);
        $t->contains('data-odf-section-hidden="false"', $blocksHtml);
        $t->contains('<div id="draft-only" class="odf-section odf-conditional-section odf-hidden-section" data-odf-section-name="Draft Only" data-odf-section-condition="DraftOnly" data-odf-section-hidden="true">', $blocksHtml);
    },
    'maps ODT tracked changes into review spans and import report metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTrackedChanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <text:tracked-changes>
        <text:changed-region text:id="ct-ins">
          <text:insertion>
            <office:change-info>
              <dc:creator>Editor A</dc:creator>
              <dc:date>2026-06-05T00:10:00Z</dc:date>
              <text:p>Inserted during source review.</text:p>
            </office:change-info>
          </text:insertion>
        </text:changed-region>
        <text:changed-region text:id="ct-del">
          <text:deletion>
            <office:change-info>
              <dc:creator>Editor B</dc:creator>
              <dc:date>2026-06-05T00:12:00Z</dc:date>
            </office:change-info>
            <text:p>legacy deleted claim</text:p>
          </text:deletion>
        </text:changed-region>
        <text:changed-region text:id="ct-fmt">
          <text:format-change>
            <office:change-info>
              <dc:creator>Editor C</dc:creator>
              <dc:date>2026-06-05T00:14:00Z</dc:date>
            </office:change-info>
          </text:format-change>
        </text:changed-region>
      </text:tracked-changes>
      <text:p>Stable <text:change-start text:change-id="ct-ins"/>inserted review text<text:change-end text:change-id="ct-ins"/> and <text:change text:change-id="ct-del"/> plus <text:change-start text:change-id="ct-fmt"/>formatted cue<text:change-end text:change-id="ct-fmt"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTrackedChanges));
        $paragraph = $result['document']->children[0];
        $changesById = [];
        foreach ($result['trackedChanges'] as $change) {
            $changesById[$change['id']] = $change;
        }

        $t->same('paragraph', $paragraph->type);
        $t->same('Stable inserted review text and legacy deleted claim plus formatted cue.', $paragraph->attr('text'));
        $t->same(3, count($result['trackedChanges']));
        $t->same('insertion', $changesById['ct-ins']['type']);
        $t->same('Editor A', $changesById['ct-ins']['creator']);
        $t->same('2026-06-05T00:10:00Z', $changesById['ct-ins']['date']);
        $t->same(['Inserted during source review.'], $changesById['ct-ins']['comments']);
        $t->same('deletion', $changesById['ct-del']['type']);
        $t->same('legacy deleted claim', $changesById['ct-del']['text']);
        $t->same('format-change', $changesById['ct-fmt']['type']);

        $insertion = $paragraph->children[1];
        $deletion = $paragraph->children[3];
        $formatChange = $paragraph->children[5];
        $t->same('span', $insertion->type);
        $t->same(['odf-change', 'odf-insertion'], $insertion->attr('classes'));
        $t->same('ct-ins', $insertion->attr('attributes')['data-odf-change-id']);
        $t->same('insertion', $insertion->attr('attributes')['data-odf-change-type']);
        $t->same('Editor A', $insertion->attr('attributes')['data-odf-change-creator']);
        $t->same('inserted review text', $insertion->children[0]->attr('text'));
        $t->same(['odf-change', 'odf-deletion'], $deletion->attr('classes'));
        $t->same('legacy deleted claim', $deletion->children[0]->attr('text'));
        $t->same(['odf-change', 'odf-format-change'], $formatChange->attr('classes'));
        $t->same('formatted cue', $formatChange->children[0]->attr('text'));

        $t->same(3, $result['importReport']['trackedChanges']['count']);
        $t->same(3, $result['importReport']['content']['trackedChangeCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[inserted review text]{.odf-change .odf-insertion data-odf-change-id="ct-ins" data-odf-change-type="insertion" data-odf-change-creator="Editor A" data-odf-change-date="2026-06-05T00:10:00Z"}', $markdown);
        $t->contains('[legacy deleted claim]{.odf-change .odf-deletion data-odf-change-id="ct-del" data-odf-change-type="deletion" data-odf-change-creator="Editor B" data-odf-change-date="2026-06-05T00:12:00Z"}', $markdown);
        $t->contains('<span class="odf-change odf-insertion" data-odf-change-id="ct-ins" data-odf-change-type="insertion" data-odf-change-creator="Editor A" data-odf-change-date="2026-06-05T00:10:00Z">inserted review text</span>', $blocksHtml);
        $t->contains('<span class="odf-change odf-deletion" data-odf-change-id="ct-del" data-odf-change-type="deletion" data-odf-change-creator="Editor B" data-odf-change-date="2026-06-05T00:12:00Z">legacy deleted claim</span>', $blocksHtml);
    },
    'maps ODT tracked table changes into content declarations' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTrackedTableChanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <table:tracked-changes>
        <table:tracked-change table:id="tc-delete-row" table:acceptance-state="pending">
          <office:change-info>
            <dc:creator>Sheet Reviewer</dc:creator>
            <dc:date>2026-06-08T18:15:00Z</dc:date>
            <text:p>Deleted source row after import reconciliation.</text:p>
          </office:change-info>
          <table:deletion table:type="row" table:position="Review.3" table:table="Review"/>
        </table:tracked-change>
        <table:tracked-change table:id="tc-cell-value" table:acceptance-state="accepted" table:rejecting-change-id="tc-delete-row">
          <office:change-info>
            <dc:creator>Data Reviewer</dc:creator>
            <dc:date>2026-06-08T18:17:00Z</dc:date>
          </office:change-info>
          <table:cell-content-change table:cell-address="Review.B2" office:value-type="string" office:string-value="Ready">
            <table:previous table:cell-address="Review.B2" office:value-type="string" office:string-value="Draft"><text:p>Draft</text:p></table:previous>
          </table:cell-content-change>
        </table:tracked-change>
        <table:tracked-change table:id="tc-move-range">
          <office:change-info>
            <dc:creator>Data Reviewer</dc:creator>
            <dc:date>2026-06-08T18:19:00Z</dc:date>
          </office:change-info>
          <table:movement table:source-range-address="Review.A5:Review.B5" table:target-range-address="Review.A2:Review.B2"/>
        </table:tracked-change>
      </table:tracked-changes>
      <text:p>Table change metadata remains review-only.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTrackedTableChanges));
        $declarations = $result['contentDeclarations'];
        $documentDeclarations = $result['document']->attr('contentDeclarations');
        $changes = $declarations['tableTrackedChanges'];
        $changesById = $declarations['tableTrackedChangesById'];

        $t->same('Table change metadata remains review-only.', $result['document']->children[0]->attr('text'));
        $t->same(3, $declarations['tableTrackedChangeCount']);
        $t->same(['deletion' => 1, 'cell-content-change' => 1, 'movement' => 1], $declarations['tableTrackedChangeActionCounts']);
        $t->same($declarations, $documentDeclarations);
        $t->same(3, count($changes));
        $t->same('tc-delete-row', $changes[0]['id']);
        $t->same('pending', $changes[0]['acceptanceState']);
        $t->same('Sheet Reviewer', $changesById['tc-delete-row']['creator']);
        $t->same('2026-06-08T18:15:00Z', $changesById['tc-delete-row']['date']);
        $t->same(['Deleted source row after import reconciliation.'], $changesById['tc-delete-row']['comments']);
        $t->same('deletion', $changesById['tc-delete-row']['actionType']);
        $t->same('deletion', $changesById['tc-delete-row']['action']['element']);
        $t->same('row', $changesById['tc-delete-row']['action']['attributes']['type']);
        $t->same('Review.3', $changesById['tc-delete-row']['action']['attributes']['position']);
        $t->same('Review', $changesById['tc-delete-row']['action']['attributes']['table']);

        $cellChange = $changesById['tc-cell-value'];
        $t->same('accepted', $cellChange['acceptanceState']);
        $t->same('tc-delete-row', $cellChange['rejectingChangeId']);
        $t->same('cell-content-change', $cellChange['actionType']);
        $t->same('Review.B2', $cellChange['action']['attributes']['cellAddress']);
        $t->same('string', $cellChange['action']['attributes']['valueType']);
        $t->same('Ready', $cellChange['action']['attributes']['stringValue']);
        $t->same('previous', $cellChange['action']['previous'][0]['element']);
        $t->same('Draft', $cellChange['action']['previous'][0]['attributes']['stringValue']);
        $t->same('Draft', $cellChange['action']['previous'][0]['text']);

        $movement = $changesById['tc-move-range'];
        $t->same('movement', $movement['actionType']);
        $t->same('Review.A5:Review.B5', $movement['action']['attributes']['sourceRangeAddress']);
        $t->same('Review.A2:Review.B2', $movement['action']['attributes']['targetRangeAddress']);

        $t->same(3, $result['importReport']['contentDeclarations']['tableTrackedChangeCount']);
        $t->same(['deletion' => 1, 'cell-content-change' => 1, 'movement' => 1], $result['importReport']['contentDeclarations']['tableTrackedChangeActionCounts']);
        $t->same(3, $result['importReport']['content']['tableTrackedChangeCount']);
        $t->same(0, $result['importReport']['content']['trackedChangeCount']);
    },
    'maps ODT embedded MathML objects into display math handoff nodes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $contentWithMathObjects = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Inline formula <draw:frame draw:name="Inline formula"><draw:object xlink:href="./Object 1"/></draw:frame> preserved.</text:p>
      <draw:frame draw:name="Display formula"><draw:object xlink:href="Object 2"/></draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $mathObjectOne = <<<'XML'
<office:document
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:body>
    <office:math>
      <math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">
        <semantics>
          <mrow><mi>x</mi><mo>=</mo><mn>1</mn></mrow>
          <annotation encoding="application/x-tex">x=1</annotation>
        </semantics>
      </math>
    </office:math>
  </office:body>
</office:document>
XML;
        $mathObjectTwo = <<<'XML'
<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">
  <mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow>
</math>
XML;
        $manifestWithMathObjects = str_replace(
            '</manifest:manifest>',
            '<manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>'
            . '<manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Object 2/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>'
            . '<manifest:file-entry manifest:full-path="Object 2/content.xml" manifest:media-type="text/xml"/>'
            . '</manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithMathObjects,
            $manifestWithMathObjects,
            null,
            null,
            [
                ['name' => 'Object 1/content.xml', 'data' => $mathObjectOne],
                ['name' => 'Object 2/content.xml', 'data' => $mathObjectTwo],
            ]
        ));

        $blocks = $result['document']->children;
        $t->same(2, count($blocks));
        if (count($blocks) !== 2) {
            return;
        }
        $paragraph = $blocks[0];
        $inlineMath = $paragraph->children[1];
        $displayParagraph = $blocks[1];
        $displayMath = $displayParagraph->children[0];

        $t->same('Inline formula x=1 preserved.', $paragraph->attr('text'));
        $t->same('math', $inlineMath->type);
        $t->same(true, $inlineMath->attr('display'));
        $t->same('odt-mathml', $inlineMath->attr('sourceFormat'));
        $t->same('Object 1', $inlineMath->attr('objectPath'));
        $t->same('Object 1/content.xml', $inlineMath->attr('sourcePart'));
        $t->same('x=1', $inlineMath->attr('text'));
        $t->contains('<annotation encoding="application/x-tex">x=1</annotation>', $inlineMath->attr('mathml'));
        $t->same('paragraph', $displayParagraph->type);
        $t->same('a+b', $displayParagraph->attr('text'));
        $t->same('math', $displayMath->type);
        $t->same('Object 2/content.xml', $displayMath->attr('sourcePart'));
        $t->same('a+b', $displayMath->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $displayMath->attr('mathml'));
        $t->same(2, $result['importReport']['content']['mathCount']);
        $t->same(1, count($result['media']));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Inline formula $$x=1$$ preserved.', $markdown);
        $t->contains('$$a+b$$', $markdown);
        $t->contains('<span class="math display"><math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">', $blocksHtml);
        $t->contains('<annotation encoding="application/x-tex">x=1</annotation>', $blocksHtml);
        $t->contains('<span class="math display"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $blocksHtml);
    },
    'maps ODT chart draw objects into embedded object review placeholders' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithChartObjects = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Missing/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
</manifest:manifest>
XML;
        $contentWithChartObjects = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Inline chart <draw:frame draw:name="Inline chart"><svg:desc>Revenue chart placeholder</svg:desc><draw:object xlink:href="./Object%20Chart"/></draw:frame> queued for review.</text:p>
      <draw:frame draw:name="Missing chart">
        <svg:title>Missing chart placeholder</svg:title>
        <draw:object xlink:href="Object%20Missing"/>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $chartObjectXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar">
        <chart:plot-area table:cell-range-address="Sheet1.A1:Sheet1.B4" chart:data-source-has-labels="both">
          <chart:categories table:cell-range-address="Sheet1.A2:Sheet1.A4"/>
          <chart:series chart:values-cell-range-address="Sheet1.B2:Sheet1.B4" chart:label-cell-address="Sheet1.B1" chart:attached-axis="primary-y"/>
        </chart:plot-area>
      </chart:chart>
    </office:chart>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithChartObjects,
            $manifestWithChartObjects,
            null,
            null,
            [
                ['name' => 'Object Chart/content.xml', 'data' => $chartObjectXml],
            ]
        ));

        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $inlineChart = $paragraph->children[1];
        $missingChart = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('Inline chart Revenue chart placeholder queued for review.', $paragraph->attr('text'));
        $t->same('span', $inlineChart->type);
        $t->same(['odf-embedded-object', 'odf-object-chart'], $inlineChart->attr('classes'));
        $t->same('chart', $inlineChart->attr('objectType'));
        $t->same('./Object%20Chart', $inlineChart->attr('href'));
        $t->same('Object Chart', $inlineChart->attr('objectPath'));
        $t->same('Object Chart/', $inlineChart->attr('sourcePart'));
        $t->same('application/vnd.oasis.opendocument.chart', $inlineChart->attr('mediaType'));
        $t->same(true, $inlineChart->attr('exists'));
        $t->same(false, $inlineChart->attr('canExposeBytes'));
        $t->same(['Object Chart/content.xml'], $inlineChart->attr('containedParts'));
        $t->same(1, $inlineChart->attr('containedPartCount'));
        $t->same(strlen($chartObjectXml), $inlineChart->attr('containedByteLength'));
        $t->same('Object Chart/content.xml', $inlineChart->attr('chartMetadata')['sourcePart']);
        $t->same('chart:bar', $inlineChart->attr('chartMetadata')['chartClass']);
        $t->same('bar', $inlineChart->attr('chartMetadata')['chartClassName']);
        $t->same('Sheet1.A1:Sheet1.B4', $inlineChart->attr('chartMetadata')['cellRangeAddress']);
        $t->same('both', $inlineChart->attr('chartMetadata')['dataSourceHasLabels']);
        $t->same('Sheet1.A2:Sheet1.A4', $inlineChart->attr('chartMetadata')['categories'][0]['cellRangeAddress']);
        $t->same(1, $inlineChart->attr('chartMetadata')['seriesCount']);
        $t->same('Sheet1.B2:Sheet1.B4', $inlineChart->attr('chartMetadata')['series'][0]['valuesCellRangeAddress']);
        $t->same('Sheet1.B1', $inlineChart->attr('chartMetadata')['series'][0]['labelCellAddress']);
        $t->same('bar', $inlineChart->attr('attributes')['data-odf-chart-class']);
        $t->same('Sheet1.A1:Sheet1.B4', $inlineChart->attr('attributes')['data-odf-chart-cell-range']);
        $t->same('1', $inlineChart->attr('attributes')['data-odf-chart-series-count']);
        $t->same('Revenue chart placeholder', $inlineChart->children[0]->attr('text'));

        $t->same('div', $missingChart->type);
        $t->same(['odf-embedded-object', 'odf-object-chart'], $missingChart->attr('classes'));
        $t->same('chart', $missingChart->attr('objectType'));
        $t->same('Object Missing', $missingChart->attr('objectPath'));
        $t->same('Object Missing/', $missingChart->attr('sourcePart'));
        $t->same(false, $missingChart->attr('exists'));
        $t->same(0, $missingChart->attr('containedPartCount'));
        $t->same('Missing chart placeholder', $missingChart->children[0]->children[0]->attr('text'));

        $t->same(2, $result['importReport']['content']['embeddedObjectCount']);
        $t->same(2, $result['importReport']['content']['chartObjectCount']);
        $t->same(1, $result['importReport']['content']['chartMetadataCount']);
        $t->same(1, $result['importReport']['content']['missingEmbeddedObjectCount']);
        $t->same(0, count($result['media']));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Revenue chart placeholder]{.odf-embedded-object .odf-object-chart data-odf-object-type="chart" data-odf-object-href="./Object%20Chart" data-odf-object-path="Object Chart" data-odf-object-source-part="Object Chart/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="' . strlen($chartObjectXml) . '" data-odf-object-can-expose-bytes="false" data-odf-chart-source-part="Object Chart/content.xml" data-odf-chart-class="bar" data-odf-chart-cell-range="Sheet1.A1:Sheet1.B4" data-odf-chart-data-source-has-labels="both" data-odf-chart-series-count="1" data-odf-chart-categories-range="Sheet1.A2:Sheet1.A4"}', $markdown);
        $t->contains('::: {.odf-embedded-object .odf-object-chart data-odf-object-type="chart" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('<span class="odf-embedded-object odf-object-chart" data-odf-object-type="chart" data-odf-object-href="./Object%20Chart" data-odf-object-path="Object Chart" data-odf-object-source-part="Object Chart/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="' . strlen($chartObjectXml) . '" data-odf-object-can-expose-bytes="false" data-odf-chart-source-part="Object Chart/content.xml" data-odf-chart-class="bar" data-odf-chart-cell-range="Sheet1.A1:Sheet1.B4" data-odf-chart-data-source-has-labels="both" data-odf-chart-series-count="1" data-odf-chart-categories-range="Sheet1.A2:Sheet1.A4">Revenue chart placeholder</span>', $blocksHtml);
        $t->contains('<div class="odf-embedded-object odf-object-chart" data-odf-object-type="chart" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"><p>Missing chart placeholder</p></div>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'chart:bar'), 'Opaque chart object XML must not render in WordPress output');
    },
    'maps ODT chart content XML into sanitized review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithChartObject = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Line/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object%20Line/content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $contentWithChartObject = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <draw:frame draw:name="Traffic chart"><svg:title>Traffic chart placeholder</svg:title><draw:object xlink:href="./Object%20Line"/></draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $chartObjectXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:line">
        <chart:plot-area table:cell-range-address="Visits.A1:Visits.C5" chart:data-source-has-labels="row">
          <chart:categories table:cell-range-address="Visits.A2:Visits.A5"/>
          <chart:series chart:values-cell-range-address="Visits.B2:Visits.B5" chart:label-cell-address="Visits.B1"/>
          <chart:series chart:values-cell-range-address="Visits.C2:Visits.C5" chart:label-cell-address="Visits.C1"/>
        </chart:plot-area>
      </chart:chart>
    </office:chart>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithChartObject,
            $manifestWithChartObject,
            null,
            null,
            [
                ['name' => 'Object Line/content.xml', 'data' => $chartObjectXml],
            ]
        ));

        $chart = $result['document']->children[0];
        $metadata = $chart->attr('chartMetadata');
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same('div', $chart->type);
        $t->same('chart', $chart->attr('objectType'));
        $t->same('Object Line/content.xml', $metadata['sourcePart']);
        $t->same('chart:line', $metadata['chartClass']);
        $t->same('line', $metadata['chartClassName']);
        $t->same('Visits.A1:Visits.C5', $metadata['cellRangeAddress']);
        $t->same('row', $metadata['dataSourceHasLabels']);
        $t->same('Visits.A2:Visits.A5', $metadata['categories'][0]['cellRangeAddress']);
        $t->same(2, $metadata['seriesCount']);
        $t->same('Visits.C2:Visits.C5', $metadata['series'][1]['valuesCellRangeAddress']);
        $t->same('Visits.C1', $metadata['series'][1]['labelCellAddress']);
        $t->same(1, $result['importReport']['content']['chartObjectCount']);
        $t->same(1, $result['importReport']['content']['chartMetadataCount']);
        $t->contains('data-odf-chart-class="line"', $blocksHtml);
        $t->contains('data-odf-chart-cell-range="Visits.A1:Visits.C5"', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'chart:line'), 'ODT chart class prefix should stay metadata-only, not rendered as raw chart XML');
    },
    'maps ODT chart title axes and legend into sanitized review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithChartObject = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Axes/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object%20Axes/content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $contentWithChartObject = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <draw:frame draw:name="Quarterly chart"><svg:title>Quarterly revenue chart</svg:title><draw:object xlink:href="./Object%20Axes"/></draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $chartObjectXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar" chart:style-name="chQuarterly">
        <chart:title chart:style-name="chart-title" svg:x="1cm" svg:y="0.5cm"><text:p>Quarterly revenue</text:p></chart:title>
        <chart:legend chart:style-name="chart-legend" chart:legend-position="end" chart:legend-align="center"/>
        <chart:plot-area table:cell-range-address="Revenue.A1:Revenue.C5" chart:data-source-has-labels="both">
          <chart:axis chart:dimension="x" chart:name="primary-x" chart:style-name="axis-x">
            <chart:title><text:p>Quarter</text:p></chart:title>
          </chart:axis>
          <chart:axis chart:dimension="y" chart:name="primary-y" chart:style-name="axis-y">
            <chart:title><text:p>Revenue</text:p></chart:title>
          </chart:axis>
          <chart:categories table:cell-range-address="Revenue.A2:Revenue.A5"/>
          <chart:series chart:values-cell-range-address="Revenue.B2:Revenue.B5" chart:label-cell-address="Revenue.B1" chart:attached-axis="primary-y"/>
          <chart:series chart:values-cell-range-address="Revenue.C2:Revenue.C5" chart:label-cell-address="Revenue.C1" chart:attached-axis="primary-y"/>
        </chart:plot-area>
      </chart:chart>
    </office:chart>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithChartObject,
            $manifestWithChartObject,
            null,
            null,
            [
                ['name' => 'Object Axes/content.xml', 'data' => $chartObjectXml],
            ]
        ));

        $chart = $result['document']->children[0];
        $metadata = $chart->attr('chartMetadata');
        $attributes = $chart->attr('attributes');
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same('div', $chart->type);
        $t->same('chart', $chart->attr('objectType'));
        $t->same('Quarterly revenue', $metadata['title']['text']);
        $t->same('chart-title', $metadata['title']['styleName']);
        $t->same('1cm', $metadata['title']['x']);
        $t->same('0.5cm', $metadata['title']['y']);
        $t->same(2, $metadata['axisCount']);
        $t->same('x', $metadata['axes'][0]['dimension']);
        $t->same('primary-x', $metadata['axes'][0]['name']);
        $t->same('Quarter', $metadata['axes'][0]['title']['text']);
        $t->same('y', $metadata['axes'][1]['dimension']);
        $t->same('Revenue', $metadata['axes'][1]['title']['text']);
        $t->same('end', $metadata['legend']['position']);
        $t->same('center', $metadata['legend']['align']);
        $t->same('chart-legend', $metadata['legend']['styleName']);
        $t->same(2, $metadata['seriesCount']);
        $t->same(1, $result['importReport']['content']['chartTitleCount']);
        $t->same(2, $result['importReport']['content']['chartAxisCount']);
        $t->same(1, $result['importReport']['content']['chartLegendCount']);
        $t->same('Quarterly revenue', $attributes['data-odf-chart-title']);
        $t->same('2', $attributes['data-odf-chart-axis-count']);
        $t->same('end', $attributes['data-odf-chart-legend-position']);
        $t->contains('data-odf-chart-title="Quarterly revenue"', $blocksHtml);
        $t->contains('data-odf-chart-axis-count="2"', $blocksHtml);
        $t->contains('data-odf-chart-legend-position="end"', $blocksHtml);
        $t->true(!str_contains($blocksHtml, '<chart:title'), 'ODT chart title XML must stay metadata-only, not render as raw chart XML');
    },
    'maps ODT object-ole frames into embedded object review placeholders' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithOleObjects = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20OLE/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>
  <manifest:file-entry manifest:full-path="Object%20OLE/oleObject.bin" manifest:media-type="application/vnd.openxmlformats-officedocument.oleObject" manifest:size="9"/>
  <manifest:file-entry manifest:full-path="Object%20Missing/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
</manifest:manifest>
XML;
        $contentWithOleObjects = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Inline object <draw:frame draw:name="Inline spreadsheet"><draw:object-ole xlink:href="./Object%20OLE"/></draw:frame> queued.</text:p>
      <draw:frame draw:name="Missing object">
        <svg:title>Linked chart</svg:title>
        <draw:object-ole xlink:href="Object%20Missing"/>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithOleObjects,
            $manifestWithOleObjects,
            null,
            null,
            [
                ['name' => 'Object OLE/oleObject.bin', 'data' => 'OLEBYTES!'],
            ]
        ));

        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $inlineObject = $paragraph->children[1];
        $blockObject = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('Inline object Inline spreadsheet queued.', $paragraph->attr('text'));
        $t->same('span', $inlineObject->type);
        $t->same(['odf-embedded-object', 'odf-object-ole'], $inlineObject->attr('classes'));
        $t->same('ole', $inlineObject->attr('objectType'));
        $t->same('./Object%20OLE', $inlineObject->attr('href'));
        $t->same('Object OLE', $inlineObject->attr('objectPath'));
        $t->same('Object OLE/', $inlineObject->attr('sourcePart'));
        $t->same('application/vnd.oasis.opendocument.spreadsheet', $inlineObject->attr('mediaType'));
        $t->same(true, $inlineObject->attr('exists'));
        $t->same(false, $inlineObject->attr('canExposeBytes'));
        $t->same(['Object OLE/oleObject.bin'], $inlineObject->attr('containedParts'));
        $t->same(1, $inlineObject->attr('containedPartCount'));
        $t->same(9, $inlineObject->attr('containedByteLength'));
        $t->same('Inline spreadsheet', $inlineObject->children[0]->attr('text'));

        $t->same('div', $blockObject->type);
        $t->same(['odf-embedded-object', 'odf-object-ole'], $blockObject->attr('classes'));
        $t->same('Object Missing', $blockObject->attr('objectPath'));
        $t->same('Object Missing/', $blockObject->attr('sourcePart'));
        $t->same(false, $blockObject->attr('exists'));
        $t->same('Linked chart', $blockObject->children[0]->children[0]->attr('text'));

        $mediaByPart = [];
        foreach ($result['media'] as $media) {
            $mediaByPart[$media['part']] = $media;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPart[$item['part']] = $item;
        }
        $provenanceParts = $result['importReport']['manifest']['packageProvenance']['parts'];
        $olePayloadManifest = $manifestByPart['Object OLE/oleObject.bin'];
        $olePayloadInventory = $provenanceParts['Object OLE/oleObject.bin'];

        $t->same(2, $result['importReport']['content']['embeddedObjectCount']);
        $t->same(1, $result['importReport']['content']['missingEmbeddedObjectCount']);
        $t->same(false, isset($mediaByPart['Object OLE/oleObject.bin']));
        $t->same('application/vnd.openxmlformats-officedocument.oleObject', $olePayloadManifest['mediaType']);
        $t->same(true, $olePayloadManifest['embeddedObjectPackagePart']);
        $t->same(true, $olePayloadManifest['embeddedObjectContainedPart']);
        $t->same(false, $olePayloadManifest['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $olePayloadManifest['byteExposurePolicy']);
        $t->same(9, $olePayloadManifest['storedByteLength']);
        $t->same(false, $olePayloadInventory['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $olePayloadInventory['byteExposurePolicy']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Inline spreadsheet]{.odf-embedded-object .odf-object-ole data-odf-object-type="ole" data-odf-object-href="./Object%20OLE" data-odf-object-path="Object OLE" data-odf-object-source-part="Object OLE/" data-odf-object-media-type="application/vnd.oasis.opendocument.spreadsheet" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="9" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('::: {.odf-embedded-object .odf-object-ole data-odf-object-type="ole" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('<span class="odf-embedded-object odf-object-ole" data-odf-object-type="ole" data-odf-object-href="./Object%20OLE" data-odf-object-path="Object OLE" data-odf-object-source-part="Object OLE/" data-odf-object-media-type="application/vnd.oasis.opendocument.spreadsheet" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="9" data-odf-object-can-expose-bytes="false">Inline spreadsheet</span>', $blocksHtml);
        $t->contains('<div class="odf-embedded-object odf-object-ole" data-odf-object-type="ole" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"><p>Linked chart</p></div>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'OLEBYTES!'), 'Opaque OLE bytes must not render in WordPress output');
    },
    'normalizes ODT URI encoded package part references for media and objects' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithEncodedParts = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source%20hero.png" manifest:media-type="image/png" manifest:size="8"/>
  <manifest:file-entry manifest:full-path="Object%201/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Object%201/content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $contentWithEncodedReferences = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Encoded package image <draw:frame draw:name="Encoded hero"><draw:image xlink:href="./Pictures/source%20hero.png"><svg:title>Encoded hero</svg:title><svg:desc>Decoded source hero</svg:desc></draw:image></draw:frame> and formula <draw:frame draw:name="Encoded formula"><draw:object xlink:href="./Object%201"/></draw:frame>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $mathObject = <<<'XML'
<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">
  <mrow><mi>y</mi><mo>=</mo><mn>2</mn></mrow>
</math>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithEncodedReferences,
            $manifestWithEncodedParts,
            null,
            null,
            [
                ['name' => 'Pictures/source hero.png', 'data' => 'PNGDATA!'],
                ['name' => 'Object 1/content.xml', 'data' => $mathObject],
            ]
        ));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $paragraph = $result['document']->children[0];
        $image = $paragraph->children[1];
        $math = $paragraph->children[3];

        $t->same('Pictures/source hero.png', $manifestByPath['Pictures/source%20hero.png']['part']);
        $t->same(true, $manifestByPath['Pictures/source%20hero.png']['exists']);
        $t->same(8, $manifestByPath['Pictures/source%20hero.png']['byteLength']);
        $t->same('Object 1/content.xml', $manifestByPath['Object%201/content.xml']['part']);
        $t->same(true, $manifestByPath['Object%201/content.xml']['exists']);
        $t->same(1, count($result['media']));
        $t->same('Pictures/source hero.png', $result['media'][0]['part']);
        $t->same(8, $result['media'][0]['byteLength']);

        $t->same('Encoded package image Decoded source hero and formula y=2.', $paragraph->attr('text'));
        $t->same('image', $image->type);
        $t->same('./Pictures/source%20hero.png', $image->attr('url'));
        $t->same('Pictures/source hero.png', $image->attr('sourcePart'));
        $t->same(8, $image->attr('bytes'));
        $t->same('math', $math->type);
        $t->same('Object 1', $math->attr('objectPath'));
        $t->same('Object 1/content.xml', $math->attr('sourcePart'));
        $t->same('y=2', $math->attr('text'));
        $t->same(1, $result['importReport']['content']['mathCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<img src="./Pictures/source%20hero.png" alt="Decoded source hero" title="Encoded hero"/>', $blocksHtml);
        $t->contains('<span class="math display"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $blocksHtml);
    },
    'maps ODT frame text-box image captions into figure image handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTextBoxCaption = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Before <draw:frame draw:name="Captioned hero"><draw:text-box><text:p>skip label <draw:frame draw:name="Nested hero"><draw:image xlink:href="Pictures/hero.png"><svg:title>Original hero title</svg:title><svg:desc>Original hero alt</svg:desc></draw:image></draw:frame>Recovered hero caption.</text:p></draw:text-box></draw:frame> after.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTextBoxCaption));
        $paragraph = $result['document']->children[0];
        $image = $paragraph->children[1];
        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same('paragraph', $paragraph->type);
        $t->same('Before Recovered hero caption. after.', $paragraph->attr('text'));
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png', $image->attr('url'));
        $t->same('Pictures/hero.png', $image->attr('sourcePart'));
        $t->same(7, $image->attr('bytes'));
        $t->same('Recovered hero caption.', $image->attr('alt'));
        $t->same('fig:Original hero title', $image->attr('title'));
        $t->same(['odf-text-box-image-caption'], $image->attr('classes'));
        $t->same('true', $image->attr('attributes')['data-odf-text-box-caption']);
        $t->same('Captioned hero', $image->attr('attributes')['data-odf-text-box-frame-name']);
        $t->same('Recovered hero caption.', $image->children[0]->attr('text'));

        $t->contains('![Recovered hero caption.](Pictures/hero.png "fig:Original hero title"){.odf-text-box-image-caption data-odf-text-box-caption="true" data-odf-text-box-frame-name="Captioned hero"}', $markdown);
        $t->contains('<img src="Pictures/hero.png" alt="Recovered hero caption." title="fig:Original hero title" class="odf-text-box-image-caption" data-odf-text-box-caption="true" data-odf-text-box-frame-name="Captioned hero"/>', $blocksHtml);
    },
    'maps block-level ODT frame text-box image captions into figure handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithBlockTextBoxCaption = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <draw:frame draw:name="Block captioned hero">
        <draw:text-box>
          <text:p><draw:frame draw:name="Nested block hero"><draw:image xlink:href="Pictures/hero.png"><svg:title>Block hero title</svg:title><svg:desc>Block hero fallback alt</svg:desc></draw:image></draw:frame>Block-level recovered caption.</text:p>
        </draw:text-box>
      </draw:frame>
      <text:p>Following paragraph remains separate.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithBlockTextBoxCaption));
        $blocks = $result['document']->children;
        $figure = $blocks[0];
        $image = $figure->children[0];
        $paragraph = $blocks[1];
        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same(2, count($blocks));
        $t->same('figure', $figure->type);
        $t->same('Block-level recovered caption.', $figure->attr('caption'));
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png', $image->attr('url'));
        $t->same('Pictures/hero.png', $image->attr('sourcePart'));
        $t->same(7, $image->attr('bytes'));
        $t->same('Block-level recovered caption.', $image->attr('alt'));
        $t->same('fig:Block hero title', $image->attr('title'));
        $t->same(['odf-text-box-image-caption'], $image->attr('classes'));
        $t->same('true', $image->attr('attributes')['data-odf-text-box-caption']);
        $t->same('Block captioned hero', $image->attr('attributes')['data-odf-text-box-frame-name']);
        $t->same('Block-level recovered caption.', $image->children[0]->attr('text'));
        $t->same('paragraph', $paragraph->type);
        $t->same('Following paragraph remains separate.', $paragraph->attr('text'));
        $t->contains('![Block-level recovered caption.](Pictures/hero.png "Block hero title"){.odf-text-box-image-caption data-odf-text-box-caption="true" data-odf-text-box-frame-name="Block captioned hero"}', $markdown);
        $t->contains('<figure class="wp-block-image"><img src="Pictures/hero.png" alt="Block-level recovered caption." title="fig:Block hero title" class="odf-text-box-image-caption" data-odf-text-box-caption="true" data-odf-text-box-frame-name="Block captioned hero"/><figcaption>Block-level recovered caption.</figcaption></figure>', $blocksHtml);
    },
    'maps ODT draw frame captions into figure caption metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDrawCaption = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <draw:frame draw:name="Captioned draw frame">
        <draw:image xlink:href="Pictures/hero.png">
          <svg:title>Hero source title</svg:title>
          <svg:desc>Hero fallback alt</svg:desc>
        </draw:image>
        <draw:caption>
          <text:p>Figure <text:span text:style-name="CaptionStrong">2</text:span>: Source hero caption.</text:p>
        </draw:caption>
      </draw:frame>
      <text:p>Following content remains separate.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDrawCaption));
        $blocks = $result['document']->children;
        $figure = $blocks[0];
        $image = $figure->children[0];
        $captionMetadata = $figure->attr('odfFrameCaption');
        $attributes = $figure->attr('attributes');
        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same(2, count($blocks));
        $t->same('figure', $figure->type);
        $t->same('Figure 2: Source hero caption.', $figure->attr('caption'));
        $t->same(['odf-frame-caption'], $figure->attr('classes'));
        $t->same('draw:caption', $captionMetadata['sourceElement'] ?? null);
        $t->same('Figure 2: Source hero caption.', $captionMetadata['text'] ?? null);
        $t->same('Captioned draw frame', $captionMetadata['frameName'] ?? null);
        $t->same(1, $captionMetadata['paragraphCount'] ?? null);
        $t->same('draw:caption', $attributes['data-odf-frame-caption-source'] ?? null);
        $t->same('Captioned draw frame', $attributes['data-odf-frame-caption-frame-name'] ?? null);
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png', $image->attr('url'));
        $t->same('Hero fallback alt', $image->attr('alt'));
        $t->same('Hero source title', $image->attr('title'));
        $t->same(1, $result['importReport']['content']['frameCaptionCount'] ?? 0);
        $t->same('paragraph', $blocks[1]->type);
        $t->same('Following content remains separate.', $blocks[1]->attr('text'));
        $t->contains('<figure class="odf-frame-caption" data-odf-frame-caption-source="draw:caption" data-odf-frame-caption-frame-name="Captioned draw frame">', $markdown);
        $t->contains('<figcaption>Figure 2: Source hero caption.</figcaption>', $markdown);
        $t->contains('<figure class="wp-block-image odf-frame-caption" data-odf-frame-caption-source="draw:caption" data-odf-frame-caption-frame-name="Captioned draw frame"><img src="Pictures/hero.png" alt="Hero fallback alt" title="Hero source title"/><figcaption>Figure 2: Source hero caption.</figcaption></figure>', $blocksHtml);
    },
    'preserves ODT frame image dimensions for Markdown and WordPress handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSizedImages = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Inline <draw:frame draw:name="Inline proof" svg:width="2.5cm" svg:height="1.25cm"><draw:image xlink:href="Pictures/hero.png"><svg:title>Inline proof title</svg:title><svg:desc>Inline proof alt</svg:desc></draw:image></draw:frame> image.</text:p>
      <draw:frame draw:name="Block proof" svg:width="5cm" svg:height="3cm">
        <draw:image xlink:href="Pictures/hero.png">
          <svg:title>Block proof title</svg:title>
          <svg:desc>Block proof alt</svg:desc>
        </draw:image>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSizedImages));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $paragraphImage = $blocks[0]->children[1];
        $figure = $blocks[1];
        $figureImage = $figure->children[0];

        $t->same('image', $paragraphImage->type);
        $t->same('2.5cm', $paragraphImage->attr('width'));
        $t->same('1.25cm', $paragraphImage->attr('height'));
        $t->same('2.5cm', $paragraphImage->attr('attributes')['width']);
        $t->same('1.25cm', $paragraphImage->attr('attributes')['height']);
        $t->same('Inline proof alt', $paragraphImage->attr('alt'));
        $t->same('Inline proof title', $paragraphImage->attr('title'));
        $t->same('Inline Inline proof alt image.', $blocks[0]->attr('text'));

        $t->same('figure', $figure->type);
        $t->same('Block proof alt', $figure->attr('caption'));
        $t->same('image', $figureImage->type);
        $t->same('5cm', $figureImage->attr('width'));
        $t->same('3cm', $figureImage->attr('height'));
        $t->same('5cm', $figureImage->attr('attributes')['width']);
        $t->same('3cm', $figureImage->attr('attributes')['height']);
        $t->same('Block proof title', $figureImage->attr('title'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('![Inline proof alt](Pictures/hero.png "Inline proof title"){width="2.5cm" height="1.25cm"}', $markdown);
        $t->contains('![Block proof alt](Pictures/hero.png "Block proof title"){width="5cm" height="3cm"}', $markdown);
        $t->contains('<img src="Pictures/hero.png" alt="Inline proof alt" title="Inline proof title" data-pandoc-width="2.5cm" data-pandoc-height="1.25cm" style="width:2.5cm; height:1.25cm"/>', $blocksHtml);
        $t->contains('<img src="Pictures/hero.png" alt="Block proof alt" title="Block proof title" data-pandoc-width="5cm" data-pandoc-height="3cm" style="width:5cm; height:3cm"/>', $blocksHtml);
    },
    'preserves ODT frame image xlink metadata for review handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLinkedImage = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Linked <draw:frame draw:name="Linked hero" svg:width="4cm"><draw:image xlink:href="Pictures/hero.png" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"><svg:title>Linked hero title</svg:title><svg:desc>Linked hero alt</svg:desc></draw:image></draw:frame> image.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLinkedImage));
        $paragraph = $result['document']->children[0];
        $image = $paragraph->children[1];
        $metadata = $image->attr('odfImageMetadata');
        $attributes = $image->attr('attributes');

        $t->same('paragraph', $paragraph->type);
        $t->same('Linked Linked hero alt image.', $paragraph->attr('text'));
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png', $image->attr('url'));
        $t->same('Linked hero alt', $image->attr('alt'));
        $t->same('Linked hero title', $image->attr('title'));
        $t->same('4cm', $image->attr('width'));
        $t->same([
            'xlinkType' => 'simple',
            'xlinkShow' => 'embed',
            'xlinkActuate' => 'onLoad',
        ], $metadata);
        $t->same('4cm', $attributes['width']);
        $t->same('simple', $attributes['data-odf-image-xlink-type']);
        $t->same('embed', $attributes['data-odf-image-xlink-show']);
        $t->same('onLoad', $attributes['data-odf-image-xlink-actuate']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('![Linked hero alt](Pictures/hero.png "Linked hero title"){width="4cm" data-odf-image-xlink-type="simple" data-odf-image-xlink-show="embed" data-odf-image-xlink-actuate="onLoad"}', $markdown);
        $t->contains('<img src="Pictures/hero.png" alt="Linked hero alt" title="Linked hero title" data-pandoc-width="4cm" style="width:4cm" data-odf-image-xlink-type="simple" data-odf-image-xlink-show="embed" data-odf-image-xlink-actuate="onLoad"/>', $blocksHtml);
    },
    'preserves ODT image frame anchor metadata for review handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithAnchoredImage = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Anchored <draw:frame draw:name="Review image frame" draw:style-name="FrameStyle" text:anchor-type="paragraph" text:anchor-page-number="4" svg:x="1.25cm" svg:y="2cm" svg:width="4cm" draw:z-index="7"><draw:image xlink:href="Pictures/hero.png"><svg:title>Frame metadata title</svg:title><svg:desc>Frame metadata alt</svg:desc></draw:image></draw:frame> image.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithAnchoredImage));
        $paragraph = $result['document']->children[0];
        $image = $paragraph->children[1];
        $metadata = $image->attr('odfFrameMetadata');
        $attributes = $image->attr('attributes');

        $t->same('paragraph', $paragraph->type);
        $t->same('Anchored Frame metadata alt image.', $paragraph->attr('text'));
        $t->same('image', $image->type);
        $t->same('Frame metadata alt', $image->attr('alt'));
        $t->same('Frame metadata title', $image->attr('title'));
        $t->same('4cm', $image->attr('width'));
        $t->same([
            'name' => 'Review image frame',
            'styleName' => 'FrameStyle',
            'anchorType' => 'paragraph',
            'anchorPageNumber' => '4',
            'x' => '1.25cm',
            'y' => '2cm',
            'zIndex' => '7',
        ], $metadata);
        $t->same('4cm', $attributes['width']);
        $t->same('Review image frame', $attributes['data-odf-frame-name']);
        $t->same('FrameStyle', $attributes['data-odf-frame-style-name']);
        $t->same('paragraph', $attributes['data-odf-frame-anchor-type']);
        $t->same('4', $attributes['data-odf-frame-anchor-page-number']);
        $t->same('1.25cm', $attributes['data-odf-frame-x']);
        $t->same('2cm', $attributes['data-odf-frame-y']);
        $t->same('7', $attributes['data-odf-frame-z-index']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('![Frame metadata alt](Pictures/hero.png "Frame metadata title"){width="4cm" data-odf-frame-name="Review image frame" data-odf-frame-style-name="FrameStyle" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="4" data-odf-frame-x="1.25cm" data-odf-frame-y="2cm" data-odf-frame-z-index="7"}', $markdown);
        $t->contains('<img src="Pictures/hero.png" alt="Frame metadata alt" title="Frame metadata title" data-pandoc-width="4cm" style="width:4cm" data-odf-frame-name="Review image frame" data-odf-frame-style-name="FrameStyle" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="4" data-odf-frame-x="1.25cm" data-odf-frame-y="2cm" data-odf-frame-z-index="7"/>', $blocksHtml);
    },
    'preserves ODT text box frame anchor metadata for review handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithAnchoredTextBox = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <draw:frame draw:name="Reviewer aside frame" draw:style-name="AsideFrame" text:anchor-type="paragraph" text:anchor-page-number="3" svg:x="2cm" svg:y="4cm" svg:width="6cm" svg:height="2cm" draw:z-index="9">
        <draw:text-box>
          <text:p>Anchored reviewer aside.</text:p>
        </draw:text-box>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithAnchoredTextBox));
        $textBox = $result['document']->children[0];
        $metadata = $textBox->attr('odfFrameMetadata');
        $attributes = $textBox->attr('attributes');

        $t->same('div', $textBox->type);
        $t->same(['odf-text-box'], $textBox->attr('classes'));
        $t->same('Anchored reviewer aside.', $textBox->children[0]->attr('text'));
        $t->same([
            'name' => 'Reviewer aside frame',
            'styleName' => 'AsideFrame',
            'anchorType' => 'paragraph',
            'anchorPageNumber' => '3',
            'x' => '2cm',
            'y' => '4cm',
            'width' => '6cm',
            'height' => '2cm',
            'zIndex' => '9',
        ], $metadata);
        $t->same('Reviewer aside frame', $attributes['data-odf-frame-name']);
        $t->same('AsideFrame', $attributes['data-odf-frame-style-name']);
        $t->same('paragraph', $attributes['data-odf-frame-anchor-type']);
        $t->same('3', $attributes['data-odf-frame-anchor-page-number']);
        $t->same('2cm', $attributes['data-odf-frame-x']);
        $t->same('4cm', $attributes['data-odf-frame-y']);
        $t->same('6cm', $attributes['data-odf-frame-width']);
        $t->same('2cm', $attributes['data-odf-frame-height']);
        $t->same('9', $attributes['data-odf-frame-z-index']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {.odf-text-box data-odf-frame-name="Reviewer aside frame" data-odf-frame-style-name="AsideFrame" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="3" data-odf-frame-x="2cm" data-odf-frame-y="4cm" data-odf-frame-width="6cm" data-odf-frame-height="2cm" data-odf-frame-z-index="9"}', $markdown);
        $t->contains('<div class="odf-text-box" data-odf-frame-name="Reviewer aside frame" data-odf-frame-style-name="AsideFrame" data-odf-frame-anchor-type="paragraph" data-odf-frame-anchor-page-number="3" data-odf-frame-x="2cm" data-odf-frame-y="4cm" data-odf-frame-width="6cm" data-odf-frame-height="2cm" data-odf-frame-z-index="9"><p>Anchored reviewer aside.</p></div>', $blocksHtml);
    },
    'maps ODT drawing layers into frame review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithDrawLayers = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:automatic-styles>
    <draw:layer-set>
      <draw:layer draw:name="review-media" draw:display="screen" draw:protected="true"/>
      <draw:layer draw:name="draft-notes" draw:display="none"/>
    </draw:layer-set>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <draw:frame draw:name="Layered hero" draw:layer="review-media" svg:width="4cm">
        <draw:image xlink:href="Pictures/hero.png"><svg:title>Layered hero title</svg:title><svg:desc>Layered hero alt</svg:desc></draw:image>
      </draw:frame>
      <draw:frame draw:name="Layered aside" draw:layer="draft-notes" svg:width="6cm">
        <draw:text-box><text:p>Draft layer note.</text:p></draw:text-box>
      </draw:frame>
      <text:p>Inline <draw:frame draw:name="Unmapped inline layer" draw:layer="missing-layer"><draw:image xlink:href="Pictures/hero.png"><svg:desc>Missing layer alt</svg:desc></draw:image></draw:frame> remains visible.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithDrawLayers));
        $declarations = $result['contentDeclarations'];
        $image = $result['document']->children[0]->children[0];
        $textBox = $result['document']->children[1];
        $inlineImage = $result['document']->children[2]->children[1];

        $t->same(2, $declarations['drawLayerCount']);
        $t->same(1, $declarations['hiddenDrawLayerCount']);
        $t->same(1, $declarations['protectedDrawLayerCount']);
        $t->same('review-media', $declarations['drawLayers'][0]['name']);
        $t->same('screen', $declarations['drawLayersByName']['review-media']['display']);
        $t->same(true, $declarations['drawLayersByName']['review-media']['protected']);
        $t->same(true, $declarations['drawLayersByName']['draft-notes']['hidden']);

        $t->same('image', $image->type);
        $t->same('review-media', $image->attr('odfFrameMetadata')['layer']);
        $t->same('true', $image->attr('odfFrameMetadata')['layerExists']);
        $t->same('screen', $image->attr('odfFrameMetadata')['layerDisplay']);
        $t->same('true', $image->attr('odfFrameMetadata')['layerProtected']);
        $t->same('review-media', $image->attr('attributes')['data-odf-frame-layer']);
        $t->same('true', $image->attr('attributes')['data-odf-frame-layer-exists']);

        $t->same('div', $textBox->type);
        $t->same('draft-notes', $textBox->attr('odfFrameMetadata')['layer']);
        $t->same('none', $textBox->attr('odfFrameMetadata')['layerDisplay']);
        $t->same('true', $textBox->attr('odfFrameMetadata')['layerHidden']);
        $t->same('true', $textBox->attr('attributes')['data-odf-frame-layer-hidden']);

        $t->same('image', $inlineImage->type);
        $t->same('missing-layer', $inlineImage->attr('odfFrameMetadata')['layer']);
        $t->same('false', $inlineImage->attr('odfFrameMetadata')['layerExists']);
        $t->same('missing-layer', $inlineImage->attr('attributes')['data-odf-frame-layer']);
        $t->same('false', $inlineImage->attr('attributes')['data-odf-frame-layer-exists']);

        $t->same(2, $result['importReport']['content']['drawLayerCount']);
        $t->same(1, $result['importReport']['content']['hiddenDrawLayerCount']);
        $t->same(1, $result['importReport']['content']['protectedDrawLayerCount']);
        $t->same(3, $result['importReport']['content']['frameLayerReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('![Layered hero alt](Pictures/hero.png "Layered hero title"){width="4cm" data-odf-frame-name="Layered hero" data-odf-frame-layer="review-media" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="screen" data-odf-frame-layer-protected="true"}', $markdown);
        $t->contains('<img src="Pictures/hero.png" alt="Layered hero alt" title="Layered hero title" data-pandoc-width="4cm" style="width:4cm" data-odf-frame-name="Layered hero" data-odf-frame-layer="review-media" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="screen" data-odf-frame-layer-protected="true"/>', $blocksHtml);
        $t->contains('<div class="odf-text-box" data-odf-frame-name="Layered aside" data-odf-frame-width="6cm" data-odf-frame-layer="draft-notes" data-odf-frame-layer-exists="true" data-odf-frame-layer-display="none" data-odf-frame-layer-hidden="true"><p>Draft layer note.</p></div>', $blocksHtml);
    },
    'renders ODT handoff nodes through Markdown and WordPress writers' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $document = (new OdfReader())->readDocument($buildOdtPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# Imported ODT Packet', $markdown);
        $t->contains('[source link](https://example.test/source.odt)', $markdown);
        $t->contains('[summary]{data-odf-style-name="StrongEmphasis"}', $markdown);
        $t->contains('[^1]', $markdown);
        $t->contains('c.  Legal review', $markdown);
        $t->contains('![Hero alt text](Pictures/hero.png "Hero title")', $markdown);
        $t->contains('| Status                | Owner', $markdown);
        $t->contains(': Audit', $markdown);
        $t->contains('Ready for review', $markdown);

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<span data-odf-style-name="StrongEmphasis">summary</span>', $blocks);
        $t->contains('<a href="https://example.test/source.odt">source link</a>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes">', $blocks);
        $t->contains('<ol start="3" type="a">', $blocks);
        $t->contains('<img src="Pictures/hero.png" alt="Hero alt text" title="Hero title"/>', $blocks);
        $t->contains('<th><p>Status</p></th>', $blocks);
        $t->contains('<td colspan="2"><p>Ready for review</p></td>', $blocks);
    },
    'reports missing ODT manifest media without dropping content blocks' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithMissing = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/><manifest:file-entry manifest:full-path="Pictures/missing.jpg" manifest:media-type="image/jpeg"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithMissing));
        $missing = $result['importReport']['manifest']['missingItems'];
        $mediaByPart = [];
        foreach ($result['media'] as $media) {
            $mediaByPart[$media['part']] = $media;
        }

        $t->same(1, count($missing));
        $t->same('Pictures/missing.jpg', $missing[0]['part']);
        $t->same(false, $mediaByPart['Pictures/missing.jpg']['exists']);
        $t->same(null, $mediaByPart['Pictures/missing.jpg']['byteLength']);
        $t->same(8, count($result['document']->children));
        $t->same('Imported ODT Packet', $result['document']->children[0]->children[0]->attr('text'));
    },
    'reports ODT manifest URI suffix provenance while resolving package parts' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $sourceBytes = 'SOURCEPNG';
        $manifestWithSuffixedPaths = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png?cache=1#review" manifest:media-type="image/png"/>'
                . '<manifest:file-entry manifest:full-path="Pictures/source%20hero.png?download=true#asset" manifest:media-type="image/png" manifest:size="' . strlen($sourceBytes) . '"/>',
            $manifestXml
        );
        $contentWithSuffixedImage = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <draw:frame draw:name="Suffixed hero"><draw:image xlink:href="Pictures/hero.png?cache=1#review"><svg:desc>Suffixed hero alt</svg:desc></draw:image></draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithSuffixedImage,
            $manifestWithSuffixedPaths,
            null,
            null,
            [['name' => 'Pictures/source hero.png', 'data' => $sourceBytes, 'compressionMethod' => 0]]
        ));
        $manifestByFullPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByFullPath[$item['fullPath']] = $item;
        }
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $provenanceParts = $result['importReport']['manifest']['packageProvenance']['parts'];
        $image = $result['document']->children[0]->children[0];

        $hero = $manifestByFullPath['Pictures/hero.png?cache=1#review'];
        $source = $manifestByFullPath['Pictures/source%20hero.png?download=true#asset'];
        $t->same('Pictures/hero.png', $hero['part']);
        $t->same('Pictures/hero.png', $hero['partReference']);
        $t->same('?cache=1#review', $hero['partSuffix']);
        $t->same('cache=1', $hero['partQuery']);
        $t->same('review', $hero['partFragment']);
        $t->same(true, $hero['exists']);
        $t->same(7, $hero['byteLength']);
        $t->same('Pictures/source hero.png', $source['part']);
        $t->same('Pictures/source%20hero.png', $source['partReference']);
        $t->same('?download=true#asset', $source['partSuffix']);
        $t->same('download=true', $source['partQuery']);
        $t->same('asset', $source['partFragment']);
        $t->same(strlen($sourceBytes), $source['byteLength']);

        $t->same('Pictures/hero.png?cache=1#review', $mediaByPart['Pictures/hero.png']['fullPath']);
        $t->same('?cache=1#review', $mediaByPart['Pictures/hero.png']['partSuffix']);
        $t->same('review', $mediaByPart['Pictures/hero.png']['partFragment']);
        $t->same('Pictures/source%20hero.png', $mediaByPart['Pictures/source hero.png']['partReference']);
        $t->same('download=true', $mediaByPart['Pictures/source hero.png']['partQuery']);
        $t->same('Pictures/hero.png?cache=1#review', $provenanceParts['Pictures/hero.png']['manifestFullPath']);
        $t->same('Pictures/hero.png', $provenanceParts['Pictures/hero.png']['manifestPartReference']);
        $t->same('?cache=1#review', $provenanceParts['Pictures/hero.png']['manifestPartSuffix']);
        $t->same('cache=1', $provenanceParts['Pictures/hero.png']['manifestPartQuery']);
        $t->same('review', $provenanceParts['Pictures/hero.png']['manifestPartFragment']);
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png?cache=1#review', $image->attr('url'));
        $t->same('Pictures/hero.png', $image->attr('sourcePart'));
        $t->same(7, $image->attr('bytes'));

        $duplicateResolvedPartManifest = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png?cache=1" manifest:media-type="image/png"/>'
                . '<manifest:file-entry manifest:full-path="Pictures/hero.png#review" manifest:media-type="image/png"/>',
            $manifestXml
        );
        $t->throws(\RuntimeException::class, static fn (): array => (new OdfReader())->readPackage($buildOdtPackage(null, $duplicateResolvedPartManifest)));
    },
    'summarizes ODT manifest URI suffix provenance in package provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml): void {
        $manifestWithSuffixedPaths = str_replace(
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            ],
            [
                '<manifest:file-entry manifest:full-path="content.xml?role=body#content" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="styles.xml#styledefs" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
                    . '<manifest:file-entry manifest:full-path="Pictures/missing.png?missing=true" manifest:media-type="image/png"/>',
            ],
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSuffixedPaths));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $suffixItems = [];
        foreach ($provenance['manifestPartReferenceSuffixItems'] as $item) {
            $suffixItems[$item['fullPath']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(3, $provenance['manifestPartReferenceSuffixCount']);
        $t->same(2, $provenance['manifestPartReferenceQueryCount']);
        $t->same(2, $provenance['manifestPartReferenceFragmentCount']);
        $t->same([
            'content.xml?role=body#content',
            'styles.xml#styledefs',
            'Pictures/missing.png?missing=true',
        ], array_column($provenance['manifestPartReferenceSuffixItems'], 'fullPath'));

        $content = $suffixItems['content.xml?role=body#content'];
        $t->same('content.xml', $content['part']);
        $t->same('content.xml', $content['partReference']);
        $t->same('?role=body#content', $content['partSuffix']);
        $t->same('role=body', $content['partQuery']);
        $t->same('content', $content['partFragment']);
        $t->same(true, $content['exists']);
        $t->same(true, $content['canExposeBytes']);
        $t->same(strlen($contentXml), $provenance['parts']['content.xml']['byteLength']);
        $t->same('?role=body#content', $provenance['parts']['content.xml']['manifestPartSuffix']);

        $styles = $suffixItems['styles.xml#styledefs'];
        $t->same('styles.xml', $styles['part']);
        $t->same('#styledefs', $styles['partSuffix']);
        $t->same(null, $styles['partQuery']);
        $t->same('styledefs', $styles['partFragment']);
        $t->same(true, $styles['exists']);

        $missing = $suffixItems['Pictures/missing.png?missing=true'];
        $t->same('Pictures/missing.png', $missing['part']);
        $t->same('?missing=true', $missing['partSuffix']);
        $t->same('missing=true', $missing['partQuery']);
        $t->same(null, $missing['partFragment']);
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same(1, count($result['importReport']['manifest']['missingItems']));
        $t->same('Pictures/missing.png?missing=true', $result['importReport']['manifest']['missingItems'][0]['fullPath']);
    },
    'reports ODT ZIP entries missing from the manifest for package review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $result = (new OdfReader())->readPackage($buildOdtPackage(null, null, null, null, [
            ['name' => 'Pictures/orphan.png', 'data' => 'ORPHANPNG'],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accelerator/>', 'compressionMethod' => 0],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMBNAIL'],
            ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
        ]));
        $manifestReport = $result['importReport']['manifest'];
        $undeclaredByPart = [];
        foreach ($manifestReport['undeclaredEntries'] as $entry) {
            $undeclaredByPart[$entry['part']] = $entry;
        }

        $t->same(3, $manifestReport['undeclaredEntryCount']);
        $t->same(['Pictures/orphan.png', 'Configurations2/accelerator/current.xml', 'Thumbnails/thumbnail.png'], array_keys($undeclaredByPart));
        $t->same('odf-manifest-undeclared-package-entry', $undeclaredByPart['Pictures/orphan.png']['diagnostic']);
        $t->same(9, $undeclaredByPart['Pictures/orphan.png']['byteLength']);
        $t->same(sprintf('%08x', crc32('ORPHANPNG')), $undeclaredByPart['Pictures/orphan.png']['crc32']);
        $t->same(0, $undeclaredByPart['Configurations2/accelerator/current.xml']['compressionMethod']);
        $t->same(false, isset($undeclaredByPart['Configurations2/']));
        $t->same(1, count($result['media']), 'undeclared ZIP payloads must stay out of declared media handoff');
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $t->same(8, count($result['document']->children));
    },
    'reports ODT package thumbnails as metadata-only previews' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $thumbnailBytes = 'THUMBNAIL';
        $orphanThumbnailBytes = 'ORPHAN-THUMBNAIL';
        $manifestWithThumbnails = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png" manifest:size="' . strlen($thumbnailBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Thumbnails/missing.jpg" manifest:media-type="image/jpeg"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithThumbnails, null, null, [
            ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
            ['name' => 'Thumbnails/orphan.jpg', 'data' => $orphanThumbnailBytes, 'compressionMethod' => 0],
        ]));
        $report = $result['importReport']['packageThumbnails'];
        $metadata = $result['metadata']['odfPackageThumbnails'];
        $documentThumbnails = $result['document']->attr('packageThumbnails');
        $itemsByPart = [];
        foreach ($report['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPart[$item['part'] ?? ''] = $item;
        }
        $provenanceParts = $result['importReport']['manifest']['packageProvenance']['parts'];

        $t->same($report, $metadata);
        $t->same($report, $documentThumbnails);
        $t->same(3, $report['count']);
        $t->same(2, $report['readableCount']);
        $t->same(2, $report['declaredCount']);
        $t->same(1, $report['undeclaredCount']);
        $t->same(1, $report['missingCount']);
        $t->same(0, $report['encryptedCount']);
        $t->same(0, $report['invalidMediaTypeCount']);
        $t->same(2, $report['issueCount']);
        $t->same([
            'odf-thumbnail-missing-package-part',
            'odf-thumbnail-undeclared-package-part',
        ], $report['issueCodes']);

        $t->same('Thumbnails/thumbnail.png', $itemsByPart['Thumbnails/thumbnail.png']['part']);
        $t->same('image/png', $itemsByPart['Thumbnails/thumbnail.png']['mediaType']);
        $t->same(true, $itemsByPart['Thumbnails/thumbnail.png']['declared']);
        $t->same(true, $itemsByPart['Thumbnails/thumbnail.png']['exists']);
        $t->same(true, $itemsByPart['Thumbnails/thumbnail.png']['valid']);
        $t->same(strlen($thumbnailBytes), $itemsByPart['Thumbnails/thumbnail.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $itemsByPart['Thumbnails/thumbnail.png']['crc32']);
        $t->same(strlen($thumbnailBytes), $itemsByPart['Thumbnails/thumbnail.png']['storedByteLength']);
        $t->same(false, $itemsByPart['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same(false, $itemsByPart['Thumbnails/thumbnail.png']['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-bytes-blocked', $itemsByPart['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same('package-thumbnail-metadata-only', $itemsByPart['Thumbnails/thumbnail.png']['reviewPolicy']);
        $t->same([], $itemsByPart['Thumbnails/thumbnail.png']['issues']);

        $manifestThumbnail = $manifestByPart['Thumbnails/thumbnail.png'];
        $t->same(true, $manifestThumbnail['thumbnailPackagePart']);
        $t->same(false, $manifestThumbnail['canExposeBytes']);
        $t->same(null, $manifestThumbnail['byteLength']);
        $t->same(strlen($thumbnailBytes), $manifestThumbnail['storedByteLength']);
        $t->same(null, $manifestThumbnail['crc32']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $manifestThumbnail['storedCrc32']);
        $t->same(null, $manifestThumbnail['byteSha256']);
        $t->same('package-thumbnail-bytes-blocked', $manifestThumbnail['byteExposurePolicy']);
        $t->same(true, $provenanceParts['Thumbnails/thumbnail.png']['thumbnailPackagePart']);
        $t->same(false, $provenanceParts['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same('package-thumbnail-bytes-blocked', $provenanceParts['Thumbnails/thumbnail.png']['byteExposurePolicy']);

        $t->same(false, $itemsByPart['Thumbnails/missing.jpg']['exists']);
        $t->same(['odf-thumbnail-missing-package-part'], $itemsByPart['Thumbnails/missing.jpg']['issues']);
        $t->same(null, $itemsByPart['Thumbnails/missing.jpg']['byteLength']);
        $t->same('image/jpeg', $itemsByPart['Thumbnails/orphan.jpg']['mediaType']);
        $t->same(false, $itemsByPart['Thumbnails/orphan.jpg']['declared']);
        $t->same(true, $itemsByPart['Thumbnails/orphan.jpg']['undeclared']);
        $t->same(true, $itemsByPart['Thumbnails/orphan.jpg']['exists']);
        $t->same(strlen($orphanThumbnailBytes), $itemsByPart['Thumbnails/orphan.jpg']['byteLength']);
        $t->same(['odf-thumbnail-undeclared-package-part'], $itemsByPart['Thumbnails/orphan.jpg']['issues']);

        $mediaParts = array_column($result['media'], 'part');
        $t->same(['Pictures/hero.png'], $mediaParts, 'ODT package thumbnails must not become document media handoff items');
        $t->same(1, $result['importReport']['media']['count']);
        $t->same(1, $result['importReport']['manifest']['undeclaredEntryCount']);
        $t->same('Thumbnails/orphan.jpg', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);
    },
    'reports ODT package fonts as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $reviewSansBytes = 'WOFF2DATA';
        $sourceFontBytes = 'SOURCE-WOFF';
        $invalidBytes = 'NOTFONT';
        $encryptedBytes = 'ENCFONT';
        $orphanBytes = 'ORPHAN-OTF';
        $encryptedEntry = <<<'XML'
  <manifest:file-entry manifest:full-path="Fonts/encrypted.ttf" manifest:media-type="font/ttf" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="font-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="font-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithFonts = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Fonts/ReviewSans.woff2" manifest:media-type="font/woff2" manifest:size="' . strlen($reviewSansBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Fonts/Missing.otf" manifest:media-type="application/vnd.ms-opentype"/>'
            . '<manifest:file-entry manifest:full-path="Fonts/not-font.bin" manifest:media-type="application/octet-stream" manifest:size="' . strlen($invalidBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Assets/source.woff" manifest:media-type="font/woff; technology=&quot;variations&quot;" manifest:size="' . strlen($sourceFontBytes) . '"/>'
            . $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithFonts, null, null, [
            ['name' => 'Fonts/ReviewSans.woff2', 'data' => $reviewSansBytes, 'compressionMethod' => 0],
            ['name' => 'Fonts/not-font.bin', 'data' => $invalidBytes, 'compressionMethod' => 0],
            ['name' => 'Assets/source.woff', 'data' => $sourceFontBytes, 'compressionMethod' => 0],
            ['name' => 'Fonts/encrypted.ttf', 'data' => $encryptedBytes, 'compressionMethod' => 0],
            ['name' => 'Fonts/orphan.otf', 'data' => $orphanBytes, 'compressionMethod' => 0],
        ]));
        $report = $result['importReport']['packageFonts'];
        $metadata = $result['metadata']['odfPackageFonts'];
        $documentFonts = $result['document']->attr('packageFonts');
        $itemsByPart = [];
        foreach ($report['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($report, $metadata);
        $t->same($report, $documentFonts);
        $t->same(6, $report['count']);
        $t->same(4, $report['readableCount']);
        $t->same(5, $report['declaredCount']);
        $t->same(1, $report['undeclaredCount']);
        $t->same(1, $report['missingCount']);
        $t->same(1, $report['encryptedCount']);
        $t->same(1, $report['invalidMediaTypeCount']);
        $t->same(4, $report['issueCount']);
        $t->same([
            'odf-font-encrypted-package-part',
            'odf-font-invalid-media-type',
            'odf-font-missing-package-part',
            'odf-font-undeclared-package-part',
        ], $report['issueCodes']);
        $t->same([
            'opentype' => 2,
            'truetype' => 1,
            'unknown' => 1,
            'woff' => 1,
            'woff2' => 1,
        ], $report['fontFormatCounts']);
        $t->same([
            'media-type' => 4,
            'package-extension' => 1,
            'unknown' => 1,
        ], $report['fontFormatSourceCounts']);
        $t->same([
            'sfnt' => 3,
            'unknown' => 1,
            'webfont' => 2,
        ], $report['fontFormatFamilyCounts']);
        $t->same([
            'bin' => 1,
            'otf' => 2,
            'ttf' => 1,
            'woff' => 1,
            'woff2' => 1,
        ], $report['fontFileExtensionCounts']);
        $t->same(5, $report['recognizedFontFormatCount']);
        $t->same(1, $report['unknownFontFormatCount']);

        $declared = $itemsByPart['Fonts/ReviewSans.woff2'];
        $t->same('font/woff2', $declared['mediaType']);
        $t->same('woff2', $declared['fontFileExtension']);
        $t->same('woff2', $declared['fontFormat']);
        $t->same('media-type', $declared['fontFormatSource']);
        $t->same('webfont', $declared['fontFormatFamily']);
        $t->same(true, $declared['recognizedFontFormat']);
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['exists']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($reviewSansBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewSansBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-font-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);

        $source = $itemsByPart['Assets/source.woff'];
        $t->same('font/woff; technology="variations"', $source['mediaType']);
        $t->same('font/woff', $source['mediaTypeBase']);
        $t->same(['technology' => 'variations'], $source['mediaTypeParameterMap']);
        $t->same('woff', $source['fontFileExtension']);
        $t->same('woff', $source['fontFormat']);
        $t->same('media-type', $source['fontFormatSource']);
        $t->same('webfont', $source['fontFormatFamily']);
        $t->same([], $source['issues']);

        $missing = $itemsByPart['Fonts/Missing.otf'];
        $t->same('opentype', $missing['fontFormat']);
        $t->same('media-type', $missing['fontFormatSource']);
        $t->same('sfnt', $missing['fontFormatFamily']);
        $t->same(false, $missing['exists']);
        $t->same(['odf-font-missing-package-part'], $missing['issues']);
        $t->same(null, $missing['byteLength']);

        $invalid = $itemsByPart['Fonts/not-font.bin'];
        $t->same('bin', $invalid['fontFileExtension']);
        $t->same('unknown', $invalid['fontFormat']);
        $t->same('unknown', $invalid['fontFormatSource']);
        $t->same('unknown', $invalid['fontFormatFamily']);
        $t->same(false, $invalid['recognizedFontFormat']);
        $t->same(false, $invalid['valid']);
        $t->same(['odf-font-invalid-media-type'], $invalid['issues']);

        $encrypted = $itemsByPart['Fonts/encrypted.ttf'];
        $t->same('truetype', $encrypted['fontFormat']);
        $t->same('media-type', $encrypted['fontFormatSource']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(sprintf('%08x', crc32($encryptedBytes)), $encrypted['storedCrc32']);
        $t->same(['odf-font-encrypted-package-part'], $encrypted['issues']);

        $orphan = $itemsByPart['Fonts/orphan.otf'];
        $t->same('opentype', $orphan['fontFormat']);
        $t->same('package-extension', $orphan['fontFormatSource']);
        $t->same('sfnt', $orphan['fontFormatFamily']);
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(['odf-font-undeclared-package-part'], $orphan['issues']);

        $mediaParts = array_column($result['media'], 'part');
        $t->same(['Pictures/hero.png'], $mediaParts, 'ODT package fonts must not become document media handoff items');
        $t->same('font-package-bytes-blocked', $manifestByPart['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same('font-package-bytes-blocked', $manifestByPart['Assets/source.woff']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $manifestByPart['Fonts/encrypted.ttf']['byteExposurePolicy']);
        $t->same(false, $manifestByPart['Fonts/ReviewSans.woff2']['canExposeBytes']);
        $t->same(true, $manifestByPart['Fonts/ReviewSans.woff2']['fontPackagePart']);
        $t->same(true, $manifestByPart['Assets/source.woff']['fontPackagePart']);

        $t->same(5, $provenance['packageFontPartCount']);
        $t->same(5, $provenance['roleCounts']['font-package']);
        $t->same(1, $provenance['undeclaredRoleCounts']['font-package']);
        $t->same(['font-package', 'manifest-declared'], $provenance['parts']['Fonts/ReviewSans.woff2']['roles']);
        $t->same(['font-package', 'manifest-declared'], $provenance['parts']['Assets/source.woff']['roles']);
        $t->same(['font-package', 'undeclared-package-entry'], $provenance['parts']['Fonts/orphan.otf']['roles']);
        $t->same('font-package-bytes-blocked', $provenance['parts']['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same('Fonts/orphan.otf', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);
    },
    'treats ODT manifest directory declarations as logical package entries' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithDirectories = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/" manifest:media-type=""/>'
            . '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>'
            . '<manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Configurations2/" manifest:media-type=""/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithDirectories, null, null, [
            ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object 1/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object 1/content.xml', 'data' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math>'],
            ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
        ]));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $manifestReport = $result['importReport']['manifest'];
        $packageProvenance = $manifestReport['packageProvenance'];

        $t->same(9, $manifestReport['count']);
        $t->same(3, $manifestReport['directoryCount']);
        $t->same(['Pictures/', 'Object 1/', 'Configurations2/'], array_column($manifestReport['directoryItems'], 'part'));
        $t->same([], $manifestReport['missingItems']);
        $t->same(0, $manifestReport['undeclaredEntryCount']);
        $t->same(1, count($result['media']), 'manifest directory entries must stay out of media byte handoff');
        $t->same('Pictures/hero.png', $result['media'][0]['part']);

        $picturesDirectory = $manifestByPath['Pictures/'];
        $objectDirectory = $manifestByPath['Object 1/'];
        $configurationDirectory = $manifestByPath['Configurations2/'];
        $t->same(true, $picturesDirectory['isDirectory']);
        $t->same(true, $picturesDirectory['exists']);
        $t->same(false, $picturesDirectory['canExposeBytes']);
        $t->same(null, $picturesDirectory['byteLength']);
        $t->same(null, $picturesDirectory['crc32']);
        $t->same(true, $objectDirectory['isDirectory']);
        $t->same('application/vnd.oasis.opendocument.formula', $objectDirectory['mediaType']);
        $t->same(true, $objectDirectory['exists']);
        $t->same(false, $objectDirectory['canExposeBytes']);
        $t->same('Object 1/content.xml', $manifestByPath['Object 1/content.xml']['part']);
        $t->same(true, $manifestByPath['Object 1/content.xml']['exists']);
        $t->same(false, $configurationDirectory['canExposeBytes']);
        $t->same(3, $packageProvenance['packageDirectoryCount']);
        $t->same(1, $packageProvenance['mediaResourcePartCount']);
        $t->same(1, $packageProvenance['embeddedObjectPackageCount']);
        $t->same(['zip-directory', 'manifest-declared'], $packageProvenance['parts']['Pictures/']['roles']);
        $t->same(['zip-directory', 'embedded-object-root', 'manifest-declared'], $packageProvenance['parts']['Object 1/']['roles']);
        $t->same(['configuration-package', 'zip-directory', 'manifest-declared'], $packageProvenance['parts']['Configurations2/']['roles']);
        $t->same($manifestReport['directoryItems'], array_values(array_filter(
            $result['document']->attr('manifest')['items'],
            static fn (array $item): bool => ($item['isDirectory'] ?? false) === true
        )));
    },
    'summarizes ODT manifest media-type package buckets for review handoff' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $objectXml = '<math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math>';
        $encryptedHero = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="checksum-base64">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="iv-base64"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithMediaTypes = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/" manifest:media-type=""/>'
            . $encryptedHero
            . '<manifest:file-entry manifest:full-path="Pictures/cover.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/missing.jpg" manifest:media-type="image/jpeg"/>'
            . '<manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>'
            . '<manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Configurations2/" manifest:media-type=""/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithMediaTypes, null, null, [
            ['name' => 'Pictures/cover.png', 'data' => 'COVERPNG', 'compressionMethod' => 0],
            ['name' => 'Object 1/content.xml', 'data' => $objectXml],
        ]));
        $summary = $result['importReport']['manifest']['mediaTypeSummary'];
        $summaryByType = [];
        foreach ($summary['items'] as $item) {
            $summaryByType[$item['mediaType']] = $item;
        }
        $coreTextByteLength = strlen($contentXml) + strlen($stylesXml) + strlen($metaXml);
        $textByteLength = $coreTextByteLength + strlen($objectXml);

        $t->same($summary, $result['document']->attr('manifest')['mediaTypeSummary']);
        $t->same(11, $summary['manifestItemCount']);
        $t->same(9, $summary['typedItemCount']);
        $t->same(5, $summary['mediaTypeCount']);
        $t->same(2, $summary['emptyMediaTypeCount']);
        $t->same(['Pictures/', 'Configurations2/'], $summary['emptyMediaTypeParts']);
        $t->same(3, $summary['directoryCount']);
        $t->same(1, $summary['missingCount']);
        $t->same(1, $summary['encryptedCount']);
        $t->same(0, $summary['declaredSizeMismatchCount']);
        $t->same(2048, $summary['declaredSize']);
        $t->same($textByteLength + 15, $summary['storedByteLength']);
        $t->same($coreTextByteLength + 8, $summary['exposableByteLength']);

        $imagePng = $summaryByType['image/png'];
        $t->same(2, $imagePng['count']);
        $t->same(['Pictures/hero.png', 'Pictures/cover.png'], $imagePng['parts']);
        $t->same(2, $imagePng['existsCount']);
        $t->same(0, $imagePng['missingCount']);
        $t->same(1, $imagePng['encryptedCount']);
        $t->same(0, $imagePng['directoryCount']);
        $t->same(2048, $imagePng['declaredSize']);
        $t->same(15, $imagePng['storedByteLength']);
        $t->same(8, $imagePng['exposableByteLength']);

        $textXml = $summaryByType['text/xml'];
        $t->same(4, $textXml['count']);
        $t->same(['content.xml', 'styles.xml', 'meta.xml', 'Object 1/content.xml'], $textXml['parts']);
        $t->same(0, $textXml['encryptedCount']);
        $t->same(0, $textXml['missingCount']);
        $t->same($textByteLength, $textXml['storedByteLength']);
        $t->same($coreTextByteLength, $textXml['exposableByteLength']);

        $imageJpeg = $summaryByType['image/jpeg'];
        $t->same(1, $imageJpeg['count']);
        $t->same(['Pictures/missing.jpg'], $imageJpeg['parts']);
        $t->same(0, $imageJpeg['existsCount']);
        $t->same(1, $imageJpeg['missingCount']);
        $t->same(0, $imageJpeg['storedByteLength']);

        $formula = $summaryByType['application/vnd.oasis.opendocument.formula'];
        $t->same(1, $formula['count']);
        $t->same(['Object 1/'], $formula['parts']);
        $t->same(1, $formula['directoryCount']);
        $t->same(0, $formula['exposableByteLength']);

        $root = $summaryByType[OdfReader::MIMETYPE];
        $t->same(['/'], $root['parts']);
        $t->same(1, $root['existsCount']);
        $t->same(['Pictures/missing.jpg'], array_column($result['importReport']['manifest']['missingItems'], 'part'));
        $t->same(['Pictures/hero.png', 'Pictures/cover.png', 'Pictures/missing.jpg'], array_column($result['media'], 'part'));
    },
    'reports ODT audio video media resource role conflicts in package provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $audioBytes = 'AUDIO-BYTES';
        $videoBytes = 'VIDEO-BYTES';
        $conflictAudioBytes = 'PICTURE-AUDIO';
        $conflictVideoBytes = 'POSTER-VIDEO';
        $replacementBytes = 'OBJECT-AUDIO';
        $thumbnailBytes = 'THUMB-VIDEO';
        $manifestWithAvResources = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Media/narration.ogg" manifest:media-type="audio/ogg; codecs=&quot;opus&quot;" manifest:size="' . strlen($audioBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Media/clip.mp4" manifest:media-type="video/mp4" manifest:size="' . strlen($videoBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/sound.ogg" manifest:media-type="audio/ogg" manifest:size="' . strlen($conflictAudioBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Media/poster.png" manifest:media-type="video/mp4" manifest:size="' . strlen($conflictVideoBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Media/missing.mp4" manifest:media-type="video/mp4"/>'
            . '<manifest:file-entry manifest:full-path="ObjectReplacements/sound.ogg" manifest:media-type="audio/ogg" manifest:size="' . strlen($replacementBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Thumbnails/poster.png" manifest:media-type="video/mp4" manifest:size="' . strlen($thumbnailBytes) . '"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithAvResources, null, null, [
            ['name' => 'Media/narration.ogg', 'data' => $audioBytes, 'compressionMethod' => 0],
            ['name' => 'Media/clip.mp4', 'data' => $videoBytes, 'compressionMethod' => 0],
            ['name' => 'Pictures/sound.ogg', 'data' => $conflictAudioBytes, 'compressionMethod' => 0],
            ['name' => 'Media/poster.png', 'data' => $conflictVideoBytes, 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/sound.ogg', 'data' => $replacementBytes, 'compressionMethod' => 0],
            ['name' => 'Thumbnails/poster.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
        ]));
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $provenanceParts = $provenance['parts'];
        $mediaResources = $provenance['mediaResources'];
        $resourceItemsByPart = [];
        foreach ($mediaResources['items'] as $item) {
            $resourceItemsByPart[$item['part']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same([
            'Pictures/hero.png',
            'Media/narration.ogg',
            'Media/clip.mp4',
            'Pictures/sound.ogg',
            'Media/poster.png',
            'Media/missing.mp4',
        ], array_column($result['media'], 'part'));
        $t->same(5, $provenance['mediaResourcePartCount']);
        $t->same(5, $provenance['roleCounts']['media-resource']);
        $t->same(['manifest-declared', 'media-resource'], $provenanceParts['Media/narration.ogg']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $provenanceParts['Media/clip.mp4']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $provenanceParts['Pictures/sound.ogg']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $provenanceParts['Media/poster.png']['roles']);
        $t->same(['object-replacement', 'manifest-declared'], $provenanceParts['ObjectReplacements/sound.ogg']['roles']);
        $t->same(['package-thumbnail', 'manifest-declared'], $provenanceParts['Thumbnails/poster.png']['roles']);

        $t->same('audio/ogg; codecs="opus"', $mediaByPart['Media/narration.ogg']['mediaType']);
        $t->same('audio/ogg', $mediaByPart['Media/narration.ogg']['mediaTypeBase']);
        $t->same(['codecs' => 'opus'], $mediaByPart['Media/narration.ogg']['mediaTypeParameterMap']);
        $t->same(strlen($audioBytes), $mediaByPart['Media/narration.ogg']['byteLength']);
        $t->same('video/mp4', $mediaByPart['Media/clip.mp4']['mediaTypeBase']);
        $t->same(strlen($videoBytes), $mediaByPart['Media/clip.mp4']['byteLength']);
        $t->same(false, $mediaByPart['Media/missing.mp4']['exists']);
        $t->same(null, $mediaByPart['Media/missing.mp4']['byteLength']);
        $t->same(false, $mediaByPart['Media/missing.mp4']['canExposeBytes']);

        $t->same(8, $mediaResources['manifestDeclaredCount']);
        $t->same(6, $mediaResources['mediaResourceCount']);
        $t->same(5, $mediaResources['mediaResourceExistingCount']);
        $t->same(1, $mediaResources['mediaResourceMissingCount']);
        $t->same(5, $mediaResources['mediaResourceCanExposeCount']);
        $t->same(7, $mediaResources['existingCount']);
        $t->same(1, $mediaResources['missingCount']);
        $t->same(['image' => 1, 'audio' => 3, 'video' => 4, 'other' => 0], $mediaResources['familyCounts']);
        $t->same([
            'audio/ogg' => 3,
            'image/png' => 1,
            'video/mp4' => 4,
        ], $mediaResources['mediaTypeBaseCounts']);
        $t->same(3, $mediaResources['roleConflictCount']);
        $t->same(2, $mediaResources['resourceRoleConflictCount']);
        $t->same(3, $mediaResources['pathMediaTypeConflictCount']);
        $t->same(['Pictures/sound.ogg', 'Media/poster.png', 'Thumbnails/poster.png'], array_column($mediaResources['pathMediaTypeConflictItems'], 'part'));
        $t->same([
            [
                'pathMediaFamily' => 'image',
                'declaredMediaFamily' => 'audio',
                'count' => 1,
                'parts' => ['Pictures/sound.ogg'],
            ],
            [
                'pathMediaFamily' => 'image',
                'declaredMediaFamily' => 'video',
                'count' => 2,
                'parts' => ['Media/poster.png', 'Thumbnails/poster.png'],
            ],
        ], $mediaResources['pathMediaTypeConflictPairs']);
        $t->same(1, $mediaResources['missingMediaResourceItemCount']);
        $t->same(['Media/missing.mp4'], array_column($mediaResources['missingMediaResourceItems'], 'part'));
        $t->same(2, $mediaResources['packageRolePrecedenceCount']);
        $t->same([
            'odf-media-resource-missing-package-part' => 1,
            'odf-media-resource-package-role-precedence' => 2,
            'odf-media-resource-role-conflict' => 3,
        ], $mediaResources['issueCodeCounts']);

        $t->same(['manifest-media-type', 'package-extension'], $resourceItemsByPart['Media/narration.ogg']['roleSources']);
        $t->same('audio', $resourceItemsByPart['Media/narration.ogg']['declaredMediaFamily']);
        $t->same('audio', $resourceItemsByPart['Media/narration.ogg']['packagePathMediaFamily']);
        $t->same(false, $resourceItemsByPart['Media/narration.ogg']['roleConflict']);
        $t->same(['manifest-media-type', 'pictures-path'], $resourceItemsByPart['Pictures/sound.ogg']['roleSources']);
        $t->same('audio', $resourceItemsByPart['Pictures/sound.ogg']['declaredMediaFamily']);
        $t->same('image', $resourceItemsByPart['Pictures/sound.ogg']['packagePathMediaFamily']);
        $t->same(true, $resourceItemsByPart['Pictures/sound.ogg']['roleConflict']);
        $t->same(true, $resourceItemsByPart['Pictures/sound.ogg']['pathMediaTypeConflict']);
        $t->same('image:audio', $resourceItemsByPart['Pictures/sound.ogg']['pathMediaTypeConflictPair']);
        $t->same(['odf-media-resource-role-conflict'], $resourceItemsByPart['Pictures/sound.ogg']['issues']);
        $t->same('video', $resourceItemsByPart['Media/poster.png']['declaredMediaFamily']);
        $t->same('image', $resourceItemsByPart['Media/poster.png']['packagePathMediaFamily']);
        $t->same(true, $resourceItemsByPart['Media/poster.png']['roleConflict']);
        $t->same('image:video', $resourceItemsByPart['Media/poster.png']['pathMediaTypeConflictPair']);
        $t->same(false, $resourceItemsByPart['Media/missing.mp4']['exists']);
        $t->same(['odf-media-resource-missing-package-part'], $resourceItemsByPart['Media/missing.mp4']['issues']);

        $replacement = $resourceItemsByPart['ObjectReplacements/sound.ogg'];
        $thumbnail = $resourceItemsByPart['Thumbnails/poster.png'];
        $t->same(false, $replacement['mediaResource']);
        $t->same(['object-replacement'], $replacement['packageRolePrecedence']);
        $t->same(['odf-media-resource-package-role-precedence'], $replacement['issues']);
        $t->same(false, $thumbnail['mediaResource']);
        $t->same(['package-thumbnail'], $thumbnail['packageRolePrecedence']);
        $t->same(['odf-media-resource-role-conflict', 'odf-media-resource-package-role-precedence'], $thumbnail['issues']);
        $t->same(false, in_array('ObjectReplacements/sound.ogg', array_column($result['media'], 'part'), true));
        $t->same(false, in_array('Thumbnails/poster.png', array_column($result['media'], 'part'), true));
        $t->same(false, in_array('media-resource', $provenanceParts['ObjectReplacements/sound.ogg']['roles'], true));
        $t->same(false, in_array('media-resource', $provenanceParts['Thumbnails/poster.png']['roles'], true));
        $t->same(false, $manifestByPart['ObjectReplacements/sound.ogg']['canExposeBytes']);
    },
    'diagnoses ODT manifest file entries missing media types before byte exposure' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $sidecarBytes = 'BINARYPAYLOAD';
        $manifestWithMissingMediaType = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/nameless.bin" manifest:media-type="" manifest:size="' . strlen($sidecarBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="Configurations2/" manifest:media-type=""/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithMissingMediaType, null, null, [
            ['name' => 'Pictures/nameless.bin', 'data' => $sidecarBytes, 'compressionMethod' => 0],
        ]));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $summary = $result['importReport']['manifest']['mediaTypeSummary'];
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $mediaParts = array_column($result['media'], 'part');

        $nameless = $manifestByPath['Pictures/nameless.bin'];
        $t->same('', $nameless['mediaType']);
        $t->same(true, $nameless['exists']);
        $t->same(strlen($sidecarBytes), $nameless['storedByteLength']);
        $t->same(null, $nameless['byteLength']);
        $t->same(false, $nameless['canExposeBytes']);
        $t->same(['odf-manifest-file-entry-missing-media-type'], $nameless['diagnostics']);

        $t->same($summary, $result['document']->attr('manifest')['mediaTypeSummary']);
        $t->same(2, $summary['emptyMediaTypeCount']);
        $t->same(['Pictures/nameless.bin', 'Configurations2/'], $summary['emptyMediaTypeParts']);
        $t->same(1, $summary['emptyMediaTypeDirectoryCount']);
        $t->same(['Configurations2/'], $summary['emptyMediaTypeDirectoryParts']);
        $t->same(1, $summary['emptyMediaTypeNonDirectoryCount']);
        $t->same('Pictures/nameless.bin', $summary['emptyMediaTypeNonDirectoryItems'][0]['part']);
        $t->same(false, $summary['emptyMediaTypeNonDirectoryItems'][0]['canExposeBytes']);
        $t->same(['odf-manifest-file-entry-missing-media-type'], $summary['emptyMediaTypeNonDirectoryItems'][0]['diagnostics']);
        $t->same(1, $summary['diagnosticCount']);
        $t->same(['odf-manifest-file-entry-missing-media-type' => 1], $summary['diagnosticCodeCounts']);
        $t->same('Pictures/nameless.bin', $summary['diagnostics'][0]['part']);
        $t->same(false, $summary['diagnostics'][0]['canExposeBytes']);

        $t->same(['odf-manifest-file-entry-missing-media-type'], $provenance['parts']['Pictures/nameless.bin']['manifestDiagnostics']);
        $t->same(false, $provenance['parts']['Pictures/nameless.bin']['canExposeBytes']);
        $t->same(false, in_array('Pictures/nameless.bin', $mediaParts, true));
    },
    'preserves ODT manifest media-type parameter provenance for review handoff' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $thumbnailBytes = 'THUMBNAIL';
        $manifestWithMediaTypeParameters = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/jpeg; charset=UTF-8; profile=&quot;review cover&quot;"/>'
            . '<manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png; role=&quot;preview&quot;" manifest:size="' . strlen($thumbnailBytes) . '"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithMediaTypeParameters, null, null, [
            ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
        ]));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $thumbnailsByPart = [];
        foreach ($result['importReport']['packageThumbnails']['items'] as $item) {
            $thumbnailsByPart[$item['part']] = $item;
        }
        $summaryByType = [];
        foreach ($result['importReport']['manifest']['mediaTypeSummary']['items'] as $item) {
            $summaryByType[$item['mediaType']] = $item;
        }
        $provenanceParts = $result['importReport']['manifest']['packageProvenance']['parts'];

        $hero = $manifestByPath['Pictures/hero.png'];
        $t->same('image/jpeg; charset=UTF-8; profile="review cover"', $hero['mediaType']);
        $t->same('image/jpeg', $hero['mediaTypeBase']);
        $t->same(true, $hero['mediaTypeHasParameters']);
        $t->same(2, $hero['mediaTypeParameterCount']);
        $t->same([
            ['name' => 'charset', 'value' => 'UTF-8', 'raw' => 'charset=UTF-8'],
            ['name' => 'profile', 'value' => 'review cover', 'raw' => 'profile="review cover"'],
        ], $hero['mediaTypeParameters']);
        $t->same(['charset' => 'UTF-8', 'profile' => 'review cover'], $hero['mediaTypeParameterMap']);

        $heroMedia = $mediaByPart['Pictures/hero.png'];
        $t->same('image/jpeg', $heroMedia['mediaTypeBase']);
        $t->same(['charset' => 'UTF-8', 'profile' => 'review cover'], $heroMedia['mediaTypeParameterMap']);
        $t->same(1, count($result['media']), 'parameterized ODF package thumbnails must stay out of document media handoff');

        $thumbnail = $thumbnailsByPart['Thumbnails/thumbnail.png'];
        $t->same('image/png; role="preview"', $thumbnail['mediaType']);
        $t->same('image/png', $thumbnail['mediaTypeBase']);
        $t->same(['role' => 'preview'], $thumbnail['mediaTypeParameterMap']);
        $t->same(false, $thumbnail['canExposeAsDocumentMedia']);

        $heroProvenance = $provenanceParts['Pictures/hero.png'];
        $t->same('image/jpeg; charset=UTF-8; profile="review cover"', $heroProvenance['manifestMediaType']);
        $t->same('image/jpeg', $heroProvenance['manifestMediaTypeBase']);
        $t->same(2, $heroProvenance['manifestMediaTypeParameterCount']);
        $t->same(['charset' => 'UTF-8', 'profile' => 'review cover'], $heroProvenance['manifestMediaTypeParameterMap']);

        $summary = $result['importReport']['manifest']['mediaTypeSummary'];
        $t->same($summary, $result['document']->attr('manifest')['mediaTypeSummary']);
        $t->same(2, $summary['parameterizedItemCount']);
        $t->same(['charset', 'profile', 'role'], $summary['mediaTypeParameterNames']);
        $t->same(4, $summary['mediaTypeCount']);
        $t->same(1, $summaryByType['image/jpeg']['count']);
        $t->same(['image/jpeg; charset=UTF-8; profile="review cover"'], $summaryByType['image/jpeg']['rawMediaTypes']);
        $t->same(1, $summaryByType['image/jpeg']['rawMediaTypeCount']);
        $t->same(1, $summaryByType['image/jpeg']['parameterizedItemCount']);
        $t->same(['charset', 'profile'], $summaryByType['image/jpeg']['mediaTypeParameterNames']);
        $t->same(['Pictures/hero.png'], $summaryByType['image/jpeg']['parts']);
        $t->same(1, $summaryByType['image/png']['count']);
        $t->same(['image/png; role="preview"'], $summaryByType['image/png']['rawMediaTypes']);
        $t->same(['role'], $summaryByType['image/png']['mediaTypeParameterNames']);
        $t->same(['Thumbnails/thumbnail.png'], $summaryByType['image/png']['parts']);
    },
    'reports ODT manifest ZIP compression provenance for package media' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $sourceBytes = 'SIDECAR-RAW';
        $manifestWithCompression = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/source.raw" manifest:media-type="application/octet-stream" manifest:size="' . strlen($sourceBytes) . '"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestWithCompression],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/source.raw', 'data' => $sourceBytes, 'compressionMethod' => 12],
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name')));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $summary = $result['importReport']['manifest']['mediaTypeSummary'];
        $summaryByType = [];
        foreach ($summary['items'] as $item) {
            $summaryByType[$item['mediaType']] = $item;
        }

        $hero = $manifestByPath['Pictures/hero.png'];
        $t->same(0, $hero['compressionMethod']);
        $t->same('stored', $hero['compressionMethodName']);
        $t->same(7, $hero['compressedByteLength']);
        $t->same(true, $hero['canExposeBytes']);
        $t->same('deflated', $manifestByPath['content.xml']['compressionMethodName']);

        $sidecar = $manifestByPath['Pictures/source.raw'];
        $t->same(null, $sidecar['byteLength']);
        $t->same(strlen($sourceBytes), $sidecar['storedByteLength']);
        $t->same(strlen($sourceBytes), $sidecar['compressedByteLength']);
        $t->same(12, $sidecar['compressionMethod']);
        $t->same('unsupported', $sidecar['compressionMethodName']);
        $t->same(false, $sidecar['canExposeBytes']);
        $t->same(null, $sidecar['crc32']);
        $t->same(sprintf('%08x', crc32($sourceBytes)), $sidecar['storedCrc32']);

        $sidecarMedia = $mediaByPart['Pictures/source.raw'];
        $t->same(true, $sidecarMedia['exists']);
        $t->same(null, $sidecarMedia['byteLength']);
        $t->same(strlen($sourceBytes), $sidecarMedia['storedByteLength']);
        $t->same(strlen($sourceBytes), $sidecarMedia['compressedByteLength']);
        $t->same(12, $sidecarMedia['compressionMethod']);
        $t->same('unsupported', $sidecarMedia['compressionMethodName']);
        $t->same(false, $sidecarMedia['canExposeBytes']);
        $t->same(null, $sidecarMedia['crc32']);
        $t->same(sprintf('%08x', crc32($sourceBytes)), $sidecarMedia['storedCrc32']);

        $t->same(1, $summary['storedCompressionMethodCount']);
        $t->same(3, $summary['deflatedCompressionMethodCount']);
        $t->same(1, $summary['unsupportedCompressionMethodCount']);
        $t->same(1, $summaryByType['application/octet-stream']['unsupportedCompressionMethodCount']);
        $t->same(strlen($sourceBytes), $summaryByType['application/octet-stream']['compressedByteLength']);
        $t->same(0, $summaryByType['application/octet-stream']['exposableByteLength']);
        $t->same(1, $summaryByType['image/png']['storedCompressionMethodCount']);
        $t->same(3, $summaryByType['text/xml']['deflatedCompressionMethodCount']);
        $t->same(['Pictures/hero.png', 'Pictures/source.raw'], array_column($result['media'], 'part'));

        $image = $result['document']->children[5]->children[0];
        $t->same(0, $image->attr('compressionMethod'));
        $t->same('stored', $image->attr('compressionMethodName'));
        $t->same(7, $image->attr('compressedByteLength'));
        $t->same(7, $image->attr('bytes'));
    },
    'preserves ODT package handoff order across missing and unsupported byte blocks' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $contentXml, $stylesXml, $metaXml): void {
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:settings/>
</office:document-settings>
XML;
        $rdfXml = <<<'XML'
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="content.xml"/>
</rdf:RDF>
XML;
        $unsupportedBytes = 'UNSUPPORTED-BLOCK';
        $scriptXml = '<script:module xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"/>';
        $manifestWithPackageHandoff = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/unsupported.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>
</manifest:manifest>
XML;
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestWithPackageHandoff],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/unsupported.bin', 'data' => $unsupportedBytes, 'compressionMethod' => 12],
            ['name' => 'Basic/Standard/Module1.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
            ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name')));
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }

        $manifestReport = $result['importReport']['manifest'];
        $provenance = $manifestReport['packageProvenance'];
        $packageParts = $provenance['parts'];
        $manifestOrder = $provenance['manifestFileEntryOrder'];
        $manifestOrderByPart = [];
        foreach ($manifestOrder as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestOrderByPart[$item['part']] = $item;
            }
        }
        $localOrder = $provenance['localHeaderOrder'];
        $compression = $provenance['compressionMethods'];

        $t->same([
            '/',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'settings.xml',
            'Pictures/hero.png',
            'Pictures/missing.png',
            'Pictures/unsupported.bin',
            'Basic/Standard/Module1.xml',
            'manifest.rdf',
        ], array_column($manifestOrder, 'fullPath'));
        $t->same(array_column($parts, 'name'), array_column($localOrder['entries'], 'name'));
        $t->same(10, $provenance['entryCount']);
        $t->same(9, $provenance['manifestDeclaredPartCount']);
        $t->same(10, $provenance['manifestFileEntryCount']);
        $t->same(6, $provenance['corePackagePartCount']);
        $t->same(2, $provenance['mediaResourcePartCount']);
        $t->same(1, $provenance['rdfMetadataPartCount']);
        $t->same(1, $provenance['roleCounts']['script-package']);
        $t->same(1, $provenance['roleCounts']['rdf-metadata']);

        $t->same(['odf-mimetype'], $packageParts['mimetype']['roles']);
        $t->same(['odf-manifest'], $packageParts['META-INF/manifest.xml']['roles']);
        $t->same(['odf-content', 'manifest-declared'], $packageParts['content.xml']['roles']);
        $t->same(['odf-styles', 'manifest-declared'], $packageParts['styles.xml']['roles']);
        $t->same(['odf-meta', 'manifest-declared'], $packageParts['meta.xml']['roles']);
        $t->same(['odf-settings', 'manifest-declared'], $packageParts['settings.xml']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $packageParts['Pictures/hero.png']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $packageParts['Pictures/unsupported.bin']['roles']);
        $t->same(['manifest-declared', 'script-package'], $packageParts['Basic/Standard/Module1.xml']['roles']);
        $t->same(['rdf-metadata', 'manifest-declared'], $packageParts['manifest.rdf']['roles']);

        $t->same(['Pictures/hero.png', 'Pictures/missing.png', 'Pictures/unsupported.bin'], array_column($result['media'], 'part'));
        $t->same(true, $mediaByPart['Pictures/hero.png']['canExposeBytes']);
        $t->same('package-bytes-exposable', $mediaByPart['Pictures/hero.png']['byteExposurePolicy']);
        $t->same(false, $mediaByPart['Pictures/missing.png']['exists']);
        $t->same(null, $mediaByPart['Pictures/missing.png']['byteLength']);
        $t->same('missing-package-part', $mediaByPart['Pictures/missing.png']['byteExposurePolicy']);
        $t->same(true, $mediaByPart['Pictures/unsupported.bin']['exists']);
        $t->same(null, $mediaByPart['Pictures/unsupported.bin']['byteLength']);
        $t->same(strlen($unsupportedBytes), $mediaByPart['Pictures/unsupported.bin']['storedByteLength']);
        $t->same(12, $mediaByPart['Pictures/unsupported.bin']['compressionMethod']);
        $t->same('unsupported', $mediaByPart['Pictures/unsupported.bin']['compressionMethodName']);
        $t->same(false, $mediaByPart['Pictures/unsupported.bin']['canExposeBytes']);
        $t->same('unsupported-compression-bytes-blocked', $mediaByPart['Pictures/unsupported.bin']['byteExposurePolicy']);

        $t->same('missing-package-part', $manifestByPart['Pictures/missing.png']['byteExposurePolicy']);
        $t->same('unsupported-compression-bytes-blocked', $manifestByPart['Pictures/unsupported.bin']['byteExposurePolicy']);
        $t->same(true, $manifestByPart['Basic/Standard/Module1.xml']['scriptPackagePart']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->same('rdf-metadata-bytes-blocked', $manifestByPart['manifest.rdf']['byteExposurePolicy']);
        $t->same('unsupported-compression-bytes-blocked', $packageParts['Pictures/unsupported.bin']['byteExposurePolicy']);
        $t->same(true, $manifestOrderByPart['Basic/Standard/Module1.xml']['scriptPackagePart']);
        $t->same(false, $manifestOrderByPart['Basic/Standard/Module1.xml']['configurationPackagePart']);
        $t->same(true, $packageParts['Basic/Standard/Module1.xml']['scriptPackagePart']);
        $t->same('script-package-bytes-blocked', $packageParts['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->same('rdf-metadata-bytes-blocked', $packageParts['manifest.rdf']['byteExposurePolicy']);
        $t->same(['Pictures/missing.png'], array_column($manifestReport['missingItems'], 'part'));
        $t->same(1, $compression['unsupportedCompressionMethodCount']);
        $t->same(['Pictures/unsupported.bin'], array_column($compression['unsupportedEntries'], 'name'));
        $t->same(1, $result['rdfMetadata']['partCount']);
        $t->same(1, $result['scriptMetadata']['count']);
    },
    'reports ODT manifest declared size mismatches for package review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml): void {
        $manifestWithDeclaredSizes = str_replace(
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            ],
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="1"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048"/>',
            ],
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithDeclaredSizes));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $mismatches = $result['importReport']['manifest']['declaredSizeMismatches'];

        $t->same(2, $result['importReport']['manifest']['declaredSizeMismatchCount']);
        $t->same('content.xml', $mismatches[0]['part']);
        $t->same(1, $mismatches[0]['declaredSize']);
        $t->same(strlen($contentXml), $mismatches[0]['byteLength']);
        $t->same('Pictures/hero.png', $mismatches[1]['part']);
        $t->same(2048, $mismatches[1]['declaredSize']);
        $t->same(7, $mismatches[1]['byteLength']);
        $t->same(true, $manifestByPath['content.xml']['declaredSizeMismatch']);
        $t->same(true, $manifestByPath['Pictures/hero.png']['declaredSizeMismatch']);
        $t->same(2048, $result['media'][0]['declaredSize']);
        $t->same(true, $result['media'][0]['declaredSizeMismatch']);
        $t->same(0, $result['importReport']['manifest']['encryptedCount']);
        $t->same(8, count($result['document']->children));
    },
    'preserves ODT manifest invalid declared size provenance for package review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $oversizedSize = '92233720368547758070';
        $manifestWithInvalidSizes = str_replace(
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            ],
            [
                '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="not-a-number"/>',
                '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="' . $oversizedSize . '"/>',
            ],
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithInvalidSizes));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $summary = $result['importReport']['manifest']['mediaTypeSummary'];
        $summaryByType = [];
        foreach ($summary['items'] as $item) {
            $summaryByType[$item['mediaType']] = $item;
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $order = $provenance['manifestFileEntryOrder'];
        $heroMedia = $result['media'][0];

        $t->same('not-a-number', $manifestByPath['content.xml']['declaredSizeRaw']);
        $t->same(null, $manifestByPath['content.xml']['declaredSize']);
        $t->same(false, $manifestByPath['content.xml']['declaredSizeValid']);
        $t->same(true, $manifestByPath['content.xml']['declaredSizeInvalid']);
        $t->same(['odf-manifest-invalid-declared-size'], $manifestByPath['content.xml']['diagnostics']);

        $t->same($oversizedSize, $manifestByPath['Pictures/hero.png']['declaredSizeRaw']);
        $t->same(null, $manifestByPath['Pictures/hero.png']['declaredSize']);
        $t->same(false, $manifestByPath['Pictures/hero.png']['declaredSizeValid']);
        $t->same(true, $manifestByPath['Pictures/hero.png']['declaredSizeInvalid']);
        $t->same(false, $manifestByPath['Pictures/hero.png']['declaredSizeMismatch']);
        $t->same(true, $manifestByPath['Pictures/hero.png']['canExposeBytes']);

        $t->same($oversizedSize, $heroMedia['declaredSizeRaw']);
        $t->same(true, $heroMedia['declaredSizeInvalid']);
        $t->same(false, $heroMedia['declaredSizeMismatch']);
        $t->same(true, $heroMedia['canExposeBytes']);
        $t->same(7, $heroMedia['byteLength']);

        $t->same(2, $summary['invalidDeclaredSizeCount']);
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($summary['invalidDeclaredSizeItems'], 'part'));
        $t->same([
            'odf-manifest-invalid-declared-size' => 2,
        ], $summary['diagnosticCodeCounts']);
        $t->same(0, $summary['declaredSizeMismatchCount']);
        $t->same(0, $summary['declaredSize']);
        $t->same(1, $summaryByType['text/xml']['invalidDeclaredSizeCount']);
        $t->same(1, $summaryByType['image/png']['invalidDeclaredSizeCount']);

        $t->same('not-a-number', $order[1]['declaredSizeRaw']);
        $t->same(true, $order[1]['declaredSizeInvalid']);
        $t->same($oversizedSize, $order[4]['declaredSizeRaw']);
        $t->same(true, $order[4]['declaredSizeInvalid']);
        $t->same($oversizedSize, $provenance['parts']['Pictures/hero.png']['manifestDeclaredSizeRaw']);
        $t->same(true, $provenance['parts']['Pictures/hero.png']['manifestDeclaredSizeInvalid']);
    },
    'preserves ODT manifest key derivation size for encrypted package parts' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="sha256-checksum">
      <manifest:algorithm manifest:algorithm-name="AES-256-CBC" manifest:initialisation-vector="iv-256"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="32" manifest:iteration-count="600000" manifest:salt="salt-256"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithEncryptedMedia = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithEncryptedMedia));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $heroManifest = $manifestByPath['Pictures/hero.png'];
        $manifestEncryption = $heroManifest['encryption'];
        $mediaEncryption = $result['media'][0]['encryption'];
        $reportEncryption = $result['importReport']['encryption']['items'][0]['encryption'];
        $documentManifestItems = $result['document']->attr('manifest')['items'];
        $documentManifestByPath = [];
        foreach ($documentManifestItems as $item) {
            $documentManifestByPath[$item['fullPath']] = $item;
        }
        $image = $result['document']->children[5]->children[0];
        $imageEncryption = $image->attr('encryption');

        $t->same(true, $heroManifest['encrypted']);
        $t->same(false, $heroManifest['canExposeBytes']);
        $t->same('AES-256-CBC', $manifestEncryption['algorithm']['name']);
        $t->same('PBKDF2', $manifestEncryption['keyDerivation']['name']);
        $t->same(32, $manifestEncryption['keyDerivation']['keySize']);
        $t->same(600000, $manifestEncryption['keyDerivation']['iterationCount']);
        $t->same('salt-256', $manifestEncryption['keyDerivation']['salt']);
        $t->same(32, $manifestEncryption['startKeyGeneration']['keySize']);
        $t->same(32, $mediaEncryption['keyDerivation']['keySize']);
        $t->same(32, $reportEncryption['keyDerivation']['keySize']);
        $t->same(32, $documentManifestByPath['Pictures/hero.png']['encryption']['keyDerivation']['keySize']);
        $t->same(32, $imageEncryption['keyDerivation']['keySize']);
        $t->same(null, $image->attr('bytes'), 'encrypted media bytes must remain blocked');
    },
    'reports encrypted ODT manifest resources without exposing media bytes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="checksum-base64">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="iv-base64"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="16" manifest:iteration-count="1024" manifest:salt="salt-base64"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithEncryptedMedia = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithEncryptedMedia));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $heroManifest = $manifestByPath['Pictures/hero.png'];
        $t->same(true, $heroManifest['encrypted']);
        $t->same(false, $heroManifest['canExposeBytes']);
        $t->same(null, $heroManifest['byteLength']);
        $t->same(7, $heroManifest['storedByteLength']);
        $t->same(null, $heroManifest['crc32']);
        $t->same(sprintf('%08x', crc32('PNGDATA')), $heroManifest['storedCrc32']);
        $t->same(2048, $heroManifest['declaredSize']);
        $t->same('SHA1/1K', $heroManifest['encryption']['checksumType']);
        $t->same('checksum-base64', $heroManifest['encryption']['checksum']);
        $t->same('Blowfish CFB', $heroManifest['encryption']['algorithm']['name']);
        $t->same('iv-base64', $heroManifest['encryption']['algorithm']['initialisationVector']);
        $t->same('PBKDF2', $heroManifest['encryption']['keyDerivation']['name']);
        $t->same(16, $heroManifest['encryption']['keyDerivation']['keySize']);
        $t->same(1024, $heroManifest['encryption']['keyDerivation']['iterationCount']);
        $t->same('salt-base64', $heroManifest['encryption']['keyDerivation']['salt']);
        $t->same('SHA1', $heroManifest['encryption']['startKeyGeneration']['name']);
        $t->same(20, $heroManifest['encryption']['startKeyGeneration']['keySize']);

        $media = $result['media'][0];
        $t->same('Pictures/hero.png', $media['part']);
        $t->same(true, $media['encrypted']);
        $t->same(false, $media['canExposeBytes']);
        $t->same(null, $media['byteLength']);
        $t->same(7, $media['storedByteLength']);
        $t->same('Blowfish CFB', $media['encryption']['algorithm']['name']);

        $image = $result['document']->children[5]->children[0];
        $t->same(true, $image->attr('encrypted'));
        $t->same(false, $image->attr('canExposeBytes'));
        $t->same('not-exposed', $image->attr('bytes', 'not-exposed'));
        $t->same('Blowfish CFB', $image->attr('encryption')['algorithm']['name']);

        $t->same(1, $result['importReport']['manifest']['encryptedCount']);
        $t->same('Pictures/hero.png', $result['importReport']['manifest']['encryptedItems'][0]['part']);
        $t->same(1, $result['importReport']['encryption']['count']);
        $t->same(['Pictures/hero.png'], $result['importReport']['encryption']['encryptedParts']);
        $t->same(0, count($result['importReport']['manifest']['missingItems']));

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<img src="Pictures/hero.png" alt="Hero alt text" title="Hero title"/>', $blocks);
    },
    'preserves ODT manifest encryption child multiplicity provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="multi-checksum" xmlns:review="urn:example:review">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialization-vector="iv-a"/>
      <manifest:algorithm manifest:algorithm-name="AES256" manifest:initialisation-vector="iv-b"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="1024" manifest:salt="salt-a"/>
      <manifest:key-derivation manifest:key-derivation-name="Argon2id" manifest:iteration-count="3" manifest:salt="salt-b"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
      <review:extension review:mode="strict"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithRepeatedEncryptionChildren = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithRepeatedEncryptionChildren));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $encryption = $manifestByPath['Pictures/hero.png']['encryption'];
        $t->same('SHA256/1K', $encryption['checksumType']);
        $t->same('multi-checksum', $encryption['checksum']);
        $t->same('Blowfish CFB', $encryption['algorithm']['name']);
        $t->same('iv-a', $encryption['algorithm']['initialisationVector']);
        $t->same(2, $encryption['algorithmCount']);
        $t->same(['Blowfish CFB', 'AES256'], array_column($encryption['algorithms'], 'name'));
        $t->same(['iv-a', 'iv-b'], array_column($encryption['algorithms'], 'initialisationVector'));
        $t->same('PBKDF2', $encryption['keyDerivation']['name']);
        $t->same(2, $encryption['keyDerivationCount']);
        $t->same(['PBKDF2', 'Argon2id'], array_column($encryption['keyDerivations'], 'name'));
        $t->same([1024, 3], array_column($encryption['keyDerivations'], 'iterationCount'));
        $t->same('SHA1', $encryption['startKeyGeneration']['name']);
        $t->same(2, $encryption['startKeyGenerationCount']);
        $t->same(['SHA1', 'SHA256'], array_column($encryption['startKeyGenerations'], 'name'));
        $t->same([20, 32], array_column($encryption['startKeyGenerations'], 'keySize'));
        $t->same(1, $encryption['unknownChildCount']);
        $t->same('review:extension', $encryption['unknownChildren'][0]['name']);
        $t->same('urn:example:review', $encryption['unknownChildren'][0]['namespaceUri']);
        $t->same('extension', $encryption['unknownChildren'][0]['localName']);
        $t->same(4, $encryption['issueCount']);
        $t->same([
            'odf-manifest-encryption-multiple-algorithms',
            'odf-manifest-encryption-multiple-key-derivations',
            'odf-manifest-encryption-multiple-start-key-generations',
            'odf-manifest-encryption-unknown-child',
        ], $encryption['issueCodes']);

        $mediaEncryption = $result['media'][0]['encryption'];
        $imageEncryption = $result['document']->children[5]->children[0]->attr('encryption');
        $reportEncryption = $result['importReport']['manifest']['encryptedItems'][0]['encryption'];
        $t->same($encryption, $mediaEncryption);
        $t->same($encryption['issueCodes'], $imageEncryption['issueCodes']);
        $t->same($encryption['algorithms'], $reportEncryption['algorithms']);
        $t->same(false, $result['media'][0]['canExposeBytes']);
        $t->same(1, $result['importReport']['encryption']['count']);

        $compactPackage = OpenDocumentPackage::fromPackage($buildOdtPackage(null, $manifestWithRepeatedEncryptionChildren));
        $compactEncryption = $compactPackage->manifestEntry('Pictures/hero.png')['encryption'];
        $t->same($encryption['issueCodes'], $compactEncryption['issueCodes']);
        $t->same($encryption['algorithms'], $compactEncryption['algorithms']);
        $t->same($encryption['unknownChildren'], $compactEncryption['unknownChildren']);
    },
    'preserves ODT repeated manifest encryption data record provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="first-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="first-iv"/>
    </manifest:encryption-data>
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="second-checksum">
      <manifest:algorithm manifest:algorithm-name="AES256" manifest:initialization-vector="second-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="2048" manifest:salt="second-salt"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithRepeatedEncryptionData = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithRepeatedEncryptionData));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $hero = $manifestByPath['Pictures/hero.png'];
        $encryption = $hero['encryption'];
        $t->same(true, $hero['encrypted']);
        $t->same(false, $hero['canExposeBytes']);
        $t->same('SHA1/1K', $encryption['checksumType']);
        $t->same('first-checksum', $encryption['checksum']);
        $t->same('Blowfish CFB', $encryption['algorithm']['name']);
        $t->same('first-iv', $encryption['algorithm']['initialisationVector']);
        $t->same(2, $encryption['recordCount']);
        $t->same('SHA1/1K', $encryption['records'][0]['checksumType']);
        $t->same('SHA256/1K', $encryption['records'][1]['checksumType']);
        $t->same('second-checksum', $encryption['records'][1]['checksum']);
        $t->same('AES256', $encryption['records'][1]['algorithm']['name']);
        $t->same('second-iv', $encryption['records'][1]['algorithm']['initialisationVector']);
        $t->same('PBKDF2', $encryption['records'][1]['keyDerivation']['name']);
        $t->same(2048, $encryption['records'][1]['keyDerivation']['iterationCount']);
        $t->same('second-salt', $encryption['records'][1]['keyDerivation']['salt']);
        $t->same(1, $encryption['issueCount']);
        $t->same(['odf-manifest-encryption-multiple-encryption-data'], $encryption['issueCodes']);

        $mediaEncryption = $result['media'][0]['encryption'];
        $imageEncryption = $result['document']->children[5]->children[0]->attr('encryption');
        $reportEncryption = $result['importReport']['manifest']['encryptedItems'][0]['encryption'];
        $t->same($encryption, $mediaEncryption);
        $t->same($encryption, $reportEncryption);
        $t->same($encryption['records'], $imageEncryption['records']);
        $t->same(false, $result['media'][0]['canExposeBytes']);
        $t->same(1, $result['importReport']['encryption']['count']);
        $t->same(['Pictures/hero.png'], $result['importReport']['encryption']['encryptedParts']);

        $compactPackage = OpenDocumentPackage::fromPackage($buildOdtPackage(null, $manifestWithRepeatedEncryptionData));
        $compactEncryption = $compactPackage->manifestEntry('Pictures/hero.png')['encryption'];
        $compactSummary = $compactPackage->summarize();
        $compactReviewByPath = [];
        foreach ($compactSummary['manifestReview']['items'] as $item) {
            $compactReviewByPath[$item['path']] = $item;
        }

        $t->same(2, $compactEncryption['recordCount']);
        $t->same($encryption['issueCodes'], $compactEncryption['issueCodes']);
        $t->same($encryption['records'], $compactEncryption['records']);
        $t->same(false, $compactPackage->manifestEntry('Pictures/hero.png')['canExposeBytes']);
        $t->same(2, $compactSummary['mediaParts'][0]['encryptionRecordCount']);
        $t->same($encryption['issueCodes'], $compactSummary['mediaParts'][0]['encryptionIssueCodes']);
        $t->same($compactEncryption, $compactSummary['mediaParts'][0]['encryption']);
        $t->same(2, $compactReviewByPath['Pictures/hero.png']['encryptionRecordCount']);
        $t->same($encryption['issueCodes'], $compactReviewByPath['Pictures/hero.png']['encryptionIssueCodes']);
        $t->same($compactEncryption, $compactReviewByPath['Pictures/hero.png']['encryption']);
        $t->same(2, $compactSummary['packageInventory']['parts']['Pictures/hero.png']['manifestEncryptionRecordCount']);
        $t->same($encryption['issueCodes'], $compactSummary['packageInventory']['parts']['Pictures/hero.png']['manifestEncryptionIssueCodes']);
    },
    'summarizes ODT manifest encryption methods across blocked package parts' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedEntries = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="first-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="first-iv"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="second-checksum">
      <manifest:algorithm manifest:algorithm-name="AES256" manifest:initialization-vector="second-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="2048" manifest:salt="second-salt"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml" manifest:size="13">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="macro-checksum">
      <manifest:algorithm manifest:algorithm-name="ChaCha20" manifest:initialisation-vector="macro-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="Argon2id" manifest:iteration-count="3" manifest:salt="macro-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithEncryptionSummary = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $encryptedEntries,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithEncryptionSummary, null, null, [
            ['name' => 'Basic/Standard/Module1.xml', 'data' => '<script/>', 'compressionMethod' => 0],
        ]));

        $encryption = $result['importReport']['encryption'];
        $summary = $encryption['summary'];
        $manifestSummary = $result['importReport']['manifest']['encryption'];
        $documentSummary = $result['document']->attr('manifest')['encryption'];

        $t->same(2, $encryption['count']);
        $t->same(['Pictures/hero.png', 'Basic/Standard/Module1.xml'], $encryption['encryptedParts']);
        $t->same(2, $summary['encryptedItemCount']);
        $t->same(3, $summary['recordCount']);
        $t->same([
            'AES256' => 1,
            'Blowfish CFB' => 1,
            'ChaCha20' => 1,
        ], $summary['algorithmNameCounts']);
        $t->same([
            'Argon2id' => 1,
            'PBKDF2' => 1,
        ], $summary['keyDerivationNameCounts']);
        $t->same([
            'SHA1' => 1,
            'SHA256' => 1,
        ], $summary['startKeyGenerationNameCounts']);
        $t->same([
            'SHA1/1K' => 2,
            'SHA256/1K' => 1,
        ], $summary['checksumTypeCounts']);
        $t->same([
            'odf-manifest-encryption-multiple-encryption-data' => 1,
        ], $summary['issueCodeCounts']);
        $t->same(1, $summary['issueItemCount']);

        $hero = $summary['items'][0];
        $script = $summary['items'][1];
        $t->same('Pictures/hero.png', $hero['part']);
        $t->same(2, $hero['encryptionRecordCount']);
        $t->same(['Blowfish CFB', 'AES256'], $hero['algorithmNames']);
        $t->same(['SHA1/1K', 'SHA256/1K'], $hero['checksumTypes']);
        $t->same(['odf-manifest-encryption-multiple-encryption-data'], $hero['issueCodes']);
        $t->same('Basic/Standard/Module1.xml', $script['part']);
        $t->same('encrypted-resource-bytes-blocked', $script['byteExposurePolicy']);
        $t->same(1, $script['encryptionRecordCount']);
        $t->same(['ChaCha20'], $script['algorithmNames']);
        $t->same(['Argon2id'], $script['keyDerivationNames']);
        $t->same(['SHA256'], $script['startKeyGenerationNames']);
        $t->same('encrypted-resource-bytes-blocked', $result['importReport']['manifest']['packageProvenance']['parts']['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->true(in_array('script-package', $result['importReport']['manifest']['packageProvenance']['parts']['Basic/Standard/Module1.xml']['roles'], true));
        $t->same($summary, $manifestSummary);
        $t->same($summary['algorithmNameCounts'], $documentSummary['algorithmNameCounts']);
        $t->same($summary['items'], $documentSummary['items']);
    },
    'reports ODT byte exposure policy and encryption provenance in package inventory' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $reviewEntries = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="first-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="first-iv"/>
    </manifest:encryption-data>
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="second-checksum">
      <manifest:algorithm manifest:algorithm-name="AES256" manifest:initialization-vector="second-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/nameless.bin"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml"/>
XML;
        $manifestWithReviewEntries = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $reviewEntries,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithReviewEntries, null, null, [
            ['name' => 'Pictures/nameless.bin', 'data' => 'BINARY', 'compressionMethod' => 0],
            ['name' => 'Basic/Standard/Module1.xml', 'data' => '<script:module xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"/>', 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/object1.bin', 'data' => 'UNDECLARED', 'compressionMethod' => 0],
        ]));

        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }

        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $parts = $provenance['parts'];
        $heroEncryption = $manifestByPart['Pictures/hero.png']['encryption'];
        $undeclared = $result['importReport']['manifest']['undeclaredEntries'][0];

        $t->same([
            'encrypted-resource-bytes-blocked' => 1,
            'missing-media-type-bytes-blocked' => 1,
            'missing-package-part' => 1,
            'package-bytes-exposable' => 3,
            'package-root-no-bytes' => 1,
            'script-package-bytes-blocked' => 1,
        ], $provenance['manifestByteExposurePolicyCounts']);
        $t->same(8, $provenance['manifestByteExposurePolicyItemCount']);
        $t->same(
            ['/', 'content.xml', 'styles.xml', 'meta.xml', 'Pictures/hero.png', 'Pictures/missing.png', 'Pictures/nameless.bin', 'Basic/Standard/Module1.xml'],
            array_column($provenance['manifestByteExposurePolicyItems'], 'fullPath')
        );
        $t->same([
            'encrypted-resource-bytes-blocked' => 1,
            'missing-media-type-bytes-blocked' => 1,
            'object-replacement-package-bytes-blocked' => 1,
            'package-bytes-exposable' => 3,
            'script-package-bytes-blocked' => 1,
        ], $provenance['packagePartByteExposurePolicyCounts']);
        $t->same(7, $provenance['packagePartByteExposurePolicyItemCount']);
        $t->same(
            ['content.xml', 'styles.xml', 'meta.xml', 'Pictures/hero.png', 'Pictures/nameless.bin', 'Basic/Standard/Module1.xml', 'ObjectReplacements/object1.bin'],
            array_column($provenance['packagePartByteExposurePolicyItems'], 'part')
        );
        $t->same('package-root-no-bytes', $provenance['manifestFileEntryOrder'][0]['byteExposurePolicy']);
        $t->same('package-bytes-exposable', $manifestByPart['content.xml']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $manifestByPart['Pictures/hero.png']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $result['media'][0]['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $parts['Pictures/hero.png']['byteExposurePolicy']);
        $t->same($heroEncryption, $parts['Pictures/hero.png']['manifestEncryption']);
        $t->same(2, $parts['Pictures/hero.png']['manifestEncryptionRecordCount']);
        $t->same(['odf-manifest-encryption-multiple-encryption-data'], $parts['Pictures/hero.png']['manifestEncryptionIssueCodes']);
        $t->same('missing-package-part', $manifestByPart['Pictures/missing.png']['byteExposurePolicy']);
        $t->same('missing-media-type-bytes-blocked', $manifestByPart['Pictures/nameless.bin']['byteExposurePolicy']);
        $t->same(['odf-manifest-file-entry-missing-media-type'], $manifestByPart['Pictures/nameless.bin']['diagnostics']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->same('script-package-bytes-blocked', $parts['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->same('object-replacement-package-bytes-blocked', $undeclared['byteExposurePolicy']);
        $t->same('object-replacement-package-bytes-blocked', $parts['ObjectReplacements/object1.bin']['byteExposurePolicy']);
        $t->same(['odf-manifest-undeclared-package-entry'], $undeclared['diagnostics']);
    },
    'preserves ODT sidecar blocked-byte provenance and package ordering' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $stylesXml, $metaXml): void {
        $declaredImage = 'DECLAREDPNG';
        $unsupportedImage = 'WEBP-BYTES';
        $basicModuleXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Approve
End Sub</script:module>
XML;
        $rdfXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <rdf:Description rdf:about="Pictures/declared.png">
    <dc:format>image/png</dc:format>
  </rdf:Description>
</rdf:RDF>
XML;
        $contentWithBasicReference = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Macro <text:a xlink:href="https://example.test/review.odt"><office:event-listeners><script:event-listener script:event-name="dom:activate" script:language="ooo:Basic" xlink:href="vnd.sun.star.script:Standard.Review.Approve?language=Basic&amp;location=document" xlink:type="simple"/></office:event-listeners>review</text:a> remains inert.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $manifestWithSidecars = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Basic/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="144"/>
  <manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>
  <manifest:file-entry manifest:full-path="Pictures/declared.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/unsupported.webp" manifest:media-type="image/webp"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestWithSidecars, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentWithBasicReference, 'compressionMethod' => 0],
            ['name' => 'Basic/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Basic/Standard/Review.xml', 'data' => $basicModuleXml, 'compressionMethod' => 0],
            ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/declared.png', 'data' => $declaredImage, 'compressionMethod' => 0],
            ['name' => 'Pictures/unsupported.webp', 'data' => $unsupportedImage, 'compressionMethod' => 12],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 8],
        ];
        $centralOrder = [
            'mimetype',
            'content.xml',
            'Basic/Standard/Review.xml',
            'manifest.rdf',
            'Pictures/unsupported.webp',
            'Pictures/declared.png',
            'styles.xml',
            'meta.xml',
            'Basic/',
            'META-INF/manifest.xml',
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, $centralOrder));
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $scriptByPart = [];
        foreach ($result['scriptMetadata']['parts'] as $item) {
            $scriptByPart[$item['part']] = $item;
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $inventory = $provenance['parts'];
        $compression = $provenance['compressionMethods'];
        $rdfPart = $result['rdfMetadata']['parts'][0];

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(['/', 'content.xml', 'Basic/', 'Basic/Standard/Review.xml', 'manifest.rdf', 'Pictures/declared.png', 'Pictures/missing.png', 'Pictures/unsupported.webp', 'styles.xml', 'meta.xml'], array_column($provenance['manifestFileEntryOrder'], 'fullPath'));
        $t->same([
            'package-root-no-bytes',
            'package-bytes-exposable',
            'directory-entry-no-bytes',
            'script-package-bytes-blocked',
            'rdf-metadata-bytes-blocked',
            'package-bytes-exposable',
            'missing-package-part',
            'unsupported-compression-bytes-blocked',
            'package-bytes-exposable',
            'package-bytes-exposable',
        ], array_column($provenance['manifestFileEntryOrder'], 'byteExposurePolicy'));
        $t->same(array_column($parts, 'name'), $provenance['localHeaderOrder']['localHeaderOrderNames']);
        $t->same($centralOrder, $provenance['localHeaderOrder']['centralDirectoryOrderNames']);
        $t->same(true, $provenance['localHeaderOrder']['hasCentralDirectoryOrderMismatch']);
        $t->same($centralOrder, array_keys($inventory));
        $t->same(10, $provenance['entryCount']);
        $t->same(9, $provenance['manifestDeclaredPartCount']);
        $t->same(10, $provenance['manifestFileEntryCount']);
        $t->same(5, $provenance['corePackagePartCount']);
        $t->same(2, $provenance['mediaResourcePartCount']);
        $t->same(1, $provenance['rdfMetadataPartCount']);
        $t->same(0, $provenance['undeclaredEntryCount']);
        $t->same(['Pictures/declared.png', 'Pictures/missing.png', 'Pictures/unsupported.webp'], array_column($result['media'], 'part'));
        $t->same(['Pictures/missing.png'], array_column($result['importReport']['manifest']['missingItems'], 'part'));

        $t->same('package-bytes-exposable', $manifestByPart['Pictures/declared.png']['byteExposurePolicy']);
        $t->same(true, $mediaByPart['Pictures/declared.png']['canExposeBytes']);
        $t->same(strlen($declaredImage), $mediaByPart['Pictures/declared.png']['byteLength']);
        $t->same(hash('sha256', $declaredImage), $mediaByPart['Pictures/declared.png']['byteSha256']);
        $t->same('missing-package-part', $manifestByPart['Pictures/missing.png']['byteExposurePolicy']);
        $t->same(false, $mediaByPart['Pictures/missing.png']['exists']);
        $t->same(null, $mediaByPart['Pictures/missing.png']['byteLength']);
        $t->same(null, $mediaByPart['Pictures/missing.png']['byteSha256']);
        $t->same('unsupported-compression-bytes-blocked', $manifestByPart['Pictures/unsupported.webp']['byteExposurePolicy']);
        $t->same(false, $manifestByPart['Pictures/unsupported.webp']['canExposeBytes']);
        $t->same(null, $manifestByPart['Pictures/unsupported.webp']['byteLength']);
        $t->same(strlen($unsupportedImage), $manifestByPart['Pictures/unsupported.webp']['storedByteLength']);
        $t->same(12, $manifestByPart['Pictures/unsupported.webp']['compressionMethod']);
        $t->same('unsupported', $manifestByPart['Pictures/unsupported.webp']['compressionMethodName']);
        $t->same(null, $manifestByPart['Pictures/unsupported.webp']['byteSha256']);
        $t->same(false, $mediaByPart['Pictures/unsupported.webp']['canExposeBytes']);
        $t->same(null, $mediaByPart['Pictures/unsupported.webp']['crc32']);
        $t->same(sprintf('%08x', crc32($unsupportedImage)), $mediaByPart['Pictures/unsupported.webp']['storedCrc32']);
        $t->same(1, $compression['unsupportedCompressionMethodCount']);
        $t->same('Pictures/unsupported.webp', $compression['unsupportedEntries'][0]['name']);

        $t->same('script-package-bytes-blocked', $manifestByPart['Basic/Standard/Review.xml']['byteExposurePolicy']);
        $t->same(false, $manifestByPart['Basic/Standard/Review.xml']['canExposeBytes']);
        $t->same(null, $manifestByPart['Basic/Standard/Review.xml']['byteSha256']);
        $t->same('basic-module', $scriptByPart['Basic/Standard/Review.xml']['kind']);
        $t->same(true, $scriptByPart['Basic/Standard/Review.xml']['referenced']);
        $t->same(false, $scriptByPart['Basic/Standard/Review.xml']['canExposeBytes']);
        $t->same(null, $scriptByPart['Basic/Standard/Review.xml']['byteLength']);
        $t->same(strlen($basicModuleXml), $scriptByPart['Basic/Standard/Review.xml']['storedByteLength']);
        $t->same(['vnd.sun.star.script:Standard.Review.Approve?language=Basic&location=document'], $scriptByPart['Basic/Standard/Review.xml']['hrefs']);

        $t->same('rdf-metadata-bytes-blocked', $manifestByPart['manifest.rdf']['byteExposurePolicy']);
        $t->same(false, $manifestByPart['manifest.rdf']['canExposeBytes']);
        $t->same(null, $manifestByPart['manifest.rdf']['byteSha256']);
        $t->same('rdf-metadata-bytes-blocked', $rdfPart['byteExposurePolicy']);
        $t->same('rdf-metadata-only', $rdfPart['reviewPolicy']);
        $t->same(false, $rdfPart['canExposeBytes']);
        $t->same(null, $rdfPart['byteLength']);
        $t->same(strlen($rdfXml), $rdfPart['storedByteLength']);
        $t->same(1, $rdfPart['tripleCount']);

        $t->same(['zip-directory', 'manifest-declared', 'script-package'], $inventory['Basic/']['roles']);
        $t->same(['manifest-declared', 'script-package'], $inventory['Basic/Standard/Review.xml']['roles']);
        $t->same(['rdf-metadata', 'manifest-declared'], $inventory['manifest.rdf']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $inventory['Pictures/unsupported.webp']['roles']);
        $t->same('unsupported-compression-bytes-blocked', $inventory['Pictures/unsupported.webp']['byteExposurePolicy']);
        $t->same(null, $inventory['Pictures/unsupported.webp']['byteSha256']);
        $t->same(2, $provenance['roleCounts']['script-package']);
        $t->same(1, $provenance['roleCounts']['rdf-metadata']);
        $t->same(2, $provenance['roleCounts']['media-resource']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Macro <a href="https://example.test/review.odt"', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'Sub Approve'), 'Basic macro source bytes must stay out of WordPress output');
        $t->true(!str_contains($blocksHtml, 'WEBP-BYTES'), 'Unsupported-compression media bytes must stay out of WordPress output');
    },
    'maps ODT RDF metadata sidecars into package review metadata' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithRdf = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>'
            . '<manifest:file-entry manifest:full-path="metadata/invalid.rdf" manifest:media-type="application/rdf+xml"/>',
            $manifestXml
        );
        $rdfXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="https://example.test/ns/wp#"
  xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <rdf:Description rdf:about="content.xml">
    <dc:title xml:lang="en">Reviewed ODT source body</dc:title>
    <dc:creator rdf:resource="urn:uuid:reviewer-1"/>
    <wp:review-status>ready</wp:review-status>
  </rdf:Description>
  <rdf:Description rdf:about="Pictures/hero.png">
    <dc:format>image/png</dc:format>
  </rdf:Description>
</rdf:RDF>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithRdf, null, null, [
            ['name' => 'manifest.rdf', 'data' => $rdfXml],
            ['name' => 'metadata/invalid.rdf', 'data' => '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description'],
        ]));
        $rdf = $result['rdfMetadata'];

        $t->same(2, $rdf['partCount']);
        $t->same(1, $rdf['parsedPartCount']);
        $t->same(1, $rdf['parseErrorCount']);
        $t->same(4, $rdf['tripleCount']);
        $t->same(3, $rdf['literalCount']);
        $t->same(1, $rdf['resourceCount']);
        $t->same(2, $rdf['subjectCount']);
        $t->same($rdf, $result['document']->attr('rdfMetadata'));
        $t->same($rdf, $result['importReport']['rdfMetadata']);
        $t->same(7, $result['importReport']['manifest']['count']);
        $t->same(1, count($result['media']), 'RDF XML sidecars must stay out of media byte handoff');

        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPart[$item['part']] = $item;
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $parts = $provenance['parts'];
        $t->same(2, $provenance['rdfMetadataPartCount']);
        $t->same(2, $provenance['roleCounts']['rdf-metadata']);
        $t->same(true, $manifestByPart['manifest.rdf']['rdfMetadataPart']);
        $t->same(false, $manifestByPart['manifest.rdf']['canExposeBytes']);
        $t->same('rdf-metadata-bytes-blocked', $manifestByPart['manifest.rdf']['byteExposurePolicy']);
        $t->same(true, $parts['manifest.rdf']['rdfMetadataPart']);
        $t->same(false, $parts['manifest.rdf']['canExposeBytes']);
        $t->same('rdf-metadata-bytes-blocked', $parts['manifest.rdf']['byteExposurePolicy']);

        $validPart = $rdf['parts'][0];
        $invalidPart = $rdf['parts'][1];
        $t->same('manifest.rdf', $validPart['part']);
        $t->same('application/rdf+xml', $validPart['mediaType']);
        $t->same(true, $validPart['exists']);
        $t->same(true, $validPart['parseable']);
        $t->same(4, $validPart['tripleCount']);
        $t->same(2, $validPart['subjectCount']);
        $t->same('metadata/invalid.rdf', $invalidPart['part']);
        $t->same(false, $invalidPart['parseable']);
        $t->same('invalid-rdf-xml', $invalidPart['diagnostic']);

        $contentSubject = $rdf['subjectsBySubject']['content.xml'];
        $imageSubject = $rdf['subjectsBySubject']['Pictures/hero.png'];
        $t->same(3, $contentSubject['tripleCount']);
        $t->same(2, $contentSubject['literalCount']);
        $t->same(1, $contentSubject['resourceCount']);
        $t->same(['dc:creator', 'dc:title', 'wp:review-status'], $contentSubject['predicates']);
        $t->same(1, $imageSubject['tripleCount']);
        $t->same(['dc:format'], $imageSubject['predicates']);

        $triplesByPredicate = [];
        foreach ($validPart['triples'] as $triple) {
            $triplesByPredicate[$triple['subject'] . '|' . $triple['predicate']] = $triple;
        }

        $t->same('Reviewed ODT source body', $triplesByPredicate['content.xml|dc:title']['object']);
        $t->same('literal', $triplesByPredicate['content.xml|dc:title']['objectType']);
        $t->same('en', $triplesByPredicate['content.xml|dc:title']['language']);
        $t->same('urn:uuid:reviewer-1', $triplesByPredicate['content.xml|dc:creator']['object']);
        $t->same('resource', $triplesByPredicate['content.xml|dc:creator']['objectType']);
        $t->same('ready', $triplesByPredicate['content.xml|wp:review-status']['object']);
        $t->same('image/png', $triplesByPredicate['Pictures/hero.png|dc:format']['object']);
    },
    'discovers undeclared ODT manifest RDF sidecars by package path' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $rdfXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <rdf:Description rdf:about="meta.xml">
    <dc:title>Undeclared package metadata</dc:title>
    <dc:relation rdf:resource="Pictures/hero.png"/>
  </rdf:Description>
</rdf:RDF>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, null, null, null, [
            ['name' => 'manifest.rdf', 'data' => $rdfXml],
            ['name' => 'metadata/review.rdf', 'data' => $rdfXml],
        ]));
        $rdf = $result['rdfMetadata'];

        $t->same(1, $rdf['partCount']);
        $t->same(1, $rdf['parsedPartCount']);
        $t->same(0, $rdf['parseErrorCount']);
        $t->same(2, $rdf['tripleCount']);
        $t->same(1, $rdf['literalCount']);
        $t->same(1, $rdf['resourceCount']);
        $t->same(1, $rdf['subjectCount']);
        $t->same($rdf, $result['document']->attr('rdfMetadata'));
        $t->same($rdf, $result['importReport']['rdfMetadata']);
        $t->same(1, count($result['media']), 'undeclared RDF sidecars must stay out of media byte handoff');

        $part = $rdf['parts'][0];
        $t->same('manifest.rdf', $part['part']);
        $t->same(false, $part['declared']);
        $t->same('odf-rdf-package-undeclared-part', $part['diagnostic']);
        $t->same(null, $part['mediaType']);
        $t->same(true, $part['exists']);
        $t->same(true, $part['parseable']);
        $t->same(false, $part['canExposeBytes']);
        $t->same(null, $part['byteLength']);
        $t->same(strlen($rdfXml), $part['storedByteLength']);
        $t->same('rdf-metadata-bytes-blocked', $part['byteExposurePolicy']);
        $t->same('rdf-metadata-only', $part['reviewPolicy']);

        $subject = $rdf['subjectsBySubject']['meta.xml'];
        $t->same(2, $subject['tripleCount']);
        $t->same(['dc:relation', 'dc:title'], $subject['predicates']);

        $triplesByPredicate = [];
        foreach ($part['triples'] as $triple) {
            $triplesByPredicate[$triple['predicate']] = $triple;
        }
        $t->same('Undeclared package metadata', $triplesByPredicate['dc:title']['object']);
        $t->same('Pictures/hero.png', $triplesByPredicate['dc:relation']['object']);
        $t->same('resource', $triplesByPredicate['dc:relation']['objectType']);

        $undeclaredParts = array_column($result['importReport']['manifest']['undeclaredEntries'], 'part');
        $t->same(2, $result['importReport']['manifest']['undeclaredEntryCount']);
        $t->same(['manifest.rdf', 'metadata/review.rdf'], $undeclaredParts);
    },
    'maps ODT XML signature sidecars into package review metadata without validation' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithSignatures = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/macrosignatures.xml" manifest:media-type="text/xml"/>',
            $manifestXml
        );
        $signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
  <dsig:Signature Id="review-signature">
    <dsig:SignedInfo>
      <dsig:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <dsig:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <dsig:Reference URI="content.xml" Type="http://example.test/odf/content">
        <dsig:Transforms>
          <dsig:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
        </dsig:Transforms>
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>contentdigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Pictures/hero.png#manifest">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>picturedigest</dsig:DigestValue>
      </dsig:Reference>
    </dsig:SignedInfo>
    <dsig:SignatureValue>signature-bytes</dsig:SignatureValue>
    <dsig:KeyInfo/>
  </dsig:Signature>
</dsig:document-signatures>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSignatures, null, null, [
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml],
            ['name' => 'META-INF/macrosignatures.xml', 'data' => '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"><dsig:Signature'],
        ]));
        $signatures = $result['signatureMetadata'];
        $packageProvenance = $result['importReport']['manifest']['packageProvenance'];
        $packageParts = $packageProvenance['parts'];

        $t->same($signatures, $result['document']->attr('signatureMetadata'));
        $t->same($signatures, $result['importReport']['signatureMetadata']);
        $t->same(2, $signatures['partCount']);
        $t->same(1, $signatures['parsedPartCount']);
        $t->same(1, $signatures['parseErrorCount']);
        $t->same(1, $signatures['signatureCount']);
        $t->same(2, $signatures['referenceCount']);
        $t->same(['Pictures/hero.png', 'content.xml'], $signatures['signedParts']);
        $t->same(7, $result['importReport']['manifest']['count']);
        $t->same(1, count($result['media']), 'signature XML sidecars must stay out of media byte handoff');
        $t->same(2, $packageProvenance['packageSignaturePartCount']);
        $t->same(2, $packageProvenance['roleCounts']['package-signature']);
        $t->same(['package-signature', 'manifest-declared'], $packageParts['META-INF/documentsignatures.xml']['roles']);
        $t->same(['package-signature', 'manifest-declared'], $packageParts['META-INF/macrosignatures.xml']['roles']);

        $documentSignatures = $signatures['parts'][0];
        $macroSignatures = $signatures['parts'][1];
        $t->same('META-INF/documentsignatures.xml', $documentSignatures['part']);
        $t->same('text/xml', $documentSignatures['mediaType']);
        $t->same(true, $documentSignatures['exists']);
        $t->same(true, $documentSignatures['parseable']);
        $t->same(1, $documentSignatures['signatureCount']);
        $t->same(2, $documentSignatures['referenceCount']);
        $t->same('META-INF/macrosignatures.xml', $macroSignatures['part']);
        $t->same(false, $macroSignatures['parseable']);
        $t->same('invalid-signature-xml', $macroSignatures['diagnostic']);

        $signature = $documentSignatures['signatures'][0];
        $t->same('review-signature', $signature['id']);
        $t->same('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $signature['signatureMethod']);
        $t->same('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $signature['canonicalizationMethod']);
        $t->same(strlen('signature-bytes'), $signature['signatureValueLength']);
        $t->same(true, $signature['hasKeyInfo']);
        $t->same('content.xml', $signature['references'][0]['part']);
        $t->same('http://example.test/odf/content', $signature['references'][0]['type']);
        $t->same('http://www.w3.org/2001/04/xmlenc#sha256', $signature['references'][0]['digestMethod']);
        $t->same(strlen('contentdigest'), $signature['references'][0]['digestValueLength']);
        $t->same(['http://www.w3.org/2000/09/xmldsig#enveloped-signature'], $signature['references'][0]['transforms']);
        $t->same('Pictures/hero.png', $signature['references'][1]['part']);
    },
    'reports ODT package signature sidecars as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $documentSignatureBytes = '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>';
        $encryptedBytes = '<encrypted-signatures/>';
        $invalidBytes = 'PNG-SIGNATURE-SIDECAR';
        $orphanBytes = '<orphan-signatures/>';
        $encryptedEntry = <<<'XML'
  <manifest:file-entry manifest:full-path="META-INF/encrypted-signatures.xml" manifest:media-type="text/xml" manifest:size="21">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="signature-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="signature-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithSignatures = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml" manifest:size="' . strlen($documentSignatureBytes) . '"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/macrosignatures.xml" manifest:media-type="application/xml"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/packagesignatures.xml" manifest:media-type="image/png"/>'
            . $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSignatures, null, null, [
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $documentSignatureBytes, 'compressionMethod' => 0],
            ['name' => 'META-INF/encrypted-signatures.xml', 'data' => $encryptedBytes, 'compressionMethod' => 0],
            ['name' => 'META-INF/packagesignatures.xml', 'data' => $invalidBytes, 'compressionMethod' => 0],
            ['name' => 'META-INF/orphan-signatures.xml', 'data' => $orphanBytes, 'compressionMethod' => 0],
        ]));
        $signatures = $result['packageSignatures'];
        $itemsByPart = [];
        foreach ($signatures['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $inventory = $provenance['parts'];
        $mediaResources = $provenance['mediaResources'];

        $t->same($signatures, $result['document']->attr('packageSignatures'));
        $t->same($signatures, $result['metadata']['odfPackageSignatures']);
        $t->same($signatures, $result['importReport']['packageSignatures']);
        $t->same(5, $signatures['count']);
        $t->same(3, $signatures['readableCount']);
        $t->same(4, $signatures['declaredCount']);
        $t->same(1, $signatures['undeclaredCount']);
        $t->same(1, $signatures['missingCount']);
        $t->same(1, $signatures['encryptedCount']);
        $t->same(1, $signatures['invalidMediaTypeCount']);
        $t->same(4, $signatures['issueCount']);
        $t->same([
            'odf-signature-encrypted-package-part',
            'odf-signature-invalid-media-type',
            'odf-signature-missing-package-part',
            'odf-signature-undeclared-package-part',
        ], $signatures['issueCodes']);

        $declared = $itemsByPart['META-INF/documentsignatures.xml'];
        $t->same('text/xml', $declared['mediaType']);
        $t->same(['text/xml', 'application/xml'], $declared['expectedMediaTypes']);
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($documentSignatureBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($documentSignatureBytes)), $declared['crc32']);
        $t->same('signature-package-bytes-blocked', $declared['byteExposurePolicy']);
        $t->same('package-signature-metadata-only', $declared['reviewPolicy']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same([], $declared['issues']);

        $missing = $itemsByPart['META-INF/macrosignatures.xml'];
        $t->same(false, $missing['exists']);
        $t->same('application/xml', $missing['mediaType']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-signature-missing-package-part'], $missing['issues']);

        $encrypted = $itemsByPart['META-INF/encrypted-signatures.xml'];
        $t->same(true, $encrypted['exists']);
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(null, $encrypted['crc32']);
        $t->same(sprintf('%08x', crc32($encryptedBytes)), $encrypted['storedCrc32']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-signature-encrypted-package-part'], $encrypted['issues']);

        $invalid = $itemsByPart['META-INF/packagesignatures.xml'];
        $t->same('image/png', $invalid['mediaType']);
        $t->same('image/png', $invalid['mediaTypeBase']);
        $t->same(false, $invalid['valid']);
        $t->same(strlen($invalidBytes), $invalid['byteLength']);
        $t->same('signature-package-bytes-blocked', $invalid['byteExposurePolicy']);
        $t->same(['odf-signature-invalid-media-type'], $invalid['issues']);

        $orphan = $itemsByPart['META-INF/orphan-signatures.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(null, $orphan['mediaType']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same('signature-package-bytes-blocked', $orphan['byteExposurePolicy']);
        $t->same(['odf-signature-undeclared-package-part'], $orphan['issues']);

        $t->same(true, $manifestByPart['META-INF/documentsignatures.xml']['signaturePackagePart']);
        $t->same(false, $manifestByPart['META-INF/documentsignatures.xml']['canExposeBytes']);
        $t->same('signature-package-bytes-blocked', $manifestByPart['META-INF/documentsignatures.xml']['byteExposurePolicy']);
        $t->same(true, $manifestByPart['META-INF/packagesignatures.xml']['signaturePackagePart']);
        $t->same(false, $manifestByPart['META-INF/packagesignatures.xml']['canExposeBytes']);
        $t->same('signature-package-bytes-blocked', $manifestByPart['META-INF/packagesignatures.xml']['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(4, $provenance['packageSignaturePartCount']);
        $t->same(4, $provenance['roleCounts']['package-signature']);
        $t->same(1, $provenance['undeclaredRoleCounts']['package-signature']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['META-INF/documentsignatures.xml']['roles']);
        $t->same(['package-signature', 'manifest-declared'], $inventory['META-INF/packagesignatures.xml']['roles']);
        $t->same(['package-signature', 'undeclared-package-entry'], $inventory['META-INF/orphan-signatures.xml']['roles']);
        $t->same(false, $inventory['META-INF/documentsignatures.xml']['canExposeBytes']);
        $t->same('signature-package-bytes-blocked', $inventory['META-INF/documentsignatures.xml']['byteExposurePolicy']);
        $t->same(1, $result['importReport']['manifest']['undeclaredEntryCount']);
        $t->same('META-INF/orphan-signatures.xml', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);
        $t->same('signature-package-bytes-blocked', $result['importReport']['manifest']['undeclaredEntries'][0]['byteExposurePolicy']);
        $t->same(1, $mediaResources['packageRolePrecedenceCount']);
        $t->same(['package-signature'], $mediaResources['packageRolePrecedenceItems'][0]['packageRolePrecedence']);
    },
    'maps ODT XML signature reference target package diagnostics' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml): void {
        $encryptedBytes = 'ENCRYPTEDPNG';
        $thumbnailBytes = 'THUMBNAIL';
        $signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
  <dsig:Signature Id="target-signature">
    <dsig:SignedInfo>
      <dsig:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <dsig:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <dsig:Reference URI="content.xml#body" Id="content-ref">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>contentdigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Pictures/missing.png">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>missingdigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Pictures/encrypted.png">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>encrypteddigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Thumbnails/thumbnail.png">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>thumbdigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="http://example.test/archive.bin">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>externaldigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="../secret.xml">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>unsafedigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="#signature-object">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>fragmentdigest</dsig:DigestValue>
      </dsig:Reference>
    </dsig:SignedInfo>
    <dsig:SignatureValue>signature-bytes</dsig:SignatureValue>
  </dsig:Signature>
</dsig:document-signatures>
XML;
        $encryptedManifestEntry = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/encrypted.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="encrypted-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="encrypted-iv"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithSignatureTargets = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>'
            . $encryptedManifestEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSignatureTargets, null, null, [
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml],
            ['name' => 'Pictures/encrypted.png', 'data' => $encryptedBytes],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes],
        ]));
        $signatures = $result['signatureMetadata'];
        $part = $signatures['parts'][0];
        $references = $part['signatures'][0]['references'];
        $referencesByUri = [];
        foreach ($references as $reference) {
            $referencesByUri[$reference['uri']] = $reference;
        }

        $t->same($signatures, $result['document']->attr('signatureMetadata'));
        $t->same($signatures, $result['importReport']['signatureMetadata']);
        $t->same(1, $signatures['signatureCount']);
        $t->same(7, $signatures['referenceCount']);
        $t->same(4, $signatures['packagePartReferenceCount']);
        $t->same(1, $signatures['sameDocumentReferenceCount']);
        $t->same(1, $signatures['externalReferenceCount']);
        $t->same(1, $signatures['unsafeReferenceCount']);
        $t->same(1, $signatures['missingPartReferenceCount']);
        $t->same(1, $signatures['undeclaredPartReferenceCount']);
        $t->same(1, $signatures['encryptedPartReferenceCount']);
        $t->same(4, $signatures['signedPartCount']);
        $t->same(['Pictures/encrypted.png', 'Pictures/missing.png', 'Thumbnails/thumbnail.png', 'content.xml'], $signatures['signedParts']);
        $t->same(4, $part['packagePartReferenceCount']);
        $t->same(1, $part['sameDocumentReferenceCount']);
        $t->same(1, $part['externalReferenceCount']);
        $t->same(1, $part['unsafeReferenceCount']);

        $content = $referencesByUri['content.xml#body'];
        $t->same('package-part', $content['uriKind']);
        $t->same('content.xml', $content['part']);
        $t->same(true, $content['targetExists']);
        $t->same(true, $content['targetDeclaredInManifest']);
        $t->same('content.xml', $content['targetManifestFullPath']);
        $t->same('text/xml', $content['targetMediaType']);
        $t->same(strlen($contentXml), $content['targetStoredByteLength']);
        $t->same(sprintf('%08x', crc32($contentXml)), $content['targetStoredCrc32']);

        $missing = $referencesByUri['Pictures/missing.png'];
        $t->same('package-part', $missing['uriKind']);
        $t->same(false, $missing['targetExists']);
        $t->same(true, $missing['targetDeclaredInManifest']);
        $t->same(['odf-signature-reference-missing-package-part'], $missing['diagnostics']);

        $encrypted = $referencesByUri['Pictures/encrypted.png'];
        $t->same(true, $encrypted['targetExists']);
        $t->same(true, $encrypted['targetDeclaredInManifest']);
        $t->same(true, $encrypted['targetEncrypted']);
        $t->same(false, $encrypted['targetCanExposeBytes']);
        $t->same(strlen($encryptedBytes), $encrypted['targetStoredByteLength']);
        $t->same(['odf-signature-reference-encrypted-package-part'], $encrypted['diagnostics']);

        $thumbnail = $referencesByUri['Thumbnails/thumbnail.png'];
        $t->same(true, $thumbnail['targetExists']);
        $t->same(false, $thumbnail['targetDeclaredInManifest']);
        $t->same(strlen($thumbnailBytes), $thumbnail['targetStoredByteLength']);
        $t->same(['odf-signature-reference-undeclared-package-part'], $thumbnail['diagnostics']);

        $external = $referencesByUri['http://example.test/archive.bin'];
        $t->same('external-uri', $external['uriKind']);
        $t->same(['odf-signature-reference-external-uri'], $external['diagnostics']);

        $unsafe = $referencesByUri['../secret.xml'];
        $t->same('unsafe-package-path', $unsafe['uriKind']);
        $t->same(['odf-signature-reference-unsafe-package-path'], $unsafe['diagnostics']);

        $fragment = $referencesByUri['#signature-object'];
        $t->same('same-document-fragment', $fragment['uriKind']);
        $t->same('signature-object', $fragment['fragment']);
    },
    'preserves ODT XML signature reference target suffix provenance' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml): void {
        $signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
  <dsig:Signature Id="suffix-signature">
    <dsig:SignedInfo>
      <dsig:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <dsig:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <dsig:Reference URI="content.xml?view=body#signature-body">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>contentdigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Pictures/hero.png#image-ref">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>herodigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Pictures/missing.png?missing=true">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>missingdigest</dsig:DigestValue>
      </dsig:Reference>
    </dsig:SignedInfo>
    <dsig:SignatureValue>signature-bytes</dsig:SignatureValue>
  </dsig:Signature>
</dsig:document-signatures>
XML;
        $manifestWithSignatureTargets = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithSignatureTargets, null, null, [
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml],
        ]));
        $signatures = $result['signatureMetadata'];
        $part = $signatures['parts'][0];
        $references = $part['signatures'][0]['references'];
        $referencesByUri = [];
        foreach ($references as $reference) {
            $referencesByUri[$reference['uri']] = $reference;
        }

        $t->same($signatures, $result['document']->attr('signatureMetadata'));
        $t->same($signatures, $result['importReport']['signatureMetadata']);
        $t->same(3, $signatures['packagePartReferenceCount']);
        $t->same(3, $signatures['packagePartReferenceSuffixCount']);
        $t->same(2, $signatures['packagePartReferenceQueryCount']);
        $t->same(2, $signatures['packagePartReferenceFragmentCount']);
        $t->same(1, $signatures['missingPartReferenceCount']);
        $t->same(['Pictures/hero.png', 'Pictures/missing.png', 'content.xml'], $signatures['signedParts']);
        $t->same(3, $part['packagePartReferenceSuffixCount']);
        $t->same(2, $part['packagePartReferenceQueryCount']);
        $t->same(2, $part['packagePartReferenceFragmentCount']);

        $content = $referencesByUri['content.xml?view=body#signature-body'];
        $t->same('package-part', $content['uriKind']);
        $t->same('content.xml', $content['part']);
        $t->same('content.xml', $content['partReference']);
        $t->same('?view=body#signature-body', $content['partSuffix']);
        $t->same('view=body', $content['partQuery']);
        $t->same('signature-body', $content['partFragment']);
        $t->same(true, $content['targetExists']);
        $t->same('content.xml', $content['targetManifestFullPath']);
        $t->same(strlen($contentXml), $content['targetStoredByteLength']);

        $hero = $referencesByUri['Pictures/hero.png#image-ref'];
        $t->same('Pictures/hero.png', $hero['part']);
        $t->same('Pictures/hero.png', $hero['partReference']);
        $t->same('#image-ref', $hero['partSuffix']);
        $t->same(false, array_key_exists('partQuery', $hero));
        $t->same('image-ref', $hero['partFragment']);
        $t->same(true, $hero['targetExists']);
        $t->same('image/png', $hero['targetMediaType']);

        $missing = $referencesByUri['Pictures/missing.png?missing=true'];
        $t->same('Pictures/missing.png', $missing['part']);
        $t->same('Pictures/missing.png', $missing['partReference']);
        $t->same('?missing=true', $missing['partSuffix']);
        $t->same('missing=true', $missing['partQuery']);
        $t->same(false, array_key_exists('partFragment', $missing));
        $t->same(false, $missing['targetExists']);
        $t->same(true, $missing['targetDeclaredInManifest']);
        $t->same(['odf-signature-reference-missing-package-part'], $missing['diagnostics']);
    },
    'preserves ODT manifest file-entry order provenance for package review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithReorderedEntries = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml" manifest:version="1.2"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:version="1.1" manifest:preferred-view-mode="thumbnail"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:version="1.2" manifest:preferred-view-mode="page-preview"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithReorderedEntries));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $manifestSummary = $result['importReport']['manifest']['mediaTypeSummary'];
        $manifestOrder = $provenance['manifestFileEntryOrder'];
        $inventory = $provenance['parts'];
        $summaryByType = [];
        foreach ($manifestSummary['items'] as $item) {
            $summaryByType[$item['mediaType']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same($manifestSummary, $result['document']->attr('manifest')['mediaTypeSummary']);
        $t->same(6, $provenance['manifestFileEntryCount']);
        $t->same([0, 1, 2, 3, 4, 5], array_column($result['manifest'], 'manifestIndex'));
        $t->same(['/', 'styles.xml', 'Pictures/hero.png', 'content.xml', 'Pictures/missing.png', 'meta.xml'], array_column($manifestOrder, 'fullPath'));
        $t->same([0, 1, 2, 3, 4, 5], array_column($manifestOrder, 'manifestIndex'));
        $t->same(['1.3', '1.2', '1.1', '1.2', null, null], array_column($manifestOrder, 'version'));
        $t->same(['edit', null, 'thumbnail', 'page-preview', null, null], array_column($manifestOrder, 'preferredViewMode'));
        $t->same(4, $manifestSummary['versionedItemCount']);
        $t->same(['1.3', '1.2', '1.1'], $manifestSummary['manifestVersions']);
        $t->same(['/', 'styles.xml', 'Pictures/hero.png', 'content.xml'], array_column($manifestSummary['versionedItems'], 'fullPath'));
        $t->same(3, $manifestSummary['preferredViewModeCount']);
        $t->same(['edit', 'thumbnail', 'page-preview'], $manifestSummary['preferredViewModes']);
        $t->same(['/', 'Pictures/hero.png', 'content.xml'], array_column($manifestSummary['preferredViewModeItems'], 'fullPath'));
        $t->same(1, $summaryByType[OdfReader::MIMETYPE]['versionedItemCount']);
        $t->same(['1.3'], $summaryByType[OdfReader::MIMETYPE]['manifestVersions']);
        $t->same(1, $summaryByType[OdfReader::MIMETYPE]['preferredViewModeCount']);
        $t->same(['edit'], $summaryByType[OdfReader::MIMETYPE]['preferredViewModes']);
        $t->same(2, $summaryByType['text/xml']['versionedItemCount']);
        $t->same(['1.2'], $summaryByType['text/xml']['manifestVersions']);
        $t->same(1, $summaryByType['text/xml']['preferredViewModeCount']);
        $t->same(['page-preview'], $summaryByType['text/xml']['preferredViewModes']);
        $t->same(1, $summaryByType['image/png']['versionedItemCount']);
        $t->same(['1.1'], $summaryByType['image/png']['manifestVersions']);
        $t->same(1, $summaryByType['image/png']['preferredViewModeCount']);
        $t->same(['thumbnail'], $summaryByType['image/png']['preferredViewModes']);

        $t->same(1, $manifestByPath['styles.xml']['manifestIndex']);
        $t->same(2, $manifestByPath['Pictures/hero.png']['manifestIndex']);
        $t->same(3, $manifestByPath['content.xml']['manifestIndex']);
        $t->same(4, $manifestByPath['Pictures/missing.png']['manifestIndex']);
        $t->same(5, $manifestByPath['meta.xml']['manifestIndex']);

        $t->same(3, $inventory['content.xml']['manifestIndex']);
        $t->same(1, $inventory['styles.xml']['manifestIndex']);
        $t->same(2, $inventory['Pictures/hero.png']['manifestIndex']);
        $t->same(5, $inventory['meta.xml']['manifestIndex']);
        $t->same('1.2', $inventory['content.xml']['manifestVersion']);
        $t->same('page-preview', $inventory['content.xml']['manifestPreferredViewMode']);
        $t->same('1.1', $inventory['Pictures/hero.png']['manifestVersion']);
        $t->same('thumbnail', $inventory['Pictures/hero.png']['manifestPreferredViewMode']);
        $t->same(false, $manifestOrder[4]['exists']);
        $t->same(false, $manifestOrder[4]['canExposeBytes']);
        $t->same(['Pictures/missing.png'], array_column($result['importReport']['manifest']['missingItems'], 'part'));
        $t->same(4, $result['importReport']['manifest']['missingItems'][0]['manifestIndex']);
        $t->same(2, count($result['media']), 'missing manifest media remains metadata-only in declared media handoff');
        $t->same(false, $result['media'][1]['exists']);
        $t->same(null, $result['media'][1]['byteLength']);
    },
    'summarizes ODT manifest preferred view mode applicability and token diagnostics' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithPreferredViewModes = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="read-only"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="presentation-slide-show"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="wp:review"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:preferred-view-mode="thumbnail"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:preferred-view-mode="EDIT"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:preferred-view-mode="view"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="wp:object"/>
</manifest:manifest>
XML;

        $package = $buildOdtPackage(null, $manifestWithPreferredViewModes);
        $result = (new OdfReader())->readPackage($package);
        $summary = $result['importReport']['manifest']['packageProvenance']['preferredViewModes'];
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize()['manifestReview']['preferredViewModes'];

        $t->same($summary, $result['document']->attr('manifest')['packageProvenance']['preferredViewModes']);
        $t->same(8, $summary['count']);
        $t->same('edit', $summary['rootMode']);
        $t->same(3, $summary['definedModeCount']);
        $t->same(2, $summary['namespacedTokenCount']);
        $t->same(3, $summary['invalidTokenCount']);
        $t->same(7, $summary['nonRootEntryCount']);
        $t->same(7, $summary['issueCount']);
        $t->same([
            'odf-preferred-view-mode-invalid-token' => 3,
            'odf-preferred-view-mode-non-root-entry' => 7,
        ], $summary['issueCodeCounts']);
        $t->same([
            'EDIT' => 1,
            'edit' => 1,
            'presentation-slide-show' => 1,
            'read-only' => 1,
            'thumbnail' => 1,
            'view' => 1,
            'wp:object' => 1,
            'wp:review' => 1,
        ], $summary['modeCounts']);
        $t->same([
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Pictures/missing.png',
            'Object%20Chart/',
            'Object%20Chart/content.xml',
        ], array_column($summary['nonRootItems'], 'fullPath'));
        $t->same([
            'Pictures/hero.png',
            'Pictures/missing.png',
            'Object%20Chart/',
        ], array_column($summary['invalidTokenItems'], 'fullPath'));

        $itemsByPath = [];
        foreach ($summary['items'] as $item) {
            $itemsByPath[$item['fullPath']] = $item;
        }
        $t->same(true, $itemsByPath['/']['applicableToRootEntry']);
        $t->same(true, $itemsByPath['/']['validToken']);
        $t->same('defined', $itemsByPath['/']['modeFamily']);
        $t->same([], $itemsByPath['/']['issues'] ?? []);
        $t->same(true, $itemsByPath['meta.xml']['namespacedToken']);
        $t->same('namespaced-token', $itemsByPath['meta.xml']['modeFamily']);
        $t->same(['odf-preferred-view-mode-non-root-entry'], $itemsByPath['meta.xml']['issues']);
        $t->same(false, $itemsByPath['Pictures/hero.png']['validToken']);
        $t->same('invalid-token', $itemsByPath['Pictures/hero.png']['modeFamily']);
        $t->same([
            'odf-preferred-view-mode-non-root-entry',
            'odf-preferred-view-mode-invalid-token',
        ], $itemsByPath['Pictures/hero.png']['issues']);
        $t->same('Object Chart/', $itemsByPath['Object%20Chart/']['part']);
        $t->same('Object Chart/content.xml', $itemsByPath['Object%20Chart/content.xml']['part']);

        $t->same($summary['count'], $compactSummary['count']);
        $t->same($summary['issueCodeCounts'], $compactSummary['issueCodeCounts']);
        $t->same($summary['modeCounts'], $compactSummary['modeCounts']);
        $t->same('Object Chart/', $compactSummary['items'][6]['packagePath']);
        $t->same('Object Chart/content.xml', $compactSummary['items'][7]['packagePath']);
    },
    'preserves ODT manifest custom file-entry attributes in package review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithCustomAttributes = <<<'XML'
<manifest:manifest
  xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"
  xmlns:loext="urn:libreoffice:names:experimental:office:xmlns:loext:1.0"
  xmlns:wp="urn:wordpress:review"
  manifest:version="1.3"
  loext:generator="LibreOffice 24.2"
  wp:review-source="migration-queue"
  xml:lang="en-US">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" loext:checksum="sha256-content" wp:review-priority="high"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="7" loext:media-type-hint="review-cover" wp:empty-note="" xml:lang="en-US"/>
</manifest:manifest>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithCustomAttributes));
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }

        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $manifestReport = $result['importReport']['manifest'];
        $documentManifest = $result['document']->attr('manifest');
        $order = $provenance['manifestFileEntryOrder'];
        $inventory = $provenance['parts'];
        $content = $manifestByPart['content.xml'];
        $hero = $manifestByPart['Pictures/hero.png'];
        $rootAttributes = [];
        foreach ($manifestReport['rootAttributes'] as $attribute) {
            $rootAttributes[$attribute['name']] = $attribute;
        }
        $contentAttributes = [];
        foreach ($content['manifestAttributes'] as $attribute) {
            $contentAttributes[$attribute['name']] = $attribute;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(4, $manifestReport['rootAttributeCount']);
        $t->same(['loext:generator', 'manifest:version', 'wp:review-source', 'xml:lang'], $manifestReport['rootAttributeNames']);
        $t->same(true, $rootAttributes['manifest:version']['structural']);
        $t->same(false, $rootAttributes['loext:generator']['structural']);
        $t->same('urn:libreoffice:names:experimental:office:xmlns:loext:1.0', $rootAttributes['loext:generator']['namespaceUri']);
        $t->same('LibreOffice 24.2', $rootAttributes['loext:generator']['value']);
        $t->same(3, $manifestReport['rootCustomAttributeCount']);
        $t->same(['loext:generator', 'wp:review-source', 'xml:lang'], $manifestReport['rootCustomAttributeNames']);
        $t->same([
            'loext:generator' => 'LibreOffice 24.2',
            'wp:review-source' => 'migration-queue',
            'xml:lang' => 'en-US',
        ], $manifestReport['rootCustomAttributeMap']);
        $t->same($manifestReport['rootAttributes'], $documentManifest['rootAttributes']);
        $t->same($manifestReport['rootCustomAttributeMap'], $documentManifest['rootCustomAttributeMap']);
        $t->same(4, $provenance['manifestRootAttributeCount']);
        $t->same(['loext:generator', 'wp:review-source', 'xml:lang'], $provenance['manifestRootCustomAttributeNames']);
        $t->same('migration-queue', $provenance['manifestRootCustomAttributeMap']['wp:review-source']);
        $t->same(4, $content['manifestAttributeCount']);
        $t->same(['loext:checksum', 'manifest:full-path', 'manifest:media-type', 'wp:review-priority'], $content['manifestAttributeNames']);
        $t->same(true, $contentAttributes['manifest:full-path']['structural']);
        $t->same(false, $contentAttributes['loext:checksum']['structural']);
        $t->same('urn:libreoffice:names:experimental:office:xmlns:loext:1.0', $contentAttributes['loext:checksum']['namespaceUri']);
        $t->same('sha256-content', $contentAttributes['loext:checksum']['value']);
        $t->same(2, $content['customManifestAttributeCount']);
        $t->same(['loext:checksum', 'wp:review-priority'], $content['customManifestAttributeNames']);
        $t->same([
            'loext:checksum' => 'sha256-content',
            'wp:review-priority' => 'high',
        ], $content['customManifestAttributeMap']);

        $t->same(6, $hero['manifestAttributeCount']);
        $t->same(['loext:media-type-hint', 'wp:empty-note', 'xml:lang'], $hero['customManifestAttributeNames']);
        $t->same('review-cover', $hero['customManifestAttributeMap']['loext:media-type-hint']);
        $t->same('', $hero['customManifestAttributeMap']['wp:empty-note']);
        $t->same('en-US', $hero['customManifestAttributeMap']['xml:lang']);

        $t->same(2, $provenance['manifestCustomAttributeEntryCount']);
        $t->same(5, $provenance['manifestCustomAttributeCount']);
        $t->same(['loext:checksum', 'loext:media-type-hint', 'wp:empty-note', 'wp:review-priority', 'xml:lang'], $provenance['manifestCustomAttributeNames']);
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($provenance['manifestCustomAttributeItems'], 'part'));
        $t->same(['loext:checksum', 'wp:review-priority'], $order[1]['customManifestAttributeNames']);
        $t->same(['loext:media-type-hint', 'wp:empty-note', 'xml:lang'], $order[4]['customManifestAttributeNames']);
        $t->same(2, $inventory['content.xml']['customManifestAttributeCount']);
        $t->same('sha256-content', $inventory['content.xml']['customManifestAttributeMap']['loext:checksum']);
        $t->same(3, $inventory['Pictures/hero.png']['customManifestAttributeCount']);
        $t->same('en-US', $inventory['Pictures/hero.png']['customManifestAttributeMap']['xml:lang']);
    },
    'preserves ODT manifest file-entry child element provenance in package review' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithChildElements = <<<'XML'
<manifest:manifest
  xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"
  xmlns:loext="urn:libreoffice:manifest"
  xmlns:wp="urn:wordpress:review"
  manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"><wp:review-hint wp:state="manual"><wp:nested/></wp:review-hint></manifest:file-entry>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="7"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="hero-checksum"/><loext:media-policy loext:role="review"/></manifest:file-entry>
</manifest:manifest>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithChildElements));
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $documentProvenance = $result['document']->attr('manifest')['packageProvenance'];
        $order = $provenance['manifestFileEntryOrder'];
        $inventory = $provenance['parts'];
        $identityByPath = [];
        foreach ($provenance['packageIdentity']['manifestEntries'] as $item) {
            $identityByPath[$item['fullPath']] = $item;
        }
        $content = $manifestByPart['content.xml'];
        $hero = $manifestByPart['Pictures/hero.png'];

        $t->same($provenance, $documentProvenance);
        $t->same(1, $content['manifestChildElementCount']);
        $t->same(['wp:review-hint'], $content['manifestChildElementNames']);
        $t->same(1, $content['customManifestChildElementCount']);
        $t->same(['wp:review-hint'], $content['customManifestChildElementNames']);
        $t->same('urn:wordpress:review', $content['customManifestChildElements'][0]['namespaceUri']);
        $t->same('wp', $content['customManifestChildElements'][0]['prefix']);
        $t->same(1, $content['customManifestChildElements'][0]['attributeCount']);
        $t->same(1, $content['customManifestChildElements'][0]['childElementCount']);
        $t->same(3, $content['customManifestChildElements'][0]['namespaceDeclarationCount']);
        $t->same(['xmlns:loext', 'xmlns:manifest', 'xmlns:wp'], $content['customManifestChildElements'][0]['namespaceDeclarationNames']);
        $t->same('urn:wordpress:review', $content['customManifestChildElements'][0]['namespaceDeclarationMap']['xmlns:wp']);
        $t->same('urn:libreoffice:manifest', $content['customManifestChildElements'][0]['namespaceDeclarationMap']['xmlns:loext']);

        $t->same(2, $hero['manifestChildElementCount']);
        $t->same(['manifest:encryption-data', 'loext:media-policy'], $hero['manifestChildElementNames']);
        $t->same(true, $hero['manifestChildElements'][0]['structural']);
        $t->same(false, $hero['manifestChildElements'][1]['structural']);
        $t->same(1, $hero['customManifestChildElementCount']);
        $t->same(['loext:media-policy'], $hero['customManifestChildElementNames']);
        $t->same('media-policy', $hero['customManifestChildElements'][0]['localName']);
        $t->same(3, $hero['customManifestChildElements'][0]['namespaceDeclarationCount']);
        $t->same('urn:libreoffice:manifest', $hero['customManifestChildElements'][0]['namespaceDeclarationMap']['xmlns:loext']);
        $t->same('urn:wordpress:review', $hero['customManifestChildElements'][0]['namespaceDeclarationMap']['xmlns:wp']);

        $t->same(2, $provenance['manifestCustomChildElementEntryCount']);
        $t->same(2, $provenance['manifestCustomChildElementCount']);
        $t->same(['loext:media-policy', 'wp:review-hint'], $provenance['manifestCustomChildElementNames']);
        $t->same(['content.xml', 'Pictures/hero.png'], array_column($provenance['manifestCustomChildElementItems'], 'part'));
        $t->same(['wp:review-hint'], $order[1]['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $order[4]['customManifestChildElementNames']);
        $t->same(['wp:review-hint'], $inventory['content.xml']['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $inventory['Pictures/hero.png']['customManifestChildElementNames']);
        $t->same(['wp:review-hint'], $identityByPath['content.xml']['customManifestChildElementNames']);
        $t->same(['loext:media-policy'], $identityByPath['Pictures/hero.png']['customManifestChildElementNames']);
        $t->same($content['customManifestChildElements'][0]['namespaceDeclarationMap'], $order[1]['customManifestChildElements'][0]['namespaceDeclarationMap']);
        $t->same($hero['customManifestChildElements'][0]['namespaceDeclarationMap'], $order[4]['customManifestChildElements'][0]['namespaceDeclarationMap']);
        $t->same($content['customManifestChildElements'][0]['namespaceDeclarationMap'], $inventory['content.xml']['customManifestChildElements'][0]['namespaceDeclarationMap']);
        $t->same($hero['customManifestChildElements'][0]['namespaceDeclarationMap'], $inventory['Pictures/hero.png']['customManifestChildElements'][0]['namespaceDeclarationMap']);
        $t->same($content['customManifestChildElements'][0]['namespaceDeclarationMap'], $identityByPath['content.xml']['customManifestChildElements'][0]['namespaceDeclarationMap']);
        $t->same($hero['customManifestChildElements'][0]['namespaceDeclarationMap'], $identityByPath['Pictures/hero.png']['customManifestChildElements'][0]['namespaceDeclarationMap']);
    },
    'summarizes ODT XML package part office versions for provenance review' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithSettings = str_replace(
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>',
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>',
            $manifestXml
        );
        $contentWithVersion = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body><office:text><text:p>Versioned content packet.</text:p></office:text></office:body>
</office:document-content>
XML;
        $stylesWithVersionMismatch = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.2">
  <office:styles/>
</office:document-styles>
XML;
        $metaWithoutVersion = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;
        $settingsWithVersionMismatch = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.4">
  <office:settings/>
</office:document-settings>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithVersion,
            $manifestWithSettings,
            $stylesWithVersionMismatch,
            $metaWithoutVersion,
            [['name' => 'settings.xml', 'data' => $settingsWithVersionMismatch, 'compressionMethod' => 0]]
        ));
        $versionReport = $result['importReport']['manifest']['documentPartVersions'];
        $versionsByPart = [];
        foreach ($versionReport['items'] as $item) {
            $versionsByPart[$item['part']] = $item;
        }

        $t->same($versionReport, $result['document']->attr('manifest')['documentPartVersions']);
        $t->same($versionReport, $result['documentPartVersions']);
        $t->same('1.3', $versionReport['manifestVersion']);
        $t->same(4, $versionReport['count']);
        $t->same(3, $versionReport['versionedCount']);
        $t->same(1, $versionReport['missingVersionCount']);
        $t->same(['meta.xml'], $versionReport['missingVersionParts']);
        $t->same(2, $versionReport['versionMismatchCount']);
        $t->same([
            ['part' => 'styles.xml', 'officeVersion' => '1.2', 'manifestVersion' => '1.3'],
            ['part' => 'settings.xml', 'officeVersion' => '1.4', 'manifestVersion' => '1.3'],
        ], $versionReport['versionMismatches']);
        $t->same(['1.2' => 1, '1.3' => 1, '1.4' => 1], $versionReport['versionCounts']);

        $t->same('document-content', $versionsByPart['content.xml']['rootName']);
        $t->same('1.3', $versionsByPart['content.xml']['officeVersion']);
        $t->same([], $versionsByPart['content.xml']['diagnostics']);
        $t->same(true, $versionsByPart['content.xml']['validRoot']);
        $t->same('text/xml', $versionsByPart['content.xml']['manifestMediaType']);

        $t->same('1.2', $versionsByPart['styles.xml']['officeVersion']);
        $t->same(['odf-xml-part-version-mismatch'], $versionsByPart['styles.xml']['diagnostics']);
        $t->same(null, $versionsByPart['meta.xml']['officeVersion']);
        $t->same(['odf-xml-part-missing-office-version'], $versionsByPart['meta.xml']['diagnostics']);
        $t->same('1.4', $versionsByPart['settings.xml']['officeVersion']);
        $t->same('settings.xml', $versionsByPart['settings.xml']['manifestFullPath']);
        $t->same(sprintf('%08x', crc32($settingsWithVersionMismatch)), $versionsByPart['settings.xml']['crc32']);
        $t->same(['odf-xml-part-version-mismatch'], $versionsByPart['settings.xml']['diagnostics']);
    },
    'reports ODT package ZIP order and part role provenance' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMBNAIL', 'compressionMethod' => 0],
        ];
        $centralOrder = [
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Thumbnails/thumbnail.png',
            'Pictures/',
            'mimetype',
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, $centralOrder));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $documentProvenance = $result['document']->attr('manifest')['packageProvenance'];
        $inventory = $provenance['parts'];
        $order = $provenance['localHeaderOrder'];
        $compression = $provenance['compressionMethods'];

        $t->same($provenance, $documentProvenance);
        $t->same(8, $provenance['entryCount']);
        $t->same(4, $provenance['manifestDeclaredPartCount']);
        $t->same(1, $provenance['undeclaredEntryCount']);
        $t->same(1, $provenance['packageDirectoryCount']);
        $t->same(5, $provenance['corePackagePartCount']);
        $t->same(1, $provenance['mediaResourcePartCount']);
        $t->same(1, $provenance['packageThumbnailPartCount']);
        $t->same(0, $provenance['packageSignaturePartCount']);
        $t->same([
            'manifest-declared' => 4,
            'media-resource' => 1,
            'odf-content' => 1,
            'odf-manifest' => 1,
            'odf-meta' => 1,
            'odf-mimetype' => 1,
            'odf-styles' => 1,
            'package-thumbnail' => 1,
            'undeclared-package-entry' => 1,
            'zip-directory' => 1,
        ], $provenance['roleCounts']);
        $t->same([
            'package-thumbnail' => 1,
            'undeclared-package-entry' => 1,
        ], $provenance['undeclaredRoleCounts']);
        $t->same(false, $provenance['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same('mimetype', $provenance['mimetypeEntry']['firstLocalEntryName']);
        $t->same(true, $provenance['mimetypeEntry']['isValid']);

        $t->same($centralOrder, $order['centralDirectoryOrderNames']);
        $t->same(array_column($parts, 'name'), $order['localHeaderOrderNames']);
        $t->same(true, $order['hasCentralDirectoryOrderMismatch']);
        $t->same(8, $order['mismatchedEntryCount']);
        $t->same('META-INF/manifest.xml', $order['mismatchedEntries'][0]['name']);
        $t->same('mimetype', $order['mismatchedEntries'][0]['localHeaderNameAtCentralDirectoryIndex']);
        $t->same(0, $inventory['mimetype']['localHeaderOrder']);
        $t->same(7, $inventory['mimetype']['centralDirectoryIndex']);
        $t->same(false, $inventory['mimetype']['matchesCentralDirectoryOrder']);

        $t->same(8, $compression['entryCount']);
        $t->same(4, $compression['storedEntryCount']);
        $t->same(4, $compression['deflatedEntryCount']);
        $t->same(0, $compression['unsupportedCompressionMethodCount']);

        $t->same(['odf-mimetype'], $inventory['mimetype']['roles']);
        $t->same(['odf-manifest'], $inventory['META-INF/manifest.xml']['roles']);
        $t->same(['odf-content', 'manifest-declared'], $inventory['content.xml']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $inventory['Pictures/hero.png']['roles']);
        $t->same(['zip-directory'], $inventory['Pictures/']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $inventory['Thumbnails/thumbnail.png']['roles']);
        $t->same(true, $inventory['content.xml']['declaredInManifest']);
        $t->same('content.xml', $inventory['content.xml']['manifestFullPath']);
        $t->same('text/xml', $inventory['content.xml']['manifestMediaType']);
        $t->same(false, $inventory['Thumbnails/thumbnail.png']['declaredInManifest']);
        $t->same(true, $inventory['Thumbnails/thumbnail.png']['undeclared']);
        $t->same(sprintf('%08x', crc32('THUMBNAIL')), $inventory['Thumbnails/thumbnail.png']['crc32']);
    },
    'summarizes ODT package inventory role byte buckets for review handoff' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $reviewImage = 'REVIEWPNG';
        $scriptXml = '<script:module xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"/>';
        $unsupportedImage = 'UNSUPPORTED-WEBP';
        $thumbnail = 'THUMBNAIL';
        $manifestWithBuckets = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/unsupported.webp" manifest:media-type="image/webp"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestWithBuckets, 'compressionMethod' => 8],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 8],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/review.png', 'data' => $reviewImage, 'compressionMethod' => 0],
            ['name' => 'Basic/Standard/Module1.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/unsupported.webp', 'data' => $unsupportedImage, 'compressionMethod' => 12],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnail, 'compressionMethod' => 0],
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name')));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $documentProvenance = $result['document']->attr('manifest')['packageProvenance'];
        $manifestDeclaredBytes = strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen('PNGDATA') + strlen($reviewImage) + strlen($scriptXml) + strlen($unsupportedImage);
        $manifestDeclaredCompressedBytes = strlen(gzdeflate($contentXml)) + strlen(gzdeflate($stylesXml)) + strlen(gzdeflate($metaXml)) + strlen('PNGDATA') + strlen($reviewImage) + strlen($scriptXml) + strlen($unsupportedImage);
        $exposableBytes = strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen('PNGDATA') + strlen($reviewImage);
        $exposableCompressedBytes = strlen(gzdeflate($contentXml)) + strlen(gzdeflate($stylesXml)) + strlen(gzdeflate($metaXml)) + strlen('PNGDATA') + strlen($reviewImage);
        $totalBytes = strlen(OdfReader::MIMETYPE) + strlen($manifestWithBuckets) + $manifestDeclaredBytes + strlen($thumbnail);
        $totalCompressedBytes = strlen(OdfReader::MIMETYPE) + strlen(gzdeflate($manifestWithBuckets)) + $manifestDeclaredCompressedBytes + strlen($thumbnail);

        $t->same($provenance, $documentProvenance);
        $t->same(10, $provenance['entryCount']);
        $t->same(10, $provenance['fileEntryCount']);
        $t->same(0, $provenance['directoryEntryCount']);
        $t->same($totalBytes, $provenance['totalByteLength']);
        $t->same($totalCompressedBytes, $provenance['totalCompressedByteLength']);
        $t->same($totalBytes, $provenance['fileByteLength']);
        $t->same($totalCompressedBytes, $provenance['fileCompressedByteLength']);
        $t->same(0, $provenance['directoryByteLength']);
        $t->same(0, $provenance['directoryCompressedByteLength']);

        $t->same($manifestDeclaredBytes, $provenance['roleByteLengths']['manifest-declared']);
        $t->same($manifestDeclaredCompressedBytes, $provenance['roleCompressedByteLengths']['manifest-declared']);
        $t->same(strlen(OdfReader::MIMETYPE), $provenance['roleByteLengths']['odf-mimetype']);
        $t->same(strlen($manifestWithBuckets), $provenance['roleByteLengths']['odf-manifest']);
        $t->same(strlen(gzdeflate($manifestWithBuckets)), $provenance['roleCompressedByteLengths']['odf-manifest']);
        $t->same(strlen($contentXml), $provenance['roleByteLengths']['odf-content']);
        $t->same(strlen(gzdeflate($contentXml)), $provenance['roleCompressedByteLengths']['odf-content']);
        $t->same(strlen('PNGDATA') + strlen($reviewImage) + strlen($unsupportedImage), $provenance['roleByteLengths']['media-resource']);
        $t->same(strlen($scriptXml), $provenance['roleByteLengths']['script-package']);
        $t->same(strlen($thumbnail), $provenance['roleByteLengths']['package-thumbnail']);
        $t->same(strlen($thumbnail), $provenance['roleByteLengths']['undeclared-package-entry']);

        $t->same([
            'package-bytes-exposable' => 5,
            'package-thumbnail-bytes-blocked' => 1,
            'script-package-bytes-blocked' => 1,
            'unsupported-compression-bytes-blocked' => 1,
        ], $provenance['packagePartByteExposurePolicyCounts']);
        $t->same($exposableBytes, $provenance['packagePartByteExposurePolicyByteLengths']['package-bytes-exposable']);
        $t->same($exposableCompressedBytes, $provenance['packagePartByteExposurePolicyCompressedByteLengths']['package-bytes-exposable']);
        $t->same(strlen($thumbnail), $provenance['packagePartByteExposurePolicyByteLengths']['package-thumbnail-bytes-blocked']);
        $t->same(strlen($scriptXml), $provenance['packagePartByteExposurePolicyByteLengths']['script-package-bytes-blocked']);
        $t->same(strlen($unsupportedImage), $provenance['packagePartByteExposurePolicyByteLengths']['unsupported-compression-bytes-blocked']);
        $t->same(['manifest-declared', 'media-resource'], $provenance['parts']['Pictures/unsupported.webp']['roles']);
        $t->same('unsupported-compression-bytes-blocked', $provenance['parts']['Pictures/unsupported.webp']['byteExposurePolicy']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $provenance['parts']['Thumbnails/thumbnail.png']['roles']);
        $t->same('package-thumbnail-bytes-blocked', $provenance['parts']['Thumbnails/thumbnail.png']['byteExposurePolicy']);
    },
    'reports ODT package media SHA-256 provenance without exposing blocked sidecars' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $reviewImage = 'REVIEWPNG';
        $scriptBytes = 'alert("blocked");';
        $configurationXml = '<accel:acceleratorlist xmlns:accel="http://openoffice.org/2001/accel"/>';
        $manifestWithReviewParts = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png" manifest:size="' . strlen($reviewImage) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/review.js" manifest:media-type="application/javascript" manifest:size="' . strlen($scriptBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="' . strlen($configurationXml) . '"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithReviewParts, null, null, [
            ['name' => 'Pictures/review.png', 'data' => $reviewImage, 'compressionMethod' => 0],
            ['name' => 'Scripts/review.js', 'data' => $scriptBytes, 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationXml, 'compressionMethod' => 0],
        ]));

        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $inventory = $result['importReport']['manifest']['packageProvenance']['parts'];

        $t->same(['Pictures/hero.png', 'Pictures/review.png'], array_column($result['media'], 'part'));
        $t->same(hash('sha256', 'PNGDATA'), $manifestByPart['Pictures/hero.png']['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $manifestByPart['Pictures/review.png']['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $mediaByPart['Pictures/review.png']['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $inventory['Pictures/review.png']['byteSha256']);
        $t->same(hash('sha256', $reviewImage), $result['document']->attr('manifest')['packageProvenance']['parts']['Pictures/review.png']['byteSha256']);

        $t->same(false, $manifestByPart['Scripts/review.js']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/review.js']['byteExposurePolicy']);
        $t->same(null, $manifestByPart['Scripts/review.js']['byteSha256']);
        $t->same(null, $inventory['Scripts/review.js']['byteSha256']);
        $t->same(false, $manifestByPart['Configurations2/accelerator/current.xml']['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $manifestByPart['Configurations2/accelerator/current.xml']['byteExposurePolicy']);
        $t->same(null, $manifestByPart['Configurations2/accelerator/current.xml']['byteSha256']);
        $t->same(null, $inventory['Configurations2/accelerator/current.xml']['byteSha256']);
    },
    'reports ODT configuration package sidecars without document media byte exposure' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $acceleratorXml = '<accel:acceleratorlist xmlns:accel="http://openoffice.org/2001/accel"/>';
        $configIconBytes = 'CONFIGPNG';
        $encryptedXml = '<encrypted-config/>';
        $invalidBytes = '%PDF-config';
        $statusbarXml = '<statusbar:statusbar xmlns:statusbar="http://openoffice.org/2001/statusbar"/>';
        $configurationEntries =
            '  <manifest:file-entry manifest:full-path="Configurations2/" manifest:media-type=""/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="' . strlen($acceleratorXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/images/Bitmaps/review.png" manifest:media-type="image/png" manifest:size="' . strlen($configIconBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/menubar/encrypted.xml" manifest:media-type="text/xml" manifest:size="' . strlen($encryptedXml) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="config-checksum"/></manifest:file-entry>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/popupmenu/invalid.pdf" manifest:media-type="application/pdf" manifest:size="' . strlen($invalidBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/toolbar/missing.xml" manifest:media-type="text/xml"/>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $configurationEntries . '</manifest:manifest>', $manifestXml);

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            overrideManifestXml: $manifest,
            extraParts: [
                ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
                ['name' => 'Configurations2/accelerator/current.xml', 'data' => $acceleratorXml, 'compressionMethod' => 0],
                ['name' => 'Configurations2/images/Bitmaps/review.png', 'data' => $configIconBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/menubar/encrypted.xml', 'data' => $encryptedXml, 'compressionMethod' => 0],
                ['name' => 'Configurations2/popupmenu/invalid.pdf', 'data' => $invalidBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/statusbar/standardbar.xml', 'data' => $statusbarXml, 'compressionMethod' => 0],
            ],
        ));
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $parts = $provenance['parts'];
        $manifestOrderByPart = [];
        foreach ($provenance['manifestFileEntryOrder'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestOrderByPart[$item['part']] = $item;
            }
        }
        $configurations = $result['packageConfigurations'];
        $configurationsByPart = [];
        foreach ($configurations['items'] as $item) {
            $configurationsByPart[$item['part']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(6, $provenance['configurationPackagePartCount']);
        $t->same(6, $provenance['roleCounts']['configuration-package']);
        $t->same(1, $provenance['undeclaredRoleCounts']['configuration-package']);
        $t->same(true, $manifestOrderByPart['Configurations2/accelerator/current.xml']['configurationPackagePart']);
        $t->same(false, $manifestOrderByPart['Configurations2/accelerator/current.xml']['scriptPackagePart']);
        $t->same(['configuration-package', 'zip-directory', 'manifest-declared'], $parts['Configurations2/']['roles']);
        $t->same(['configuration-package', 'manifest-declared'], $parts['Configurations2/accelerator/current.xml']['roles']);
        $t->same(['configuration-package', 'manifest-declared'], $parts['Configurations2/images/Bitmaps/review.png']['roles']);
        $t->same(['configuration-package', 'manifest-declared'], $parts['Configurations2/menubar/encrypted.xml']['roles']);
        $t->same(['configuration-package', 'manifest-declared'], $parts['Configurations2/popupmenu/invalid.pdf']['roles']);
        $t->same(['configuration-package', 'undeclared-package-entry'], $parts['Configurations2/statusbar/standardbar.xml']['roles']);

        $t->same($configurations, $result['document']->attr('packageConfigurations'));
        $t->same($configurations, $result['metadata']['odfPackageConfigurations']);
        $t->same($configurations, $result['importReport']['packageConfigurations']);
        $t->same(7, $configurations['count']);
        $t->same(6, $configurations['fileCount']);
        $t->same(1, $configurations['directoryCount']);
        $t->same(6, $configurations['declaredCount']);
        $t->same(1, $configurations['undeclaredCount']);
        $t->same(1, $configurations['missingCount']);
        $t->same(1, $configurations['encryptedCount']);
        $t->same(1, $configurations['invalidMediaTypeCount']);
        $t->same([
            'odf-configuration-package-encrypted-part',
            'odf-configuration-package-invalid-media-type',
            'odf-configuration-package-missing-part',
            'odf-configuration-package-undeclared-part',
        ], $configurations['issueCodes']);
        $t->same([
            'accelerator-configuration' => 1,
            'configuration-directory' => 1,
            'image-configuration-resource' => 1,
            'menubar-configuration' => 1,
            'popupmenu-configuration' => 1,
            'statusbar-configuration' => 1,
            'toolbar-configuration' => 1,
        ], $configurations['kindCounts']);
        $t->same('configuration-package-metadata-only', $configurations['reviewPolicy']);

        $accelerator = $manifestByPart['Configurations2/accelerator/current.xml'];
        $t->same(true, $accelerator['configurationPackagePart']);
        $t->same(false, $accelerator['canExposeBytes']);
        $t->same(null, $accelerator['byteLength']);
        $t->same(strlen($acceleratorXml), $accelerator['storedByteLength']);
        $t->same(null, $accelerator['crc32']);
        $t->same(sprintf('%08x', crc32($acceleratorXml)), $accelerator['storedCrc32']);
        $t->same('configuration-package-bytes-blocked', $accelerator['byteExposurePolicy']);
        $t->same('accelerator-configuration', $configurationsByPart['Configurations2/accelerator/current.xml']['kind']);
        $t->same('accelerator', $configurationsByPart['Configurations2/accelerator/current.xml']['group']);
        $t->same(null, $configurationsByPart['Configurations2/accelerator/current.xml']['byteLength']);
        $t->same(strlen($acceleratorXml), $configurationsByPart['Configurations2/accelerator/current.xml']['storedByteLength']);
        $t->same(null, $configurationsByPart['Configurations2/accelerator/current.xml']['crc32']);
        $t->same(sprintf('%08x', crc32($acceleratorXml)), $configurationsByPart['Configurations2/accelerator/current.xml']['storedCrc32']);
        $t->same(false, $configurationsByPart['Configurations2/accelerator/current.xml']['canExposeAsDocumentMedia']);
        $t->same([], $configurationsByPart['Configurations2/accelerator/current.xml']['issues']);

        $configIcon = $manifestByPart['Configurations2/images/Bitmaps/review.png'];
        $t->same(true, $configIcon['configurationPackagePart']);
        $t->same(false, $configIcon['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $configIcon['byteExposurePolicy']);
        $t->same('image-configuration-resource', $configurationsByPart['Configurations2/images/Bitmaps/review.png']['kind']);
        $t->same('image/png', $configurationsByPart['Configurations2/images/Bitmaps/review.png']['mediaType']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));

        $missing = $manifestByPart['Configurations2/toolbar/missing.xml'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same('configuration-package-bytes-blocked', $missing['byteExposurePolicy']);
        $t->same(['Configurations2/toolbar/missing.xml'], array_column($result['importReport']['manifest']['missingItems'], 'part'));
        $t->same(['odf-configuration-package-missing-part'], $configurationsByPart['Configurations2/toolbar/missing.xml']['issues']);

        $encrypted = $configurationsByPart['Configurations2/menubar/encrypted.xml'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedXml), $encrypted['storedByteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-configuration-package-encrypted-part'], $encrypted['issues']);

        $invalid = $configurationsByPart['Configurations2/popupmenu/invalid.pdf'];
        $t->same('application/pdf', $invalid['mediaType']);
        $t->same(false, $invalid['valid']);
        $t->same(['odf-configuration-package-invalid-media-type'], $invalid['issues']);

        $orphan = $configurationsByPart['Configurations2/statusbar/standardbar.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('text/xml', $orphan['mediaType']);
        $t->same('statusbar-configuration', $orphan['kind']);
        $t->same(['odf-configuration-package-undeclared-part'], $orphan['issues']);

        $t->same(false, $parts['Configurations2/accelerator/current.xml']['canExposeBytes']);
        $t->same(false, $parts['Configurations2/images/Bitmaps/review.png']['canExposeBytes']);
        $t->same(false, $parts['Configurations2/menubar/encrypted.xml']['canExposeBytes']);
        $t->same(false, $parts['Configurations2/popupmenu/invalid.pdf']['canExposeBytes']);
        $t->same(true, $parts['Configurations2/statusbar/standardbar.xml']['undeclared']);
    },
    'reports ODT configuration package metadata summaries as review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $acceleratorXml = '<accel:acceleratorlist xmlns:accel="http://openoffice.org/2001/accel"/>';
        $encryptedXml = '<encrypted-config/>';
        $invalidBytes = '%PDF-config';
        $statusbarXml = '<statusbar:statusbar xmlns:statusbar="http://openoffice.org/2001/statusbar"/>';
        $configurationEntries =
            '  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="' . strlen($acceleratorXml) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/encrypted/current.xml" manifest:media-type="text/xml" manifest:size="' . strlen($encryptedXml) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="config-checksum"/></manifest:file-entry>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/popupmenu/invalid.pdf" manifest:media-type="application/pdf" manifest:size="' . strlen($invalidBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Configurations2/toolbar/missing.xml" manifest:media-type="text/xml"/>' . "\n";
        $manifest = str_replace('</manifest:manifest>', $configurationEntries . '</manifest:manifest>', $manifestXml);

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            overrideManifestXml: $manifest,
            extraParts: [
                ['name' => 'Configurations2/accelerator/current.xml', 'data' => $acceleratorXml, 'compressionMethod' => 0],
                ['name' => 'Configurations2/encrypted/current.xml', 'data' => $encryptedXml, 'compressionMethod' => 0],
                ['name' => 'Configurations2/popupmenu/invalid.pdf', 'data' => $invalidBytes, 'compressionMethod' => 0],
                ['name' => 'Configurations2/statusbar/standardbar.xml', 'data' => $statusbarXml, 'compressionMethod' => 0],
            ],
        ));

        $configurations = $result['packageConfigurations'];
        $itemsByPart = [];
        foreach ($configurations['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }

        $t->same($configurations, $result['document']->attr('packageConfigurations'));
        $t->same($configurations, $result['metadata']['odfPackageConfigurations']);
        $t->same($configurations, $result['importReport']['packageConfigurations']);
        $t->same(5, $configurations['count']);
        $t->same(5, $configurations['fileCount']);
        $t->same(0, $configurations['directoryCount']);
        $t->same(4, $configurations['declaredCount']);
        $t->same(1, $configurations['undeclaredCount']);
        $t->same(1, $configurations['missingCount']);
        $t->same(1, $configurations['encryptedCount']);
        $t->same(1, $configurations['invalidMediaTypeCount']);
        $t->same([
            'odf-configuration-package-encrypted-part',
            'odf-configuration-package-invalid-media-type',
            'odf-configuration-package-missing-part',
            'odf-configuration-package-undeclared-part',
        ], $configurations['issueCodes']);
        $t->same([
            'accelerator-configuration' => 1,
            'popupmenu-configuration' => 1,
            'statusbar-configuration' => 1,
            'toolbar-configuration' => 1,
            'xml-configuration' => 1,
        ], $configurations['kindCounts']);
        $t->same('configuration-package-bytes-blocked', $configurations['byteExposurePolicy']);
        $t->same('configuration-package-metadata-only', $configurations['reviewPolicy']);

        $accelerator = $itemsByPart['Configurations2/accelerator/current.xml'];
        $t->same('text/xml', $accelerator['mediaType']);
        $t->same('accelerator-configuration', $accelerator['kind']);
        $t->same(true, $accelerator['declared']);
        $t->same(true, $accelerator['valid']);
        $t->same(null, $accelerator['byteLength']);
        $t->same(strlen($acceleratorXml), $accelerator['storedByteLength']);
        $t->same(null, $accelerator['crc32']);
        $t->same(sprintf('%08x', crc32($acceleratorXml)), $accelerator['storedCrc32']);
        $t->same(false, $accelerator['canExposeBytes']);
        $t->same(false, $accelerator['canExposeAsDocumentMedia']);
        $t->same('configuration-package-bytes-blocked', $accelerator['byteExposurePolicy']);
        $t->same([], $accelerator['issues']);

        $missing = $itemsByPart['Configurations2/toolbar/missing.xml'];
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['storedByteLength']);
        $t->same(['odf-configuration-package-missing-part'], $missing['issues']);

        $encrypted = $itemsByPart['Configurations2/encrypted/current.xml'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedXml), $encrypted['storedByteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-configuration-package-encrypted-part'], $encrypted['issues']);

        $invalid = $itemsByPart['Configurations2/popupmenu/invalid.pdf'];
        $t->same('application/pdf', $invalid['mediaType']);
        $t->same(false, $invalid['valid']);
        $t->same(['odf-configuration-package-invalid-media-type'], $invalid['issues']);

        $orphan = $itemsByPart['Configurations2/statusbar/standardbar.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('text/xml', $orphan['mediaType']);
        $t->same('statusbar-configuration', $orphan['kind']);
        $t->same(['odf-configuration-package-undeclared-part'], $orphan['issues']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
    },
    'reports ODT embedded object package provenance without exposing payload bytes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $chartContent = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"><office:body/></office:document-content>';
        $chartStyles = '<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>';
        $chartPreview = 'PREVIEW';
        $chartRdf = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>';
        $oleBytes = 'OLEBYTES!';
        $manifestWithObjects = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:version="1.3" manifest:preferred-view-mode="view"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml" manifest:size="' . strlen($chartContent) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Chart/styles.xml" manifest:media-type="text/xml" manifest:size="' . strlen($chartStyles) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Chart/Pictures/preview.png" manifest:media-type="image/png" manifest:size="' . strlen($chartPreview) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20OLE/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20OLE/oleObject.bin" manifest:media-type="application/vnd.openxmlformats-officedocument.oleObject" manifest:size="' . strlen($oleBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Missing/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Object%20Missing/content.xml" manifest:media-type="text/xml"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithObjects, null, null, [
            ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object Chart/content.xml', 'data' => $chartContent, 'compressionMethod' => 0],
            ['name' => 'Object Chart/styles.xml', 'data' => $chartStyles, 'compressionMethod' => 0],
            ['name' => 'Object Chart/Pictures/preview.png', 'data' => $chartPreview, 'compressionMethod' => 0],
            ['name' => 'Object Chart/manifest.rdf', 'data' => $chartRdf, 'compressionMethod' => 0],
            ['name' => 'Object OLE/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object OLE/oleObject.bin', 'data' => $oleBytes, 'compressionMethod' => 0],
        ]));

        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $objects = $provenance['embeddedObjectPackages'];
        $chart = $objects['byRootPart']['Object Chart/'];
        $ole = $objects['byRootPart']['Object OLE/'];
        $missing = $objects['byRootPart']['Object Missing/'];
        $inventory = $provenance['parts'];
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPart[$item['part']] = $item;
        }
        $chartContainedByPart = [];
        foreach ($chart['containedParts'] as $item) {
            $chartContainedByPart[$item['part']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(3, $provenance['embeddedObjectPackageCount']);
        $t->same(2, $provenance['embeddedObjectPackageExistingCount']);
        $t->same(1, $provenance['embeddedObjectPackageMissingCount']);
        $t->same(0, $provenance['embeddedObjectPackageEncryptedCount']);
        $t->same(5, $provenance['embeddedObjectContainedPartCount']);
        $t->same([
            'document-xml' => 2,
            'media-resource' => 1,
            'package-part' => 1,
            'rdf-metadata' => 1,
        ], $provenance['embeddedObjectContainedRoleCounts']);
        $t->same([
            'image' => 1,
            'other' => 1,
            'rdf' => 1,
            'xml' => 2,
        ], $provenance['embeddedObjectContainedMediaFamilyCounts']);
        $t->same(5, $provenance['embeddedObjectDeclaredContainedPartCount']);
        $t->same(4, $provenance['embeddedObjectExistingDeclaredContainedPartCount']);
        $t->same(1, $provenance['embeddedObjectMissingDeclaredContainedPartCount']);
        $t->same(1, $provenance['embeddedObjectUndeclaredContainedPartCount']);
        $t->same(3, $objects['count']);
        $t->same(['Object Chart/', 'Object OLE/', 'Object Missing/'], $objects['rootParts']);
        $t->same(['chart', 'spreadsheet'], $objects['objectTypes']);
        $t->same('embedded-object-package-bytes-blocked', $objects['byteExposurePolicy']);
        $t->same('embedded-object-package-metadata-only', $objects['reviewPolicy']);
        $t->same($objects['containedRoleCounts'], $provenance['embeddedObjectContainedRoleCounts']);
        $t->same($objects['containedMediaFamilyCounts'], $provenance['embeddedObjectContainedMediaFamilyCounts']);
        $t->same([
            'document-xml' => 2,
            'media-resource' => 1,
            'package-part' => 1,
            'rdf-metadata' => 1,
        ], $objects['containedRoleCounts']);
        $t->same([
            'document-xml' => strlen($chartContent) + strlen($chartStyles),
            'media-resource' => strlen($chartPreview),
            'package-part' => strlen($oleBytes),
            'rdf-metadata' => strlen($chartRdf),
        ], $objects['containedRoleByteLengths']);
        $t->same([
            'document-xml' => strlen($chartContent) + strlen($chartStyles),
            'media-resource' => strlen($chartPreview),
            'package-part' => strlen($oleBytes),
            'rdf-metadata' => strlen($chartRdf),
        ], $objects['containedRoleCompressedByteLengths']);
        $t->same([
            'image' => 1,
            'other' => 1,
            'rdf' => 1,
            'xml' => 2,
        ], $objects['containedMediaFamilyCounts']);
        $t->same([
            'image' => strlen($chartPreview),
            'other' => strlen($oleBytes),
            'rdf' => strlen($chartRdf),
            'xml' => strlen($chartContent) + strlen($chartStyles),
        ], $objects['containedMediaFamilyByteLengths']);
        $t->same([
            'image' => strlen($chartPreview),
            'other' => strlen($oleBytes),
            'rdf' => strlen($chartRdf),
            'xml' => strlen($chartContent) + strlen($chartStyles),
        ], $objects['containedMediaFamilyCompressedByteLengths']);
        $t->same([
            'odf-embedded-object-package-missing',
            'odf-embedded-object-package-missing-declared-part',
            'odf-embedded-object-package-undeclared-contained-part',
        ], $objects['issueCodes']);

        $t->same('Object Chart/', $chart['rootPart']);
        $t->same('Object Chart', $chart['objectPath']);
        $t->same('Object%20Chart/', $chart['fullPath']);
        $t->same('chart', $chart['objectType']);
        $t->same('application/vnd.oasis.opendocument.chart', $chart['mediaType']);
        $t->same('1.3', $chart['version']);
        $t->same('view', $chart['preferredViewMode']);
        $t->same(true, $chart['exists']);
        $t->same(false, $chart['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $chart['byteExposurePolicy']);
        $t->same(4, $chart['containedPartCount']);
        $t->same(strlen($chartContent) + strlen($chartStyles) + strlen($chartPreview) + strlen($chartRdf), $chart['containedByteLength']);
        $t->same([
            'document-xml' => 2,
            'media-resource' => 1,
            'rdf-metadata' => 1,
        ], $chart['containedRoleCounts']);
        $t->same([
            'document-xml' => strlen($chartContent) + strlen($chartStyles),
            'media-resource' => strlen($chartPreview),
            'rdf-metadata' => strlen($chartRdf),
        ], $chart['containedRoleByteLengths']);
        $t->same([
            'image' => 1,
            'rdf' => 1,
            'xml' => 2,
        ], $chart['containedMediaFamilyCounts']);
        $t->same([
            'image' => strlen($chartPreview),
            'rdf' => strlen($chartRdf),
            'xml' => strlen($chartContent) + strlen($chartStyles),
        ], $chart['containedMediaFamilyByteLengths']);
        $t->same(['Object Chart/Pictures/preview.png', 'Object Chart/content.xml', 'Object Chart/manifest.rdf', 'Object Chart/styles.xml'], array_column($chart['containedParts'], 'part'));
        $t->same(['media-resource', 'document-xml', 'rdf-metadata', 'document-xml'], array_column($chart['containedParts'], 'containedRole'));
        $t->same(['image', 'xml', 'rdf', 'xml'], array_column($chart['containedParts'], 'containedMediaFamily'));
        $t->same('document-xml', $chartContainedByPart['Object Chart/styles.xml']['containedRole']);
        $t->same('xml', $chartContainedByPart['Object Chart/styles.xml']['containedMediaFamily']);
        $t->same(3, $chart['declaredContainedPartCount']);
        $t->same(3, $chart['existingDeclaredContainedPartCount']);
        $t->same(0, $chart['missingDeclaredContainedPartCount']);
        $t->same(1, $chart['undeclaredContainedPartCount']);
        $t->same(['Object Chart/manifest.rdf'], array_column($chart['undeclaredContainedParts'], 'part'));
        $t->same(['rdf-metadata'], array_column($chart['undeclaredContainedParts'], 'containedRole'));
        $t->same(['rdf'], array_column($chart['undeclaredContainedParts'], 'containedMediaFamily'));
        $t->same(['odf-embedded-object-package-undeclared-contained-part'], $chart['issues']);

        $t->same('spreadsheet', $ole['objectType']);
        $t->same('application/vnd.oasis.opendocument.spreadsheet', $ole['mediaType']);
        $t->same(false, $ole['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $ole['byteExposurePolicy']);
        $t->same(1, $ole['containedPartCount']);
        $t->same(strlen($oleBytes), $ole['containedByteLength']);
        $t->same(['package-part' => 1], $ole['containedRoleCounts']);
        $t->same(['package-part' => strlen($oleBytes)], $ole['containedRoleByteLengths']);
        $t->same(['other' => 1], $ole['containedMediaFamilyCounts']);
        $t->same(['other' => strlen($oleBytes)], $ole['containedMediaFamilyByteLengths']);
        $t->same(['Object OLE/oleObject.bin'], array_column($ole['containedParts'], 'part'));
        $t->same(['package-part'], array_column($ole['containedParts'], 'containedRole'));
        $t->same(['other'], array_column($ole['containedParts'], 'containedMediaFamily'));
        $t->same([], $ole['issues']);

        $t->same('Object Missing/', $missing['rootPart']);
        $t->same(false, $missing['exists']);
        $t->same(0, $missing['containedPartCount']);
        $t->same(1, $missing['declaredContainedPartCount']);
        $t->same(1, $missing['missingDeclaredContainedPartCount']);
        $t->same(['Object Missing/content.xml'], array_column($missing['missingDeclaredContainedParts'], 'part'));
        $t->same([
            'odf-embedded-object-package-missing',
            'odf-embedded-object-package-missing-declared-part',
        ], $missing['issues']);

        $previewManifest = $manifestByPart['Object Chart/Pictures/preview.png'];
        $previewInventory = $inventory['Object Chart/Pictures/preview.png'];
        $t->same(true, $previewManifest['embeddedObjectPackagePart']);
        $t->same(true, $previewManifest['embeddedObjectContainedPart']);
        $t->same('Object Chart/', $previewManifest['embeddedObjectRootPart']);
        $t->same(false, $previewManifest['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $previewManifest['byteExposurePolicy']);
        $t->same(true, $previewInventory['embeddedObjectPackagePart']);
        $t->same(true, $previewInventory['embeddedObjectContainedPart']);
        $t->same(false, $previewInventory['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $previewInventory['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));

        $t->same(2, $provenance['roleCounts']['embedded-object-root']);
        $t->same(5, $provenance['roleCounts']['embedded-object-part']);
        $t->true(in_array('embedded-object-root', $inventory['Object Chart/']['roles'], true), 'Object chart root role missing');
        $t->true(in_array('embedded-object-part', $inventory['Object Chart/content.xml']['roles'], true), 'Object chart content role missing');
        $t->true(in_array('embedded-object-part', $inventory['Object Chart/manifest.rdf']['roles'], true), 'Object chart undeclared part role missing');
        $t->true(in_array('undeclared-package-entry', $inventory['Object Chart/manifest.rdf']['roles'], true), 'Object chart undeclared role missing');
        $t->true(in_array('embedded-object-root', $inventory['Object OLE/']['roles'], true), 'Object OLE root role missing');
        $t->true(in_array('embedded-object-part', $inventory['Object OLE/oleObject.bin']['roles'], true), 'Object OLE payload role missing');
    },
    'reports ODT object replacement sidecars as metadata-only package review items' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $previewBytes = 'PREVIEWPNG';
        $encryptedBytes = '<svg/>';
        $invalidBytes = 'NOTIMAGE';
        $orphanBytes = 'ORPHANPNG';
        $manifestWithReplacements = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="ObjectReplacements/preview.png" manifest:media-type="image/png" manifest:size="' . strlen($previewBytes) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="ObjectReplacements/missing.jpg" manifest:media-type="image/jpeg"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="ObjectReplacements/encrypted.svg" manifest:media-type="image/svg+xml" manifest:size="' . strlen($encryptedBytes) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="replacement-checksum"/></manifest:file-entry>' . "\n"
            . '  <manifest:file-entry manifest:full-path="ObjectReplacements/invalid.bin" manifest:media-type="application/octet-stream" manifest:size="' . strlen($invalidBytes) . '"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithReplacements, null, null, [
            ['name' => 'ObjectReplacements/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/encrypted.svg', 'data' => $encryptedBytes, 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/invalid.bin', 'data' => $invalidBytes, 'compressionMethod' => 0],
            ['name' => 'ObjectReplacements/orphan.png', 'data' => $orphanBytes, 'compressionMethod' => 0],
        ]));

        $replacements = $result['packageObjectReplacements'];
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $itemsByPart = [];
        foreach ($replacements['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $inventory = $provenance['parts'];

        $t->same($replacements, $result['document']->attr('packageObjectReplacements'));
        $t->same($replacements, $result['metadata']['odfPackageObjectReplacements']);
        $t->same($replacements, $result['importReport']['packageObjectReplacements']);
        $t->same(5, $replacements['count']);
        $t->same(3, $replacements['readableCount']);
        $t->same(4, $replacements['declaredCount']);
        $t->same(1, $replacements['undeclaredCount']);
        $t->same(1, $replacements['missingCount']);
        $t->same(1, $replacements['encryptedCount']);
        $t->same(1, $replacements['invalidMediaTypeCount']);
        $t->same(4, $replacements['issueCount']);
        $t->same([
            'odf-object-replacement-encrypted-package-part',
            'odf-object-replacement-invalid-media-type',
            'odf-object-replacement-missing-package-part',
            'odf-object-replacement-undeclared-package-part',
        ], $replacements['issueCodes']);

        $preview = $itemsByPart['ObjectReplacements/preview.png'];
        $t->same('image/png', $preview['mediaType']);
        $t->same(true, $preview['declared']);
        $t->same(true, $preview['valid']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $preview['crc32']);
        $t->same('object-replacement-package-bytes-blocked', $preview['byteExposurePolicy']);
        $t->same('object-replacement-metadata-only', $preview['reviewPolicy']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);
        $t->same(true, $manifestByPart['ObjectReplacements/preview.png']['objectReplacementPackagePart']);
        $t->same(false, $manifestByPart['ObjectReplacements/preview.png']['canExposeBytes']);
        $t->same('object-replacement-package-bytes-blocked', $manifestByPart['ObjectReplacements/preview.png']['byteExposurePolicy']);

        $missing = $itemsByPart['ObjectReplacements/missing.jpg'];
        $t->same(false, $missing['exists']);
        $t->same(['odf-object-replacement-missing-package-part'], $missing['issues']);

        $encrypted = $itemsByPart['ObjectReplacements/encrypted.svg'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-object-replacement-encrypted-package-part'], $encrypted['issues']);

        $invalid = $itemsByPart['ObjectReplacements/invalid.bin'];
        $t->same('application/octet-stream', $invalid['mediaType']);
        $t->same(false, $invalid['valid']);
        $t->same(['odf-object-replacement-invalid-media-type'], $invalid['issues']);

        $orphan = $itemsByPart['ObjectReplacements/orphan.png'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('image/png', $orphan['mediaType']);
        $t->same(['odf-object-replacement-undeclared-package-part'], $orphan['issues']);

        $t->same(4, $provenance['objectReplacementPartCount']);
        $t->same(4, $provenance['roleCounts']['object-replacement']);
        $t->same(1, $provenance['undeclaredRoleCounts']['object-replacement']);
        $t->same(['object-replacement', 'manifest-declared'], $inventory['ObjectReplacements/preview.png']['roles']);
        $t->same(['object-replacement', 'undeclared-package-entry'], $inventory['ObjectReplacements/orphan.png']['roles']);
        $t->same(false, in_array('media-resource', $inventory['ObjectReplacements/preview.png']['roles'], true));
        $t->same(1, count($result['media']), 'object replacement sidecars must stay out of document media handoff');
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
    },
    'reports ODT layout-cache sidecar as metadata-only package review data' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $layoutCacheBytes = 'LAYOUT-CACHE-BYTES';
        $manifestWithLayoutCache = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . strlen($layoutCacheBytes) . '"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithLayoutCache, null, null, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]));
        $layoutCaches = $result['packageLayoutCaches'];
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $itemsByPart = [];
        foreach ($layoutCaches['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($layoutCaches, $result['document']->attr('packageLayoutCaches'));
        $t->same($layoutCaches, $result['metadata']['odfPackageLayoutCaches']);
        $t->same($layoutCaches, $result['importReport']['packageLayoutCaches']);
        $t->same(1, $layoutCaches['count']);
        $t->same(1, $layoutCaches['readableCount']);
        $t->same(1, $layoutCaches['declaredCount']);
        $t->same(0, $layoutCaches['undeclaredCount']);
        $t->same(0, $layoutCaches['missingCount']);
        $t->same(0, $layoutCaches['encryptedCount']);
        $t->same(0, $layoutCaches['invalidMediaTypeCount']);
        $t->same(0, $layoutCaches['issueCount']);
        $t->same('layout-cache-package-bytes-blocked', $layoutCaches['byteExposurePolicy']);
        $t->same('layout-cache-metadata-only', $layoutCaches['reviewPolicy']);

        $declared = $itemsByPart['layout-cache'];
        $t->same('application/binary', $declared['mediaType']);
        $t->same('application/binary', $declared['mediaTypeBase']);
        $t->same(['application/binary', 'application/octet-stream'], $declared['expectedMediaTypes']);
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($layoutCacheBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($layoutCacheBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('layout-cache-package-bytes-blocked', $declared['byteExposurePolicy']);
        $t->same('layout-cache-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);

        $manifestLayoutCache = $manifestByPart['layout-cache'];
        $t->same(true, $manifestLayoutCache['layoutCachePackagePart']);
        $t->same(false, $manifestLayoutCache['canExposeBytes']);
        $t->same(null, $manifestLayoutCache['byteLength']);
        $t->same(strlen($layoutCacheBytes), $manifestLayoutCache['storedByteLength']);
        $t->same(null, $manifestLayoutCache['byteSha256']);
        $t->same('layout-cache-package-bytes-blocked', $manifestLayoutCache['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(1, $provenance['layoutCachePartCount']);
        $t->same(1, $provenance['roleCounts']['layout-cache']);
        $t->same(['layout-cache', 'manifest-declared'], $provenance['parts']['layout-cache']['roles']);

        $missingResult = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithLayoutCache));
        $missing = $missingResult['packageLayoutCaches']['items'][0];
        $t->same(false, $missing['exists']);
        $t->same(['odf-layout-cache-missing-package-part'], $missing['issues']);
        $t->same('layout-cache-package-bytes-blocked', $missing['byteExposurePolicy']);

        $invalidManifest = str_replace('manifest:media-type="application/binary"', 'manifest:media-type="image/png"', $manifestWithLayoutCache);
        $invalidResult = (new OdfReader())->readPackage($buildOdtPackage(null, $invalidManifest, null, null, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]));
        $invalid = $invalidResult['packageLayoutCaches']['items'][0];
        $t->same(false, $invalid['valid']);
        $t->same(['odf-layout-cache-invalid-media-type'], $invalid['issues']);
        $t->same(['Pictures/hero.png'], array_column($invalidResult['media'], 'part'));

        $encryptedManifest = str_replace(
            '<manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . strlen($layoutCacheBytes) . '"/>',
            '<manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . strlen($layoutCacheBytes) . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="layout-cache-checksum"/></manifest:file-entry>',
            $manifestWithLayoutCache
        );
        $encryptedResult = (new OdfReader())->readPackage($buildOdtPackage(null, $encryptedManifest, null, null, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]));
        $encrypted = $encryptedResult['packageLayoutCaches']['items'][0];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-layout-cache-encrypted-package-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $undeclaredResult = (new OdfReader())->readPackage($buildOdtPackage(null, null, null, null, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]));
        $undeclared = $undeclaredResult['packageLayoutCaches']['items'][0];
        $t->same(false, $undeclared['declared']);
        $t->same(true, $undeclared['undeclared']);
        $t->same('application/binary', $undeclared['mediaType']);
        $t->same(['odf-layout-cache-undeclared-package-part'], $undeclared['issues']);
        $t->same(['layout-cache', 'undeclared-package-entry'], $undeclaredResult['importReport']['manifest']['packageProvenance']['parts']['layout-cache']['roles']);
    },
    'preserves ODT raw ZIP entry name provenance in package review' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $decodedName = "Pictures/caf\xc3\xa9.png";
        $rawName = "Pictures/caf\x82.png";
        $legacyBytes = 'CAFEPNG';
        $manifestWithLegacyName = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/caf%C3%A9.png" manifest:media-type="image/png"/>',
            $manifestXml
        );
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestWithLegacyName],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            [
                'name' => $decodedName,
                'rawName' => $rawName,
                'generalPurposeFlags' => 0,
                'data' => $legacyBytes,
                'compressionMethod' => 0,
            ],
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name')));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $legacy = $provenance['parts'][$decodedName];
        $mediaByPart = [];
        foreach ($result['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(1, $provenance['rawNameProvenanceEntryCount']);
        $t->same(1, $provenance['legacyEncodedNameEntryCount']);
        $t->same(0, $provenance['unicodePathExtraEntryCount']);
        $t->same(1, $provenance['decodedNameDiffersFromRawNameEntryCount']);
        $t->same($decodedName, $provenance['rawNameProvenanceEntries'][0]['part']);
        $t->same(bin2hex($rawName), $provenance['rawNameProvenanceEntries'][0]['rawNameHex']);
        $t->same('cp437', $provenance['rawNameProvenanceEntries'][0]['nameEncoding']);
        $t->same(false, $provenance['rawNameProvenanceEntries'][0]['rawNameMatchesDecodedName']);

        $t->same($decodedName, $legacy['part']);
        $t->same('Pictures/caf%C3%A9.png', $legacy['manifestFullPath']);
        $t->same('Pictures/caf%C3%A9.png', $legacy['manifestPartReference']);
        $t->same(bin2hex($rawName), $legacy['rawNameHex']);
        $t->same('cp437', $legacy['nameEncoding']);
        $t->same(false, $legacy['rawNameMatchesDecodedName']);
        $t->same(true, $legacy['usesLegacyNameEncoding']);
        $t->same(false, $legacy['usesUnicodePathExtraField']);
        $t->same(true, $legacy['hasRawNameProvenance']);
        $t->same(true, $legacy['declaredInManifest']);
        $t->same(['manifest-declared', 'media-resource'], $legacy['roles']);
        $t->same(strlen($legacyBytes), $mediaByPart[$decodedName]['byteLength']);
        $t->same('image/png', $mediaByPart[$decodedName]['mediaType']);
    },
    'checks ODT mimetype placement by local ZIP header order' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, [
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'mimetype',
        ]));

        $t->same('ODT Import Packet', $result['metadata']['title']);
        $t->same('odt', $result['document']->attr('source'));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $mimetypeEntry = $result['importReport']['manifest']['mimetypeEntry'];
        $t->same($mimetypeEntry, $result['document']->attr('manifest')['mimetypeEntry']);
        $t->same('mimetype', $mimetypeEntry['entryName']);
        $t->same(true, $mimetypeEntry['exists']);
        $t->same('mimetype', $mimetypeEntry['firstLocalEntryName']);
        $t->same(true, $mimetypeEntry['isFirstLocalEntry']);
        $t->same(0, $mimetypeEntry['compressionMethod']);
        $t->same('stored', $mimetypeEntry['compressionMethodName']);
        $t->same(false, $mimetypeEntry['usesDataDescriptor']);
        $t->same([], $mimetypeEntry['centralExtraFieldIds']);
        $t->same([], $mimetypeEntry['localExtraFieldIds']);
        $t->same(strlen(OdfReader::MIMETYPE), $mimetypeEntry['contentBytes']);
        $t->same(true, $mimetypeEntry['contentsMatch']);
        $t->same(true, $mimetypeEntry['isValid']);
        $t->same([], $mimetypeEntry['diagnostics']);
    },
    'aggregates ODT manifest encryption review provenance' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifest = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:wp="urn:wordpress:review" manifest:version="1.4">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="hero-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="hero-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="16" manifest:iteration-count="1024" manifest:salt="hero-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Pictures/secret.bin" manifest:media-type="application/octet-stream" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="secret-checksum">
      <manifest:algorithm manifest:algorithm-name="AES-256-CBC" manifest:initialisation-vector="secret-iv"/>
      <manifest:algorithm manifest:algorithm-name="AES-128-CBC" manifest:initialisation-vector="legacy-iv"/>
      <wp:review-hint>legacy encryption metadata</wp:review-hint>
    </manifest:encryption-data>
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="legacy-checksum">
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="32" manifest:iteration-count="2048" manifest:salt="secret-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
    </manifest:encryption-data>
  </manifest:file-entry>
</manifest:manifest>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            null,
            $manifest,
            null,
            null,
            [
                ['name' => 'Pictures/secret.bin', 'data' => 'SECRETBYTES'],
            ]
        ));
        $encryption = $result['importReport']['manifest']['encryption'];
        $documentEncryption = $result['document']->attr('manifest')['encryption'];
        $reportEncryption = $result['importReport']['encryption']['summary'];
        $provenanceEncryption = $result['importReport']['manifest']['packageProvenance']['manifestEncryption'];
        $itemsByPath = [];
        foreach ($encryption['items'] as $item) {
            $itemsByPath[$item['path']] = $item;
        }

        $t->same(2, $encryption['encryptedItemCount']);
        $t->same(3, $encryption['recordCount']);
        $t->same(['Pictures/hero.png', 'Pictures/secret.bin'], $encryption['encryptedParts']);
        $t->same(['SHA1/1K' => 2, 'SHA256/1K' => 1], $encryption['checksumTypeCounts']);
        $t->same([
            'AES-128-CBC' => 1,
            'AES-256-CBC' => 1,
            'Blowfish CFB' => 1,
        ], $encryption['algorithmNameCounts']);
        $t->same(['PBKDF2' => 2], $encryption['keyDerivationNameCounts']);
        $t->same(['SHA1' => 1, 'SHA256' => 1], $encryption['startKeyGenerationNameCounts']);
        $t->same(1, $encryption['unknownChildCount']);
        $t->same(['wp:review-hint' => 1], $encryption['unknownChildNameCounts']);
        $t->same([
            'odf-manifest-encryption-multiple-algorithms' => 1,
            'odf-manifest-encryption-multiple-encryption-data' => 1,
            'odf-manifest-encryption-unknown-child' => 1,
        ], $encryption['issueCodeCounts']);
        $t->same(1, $itemsByPath['Pictures/hero.png']['encryptionRecordCount']);
        $t->same(['Blowfish CFB'], $itemsByPath['Pictures/hero.png']['algorithmNames']);
        $t->same(2, $itemsByPath['Pictures/secret.bin']['encryptionRecordCount']);
        $t->same(['AES-256-CBC', 'AES-128-CBC'], $itemsByPath['Pictures/secret.bin']['algorithmNames']);
        $t->same(['wp:review-hint'], $itemsByPath['Pictures/secret.bin']['unknownChildNames']);
        $t->same([
            'odf-manifest-encryption-multiple-algorithms',
            'odf-manifest-encryption-unknown-child',
            'odf-manifest-encryption-multiple-encryption-data',
        ], $itemsByPath['Pictures/secret.bin']['issueCodes']);
        $t->same(false, $itemsByPath['Pictures/secret.bin']['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $itemsByPath['Pictures/secret.bin']['byteExposurePolicy']);
        $t->same($encryption, $documentEncryption);
        $t->same($encryption, $reportEncryption);
        $t->same($encryption, $provenanceEncryption);
    },
    'rejects invalid ODT manifest root content and duplicate part declarations' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $reader = new OdfReader();

        $manifestWithoutRoot = str_replace(
            '  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>' . "\n",
            '',
            $manifestXml
        );
        $manifestWithWrongRoot = str_replace(
            'manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"',
            'manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"',
            $manifestXml
        );
        $manifestWithoutContentXml = str_replace(
            '  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' . "\n",
            '',
            $manifestXml
        );
        $manifestWithDuplicateFullPath = str_replace(
            '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/jpeg"/>',
            $manifestXml
        );
        $manifestWithDuplicateDecodedPart = str_replace(
            '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Pictures/hero%2Epng" manifest:media-type="image/png"/>',
            $manifestXml
        );

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $manifestWithoutRoot)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $manifestWithWrongRoot)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $manifestWithoutContentXml)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $manifestWithDuplicateFullPath)));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $manifestWithDuplicateDecodedPart)));
    },
    'surfaces ODT ZIP package comments in rich package provenance' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'comment' => 'manifest review'],
            ['name' => 'content.xml', 'data' => $contentXml, 'comment' => 'body review'],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0, 'comment' => 'media review'],
        ], 'odt package review');

        $result = (new OdfReader())->readPackage($package);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $documentProvenance = $result['document']->attr('manifest')['packageProvenance'];
        $comments = $provenance['comments'];
        $content = $provenance['parts']['content.xml'];
        $hero = $provenance['parts']['Pictures/hero.png'];

        $t->same($comments, $package->commentPreflight());
        $t->same($provenance, $documentProvenance);
        $t->same(true, $comments['hasPackageComment']);
        $t->same(true, $comments['hasEntryComments']);
        $t->same(true, $comments['hasComments']);
        $t->same('odt package review', $comments['packageComment']);
        $t->same(3, $comments['entryCommentCount']);
        $t->same(['META-INF/manifest.xml', 'content.xml', 'Pictures/hero.png'], $comments['commentedEntryNames']);
        $t->same(true, $provenance['hasPackageComment']);
        $t->same(true, $provenance['hasEntryComments']);
        $t->same(3, $provenance['entryCommentCount']);
        $t->same(['META-INF/manifest.xml', 'content.xml', 'Pictures/hero.png'], $provenance['commentedEntryNames']);

        $t->same('body review', $content['zipEntryComment']);
        $t->same(strlen('body review'), $content['zipEntryCommentLength']);
        $t->same('utf-8', $content['zipEntryCommentEncoding']);
        $t->same(true, $content['zipEntryHasComment']);
        $t->same([], $content['zipEntryCommentIssues']);
        $t->same('media review', $hero['zipEntryComment']);
        $t->same(true, $hero['zipEntryHasComment']);
        $t->same('package-bytes-exposable', $hero['byteExposurePolicy']);
        $t->same(1, count($result['media']));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $t->same(0, $provenance['undeclaredEntryCount']);
    },
    'rejects malformed ODT packages before conversion handoff' => static function (TestRunner $t) use ($buildOdtPackage, $buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml): void {
        $reader = new OdfReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            [
                'name' => 'mimetype',
                'data' => OdfReader::MIMETYPE,
                'compressionMethod' => 0,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('review'), 'review'),
            ],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $wrongContentRoot = '<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>';
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildOdtPackage($wrongContentRoot)));

        $unsafeManifest = str_replace('Pictures/hero.png', 'Pictures/../secret.png', $manifestXml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $unsafeManifest)));

        $encodedUnsafeManifest = str_replace('Pictures/hero.png', 'Pictures/%2e%2e/secret.png', $manifestXml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $encodedUnsafeManifest)));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildZipPackageWithCentralDirectoryOrder([
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml],
        ], [
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
        ])));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
        ])));
    },
];
