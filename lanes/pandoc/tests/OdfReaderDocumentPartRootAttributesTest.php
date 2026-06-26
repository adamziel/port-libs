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
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:wp="urn:wordpress:review:odf"
  office:version="1.3"
  wp:review-state="triaged"
  xml:lang="en-US">
  <office:body><office:text><text:p>Root attrs packet.</text:p></office:text></office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:meta/>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0"
  xmlns:wp="urn:wordpress:review:settings"
  office:version="1.3"
  wp:package-origin="template-import">
  <office:settings>
    <config:config-item-set config:name="ooo:view-settings"/>
  </office:settings>
</office:document-settings>
XML;

return [
    'preserves ODT XML package part root custom attributes for provenance review' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml, $settingsXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
        ], 'odt document part root attributes'));
        $report = $result['importReport']['manifest']['documentPartVersions'];
        $itemsByPart = [];
        foreach ($report['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $contentAttributes = [];
        foreach ($itemsByPart['content.xml']['rootAttributes'] as $attribute) {
            $contentAttributes[$attribute['name']] = $attribute;
        }
        $settingsAttributes = [];
        foreach ($itemsByPart['settings.xml']['rootAttributes'] as $attribute) {
            $settingsAttributes[$attribute['name']] = $attribute;
        }
        $customItemsByPart = [];
        foreach ($report['rootCustomAttributeItems'] as $item) {
            $customItemsByPart[$item['part']] = $item;
        }

        $t->same($report, $result['document']->attr('manifest')['documentPartVersions']);
        $t->same($report, $result['documentPartVersions']);
        $t->same(2, $report['rootCustomAttributePartCount']);
        $t->same(3, $report['rootCustomAttributeCount']);
        $t->same(['wp:package-origin', 'wp:review-state', 'xml:lang'], $report['rootCustomAttributeNames']);

        $t->same(['office:version', 'wp:review-state', 'xml:lang'], $itemsByPart['content.xml']['rootAttributeNames']);
        $t->same(2, $itemsByPart['content.xml']['rootCustomAttributeCount']);
        $t->same([
            'wp:review-state' => 'triaged',
            'xml:lang' => 'en-US',
        ], $itemsByPart['content.xml']['rootCustomAttributeMap']);
        $t->same(true, $contentAttributes['office:version']['structural']);
        $t->same(false, $contentAttributes['wp:review-state']['structural']);
        $t->same('urn:wordpress:review:odf', $contentAttributes['wp:review-state']['namespaceUri']);
        $t->same('urn:wordpress:review:odf', $itemsByPart['content.xml']['rootNamespaceDeclarationMap']['xmlns:wp']);

        $t->same(['office:version', 'wp:package-origin'], $itemsByPart['settings.xml']['rootAttributeNames']);
        $t->same(1, $itemsByPart['settings.xml']['rootCustomAttributeCount']);
        $t->same(['wp:package-origin' => 'template-import'], $itemsByPart['settings.xml']['rootCustomAttributeMap']);
        $t->same(true, $settingsAttributes['office:version']['structural']);
        $t->same(false, $settingsAttributes['wp:package-origin']['structural']);
        $t->same('urn:wordpress:review:settings', $settingsAttributes['wp:package-origin']['namespaceUri']);
        $t->same('urn:wordpress:review:settings', $customItemsByPart['settings.xml']['rootNamespaceDeclarationMap']['xmlns:wp']);
        $t->same('template-import', $customItemsByPart['settings.xml']['rootCustomAttributeMap']['wp:package-origin']);
    },
];
