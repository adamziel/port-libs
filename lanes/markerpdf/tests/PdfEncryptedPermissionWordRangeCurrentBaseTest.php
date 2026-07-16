<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$standardEncryptedPermissionWordPdf = static function (string $permissionWord, string $label): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} out-of-range encrypted text leak) Tj ET";
    $ownerKey = "{$label}_OWNER_KEY_SHOULD_NOT_LEAK";
    $userKey = "{$label}_USER_KEY_SHOULD_NOT_LEAK";
    $ownerBytes = str_pad($ownerKey, 32, 'O');
    $userBytes = str_pad($userKey, 32, 'U');

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
        . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
        . " /P {$permissionWord} /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes];
};

$assertOutOfRangePermissionReview = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerKey,
    string $userKey,
    string $ownerBytes,
    string $userBytes,
    int $declared
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $permissions = $metadata['encryption']['standard_permissions'];
    $permission = $report['permission_preflight'];
    $handler = $report['permission_handler_review'];
    $review = $permissions['standard_permission_word_review'] ?? ($metadata['encryption']['standard_permission_word_review'] ?? []);
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'permission_word_out_of_range'], $report['review_reasons']);

    $t->same($declared, $permissions['declared']);
    $t->same('out_of_range_decimal', $permissions['declared_form']);
    $t->same(false, $permissions['permission_word_range_valid']);
    $t->same('permission_word_out_of_range_review', $permissions['permission_word_range_status']);
    $t->same('permission_word_out_of_range_review', $permissions['permission_word_status']);
    $t->same(null, $permissions['signed']);
    $t->same(null, $permissions['unsigned']);
    $t->same(null, $permissions['hex']);
    $t->same([], $permissions['allowed']);
    $t->same([], $permissions['denied']);
    $t->same([], $permissions['permission_bits']);
    $t->same(0, $permissions['permission_bit_review_count']);
    $t->same([], $permissions['permission_bit_statuses']);
    $t->same(false, $permissions['reserved_bits_valid']);
    $t->same(false, $permissions['reserved_bits']['word_range_valid']);
    $t->same('permission_word_out_of_32bit_range', $permissions['reserved_bits']['violations'][0] ?? null);

    $t->same('standard_security_handler_malformed_permissions', $permission['source']);
    $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
    $t->same(null, $permission['permission_hex']);
    $t->same(null, $permission['permission_signed']);
    $t->same(null, $permission['permission_unsigned']);
    $t->same(false, $permission['permission_word_range_valid']);
    $t->same('permission_word_out_of_range_review', $permission['permission_word_range_status']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same(false, $permission['permission_word_well_formed']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);

    $t->same('permission_handler_review', $handler['source']);
    $t->same('Standard', $handler['handler']);
    $t->same(true, $handler['standard_handler']);
    $t->same(false, $handler['permission_word_range_valid']);
    $t->same('permission_word_out_of_range_review', $handler['permission_word_range_status']);
    $t->same('permission_word_out_of_range_review', $handler['status']);
    $t->same(false, $handler['permission_word_well_formed']);
    $t->same([], $handler['permission_bits']);
    $t->same(0, $handler['permission_bit_review_count']);
    $t->same(false, $handler['executes_decryption']);
    $t->same(false, $handler['executes_permission_enforcement']);

    $t->same('standard_permission_word_declaration_review', $review['source']);
    $t->same(1, $review['declared_entry_count']);
    $t->same(1, $review['integer_entry_count']);
    $t->same(true, $review['permission_word_ambiguous']);
    $t->same('malformed_standard_permission_word_review', $review['status']);
    $t->same(['permission_word_out_of_range_review'], $review['entry_statuses']);
    $t->same([], $review['unsigned_values']);
    $t->same([], $review['hex_values']);
    $t->same($declared, $review['entries'][0]['declared']);
    $t->same('permission_word_out_of_range_review', $review['entries'][0]['status']);

    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded)
        && !str_contains($encoded, $content)
        && !str_contains($encoded, $ownerKey)
        && !str_contains($encoded, $userKey)
        && !str_contains($encoded, $ownerBytes)
        && !str_contains($encoded, $userBytes)
        && !str_contains($encoded, strtoupper(bin2hex($ownerBytes)))
        && !str_contains($encoded, strtoupper(bin2hex($userBytes))));
};

return [
    'flags positive Standard permission words outside the unsigned 32-bit boundary' => static function (
        TestRunner $t
    ) use ($standardEncryptedPermissionWordPdf, $assertOutOfRangePermissionReview): void {
        [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes] = $standardEncryptedPermissionWordPdf('4294967296', 'POSITIVE_RANGE');

        $assertOutOfRangePermissionReview($t, $pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes, 4294967296);
    },
    'flags negative Standard permission words below the signed 32-bit boundary' => static function (
        TestRunner $t
    ) use ($standardEncryptedPermissionWordPdf, $assertOutOfRangePermissionReview): void {
        [$pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes] = $standardEncryptedPermissionWordPdf('-2147483649', 'NEGATIVE_RANGE');

        $assertOutOfRangePermissionReview($t, $pdf, $content, $ownerKey, $userKey, $ownerBytes, $userBytes, -2147483649);
    },
];
