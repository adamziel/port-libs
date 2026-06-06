<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$nonFileSpecTypedAttachmentPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-untyped-filespec-boundary"/></wp-export>';
    $pageDecoyPayload = '<wp-export><post id="typed-page-decoy-attachment"/></wp-export>';
    $catalogDecoyPayload = '<wp-export><post id="typed-catalog-decoy-attachment"/></wp-export>';
    $validChecksum = md5($validPayload);
    $pageDecoyChecksum = md5($pageDecoyPayload);
    $catalogDecoyChecksum = md5($catalogDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Non FileSpec Type Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [20 0 R 30 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names ["
        . "(typed-page-decoy.xml) 10 0 R "
        . "(typed-catalog-decoy.xml) << /Type /Catalog /F (typed-catalog-decoy.xml) /Desc (Typed catalog decoy attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >> "
        . "(valid-untyped.xml) 30 0 R"
        . "] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Page /F (typed-page-decoy.xml) /Desc (Typed page decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageDecoyPayload) . " /CheckSum <{$pageDecoyChecksum}> >> /Length " . strlen($pageDecoyPayload) . " >>\n"
        . "stream\n{$pageDecoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Catalog /F (catalog-af-decoy.xml) /Desc (Typed catalog AF decoy) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogDecoyPayload) . " /CheckSum <{$catalogDecoyChecksum}> >> /Length " . strlen($catalogDecoyPayload) . " >>\n"
        . "stream\n{$catalogDecoyPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /F (valid-untyped.xml) /Desc (Valid untyped legacy FileSpec) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260606013753Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $validPayload, $pageDecoyPayload, $catalogDecoyPayload, $validChecksum, $pageDecoyChecksum, $catalogDecoyChecksum];
};

return [
    'fails closed on typed non-FileSpec dictionaries before WordPress attachment import' => static function (
        TestRunner $t
    ) use ($nonFileSpecTypedAttachmentPdf): void {
        [$pdf, $validPayload, $pageDecoyPayload, $catalogDecoyPayload, $validChecksum, $pageDecoyChecksum, $catalogDecoyChecksum] = $nonFileSpecTypedAttachmentPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-untyped.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-untyped.xml', $attachment['name_key']);
        $t->same('valid-untyped.xml', $attachment['filename']);
        $t->same('Valid untyped legacy FileSpec', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260606013753Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-untyped.xml', $file['name']);
        $t->same('valid-untyped.xml', $file['filename']);
        $t->same(30, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Non FileSpec Type Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'typed-page-decoy.xml'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'typed-catalog-decoy.xml'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'catalog-af-decoy.xml'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $pageDecoyPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $catalogDecoyPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $pageDecoyChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $catalogDecoyChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $validPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $pageDecoyPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $catalogDecoyPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
