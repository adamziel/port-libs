<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate CF dictionary encrypted text leak) Tj ET';
$ownerKey = str_repeat('D', 32);
$userKey = str_repeat('F', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearStreams << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
    . " /CF << /ClearStreams << /CFM /Identity /AuthEvent /DocOpen >> /ClearStrings << /CFM /Identity /AuthEvent /DocOpen >> /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >> >>"
    . " /StmF /ClearStreams /StrF /ClearStrings /EFF /ClearEmbedded >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$permission = is_array($report['permission_preflight'] ?? null) ? $report['permission_preflight'] : [];
$review = is_array($report['crypt_filter_content_review'] ?? null) ? $report['crypt_filter_content_review'] : [];
$dictionaryReview = is_array($metadata['encryption']['crypt_filter_dictionary_declaration_review'] ?? null)
    ? $metadata['encryption']['crypt_filter_dictionary_declaration_review']
    : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}
if (($permission['content_extraction_boundary'] ?? null) !== 'blocked_by_duplicate_document_crypt_filter_dictionary') {
    throw new RuntimeException('Expected duplicate crypt-filter dictionaries to fail closed before WordPress import.');
}
if (($dictionaryReview['declared_entry_count'] ?? null) !== 2 || ($dictionaryReview['duplicate_entries'] ?? null) !== true) {
    throw new RuntimeException('Expected both /CF dictionaries to be preserved as ambiguous review metadata.');
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

echo '<!-- markerpdf-encrypted-duplicate-crypt-filter-dictionary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-duplicate-crypt-filter-dictionary-currentbase',
    'native_boundary' => 'Standard encrypted duplicate /CF dictionaries fail closed before WordPress import even when a later dictionary selects identity filters',
    'plain_text_blocked' => $plainText === '',
    'import_decision' => $report['import_decision'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'text_content_policy' => $review['text_content_policy'] ?? null,
    'dictionary_declared_entry_count' => $dictionaryReview['declared_entry_count'] ?? null,
    'dictionary_duplicate_entries' => $dictionaryReview['duplicate_entries'] ?? null,
    'dictionary_status' => $dictionaryReview['status'] ?? null,
    'selected_filter_names' => $dictionaryReview['selected_filter_names'] ?? [],
    'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
    'fail_closed_filter_names' => $review['fail_closed_filter_names'] ?? [],
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<h2>Encrypted PDF Duplicate Crypt Filter Dictionary Preflight</h2>\n";
echo "<p>Encrypted PDF text remains blocked. Duplicate /CF dictionaries are preserved as ambiguous security metadata and do not authorize native text import.</p>\n";
echo '<!-- markerpdf:encrypted-duplicate-crypt-filter-dictionary-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $permission['policy'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'native_text_extraction_allowed_now' => $permission['native_text_extraction_allowed_now'] ?? null,
    ],
    'crypt_filter_review' => [
        'text_content_policy' => $review['text_content_policy'] ?? null,
        'embedded_file_payload_policy' => $review['embedded_file_payload_policy'] ?? null,
        'crypt_filter_dictionary_fail_closed' => $review['crypt_filter_dictionary_fail_closed'] ?? null,
        'fail_closed_role_names' => $review['fail_closed_role_names'] ?? [],
        'fail_closed_filter_names' => $review['fail_closed_filter_names'] ?? [],
    ],
    'dictionary_declaration_review' => [
        'declared_entry_count' => $dictionaryReview['declared_entry_count'] ?? null,
        'resolved_dictionary_entry_count' => $dictionaryReview['resolved_dictionary_entry_count'] ?? null,
        'duplicate_entries' => $dictionaryReview['duplicate_entries'] ?? null,
        'status' => $dictionaryReview['status'] ?? null,
        'declared_filter_names' => $dictionaryReview['declared_filter_names'] ?? [],
        'selected_filter_names' => $dictionaryReview['selected_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
