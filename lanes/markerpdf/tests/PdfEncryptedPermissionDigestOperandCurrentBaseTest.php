<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$malformedPermsOperandPdf = static function (string $permsOperand, string $label, string $rawPermsBytes) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} malformed Perms encrypted text leak) Tj ET";
    $ownerValidation = str_repeat('O', 48);
    $userValidation = str_repeat('U', 48);
    $ownerEncryptionKey = str_repeat('E', 32);
    $userEncryptionKey = str_repeat('K', 32);

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
        . " /P -44 /EncryptMetadata true /Perms {$permsOperand}"
        . " /CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>"
        . " /StmF /StdCF /StrF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $rawPermsBytes];
};

$assertMalformedPermsOperand = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $ownerEncryptionKey,
    string $userEncryptionKey,
    string $rawPermsBytes,
    string $operandShape,
    string $entryStatus
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $permission = $report['permission_preflight'];
    $auth = $permission['standard_authentication_review'];
    $material = $permission['standard_authentication_material_review'];
    $trust = $permission['permission_authentication_trust_review'];
    $metadataPerms = $metadata['encryption']['perms'];
    $digest = $auth['permission_digest'];
    $entry = $digest['entry_reviews'][0] ?? [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
    $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
    $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
    $t->same(true, $permission['permission_bits_reliable']);
    $t->same(true, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['standard_authentication_ready_for_password_attempt']);
    $t->same('permission_digest_malformed_before_permission_authentication', $permission['permission_authentication_status']);

    $t->same(false, $metadataPerms['bytes_resolved']);
    $t->same(null, $metadataPerms['bytes']);
    $t->same(null, $metadataPerms['sha256']);
    $t->same(1, $metadataPerms['declared_entry_count']);
    $t->same(false, $metadataPerms['duplicate_entries']);
    $t->same(0, $metadataPerms['selected_entry_index']);
    $t->same($entryStatus, $metadataPerms['selected_entry_status']);
    $t->same($operandShape, $metadataPerms['selected_entry_operand_shape']);
    $t->same([$entryStatus], $metadataPerms['entry_statuses']);
    $t->same([$operandShape], $metadataPerms['entry_operand_shapes']);

    $t->same('standard_permissions_validation_ciphertext', $digest['source']);
    $t->same(true, $digest['present']);
    $t->same(null, $digest['bytes']);
    $t->same(null, $digest['sha256']);
    $t->same(16, $digest['expected_bytes']);
    $t->same(false, $digest['length_valid']);
    $t->same($entryStatus, $digest['status']);
    $t->same($entryStatus, $digest['selected_entry_status']);
    $t->same($operandShape, $digest['selected_entry_operand_shape']);
    $t->same([$operandShape], $digest['entry_operand_shapes']);
    $t->same(false, $digest['raw_bytes_exposed']);

    $t->same('standard_permissions_validation_ciphertext_entry', $entry['source'] ?? null);
    $t->same($operandShape, $entry['operand_shape'] ?? null);
    $t->same($entryStatus, $entry['status'] ?? null);
    $t->same(false, $entry['bytes_resolved'] ?? null);
    $t->same(null, $entry['sha256'] ?? null);
    $t->same(false, $entry['raw_bytes_exposed'] ?? null);

    $t->same('standard_authentication_material_incomplete_or_malformed_review', $material['status']);
    $t->same(false, $material['ready_for_password_attempt']);
    $t->same(true, $material['permission_digest_present']);
    $t->same(false, $material['permission_digest_length_valid']);
    $t->same($entryStatus, $material['permission_digest_status']);
    $t->same($operandShape, $material['permission_digest_selected_entry_operand_shape']);
    $t->same([$operandShape], $material['permission_digest_entry_operand_shapes']);

    $t->same('permission_digest_malformed_before_permission_authentication', $trust['status']);
    $t->same($entryStatus, $trust['permission_digest_status']);
    $t->same($operandShape, $trust['permission_digest_selected_entry_operand_shape']);
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
        && !str_contains($encoded, $rawPermsBytes)
        && !str_contains($encoded, strtoupper(bin2hex($rawPermsBytes))));
};

return [
    'classifies composite Standard permission digest operands before encrypted preflight' => static function (
        TestRunner $t
    ) use ($hex, $malformedPermsOperandPdf, $assertMalformedPermsOperand): void {
        $rawPermsBytes = 'ARRAY_PERMS_BYTES_SHOULD_NOT_LEAK';
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $rawPermsBytes] = $malformedPermsOperandPdf(
            '[' . $hex($rawPermsBytes) . ']',
            'ARRAY',
            $rawPermsBytes
        );

        $assertMalformedPermsOperand(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $rawPermsBytes,
            'array',
            'permission_digest_composite_operand_review'
        );
    },
    'classifies non-string Standard permission digest operands before encrypted preflight' => static function (
        TestRunner $t
    ) use ($malformedPermsOperandPdf, $assertMalformedPermsOperand): void {
        $rawPermsBytes = 'NAME_PERMS_BYTES_SHOULD_NOT_LEAK';
        [$pdf, $content, $ownerValidation, $userValidation, $ownerEncryptionKey, $userEncryptionKey, $rawPermsBytes] = $malformedPermsOperandPdf(
            '/NotCiphertext',
            'NAME',
            $rawPermsBytes
        );

        $assertMalformedPermsOperand(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $rawPermsBytes,
            'name',
            'permission_digest_non_string_operand_review'
        );
    },
];
