<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest custom attribute namespace parity.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Custom Attribute Namespace Parity</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest
  xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"
  xmlns:wp="urn:wordpress:content"
  xmlns:audit="urn:wordpress:audit"
  xmlns:alt="urn:wordpress:alt"
  manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" wp:state="canonical" audit:priority="high"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" xmlns:wp="urn:wordpress:asset" wp:state="cover" audit:priority="medium" alt:priority="visual"/>
</manifest:manifest>
XML;

$buildPackage = static fn (string $manifest): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
], 'odt manifest custom attribute namespace parity');

$indexByNamespace = static function (array $summaries): array {
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[(string) $summary['namespaceUri']] = $summary;
    }

    return $indexed;
};

return [
    'carries ODT manifest custom attribute namespace rollups across compact and rich identities' => static function (TestRunner $t) use (
        $buildPackage,
        $manifestXml,
        $indexByNamespace,
    ): void {
        $package = $buildPackage($manifestXml);
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactReview = $compactSummary['manifestReview'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        foreach ([
            'manifestCustomAttributeNamespaceCount',
            'manifestCustomAttributeNamespaceUris',
            'manifestCustomAttributeNamespaceCounts',
            'manifestCustomAttributeNamespaceEntryCounts',
            'manifestCustomAttributeNamespaceFullPaths',
            'manifestCustomAttributeNamespaceParts',
            'manifestCustomAttributeNamespaceAttributeNames',
            'manifestCustomAttributeNamespaceLocalNames',
            'manifestCustomAttributeNamespacePrefixes',
            'manifestCustomAttributeNamespaceSummaries',
        ] as $key) {
            $t->same($compactReview[$key], $richProvenance[$key], "compact and rich agree on {$key}");
            $t->same($compactReview[$key], $compactIdentity[$key], "compact identity carries {$key}");
            $t->same($richProvenance[$key], $richIdentity[$key], "rich identity carries {$key}");
            $t->same($richIdentity[$key], $documentIdentity[$key], "document identity carries {$key}");
        }

        $expectedCounts = [
            'urn:wordpress:alt' => 1,
            'urn:wordpress:asset' => 1,
            'urn:wordpress:audit' => 2,
            'urn:wordpress:content' => 1,
        ];
        $t->same(4, $compactReview['manifestCustomAttributeNamespaceCount']);
        $t->same(array_keys($expectedCounts), $compactReview['manifestCustomAttributeNamespaceUris']);
        $t->same($expectedCounts, $compactReview['manifestCustomAttributeNamespaceCounts']);
        $t->same([
            'urn:wordpress:alt' => 1,
            'urn:wordpress:asset' => 1,
            'urn:wordpress:audit' => 2,
            'urn:wordpress:content' => 1,
        ], $compactReview['manifestCustomAttributeNamespaceEntryCounts']);
        $t->same([
            'urn:wordpress:alt' => ['Pictures/hero.png'],
            'urn:wordpress:asset' => ['Pictures/hero.png'],
            'urn:wordpress:audit' => ['Pictures/hero.png', 'content.xml'],
            'urn:wordpress:content' => ['content.xml'],
        ], $compactReview['manifestCustomAttributeNamespaceParts']);
        $t->same([
            'urn:wordpress:alt' => ['alt:priority'],
            'urn:wordpress:asset' => ['wp:state'],
            'urn:wordpress:audit' => ['audit:priority'],
            'urn:wordpress:content' => ['wp:state'],
        ], $compactReview['manifestCustomAttributeNamespaceAttributeNames']);
        $t->same([
            'urn:wordpress:alt' => ['alt'],
            'urn:wordpress:asset' => ['wp'],
            'urn:wordpress:audit' => ['audit'],
            'urn:wordpress:content' => ['wp'],
        ], $compactReview['manifestCustomAttributeNamespacePrefixes']);

        $summaries = $indexByNamespace($compactReview['manifestCustomAttributeNamespaceSummaries']);
        $t->same(['Pictures/hero.png', 'content.xml'], $summaries['urn:wordpress:audit']['fullPaths']);
        $t->same(['priority'], $summaries['urn:wordpress:audit']['localNames']);
        $t->same(2, $summaries['urn:wordpress:audit']['attributeCount']);
        $t->same(2, $summaries['urn:wordpress:audit']['entryCount']);
        $t->same(['wp:state'], $summaries['urn:wordpress:asset']['attributeNames']);
        $t->same(['wp:state'], $summaries['urn:wordpress:content']['attributeNames']);

        $changedManifest = str_replace(
            'xmlns:wp="urn:wordpress:asset"',
            'xmlns:wp="urn:wordpress:asset-v2"',
            $manifestXml
        );
        $changedCompactIdentity = OpenDocumentPackage::fromPackage($buildPackage($changedManifest))->summarize()['packageIdentity'];
        $changedRichIdentity = (new OdfReader())
            ->readPackage($buildPackage($changedManifest))['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $changedCompactIdentity['identitySha256']);
        $t->same(false, $richIdentity['identitySha256'] === $changedRichIdentity['identitySha256']);
        $t->same(0, $changedCompactIdentity['manifestCustomAttributeNamespaceCounts']['urn:wordpress:asset'] ?? 0);
        $t->same(1, $changedCompactIdentity['manifestCustomAttributeNamespaceCounts']['urn:wordpress:asset-v2']);
    },
];
