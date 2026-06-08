<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Current leaf-limit jump Decoy leaf-limit jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Leaf-limit destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 216 718] /Dest (Review Live) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [226 700 374 718] /Dest (Zulu Decoy) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [384 700 456 718] /A << /S /URI /URI (https://example.com/leaf-limit-safe-uri) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alpha Live) (Review Live)] /Kids [21 0 R 23 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(Alpha Live) (Alpha Live)] /Names [(Alpha Live) [3 0 R /FitH 710]] >>\nendobj\n"
    . "23 0 obj\n<< /Limits [(Zulu Decoy) (Zzz Decoy)] /Names [(Review Live) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Review Live Outline) /Parent 50 0 R /Dest (Review Live) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Zulu Decoy Outline) /Parent 50 0 R /Dest (Zulu Decoy) /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages([[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 456.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 456.0, 718.0],
            'spans' => [
                ['text' => 'Current leaf-limit jump', 'bbox' => [72.0, 700.0, 216.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Decoy leaf-limit jump', 'bbox' => [226.0, 700.0, 374.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [384.0, 700.0, 456.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]], $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$metadataNames = $metadata['document_destinations']['names'] ?? [];
$annotationObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$expectedNames = ['Alpha Live', 'LegacyTail'];

if ($destinationNames !== $expectedNames || $metadataNames !== $expectedNames) {
    throw new RuntimeException('Expected disjoint leaf Limits destinations to stay out of WordPress destination metadata.');
}
if ($annotationObjects !== [9] || isset($spans[0]['link_destination']) || isset($spans[1]['link_destination'])) {
    throw new RuntimeException('Expected disjoint leaf-limit named destinations to remain unresolved before WordPress span promotion.');
}
foreach (['Alpha Live', 'Review Live', 'Zulu Decoy', 'Review Live Outline', 'leaf-limit-safe-uri'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected destination metadata and URI operands to stay out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-leaf-disjoint-limits-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'disjoint_leaf_destination_pruned' => !in_array('Review Live', $destinationNames, true),
    'metadata_order_matches_review_order' => $metadataNames === $destinationNames,
    'decoy_named_links_unresolved' => !isset($spans[0]['link_destination']) && !isset($spans[1]['link_destination']),
    'uri_link_preserved' => ($spans[2]['link_uri'] ?? null) === 'https://example.com/leaf-limit-safe-uri',
    'visible_text_excludes_destination_labels' => true,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

$text = $blocks[0]['text'] ?? '';
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
