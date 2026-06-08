<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$legacyStrayPermsPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Legacy stray Perms encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);
    $permissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /Perms " . $hex($permissionDigest)
        . " /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $permissionDigest];
};

return [
    'marks stray legacy Standard Perms as unexpected review metadata without authenticating permissions' => static function (
        TestRunner $t
    ) use ($legacyStrayPermsPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $permissionDigest] = $legacyStrayPermsPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncryption = $metadata['encryption'];
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $digest = $auth['permission_digest'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $reviewEncryption = $report['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_decryption_required',
            'legacy_permission_digest_ignored',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(4, $metadataEncryption['revision']);
        $t->same('standard_handler_revision_4', $metadataEncryption['revision_label']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('standard_permissions_validation_ciphertext', $digest['source']);
        $t->same(true, $digest['present']);
        $t->same(false, $digest['applicable_for_revision']);
        $t->same(true, $digest['unexpected_for_revision']);
        $t->same(true, $digest['ignored_for_permission_authentication']);
        $t->same(16, $digest['bytes']);
        $t->same(null, $digest['expected_bytes']);
        $t->same(null, $digest['length_valid']);
        $t->same('unexpected_permission_digest_for_legacy_revision_review', $digest['status']);
        $t->same('permission_digest_ciphertext_review', $digest['selected_entry_status']);
        $t->same(false, $digest['raw_bytes_exposed']);

        $t->same('standard_authentication_material_review', $material['source']);
        $t->same(true, $material['present']);
        $t->same(false, $material['permission_digest_required']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(false, $material['permission_digest_applicable_for_revision']);
        $t->same(true, $material['permission_digest_unexpected_for_revision']);
        $t->same(true, $material['permission_digest_ignored_for_permission_authentication']);
        $t->same(16, $material['permission_digest_bytes']);
        $t->same(null, $material['permission_digest_expected_bytes']);
        $t->same(null, $material['permission_digest_length_valid']);
        $t->same('unexpected_permission_digest_for_legacy_revision_review', $material['permission_digest_status']);
        $t->same(true, $material['ready_for_password_attempt']);
        $t->same(false, $material['password_validation_performed']);
        $t->same(false, $material['permissions_authenticated']);

        $t->same('standard_permission_authentication_trust_review', $trust['source']);
        $t->same(true, $trust['authentication_required']);
        $t->same(false, $trust['permission_digest_required']);
        $t->same(true, $trust['permission_digest_present']);
        $t->same(false, $trust['permission_digest_applicable_for_revision']);
        $t->same(true, $trust['permission_digest_unexpected_for_revision']);
        $t->same(true, $trust['permission_digest_ignored_for_permission_authentication']);
        $t->same('unexpected_permission_digest_for_legacy_revision_review', $trust['permission_digest_status']);
        $t->same('permission_bits_decoded_but_password_not_validated', $trust['status']);
        $t->same('password_validation_not_performed', $trust['permission_trust_blocker']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);

        $t->same(true, $permission['standard_permission_digest_unexpected_for_revision']);
        $t->same(true, $permission['standard_permission_digest_ignored_for_permission_authentication']);
        $t->same(true, $reviewEncryption['standard_permission_digest_unexpected_for_revision']);
        $t->same(true, $reviewEncryption['standard_permission_digest_ignored_for_permission_authentication']);
        $t->same(true, $reviewEncryption['perms_hash_present']);
        $t->same(true, $reviewEncryption['perms_unexpected_for_revision']);
        $t->same($digest, $metadataEncryption['standard_authentication_review']['permission_digest']);
        $t->same($material, $reviewEncryption['standard_authentication_material_review']);
        $t->same($trust, $reviewEncryption['permission_authentication_trust_review']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($userValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
    'keeps stray legacy Perms ciphertext out of visible WordPress text and import JSON' => static function (
        TestRunner $t
    ) use ($legacyStrayPermsPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $permissionDigest] = $legacyStrayPermsPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, str_contains($plainText, $content));
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
];
