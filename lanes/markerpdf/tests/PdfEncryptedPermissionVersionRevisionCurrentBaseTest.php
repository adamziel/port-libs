<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$versionRevisionMismatchPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Version revision mismatch encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 6 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

return [
    'fails closed when Standard security handler version and revision are incompatible' => static function (
        TestRunner $t
    ) use ($versionRevisionMismatchPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $versionRevisionMismatchPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $metadataEncryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $material = $permission['standard_authentication_material_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'standard_security_handler_parameters_malformed',
            'standard_security_handler_version_revision_mismatch',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(4, $metadataEncryption['version']);
        $t->same(6, $metadataEncryption['revision']);
        $t->same('standard_handler_revision_6', $metadataEncryption['revision_label']);
        $t->same('standard_security_handler_parameter_review', $metadataEncryption['standard_security_handler_parameter_review']['source']);
        $t->same(true, $metadataEncryption['standard_security_handler_parameter_review']['version_supported']);
        $t->same(true, $metadataEncryption['standard_security_handler_parameter_review']['revision_supported']);
        $t->same(false, $metadataEncryption['standard_security_handler_parameter_review']['version_revision_compatible']);
        $t->same('standard_security_handler_key_length_supported', $metadataEncryption['standard_security_handler_parameter_review']['key_length_status']);
        $t->same(['standard_security_handler_version_revision_mismatch'], $metadataEncryption['standard_security_handler_parameter_review']['violations']);
        $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
        $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same('malformed_standard_security_handler_parameters_review', $permission['standard_security_handler_parameter_status']);
        $t->same(true, $permission['standard_security_handler_version_supported']);
        $t->same(true, $permission['standard_security_handler_revision_supported']);
        $t->same(false, $permission['standard_security_handler_version_revision_compatible']);
        $t->same('standard_security_handler_key_length_supported', $permission['standard_security_handler_key_length_status']);
        $t->same(['standard_security_handler_version_revision_mismatch'], $permission['standard_security_handler_parameter_violations']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
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
        $t->same(true, $parameterReview['key_length_present']);
        $t->same(true, $parameterReview['version_supported']);
        $t->same(true, $parameterReview['revision_supported']);
        $t->same(false, $parameterReview['version_revision_compatible']);
        $t->same(true, $parameterReview['key_length_valid']);
        $t->same(false, $parameterReview['parameters_well_formed']);
        $t->same(['standard_security_handler_version_revision_mismatch'], $parameterReview['violations']);
        $t->same(false, $parameterReview['executes_decryption']);
        $t->same(false, $parameterReview['executes_permission_enforcement']);

        $t->same('permission_handler_review', $handler['source']);
        $t->same('malformed_standard_security_handler_parameters_review', $handler['status']);
        $t->same(false, $handler['standard_security_handler_parameters_well_formed']);
        $t->same(true, $handler['standard_security_handler_version_supported']);
        $t->same(true, $handler['standard_security_handler_revision_supported']);
        $t->same(false, $handler['standard_security_handler_version_revision_compatible']);
        $t->same('standard_security_handler_key_length_supported', $handler['standard_security_handler_key_length_status']);
        $t->same(['standard_security_handler_version_revision_mismatch'], $handler['standard_security_handler_parameter_violations']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same(false, $reviewEncryption['standard_security_handler_parameters_well_formed']);
        $t->same(true, $reviewEncryption['standard_security_handler_version_supported']);
        $t->same(true, $reviewEncryption['standard_security_handler_revision_supported']);
        $t->same(false, $reviewEncryption['standard_security_handler_version_revision_compatible']);
        $t->same('standard_security_handler_key_length_supported', $reviewEncryption['standard_security_handler_key_length_status']);
        $t->same(['standard_security_handler_version_revision_mismatch'], $reviewEncryption['standard_security_handler_parameter_violations']);
        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
        $t->same([], $reviewEncryption['permission_bits']);

        $t->same('standard_authentication_material_ready_for_password_attempt', $material['status']);
        $t->same(true, $material['ready_for_password_attempt']);
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
    },
    'keeps version-revision mismatch encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($versionRevisionMismatchPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $versionRevisionMismatchPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
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
    },
];
