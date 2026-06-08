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

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Rejected named jump Legacy jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect limits destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacySafe [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 216 718] /Dest (Clean Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [226 700 300 718] /Dest /LegacySafe >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 378 718] /A << /S /URI /URI (https://example.com/named-destination-indirect-limits-tail) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [21 0 R (Stale Tail Target)] /Names [(Clean Target) [4 0 R /XYZ 72 640 0] (Stale Tail Target) [3 0 R /FitH 111]] >>\nendobj\n"
    . "21 0 obj\n(Clean Target) /PrivateLimitTail\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Clean Target Outline) /Parent 50 0 R /Dest (Clean Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Legacy Target Outline) /Parent 50 0 R /Dest /LegacySafe /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Stale Tail Outline) /Parent 50 0 R /Dest (Stale Tail Target) /Prev 52 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 378.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 378.0, 718.0],
            'spans' => [
                ['text' => 'Rejected named jump', 'bbox' => [72.0, 700.0, 216.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [226.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [310.0, 700.0, 378.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$documentDestinations = $metadata['document_destinations'] ?? [];
$encodedReview = json_encode([$destinations, $documentDestinations, $toc, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (
    array_column($destinations, 'name') !== ['LegacySafe']
    || ($documentDestinations['names'] ?? []) !== ['LegacySafe']
    || array_column($toc, 'title') !== ['Legacy Target Outline']
    || array_column($links[0]['links'] ?? [], 'annotation_object') !== [8, 9]
    || str_contains($encodedReview, 'Clean Target')
    || str_contains($encodedReview, 'Stale Tail Target')
    || str_contains($encodedReview, 'PrivateLimitTail')
    || str_contains($plainText, 'named-destination-indirect-limits-tail')
) {
    throw new RuntimeException('Expected tailed indirect /Limits operands to stay out of WordPress review output.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'indirect name-tree Limits operands must resolve to one top-level PDF value',
    'destination_names' => array_column($destinations, 'name'),
    'document_destination_names' => $documentDestinations['names'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'annotation_action_safety' => array_map(
        static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
        $annotations[0]['annotations'] ?? []
    ),
    'tailed_limits_rejected' => !str_contains($encodedReview, 'Clean Target')
        && !str_contains($encodedReview, 'Stale Tail Target')
        && !str_contains($encodedReview, 'PrivateLimitTail'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Clean Target')
        && !str_contains($plainText, 'Stale Tail Target')
        && !str_contains($plainText, 'Legacy Target Outline'),
    'wordpress_text' => $blocks[0]['text'] ?? '',
];

echo '<!-- markerpdf-pdf-named-destination-indirect-limits-operand-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
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
