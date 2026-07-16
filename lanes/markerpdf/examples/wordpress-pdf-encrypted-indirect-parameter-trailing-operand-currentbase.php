<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress indirect parameter trailing operand encrypted text leak) Tj ET';
$ownerValidation = str_repeat('W', 32);
$userValidation = str_repeat('P', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 7 0 R /R 8 0 R /Length 9 0 R"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata true >>\nendobj\n"
    . "7 0 obj\n4 /ShadowVersion 2\nendobj\n"
    . "8 0 obj\n4 10 0 R\nendobj\n"
    . "9 0 obj\n128 256\nendobj\n"
    . "10 0 obj\n5\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$parameterReview = is_array($permission['standard_security_handler_parameter_review'] ?? null)
    ? $permission['standard_security_handler_parameter_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted content to stay blocked.');
}

if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
    || str_contains($encoded, strtoupper(bin2hex($userValidation)))
) {
    throw new RuntimeException('Expected encrypted payload and authentication material to stay redacted.');
}

if (($parameterReview['parameters_well_formed'] ?? null) !== false) {
    throw new RuntimeException('Expected indirect trailing Standard parameters to fail closed.');
}

$rowSummary = [];
foreach ($parameterReview['parameter_declaration_review']['rows'] ?? [] as $row) {
    if (!is_array($row) || !is_string($row['pdf_name'] ?? null)) {
        continue;
    }

    $entry = is_array($row['entries'][0] ?? null) ? $row['entries'][0] : [];
    $rowSummary[$row['pdf_name']] = [
        'status' => $row['selected_entry_status'] ?? null,
        'reference_object' => $row['selected_entry_reference_object_number'] ?? null,
        'reference_generation' => $row['selected_entry_reference_generation'] ?? null,
        'single_value' => $entry['single_value'] ?? null,
        'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
        'trailing_operand_preview' => $entry['trailing_operand_preview'] ?? null,
    ];
}

echo '<!-- markerpdf-encrypted-indirect-parameter-trailing-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-indirect-parameter-trailing-operand-currentbase',
    'native_boundary' => 'Standard security-handler indirect /V /R /Length operands must resolve to one scalar token before permission preflight',
    'plain_text_blocked' => true,
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'parameters_well_formed' => $parameterReview['parameters_well_formed'] ?? null,
    'parameter_status' => $parameterReview['status'] ?? null,
    'violations' => $parameterReview['violations'] ?? [],
    'malformed_parameter_names' => $parameterReview['malformed_parameter_names'] ?? [],
    'rows' => $rowSummary,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF content remains blocked. Indirect Standard security-handler parameters with trailing operands are treated as review-only malformed permission metadata before WordPress import.</p>\n";
echo "<!-- /wp:paragraph -->\n";
