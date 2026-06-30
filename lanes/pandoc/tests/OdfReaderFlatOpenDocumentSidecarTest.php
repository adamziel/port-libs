<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$flatTextBytes = '<office:document/>';
$databaseBytes = 'ODB-SIDECAR-DATA';
$flatSpreadsheetBytes = '<office:spreadsheet/>';
$flatPresentationBytes = '<office:presentation/>';
$flatTemplateBytes = '<office:template/>';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/source.fodt" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Attachments/Review/source.odb" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Reports/Summary/export.fods" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Reports/Summary/slides.fodp" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Templates/Review/letter.fodt" manifest:media-type=""/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Flat OpenDocument sidecars.</text:p>
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
    <dc:title>Flat OpenDocument Sidecars</dc:title>
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
    ['name' => 'Attachments/Review/source.fodt', 'data' => $flatTextBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/source.odb', 'data' => $databaseBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Summary/export.fods', 'data' => $flatSpreadsheetBytes, 'compressionMethod' => 0],
    ['name' => 'Reports/Summary/slides.fodp', 'data' => $flatPresentationBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/letter.fodt', 'data' => $flatTemplateBytes, 'compressionMethod' => 0],
], 'odt flat opendocument sidecars');

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
    'classifies flat OpenDocument sidecars as metadata-only package documents' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $flatTextBytes,
        $databaseBytes,
        $flatSpreadsheetBytes,
        $flatPresentationBytes,
        $flatTemplateBytes
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $attachments = $result['packageAttachments'];
        $reports = $result['packageReports'];
        $templates = $result['packageTemplates'];
        $attachmentItems = $indexBy($attachments['items'], 'part');
        $reportItems = $indexBy($reports['items'], 'part');
        $templateItems = $indexBy($templates['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');

        $t->same(2, $attachments['count']);
        $t->same(2, $attachments['readableCount']);
        $t->same(0, $attachments['issueCount']);
        $t->same(['attachment-document-resource' => 2], $attachments['kindCounts']);
        $t->same('application/vnd.oasis.opendocument.text-flat-xml', $attachmentItems['Attachments/Review/source.fodt']['mediaTypeBase']);
        $t->same('attachment-document-resource', $attachmentItems['Attachments/Review/source.fodt']['kind']);
        $t->same(strlen($flatTextBytes), $attachmentItems['Attachments/Review/source.fodt']['byteLength']);
        $t->same('attachment-package-bytes-blocked', $attachmentItems['Attachments/Review/source.fodt']['byteExposurePolicy']);
        $t->same(false, $attachmentItems['Attachments/Review/source.fodt']['canExposeBytes']);
        $t->same(false, $attachmentItems['Attachments/Review/source.fodt']['canExposeAsDocumentMedia']);
        $t->same('application/vnd.oasis.opendocument.database', $attachmentItems['Attachments/Review/source.odb']['mediaTypeBase']);
        $t->same('attachment-document-resource', $attachmentItems['Attachments/Review/source.odb']['kind']);
        $t->same(strlen($databaseBytes), $attachmentItems['Attachments/Review/source.odb']['byteLength']);

        $t->same(2, $reports['count']);
        $t->same(2, $reports['readableCount']);
        $t->same(0, $reports['issueCount']);
        $t->same(['report-document' => 2], $reports['kindCounts']);
        $t->same('application/vnd.oasis.opendocument.spreadsheet-flat-xml', $reportItems['Reports/Summary/export.fods']['mediaTypeBase']);
        $t->same('report-document', $reportItems['Reports/Summary/export.fods']['kind']);
        $t->same(strlen($flatSpreadsheetBytes), $reportItems['Reports/Summary/export.fods']['byteLength']);
        $t->same('report-package-bytes-blocked', $reportItems['Reports/Summary/export.fods']['byteExposurePolicy']);
        $t->same(false, $reportItems['Reports/Summary/export.fods']['canExposeAsDocumentMedia']);
        $t->same('application/vnd.oasis.opendocument.presentation-flat-xml', $reportItems['Reports/Summary/slides.fodp']['mediaTypeBase']);
        $t->same(strlen($flatPresentationBytes), $reportItems['Reports/Summary/slides.fodp']['byteLength']);

        $t->same(1, $templates['count']);
        $t->same(1, $templates['readableCount']);
        $t->same(0, $templates['issueCount']);
        $t->same(['template-document' => 1], $templates['kindCounts']);
        $t->same('application/vnd.oasis.opendocument.text-flat-xml', $templateItems['Templates/Review/letter.fodt']['mediaTypeBase']);
        $t->same('template-document', $templateItems['Templates/Review/letter.fodt']['kind']);
        $t->same(strlen($flatTemplateBytes), $templateItems['Templates/Review/letter.fodt']['byteLength']);
        $t->same('template-package-bytes-blocked', $templateItems['Templates/Review/letter.fodt']['byteExposurePolicy']);
        $t->same(false, $templateItems['Templates/Review/letter.fodt']['canExposeBytes']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(true, $manifestByPart['Attachments/Review/source.fodt']['attachmentPackagePart']);
        $t->same(true, $manifestByPart['Reports/Summary/export.fods']['reportPackagePart']);
        $t->same(true, $manifestByPart['Templates/Review/letter.fodt']['templatePackagePart']);
        $t->same(null, $manifestByPart['Attachments/Review/source.fodt']['byteLength']);
        $t->same('attachment-package-bytes-blocked', $manifestByPart['Attachments/Review/source.fodt']['byteExposurePolicy']);
        $t->same('report-package-bytes-blocked', $manifestByPart['Reports/Summary/export.fods']['byteExposurePolicy']);
        $t->same('template-package-bytes-blocked', $manifestByPart['Templates/Review/letter.fodt']['byteExposurePolicy']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Flat OpenDocument sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $flatTextBytes));
        $t->same(false, str_contains($blocks, $databaseBytes));
        $t->same(false, str_contains($blocks, $flatSpreadsheetBytes));
        $t->same(false, str_contains($blocks, $flatPresentationBytes));
        $t->same(false, str_contains($blocks, $flatTemplateBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactAttachments = $compactSummary['packageAttachments'];
        $compactReports = $compactSummary['packageReports'];
        $compactTemplates = $compactSummary['packageTemplates'];
        $compactAttachmentItems = $indexBy($compactAttachments['items'], 'packagePath');
        $compactReportItems = $indexBy($compactReports['items'], 'packagePath');
        $compactTemplateItems = $indexBy($compactTemplates['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');

        $t->same($attachments['kindCounts'], $compactAttachments['kindCounts']);
        $t->same($reports['kindCounts'], $compactReports['kindCounts']);
        $t->same($templates['kindCounts'], $compactTemplates['kindCounts']);
        $t->same('application/vnd.oasis.opendocument.text-flat-xml', $compactAttachmentItems['Attachments/Review/source.fodt']['mediaTypeBase']);
        $t->same('application/vnd.oasis.opendocument.database', $compactAttachmentItems['Attachments/Review/source.odb']['mediaTypeBase']);
        $t->same('application/vnd.oasis.opendocument.spreadsheet-flat-xml', $compactReportItems['Reports/Summary/export.fods']['mediaTypeBase']);
        $t->same('application/vnd.oasis.opendocument.presentation-flat-xml', $compactReportItems['Reports/Summary/slides.fodp']['mediaTypeBase']);
        $t->same('application/vnd.oasis.opendocument.text-flat-xml', $compactTemplateItems['Templates/Review/letter.fodt']['mediaTypeBase']);
        $t->same(false, $compactAttachmentItems['Attachments/Review/source.fodt']['canExposeBytes']);
        $t->same(false, $compactReportItems['Reports/Summary/export.fods']['canExposeAsDocumentMedia']);
        $t->same(false, $compactTemplateItems['Templates/Review/letter.fodt']['canExposeBytes']);
        $t->same('attachment', $reviewByPath['Attachments/Review/source.fodt']['manifestMediaFamily']);
        $t->same('report', $reviewByPath['Reports/Summary/export.fods']['manifestMediaFamily']);
        $t->same('template', $reviewByPath['Templates/Review/letter.fodt']['manifestMediaFamily']);
        $t->same(2, $compactSummary['manifestReview']['attachmentPackagePartCount']);
        $t->same(2, $compactSummary['manifestReview']['reportPackagePartCount']);
        $t->same(1, $compactSummary['manifestReview']['templatePackagePartCount']);
        $t->same(2, $compactSummary['packageInventory']['attachmentPackagePartCount']);
        $t->same(2, $compactSummary['packageInventory']['reportPackagePartCount']);
        $t->same(1, $compactSummary['packageInventory']['templatePackagePartCount']);
    },
];
