<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest
  xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"
  xmlns:loext="urn:libreoffice:manifest"
  xmlns:wp="urn:review:root"
  manifest:version="1.3"
  wp:packet="namespace-rollup">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" xmlns:wp="urn:review:content" wp:state="canonical"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" xmlns:media="urn:review:media" media:role="cover"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest namespace rollup.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="NamespaceBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Namespace Rollup</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
], 'odt manifest namespace declaration uri rollup');

$indexByScope = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $key = (string) $item['scope'] . ':' . (string) ($item['fullPath'] ?? '(manifest-root)');
        $indexed[$key] = $item;
    }

    return $indexed;
};

return [
    'rolls up ODT manifest namespace declaration URI usage across compact and rich package metadata' => static function (TestRunner $t) use ($buildPackage, $indexByScope): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactReview = $compactSummary['manifestReview'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $expectedUris = [
            'urn:libreoffice:manifest',
            OpenDocumentPackage::MANIFEST_NAMESPACE,
            'urn:review:content',
            'urn:review:media',
            'urn:review:root',
        ];
        $expectedCounts = [
            'urn:libreoffice:manifest' => 6,
            OpenDocumentPackage::MANIFEST_NAMESPACE => 6,
            'urn:review:content' => 1,
            'urn:review:media' => 1,
            'urn:review:root' => 5,
        ];
        $expectedNamesByUri = [
            'urn:libreoffice:manifest' => ['xmlns:loext'],
            OpenDocumentPackage::MANIFEST_NAMESPACE => ['xmlns:manifest'],
            'urn:review:content' => ['xmlns:wp'],
            'urn:review:media' => ['xmlns:media'],
            'urn:review:root' => ['xmlns:wp'],
        ];

        foreach ([$compactReview, $compactIdentity, $richProvenance, $richIdentity, $documentIdentity] as $handoff) {
            $t->same(6, $handoff['manifestNamespaceDeclarationScopeCount']);
            $t->same(5, $handoff['manifestNamespaceDeclarationUriCount']);
            $t->same($expectedUris, $handoff['manifestNamespaceDeclarationUris']);
            $t->same($expectedCounts, $handoff['manifestNamespaceDeclarationUriCounts']);
            $t->same($expectedNamesByUri, $handoff['manifestNamespaceDeclarationNamesByUri']);
        }

        $t->same($compactReview['manifestNamespaceDeclarationUriSummaries'], $richProvenance['manifestNamespaceDeclarationUriSummaries']);
        $t->same($compactReview['manifestNamespaceDeclarationScopeItems'], $richProvenance['manifestNamespaceDeclarationScopeItems']);
        $t->same($richIdentity['manifestNamespaceDeclarationScopeItems'], $documentIdentity['manifestNamespaceDeclarationScopeItems']);

        $scopeItems = $indexByScope($richProvenance['manifestNamespaceDeclarationScopeItems']);
        $t->same(3, $scopeItems['manifest-root:(manifest-root)']['namespaceDeclarationCount']);
        $t->same('urn:review:content', $scopeItems['manifest-file-entry:content.xml']['namespaceDeclarationMap']['xmlns:wp']);
        $t->same(4, $scopeItems['manifest-file-entry:Pictures/hero.png']['namespaceDeclarationCount']);
        $t->same(
            ['xmlns:loext', 'xmlns:manifest', 'xmlns:media', 'xmlns:wp'],
            $scopeItems['manifest-file-entry:Pictures/hero.png']['namespaceDeclarationNames']
        );
    },
];
