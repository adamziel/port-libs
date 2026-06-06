<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Partial limits stale page link) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Partial limits current destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Annots [7 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Partial bounded kid outline) /Parent 5 0 R /Dest (DuplicateReview) >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Dest (DuplicateReview) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Kids [14 0 R 21 0 R 22 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Names [(Zulu Ignored) [3 0 R /Fit] (zz-stale-ignored) [3 0 R /Fit]] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Names [(A Broad) [3 0 R /Fit] (DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 250.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'spans' => [[
                'text' => 'Partial limits stale page link',
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$outline = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$reviewJson = json_encode([$destinations, $outline, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$destinationNames = array_column($destinations, 'name');
$outlinePages = array_column($outline, 'page');
$linkPages = array_column($links[0]['links'] ?? [], 'destination_page');
$linkModes = array_column($links[0]['links'] ?? [], 'view_mode');

if (
    $destinationNames !== ['A Broad', 'DuplicateReview']
    || $outlinePages !== [1]
    || $linkPages !== [1]
    || $linkModes !== ['XYZ']
    || str_contains($reviewJson, 'FitH')
    || str_contains($reviewJson, '111')
    || str_contains($plainText, 'DuplicateReview')
) {
    throw new RuntimeException('Expected partial bounded name-tree kids to resolve current destination review rows before WordPress rendering.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-action-review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'partially bounded /Kids are ordered by effective /Limits for outline and link destination review',
    'destination_names' => $destinationNames,
    'outline_pages' => $outlinePages,
    'link_destination_pages' => $linkPages,
    'link_view_modes' => $linkModes,
    'stale_broad_duplicate_hidden_from_review' => !str_contains($reviewJson, 'FitH') && !str_contains($reviewJson, '111'),
    'visible_text_excludes_destination_labels' => !str_contains($plainText, 'DuplicateReview'),
];

echo '<!-- markerpdf-pdf-named-destination-partial-kid-action-review-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($outline as $item) {
    echo '<li data-marker-outline-destination="' . htmlspecialchars((string) ($item['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) (($item['page'] ?? -1) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-view="' . htmlspecialchars((string) ($item['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
