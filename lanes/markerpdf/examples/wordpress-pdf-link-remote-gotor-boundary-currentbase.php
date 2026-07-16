<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Remote appendix Local fallback Hidden remote) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Local fallback target) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 14 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Contents (Remote appendix review) /A 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /S /GoToR /F 20 0 R /D [3 /FitH 720] /NewWindow true /Next [9 0 R 12 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D (local-fallback) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [300 700 400 718] /F 2 /Contents (Hidden remote review) /A << /S /GoToR /F (hidden-remote.pdf) /D (hidden-target) >> >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (remoteLinkDownReview\\(\\)) >>\nendobj\n"
    . "14 0 obj\n<< /Names [(local-fallback) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (fallback-appendix.pdf) /UF <FEFF00720065006D006F00740065002D0061007000700065006E006400690078002E007000640066> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 400.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 400.0, 718.0],
            'spans' => [
                ['text' => 'Remote appendix', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Local fallback', 'bbox' => [200.0, 700.0, 290.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden remote', 'bbox' => [300.0, 700.0, 400.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-link-remote-gotor-boundary',
    'native_boundary' => 'PDF Link /S /GoToR action targets remain remote-document review metadata before WordPress import',
    'link_count' => count($links),
    'primary_action_type' => $links[0]['action_type'] ?? null,
    'primary_safety' => $links[0]['safety'] ?? null,
    'remote_file' => $spans[0]['link_remote_file'] ?? null,
    'remote_destination_page' => $spans[0]['link_remote_destination_page'] ?? null,
    'remote_view_mode' => $spans[0]['link_remote_view_mode'] ?? null,
    'remote_new_window' => $spans[0]['link_remote_new_window'] ?? null,
    'local_destination_page_promoted' => isset($spans[0]['link_destination_page']),
    'next_action_safety' => array_column($spans[0]['link_actions_review'] ?? [], 'safety'),
    'next_local_destination' => $spans[0]['link_actions_review'][1]['destination'] ?? null,
    'hidden_remote_promoted' => isset($spans[2]['link_remote_file']),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_remote_operands' => !str_contains($visibleText, 'remote-appendix.pdf')
        && !str_contains($visibleText, 'fallback-appendix.pdf')
        && !str_contains($visibleText, 'hidden-remote.pdf')
        && !str_contains($visibleText, 'remoteLinkDownReview'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-remote-gotor-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_remote_file'])) {
        echo '<span data-markerpdf-link-action="GoToR"'
            . ' data-markerpdf-remote-file="' . htmlspecialchars((string) $span['link_remote_file'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-markerpdf-remote-page="' . (int) ($span['link_remote_destination_page'] ?? -1) . '"'
            . ' data-markerpdf-remote-view="' . htmlspecialchars((string) ($span['link_remote_view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-markerpdf-review-only="true">' . $text . '</span>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
