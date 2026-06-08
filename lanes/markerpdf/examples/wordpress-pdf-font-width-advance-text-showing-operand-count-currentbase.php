<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$makePdf = static function (string $malformedOperator): string {
    $content = 'BT /Fshow 12 Tf '
        . '1 0 0 1 72 720 Tm (Lead) Tj '
        . $malformedOperator . ' '
        . '72 0 Td (Safe) Tj ET';
    $widths = implode(' ', array_fill(0, 90, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fshow 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextShowingOperandCount /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 121 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$tjPdf = $makePdf('100 (Decoy) Tj');
$tjLines = $extractor->extractTextLines($tjPdf);
$tjPlain = $extractor->extractPlainText($tjPdf);
$tjPages = $extractor->extractStyledTextPages($tjPdf);
$tjSpans = $tjPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$tjArrayPdf = $makePdf('[(Bad)] [(Array)] TJ');
$tjArrayLines = $extractor->extractTextLines($tjArrayPdf);
$tjArrayPlain = $extractor->extractPlainText($tjArrayPdf);
$tjArrayPages = $extractor->extractStyledTextPages($tjArrayPdf);
$tjArraySpans = $tjArrayPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$summary = [
    'scenario' => 'wordpress-pdf-font-width-advance-text-showing-operand-count-currentbase',
    'source' => 'native-pdf-tj-tjarray-text-showing-operand-count-boundary',
    'tj_visible_lines' => $tjLines,
    'tj_array_visible_lines' => $tjArrayLines,
    'extra_tj_operand_rejected' => !str_contains($tjPlain, 'Decoy'),
    'extra_tj_operand_did_not_advance' => array_column($tjSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [72.0, 0.0, 120.0, 12.0]],
    'extra_tj_array_operand_rejected' => !str_contains($tjArrayPlain, 'Bad') && !str_contains($tjArrayPlain, 'Array'),
    'extra_tj_array_operand_did_not_advance' => array_column($tjArraySpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [72.0, 0.0, 120.0, 12.0]],
    'font_payload_excluded' => !str_contains($tjPlain . $tjArrayPlain, 'TextShowingOperandCount') && !str_contains($tjPlain . $tjArrayPlain, 'Fshow'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $tjLines !== ['Lead Safe']
    || $tjPlain !== 'Lead Safe'
    || array_column($tjSpans, 'text') !== ['Lead', 'Safe']
    || array_column($tjSpans, 'bbox') !== [[0.0, 0.0, 48.0, 12.0], [72.0, 0.0, 120.0, 12.0]]
    || $tjArrayLines !== ['Lead Safe']
    || $tjArrayPlain !== 'Lead Safe'
    || array_column($tjArraySpans, 'text') !== ['Lead', 'Safe']
    || array_column($tjArraySpans, 'bbox') !== [[0.0, 0.0, 48.0, 12.0], [72.0, 0.0, 120.0, 12.0]]
    || !$summary['font_payload_excluded']
) {
    throw new RuntimeException('Expected malformed text-showing operands to be rejected before WordPress import: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf:pdf-font-width-advance-text-showing-operand-count-currentbase ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($tjLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
