<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$aes256PermissionPdf = static function (?string $lengthOperand, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} AES256 permission text leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $lengthEntry = $lengthOperand === null ? '' : " /Length {$lengthOperand}";

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6{$lengthEntry}"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

$assertMissingAes256LengthPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $ownerEncryptionKey,
    string $userEncryptionKey,
    string $permissionDigest,
    array $expectedReviewReasons = [
        'encrypted_document',
        'encrypted_text_extraction_blocked',
        'standard_security_handler_parameters_malformed',
    ],
    array $expectedParameterViolations = ['missing_standard_security_handler_key_length'],
    string $expectedKeyLengthStatus = 'missing_standard_security_handler_key_length_review',
    bool $expectedKeyLengthPresent = false,
    array $expectedMalformedParameterNames = []
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $metadataEncryption = $metadata['encryption'];
    $metadataParameterReview = $metadataEncryption['standard_security_handler_parameter_review'];
    $reviewEncryption = $report['encryption'];
    $permission = $report['permission_preflight'];
    $handler = $report['permission_handler_review'];
    $parameterReview = $permission['standard_security_handler_parameter_review'];
    $material = $permission['standard_authentication_material_review'];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same($expectedReviewReasons, $report['review_reasons']);

    $t->same('Standard', $metadataEncryption['filter']);
    $t->same(5, $metadataEncryption['version']);
    $t->same(6, $metadataEncryption['revision']);
    $t->same('standard_handler_revision_6', $metadataEncryption['revision_label']);
    $t->same(false, array_key_exists('key_length_bits', $metadataEncryption));
    $t->same('standard_security_handler_parameter_review', $metadataParameterReview['source']);
    $t->same($expectedKeyLengthStatus, $metadataParameterReview['key_length_status']);
    $t->same(false, $metadataParameterReview['parameters_well_formed']);
    $t->same($expectedParameterViolations, $metadataParameterReview['violations']);
    $t->same($expectedMalformedParameterNames, $metadataParameterReview['malformed_parameter_names']);
    $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
    $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

    $t->same('standard_security_handler_malformed_parameters', $permission['source']);
    $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
    $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
    $t->same('malformed_standard_security_handler_parameters_review', $permission['standard_security_handler_parameter_status']);
    $t->same($expectedParameterViolations, $permission['standard_security_handler_parameter_violations']);
    $t->same($expectedMalformedParameterNames, $permission['standard_security_handler_malformed_parameter_names']);
    $t->same(count($expectedMalformedParameterNames), $permission['standard_security_handler_malformed_parameter_count']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same(false, $permission['permission_word_well_formed']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(null, $permission['accessibility_extract_allowed']);
    $t->same([], $permission['allowed']);
    $t->same([], $permission['denied']);
    $t->same([], $permission['permission_bits']);
    $t->same(0, $permission['permission_bit_review_count']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);

    $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
    $t->same(true, $parameterReview['version_present']);
    $t->same(true, $parameterReview['revision_present']);
    $t->same($expectedKeyLengthPresent, $parameterReview['key_length_present']);
    $t->same(5, $parameterReview['version']);
    $t->same(6, $parameterReview['revision']);
    $t->same(null, $parameterReview['key_length_bits']);
    $t->same(true, $parameterReview['version_supported']);
    $t->same(true, $parameterReview['revision_supported']);
    $t->same(true, $parameterReview['version_revision_compatible']);
    $t->same(false, $parameterReview['key_length_valid']);
    $t->same($expectedKeyLengthStatus, $parameterReview['key_length_status']);
    $t->same(256, $parameterReview['minimum_key_length_bits']);
    $t->same(256, $parameterReview['maximum_key_length_bits']);
    $t->same(true, $parameterReview['permission_word_present']);
    $t->same(false, $parameterReview['parameters_well_formed']);
    $t->same($expectedParameterViolations, $parameterReview['violations']);
    $t->same($expectedMalformedParameterNames, $parameterReview['malformed_parameter_names']);
    $t->same(count($expectedMalformedParameterNames), $parameterReview['malformed_parameter_count']);
    $t->same(false, $parameterReview['executes_decryption']);
    $t->same(false, $parameterReview['executes_permission_enforcement']);

    $t->same('permission_handler_review', $handler['source']);
    $t->same('malformed_standard_security_handler_parameters_review', $handler['status']);
    $t->same(false, $handler['standard_security_handler_parameters_well_formed']);
    $t->same($expectedParameterViolations, $handler['standard_security_handler_parameter_violations']);
    $t->same($expectedMalformedParameterNames, $handler['standard_security_handler_malformed_parameter_names']);
    $t->same(count($expectedMalformedParameterNames), $handler['standard_security_handler_malformed_parameter_count']);
    $t->same(false, $handler['permission_word_well_formed']);
    $t->same(false, $handler['executes_permission_enforcement']);

    $t->same($parameterReview, $reviewEncryption['standard_security_handler_parameter_review']);
    $t->same('malformed_standard_security_handler_parameters_review', $reviewEncryption['standard_security_handler_parameter_status']);
    $t->same(false, $reviewEncryption['standard_security_handler_parameters_well_formed']);
    $t->same(false, $reviewEncryption['permission_bits_reliable']);
    $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
    $t->same([], $reviewEncryption['permission_bits']);

    $t->same('standard_authentication_material_ready_for_password_attempt', $material['status']);
    $t->same(true, $material['ready_for_password_attempt']);
    $t->same(true, $material['permission_digest_present']);
    $t->same(false, $material['password_validation_performed']);
    $t->same(false, $material['permissions_authenticated']);
    $t->same(false, $material['executes_decryption']);
    $t->same(false, $material['executes_permission_enforcement']);

    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded)
        && !str_contains($encoded, $content)
        && !str_contains($encoded, $ownerValidation)
        && !str_contains($encoded, $userValidation)
        && !str_contains($encoded, $ownerEncryptionKey)
        && !str_contains($encoded, $userEncryptionKey)
        && !str_contains($encoded, $permissionDigest)
        && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
};

return [
    'fails closed when Standard AES256 security handler omits top-level key length' => static function (
        TestRunner $t
    ) use ($aes256PermissionPdf, $assertMissingAes256LengthPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $aes256PermissionPdf(null, 'MISSING_LENGTH');

        $assertMissingAes256LengthPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest
        );
    },
    'fails closed when Standard AES256 security handler length operand is unresolved' => static function (
        TestRunner $t
    ) use ($aes256PermissionPdf, $assertMissingAes256LengthPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $aes256PermissionPdf('99 0 R', 'UNRESOLVED_LENGTH');

        $assertMissingAes256LengthPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest,
            [
                'encrypted_document',
                'encrypted_text_extraction_blocked',
                'standard_security_handler_parameters_malformed',
                'standard_security_handler_parameter_operands_malformed',
            ],
            ['malformed_standard_security_handler_parameter_entries'],
            'malformed_standard_security_handler_key_length_operand_review',
            true,
            ['Length']
        );
    },
];
