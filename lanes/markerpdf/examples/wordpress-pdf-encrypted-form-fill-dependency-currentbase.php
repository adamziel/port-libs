<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress form fill dependency encrypted text leak) Tj ET';
$ownerKey = 'WORDPRESS_FORM_FILL_DEPENDENCY_OWNER_SHOULD_NOT_LEAK';
$userKey = 'WORDPRESS_FORM_FILL_DEPENDENCY_USER_SHOULD_NOT_LEAK';
$ownerBytes = str_pad($ownerKey, 32, 'O');
$userBytes = str_pad($userKey, 32, 'U');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
    . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
    . " /P -3856 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$operationReview = is_array($permission['standard_permission_operation_review'] ?? null)
    ? $permission['standard_permission_operation_review']
    : [];
$operations = is_array($operationReview['operations'] ?? null) ? $operationReview['operations'] : [];
$formFillOperation = null;
foreach ($operations as $operation) {
    if (is_array($operation) && ($operation['name'] ?? null) === 'fill_form_fields') {
        $formFillOperation = $operation;
        break;
    }
}

$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, $ownerBytes)
        || str_contains($encoded, $userBytes)
        || str_contains($encoded, strtoupper(bin2hex($ownerBytes)))
        || str_contains($encoded, strtoupper(bin2hex($userBytes)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted form-fill dependency fixture text to stay blocked.');
}

if (
    !is_array($formFillOperation)
    || ($formFillOperation['bit_set'] ?? null) !== false
    || ($formFillOperation['granted_by_permission_name'] ?? null) !== 'add_or_modify_annotations'
    || ($formFillOperation['effective_status'] ?? null) !== 'allowed_by_permission_bit_pending_authentication'
    || !in_array('fill_form_fields', $operationReview['pending_authentication_operation_names'] ?? [], true)
    || in_array('fill_form_fields', $operationReview['denied_operation_names'] ?? [], true)
    || $rawMaterialExposed
) {
    throw new RuntimeException('Expected bit-6 annotation permission to grant form-fill review while keeping encrypted payloads blocked.');
}

echo '<!-- markerpdf-encrypted-form-fill-dependency-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-form-fill-dependency-currentbase',
    'native_boundary' => 'Standard permission bit 6 grants existing form-fill review even when revision-3 bit 9 is clear, while encrypted WordPress import remains blocked pending password validation and decryption',
    'encrypted_text_blocked' => $plainText === '',
    'permission_hex' => $permission['permission_hex'] ?? null,
    'operation_count' => $operationReview['operation_count'] ?? null,
    'form_fill_bit_set' => $formFillOperation['bit_set'] ?? null,
    'form_fill_effective_status' => $formFillOperation['effective_status'] ?? null,
    'form_fill_granted_by_permission_name' => $formFillOperation['granted_by_permission_name'] ?? null,
    'pending_authentication_operation_names' => $operationReview['pending_authentication_operation_names'] ?? [],
    'denied_operation_names' => $operationReview['denied_operation_names'] ?? [],
    'raw_auth_material_exposed' => $rawMaterialExposed,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Form Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The security preflight records that annotation permission also permits existing form-field filling, but the result stays review-only until password validation and decryption authenticate the permission bits.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-form-fill-dependency ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
    ],
    'operation_review' => [
        'status' => $operationReview['status'] ?? null,
        'pending_authentication_operation_names' => $operationReview['pending_authentication_operation_names'] ?? [],
        'denied_operation_names' => $operationReview['denied_operation_names'] ?? [],
        'form_fill' => [
            'bit_set' => $formFillOperation['bit_set'] ?? null,
            'effective_status' => $formFillOperation['effective_status'] ?? null,
            'granted_by_permission_name' => $formFillOperation['granted_by_permission_name'] ?? null,
            'permission_boundary' => $formFillOperation['permission_boundary'] ?? null,
        ],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
