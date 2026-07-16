<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'suppresses encrypted EFF payloads in direct embedded-file inventories without exposing raw bytes' => static function (TestRunner $t): void {
        $payload = '<wp-export><post id="embedded-encrypted-eff-boundary"/></wp-export>';
        $relatedPayload = 'related-private-wordpress-export-payload';
        $payloadChecksum = md5($payload);
        $relatedChecksum = md5($relatedPayload);
        $visibleText = 'Embedded Encrypted EFF Boundary Body';
        $content = 'BT /F1 12 Tf 72 720 Td (' . $visibleText . ') Tj ET';

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(encrypted-embedded.xml) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (encrypted-embedded.xml) /Desc (Encrypted EFF WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(related-private.txt) 12 0 R] >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$payloadChecksum}> /ModDate (D:20260606064050Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\n"
            . "stream\n{$relatedPayload}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
            . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /ClearText << /CFM /None /AuthEvent /DocOpen >> >>"
            . " /StmF /ClearText /StrF /ClearText /EFF /StdCF >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 20 0 R >>\n%%EOF\n";

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same('catalog_af', $file['associated_file_source']);
        $t->same(0, $file['associated_file_index']);
        $t->same('encrypted-embedded.xml', $file['name']);
        $t->same('encrypted-embedded.xml', $file['filename']);
        $t->same('Encrypted EFF WordPress source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same('F', $file['ef_key']);
        $t->same(true, $file['encrypted_payload_suppressed']);
        $t->same(false, $file['raw_encrypted_bytes_exposed']);
        $t->same(false, $file['executes_decryption']);

        $policy = $file['encryption_policy'];
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
            'content',
            'size',
            'content_sha256',
            'checksum',
            'computed_checksum',
            'checksum_matches',
            'declared_size',
            'declared_size_matches',
            'mime_type',
            'filters',
            'modified_at',
            'modified_at_utc',
        ] as $suppressedKey) {
            $t->same(false, array_key_exists($suppressedKey, $file));
        }

        $t->same(1, $file['related_file_count']);
        $related = $file['related_files'][0];
        $t->same('filespec_related_files', $related['source']);
        $t->same('F', $related['rf_key']);
        $t->same(0, $related['related_file_index']);
        $t->same(12, $related['embedded_file_object']);
        $t->same('related-private.txt', $related['related_filename']);
        $t->same(false, $related['payload_included']);
        $t->same(true, $related['encrypted_payload_suppressed']);
        $t->same(false, $related['raw_encrypted_bytes_exposed']);
        $t->same(false, $related['executes_decryption']);
        foreach (['size', 'content_sha256', 'checksum', 'computed_checksum', 'checksum_matches', 'declared_size', 'mime_type'] as $suppressedKey) {
            $t->same(false, array_key_exists($suppressedKey, $related));
        }

        $t->same(1, $summary['attachment_count']);
        $t->same(0, $summary['total_bytes']);
        $t->same(['encrypted-embedded.xml'], $summary['filenames']);
        $t->same(true, $summary['attachments'][0]['encrypted_payload_suppressed']);
        $t->same('', $plainText);
        $t->true(!str_contains($plainText, $payload));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $payload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $relatedPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $payloadChecksum));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $relatedChecksum));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $relatedPayload));
    },
];
