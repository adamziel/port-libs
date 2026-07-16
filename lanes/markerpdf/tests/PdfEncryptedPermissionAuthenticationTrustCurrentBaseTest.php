<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$revisionSixTrustPdf = static function (bool $includePerms, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} permission trust encrypted text leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $perms = $includePerms ? ' /Perms ' . $hex($permissionDigest) : '';

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
        . " /P -44 /EncryptMetadata true{$perms}"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

$legacyTrustPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Legacy permission trust encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('L', 32);
    $userValidation = str_repeat('G', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true"
        . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'marks complete revision six permission bits decoded but unauthenticated before import decisions' => static function (
        TestRunner $t
    ) use ($revisionSixTrustPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixTrustPdf(true, 'COMPLETE_R6');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $trust = $permission['permission_authentication_trust_review'];
        $encryptionTrust = $report['encryption']['permission_authentication_trust_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same(true, $permission['permission_bits_authentication_required']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $permission['permission_authentication_status']);

        $t->same($trust, $encryptionTrust);
        $t->same('standard_permission_authentication_trust_review', $trust['source']);
        $t->same(true, $trust['present']);
        $t->same('Standard', $trust['handler']);
        $t->same(6, $trust['revision']);
        $t->same('standard_handler_revision_6', $trust['revision_label']);
        $t->same(true, $trust['permission_word_decoded']);
        $t->same(true, $trust['syntactic_permission_bits_reliable']);
        $t->same(true, $trust['authentication_required']);
        $t->same(true, $trust['permission_digest_required']);
        $t->same(true, $trust['permission_digest_present']);
        $t->same(16, $trust['permission_digest_bytes']);
        $t->same(16, $trust['permission_digest_expected_bytes']);
        $t->same(true, $trust['permission_digest_length_valid']);
        $t->same('permission_digest_ciphertext_review', $trust['permission_digest_status']);
        $t->same(true, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['password_validation_performed']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['decryption_performed']);
        $t->same(false, $trust['executes_permission_enforcement']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $trust['trust_boundary']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $trust['status']);

        $t->same(false, $report['encryption']['permission_bits_authenticated']);
        $t->same(false, $report['encryption']['authenticated_permission_bits_reliable']);
        $t->same(true, $report['encryption']['permission_bits_authentication_required']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $report['encryption']['permission_authentication_status']);
        $t->same(hash('sha256', $permissionDigest), $permission['standard_authentication_review']['permission_digest']['sha256']);
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
    'marks missing revision six permission digest before trusting decoded permission bits' => static function (
        TestRunner $t
    ) use ($revisionSixTrustPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixTrustPdf(false, 'MISSING_R6_PERMS');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $trust = $permission['permission_authentication_trust_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same(true, $permission['permission_bits_authentication_required']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $permission['permission_authentication_status']);

        $t->same('standard_permission_authentication_trust_review', $trust['source']);
        $t->same(true, $trust['permission_word_decoded']);
        $t->same(true, $trust['syntactic_permission_bits_reliable']);
        $t->same(true, $trust['permission_digest_required']);
        $t->same(false, $trust['permission_digest_present']);
        $t->same(null, $trust['permission_digest_bytes']);
        $t->same(16, $trust['permission_digest_expected_bytes']);
        $t->same(null, $trust['permission_digest_length_valid']);
        $t->same('required_permission_digest_missing', $trust['permission_digest_status']);
        $t->same(false, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $trust['trust_boundary']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $trust['status']);
        $t->same($trust, $report['encryption']['permission_authentication_trust_review']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
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
    'keeps legacy Standard permission bits review-only until password validation exists' => static function (
        TestRunner $t
    ) use ($legacyTrustPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $legacyTrustPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $trust = $permission['permission_authentication_trust_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);
        $t->same(true, $permission['permission_bits_authentication_required']);
        $t->same('permission_bits_decoded_but_password_not_validated', $permission['permission_authentication_status']);

        $t->same('standard_permission_authentication_trust_review', $trust['source']);
        $t->same(true, $trust['present']);
        $t->same(4, $trust['revision']);
        $t->same('standard_handler_revision_4', $trust['revision_label']);
        $t->same(true, $trust['permission_word_decoded']);
        $t->same(true, $trust['syntactic_permission_bits_reliable']);
        $t->same(true, $trust['authentication_required']);
        $t->same(false, $trust['permission_digest_required']);
        $t->same(false, $trust['permission_digest_present']);
        $t->same('permission_digest_absent_for_legacy_revision', $trust['permission_digest_status']);
        $t->same(true, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['password_validation_performed']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $trust['trust_boundary']);
        $t->same('permission_bits_decoded_but_password_not_validated', $trust['status']);
        $t->same($trust, $report['encryption']['permission_authentication_trust_review']);

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
