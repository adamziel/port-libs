<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontDescriptorMissingWidthBoundaryCurrentBasePdf = static function (string $descriptorOperands, string $extraObjects = ''): string {
    $content = 'BT /Fdesc 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdesc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DescriptorMissingWidthBoundary /Encoding /WinAnsiEncoding /FontDescriptor 7 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /FontDescriptor /FontName /DescriptorMissingWidthBoundary /Flags 4 {$descriptorOperands} >>\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

return [
    'rejects tailed direct FontDescriptor MissingWidth operands before current advance gaps' => static function (
        TestRunner $t
    ) use ($fontDescriptorMissingWidthBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontDescriptorMissingWidthBoundaryCurrentBasePdf('/MissingWidth 250 1000 /StemV 80');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]]);
        $t->true(!str_contains($plainText, 'DescriptorMissingWidthBoundary'));
        $t->true(!str_contains($plainText, 'Fdesc'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed indirect FontDescriptor MissingWidth helpers before current advance gaps' => static function (
        TestRunner $t
    ) use ($fontDescriptorMissingWidthBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontDescriptorMissingWidthBoundaryCurrentBasePdf(
            '/MissingWidth 8 0 R 1000 /StemV 80',
            "8 0 obj\n250\nendobj\n"
        );
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]]);
        $t->true(!str_contains($plainText, 'DescriptorMissingWidthBoundary'));
        $t->true(!str_contains($plainText, 'Fdesc'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses top-level FontDescriptor MissingWidth instead of nested decoys before current advance gaps' => static function (
        TestRunner $t
    ) use ($fontDescriptorMissingWidthBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontDescriptorMissingWidthBoundaryCurrentBasePdf('/Nested << /MissingWidth 250 >> /MissingWidth 1000');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]]);
        $t->true(!str_contains($plainText, 'DescriptorMissingWidthBoundary'));
        $t->true(!str_contains($plainText, 'Fdesc'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
