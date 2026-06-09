<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentTypeKeyBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-type-boundary"/></wp-export>';
    $duplicateFileSpecPayload = '<wp-export><post id="duplicate-filespec-type-leak"/></wp-export>';
    $duplicateStreamTypePayload = '<wp-export><post id="duplicate-stream-type-leak"/></wp-export>';
    $duplicateSubtypePayload = '<wp-export><post id="duplicate-stream-subtype-leak"/></wp-export>';
    $validChecksum = md5($validPayload);
    $duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
    $duplicateStreamTypeChecksum = md5($duplicateStreamTypePayload);
    $duplicateSubtypeChecksum = md5($duplicateSubtypePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Attachment Type Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R 40 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names ["
        . "(valid-type.xml) 10 0 R "
        . "(duplicate-filespec-type.xml) 20 0 R "
        . "(duplicate-stream-type.xml) 30 0 R "
        . "(duplicate-stream-subtype.xml) 40 0 R"
        . "] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (valid-type.xml) /Desc (Valid type boundary source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260609000817Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Catalog /Type /Filespec /F (duplicate-filespec-type.xml) /Desc (Duplicate FileSpec Type should stay private) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
        . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (duplicate-stream-type.xml) /Desc (Duplicate stream Type should stay private) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /Metadata /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateStreamTypePayload) . " /CheckSum <{$duplicateStreamTypeChecksum}> >> /Length " . strlen($duplicateStreamTypePayload) . " >>\n"
        . "stream\n{$duplicateStreamTypePayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /F (duplicate-stream-subtype.xml) /Desc (Duplicate stream Subtype should stay private) /AFRelationship /Data /EF << /F 41 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateSubtypePayload) . " /CheckSum <{$duplicateSubtypeChecksum}> >> /Length " . strlen($duplicateSubtypePayload) . " >>\n"
        . "stream\n{$duplicateSubtypePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $duplicateFileSpecPayload,
        $duplicateStreamTypePayload,
        $duplicateSubtypePayload,
        $validChecksum,
        $duplicateFileSpecChecksum,
        $duplicateStreamTypeChecksum,
        $duplicateSubtypeChecksum,
    ];
};

return [
    'rejects duplicate FileSpec and EmbeddedFile stream Type keys before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentTypeKeyBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $duplicateFileSpecPayload,
            $duplicateStreamTypePayload,
            $duplicateSubtypePayload,
            $validChecksum,
            $duplicateFileSpecChecksum,
            $duplicateStreamTypeChecksum,
            $duplicateSubtypeChecksum,
        ] = $attachmentTypeKeyBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-type.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-type.xml', $attachment['name_key']);
        $t->same('valid-type.xml', $attachment['filename']);
        $t->same('Valid type boundary source', $attachment['description']);
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
        $t->same('D:20260609000817Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-type.xml', $file['name']);
        $t->same('valid-type.xml', $file['filename']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Attachment Type Boundary Body', $plainText);
        foreach ([
            'duplicate-filespec-type.xml',
            'duplicate-stream-type.xml',
            'duplicate-stream-subtype.xml',
            'Duplicate FileSpec Type should stay private',
            'Duplicate stream Type should stay private',
            'Duplicate stream Subtype should stay private',
            $duplicateFileSpecPayload,
            $duplicateStreamTypePayload,
            $duplicateSubtypePayload,
            $duplicateFileSpecChecksum,
            $duplicateStreamTypeChecksum,
            $duplicateSubtypeChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
