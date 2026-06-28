<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$previewBytes = 'REPLACEMENT-PREVIEW';
$orphanBytes = 'UNDECLARED-REPLACEMENT';

$previewSize = strlen($previewBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="ObjectReplacements/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="ObjectReplacements/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Object replacement directory packet.</text:p>
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
  office:version="1.3">
  <office:meta/>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'ObjectReplacements/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'ObjectReplacements/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'ObjectReplacements/orphan.png', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt object replacement directory package');

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
    'keeps object replacement directories metadata-only in rich and compact package review' => static function (TestRunner $t) use (
        $buildPackage,
        $previewBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerReplacements = $result['packageObjectReplacements'];
        $readerItems = $indexBy($readerReplacements['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerReplacements, $result['metadata']['odfPackageObjectReplacements']);
        $t->same(3, $readerReplacements['count']);
        $t->same(2, $readerReplacements['readableCount']);
        $t->same(2, $readerReplacements['declaredCount']);
        $t->same(1, $readerReplacements['undeclaredCount']);
        $t->same(0, $readerReplacements['missingCount']);
        $t->same(1, $readerReplacements['directoryCount']);
        $t->same(0, $readerReplacements['encryptedCount']);
        $t->same(0, $readerReplacements['invalidMediaTypeCount']);
        $t->same(1, $readerReplacements['issueCount']);
        $t->same(['odf-object-replacement-undeclared-package-part'], $readerReplacements['issueCodes']);
        $t->same([
            'object-replacement-directory' => 1,
            'object-replacement-preview' => 2,
        ], $readerReplacements['kindCounts']);

        $declaredDirectory = $readerItems['ObjectReplacements/'];
        $t->same('object-replacement-directory', $declaredDirectory['kind']);
        $t->same(true, $declaredDirectory['isDirectory']);
        $t->same(true, $declaredDirectory['valid']);
        $t->same(null, $declaredDirectory['mediaType']);
        $t->same(null, $declaredDirectory['byteLength']);
        $t->same(false, $declaredDirectory['canExposeBytes']);
        $t->same(false, $declaredDirectory['canExposeAsDocumentMedia']);
        $t->same('directory-entry-no-bytes', $declaredDirectory['byteExposurePolicy']);
        $t->same([], $declaredDirectory['issues']);

        $preview = $readerItems['ObjectReplacements/preview.png'];
        $t->same('object-replacement-preview', $preview['kind']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $preview['crc32']);
        $t->same(false, $preview['canExposeBytes']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);
        $t->same('object-replacement-package-bytes-blocked', $preview['byteExposurePolicy']);

        $orphan = $readerItems['ObjectReplacements/orphan.png'];
        $t->same('object-replacement-preview', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-object-replacement-undeclared-package-part'], $orphan['issues']);
        $t->same('object-replacement-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestDirectory = $manifestByPart['ObjectReplacements/'];
        $t->same(true, $manifestDirectory['objectReplacementPackagePart']);
        $t->same(false, $manifestDirectory['canExposeBytes']);
        $t->same(null, $manifestDirectory['byteLength']);
        $t->same('directory-entry-no-bytes', $manifestDirectory['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(3, $provenance['objectReplacementPartCount']);
        $t->same(3, $provenance['roleCounts']['object-replacement']);
        $t->same(1, $provenance['undeclaredRoleCounts']['object-replacement']);
        $t->same(['object-replacement', 'zip-directory', 'manifest-declared'], $provenance['parts']['ObjectReplacements/']['roles']);
        $t->same(['object-replacement', 'manifest-declared'], $provenance['parts']['ObjectReplacements/preview.png']['roles']);
        $t->same(['object-replacement', 'undeclared-package-entry'], $provenance['parts']['ObjectReplacements/orphan.png']['roles']);
        $t->same(true, $provenance['parts']['ObjectReplacements/']['objectReplacementPackagePart']);
        $t->same('directory-entry-no-bytes', $provenance['parts']['ObjectReplacements/']['byteExposurePolicy']);
        $t->same(false, $provenance['parts']['ObjectReplacements/preview.png']['canExposeBytes']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactReplacements = $compactSummary['packageObjectReplacements'];
        $compactItems = $indexBy($compactReplacements['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(3, $compactReplacements['count']);
        $t->same(2, $compactReplacements['readableCount']);
        $t->same(1, $compactReplacements['directoryCount']);
        $t->same(1, $compactReplacements['undeclaredCount']);
        $t->same($readerReplacements['issueCodes'], $compactReplacements['issueCodes']);
        $t->same($readerReplacements['kindCounts'], $compactReplacements['kindCounts']);
        $t->same('object-replacement-directory', $compactItems['ObjectReplacements/']['kind']);
        $t->same(true, $compactItems['ObjectReplacements/']['isDirectory']);
        $t->same(true, $compactItems['ObjectReplacements/']['valid']);
        $t->same(null, $compactItems['ObjectReplacements/']['byteLength']);
        $t->same('directory-entry-no-bytes', $compactItems['ObjectReplacements/']['byteExposurePolicy']);
        $t->same('object-replacement-preview', $compactItems['ObjectReplacements/preview.png']['kind']);
        $t->same(false, $compactItems['ObjectReplacements/preview.png']['canExposeBytes']);
        $t->same(false, $compactItems['ObjectReplacements/preview.png']['canExposeAsDocumentMedia']);
        $t->same(strlen($previewBytes), $compactItems['ObjectReplacements/preview.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $compactItems['ObjectReplacements/preview.png']['crc32']);
        $t->same(['odf-object-replacement-undeclared-package-part'], $compactItems['ObjectReplacements/orphan.png']['issues']);

        $t->same(true, $reviewByPath['ObjectReplacements/']['objectReplacementPackagePart']);
        $t->same(false, $reviewByPath['ObjectReplacements/']['canExposeBytes']);
        $t->same(null, $reviewByPath['ObjectReplacements/']['byteLength']);
        $t->same('directory-entry-no-bytes', $reviewByPath['ObjectReplacements/']['byteExposurePolicy']);
        $t->same('directory', $reviewByPath['ObjectReplacements/']['manifestMediaFamily']);
        $t->same(true, $reviewByPath['ObjectReplacements/preview.png']['objectReplacementPackagePart']);
        $t->same(false, $reviewByPath['ObjectReplacements/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['ObjectReplacements/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['ObjectReplacements/preview.png']['storedByteLength']);
        $t->same('object-replacement-package-bytes-blocked', $reviewByPath['ObjectReplacements/preview.png']['byteExposurePolicy']);
        $t->same('object-replacement', $reviewByPath['ObjectReplacements/preview.png']['manifestMediaFamily']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(2, $compactSummary['manifestReview']['objectReplacementPackagePartCount']);
        $t->same(3, $inventory['objectReplacementPartCount']);
        $t->same(3, $inventory['roleCounts']['object-replacement']);
        $t->same(1, $inventory['undeclaredRoleCounts']['object-replacement']);
        $t->same(['zip-directory', 'object-replacement', 'manifest-declared'], $inventory['parts']['ObjectReplacements/']['roles']);
        $t->same(['object-replacement', 'manifest-declared'], $inventory['parts']['ObjectReplacements/preview.png']['roles']);
        $t->same(['object-replacement', 'undeclared-package-entry'], $inventory['parts']['ObjectReplacements/orphan.png']['roles']);
        $t->same(true, $inventory['parts']['ObjectReplacements/']['objectReplacementPackagePart']);
        $t->same('directory-entry-no-bytes', $inventory['parts']['ObjectReplacements/']['byteExposurePolicy']);
        $t->same(false, $inventory['parts']['ObjectReplacements/preview.png']['canExposeBytes']);
    },
];
