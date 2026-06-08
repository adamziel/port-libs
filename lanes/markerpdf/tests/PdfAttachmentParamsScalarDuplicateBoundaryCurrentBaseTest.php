<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentDuplicateParamsScalarBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-inner-params-boundary"/></wp-export>';
    $relatedPayload = '{"manifest":"valid-inner-params-related"}';
    $duplicatePayload = '<wp-export><post id="duplicate-inner-params-should-not-count"/></wp-export>';
    $duplicateRelatedPayload = 'DUPLICATE_INNER_PARAMS_RELATED_PAYLOAD_SHOULD_NOT_COUNT';
    $validChecksum = md5($validPayload);
    $relatedChecksum = md5($relatedPayload);
    $duplicateChecksum = md5($duplicatePayload);
    $duplicateRelatedChecksum = md5($duplicateRelatedPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Inner Params Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(valid-inner-source.xml) 10 0 R (duplicate-inner-params.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (valid-inner-source.xml) /Desc (Valid inner Params WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(duplicate-inner-related.json) 12 0 R (valid-inner-related.json) 13 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607235600Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($duplicateRelatedPayload) . " /Size 1 /CheckSum <{$duplicateRelatedChecksum}> /CheckSum <00000000000000000000000000000000> /CreationDate (D:20260607235601Z) /CreationDate (D:20260607235602Z) >> /Length " . strlen($duplicateRelatedPayload) . " >>\nstream\n{$duplicateRelatedPayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> /CreationDate (D:20260607235603Z) >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (duplicate-inner-params.xml) /Desc (Duplicate inner Params WordPress source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /Size 2 /CheckSum <{$duplicateChecksum}> /CheckSum <11111111111111111111111111111111> /ModDate (D:20260607235604Z) /ModDate (D:20260607235605Z) >> /Length " . strlen($duplicatePayload) . " >>\nstream\n{$duplicatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $validPayload, $relatedPayload, $duplicatePayload, $duplicateRelatedPayload, $duplicateChecksum, $duplicateRelatedChecksum];
};

return [
    'fails closed on duplicate EmbeddedFile Params scalar metadata before attachment review' => static function (
        TestRunner $t
    ) use ($attachmentDuplicateParamsScalarBoundaryPdf): void {
        [$pdf, $validPayload, $relatedPayload, $duplicatePayload, $duplicateRelatedPayload, $duplicateChecksum, $duplicateRelatedChecksum] = $attachmentDuplicateParamsScalarBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-inner-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('valid-inner-source.xml', $attachment['name_key']);
        $t->same('valid-inner-source.xml', $attachment['filename']);
        $t->same('Valid inner Params WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum = md5($validPayload), $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607235600Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $related = $attachment['related_files'][0] ?? null;
        $t->true(is_array($related));
        $t->same('filespec_related_files', $related['source']);
        $t->same('F', $related['rf_key']);
        $t->same(0, $related['related_file_index']);
        $t->same('valid-inner-related.json', $related['related_filename']);
        $t->same('application/json', $related['content_type']);
        $t->same(strlen($relatedPayload), $related['declared_size']);
        $t->same(true, $related['declared_size_matches']);
        $t->same(strlen($relatedPayload), $related['byte_length']);
        $t->same($relatedChecksum = md5($relatedPayload), $related['checksum_hex']);
        $t->same($relatedChecksum, $related['computed_checksum_hex']);
        $t->same(true, $related['checksum_matches']);
        $t->same('D:20260607235603Z', $related['created_at']);
        $t->same(false, array_key_exists('bytes', $related));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-inner-source.xml', $file['name']);
        $t->same('valid-inner-source.xml', $file['filename']);
        $t->same($validPayload, $file['content']);
        $t->same(strlen($validPayload), $file['declared_size']);
        $t->same(strlen($validPayload), $file['size']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(1, $file['related_file_count']);
        $t->same('valid-inner-related.json', $file['related_files'][0]['related_filename']);
        $t->same(strlen($relatedPayload), $file['related_files'][0]['declared_size']);
        $t->same(true, $file['related_files'][0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $file['related_files'][0]));

        $t->same('Inner Params Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'duplicate-inner-params.xml'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'duplicate-inner-related.json'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $duplicatePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $duplicateRelatedPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $duplicateChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $duplicateRelatedChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $validPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $relatedPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $duplicatePayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $duplicateRelatedPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'DUPLICATE_INNER_PARAMS_RELATED_PAYLOAD'));
    },
];
