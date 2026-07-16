<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$duplicateFilterHandlerPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate Filter handler encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /Filter /VendorSecurity /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed when selected Encrypt dictionary duplicates security-handler Filter entries' => static function (
        TestRunner $t
    ) use ($duplicateFilterHandlerPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicateFilterHandlerPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $metadataEncryption = $metadata['encryption'];
        $reportEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $declaration = $permission['security_handler_declaration_review'];
        $firstEntry = $declaration['entries'][0] ?? [];
        $secondEntry = $declaration['entries'][1] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'security_handler_declaration_malformed',
            'duplicate_security_handler_filter_entries',
        ], $report['review_reasons']);

        $t->same('VendorSecurity', $metadataEncryption['filter']);
        $t->same('security_handler_declaration_review', $metadataEncryption['security_handler_declaration_review']['source']);
        $t->same($metadataEncryption['security_handler_declaration_review'], $declaration);
        $t->same($declaration, $reportEncryption['security_handler_declaration_review']);
        $t->same('duplicate_security_handler_filter_entries_review', $declaration['status']);
        $t->same(2, $declaration['declared_entry_count']);
        $t->same(true, $declaration['duplicate_entries']);
        $t->same(false, $declaration['malformed_entries']);
        $t->same(true, $declaration['ambiguous']);
        $t->same(true, $declaration['fail_closed']);
        $t->same(1, $declaration['selected_entry_index']);
        $t->same('VendorSecurity', $declaration['selected_filter_name']);
        $t->same(['Standard', 'VendorSecurity'], $declaration['filter_names']);
        $t->same(['security_handler_filter_name'], $declaration['entry_statuses']);
        $t->same(['name'], $declaration['entry_operand_shapes']);
        $t->same('Standard', $firstEntry['filter_name'] ?? null);
        $t->same('VendorSecurity', $secondEntry['filter_name'] ?? null);
        $t->same(false, $firstEntry['executes_decryption'] ?? null);
        $t->same(false, $secondEntry['executes_decryption'] ?? null);
        $t->same(false, $firstEntry['executes_permission_enforcement'] ?? null);
        $t->same(false, $secondEntry['executes_permission_enforcement'] ?? null);

        $t->same('security_handler_declaration_malformed', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_security_handler_malformed', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['standard_permissions_declared']);
        $t->same(true, $permission['standard_permission_bits_decoded']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(-44, $permission['permission_signed']);
        $t->same(4294967252, $permission['permission_unsigned']);
        $t->same(true, $permission['security_handler_declaration_fail_closed']);
        $t->same(true, $permission['security_handler_duplicate_filter_entries']);
        $t->same(false, $permission['security_handler_malformed_filter_entries']);
        $t->same(2, $permission['security_handler_filter_declared_entry_count']);
        $t->same(['Standard', 'VendorSecurity'], $permission['security_handler_filter_names']);
        $t->same(['security_handler_filter_name'], $permission['security_handler_filter_entry_statuses']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(null, $permission['accessibility_extract_allowed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);
        $t->same(0, $permission['permission_bit_review_count']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('permission_handler_review', $handler['source']);
        $t->same('VendorSecurity', $handler['handler']);
        $t->same(false, $handler['standard_handler']);
        $t->same(false, $handler['handler_supported_for_native_permission_review']);
        $t->same('malformed_security_handler_declaration_review', $handler['status']);
        $t->same(false, $handler['permission_word_well_formed']);
        $t->same(true, $handler['security_handler_declaration_fail_closed']);
        $t->same(true, $handler['security_handler_duplicate_filter_entries']);
        $t->same(['Standard', 'VendorSecurity'], $handler['security_handler_filter_names']);
        $t->same(false, $handler['executes_permission_enforcement']);

        $t->same(true, $reportEncryption['security_handler_declaration_fail_closed']);
        $t->same(true, $reportEncryption['security_handler_duplicate_filter_entries']);
        $t->same(false, $reportEncryption['security_handler_malformed_filter_entries']);
        $t->same(['Standard', 'VendorSecurity'], $reportEncryption['security_handler_filter_names']);
        $t->same(false, $reportEncryption['permission_bits_reliable']);
        $t->same(null, $reportEncryption['copy_or_extract_allowed']);
        $t->same([], $reportEncryption['allowed']);
        $t->same([], $reportEncryption['permission_bits']);

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
