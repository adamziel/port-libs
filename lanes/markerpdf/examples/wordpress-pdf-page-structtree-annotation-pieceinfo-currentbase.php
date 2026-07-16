<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="annot-piece"/></wp-export>';
$checksum = strtoupper(hash('md5', $payload));
$content = 'BT /F1 12 Tf /Body << /MCID 0 >> BDC 72 720 Td (Visible annotation PieceInfo body) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (ann-) /S /D /St 6 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 6 /Contents 4 0 R /PieceInfo << /WPAnnot << /LastModified (D:20260602211000Z) /Private << /BatchId (annot-piece-6) /ReviewStage /annotation-structure /NeedsReview true >> >> >> /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 16 /Rect [72 690 260 730] /Contents (Editor note stays review only) /T (Import QA) /NM (piece-note) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 17 /Rect [72 642 260 680] /Contents (Detached stale note review only) /T (Detached QA) /NM (piece-stale) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (annotation-source.xml) /Desc (Annotation review source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260602210930Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Body /P /ReviewNote /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [6 [40 0 R] 16 41 0 R 17 42 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Body structure row) /K 0 >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Current annotation review structure) /Alt (Current annotation alternate text) /ActualText (Current annotation actual text) /AF [10 0 R] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Detached stale annotation structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review row.');
}

$page = $pageReviews[0];
$annotationRows = $page['annotation_structure_parent_rows'] ?? [];
$annotation = $annotationRows[0] ?? [];
$structure = is_array($annotation['structure_parent'] ?? null) ? $annotation['structure_parent'] : [];

if (($page['piece_info']['WPAnnot']['private']['BatchId'] ?? null) !== 'annot-piece-6') {
    throw new RuntimeException('Expected page PieceInfo review metadata.');
}
if (count($annotationRows) !== 1 || ($annotation['annotation_object'] ?? null) !== 7) {
    throw new RuntimeException('Expected only the current OBJR-backed annotation review row.');
}
if (($structure['role'] ?? null) !== 'Span' || ($structure['struct_object'] ?? null) !== 41) {
    throw new RuntimeException('Expected annotation StructParent structure metadata.');
}
if ($lines !== ['Visible annotation PieceInfo body']
    || str_contains($plainText, 'Editor note stays review only')
    || str_contains($plainText, 'Current annotation review structure')
    || str_contains($plainText, 'Current annotation alternate text')
    || str_contains($plainText, 'Current annotation actual text')
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'annot-piece-6')
) {
    throw new RuntimeException('Expected annotation, StructTree, and PieceInfo review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structtree-annotation-pieceinfo-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-structtree-annotation-pieceinfo-review-parser',
    'native_boundary' => 'page /PieceInfo composes with page /StructParents and singular annotation /StructParent OBJR rows before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'page_label' => $page['page_label'] ?? null,
    'struct_parents' => $page['struct_parents'] ?? null,
    'piece_info_apps' => array_keys($page['piece_info'] ?? []),
    'annotation_struct_parent' => $annotation['struct_parent'] ?? null,
    'annotation_struct_object' => $structure['struct_object'] ?? null,
    'annotation_role' => $structure['role'] ?? null,
    'structure_associated_file_count' => $structure['associated_file_count'] ?? null,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Editor note stays review only')
        && !str_contains($plainText, 'Current annotation review structure')
        && !str_contains($plainText, 'Current annotation alternate text')
        && !str_contains($plainText, 'Current annotation actual text')
        && !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'annot-piece-6'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structtree-annotation-pieceinfo-review ' . $htmlJson([
    'page_object' => $page['page_object'] ?? null,
    'page_label' => $page['page_label'] ?? null,
    'piece_info' => $page['piece_info'] ?? [],
    'parent_tree' => $page['parent_tree'] ?? [],
    'annotation_structure_parent_rows' => array_map(static fn (array $row): array => [
        'annotation_object' => $row['annotation_object'] ?? null,
        'subtype' => $row['subtype'] ?? null,
        'struct_parent' => $row['struct_parent'] ?? null,
        'structure_parent' => $row['structure_parent'] ?? [],
        'page_piece_info_review_only' => $row['page_piece_info_review_only'] ?? null,
        'review_only' => $row['review_only'] ?? null,
        'visible_text_source' => $row['visible_text_source'] ?? null,
    ], $annotationRows),
]) . " -->\n";
