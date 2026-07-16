<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentParamsTrailingOperandPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-params-trailing-boundary"/></wp-export>';
    $validRelatedPayload = '{"manifest":"valid-params-trailing-related"}';
    $badPrimaryPayload = '<wp-export><post id="bad-primary-params-trailing"/></wp-export>';
    $badRelatedPayload = 'BAD_RELATED_PARAMS_TRAILING_PAYLOAD_SHOULD_NOT_COUNT';
    $validChecksum = md5($validPayload);
    $validRelatedChecksum = md5($validRelatedPayload);
    $badPrimaryChecksum = md5($badPrimaryPayload);
    $badRelatedChecksum = md5($badRelatedPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Params Trailing Operand Attachment Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(valid-trailing-source.xml) 10 0 R (bad-primary-trailing.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (valid-trailing-source.xml) /Desc (Valid Params trailing boundary source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(bad-related-trailing.json) 12 0 R (valid-related-trailing.json) 13 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608024042Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($badRelatedPayload) . " 90 0 R /CheckSum <{$badRelatedChecksum}> /CreationDate (D:20260608024043Z) >> /Length " . strlen($badRelatedPayload) . " >>\nstream\n{$badRelatedPayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($validRelatedPayload) . " /CheckSum <{$validRelatedChecksum}> /CreationDate (D:20260608024044Z) >> /Length " . strlen($validRelatedPayload) . " >>\nstream\n{$validRelatedPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (bad-primary-trailing.xml) /Desc (Bad primary Params trailing source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badPrimaryPayload) . " 91 0 R /CheckSum <{$badPrimaryChecksum}> /ModDate (D:20260608024045Z) >> /Length " . strlen($badPrimaryPayload) . " >>\nstream\n{$badPrimaryPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $validRelatedPayload,
        $badPrimaryPayload,
        $badRelatedPayload,
        $validChecksum,
        $validRelatedChecksum,
        $badPrimaryChecksum,
        $badRelatedChecksum,
    ];
};

return [
    'fails closed on EmbeddedFile Params trailing operands before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentParamsTrailingOperandPdf): void {
        [
            $pdf,
            $validPayload,
            $validRelatedPayload,
            $badPrimaryPayload,
            $badRelatedPayload,
            $validChecksum,
            $validRelatedChecksum,
            $badPrimaryChecksum,
            $badRelatedChecksum,
        ] = $attachmentParamsTrailingOperandPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-trailing-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('valid-trailing-source.xml', $attachment['name_key']);
        $t->same('valid-trailing-source.xml', $attachment['filename']);
        $t->same('Valid Params trailing boundary source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608024042Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $related = $attachment['related_files'][0] ?? null;
        $t->true(is_array($related));
        $t->same(1, $attachment['related_file_count']);
        $t->same('filespec_related_files', $related['source']);
        $t->same('F', $related['rf_key']);
        $t->same(0, $related['related_file_index']);
        $t->same('valid-related-trailing.json', $related['related_filename']);
        $t->same('application/json', $related['content_type']);
        $t->same(13, $related['stream_object_id']);
        $t->same(strlen($validRelatedPayload), $related['declared_size']);
        $t->same(true, $related['declared_size_matches']);
        $t->same(strlen($validRelatedPayload), $related['byte_length']);
        $t->same($validRelatedChecksum, $related['checksum_hex']);
        $t->same($validRelatedChecksum, $related['computed_checksum_hex']);
        $t->same(true, $related['checksum_matches']);
        $t->same('D:20260608024044Z', $related['created_at']);
        $t->same(false, array_key_exists('bytes', $related));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-trailing-source.xml', $file['name']);
        $t->same('valid-trailing-source.xml', $file['filename']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same(strlen($validPayload), $file['declared_size']);
        $t->same(strlen($validPayload), $file['size']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(1, $file['related_file_count']);
        $t->same('valid-related-trailing.json', $file['related_files'][0]['related_filename']);
        $t->same(13, $file['related_files'][0]['embedded_file_object']);
        $t->same(strlen($validRelatedPayload), $file['related_files'][0]['declared_size']);
        $t->same(true, $file['related_files'][0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $file['related_files'][0]));

        $t->same('Params Trailing Operand Attachment Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'bad-primary-trailing.xml',
            'bad-related-trailing.json',
            'Bad primary Params trailing source',
            $badPrimaryPayload,
            $badRelatedPayload,
            $badPrimaryChecksum,
            $badRelatedChecksum,
            $validPayload,
            $validRelatedPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
