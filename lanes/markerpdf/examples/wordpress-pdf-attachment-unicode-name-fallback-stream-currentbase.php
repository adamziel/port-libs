<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="unicode-name-fallback-stream"/></wp-export>';
$checksum = md5($payload);
$content = 'BT /F1 12 Tf 72 720 Td (Unicode Name Fallback Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(legacy-wordpress-source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-wordpress-source.xml) /UF (wordpress-source-unicode.xml) /Desc (Unicode filename with F-only embedded stream) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260608073746Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

$attachment = $summary['attachments'][0] ?? [];
$file = $files[0] ?? [];

if (($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'wordpress-source-unicode.xml'
    || ($attachment['filename_source'] ?? null) !== 'UF'
    || ($attachment['ef_key'] ?? null) !== 'F'
    || ($attachment['ef_key_selection_status'] ?? null) !== 'fallback_embedded_file_key'
    || ($attachment['ef_key_preferred_source'] ?? null) !== 'UF'
    || ($file['content'] ?? null) !== $payload
    || ($file['ef_key_selection_status'] ?? null) !== 'fallback_embedded_file_key'
    || str_contains($summaryJson, $payload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected Unicode filename fallback stream metadata without leaking embedded attachment payload into WordPress output.');
}

echo '<!-- markerpdf:attachment-unicode-name-fallback-stream ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embedded-file-attachment-parser',
    'native_boundary' => 'FileSpec /UF filename preserved while /EF /F payload stream is selected as a reviewed fallback',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'] ?? null,
    'filename_source' => $attachment['filename_source'] ?? null,
    'ef_key' => $attachment['ef_key'] ?? null,
    'ef_key_selection_status' => $attachment['ef_key_selection_status'] ?? null,
    'ef_key_preferred_source' => $attachment['ef_key_preferred_source'] ?? null,
    'unicode_filename_preserved' => ($attachment['unicode_filename'] ?? null) === 'wordpress-source-unicode.xml',
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'visible_text_excludes_payload' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) ($attachment['filename_storage_name'] ?? $attachment['filename']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) ($attachment['filename_storage_name'] ?? $attachment['filename']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
