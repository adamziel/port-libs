<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvancePositionOperandBoundaryCurrentBasePdf = static function (): string {
    $hugeCoordinate = '1' . str_repeat('0', 308);
    $content = 'BT /Fpos 12 Tf '
        . "1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 {$hugeCoordinate} 720 Tm <4344> Tj "
        . "T* 1 0 0 1 72 704 Tm <4546> Tj {$hugeCoordinate} 0 Td <4748> Tj "
        . "T* {$hugeCoordinate} 0 0 1 72 688 Tm <494A> Tj 1 0 0 1 96 688 Tm <4B4C> Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fpos 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeFinitePositionAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 76 /Widths [1000 1000 1000 1000 1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H /I /J /K /L] >>\nendobj\n%%EOF";
};

return [
    'rejects overlarge finite Tm and Td operands before current advance overflow on current base' => static function (TestRunner $t) use ($fontWidthAdvancePositionOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvancePositionOperandBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = [];
        foreach (($pages[0]['blocks'] ?? []) as $block) {
            foreach (($block['lines'] ?? []) as $line) {
                $styledLines[] = $line;
            }
        }

        $spanBboxes = [];
        foreach ($styledLines as $line) {
            foreach (($line['spans'] ?? []) as $span) {
                $spanBboxes[] = $span['bbox'] ?? [];
            }
            $spanBboxes[] = $line['bbox'] ?? [];
        }

        $allBboxNumbersFinite = true;
        $maxBboxMagnitude = 0.0;
        foreach ($spanBboxes as $bbox) {
            foreach ($bbox as $number) {
                $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
                $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
            }
        }

        $t->same(['ABCD', 'EFGH', 'IJKL'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'EF', 'GH', 'IJ', 'KL'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nEFGH\nIJKL", $plainText);
        $t->same("ABCD\nEFGH\nIJKL\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($styledLines[0]['spans'] ?? [], 'text'));
        $t->same(['EF', 'GH'], array_column($styledLines[1]['spans'] ?? [], 'text'));
        $t->same(['IJ', 'KL'], array_column($styledLines[2]['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($styledLines[0]['spans'] ?? [], 'bbox'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($styledLines[1]['spans'] ?? [], 'bbox'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($styledLines[2]['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $styledLines[0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 48.0, 12.0], $styledLines[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 48.0, 12.0], $styledLines[2]['bbox'] ?? null);
        $t->true($allBboxNumbersFinite);
        $t->true($maxBboxMagnitude < 1000.0);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'EF GH'));
        $t->true(!str_contains($plainText, 'IJ KL'));
        $t->true(!str_contains($plainText, 'HugeFinitePositionAdvance'));
        $t->true(!str_contains($plainText, 'Fpos'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
