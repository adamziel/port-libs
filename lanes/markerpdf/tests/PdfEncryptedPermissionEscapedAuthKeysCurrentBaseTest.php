<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$escapedAuthenticationKeysPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Escaped auth key encrypted text leak) Tj ET';
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
        . " /#4F " . $hex($ownerValidation)
        . " /#55 " . $hex($userValidation)
        . " /#4F#45 " . $hex($ownerEncryptionKey)
        . " /#55#45 " . $hex($userEncryptionKey)
        . " /#50 -44 /EncryptMetadata true /#50erms " . $hex($permissionDigest)
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest];
};

return [
    'resolves escaped Standard authentication keys before encrypted permission trust review' => static function (
        TestRunner $t
    ) use ($escapedAuthenticationKeysPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $permissionDigest] = $escapedAuthenticationKeysPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $metadataEncryption = $metadata['encryption'];
        $permission = $report['permission_preflight'];
        $auth = $permission['standard_authentication_review'];
        $material = $permission['standard_authentication_material_review'];
        $trust = $permission['permission_authentication_trust_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(5, $metadataEncryption['version']);
        $t->same(6, $metadataEncryption['revision']);
        $t->same(256, $metadataEncryption['key_length_bits']);
        $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
        $t->same(true, $metadataEncryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));
        $t->same(true, isset($metadataEncryption['perms']['sha256']));

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['standard_authentication_ready_for_password_attempt']);
        $t->same('permission_bits_decoded_but_unauthenticated_ready_for_password_attempt', $permission['permission_authentication_status']);

        $t->same('standard_security_handler_authentication_review', $auth['source']);
        $t->same(hash('sha256', $ownerValidation), $auth['entries']['owner_validation']['sha256']);
        $t->same(hash('sha256', $userValidation), $auth['entries']['user_validation']['sha256']);
        $t->same(hash('sha256', $ownerEncryptionKey), $auth['entries']['owner_encryption_key']['sha256']);
        $t->same(hash('sha256', $userEncryptionKey), $auth['entries']['user_encryption_key']['sha256']);
        $t->same(hash('sha256', $permissionDigest), $auth['permission_digest']['sha256']);
        $t->same('permission_digest_ciphertext_review', $auth['permission_digest']['status']);
        $t->same(false, $auth['password_validation_performed']);
        $t->same(false, $auth['permissions_authenticated']);

        $t->same('standard_authentication_material_ready_for_password_attempt', $material['status']);
        $t->same(true, $material['ready_for_password_attempt']);
        $t->same(['owner_validation', 'user_validation', 'owner_encryption_key', 'user_encryption_key'], $material['present_required_entries']);
        $t->same([], $material['missing_required_entries']);
        $t->same([], $material['unresolved_required_entries']);
        $t->same([], $material['length_mismatch_required_entries']);
        $t->same(true, $material['permission_digest_present']);
        $t->same(16, $material['permission_digest_bytes']);
        $t->same(true, $material['permission_digest_length_valid']);

        $t->same('standard_permission_authentication_trust_review', $trust['source']);
        $t->same(true, $trust['authentication_material_ready_for_password_attempt']);
        $t->same(false, $trust['permissions_authenticated']);
        $t->same(false, $trust['authenticated_permission_bits_reliable']);
        $t->same('blocked_until_password_validation_and_permission_authentication', $trust['trust_boundary']);

        $t->same(true, $report['encryption']['perms_hash_present']);
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
