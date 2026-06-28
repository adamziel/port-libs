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
];
