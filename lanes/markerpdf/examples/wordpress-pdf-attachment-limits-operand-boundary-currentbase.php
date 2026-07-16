<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="smoke-valid-limits-sibling"/></wp-export>';
$tailedLimitsPayload = '<wp-export><post id="smoke-tailed-limits-node"/></wp-export>';
$tailDecoyPayload = '<wp-export><post id="smoke-limits-tail-decoy"/></wp-export>';
$validChecksum = md5($validPayload);
$tailedLimitsChecksum = md5($tailedLimitsPayload);
$tailDecoyChecksum = md5($tailDecoyPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Indirect Limits Operand Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(tailed-limits-smoke.xml) (valid-limits-smoke.xml)] /Kids [8 0 R 7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(valid-limits-smoke.xml) (valid-limits-smoke.xml)] /Names [(valid-limits-smoke.xml) 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Limits 50 0 R /Names [(tailed-limits-smoke.xml) 20 0 R (limits-tail-smoke-decoy.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (valid-limits-smoke.xml) /Desc (Valid sibling after tailed Limits smoke) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608153711Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (tailed-limits-smoke.xml) /Desc (Malformed tailed Limits smoke source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($tailedLimitsPayload) . " /CheckSum <{$tailedLimitsChecksum}> >> /Length " . strlen($tailedLimitsPayload) . " >>\n"
    . "stream\n{$tailedLimitsPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (limits-tail-smoke-decoy.xml) /Desc (Limits tail smoke decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($tailDecoyPayload) . " /CheckSum <{$tailDecoyChecksum}> >> /Length " . strlen($tailDecoyPayload) . " >>\n"
    . "stream\n{$tailDecoyPayload}\nendstream\nendobj\n"
    . "50 0 obj\n[(tailed-limits-smoke.xml) (tailed-limits-smoke.xml)] 30 0 R\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$attachment = $summary['attachments'][0] ?? null;

if (!is_array($attachment)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-limits-smoke.xml'
    || ($files[0]['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, 'tailed-limits-smoke.xml')
    || str_contains($filesJson, 'tailed-limits-smoke.xml')
    || str_contains($summaryJson, 'limits-tail-smoke-decoy.xml')
    || str_contains($filesJson, 'limits-tail-smoke-decoy.xml')
    || str_contains($summaryJson, $tailedLimitsPayload)
    || str_contains($filesJson, $tailedLimitsPayload)
    || str_contains($summaryJson, $tailDecoyPayload)
    || str_contains($filesJson, $tailDecoyPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected tailed indirect EmbeddedFiles Limits arrays to fail closed before WordPress attachment review.');
}

$metadata = [
    'support_component' => 'native-pdf-embeddedfiles-limits-array-boundary',
    'native_boundary' => 'EmbeddedFiles /Limits indirect array operands must resolve to one top-level array object',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'valid_sibling_preserved' => ($attachment['filename'] ?? null) === 'valid-limits-smoke.xml',
    'tailed_limits_node_rejected' => !str_contains($summaryJson, 'tailed-limits-smoke.xml')
        && !str_contains($filesJson, 'tailed-limits-smoke.xml'),
    'tail_operand_decoy_excluded' => !str_contains($summaryJson, 'limits-tail-smoke-decoy.xml')
        && !str_contains($filesJson, 'limits-tail-smoke-decoy.xml'),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
];

echo '<!-- markerpdf-pdf-attachment-limits-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

$storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename']);
echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
