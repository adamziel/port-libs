<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Clean jump Stream value jump Legacy jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Stream value boundary target body) Tj ET';
$hiddenPayload = 'BT /F1 12 Tf 72 720 Td (hidden stream destination value payload) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 144] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Dest (Clean Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 265 718] /Dest (Stream Value Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [275 700 350 718] /Dest /LegacyOk >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 430 718] /A << /S /URI /URI (https://example.com/named-destination-stream-value-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Clean Target) (Stream Value Target)] /Names [(Clean Target) [4 0 R /FitH 700] (Stream Value Target) 21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /D [4 0 R /XYZ 72 640 0] /Length " . strlen($hiddenPayload) . " >>\nstream\n{$hiddenPayload}\nendstream\nendobj\n"
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
                ['text' => 'Clean jump', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stream value jump', 'bbox' => [160.0, 700.0, 265.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [275.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [360.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedReview = json_encode([$destinations, $documentDestinations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($destinations, 'name') !== ['Clean Target', 'LegacyOk']
    || ($documentDestinations['names'] ?? []) !== ['Clean Target', 'LegacyOk']
    || array_column($links[0]['links'] ?? [], 'annotation_object') !== [7, 9, 10]
    || ($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_destination'] ?? null) !== null
    || str_contains($encodedReview, 'Stream Value Target')
    || str_contains($encodedReview, 'hidden stream destination value payload')
    || str_contains($plainText, 'Stream Value Target')
    || str_contains($plainText, 'hidden stream destination value payload')
) {
    throw new RuntimeException('Expected stream-carrier named-destination values to stay out of WordPress navigation review.');
}

echo '<!-- markerpdf-pdf-named-destination-stream-value-boundary-currentbase '
    . htmlspecialchars(json_encode([
        'support_component' => 'native-pdf-named-destination-parser',
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'referenced stream-carrier named-destination values are rejected before WordPress navigation review',
        'destination_names' => array_column($destinations, 'name'),
        'document_destination_names' => $documentDestinations['names'] ?? [],
        'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
        'stream_carrier_destination_value_rejected' => !str_contains($encodedReview, 'Stream Value Target'),
        'visible_text_excludes_stream_payload' => !str_contains($plainText, 'hidden stream destination value payload'),
        'executes_pdf_actions' => false,
        'wordpress_text' => $blocks[0]['text'] ?? '',
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
