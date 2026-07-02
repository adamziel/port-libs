<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/Pictures/preview.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Chart object package XML root review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$chartContentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar"/>
    </office:chart>
  </office:body>
</office:document-content>
XML;

$chartStylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="chart-title" style:family="chart"/>
  </office:styles>
</office:document-styles>
XML;

$chartSettingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0">
  <office:settings>
    <config:config-item-set config:name="view-settings"/>
  </office:settings>
</office:document-settings>
XML;

$buildPackage = static function () use ($manifestXml, $contentXml, $chartContentXml, $chartStylesXml, $chartSettingsXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
        ['name' => 'content.xml', 'data' => $contentXml],
        ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'Object Chart/content.xml', 'data' => $chartContentXml, 'compressionMethod' => 0],
        ['name' => 'Object Chart/styles.xml', 'data' => $chartStylesXml, 'compressionMethod' => 0],
        ['name' => 'Object Chart/Pictures/preview.png', 'data' => 'PREVIEWPNG', 'compressionMethod' => 0],
        ['name' => 'Object Chart/settings.xml', 'data' => $chartSettingsXml, 'compressionMethod' => 0],
    ], 'odt embedded object xml root review');
};

return [
    'carries embedded object XML root metadata without exposing object bytes' => static function (TestRunner $t) use ($buildPackage): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $objects = $provenance['embeddedObjectPackages'];
        $chart = $objects['byRootPart']['Object Chart/'];
        $containedByPart = [];
        foreach ($chart['containedParts'] as $item) {
            $containedByPart[$item['part']] = $item;
        }

        $t->same(1, $objects['count']);
        $t->same(4, $chart['containedPartCount']);
        $t->same(3, $chart['containedXmlRootElementPartCount']);
        $t->same(3, $objects['containedXmlRootElementPartCount']);
        $t->same([
            'Object Chart/content.xml',
            'Object Chart/settings.xml',
            'Object Chart/styles.xml',
        ], $chart['containedXmlRootElementPartNames']);
        $t->same([
            'office:document-content',
            'office:document-settings',
            'office:document-styles',
        ], $chart['containedXmlRootElementNames']);
        $t->same([
            'office:document-content' => 1,
            'office:document-settings' => 1,
            'office:document-styles' => 1,
        ], $objects['containedXmlRootElementNameCounts']);
        $t->same([
            'urn:oasis:names:tc:opendocument:xmlns:office:1.0',
        ], $chart['containedXmlRootElementNamespaceUris']);
        $t->same([
            'urn:oasis:names:tc:opendocument:xmlns:office:1.0' => 3,
        ], $objects['containedXmlRootElementNamespaceUriCounts']);
        $t->same(false, $chart['containedXmlRootElementsTruncated']);
        $t->same(false, $objects['containedXmlRootElementsTruncated']);
        $t->same([
            'Object Chart/content.xml',
            'Object Chart/settings.xml',
            'Object Chart/styles.xml',
        ], array_column($chart['containedXmlRootElements'], 'part'));
        $t->same('office:document-content', $containedByPart['Object Chart/content.xml']['xmlRootElementName']);
        $t->same('document-content', $containedByPart['Object Chart/content.xml']['xmlRootElementLocalName']);
        $t->same('/office:document-content', $containedByPart['Object Chart/content.xml']['xmlRootElementPath']);
        $t->same(['xmlns:chart', 'xmlns:office'], $containedByPart['Object Chart/content.xml']['xmlRootElementNamespaceDeclarationNames']);
        $t->same('office:document-settings', $containedByPart['Object Chart/settings.xml']['xmlRootElementName']);
        $t->same(true, $containedByPart['Object Chart/settings.xml']['xmlHasRootElement']);
        $t->same(false, $containedByPart['Object Chart/Pictures/preview.png']['xmlHasRootElement']);
        $t->same(null, $containedByPart['Object Chart/Pictures/preview.png']['xmlRootElementName']);
        $t->same(false, $containedByPart['Object Chart/content.xml']['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $provenance['parts']['Object Chart/content.xml']['byteExposurePolicy']);
        $t->same('embedded-object-package-bytes-blocked', $containedByPart['Object Chart/content.xml']['byteExposurePolicy']);
        $t->same(['odf-embedded-object-package-undeclared-contained-part'], $chart['issues']);
    },
];
