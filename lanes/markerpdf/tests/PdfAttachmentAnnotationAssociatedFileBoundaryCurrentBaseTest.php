<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationAssociatedFileBoundaryPdf = static function (): array {
    $mirrorPayload = '<wp-export><post id="annotation-af-mirror"/></wp-export>';
    $annotationOnlyPayload = '<wp-export><post id="annotation-af-only"/></wp-export>';
    $duplicatePayload = '<wp-export><post id="annotation-af-duplicate"/></wp-export>';
    $mirrorChecksum = md5($mirrorPayload);
    $annotationOnlyChecksum = md5($annotationOnlyPayload);
    $duplicateChecksum = md5($duplicatePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Annotation Associated File Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(annotation-mirror.xml) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 700 96 724] /F 4 /Contents (Mirror annotation associated source) /NM (annot-af-mirror) /AF [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (annotation-mirror.xml) /Desc (Mirrored annotation associated source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($mirrorPayload) . " /CheckSum <{$mirrorChecksum}> /ModDate (D:20260608065617Z) >> /Length " . strlen($mirrorPayload) . " >>\n"
        . "stream\n{$mirrorPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [120 680 220 700] /Contents (Annotation-only source packet) /AF [13 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /Type /Filespec /F (annotation-only.xml) /Desc (Annotation-only associated source) /AFRelationship /Supplement /EF << /F 14 0 R >> >>\nendobj\n"
        . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($annotationOnlyPayload) . " /CheckSum <{$annotationOnlyChecksum}> /ModDate (D:20260608065618Z) >> /Length " . strlen($annotationOnlyPayload) . " >>\n"
        . "stream\n{$annotationOnlyPayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Type /Annot /Subtype /Text /Rect [240 680 260 700] /Contents (Duplicate annotation AF decoy) /AF [17 0 R] /#41F [17 0 R] >>\nendobj\n"
        . "17 0 obj\n<< /Type /Filespec /F (annotation-duplicate.xml) /Desc (Duplicate annotation AF decoy source) /AFRelationship /Alternative /EF << /F 18 0 R >> >>\nendobj\n"
        . "18 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
        . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $mirrorPayload,
        $annotationOnlyPayload,
        $duplicatePayload,
        $mirrorChecksum,
        $annotationOnlyChecksum,
        $duplicateChecksum,
    ];
};

return [
    'extracts annotation AF associated files and merges name-tree mirrors before WordPress review' => static function (
        TestRunner $t
    ) use ($annotationAssociatedFileBoundaryPdf): void {
        [
            $pdf,
            $mirrorPayload,
            $annotationOnlyPayload,
            $duplicatePayload,
            $mirrorChecksum,
            $annotationOnlyChecksum,
            $duplicateChecksum,
        ] = $annotationAssociatedFileBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($mirrorPayload) + strlen($annotationOnlyPayload), $summary['total_bytes']);
        $t->same(['annotation-mirror.xml', 'annotation-only.xml'], $summary['filenames']);

        $mirror = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $mirror['source']);
        $t->same('annotation-mirror.xml', $mirror['filename']);
        $t->same('Source', $mirror['relationship']);
        $t->same(true, $mirror['annotation_associated_file']);
        $t->same('annotation_af', $mirror['annotation_associated_file_source']);
        $t->same(0, $mirror['annotation_associated_file_index']);
        $t->same(1, $mirror['page_number']);
        $t->same(3, $mirror['page_object_id']);
        $t->same(8, $mirror['annotation_object_id']);
        $t->same('Text', $mirror['annotation_subtype']);
        $t->same('Mirror annotation associated source', $mirror['annotation_contents']);
        $t->same([72.0, 700.0, 96.0, 724.0], $mirror['annotation_rect']);
        $t->same('annot-af-mirror', $mirror['annotation_name']);
        $t->same($mirrorChecksum, $mirror['checksum_hex']);
        $t->same(true, $mirror['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $mirror));

        $annotationOnly = $summary['attachments'][1];
        $t->same('annotation-associated-file', $annotationOnly['source']);
        $t->same('annotation-only.xml', $annotationOnly['filename']);
        $t->same('Annotation-only associated source', $annotationOnly['description']);
        $t->same('Supplement', $annotationOnly['relationship']);
        $t->same('supplemental_representation', $annotationOnly['relationship_role']);
        $t->same(true, $annotationOnly['associated_file']);
        $t->same(true, $annotationOnly['annotation_associated_file']);
        $t->same('annotation_af', $annotationOnly['annotation_associated_file_source']);
        $t->same(0, $annotationOnly['annotation_associated_file_index']);
        $t->same(12, $annotationOnly['annotation_object_id']);
        $t->same('Link', $annotationOnly['annotation_subtype']);
        $t->same('Annotation-only source packet', $annotationOnly['annotation_contents']);
        $t->same([120.0, 680.0, 220.0, 700.0], $annotationOnly['annotation_rect']);
        $t->same($annotationOnlyChecksum, $annotationOnly['checksum_hex']);
        $t->same(true, $annotationOnly['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $annotationOnly));

        $t->same(2, count($files));
        $fileMirror = $files[0];
        $t->same('catalog_names_embedded_files', $fileMirror['source']);
        $t->same(true, $fileMirror['annotation_associated_file']);
        $t->same('annotation_af', $fileMirror['annotation_associated_file_source']);
        $t->same(8, $fileMirror['annotation_object_id']);
        $t->same('Text', $fileMirror['annotation_subtype']);
        $t->same($mirrorPayload, $fileMirror['content']);
        $t->same(true, $fileMirror['checksum_matches']);

        $fileAnnotationOnly = $files[1];
        $t->same('annotation_associated_files', $fileAnnotationOnly['source']);
        $t->same('annotation-only.xml', $fileAnnotationOnly['filename']);
        $t->same(true, $fileAnnotationOnly['annotation_associated_file']);
        $t->same(12, $fileAnnotationOnly['annotation_object_id']);
        $t->same('Link', $fileAnnotationOnly['annotation_subtype']);
        $t->same($annotationOnlyPayload, $fileAnnotationOnly['content']);
        $t->same(true, $fileAnnotationOnly['checksum_matches']);

        $t->same('Annotation Associated File Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'annotation-duplicate.xml',
            'Duplicate annotation AF decoy source',
            $duplicatePayload,
            $duplicateChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
        }
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $mirrorPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $annotationOnlyPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
