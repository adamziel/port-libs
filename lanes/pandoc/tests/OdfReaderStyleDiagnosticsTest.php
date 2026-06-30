<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
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

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:family="paragraph" style:display-name="Nameless Paragraph"/>
    <style:style style:name="FamilylessParagraph" style:display-name="Familyless Paragraph"/>
    <style:style style:name="VendorReviewStyle" style:family="review-extension"/>
    <style:style style:name="KnownParagraph" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;

return [
    'reports malformed ODT style definitions with source provenance' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
        ], 'odt style diagnostics'));

        $styleReport = $result['importReport']['styles'];
        $documentStyles = $result['document']->attr('styles');
        $diagnosticsByCode = [];
        foreach ($styleReport['diagnostics'] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']][] = $diagnostic;
        }

        $t->same(3, $styleReport['count']);
        $t->same(3, $styleReport['diagnosticCount']);
        $t->same($styleReport['diagnostics'], $documentStyles['diagnostics']);
        $t->same([
            'odf-style-missing-family' => 1,
            'odf-style-missing-name' => 1,
            'odf-style-unknown-family' => 1,
        ], $styleReport['diagnosticCodeCounts']);

        $missingName = $diagnosticsByCode['odf-style-missing-name'][0];
        $t->same('paragraph', $missingName['family']);
        $t->same('styles.xml', $missingName['sourcePart']);
        $t->same('office:styles', $missingName['sourceContainer']);

        $missingFamily = $diagnosticsByCode['odf-style-missing-family'][0];
        $t->same('FamilylessParagraph', $missingFamily['styleName']);
        $t->same('styles.xml', $missingFamily['sourcePart']);
        $t->same('office:styles', $missingFamily['sourceContainer']);

        $unknownFamily = $diagnosticsByCode['odf-style-unknown-family'][0];
        $t->same('VendorReviewStyle', $unknownFamily['styleName']);
        $t->same('review-extension', $unknownFamily['family']);
        $t->same('styles.xml', $unknownFamily['sourcePart']);
        $t->same('office:styles', $unknownFamily['sourceContainer']);

        $t->same('', $result['styles']['FamilylessParagraph']['family']);
        $t->same('review-extension', $result['styles']['VendorReviewStyle']['family']);
        $t->same('paragraph', $result['styles']['KnownParagraph']['family']);
    },
    'reports nameless ODT style catalog definitions with source provenance' => static function (TestRunner $t) use ($manifestXml, $contentXml, $metaXml): void {
        $namelessStylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:font-face-decls>
    <style:font-face svg:font-family="Nameless Sans"/>
  </office:font-face-decls>
  <office:automatic-styles>
    <style:page-layout>
      <style:page-layout-properties style:print-orientation="portrait"/>
    </style:page-layout>
    <number:number-style>
      <number:number number:min-integer-digits="2"/>
    </number:number-style>
  </office:automatic-styles>
  <office:styles>
    <text:list-style>
      <text:list-level-style-number text:level="1" style:num-format="1"/>
    </text:list-style>
    <table:table-template/>
  </office:styles>
  <office:master-styles>
    <style:master-page style:display-name="Nameless master page"/>
  </office:master-styles>
</office:document-styles>
XML;

        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $namelessStylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
        ], 'odt nameless style diagnostics'));

        $styleReport = $result['importReport']['styles'];
        $diagnosticsByCode = [];
        foreach ($styleReport['diagnostics'] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']][] = $diagnostic;
        }

        $expectedCodeCounts = [
            'odf-data-style-missing-name' => 1,
            'odf-font-face-missing-name' => 1,
            'odf-list-style-missing-name' => 1,
            'odf-master-page-missing-name' => 1,
            'odf-page-layout-missing-name' => 1,
            'odf-table-template-missing-name' => 1,
        ];

        $t->same(0, $styleReport['count']);
        $t->same(6, $styleReport['diagnosticCount']);
        $t->same($expectedCodeCounts, $styleReport['diagnosticCodeCounts']);
        $t->same('styles.xml', $diagnosticsByCode['odf-font-face-missing-name'][0]['sourcePart']);
        $t->same('office:font-face-decls', $diagnosticsByCode['odf-font-face-missing-name'][0]['sourceContainer']);
        $t->same('style:font-face', $diagnosticsByCode['odf-font-face-missing-name'][0]['element']);
        $t->same('office:automatic-styles', $diagnosticsByCode['odf-page-layout-missing-name'][0]['sourceContainer']);
        $t->same('style:page-layout', $diagnosticsByCode['odf-page-layout-missing-name'][0]['element']);
        $t->same('number:number-style', $diagnosticsByCode['odf-data-style-missing-name'][0]['element']);
        $t->same('office:styles', $diagnosticsByCode['odf-list-style-missing-name'][0]['sourceContainer']);
        $t->same('text:list-style', $diagnosticsByCode['odf-list-style-missing-name'][0]['element']);
        $t->same('table:table-template', $diagnosticsByCode['odf-table-template-missing-name'][0]['element']);
        $t->same('office:master-styles', $diagnosticsByCode['odf-master-page-missing-name'][0]['sourceContainer']);
        $t->same('style:master-page', $diagnosticsByCode['odf-master-page-missing-name'][0]['element']);

        $packageStyles = $result['packageStyles'];
        $t->same($packageStyles, $result['importReport']['packageStyles']);
        $t->same($packageStyles, $result['document']->attr('packageStyles'));
        $t->same(1, $packageStyles['count']);
        $t->same(6, $packageStyles['diagnosticCount']);
        $t->same($expectedCodeCounts, $packageStyles['diagnosticCodeCounts']);
        $t->same(['styles.xml'], $packageStyles['diagnosticSourceParts']);
        $t->same('odf-style-package-provenance-metadata-only', $packageStyles['byteExposurePolicy']);
        $t->same(false, $packageStyles['canExposeBytes']);
        $t->same(6, $packageStyles['items'][0]['diagnosticCount']);
        $t->same($expectedCodeCounts, $packageStyles['items'][0]['diagnosticCodeCounts']);
    },
];
