<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid coordinate jump Tailed coordinate jump Tailed view jump Legacy jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect operand tail destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 188 718] /Dest (Valid Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [198 700 344 718] /Dest (Tailed Coordinate Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [354 700 462 718] /Dest (Tailed View Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [472 700 550 718] /Dest /LegacyOk >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [560 700 632 718] /A << /S /URI /URI (https://example.com/named-destination-indirect-operand-tail) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Tailed Coordinate Target) (Valid Target)] /Names [(Valid Target) [4 0 R /XYZ 21 0 R 22 0 R 23 0 R] (Tailed Coordinate Target) [4 0 R /FitH 24 0 R] (Tailed View Target) [4 0 R 25 0 R 500]] >>\nendobj\n"
    . "21 0 obj\n72\nendobj\n"
    . "22 0 obj\n640\nendobj\n"
    . "23 0 obj\n0\nendobj\n"
    . "24 0 obj\n610 /PrivateCoordinateTail\nendobj\n"
    . "25 0 obj\n/FitH /PrivateViewTail\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Valid Target Outline) /Parent 50 0 R /Dest (Valid Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Tailed Coordinate Outline) /Parent 50 0 R /Dest (Tailed Coordinate Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Tailed View Outline) /Parent 50 0 R /Dest (Tailed View Target) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
    . "54 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 53 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 632.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 632.0, 718.0],
            'spans' => [
                ['text' => 'Valid coordinate jump', 'bbox' => [72.0, 700.0, 188.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed coordinate jump', 'bbox' => [198.0, 700.0, 344.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed view jump', 'bbox' => [354.0, 700.0, 462.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [472.0, 700.0, 550.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [560.0, 700.0, 632.0, 718.0], 'font' => 'Helvetica'],
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

if (array_column($destinations, 'name') !== ['Valid Target', 'LegacyOk']
    || ($documentDestinations['names'] ?? []) !== ['Valid Target', 'LegacyOk']
    || array_column($toc, 'title') !== ['Valid Target Outline', 'Legacy Outline']
    || array_column($links[0]['links'] ?? [], 'annotation_object') !== [7, 10, 11]
    || str_contains($encodedReview, 'Tailed Coordinate Target')
    || str_contains($encodedReview, 'Tailed View Target')
    || str_contains($encodedReview, 'PrivateCoordinateTail')
    || str_contains($encodedReview, 'PrivateViewTail')
    || str_contains($plainText, 'named-destination-indirect-operand-tail')) {
    throw new RuntimeException('Expected tailed indirect named-destination operands to stay out of WordPress review output.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'indirect named-destination view/coordinate operands must resolve to one top-level PDF value',
    'destination_names' => array_column($destinations, 'name'),
    'document_destination_names' => $documentDestinations['names'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'tailed_coordinate_destination_rejected' => !str_contains($encodedReview, 'Tailed Coordinate Target'),
    'tailed_view_destination_rejected' => !str_contains($encodedReview, 'Tailed View Target'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Valid Target Outline')
        && !str_contains($plainText, 'PrivateCoordinateTail')
        && !str_contains($plainText, 'PrivateViewTail'),
    'wordpress_text' => $blocks[0]['text'] ?? '',
];

echo '<!-- markerpdf-pdf-named-destination-indirect-operand-tail-boundary-currentbase '
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
