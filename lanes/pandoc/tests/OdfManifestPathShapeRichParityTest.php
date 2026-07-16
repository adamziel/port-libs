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
      <text:p>Manifest path shape parity.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="PathShapeBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Path Shape Parity</dc:title>
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
], 'odt manifest path shape parity');

return [
    'carries rich ODT manifest path shape rollups with compact parity' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactReview = $compactSummary['manifestReview'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        foreach ([$richProvenance, $richIdentity, $documentIdentity] as $handoff) {
            $t->same($compactReview['manifestPathKindCounts'], $handoff['manifestPathKindCounts']);
            $t->same($compactReview['manifestTopLevelSegmentCounts'], $handoff['manifestTopLevelSegmentCounts']);
            $t->same($compactReview['manifestPathExtensionCounts'], $handoff['manifestPathExtensionCounts']);
            $t->same(8, count($handoff['manifestPathShapeItems']));
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

        $t->same(['directory' => 1, 'file' => 6, 'root' => 1], $richProvenance['manifestPathKindCounts']);
        $t->same([
            'Configurations2' => 1,
            'Notes' => 1,
            'Pictures' => 2,
            'content.xml' => 1,
            'meta.xml' => 1,
            'styles.xml' => 1,
        ], $richProvenance['manifestTopLevelSegmentCounts']);
        $t->same(['png' => 2, 'xml' => 4], $richProvenance['manifestPathExtensionCounts']);

        $hero = $orderByPath['Pictures/HERO.PNG?cache=1#cover'];
        $t->same('Pictures/HERO.PNG', $hero['part']);
        $t->same('Pictures/HERO.PNG', $hero['partReference']);
        $t->same('?cache=1#cover', $hero['partSuffix']);
        $t->same('cache=1', $hero['partQuery']);
        $t->same('cover', $hero['partFragment']);
        $t->same('HERO.PNG', $hero['pathShape']['basename']);
        $t->same('png', $hero['pathShape']['extension']);
        $t->same(['Pictures', 'HERO.PNG'], $hero['pathShape']['segments']);
        $t->same(['first', 'last'], array_column($hero['pathShape']['pathSegmentPositionReviews'], 'position'));
        $t->same('HERO.PNG', $shapeByPath['Pictures/HERO.PNG?cache=1#cover']['basename']);

        $encoded = $orderByPath['Pictures/space%20hero.png'];
        $t->same('Pictures/space hero.png', $encoded['part']);
        $t->same(true, $encoded['uriEncodedPartReference']);
        $t->same('space%20hero.png', $encoded['pathShape']['basename']);
        $t->same('space hero.png', $encoded['packagePathShape']['basename']);
        $t->same(true, $shapeByPath['Pictures/space%20hero.png']['uriEncodedPartReference']);
        $t->same('space hero.png', $partByPath['Pictures/space hero.png']['manifestPackagePathShape']['basename']);
        $t->same('space%20hero.png', $partByPath['Pictures/space hero.png']['manifestPathShape']['basename']);

        $notes = $orderByPath['Notes/'];
        $t->same('directory', $notes['pathShape']['kind']);
        $t->same(1, $notes['pathShape']['directorySegmentCount']);
        $t->same(['only'], array_column($notes['pathShape']['pathSegmentPositionReviews'], 'position'));

        $identityEntries = [];
        foreach ($richIdentity['manifestEntries'] as $item) {
            $identityEntries[(string) $item['fullPath']] = $item;
        }
        $t->same($hero['pathShape'], $identityEntries['Pictures/HERO.PNG?cache=1#cover']['pathShape']);
        $t->same($encoded['packagePathShape'], $identityEntries['Pictures/space%20hero.png']['packagePathShape']);
    },
];
