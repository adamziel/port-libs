<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-indirect-af-array-smoke"/></wp-export>';
$catalogPayload = '<wp-export><post id="catalog-indirect-af-array-smoke-decoy"/></wp-export>';
$pagePayload = '<wp-page><attachment role="page-indirect-af-array-smoke-decoy"/></wp-page>';
$annotationPayload = '<wp-annotation><attachment role="annotation-indirect-af-array-smoke-decoy"/></wp-annotation>';
$validChecksum = md5($validPayload);
$catalogChecksum = md5($catalogPayload);
$pageChecksum = md5($pagePayload);
$annotationChecksum = md5($annotationPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Indirect AF Array Boundary Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R] /AF 60 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(valid-indirect-af-array.xml) 30 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 700 96 724] /Contents (Malformed indirect annotation AF array) /AF 70 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (catalog-indirect-af-array-decoy.xml) /Desc (Catalog indirect AF array decoy) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> >> /Length " . strlen($catalogPayload) . " >>\nstream\n{$catalogPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (valid-indirect-af-array.xml) /Desc (Valid EmbeddedFiles source after malformed AF arrays) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608091157Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (page-indirect-af-array-decoy.xml) /Desc (Page indirect AF array decoy) /AFRelationship /Supplement /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pagePayload) . " /CheckSum <{$pageChecksum}> >> /Length " . strlen($pagePayload) . " >>\nstream\n{$pagePayload}\nendstream\nendobj\n"
    . "42 0 obj\n<< /Type /Filespec /F (annotation-indirect-af-array-decoy.xml) /Desc (Annotation indirect AF array decoy) /AFRelationship /Alternative /EF << /F 43 0 R >> >>\nendobj\n"
    . "43 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($annotationPayload) . " /CheckSum <{$annotationChecksum}> >> /Length " . strlen($annotationPayload) . " >>\nstream\n{$annotationPayload}\nendstream\nendobj\n"
    . "50 0 obj\n[10 0 R] 20 0 R\nendobj\n"
    . "60 0 obj\n[40 0 R] 20 0 R\nendobj\n"
    . "70 0 obj\n[42 0 R] 20 0 R\nendobj\n"
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
    || ($attachment['source'] ?? null) !== 'embedded-files-name-tree'
    || ($attachment['filename'] ?? null) !== 'valid-indirect-af-array.xml'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('associated_file', $attachment)
    || ($file['source'] ?? null) !== 'catalog_names_embedded_files'
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $catalogPayload)
    || str_contains($summaryJson, $pagePayload)
    || str_contains($summaryJson, $annotationPayload)
    || str_contains($filesJson, $catalogPayload)
    || str_contains($filesJson, $pagePayload)
    || str_contains($filesJson, $annotationPayload)
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, '<wp-page>')
    || str_contains($plainText, '<wp-annotation>')
) {
    throw new RuntimeException('Expected indirect /AF array boundary to keep only the safe EmbeddedFiles attachment.');
}

echo '<!-- markerpdf:attachment-indirect-af-array-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-associated-file-array-boundary',
    'native_boundary' => 'indirect catalog/page/annotation /AF arrays must resolve to one top-level array object',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'source' => $attachment['source'],
    'malformed_catalog_af_array_rejected' => !str_contains($summaryJson, 'catalog-indirect-af-array-decoy.xml'),
    'malformed_page_af_array_rejected' => !str_contains($summaryJson, 'page-indirect-af-array-decoy.xml'),
    'malformed_annotation_af_array_rejected' => !str_contains($summaryJson, 'annotation-indirect-af-array-decoy.xml'),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, '<wp-page>')
        && !str_contains($plainText, '<wp-annotation>'),
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
