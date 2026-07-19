<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cmsyType1VisibilityPdf = static function (bool $includeUnknownGlyph): string {
    $unknownText = $includeUnknownGlyph ? ' 20 0 Td (x) Tj' : '';
    $content = 'BT /Fcmsy 12 Tf 72 720 Td (y) Tj 20 0 Td (z) Tj'
        . $unknownText
        . ' ET 0 100 m 20 100 l S';
    $fontProgram = "%!PS-AdobeFont-1.0: CMSYSubset 1.0\n"
        . "/Encoding 256 array\n"
        . "0 1 255 {1 index exch /.notdef put} for\n"
        . "dup 32 /space put\n"
        . "dup 120 /notInAgl put\n"
        . "dup 121 /dagger put\n"
        . "dup 122 /daggerdbl put\n"
        . "readonly def\n"
        . "eexec\n";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << /Font << /Fcmsy 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CMSYSubset "
        . "/FirstChar 120 /LastChar 122 /Widths [600 600 600] /FontDescriptor 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+CMSYSubset "
        . "/FontBBox [0 -200 800 800] /CharSet (/dagger/daggerdbl/notInAgl) "
        . "/FontFile 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n"
        . $fontProgram
        . "endstream\nendobj\n%%EOF";
};

return [
    'decodes AGL dagger names from embedded Type1 CMSY bytes with positioned operation boxes' => static function (
        TestRunner $t
    ) use ($cmsyType1VisibilityPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cmsyType1VisibilityPdf(false);
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];

        $t->same(["\u{2020}", "\u{2021}"], $extractor->extractTextRuns($pdf));
        $t->same(["\u{2020}", "\u{2021}"], array_column($positioned, 'text'));
        $t->same([72.0, 92.0], array_column($positioned, 'textX1'));
        foreach ($positioned as $run) {
            $t->true($run['x2'] > $run['x1']);
            $t->true($run['y2'] > $run['y1']);
        }
        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'keeps unknown embedded Type1 glyph-name operations fail closed' => static function (
        TestRunner $t
    ) use ($cmsyType1VisibilityPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cmsyType1VisibilityPdf(true);
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];

        $t->same(["\u{2020}", "\u{2021}"], $extractor->extractTextRuns($pdf));
        $t->same(["\u{2020}", "\u{2021}"], array_column($positioned, 'text'));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'x'));
        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(['later-paint-occlusion'], $visibility['unresolvedReasons'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },
];
