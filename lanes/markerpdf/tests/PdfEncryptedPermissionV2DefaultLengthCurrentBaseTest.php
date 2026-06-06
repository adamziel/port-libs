<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$v2DefaultLengthPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (V2 default key length encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 2 /R 3"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'records omitted Standard V2 Length as default 40-bit permission preflight metadata' => static function (
        TestRunner $t
    ) use ($v2DefaultLengthPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $v2DefaultLengthPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncryption = $metadata['encryption'];
        $permission = $report['permission_preflight'];
        $reviewEncryption = $report['encryption'];
        $handler = $report['permission_handler_review'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_decryption_required',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(2, $metadataEncryption['version']);
        $t->same(3, $metadataEncryption['revision']);
        $t->same('rc4_variable_length', $metadataEncryption['algorithm']);
        $t->same('standard_handler_revision_3', $metadataEncryption['revision_label']);
        $t->same(40, $metadataEncryption['key_length_bits']);
        $t->same(false, $metadataEncryption['key_length_explicit']);
        $t->same(true, $metadataEncryption['key_length_defaulted']);
        $t->same('standard_security_handler_v2_default_40_bit', $metadataEncryption['key_length_source']);
        $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
        $t->same(true, $metadataEncryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

        $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
        $t->same(false, $parameterReview['key_length_present']);
        $t->same(false, $parameterReview['key_length_explicit']);
        $t->same(true, $parameterReview['key_length_defaulted']);
        $t->same('standard_security_handler_v2_default_40_bit', $parameterReview['key_length_source']);
        $t->same(40, $parameterReview['key_length_bits']);
        $t->same(true, $parameterReview['key_length_valid']);
        $t->same('standard_security_handler_key_length_default_40_bit', $parameterReview['key_length_status']);
        $t->same(40, $parameterReview['minimum_key_length_bits']);
        $t->same(128, $parameterReview['maximum_key_length_bits']);
        $t->same(true, $parameterReview['parameters_well_formed']);
        $t->same([], $parameterReview['violations']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same('standard_security_handler_key_length_default_40_bit', $permission['standard_security_handler_key_length_status']);
        $t->same(false, $permission['standard_security_handler_key_length_explicit']);
        $t->same(true, $permission['standard_security_handler_key_length_defaulted']);
        $t->same('standard_security_handler_v2_default_40_bit', $permission['standard_security_handler_key_length_source']);
        $t->same([], $permission['standard_security_handler_parameter_violations']);

        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(true, $handler['permission_word_well_formed']);
        $t->same('standard_security_handler_key_length_default_40_bit', $handler['standard_security_handler_key_length_status']);
        $t->same(true, $handler['standard_security_handler_key_length_defaulted']);
        $t->same('standard_security_handler_v2_default_40_bit', $handler['standard_security_handler_key_length_source']);

        $t->same('standard_security_handler_key_length_default_40_bit', $reviewEncryption['standard_security_handler_key_length_status']);
        $t->same(true, $reviewEncryption['standard_security_handler_key_length_defaulted']);
        $t->same('standard_security_handler_v2_default_40_bit', $reviewEncryption['standard_security_handler_key_length_source']);
        $t->same(true, $reviewEncryption['permission_bits_reliable']);
        $t->same(true, $reviewEncryption['copy_or_extract_allowed']);

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
    'keeps Standard V2 default-length encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($v2DefaultLengthPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $v2DefaultLengthPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, str_contains($plainText, $content));
        $t->same(false, str_contains($plainText, $ownerValidation));
        $t->same(false, str_contains($plainText, $userValidation));
        $t->same(false, is_string($encoded) && str_contains($encoded, strtoupper(bin2hex($ownerValidation))));
        $t->same(false, is_string($encoded) && str_contains($encoded, strtoupper(bin2hex($userValidation))));
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
    },
];
