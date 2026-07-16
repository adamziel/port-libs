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

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current jump Recovered jump Stray jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Recovered named destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTarget [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Dest (Current Start) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 272 718] /Dest (Recovered Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [282 700 348 718] /Dest /StrayName >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [358 700 430 718] /A << /S /URI /URI (https://example.com/recovered-named-destination) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Current Start) (Recovered Target)] /Names [(Current Start) [4 0 R /FitH 700] /StrayName (Recovered Target) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Current Start Outline) /Parent 50 0 R /Dest (Current Start) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Recovered Target Outline) /Parent 50 0 R /Dest (Recovered Target) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Stray Name Outline) /Parent 50 0 R /Dest /StrayName /Prev 52 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 430.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'spans' => [
                ['text' => 'Current jump', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Recovered jump', 'bbox' => [168.0, 700.0, 272.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stray jump', 'bbox' => [282.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [358.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
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
$metadataNames = $metadata['document_destinations']['names'] ?? [];
$tocTitles = array_column($toc, 'title');
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$documentDestinations = is_array($metadata['document_destinations'] ?? null)
    ? $metadata['document_destinations']
    : [];
$encodedReview = json_encode([$destinations, $documentDestinations, $toc, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== ['Current Start', 'Recovered Target', 'LegacyTarget']) {
    throw new RuntimeException('Expected sparse name-tree recovery to preserve the following string-key destination pair.');
}
if ($metadataNames !== ['Current Start', 'Recovered Target', 'LegacyTarget']) {
    throw new RuntimeException('Expected sparse name-tree recovery in document destination metadata.');
}
if ($tocTitles !== ['Current Start Outline', 'Recovered Target Outline']) {
    throw new RuntimeException('Expected recovered named destination to remain available for outline navigation review.');
}
if ($promotedLinkObjects !== [7, 8, 10]) {
    throw new RuntimeException('Expected current, recovered, and URI links to be promoted while the stray name is ignored.');
}
if (str_contains($encodedReview, 'StrayName') || str_contains($encodedReview, 'Stray Name Outline')) {
    throw new RuntimeException('Expected the stray name-tree token to remain out of review metadata.');
}
if (!str_contains($plainText, 'Current jump Recovered jump Stray jump Safe URI')
    || !str_contains($plainText, 'Recovered named destination target body')
) {
    throw new RuntimeException('Expected searchable PDF text to remain visible after native destination review.');
}

echo '<!-- markerpdf-pdf-named-destination-sparse-name-array-currentbase '
    . htmlspecialchars(json_encode([
        'support_component' => 'native-pdf-named-destination-parser',
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'executes_pdf_actions' => false,
        'native_boundary' => 'catalog /Names /Dests sparse arrays recover following PDF string-key pairs while invalid name tokens stay review-excluded',
        'destination_names' => $destinationNames,
        'metadata_destination_names' => $metadataNames,
        'outline_titles' => $tocTitles,
        'promoted_link_objects' => $promotedLinkObjects,
        'stray_name_promoted' => str_contains($encodedReview, 'StrayName'),
        'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Recovered Target')
            && !str_contains($plainText, 'StrayName'),
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n<p>"
        . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</p>\n<!-- /wp:paragraph -->\n";
}
