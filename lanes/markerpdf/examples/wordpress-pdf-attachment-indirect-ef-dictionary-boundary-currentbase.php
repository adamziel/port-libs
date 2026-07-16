<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$malformedPayload = '<wp-export><post id="indirect-ef-dictionary-smoke-prefix"/></wp-export>';
$malformedDecoyPayload = '<wp-export><post id="indirect-ef-dictionary-smoke-decoy"/></wp-export>';
$validPayload = '<wp-export><post id="valid-indirect-ef-dictionary-smoke"/></wp-export>';
$malformedChecksum = md5($malformedPayload);
$malformedDecoyChecksum = md5($malformedDecoyPayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Indirect EF Dictionary Boundary Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(malformed-indirect-ef-smoke.xml) 10 0 R (valid-indirect-ef-smoke.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (malformed-indirect-ef-smoke.xml) /Desc (Malformed indirect EF dictionary smoke) /AFRelationship /Source /EF 50 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\nstream\n{$malformedPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedDecoyPayload) . " /CheckSum <{$malformedDecoyChecksum}> >> /Length " . strlen($malformedDecoyPayload) . " >>\nstream\n{$malformedDecoyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-indirect-ef-smoke.xml) /Desc (Valid indirect EF dictionary smoke) /AFRelationship /Data /EF 60 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608161120Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
    . "50 0 obj\n<< /F 11 0 R >> 12 0 R\nendobj\n"
    . "60 0 obj\n<< /F 21 0 R >>\nendobj\n"
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
    || ($attachment['filename'] ?? null) !== 'valid-indirect-ef-smoke.xml'
    || ($attachment['stream_object_id'] ?? null) !== 21
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, 'malformed-indirect-ef-smoke.xml')
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $malformedPayload)
    || str_contains($summaryJson, $malformedDecoyPayload)
    || str_contains($filesJson, $malformedPayload)
    || str_contains($filesJson, $malformedDecoyPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected indirect /EF dictionary object boundary to keep only the clean attachment.');
}

echo '<!-- markerpdf:attachment-indirect-ef-dictionary-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-filespec-ef-dictionary-boundary',
    'native_boundary' => 'indirect FileSpec /EF dictionaries must resolve to one top-level dictionary object',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'stream_object_id' => $attachment['stream_object_id'],
    'malformed_indirect_ef_dictionary_rejected' => !str_contains($summaryJson, 'malformed-indirect-ef-smoke.xml')
        && !str_contains($filesJson, $malformedPayload),
    'valid_indirect_ef_dictionary_preserved' => ($file['content'] ?? null) === $validPayload,
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
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
