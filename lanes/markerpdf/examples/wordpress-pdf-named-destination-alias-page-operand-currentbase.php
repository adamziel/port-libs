<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Bad dest Bad action Real target Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Alias page operand target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 11 0 R /Last 12 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 140 718] /Dest (Alias Page Operand) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [150 700 226 718] /A << /S /GoTo /D (Alias Page Operand) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [236 700 316 718] /Dest (Real Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [326 700 390 718] /A << /S /URI /URI (https://example.com/named-destination-alias-page-operand) >> >>\nendobj\n"
    . "11 0 obj\n<< /Title (Bad Alias Page Operand Chapter) /Parent 5 0 R /Dest (Alias Page Operand) /Next 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /Title (Real Target Chapter) /Parent 5 0 R /Prev 11 0 R /Dest (Real Target) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Real Target) [4 0 R /XYZ 72 640 0] (Alias Page Operand) [/Real#20Target /FitH 111]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 390.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'spans' => [
                ['text' => 'Bad dest', 'bbox' => [72.0, 700.0, 140.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Bad action', 'bbox' => [150.0, 700.0, 226.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Real target', 'bbox' => [236.0, 700.0, 316.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [326.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $links, $linkedPages], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (array_column($destinations, 'name') !== ['Real Target']
    || ($metadata['document_destinations']['names'] ?? null) !== ['Real Target']
    || ($metadata['document_destinations']['unresolved_count'] ?? null) !== 1
) {
    throw new RuntimeException('Expected alias-page-operand destination to stay unresolved before WordPress review metadata.');
}
if (count($links) !== 1 || array_column($links[0]['links'], 'annotation_object') !== [9, 10]) {
    throw new RuntimeException('Expected only the valid named destination and safe URI annotations to be promoted.');
}
if (isset($spans[0]['link_destination'])
    || isset($spans[1]['link_destination'])
    || ($spans[2]['link_destination'] ?? null) !== 'Real Target'
    || ($spans[3]['link_uri'] ?? null) !== 'https://example.com/named-destination-alias-page-operand'
) {
    throw new RuntimeException('Expected malformed alias page operands to stay off WordPress link spans.');
}
foreach (['Alias Page Operand', 'Bad Alias Page Operand Chapter', 'FitH', '111'] as $reviewOnly) {
    if (str_contains($encodedReview, $reviewOnly) || str_contains($plainText, $reviewOnly)) {
        throw new RuntimeException('Expected alias page operands to stay out of promoted review rows and visible text.');
    }
}
if (!str_contains($plainText, 'Bad dest Bad action Real target Safe URI')
    || !str_contains($plainText, 'Alias page operand target body')
) {
    throw new RuntimeException('Expected searchable PDF text to remain importable.');
}

echo '<!-- markerpdf-pdf-named-destination-alias-page-operand-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-named-destination-alias-page-operand-currentbase',
    'support_component' => 'native-pdf-named-destination-parser',
    'native_boundary' => 'explicit destination arrays must use a page object or page index as operand 0; named-destination aliases are rejected before WordPress link promotion',
    'destination_names' => array_column($destinations, 'name'),
    'metadata_unresolved_destination_count' => $metadata['document_destinations']['unresolved_count'] ?? null,
    'promoted_annotation_objects' => array_column($links[0]['links'], 'annotation_object'),
    'bad_dest_annotation_unpromoted' => !isset($spans[0]['link_destination']),
    'bad_goto_action_unpromoted' => !isset($spans[1]['link_destination']),
    'real_destination_promoted' => ($spans[2]['link_destination'] ?? null) === 'Real Target',
    'safe_uri_preserved' => ($spans[3]['link_uri'] ?? null) === 'https://example.com/named-destination-alias-page-operand',
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Alias Page Operand')
        && !str_contains($plainText, 'FitH'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
