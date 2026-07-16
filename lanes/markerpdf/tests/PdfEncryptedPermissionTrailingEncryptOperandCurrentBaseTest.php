<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$trailingEncryptClassicTrailerPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Trailing Encrypt operand text leak) Tj ET';
    $ownerValidation = str_repeat('O', 32);
    $userValidation = str_repeat('U', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O " . $hex($ownerValidation)
        . " /U " . $hex($userValidation)
        . " /P -44 /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R 6 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, 'trailer_encrypt'];
};

$trailingEncryptXrefStreamTrailerPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Xref stream trailing Encrypt operand text leak) Tj ET';
    $ownerValidation = str_repeat('X', 32);
    $userValidation = str_repeat('S', 32);
    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . pack('n', $fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(
        5,
        '<< /Filter /Standard /V 4 /R 4 /Length 128'
            . ' /O ' . $hex($ownerValidation)
            . ' /U ' . $hex($userValidation)
            . ' /P -44 /EncryptMetadata true >>'
    );
    $addObject(6, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>');

    $xrefOffset = strlen($pdf);
    $xrefRows = '';
    for ($objectNumber = 0; $objectNumber <= 7; $objectNumber++) {
        if ($objectNumber === 0) {
            $xrefRows .= $row(0, 0, 65535);
            continue;
        }

        $xrefRows .= $row(1, $objectNumber === 7 ? $xrefOffset : ($offsets[$objectNumber] ?? 0), 0);
    }
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress trailing Encrypt xref stream rows.');
    }

    $pdf .= "7 0 obj\n"
        . '<< /Type /XRef /Size 8 /Root 1 0 R /Encrypt 5 0 R 6 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation, 'xref_stream_trailer_encrypt'];
};

$assertTrailingEncryptOperandPreflight = static function (
    TestRunner $t,
    string $pdf,
    string $content,
    string $ownerValidation,
    string $userValidation,
    string $expectedSource
): void {
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $encryption = $metadata['encryption'];
    $reviewEncryption = $report['encryption'];
    $permission = $report['permission_preflight'];
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', $plainText);
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same(
        ['encrypted_document', 'encrypted_text_extraction_blocked', 'encryption_permissions_unknown', 'encrypt_dictionary_trailing_operand'],
        $report['review_reasons']
    );

    $t->same(true, $encryption['is_encrypted']);
    $t->same($expectedSource, $encryption['source']);
    $t->same(true, $encryption['malformed_encrypt_dictionary']);
    $t->same(false, $encryption['encrypt_dictionary_resolved']);
    $t->same(5, $encryption['object_number']);
    $t->same(0, $encryption['object_generation']);
    $t->same('indirect_reference', $encryption['encrypt_operand_shape']);
    $t->same('encrypt_dictionary_trailing_operand_review', $encryption['encrypt_operand_status']);
    $t->same(false, $encryption['encrypt_operand_single_value']);
    $t->same(true, $encryption['encrypt_trailing_operand']);
    $t->same('indirect_reference', $encryption['encrypt_trailing_operand_shape']);
    $t->same('6 0 R', $encryption['encrypt_trailing_operand_preview']);
    $t->same(6, $encryption['encrypt_trailing_operand_object_number']);
    $t->same(0, $encryption['encrypt_trailing_operand_generation']);
    $t->same(false, isset($encryption['filter']));
    $t->same(false, isset($encryption['standard_permissions']));
    $t->same(false, isset($encryption['standard_permission_word_review']));

    $t->same(true, $reviewEncryption['malformed_encrypt_dictionary']);
    $t->same(false, $reviewEncryption['encrypt_dictionary_resolved']);
    $t->same('encrypt_dictionary_trailing_operand_review', $reviewEncryption['encrypt_operand_status']);
    $t->same(false, $reviewEncryption['encrypt_operand_single_value']);
    $t->same(true, $reviewEncryption['encrypt_trailing_operand']);
    $t->same('indirect_reference', $reviewEncryption['encrypt_trailing_operand_shape']);
    $t->same('6 0 R', $reviewEncryption['encrypt_trailing_operand_preview']);
    $t->same(6, $reviewEncryption['encrypt_trailing_operand_object_number']);
    $t->same(null, $reviewEncryption['permission_hex']);
    $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
    $t->same(false, $reviewEncryption['permission_bits_reliable']);

    $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
    $t->same(false, $permission['permissions_declared']);
    $t->same(false, $permission['standard_permissions_declared']);
    $t->same(false, $permission['standard_permission_bits_decoded']);
    $t->same('permissions_unknown_blocked_without_decryption', $permission['policy']);
    $t->same('blocked_encrypted_permissions_unknown', $permission['content_extraction_boundary']);
    $t->same(null, $permission['permission_hex']);
    $t->same(null, $permission['copy_or_extract_allowed']);
    $t->same(false, $permission['permission_bits_reliable']);
    $t->same([], $permission['allowed']);
    $t->same([], $permission['denied']);
    $t->same([], $permission['permission_bits']);
    $t->same(0, $permission['permission_bit_review_count']);
    $t->same(false, $permission['native_text_extraction_allowed_now']);

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
        && !str_contains($encoded, 'DEADBEEF')
        && !str_contains($encoded, 'CAFEFEED'));
};

return [
    'fails closed when classic trailer Encrypt reference has a trailing operand' => static function (
        TestRunner $t
    ) use ($trailingEncryptClassicTrailerPdf, $assertTrailingEncryptOperandPreflight): void {
        $assertTrailingEncryptOperandPreflight($t, ...$trailingEncryptClassicTrailerPdf());
    },
    'fails closed when xref-stream trailer Encrypt reference has a trailing operand' => static function (
        TestRunner $t
    ) use ($trailingEncryptXrefStreamTrailerPdf, $assertTrailingEncryptOperandPreflight): void {
        $assertTrailingEncryptOperandPreflight($t, ...$trailingEncryptXrefStreamTrailerPdf());
    },
];
