<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress missing permission word encrypted leak) Tj ET';
$ownerValidation = 'WORDPRESS_MISSING_P_OWNER_VALIDATION_SHOULD_NOT_LEAK';
$userValidation = 'WORDPRESS_MISSING_P_USER_VALIDATION_SHOULD_NOT_LEAK';
$ownerBytes = str_pad($ownerValidation, 32, 'O');
$userBytes = str_pad($userValidation, 32, 'U');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
    . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
    . " /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$parameterReview = is_array($permission['standard_security_handler_parameter_review'] ?? null)
    ? $permission['standard_security_handler_parameter_review']
    : [];
$handlerReview = is_array($report['permission_handler_review'] ?? null) ? $report['permission_handler_review'] : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected missing-/P encrypted Standard text to stay blocked.');
}

if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_parameters') {
    throw new RuntimeException('Expected missing /P to be classified as malformed Standard parameters.');
}

if (!in_array('missing_standard_permission_word', $parameterReview['violations'] ?? [], true)) {
    throw new RuntimeException('Expected missing_standard_permission_word violation.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerValidation)
    || str_contains($encoded, $userValidation)
    || str_contains($encoded, $ownerBytes)
    || str_contains($encoded, $userBytes)
    || str_contains($encoded, strtoupper(bin2hex($ownerBytes)))
    || str_contains($encoded, strtoupper(bin2hex($userBytes)))
) {
    throw new RuntimeException('Expected encrypted content and raw Standard authentication bytes to remain redacted.');
}

echo '<!-- markerpdf-encrypted-missing-permission-word-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-missing-permission-word-currentbase',
    'native_boundary' => 'Standard encryption dictionaries without required /P fail closed as malformed permission preflight metadata before WordPress import',
    'encrypted_text_blocked' => $plainText === '',
    'permission_source' => $permission['source'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'parameter_status' => $parameterReview['status'] ?? null,
    'parameter_violations' => $parameterReview['violations'] ?? [],
    'permission_word_present' => $parameterReview['permission_word_present'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'handler_status' => $handlerReview['status'] ?? null,
    'raw_auth_material_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records the missing Standard permission word as malformed security metadata before any password prompt, decryption, or permission-enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-missing-permission-word-preflight ' . htmlspecialchars(json_encode([
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
        'version' => $parameterReview['version'] ?? null,
        'revision' => $parameterReview['revision'] ?? null,
        'key_length_bits' => $parameterReview['key_length_bits'] ?? null,
        'permission_word_present' => $parameterReview['permission_word_present'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
