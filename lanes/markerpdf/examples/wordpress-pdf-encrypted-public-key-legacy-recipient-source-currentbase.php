<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress legacy public-key recipient source encrypted leak) Tj ET';
$legacyRecipient = 'WORDPRESS_LEGACY_PUBLICKEY_TOPLEVEL_RECIPIENT_SHOULD_NOT_LEAK';
$cryptFilterRecipient = 'WORDPRESS_LEGACY_PUBLICKEY_CRYPT_FILTER_DECOY_SHOULD_NOT_LEAK';
$legacyRecipientHex = strtoupper(bin2hex($legacyRecipient));
$cryptFilterRecipientHex = strtoupper(bin2hex($cryptFilterRecipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s4 /V 4 /Length 128"
    . " /Recipients [<{$legacyRecipientHex}>]"
    . " /CF << /LegacyDecoyFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$cryptFilterRecipientHex}>] >> >>"
    . " /StmF /LegacyDecoyFilter /StrF /LegacyDecoyFilter /EFF /Identity /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$permission = $report['permission_preflight'] ?? [];
$recipientReview = $permission['public_key_recipient_review'] ?? [];
$selection = $recipientReview['crypt_filter_selection'] ?? [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted legacy public-key fixture text to stay blocked.');
}

if (($recipientReview['selected_recipient_source_policy'] ?? null) !== 'encryption_dictionary_recipients') {
    throw new RuntimeException('Expected legacy public-key permission source to stay on top-level Recipients.');
}

if (($recipientReview['selected_recipient_count'] ?? null) !== 1 || ($selection['selected_recipient_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected crypt-filter recipient decoys to remain unselected for legacy public-key SubFilter.');
}

if (($selection['selection_suppressed_by_legacy_subfilter'] ?? null) !== true) {
    throw new RuntimeException('Expected legacy SubFilter recipient selection suppression metadata.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $legacyRecipient)
    || str_contains($encoded, $cryptFilterRecipient)
    || str_contains($encoded, $legacyRecipientHex)
    || str_contains($encoded, $cryptFilterRecipientHex)
) {
    throw new RuntimeException('Expected encrypted text and recipient bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-public-key-legacy-recipient-source-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-public-key-legacy-recipient-source-currentbase',
    'native_boundary' => 'Legacy public-key SubFilter uses top-level Recipients for permission preflight while crypt-filter recipient arrays remain review-only decoys',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'permission_source' => $permission['source'] ?? null,
    'selected_recipient_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'selected_crypt_filter_recipient_count' => $selection['selected_recipient_count'] ?? null,
    'unselected_crypt_filter_recipients' => $recipientReview['unselected_crypt_filter_recipient_filter_names'] ?? [],
    'crypt_filter_selection_suppressed_by_legacy_subfilter' => $selection['selection_suppressed_by_legacy_subfilter'] ?? null,
    'raw_recipient_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted Public-Key PDF Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records that legacy public-key permissions come from top-level recipient envelopes while crypt-filter recipient arrays are retained only as review metadata.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-public-key-legacy-recipient-source ' . htmlspecialchars(json_encode([
    'permission' => [
        'source' => $permission['source'] ?? null,
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    ],
    'public_key_recipients' => [
        'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
        'selected_recipient_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
        'recipient_count' => $recipientReview['recipient_count'] ?? null,
        'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
        'crypt_filter_recipient_filter_names' => $recipientReview['crypt_filter_recipient_filter_names'] ?? [],
        'selected_crypt_filter_recipient_filter_names' => $recipientReview['selected_crypt_filter_recipient_filter_names'] ?? [],
        'unselected_crypt_filter_recipient_filter_names' => $recipientReview['unselected_crypt_filter_recipient_filter_names'] ?? [],
        'legacy_subfilter_selection_suppressed' => $selection['selection_suppressed_by_legacy_subfilter'] ?? null,
    ],
    'security' => [
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
