<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$authMaterialPolicyPdf = static function (string $case) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$case} auth material policy encrypted text leak) Tj ET";
    $ownerValidation = $case === 'LENGTH_MISMATCH' ? str_repeat('O', 47) : str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = $case === 'LENGTH_MISMATCH' ? str_repeat('K', 31) : str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $perms = $case === 'MISSING_PERMS' ? '' : ' /Perms ' . $hex($permissionDigest);

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

$assertNoRawAuthMaterial = static function (
    TestRunner $t,
    array $report,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $ownerEncryptionKey,
    string $userEncryptionKey,
    string $permissionDigest
): void {
    $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

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
        && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        && !str_contains($encoded, strtoupper(bin2hex($userValidation)))
        && !str_contains($encoded, strtoupper(bin2hex($ownerEncryptionKey)))
        && !str_contains($encoded, strtoupper(bin2hex($userEncryptionKey)))
        && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
};

return [
    'summarizes Standard authentication material length mismatches before permission import trust' => static function (
        TestRunner $t
    ) use ($authMaterialPolicyPdf, $assertNoRawAuthMaterial): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] =
            $authMaterialPolicyPdf('LENGTH_MISMATCH');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
        $material = $permission['standard_authentication_material_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same('standard_authentication_material_length_mismatch', $permission['standard_authentication_material_policy'] ?? null);
        $t->same('standard_authentication_material_length_mismatch', $encryption['standard_authentication_material_policy'] ?? null);
        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(['owner_validation', 'user_encryption_key'], $permission['standard_authentication_length_mismatch_required_entries'] ?? null);
        $t->same(['owner_validation', 'user_encryption_key'], $encryption['standard_authentication_length_mismatch_required_entries'] ?? null);
        $t->same([], $permission['standard_authentication_missing_required_entries'] ?? null);
        $t->same([], $permission['standard_authentication_unresolved_required_entries'] ?? null);
        $t->same([], $permission['standard_authentication_duplicate_required_entries'] ?? null);
        $t->same(['authentication_entry_length_mismatch_review', 'authentication_entry_digest_review'], $permission['standard_authentication_required_entry_statuses'] ?? null);
        $t->same('permission_digest_ciphertext_review', $permission['standard_authentication_permission_digest_status'] ?? null);
        $t->same(true, $permission['standard_authentication_permission_digest_length_valid'] ?? null);
        $t->same(false, $permission['standard_authentication_permission_digest_duplicate_entries'] ?? null);
        $t->same('permission_bits_decoded_but_authentication_material_incomplete', $permission['permission_authentication_status']);
        $t->same(false, $permission['permission_bits_authentication_material_usable']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);

        $assertNoRawAuthMaterial(
            $t,
            $report,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest
        );
    },
    'summarizes missing revision six permission digest before permission import trust' => static function (
        TestRunner $t
    ) use ($authMaterialPolicyPdf, $assertNoRawAuthMaterial): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] =
            $authMaterialPolicyPdf('MISSING_PERMS');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
        $material = $permission['standard_authentication_material_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same('standard_permission_digest_missing', $permission['standard_authentication_material_policy'] ?? null);
        $t->same('standard_permission_digest_missing', $encryption['standard_authentication_material_policy'] ?? null);
        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same([], $permission['standard_authentication_missing_required_entries'] ?? null);
        $t->same([], $permission['standard_authentication_unresolved_required_entries'] ?? null);
        $t->same([], $permission['standard_authentication_length_mismatch_required_entries'] ?? null);
        $t->same([], $permission['standard_authentication_duplicate_required_entries'] ?? null);
        $t->same(['authentication_entry_digest_review'], $permission['standard_authentication_required_entry_statuses'] ?? null);
        $t->same('required_permission_digest_missing', $permission['standard_authentication_permission_digest_status'] ?? null);
        $t->same(null, $permission['standard_authentication_permission_digest_length_valid'] ?? null);
        $t->same(false, $permission['standard_authentication_permission_digest_duplicate_entries'] ?? null);
        $t->same('required_permission_digest_missing_before_permission_authentication', $permission['permission_authentication_status']);
        $t->same('required_permission_digest_missing_before_permission_authentication', $permission['permission_bits_trust_blocker']);
        $t->same(false, $permission['permission_bits_authentication_material_usable']);
        $t->same(false, $permission['permission_bits_authenticated']);
        $t->same(false, $permission['authenticated_permission_bits_reliable']);

        $assertNoRawAuthMaterial(
            $t,
            $report,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest
        );
    },
];
