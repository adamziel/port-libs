<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Invalid crypt filter length encrypted text leak) Tj ET';
$ownerKey = str_repeat('L', 32);
$userKey = str_repeat('U', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF <<"
    . " /ShortAesStreams << /CFM /AESV2 /AuthEvent /DocOpen /Length 4 >>"
    . " /LongV2Strings << /CFM /V2 /AuthEvent /DocOpen /Length 17 >>"
    . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen /Length 0 >>"
    . " >>"
    . " /StmF /ShortAesStreams /StrF /LongV2Strings /EFF /ClearEmbedded >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$review = is_array($report['crypt_filter_content_review'] ?? null)
    ? $report['crypt_filter_content_review']
    : [];
$permission = is_array($report['permission_preflight'] ?? null)
    ? $report['permission_preflight']
    : [];
$encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}
if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_by_invalid_document_crypt_filter_key_length') {
    throw new RuntimeException('Expected invalid crypt-filter lengths to fail closed before WordPress import.');
}
if (($review['key_length_invalid_filter_names'] ?? []) !== ['ShortAesStreams', 'LongV2Strings']) {
    throw new RuntimeException('Expected both malformed document-content crypt filters to be reviewed.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $content)
    || str_contains($encoded, $ownerKey)
    || str_contains($encoded, $userKey)
    || str_contains($encoded, strtoupper(bin2hex($ownerKey)))
    || str_contains($encoded, strtoupper(bin2hex($userKey)))
) {
    throw new RuntimeException('Expected encrypted payload and authentication bytes to stay out of review output.');
}

echo '<!-- markerpdf-encrypted-crypt-filter-length-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-crypt-filter-length-currentbase',
    'native_boundary' => 'Standard encrypted crypt-filter key lengths are reviewed before WordPress import without decrypting payloads',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'text_content_policy' => $review['text_content_policy'] ?? null,
    'key_length_statuses' => $review['key_length_statuses'] ?? [],
    'key_length_invalid_filter_names' => $review['key_length_invalid_filter_names'] ?? [],
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . " -->\n";

echo "<h2>Encrypted PDF Crypt Filter Length Preflight</h2>\n";
echo "<p>Encrypted PDF text remains blocked. Malformed crypt-filter key lengths are preserved as review metadata and do not authorize immediate text import.</p>\n";
echo '<!-- markerpdf:encrypted-crypt-filter-length-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    ],
    'crypt_filter_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
        'key_length_invalid_filter_names' => $review['key_length_invalid_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . " -->\n";
