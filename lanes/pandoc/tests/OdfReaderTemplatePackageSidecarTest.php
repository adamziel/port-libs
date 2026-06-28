<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$templateManifestXml = '<templates xmlns="urn:example:templates"><template name="letter.ott"/></templates>';
$templateBytes = 'ODF-TEMPLATE-BYTES';
$openXmlTemplateBytes = 'OPENXML-TEMPLATE-BYTES';
$previewBytes = 'TEMPLATE-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-TEMPLATE-BYTES';
$orphanBytes = 'ORPHAN-TEMPLATE-BYTES';
$orphanOpenXmlTemplateBytes = 'ORPHAN-OPENXML-TEMPLATE-BYTES';

$templateManifestSize = strlen($templateManifestXml);
$templateSize = strlen($templateBytes);
$openXmlTemplateSize = strlen($openXmlTemplateBytes);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Templates/Review/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Templates/Review/manifest.xml" manifest:media-type="text/xml" manifest:size="{$templateManifestSize}"/>
  <manifest:file-entry manifest:full-path="Templates/Review/letter.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template" manifest:size="{$templateSize}"/>
  <manifest:file-entry manifest:full-path="Templates/Review/letter.dotx" manifest:media-type="" manifest:size="{$openXmlTemplateSize}"/>
  <manifest:file-entry manifest:full-path="Templates/Review/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Templates/Review/missing.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Templates/Review/encrypted.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="template-checksum"/>
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
      <text:p>Template package sidecars.</text:p>
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
    <dc:title>Template Sidecar Packet</dc:title>
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
    ['name' => 'Templates/Review/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Templates/Review/manifest.xml', 'data' => $templateManifestXml, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/letter.ott', 'data' => $templateBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/letter.dotx', 'data' => $openXmlTemplateBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/encrypted.ott', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/orphan.ott', 'data' => $orphanBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/orphan.potx', 'data' => $orphanOpenXmlTemplateBytes, 'compressionMethod' => 0],
], 'odt template package sidecars');

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
    'reports ODT template package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $templateManifestXml,
        $templateBytes,
        $openXmlTemplateBytes,
        $previewBytes,
        $orphanBytes,
        $orphanOpenXmlTemplateBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerTemplates = $result['packageTemplates'];
        $readerItems = $indexBy($readerTemplates['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerTemplates, $result['document']->attr('packageTemplates'));
        $t->same($readerTemplates, $result['metadata']['odfPackageTemplates']);
        $t->same($readerTemplates, $result['importReport']['packageTemplates']);
        $t->same(9, $readerTemplates['count']);
        $t->same(6, $readerTemplates['readableCount']);
        $t->same(7, $readerTemplates['declaredCount']);
        $t->same(2, $readerTemplates['undeclaredCount']);
        $t->same(1, $readerTemplates['missingCount']);
        $t->same(1, $readerTemplates['directoryCount']);
        $t->same(1, $readerTemplates['encryptedCount']);
        $t->same(0, $readerTemplates['missingMediaTypeCount']);
        $t->same(0, $readerTemplates['invalidMediaTypeCount']);
        $t->same(4, $readerTemplates['issueCount']);
        $t->same([
            'odf-template-package-encrypted-part',
            'odf-template-package-missing-part',
            'odf-template-package-undeclared-part',
        ], $readerTemplates['issueCodes']);
        $t->same([
            'template-directory' => 1,
            'template-document' => 6,
            'template-manifest' => 1,
            'template-preview-media' => 1,
        ], $readerTemplates['kindCounts']);
        $t->same(['review' => 9], $readerTemplates['groupCounts']);
        $t->same('template-package-bytes-blocked', $readerTemplates['byteExposurePolicy']);
        $t->same('template-package-metadata-only', $readerTemplates['reviewPolicy']);

        $directory = $readerItems['Templates/Review/'];
        $t->same('template-directory', $directory['kind']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $templateManifest = $readerItems['Templates/Review/manifest.xml'];
        $t->same('template-manifest', $templateManifest['kind']);
        $t->same(strlen($templateManifestXml), $templateManifest['byteLength']);
        $t->same(sprintf('%08x', crc32($templateManifestXml)), $templateManifest['crc32']);
        $t->same(false, $templateManifest['canExposeBytes']);
        $t->same(false, $templateManifest['canExposeAsDocumentMedia']);
        $t->same('template-package-bytes-blocked', $templateManifest['byteExposurePolicy']);

        $template = $readerItems['Templates/Review/letter.ott'];
        $t->same('template-document', $template['kind']);
        $t->same('application/vnd.oasis.opendocument.text-template', $template['mediaTypeBase']);
        $t->same(strlen($templateBytes), $template['byteLength']);
        $t->same(false, $template['canExposeBytes']);

        $openXmlTemplate = $readerItems['Templates/Review/letter.dotx'];
        $t->same('template-document', $openXmlTemplate['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.template', $openXmlTemplate['mediaTypeBase']);
        $t->same(strlen($openXmlTemplateBytes), $openXmlTemplate['byteLength']);
        $t->same(false, $openXmlTemplate['canExposeBytes']);
        $t->same(false, $openXmlTemplate['canExposeAsDocumentMedia']);
        $t->same([], $openXmlTemplate['issues']);

        $preview = $readerItems['Templates/Review/preview.png'];
        $t->same('template-preview-media', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $readerItems['Templates/Review/missing.ott'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-template-package-missing-part'], $missing['issues']);

        $encrypted = $readerItems['Templates/Review/encrypted.ott'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-template-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Templates/Review/orphan.ott'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('template-document', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-template-package-undeclared-part'], $orphan['issues']);

        $orphanOpenXml = $readerItems['Templates/Review/orphan.potx'];
        $t->same(false, $orphanOpenXml['declared']);
        $t->same(true, $orphanOpenXml['undeclared']);
        $t->same('template-document', $orphanOpenXml['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.presentationml.template', $orphanOpenXml['mediaTypeBase']);
        $t->same(strlen($orphanOpenXmlTemplateBytes), $orphanOpenXml['byteLength']);
        $t->same(['odf-template-package-undeclared-part'], $orphanOpenXml['issues']);
        $t->same('template-package-bytes-blocked', $orphanOpenXml['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Templates/Review/preview.png'];
        $t->same(true, $manifestPreview['templatePackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('template-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(8, $readerProvenance['templatePackagePartCount']);
        $t->same(8, $readerProvenance['roleCounts']['template-package']);
        $t->same(2, $readerProvenance['undeclaredRoleCounts']['template-package']);
        $t->same(['template-package', 'manifest-declared'], $readerProvenance['parts']['Templates/Review/preview.png']['roles']);
        $t->same(['template-package', 'undeclared-package-entry'], $readerProvenance['parts']['Templates/Review/orphan.ott']['roles']);
        $t->same(['template-package', 'undeclared-package-entry'], $readerProvenance['parts']['Templates/Review/orphan.potx']['roles']);
        $readerIdentityManifest = $indexBy($readerProvenance['packageIdentity']['manifestEntries'], 'part');
        $readerIdentityPackage = $indexBy($readerProvenance['packageIdentity']['packageEntries'], 'part');
        $t->same(true, $readerIdentityManifest['Templates/Review/letter.dotx']['templatePackagePart']);
        $t->same(true, $readerIdentityPackage['Templates/Review/orphan.potx']['templatePackagePart']);
        $t->same(8, $readerProvenance['packageIdentity']['templatePackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Template package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $templateManifestXml));
        $t->same(false, str_contains($blocks, $templateBytes));
        $t->same(false, str_contains($blocks, $openXmlTemplateBytes));
        $t->same(false, str_contains($blocks, $previewBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));
        $t->same(false, str_contains($blocks, $orphanOpenXmlTemplateBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactTemplates = $compactSummary['packageTemplates'];
        $compactItems = $indexBy($compactTemplates['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(9, $compactTemplates['count']);
        $t->same(6, $compactTemplates['readableCount']);
        $t->same(7, $compactTemplates['declaredCount']);
        $t->same(2, $compactTemplates['undeclaredCount']);
        $t->same(1, $compactTemplates['missingCount']);
        $t->same(1, $compactTemplates['encryptedCount']);
        $t->same($readerTemplates['issueCodes'], $compactTemplates['issueCodes']);
        $t->same($readerTemplates['kindCounts'], $compactTemplates['kindCounts']);
        $t->same('template-package-bytes-blocked', $compactTemplates['byteExposurePolicy']);
        $t->same('template-package-metadata-only', $compactTemplates['reviewPolicy']);
        $t->same('template-document', $compactItems['Templates/Review/letter.dotx']['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.template', $compactItems['Templates/Review/letter.dotx']['mediaTypeBase']);
        $t->same('template-document', $compactItems['Templates/Review/orphan.potx']['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.presentationml.template', $compactItems['Templates/Review/orphan.potx']['mediaTypeBase']);
        $t->same(['odf-template-package-undeclared-part'], $compactItems['Templates/Review/orphan.potx']['issues']);
        $t->same(false, $compactItems['Templates/Review/preview.png']['canExposeBytes']);
        $t->same(false, $compactItems['Templates/Review/preview.png']['canExposeAsDocumentMedia']);
        $t->same('template-package-bytes-blocked', $compactItems['Templates/Review/preview.png']['byteExposurePolicy']);
        $t->same(false, $reviewByPath['Templates/Review/preview.png']['canExposeBytes']);
        $t->same('template-package-bytes-blocked', $reviewByPath['Templates/Review/preview.png']['byteExposurePolicy']);
        $t->same(true, $reviewByPath['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'packagePath'));
        $t->same(7, $compactSummary['manifestReview']['templatePackagePartCount']);
        $t->same(8, $inventory['templatePackagePartCount']);
        $t->same(8, $inventory['roleCounts']['template-package']);
        $t->same(2, $inventory['undeclaredRoleCounts']['template-package']);
        $t->same(['template-package', 'undeclared-package-entry'], $inventory['parts']['Templates/Review/orphan.ott']['roles']);
        $t->same(['template-package', 'undeclared-package-entry'], $inventory['parts']['Templates/Review/orphan.potx']['roles']);
        $t->same(8, $compactSummary['packageIdentity']['templatePackagePartCount']);
    },
];
