<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress public-key ambiguous subfilter encrypted text should not import) Tj ET';
$legacyRecipient = 'WP_PUBLICKEY_SUBFILTER_LEGACY_RECIPIENT_SHOULD_NOT_LEAK';
$cryptFilterRecipient = 'WP_PUBLICKEY_SUBFILTER_CRYPT_FILTER_RECIPIENT_SHOULD_NOT_LEAK';
$legacyRecipientHex = strtoupper(bin2hex($legacyRecipient));
$cryptFilterRecipientHex = strtoupper(bin2hex($cryptFilterRecipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s3 /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /Recipients [<{$legacyRecipientHex}>]"
    . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$cryptFilterRecipientHex}>] >> >>"
    . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$recipientReview = is_array($permission['public_key_recipient_review'] ?? null)
    ? $permission['public_key_recipient_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawRecipientMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $legacyRecipient)
        || str_contains($encoded, $cryptFilterRecipient)
        || str_contains($encoded, $legacyRecipientHex)
        || str_contains($encoded, $cryptFilterRecipientHex)
        || str_contains($encoded, $content)
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted public-key fixture text to stay blocked.');
}
if (($permission['source'] ?? null) !== 'security_handler_subfilter_declaration_malformed') {
    throw new RuntimeException('Expected duplicate SubFilter declarations to fail closed before public-key permission selection.');
}
if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_encrypted_security_handler_subfilter_malformed') {
    throw new RuntimeException('Expected malformed SubFilter boundary to block encrypted content extraction.');
}
if (($recipientReview['selected_recipient_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected ambiguous SubFilter declarations to suppress selected recipient permission envelopes.');
}
if ($rawRecipientMaterialExposed) {
    throw new RuntimeException('Expected public-key recipient material to remain hashed/review-only.');
}

echo '<!-- markerpdf-publickey-subfilter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-publickey-subfilter-boundary-currentbase',
    'native_boundary' => 'duplicate public-key SubFilter declarations fail closed before recipient permission selection',
    'text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'subfilter_declaration_status' => $permission['security_handler_subfilter_declaration_status'] ?? null,
    'recipient_count' => $recipientReview['recipient_count'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
    'raw_recipient_material_exposed' => false,
    'executes_cms_parse' => $recipientReview['executes_cms_parse'] ?? null,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $preflight['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Public-Key SubFilter Boundary</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted public-key PDF text remains blocked when SubFilter declarations disagree. Recipient permission envelopes are inventoried as hashes, but no envelope is selected until native CMS permission decoding can trust the security-handler boundary.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:publickey-subfilter-boundary ' . htmlspecialchars(json_encode([
    'permission_policy' => $permission['policy'] ?? null,
    'content_boundary' => $permission['content_extraction_boundary'] ?? null,
    'subfilter_declaration' => [
        'status' => $permission['security_handler_subfilter_declaration_status'] ?? null,
        'fail_closed' => $permission['security_handler_subfilter_declaration_fail_closed'] ?? null,
        'subfilter_names' => $permission['security_handler_subfilter_names'] ?? [],
    ],
    'recipient_review' => [
        'source_policy' => $recipientReview['recipient_source_policy'] ?? null,
        'recipient_count' => $recipientReview['recipient_count'] ?? null,
        'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
        'selected_filters' => $recipientReview['selected_crypt_filter_recipient_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
