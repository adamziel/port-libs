<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentEncryptedStreamTypeBoundaryPdf = static function (): array {
    $decoyPayload = '<wp-export><post id="encrypted-xobject-decoy"/></wp-export>';
    $validPayload = '<wp-export><post id="encrypted-embedded-source"/></wp-export>';
    $decoyChecksum = md5($decoyPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted Attachment Stream Type Body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(encrypted-xobject-decoy.xml) 10 0 R (valid-encrypted-source.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (encrypted-xobject-decoy.xml) /Desc (Encrypted typed stream decoy) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\n"
        . "stream\n{$decoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-encrypted-source.xml) /Desc (Encrypted valid EmbeddedFile source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608232423Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
        . " /CF << /ClearStrings << /CFM /None /AuthEvent /DocOpen >> /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
        . " /StmF /ClearStrings /StrF /ClearStrings /EFF /StdCF >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 30 0 R >>\n%%EOF";

    return [$pdf, $decoyPayload, $validPayload, $decoyChecksum, $validChecksum];
};

return [
    'rejects encrypted typed non-EmbeddedFile EF streams before attachment summaries' => static function (
        TestRunner $t
    ) use ($attachmentEncryptedStreamTypeBoundaryPdf): void {
        [$pdf, $decoyPayload, $validPayload, $decoyChecksum, $validChecksum] = $attachmentEncryptedStreamTypeBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(0, $summary['total_bytes']);
        $t->same(['valid-encrypted-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(1, $attachment['associated_file_index']);
        $t->same('valid-encrypted-source.xml', $attachment['name_key']);
        $t->same('valid-encrypted-source.xml', $attachment['filename']);
        $t->same('Encrypted valid EmbeddedFile source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(20, $attachment['file_spec_object_id']);
        $t->same(21, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same(true, $attachment['encrypted_payload_suppressed']);
        $t->same(false, $attachment['raw_encrypted_bytes_exposed']);
        $t->same(false, $attachment['executes_decryption']);

        $policy = $attachment['encryption_policy'];
        $t->same('suppressed_encrypted_embedded_file_streams', $policy['embedded_file_stream_policy']);
        $t->same('preserved_identity_crypt_filter', $policy['file_spec_strings_policy']);
        $t->same(false, $policy['payload_hash_available']);
        $t->same(false, $policy['payload_content_included']);

        foreach ([
            'content_type',
            'declared_size',
            'byte_length',
            'sha256',
            'checksum_hex',
            'computed_checksum_hex',
            'checksum_matches',
            'modified_at',
            'bytes',
        ] as $suppressedKey) {
            $t->same(false, array_key_exists($suppressedKey, $attachment));
        }

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same('catalog_af', $file['associated_file_source']);
        $t->same(1, $file['associated_file_index']);
        $t->same('valid-encrypted-source.xml', $file['name']);
        $t->same('valid-encrypted-source.xml', $file['filename']);
        $t->same('Encrypted valid EmbeddedFile source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(20, $file['file_spec_object']);
        $t->same(21, $file['embedded_file_object']);
        $t->same(true, $file['encrypted_payload_suppressed']);
        $t->same(false, array_key_exists('content', $file));
        $t->same(false, array_key_exists('checksum', $file));

        $t->same('', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'encrypted-xobject-decoy.xml',
            'Encrypted typed stream decoy',
            $decoyPayload,
            $validPayload,
            $decoyChecksum,
            $validChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
