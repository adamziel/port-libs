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

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Scalar kids jump Legacy jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Scalar Kids destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 176 718] /Dest (Scalar Kids Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [186 700 268 718] /Dest /LegacyOk >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [278 700 350 718] /A << /S /URI /URI (https://example.com/scalar-kids-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Review Summary) (Scalar Kids Target)] /Kids /ScalarKid /Names [(Scalar Kids Target) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Scalar Kids Outline) /Parent 50 0 R /Dest (Scalar Kids Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 350.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 350.0, 718.0],
            'spans' => [
                ['text' => 'Scalar kids jump', 'bbox' => [72.0, 700.0, 176.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [186.0, 700.0, 268.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [278.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
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

$destinationNames = array_column($destinations, 'name');
$documentDestinationNames = $metadata['document_destinations']['names'] ?? [];
$tocTitles = array_column($toc, 'title');
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$encodedReview = json_encode(
    [$destinations, $metadata['document_destinations'] ?? [], $toc, $annotations, $links, $linkedPages],
    JSON_UNESCAPED_SLASHES
) ?: '';

if ($destinationNames !== ['LegacyOk']
    || $documentDestinationNames !== ['LegacyOk']
    || $tocTitles !== ['Legacy Outline']
    || $promotedLinkObjects !== [8, 9]
    || str_contains($encodedReview, 'Scalar Kids Target')
    || str_contains($encodedReview, 'Scalar Kids Outline')
    || !str_contains($plainText, 'Scalar Kids destination target body')
    || str_contains($plainText, 'scalar-kids-boundary')
) {
    throw new RuntimeException('Expected scalar /Kids name-tree rows to stay out of WordPress destination review.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'catalog /Names /Dests nodes reject scalar Kids operands instead of treating them as leaf nodes',
    'destination_names' => $destinationNames,
    'document_destination_names' => $documentDestinationNames,
    'toc_titles' => $tocTitles,
    'promoted_link_objects' => $promotedLinkObjects,
    'scalar_kids_destinations_rejected' => !str_contains($encodedReview, 'Scalar Kids Target'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Scalar Kids Target')
        && !str_contains($plainText, 'Scalar Kids Outline')
        && !str_contains($plainText, 'scalar-kids-boundary'),
];

echo '<!-- markerpdf-pdf-named-destination-scalar-kids-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n<p>"
        . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</p>\n<!-- /wp:paragraph -->\n";
}

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
