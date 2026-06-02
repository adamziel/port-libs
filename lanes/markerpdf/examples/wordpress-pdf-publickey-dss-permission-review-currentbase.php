<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress public-key DSS encrypted text should not import) Tj ET';
$documentRecipient = 'WORDPRESS_DOCUMENT_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$embeddedFileRecipient = 'WORDPRESS_EMBEDDED_FILE_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$unusedRecipient = 'WORDPRESS_UNUSED_CRYPT_FILTER_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$certPayload = 'WORDPRESS_PUBLICKEY_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'WORDPRESS_PUBLICKEY_DSS_OCSP_BYTES_SHOULD_NOT_LEAK';
$documentRecipientHex = strtoupper(bin2hex($documentRecipient));
$embeddedFileRecipientHex = strtoupper(bin2hex($embeddedFileRecipient));
$unusedRecipientHex = strtoupper(bin2hex($unusedRecipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /DSS 60 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
    . " /CF <<"
    . " /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$documentRecipientHex}>] >>"
    . " /EmbeddedFiles << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 /Recipients 8 0 R >>"
    . " /UnusedRights << /CFM /V2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$unusedRecipientHex}>] >>"
    . " >> /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EFF /EmbeddedFiles /EncryptMetadata true >>\nendobj\n"
    . "8 0 obj\n[<{$embeddedFileRecipientHex}>]\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$recipientReview = $preflight['permission_preflight']['public_key_recipient_review'] ?? [];
$selection = is_array($recipientReview['crypt_filter_selection'] ?? null) ? $recipientReview['crypt_filter_selection'] : [];
$dss = is_array($preflight['document_security_store'] ?? null) ? $preflight['document_security_store'] : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $documentRecipient)
        || str_contains($encoded, $embeddedFileRecipient)
        || str_contains($encoded, $unusedRecipient)
        || str_contains($encoded, $documentRecipientHex)
        || str_contains($encoded, $embeddedFileRecipientHex)
        || str_contains($encoded, $unusedRecipientHex)
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
    );

echo '<!-- markerpdf-publickey-dss-permission-review-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-publickey-dss-permission-review-currentbase',
    'native_boundary' => 'selected public-key crypt-filter recipient permissions and DSS validation streams are review metadata before WordPress import',
    'text_blocked' => $plainText === '',
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
    'recipient_count' => $recipientReview['recipient_count'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'selected_recipient_filters' => $selection['selected_recipient_filter_names'] ?? [],
    'unselected_recipient_filters' => $selection['unselected_recipient_filter_names'] ?? [],
    'dss_present' => $dss['present'] ?? null,
    'dss_validation_stream_count' => $dss['total_validation_stream_count'] ?? null,
    'raw_security_material_exposed' => $rawSecurityMaterialExposed,
    'executes_cms_parse' => $recipientReview['executes_cms_parse'] ?? null,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_revocation_check' => $preflight['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $preflight['executes_trust_chain_validation'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Public-Key DSS Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Public-key recipient permissions and DSS validation streams are kept as review-only metadata. WordPress import blocks encrypted text until native decryption and CMS permission decoding are available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:publickey-dss-permission-review ' . htmlspecialchars(json_encode([
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'content_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    'recipient_review' => [
        'recipient_count' => $recipientReview['recipient_count'] ?? null,
        'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
        'selected_filters' => $selection['selected_recipient_filter_names'] ?? [],
        'unselected_filters' => $selection['unselected_recipient_filter_names'] ?? [],
    ],
    'dss_review' => [
        'present' => $dss['present'] ?? null,
        'validation_stream_count' => $dss['total_validation_stream_count'] ?? null,
    ],
    'raw_security_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
