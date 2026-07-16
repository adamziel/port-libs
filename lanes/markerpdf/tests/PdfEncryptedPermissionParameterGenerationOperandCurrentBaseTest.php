<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedParameterGenerationPdf = static function (int $parameterGeneration): array {
    $content = "BT /F1 12 Tf 72 720 Td (Generation {$parameterGeneration} security parameter encrypted leak) Tj ET";
    $ownerValidation = str_repeat('S', 32);
    $userValidation = str_repeat('P', 32);
    $hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber][$generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(
        5,
        0,
        '<< /Filter /Standard /V 7 ' . $parameterGeneration . ' R /R 8 ' . $parameterGeneration . ' R'
            . ' /Length 9 ' . $parameterGeneration . ' R'
            . ' /O ' . $hex($ownerValidation)
            . ' /U ' . $hex($userValidation)
            . ' /P -44 /EncryptMetadata true >>'
    );
    $addObject(7, 0, '2');
    $addObject(7, 1, '4');
    $addObject(8, 0, '3');
    $addObject(8, 1, '4');
    $addObject(9, 0, '40');
    $addObject(9, 1, '128');

    $xrefOffset = strlen($pdf);
    $row = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 10\n";
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        if ($objectNumber === 0) {
            $pdf .= $row(0, 65535, 'f');
            continue;
        }

        if (in_array($objectNumber, [7, 8, 9], true)) {
            $pdf .= $row($offsets[$objectNumber][1], 1);
            continue;
        }

        if (!isset($offsets[$objectNumber][0])) {
            $pdf .= $row(0, 65535, 'f');
            continue;
        }

        $pdf .= $row($offsets[$objectNumber][0], 0);
    }
    $pdf .= "trailer\n<< /Size 10 /Root 1 0 R /Encrypt 5 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

$parameterRow = static function (array $parameterReview, string $pdfName): array {
    foreach ($parameterReview['parameter_declaration_review']['rows'] ?? [] as $row) {
        if (($row['pdf_name'] ?? null) === $pdfName) {
            return $row;
        }
    }

    throw new RuntimeException("Missing Standard security-handler parameter row for {$pdfName}.");
};

$assertSelectedParameterGeneration = static function (
    TestRunner $t,
    array $parameterReview,
    string $pdfName,
    int $objectNumber,
    int $expectedInteger
) use ($parameterRow): void {
    $row = $parameterRow($parameterReview, $pdfName);
    $entry = $row['entries'][0] ?? [];

    $t->same('standard_security_handler_parameter_declaration_row', $row['source'] ?? null);
    $t->same($pdfName, $row['pdf_name'] ?? null);
    $t->same(1, $row['declared_entry_count'] ?? null);
    $t->same(false, $row['duplicate_entries'] ?? null);
    $t->same(0, $row['selected_entry_index'] ?? null);
    $t->same('standard_security_handler_parameter_entry_well_formed', $row['selected_entry_status'] ?? null);
    $t->same('token', $row['selected_entry_operand_shape'] ?? null);
    $t->same('indirect_reference', $row['selected_entry_raw_operand_shape'] ?? null);
    $t->same(true, $row['selected_entry_resolved'] ?? null);
    $t->same(true, $row['selected_entry_integer'] ?? null);
    $t->same($expectedInteger, $row['selected_integer_value'] ?? null);
    $t->same($objectNumber, $row['selected_entry_reference_object_number'] ?? null);
    $t->same(1, $row['selected_entry_reference_generation'] ?? null);
    $t->same($objectNumber, $row['selected_entry_resolved_object_number'] ?? null);
    $t->same(1, $row['selected_entry_resolved_generation'] ?? null);
    $t->same(['token'], $row['entry_operand_shapes'] ?? []);
    $t->same(['indirect_reference'], $row['entry_raw_operand_shapes'] ?? []);
    $t->same(['standard_security_handler_parameter_entry_well_formed'], $row['entry_statuses'] ?? []);
    $t->same(false, $row['malformed_entries'] ?? null);

    $t->same('standard_security_handler_parameter_entry_review', $entry['source'] ?? null);
    $t->same(true, $entry['resolved'] ?? null);
    $t->same('token', $entry['operand_shape'] ?? null);
    $t->same('indirect_reference', $entry['raw_operand_shape'] ?? null);
    $t->same(true, $entry['integer'] ?? null);
    $t->same($expectedInteger, $entry['integer_value'] ?? null);
    $t->same($objectNumber, $entry['reference_object_number'] ?? null);
    $t->same(1, $entry['reference_generation'] ?? null);
    $t->same($objectNumber, $entry['resolved_object_number'] ?? null);
    $t->same(1, $entry['resolved_generation'] ?? null);
};

return [
    'records generation-selected Standard security handler scalar parameter provenance before permission review' => static function (
        TestRunner $t
    ) use ($encryptedParameterGenerationPdf, $assertSelectedParameterGeneration): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $encryptedParameterGenerationPdf(1);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same('Standard', $metadata['encryption']['filter']);
        $t->same(4, $metadata['encryption']['version']);
        $t->same(4, $metadata['encryption']['revision']);
        $t->same(128, $metadata['encryption']['key_length_bits']);
        $t->same('FFFFFFD4', $metadata['encryption']['standard_permissions']['hex']);

        $t->same('standard_security_handler_parameter_review', $parameterReview['source']);
        $t->same(true, $parameterReview['parameters_well_formed']);
        $t->same([], $parameterReview['violations']);
        $t->same(true, $parameterReview['version_revision_compatible']);
        $t->same(true, $parameterReview['key_length_valid']);
        $t->same('standard_security_handler_key_length_supported', $parameterReview['key_length_status']);
        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);

        $assertSelectedParameterGeneration($t, $parameterReview, 'V', 7, 4);
        $assertSelectedParameterGeneration($t, $parameterReview, 'R', 8, 4);
        $assertSelectedParameterGeneration($t, $parameterReview, 'Length', 9, 128);

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
    'fails closed when Standard security handler scalar parameters reference stale generations' => static function (
        TestRunner $t
    ) use ($encryptedParameterGenerationPdf, $parameterRow): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $encryptedParameterGenerationPdf(0);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $parameterReview = $permission['standard_security_handler_parameter_review'];
        $versionRow = $parameterRow($parameterReview, 'V');
        $revisionRow = $parameterRow($parameterReview, 'R');
        $lengthRow = $parameterRow($parameterReview, 'Length');
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'standard_security_handler_parameters_malformed',
            'standard_security_handler_parameter_operands_malformed',
        ], $report['review_reasons']);
        $t->same(false, isset($metadata['encryption']['version']));
        $t->same(false, isset($metadata['encryption']['revision']));
        $t->same(false, isset($metadata['encryption']['key_length_bits']));
        $t->same('standard_security_handler_malformed_parameters', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['standard_security_handler_parameters_well_formed']);
        $t->same(['malformed_standard_security_handler_parameter_entries'], $permission['standard_security_handler_parameter_violations']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);

        foreach ([
            [$versionRow, 'V', 7],
            [$revisionRow, 'R', 8],
            [$lengthRow, 'Length', 9],
        ] as [$row, $pdfName, $objectNumber]) {
            $entry = $row['entries'][0] ?? [];

            $t->same($pdfName, $row['pdf_name'] ?? null);
            $t->same('standard_security_handler_parameter_unresolved_reference', $row['selected_entry_status'] ?? null);
            $t->same('indirect_reference', $row['selected_entry_operand_shape'] ?? null);
            $t->same('indirect_reference', $row['selected_entry_raw_operand_shape'] ?? null);
            $t->same(false, $row['selected_entry_resolved'] ?? null);
            $t->same(false, $row['selected_entry_integer'] ?? null);
            $t->same($objectNumber, $row['selected_entry_reference_object_number'] ?? null);
            $t->same(0, $row['selected_entry_reference_generation'] ?? null);
            $t->same(null, $row['selected_entry_resolved_object_number'] ?? null);
            $t->same(null, $row['selected_entry_resolved_generation'] ?? null);
            $t->same(['indirect_reference'], $row['entry_operand_shapes'] ?? []);
            $t->same(['indirect_reference'], $row['entry_raw_operand_shapes'] ?? []);

            $t->same(false, $entry['resolved'] ?? null);
            $t->same(false, $entry['integer'] ?? null);
            $t->same($objectNumber, $entry['reference_object_number'] ?? null);
            $t->same(0, $entry['reference_generation'] ?? null);
            $t->same(false, isset($entry['resolved_object_number']));
            $t->same(false, isset($entry['resolved_generation']));
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
