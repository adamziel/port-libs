<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress crypt-filter parameter tail text leak) Tj ET';
$ownerKey = 'WP_CRYPT_FILTER_PARAMETER_TAIL_OWNER';
$userKey = 'WP_CRYPT_FILTER_PARAMETER_TAIL_USER';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF <<"
    . " /DocCF << /CFM /AESV2 9 0 R /AuthEvent /DocOpen /Length 16 >>"
    . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >>"
    . " >>"
    . " /StmF /DocCF /StrF /DocCF /EFF /ClearEmbedded >>\nendobj\n"
    . "9 0 obj\n/Identity\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = $metadata['encryption'] ?? [];
$docFilter = is_array($encryption['crypt_filters']['DocCF'] ?? null) ? $encryption['crypt_filters']['DocCF'] : [];
$parameterReview = is_array($docFilter['parameter_declaration_review'] ?? null) ? $docFilter['parameter_declaration_review'] : [];
$cfmRow = [];
foreach (($parameterReview['rows'] ?? []) as $row) {
    if (is_array($row) && ($row['pdf_name'] ?? null) === 'CFM') {
        $cfmRow = $row;
        break;
    }
}
$cfmEntry = is_array($cfmRow['entries'][0] ?? null) ? $cfmRow['entries'][0] : [];
$review = $preflight['crypt_filter_content_review'] ?? [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-crypt-filter-parameter-tail-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-crypt-filter-parameter-trailing-operand-currentbase',
    'native_boundary' => 'crypt-filter parameter values with trailing top-level operands fail closed before WordPress import',
    'encrypted_text_blocked' => $plainText === '',
    'import_decision' => $preflight['import_decision'] ?? null,
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'content_extraction_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    'text_content_policy' => $review['text_content_policy'] ?? null,
    'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
    'fail_closed_filter_names' => $review['fail_closed_filter_names'] ?? [],
    'malformed_parameter_names' => $docFilter['malformed_parameter_names'] ?? [],
    'selected_parameter_status' => $cfmRow['selected_entry_status'] ?? null,
    'trailing_operand_shape' => $cfmEntry['trailing_operand_shape'] ?? null,
    'trailing_operand_preview' => $cfmEntry['trailing_operand_preview'] ?? null,
    'raw_key_material_exposed' => is_string($encoded) && (
        str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex($ownerKey)))
        || str_contains($encoded, strtoupper(bin2hex($userKey)))
    ),
    'content_exposed_in_review_json' => is_string($encoded) && str_contains($encoded, $content),
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Crypt-Filter Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked when crypt-filter parameters contain trailing operands; the import keeps only review metadata and requires decryption before content extraction.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-crypt-filter-parameter-tail ' . htmlspecialchars(json_encode([
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'content_extraction_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    'crypt_filter_review' => $review,
    'parameter_declaration_review' => $parameterReview,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
