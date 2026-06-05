<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate destination jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate destination current target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /DuplicateReview [3 0 R /Fit] /LegacyOnly [3 0 R /FitV 90] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 244 718] /Dest (DuplicateReview) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [254 700 324 718] /A << /S /URI /URI (https://example.com/duplicate-destination-safe-uri) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(DuplicateReview) (SummaryReview)] /Kids [21 0 R 22 0 R 23 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [(DuplicateReview) (SummaryReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0] (SummaryReview) [4 0 R /FitBH 600]] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [99 /FitH 222]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Count 1 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Duplicate Destination Outline) /Parent 50 0 R /Dest (DuplicateReview) >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 324.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 324.0, 718.0],
            'spans' => [
                ['text' => 'Current duplicate destination jump', 'bbox' => [72.0, 700.0, 244.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [254.0, 700.0, 324.0, 718.0], 'font' => 'Helvetica'],
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
$documentDestinationPages = array_column($metadata['document_destinations']['destinations'] ?? [], 'page');
$promotedLinkPages = array_column($links[0]['links'] ?? [], 'destination_page');
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc, $links], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== ['DuplicateReview', 'SummaryReview', 'LegacyOnly']
    || ($destinations[0]['page'] ?? null) !== 1
    || $documentDestinationPages !== [1, 1, 0]
    || array_column($toc, 'page') !== [1]
    || ($promotedLinkPages[0] ?? null) !== 1
    || str_contains($encodedReview, 'FitH 111')
    || str_contains($encodedReview, '222')
    || str_contains($plainText, 'DuplicateReview')
    || str_contains($plainText, 'duplicate-destination-safe-uri')
) {
    throw new RuntimeException('Expected duplicate named-destination rows to select the later valid current destination before WordPress output.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'duplicate catalog /Names /Dests keys are accepted only after valid page-boundary resolution, with later valid name-tree rows overriding stale rows and malformed later rows ignored',
    'destination_names' => $destinationNames,
    'duplicate_review_page' => $destinations[0]['page'] ?? null,
    'duplicate_review_fit' => $destinations[0]['fit'] ?? null,
    'document_destination_pages' => $documentDestinationPages,
    'outline_pages' => array_column($toc, 'page'),
    'promoted_link_pages' => $promotedLinkPages,
    'stale_first_duplicate_hidden' => !str_contains($encodedReview, 'FitH 111'),
    'malformed_later_duplicate_hidden' => !str_contains($encodedReview, '222'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'DuplicateReview'),
];

echo '<!-- markerpdf-pdf-named-destination-duplicate-key-boundary-currentbase '
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
