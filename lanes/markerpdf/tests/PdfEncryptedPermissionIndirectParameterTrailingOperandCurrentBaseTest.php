<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$indirectParameterTrailingOperandPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect parameter trailing operand encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('I', 32);
    $userValidation = str_repeat('T', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 7 0 R /R 8 0 R /Length 9 0 R"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "7 0 obj\n4 /ShadowVersion 2\nendobj\n"
        . "8 0 obj\n4 10 0 R\nendobj\n"
        . "9 0 obj\n128 256\nendobj\n"
        . "10 0 obj\n5\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$parameterRow = static function (array $review, string $pdfName): array {
    foreach ($review['parameter_declaration_review']['rows'] ?? [] as $row) {
        if (is_array($row) && ($row['pdf_name'] ?? null) === $pdfName) {
            return $row;
        }
    }

    throw new RuntimeException("Missing Standard security-handler parameter row for {$pdfName}.");
};

return [
    'fails closed when indirect Standard security handler parameters resolve to trailing operands' => static function (
        TestRunner $t
    ) use ($indirectParameterTrailingOperandPdf, $parameterRow): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $indirectParameterTrailingOperandPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncryption = $metadata['encryption'];
        $permission = $report['permission_preflight'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $declaration = $parameterReview['parameter_declaration_review'];
        $versionRow = $parameterRow($parameterReview, 'V');
        $revisionRow = $parameterRow($parameterReview, 'R');
        $lengthRow = $parameterRow($parameterReview, 'Length');
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'standard_security_handler_parameters_malformed',
            'standard_security_handler_parameter_operands_malformed',
        ], $report['review_reasons']);

        $t->same('Standard', $metadataEncryption['filter']);
        $t->same(false, isset($metadataEncryption['version']));
        $t->same(false, isset($metadataEncryption['revision']));
        $t->same(false, isset($metadataEncryption['key_length_bits']));
        $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
        $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same('malformed_standard_security_handler_parameters_review', $permission['standard_security_handler_parameter_status']);
        $t->same(['malformed_standard_security_handler_parameter_entries'], $permission['standard_security_handler_parameter_violations']);
        $t->same(['V', 'R', 'Length'], $permission['standard_security_handler_malformed_parameter_names']);
        $t->same(3, $permission['standard_security_handler_malformed_parameter_count']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);
        $t->same(0, $permission['permission_bit_review_count']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
        $t->same(false, $parameterReview['parameters_well_formed']);
        $t->same(['malformed_standard_security_handler_parameter_entries'], $parameterReview['violations']);
        $t->same(['V', 'R', 'Length'], $parameterReview['malformed_parameter_names']);
        $t->same(3, $parameterReview['malformed_parameter_count']);
        $t->same([], $parameterReview['duplicate_parameter_names']);
        $t->same(0, $parameterReview['duplicate_parameter_count']);

        $t->same('standard_security_handler_parameter_declaration_review', $declaration['source']);
        $t->same(['V', 'R', 'Length'], $declaration['malformed_parameter_names']);
        $t->same(3, $declaration['malformed_parameter_count']);
        $t->same(['V', 'R', 'Length'], $declaration['indirect_parameter_names']);
        $t->same(3, $declaration['indirect_parameter_count']);
        $t->same(true, $declaration['fail_closed']);
        $t->same('malformed_standard_security_handler_parameter_entries_review', $declaration['status']);

        foreach ([
            [$versionRow, 'V', 7, 4, 'name', '/ShadowVersion', ['trailing_operand_name' => 'ShadowVersion']],
            [$revisionRow, 'R', 8, 4, 'indirect_reference', '10 0 R', ['trailing_operand_object_number' => 10, 'trailing_operand_generation' => 0]],
            [$lengthRow, 'Length', 9, 128, 'number', '256', []],
        ] as [$row, $pdfName, $objectNumber, $integerValue, $trailingShape, $trailingPreview, $extra]) {
            $entry = $row['entries'][0] ?? [];

            $t->same('standard_security_handler_parameter_declaration_row', $row['source'] ?? null);
            $t->same($pdfName, $row['pdf_name'] ?? null);
            $t->same(1, $row['declared_entry_count'] ?? null);
            $t->same(false, $row['duplicate_entries'] ?? null);
            $t->same('standard_security_handler_parameter_trailing_operand_review', $row['selected_entry_status'] ?? null);
            $t->same('token', $row['selected_entry_operand_shape'] ?? null);
            $t->same('indirect_reference', $row['selected_entry_raw_operand_shape'] ?? null);
            $t->same(true, $row['selected_entry_resolved'] ?? null);
            $t->same(true, $row['selected_entry_integer'] ?? null);
            $t->same($integerValue, $row['selected_integer_value'] ?? null);
            $t->same($objectNumber, $row['selected_entry_reference_object_number'] ?? null);
            $t->same(0, $row['selected_entry_reference_generation'] ?? null);
            $t->same($objectNumber, $row['selected_entry_resolved_object_number'] ?? null);
            $t->same(0, $row['selected_entry_resolved_generation'] ?? null);
            $t->same(['standard_security_handler_parameter_trailing_operand_review'], $row['entry_statuses'] ?? []);
            $t->same(true, $row['malformed_entries'] ?? null);
            $t->same(1, $row['malformed_entry_count'] ?? null);

            $t->same('standard_security_handler_parameter_entry_review', $entry['source'] ?? null);
            $t->same(true, $entry['resolved'] ?? null);
            $t->same(false, $entry['single_value'] ?? null);
            $t->same('standard_security_handler_parameter_trailing_operand_review', $entry['status'] ?? null);
            $t->same(true, $entry['trailing_operand'] ?? null);
            $t->same($trailingShape, $entry['trailing_operand_shape'] ?? null);
            $t->same($trailingPreview, $entry['trailing_operand_preview'] ?? null);
            foreach ($extra as $key => $value) {
                $t->same($value, $entry[$key] ?? null);
            }
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
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
];
