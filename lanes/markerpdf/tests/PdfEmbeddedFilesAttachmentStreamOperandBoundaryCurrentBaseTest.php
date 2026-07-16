<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFilesAttachmentStreamOperandBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-stream-dictionary-boundary"/></wp-export>';
    $paramsOperandPayload = '<wp-export><post id="bad-stream-params-operand"/></wp-export>';
    $decodeParmsOperandPayload = '<wp-export><post id="bad-stream-decodeparms-operand"/></wp-export>';
    $validChecksum = md5($validPayload);
    $paramsOperandChecksum = md5($paramsOperandPayload);
    $decodeParmsOperandChecksum = md5($decodeParmsOperandPayload);
    $decodeParmsOperandEncoded = gzcompress($decodeParmsOperandPayload);
    if (!is_string($decodeParmsOperandEncoded)) {
        throw new RuntimeException('Unable to compress focused attachment stream-operand fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Stream Operand Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(bad-params-operand.xml) 10 0 R (bad-decodeparms-operand.xml) 20 0 R (valid-stream-boundary.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (bad-params-operand.xml) /Desc (Bad top-level Params operand source) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($paramsOperandPayload) . " /CheckSum <{$paramsOperandChecksum}> /ModDate (D:20260608052619Z) >> 44 /Length " . strlen($paramsOperandPayload) . " >>\n"
        . "stream\n{$paramsOperandPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (bad-decodeparms-operand.xml) /Desc (Bad top-level DecodeParms operand source) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /DecodeParms << /Predictor 1 >> 17 /Params << /Size " . strlen($decodeParmsOperandPayload) . " /CheckSum <{$decodeParmsOperandChecksum}> /ModDate (D:20260608052620Z) >> /Length " . strlen($decodeParmsOperandEncoded) . " >>\n"
        . "stream\n{$decodeParmsOperandEncoded}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-stream-boundary.xml) /Desc (Valid stream dictionary operand source) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608052621Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $paramsOperandPayload,
        $decodeParmsOperandPayload,
        $validChecksum,
        $paramsOperandChecksum,
        $decodeParmsOperandChecksum,
    ];
};

return [
    'rejects embedded-file stream dictionaries with trailing boundary operands before attachment import' => static function (
        TestRunner $t
    ) use ($embeddedFilesAttachmentStreamOperandBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $paramsOperandPayload,
            $decodeParmsOperandPayload,
            $validChecksum,
            $paramsOperandChecksum,
            $decodeParmsOperandChecksum,
        ] = $embeddedFilesAttachmentStreamOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-stream-boundary.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('valid-stream-boundary.xml', $attachment['name_key']);
        $t->same('valid-stream-boundary.xml', $attachment['filename']);
        $t->same('Valid stream dictionary operand source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608052621Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-stream-boundary.xml', $file['name']);
        $t->same('valid-stream-boundary.xml', $file['filename']);
        $t->same('Valid stream dictionary operand source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(30, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same(strlen($validPayload), $file['declared_size']);
        $t->same(strlen($validPayload), $file['size']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible Attachment Stream Operand Boundary', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'bad-params-operand.xml',
            'bad-decodeparms-operand.xml',
            'Bad top-level Params operand source',
            'Bad top-level DecodeParms operand source',
            $paramsOperandPayload,
            $decodeParmsOperandPayload,
            $paramsOperandChecksum,
            $decodeParmsOperandChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
