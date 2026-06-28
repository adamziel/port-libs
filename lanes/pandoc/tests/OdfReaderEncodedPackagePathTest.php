<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$attachmentBytes = 'URI-ENCODED-ATTACHMENT-PREVIEW';
$attachmentSize = strlen($attachmentBytes);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Encoded package path sidecar.</text:p>
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
    <dc:title>Encoded Sidecar Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Attachments%2FReview%2Fpreview%20image.png?source=desk#packet" manifest:media-type="image/png" manifest:size="{$attachmentSize}"/>
</manifest:manifest>
XML;

$malformedManifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/bad%GG.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$buildPackage = static fn (string $manifest): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/preview image.png', 'data' => $attachmentBytes, 'compressionMethod' => 0],
], 'odt encoded manifest package path sidecar');

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
    'classifies URI-encoded ODT sidecar package paths after manifest decoding' => static function (TestRunner $t) use (
        $buildPackage,
        $manifestXml,
        $attachmentBytes,
        $indexBy
    ): void {
        $package = $buildPackage($manifestXml);
        $result = (new OdfReader())->readPackage($package);
        $readerAttachments = $result['packageAttachments'];
        $readerItems = $indexBy($readerAttachments['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $encoded = $readerItems['Attachments/Review/preview image.png'];
        $t->same('Attachments%2FReview%2Fpreview%20image.png?source=desk#packet', $encoded['fullPath']);
        $t->same('Attachments%2FReview%2Fpreview%20image.png', $encoded['partReference']);
        $t->same('?source=desk#packet', $encoded['partSuffix']);
        $t->same('source=desk', $encoded['partQuery']);
        $t->same('packet', $encoded['partFragment']);
        $t->same('attachment-media-resource', $encoded['kind']);
        $t->same('review', $encoded['group']);
        $t->same(strlen($attachmentBytes), $encoded['byteLength']);
        $t->same(false, $encoded['canExposeBytes']);
        $t->same(false, $encoded['canExposeAsDocumentMedia']);
        $t->same('attachment-package-bytes-blocked', $encoded['byteExposurePolicy']);
        $t->same([], $encoded['issues']);

        $manifestItem = $manifestByPart['Attachments/Review/preview image.png'];
        $t->same(true, $manifestItem['attachmentPackagePart']);
        $t->same('Attachments%2FReview%2Fpreview%20image.png?source=desk#packet', $manifestItem['fullPath']);
        $t->same('Attachments%2FReview%2Fpreview%20image.png', $manifestItem['partReference']);
        $t->same('Attachments/Review/preview image.png', $manifestItem['part']);
        $t->same(false, $manifestItem['canExposeBytes']);
        $t->same(null, $manifestItem['byteSha256']);
        $t->same('attachment-package-bytes-blocked', $manifestItem['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(1, $readerProvenance['attachmentPackagePartCount']);
        $t->same(['attachment-package', 'manifest-declared'], $readerProvenance['parts']['Attachments/Review/preview image.png']['roles']);
        $t->same(1, $readerProvenance['mediaResources']['packageRolePrecedenceCount']);
        $t->same(['attachment-package'], $readerProvenance['mediaResources']['packageRolePrecedenceItems'][0]['packageRolePrecedence']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactAttachments = $compactSummary['packageAttachments'];
        $compactItems = $indexBy($compactAttachments['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'packagePath');
        $inventory = $compactSummary['packageInventory'];

        $compactEncoded = $compactItems['Attachments/Review/preview image.png'];
        $t->same($encoded['fullPath'], $compactEncoded['fullPath']);
        $t->same($encoded['partReference'], $compactEncoded['pathReference']);
        $t->same($encoded['partSuffix'], $compactEncoded['pathSuffix']);
        $t->same($encoded['partQuery'], $compactEncoded['pathQuery']);
        $t->same($encoded['partFragment'], $compactEncoded['pathFragment']);
        $t->same('attachment-media-resource', $compactEncoded['kind']);
        $t->same('attachment-package-bytes-blocked', $compactEncoded['byteExposurePolicy']);
        $t->same(false, $compactEncoded['canExposeBytes']);

        $compactReview = $reviewByPath['Attachments/Review/preview image.png'];
        $t->same(true, $compactReview['uriEncodedPackageReference']);
        $t->same('attachment', $compactReview['manifestMediaFamily']);
        $t->same(false, $compactReview['canExposeBytes']);
        $t->same('attachment-package-bytes-blocked', $compactReview['byteExposurePolicy']);
        $t->same(1, $compactSummary['manifestReview']['mediaResources']['packageRolePrecedenceCount']);
        $t->same(['attachment-package'], $compactSummary['manifestReview']['mediaResources']['packageRolePrecedenceItems'][0]['packageRolePrecedence']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'packagePath'));
        $t->same(['attachment-package', 'manifest-declared'], $inventory['parts']['Attachments/Review/preview image.png']['roles']);
    },
    'rejects malformed percent escapes in ODT manifest package paths' => static function (TestRunner $t) use (
        $buildPackage,
        $malformedManifestXml
    ): void {
        $package = $buildPackage($malformedManifestXml);

        $t->throws(\InvalidArgumentException::class, static fn (): array => (new OdfReader())->readPackage($package));
        $t->throws(\InvalidArgumentException::class, static fn (): OpenDocumentPackage => OpenDocumentPackage::fromPackage($package));
    },
];
