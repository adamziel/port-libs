<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Current root jump Stale root jump Legacy root jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Direct root duplicate-key target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Limits [(Current Root) (Stale Root)] /Names [(Current Root) [4 0 R /FitH 700]] /#4eames [(Stale Root) [4 0 R /XYZ 72 640 0]] >> >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Dest (Current Root) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [180 700 268 718] /Dest (Stale Root) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [278 700 356 718] /Dest /LegacyOk >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [366 700 430 718] /A << /S /URI /URI (https://example.com/direct-root-duplicate-key) >> >>\nendobj\n"
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
                ['text' => 'Current root jump', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale root jump', 'bbox' => [180.0, 700.0, 268.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy root jump', 'bbox' => [278.0, 700.0, 356.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [366.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
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
    || $blocks[0]['text'] !== 'Current root jump Stale root jump Legacy root jump [Safe URI](https://example.com/direct-root-duplicate-key)'
    || str_contains($encodedReview, 'Current Root')
    || str_contains($encodedReview, 'Stale Root')
    || str_contains($encodedReview, 'FitH')
    || str_contains($encodedReview, 'XYZ')
    || str_contains($plainText, 'Current Root')
    || str_contains($plainText, 'Stale Root')
    || str_contains($plainText, 'direct-root-duplicate-key')
) {
    throw new RuntimeException('Expected duplicate direct destination root traversal keys to fail closed before WordPress destination import.');
}

echo '<!-- markerpdf-pdf-named-destination-direct-root-duplicate-key-currentbase '
    . htmlspecialchars(json_encode([
        'support_component' => 'native-pdf-named-destination-parser',
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'direct inline /Names /Dests root dictionaries with duplicate decoded traversal keys fail closed before standalone destination metadata',
        'destination_names' => $destinationNames,
        'document_destination_names' => $documentDestinationNames,
        'promoted_link_objects' => $promotedLinkObjects,
        'duplicate_direct_root_hidden' => !str_contains($encodedReview, 'Current Root')
            && !str_contains($encodedReview, 'Stale Root'),
        'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Current Root')
            && !str_contains($plainText, 'Stale Root'),
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
