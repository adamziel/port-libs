<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Kids tail jump Names tail jump Valid jump Legacy jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect array operand destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 60 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 61 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Dest (Kids Tail Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 262 718] /Dest (Names Tail Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [272 700 344 718] /Dest (Valid Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [354 700 430 718] /Dest /LegacyOk >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [440 700 514 718] /A << /S /URI /URI (https://example.com/indirect-array-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Kids Tail Target) (Valid Target)] /Kids [21 0 R 31 0 R 41 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(Kids Tail Target) (Kids Tail Target)] /Kids 22 0 R >>\nendobj\n"
    . "22 0 obj\n[23 0 R] 99 0 R\nendobj\n"
    . "23 0 obj\n<< /Limits [(Kids Tail Target) (Kids Tail Target)] /Names [(Kids Tail Target) [4 0 R /FitH 710]] >>\nendobj\n"
    . "31 0 obj\n<< /Limits [(Names Tail Target) (Names Tail Target)] /Names 32 0 R >>\nendobj\n"
    . "32 0 obj\n[(Names Tail Target) [4 0 R /XYZ 72 640 0]] 98 0 R\nendobj\n"
    . "41 0 obj\n<< /Limits [(Valid Target) (Valid Target)] /Names [(Valid Target) [4 0 R /FitBH 600]] >>\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Kids Tail Outline) /Parent 50 0 R /Dest (Kids Tail Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Names Tail Outline) /Parent 50 0 R /Dest (Names Tail Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Valid Outline) /Parent 50 0 R /Dest (Valid Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
    . "54 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 53 0 R >>\nendobj\n"
    . "60 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "61 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 514.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 514.0, 718.0],
            'spans' => [
                ['text' => 'Kids tail jump', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Names tail jump', 'bbox' => [170.0, 700.0, 262.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Valid jump', 'bbox' => [272.0, 700.0, 344.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [354.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [440.0, 700.0, 514.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$documentDestinationNames = $metadata['document_destinations']['names'] ?? [];
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$encodedReview = json_encode(
    [$destinations, $metadata['document_destinations'] ?? [], $toc, $links, $linkedPages],
    JSON_UNESCAPED_SLASHES
) ?: '';

if ($destinationNames !== ['Valid Target', 'LegacyOk']
    || $documentDestinationNames !== ['Valid Target', 'LegacyOk']
    || array_column($toc, 'title') !== ['Valid Outline', 'Legacy Outline']
    || $promotedLinkObjects !== [9, 10, 11]
    || str_contains($encodedReview, 'Kids Tail Target')
    || str_contains($encodedReview, 'Names Tail Target')
    || str_contains($encodedReview, 'Kids Tail Outline')
    || str_contains($encodedReview, 'Names Tail Outline')
    || str_contains($encodedReview, '710')
    || str_contains($encodedReview, '640')
    || !str_contains($plainText, 'Indirect array operand destination target body')
    || str_contains($plainText, 'indirect-array-boundary')
) {
    throw new RuntimeException('Expected tailed indirect name-tree arrays to stay out of WordPress destination output.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests array operands must resolve to exactly one top-level array',
    'destination_names' => $destinationNames,
    'document_destination_names' => $documentDestinationNames,
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => $promotedLinkObjects,
    'tailed_kids_destination_rejected' => !str_contains($encodedReview, 'Kids Tail Target'),
    'tailed_names_destination_rejected' => !str_contains($encodedReview, 'Names Tail Target'),
    'safe_uri_link_preserved' => in_array(11, $promotedLinkObjects, true),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Valid Outline')
        && !str_contains($plainText, 'indirect-array-boundary'),
];

echo '<!-- markerpdf-pdf-named-destination-indirect-array-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $metadata = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($metadata, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
