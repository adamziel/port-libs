<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$malformedFilterOperandPdf = static function (string $filterOperand, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} malformed Filter encrypted text leak) Tj ET";
    $ownerValidation = str_repeat(substr($label, 0, 1), 32);
    $userValidation = str_repeat(substr($label, -1), 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter {$filterOperand} /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$assertMalformedFilterOperandPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $operandShape
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $metadataEncryption = $metadata['encryption'];
    $reviewEncryption = $report['encryption'];
    $permission = $report['permission_preflight'];
    $handler = $report['permission_handler_review'];
    $parameterReview = $permission['standard_security_handler_parameter_review'];
    $declaration = $parameterReview['parameter_declaration_review'];
    $filterRow = null;
    foreach ($declaration['rows'] ?? [] as $row) {
        if (($row['pdf_name'] ?? null) === 'Filter') {
            $filterRow = $row;
            break;
        }
    }
    $filterEntry = $filterRow['entries'][0] ?? [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same([
        'encrypted_document',
        'encrypted_text_extraction_blocked',
        'standard_security_handler_parameters_malformed',
        'standard_security_handler_parameter_operands_malformed',
    ], $report['review_reasons']);

    $t->same('Standard', $metadataEncryption['filter']);
    $t->same(4, $metadataEncryption['version']);
    $t->same(4, $metadataEncryption['revision']);
    $t->same(128, $metadataEncryption['key_length_bits']);
    $t->same('FFFFFFD4', $metadataEncryption['standard_permissions']['hex']);
    $t->same(true, in_array('copy_or_extract', $metadataEncryption['standard_permissions']['allowed'], true));

    $t->same('standard_security_handler_malformed_parameters', $permission['source']);
    $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
    $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
    $t->same('malformed_standard_security_handler_parameters_review', $permission['standard_security_handler_parameter_status']);
    $t->same(['malformed_standard_security_handler_parameter_entries'], $permission['standard_security_handler_parameter_violations']);
    $t->same(['Filter'], $permission['standard_security_handler_malformed_parameter_names']);
    $t->same(1, $permission['standard_security_handler_malformed_parameter_count']);
    $t->same([], $permission['standard_security_handler_duplicate_parameter_names']);
    $t->same(0, $permission['standard_security_handler_duplicate_parameter_count']);
    $t->same(false, $permission['permission_word_well_formed']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(null, $permission['accessibility_extract_allowed']);
    $t->same([], $permission['allowed']);
    $t->same([], $permission['denied']);
    $t->same([], $permission['permission_bits']);
    $t->same(0, $permission['permission_bit_review_count']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);

    $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
    $t->same(false, $parameterReview['parameters_well_formed']);
    $t->same(['malformed_standard_security_handler_parameter_entries'], $parameterReview['violations']);
    $t->same(['Filter'], $parameterReview['malformed_parameter_names']);
    $t->same(1, $parameterReview['malformed_parameter_count']);
    $t->same([], $parameterReview['duplicate_parameter_names']);
    $t->same(0, $parameterReview['duplicate_parameter_count']);
    $t->same(true, $parameterReview['version_supported']);
    $t->same(true, $parameterReview['revision_supported']);
    $t->same(true, $parameterReview['version_revision_compatible']);
    $t->same(true, $parameterReview['key_length_valid']);
    $t->same('standard_security_handler_key_length_supported', $parameterReview['key_length_status']);

    $t->same('standard_security_handler_parameter_declaration_review', $declaration['source']);
    $t->same(['Filter'], $declaration['malformed_parameter_names']);
    $t->same(1, $declaration['malformed_parameter_count']);
    $t->same([], $declaration['duplicate_parameter_names']);
    $t->same(0, $declaration['duplicate_parameter_count']);
    $t->same(true, $declaration['fail_closed']);
    $t->same('malformed_standard_security_handler_parameter_entries_review', $declaration['status']);
    $t->same(['Filter' => 1, 'V' => 1, 'R' => 1, 'Length' => 1], $declaration['parameter_entry_counts']);

    $t->same('standard_security_handler_parameter_declaration_row', $filterRow['source'] ?? null);
    $t->same('Filter', $filterRow['pdf_name'] ?? null);
    $t->same('filter', $filterRow['metadata_key'] ?? null);
    $t->same(1, $filterRow['declared_entry_count'] ?? null);
    $t->same(false, $filterRow['duplicate_entries'] ?? null);
    $t->same([$operandShape], $filterRow['entry_operand_shapes'] ?? []);
    $t->same(['standard_security_handler_filter_non_name_operand_review'], $filterRow['entry_statuses'] ?? []);
    $t->same(true, $filterRow['malformed_entries'] ?? null);
    $t->same(1, $filterRow['malformed_entry_count'] ?? null);
    $t->same($operandShape, $filterEntry['operand_shape'] ?? null);
    $t->same('standard_security_handler_filter_non_name_operand_review', $filterEntry['status'] ?? null);

    $t->same('malformed_standard_security_handler_parameters_review', $handler['status']);
    $t->same(false, $handler['standard_security_handler_parameters_well_formed']);
    $t->same(['malformed_standard_security_handler_parameter_entries'], $handler['standard_security_handler_parameter_violations']);
    $t->same(['Filter'], $handler['standard_security_handler_malformed_parameter_names']);
    $t->same(false, $handler['permission_word_well_formed']);
    $t->same(null, $handler['copy_or_extract_allowed'] ?? null);
    $t->same([], $handler['allowed'] ?? []);
    $t->same([], $handler['permission_bits']);
    $t->same(false, $handler['executes_permission_enforcement']);

    $t->same($parameterReview, $reviewEncryption['standard_security_handler_parameter_review']);
    $t->same(false, $reviewEncryption['standard_security_handler_parameters_well_formed']);
    $t->same(['Filter'], $reviewEncryption['standard_security_handler_malformed_parameter_names']);
    $t->same(1, $reviewEncryption['standard_security_handler_malformed_parameter_count']);
    $t->same(false, $reviewEncryption['permission_word_well_formed']);
    $t->same(false, $reviewEncryption['permission_bits_reliable']);
    $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
    $t->same([], $reviewEncryption['allowed']);
    $t->same([], $reviewEncryption['permission_bits']);

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
    'fails closed when Standard Filter is a literal string operand' => static function (
        TestRunner $t
    ) use ($malformedFilterOperandPdf, $assertMalformedFilterOperandPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $malformedFilterOperandPdf('(Standard)', 'LITERAL');

        $assertMalformedFilterOperandPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'literal_string'
        );
    },
    'fails closed when Standard Filter is a hex string operand' => static function (
        TestRunner $t
    ) use ($malformedFilterOperandPdf, $assertMalformedFilterOperandPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $malformedFilterOperandPdf('<5374616E64617264>', 'HEX');

        $assertMalformedFilterOperandPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'hex_string'
        );
    },
];
