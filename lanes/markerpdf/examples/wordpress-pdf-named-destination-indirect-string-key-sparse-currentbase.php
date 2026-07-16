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

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Recovered key jump Missing key jump Alias jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Recovered indirect string key target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Dest (Recovered Indirect Key) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [200 700 312 718] /Dest (Missing Before Key) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [322 700 404 718] /Dest (Indirect Alias) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [414 700 486 718] /A << /S /URI /URI (https://example.com/indirect-string-key-sparse) >> >>\nendobj\n"
    . "12 0 obj\n(Actual Target)\nendobj\n"
    . "21 0 obj\n(Recovered Indirect Key)\nendobj\n"
    . "20 0 obj\n<< /Limits [(Actual Target) (Recovered Indirect Key)] /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (Indirect Alias) 12 0 R (Missing Before Key) 21 0 R [4 0 R /FitH 620]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 54 0 R /Count 4 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Actual Target Outline) /Parent 50 0 R /Dest (Actual Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Recovered Key Outline) /Parent 50 0 R /Dest (Recovered Indirect Key) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Missing Key Outline) /Parent 50 0 R /Dest (Missing Before Key) /Prev 52 0 R /Next 54 0 R >>\nendobj\n"
    . "54 0 obj\n<< /Title (Alias Outline) /Parent 50 0 R /Dest (Indirect Alias) /Prev 53 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 486.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 486.0, 718.0],
            'spans' => [
                ['text' => 'Recovered key jump', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Missing key jump', 'bbox' => [200.0, 700.0, 312.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Alias jump', 'bbox' => [322.0, 700.0, 404.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [414.0, 700.0, 486.0, 718.0], 'font' => 'Helvetica'],
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

$expectedNames = ['Actual Target', 'Indirect Alias', 'Recovered Indirect Key', 'LegacyTail'];
$destinationNames = array_column($destinations, 'name');
$documentDestinations = is_array($metadata['document_destinations'] ?? null)
    ? $metadata['document_destinations']
    : [];
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$encodedReview = json_encode([$destinations, $documentDestinations, $toc, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== $expectedNames) {
    throw new RuntimeException('Expected indirect string key recovery in named-destination review.');
}
if (($documentDestinations['names'] ?? null) !== $expectedNames) {
    throw new RuntimeException('Expected indirect string key recovery in document destination metadata.');
}
if (array_column($toc, 'title') !== ['Actual Target Outline', 'Recovered Key Outline', 'Alias Outline']) {
    throw new RuntimeException('Expected outline rows to resolve the recovered indirect key while excluding the missing-value key.');
}
if ($promotedLinkObjects !== [7, 9, 10]) {
    throw new RuntimeException('Expected recovered-key, alias, and URI links to promote while the missing-value key stays hidden.');
}
if (($blocks[0]['text'] ?? '') !== 'Recovered key jump Missing key jump Alias jump [Safe URI](https://example.com/indirect-string-key-sparse)') {
    throw new RuntimeException('Expected WordPress span merge to keep visible link text stable.');
}
foreach (['Actual Target', 'Indirect Alias', 'Recovered Indirect Key', 'Missing Before Key', 'indirect-string-key-sparse'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected destination metadata and URI text to remain out of visible PDF text.');
    }
}
foreach (['Missing Before Key', 'Missing Key Outline'] as $hidden) {
    if (str_contains($encodedReview, $hidden)) {
        throw new RuntimeException('Expected missing-value destination keys to stay out of review metadata.');
    }
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'catalog /Names /Dests sparse arrays recover indirect string keys before explicit destination values',
    'destination_names' => $destinationNames,
    'metadata_names' => $documentDestinations['names'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'promoted_link_objects' => $promotedLinkObjects,
    'missing_key_promoted' => in_array(8, $promotedLinkObjects, true),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Recovered Indirect Key')
        && !str_contains($plainText, 'Missing Before Key'),
];

echo '<!-- markerpdf-pdf-named-destination-indirect-string-key-sparse-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n<p>"
        . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</p>\n<!-- /wp:paragraph -->\n";
}
