<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$duplicatePayload = '<wp-export><post id="duplicate-desc-smoke"/></wp-export>';
$validPayload = '<wp-export><post id="valid-desc-smoke"/></wp-export>';
$duplicateChecksum = md5($duplicatePayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (WordPress Attachment Description Boundary) Tj ET';
$directDuplicateFileSpec = '<< /Type /Filespec /F (duplicate-desc-smoke.xml) /Desc (Current duplicate smoke description) /Desc (Stale duplicate smoke description leak) /AFRelationship /Source /EF << /F 11 0 R >> >>';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [{$directDuplicateFileSpec} 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(duplicate-desc-smoke.xml) {$directDuplicateFileSpec} (valid-desc-smoke.xml) 20 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> /ModDate (D:20260608205704Z) >> /Length " . strlen($duplicatePayload) . " >>\n"
    . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-desc-smoke.xml) /Desc (Valid WordPress attachment description) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608205705Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

$duplicate = $summary['attachments'][0] ?? null;
$valid = $summary['attachments'][1] ?? null;

if (!is_array($duplicate)
    || !is_array($valid)
    || ($summary['attachment_count'] ?? null) !== 2
    || count($files) !== 2
    || ($duplicate['filename'] ?? null) !== 'duplicate-desc-smoke.xml'
    || ($duplicate['description'] ?? null) !== null
    || ($duplicate['description_status'] ?? null) !== 'malformed_filespec_description_omitted'
    || ($duplicate['checksum_matches'] ?? null) !== true
    || ($valid['filename'] ?? null) !== 'valid-desc-smoke.xml'
    || ($valid['description'] ?? null) !== 'Valid WordPress attachment description'
    || array_key_exists('bytes', $duplicate)
    || str_contains($summaryJson, 'Stale duplicate smoke description leak')
    || str_contains($filesJson, 'Stale duplicate smoke description leak')
    || str_contains($summaryJson, $duplicatePayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected malformed FileSpec descriptions to be omitted without suppressing attachment review rows.');
}

echo '<!-- markerpdf:attachment-description-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-filespec-description-boundary',
    'native_boundary' => 'duplicate FileSpec /Desc metadata is omitted while embedded payload review remains available',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'duplicate_description_status' => $duplicate['description_status'] ?? null,
    'valid_description_preserved' => ($valid['description'] ?? null) === 'Valid WordPress attachment description',
    'duplicate_checksum_matches' => $duplicate['checksum_matches'] ?? null,
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $duplicate),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($summary['attachments'] as $attachment) {
    $storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename']);
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
