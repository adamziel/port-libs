<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress default CFM encrypted text leak) Tj ET';
$payload = '<wp-export><post id="default-cfm-attachment"/></wp-export>';
$ownerKey = str_repeat('W', 32);
$userKey = str_repeat('D', 32);

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
    . " /ClearStreams << /AuthEvent /DocOpen /Length 16 >>"
    . " /EncryptedStrings << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
    . " /ClearEmbedded << /AuthEvent /EFOpen /Length 16 >>"
    . " >>"
    . " /StmF /ClearStreams /StrF /EncryptedStrings /EFF /ClearEmbedded >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (default-cfm.xml) /UF (default-cfm.xml) /Desc (Default CFM attachment metadata) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
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

echo '<!-- markerpdf-encrypted-default-crypt-filter-method-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-default-crypt-filter-method-currentbase',
    'native_boundary' => 'omitted crypt-filter CFM defaults to None before encrypted WordPress import review',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'stream_filter_method' => $encryption['crypt_filters']['ClearStreams']['method'] ?? null,
    'stream_filter_cfm_defaulted' => $encryption['crypt_filters']['ClearStreams']['cfm_defaulted'] ?? null,
    'embedded_filter_method' => $encryption['crypt_filters']['ClearEmbedded']['method'] ?? null,
    'embedded_filter_cfm_defaulted' => $encryption['crypt_filters']['ClearEmbedded']['cfm_defaulted'] ?? null,
    'cfm_defaulted_role_names' => $review['cfm_defaulted_role_names'] ?? [],
    'identity_role_names' => $review['identity_role_names'] ?? [],
    'encrypted_role_names' => $review['encrypted_role_names'] ?? [],
    'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
    'file_spec_strings_policy' => $filePolicy['file_spec_strings_policy'] ?? null,
    'payload_hash_available' => $filePolicy['payload_hash_available'] ?? null,
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
echo "<h2>Encrypted PDF Default Crypt Filter Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Crypt filters with omitted CFM are reviewed as default None filters, so clear embedded-file payload fingerprints remain review metadata while encrypted FileSpec strings stay redacted.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-default-crypt-filter-method ' . htmlspecialchars(json_encode([
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'content_extraction_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    'crypt_filter_content_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
        'cfm_defaulted_role_names' => $review['cfm_defaulted_role_names'] ?? [],
        'cfm_defaulted_filter_names' => $review['cfm_defaulted_filter_names'] ?? [],
        'roles' => $review['roles'] ?? [],
    ],
    'associated_file_review' => [
        'payload_sha256' => $file['content_sha256'] ?? null,
        'computed_checksum' => $file['computed_checksum'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'encryption_policy' => $filePolicy,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
