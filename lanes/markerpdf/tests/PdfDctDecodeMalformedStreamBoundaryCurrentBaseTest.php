<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeMalformedStreamBoundaryCurrentBaseFixture = static function (string $payload, string $label): array {
    $before = "BT /F1 12 Tf 72 720 Td (Before {$label}) Tj ET";
    $after = "BT /F1 12 Tf 72 680 Td (After {$label}) Tj ET";
    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    $rendererObjects = [
        30 => '[ /ICCBased 31 0 R ]',
        31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
    ];
    $rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$payload}\nendstream";

    return [
        'pdf' => $pdf,
        'renderer_image' => $rendererImage,
        'renderer_objects' => $rendererObjects,
        'payload' => $payload,
        'label' => $label,
        'expected_lines' => ["Before {$label}", "After {$label}"],
    ];
};

return [
    'records malformed DCTDecode stream boundaries fail closed before WordPress text handoff' => static function (TestRunner $t) use ($pdfDctDecodeMalformedStreamBoundaryCurrentBaseFixture): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();

        $fixtures = [
            [
                $pdfDctDecodeMalformedStreamBoundaryCurrentBaseFixture(
                    'not a jpeg BT /F1 12 Tf 72 700 Td (Malformed DCT no SOI leak) Tj ET',
                    'malformed DCT no SOI'
                ),
                'missing_jpeg_soi',
                null,
            ],
            [
                $pdfDctDecodeMalformedStreamBoundaryCurrentBaseFixture(
                    "\xff\xd8\xff\xe0JFIF\0 incomplete BT /F1 12 Tf 72 700 Td (Malformed DCT no EOI leak) Tj ET",
                    'malformed DCT no EOI'
                ),
                'missing_jpeg_eoi',
                0,
            ],
        ];

        foreach ($fixtures as [$fixture, $invalidReason, $expectedSoiOffset]) {
            $plainText = $extractor->extractPlainText($fixture['pdf']);
            $review = $extractor->extractImageXObjectBoundaryReview($fixture['pdf']);
            $entry = $review['entries'][0] ?? null;
            $boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? null) : null;
            $rendererPreview = $renderer->iccBasedImageStreamPreviewRows(
                $fixture['renderer_image'],
                $fixture['renderer_objects']
            );
            $rendererBoundary = $rendererPreview['image_stream']['dctdecode_stream_boundary'] ?? null;

            $t->same($fixture['expected_lines'], $extractor->extractTextLines($fixture['pdf']));
            $t->same($fixture['expected_lines'], $extractor->extractTextRuns($fixture['pdf']));
            $t->same(implode("\n", $fixture['expected_lines']), $plainText);
            $t->same(implode("\n", $fixture['expected_lines']) . "\n", $extractor->naiveGetText($fixture['pdf']));
            $t->true(!str_contains($plainText, 'Malformed DCT no SOI leak'));
            $t->true(!str_contains($plainText, 'Malformed DCT no EOI leak'));
            $t->true(!str_contains($plainText, 'JFIF'));

            $t->true(is_array($entry), 'Image XObject review row should be present.');
            $t->same(['DCTDecode'], $entry['filters'] ?? null);
            $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
            $t->same(false, $entry['native_raster_decode'] ?? null);
            $t->same(false, $entry['decoded_with_current_filters'] ?? null);
            $t->same(false, $entry['payload_in_visible_text'] ?? null);
            $t->same(strlen($fixture['payload']), $entry['raw_length'] ?? null);
            $t->true(is_array($boundary), 'Malformed DCT stream boundary should be explicit.');
            $t->same('dctdecode_jpeg_marker_boundary_unverified', $boundary['source'] ?? null);
            $t->same(false, $boundary['valid_jpeg_marker_boundary'] ?? null);
            $t->same($invalidReason, $boundary['invalid_reason'] ?? null);
            $t->same($expectedSoiOffset, $boundary['jpeg_soi_offset'] ?? null);
            $t->same(null, $boundary['jpeg_eoi_end_offset'] ?? null);
            $t->same(false, $boundary['jpeg_marker_framing_used'] ?? null);
            $t->same(false, $boundary['native_raster_decode'] ?? null);
            $t->same(false, $boundary['payload_in_visible_text'] ?? null);

            $t->same(true, $rendererPreview['review_only_image_stream']);
            $t->same(['DCTDecode'], $rendererPreview['image_stream']['filters']);
            $t->same(['DCTDecode'], $rendererPreview['image_stream']['preview_only_filters']);
            $t->same(false, $rendererPreview['image_stream']['decoded_with_current_filters']);
            $t->same(false, $rendererPreview['image_stream']['decode_failed']);
            $t->same(strlen($fixture['payload']), $rendererPreview['image_stream']['raw_length']);
            $t->true(is_array($rendererBoundary), 'Renderer malformed DCT boundary should be explicit.');
            $t->same('dctdecode_jpeg_marker_boundary_unverified', $rendererBoundary['source'] ?? null);
            $t->same(false, $rendererBoundary['valid_jpeg_marker_boundary'] ?? null);
            $t->same($invalidReason, $rendererBoundary['invalid_reason'] ?? null);
            $t->same($expectedSoiOffset, $rendererBoundary['jpeg_soi_offset'] ?? null);
            $t->same(false, $rendererBoundary['native_raster_decode'] ?? null);
            $t->same([], $rendererPreview['pixels']);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $review['executes_external_pdf_tools']);
        }
    },
];
