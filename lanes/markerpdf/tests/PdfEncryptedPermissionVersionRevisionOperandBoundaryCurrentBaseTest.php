<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$standardParameterOperandPdf = static function (
    string $versionOperand,
    string $revisionOperand,
    string $label
) use ($hex): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$label} Standard parameter operand text leak) Tj ET";
    $ownerValidation = str_repeat('V', 32);
    $userValidation = str_repeat('R', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V {$versionOperand} /R {$revisionOperand} /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$rowByName = static function (array $rows, string $pdfName): array {
    foreach ($rows as $row) {
        if (($row['pdf_name'] ?? null) === $pdfName) {
            return $row;
        }
    }

    return [];
};

$assertMalformedStandardParameterPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $parameterName,
    string $selectedStatus,
    string $selectedShape,
    ?string $trailingShape
) use ($rowByName): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $encryption = $metadata['encryption'];
    $permission = $report['permission_preflight'];
    $handler = $report['permission_handler_review'];
    $reviewEncryption = $report['encryption'];
    $parameterReview = $permission['standard_security_handler_parameter_review'];
    $declarationReview = $parameterReview['parameter_declaration_review'];
    $row = $rowByName($declarationReview['rows'], $parameterName);
    $entry = $row['entries'][0] ?? [];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $expectedTrailingShapes = $trailingShape === null ? [] : [$trailingShape];

    $t->same('', $plainText);
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same([
        'encrypted_document',
        'encrypted_text_extraction_blocked',
        'standard_security_handler_parameters_malformed',
        'standard_security_handler_parameter_operands_malformed',
    ], $report['review_reasons']);

    $t->same('Standard', $encryption['filter']);
    $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
    $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));

    $t->same('standard_security_handler_parameter_declaration_review', $declarationReview['source']);
    $t->same([$parameterName], $declarationReview['malformed_parameter_names']);
    $t->same('malformed_standard_security_handler_parameter_entries_review', $declarationReview['status']);
    $t->same(true, $declarationReview['fail_closed']);

    $t->same('standard_security_handler_parameter_declaration_row', $row['source'] ?? null);
    $t->same($parameterName, $row['pdf_name'] ?? null);
    $t->same(1, $row['declared_entry_count'] ?? null);
    $t->same(true, $row['malformed_entries'] ?? null);
    $t->same(1, $row['malformed_entry_count'] ?? null);
    $t->same($selectedStatus, $row['selected_entry_status'] ?? null);
    $t->same($selectedShape, $row['selected_entry_operand_shape'] ?? null);
    $t->same([$selectedStatus], $row['entry_statuses'] ?? []);
    $t->same([$selectedShape], $row['entry_operand_shapes'] ?? []);
    $t->same($selectedStatus, $entry['status'] ?? null);
    $t->same($selectedShape, $entry['operand_shape'] ?? null);
    $t->same($trailingShape !== null, $entry['trailing_operand'] ?? false);
    if ($trailingShape !== null) {
        $t->same($trailingShape, $entry['trailing_operand_shape'] ?? null);
    }

    $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
    $t->same(false, $parameterReview['parameters_well_formed']);
    $t->same(['malformed_standard_security_handler_parameter_entries'], $parameterReview['violations']);
    $t->same([$parameterName], $parameterReview['malformed_parameter_names']);
    $t->same([$selectedStatus], $parameterReview['malformed_parameter_statuses']);
    $t->same([$selectedShape], $parameterReview['malformed_parameter_operand_shapes']);
    $t->same($expectedTrailingShapes, $parameterReview['malformed_parameter_trailing_operand_shapes']);

    foreach ([$permission, $handler, $reviewEncryption] as $preflightProjection) {
        $t->same([$parameterName], $preflightProjection['standard_security_handler_malformed_parameter_names']);
        $t->same(1, $preflightProjection['standard_security_handler_malformed_parameter_count']);
        $t->same([$selectedStatus], $preflightProjection['standard_security_handler_malformed_parameter_statuses']);
        $t->same([$selectedShape], $preflightProjection['standard_security_handler_malformed_parameter_operand_shapes']);
        $t->same(
            $expectedTrailingShapes,
            $preflightProjection['standard_security_handler_malformed_parameter_trailing_operand_shapes']
        );
    }

    $t->same('standard_security_handler_malformed_parameters', $permission['source']);
    $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);
    $t->same('malformed_standard_security_handler_parameters_review', $handler['status']);
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
        && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
};

return [
    'fails closed when Standard handler V has a trailing indirect operand' => static function (
        TestRunner $t
    ) use ($standardParameterOperandPdf, $assertMalformedStandardParameterPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $standardParameterOperandPdf(
            '4 9 0 R',
            '4',
            'VERSION'
        );

        $assertMalformedStandardParameterPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'V',
            'standard_security_handler_parameter_trailing_operand_review',
            'token',
            'indirect_reference'
        );
    },
    'fails closed when Standard handler R is a composite operand' => static function (
        TestRunner $t
    ) use ($standardParameterOperandPdf, $assertMalformedStandardParameterPreflight): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $standardParameterOperandPdf(
            '4',
            '[4]',
            'REVISION'
        );

        $assertMalformedStandardParameterPreflight(
            $t,
            $pdf,
            $content,
            $ownerValidation,
            $userValidation,
            'R',
            'standard_security_handler_parameter_composite_operand_review',
            'array',
            null
        );
    },
];
