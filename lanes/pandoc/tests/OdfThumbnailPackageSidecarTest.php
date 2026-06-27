<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$thumbnailBytes = 'PNGTHUMB';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/review" manifest:media-type="image/png" manifest:size="8"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Extensionless thumbnail package review.</text:p>
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

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Extensionless Thumbnail Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Thumbnails/review', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
];

return [
    'classifies compact ODT extensionless image thumbnails as package sidecars' => static function (TestRunner $t) use ($parts, $thumbnailBytes): void {
        $summary = OpenDocumentPackage::fromPackage(ZipPackage::fromParts($parts, 'compact extensionless thumbnail'))->summarize();
        $thumbnails = $summary['packageThumbnails'];
        $thumbnail = $thumbnails['items'][0];
        $inventory = $summary['packageInventory'];
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }

        $t->same(1, $thumbnails['count']);
        $t->same(1, $thumbnails['declaredCount']);
        $t->same(0, $thumbnails['undeclaredCount']);
        $t->same(0, $thumbnails['issueCount']);
        $t->same('Thumbnails/review', $thumbnail['packagePath']);
        $t->same('image/png', $thumbnail['mediaType']);
        $t->same(true, $thumbnail['valid']);
        $t->same(strlen($thumbnailBytes), $thumbnail['byteLength']);
        $t->same(false, $thumbnail['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-metadata-only', $thumbnail['reviewPolicy']);

        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same(1, $inventory['packageThumbnailPartCount']);
        $t->same(['package-thumbnail', 'manifest-declared'], $inventory['parts']['Thumbnails/review']['roles']);
        $t->same('thumbnail', $inventory['parts']['Thumbnails/review']['manifestMediaFamily']);
        $t->same('thumbnail', $reviewByPath['Thumbnails/review']['manifestMediaFamily']);
    },
    'classifies rich ODT extensionless image thumbnails before media and WordPress handoff' => static function (TestRunner $t) use ($parts, $thumbnailBytes): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'rich extensionless thumbnail'));
        $thumbnails = $result['packageThumbnails'];
        $thumbnail = $thumbnails['items'][0];
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $mediaResources = $provenance['mediaResources'];

        $t->same($thumbnails, $result['document']->attr('packageThumbnails'));
        $t->same($thumbnails, $result['importReport']['packageThumbnails']);
        $t->same(1, $thumbnails['count']);
        $t->same(1, $thumbnails['declaredCount']);
        $t->same(0, $thumbnails['undeclaredCount']);
        $t->same(0, $thumbnails['issueCount']);
        $t->same('Thumbnails/review', $thumbnail['part']);
        $t->same('image/png', $thumbnail['mediaType']);
        $t->same(true, $thumbnail['valid']);
        $t->same(strlen($thumbnailBytes), $thumbnail['byteLength']);
        $t->same(false, $thumbnail['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-metadata-only', $thumbnail['reviewPolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(1, $provenance['packageThumbnailPartCount']);
        $t->same(['package-thumbnail', 'manifest-declared'], $provenance['parts']['Thumbnails/review']['roles']);
        $t->same(1, $mediaResources['packageRolePrecedenceCount']);
        $t->same(['package-thumbnail'], $mediaResources['packageRolePrecedenceItems'][0]['packageRolePrecedence']);
        $t->same(false, in_array('media-resource', $provenance['parts']['Thumbnails/review']['roles'], true));

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Extensionless thumbnail package review.', $blocksHtml);
        $t->true(!str_contains($blocksHtml, $thumbnailBytes), 'Extensionless thumbnail bytes must not be rendered into WordPress output');
    },
];
