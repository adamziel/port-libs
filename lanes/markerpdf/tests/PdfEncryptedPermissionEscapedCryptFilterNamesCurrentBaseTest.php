<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$escapedCryptFilterNamesPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Escaped crypt-filter encrypted text leak) Tj ET';
    $recipient = 'PUBLICKEY_ESCAPED_CRYPT_FILTER_RECIPIENT_SHOULD_NOT_LEAK';
    $recipientHex = strtoupper(bin2hex($recipient));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /#46ilter /Adobe.PubSec /#53ubFilter /adbe.pkcs7.s5 /V 4 /R 4 /Length 128"
        . " /#43F << /Default#43ryptFilter << /#43FM /AESV2 /#41uthEvent /DocOpen /#4Cength 16"
        . " /#52ecipients [<{$recipientHex}>] >> >>"
        . " /#53tmF /Default#43ryptFilter /#53trF /Default#43ryptFilter /#45FF /Default#43ryptFilter"
        . " /EncryptMetadata true >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $recipient, $recipientHex];
};

return [
    'surfaces escaped crypt filter names in encrypted public-key permission preflight summaries' => static function (
        TestRunner $t
    ) use ($escapedCryptFilterNamesPdf): void {
        [$pdf, $content, $recipient, $recipientHex] = $escapedCryptFilterNamesPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $preflightEncryption = $report['encryption'];
        $permission = $report['permission_preflight'];
        $recipientReview = $permission['public_key_recipient_review'];
        $cryptFilterReview = $report['crypt_filter_content_review'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'public_key_recipient_permissions_undecoded'], $report['review_reasons']);

        $t->same('Adobe.PubSec', $encryption['filter']);
        $t->same('adbe.pkcs7.s5', $encryption['subfilter']);
        $t->same('DefaultCryptFilter', $encryption['stream_filter']);
        $t->same('DefaultCryptFilter', $encryption['string_filter']);
        $t->same('DefaultCryptFilter', $encryption['embedded_file_filter']);
        $t->same('AESV2', $encryption['crypt_filters']['DefaultCryptFilter']['method']);
        $t->same('DocOpen', $encryption['crypt_filters']['DefaultCryptFilter']['auth_event']);
        $t->same(16, $encryption['crypt_filters']['DefaultCryptFilter']['key_length_bytes']);
        $t->same(1, $encryption['crypt_filters']['DefaultCryptFilter']['recipients']['recipient_count']);
        $t->same([hash('sha256', $recipient)], $encryption['crypt_filters']['DefaultCryptFilter']['recipients']['recipient_sha256']);

        $t->same(1, $preflightEncryption['declared_crypt_filter_count']);
        $t->same(['DefaultCryptFilter'], $preflightEncryption['declared_crypt_filter_names']);
        $t->same(['DefaultCryptFilter'], $preflightEncryption['selected_crypt_filter_names']);
        $t->same(['DefaultCryptFilter'], $preflightEncryption['crypt_filter_dictionary_declared_filter_names']);
        $t->same(['DefaultCryptFilter'], $preflightEncryption['crypt_filter_dictionary_selected_filter_names']);
        $t->same('well_formed_crypt_filter_dictionary', $preflightEncryption['crypt_filter_dictionary_status']);

        $t->same('public_key_recipient_permissions', $permission['source']);
        $t->same('public_key_recipient_permissions_blocked_without_private_key', $permission['policy']);
        $t->same('blocked_encrypted_public_key_recipient_permissions', $permission['content_extraction_boundary']);
        $t->same(1, $permission['selected_public_key_recipient_count']);
        $t->same(false, $permission['recipient_bytes_exposed']);

        $t->same('crypt_filter_recipients', $recipientReview['recipient_source_policy']);
        $t->same(['DefaultCryptFilter'], $recipientReview['crypt_filter_recipient_filter_names']);
        $t->same(['DefaultCryptFilter'], $recipientReview['selected_crypt_filter_recipient_filter_names']);
        $t->same([], $recipientReview['unselected_crypt_filter_recipient_filter_names']);
        $t->same(1, $recipientReview['selected_recipient_count']);
        $t->same([hash('sha256', $recipient)], $recipientReview['selected_recipient_sha256']);

        $t->same(['DefaultCryptFilter'], $cryptFilterReview['selected_filter_names']);
        $t->same(['DefaultCryptFilter'], $cryptFilterReview['encrypted_filter_names']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $cryptFilterReview['encrypted_role_names']);
        $t->same('encrypted_crypt_filter', $cryptFilterReview['roles'][0]['status']);
        $t->same('document_open_authorization', $cryptFilterReview['roles'][0]['auth_event_status']);
        $t->same('crypt_filter_key_length_supported', $cryptFilterReview['roles'][0]['key_length_status']);

        $t->same(false, $report['executes_cms_parse'] ?? false);
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
