<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$legacyImplicitLengthPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Legacy implicit key length text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 1 /R 2"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'marks legacy Standard V1 omitted Length as implicit 40-bit while trusting permission bits only after decryption' => static function (
        TestRunner $t
    ) use ($legacyImplicitLengthPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $legacyImplicitLengthPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $metadataEncryption = $metadata['encryption'];
        $metadataParameterReview = $metadataEncryption['standard_security_handler_parameter_review'];
        $permission = $report['permission_preflight'];
        $reviewEncryption = $report['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_decryption_required',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(1, $metadataEncryption['version']);
        $t->same(2, $metadataEncryption['revision']);
        $t->same('rc4_40_bit', $metadataEncryption['algorithm']);
        $t->same('standard_handler_revision_2', $metadataEncryption['revision_label']);
        $t->same(40, $metadataEncryption['key_length_bits']);
        $t->same(false, $metadataEncryption['key_length_explicit']);
        $t->same(true, $metadataEncryption['key_length_defaulted']);
        $t->same('standard_security_handler_v1_implicit_40_bit', $metadataEncryption['key_length_source']);

        $t->same('standard_security_handler_parameter_review', $metadataParameterReview['source']);
        $t->same(false, $metadataParameterReview['key_length_present']);
        $t->same(false, $metadataParameterReview['key_length_explicit']);
        $t->same(true, $metadataParameterReview['key_length_defaulted']);
        $t->same('standard_security_handler_v1_implicit_40_bit', $metadataParameterReview['key_length_source']);
        $t->same(true, $metadataParameterReview['key_length_valid']);
        $t->same('standard_security_handler_key_length_implicit_40_bit', $metadataParameterReview['key_length_status']);
        $t->same(40, $metadataParameterReview['minimum_key_length_bits']);
        $t->same(40, $metadataParameterReview['maximum_key_length_bits']);
        $t->same(true, $metadataParameterReview['parameters_well_formed']);
        $t->same([], $metadataParameterReview['violations']);

        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same('standard_security_handler_key_length_implicit_40_bit', $permission['standard_security_handler_key_length_status']);
        $t->same(false, $permission['standard_security_handler_key_length_explicit']);
        $t->same(true, $permission['standard_security_handler_key_length_defaulted']);
        $t->same('standard_security_handler_v1_implicit_40_bit', $permission['standard_security_handler_key_length_source']);
        $t->same([], $permission['standard_security_handler_parameter_violations']);

        $t->same('standard_security_handler_key_length_implicit_40_bit', $reviewEncryption['standard_security_handler_key_length_status']);
        $t->same(false, $reviewEncryption['standard_security_handler_key_length_explicit']);
        $t->same(true, $reviewEncryption['standard_security_handler_key_length_defaulted']);
        $t->same('standard_security_handler_v1_implicit_40_bit', $reviewEncryption['standard_security_handler_key_length_source']);
        $t->same(true, $reviewEncryption['permission_bits_reliable']);
        $t->same(true, $reviewEncryption['copy_or_extract_allowed']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
];
