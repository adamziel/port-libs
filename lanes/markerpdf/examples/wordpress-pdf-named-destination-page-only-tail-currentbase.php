<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Page tail jump Numeric tail jump Alias jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Page-only tail destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /MediaBox [0 0 800 792] >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Dest (Valid Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 284 718] /Dest (Page Tail Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [294 700 426 718] /Dest (Numeric Tail Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [436 700 520 718] /Dest (Alias Target) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [530 700 610 718] /A << /S /URI /URI (https://example.com/named-destination-page-only-tail) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alias Target) (Valid Target)] /Names [(Valid Target) [4 0 R /XYZ 72 640 0] (Page Tail Target) 4 0 R /FitH 610 (Numeric Tail Target) 1 /FitV 120 (Alias Target) /LegacyOk] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Valid Outline) /Parent 50 0 R /Dest (Valid Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Page Tail Outline) /Parent 50 0 R /Dest (Page Tail Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Numeric Tail Outline) /Parent 50 0 R /Dest (Numeric Tail Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
    . "54 0 obj\n<< /Title (Alias Outline) /Parent 50 0 R /Dest (Alias Target) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
    . "55 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 54 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 610.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 610.0, 718.0],
            'spans' => [
                ['text' => 'Valid jump', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Page tail jump', 'bbox' => [170.0, 700.0, 284.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Numeric tail jump', 'bbox' => [294.0, 700.0, 426.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Alias jump', 'bbox' => [436.0, 700.0, 520.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [530.0, 700.0, 610.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$documentDestinations = $metadata['document_destinations'] ?? [];
$encodedReview = json_encode([$destinations, $documentDestinations, $toc, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($destinations, 'name') !== ['Valid Target', 'Alias Target', 'LegacyOk']
    || ($documentDestinations['names'] ?? []) !== ['Valid Target', 'Alias Target', 'LegacyOk']
    || array_column($toc, 'title') !== ['Valid Outline', 'Alias Outline', 'Legacy Outline']
    || array_column($links[0]['links'] ?? [], 'annotation_object') !== [7, 10, 11]
    || str_contains($encodedReview, 'Page Tail Target')
    || str_contains($encodedReview, 'Numeric Tail Target')
    || str_contains($encodedReview, 'Page Tail Outline')
    || str_contains($plainText, 'named-destination-page-only-tail')) {
    throw new RuntimeException('Expected unbracketed page-only destination view operands to stay out of WordPress review output.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'page-only name-tree destination values followed by unbracketed view operands are rejected',
    'destination_names' => array_column($destinations, 'name'),
    'document_destination_names' => $documentDestinations['names'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'page_only_tail_destination_rejected' => !str_contains($encodedReview, 'Page Tail Target'),
    'numeric_page_tail_destination_rejected' => !str_contains($encodedReview, 'Numeric Tail Target'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Page Tail Outline'),
    'wordpress_text' => $blocks[0]['text'] ?? '',
];

echo '<!-- markerpdf-pdf-named-destination-page-only-tail-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $attributes = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($attributes, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
