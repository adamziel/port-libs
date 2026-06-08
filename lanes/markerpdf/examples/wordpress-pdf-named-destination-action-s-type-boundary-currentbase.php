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

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid destination jump Direct action jump Indirect action jump Tailed action jump Plain destination jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Action subtype boundary target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk << /D [4 0 R /FitV 130] >> /LegacyBadIndirect << /S 22 0 R /D [4 0 R /FitH 333] >> >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /MediaBox [0 0 800 792] >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 184 718] /Dest (Valid GoTo Dest) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [194 700 300 718] /Dest (Direct URI Dest) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 422 718] /Dest (Indirect Launch Dest) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [432 700 536 718] /Dest (Tailed GoTo Dest) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [546 700 658 718] /Dest (Plain Dict Dest) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [668 700 732 718] /A << /S /URI /URI (https://example.com/named-destination-s-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Valid GoTo Dest) << /S /GoTo /D [4 0 R /FitH 700] >> (Direct URI Dest) << /S /URI /URI (https://example.com/hidden-direct-destination-action) /D [4 0 R /FitH 111] >> (Indirect Launch Dest) << /S 21 0 R /D [4 0 R /FitV 222] >> (Tailed GoTo Dest) << /S 22 0 R /D [4 0 R /FitR 1 2 3 4] >> (Plain Dict Dest) << /D [4 0 R /XYZ 72 640 0] >>] >>\nendobj\n"
    . "21 0 obj\n/Launch\nendobj\n"
    . "22 0 obj\n/GoTo << /S /URI /URI (https://example.com/hidden-tailed-destination-action) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 55 0 R /Count 5 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Valid GoTo Outline) /Parent 50 0 R /Dest (Valid GoTo Dest) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Direct URI Outline) /Parent 50 0 R /Dest (Direct URI Dest) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Indirect Launch Outline) /Parent 50 0 R /Dest (Indirect Launch Dest) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
    . "54 0 obj\n<< /Title (Tailed GoTo Outline) /Parent 50 0 R /Dest (Tailed GoTo Dest) /Prev 53 0 R /Next 55 0 R >>\nendobj\n"
    . "55 0 obj\n<< /Title (Plain Dict Outline) /Parent 50 0 R /Dest (Plain Dict Dest) /Prev 54 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 732.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 732.0, 718.0],
            'spans' => [
                ['text' => 'Valid destination jump', 'bbox' => [72.0, 700.0, 184.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct action jump', 'bbox' => [194.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect action jump', 'bbox' => [310.0, 700.0, 422.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed action jump', 'bbox' => [432.0, 700.0, 536.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Plain destination jump', 'bbox' => [546.0, 700.0, 658.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [668.0, 700.0, 732.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$documentDestinationNames = $documentDestinations['names'] ?? [];
$tocTitles = array_column($toc, 'title');
$annotationSafety = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);
$linkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedReview = json_encode([$destinations, $documentDestinations, $toc, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== ['Valid GoTo Dest', 'Plain Dict Dest', 'LegacyOk']) {
    throw new RuntimeException('Expected only local GoTo destination dictionaries before WordPress named-destination output.');
}
if ($documentDestinationNames !== ['Valid GoTo Dest', 'Plain Dict Dest', 'LegacyOk']) {
    throw new RuntimeException('Expected metadata document destinations to reject non-GoTo action dictionaries.');
}
if ($tocTitles !== ['Valid GoTo Outline', 'Plain Dict Outline']) {
    throw new RuntimeException('Expected outlines to reject non-GoTo named destinations before WordPress TOC import.');
}
if ($annotationSafety !== [['local-destination'], [], [], [], ['local-destination'], ['review-uri']]) {
    throw new RuntimeException('Expected unsafe named-destination action dictionaries to stay out of annotation action chains.');
}
if ($linkObjects !== [7, 11, 12]) {
    throw new RuntimeException('Expected only safe local destinations and direct URI links to be promoted.');
}
if (($spans[0]['link_destination'] ?? null) !== 'Valid GoTo Dest'
    || isset($spans[1]['link_destination'])
    || isset($spans[2]['link_destination'])
    || isset($spans[3]['link_destination'])
    || ($spans[4]['link_destination'] ?? null) !== 'Plain Dict Dest'
    || ($spans[5]['link_uri'] ?? null) !== 'https://example.com/named-destination-s-boundary'
) {
    throw new RuntimeException('Expected malformed named destinations to stay off WordPress text spans.');
}
if ($wordpressText !== 'Valid destination jump Direct action jump Indirect action jump Tailed action jump Plain destination jump [Safe URI](https://example.com/named-destination-s-boundary)') {
    throw new RuntimeException('Unexpected WordPress Markdown output.');
}
foreach ([
    'Direct URI Dest',
    'Indirect Launch Dest',
    'Tailed GoTo Dest',
    'LegacyBadIndirect',
    'hidden-direct-destination-action',
    'hidden-tailed-destination-action',
    'FitH 111',
    'FitV 222',
    'FitH 333',
] as $hidden) {
    if (str_contains($encodedReview, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Malformed named-destination action payload leaked into WordPress review or visible text.');
    }
}

$summary = [
    'support_component' => 'native-pdf-named-destination-action-subtype-boundary',
    'native_boundary' => 'named destination dictionaries accept /S /GoTo plus /D or bare /D, and reject non-GoTo or trailing-action /S operands before WordPress review/link promotion',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'document_destination_count' => $documentDestinations['count'] ?? null,
    'toc_titles' => $tocTitles,
    'annotation_safety' => $annotationSafety,
    'promoted_link_objects' => $linkObjects,
    'malformed_destination_promoted' => false,
    'direct_uri_promoted' => true,
    'visible_text_imported' => str_contains($plainText, 'Valid destination jump')
        && str_contains($plainText, 'Action subtype boundary target body'),
    'wordpress_markdown' => $wordpressText,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-named-destination-action-s-type-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
