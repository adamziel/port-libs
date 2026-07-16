<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$duplicateLengthParameterPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate Length selected-entry encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('D', 32);
    $userValidation = str_repeat('S', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 40 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$selectedLengthRow = static function (array $parameterReview): array {
    foreach ($parameterReview['parameter_declaration_review']['rows'] ?? [] as $row) {
        if (($row['pdf_name'] ?? null) === 'Length') {
            return $row;
        }
    }

    return [];
};

return [
    'records selected duplicate Standard key length entry before failing permission preflight closed' => static function (
        TestRunner $t
    ) use ($duplicateLengthParameterPdf, $selectedLengthRow): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicateLengthParameterPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $metadataEncryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $lengthRow = $selectedLengthRow($parameterReview);
        $firstEntry = $lengthRow['entries'][0] ?? [];
        $selectedEntry = $lengthRow['entries'][$lengthRow['selected_entry_index'] ?? -1] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'standard_security_handler_parameters_malformed',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(4, $metadataEncryption['version']);
        $t->same(4, $metadataEncryption['revision']);
        $t->same(128, $metadataEncryption['key_length_bits']);
        $t->same(true, $metadataEncryption['key_length_explicit']);
        $t->same('encryption_dictionary_length_entry', $metadataEncryption['key_length_source']);

        $t->same('standard_security_handler_parameter_declaration_review', $parameterReview['parameter_declaration_review']['source']);
        $t->same(['Length'], $parameterReview['duplicate_parameter_names']);
        $t->same(['duplicate_standard_security_handler_parameter_entries'], $parameterReview['violations']);
        $t->same(false, $parameterReview['parameters_well_formed']);

        $t->same('standard_security_handler_parameter_declaration_row', $lengthRow['source'] ?? null);
        $t->same('Length', $lengthRow['pdf_name'] ?? null);
        $t->same('key_length_bits', $lengthRow['metadata_key'] ?? null);
        $t->same(2, $lengthRow['declared_entry_count'] ?? null);
        $t->same(true, $lengthRow['duplicate_entries'] ?? null);
        $t->same(1, $lengthRow['selected_entry_index'] ?? null);
        $t->same('standard_security_handler_parameter_entry_well_formed', $lengthRow['selected_entry_status'] ?? null);
        $t->same('token', $lengthRow['selected_entry_operand_shape'] ?? null);
        $t->same(true, $lengthRow['selected_entry_resolved'] ?? null);
        $t->same(true, $lengthRow['selected_entry_integer'] ?? null);
        $t->same(128, $lengthRow['selected_integer_value'] ?? null);
        $t->same(null, $lengthRow['selected_name_value'] ?? null);
        $t->same(['token'], $lengthRow['entry_operand_shapes'] ?? []);
        $t->same(['standard_security_handler_parameter_entry_well_formed'], $lengthRow['entry_statuses'] ?? []);
        $t->same(false, $lengthRow['malformed_entries'] ?? null);
        $t->same(0, $lengthRow['malformed_entry_count'] ?? null);

        $t->same('standard_security_handler_parameter_entry_review', $firstEntry['source'] ?? null);
        $t->same(0, $firstEntry['index'] ?? null);
        $t->same(true, $firstEntry['integer'] ?? null);
        $t->same(40, $firstEntry['integer_value'] ?? null);
        $t->same(null, $firstEntry['name_value'] ?? null);
        $t->same('standard_security_handler_parameter_entry_review', $selectedEntry['source'] ?? null);
        $t->same(1, $selectedEntry['index'] ?? null);
        $t->same(true, $selectedEntry['integer'] ?? null);
        $t->same(128, $selectedEntry['integer_value'] ?? null);
        $t->same(null, $selectedEntry['name_value'] ?? null);

        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);

        $t->same($parameterReview, $reviewEncryption['standard_security_handler_parameter_review']);
        $t->same(false, $reviewEncryption['standard_security_handler_parameters_well_formed']);
        $t->same(['Length'], $reviewEncryption['standard_security_handler_duplicate_parameter_names']);
        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);

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
    },
    'keeps duplicate-parameter encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($duplicateLengthParameterPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicateLengthParameterPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(false, str_contains($plainText, $content));
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
    },
];
