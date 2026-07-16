<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-duplicate-filespec-key-boundary-smoke"/></wp-export>';
$staleFileSpecPayload = '<wp-export><post id="stale-duplicate-filespec-f-key-smoke"/></wp-export>';
$staleEfPayload = '<wp-export><post id="stale-duplicate-ef-key-smoke"/></wp-export>';
$validChecksum = md5($validPayload);
$staleFileSpecChecksum = md5($staleFileSpecPayload);
$staleEfChecksum = md5($staleEfPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Duplicate FileSpec Key Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(duplicate-filespec.xml) 10 0 R (duplicate-ef.xml) 20 0 R (valid-source.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (current-filespec.xml) /F (stale-filespec.xml) /Desc (Duplicate FileSpec filename keys) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($staleFileSpecPayload) . " /CheckSum <{$staleFileSpecChecksum}> >> /Length " . strlen($staleFileSpecPayload) . " >>\n"
    . "stream\n{$staleFileSpecPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (current-ef.xml) /Desc (Duplicate EF stream keys) /AFRelationship /Source /EF << /F 21 0 R /F 22 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($staleEfPayload) . " /CheckSum <{$staleEfChecksum}> >> /Length " . strlen($staleEfPayload) . " >>\n"
    . "stream\n{$staleEfPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid WordPress source after duplicate FileSpecs) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260605214335Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

if (
    !is_array($attachment)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-source.xml'
    || ($attachment['file_spec_object_id'] ?? null) !== 30
    || ($attachment['stream_object_id'] ?? null) !== 31
    || ($attachment['checksum_matches'] ?? null) !== true
    || ($file['filename'] ?? null) !== 'valid-source.xml'
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, 'stale-filespec.xml')
    || str_contains($summaryJson, 'current-ef.xml')
    || str_contains($summaryJson, $staleFileSpecChecksum)
    || str_contains($summaryJson, $staleEfChecksum)
    || str_contains($summaryJson, $validPayload)
    || str_contains($filesJson, $staleFileSpecPayload)
    || str_contains($filesJson, $staleEfPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected duplicate FileSpec and EF keys to fail closed before WordPress attachment preflight.');
}

echo '<!-- markerpdf:attachment-duplicate-filespec-key-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF FileSpec duplicate filename and EF key attachment preflight',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'duplicate_filespec_key_rejected' => !str_contains($summaryJson, 'stale-filespec.xml'),
    'duplicate_ef_key_rejected' => !str_contains($summaryJson, 'current-ef.xml'),
    'valid_attachment_preserved' => ($attachment['filename'] ?? null) === 'valid-source.xml',
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
