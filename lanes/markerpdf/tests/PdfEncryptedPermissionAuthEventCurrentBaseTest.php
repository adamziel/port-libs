<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedCryptFilterAuthEventPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (AuthEvent encrypted text leak) Tj ET';
    $ownerKey = str_repeat('A', 32);
    $userKey = str_repeat('B', 32);

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
        . " /DocStreams << /CFM /AESV2 /Length 16 >>"
        . " /EmbeddedOnly << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 >>"
        . " >>"
        . " /StmF /DocStreams /StrF /EmbeddedOnly /EFF /EmbeddedOnly >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $ownerKey, $userKey];
};

return [
    'defaults missing crypt-filter AuthEvent and flags EFOpen document-content roles before encrypted import' => static function (
        TestRunner $t
    ) use ($encryptedCryptFilterAuthEventPdf): void {
        [$pdf, $content, $ownerKey, $userKey] = $encryptedCryptFilterAuthEventPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $encryption = $metadata['encryption'];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('DocOpen', $encryption['crypt_filters']['DocStreams']['auth_event']);
        $t->same(true, $encryption['crypt_filters']['DocStreams']['auth_event_defaulted']);
        $t->same('pdf_default_doc_open', $encryption['crypt_filters']['DocStreams']['auth_event_source']);
        $t->same('EFOpen', $encryption['crypt_filters']['EmbeddedOnly']['auth_event']);
        $t->true(!array_key_exists('auth_event_defaulted', $encryption['crypt_filters']['EmbeddedOnly']));

        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['role_names']);
        $t->same(['encrypted_crypt_filter'], $review['role_statuses']);
        $t->same(['DocOpen', 'EFOpen'], $permission['permission_handler_review']['standard_authentication_auth_events']);
        $t->same(['document_open_authorization', 'embedded_file_auth_event_on_document_content_review', 'embedded_file_open_authorization'], $review['auth_event_statuses']);
        $t->same(['document_streams'], $review['auth_event_defaulted_role_names']);
        $t->same(['DocStreams'], $review['auth_event_defaulted_filter_names']);
        $t->same(['document_strings'], $review['auth_event_mismatch_role_names']);
        $t->same(['EmbeddedOnly'], $review['auth_event_mismatch_filter_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('DocStreams', $streamRole['filter_name']);
        $t->same('DocOpen', $streamRole['auth_event']);
        $t->same(true, $streamRole['auth_event_defaulted']);
        $t->same('document_open_authorization', $streamRole['auth_event_status']);
        $t->same(true, $streamRole['auth_event_applies_to_role']);

        $t->same('document_strings', $stringRole['role']);
        $t->same('EmbeddedOnly', $stringRole['filter_name']);
        $t->same('EFOpen', $stringRole['auth_event']);
        $t->same(false, $stringRole['auth_event_defaulted']);
        $t->same('embedded_file_auth_event_on_document_content_review', $stringRole['auth_event_status']);
        $t->same(false, $stringRole['auth_event_applies_to_role']);

        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('EmbeddedOnly', $embeddedRole['filter_name']);
        $t->same('EFOpen', $embeddedRole['auth_event']);
        $t->same('embedded_file_open_authorization', $embeddedRole['auth_event_status']);
        $t->same(true, $embeddedRole['auth_event_applies_to_role']);

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
