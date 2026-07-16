<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="attachment-date-utc"/></wp-export>';
$relatedPayload = '{"preview":"attachment-date-utc"}';
$sourceChecksum = md5($sourcePayload);
$relatedChecksum = md5($relatedPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Attachment Date Boundary Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(date-source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (date-source.xml) /Desc (Date-normalized source export) /AFRelationship /Source /RF << /F [(preview.json) 12 0 R] >> /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /CreationDate (D:20260605221530-07'30') /ModDate (D:20260606011530+05'45') >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> /CreationDate (D:20260605230000Z) /ModDate (D:20260605231631) >> /Length " . strlen($relatedPayload) . " >>\n"
    . "stream\n{$relatedPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? [];
$related = is_array($attachment) ? ($attachment['related_files'][0] ?? []) : [];
$file = $files[0] ?? [];
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (!is_array($attachment)
    || ($attachment['created_at_utc'] ?? null) !== '2026-06-06T05:45:30Z'
    || ($attachment['modified_at_utc'] ?? null) !== '2026-06-05T19:30:30Z'
    || !is_array($related)
    || ($related['created_at_utc'] ?? null) !== '2026-06-05T23:00:00Z'
    || array_key_exists('modified_at_utc', $related)
    || str_contains($summaryJson, $sourcePayload)
    || str_contains($summaryJson, $relatedPayload)
) {
    throw new RuntimeException('Expected attachment date UTC review metadata with payload bytes omitted.');
}

if (!is_array($file)
    || ($file['created_at_utc'] ?? null) !== '2026-06-06T05:45:30Z'
    || ($file['modified_at_utc'] ?? null) !== '2026-06-05T19:30:30Z'
    || !isset($file['related_files'][0])
    || ($file['related_files'][0]['created_at_utc'] ?? null) !== '2026-06-05T23:00:00Z'
    || array_key_exists('modified_at_utc', $file['related_files'][0])
) {
    throw new RuntimeException('Expected embedded-file extraction rows to preserve attachment UTC date metadata.');
}

echo '<!-- markerpdf-pdf-attachment-date-utc-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'EmbeddedFile Params CreationDate and ModDate UTC review metadata',
    'attachment_created_at' => $attachment['created_at'] ?? null,
    'attachment_created_at_utc' => $attachment['created_at_utc'] ?? null,
    'attachment_modified_at_utc' => $attachment['modified_at_utc'] ?? null,
    'related_created_at_utc' => $related['created_at_utc'] ?? null,
    'timezone_free_related_moddate_raw_only' => isset($related['modified_at']) && !array_key_exists('modified_at_utc', $related),
    'embedded_file_created_at_utc' => $file['created_at_utc'] ?? null,
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $sourcePayload) && !str_contains($summaryJson, $relatedPayload),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>') && !str_contains($plainText, 'attachment-date-utc'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";
echo '<!-- markerpdf:attachment-review ' . htmlspecialchars(json_encode([
    'filenames' => $summary['filenames'],
    'created_at_utc' => $attachment['created_at_utc'] ?? null,
    'modified_at_utc' => $attachment['modified_at_utc'] ?? null,
    'related_created_at_utc' => $related['created_at_utc'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
