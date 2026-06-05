<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$sourcePayload = '<wp-export><post id="encrypted-related-smoke"/></wp-export>';
$sourceChecksum = md5($sourcePayload);
$relatedCiphertext = 'ENCRYPTED_RELATED_SMOKE_BYTES_SHOULD_NOT_LEAK';
$manifestCiphertext = 'ENCRYPTED_MANIFEST_SMOKE_BYTES_SHOULD_NOT_LEAK';
$unicodeRelatedName = iconv('UTF-8', 'UTF-16BE', 'manifest.json');
if (!is_string($unicodeRelatedName)) {
    throw new RuntimeException('Unable to encode encrypted related filename smoke fixture.');
}
$unicodeRelatedNameHex = strtoupper(bin2hex("\xFE\xFF" . $unicodeRelatedName));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Names [(encrypted-related-source.xml) 4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (encrypted-related-source.xml) /Desc (Encrypted related file source) /AFRelationship /Source /EF << /F 5 0 R >> /RF << /F [(private-note.txt) 6 0 R] /UF [<{$unicodeRelatedNameHex}> 7 0 R] >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Filter /Crypt /Params << /Size 1024 /CheckSum <" . str_repeat('aa', 16) . "> >> /Length " . strlen($relatedCiphertext) . " >>\n"
    . "stream\n{$relatedCiphertext}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter [/Crypt /FlateDecode] /Params << /Size 2048 /CheckSum <" . str_repeat('bb', 16) . "> >> /Length " . strlen($manifestCiphertext) . " >>\n"
    . "stream\n{$manifestCiphertext}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 >> /ClearText << /CFM /None /AuthEvent /DocOpen >> >>"
    . " /StmF /ClearText /StrF /ClearText /EFF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$attachment = $summary['attachments'][0] ?? [];
$relatedFiles = is_array($attachment['related_files'] ?? null) ? $attachment['related_files'] : [];
$encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

if (($summary['attachment_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected one encrypted attachment summary.');
}
if (($summary['total_bytes'] ?? null) !== 0 || ($attachment['encrypted_payload_suppressed'] ?? null) !== true) {
    throw new RuntimeException('Expected encrypted embedded-file payload bytes to be suppressed.');
}
if (($attachment['related_file_count'] ?? null) !== 2 || count($relatedFiles) !== 2) {
    throw new RuntimeException('Expected encrypted related-file rows to remain reviewable.');
}
foreach ($relatedFiles as $row) {
    if (($row['encrypted_payload_suppressed'] ?? null) !== true || array_key_exists('sha256', $row)) {
        throw new RuntimeException('Expected encrypted related-file payload metadata to be suppressed.');
    }
}
if (
    !is_string($encoded)
    || str_contains($encoded, $sourcePayload)
    || str_contains($encoded, $sourceChecksum)
    || str_contains($encoded, $relatedCiphertext)
    || str_contains($encoded, $manifestCiphertext)
) {
    throw new RuntimeException('Expected encrypted attachment payload bytes and checksums to stay out of WordPress review output.');
}

echo '<!-- markerpdf-attachment-encrypted-related-files-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-attachment-encrypted-related-file-preflight',
    'native_boundary' => 'FileSpec /RF related embedded-file rows inherit trailer /Encrypt /EFF suppression before WordPress import',
    'attachment_count' => $summary['attachment_count'],
    'total_bytes' => $summary['total_bytes'],
    'main_payload_suppressed' => $attachment['encrypted_payload_suppressed'] ?? null,
    'related_file_count' => $attachment['related_file_count'] ?? null,
    'related_stream_object_ids' => array_map(
        static fn (array $row): mixed => $row['stream_object_id'] ?? null,
        $relatedFiles
    ),
    'related_payloads_suppressed' => array_map(
        static fn (array $row): mixed => $row['encrypted_payload_suppressed'] ?? null,
        $relatedFiles
    ),
    'payload_content_exposed' => str_contains($encoded, $sourcePayload)
        || str_contains($encoded, $relatedCiphertext)
        || str_contains($encoded, $manifestCiphertext),
    'executes_decryption' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF attachment sidecars require review before import. Related embedded files stay visible as object-level review rows while encrypted payload bytes remain unavailable until decryption is supported.</p>\n";
echo "<!-- /wp:paragraph -->\n";
