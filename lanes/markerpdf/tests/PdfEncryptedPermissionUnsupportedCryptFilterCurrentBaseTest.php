<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$unsupportedCryptFilterPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Unsupported crypt filter encrypted text leak) Tj ET';
    $ownerKey = str_repeat('O', 32);
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
        . " /VendorStreams << /CFM /VendorAES /AuthEvent /DocOpen /Length 16 >>"
        . " /MysteryStrings << /CFM [] /AuthEvent /DocOpen /Length 16 >>"
        . " /ClearEmbedded << /CFM /Identity /AuthEvent /EFOpen >>"
        . " >>"
        . " /StmF /VendorStreams /StrF /MysteryStrings /EFF /ClearEmbedded >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'fails closed when document crypt filters use unsupported or unknown methods despite copy permission' => static function (
        TestRunner $t
    ) use ($unsupportedCryptFilterPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $unsupportedCryptFilterPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
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

        $t->same('Standard', $metadata['encryption']['filter']);
        $t->same('VendorAES', $metadata['encryption']['crypt_filters']['VendorStreams']['method']);
        $t->true(!array_key_exists('method', $metadata['encryption']['crypt_filters']['MysteryStrings']));
        $t->same('Identity', $metadata['encryption']['crypt_filters']['ClearEmbedded']['method']);

        $t->same(1, $report['crypt_filter_content_review_count']);
        $t->same('unsupported_crypt_filter_method_fail_closed', $review['text_content_policy']);
        $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['role_names']);
        $t->same([
            'unsupported_crypt_filter_method_fail_closed',
            'unknown_crypt_filter_method_fail_closed',
            'identity_crypt_filter',
        ], $review['role_statuses']);
        $t->same(['embedded_file_streams'], $review['identity_role_names']);
        $t->same([], $review['encrypted_role_names']);
        $t->same([], $review['missing_role_names']);
        $t->same(['document_streams', 'document_strings'], $review['unsupported_role_names']);
        $t->same(['document_streams', 'document_strings'], $review['fail_closed_role_names']);
        $t->same(['VendorStreams', 'MysteryStrings'], $review['fail_closed_filter_names']);
        $t->same(2, $review['fail_closed_role_count']);
        $t->same(false, $review['native_text_extraction_allowed_now']);
        $t->same(false, $review['executes_decryption']);
        $t->same(false, $review['executes_permission_enforcement']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('VendorStreams', $streamRole['filter_name']);
        $t->same('VendorAES', $streamRole['method']);
        $t->same('unsupported_crypt_filter_method_fail_closed', $streamRole['status']);
        $t->same(true, $streamRole['content_encrypted']);
        $t->same(false, $streamRole['identity_crypt_filter']);
        $t->same('document_strings', $stringRole['role']);
        $t->same('MysteryStrings', $stringRole['filter_name']);
        $t->same(null, $stringRole['method']);
        $t->same('unknown_crypt_filter_method_fail_closed', $stringRole['status']);
        $t->same(true, $stringRole['content_encrypted']);
        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('ClearEmbedded', $embeddedRole['filter_name']);
        $t->same('Identity', $embeddedRole['method']);
        $t->same('identity_crypt_filter', $embeddedRole['status']);
        $t->same(false, $embeddedRole['content_encrypted']);

        $t->same('standard_security_handler_crypt_filter_preflight', $permission['source']);
        $t->same('copy_extract_allowed_but_crypt_filter_preflight_blocked', $permission['policy']);
        $t->same('blocked_by_unsupported_document_crypt_filter_method', $permission['content_extraction_boundary']);
        $t->same('unsupported_crypt_filter_method_fail_closed', $permission['crypt_filter_text_policy']);
        $t->same(true, $permission['crypt_filter_text_fail_closed']);
        $t->same(['document_streams', 'document_strings'], $permission['crypt_filter_fail_closed_role_names']);
        $t->same(['VendorStreams', 'MysteryStrings'], $permission['crypt_filter_fail_closed_filter_names']);
        $t->same(true, $permission['permission_bits_reliable']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);
        $t->same(false, $permission['decryption_performed']);

        $t->same($review, $encryption['crypt_filter_content_review']);
        $t->same(false, $report['content_extraction_allowed']);
        $t->same('blocked_without_decryption', $report['text_extraction_policy']);
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
    'keeps unsupported-crypt-filter encrypted payloads out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($unsupportedCryptFilterPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $unsupportedCryptFilterPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('', $plainText);
        $t->true(!str_contains($plainText, $content));
        $t->true(!str_contains($plainText, $ownerKey));
        $t->true(!str_contains($plainText, $userKey));
    },
];
