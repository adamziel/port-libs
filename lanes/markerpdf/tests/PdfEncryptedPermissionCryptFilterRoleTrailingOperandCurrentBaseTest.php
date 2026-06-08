<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$tailedCryptFilterRolePdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Tailed crypt-filter role encrypted text leak) Tj ET';
    $ownerKey = str_repeat('T', 32);
    $userKey = str_repeat('R', 32);

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
        . " /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
        . " /ClearStreams << /CFM /Identity /AuthEvent /DocOpen >>"
        . " /ClearStrings << /CFM /Identity /AuthEvent /DocOpen >>"
        . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >>"
        . " >>"
        . " /StmF /ClearStreams 9 0 R /StrF /ClearStrings /EFF /ClearEmbedded >>\nendobj\n"
        . "9 0 obj\n/StdCF\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'fails closed on Standard crypt-filter role values with trailing top-level operands' => static function (
        TestRunner $t
    ) use ($tailedCryptFilterRolePdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $tailedCryptFilterRolePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncryption = $metadata['encryption'];
        $reportEncryption = $report['encryption'];
        $declarationReview = $metadataEncryption['crypt_filter_role_declaration_review'];
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
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);

        $t->same('encryption_crypt_filter_role_declaration_review', $declarationReview['source']);
        $t->same(3, $declarationReview['role_count']);
        $t->same([], $declarationReview['duplicate_role_names']);
        $t->same([], $declarationReview['duplicate_pdf_names']);
        $t->same(['document_streams'], $declarationReview['malformed_role_names']);
        $t->same(['StmF'], $declarationReview['malformed_pdf_names']);
        $t->same([
            'malformed_crypt_filter_role_entry_review',
            'single_crypt_filter_role_entry',
        ], $declarationReview['role_statuses']);
        $t->same(['document_streams'], $declarationReview['fail_closed_role_names']);
        $t->same(['StmF'], $declarationReview['fail_closed_pdf_names']);
        $t->same(true, $declarationReview['review_only']);
        $t->same(false, $declarationReview['executes_decryption']);
        $t->same(false, $declarationReview['executes_permission_enforcement']);
        $t->same($declarationReview, $reportEncryption['crypt_filter_role_declaration_review']);
        $t->same($declarationReview['role_statuses'], $reportEncryption['crypt_filter_role_declaration_statuses']);
        $t->same($declarationReview['duplicate_role_names'], $reportEncryption['crypt_filter_role_declaration_duplicate_role_names']);
        $t->same($declarationReview['duplicate_pdf_names'], $reportEncryption['crypt_filter_role_declaration_duplicate_pdf_names']);

        $streamDeclaration = $declarationReview['roles'][0];
        $stringDeclaration = $declarationReview['roles'][1];
        $embeddedDeclaration = $declarationReview['roles'][2];
        $t->same('document_streams', $streamDeclaration['role']);
        $t->same('StmF', $streamDeclaration['pdf_name']);
        $t->same('stream_filter', $streamDeclaration['metadata_key']);
        $t->same(true, $streamDeclaration['declared']);
        $t->same(1, $streamDeclaration['declared_entry_count']);
        $t->same(false, $streamDeclaration['duplicate_entries']);
        $t->same(1, $streamDeclaration['malformed_entry_count']);
        $t->same(false, $streamDeclaration['defaulted']);
        $t->same('pdf_dictionary', $streamDeclaration['source_policy']);
        $t->same(null, $streamDeclaration['selected_filter_name']);
        $t->same(['ClearStreams'], $streamDeclaration['entry_filter_names']);
        $t->same(['crypt_filter_role_trailing_operand_review'], $streamDeclaration['entry_statuses']);
        $t->same('malformed_crypt_filter_role_entry_review', $streamDeclaration['status']);
        $t->same(true, $streamDeclaration['fail_closed']);
        $t->same(1, count($streamDeclaration['entries']));

        $streamEntry = $streamDeclaration['entries'][0];
        $t->same('crypt_filter_role_declaration_entry_review', $streamEntry['source']);
        $t->same(0, $streamEntry['index']);
        $t->same(true, $streamEntry['resolved']);
        $t->same('name', $streamEntry['operand_shape']);
        $t->same('ClearStreams', $streamEntry['filter_name']);
        $t->same(false, $streamEntry['single_value']);
        $t->same(true, $streamEntry['trailing_operand']);
        $t->same('indirect_reference', $streamEntry['trailing_operand_shape']);
        $t->same('9 0 R', $streamEntry['trailing_operand_preview']);
        $t->same(9, $streamEntry['trailing_operand_object_number']);
        $t->same(0, $streamEntry['trailing_operand_generation']);
        $t->same('crypt_filter_role_trailing_operand_review', $streamEntry['status']);

        $t->same('single_crypt_filter_role_entry', $stringDeclaration['status']);
        $t->same(false, $stringDeclaration['fail_closed']);
        $t->same('single_crypt_filter_role_entry', $embeddedDeclaration['status']);
        $t->same(false, $embeddedDeclaration['fail_closed']);

        $t->same(1, $report['crypt_filter_content_review_count']);
        $t->same($review, $permission['crypt_filter_content_review']);
        $t->same($review, $reportEncryption['crypt_filter_content_review']);
        $t->same('malformed_crypt_filter_role_entry_fail_closed', $review['text_content_policy']);
        $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['role_names']);
        $t->same(['undeclared_crypt_filter_fail_closed', 'identity_crypt_filter'], $review['role_statuses']);
        $t->same(['ClearStrings', 'ClearEmbedded'], $review['selected_filter_names']);
        $t->same(['document_strings', 'embedded_file_streams'], $review['identity_role_names']);
        $t->same([], $review['encrypted_role_names']);
        $t->same(['document_streams'], $review['fail_closed_role_names']);
        $t->same([], $review['fail_closed_filter_names']);
        $t->same(1, $review['fail_closed_role_count']);
        $t->same(['malformed_crypt_filter_role_entry_review', 'single_crypt_filter_role_entry'], $review['role_declaration_statuses']);
        $t->same([], $review['role_declaration_duplicate_role_names']);
        $t->same([], $review['role_declaration_duplicate_pdf_names']);
        $t->same(['document_streams'], $review['role_declaration_fail_closed_role_names']);
        $t->same(['StmF'], $review['role_declaration_fail_closed_pdf_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('StmF', $streamRole['pdf_name']);
        $t->same(null, $streamRole['filter_name']);
        $t->same(null, $streamRole['method']);
        $t->same('undeclared_crypt_filter_fail_closed', $streamRole['status']);
        $t->same(true, $streamRole['content_encrypted']);
        $t->same(false, $streamRole['identity_crypt_filter']);
        $t->same('malformed_crypt_filter_role_entry_review', $streamRole['role_declaration_status']);
        $t->same(true, $streamRole['role_declaration_declared']);
        $t->same(1, $streamRole['role_declaration_declared_entry_count']);
        $t->same(false, $streamRole['role_declaration_duplicate_entries']);
        $t->same(1, $streamRole['role_declaration_malformed_entry_count']);
        $t->same(false, $streamRole['role_declaration_defaulted']);
        $t->same(null, $streamRole['role_declaration_selected_filter_name']);
        $t->same(['ClearStreams'], $streamRole['role_declaration_entry_filter_names']);
        $t->same(['crypt_filter_role_trailing_operand_review'], $streamRole['role_declaration_entry_statuses']);
        $t->same(true, $streamRole['role_declaration_fail_closed']);
        $t->same(false, $stringRole['role_declaration_fail_closed']);
        $t->same(false, $embeddedRole['role_declaration_fail_closed']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_malformed_document_crypt_filter_role', $permission['content_extraction_boundary']);
        $t->same('malformed_crypt_filter_role_entry_fail_closed', $permission['crypt_filter_text_policy']);
        $t->same(true, $permission['crypt_filter_text_fail_closed']);
        $t->same(['document_streams'], $permission['crypt_filter_fail_closed_role_names']);
        $t->same([], $permission['crypt_filter_fail_closed_filter_names']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $permission['decryption_performed']);
        $t->same(false, $permission['raw_key_material_exposed']);
        $t->same(false, $permission['recipient_bytes_exposed']);

        $t->true(is_string($encoded));
        $t->same(false, str_contains($encoded, $content));
        $t->same(false, str_contains($encoded, $ownerKey));
        $t->same(false, str_contains($encoded, $userKey));
        $t->same(false, str_contains($encoded, strtoupper(bin2hex($ownerKey))));
        $t->same(false, str_contains($encoded, strtoupper(bin2hex($userKey))));
    },
];
