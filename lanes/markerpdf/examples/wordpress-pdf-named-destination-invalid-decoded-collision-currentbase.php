<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid alias jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Valid decoded destination page) Tj ET';
$utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /Dest (Alias Valid) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [220 700 288 718] /A << /S /URI /URI (https://example.com/valid-named-destination) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alias Valid) {$utf16Collision}] /Names [(Collision) [99 0 R /FitH 111] {$utf16Collision} [4 0 R /XYZ 72 640 0] (Alias Valid) (Collision)] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 51 0 R /Count 1 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Valid Alias Outline) /Parent 50 0 R /Dest (Alias Valid) >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 288.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 288.0, 718.0],
            'spans' => [
                ['text' => 'Valid alias jump', 'bbox' => [72.0, 700.0, 210.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [220.0, 700.0, 288.0, 718.0], 'font' => 'Helvetica'],
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
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];

if (array_column($destinations, 'name') !== ['Collision', 'Alias Valid']
    || array_column($destinations, 'page') !== [1, 1]
    || array_column($documentDestinations['destinations'] ?? [], 'page') !== [1, 1]
    || ($documentDestinations['unresolved_count'] ?? null) !== 1
    || array_column($toc, 'page') !== [1]
    || ($links[0]['links'][0]['destination_page'] ?? null) !== 1
    || ($spans[0]['link_destination_page'] ?? null) !== 1
    || ($blocks[0]['text'] ?? null) !== 'Valid alias jump [Safe URI](https://example.com/valid-named-destination)'
) {
    throw new RuntimeException('Expected invalid decoded-collision rows to be ignored before WordPress alias resolution.');
}

foreach (['Collision', 'Alias Valid', 'Valid Alias Outline', 'FitH 111', '99 0 R', 'valid-named-destination'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected invalid destination operands and review labels to stay out of visible WordPress text.');
    }
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'invalid raw name-tree rows do not create decoded-collision aliases before WordPress link review',
    'destination_names' => array_column($destinations, 'name'),
    'destination_pages' => array_column($destinations, 'page'),
    'metadata_destination_pages' => array_column($documentDestinations['destinations'] ?? [], 'page'),
    'metadata_unresolved_count' => $documentDestinations['unresolved_count'] ?? null,
    'toc_pages' => array_column($toc, 'page'),
    'link_destination_page' => $links[0]['links'][0]['destination_page'] ?? null,
    'span_destination_page' => $spans[0]['link_destination_page'] ?? null,
    'visible_text_excludes_destination_metadata' => true,
];

echo '<!-- markerpdf-pdf-named-destination-invalid-decoded-collision-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n\n";
echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $item = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerNameBytesHex' => $destination['name_bytes_hex'] ?? null,
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($item, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
