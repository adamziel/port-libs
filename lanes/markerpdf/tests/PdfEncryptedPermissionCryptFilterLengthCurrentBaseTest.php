<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$invalidCryptFilterLengthPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Invalid crypt filter length encrypted text leak) Tj ET';
    $ownerKey = str_repeat('L', 32);
    $userKey = str_repeat('U', 32);

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
        . " /ShortAesStreams << /CFM /AESV2 /AuthEvent /DocOpen /Length 4 >>"
        . " /LongV2Strings << /CFM /V2 /AuthEvent /DocOpen /Length 17 >>"
        . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen /Length 0 >>"
        . " >>"
        . " /StmF /ShortAesStreams /StrF /LongV2Strings /EFF /ClearEmbedded >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'fails closed when encrypted document crypt filters declare invalid key lengths' => static function (
        TestRunner $t
    ) use ($invalidCryptFilterLengthPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $invalidCryptFilterLengthPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $encryption = $metadata['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same([
            'encrypted_document',
            'encrypted_text_extraction_blocked',
            'copy_or_extract_allowed_but_crypt_filter_fail_closed',
            'crypt_filter_text_fail_closed',
        ], $report['review_reasons']);
        $t->same(['native_text_extraction', 'decryption'], $report['blocked_operations']);

        $t->same('AESV2', $encryption['crypt_filters']['ShortAesStreams']['method']);
        $t->same(4, $encryption['crypt_filters']['ShortAesStreams']['key_length_bytes']);
        $t->same('V2', $encryption['crypt_filters']['LongV2Strings']['method']);
        $t->same(17, $encryption['crypt_filters']['LongV2Strings']['key_length_bytes']);
        $t->same('Identity', $encryption['crypt_filters']['ClearEmbedded']['method']);
        $t->same(0, $encryption['crypt_filters']['ClearEmbedded']['key_length_bytes']);

        $t->same('invalid_crypt_filter_key_length_fail_closed', $review['text_content_policy']);
        $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['role_names']);
        $t->same(['encrypted_crypt_filter', 'identity_crypt_filter'], $review['role_statuses']);
        $t->same(['document_streams', 'document_strings'], $review['fail_closed_role_names']);
        $t->same(['ShortAesStreams', 'LongV2Strings'], $review['fail_closed_filter_names']);
        $t->same(2, $review['fail_closed_role_count']);
        $t->same(['invalid_crypt_filter_key_length_review', 'identity_filter_key_length_ignored'], $review['key_length_statuses']);
        $t->same(['document_streams', 'document_strings'], $review['key_length_invalid_role_names']);
        $t->same(['ShortAesStreams', 'LongV2Strings'], $review['key_length_invalid_filter_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('ShortAesStreams', $streamRole['filter_name']);
        $t->same('AESV2', $streamRole['method']);
        $t->same(4, $streamRole['key_length_bytes']);
        $t->same(5, $streamRole['minimum_key_length_bytes']);
        $t->same(16, $streamRole['maximum_key_length_bytes']);
        $t->same(false, $streamRole['key_length_valid']);
        $t->same('invalid_crypt_filter_key_length_review', $streamRole['key_length_status']);
        $t->same(true, $streamRole['key_length_fail_closed']);
        $t->same('encrypted_crypt_filter', $streamRole['status']);

        $t->same('document_strings', $stringRole['role']);
        $t->same('LongV2Strings', $stringRole['filter_name']);
        $t->same('V2', $stringRole['method']);
        $t->same(17, $stringRole['key_length_bytes']);
        $t->same(5, $stringRole['minimum_key_length_bytes']);
        $t->same(16, $stringRole['maximum_key_length_bytes']);
        $t->same(false, $stringRole['key_length_valid']);
        $t->same('invalid_crypt_filter_key_length_review', $stringRole['key_length_status']);
        $t->same(true, $stringRole['key_length_fail_closed']);

        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('ClearEmbedded', $embeddedRole['filter_name']);
        $t->same('Identity', $embeddedRole['method']);
        $t->same(0, $embeddedRole['key_length_bytes']);
        $t->same(null, $embeddedRole['minimum_key_length_bytes']);
        $t->same(null, $embeddedRole['maximum_key_length_bytes']);
        $t->same(null, $embeddedRole['key_length_valid']);
        $t->same('identity_filter_key_length_ignored', $embeddedRole['key_length_status']);
        $t->same(false, $embeddedRole['key_length_fail_closed']);
        $t->same('identity_crypt_filter', $embeddedRole['status']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_invalid_document_crypt_filter_key_length', $permission['content_extraction_boundary']);
        $t->same('invalid_crypt_filter_key_length_fail_closed', $permission['crypt_filter_text_policy']);
        $t->same(true, $permission['crypt_filter_text_fail_closed']);
        $t->same(['document_streams', 'document_strings'], $permission['crypt_filter_fail_closed_role_names']);
        $t->same(['ShortAesStreams', 'LongV2Strings'], $permission['crypt_filter_fail_closed_filter_names']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

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
