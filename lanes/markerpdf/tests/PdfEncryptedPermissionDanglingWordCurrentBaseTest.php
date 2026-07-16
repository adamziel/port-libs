<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$danglingPermissionPdf = static function (string $permissionSegment, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} dangling permission encrypted text leak) Tj ET";
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
        . $permissionSegment
        . " >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$assertDanglingPermissionPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    array $expectedReasons,
    int $expectedDeclaredEntryCount,
    int $expectedIntegerEntryCount,
    string $expectedDeclarationStatus,
    bool $expectedDuplicate,
    array $expectedUnsignedValues,
    array $expectedHexValues
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $encryption = $metadata['encryption'];
    $reviewEncryption = $report['encryption'];
    $permission = $report['permission_preflight'];
    $handler = $report['permission_handler_review'];
    $declaration = $permission['standard_permission_word_review'];
    $selectedEntry = $declaration['entries'][$declaration['selected_entry_index']] ?? [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', $plainText);
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same($expectedReasons, $report['review_reasons']);
    $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

    $t->same('Standard', $encryption['filter']);
    $t->same(4, $encryption['version']);
    $t->same(4, $encryption['revision']);
    $t->same(128, $encryption['key_length_bits']);
    $t->same(false, isset($encryption['standard_permissions']));
    $t->same($declaration, $encryption['standard_permission_word_review']);
    $t->same($declaration, $reviewEncryption['standard_permission_word_review']);
    $t->same($expectedDeclarationStatus, $reviewEncryption['permission_word_status']);
    $t->same(false, $reviewEncryption['permission_word_well_formed']);
    $t->same(false, $reviewEncryption['permission_bits_reliable']);
    $t->same(null, $reviewEncryption['permission_hex']);
    $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
    $t->same([], $reviewEncryption['allowed']);
    $t->same([], $reviewEncryption['permission_bits']);

    $t->same('standard_security_handler_malformed_permissions', $permission['source']);
    $t->same(true, $permission['permissions_declared']);
    $t->same(true, $permission['standard_permissions_declared']);
    $t->same(true, $permission['standard_permission_word_declared']);
    $t->same(false, $permission['standard_permission_bits_decoded']);
    $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
    $t->same(null, $permission['permission_hex']);
    $t->same(null, $permission['permission_signed']);
    $t->same(null, $permission['permission_unsigned']);
    $t->same(false, $permission['permission_word_well_formed']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(null, $permission['accessibility_extract_allowed']);
    $t->same([], $permission['allowed']);
    $t->same([], $permission['denied']);
    $t->same([], $permission['permission_bits']);
    $t->same(0, $permission['permission_bit_review_count']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);

    $t->same('standard_permission_word_declaration_review', $declaration['source']);
    $t->same('P', $declaration['pdf_name']);
    $t->same($expectedDeclaredEntryCount, $declaration['declared_entry_count']);
    $t->same($expectedIntegerEntryCount, $declaration['integer_entry_count']);
    $t->same($expectedDeclaredEntryCount - 1, $declaration['selected_entry_index']);
    $t->same('permission_word_trailing_operand_review', $declaration['selected_entry_status']);
    $t->same(false, $declaration['selected_entry_integer']);
    $t->same(null, $declaration['selected_permission_hex']);
    $t->same($expectedDuplicate, $declaration['duplicate_permission_entries']);
    $t->same(true, $declaration['permission_word_ambiguous']);
    $t->same($expectedDeclarationStatus, $declaration['status']);
    $t->same($expectedUnsignedValues, $declaration['unsigned_values']);
    $t->same($expectedHexValues, $declaration['hex_values']);
    $t->same('standard_permission_word_entry_review', $selectedEntry['source'] ?? null);
    $t->same('P', $selectedEntry['pdf_name'] ?? null);
    $t->same('empty', $selectedEntry['operand_shape'] ?? null);
    $t->same(false, $selectedEntry['integer'] ?? null);
    $t->same(false, $selectedEntry['single_value'] ?? null);
    $t->same(true, $selectedEntry['trailing_operand'] ?? null);
    $t->same('missing_value', $selectedEntry['trailing_operand_shape'] ?? null);
    $t->same('missing_top_level_value', $selectedEntry['trailing_operand_preview'] ?? null);
    $t->same('permission_word_trailing_operand_review', $selectedEntry['status'] ?? null);

    $t->same('permission_handler_review', $handler['source']);
    $t->same('Standard', $handler['handler']);
    $t->same(true, $handler['standard_handler']);
    $t->same(true, $handler['handler_supported_for_native_permission_review']);
    $t->same(false, $handler['permission_word_well_formed']);
    $t->same($expectedDeclarationStatus, $handler['status']);
    $t->same(false, $handler['executes_permission_enforcement']);

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
        && !str_contains($encoded, 'copy_extract_allowed_after_decryption'));
};

return [
    'fails closed when a duplicate Standard permission key is dangling at dictionary close' => static function (
        TestRunner $t
    ) use ($danglingPermissionPdf, $assertDanglingPermissionPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $danglingPermissionPdf(' /P -44 /P', 'DUPLICATE');

        $assertDanglingPermissionPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            ['encrypted_document', 'encrypted_text_extraction_blocked', 'permission_word_duplicate_entries'],
            2,
            1,
            'duplicate_standard_permission_entries_review',
            true,
            [4294967252],
            ['FFFFFFD4']
        );
    },
    'treats an explicit Standard permission key without a value as malformed permissions' => static function (
        TestRunner $t
    ) use ($danglingPermissionPdf, $assertDanglingPermissionPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $danglingPermissionPdf(' /P', 'MISSING');

        $assertDanglingPermissionPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            ['encrypted_document', 'encrypted_text_extraction_blocked', 'permission_word_trailing_operand'],
            1,
            0,
            'malformed_standard_permission_word_review',
            false,
            [],
            []
        );
    },
];
