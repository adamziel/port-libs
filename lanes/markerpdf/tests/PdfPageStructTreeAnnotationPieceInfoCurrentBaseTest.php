<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructTreeAnnotationPieceInfoPayload = '<wp-export><post id="annot-piece"/></wp-export>';
$pageStructTreeAnnotationPieceInfoChecksum = strtoupper(hash('md5', $pageStructTreeAnnotationPieceInfoPayload));
$pageStructTreeAnnotationPieceInfoPdf = static function () use (
    $pageStructTreeAnnotationPieceInfoPayload,
    $pageStructTreeAnnotationPieceInfoChecksum
): string {
    $content = 'BT /F1 12 Tf /Body << /MCID 0 >> BDC 72 720 Td (Visible annotation PieceInfo body) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (ann-) /S /D /St 6 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 6 /Contents 4 0 R /PieceInfo << /WPAnnot << /LastModified (D:20260602211000Z) /Private << /BatchId (annot-piece-6) /ReviewStage /annotation-structure /NeedsReview true >> >> >> /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 16 /Rect [72 690 260 730] /Contents (Editor note stays review only) /T (Import QA) /NM (piece-note) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 17 /Rect [72 642 260 680] /Contents (Detached stale note review only) /T (Detached QA) /NM (piece-stale) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (annotation-source.xml) /Desc (Annotation review source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageStructTreeAnnotationPieceInfoPayload) . " /CheckSum <{$pageStructTreeAnnotationPieceInfoChecksum}> /ModDate (D:20260602210930Z) >> /Length " . strlen($pageStructTreeAnnotationPieceInfoPayload) . " >>\nstream\n{$pageStructTreeAnnotationPieceInfoPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Body /P /ReviewNote /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [6 [40 0 R] 16 41 0 R 17 42 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Body structure row) /K 0 >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Current annotation review structure) /Alt (Current annotation alternate text) /ActualText (Current annotation actual text) /AF [10 0 R] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Detached stale annotation structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'composes page PieceInfo with current annotation StructParent review rows' => static function (TestRunner $t) use (
        $pageStructTreeAnnotationPieceInfoPdf,
        $pageStructTreeAnnotationPieceInfoPayload,
        $pageStructTreeAnnotationPieceInfoChecksum
    ): void {
        $pdf = $pageStructTreeAnnotationPieceInfoPdf();
        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(1, $page['page_number']);
        $t->same(3, $page['page_object']);
        $t->same('ann-6', $page['page_label']);
        $t->same(6, $page['struct_parents']);
        $t->same('D:20260602211000Z', $page['piece_info']['WPAnnot']['last_modified']);
        $t->same('annot-piece-6', $page['piece_info']['WPAnnot']['private']['BatchId']);
        $t->same('annotation-structure', $page['piece_info']['WPAnnot']['private']['ReviewStage']);
        $t->same(true, $page['piece_info']['WPAnnot']['private']['NeedsReview']);

        $rows = $page['annotation_structure_parent_rows'];
        $t->same(1, count($rows), 'Only the current page OBJR annotation is promoted to page review rows.');
        $row = $rows[0];
        $t->same('page_annotation_struct_parent_review', $row['source']);
        $t->same(7, $row['annotation_object']);
        $t->same(0, $row['annotation_index']);
        $t->same('Text', $row['subtype']);
        $t->same('Editor note stays review only', $row['contents']);
        $t->same('Import QA', $row['title']);
        $t->same('piece-note', $row['name']);
        $t->same(16, $row['struct_parent']);
        $t->same(6, $row['page_struct_parents']);
        $t->same([0], $row['page_parent_tree']['mcids']);
        $t->same(['P'], $row['page_parent_tree']['roles']);
        $t->same('D:20260602211000Z', $row['page_piece_info']['WPAnnot']['last_modified']);
        $t->same('annot-piece-6', $row['page_piece_info']['WPAnnot']['private']['BatchId']);
        $t->same(true, $row['page_piece_info_review_only']);
        $t->same(false, $row['visible_text_source']);
        $t->same(true, $row['review_only']);

        $structure = $row['structure_parent'];
        $t->same('annotation_struct_parent_parent_tree', $structure['source']);
        $t->same(16, $structure['key']);
        $t->same(41, $structure['struct_object']);
        $t->same('ReviewNote', $structure['raw_role']);
        $t->same('Span', $structure['role']);
        $t->same(true, $structure['role_mapped']);
        $t->same('Current annotation review structure', $structure['title']);
        $t->same('Current annotation alternate text', $structure['alternate_text']);
        $t->same('Current annotation actual text', $structure['actual_text']);
        $t->same([7], $structure['annotation_objects']);
        $t->same(true, $structure['current_annotation_object_ref_matched']);
        $t->same(true, $structure['current_page_annotation']);
        $t->same(false, $structure['visible_text_source']);

        $files = $structure['associated_files'];
        $t->same(1, $structure['associated_file_count']);
        $t->same('structure_element_associated_files', $files[0]['source']);
        $t->same('annotation-source.xml', $files[0]['filename']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(hash('sha256', $pageStructTreeAnnotationPieceInfoPayload), $files[0]['content_sha256']);
        $t->same(strtolower($pageStructTreeAnnotationPieceInfoChecksum), $files[0]['checksum']);
        $t->same(hash('md5', $pageStructTreeAnnotationPieceInfoPayload), $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same('D:20260602210930Z', $files[0]['modified_at']);
        $t->same(false, array_key_exists('content', $files[0]));

        $encoded = json_encode($page, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Detached stale annotation structure'));
        $t->same(['Visible annotation PieceInfo body'], $textExtractor->extractTextLines($pdf));
        $t->contains('Visible annotation PieceInfo body', $plainText);
        $t->same(false, str_contains($plainText, 'Editor note stays review only'));
        $t->same(false, str_contains($plainText, 'Current annotation review structure'));
        $t->same(false, str_contains($plainText, 'Current annotation alternate text'));
        $t->same(false, str_contains($plainText, 'Current annotation actual text'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'annot-piece-6'));
    },
];
