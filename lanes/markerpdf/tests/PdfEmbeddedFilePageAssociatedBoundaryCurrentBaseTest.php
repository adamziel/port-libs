<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAssociatedEmbeddedFileBoundaryPdf = static function (): array {
    $pageOnlyPayload = '<wp-page><attachment role="page-af-only"/></wp-page>';
    $pageMirrorPayload = '<wp-page><attachment role="page-af-mirror"/></wp-page>';
    $pageOnlyChecksum = md5($pageOnlyPayload);
    $pageMirrorChecksum = md5($pageMirrorPayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Page AF Embedded Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 5 0 R /AF [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [20 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(page-mirror.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (page-only.xml) /Desc (Page-only associated WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageOnlyPayload) . " /CheckSum <{$pageOnlyChecksum}> /ModDate (D:20260606022800Z) >> /Length " . strlen($pageOnlyPayload) . " >>\n"
        . "stream\n{$pageOnlyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (page-mirror.xml) /Desc (Mirrored page associated WordPress source) /AFRelationship /Supplement /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageMirrorPayload) . " /CheckSum <{$pageMirrorChecksum}> /ModDate (D:20260606022900Z) >> /Length " . strlen($pageMirrorPayload) . " >>\n"
        . "stream\n{$pageMirrorPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $pageOnlyPayload, $pageMirrorPayload, $pageOnlyChecksum, $pageMirrorChecksum];
};

return [
    'extracts page associated Filespec entries in EmbeddedFile review and marks name-tree mirrors' => static function (
        TestRunner $t
    ) use ($pageAssociatedEmbeddedFileBoundaryPdf): void {
        [$pdf, $pageOnlyPayload, $pageMirrorPayload, $pageOnlyChecksum, $pageMirrorChecksum] = $pageAssociatedEmbeddedFileBoundaryPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(2, count($files));
        $t->same(['page-mirror.xml', 'page-only.xml'], array_column($files, 'filename'));

        $mirror = $files[0];
        $t->same('catalog_names_embedded_files', $mirror['source']);
        $t->same('page-mirror.xml', $mirror['name']);
        $t->same('page-mirror.xml', $mirror['filename']);
        $t->same('Supplement', $mirror['relationship']);
        $t->same(true, $mirror['associated_file']);
        $t->same(true, $mirror['page_associated_file']);
        $t->same('page_af', $mirror['page_associated_file_source']);
        $t->same(2, $mirror['page_number']);
        $t->same(4, $mirror['page_object_id']);
        $t->same(0, $mirror['page_associated_file_index']);
        $t->same(20, $mirror['file_spec_object']);
        $t->same(21, $mirror['embedded_file_object']);
        $t->same(strlen($pageMirrorPayload), $mirror['declared_size']);
        $t->same(strlen($pageMirrorPayload), $mirror['size']);
        $t->same($pageMirrorChecksum, $mirror['checksum']);
        $t->same($pageMirrorChecksum, $mirror['computed_checksum']);
        $t->same(true, $mirror['checksum_matches']);

        $pageOnly = $files[1];
        $t->same('page_associated_files', $pageOnly['source']);
        $t->same(true, $pageOnly['associated_file']);
        $t->same(true, $pageOnly['page_associated_file']);
        $t->same(1, $pageOnly['page_number']);
        $t->same(3, $pageOnly['page_object_id']);
        $t->same(0, $pageOnly['page_associated_file_index']);
        $t->same('page-only.xml', $pageOnly['name']);
        $t->same('page-only.xml', $pageOnly['filename']);
        $t->same('Page-only associated WordPress source', $pageOnly['description']);
        $t->same('Source', $pageOnly['relationship']);
        $t->same('text/xml', $pageOnly['mime_type']);
        $t->same(10, $pageOnly['file_spec_object']);
        $t->same(11, $pageOnly['embedded_file_object']);
        $t->same(strlen($pageOnlyPayload), $pageOnly['declared_size']);
        $t->same(strlen($pageOnlyPayload), $pageOnly['size']);
        $t->same($pageOnlyChecksum, $pageOnly['checksum']);
        $t->same($pageOnlyChecksum, $pageOnly['computed_checksum']);
        $t->same(true, $pageOnly['checksum_matches']);

        $t->same(2, $summary['attachment_count']);
        $t->same(['page-mirror.xml', 'page-only.xml'], $summary['filenames']);
        $t->same(true, $summary['attachments'][0]['page_associated_file']);
        $t->same(true, $summary['attachments'][1]['page_associated_file']);
        $t->same('Page AF Embedded Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'attachment role="page-af-only"'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'attachment role="page-af-mirror"'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $pageOnlyPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $pageMirrorPayload));
        $t->true(!str_contains($plainText, '<wp-page>'));
    },
];
