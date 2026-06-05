<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = "Title,Status\nIndirect Name Key,Ready\n";
$stalePayload = "Title,Status\nStale Name Key,Ignore\n";
$checksum = md5($payload);
$staleChecksum = md5($stalePayload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Indirect Attachment Boundary) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [8 0 R 8 0 R] /Names [8 0 R 10 0 R (zz-stale-key.csv) 12 0 R] >>\nendobj\n"
    . "8 0 obj\n(indirect-key.csv)\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-indirect-key.csv) /UF (indirect-key.csv) /Desc (Indirect name-key WordPress rows) /AFRelationship /Data /EF << /UF 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605093241Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (zz-stale-key.csv) /Desc (Stale out-of-limits name-key rows) /AFRelationship /Alternative /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

if (!is_array($attachment) || !is_string($summaryJson)) {
    throw new RuntimeException('Expected indirect name-key attachment summary.');
}

$metadata = [
    'native_boundary' => 'EmbeddedFiles name-tree indirect string-key attachment preflight',
    'attachment_count' => $summary['attachment_count'] ?? null,
    'filename' => $attachment['filename'] ?? null,
    'name_key' => $attachment['name_key'] ?? null,
    'indirect_name_key_resolved' => ($attachment['name_key'] ?? null) === 'indirect-key.csv',
    'ef_key' => $attachment['ef_key'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'declared_size_matches' => $attachment['declared_size_matches'] ?? null,
    'checksum_matches' => $attachment['checksum_matches'] ?? null,
    'stale_out_of_limits_attachment_excluded' => !str_contains($summaryJson, 'zz-stale-key.csv')
        && !str_contains($summaryJson, $stalePayload),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment)
        && !str_contains($summaryJson, $payload),
    'visible_text' => $plainText,
    'executes_python_or_models' => $summary['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'] ?? null,
];

echo '<!-- markerpdf:attachment-indirect-name-key-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
