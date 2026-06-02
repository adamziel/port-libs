<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf /Body << /MCID 0 >> BDC 72 720 Td (Visible contextual span) Tj EMC ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (ctx-) /S /D /St 3 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 9 /Contents 5 0 R /Annots [7 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Highlight /StructParent 20 /Rect [72 700 210 720] /QuadPoints [72 720 210 720 72 700 210 700] /Contents (Private markup review note) /T (Import Reviewer) /Subj (Context highlight) /NM (ctx-highlight-1) /C [1 0.85 0] >>\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Body /P /ReviewMarkup /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [9 [40 0 R] 20 41 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Visible body structure) /K 0 >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /ReviewMarkup /Pg 3 0 R /T (Span review structure) /Alt (Accessible span review) /ActualText (Actual span review) /ID (span-review-id) /C [/qa /highlight] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$suppliedPages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 260.0, 720.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 260.0, 720.0],
            'spans' => [
                ['text' => 'Visible contextual span', 'bbox' => [72.0, 700.0, 210.0, 720.0], 'font' => 'Helvetica'],
                ['text' => 'outside', 'bbox' => [220.0, 700.0, 260.0, 720.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$markedPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($suppliedPages, $pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$review = $markedPages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0] ?? [];

if (($review['page_structparent_context']['struct_parents'] ?? null) !== 9) {
    throw new RuntimeException('Expected page StructParents context on supplied markup review span.');
}
if (($review['structure_parent']['struct_object'] ?? null) !== 41 || ($review['structure_parent']['role'] ?? null) !== 'Span') {
    throw new RuntimeException('Expected annotation StructParent structure context on supplied markup review span.');
}
if (isset($markedPages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations'])) {
    throw new RuntimeException('Expected outside span to stay unannotated.');
}
if ($lines !== ['Visible contextual span']
    || str_contains($plainText, 'Private markup review note')
    || str_contains($plainText, 'Span review structure')
    || str_contains($plainText, 'Accessible span review')
    || str_contains($plainText, 'Actual span review')
    || str_contains($plainText, 'span-review-id')
) {
    throw new RuntimeException('Expected structure and annotation review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structparent-markup-annotation-context-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-structparent-markup-annotation-context-parser',
    'native_boundary' => 'supplied marker/pdftext spans carry page /StructParents context plus singular annotation /StructParent OBJR context as review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'page_label' => $review['page_structparent_context']['page_label'] ?? null,
    'page_struct_parents' => $review['page_structparent_context']['struct_parents'] ?? null,
    'page_parent_tree_mcids' => $review['page_structparent_context']['parent_tree']['mcids'] ?? [],
    'markup_struct_parent' => $review['struct_parent'] ?? null,
    'markup_struct_object' => $review['structure_parent']['struct_object'] ?? null,
    'markup_role' => $review['structure_parent']['role'] ?? null,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Private markup review note')
        && !str_contains($plainText, 'Span review structure')
        && !str_contains($plainText, 'Accessible span review')
        && !str_contains($plainText, 'Actual span review')
        && !str_contains($plainText, 'span-review-id'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structparent-markup-annotation-context-review ' . $htmlJson([
    'page_context' => $review['page_structparent_context'] ?? [],
    'structure_parent' => $review['structure_parent'] ?? [],
    'annotation_object' => $review['annotation_object'] ?? null,
    'subtype' => $review['subtype'] ?? null,
    'quad_rect' => $review['quad_rect'] ?? null,
    'review_only' => $review['structure_parent']['review_only'] ?? null,
    'visible_text_source' => $review['structure_parent']['visible_text_source'] ?? null,
]) . " -->\n";
