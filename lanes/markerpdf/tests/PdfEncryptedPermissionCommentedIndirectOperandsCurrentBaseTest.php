<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$commentedIndirectPermissionPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Commented indirect permission encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('C', 32);
    $userValidation = str_repeat('I', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 7 0 R /R 8 0 R /Length 9 0 R"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P 18 0 R /EncryptMetadata 19 0 R"
        . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 25 0 R >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "7 0 obj\n% version comment before scalar\n4 % version comment after scalar\nendobj\n"
        . "8 0 obj\n% revision comment before scalar\n4 % revision comment after scalar\nendobj\n"
        . "9 0 obj\n% key-length comment before scalar\n128 % key-length comment after scalar\nendobj\n"
        . "18 0 obj\n% permission comment before scalar\n-44 % permission comment after scalar\nendobj\n"
        . "19 0 obj\n% EncryptMetadata comment before scalar\ntrue % EncryptMetadata comment after scalar\nendobj\n"
        . "25 0 obj\n% crypt-filter length comment before scalar\n16 % crypt-filter length comment after scalar\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'strips comments around indirect Standard permission integers before encrypted preflight' => static function (
        TestRunner $t
    ) use ($commentedIndirectPermissionPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $commentedIndirectPermissionPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $review = $report['crypt_filter_content_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same('Standard', $encryption['filter']);
        $t->same(4, $encryption['version']);
        $t->same('security_handler_crypt_filters', $encryption['algorithm']);
        $t->same(4, $encryption['revision']);
        $t->same('standard_handler_revision_4', $encryption['revision_label']);
        $t->same(128, $encryption['key_length_bits']);
        $t->same(true, $encryption['key_length_explicit']);
        $t->same(false, $encryption['key_length_defaulted']);
        $t->same(true, $encryption['encrypt_metadata']);
        $t->same(true, $encryption['encrypt_metadata_explicit']);
        $t->same(true, $encryption['encrypt_metadata_trusted']);
        $t->same('well_formed_encrypt_metadata_boolean', $encryption['encrypt_metadata_status']);
        $t->same('StdCF', $encryption['stream_filter']);
        $t->same('StdCF', $encryption['string_filter']);
        $t->same('StdCF', $encryption['embedded_file_filter']);
        $t->same(true, $encryption['embedded_file_filter_defaulted_from_stream_filter']);
        $t->same('AESV2', $encryption['crypt_filters']['StdCF']['method']);
        $t->same(16, $encryption['crypt_filters']['StdCF']['key_length_bytes']);
        $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
        $t->same(true, $encryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));

        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
        $t->same(true, $parameterReview['parameters_well_formed']);
        $t->same([], $parameterReview['violations']);
        $t->same(true, $parameterReview['version_supported']);
        $t->same(true, $parameterReview['revision_supported']);
        $t->same(true, $parameterReview['version_revision_compatible']);
        $t->same(true, $parameterReview['key_length_valid']);
        $t->same('standard_security_handler_key_length_supported', $parameterReview['key_length_status']);

        $permissionReview = $permission['standard_permission_word_review'];
        $t->same('standard_permission_word_declaration_review', $permissionReview['source']);
        $t->same(1, $permissionReview['declared_entry_count']);
        $t->same(1, $permissionReview['integer_entry_count']);
        $t->same(false, $permissionReview['duplicate_permission_entries']);
        $t->same(false, $permissionReview['permission_word_ambiguous']);
        $t->same('well_formed_standard_permissions', $permissionReview['status']);
        $t->same(['well_formed_standard_permissions'], $permissionReview['entry_statuses']);
        $t->same([4294967252], $permissionReview['unsigned_values']);
        $t->same(['FFFFFFD4'], $permissionReview['hex_values']);
        $t->same('token', $permissionReview['entries'][0]['operand_shape']);
        $t->same(true, $permissionReview['entries'][0]['resolved']);
        $t->same(true, $permissionReview['entries'][0]['integer']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['permission_word_well_formed']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(true, $handler['standard_security_handler_parameters_well_formed']);
        $t->same(true, $handler['permission_word_well_formed']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same('review_only_encrypted_document_boundary', $review['text_content_policy']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['encrypted_role_names']);
        $t->same(['StdCF'], $review['encrypted_filter_names']);
        $t->same(['encrypted_crypt_filter'], $review['role_statuses']);
        $t->same(16, $review['roles'][0]['key_length_bytes']);
        $t->same('crypt_filter_key_length_supported', $review['roles'][0]['key_length_status']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
];
