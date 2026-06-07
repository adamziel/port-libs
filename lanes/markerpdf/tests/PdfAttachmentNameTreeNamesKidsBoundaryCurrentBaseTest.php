<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreeNamesKidsBoundaryPdf = static function (): array {
    $stalePayload = '<wp-export><post id="stale-local-names-entry"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-child-kids-entry"/></wp-export>';
    $staleChecksum = md5($stalePayload);
    $currentChecksum = md5($currentPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Names Kids Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(child-source.xml) (local-stale.xml)] /Names [(local-stale.xml) 10 0 R] /Kids [7 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(child-source.xml) (child-source.xml)] /Names [(child-source.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (local-stale.xml) /Desc (Malformed local Names entry) /AFRelationship /Alternative /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (child-source.xml) /Desc (Child node WordPress source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260607105338Z) >> /Length " . strlen($currentPayload) . " >>\n"
        . "stream\n{$currentPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum];
};

return [
    'treats EmbeddedFiles nodes with Kids as intermediate before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreeNamesKidsBoundaryPdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum] = $attachmentNameTreeNamesKidsBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['child-source.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('child-source.xml', $attachment['name_key']);
        $t->same('child-source.xml', $attachment['filename']);
        $t->same('Child node WordPress source', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(20, $attachment['file_spec_object_id']);
        $t->same(21, $attachment['stream_object_id']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607105338Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('child-source.xml', $file['name']);
        $t->same('child-source.xml', $file['filename']);
        $t->same('Child node WordPress source', $file['description']);
        $t->same('Data', $file['relationship']);
        $t->same(20, $file['file_spec_object']);
        $t->same(21, $file['embedded_file_object']);
        $t->same($currentPayload, $file['content']);
        $t->same($currentChecksum, $file['checksum']);
        $t->same($currentChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible Attachment Names Kids Boundary', $plainText);
        foreach ([
            'local-stale.xml',
            'Malformed local Names entry',
            $stalePayload,
            $staleChecksum,
            $currentPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
