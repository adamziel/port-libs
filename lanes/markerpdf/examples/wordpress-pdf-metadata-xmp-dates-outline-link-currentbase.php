<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Link XMP Review Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T20:27:13-04:00</xmp:CreateDate>'
    . '<xmp:ModifyDate>2026-06-02T21:28:14+02:00</xmp:ModifyDate>'
    . '<xmp:MetadataDate>2026-06-02T22:29:15Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP link smoke stream.');
}

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Jump to chapter) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Chapter target body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageLabels 10 0 R /Metadata 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R] /Contents 11 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 12 0 R /AA << /O 13 0 R >> /Contents 14 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Chapter One Outline) /Parent 5 0 R /Dest /chapter-one >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 180 718] /Dest /chapter-one /AA << /E 15 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Names [(chapter-one) [4 0 R /FitH 700]] >>\nendobj\n"
    . "10 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Chapter ) /St 4 >>] >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /Wipe /D .75 /Dm /H /M /O /Di 180 >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/chapter-open-review) >>\nendobj\n"
    . "14 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "15 0 obj\n<< /S /JavaScript /JS (hoverImportReview\\(\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 180.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 180.0, 718.0],
            'spans' => [[
                'text' => 'Jump to chapter',
                'bbox' => [72.0, 700.0, 180.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkPages = $extractor->extractPageLinks($pdf);
$link = $linkPages[0]['links'][0] ?? [];
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (($link['destination_page_label'] ?? null) !== 'Chapter 4') {
    throw new RuntimeException('Expected link destination page label context.');
}
if (($link['target_outline_titles'] ?? []) !== ['Chapter One Outline']) {
    throw new RuntimeException('Expected link to inherit matching outline title context.');
}
if (($link['target_page_transition']['style'] ?? null) !== 'Wipe') {
    throw new RuntimeException('Expected link target page transition metadata.');
}
if (($link['document_metadata_dates']['created_at_utc'] ?? null) !== '2026-06-03T00:27:13Z') {
    throw new RuntimeException('Expected XMP create date UTC normalization on link metadata.');
}
if (($span['link_destination_page_label'] ?? null) !== 'Chapter 4') {
    throw new RuntimeException('Expected supplied span link destination label context.');
}
if (str_contains($plainText, 'Link XMP Review Title') || str_contains($plainText, 'chapter-open-review') || str_contains($plainText, 'hoverImportReview')) {
    throw new RuntimeException('Expected XMP and action operands to stay out of visible text.');
}

echo '<!-- markerpdf-metadata-xmp-dates-outline-link-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-link-target-context-review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'local PDF link annotations inherit destination page label, matching outline title, target page transition/actions, and XMP date metadata as review-only fields',
    'link_count' => count($linkPages[0]['links'] ?? []),
    'link_destination' => $link['destination'] ?? null,
    'destination_page_label' => $link['destination_page_label'] ?? null,
    'target_outline_titles' => $link['target_outline_titles'] ?? [],
    'target_transition_style' => $link['target_page_transition']['style'] ?? null,
    'target_page_action_safeties' => array_column($link['target_page_actions'] ?? [], 'safety'),
    'document_created_at_utc' => $link['document_metadata_dates']['created_at_utc'] ?? null,
    'document_modified_at_utc' => $link['document_metadata_dates']['modified_at_utc'] ?? null,
    'document_metadata_date_utc' => $link['document_metadata_dates']['metadata_date_utc'] ?? null,
    'visible_text_excludes_xmp_and_actions' => !str_contains($plainText, 'Link XMP Review Title')
        && !str_contains($plainText, 'chapter-open-review')
        && !str_contains($plainText, 'hoverImportReview'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p data-marker-link-destination="'
        . htmlspecialchars((string) ($span['link_destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-link-page="'
        . htmlspecialchars((string) ($span['link_destination_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-link-outline="'
        . htmlspecialchars(implode(', ', $span['link_target_outline_titles'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="false">'
        . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars('Chapter target body', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
