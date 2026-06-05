<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentDecodedLengthBoundaryPdf = static function (): array {
    $payload = '<wp-export><post id="decoded-length-source"/></wp-export>';
    $relatedPayload = '{"manifest":"decoded-length-related"}';
    $compressedPayload = gzcompress($payload);
    if (!is_string($compressedPayload)) {
        throw new RuntimeException('Unable to compress decoded-length attachment fixture.');
    }

    $checksum = md5($payload);
    $relatedChecksum = md5($relatedPayload);
    $staleRelatedDecodedLength = strlen($relatedPayload) + 7;
    $content = 'BT /F1 12 Tf 72 720 Td (Decoded Length Attachment Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(decoded-source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (decoded-source.xml) /Desc (Decoded length WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(decoded-related.json) 12 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /DL " . strlen($payload) . " /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605122914Z) >> /Length " . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /DL {$staleRelatedDecodedLength} /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $relatedPayload, $staleRelatedDecodedLength];
};

return [
    'carries EmbeddedFile stream DL decoded-length review metadata through attachment summaries' => static function (
        TestRunner $t
    ) use ($attachmentDecodedLengthBoundaryPdf): void {
        [$pdf, $payload, $relatedPayload, $staleRelatedDecodedLength] = $attachmentDecodedLengthBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['decoded-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('decoded-source.xml', $attachment['name_key']);
        $t->same('decoded-source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(['FlateDecode'], $attachment['filters']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['decoded_length']);
        $t->same(true, $attachment['decoded_length_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605122914Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $related = $attachment['related_files'][0] ?? null;
        $t->true(is_array($related));
        $t->same('filespec_related_files', $related['source']);
        $t->same('decoded-related.json', $related['related_filename']);
        $t->same('rf_name_pair', $related['related_filename_source']);
        $t->same('application/json', $related['content_type']);
        $t->same(strlen($relatedPayload), $related['declared_size']);
        $t->same(true, $related['declared_size_matches']);
        $t->same($staleRelatedDecodedLength, $related['decoded_length']);
        $t->same(false, $related['decoded_length_matches']);
        $t->same(strlen($relatedPayload), $related['byte_length']);
        $t->same(true, $related['checksum_matches']);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('decoded-source.xml', $file['name']);
        $t->same('decoded-source.xml', $file['filename']);
        $t->same(strlen($payload), $file['decoded_length']);
        $t->same(true, $file['decoded_length_matches']);
        $t->same($payload, $file['content']);
        $t->same('associated_file_provenance', $file['provenance_review']['source'] ?? null);
        $t->same(strlen($payload), $file['provenance_review']['payload']['decoded_length'] ?? null);
        $t->same(true, $file['provenance_review']['payload']['decoded_length_matches'] ?? null);
        $t->same($staleRelatedDecodedLength, $file['related_files'][0]['decoded_length'] ?? null);
        $t->same(false, $file['related_files'][0]['decoded_length_matches'] ?? null);

        $t->same('Decoded Length Attachment Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $payload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $relatedPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'decoded-length-related'));
    },
];
