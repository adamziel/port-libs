<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (ASCII collision jump UTF16 collision jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (UTF16 collision destination target body) Tj ET';
$utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 180 718] /Dest (Collision) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [190 700 306 718] /Dest {$utf16Collision} >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [316 700 386 718] /A << /S /URI /URI (https://example.com/named-destination-byte-collision) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Collision) {$utf16Collision}] /Names [(Collision) [3 0 R /FitH 700] {$utf16Collision} [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (ASCII Collision Outline) /Parent 50 0 R /Dest (Collision) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (UTF16 Collision Outline) /Parent 50 0 R /Dest {$utf16Collision} /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 386.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 386.0, 718.0],
            'spans' => [
                ['text' => 'ASCII collision jump', 'bbox' => [72.0, 700.0, 180.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' UTF16 collision jump', 'bbox' => [190.0, 700.0, 306.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [316.0, 700.0, 386.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (array_column($destinations, 'name') !== ['Collision', 'Collision']
    || array_column($destinations, 'page') !== [0, 1]
    || ($destinations[0]['name_bytes_hex'] ?? null) !== '436f6c6c6973696f6e'
    || ($destinations[1]['name_bytes_hex'] ?? null) !== 'feff0043006f006c006c006900730069006f006e'
) {
    throw new RuntimeException('Expected distinct raw-byte named destinations with the same decoded label.');
}
if (array_column($toc, 'page') !== [0, 1] || array_column($toc, 'view_mode') !== ['FitH', 'XYZ']) {
    throw new RuntimeException('Expected outline destination review to preserve raw-byte destination targets.');
}
if (($spans[0]['link_destination_page'] ?? null) !== 0
    || ($spans[1]['link_destination_page'] ?? null) !== 1
    || ($spans[2]['link_uri'] ?? null) !== 'https://example.com/named-destination-byte-collision'
) {
    throw new RuntimeException('Expected WordPress span link metadata to preserve distinct destination pages.');
}
if (($blocks[0]['text'] ?? null) !== 'ASCII collision jump UTF16 collision jump [Safe URI](https://example.com/named-destination-byte-collision)') {
    throw new RuntimeException('Expected only the safe URI annotation to become visible WordPress Markdown.');
}
if (!str_contains($plainText, 'ASCII collision jump UTF16 collision jump Safe URI')
    || !str_contains($plainText, 'UTF16 collision destination target body')
    || str_contains($plainText, 'Collision Outline')
    || str_contains($plainText, 'named-destination-byte-collision')
) {
    throw new RuntimeException('Expected named-destination operands to stay out of visible WordPress text.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-action-map',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'decoded-collision /Names /Dests entries resolve outline and annotation actions by raw PDF string bytes',
    'destination_names' => array_column($destinations, 'name'),
    'destination_pages' => array_column($destinations, 'page'),
    'destination_name_bytes_hex' => array_column($destinations, 'name_bytes_hex'),
    'toc_pages' => array_column($toc, 'page'),
    'span_destination_pages' => [$spans[0]['link_destination_page'] ?? null, $spans[1]['link_destination_page'] ?? null],
    'uri_markdown_visible' => str_contains($blocks[0]['text'] ?? '', 'https://example.com/named-destination-byte-collision'),
    'destination_operands_visible_text_excluded' => !str_contains($plainText, 'Collision Outline')
        && !str_contains($plainText, 'named-destination-byte-collision'),
];

echo '<!-- markerpdf-pdf-named-destination-byte-collision-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-destination-page="'
        . htmlspecialchars((string) $item['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-view="'
        . htmlspecialchars((string) $item['view_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
