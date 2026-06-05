<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Invalid view jump Action invalid jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Named destination view target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyBad [4 0 R /Launch 88] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 142 718] /Dest (Valid Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [152 700 264 718] /Dest (Invalid View Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [274 700 396 718] /A << /S /GoTo /D (Action Invalid View) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [406 700 470 718] /A << /S /URI /URI (https://example.com/named-destination-action-view) >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Valid Target) [4 0 R /XYZ 72 640 0] (Invalid View Target) [4 0 R /Launch 77] (Indirect Invalid View) [4 0 R 21 0 R 88] (Action Invalid View) << /S /GoTo /D [4 0 R /Movie 99] >>] >>\nendobj\n"
    . "21 0 obj\n/RichMedia\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 470.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'spans' => [
                ['text' => 'Valid jump', 'bbox' => [72.0, 700.0, 142.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Invalid view jump', 'bbox' => [152.0, 700.0, 264.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Action invalid jump', 'bbox' => [274.0, 700.0, 396.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [406.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$annotationSafety = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-action-review-named-destination-view-boundary',
    'destination_names' => $destinationNames,
    'document_destination_names' => $metadata['document_destinations']['names'] ?? [],
    'promoted_link_objects' => $promotedLinkObjects,
    'annotation_action_safety' => $annotationSafety,
    'invalid_view_destinations_rejected' => $destinationNames === ['Valid Target', 'LegacyOk'],
    'invalid_view_links_rejected' => $promotedLinkObjects === [7, 10],
    'unsupported_goto_reviewed_without_promotion' => ($annotationSafety[2] ?? []) === ['unsupported-action-review'],
    'safe_uri_link_preserved' => ($links[0]['links'][1]['uri'] ?? null) === 'https://example.com/named-destination-action-view',
    'valid_named_destination_promoted' => ($links[0]['links'][0]['destination'] ?? null) === 'Valid Target',
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Invalid View Target')
        && !str_contains($plainText, 'Action Invalid View')
        && !str_contains($plainText, 'LegacyBad'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$summary['invalid_view_destinations_rejected']
    || !$summary['invalid_view_links_rejected']
    || !$summary['unsupported_goto_reviewed_without_promotion']
    || !$summary['safe_uri_link_preserved']
    || !$summary['valid_named_destination_promoted']
    || !$summary['visible_text_excludes_destination_metadata']
) {
    throw new RuntimeException('Named-destination invalid view modes must stay out of WordPress link promotion.');
}

echo '<!-- markerpdf-pdf-named-destination-action-view-mode-boundary ' . htmlspecialchars(
    json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($blocks[0]['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
