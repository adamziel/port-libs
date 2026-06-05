<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$directFileSpecNameTreeMirrorPdf = static function (): array {
    $payload = '<wp-export><post id="direct-nameless-filespec"/></wp-export>';
    $checksum = md5($payload);
    $content = 'BT /F1 12 Tf 72 720 Td (Direct FileSpec Mirror Body) Tj ET';
    $fileSpec = '<< /Type /Filespec /Desc (Direct nameless WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>';
    $catalogMirror = '<< /Type /Filespec /Desc (Catalog mirror should not duplicate) /AFRelationship /Source /EF << /F 11 0 R >> >>';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [{$catalogMirror}] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(wp-source.xml) {$fileSpec}] >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605172105Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'dedupes direct nameless FileSpec mirrors using EmbeddedFiles name-tree filename before attachment import' => static function (
        TestRunner $t
    ) use ($directFileSpecNameTreeMirrorPdf): void {
        [$pdf, $payload, $checksum] = $directFileSpecNameTreeMirrorPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['wp-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('wp-source.xml', $attachment['name_key']);
        $t->same('wp-source.xml', $attachment['filename']);
        $t->same('name_tree_key', $attachment['filename_source']);
        $t->same(null, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same('Direct nameless WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(hash('sha256', $payload), $attachment['sha256']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605172105Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('wp-source.xml', $file['name']);
        $t->same('wp-source.xml', $file['filename']);
        $t->same('name_tree_key', $file['filename_source']);
        $t->same(null, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same('F', $file['ef_key']);
        $t->same($payload, $file['content']);
        $t->same(strlen($payload), $file['size']);
        $t->same(hash('sha256', $payload), $file['content_sha256']);
        $t->same($checksum, $file['checksum']);
        $t->same($checksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Direct FileSpec Mirror Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'attachment-11'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'Catalog mirror should not duplicate'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $payload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, 'embedded-file'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
