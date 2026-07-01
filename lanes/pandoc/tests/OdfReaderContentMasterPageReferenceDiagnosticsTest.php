<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:master-styles>
    <style:master-page style:name="ExistingEndnotes"/>
  </office:master-styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:notes-configuration
        text:note-class="footnote"
        text:master-page-name="MissingFootnotePage"/>
      <text:notes-configuration
        text:note-class="endnote"
        text:master-page-name="ExistingEndnotes"/>
      <text:p>Content master page diagnostics packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

return [
    'reports content master page reference diagnostics without exposing package bytes' => static function (TestRunner $t) use ($manifestXml, $stylesXml, $contentXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
        ], 'odt content master page diagnostics'));

        $styleReport = $result['importReport']['styles'];
        $documentStyles = $result['document']->attr('styles');
        $declarations = $result['contentDeclarations'];
        $diagnostic = $styleReport['diagnostics'][0] ?? [];

        $t->same($styleReport['diagnostics'], $documentStyles['diagnostics']);
        $t->same($styleReport['diagnosticCodeCounts'], $documentStyles['diagnosticCodeCounts']);
        $t->same(1, $styleReport['diagnosticCount']);
        $t->same(['odf-content-missing-master-page' => 1], $styleReport['diagnosticCodeCounts']);
        $t->same('odf-content-missing-master-page', $diagnostic['code'] ?? null);
        $t->same('content.xml', $diagnostic['sourcePart'] ?? null);
        $t->same('text:notes-configuration', $diagnostic['element'] ?? null);
        $t->same('text:master-page-name', $diagnostic['attribute'] ?? null);
        $t->same('MissingFootnotePage', $diagnostic['masterPageName'] ?? null);
        $t->same(1, $styleReport['masterPageCount']);
        $t->same(['ExistingEndnotes'], array_keys($result['masterPages']));
        $t->same(2, $declarations['noteConfigurationCount']);
        $t->same('MissingFootnotePage', $declarations['noteConfigurationsByClass']['footnote']['masterPageName']);
        $t->same('ExistingEndnotes', $declarations['noteConfigurationsByClass']['endnote']['masterPageName']);
        $t->same('odf-style-package-provenance-metadata-only', $result['importReport']['manifest']['packageProvenance']['stylePackageProvenance']['byteExposurePolicy']);
        $t->same(false, $result['importReport']['manifest']['packageProvenance']['stylePackageProvenance']['canExposeBytes']);
    },
];
