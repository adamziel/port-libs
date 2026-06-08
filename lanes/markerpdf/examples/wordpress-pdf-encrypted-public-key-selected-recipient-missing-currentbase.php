<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress S5 top-level recipient encrypted text leak) Tj ET';
$recipient = 'WORDPRESS_S5_TOPLEVEL_RECIPIENT_SHOULD_NOT_LEAK';
$recipientHex = strtoupper(bin2hex($recipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /Recipients [<{$recipientHex}>]"
    . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
    . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$recipientReview = is_array($permission['public_key_recipient_review'] ?? null)
    ? $permission['public_key_recipient_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $recipient)
        || str_contains($encoded, $recipientHex)
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted S5 top-level-recipient text to stay blocked.');
}
if (($permission['policy'] ?? null) !== 'public_key_selected_recipient_permissions_missing') {
    throw new RuntimeException('Expected missing selected S5 recipient envelopes to block import explicitly.');
}
if (($recipientReview['selected_recipient_permissions_missing'] ?? null) !== true) {
    throw new RuntimeException('Expected selected-recipient-missing metadata.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted text and public-key recipient bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-public-key-selected-recipient-missing-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-public-key-selected-recipient-missing-currentbase',
    'native_boundary' => 'adbe.pkcs7.s5 top-level Recipients are inventoried but not selected permission envelopes',
    'encrypted_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'permission_boundary' => $permission['content_extraction_boundary'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
    'selected_recipient_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
    'recipient_count' => $recipientReview['recipient_count'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'selected_recipient_permissions_missing' => $recipientReview['selected_recipient_permissions_missing'] ?? null,
    'permission_decode_status' => $recipientReview['permission_decode_status'] ?? null,
    'raw_material_exposed' => $rawMaterialExposed,
    'executes_cms_parse' => $recipientReview['executes_cms_parse'] ?? null,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted public-key PDF text remains blocked because the S5 security handler has no selected crypt-filter recipient envelope for permission review.</p>\n";
echo "<!-- /wp:paragraph -->\n";
