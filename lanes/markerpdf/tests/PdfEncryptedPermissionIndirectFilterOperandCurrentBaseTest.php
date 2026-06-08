<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$indirectFilterOperandPdf = static function (string $filterObjectBody, string $label) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} indirect filter encrypted text leak) Tj ET";
    $ownerValidation = str_repeat(substr($label, 0, 1), 32);
    $userValidation = str_repeat(substr($label, -1), 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter 9 0 R /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "9 0 obj\n{$filterObjectBody}\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$filterRow = static function (array $parameterReview): array {
    foreach ($parameterReview['parameter_declaration_review']['rows'] ?? [] as $row) {
        if (($row['pdf_name'] ?? null) === 'Filter') {
            return $row;
        }
    }

    throw new RuntimeException('Missing Standard Filter parameter declaration row.');
};

return [
    'fails closed when Standard Filter resolves through an indirect literal string operand' => static function (
        TestRunner $t
    ) use ($indirectFilterOperandPdf, $filterRow): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $indirectFilterOperandPdf('(Standard)', 'LITERAL');
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $row = $filterRow($parameterReview);
        $entry = $row['entries'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'standard_security_handler_parameters_malformed',
            'standard_security_handler_parameter_operands_malformed',
        ], $report['review_reasons']);

        $t->same('Standard', $metadata['encryption']['filter']);
        $t->same(false, isset($metadata['encryption']['security_handler_declaration_review']));
        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same(['malformed_standard_security_handler_parameter_entries'], $permission['standard_security_handler_parameter_violations']);
        $t->same(['Filter'], $permission['standard_security_handler_malformed_parameter_names']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['permission_bits']);

        $t->same('Filter', $row['pdf_name'] ?? null);
        $t->same('filter', $row['metadata_key'] ?? null);
        $t->same('standard_security_handler_filter_non_name_operand_review', $row['selected_entry_status'] ?? null);
        $t->same('literal_string', $row['selected_entry_operand_shape'] ?? null);
        $t->same('indirect_reference', $row['selected_entry_raw_operand_shape'] ?? null);
        $t->same(true, $row['selected_entry_resolved'] ?? null);
        $t->same(9, $row['selected_entry_reference_object_number'] ?? null);
        $t->same(0, $row['selected_entry_reference_generation'] ?? null);
        $t->same(['literal_string'], $row['entry_operand_shapes'] ?? []);
        $t->same(['indirect_reference'], $row['entry_raw_operand_shapes'] ?? []);
        $t->same(['standard_security_handler_filter_non_name_operand_review'], $row['entry_statuses'] ?? []);

        $t->same('standard_security_handler_parameter_entry_review', $entry['source'] ?? null);
        $t->same('literal_string', $entry['operand_shape'] ?? null);
        $t->same('indirect_reference', $entry['raw_operand_shape'] ?? null);
        $t->same('standard_security_handler_filter_non_name_operand_review', $entry['status'] ?? null);
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
    'fails closed when Standard Filter resolves through an indirect composite operand without publishing a numeric handler' => static function (
        TestRunner $t
    ) use ($indirectFilterOperandPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $indirectFilterOperandPdf('[/Standard]', 'ARRAY');
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $declaration = $permission['security_handler_declaration_review'];
        $entry = $declaration['entries'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'security_handler_declaration_malformed',
            'malformed_security_handler_filter_entries',
        ], $report['review_reasons']);

        $t->same(null, $metadata['encryption']['filter'] ?? null);
        $t->same($metadata['encryption']['security_handler_declaration_review'], $declaration);
        $t->same('malformed_security_handler_filter_entries_review', $declaration['status']);
        $t->same(true, $declaration['fail_closed']);
        $t->same(null, $declaration['selected_filter_name']);
        $t->same([], $declaration['filter_names']);
        $t->same(['security_handler_filter_composite_operand_review'], $declaration['entry_statuses']);
        $t->same(['array'], $declaration['entry_operand_shapes']);
        $t->same(true, $entry['resolved'] ?? null);
        $t->same('array', $entry['operand_shape'] ?? null);
        $t->same(null, $entry['filter_name'] ?? null);

        $t->same('security_handler_declaration_malformed', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_security_handler_malformed', $permission['content_extraction_boundary']);
        $t->same(true, $permission['security_handler_declaration_fail_closed']);
        $t->same(true, $permission['security_handler_malformed_filter_entries']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['permission_bits']);

        $t->same(null, $handler['handler']);
        $t->same(false, $handler['standard_handler']);
        $t->same('malformed_security_handler_declaration_review', $handler['status']);
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
