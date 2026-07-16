<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$legacyPublicKeyRecipientPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Legacy public-key encrypted text leak) Tj ET';
    $recipientOne = 'LEGACY_PUBLICKEY_RECIPIENT_ONE_PERMISSION_BYTES_SHOULD_NOT_LEAK';
    $recipientTwo = 'LEGACY_PUBLICKEY_RECIPIENT_TWO_PERMISSION_BYTES_SHOULD_NOT_LEAK';
    $recipientOneHex = strtoupper(bin2hex($recipientOne));
    $recipientTwoHex = strtoupper(bin2hex($recipientTwo));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s4 /V 2 /Length 128 /Recipients [<{$recipientOneHex}> 6 0 R] /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n<{$recipientTwoHex}>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex];
};

return [
    'marks legacy public-key encryption dictionary recipients as selected permission envelopes' => static function (TestRunner $t) use ($legacyPublicKeyRecipientPdf): void {
        [$pdf, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex] = $legacyPublicKeyRecipientPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encryption = $metadata['encryption'];
        $review = $encryption['public_key_recipient_review'];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('Adobe.PubSec', $encryption['filter']);
        $t->same('adbe.pkcs7.s4', $encryption['subfilter']);
        $t->same('encryption_dictionary_recipients', $review['recipient_source_policy']);
        $t->same(2, $review['recipient_count']);
        $t->same(2, $review['top_level_recipient_count']);
        $t->same(true, $review['top_level_recipients_selected']);
        $t->same('encryption_dictionary_recipients', $review['selected_recipient_source_policy']);
        $t->same(['encryption_dictionary_recipients'], $review['selected_recipient_sources']);
        $t->same(2, $review['selected_recipient_count']);
        $t->same(strlen($recipientOne) + strlen($recipientTwo), $review['selected_recipient_bytes']);
        $t->same([hash('sha256', $recipientOne), hash('sha256', $recipientTwo)], $review['selected_recipient_sha256']);
        $t->same(true, $review['permissions_available_in_recipient_envelopes']);
        $t->same(true, $review['selected_permissions_available_in_recipient_envelopes']);
        $t->same(false, $review['permissions_decoded']);
        $t->same('cms_pkcs7_permission_decode_unavailable', $review['permission_decode_status']);
        $t->same(false, $review['recipient_bytes_exposed']);
        $t->same(false, $review['executes_cms_parse']);
        $t->same(false, $review['executes_decryption']);
        $t->true(is_string($encoded) && !str_contains($encoded, $recipientOne) && !str_contains($encoded, $recipientTwo));
        $t->true(is_string($encoded) && !str_contains($encoded, $recipientOneHex) && !str_contains($encoded, $recipientTwoHex));
    },
    'blocks WordPress text import while surfacing selected legacy public-key permission review' => static function (TestRunner $t) use ($legacyPublicKeyRecipientPdf): void {
        [$pdf, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex] = $legacyPublicKeyRecipientPdf();
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $handler = $report['permission_handler_review'];
        $recipientReview = $permission['public_key_recipient_review'];
        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'public_key_recipient_permissions_undecoded'], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);
        $t->same(2, $report['encryption']['public_key_recipient_count']);
        $t->same(2, $report['encryption']['selected_public_key_recipient_count']);
        $t->same(2, $permission['selected_public_key_recipient_count']);
        $t->same('public_key_recipient_permissions', $permission['source']);
        $t->same(true, $permission['recipient_permissions_declared']);
        $t->same(true, $permission['selected_recipient_permissions_declared']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
        $t->same(2, $handler['selected_public_key_recipient_count']);
        $t->same('public_key_recipient_permissions_undecoded_review', $handler['status']);
        $t->same('encryption_dictionary_recipients', $recipientReview['selected_recipient_source_policy']);
        $t->same(['encryption_dictionary_recipients'], $recipientReview['selected_recipient_sources']);
        $t->same(true, $recipientReview['selected_permissions_available_in_recipient_envelopes']);
        $t->same(false, $handler['executes_cms_parse']);
        $t->same(false, $handler['executes_decryption']);
        $t->same(false, $handler['executes_permission_enforcement']);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, 'Legacy public-key encrypted text leak')
            && !str_contains($encoded, $recipientOne)
            && !str_contains($encoded, $recipientTwo)
            && !str_contains($encoded, $recipientOneHex)
            && !str_contains($encoded, $recipientTwoHex));
    },
];
