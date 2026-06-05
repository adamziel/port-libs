<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$standardMissingPermissionWordPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Missing permission word encrypted text leak) Tj ET';
    $ownerValidation = 'MISSING_P_OWNER_VALIDATION_SHOULD_NOT_LEAK';
    $userValidation = 'MISSING_P_USER_VALIDATION_SHOULD_NOT_LEAK';
    $ownerBytes = str_pad($ownerValidation, 32, 'O');
    $userBytes = str_pad($userValidation, 32, 'U');

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerBytes)) . ">"
        . " /U <" . strtoupper(bin2hex($userBytes)) . ">"
        . " /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerBytes, $userBytes];
};

return [
    'fails closed when Standard encryption dictionary omits required permission word' => static function (
        TestRunner $t
    ) use ($standardMissingPermissionWordPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerBytes, $userBytes] = $standardMissingPermissionWordPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'standard_security_handler_parameters_malformed'], $report['review_reasons']);

        $t->same('Standard', $metadata['encryption']['filter']);
        $t->same(false, isset($metadata['encryption']['standard_permissions']));
        $t->same(false, isset($metadata['encryption']['standard_permission_word_review']));
        $t->same('malformed_standard_security_handler_parameters_review', $encryption['standard_security_handler_parameter_status']);
        $t->same(false, $encryption['standard_security_handler_parameters_well_formed']);
        $t->same(['missing_standard_permission_word'], $encryption['standard_security_handler_parameter_violations']);
        $t->same(null, $encryption['permission_hex']);
        $t->same(null, $encryption['copy_or_extract_allowed']);
        $t->same(false, $encryption['permission_bits_reliable']);
        $t->same([], $encryption['permission_bits']);

        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_permissions_declared']);
        $t->same(false, $permission['standard_permission_word_declared']);
        $t->same(false, $permission['standard_permission_bits_decoded']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);
        $t->same(0, $permission['permission_bit_review_count']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('permission_handler_review', $handler['source']);
        $t->same('Standard', $handler['handler']);
        $t->same(true, $handler['standard_handler']);
        $t->same(false, $handler['permissions_declared']);
        $t->same(false, $handler['standard_permissions_declared']);
        $t->same(false, $handler['handler_supported_for_native_permission_review']);
        $t->same('malformed_standard_security_handler_parameters_review', $handler['status']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same(false, $handler['standard_security_handler_parameters_well_formed']);
        $t->same(['missing_standard_permission_word'], $handler['standard_security_handler_parameter_violations']);
        $t->same([], $handler['permission_bits']);
        $t->same(0, $handler['permission_bit_review_count']);

        $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
        $t->same(4, $parameterReview['version']);
        $t->same(4, $parameterReview['revision']);
        $t->same(true, $parameterReview['version_revision_compatible']);
        $t->same('standard_security_handler_key_length_supported', $parameterReview['key_length_status']);
        $t->same(false, $parameterReview['permission_word_present']);
        $t->same(false, $parameterReview['parameters_well_formed']);
        $t->same(['missing_standard_permission_word'], $parameterReview['violations']);
        $t->same(false, $parameterReview['executes_decryption']);
        $t->same(false, $parameterReview['executes_permission_enforcement']);
    },
    'keeps missing-permission-word encrypted content and key material out of import JSON' => static function (
        TestRunner $t
    ) use ($standardMissingPermissionWordPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerBytes, $userBytes] = $standardMissingPermissionWordPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->same(false, $report['raw_owner_user_keys_exposed']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerBytes)
            && !str_contains($encoded, $userBytes)
            && !str_contains($encoded, strtoupper(bin2hex($ownerBytes)))
            && !str_contains($encoded, strtoupper(bin2hex($userBytes))));
    },
];
