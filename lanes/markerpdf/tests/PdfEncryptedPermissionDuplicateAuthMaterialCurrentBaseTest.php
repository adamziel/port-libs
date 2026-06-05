<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$revisionSixDuplicateAuthPdf = static function (string $duplicateKind) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$duplicateKind} duplicate auth material encrypted text leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $duplicateOwnerValidation = str_repeat('D', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $duplicatePermissionDigest = str_repeat('Q', 16);

    $ownerEntries = ' /O ' . $hex($ownerValidation);
    if ($duplicateKind === 'OWNER') {
        $ownerEntries .= ' /O ' . $hex($duplicateOwnerValidation);
    }

    $permsEntries = ' /Perms ' . $hex($permissionDigest);
    if ($duplicateKind === 'PERMS') {
        $permsEntries .= ' /Perms ' . $hex($duplicatePermissionDigest);
    }

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
        . $ownerEntries
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true"
        . $permsEntries
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [
        $pdf,
        $content,
        $ownerValidation,
        $duplicateOwnerValidation,
        $userValidation,
        $ownerEncryptionKey,
        $userEncryptionKey,
        $permissionDigest,
        $duplicatePermissionDigest,
    ];
};

return [
    'marks duplicate Standard owner authentication entries as not ready for password attempts' => static function (
        TestRunner $t
    ) use ($revisionSixDuplicateAuthPdf): void {
        [
            $pdf,
            $content,
            $ownerValidation,
            $duplicateOwnerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest,
            $duplicatePermissionDigest,
        ] = $revisionSixDuplicateAuthPdf('OWNER');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $ownerEntry = $auth['entries']['owner_validation'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same('authentication_entry_duplicate_entries_review', $ownerEntry['status']);
        $t->same('authentication_entry_digest_review', $ownerEntry['selected_entry_status']);
        $t->same(2, $ownerEntry['declared_entry_count']);
        $t->same(true, $ownerEntry['duplicate_entries']);
        $t->same(1, $ownerEntry['selected_entry_index']);
        $t->same(['authentication_entry_digest_review'], $ownerEntry['entry_statuses']);
        $t->same(48, $ownerEntry['bytes']);
        $t->same(hash('sha256', $duplicateOwnerValidation), $ownerEntry['sha256']);
        $t->same(false, $ownerEntry['raw_bytes_exposed']);

        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(['owner_validation'], $material['duplicate_required_entries']);
        $t->same(1, $material['duplicate_required_entry_count']);
        $t->same(['authentication_entry_duplicate_entries_review', 'authentication_entry_digest_review'], $material['required_entry_statuses']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['unresolved_required_entries']);
        $t->same([], $material['length_mismatch_required_entries']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(false, $material['permission_digest_duplicate_entries']);
        $t->same('permission_digest_ciphertext_review', $material['permission_digest_status']);

        $t->same('permission_bits_decoded_but_authentication_material_incomplete', $permission['permission_authentication_status']);
        $t->same(false, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $trust['trust_boundary']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $duplicateOwnerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, $duplicatePermissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($duplicateOwnerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
    'marks duplicate revision six permission digest entries as not ready for permission trust' => static function (
        TestRunner $t
    ) use ($revisionSixDuplicateAuthPdf): void {
        [
            $pdf,
            $content,
            $ownerValidation,
            $duplicateOwnerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permissionDigest,
            $duplicatePermissionDigest,
        ] = $revisionSixDuplicateAuthPdf('PERMS');
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $digest = $auth['permission_digest'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same('permission_digest_duplicate_entries_review', $digest['status']);
        $t->same('permission_digest_ciphertext_review', $digest['selected_entry_status']);
        $t->same(2, $digest['declared_entry_count']);
        $t->same(true, $digest['duplicate_entries']);
        $t->same(1, $digest['selected_entry_index']);
        $t->same(['permission_digest_ciphertext_review'], $digest['entry_statuses']);
        $t->same(16, $digest['bytes']);
        $t->same(hash('sha256', $duplicatePermissionDigest), $digest['sha256']);
        $t->same(false, $digest['raw_bytes_exposed']);

        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same([], $material['duplicate_required_entries']);
        $t->same(0, $material['duplicate_required_entry_count']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(true, $material['permission_digest_duplicate_entries']);
        $t->same(2, $material['permission_digest_declared_entry_count']);
        $t->same(1, $material['permission_digest_selected_entry_index']);
        $t->same(['permission_digest_ciphertext_review'], $material['permission_digest_entry_statuses']);
        $t->same('permission_digest_duplicate_entries_review', $material['permission_digest_status']);
        $t->same(true, $material['permission_digest_length_valid']);

        $t->same('permission_digest_malformed_before_permission_authentication', $permission['permission_authentication_status']);
        $t->same('permission_digest_malformed_before_permission_authentication', $trust['status']);
        $t->same(false, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $duplicateOwnerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, $duplicatePermissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
            && !str_contains($encoded, strtoupper(bin2hex($duplicatePermissionDigest))));
    },
];
