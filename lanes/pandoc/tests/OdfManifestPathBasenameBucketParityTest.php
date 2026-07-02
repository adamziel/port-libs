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
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG?cache=1#cover" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/space%20hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Notes/"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest path basename buckets.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="ManifestBasenameBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Path Basename Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/space hero.png', 'data' => 'SPACEDPNG', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => 'Notes/', 'data' => '', 'compressionMethod' => 0],
], 'odt manifest path basename bucket parity');

return [
    'carries ODT manifest path basename buckets through compact and rich provenance' => static function (TestRunner $t) use ($buildPackage): void {
        $expectedBaseNames = [
            'HERO.PNG' => 1,
            'Notes' => 1,
            'content.xml' => 1,
            'current.xml' => 1,
            'meta.xml' => 1,
            'space%20hero.png' => 1,
            'styles.xml' => 1,
        ];
        $expectedBaseNameStems = [
            'HERO' => 1,
            'Notes' => 1,
            'content' => 1,
            'current' => 1,
            'meta' => 1,
            'space%20hero' => 1,
            'styles' => 1,
        ];

        $compactReview = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['manifestReview'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $t->same($expectedBaseNames, $compactReview['manifestPathBaseNameCounts']);
        $t->same($expectedBaseNameStems, $compactReview['manifestPathBaseNameStemCounts']);

        foreach ([$richProvenance, $richIdentity, $documentIdentity] as $handoff) {
            $t->same($compactReview['manifestPathBaseNameCounts'], $handoff['manifestPathBaseNameCounts']);
            $t->same($compactReview['manifestPathBaseNameStemCounts'], $handoff['manifestPathBaseNameStemCounts']);
        }

        $orderByPath = [];
        foreach ($richProvenance['manifestFileEntryOrder'] as $item) {
            $orderByPath[(string) $item['fullPath']] = $item;
        }
        $shapeByPath = [];
        foreach ($richProvenance['manifestPathShapeItems'] as $item) {
            $shapeByPath[(string) $item['fullPath']] = $item;
        }
        $partByPath = $richProvenance['parts'];

        $hero = $orderByPath['Pictures/HERO.PNG?cache=1#cover'];
        $t->same('HERO.PNG', $hero['pathShape']['basename']);
        $t->same('HERO', $hero['pathShape']['basenameStem']);
        $t->same('hero', $hero['pathShape']['caseFoldBasenameStem']);
        $t->same('HERO', $shapeByPath['Pictures/HERO.PNG?cache=1#cover']['basenameStem']);
        $t->same('hero', $shapeByPath['Pictures/HERO.PNG?cache=1#cover']['caseFoldBasenameStem']);

        $encoded = $orderByPath['Pictures/space%20hero.png'];
        $t->same('space%20hero.png', $encoded['pathShape']['basename']);
        $t->same('space%20hero', $encoded['pathShape']['basenameStem']);
        $t->same('space hero.png', $encoded['packagePathShape']['basename']);
        $t->same('space hero', $encoded['packagePathShape']['basenameStem']);
        $t->same('space hero', $partByPath['Pictures/space hero.png']['manifestPackagePathShape']['basenameStem']);
        $t->same('space%20hero', $partByPath['Pictures/space hero.png']['manifestPathShape']['basenameStem']);

        $notes = $orderByPath['Notes/'];
        $t->same('Notes', $notes['pathShape']['basename']);
        $t->same('Notes', $notes['pathShape']['basenameStem']);
        $t->same('notes', $notes['pathShape']['caseFoldBasenameStem']);
    },
];
