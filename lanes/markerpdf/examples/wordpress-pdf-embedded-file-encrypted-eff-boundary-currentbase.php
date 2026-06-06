<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$payload = '<wp-export><post id="embedded-encrypted-eff-smoke"/></wp-export>';
$relatedPayload = 'related-private-wordpress-export-smoke';
$payloadChecksum = md5($payload);
$relatedChecksum = md5($relatedPayload);
$visibleText = 'Embedded encrypted EFF smoke body';
$content = 'BT /F1 12 Tf 72 720 Td (' . $visibleText . ') Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(encrypted-embedded.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (encrypted-embedded.xml) /Desc (Encrypted EFF WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(related-private.txt) 12 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$payloadChecksum}> /ModDate (D:20260606064050Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\n"
    . "stream\n{$relatedPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearText << /CFM /None /AuthEvent /DocOpen >> >>"
    . " /StmF /ClearText /StrF /ClearText /EFF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 20 0 R >>\n%%EOF\n";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$file = $files[0] ?? [];
$related = is_array($file['related_files'] ?? null) ? ($file['related_files'][0] ?? []) : [];
$attachment = $summary['attachments'][0] ?? [];
$encoded = json_encode([$files, $summary, $plainText], JSON_UNESCAPED_SLASHES);

if (count($files) !== 1 || ($file['encrypted_payload_suppressed'] ?? null) !== true) {
    throw new RuntimeException('Expected direct embedded-file inventory to suppress encrypted EFF payload bytes.');
}
if (array_key_exists('content', $file) || array_key_exists('content_sha256', $file) || array_key_exists('checksum', $file)) {
    throw new RuntimeException('Expected encrypted embedded-file payload metadata to stay unavailable without decryption.');
}
if (($file['related_file_count'] ?? null) !== 1 || ($related['encrypted_payload_suppressed'] ?? null) !== true) {
    throw new RuntimeException('Expected encrypted related-file rows to inherit EFF payload suppression.');
}
if (array_key_exists('content_sha256', $related) || array_key_exists('checksum', $related)) {
    throw new RuntimeException('Expected encrypted related-file hashes to stay unavailable without decryption.');
}
if (($summary['attachment_count'] ?? null) !== 1
    || ($summary['total_bytes'] ?? null) !== 0
    || ($attachment['encrypted_payload_suppressed'] ?? null) !== true
) {
    throw new RuntimeException('Expected high-level attachment preflight to stay aligned with direct embedded-file suppression.');
}
if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted document text extraction to remain blocked without decryption.');
}
if (!is_string($encoded)
    || str_contains($encoded, $payload)
    || str_contains($encoded, $relatedPayload)
    || str_contains($encoded, $payloadChecksum)
    || str_contains($encoded, $relatedChecksum)
) {
    throw new RuntimeException('Expected encrypted embedded-file bytes and checksums to stay out of WordPress review output.');
}

echo '<!-- markerpdf-embedded-file-encrypted-eff-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-embedded-file-encrypted-eff-preflight',
    'native_boundary' => 'trailer /Encrypt /EFF suppresses direct embedded-file and related-file payload metadata before WordPress import',
    'embedded_file_count' => count($files),
    'attachment_summary_count' => $summary['attachment_count'] ?? null,
    'attachment_summary_total_bytes' => $summary['total_bytes'] ?? null,
    'direct_payload_suppressed' => $file['encrypted_payload_suppressed'] ?? null,
    'related_payload_suppressed' => $related['encrypted_payload_suppressed'] ?? null,
    'high_level_payload_suppressed' => $attachment['encrypted_payload_suppressed'] ?? null,
    'payload_hash_available' => $file['encryption_policy']['payload_hash_available'] ?? null,
    'payload_content_exposed' => false,
    'encrypted_document_text_extraction_blocked' => $plainText === '',
    'executes_decryption' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted embedded-file attachments require review before import. Direct embedded-file inventories keep FileSpec identity and related-file object references while suppressing encrypted payload bytes and hashes.</p>\n";
echo "<!-- /wp:paragraph -->\n";
