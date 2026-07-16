<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (CFM None encrypted page text leak) Tj ET';
$payload = '<wp-export><post id="cfm-none-attachment"/></wp-export>';
$ownerKey = 'WORDPRESS_CFM_NONE_OWNER_KEY_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_CFM_NONE_USER_KEY_SHOULD_NOT_LEAK';

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
    . " /ClearStrings << /CFM /None /AuthEvent /DocOpen >>"
    . " /ClearEmbedded << /CFM /None /AuthEvent /EFOpen >>"
    . " >>"
    . " /StmF /StdCF /StrF /ClearStrings /EFF /ClearEmbedded >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (clear-cfm-none.xml) /UF (clear-cfm-none.xml) /Desc (Clear CFM None attachment metadata) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$review = $report['crypt_filter_content_review'] ?? [];
$file = $metadata['associated_files'][0] ?? [];
$filePolicy = is_array($file['encryption_policy'] ?? null) ? $file['encryption_policy'] : [];

echo '<!-- markerpdf-encrypted-cfm-none-permission-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-cfm-none-permission-preflight-currentbase',
    'native_boundary' => 'PDF crypt-filter /CFM /None is clear review metadata, but encrypted document text remains blocked until decryption is available',
    'text_blocked' => $plainText === '',
    'permission_policy' => $report['permission_preflight']['policy'] ?? null,
    'content_extraction_boundary' => $report['permission_preflight']['content_extraction_boundary'] ?? null,
    'text_content_policy' => $review['text_content_policy'] ?? null,
    'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
    'identity_role_names' => $review['identity_role_names'] ?? [],
    'encrypted_role_names' => $review['encrypted_role_names'] ?? [],
    'attachment_filename_preserved' => $file['filename'] ?? null,
    'attachment_payload_hash_available' => $filePolicy['payload_hash_available'] ?? null,
    'file_spec_strings_policy' => $filePolicy['file_spec_strings_policy'] ?? null,
    'embedded_file_stream_policy' => $filePolicy['embedded_file_stream_policy'] ?? null,
    'raw_key_material_exposed' => false,
    'payload_content_included' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF CFM None Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF page text remains blocked. Clear crypt-filter string and embedded-file metadata are preserved as review metadata without decrypting content or exposing payload bytes.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-cfm-none-permission-preflight ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'] ?? null,
    'permission' => [
        'policy' => $report['permission_preflight']['policy'] ?? null,
        'copy_or_extract_allowed' => $report['permission_preflight']['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $report['permission_preflight']['native_text_extraction_allowed_now'] ?? null,
    ],
    'crypt_filter_content_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
        'identity_filter_names' => $review['identity_filter_names'] ?? [],
        'encrypted_filter_names' => $review['encrypted_filter_names'] ?? [],
        'role_statuses' => $review['role_statuses'] ?? [],
    ],
    'attachment_review' => [
        'filename' => $file['filename'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'file_spec_strings_policy' => $filePolicy['file_spec_strings_policy'] ?? null,
        'embedded_file_stream_policy' => $filePolicy['embedded_file_stream_policy'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
