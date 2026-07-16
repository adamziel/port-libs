<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fileSpecMetadataBoundaryPdf = static function (): array {
    $payload = '<wp-export><post id="filespec-metadata"/></wp-export>';
    $checksum = md5($payload);
    $permanentIdHex = '00112233445566778899aabbccddeeff';
    $changingId = 'wp-filespec-metadata-v2';
    $changingIdHex = strtoupper(bin2hex($changingId));

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/wp-export.xml) /UF (source.xml) /Desc (URL-backed WordPress source) /AFRelationship /Source /ID [<{$permanentIdHex}> <{$changingIdHex}>] /V true /EF << /UF 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605062438Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $permanentIdHex, strtolower($changingIdHex)];
};

return [
    'carries FileSpec file-system identifier and volatility metadata in attachment preflight' => static function (
        TestRunner $t
    ) use ($fileSpecMetadataBoundaryPdf): void {
        [$pdf, $payload, $permanentIdHex, $changingIdHex] = $fileSpecMetadataBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('source.xml', $attachment['name_key']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('UF', $attachment['filename_source']);
        $t->same('URL-backed WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('URL', $attachment['file_system']);
        $t->same('external_url_file_system_review_only', $attachment['file_system_status']);
        $t->same(true, $attachment['volatile']);
        $t->same('volatile_file_spec_review', $attachment['volatile_status']);
        $t->same($permanentIdHex, $attachment['file_identifier']['permanent_id_hex']);
        $t->same($changingIdHex, $attachment['file_identifier']['changing_id_hex']);
        $t->same(2, $attachment['file_identifier']['identifier_count']);
        $t->same('complete_file_identifier_pair', $attachment['file_identifier']['identifier_status']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum = md5($payload), $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605062438Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
    },
    'propagates FileSpec metadata through embedded-file review rows without visible text leakage' => static function (
        TestRunner $t
    ) use ($fileSpecMetadataBoundaryPdf): void {
        [$pdf, $payload, $permanentIdHex, $changingIdHex] = $fileSpecMetadataBoundaryPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['name']);
        $t->same('source.xml', $file['filename']);
        $t->same('Source', $file['relationship']);
        $t->same('URL', $file['file_system']);
        $t->same('external_url_file_system_review_only', $file['file_system_status']);
        $t->same(true, $file['volatile']);
        $t->same('volatile_file_spec_review', $file['volatile_status']);
        $t->same($permanentIdHex, $file['file_identifier']['permanent_id_hex']);
        $t->same($changingIdHex, $file['file_identifier']['changing_id_hex']);
        $t->same('complete_file_identifier_pair', $file['file_identifier']['identifier_status']);
        $t->same($payload, $file['content']);
        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    },
];
