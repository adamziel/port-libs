<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$reportManifestXml = '<reports xmlns="urn:example:reports"><report name="quarterly"/></reports>';
$reportDocumentBytes = 'ODF-REPORT-DOCUMENT-BYTES';
$reportDocxBytes = 'DOCX-REPORT-DOCUMENT-BYTES';
$previewBytes = 'REPORT-PREVIEW-PNG';
$exportBytes = '%PDF-REPORT-EXPORT%';
$encryptedBytes = 'ENCRYPTED-REPORT-BYTES';
$orphanBytes = '%PDF-ORPHAN-REPORT%';
$orphanWorkbookBytes = 'XLSX-ORPHAN-REPORT-DATA';

$reportManifestSize = strlen($reportManifestXml);
$reportDocumentSize = strlen($reportDocumentBytes);
$reportDocxSize = strlen($reportDocxBytes);
$previewSize = strlen($previewBytes);
$exportSize = strlen($exportBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/manifest.xml" manifest:media-type="text/xml" manifest:size="{$reportManifestSize}"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/report.odt" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:size="{$reportDocumentSize}"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/source.docx" manifest:media-type="" manifest:size="{$reportDocxSize}"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/export.pdf" manifest:media-type="application/pdf" manifest:size="{$exportSize}bytes"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/missing.pdf" manifest:media-type="application/pdf" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Reports/Quarterly/encrypted.pdf" manifest:media-type="application/pdf" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="report-checksum"/>
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
      <text:p>Report package sidecars.</text:p>
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
    <dc:title>Report Sidecar Packet</dc:title>
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
    ['name' => 'Reports/Quarterly/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/manifest.xml', 'data' => $reportManifestXml, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/report.odt', 'data' => $reportDocumentBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/source.docx', 'data' => $reportDocxBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/export.pdf', 'data' => $exportBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/encrypted.pdf', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/orphan.pdf', 'data' => $orphanBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Quarterly/orphan.xlsx', 'data' => $orphanWorkbookBytes, 'compressionMethod' => 0],
], 'odt report package sidecars');

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
    'reports ODT report package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $reportManifestXml,
        $reportDocumentBytes,
        $reportDocxBytes,
        $previewBytes,
        $exportBytes,
        $exportSize,
        $orphanBytes,
        $orphanWorkbookBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerReports = $result['packageReports'];
        $readerItems = $indexBy($readerReports['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];
        $readerMediaResources = $readerProvenance['mediaResources'];
        $readerMediaResourceByPart = $indexBy($readerMediaResources['items'], 'part');
        $readerMediaResourcePrecedenceByPart = $indexBy($readerMediaResources['packageRolePrecedenceItems'], 'part');

        $t->same($readerReports, $result['document']->attr('packageReports'));
        $t->same($readerReports, $result['metadata']['odfPackageReports']);
        $t->same($readerReports, $result['importReport']['packageReports']);
        $t->same(10, $readerReports['count']);
        $t->same(7, $readerReports['readableCount']);
        $t->same(8, $readerReports['declaredCount']);
        $t->same(2, $readerReports['undeclaredCount']);
        $t->same(1, $readerReports['missingCount']);
        $t->same(1, $readerReports['directoryCount']);
        $t->same(1, $readerReports['encryptedCount']);
        $t->same(0, $readerReports['missingMediaTypeCount']);
        $t->same(0, $readerReports['invalidMediaTypeCount']);
        $t->same(1, $readerReports['invalidDeclaredSizeCount']);
        $t->same(5, $readerReports['issueCount']);
        $t->same([
            'odf-report-package-encrypted-part',
            'odf-report-package-invalid-declared-size',
            'odf-report-package-missing-part',
            'odf-report-package-undeclared-part',
        ], $readerReports['issueCodes']);
        $t->same([
            'report-definition' => 1,
            'report-directory' => 1,
            'report-document' => 3,
            'report-output-resource' => 4,
            'report-preview-media' => 1,
        ], $readerReports['kindCounts']);
        $t->same(['quarterly' => 10], $readerReports['groupCounts']);
        $t->same('report-package-bytes-blocked', $readerReports['byteExposurePolicy']);
        $t->same('report-package-metadata-only', $readerReports['reviewPolicy']);
        $t->same(0, $result['packageForms']['count']);
        $t->same([], $result['packageForms']['items']);
        $t->same(0, $result['document']->attr('packageForms')['count']);
        $t->same(false, array_key_exists('odfPackageForms', $result['metadata']));

        $directory = $readerItems['Reports/Quarterly/'];
        $t->same('report-directory', $directory['kind']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $definition = $readerItems['Reports/Quarterly/manifest.xml'];
        $t->same('report-definition', $definition['kind']);
        $t->same(strlen($reportManifestXml), $definition['byteLength']);
        $t->same(sprintf('%08x', crc32($reportManifestXml)), $definition['crc32']);
        $t->same(false, $definition['canExposeBytes']);
        $t->same(false, $definition['canExposeAsDocumentMedia']);

        $document = $readerItems['Reports/Quarterly/report.odt'];
        $t->same('report-document', $document['kind']);
        $t->same('application/vnd.oasis.opendocument.text', $document['mediaTypeBase']);
        $t->same(strlen($reportDocumentBytes), $document['byteLength']);
        $t->same(false, $document['canExposeBytes']);

        $sourceDocx = $readerItems['Reports/Quarterly/source.docx'];
        $t->same('report-document', $sourceDocx['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $sourceDocx['mediaTypeBase']);
        $t->same(strlen($reportDocxBytes), $sourceDocx['byteLength']);
        $t->same(false, $sourceDocx['canExposeBytes']);
        $t->same([], $sourceDocx['issues']);

        $preview = $readerItems['Reports/Quarterly/preview.png'];
        $t->same('report-preview-media', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $export = $readerItems['Reports/Quarterly/export.pdf'];
        $t->same('report-output-resource', $export['kind']);
        $t->same(strlen($exportBytes), $export['byteLength']);
        $t->same(null, $export['declaredSize']);
        $t->same($exportSize . 'bytes', $export['declaredSizeRaw']);
        $t->same(false, $export['declaredSizeValid']);
        $t->same(true, $export['declaredSizeInvalid']);
        $t->same(['odf-report-package-invalid-declared-size'], $export['issues']);

        $missing = $readerItems['Reports/Quarterly/missing.pdf'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-report-package-missing-part'], $missing['issues']);

        $encrypted = $readerItems['Reports/Quarterly/encrypted.pdf'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-report-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Reports/Quarterly/orphan.pdf'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('report-output-resource', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-report-package-undeclared-part'], $orphan['issues']);

        $orphanWorkbook = $readerItems['Reports/Quarterly/orphan.xlsx'];
        $t->same(false, $orphanWorkbook['declared']);
        $t->same(true, $orphanWorkbook['undeclared']);
        $t->same('report-document', $orphanWorkbook['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $orphanWorkbook['mediaTypeBase']);
        $t->same(strlen($orphanWorkbookBytes), $orphanWorkbook['byteLength']);
        $t->same(['odf-report-package-undeclared-part'], $orphanWorkbook['issues']);
        $t->same('report-package-bytes-blocked', $orphanWorkbook['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Reports/Quarterly/preview.png'];
        $t->same(true, $manifestPreview['reportPackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('report-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(1, $readerMediaResources['mediaResourceCount']);
        $t->same(5, $readerMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Reports/Quarterly/report.odt',
            'Reports/Quarterly/preview.png',
            'Reports/Quarterly/export.pdf',
            'Reports/Quarterly/missing.pdf',
            'Reports/Quarterly/encrypted.pdf',
        ], array_column($readerMediaResources['items'], 'part'));
        $t->same(['report-package'], $readerMediaResourceByPart['Reports/Quarterly/preview.png']['packageRolePrecedence']);
        $t->same(false, $readerMediaResourceByPart['Reports/Quarterly/preview.png']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $readerMediaResourceByPart['Reports/Quarterly/preview.png']['issues']);
        $t->same('report-package-bytes-blocked', $readerMediaResourcePrecedenceByPart['Reports/Quarterly/preview.png']['byteExposurePolicy']);
        $t->same(9, $readerProvenance['reportPackagePartCount']);
        $t->same(9, $readerProvenance['roleCounts']['report-package']);
        $t->same(2, $readerProvenance['undeclaredRoleCounts']['report-package']);
        $t->same(['report-package', 'manifest-declared'], $readerProvenance['parts']['Reports/Quarterly/preview.png']['roles']);
        $t->same(['report-package', 'undeclared-package-entry'], $readerProvenance['parts']['Reports/Quarterly/orphan.pdf']['roles']);
        $t->same(['report-package', 'undeclared-package-entry'], $readerProvenance['parts']['Reports/Quarterly/orphan.xlsx']['roles']);
        $t->same(['report-package', 'manifest-declared'], $readerProvenance['parts']['Reports/Quarterly/source.docx']['roles']);
        $t->same(true, $readerProvenance['parts']['Reports/Quarterly/preview.png']['reportPackagePart']);
        $t->same(true, $readerProvenance['parts']['Reports/Quarterly/source.docx']['reportPackagePart']);
        $t->same(0, $readerProvenance['formPackagePartCount']);
        $t->same(false, array_key_exists('form-package', $readerProvenance['roleCounts']));
        $t->same(false, $readerProvenance['parts']['Reports/Quarterly/preview.png']['formPackagePart'] ?? false);
        $t->same(0, $readerProvenance['packageIdentity']['formPackagePartCount']);
        $t->same(9, $readerProvenance['packageIdentity']['reportPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Report package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $reportManifestXml));
        $t->same(false, str_contains($blocks, $reportDocumentBytes));
        $t->same(false, str_contains($blocks, $reportDocxBytes));
        $t->same(false, str_contains($blocks, $previewBytes));
        $t->same(false, str_contains($blocks, $exportBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));
        $t->same(false, str_contains($blocks, $orphanWorkbookBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactReports = $compactSummary['packageReports'];
        $compactItems = $indexBy($compactReports['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactMediaResources = $compactSummary['manifestReview']['mediaResources'];
        $compactMediaResourceByPart = $indexBy($compactMediaResources['items'], 'part');
        $compactMediaResourcePrecedenceByPart = $indexBy($compactMediaResources['packageRolePrecedenceItems'], 'part');
        $inventory = $compactSummary['packageInventory'];

        $t->same(0, $compactSummary['packageForms']['count']);
        $t->same([], $compactSummary['packageForms']['items']);
        $t->same(10, $compactReports['count']);
        $t->same(7, $compactReports['readableCount']);
        $t->same(8, $compactReports['declaredCount']);
        $t->same(2, $compactReports['undeclaredCount']);
        $t->same(1, $compactReports['missingCount']);
        $t->same(1, $compactReports['directoryCount']);
        $t->same(1, $compactReports['encryptedCount']);
        $t->same(1, $compactReports['invalidDeclaredSizeCount']);
        $t->same(5, $compactReports['issueCount']);
        $t->same($readerReports['issueCodes'], $compactReports['issueCodes']);
        $t->same($readerReports['kindCounts'], $compactReports['kindCounts']);
        $t->same('report-package-bytes-blocked', $compactReports['byteExposurePolicy']);
        $t->same('report-package-metadata-only', $compactReports['reviewPolicy']);
        $t->same('report-definition', $compactItems['Reports/Quarterly/manifest.xml']['kind']);
        $t->same('report-document', $compactItems['Reports/Quarterly/report.odt']['kind']);
        $t->same('report-document', $compactItems['Reports/Quarterly/source.docx']['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $compactItems['Reports/Quarterly/source.docx']['mediaTypeBase']);
        $t->same('report-preview-media', $compactItems['Reports/Quarterly/preview.png']['kind']);
        $t->same('report-output-resource', $compactItems['Reports/Quarterly/export.pdf']['kind']);
        $t->same(null, $compactItems['Reports/Quarterly/export.pdf']['declaredSize']);
        $t->same($exportSize . 'bytes', $compactItems['Reports/Quarterly/export.pdf']['declaredSizeRaw']);
        $t->same(false, $compactItems['Reports/Quarterly/export.pdf']['declaredSizeValid']);
        $t->same(true, $compactItems['Reports/Quarterly/export.pdf']['declaredSizeInvalid']);
        $t->same(['odf-report-package-invalid-declared-size'], $compactItems['Reports/Quarterly/export.pdf']['issues']);
        $t->same(['odf-report-package-missing-part'], $compactItems['Reports/Quarterly/missing.pdf']['issues']);
        $t->same(['odf-report-package-encrypted-part'], $compactItems['Reports/Quarterly/encrypted.pdf']['issues']);
        $t->same(['odf-report-package-undeclared-part'], $compactItems['Reports/Quarterly/orphan.pdf']['issues']);
        $t->same('report-document', $compactItems['Reports/Quarterly/orphan.xlsx']['kind']);
        $t->same('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $compactItems['Reports/Quarterly/orphan.xlsx']['mediaTypeBase']);
        $t->same(['odf-report-package-undeclared-part'], $compactItems['Reports/Quarterly/orphan.xlsx']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'packagePath'));
        $t->same(8, $compactSummary['manifestReview']['reportPackagePartCount']);
        $t->same(0, $compactSummary['manifestReview']['formPackagePartCount']);
        $t->same(true, $reviewByPath['Reports/Quarterly/preview.png']['reportPackagePart']);
        $t->same(false, $reviewByPath['Reports/Quarterly/preview.png']['formPackagePart'] ?? false);
        $t->same(false, $reviewByPath['Reports/Quarterly/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Reports/Quarterly/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['Reports/Quarterly/preview.png']['storedByteLength']);
        $t->same('report-package-bytes-blocked', $reviewByPath['Reports/Quarterly/preview.png']['byteExposurePolicy']);
        $t->same('report', $reviewByPath['Reports/Quarterly/preview.png']['manifestMediaFamily']);
        $t->same(1, $compactMediaResources['mediaResourceCount']);
        $t->same(5, $compactMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Reports/Quarterly/report.odt',
            'Reports/Quarterly/preview.png',
            'Reports/Quarterly/export.pdf',
            'Reports/Quarterly/missing.pdf',
            'Reports/Quarterly/encrypted.pdf',
        ], array_column($compactMediaResources['items'], 'part'));
        $t->same(['report-package'], $compactMediaResourceByPart['Reports/Quarterly/preview.png']['packageRolePrecedence']);
        $t->same(false, $compactMediaResourceByPart['Reports/Quarterly/preview.png']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $compactMediaResourceByPart['Reports/Quarterly/preview.png']['issues']);
        $t->same('report-package-bytes-blocked', $compactMediaResourcePrecedenceByPart['Reports/Quarterly/preview.png']['byteExposurePolicy']);
        $t->same(7, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['report']);
        $t->same(9, $inventory['reportPackagePartCount']);
        $t->same(0, $inventory['formPackagePartCount']);
        $t->same(9, $inventory['roleCounts']['report-package']);
        $t->same(false, array_key_exists('form-package', $inventory['roleCounts']));
        $t->same(2, $inventory['undeclaredRoleCounts']['report-package']);
        $t->same(['report-package', 'manifest-declared'], $inventory['parts']['Reports/Quarterly/preview.png']['roles']);
        $t->same(['report-package', 'undeclared-package-entry'], $inventory['parts']['Reports/Quarterly/orphan.pdf']['roles']);
        $t->same(['report-package', 'undeclared-package-entry'], $inventory['parts']['Reports/Quarterly/orphan.xlsx']['roles']);
        $t->same(['report-package', 'manifest-declared'], $inventory['parts']['Reports/Quarterly/source.docx']['roles']);
        $t->same(true, $inventory['parts']['Reports/Quarterly/preview.png']['reportPackagePart']);
        $t->same(true, $inventory['parts']['Reports/Quarterly/source.docx']['reportPackagePart']);
        $t->same(false, $inventory['parts']['Reports/Quarterly/preview.png']['formPackagePart'] ?? false);
        $t->same(0, $compactSummary['packageIdentity']['formPackagePartCount']);
        $t->same(9, $compactSummary['packageIdentity']['reportPackagePartCount']);
    },
];
