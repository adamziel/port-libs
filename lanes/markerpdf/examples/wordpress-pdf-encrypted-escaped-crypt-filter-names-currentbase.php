<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress escaped crypt-filter encrypted leak) Tj ET';
$recipient = 'WORDPRESS_ESCAPED_CRYPT_FILTER_RECIPIENT_SHOULD_NOT_LEAK';
$recipientHex = strtoupper(bin2hex($recipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /#46ilter /Adobe.PubSec /#53ubFilter /adbe.pkcs7.s5 /V 4 /R 4 /Length 128"
    . " /#43F << /Default#43ryptFilter << /#43FM /AESV2 /#41uthEvent /DocOpen /#4Cength 16"
    . " /#52ecipients [<{$recipientHex}>] >> >>"
    . " /#53tmF /Default#43ryptFilter /#53trF /Default#43ryptFilter /#45FF /Default#43ryptFilter"
    . " /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($report['encryption'] ?? null) ? $report['encryption'] : [];
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$recipientReview = is_array($permission['public_key_recipient_review'] ?? null)
    ? $permission['public_key_recipient_review']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected escaped crypt-filter encrypted fixture text to stay blocked.');
}

if (($encryption['declared_crypt_filter_names'] ?? []) !== ['DefaultCryptFilter']
    || ($encryption['selected_crypt_filter_names'] ?? []) !== ['DefaultCryptFilter']
    || ($encryption['crypt_filter_dictionary_selected_filter_names'] ?? []) !== ['DefaultCryptFilter']
) {
    throw new RuntimeException('Expected escaped crypt-filter names to appear in the encryption preflight summary.');
}

if (($recipientReview['selected_recipient_count'] ?? null) !== 1
    || ($permission['selected_public_key_recipient_count'] ?? null) !== 1
) {
    throw new RuntimeException('Expected the escaped crypt-filter recipient envelope to be selected for public-key review.');
}

if (!is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $recipient)
    || str_contains($encoded, $recipientHex)
) {
    throw new RuntimeException('Expected encrypted text and recipient bytes to stay redacted.');
}

echo '<!-- markerpdf-encrypted-escaped-crypt-filter-names-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-escaped-crypt-filter-names-currentbase',
    'native_boundary' => 'Escaped crypt-filter names are surfaced for WordPress preflight while encrypted text and recipient bytes remain blocked',
    'encrypted_text_blocked' => $plainText === '',
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'declared_crypt_filter_names' => $encryption['declared_crypt_filter_names'] ?? [],
    'selected_crypt_filter_names' => $encryption['selected_crypt_filter_names'] ?? [],
    'dictionary_selected_filter_names' => $encryption['crypt_filter_dictionary_selected_filter_names'] ?? [],
    'selected_public_key_recipient_count' => $permission['selected_public_key_recipient_count'] ?? null,
    'raw_recipient_material_exposed' => false,
    'executes_cms_parse' => $report['executes_cms_parse'] ?? false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Crypt-Filter Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The native preflight records escaped crypt-filter names and selected public-key recipient counts as review metadata without decrypting or exposing recipient bytes.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-escaped-crypt-filter-names ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'selected_public_key_recipient_count' => $permission['selected_public_key_recipient_count'] ?? null,
    ],
    'encryption' => [
        'filter' => $encryption['filter'] ?? null,
        'subfilter' => $encryption['subfilter'] ?? null,
        'declared_crypt_filter_names' => $encryption['declared_crypt_filter_names'] ?? [],
        'selected_crypt_filter_names' => $encryption['selected_crypt_filter_names'] ?? [],
        'crypt_filter_dictionary_declared_filter_names' => $encryption['crypt_filter_dictionary_declared_filter_names'] ?? [],
        'crypt_filter_dictionary_selected_filter_names' => $encryption['crypt_filter_dictionary_selected_filter_names'] ?? [],
    ],
    'security' => [
        'executes_cms_parse' => $report['executes_cms_parse'] ?? false,
        'executes_decryption' => $report['executes_decryption'] ?? null,
        'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
        'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
