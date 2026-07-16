<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$legacyPublicKeyCryptFilterDecoyPdf = static function (string $subfilter): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Legacy public-key crypt-filter decoy text leak) Tj ET';
    $legacyRecipient = 'LEGACY_PUBLICKEY_TOPLEVEL_RECIPIENT_SHOULD_NOT_LEAK';
    $cryptFilterRecipient = 'LEGACY_PUBLICKEY_CRYPT_FILTER_DECOY_SHOULD_NOT_LEAK';
    $legacyRecipientHex = strtoupper(bin2hex($legacyRecipient));
    $cryptFilterRecipientHex = strtoupper(bin2hex($cryptFilterRecipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /{$subfilter} /V 4 /Length 128"
        . " /Recipients [<{$legacyRecipientHex}>]"
        . " /CF << /LegacyDecoyFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$cryptFilterRecipientHex}>] >> >>"
        . " /StmF /LegacyDecoyFilter /StrF /LegacyDecoyFilter /EFF /Identity /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $legacyRecipient, $cryptFilterRecipient, $legacyRecipientHex, $cryptFilterRecipientHex];
};

$assertLegacySubfilterUsesTopLevelRecipients = static function (
    TestRunner $t,
    array $fixture,
    string $subfilter
): void {
    [$pdf, $content, $legacyRecipient, $cryptFilterRecipient, $legacyRecipientHex, $cryptFilterRecipientHex] = $fixture;

    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $report = (new PdfSecurityPreflight())->analyze($pdf);
    $permission = $report['permission_preflight'];
    $recipientReview = $permission['public_key_recipient_review'];
    $selection = $recipientReview['crypt_filter_selection'];
    $streamRows = array_values(array_filter(
        $selection['selected_crypt_filters'],
        static fn (array $row): bool => ($row['role'] ?? null) === 'stream_filter'
    ));
    $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

    $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
    $t->same([
        'encrypted_document',
        'encrypted_text_extraction_blocked',
        'public_key_recipient_permissions_undecoded',
    ], $report['review_reasons']);
    $t->same('Adobe.PubSec', $report['encryption']['filter']);
    $t->same($subfilter, $report['encryption']['subfilter']);
    $t->same(2, $report['encryption']['public_key_recipient_count']);
    $t->same(1, $report['encryption']['selected_public_key_recipient_count']);

    $t->same('public_key_recipient_permissions', $permission['source']);
    $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
    $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
    $t->same(true, $permission['recipient_permissions_declared']);
    $t->same(true, $permission['selected_recipient_permissions_declared']);
    $t->same(1, $permission['selected_public_key_recipient_count']);

    $t->same('public_key_security_handler', $recipientReview['source']);
    $t->same($subfilter, $recipientReview['subfilter']);
    $t->same('encryption_dictionary_recipients', $recipientReview['recipient_source_policy']);
    $t->same(2, $recipientReview['recipient_count']);
    $t->same(1, $recipientReview['top_level_recipient_count']);
    $t->same(true, $recipientReview['top_level_recipients_selected']);
    $t->same(['LegacyDecoyFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
    $t->same([], $recipientReview['selected_crypt_filter_recipient_filter_names']);
    $t->same(['LegacyDecoyFilter'], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
    $t->same(1, $recipientReview['selected_recipient_count']);
    $t->same(strlen($legacyRecipient), $recipientReview['selected_recipient_bytes']);
    $t->same([hash('sha256', $legacyRecipient)], $recipientReview['selected_recipient_sha256']);
    $t->same(['encryption_dictionary_recipients'], $recipientReview['selected_recipient_sources']);
    $t->same('encryption_dictionary_recipients', $recipientReview['selected_recipient_source_policy']);
    $t->same(true, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
    $t->same(false, $recipientReview['permissions_decoded']);
    $t->same('cms_pkcs7_permission_decode_unavailable', $recipientReview['permission_decode_status']);

    $t->same('public_key_crypt_filter_selection', $selection['source']);
    $t->same(true, $selection['selection_suppressed_by_legacy_subfilter']);
    $t->same($subfilter, $selection['selection_suppression_subfilter']);
    $t->same([], $selection['selected_recipient_filter_names']);
    $t->same(['LegacyDecoyFilter'], $selection['unselected_recipient_filter_names']);
    $t->same(0, $selection['selected_recipient_count']);
    $t->same(0, $selection['selected_recipient_bytes']);
    $t->same([], $selection['selected_recipient_sha256']);
    $t->same(1, count($streamRows));
    $t->same(true, $streamRows[0]['selection_suppressed_by_legacy_subfilter']);
    $t->same('legacy_public_key_subfilter_uses_top_level_recipients', $streamRows[0]['permission_decode_status']);
    $t->same(0, $streamRows[0]['recipient_count']);
    $t->same(1, $streamRows[0]['suppressed_recipient_count']);
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
};

return [
    'keeps adbe pkcs7 s4 crypt-filter recipients review-only when top-level recipients own permissions' => static function (
        TestRunner $t
    ) use ($legacyPublicKeyCryptFilterDecoyPdf, $assertLegacySubfilterUsesTopLevelRecipients): void {
        $assertLegacySubfilterUsesTopLevelRecipients(
            $t,
            $legacyPublicKeyCryptFilterDecoyPdf('adbe.pkcs7.s4'),
            'adbe.pkcs7.s4'
        );
    },

    'keeps adbe pkcs7 s3 crypt-filter recipients review-only when top-level recipients own permissions' => static function (
        TestRunner $t
    ) use ($legacyPublicKeyCryptFilterDecoyPdf, $assertLegacySubfilterUsesTopLevelRecipients): void {
        $assertLegacySubfilterUsesTopLevelRecipients(
            $t,
            $legacyPublicKeyCryptFilterDecoyPdf('adbe.pkcs7.s3'),
            'adbe.pkcs7.s3'
        );
    },
];
