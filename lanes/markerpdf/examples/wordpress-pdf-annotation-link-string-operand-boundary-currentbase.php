<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean state Tainted state Indirect clean Indirect tainted) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Contents (Clean state review) /T (Clean title) /Subj (Clean subject) /NM (clean-name) /M (D:20260608172104Z) /A << /S /URI /URI (https://example.com/clean-state) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 266 718] /Contents (Tainted direct review) 90 0 R /T (Tainted direct title) 90 0 R /Subj (Tainted direct subject) 90 0 R /NM (tainted-direct-name) 90 0 R /M (D:20260608172204Z) 90 0 R /A << /S /URI /URI (https://example.com/tainted-direct-state) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [276 700 378 718] /Contents 20 0 R /T 21 0 R /Subj 22 0 R /NM 23 0 R /M 24 0 R /A << /S /URI /URI (https://example.com/indirect-clean-state) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [388 700 510 718] /Contents 30 0 R /T 31 0 R /Subj 32 0 R /NM 33 0 R /M 34 0 R /A << /S /URI /URI (https://example.com/indirect-tainted-state) >> >>\nendobj\n"
    . "20 0 obj\n(Indirect clean review)\nendobj\n"
    . "21 0 obj\n<FEFF0049006E00640069007200650063007400200063006C00650061006E0020007400690074006C0065>\nendobj\n"
    . "22 0 obj\n(Indirect clean subject)\nendobj\n"
    . "23 0 obj\n(indirect-clean-name)\nendobj\n"
    . "24 0 obj\n(D:20260608172304Z)\nendobj\n"
    . "30 0 obj\n(Indirect tainted review) 90 0 R\nendobj\n"
    . "31 0 obj\n(Indirect tainted title) 90 0 R\nendobj\n"
    . "32 0 obj\n(Indirect tainted subject) 90 0 R\nendobj\n"
    . "33 0 obj\n(indirect-tainted-name) 90 0 R\nendobj\n"
    . "34 0 obj\n(D:20260608172404Z) 90 0 R\nendobj\n"
    . "90 0 obj\n<< /S /JavaScript /JS (staleStringOperandReview\\(\\)) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 510.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'spans' => [
                ['text' => 'Clean state', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tainted state', 'bbox' => [168.0, 700.0, 266.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect clean', 'bbox' => [276.0, 700.0, 378.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect tainted', 'bbox' => [388.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationRows = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0]['annotations'] ?? [];
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotationRows, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$taintedReviewExcluded = !str_contains($encodedReview, 'Tainted direct review')
    && !str_contains($encodedReview, 'Indirect tainted review')
    && !str_contains($encodedReview, 'staleStringOperandReview');
$safeLinksPreserved = array_column($links[0]['links'] ?? [], 'uri') === [
    'https://example.com/clean-state',
    'https://example.com/tainted-direct-state',
    'https://example.com/indirect-clean-state',
    'https://example.com/indirect-tainted-state',
];
$summary = [
    'support_component' => 'native-pdf-link-annotation-string-operand-boundary',
    'native_boundary' => 'tailed direct and indirect Link annotation string state operands are rejected while safe URI promotion remains intact',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'safe_link_targets_preserved' => $safeLinksPreserved,
    'tailed_direct_state_dropped' => !isset($spans[1]['link_annotation_contents'])
        && !isset($spans[1]['link_annotation_subject'])
        && !isset($spans[1]['link_annotation_name']),
    'tailed_indirect_state_dropped' => !isset($spans[3]['link_annotation_contents'])
        && !isset($spans[3]['link_annotation_subject'])
        && !isset($spans[3]['link_annotation_name']),
    'clean_direct_state_preserved' => ($spans[0]['link_annotation_subject'] ?? null) === 'Clean subject',
    'clean_indirect_state_preserved' => ($spans[2]['link_annotation_subject'] ?? null) === 'Indirect clean subject',
    'tainted_review_text_excluded' => $taintedReviewExcluded,
    'visible_text_excludes_annotation_state' => !str_contains($visibleText, 'Clean state review')
        && !str_contains($visibleText, 'Tainted direct review')
        && !str_contains($visibleText, 'Indirect clean review')
        && !str_contains($visibleText, 'Indirect tainted review'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

if (
    $summary['safe_link_targets_preserved'] !== true
    || $summary['tailed_direct_state_dropped'] !== true
    || $summary['tailed_indirect_state_dropped'] !== true
    || $summary['tainted_review_text_excluded'] !== true
) {
    throw new RuntimeException('Unexpected markerPDF link string operand boundary smoke output.');
}

echo '<!-- markerpdf-pdf-annotation-link-string-operand-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
