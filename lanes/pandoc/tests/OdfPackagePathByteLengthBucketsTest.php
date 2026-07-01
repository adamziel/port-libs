<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes ODT package path byte-length buckets across compact and rich package review' => static function (TestRunner $t): void {
        $mediumPath = 'VeryLongPackagePath/' . str_repeat('a', 64) . '.bin';
        $longPath = 'Extensions/' . str_repeat('verylongsegment/', 8) . 'payload.bin';
        $manifestXml = '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">'
            . '<manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>'
            . '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>'
            . '<manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="' . $mediumPath . '" manifest:media-type="application/octet-stream"/>'
            . '<manifest:file-entry manifest:full-path="' . $longPath . '" manifest:media-type="application/octet-stream"/>'
            . '</manifest:manifest>';
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Package path byte buckets.</text:p>
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
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Package Path Byte Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<config/>', 'compressionMethod' => 0],
            ['name' => $mediumPath, 'data' => 'MEDIUM-PATH', 'compressionMethod' => 0],
            ['name' => $longPath, 'data' => 'LONG-PATH', 'compressionMethod' => 0],
            ['name' => 'Notes/private.txt', 'data' => 'PRIVATE', 'compressionMethod' => 0],
        ], 'odt path byte length review');

        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compact['packageInventory'];
        $compactIdentity = $compact['packageIdentity'];
        $readerResult = (new OdfReader())->readPackage($package);
        $provenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $expectedCounts = [
            '1-15' => 4,
            '16-31' => 3,
            '32-63' => 1,
            '64-127' => 1,
            '128-plus' => 1,
        ];

        $t->same($expectedCounts, $compactInventory['packagePathByteLengthBucketCounts']);
        $t->same($expectedCounts, $compactIdentity['packagePathByteLengthBucketCounts']);
        $t->same($expectedCounts, $provenance['packagePathByteLengthBucketCounts']);
        $t->same($expectedCounts, $identity['packagePathByteLengthBucketCounts']);
        $t->same(5, $provenance['packagePathByteLengthBucketCount']);
        $t->same(5, $identity['packagePathByteLengthBucketCount']);
        $t->same(strlen($longPath), $provenance['maxPackagePathByteLength']);
        $t->same(strlen($longPath), $identity['maxPackagePathByteLength']);
        $t->same([$longPath], $provenance['longestPackagePathNames']);
        $t->same([$longPath], $identity['longestPackagePathNames']);
        $t->same($provenance, $readerResult['document']->attr('manifest')['packageProvenance']);

        $readerBuckets = [];
        foreach ($provenance['packagePathByteLengthBuckets'] as $bucket) {
            $readerBuckets[$bucket['bucket']] = $bucket;
        }

        $t->same(['content.xml', 'meta.xml', 'mimetype', 'styles.xml'], $readerBuckets['1-15']['partNames']);
        $t->same(8, $readerBuckets['1-15']['minPathByteLength']);
        $t->same(11, $readerBuckets['1-15']['maxPathByteLength']);
        $t->same(['META-INF/manifest.xml', 'Notes/private.txt', 'Pictures/hero.png'], $readerBuckets['16-31']['partNames']);
        $t->same('Configurations2/accelerator/current.xml', $readerBuckets['32-63']['longestPart']['part']);
        $t->same($mediumPath, $readerBuckets['64-127']['longestPart']['part']);
        $t->same($longPath, $readerBuckets['128-plus']['longestPart']['part']);
        $t->same(strlen($longPath), $readerBuckets['128-plus']['longestPart']['pathByteLength']);
        $t->same('128-plus', $readerBuckets['128-plus']['longestPart']['pathByteLengthBucket']);

        $readerParts = $provenance['parts'];
        $compactParts = $compactInventory['parts'];
        $identityParts = [];
        foreach ($identity['packageEntries'] as $entry) {
            $identityParts[$entry['part']] = $entry;
        }
        $compactIdentityParts = [];
        foreach ($compactIdentity['packageEntries'] as $entry) {
            $compactIdentityParts[$entry['path']] = $entry;
        }

        $t->same(strlen('content.xml'), $readerParts['content.xml']['pathByteLength']);
        $t->same('1-15', $readerParts['content.xml']['pathByteLengthBucket']);
        $t->same(strlen($mediumPath), $readerParts[$mediumPath]['pathByteLength']);
        $t->same('64-127', $readerParts[$mediumPath]['pathByteLengthBucket']);
        $t->same(strlen($longPath), $readerParts[$longPath]['pathByteLength']);
        $t->same('128-plus', $readerParts[$longPath]['pathByteLengthBucket']);
        $t->same(strlen($longPath), $compactParts[$longPath]['pathByteLength']);
        $t->same('128-plus', $compactParts[$longPath]['pathByteLengthBucket']);
        $t->same(strlen($longPath), $identityParts[$longPath]['pathByteLength']);
        $t->same('128-plus', $identityParts[$longPath]['pathByteLengthBucket']);
        $t->same(strlen($longPath), $compactIdentityParts[$longPath]['pathByteLength']);
        $t->same('128-plus', $compactIdentityParts[$longPath]['pathByteLengthBucket']);
    },
];
