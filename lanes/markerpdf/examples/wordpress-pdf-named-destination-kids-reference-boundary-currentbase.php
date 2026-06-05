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

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current child jump Direct child jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Kids reference destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Dest (Current Child) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [184 700 286 718] /Dest (Direct Child Decoy) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [296 700 376 718] /A << /S /URI /URI (https://example.com/kids-reference-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Current Child) (Review Summary)] /Kids [21 0 R << /Limits [(Direct Child Decoy) (Direct Child Decoy)] /Names [(Direct Child Decoy) [4 0 R /FitH 111]] >> 22 1 R /ScalarKid] >>\nendobj\n"
    . "21 0 obj\n<< /Names [(Current Child) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "22 0 obj\n<< /Names [(Generation Child Decoy) [4 0 R /FitBH 222]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Current Child Outline) /Parent 50 0 R /Dest (Current Child) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Direct Child Outline) /Parent 50 0 R /Dest (Direct Child Decoy) /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 376.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 376.0, 718.0],
            'spans' => [
                ['text' => 'Current child jump', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct child jump', 'bbox' => [184.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [296.0, 700.0, 376.0, 718.0], 'font' => 'Helvetica'],
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
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$encodedReview = json_encode(
    [$destinations, $metadata['document_destinations'] ?? [], $toc, $annotations, $links, $linkedPages],
    JSON_UNESCAPED_SLASHES
) ?: '';

if ($destinationNames !== ['Current Child', 'Review Summary', 'LegacyOk']
    || $documentDestinationNames !== ['Current Child', 'Review Summary', 'LegacyOk']
    || array_column($toc, 'title') !== ['Current Child Outline']
    || $promotedLinkObjects !== [7, 9]
    || str_contains($encodedReview, 'Direct Child Decoy')
    || str_contains($encodedReview, 'Generation Child Decoy')
    || str_contains($encodedReview, 'Direct Child Outline')
    || str_contains($encodedReview, '111')
    || str_contains($encodedReview, '222')
    || !str_contains($plainText, 'Kids reference destination target body')
    || str_contains($plainText, 'kids-reference-boundary')
) {
    throw new RuntimeException('Expected destination /Kids entries to require valid indirect child references before WordPress output.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests /Kids arrays recurse only through valid indirect child-node references',
    'destination_names' => $destinationNames,
    'document_destination_names' => $documentDestinationNames,
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => $promotedLinkObjects,
    'direct_child_destination_rejected' => !str_contains($encodedReview, 'Direct Child Decoy'),
    'bad_generation_child_rejected' => !str_contains($encodedReview, 'Generation Child Decoy'),
    'safe_uri_link_preserved' => in_array(9, $promotedLinkObjects, true),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Current Child Outline')
        && !str_contains($plainText, 'Direct Child Decoy')
        && !str_contains($plainText, 'kids-reference-boundary'),
];

echo '<!-- markerpdf-pdf-named-destination-kids-reference-boundary-currentbase '
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
