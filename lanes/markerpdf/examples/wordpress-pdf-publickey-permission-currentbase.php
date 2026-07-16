<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$recipientOne = 'WORDPRESS_LEGACY_PUBLICKEY_RECIPIENT_ONE_BYTES_SHOULD_NOT_LEAK';
$recipientTwo = 'WORDPRESS_LEGACY_PUBLICKEY_RECIPIENT_TWO_BYTES_SHOULD_NOT_LEAK';
$recipientOneHex = strtoupper(bin2hex($recipientOne));
$recipientTwoHex = strtoupper(bin2hex($recipientTwo));
$content = 'BT /F1 12 Tf 72 720 Td (WordPress legacy public-key encrypted text should not import) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s4 /V 2 /Length 128 /Recipients [<{$recipientOneHex}> 6 0 R] /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n<{$recipientTwoHex}>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$recipientReview = $preflight['permission_preflight']['public_key_recipient_review'] ?? [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);
$rawRecipientMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $recipientOne)
        || str_contains($encoded, $recipientTwo)
        || str_contains($encoded, $recipientOneHex)
        || str_contains($encoded, $recipientTwoHex)
    );

echo '<!-- markerpdf-publickey-permission-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-publickey-permission-currentbase',
    'native_boundary' => 'legacy public-key encryption dictionary recipients are selected permission envelopes before WordPress import',
    'text_blocked' => $plainText === '',
    'handler' => $preflight['permission_handler_review']['handler'] ?? null,
    'subfilter' => $preflight['permission_handler_review']['subfilter'] ?? null,
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'handler_status' => $preflight['permission_handler_review']['status'] ?? null,
    'recipient_source_policy' => $recipientReview['recipient_source_policy'] ?? null,
    'selected_recipient_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
    'recipient_count' => $recipientReview['recipient_count'] ?? null,
    'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
    'top_level_recipients_selected' => $recipientReview['top_level_recipients_selected'] ?? null,
    'metadata_selected_recipient_count' => $metadata['encryption']['public_key_recipient_review']['selected_recipient_count'] ?? null,
    'raw_recipient_material_exposed' => $rawRecipientMaterialExposed,
    'executes_cms_parse' => $recipientReview['executes_cms_parse'] ?? null,
    'executes_decryption' => $preflight['executes_decryption'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Public-Key Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Legacy public-key recipient permission envelopes are review-only metadata. WordPress import blocks encrypted text until native decryption and CMS recipient permission decoding are available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:publickey-permission-currentbase ' . htmlspecialchars(json_encode([
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'content_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    'recipient_review' => [
        'source_policy' => $recipientReview['recipient_source_policy'] ?? null,
        'selected_source_policy' => $recipientReview['selected_recipient_source_policy'] ?? null,
        'recipient_count' => $recipientReview['recipient_count'] ?? null,
        'selected_recipient_count' => $recipientReview['selected_recipient_count'] ?? null,
        'selected_sources' => $recipientReview['selected_recipient_sources'] ?? [],
        'recipient_sha256' => $recipientReview['recipient_sha256'] ?? [],
        'selected_recipient_sha256' => $recipientReview['selected_recipient_sha256'] ?? [],
    ],
    'raw_recipient_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
