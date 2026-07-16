<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildPdf = static function (string $operand, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td (WordPress {$label} permission operand encrypted leak) Tj ET";
    $ownerValidation = str_repeat(substr($label, 0, 1), 32);
    $userValidation = str_repeat(substr($label, -1), 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P {$operand} /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$summarize = static function (string $operand, string $label) use ($buildPdf): array {
    [$pdf, $content, $ownerValidation, $userValidation] = $buildPdf($operand, $label);
    $preflight = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
    $declaration = is_array($permission['standard_permission_word_review'] ?? null)
        ? $permission['standard_permission_word_review']
        : [];
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
    if (($permission['source'] ?? null) !== 'standard_security_handler_malformed_permissions') {
        throw new RuntimeException("Expected {$label} malformed /P operand to own permission preflight.");
    }
    if (($permission['copy_or_extract_allowed'] ?? null) !== null || ($permission['permission_bits_reliable'] ?? null) !== false) {
        throw new RuntimeException("Expected {$label} malformed /P operand to avoid bit-derived copy permission.");
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
        'permission_hex' => $permission['permission_hex'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
        'declaration_status' => $declaration['status'] ?? null,
        'entry_statuses' => $declaration['entry_statuses'] ?? [],
        'integer_entry_count' => $declaration['integer_entry_count'] ?? null,
        'raw_material_exposed' => false,
    ];
};

$nonInteger = $summarize('(copy-ok)', 'non-integer');
$unresolved = $summarize('99 0 R', 'unresolved-reference');

echo '<!-- markerpdf-encrypted-permission-token-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-token-preflight-currentbase',
    'native_boundary' => 'Standard /P operands that are non-integer or unresolved are malformed review metadata, not missing permissions',
    'non_integer' => $nonInteger,
    'unresolved_reference' => $unresolved,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Operand Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Non-integer and unresolved Standard permission operands are recorded as malformed preflight metadata before any password prompt, decryption, or permission-enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-token-preflight ' . htmlspecialchars(json_encode([
    'non_integer' => [
        'policy' => $nonInteger['policy'],
        'entry_statuses' => $nonInteger['entry_statuses'],
        'permission_bits_reliable' => $nonInteger['permission_bits_reliable'],
        'copy_or_extract_allowed' => $nonInteger['copy_or_extract_allowed'],
    ],
    'unresolved_reference' => [
        'policy' => $unresolved['policy'],
        'entry_statuses' => $unresolved['entry_statuses'],
        'permission_bits_reliable' => $unresolved['permission_bits_reliable'],
        'copy_or_extract_allowed' => $unresolved['copy_or_extract_allowed'],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
