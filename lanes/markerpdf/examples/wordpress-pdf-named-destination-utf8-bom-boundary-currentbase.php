<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Resume jump Zurich jump Malformed jump Legacy jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (UTF8 BOM named destination target body) Tj ET';
$resumeHex = 'EFBBBF52C3A973756DC3A9205374617274';
$zurichHex = 'EFBBBF5AC3BC7269636820526576696577';
$malformedHex = 'EFBBBF53FF74616C65204B6579';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 152 718] /Dest <{$resumeHex}> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [162 700 238 718] /Dest <{$zurichHex}> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 348 718] /Dest <{$malformedHex}> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [358 700 430 718] /Dest /LegacyOk >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [440 700 510 718] /A << /S /URI /URI (https://example.com/utf8-bom-destination) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [<{$resumeHex}> <{$zurichHex}>] /Names [<{$resumeHex}> [4 0 R /FitH 700] <{$malformedHex}> [4 0 R /FitH 111] <{$zurichHex}> 21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Resume Outline) /Parent 50 0 R /Dest <{$resumeHex}> /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Zurich Outline) /Parent 50 0 R /Dest <{$zurichHex}> /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Malformed Outline) /Parent 50 0 R /Dest <{$malformedHex}> /Prev 52 0 R >>\nendobj\n"
    . "%%EOF\n";

$resume = "R\u{00e9}sum\u{00e9} Start";
$zurich = "Z\u{00fc}rich Review";
$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 510.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'spans' => [
                ['text' => 'Resume jump', 'bbox' => [72.0, 700.0, 152.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Zurich jump', 'bbox' => [162.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Malformed jump', 'bbox' => [248.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Legacy jump', 'bbox' => [358.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [440.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc, $links], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($destinations, 'name') !== [$resume, $zurich, 'LegacyOk']) {
    throw new RuntimeException('Expected UTF-8 BOM destination names to decode before review metadata.');
}
if (($metadata['document_destinations']['names'] ?? null) !== [$resume, $zurich, 'LegacyOk']) {
    throw new RuntimeException('Expected UTF-8 BOM destination names in document metadata.');
}
if (array_column($toc, 'destination') !== [$resume, $zurich]) {
    throw new RuntimeException('Expected outline review to resolve UTF-8 BOM destination names.');
}
if (array_column($links[0]['links'] ?? [], 'annotation_object') !== [7, 8, 10, 11]) {
    throw new RuntimeException('Expected malformed UTF-8 BOM destination link to be excluded.');
}
if (($blocks[0]['text'] ?? '') !== 'Resume jump Zurich jump Malformed jump Legacy jump [Safe URI](https://example.com/utf8-bom-destination)') {
    throw new RuntimeException('Expected WordPress markdown link promotion to keep malformed destination unlinked.');
}
if (str_contains($encodedReview, "\u{00ef}\u{00bb}\u{00bf}") || str_contains($encodedReview, 'Stale Key') || str_contains($encodedReview, 'Malformed Outline')) {
    throw new RuntimeException('Expected BOM bytes and malformed destination metadata to stay out of review output.');
}
foreach ([$resume, $zurich, 'LegacyOk', 'utf8-bom-destination'] as $reviewOnly) {
    if (str_contains($plainText, $reviewOnly)) {
        throw new RuntimeException('Expected destination review metadata to stay out of visible WordPress text.');
    }
}

$payload = [
    'scenario' => 'wordpress-pdf-named-destination-utf8-bom-boundary-currentbase',
    'support_component' => 'native-pdf-named-destination-utf8-bom-string-decoder',
    'native_boundary' => 'PDF 2.0 UTF-8 BOM text strings in /Names /Dests and /Dest action operands decode before review metadata',
    'destination_names' => array_column($destinations, 'name'),
    'outline_destinations' => array_column($toc, 'destination'),
    'link_annotation_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'malformed_utf8_bom_destination_excluded' => !str_contains($encodedReview, 'Stale Key'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, $resume)
        && !str_contains($plainText, $zurich)
        && !str_contains($plainText, 'LegacyOk'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-named-destination-utf8-bom-boundary-currentbase '
    . htmlspecialchars(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_NOQUOTES)
    . " -->\n";
