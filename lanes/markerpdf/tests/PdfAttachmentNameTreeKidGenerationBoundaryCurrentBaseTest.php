<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreeKidGenerationBoundaryCurrentBasePdf = static function (): array {
    $currentPayload = '<wp-export><post id="generation-nested-current"/></wp-export>';
    $summaryPayload = '<wp-export><post id="generation-sibling-summary"/></wp-export>';
    $currentChecksum = md5($currentPayload);
    $summaryChecksum = md5($summaryPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Kid Generation Body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(current-generation-kid.xml) (summary-generation-kid.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(current-generation-kid.xml) (current-generation-kid.xml)] /Kids [7 1 R] >>\nendobj\n"
        . "7 1 obj\n<< /Limits [(current-generation-kid.xml) (current-generation-kid.xml)] /Names [(current-generation-kid.xml) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(summary-generation-kid.xml) (summary-generation-kid.xml)] /Names [(summary-generation-kid.xml) 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (current-generation-kid.xml) /Desc (Generation nested current attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260607084728Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (summary-generation-kid.xml) /Desc (Generation sibling summary attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($summaryPayload) . " /CheckSum <{$summaryChecksum}> /ModDate (D:20260607084729Z) >> /Length " . strlen($summaryPayload) . " >>\nstream\n{$summaryPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $currentPayload, $summaryPayload, $currentChecksum, $summaryChecksum];
};

return [
    'keeps same-object EmbeddedFiles name-tree kid generations distinct before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreeKidGenerationBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $summaryPayload, $currentChecksum, $summaryChecksum] = $attachmentNameTreeKidGenerationBoundaryCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($currentPayload) + strlen($summaryPayload), $summary['total_bytes']);
        $t->same(['current-generation-kid.xml', 'summary-generation-kid.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $current = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $current['source']);
        $t->same('current-generation-kid.xml', $current['name_key']);
        $t->same('current-generation-kid.xml', $current['filename']);
        $t->same('Generation nested current attachment', $current['description']);
        $t->same('Source', $current['relationship']);
        $t->same('original_source', $current['relationship_role']);
        $t->same(10, $current['file_spec_object_id']);
        $t->same(11, $current['stream_object_id']);
        $t->same(strlen($currentPayload), $current['byte_length']);
        $t->same($currentChecksum, $current['checksum_hex']);
        $t->same($currentChecksum, $current['computed_checksum_hex']);
        $t->same(true, $current['checksum_matches']);
        $t->same('D:20260607084728Z', $current['modified_at']);
        $t->same(false, array_key_exists('bytes', $current));

        $summaryAttachment = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $summaryAttachment['source']);
        $t->same('summary-generation-kid.xml', $summaryAttachment['name_key']);
        $t->same('summary-generation-kid.xml', $summaryAttachment['filename']);
        $t->same('Generation sibling summary attachment', $summaryAttachment['description']);
        $t->same('Data', $summaryAttachment['relationship']);
        $t->same('base_data_for_visual_presentation', $summaryAttachment['relationship_role']);
        $t->same(12, $summaryAttachment['file_spec_object_id']);
        $t->same(13, $summaryAttachment['stream_object_id']);
        $t->same(strlen($summaryPayload), $summaryAttachment['byte_length']);
        $t->same($summaryChecksum, $summaryAttachment['checksum_hex']);
        $t->same($summaryChecksum, $summaryAttachment['computed_checksum_hex']);
        $t->same(true, $summaryAttachment['checksum_matches']);
        $t->same('D:20260607084729Z', $summaryAttachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $summaryAttachment));

        $t->same(2, count($files));
        $t->same(['current-generation-kid.xml', 'summary-generation-kid.xml'], array_column($files, 'filename'));
        $t->same(['catalog_names_embedded_files', 'catalog_names_embedded_files'], array_column($files, 'source'));
        $t->same([10, 12], array_column($files, 'file_spec_object'));
        $t->same([11, 13], array_column($files, 'embedded_file_object'));
        $t->same([$currentPayload, $summaryPayload], array_column($files, 'content'));
        $t->same([$currentChecksum, $summaryChecksum], array_column($files, 'checksum'));
        $t->same([true, true], array_column($files, 'checksum_matches'));

        $t->same('Visible Attachment Kid Generation Body', $plainText);
        foreach ([$currentPayload, $summaryPayload] as $hiddenPayload) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hiddenPayload));
            $t->true(!str_contains($plainText, $hiddenPayload));
        }
        $t->true(is_string($filesJson) && str_contains($filesJson, 'generation-nested-current'));
        $t->true(is_string($filesJson) && str_contains($filesJson, 'generation-sibling-summary'));
    },
];
