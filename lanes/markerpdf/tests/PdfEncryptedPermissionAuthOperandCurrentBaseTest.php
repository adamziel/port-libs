<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$malformedAuthenticationOperandPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Malformed auth operand encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidationName = 'UserCredentialShouldNotAuthorize';
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
        . " /O [" . $hex($ownerValidation) . "]"
        . " /U /{$userValidationName}"
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidationName, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

return [
    'classifies malformed Standard authentication operands before permission trust review' => static function (
        TestRunner $t
    ) use ($malformedAuthenticationOperandPdf): void {
        [$pdf, $content, $ownerValidation, $userValidationName, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $malformedAuthenticationOperandPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $ownerEntry = $auth['entries']['owner_validation'];
        $userEntry = $auth['entries']['user_validation'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same('array', $ownerEntry['operand_shape'] ?? null);
        $t->same('authentication_entry_composite_operand_review', $ownerEntry['status']);
        $t->same('authentication_entry_composite_operand_review', $ownerEntry['selected_entry_status']);
        $t->same(['array'], $ownerEntry['entry_operand_shapes']);
        $t->same(['authentication_entry_composite_operand_review'], $ownerEntry['entry_statuses']);
        $t->same(false, $ownerEntry['bytes_resolved']);
        $t->same(null, $ownerEntry['bytes']);
        $t->same(null, $ownerEntry['sha256']);

        $t->same('name', $userEntry['operand_shape'] ?? null);
        $t->same('authentication_entry_non_string_operand_review', $userEntry['status']);
        $t->same('authentication_entry_non_string_operand_review', $userEntry['selected_entry_status']);
        $t->same(['name'], $userEntry['entry_operand_shapes']);
        $t->same(['authentication_entry_non_string_operand_review'], $userEntry['entry_statuses']);
        $t->same(false, $userEntry['bytes_resolved']);
        $t->same(null, $userEntry['bytes']);
        $t->same(null, $userEntry['sha256']);

        $t->same('authentication_entry_digest_review', $auth['entries']['owner_encryption_key']['status']);
        $t->same('authentication_entry_digest_review', $auth['entries']['user_encryption_key']['status']);
        $t->same('permission_digest_ciphertext_review', $auth['permission_digest']['status']);
        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'], $material['present_required_entries']);
        $t->same(['owner_validation', 'user_validation'], $material['unresolved_required_entries']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['length_mismatch_required_entries']);
        $t->same([], $material['duplicate_required_entries']);
        $t->same([
            'authentication_entry_composite_operand_review',
            'authentication_entry_non_string_operand_review',
            'authentication_entry_digest_review',
        ], $material['required_entry_statuses']);

        $t->same('permission_bits_decoded_but_authentication_material_incomplete', $permission['permission_authentication_status']);
        $t->same('permission_bits_decoded_but_authentication_material_incomplete', $trust['status']);
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
            && !str_contains($encoded, $userValidationName)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest))));
    },
];
