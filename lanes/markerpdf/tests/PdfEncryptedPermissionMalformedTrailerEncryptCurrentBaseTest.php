<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$malformedTrailerEncryptPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Malformed current trailer Encrypt text leak) Tj ET';
    $staleOwner = str_repeat('S', 32);
    $staleUser = str_repeat('T', 32);
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
            . ' /O ' . $hex($staleOwner)
            . ' /U ' . $hex($staleUser)
            . ' /P -44 /EncryptMetadata true >>'
    );

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . "trailer\n<< /Size 100 /Root 1 0 R /Encrypt 5 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 1\n"
        . $xrefRow(0, 65535, 'f')
        . "trailer\n<< /Size 100 /Root 1 0 R /Encrypt 99 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $content, $staleOwner, $staleUser];
};

return [
    'fails closed when current trailer Encrypt is unresolved before stale Prev permissions' => static function (
        TestRunner $t
    ) use ($malformedTrailerEncryptPdf): void {
        [$pdf, $content, $staleOwner, $staleUser] = $malformedTrailerEncryptPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same(['encryption'], $metadata['source']);
        $t->same(true, $report['encrypted']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same('trailer_encrypt', $encryption['source']);
        $t->same(99, $encryption['object_number']);
        $t->same(0, $encryption['object_generation']);
        $t->same(true, $encryption['malformed_encrypt_dictionary']);
        $t->same(false, $encryption['encrypt_dictionary_resolved']);
        $t->same('indirect_reference', $encryption['encrypt_operand_shape']);
        $t->same('encrypt_dictionary_unresolved_reference', $encryption['encrypt_operand_status']);
        $t->same(false, isset($encryption['standard_permissions']));
        $t->same(true, $encryption['requires_password_for_content_extraction']);

        $t->same('trailer_encrypt', $reviewEncryption['source']);
        $t->same(99, $reviewEncryption['object_number']);
        $t->same(0, $reviewEncryption['object_generation']);
        $t->same(null, $reviewEncryption['filter']);
        $t->same(null, $reviewEncryption['permission_hex']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same([], $reviewEncryption['allowed']);
        $t->same([], $reviewEncryption['permission_bits']);
        $t->same(true, $reviewEncryption['requires_password_for_content_extraction']);

        $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
        $t->same(true, $permission['encrypted']);
        $t->same(false, $permission['permissions_declared']);
        $t->same(false, $permission['standard_permissions_declared']);
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
            && !str_contains($encoded, $staleOwner)
            && !str_contains($encoded, $staleUser)
            && !str_contains($encoded, strtoupper(bin2hex($staleOwner)))
            && !str_contains($encoded, strtoupper(bin2hex($staleUser)))
            && !str_contains($encoded, 'FFFFFFD4')
            && !str_contains($encoded, 'copy_extract_allowed_after_decryption'));
    },
];
