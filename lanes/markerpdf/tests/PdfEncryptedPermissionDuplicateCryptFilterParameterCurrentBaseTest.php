<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$duplicateCryptFilterParameterPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate crypt-filter parameter encrypted text leak) Tj ET';
    $ownerKey = str_repeat('P', 32);
    $userKey = str_repeat('Q', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
        . " /U <" . strtoupper(bin2hex($userKey)) . ">"
        . " /P -44 /EncryptMetadata true"
        . " /CF <<"
        . " /DocCF << /CFM /AESV2 /CFM /Identity /AuthEvent /DocOpen /Length 16 >>"
        . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >>"
        . " >>"
        . " /StmF /DocCF /StrF /DocCF /EFF /ClearEmbedded >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'fails closed when selected crypt-filter dictionaries duplicate the CFM parameter' => static function (
        TestRunner $t
    ) use ($duplicateCryptFilterParameterPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $duplicateCryptFilterParameterPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncryption = $metadata['encryption'];
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $reportEncryption = $report['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_crypt_filter_fail_closed',
            'crypt_filter_text_fail_closed',
        ], $report['review_reasons']);

        $t->same('Identity', $metadataEncryption['crypt_filters']['DocCF']['method']);
        $t->same('DocOpen', $metadataEncryption['crypt_filters']['DocCF']['auth_event']);
        $t->same(16, $metadataEncryption['crypt_filters']['DocCF']['key_length_bytes']);
        $t->same(true, $metadataEncryption['crypt_filters']['DocCF']['parameter_declaration_fail_closed']);
        $t->same('duplicate_crypt_filter_parameter_entries_review', $metadataEncryption['crypt_filters']['DocCF']['parameter_declaration_status']);
        $t->same(['CFM'], $metadataEncryption['crypt_filters']['DocCF']['duplicate_parameter_names']);
        $t->same('Identity', $metadataEncryption['crypt_filters']['ClearEmbedded']['method']);
        $t->same(false, $metadataEncryption['crypt_filters']['ClearEmbedded']['parameter_declaration_fail_closed'] ?? false);

        $parameterReview = $metadataEncryption['crypt_filters']['DocCF']['parameter_declaration_review'];
        $t->same('crypt_filter_parameter_declaration_review', $parameterReview['source']);
        $t->same('DocCF', $parameterReview['filter_name']);
        $t->same(1, $parameterReview['duplicate_parameter_count']);
        $t->same(['CFM'], $parameterReview['duplicate_parameter_names']);
        $t->same('duplicate_crypt_filter_parameter_entries_review', $parameterReview['status']);
        $t->same(true, $parameterReview['fail_closed']);
        $t->same(['CFM' => 2, 'AuthEvent' => 1, 'Length' => 1], $parameterReview['parameter_entry_counts']);

        $cfmRow = $parameterReview['rows'][0];
        $t->same('crypt_filter_parameter_declaration_row', $cfmRow['source']);
        $t->same('CFM', $cfmRow['pdf_name']);
        $t->same('method', $cfmRow['metadata_key']);
        $t->same(2, $cfmRow['declared_entry_count']);
        $t->same(true, $cfmRow['duplicate_entries']);
        $t->same(1, $cfmRow['selected_entry_index']);
        $t->same(['name'], $cfmRow['entry_operand_shapes']);
        $t->same(2, count($cfmRow['entries']));
        $t->same(0, $cfmRow['entries'][0]['index']);
        $t->same('name', $cfmRow['entries'][0]['operand_shape']);
        $t->same(1, $cfmRow['entries'][1]['index']);
        $t->same('name', $cfmRow['entries'][1]['operand_shape']);

        $t->same(1, $report['crypt_filter_content_review_count']);
        $t->same($review, $permission['crypt_filter_content_review']);
        $t->same($review, $reportEncryption['crypt_filter_content_review']);
        $t->same('duplicate_crypt_filter_parameter_entries_fail_closed', $review['text_content_policy']);
        $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['role_names']);
        $t->same(['identity_crypt_filter'], $review['role_statuses']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['identity_role_names']);
        $t->same(['document_streams', 'document_strings'], $review['fail_closed_role_names']);
        $t->same(['DocCF'], $review['fail_closed_filter_names']);
        $t->same(2, $review['fail_closed_role_count']);
        $t->same(['duplicate_crypt_filter_parameter_entries_review'], $review['crypt_filter_parameter_statuses']);
        $t->same(['document_streams', 'document_strings'], $review['crypt_filter_parameter_duplicate_role_names']);
        $t->same(['DocCF'], $review['crypt_filter_parameter_duplicate_filter_names']);
        $t->same(['CFM'], $review['crypt_filter_parameter_duplicate_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('DocCF', $streamRole['filter_name']);
        $t->same('Identity', $streamRole['method']);
        $t->same('identity_crypt_filter', $streamRole['status']);
        $t->same(true, $streamRole['identity_crypt_filter']);
        $t->same(true, $streamRole['crypt_filter_parameter_fail_closed']);
        $t->same('duplicate_crypt_filter_parameter_entries_review', $streamRole['crypt_filter_parameter_declaration_status']);
        $t->same(['CFM'], $streamRole['crypt_filter_parameter_duplicate_names']);
        $t->same($parameterReview, $streamRole['crypt_filter_parameter_declaration_review']);

        $t->same('document_strings', $stringRole['role']);
        $t->same('DocCF', $stringRole['filter_name']);
        $t->same(true, $stringRole['crypt_filter_parameter_fail_closed']);
        $t->same(['CFM'], $stringRole['crypt_filter_parameter_duplicate_names']);

        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('ClearEmbedded', $embeddedRole['filter_name']);
        $t->same('Identity', $embeddedRole['method']);
        $t->same('identity_crypt_filter', $embeddedRole['status']);
        $t->same(false, $embeddedRole['crypt_filter_parameter_fail_closed']);
        $t->same([], $embeddedRole['crypt_filter_parameter_duplicate_names']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_duplicate_document_crypt_filter_parameters', $permission['content_extraction_boundary']);
        $t->same('duplicate_crypt_filter_parameter_entries_fail_closed', $permission['crypt_filter_text_policy']);
        $t->same(true, $permission['crypt_filter_text_fail_closed']);
        $t->same(['document_streams', 'document_strings'], $permission['crypt_filter_fail_closed_role_names']);
        $t->same(['DocCF'], $permission['crypt_filter_fail_closed_filter_names']);
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
