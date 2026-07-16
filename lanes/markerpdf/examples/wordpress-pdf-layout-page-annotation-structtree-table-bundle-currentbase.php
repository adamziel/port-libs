<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="layout-table-annotation"/></wp-export>';
$checksum = strtoupper(hash('md5', $payload));
$content = 'BT /F1 12 Tf '
    . '/TaggedTable << /MCID 0 >> BDC 72 720 Td (Tagged table review source) Tj EMC '
    . '/Body << /MCID 1 >> BDC 72 684 Td (Body after tagged table) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (bundle-) /S /D /St 4 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 8 /Contents 4 0 R /PieceInfo << /WPTable << /LastModified (D:20260602220100Z) /Private << /BatchId (table-bundle-8) /ReviewStage /table-annotation-structure /NeedsReview true >> >> >> /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 16 /Rect [72 150 430 230] /Contents (Table annotation review note) /T (Table QA) /NM (table-note) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 17 /Rect [72 500 430 540] /Contents (Outside annotation review note) /T (Outside QA) /NM (outside-note) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (table-annotation-source.xml) /Desc (Table annotation source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260602220030Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /TaggedTable /Table /Body /P /ReviewNote /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R 43 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [8 [40 0 R 41 0 R] 16 42 0 R 17 43 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /TaggedTable /Pg 3 0 R /T (Tagged table structure) /K 0 >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Body structure row) /K 1 >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Current table annotation structure) /Alt (Current table annotation alternate text) /ActualText (Current table annotation actual text) /AF [10 0 R] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Outside annotation structure) /K << /Type /OBJR /Obj 8 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

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

$pdftextPage = [
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

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$path = sys_get_temp_dir() . '/markerpdf-wordpress-layout-table-bundle-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% supplied layout table current-base fixture\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [$pdftextPage],
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

$tableReviews = $converted['metadata']['table_section_caption_review'] ?? [];
$pageReview = is_array($tableReviews[0]['page_review'] ?? null) ? $tableReviews[0]['page_review'] : [];
$annotationRows = is_array($pageReview['annotation_structure_parent_rows'] ?? null)
    ? $pageReview['annotation_structure_parent_rows']
    : [];

if (($pageReview['page_label'] ?? null) !== 'bundle-4') {
    throw new RuntimeException('Expected table review to carry page label metadata.');
}
if (($pageReview['annotation_structure_parent_count'] ?? null) !== 1 || (($annotationRows[0]['annotation_object'] ?? null) !== 7)) {
    throw new RuntimeException('Expected only the overlapping annotation StructParent row to attach to the table review.');
}
if (str_contains(json_encode($converted['metadata'], JSON_UNESCAPED_SLASHES) ?: '', 'Outside annotation structure')) {
    throw new RuntimeException('Expected outside annotation review metadata to stay detached from the table context.');
}
if (str_contains($converted['text'], 'Legacy table text should be replaced.')
    || str_contains($converted['text'], 'Table annotation review note')
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected table replacement and review-only metadata boundaries.');
}

echo json_encode([
    'scenario' => 'wordpress-pdf-layout-page-annotation-structtree-table-bundle-currentbase',
    'native_boundary' => 'supplied table formatting carries page StructTree and overlapping annotation StructParent review metadata without promoting review text to WordPress blocks',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Structured Table Import</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>StructTree</td><td>Bundled</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After structured table bundle.</p>'],
    ],
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'page_review_metadata_count' => $converted['metadata']['page_review_metadata_count'] ?? null,
    'table_page_review' => [
        'page_label' => $pageReview['page_label'] ?? null,
        'struct_parents' => $pageReview['struct_parents'] ?? null,
        'piece_info_apps' => array_keys($pageReview['page_piece_info'] ?? []),
        'structure_roles' => $pageReview['structure_roles'] ?? [],
        'annotation_objects' => array_column($annotationRows, 'annotation_object'),
        'annotation_struct_parents' => $pageReview['annotation_struct_parents'] ?? [],
        'annotation_associated_file' => $annotationRows[0]['structure_parent']['associated_files'][0]['filename'] ?? null,
        'review_only' => $pageReview['review_only'] ?? null,
        'visible_text_source' => $pageReview['visible_text_source'] ?? null,
    ],
    'excluded_legacy_table_text' => !str_contains($converted['text'], 'Legacy table text should be replaced.'),
    'excluded_outside_annotation_context' => !str_contains(json_encode($converted['metadata'], JSON_UNESCAPED_SLASHES) ?: '', 'Outside annotation structure'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $converted['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
