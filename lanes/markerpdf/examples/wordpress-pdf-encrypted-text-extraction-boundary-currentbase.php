<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress accessibility-only encrypted text leak) Tj ET';
$ownerKey = 'WORDPRESS_ACCESSIBILITY_ONLY_OWNER_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_ACCESSIBILITY_ONLY_USER_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
    . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
    . " /P -316 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$textReview = is_array($permission['standard_permission_text_extraction_review'] ?? null)
    ? $permission['standard_permission_text_extraction_review']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))))
        || str_contains($encoded, strtoupper(bin2hex(str_pad($userKey, 32, 'U'))))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected accessibility-only encrypted fixture text to stay blocked.');
}

if (
    ($permission['policy'] ?? null) !== 'copy_extract_denied_by_permissions'
    || ($permission['copy_or_extract_allowed'] ?? null) !== false
    || ($permission['accessibility_extract_allowed'] ?? null) !== true
    || ($textReview['status'] ?? null) !== 'accessibility_extract_allowed_but_copy_extract_denied'
    || ($textReview['wordpress_import_policy'] ?? null) !== 'blocked_by_copy_permission_denial_accessibility_review_only'
    || ($textReview['accessibility_permission_grants_wordpress_import'] ?? null) !== false
    || $rawMaterialExposed
) {
    throw new RuntimeException('Expected accessibility-only permission to remain blocked for WordPress text import.');
}

echo '<!-- markerpdf-encrypted-text-extraction-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-text-extraction-boundary-currentbase',
    'native_boundary' => 'PDF Standard accessibility extraction permission is review metadata and does not grant ordinary WordPress text import when copy/extract is denied',
    'encrypted_text_blocked' => $plainText === '',
    'policy' => $permission['policy'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'accessibility_extract_allowed' => $permission['accessibility_extract_allowed'] ?? null,
    'text_extraction_review_status' => $textReview['status'] ?? null,
    'wordpress_import_policy' => $textReview['wordpress_import_policy'] ?? null,
    'raw_auth_material_exposed' => $rawMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Text Extraction Boundary</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked for WordPress import when the Standard copy/extract bit is denied, even if the accessibility extraction bit is set for review after password validation.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-text-extraction-boundary ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'accessibility_extract_allowed' => $permission['accessibility_extract_allowed'] ?? null,
    ],
    'text_extraction_review' => [
        'status' => $textReview['status'] ?? null,
        'text_import_allowed_after_decryption' => $textReview['text_import_allowed_after_decryption'] ?? null,
        'accessibility_extraction_allowed_after_decryption' => $textReview['accessibility_extraction_allowed_after_decryption'] ?? null,
        'accessibility_permission_grants_wordpress_import' => $textReview['accessibility_permission_grants_wordpress_import'] ?? null,
        'wordpress_import_policy' => $textReview['wordpress_import_policy'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
