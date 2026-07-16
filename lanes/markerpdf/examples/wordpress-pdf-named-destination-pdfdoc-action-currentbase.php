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

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Breve jump Bullet jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (PDFDoc encoded destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 148 718] /Dest <18> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [158 700 242 718] /A << /S /GoTo /D <80> >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [252 700 316 718] /A << /S /URI /URI (https://example.com/pdfdoc-destination) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [<18> <80>] /Names [<18> [4 0 R /FitH 700] <80> [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Breve Outline) /Parent 50 0 R /Dest <18> /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Bullet Outline) /Parent 50 0 R /Dest <80> /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 316.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 316.0, 718.0],
            'spans' => [
                ['text' => 'Breve jump', 'bbox' => [72.0, 700.0, 148.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Bullet jump', 'bbox' => [158.0, 700.0, 242.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [252.0, 700.0, 316.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$breve = "\u{02d8}";
$bullet = "\u{2022}";
$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$annotationDestinations = array_map(
    static fn (array $annotation): ?string => $annotation['actions'][0]['destination'] ?? null,
    $annotations[0]['annotations'] ?? []
);
$linkDestinations = array_map(
    static fn (array $link): ?string => $link['destination'] ?? null,
    $links[0]['links'] ?? []
);
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc, $annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (array_column($destinations, 'name') !== [$breve, $bullet]) {
    throw new RuntimeException('Expected PDFDocEncoding destination names to decode in standalone metadata.');
}
if ($annotationDestinations !== [$breve, $bullet, null]) {
    throw new RuntimeException('Expected annotation action review to decode PDFDocEncoding destination names.');
}
if ($linkDestinations !== [$breve, $bullet, null]) {
    throw new RuntimeException('Expected promoted link review to decode PDFDocEncoding destination names.');
}
if (str_contains($encodedReview, "\x18") || str_contains($encodedReview, "\x80")) {
    throw new RuntimeException('Expected raw PDFDocEncoding bytes to stay out of WordPress review metadata.');
}
if (!str_contains($plainText, 'Breve jump Bullet jump Safe URI')
    || !str_contains($plainText, 'PDFDoc encoded destination target body')
) {
    throw new RuntimeException('Expected searchable PDF page text to remain visible.');
}

echo '<!-- markerpdf-pdf-named-destination-pdfdoc-action-currentbase '
    . htmlspecialchars(json_encode([
        'support_component' => 'native-pdf-action-review-nametree-parser',
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'executes_pdf_actions' => false,
        'native_boundary' => 'PDFDocEncoding byte-string destination names are decoded for annotation action and link review while raw bytes remain hidden',
        'destination_names' => array_column($destinations, 'name'),
        'metadata_destination_names' => $metadata['document_destinations']['names'] ?? [],
        'outline_destinations' => array_column($toc, 'destination'),
        'annotation_destinations' => $annotationDestinations,
        'link_destinations' => $linkDestinations,
        'raw_control_destination_hidden' => !str_contains($encodedReview, "\x18"),
        'raw_high_byte_destination_hidden' => !str_contains($encodedReview, "\x80"),
        'visible_text_excludes_destination_labels' => !str_contains($plainText, $breve)
            && !str_contains($plainText, $bullet)
            && !str_contains($plainText, 'pdfdoc-destination'),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($blocks as $block) {
    $text = is_string($block['text'] ?? null) ? $block['text'] : '';
    if ($text === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
