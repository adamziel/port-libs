<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$selectedDuplicatePermissionOperandPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Selected duplicate permission operand encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('S', 32);
    $userValidation = str_repeat('D', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -60 9 0 R /P -44 /EncryptMetadata true >>\nendobj\n"
        . "9 0 obj\n<< /P -4 /O <DEADBEEF> /U <CAFEFEED> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed while selecting later duplicate Standard permission word after stale trailing operand' => static function (
        TestRunner $t
    ) use ($selectedDuplicatePermissionOperandPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $selectedDuplicatePermissionOperandPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataDeclaration = $metadata['encryption']['standard_permission_word_review'];
        $permission = $report['permission_preflight'];
        $declaration = $permission['standard_permission_word_review'];
        $reviewDeclaration = $report['encryption']['standard_permission_word_review'];
        $staleEntry = $declaration['entries'][0] ?? [];
        $selectedEntry = $declaration['entries'][$declaration['selected_entry_index'] ?? -1] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['permission_bits']);

        $t->same($metadataDeclaration, $declaration);
        $t->same($declaration, $reviewDeclaration);
        $t->same('standard_permission_word_declaration_review', $declaration['source']);
        $t->same(2, $declaration['declared_entry_count']);
        $t->same(1, $declaration['integer_entry_count']);
        $t->same(1, $declaration['selected_entry_index']);
        $t->same('well_formed_standard_permissions', $declaration['selected_entry_status']);
        $t->same('token', $declaration['selected_entry_operand_shape']);
        $t->same('token', $declaration['selected_entry_raw_operand_shape']);
        $t->same(true, $declaration['selected_entry_resolved']);
        $t->same(true, $declaration['selected_entry_integer']);
        $t->same(true, $declaration['selected_entry_single_value']);
        $t->same(false, $declaration['selected_entry_trailing_operand']);
        $t->same(-44, $declaration['selected_permission_signed']);
        $t->same(4294967252, $declaration['selected_permission_unsigned']);
        $t->same('FFFFFFD4', $declaration['selected_permission_hex']);
        $t->same(true, $declaration['duplicate_permission_entries']);
        $t->same(true, $declaration['permission_word_ambiguous']);
        $t->same('duplicate_standard_permission_entries_review', $declaration['status']);
        $t->same(['permission_word_trailing_operand_review', 'well_formed_standard_permissions'], $declaration['entry_statuses']);
        $t->same(['token'], $declaration['entry_operand_shapes']);
        $t->same(['token'], $declaration['entry_raw_operand_shapes']);
        $t->same([0], $declaration['malformed_entry_indexes']);
        $t->same(['permission_word_trailing_operand_review'], $declaration['malformed_entry_statuses']);
        $t->same(true, $declaration['malformed_entries']);
        $t->same(1, $declaration['malformed_entry_count']);
        $t->same([4294967252], $declaration['unsigned_values']);
        $t->same(['FFFFFFD4'], $declaration['hex_values']);
        $t->same([], $declaration['conflicting_permission_names']);
        $t->same([], $declaration['conflicting_statuses']);

        $t->same('standard_permission_word_entry_review', $staleEntry['source'] ?? null);
        $t->same(0, $staleEntry['index'] ?? null);
        $t->same(false, $staleEntry['integer'] ?? null);
        $t->same(false, $staleEntry['single_value'] ?? null);
        $t->same('permission_word_trailing_operand_review', $staleEntry['status'] ?? null);
        $t->same(true, $staleEntry['trailing_operand'] ?? null);
        $t->same('indirect_reference', $staleEntry['trailing_operand_shape'] ?? null);
        $t->same('9 0 R', $staleEntry['trailing_operand_preview'] ?? null);
        $t->same(9, $staleEntry['trailing_operand_object_number'] ?? null);
        $t->same(0, $staleEntry['trailing_operand_generation'] ?? null);

        $t->same(1, $selectedEntry['index'] ?? null);
        $t->same(true, $selectedEntry['integer'] ?? null);
        $t->same(true, $selectedEntry['single_value'] ?? null);
        $t->same(-44, $selectedEntry['signed'] ?? null);
        $t->same('FFFFFFD4', $selectedEntry['hex'] ?? null);
        $t->same('allowed_by_permission_bit', $selectedEntry['permission_bits_by_name']['copy_or_extract']['status'] ?? null);

        $t->true(in_array('permission_word_duplicate_entries', $report['review_reasons'], true));
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
            && !str_contains($encoded, 'DEADBEEF')
            && !str_contains($encoded, 'CAFEFEED'));
    },
    'keeps selected duplicate permission operand payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($selectedDuplicatePermissionOperandPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $selectedDuplicatePermissionOperandPdf();
        $extractor = new PdfTextExtractor();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $extractor->extractPlainText($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same(false, $report['raw_owner_user_keys_exposed']);
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
            && !str_contains($encoded, 'DEADBEEF')
            && !str_contains($encoded, 'CAFEFEED'));
    },
];
