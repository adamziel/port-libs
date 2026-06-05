<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$duplicateFileSpecKeyPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-duplicate-filespec-key-boundary"/></wp-export>';
    $staleFileSpecPayload = '<wp-export><post id="stale-duplicate-filespec-f-key"/></wp-export>';
    $staleEfPayload = '<wp-export><post id="stale-duplicate-ef-key"/></wp-export>';
    $validChecksum = md5($validPayload);
    $staleFileSpecChecksum = md5($staleFileSpecPayload);
    $staleEfChecksum = md5($staleEfPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Duplicate FileSpec Key Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(duplicate-filespec.xml) 10 0 R (duplicate-ef.xml) 20 0 R (valid-source.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (current-filespec.xml) /F (stale-filespec.xml) /Desc (Duplicate FileSpec filename keys) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($staleFileSpecPayload) . " /CheckSum <{$staleFileSpecChecksum}> >> /Length " . strlen($staleFileSpecPayload) . " >>\n"
        . "stream\n{$staleFileSpecPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (current-ef.xml) /Desc (Duplicate EF stream keys) /AFRelationship /Source /EF << /F 21 0 R /F 22 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($staleEfPayload) . " /CheckSum <{$staleEfChecksum}> >> /Length " . strlen($staleEfPayload) . " >>\n"
        . "stream\n{$staleEfPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid WordPress source after duplicate FileSpecs) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260605214335Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $validPayload, $staleFileSpecPayload, $staleEfPayload, $validChecksum, $staleFileSpecChecksum, $staleEfChecksum];
};

return [
    'fails closed on duplicate FileSpec filename or EF keys before attachment preflight' => static function (
        TestRunner $t
    ) use ($duplicateFileSpecKeyPdf): void {
        [$pdf, $validPayload, $staleFileSpecPayload, $staleEfPayload, $validChecksum, $staleFileSpecChecksum, $staleEfChecksum] = $duplicateFileSpecKeyPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-source.xml', $attachment['name_key']);
        $t->same('valid-source.xml', $attachment['filename']);
        $t->same('Valid WordPress source after duplicate FileSpecs', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605214335Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-source.xml', $file['name']);
        $t->same('valid-source.xml', $file['filename']);
        $t->same($validPayload, $file['content']);
        $t->same(strlen($validPayload), $file['size']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Duplicate FileSpec Key Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'stale-filespec.xml'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'current-ef.xml'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $staleFileSpecPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $staleEfPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $staleFileSpecChecksum));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $staleEfChecksum));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $validPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $staleFileSpecPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $staleEfPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
