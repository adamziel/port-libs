<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$layoutPageAnnotationStructTreeTablePayload = '<wp-export><post id="layout-table-annotation"/></wp-export>';
$layoutPageAnnotationStructTreeTableChecksum = strtoupper(hash('md5', $layoutPageAnnotationStructTreeTablePayload));
$layoutPageAnnotationStructTreeTablePdf = static function () use (
    $layoutPageAnnotationStructTreeTablePayload,
    $layoutPageAnnotationStructTreeTableChecksum
): string {
    $content = 'BT /F1 12 Tf '
        . '/TaggedTable << /MCID 0 >> BDC 72 720 Td (Tagged table review source) Tj EMC '
        . '/Body << /MCID 1 >> BDC 72 684 Td (Body after tagged table) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (bundle-) /S /D /St 4 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 8 /Contents 4 0 R /PieceInfo << /WPTable << /LastModified (D:20260602220100Z) /Private << /BatchId (table-bundle-8) /ReviewStage /table-annotation-structure /NeedsReview true >> >> >> /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 16 /Rect [72 150 430 230] /Contents (Table annotation review note) /T (Table QA) /NM (table-note) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 17 /Rect [72 500 430 540] /Contents (Outside annotation review note) /T (Outside QA) /NM (outside-note) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (table-annotation-source.xml) /Desc (Table annotation source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($layoutPageAnnotationStructTreeTablePayload) . " /CheckSum <{$layoutPageAnnotationStructTreeTableChecksum}> /ModDate (D:20260602220030Z) >> /Length " . strlen($layoutPageAnnotationStructTreeTablePayload) . " >>\nstream\n{$layoutPageAnnotationStructTreeTablePayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /TaggedTable /Table /Body /P /ReviewNote /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R 43 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [8 [40 0 R 41 0 R] 16 42 0 R 17 43 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /TaggedTable /Pg 3 0 R /T (Tagged table structure) /K 0 >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Body structure row) /K 1 >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Current table annotation structure) /Alt (Current table annotation alternate text) /ActualText (Current table annotation actual text) /AF [10 0 R] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Outside annotation structure) /K << /Type /OBJR /Obj 8 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$layoutPageAnnotationStructTreeTablePdfTextPage = static function (): array {
    $line = static fn (string $text, array $bbox, string $font = 'Times-Roman', int $weight = 400, int $size = 11): array => [
        'bbox' => $bbox,
        'spans' => [[
            'text' => $text,
            'bbox' => $bbox,
            'font' => [
                'name' => $font,
                'flags' => 0,
                'weight' => $weight,
                'size' => $size,
            ],
        ]],
    ];

    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => [
                $line('Structured table import', [72.0, 48.0, 340.0, 68.0], 'Times-Bold', 700, 18),
                $line('Legacy table text should be replaced.', [72.0, 178.0, 430.0, 196.0]),
                $line('After structured table bundle.', [72.0, 276.0, 430.0, 294.0]),
            ],
        ]],
    ];
};

return [
    'bundles layout table review with page StructTree and overlapping annotation metadata' => static function (TestRunner $t) use (
        $layoutPageAnnotationStructTreeTablePdf,
        $layoutPageAnnotationStructTreeTablePdfTextPage,
        $layoutPageAnnotationStructTreeTablePayload,
        $layoutPageAnnotationStructTreeTableChecksum
    ): void {
        $pdf = $layoutPageAnnotationStructTreeTablePdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $path = sys_get_temp_dir() . '/markerpdf-layout-page-annotation-structtree-table-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% supplied layout table current-base fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$layoutPageAnnotationStructTreeTablePdfTextPage()],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 340.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                        ],
                    ]],
                    'page_review_metadata' => $pageReviews,
                    'recognized_tables' => [[
                        'rows' => [
                            ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
                            ['row_id' => 1, 'bbox' => [0.0, 38.0, 358.0, 72.0]],
                        ],
                        'cols' => [
                            ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                            ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
                        ],
                        'cells' => [
                            ['bbox' => [12.0, 8.0, 160.0, 28.0], 'text' => 'Feature'],
                            ['bbox' => [198.0, 8.0, 344.0, 28.0], 'text' => 'Status'],
                            ['bbox' => [12.0, 44.0, 160.0, 66.0], 'text' => 'StructTree'],
                            ['bbox' => [198.0, 44.0, 344.0, 66.0], 'text' => 'Bundled'],
                        ],
                    ]],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $t->same(1, count($pageReviews));
        $t->same('bundle-4', $pageReviews[0]['page_label']);
        $t->contains('Tagged table review source', $plainText);
        $t->same(false, str_contains($plainText, 'Table annotation review note'));
        $t->same(false, str_contains($plainText, 'Current table annotation structure'));
        $t->same(false, str_contains($plainText, '<wp-export>'));

        $metadata = $result['metadata'];
        $t->same(['layout', 'page-review-metadata', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries']);
        $t->same(1, $metadata['page_review_metadata_count']);
        $t->contains('| Feature    | Status  |', $result['text']);
        $t->contains('| StructTree | Bundled |', $result['text']);
        $t->same(false, str_contains($result['text'], 'Legacy table text should be replaced.'));
        $t->contains('After structured table bundle.', $result['text']);

        $reviews = $metadata['table_section_caption_review'];
        $t->same(1, count($reviews));
        $pageReview = $reviews[0]['page_review'];
        $t->same('table_page_review_context', $pageReview['source']);
        $t->same('bundle-4', $pageReview['page_label']);
        $t->same(3, $pageReview['page_object']);
        $t->same(8, $pageReview['struct_parents']);
        $t->same('table-bundle-8', $pageReview['page_piece_info']['WPTable']['private']['BatchId']);
        $t->same(true, $pageReview['page_piece_info_review_only']);
        $t->same(2, $pageReview['structure_marked_content_count']);
        $t->same([0, 1], array_column($pageReview['structure_marked_content'], 'mcid'));
        $t->same(['Table', 'P'], array_column($pageReview['structure_marked_content'], 'role'));
        $t->same(['Tagged table structure', 'Body structure row'], array_column($pageReview['structure_marked_content'], 'title'));
        $t->same(1, $pageReview['annotation_structure_parent_count']);
        $t->same([7], array_column($pageReview['annotation_structure_parent_rows'], 'annotation_object'));
        $t->same([16], array_column($pageReview['annotation_structure_parent_rows'], 'struct_parent'));
        $t->same('Current table annotation structure', $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['title']);
        $t->same('Current table annotation actual text', $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['actual_text']);
        $t->same(1, $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['associated_file_count']);
        $t->same('table-annotation-source.xml', $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['associated_files'][0]['filename']);
        $t->same(hash('sha256', $layoutPageAnnotationStructTreeTablePayload), $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['associated_files'][0]['content_sha256']);
        $t->same(strtolower($layoutPageAnnotationStructTreeTableChecksum), $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['associated_files'][0]['checksum']);
        $t->same(true, $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['associated_files'][0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $pageReview['annotation_structure_parent_rows'][0]['structure_parent']['associated_files'][0]));
        $t->same(true, $pageReview['review_only']);
        $t->same(false, $pageReview['visible_text_source']);

        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Outside annotation review note'));
        $t->same(false, str_contains($encoded, 'Outside annotation structure'));
        $t->same(false, str_contains($encoded, $layoutPageAnnotationStructTreeTablePayload));
    },
];
