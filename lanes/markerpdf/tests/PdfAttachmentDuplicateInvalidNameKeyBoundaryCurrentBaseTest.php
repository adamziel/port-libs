<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentDuplicateInvalidNameKeyPdf = static function (): array {
    $payload = '<wp-export><post id="duplicate-invalid-key-current"/></wp-export>';
    $checksum = md5($payload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate Invalid Name Key Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 20 0 R (source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (current-source.xml) /Desc (Current duplicate-key WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605164243Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (invalid-source.xml) /Desc (Invalid duplicate missing EF) /AFRelationship /Alternative >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'continues past malformed first duplicate EmbeddedFiles name-tree FileSpecs before attachment preflight' => static function (
        TestRunner $t
    ) use ($attachmentDuplicateInvalidNameKeyPdf): void {
        [$pdf, $payload, $checksum] = $attachmentDuplicateInvalidNameKeyPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['current-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('source.xml', $attachment['name_key']);
        $t->same('current-source.xml', $attachment['filename']);
        $t->same('Current duplicate-key WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(hash('sha256', $payload), $attachment['sha256']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605164243Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('catalog_names_embedded_files', $files[0]['source']);
        $t->same('source.xml', $files[0]['name']);
        $t->same('current-source.xml', $files[0]['filename']);
        $t->same($payload, $files[0]['content']);
        $t->same(true, $files[0]['checksum_matches']);

        $t->same('Duplicate Invalid Name Key Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'invalid-source.xml'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Invalid duplicate missing EF'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'invalid-source.xml'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
