<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$formDefinitionXml = '<form:form xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0" form:name="ReviewForm"/>';
$formPreviewBytes = 'FORM-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-FORM-BYTES';
$orphanBytes = 'ORPHAN-FORM-DATA';

$formDefinitionSize = strlen($formDefinitionXml);
$formPreviewSize = strlen($formPreviewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Forms/Review/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Forms/Review/form.xml" manifest:media-type="text/xml" manifest:size="{$formDefinitionSize}"/>
  <manifest:file-entry manifest:full-path="Forms/Review/preview.png" manifest:media-type="image/png" manifest:size="{$formPreviewSize}"/>
  <manifest:file-entry manifest:full-path="Forms/Review/missing.dat" manifest:media-type="application/octet-stream" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Forms/Review/encrypted.dat" manifest:media-type="application/octet-stream" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="form-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Form package sidecars.</text:p>
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
    <dc:title>Form Sidecar Packet</dc:title>
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
    ['name' => 'Forms/Review/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Forms/Review/form.xml', 'data' => $formDefinitionXml, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/preview.png', 'data' => $formPreviewBytes, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/encrypted.dat', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/orphan.dat', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt form package sidecars');

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
    'reports ODT form package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $formDefinitionXml,
        $formPreviewBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerForms = $result['packageForms'];
        $readerItems = $indexBy($readerForms['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerForms, $result['document']->attr('packageForms'));
        $t->same($readerForms, $result['metadata']['odfPackageForms']);
        $t->same($readerForms, $result['importReport']['packageForms']);
        $t->same(6, $readerForms['count']);
        $t->same(3, $readerForms['readableCount']);
        $t->same(5, $readerForms['declaredCount']);
        $t->same(1, $readerForms['undeclaredCount']);
        $t->same(1, $readerForms['missingCount']);
        $t->same(1, $readerForms['directoryCount']);
        $t->same(1, $readerForms['encryptedCount']);
        $t->same(0, $readerForms['missingMediaTypeCount']);
        $t->same(0, $readerForms['invalidMediaTypeCount']);
        $t->same(3, $readerForms['issueCount']);
        $t->same([
            'odf-form-package-encrypted-part',
            'odf-form-package-missing-part',
            'odf-form-package-undeclared-part',
        ], $readerForms['issueCodes']);
        $t->same([
            'form-binary-resource' => 3,
            'form-definition' => 1,
            'form-directory' => 1,
            'form-media-resource' => 1,
        ], $readerForms['kindCounts']);
        $t->same(['review' => 6], $readerForms['groupCounts']);
        $t->same('form-package-bytes-blocked', $readerForms['byteExposurePolicy']);
        $t->same('form-package-metadata-only', $readerForms['reviewPolicy']);

        $directory = $readerItems['Forms/Review/'];
        $t->same('form-directory', $directory['kind']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $definition = $readerItems['Forms/Review/form.xml'];
        $t->same('form-definition', $definition['kind']);
        $t->same(strlen($formDefinitionXml), $definition['byteLength']);
        $t->same(sprintf('%08x', crc32($formDefinitionXml)), $definition['crc32']);
        $t->same(false, $definition['canExposeBytes']);
        $t->same(false, $definition['canExposeAsDocumentMedia']);
        $t->same('form-package-bytes-blocked', $definition['byteExposurePolicy']);
        $t->same([], $definition['issues']);

        $preview = $readerItems['Forms/Review/preview.png'];
        $t->same('form-media-resource', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($formPreviewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $readerItems['Forms/Review/missing.dat'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-form-package-missing-part'], $missing['issues']);
        $t->same('form-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Forms/Review/encrypted.dat'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-form-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Forms/Review/orphan.dat'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('form-binary-resource', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-form-package-undeclared-part'], $orphan['issues']);
        $t->same('form-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Forms/Review/preview.png'];
        $t->same(true, $manifestPreview['formPackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($formPreviewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('form-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(5, $readerProvenance['formPackagePartCount']);
        $t->same(5, $readerProvenance['roleCounts']['form-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['form-package']);
        $t->same(['form-package', 'manifest-declared'], $readerProvenance['parts']['Forms/Review/preview.png']['roles']);
        $t->same(['form-package', 'undeclared-package-entry'], $readerProvenance['parts']['Forms/Review/orphan.dat']['roles']);
        $t->same(true, $readerProvenance['parts']['Forms/Review/preview.png']['formPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][7]['formPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][8]['formPackagePart']);
        $t->same(5, $readerProvenance['packageIdentity']['formPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Form package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $formDefinitionXml));
        $t->same(false, str_contains($blocks, $formPreviewBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactForms = $compactSummary['packageForms'];
        $compactItems = $indexBy($compactForms['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(6, $compactForms['count']);
        $t->same(3, $compactForms['readableCount']);
        $t->same(5, $compactForms['declaredCount']);
        $t->same(1, $compactForms['undeclaredCount']);
        $t->same(1, $compactForms['missingCount']);
        $t->same(1, $compactForms['directoryCount']);
        $t->same(1, $compactForms['encryptedCount']);
        $t->same(3, $compactForms['issueCount']);
        $t->same($readerForms['issueCodes'], $compactForms['issueCodes']);
        $t->same('form-package-bytes-blocked', $compactForms['byteExposurePolicy']);
        $t->same('form-package-metadata-only', $compactForms['reviewPolicy']);
        $t->same('form-definition', $compactItems['Forms/Review/form.xml']['kind']);
        $t->same(false, $compactItems['Forms/Review/form.xml']['canExposeBytes']);
        $t->same(false, $compactItems['Forms/Review/form.xml']['canExposeAsDocumentMedia']);
        $t->same(strlen($formDefinitionXml), $compactItems['Forms/Review/form.xml']['byteLength']);
        $t->same(sprintf('%08x', crc32($formDefinitionXml)), $compactItems['Forms/Review/form.xml']['crc32']);
        $t->same(['odf-form-package-missing-part'], $compactItems['Forms/Review/missing.dat']['issues']);
        $t->same(['odf-form-package-encrypted-part'], $compactItems['Forms/Review/encrypted.dat']['issues']);
        $t->same(['odf-form-package-undeclared-part'], $compactItems['Forms/Review/orphan.dat']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(5, $compactSummary['manifestReview']['formPackagePartCount']);
        $t->same(true, $reviewByPath['Forms/Review/preview.png']['formPackagePart']);
        $t->same(false, $reviewByPath['Forms/Review/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Forms/Review/preview.png']['byteLength']);
        $t->same(strlen($formPreviewBytes), $reviewByPath['Forms/Review/preview.png']['storedByteLength']);
        $t->same('form-package-bytes-blocked', $reviewByPath['Forms/Review/preview.png']['byteExposurePolicy']);
        $t->same('form', $reviewByPath['Forms/Review/preview.png']['manifestMediaFamily']);
        $t->same(4, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['form']);
        $t->same(5, $inventory['formPackagePartCount']);
        $t->same(5, $inventory['roleCounts']['form-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['form-package']);
        $t->same(['form-package', 'manifest-declared'], $inventory['parts']['Forms/Review/preview.png']['roles']);
        $t->same(['form-package', 'undeclared-package-entry'], $inventory['parts']['Forms/Review/orphan.dat']['roles']);
        $t->same(true, $inventory['parts']['Forms/Review/preview.png']['formPackagePart']);
        $t->same(false, $inventory['parts']['Forms/Review/preview.png']['canExposeBytes']);
        $t->same(true, $compactSummary['packageIdentity']['manifestEntries'][7]['formPackagePart']);
        $t->same(true, $compactSummary['packageIdentity']['packageEntries'][8]['formPackagePart']);
        $t->same(5, $compactSummary['packageIdentity']['formPackagePartCount']);
    },
];
