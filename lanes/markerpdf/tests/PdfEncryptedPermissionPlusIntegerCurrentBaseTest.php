<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$standardPlusSignedPermissionPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Plus signed permission encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V +4 /R +4 /Length +128"
        . " /O <" . strtoupper(bin2hex($ownerValidation)) . ">"
        . " /U <" . strtoupper(bin2hex($userValidation)) . ">"
        . " /P +4294967252 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'normalizes plus-signed Standard encryption integers before permission preflight' => static function (
        TestRunner $t
    ) use ($standardPlusSignedPermissionPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $standardPlusSignedPermissionPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $parameterReview = $encryption['standard_security_handler_parameter_review'];
        $declaration = $permission['standard_permission_word_review'];
        $entry = $declaration['entries'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same('Standard', $encryption['filter']);
        $t->same(4, $encryption['version']);
        $t->same(4, $encryption['revision']);
        $t->same('standard_handler_revision_4', $encryption['revision_label']);
        $t->same(128, $encryption['key_length_bits']);
        $t->same('standard_security_handler_parameters_well_formed', $parameterReview['status']);
        $t->same(true, $parameterReview['parameters_well_formed']);
        $t->same([], $parameterReview['violations']);

        $t->same(4294967252, $encryption['standard_permissions']['declared']);
        $t->same('unsigned_decimal', $encryption['standard_permissions']['declared_form']);
        $t->same(true, $encryption['standard_permissions']['normalized_from_unsigned_decimal']);
        $t->same(-44, $encryption['standard_permissions']['signed']);
        $t->same(4294967252, $encryption['standard_permissions']['unsigned']);
        $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
        $t->same(true, $encryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(-44, $permission['permission_signed']);
        $t->same(4294967252, $permission['permission_unsigned']);
        $t->same('unsigned_decimal', $permission['permission_word_form']);
        $t->same(true, $permission['permission_normalized_from_unsigned_decimal']);
        $t->same(true, $permission['permission_word_range_valid']);
        $t->same(true, $permission['permission_word_well_formed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(true, $handler['permission_word_well_formed']);
        $t->same('standard_permission_word_declaration_review', $declaration['source']);
        $t->same(1, $declaration['declared_entry_count']);
        $t->same(1, $declaration['integer_entry_count']);
        $t->same(false, $declaration['duplicate_permission_entries']);
        $t->same(false, $declaration['permission_word_ambiguous']);
        $t->same('well_formed_standard_permissions', $declaration['status']);
        $t->same(['well_formed_standard_permissions'], $declaration['entry_statuses']);
        $t->same([4294967252], $declaration['unsigned_values']);
        $t->same(['FFFFFFD4'], $declaration['hex_values']);
        $t->same(true, $entry['integer'] ?? null);
        $t->same(4294967252, $entry['declared'] ?? null);
        $t->same('unsigned_decimal', $entry['declared_form'] ?? null);
        $t->same('well_formed_standard_permissions', $entry['status'] ?? null);

        $t->same($declaration, $reviewEncryption['standard_permission_word_review']);
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
