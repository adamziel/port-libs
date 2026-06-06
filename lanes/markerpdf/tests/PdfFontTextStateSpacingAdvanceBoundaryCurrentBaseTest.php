<?php
declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontTextStateTwAdvanceBoundaryCurrentBasePdf = static function (): string {
    $huge = '1' . str_repeat('0', 308);
    $widths = implode(' ', array_fill(0, 37, '1000'));
    $content = 'BT /Ftwbig 12 Tf 16 TL ' . $huge . ' Tw '
        . '1 0 0 1 72 720 Tm (A ) Tj 1 0 0 1 96 720 Tm (B) Tj '
        . 'T* 1 0 0 1 72 704 Tm (C ) Tj 1 0 0 1 96 704 Tm (D) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftwbig 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeTextStateWordSpacing /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$widths}] >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontTextStateQuoteAdvanceBoundaryCurrentBasePdf = static function (): string {
    $huge = '1' . str_repeat('0', 308);
    $widths = implode(' ', array_fill(0, 37, '1000'));
    $content = 'BT /Fquotehuge 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj '
        . $huge . ' ' . $huge . ' (C D) " ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fquotehuge 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeQuoteSpacing /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$widths}] >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontTextStateSpacingBboxNumbers = static function (array $pages): array {
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

$fontTextStateSpacingStyledLines = static function (array $pages): array {
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

$fontTextStateSpacingAllBboxesFinite = static function (array $pages): bool {
    foreach ($pages as $page) {
        foreach ($page['blocks'] ?? [] as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                foreach (($line['bbox'] ?? []) as $value) {
                    if (!is_finite((float) $value)) {
                        return false;
                    }
                }
                foreach ($line['spans'] ?? [] as $span) {
                    foreach (($span['bbox'] ?? []) as $value) {
                        if (!is_finite((float) $value)) {
                            return false;
                        }
                    }
                }
            }
        }
    }

    return true;
};

return [
    'rejects overlarge finite Tw word spacing before current advance bboxes on current base' => static function (TestRunner $t) use (
        $fontTextStateTwAdvanceBoundaryCurrentBasePdf,
        $fontTextStateSpacingStyledLines,
        $fontTextStateSpacingBboxNumbers,
        $fontTextStateSpacingAllBboxesFinite
    ): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontTextStateTwAdvanceBoundaryCurrentBasePdf();
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = $fontTextStateSpacingStyledLines($pages);
        $firstLine = $styledLines[0] ?? [];
        $secondLine = $styledLines[1] ?? [];
        $bboxNumbers = $fontTextStateSpacingBboxNumbers($pages);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A B', 'C D'], $extractor->extractTextLines($pdf));
        $t->same(['A ', 'B', 'C ', 'D'], $extractor->extractTextRuns($pdf));
        $t->same("A B\nC D", $plainText);
        $t->same("A B\nC D\n", $extractor->naiveGetText($pdf));
        $t->same(['A ', 'B'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same(['C ', 'D'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true($fontTextStateSpacingAllBboxesFinite($pages));
        $t->true($bboxNumbers !== [] && max(array_map('abs', $bboxNumbers)) < 1000.0);
        $t->true(!str_contains($plainText, 'HugeTextStateWordSpacing'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects overlarge finite quote operator spacing before styled advance bboxes on current base' => static function (TestRunner $t) use (
        $fontTextStateQuoteAdvanceBoundaryCurrentBasePdf,
        $fontTextStateSpacingStyledLines,
        $fontTextStateSpacingBboxNumbers,
        $fontTextStateSpacingAllBboxesFinite
    ): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontTextStateQuoteAdvanceBoundaryCurrentBasePdf();
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = $fontTextStateSpacingStyledLines($pages);
        $firstLine = $styledLines[0] ?? [];
        $secondLine = $styledLines[1] ?? [];
        $bboxNumbers = $fontTextStateSpacingBboxNumbers($pages);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Lead', 'C D'], $extractor->extractTextLines($pdf));
        $t->same(['Lead', 'C D'], $extractor->extractTextRuns($pdf));
        $t->same("Lead\nC D", $plainText);
        $t->same("Lead\nC D\n", $extractor->naiveGetText($pdf));
        $t->same(['Lead'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same(['C D'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 48.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same([[0.0, 0.0, 36.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true($fontTextStateSpacingAllBboxesFinite($pages));
        $t->true($bboxNumbers !== [] && max(array_map('abs', $bboxNumbers)) < 1000.0);
        $t->true(!str_contains($plainText, 'HugeQuoteSpacing'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
