<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed EFF encrypted text leak) Tj ET';
$payload = '<wp-export><post id="malformed-eff-smoke"/></wp-export>';
$ownerKey = str_repeat('W', 32);
$userKey = str_repeat('P', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF <<"
    . " /ClearStreams << /CFM /None /AuthEvent /DocOpen >>"
    . " /ClearStrings << /CFM /None /AuthEvent /DocOpen >>"
    . " /ClearEmbedded << /CFM /None /AuthEvent /EFOpen >>"
    . " >>"
    . " /StmF /ClearStreams /StrF /ClearStrings /EFF [/ClearEmbedded] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (malformed-eff-smoke.xml) /UF (malformed-eff-smoke.xml) /Desc (Malformed EFF smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$attachment = $summary['attachments'][0] ?? [];
$attachmentPolicy = is_array($attachment['encryption_policy'] ?? null) ? $attachment['encryption_policy'] : [];
$associatedFile = $metadata['associated_files'][0] ?? [];
$associatedPolicy = is_array($associatedFile['encryption_policy'] ?? null) ? $associatedFile['encryption_policy'] : [];
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];

$encoded = json_encode([$metadata, $summary, $preflight], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $payload)
        || str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex($ownerKey)))
        || str_contains($encoded, strtoupper(bin2hex($userKey)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted page text to stay blocked.');
}
if (($permission['crypt_filter_embedded_file_boundary'] ?? null) !== 'blocked_by_malformed_embedded_file_crypt_filter_role') {
    throw new RuntimeException('Expected malformed EFF role to be an explicit embedded-file boundary.');
}
if (($attachment['encrypted_payload_suppressed'] ?? null) !== true || array_key_exists('sha256', $attachment)) {
    throw new RuntimeException('Expected malformed EFF attachment payload metadata to be suppressed.');
}
if (array_key_exists('content_sha256', $associatedFile)) {
    throw new RuntimeException('Expected associated-file payload hash to be suppressed for malformed EFF.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content, payload, and key material to stay out of review JSON.');
}

echo '<!-- markerpdf-encrypted-malformed-eff-attachment-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-malformed-eff-attachment-currentbase',
    'native_boundary' => 'malformed Standard /EFF crypt-filter role fails closed before WordPress attachment payload review',
    'plain_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'embedded_file_payload_policy' => $permission['crypt_filter_embedded_file_payload_policy'] ?? null,
    'embedded_file_boundary' => $permission['crypt_filter_embedded_file_boundary'] ?? null,
    'attachment_count' => $summary['attachment_count'] ?? null,
    'attachment_name_preserved' => ($attachment['filename'] ?? null) === 'malformed-eff-smoke.xml',
    'attachment_payload_suppressed' => ($attachment['encrypted_payload_suppressed'] ?? null) === true,
    'attachment_payload_hash_available' => $attachmentPolicy['payload_hash_available'] ?? null,
    'associated_file_payload_hash_available' => $associatedPolicy['payload_hash_available'] ?? null,
    'raw_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Attachment Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted attachment metadata can be reviewed, but malformed embedded-file crypt-filter roles keep payload hashes and checksums unavailable until a safe decryption path exists.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-malformed-eff-attachment ' . htmlspecialchars(json_encode([
    'filename' => $attachment['filename'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'permission_boundary' => $permission['crypt_filter_embedded_file_boundary'] ?? null,
    'attachment_policy' => [
        'embedded_file_stream_policy' => $attachmentPolicy['embedded_file_stream_policy'] ?? null,
        'embedded_file_stream_policy_reason' => $attachmentPolicy['embedded_file_stream_policy_reason'] ?? null,
        'payload_hash_available' => $attachmentPolicy['payload_hash_available'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
