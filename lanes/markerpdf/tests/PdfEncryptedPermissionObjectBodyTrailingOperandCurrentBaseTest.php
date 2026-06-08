<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$encryptObjectBodyTrailingOperandPdf = static function () use ($hex): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Object body trailing Encrypt operand text leak) Tj ET';
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
        . " /P -44 /EncryptMetadata true >> 6 0 R\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'fails closed when referenced Encrypt object body has a trailing operand' => static function (
        TestRunner $t
    ) use ($encryptObjectBodyTrailingOperandPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $encryptObjectBodyTrailingOperandPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $reviewEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $boundary = $permission['encrypt_dictionary_permission_review'];
        $handler = $report['permission_handler_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'encryption_permissions_unknown',
            'encrypt_dictionary_trailing_operand',
        ], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same(true, $encryption['is_encrypted']);
        $t->same('trailer_encrypt', $encryption['source']);
        $t->same(5, $encryption['object_number']);
        $t->same(0, $encryption['object_generation']);
        $t->same(true, $encryption['malformed_encrypt_dictionary']);
        $t->same(false, $encryption['encrypt_dictionary_resolved']);
        $t->same('indirect_reference', $encryption['encrypt_operand_shape']);
        $t->same(false, $encryption['encrypt_operand_single_value']);
        $t->same('encrypt_dictionary_trailing_operand_review', $encryption['encrypt_operand_status']);
        $t->same(true, $encryption['encrypt_trailing_operand']);
        $t->same('indirect_reference', $encryption['encrypt_trailing_operand_shape']);
        $t->same('6 0 R', $encryption['encrypt_trailing_operand_preview']);
        $t->same(6, $encryption['encrypt_trailing_operand_object_number']);
        $t->same(0, $encryption['encrypt_trailing_operand_generation']);
        $t->same(false, isset($encryption['filter']));
        $t->same(false, isset($encryption['standard_permissions']));
        $t->same(true, $encryption['requires_password_for_content_extraction']);

        $t->same(true, $reviewEncryption['malformed_encrypt_dictionary']);
        $t->same(false, $reviewEncryption['encrypt_dictionary_resolved']);
        $t->same('indirect_reference', $reviewEncryption['encrypt_operand_shape']);
        $t->same(false, $reviewEncryption['encrypt_operand_single_value']);
        $t->same('encrypt_dictionary_trailing_operand_review', $reviewEncryption['encrypt_operand_status']);
        $t->same(true, $reviewEncryption['encrypt_trailing_operand']);
        $t->same('indirect_reference', $reviewEncryption['encrypt_trailing_operand_shape']);
        $t->same('6 0 R', $reviewEncryption['encrypt_trailing_operand_preview']);
        $t->same(6, $reviewEncryption['encrypt_trailing_operand_object_number']);
        $t->same(0, $reviewEncryption['encrypt_trailing_operand_generation']);
        $t->same(null, $reviewEncryption['filter']);
        $t->same(null, $reviewEncryption['permission_hex']);
        $t->same(null, $reviewEncryption['copy_or_extract_allowed']);
        $t->same(false, $reviewEncryption['permission_bits_reliable']);
        $t->same([], $reviewEncryption['allowed']);
        $t->same([], $reviewEncryption['permission_bits']);

        $t->same('encryption_dictionary_without_standard_permissions', $permission['source']);
        $t->same(false, $permission['permissions_declared']);
        $t->same(false, $permission['standard_permissions_declared']);
        $t->same(false, $permission['standard_permission_bits_decoded']);
        $t->same('permissions_unknown_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_permissions_unknown', $permission['content_extraction_boundary']);
        $t->same(true, $permission['malformed_encrypt_dictionary']);
        $t->same(false, $permission['encrypt_dictionary_resolved']);
        $t->same('encrypt_dictionary_trailing_operand_review', $permission['encrypt_operand_status']);
        $t->same(null, $permission['permission_hex']);
        $t->same(null, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['permission_bits_reliable']);
        $t->same([], $permission['allowed']);
        $t->same([], $permission['denied']);
        $t->same([], $permission['permission_bits']);
        $t->same(0, $permission['permission_bit_review_count']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('encrypt_dictionary_permission_preflight', $boundary['source']);
        $t->same(true, $boundary['present']);
        $t->same(true, $boundary['malformed_encrypt_dictionary']);
        $t->same(false, $boundary['encrypt_dictionary_resolved']);
        $t->same('encrypt_dictionary_trailing_operand_review', $boundary['encrypt_operand_status']);
        $t->same('malformed_trailing_encrypt_operand', $boundary['status']);
        $t->same(true, $boundary['fail_closed']);
        $t->same('indirect_reference', $boundary['encrypt_trailing_operand_shape']);
        $t->same('6 0 R', $boundary['encrypt_trailing_operand_preview']);
        $t->same(6, $boundary['encrypt_trailing_operand_object_number']);
        $t->same(0, $boundary['encrypt_trailing_operand_generation']);
        $t->same(false, $boundary['permission_bits_reliable']);
        $t->same(false, $boundary['decryption_performed']);
        $t->same(false, $boundary['executes_permission_enforcement']);
        $t->same(false, $boundary['executes_external_pdf_tools']);

        $t->same('permission_handler_review', $handler['source']);
        $t->same(null, $handler['handler']);
        $t->same(false, $handler['standard_handler']);
        $t->same(false, $handler['permissions_declared']);
        $t->same('permissions_unavailable_review', $handler['status']);
        $t->same(null, $handler['permission_word_well_formed']);

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
            && !str_contains($encoded, 'CAFEFEED')
            && !str_contains($encoded, 'FFFFFFD4')
            && !str_contains($encoded, 'copy_extract_allowed_after_decryption'));
    },
    'keeps trailing Encrypt object body payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($encryptObjectBodyTrailingOperandPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $encryptObjectBodyTrailingOperandPdf();
        $extractor = new PdfTextExtractor();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $extractor->extractPlainText($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same(false, $report['content_extraction_allowed']);
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
    },
];
