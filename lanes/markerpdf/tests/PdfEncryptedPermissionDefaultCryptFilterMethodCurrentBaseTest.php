<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$defaultCryptFilterMethodPdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Default CFM encrypted text leak) Tj ET';
    $payload = '<wp-export><post id="default-cfm-attachment"/></wp-export>';
    $ownerKey = str_repeat('C', 32);
    $userKey = str_repeat('M', 32);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
        . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
        . " /U <" . strtoupper(bin2hex($userKey)) . ">"
        . " /P -44 /EncryptMetadata true"
        . " /CF <<"
        . " /ClearStreams << /AuthEvent /DocOpen /Length 16 >>"
        . " /EncryptedStrings << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
        . " /ClearEmbedded << /AuthEvent /EFOpen /Length 16 >>"
        . " >>"
        . " /StmF /ClearStreams /StrF /EncryptedStrings /EFF /ClearEmbedded >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (default-cfm.xml) /UF (default-cfm.xml) /Desc (Default CFM attachment metadata) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $payload, $ownerKey, $userKey];
};

return [
    'treats omitted crypt-filter CFM as default None before encrypted permission preflight' => static function (
        TestRunner $t
    ) use ($defaultCryptFilterMethodPdf): void {
        [$pdf, $content, $payload, $ownerKey, $userKey] = $defaultCryptFilterMethodPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $file = $metadata['associated_files'][0] ?? [];
        $filePolicy = is_array($file['encryption_policy'] ?? null) ? $file['encryption_policy'] : [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('Standard', $encryption['filter']);
        $t->same('ClearStreams', $encryption['stream_filter']);
        $t->same('EncryptedStrings', $encryption['string_filter']);
        $t->same('ClearEmbedded', $encryption['embedded_file_filter']);
        $t->same('None', $encryption['crypt_filters']['ClearStreams']['method']);
        $t->same(true, $encryption['crypt_filters']['ClearStreams']['cfm_defaulted']);
        $t->same('pdf_default_none', $encryption['crypt_filters']['ClearStreams']['cfm_source']);
        $t->same('AESV2', $encryption['crypt_filters']['EncryptedStrings']['method']);
        $t->true(!array_key_exists('cfm_defaulted', $encryption['crypt_filters']['EncryptedStrings']));
        $t->same('None', $encryption['crypt_filters']['ClearEmbedded']['method']);
        $t->same(true, $encryption['crypt_filters']['ClearEmbedded']['cfm_defaulted']);
        $t->same('pdf_default_none', $encryption['crypt_filters']['ClearEmbedded']['cfm_source']);

        $t->same('review_only_encrypted_document_boundary', $review['text_content_policy']);
        $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
        $t->same(['document_streams', 'embedded_file_streams'], $review['identity_role_names']);
        $t->same(['document_strings'], $review['encrypted_role_names']);
        $t->same([], $review['missing_role_names']);
        $t->same([], $review['unsupported_role_names']);
        $t->same([], $review['fail_closed_role_names']);
        $t->same(['identity_crypt_filter', 'encrypted_crypt_filter'], $review['role_statuses']);
        $t->same(['ClearStreams', 'ClearEmbedded'], $review['identity_filter_names']);
        $t->same(['EncryptedStrings'], $review['encrypted_filter_names']);
        $t->same(['document_streams', 'embedded_file_streams'], $review['cfm_defaulted_role_names']);
        $t->same(['ClearStreams', 'ClearEmbedded'], $review['cfm_defaulted_filter_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('ClearStreams', $streamRole['filter_name']);
        $t->same('None', $streamRole['method']);
        $t->same(true, $streamRole['cfm_defaulted']);
        $t->same('pdf_default_none', $streamRole['cfm_source']);
        $t->same('identity_crypt_filter', $streamRole['status']);
        $t->same(false, $streamRole['content_encrypted']);
        $t->same('identity_filter_key_length_ignored', $streamRole['key_length_status']);
        $t->same(false, $streamRole['key_length_fail_closed']);

        $t->same('document_strings', $stringRole['role']);
        $t->same('EncryptedStrings', $stringRole['filter_name']);
        $t->same('AESV2', $stringRole['method']);
        $t->same(false, $stringRole['cfm_defaulted']);
        $t->same('encrypted_crypt_filter', $stringRole['status']);
        $t->same(true, $stringRole['content_encrypted']);

        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('ClearEmbedded', $embeddedRole['filter_name']);
        $t->same('None', $embeddedRole['method']);
        $t->same(true, $embeddedRole['cfm_defaulted']);
        $t->same('identity_crypt_filter', $embeddedRole['status']);
        $t->same(false, $embeddedRole['content_encrypted']);

        $t->true(!array_key_exists('filename', $file));
        $t->true(!array_key_exists('description', $file));
        $t->same(hash('sha256', $payload), $file['content_sha256'] ?? null);
        $t->same(strtolower(hash('md5', $payload)), $file['computed_checksum'] ?? null);
        $t->same(true, $file['checksum_matches'] ?? null);
        $t->same('encrypted_associated_file_review', $filePolicy['source'] ?? null);
        $t->same('EncryptedStrings', $filePolicy['string_filter'] ?? null);
        $t->same('encrypted_crypt_filter', $filePolicy['string_filter_status'] ?? null);
        $t->same('ClearEmbedded', $filePolicy['embedded_file_filter'] ?? null);
        $t->same('identity_crypt_filter', $filePolicy['embedded_file_filter_status'] ?? null);
        $t->same('suppressed_encrypted_strings', $filePolicy['file_spec_strings_policy'] ?? null);
        $t->same('preserved_identity_crypt_filter', $filePolicy['embedded_file_stream_policy'] ?? null);
        $t->same(true, $filePolicy['payload_hash_available'] ?? null);
        $t->same(false, $filePolicy['payload_content_included'] ?? null);

        $t->same(false, $report['executes_decryption']);
        $t->same(false, $report['executes_permission_enforcement']);
        $t->same(false, $report['executes_python_or_models']);
        $t->same(false, $report['executes_external_pdf_tools']);
        $t->true(is_string($encoded)
            && !str_contains($encoded, $content)
            && !str_contains($encoded, $payload)
            && !str_contains($encoded, $ownerKey)
            && !str_contains($encoded, $userKey)
            && !str_contains($encoded, strtoupper(bin2hex($ownerKey)))
            && !str_contains($encoded, strtoupper(bin2hex($userKey))));
    },
];
