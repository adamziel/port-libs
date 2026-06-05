<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$malformedLegacyStandardPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Malformed Standard auth encrypted text leak) Tj ET';
    $ownerValidation = 'OWNER_SHORT_SHOULD_NOT_LEAK';
    $userValidation = 'USER_SHORT_SHOULD_NOT_LEAK';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$revisionSixPdf = static function (bool $includePerms) use ($hex): array {
    $content = $includePerms
        ? 'BT /F1 12 Tf 72 720 Td (Complete R6 Standard auth encrypted text leak) Tj ET'
        : 'BT /F1 12 Tf 72 720 Td (Missing Perms Standard auth encrypted text leak) Tj ET';
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
        . " /P -44 /EncryptMetadata true"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF{$perms} >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

$duplicatePermissionWordPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate permission encrypted text leak) Tj ET';
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
        . " /P -60 /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'marks malformed legacy Standard authentication material before permission import review' => static function (
        TestRunner $t
    ) use ($malformedLegacyStandardPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $malformedLegacyStandardPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $material = $permission['standard_authentication_material_review'];
        $encryptionMaterial = $report['encryption']['standard_authentication_material_review'];
        $auth = $permission['standard_authentication_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(false, $report['encryption']['standard_authentication_ready_for_password_attempt']);

        $t->same($material, $encryptionMaterial);
        $t->same('standard_authentication_material_review', $material['source']);
        $t->same(true, $material['present']);
        $t->same(true, $material['standard_handler']);
        $t->same(4, $material['revision']);
        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(2, $material['required_entry_count']);
        $t->same(2, $material['present_required_entry_count']);
        $t->same(['owner_validation', 'user_validation'], $material['present_required_entries']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['unresolved_required_entries']);
        $t->same(['owner_validation', 'user_validation'], $material['length_mismatch_required_entries']);
        $t->same(['authentication_entry_length_mismatch_review'], $material['required_entry_statuses']);
        $t->same(false, $material['permission_digest_required']);
        $t->same(false, $material['permission_digest_present']);
        $t->same('permission_digest_absent_for_legacy_revision', $material['permission_digest_status']);
        $t->same(false, $material['password_validation_performed']);
        $t->same(false, $material['permissions_authenticated']);
        $t->same(false, $material['executes_decryption']);
        $t->same(false, $material['executes_permission_enforcement']);

        $t->same('authentication_entry_length_mismatch_review', $auth['entries']['owner_validation']['status']);
        $t->same('authentication_entry_length_mismatch_review', $auth['entries']['user_validation']['status']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
    'marks missing revision six permission digest as not ready for password authentication' => static function (
        TestRunner $t
    ) use ($revisionSixPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixPdf(false);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $material = $permission['standard_authentication_material_review'];
        $auth = $permission['standard_authentication_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(6, $material['revision']);
        $t->same('standard_handler_revision_6', $material['revision_label']);
        $t->same(4, $material['required_entry_count']);
        $t->same(4, $material['present_required_entry_count']);
        $t->same(['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'], $material['present_required_entries']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['unresolved_required_entries']);
        $t->same([], $material['length_mismatch_required_entries']);
        $t->same(['authentication_entry_digest_review'], $material['required_entry_statuses']);
        $t->same(true, $material['permission_digest_required']);
        $t->same(false, $material['permission_digest_present']);
        $t->same(null, $material['permission_digest_bytes']);
        $t->same(16, $material['permission_digest_expected_bytes']);
        $t->same(null, $material['permission_digest_length_valid']);
        $t->same('required_permission_digest_missing', $material['permission_digest_status']);
        $t->same('required_permission_digest_missing', $auth['permission_digest']['status']);
        $t->same(false, $auth['permissions_authenticated']);
        $t->same(false, $material['raw_owner_user_keys_exposed']);
        $t->same(false, $material['raw_file_encryption_keys_exposed']);
        $t->same(false, $report['executes_decryption']);
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
    'keeps complete revision six Standard authentication material review-only and ready for password attempts' => static function (
        TestRunner $t
    ) use ($revisionSixPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $revisionSixPdf(true);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $material = $permission['standard_authentication_material_review'];
        $auth = $permission['standard_authentication_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same(true, $report['encryption']['standard_authentication_ready_for_password_attempt']);
        $t->same('standard_authentication_material_ready_for_password_attempt', $material['status']);
        $t->same(true, $material['ready_for_password_attempt']);
        $t->same(4, $material['required_entry_count']);
        $t->same(4, $material['present_required_entry_count']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['unresolved_required_entries']);
        $t->same([], $material['length_mismatch_required_entries']);
        $t->same(['authentication_entry_digest_review'], $material['required_entry_statuses']);
        $t->same(true, $material['permission_digest_required']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(16, $material['permission_digest_bytes']);
        $t->same(16, $material['permission_digest_expected_bytes']);
        $t->same(true, $material['permission_digest_length_valid']);
        $t->same('permission_digest_ciphertext_review', $material['permission_digest_status']);
        $t->same(hash('sha256', $permissionDigest), $auth['permission_digest']['sha256']);
        $t->same(false, $material['password_validation_performed']);
        $t->same(false, $material['permissions_authenticated']);
        $t->same(false, $material['decryption_performed']);
        $t->same(false, $material['executes_permission_enforcement']);
        $t->same(false, $report['executes_decryption']);
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
    'fails closed when Standard encryption dictionary declares duplicate permission words' => static function (
        TestRunner $t
    ) use ($duplicatePermissionWordPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicatePermissionWordPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $permission['permission_handler_review'];
        $declaration = $permission['standard_permission_word_review'];
        $encryptionDeclaration = $report['encryption']['standard_permission_word_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same('duplicate_standard_permission_entries_review', $handler['permission_word_status']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $report['encryption']['copy_or_extract_allowed']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same($declaration, $encryptionDeclaration);
        $t->same('standard_permission_word_declaration_review', $declaration['source']);
        $t->same(2, $declaration['declared_entry_count']);
        $t->same(true, $declaration['duplicate_permission_entries']);
        $t->same(true, $declaration['permission_word_ambiguous']);
        $t->same('duplicate_standard_permission_entries_review', $declaration['status']);
        $t->same([4294967236, 4294967252], $declaration['unsigned_values']);
        $t->same(['FFFFFFC4', 'FFFFFFD4'], $declaration['hex_values']);
        $t->same(['copy_or_extract'], $declaration['conflicting_permission_names']);
        $t->same(['denied_by_permission_bit', 'allowed_by_permission_bit'], $declaration['conflicting_statuses']['copy_or_extract']);
        $t->same('denied_by_permission_bit', $declaration['entries'][0]['permission_bits_by_name']['copy_or_extract']['status']);
        $t->same('allowed_by_permission_bit', $declaration['entries'][1]['permission_bits_by_name']['copy_or_extract']['status']);

        $t->true(in_array('permission_word_duplicate_entries', $report['review_reasons'], true));
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
