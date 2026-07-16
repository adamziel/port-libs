<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-direct-af-filespec-smoke"/></wp-export>';
$catalogDuplicatePayload = '<wp-export><post id="duplicate-catalog-af-filespec-smoke"/></wp-export>';
$pageDuplicatePayload = '<wp-page><attachment role="duplicate-page-af-ef-smoke"/></wp-page>';
$pageDuplicateDecoyPayload = '<wp-page><attachment role="duplicate-page-af-decoy-smoke"/></wp-page>';
$validChecksum = md5($validPayload);
$catalogDuplicateChecksum = md5($catalogDuplicatePayload);
$pageDuplicateChecksum = md5($pageDuplicatePayload);
$pageDuplicateDecoyChecksum = md5($pageDuplicateDecoyPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Direct AF FileSpec Boundary Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF ["
    . "<< /Type /Filespec /F (catalog-current.xml) /F (catalog-stale.xml) /Desc (Duplicate direct catalog AF FileSpec) /AFRelationship /Source /EF << /F 11 0 R >> >> "
    . "<< /Type /Filespec /F (valid-direct-af.xml) /Desc (Valid direct catalog associated file) /AFRelationship /Data /EF << /F 21 0 R >> >>"
    . "] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /AF ["
    . "<< /Type /Filespec /F (page-current.xml) /Desc (Duplicate direct page AF EF keys) /AFRelationship /Supplement /EF << /F 31 0 R /F 32 0 R >> >>"
    . "] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogDuplicatePayload) . " /CheckSum <{$catalogDuplicateChecksum}> >> /Length " . strlen($catalogDuplicatePayload) . " >>\n"
    . "stream\n{$catalogDuplicatePayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260606025138Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageDuplicatePayload) . " /CheckSum <{$pageDuplicateChecksum}> >> /Length " . strlen($pageDuplicatePayload) . " >>\n"
    . "stream\n{$pageDuplicatePayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageDuplicateDecoyPayload) . " /CheckSum <{$pageDuplicateDecoyChecksum}> >> /Length " . strlen($pageDuplicateDecoyPayload) . " >>\n"
    . "stream\n{$pageDuplicateDecoyPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

if (!is_array($attachment)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-direct-af.xml'
    || ($attachment['source'] ?? null) !== 'catalog-associated-file'
    || ($attachment['associated_file_index'] ?? null) !== 1
    || ($attachment['checksum_matches'] ?? null) !== true
    || ($file['filename'] ?? null) !== 'valid-direct-af.xml'
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, 'catalog-stale.xml')
    || str_contains($summaryJson, 'page-current.xml')
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $catalogDuplicatePayload)
    || str_contains($summaryJson, $pageDuplicatePayload)
    || str_contains($summaryJson, $pageDuplicateDecoyPayload)
    || str_contains($filesJson, $catalogDuplicatePayload)
    || str_contains($filesJson, $pageDuplicatePayload)
    || str_contains($filesJson, $pageDuplicateDecoyPayload)
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, '<wp-page>')
) {
    throw new RuntimeException('Expected malformed direct /AF FileSpecs to fail closed while preserving the valid direct associated file.');
}

echo '<!-- markerpdf:attachment-direct-af-filespec-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-direct-associated-filespec-boundary',
    'native_boundary' => 'direct catalog/page /AF FileSpec dictionaries keep raw source text for duplicate key fail-closed review',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'source' => $attachment['source'],
    'associated_file_index' => $attachment['associated_file_index'],
    'stream_object_id' => $attachment['stream_object_id'],
    'direct_catalog_af_duplicate_rejected' => !str_contains($summaryJson, 'catalog-stale.xml'),
    'direct_page_af_duplicate_rejected' => !str_contains($summaryJson, 'page-current.xml'),
    'valid_direct_af_kept' => ($attachment['filename'] ?? null) === 'valid-direct-af.xml',
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, '<wp-page>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

$storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename']);
echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
