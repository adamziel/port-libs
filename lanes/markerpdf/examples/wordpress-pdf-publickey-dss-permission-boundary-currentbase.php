<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress public-key DSS permission boundary text should not import) Tj ET';
$documentRecipient = 'WP_BOUNDARY_DOCUMENT_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$embeddedFileRecipient = 'WP_BOUNDARY_EMBEDDED_FILE_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$legacyRecipient = 'WP_BOUNDARY_LEGACY_S5_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$unusedRecipient = 'WP_BOUNDARY_UNUSED_RECIPIENT_BYTES_SHOULD_NOT_LEAK';
$signaturePayload = 'WP_BOUNDARY_DSS_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';
$vriKey = strtoupper(hash('sha1', $signaturePayload));
$certPayload = 'WP_BOUNDARY_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'WP_BOUNDARY_DSS_OCSP_BYTES_SHOULD_NOT_LEAK';
$documentRecipientHex = strtoupper(bin2hex($documentRecipient));
$embeddedFileRecipientHex = strtoupper(bin2hex($embeddedFileRecipient));
$legacyRecipientHex = strtoupper(bin2hex($legacyRecipient));
$unusedRecipientHex = strtoupper(bin2hex($unusedRecipient));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> /DSS 60 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.wpPublicKeyBoundary) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (article.title) /V (Encrypted WordPress title) >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (WordPress Boundary Reviewer) /M (D:20260602224641Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [31 0 R 33 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Type /SigRef /TransformMethod /FieldMDP /Data 5 0 R /TransformParams 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /TransformParams /V /1.2 /Action /Include /Fields [9 0 R] >>\nendobj\n"
    . "33 0 obj\n<< /Type /SigRef /TransformMethod /UR3 /Data 1 0 R /TransformParams 34 0 R >>\nendobj\n"
    . "34 0 obj\n<< /Type /TransformParams /V /2.2 /Form [/FillIn /Export] /EF [/Create] /Msg (WordPress public-key DSS rights review only) >>\nendobj\n"
    . "50 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128 /Recipients [<{$legacyRecipientHex}>]"
    . " /CF <<"
    . " /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$documentRecipientHex}>] >>"
    . " /EmbeddedFiles << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 /Recipients [<{$embeddedFileRecipientHex}>] >>"
    . " /UnusedRights << /CFM /V2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$unusedRecipientHex}>] >>"
    . " >> /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EFF /EmbeddedFiles /EncryptMetadata true >>\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /{$vriKey} 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602224641Z) >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 50 0 R >>\n%%EOF";

$gapStart = strpos($pdf, $signatureContentsToken);
if ($gapStart === false) {
    throw new RuntimeException('Unable to locate signature contents token in WordPress public-key boundary fixture.');
}

$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
]);

$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$boundary = is_array($preflight['public_key_dss_permission_boundary_review'] ?? null)
    ? $preflight['public_key_dss_permission_boundary_review']
    : [];
$encoded = json_encode($preflight, JSON_UNESCAPED_SLASHES);
$rawSecurityMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $documentRecipient)
        || str_contains($encoded, $embeddedFileRecipient)
        || str_contains($encoded, $legacyRecipient)
        || str_contains($encoded, $unusedRecipient)
        || str_contains($encoded, $signaturePayload)
        || str_contains($encoded, $documentRecipientHex)
        || str_contains($encoded, $embeddedFileRecipientHex)
        || str_contains($encoded, $legacyRecipientHex)
        || str_contains($encoded, $unusedRecipientHex)
        || str_contains($encoded, strtoupper(bin2hex($signaturePayload)))
        || str_contains($encoded, $certPayload)
        || str_contains($encoded, $ocspPayload)
    );

echo '<!-- markerpdf-publickey-dss-permission-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-publickey-dss-permission-boundary-currentbase',
    'native_boundary' => 'public-key recipient permissions, DSS validation material, and signature permission transforms stay review-only before WordPress import',
    'text_blocked' => $plainText === '',
    'boundary_present' => $boundary['present'] ?? null,
    'boundary_decision' => $boundary['boundary_decision'] ?? null,
    'permission_policy' => $boundary['permission_policy'] ?? null,
    'selected_recipient_count' => $boundary['selected_recipient_count'] ?? null,
    'unselected_recipient_count' => $boundary['unselected_recipient_count'] ?? null,
    'selected_recipient_filters' => $boundary['selected_crypt_filter_recipient_filter_names'] ?? [],
    'unselected_recipient_filters' => $boundary['unselected_crypt_filter_recipient_filter_names'] ?? [],
    'dss_signature_match_count' => $boundary['document_security_store_signature_match_count'] ?? null,
    'signature_permission_methods' => $boundary['signature_permission_transform_methods'] ?? [],
    'raw_security_material_exposed' => $rawSecurityMaterialExposed,
    'executes_cms_parse' => $boundary['executes_cms_parse'] ?? null,
    'executes_decryption' => $boundary['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $boundary['executes_permission_enforcement'] ?? null,
    'executes_rights_enforcement' => $boundary['executes_rights_enforcement'] ?? null,
    'executes_signature_validation' => $boundary['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $boundary['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $boundary['executes_trust_chain_validation'] ?? null,
    'executes_external_pdf_tools' => $boundary['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Public-Key DSS Permission Boundary</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted public-key recipient permissions, DSS validation streams, and signature usage-right declarations were detected as security-review metadata. WordPress text import remains blocked until native CMS recipient decoding and decryption are available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:publickey-dss-permission-boundary ' . htmlspecialchars(json_encode([
    'boundary_decision' => $boundary['boundary_decision'] ?? null,
    'content_boundary' => $boundary['content_extraction_boundary'] ?? null,
    'selected_recipient_filters' => $boundary['selected_crypt_filter_recipient_filter_names'] ?? [],
    'unselected_recipient_filters' => $boundary['unselected_crypt_filter_recipient_filter_names'] ?? [],
    'dss_signature_match_count' => $boundary['document_security_store_signature_match_count'] ?? null,
    'signature_permission_methods' => $boundary['signature_permission_transform_methods'] ?? [],
    'raw_security_material_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
