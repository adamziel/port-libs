<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildPdf = static function (string $versionOperand, string $revisionOperand, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td (WordPress {$label} Standard parameter operand leak) Tj ET";
    $ownerValidation = str_repeat('V', 32);
    $userValidation = str_repeat('R', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V {$versionOperand} /R {$revisionOperand} /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$summarize = static function (
    string $versionOperand,
    string $revisionOperand,
    string $label,
    string $expectedParameterName,
    string $expectedStatus,
    string $expectedShape,
    array $expectedTrailingShapes
) use ($buildPdf): array {
    [$pdf, $content, $ownerValidation, $userValidation] = $buildPdf($versionOperand, $revisionOperand, $label);
    $preflight = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
    $encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
    $rawMaterialExposed = is_string($encoded)
        && (
            str_contains($encoded, $content)
            || str_contains($encoded, $ownerValidation)
            || str_contains($encoded, $userValidation)
            || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        );

    if ($plainText !== '') {
        throw new RuntimeException("Expected {$label} encrypted text to stay blocked.");
    }
    if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_parameters') {
        throw new RuntimeException("Expected {$label} malformed Standard parameters to own permission preflight.");
    }
    if (($permission['copy_or_extract_allowed'] ?? null) !== null || ($permission['permission_bits_reliable'] ?? null) !== false) {
        throw new RuntimeException("Expected {$label} malformed Standard parameters to avoid bit-derived copy permission.");
    }
    if (($permission['standard_security_handler_malformed_parameter_names'] ?? []) !== [$expectedParameterName]
        || ($permission['standard_security_handler_malformed_parameter_statuses'] ?? []) !== [$expectedStatus]
        || ($permission['standard_security_handler_malformed_parameter_operand_shapes'] ?? []) !== [$expectedShape]
        || ($permission['standard_security_handler_malformed_parameter_trailing_operand_shapes'] ?? []) !== $expectedTrailingShapes
    ) {
        throw new RuntimeException("Expected {$label} malformed Standard parameter operand diagnostics.");
    }
    if ($rawMaterialExposed) {
        throw new RuntimeException("Expected {$label} encrypted content and authentication material to remain redacted.");
    }

    return [
        'text_blocked' => $plainText === '',
        'policy' => $permission['policy'] ?? null,
        'source' => $permission['source'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'review_reasons' => $preflight['review_reasons'] ?? [],
        'malformed_parameter_names' => $permission['standard_security_handler_malformed_parameter_names'] ?? [],
        'malformed_parameter_statuses' => $permission['standard_security_handler_malformed_parameter_statuses'] ?? [],
        'malformed_parameter_operand_shapes' => $permission['standard_security_handler_malformed_parameter_operand_shapes'] ?? [],
        'malformed_parameter_trailing_operand_shapes' => $permission['standard_security_handler_malformed_parameter_trailing_operand_shapes'] ?? [],
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'raw_material_exposed' => false,
    ];
};

$versionTrailing = $summarize(
    '4 9 0 R',
    '4',
    'version-trailing',
    'V',
    'standard_security_handler_parameter_trailing_operand_review',
    'token',
    ['indirect_reference']
);
$revisionComposite = $summarize(
    '4',
    '[4]',
    'revision-composite',
    'R',
    'standard_security_handler_parameter_composite_operand_review',
    'array',
    []
);

echo '<!-- markerpdf-encrypted-version-revision-operand-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-version-revision-operand-boundary-currentbase',
    'native_boundary' => 'malformed Standard /V and /R operands fail closed before permission bits are trusted',
    'version_trailing' => $versionTrailing,
    'revision_composite' => $revisionComposite,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Standard Parameter Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Malformed Standard security-handler version and revision operands are reported as permission preflight metadata before any password prompt, decryption, or permission-enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-version-revision-operand-boundary ' . htmlspecialchars(json_encode([
    'version_trailing' => [
        'policy' => $versionTrailing['policy'],
        'malformed_parameter_names' => $versionTrailing['malformed_parameter_names'],
        'malformed_parameter_statuses' => $versionTrailing['malformed_parameter_statuses'],
        'malformed_parameter_trailing_operand_shapes' => $versionTrailing['malformed_parameter_trailing_operand_shapes'],
        'permission_bits_reliable' => $versionTrailing['permission_bits_reliable'],
        'copy_or_extract_allowed' => $versionTrailing['copy_or_extract_allowed'],
    ],
    'revision_composite' => [
        'policy' => $revisionComposite['policy'],
        'malformed_parameter_names' => $revisionComposite['malformed_parameter_names'],
        'malformed_parameter_statuses' => $revisionComposite['malformed_parameter_statuses'],
        'malformed_parameter_operand_shapes' => $revisionComposite['malformed_parameter_operand_shapes'],
        'permission_bits_reliable' => $revisionComposite['permission_bits_reliable'],
        'copy_or_extract_allowed' => $revisionComposite['copy_or_extract_allowed'],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
