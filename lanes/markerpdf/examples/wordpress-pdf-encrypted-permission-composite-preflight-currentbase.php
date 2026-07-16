<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$buildPdf = static function (string $operand, string $label, string $extraObjects = '') use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td (WordPress {$label} composite permission encrypted leak) Tj ET";
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
        . $extraObjects
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$summarize = static function (string $operand, string $label, string $extraObjects = '') use ($buildPdf): array {
    [$pdf, $content, $ownerValidation, $userValidation] = $buildPdf($operand, $label, $extraObjects);
    $preflight = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
    $declaration = is_array($permission['standard_permission_word_review'] ?? null)
        ? $permission['standard_permission_word_review']
        : [];
    $entry = is_array($declaration['entries'][0] ?? null) ? $declaration['entries'][0] : [];
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
        throw new RuntimeException("Expected {$label} composite /P operand to own permission preflight.");
    }
    if (!in_array('permission_word_composite_operand', $preflight['review_reasons'] ?? [], true)) {
        throw new RuntimeException("Expected {$label} review reasons to include composite permission operand.");
    }
    if (($permission['copy_or_extract_allowed'] ?? null) !== null || ($permission['permission_bits_reliable'] ?? null) !== false) {
        throw new RuntimeException("Expected {$label} composite /P operand to avoid bit-derived copy permission.");
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
        'operand_shape' => $entry['operand_shape'] ?? null,
        'integer_entry_count' => $declaration['integer_entry_count'] ?? null,
        'raw_material_exposed' => false,
    ];
};

$arrayOperand = $summarize('[-44]', 'array');
$dictionaryOperand = $summarize('18 0 R', 'dictionary', "18 0 obj\n<< /Value -44 >>\nendobj\n");

echo '<!-- markerpdf-encrypted-permission-composite-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-composite-preflight-currentbase',
    'native_boundary' => 'Standard /P operands that are arrays or dictionaries are malformed composite permission metadata, not scalar permission grants',
    'array_operand' => $arrayOperand,
    'dictionary_operand' => $dictionaryOperand,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Composite Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Array and dictionary Standard permission operands are recorded as malformed composite preflight metadata before any password prompt, decryption, or permission-enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-composite-preflight ' . htmlspecialchars(json_encode([
    'array_operand' => [
        'policy' => $arrayOperand['policy'],
        'entry_statuses' => $arrayOperand['entry_statuses'],
        'operand_shape' => $arrayOperand['operand_shape'],
        'permission_bits_reliable' => $arrayOperand['permission_bits_reliable'],
        'copy_or_extract_allowed' => $arrayOperand['copy_or_extract_allowed'],
    ],
    'dictionary_operand' => [
        'policy' => $dictionaryOperand['policy'],
        'entry_statuses' => $dictionaryOperand['entry_statuses'],
        'operand_shape' => $dictionaryOperand['operand_shape'],
        'permission_bits_reliable' => $dictionaryOperand['permission_bits_reliable'],
        'copy_or_extract_allowed' => $dictionaryOperand['copy_or_extract_allowed'],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
