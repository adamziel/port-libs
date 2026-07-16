<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$malformedPayload = 'BT /F1 12 Tf 72 720 Td (Malformed EF Payload Leak) Tj ET';
$trailingOperandPayload = 'BT /F1 12 Tf 72 700 Td (Trailing EF Operand Leak) Tj ET';
$validPayload = '<wp-export><post id="valid-malformed-ef-sibling"/></wp-export>';
$validChecksum = md5($validPayload);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /Names [(malformed-ef.txt) 10 0 R (valid-source.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (malformed-ef.txt) /Desc (Malformed direct EF stream operand) /AFRelationship /Data /EF 11 0 R 12 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($malformedPayload) . " >>\nstream\n{$malformedPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($trailingOperandPayload) . " >>\nstream\n{$trailingOperandPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid sibling source export) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608200330Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
if (
    ($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['valid-source.xml']
    || !is_array($attachment)
    || !is_array($file)
    || ($attachment['filename'] ?? null) !== 'valid-source.xml'
    || ($attachment['relationship_role'] ?? null) !== 'original_source'
    || ($attachment['byte_length'] ?? null) !== strlen($validPayload)
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $validPayload
    || ($file['checksum_matches'] ?? null) !== true
    || $plainText !== ''
    || ($summary['executes_python_or_models'] ?? null) !== false
    || ($summary['executes_external_pdf_tools'] ?? null) !== false
) {
    throw new RuntimeException('Expected valid sibling attachment and no malformed EF fallback text.');
}

foreach ([
    'malformed-ef.txt',
    'Malformed direct EF stream operand',
    $malformedPayload,
    $trailingOperandPayload,
    'Malformed EF Payload Leak',
    'Trailing EF Operand Leak',
] as $hidden) {
    if (str_contains($summaryJson, $hidden) || str_contains($filesJson, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Malformed EF stream operand leaked into WordPress review.');
    }
}

echo json_encode([
    'scenario' => 'wordpress-pdf-embedded-files-malformed-ef-fallback-text-currentbase',
    'source' => 'sddai/markerPDF no-GPU native PDF EmbeddedFiles fallback-text boundary',
    'purpose' => 'Reject malformed direct /EF stream operands as attachments while keeping their stream text out of WordPress paragraph extraction.',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'total_bytes' => $summary['total_bytes'],
    'valid_relationship_role' => $attachment['relationship_role'],
    'valid_checksum_matches' => $attachment['checksum_matches'],
    'malformed_attachment_excluded' => !str_contains($summaryJson, 'malformed-ef.txt') && !str_contains($filesJson, 'malformed-ef.txt'),
    'malformed_ef_payload_excluded_from_text' => !str_contains($plainText, 'Malformed EF Payload Leak')
        && !str_contains($plainText, 'Trailing EF Operand Leak'),
    'fallback_text_empty' => $plainText === '',
    'summary_exposes_attachment_bytes' => array_key_exists('bytes', $attachment),
    'embedded_file_payload_available' => ($file['content'] ?? null) === $validPayload,
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

echo '<!-- wp:file {"href":"media/' . htmlspecialchars($file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
