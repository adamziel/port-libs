<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_repeated_move_path_image_xobject_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before repeated move image clip) Tj ET\n"
        . "q 10 10 m 100 100 m 120 100 l 120 120 l 100 120 l h W n 150 0 0 150 0 0 cm /Repeated#20Move#20Image Do Q\n"
        . "q 10 10 m 30 10 l 30 30 l 10 30 l h 100 100 m 120 100 l 120 120 l 100 120 l h W n 150 0 0 150 0 0 cm /Multi#20Subpath#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After repeated move image clip) Tj ET';
    $repeatedPayload = 'BT /F1 12 Tf 72 720 Td (Repeated Move Image Payload Noise) Tj ET';
    $multiPayload = 'BT /F1 12 Tf 72 720 Td (Multi Subpath Image Payload Noise) Tj ET';
    $repeatedCompressed = gzcompress($repeatedPayload);
    $multiCompressed = gzcompress($multiPayload);
    if (!is_string($repeatedCompressed) || !is_string($multiCompressed)) {
        throw new RuntimeException('Unable to compress repeated move image XObject fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Repeated#20Move#20Image 5 0 R /Multi#20Subpath#20Image 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($repeatedCompressed) . " >>\nstream\n{$repeatedCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($multiCompressed) . " >>\nstream\n{$multiCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $repeatedPayload, $multiPayload];
}

return [
    'replaces dangling repeated moveto before image XObject clipping boundaries' => static function (TestRunner $t): void {
        [$pdf, $repeatedPayload, $multiPayload] = markerpdf_repeated_move_path_image_xobject_pdf();
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

        $repeated = $entriesByName['Repeated Move Image'];
        $t->same(true, $repeated['invoked']);
        $t->same(1, $repeated['invocation_count']);
        $t->same([[150.0, 0.0, 0.0, 150.0, 0.0, 0.0]], $repeated['invocation_matrices']);
        $t->same([0.0, 0.0, 150.0, 150.0], $repeated['image_unit_bbox']);
        $t->same([[100.0, 100.0, 120.0, 120.0]], $repeated['invocation_clip_bboxes']);
        $t->same([[100.0, 100.0, 120.0, 120.0]], $repeated['invocation_visible_bboxes']);
        $t->same([100.0, 100.0, 120.0, 120.0], $repeated['image_visible_bbox']);
        $t->same(true, $repeated['clip_applied']);
        $t->same(true, $repeated['clip_reduces_painted_bbox']);
        $t->same(false, $repeated['clip_excludes_image']);
        $t->same(true, $repeated['decoded_with_current_filters']);
        $t->same(strlen($repeatedPayload), $repeated['decoded_length']);
        $t->same(hash('sha256', $repeatedPayload), $repeated['decoded_sha256']);
        $t->same(false, $repeated['payload_in_visible_text']);

        $multi = $entriesByName['Multi Subpath Image'];
        $t->same(true, $multi['invoked']);
        $t->same(1, $multi['invocation_count']);
        $t->same([[150.0, 0.0, 0.0, 150.0, 0.0, 0.0]], $multi['invocation_matrices']);
        $t->same([0.0, 0.0, 150.0, 150.0], $multi['image_unit_bbox']);
        $t->same([[10.0, 10.0, 120.0, 120.0]], $multi['invocation_clip_bboxes']);
        $t->same([[10.0, 10.0, 120.0, 120.0]], $multi['invocation_visible_bboxes']);
        $t->same([10.0, 10.0, 120.0, 120.0], $multi['image_visible_bbox']);
        $t->same(true, $multi['clip_applied']);
        $t->same(true, $multi['clip_reduces_painted_bbox']);
        $t->same(false, $multi['clip_excludes_image']);
        $t->same(true, $multi['decoded_with_current_filters']);
        $t->same(strlen($multiPayload), $multi['decoded_length']);
        $t->same(hash('sha256', $multiPayload), $multi['decoded_sha256']);
        $t->same(false, $multi['payload_in_visible_text']);

        $t->same(['Before repeated move image clip', 'After repeated move image clip'], $extractor->extractTextLines($pdf));
        $t->same("Before repeated move image clip\nAfter repeated move image clip", $plainText);
        $t->true(!str_contains($plainText, 'Repeated Move Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Multi Subpath Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $repeatedPayload));
        $t->true(!str_contains($encoded, $multiPayload));
        $t->true(str_contains($encoded, hash('sha256', $repeatedPayload)));
        $t->true(str_contains($encoded, hash('sha256', $multiPayload)));
    },
];
