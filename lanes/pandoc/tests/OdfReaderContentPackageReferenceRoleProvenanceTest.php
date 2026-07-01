<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$mathObject = <<<'XML'
<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">
  <mrow><mi>x</mi><mo>=</mo><mn>1</mn></mrow>
</math>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Package references <draw:frame draw:name="Declared image"><draw:image xlink:href="Pictures/hero.png"><svg:desc>Declared image</svg:desc></draw:image></draw:frame> <draw:frame draw:name="Undeclared image"><draw:image xlink:href="Pictures/orphan.png"><svg:desc>Undeclared image</svg:desc></draw:image></draw:frame> <draw:frame draw:name="Formula"><draw:object xlink:href="./Object%201"/></draw:frame> <draw:frame draw:name="OLE"><draw:object-ole xlink:href="./Object%20OLE"/></draw:frame>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Object%201/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Object%201/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20OLE/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>
</manifest:manifest>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/orphan.png', 'data' => 'ORPHANPNG', 'compressionMethod' => 0],
    ['name' => 'Object 1/content.xml', 'data' => $mathObject, 'compressionMethod' => 0],
    ['name' => 'Object OLE/content.xml', 'data' => '<office:document/>', 'compressionMethod' => 0],
], 'odf content package reference role provenance');

return [
    'carries package role provenance on ODT content package references' => static function (TestRunner $t) use ($buildPackage): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $report = $result['contentPackageReferences'];
        $itemsByPart = [];
        foreach ($report['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }

        $t->same($report, $result['document']->attr('manifest')['contentPackageReferences']);
        $t->same($report, $result['importReport']['manifest']['contentPackageReferences']);
        $t->same($report, $result['importReport']['content']['packageReferences']);
        $t->same(4, $report['count']);
        $t->same(2, $report['embeddedObjectReferenceCount']);
        $t->same(2, $report['mediaResourceReferenceCount']);
        $t->same(2, $report['packageRolePrecedenceReferenceCount']);
        $t->same(['image' => 2], $report['mediaFamilyCounts']);
        $t->same([
            'directory-entry-no-bytes' => 1,
            'embedded-object-package-bytes-blocked' => 1,
            'package-bytes-exposable' => 2,
        ], $report['byteExposurePolicyCounts']);

        $declaredImage = $itemsByPart['Pictures/hero.png'];
        $t->same('draw:image', $declaredImage['referenceRole']);
        $t->same(true, $declaredImage['mediaResource']);
        $t->same('image', $declaredImage['declaredMediaFamily']);
        $t->same('image', $declaredImage['packagePathMediaFamily']);
        $t->same('image', $declaredImage['effectiveMediaFamily']);
        $t->same(true, $declaredImage['canExposeBytes']);
        $t->same('package-bytes-exposable', $declaredImage['byteExposurePolicy']);

        $undeclaredImage = $itemsByPart['Pictures/orphan.png'];
        $t->same(false, $undeclaredImage['declaredInManifest']);
        $t->same(true, $undeclaredImage['mediaResource']);
        $t->same('image', $undeclaredImage['packagePathMediaFamily']);
        $t->same('image', $undeclaredImage['effectiveMediaFamily']);
        $t->same(true, $undeclaredImage['exists']);
        $t->same(true, $undeclaredImage['canExposeBytes']);
        $t->same('package-bytes-exposable', $undeclaredImage['byteExposurePolicy']);

        $mathObject = $itemsByPart['Object 1/content.xml'];
        $t->same('draw:object-mathml', $mathObject['referenceRole']);
        $t->same(true, $mathObject['embeddedObjectPackagePart']);
        $t->same(false, $mathObject['embeddedObjectRoot']);
        $t->same(true, $mathObject['embeddedObjectContainedPart']);
        $t->same('Object 1/', $mathObject['embeddedObjectRootPart']);
        $t->same('Object 1', $mathObject['embeddedObjectPath']);
        $t->same('formula', $mathObject['embeddedObjectType']);
        $t->same(['embedded-object-package'], $mathObject['packageRolePrecedence']);
        $t->same(false, $mathObject['canExposeBytes']);
        $t->same('embedded-object-package-bytes-blocked', $mathObject['byteExposurePolicy']);

        $oleObject = $itemsByPart['Object OLE/'];
        $t->same('draw:object-ole', $oleObject['referenceRole']);
        $t->same(true, $oleObject['embeddedObjectPackagePart']);
        $t->same(true, $oleObject['embeddedObjectRoot']);
        $t->same(false, $oleObject['embeddedObjectContainedPart']);
        $t->same('Object OLE/', $oleObject['embeddedObjectRootPart']);
        $t->same('Object OLE', $oleObject['embeddedObjectPath']);
        $t->same('ole', $oleObject['objectType']);
        $t->same('spreadsheet', $oleObject['embeddedObjectType']);
        $t->same(['embedded-object-package'], $oleObject['packageRolePrecedence']);
        $t->same(false, $oleObject['canExposeBytes']);
        $t->same('directory-entry-no-bytes', $oleObject['byteExposurePolicy']);
    },
];
