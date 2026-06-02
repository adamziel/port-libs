<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="preview-bundle"/></wp-export>';
$checksum = strtoupper(hash('md5', $payload));
$content = 'BT /F1 12 Tf '
    . '/Body << /MCID 0 >> BDC 40 340 Td (Visible preview body) Tj EMC '
    . '/Caption << /MCID 1 >> BDC 40 310 Td (Visible caption) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (prev-) /S /D /St 4 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 300 400] /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 8 /Contents 4 0 R /PieceInfo << /WPPreview << /LastModified (D:20260602221200Z) /Private << /BundleId (preview-bundle-8) /ReviewStage /layout-overlay /NeedsReview true >> >> >> /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 18 /Rect [40 330 210 360] /Contents (Private link note) /T (Preview Reviewer) /NM (preview-link) /A << /S /URI /URI (https://example.com/private-preview) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /StructParent 19 /Rect [40 300 180 322] /QuadPoints [40 322 180 322 40 300 180 300] /Contents (Private highlight note) /T (Markup Reviewer) /Subj (Preview highlight) /NM (preview-highlight) /C [1 0.9 0] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (preview-source.xml) /Desc (Preview source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260602221130Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Body /P /Caption /Caption /ReviewLink /Link /ReviewMarkup /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R 43 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [8 [40 0 R 41 0 R] 18 42 0 R 19 43 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Visible body structure) /K 0 >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /Caption /Pg 3 0 R /T (Visible caption structure) /K 1 >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (Link structure review) /Alt (Link alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /ReviewMarkup /Pg 3 0 R /T (Highlight structure review) /ActualText (Highlight actual review) /K << /Type /OBJR /Obj 8 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pages = [[
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

$pages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($pages, $pdf);
$bundle = (new MarkerAppPreview())->getPageLayoutPreviewBundle($pdf, 1, $pages, 72.0);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (($bundle['layout_block_count'] ?? null) !== 2
    || ($bundle['annotation_count'] ?? null) !== 2
    || ($bundle['text_markup_annotation_count'] ?? null) !== 1
    || ($bundle['annotation_structure_parent_row_count'] ?? null) !== 2
    || ($bundle['structure_marked_content_count'] ?? null) !== 2
) {
    throw new RuntimeException('Expected annotation, StructTree, markup, and layout preview rows.');
}
if (($bundle['annotations'][0]['structure_parent']['associated_files'][0]['filename'] ?? null) !== 'preview-source.xml') {
    throw new RuntimeException('Expected annotation StructParent associated-file review metadata.');
}
if (($bundle['layout_blocks'][1]['review_annotation_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected supplied markup span review count on the caption layout block.');
}
if ($lines !== ['Visible preview body', 'Visible caption']
    || str_contains($plainText, 'Private link note')
    || str_contains($plainText, 'Private highlight note')
    || str_contains($plainText, 'Link structure review')
    || str_contains($plainText, 'Highlight structure review')
    || str_contains($plainText, 'Highlight actual review')
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'preview-source.xml')
    || str_contains($plainText, 'preview-bundle-8')
    || str_contains($plainText, 'https://example.com/private-preview')
) {
    throw new RuntimeException('Expected preview review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-annotation-structtree-layout-preview-currentbase ' . $htmlJson([
    'support_component' => 'native-marker-app-page-annotation-structtree-layout-preview-bundle',
    'native_boundary' => 'page image preview geometry composes with annotation StructParent rows, page ParentTree rows, and supplied layout blocks without action execution',
    'page_label' => $bundle['page_review']['page_label'] ?? null,
    'layout_block_count' => $bundle['layout_block_count'] ?? null,
    'annotation_count' => $bundle['annotation_count'] ?? null,
    'text_markup_annotation_count' => $bundle['text_markup_annotation_count'] ?? null,
    'annotation_structure_parent_row_count' => $bundle['annotation_structure_parent_row_count'] ?? null,
    'structure_marked_content_count' => $bundle['structure_marked_content_count'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Private link note')
        && !str_contains($plainText, 'Private highlight note')
        && !str_contains($plainText, 'Link structure review')
        && !str_contains($plainText, 'Highlight structure review')
        && !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'preview-source.xml'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-annotation-structtree-layout-preview-bundle ' . $htmlJson([
    'image_plan' => [
        'page_number' => $bundle['image_plan']['page_number'] ?? null,
        'rendered_image_size' => $bundle['image_plan']['rendered_image_size'] ?? null,
        'annotation_mode' => $bundle['image_plan']['annotation_mode'] ?? null,
    ],
    'layout_blocks' => $bundle['layout_blocks'] ?? [],
    'annotation_preview' => array_map(static fn (array $row): array => [
        'subtype' => $row['subtype'] ?? null,
        'annotation_object' => $row['annotation_object'] ?? null,
        'struct_parent' => $row['struct_parent'] ?? null,
        'preview_bbox' => $row['preview_bbox'] ?? null,
        'role' => $row['structure_parent']['role'] ?? null,
        'associated_file_count' => $row['structure_parent']['associated_file_count'] ?? null,
        'visible_text_source' => $row['visible_text_source'] ?? null,
    ], $bundle['annotations'] ?? []),
]) . " -->\n";
