<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreeFallbackEfOrderPdf = static function (): array {
    $currentPayload = '<wp-export><post id="name-tree-uf-current"/></wp-export>';
    $stalePayload = '<wp-export><post id="name-tree-f-stale"/></wp-export>';
    $currentChecksum = md5($currentPayload);
    $staleChecksum = md5($stalePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Name Tree EF Fallback Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(name-tree-display.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /Desc (Name tree fallback WordPress source) /AFRelationship /Source /EF << /F 11 0 R /UF 12 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> /ModDate (D:20260605091400Z) >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605091430Z) >> /Length " . strlen($currentPayload) . " >>\n"
        . "stream\n{$currentPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum];
};

return [
    'uses standard EF key order when FileSpec filename falls back to EmbeddedFiles name-tree key' => static function (
        TestRunner $t
    ) use ($attachmentNameTreeFallbackEfOrderPdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum] = $attachmentNameTreeFallbackEfOrderPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['name-tree-display.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('name-tree-display.xml', $attachment['name_key']);
        $t->same('name-tree-display.xml', $attachment['filename']);
        $t->same('name_tree_key', $attachment['filename_source']);
        $t->same('UF', $attachment['ef_key']);
        $t->same('Name tree fallback WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $currentPayload), $attachment['sha256']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605091430Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('name-tree-display.xml', $file['name']);
        $t->same('name-tree-display.xml', $file['filename']);
        $t->same('UF', $file['ef_key']);
        $t->same($currentPayload, $file['content']);
        $t->same(strlen($currentPayload), $file['size']);
        $t->same($currentChecksum, $file['checksum']);
        $t->same($currentChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Name Tree EF Fallback Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $currentPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $stalePayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $staleChecksum));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $stalePayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $staleChecksum));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
