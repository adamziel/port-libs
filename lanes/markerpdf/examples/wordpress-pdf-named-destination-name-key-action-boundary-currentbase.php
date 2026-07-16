<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current jump Malformed name jump Legacy jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Name-tree key destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyNameKey [4 0 R /FitV 130] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Dest (Current String Key) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 294 718] /Dest /NameObjectStale >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [304 700 394 718] /Dest /LegacyNameKey >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [404 700 468 718] /A << /S /URI /URI (https://example.com/name-tree-key-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Current String Key) (Review Summary)] /Names [(Current String Key) [4 0 R /FitH 700] /NameObjectStale [4 0 R /FitH 111] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 468.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 468.0, 718.0],
            'spans' => [
                ['text' => 'Current jump', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Malformed name jump', 'bbox' => [168.0, 700.0, 294.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [304.0, 700.0, 394.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [404.0, 700.0, 468.0, 718.0], 'font' => 'Helvetica'],
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
$names = array_column($destinations, 'name');
$metadataNames = $metadata['document_destinations']['names'] ?? [];
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$encodedReview = json_encode([$destinations, $metadata, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($names !== ['Current String Key', 'Review Summary', 'LegacyNameKey']) {
    throw new RuntimeException('Expected malformed name-tree PDF-name key to stay out of named destinations.');
}
if ($metadataNames !== ['Current String Key', 'Review Summary', 'LegacyNameKey']) {
    throw new RuntimeException('Expected malformed name-tree PDF-name key to stay out of document destination metadata.');
}
if ($promotedLinkObjects !== [7, 9, 10]) {
    throw new RuntimeException('Expected valid string and legacy destinations plus the safe URI to be promoted.');
}
if (str_contains($encodedReview, 'NameObjectStale') || str_contains($encodedReview, 'FitH 111')) {
    throw new RuntimeException('Expected malformed name-tree key/action payload to remain review-excluded.');
}
if (!str_contains($plainText, 'Current jump Malformed name jump Legacy jump Safe URI')) {
    throw new RuntimeException('Expected visible searchable text to survive native destination review.');
}

echo '<!-- markerpdf-pdf-named-destination-name-key-action-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'catalog Names Dests name-tree keys must be PDF strings; legacy Dests dictionary name keys remain valid',
    'destination_names' => $names,
    'metadata_destination_names' => $metadataNames,
    'promoted_link_objects' => $promotedLinkObjects,
    'malformed_name_key_promoted' => in_array(8, $promotedLinkObjects, true),
    'legacy_name_key_promoted' => in_array(9, $promotedLinkObjects, true),
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Current String Key')
        && !str_contains($plainText, 'Review Summary')
        && !str_contains($plainText, 'LegacyNameKey'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
}
