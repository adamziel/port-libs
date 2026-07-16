<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $content): string {
    $widths = implode(' ', array_fill(0, 37, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fstate 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextStateOperandCount /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$extract = static function (PdfTextExtractor $extractor, string $pdf): array {
    $pages = $extractor->extractStyledTextPages($pdf);
    $styledLines = [];
    foreach (($pages[0]['blocks'] ?? []) as $block) {
        foreach (($block['lines'] ?? []) as $line) {
            $styledLines[] = $line;
        }
    }
    $line = $styledLines[0] ?? [];
    $spans = $line['spans'] ?? [];

    return [
        'lines' => $extractor->extractTextLines($pdf),
        'plain' => $extractor->extractPlainText($pdf),
        'span_texts' => array_column($spans, 'text'),
        'span_bboxes' => array_column($spans, 'bbox'),
        'line_bbox' => $line['bbox'] ?? null,
        'line_count' => count($styledLines),
    ];
};

$extractor = new PdfTextExtractor();
$tw = $extract($extractor, $buildPdf('BT /Fstate 12 Tf 100 30 Tw 1 0 0 1 72 720 Tm (A ) Tj (B) Tj ET'));
$tc = $extract($extractor, $buildPdf('BT /Fstate 12 Tf 100 6 Tc 1 0 0 1 72 720 Tm (AB) Tj (CD) Tj ET'));
$ts = $extract($extractor, $buildPdf('BT /Fstate 12 Tf 100 6 Ts 1 0 0 1 72 720 Tm (AB) Tj (CD) Tj ET'));
$quote = $extract($extractor, $buildPdf('BT /Fstate 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj 999 24 6 (Tail) " ET'));

$summary = [
    'scenario' => 'wordpress-pdf-font-width-advance-text-state-operand-count-currentbase',
    'source' => 'native-pdf-text-state-and-quote-operator-operand-count-boundary',
    'tw_extra_operands_ignored' => $tw['span_bboxes'] === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 36.0, 12.0]],
    'tc_extra_operands_ignored' => $tc['span_bboxes'] === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'ts_extra_operands_ignored' => $ts['span_bboxes'] === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'quote_extra_leading_operands_rejected' => $quote['lines'] === ['Lead'] && !str_contains($quote['plain'], 'Tail'),
    'wordpress_visible_lines' => [
        'tw' => $tw['lines'],
        'tc' => $tc['lines'],
        'ts' => $ts['lines'],
        'quote' => $quote['lines'],
    ],
    'font_payload_excluded' => !str_contains($tw['plain'] . $tc['plain'] . $ts['plain'] . $quote['plain'], 'TextStateOperandCount')
        && !str_contains($tw['plain'] . $tc['plain'] . $ts['plain'] . $quote['plain'], 'Fstate')
        && !str_contains($tw['plain'] . $tc['plain'] . $ts['plain'] . $quote['plain'], "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach (['tw_extra_operands_ignored', 'tc_extra_operands_ignored', 'ts_extra_operands_ignored', 'quote_extra_leading_operands_rejected', 'font_payload_excluded'] as $check) {
    if ($summary[$check] !== true) {
        throw new RuntimeException('Expected malformed text-state operand count to be rejected before WordPress import: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
    }
}

echo '<!-- markerpdf:pdf-font-width-advance-text-state-operand-count-currentbase ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (['A B', 'ABCD', 'Lead'] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
