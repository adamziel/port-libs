<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Valid destination jump Stale byte jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Byte-limited destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 188 718] /Dest <41> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [198 700 304 718] /Dest <80> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [314 700 378 718] /A << /S /URI /URI (https://example.com/byte-limit-action) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [<18> <41>] /Names [<18> [3 0 R /FitH 700] <41> [4 0 R /XYZ 72 640 0] <80> [4 0 R /FitH 111]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Valid A Outline) /Parent 50 0 R /Dest <41> /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Stale Bullet Outline) /Parent 50 0 R /Dest <80> /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 378.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 378.0, 718.0],
            'spans' => [
                ['text' => 'Valid destination jump', 'bbox' => [72.0, 700.0, 188.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale byte jump', 'bbox' => [198.0, 700.0, 304.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [314.0, 700.0, 378.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc, $annotations, $links], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (array_column($destinations, 'name') !== ["\u{02d8}", 'A', 'LegacyOk']) {
    throw new RuntimeException('Expected named destinations to be bounded by raw PDF string bytes.');
}
if (array_column($toc, 'title') !== ['Valid A Outline']) {
    throw new RuntimeException('Expected byte-out-of-range outline destination to stay out of TOC metadata.');
}
if (count($links) !== 1 || array_column($links[0]['links'], 'annotation_object') !== [7, 9]) {
    throw new RuntimeException('Expected byte-out-of-range annotation destination to stay out of link promotion.');
}
if (str_contains($encodedReview, "\u{2022}") || str_contains($encodedReview, 'Stale Bullet Outline') || str_contains($encodedReview, '111')) {
    throw new RuntimeException('Expected stale byte-out-of-range destination rows to stay review-only excluded.');
}

echo '<!-- markerpdf-pdf-named-destination-byte-limit-action-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF /Names /Dests byte-string /Limits constrain outline and annotation local-destination review metadata',
    'destination_names' => array_column($destinations, 'name'),
    'metadata_destination_names' => $metadata['document_destinations']['names'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'link_annotation_objects' => count($links) === 1 ? array_column($links[0]['links'], 'annotation_object') : [],
    'byte_out_of_range_destination_excluded' => !str_contains($encodedReview, "\u{2022}"),
    'stale_outline_excluded' => !str_contains($encodedReview, 'Stale Bullet Outline'),
    'visible_text_excludes_destination_operands' => !str_contains($plainText, "\u{2022}")
        && !str_contains($plainText, 'Stale Bullet Outline')
        && !str_contains($plainText, 'byte-limit-action'),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    $text = is_string($block['text'] ?? null) ? $block['text'] : '';
    if ($text === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-outline-destination="' . htmlspecialchars((string) ($item['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Outline destination: ' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
