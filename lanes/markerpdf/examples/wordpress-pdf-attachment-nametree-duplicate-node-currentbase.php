<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cleanPayload = '<wp-export><post id="clean-name-tree-node-smoke"/></wp-export>';
$stalePayload = '<wp-export><post id="stale-duplicate-node-smoke"/></wp-export>';
$malformedPayload = '<wp-export><post id="malformed-duplicate-node-smoke"/></wp-export>';
$cleanChecksum = md5($cleanPayload);
$staleChecksum = md5($stalePayload);
$malformedChecksum = md5($malformedPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Duplicate Node Smoke) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(clean.xml) (malformed.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(clean.xml) (clean.xml)] /Names [(clean.xml) 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(malformed.xml) (malformed.xml)] /Names [(stale.xml) 20 0 R] /Names [(malformed.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (clean.xml) /Desc (Clean sibling attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($cleanPayload) . " /CheckSum <{$cleanChecksum}> >> /Length " . strlen($cleanPayload) . " >>\n"
    . "stream\n{$cleanPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (stale.xml) /Desc (Stale duplicate name-tree node attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (malformed.xml) /Desc (Malformed duplicate name-tree node attachment) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\n"
    . "stream\n{$malformedPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

if (
    ($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['clean.xml']
    || array_column($files, 'filename') !== ['clean.xml']
    || $plainText !== 'Visible Attachment Duplicate Node Smoke'
    || !is_string($summaryJson)
    || !is_string($filesJson)
) {
    throw new RuntimeException('Expected malformed duplicate /Names child node to be skipped while clean attachment remains.');
}

foreach (['stale.xml', 'malformed.xml', $stalePayload, $malformedPayload, $staleChecksum, $malformedChecksum, $cleanPayload] as $hidden) {
    if (str_contains($summaryJson, $hidden) || str_contains($filesJson, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected malformed name-tree child payloads to remain out of WordPress review output.');
    }
}

echo '<!-- markerpdf-pdf-attachment-nametree-duplicate-node-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfiles-name-tree-parser',
    'native_boundary' => 'EmbeddedFiles name-tree child nodes with duplicate /Names, /Kids, or /Limits keys are skipped before attachment review',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'malformed_duplicate_node_skipped' => true,
    'clean_sibling_preserved' => array_column($files, 'filename') === ['clean.xml'],
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $summary['attachments'][0] ?? []),
    'visible_text_excludes_attachment_payloads' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    echo '<li data-marker-attachment-source="' . htmlspecialchars((string) $attachment['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-attachment-stream="' . htmlspecialchars((string) $attachment['stream_object_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
