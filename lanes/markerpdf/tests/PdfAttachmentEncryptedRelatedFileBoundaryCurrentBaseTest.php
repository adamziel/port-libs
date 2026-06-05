<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

return [
    'keeps encrypted FileSpec related-file rows as review-only metadata without decoding payloads' => static function (
        TestRunner $t
    ): void {
        $sourcePayload = '<wp-export><post id="encrypted-related-source"/></wp-export>';
        $sourceChecksum = md5($sourcePayload);
        $relatedCiphertext = "ENCRYPTED_RELATED_BYTES_SHOULD_NOT_LEAK";
        $relatedManifestCiphertext = "ENCRYPTED_MANIFEST_BYTES_SHOULD_NOT_LEAK";
        $unicodeRelatedName = iconv('UTF-8', 'UTF-16BE', 'manifest.json');
        if (!is_string($unicodeRelatedName)) {
            throw new RuntimeException('Unable to encode encrypted related filename fixture.');
        }
        $unicodeRelatedNameHex = strtoupper(bin2hex("\xFE\xFF" . $unicodeRelatedName));

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Names [(encrypted-related-source.xml) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (encrypted-related-source.xml) /Desc (Encrypted related file source) /AFRelationship /Source /EF << /F 5 0 R >> /RF << /F [(private-note.txt) 6 0 R] /UF [<{$unicodeRelatedNameHex}> 7 0 R] >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260605100752Z) >> /Length " . strlen($sourcePayload) . " >>\n"
            . "stream\n{$sourcePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Filter /Crypt /Params << /Size 1024 /CheckSum <" . str_repeat('aa', 16) . "> /ModDate (D:20260605100753Z) >> /Length " . strlen($relatedCiphertext) . " >>\n"
            . "stream\n{$relatedCiphertext}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter [/Crypt /FlateDecode] /Params << /Size 2048 /CheckSum <" . str_repeat('bb', 16) . "> >> /Length " . strlen($relatedManifestCiphertext) . " >>\n"
            . "stream\n{$relatedManifestCiphertext}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
            . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 >> /ClearText << /CFM /None /AuthEvent /DocOpen >> >>"
            . " /StmF /ClearText /StrF /ClearText /EFF /StdCF >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(0, $summary['total_bytes']);
        $t->same(['encrypted-related-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('encrypted-related-source.xml', $attachment['name_key']);
        $t->same('encrypted-related-source.xml', $attachment['filename']);
        $t->same('Encrypted related file source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same(true, $attachment['encrypted_payload_suppressed']);
        $t->same(2, $attachment['related_file_count']);

        $related = $attachment['related_files'];
        $t->same('filespec_related_files', $related[0]['source']);
        $t->same('F', $related[0]['rf_key']);
        $t->same(0, $related[0]['related_file_index']);
        $t->same(6, $related[0]['stream_object_id']);
        $t->same('private-note.txt', $related[0]['related_filename']);
        $t->same('rf_name_pair', $related[0]['related_filename_source']);
        $t->same(true, $related[0]['encrypted_payload_suppressed']);
        $t->same(false, $related[0]['raw_encrypted_bytes_exposed']);
        $t->same(false, $related[0]['executes_decryption']);

        $t->same('UF', $related[1]['rf_key']);
        $t->same(0, $related[1]['related_file_index']);
        $t->same(7, $related[1]['stream_object_id']);
        $t->same('manifest.json', $related[1]['related_filename']);
        $t->same(true, $related[1]['encrypted_payload_suppressed']);
        $t->same(false, $related[1]['raw_encrypted_bytes_exposed']);
        $t->same(false, $related[1]['executes_decryption']);

        foreach ([$attachment, $related[0], $related[1]] as $row) {
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
                'filters',
                'bytes',
            ] as $suppressedKey) {
                $t->same(false, array_key_exists($suppressedKey, $row));
            }
        }

        $policy = $attachment['encryption_policy'];
        $t->same('StdCF', $policy['embedded_file_filter']);
        $t->same('encrypted_crypt_filter', $policy['embedded_file_filter_status']);
        $t->same('suppressed_encrypted_embedded_file_streams', $policy['embedded_file_stream_policy']);
        $t->same(false, $policy['payload_hash_available']);

        $t->same($policy, $related[0]['encryption_policy']);
        $t->same($policy, $related[1]['encryption_policy']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $sourceChecksum));
        $t->true(is_string($encoded) && !str_contains($encoded, $relatedCiphertext));
        $t->true(is_string($encoded) && !str_contains($encoded, $relatedManifestCiphertext));
    },
];
