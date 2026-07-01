<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="thumbnails/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="objects/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Objects/Pictures/readme.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Objects/pictures/icon.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package identity lookup maps.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="ReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package Identity Lookup Maps</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'thumbnails/hero.png', 'data' => 'thumb', 'compressionMethod' => 0],
    ['name' => 'objects/content.xml', 'data' => '<object/>', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures/readme.txt', 'data' => 'readme', 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/icon.png', 'data' => 'icon', 'compressionMethod' => 0],
], 'odt package identity lookup maps');

return [
    'carries ODT package basename lookup maps through compact and rich identities' => static function (TestRunner $t) use ($buildPackage): void {
        $compactIdentity = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richIdentity = $richResult['importReport']['manifest']['packageProvenance']['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $identities = [$compactIdentity, $richIdentity, $documentIdentity];
        foreach ($identities as $identity) {
            $t->same(['content.xml', 'objects/content.xml'], $identity['entryNamesByPackageBasename']['content.xml']);
            $t->same(['Pictures/HERO.PNG', 'thumbnails/hero.png'], $identity['entryNamesByPackageCaseFoldedBasename']['hero.png']);
            $t->same(
                ['Objects/Pictures/', 'Objects/Pictures/readme.txt', 'Pictures/', 'Pictures/HERO.PNG'],
                $identity['entryNamesByPackageDirectoryBaseName']['Pictures']
            );
            $t->same(
                [
                    'Objects/Pictures/',
                    'Objects/Pictures/readme.txt',
                    'Objects/pictures/',
                    'Objects/pictures/icon.png',
                    'Pictures/',
                    'Pictures/HERO.PNG',
                ],
                $identity['entryNamesByPackageCaseFoldDirectoryBaseName']['pictures']
            );
        }
    },
];
