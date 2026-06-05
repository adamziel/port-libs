<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$duplicateStandardParameterPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate Standard parameter encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('L', 32);
    $userValidation = str_repeat('M', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 40 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed when Standard encryption dictionary duplicates security parameters' => static function (
        TestRunner $t
    ) use ($duplicateStandardParameterPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicateStandardParameterPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $declarationReview = $parameterReview['parameter_declaration_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'standard_security_handler_parameters_malformed',
        ], $report['review_reasons']);

        $t->same('Standard', $encryption['filter']);
        $t->same(4, $encryption['version']);
        $t->same(4, $encryption['revision']);
        $t->same(128, $encryption['key_length_bits']);
        $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
        $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));

        $t->same('standard_security_handler_parameter_declaration_review', $declarationReview['source']);
        $t->same(['Length'], $declarationReview['duplicate_parameter_names']);
        $t->same(['Filter' => 1, 'V' => 1, 'R' => 1, 'Length' => 2], $declarationReview['parameter_entry_counts']);
        $t->same('duplicate_standard_security_handler_parameter_entries_review', $declarationReview['status']);
        $t->same(true, $declarationReview['fail_closed']);
        $t->same(false, $declarationReview['executes_decryption']);
        $t->same(false, $declarationReview['executes_permission_enforcement']);

        $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
        $t->same(false, $parameterReview['parameters_well_formed']);
        $t->same(['duplicate_standard_security_handler_parameter_entries'], $parameterReview['violations']);
        $t->same(['Length'], $parameterReview['duplicate_parameter_names']);
        $t->same(1, $parameterReview['duplicate_parameter_count']);
        $t->same(true, $parameterReview['version_revision_compatible']);
        $t->same(true, $parameterReview['key_length_valid']);
        $t->same('standard_security_handler_key_length_supported', $parameterReview['key_length_status']);

        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same(['duplicate_standard_security_handler_parameter_entries'], $permission['standard_security_handler_parameter_violations']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);

        $t->same('malformed_standard_security_handler_parameters_review', $handler['status']);
        $t->same(false, $handler['standard_security_handler_parameters_well_formed']);
        $t->same(['duplicate_standard_security_handler_parameter_entries'], $handler['standard_security_handler_parameter_violations']);
        $t->same(['Length'], $handler['standard_security_handler_duplicate_parameter_names']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same($parameterReview, $reviewEncryption['standard_security_handler_parameter_review']);
        $t->same('malformed_standard_security_handler_parameters_review', $reviewEncryption['standard_security_handler_parameter_status']);
        $t->same(false, $reviewEncryption['standard_security_handler_parameters_well_formed']);
        $t->same(['duplicate_standard_security_handler_parameter_entries'], $reviewEncryption['standard_security_handler_parameter_violations']);
        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);

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
