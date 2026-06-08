<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$scalarEncryptOperandPdf = static function (string $currentEncryptOperand, string $expectedShape): array {
    $content = "BT /F1 12 Tf 72 720 Td ({$expectedShape} scalar Encrypt operand text leak) Tj ET";
    $staleOwner = str_repeat('Q', 32);
    $staleUser = str_repeat('R', 32);
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
        . "trailer\n<< /Size 100 /Root 1 0 R /Encrypt {$currentEncryptOperand} /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $content, $staleOwner, $staleUser, $expectedShape];
};

return [
    'reports scalar non-dictionary Encrypt operands before stale Prev permissions' => static function (
        TestRunner $t
    ) use ($scalarEncryptOperandPdf): void {
        foreach ([
            'BOOLEAN' => ['false', 'token'],
            'NAME' => ['/NotAnEncryptDictionary', 'name'],
        ] as $case) {
            [$pdf, $content, $staleOwner, $staleUser, $expectedShape] = $scalarEncryptOperandPdf($case[0], $case[1]);
            $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
            $report = (new PdfSecurityPreflight())->analyze($pdf);
            $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
            $encryption = $metadata['encryption'];
            $permission = $report['permission_preflight'];
            $handler = $report['permission_handler_review'];
            $review = $report['encryption'];
            $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

            $t->same('', $plainText);
            $t->same(true, $report['encrypted']);
            $t->same(false, $report['content_extraction_allowed']);
            $t->same('blocked_without_decryption', $report['text_extraction_policy']);
            $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
            $t->same([
                'encrypted_document',
                'encrypted_text_extraction_blocked',
                'encryption_permissions_unknown',
                'encrypt_dictionary_non_dictionary_operand',
            ], $report['review_reasons']);
            $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

            $t->same('trailer_encrypt', $encryption['source']);
            $t->same(true, $encryption['malformed_encrypt_dictionary']);
            $t->same(false, $encryption['encrypt_dictionary_resolved']);
            $t->same($expectedShape, $encryption['encrypt_operand_shape']);
            $t->same('encrypt_dictionary_non_dictionary_operand', $encryption['encrypt_operand_status']);
            $t->same(false, isset($encryption['standard_permissions']));
            $t->same(true, $encryption['requires_password_for_content_extraction']);

            $t->same($expectedShape, $review['encrypt_operand_shape']);
            $t->same('encrypt_dictionary_non_dictionary_operand', $review['encrypt_operand_status']);
            $t->same(null, $review['permission_hex']);
            $t->same(null, $review['copy_or_extract_allowed']);
            $t->same(false, $review['permission_bits_reliable']);
            $t->same([], $review['allowed']);
            $t->same([], $review['permission_bits']);

            $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
            $t->same('permissions_unknown_blocked_without_decryption', $permission['policy']);
            $t->same('blocked_encrypted_permissions_unknown', $permission['content_extraction_boundary']);
            $t->same('encrypt_dictionary_non_dictionary_operand', $permission['encrypt_operand_status']);
            $t->same($expectedShape, $permission['encrypt_operand_shape']);
            $t->same(null, $permission['permission_hex']);
            $t->same(null, $permission['copy_or_extract_allowed']);
            $t->same(false, $permission['permission_bits_reliable']);
            $t->same(false, $permission['native_text_extraction_allowed_now']);

            $t->same('permission_handler_review', $handler['source']);
            $t->same(null, $handler['handler']);
            $t->same(false, $handler['standard_handler']);
            $t->same(false, $handler['permissions_declared']);
            $t->same('permissions_unavailable_review', $handler['status']);
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
        }
    },
];
