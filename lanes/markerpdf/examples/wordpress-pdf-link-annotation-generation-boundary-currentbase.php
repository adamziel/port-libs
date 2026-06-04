<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current docs Exact jump Stale decoy) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Exact generation destination) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 1 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots 6 1 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 16 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 1 obj\n[7 1 R 8 1 R]\nendobj\n"
    . "7 1 obj\n<< /Type /Annot /Subtype /Link /Rect 40 1 R /F 41 1 R /A 30 1 R /AA << /E 31 1 R >> >>\nendobj\n"
    . "8 1 obj\n<< /Type /Annot /Subtype /Link /Rect [166 700 252 718] /Dest 44 1 R >>\nendobj\n"
    . "13 1 obj\n<< /Names [(exact-target) 17 1 R] >>\nendobj\n"
    . "16 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "17 1 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "30 1 obj\n<< /S /URI /URI (https://example.com/current-generation-link) /Next 32 1 R >>\nendobj\n"
    . "31 1 obj\n<< /S /URI /URI (mailto:current-generation@example.test) >>\nendobj\n"
    . "32 1 obj\n<< /S /GoTo /D (exact-target) >>\nendobj\n"
    . "40 1 obj\n[72 700 158 718]\nendobj\n"
    . "41 1 obj\n4\nendobj\n"
    . "44 1 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
    . "6 0 obj\n[9 0 R]\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 342 718] /A << /S /URI /URI (https://example.com/stale-array-link) >> >>\nendobj\n"
    . "13 0 obj\n<< /Names [(exact-target) 18 0 R] >>\nendobj\n"
    . "18 0 obj\n[3 0 R /Fit]\nendobj\n"
    . "30 0 obj\n<< /S /URI /URI (https://example.com/stale-generation-link) /Next 32 0 R >>\nendobj\n"
    . "31 0 obj\n<< /S /JavaScript /JS (staleHoverLeak\\(\\)) >>\nendobj\n"
    . "32 0 obj\n<< /S /GoTo /D (stale-target) >>\nendobj\n"
    . "40 0 obj\n[72 640 158 658]\nendobj\n"
    . "41 0 obj\n2\nendobj\n"
    . "44 0 obj\n[3 0 R /Fit]\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 342.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 342.0, 718.0],
            'spans' => [
                ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Exact jump', 'bbox' => [166.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale decoy', 'bbox' => [260.0, 700.0, 342.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-generation-boundary',
    'native_boundary' => 'page Annots, Link Rect/F/A/AA, and destination operands resolve only when object generations match the N G R reference',
    'page_link_count' => count($links[0]['links'] ?? []),
    'link_uris' => array_values(array_filter(array_map(
        static fn (array $link): ?string => is_string($link['uri'] ?? null) ? $link['uri'] : null,
        $links[0]['links'] ?? []
    ))),
    'local_destinations' => array_values(array_filter(array_map(
        static fn (array $link): ?string => is_string($link['destination'] ?? null) ? $link['destination'] : null,
        $links[0]['links'] ?? []
    ))),
    'additional_action_uris' => array_values(array_filter(array_map(
        static fn (array $action): ?string => is_string($action['uri'] ?? null) ? $action['uri'] : null,
        $links[0]['links'][0]['additional_actions'] ?? []
    ))),
    'markdown' => $blocks[0]['text'] ?? '',
    'excludes_stale_generation_links' => !str_contains($encodedReview, 'stale-array-link')
        && !str_contains($encodedReview, 'stale-generation-link')
        && !str_contains($encodedReview, 'staleHoverLeak')
        && !str_contains($encodedReview, 'stale-target'),
    'visible_text_excludes_link_targets' => !str_contains($plainText, 'current-generation-link')
        && !str_contains($plainText, 'stale-generation-link')
        && !str_contains($plainText, 'stale-array-link'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-link-annotation-generation-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
