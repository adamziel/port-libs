<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $fontResource, string $fontName, string $content): string {
    $widths = implode(' ', array_fill(0, 37, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /{$fontResource} 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+{$fontName} /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$widths}] >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$styledLines = static function (array $pages): array {
    $lines = [];
    foreach ($pages as $page) {
        foreach ($page['blocks'] ?? [] as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                $lines[] = $line;
            }
        }
    }

    return $lines;
};

$bboxNumbers = static function (array $pages): array {
    $numbers = [];
    foreach ($pages as $page) {
        foreach ($page['blocks'] ?? [] as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                foreach (($line['bbox'] ?? []) as $value) {
                    $numbers[] = (float) $value;
                }
                foreach ($line['spans'] ?? [] as $span) {
                    foreach (($span['bbox'] ?? []) as $value) {
                        $numbers[] = (float) $value;
                    }
                }
            }
        }
    }

    return $numbers;
};

$bboxNumbersAreBounded = static function (array $pages) use ($bboxNumbers): bool {
    $numbers = $bboxNumbers($pages);
    if ($numbers === []) {
        return false;
    }

    foreach ($numbers as $number) {
        if (!is_finite($number) || abs($number) >= 1000.0) {
            return false;
        }
    }

    return true;
};

$huge = '1' . str_repeat('0', 308);
$twContent = 'BT /Ftwbig 12 Tf 16 TL ' . $huge . ' Tw '
    . '1 0 0 1 72 720 Tm (A ) Tj 1 0 0 1 96 720 Tm (B) Tj '
    . 'T* 1 0 0 1 72 704 Tm (C ) Tj 1 0 0 1 96 704 Tm (D) Tj ET';
$quoteContent = 'BT /Fquotehuge 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj '
    . $huge . ' ' . $huge . ' (C D) " ET';

$extractor = new PdfTextExtractor();
$twPdf = $buildPdf('Ftwbig', 'HugeTextStateWordSpacing', $twContent);
$quotePdf = $buildPdf('Fquotehuge', 'HugeQuoteSpacing', $quoteContent);
$twPages = $extractor->extractStyledTextPages($twPdf);
$quotePages = $extractor->extractStyledTextPages($quotePdf);
$twLines = $styledLines($twPages);
$quoteLines = $styledLines($quotePages);
$twPlain = $extractor->extractPlainText($twPdf);
$quotePlain = $extractor->extractPlainText($quotePdf);

$checks = [
    'source' => 'wordpress_pdf_font_text_state_spacing_advance_boundary_currentbase',
    'tw_spacing_rejected' => $extractor->extractTextLines($twPdf) === ['A B', 'C D'],
    'quote_spacing_rejected' => $extractor->extractTextLines($quotePdf) === ['Lead', 'C D'],
    'tw_bbox_bounded' => $bboxNumbersAreBounded($twPages),
    'quote_bbox_bounded' => $bboxNumbersAreBounded($quotePages),
    'tw_styled_span_bboxes' => array_column($twLines[0]['spans'] ?? [], 'bbox') === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 36.0, 12.0]]
        && array_column($twLines[1]['spans'] ?? [], 'bbox') === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 36.0, 12.0]],
    'quote_styled_span_bbox' => array_column($quoteLines[1]['spans'] ?? [], 'bbox') === [[0.0, 0.0, 36.0, 12.0]],
    'visible_text_excludes_font_metadata' => !str_contains($twPlain, 'HugeTextStateWordSpacing')
        && !str_contains($quotePlain, 'HugeQuoteSpacing')
        && !str_contains($twPlain . $quotePlain, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($checks as $name => $passed) {
    if ($passed !== true && str_starts_with((string) $name, 'executes_') === false && $name !== 'source') {
        throw new RuntimeException('Failed markerPDF font text-state spacing advance smoke check: ' . $name);
    }
}

$json = json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    throw new RuntimeException('Failed to encode markerPDF text-state spacing smoke summary.');
}

echo "<!-- markerpdf-font-text-state-spacing-advance-boundary-currentbase\n{$json}\n-->\n";
echo '<p>markerPDF bounded overlarge PDF Tw spacing before WordPress text import.</p>' . "\n";
echo '<p>markerPDF bounded overlarge PDF quote-operator spacing before styled-span bbox import.</p>' . "\n";
