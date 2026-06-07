<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_indirect_imagemask_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect ImageMask) Tj ET\n"
        . "0.3 g q 12 0 0 6 72 690 cm /Indirect#20Stencil Do Q\n"
        . "q 9 0 0 9 104 690 cm /Ordinary#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After indirect ImageMask) Tj ET';
    $stencilPayload = 'BT /F1 12 Tf 72 720 Td (Indirect ImageMask Stencil Payload Noise) Tj ET';
    $ordinaryPayload = 'BT /F1 12 Tf 72 720 Td (Ordinary ImageMask False Payload Noise) Tj ET';
    $stencilCompressed = gzcompress($stencilPayload);
    $ordinaryCompressed = gzcompress($ordinaryPayload);
    if (!is_string($stencilCompressed) || !is_string($ordinaryCompressed)) {
        throw new RuntimeException('Unable to compress indirect ImageMask fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Indirect#20Stencil 5 0 R /Ordinary#20Image 8 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask 6 0 R /Filter /FlateDecode /Decode [1 0] /Length " . strlen($stencilCompressed) . " >>\nstream\n{$stencilCompressed}\nendstream\nendobj\n"
        . "6 0 obj\ntrue\nendobj\n"
        . "7 0 obj\nfalse\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Private << /ImageMask true >> /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /ImageMask 7 0 R /Filter /FlateDecode /Length " . strlen($ordinaryCompressed) . " >>\nstream\n{$ordinaryCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $stencilPayload, $ordinaryPayload];
}

return [
    'resolves top-level indirect ImageMask booleans before image XObject review' => static function (TestRunner $t): void {
        [$pdf, $stencilPayload, $ordinaryPayload] = markerpdf_image_xobject_indirect_imagemask_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $stencil = $entriesByName['Indirect Stencil'];
        $t->same(true, $stencil['invoked']);
        $t->same(true, $stencil['image_mask']);
        $t->same(1, $stencil['bits_per_component']);
        $t->same(null, $stencil['color_space']);
        $t->same(true, $stencil['image_mask_uses_current_nonstroking_color']);
        $t->same(true, $stencil['image_mask_paint_color_review_only']);
        $t->same([
            [
                'color_space' => 'DeviceGray',
                'components' => [0.3],
                'pattern_name' => null,
                'operator' => 'g',
                'review_only' => true,
            ],
        ], $stencil['image_mask_paint_colors']);
        $t->same([[12.0, 0.0, 0.0, 6.0, 72.0, 690.0]], $stencil['invocation_matrices']);
        $t->same([72.0, 690.0, 84.0, 696.0], $stencil['image_unit_bbox']);
        $t->same(true, $stencil['decoded_with_current_filters']);
        $t->same(strlen($stencilPayload), $stencil['decoded_length']);
        $t->same(hash('sha256', $stencilPayload), $stencil['decoded_sha256']);
        $t->same(false, $stencil['payload_in_visible_text']);

        $ordinary = $entriesByName['Ordinary Image'];
        $t->same(true, $ordinary['invoked']);
        $t->same(false, $ordinary['image_mask']);
        $t->same(8, $ordinary['bits_per_component']);
        $t->same('DeviceRGB', $ordinary['color_space']);
        $t->same([], $ordinary['image_mask_paint_colors']);
        $t->same(false, $ordinary['image_mask_paint_color_review_only']);
        $t->same([[9.0, 0.0, 0.0, 9.0, 104.0, 690.0]], $ordinary['invocation_matrices']);
        $t->same([104.0, 690.0, 113.0, 699.0], $ordinary['image_unit_bbox']);
        $t->same(true, $ordinary['decoded_with_current_filters']);
        $t->same(strlen($ordinaryPayload), $ordinary['decoded_length']);
        $t->same(hash('sha256', $ordinaryPayload), $ordinary['decoded_sha256']);
        $t->same(false, $ordinary['payload_in_visible_text']);

        $t->same(['Before indirect ImageMask', 'After indirect ImageMask'], $extractor->extractTextLines($pdf));
        $t->same("Before indirect ImageMask\nAfter indirect ImageMask", $plainText);
        $t->true(!str_contains($plainText, 'Indirect ImageMask Stencil Payload Noise'));
        $t->true(!str_contains($plainText, 'Ordinary ImageMask False Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $stencilPayload));
        $t->true(!str_contains($encoded, $ordinaryPayload));
        $t->true(str_contains($encoded, hash('sha256', $stencilPayload)));
        $t->true(str_contains($encoded, hash('sha256', $ordinaryPayload)));
    },
];
