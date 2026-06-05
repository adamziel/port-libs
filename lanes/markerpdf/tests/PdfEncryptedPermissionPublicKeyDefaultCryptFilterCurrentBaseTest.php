<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$publicKeyDefaultCryptFilterPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Public-key default EFF encrypted text leak) Tj ET';
    $recipientOne = 'PUBLICKEY_DEFAULT_EFF_RECIPIENT_ONE_SHOULD_NOT_LEAK';
    $recipientTwo = 'PUBLICKEY_DEFAULT_EFF_RECIPIENT_TWO_SHOULD_NOT_LEAK';
    $recipientOneHex = strtoupper(bin2hex($recipientOne));
    $recipientTwoHex = strtoupper(bin2hex($recipientTwo));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Adobe.PubSec /SubFilter /adbe.pkcs7.s5 /V 4 /Length 128"
        . " /CF << /DefaultCryptFilter << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [<{$recipientOneHex}> 6 0 R] >> >>"
        . " /StmF /DefaultCryptFilter /StrF /DefaultCryptFilter /EncryptMetadata true >>\nendobj\n"
        . "6 0 obj\n<{$recipientTwoHex}>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex];
};

return [
    'applies default EFF role to public-key recipient permission selection without double counting envelopes' => static function (
        TestRunner $t
    ) use ($publicKeyDefaultCryptFilterPdf): void {
        [$pdf, $content, $recipientOne, $recipientTwo, $recipientOneHex, $recipientTwoHex] = $publicKeyDefaultCryptFilterPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $selection = $recipientReview['crypt_filter_selection'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'public_key_recipient_permissions_undecoded'], $report['review_reasons']);
        $t->same('Adobe.PubSec', $report['encryption']['filter']);
        $t->same('adbe.pkcs7.s5', $report['encryption']['subfilter']);
        $t->same('DefaultCryptFilter', $report['encryption']['stream_filter']);
        $t->same('DefaultCryptFilter', $report['encryption']['string_filter']);
        $t->same('DefaultCryptFilter', $report['encryption']['embedded_file_filter']);
        $t->same(true, $metadata['encryption']['embedded_file_filter_defaulted_from_stream_filter']);
        $t->same('pdf_default_stream_filter', $metadata['encryption']['embedded_file_filter_source']);

        $t->same('public_key_recipient_permissions', $permission['source']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
        $t->same(2, $permission['selected_public_key_recipient_count']);
        $t->same(true, $permission['selected_recipient_permissions_declared']);

        $t->same('public_key_security_handler', $recipientReview['source']);
        $t->same('crypt_filter_recipients', $recipientReview['recipient_source_policy']);
        $t->same(['DefaultCryptFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same([], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same(2, $recipientReview['recipient_count']);
        $t->same(2, $recipientReview['selected_recipient_count']);
        $t->same([hash('sha256', $recipientOne), hash('sha256', $recipientTwo)], $recipientReview['selected_recipient_sha256']);

        $t->same('public_key_crypt_filter_selection', $selection['source']);
        $t->same([
            'stream_filter' => 'DefaultCryptFilter',
            'string_filter' => 'DefaultCryptFilter',
            'embedded_file_filter' => 'DefaultCryptFilter',
        ], $selection['declared_content_filters']);
        $t->same(['embedded_file_filter' => 'DefaultCryptFilter'], $selection['defaulted_content_filters']);
        $t->same([
            'stream_filter' => 'pdf_dictionary',
            'string_filter' => 'pdf_dictionary',
            'embedded_file_filter' => 'pdf_default_stream_filter',
        ], $selection['content_filter_sources']);
        $t->same(['DefaultCryptFilter'], $selection['selected_filter_names']);
        $t->same(['DefaultCryptFilter'], $selection['selected_recipient_filter_names']);
        $t->same(2, $selection['selected_recipient_count']);
        $t->same(0, $selection['selected_unresolved_recipient_count']);
        $t->same(3, count($selection['selected_crypt_filters']));
        $t->same('embedded_file_filter', $selection['selected_crypt_filters'][2]['role']);
        $t->same('EFF', $selection['selected_crypt_filters'][2]['pdf_name']);
        $t->same('DefaultCryptFilter', $selection['selected_crypt_filters'][2]['name']);
        $t->same(true, $selection['selected_crypt_filters'][2]['filter_defaulted']);
        $t->same('pdf_default_stream_filter', $selection['selected_crypt_filters'][2]['filter_source']);
        $t->same(2, $selection['selected_crypt_filters'][2]['recipient_count']);

        $t->same(false, $report['executes_cms_parse'] ?? false);
        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $recipientOne)
            && !str_contains($encoded, $recipientTwo)
            && !str_contains($encoded, $recipientOneHex)
            && !str_contains($encoded, $recipientTwoHex));
    },
];
