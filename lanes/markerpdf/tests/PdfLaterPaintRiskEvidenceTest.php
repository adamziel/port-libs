<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithImage = static function (
    string $content,
    string $resource = 'Im26',
    ?string $fontDictionary = null
): string {
    $imageBytes = "\x80";
    $fontDictionary ??= '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica '
        . '/Encoding /WinAnsiEncoding >>';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << /Font << /F1 5 0 R >> /XObject << /{$resource} 6 0 R >> >> "
        . "/Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n{$fontDictionary}\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 "
        . "/ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($imageBytes)
        . " >>\nstream\n{$imageBytes}\nendstream\nendobj\n"
        . "%%EOF";
};

$digest = static function (mixed $value): string {
    $encoded = json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );

    return hash('sha256', is_string($encoded) ? $encoded : serialize($value));
};

return [
    'markerpdf exposes one immutable source and exact image paint risk record' => static function (
        TestRunner $t
    ) use ($pdfWithImage, $digest): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (covered text) Tj ET '
            . 'q 180 0 0 60 60 690 cm /Im26 Do Q';
        $pdf = $pdfWithImage($content);
        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];
        $risks = $visibility['laterPaintRisks'] ?? [];

        $t->same(false, $visibility['complete'] ?? null);
        $t->same(1, $visibility['laterPaintRiskCount'] ?? null);
        $t->same(1, $visibility['laterPaintRiskRecordedCount'] ?? null);
        $t->same(0, $visibility['laterPaintRiskUnboundCount'] ?? null);
        $t->same(0, $visibility['laterPaintRiskTruncatedCount'] ?? null);
        $t->same(1, count($risks));

        $risk = $risks[0];
        $t->same([
            'id',
            'version',
            'sourceSha256',
            'page',
            'sourceOccurrenceIndex',
            'sourceStream',
            'sourceRange',
            'sourceProjectionDigest',
            'textOperation',
            'textBounds',
            'paintOperation',
            'paintStream',
            'paintOperator',
            'paintResource',
            'paintObject',
            'paintSubtype',
            'paintBounds',
            'riskDigest',
        ], array_keys($risk));
        $t->same(1, $risk['version'] ?? null);
        $t->same(hash('sha256', $pdf), $risk['sourceSha256'] ?? null);
        $t->same(1, $risk['page'] ?? null);
        $t->same(0, $risk['sourceOccurrenceIndex'] ?? null);
        $t->same(1, $risk['sourceStream'] ?? null);
        $t->same(['start' => 0, 'end' => strlen('coveredtext')], $risk['sourceRange'] ?? null);
        $t->same(hash('sha256', 'coveredtext'), $risk['sourceProjectionDigest'] ?? null);
        $t->same('Do', $risk['paintOperator'] ?? null);
        $t->same('Im26', $risk['paintResource'] ?? null);
        $t->same(6, $risk['paintObject'] ?? null);
        $t->same('Image', $risk['paintSubtype'] ?? null);
        $t->true(($risk['paintOperation'] ?? -1) > ($risk['textOperation'] ?? PHP_INT_MAX));
        $t->true(is_array($risk['textBounds'] ?? null));
        $t->true(is_array($risk['paintBounds'] ?? null));

        $payload = $risk;
        $id = array_shift($payload);
        $riskDigest = array_pop($payload);
        $t->same($digest($payload), $riskDigest);
        $t->same('pdf-later-paint-risk-' . substr($riskDigest, 0, 32), $id);
        $t->same($digest($risks), $visibility['laterPaintRisksDigest'] ?? null);
    },

    'markerpdf localizes clipped image risk without changing its signed placement bounds' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        $content = 'BT /F1 10 Tf 1 0 0 1 10 20 Tm (inside) Tj ET '
            . 'BT /F1 10 Tf 1 0 0 1 70 100 Tm (outside) Tj ET '
            . 'q 0 0 50 50 re W n 100 0 0 100 0 0 cm /Im26 Do Q';
        $pdf = $pdfWithImage($content);
        $extractor = new PdfTextExtractor();
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $risks = $visibility['laterPaintRisks'] ?? [];
        $placements = $extractor->extractImagePlacements($pdf);

        $t->same(1, $visibility['laterPaintRiskCount'] ?? null);
        $t->same(1, count($risks));
        $t->same(0, $risks[0]['sourceOccurrenceIndex'] ?? null, 'The text outside the effective clip must not be charged.');
        $t->same(1, count($placements));
        $t->same(true, $placements[0]['boundsClipped'] ?? null);
        $t->same($placements[0]['bbox'] ?? null, $risks[0]['paintBounds'] ?? null);
    },

    'markerpdf does not trust image bounds after a rejected CTM' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 300 500 Tm (target) Tj ET '
            . '1 0 0 1 1000001 0 cm 100 0 0 40 -999721 490 cm /Im26 Do';
        $diagnostics = (new PdfTextExtractor())->diagnostics($pdfWithImage($content));

        $t->same(1, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->true(in_array(
            'invalid_content_transform',
            array_column($diagnostics['pageExtractionIssues'] ?? [], 'reason'),
            true
        ));
    },

    'markerpdf charges a direct inline image as later paint at its exact bounds' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        $inlineImage = "q 180 0 0 50 60 690 cm BI /W 1 /H 1 /CS /G /BPC 8 ID \x80 EI Q";
        $coveredPdf = $pdfWithImage(
            'BT /F1 12 Tf 1 0 0 1 72 700 Tm (covered text) Tj ET ' . $inlineImage
        );
        $extractor = new PdfTextExtractor();
        $covered = $extractor->diagnostics($coveredPdf)['textVisibility'];
        $risks = $covered['laterPaintRisks'] ?? [];
        $placements = $extractor->extractImagePlacements($coveredPdf);

        $t->same(1, $covered['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $covered['complete'] ?? null);
        $t->same(1, count($risks));
        $t->same('BI', $risks[0]['paintOperator'] ?? null);
        $t->same('inline-image', $risks[0]['paintResource'] ?? null);
        $t->same(0, $risks[0]['paintObject'] ?? null);
        $t->same('InlineImage', $risks[0]['paintSubtype'] ?? null);
        $t->same($placements[0]['bbox'] ?? null, $risks[0]['paintBounds'] ?? null);

        $disjoint = (new PdfTextExtractor())->diagnostics($pdfWithImage(
            'BT /F1 12 Tf 1 0 0 1 300 500 Tm (distant text) Tj ET ' . $inlineImage
        ))['textVisibility'];
        $t->same(0, $disjoint['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $disjoint['complete'] ?? null);
    },

    'markerpdf proves every cubic component disjoint in a false-overlap stroke' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        // The first cubic is the reduced Muir geometry: its expanded axis-
        // aligned box overlaps the rotated glyph even though its bounded
        // stroke does not. A second connected cubic keeps this on the
        // multi-component path that previously lost the exact proof.
        $content = 'BT /F1 7.889 Tf '
            . '.6847077 -.7288178 .7288178 .6847077 297.0434 529.6103 Tm (T) Tj ET '
            . 'q 1.914 w 1 0 0 1 288.3212 533.3932 cm '
            . '0 0 m 18.445 -19.661 22.803 -21.964 v '
            . '30 -30 35 -35 40 -40 c S Q';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithImage($content)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'markerpdf keeps a crossing component of a multi-cubic stroke fail closed' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 100 100 Tm (ABC) Tj ET '
            . '2 w 100 105 m 110 105 130 105 150 105 c '
            . '160 110 170 120 180 130 c S';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithImage($content)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'markerpdf exhausts aggregate cubic proof work fail closed' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        $text = str_repeat(
            'BT /F1 7.889 Tf '
                . '.6847077 -.7288178 .7288178 .6847077 297.0434 529.6103 Tm (T) Tj ET ',
            300
        );
        $component = '0 0 m 18.445 -19.661 22.803 -21.964 v ';
        $content = $text
            . 'q 1.914 w 1 0 0 1 288.3212 533.3932 cm '
            . str_repeat($component, 300)
            . 'S Q';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithImage($content)
        )['textVisibility'];

        $t->true(($visibility['unresolvedOcclusionRiskRuns'] ?? 0) > 0);
        $t->true(
            ($visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? 0) > 0
        );
        $t->same(false, $visibility['complete'] ?? null);
    },

    'markerpdf does not trust a declared font box without an embedded program' => static function (
        TestRunner $t
    ) use ($pdfWithImage): void {
        $font = '<< /Type /Font /Subtype /TrueType /BaseFont /UnembeddedBounds '
            . '/Encoding /WinAnsiEncoding /FirstChar 65 /LastChar 67 '
            . '/Widths [600 600 600] '
            . '/FontDescriptor << /FontBBox [0 -200 600 500] >> >>';
        $content = 'BT /F1 10 Tf 1 0 0 1 100 100 Tm (ABC) Tj ET '
            . '0.5 w 90 107 m 140 107 l S';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithImage($content, 'Im26', $font)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'markerpdf uses verified embedded font bounds for the TraceMonkey page-eleven border' => static function (
        TestRunner $t
    ): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf';
        $pdf = is_file($path) ? file_get_contents($path) : false;
        $t->true(is_string($pdf) && $pdf !== '', 'Expected the TraceMonkey fixture.');
        if (!is_string($pdf) || $pdf === '') {
            return;
        }

        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];
        $pageEleven = null;
        foreach ($visibility['pages'] ?? [] as $page) {
            if (($page['page'] ?? null) === 11) {
                $pageEleven = $page;
                break;
            }
        }

        $t->same(0, $pageEleven['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(0, $pageEleven['laterPaintRiskRecordedCount'] ?? null);
        $t->same(0, $pageEleven['laterPaintRiskUnboundCount'] ?? null);
    },

    'markerpdf bounds and reports a truncated later paint risk inventory' => static function (
        TestRunner $t
    ) use ($pdfWithImage, $digest): void {
        $textOperations = [];
        for ($index = 0; $index < 65; $index++) {
            $textOperations[] = sprintf(
                'BT /F1 12 Tf 1 0 0 1 72 700 Tm (risk-%02d) Tj ET',
                $index
            );
        }
        $content = implode(' ', $textOperations)
            . ' q 180 0 0 60 60 690 cm /Im26 Do Q';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithImage($content)
        )['textVisibility'];
        $risks = $visibility['laterPaintRisks'] ?? [];

        $t->same(false, $visibility['complete'] ?? null);
        $t->same(65, $visibility['laterPaintRiskCount'] ?? null);
        $t->same(64, $visibility['laterPaintRiskRecordedCount'] ?? null);
        $t->same(0, $visibility['laterPaintRiskUnboundCount'] ?? null);
        $t->same(1, $visibility['laterPaintRiskTruncatedCount'] ?? null);
        $t->same(64, count($risks));
        $t->same($digest($risks), $visibility['laterPaintRisksDigest'] ?? null);
        $t->same(64, count(array_unique(array_column($risks, 'id'))));
        $t->same(64, count(array_unique(array_column($risks, 'riskDigest'))));
        $page = $visibility['pages'][0] ?? [];
        $t->same(false, $page['complete'] ?? null);
        $t->same(65, $page['laterPaintRiskCount'] ?? null);
        $t->same(64, $page['laterPaintRiskRecordedCount'] ?? null);
        $t->same(0, $page['laterPaintRiskUnboundCount'] ?? null);
        $t->same(1, $page['laterPaintRiskTruncatedCount'] ?? null);
        $t->same($digest($risks), $page['laterPaintRisksDigest'] ?? null);
    },
];
