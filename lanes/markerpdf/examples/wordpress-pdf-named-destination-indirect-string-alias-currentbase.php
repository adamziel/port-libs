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

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Indirect alias jump Direct alias jump Stray jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect string alias target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 198 718] /Dest (Indirect Alias) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [208 700 318 718] /Dest (Direct Alias) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [328 700 396 718] /Dest /StrayOperand >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [406 700 478 718] /A << /S /URI /URI (https://example.com/indirect-string-alias) >> >>\nendobj\n"
    . "12 0 obj\n(Actual Target)\nendobj\n"
    . "20 0 obj\n<< /Limits [(Actual Target) (Indirect Alias)] /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (Indirect Alias) 12 0 R /StrayOperand (Direct Alias) (Actual Target)] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Actual Target Outline) /Parent 50 0 R /Dest (Actual Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Indirect Alias Outline) /Parent 50 0 R /Dest (Indirect Alias) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Direct Alias Outline) /Parent 50 0 R /Dest (Direct Alias) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
    . "54 0 obj\n<< /Title (Stray Operand Outline) /Parent 50 0 R /Dest /StrayOperand /Prev 53 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 478.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 478.0, 718.0],
            'spans' => [
                ['text' => 'Indirect alias jump', 'bbox' => [72.0, 700.0, 198.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct alias jump', 'bbox' => [208.0, 700.0, 318.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stray jump', 'bbox' => [328.0, 700.0, 396.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [406.0, 700.0, 478.0, 718.0], 'font' => 'Helvetica'],
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
$expectedNames = ['Actual Target', 'Indirect Alias', 'Direct Alias', 'LegacyTail'];
$documentDestinations = is_array($metadata['document_destinations'] ?? null)
    ? $metadata['document_destinations']
    : [];
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$resolvedLinkTargets = array_column(array_slice($links[0]['links'] ?? [], 0, 2), 'destination');
$encodedReview = json_encode([$destinations, $documentDestinations, $toc, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== $expectedNames) {
    throw new RuntimeException('Expected indirect string alias rows to remain in native named-destination review.');
}
if (($documentDestinations['names'] ?? null) !== $expectedNames) {
    throw new RuntimeException('Expected indirect string alias rows to remain in document destination metadata.');
}
if (array_column($toc, 'title') !== ['Actual Target Outline', 'Indirect Alias Outline', 'Direct Alias Outline']) {
    throw new RuntimeException('Expected alias outline rows to resolve while the stray operand outline is rejected.');
}
if ($promotedLinkObjects !== [7, 8, 10] || $resolvedLinkTargets !== ['Actual Target', 'Actual Target']) {
    throw new RuntimeException('Expected indirect/direct alias links and URI to promote while stray name operands stay hidden.');
}
if (($blocks[0]['text'] ?? '') !== 'Indirect alias jump Direct alias jump Stray jump [Safe URI](https://example.com/indirect-string-alias)') {
    throw new RuntimeException('Expected WordPress span merge to keep visible link text stable.');
}
foreach (['Actual Target', 'Indirect Alias', 'Direct Alias', 'StrayOperand', 'indirect-string-alias'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected destination metadata and URI text to remain out of visible PDF text.');
    }
}
foreach (['StrayOperand', 'Stray Operand Outline'] as $hidden) {
    if (str_contains($encodedReview, $hidden)) {
        throw new RuntimeException('Expected malformed stray destination operands to stay out of review metadata.');
    }
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'catalog /Names /Dests indirect string alias values are not reinterpreted as missing destination keys',
    'destination_names' => $destinationNames,
    'metadata_names' => $documentDestinations['names'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => $promotedLinkObjects,
    'resolved_link_targets' => $resolvedLinkTargets,
    'stray_operand_promoted' => in_array(9, $promotedLinkObjects, true),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Actual Target')
        && !str_contains($plainText, 'Indirect Alias')
        && !str_contains($plainText, 'Direct Alias')
        && !str_contains($plainText, 'StrayOperand'),
];

echo '<!-- markerpdf-pdf-named-destination-indirect-string-alias-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $itemMetadata = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($itemMetadata, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
