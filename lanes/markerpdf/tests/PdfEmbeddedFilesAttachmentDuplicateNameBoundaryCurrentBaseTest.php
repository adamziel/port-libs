<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFilesDuplicateNameBoundaryPdf = static function (): array {
    $currentPayload = "Title,Status\nCurrent Duplicate Name,Ready\n";
    $stalePayload = "Title,Status\nStale Duplicate Name,Ignore\n";
    $currentChecksum = md5($currentPayload);
    $staleChecksum = md5($stalePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Duplicate Attachment Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(shared.csv) (shared.csv)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(shared.csv) (shared.csv)] /Names [(shared.csv) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(shared.csv) (shared.csv)] /Names [(shared.csv) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (current-shared.csv) /Desc (Current duplicate name-tree attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260606033001Z) >> /Length " . strlen($currentPayload) . " >>\n"
        . "stream\n{$currentPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (stale-shared.csv) /Desc (Stale duplicate name-tree attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum];
};

return [
    'keeps first duplicate EmbeddedFiles name-tree key across full and summary attachment review' => static function (
        TestRunner $t
    ) use ($embeddedFilesDuplicateNameBoundaryPdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum] = $embeddedFilesDuplicateNameBoundaryPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('shared.csv', $file['name']);
        $t->same('current-shared.csv', $file['filename']);
        $t->same('Current duplicate name-tree attachment', $file['description']);
        $t->same('Data', $file['relationship']);
        $t->same('text/csv', $file['mime_type']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($currentPayload, $file['content']);
        $t->same(strlen($currentPayload), $file['declared_size']);
        $t->same(strlen($currentPayload), $file['size']);
        $t->same($currentChecksum, $file['checksum']);
        $t->same($currentChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same('D:20260606033001Z', $file['modified_at']);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['current-shared.csv'], $summary['filenames']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('shared.csv', $attachment['name_key']);
        $t->same('current-shared.csv', $attachment['filename']);
        $t->same('Current duplicate name-tree attachment', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260606033001Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same('Visible Duplicate Attachment Boundary', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach (['stale-shared.csv', 'Stale duplicate name-tree attachment', $stalePayload, $staleChecksum, $currentPayload] as $hidden) {
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
        }
        $t->true(!str_contains($plainText, 'Current Duplicate Name'));
        $t->true(!str_contains($plainText, 'Stale Duplicate Name'));
    },
];
