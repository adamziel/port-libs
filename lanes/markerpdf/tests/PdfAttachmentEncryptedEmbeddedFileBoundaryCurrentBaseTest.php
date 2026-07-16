<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

return [
    'suppresses encrypted EFF payload metadata in attachment preflight summaries' => static function (TestRunner $t): void {
        $payload = '<wp-export><post id="encrypted-eff"/></wp-export>';
        $checksum = md5($payload);

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Names [(encrypted-source.xml) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (encrypted-source.xml) /Desc (Encrypted EFF WordPress source) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605043836Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
            . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearText << /CFM /None /AuthEvent /DocOpen >> >>"
            . " /StmF /ClearText /StrF /ClearText /EFF /StdCF >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(0, $summary['total_bytes']);
        $t->same(['encrypted-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('encrypted-source.xml', $attachment['filename']);
        $t->same('Encrypted EFF WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same(true, $attachment['encrypted_payload_suppressed']);
        $t->same(false, $attachment['raw_encrypted_bytes_exposed']);
        $t->same(false, $attachment['executes_decryption']);

        $policy = $attachment['encryption_policy'];
        $t->same('encrypted_attachment_preflight', $policy['source']);
        $t->same(true, $policy['encrypted_document']);
        $t->same('ClearText', $policy['stream_filter']);
        $t->same('identity_crypt_filter', $policy['stream_filter_status']);
        $t->same('ClearText', $policy['string_filter']);
        $t->same('identity_crypt_filter', $policy['string_filter_status']);
        $t->same('StdCF', $policy['embedded_file_filter']);
        $t->same('encrypted_crypt_filter', $policy['embedded_file_filter_status']);
        $t->same('preserved_identity_crypt_filter', $policy['file_spec_strings_policy']);
        $t->same('suppressed_encrypted_embedded_file_streams', $policy['embedded_file_stream_policy']);
        $t->same(false, $policy['payload_hash_available']);
        $t->same(false, $policy['payload_content_included']);

        foreach ([
            'byte_length',
            'sha256',
            'checksum_hex',
            'computed_checksum_hex',
            'checksum_matches',
            'declared_size',
            'declared_size_matches',
            'content_type',
            'modified_at',
            'bytes',
        ] as $suppressedKey) {
            $t->same(false, array_key_exists($suppressedKey, $attachment));
        }

        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(is_string($encoded) && !str_contains($encoded, $checksum));
    },

    'keeps identity EFF payload hashes while suppressing encrypted FileSpec strings and bytes' => static function (TestRunner $t): void {
        $payload = '<wp-export><post id="identity-eff"/></wp-export>';
        $checksum = md5($payload);

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Names [(identity-eff-source.xml) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (identity-eff-source.xml) /Desc (Identity EFF WordPress source) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /CreationDate (D:20260605043800Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
            . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearFiles << /CFM /None /AuthEvent /DocOpen >> >>"
            . " /StmF /ClearFiles /StrF /StdCF /EFF /ClearFiles >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same([], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(hash('sha256', $payload), $attachment['sha256']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, array_key_exists('filename', $attachment));
        $t->same(false, array_key_exists('filename_source', $attachment));
        $t->same(false, array_key_exists('name_key', $attachment));
        $t->same(false, array_key_exists('description', $attachment));
        $t->same(false, $attachment['raw_encrypted_bytes_exposed']);
        $t->same(false, $attachment['executes_decryption']);

        $policy = $attachment['encryption_policy'];
        $t->same('StdCF', $policy['string_filter']);
        $t->same('encrypted_crypt_filter', $policy['string_filter_status']);
        $t->same('ClearFiles', $policy['embedded_file_filter']);
        $t->same('identity_crypt_filter', $policy['embedded_file_filter_status']);
        $t->same('suppressed_encrypted_strings', $policy['file_spec_strings_policy']);
        $t->same('preserved_identity_crypt_filter', $policy['embedded_file_stream_policy']);
        $t->same(true, $policy['payload_hash_available']);
        $t->same(false, $policy['payload_content_included']);

        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'identity-eff-source.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Identity EFF WordPress source'));
    },
];
