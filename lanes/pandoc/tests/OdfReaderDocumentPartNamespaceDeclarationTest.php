<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
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
  xmlns:wpc="urn:wordpress:review:content"
  office:version="1.3"
  wpc:review-state="triaged">
  <office:body><office:text><text:p>Namespace declaration packet.</text:p></office:text></office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:loext="urn:org:documentfoundation:names:experimental:office:xmlns:loext:1.0"
  office:version="1.3"
  loext:profile="interop">
  <office:styles><style:style style:name="BodyText" style:family="paragraph"/></office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  office:version="1.3">
  <office:meta><dc:title>Namespace Declaration Packet</dc:title><meta:keyword>odf</meta:keyword></office:meta>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0"
  xmlns:wps="urn:wordpress:review:settings"
  office:version="1.3"
  wps:source="template-import">
  <office:settings><config:config-item-set config:name="ooo:view-settings"/></office:settings>
</office:document-settings>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'settings.xml', 'data' => $settingsXml],
], 'odt document part namespace declarations');

$indexByPart = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[$item['part']] = $item;
    }

    return $indexed;
};

return [
    'rolls up ODT core XML document part namespace declarations' => static function (TestRunner $t) use ($buildPackage, $indexByPart): void {
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richReport = $richResult['documentPartVersions'];
        $compactReport = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['documentPartVersions'];

        $t->same($richReport, $richResult['document']->attr('manifest')['documentPartVersions']);
        foreach ([$richReport, $compactReport] as $report) {
            $itemsByPart = $indexByPart($report['items']);
            $namespaceItemsByPart = $indexByPart($report['rootNamespaceDeclarationItems']);

            $t->same(4, $report['rootNamespaceDeclarationPartCount']);
            $t->same(12, $report['rootNamespaceDeclarationCount']);
            $t->same([
                'xmlns:config',
                'xmlns:dc',
                'xmlns:loext',
                'xmlns:meta',
                'xmlns:office',
                'xmlns:style',
                'xmlns:text',
                'xmlns:wpc',
                'xmlns:wps',
            ], $report['rootNamespaceDeclarationNames']);

            $t->same(['xmlns:office', 'xmlns:text', 'xmlns:wpc'], $itemsByPart['content.xml']['rootNamespaceDeclarationNames']);
            $t->same('urn:wordpress:review:content', $itemsByPart['content.xml']['rootNamespaceDeclarationMap']['xmlns:wpc']);
            $t->same('triaged', $itemsByPart['content.xml']['rootCustomAttributeMap']['wpc:review-state']);

            $t->same(['xmlns:loext', 'xmlns:office', 'xmlns:style'], $itemsByPart['styles.xml']['rootNamespaceDeclarationNames']);
            $t->same('urn:org:documentfoundation:names:experimental:office:xmlns:loext:1.0', $itemsByPart['styles.xml']['rootNamespaceDeclarationMap']['xmlns:loext']);
            $t->same('interop', $itemsByPart['styles.xml']['rootCustomAttributeMap']['loext:profile']);

            $t->same(['xmlns:dc', 'xmlns:meta', 'xmlns:office'], $itemsByPart['meta.xml']['rootNamespaceDeclarationNames']);
            $t->same('http://purl.org/dc/elements/1.1/', $itemsByPart['meta.xml']['rootNamespaceDeclarationMap']['xmlns:dc']);

            $t->same(['xmlns:config', 'xmlns:office', 'xmlns:wps'], $itemsByPart['settings.xml']['rootNamespaceDeclarationNames']);
            $t->same('urn:wordpress:review:settings', $itemsByPart['settings.xml']['rootNamespaceDeclarationMap']['xmlns:wps']);
            $t->same('template-import', $itemsByPart['settings.xml']['rootCustomAttributeMap']['wps:source']);

            $t->same(3, $namespaceItemsByPart['content.xml']['rootNamespaceDeclarationCount']);
            $t->same('urn:wordpress:review:content', $namespaceItemsByPart['content.xml']['rootNamespaceDeclarationMap']['xmlns:wpc']);
            $t->same(['wpc:review-state' => 'triaged'], $namespaceItemsByPart['content.xml']['rootCustomAttributeMap']);
            $t->same(3, $namespaceItemsByPart['settings.xml']['rootNamespaceDeclarationCount']);
            $t->same('urn:wordpress:review:settings', $namespaceItemsByPart['settings.xml']['rootNamespaceDeclarationMap']['xmlns:wps']);
            $t->same(['wps:source' => 'template-import'], $namespaceItemsByPart['settings.xml']['rootCustomAttributeMap']);
        }
    },
];
