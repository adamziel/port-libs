<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Identity stream encrypted permission text leak) Tj ET';
$ownerKey = 'CRYPT_FILTER_OWNER_KEY_SHOULD_NOT_LEAK';
$userKey = 'CRYPT_FILTER_USER_KEY_SHOULD_NOT_LEAK';

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
    . " /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
    . " /ClearStreams << /CFM /Identity /AuthEvent /DocOpen >>"
    . " >>"
    . " /StmF /ClearStreams /StrF /StdCF /EFF /MissingEmbeddedFilter >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$review = $report['crypt_filter_content_review'] ?? [];
$extractor = new PdfTextExtractor();

echo '<!-- markerpdf-encrypted-crypt-filter-permission-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-crypt-filter-permission-preflight-currentbase',
    'native_boundary' => 'Standard crypt-filter StmF/StrF/EFF role review is metadata only before encrypted WordPress import',
    'text_blocked' => $extractor->extractPlainText($pdf) === '',
    'permission_policy' => $report['permission_preflight']['policy'] ?? null,
    'content_extraction_boundary' => $report['permission_preflight']['content_extraction_boundary'] ?? null,
    'text_content_policy' => $review['text_content_policy'] ?? null,
    'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
    'identity_role_names' => $review['identity_role_names'] ?? [],
    'encrypted_role_names' => $review['encrypted_role_names'] ?? [],
    'missing_role_names' => $review['missing_role_names'] ?? [],
    'role_statuses' => $review['role_statuses'] ?? [],
    'raw_key_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Crypt Filter Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text stays blocked until decryption is available. The import preflight records which crypt-filter roles are identity, encrypted, or missing so reviewers can see why permissions do not authorize immediate text import.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-crypt-filter-permission-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $report['permission_preflight']['policy'] ?? null,
        'copy_or_extract_allowed' => $report['permission_preflight']['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $report['permission_preflight']['native_text_extraction_allowed_now'] ?? null,
    ],
    'crypt_filter_content_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
        'identity_role_names' => $review['identity_role_names'] ?? [],
        'encrypted_role_names' => $review['encrypted_role_names'] ?? [],
        'missing_role_names' => $review['missing_role_names'] ?? [],
        'selected_filter_names' => $review['selected_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
