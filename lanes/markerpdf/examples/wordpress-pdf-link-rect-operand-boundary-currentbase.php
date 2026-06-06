<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean rect Null rect Name rect Ref rect) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean rect review) /A << /S /URI /URI (https://example.com/clean-rect) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 null 250 718] /Contents (Null rect review) /A << /S /URI /URI (https://example.com/null-rect-decoy) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 /BadCoordinate 350 718] /Contents (Name rect review) /A << /S /URI /URI (https://example.com/name-rect-decoy) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 20 0 R 450 718] /Contents (Reference rect review) /A << /S /URI /URI (https://example.com/ref-rect-decoy) >> >>\nendobj\n"
    . "20 0 obj\n(not a numeric coordinate)\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 450.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'spans' => [
                ['text' => 'Clean rect', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Null rect', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Name rect', 'bbox' => [260.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Ref rect', 'bbox' => [360.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$markdown = (string) ($blocks[0]['text'] ?? '');

if ($markdown !== '[Clean rect](https://example.com/clean-rect) Null rect Name rect Ref rect') {
    throw new RuntimeException('Unexpected WordPress Markdown for malformed Link /Rect operand boundary.');
}
if (($spans[0]['link_uri'] ?? null) !== 'https://example.com/clean-rect') {
    throw new RuntimeException('Valid Link /Rect was not promoted.');
}
if (isset($spans[1]['link_uri']) || isset($spans[2]['link_uri']) || isset($spans[3]['link_uri'])) {
    throw new RuntimeException('Malformed Link /Rect operands were promoted.');
}
if (str_contains($plainText, 'rect-decoy') || str_contains($plainText, 'rect review')) {
    throw new RuntimeException('Link annotation review payload leaked into visible text.');
}

$summary = [
    'support_component' => 'native-pdf-link-rect-operand-boundary',
    'native_boundary' => 'malformed Link annotation Rect operands fail closed before WordPress span promotion',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'malformed_null_rect_linked' => isset($spans[1]['link_uri']),
    'malformed_name_rect_linked' => isset($spans[2]['link_uri']),
    'malformed_reference_rect_linked' => isset($spans[3]['link_uri']),
    'wordpress_markdown' => $markdown,
    'visible_text_imported' => str_contains($plainText, 'Clean rect Null rect Name rect Ref rect'),
    'annotation_payload_text_visible' => str_contains($plainText, 'rect-decoy') || str_contains($plainText, 'rect review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-link-rect-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>';
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n";
echo "<!-- /wp:paragraph -->\n";
