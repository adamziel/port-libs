<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$integratedBoundaryRegressionEmptyPageTreeFallbackPdf = static function (): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Explicit Empty Page Tree Fallback) Tj ET';
    $privatePayload = 'BT /F1 12 Tf 72 700 Td (Explicit Empty Page Tree Private Leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /WP << /Private 4 0 R >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$integratedBoundaryRegressionBrokenNonEmptyPageTreePdf = static function (): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Broken Nonempty Page Tree Fallback Leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [9 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'aligns renderer DecodeParms after leading null image filter slots' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 '
            . '/Filter [ null /DCTDecode ] /DecodeParms [ 99 0 R << /ColorTransform 0 >> ] >>'
        );

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same('DCTDecode', $plan['image_filter_details'][0]['filter'] ?? null);
        $t->same(0, $plan['image_filter_details'][0]['decode_parms']['color_transform'] ?? null);
        $t->true(($plan['image_filter_details'][0]['decode_parms']['valid_color_transform'] ?? false) === true);
        $t->same(['dctdecode_image_filter_review_only'], $plan['notes']);
        $t->same([], $plan['image_filter_boundary']['malformed_filter_operands'] ?? []);
        $t->same([], $plan['image_filter_boundary']['unresolved_filter_operands'] ?? []);
    },
    'aligns renderer DecodeParms before trailing null image filter slots' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 '
            . '/Filter [ /DCTDecode null ] /DecodeParms [ << /ColorTransform 1 >> 99 0 R ] >>'
        );

        $t->same(['DCTDecode'], $plan['image_filters']);
        $t->same('DCTDecode', $plan['image_filter_details'][0]['filter'] ?? null);
        $t->same(1, $plan['image_filter_details'][0]['decode_parms']['color_transform'] ?? null);
        $t->true(($plan['image_filter_details'][0]['decode_parms']['valid_color_transform'] ?? false) === true);
        $t->same(['dctdecode_image_filter_review_only'], $plan['notes']);
        $t->same([], $plan['image_filter_boundary']['malformed_filter_operands'] ?? []);
        $t->same([], $plan['image_filter_boundary']['unresolved_filter_operands'] ?? []);
    },
    'allows stream-only fallback only for an explicitly empty catalog page tree' => static function (
        TestRunner $t
    ) use ($integratedBoundaryRegressionEmptyPageTreeFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $integratedBoundaryRegressionEmptyPageTreeFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Explicit Empty Page Tree Fallback', $plainText);
        $t->same(['Explicit Empty Page Tree Fallback'], $extractor->extractTextLines($pdf));
        $t->same(['Explicit Empty Page Tree Fallback'], $extractor->extractTextRuns($pdf));
        $t->same("Explicit Empty Page Tree Fallback\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Explicit Empty Page Tree Private Leak'));
    },
    'blocks stream-only fallback for nonempty catalog page trees with unresolved kids' => static function (
        TestRunner $t
    ) use ($integratedBoundaryRegressionBrokenNonEmptyPageTreePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $integratedBoundaryRegressionBrokenNonEmptyPageTreePdf();

        $t->same('', $extractor->extractPlainText($pdf));
        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
];
