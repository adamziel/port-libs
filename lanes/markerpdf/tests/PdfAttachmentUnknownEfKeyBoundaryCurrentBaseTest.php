<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentUnknownEfKeyBoundaryPdf = static function (): array {
    $unknownPayload = '<wp-export><post id="unknown-ef-key-decoy"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-standard-ef-key"/></wp-export>';
    $unknownChecksum = md5($unknownPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Unknown EF Key Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(unknown-private-ef.xml) 10 0 R (valid-standard-ef.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (unknown-private-ef.xml) /Desc (Unknown EF key should stay review-only) /AFRelationship /Alternative /EF << /Private 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($unknownPayload) . " /CheckSum <{$unknownChecksum}> >> /Length " . strlen($unknownPayload) . " >>\n"
        . "stream\n{$unknownPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-standard-ef.xml) /Desc (Valid standard EF key WordPress source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608215631Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $unknownPayload, $validPayload, $unknownChecksum, $validChecksum];
};

return [
    'rejects unknown FileSpec EF dictionary keys before WordPress attachment summaries' => static function (
        TestRunner $t
    ) use ($attachmentUnknownEfKeyBoundaryPdf): void {
        [$pdf, $unknownPayload, $validPayload, $unknownChecksum, $validChecksum] = $attachmentUnknownEfKeyBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-standard-ef.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-standard-ef.xml', $attachment['name_key']);
        $t->same('valid-standard-ef.xml', $attachment['filename']);
        $t->same('Valid standard EF key WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(20, $attachment['file_spec_object_id']);
        $t->same(21, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608215631Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-standard-ef.xml', $file['name']);
        $t->same('valid-standard-ef.xml', $file['filename']);
        $t->same('Valid standard EF key WordPress source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(20, $file['file_spec_object']);
        $t->same(21, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same(strlen($validPayload), $file['size']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Unknown EF Key Boundary Body', $plainText);
        foreach ([
            'unknown-private-ef.xml',
            'Unknown EF key should stay review-only',
            $unknownPayload,
            $unknownChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
