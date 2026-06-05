<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$compositePermissionPdf = static function (
    string $permissionOperand,
    string $label,
    string $extraObjects = ''
) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} composite permission encrypted text leak) Tj ET";
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
        . " /P {$permissionOperand} /EncryptMetadata true >>\nendobj\n"
        . $extraObjects
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$assertCompositePermissionOperand = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $operandShape
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $permission = $report['permission_preflight'];
    $handler = $report['permission_handler_review'];
    $reviewEncryption = $report['encryption'];
    $declaration = $permission['standard_permission_word_review'];
    $entry = $declaration['entries'][0] ?? [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'permission_word_composite_operand'], $report['review_reasons']);
    $t->same('Standard', $metadata['encryption']['filter']);
    $t->same(false, isset($metadata['encryption']['standard_permissions']));

    $t->same('standard_security_handler_malformed_permissions', $permission['source']);
    $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
    $t->same(null, $permission['permission_hex']);
    $t->same(null, $permission['permission_signed']);
    $t->same(null, $permission['permission_unsigned']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same(false, $permission['permission_word_well_formed']);
    $t->same([], $permission['allowed']);
    $t->same([], $permission['denied']);
    $t->same([], $permission['permission_bits']);
    $t->same(0, $permission['permission_bit_review_count']);

    $t->same('malformed_standard_permission_word_review', $reviewEncryption['permission_word_status']);
    $t->same(false, $reviewEncryption['permission_word_well_formed']);
    $t->same(false, $reviewEncryption['permission_bits_reliable']);
    $t->same(null, $reviewEncryption['copy_or_extract_allowed']);

    $t->same('malformed_standard_permission_word_review', $handler['status']);
    $t->same(false, $handler['permission_word_well_formed']);
    $t->same(false, $handler['executes_permission_enforcement']);

    $t->same('standard_permission_word_declaration_review', $declaration['source']);
    $t->same(1, $declaration['declared_entry_count']);
    $t->same(0, $declaration['integer_entry_count']);
    $t->same(false, $declaration['duplicate_permission_entries']);
    $t->same(true, $declaration['permission_word_ambiguous']);
    $t->same('malformed_standard_permission_word_review', $declaration['status']);
    $t->same(['permission_word_composite_operand_review'], $declaration['entry_statuses']);
    $t->same([], $declaration['unsigned_values']);
    $t->same([], $declaration['hex_values']);
    $t->same('standard_permission_word_entry_review', $entry['source'] ?? null);
    $t->same('permission_word_composite_operand_review', $entry['status'] ?? null);
    $t->same($operandShape, $entry['operand_shape'] ?? null);
    $t->same(true, $entry['resolved'] ?? null);
    $t->same(false, $entry['integer'] ?? null);

    $t->same(false, $report['executes_decryption']);
    $t->same(false, $report['executes_permission_enforcement']);
    $t->same(false, $report['executes_python_or_models']);
    $t->same(false, $report['executes_external_pdf_tools']);
    $t->true(is_string($encoded)
        && !str_contains($encoded, $content)
        && !str_contains($encoded, $ownerValidation)
        && !str_contains($encoded, $userValidation)
        && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
};

return [
    'fails closed when Standard permission word is a direct array operand' => static function (
        TestRunner $t
    ) use ($compositePermissionPdf, $assertCompositePermissionOperand): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $compositePermissionPdf('[-44]', 'ARRAY');

        $assertCompositePermissionOperand($t, $pdf, $content, $ownerValidation, $userValidation, 'array');
    },
    'fails closed when Standard permission word resolves to a dictionary operand' => static function (
        TestRunner $t
    ) use ($compositePermissionPdf, $assertCompositePermissionOperand): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $compositePermissionPdf(
            '18 0 R',
            'DICTIONARY',
            "18 0 obj\n<< /Value -44 >>\nendobj\n"
        );

        $assertCompositePermissionOperand($t, $pdf, $content, $ownerValidation, $userValidation, 'dictionary');
    },
];
