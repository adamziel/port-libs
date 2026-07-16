<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthNegativeTjHorizontalScaleBoundaryCurrentBasePdf = static function (): string {
    $widths = implode(' ', array_fill(0, 45, '1000'));
    $content = 'BT /Fmirror 12 Tf -100 Tz 1 0 0 1 72 720 Tm [(AB) -1500 (CD)] TJ T* '
        . '100 Tz 1 0 0 1 72 704 Tm [(EF) -1500 (GH)] TJ T* '
        . '-100 Tz 1 0 0 1 72 688 Tm [(IJ) 1500 (KL)] TJ ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] "
        . "/Resources << /Font << /Fmirror 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
        . "/Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 76 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n"
        . $content
        . "\nendstream\nendobj\n"
        . "xref\n0 6\n0000000000 65535 f \n"
        . "trailer\n<< /Root 1 0 R /Size 6 >>\nstartxref\n0\n%%EOF";
};

return [
    'mirrored horizontal scale applies TJ spacing in mirrored advance direction' => static function ($t) use ($fontWidthNegativeTjHorizontalScaleBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeTjHorizontalScaleBoundaryCurrentBasePdf();

        $expectedLines = [
            'AB CD',
            'EF GH',
            'IJKL',
        ];

        $t->same($expectedLines, $extractor->extractTextLines($pdf));
        $t->same($expectedLines, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expectedLines), trim($extractor->extractPlainText($pdf)));

        $styledPages = $extractor->extractStyledTextPages($pdf);
        $pageLines = [];
        foreach (($styledPages[0]['blocks'] ?? []) as $block) {
            foreach (($block['lines'] ?? []) as $line) {
                $pageLines[] = $line;
            }
        }

        $styledLineTexts = array_map(
            static fn (array $line): string => implode('', array_map(static fn (array $span): string => (string) ($span['text'] ?? ''), $line['spans'] ?? [])),
            $pageLines
        );
        $styledText = implode("\n", $styledLineTexts);
        $t->true(str_contains($styledText, 'AB CD'));
        $t->true(str_contains($styledText, 'EF GH'));
        $t->true(str_contains($styledText, 'IJKL'));

        foreach ($pageLines as $line) {
            $t->true(isset($line['bbox']));
            $t->true(($line['bbox'][2] ?? 0.0) > ($line['bbox'][0] ?? 0.0));
            $t->true(($line['bbox'][3] ?? 0.0) > ($line['bbox'][1] ?? 0.0));
        }

        $plainText = trim($extractor->extractPlainText($pdf));
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'EFGH'));
        $t->true(!str_contains($plainText, 'IJ KL'));
        $t->true(!str_contains($plainText, '/Fmirror'));
    },
];
