<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$badEfPayload = '<wp-export><post id="malformed-ef-smoke"/></wp-export>';
$badEfDecoyPayload = '<wp-export><post id="malformed-ef-decoy-smoke"/></wp-export>';
$validPayload = '<wp-export><post id="valid-trailing-operand-smoke"/></wp-export>';
$relatedPrimaryPayload = '<wp-export><post id="related-primary-trailing-operand-smoke"/></wp-export>';
$relatedSidecarPayload = 'RELATED_TRAILING_OPERAND_SIDECAR_SHOULD_NOT_LEAK';
$badEfChecksum = md5($badEfPayload);
$badEfDecoyChecksum = md5($badEfDecoyPayload);
$validChecksum = md5($validPayload);
$relatedPrimaryChecksum = md5($relatedPrimaryPayload);
$relatedSidecarChecksum = md5($relatedSidecarPayload);
$content = 'BT /F1 12 Tf 72 720 Td (WordPress Attachment Trailing Operand Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(malformed-ef.xml) 10 0 R (valid-source.xml) 20 0 R (related-primary.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (malformed-ef.xml) /Desc (Malformed EF smoke source) /AFRelationship /Source /EF << /F 11 0 R 12 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badEfPayload) . " /CheckSum <{$badEfChecksum}> >> /Length " . strlen($badEfPayload) . " >>\n"
    . "stream\n{$badEfPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badEfDecoyPayload) . " /CheckSum <{$badEfDecoyChecksum}> >> /Length " . strlen($badEfDecoyPayload) . " >>\n"
    . "stream\n{$badEfDecoyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid source smoke) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607154020Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (related-primary.xml) /Desc (Malformed RF smoke source) /AFRelationship /Supplement /EF << /F 31 0 R >> /RF << /F [(sidecar.css) 32 0 R] 33 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($relatedPrimaryPayload) . " /CheckSum <{$relatedPrimaryChecksum}> >> /Length " . strlen($relatedPrimaryPayload) . " >>\n"
    . "stream\n{$relatedPrimaryPayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedSidecarPayload) . " /CheckSum <{$relatedSidecarChecksum}> >> /Length " . strlen($relatedSidecarPayload) . " >>\n"
    . "stream\n{$relatedSidecarPayload}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Length 5 >>\nstream\nDECOY\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$valid = $summary['attachments'][0] ?? null;
$relatedPrimary = $summary['attachments'][1] ?? null;
if (!is_array($valid)
    || !is_array($relatedPrimary)
    || ($summary['attachment_count'] ?? null) !== 2
    || ($valid['filename'] ?? null) !== 'valid-source.xml'
    || ($valid['checksum_matches'] ?? null) !== true
    || ($relatedPrimary['filename'] ?? null) !== 'related-primary.xml'
    || ($relatedPrimary['checksum_matches'] ?? null) !== true
    || array_key_exists('related_files', $relatedPrimary)
    || count($files) !== 2
    || str_contains($summaryJson, 'malformed-ef.xml')
    || str_contains($summaryJson, $badEfPayload)
    || str_contains($summaryJson, $badEfDecoyPayload)
    || str_contains($summaryJson, $relatedSidecarPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($filesJson, $badEfPayload)
    || str_contains($filesJson, $badEfDecoyPayload)
    || str_contains($filesJson, $relatedSidecarPayload)
    || str_contains($plainText, '<wp-export>')
    || $plainText !== 'WordPress Attachment Trailing Operand Body'
) {
    throw new RuntimeException('Expected attachment trailing operands to fail closed while preserving valid WordPress attachments.');
}

echo '<!-- markerpdf-pdf-attachment-filespec-trailing-operand-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-filespec-attachment-boundary',
    'native_boundary' => 'FileSpec EF and RF dictionaries reject unkeyed trailing operands before WordPress attachment review',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'malformed_ef_attachment_excluded' => !str_contains($summaryJson, 'malformed-ef.xml')
        && !str_contains($filesJson, $badEfPayload),
    'malformed_rf_related_files_suppressed' => !array_key_exists('related_files', $relatedPrimary)
        && !str_contains($summaryJson, $relatedSidecarPayload)
        && !str_contains($filesJson, $relatedSidecarPayload),
    'valid_attachment_checksum_matches' => $valid['checksum_matches'],
    'related_primary_checksum_matches' => $relatedPrimary['checksum_matches'],
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

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
