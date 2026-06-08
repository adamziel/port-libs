<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineDctPaddedSoiBoundaryPdf = static function (string $label, string $prefix): array {
    $before = "BT /F1 12 Tf 72 720 Td (Before {$label}) Tj ET";
    $after = "BT /F1 12 Tf 72 680 Td (After {$label}) Tj ET";
    $leak = "BT /F1 12 Tf 72 700 Td ({$label} leak) Tj ET";
    $segmentPayload = "{$label} APP bytes before tokenizer decoy EI {$leak} still image bytes";
    $jpegPayload = $prefix
        . "\xff\xd8"
        . "\xff\xe0" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
        . "\xff\xd9";
    $content = $before . "\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n{$jpegPayload}\nEI\n"
        . $after;

    return [
        'label' => $label,
        'dictionary' => '/W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode',
        'payload' => $jpegPayload,
        'expected' => [
            "Before {$label}",
            "After {$label}",
        ],
        'pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "%%EOF",
    ];
};

return [
    'keeps padded inline DCTDecode SOI payloads closed until real JPEG EOI before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($inlineDctPaddedSoiBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $fixtures = [
            $inlineDctPaddedSoiBoundaryPdf('NUL padded inline DCT', "\0\0"),
            $inlineDctPaddedSoiBoundaryPdf('BOM marker-fill inline DCT', "\xef\xbb\xbf\xff"),
        ];

        foreach ($fixtures as $fixture) {
            $plainText = $extractor->extractPlainText($fixture['pdf']);
            $review = $renderer->inlineImageReviewPlan($fixture['dictionary'], $fixture['payload']);

            $t->same($fixture['expected'], $extractor->extractTextLines($fixture['pdf']));
            $t->same($fixture['expected'], $extractor->extractTextRuns($fixture['pdf']));
            $t->same(implode("\n", $fixture['expected']), $plainText);
            $t->same(implode("\n", $fixture['expected']) . "\n", $extractor->naiveGetText($fixture['pdf']));
            $t->true(!str_contains($plainText, $fixture['label'] . ' leak'));
            $t->true(!str_contains($plainText, $fixture['label'] . ' APP bytes'));
            $t->true(!str_contains($plainText, 'still image bytes'));
            $t->true(!str_contains($plainText, 'tokenizer decoy EI'));

            $t->same(['DCTDecode'], $review['image_filters']);
            $t->same(['DCTDecode'], $review['image_filter_boundary']['preview_only_filters']);
            $t->same(false, $review['image_filter_boundary']['native_raster_decode']);
            $t->same(true, $review['inline_image_review_only']);
            $t->same(true, $review['inline_image_payload_excluded_from_text']);
            $t->same(true, $review['inline_image']['excluded_from_visible_text']);
            $t->same(false, $review['inline_image']['native_raster_decode']);
            $t->contains('inline_dct_image_filter_review_only', implode(',', $review['notes']));
        }
    },
];
