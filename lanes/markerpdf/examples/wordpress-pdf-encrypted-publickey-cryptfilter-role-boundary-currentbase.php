<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress malformed public-key role encrypted leak) Tj ET';
$recipient = 'WORDPRESS_PUBLICKEY_MALFORMED_STMF_RECIPIENT_SHOULD_NOT_LEAK';
$recipientHex = strtoupper(bin2hex($recipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$recipientHex}>] >> >>"
    . " /StmF /DefaultCryptFilter 9 0 R /EncryptMetadata true >>\nendobj\n"
    . "9 0 obj\n/Identity\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$recipientReview = is_array($permission['public_key_recipient_review'] ?? null) ? $permission['public_key_recipient_review'] : [];
$selection = is_array($recipientReview['crypt_filter_selection'] ?? null) ? $recipientReview['crypt_filter_selection'] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);

$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $recipient)
        || str_contains($encoded, $recipientHex)
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted public-key text to remain blocked.');
}
if (($permission['source'] ?? null) !== 'public_key_crypt_filter_role_declaration_malformed') {
    throw new RuntimeException('Expected malformed public-key crypt-filter role to own permission preflight.');
}
if (($recipientReview['selected_recipient_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected malformed StmF role to suppress selected recipient envelopes.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted payload and recipient bytes to remain redacted.');
}

$summary = [
    'scenario' => 'wordpress-pdf-encrypted-publickey-cryptfilter-role-boundary-currentbase',
    'native_boundary' => 'malformed public-key StmF role suppresses crypt-filter recipient selection before import',
    'text_blocked' => $plainText === '',
    'policy' => $permission['policy'] ?? null,
    'source' => $permission['source'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'review_reasons' => $preflight['review_reasons'] ?? [],
    'recipient_count' => $recipientReview['recipient_count'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'selected_recipient_filters' => $recipientReview['selected_crypt_filter_recipient_filter_names'] ?? [],
    'unselected_recipient_filters' => $recipientReview['unselected_crypt_filter_recipient_filter_names'] ?? [],
    'role_fail_closed' => $recipientReview['crypt_filter_role_declaration_fail_closed'] ?? null,
    'role_fail_closed_pdf_names' => $recipientReview['crypt_filter_role_fail_closed_pdf_names'] ?? [],
    'selection_suppressed' => $selection['role_declaration_fail_closed'] ?? null,
    'raw_material_exposed' => false,
    'executes_cms_parse' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-encrypted-publickey-cryptfilter-role-boundary-currentbase-smoke ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted Public-Key PDF Role Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. A malformed public-key crypt-filter role is review-only metadata and cannot select recipient permission envelopes for WordPress import.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-publickey-cryptfilter-role-boundary ' . htmlspecialchars(json_encode([
    'policy' => $summary['policy'],
    'source' => $summary['source'],
    'selected_recipient_count' => $summary['selected_recipient_count'],
    'unselected_recipient_filters' => $summary['unselected_recipient_filters'],
    'role_fail_closed_pdf_names' => $summary['role_fail_closed_pdf_names'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
