<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress default EFF encrypted text leak) Tj ET';
$payload = '<wp-export><post id="default-eff-attachment"/></wp-export>';
$ownerKey = 'WP_DEFAULT_EFF_OWNER_KEY_SHOULD_NOT_LEAK';
$userKey = 'WP_DEFAULT_EFF_USER_KEY_SHOULD_NOT_LEAK';

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
    . " /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
    . " /ClearStreams << /CFM /None /AuthEvent /DocOpen >>"
    . " >>"
    . " /StmF /ClearStreams /StrF /StdCF >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (default-eff.xml) /UF (default-eff.xml) /Desc (Default EFF attachment metadata) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = $metadata['encryption'] ?? [];
$review = $preflight['crypt_filter_content_review'] ?? [];
$file = $metadata['associated_files'][0] ?? [];
$filePolicy = is_array($file['encryption_policy'] ?? null) ? $file['encryption_policy'] : [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-default-crypt-filter-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-default-crypt-filter-preflight-currentbase',
    'native_boundary' => 'omitted encryption dictionary EFF inherits StmF before WordPress associated-file review',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'stream_filter' => $encryption['stream_filter'] ?? null,
    'string_filter' => $encryption['string_filter'] ?? null,
    'embedded_file_filter' => $encryption['embedded_file_filter'] ?? null,
    'embedded_file_filter_defaulted_from_stream_filter' => $encryption['embedded_file_filter_defaulted_from_stream_filter'] ?? null,
    'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
    'file_spec_strings_policy' => $filePolicy['file_spec_strings_policy'] ?? null,
    'payload_hash_available' => $filePolicy['payload_hash_available'] ?? null,
    'payload_content_included' => $filePolicy['payload_content_included'] ?? null,
    'payload_sha256_matches' => ($file['content_sha256'] ?? null) === hash('sha256', $payload),
    'file_spec_strings_redacted' => !array_key_exists('filename', $file) && !array_key_exists('description', $file),
    'raw_key_material_exposed' => is_string($encoded) && (
        str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex($ownerKey)))
        || str_contains($encoded, strtoupper(bin2hex($userKey)))
    ),
    'payload_content_exposed' => is_string($encoded) && str_contains($encoded, $payload),
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Associated-File Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Omitted embedded-file crypt-filter metadata inherits the stream filter, so clear attachment payload fingerprints can be reviewed while encrypted FileSpec strings stay redacted.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-default-crypt-filter-preflight ' . htmlspecialchars(json_encode([
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'content_extraction_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    'crypt_filter_roles' => $review['roles'] ?? [],
    'associated_file_review' => [
        'payload_sha256' => $file['content_sha256'] ?? null,
        'computed_checksum' => $file['computed_checksum'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'encryption_policy' => $filePolicy,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
