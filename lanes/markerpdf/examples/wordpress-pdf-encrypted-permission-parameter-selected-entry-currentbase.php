<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate Length selected-entry encrypted leak) Tj ET';
$ownerValidation = str_repeat('D', 32);
$userValidation = str_repeat('S', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 40 /Length 128"
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
$lengthRow = [];
foreach ($declaration['rows'] ?? [] as $row) {
    if (is_array($row) && ($row['pdf_name'] ?? null) === 'Length') {
        $lengthRow = $row;
        break;
    }
}
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected duplicate-Length encrypted text to stay blocked.');
}

if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_parameters') {
    throw new RuntimeException('Expected duplicate Standard Length parameters to fail closed as malformed parameters.');
}

if (($lengthRow['selected_entry_status'] ?? null) !== 'standard_security_handler_parameter_entry_well_formed'
    || ($lengthRow['selected_integer_value'] ?? null) !== 128
    || ($lengthRow['duplicate_entries'] ?? null) !== true
) {
    throw new RuntimeException('Expected duplicate Length review to expose the selected entry as review-only metadata.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected duplicate-Length encrypted content and authentication bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-permission-parameter-selected-entry-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-parameter-selected-entry-currentbase',
    'native_boundary' => 'duplicate Standard key-length parameters expose selected-entry provenance while permission reliance fails closed',
    'encrypted_text_blocked' => $plainText === '',
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'parameter_status' => $parameterReview['status'] ?? null,
    'parameter_violations' => $parameterReview['violations'] ?? [],
    'duplicate_parameter_names' => $parameterReview['duplicate_parameter_names'] ?? [],
    'selected_length_entry_index' => $lengthRow['selected_entry_index'] ?? null,
    'selected_length_entry_status' => $lengthRow['selected_entry_status'] ?? null,
    'selected_length_integer_value' => $lengthRow['selected_integer_value'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Parameter Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Duplicate Standard key-length declarations are preserved as review metadata before any password prompt, decryption, or permission-enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-parameter-selected-entry ' . htmlspecialchars(json_encode([
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
        'duplicate_parameter_names' => $parameterReview['duplicate_parameter_names'] ?? [],
        'selected_length_entry_index' => $lengthRow['selected_entry_index'] ?? null,
        'selected_length_entry_status' => $lengthRow['selected_entry_status'] ?? null,
        'selected_length_integer_value' => $lengthRow['selected_integer_value'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
