<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $decoy, string $label): array {
    $content = "BT /F1 12 Tf 72 720 Td (WordPress {$label} top-level permission boundary encrypted text leak) Tj ET";
    $ownerValidation = str_repeat(substr($label, 0, 1), 32);
    $userValidation = str_repeat(substr($label, -1), 32);
    $hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$decoy} /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$summarize = static function (string $decoy, string $label) use ($buildPdf): array {
    [$pdf, $content, $ownerValidation, $userValidation] = $buildPdf($decoy, $label);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
    $declaration = is_array($permission['standard_permission_word_review'] ?? null)
        ? $permission['standard_permission_word_review']
        : [];
    $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
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
    if (($permission['source'] ?? null) !== 'standard_security_handler_permissions') {
        throw new RuntimeException("Expected {$label} permission preflight to use the real top-level /P entry.");
    }
    if (($permission['policy'] ?? null) !== 'copy_extract_allowed_after_decryption') {
        throw new RuntimeException("Expected {$label} permission word to allow copy only after decryption.");
    }
    if (($declaration['declared_entry_count'] ?? null) !== 1 || ($declaration['duplicate_permission_entries'] ?? null) !== false) {
        throw new RuntimeException("Expected {$label} malformed decoy tokens not to count as duplicate /P entries.");
    }
    if ($rawMaterialExposed) {
        throw new RuntimeException("Expected {$label} encrypted content and authentication material to remain redacted.");
    }

    return [
        'text_blocked' => $plainText === '',
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'declared_permission_entries' => $declaration['declared_entry_count'] ?? null,
        'duplicate_permission_entries' => $declaration['duplicate_permission_entries'] ?? null,
        'permission_hex' => $permission['permission_hex'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
        'raw_material_exposed' => false,
    ];
};

$cases = [
    'literal_decoy' => $summarize('(literal decoy /P -64 should not count)', 'literal'),
    'nested_dictionary_decoy' => $summarize('<< /P -64 /Reason (nested decoy) >>', 'dictionary'),
    'array_decoy' => $summarize('[ /P -64 (array decoy) ]', 'array'),
    'hex_decoy' => $summarize('<2F50202D363420686578206465636F79>', 'hex'),
    'comment_decoy' => $summarize("% /P -64 comment decoy\n", 'comment'),
];

echo '<!-- markerpdf-encrypted-permission-top-level-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-permission-top-level-boundary-currentbase',
    'native_boundary' => 'malformed literal, dictionary, array, hex, and comment decoys are skipped before Standard /P permission preflight',
    'cases' => $cases,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Boundary Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Malformed decoy tokens before the real Standard permission word are review metadata only and do not create duplicate permission entries.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-permission-top-level-boundary ' . htmlspecialchars(json_encode([
    'permission_entry_counts' => array_map(
        static fn (array $case): int => (int) $case['declared_permission_entries'],
        $cases
    ),
    'duplicate_permission_entries' => array_map(
        static fn (array $case): bool => (bool) $case['duplicate_permission_entries'],
        $cases
    ),
    'permission_policies' => array_map(
        static fn (array $case): ?string => is_string($case['policy'] ?? null) ? $case['policy'] : null,
        $cases
    ),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
