<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthQuoteOperandTailBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fqtail 12 Tf 16 TL 30 Tw 4 Tc '
        . '1 0 0 1 72 720 Tm (Lead) Tj '
        . '100 100 (Decoy) 24 " '
        . '1 0 0 1 72 704 Tm (A B) Tj ET';
    $widths = implode(' ', array_fill(0, 69, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fqtail 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+QuoteOperandTail /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 100 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects tailed quote-operator string operands before current advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthQuoteOperandTailBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthQuoteOperandTailBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $blocks = $pages[0]['blocks'] ?? [];
        $secondSpan = $blocks[1]['lines'][0]['spans'][0] ?? [];

        $t->same(['Lead', 'A B'], $extractor->extractTextLines($pdf));
        $t->same(['Lead', 'A B'], $extractor->extractTextRuns($pdf));
        $t->same("Lead\nA B", $plainText);
        $t->same("Lead\nA B\n", $extractor->naiveGetText($pdf));
        $t->same('A B', $secondSpan['text'] ?? null);
        $t->same([0.0, 0.0, 74.0, 12.0], $secondSpan['bbox'] ?? null);
        $t->same([0.0, 0.0, 74.0, 12.0], $blocks[1]['lines'][0]['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Decoy'));
        $t->true(!str_contains($plainText, 'Lead' . "\n" . 'Decoy'));
        $t->true(($secondSpan['bbox'] ?? null) !== [0.0, 0.0, 144.0, 12.0]);
        $t->true(!str_contains($plainText, 'QuoteOperandTail'));
        $t->true(!str_contains($plainText, 'Fqtail'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
