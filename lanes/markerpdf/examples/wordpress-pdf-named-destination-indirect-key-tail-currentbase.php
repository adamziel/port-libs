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

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Tailed key jump Valid jump Legacy jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect key tail destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 168 718] /Dest (Tailed Key) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [178 700 260 718] /Dest (Valid Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 352 718] /Dest /LegacyOk >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [362 700 440 718] /A << /S /URI /URI (https://example.com/indirect-key-tail) >> >>\nendobj\n"
    . "12 0 obj\n(Tailed Key) /Extra\nendobj\n"
    . "20 0 obj\n<< /Limits [(Tailed Key) (Valid Target)] /Names [12 0 R [4 0 R /FitH 710] (Valid Target) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Tailed Key Outline) /Parent 50 0 R /Dest (Tailed Key) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Valid Outline) /Parent 50 0 R /Dest (Valid Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Prev 52 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 440.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 440.0, 718.0],
            'spans' => [
                ['text' => 'Tailed key jump', 'bbox' => [72.0, 700.0, 168.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Valid jump', 'bbox' => [178.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [270.0, 700.0, 352.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [362.0, 700.0, 440.0, 718.0], 'font' => 'Helvetica'],
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

if ($destinationNames !== ['Valid Target', 'LegacyOk']
    || $documentDestinationNames !== ['Valid Target', 'LegacyOk']
    || $tocTitles !== ['Valid Outline', 'Legacy Outline']
    || $promotedLinkObjects !== [8, 9, 10]
    || str_contains($encodedReview, 'Tailed Key')
    || str_contains($encodedReview, 'Tailed Key Outline')
    || !str_contains($plainText, 'Indirect key tail destination target body')
    || str_contains($plainText, 'indirect-key-tail')
) {
    throw new RuntimeException('Expected tailed indirect string name-tree keys to stay out of WordPress destination review.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'catalog /Names /Dests rejects indirect string keys with trailing operands before destination review',
    'destination_names' => $destinationNames,
    'document_destination_names' => $documentDestinationNames,
    'toc_titles' => $tocTitles,
    'promoted_link_objects' => $promotedLinkObjects,
    'tailed_key_destination_rejected' => !str_contains($encodedReview, 'Tailed Key'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Tailed Key')
        && !str_contains($plainText, 'Tailed Key Outline')
        && !str_contains($plainText, 'indirect-key-tail'),
];

echo '<!-- markerpdf-pdf-named-destination-indirect-key-tail-currentbase '
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
