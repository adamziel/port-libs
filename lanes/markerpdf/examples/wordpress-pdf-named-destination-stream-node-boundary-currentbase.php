<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Carrier jump Legacy jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Stream-node boundary target body) Tj ET';
$carrierPayload = 'BT /F1 12 Tf 72 720 Td (hidden carrier stream destination payload) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 162 718] /Dest (Carrier Decoy) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [172 700 252 718] /Dest /LegacyOk >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 330 718] /A << /S /URI /URI (https://example.com/stream-node-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /ObjStm /N 1 /First 0 /Names [(Carrier Decoy) [4 0 R /XYZ 72 640 0]] /Length " . strlen($carrierPayload) . " >>\nstream\n{$carrierPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 330.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 330.0, 718.0],
            'spans' => [
                ['text' => 'Carrier jump', 'bbox' => [72.0, 700.0, 162.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [172.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [262.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedReview = json_encode([$destinations, $documentDestinations, $linkedPages], JSON_UNESCAPED_SLASHES);

if (array_column($destinations, 'name') !== ['LegacyOk']) {
    throw new RuntimeException('Expected stream-object name-tree node to be rejected before WordPress destination review.');
}
if (($linkedPages[0]['blocks'][0]['lines'][0]['spans'][0]['link_destination'] ?? null) !== null) {
    throw new RuntimeException('Expected stream-carrier destination decoy to stay out of WordPress span links.');
}
if (($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_destination'] ?? null) !== 'LegacyOk') {
    throw new RuntimeException('Expected legacy named destination link to remain promotable.');
}
if (!is_string($encodedReview)
    || str_contains($encodedReview, 'Carrier Decoy')
    || str_contains($encodedReview, 'hidden carrier stream destination payload')
    || str_contains($plainText, 'Carrier Decoy')
    || str_contains($plainText, 'hidden carrier stream destination payload')
) {
    throw new RuntimeException('Expected stream-carrier destination metadata and payload text to remain hidden.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog named destinations reject stream-object name-tree nodes before WordPress navigation review',
    'destination_names' => array_column($destinations, 'name'),
    'document_destination_count' => $documentDestinations['count'] ?? null,
    'stream_object_destination_rejected' => !str_contains($encodedReview, 'Carrier Decoy'),
    'legacy_destination_promoted' => ($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_destination'] ?? null) === 'LegacyOk',
    'visible_text_excludes_stream_payload' => !str_contains($plainText, 'hidden carrier stream destination payload'),
    'wordpress_text' => $blocks[0]['text'] ?? '',
];

echo '<!-- markerpdf-pdf-named-destination-stream-node-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $item = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($item, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
echo '<p>' . htmlspecialchars($blocks[0]['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
