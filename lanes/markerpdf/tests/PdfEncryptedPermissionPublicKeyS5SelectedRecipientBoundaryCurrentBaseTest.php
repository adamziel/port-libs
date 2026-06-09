<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$s5SelectedRecipientWithLegacyDecoyPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (S5 selected recipient boundary encrypted text leak) Tj ET';
    $selectedRecipient = 'S5_SELECTED_CRYPT_FILTER_RECIPIENT_SHOULD_NOT_LEAK';
    $legacyDecoyRecipient = 'S5_LEGACY_TOPLEVEL_RECIPIENT_DECOY_SHOULD_NOT_LEAK';
    $selectedRecipientHex = strtoupper(bin2hex($selectedRecipient));
    $legacyDecoyRecipientHex = strtoupper(bin2hex($legacyDecoyRecipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
        . " /Recipients [<{$legacyDecoyRecipientHex}>] 6 0 R"
        . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$selectedRecipientHex}>] >> >>"
        . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n<< /IgnoredLegacyRecipientTrailer true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $selectedRecipient, $legacyDecoyRecipient, $selectedRecipientHex, $legacyDecoyRecipientHex];
};

return [
    'keeps s5 crypt-filter recipient selection authoritative when legacy top-level recipients are malformed decoys' => static function (
        TestRunner $t
    ) use ($s5SelectedRecipientWithLegacyDecoyPdf): void {
        [$pdf, $content, $selectedRecipient, $legacyDecoyRecipient, $selectedRecipientHex, $legacyDecoyRecipientHex] =
            $s5SelectedRecipientWithLegacyDecoyPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $selection = $recipientReview['crypt_filter_selection'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'public_key_recipient_permissions_undecoded',
        ], $report['review_reasons']);

        $t->same('Adobe.PubSec', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same(2, $report['encryption']['public_key_recipient_count']);
        $t->same(1, $report['encryption']['selected_public_key_recipient_count']);

        $t->same('public_key_recipient_permissions', $permission['source']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['recipient_permissions_declared']);
        $t->same(true, $permission['selected_recipient_permissions_declared']);
        $t->same(1, $permission['selected_public_key_recipient_count']);
        $t->same(true, $permission['public_key_recipient_declaration_fail_closed']);
        $t->same(false, $permission['public_key_selected_recipient_declaration_fail_closed']);
        $t->same(false, $permission['public_key_selected_recipient_permissions_missing']);
        $t->same(['public_key_recipient_trailing_operand_review', 'public_key_recipient_entry'], $permission['public_key_recipient_entry_statuses']);
        $t->same(['public_key_recipient_entry'], $permission['public_key_selected_recipient_entry_statuses']);

        $t->same('public_key_security_handler', $recipientReview['source']);
        $t->same('crypt_filter_recipients_with_legacy_encryption_dictionary_recipients', $recipientReview['recipient_source_policy']);
        $t->same(2, $recipientReview['recipient_count']);
        $t->same(1, $recipientReview['top_level_recipient_count']);
        $t->same(false, $recipientReview['top_level_recipients_selected']);
        $t->same(['DefaultCryptFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same([], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same(1, $recipientReview['selected_recipient_count']);
        $t->same([hash('sha256', $selectedRecipient)], $recipientReview['selected_recipient_sha256']);
        $t->same(['crypt_filter_recipients'], $recipientReview['selected_recipient_sources']);
        $t->same('crypt_filter_recipients', $recipientReview['selected_recipient_source_policy']);
        $t->same(true, $recipientReview['recipient_declaration_fail_closed']);
        $t->same(false, $recipientReview['selected_recipient_declaration_fail_closed']);
        $t->same(1, $recipientReview['recipient_trailing_operand_count']);
        $t->same(0, $recipientReview['selected_recipient_trailing_operand_count']);
        $t->same(true, $recipientReview['permissions_available_in_recipient_envelopes']);
        $t->same(true, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
        $t->same(false, $recipientReview['selected_recipient_permissions_missing']);
        $t->same(false, $recipientReview['permissions_decoded']);
        $t->same('cms_pkcs7_permission_decode_unavailable', $recipientReview['permission_decode_status']);

        $t->same('public_key_crypt_filter_selection', $selection['source']);
        $t->same(['DefaultCryptFilter'], $selection['selected_recipient_filter_names']);
        $t->same([], $selection['unselected_recipient_filter_names']);
        $t->same(1, $selection['selected_recipient_count']);
        $t->same(false, $selection['selected_recipient_declaration_fail_closed']);
        $t->same(['public_key_recipient_entry'], $selection['selected_recipient_entry_statuses']);
        $t->same(0, $selection['selected_recipient_trailing_operand_count']);

        $t->same(false, $recipientReview['executes_cms_parse']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $selectedRecipient)
            && !str_contains($encoded, $legacyDecoyRecipient)
            && !str_contains($encoded, $selectedRecipientHex)
            && !str_contains($encoded, $legacyDecoyRecipientHex));
    },
];
