<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed Filter encrypted leak) Tj ET';
$ownerValidation = str_repeat('F', 32);
$userValidation = str_repeat('R', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter (Standard) /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$parameterReview = is_array($permission['standard_security_handler_parameter_review'] ?? null)
    ? $permission['standard_security_handler_parameter_review']
    : [];
$declaration = is_array($parameterReview['parameter_declaration_review'] ?? null)
    ? $parameterReview['parameter_declaration_review']
    : [];
$filterRow = null;
foreach ($declaration['rows'] ?? [] as $row) {
    if (is_array($row) && ($row['pdf_name'] ?? null) === 'Filter') {
        $filterRow = $row;
        break;
    }
}
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-filter-operand-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-filter-operand-currentbase',
    'native_boundary' => 'Standard security-handler Filter must be a PDF name before WordPress import trusts permission bits',
    'text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'malformed_parameter_names' => $parameterReview['malformed_parameter_names'] ?? [],
    'filter_operand_shapes' => is_array($filterRow) ? ($filterRow['entry_operand_shapes'] ?? []) : [],
    'filter_entry_statuses' => is_array($filterRow) ? ($filterRow['entry_statuses'] ?? []) : [],
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'raw_auth_material_exposed' => is_string($encoded)
        && (
            str_contains($encoded, $content)
            || str_contains($encoded, $ownerValidation)
            || str_contains($encoded, $userValidation)
            || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        ),
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. A Standard security-handler Filter written as a string is review-only malformed metadata until a real decryption and password-validation path exists.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-filter-operand-preflight ' . htmlspecialchars(json_encode([
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'malformed_parameter_names' => $parameterReview['malformed_parameter_names'] ?? [],
    'review_reasons' => $report['review_reasons'] ?? [],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
