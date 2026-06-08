<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'ignores orphan curve path operators before image XObject clipping review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before curve clip images) Tj ET\n"
            . "q 40 0 0 20 0 0 cm .5 0 .5 .5 0 .5 c W n /Orphan#20C#20Image Do Q\n"
            . "q 40 0 0 20 50 0 cm .25 .25 .5 .5 v W n /Orphan#20V#20Image Do Q\n"
            . "q 40 0 0 20 100 0 cm .25 .25 .5 .5 y W n /Orphan#20Y#20Image Do Q\n"
            . "q 40 0 0 20 150 0 cm 0 0 m .5 0 .5 .5 0 .5 c W n /Valid#20Curve#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After curve clip images) Tj ET';
        $payloads = [
            'Orphan C Image' => 'BT /F1 12 Tf 72 720 Td (Orphan C Curve Image Payload Noise) Tj ET',
            'Orphan V Image' => 'BT /F1 12 Tf 72 720 Td (Orphan V Curve Image Payload Noise) Tj ET',
            'Orphan Y Image' => 'BT /F1 12 Tf 72 720 Td (Orphan Y Curve Image Payload Noise) Tj ET',
            'Valid Curve Image' => 'BT /F1 12 Tf 72 720 Td (Valid Curve Image Payload Noise) Tj ET',
        ];
        $compressed = [];
        foreach ($payloads as $name => $payload) {
            $compressedPayload = gzcompress($payload);
            if (!is_string($compressedPayload)) {
                throw new RuntimeException("Unable to compress {$name} fixture payload.");
            }
            $compressed[$name] = $compressedPayload;
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Orphan#20C#20Image 5 0 R /Orphan#20V#20Image 6 0 R /Orphan#20Y#20Image 7 0 R /Valid#20Curve#20Image 8 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Orphan C Image']) . " >>\nstream\n{$compressed['Orphan C Image']}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Orphan V Image']) . " >>\nstream\n{$compressed['Orphan V Image']}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Orphan Y Image']) . " >>\nstream\n{$compressed['Orphan Y Image']}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Valid Curve Image']) . " >>\nstream\n{$compressed['Valid Curve Image']}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

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
        $t->same(4, $review['image_xobject_count']);
        $t->same(4, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        foreach ([
            'Orphan C Image' => [[40.0, 0.0, 0.0, 20.0, 0.0, 0.0], [0.0, 0.0, 40.0, 20.0]],
            'Orphan V Image' => [[40.0, 0.0, 0.0, 20.0, 50.0, 0.0], [50.0, 0.0, 90.0, 20.0]],
            'Orphan Y Image' => [[40.0, 0.0, 0.0, 20.0, 100.0, 0.0], [100.0, 0.0, 140.0, 20.0]],
        ] as $name => [$matrix, $bbox]) {
            $entry = $entriesByName[$name];
            $t->same(true, $entry['invoked']);
            $t->same(1, $entry['invocation_count']);
            $t->same([$matrix], $entry['invocation_matrices']);
            $t->same([$bbox], $entry['invocation_bboxes']);
            $t->same([], $entry['invocation_clip_bboxes']);
            $t->same([$bbox], $entry['invocation_visible_bboxes']);
            $t->same($bbox, $entry['image_visible_bbox']);
            $t->same(false, $entry['clip_applied']);
            $t->same(false, $entry['clip_reduces_painted_bbox']);
            $t->same(false, $entry['clip_excludes_image']);
            $t->same(1, $entry['painted_invocation_count']);
            $t->same(0, $entry['clip_excluded_invocation_count']);
            $t->same(true, $entry['decoded_with_current_filters']);
            $t->same(strlen($payloads[$name]), $entry['decoded_length']);
            $t->same(hash('sha256', $payloads[$name]), $entry['decoded_sha256']);
            $t->same(false, $entry['payload_in_visible_text']);
        }

        $valid = $entriesByName['Valid Curve Image'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[40.0, 0.0, 0.0, 20.0, 150.0, 0.0]], $valid['invocation_matrices']);
        $t->same([[150.0, 0.0, 190.0, 20.0]], $valid['invocation_bboxes']);
        $t->same([[150.0, 0.0, 170.0, 10.0]], $valid['invocation_clip_bboxes']);
        $t->same([[150.0, 0.0, 170.0, 10.0]], $valid['invocation_visible_bboxes']);
        $t->same([150.0, 0.0, 170.0, 10.0], $valid['image_visible_bbox']);
        $t->same(true, $valid['clip_applied']);
        $t->same(true, $valid['clip_reduces_painted_bbox']);
        $t->same(false, $valid['clip_excludes_image']);
        $t->same(1, $valid['painted_invocation_count']);
        $t->same(0, $valid['clip_excluded_invocation_count']);
        $t->same(true, $valid['decoded_with_current_filters']);
        $t->same(strlen($payloads['Valid Curve Image']), $valid['decoded_length']);
        $t->same(hash('sha256', $payloads['Valid Curve Image']), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before curve clip images', 'After curve clip images'], $extractor->extractTextLines($pdf));
        $t->same("Before curve clip images\nAfter curve clip images", $plainText);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ($payloads as $payload) {
            $t->true(!str_contains($plainText, $payload));
            $t->true(!str_contains($encoded, $payload));
            $t->true(str_contains($encoded, hash('sha256', $payload)));
        }
    },
];
