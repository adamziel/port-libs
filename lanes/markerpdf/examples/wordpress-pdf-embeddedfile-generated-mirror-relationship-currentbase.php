<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="generated-mirror-relationship-smoke"/></wp-export>';
$checksum = md5($payload);
$content = 'BT /F1 12 Tf 72 720 Td (Generated Mirror Relationship Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF ["
    . "<< /Type /Filespec /AFRelationship /Source /EF << /F 11 0 R >> >>"
    . "] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) << /Type /Filespec /Desc (Name-tree source without relationship) /EF << /F 11 0 R >> >>] >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260608165030Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$file = $files[0] ?? null;
$attachment = $summary['attachments'][0] ?? null;
$provenance = is_array($file) ? ($file['associated_file_provenance_review'] ?? null) : null;

if (!is_array($file)
    || !is_array($attachment)
    || !is_array($provenance)
    || count($files) !== 1
    || ($summary['attachment_count'] ?? null) !== 1
    || ($file['filename'] ?? null) !== 'source.xml'
    || ($file['relationship'] ?? null) !== 'Source'
    || ($file['associated_file'] ?? null) !== true
    || ($file['associated_file_source'] ?? null) !== 'catalog_af'
    || ($provenance['relationship_role'] ?? null) !== 'original_source'
    || ($attachment['relationship_role'] ?? null) !== 'original_source'
    || array_key_exists('bytes', $attachment)
    || str_contains($summaryJson, $payload)
    || substr_count($filesJson, 'embedded-file') !== 0
    || $plainText !== 'Generated Mirror Relationship Smoke Body'
    || ($summary['executes_python_or_models'] ?? true) !== false
    || ($summary['executes_external_pdf_tools'] ?? true) !== false
) {
    throw new RuntimeException('Expected generated catalog AF mirror to merge relationship provenance into the named EmbeddedFiles row without payload leakage.');
}

echo '<!-- markerpdf-pdf-embeddedfile-generated-mirror-relationship ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfile-generated-mirror-dedupe',
    'native_boundary' => 'catalog AF generated-name mirror merges into named EmbeddedFiles row',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $file['filename'],
    'relationship' => $file['relationship'],
    'relationship_role' => $provenance['relationship_role'],
    'associated_file_source' => $file['associated_file_source'],
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $payload),
    'generated_filename_row_suppressed' => substr_count($filesJson, 'embedded-file') === 0,
    'visible_text_preserved' => $plainText === 'Generated Mirror Relationship Smoke Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
