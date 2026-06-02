<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Widget docs Named target Hidden widget) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Widget destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 40 0 R /P 3 0 R /F 41 0 R /Parent 20 0 R /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 42 0 R /P 3 0 R /F 43 0 R /Parent 21 0 R /Dest 44 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 45 0 R /P 3 0 R /F 46 0 R /Parent 22 0 R /A << /S /URI /URI (https://example.com/hidden-widget) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Widget /Rect 47 0 R /P 3 0 R /F 48 0 R /Parent 23 0 R /A << /S /URI /URI (https://example.com/no-view-widget) >> >>\nendobj\n"
    . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R 23 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Names [(widget-target) 17 0 R] >>\nendobj\n"
    . "17 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
    . "20 0 obj\n<< /FT /Btn /T (widget.docs) /Kids [7 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /FT /Btn /T (widget.target) /Kids [8 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /FT /Btn /T (widget.hidden) /Kids [9 0 R] >>\nendobj\n"
    . "23 0 obj\n<< /FT /Btn /T (widget.no_view) /Kids [10 0 R] >>\nendobj\n"
    . "30 0 obj\n<< /S /URI /URI (https://example.com/indirect-widget) /Next << /S /GoTo /D (widget-target) >> >>\nendobj\n"
    . "31 0 obj\n<< /S /URI /URI (mailto:widget-review@example.com) >>\nendobj\n"
    . "40 0 obj\n[72 700 158 718]\nendobj\n"
    . "41 0 obj\n4\nendobj\n"
    . "42 0 obj\n[166 700 248 718]\nendobj\n"
    . "43 0 obj\n4\nendobj\n"
    . "44 0 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "45 0 obj\n[256 700 340 718]\nendobj\n"
    . "46 0 obj\n2\nendobj\n"
    . "47 0 obj\n[72 650 140 668]\nendobj\n"
    . "48 0 obj\n32\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 340.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'spans' => [
                ['text' => 'Widget docs', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Named target', 'bbox' => [166.0, 700.0, 248.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden widget', 'bbox' => [256.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encoded = json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-page-widget-link-parser',
    'native_boundary' => 'page-scoped Widget annotation links resolve indirect Rect and F operands before WordPress span promotion',
    'link_count' => count($links),
    'widget_link_count' => count(array_filter($links, static fn (array $link): bool => ($link['widget_annotation'] ?? false) === true)),
    'uri_widget_rect' => $links[0]['rect'] ?? null,
    'uri_widget_href' => $links[0]['uri'] ?? null,
    'mouse_up_action_safety' => $links[0]['additional_actions'][0]['safety'] ?? null,
    'destination_widget_page' => $links[1]['destination_page'] ?? null,
    'destination_widget_view_mode' => $links[1]['view_mode'] ?? null,
    'indirect_hidden_widget_excluded' => !str_contains($encoded, 'hidden-widget'),
    'indirect_no_view_widget_excluded' => !str_contains($encoded, 'no-view-widget'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-page-widget-link-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

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
