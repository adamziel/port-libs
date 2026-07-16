<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress unsupported crypt filter encrypted text leak) Tj ET';
$ownerKey = str_repeat('O', 32);
$userKey = str_repeat('U', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF <<"
    . " /VendorStreams << /CFM /VendorAES /AuthEvent /DocOpen /Length 16 >>"
    . " /MysteryStrings << /CFM [] /AuthEvent /DocOpen /Length 16 >>"
    . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >>"
    . " >>"
    . " /StmF /VendorStreams /StrF /MysteryStrings /EFF /ClearEmbedded >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$review = $preflight['crypt_filter_content_review'] ?? [];
$permission = $preflight['permission_preflight'] ?? [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-unsupported-crypt-filter-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-unsupported-crypt-filter-currentbase',
    'native_boundary' => 'unsupported or unknown document crypt-filter methods fail closed before WordPress text import',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'crypt_filter_text_policy' => $permission['crypt_filter_text_policy'] ?? null,
    'crypt_filter_text_fail_closed' => $permission['crypt_filter_text_fail_closed'] ?? null,
    'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
    'fail_closed_filter_names' => $review['fail_closed_filter_names'] ?? [],
    'role_statuses' => $review['role_statuses'] ?? [],
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    'raw_key_material_exposed' => is_string($encoded) && (
        str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex($ownerKey)))
        || str_contains($encoded, strtoupper(bin2hex($userKey)))
    ),
    'encrypted_text_exposed' => is_string($encoded) && str_contains($encoded, $content),
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Crypt Filter Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked when document streams or strings select unsupported crypt-filter methods, even if copy and extraction permission bits are set.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-unsupported-crypt-filter ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    ],
    'crypt_filter_content_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
        'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
        'fail_closed_filter_names' => $review['fail_closed_filter_names'] ?? [],
        'roles' => $review['roles'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
