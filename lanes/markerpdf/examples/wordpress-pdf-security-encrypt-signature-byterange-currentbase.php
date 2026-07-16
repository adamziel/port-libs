<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Encrypted signed cleartext leak) Tj ET';
$signaturePayload = 'ENCRYPTED_VALID_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.encryptedSignature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Encrypted Signature Reviewer) /M (D:20260602190722Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} >>\nendobj\n"
    . "31 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 31 0 R >>\n%%EOF";

$gapStart = strpos($pdf, $signatureContentsToken);
if ($gapStart === false) {
    throw new RuntimeException('Unable to locate signature contents token in encrypted signature smoke fixture.');
}

$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
]);

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$review = $report['encrypted_signature_byte_range_review'] ?? [];
$row = is_array($review['rows'][0] ?? null) ? $review['rows'][0] : [];

echo '<!-- markerpdf-security-encrypt-signature-byterange-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-encrypt-signature-byterange-currentbase',
    'native_boundary' => 'Encrypted signed PDFs keep text extraction blocked while valid signature ByteRange metadata remains review-only',
    'encrypted_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'signature_byte_range_count' => $report['signature_byte_range_count'] ?? null,
    'valid_signature_byte_range_count' => $report['valid_signature_byte_range_count'] ?? null,
    'encrypted_signature_byte_range_review_count' => $report['encrypted_signature_byte_range_review_count'] ?? null,
    'byte_range_status' => $row['byte_range_status'] ?? null,
    'content_extraction_boundary' => $review['content_extraction_boundary'] ?? null,
    'byte_range_does_not_grant_import' => $review['byte_range_does_not_grant_import'] ?? null,
    'raw_signature_contents_exposed' => $review['raw_signature_contents_exposed'] ?? null,
    'raw_key_material_exposed' => $review['raw_key_material_exposed'] ?? null,
    'executes_decryption' => $review['executes_decryption'] ?? null,
    'executes_signature_validation' => $review['executes_signature_validation'] ?? null,
    'executes_signing' => $review['executes_signing'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted Signed PDF Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF content remains blocked from import. The signature byte range is summarized for editorial review only and does not grant native text extraction.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-signature-byte-range ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'] ?? null,
    'text_extraction_policy' => $report['text_extraction_policy'] ?? null,
    'permission_policy' => $report['permission_preflight']['policy'] ?? null,
    'byte_range_statuses' => $review['byte_range_statuses'] ?? [],
    'field_names' => $review['field_names'] ?? [],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
