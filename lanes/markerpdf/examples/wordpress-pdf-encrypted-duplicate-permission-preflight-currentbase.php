<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate permission encrypted leak) Tj ET';
$ownerValidation = str_repeat('A', 32);
$userValidation = str_repeat('B', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -60 /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = $report['permission_preflight'] ?? [];
$declaration = is_array($permission['standard_permission_word_review'] ?? null)
    ? $permission['standard_permission_word_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-duplicate-permission-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-duplicate-permission-preflight-currentbase',
    'native_boundary' => 'duplicate Standard /P permission keys are invalid and permission reliance fails closed before WordPress import',
    'text_blocked' => (new PdfTextExtractor())->extractPlainText($pdf) === '',
    'policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_word_status' => $permission['permission_handler_review']['permission_word_status'] ?? null,
    'duplicate_permission_entries' => $declaration['duplicate_permission_entries'] ?? null,
    'permission_word_ambiguous' => $declaration['permission_word_ambiguous'] ?? null,
    'conflicting_permission_names' => $declaration['conflicting_permission_names'] ?? [],
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'raw_auth_material_exposed' => is_string($encoded)
        && (
            str_contains($encoded, $content)
            || str_contains($encoded, $ownerValidation)
            || str_contains($encoded, $userValidation)
            || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            || str_contains($encoded, strtoupper(bin2hex($userValidation)))
        ),
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. Duplicate Standard permission declarations are reported as ambiguous review metadata before any password prompt, decryption, or permission enforcement path.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-duplicate-permission-preflight ' . htmlspecialchars(json_encode([
    'policy' => $permission['policy'] ?? null,
    'permission_hex_values' => $declaration['hex_values'] ?? [],
    'conflicting_permission_names' => $declaration['conflicting_permission_names'] ?? [],
    'review_reasons' => $report['review_reasons'] ?? [],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
