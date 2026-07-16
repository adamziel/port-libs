<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="direct-nameless-filespec-smoke"/></wp-export>';
$checksum = md5($payload);
$content = 'BT /F1 12 Tf 72 720 Td (Direct FileSpec Mirror Smoke Body) Tj ET';
$fileSpec = '<< /Type /Filespec /Desc (Direct nameless smoke source) /AFRelationship /Source /EF << /F 11 0 R >> >>';
$catalogMirror = '<< /Type /Filespec /Desc (Catalog nameless mirror should not duplicate) /AFRelationship /Source /EF << /F 11 0 R >> >>';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [{$catalogMirror}] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(wp-source.xml) {$fileSpec}] >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605172105Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;

if (!is_array($attachment)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'wp-source.xml'
    || ($attachment['filename_source'] ?? null) !== 'name_tree_key'
    || ($attachment['associated_file_source'] ?? null) !== 'catalog_af'
    || ($file['filename_source'] ?? null) !== 'name_tree_key'
    || ($file['content'] ?? null) !== $payload
    || str_contains(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '', 'attachment-11')
    || str_contains(json_encode($files, JSON_UNESCAPED_SLASHES) ?: '', 'embedded-file')
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected direct nameless FileSpec mirrors to collapse onto the EmbeddedFiles name-tree attachment.');
}

echo '<!-- markerpdf:attachment-direct-filespec-nametree-mirror ' . htmlspecialchars(json_encode([
    'native_boundary' => 'direct nameless FileSpec mirrors collapse by embedded stream while preserving EmbeddedFiles name-tree filename',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'filename_source' => $attachment['filename_source'],
    'associated_file_source' => $attachment['associated_file_source'] ?? null,
    'stream_object_id' => $attachment['stream_object_id'],
    'file_spec_object_id' => $attachment['file_spec_object_id'],
    'checksum_matches' => $attachment['checksum_matches'],
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
