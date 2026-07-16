<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (ASCII alias jump UTF16 alias jump Safe URI) Tj ET';
$asciiPageContent = 'BT /F1 12 Tf 72 720 Td (ASCII collision alias destination page) Tj ET';
$utf16PageContent = 'BT /F1 12 Tf 72 720 Td (UTF16 collision alias destination page) Tj ET';
$utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 178 718] /Dest (Alias ASCII) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [188 700 296 718] /Dest (Alias UTF16) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [306 700 380 718] /A << /S /URI /URI (https://example.com/named-destination-byte-alias) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alias ASCII) {$utf16Collision}] /Names [(Collision) [4 0 R /FitH 710] {$utf16Collision} [5 0 R /XYZ 72 640 0] (Alias ASCII) (Collision) (Alias UTF16) {$utf16Collision}] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($asciiPageContent) . " >>\nstream\n{$asciiPageContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($utf16PageContent) . " >>\nstream\n{$utf16PageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (ASCII Alias Outline) /Parent 50 0 R /Dest (Alias ASCII) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (UTF16 Alias Outline) /Parent 50 0 R /Dest (Alias UTF16) /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 380.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 380.0, 718.0],
            'spans' => [
                ['text' => 'ASCII alias jump', 'bbox' => [72.0, 700.0, 178.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' UTF16 alias jump', 'bbox' => [188.0, 700.0, 296.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [306.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];

if (array_column($destinations, 'name') !== ['Collision', 'Collision', 'Alias ASCII', 'Alias UTF16']
    || array_column($destinations, 'page') !== [1, 2, 1, 2]
    || array_column($documentDestinations['destinations'] ?? [], 'page') !== [1, 2, 1, 2]
    || array_column($toc, 'page') !== [1, 2]
    || ($spans[0]['link_destination_page'] ?? null) !== 1
    || ($spans[1]['link_destination_page'] ?? null) !== 2
    || ($blocks[0]['text'] ?? null) !== 'ASCII alias jump UTF16 alias jump [Safe URI](https://example.com/named-destination-byte-alias)'
) {
    throw new RuntimeException('Expected raw-byte destination aliases to resolve to distinct WordPress review pages.');
}

foreach (['Collision', 'Alias ASCII', 'Alias UTF16', 'Alias Outline', 'named-destination-byte-alias'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected named-destination alias metadata to stay out of visible WordPress text.');
    }
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'decoded-collision name-tree aliases resolve by raw PDF string bytes before WordPress link metadata',
    'destination_names' => array_column($destinations, 'name'),
    'destination_pages' => array_column($destinations, 'page'),
    'metadata_destination_pages' => array_column($documentDestinations['destinations'] ?? [], 'page'),
    'toc_pages' => array_column($toc, 'page'),
    'span_destination_pages' => [$spans[0]['link_destination_page'] ?? null, $spans[1]['link_destination_page'] ?? null],
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Collision')
        && !str_contains($plainText, 'Alias ASCII')
        && !str_contains($plainText, 'Alias UTF16'),
];

echo '<!-- markerpdf-pdf-named-destination-decoded-collision-alias-currentbase '
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
