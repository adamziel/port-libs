<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$duplicateTrailerEncryptPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate trailer Encrypt encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('D', 32);
    $userValidation = str_repeat('E', 32);
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
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
            . ' /O ' . $hex($ownerValidation)
            . ' /U ' . $hex($userValidation)
            . ' /P -44 /EncryptMetadata true >>'
    );

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . "trailer\n<< /Size 6 /Root 1 0 R /Encrypt null /Encrypt 5 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed when selected trailer declares duplicate Encrypt entries' => static function (
        TestRunner $t
    ) use ($duplicateTrailerEncryptPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $duplicateTrailerEncryptPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same(['encryption'], $metadata['source']);
        $t->same(true, $report['encrypted']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown', 'duplicate_encrypt_dictionary_entries'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same('trailer_encrypt', $encryption['source']);
        $t->same(true, $encryption['malformed_encrypt_dictionary']);
        $t->same(false, $encryption['encrypt_dictionary_resolved']);
        $t->same(true, $encryption['duplicate_encrypt_dictionary_entries']);
        $t->same(2, $encryption['encrypt_dictionary_declared_entry_count']);
        $t->same(1, $encryption['encrypt_dictionary_resolved_entry_count']);
        $t->same('duplicate_entries', $encryption['encrypt_operand_shape']);
        $t->same('duplicate_encrypt_dictionary_entries_review', $encryption['encrypt_operand_status']);
        $t->same(['encrypt_dictionary_explicit_null', 'encrypt_dictionary_entry_resolved'], $encryption['encrypt_dictionary_entry_statuses']);
        $t->same(['token', 'indirect_reference'], $encryption['encrypt_dictionary_entry_shapes']);
        $t->same(false, isset($encryption['standard_permissions']));
        $t->same(true, $encryption['requires_password_for_content_extraction']);

        $t->same('trailer_encrypt', $reviewEncryption['source']);
        $t->same(true, $reviewEncryption['malformed_encrypt_dictionary']);
        $t->same(false, $reviewEncryption['encrypt_dictionary_resolved']);
        $t->same(true, $reviewEncryption['duplicate_encrypt_dictionary_entries']);
        $t->same(2, $reviewEncryption['encrypt_dictionary_declared_entry_count']);
        $t->same('duplicate_encrypt_dictionary_entries_review', $reviewEncryption['encrypt_operand_status']);
        $t->same(null, $reviewEncryption['permission_hex']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same([], $reviewEncryption['allowed']);
        $t->same([], $reviewEncryption['permission_bits']);

        $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
        $t->same(true, $permission['encrypted']);
        $t->same(false, $permission['permissions_declared']);
        $t->same(false, $permission['standard_permissions_declared']);
        $t->same(true, $permission['malformed_encrypt_dictionary']);
        $t->same(true, $permission['duplicate_encrypt_dictionary_entries']);
        $t->same('permissions_unknown_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_unknown', $permission['content_extraction_boundary']);
        $t->same(null, $permission['permission_hex']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $permission['decryption_performed']);

        $t->same('permission_handler_review', $handler['source']);
        $t->same(null, $handler['handler']);
        $t->same(false, $handler['standard_handler']);
        $t->same(false, $handler['permissions_declared']);
        $t->same('permissions_unavailable_review', $handler['status']);
        $t->same(null, $handler['permission_word_well_formed']);
        $t->same(false, $handler['executes_decryption']);
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
            && !str_contains($encoded, strtoupper(bin2hex($userValidation)))
            && !str_contains($encoded, 'FFFFFFD4')
            && !str_contains($encoded, 'copy_extract_allowed_after_decryption'));
    },
];
