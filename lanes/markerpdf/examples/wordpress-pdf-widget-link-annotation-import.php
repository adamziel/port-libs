<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Download docs Section jump Hidden widget) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Widget destination target) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 160 718] /P 3 0 R /F 4 /Parent 20 0 R /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [170 700 260 718] /P 3 0 R /F 4 /Parent 21 0 R /Dest [4 0 R /FitH 720] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [270 700 340 718] /P 3 0 R /F 36 /Parent 22 0 R /A << /S /URI /URI (https://example.com/hidden-widget) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 650 160 668] /Parent 22 0 R /A << /S /URI /URI (https://example.com/detached-widget) >> >>\nendobj\n"
    . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Names [(target-section) 17 0 R] >>\nendobj\n"
    . "17 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
    . "20 0 obj\n<< /FT /Btn /T (download.docs) /Ff 65536 /Kids [7 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /FT /Btn /T (section.jump) /Ff 65536 /Kids [8 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /FT /Btn /T (hidden.widget) /Ff 65536 /Kids [9 0 R 10 0 R] >>\nendobj\n"
    . "30 0 obj\n<< /S /URI /URI (https://example.com/widget-docs) /Next << /S /GoTo /D (target-section) >> >>\nendobj\n"
    . "31 0 obj\n<< /S /URI /URI (mailto:review@example.com) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 340.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'spans' => [
                ['text' => 'Download docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Section jump', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden widget', 'bbox' => [270.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));

$summary = [
    'support_component' => 'native-pdf-link-annotation-parser',
    'native_boundary' => 'current page /Widget annotations with safe URI or local destinations become non-executing link review metadata before WordPress rendering',
    'link_count' => count($links),
    'widget_link_count' => count(array_filter($links, static fn (array $link): bool => ($link['widget_annotation'] ?? false) === true)),
    'uri_widget_object' => $links[0]['annotation_object'] ?? null,
    'uri_widget_href' => $links[0]['uri'] ?? null,
    'uri_widget_chained_destination' => $links[0]['actions'][1]['destination'] ?? null,
    'mouse_up_action_safety' => $links[0]['additional_actions'][0]['safety'] ?? null,
    'destination_widget_object' => $links[1]['annotation_object'] ?? null,
    'destination_widget_page' => $links[1]['destination_page'] ?? null,
    'destination_widget_view_mode' => $links[1]['view_mode'] ?? null,
    'hidden_widget_excluded' => !isset($spans[2]['link_uri']) && !isset($spans[2]['link_destination_page']),
    'detached_widget_excluded' => count($links) === 2,
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-widget-link-annotation-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-widget-link="true">' . $text . '</a>';
        continue;
    }

    if (isset($span['link_destination_page'])) {
        echo '<span data-markerpdf-widget-destination-page="' . (int) $span['link_destination_page'] . '"'
            . ' data-markerpdf-widget-view-mode="' . htmlspecialchars((string) ($span['link_view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . $text
            . '</span>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
