<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress operation permission encrypted text leak) Tj ET';
$ownerKey = 'WORDPRESS_OPERATION_PERMISSION_OWNER_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_OPERATION_PERMISSION_USER_SHOULD_NOT_LEAK';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex(str_pad($ownerKey, 32, 'O'))) . ">"
    . " /U <" . strtoupper(bin2hex(str_pad($userKey, 32, 'U'))) . ">"
    . " /P 4294967252 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = $report['permission_preflight'] ?? [];
$operationReview = is_array($permission['standard_permission_operation_review'] ?? null)
    ? $permission['standard_permission_operation_review']
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
    throw new RuntimeException('Expected encrypted permission-operation fixture text to stay blocked.');
}

if (
    ($operationReview['pending_authentication_operation_names'] ?? []) !== [
        'print',
        'copy_or_extract',
        'fill_form_fields',
        'extract_for_accessibility',
        'assemble_document',
        'high_quality_print',
    ]
    || ($operationReview['denied_operation_names'] ?? []) !== ['modify_contents', 'add_or_modify_annotations']
    || ($operationReview['native_import_allowed_operation_names'] ?? []) !== []
    || $rawMaterialExposed
) {
    throw new RuntimeException('Expected Standard operation permissions to remain review-only and payload-safe.');
}

echo '<!-- markerpdf-encrypted-permission-operation-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-operation-preflight-currentbase',
    'native_boundary' => 'Standard permission bits are mapped to operation rows while encrypted WordPress import remains blocked until password validation and decryption authenticate them',
    'encrypted_text_blocked' => $plainText === '',
    'operation_count' => $operationReview['operation_count'] ?? null,
    'operation_statuses' => $operationReview['operation_statuses'] ?? [],
    'pending_authentication_operation_names' => $operationReview['pending_authentication_operation_names'] ?? [],
    'denied_operation_names' => $operationReview['denied_operation_names'] ?? [],
    'native_import_allowed_operation_names' => $operationReview['native_import_allowed_operation_names'] ?? [],
    'permission_boundary' => $operationReview['permission_boundary'] ?? null,
    'raw_auth_material_exposed' => $rawMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Operation Permissions</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The import preflight maps Standard permission bits to operation rows so review tools can distinguish denied operations from operations that are syntactically allowed but still unauthenticated.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-operation-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
    ],
    'operation_review' => [
        'status' => $operationReview['status'] ?? null,
        'operation_statuses' => $operationReview['operation_statuses'] ?? [],
        'pending_authentication_operation_names' => $operationReview['pending_authentication_operation_names'] ?? [],
        'denied_operation_names' => $operationReview['denied_operation_names'] ?? [],
        'native_import_allowed_operation_names' => $operationReview['native_import_allowed_operation_names'] ?? [],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
