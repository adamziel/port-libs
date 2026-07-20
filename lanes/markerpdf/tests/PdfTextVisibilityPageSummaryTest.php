<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$digest = static function (array $value): string {
    $encoded = json_encode(
        $value,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
    );

    return hash('sha256', is_string($encoded) ? $encoded : serialize($value));
};

$twoPageVisibilityPdf = static function (): string {
    $pageOne = 'BT /F1 10 Tf '
        . '1 0 0 1 10 50 Tm (visible-one) Tj '
        . '3 Tr 1 0 0 1 10 35 Tm (hidden-mode) Tj '
        . '0 Tr 1 0 0 1 150 50 Tm (outside-one) Tj ET';
    $pageTwo = 'q /Masked gs BT /F1 10 Tf 1 0 0 1 10 150 Tm (masked-two) Tj ET Q '
        . 'BT /F1 10 Tf 1 0 0 1 60 70 Tm (covered-two) Tj ET '
        . 'q 100 0 0 60 50 50 cm /Im8 Do Q';
    $imageBytes = "\x80";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] "
        . "/CropBox [0 0 100 100] /Resources << /Font << /F1 5 0 R >> >> "
        . "/Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
        . "/Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] "
        . "/CropBox [0 0 200 200] /Resources << /Font << /F1 5 0 R >> "
        . "/ExtGState << /Masked << /ca 1 /CA 1 /SMask << /S /Alpha >> >> >> "
        . "/XObject << /Im8 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 "
        . "/ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($imageBytes)
        . " >>\nstream\n{$imageBytes}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'markerpdf emits complete page-scoped visibility counters and risk receipts' => static function (
        TestRunner $t
    ) use ($digest, $twoPageVisibilityPdf): void {
        $visibility = (new PdfTextExtractor())->diagnostics(
            $twoPageVisibilityPdf()
        )['textVisibility'];
        $pages = $visibility['pages'] ?? [];
        $t->same(2, count($pages));
        $t->same([1, 2], array_column($pages, 'page'));

        $pageOne = $pages[0];
        $t->same(true, $pageOne['complete'] ?? null);
        $t->same(1, $pageOne['visibleRuns'] ?? null);
        $t->same(1, $pageOne['visibleOutputRuns'] ?? null);
        $t->same(1, $pageOne['suppressedNonPaintingRuns'] ?? null);
        $t->same(1, $pageOne['suppressedRenderingModeRuns'] ?? null);
        $t->same(0, $pageOne['suppressedZeroAlphaRuns'] ?? null);
        $t->same(0, $pageOne['suppressedOptionalContentRuns'] ?? null);
        $t->same(1, $pageOne['suppressedOutsidePageRuns'] ?? null);
        $t->same([], $pageOne['unresolvedReasonCounts'] ?? null);
        $t->same(0, $pageOne['laterPaintRiskCount'] ?? null);
        $t->same($digest([]), $pageOne['laterPaintRisksDigest'] ?? null);

        $pageTwo = $pages[1];
        $risks = $visibility['laterPaintRisks'] ?? [];
        $t->same(false, $pageTwo['complete'] ?? null);
        $t->same(2, $pageTwo['visibleRuns'] ?? null);
        $t->same(1, $pageTwo['unresolvedRuns'] ?? null);
        $t->same(
            ['ext-gstate-opacity-or-soft-mask' => 1, 'later-paint-occlusion' => 1],
            $pageTwo['unresolvedReasonCounts'] ?? null
        );
        $t->same(1, $pageTwo['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $pageTwo['laterPaintRiskCount'] ?? null);
        $t->same(1, $pageTwo['laterPaintRiskRecordedCount'] ?? null);
        $t->same(0, $pageTwo['laterPaintRiskUnboundCount'] ?? null);
        $t->same(0, $pageTwo['laterPaintRiskTruncatedCount'] ?? null);
        $t->same($digest($risks), $pageTwo['laterPaintRisksDigest'] ?? null);

        foreach ([
            'visibleRuns',
            'visibleOutputRuns',
            'suppressedNonPaintingRuns',
            'suppressedRenderingModeRuns',
            'suppressedZeroAlphaRuns',
            'suppressedOptionalContentRuns',
            'suppressedOutsidePageRuns',
            'suppressedNonPaintingActualTextRuns',
            'suppressedAccessibilityReplacementRuns',
            'unresolvedRuns',
            'unresolvedClippingRuns',
            'unresolvedOcclusionRiskRuns',
            'laterPaintRiskCount',
            'laterPaintRiskRecordedCount',
            'laterPaintRiskUnboundCount',
            'laterPaintRiskTruncatedCount',
        ] as $key) {
            $t->same(
                ($pageOne[$key] ?? null) + ($pageTwo[$key] ?? null),
                $visibility[$key] ?? null,
                $key
            );
        }
    },
];
