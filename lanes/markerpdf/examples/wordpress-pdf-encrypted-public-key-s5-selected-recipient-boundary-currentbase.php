<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress S5 selected recipient boundary encrypted leak) Tj ET';
$selectedRecipient = 'WORDPRESS_S5_SELECTED_CRYPT_FILTER_RECIPIENT_SHOULD_NOT_LEAK';
$legacyDecoyRecipient = 'WORDPRESS_S5_LEGACY_TOPLEVEL_RECIPIENT_DECOY_SHOULD_NOT_LEAK';
$selectedRecipientHex = strtoupper(bin2hex($selectedRecipient));
$legacyDecoyRecipientHex = strtoupper(bin2hex($legacyDecoyRecipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /Recipients [<{$legacyDecoyRecipientHex}>] 6 0 R"
    . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$selectedRecipientHex}>] >> >>"
    . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n<< /IgnoredLegacyRecipientTrailer true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = $report['permission_preflight'] ?? [];
$recipientReview = is_array($permission['public_key_recipient_review'] ?? null)
    ? $permission['public_key_recipient_review']
    : [];
$selection = is_array($recipientReview['crypt_filter_selection'] ?? null)
    ? $recipientReview['crypt_filter_selection']
    : [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted S5 public-key fixture text to stay blocked.');
}

if (($permission['policy'] ?? null) !== 'public_key_recipient_permissions_blocked_without_private_key') {
    throw new RuntimeException('Expected selected S5 crypt-filter recipient permissions to remain blocked without a private key.');
}

if (($recipientReview['selected_recipient_source_policy'] ?? null) !== 'crypt_filter_recipients') {
    throw new RuntimeException('Expected S5 permission source to come from crypt-filter recipients.');
}

if (($recipientReview['selected_recipient_declaration_fail_closed'] ?? null) !== false) {
    throw new RuntimeException('Expected malformed legacy top-level Recipients decoy not to mark selected recipients malformed.');
}

if (($recipientReview['recipient_declaration_fail_closed'] ?? null) !== true) {
    throw new RuntimeException('Expected aggregate recipient diagnostics to retain the malformed legacy decoy.');
}

if (($selection['selected_recipient_count'] ?? null) !== 1 || ($permission['selected_public_key_recipient_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected one selected S5 crypt-filter recipient envelope.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $selectedRecipient)
    || str_contains($encoded, $legacyDecoyRecipient)
    || str_contains($encoded, $selectedRecipientHex)
    || str_contains($encoded, $legacyDecoyRecipientHex)
) {
    throw new RuntimeException('Expected encrypted text and recipient bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-public-key-s5-selected-recipient-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-public-key-s5-selected-recipient-boundary-currentbase',
    'native_boundary' => 'S5 public-key preflight selects crypt-filter recipients while retaining malformed legacy top-level Recipients as aggregate diagnostics only',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'permission_source' => $permission['source'] ?? null,
    'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
    'selected_recipient_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
    'recipient_declaration_fail_closed' => $recipientReview['recipient_declaration_fail_closed'] ?? null,
    'selected_recipient_declaration_fail_closed' => $recipientReview['selected_recipient_declaration_fail_closed'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'selected_filter_names' => $selection['selected_recipient_filter_names'] ?? [],
    'raw_recipient_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted S5 Public-Key PDF Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records selected S5 crypt-filter recipients as the permission source while malformed legacy top-level recipient data remains review-only diagnostics.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-public-key-s5-selected-recipient-boundary ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    ],
    'public_key_recipients' => [
        'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
        'selected_recipient_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
        'recipient_declaration_fail_closed' => $recipientReview['recipient_declaration_fail_closed'] ?? null,
        'selected_recipient_declaration_fail_closed' => $recipientReview['selected_recipient_declaration_fail_closed'] ?? null,
        'recipient_entry_statuses' => $recipientReview['recipient_entry_statuses'] ?? [],
        'selected_recipient_entry_statuses' => $recipientReview['selected_recipient_entry_statuses'] ?? [],
        'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
        'selected_crypt_filter_recipient_filter_names' => $recipientReview['selected_crypt_filter_recipient_filter_names'] ?? [],
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
