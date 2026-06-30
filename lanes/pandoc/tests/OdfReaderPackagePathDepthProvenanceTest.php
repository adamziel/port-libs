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
  <manifest:file-entry manifest:full-path="Pictures/review/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Extras/depth/cache/preview.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Depth review packet.</text:p>
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

$scriptXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review" script:language="StarBasic">Sub Review
End Sub</script:module>
XML;

$deepPart = 'Extras/depth/cache/preview.bin';
$packageParts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'Pictures/review/hero.png', 'data' => 'PNGDATA'],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
    ['name' => $deepPart, 'data' => 'PREVIEW-CACHE-BYTES', 'compressionMethod' => 0],
    ['name' => 'Notes/private/review.txt', 'data' => 'private review note', 'compressionMethod' => 0],
];

$package = static fn (): ZipPackage => ZipPackage::fromParts($packageParts, 'odt package path depth review');

return [
    'summarizes ODT package part path depths across compact and rich readers' => static function (TestRunner $t) use ($package, $deepPart): void {
        $compact = OpenDocumentPackage::fromPackage($package())->summarize();
        $compactInventory = $compact['packageInventory'];
        $compactParts = $compactInventory['parts'];
        $compactDepths = [];
        foreach ($compactInventory['partPathDepths'] as $depth) {
            $compactDepths[$depth['pathSegmentCount']] = $depth;
        }

        $t->same(4, $compactInventory['partPathDepthCount']);
        $t->same(4, $compactInventory['maxPartPathSegmentCount']);
        $t->same(3, $compactInventory['maxPartDirectoryDepth']);
        $t->same([$deepPart], $compactInventory['deepestPartNames']);
        $t->same($deepPart, $compactInventory['deepestParts'][0]['partName']);
        $t->same('Extras/depth/cache', $compactInventory['deepestParts'][0]['directory']);
        $t->same('preview.bin', $compactInventory['deepestParts'][0]['baseName']);
        $t->same(['Extras', 'depth', 'cache', 'preview.bin'], $compactParts[$deepPart]['pathSegments']);
        $t->same(4, $compactParts[$deepPart]['pathSegmentCount']);
        $t->same('Extras/depth/cache', $compactParts[$deepPart]['directory']);
        $t->same(3, $compactParts[$deepPart]['directoryDepth']);
        $t->same('preview.bin', $compactParts[$deepPart]['baseName']);
        $t->same(['mimetype'], $compactParts['mimetype']['pathSegments']);
        $t->same('/', $compactParts['mimetype']['directory']);
        $t->same(0, $compactParts['mimetype']['directoryDepth']);
        $t->same(1, $compactDepths[4]['partCount']);
        $t->same([$deepPart], $compactDepths[4]['partNames']);
        $t->same(['Extras/depth/cache'], $compactDepths[4]['directories']);
        $t->same(1, $compactDepths[4]['roleCounts']['manifest-declared']);
        $t->same($deepPart, $compact['packageIdentity']['deepestPartNames'][0]);
        $t->same(4, $compact['packageIdentity']['maxPartPathSegmentCount']);

        $rich = (new OdfReader())->readPackage($package());
        $provenance = $rich['importReport']['manifest']['packageProvenance'];
        $richParts = $provenance['parts'];
        $richDepths = [];
        foreach ($provenance['partPathDepths'] as $depth) {
            $richDepths[$depth['pathSegmentCount']] = $depth;
        }
        $identityEntries = [];
        foreach ($provenance['packageIdentity']['packageEntries'] as $entry) {
            $identityEntries[$entry['part']] = $entry;
        }

        $t->same($provenance, $rich['document']->attr('manifest')['packageProvenance']);
        $t->same(4, $provenance['partPathDepthCount']);
        $t->same(4, $provenance['maxPartPathSegmentCount']);
        $t->same(3, $provenance['maxPartDirectoryDepth']);
        $t->same([$deepPart], $provenance['deepestPartNames']);
        $t->same($deepPart, $provenance['deepestParts'][0]['partName']);
        $t->same(['Extras', 'depth', 'cache', 'preview.bin'], $richParts[$deepPart]['pathSegments']);
        $t->same(4, $richParts[$deepPart]['pathSegmentCount']);
        $t->same('Extras/depth/cache', $richParts[$deepPart]['directory']);
        $t->same(3, $richParts[$deepPart]['directoryDepth']);
        $t->same('preview.bin', $richParts[$deepPart]['baseName']);
        $t->same(1, $richDepths[4]['partCount']);
        $t->same([$deepPart], $richDepths[4]['partNames']);
        $t->same(['Extras/depth/cache'], $richDepths[4]['directories']);
        $t->same(1, $richDepths[4]['roleCounts']['manifest-declared']);
        $t->same(4, $provenance['packageIdentity']['partPathDepthCount']);
        $t->same(4, $provenance['packageIdentity']['maxPartPathSegmentCount']);
        $t->same([$deepPart], $provenance['packageIdentity']['deepestPartNames']);
        $t->same(['Extras', 'depth', 'cache', 'preview.bin'], $identityEntries[$deepPart]['pathSegments']);
        $t->same('Extras/depth/cache', $identityEntries[$deepPart]['directory']);
        $t->same(3, $identityEntries[$deepPart]['directoryDepth']);
    },
];
