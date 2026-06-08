<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentMirrorGenerationBoundaryPdf = static function (): array {
    $nameTreePayload = '<wp-export><post id="name-tree-generation-zero"/></wp-export>';
    $catalogPayload = '<wp-export><post id="catalog-af-generation-one"/></wp-export>';
    $nameTreeChecksum = md5($nameTreePayload);
    $catalogChecksum = md5($catalogPayload);
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Attachment Mirror Generation Boundary Body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 6 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 1 R] >>\nendobj\n"
        . "2 0 obj\n<< /Names [(name-tree-generation.xml) 4 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Filespec /F (name-tree-generation.xml) /Desc (Name tree generation zero FileSpec) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
        . "4 1 obj\n<< /Type /Filespec /F (catalog-af-generation.xml) /Desc (Catalog AF generation one FileSpec) /AFRelationship /Data /EF << /F 5 1 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($nameTreePayload) . " /CheckSum <{$nameTreeChecksum}> /ModDate (D:20260608223742Z) >> /Length " . strlen($nameTreePayload) . " >>\n"
        . "stream\n{$nameTreePayload}\nendstream\nendobj\n"
        . "5 1 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260608223842Z) >> /Length " . strlen($catalogPayload) . " >>\n"
        . "stream\n{$catalogPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 >>\nendobj\n"
        . "7 0 obj\n<< /Type /Page /Parent 6 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $nameTreePayload, $catalogPayload, $nameTreeChecksum, $catalogChecksum];
};

return [
    'keeps same-object-number generation distinct FileSpec mirrors separate before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentMirrorGenerationBoundaryPdf): void {
        [$pdf, $nameTreePayload, $catalogPayload, $nameTreeChecksum, $catalogChecksum] = $attachmentMirrorGenerationBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(['name-tree-generation.xml', 'catalog-af-generation.xml'], $summary['filenames']);
        $t->same(strlen($nameTreePayload) + strlen($catalogPayload), $summary['total_bytes']);

        $nameTreeAttachment = $summary['attachments'][0];
        $catalogAttachment = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $nameTreeAttachment['source']);
        $t->same('name-tree-generation.xml', $nameTreeAttachment['filename']);
        $t->same(4, $nameTreeAttachment['file_spec_object_id']);
        $t->same(0, $nameTreeAttachment['file_spec_object_generation']);
        $t->same(5, $nameTreeAttachment['stream_object_id']);
        $t->same(0, $nameTreeAttachment['stream_object_generation']);
        $t->same('Source', $nameTreeAttachment['relationship']);
        $t->same('original_source', $nameTreeAttachment['relationship_role']);
        $t->same($nameTreeChecksum, $nameTreeAttachment['checksum_hex']);
        $t->same($nameTreeChecksum, $nameTreeAttachment['computed_checksum_hex']);
        $t->same(true, $nameTreeAttachment['checksum_matches']);
        $t->same(hash('sha256', $nameTreePayload), $nameTreeAttachment['sha256']);
        $t->same(false, array_key_exists('bytes', $nameTreeAttachment));

        $t->same('catalog-associated-file', $catalogAttachment['source']);
        $t->same('catalog-af-generation.xml', $catalogAttachment['filename']);
        $t->same(4, $catalogAttachment['file_spec_object_id']);
        $t->same(1, $catalogAttachment['file_spec_object_generation']);
        $t->same(5, $catalogAttachment['stream_object_id']);
        $t->same(1, $catalogAttachment['stream_object_generation']);
        $t->same(true, $catalogAttachment['associated_file']);
        $t->same(0, $catalogAttachment['associated_file_index']);
        $t->same('Data', $catalogAttachment['relationship']);
        $t->same('base_data_for_visual_presentation', $catalogAttachment['relationship_role']);
        $t->same($catalogChecksum, $catalogAttachment['checksum_hex']);
        $t->same($catalogChecksum, $catalogAttachment['computed_checksum_hex']);
        $t->same(true, $catalogAttachment['checksum_matches']);
        $t->same(hash('sha256', $catalogPayload), $catalogAttachment['sha256']);
        $t->same(false, array_key_exists('bytes', $catalogAttachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $t->same(2, count($files));
        $t->same(['name-tree-generation.xml', 'catalog-af-generation.xml'], array_column($files, 'filename'));
        $t->same([0, 1], array_column($files, 'file_spec_generation'));
        $t->same([0, 1], array_column($files, 'embedded_file_generation'));
        $t->same($nameTreePayload, $files[0]['content']);
        $t->same($catalogPayload, $files[1]['content']);

        $t->same('Attachment Mirror Generation Boundary Body', $plainText);
        foreach ([$nameTreePayload, $catalogPayload] as $hiddenPayload) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hiddenPayload));
            $t->true(!str_contains($plainText, $hiddenPayload));
        }
    },
];
