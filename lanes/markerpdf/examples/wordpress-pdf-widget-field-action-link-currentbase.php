<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Inherited widget Local widget Unsafe parent) Tj ET';
$targetText = 'BT /F1 12 Tf 72 720 Td (Inherited widget target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 185 718] /P 3 0 R /F 4 /Parent 20 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [194 700 286 718] /P 3 0 R /F 4 /Parent 21 0 R /A << /S /URI /URI (https://example.com/local-widget-action) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [294 700 390 718] /P 3 0 R /F 4 /Parent 22 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R 23 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Names [(inherited-target) 17 0 R] >>\nendobj\n"
    . "17 0 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "20 0 obj\n<< /FT /Btn /T (inherited.widget) /Kids [7 0 R] /A 30 0 R /AA << /U 31 0 R /D << /S /GoTo /D (inherited-target) >> >> >>\nendobj\n"
    . "21 0 obj\n<< /FT /Btn /T (local.widget) /Kids [8 0 R] /A << /S /URI /URI (https://example.com/stale-parent-action) >> >>\nendobj\n"
    . "22 0 obj\n<< /FT /Btn /T (unsafe.parent) /Kids [9 0 R] /A << /S /URI /URI (javascript:parentFieldReview\\(\\)) >> >>\nendobj\n"
    . "23 0 obj\n<< /FT /Btn /T (detached.inherited.widget) /Kids [24 0 R] /A << /S /URI /URI (https://example.com/detached-field-widget) >> >>\nendobj\n"
    . "24 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 640 180 658] /Parent 23 0 R >>\nendobj\n"
    . "30 0 obj\n<< /S /URI /URI (https://example.com/inherited-widget-action) /Next << /S /GoTo /D (inherited-target) >> >>\nendobj\n"
    . "31 0 obj\n<< /S /URI /URI (mailto:field-review@example.com) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 390.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'spans' => [
                ['text' => 'Inherited widget', 'bbox' => [72.0, 700.0, 185.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Local widget', 'bbox' => [194.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Unsafe parent', 'bbox' => [294.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
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
    'support_component' => 'native-pdf-widget-field-action-link-boundary',
    'native_boundary' => 'page-referenced Widget annotations inherit terminal-field A/AA link actions without promoting detached field-only widgets',
    'link_count' => count($links),
    'widget_link_count' => count(array_filter($links, static fn (array $link): bool => ($link['widget_annotation'] ?? false) === true)),
    'inherited_widget_object' => $links[0]['annotation_object'] ?? null,
    'inherited_widget_keys' => $links[0]['inherited_widget_link_keys'] ?? [],
    'field_parent_object' => $links[0]['widget_field_parent_object'] ?? null,
    'field_chain' => $links[0]['widget_field_chain'] ?? [],
    'field_additional_action_events' => array_column($links[0]['additional_actions'] ?? [], 'event'),
    'local_widget_uses_local_action' => ($links[1]['uri'] ?? null) === 'https://example.com/local-widget-action',
    'unsafe_parent_promoted' => str_contains($encoded, 'parentFieldReview'),
    'detached_field_widget_promoted' => str_contains($encoded, 'detached-field-widget'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['link_count'] ?? 0) !== 2 || ($summary['unsafe_parent_promoted'] ?? true) || ($summary['detached_field_widget_promoted'] ?? true)) {
    throw new RuntimeException('Widget field action link boundary smoke failed.');
}

echo '<!-- markerpdf-widget-field-action-link-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-widget-action-source="'
            . htmlspecialchars((string) ($span['link_widget_action_source'] ?? 'annotation'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
