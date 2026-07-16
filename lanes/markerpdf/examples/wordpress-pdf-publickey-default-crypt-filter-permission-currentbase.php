<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress public-key default EFF encrypted text should not import) Tj ET';
$recipientOne = 'WP_PUBLICKEY_DEFAULT_EFF_RECIPIENT_ONE_SHOULD_NOT_LEAK';
$recipientTwo = 'WP_PUBLICKEY_DEFAULT_EFF_RECIPIENT_TWO_SHOULD_NOT_LEAK';
$recipientOneHex = strtoupper(bin2hex($recipientOne));
$recipientTwoHex = strtoupper(bin2hex($recipientTwo));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$recipientOneHex}> 6 0 R] >> >>"
    . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n<{$recipientTwoHex}>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($preflight['permission_preflight'] ?? null) ? $preflight['permission_preflight'] : [];
$recipientReview = is_array($permission['public_key_recipient_review'] ?? null)
    ? $permission['public_key_recipient_review']
    : [];
$selection = is_array($recipientReview['crypt_filter_selection'] ?? null)
    ? $recipientReview['crypt_filter_selection']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawRecipientMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $recipientOne)
        || str_contains($encoded, $recipientTwo)
        || str_contains($encoded, $recipientOneHex)
        || str_contains($encoded, $recipientTwoHex)
        || str_contains($encoded, $content)
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted public-key fixture text to stay blocked.');
}
if (($selection['declared_content_filters']['embedded_file_filter'] ?? null) !== 'DefaultCryptFilter') {
    throw new RuntimeException('Expected omitted EFF to be defaulted into public-key recipient selection.');
}
if (($selection['selected_recipient_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected defaulted EFF to reuse, not double-count, the DefaultCryptFilter recipient envelopes.');
}
if ($rawRecipientMaterialExposed) {
    throw new RuntimeException('Expected public-key recipient material to remain hashed/review-only.');
}

echo '<!-- markerpdf-publickey-default-crypt-filter-permission-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-publickey-default-crypt-filter-permission-currentbase',
    'native_boundary' => 'omitted public-key EFF inherits StmF before recipient permission review without CMS parsing or decryption',
    'text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'declared_content_filters' => $selection['declared_content_filters'] ?? [],
    'defaulted_content_filters' => $selection['defaulted_content_filters'] ?? [],
    'content_filter_sources' => $selection['content_filter_sources'] ?? [],
    'selected_recipient_count' => $selection['selected_recipient_count'] ?? null,
    'selected_recipient_filters' => $selection['selected_recipient_filter_names'] ?? [],
    'raw_recipient_material_exposed' => false,
    'executes_cms_parse' => $recipientReview['executes_cms_parse'] ?? null,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $preflight['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Public-Key Recipient Defaults</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted public-key PDF text remains blocked. Recipient permission envelopes are counted through the defaulted embedded-file crypt-filter role as review metadata only.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:publickey-default-crypt-filter-permission ' . htmlspecialchars(json_encode([
    'permission_policy' => $permission['policy'] ?? null,
    'content_boundary' => $permission['content_extraction_boundary'] ?? null,
    'recipient_review' => [
        'source_policy' => $recipientReview['recipient_source_policy'] ?? null,
        'recipient_count' => $recipientReview['recipient_count'] ?? null,
        'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
        'recipient_sha256' => $recipientReview['selected_recipient_sha256'] ?? [],
        'selected_filters' => $recipientReview['selected_crypt_filter_recipient_filter_names'] ?? [],
    ],
    'crypt_filter_selection' => [
        'declared_content_filters' => $selection['declared_content_filters'] ?? [],
        'defaulted_content_filters' => $selection['defaulted_content_filters'] ?? [],
        'content_filter_sources' => $selection['content_filter_sources'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
