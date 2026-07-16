<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$recipientOne = 'WORDPRESS_PUBLICKEY_RECIPIENT_ONE_BYTES_SHOULD_NOT_LEAK';
$recipientTwo = 'WORDPRESS_PUBLICKEY_RECIPIENT_TWO_BYTES_SHOULD_NOT_LEAK';
$recipientOneHex = strtoupper(bin2hex($recipientOne));
$recipientTwoHex = strtoupper(bin2hex($recipientTwo));
$content = 'BT /F1 12 Tf 72 720 Td (WordPress public key encrypted text should not import) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients 6 0 R >> >>"
    . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n[<{$recipientOneHex}> 7 0 R]\nendobj\n"
    . "7 0 obj\n<{$recipientTwoHex}>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$recipientReview = $report['permission_preflight']['public_key_recipient_review'] ?? [];
$recipientEncoded = json_encode($recipientReview, JSON_UNESCAPED_SLASHES);
$recipientBytesLeaked = is_string($recipientEncoded)
    && (str_contains($recipientEncoded, $recipientOne) || str_contains($recipientEncoded, $recipientTwo));
$recipientHexLeaked = is_string($recipientEncoded)
    && (str_contains($recipientEncoded, $recipientOneHex) || str_contains($recipientEncoded, $recipientTwoHex));

echo '<!-- markerpdf-publickey-recipient-permission-review-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-publickey-recipient-permission-review-currentbase',
    'native_boundary' => 'Public-key recipient permission envelopes are counted and hashed before WordPress import without CMS parsing or decryption',
    'text_blocked' => $plainText === '',
    'handler' => $report['permission_handler_review']['handler'] ?? null,
    'subfilter' => $report['permission_handler_review']['subfilter'] ?? null,
    'permission_policy' => $report['permission_preflight']['policy'] ?? null,
    'handler_status' => $report['permission_handler_review']['status'] ?? null,
    'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
    'recipient_count' => $recipientReview['recipient_count'] ?? null,
    'recipient_permission_decode_status' => $recipientReview['permission_decode_status'] ?? null,
    'crypt_filter_recipient_filter_names' => $recipientReview['crypt_filter_recipient_filter_names'] ?? [],
    'metadata_recipient_count' => $metadata['encryption']['public_key_recipient_review']['recipient_count'] ?? null,
    'recipient_bytes_exposed' => $recipientBytesLeaked || $recipientHexLeaked,
    'executes_cms_parse' => $recipientReview['executes_cms_parse'] ?? null,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Public-Key Recipient Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Public-key recipient envelopes are review-only metadata. WordPress import blocks encrypted text until native decryption and CMS recipient permission decoding are available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:publickey-recipient-permission-review ' . htmlspecialchars(json_encode([
    'permission_policy' => $report['permission_preflight']['policy'] ?? null,
    'content_boundary' => $report['permission_preflight']['content_extraction_boundary'] ?? null,
    'handler_status' => $report['permission_handler_review']['status'] ?? null,
    'recipient_review' => [
        'source_policy' => $recipientReview['recipient_source_policy'] ?? null,
        'recipient_count' => $recipientReview['recipient_count'] ?? null,
        'permission_decode_status' => $recipientReview['permission_decode_status'] ?? null,
        'recipient_sha256' => $recipientReview['recipient_sha256'] ?? [],
        'crypt_filter_names' => $recipientReview['crypt_filter_recipient_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
