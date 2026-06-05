<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Child destination jump Inline parent jump External URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Internal leaf destination target body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 194 718] /Dest (Child Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [204 700 326 718] /Dest (Inline Parent Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [336 700 424 718] /A << /S /URI /URI (https://example.com/leaf-boundary) >> >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Limits [(Child Target) (Review Summary)] /Kids [21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(Child Target) (Review Summary)] /Names [(Inline Parent Target) [4 0 R /FitH 111]] /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [(Child Target) (Review Summary)] /Names [(Child Target) [4 0 R /FitH 700] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Child Target Outline) /Parent 50 0 R /Dest (Child Target) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Inline Parent Outline) /Parent 50 0 R /Dest (Inline Parent Target) /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 424.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 424.0, 718.0],
            'spans' => [
                ['text' => 'Child destination jump', 'bbox' => [72.0, 700.0, 194.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Inline parent jump', 'bbox' => [204.0, 700.0, 326.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' External URI', 'bbox' => [336.0, 700.0, 424.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

$destinationNames = array_column($destinations, 'name');
$metadataDestinationNames = $metadata['document_destinations']['names'] ?? [];
$tocTitles = array_column($toc, 'title');
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

foreach (['Child Target', 'Review Summary', 'LegacyOnly'] as $expected) {
    if (!in_array($expected, $destinationNames, true) || !in_array($expected, $metadataDestinationNames, true)) {
        throw new RuntimeException("Expected {$expected} in child-leaf named-destination review metadata.");
    }
}
if (str_contains($encodedReview, 'Inline Parent Target')) {
    throw new RuntimeException('Internal-node local destination names must stay out of WordPress review metadata.');
}
if (($spans[1]['link_destination'] ?? null) !== null || !isset($spans[2]['link_uri'])) {
    throw new RuntimeException('Expected stale internal-node destination link to stay unpromoted while safe URI remains linked.');
}

echo '<!-- markerpdf-pdf-named-destination-internal-leaf-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF destination name-tree nodes with /Kids are internal nodes; only child leaf /Names entries are promoted into WordPress review metadata',
    'destination_names' => $destinationNames,
    'metadata_destination_names' => $metadataDestinationNames,
    'toc_titles' => $tocTitles,
    'child_destination_linked' => ($spans[0]['link_destination'] ?? null) === 'Child Target'
        && ($spans[0]['link_destination_page'] ?? null) === 1,
    'internal_parent_destination_unpromoted' => !isset($spans[1]['link_destination'])
        && !str_contains($encodedReview, 'Inline Parent Target'),
    'safe_uri_link_preserved' => ($spans[2]['link_uri'] ?? null) === 'https://example.com/leaf-boundary',
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Child Target')
        && !str_contains($plainText, 'Inline Parent Target')
        && !str_contains($plainText, 'Review Summary'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($destination, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";

echo '<p>' . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
