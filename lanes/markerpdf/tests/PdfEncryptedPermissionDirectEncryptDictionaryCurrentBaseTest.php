<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$directEncryptDictionaryPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Direct trailer Encrypt encrypted text leak) Tj ET';
    $ownerValidation = str_repeat('D', 32);
    $userValidation = str_repeat('T', 32);
    $ownerHex = strtoupper(bin2hex($ownerValidation));
    $userHex = strtoupper(bin2hex($userValidation));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt << /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <{$ownerHex}> /U <{$userHex}> /P -44 /EncryptMetadata true >> >>\n%%EOF";

    return [$pdf, $content, $ownerValidation, $userValidation];
};

return [
    'resolves direct trailer Encrypt dictionaries before Standard permission preflight' => static function (
        TestRunner $t
    ) use ($directEncryptDictionaryPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $directEncryptDictionaryPdf();
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
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);

        $t->same('trailer_encrypt', $encryption['source']);
        $t->same(false, isset($encryption['object_number']));
        $t->same(true, $encryption['encrypt_dictionary_resolved']);
        $t->same('dictionary', $encryption['encrypt_operand_shape']);
        $t->same('encrypt_dictionary_direct_dictionary_resolved', $encryption['encrypt_operand_status']);
        $t->same(false, $encryption['malformed_encrypt_dictionary'] ?? false);
        $t->same('Standard', $encryption['filter']);
        $t->same(4, $encryption['version']);
        $t->same(4, $encryption['revision']);
        $t->same('standard_handler_revision_4', $encryption['revision_label']);
        $t->same(128, $encryption['key_length_bits']);
        $t->same('FFFFFFD4', $encryption['standard_permissions']['hex']);
        $t->same(true, $encryption['standard_permissions']['reserved_bits_valid']);
        $t->same(true, in_array('copy_or_extract', $encryption['standard_permissions']['allowed'], true));

        $t->same(true, $reviewEncryption['encrypt_dictionary_resolved']);
        $t->same('dictionary', $reviewEncryption['encrypt_operand_shape']);
        $t->same('encrypt_dictionary_direct_dictionary_resolved', $reviewEncryption['encrypt_operand_status']);
        $t->same(false, $reviewEncryption['malformed_encrypt_dictionary']);
        $t->same('Standard', $reviewEncryption['filter']);
        $t->same('FFFFFFD4', $reviewEncryption['permission_hex']);
        $t->same(true, $reviewEncryption['permission_bits_reliable']);
        $t->same(true, $reviewEncryption['copy_or_extract_allowed']);

        $t->same('standard_security_handler_permissions', $permission['source']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['standard_permissions_declared']);
        $t->same(true, $permission['standard_permission_bits_decoded']);
        $t->same(true, $permission['encrypt_dictionary_resolved']);
        $t->same('dictionary', $permission['encrypt_operand_shape']);
        $t->same('encrypt_dictionary_direct_dictionary_resolved', $permission['encrypt_operand_status']);
        $t->same(false, $permission['malformed_encrypt_dictionary']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same('FFFFFFD4', $permission['permission_hex']);
        $t->same(-44, $permission['permission_signed']);
        $t->same(4294967252, $permission['permission_unsigned']);
        $t->same(true, $permission['permission_word_well_formed']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('well_formed_standard_permissions', $handler['status']);
        $t->same(true, $handler['standard_handler']);
        $t->same(true, $handler['handler_supported_for_native_permission_review']);
        $t->same(true, $handler['permission_word_well_formed']);
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
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
    'keeps direct trailer Encrypt dictionary text blocked from WordPress paragraphs' => static function (
        TestRunner $t
    ) use ($directEncryptDictionaryPdf): void {
        [$pdf, $content, $ownerValidation, $userValidation] = $directEncryptDictionaryPdf();
        $extractor = new PdfTextExtractor();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', $extractor->extractPlainText($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);
        $t->same(false, $report['raw_owner_user_keys_exposed']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerValidation)
            && !str_contains($encoded, $userValidation)
            && !str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
            && !str_contains($encoded, strtoupper(bin2hex($userValidation))));
    },
];
