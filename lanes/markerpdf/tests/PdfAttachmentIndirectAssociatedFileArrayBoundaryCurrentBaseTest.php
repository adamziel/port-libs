<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$indirectAssociatedFileArrayBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-indirect-af-array-boundary"/></wp-export>';
    $catalogPayload = '<wp-export><post id="catalog-indirect-af-array-decoy"/></wp-export>';
    $pagePayload = '<wp-page><attachment role="page-indirect-af-array-decoy"/></wp-page>';
    $annotationPayload = '<wp-annotation><attachment role="annotation-indirect-af-array-decoy"/></wp-annotation>';
    $validChecksum = md5($validPayload);
    $catalogChecksum = md5($catalogPayload);
    $pageChecksum = md5($pagePayload);
    $annotationChecksum = md5($annotationPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect Associated File Array Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R] /AF 60 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(valid-indirect-af-array.xml) 30 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 700 96 724] /Contents (Malformed indirect annotation AF array) /AF 70 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (catalog-indirect-af-array-decoy.xml) /Desc (Catalog indirect AF array decoy) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> >> /Length " . strlen($catalogPayload) . " >>\nstream\n{$catalogPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (catalog-trailing-af-array-decoy.xml) /Desc (Catalog trailing AF operand decoy) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length 19 >>\nstream\ncatalog-trailing\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-indirect-af-array.xml) /Desc (Valid EmbeddedFiles source after malformed AF arrays) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608091157Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /F (page-indirect-af-array-decoy.xml) /Desc (Page indirect AF array decoy) /AFRelationship /Supplement /EF << /F 41 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pagePayload) . " /CheckSum <{$pageChecksum}> >> /Length " . strlen($pagePayload) . " >>\nstream\n{$pagePayload}\nendstream\nendobj\n"
        . "42 0 obj\n<< /Type /Filespec /F (annotation-indirect-af-array-decoy.xml) /Desc (Annotation indirect AF array decoy) /AFRelationship /Alternative /EF << /F 43 0 R >> >>\nendobj\n"
        . "43 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($annotationPayload) . " /CheckSum <{$annotationChecksum}> >> /Length " . strlen($annotationPayload) . " >>\nstream\n{$annotationPayload}\nendstream\nendobj\n"
        . "50 0 obj\n[10 0 R] 20 0 R\nendobj\n"
        . "60 0 obj\n[40 0 R] 20 0 R\nendobj\n"
        . "70 0 obj\n[42 0 R] 20 0 R\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $catalogPayload,
        $pagePayload,
        $annotationPayload,
        $validChecksum,
        $catalogChecksum,
        $pageChecksum,
        $annotationChecksum,
    ];
};

return [
    'rejects indirect catalog page and annotation AF arrays with trailing operands before attachment review' => static function (
        TestRunner $t
    ) use ($indirectAssociatedFileArrayBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $catalogPayload,
            $pagePayload,
            $annotationPayload,
            $validChecksum,
            $catalogChecksum,
            $pageChecksum,
            $annotationChecksum,
        ] = $indirectAssociatedFileArrayBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-indirect-af-array.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-indirect-af-array.xml', $attachment['name_key']);
        $t->same('valid-indirect-af-array.xml', $attachment['filename']);
        $t->same('Valid EmbeddedFiles source after malformed AF arrays', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608091157Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('associated_file', $attachment));
        $t->same(false, array_key_exists('page_associated_file', $attachment));
        $t->same(false, array_key_exists('annotation_associated_file', $attachment));
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-indirect-af-array.xml', $file['name']);
        $t->same('valid-indirect-af-array.xml', $file['filename']);
        $t->same(30, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(false, array_key_exists('associated_file', $file));
        $t->same(false, array_key_exists('page_associated_file', $file));
        $t->same(false, array_key_exists('annotation_associated_file', $file));

        $t->same('Indirect Associated File Array Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'catalog-indirect-af-array-decoy.xml',
            'catalog-trailing-af-array-decoy.xml',
            'page-indirect-af-array-decoy.xml',
            'annotation-indirect-af-array-decoy.xml',
            'Catalog indirect AF array decoy',
            'Catalog trailing AF operand decoy',
            'Page indirect AF array decoy',
            'Annotation indirect AF array decoy',
            $catalogPayload,
            $pagePayload,
            $annotationPayload,
            $catalogChecksum,
            $pageChecksum,
            $annotationChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
