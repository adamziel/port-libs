<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Named docs Indirect state Hidden state) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Named docs state review) /T (Import reviewer) /Subj (Migration link) /NM (named-link-1) /M (D:20260605213631Z) /CA 0.65 /A << /S /URI /URI (https://example.com/named-docs-state) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 252 718] /Contents (Indirect state review) /T 20 0 R /Subj 21 0 R /NM 22 0 R /M 23 0 R /CA 24 0 R /A << /S /URI /URI (https://example.com/indirect-state) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 352 718] /F 2 /Contents (Hidden state review) /T (Hidden reviewer) /Subj (Hidden subject) /NM (hidden-link-1) /M (D:20260605000000Z) /CA 0.25 /A << /S /URI /URI (https://example.com/hidden-state) >> >>\nendobj\n"
    . "20 0 obj\n<FEFF0049006E006400690072006500630074002000720065007600690065007700650072>\nendobj\n"
    . "21 0 obj\n(Indirect migration link)\nendobj\n"
    . "22 0 obj\n(indirect-link-2)\nendobj\n"
    . "23 0 obj\n(D:20260605213700-04'00')\nendobj\n"
    . "24 0 obj\n0.4\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 352.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 352.0, 718.0],
            'spans' => [
                ['text' => 'Named docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect state', 'bbox' => [160.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden state', 'bbox' => [262.0, 700.0, 352.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (($blocks[0]['text'] ?? '') !== '[Named docs](https://example.com/named-docs-state) [Indirect state](https://example.com/indirect-state) Hidden state') {
    throw new RuntimeException('Expected visible links to preserve Markdown while hidden link state stays out of WordPress text.');
}
if (str_contains($encoded, 'hidden-state') || str_contains($encoded, 'Hidden state review')) {
    throw new RuntimeException('Hidden link annotation state was promoted.');
}
if (str_contains($visibleText, 'Migration link') || str_contains($visibleText, 'named-link-1') || str_contains($visibleText, 'indirect-link-2')) {
    throw new RuntimeException('Link annotation review state leaked into visible text.');
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-link-annotation-state-boundary',
    'native_boundary' => 'visible Link annotation /Subj /NM /M /CA review state is carried onto WordPress spans while hidden annotation state is excluded',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_subjects' => array_column($links[0]['links'] ?? [], 'subject'),
    'promoted_link_names' => array_column($links[0]['links'] ?? [], 'name'),
    'promoted_link_modified_dates' => array_column($links[0]['links'] ?? [], 'modified_at'),
    'promoted_link_opacities' => array_column($links[0]['links'] ?? [], 'opacity'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'hidden_link_state_excluded' => !str_contains($encoded, 'hidden-state') && !str_contains($encoded, 'Hidden state review'),
    'annotation_state_text_excluded_from_visible_text' => !str_contains($visibleText, 'Migration link')
        && !str_contains($visibleText, 'named-link-1')
        && !str_contains($visibleText, 'indirect-link-2'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-state-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
