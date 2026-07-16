<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$aes256WithLegacyCryptFilterPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (AES256 legacy crypt filter encrypted text leak) Tj ET';
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
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " /CF <<"
        . " /LegacyDoc << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
        . " /EmbeddedAes256 << /CFM /AESV3 /AuthEvent /EFOpen /Length 32 >>"
        . " >>"
        . " /StmF /LegacyDoc /StrF /LegacyDoc /EFF /EmbeddedAes256 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

$revisionFourWithAes256CryptFilterPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Revision4 AES256 crypt filter encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('A', 32);
    $userValidation = str_repeat('B', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true"
        . " /CF <<"
        . " /TooNew << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >>"
        . " /DocAesV2 << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
        . " >>"
        . " /StmF /TooNew /StrF /DocAesV2 /EFF /DocAesV2 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed when Standard AES256 document roles select legacy crypt-filter methods' => static function (
        TestRunner $t
    ) use ($aes256WithLegacyCryptFilterPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $aes256WithLegacyCryptFilterPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $parameterReview = $report['encryption']['standard_security_handler_parameter_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_crypt_filter_fail_closed',
            'crypt_filter_text_fail_closed',
        ], $report['review_reasons']);

        $t->same('Standard', $metadata['encryption']['filter']);
        $t->same(5, $metadata['encryption']['version']);
        $t->same(6, $metadata['encryption']['revision']);
        $t->same('standard_security_handler_parameters_well_formed', $parameterReview['status']);
        $t->same(true, $parameterReview['parameters_well_formed']);
        $t->same([], $parameterReview['violations']);
        $t->same('AESV2', $metadata['encryption']['crypt_filters']['LegacyDoc']['method']);
        $t->same('AESV3', $metadata['encryption']['crypt_filters']['EmbeddedAes256']['method']);

        $t->same('crypt_filter_method_generation_mismatch_fail_closed', $review['text_content_policy']);
        $t->same('encrypted_filter_requires_decryption', $review['embedded_file_payload_policy']);
        $t->same(['standard_aes256_requires_aesv3_crypt_filter_review', 'standard_aes256_crypt_filter_method_compatible'], $review['method_generation_statuses']);
        $t->same(['document_streams', 'document_strings'], $review['method_generation_fail_closed_role_names']);
        $t->same(['LegacyDoc'], $review['method_generation_fail_closed_filter_names']);
        $t->same(['document_streams', 'document_strings'], $review['fail_closed_role_names']);
        $t->same(['LegacyDoc'], $review['fail_closed_filter_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('LegacyDoc', $streamRole['filter_name']);
        $t->same('AESV2', $streamRole['method']);
        $t->same(false, $streamRole['method_compatible_with_standard_handler']);
        $t->same('standard_aes256_requires_aesv3_crypt_filter_review', $streamRole['method_generation_status']);
        $t->same(true, $streamRole['method_generation_fail_closed']);
        $t->same('document_strings', $stringRole['role']);
        $t->same('standard_aes256_requires_aesv3_crypt_filter_review', $stringRole['method_generation_status']);
        $t->same(true, $stringRole['method_generation_fail_closed']);
        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('EmbeddedAes256', $embeddedRole['filter_name']);
        $t->same('AESV3', $embeddedRole['method']);
        $t->same(true, $embeddedRole['method_compatible_with_standard_handler']);
        $t->same('standard_aes256_crypt_filter_method_compatible', $embeddedRole['method_generation_status']);
        $t->same(false, $embeddedRole['method_generation_fail_closed']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_incompatible_document_crypt_filter_method', $permission['content_extraction_boundary']);
        $t->same('crypt_filter_method_generation_mismatch_fail_closed', $permission['crypt_filter_text_policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

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
    'fails closed when Standard revision four document roles select AES256 crypt-filter methods' => static function (
        TestRunner $t
    ) use ($revisionFourWithAes256CryptFilterPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $revisionFourWithAes256CryptFilterPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('crypt_filter_method_generation_mismatch_fail_closed', $review['text_content_policy']);
        $t->same(['standard_revision4_disallows_aesv3_crypt_filter_review', 'standard_revision4_crypt_filter_method_compatible'], $review['method_generation_statuses']);
        $t->same(['document_streams'], $review['method_generation_fail_closed_role_names']);
        $t->same(['TooNew'], $review['method_generation_fail_closed_filter_names']);
        $t->same('document_streams', $review['roles'][0]['role']);
        $t->same('AESV3', $review['roles'][0]['method']);
        $t->same(false, $review['roles'][0]['method_compatible_with_standard_handler']);
        $t->same('standard_revision4_disallows_aesv3_crypt_filter_review', $review['roles'][0]['method_generation_status']);
        $t->same(true, $review['roles'][0]['method_generation_fail_closed']);
        $t->same('document_strings', $review['roles'][1]['role']);
        $t->same('AESV2', $review['roles'][1]['method']);
        $t->same(true, $review['roles'][1]['method_compatible_with_standard_handler']);
        $t->same('embedded_file_streams', $review['roles'][2]['role']);
        $t->same(true, $review['roles'][2]['method_compatible_with_standard_handler']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_incompatible_document_crypt_filter_method', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);

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
