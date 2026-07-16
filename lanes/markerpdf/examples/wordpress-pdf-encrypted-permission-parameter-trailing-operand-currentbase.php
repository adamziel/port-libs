<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress Standard Length trailing operand encrypted leak) Tj ET';
$ownerValidation = str_repeat('T', 32);
$userValidation = str_repeat('G', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 9 0 R"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "9 0 obj\n256\nendobj\n"
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
$lengthRow = null;
foreach ($declaration['rows'] ?? [] as $row) {
    if (is_array($row) && ($row['pdf_name'] ?? null) === 'Length') {
        $lengthRow = $row;
        break;
    }
}
$lengthEntry = is_array($lengthRow['entries'][0] ?? null) ? $lengthRow['entries'][0] : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text with a trailing Standard Length operand to stay blocked.');
}

if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_parameters') {
    throw new RuntimeException('Expected malformed Standard handler parameters to own the permission preflight source.');
}

if (($permission['copy_or_extract_allowed'] ?? null) !== null
    || ($permission['permission_bits_reliable'] ?? true) !== false
) {
    throw new RuntimeException('Expected copy/extract permission bits to be ignored after trailing Standard Length operands.');
}

if (($lengthEntry['status'] ?? null) !== 'standard_security_handler_parameter_trailing_operand_review'
    || ($lengthEntry['trailing_operand_shape'] ?? null) !== 'indirect_reference'
    || ($lengthEntry['trailing_operand_preview'] ?? null) !== '9 0 R'
) {
    throw new RuntimeException('Expected Standard Length trailing operand review metadata.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected encrypted content and raw Standard authentication bytes to remain redacted.');
}

echo '<!-- markerpdf-encrypted-permission-parameter-trailing-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-parameter-trailing-operand-currentbase',
    'native_boundary' => 'Standard security-handler /Length trailing operands fail closed before WordPress import permission reliance',
    'encrypted_text_blocked' => $plainText === '',
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'parameter_status' => $parameterReview['status'] ?? null,
    'parameter_violations' => $parameterReview['violations'] ?? [],
    'malformed_parameter_names' => $parameterReview['malformed_parameter_names'] ?? [],
    'length_entry_statuses' => is_array($lengthRow) ? ($lengthRow['entry_statuses'] ?? []) : [],
    'length_trailing_operand_shape' => $lengthEntry['trailing_operand_shape'] ?? null,
    'length_trailing_operand_preview' => $lengthEntry['trailing_operand_preview'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Parameter Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records the trailing Standard security-handler parameter operand before copy/extract permission bits are trusted for import.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-parameter-trailing-operand-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    ],
    'standard_security_handler_parameters' => [
        'status' => $parameterReview['status'] ?? null,
        'violations' => $parameterReview['violations'] ?? [],
        'malformed_parameter_names' => $parameterReview['malformed_parameter_names'] ?? [],
        'length_entry_statuses' => is_array($lengthRow) ? ($lengthRow['entry_statuses'] ?? []) : [],
        'length_trailing_operand_shape' => $lengthEntry['trailing_operand_shape'] ?? null,
        'length_trailing_operand_preview' => $lengthEntry['trailing_operand_preview'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
