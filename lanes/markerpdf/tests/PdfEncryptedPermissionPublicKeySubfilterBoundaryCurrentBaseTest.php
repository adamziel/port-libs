<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$publicKeySubfilterBoundaryPdf = static function (string $subfilterOperand): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Public-key ambiguous subfilter encrypted text leak) Tj ET';
    $legacyRecipient = 'PUBLICKEY_SUBFILTER_LEGACY_RECIPIENT_SHOULD_NOT_LEAK';
    $cryptFilterRecipient = 'PUBLICKEY_SUBFILTER_CRYPT_FILTER_RECIPIENT_SHOULD_NOT_LEAK';
    $legacyRecipientHex = strtoupper(bin2hex($legacyRecipient));
    $cryptFilterRecipientHex = strtoupper(bin2hex($cryptFilterRecipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec {$subfilterOperand} /V 4 /Length 128"
        . " /Recipients [<{$legacyRecipientHex}>]"
        . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$cryptFilterRecipientHex}>] >> >>"
        . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $legacyRecipient, $cryptFilterRecipient, $legacyRecipientHex, $cryptFilterRecipientHex];
};

return [
    'fails closed when public-key SubFilter entries disagree before recipient permission selection' => static function (
        TestRunner $t
    ) use ($publicKeySubfilterBoundaryPdf): void {
        [
            $pdf,
            $content,
            $legacyRecipient,
            $cryptFilterRecipient,
            $legacyRecipientHex,
            $cryptFilterRecipientHex,
        ] = $publicKeySubfilterBoundaryPdf('/SubFilter /adbe.pkcs7.s3 /SubFilter /adbe.pkcs7.s5');

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $declarationReview = $permission['security_handler_subfilter_declaration_review'];
        $selection = $recipientReview['crypt_filter_selection'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'security_handler_subfilter_declaration_malformed',
            'duplicate_security_handler_subfilter_entries',
        ], $report['review_reasons']);
        $t->same('Adobe.PubSec', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same('duplicate_security_handler_subfilter_entries_review', $report['encryption']['security_handler_subfilter_declaration_status']);
        $t->same(true, $report['encryption']['security_handler_subfilter_declaration_fail_closed']);
        $t->same(['adbe.pkcs7.s3', 'adbe.pkcs7.s5'], $report['encryption']['security_handler_subfilter_names']);

        $t->same('security_handler_subfilter_declaration_malformed', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_security_handler_subfilter_malformed', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['recipient_permissions_declared']);
        $t->same(false, $permission['selected_recipient_permissions_declared']);
        $t->same(0, $permission['selected_public_key_recipient_count']);
        $t->same(true, $permission['security_handler_subfilter_declaration_fail_closed']);
        $t->same(true, $permission['security_handler_duplicate_subfilter_entries']);
        $t->same(false, $permission['security_handler_malformed_subfilter_entries']);

        $t->same('security_handler_subfilter_declaration_review', $declarationReview['source']);
        $t->same(2, $declarationReview['declared_entry_count']);
        $t->same(true, $declarationReview['duplicate_entries']);
        $t->same(false, $declarationReview['malformed_entries']);
        $t->same(true, $declarationReview['fail_closed']);
        $t->same(1, $declarationReview['selected_entry_index']);
        $t->same('adbe.pkcs7.s5', $declarationReview['selected_subfilter_name']);
        $t->same(['security_handler_subfilter_name'], $declarationReview['entry_statuses']);
        $t->same(['name'], $declarationReview['entry_operand_shapes']);

        $t->same('malformed_security_handler_subfilter_declaration_review', $permission['permission_handler_review']['permission_word_status']);
        $t->same(false, $permission['permission_handler_review']['permission_word_well_formed']);
        $t->same(true, $permission['permission_handler_review']['security_handler_subfilter_declaration_fail_closed']);

        $t->same('subfilter_declaration_ambiguous_recipients_fail_closed', $recipientReview['recipient_source_policy']);
        $t->same('public_key_subfilter_declaration_malformed_review', $recipientReview['permission_decode_status']);
        $t->same(2, $recipientReview['recipient_count']);
        $t->same(1, $recipientReview['top_level_recipient_count']);
        $t->same(false, $recipientReview['top_level_recipients_selected']);
        $t->same(['DefaultCryptFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same([], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same(0, $recipientReview['selected_recipient_count']);
        $t->same([], $recipientReview['selected_recipient_sources']);
        $t->same('no_selected_recipient_permission_envelopes', $recipientReview['selected_recipient_source_policy']);
        $t->same(false, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
        $t->same(true, $selection['selection_suppressed_by_subfilter_declaration']);
        $t->same([], $selection['selected_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $selection['unselected_recipient_filter_names']);
        $t->same(0, $selection['selected_recipient_count']);

        $t->same(false, $recipientReview['executes_cms_parse']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $legacyRecipient)
            && !str_contains($encoded, $cryptFilterRecipient)
            && !str_contains($encoded, $legacyRecipientHex)
            && !str_contains($encoded, $cryptFilterRecipientHex));
    },

    'fails closed when public-key SubFilter is a composite operand' => static function (
        TestRunner $t
    ) use ($publicKeySubfilterBoundaryPdf): void {
        [$pdf] = $publicKeySubfilterBoundaryPdf('/SubFilter [/adbe.pkcs7.s5]');

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $declarationReview = $metadata['encryption']['security_handler_subfilter_declaration_review'];

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'security_handler_subfilter_declaration_malformed',
            'malformed_security_handler_subfilter_entries',
        ], $report['review_reasons']);
        $t->same(null, $report['encryption']['subfilter']);
        $t->same('malformed_security_handler_subfilter_entries_review', $report['encryption']['security_handler_subfilter_declaration_status']);
        $t->same(true, $report['encryption']['security_handler_malformed_subfilter_entries']);
        $t->same('security_handler_subfilter_declaration_malformed', $permission['source']);
        $t->same('permissions_malformed_blocked_without_decryption', $permission['policy']);
        $t->same('blocked_encrypted_security_handler_subfilter_malformed', $permission['content_extraction_boundary']);
        $t->same('malformed_security_handler_subfilter_declaration_review', $permission['permission_handler_review']['permission_word_status']);

        $t->same(1, $declarationReview['declared_entry_count']);
        $t->same(false, $declarationReview['duplicate_entries']);
        $t->same(true, $declarationReview['malformed_entries']);
        $t->same(true, $declarationReview['fail_closed']);
        $t->same(null, $declarationReview['selected_subfilter_name']);
        $t->same(['security_handler_subfilter_composite_operand_review'], $declarationReview['entry_statuses']);
        $t->same(['array'], $declarationReview['entry_operand_shapes']);

        $t->same('subfilter_declaration_ambiguous_recipients_fail_closed', $recipientReview['recipient_source_policy']);
        $t->same(2, $recipientReview['recipient_count']);
        $t->same(0, $recipientReview['selected_recipient_count']);
        $t->same([], $recipientReview['selected_recipient_sources']);
        $t->same('no_selected_recipient_permission_envelopes', $recipientReview['selected_recipient_source_policy']);
        $t->same(true, $recipientReview['permissions_available_in_recipient_envelopes']);
        $t->same(false, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
        $t->same('public_key_subfilter_declaration_malformed_review', $recipientReview['permission_decode_status']);
        $t->same(false, $recipientReview['executes_cms_parse']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
    },
];
