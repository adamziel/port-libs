<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$unsignedStandardPermissionPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Unsigned permission encrypted text leak) Tj ET';
    $ownerKey = 'UNSIGNED_PERMISSION_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'UNSIGNED_PERMISSION_USER_KEY_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
        . " /U <" . strtoupper(bin2hex($userKey)) . ">"
        . " /P 4294967252 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'normalizes unsigned Standard permission words before encrypted preflight decisions' => static function (
        TestRunner $t
    ) use ($unsignedStandardPermissionPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $unsignedStandardPermissionPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permissions = $metadata['encryption']['standard_permissions'];
        $review = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same(4294967252, $permissions['declared']);
        $t->same('unsigned_decimal', $permissions['declared_form']);
        $t->same(true, $permissions['normalized_from_unsigned_decimal']);
        $t->same(-44, $permissions['signed']);
        $t->same(4294967252, $permissions['unsigned']);
        $t->same('FFFFFFD4', $permissions['hex']);
        $t->same([
            'print',
            'copy_or_extract',
            'fill_form_fields',
            'extract_for_accessibility',
            'assemble_document',
            'high_quality_print',
        ], $permissions['allowed']);
        $t->same(['modify_contents', 'add_or_modify_annotations'], $permissions['denied']);
        $t->same(true, $permissions['reserved_bits_valid']);
        $t->same('well_formed_standard_permissions', $permissions['permission_word_status']);

        $t->same('FFFFFFD4', $review['permission_hex']);
        $t->same(-44, $review['permission_signed']);
        $t->same(4294967252, $review['permission_unsigned']);
        $t->same('unsigned_decimal', $review['permission_word_form']);
        $t->same(true, $review['permission_normalized_from_unsigned_decimal']);
        $t->same(true, $review['copy_or_extract_allowed']);
        $t->same(true, $review['accessibility_extract_allowed']);
        $t->same(true, $review['permission_word_well_formed']);
        $t->same(true, $review['permission_bits_reliable']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(-44, $permission['permission_signed']);
        $t->same(4294967252, $permission['permission_unsigned']);
        $t->same('unsigned_decimal', $permission['permission_word_form']);
        $t->same(true, $permission['permission_normalized_from_unsigned_decimal']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['permission_bits_reliable']);

        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(-44, $handler['permission_signed']);
        $t->same(4294967252, $handler['permission_unsigned']);
        $t->same('unsigned_decimal', $handler['permission_word_form']);
        $t->same(true, $handler['permission_normalized_from_unsigned_decimal']);
        $t->same(false, $handler['executes_permission_enforcement']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex($ownerKey)))
            && !str_contains($encoded, strtoupper(bin2hex($userKey))));
    },
    'keeps unsigned-permission encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($unsignedStandardPermissionPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $unsignedStandardPermissionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('', $plainText);
        $t->true(!str_contains($plainText, $content));
        $t->true(!str_contains($plainText, $ownerKey));
        $t->true(!str_contains($plainText, $userKey));
    },
];
