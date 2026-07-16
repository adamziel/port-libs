<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Current tree jump Stale tree jump Legacy jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Direct Names duplicate Dests target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /#44ests 20 0 R /Dests 21 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 168 718] /Dest (Current Tree) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [178 700 264 718] /Dest (Stale Tree) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [274 700 348 718] /Dest /LegacyOk >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [358 700 430 718] /A << /S /URI /URI (https://example.com/direct-names-duplicate-dests) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Current Tree) (Current Tree)] /Names [(Current Tree) [4 0 R /FitH 700]] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(Stale Tree) (Stale Tree)] /Names [(Stale Tree) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 430.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'spans' => [
                ['text' => 'Current tree jump', 'bbox' => [72.0, 700.0, 168.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale tree jump', 'bbox' => [178.0, 700.0, 264.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [274.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [358.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$documentDestinationNames = $metadata['document_destinations']['names'] ?? [];
$promotedLinkObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== ['LegacyOk']
    || $documentDestinationNames !== ['LegacyOk']
    || array_column($destinations, 'source') !== ['legacy-dests']
    || ($destinations[0]['page'] ?? null) !== 1
    || ($destinations[0]['fit'] ?? null) !== 'FitV'
    || $promotedLinkObjects !== [9, 10]
    || ($links[0]['links'][0]['destination'] ?? null) !== 'LegacyOk'
    || ($links[0]['links'][0]['destination_page'] ?? null) !== 1
    || $blocks[0]['text'] !== 'Current tree jump Stale tree jump Legacy jump [Safe URI](https://example.com/direct-names-duplicate-dests)'
    || str_contains($encodedReview, 'Current Tree')
    || str_contains($encodedReview, 'Stale Tree')
    || str_contains($encodedReview, 'FitH')
    || str_contains($encodedReview, 'XYZ')
    || str_contains($plainText, 'Current Tree')
    || str_contains($plainText, 'Stale Tree')
    || str_contains($plainText, 'direct-names-duplicate-dests')
) {
    throw new RuntimeException('Expected direct duplicate catalog /Names /Dests keys to fail closed before WordPress destination import.');
}

echo '<!-- markerpdf-pdf-named-destination-direct-names-duplicate-dests-currentbase '
    . htmlspecialchars(json_encode([
        'support_component' => 'native-pdf-named-destination-parser',
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'direct inline catalog /Names dictionaries with duplicate decoded /Dests keys fail closed before standalone destination metadata',
        'destination_names' => $destinationNames,
        'document_destination_names' => $documentDestinationNames,
        'promoted_link_objects' => $promotedLinkObjects,
        'direct_duplicate_name_tree_hidden' => !str_contains($encodedReview, 'Current Tree')
            && !str_contains($encodedReview, 'Stale Tree'),
        'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Current Tree')
            && !str_contains($plainText, 'Stale Tree'),
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    echo '<li data-marker-destination-source="' . htmlspecialchars($destination['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-page="' . htmlspecialchars((string) (($destination['page'] ?? -1) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-fit="' . htmlspecialchars($destination['fit'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
