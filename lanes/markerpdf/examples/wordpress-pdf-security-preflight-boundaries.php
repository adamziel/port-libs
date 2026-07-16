<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Encrypted cleartext leak) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Sig /T (approval.signature) /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /M (D:20260602091100Z) /ByteRange [0 12 9999 10] /Contents <010203> >>\nendobj\n"
    . "31 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 31 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

echo '<!-- markerpdf-pdf-security-preflight-boundaries-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-security-preflight-boundaries',
    'native_boundary' => 'encryption permissions and signature byte ranges summarized before WordPress text import without decryption or signature validation',
    'import_decision' => $report['import_decision'],
    'review_reasons' => $report['review_reasons'],
    'encrypted_text_blocked' => $plainText === '',
    'permission_hex' => $report['encryption']['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $report['encryption']['copy_or_extract_allowed'] ?? null,
    'invalid_signature_byte_range_count' => $report['invalid_signature_byte_range_count'],
    'raw_owner_user_keys_exposed' => $report['raw_owner_user_keys_exposed'],
    'executes_decryption' => $report['executes_decryption'],
    'executes_signature_validation' => $report['executes_signature_validation'],
    'executes_signing' => $report['executes_signing'],
    'executes_python_or_models' => $report['executes_python_or_models'],
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>PDF Security Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF content is blocked from native text import. Security metadata is available for editorial review without exposing owner/user keys or signature contents.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:security-preflight ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'],
    'text_extraction_policy' => $report['text_extraction_policy'],
    'permission_hex' => $report['encryption']['permission_hex'] ?? null,
    'denied_permissions' => $report['encryption']['denied'] ?? [],
    'signature_byte_range_status' => $report['signatures'][0]['byte_range']['status'] ?? null,
    'blocked_operations' => $report['blocked_operations'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
