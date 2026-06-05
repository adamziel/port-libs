<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$duplicateCryptFilterDictionaryPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate CF dictionary encrypted text leak) Tj ET';
    $ownerKey = str_repeat('D', 32);
    $userKey = str_repeat('F', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
        . " /U <" . strtoupper(bin2hex($userKey)) . ">"
        . " /P -44 /EncryptMetadata true"
        . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearStreams << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
        . " /CF << /ClearStreams << /CFM /Identity /AuthEvent /DocOpen >> /ClearStrings << /CFM /Identity /AuthEvent /DocOpen >> /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >> >>"
        . " /StmF /ClearStreams /StrF /ClearStrings /EFF /ClearEmbedded >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'fails closed on duplicate crypt-filter dictionaries before permission import review' => static function (
        TestRunner $t
    ) use ($duplicateCryptFilterDictionaryPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $duplicateCryptFilterDictionaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncryption = $metadata['encryption'];
        $reportEncryption = $report['encryption'];
        $dictionaryReview = $metadataEncryption['crypt_filter_dictionary_declaration_review'];
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_crypt_filter_fail_closed',
            'crypt_filter_text_fail_closed',
        ], $report['review_reasons']);

        $t->same('encryption_crypt_filter_dictionary_declaration_review', $dictionaryReview['source']);
        $t->same(2, $dictionaryReview['declared_entry_count']);
        $t->same(2, $dictionaryReview['resolved_dictionary_entry_count']);
        $t->same(true, $dictionaryReview['duplicate_entries']);
        $t->same(false, $dictionaryReview['malformed_entries']);
        $t->same(true, $dictionaryReview['ambiguous']);
        $t->same(true, $dictionaryReview['fail_closed']);
        $t->same('duplicate_crypt_filter_dictionary_entries_review', $dictionaryReview['status']);
        $t->same(['crypt_filter_dictionary_entry_resolved'], $dictionaryReview['entry_statuses']);
        $t->same(['StdCF', 'ClearStreams', 'ClearStrings', 'ClearEmbedded'], $dictionaryReview['declared_filter_names']);
        $t->same(1, $dictionaryReview['selected_entry_index']);
        $t->same(['ClearStreams', 'ClearStrings', 'ClearEmbedded'], $dictionaryReview['selected_filter_names']);
        $t->same($dictionaryReview, $reportEncryption['crypt_filter_dictionary_declaration_review']);
        $t->same('duplicate_crypt_filter_dictionary_entries_review', $reportEncryption['crypt_filter_dictionary_status']);
        $t->same(true, $reportEncryption['crypt_filter_dictionary_fail_closed']);

        $t->same(1, $report['crypt_filter_content_review_count']);
        $t->same($review, $permission['crypt_filter_content_review']);
        $t->same($review, $reportEncryption['crypt_filter_content_review']);
        $t->same('duplicate_crypt_filter_dictionary_entries_fail_closed', $review['text_content_policy']);
        $t->same('duplicate_crypt_filter_dictionary_entries_fail_closed', $review['embedded_file_payload_policy']);
        $t->same(true, $review['crypt_filter_dictionary_fail_closed']);
        $t->same('duplicate_crypt_filter_dictionary_entries_review', $review['crypt_filter_dictionary_declaration_status']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['fail_closed_role_names']);
        $t->same(['ClearStreams', 'ClearStrings', 'ClearEmbedded'], $review['fail_closed_filter_names']);
        $t->same(3, $review['fail_closed_role_count']);
        $t->same(['identity_crypt_filter'], $review['role_statuses']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['identity_role_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('ClearStreams', $streamRole['filter_name']);
        $t->same('Identity', $streamRole['method']);
        $t->same('identity_crypt_filter', $streamRole['status']);
        $t->same(true, $streamRole['crypt_filter_dictionary_fail_closed']);
        $t->same('duplicate_crypt_filter_dictionary_entries_review', $streamRole['crypt_filter_dictionary_declaration_status']);
        $t->same(true, $stringRole['crypt_filter_dictionary_fail_closed']);
        $t->same(true, $embeddedRole['crypt_filter_dictionary_fail_closed']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_duplicate_document_crypt_filter_dictionary', $permission['content_extraction_boundary']);
        $t->same('duplicate_crypt_filter_dictionary_entries_fail_closed', $permission['crypt_filter_text_policy']);
        $t->same(true, $permission['crypt_filter_text_fail_closed']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $permission['crypt_filter_fail_closed_role_names']);
        $t->same(['ClearStreams', 'ClearStrings', 'ClearEmbedded'], $permission['crypt_filter_fail_closed_filter_names']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $permission['decryption_performed']);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex($ownerKey)))
            && !str_contains($encoded, strtoupper(bin2hex($userKey))));
    },
];
