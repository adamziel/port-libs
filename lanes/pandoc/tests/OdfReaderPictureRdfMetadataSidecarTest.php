<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$rdfXml = <<<'XML'
<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <rdf:Description rdf:about="Pictures/hero.png">
    <dc:title>Hero review provenance</dc:title>
    <dc:format>image/png</dc:format>
  </rdf:Description>
</rdf:RDF>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Picture RDF metadata package sidecars.</text:p>
    </office:text>
  </office:body>
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

$manifestXml = sprintf(
    <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/review/manifest.rdf" manifest:media-type="application/rdf+xml" manifest:size="%d"/>
</manifest:manifest>
XML,
    strlen($rdfXml)
);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/review/manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
], 'odt picture rdf metadata sidecar');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $indexed[$value] = $item;
        }
    }

    return $indexed;
};

return [
    'keeps Pictures RDF metadata sidecars out of ODT media handoff' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $rdfXml
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $readerManifestByPart = $indexBy($result['manifest'], 'part');
        $compactReviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactInventory = $compactSummary['packageInventory'];

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'packagePath'));
        $t->same(1, $result['rdfMetadata']['partCount']);
        $t->same(1, $compactSummary['rdfMetadata']['partCount']);
        $t->same('Pictures/review/manifest.rdf', $result['rdfMetadata']['parts'][0]['part']);
        $t->same('Pictures/review/manifest.rdf', $compactSummary['rdfMetadata']['parts'][0]['part']);

        $readerRdfManifest = $readerManifestByPart['Pictures/review/manifest.rdf'];
        $compactRdfReview = $compactReviewByPath['Pictures/review/manifest.rdf'];
        $compactRdfInventory = $compactInventory['parts']['Pictures/review/manifest.rdf'];

        $t->same(true, $readerRdfManifest['rdfMetadataPart']);
        $t->same(false, $readerRdfManifest['canExposeBytes']);
        $t->same('rdf-metadata-bytes-blocked', $readerRdfManifest['byteExposurePolicy']);
        $t->same(true, $compactRdfReview['rdfMetadataPart']);
        $t->same(false, $compactRdfReview['canExposeBytes']);
        $t->same(null, $compactRdfReview['byteLength']);
        $t->same(strlen($rdfXml), $compactRdfReview['storedByteLength']);
        $t->same('rdf-metadata-bytes-blocked', $compactRdfReview['byteExposurePolicy']);
        $t->same('rdf', $compactRdfReview['manifestMediaFamily']);
        $t->same(['rdf-metadata', 'manifest-declared'], $compactRdfInventory['roles']);
        $t->same(true, $compactRdfInventory['rdfMetadataPart']);
        $t->same(false, $compactRdfInventory['canExposeBytes']);
        $t->same('rdf-metadata-bytes-blocked', $compactRdfInventory['byteExposurePolicy']);

        $t->same(1, $compactSummary['manifestReview']['rdfMetadataPartCount']);
        $t->same(1, $compactInventory['rdfMetadataPartCount']);
        $t->same(1, $compactInventory['roleCounts']['rdf-metadata']);
        $t->same(0, $compactInventory['undeclaredEntryCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Picture RDF metadata package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, 'Hero review provenance'));
    },
];
