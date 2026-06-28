<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$thumbnailBytes = 'THUMBNAIL-PREVIEW';
$orphanBytes = 'ORPHAN-THUMBNAIL-PREVIEW';

$thumbnailSize = strlen($thumbnailBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png" manifest:size="{$thumbnailSize}"/>
  <manifest:file-entry manifest:full-path="Thumbnails/missing.jpg" manifest:media-type="image/jpeg"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Thumbnail package sidecars.</text:p>
      <text:p><draw:frame draw:name="hero"><draw:image xlink:href="Pictures/hero.png"/></draw:frame></text:p>
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
    <dc:title>Thumbnail Sidecar Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
    ['name' => 'Thumbnails/orphan.webp', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt thumbnail package sidecars');

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
    'reports ODT thumbnail package sidecars with rich and compact package paths' => static function (TestRunner $t) use (
        $buildPackage,
        $thumbnailBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerThumbnails = $result['packageThumbnails'];
        $readerItems = $indexBy($readerThumbnails['items'], 'packagePath');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerThumbnails, $result['document']->attr('packageThumbnails'));
        $t->same($readerThumbnails, $result['metadata']['odfPackageThumbnails']);
        $t->same($readerThumbnails, $result['importReport']['packageThumbnails']);
        $t->same(3, $readerThumbnails['count']);
        $t->same(2, $readerThumbnails['readableCount']);
        $t->same(2, $readerThumbnails['declaredCount']);
        $t->same(1, $readerThumbnails['undeclaredCount']);
        $t->same(1, $readerThumbnails['missingCount']);
        $t->same(0, $readerThumbnails['encryptedCount']);
        $t->same(0, $readerThumbnails['invalidMediaTypeCount']);
        $t->same(2, $readerThumbnails['issueCount']);
        $t->same([
            'odf-thumbnail-missing-package-part',
            'odf-thumbnail-undeclared-package-part',
        ], $readerThumbnails['issueCodes']);

        $declared = $readerItems['Thumbnails/thumbnail.png'];
        $t->same('Thumbnails/thumbnail.png', $declared['part']);
        $t->same('Thumbnails/thumbnail.png', $declared['packagePath']);
        $t->same('image/png', $declared['mediaType']);
        $t->same('image/png', $declared['mediaTypeBase']);
        $t->same(true, $declared['declared']);
        $t->same(true, $declared['exists']);
        $t->same(true, $declared['valid']);
        $t->same(strlen($thumbnailBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeBytes']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-bytes-blocked', $declared['byteExposurePolicy']);
        $t->same('package-thumbnail-metadata-only', $declared['reviewPolicy']);
        $t->same([], $declared['issues']);

        $missing = $readerItems['Thumbnails/missing.jpg'];
        $t->same('Thumbnails/missing.jpg', $missing['packagePath']);
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-thumbnail-missing-package-part'], $missing['issues']);
        $t->same('package-thumbnail-bytes-blocked', $missing['byteExposurePolicy']);

        $orphan = $readerItems['Thumbnails/orphan.webp'];
        $t->same('Thumbnails/orphan.webp', $orphan['packagePath']);
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('image/webp', $orphan['mediaType']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-thumbnail-undeclared-package-part'], $orphan['issues']);
        $t->same('package-thumbnail-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestThumbnail = $manifestByPart['Thumbnails/thumbnail.png'];
        $t->same(true, $manifestThumbnail['thumbnailPackagePart']);
        $t->same(false, $manifestThumbnail['canExposeBytes']);
        $t->same(null, $manifestThumbnail['byteLength']);
        $t->same(strlen($thumbnailBytes), $manifestThumbnail['storedByteLength']);
        $t->same(null, $manifestThumbnail['byteSha256']);
        $t->same('package-thumbnail-bytes-blocked', $manifestThumbnail['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(2, $readerProvenance['packageThumbnailPartCount']);
        $t->same(2, $readerProvenance['roleCounts']['package-thumbnail']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['package-thumbnail']);
        $t->same(['package-thumbnail', 'manifest-declared'], $readerProvenance['parts']['Thumbnails/thumbnail.png']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $readerProvenance['parts']['Thumbnails/orphan.webp']['roles']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][5]['thumbnailPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][6]['thumbnailPackagePart']);
        $t->same(2, $readerProvenance['packageIdentity']['packageThumbnailPartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Thumbnail package sidecars.', $blocks);
        $t->contains('Pictures/hero.png', $blocks);
        $t->same(false, str_contains($blocks, $thumbnailBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactThumbnails = $compactSummary['packageThumbnails'];
        $compactItems = $indexBy($compactThumbnails['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(3, $compactThumbnails['count']);
        $t->same(2, $compactThumbnails['readableCount']);
        $t->same(2, $compactThumbnails['declaredCount']);
        $t->same(1, $compactThumbnails['undeclaredCount']);
        $t->same(1, $compactThumbnails['missingCount']);
        $t->same($readerThumbnails['issueCodes'], $compactThumbnails['issueCodes']);
        $t->same('Thumbnails/thumbnail.png', $compactItems['Thumbnails/thumbnail.png']['packagePath']);
        $t->same(strlen($thumbnailBytes), $compactItems['Thumbnails/thumbnail.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $compactItems['Thumbnails/thumbnail.png']['crc32']);
        $t->same('package-thumbnail-bytes-blocked', $compactItems['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same(['odf-thumbnail-missing-package-part'], $compactItems['Thumbnails/missing.jpg']['issues']);
        $t->same(['odf-thumbnail-undeclared-package-part'], $compactItems['Thumbnails/orphan.webp']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(true, $reviewByPath['Thumbnails/thumbnail.png']['thumbnailPackagePart']);
        $t->same('thumbnail', $reviewByPath['Thumbnails/thumbnail.png']['manifestMediaFamily']);
        $t->same(false, $reviewByPath['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Thumbnails/thumbnail.png']['byteLength']);
        $t->same(strlen($thumbnailBytes), $reviewByPath['Thumbnails/thumbnail.png']['storedByteLength']);
        $t->same('package-thumbnail-bytes-blocked', $reviewByPath['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same(2, $compactSummary['manifestReview']['packageThumbnailPartCount']);
        $t->same(2, $inventory['packageThumbnailPartCount']);
        $t->same(2, $inventory['roleCounts']['package-thumbnail']);
        $t->same(1, $inventory['undeclaredRoleCounts']['package-thumbnail']);
        $t->same(['package-thumbnail', 'manifest-declared'], $inventory['parts']['Thumbnails/thumbnail.png']['roles']);
        $t->same(['package-thumbnail', 'undeclared-package-entry'], $inventory['parts']['Thumbnails/orphan.webp']['roles']);
        $t->same(2, $compactSummary['packageIdentity']['packageThumbnailPartCount']);
        $t->same(true, $compactSummary['packageIdentity']['manifestEntries'][5]['thumbnailPackagePart']);
        $t->same(true, $compactSummary['packageIdentity']['packageEntries'][6]['thumbnailPackagePart']);
    },
];
