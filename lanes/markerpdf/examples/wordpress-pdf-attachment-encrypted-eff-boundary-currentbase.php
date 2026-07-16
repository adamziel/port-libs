<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$encryptedPayload = '<wp-export><post id="encrypted-eff-smoke"/></wp-export>';
$encryptedChecksum = md5($encryptedPayload);
$encryptedPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Names [(encrypted-source.xml) 4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (encrypted-source.xml) /Desc (Encrypted EFF source packet) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($encryptedPayload) . " /CheckSum <{$encryptedChecksum}> >> /Length " . strlen($encryptedPayload) . " >>\n"
    . "stream\n{$encryptedPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearText << /CFM /None /AuthEvent /DocOpen >> >>"
    . " /StmF /ClearText /StrF /ClearText /EFF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF\n";

$identityPayload = '<wp-export><post id="identity-eff-smoke"/></wp-export>';
$identityChecksum = md5($identityPayload);
$identityPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Names [(identity-eff-source.xml) 4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (identity-eff-source.xml) /Desc (Identity EFF source packet) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($identityPayload) . " /CheckSum <{$identityChecksum}> >> /Length " . strlen($identityPayload) . " >>\n"
    . "stream\n{$identityPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearFiles << /CFM /None /AuthEvent /DocOpen >> >>"
    . " /StmF /ClearFiles /StrF /StdCF /EFF /ClearFiles >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF\n";

$extractor = new PdfAttachmentExtractor();
$encryptedSummary = $extractor->attachmentSummary($encryptedPdf);
$identitySummary = $extractor->attachmentSummary($identityPdf);
$encryptedAttachment = $encryptedSummary['attachments'][0] ?? [];
$identityAttachment = $identitySummary['attachments'][0] ?? [];
$encoded = json_encode([$encryptedSummary, $identitySummary], JSON_UNESCAPED_SLASHES);

if (($encryptedSummary['attachment_count'] ?? null) !== 1 || ($encryptedSummary['total_bytes'] ?? null) !== 0) {
    throw new RuntimeException('Expected encrypted EFF payload bytes to be suppressed from attachment preflight.');
}
if (($encryptedAttachment['encrypted_payload_suppressed'] ?? null) !== true) {
    throw new RuntimeException('Expected encrypted EFF attachment policy metadata.');
}
if (array_key_exists('sha256', $encryptedAttachment) || array_key_exists('checksum_hex', $encryptedAttachment)) {
    throw new RuntimeException('Expected encrypted EFF hashes to stay unavailable without decryption.');
}
if (($identitySummary['attachment_count'] ?? null) !== 1 || ($identitySummary['total_bytes'] ?? null) !== strlen($identityPayload)) {
    throw new RuntimeException('Expected identity EFF payload hash metadata to remain available.');
}
if (array_key_exists('filename', $identityAttachment) || array_key_exists('description', $identityAttachment)) {
    throw new RuntimeException('Expected encrypted FileSpec strings to be redacted in attachment preflight.');
}
if (($identityAttachment['checksum_matches'] ?? null) !== true || ($identityAttachment['sha256'] ?? null) !== hash('sha256', $identityPayload)) {
    throw new RuntimeException('Expected identity EFF payload checksum metadata.');
}
if (!is_string($encoded)
    || str_contains($encoded, $encryptedPayload)
    || str_contains($encoded, $identityPayload)
    || str_contains($encoded, 'identity-eff-source.xml')
    || str_contains($encoded, 'Identity EFF source packet')
) {
    throw new RuntimeException('Expected payload bytes and encrypted FileSpec strings to stay out of WordPress review output.');
}

echo '<!-- markerpdf-attachment-encrypted-eff-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-attachment-encrypted-eff-preflight',
    'native_boundary' => 'trailer /Encrypt crypt-filter policy redacts standalone attachment summaries before WordPress import',
    'encrypted_eff_attachment_count' => $encryptedSummary['attachment_count'],
    'encrypted_eff_total_bytes' => $encryptedSummary['total_bytes'],
    'encrypted_eff_payload_suppressed' => $encryptedAttachment['encrypted_payload_suppressed'] ?? null,
    'encrypted_eff_payload_hash_available' => $encryptedAttachment['encryption_policy']['payload_hash_available'] ?? null,
    'identity_eff_total_bytes' => $identitySummary['total_bytes'],
    'identity_eff_checksum_matches' => $identityAttachment['checksum_matches'] ?? null,
    'identity_eff_strings_redacted' => !array_key_exists('filename', $identityAttachment)
        && !array_key_exists('description', $identityAttachment),
    'payload_content_exposed' => str_contains($encoded, $encryptedPayload) || str_contains($encoded, $identityPayload),
    'executes_decryption' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF attachments require review before import. Encrypted embedded-file streams stay hashless until decryption is available, while identity embedded-file streams expose only checksum metadata when FileSpec strings are encrypted.</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-attachment-review ' . $htmlJson([
    'encrypted_eff' => [
        'filename' => $encryptedAttachment['filename'] ?? null,
        'relationship' => $encryptedAttachment['relationship'] ?? null,
        'encryption_policy' => $encryptedAttachment['encryption_policy'] ?? null,
    ],
    'identity_eff' => [
        'sha256' => $identityAttachment['sha256'] ?? null,
        'checksum_matches' => $identityAttachment['checksum_matches'] ?? null,
        'encryption_policy' => $identityAttachment['encryption_policy'] ?? null,
    ],
]) . " -->\n";
