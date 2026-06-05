<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$malformedEmbeddedFileRolePdf = static function (): array {
    $content = 'BT /F1 12 Tf 72 720 Td (Malformed EFF encrypted text leak) Tj ET';
    $payload = '<wp-export><post id="malformed-eff-attachment"/></wp-export>';
    $ownerKey = str_repeat('M', 32);
    $userKey = str_repeat('E', 32);

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
        . " /ClearStreams << /CFM /None /AuthEvent /DocOpen >>"
        . " /ClearStrings << /CFM /None /AuthEvent /DocOpen >>"
        . " /ClearEmbedded << /CFM /None /AuthEvent /EFOpen >>"
        . " >>"
        . " /StmF /ClearStreams /StrF /ClearStrings /EFF [/ClearEmbedded] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (malformed-eff.xml) /UF (malformed-eff.xml) /Desc (Malformed EFF attachment metadata) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

    return [$pdf, $content, $payload, $ownerKey, $userKey];
};

return [
    'fails closed on malformed EFF role before encrypted attachment payload review' => static function (
        TestRunner $t
    ) use ($malformedEmbeddedFileRolePdf): void {
        [$pdf, $content, $payload, $ownerKey, $userKey] = $malformedEmbeddedFileRolePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $report = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encryption = $metadata['encryption'];
        $roleReview = $encryption['crypt_filter_role_declaration_review'];
        $review = $report['crypt_filter_content_review'];
        $permission = $report['permission_preflight'];
        $associatedFile = $metadata['associated_files'][0] ?? [];
        $attachment = $summary['attachments'][0] ?? [];
        $associatedPolicy = $associatedFile['encryption_policy'] ?? [];
        $attachmentPolicy = $attachment['encryption_policy'] ?? [];
        $encoded = json_encode([$metadata, $summary, $report], JSON_UNESCAPED_SLASHES);

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $report['import_decision']);
        $t->same(['encrypted_document', 'encrypted_text_extraction_blocked', 'copy_or_extract_allowed_but_decryption_required'], $report['review_reasons']);
        $t->same('copy_extract_allowed_after_decryption', $permission['policy']);
        $t->same('blocked_until_decryption_password_available', $permission['content_extraction_boundary']);
        $t->same(false, $permission['crypt_filter_text_fail_closed']);

        $t->same('encryption_crypt_filter_role_declaration_review', $roleReview['source']);
        $t->same(['embedded_file_streams'], $roleReview['malformed_role_names']);
        $t->same(['EFF'], $roleReview['malformed_pdf_names']);
        $t->same(['embedded_file_streams'], $roleReview['fail_closed_role_names']);
        $t->same(['EFF'], $roleReview['fail_closed_pdf_names']);
        $t->same('malformed_crypt_filter_role_entry_review', $roleReview['roles'][2]['status']);
        $t->same(true, $roleReview['roles'][2]['fail_closed']);
        $t->same('array', $roleReview['roles'][2]['entries'][0]['operand_shape']);
        $t->same('crypt_filter_role_entry_non_name_review', $roleReview['roles'][2]['entries'][0]['status']);

        $t->same('identity_filters_review_only_encrypted_document_boundary', $review['text_content_policy']);
        $t->same('malformed_crypt_filter_role_entry_fail_closed', $review['embedded_file_payload_policy']);
        $t->same(['document_streams', 'document_strings', 'embedded_file_streams'], $review['identity_role_names']);
        $t->same(['embedded_file_streams'], $review['fail_closed_role_names']);
        $t->same(['ClearStreams'], $review['fail_closed_filter_names']);
        $t->same(['embedded_file_streams'], $review['role_declaration_fail_closed_role_names']);
        $t->same(['EFF'], $review['role_declaration_fail_closed_pdf_names']);

        $t->same('malformed_crypt_filter_role_entry_fail_closed', $permission['crypt_filter_embedded_file_payload_policy']);
        $t->same(true, $permission['crypt_filter_embedded_file_fail_closed']);
        $t->same('blocked_by_malformed_embedded_file_crypt_filter_role', $permission['crypt_filter_embedded_file_boundary']);
        $t->same(['embedded_file_streams'], $permission['crypt_filter_fail_closed_role_names']);
        $t->same(['ClearStreams'], $permission['crypt_filter_fail_closed_filter_names']);

        $t->same(1, $summary['attachment_count']);
        $t->same('malformed-eff.xml', $attachment['filename']);
        $t->same('Malformed EFF attachment metadata', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same(true, $attachment['encrypted_payload_suppressed']);
        $t->same(false, array_key_exists('sha256', $attachment));
        $t->same(false, array_key_exists('computed_checksum_hex', $attachment));
        $t->same(false, array_key_exists('byte_length', $attachment));
        $t->same('suppressed_malformed_crypt_filter_role', $attachmentPolicy['embedded_file_stream_policy_reason'] ?? null);
        $t->same('malformed_crypt_filter_role_entry_review', $attachmentPolicy['crypt_filter_role_declaration_status'] ?? null);
        $t->same(['embedded_file_streams'], $attachmentPolicy['crypt_filter_role_fail_closed_role_names'] ?? []);
        $t->same(false, $attachmentPolicy['payload_hash_available'] ?? null);

        $t->same('malformed-eff.xml', $associatedFile['filename'] ?? null);
        $t->same('Malformed EFF attachment metadata', $associatedFile['description'] ?? null);
        $t->same(false, array_key_exists('content_sha256', $associatedFile));
        $t->same(false, array_key_exists('computed_checksum', $associatedFile));
        $t->same('suppressed_malformed_crypt_filter_role', $associatedPolicy['embedded_file_stream_policy_reason'] ?? null);
        $t->same('malformed_crypt_filter_role_entry_review', $associatedPolicy['crypt_filter_role_declaration_status'] ?? null);
        $t->same(['embedded_file_streams'], $associatedPolicy['crypt_filter_role_fail_closed_role_names'] ?? []);
        $t->same(false, $associatedPolicy['payload_hash_available'] ?? null);

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
