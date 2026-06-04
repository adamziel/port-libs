<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedCryptFilterPermissionPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Identity stream encrypted permission text leak) Tj ET';
    $ownerKey = 'CRYPT_FILTER_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'CRYPT_FILTER_USER_KEY_SHOULD_NOT_LEAK';

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
        . " /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
        . " /ClearStreams << /CFM /Identity /AuthEvent /DocOpen >>"
        . " >>"
        . " /StmF /ClearStreams /StrF /StdCF /EFF /MissingEmbeddedFilter >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'summarizes Standard crypt-filter content roles before encrypted permission import preflight' => static function (
        TestRunner $t
    ) use ($encryptedCryptFilterPermissionPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $encryptedCryptFilterPermissionPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $encryption = $report['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(true, $permission['copy_or_extract_allowed']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same(1, $report['crypt_filter_content_review_count']);
        $t->same($review, $permission['crypt_filter_content_review']);
        $t->same($review, $encryption['crypt_filter_content_review']);
        $t->same('encryption_crypt_filter_content_review', $review['source']);
        $t->same(true, $review['present']);
        $t->same(true, $review['encrypted_document']);
        $t->same('Standard', $review['handler']);
        $t->same('review_only_encrypted_document_boundary', $review['text_content_policy']);
        $t->same('missing_declared_filter_fail_closed', $review['embedded_file_payload_policy']);
        $t->same(false, $review['native_text_extraction_allowed_now']);
        $t->same(false, $review['decryption_performed']);
        $t->same(false, $review['executes_decryption']);

        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['role_names']);
        $t->same(['document_strings'], $review['encrypted_role_names']);
        $t->same(['document_streams'], $review['identity_role_names']);
        $t->same(['embedded_file_streams'], $review['missing_role_names']);
        $t->same(['identity_crypt_filter', 'encrypted_crypt_filter', 'missing_declared_crypt_filter'], $review['role_statuses']);
        $t->same(['ClearStreams', 'StdCF', 'MissingEmbeddedFilter'], $review['selected_filter_names']);
        $t->same(['ClearStreams'], $review['identity_filter_names']);
        $t->same(['StdCF'], $review['encrypted_filter_names']);
        $t->same(['MissingEmbeddedFilter'], $review['missing_filter_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('StmF', $streamRole['pdf_name']);
        $t->same('ClearStreams', $streamRole['filter_name']);
        $t->same('Identity', $streamRole['method']);
        $t->same('DocOpen', $streamRole['auth_event']);
        $t->same(false, $streamRole['content_encrypted']);
        $t->same(true, $streamRole['identity_crypt_filter']);
        $t->same('identity_crypt_filter', $streamRole['status']);

        $t->same('document_strings', $stringRole['role']);
        $t->same('StrF', $stringRole['pdf_name']);
        $t->same('StdCF', $stringRole['filter_name']);
        $t->same('AESV2', $stringRole['method']);
        $t->same(16, $stringRole['key_length_bytes']);
        $t->same(true, $stringRole['content_encrypted']);
        $t->same(false, $stringRole['identity_crypt_filter']);
        $t->same('encrypted_crypt_filter', $stringRole['status']);

        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('EFF', $embeddedRole['pdf_name']);
        $t->same('MissingEmbeddedFilter', $embeddedRole['filter_name']);
        $t->same(false, $embeddedRole['crypt_filter_present']);
        $t->same(true, $embeddedRole['content_encrypted']);
        $t->same(false, $embeddedRole['identity_crypt_filter']);
        $t->same(true, $embeddedRole['missing_declared_crypt_filter']);
        $t->same('missing_declared_crypt_filter', $embeddedRole['status']);

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
