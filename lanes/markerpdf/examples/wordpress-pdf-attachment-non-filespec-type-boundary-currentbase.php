<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-untyped-filespec-boundary-smoke"/></wp-export>';
$pageDecoyPayload = '<wp-export><post id="typed-page-decoy-attachment-smoke"/></wp-export>';
$catalogDecoyPayload = '<wp-export><post id="typed-catalog-decoy-attachment-smoke"/></wp-export>';
$validChecksum = md5($validPayload);
$pageDecoyChecksum = md5($pageDecoyPayload);
$catalogDecoyChecksum = md5($catalogDecoyPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Non FileSpec Type Boundary Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [20 0 R 30 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names ["
    . "(typed-page-decoy.xml) 10 0 R "
    . "(typed-catalog-decoy.xml) << /Type /Catalog /F (typed-catalog-decoy.xml) /Desc (Typed catalog decoy attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >> "
    . "(valid-untyped.xml) 30 0 R"
    . "] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Page /F (typed-page-decoy.xml) /Desc (Typed page decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageDecoyPayload) . " /CheckSum <{$pageDecoyChecksum}> >> /Length " . strlen($pageDecoyPayload) . " >>\n"
    . "stream\n{$pageDecoyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Catalog /F (catalog-af-decoy.xml) /Desc (Typed catalog AF decoy) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogDecoyPayload) . " /CheckSum <{$catalogDecoyChecksum}> >> /Length " . strlen($catalogDecoyPayload) . " >>\n"
    . "stream\n{$catalogDecoyPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /F (valid-untyped.xml) /Desc (Valid untyped legacy FileSpec) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260606013753Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (!is_array($attachment)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-untyped.xml'
    || ($attachment['relationship'] ?? null) !== 'Source'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, 'typed-page-decoy.xml')
    || str_contains($summaryJson, 'typed-catalog-decoy.xml')
    || str_contains($summaryJson, 'catalog-af-decoy.xml')
    || str_contains($summaryJson, $pageDecoyPayload)
    || str_contains($summaryJson, $catalogDecoyPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $pageDecoyChecksum)
    || str_contains($summaryJson, $catalogDecoyChecksum)
    || str_contains($filesJson, $pageDecoyPayload)
    || str_contains($filesJson, $catalogDecoyPayload)
    || $plainText !== 'Non FileSpec Type Boundary Smoke Body'
) {
    throw new RuntimeException('Expected typed non-FileSpec dictionaries to fail closed before WordPress attachment rendering.');
}

echo "<!-- markerpdf-pdf-attachment-non-filespec-type-boundary " . htmlspecialchars(json_encode([
    'native_boundary' => 'typed non-FileSpec dictionaries with /EF are excluded from attachment import',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'relationship' => $attachment['relationship'],
    'relationship_role' => $attachment['relationship_role'],
    'checksum_matches' => $attachment['checksum_matches'],
    'typed_page_decoy_rejected' => !str_contains($summaryJson, 'typed-page-decoy.xml'),
    'typed_catalog_decoy_rejected' => !str_contains($summaryJson, 'typed-catalog-decoy.xml'),
    'untyped_legacy_filespec_preserved' => ($file['filename'] ?? null) === 'valid-untyped.xml',
    'payload_omitted_from_summary' => !str_contains($summaryJson, $validPayload),
    'visible_text_preserved' => $plainText === 'Non FileSpec Type Boundary Smoke Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:file {\"href\":\"media/" . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"} -->\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
