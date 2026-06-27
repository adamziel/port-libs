<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$formXml = '<forms><form id="review"/></forms>';
$formIconBytes = 'FORM-ICON-PNG';
$formDataBytes = 'FORM-DATA-BYTES';
$encryptedFormBytes = 'ENCRYPTED-FORM-BYTES';
$orphanFormXml = '<xforms:model id="orphan"/>';

$formXmlSize = strlen($formXml);
$formIconSize = strlen($formIconBytes);
$formDataSize = strlen($formDataBytes);
$encryptedFormSize = strlen($encryptedFormBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Forms/Review/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Forms/Review/forms.xml" manifest:media-type="text/xml" manifest:size="{$formXmlSize}"/>
  <manifest:file-entry manifest:full-path="Forms/Review/icon.png" manifest:media-type="image/png" manifest:size="{$formIconSize}"/>
  <manifest:file-entry manifest:full-path="Forms/Review/data.bin" manifest:media-type="application/octet-stream" manifest:size="{$formDataSize}"/>
  <manifest:file-entry manifest:full-path="Forms/Review/missing.xml" manifest:media-type="text/xml" manifest:size="19"/>
  <manifest:file-entry manifest:full-path="Forms/Review/encrypted.dat" manifest:media-type="application/octet-stream" manifest:size="{$encryptedFormSize}">
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
    ['name' => 'Forms/Review/forms.xml', 'data' => $formXml, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/icon.png', 'data' => $formIconBytes, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/data.bin', 'data' => $formDataBytes, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/encrypted.dat', 'data' => $encryptedFormBytes, 'compressionMethod' => 0],
    ['name' => 'Forms/Review/orphan.xforms', 'data' => $orphanFormXml, 'compressionMethod' => 0],
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
        $formXml,
        $formIconBytes,
        $formDataBytes,
        $orphanFormXml,
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
        $t->same(7, $readerForms['count']);
        $t->same(4, $readerForms['readableCount']);
        $t->same(6, $readerForms['declaredCount']);
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
            'form-binary-resource' => 2,
            'form-definition' => 3,
            'form-directory' => 1,
            'form-media-resource' => 1,
        ], $readerForms['kindCounts']);
        $t->same(['review' => 7], $readerForms['groupCounts']);
        $t->same('form-package-bytes-blocked', $readerForms['byteExposurePolicy']);
        $t->same('form-package-metadata-only', $readerForms['reviewPolicy']);

        $definition = $readerItems['Forms/Review/forms.xml'];
        $t->same('form-definition', $definition['kind']);
        $t->same('review', $definition['group']);
        $t->same(strlen($formXml), $definition['byteLength']);
        $t->same(sprintf('%08x', crc32($formXml)), $definition['crc32']);
        $t->same(false, $definition['canExposeBytes']);
        $t->same(false, $definition['canExposeAsDocumentMedia']);
        $t->same('form-package-bytes-blocked', $definition['byteExposurePolicy']);
        $t->same([], $definition['issues']);

        $icon = $readerItems['Forms/Review/icon.png'];
        $t->same('form-media-resource', $icon['kind']);
        $t->same('image/png', $icon['mediaTypeBase']);
        $t->same(strlen($formIconBytes), $icon['byteLength']);
        $t->same(false, $icon['canExposeAsDocumentMedia']);

        $data = $readerItems['Forms/Review/data.bin'];
        $t->same('form-binary-resource', $data['kind']);
        $t->same('application/octet-stream', $data['mediaTypeBase']);
        $t->same(strlen($formDataBytes), $data['storedByteLength']);
        $t->same('form-package-bytes-blocked', $data['byteExposurePolicy']);

        $missing = $readerItems['Forms/Review/missing.xml'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-form-package-missing-part'], $missing['issues']);
        $t->same('form-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Forms/Review/encrypted.dat'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-form-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Forms/Review/orphan.xforms'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('form-definition', $orphan['kind']);
        $t->same(strlen($orphanFormXml), $orphan['byteLength']);
        $t->same(['odf-form-package-undeclared-part'], $orphan['issues']);
        $t->same('form-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestIcon = $manifestByPart['Forms/Review/icon.png'];
        $t->same(true, $manifestIcon['formPackagePart']);
        $t->same(false, $manifestIcon['canExposeBytes']);
        $t->same(null, $manifestIcon['byteLength']);
        $t->same(strlen($formIconBytes), $manifestIcon['storedByteLength']);
        $t->same(null, $manifestIcon['byteSha256']);
        $t->same('form-package-bytes-blocked', $manifestIcon['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(6, $readerProvenance['formPackagePartCount']);
        $t->same(6, $readerProvenance['roleCounts']['form-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['form-package']);
        $t->same(['form-package', 'manifest-declared'], $readerProvenance['parts']['Forms/Review/icon.png']['roles']);
        $t->same(['form-package', 'undeclared-package-entry'], $readerProvenance['parts']['Forms/Review/orphan.xforms']['roles']);
        $t->same(true, $readerProvenance['parts']['Forms/Review/icon.png']['formPackagePart']);

        $readerIdentityManifest = $indexBy($readerProvenance['packageIdentity']['manifestEntries'], 'part');
        $readerIdentityPackage = $indexBy($readerProvenance['packageIdentity']['packageEntries'], 'part');
        $t->same(true, $readerIdentityManifest['Forms/Review/icon.png']['formPackagePart']);
        $t->same(true, $readerIdentityPackage['Forms/Review/icon.png']['formPackagePart']);
        $t->same(6, $readerProvenance['packageIdentity']['formPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Form package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $formXml));
        $t->same(false, str_contains($blocks, $formIconBytes));
        $t->same(false, str_contains($blocks, $orphanFormXml));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactForms = $compactSummary['packageForms'];
        $compactItems = $indexBy($compactForms['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(7, $compactForms['count']);
        $t->same(4, $compactForms['readableCount']);
        $t->same(6, $compactForms['declaredCount']);
        $t->same(1, $compactForms['undeclaredCount']);
        $t->same(1, $compactForms['missingCount']);
        $t->same(1, $compactForms['directoryCount']);
        $t->same(1, $compactForms['encryptedCount']);
        $t->same(3, $compactForms['issueCount']);
        $t->same($readerForms['issueCodes'], $compactForms['issueCodes']);
        $t->same('form-package-bytes-blocked', $compactForms['byteExposurePolicy']);
        $t->same('form-package-metadata-only', $compactForms['reviewPolicy']);
        $t->same('form-definition', $compactItems['Forms/Review/forms.xml']['kind']);
        $t->same('form-media-resource', $compactItems['Forms/Review/icon.png']['kind']);
        $t->same(false, $compactItems['Forms/Review/icon.png']['canExposeBytes']);
        $t->same(false, $compactItems['Forms/Review/icon.png']['canExposeAsDocumentMedia']);
        $t->same(strlen($formIconBytes), $compactItems['Forms/Review/icon.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($formIconBytes)), $compactItems['Forms/Review/icon.png']['crc32']);
        $t->same(['odf-form-package-missing-part'], $compactItems['Forms/Review/missing.xml']['issues']);
        $t->same(['odf-form-package-encrypted-part'], $compactItems['Forms/Review/encrypted.dat']['issues']);
        $t->same(['odf-form-package-undeclared-part'], $compactItems['Forms/Review/orphan.xforms']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $compactSummary['manifestReview']['formPackagePartCount']);
        $t->same(true, $reviewByPath['Forms/Review/icon.png']['formPackagePart']);
        $t->same(false, $reviewByPath['Forms/Review/icon.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Forms/Review/icon.png']['byteLength']);
        $t->same(strlen($formIconBytes), $reviewByPath['Forms/Review/icon.png']['storedByteLength']);
        $t->same('form-package-bytes-blocked', $reviewByPath['Forms/Review/icon.png']['byteExposurePolicy']);
        $t->same('form', $reviewByPath['Forms/Review/icon.png']['manifestMediaFamily']);
        $t->same(5, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['form']);
        $t->same(6, $inventory['formPackagePartCount']);
        $t->same(6, $inventory['roleCounts']['form-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['form-package']);
        $t->same(['form-package', 'manifest-declared'], $inventory['parts']['Forms/Review/icon.png']['roles']);
        $t->same(['form-package', 'undeclared-package-entry'], $inventory['parts']['Forms/Review/orphan.xforms']['roles']);
        $t->same(true, $inventory['parts']['Forms/Review/icon.png']['formPackagePart']);
        $t->same(false, $inventory['parts']['Forms/Review/icon.png']['canExposeBytes']);

        $compactIdentityManifest = $indexBy($compactSummary['packageIdentity']['manifestEntries'], 'path');
        $compactIdentityPackage = $indexBy($compactSummary['packageIdentity']['packageEntries'], 'path');
        $t->same(true, $compactIdentityManifest['Forms/Review/icon.png']['formPackagePart']);
        $t->same(true, $compactIdentityPackage['Forms/Review/icon.png']['formPackagePart']);
        $t->same(6, $compactSummary['packageIdentity']['formPackagePartCount']);
    },
];
