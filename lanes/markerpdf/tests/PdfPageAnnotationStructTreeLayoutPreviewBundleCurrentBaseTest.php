<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationStructTreeLayoutPreviewPayload = '<wp-export><post id="preview-bundle"/></wp-export>';
$pageAnnotationStructTreeLayoutPreviewChecksum = strtoupper(hash('md5', $pageAnnotationStructTreeLayoutPreviewPayload));
$pageAnnotationStructTreeLayoutPreviewPdf = static function () use (
    $pageAnnotationStructTreeLayoutPreviewPayload,
    $pageAnnotationStructTreeLayoutPreviewChecksum
): string {
    $content = 'BT /F1 12 Tf '
        . '/Body << /MCID 0 >> BDC 40 340 Td (Visible preview body) Tj EMC '
        . '/Caption << /MCID 1 >> BDC 40 310 Td (Visible caption) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (prev-) /S /D /St 4 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 300 400] /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 8 /Contents 4 0 R /PieceInfo << /WPPreview << /LastModified (D:20260602221200Z) /Private << /BundleId (preview-bundle-8) /ReviewStage /layout-overlay /NeedsReview true >> >> >> /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 18 /Rect [40 330 210 360] /Contents (Private link note) /T (Preview Reviewer) /NM (preview-link) /A << /S /URI /URI (https://example.com/private-preview) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /StructParent 19 /Rect [40 300 180 322] /QuadPoints [40 322 180 322 40 300 180 300] /Contents (Private highlight note) /T (Markup Reviewer) /Subj (Preview highlight) /NM (preview-highlight) /C [1 0.9 0] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (preview-source.xml) /Desc (Preview source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAnnotationStructTreeLayoutPreviewPayload) . " /CheckSum <{$pageAnnotationStructTreeLayoutPreviewChecksum}> /ModDate (D:20260602221130Z) >> /Length " . strlen($pageAnnotationStructTreeLayoutPreviewPayload) . " >>\nstream\n{$pageAnnotationStructTreeLayoutPreviewPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Body /P /Caption /Caption /ReviewLink /Link /ReviewMarkup /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R 43 0 R 44 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [8 [40 0 R 41 0 R] 18 42 0 R 19 43 0 R 99 44 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Visible body structure) /K 0 >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /Caption /Pg 3 0 R /T (Visible caption structure) /K 1 >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (Link structure review) /Alt (Link alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /ReviewMarkup /Pg 3 0 R /T (Highlight structure review) /ActualText (Highlight actual review) /K << /Type /OBJR /Obj 8 0 R >> >>\nendobj\n"
        . "44 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (Detached stale preview structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$pageAnnotationStructTreeLayoutPreviewPages = static function (): array {
    return [[
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 300.0, 400.0],
        'blocks' => [
            [
                'block_type' => 'Text',
                'bbox' => [40.0, 330.0, 210.0, 360.0],
                'lines' => [[
                    'bbox' => [40.0, 330.0, 210.0, 360.0],
                    'spans' => [[
                        'text' => 'Visible preview body',
                        'bbox' => [40.0, 330.0, 210.0, 360.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ],
            [
                'block_type' => 'Caption',
                'bbox' => [40.0, 300.0, 180.0, 322.0],
                'lines' => [[
                    'bbox' => [40.0, 300.0, 180.0, 322.0],
                    'spans' => [[
                        'text' => 'Visible caption',
                        'bbox' => [40.0, 300.0, 180.0, 322.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ],
        ],
    ]];
};

return [
    'bundles page annotations StructTree context and supplied layout blocks into marker app preview metadata' => static function (TestRunner $t) use (
        $pageAnnotationStructTreeLayoutPreviewPdf,
        $pageAnnotationStructTreeLayoutPreviewPages,
        $pageAnnotationStructTreeLayoutPreviewPayload,
        $pageAnnotationStructTreeLayoutPreviewChecksum
    ): void {
        $pdf = $pageAnnotationStructTreeLayoutPreviewPdf();
        $pages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages(
            $pageAnnotationStructTreeLayoutPreviewPages(),
            $pdf
        );
        $bundle = (new MarkerAppPreview())->getPageLayoutPreviewBundle($pdf, 1, $pages, 72.0);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same('marker_app_page_annotations_structtree_layout_preview_bundle', $bundle['source']);
        $t->same(1, $bundle['page_number']);
        $t->same(0, $bundle['page_index']);
        $t->same(1, $bundle['page_count']);
        $t->same(3, $bundle['page_object']);
        $t->same(true, $bundle['review_only']);
        $t->same(false, $bundle['visible_text_source']);
        $t->same(false, $bundle['executes_python_or_models']);
        $t->same(false, $bundle['executes_external_pdf_tools']);
        $t->same(false, $bundle['executes_pdf_actions']);
        $t->same('rendered_image_pixels', $bundle['overlay_coordinate_space']);

        $image = $bundle['image_plan'];
        $t->same([0.0, 0.0, 300.0, 400.0], $image['page_bbox']);
        $t->same(['width' => 300, 'height' => 400], $image['rendered_image_size']);
        $t->same(1.0, $image['scale']);
        $t->same('pypdfium-default', $image['annotation_mode']);

        $pageReview = $bundle['page_review'];
        $t->same('marker_app_page_review_preview_context', $pageReview['source']);
        $t->same('prev-4', $pageReview['page_label']);
        $t->same(8, $pageReview['struct_parents']);
        $t->same([0, 1], $pageReview['parent_tree']['mcids']);
        $t->same('preview-bundle-8', $pageReview['piece_info']['WPPreview']['private']['BundleId']);
        $t->same(true, $pageReview['piece_info']['WPPreview']['private']['NeedsReview']);

        $t->same(2, $bundle['layout_block_count']);
        $layoutBlocks = $bundle['layout_blocks'];
        $t->same('supplied_marker_layout_preview_block', $layoutBlocks[0]['source']);
        $t->same('Text', $layoutBlocks[0]['block_type']);
        $t->same('Visible preview body', $layoutBlocks[0]['text_preview']);
        $t->same([40.0, 40.0, 210.0, 70.0], $layoutBlocks[0]['preview_bbox']);
        $t->same(0, $layoutBlocks[0]['review_annotation_count']);
        $t->same('Caption', $layoutBlocks[1]['block_type']);
        $t->same('Visible caption', $layoutBlocks[1]['text_preview']);
        $t->same([40.0, 78.0, 180.0, 100.0], $layoutBlocks[1]['preview_bbox']);
        $t->same(1, $layoutBlocks[1]['review_annotation_count']);

        $t->same(2, $bundle['annotation_count']);
        $annotations = $bundle['annotations'];
        $t->same('page_annotation_preview_overlay', $annotations[0]['source']);
        $t->same('Link', $annotations[0]['subtype']);
        $t->same(7, $annotations[0]['annotation_object']);
        $t->same(18, $annotations[0]['struct_parent']);
        $t->same([40.0, 40.0, 210.0, 70.0], $annotations[0]['preview_bbox']);
        $t->same('Link structure review', $annotations[0]['structure_parent']['title']);
        $t->same('Link', $annotations[0]['structure_parent']['role']);
        $t->same(1, $annotations[0]['structure_parent']['associated_file_count']);
        $t->same('preview-source.xml', $annotations[0]['structure_parent']['associated_files'][0]['filename']);
        $t->same(hash('sha256', $pageAnnotationStructTreeLayoutPreviewPayload), $annotations[0]['structure_parent']['associated_files'][0]['content_sha256']);
        $t->same(strtolower($pageAnnotationStructTreeLayoutPreviewChecksum), $annotations[0]['structure_parent']['associated_files'][0]['checksum']);
        $t->same(hash('md5', $pageAnnotationStructTreeLayoutPreviewPayload), $annotations[0]['structure_parent']['associated_files'][0]['computed_checksum']);
        $t->same(true, $annotations[0]['structure_parent']['associated_files'][0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $annotations[0]['structure_parent']['associated_files'][0]));
        $t->same(1, $annotations[0]['action_count']);
        $t->same(false, $annotations[0]['executes_actions_on_import']);

        $t->same('Highlight', $annotations[1]['subtype']);
        $t->same(19, $annotations[1]['struct_parent']);
        $t->same([40.0, 78.0, 180.0, 100.0], $annotations[1]['preview_bbox']);
        $t->same('Highlight structure review', $annotations[1]['structure_parent']['title']);
        $t->same('Highlight actual review', $annotations[1]['structure_parent']['actual_text']);
        $t->same(false, $annotations[1]['visible_text_source']);

        $t->same(1, $bundle['text_markup_annotation_count']);
        $markup = $bundle['text_markup_annotations'][0];
        $t->same('page_text_markup_preview_overlay', $markup['source']);
        $t->same('Highlight', $markup['subtype']);
        $t->same(8, $markup['annotation_object']);
        $t->same(19, $markup['struct_parent']);
        $t->same([40.0, 78.0, 180.0, 100.0], $markup['preview_bbox']);
        $t->same([[40.0, 78.0, 180.0, 100.0]], $markup['quad_preview_bboxes']);
        $t->same('Span', $markup['structure_parent']['role']);
        $t->same('Highlight structure review', $markup['structure_parent']['title']);
        $t->same(false, $markup['visible_text_source']);

        $t->same(2, $bundle['annotation_structure_parent_row_count']);
        $structureRows = $bundle['annotation_structure_parent_rows'];
        $t->same('page_annotation_struct_parent_preview_overlay', $structureRows[0]['preview_source']);
        $t->same(18, $structureRows[0]['struct_parent']);
        $t->same([40.0, 40.0, 210.0, 70.0], $structureRows[0]['preview_bbox']);
        $t->same('preview-bundle-8', $structureRows[0]['page_piece_info']['WPPreview']['private']['BundleId']);
        $t->same(true, $structureRows[0]['page_piece_info_review_only']);
        $t->same(false, $structureRows[0]['visible_text_source']);
        $t->same(19, $structureRows[1]['struct_parent']);
        $t->same([40.0, 78.0, 180.0, 100.0], $structureRows[1]['preview_bbox']);

        $t->same(2, $bundle['structure_marked_content_count']);
        $marked = $bundle['structure_marked_content'];
        $t->same('page_structtree_marked_content_preview_context', $marked[0]['preview_source']);
        $t->same([0, 1], array_column($marked, 'mcid'));
        $t->same(['P', 'Caption'], array_column($marked, 'role'));
        $t->same(false, $marked[0]['visible_text_source']);

        $encoded = json_encode($bundle, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Detached stale preview structure'));
        $t->same(['Visible preview body', 'Visible caption'], $textExtractor->extractTextLines($pdf));
        $t->contains('Visible preview body', $plainText);
        $t->contains('Visible caption', $plainText);
        $t->same(false, str_contains($plainText, 'Private link note'));
        $t->same(false, str_contains($plainText, 'Private highlight note'));
        $t->same(false, str_contains($plainText, 'Link structure review'));
        $t->same(false, str_contains($plainText, 'Highlight structure review'));
        $t->same(false, str_contains($plainText, 'Highlight actual review'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'preview-source.xml'));
        $t->same(false, str_contains($plainText, 'preview-bundle-8'));
        $t->same(false, str_contains($plainText, 'https://example.com/private-preview'));
    },
];
