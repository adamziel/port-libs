<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$publicKeyCryptFilterRoleBoundaryPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Public-key malformed crypt-filter role text leak) Tj ET';
    $recipient = 'PUBLICKEY_MALFORMED_STMF_RECIPIENT_SHOULD_NOT_LEAK';
    $recipientHex = strtoupper(bin2hex($recipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
        . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$recipientHex}>] >> >>"
        . " /StmF /DefaultCryptFilter 9 0 R /EncryptMetadata true >>\nendobj\n"
        . "9 0 obj\n/Identity\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $recipient, $recipientHex];
};

return [
    'suppresses public-key crypt-filter recipients when the selected StmF role has a trailing operand' => static function (
        TestRunner $t
    ) use ($publicKeyCryptFilterRoleBoundaryPdf): void {
        [$pdf, $content, $recipient, $recipientHex] = $publicKeyCryptFilterRoleBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $selection = $recipientReview['crypt_filter_selection'];
        $roleReview = $metadata['encryption']['crypt_filter_role_declaration_review'];
        $contentReview = $report['crypt_filter_content_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'public_key_crypt_filter_role_declaration_malformed',
            'malformed_public_key_crypt_filter_roles',
            'crypt_filter_text_fail_closed',
        ], $report['review_reasons']);

        $t->same('encryption_crypt_filter_role_declaration_review', $roleReview['source']);
        $t->same(['document_streams'], $roleReview['malformed_role_names']);
        $t->same(['StmF'], $roleReview['malformed_pdf_names']);
        $t->same(['document_streams'], $roleReview['fail_closed_role_names']);
        $t->same(['StmF'], $roleReview['fail_closed_pdf_names']);

        $t->same('public_key_crypt_filter_role_declaration_malformed', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_public_key_crypt_filter_role_malformed', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['recipient_permissions_declared']);
        $t->same(false, $permission['selected_recipient_permissions_declared']);
        $t->same(true, $permission['public_key_crypt_filter_role_declaration_fail_closed']);
        $t->same(['document_streams'], $permission['public_key_crypt_filter_role_fail_closed_role_names']);
        $t->same(['StmF'], $permission['public_key_crypt_filter_role_fail_closed_pdf_names']);
        $t->same(1, $permission['public_key_recipient_declared_entry_count']);
        $t->same(0, $permission['selected_public_key_recipient_count']);

        $t->same('public_key_security_handler', $recipientReview['source']);
        $t->same('crypt_filter_recipients', $recipientReview['recipient_source_policy']);
        $t->same(true, $recipientReview['crypt_filter_role_declaration_fail_closed']);
        $t->same(['document_streams'], $recipientReview['crypt_filter_role_fail_closed_role_names']);
        $t->same(['StmF'], $recipientReview['crypt_filter_role_fail_closed_pdf_names']);
        $t->same(['DefaultCryptFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same([], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same(1, $recipientReview['recipient_count']);
        $t->same(0, $recipientReview['selected_recipient_count']);
        $t->same([], $recipientReview['selected_recipient_sources']);
        $t->same('no_selected_recipient_permission_envelopes', $recipientReview['selected_recipient_source_policy']);
        $t->same(true, $recipientReview['permissions_available_in_recipient_envelopes']);
        $t->same(false, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
        $t->same('public_key_crypt_filter_role_declaration_malformed_review', $recipientReview['permission_decode_status']);

        $t->same('public_key_crypt_filter_selection', $selection['source']);
        $t->same(true, $selection['role_declaration_fail_closed']);
        $t->same(['stream_filter'], $selection['role_declaration_fail_closed_content_roles']);
        $t->same(['document_streams'], $selection['role_declaration_fail_closed_role_names']);
        $t->same(['StmF'], $selection['role_declaration_fail_closed_pdf_names']);
        $t->same([], $selection['selected_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $selection['unselected_recipient_filter_names']);
        $t->same(0, $selection['selected_recipient_count']);
        $t->same([], $selection['selected_recipient_sha256']);
        $t->same(2, count($selection['selected_crypt_filters']));
        $t->same('stream_filter', $selection['selected_crypt_filters'][0]['role']);
        $t->same('DefaultCryptFilter', $selection['selected_crypt_filters'][0]['name']);
        $t->same(true, $selection['selected_crypt_filters'][0]['selection_suppressed_by_role_declaration']);
        $t->same('malformed_crypt_filter_role_entry_review', $selection['selected_crypt_filters'][0]['role_declaration_status']);
        $t->same(true, $selection['selected_crypt_filters'][0]['role_declaration_fail_closed']);
        $t->same(0, $selection['selected_crypt_filters'][0]['recipient_count']);
        $t->same('string_filter', $selection['selected_crypt_filters'][1]['role']);
        $t->same('Identity', $selection['selected_crypt_filters'][1]['name']);
        $t->same(true, $selection['selected_crypt_filters'][1]['filter_defaulted']);
        $t->same(false, $selection['selected_crypt_filters'][1]['selection_suppressed_by_role_declaration']);
        $t->same(0, $selection['selected_crypt_filters'][1]['recipient_count']);

        $t->same('malformed_crypt_filter_role_entry_fail_closed', $contentReview['text_content_policy']);
        $t->same(true, $contentReview['fail_closed_role_count'] > 0);
        $t->same(true, $permission['crypt_filter_text_fail_closed']);
        $t->same('blocked_by_malformed_document_crypt_filter_role', $permission['crypt_filter_content_extraction_boundary']);

        $t->same(false, $recipientReview['executes_cms_parse']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $recipient)
            && !str_contains($encoded, $recipientHex));
    },
];
