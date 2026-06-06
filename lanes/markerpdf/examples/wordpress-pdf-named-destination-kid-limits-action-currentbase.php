<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate limits action jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Limits ordered destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 248 718] /Dest (DuplicateReview) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [258 700 326 718] /A << /S /URI /URI (https://example.com/kid-limits-action-safe-uri) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Kids [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Names [(A Broad) [3 0 R /Fit] (DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 326.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 326.0, 718.0],
            'spans' => [
                ['text' => 'Duplicate limits action jump', 'bbox' => [72.0, 700.0, 248.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [258.0, 700.0, 326.0, 718.0], 'font' => 'Helvetica'],
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
$documentDestinationPages = array_column($metadata['document_destinations']['destinations'] ?? [], 'page');
$promotedLinkPages = array_column($links[0]['links'] ?? [], 'destination_page');
$promotedLinkModes = array_column($links[0]['links'] ?? [], 'view_mode');
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if ($destinationNames !== ['A Broad', 'DuplicateReview']
    || ($destinations[1]['page'] ?? null) !== 1
    || ($destinations[1]['fit'] ?? null) !== 'XYZ'
    || $documentDestinationPages !== [0, 1]
    || ($promotedLinkPages[0] ?? null) !== 1
    || ($promotedLinkModes[0] ?? null) !== 'XYZ'
    || str_contains($encodedReview, 'FitH 111')
    || str_contains($plainText, 'DuplicateReview')
    || str_contains($plainText, 'kid-limits-action-safe-uri')
) {
    throw new RuntimeException('Expected action promotion to use logical /Limits-ordered named destinations before WordPress rendering.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-action-map',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'annotation action named-destination map orders /Kids by effective /Limits before duplicate key replacement',
    'destination_names' => $destinationNames,
    'document_destination_pages' => $documentDestinationPages,
    'promoted_link_pages' => $promotedLinkPages,
    'promoted_link_modes' => $promotedLinkModes,
    'stale_physical_child_hidden' => !str_contains($encodedReview, 'FitH 111'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'DuplicateReview'),
    'visible_text_excludes_uri_metadata' => !str_contains($plainText, 'kid-limits-action-safe-uri'),
];

echo '<!-- markerpdf-pdf-named-destination-kid-limits-action-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    echo '<li data-marker-destination-page="' . htmlspecialchars((string) (($destination['page'] ?? -1) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-fit="' . htmlspecialchars($destination['fit'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
