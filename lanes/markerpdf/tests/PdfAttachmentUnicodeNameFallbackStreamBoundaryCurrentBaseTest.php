<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$unicodeNameFallbackStreamPdf = static function (): array {
    $payload = '<wp-export><post id="unicode-name-fallback-stream"/></wp-export>';
    $checksum = md5($payload);
    $content = 'BT /F1 12 Tf 72 720 Td (Unicode Name Fallback Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(legacy-wordpress-source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-wordpress-source.xml) /UF (wordpress-source-unicode.xml) /Desc (Unicode filename with F-only embedded stream) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260608073746Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'records EF stream fallback when Unicode FileSpec name uses F-only payload stream' => static function (
        TestRunner $t
    ) use ($unicodeNameFallbackStreamPdf): void {
        [$pdf, $payload, $checksum] = $unicodeNameFallbackStreamPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['wordpress-source-unicode.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('legacy-wordpress-source.xml', $attachment['name_key']);
        $t->same('wordpress-source-unicode.xml', $attachment['filename']);
        $t->same('UF', $attachment['filename_source']);
        $t->same('wordpress-source-unicode.xml', $attachment['unicode_filename']);
        $t->same('F', $attachment['ef_key']);
        $t->same('fallback_embedded_file_key', $attachment['ef_key_selection_status']);
        $t->same('UF', $attachment['ef_key_preferred_source']);
        $t->same('Unicode filename with F-only embedded stream', $attachment['description']);
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
        $t->same('D:20260608073746Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('legacy-wordpress-source.xml', $file['name']);
        $t->same('wordpress-source-unicode.xml', $file['filename']);
        $t->same('UF', $file['filename_source']);
        $t->same('wordpress-source-unicode.xml', $file['unicode_filename']);
        $t->same('F', $file['ef_key']);
        $t->same('fallback_embedded_file_key', $file['ef_key_selection_status']);
        $t->same('UF', $file['ef_key_preferred_source']);
        $t->same($payload, $file['content']);
        $t->same(strlen($payload), $file['size']);
        $t->same(hash('sha256', $payload), $file['content_sha256']);
        $t->same($checksum, $file['checksum']);
        $t->same($checksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Unicode Name Fallback Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
