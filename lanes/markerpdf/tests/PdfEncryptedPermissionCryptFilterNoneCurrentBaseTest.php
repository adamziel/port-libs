<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$cryptFilterNonePdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (CFM None encrypted page text leak) Tj ET';
    $payload = '<wp-export><post id="cfm-none-attachment"/></wp-export>';
    $ownerKey = 'CFM_NONE_OWNER_KEY_SHOULD_NOT_LEAK';
    $userKey = 'CFM_NONE_USER_KEY_SHOULD_NOT_LEAK';

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
        . " /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >>"
        . " /ClearStrings << /CFM /None /AuthEvent /DocOpen >>"
        . " /ClearEmbedded << /CFM /None /AuthEvent /EFOpen >>"
        . " >>"
        . " /StmF /StdCF /StrF /ClearStrings /EFF /ClearEmbedded >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (clear-cfm-none.xml) /UF (clear-cfm-none.xml) /Desc (Clear CFM None attachment metadata) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $payload, $ownerKey, $userKey];
};

return [
    'treats named crypt-filter CFM None as unencrypted review metadata while page text remains blocked' => static function (
        TestRunner $t
    ) use ($cryptFilterNonePdf): void {
        [$pdf, $content, $payload, $ownerKey, $userKey] = $cryptFilterNonePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $file = $metadata['associated_files'][0] ?? [];
        $filePolicy = $file['encryption_policy'] ?? [];
        $encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(false, $permission['native_text_extraction_allowed_now']);

        $t->same('AESV2', $metadata['encryption']['crypt_filters']['StdCF']['method']);
        $t->same('None', $metadata['encryption']['crypt_filters']['ClearStrings']['method']);
        $t->same('None', $metadata['encryption']['crypt_filters']['ClearEmbedded']['method']);
        $t->same('StdCF', $metadata['encryption']['stream_filter']);
        $t->same('ClearStrings', $metadata['encryption']['string_filter']);
        $t->same('ClearEmbedded', $metadata['encryption']['embedded_file_filter']);

        $t->same('review_only_encrypted_document_boundary', $review['text_content_policy']);
        $t->same('identity_filter_review_only_payload_boundary', $review['embedded_file_payload_policy']);
        $t->same(['document_strings', 'embedded_file_streams'], $review['identity_role_names']);
        $t->same(['document_streams'], $review['encrypted_role_names']);
        $t->same([], $review['missing_role_names']);
        $t->same([], $review['unsupported_role_names']);
        $t->same(['encrypted_crypt_filter', 'identity_crypt_filter'], $review['role_statuses']);
        $t->same(['ClearStrings', 'ClearEmbedded'], $review['identity_filter_names']);
        $t->same(['StdCF'], $review['encrypted_filter_names']);

        $streamRole = $review['roles'][0];
        $stringRole = $review['roles'][1];
        $embeddedRole = $review['roles'][2];
        $t->same('document_streams', $streamRole['role']);
        $t->same('encrypted_crypt_filter', $streamRole['status']);
        $t->same(true, $streamRole['content_encrypted']);
        $t->same('document_strings', $stringRole['role']);
        $t->same('None', $stringRole['method']);
        $t->same('identity_crypt_filter', $stringRole['status']);
        $t->same(false, $stringRole['content_encrypted']);
        $t->same('embedded_file_streams', $embeddedRole['role']);
        $t->same('None', $embeddedRole['method']);
        $t->same('identity_crypt_filter', $embeddedRole['status']);
        $t->same(false, $embeddedRole['content_encrypted']);

        $t->same('clear-cfm-none.xml', $file['filename'] ?? null);
        $t->same('Clear CFM None attachment metadata', $file['description'] ?? null);
        $t->same(hash('sha256', $payload), $file['content_sha256'] ?? null);
        $t->same(strtolower(hash('md5', $payload)), $file['computed_checksum'] ?? null);
        $t->same(true, $file['checksum_matches'] ?? null);
        $t->same('encrypted_associated_file_review', $filePolicy['source'] ?? null);
        $t->same('ClearStrings', $filePolicy['string_filter'] ?? null);
        $t->same('ClearEmbedded', $filePolicy['embedded_file_filter'] ?? null);
        $t->same('preserved_identity_crypt_filter', $filePolicy['file_spec_strings_policy'] ?? null);
        $t->same('preserved_identity_crypt_filter', $filePolicy['embedded_file_stream_policy'] ?? null);
        $t->same(true, $filePolicy['payload_hash_available'] ?? null);
        $t->same(false, $filePolicy['payload_content_included'] ?? null);

        $t->same(false, $report['executes_decryption']);
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
