<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$generationSelectedPermissionWordPdf = static function (int $referenceGeneration): array {
    $content = "BT /F1 12 Tf 72 720 Td (Generation {$referenceGeneration} permission word encrypted leak) Tj ET";
    $ownerValidation = str_repeat('G', 32);
    $userValidation = str_repeat('P', 32);

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
        '<< /Filter /Standard /V 4 /R 4 /Length 128'
            . ' /O <' . strtoupper(bin2hex($ownerValidation)) . '>'
            . ' /U <' . strtoupper(bin2hex($userValidation)) . '>'
            . " /P 18 {$referenceGeneration} R /EncryptMetadata true >>"
    );
    $addObject(18, 0, '-64');
    $addObject(18, 1, '-44');

    $xrefOffset = strlen($pdf);
    $row = static fn (int $offset, int $generation, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 19\n";
    for ($objectNumber = 0; $objectNumber <= 18; $objectNumber++) {
        if ($objectNumber === 0) {
            $pdf .= $row(0, 65535, 'f');
            continue;
        }

        if ($objectNumber === 18) {
            $pdf .= $row($offsets[18][1], 1);
            continue;
        }

        if (isset($offsets[$objectNumber][0])) {
            $pdf .= $row($offsets[$objectNumber][0], 0);
            continue;
        }

        $pdf .= $row(0, 65535, 'f');
    }
    $pdf .= "trailer\n<< /Size 19 /Root 1 0 R /Encrypt 5 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'records generation-selected Standard permission word operand provenance before policy review' => static function (
        TestRunner $t
    ) use ($generationSelectedPermissionWordPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $generationSelectedPermissionWordPdf(1);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $declaration = $permission['standard_permission_word_review'];
        $entry = $declaration['entries'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('FFFFFFD4', $metadata['encryption']['standard_permissions']['hex']);
        $t->same(-44, $metadata['encryption']['standard_permissions']['signed']);
        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(false, in_array('copy_or_extract', $permission['denied'], true));

        $t->same('standard_permission_word_declaration_review', $declaration['source']);
        $t->same(1, $declaration['declared_entry_count']);
        $t->same(1, $declaration['integer_entry_count']);
        $t->same('well_formed_standard_permissions', $declaration['status']);
        $t->same(true, $declaration['selected_entry_resolved']);
        $t->same(true, $declaration['selected_entry_integer']);
        $t->same('token', $declaration['selected_entry_operand_shape']);
        $t->same('indirect_reference', $declaration['selected_entry_raw_operand_shape']);
        $t->same(18, $declaration['selected_entry_reference_object_number']);
        $t->same(1, $declaration['selected_entry_reference_generation']);
        $t->same('FFFFFFD4', $declaration['selected_permission_hex']);

        $t->same('standard_permission_word_entry_review', $entry['source'] ?? null);
        $t->same(true, $entry['resolved'] ?? null);
        $t->same(true, $entry['integer'] ?? null);
        $t->same('token', $entry['operand_shape'] ?? null);
        $t->same('indirect_reference', $entry['raw_operand_shape'] ?? null);
        $t->same(18, $entry['reference_object_number'] ?? null);
        $t->same(1, $entry['reference_generation'] ?? null);
        $t->same(18, $entry['resolved_object_number'] ?? null);
        $t->same(1, $entry['resolved_generation'] ?? null);
        $t->same('FFFFFFD4', $entry['hex'] ?? null);

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
    'fails closed when Standard permission word references a stale object generation' => static function (
        TestRunner $t
    ) use ($generationSelectedPermissionWordPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $generationSelectedPermissionWordPdf(0);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $declaration = $permission['standard_permission_word_review'];
        $entry = $declaration['entries'][0] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'permission_word_unresolved_reference'], $report['review_reasons']);
        $t->same(false, isset($metadata['encryption']['standard_permissions']));
        $t->same('standard_security_handler_malformed_permissions', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_malformed', $permission['content_extraction_boundary']);
        $t->same(false, $permission['permission_word_well_formed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(null, $permission['copy_or_extract_allowed']);

        $t->same('malformed_standard_permission_word_review', $declaration['status']);
        $t->same(['permission_word_unresolved_reference'], $declaration['entry_statuses']);
        $t->same(false, $declaration['selected_entry_resolved']);
        $t->same(false, $declaration['selected_entry_integer']);
        $t->same('indirect_reference', $declaration['selected_entry_operand_shape']);
        $t->same('indirect_reference', $declaration['selected_entry_raw_operand_shape']);
        $t->same(18, $declaration['selected_entry_reference_object_number']);
        $t->same(0, $declaration['selected_entry_reference_generation']);
        $t->same(null, $declaration['selected_permission_hex']);

        $t->same('standard_permission_word_entry_review', $entry['source'] ?? null);
        $t->same(false, $entry['resolved'] ?? null);
        $t->same(false, $entry['integer'] ?? null);
        $t->same('indirect_reference', $entry['operand_shape'] ?? null);
        $t->same('indirect_reference', $entry['raw_operand_shape'] ?? null);
        $t->same(18, $entry['reference_object_number'] ?? null);
        $t->same(0, $entry['reference_generation'] ?? null);
        $t->same(false, isset($entry['resolved_object_number']));
        $t->same(false, isset($entry['resolved_generation']));

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
