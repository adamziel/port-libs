<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$duplicatePayload = '<wp-export><post id="duplicate-afrelationship-smoke"/></wp-export>';
$trailingPayload = '<wp-export><post id="trailing-afrelationship-smoke"/></wp-export>';
$validPayload = '<wp-export><post id="valid-afrelationship-smoke"/></wp-export>';
$duplicateChecksum = md5($duplicatePayload);
$trailingChecksum = md5($trailingPayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Visible AFRelationship Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(duplicate-afrelationship-smoke.xml) 10 0 R (trailing-afrelationship-smoke.xml) 20 0 R (valid-afrelationship-smoke.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (duplicate-afrelationship-smoke.xml) /Desc (Duplicate relationship smoke source) /AFRelationship /Source /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
    . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (trailing-afrelationship-smoke.xml) /Desc (Trailing relationship operand smoke source) /AFRelationship /Alternative 99 0 R /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingPayload) . " /CheckSum <{$trailingChecksum}> >> /Length " . strlen($trailingPayload) . " >>\n"
    . "stream\n{$trailingPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (valid-afrelationship-smoke.xml) /Desc (Valid relationship smoke source) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608061449Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

if (($summary['attachment_count'] ?? null) !== 1 || count($files) !== 1) {
    throw new RuntimeException('Expected only the unambiguous AFRelationship attachment to survive.');
}

$attachment = $summary['attachments'][0] ?? [];
$file = $files[0] ?? [];
if (
    ($attachment['filename'] ?? null) !== 'valid-afrelationship-smoke.xml'
    || ($attachment['relationship'] ?? null) !== 'Source'
    || ($attachment['relationship_status'] ?? null) !== 'standard_pdf_associated_file_relationship'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $validPayload
) {
    throw new RuntimeException('Expected valid Source attachment metadata without payload bytes in the summary.');
}

foreach ([
    'duplicate-afrelationship-smoke.xml',
    'trailing-afrelationship-smoke.xml',
    'Duplicate relationship smoke source',
    'Trailing relationship operand smoke source',
    $duplicatePayload,
    $trailingPayload,
    $duplicateChecksum,
    $trailingChecksum,
] as $hidden) {
    if (
        !is_string($summaryJson)
        || !is_string($filesJson)
        || str_contains($summaryJson, $hidden)
        || str_contains($filesJson, $hidden)
        || str_contains($plainText, $hidden)
    ) {
        throw new RuntimeException('Expected ambiguous AFRelationship rows to stay out of WordPress review output.');
    }
}

if ($plainText !== 'Visible AFRelationship Smoke Body' || str_contains($plainText, '<wp-export>')) {
    throw new RuntimeException('Expected visible page text without embedded payload leakage.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf:embedded-files-afrelationship-boundary ' . $htmlJson([
    'support_component' => 'native-pdf-embedded-files-attachment-parser',
    'native_boundary' => 'duplicate and trailing FileSpec /AFRelationship operands fail closed before WordPress attachment import',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'relationship_status' => $attachment['relationship_status'] ?? null,
    'checksum_matches' => $attachment['checksum_matches'] ?? null,
    'summary_exposes_payload_bytes' => array_key_exists('bytes', $attachment),
    'ambiguous_rows_suppressed' => is_string($summaryJson)
        && is_string($filesJson)
        && !str_contains($summaryJson, 'duplicate-afrelationship-smoke')
        && !str_contains($filesJson, 'trailing-afrelationship-smoke'),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
