<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review link Destination jump Hidden stale) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Destination target page) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 31 /Rect [72 700 150 718] /Contents (Review link annotation note) /A << /S /URI /URI (https://example.com/review-link) /Next 12 0 R >> /AA << /U << /S /JavaScript /JS (hoverReview\\(\\)) >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 32 /Rect [160 700 250 718] /A << /S /GoTo /D (dest-review) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 99 /Rect [260 700 340 718] /F 2 /A << /S /URI /URI (https://example.com/stale-hidden) >> >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D (dest-review) >>\nendobj\n"
    . "13 0 obj\n<< /Names [(dest-review) 14 0 R] >>\nendobj\n"
    . "14 0 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Reference /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [31 40 0 R 32 41 0 R 99 42 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /Link /Pg 3 0 R /T (Review action structure) /ActualText (Actual review link text) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /Reference /Pg 3 0 R /Alt (Destination link alt review) /K [<< /Type /OBJR /Obj 8 0 R >>] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /Link /Pg 3 0 R /T (Hidden stale action structure) /K << /Type /OBJR /Obj 9 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 340.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'spans' => [
                ['text' => 'Review link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Destination jump', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden stale', 'bbox' => [260.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkPages = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (count($linkPages) !== 1 || count($linkPages[0]['links']) !== 2) {
    throw new RuntimeException('Expected two visible current page action link annotations.');
}

$uri = $linkPages[0]['links'][0];
$destination = $linkPages[0]['links'][1];
if (($uri['struct_parent'] ?? null) !== 31 || ($uri['structure_parent']['struct_object'] ?? null) !== 40) {
    throw new RuntimeException('Expected URI action annotation StructParent to resolve through ParentTree.');
}
if (($destination['struct_parent'] ?? null) !== 32 || ($destination['structure_parent']['role'] ?? null) !== 'Link') {
    throw new RuntimeException('Expected destination action annotation StructParent role mapping.');
}
if (($linkedPages[0]['blocks'][0]['lines'][0]['spans'][0]['link_struct_parent'] ?? null) !== 31
    || ($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_struct_parent'] ?? null) !== 32
    || isset($linkedPages[0]['blocks'][0]['lines'][0]['spans'][2]['link_struct_parent'])
) {
    throw new RuntimeException('Expected only visible current action links to receive ParentTree span metadata.');
}
if (str_contains($plainText, 'Review action structure')
    || str_contains($plainText, 'Actual review link text')
    || str_contains($plainText, 'Destination link alt review')
    || str_contains($plainText, 'Hidden stale action structure')
    || str_contains($plainText, 'hoverReview')
) {
    throw new RuntimeException('Expected ParentTree/action review text to stay out of visible WordPress paragraphs.');
}

$json = static fn (array $value): string => htmlspecialchars(
    json_encode($value, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-page-parenttree-action-annotation-currentbase ' . $json([
    'support_component' => 'native-pdf-link-annotation-parenttree-review',
    'native_boundary' => 'current page Link annotations carry singular StructParent ParentTree review through WordPress link span metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'visible_link_count' => count($linkPages[0]['links']),
    'hidden_action_annotation_promoted' => false,
    'uri_struct_parent' => $uri['struct_parent'] ?? null,
    'destination_struct_parent' => $destination['struct_parent'] ?? null,
    'uri_structure_role' => $uri['structure_parent']['role'] ?? null,
    'destination_structure_role' => $destination['structure_parent']['role'] ?? null,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Review action structure')
        && !str_contains($plainText, 'Actual review link text')
        && !str_contains($plainText, 'Destination link alt review')
        && !str_contains($plainText, 'Hidden stale action structure')
        && !str_contains($plainText, 'hoverReview'),
]) . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:link-annotation-parenttree-review ' . $json([
    'links' => array_map(static fn (array $link): array => [
        'annotation_object' => $link['annotation_object'] ?? null,
        'action_type' => $link['action_type'] ?? null,
        'safety' => $link['safety'] ?? null,
        'struct_parent' => $link['struct_parent'] ?? null,
        'structure_parent' => [
            'struct_object' => $link['structure_parent']['struct_object'] ?? null,
            'role' => $link['structure_parent']['role'] ?? null,
            'annotation_objects' => $link['structure_parent']['annotation_objects'] ?? [],
            'review_only' => $link['structure_parent']['review_only'] ?? null,
            'visible_text_source' => $link['structure_parent']['visible_text_source'] ?? null,
        ],
    ], $linkPages[0]['links']),
]) . " -->\n";
