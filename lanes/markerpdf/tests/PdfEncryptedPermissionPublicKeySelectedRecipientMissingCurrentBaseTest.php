<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$s5TopLevelRecipientOnlyPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (S5 top-level recipient encrypted text leak) Tj ET';
    $recipient = 'S5_TOPLEVEL_RECIPIENT_SHOULD_NOT_LEAK';
    $recipientHex = strtoupper(bin2hex($recipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
        . " /Recipients [<{$recipientHex}>]"
        . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
        . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $recipient, $recipientHex];
};

return [
    'fails closed when adbe pkcs7 s5 has only unselected top-level recipient envelopes' => static function (
        TestRunner $t
    ) use ($s5TopLevelRecipientOnlyPdf): void {
        [$pdf, $content, $recipient, $recipientHex] = $s5TopLevelRecipientOnlyPdf();

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
            'public_key_selected_recipient_permissions_missing',
        ], $report['review_reasons']);

        $t->same('Adobe.PubSec', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same(1, $report['encryption']['public_key_recipient_count']);
        $t->same(0, $report['encryption']['selected_public_key_recipient_count']);

        $t->same('public_key_selected_recipient_permissions_missing', $permission['source']);
        $t->same('public_key_selected_recipient_permissions_missing', $permission['policy']);
        $t->same('blocked_encrypted_public_key_selected_recipient_permissions_missing', $permission['content_extraction_boundary']);
        $t->same(true, $permission['permissions_declared']);
        $t->same(true, $permission['recipient_permissions_declared']);
        $t->same(false, $permission['selected_recipient_permissions_declared']);
        $t->same(true, $permission['public_key_selected_recipient_permissions_missing']);
        $t->same(1, $permission['public_key_recipient_declared_entry_count']);
        $t->same(0, $permission['selected_public_key_recipient_count']);

        $t->same('legacy_encryption_dictionary_recipients_present_for_s5', $recipientReview['recipient_source_policy']);
        $t->same(1, $recipientReview['recipient_count']);
        $t->same(1, $recipientReview['top_level_recipient_count']);
        $t->same(false, $recipientReview['top_level_recipients_selected']);
        $t->same([], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same([], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same(0, $recipientReview['selected_recipient_count']);
        $t->same([], $recipientReview['selected_recipient_sources']);
        $t->same('no_selected_recipient_permission_envelopes', $recipientReview['selected_recipient_source_policy']);
        $t->same(true, $recipientReview['permissions_available_in_recipient_envelopes']);
        $t->same(false, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
        $t->same(true, $recipientReview['selected_recipient_permissions_missing']);
        $t->same('public_key_selected_recipient_envelopes_missing', $recipientReview['permission_decode_status']);

        $t->same('public_key_crypt_filter_selection', $selection['source']);
        $t->same([
            'stream_filter' => 'DefaultCryptFilter',
            'string_filter' => 'DefaultCryptFilter',
            'embedded_file_filter' => 'DefaultCryptFilter',
        ], $selection['declared_content_filters']);
        $t->same([], $selection['selected_recipient_filter_names']);
        $t->same([], $selection['unselected_recipient_filter_names']);
        $t->same(0, $selection['selected_recipient_count']);
        $t->same([], $selection['selected_recipient_sha256']);
        $t->same(3, count($selection['selected_crypt_filters']));
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
