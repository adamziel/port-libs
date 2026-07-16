<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = '<wp-export><post id="name-tree-uf-current"/></wp-export>';
$stalePayload = '<wp-export><post id="name-tree-f-stale"/></wp-export>';
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);
$content = 'BT /F1 12 Tf 72 720 Td (Name Tree EF Fallback Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(name-tree-display.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /Desc (Name tree fallback WordPress source) /AFRelationship /Source /EF << /F 11 0 R /UF 12 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> /ModDate (D:20260605091400Z) >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605091430Z) >> /Length " . strlen($currentPayload) . " >>\n"
    . "stream\n{$currentPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? [];
$file = $files[0] ?? [];
$encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

if (
    $summary['attachment_count'] !== 1
    || ($attachment['filename_source'] ?? null) !== 'name_tree_key'
    || ($attachment['ef_key'] ?? null) !== 'UF'
    || ($attachment['checksum_hex'] ?? null) !== $currentChecksum
    || ($attachment['byte_length'] ?? null) !== strlen($currentPayload)
    || ($file['ef_key'] ?? null) !== 'UF'
    || ($file['content'] ?? null) !== $currentPayload
    || $plainText !== 'Name Tree EF Fallback Body'
    || !is_string($encodedSummary)
    || !is_string($encodedFiles)
    || str_contains($encodedSummary, $currentPayload)
    || str_contains($encodedSummary, $stalePayload)
    || str_contains($encodedSummary, $staleChecksum)
    || str_contains($encodedFiles, $stalePayload)
    || str_contains($encodedFiles, $staleChecksum)
) {
    throw new RuntimeException('Expected name-tree fallback FileSpec to choose /UF EF stream before stale /F stream.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-attachment-name-tree-ef-order-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-filespec-ef-fallback-order',
    'native_boundary' => 'FileSpec with name-tree fallback filename uses standard /UF then /F embedded-file stream order',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'] ?? null,
    'filename_source' => $attachment['filename_source'] ?? null,
    'ef_key' => $attachment['ef_key'] ?? null,
    'checksum_matches' => $attachment['checksum_matches'] ?? null,
    'stale_f_stream_excluded' => !str_contains($encodedSummary, $staleChecksum)
        && !str_contains($encodedFiles, $staleChecksum),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) ($attachment['filename_storage_name'] ?? 'name-tree-display.xml'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) ($attachment['filename_storage_name'] ?? 'name-tree-display.xml'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) ($attachment['filename_leaf'] ?? 'name-tree-display.xml'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
