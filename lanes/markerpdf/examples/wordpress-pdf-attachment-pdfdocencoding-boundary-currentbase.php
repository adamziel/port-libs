<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="pdfdoc-encoded-attachment"/></wp-export>';
$relatedPayload = 'body{font-family:serif}';
$stalePayload = '<wp-export><post id="stale-pdfdoc-attachment"/></wp-export>';
$checksum = md5($payload);
$relatedChecksum = md5($relatedPayload);
$staleChecksum = md5($stalePayload);
$content = 'BT /F1 12 Tf 72 720 Td (PDFDoc Attachment Boundary Body) Tj ET';

$nameKeyBytes = 'Review ' . chr(0x8d) . 'Attachment' . chr(0x8e);
$filenameBytes = 'WP' . chr(0x80) . '-Import' . chr(0x81) . '.xml';
$relatedNameBytes = 'review' . chr(0x8d) . 'style' . chr(0x8e) . '.css';
$staleNameBytes = 'ZZ' . chr(0x80) . 'Stale';

$nameKeyHex = strtoupper(bin2hex($nameKeyBytes));
$filenameHex = strtoupper(bin2hex($filenameBytes));
$relatedNameHex = strtoupper(bin2hex($relatedNameBytes));
$staleNameHex = strtoupper(bin2hex($staleNameBytes));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Limits [<{$nameKeyHex}> <{$nameKeyHex}>] /Names [<{$nameKeyHex}> 10 0 R <{$staleNameHex}> 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-import.xml) /UF <{$filenameHex}> /Desc (PDFDocEncoding WordPress source) /AFRelationship /Source /EF << /UF 11 0 R >> /RF << /UF [<{$relatedNameHex}> 12 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605152458Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\n"
    . "stream\n{$relatedPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (stale-pdfdoc.xml) /Desc (Stale PDFDocEncoding attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

if (!is_array($attachment) || !is_string($summaryJson)) {
    throw new RuntimeException('Expected PDFDocEncoding attachment summary.');
}

$metadata = [
    'native_boundary' => 'EmbeddedFiles PDFDocEncoding name-tree and FileSpec filename attachment review',
    'attachment_count' => $summary['attachment_count'] ?? null,
    'name_key' => $attachment['name_key'] ?? null,
    'filename' => $attachment['filename'] ?? null,
    'filename_storage_name' => $attachment['filename_storage_name'] ?? null,
    'related_filename' => $attachment['related_files'][0]['related_filename'] ?? null,
    'pdfdocencoding_names_decoded' => ($attachment['name_key'] ?? null) === "Review \u{201C}Attachment\u{201D}"
        && ($attachment['filename'] ?? null) === "WP\u{2022}-Import\u{2020}.xml",
    'stale_out_of_limits_attachment_excluded' => !str_contains($summaryJson, 'stale-pdfdoc.xml')
        && !str_contains($summaryJson, $stalePayload),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment)
        && !str_contains($summaryJson, $payload)
        && !str_contains($summaryJson, $relatedPayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => $summary['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'] ?? null,
];

echo '<!-- markerpdf:attachment-pdfdocencoding-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
