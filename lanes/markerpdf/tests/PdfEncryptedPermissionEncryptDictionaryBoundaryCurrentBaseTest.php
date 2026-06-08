<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$malformedEncryptDictionaryBoundaryPdf = static function (
    string $encryptOperand,
    string $label,
    string $extraObjects = '',
    ?string $trailerPrefix = null
) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} malformed Encrypt boundary text leak) Tj ET";
    $ownerValidation = str_repeat(substr($label, 0, 1), 32);
    $userValidation = str_repeat(substr($label, -1), 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . $extraObjects
        . 'trailer' . "\n"
        . ($trailerPrefix ?? '<< /Root 1 0 R ')
        . "/Encrypt {$encryptOperand} >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$assertMalformedEncryptDictionaryPermissionBoundary = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $expectedOperandStatus,
    string $expectedPermissionStatus,
    array $expectedReviewReasons,
    ?array $expectedExtra = null
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $encryption = $metadata['encryption'];
    $permission = $report['permission_preflight'];
    $boundary = is_array($permission['encrypt_dictionary_permission_review'] ?? null)
        ? $permission['encrypt_dictionary_permission_review']
        : [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', $plainText);
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same($expectedReviewReasons, $report['review_reasons']);
    $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

    $t->same(true, $encryption['malformed_encrypt_dictionary']);
    $t->same(false, $encryption['encrypt_dictionary_resolved']);
    $t->same($expectedOperandStatus, $encryption['encrypt_operand_status']);
    $t->same(false, isset($encryption['standard_permissions']));

    $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
    $t->same('permissions_unknown_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_unknown', $permission['content_extraction_boundary']);
    $t->same(false, $permission['permissions_declared']);
    $t->same(false, $permission['standard_permissions_declared']);
    $t->same(null, $permission['permission_hex']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same([], $permission['allowed']);
    $t->same([], $permission['denied']);
    $t->same([], $permission['permission_bits']);
    $t->same(0, $permission['permission_bit_review_count']);

    $t->same('encrypt_dictionary_permission_preflight', $boundary['source'] ?? null);
    $t->same(true, $boundary['present'] ?? null);
    $t->same(true, $boundary['malformed_encrypt_dictionary'] ?? null);
    $t->same(false, $boundary['encrypt_dictionary_resolved'] ?? null);
    $t->same($expectedOperandStatus, $boundary['encrypt_operand_status'] ?? null);
    $t->same($expectedPermissionStatus, $boundary['status'] ?? null);
    $t->same(true, $boundary['fail_closed'] ?? null);
    $t->same('permissions_unknown_blocked_without_decryption', $boundary['permission_policy'] ?? null);
    $t->same('blocked_encrypted_permissions_unknown', $boundary['content_extraction_boundary'] ?? null);
    $t->same(false, $boundary['permission_bits_reliable'] ?? null);
    $t->same(false, $boundary['decryption_performed'] ?? null);
    $t->same(false, $boundary['executes_permission_enforcement'] ?? null);
    $t->same(false, $boundary['executes_decryption'] ?? null);
    $t->same($expectedPermissionStatus, $permission['encrypt_dictionary_permission_status'] ?? null);
    $t->same(true, $permission['encrypt_dictionary_permission_fail_closed'] ?? null);
    $t->same('blocked_encrypted_permissions_unknown', $permission['encrypt_dictionary_permission_boundary'] ?? null);

    foreach ($expectedExtra ?? [] as $key => $value) {
        $t->same($value, $boundary[$key] ?? null);
    }

    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded)
        && !str_contains($encoded, $content)
        && !str_contains($encoded, $ownerValidation)
        && !str_contains($encoded, $userValidation)
        && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        && !str_contains($encoded, strtoupper(bin2hex($userValidation)))
        && !str_contains($encoded, 'FFFFFFD4')
        && !str_contains($encoded, 'copy_extract_allowed_after_decryption'));
};

return [
    'records unresolved Encrypt reference as a permission preflight boundary before stale grants' => static function (
        TestRunner $t
    ) use ($malformedEncryptDictionaryBoundaryPdf, $assertMalformedEncryptDictionaryPermissionBoundary): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $malformedEncryptDictionaryBoundaryPdf(
            '99 0 R',
            'UNRESOLVED'
        );

        $assertMalformedEncryptDictionaryPermissionBoundary(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'encrypt_dictionary_unresolved_reference',
            'malformed_unresolved_encrypt_reference',
            ['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown'],
            [
                'encrypt_operand_shape' => 'indirect_reference',
                'encrypt_dictionary_declared_entry_count' => 0,
                'duplicate_encrypt_dictionary_entries' => false,
            ]
        );
    },
    'records trailing Encrypt operands as a permission preflight boundary' => static function (
        TestRunner $t
    ) use ($malformedEncryptDictionaryBoundaryPdf, $assertMalformedEncryptDictionaryPermissionBoundary): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $malformedEncryptDictionaryBoundaryPdf(
            '5 0 R 6 0 R',
            'TRAILING',
            "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 >>\nendobj\n"
        );

        $assertMalformedEncryptDictionaryPermissionBoundary(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'encrypt_dictionary_trailing_operand_review',
            'malformed_trailing_encrypt_operand',
            ['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown', 'encrypt_dictionary_trailing_operand'],
            [
                'encrypt_operand_shape' => 'indirect_reference',
                'encrypt_operand_single_value' => false,
                'encrypt_trailing_operand' => true,
                'encrypt_trailing_operand_shape' => 'indirect_reference',
                'encrypt_trailing_operand_preview' => '6 0 R',
                'encrypt_trailing_operand_object_number' => 6,
                'encrypt_trailing_operand_generation' => 0,
            ]
        );
    },
    'records duplicate Encrypt entries as a permission preflight boundary' => static function (
        TestRunner $t
    ) use ($malformedEncryptDictionaryBoundaryPdf, $assertMalformedEncryptDictionaryPermissionBoundary): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $malformedEncryptDictionaryBoundaryPdf(
            '5 0 R',
            'DUPLICATE',
            '',
            '<< /Root 1 0 R /Encrypt null '
        );

        $assertMalformedEncryptDictionaryPermissionBoundary(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'duplicate_encrypt_dictionary_entries_review',
            'malformed_duplicate_encrypt_dictionary_entries',
            ['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown', 'duplicate_encrypt_dictionary_entries'],
            [
                'encrypt_operand_shape' => 'duplicate_entries',
                'duplicate_encrypt_dictionary_entries' => true,
                'encrypt_dictionary_declared_entry_count' => 2,
                'encrypt_dictionary_resolved_entry_count' => 1,
                'encrypt_dictionary_entry_statuses' => ['encrypt_dictionary_explicit_null', 'encrypt_dictionary_entry_resolved'],
                'encrypt_dictionary_entry_shapes' => ['token', 'indirect_reference'],
            ]
        );
    },
];
