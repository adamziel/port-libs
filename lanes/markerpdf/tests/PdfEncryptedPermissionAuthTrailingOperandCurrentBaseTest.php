<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$topLevelTrailingAuthPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Trailing auth operand encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $shadowAuth = str_repeat('S', 48);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 5 /R 6 /Length 256"
        . " /O " . $hex($ownerValidation) . " 30 0 R"
        . " /U " . $hex($userValidation)
        . " /OE " . $hex($ownerEncryptionKey)
        . " /UE " . $hex($userEncryptionKey)
        . " /P -44 /EncryptMetadata true /Perms " . $hex($permissionDigest)
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "30 0 obj\n" . $hex($shadowAuth) . "\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest, $shadowAuth];
};

$indirectTrailingPermsPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Trailing Perms operand encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);
    $permissionDigest = str_repeat('P', 16);
    $shadowDigest = str_repeat('Q', 16);

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
        . " /P -44 /EncryptMetadata true /Perms 31 0 R"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "31 0 obj\n" . $hex($permissionDigest) . " /ShadowPerms\nendobj\n"
        . "32 0 obj\n" . $hex($shadowDigest) . "\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest, $shadowDigest];
};

return [
    'fails closed when a Standard owner auth string has a trailing top-level operand' => static function (
        TestRunner $t
    ) use ($topLevelTrailingAuthPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest, $shadowAuth] = $topLevelTrailingAuthPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $ownerEntry = $auth['entries']['owner_validation'];
        $ownerReview = $ownerEntry['entry_reviews'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same('authentication_entry_trailing_operand_review', $ownerEntry['status']);
        $t->same('authentication_entry_trailing_operand_review', $ownerEntry['selected_entry_status']);
        $t->same(['authentication_entry_trailing_operand_review'], $ownerEntry['entry_statuses']);
        $t->same('hex_string', $ownerEntry['operand_shape']);
        $t->same(false, $ownerEntry['bytes_resolved']);
        $t->same(null, $ownerEntry['bytes']);
        $t->same(null, $ownerEntry['sha256']);
        $t->same(true, $ownerReview['trailing_operand'] ?? null);
        $t->same('indirect_reference', $ownerReview['trailing_operand_shape'] ?? null);
        $t->same('30 0 R', $ownerReview['trailing_operand_preview'] ?? null);
        $t->same(30, $ownerReview['trailing_operand_object_number'] ?? null);
        $t->same(0, $ownerReview['trailing_operand_generation'] ?? null);

        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(['owner_validation'], $material['unresolved_required_entries']);
        $t->same(['authentication_entry_trailing_operand_review', 'authentication_entry_digest_review'], $material['required_entry_statuses']);
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
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, $shadowAuth)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($shadowAuth))));
    },
    'fails closed when an indirect revision six Perms string has a trailing operand' => static function (
        TestRunner $t
    ) use ($indirectTrailingPermsPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest, $shadowDigest] = $indirectTrailingPermsPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $metadataPerms = $metadata['encryption']['perms'];
        $digest = $auth['permission_digest'];
        $digestReview = $digest['entry_reviews'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);

        $t->same(false, $metadataPerms['bytes_resolved']);
        $t->same(null, $metadataPerms['bytes']);
        $t->same(null, $metadataPerms['sha256']);
        $t->same('permission_digest_trailing_operand_review', $metadataPerms['selected_entry_status']);
        $t->same('hex_string', $metadataPerms['selected_entry_operand_shape']);
        $t->same(['permission_digest_trailing_operand_review'], $metadataPerms['entry_statuses']);

        $t->same('permission_digest_trailing_operand_review', $digest['status']);
        $t->same('permission_digest_trailing_operand_review', $digest['selected_entry_status']);
        $t->same('hex_string', $digest['selected_entry_operand_shape']);
        $t->same(false, $digest['length_valid']);
        $t->same(null, $digest['bytes']);
        $t->same(null, $digest['sha256']);
        $t->same(true, $digestReview['trailing_operand'] ?? null);
        $t->same('name', $digestReview['trailing_operand_shape'] ?? null);
        $t->same('/ShadowPerms', $digestReview['trailing_operand_preview'] ?? null);
        $t->same('ShadowPerms', $digestReview['trailing_operand_name'] ?? null);

        $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
        $t->same(false, $material['ready_for_password_attempt']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(false, $material['permission_digest_length_valid']);
        $t->same('permission_digest_trailing_operand_review', $material['permission_digest_status']);
        $t->same('permission_digest_malformed_before_permission_authentication', $trust['status']);
        $t->same('permission_digest_trailing_operand_review', $trust['permission_digest_status']);
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
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, $ownerEncryptionKey)
            && !str_contains($encoded, $userEncryptionKey)
            && !str_contains($encoded, $permissionDigest)
            && !str_contains($encoded, $shadowDigest)
            && !str_contains($encoded, strtoupper(bin2hex($permissionDigest)))
            && !str_contains($encoded, strtoupper(bin2hex($shadowDigest))));
    },
];
