<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Valid remote Invalid view Missing top Named remote) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Valid remote review) /A << /S /GoToR /F (valid-remote.pdf) /D [2 /FitH 720] /NewWindow true >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 260 718] /Contents (Invalid remote view review) /A << /S /GoToR /F (invalid-view.pdf) /D [4 /Launch 720] /NewWindow true >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Missing remote top review) /A << /S /GoToR /F (missing-top.pdf) /D [5 /FitH] /NewWindow false >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 470 718] /Contents (Named remote review) /A << /S /GoToR /F (named-remote.pdf) /D (Remote Appendix) /NewWindow false >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 470.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'spans' => [
                ['text' => 'Valid remote', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Invalid view', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Missing top', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Named remote', 'bbox' => [370.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationRows = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0]['annotations'] ?? [];
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$encodedPromoted = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($links[0]['links'] ?? [], 'annotation_object') !== [7, 10]) {
    throw new RuntimeException('Expected only valid remote GoToR destinations to reach WordPress link review spans.');
}
if (($blocks[0]['text'] ?? '') !== 'Valid remote Invalid view Missing top Named remote') {
    throw new RuntimeException('Remote GoToR review metadata must not alter imported paragraph text.');
}
if (str_contains($encodedPromoted, 'invalid-view.pdf') || str_contains($encodedPromoted, 'missing-top.pdf')) {
    throw new RuntimeException('Malformed remote GoToR destinations were promoted into WordPress span metadata.');
}
if (str_contains($visibleText, 'valid-remote.pdf') || str_contains($visibleText, 'Remote Appendix')) {
    throw new RuntimeException('Remote GoToR action operands leaked into visible PDF text.');
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-link-remote-gotor-view-boundary',
    'native_boundary' => 'remote GoToR page-number destination arrays must use valid PDF destination view names and required coordinates before WordPress span promotion',
    'annotation_objects' => array_column($annotationRows, 'annotation_object'),
    'annotation_action_safeties' => array_map(
        static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
        $annotationRows
    ),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_remote_files' => array_column($links[0]['links'] ?? [], 'file'),
    'invalid_view_remote_promoted' => str_contains($encodedPromoted, 'invalid-view.pdf'),
    'missing_coordinate_remote_promoted' => str_contains($encodedPromoted, 'missing-top.pdf'),
    'valid_remote_review_span' => ($spans[0]['link_remote_file'] ?? null) === 'valid-remote.pdf'
        && ($spans[0]['link_remote_view_mode'] ?? null) === 'FitH',
    'named_remote_review_span' => ($spans[3]['link_remote_file'] ?? null) === 'named-remote.pdf'
        && ($spans[3]['link_remote_destination'] ?? null) === 'Remote Appendix',
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'visible_text_excludes_remote_operands' => !str_contains($visibleText, 'valid-remote.pdf')
        && !str_contains($visibleText, 'invalid-view.pdf')
        && !str_contains($visibleText, 'missing-top.pdf')
        && !str_contains($visibleText, 'Remote Appendix'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-link-remote-gotor-view-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
