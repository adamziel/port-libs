<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fqtail 12 Tf 16 TL 30 Tw 4 Tc '
    . '1 0 0 1 72 720 Tm (Lead) Tj '
    . '100 100 (Decoy) 24 " '
    . '1 0 0 1 72 704 Tm (A B) Tj ET';
$widths = implode(' ', array_fill(0, 69, '1000'));
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fqtail 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+QuoteOperandTail /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 100 /Widths [{$widths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$secondSpan = $pages[0]['blocks'][1]['lines'][0]['spans'][0] ?? [];

$summary = [
    'scenario' => 'wordpress-pdf-font-width-advance-quote-operand-tail-currentbase',
    'source' => 'native-pdf-double-quote-text-showing-operand-tail-boundary',
    'visible_lines' => $lines,
    'tailed_quote_string_rejected' => !str_contains($plainText, 'Decoy'),
    'quote_spacing_tail_did_not_poison_advance' => ($secondSpan['bbox'] ?? null) === [0.0, 0.0, 74.0, 12.0],
    'poisoned_quote_spacing_bbox_excluded' => ($secondSpan['bbox'] ?? null) !== [0.0, 0.0, 144.0, 12.0],
    'font_payload_excluded' => !str_contains($plainText, 'QuoteOperandTail') && !str_contains($plainText, 'Fqtail'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $lines !== ['Lead', 'A B']
    || $plainText !== "Lead\nA B"
    || ($secondSpan['text'] ?? null) !== 'A B'
    || ($secondSpan['bbox'] ?? null) !== [0.0, 0.0, 74.0, 12.0]
    || !$summary['tailed_quote_string_rejected']
    || !$summary['poisoned_quote_spacing_bbox_excluded']
    || !$summary['font_payload_excluded']
) {
    throw new RuntimeException('Expected quote-operand tail WordPress smoke to reject decoy text and preserve safe advances: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf:pdf-font-width-advance-quote-operand-tail-currentbase ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
